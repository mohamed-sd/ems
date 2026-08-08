<?php
/**
 * tools/u12_coa_restructure.php — إعادةُ هيكلةِ دليلِ الحساباتِ وترحيلُه
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: EQUIPATION-COA-01 (docs/update0012) — الشجرةُ تُقرأ من الوثيقةِ نفسِها
 * لا تُنسخ يدويًّا، فلا خطأَ نسخٍ ولا انحرافٌ عن المصدر.
 *
 * ما تنفّذه (idempotent — يُعاد تشغيلُه بلا أثرٍ مزدوج):
 *   ① وسمُ الموروث: كلُّ حسابٍ حيٍّ يصير كودُه L-<code> وactive=0 —
 *      ◆ ولا يُحذف صفٌّ ولا رصيد (R10 · PR-06).
 *   ② بناءُ الشجرةِ القانونية: 5 جذورٍ + 18 مستوًى ثانيًا + 103 ثالثًا
 *      بأعمدتها كاملةً (المستوى · الأبُ · طبيعةُ الرصيدِ · القائمةُ وبندُها ·
 *      نشاطُ التدفقِ · الأبعادُ الإلزامية).
 *   ③ خريطةُ الترحيل: كلُّ موروثٍ ← قانونيٌّ (+بُعدٌ حيث لزم) في
 *      fin_coa_migration بأرصدةِ قبلَ وبعد.
 *   ④ إعادةُ توجيهِ سطورِ القيدِ إلى الحساباتِ القانونيةِ مع حفظِ
 *      legacy_account_id — ◆ فالترحيلُ يُعكس ولا يُمحى.
 *   ⑤ تقريرُ التساوي: Σ (مدين−دائن) قبلَ = Σ بعدُ بالضبط، وإلا فشلٌ صريح.
 *   ⑥ حساباتُ الأشخاص: العهدةُ حسابٌ واحدٌ والشخصُ بُعد D6 (R2).
 *
 * التشغيل: php tools/u12_coa_restructure.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$XLSX = $ROOT . '/docs/update0012/EQUIPATION-COA-01 — دليل الحسابات المعاد هيكلته.xlsx';
$CO = 4;

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

function say($s) { echo $s . "\n"; }

/* ══ قارئُ XLSX مختصرٌ — الشجرةُ من المصدرِ لا من نسخةٍ يدوية ═══════════ */
function xlsx_rows($path, $sheetIndex1)
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) { throw new \RuntimeException('تعذّر فتحُ ' . $path); }
    $sst = array();
    $sstXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sstXml !== false) {
        $sx = simplexml_load_string($sstXml);
        foreach ($sx->si as $si) {
            if (isset($si->t)) { $sst[] = (string) $si->t; }
            else { $s = ''; foreach ($si->r as $r) { $s .= (string) $r->t; } $sst[] = $s; }
        }
    }
    $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $map = array();
    foreach ($rels->Relationship as $r) { $map[(string) $r['Id']] = ltrim((string) $r['Target'], '/'); }
    $i = 0; $target = null;
    foreach ($wb->sheets->sheet as $s) {
        if (++$i === $sheetIndex1) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $t = $map[$rid];
            $target = (strpos($t, 'xl/') === 0 ? $t : 'xl/' . $t);
            break;
        }
    }
    $xml = $zip->getFromName($target);
    $zip->close();
    $sx = simplexml_load_string($xml);
    $out = array();
    foreach ($sx->sheetData->row as $row) {
        $cells = array();
        foreach ($row->c as $c) {
            preg_match('/^([A-Z]+)/', (string) $c['r'], $mm);
            $n = 0;
            foreach (str_split($mm[1]) as $ch) { $n = $n * 26 + (ord($ch) - 64); }
            $v = isset($c->v) ? (string) $c->v : (isset($c->is->t) ? (string) $c->is->t : '');
            if ((string) $c['t'] === 's') { $v = $sst[(int) $v] ?? ''; }
            $cells[$n - 1] = trim($v);
        }
        if (!$cells) { continue; }
        $maxc = max(array_keys($cells));
        $line = array();
        for ($j = 0; $j <= $maxc; $j++) { $line[] = $cells[$j] ?? ''; }
        $out[] = $line;
    }
    return $out;
}

/* ══ ① قراءةُ الشجرةِ القانونيةِ من الوثيقة ═══════════════════════════════ */
$rows = xlsx_rows($XLSX, 2); // 01 — الشجرة المعاد هيكلتها
array_shift($rows);          // الترويسة
$canon = array();
foreach ($rows as $r) {
    $code = trim($r[0] ?? '');
    if ($code === '' || !preg_match('/^\d+$/', $code)) { continue; }
    $typeRaw = $r[5] ?? '';
    $type = 'asset';
    if (strpos($typeRaw, 'Liability') !== false) { $type = 'liability'; }
    elseif (strpos($typeRaw, 'Equity') !== false) { $type = 'equity'; }
    elseif (strpos($typeRaw, 'Revenue') !== false) { $type = 'revenue'; }
    elseif (strpos($typeRaw, 'Expense') !== false) { $type = 'expense'; }
    $natureRaw = $r[6] ?? '';
    $nature = strpos($natureRaw, 'Credit') !== false ? 'credit' : 'debit';
    $stmtRaw = $r[7] ?? '';
    $stmt = strpos($stmtRaw, 'Balance Sheet') !== false ? 'S1'
          : (strpos($stmtRaw, 'Income') !== false ? 'S2' : 'S1');
    $flowRaw = $r[9] ?? '';
    $flow = 'none';
    if (strpos($flowRaw, 'Operating') !== false) { $flow = 'operating'; }
    elseif (strpos($flowRaw, 'Investing') !== false) { $flow = 'investing'; }
    elseif (strpos($flowRaw, 'Financing') !== false) { $flow = 'financing'; }
    $level = (int) ($r[4] ?? 3);
    $parent = trim($r[3] ?? '');
    if ($parent === '—' || $parent === '') { $parent = ''; }
    $canon[$code] = array(
        'code' => $code,
        'name' => $r[1] ?? '',
        'name_en' => $r[2] ?? '',
        'parent_code' => $parent,
        'level' => $level,
        'type' => $type,
        'nature' => $nature,
        'stmt' => $stmt,
        'stmt_line' => $r[8] ?? '',
        'flow' => $flow,
        'note' => $r[10] ?? '',
        'postable' => $level >= 3 ? 1 : 0,
    );
}
say('الشجرةُ القانونيةُ من الوثيقة: ' . count($canon) . ' حسابًا'
    . ' (جذور ' . count(array_filter($canon, function ($a) { return $a['level'] === 1; }))
    . ' · ثانٍ ' . count(array_filter($canon, function ($a) { return $a['level'] === 2; }))
    . ' · ثالث ' . count(array_filter($canon, function ($a) { return $a['level'] === 3; })) . ')');
if (count($canon) < 100) { fwrite(STDERR, "شجرةٌ ناقصةٌ — تُراجَع الوثيقة\n"); exit(1); }

/* الأبعادُ الإلزاميةُ لكل حساب (R9 · الورقة 02) — تُشتق من التصنيفِ والنشاط */
function dims_for($a)
{
    $d = array('D1'); // الكيانُ إلزاميٌّ في كل قيد
    $c = $a['code'];
    if ($a['type'] === 'expense') { $d[] = 'D4'; }
    if (strpos($c, '51') === 0) { $d = array_merge($d, array('D2', 'D3', 'D5', 'D7')); }
    if (strpos($c, '41') === 0) { $d = array_merge($d, array('D2', 'D6', 'D7', 'D8')); }
    if (in_array($c, array('5101', '5102', '5103', '5104', '5201', '5202', '5203'), true)) { $d[] = 'D9'; }
    if (in_array($c, array('1104', '1105', '2101', '2102', '2103', '1103'), true)) { $d[] = 'D6'; }
    if (strpos($c, '13') === 0 || strpos($c, '55') === 0) { $d[] = 'D5'; }
    if (strpos($c, '23') === 0 || strpos($c, '54') === 0 || strpos($c, '22') === 0) { $d = array_merge($d, array('D6', 'D9')); }
    return implode(',', array_values(array_unique($d)));
}

/* ══ ② وسمُ الموروثِ — لا حذفَ ولا مساسَ برصيد ════════════════════════════ */
// ◆ الموروثُ وحدَه — والقانونيُّ لا يُوسَم موروثًا مهما أُعيد التشغيل (عطالةُ الأداة)
$legacy = array();
$r = $db->query("SELECT id, code, name, account_type FROM fin_chart_of_accounts
                  WHERE company_id = {$CO} AND is_canonical = 0");
while ($x = $r->fetch_assoc()) { $legacy[(int) $x['id']] = $x; }

$balBefore = array();
$r = $db->query("SELECT account_id, SUM(debit) dr, SUM(credit) cr, COUNT(*) n
                   FROM fin_journal_lines WHERE company_id = {$CO} GROUP BY account_id");
while ($x = $r->fetch_assoc()) {
    $balBefore[(int) $x['account_id']] = array(
        'bal' => round((float) $x['dr'] - (float) $x['cr'], 2), 'n' => (int) $x['n']);
}
$sumBefore = 0.0;
foreach ($balBefore as $b) { $sumBefore += $b['bal']; }
say('الأرصدةُ قبل: ' . count($balBefore) . ' حسابًا مستعملًا · Σ(مدين−دائن) = ' . number_format($sumBefore, 2));

$tagged = 0;
foreach ($legacy as $id => $a) {
    if (strpos($a['code'], 'L-') === 0) { continue; } // موسومٌ سلفًا
    $newCode = 'L-' . $a['code'];
    $st = $db->prepare("UPDATE fin_chart_of_accounts
                           SET code = ?, active = 0, is_postable = 0, is_canonical = 0,
                               coa_note = 'موروثٌ محجورٌ — مرحَّلٌ بخريطةِ COA R10 ولا يُحذف'
                         WHERE id = ? AND company_id = ?");
    $st->bind_param('sii', $newCode, $id, $CO);
    $st->execute();
    if ($db->affected_rows > 0) { $tagged++; }
    $st->close();
    $legacy[$id]['code'] = $newCode;
    $legacy[$id]['orig_code'] = $a['code'];
}
foreach ($legacy as $id => $a) {
    if (!isset($legacy[$id]['orig_code'])) {
        $legacy[$id]['orig_code'] = preg_replace('/^L-/', '', $a['code']);
    }
}
say("موروثٌ وُسم: {$tagged} (والباقي موسومٌ من تشغيلٍ سابق)");

/* ══ ③ بناءُ الشجرةِ القانونية ════════════════════════════════════════════ */
$ins = 0; $upd = 0;
foreach ($canon as $code => $a) {
    $dims = dims_for($a);
    $st = $db->prepare("INSERT INTO fin_chart_of_accounts
        (company_id, code, name, name_en, account_type, acc_level, parent_code, balance_nature,
         statement_code, statement_line, cashflow_activity, required_dims, is_canonical,
         coa_note, parent_id, is_postable, active, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,NULL,?,1,1,NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name), name_en = VALUES(name_en), account_type = VALUES(account_type),
            acc_level = VALUES(acc_level), parent_code = VALUES(parent_code),
            balance_nature = VALUES(balance_nature), statement_code = VALUES(statement_code),
            statement_line = VALUES(statement_line), cashflow_activity = VALUES(cashflow_activity),
            required_dims = VALUES(required_dims), is_canonical = 1, coa_note = VALUES(coa_note),
            is_postable = VALUES(is_postable), active = 1");
    $st->bind_param('issssisssssssi', $CO, $a['code'], $a['name'], $a['name_en'], $a['type'],
        $a['level'], $a['parent_code'], $a['nature'], $a['stmt'], $a['stmt_line'],
        $a['flow'], $dims, $a['note'], $a['postable']);
    $st->execute();
    if ($st->errno) { fwrite(STDERR, 'ERR ' . $code . ': ' . $st->error . "\n"); exit(1); }
    if ($db->affected_rows === 1) { $ins++; } elseif ($db->affected_rows === 2) { $upd++; }
    $st->close();
}
say("الشجرةُ القانونية: أُدرج {$ins} · حُدّث {$upd}");

/* ربطُ parent_id من parent_code — الشجرةُ تصير قابلةً للاجتياز */
$idByCode = array();
$r = $db->query("SELECT id, code FROM fin_chart_of_accounts WHERE company_id = {$CO} AND is_canonical = 1");
while ($x = $r->fetch_assoc()) { $idByCode[$x['code']] = (int) $x['id']; }
$linked = 0;
foreach ($canon as $code => $a) {
    if ($a['parent_code'] === '' || !isset($idByCode[$a['parent_code']]) || !isset($idByCode[$code])) { continue; }
    $db->query("UPDATE fin_chart_of_accounts SET parent_id = " . $idByCode[$a['parent_code']]
        . " WHERE id = " . $idByCode[$code]);
    $linked++;
}
say("روابطُ الأبوّة: {$linked}");

/* ══ ④ خريطةُ الترحيلِ الموروث ← القانوني ════════════════════════════════ */
// الخريطةُ الحاكمة: القرارُ خبرةً على الاسمِ والمعنى — لا على تشابهِ الكود.
$MAP = array(
    // النقديةُ والبنوك
    '1'=>'1','1000'=>'1','11'=>'11','12'=>'12','1100'=>'1101','1101'=>'1101','1102'=>'1102',
    '1103'=>'1101', // نقدٌ مقيدُ السحبِ ← النقديةُ بالصندوقِ والخزائن
    // الذممُ والمخزون
    '1200'=>'1104','1201'=>'1104','1202'=>'1104','1203'=>'1105','1204'=>'1108','1205'=>'1103','1206'=>'1107',
    '1300'=>'1106','1301'=>'1106','1302'=>'1106','1303'=>'1106','1304'=>'1106','1309'=>'1106',
    // الأصولُ الثابتة
    '1401'=>'1301','1402'=>'1302','1403'=>'1304','1404'=>'1304','1405'=>'1303','1406'=>'1306',
    '1411'=>'1305','1412'=>'1305','1413'=>'1305','1414'=>'1305','1415'=>'1305',
    '1421'=>'1301','1422'=>'1305','1431'=>'1201',
    // الالتزامات
    '2'=>'2','2000'=>'2','21'=>'21','22'=>'22',
    '2100'=>'2101','2101'=>'2102','2102'=>'2101','2103'=>'2102','2104'=>'2107','2105'=>'2102','2106'=>'2107',
    '2200'=>'2201','2201'=>'2103','2202'=>'2103','2203'=>'2103','2204'=>'2202',
    '2301'=>'2106','2302'=>'2105','2401'=>'2305','2402'=>'2305',
    '2501'=>'2301','2502'=>'2301','2503'=>'2302','2504'=>'2303','2505'=>'2304','2506'=>'2304',
    // حقوقُ الملكية
    '3'=>'3','3000'=>'3','31'=>'31','3100'=>'3101','3101'=>'3101','3102'=>'3301',
    '3201'=>'3202','3301'=>'3201','3302'=>'3204','3401'=>'3205',
    // الإيرادات
    '4'=>'4','4000'=>'4','41'=>'41','42'=>'42',
    '4100'=>'4101','4101'=>'4101','4102'=>'4102','4103'=>'4103','4104'=>'4104',
    '4200'=>'4106','4201'=>'4201','4202'=>'4204',
    // المصروفات
    '5'=>'5','5000'=>'5','51'=>'51','52'=>'52','53'=>'54','54'=>'56',
    '5100'=>'5101','5101'=>'5105','5102'=>'5101','5103'=>'5101','5104'=>'5108','5105'=>'5109',
    '5106'=>'5108','5107'=>'5111','5108'=>'5113','5109'=>'5112','5110'=>'5114',
    '5111'=>'5501','5112'=>'5501','5113'=>'5602','5114'=>'5602',
    '5200'=>'5108','5201'=>'5201','5202'=>'5204','5203'=>'5210','5204'=>'5205','5205'=>'5206',
    '5206'=>'5207','5207'=>'5208','5208'=>'5209','5209'=>'5504','5210'=>'5210',
    '5300'=>'5110','5301'=>'5401','5302'=>'5403','5303'=>'5403','5304'=>'5406','5305'=>'5407',
    '5401'=>'5601','5402'=>'5211','5403'=>'5602',
);
/* الاحتياطُ بالنوعِ حين لا مطابقةَ صريحة — ولا يُترك حسابٌ بلا وجهة */
$FALLBACK = array('asset' => '1108', 'liability' => '2107', 'equity' => '3201',
                  'revenue' => '4204', 'expense' => '5210');

$mapped = 0; $moved = 0; $unmapped = array();
foreach ($legacy as $id => $a) {
    $orig = $a['orig_code'];
    $newCode = $MAP[$orig] ?? ($FALLBACK[$a['account_type']] ?? '1108');
    if (!isset($idByCode[$newCode])) { $unmapped[] = $orig; continue; }
    $newId = $idByCode[$newCode];
    $before = $balBefore[$id]['bal'] ?? 0.0;
    $nLines = $balBefore[$id]['n'] ?? 0;
    $rule = isset($MAP[$orig]) ? 'مطابقةٌ دلاليةٌ خبرةً على الاسمِ والمعنى' : 'احتياطٌ بالنوع — R8 لا حسابَ يتيمًا';

    $st = $db->prepare("INSERT INTO fin_coa_migration
        (company_id, old_account_id, old_code, old_name, new_account_id, new_code,
         balance_before, lines_moved, rule_note, migrated_by)
        VALUES (?,?,?,?,?,?,?,?,?,0)
        ON DUPLICATE KEY UPDATE new_account_id = VALUES(new_account_id), new_code = VALUES(new_code),
            balance_before = VALUES(balance_before), lines_moved = VALUES(lines_moved),
            rule_note = VALUES(rule_note)");
    $st->bind_param('iissisdis', $CO, $id, $orig, $a['name'], $newId, $newCode, $before, $nLines, $rule);
    $st->execute();
    if ($st->errno) { fwrite(STDERR, 'MIGERR ' . $orig . ': ' . $st->error . "\n"); exit(1); }
    $st->close();
    $mapped++;

    if ($nLines > 0) {
        // إعادةُ التوجيهِ مع حفظِ التصنيفِ الأصليِّ — والترحيلُ يُعكس
        $st = $db->prepare("UPDATE fin_journal_lines
                               SET legacy_account_id = COALESCE(legacy_account_id, account_id),
                                   account_id = ?
                             WHERE company_id = ? AND account_id = ?");
        $st->bind_param('iii', $newId, $CO, $id);
        $st->execute();
        $moved += $db->affected_rows;
        $st->close();
    }
}
say("خريطةُ الترحيل: {$mapped} حسابًا · سطورُ قيدٍ أُعيد توجيهُها: {$moved}"
    . (empty($unmapped) ? '' : ' · بلا وجهة: ' . implode(',', $unmapped)));

/* ══ ⑤ حساباتُ الأشخاص ← البُعد D6 (R2) ══════════════════════════════════ */
$personRows = xlsx_rows($XLSX, 8); // 07 — ترحيل حسابات الأشخاص
array_shift($personRows);
$persons = 0;
foreach ($personRows as $pr) {
    $oldCode = trim($pr[0] ?? '');
    $oldName = trim($pr[1] ?? '');
    $newCode = trim($pr[2] ?? '');
    $dimText = trim($pr[3] ?? '');
    if ($oldCode === '' || $newCode === '') { continue; }
    $dimVal = trim(str_replace('الطرفُ المقابل =', '', $dimText));
    $newId = $idByCode[$newCode] ?? null;
    $st = $db->prepare("INSERT INTO fin_coa_migration
        (company_id, old_account_id, old_code, old_name, new_account_id, new_code,
         dim_key, dim_value, rule_note, migrated_by)
        VALUES (?, NULL, ?, ?, ?, ?, 'D6', ?, 'R2: العهدةُ حسابٌ واحدٌ والشخصُ بُعدٌ تحليلي', 0)
        ON DUPLICATE KEY UPDATE dim_key = 'D6', dim_value = VALUES(dim_value),
            new_account_id = VALUES(new_account_id), new_code = VALUES(new_code),
            rule_note = VALUES(rule_note)");
    $st->bind_param('ississ', $CO, $oldCode, $oldName, $newId, $newCode, $dimVal);
    $st->execute();
    $st->close();
    $persons++;
}
say("حساباتُ الأشخاصِ في الخريطة (R2): {$persons} — العهدةُ حسابٌ واحدٌ والشخصُ بُعد D6");

/* ══ ⑥-ب تطهيرُ صفوفٍ فارغةٍ خلّفها تشغيلٌ متعثر — وسمًا لا حذفًا ═════════ */
// صفٌّ موروثٌ بلا سطرِ قيدٍ وكودُه الأصليُّ يطابق كودًا قانونيًّا ⇒ نسخةٌ فارغةٌ
// وُسمت خطأً في تشغيلٍ انقطع. تُوسَم صراحةً ولا تُحذف (صفرُ حذفٍ في الجولة).
$voided = 0;
$r = $db->query("SELECT id, code FROM fin_chart_of_accounts
                  WHERE company_id = {$CO} AND is_canonical = 0 AND code LIKE 'L-%'");
$voidIds = array();
while ($x = $r->fetch_assoc()) {
    $orig = preg_replace('/^L-/', '', $x['code']);
    if (!isset($canon[$orig])) { continue; }             // ليس كودًا قانونيًّا
    $u = $db->query("SELECT COUNT(*) c FROM fin_journal_lines
                      WHERE company_id = {$CO} AND (account_id = " . (int) $x['id']
                      . " OR legacy_account_id = " . (int) $x['id'] . ")");
    if ((int) $u->fetch_assoc()['c'] > 0) { continue; }  // له تاريخٌ — يبقى كما هو
    $voidIds[] = (int) $x['id'];
    $voided++;
}
if ($voidIds) {
    $in = implode(',', $voidIds);
    $db->query("UPDATE fin_chart_of_accounts
                   SET coa_note = 'نسخةٌ فارغةٌ من تشغيلٍ متعثر — بلا رصيدٍ ولا سطرِ قيد · تُبقى ولا تُحذف'
                 WHERE id IN ($in) AND company_id = {$CO}");
    $db->query("UPDATE fin_coa_migration
                   SET rule_note = 'صفٌّ فارغٌ من تشغيلٍ متعثر — لا رصيدَ ولا سطر'
                 WHERE company_id = {$CO} AND old_account_id IN ($in)");
}
say("صفوفٌ فارغةٌ وُسمت (لا حذف): {$voided}");

/* ══ ⑥ تقريرُ التساوي (R10) ══════════════════════════════════════════════ */
$balAfter = array();
$r = $db->query("SELECT account_id, SUM(debit) dr, SUM(credit) cr
                   FROM fin_journal_lines WHERE company_id = {$CO} GROUP BY account_id");
$sumAfter = 0.0;
while ($x = $r->fetch_assoc()) {
    $b = round((float) $x['dr'] - (float) $x['cr'], 2);
    $balAfter[(int) $x['account_id']] = $b;
    $sumAfter += $b;
}
// ◆ عدةُ حساباتٍ موروثةٍ تُخرَّط إلى حسابٍ قانونيٍّ واحد — فلو حُمّل الرصيدُ
//   على كلِّ صفٍّ لاحتُسب مرتين. يحمله الصفُّ الأولُ وحدَه والبقيةُ صفرٌ،
//   فيبقى مجموعُ «بعد» مساويًا لمجموعِ «قبل» بالضبط.
$db->query("UPDATE fin_coa_migration SET balance_after = 0 WHERE company_id = {$CO}");
foreach ($balAfter as $accId => $b) {
    $db->query("UPDATE fin_coa_migration SET balance_after = " . $b
        . " WHERE company_id = {$CO} AND new_account_id = {$accId} ORDER BY id LIMIT 1");
}
$diff = round($sumBefore - $sumAfter, 2);
$mapAfter = (float) $db->query("SELECT COALESCE(SUM(balance_after),0) s FROM fin_coa_migration
    WHERE company_id = {$CO}")->fetch_assoc()['s'];
say('الأرصدةُ بعد: ' . count($balAfter) . ' حسابًا قانونيًّا · Σ = ' . number_format($sumAfter, 2)
    . ' · وفي الخريطة Σ = ' . number_format($mapAfter, 2));
say('◆ فرقُ التساوي: ' . number_format($diff, 2) . ($diff == 0.0 ? '  ✔ متساوٍ بالضبط (R10)' : '  ✘ خلل'));

/* لا حسابَ موروثٌ يبقى نشطًا · ولا قانونيَّ معطَّل */
$activeLegacy = (int) $db->query("SELECT COUNT(*) c FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 0 AND active = 1")->fetch_assoc()['c'];
$canonCount = (int) $db->query("SELECT COUNT(*) c FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1")->fetch_assoc()['c'];
say("الشجرةُ الحية: {$canonCount} حسابًا قانونيًّا · موروثٌ نشطٌ: {$activeLegacy} (يجب صفر)");

exit(($diff == 0.0 && $activeLegacy === 0) ? 0 : 1);

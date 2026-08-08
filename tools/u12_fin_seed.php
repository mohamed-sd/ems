<?php
/**
 * tools/u12_fin_seed.php — بذرُ مراجعِ المالية من الوثائقِ نفسِها
 * ═══════════════════════════════════════════════════════════════════════════
 * يقرأ من المصدرِ الحاكمِ لا من نسخةٍ يدوية:
 *   ① أنواعُ عقودِ الموظفينَ الثمانيةُ (EC) والممولينَ العشرةُ (FC) — COA §03/§04
 *   ② مصفوفةُ الترحيلِ لكل إدارة (27 صفًّا) — MAP-7 الورقةُ 37
 *   ③ حدودُ النسبِ الأربعِ والأربعينَ (FR-01..44) — MAP-7 الورقةُ 35
 *   ④ قواعدُ إشاراتِ الإنذارِ الستَّ عشرةَ (FS-01..16) — MAP-7 الورقةُ 36
 * idempotent بالكامل — يُعاد تشغيلُه بلا أثرٍ مزدوج.
 *
 * التشغيل: php tools/u12_fin_seed.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$COA = $ROOT . '/docs/update0012/EQUIPATION-COA-01 — دليل الحسابات المعاد هيكلته.xlsx';
$MAP = $ROOT . '/docs/update0012/INJAZ-MASTER-MAP-7.xlsx';
$CO = 4;

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
function say($s) { echo $s . "\n"; }

/* قارئُ XLSX (نسخةُ الأداةِ نفسِها في u12_coa_restructure) */
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
/** الأرقامُ الشرقيةُ في نصوصِ الوثيقةِ تُحوَّل غربيةً للتخزينِ والحساب */
function wnum($s)
{
    return strtr((string) $s, array('٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
        '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٪'=>'%'));
}
/** استخراجُ أكوادِ الحساباتِ من نصِّ صيغةٍ (٤١ − ٥١) ⇒ 41,51 */
function codes_of($s)
{
    $t = wnum($s);
    preg_match_all('/\b\d{1,4}\b/', $t, $mm);
    $out = array();
    foreach ($mm[0] as $c) { if (strlen($c) >= 1 && (int) $c > 0) { $out[] = $c; } }
    return implode(',', array_values(array_unique($out)));
}

/* ══ ① أنواعُ العقود EC + FC ═════════════════════════════════════════════ */
$types = 0;
foreach (array(array(4, 'employee'), array(5, 'financier')) as $pair) {
    list($sheet, $family) = $pair;
    $rows = xlsx_rows($COA, $sheet);
    array_shift($rows);
    foreach ($rows as $r) {
        $code = trim($r[0] ?? '');
        if ($code === '' || !preg_match('/^(EC|FC)-\d+$/', $code)) { continue; }
        $accs = wnum(str_replace(array(' · ', '·'), ',', trim($r[3] ?? '')));
        $accs = preg_replace('/[^0-9,\.]/', '', $accs);
        $rule = trim($r[5] ?? '');
        $cap = (mb_strpos($rule, 'يُرسمَل') !== false || mb_strpos($rule, 'الأصلُ يُرسمَل') !== false
                || mb_strpos($rule, 'ترسمل') !== false) ? 1 : 0;
        $st = $db->prepare("INSERT INTO fin_contract_types
            (company_id, type_code, family, name_ar, name_en, accounts_csv, cost_nature, accounting_rule, capitalizes)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), name_en=VALUES(name_en),
                accounts_csv=VALUES(accounts_csv), cost_nature=VALUES(cost_nature),
                accounting_rule=VALUES(accounting_rule), capitalizes=VALUES(capitalizes)");
        $nameAr = trim($r[1] ?? ''); $nameEn = trim($r[2] ?? ''); $nature = trim($r[4] ?? '');
        $st->bind_param('isssssssi', $CO, $code, $family, $nameAr, $nameEn, $accs, $nature, $rule, $cap);
        $st->execute();
        if ($st->errno) { fwrite(STDERR, 'CT ' . $code . ': ' . $st->error . "\n"); }
        $st->close();
        $types++;
    }
}
say("أنواعُ العقود (D9): {$types} — ثمانيةُ موظفينَ وعشرةُ ممولين");

/* ══ ② مصفوفةُ الترحيلِ لكل إدارة ═══════════════════════════════════════ */
$rows = xlsx_rows($MAP, 38); // 37 — مصفوفة الترحيل
array_shift($rows);
$pm = 0;
foreach ($rows as $r) {
    $code = trim($r[0] ?? '');
    if ($code === '' || $code === '◆') { continue; }
    $dept = trim($r[1] ?? ''); $ev = trim($r[2] ?? '');
    // ◆ الوثيقةُ تكتب المدى «٥١٠١..٥١٠٧» — يُفكُّ أكوادًا صريحةً فلا يبقى
    //   صفٌّ بلا حسابٍ قابلٍ للاشتقاق (وإلا رفضت المصفوفةُ الاشتقاق).
    $expand = function ($s) {
        $s = wnum($s);
        $s = preg_replace_callback('/(\d{3,4})\s*\.\.\s*(\d{3,4})/', function ($m) {
            $a = (int) $m[1]; $b = (int) $m[2];
            if ($b < $a || $b - $a > 40) { return $m[1] . ',' . $m[2]; }
            $out = array();
            for ($i = $a; $i <= $b; $i++) { $out[] = (string) $i; }
            return implode(',', $out);
        }, $s);
        return $s;
    };
    $rev = $expand(trim($r[3] ?? '')); $cost = $expand(trim($r[4] ?? ''));
    $dims = str_replace(array(' · ', '·', ' '), array(',', ',', ''), trim($r[5] ?? ''));
    $gate = trim($r[6] ?? ''); $rule = trim($r[7] ?? '');
    $st = $db->prepare("INSERT INTO fin_posting_matrix
        (company_id, rule_code, dept_ar, source_event, revenue_accounts, cost_accounts,
         required_dims, gate_ar, governing_rule, version_no)
        VALUES (?,?,?,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE dept_ar=VALUES(dept_ar), source_event=VALUES(source_event),
            revenue_accounts=VALUES(revenue_accounts), cost_accounts=VALUES(cost_accounts),
            required_dims=VALUES(required_dims), gate_ar=VALUES(gate_ar),
            governing_rule=VALUES(governing_rule)");
    $st->bind_param('issssssss', $CO, $code, $dept, $ev, $rev, $cost, $dims, $gate, $rule);
    $st->execute();
    if ($st->errno) { fwrite(STDERR, 'PM ' . $code . ': ' . $st->error . "\n"); }
    $st->close();
    $pm++;
}
say("مصفوفةُ الترحيل: {$pm} صفًّا — الحسابُ يُشتق ولا يُختار يدويًّا");

/* ══ ③ حدودُ النسبِ الأربعِ والأربعين ═══════════════════════════════════ */
$rows = xlsx_rows($MAP, 36); // 35 — التحليل المالي والنسب
array_shift($rows);
$fr = 0; $group = '';
foreach ($rows as $r) {
    $code = trim($r[0] ?? '');
    if (preg_match('/^RG-\d+$/', $code)) { $group = $code; continue; }
    if (!preg_match('/^FR-\d+$/', $code)) { continue; }
    $nameAr = trim($r[1] ?? ''); $nameEn = trim($r[2] ?? '');
    $formula = trim($r[3] ?? ''); $codesTxt = trim($r[4] ?? '');
    $unit = trim($r[5] ?? ''); $limit = trim($r[6] ?? '');
    $cad = trim($r[7] ?? 'شهريًّا'); $grp = trim($r[8] ?? $group);

    // البسطُ والمقامُ من نصِّ أكوادِ الشجرة (٤١ ÷ ٢١ ⇒ 41 | 21)
    $t = wnum($codesTxt);
    $num = $t; $den = '';
    foreach (array('÷', '/') as $sep) {
        if (mb_strpos($t, $sep) !== false) {
            $parts = explode($sep, $t, 2);
            $num = $parts[0]; $den = $parts[1];
            break;
        }
    }
    $numCodes = codes_of($num);
    $denCodes = codes_of($den);

    // حدُّ الإنذارِ والحرجِ من نصِّ الحدِّ حين يكون رقميًّا صريحًا
    $lim = wnum($limit);
    $op = 'none'; $warn = null; $crit = null; $target = null;
    if (preg_match('/≥\s*([\d\.]+)/u', $lim, $mm)) { $op = 'lt'; $warn = (float) $mm[1]; }
    elseif (preg_match('/>\s*([\d\.]+)/u', $lim, $mm)) { $op = 'gt'; $warn = (float) $mm[1]; }
    if (preg_match('/<\s*([\d\.]+)\s*إنذار/u', $lim, $mm)) { $crit = (float) $mm[1]; }
    if (preg_match('/([\d\.]+)٪?\s*مستهدَف/u', $lim, $mm)) { $target = (float) $mm[1]; }

    $owner = (mb_strpos($grp, 'RG-1') !== false || mb_strpos($cad, 'يومي') !== false)
        ? 'النائبُ المالي' : 'المديرُ المالي';
    $st = $db->prepare("INSERT INTO fin_ratio_targets
        (company_id, ratio_code, group_code, name_ar, name_en, formula_ar,
         numerator_codes, denominator_codes, unit_ar, warn_op, warn_value, critical_value,
         target_value, limit_text, cadence, owner_role, version_no)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE group_code=VALUES(group_code), name_ar=VALUES(name_ar),
            name_en=VALUES(name_en), formula_ar=VALUES(formula_ar),
            numerator_codes=VALUES(numerator_codes), denominator_codes=VALUES(denominator_codes),
            unit_ar=VALUES(unit_ar), warn_op=VALUES(warn_op), warn_value=VALUES(warn_value),
            critical_value=VALUES(critical_value), target_value=VALUES(target_value),
            limit_text=VALUES(limit_text), cadence=VALUES(cadence), owner_role=VALUES(owner_role)");
    $st->bind_param('isssssssssdddsss', $CO, $code, $grp, $nameAr, $nameEn, $formula,
        $numCodes, $denCodes, $unit, $op, $warn, $crit, $target, $limit, $cad, $owner);
    $st->execute();
    if ($st->errno) { fwrite(STDERR, 'FR ' . $code . ': ' . $st->error . "\n"); }
    $st->close();
    $fr++;
}
say("حدودُ النسب: {$fr} نسبةً — ولا نسبةَ تُعرض بلا حدٍّ ومالكٍ ودورية");

/* ══ ④ قواعدُ إشاراتِ الإنذار ═══════════════════════════════════════════ */
$rows = xlsx_rows($MAP, 37); // 36 — إشارات الإنذار المالي
array_shift($rows);
$fs = 0;
foreach ($rows as $r) {
    $code = trim($r[0] ?? '');
    if (!preg_match('/^FS-\d+$/', $code)) { continue; }
    $name = trim($r[1] ?? ''); $expr = wnum(trim($r[2] ?? ''));
    $sev = trim($r[3] ?? 'متوسط'); $dest = trim($r[4] ?? ''); $cad = trim($r[5] ?? 'شهريًّا');

    $ratio = ''; $op = 'none'; $thr = null; $streak = 0;
    if (preg_match('/(FR-\d+)/', $expr, $mm)) { $ratio = $mm[1]; }
    if (preg_match('/↓\s*×\s*(\d+)/u', $expr, $mm)) { $op = 'decline_streak'; $streak = (int) $mm[1]; }
    elseif (preg_match('/Δ\s*FR-\d+\s*>\s*([\d\.]+)/u', $expr, $mm)) { $op = 'delta_gt'; $thr = (float) $mm[1]; }
    elseif (preg_match('/<\s*([\d\.]+)/', $expr, $mm)) { $op = 'lt'; $thr = (float) $mm[1]; }
    elseif (preg_match('/>\s*(?:الهدف\+)?([\d\.]+)/u', $expr, $mm)) { $op = 'gt'; $thr = (float) $mm[1]; }
    if (!in_array($sev, array('حرج', 'مرتفع', 'متوسط', 'منخفض'), true)) { $sev = 'متوسط'; }

    $st = $db->prepare("INSERT INTO fin_signal_rules
        (company_id, signal_code, name_ar, rule_expr, ratio_code, operator, threshold,
         streak_periods, severity, destination_ar, cadence)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), rule_expr=VALUES(rule_expr),
            ratio_code=VALUES(ratio_code), operator=VALUES(operator), threshold=VALUES(threshold),
            streak_periods=VALUES(streak_periods), severity=VALUES(severity),
            destination_ar=VALUES(destination_ar), cadence=VALUES(cadence)");
    $st->bind_param('isssssdisss', $CO, $code, $name, $expr, $ratio, $op, $thr, $streak, $sev, $dest, $cad);
    $st->execute();
    if ($st->errno) { fwrite(STDERR, 'FS ' . $code . ': ' . $st->error . "\n"); }
    $st->close();
    $fs++;
}
say("قواعدُ الإشارات: {$fs} إشارةً — كلُّها تُنشر لإدارةِ المخاطرِ فتدخل الفرزَ الرباعي");

say('اكتمل بذرُ مراجع المالية.');

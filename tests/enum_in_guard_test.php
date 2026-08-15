<?php
/**
 * tests/enum_in_guard_test.php — لا يُسأل عن قيمةٍ لا موضعَ لها في تعدادِ عمودها
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0334: «طلبُ عروضٍ حالتُه `closed` يظهر في قائمة الترسية،
 *   ولا تظهر طلباتٌ بحالاتٍ غيرِ مسموحة».
 *
 * ── والشاهدُ ثلاثُ طبقاتٍ لا واحدة ────────────────────────────────────────
 * ① **المقياسُ على الشاشةِ نفسِها**: شرطُ `IN` يُقتطع من مصدرِ
 *    `Procurement/rfq_compare_award.php` حيًّا — فلو غُيّر غدًا تبع الشاهدُ
 *    التغييرَ ولم يصادق على نسخةٍ في ذاكرتِه.
 * ② **الحكمُ على صفوفٍ حقيقية**: يُبذر طلبُ عروضٍ في كلِّ حالةٍ من التعدادِ
 *    الستِّ، ثم يُنفَّذ شرطُ الشاشةِ فيُثبَت أنَّ `closed` **يظهر** وأنَّ
 *    `draft` و`awarded` و`contracted` و`cancelled` **لا تظهر**.
 * ③ **الحارسُ العامُّ**: `tools/fix_enum_in_scan.php` يمسح المستودعَ كلَّه.
 *
 * ◆ **والاختبارُ السلبيُّ شرطُ صحةِ الحارس (GT-01)**: يُزرع ملفٌّ مؤقتٌ يسأل عن
 *   قيمةٍ ميتةٍ فيجب أن **يرسبَ** الفاحصُ ويسمّيَه — وفاحصٌ لا يرسب عند إفسادِ
 *   مفحوصِه يصادق على نفسِه ولا قيمةَ له.
 * ◆ والوسمُ عائليٌّ ثابتٌ لا `getmypid()` · والكنسُ الأبناءُ قبل الآباء ·
 *   ويُفحص مُرجَعُ كلِّ حذفٍ لأنَّ FK يردُّ صامتًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'ENUMIN-TEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/** تعدادُ عمودٍ **مقروءًا حيًّا** من `SHOW COLUMNS` لا من مخطَّطٍ محفوظ. */
$enumOf = function ($table, $col) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($col) . "'");
    if (!$r || !($x = $r->fetch_assoc())) { return array(); }
    if (!preg_match('~^(?:enum|set)\((.*)\)$~is', (string) $x['Type'], $m)) { return array(); }
    return explode("','", trim($m[1], "'"));
};

$say('══ لا يُسأل عن قيمةٍ لا موضعَ لها في تعدادِ عمودها (INJ-0334)');

/* ── ① الحالاتُ المكتوبةُ في كلِّ موضعٍ أُصلح ⊆ التعدادِ الحيّ ───────────── */
$say("\n── ① كلُّ قيمةٍ في المواضعِ المُصلَحةِ لها موضعٌ في تعدادِها");
$SITES = array(
    array('Procurement/rfq_compare_award.php',              'supplier_rfqs',          'state'),
    array('app/Services/Finance/ApprovalsInboxService.php', 'fin_requests',           'state'),
    array('app/Services/Portal/WorkspaceFeedService.php',   'fin_requests',           'state'),
    array('app/Services/Portal/AchievementService.php',     'fin_requests',           'state'),
    array('Financing/installments.php',                     'financing_installments', 'state'),
    array('app/Services/Finance/FinanceM10Service.php',     'fin_event_links',        'effect_type'),
);
foreach ($SITES as $s) {
    list($rel, $tbl, $col) = $s;
    $src  = (string) @file_get_contents($ROOT . '/' . $rel);
    $vals = $enumOf($tbl, $col);
    $bad  = array(); $seen = 0;
    /* ◆ الوحدةُ المقيسةُ **نصُّ الاستعلامِ الذي يذكر هذا الجدولَ** لا الملفُّ كلُّه:
         فـ`ApprovalsInboxService` يحمل صندوقًا عن `fin_requests` وآخرَ عن
         `settlements` — ولكلٍّ تعدادُه. وقياسٌ على الملفِّ ينسب `payment_requested`
         (وهي حيّةٌ في تسويات) إلى تعدادِ الطلباتِ فيُدين بريئًا. */
    if ($src !== '' && $vals
        && preg_match_all('~"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'~s', $src, $qm, PREG_SET_ORDER)) {
        foreach ($qm as $qs) {
            $sql = ($qs[1] !== '' ? $qs[1] : (isset($qs[2]) ? $qs[2] : ''));
            if ($sql === '' || !preg_match('~\b(?:FROM|JOIN|UPDATE)\s+`?' . preg_quote($tbl, '~') . '\b~i', $sql)) { continue; }
            if (!preg_match_all('~\b' . preg_quote($col, '~') . '\s+IN\s*\(\s*((?:\'[^\']*\'\s*,\s*)*\'[^\']*\')\s*\)~i',
                    $sql, $mm, PREG_SET_ORDER)) { continue; }
            foreach ($mm as $m) {
                preg_match_all("~'([^']*)'~", $m[1], $vm);
                foreach ($vm[1] as $v) { $seen++; if ($v !== '' && !in_array($v, $vals, true)) { $bad[] = $v; } }
            }
        }
    }
    $ok($vals !== array() && $seen > 0 && !$bad,
        $rel . ' · ' . $tbl . '.' . $col . ' — ' . $seen . ' قيمةً كلُّها حيّة',
        $bad ? ('ميتٌ: ' . implode(',', $bad)) : ($seen === 0 ? 'لم يُقرأ شرطٌ' : 'لا تعداد'));
}
/* والوقائيةُ تنادي التعريفَ المركزيَّ فلا قائمةَ مكتوبةً فيها أصلًا */
require_once $ROOT . '/includes/unit_chain_helpers.php';
$ueEnum = $enumOf('unit_entries', 'state');
$ueAcc  = ems_uc_accepted_states();
$ok($ueAcc && !array_diff($ueAcc, $ueEnum),
    'includes/unit_chain_helpers.php · حالاتُ القبولِ الأربعُ كلُّها في تعدادِ unit_entries.state',
    implode(',', array_diff($ueAcc, $ueEnum)));
$prev = (string) @file_get_contents($ROOT . '/Maintenance/equipment_hours_preventive.php');
$ok(strpos($prev, "'approved','converted'") === false && strpos($prev, 'ems_uc_accepted_sql') !== false,
    'Maintenance/equipment_hours_preventive.php · صارت تنادي التعريفَ المركزيَّ لا قائمةً مكتوبة');
$dept = (string) @file_get_contents($ROOT . '/Portal/dept_achievement.php');
$ok(strpos($dept, 'ems_uc_accepted_sql') !== false,
    'Portal/dept_achievement.php · التعريفُ نفسُه — فلا يفترق رقمانِ لمعنًى واحد');

/* ── ② الحكمُ على صفوفٍ حقيقيةٍ بشرطِ الشاشةِ نفسِه ────────────────────── */
$say("\n── ② شرطُ شاشةِ الترسيةِ مقتطَعًا من مصدرِها ومُنفَّذًا على صفوفٍ مبذورة");
$awardSrc = (string) @file_get_contents($ROOT . '/Procurement/rfq_compare_award.php');
$pred = '';
if (preg_match('~FROM\s+supplier_rfqs.*?WHERE\s+company_id=\$company_id\s+AND\s+is_deleted=0\s+AND\s+(state\s+IN\s*\([^)]*\))~is',
        $awardSrc, $pm)) { $pred = preg_replace('~\s+~', ' ', $pm[1]); }
$ok($pred !== '', 'اقتُطع شرطُ الحالةِ من مصدرِ الشاشةِ حيًّا: ' . $pred);

$sweep = function () use ($conn, $TAG, &$ok) {
    /* الأبناءُ قبل الآباء — ويُفحص المُرجَع */
    $c1 = $conn->query("DELETE FROM rfq_quotes WHERE rfq_id IN (SELECT id FROM supplier_rfqs WHERE rfq_no LIKE '%{$TAG}%')");
    $c2 = $conn->query("DELETE FROM supplier_rfqs WHERE rfq_no LIKE '%{$TAG}%'");
    if ($c1 === false || $c2 === false) { return -1; }
    $r = $conn->query("SELECT COUNT(*) FROM supplier_rfqs WHERE rfq_no LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

/* عقدُ عميلٍ قائمٌ — العمودُ NOT NULL فلا بذرَ بلا أب */
$cc = 0;
$r = $conn->query("SELECT id FROM contracts WHERE company_id={$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $cc = (int) $x[0]; }
$ok($cc > 0, 'عقدُ عميلٍ قائمٌ يُعلَّق عليه البذرُ #' . $cc);

$states = $enumOf('supplier_rfqs', 'state');
$ok(count($states) === 6, 'تعدادُ supplier_rfqs.state ستُّ حالاتٍ حيّة: ' . implode('·', $states));
/* ◆ والقاعدةُ نفسُها تحرس المعنى: `ck_rfq_awarded` يردُّ مُرسًى بلا مُرسٍ،
     و`ck_rfq_cancel` يردُّ ملغيًّا بلا سبب — فالبذرُ يستوفي شرطَ كلِّ حالة. */
$seeded = array(); $seedErr = array();
foreach ($states as $st) {
    $no  = $TAG . '-' . $st;
    $ttl = 'شاهدُ التعداد ' . $TAG;
    $by  = in_array($st, array('awarded', 'contracted'), true) ? '1' : 'NULL';
    $rsn = ($st === 'cancelled') ? "'شاهدٌ — يُكنس'" : 'NULL';
    $okI = $conn->query("INSERT INTO supplier_rfqs
              (company_id, rfq_no, client_contract_id, title, due_date, state, awarded_by, cancel_reason, created_at)
              VALUES ({$CO}, '{$no}', {$cc}, '{$ttl}', CURDATE(), '{$st}', {$by}, {$rsn}, NOW())");
    if ($okI && $conn->affected_rows > 0) { $seeded[$st] = (int) $conn->insert_id; }
    else { $seedErr[] = $st . ': ' . $conn->error; }
}
$ok(count($seeded) === count($states),
    'بُذرت ' . count($seeded) . ' حالةً — واحدةٌ لكلِّ قيمةٍ في التعداد',
    implode(' | ', $seedErr));

$visible = array();
if ($pred !== '') {
    $r = $conn->query("SELECT state FROM supplier_rfqs
                        WHERE company_id={$CO} AND is_deleted=0 AND {$pred}
                          AND rfq_no LIKE '%{$TAG}%'");
    if ($r === false) { $ok(false, 'استعلامُ الشاشةِ نُفِّذ', $conn->error); }
    while ($r && ($x = $r->fetch_assoc())) { $visible[] = $x['state']; }
}
$ok(in_array('closed', $visible, true),
    '«طلبُ عروضٍ حالتُه closed **يظهر** في قائمة الترسية» — نصُّ القبولِ حرفًا');
$ok(in_array('sent', $visible, true), 'والمُرسَلُ يظهر كذلك (مهلتُه مفتوحة)');
$forbidden = array_intersect($visible, array('draft', 'awarded', 'contracted', 'cancelled'));
$ok(!$forbidden,
    '«ولا تظهر طلباتٌ بحالاتٍ غيرِ مسموحة» — صفرُ حالةٍ ممنوعة',
    implode(',', $forbidden));
$ok(count($visible) === 2, 'الظاهرُ حصرًا حالتانِ من ستٍّ: ' . implode('·', $visible));

/* ولو عاد النمطُ (قيمةٌ ميتةٌ في الشرط) لَما ظهر شيءٌ — وهذا جوهرُ العيب */
$rDead = $conn->query("SELECT COUNT(*) FROM supplier_rfqs WHERE company_id={$CO} AND is_deleted=0
                        AND state IN ('sent','opened','quoted') AND rfq_no LIKE '%{$TAG}%'");
$deadN = ($rDead && ($x = $rDead->fetch_row())) ? (int) $x[0] : -1;
$ok($deadN === 1,
    'وبالشرطِ القديمِ يظهر واحدٌ فقط من ستٍّ — فالقيمتانِ الميتتانِ **كانتا تحجبان** المُقفَل',
    'العدد=' . $deadN);

/* ── ③ الحارسُ العامُّ على المستودعِ كلِّه ─────────────────────────────── */
$say("\n── ③ الحارسُ العامُّ: مسحُ المستودعِ كلِّه");
$out = array(); $rc = 1;
@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/fix_enum_in_scan.php') . ' 2>&1', $out, $rc);
$txt = implode("\n", $out);
$ok($rc === 0, 'الفاحصُ يمرُّ على الشجرةِ النظيفة (خروج=' . $rc . ')',
    mb_substr(preg_replace('~\s+~', ' ', $txt), 0, 220));
$ok(preg_match('~شروطُ IN مقيسةٌ: (\d+)~u', $txt, $cm) && (int) $cm[1] >= 60,
    'ويقيس ما يكفي ليكونَ حارسًا: ' . (isset($cm[1]) ? $cm[1] : 0) . ' شرطًا');

/* ── ④ الاختبارُ السلبيُّ — أفسِد المفحوصَ وأثبِت الرسوب (GT-01) ─────────── */
$say("\n── ④ الاختبارُ السلبيُّ: فاحصٌ لا يرسب عند الإفساد يصادق على نفسِه");
$bait = $ROOT . '/Procurement/_enumin_bait_' . $TAG . '.php';
$baitRel = basename($bait);
file_put_contents($bait, "<?php\n/* طُعمُ الاختبارِ السلبيِّ — يُحذف في الكنسِ البعديّ */\n"
    . "\$q = \"SELECT id FROM supplier_rfqs WHERE state IN ('sent','opened','quoted')\";\n");
$out2 = array(); $rc2 = 0;
@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/fix_enum_in_scan.php') . ' 2>&1', $out2, $rc2);
$txt2 = implode("\n", $out2);
$ok($rc2 === 1, 'رسب الفاحصُ عند زرعِ قيمةٍ ميتةٍ (خروج=' . $rc2 . ')');
$ok(strpos($txt2, $baitRel) !== false, 'وسمّى الملفَّ المُفسَدَ بعينِه: ' . $baitRel);
$ok(strpos($txt2, 'opened') !== false && strpos($txt2, 'quoted') !== false,
    'وسمّى القيمتينِ الميتتينِ لا العددَ وحدَه');
@unlink($bait);
$ok(!file_exists($bait), 'رُفع الطُّعمُ من الشجرة');
$out3 = array(); $rc3 = 1;
@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/fix_enum_in_scan.php') . ' 2>&1', $out3, $rc3);
$ok($rc3 === 0, 'وعادت الشجرةُ خضراءَ بعد رفعِه — فالرسوبُ كان بالطُّعمِ لا بعطبٍ فينا');

/* ── الكنسُ البعديُّ ──────────────────────────────────────────────────── */
$say("\n── الكنسُ البعديّ");
$left = $sweep();
$ok($left === 0, 'كُنست عائلةُ الوسمِ كاملةً — صفرُ صفٍّ متروك', 'المتبقّي=' . $left);

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");   /* الصيغةُ التي يقرأها `tests/_regression.php` */
exit($FAIL > 0 ? 1 : 0);

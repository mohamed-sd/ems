<?php
/**
 * tools/repair01_w16_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ السادسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب منه أن يسقط، ثمَّ يُرجَع.
 *   **والحاجبُ الذي لا يسقط عند كسرِه أعمى والمرحلةُ غيرُ مُغلقة.**
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة**: الالتقاطُ برمزِ الحاجبِ `W16-nn`.
 *
 * ◆ **وأربعةُ أحزمة**: ① كسرٌ في الدفاتر · ② قيودُ المخطَّطِ تردُّ الكتابة ·
 *   ③ حالاتُ الملفِّ بكسرٍ وإرجاعٍ متحقَّقٍ بتجزئة · ④ **قدرةُ محرّكِ التحدّي
 *   على إصدارِ `REDESIGN`** — وهي شرطُ البندِ ٥٠ ولا تُثبَت إلّا بزرعِ خرقٍ حقيقيّ.
 *
 * التشغيل: php tools/repair01_w16_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w16_gate.php';
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W16-') !== false && preg_match('/W16-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode('،', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

$blind = 0; $done = 0;

/* ═══════════════════════════════════════════════════════════════════════════
   ① الحزامُ الأوّل — كسرٌ في الدفاتر
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① كسرٌ في الدفاتر ─────────────────────────────────────────────\n";

$pick = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    $x = $r ? $r->fetch_row() : null;
    return $x ? (string) $x[0] : '';
};
$anyLayer = $pick("SELECT layer_key FROM repair01_w16_layers ORDER BY layer_no LIMIT 1");
$anyAxis  = $pick("SELECT axis_key  FROM repair01_w16_axes   ORDER BY axis_no  LIMIT 1");
$anyDom   = $pick("SELECT domain_code FROM repair01_w16_scorecard ORDER BY domain_code LIMIT 1");
$anyTab   = $pick("SELECT screen_file FROM repair01_w16_tabs ORDER BY screen_file LIMIT 1");
$anyTabD  = $pick("SELECT dept_code FROM repair01_w16_tabs WHERE screen_file = '" . $esc($anyTab) . "' LIMIT 1");
$anySta   = $pick("SELECT station_id FROM repair01_w16_uat WHERE is_negative = 0 ORDER BY station_id LIMIT 1");
$anyDec   = $pick("SELECT decision_id FROM repair01_w16_decisions WHERE decision_id <> 'W16-D-01' ORDER BY decision_id LIMIT 1");
$anyCh    = $pick("SELECT finding_id FROM repair01_w16_challenge ORDER BY finding_id LIMIT 1");
$ownerTab = $pick("SELECT screen_file FROM repair01_w16_tabs WHERE disposition = 'GRANT_GAP_TO_OWNER' LIMIT 1");
$ownerTabD = $pick("SELECT dept_code FROM repair01_w16_tabs WHERE screen_file = '" . $esc($ownerTab) . "' LIMIT 1");
$anyDcSql = $pick("SELECT class_code FROM repair01_debt_register WHERE TRIM(measure_sql) <> '' LIMIT 1");
$dcSqlWas = $pick("SELECT measure_sql FROM repair01_debt_register WHERE class_code = '" . $esc($anyDcSql) . "'");
$dcToolCls = $pick("SELECT class_code FROM repair01_debt_register WHERE TRIM(measure_tool) <> '' LIMIT 1");
$dcToolWas = $pick("SELECT measure_tool FROM repair01_debt_register WHERE class_code = '" . $esc($dcToolCls) . "'");
$blId     = $pick("SELECT baseline_id FROM repair01_w16_baseline ORDER BY issued_at DESC LIMIT 1");
$blStWas  = $pick("SELECT state FROM repair01_w16_baseline WHERE baseline_id = '" . $esc($blId) . "'");
$blSnapWas = $pick("SELECT snapshot_id FROM repair01_w16_baseline WHERE baseline_id = '" . $esc($blId) . "'");
$scRow    = $pick("SELECT axis_key FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($anyDom) . "' AND verdict = 'MEASURED' LIMIT 1");
$scNum    = (int) $pick("SELECT num FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($anyDom) . "' AND axis_key = '" . $esc($scRow) . "'");
$nmDom    = $pick("SELECT domain_code FROM repair01_w16_scorecard WHERE verdict = 'NOT_MEASURED' LIMIT 1");
$nmAxis   = $pick("SELECT axis_key FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($nmDom) . "' AND verdict = 'NOT_MEASURED' LIMIT 1");
$nmNote   = $pick("SELECT note FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($nmDom) . "' AND axis_key = '" . $esc($nmAxis) . "'");
$layNum   = (int) $pick("SELECT measured_num FROM repair01_w16_layers WHERE layer_key = '" . $esc($anyLayer) . "'");
$axInst   = $pick("SELECT instrument FROM repair01_w16_axes WHERE axis_key = '" . $esc($anyAxis) . "'");
$decRat   = $pick("SELECT rationale FROM repair01_w16_decisions WHERE decision_id = '" . $esc($anyDec) . "'");
$chMeas   = $pick("SELECT measured FROM repair01_w16_challenge WHERE finding_id = '" . $esc($anyCh) . "'");
$staRole  = $pick("SELECT required_role FROM repair01_w16_uat WHERE station_id = '" . $esc($anySta) . "'");
$layClause = $pick("SELECT clause_ref FROM repair01_w16_layers WHERE layer_key = '" . $esc($anyLayer) . "'");

$cases = array(
array('W16-02', 'طبقةٌ بلا مرجعِ بند',
  "UPDATE repair01_w16_layers SET clause_ref = '' WHERE layer_key = '" . $esc($anyLayer) . "'",
  "UPDATE repair01_w16_layers SET clause_ref = '" . $esc($layClause) . "' WHERE layer_key = '" . $esc($anyLayer) . "'"),

array('W16-03', 'دفترُ الطبقةِ يقول غيرَ ما يعيده استعلامُها',
  "UPDATE repair01_w16_layers SET measured_num = measured_num + 1 WHERE layer_key = '" . $esc($anyLayer) . "'",
  "UPDATE repair01_w16_layers SET measured_num = $layNum WHERE layer_key = '" . $esc($anyLayer) . "'"),

array('W16-04', 'محورٌ بلا حدِّ أداةٍ مُعلَن',
  "UPDATE repair01_w16_axes SET instrument = '' WHERE axis_key = '" . $esc($anyAxis) . "'",
  "UPDATE repair01_w16_axes SET instrument = '" . $esc($axInst) . "' WHERE axis_key = '" . $esc($anyAxis) . "'"),

array('W16-05', 'نطاقٌ بثمانيةِ محاورَ بدل تسعة',
  "DELETE FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($nmDom) . "' AND axis_key = '" . $esc($nmAxis) . "'",
  "INSERT INTO repair01_w16_scorecard (domain_code, axis_key, num, den, den_name, verdict, note, measured_at)
     VALUES ('" . $esc($nmDom) . "', '" . $esc($nmAxis) . "', -1, -1, '', 'NOT_MEASURED', '" . $esc($nmNote) . "', NOW())"),

array('W16-14', 'قاعدةُ تحدٍّ بلا مقيسٍ مكتوب',
  "UPDATE repair01_w16_challenge SET measured = '' WHERE finding_id = '" . $esc($anyCh) . "'",
  "UPDATE repair01_w16_challenge SET measured = '" . $esc($chMeas) . "' WHERE finding_id = '" . $esc($anyCh) . "'"),

array('W16-13', 'محطّةُ رحلةٍ بلا دورٍ مطلوب',
  "UPDATE repair01_w16_uat SET required_role = '' WHERE station_id = '" . $esc($anySta) . "'",
  "UPDATE repair01_w16_uat SET required_role = '" . $esc($staRole) . "' WHERE station_id = '" . $esc($anySta) . "'"),

array('W16-17', 'تبويبٌ موروثٌ فقد حكمَه المسجَّل',
  "DELETE FROM repair01_w16_tabs WHERE screen_file = '" . $esc($anyTab) . "' AND dept_code = '" . $esc($anyTabD) . "'",
  ''),   /* الإرجاعُ بإعادةِ الاشتقاق — أدناه */

array('W16-18', 'تبويبٌ مرفوعٌ للمالكِ بمُعرِّفِ تأجيلٍ يتيم',
  "UPDATE repair01_w16_tabs SET owner_ref = 'W16-P-99' WHERE screen_file = '" . $esc($ownerTab) . "' AND dept_code = '" . $esc($ownerTabD) . "'",
  "UPDATE repair01_w16_tabs SET owner_ref = 'W16-P-01' WHERE screen_file = '" . $esc($ownerTab) . "' AND dept_code = '" . $esc($ownerTabD) . "'"),

array('W16-19', 'صنفُ دَينٍ فقد مقياسَيه معًا',
  "UPDATE repair01_debt_register SET measure_tool = '' WHERE class_code = '" . $esc($dcToolCls) . "'",
  "UPDATE repair01_debt_register SET measure_tool = '" . $esc($dcToolWas) . "' WHERE class_code = '" . $esc($dcToolCls) . "'"),

array('W16-20', 'مقياسُ صنفٍ مسجَّلٌ لا يعمل',
  "UPDATE repair01_debt_register SET measure_sql = 'SELECT COUNT(*) FROM la_wujuda_lahu' WHERE class_code = '" . $esc($anyDcSql) . "'",
  "UPDATE repair01_debt_register SET measure_sql = '" . $esc($dcSqlWas) . "' WHERE class_code = '" . $esc($anyDcSql) . "'"),

array('W16-21', 'سجلُّ إصدارٍ بلقطةٍ غيرِ مسجَّلة',
  "UPDATE repair01_w16_baseline SET snapshot_id = 'SNAP-LA-WUJUDA-LAHA' WHERE baseline_id = '" . $esc($blId) . "'",
  "UPDATE repair01_w16_baseline SET snapshot_id = '" . $esc($blSnapWas) . "' WHERE baseline_id = '" . $esc($blId) . "'"),

array('W16-21', 'اسمُ الإصدارِ مبتورٌ عن اسمِه الكامل',
  "UPDATE repair01_w16_baseline SET version = 'ENTERPRISE-TARGET-BASELI' WHERE baseline_id = '" . $esc($blId) . "'",
  "UPDATE repair01_w16_baseline SET version = 'ENTERPRISE-TARGET-BASELINE-v1.0' WHERE baseline_id = '" . $esc($blId) . "'"),

array('W16-23', 'حالةُ الأساسِ تخالف المُشتقَّ من المقيس',
  "UPDATE repair01_w16_baseline SET state = 'REDESIGN' WHERE baseline_id = '" . $esc($blId) . "'",
  "UPDATE repair01_w16_baseline SET state = '" . $esc($blStWas) . "' WHERE baseline_id = '" . $esc($blId) . "'"),

array('W16-26', 'قرارٌ بلا حجّة',
  "UPDATE repair01_w16_decisions SET rationale = '' WHERE decision_id = '" . $esc($anyDec) . "'",
  "UPDATE repair01_w16_decisions SET rationale = '" . $esc($decRat) . "' WHERE decision_id = '" . $esc($anyDec) . "'"),
);

foreach ($cases as $c) {
    list($id, $what, $break, $back) = $c;
    if (!$conn->query($break)) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $id, $conn->error); continue; }
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($id, $failed, true);
    if ($back !== '') { $conn->query($back); }
    else {
        /* إرجاعٌ بإعادةِ الاشتقاقِ من الأداةِ نفسِها — لا بكتابةِ صفٍّ باليد */
        exec('"' . $PHP . '" "' . $ROOT . '/tools/repair01_w16_apply.php" 2>&1');
    }
    list($c2, $f2) = run_gate($PHP, $GATE);
    $restored = ($c2 === 0);
    $done++;
    if (!$caught) { $blind++; }
    printf("  %s %-8s %-46s %s%s\n", $caught ? '✔' : '✘ أعمى', $id, mb_substr($what, 0, 46),
        $caught ? 'سقط' : '**لم يسقط**', $restored ? ' · أُرجع ✔' : ' · **الإرجاعُ فشل** ✘');
    if (!$restored) { echo "     الساقطُ بعد الإرجاع: " . implode('،', $f2) . "\n"; $blind++; }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② الحزامُ الثاني — قيودُ المخطَّطِ تردُّ الكتابةَ في القاعدة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② قيودُ المخطَّط — كتابةٌ يجب أن تُردّ ────────────────────────\n";
$mustReject = array(
array('chk_w16_sc_den', 'صفٌّ مقيسٌ بمقامٍ خاوٍ',
  "INSERT INTO repair01_w16_scorecard (domain_code, axis_key, num, den, den_name, verdict, note)
     VALUES ('ZZ-TEST', 'STRUCTURAL', 0, 0, 'مقام', 'MEASURED', '')"),
array('chk_w16_sc_den', 'غيرُ مقيسٍ مكتوبٌ صفرًا',
  "INSERT INTO repair01_w16_scorecard (domain_code, axis_key, num, den, den_name, verdict, note)
     VALUES ('ZZ-TEST', 'DATA', 0, 0, '', 'NOT_MEASURED', 'سبب')"),
array('chk_w16_sc_den', 'بسطٌ يتجاوز مقامَه',
  "INSERT INTO repair01_w16_scorecard (domain_code, axis_key, num, den, den_name, verdict, note)
     VALUES ('ZZ-TEST', 'FIELD', 9, 4, 'مقام', 'MEASURED', '')"),
array('chk_w16_uat_real', 'محطّةٌ ناجحةٌ بلا فاعلٍ حقيقيّ',
  "UPDATE repair01_w16_uat SET status = 'PASSED' WHERE station_id = '" . $esc($anySta) . "'"),
array('chk_w16_uat_negative', 'مسارٌ سالبٌ عابرٌ بلا قيدِ محاولة',
  "UPDATE repair01_w16_uat SET status = 'PASSED', actor_user_id = 1, actor_name = 'ف',
          acted_at = NOW(), evidence_ref = 'د' WHERE is_negative = 1 LIMIT 1"),
array('chk_w16_bl_owner', 'ختمُ مالكٍ بلا مرجعِ ختم',
  "UPDATE repair01_w16_baseline SET state = 'OWNER_APPROVED' WHERE baseline_id = '" . $esc($blId) . "'"),
array('chk_w16_ch_evidence', 'قاعدةُ تحدٍّ بلا مصدرٍ أوّليّ',
  "INSERT INTO repair01_w16_challenge (finding_id, rule_key, title, severity, primary_source, evidence, raised_at)
     VALUES ('ZZ-01', 'ZZ', 'ت', 'ACCEPT', '', 'ش', NOW())"),
array('chk_w16_tab_why', 'حكمُ تبويبٍ بلا سبب',
  "INSERT INTO repair01_w16_tabs (screen_file, dept_code, judged_verdict, disposition, why, decided_at)
     VALUES ('zz_test.php', 'ZZ', 'KEEP_ITEM', 'KEEP_ITEM', '', NOW())"),
array('chk_w16_tab_why', 'مرفوعٌ للمالكِ بلا مُعرِّفِ تأجيل',
  "INSERT INTO repair01_w16_tabs (screen_file, dept_code, judged_verdict, disposition, why, decided_at, owner_ref)
     VALUES ('zz_test2.php', 'ZZ', 'HIDDEN', 'GRANT_GAP_TO_OWNER', 'سبب', NOW(), '')"),
array('chk_w16_layer_measure', 'طبقةٌ بلا استعلامِ قياس',
  "INSERT INTO repair01_w16_layers (layer_no, layer_key, layer_name_ar, measure_sql, den_name)
     VALUES (99, 'ZZ', 'ت', '', 'م')"),
);
foreach ($mustReject as $m) {
    list($chk, $what, $sql) = $m;
    $ok = @$conn->query($sql);
    $rejected = ($ok === false);
    $done++;
    if (!$rejected) {
        $blind++;
        /* نُظِّف ما قبِلته القاعدةُ خطأً كي لا يبقى صفٌّ زائف */
        $conn->query("DELETE FROM repair01_w16_scorecard WHERE domain_code = 'ZZ-TEST'");
        $conn->query("DELETE FROM repair01_w16_challenge WHERE finding_id = 'ZZ-01'");
        $conn->query("DELETE FROM repair01_w16_tabs WHERE dept_code = 'ZZ'");
        $conn->query("DELETE FROM repair01_w16_layers WHERE layer_key = 'ZZ'");
        $conn->query("UPDATE repair01_w16_uat SET status = 'PENDING', actor_user_id = 0,
                             actor_name = '', acted_at = NULL, evidence_ref = ''");
        $conn->query("UPDATE repair01_w16_baseline SET state = '" . $esc($blStWas) . "'
                       WHERE baseline_id = '" . $esc($blId) . "'");
    }
    printf("  %s %-22s %-46s %s\n", $rejected ? '✔' : '✘ قبِلت', $chk, mb_substr($what, 0, 46),
        $rejected ? 'رُدَّت' : '**قُبِلت والقيدُ زينة**');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الحزامُ الثالث — حالاتُ الملفِّ بكسرٍ وإرجاعٍ متحقَّقٍ بتجزئة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ حالاتُ الملفِّ — كسرٌ على القرصِ وإرجاعٌ بتجزئة ─────────────\n";

$fileCase = function ($id, $what, $path, $mutate) use ($PHP, $GATE, &$blind, &$done) {
    $orig = @file_get_contents($path);
    if ($orig === false) { printf("  ⚠ %-8s ملفٌّ غائب: %s\n", $id, $path); return; }
    $h0 = hash('sha256', $orig);
    file_put_contents($path, $mutate($orig));
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($id, $failed, true);
    file_put_contents($path, $orig);
    $restored = (hash_file('sha256', $path) === $h0);
    $done++;
    if (!$caught) { $blind++; }
    if (!$restored) { $blind++; }
    printf("  %s %-8s %-46s %s · %s\n", $caught ? '✔' : '✘ أعمى', $id, mb_substr($what, 0, 46),
        $caught ? 'سقط' : '**لم يسقط**', $restored ? 'أُرجع بتجزئةٍ مطابقة ✔' : '**الإرجاعُ فشل** ✘');
};

/* W16-15 · نزعُ قدرةِ محرّكِ التحدّي على إصدارِ REDESIGN */
$fileCase('W16-15', 'محرّكُ التحدّي بلا قاعدةٍ تُصدر REDESIGN',
    $ROOT . '/tools/repair01_w16_challenge.php',
    function ($s) { return str_replace("'REDESIGN'", "'CONCERN'", $s); });

/* W16-16 · جعلُ محرّكِ التحدّي يقرأ دفترَ موجة */
$fileCase('W16-16', 'محرّكُ التحدّي يقرأ دفترَ موجةٍ بنت الهدف',
    $ROOT . '/tools/repair01_w16_challenge.php',
    function ($s) { return $s . "\n/* repair01_w15_scope */\n"; });

/* W16-27 · إفراغُ وثيقةِ المقامات */
$fileCase('W16-27', 'وثيقةُ المقاماتِ بلا نطاقاتِها',
    $ROOT . '/docs/REPAIR01_20260823/W16_SCORECARD.md',
    function ($s) { return "# فارغة\n"; });

/* W16-08 · نشرُ نسبةٍ مجمَّعةٍ في وثيقةٍ حيّة */
$fileCase('W16-08', 'وثيقةٌ تنشر «٪ مكتمل» مجمَّعةً',
    $ROOT . '/docs/REPAIR01_20260823/W16_LAYERS.md',
    function ($s) { return $s . "\nالإنجاز الكلي: 95% مكتمل\n"; });

/* W16-01 · نزعُ شاهدٍ سالبٍ لمرحلةٍ سابقة (‏بنقلِه جانبًا ثمَّ إرجاعِه) */
$w0n = $ROOT . '/tools/repair01_w0_negative.php';
if (is_file($w0n)) {
    $h0 = hash_file('sha256', $w0n);
    $bak = $w0n . '.negtest';
    rename($w0n, $bak);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W16-01', $failed, true);
    rename($bak, $w0n);
    $restored = (is_file($w0n) && hash_file('sha256', $w0n) === $h0);
    $done++;
    if (!$caught) { $blind++; }
    if (!$restored) { $blind++; }
    printf("  %s %-8s %-46s %s · %s\n", $caught ? '✔' : '✘ أعمى', 'W16-01',
        'مرحلةٌ سابقةٌ فقدت شاهدَها السالب', $caught ? 'سقط' : '**لم يسقط**',
        $restored ? 'أُرجع بتجزئةٍ مطابقة ✔' : '**الإرجاعُ فشل** ✘');
}

/* W16-24 · مصنَّفٌ مجمَّدٌ يُنقل جانبًا — والحاجبُ يجب أن يسقط */
$frz = $ROOT . '/docs/REPAIR01_20260823/12 · مراجعة القيادة.xlsx';
if (is_file($frz)) {
    $h0 = hash_file('sha256', $frz);
    $bak = $frz . '.negtest';
    rename($frz, $bak);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array('W16-24', $failed, true);
    rename($bak, $frz);
    $restored = (is_file($frz) && hash_file('sha256', $frz) === $h0);
    $done++;
    if (!$caught) { $blind++; }
    if (!$restored) { $blind++; }
    printf("  %s %-8s %-46s %s · %s\n", $caught ? '✔' : '✘ أعمى', 'W16-24',
        'مصنَّفٌ مجمَّدٌ غاب عن موضعِه', $caught ? 'سقط' : '**لم يسقط**',
        $restored ? 'أُرجع بتجزئةٍ مطابقة ✔' : '**الإرجاعُ فشل** ✘');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ الحزامُ الرابع — أيستطيع محرّكُ التحدّي إصدارَ REDESIGN فعلًا؟
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **وهذا شرطُ البندِ ٥٠ ولا يُثبَت بقراءةِ كودٍ وحدَها**: يُزرع خرقٌ حقيقيٌّ
   في مصدرٍ أوّليٍّ ويُطلَب من المحرّكِ أن يقوله. */
echo "\n④ قدرةُ محرّكِ التحدّي على إصدارِ REDESIGN ───────────────────\n";
$chTool = $ROOT . '/tools/repair01_w16_challenge.php';
$runCh = function () use ($PHP, $chTool) {
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $chTool . '" 2>&1', $out, $code);
    $v = '';
    foreach ($out as $l) {
        if (mb_strpos($l, 'حكمُ المراجعةِ المستقلّة') !== false) {
            if (preg_match('~(REDESIGN|CONCERN|ACCEPT)~', $l, $m)) { $v = $m[1]; }
        }
    }
    return array($v, $code);
};
list($vBefore, ) = $runCh();
/* ⛔ **وزرعٌ رُدَّ صامتًا يُقرأ عجزًا في المحرّك**: القاعدةُ نفسُها تحرس هذا
   الجدولَ (`chk_w135_why`)، فإن رفضت الكتابةَ ولم نتحقّق **اتّهمنا المحرّكَ
   بما لم يقع**. فالزرعُ يُتحقَّق منه قبل أن يُسأل المحرّك. */
$planted = $conn->query("INSERT INTO repair01_screen_registry (screen_id, screen_file, route,
                owner_code, lifecycle, on_disk, origin, ownership_verdict, surface_kind,
                canonical_label_ar, verdict_rule)
              VALUES ('SCR-ZZ99', 'zz_negative_probe.php', 'ZZ/zz_negative_probe.php', 'DEP-01',
                      'LIVE_REGISTERED', 1, 'W16', 'DOMAIN_SOURCE', 'SOURCE', 'شاهد سالب',
                      'W16_NEGATIVE_PROBE')");
if (!$planted) {
    echo "  ✘ تعذّر زرعُ الخرق: " . $conn->error . " — ولا يُحكم على المحرّكِ بزرعٍ لم يقع\n";
    $blind++;
    $vAfter = '(لم يُزرع)'; $vBack = $vBefore;
} else {
    list($vAfter, ) = $runCh();
    $conn->query("DELETE FROM repair01_screen_registry WHERE screen_id = 'SCR-ZZ99'");
    list($vBack, ) = $runCh();
}
$done++;
$able = ($vAfter === 'REDESIGN');
if ($planted && !$able) { $blind++; }
if ($vBack !== $vBefore) { $blind++; }
printf("  %s قبل الزرع %s · بعد زرعِ سطحٍ يزعم قرصًا لا وجودَ له **%s** · بعد الإرجاع %s\n",
    $able ? '✔' : '✘ عاجز', $vBefore, $vAfter, $vBack);
printf("     %s\n", $able
    ? 'المحرّكُ يستطيع إصدارَ REDESIGN على خرقٍ حقيقيٍّ — والبوّاباتُ الستَّ عشرةَ خضراءُ في الحالَين'
    : '**محرّكٌ لا يستطيع الرفضَ ليس تحدّيًا** — والبندُ ٥٠ غيرُ مستوفًى');

/* ═══════════════════════════════════════════════════════════════════════════
   الحكم
   ═══════════════════════════════════════════════════════════════════════════ */
list($cF, $fF) = run_gate($PHP, $GATE);
echo "\n────────────────────────────────────────────────────────────\n";
printf("حالاتٌ: %d · حواجبُ عمياءُ أو إرجاعٌ فاشل: %d · البوّابةُ بعد الكلّ: %s\n",
       $done, $blind, $cF === 0 ? 'خضراء ✔' : 'ساقطة ✘ (' . implode('،', $fF) . ')');
$bad = ($blind > 0 || $cF !== 0);
echo $bad ? "الحكم: **الفحصُ السلبيُّ لم يمرّ** ✘\n" : "الحكم: كلُّ حاجبٍ يسقط عند كسرِه ويرجع ✔\n";
exit($bad ? 1 : 0);

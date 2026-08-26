<?php
/**
 * tools/repair01_w0_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ صفر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الأخضرُ لا يُثبت شيئًا وحدَه: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدمِ. فهنا نكسر كلَّ حاجبٍ على حِدةٍ ونطلب من البوّابةِ أن تسقط — ثم
 *   نُرجع الحالةَ. الحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 * ◆ الإرجاعُ مضمونٌ بـtry/finally، ويُتحقَّق منه بإعادةِ تشغيلِ البوّابةِ
 *   في النهايةِ ووجوبِ عودتها خضراء.
 *
 * التشغيل: php tools/repair01_w0_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PHP = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w0_gate.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE) {
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) { if (mb_strpos($l, '✘ G0-') !== false && preg_match('/G0-\d+/', $l, $m)) { $failed[] = $m[0]; } }
    return array($code, $failed);
}

/* الأساس: يجب أن تكون خضراءَ قبل البدء */
list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* كلُّ حالةِ كسرٍ: [الحاجبُ المتوقَّعُ سقوطُه، الكسر، الإرجاع] */
$cases = array(
    array('G0-01', "تبديلُ تجزئةِ ملفٍّ مصدر",
        "UPDATE repair01_source_files SET sha256=REPEAT('0',64) WHERE file_no='09'",
        null /* يُلتقط قبلًا */),
    array('G0-02', "قلبُ حالةِ قرارٍ معتمد",
        "UPDATE repair01_decisions SET status='NEEDS_OWNER_DECISION' WHERE decision_id='DEC-OPEN-03'",
        "UPDATE repair01_decisions SET status='APPROVED' WHERE decision_id='DEC-OPEN-03'"),
    array('G0-03', "معتمدٌ يحمل حجبًا",
        "UPDATE repair01_decisions SET blocking_level='CONFIG_PENDING' WHERE decision_id='DEC-CEO-03'",
        "UPDATE repair01_decisions SET blocking_level='NONE' WHERE decision_id='DEC-CEO-03'"),
    array('G0-05', "ثغرةٌ في ترقيمِ الإدارات",
        "UPDATE repair01_departments SET display_order=99 WHERE canonical_code='DEP-09'",
        "UPDATE repair01_departments SET display_order=9 WHERE canonical_code='DEP-09'"),
    array('G0-06', "نزعُ جسرِ مسمّى حيّ",
        "DELETE FROM repair01_dept_crosswalk WHERE legacy_name='إدارة المخازن'",
        "INSERT INTO repair01_dept_crosswalk (legacy_name,canonical_code,verdict,split_rule,note) VALUES ('إدارة المخازن','DEP-17','MAP','','')"),
    array('G0-08', "وسمُ شبحٍ بأنّه على القرص",
        "UPDATE repair01_surfaces SET on_disk=1 WHERE on_disk=0 LIMIT 1",
        null /* يُرجَع بإعادةِ الاستيعابِ الموضعيّ */),
    array('G0-11', "نزعُ تصنيفِ الحجبِ عن قرار",
        "UPDATE repair01_decisions SET blocker_type=NULL WHERE decision_id='DEC-OPEN-01'",
        "UPDATE repair01_decisions SET blocker_type='THRESHOLD' WHERE decision_id='DEC-OPEN-01'"),
    array('G0-11', "وسمُ عتبةٍ بأنّها حاجبٌ بنيويّ",
        "UPDATE repair01_decisions SET blocking_level='STRUCTURAL_TARGET_BLOCKER' WHERE decision_id='DEC-OPEN-02'",
        "UPDATE repair01_decisions SET blocking_level='CONFIG_PENDING' WHERE decision_id='DEC-OPEN-02'"),
    array('G0-09', "محوُ مرجعِ خليّةٍ من صفّ",
        "UPDATE repair01_ownership SET src_ref='' WHERE id=(SELECT MIN(id) FROM (SELECT id FROM repair01_ownership) z)",
        null),
    /* ⚠ **الكسرُ من زاويةٍ لا يحرسها مخطَّطٌ**: العمودُ `TEXT` بلا `CHECK`، فلو
         كان الحاجبُ أعمى لعبرَ الجوفُ صامتًا كما عبرَ فعلًا في `DEC-OPEN-15`.
         والقيمةُ المكسورُ بها `—` هي القيمةُ الواقعيّةُ التي وُجدت لا قيمةٌ
         مصطنَعةٌ — فالكسرُ يُعيد إنتاجَ العطبِ نفسِه لا شبيهًا له. */
    array('G0-13', "قرارٌ معتمَدٌ يُفرَّغ من نصِّ حكمِه",
        "UPDATE repair01_decisions SET owner_decision='—' WHERE decision_id='DEC-OPEN-12'",
        null /* يُرجَع بالقيمةِ الملتقَطةِ قبلًا */),
);

/* التقاطُ ما نحتاج إرجاعَه بالقيمة */
$sha09 = null;
$r = $conn->query("SELECT sha256 FROM repair01_source_files WHERE file_no='09'");
if ($r && ($x = $r->fetch_row())) { $sha09 = $x[0]; }
$ownMin = null; $ownSrc = null;
$r = $conn->query("SELECT id, src_ref FROM repair01_ownership ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $ownMin = $x['id']; $ownSrc = $x['src_ref']; }
$dec12 = null;
$r = $conn->query("SELECT owner_decision FROM repair01_decisions WHERE decision_id='DEC-OPEN-12'");
if ($r && ($x = $r->fetch_row())) { $dec12 = $x[0]; }
$ghostId = null;
$r = $conn->query("SELECT id FROM repair01_surfaces WHERE on_disk=0 ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $ghostId = $x[0]; }

$blind = 0; $done = 0;
foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    /* تخصيصُ الكسرِ والإرجاعِ للحالاتِ ذاتِ القيمِ الملتقَطة */
    if ($want === 'G0-08') { $break = "UPDATE repair01_surfaces SET on_disk=1 WHERE id=" . (int) $ghostId; $restore = "UPDATE repair01_surfaces SET on_disk=0 WHERE id=" . (int) $ghostId; }
    if ($want === 'G0-09') { $break = "UPDATE repair01_ownership SET src_ref='' WHERE id=" . (int) $ownMin; $restore = "UPDATE repair01_ownership SET src_ref='" . $conn->real_escape_string($ownSrc) . "' WHERE id=" . (int) $ownMin; }
    if ($want === 'G0-01') { $restore = "UPDATE repair01_source_files SET sha256='" . $conn->real_escape_string($sha09) . "' WHERE file_no='09'"; }
    if ($want === 'G0-13') { $restore = "UPDATE repair01_decisions SET owner_decision='" . $conn->real_escape_string($dec12) . "' WHERE decision_id='DEC-OPEN-12'"; }

    if ($conn->query($break) === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-34s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-34s **لم تسقط** — الحاجبُ أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }
    if ($conn->query($restore) === false) { printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++; }
    $done++;
}

/* التحقّقُ من الإرجاع */
echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

printf("\nالفحصُ السلبيّ: %d حاجبًا مُختبَرًا · أعمى %d\n", $done, $blind);
echo ($blind === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($blind === 0 ? 0 : 1);

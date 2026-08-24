<?php
/**
 * tools/repair01_stage_assign.php — إسنادُ كلِّ متطلَّبٍ إلى مرحلتِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: ملفُّ مرحلةٍ يقول «١١٩ متطلَّبًا» ولا يقول **أيَّها** يُجبر
 *   الجلسةَ على اشتقاقِ نطاقِها بنفسِها — وهو أضيعُ ما يضيع يومَ تنفيذ.
 * ◆ **والقاعدةُ صريحةٌ لا مبهمة**: الموجةُ أ تُقسَّم بموضعِ المجموعةِ من
 *   الدورة (تأسيسٌ أوّلًا ثمّ الباقي بالوحدة) — لأنّ §17 يشترط المراجعَ
 *   الأمَّ قبل الحقيقةِ الميدانية. وبقيّةُ الموجاتِ تُقسَّم بالوحدةِ لأنّ
 *   حدودَها التنظيميّةَ هي حدودُ العمل.
 * ◆ **والمجموعُ يُتحقَّق منه**: لا متطلَّبَ بلا مرحلة، ومجموعُ المراحلِ = ٤٢٩.
 *
 * التشغيل: php tools/repair01_stage_assign.php
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

/* العمودُ يُضاف مرّةً */
$has = $conn->query("SHOW COLUMNS FROM repair01_requirements LIKE 'stage_no'");
if (!$has || $has->num_rows === 0) {
    if ($conn->query("ALTER TABLE repair01_requirements ADD COLUMN `stage_no` TINYINT UNSIGNED NULL AFTER `wave`, ADD KEY `k_stage` (`stage_no`)") === false) {
        exit("تعذّر إضافةُ العمود: {$conn->error}\n");
    }
    echo "✔ أُضيف العمودُ stage_no\n";
}
$conn->query("UPDATE repair01_requirements SET stage_no=NULL");

/* ═══ القاعدةُ ═══
   الموجةُ أ: مجموعاتُ التأسيسِ والمرجعيّاتِ كلُّها إلى W03 (§17-A0 «Masters أوّلًا»)،
              وما بقي يُقسَّم بالوحدة. وبقيّةُ الموجاتِ بالوحدةِ مباشرةً. */
$A_SETUP = array(
    'التأسيس', 'التأسيس المرجعي',
    'أ · التعريف والسياسات', 'ط · المرجعيات والمصالحة',
    'التأهيل والجاهزية',
);
$A_BY_UNIT = array(  /* بعد استبعادِ التأسيس */
    '11 إدارة التشغيل' => 4, '12 إدارة الموقع' => 4,
    '04 إدارة الأسطول والأصول' => 5, '13 إدارة القوى التشغيلية' => 5,
    '14 إدارة الصيانة' => 6, '15 إدارة النقل والترحيل' => 6,
);
$BY_UNIT = array(
    'B' => array('01 إدارة المبيعات التعاقدية والعقود' => 7, '02 إدارة الموردين' => 7,
                 '16 إدارة المشتريات' => 8, '17 إدارة المخازن' => 8),
    'C' => array('05 الإدارة المالية' => 10, '06 إدارة الخزينة' => 10,
                 '03 إدارة التمويل والممولين' => 11),
    'D' => array('07 إدارة الموارد البشرية' => 12, '10 إدارة البلاغات' => 12,
                 '08 الحوكمة والالتزام' => 13, '09 إدارة المخاطر' => 13,
                 'AS المراجعة الداخلية المستقلة' => 13),
    'E' => array('E1 مساحة الرئيس التنفيذي' => 14, 'E2 مساحة النواب' => 14,
                 'WS مساحة عملي' => 14),
);

$r = $conn->query("SELECT requirement_id, wave, unit, group_name FROM repair01_requirements");
$assigned = 0; $unassigned = array(); $byStage = array();
while ($x = $r->fetch_assoc()) {
    $s = null;
    if ($x['wave'] === 'A') {
        $s = in_array(trim($x['group_name']), $A_SETUP, true)
            ? 3
            : (isset($A_BY_UNIT[trim($x['unit'])]) ? $A_BY_UNIT[trim($x['unit'])] : null);
    } elseif (isset($BY_UNIT[$x['wave']][trim($x['unit'])])) {
        $s = $BY_UNIT[$x['wave']][trim($x['unit'])];
    }
    if ($s === null) { $unassigned[] = "{$x['requirement_id']} [{$x['wave']}] {$x['unit']} › {$x['group_name']}"; continue; }
    $conn->query("UPDATE repair01_requirements SET stage_no=$s WHERE requirement_id='" . $conn->real_escape_string($x['requirement_id']) . "'");
    $assigned++;
    $byStage[$s] = (isset($byStage[$s]) ? $byStage[$s] : 0) + 1;
}

ksort($byStage);
echo "\n═══ الإسناد ═══\n";
$tot = 0;
foreach ($byStage as $s => $n) {
    $nn = str_pad($s, 2, '0', STR_PAD_LEFT);
    printf("  W%s : %3d متطلَّبًا\n", $nn, $n);
    $tot += $n;
}
echo "───────────────\n";
printf("  المجموع: %d · بلا مرحلة: %d\n", $tot, count($unassigned));
if ($unassigned) {
    echo "\n⚠ بلا مرحلة:\n";
    foreach (array_slice($unassigned, 0, 20) as $u) { echo "   · $u\n"; }
}
$dbTot = $conn->query("SELECT COUNT(*) FROM repair01_requirements")->fetch_row()[0];
echo "\nالتحقّق: " . ($tot == $dbTot && !$unassigned ? "✔ كلُّ متطلَّبٍ ($dbTot) أُسنِد" : "✘ $tot من $dbTot") . "\n";
exit(($tot == $dbTot && !$unassigned) ? 0 : 1);

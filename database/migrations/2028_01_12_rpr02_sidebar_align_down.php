<?php
/**
 * 2028_01_12_rpr02_sidebar_align_down.php — تراجعُ محاذاةِ السايدبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والتراجعُ يردُّ ما رآه المستخدمُ قبلَ المحاذاة** — من الجدولِ نفسِه لا
 *   من ذاكرةٍ ولا بإعادةِ تشغيلِ أداة: لكلِّ صفٍّ `before_group` و`before_order`
 *   يُكتبان في `nav_canonical`، ثمَّ يُسقَط الموضع.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$q = @$conn->query("SELECT route, before_group, before_order FROM repair01_sidebar_align");
$back = 0;
while ($q && ($x = $q->fetch_assoc())) {
    $ok = $conn->query("UPDATE `nav_canonical`
                           SET `group_name` = '" . $conn->real_escape_string($x['before_group']) . "',
                               `sort_no` = " . (int) $x['before_order'] . "
                         WHERE `route` = '" . $conn->real_escape_string($x['route']) . "'");
    if ($ok) { $back += $conn->affected_rows; }
}
echo "  ✔ رُدَّ إلى ما قبلَ المحاذاة: $back مسارًا\n";
if (!$conn->query("DROP TABLE IF EXISTS `repair01_sidebar_align`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_sidebar_align`\n";

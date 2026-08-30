<?php
/**
 * 2028_01_15_rpr02_s4_nav_sort_align_down.php — تراجعُ محاذاةِ ترتيبِ المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والتراجعُ يردُّ المخزنَ إلى ما كان — من الجدولِ نفسِه لا من ذاكرةٍ ولا
 *   بإعادةِ تشغيلِ أداة**: لكلِّ صفٍّ `before_sort` يُكتب في
 *   `nav_items.sort_order`، ثمَّ يُسقَط الموضع.
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

$back = 0;
$q = @$conn->query("SELECT nav_item_id, before_sort FROM repair01_nav_sort_align WHERE applied_at IS NOT NULL");
while ($q && ($x = $q->fetch_assoc())) {
    if ($conn->query("UPDATE `nav_items` SET `sort_order` = " . (int) $x['before_sort']
                   . " WHERE `id` = " . (int) $x['nav_item_id'])) { $back += $conn->affected_rows; }
}
echo "  ✔ رُدَّ إلى ما قبلَ المحاذاة: $back بندًا\n";

if (!$conn->query("DROP TABLE IF EXISTS `repair01_nav_sort_align`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_nav_sort_align`\n";

<?php
/**
 * 2028_01_14_rpr02_s7_canon_screen_id_down.php — تراجعُ ربطِ السجلِّ بمعرِّفِ الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والتراجعُ يردُّ السجلَّ إلى ما كان — من الجدولِ نفسِه لا من ذاكرةٍ ولا
 *   بإعادةِ تشغيلِ أداة**: لكلِّ صفٍّ `before_screen_id` يُكتب في
 *   `nav_canonical.screen_id`، ثمَّ يُسقَط الموضع.
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
$q = @$conn->query("SELECT route, before_screen_id FROM repair01_canon_screen_bind WHERE applied_at IS NOT NULL");
while ($q && ($x = $q->fetch_assoc())) {
    $ok = $conn->query("UPDATE `nav_canonical` SET `screen_id` = '" . $conn->real_escape_string($x['before_screen_id']) . "'
                         WHERE `route` = '" . $conn->real_escape_string($x['route']) . "'");
    if ($ok) { $back += $conn->affected_rows; }
}
echo "  ✔ رُدَّ إلى ما قبلَ الربط: $back مسارًا\n";

if (!$conn->query("DROP TABLE IF EXISTS `repair01_canon_screen_bind`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_canon_screen_bind`\n";

<?php
/**
 * 2028_01_17_rpr02_s6_nav_perm_code_down.php — تراجعُ سدِّ ثقبِ الظهور
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والتراجعُ يعيد `permission_code` إلى `NULL` من الجدولِ نفسِه** — أي
 *   **يعيد فتحَ الثقب**، ولا يُفعل إلّا بقرارٍ صريح: كلُّ صفٍّ هنا كان قبلَه
 *   `NULL` (‏وهو شرطُ دخولِه الموضعَ أصلًا).
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
$q = @$conn->query("SELECT nav_item_id FROM repair01_nav_perm_bind WHERE applied_at IS NOT NULL");
while ($q && ($x = $q->fetch_row())) {
    if ($conn->query("UPDATE `nav_items` SET `permission_code` = NULL WHERE `id` = " . (int) $x[0])) {
        $back += $conn->affected_rows;
    }
}
echo "  ✔ أُعيد إلى الظهورِ بلا فحص: $back بندًا ⛔ **والثقبُ عاد مفتوحًا بقرارِ التراجع**\n";

if (!$conn->query("DROP TABLE IF EXISTS `repair01_nav_perm_bind`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_nav_perm_bind`\n";

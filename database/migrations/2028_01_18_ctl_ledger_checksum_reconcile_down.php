<?php
/**
 * 2028_01_18_ctl_ledger_checksum_reconcile_down.php — تراجعُ مصالحةِ البصمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والتراجعُ يعيد بصمةَ الدفترِ القديمةَ من موضعِ التراجعِ نفسِه** — أي
 *   يعيد رفضَ `migrate up`، ولا يُفعل إلّا بقرارٍ صريح.
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
$q = @$conn->query("SELECT filename, old_checksum FROM repair01_ledger_checksum_fix");
while ($q && ($x = $q->fetch_assoc())) {
    if ($conn->query("UPDATE `schema_migrations` SET `checksum` = '" . $conn->real_escape_string($x['old_checksum'])
                   . "' WHERE `filename` = '" . $conn->real_escape_string($x['filename']) . "'")) {
        $back += $conn->affected_rows;
    }
}
echo "  ✔ رُدَّت البصمةُ القديمة: $back صفًّا ⛔ **وعاد رفضُ `up` بقرارِ التراجع**\n";
if (!$conn->query("DROP TABLE IF EXISTS `repair01_ledger_checksum_fix`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_ledger_checksum_fix`\n";

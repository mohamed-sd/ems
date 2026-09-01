<?php
/**
 * 2028_03_16_site15_invoice_ref_down.php — العكس
 * @migration-objects: drop col tre_petty_expense.invoice_ref
 * ⛔ ولا يُسقَط عمودٌ فيه بياناتٌ صامتًا — يُسمّى ويُترَك، ويُحذف قيدُه فحسب.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$conn->query("DELETE FROM gov_field_class
               WHERE screen_code = 'site_field_expense' AND field_key = 'invoice_ref'
                 AND label_ar = 'المستند/الفاتورة'");
echo "قيودٌ محذوفة: " . $conn->affected_rows . "\n";

$q = $conn->query("SHOW COLUMNS FROM `tre_petty_expense` LIKE 'invoice_ref'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `tre_petty_expense` WHERE `invoice_ref` IS NOT NULL");
    $used = $r ? (int) $r->fetch_row()[0] : 0;
    if ($used > 0) { echo "أُبقي العمودُ لبياناتِه ({$used} صفًّا فيه قيمة)\n"; }
    elseif ($conn->query("ALTER TABLE `tre_petty_expense` DROP COLUMN `invoice_ref`")) { echo "أُسقط العمود\n"; }
} else { echo "= العمودُ غيرُ موجود\n"; }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

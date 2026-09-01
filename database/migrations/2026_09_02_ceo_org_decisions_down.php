<?php
/**
 * 2026_09_02_ceo_org_decisions_down.php — عكسُ سجلِّ القراراتِ الهيكليّة
 * @migration-objects: ceo_org_decisions
 * ⛔ ولا يُسقَط جدولٌ فيه قراراتٌ مسجَّلةٌ صامتًا — يُسمّى عددُها ويُترَك.
 *    وللإسقاطِ رغمَ ذلك: `--force`.
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
$force = in_array('--force', $argv, true);

$q = $conn->query("SHOW TABLES LIKE 'ceo_org_decisions'");
if (!$q || !$q->num_rows) { echo "= ceo_org_decisions غيرُ موجود\n"; }
else {
    $c = $conn->query("SELECT COUNT(*) c FROM `ceo_org_decisions`");
    $n = $c ? (int) $c->fetch_assoc()['c'] : 0;
    if ($n > 0 && !$force) { echo "! ceo_org_decisions فيه {$n} قرارًا — لم يُسقَط. للإسقاط: --force\n"; }
    else {
        echo $conn->query("DROP TABLE `ceo_org_decisions`") ? "- جدول ceo_org_decisions ({$n} صفًّا)\n"
                                                            : "x ceo_org_decisions: " . $conn->error . "\n";
    }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000), 'baseline');

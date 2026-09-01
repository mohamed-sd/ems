<?php
/**
 * 2028_04_15_govui_state_models_down.php — عكسُ سجلِّ آلاتِ الحالة
 * @migration-objects: gov_state_models, gov_state_model_bind
 * ⛔ ولا يُسقَط جدولٌ فيه صفوفٌ صامتًا — يُسمّى عددُها ويُترَك. وللإسقاطِ رغمَ
 *    ذلك: `--force`. (‏والسجلُّ مولَّدٌ من الملفاتِ الحاكمةِ فيُعاد بناؤه
 *    بـ`tools/govui_state_author.php`، لكنَّ الربطَ قد يحمل حكمًا يدويًّا.)
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

foreach (array('gov_state_model_bind', 'gov_state_models') as $t) {
    $q = $conn->query("SHOW TABLES LIKE '{$t}'");
    if (!$q || !$q->num_rows) { echo "= {$t} غيرُ موجود\n"; continue; }
    $c = $conn->query("SELECT COUNT(*) c FROM `{$t}`");
    $n = $c ? (int) $c->fetch_assoc()['c'] : 0;
    if ($n > 0 && !$force) { echo "! {$t} فيه {$n} صفًّا — لم يُسقَط. للإسقاط: --force\n"; continue; }
    echo $conn->query("DROP TABLE `{$t}`") ? "- جدول {$t} ({$n} صفًّا)\n"
                                           : "x {$t}: " . $conn->error . "\n";
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000), 'baseline');

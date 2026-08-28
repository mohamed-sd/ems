<?php
/**
 * 2027_12_23_rpr03_consumer_contract_fields_down.php — نقضُ حقولِ عقدِ الأثر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والنقضُ يمحو عقودًا مسجَّلة** — فيُقاس المملوءُ ويُردُّ النقضُ إن وُجد.
 *   وعقدٌ يُمحى لا يُستعاد من الشيفرة: هو حكمٌ لكلِّ مستهلكٍ على حدة.
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

$r = @$conn->query("SELECT COUNT(*) FROM event_consumers
                     WHERE payload_schema <> '' OR idempotency_key <> ''
                        OR failure_behavior <> '' OR audit_effect <> ''");
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) {
    exit("⛔ **$n اشتراكًا يحمل عقدًا مسجَّلًا** — والنقضُ يمحوه ولا يُستعاد. صرِّحْ أوّلًا.\n");
}
foreach (array('audit_effect', 'failure_behavior', 'idempotency_key', 'payload_schema') as $c) {
    $r = $conn->query("SHOW COLUMNS FROM `event_consumers` LIKE '$c'");
    if ($r && $r->num_rows) {
        $conn->query("ALTER TABLE `event_consumers` DROP COLUMN `$c`");
        echo "  ✔ أُسقط `$c`\n";
    }
}
echo "✔ نُقضت حقولُ عقدِ الأثر\n";

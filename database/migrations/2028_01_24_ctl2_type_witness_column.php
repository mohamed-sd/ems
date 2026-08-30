<?php
/**
 * 2028_01_24_ctl2_type_witness_column.php — عمودُ شاهدِ التصنيف
 * ◆ أمرُ الاستئنافِ الثاني: تصنيفُ غيرِ المصنَّفِ بالعقودِ الخمسةِ —
 *   **وكلُّ حكمِ نوعٍ يحمل مفردةَ حسمِه شاهدًا** في عمودٍ خاصٍّ به،
 *   فلا نوعَ بالذوقِ ولا شاهدَ مدسوسًا في عمودِ حالةٍ آخر.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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

$has = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE 'type_witness'");
if ($has && $has->num_rows > 0) {
    echo "  ✔ `type_witness` موجودٌ سلفًا — لا فعل\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_requirements`
        ADD COLUMN `type_witness` VARCHAR(500) NULL COMMENT 'مفردة حسم النوع من الدفتر الحاكم بلقطتها'");
    if (!$ok) { exit("✘ {$conn->error}\n"); }
    echo "  ✔ أُضيف `repair01_requirements.type_witness`\n";
}
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ عمودُ شاهدِ التصنيفِ جاهز\n";

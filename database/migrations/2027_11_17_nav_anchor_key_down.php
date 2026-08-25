<?php
/**
 * 2027_11_17_nav_anchor_key_down.php — تراجعُ مرساةِ القشرة.
 * ⚠ لا يُشغَّل قبلَ إرجاعِ `insidebar.php` و`includes/unified_nav.php`
 *   و`includes/nav_anchors.php` بـ`git checkout main`: الغلافُ يقرأ العمودَ،
 *   ونزعُه وحدَه يُفقد المراسي الخمسَ من كلِّ قائمة.
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

$r = $conn->query("SHOW COLUMNS FROM `nav_canonical` LIKE 'anchor_key'");
if (!$r || $r->num_rows === 0) { exit("= nav_canonical.anchor_key (غيرُ موجود)\n"); }
if ($conn->query("ALTER TABLE `nav_canonical` DROP INDEX `uq_anchor`, DROP COLUMN `anchor_key`") === false) {
    exit("✘ {$conn->error}\n");
}
echo "✔ نُزع nav_canonical.anchor_key\n";
exit(0);

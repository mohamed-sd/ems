<?php
/**
 * 2027_11_14_repair01_blocker_type_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تراجعُ RPR-PATCH-01 §3 — نزعُ عمودِ `blocker_type`.
 *
 * ◆ يُشغَّل ضمنَ تسلسلِ التراجعِ الكاملِ الموثَّقِ في `W00_CLOSURE.md`، ولا
 *   يُشغَّل منفردًا: البوّابةُ `G0-11` تقرأ العمودَ فتسقط بعد نزعِه — وهذا
 *   مقصود، فالتراجعُ يُتبَع بإرجاعِ الأدواتِ وإعادةِ التوليد.
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

$has = $conn->query("SHOW COLUMNS FROM `repair01_decisions` LIKE 'blocker_type'");
if (!$has || $has->num_rows === 0) { exit("= blocker_type منزوعٌ سلفًا\n"); }
if ($conn->query("ALTER TABLE `repair01_decisions` DROP KEY `k_btype`, DROP COLUMN `blocker_type`") === false) {
    exit("✘ {$conn->error}\n");
}
echo "✔ blocker_type نُزع — أتبِعْه بإرجاعِ الأدواتِ وإعادةِ التوليد\n";

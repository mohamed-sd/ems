<?php
/**
 * 2027_11_20_repair01_w3_label_keys_down.php — نزعُ أعمدةِ المفتاحِ المضافة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ النزعُ يعيد الجداولَ الثلاثةَ إلى **التعريفِ بالنصّ** — وهو الحالُ الذي
 *   قاسته البوّابةُ معرّفًا بديلًا. فلا يُشغَّل إلّا في تراجعٍ كامل.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$err = 0;
foreach (repair01_w3_label_repairs() as $r) {
    $t = $r['table']; $c = $r['key_col'];
    $exists = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    if (!$exists || $exists->num_rows === 0) { echo "  ⟳ $t.$c منزوعٌ سلفًا\n"; continue; }
    if ($conn->query("ALTER TABLE `$t` DROP COLUMN `$c`") === true) { echo "  ✔ نُزع $t.$c\n"; }
    else { echo "  ✘ $t.$c — " . $conn->error . "\n"; $err++; }
}
echo ($err === 0 ? "الحكم: رجعت ✔\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);

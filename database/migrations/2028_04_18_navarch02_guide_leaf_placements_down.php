<?php
/**
 * 2028_04_18_navarch02_guide_leaf_placements_down.php — عكسُ مواضعِ ورقةِ الدليل
 * ◆ يحذف **ما أنشأته الهجرةُ وحدَها** — بشهادةِ `created_by` المطبوعةِ في الصفِّ
 *   نفسِه — ولا يمسُّ موضعًا من مخرَجِ `classify.php`.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
 * ⚠ **وإن أُعيد تشغيلُ `tools/navarch/classify.php` بعدَ الهجرة** فإنّه يمسح
 *   السجلَّ ويعيد بناءَه بمُنشِئِه هو، **فتصير هذه الصفوفُ من إنتاجِ الأداة** —
 *   وحينَها لا يجد هذا العكسُ صفًّا، ويُعلِنُه صراحةً بدل أن يصمت.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');

$CB = 'migrations/2028_04_18_navarch02_guide_leaf_placements.php';
$st = $conn->prepare("DELETE FROM nav_workspace_placements WHERE created_by = ?");
$st->bind_param('s', $CB);
$st->execute();
echo "- مواضعُ الهجرة: " . $conn->affected_rows . "\n";
$st->close();

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_04_18_navarch02_guide_leaf_placements.php'");
echo "- قيدُ الدفتر: " . $conn->affected_rows . "\n";

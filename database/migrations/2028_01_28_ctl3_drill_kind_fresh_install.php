<?php
/**
 * 2028_01_28_ctl3_drill_kind_fresh_install.php — مفردةُ تمرينِ التثبيتِ من الصفر
 * ◆ سجلُّ التمارين yحصر أنواعَه (pitr/full_restore/failover) — وتمرينُ
 *   «تثبيتٍ من الصفر» (RPR-03 ⑬) جنسٌ رابعٌ يُقيَّد في السجلِّ نفسِه
 *   لا في ملفٍّ جانبيٍّ يفترق (قاعدةُ القارئِ الواحد).
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
$ok = $conn->query("ALTER TABLE `dr_drills`
    MODIFY `drill_kind` ENUM('pitr','full_restore','failover','fresh_install') NOT NULL");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "  ✔ أُضيفت مفردةُ fresh_install لسجلِّ التمارين\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ تمّ\n";

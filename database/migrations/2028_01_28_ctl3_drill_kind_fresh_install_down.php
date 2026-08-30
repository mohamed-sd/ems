<?php
/**
 * 2028_01_28_ctl3_drill_kind_fresh_install_down.php — التراجع: نزعُ المفردة
 * ◆ تُحذف صفوفُ الجنسِ الرابعِ أولًا (وهي قيودُ تمرينٍ لا حقائقُ أعمال)
 *   ثم يُضيَّق الحصرُ لمفرداتِه الثلاث.
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
$conn->query("DELETE FROM `dr_drills` WHERE drill_kind = 'fresh_install'");
printf("  ✔ حُذفت %d قيودَ تمرينِ تثبيت\n", $conn->affected_rows);
$ok = $conn->query("ALTER TABLE `dr_drills`
    MODIFY `drill_kind` ENUM('pitr','full_restore','failover') NOT NULL");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "✔ ضُيِّق الحصرُ لمفرداتِه الثلاث\n";

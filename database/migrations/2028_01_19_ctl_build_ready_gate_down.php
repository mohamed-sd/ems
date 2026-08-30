<?php
/**
 * 2028_01_19_ctl_build_ready_gate_down.php — تراجعُ موضعِ بوّابةِ الجاهزيّة
 * ⛔ الجدولُ مشتقٌّ كلُّه من الدفترِ والكونِ — إسقاطُه لا يفقد حقيقةً أصليّة.
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
if (!$conn->query("DROP TABLE IF EXISTS `repair01_build_ready`")) { exit("✘ {$conn->error}\n"); }
echo "  ✔ أُسقط `repair01_build_ready` — ويُعاد بناؤه بإعادةِ الهجرةِ والأداة\n";

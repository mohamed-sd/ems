<?php
/**
 * هبوطُ 2027_12_05 — يُسقط سجلَّ أصنافِ الدَّين.
 * ⛔ **وإسقاطُه يفقد التصنيفَ لا الدَّينَ نفسَه** — والدَّينُ يبقى مقيسًا في
 *   `repair01_edc_scan.php`، لكنّه يعود **غيرَ مُدارٍ** بنصِّ حكمِ المالك.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
echo $conn->query("DROP TABLE IF EXISTS `repair01_debt_register`") ? "✔ أُسقط\n" : "✘ " . $conn->error . "\n";

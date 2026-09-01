<?php
/**
 * 2028_04_19_gov_guide_lists_down.php — عكسُ سجلِّ القوائمِ المحكومة
 * ◆ يُسقط `gov_guide_lists` — والجدولُ أنشأته هذه الهجرةُ وحدَها، ولا قارئَ
 *   له غيرُ `includes/w14_guide_form.php` الذي يعمل بدونِه (‏يهبط إلى مفرداتِ
 *   `ENUM` من المخطَّطِ ثمَّ إلى خانةِ نصّ).
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
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

$c = $conn->query('SELECT COUNT(*) c FROM `gov_guide_lists`');
$n = $c ? (int) $c->fetch_assoc()['c'] : 0;
$conn->query('DROP TABLE IF EXISTS `gov_guide_lists`');
echo "- أُسقط gov_guide_lists (كان فيه {$n} قيمةً)\n";

$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_04_19_gov_guide_lists.php'");
echo '- قيدُ الدفتر: ' . $conn->affected_rows . "\n";

<?php
/**
 * 2028_01_25_ctl2_build_ready_history.php — تاريخُ بوّابةِ الجاهزيّة
 * ◆ عطبٌ قيس في مصالحةِ «الثامنة»: البوّابةُ تُكتب فوقيًّا فلا يُجاب
 *   «مَن كان جاهزًا عند لقطةِ كذا؟» بدليلٍ — فيُلحَق سجلُّ تاريخٍ
 *   تُصَبُّ فيه نسخةُ كلِّ تمريرةٍ بلقطتِها، قراءةً للأثرِ لا حكمًا.
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_build_ready_history` (
  `hid`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gated_run`   DATETIME NOT NULL,
  `snapshot_id` VARCHAR(48) NOT NULL,
  `target_uid`  VARCHAR(16) NOT NULL,
  `requirement_id` VARCHAR(40) NOT NULL DEFAULT '',
  `build_ready` VARCHAR(8) NOT NULL,
  `build_blocker` VARCHAR(400) NOT NULL DEFAULT '',
  PRIMARY KEY (`hid`),
  KEY `ix_brh_run` (`gated_run`),
  KEY `ix_brh_tgt` (`target_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تاريخ تمريرات بوابة الجاهزية — نسخة كل تمريرة بلقطتها، قراءة اثر لا حكم'");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "  ✔ `repair01_build_ready_history` جاهز\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ سجلُّ تاريخِ البوّابةِ مفتوح\n";

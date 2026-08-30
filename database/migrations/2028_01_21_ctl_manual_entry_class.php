<?php
/**
 * 2028_01_21_ctl_manual_entry_class.php — سجلُّ تصنيفِ القيودِ اليدويّة
 * ◆ أمرُ الضبطِ §٩: 1,644 = مجموعُ الفئاتِ والفرقُ صفر — ⛔ ولا يُرفع للمالكِ
 *   صفٌّ صفًّا؛ يُرفع الغامضُ بعد القواعدِ بفئتِه وعددِها.
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_manual_entry_class` (
  `entry_id`   INT(11) NOT NULL COMMENT 'fin_journal_entries.id',
  `category`   VARCHAR(32) NOT NULL COMMENT 'فئة القسمة الاولية — التقاطع صفر والاتحاد 1644',
  `era`        ENUM('PRE_LEDGER','CURRENT') NOT NULL COMMENT 'قبل 2026 لا فترة له — بعده له فترة',
  `doc_hint`   VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'مرجع مستند منتزع من memo ان وجد (CLM-…)',
  `rule_applied` VARCHAR(32) NOT NULL,
  `witness`    VARCHAR(300) NOT NULL,
  `snapshot_id` VARCHAR(48) NOT NULL,
  `classed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  KEY `ix_mec_cat` (`category`, `era`),
  CONSTRAINT `chk_mec_witness` CHECK (`witness` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 9 · مصالحة القيود اليدوية — مجموع الفئات = 1644 بفارق صفر'");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "  ✔ `repair01_manual_entry_class` جاهز\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ المصالحةِ مفتوح\n";

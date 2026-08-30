<?php
/**
 * 2028_01_23_ctl_evidence_closure.php — موضعُ مسارِ الإغلاقِ بالدليل
 * ◆ أمرُ الضبطِ §٥: الموجودُ لا يُعاد بناؤه — يُغلق بعقدِ الإثباتِ المناسبِ
 *   لنوعِه، و`EVIDENCE_CLOSED` يبدأ بالارتفاعِ بالتوازي مع البناء.
 * ◆ وكلُّ إغلاقٍ يُدوَّن هنا بحالتِه السابقةِ وفحوصِه — **وهو مصدرُ التراجع**.
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_evidence_closure` (
  `requirement_id` VARCHAR(40) NOT NULL,
  `screen_id`      VARCHAR(12) NOT NULL DEFAULT '',
  `req_type`       VARCHAR(24) NOT NULL DEFAULT '',
  `before_state`   VARCHAR(40) NOT NULL COMMENT 'حالة amd01_state قبل الاغلاق — ومفتاح الرد',
  `checks_passed`  VARCHAR(200) NOT NULL COMMENT 'E1..E4 التي اجتيزت باسمها',
  `render_proof`   VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'len+sha من مجس التصيير الفعلي',
  `witness`        VARCHAR(600) NOT NULL,
  `snapshot_id`    VARCHAR(48) NOT NULL,
  `closed_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`requirement_id`),
  CONSTRAINT `chk_ec_witness` CHECK (`witness` <> ''),
  CONSTRAINT `chk_ec_checks`  CHECK (`checks_passed` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 5 · مسار الاغلاق بالدليل — سجل كل اغلاق بفحوصه وهو مصدر التراجع'");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "  ✔ `repair01_evidence_closure` جاهز\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ مسارُ الإغلاقِ بالدليلِ مفتوح\n";

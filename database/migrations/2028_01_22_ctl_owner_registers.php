<?php
/**
 * 2028_01_22_ctl_owner_registers.php — سجلّا قراراتِ المالكِ وملكيّةِ المنصّة
 * ◆ أمرُ الضبطِ §١٠: لا يُرفع بندٌ قبل تصنيفِه، ولكلِّ بندٍ قرارُه وما يحجبه
 *   وبوّابتُه وخياراتُه وأثرُه وتوصيةُ المنفِّذِ وحالتُه — ⛔ ولا يتحوّل السجلُّ
 *   طابورَ تنظيفِ بيانات · §١١: ما يحتاج ملكيّةً تقنيّةً فقط يُفصل في سجلِّ
 *   المنصّةِ ولا يُغلق سطحٌ دون تبريرٍ صحيحٍ ومالكٍ مسمًّى.
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_owner_actions` (
  `action_key`    VARCHAR(40) NOT NULL,
  `class`         ENUM('BUSINESS_DECISION','POLICY_DECISION','CONFIG_VALUE','TECHNICAL_DECISION','UAT_INPUT') NOT NULL,
  `decision`      VARCHAR(300) NOT NULL COMMENT 'القرار المحدد المطلوب',
  `blocks`        VARCHAR(300) NOT NULL COMMENT 'ما الذي يحجبه — بعدده',
  `required_by`   VARCHAR(120) NOT NULL COMMENT 'Required-by-Gate',
  `options`       VARCHAR(400) NOT NULL,
  `impact`        VARCHAR(300) NOT NULL,
  `recommendation` VARCHAR(300) NOT NULL COMMENT 'توصية المنفذ',
  `status`        ENUM('PENDING','DECIDED','WITHDRAWN') NOT NULL DEFAULT 'PENDING',
  `decided_ref`   VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'مرجع القرار حين يصدر',
  `decided_at`    DATETIME NULL COMMENT 'يكتب فقط اذا حدده المالك او فرضته سياسة',
  `snapshot_id`   VARCHAR(48) NOT NULL DEFAULT '',
  `raised_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`action_key`),
  KEY `ix_oa_status` (`status`, `class`),
  CONSTRAINT `chk_oa_decision` CHECK (`decision` <> '' AND `blocks` <> '' AND `options` <> '' AND `recommendation` <> ''),
  CONSTRAINT `chk_oa_decided`  CHECK (`status` <> 'DECIDED' OR `decided_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 10 · OWNER_ACTION_REGISTER — قرار حقيقي مصنف لا طابور تنظيف'");
if (!$ok) { exit("✘ owner_actions: {$conn->error}\n"); }

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_platform_ownership` (
  `screen_id`     VARCHAR(12) NOT NULL,
  `label_ar`      VARCHAR(190) NOT NULL DEFAULT '',
  `justify_state` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'من rpr02_platform_justify — J1/J2/J3',
  `criteria_met`  VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'اي الاربعة استوفى — مثل 12-4',
  `tech_owner`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'شخص مسمى — فارغه هو الحاجز',
  `status`        ENUM('AWAITING_OWNER_NAME','JUSTIFIED','RETURNED_TO_SCOPE') NOT NULL DEFAULT 'AWAITING_OWNER_NAME',
  `witness`       VARCHAR(400) NOT NULL,
  `snapshot_id`   VARCHAR(48) NOT NULL DEFAULT '',
  PRIMARY KEY (`screen_id`),
  CONSTRAINT `chk_po_witness` CHECK (`witness` <> ''),
  CONSTRAINT `chk_po_justified` CHECK (`status` <> 'JUSTIFIED' OR `tech_owner` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 11 · PLATFORM_SHARED_OWNERSHIP_REGISTER — لا اغلاق بلا مالك تقني مسمى'");
if (!$ok) { exit("✘ platform: {$conn->error}\n"); }

echo "  ✔ `repair01_owner_actions` و`repair01_platform_ownership` جاهزان\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ سجلّا المالكِ مفتوحان\n";

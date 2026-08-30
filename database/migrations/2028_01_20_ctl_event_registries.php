<?php
/**
 * 2028_01_20_ctl_event_registries.php — سجلّا الأحداثِ الموحَّدُ والمتراكمِ التاريخيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **أمرُ الضبطِ §٧**: `EVENT_EFFECT_CROSSWALK` — صفٌّ لكلِّ نوعِ حدثٍ يجمع
 *   المنتِجَ والتصنيفَ والاشتراكَ والمستهلكَ وعقدَ الأثرِ والتقدُّمَ والمتراكمَ
 *   — ⛔ **ولا تُخلط مقاماتُ الأنواعِ والاشتراكاتِ والمستهلكين في رقمٍ واحد**.
 * ◆ **§٨**: `BACKLOG_DISPOSITION_REGISTER` — تصريفُ المتراكمِ التاريخيِّ يبدأ
 *   بتصنيفٍ لا بتشغيل: ⛔ **صفرُ Replay في جولةِ الضبط**، والدفعاتُ Canary
 *   بعد النقطةِ الثانيةِ حصرًا.
 * التشغيل: php database/migrations/2028_01_20_ctl_event_registries.php
 * ═══════════════════════════════════════════════════════════════════════════
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_event_effect_crosswalk` (
  `event_key`         VARCHAR(120) NOT NULL,
  `classification`    VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'BUSINESS/AUDIT/RETIRED/NEEDS_ADJUDICATION',
  `occurrences`       INT(11) NOT NULL DEFAULT 0,
  `needs_effect`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'حدث اعمال يستلزم اثرا',
  `subscriptions`     INT(11) NOT NULL DEFAULT 0 COMMENT 'اشتراكات فعالة',
  `effect_consumers`  INT(11) NOT NULL DEFAULT 0 COMMENT 'مستهلكو اثر write غير حارس',
  `watch_consumers`   INT(11) NOT NULL DEFAULT 0 COMMENT 'حراس notify',
  `contracts_full`    INT(11) NOT NULL DEFAULT 0 COMMENT 'اشتراكات بعقد اثر كامل الخمسة',
  `last_progress`     DATETIME NULL COMMENT 'اخر تسليم ناجح لهذا النوع',
  `backlog`           INT(11) NOT NULL DEFAULT 0 COMMENT 'وقائع خلف ابعد مؤشر مستهلك',
  `final_status`      VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'EFFECT_COVERED/GUARD_ONLY/AUDIT_ONLY/RETIRED/NEEDS_ADJUDICATION',
  `witness`           VARCHAR(500) NOT NULL,
  `snapshot_id`       VARCHAR(48) NOT NULL,
  `built_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_key`),
  KEY `ix_eec_status` (`final_status`),
  CONSTRAINT `chk_eec_witness` CHECK (`witness` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 7 · السجل الموحد: نوع الحدث ومنتجه واشتراكه وعقده ومتراكمه — مقامات مفصولة'");
if (!$ok) { exit("✘ crosswalk: {$conn->error}\n"); }

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_backlog_disposition` (
  `consumer_key`   VARCHAR(64) NOT NULL,
  `event_key`      VARCHAR(120) NOT NULL,
  `backlog_count`  INT(11) NOT NULL DEFAULT 0,
  `disposition`    ENUM('REPLAY_REQUIRED','EFFECT_ALREADY_REALIZED','AUDIT_ONLY','SUPERSEDED',
                        'MANUAL_RECONCILIATION','CLOSE_WITH_REASON') NOT NULL,
  `rule_applied`   VARCHAR(64) NOT NULL COMMENT 'اي قاعدة انتجت الحكم',
  `witness`        VARCHAR(500) NOT NULL,
  `watermark_id`   BIGINT NULL COMMENT 'اخر حدث مشمول بالحكم — نقطة الماء للدفعات',
  `replayed`       INT(11) NOT NULL DEFAULT 0 COMMENT 'ما صرف فعلا — صفر طوال جولة الضبط',
  `snapshot_id`    VARCHAR(48) NOT NULL,
  `ruled_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumer_key`, `event_key`),
  KEY `ix_bd_disp` (`disposition`),
  CONSTRAINT `chk_bd_witness` CHECK (`witness` <> ''),
  CONSTRAINT `chk_bd_rule`    CHECK (`rule_applied` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 8 · تصريف المتراكم التاريخي — تصنيف قبل تشغيل وصفر Replay في جولة الضبط'");
if (!$ok) { exit("✘ backlog: {$conn->error}\n"); }

echo "  ✔ `repair01_event_effect_crosswalk` و`repair01_backlog_disposition` جاهزان\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ سجلّا الأحداثِ مفتوحان\n";

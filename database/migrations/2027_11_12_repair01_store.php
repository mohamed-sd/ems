<?php
/**
 * 2027_11_12_repair01_store.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 §المرحلة صفر — مخزنُ الحقيقةِ الواحد.
 *
 * ◆ **لماذا مخزنٌ لا مصنَّفات**: حزمةُ الدراسةِ ١٣ ملفًّا و١٥١ ورقةً و٣١٬٣١٩
 *   صفًّا. وشيتُ `05_سجل_القرارات` كتب `PENDING` في صفوفِه الأربعةِ والتسعين
 *   كلِّها بينما `OWNER_DECISIONS_MASTER` على بُعدِ أربعةِ شيتاتٍ يقول
 *   `APPROVED` في ٧٧ منها — لأنّ المصنَّفَ الثنائيَّ لا يُقارَن آليًّا بأصلِه.
 *   فالإكسلُ صيغةُ دخولٍ لا صيغةُ عمل: يُستوعَب مرّةً، ويصير كلُّ ما بعده
 *   إسقاطًا مولَّدًا.
 *
 * ◆ **والمنشأُ يُحفظ مع كلِّ صفّ**: `src_ref` يحمل «الملفّ › الورقة › الصفّ»
 *   حرفًا — فكلُّ رقمٍ في أيِّ تقريرٍ لاحقٍ يعود إلى خليّتِه في المصنَّف.
 *
 * ◆ **وترقيمُ الإدارات لا يمسُّ المفاتيحَ التاريخية** (قرارُ المالك
 *   DEC-OPEN-18): ثلاثةُ محاورَ منفصلة — المعرّفُ التقنيُّ الثابت، والرمزُ
 *   المعياريُّ `DEP-01..17`، وترتيبُ العرض — يجسرها `repair01_dept_crosswalk`
 *   بلا `UPDATE` مدمِّرٍ على مفتاحٍ أجنبيٍّ أو سجلِّ تدقيقٍ أو حدث.
 * ═══════════════════════════════════════════════════════════════════════════
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

$ddl = array();

/* ① المنشأ — الملفّاتُ المُجمَّدة */
$ddl['repair01_source_files'] = "
CREATE TABLE IF NOT EXISTS `repair01_source_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_no` VARCHAR(8) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `bytes` INT UNSIGNED NOT NULL,
  `sheet_count` SMALLINT UNSIGNED NOT NULL,
  `data_rows` INT UNSIGNED NOT NULL,
  `frozen_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_file` (`file_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ② القرارات — المصدرُ الحاكمُ الوحيد */
$ddl['repair01_decisions'] = "
CREATE TABLE IF NOT EXISTS `repair01_decisions` (
  `decision_id` VARCHAR(32) NOT NULL,
  `domain` VARCHAR(120) NOT NULL DEFAULT '',
  `question` TEXT NULL,
  `current_state` TEXT NULL,
  `options` TEXT NULL,
  `recommended` TEXT NULL,
  `owner_decision` TEXT NULL,
  `status` ENUM('APPROVED','NEEDS_OWNER_DECISION') NOT NULL DEFAULT 'NEEDS_OWNER_DECISION',
  `blocking_level` ENUM('STRUCTURAL_TARGET_BLOCKER','READY_TO_BUILD_BLOCKER','UAT_BLOCKER','GO_LIVE_BLOCKER','CONFIG_PENDING','NONE') NOT NULL DEFAULT 'NONE',
  `blocking_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `affected_documents` TEXT NULL,
  `affected_screens` TEXT NULL,
  `affected_rules` TEXT NULL,
  `migration_impact` TEXT NULL,
  `code_impact` TEXT NULL,
  `evidence` TEXT NULL,
  `approved_by` VARCHAR(120) NOT NULL DEFAULT '',
  `approved_at` VARCHAR(40) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`),
  KEY `k_status` (`status`), KEY `k_block` (`blocking_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ③ الإدارات — الرمزُ المعياريّ (DEC-OPEN-18) */
$ddl['repair01_departments'] = "
CREATE TABLE IF NOT EXISTS `repair01_departments` (
  `canonical_code` VARCHAR(12) NOT NULL,
  `display_order` SMALLINT UNSIGNED NULL,
  `name_ar` VARCHAR(160) NOT NULL,
  `sector` ENUM('CORPORATE','OPERATIONAL','OUTSIDE') NOT NULL,
  `parent_code` VARCHAR(12) NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`canonical_code`), KEY `k_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ④ الجسرُ إلى المسمّياتِ القديمة — بلا مساسٍ بالمفاتيح */
$ddl['repair01_dept_crosswalk'] = "
CREATE TABLE IF NOT EXISTS `repair01_dept_crosswalk` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_name` VARCHAR(160) NOT NULL,
  `canonical_code` VARCHAR(12) NOT NULL,
  `verdict` ENUM('MAP','SPLIT','RECLASSIFY','NEW') NOT NULL,
  `split_rule` VARCHAR(255) NOT NULL DEFAULT '',
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), KEY `k_legacy` (`legacy_name`), KEY `k_canon` (`canonical_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑤ الأسطحُ المبنيّة — مع علَمِ الشبح */
$ddl['repair01_surfaces'] = "
CREATE TABLE IF NOT EXISTS `repair01_surfaces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `screen_file` VARCHAR(160) NOT NULL,
  `dept_legacy` VARCHAR(160) NOT NULL DEFAULT '',
  `canonical_code` VARCHAR(12) NULL,
  `screen_title` VARCHAR(255) NOT NULL DEFAULT '',
  `layer_name` VARCHAR(60) NOT NULL DEFAULT '',
  `stage_order` VARCHAR(10) NOT NULL DEFAULT '',
  `stage_name` VARCHAR(160) NOT NULL DEFAULT '',
  `group_name` VARCHAR(160) NOT NULL DEFAULT '',
  `output_doc` VARCHAR(255) NOT NULL DEFAULT '',
  `resp_role` VARCHAR(160) NOT NULL DEFAULT '',
  `next_state` VARCHAR(255) NOT NULL DEFAULT '',
  `stage_kind` VARCHAR(20) NOT NULL DEFAULT '',
  `on_disk` TINYINT(1) NOT NULL DEFAULT 0,
  `disk_path` VARCHAR(255) NOT NULL DEFAULT '',
  `recon_verdict` VARCHAR(60) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `k_file` (`screen_file`), KEY `k_disk` (`on_disk`), KEY `k_dept` (`dept_legacy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑥ الأسطحُ المستهدفةُ غيرُ المبنيّة */
$ddl['repair01_target_gaps'] = "
CREATE TABLE IF NOT EXISTS `repair01_target_gaps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit` VARCHAR(160) NOT NULL DEFAULT '',
  `surface_name` VARCHAR(255) NOT NULL,
  `built_counterpart` VARCHAR(255) NOT NULL DEFAULT '',
  `verdict` VARCHAR(120) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), KEY `k_unit` (`unit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑦ المتطلَّبات */
$ddl['repair01_requirements'] = "
CREATE TABLE IF NOT EXISTS `repair01_requirements` (
  `requirement_id` VARCHAR(48) NOT NULL,
  `wave` VARCHAR(8) NOT NULL DEFAULT '',
  `unit` VARCHAR(160) NOT NULL DEFAULT '',
  `dependency` VARCHAR(160) NOT NULL DEFAULT '',
  `seq` VARCHAR(12) NOT NULL DEFAULT '',
  `group_name` VARCHAR(160) NOT NULL DEFAULT '',
  `surface` VARCHAR(255) NOT NULL DEFAULT '',
  `grain` VARCHAR(255) NOT NULL DEFAULT '',
  `source_of_truth` VARCHAR(255) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`), KEY `k_wave` (`wave`), KEY `k_unit` (`unit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑧ الحقول */
$ddl['repair01_fields'] = "
CREATE TABLE IF NOT EXISTS `repair01_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requirement_id` VARCHAR(48) NOT NULL DEFAULT '',
  `wave` VARCHAR(8) NOT NULL DEFAULT '',
  `unit` VARCHAR(160) NOT NULL DEFAULT '',
  `surface` VARCHAR(255) NOT NULL DEFAULT '',
  `seq` VARCHAR(12) NOT NULL DEFAULT '',
  `field_name` VARCHAR(255) NOT NULL DEFAULT '',
  `field_type` VARCHAR(80) NOT NULL DEFAULT '',
  `visibility_rule` VARCHAR(255) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), KEY `k_req` (`requirement_id`), KEY `k_wave` (`wave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑨ الأحداث */
$ddl['repair01_events'] = "
CREATE TABLE IF NOT EXISTS `repair01_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_code` VARCHAR(80) NOT NULL,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `wave` VARCHAR(8) NOT NULL DEFAULT '',
  `source_unit` VARCHAR(160) NOT NULL DEFAULT '',
  `source_screen` VARCHAR(255) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(255) NOT NULL DEFAULT '',
  `consumers` TEXT NULL,
  `effect_type` VARCHAR(160) NOT NULL DEFAULT '',
  `retry_policy` VARCHAR(80) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), KEY `k_code` (`event_code`), KEY `k_wave` (`wave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑩ الملكيّةُ والتداخل */
$ddl['repair01_ownership'] = "
CREATE TABLE IF NOT EXISTS `repair01_ownership` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `space_role` VARCHAR(160) NOT NULL DEFAULT '',
  `screen` VARCHAR(255) NOT NULL DEFAULT '',
  `route` VARCHAR(255) NOT NULL DEFAULT '',
  `owner_dept` VARCHAR(160) NOT NULL DEFAULT '',
  `classification` VARCHAR(60) NOT NULL DEFAULT '',
  `ownership_kind` VARCHAR(80) NOT NULL DEFAULT '',
  `space_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `gov_meaning` VARCHAR(255) NOT NULL DEFAULT '',
  `src_ref` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `k_cls` (`classification`), KEY `k_screen` (`screen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$made = 0; $had = 0;
foreach ($ddl as $t => $sql) {
    $exists = $conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0;
    if ($conn->query($sql) === false) { echo "✘ $t : {$conn->error}\n"; continue; }
    if ($exists) { $had++; echo "= $t (قائم)\n"; } else { $made++; echo "✔ $t\n"; }
}
echo "\nأُنشئ: $made · قائمٌ سلفًا: $had\n";

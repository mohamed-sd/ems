-- ═══════════════════════════════════════════════════════════════════════════
-- M-41 · الدورياتُ الثلاثُ الباقية بمفاتيحها — 2026-07-31
-- البطاقة: docs/specs/M-41_periodic_events.md
-- المصدر: SPEC-01 #23 «المخصصُ حدثٌ دوريٌّ آليٌّ بمفتاح (المعدة × الفترة) ·
--         لا كتابةَ يدويةً على الدفتر» · #30 «القسطُ المستحق حدثٌ آليٌّ بمفتاح
--         (الالتزام × القسط)» · #22 «قيدُ الإقرار حدثٌ دوريٌّ بمفتاح الفترة».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   ① مخصصُ الصيانة: «جدولُ الإعداد القائم» الذي تفترضه الوثيقةُ **غيرُ موجودٍ
--      إطلاقًا** (ثمانيةُ جداولِ قواعدَ في القاعدة ليس فيها مخصصُ صيانة).
--   ② قسطُ التمويل: `fin_funding_schedules` **قائمٌ بـ57 قسطًا** — لكن **صفرَ
--      مفتاحٍ فريدٍ** على (الالتزام × القسط) ولا عمودَ حدثٍ ولا حدثَ منشور.
--   ③ الإقرار: `fin_tax_transactions` حركاتٌ (صفّان) **بلا كيان إقرارٍ** بمفتاح
--      الفترة، ولا مفتاحَ فريدًا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① قاعدةُ مخصص الصيانة — أعمدةُ #23 نصًّا ────────────────────────────────
CREATE TABLE IF NOT EXISTS `fin_maint_provision_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'معدةٌ بعينها — NULL = القاعدةُ لنوعها أو الأعمّ',
  `equipment_type` INT UNSIGNED NULL DEFAULT NULL COMMENT 'نوعُ المعدة — NULL مع NULL أعلاه = الأعمّ',
  `basis` ENUM('hour','unit') NOT NULL COMMENT '«أساسُ المخصص (ساعة/وحدة)» — نصُّ #23',
  `rate` DECIMAL(14,4) NOT NULL COMMENT 'معدلُ المخصص لوحدة الأساس',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG' COMMENT 'لا جمعَ عملتين في رقم',
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `note` VARCHAR(200) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mprov_rule` (`company_id`, `equipment_id`, `equipment_type`, `basis`, `effective_from`),
  KEY `ix_mprov_rule_lookup` (`company_id`, `state`, `effective_from`, `effective_to`),
  CONSTRAINT `ck_mprov_rate` CHECK (`rate` > 0),
  CONSTRAINT `ck_mprov_span` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SPEC-01 #23 — قاعدةُ مخصص الصيانة: الأساسُ والمعدلُ والسريان';

-- ── ② سجلُّ المخصص — «بمفتاح (المعدة × الفترة)» ────────────────────────────
CREATE TABLE IF NOT EXISTS `fin_maint_provisions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT UNSIGNED NOT NULL,
  `period_ref` VARCHAR(10) NOT NULL COMMENT 'YYYY-MM',
  `rule_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'القاعدةُ التي احتُسب بها — «لا كتابةَ يدويةً»',
  `basis` ENUM('hour','unit') NOT NULL,
  `qty` DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'من **وحدات المعدة المعتمدة** في الفترة',
  `rate` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '**محسوبٌ لا مُدخَل**: الكميةُ × المعدل',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `event_id` INT NULL DEFAULT NULL,
  `basis_json` TEXT NULL DEFAULT NULL,
  `source` ENUM('screen','cron') NOT NULL DEFAULT 'screen',
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_maint_provision` (`company_id`, `equipment_id`, `period_ref`)
      COMMENT '«بمفتاح (المعدة × الفترة)» بنيويًّا',
  KEY `ix_mprov_period` (`company_id`, `period_ref`),
  KEY `ix_mprov_event` (`event_id`),
  CONSTRAINT `ck_mprov_amount` CHECK (`amount` >= 0),
  -- «لا كتابةَ يدويةً على الدفتر»: مبلغٌ بلا قاعدةٍ مستحيل
  CONSTRAINT `ck_mprov_rule_src` CHECK (`amount` = 0 OR `rule_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ قسطُ التمويل — المفتاحُ الغائبُ والأثرُ الغائب ───────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_funding_schedules'
                  AND COLUMN_NAME = 'event_id'),
    'ALTER TABLE `fin_funding_schedules`
       ADD COLUMN `event_id` INT NULL DEFAULT NULL
           COMMENT ''حدثُ استحقاق القسط — «أقساطٌ آليةٌ بمرجع الجدول لحظةَ استحقاقها»'' AFTER `state`,
       ADD COLUMN `accrued_at` DATETIME NULL DEFAULT NULL
           COMMENT ''لحظةُ الاعتراف بالاستحقاق — لا تُكتب إلا مع الحدث'' AFTER `event_id`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- «بمفتاح (الالتزام × القسط)» — بنيويًّا لا بفحصٍ يُنسى
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_funding_schedules'
                  AND INDEX_NAME = 'uq_funding_installment'),
    'ALTER TABLE `fin_funding_schedules`
       ADD UNIQUE KEY `uq_funding_installment` (`company_id`, `facility_id`, `installment_no`),
       ADD KEY `ix_funding_due` (`company_id`, `due_date`, `state`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ④ الإقرارُ الضريبي — «بمفتاح الفترة» ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `fin_tax_returns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `period_ref` VARCHAR(10) NOT NULL COMMENT 'YYYY-MM',
  `taxable_sales` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'المبيعاتُ الخاضعة (وعاءُ المخرجات)',
  `output_tax` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'ضريبةُ المخرجات',
  `taxable_purchases` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'المشتريات (وعاءُ المدخلات)',
  `input_tax` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'ضريبةُ المدخلات',
  `net_tax` DECIMAL(18,2) GENERATED ALWAYS AS (ROUND(`output_tax` - `input_tax`, 2)) STORED
      COMMENT '«الصافي» — **عمودٌ مولَّدٌ لا يُكتب** فلا ينحرف عن طرفيه',
  `lines_count` INT NOT NULL DEFAULT 0 COMMENT 'عددُ الحركات المشتقّ منها — الصفرُ يُعلَن ولا يُخفى',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `state` ENUM('draft','filed') NOT NULL DEFAULT 'draft',
  `event_id` INT NULL DEFAULT NULL,
  `basis_json` TEXT NULL DEFAULT NULL,
  `filed_at` DATETIME NULL DEFAULT NULL,
  `filed_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_return` (`company_id`, `period_ref`) COMMENT '«بمفتاح الفترة»',
  KEY `ix_tax_return_state` (`company_id`, `state`),
  CONSTRAINT `ck_taxret_filed` CHECK (`state` <> 'filed' OR `filed_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SPEC-01 #22 — الإقرارُ الضريبيُّ الدوريُّ بمفتاح الفترة';

-- ── ⑤ تسجيلُ شاشة «الدوريات المالية» — الوحدة 169 ──────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 169, 'الدوريات المالية', 'Finance/periodic_events_fin.php', 17, 0, 0, 'fa fa-repeat', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Finance/periodic_events_fin.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 169, 1, r.a, r.e, 0
  FROM (SELECT 17 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 18, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 169);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 169, 'الدوريات المالية', 'Finance/periodic_events_fin.php',
       'fa fa-repeat', 68, NULL, 'Finance/periodic_events_fin.php', 1
  FROM (SELECT 17 AS rid UNION ALL SELECT 19 UNION ALL SELECT 18) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Finance/periodic_events_fin.php');

-- ═══════════════════════════════════════════════════════════════════════════
-- M-52 · أمرُ الترحيل بتعرفته مصدرًا لتحميل النقل — 2026-07-31
-- البطاقة: docs/specs/M-52_transfer_tariff.md
-- المصدر: ENT-02 §3-④: «**أمرُ الترحيل المسلَّم · بتعرفته**» · و§3 الحاكمة:
--         «**لا إدخالَ حرًّا** — كلُّ تحميلٍ سطرٌ برابط مستنده؛ وما لا مستندَ
--         له لا يُحمَّل — **والمبلغُ يُقرأ من مصدره لا يُكتب**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء — وتصحيحُ ادّعاء الكتالوج:
--   الكتالوجُ يقول «لا كيانَ لأمر الترحيل» لأنه بحث ببادئة `trs_`؛ والوحدةُ
--   مبنيةٌ ببادئة `transfer_`: `transfer_orders` (11 صفًّا · دورةُ حياةٍ من سبع
--   مراحل) وستُّ شاشاتٍ حيّة (108–113).
--   فالناقصُ شيئان بالضبط: **التعرفة** (`transfer_cost_lines` يسجّل تكاليفَ
--   وقعت لا أسعارًا، و`transfer_cost_rules` يقرّر المتحمِّلَ لا السعر)، و**الوصلُ
--   بتسوية المورد** (`collectLines` بلا سطر نقلٍ · و`fin_dues.transport` صفر).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① التعرفة — «بتعرفته» بجدولها ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transfer_tariffs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'المورد المحمَّل — NULL = تعرفةٌ لا تخصُّ موردًا بعينه (الأعمّ)',
  `transfer_type_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'نوعُ الترحيل — NULL = أي نوع',
  `from_location_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'مبدأُ المسار — NULL = أي مبدأ',
  `to_location_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'منتهاه — NULL = أي منتهى',
  `pricing_model` ENUM('per_trip','per_km','per_ton','per_equipment') NOT NULL
      COMMENT 'نموذجُ التسعير — والكميةُ تُقرأ من الأمر بحسبه',
  `rate` DECIMAL(14,4) NOT NULL COMMENT 'معدلُ الوحدة — عمودٌ مستقلٌّ بدقّته (گوتشا M-15: pct(5,2) يبتر)',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG' COMMENT 'لا جمعَ عملتين في رقم',
  `min_amount` DECIMAL(18,2) NULL DEFAULT NULL,
  `max_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'سقفٌ يقصّ **ويُعلن قصَّه**',
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL DEFAULT NULL COMMENT 'NULL = مفتوحةُ الطرف',
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `note` VARCHAR(200) NULL DEFAULT NULL COMMENT 'بندُ العقد أو مرجعُ التعرفة',
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfer_tariff` (`company_id`, `supplier_id`, `transfer_type_id`,
      `from_location_id`, `to_location_id`, `pricing_model`, `effective_from`)
      COMMENT 'تعرفةٌ واحدةٌ لمفتاحها في تاريخها — والجديدُ بسريانٍ جديد',
  KEY `ix_tariff_lookup` (`company_id`, `state`, `effective_from`, `effective_to`),
  CONSTRAINT `ck_tariff_rate` CHECK (`rate` > 0),
  CONSTRAINT `ck_tariff_limits` CHECK (
      `min_amount` IS NULL OR `max_amount` IS NULL OR `min_amount` <= `max_amount`),
  CONSTRAINT `ck_tariff_span` CHECK (
      `effective_to` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ENT-02 §3-④ — تعرفةُ الترحيل: السعرُ المكتوب الذي يُحمَّل به المورد';

-- ── ② أمرُ الترحيل: على من يُحمَّل · وبأيّ تعرفةٍ سُعّر ────────────────────
-- `charge_supplier_id` **نظير `proc_issue.charge_supplier_id` و`mnt_order` حرفيًّا**
-- — النمطُ نفسُه يُثبت نفسَه ثالثةً، فلا يتعلّم القارئُ ثلاثَ طرائق لشيءٍ واحد.
-- و`cost_bearer` القائم (client·company·new_client) **يبقى كما هو** ولا يُمسّ:
-- ذاك متحمِّلُ **تكلفتنا الداخلية**، وهذا **من نحمّله تعرفتَنا** — بابان لا باب.
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'transfer_orders'
                  AND COLUMN_NAME = 'charge_supplier_id'),
    'ALTER TABLE `transfer_orders`
       ADD COLUMN `charge_supplier_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''المورد الذي يُحمَّل بتعرفة هذا الأمر (ENT-02 §3-④) — NULL = لا تحميلَ على مورد'' AFTER `cost_bearer`,
       ADD COLUMN `tariff_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''التعرفةُ التي سُعّر بها — «المبلغُ يُقرأ من مصدره»'' AFTER `charge_supplier_id`,
       ADD COLUMN `tariff_amount` DECIMAL(18,2) NULL DEFAULT NULL
           COMMENT ''**محسوبٌ لا مُدخَل**: كميةُ نموذج التعرفة × معدلها مقصوصةً بحدَّيها'' AFTER `tariff_id`,
       ADD COLUMN `tariff_currency` VARCHAR(8) NULL DEFAULT NULL AFTER `tariff_amount`,
       ADD COLUMN `tariff_note` VARCHAR(255) NULL DEFAULT NULL
           COMMENT ''بيانُ الاحتساب: النموذجُ والكميةُ والمعدل وقصُّ الحدّ إن وقع'' AFTER `tariff_currency`,
       ADD COLUMN `distance_km` DECIMAL(12,2) NULL DEFAULT NULL
           COMMENT ''مسافةُ المسار — لازمةٌ لنموذج per_km وبلا قيمةٍ لا تسعير'' AFTER `tariff_note`,
       ADD COLUMN `priced_at` DATETIME NULL DEFAULT NULL AFTER `distance_km`,
       ADD COLUMN `priced_by` INT UNSIGNED NULL DEFAULT NULL AFTER `priced_at`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'transfer_orders'
                  AND INDEX_NAME = 'ix_order_charge_supplier'),
    'ALTER TABLE `transfer_orders`
       ADD KEY `ix_order_charge_supplier` (`company_id`, `charge_supplier_id`, `stage`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- «المبلغُ يُقرأ من مصدره لا يُكتب» — بنيويًّا: مبلغٌ بلا تعرفةٍ مستحيل
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'transfer_orders'
                  AND CONSTRAINT_NAME = 'ck_order_tariff_source'),
    'ALTER TABLE `transfer_orders`
       ADD CONSTRAINT `ck_order_tariff_source`
           CHECK (`tariff_amount` IS NULL
                  OR (`tariff_id` IS NOT NULL AND `tariff_currency` IS NOT NULL))',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ③ تسجيلُ شاشة «تعرفة الترحيل» — الوحدة 168 ─────────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 168, 'تعرفة الترحيل وتسعير الأوامر', 'Transport/transfer_tariffs.php', 23, 0, 0, 'fa fa-money-bill-transfer', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Transport/transfer_tariffs.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 168, 1, r.a, r.e, 0
  FROM (SELECT 23 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 0
        UNION ALL SELECT 2, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 168);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 168, 'تعرفة الترحيل وتسعير الأوامر', 'Transport/transfer_tariffs.php',
       'fa fa-money-bill-transfer', 67, NULL, 'Transport/transfer_tariffs.php', 1
  FROM (SELECT 23 AS rid UNION ALL SELECT 17 UNION ALL SELECT 2) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Transport/transfer_tariffs.php');

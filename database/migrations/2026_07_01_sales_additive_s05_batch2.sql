-- ══════════════════════════════════════════════════════════════════════════════
-- EQUIP-OPE-S05-EMS · حزمة إضافات المبيعات الآمنة (2) — Additive only
-- 4 كيانات جديدة، أثرها صفر على الجداول القائمة. الشاشات داخل Clients/.
-- products = كتالوج خدمات المبيعات (منفصل تمامًا عن proc_item للمشتريات).
-- التنفيذ: mysql -u root --default-character-set=utf8mb4 < الملف. التراجع في النهاية.
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── 1) المنتجات/الخدمات (§3.5 · §6.7) — كتالوج بيعي مستقل (≠ proc_item)
CREATE TABLE IF NOT EXISTS `products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT NOT NULL,
  `product_code`   VARCHAR(50) NOT NULL,
  `name`           VARCHAR(200) NOT NULL,
  `product_type`   ENUM('خدمة','معدة','مادة') NOT NULL DEFAULT 'خدمة',
  `revenue_model`  ENUM('hourly','ton','meter') NULL,
  `default_uom`    VARCHAR(30) NULL,
  `standard_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `currency`       ENUM('USD','SDG') NOT NULL DEFAULT 'USD',
  `description`    TEXT NULL,
  `status`         TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`     INT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`     DATETIME NULL,
  `deleted_by`     INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prod_scope` (`company_id`,`is_deleted`),
  KEY `idx_prod_model` (`revenue_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2) وحدات القياس (§3.5) — جدول مرجعي بيعي
CREATE TABLE IF NOT EXISTS `units_of_measure` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `uom_code`   VARCHAR(30) NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `symbol`     VARCHAR(20) NULL,
  `category`   ENUM('زمن','وزن','طول','حجم','عدد') NOT NULL DEFAULT 'عدد',
  `factor`     DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  `notes`      TEXT NULL,
  `status`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uom_scope` (`company_id`,`is_deleted`),
  KEY `idx_uom_cat`   (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3) الملاحق والتجديدات (§6.9) — FK منطقي → contracts (يعيش هنا فقط)
CREATE TABLE IF NOT EXISTS `contract_amendments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT NOT NULL,
  `amendment_code`  VARCHAR(50) NOT NULL,
  `contract_id`     INT NULL,
  `amend_type`      ENUM('تجديد','تمديد','زيادة نطاق','تخفيض نطاق','تغيير أسعار','إضافة معدات','إضافة خدمات') NOT NULL DEFAULT 'تجديد',
  `amend_date`      DATE NULL,
  `requested_by`    INT NULL,
  `reason`          TEXT NULL,
  `old_value`       VARCHAR(255) NULL,
  `new_value`       VARCHAR(255) NULL,
  `effect_price`    DECIMAL(14,2) NULL,
  `effect_qty`      DECIMAL(14,2) NULL,
  `effect_duration` INT NULL,
  `effect_summary`  TEXT NULL,
  `created_by`      INT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`      DATETIME NULL,
  `deleted_by`      INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_amd_scope`    (`company_id`,`is_deleted`),
  KEY `idx_amd_contract` (`contract_id`),
  KEY `idx_amd_type`     (`amend_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4) سجل الأحداث التعاقدية (§6.14) — FK منطقي → contracts
CREATE TABLE IF NOT EXISTS `contract_events` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT NOT NULL,
  `event_code`  VARCHAR(50) NOT NULL,
  `contract_id` INT NULL,
  `event_date`  DATETIME NULL,
  `event_type`  ENUM('انخفاض إنتاج','تأخر اعتماد العميل','نقص معدات','تأخر موردين','قوة قاهرة','أمر تغيير','مطالبة إضافية','تمديد محتمل','خلاف تشغيلي','إخلال طرف') NOT NULL DEFAULT 'أمر تغيير',
  `party`       ENUM('الشركة','العميل','المورد') NULL,
  `description` TEXT NULL,
  `state`       ENUM('مفتوح','قيد المتابعة','مغلق') NOT NULL DEFAULT 'مفتوح',
  `created_by`  INT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`  DATETIME NULL,
  `deleted_by`  INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_evt_scope`    (`company_id`,`is_deleted`),
  KEY `idx_evt_contract` (`contract_id`),
  KEY `idx_evt_state`    (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسجيل الوحدات (نمط module 66) — ids 82..85 owner_role_id=12
INSERT INTO `modules` (`id`,`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`) VALUES
  (82,'المنتجات والخدمات','Clients/products.php',           12,'1','fa fa-box',10),
  (83,'وحدات القياس','Clients/units_of_measure.php',        12,'1','fa fa-ruler-combined',11),
  (84,'الملاحق والتجديدات','Clients/contract_amendments.php',12,'1','fa fa-file-pen',12),
  (85,'سجل الأحداث التعاقدية','Clients/contract_events.php', 12,'1','fa fa-timeline',13);

INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`) VALUES
  (12,82,1,1,1,1),
  (12,83,1,1,1,1),
  (12,84,1,1,1,1),
  (12,85,1,1,1,1);

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ فقط عند الطلب):
--   DELETE FROM role_permissions WHERE module_id IN (82,83,84,85);
--   DELETE FROM modules WHERE id IN (82,83,84,85);
--   DROP TABLE IF EXISTS products, units_of_measure, contract_amendments, contract_events;
-- ══════════════════════════════════════════════════════════════════════════════

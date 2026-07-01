-- ══════════════════════════════════════════════════════════════════════════════
-- EQUIP-OPE-S05-EMS · إضافات المبيعات الآمنة (Additive only)
-- 5 كيانات جديدة من المواصفة غير موجودة في النظام، أثرها صفر على الجداول القائمة.
-- كل الشاشات المرتبطة توضع داخل مجلد Clients/.
-- التنفيذ: mysql -u root --default-character-set=utf8mb4 < هذا الملف
-- التراجع (Rollback) في نهاية الملف (تعليق).
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── 1) سجل الأنشطة التجارية (§6.10) — polymorphic، لا FK فيزيائي على الجداول القائمة
CREATE TABLE IF NOT EXISTS `activities` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT NOT NULL,
  `activity_code`    VARCHAR(50) NOT NULL,
  `activity_type`    ENUM('زيارة عميل','اجتماع موقع','افتراضي','هاتفي','تفاوضي','زيارة مناجم') NOT NULL,
  `entity_type`      ENUM('opportunity','client','contract') NOT NULL DEFAULT 'client',
  `entity_id`        INT UNSIGNED NULL,
  `subject`          VARCHAR(255) NULL,
  `activity_date`    DATE NULL,
  `assigned_user_id` INT NULL,
  `outcome`          TEXT NULL,
  `is_negotiation`   TINYINT(1) NOT NULL DEFAULT 0,
  `notes`            TEXT NULL,
  `created_by`       INT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`       TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`       DATETIME NULL,
  `deleted_by`       INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_act_scope`  (`company_id`,`is_deleted`),
  KEY `idx_act_entity` (`entity_type`,`entity_id`),
  KEY `idx_act_type`   (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2) سجل المخاطر التجارية (§6.11) — polymorphic
CREATE TABLE IF NOT EXISTS `commercial_risks` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT NOT NULL,
  `risk_code`     VARCHAR(50) NOT NULL,
  `name`          VARCHAR(255) NOT NULL,
  `risk_type`     ENUM('عميل','موقع','تمويل','تحصيل','تشغيل','موردون') NOT NULL DEFAULT 'عميل',
  `severity`      ENUM('منخفضة','متوسطة','عالية') NOT NULL DEFAULT 'متوسطة',
  `mitigation`    TEXT NULL,
  `owner_user_id` INT NULL,
  `state`         ENUM('مفتوح','تحت المعالجة','مغلق') NOT NULL DEFAULT 'مفتوح',
  `entity_type`   ENUM('opportunity','contract') NOT NULL DEFAULT 'opportunity',
  `entity_id`     INT UNSIGNED NULL,
  `notes`         TEXT NULL,
  `created_by`    INT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`    DATETIME NULL,
  `deleted_by`    INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_risk_scope`  (`company_id`,`is_deleted`),
  KEY `idx_risk_entity` (`entity_type`,`entity_id`),
  KEY `idx_risk_state`  (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3) المناقصات (§6.4) — FK منطقي إلى opportunities/clients (يعيش هنا فقط)
CREATE TABLE IF NOT EXISTS `tenders` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`          INT NOT NULL,
  `tender_code`         VARCHAR(50) NOT NULL,
  `name`                VARCHAR(255) NOT NULL,
  `authority_id`        INT NULL,
  `opportunity_id`      INT UNSIGNED NULL,
  `closing_date`        DATE NULL,
  `participation_state` ENUM('إعداد','مقدمة','مسحوبة') NOT NULL DEFAULT 'إعداد',
  `result`              ENUM('قيد التقييم','فوز','خسارة','إلغاء') NOT NULL DEFAULT 'قيد التقييم',
  `result_reason`       VARCHAR(255) NULL,
  `notes`               TEXT NULL,
  `created_by`          INT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`          TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`          DATETIME NULL,
  `deleted_by`          INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tender_scope` (`company_id`,`is_deleted`),
  KEY `idx_tender_opp`   (`opportunity_id`),
  KEY `idx_tender_state` (`participation_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4) العروض (§6.5) — رأس العرض (البنود مؤجلة، القيمة تُدخل مباشرة)
CREATE TABLE IF NOT EXISTS `quotations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT NOT NULL,
  `quotation_code` VARCHAR(50) NOT NULL,
  `client_id`      INT NULL,
  `opportunity_id` INT UNSIGNED NULL,
  `currency`       ENUM('USD','SDG') NOT NULL DEFAULT 'USD',
  `amount_total`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `validity_date`  DATE NULL,
  `payment_terms`  VARCHAR(255) NULL,
  `state`          ENUM('مسودة','مقدم','مقبول','مرفوض') NOT NULL DEFAULT 'مسودة',
  `notes`          TEXT NULL,
  `created_by`     INT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`     DATETIME NULL,
  `deleted_by`     INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_quo_scope` (`company_id`,`is_deleted`),
  KEY `idx_quo_opp`   (`opportunity_id`),
  KEY `idx_quo_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5) نماذج التسعير (§6.8) — كتالوج مستقل تمامًا
CREATE TABLE IF NOT EXISTS `pricelists` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT NOT NULL,
  `pricelist_code`  VARCHAR(50) NOT NULL,
  `name`            VARCHAR(255) NOT NULL,
  `currency`        ENUM('USD','SDG') NOT NULL DEFAULT 'USD',
  `revenue_model`   ENUM('hourly','ton','meter') NULL,
  `base_price`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `distance_factor` DECIMAL(6,3) NULL,
  `shift_factor`    DECIMAL(6,3) NULL,
  `volume_factor`   DECIMAL(6,3) NULL,
  `duration_factor` DECIMAL(6,3) NULL,
  `notes`           TEXT NULL,
  `created_by`      INT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`      DATETIME NULL,
  `deleted_by`      INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pl_scope` (`company_id`,`is_deleted`),
  KEY `idx_pl_model` (`revenue_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسجيل الوحدات (نمط module 66) — صفوف جديدة فقط، ids 77..81 owner_role_id=12
INSERT INTO `modules` (`id`,`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`) VALUES
  (77,'الأنشطة التجارية','Clients/activities.php',      12,'1','fa fa-handshake',5),
  (78,'المخاطر التجارية','Clients/commercial_risks.php',12,'1','fa fa-triangle-exclamation',6),
  (79,'المناقصات','Clients/tenders.php',                12,'1','fa fa-gavel',7),
  (80,'العروض','Clients/quotations.php',                12,'1','fa fa-file-invoice-dollar',8),
  (81,'نماذج التسعير','Clients/pricelists.php',          12,'1','fa fa-tags',9);

-- ── صلاحيات دور المبيعات (12) على وحداته الجديدة فقط
INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`) VALUES
  (12,77,1,1,1,1),
  (12,78,1,1,1,1),
  (12,79,1,1,1,1),
  (12,80,1,1,1,1),
  (12,81,1,1,1,1);

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK (للتراجع الكامل — نفّذ هذه فقط عند الطلب):
--   DELETE FROM role_permissions WHERE module_id IN (77,78,79,80,81);
--   DELETE FROM modules WHERE id IN (77,78,79,80,81);
--   DROP TABLE IF EXISTS activities, commercial_risks, tenders, quotations, pricelists;
-- ══════════════════════════════════════════════════════════════════════════════

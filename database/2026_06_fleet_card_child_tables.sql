-- =====================================================================
--  Fleet & Readiness — Phase 0 / Step 4 : جداول أبناء كرت المعدة (One2many)
--  Equipment Card Child Tables
-- ---------------------------------------------------------------------
--  1) fleet_equipment_compliance  : الوثائق الرسمية (§9.4-أ)
--  2) fleet_equipment_protection  : تجهيزات الحماية (§9.4-ب)
--  3) fleet_equipment_component   : المكوّنات الكبرى (§9.4-ج)
--  4) fleet_equipment_history     : سجل تاريخ المعدة (§9.6) — إدراج فقط
--
--  كلها: company_id (عزل) + equipment_id (FK → equipments.id CASCADE)
--  الحقول المحسوبة من S08/العدّاد تبقى فارغة (لاحقاً). إضافية غير كاسرة.
--  آمن للتشغيل المتكرر — MariaDB 10.4+ · DB: equipation_manage
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) الوثائق الرسمية
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_equipment_compliance` (
    `id`              INT(11)       NOT NULL AUTO_INCREMENT,
    `company_id`      INT(11)       NULL DEFAULT NULL,
    `equipment_id`    INT(11)       NOT NULL,
    `doc_type`        VARCHAR(40)   NOT NULL,                 -- تأمين/رخصة/شهادة فحص...
    `reference`       VARCHAR(120)  NULL DEFAULT NULL,
    `issue_date`      DATE          NULL DEFAULT NULL,
    `expiry_date`     DATE          NULL DEFAULT NULL,
    `is_critical`     TINYINT(1)    NOT NULL DEFAULT 0,
    `attachment_path` VARCHAR(255)  NULL DEFAULT NULL,
    `is_deleted`      TINYINT(1)    NOT NULL DEFAULT 0,
    `created_by`      INT(11)       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fec_equipment` (`equipment_id`),
    KEY `idx_fec_company`   (`company_id`),
    KEY `idx_fec_expiry`    (`expiry_date`),
    CONSTRAINT `fk_fec_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 2) تجهيزات الحماية
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_equipment_protection` (
    `id`              INT(11)        NOT NULL AUTO_INCREMENT,
    `company_id`      INT(11)        NULL DEFAULT NULL,
    `equipment_id`    INT(11)        NOT NULL,
    `protection_type` VARCHAR(40)    NOT NULL,
    `description`     VARCHAR(200)   NULL DEFAULT NULL,
    `start_date`      DATE           NULL DEFAULT NULL,
    `cost`            DECIMAL(12,2)  NULL DEFAULT NULL,
    `state`           VARCHAR(20)    NULL DEFAULT NULL,        -- فعّال/يحتاج تجديداً/منتهٍ
    `renewal_date`    DATE           NULL DEFAULT NULL,
    `partner_id`      INT(11)        NULL DEFAULT NULL,        -- مرجع منطقي → suppliers.id
    `compliance_id`   INT(11)        NULL DEFAULT NULL,        -- FK → fleet_equipment_compliance.id
    `attachment_path` VARCHAR(255)   NULL DEFAULT NULL,
    `is_deleted`      TINYINT(1)     NOT NULL DEFAULT 0,
    `created_by`      INT(11)        NULL DEFAULT NULL,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fep_equipment`  (`equipment_id`),
    KEY `idx_fep_company`    (`company_id`),
    KEY `idx_fep_compliance` (`compliance_id`),
    CONSTRAINT `fk_fep_equipment`  FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fep_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `fleet_equipment_compliance` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3) المكوّنات الكبرى (replace_date/component_hours/replace_count تُغذّى لاحقاً من S08)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_equipment_component` (
    `id`              INT(11)        NOT NULL AUTO_INCREMENT,
    `company_id`      INT(11)        NULL DEFAULT NULL,
    `equipment_id`    INT(11)        NOT NULL,
    `component_type`  VARCHAR(40)    NOT NULL,                 -- محرك/هيدروليك/جيربوكس...
    `serial_no`       VARCHAR(120)   NULL DEFAULT NULL,
    `install_date`    DATE           NULL DEFAULT NULL,
    `is_current`      TINYINT(1)     NOT NULL DEFAULT 1,
    `replace_date`    DATE           NULL DEFAULT NULL,        -- (لاحقاً S08)
    `component_hours` DECIMAL(12,2)  NULL DEFAULT NULL,        -- (لاحقاً العدّاد)
    `replace_count`   INT(11)        NULL DEFAULT NULL,        -- (لاحقاً S08)
    `is_deleted`      TINYINT(1)     NOT NULL DEFAULT 0,
    `created_by`      INT(11)        NULL DEFAULT NULL,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fecmp_equipment` (`equipment_id`),
    KEY `idx_fecmp_company`   (`company_id`),
    CONSTRAINT `fk_fecmp_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 4) سجل تاريخ المعدة — إدراج فقط (لا is_deleted ولا updated_at)
--    الحقول المالية/الساعات تُغذّى لاحقاً من L5/S08/الترحيل
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_equipment_history` (
    `id`               INT(11)       NOT NULL AUTO_INCREMENT,
    `company_id`       INT(11)       NULL DEFAULT NULL,
    `equipment_id`     INT(11)       NOT NULL,
    `event_date`       DATETIME      NOT NULL,
    `event_type`       VARCHAR(40)   NOT NULL,
    `reference_type`   VARCHAR(40)   NULL DEFAULT NULL,
    `reference_id`     INT(11)       NULL DEFAULT NULL,
    `project_id`       INT(11)       NULL DEFAULT NULL,
    `site_id`          VARCHAR(120)  NULL DEFAULT NULL,
    `in_out_date`      DATE          NULL DEFAULT NULL,
    `work_hours`       DECIMAL(12,2) NULL DEFAULT NULL,
    `down_hours`       DECIMAL(12,2) NULL DEFAULT NULL,
    `maintenance_cost` DECIMAL(12,2) NULL DEFAULT NULL,
    `transfer_cost`    DECIMAL(12,2) NULL DEFAULT NULL,
    `note`             VARCHAR(255)  NULL DEFAULT NULL,
    `created_by`       INT(11)       NULL DEFAULT NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feh_equipment` (`equipment_id`),
    KEY `idx_feh_company`   (`company_id`),
    KEY `idx_feh_date`      (`event_date`),
    CONSTRAINT `fk_feh_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
--  نهاية السكربت
-- =====================================================================

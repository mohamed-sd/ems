-- =====================================================================
--  Fleet & Readiness — Phase 0 / Step 1 : سجل النوع والموديل
--  Fleet Model Master
-- ---------------------------------------------------------------------
--  - fleet_model                : السجل الرئيسي للموديلات (Model Master)
--  - fleet_model_service_spec   : سطور المواصفات القياسية للصيانة (One2many)
--  - equipments.model_id        : ربط المعدة بالموديل
--  - equipments_types           : بذر الأنواع الناقصة (توحيد، بلا enum ثابت)
--  - modules + role_permissions : تسجيل الشاشة في التنقّل والصلاحيات
--
--  آمن للتشغيل المتكرر (idempotent) — MariaDB 10.4+
--  قاعدة البيانات: equipation_manage
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1) جدول الموديلات الرئيسي : fleet_model
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_model` (
    `id`                  INT(11)        NOT NULL AUTO_INCREMENT,
    `company_id`          INT(11)        NULL DEFAULT NULL,
    `code`                VARCHAR(60)    NOT NULL,                 -- كود الموديل (فريد لكل شركة)
    `manufacturer`        VARCHAR(120)   NULL DEFAULT NULL,        -- الصانع / الماركة
    `model_name`          VARCHAR(150)   NOT NULL,                 -- اسم/رقم الموديل
    `equipment_type_id`   INT(11)        NULL DEFAULT NULL,        -- FK → equipments_types.id
    `operating_category`  VARCHAR(60)    NULL DEFAULT NULL,        -- فئة التشغيل (ثابتة)
    `fuel_type`           VARCHAR(40)    NULL DEFAULT NULL,        -- نوع الوقود (ثابت)
    `std_capacity`        DECIMAL(14,2)  NULL DEFAULT NULL,        -- السعة/القدرة القياسية
    `std_capacity_uom`    VARCHAR(40)    NULL DEFAULT NULL,        -- وحدة القياس (ثابتة)
    `tech_reference`      VARCHAR(255)   NULL DEFAULT NULL,        -- مرجع فني / كتالوج
    `default_supplier_id` INT(11)        NULL DEFAULT NULL,        -- FK → suppliers.id
    `status`              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `is_deleted`          TINYINT(1)     NOT NULL DEFAULT 0,
    `created_by`          INT(11)        NULL DEFAULT NULL,
    `created_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- ملاحظة: تفرّد الكود لكل شركة يُفرض على مستوى التطبيق (للسجلات غير المحذوفة)
    -- لتجنّب رفض إعادة استخدام كود بعد الحذف الناعم. لذا فهرس عادي وليس UNIQUE.
    KEY `idx_fleet_model_company_code` (`company_id`, `code`),
    KEY `idx_fleet_model_company`  (`company_id`),
    KEY `idx_fleet_model_type`     (`equipment_type_id`),
    KEY `idx_fleet_model_supplier` (`default_supplier_id`),
    KEY `idx_fleet_model_status`   (`status`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 2) سطور المواصفات القياسية للصيانة : fleet_model_service_spec
--    (One2many مرتبط بـ fleet_model عبر model_id ، حذف متتالٍ)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_model_service_spec` (
    `id`              INT(11)        NOT NULL AUTO_INCREMENT,
    `model_id`        INT(11)        NOT NULL,                 -- FK → fleet_model.id
    `company_id`      INT(11)        NULL DEFAULT NULL,
    `item_type`       VARCHAR(80)    NULL DEFAULT NULL,        -- نوع البند (فلتر/زيت/إطار/قطعة...)
    `recommended_ref` VARCHAR(150)   NULL DEFAULT NULL,        -- المرجع الموصى به
    `qty`             DECIMAL(12,2)  NULL DEFAULT NULL,        -- الكمية
    `uom`             VARCHAR(40)    NULL DEFAULT NULL,        -- وحدة القياس
    `alt_ref`         VARCHAR(150)   NULL DEFAULT NULL,        -- مرجع بديل
    `photo_path`      VARCHAR(255)   NULL DEFAULT NULL,        -- مسار صورة البند (نسبي)
    `note`            TEXT           NULL DEFAULT NULL,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fmss_model`   (`model_id`),
    KEY `idx_fmss_company` (`company_id`),
    CONSTRAINT `fk_fmss_model` FOREIGN KEY (`model_id`)
        REFERENCES `fleet_model` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3) ربط المعدة بالموديل : equipments.model_id  (إضافي وآمن)
-- ---------------------------------------------------------------------
ALTER TABLE `equipments`
    ADD COLUMN IF NOT EXISTS `model_id` INT(11) NULL DEFAULT NULL AFTER `model`,
    ADD INDEX IF NOT EXISTS `idx_equipments_model_id` (`model_id`);

-- ---------------------------------------------------------------------
-- 4) توحيد الأنواع : بذر الأنواع الناقصة في equipments_types (idempotent)
--    form: 1=معدات ثقيلة ، 2=شاحنات ، 3=خرمات
-- ---------------------------------------------------------------------
INSERT INTO `equipments_types` (`form`, `type`, `status`, `created_at`, `updated_at`)
SELECT s.form, s.type, 'active', NOW(), NOW()
FROM (
        SELECT '1' AS form, 'لودر'        AS type UNION ALL
        SELECT '1',         'جريدر'              UNION ALL
        SELECT '1',         'دوزر'               UNION ALL
        SELECT '1',         'شيول'               UNION ALL
        SELECT '1',         'رافعة'              UNION ALL
        SELECT '1',         'حفّاضة'             UNION ALL
        SELECT '1',         'مولّد كهرباء'        UNION ALL
        SELECT '1',         'ضاغط هواء'          UNION ALL
        SELECT '2',         'شاحنة'              UNION ALL
        SELECT '2',         'صهريج'              UNION ALL
        SELECT '1',         'أخرى'
     ) AS s
WHERE NOT EXISTS (
        SELECT 1 FROM `equipments_types` e WHERE e.`type` = s.`type`
     );

-- ---------------------------------------------------------------------
-- 5) تسجيل الشاشة في التنقّل : modules  (الدور 3 = إدارة الأسطول)
-- ---------------------------------------------------------------------
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'سجل النوع والموديل', 'Equipments/fleet_models.php', 3, '1', 'fa fa-clipboard-list', 12
WHERE NOT EXISTS (
        SELECT 1 FROM `modules` m WHERE m.`code` = 'Equipments/fleet_models.php'
     );

-- ---------------------------------------------------------------------
-- 6) الصلاحيات : استنساخ صلاحيات شاشة "إدارة المعدات" (equipments_fleet)
--    لضمان رؤية الشاشة لكل من يملك إدارة المعدات (وإلا حُجبت من insidebar)
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT rp.`role_id`, nm.`id`, rp.`can_view`, rp.`can_add`, rp.`can_edit`, rp.`can_delete`
FROM `role_permissions` rp
JOIN `modules` src ON src.`code` = 'Equipments/equipments_fleet.php'
JOIN `modules` nm  ON nm.`code`  = 'Equipments/fleet_models.php'
WHERE rp.`module_id` = src.`id`
  AND NOT EXISTS (
        SELECT 1 FROM `role_permissions` x
        WHERE x.`role_id` = rp.`role_id` AND x.`module_id` = nm.`id`
     );

-- =====================================================================
--  نهاية السكربت
-- =====================================================================

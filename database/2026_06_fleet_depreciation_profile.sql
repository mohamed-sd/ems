-- =====================================================================
--  Fleet & Readiness — Phase 0 / Step 2 : ملف الافتراضات المالية والإهلاك
--  Fleet Depreciation Profile  (§9.2)
-- ---------------------------------------------------------------------
--  - fleet_depreciation_profile        : ملف الافتراضات (مسودة/معتمد + حذف ناعم)
--  - fleet_depreciation_profile_audit  : أثر تدقيقي للتعديلات (لا حذف صامت للقيم)
--  - بذور افتراضية معتمدة لكل شركة      : أدلة الصانعين + IAS 16
--  - fleet_model.depreciation_profile_id : ربط الموديل بالملف (مسار الوراثة)
--  - modules + role_permissions         : تسجيل الشاشة في التنقّل والصلاحيات
--
--  المبدأ: الملف مرجع تقرأ منه المعدة عبر موديلها — لا حساب إهلاك هنا.
--  مسار الوراثة: المعدة ← model_id ← fleet_model.depreciation_profile_id ← هذا الجدول
--
--  آمن للتشغيل المتكرر (idempotent) — MariaDB 10.4+ · DB: equipation_manage
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) جدول ملف الافتراضات المالية والإهلاك
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_depreciation_profile` (
    `id`             INT(11)        NOT NULL AUTO_INCREMENT,
    `company_id`     INT(11)        NULL DEFAULT NULL,
    `code`           VARCHAR(50)    NOT NULL,                  -- كود الملف (فريد لكل شركة — يُفرض بالتطبيق)
    `asset_category` VARCHAR(120)   NOT NULL,                  -- فئة الأصل
    `brand`          VARCHAR(120)   NULL DEFAULT NULL,         -- تخصيص لماركة (اختياري)
    `model_id`       INT(11)        NULL DEFAULT NULL,         -- مرجع منطقي → fleet_model.id (اختياري)
    `method`         ENUM('uop','sl') NOT NULL DEFAULT 'uop', -- uop=بالساعة · sl=زمني بالسنوات
    `useful_life`    DECIMAL(12,2)  NOT NULL,                  -- ساعات (uop) أو سنوات (sl)
    `salvage_pct`    DECIMAL(5,4)   NOT NULL,                  -- نسبة التخريد 0..1
    `notes`          TEXT           NULL DEFAULT NULL,
    `state`          ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    `approved_by`    INT(11)        NULL DEFAULT NULL,         -- مرجع منطقي → users.id (المدير المالي)
    `approved_at`    DATETIME       NULL DEFAULT NULL,
    `is_deleted`     TINYINT(1)     NOT NULL DEFAULT 0,
    `created_by`     INT(11)        NULL DEFAULT NULL,
    `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fdp_company_code` (`company_id`, `code`),
    KEY `idx_fdp_company`      (`company_id`),
    KEY `idx_fdp_model`        (`model_id`),
    KEY `idx_fdp_state`        (`state`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 2) جدول الأثر التدقيقي (Audit) — يحفظ لقطة قبل/بعد لكل تغيير
--    تطبيقاً لقاعدة §9.2: لا تُحذف القيمة القديمة بصمت
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fleet_depreciation_profile_audit` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `profile_id` INT(11)      NOT NULL,
    `company_id` INT(11)      NULL DEFAULT NULL,
    `action`     VARCHAR(20)  NOT NULL,            -- created / updated / approved / disabled
    `changed_by` INT(11)      NULL DEFAULT NULL,
    `changed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `old_data`   TEXT         NULL DEFAULT NULL,   -- لقطة JSON قبل التغيير
    `new_data`   TEXT         NULL DEFAULT NULL,   -- لقطة JSON بعد التغيير
    `note`       VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fdpa_profile` (`profile_id`),
    KEY `idx_fdpa_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3) البذور الافتراضية المعتمدة — لكل شركة (المصدر: أدلة الصانعين + IAS 16)
--    state=approved ، approved_by=NULL (بذور النظام) ، idempotent
-- ---------------------------------------------------------------------
INSERT INTO `fleet_depreciation_profile`
    (`company_id`, `code`, `asset_category`, `method`, `useful_life`, `salvage_pct`, `state`, `approved_at`, `created_at`, `updated_at`)
SELECT c.`id`,
       CONCAT('DEP-', LPAD(s.seq, 3, '0')),
       s.asset_category, s.method, s.useful_life, s.salvage_pct, 'approved', NOW(), NOW(), NOW()
FROM `admin_companies` c
CROSS JOIN (
        SELECT 1  AS seq, 'حفّار 22ط جديد'   AS asset_category, 'uop' AS method, 15000 AS useful_life, 0.0800 AS salvage_pct UNION ALL
        SELECT 2,  'حفّار 22ط مستعمل',  'uop',  8000, 0.0800 UNION ALL
        SELECT 3,  'حفّار 30ط مستعمل',  'uop', 10000, 0.0800 UNION ALL
        SELECT 4,  'حفّار 34ط جديد',    'uop', 18000, 0.0800 UNION ALL
        SELECT 5,  'حفّار 34ط مستعمل',  'uop', 10000, 0.0800 UNION ALL
        SELECT 6,  'قلّاب جديد',         'uop', 20000, 0.1000 UNION ALL
        SELECT 7,  'قلّاب مستعمل',       'uop', 12000, 0.1000 UNION ALL
        SELECT 8,  'خرّامة DTH جديدة',   'uop', 12000, 0.0500 UNION ALL
        SELECT 9,  'لودر مستعمل',        'uop',  6000, 0.0800 UNION ALL
        SELECT 10, 'هامر هيدروليكي',     'uop',  6000, 0.0500 UNION ALL
        SELECT 11, 'جهاز مسح RTK',       'sl',      7, 0.0500
     ) s
WHERE NOT EXISTS (
        SELECT 1 FROM `fleet_depreciation_profile` p
        WHERE p.`company_id` = c.`id` AND p.`asset_category` = s.asset_category
     );

-- ---------------------------------------------------------------------
-- 4) ربط الموديل بالملف : fleet_model.depreciation_profile_id (إضافي آمن)
--    مرجع منطقي → fleet_depreciation_profile.id  (لا عمود جديد على equipments)
-- ---------------------------------------------------------------------
ALTER TABLE `fleet_model`
    ADD COLUMN IF NOT EXISTS `depreciation_profile_id` INT(11) NULL DEFAULT NULL AFTER `default_supplier_id`,
    ADD INDEX IF NOT EXISTS `idx_fleet_model_dep_profile` (`depreciation_profile_id`);

-- ---------------------------------------------------------------------
-- 5) تسجيل الشاشة في التنقّل : modules  (الدور 3 = إدارة الأسطول)
-- ---------------------------------------------------------------------
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'ملف الإهلاك المالي', 'Equipments/fleet_depreciation_profiles.php', 3, '1', 'fa fa-coins', 13
WHERE NOT EXISTS (
        SELECT 1 FROM `modules` m WHERE m.`code` = 'Equipments/fleet_depreciation_profiles.php'
     );

-- ---------------------------------------------------------------------
-- 6) الصلاحيات : استنساخ صلاحيات شاشة "سجل النوع والموديل"
--    (الاعتماد يُقصر بالتطبيق على من يملك can_delete = مستوى الإدارة)
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT rp.`role_id`, nm.`id`, rp.`can_view`, rp.`can_add`, rp.`can_edit`, rp.`can_delete`
FROM `role_permissions` rp
JOIN `modules` src ON src.`code` = 'Equipments/fleet_models.php'
JOIN `modules` nm  ON nm.`code`  = 'Equipments/fleet_depreciation_profiles.php'
WHERE rp.`module_id` = src.`id`
  AND NOT EXISTS (
        SELECT 1 FROM `role_permissions` x
        WHERE x.`role_id` = rp.`role_id` AND x.`module_id` = nm.`id`
     );

-- =====================================================================
--  نهاية السكربت
-- =====================================================================

-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_07_07_catchup_runtime_ddl.sql — ترحيل اللحاق (المرحلة 0 · ADR-03)
--
-- ينقل إلى المُشغِّل كلَّ عبارات DDL التي كانت الصفحات تنفّذها وقت الطلب
-- (الجرد الكامل: 21 ملفًا — قائمة المواضع في docs/MIGRATIONS_GUIDE_ar.md §5).
-- بعد تطبيق هذا الملف تصبح كل فحوص db_table_has_column في تلك الصفحات
-- صادقةً دائمًا، فلا يُنفَّذ أي ALTER وقت الطلب — تمهيدًا لتجميد EMS_DDL_FREEZE.
--
-- idempotent بالكامل: الأعمدة والفهارس عبر الحارس الموحّد (يفحص غياب العمود
-- قبل الإضافة)، والجداول عبر CREATE TABLE IF NOT EXISTS.
--
-- ملاحظة ترميز: op_state بقيم ENUM عربية — يجب التطبيق بعميل utf8mb4
-- (المُشغِّل يضمن ذلك؛ التطبيق اليدوي يتطلب --default-character-set=utf8mb4).
--
-- ملاحظة مطابقة: جدولا timesheet_approvals/timesheet_approval_notes كانا
-- يُنشآن في المصدر بـ utf8_general_ci؛ هنا utf8mb4_unicode_ci التزامًا بترحيل
-- التوحيد 2026_06_08 (الجداول القائمة لا تُمسّ — IF NOT EXISTS).
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1) operations — من Oprators/oprators.php · movement/move_oprators.php ·
--    movement/movement_operations.php
-- ─────────────────────────────────────────────────────────────────────────────

-- operations.company_id INT NULL AFTER project_id
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME='company_id'),
    'ALTER TABLE operations ADD COLUMN company_id INT NULL AFTER project_id', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- INDEX idx_operations_company_id (company_id)
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND INDEX_NAME='idx_operations_company_id'),
    'ALTER TABLE operations ADD INDEX idx_operations_company_id (company_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- operations.shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER shift_hours
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME='shift_type'),
    'ALTER TABLE operations ADD COLUMN shift_type ENUM(''D'',''N'',''B'') NOT NULL DEFAULT ''B'' AFTER shift_hours', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- operations.target_daily_hours DECIMAL(10,2) NULL AFTER shift_hours
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME='target_daily_hours'),
    'ALTER TABLE operations ADD COLUMN target_daily_hours DECIMAL(10,2) NULL DEFAULT NULL AFTER shift_hours', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- operations.op_state ENUM عربية NOT NULL DEFAULT 'جاهزة' AFTER status
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME='op_state'),
    'ALTER TABLE operations ADD COLUMN op_state ENUM(''تعمل'',''جاهزة'',''معطلة'') NOT NULL DEFAULT ''جاهزة'' AFTER status', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- operations.prev_equipment_category VARCHAR(20) NULL AFTER equipment_category
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME='prev_equipment_category'),
    'ALTER TABLE operations ADD COLUMN prev_equipment_category VARCHAR(20) NULL DEFAULT NULL AFTER equipment_category', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2) equipment_drivers — من movement/movement_operations.php · project_drivers.php
-- ─────────────────────────────────────────────────────────────────────────────

-- equipment_drivers.shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER end_date
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipment_drivers' AND COLUMN_NAME='shift_type'),
    'ALTER TABLE equipment_drivers ADD COLUMN shift_type ENUM(''D'',''N'',''B'') NOT NULL DEFAULT ''B'' AFTER end_date', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3) users — من main/users.php · main/project_users.php (الحذف الناعم + الحالة)
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_deleted'),
    'ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='deleted_at'),
    'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='deleted_by'),
    'ALTER TABLE users ADD COLUMN deleted_by INT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status'),
    'ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''active''', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4) suppliers — من Suppliers/suppliers.php (الحذف الناعم)
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='is_deleted'),
    'ALTER TABLE suppliers ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='deleted_at'),
    'ALTER TABLE suppliers ADD COLUMN deleted_at DATETIME NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='deleted_by'),
    'ALTER TABLE suppliers ADD COLUMN deleted_by INT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5) contracts — من Contracts/contracts.php (الحذف الناعم)
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='is_deleted'),
    'ALTER TABLE contracts ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='deleted_at'),
    'ALTER TABLE contracts ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='deleted_by'),
    'ALTER TABLE contracts ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 6) clients — من Clients/clients.php (الحذف الناعم)
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clients' AND COLUMN_NAME='is_deleted'),
    'ALTER TABLE clients ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clients' AND COLUMN_NAME='deleted_at'),
    'ALTER TABLE clients ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clients' AND COLUMN_NAME='deleted_by'),
    'ALTER TABLE clients ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 7) project — من Projects/projects.php (الحذف الناعم)
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project' AND COLUMN_NAME='is_deleted'),
    'ALTER TABLE project ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project' AND COLUMN_NAME='deleted_at'),
    'ALTER TABLE project ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project' AND COLUMN_NAME='deleted_by'),
    'ALTER TABLE project ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 8) equipments — من Equipments/equipments.php · equipments_drivers.php
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipments' AND COLUMN_NAME='company_id'),
    'ALTER TABLE equipments ADD COLUMN company_id INT(11) NULL DEFAULT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 9) mnt_breakdown — من Maintenance/breakdowns.php
-- ─────────────────────────────────────────────────────────────────────────────

SET @ddl = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mnt_breakdown')
    AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mnt_breakdown' AND COLUMN_NAME='target_role'),
    'ALTER TABLE mnt_breakdown ADD COLUMN target_role INT NULL DEFAULT NULL AFTER reporter_dept', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ─────────────────────────────────────────────────────────────────────────────
-- 10) جداول اعتماد الساعات — من Approvals/hours_approval*.php
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `timesheet_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `approval_level` tinyint(1) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_by_name` varchar(255) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ts_level` (`timesheet_id`, `approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `timesheet_approval_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `column_name` varchar(100) NOT NULL,
  `column_label` varchar(255) NOT NULL,
  `note_text` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_by_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 11) جداول الاشتراك SaaS — من company/register.php
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admin_subscription_plans (
    id INT NOT NULL AUTO_INCREMENT,
    plan_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_users INT NOT NULL DEFAULT 0,
    max_projects INT NOT NULL DEFAULT 0,
    max_equipments INT NOT NULL DEFAULT 0,
    features TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_companies (
    id INT NOT NULL AUTO_INCREMENT,
    plan_id INT NULL,
    name VARCHAR(200) NOT NULL,
    company_name_ar VARCHAR(200) NULL,
    company_name_en VARCHAR(200) NULL,
    company_name VARCHAR(200) NULL,
    commercial_registration VARCHAR(120) NULL,
    sector VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    tax_number VARCHAR(120) NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    address TEXT NULL,
    postal_address TEXT NULL,
    logo_path VARCHAR(255) NULL,
    status ENUM('pending','active','suspended','cancelled') NOT NULL DEFAULT 'pending',
    modules_enabled TEXT NULL,
    subscription_start DATE NULL,
    subscription_end DATE NULL,
    users_count INT NOT NULL DEFAULT 0,
    max_users INT NOT NULL DEFAULT 0,
    max_equipments INT NOT NULL DEFAULT 0,
    max_projects INT NOT NULL DEFAULT 0,
    currency VARCHAR(20) NOT NULL DEFAULT 'SAR',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_companies_email (email),
    UNIQUE KEY uq_admin_companies_commercial_registration (commercial_registration),
    KEY idx_admin_companies_plan (plan_id),
    KEY idx_admin_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_subscription_requests (
    id INT NOT NULL AUTO_INCREMENT,
    company_id INT NULL,
    company_name VARCHAR(200) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    plan_id INT NULL,
    message TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    review_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_sub_req_status (status),
    KEY idx_admin_sub_req_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 12) جداول المدير الأعلى — من admin/setup_once.php
--     (الترتيب مهم: super_admins قبل جدول الاسترجاع بسبب الـ FK)
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS super_admins (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_super_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS super_admin_password_resets (
    id INT NOT NULL AUTO_INCREMENT,
    super_admin_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_super_admin_password_resets_token_hash (token_hash),
    KEY idx_super_admin_password_resets_admin_id (super_admin_id),
    CONSTRAINT fk_super_admin_password_resets_admin
        FOREIGN KEY (super_admin_id) REFERENCES super_admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 13) جدول صلاحيات التقارير — من emsreports/setup_permissions.php
--     (نظام موازٍ مُجمَّد بقرار المراجعة؛ يُوحَّد تحت RBAC في المرحلة 4 —
--      إدراجه هنا توثيقٌ للقائم لا شرعنةٌ للتوسع فيه)
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `report_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `report_code` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_report` (`role_id`, `report_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

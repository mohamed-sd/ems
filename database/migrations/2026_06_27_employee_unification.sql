-- ═══════════════════════════════════════════════════════════════════════════
-- Migration (DDL reference): توحيد كيان الموظف — 2026-06-27
-- التطبيق الكامل (idempotent، يشمل ترحيل البيانات وإعادة توجيه worker_*) عبر:
--   php database/migrations/2026_06_27_employee_unification.php
-- هذا الملف توثيقٌ لبنية الجداول/الأعمدة الجديدة فقط. طبّقه بعميلٍ utf8mb4.
-- ⚠️ employee_roles منفصلٌ تماماً عن جدول roles (أدوار مستخدمي النظام/الصلاحيات).
-- ═══════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- 1) جداول البحث + المشغّلين ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_titles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NULL,                 -- NULL = مسمّى عامّ لكل الشركات
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_operator` TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = مسمّى يقود/يشغّل المعدات
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobtitle_company_name` (`company_id`,`name`),
  KEY `idx_jobtitle_company` (`company_id`), KEY `idx_jobtitle_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_roles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emprole_company_name` (`company_id`,`name`),
  KEY `idx_emprole_company` (`company_id`), KEY `idx_emprole_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- السائقون/المشغّلون: امتداد 1:1 لـ employees ببيانات الرخصة والتشغيل فقط
CREATE TABLE IF NOT EXISTS `equipment_operators` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NULL,
  `employee_id` INT NOT NULL,
  `license_number` VARCHAR(100) NULL,
  `license_type` VARCHAR(100) NULL,
  `license_grade` VARCHAR(40) NULL,
  `license_issuer` VARCHAR(255) NULL,
  `license_issue_date` DATE NULL,
  `license_expiry_date` DATE NULL,
  `license_photo` VARCHAR(255) NULL,
  `operating_categories` MEDIUMTEXT NULL,
  `driving_authorizations` VARCHAR(255) NULL,
  `medical_report_path` VARCHAR(255) NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_employee` (`employee_id`),
  KEY `idx_operator_company` (`company_id`),
  CONSTRAINT `fk_operator_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) أعمدة employees المدموجة من worker_profile + مفاتيح المسمى/الدور --------------
-- (MySQL 8 لا يدعم ADD COLUMN IF NOT EXISTS — طبّقها مرّةً، أو استخدم .php الموجّه)
ALTER TABLE `employees`
  ADD COLUMN `job_title_id` INT NULL,
  ADD COLUMN `employee_role_id` INT NULL,
  ADD COLUMN `is_workforce` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `worker_category` VARCHAR(40) NULL,
  ADD COLUMN `source_type` ENUM('شركة','مورد','مقاول') NULL,
  ADD COLUMN `workforce_class` ENUM('أساسي','احتياطي','بديل مؤقت','تغطية إجازة','تجاري مؤقت') NULL,
  ADD COLUMN `job_grade` VARCHAR(40) NULL,
  ADD COLUMN `workforce_state` ENUM('مرشّح','مسجّل','مؤهّل','متعاقد','مخصّص','في إجازة','منتهٍ') NULL,
  ADD COLUMN `medical_fitness_status` ENUM('لائق للعمل','لائق بشروط','موقوف طبيًّا','يحتاج إعادة تقييم') NULL,
  ADD COLUMN `fitness_conditions` VARCHAR(255) NULL,
  ADD COLUMN `primary_backup_id` INT NULL,
  ADD COLUMN `is_replaceable` TINYINT(1) NULL DEFAULT 1,
  ADD COLUMN `worker_code` VARCHAR(50) NULL,
  ADD KEY `idx_emp_job_title` (`job_title_id`),
  ADD KEY `idx_emp_role` (`employee_role_id`),
  ADD KEY `idx_emp_is_workforce` (`is_workforce`);

-- 3) بذور المسميات الوظيفية (عامّة) — (name, is_operator) ----------------------
INSERT INTO `job_titles` (company_id,name,is_operator,status,sort_order) VALUES
 (NULL,'مدير',0,1,10),(NULL,'مهندس',0,1,20),(NULL,'فني',0,1,30),(NULL,'كهربائي',0,1,40),
 (NULL,'مراقب',0,1,50),(NULL,'عامل مساندة',0,1,60),(NULL,'سائق',1,1,70),(NULL,'مشغل',1,1,80),
 (NULL,'سائق/مشغّل',1,1,90),(NULL,'مساعد',1,1,100),(NULL,'مبنشر',1,1,110),(NULL,'مشرف',0,1,120),
 (NULL,'إداري',0,1,130),(NULL,'فني ورشة',0,1,140),(NULL,'أمن',0,1,150),(NULL,'أخرى',0,1,160);

-- 4) بذور أدوار الموظفين (عامّة) ----------------------------------------------
INSERT INTO `employee_roles` (company_id,name,status,sort_order) VALUES
 (NULL,'مشغّل/سائق',1,10),(NULL,'سائق/مشغّل',1,20),(NULL,'فني',1,30),(NULL,'مهندس',1,40),
 (NULL,'مشرف',1,50),(NULL,'مراقب',1,60),(NULL,'عمالة مساندة',1,70),(NULL,'إداري',1,80);

-- 5) ربط الموظفين الحاليين بالمسمى من employee_type --------------------------
UPDATE `employees` e JOIN `job_titles` jt ON jt.company_id IS NULL AND jt.name=e.employee_type
  SET e.job_title_id=jt.id WHERE e.job_title_id IS NULL AND e.employee_type IS NOT NULL AND e.employee_type<>'';

-- 6) إعادة توجيه worker_* (تتم في .php): لكل جدول worker_*:
--    a) إسقاط FK نحو worker_profile(id):  ALTER TABLE <t> DROP FOREIGN KEY <fk_*_worker>;
--    b) ترحيل القيم worker_id (worker_profile.id) -> employee_id (worker_profile.employee_id)
--    c) إعادة التسمية:  ALTER TABLE <t> RENAME COLUMN worker_id TO employee_id;
--       (worker_backup: backup_worker_id -> backup_employee_id)
--    d) قيد جديد:  ADD CONSTRAINT <fk_*_emp> FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE;
--    e) إعادة بناء Views v_worker_billable_hours/v_worker_presence/v_worker_worklog على employees (is_workforce=1).
--    f) DROP TABLE worker_profile;  (بعد التأكد من صفر FK وارد + نسخة احتياطية)

-- 7) تسجيل شاشات الإدارة في القائمة (الموارد البشرية = الدور 4) -----------------
INSERT INTO `modules` (name,code,owner_role_id,is_link,icon,display_order)
 SELECT 'المسميات الوظيفية','Employees/job_titles.php',4,'1','fa fa-user-tag',11 FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE code='Employees/job_titles.php');
INSERT INTO `modules` (name,code,owner_role_id,is_link,icon,display_order)
 SELECT 'أدوار الموظفين','Employees/employee_roles.php',4,'1','fa fa-people-arrows',12 FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE code='Employees/employee_roles.php');

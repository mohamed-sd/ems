-- Migration: ربط حساب الدخول بالموظف (Account ↔ Person) — 2026-06-29
-- القرار (الخيار ب): الإبقاء على فصل users (حسابات الدخول + الأدوار) عن employees (أشخاص HR)،
-- وإضافة رابط اختياري واحد: users.employee_id → employees.id.
--   • «إسناد حساب لموظف» = ضبط users.employee_id على ذلك الموظف.
--   • «سحب الحساب»       = تفريغ employee_id (وتعطيل الحساب اختيارياً).
--   • UNIQUE يضمن: موظف واحد ← حساب واحد كحدٍّ أقصى (NULL متعدّد مسموح: حسابات بلا موظف).
--   • ON DELETE SET NULL: حذف الموظف لا يحذف الحساب، فقط يفكّ الرابط.
-- إضافة بحتة: لا يُمسّ أي مفتاح أجنبي قائم، ولا تتأثر المصادقة. آمنة لإعادة التنفيذ (تتحقّق أولاً).
SET NAMES utf8mb4;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'employee_id'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `users`
     ADD COLUMN `employee_id` INT NULL DEFAULT NULL COMMENT ''الموظف المرتبط بهذا الحساب'' AFTER `company_id`,
     ADD UNIQUE KEY `uq_users_employee` (`employee_id`),
     ADD CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL',
  'SELECT ''users.employee_id already exists — skipped'' AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

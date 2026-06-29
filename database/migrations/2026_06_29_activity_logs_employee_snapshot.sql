-- Migration: لقطة الموظف الفاعل في سجل النشاطات — 2026-06-29
-- يضيف activity_logs.employee_id = لقطة ثابتة لـ«الموظف الذي نفّذ الفعل» وقت حدوثه.
-- لماذا لقطة؟ لأن الحساب قد يُعاد ربطه لموظف آخر مستقبلاً (ميزة عمدية)، فاللقطة تحفظ
-- الإسناد الصحيح أثرياً. العرض يفضّل هذه اللقطة ثم يحتاط بالربط الحالي users.employee_id
-- (الذي يغطّي السجل التاريخي بنسبة ~95% فوراً). إضافة بحتة، آمنة لإعادة التنفيذ.
SET NAMES utf8mb4;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = 'employee_id'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `activity_logs`
     ADD COLUMN `employee_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''لقطة الموظف الفاعل وقت الحدث'' AFTER `user_id`,
     ADD KEY `idx_employee_created` (`employee_id`, `created_at`)',
  'SELECT ''activity_logs.employee_id already exists — skipped'' AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

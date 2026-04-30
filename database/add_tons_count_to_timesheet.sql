-- إضافة حقل عدد الأطنان لجدول timesheet
-- لاستخدامه في النوع 2 (القلاب)

ALTER TABLE `timesheet` 
ADD COLUMN `tons_count` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'عدد الأطنان - للنوع 2 (القلاب)' 
AFTER `operator_notes`;

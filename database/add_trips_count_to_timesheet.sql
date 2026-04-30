-- إضافة حقل عدد النقلات لجدول timesheet
-- لاستخدامه في النوع 2 (القلاب)

ALTER TABLE `timesheet` 
ADD COLUMN `trips_count` INT(11) DEFAULT 0 COMMENT 'عدد النقلات - للنوع 2 (القلاب)' 
AFTER `tons_count`;

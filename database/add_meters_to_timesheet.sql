-- إضافة حقول الأمتار لجدول timesheet
-- لاستخدامها في النوع 3 (الخرمات)

ALTER TABLE `timesheet` 
ADD COLUMN `meters_type` VARCHAR(50) DEFAULT NULL COMMENT 'نوع الأمتار - للنوع 3 (الخرمات)' 
AFTER `trips_count`;

ALTER TABLE `timesheet` 
ADD COLUMN `meters_count` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'عدد الأمتار - للنوع 3 (الخرمات)' 
AFTER `meters_type`;

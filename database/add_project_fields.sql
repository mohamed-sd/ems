-- إضافة الحقول الجديدة لجدول operationproject
-- Add new fields to operationproject table
-- Database: equipation_manage

-- إضافة عمود كود المشروع
ALTER TABLE `operationproject` ADD COLUMN `project_code` VARCHAR(50) NULL AFTER `id`;

-- إضافة عمود تصنيف المشروع
ALTER TABLE `operationproject` ADD COLUMN `category` VARCHAR(100) NULL AFTER `name`;

-- إضافة عمود القطاع الفرعي
ALTER TABLE `operationproject` ADD COLUMN `sub_sector` VARCHAR(200) NULL AFTER `category`;

-- إضافة عمود الولاية
ALTER TABLE `operationproject` ADD COLUMN `state` VARCHAR(100) NULL AFTER `sub_sector`;

-- إضافة عمود المنطقة
ALTER TABLE `operationproject` ADD COLUMN `region` VARCHAR(100) NULL AFTER `state`;

-- إضافة عمود أقرب سوق
ALTER TABLE `operationproject` ADD COLUMN `nearest_market` VARCHAR(100) NULL AFTER `region`;

-- إضافة عمود خط العرض
ALTER TABLE `operationproject` ADD COLUMN `latitude` VARCHAR(50) NULL AFTER `nearest_market`;

-- إضافة عمود خط الطول
ALTER TABLE `operationproject` ADD COLUMN `longitude` VARCHAR(50) NULL AFTER `latitude`;

-- إضافة فهرس فريد لكود المشروع
ALTER TABLE `operationproject` ADD UNIQUE INDEX `unique_project_code` (`project_code`);

-- عرض البنية الجديدة للجدول
DESCRIBE `operationproject`;

-- إضافة عمود company_project_id إلى جدول operationproject
-- Add company_project_id column to operationproject table
-- Database: equipation_manage

-- إضافة العمود الجديد
ALTER TABLE `operationproject` 
ADD COLUMN `company_project_id` INT(11) NULL COMMENT 'معرف المشروع من جدول company_project' AFTER `id`;

-- إضافة فهرس للعمود الجديد
ALTER TABLE `operationproject` 
ADD INDEX `idx_company_project_id` (`company_project_id`);

-- عرض بنية الجدول المحدثة
DESCRIBE `operationproject`;

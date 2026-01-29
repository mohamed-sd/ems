-- إضافة عمود معرف العميل إلى جدول operationproject
ALTER TABLE `operationproject` 
ADD COLUMN `company_client_id` INT(11) NULL 
COMMENT 'معرف العميل من جدول company_clients' AFTER `company_project_id`;

-- إضافة فهرس للأداء
ALTER TABLE `operationproject` 
ADD INDEX `idx_company_client_id` (`company_client_id`);

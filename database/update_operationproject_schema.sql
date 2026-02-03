-- تحديث جدول operationproject لإضافة الحقول الجديدة من جدول company_project
-- قم بتشغيل هذا الاستعلام في phpMyAdmin أو أي أداة إدارة MySQL

ALTER TABLE `operationproject` 
ADD COLUMN `project_code` varchar(50) DEFAULT NULL COMMENT 'كود المشروع' AFTER `location`,
ADD COLUMN `category` varchar(100) DEFAULT NULL COMMENT 'الفئة' AFTER `project_code`,
ADD COLUMN `sub_sector` varchar(100) DEFAULT NULL COMMENT 'القطاع الفرعي' AFTER `category`,
ADD COLUMN `state` varchar(100) DEFAULT NULL COMMENT 'الولاية' AFTER `sub_sector`,
ADD COLUMN `region` varchar(100) DEFAULT NULL COMMENT 'المنطقة' AFTER `state`,
ADD COLUMN `nearest_market` varchar(100) DEFAULT NULL COMMENT 'أقرب سوق' AFTER `region`,
ADD COLUMN `latitude` varchar(50) DEFAULT NULL COMMENT 'خط العرض' AFTER `nearest_market`,
ADD COLUMN `longitude` varchar(50) DEFAULT NULL COMMENT 'خط الطول' AFTER `latitude`,
ADD COLUMN `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم المنشئ' AFTER `status`,
ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تحديث' AFTER `create_at`;

-- تحديث البيانات الموجودة من جدول company_project إلى operationproject
UPDATE operationproject op
INNER JOIN company_project cp ON op.company_project_id = cp.id
SET 
    op.project_code = cp.project_code,
    op.category = cp.category,
    op.sub_sector = cp.sub_sector,
    op.state = cp.state,
    op.region = cp.region,
    op.nearest_market = cp.nearest_market,
    op.latitude = cp.latitude,
    op.longitude = cp.longitude;

-- إضافة حقل project_contract_id لجدول supplierscontracts
-- هذا الحقل يربط عقد المورد بعقد محدد من جدول contracts

ALTER TABLE `supplierscontracts` 
ADD COLUMN `project_contract_id` INT(11) DEFAULT NULL COMMENT 'معرف عقد المشروع من جدول contracts' AFTER `project_id`,
ADD INDEX `idx_project_contract` (`project_contract_id`);

-- ملاحظة: بعد تشغيل هذا السكريبت، يجب تحديث العقود الموجودة حالياً يدوياً
-- أو عمل سكريبت لربطها بالعقود المناسبة إذا كان هناك عقد واحد فقط لكل مشروع

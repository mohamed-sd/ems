-- ========================================
-- Fix equipment_drivers table
-- Make end_date column nullable
-- Date: 2026-02-28
-- ========================================

-- هذا الملف اختياري - يجعل عمود end_date قابل للقيمة NULL
-- بدلاً من استخدام تاريخ مستقبلي بعيد (2099-12-31)

USE equipation_manage;

-- تعديل عمود end_date ليكون قابل للقيمة NULL
ALTER TABLE `equipment_drivers` 
MODIFY COLUMN `end_date` varchar(50) DEFAULT NULL;

-- تحديث القيم الافتراضية البعيدة إلى NULL (اختياري)
-- UPDATE `equipment_drivers` SET `end_date` = NULL WHERE `end_date` = '2099-12-31';

-- إظهار البنية الجديدة
DESC `equipment_drivers`;

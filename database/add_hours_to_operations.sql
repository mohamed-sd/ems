-- إضافة حقول ساعات العمل الكلية وساعات الوردية إلى جدول operations
-- تاريخ: 2026-02-17

-- إضافة عمود إجمالي ساعات العمل للآلية
ALTER TABLE `operations` 
ADD COLUMN `total_equipment_hours` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'إجمالي ساعات العمل الكلية للآلية' AFTER `days`;

-- إضافة عمود ساعات الوردية
ALTER TABLE `operations` 
ADD COLUMN `shift_hours` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'عدد ساعات الوردية للمعدة' AFTER `total_equipment_hours`;

-- إضافة فهارس لتحسين الأداء
ALTER TABLE `operations` 
ADD INDEX `idx_total_equipment_hours` (`total_equipment_hours`),
ADD INDEX `idx_shift_hours` (`shift_hours`);

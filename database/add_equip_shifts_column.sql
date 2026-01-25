-- إضافة عمود عدد الورديات إلى جدول contractequipments
-- تاريخ التحديث: 2026-01-25

ALTER TABLE `contractequipments` 
ADD COLUMN `equip_shifts` INT(11) DEFAULT 0 COMMENT 'عدد الورديات' 
AFTER `equip_count`;

-- تحديث السجلات الموجودة بقيمة افتراضية (1 وردية)
UPDATE `contractequipments` 
SET `equip_shifts` = 1 
WHERE `equip_shifts` = 0 OR `equip_shifts` IS NULL;

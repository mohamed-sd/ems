-- إضافة عمود وحدات العمل في الشهر للمعدات
-- Add equip_monthly_target column to contractequipments table

ALTER TABLE `contractequipments`
ADD COLUMN `equip_monthly_target` INT(11) DEFAULT 0 COMMENT 'وحدات العمل في الشهر' AFTER `equip_total_month`;

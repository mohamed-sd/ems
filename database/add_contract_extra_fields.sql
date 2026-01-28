-- إضافة حقول إضافية لجدول العقود
-- Add extra fields to contracts table

ALTER TABLE `contracts`
ADD COLUMN `equip_shifts_contract` INT(11) DEFAULT 0 COMMENT 'عدد الورديات للعقد' AFTER `contract_duration_days`,
ADD COLUMN `shift_contract` INT(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد' AFTER `equip_shifts_contract`,
ADD COLUMN `equip_total_contract_daily` INT(11) DEFAULT 0 COMMENT 'إجمالي الوحدات يومياً للعقد' AFTER `shift_contract`,
ADD COLUMN `total_contract_permonth` INT(11) DEFAULT 0 COMMENT 'وحدات العمل في الشهر للعقد' AFTER `equip_total_contract_daily`,
ADD COLUMN `total_contract_units` INT(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد' AFTER `total_contract_permonth`;

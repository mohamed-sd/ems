-- تغيير اسم العمود من equip_target_per_month إلى shift_hours
-- لأنه يمثل ساعات الوردية وليس الهدف الشهري

ALTER TABLE `contractequipments` 
CHANGE COLUMN `equip_target_per_month` `shift_hours` INT(11) DEFAULT 0 COMMENT 'إجمالي ساعات الوردية';

-- إضافة حقول أوقات الورديات لجدول contractequipments

ALTER TABLE `contractequipments` 
ADD COLUMN `shift1_start` TIME DEFAULT NULL COMMENT 'وقت بداية الوردية الأولى' AFTER `equip_unit`,
ADD COLUMN `shift1_end` TIME DEFAULT NULL COMMENT 'وقت نهاية الوردية الأولى' AFTER `shift1_start`,
ADD COLUMN `shift2_start` TIME DEFAULT NULL COMMENT 'وقت بداية الوردية الثانية' AFTER `shift1_end`,
ADD COLUMN `shift2_end` TIME DEFAULT NULL COMMENT 'وقت نهاية الوردية الثانية' AFTER `shift2_start`;

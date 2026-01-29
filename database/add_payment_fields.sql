-- إضافة الحقول المالية الجديدة لجدول contracts
-- نفذ هذا الاستعلام في phpMyAdmin أو MySQL لإضافة الحقول إلى قاعدة البيانات

ALTER TABLE `contracts` 
ADD COLUMN `price_currency_contract` VARCHAR(20) DEFAULT NULL COMMENT 'عملة العقد' AFTER `witness_two`,
ADD COLUMN `paid_contract` VARCHAR(100) DEFAULT NULL COMMENT 'المبلغ المدفوع' AFTER `price_currency_contract`,
ADD COLUMN `payment_time` VARCHAR(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)' AFTER `paid_contract`,
ADD COLUMN `guarantees` TEXT DEFAULT NULL COMMENT 'الضمانات' AFTER `payment_time`,
ADD COLUMN `payment_date` DATE DEFAULT NULL COMMENT 'تاريخ الدفع' AFTER `guarantees`;

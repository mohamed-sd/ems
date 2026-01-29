-- إضافة عمود user_id إلى جدول contract_notes لتسجيل المستخدم الذي أضاف الملاحظة

-- التحقق من وجود الجدول أولاً
-- ALTER TABLE contract_notes ADD COLUMN IF NOT EXISTS user_id INT(11) NULL DEFAULT 0;

ALTER TABLE `contract_notes` 
ADD COLUMN `user_id` INT(11) NULL DEFAULT 0 AFTER `note`,
ADD INDEX `idx_user_id` (`user_id`);

-- تحديث: إذا كان العمود موجوداً مسبقاً، استخدم هذا الأمر بدلاً من الأمر السابق:
-- ALTER TABLE `contract_notes` MODIFY COLUMN `user_id` INT(11) NULL DEFAULT 0;

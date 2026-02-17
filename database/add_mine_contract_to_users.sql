-- إضافة حقول mine_id و contract_id لجدول users
-- تاريخ: 17 فبراير 2026
-- الغرض: ربط مدير الموقع بمنجم وعقد محددين

-- إضافة حقل mine_id
ALTER TABLE `users` 
ADD COLUMN `mine_id` INT(11) NULL DEFAULT 0 COMMENT 'معرف المنجم لمدير الموقع' AFTER `project_id`;

-- إضافة حقل contract_id
ALTER TABLE `users` 
ADD COLUMN `contract_id` INT(11) NULL DEFAULT 0 COMMENT 'معرف العقد لمدير الموقع' AFTER `mine_id`;

-- إضافة مفاتيح فهرسة لتحسين الأداء
ALTER TABLE `users` 
ADD INDEX `idx_mine_id` (`mine_id`),
ADD INDEX `idx_contract_id` (`contract_id`);

-- إضافة قيود foreign key (اختياري - حسب الحاجة)
-- ALTER TABLE `users` 
-- ADD CONSTRAINT `fk_users_mine` FOREIGN KEY (`mine_id`) REFERENCES `mines` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
-- ADD CONSTRAINT `fk_users_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ملاحظة: 
-- - الحقول الجديدة تقبل NULL وقيمة افتراضية 0
-- - تستخدم فقط عندما يكون role = 5 (مدير موقع)
-- - يجب تحديد المشروع أولاً، ثم المنجم، ثم العقد

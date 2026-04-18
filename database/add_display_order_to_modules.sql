-- إضافة عمود الترتيب في جدول modules
-- Add display_order column to modules table

-- التحقق من وجود العمود وإضافته إذا لم يكن موجوداً
ALTER TABLE `modules` 
ADD COLUMN IF NOT EXISTS `display_order` INT(11) DEFAULT 0 
COMMENT 'ترتيب العرض في القوائم';

-- تحديث القيم الموجودة لتكون مرتبة حسب ID
UPDATE `modules` SET `display_order` = `id` * 10 WHERE `display_order` = 0;

-- إضافة فهرس للترتيب لتحسين الأداء
CREATE INDEX IF NOT EXISTS `idx_display_order` ON `modules` (`display_order`);

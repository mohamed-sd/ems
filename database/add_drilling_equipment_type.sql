-- إضافة نوع معدات الخرامات (drilling machines) إلى جدول equipments_types
-- هذا النوع مطلوب لتشغيل النوع 3 في نظام التايم شيت

-- التحقق من وجود النوع أولاً وإضافته إذا لم يكن موجوداً
INSERT INTO `equipments_types` (`type_name`, `form`, `status`, `created_at`, `updated_at`)
SELECT 'خرامة', '3', 'active', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `equipments_types` WHERE `form` = '3' AND `status` = 'active'
);

-- تحديث النوع إذا كان موجوداً لكن غير نشط
UPDATE `equipments_types` 
SET `status` = 'active', `updated_at` = NOW()
WHERE `form` = '3' AND `status` != 'active';

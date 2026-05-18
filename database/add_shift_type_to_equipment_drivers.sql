-- إضافة حقل نوع الوردية في جدول تشغيل السائقين
-- D = نهاري فقط
-- N = ليلي فقط
-- B = نهاري + ليلي

ALTER TABLE equipment_drivers
ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER end_date;

-- تحديث السجلات القديمة إلى نهاري + ليلي
UPDATE equipment_drivers
SET shift_type = 'B'
WHERE shift_type IS NULL OR shift_type = '';

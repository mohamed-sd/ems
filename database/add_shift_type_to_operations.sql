-- إضافة حقل نوع الوردية في جدول التشغيل
-- D = نهاري فقط
-- N = ليلي فقط
-- B = نهاري + ليلي

ALTER TABLE operations
ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER shift_hours;

-- (اختياري) تحديث السجلات القديمة إلى نهاري + ليلي
UPDATE operations
SET shift_type = 'B'
WHERE shift_type IS NULL OR shift_type = '';

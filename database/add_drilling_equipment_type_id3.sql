-- إضافة نوع المعدة: خرامة (Drilling Equipment) بـ id = 3
-- Add equipment type: Drilling Machine with id = 3

USE equipation_manage;

-- حذف السجل إذا كان موجوداً (للتأكد من إعادة الإدراج بنفس ID)
-- Delete if exists to ensure clean insert
DELETE FROM equipments_types WHERE id = 3;

-- إضافة نوع الخرامة بـ id = 3
-- Insert drilling equipment type with id = 3
INSERT INTO equipments_types (id, type_name, form, status, created_at, updated_at)
VALUES (3, 'خرامة', '3', 'active', NOW(), NOW());

-- التحقق من الإضافة
-- Verify the insert
SELECT * FROM equipments_types ORDER BY id;

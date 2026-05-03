-- إضافة حقلي عدد الحفر المخرمة وأعماق الحفر لجدول timesheet
-- تاريخ الإنشاء: 2026-05-02
-- الوصف: إضافة أعمدة لتسجيل عدد الحفر وأعماقها للخرمات في نظام التايم شيت

ALTER TABLE timesheet ADD COLUMN drilling_holes_count INT DEFAULT 0 AFTER meters_count;
ALTER TABLE timesheet ADD COLUMN drilling_depth DECIMAL(10,2) DEFAULT 0.00 AFTER drilling_holes_count;


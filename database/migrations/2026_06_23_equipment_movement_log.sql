-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-23 — سجل «تحركات الآلية» (Equipment Movement Log)
-- ════════════════════════════════════════════════════════════════════════════
-- نبني على fleet_equipment_history القائم (لا جدول جديد) ونغذّيه تلقائيًا.
-- مهم: طبّق بعميل utf8mb4 (تسميات الأحداث عربية) — SET NAMES يضمن ذلك عند mysql.exe < file.
SET NAMES utf8mb4;

-- (أ) توسيع سجل المعدة القائم
ALTER TABLE fleet_equipment_history
  ADD COLUMN from_value   VARCHAR(150) NULL AFTER note,
  ADD COLUMN to_value     VARCHAR(150) NULL AFTER from_value,
  ADD COLUMN operation_id INT NULL      AFTER to_value;

CREATE INDEX idx_feh_equipment_date
  ON fleet_equipment_history (equipment_id, event_date);

-- (ب) أعمدة تتبّع الإضافة في جدول المعدات
ALTER TABLE equipments
  ADD COLUMN created_by INT NULL      COMMENT 'منشئ المعدة' AFTER company_id,
  ADD COLUMN created_at DATETIME NULL COMMENT 'تاريخ إضافة المعدة' AFTER created_by;

-- (ج) تعبئة رجعية تقديرية للمعدات القديمة: أقدم تاريخ تشغيل معروف كبديل لتاريخ الإضافة
UPDATE equipments e
SET e.created_at = (
  SELECT MIN(o.start) FROM operations o WHERE o.equipment = e.id AND o.start IS NOT NULL
)
WHERE e.created_at IS NULL;

-- (د) بذرة حدث «إضافة للنظام» للمعدات القديمة (created_by يبقى NULL = غير معروف)
INSERT INTO fleet_equipment_history (company_id, equipment_id, event_date, event_type, note, created_by)
SELECT e.company_id, e.id, COALESCE(e.created_at, NOW()), 'إضافة للنظام', 'تعبئة رجعية تقديرية', NULL
FROM equipments e
WHERE NOT EXISTS (
  SELECT 1 FROM fleet_equipment_history h
  WHERE h.equipment_id = e.id AND h.event_type = 'إضافة للنظام'
);

-- =====================================================================
--  Fleet Model — تحويل «المورد الافتراضي» إلى إدخال يدوي حرّ (نصّي)
--  بدل الربط بجدول suppliers عبر default_supplier_id.
--  - يُضاف عمود نصّي default_supplier_name (الشاشة تكتب/تقرأ منه).
--  - يبقى default_supplier_id موجوداً (مهمل/NULL) لعدم الكسر.
--  آمن للتشغيل المتكرر — MariaDB 10.4+
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `fleet_model`
    ADD COLUMN IF NOT EXISTS `default_supplier_name` VARCHAR(150) NULL DEFAULT NULL AFTER `default_supplier_id`;

-- نقل لمرّة واحدة (غير متلف): تحويل المورد المربوط سابقاً (id) إلى نصّ
-- حتى لا تفقد الموديلات القائمة مورّدها الافتراضي عند التحوّل للإدخال اليدوي.
UPDATE `fleet_model` fm
JOIN `suppliers` s ON s.`id` = fm.`default_supplier_id`
SET fm.`default_supplier_name` = s.`name`
WHERE (fm.`default_supplier_name` IS NULL OR fm.`default_supplier_name` = '')
  AND fm.`default_supplier_id` IS NOT NULL;

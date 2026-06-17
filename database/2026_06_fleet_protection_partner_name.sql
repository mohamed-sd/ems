-- =====================================================================
--  fleet_equipment_protection — تحويل «المنفّذ/المورد» إلى إدخال يدوي حرّ
--  بدل الربط بجدول suppliers عبر partner_id.
--  - يُضاف عمود نصّي partner_name.
--  - يبقى partner_id موجوداً (مهمل/NULL) لعدم الكسر.
--  آمن للتشغيل المتكرر — MariaDB 10.4+
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `fleet_equipment_protection`
    ADD COLUMN IF NOT EXISTS `partner_name` VARCHAR(150) NULL DEFAULT NULL AFTER `partner_id`;

-- نقل لمرّة واحدة (غير متلف) للبيانات القائمة إن وُجدت
UPDATE `fleet_equipment_protection` p
JOIN `suppliers` s ON s.`id` = p.`partner_id`
SET p.`partner_name` = s.`name`
WHERE (p.`partner_name` IS NULL OR p.`partner_name` = '')
  AND p.`partner_id` IS NOT NULL;

-- ═══════════════════════════════════════════════════════════════════════════
-- EMS · أعمدة المزامنة لجدول timesheet (تطبيق مدير الموقع Offline-First)
-- @date 2026-06-07
--
-- إضافات غير كاسرة (additive) لتمكين:
--   - client_uuid : معرّف محلي فريد من الجهاز → idempotency للرفع الدفعي (منع التكرار).
--   - updated_at  : طابع زمني للتعديل → السحب التزايدي (pull) وحلّ التعارض (الأحدث يفوز).
--
-- الشاشات الويب القائمة تستخدم قائمة أعمدة صريحة في INSERT/SELECT، فلا تتأثّر.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `timesheet`
  ADD COLUMN IF NOT EXISTS `client_uuid` VARCHAR(64) NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `client_uuid`;

-- فهرس فريد للـ client_uuid (تسمح MySQL/InnoDB بقيم NULL متعددة).
CREATE UNIQUE INDEX `uq_timesheet_client_uuid` ON `timesheet` (`client_uuid`);
CREATE INDEX `idx_timesheet_updated_at` ON `timesheet` (`updated_at`);

-- ═══════════════════════════════════════════════════════════════════════════
-- K9-M2a (سياسة «الأرشفة لا الحذف» · خطة K9 §7) — دفعة البنية قبل الشاشات:
-- إضافة أعمدة الحذف الناعم للجداول الأحد عشر لوحدة النقل (كلها بلا soft —
-- مؤكد بالجرد 2026-07-08). أعمدة إضافية فورية (Instant ADD) — لا مساس بقائم.
-- تفتح softDelete للنمطين المرصودين: حذف master الأرشفي وحذف الابن المفرد.
-- Rollback: backups/M2a_down_transport_soft_delete.sql (مُختبَر على نسخ الأحد عشر).
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `transfer_attachments` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_cost_lines` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_cost_rules` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_events` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_lines` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_orders` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_permits` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_requests` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `transfer_types` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `trs_locations` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;
ALTER TABLE `trs_notifications` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `deleted_by` INT NULL DEFAULT NULL;

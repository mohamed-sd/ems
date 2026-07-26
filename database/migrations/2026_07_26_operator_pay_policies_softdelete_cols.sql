-- ═══════════════════════════════════════════════════════════════════════════
-- استكمالُ أعمدة الحذف الناعم في operator_pay_policies — 2026-07-26
-- ───────────────────────────────────────────────────────────────────────────
-- عقدُ البوابة (TenantDb::softDelete) ثلاثيٌّ: is_deleted/deleted_at/deleted_by
-- — والجدول أُنشئ بـdeleted_at وحدها فرفضت البوابة الإيقاف (قيس فعلًا:
-- «Unknown column 'is_deleted'» في دخنة الشاشة). الاستكمال بترحيلٍ جديد
-- لا بتعديل المطبَّق (الـchecksum).
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `operator_pay_policies`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `note`,
  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`;

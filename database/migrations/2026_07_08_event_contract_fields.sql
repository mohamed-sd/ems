-- ═══════════════════════════════════════════════════════════════════════════
-- K2 (ADR-10 · خطة انتقال الناقل §8.4 خطوة 1 · العقد المؤسسي §9)
-- تثبيت حقول العقد على القناة القائمة fin_financial_events — أعمدة nullable
-- **إضافية فقط**: لا مساس بأي عمود قائم (ENUM event_type يبقى كما هو —
-- الأنواع المنقّطة domain.entity.action في العمود الجديد event_key؛
-- قرار الإقرار §7-1 في docs/PHASE1_PLAN_ar.md).
--
-- ⚠ تجهيز مخطّطٍ فقط: الحقول تبقى NULL حتى يكتبها الناشر (K3) — العقد لا
--   يُعدّ «مفعَّلًا» بمجرد إضافة الأعمدة.
-- Rollback: database/backups/K2_down_event_contract_fields.sql (مُختبَر على نسخة).
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `fin_financial_events`
  ADD COLUMN `event_key` VARCHAR(64) NULL DEFAULT NULL
    COMMENT 'Event Type المنقط domain.entity.action (عقد §9) — يحوكم بسجل الأنواع لا بتوسيع ENUM'
    AFTER `event_type`,
  ADD COLUMN `category` VARCHAR(20) NULL DEFAULT NULL
    COMMENT 'Event Category (عقد §9): operational/financial/hr/fleet/maintenance/commercial/analytics'
    AFTER `event_key`,
  ADD COLUMN `quantity` DECIMAL(18,4) NULL DEFAULT NULL
    COMMENT 'الكمية إن كان الحدث كميًا (عقد §9)'
    AFTER `amount`,
  ADD COLUMN `unit` VARCHAR(16) NULL DEFAULT NULL
    COMMENT 'وحدة القياس اللاتينية hour/ton/meter/km/liter/unit (عقد §9)'
    AFTER `quantity`,
  ADD COLUMN `correlation_id` VARCHAR(64) NULL DEFAULT NULL
    COMMENT 'معرّف سلسلة الأثر طرفًا لطرف (عقد §9) — يكتبه الناشر K3',
  ADD COLUMN `idempotency_key` VARCHAR(64) NULL DEFAULT NULL
    COMMENT 'مفتاح عطالة الأثر — فريد لكل عملية مصدرية (عقد §9)؛ NULL للصفوف السابقة للعقد',
  ADD COLUMN `schema_version` SMALLINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'إصدار مخطط الحدث (عقد §9) — يكتبه الناشر K3',
  ADD UNIQUE KEY `uq_ffe_idempotency` (`idempotency_key`),
  ADD KEY `idx_ffe_correlation` (`correlation_id`),
  ADD KEY `idx_ffe_event_key` (`event_key`);

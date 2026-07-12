-- ═══════════════════════════════════════════════════════════════════════════
-- A-1 (المسار أ · قابلية التوزيع + عقد الحدث العكسي) — نمط K2 الكامل
-- ───────────────────────────────────────────────────────────────────────────
-- تجهيز مخطّطٍ فقط — أعمدة/جدول **إضافية**، لا مساس بأي عمود قائم ولا بأي كود.
--   • event_uuid: معرّف كوني لكل حدث (توزيع/تتبّع). default قاعدي (UUID()) ⇒ كل
--     إدراج (ناشر/بوابة/خام) يملؤه تلقائيًّا بلا لمس كود الناقل (الخط الأحمر مصون).
--     يُضاف nullable ثم SET DEFAULT منفصلًا (UUID() ممنوع داخل ADD COLUMN تحت
--     binlog — DDL يُسجَّل STATEMENT)، ثم backfill per-row (آمن تحت ROW binlog).
--   • event_status + reverses_event_id: عقد الحدث العكسي (C6) — مخطط يرسي البنية
--     قبل بناء المنطق (تغييره بعد هجرة إدارات = كاسر ⇒ عقد بالمعيار الفاصل).
--     ★ المنطق (النقض المعوِّض) مؤجَّل بالقيد الرابع — لا يُبنى قبل أول مستهلك جديد.
--   • ems_processed_events: عطالة استهلاك موزّعة — عقدٌ يُفعَّل عند تعدّد الموزّعات
--     (الضمان الحالي «مرّة واحدة» قائمٌ بالمؤشّر + ems_event_deliveries؛ هذا الجدول
--     احتياطٌ للتوسّع الأفقي، لا بديلٌ عنه اليوم).
-- Rollback: database/backups/A1_down_distributability_and_reversal_contract.sql
--           (مُختبَر حيًّا: up→down→up بمطابقة).
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) الأعمدة الإضافية (nullable/بمُعطىً حرفيّ — الـALTER حتميٌّ آمن للسجلّ)
ALTER TABLE `fin_financial_events`
  ADD COLUMN `event_uuid` CHAR(36) NULL DEFAULT NULL
    COMMENT 'معرّف الحدث الكوني (توزيع/تتبّع) — default قاعدي UUID() يُضبط أدناه'
    AFTER `sync_uuid`,
  ADD COLUMN `event_status` ENUM('active','reversed') NOT NULL DEFAULT 'active'
    COMMENT 'محور دورة حياة الناقل (منفصلٌ عن state سير المالية): active افتراضًا · reversed إن نقضه حدثٌ معوِّض'
    AFTER `state`,
  ADD COLUMN `reverses_event_id` INT NULL DEFAULT NULL
    COMMENT 'إن كان هذا الحدث معوِّضًا: id الحدث الذي ينقضه (عقد C6 — المنطق مؤجَّل)'
    AFTER `event_status`,
  ADD KEY `idx_ffe_reverses` (`reverses_event_id`);

-- 2) default قاعدي لـevent_uuid (منفصل: UUID() غير مسموح داخل ADD COLUMN)
ALTER TABLE `fin_financial_events` ALTER COLUMN `event_uuid` SET DEFAULT (UUID());

-- 3) تعبئة الصفوف القائمة (per-row UUID — آمن تحت ROW binlog)
UPDATE `fin_financial_events` SET `event_uuid` = UUID() WHERE `event_uuid` IS NULL;

-- 4) فهرس فريد بعد التعبئة (يمنع تكرار المعرّف الكوني)
ALTER TABLE `fin_financial_events` ADD UNIQUE KEY `uq_ffe_event_uuid` (`event_uuid`);

-- 5) جدول عطالة الاستهلاك الموزّع (عقد — فارغ حتى أول موزّعٍ ثانٍ)
CREATE TABLE IF NOT EXISTS `ems_processed_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consumer` VARCHAR(64) NOT NULL COMMENT 'اسم المستهلك (يوافق ems_event_consumers.consumer)',
  `event_uuid` CHAR(36) NOT NULL COMMENT 'معرّف الحدث الكوني المُستهلَك',
  `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_processed` (`consumer`, `event_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عطالة استهلاك موزّعة (عقد قابلية التوزيع) — يُفعَّل عند تعدّد الموزّعات';

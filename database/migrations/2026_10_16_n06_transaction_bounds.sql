-- ═══════════════════════════════════════════════════════════════════════════
-- N-06 حدود المعاملة (PLAN-05 §3-① · البوابة الأولى) — سد أركان ②③ البنيوية
-- ───────────────────────────────────────────────────────────────────────────
-- ① الكتابة الذرية (حدث + آثار + صادر): قائمة سلفًا — EffectFanout داخل معاملة
--    المستدعي + عطالة fin_event_links + UNIQUE idempotency_key. لا DDL هنا.
-- ② إعادة تصاعدية حتى خمس مرات ثم Dead-Letter بإنذار:
--    يُضاف عمود next_retry_at لجدولة التصاعد (2^attempts دقيقة) — والمنطق في
--    EventDispatcher (الحد 5 + إنذار fin_notifications عند العزل).
-- ③ جدول المعالَجات بمفتاح (المستهلك × المستند × الأثر):
--    processed_operations جديد — ems_processed_events القائم يبقى (مفتاحه
--    consumer × event_uuid — عقد التوزيع الأفقي) ولا يُخلط بينهما.
-- ④ التعويض: العقد البنيوي (event_status/reverses_event_id) قائم منذ
--    2026_07_12 — والمنطق في app/Services/CompensationService.php (لا DDL).
-- كل الأوامر idempotent (تُفحص information_schema قبل ALTER).
-- ═══════════════════════════════════════════════════════════════════════════

-- ② عمود جدولة إعادة المحاولة التصاعدية (idempotent عبر إجراء فحص)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ems_event_deliveries' AND COLUMN_NAME = 'next_retry_at');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `ems_event_deliveries` ADD COLUMN `next_retry_at` DATETIME NULL DEFAULT NULL COMMENT ''N-06: موعد المحاولة التالية (تصاعد 2^attempts دقيقة) — NULL = مستحقة الآن'' AFTER `last_error`',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ③ جدول المعالَجات بمفتاح (المستهلك × المستند × الأثر) — يمنع تكرار
--    المستهلكين عند إعادة التشغيل ولو تعدد الحدث للمستند الواحد
CREATE TABLE IF NOT EXISTS `processed_operations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consumer` VARCHAR(64) NOT NULL COMMENT 'اسم المستهلك (يوافق ems_event_consumers.consumer)',
  `doc_type` VARCHAR(64) NOT NULL COMMENT 'نوع المستند المصدر (fin_unit_record · claim · …)',
  `doc_id` BIGINT UNSIGNED NOT NULL COMMENT 'معرّف المستند المصدر',
  `effect_kind` VARCHAR(64) NOT NULL COMMENT 'نوع الأثر المعالَج (revenue · supplier_due · …)',
  `event_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'الحدث الذي حمل المعالجة (تتبع لا مفتاح)',
  `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_processed_op` (`consumer`, `doc_type`, `doc_id`, `effect_kind`),
  KEY `ix_po_doc` (`doc_type`, `doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-06 ركن ③: عطالة المستهلك على مستوى (المستند × الأثر) — Insert-only';

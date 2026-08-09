-- update0013 · وصلُ محرّكِ التوجيهِ بناقلِ الأحداث
-- ═══════════════════════════════════════════════════════════════════════════
-- OBL-0002: «◆ التوجيهُ يقع **بحدثٍ منشورٍ لا بنداءٍ مباشرٍ** — والإدارةُ
--   المصدرُ تنشر والماليةُ تستهلك.»
--
-- ◆ لماذا جدولُ ربطٍ لا شرطٌ في الكود:
--   المصفوفةُ تسمّي المُطلِقَ بالعربية («طلبُ شراءٍ تشغيلي») والناقلُ يحمل مفتاحًا
--   تقنيًّا (`expense.purchase.recorded`). والوصلُ بينهما **قرارُ تشغيلٍ يتغير**
--   بتغيّرِ مفاتيحِ الأحداث، فلا يُدفن في `switch` داخلَ خدمة. وهو أيضًا ما
--   يجعل «صفرُ حدثٍ ماليٍّ بلا مُطلِقٍ معرَّف» (OBL-0266) **قابلًا للقياس**:
--   يُمسح الحيُّ ويُقارَن بالجدول.
--
-- ◆ وما لا مفتاحَ له لا يسقط: الحكمُ الجامعُ RT-17 يلتقطه بالإدارةِ ونوعِ
--   الواقعة (OBL-0020) — فالخريطةُ تُدقِّق ولا تَحجب.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `fin_routing_event_map` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `event_key`     VARCHAR(80)  NOT NULL COMMENT 'مفتاحُ الحدثِ في الناقل — أو % للكل',
  `source_module` VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'قيدٌ إضافيٌّ — فارغٌ = أيُّ إدارة',
  `route_code`    VARCHAR(8)   NOT NULL COMMENT 'RT-01..RT-35',
  `priority`      SMALLINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'الأدقُّ أولًا',
  `note`          VARCHAR(300) NOT NULL DEFAULT '',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_map` (`company_id`, `event_key`, `source_module`),
  KEY `ix_lookup` (`event_key`, `active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 OBL-0002 — ربطُ مفاتيحِ الناقلِ بمساراتِ التوجيه';

-- عمودُ التتبعِ في سجلِّ التوجيه: أيُّ حدثٍ في الناقلِ ولّد هذا التوجيه.
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_routing_log'
      AND COLUMN_NAME = 'financial_event_id') = 0,
  'ALTER TABLE `fin_routing_log` ADD COLUMN `financial_event_id` BIGINT UNSIGNED NULL
     COMMENT ''fin_financial_events.id — الحدثُ الذي استُهلك'' AFTER `event_ref`,
     ADD KEY `ix_fev` (`financial_event_id`)',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

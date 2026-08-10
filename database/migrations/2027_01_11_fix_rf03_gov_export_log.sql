-- ═══════════════════════════════════════════════════════════════════════════
-- 2027_01_11_fix_rf03_gov_export_log.sql
-- FIX-01 · RF-03 خطوة ④ (FIXA-0030) — «صنّفْ فعلَ التصديرِ كتابةَ حوكمةٍ ويكتب
-- سجلًّا بتسعةِ بنودٍ ومنها الحقولُ المستبعَدة».
--
-- البنودُ التسعة:
--   ① exported_by     من صدّر
--   ② actor_capacity  بصفتِه (لا باسمِه — الصفةُ هي المساءَلة)
--   ③ entity_key      الكيانُ المُصدَّر
--   ④ screen_code     الشاشةُ المالكةُ لصلاحيته
--   ⑤ columns_text    الأعمدةُ المُصدَّرةُ فعلًا
--   ⑥ blocked_text    ◆ الأعمدةُ المستبعَدةُ لغيابِ المنح
--   ⑦ filters_text    المرشِّحاتُ والنطاقاتُ المطبَّقة
--   ⑧ row_count       عددُ الصفوف
--   ⑨ exported_at     الوقتُ (+ fmt الصيغة)
--
-- عطالة: CREATE TABLE IF NOT EXISTS — يُعاد تشغيلُه بلا أثر.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `gov_export_log` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT(11)         NOT NULL DEFAULT 0,
  `exported_by`    INT(11)         NOT NULL DEFAULT 0,
  `actor_capacity` VARCHAR(120)    NOT NULL DEFAULT '',
  `entity_key`     VARCHAR(64)     NOT NULL DEFAULT '',
  `screen_code`    VARCHAR(190)    NOT NULL DEFAULT '',
  `columns_text`   TEXT            NULL,
  `blocked_text`   TEXT            NULL,
  `filters_text`   TEXT            NULL,
  `row_count`      INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `fmt`            VARCHAR(12)     NOT NULL DEFAULT 'xlsx',
  `exported_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gel_company_time` (`company_id`, `exported_at`),
  KEY `idx_gel_actor`        (`exported_by`, `exported_at`),
  KEY `idx_gel_entity`       (`entity_key`, `exported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RF-03 · سجلُّ التصديرِ الحوكميِّ بتسعةِ بنودٍ ومنها المستبعَد';

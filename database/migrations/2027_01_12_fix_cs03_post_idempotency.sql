-- ═══════════════════════════════════════════════════════════════════════════
-- 2027_01_12_fix_cs03_post_idempotency.sql
-- FIX-01 · CS-07 (FIXA-0008) — «العطالةُ بمفتاحٍ مركَّبٍ لكلِّ فعلٍ ذي أثر:
-- إعادةُ النداءِ ترجع مرجعَ الأثرِ الأولِ ولا تولّد ثانيًا · والمفتاحُ من محتوى
-- الطلبِ لا من وقتِه».
--
-- ◆ الفريدُ على المفتاحِ هو الحكم — لا فحصُ التطبيقِ وحدَه. فحصٌ ثم إدراجٌ في
--   طلبين متزامنين يمرّان معًا؛ والفريدُ يرفض الثانيَ في القاعدةِ مهما تزامنا.
-- ◆ ولا يُستعمل ‎ems_sequences‎: هذا سجلُّ عطالةٍ لا مُرقِّم.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `ems_post_idempotency` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idem_key`      CHAR(40)        NOT NULL,
  `action_code`   VARCHAR(120)    NOT NULL DEFAULT '',
  `actor_user_id` INT(11)         NOT NULL DEFAULT 0,
  `result_ref`    VARCHAR(190)    NOT NULL DEFAULT '',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_idem_key` (`idem_key`),
  KEY `idx_post_idem_action` (`action_code`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CS-07 · عطالةُ معالجاتِ POST بمفتاحٍ من محتوى الطلب';

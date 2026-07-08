-- ═══════════════════════════════════════════════════════════════════════════
-- K8 (خطة المرحلة 1 §2) — جدول المتتاليات الذرّية لخدمة الترقيم الخادمي.
-- ServerId::nextNo() يخصّص أرقام أعمالٍ متسلسلةً per-scope بقفل صفٍّ ذرّي
-- (بديل fin_gen_code السباقي COUNT(*)+1 — القديم يبقى لشاشاته حتى هجرتها،
-- والناشر K3 يستعمل بادئةً منفصلة فلا تداخل بين المسارين).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE `ems_sequences` (
  `scope`      VARCHAR(120)    NOT NULL COMMENT 'نطاق المتتالية، مثال: fin_financial_events:EV:4',
  `next_val`   BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='K8: متتاليات ذرّية للترقيم الخادمي (ServerId::nextNo)';

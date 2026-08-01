-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑩ · SEC-29 — سجل فروق الظل (المرحلة ③ من §13)
-- «يُحسب المشتق ويُقارن بالقديم عند كل طلب بلا تفعيل — والقديم يقرِّر» ·
-- معيار الانتقال: صفر فرق 14 يومًا متصلة — وهذا الجدول ميزانه.
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS perm_shadow_diffs (
  diff_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id       INT NOT NULL,
  user_id          INT NOT NULL,
  module_code      VARCHAR(120) NOT NULL,
  action           VARCHAR(40) NOT NULL,
  permission_code  VARCHAR(120) NOT NULL,
  scope_rule       VARCHAR(120) NOT NULL,
  legacy_decision  TINYINT(1) NOT NULL,
  derived_decision TINYINT(1) NOT NULL,
  detail           VARCHAR(255) NULL,
  resolved         TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'حُقق وأُصلح سببه (قالب أو تحويل)',
  at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (diff_id),
  KEY idx_psd_at (at),
  KEY idx_psd_user (company_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §13 المرحلة ③: كل فرق سماح/منع/نطاق/سقف صف — والحد صفر لا نسبة';

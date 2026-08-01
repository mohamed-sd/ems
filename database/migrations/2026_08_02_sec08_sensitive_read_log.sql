-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑧ · SEC-21 — سجل الاطلاع على الحقول الحساسة والمنح
-- «كل قراءة بمنح حساس تُسجَّل في سجل الاطلاع» (SEC-01 §1.1-② · §12) —
-- والجدول مشترك مع TKT-06 (سرية البلاغات الشخصية) فيُبنى هنا مرة واحدة.
-- Insert-only.
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS sensitive_read_log (
  read_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id  INT NOT NULL,
  person_id   INT NOT NULL COMMENT 'القارئ',
  field_code  VARCHAR(120) NOT NULL COMMENT 'الحقل أو المجال المقروء',
  subject_ref VARCHAR(120) NOT NULL COMMENT 'صاحب البيان المقروء (employee:7 · ticket:12 …)',
  grant_ref   VARCHAR(120) NULL COMMENT 'مرجع المنح الذي سوّغ القراءة (GR-… · EX-… · policy)',
  context     VARCHAR(190) NULL COMMENT 'الشاشة أو الخدمة',
  at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (read_id),
  KEY idx_srl_person (company_id, person_id, at),
  KEY idx_srl_subject (subject_ref, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12 + TKT-01: سجل الاطلاع — للإدراج فقط';

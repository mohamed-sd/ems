-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ㉒ · UAT-02/03/04/05 — بنية التجربة الشاملة
-- سجل جولات UAT + وسم البيانات (UAT-2026) + حسابات وضع اختبار الصلاحيات +
-- محضر الأدلة والقرار. البيئة معزولة (DEC-UAT-G) والتنظيف بالاستعادة لا الحذف.
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS uat_runs (
  run_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id   INT NOT NULL,
  tag          VARCHAR(20) NOT NULL DEFAULT 'UAT-2026' COMMENT 'وسم التمييز — للتقارير لا للحذف',
  phase        ENUM('hardening','functional','break','close','load','decision') NOT NULL,
  title        VARCHAR(190) NOT NULL,
  state        ENUM('planned','running','passed','failed','blocked') NOT NULL DEFAULT 'planned',
  executor     VARCHAR(120) NULL COMMENT '§12.1: مستخدمو الإدارات — والفريق يراقب ويوثق',
  metrics_json JSON NULL,
  started_at   DATETIME NULL,
  finished_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (run_id),
  KEY idx_uat_phase (company_id, phase, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UAT-01: جولات التجربة — التحصين قبل كل تجربة';

CREATE TABLE IF NOT EXISTS uat_evidence (
  ev_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id      INT UNSIGNED NOT NULL,
  criterion   VARCHAR(120) NOT NULL COMMENT 'رمز المعيار: H1..H6 · S1.. · الشواهد الأربعة عشر',
  expected    VARCHAR(255) NULL,
  actual      VARCHAR(255) NULL,
  result      ENUM('pass','fail','na') NOT NULL DEFAULT 'na',
  evidence_ref VARCHAR(255) NULL COMMENT 'لقطة أو مرجع سجل',
  at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ev_id),
  KEY idx_uatev_run (run_id, criterion),
  CONSTRAINT fk_uatev_run FOREIGN KEY (run_id) REFERENCES uat_runs (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UAT-14: الشواهد الأربعة عشر — موثقة كلها';

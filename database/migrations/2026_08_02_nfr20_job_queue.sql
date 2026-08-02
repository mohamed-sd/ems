-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑳ · N-24 (NFR-05) — طابور المعالجة
-- «محاولات تصاعدية وسجل فشل ظاهر» — وN-06 (حدود المعاملة من PLAN-02) غير
-- معرَّف في المستودع بسابقة موثقة، فالطابور بحدود معاملة لكل مهمة/دفعة.
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS ems_job_queue (
  job_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id      INT NOT NULL,
  job_type        VARCHAR(60) NOT NULL COMMENT 'payroll_bind · periodic_cron · bank_recon · batch_loop …',
  payload_json    JSON NULL,
  state           ENUM('queued','processing','done','failed','dead') NOT NULL DEFAULT 'queued',
  attempts        INT NOT NULL DEFAULT 0,
  max_attempts    INT NOT NULL DEFAULT 3,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'التصاعد: 1د ثم 5د ثم 25د — بساعة القاعدة',
  progress_done   INT NOT NULL DEFAULT 0,
  progress_total  INT NOT NULL DEFAULT 0,
  batch_failures  JSON NULL COMMENT 'NFR-06: فشل دفعة لا يسقط الباقي — يسجَّل هنا ظاهرًا',
  last_error      VARCHAR(500) NULL COMMENT 'سجل الفشل الظاهر — لا فشل صامت',
  created_by      INT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at      DATETIME NULL,
  finished_at     DATETIME NULL,
  PRIMARY KEY (job_id),
  KEY idx_jq_claim (state, next_attempt_at),
  KEY idx_jq_company (company_id, state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-24: «قيد المعالجة» ثم إشعار الاكتمال — والصفحة لا تتجمد أبدًا';

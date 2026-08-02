-- ACT-01 v6 §8 — سجلُّ الأثر المطبَّق: ImpactResolver يكتب هنا ما أخطر وما علَّم
-- «فلا لوحةَ تبقى قديمةً بعد فعلٍ يمسّها» — اللوحاتُ والعداداتُ تقرأ منه
CREATE TABLE IF NOT EXISTS action_impact_log (
  il_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  action_code VARCHAR(80) NOT NULL,
  impacted_type ENUM('org_unit','person','party','screen') NOT NULL,
  impacted_ref VARCHAR(64) NOT NULL,
  effect ENUM('notify','counter','data_change','state_change') NOT NULL,
  subject_ref VARCHAR(120) NULL,
  actor_person_id INT NULL,
  seen TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (il_id),
  KEY ix_ail_target (company_id, impacted_type, impacted_ref, seen),
  KEY ix_ail_action (action_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

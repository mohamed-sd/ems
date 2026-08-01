-- ═══════════════════════════════════════════════════════════════════════════
-- POL-01 إطار السياسات ومسار الوحدة (PLAN-05 §3-⑧⑨ · POL-01 §12)
-- ───────────────────────────────────────────────────────────────────────────
-- «بنية واحدة ومضمون مختلف» — الإطار السباعي لكل إدارة، و«أثران لا أثر واحد»:
-- أولي تشغيلي فور اكتمال السلسلة، ومالي لا يولد إلا باعتماد الإدارة والمالية.
-- unit_effects ليس بديلًا عن fin_event_effects ولا موازيًا: طبقة تدرّج تسبق
-- المال، وعند بوابة الاستحقاق يُنشأ حدث FES وتُملأ fin_event_ref.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `dept_policies` (
  `policy_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `domain` ENUM('sales','suppliers','financiers','workforce','fleet','maintenance','procurement','treasury') NOT NULL,
  `name_ar` VARCHAR(160) NOT NULL,
  `scope_type` ENUM('department','project','contract','employee_type','asset_type') NOT NULL DEFAULT 'department',
  `scope_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = الإدارة كلها',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `state` ENUM('draft','active','superseded') NOT NULL DEFAULT 'active',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_dp_scope` (`company_id`, `domain`, `scope_type`, `scope_id`, `valid_from`),
  KEY `ix_dp_domain` (`company_id`, `domain`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §2: هوية السياسة ونطاقها — ولا سياسة بلا نطاق ومدة، ولا تشغيل لإدارة بلا سياسة نافذة';

CREATE TABLE IF NOT EXISTS `policy_rules` (
  `rule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_id` INT UNSIGNED NOT NULL,
  `rule_kind` VARCHAR(60) NOT NULL,
  `formula_json` JSON NULL DEFAULT NULL,
  `threshold` DECIMAL(18,4) NULL DEFAULT NULL,
  `cap` DECIMAL(18,4) NULL DEFAULT NULL,
  `periodicity` ENUM('daily','weekly','monthly') NULL DEFAULT NULL,
  `valid_from` DATE NULL DEFAULT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `ix_pr_policy` (`policy_id`, `rule_kind`),
  CONSTRAINT `fk_pr_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §2-②: قواعد الإدارة بمعادلاتها وسقوفها';

CREATE TABLE IF NOT EXISTS `impact_matrix` (
  `mx_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_id` INT UNSIGNED NOT NULL,
  `state_code` VARCHAR(40) NOT NULL COMMENT 'حالة الإدارة',
  `party_type` ENUM('client','supplier','operator','company','financier') NOT NULL,
  `effect` ENUM('billable','countable','payable','penalized','none') NOT NULL,
  `derived_from` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصفوفة الأم (CON-02 §5) إن اشتُقت',
  PRIMARY KEY (`mx_id`),
  UNIQUE KEY `uq_mx` (`policy_id`, `state_code`, `party_type`),
  CONSTRAINT `fk_mx_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §8: مصفوفة الأثر — لا حالة بلا أثر معلن لكل طرف، ولا أثر يُستنتج';

CREATE TABLE IF NOT EXISTS `deduction_types` (
  `ded_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_id` INT UNSIGNED NOT NULL,
  `ded_kind` VARCHAR(60) NOT NULL,
  `formula_json` JSON NULL DEFAULT NULL,
  `cap` DECIMAL(18,4) NULL DEFAULT NULL,
  `auto_propose` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'دائمًا 1 — لا خصم آلي الترحيل في أي إدارة',
  PRIMARY KEY (`ded_id`),
  KEY `ix_dt_policy` (`policy_id`),
  CONSTRAINT `fk_dt_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_dt_approval` CHECK (`requires_approval` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §9: أنواع الخصم — يُقترح ويُعتمد، ولا ترحيل مباشرًا بنيويًّا (CHECK)';

CREATE TABLE IF NOT EXISTS `approval_chains` (
  `chain_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_id` INT UNSIGNED NOT NULL,
  `seq_no` TINYINT UNSIGNED NOT NULL,
  `approver_role` ENUM('site','operations','suppliers','workforce','finance') NOT NULL,
  `periodicity` ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly' COMMENT 'الدورية تُختار بالسياسة — لا افتراضية صامتة',
  `sla_hours` INT UNSIGNED NULL DEFAULT NULL COMMENT 'المهلة المعلنة — تجاوزها تصعيد لا إغلاق',
  `skip_if_not_applicable` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`chain_id`),
  UNIQUE KEY `uq_ac_seq` (`policy_id`, `seq_no`),
  CONSTRAINT `fk_ac_policy` FOREIGN KEY (`policy_id`) REFERENCES `dept_policies` (`policy_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §4: سلسلة الاعتماد — لا تُفتح حلقة قبل سابقتها';

CREATE TABLE IF NOT EXISTS `decision_reasons` (
  `reason_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` ENUM('sales','suppliers','financiers','workforce','fleet','maintenance','procurement','treasury','operations') NOT NULL,
  `reason_kind` ENUM('return','reject','state_change','exception') NOT NULL,
  `code` VARCHAR(60) NOT NULL,
  `text_ar` VARCHAR(200) NOT NULL,
  `requires_document` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`reason_id`),
  UNIQUE KEY `uq_dr_code` (`domain`, `reason_kind`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §10: أسباب القرار قائمة محكومة لا نص حر — فتُقاس ويُبنى عليها تقرير';

-- أسباب الإعادة والرفض المعيارية (POL-01 §10)
INSERT IGNORE INTO `decision_reasons` (`domain`, `reason_kind`, `code`, `text_ar`, `requires_document`) VALUES
  ('operations', 'return', 'qty_mismatch',        'كمية غير مطابقة', 0),
  ('operations', 'return', 'time_exceeds_shift',  'زمن يتجاوز الوردية', 0),
  ('operations', 'return', 'stop_owner_missing',  'مسؤول توقف ناقص', 0),
  ('operations', 'return', 'field_ref_missing',   'مرجع ميداني مفقود', 0),
  ('operations', 'return', 'operator_unassigned', 'مشغّل غير مخصَّص', 0),
  ('suppliers',  'return', 'out_of_container',    'معدة خارج الحاوية', 0),
  ('suppliers',  'return', 'charge_no_doc',       'تحميل بلا سند', 1),
  ('workforce',  'return', 'state_unclassified',  'حالة غير مصنَّفة', 0),
  ('workforce',  'return', 'doc_missing',         'مستند ناقص', 1),
  ('operations', 'reject', 'contract_violation',  'مخالفة العقد', 0),
  ('operations', 'reject', 'share_cap_exceeded',  'تجاوز الحصة', 0),
  ('operations', 'reject', 'doc_expired',         'وثيقة منتهية', 0),
  ('operations', 'reject', 'out_of_assignment',   'خارج نطاق التخصيص', 0),
  ('operations', 'reject', 'period_locked',       'فترة مقفلة', 0),
  ('operations', 'state_change', 'attribution_proven_wrong', 'ثبت بمستند أن الإسناد خاطئ', 1),
  ('operations', 'state_change', 'client_correspondence',    'مراسلة عميل', 1),
  ('operations', 'state_change', 'breakdown_report',         'محضر عطل', 1),
  ('operations', 'state_change', 'committee_decision',       'قرار لجنة', 1),
  ('operations', 'exception', 'urgent_operational', 'ظرف تشغيلي طارئ', 0),
  ('operations', 'exception', 'doc_delayed',        'تعذّر استيفاء المستند في وقته', 0),
  ('operations', 'exception', 'senior_decision',    'قرار إدارة عليا', 1);

CREATE TABLE IF NOT EXISTS `unit_effects` (
  `pe_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `source_unit_id` BIGINT UNSIGNED NOT NULL COMMENT 'الوحدة المصدر (fin_unit_records.id أو سجل الوحدة)',
  `domain` ENUM('sales','suppliers','workforce','fleet','financiers','maintenance') NOT NULL,
  `effect_kind` ENUM('production','container_consumption','hours','depreciation','charge','incentive_base') NOT NULL,
  `quantity` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `stage` ENUM('primary','financial') NOT NULL,
  `state` ENUM('Applied','Proposed','Approved','Posted','Reversed') NOT NULL,
  `period` CHAR(7) NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `fin_event_ref` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'حدث FES عند بوابة الاستحقاق — الخيط متصل ولا جدول مال ثانٍ',
  `note` VARCHAR(200) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pe_id`),
  UNIQUE KEY `uq_ue_effect` (`company_id`, `source_unit_id`, `domain`, `effect_kind`, `stage`),
  KEY `ix_ue_stage` (`company_id`, `stage`, `state`, `period`),
  CONSTRAINT `ck_ue_financial_posted` CHECK (
    `stage` <> 'financial' OR `state` <> 'Posted' OR (`approved_by` IS NOT NULL AND `fin_event_ref` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POL-01 §12: طبقة التدرّج التشغيلية — الأولي يكتب في الجداول القائمة وهذا سجل تتبع؛ ولا financial/Posted إلا باعتماد الإدارة والمالية (CHECK)';

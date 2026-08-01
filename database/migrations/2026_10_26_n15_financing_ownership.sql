-- ═══════════════════════════════════════════════════════════════════════════
-- N-15 التمويل والملكية (FIN-01 §9 · PLAN-05 البوابة ③) — تعتمد N-21 (تحققت)
-- ───────────────────────────────────────────────────────────────────────────
-- «ليست سجلَّ قروض — طبقة تحدد من يملك الأصل وكيف يُعالَج محاسبيًّا».
-- كل الجداول في المجال المقيَّد (T_RESTRICTED — عبر حارس الملكية حصرًا).
-- «التمويل والالتزامات» القائم (fin_funding_facilities) يبقى قراءةً حتى اكتمال
-- الترحيل — عرضٌ لهذه الطبقة لا بيت ثانٍ (FIN-01 §القبول) — لا مساس به.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `financing_models` (
  `model_code` VARCHAR(32) NOT NULL,
  `name_ar` VARCHAR(120) NOT NULL,
  `legal_owner_effect` ENUM('transfers','stays','shared','none') NOT NULL COMMENT '① المالك القانوني',
  `economic_beneficiary` ENUM('us','financier','shared') NOT NULL COMMENT '② المنتفع الاقتصادي',
  `accounting_recognition` ENUM('owned_asset','right_of_use','liability_only') NOT NULL COMMENT '③ الاعتراف — لا يُستنتج من الاسم',
  `depreciation_bearer` VARCHAR(60) NOT NULL COMMENT '④ حامل الإهلاك',
  `security_interest_holder` VARCHAR(60) NULL DEFAULT NULL COMMENT '⑤ مرتهن الضمان',
  `policy_doc_ref` VARCHAR(160) NOT NULL COMMENT 'سياسة محاسبية مكتوبة معتمدة — إلزامية قبل الاستعمال',
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`model_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-01 §2: قاموس نماذج التمويل بمحاوره الخمسة — يُضاف إليه بقرار لا بكود';

INSERT IGNORE INTO `financing_models`
  (`model_code`, `name_ar`, `legal_owner_effect`, `economic_beneficiary`, `accounting_recognition`, `depreciation_bearer`, `security_interest_holder`, `policy_doc_ref`, `approved_by`, `approved_at`) VALUES
  ('murabaha',    'مرابحة',            'transfers', 'us', 'owned_asset',   'us',                'financier_until_paid', 'docs/files/FIN-01 §2 — أصل ملموس + التزام بربح مؤجَّل، الإهلاك علينا', 1, NOW()),
  ('ijara_op',    'إجارة تشغيلية',     'stays',     'us', 'right_of_use',  'right_of_use_term', NULL,                   'docs/files/FIN-01 §2 — حق استخدام بمدة العقد لا بعمر الأصل، لا أصل مملوك', 1, NOW()),
  ('musharaka',   'مشاركة في الأصل',   'shared',    'us', 'owned_asset',   'by_share_pct',      NULL,                   'docs/files/FIN-01 §2 — أصل بحصة + حقوق شركاء، الإهلاك بنسبة الحصة النافذة', 1, NOW()),
  ('fixed_yield', 'تمويل بعائد ثابت',  'none',      'us', 'liability_only','none',              'pledged_if_secured',   'docs/files/FIN-01 §2 — نقد مقابل عائد، لا إهلاك متعلق، الرهن يُسجَّل مرتهنًا لا مالكًا', 1, NOW());

CREATE TABLE IF NOT EXISTS `financing_operations` (
  `op_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `op_code` VARCHAR(40) NOT NULL,
  `financier_entity_id` INT UNSIGNED NOT NULL COMMENT 'كيان بصفة ممول (LEG-01) — لا سجل موازيًا',
  `model_code` VARCHAR(32) NOT NULL,
  `currency` VARCHAR(8) NOT NULL,
  `contract_ref` VARCHAR(120) NULL DEFAULT NULL,
  `signed_date` DATE NULL DEFAULT NULL,
  `capital` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `capital_source` VARCHAR(120) NULL DEFAULT NULL,
  `purchase_value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'قيمة شراء العين — أشد الحقول سرية',
  `down_payment` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `fees_admin` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `fees_insurance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `extra_costs` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `profit_rate` DECIMAL(8,4) NULL DEFAULT NULL,
  `profit_amount` DECIMAL(18,2) NULL DEFAULT NULL,
  `apr` DECIMAL(8,4) NULL DEFAULT NULL,
  `installments_no` INT UNSIGNED NOT NULL DEFAULT 0,
  `installment_amount` DECIMAL(18,2) NULL DEFAULT NULL,
  `outstanding_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `maturity_date` DATE NULL DEFAULT NULL,
  `state` ENUM('draft','negotiation','approved','signed','active','paying','settled','closed','defaulted') NOT NULL DEFAULT 'draft',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`op_id`),
  UNIQUE KEY `uq_fo_code` (`company_id`, `op_code`),
  KEY `ix_fo_financier` (`financier_entity_id`, `state`),
  CONSTRAINT `fk_fo_model` FOREIGN KEY (`model_code`) REFERENCES `financing_models` (`model_code`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fo_financier` FOREIGN KEY (`financier_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-01 §4: عمليات التمويل بدورة حياتها — ولا عملية بلا نموذج ومعالجة';

CREATE TABLE IF NOT EXISTS `financed_assets` (
  `fa_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `op_id` INT UNSIGNED NOT NULL,
  `asset_id` INT NOT NULL COMMENT 'fin_assets.id أو equipments.id بحسب التقاطع',
  `asset_kind` ENUM('fin_asset','equipment') NOT NULL DEFAULT 'equipment',
  `purchase_value` DECIMAL(18,2) NULL DEFAULT NULL,
  `in_fleet` TINYINT(1) NOT NULL DEFAULT 0,
  `in_asset_register` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fa_id`),
  UNIQUE KEY `uq_fa` (`op_id`, `asset_kind`, `asset_id`),
  CONSTRAINT `fk_fa_op` FOREIGN KEY (`op_id`) REFERENCES `financing_operations` (`op_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-01 §4-②: أعيان العملية — فحص تقاطع الأسطول وسجل الأصول';

CREATE TABLE IF NOT EXISTS `asset_ownership_shares` (
  `share_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `asset_id` INT NOT NULL,
  `asset_kind` ENUM('fin_asset','equipment') NOT NULL DEFAULT 'equipment',
  `financier_entity_id` INT UNSIGNED NOT NULL,
  `op_id` INT UNSIGNED NULL DEFAULT NULL,
  `model_code` VARCHAR(32) NULL DEFAULT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `capital` DECIMAL(18,2) NULL DEFAULT NULL,
  `share_valuation` DECIMAL(18,2) NULL DEFAULT NULL,
  `doc_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مستند الحصة — والبيع بلا مستند يُرفض (الخدمة)',
  `recorded_percent` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'التصحيح الموثق: المسجَّلة',
  `corrected_percent` DECIMAL(5,2) NULL DEFAULT NULL,
  `correction_reason` VARCHAR(255) NULL DEFAULT NULL,
  `approved_percent` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'الحكم المعتمد',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`share_id`),
  KEY `ix_aos_asset` (`company_id`, `asset_kind`, `asset_id`, `valid_from`),
  KEY `ix_aos_financier` (`financier_entity_id`),
  CONSTRAINT `fk_aos_financier` FOREIGN KEY (`financier_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_aos_pct` CHECK (`percent` > 0 AND `percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-01 §5: حصص الملكية عبر الزمن — Σ النشطة = 100.00 بالضبط (تحرسه الخدمة معاملةً) ولا تداخل لنفس (الأصل×الممول)';

CREATE TABLE IF NOT EXISTS `financing_installments` (
  `inst_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `op_id` INT UNSIGNED NOT NULL,
  `seq_no` INT UNSIGNED NOT NULL,
  `due_date` DATE NOT NULL,
  `amount_principal` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `amount_profit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `amount_total` DECIMAL(18,2) NOT NULL,
  `currency` VARCHAR(8) NOT NULL,
  `fx_rate_at_payment` DECIMAL(16,8) NULL DEFAULT NULL COMMENT 'سعر يوم السداد — فرق محقق بسطره (PLAN-03 §7.2)',
  `functional_equivalent` DECIMAL(18,2) NULL DEFAULT NULL,
  `paid_date` DATE NULL DEFAULT NULL,
  `payment_ref` VARCHAR(120) NULL DEFAULT NULL,
  `state` ENUM('scheduled','due','paid','overdue','rescheduled') NOT NULL DEFAULT 'scheduled',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inst_id`),
  UNIQUE KEY `uq_fi_seq` (`op_id`, `seq_no`) COMMENT 'يمنع تكرار القسط — وحدث الاستحقاق بمفتاح (العملية×القسط)',
  KEY `ix_fi_due` (`due_date`, `state`),
  CONSTRAINT `fk_fi_op` FOREIGN KEY (`op_id`) REFERENCES `financing_operations` (`op_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-01 §6: الأقساط تولَّد من العملية ولا تُدخل يدويًّا';

CREATE TABLE IF NOT EXISTS `financing_deviations` (
  `dev_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `dev_type` ENUM('no_ledger','payment_gap','unrecorded_exit') NOT NULL,
  `subject_ref` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NULL DEFAULT NULL,
  `priority` ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  `required_doc` VARCHAR(160) NULL DEFAULT NULL,
  `state` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `decision` VARCHAR(500) NULL DEFAULT NULL COMMENT 'القرار المتخذ — ولا يُغلق صف بلا قرار ومستند (الخدمة)',
  `decision_doc_ref` VARCHAR(160) NULL DEFAULT NULL,
  `closed_by` INT NULL DEFAULT NULL,
  `closed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`dev_id`),
  UNIQUE KEY `uq_fd_subject` (`company_id`, `dev_type`, `subject_ref`),
  KEY `ix_fd_state` (`company_id`, `state`, `priority`),
  CONSTRAINT `ck_fd_close_needs_decision` CHECK (`state` <> 'closed' OR (`decision` IS NOT NULL AND `decision_doc_ref` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-01 §7: أوراق الانحراف الثلاث — Insert-only للرصد والقرار يُضاف (CHECK بنيوي)';

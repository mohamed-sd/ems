-- ═══════════════════════════════════════════════════════════════════════════
-- N-09 الإعارة وتعدد الكيانات (PLAN-05 البوابة ③ · LEG-01 §7 النمط ②)
-- ───────────────────────────────────────────────────────────────────────────
-- «مستحق متبادل بين كيانين بنسب تحمّل — يفتح النمط ② في LEG-01»:
-- إعارة معدة بين كيانين داخليين بقيمة محاسبية — لا كيان يتعاقد مع نفسه بلا
-- علامة معاملة بين كيانين داخليين ومستحق متبادل مسجَّل (قيد التناقض ⑥).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `intercompany_loans` (
  `loan_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT NOT NULL,
  `lender_entity_id` INT UNSIGNED NOT NULL COMMENT 'الكيان المعير — داخلي (is_tenant/داخل المجموعة)',
  `borrower_entity_id` INT UNSIGNED NOT NULL COMMENT 'الكيان المستعير — داخلي',
  `date_from` DATE NOT NULL,
  `date_to` DATE NULL DEFAULT NULL,
  `monthly_value` DECIMAL(18,2) NOT NULL COMMENT 'القيمة المحاسبية الشهرية للإعارة',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `bearing_split_json` JSON NOT NULL COMMENT 'نسب التحمل بين الكيانين — Σ = 100 (تحرسه الخدمة)',
  `internal_transaction` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'علامة معاملة بين كيانين داخليين — قيد ⑥',
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `doc_ref` VARCHAR(120) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`loan_id`),
  KEY `ix_icl_equipment` (`company_id`, `equipment_id`, `state`),
  CONSTRAINT `fk_icl_lender` FOREIGN KEY (`lender_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_icl_borrower` FOREIGN KEY (`borrower_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_icl_not_self` CHECK (`lender_entity_id` <> `borrower_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-09: إعارة المعدات بين كيانين داخليين — النمط ② في LEG-01';

CREATE TABLE IF NOT EXISTS `intercompany_dues` (
  `due_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `loan_id` INT UNSIGNED NOT NULL,
  `period` CHAR(7) NOT NULL,
  `creditor_entity_id` INT UNSIGNED NOT NULL,
  `debtor_entity_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `currency` VARCHAR(8) NOT NULL,
  `state` ENUM('accrued','settled') NOT NULL DEFAULT 'accrued',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`due_id`),
  UNIQUE KEY `uq_icd` (`loan_id`, `period`, `creditor_entity_id`),
  CONSTRAINT `fk_icd_loan` FOREIGN KEY (`loan_id`) REFERENCES `intercompany_loans` (`loan_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-09: المستحق المتبادل المسجَّل بين الكيانين — بنسب التحمل';

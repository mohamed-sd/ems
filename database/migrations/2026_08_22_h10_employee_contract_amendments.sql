-- ═══════════════════════════════════════════════════════════════════════════
-- H-10 · ملاحقُ عقد الموظف والنسخةُ الموقَّعة المقفلة — 2026-07-30
-- البطاقة: docs/specs/H-10_employee_contract_amendments.md · المصدر: CON-01 §4/§5/§7.1
-- ───────────────────────────────────────────────────────────────────────────
-- «لا تعديلَ مباشرًا على عقدٍ نافذٍ — كلُّ تغييرٍ ملحقٌ بسريان؛ والنسخةُ
-- الموقَّعة ثابتةٌ لا تُستبدل، والتصحيحُ ملحقٌ يوضّح».
-- `contract_amendments` القائمُ ملكُ عقود العملاء (إسقاط D02) ولا يُمسّ —
-- فالاسمُ هنا صريحٌ: employee_contract_amendments.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `employee_contract_amendments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `amend_type` ENUM('pay_change','duration_change','location_change','scope_change','other') NOT NULL
      COMMENT 'أنواعُ §4: «تغييرُ أجرٍ أو مدةٍ أو موقعٍ أو نطاق» + مخرجُ سلامة',
  `effective_from` DATE NOT NULL COMMENT '«ملحقٌ معتمَدٌ بسريان» — والقراءةُ تأخذ الأحدثَ سريانًا قبل تاريخ الاحتساب',
  `changes_json` MEDIUMTEXT NOT NULL COMMENT '«ما يغيّره حقلًا حقلًا (قبل/بعد)» — و«قبل» يُلتقط من الواقع الحي',
  `state` ENUM('draft','approved','rejected') NOT NULL DEFAULT 'draft',
  `reject_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eca_contract_eff_type` (`contract_id`, `effective_from`, `amend_type`),
  KEY `ix_eca_company` (`company_id`),
  CONSTRAINT `fk_eca_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

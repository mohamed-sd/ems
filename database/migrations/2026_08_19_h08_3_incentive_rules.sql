-- ═══════════════════════════════════════════════════════════════════════════
-- H-08-③ · قواعدُ الحوافز وتوزيعُها بمجموع 100٪ (تدمج M-23) — 2026-07-30
-- البطاقة: docs/specs/H-08_3_incentive_rules.md · المصدر: CON-01 §3.3/§7.1
-- ───────────────────────────────────────────────────────────────────────────
-- «لكل حافزٍ نوعُه وأساسُه وسقفُه وحدُّه الأدنى ودوريّتُه وشرطُ استحقاقه
-- ونطاقُه وسريانُه» — و«مجموعُ التوزيع مئةٌ بالمئة قيدًا بنيويًّا».
-- گوتشا مثبتة: CHECK لا يرى صفوفًا أخرى — فقيدُ Σ=100 يُفرَض في الخدمة
-- بالاستبدال الذري (replaceChildren) لا بCHECK على الصف الواحد.
-- لا تعبئةَ رجعية: لا حوافزَ مهيكلةً في الموروث إطلاقًا (فجوةُ H-09-③).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `incentive_rules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `incentive_type` VARCHAR(50) NOT NULL COMMENT 'اسمُ الحافز من الاتفاق — لا قائمةَ مثبَّتةً في الكود',
  `basis` ENUM('unit','threshold','quality','readiness','safety','fuel','tier') NOT NULL
      COMMENT 'أسسُ §3.3 السبعة: وحدةٌ منفَّذة · تجاوزُ عتبة · جودة · جاهزية · التزامُ سلامة · توفيرُ وقود · شرائح',
  `rate` DECIMAL(14,4) NULL DEFAULT NULL,
  `threshold` DECIMAL(18,2) NULL DEFAULT NULL,
  `cap` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'السقف — بنص الشريحة §5.2-③',
  `floor` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الحدُّ الأدنى',
  `periodicity` ENUM('monthly','periodic','once') NOT NULL DEFAULT 'monthly',
  `condition_text` VARCHAR(255) NULL DEFAULT NULL COMMENT 'شرطُ الاستحقاق نصًّا',
  `scope_type` ENUM('project','equipment_type','site') NULL DEFAULT NULL COMMENT 'نطاقُ §3.3',
  `scope_id` INT NULL DEFAULT NULL,
  `valid_from` DATE NULL DEFAULT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','replaced','ended') NOT NULL DEFAULT 'active',
  `created_by` INT NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ir_contract` (`contract_id`),
  KEY `ix_ir_company` (`company_id`),
  CONSTRAINT `fk_ir_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- التوزيعُ ابنُ قاعدته (CASCADE — استبدالُه الذريُّ كنسُ أبنائه) و«Σ = 100.00
-- لكل rule_id» يُفرَض في الخدمة عند الحفظ (§7.1 نصًّا: فحصٌ بنيويٌّ عند الحفظ).
CREATE TABLE IF NOT EXISTS `incentive_allocations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `rule_id` INT NOT NULL,
  `beneficiary_type` ENUM('employee','job_title') NOT NULL
      COMMENT 'شخصٌ بعينه أو صفةٌ تُحل وقتَ الاحتساب («مشغّلٌ ومساعدٌ ومشرف»)',
  `beneficiary_id` INT NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ia_rule` (`rule_id`),
  KEY `ix_ia_company` (`company_id`),
  UNIQUE KEY `uq_ia_beneficiary` (`rule_id`, `beneficiary_type`, `beneficiary_id`),
  CONSTRAINT `fk_ia_rule` FOREIGN KEY (`rule_id`) REFERENCES `incentive_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

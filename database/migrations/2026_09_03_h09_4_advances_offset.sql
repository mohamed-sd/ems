-- ═══════════════════════════════════════════════════════════════════════════
-- H-09-④ · المقاصّةُ والسلف (تدمج M-20 + M-21) — 2026-07-30
-- البطاقة: docs/specs/H-09_4_advances_offset.md
-- المصدر: ENT-01 §4 («**بوابةٌ واحدةٌ لكل ما يُصرف خارج المسيّر**: سلفةٌ نقديةٌ ·
--         دفعٌ نيابةً عن العامل · مصروفٌ محمَّلٌ عليه — **كلٌّ بمستنده وجدولِ
--         استرداده**» · «خصمُ … كلٌّ بمرجعه؛ **ولا خصمَ بلا مستند، ولا يتجاوز
--         الصافي حدَّ الحماية المقرَّر**») · §8 (Schema) · PLAN-01 §6.1-④
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: «طلبُ سلفة» **نوعٌ في بوابة D05 بمرفقٍ ورقيٍّ فقط — لا
-- بيانات»؛ و`contract_advances` القائم **دفعاتُ عقود العملاء (M-01)** لا سلفُ
-- موظفين. فالبناءُ جديدٌ كاملًا ولا يمسّ شيئًا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① بوابةُ السلفيات — «كلٌّ بمستنده وجدولِ استرداده» ─────────────────────
-- `doc_ref` **NOT NULL + CHECK**: سلفةٌ بلا مستندٍ مالٌ يخرج بلا سند.
-- و`balance` **عمودٌ مولَّد** (المبلغ − المستردّ) فلا ينحرف رصيدٌ عن حركته أبدًا.
-- ⚠ ولا يُكتب: كتابةُ عمودٍ مولَّدٍ ترفض الصفَّ كلَّه و`config.php` لا يرمي.
CREATE TABLE IF NOT EXISTS `employee_advances` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `person_id` INT NOT NULL COMMENT 'employees.id — المستفيد',
  `advance_type` ENUM('cash','on_behalf','charged') NOT NULL DEFAULT 'cash'
      COMMENT 'نقديةٌ · دفعٌ نيابةً عنه (علاجٌ · تذاكرُ · رسوم) · مصروفٌ محمَّلٌ عليه',
  `amount` DECIMAL(18,2) NOT NULL,
  `currency` VARCHAR(8) NULL DEFAULT NULL,
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'مستندُ الصرف — إلزاميٌّ بنيويًّا',
  `issued_date` DATE NOT NULL,
  `installments_count` INT NOT NULL DEFAULT 1 COMMENT 'عددُ أقساط الاسترداد',
  `installment_amount` DECIMAL(18,2) NOT NULL COMMENT 'قسطُ الفترة الواحدة',
  `first_deduction_period` DATE NULL DEFAULT NULL COMMENT 'أولُ فترةٍ يبدأ منها الخصم',
  `recovered` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المستردُّ فعلًا — تُحرّكه المقاصّة',
  `balance` DECIMAL(18,2) AS (`amount` - `recovered`) STORED
      COMMENT '**مولَّد** — لا يُكتب ولا ينحرف عن حركته',
  `state` ENUM('draft','approved','active','settled','cancelled') NOT NULL DEFAULT 'draft',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_adv_person_state` (`person_id`, `state`),
  KEY `ix_adv_co` (`company_id`, `state`),
  CONSTRAINT `ck_adv_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_adv_inst` CHECK (`installments_count` >= 1 AND `installment_amount` > 0),
  CONSTRAINT `ck_adv_recovered` CHECK (`recovered` >= 0 AND `recovered` <= `amount`),
  CONSTRAINT `ck_adv_doc` CHECK (CHAR_LENGTH(TRIM(`doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② خصومُ المسيّر — «فلا يُخصم بندٌ مرتين» بنيويًّا (ENT-01 §8) ──────────
CREATE TABLE IF NOT EXISTS `payroll_deductions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `run_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `source_type` ENUM('advance','on_behalf','penalty','absence','other') NOT NULL,
  `source_id` INT NOT NULL COMMENT 'مرجعُ المصدر — 0 مرفوضٌ بالقيد',
  `amount` DECIMAL(18,2) NOT NULL COMMENT 'المخصومُ فعلًا في هذه الدورة (موجبٌ)',
  `requested_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القسطُ المستحقُّ قبل حدِّ الحماية',
  `doc_ref` VARCHAR(120) NOT NULL COMMENT '«ولا خصمَ بلا مستند» — إلزاميٌّ بنيويًّا',
  `rescheduled` TINYINT NOT NULL DEFAULT 0 COMMENT '1 = قُصّ بحدِّ الحماية ورُحّل باقيه',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_deduction` (`run_id`, `person_id`, `source_type`, `source_id`),
  KEY `ix_deduction_run` (`run_id`),
  CONSTRAINT `fk_deduction_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_deduction_amount` CHECK (`amount` >= 0),
  CONSTRAINT `ck_deduction_src` CHECK (`source_id` > 0),
  CONSTRAINT `ck_deduction_doc` CHECK (CHAR_LENGTH(TRIM(`doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ حدُّ حمايةِ الصافي — «**المقرَّر**» فإن لم يُقرَّر يُعلَن ──────────────
-- `protection_percent` **NULL افتراضًا**: لا رقمَ يُخترع. وحين يكون NULL تُعلن
-- الخدمةُ أن لا حدَّ مقرَّرًا وتخصم القسطَ كاملًا — ولا تفترض حدًّا لم يقرّره أحد.
CREATE TABLE IF NOT EXISTS `payroll_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `protection_percent` DECIMAL(5,2) NULL DEFAULT NULL
      COMMENT 'أدنى نسبةٍ من الإجمالي تبقى للعامل — NULL = لم يُقرَّر بعد',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_settings_co` (`company_id`),
  CONSTRAINT `ck_protection_pct` CHECK (
      `protection_percent` IS NULL OR (`protection_percent` >= 0 AND `protection_percent` <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ تسجيلُ شاشة «سلفيات الموظفين» — الوحدة 157 ──────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 157, 'سلفيات الموظفين', 'Workforce/employee_advances.php', 4, 0, 0, 'fa fa-hand-holding-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Workforce/employee_advances.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 157, 1, r.a, r.e, 0
  FROM (SELECT 4  AS rid, 1 AS a, 1 AS e      -- القوى: تفتح السلفة
        UNION ALL SELECT 17, 0, 1) r          -- المالية: تعتمد ولا تنشئ («من أنشأ لا يعتمد»)
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 157);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 157, 'سلفيات الموظفين', 'Workforce/employee_advances.php',
       'fa fa-hand-holding-dollar', 56, NULL, 'Workforce/employee_advances.php', 1
  FROM (SELECT 4 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Workforce/employee_advances.php');

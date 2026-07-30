-- ═══════════════════════════════════════════════════════════════════════════
-- H-08-② · مكوّناتُ الأجر بخصائصها وطريقةِ حسابها وسريانها — 2026-07-30
-- البطاقة: docs/specs/H-08_2_pay_components.md · المصدر: CON-01 §3.2/§7.1
-- ───────────────────────────────────────────────────────────────────────────
-- «عددُ المكوّنات غيرُ محدود ولا نسبَ مثبَّتةً في الكود» — الجدولُ يحمل
-- التعريفَ والخدمةُ تتحقق؛ لا قيمةَ ولا نسبةَ افتراضيةً في أي موضع.
-- لا تعبئةَ رجعية: بدلاتُ الموروث (worker_contract.allow_*) كلُّها NULL في
-- الصف الوحيد، وأسعارُ سياسات المشغّلين يبقى كاتبُها مصدرَها حتى إقفاله (N-04)
-- ورأسُها محصَّنٌ أصلًا — فلا اختراعَ مكوّناتٍ من فراغ.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `pay_components` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'عزلٌ مباشر (سابقة claim_lines: يُقرأ مجمَّعًا بلا JOIN أبيه)',
  `contract_id` INT NOT NULL,
  `component_type` ENUM('basic','cost_of_living','housing','transport','food','site','hazard',
                        'work_nature','shift','night','responsibility','supervision','assignment',
                        'travel','mission','communication','medical','fixed_bonus','other_allowance','custom')
      NOT NULL COMMENT 'قائمةُ §3.2 العشرون نصًّا — لاتينيةً (گوتشا الترميز) والتعريبُ في الخدمة',
  `calc_method` ENUM('fixed_amount','pct_reference','pct_basic','pct_gross','per_day','per_shift',
                     'per_hour','per_unit','tiers','custom_formula')
      NOT NULL COMMENT 'طرقُ الاحتساب العشر — §3.2',
  `value` DECIMAL(18,2) NULL DEFAULT NULL,
  `rate` DECIMAL(12,2) NULL DEFAULT NULL,
  -- «خصائصُها السبع» (PLAN-01 §5.2-②) = أعلامُ الدخول السبعة من الثلاث عشرة:
  `in_insurance` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل التأمينات؟',
  `in_tax` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل الضريبة؟',
  `in_leave_pay` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل أجرَ الإجازة؟',
  `in_eos` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل نهايةَ الخدمة؟',
  `in_hour_base` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل حسابَ الساعة؟',
  `in_overtime` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل العملَ الإضافي؟',
  `in_incentive_base` TINYINT NOT NULL DEFAULT 0 COMMENT 'يدخل وعاءَ الحافز؟',
  `is_variable` TINYINT NOT NULL DEFAULT 0 COMMENT 'ثابتٌ أم متغير',
  `periodicity` ENUM('monthly','periodic','once') NOT NULL DEFAULT 'monthly',
  `cost_bearer_type` ENUM('project','client_contract','dept','company') NULL DEFAULT NULL
      COMMENT 'إشارةُ المكوّن المفردة — شجرةُ Σ=100 (cost_bearers) بيتُها الشريحة ④',
  `cost_bearer_id` INT NULL DEFAULT NULL,
  `cost_center_id` INT NULL DEFAULT NULL,
  `valid_from` DATE NULL DEFAULT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','replaced','ended') NOT NULL DEFAULT 'active'
      COMMENT 'حالاتُ سياسة الأجر — التصريحُ الكامل مع E-24/H-10',
  `created_by` INT NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pc_contract` (`contract_id`),
  KEY `ix_pc_company` (`company_id`),
  CONSTRAINT `fk_pc_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`),
  CONSTRAINT `fk_pc_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `fin_cost_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

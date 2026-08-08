-- update0012 · م1 — جداولُ مرحلةِ التحليلِ الماليِّ والنسب (M-10 v5 · المرحلة ٩)
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: M-10 v5 §٥-١٣ والمرحلةُ التاسعةُ «التحليلُ الماليُّ والنسب» بعشرِ
-- شاشاتٍ وعشرةِ أفعال · وMASTER-MAP-7 الأوراقُ 35 (44 نسبة) و36 (16 إشارة).
--
-- الأحكامُ المنفَّذةُ بنيويًّا:
--   ◆ النسبُ محسوبةٌ من القيودِ لا من إدخالٍ يدويّ — والبسطُ والمقامُ من
--     أكوادِ الشجرةِ المعلنة (fin.ratio.compute).
--   ◆ لا نسبةَ تُعرض بلا حدٍّ ومالكٍ ودورية (fin.ratio.target).
--   ◆ التدفقاتُ بالطريقةِ غيرِ المباشرةِ — وتتوازن مع تغيرِ النقديةِ الفعليِّ
--     أو تُرفض (balance_check يحمل الشاهد).
--   ◆ حقوقُ الملكية: الختاميُّ = الافتتاحيُّ + الحركاتُ أو تُرفض.
--   ◆ كلُّ توليدٍ نسخةٌ تشير لسابقتها (supersedes) — ولا كتابةَ فوقَ نسخة.
--
-- النمط: CREATE TABLE IF NOT EXISTS — idempotent.

-- ═══ ① fin_ratio_targets — حدودُ النسبِ وأهدافُها (الشاشة: حدود النسب) ═════
CREATE TABLE IF NOT EXISTS `fin_ratio_targets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `ratio_code` VARCHAR(12) NOT NULL COMMENT 'FR-01..FR-44',
  `group_code` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'RG-1..RG-11',
  `name_ar` VARCHAR(190) NOT NULL,
  `name_en` VARCHAR(190) NOT NULL DEFAULT '',
  `formula_ar` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الصيغةُ نصًّا كما في الوثيقة',
  `numerator_codes` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ البسطِ من الشجرة',
  `denominator_codes` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'أكوادُ المقام',
  `unit_ar` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'مرة · ٪ · يوم · عملة',
  `warn_op` ENUM('lt','gt','lte','gte','none') NOT NULL DEFAULT 'none' COMMENT 'اتجاهُ حدِّ الإنذار',
  `warn_value` DECIMAL(18,4) NULL COMMENT 'حدُّ الإنذار',
  `critical_value` DECIMAL(18,4) NULL COMMENT 'الحدُّ الحرج',
  `target_value` DECIMAL(18,4) NULL COMMENT 'الهدفُ المعتمد',
  `limit_text` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'نصُّ الحدِّ من الوثيقة',
  `cadence` VARCHAR(24) NOT NULL DEFAULT 'شهريًّا' COMMENT 'يوميًّا · أسبوعيًّا · شهريًّا · ربعَ سنويّ',
  `owner_role` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '◆ لكل نسبةٍ مالكٌ — ولا نسبةَ بلا حد',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `approved_by` INT NULL COMMENT '◆ نائبُ الرئيس للشؤون المالية والاستثمار',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'الحدُّ السابقُ — والعكسُ إعادتُه بقرار',
  `version_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rtarget` (`company_id`, `ratio_code`, `version_no`),
  KEY `ix_rtarget_grp` (`company_id`, `group_code`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 fin.ratio.target: لكل نسبةٍ حدُّ إنذارٍ وحدٌّ حرجٌ ومالكٌ ودورية';

-- ═══ ② fin_ratio_values — قيمُ النسبِ المحسوبةُ بنسخِها ══════════════════
CREATE TABLE IF NOT EXISTS `fin_ratio_values` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `ratio_code` VARCHAR(12) NOT NULL,
  `period` VARCHAR(10) NOT NULL COMMENT 'YYYY-MM أو YYYY-Qn أو YYYY',
  `scope_kind` VARCHAR(16) NOT NULL DEFAULT 'company' COMMENT 'company · project · contract · equipment',
  `scope_ref` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'قيمةُ البُعدِ عند النطاقِ غيرِ الشركة',
  `numerator_value` DECIMAL(20,4) NULL COMMENT 'قيمةُ البسطِ المحسوبةُ من القيود',
  `denominator_value` DECIMAL(20,4) NULL,
  `result_value` DECIMAL(20,4) NULL COMMENT 'النتيجة — والمقامُ صفرٌ يعطي NULL لا صفرًا كاذبًا',
  `unit_ar` VARCHAR(24) NOT NULL DEFAULT '',
  `status_flag` ENUM('ok','warn','critical','unmeasured') NOT NULL DEFAULT 'unmeasured'
      COMMENT '◆ unmeasured حين يغيب المقام — شرطةٌ لا صفر',
  `trend_direction` ENUM('up','down','flat','na') NOT NULL DEFAULT 'na',
  `source_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'مصدرُ الحساب: أكوادُ الشجرةِ والفترة',
  `entries_count` INT NOT NULL DEFAULT 0 COMMENT 'عددُ القيودِ المكوِّنةِ — أساسُ التعمّق',
  `computed_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `supersedes_id` INT UNSIGNED NULL COMMENT 'إعادةُ الحسابِ نسخةٌ تشير لسابقتها',
  `state` ENUM('computed','superseded') NOT NULL DEFAULT 'computed',
  `idempotency_key` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rval` (`company_id`, `idempotency_key`),
  KEY `ix_rval_scope` (`company_id`, `ratio_code`, `period`, `state`),
  KEY `ix_rval_flag` (`company_id`, `status_flag`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 fin.ratio.compute: النسبُ محسوبةٌ من القيودِ لا من إدخالٍ يدوي';

-- ═══ ③ fin_project_pl — قائمةُ دخلِ المشروع (S3 · البُعد D2) ═══════════════
CREATE TABLE IF NOT EXISTS `fin_project_pl` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `pl_code` VARCHAR(16) NOT NULL COMMENT 'PPL-000001',
  `project_id` INT NOT NULL,
  `period` VARCHAR(10) NOT NULL,
  `revenue_total` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'من 41 و42 بالبُعد D2',
  `direct_cost_total` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'من 51 بالبُعد D2',
  `allocated_overhead` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'حصةٌ محمَّلةٌ من 52 بأساسِ تحميلٍ معلَن',
  `allocation_basis` VARCHAR(190) NOT NULL DEFAULT '' COMMENT '◆ أساسُ التحميلِ يُعلَن ولا يُخترع',
  `gross_margin` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'M2 على المشروع',
  `operating_profit` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'M3 على المشروع',
  `margin_pct` DECIMAL(9,4) NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `lines_json` MEDIUMTEXT NULL COMMENT 'بنودُ القائمةِ بأكوادها — أساسُ التعمّق',
  `state` ENUM('generated','superseded') NOT NULL DEFAULT 'generated',
  `supersedes_id` INT UNSIGNED NULL,
  `generated_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL COMMENT 'المحاسبُ يولّد والمديرُ الماليُّ يراجع',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ppl_code` (`company_id`, `pl_code`),
  UNIQUE KEY `uq_ppl_idem` (`company_id`, `idempotency_key`),
  KEY `ix_ppl_proj` (`company_id`, `project_id`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 S3: قائمةُ دخلِ المشروعِ تُنتَج من الأبعادِ لا من شجرةٍ منفصلة';

-- ═══ ④ fin_cashflow — قائمةُ التدفقاتِ النقدية (S4 · غيرُ مباشرة) ═════════
CREATE TABLE IF NOT EXISTS `fin_cashflow` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `cf_code` VARCHAR(16) NOT NULL COMMENT 'CFS-000001',
  `period` VARCHAR(10) NOT NULL,
  `net_profit` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'نقطةُ البدءِ — النتيجة',
  `adj_depreciation` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'تسوياتٌ غيرُ نقدية',
  `adj_provisions` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `adj_other` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `wc_receivables` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'التغيرُ في رأسِ المالِ العامل',
  `wc_inventory` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `wc_payables` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `wc_other` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `operating_net` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'التدفقُ التشغيلي',
  `investing_net` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'الاستثماري — من cashflow_activity',
  `financing_net` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'التمويلي',
  `net_change` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'صافي التغير المحسوب',
  `cash_open` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'نقديةُ أولِ المدةِ من 1101+1102',
  `cash_close` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'نقديةُ آخرِ المدةِ الفعلية',
  `actual_change` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'التغيرُ الفعليُّ = الختاميُّ − الافتتاحي',
  `balance_diff` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT '◆ الفرقُ — والتوليدُ يُرفض إن تجاوز الحد',
  `balance_ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '◆ تتوازن أو تُرفض',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `lines_json` MEDIUMTEXT NULL,
  `state` ENUM('generated','superseded') NOT NULL DEFAULT 'generated',
  `supersedes_id` INT UNSIGNED NULL,
  `generated_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfs_code` (`company_id`, `cf_code`),
  UNIQUE KEY `uq_cfs_idem` (`company_id`, `idempotency_key`),
  KEY `ix_cfs_period` (`company_id`, `period`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 S4: الطريقةُ غيرُ المباشرةِ — وتتوازن مع تغيرِ النقديةِ الفعليِّ أو تُرفض';

-- ═══ ⑤ fin_equity — قائمةُ التغيراتِ في حقوقِ الملكية (S5) ═════════════════
CREATE TABLE IF NOT EXISTS `fin_equity` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `eq_code` VARCHAR(16) NOT NULL COMMENT 'EQS-000001',
  `period` VARCHAR(10) NOT NULL,
  `component_code` VARCHAR(30) NOT NULL COMMENT 'كودُ بندِ حقوقِ الملكية — 3101 · 3201 …',
  `component_name` VARCHAR(190) NOT NULL DEFAULT '',
  `opening_balance` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `additions` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'زياداتُ رأسِ المالِ والأرباح',
  `deductions` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'التوزيعاتُ والخسائر',
  `transfers` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'التحويلُ للاحتياطيات',
  `closing_balance` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `computed_closing` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'الافتتاحيُّ + الحركات',
  `balance_ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '◆ الختاميُّ = الافتتاحيُّ + الحركاتُ أو تُرفض',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `state` ENUM('generated','superseded') NOT NULL DEFAULT 'generated',
  `supersedes_id` INT UNSIGNED NULL,
  `generated_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eqs_idem` (`company_id`, `idempotency_key`),
  KEY `ix_eqs_period` (`company_id`, `period`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 S5: الختاميُّ = الافتتاحيُّ + الحركاتُ لكل بندٍ أو تُرفض';

-- ═══ ⑥ fin_signal_rules — قواعدُ إشاراتِ الإنذارِ المالي (FS-01..16) ══════
CREATE TABLE IF NOT EXISTS `fin_signal_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `signal_code` VARCHAR(12) NOT NULL COMMENT 'FS-01..FS-16',
  `name_ar` VARCHAR(255) NOT NULL,
  `rule_expr` VARCHAR(190) NOT NULL COMMENT 'القاعدةُ نصًّا: FR-05 < 0 · FR-09 ↓ ×3',
  `ratio_code` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'النسبةُ التي تُقاس',
  `operator` ENUM('lt','gt','lte','gte','decline_streak','delta_gt','none') NOT NULL DEFAULT 'none',
  `threshold` DECIMAL(18,4) NULL,
  `streak_periods` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'تراجعٌ متتالٍ — ثلاثةُ أشهرٍ مثلًا',
  `severity` ENUM('حرج','مرتفع','متوسط','منخفض') NOT NULL DEFAULT 'متوسط',
  `destination_ar` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الوجهةُ: الرئيسُ والنائبُ المالي …',
  `cadence` VARCHAR(24) NOT NULL DEFAULT 'شهريًّا',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fsrule` (`company_id`, `signal_code`),
  KEY `ix_fsrule_active` (`company_id`, `active`, `cadence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='MAP-7 الورقة 36: كلُّ إشارةٍ تُنشر لإدارةِ المخاطرِ فتدخل الفرزَ الرباعي';

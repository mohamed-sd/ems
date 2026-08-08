-- update0012 · م2 — M-10 المالية والخزينة: جداولُ الأفعالِ المعلَنةِ غيرِ المبنية
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: M-10 — المالية والخزينة (docs/update0012) — 28 شاشةً و35 فعلًا منها
-- 24 ماليًّا. الأفعالُ المبنيةُ سلفًا (bound_page) لا تُمسّ؛ وهذه الهجرةُ تبني
-- جداولَ الأفعالِ السبعةِ المعلَنةِ غيرِ المبنية (declared_unbuilt):
--   fin.entitle → fin_entitlements (محضرُ التوليد بأحكامه الثلاثة §7-2)
--   gate.pass   → fin_entitlement_gate_log (البوابةُ الرباعية §11: سلسلةٌ ·
--                 فترةٌ · عقدٌ · حصةٌ — والردُّ بسببٍ محكوم)
--   budget.commit  → fin_budget_commitments (المتاحُ ينخفض قبل الصرف)
--   budget.request → fin_budget_change_requests (لا يُعدَّل السقفُ قبل الاعتماد)
--   stmt.client.issue → fin_client_statements (الرصيدُ التراكميُّ يُثبَّت —
--                 والعكسُ إعادةُ إصدارٍ بنسخةٍ جديدةٍ لا حذف)
--   margin.compute → fin_margin_analysis (هامشُ الواقعةِ والعقدِ — إعادةُ
--                 الاحتسابِ نسخةٌ تشير لسابقتها)
--   cycle.measure  → fin_cycle_time_metrics (مواضعُ الاختناق بالحلقة والمعتمِد)
-- (budget.approve يكتب fin_budgets الموجودَ — لا جدولَ جديدًا له.)
--
-- الأعمدةُ الحاكمة (M-10 §9-1): «المستندُ الذي يُعتمد يحمل السبعةَ كاملةً» —
-- والسجلُّ يحمل الكيانَ والمُنشئَ وتاريخَه والمرجعَ الأب. عدمُ الرجعية §9-4:
-- الجداولُ append-only بالخدمة والتصحيحُ بمرجعٍ (supersedes/reversed_by).
-- النمط الحارس: CREATE IF NOT EXISTS — idempotent.

-- ═══ ① fin_entitlements — توليدُ المستحق من العمل المعتمد (الشاشة ٢ · 31 عمودًا) ═══
-- «إيرادُ العميل وذمةُ المورد وأجرُ المشغّل تتولد من واقعةٍ واحدةٍ بأحكامٍ
--  ثلاثةٍ مستقلة — ولا أثرَ قبل هذه اللحظة» (§7-2 fin.entitle).
CREATE TABLE IF NOT EXISTS `fin_entitlements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `entitle_code` VARCHAR(16) NOT NULL COMMENT 'ENT-000001 — رقمُ المحضر',
  `period` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM — الفترة',
  `contract_id` INT NULL COMMENT 'العقد',
  `unit_record_id` INT NOT NULL COMMENT 'الواقعةُ المرجعية — fin_unit_records.id',
  `client_ruling` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'حكمُ العميل — يُفوتر أو سببُ الامتناع',
  `client_amount` DECIMAL(18,2) NULL COMMENT 'قيمةُ إيراد العميل (حقلٌ حساس)',
  `supplier_ruling` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'حكمُ المورد',
  `supplier_amount` DECIMAL(18,2) NULL COMMENT 'قيمةُ استحقاق المورد (حساس)',
  `operator_ruling` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'حكمُ المشغّل',
  `operator_amount` DECIMAL(18,2) NULL COMMENT 'قيمةُ أجر المشغّل (حساس)',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` DECIMAL(18,6) NULL COMMENT 'سعرُ الصرف (حساس) — base=amount×rate',
  `chain_completed_at` DATETIME NULL COMMENT 'تاريخُ اكتمال السلسلة الخماسية',
  `fact_event_id` INT NULL COMMENT 'رقمُ الحدث — ems_business_events.id',
  `journal_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'رقمُ القيد إن نُشر',
  `effects_json` TEXT NULL COMMENT 'مخرجُ المروحة: آثارٌ ومتخطًّى بأسبابه (لا تلفيق)',
  `state` ENUM('generated','approved','reversed') NOT NULL DEFAULT 'generated',
  `generated_by` INT NOT NULL COMMENT '§9-1 المُنشئ',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL COMMENT '§9-1 المعتمِد — المديرُ المالي',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجعُ التفويض',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '§9-1 المرجعُ الأب — رقمُ الوحدة',
  `idempotency_key` VARCHAR(80) NOT NULL COMMENT 'AR-04: (الشركة×الوحدة) — لا محضرَ ثانيًا',
  `effect_grade` VARCHAR(16) NOT NULL DEFAULT 'مالي' COMMENT 'درجةُ الأثر',
  `reversed_by_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'معكوسٌ بـ — قيدٌ عاكسٌ بمرجعه',
  `reverses_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'عكسٌ عن',
  `cost_center_id` INT NULL COMMENT 'مركزُ التكلفة (حساس)',
  `ruleset_version` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'نسخةُ القاعدةِ المستعملة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ent_code` (`company_id`, `entitle_code`),
  UNIQUE KEY `uq_ent_idem` (`company_id`, `idempotency_key`),
  KEY `ix_ent_period` (`company_id`, `period`),
  KEY `ix_ent_unit` (`company_id`, `unit_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 الشاشة ٢: توليدُ المستحق — محضرٌ بأحكامٍ ثلاثةٍ مستقلة';

-- ═══ ② fin_entitlement_gate_log — فحصُ شروط الاستحقاق (الشاشة ٢٦ · 26 عمودًا) ═══
-- «الحارسُ يفحص: سلسلةٌ مكتملة · فترةٌ مفتوحة · عقدٌ نافذ · حصةٌ متاحة — ثم
--  يتولد الأثرُ الماليُّ ولا يقع قبلها» (§7-2 gate.pass) — والردُّ بسببٍ محكوم.
CREATE TABLE IF NOT EXISTS `fin_entitlement_gate_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `gate_code` VARCHAR(16) NOT NULL COMMENT 'GTE-000001 — رقمُ البوابة',
  `period` VARCHAR(7) NOT NULL,
  `contract_id` INT NULL,
  `unit_record_id` INT NOT NULL COMMENT 'الواقعةُ المرجعية',
  `chain_ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '① سلسلةُ الاعتماد مكتملة؟',
  `period_ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '② الفترةُ المحاسبية مفتوحة؟',
  `contract_ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '③ العقدُ نافذ؟',
  `quota_ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '④ الحصةُ متاحة؟',
  `result` ENUM('pass','reject') NOT NULL COMMENT 'نتيجةُ الفحص',
  `reject_code` VARCHAR(24) NOT NULL DEFAULT ''
      COMMENT 'سببُ الرد المحكوم: GATE-CHAIN · GATE-PERIOD · GATE-CONTRACT · GATE-QUOTA',
  `client_ruling` VARCHAR(32) NOT NULL DEFAULT '',
  `supplier_ruling` VARCHAR(32) NOT NULL DEFAULT '',
  `operator_ruling` VARCHAR(32) NOT NULL DEFAULT '',
  `impact_amount` DECIMAL(18,2) NULL COMMENT 'قيمةُ الأثر (حساس)',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `fx_note` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'سعرُ الصرف ومصدره',
  `fact_event_id` INT NULL COMMENT 'رقمُ الحدثِ المولَّد إن مرّت',
  `journal_ref` VARCHAR(32) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(80) NOT NULL COMMENT '(الوحدة×المحاولة اليومية) — الإعادةُ ترجع الأول',
  `state` ENUM('logged','superseded') NOT NULL DEFAULT 'logged',
  `created_by` INT NOT NULL COMMENT '§9-1 المُنشئ',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gate_code` (`company_id`, `gate_code`),
  UNIQUE KEY `uq_gate_idem` (`company_id`, `idempotency_key`),
  KEY `ix_gate_unit` (`company_id`, `unit_record_id`, `result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 الشاشة ٢٦: بوابةُ الاستحقاقِ الرباعية — إخفاقُ فحصٍ يردُّ الواقعةَ بسببٍ محكوم';

-- ═══ ③ fin_budget_commitments — حجزُ التزامٍ على الميزانية (budget.commit) ═══
-- «المتاحُ ينخفض قبل الصرف · وتجاوزُ السقف يوقف الطلبَ حتى الاعتماد» —
-- والعكسُ تحريرُ الالتزامِ عند الإلغاء.
CREATE TABLE IF NOT EXISTS `fin_budget_commitments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `commit_code` VARCHAR(16) NOT NULL COMMENT 'CMT-000001',
  `budget_id` INT NOT NULL COMMENT 'fin_budgets.id',
  `budget_line_id` INT NULL COMMENT 'fin_budget_lines.id',
  `source_kind` ENUM('payment_request','purchase_order','contract','other') NOT NULL,
  `source_ref` VARCHAR(64) NOT NULL COMMENT 'مرجعُ المصدر — طلبُ الدفعِ أو أمرُ الشراء',
  `amount` DECIMAL(18,2) NOT NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `available_before` DECIMAL(18,2) NULL COMMENT 'المتاحُ قبل الحجز (حساس)',
  `state` ENUM('committed','consumed','released') NOT NULL DEFAULT 'committed',
  `released_reason` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'العكس: تحريرٌ عند الإلغاء بسببه',
  `released_at` DATETIME NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idempotency_key` VARCHAR(80) NOT NULL COMMENT '(المصدرُ ونوعُه) — لا حجزَ مزدوجًا',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmtb_code` (`company_id`, `commit_code`),
  UNIQUE KEY `uq_cmtb_idem` (`company_id`, `idempotency_key`),
  KEY `ix_cmtb_budget` (`company_id`, `budget_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 budget.commit: الالتزامُ يخفض المتاحَ قبل الصرف — والتحريرُ عكسُه';

-- ═══ ④ fin_budget_change_requests — طلبُ تعديل ميزانية (budget.request) ═══
CREATE TABLE IF NOT EXISTS `fin_budget_change_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `req_code` VARCHAR(16) NOT NULL COMMENT 'BCR-000001',
  `budget_id` INT NOT NULL,
  `budget_line_id` INT NULL,
  `dept_module` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'الإدارةُ الطالبة',
  `current_amount` DECIMAL(18,2) NOT NULL,
  `requested_amount` DECIMAL(18,2) NOT NULL,
  `impact_note` TEXT NULL COMMENT 'بيانُ الأثر — إلزاميٌّ قبل الاعتماد',
  `state` ENUM('submitted','approved','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
  `decided_reason` VARCHAR(190) NOT NULL DEFAULT '',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'رقمُ الموازنة الأم',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcr_code` (`company_id`, `req_code`),
  KEY `ix_bcr_budget` (`company_id`, `budget_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 budget.request: الطلبُ يدخل سلّمَ الاعتماد ببيان أثره — ولا يُعدَّل السقفُ قبله';

-- ═══ ⑤ fin_client_statements — كشفُ حساب العميل المُصدَر (الشاشة ١٦ · 28 عمودًا) ═══
-- «الرصيدُ التراكميُّ يُثبَّت · والعميلُ يُطالب بمطابقته قبل التصعيد» —
-- والعكسُ إعادةُ إصدارٍ بنسخةٍ جديدةٍ تشير لسابقتها (لا حذف).
CREATE TABLE IF NOT EXISTS `fin_client_statements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `stmt_code` VARCHAR(16) NOT NULL COMMENT 'CST-000001 — رقمُ الكشف',
  `client_id` INT NOT NULL,
  `period_from` DATE NOT NULL,
  `period_to` DATE NOT NULL,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'رصيدُ أول المدة (حساس)',
  `invoices_total` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `credit_notes_total` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `collections_total` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `advance_deduction` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'خصمُ المقدم',
  `retention_held` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'محتجزُ الضمان',
  `closing_balance` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'رصيدُ آخر المدة (حساس)',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `base_equiv` DECIMAL(18,2) NULL COMMENT 'المعادلُ بعملة الدفاتر',
  `oldest_unpaid_date` DATE NULL COMMENT 'أقدمُ فاتورةٍ غيرِ مسدَّدة',
  `overdue_days` INT NOT NULL DEFAULT 0,
  `client_match_state` ENUM('بانتظار المطابقة','طابق العميل','نزاع') NOT NULL DEFAULT 'بانتظار المطابقة',
  `layers_json` MEDIUMTEXT NULL COMMENT 'طبقاتُ الكشف من ClientStatementService — لقطةٌ مثبتة',
  `state` ENUM('issued','superseded') NOT NULL DEFAULT 'issued',
  `supersedes_id` INT UNSIGNED NULL COMMENT 'العكس: النسخةُ الجديدةُ تشير للسابقة',
  `issued_by` INT NOT NULL COMMENT '§9-1 المُنشئ — أصدره',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL COMMENT 'اعتمده',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'المرجعُ الأب — العميل أو العقد',
  `idempotency_key` VARCHAR(80) NOT NULL COMMENT '(العميل×الفترة×النسخة)',
  `cost_center_id` INT NULL,
  `fx_note` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'سعرُ الصرف ومصدره',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cst_code` (`company_id`, `stmt_code`),
  UNIQUE KEY `uq_cst_idem` (`company_id`, `idempotency_key`),
  KEY `ix_cst_client` (`company_id`, `client_id`, `period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 الشاشة ١٦: كشفُ حساب العميل — تثبيتُ رصيدٍ والعكسُ نسخةٌ جديدة';

-- ═══ ⑥ fin_margin_analysis — هامشُ الربح للعقد والواقعة (الشاشة ١٤ · 26 عمودًا) ═══
-- «هامشُ الواقعة = إيرادُ العميل − (مستحقُّ المورد + مستحقُّ المشغّل + تكاليفُ
--  المعدة)» (SAL-016) — تقريرُ ربحيةٍ محسوبٌ لا حسابٌ يدوي، والإعادةُ نسخةٌ.
CREATE TABLE IF NOT EXISTS `fin_margin_analysis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `run_code` VARCHAR(16) NOT NULL COMMENT 'MRG-000001',
  `period` VARCHAR(7) NOT NULL,
  `contract_id` INT NULL,
  `project_id` INT NULL,
  `unit_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'الوحدةُ إن كان الحسابُ بواقعة',
  `revenue_recognized` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'الإيرادُ المعترَف به',
  `cost_operators` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '(حساس)',
  `cost_fuel` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `cost_maintenance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `cost_inventory` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `cost_transfer` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `cost_financing` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `depreciation` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_cost` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `margin` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '(حساس)',
  `margin_pct` DECIMAL(9,4) NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `state` ENUM('computed','superseded') NOT NULL DEFAULT 'computed',
  `supersedes_id` INT UNSIGNED NULL COMMENT 'إعادةُ احتسابٍ بعد تصحيح — نسخةٌ تشير لسابقتها',
  `computed_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(96) NOT NULL COMMENT '(الفترة×العقد×الوحدة×النسخة)',
  `cost_center_id` INT NULL,
  `fx_note` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mrg_code` (`company_id`, `run_code`),
  UNIQUE KEY `uq_mrg_idem` (`company_id`, `idempotency_key`),
  KEY `ix_mrg_scope` (`company_id`, `period`, `contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 الشاشة ١٤: الهامشُ محسوبٌ من الاعترافات الثلاثة — وتظهر العقودُ الخاسرة';

-- ═══ ⑦ fin_cycle_time_metrics — زمنُ دورة الطلبات (الشاشة ٢٣ · 25 عمودًا) ═══
-- «مواضعُ الاختناق تُكشف بالحلقة والمعتمِد — ويُرفع تقريرٌ دوريٌّ بالمتجاوزين».
CREATE TABLE IF NOT EXISTS `fin_cycle_time_metrics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `metric_code` VARCHAR(16) NOT NULL COMMENT 'CYC-000001',
  `period` VARCHAR(7) NOT NULL,
  `request_type` VARCHAR(64) NOT NULL COMMENT 'نوعُ الطلب',
  `dept_module` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'الإدارة',
  `requests_count` INT NOT NULL DEFAULT 0,
  `avg_ring1_hours` DECIMAL(10,2) NULL COMMENT 'متوسطُ زمن الحلقة الأولى',
  `avg_ring2_hours` DECIMAL(10,2) NULL,
  `avg_ring3_hours` DECIMAL(10,2) NULL,
  `total_cycle_hours` DECIMAL(10,2) NULL COMMENT 'إجماليُّ زمن الدورة',
  `target_hours` DECIMAL(10,2) NULL COMMENT 'المستهدف — من قواعد التوجيه',
  `variance_hours` DECIMAL(10,2) NULL COMMENT 'الانحراف',
  `longest_ring` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'أطولُ حلقة',
  `slowest_approver_id` INT NULL COMMENT 'المعتمِدُ الأبطأ',
  `breach_count` INT NOT NULL DEFAULT 0 COMMENT 'عددُ المتجاوز للمهلة',
  `compliance_pct` DECIMAL(9,4) NULL COMMENT 'نسبةُ الالتزام',
  `action_note` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الإجراء',
  `state` ENUM('measured','superseded') NOT NULL DEFAULT 'measured',
  `computed_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '',
  `idempotency_key` VARCHAR(96) NOT NULL COMMENT '(الفترة×النوع×الإدارة)',
  `cost_center_id` INT NULL,
  `fx_note` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cyc_code` (`company_id`, `metric_code`),
  UNIQUE KEY `uq_cyc_idem` (`company_id`, `idempotency_key`),
  KEY `ix_cyc_period` (`company_id`, `period`, `request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-10 الشاشة ٢٣: قياسُ زمن الدورة بالحلقة والمعتمِد — والتقريرُ دوري';

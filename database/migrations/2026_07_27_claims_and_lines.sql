-- ═══════════════════════════════════════════════════════════════════════════
-- المستخلص وبنوده — من الوحدة المعتمدة إلى ذمّة العميل (UX-08 §5.2 · §8.1)
-- 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوة المقيسة**: 10 أحداثِ إيرادٍ بـ9,290,900 — كلُّها **بلا عميل** وكلُّها
-- **بلا ذمّة**؛ وصفّا الذمم الوحيدان مُدخلان يدويًّا بلا ارتباطٍ بأي حدث. فالنظامُ
-- يسجّل إيرادًا لا يعرف ممّن يُحصَّل ولا يطالب به أحدًا.
--
-- **سلسلةُ العميل (بيان المالك 2026-07-27، وتحقّقٌ على 359 صفَّ دوامٍ بلا استثناء)**:
--     تايم شيت → operations → project → client  ·  والعقدُ من operations.contract_id
-- والتسعيرُ من `contractequipments` بمطابقة (العقد × نوع المعدة) — **نفسُ مصدر
-- المروحة حرفيًّا** (EffectFanout §resolveTimesheet) فلا يختلف المستخلصُ عن الإيراد.
--
-- **قاعدةُ عدم الاستخلاص مرتين**: UQ (claim_id, source_kind, source_ref) على البنود،
-- وفهرسٌ عامٌّ يكشف أيَّ وحدةٍ استُخلصت في مستخلصين (يُحرسه الاختبار).
--
-- إضافيٌّ محض: جدولان جديدان، ولا مساسَ بجدولٍ قائم.
-- الرجوع: إسقاطُ الجدولين (بترتيب البنود ثم الرأس).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `claims` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `company_id`      INT NOT NULL COMMENT 'عزل المستأجر',
  `claim_no`        VARCHAR(32)  NOT NULL COMMENT 'رقم المستخلص التسلسلي CLM-سنة-رقم',
  `contract_id`     INT NOT NULL COMMENT 'العقد — مفتاحُ المستخلص (UX-08 §8.1)',
  `client_id`       INT NULL COMMENT 'العميل مشتقًّا من مشروع العقد — لا يُدخل',
  `project_id`      INT NULL COMMENT 'مشروع العقد',
  `period_from`     DATE NOT NULL,
  `period_to`       DATE NOT NULL,
  `currency`        VARCHAR(16) NOT NULL DEFAULT 'SDG' COMMENT 'عملة العقد',
  `gross_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي البنود قبل الاستقطاع',
  `retention_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الاستقطاعات التعاقدية (يدويةٌ بسطرها في النسخة الأولى)',
  `retention_note`  VARCHAR(255) NULL COMMENT 'مرجعُ الاستقطاع وسببه',
  `net_amount`      DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الصافي = الإجمالي − الاستقطاعات',
  `tax_code`        VARCHAR(16) NULL COMMENT 'كود الضريبة من fin_tax_codes',
  `tax_amount`      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `invoice_no`      VARCHAR(64) NULL COMMENT 'رقم الفاتورة الضريبية المولَّدة من المستخلص المعتمد',
  `invoice_date`    DATE NULL,
  `state`           VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT 'draft·review·approved·invoiced·collected·cancelled',
  `event_id`        INT NULL COMMENT 'حدث الإيراد المنشور — قراءةً بمرجعه',
  `receivable_id`   INT NULL COMMENT 'صفّ الذمّة المدينة المولَّد',
  `version`         INT NOT NULL DEFAULT 1 COMMENT 'قفلُ النسخة عند الاعتماد',
  `approved_by`     INT NULL,
  `approved_at`     DATETIME NULL,
  `notes`           VARCHAR(255) NULL,
  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`      DATETIME NULL,
  `deleted_by`      INT NULL,
  `created_by`      INT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claim_no` (`company_id`, `claim_no`),
  UNIQUE KEY `uq_claim_period` (`company_id`, `contract_id`, `period_from`, `period_to`)
    COMMENT 'مستخلصٌ واحدٌ لكل (عقد × فترة) — إعادةُ التوليد ترفض بمرجع القائم',
  KEY `ix_claim_state`  (`state`),
  KEY `ix_claim_client` (`client_id`),
  KEY `ix_claim_period` (`period_from`, `period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='المستخلص — مطالبةُ الفترة من الوحدات المعتمدة (UX-08 §5.2)';

CREATE TABLE IF NOT EXISTS `claim_lines` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `company_id`    INT NOT NULL,
  `claim_id`      INT NOT NULL,
  `source_kind`   VARCHAR(24) NOT NULL DEFAULT 'timesheet' COMMENT 'مصدر الواقعة: timesheet · unit_entry',
  `source_ref`    INT NOT NULL COMMENT 'معرّف الواقعة في مصدرها — رابطُ الأصل',
  `work_date`     DATE NULL,
  `equipment_ref` VARCHAR(64) NULL COMMENT 'المعدة كما في سجل التشغيل',
  `unit_type`     VARCHAR(16) NULL COMMENT 'hour·ton·meter — وحدةُ العقد',
  `qty`           DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `unit_price`    DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'من سطر معدة العقد — لا يُدخل',
  `amount`        DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'محسوبٌ = الكمية × السعر',
  `dispute_flag`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'بندٌ متنازَعٌ عليه — يقف وحده ولا يجمّد البقية',
  `dispute_reason` VARCHAR(255) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claim_line_src` (`claim_id`, `source_kind`, `source_ref`)
    COMMENT 'لا وحدةَ تتكرر داخل المستخلص الواحد',
  KEY `ix_cl_claim`  (`claim_id`),
  KEY `ix_cl_source` (`source_kind`, `source_ref`)
    COMMENT 'يكشف أي وحدةٍ استُخلصت في أكثر من مستخلص (حارسٌ في الاختبار)',
  CONSTRAINT `fk_claim_line_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بنود المستخلص — سطرٌ لكل واقعةٍ معتمدةٍ برابط أصلها';

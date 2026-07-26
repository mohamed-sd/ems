-- ═══════════════════════════════════════════════════════════════════════════
-- UX-02 §8.2 — سياساتُ مستحقّات المشغّلين الكاملة (بدل العمودين)
-- ───────────────────────────────────────────────────────────────────────────
-- «الشاشة الحالية عمودان (المشغّل · بالراتب) وزرُّ "اجعله بالمستحق" — نموذجُ
--  بياناتٍ ناقصٌ جوهريًّا. تُعاد جدولَ سياساتٍ يدعم النماذج الثلاثة والأسس
--  السبعة» — بالمجموعات الخمس المنصوصة حرفيًّا:
--    ① الهوية والسريان: المشغّل · عقده المرجعي · تاريخ السريان · تاريخ
--       الاعتماد · العملة
--    ② النموذج والأساس: نموذج العمل (ساعة/طن/نقلة/متر) · أساس الاستحقاق
--       (تشغيل فعلي/استعداد/حضور/طن/نقلة/متر/مركّب)
--    ③ القيم والحدود: معدل الاستحقاق · الحد الأدنى · الحد الأقصى · الخصومات
--       · الاستثناءات
--    ④ النطاق: المشروع أو نوع المعدة — وسياسةٌ افتراضيةٌ عند غياب الخاص
--
-- حكمُ النماذج الثلاثة (§8.2 نصًّا): «الاستعدادُ والتوقفُ لا يتحولان وحداتِ
-- إنتاجٍ أبدًا — ويجوز احتسابهما للمشغّل وحده وفق عقده أو سياسة حافزه» —
-- فسياسةُ المشغّل مستقلةٌ عن حكم العميل والمورد بالبناء لا بالاستثناء.
--
-- التركيب: السياسةُ المركّبة = صفوفٌ متعددةٌ لمشغّلٍ واحد (صفٌّ لكل أساس)
-- والمحرّك يجمعها؛ قيمةُ ENUM «composite» محجوزةٌ لصيغٍ لاحقةٍ ولا يحسبها
-- المحرّك اليوم — يعلن تخطّيها (قاعدة عدم التلفيق).
--
-- الأخصّية عند التعارض: مشروعٌ محدد > نوعُ معدةٍ محدد > افتراضية.
-- القديمان (employees.salary_type · fin_operator_pay) يبقيان — والسياسةُ
-- تغلبهما حيث وُجدت (قرار المالك 2026-07-26).
-- التطبيق عبر database/migrate.php حصرًا بعميل utf8mb4.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `operator_pay_policies` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,

  -- ① الهوية والسريان
  `employee_id`    INT UNSIGNED NOT NULL COMMENT 'المشغّل (employees) — مرجعٌ مرن',
  `contract_ref`   INT UNSIGNED NULL COMMENT 'عقدُ المشغّل المرجعي إن وُجد — مرجعٌ مرن',
  `effective_from` DATE NULL COMMENT 'بداية السريان — NULL = مفتوحة',
  `effective_to`   DATE NULL COMMENT 'نهاية السريان — NULL = مفتوحة',
  `approved_at`    DATETIME NULL COMMENT 'تاريخ اعتماد السياسة',
  `approved_by`    INT UNSIGNED NULL COMMENT 'معتمِدها (users)',
  `currency`       VARCHAR(10) NOT NULL DEFAULT 'SDG',

  -- ② النموذج والأساس
  `work_model`     ENUM('hour','ton','trip','meter') NOT NULL
                     COMMENT 'نموذج العمل الذي تسري عليه',
  `basis`          ENUM('actual_work','standby','attendance','ton','trip','meter','composite')
                     NOT NULL COMMENT 'أساس الاستحقاق — الأسس السبعة (§8.2)',

  -- ③ القيم والحدود
  `rate`           DECIMAL(14,4) NOT NULL COMMENT 'معدل الاستحقاق لوحدة الأساس',
  `min_amount`     DECIMAL(14,2) NULL COMMENT 'الحد الأدنى اليومي لهذا الصف — NULL = بلا حد',
  `max_amount`     DECIMAL(14,2) NULL COMMENT 'الحد الأقصى اليومي لهذا الصف — NULL = بلا حد',
  `deductions_note`  VARCHAR(200) NULL COMMENT 'الخصومات — توثيقٌ يقرؤه المخلِّص، لا صيغة تُحسب',
  `exceptions_note`  VARCHAR(200) NULL COMMENT 'الاستثناءات — توثيقٌ يقرؤه المخلِّص',

  -- ④ النطاق — «المشروع أو نوع المعدة، وسياسةٌ افتراضيةٌ عند غياب الخاص»
  `scope_project_id`     INT UNSIGNED NULL COMMENT 'NULL = لا قيد مشروع',
  `scope_equipment_type` INT UNSIGNED NULL COMMENT 'NULL = لا قيد نوع',

  `is_trial`       TINYINT(1) NOT NULL DEFAULT 0
                     COMMENT 'سياسةٌ تجريبيةُ البذر — تُستبدل قيمُها من الشاشة قبل الاستعمال الحقيقي',
  `note`           VARCHAR(200) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME NULL,

  PRIMARY KEY (`id`),
  KEY `ix_emp`   (`company_id`,`employee_id`,`basis`),
  KEY `ix_scope` (`company_id`,`scope_project_id`,`scope_equipment_type`),
  KEY `ix_valid` (`company_id`,`effective_from`,`effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UX-02 §8.2 — سياسات مستحقات المشغّلين: النماذج الثلاثة والأسس السبعة';

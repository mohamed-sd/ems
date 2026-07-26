-- ═══════════════════════════════════════════════════════════════════════════
-- توحيدُ جدول السياسات على UX-02 §15.2-ج — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- **تصحيحُ انحرافٍ أحدثه المنفّذ** (مثبَّتٌ في docs/DOCS_COMPLIANCE_AUDIT_20260727_ar.md):
-- أُنشئ `operator_pay_policies` جدولًا جديدًا بينما النصّان صريحان:
--   • UX-02 §15.2-ج: «جدولُ سياسات ساعات العقود القائم تُعاد استخدامًا
--     وتوسيعًا (ALTER إضافي) **بدل جدولٍ جديد**».
--   • UX-06 §8.1: «توسعةُ UX-02 §15.2-ج القائمة نصًّا — **لا جدولَ سياساتٍ
--     جديدًا**» — وخدمةُ PayrollBasisService تقرأ منه، فبقاءُ الانحراف يجعله
--     جذرًا لفرعٍ كامل.
--
-- قرارات المالك (2026-07-27):
--   ① دمجٌ كاملٌ بجعل ops_state اختياريًّا — جدولٌ واحدٌ بوضعَين يميّزهما party_scope.
--   ② عمودُ المعدّل: «جرّب واختبر ثم قرّر» — التجربةُ حسمت (أدناه).
--   ③ توحيدُ القاموسين الآن (التعارضُ المرفوع منذ تموز).
--
-- ═══ الوضعان في جدولٍ واحد ═══
--   وضعُ حكم الساعة (client · supplier): ops_state + ruling + pct — كما كان.
--   وضعُ سياسة المشغّل (operator):        operator_id + work_model + pay_basis
--                                          + rate + الحدود + النطاق + السريان.
--
-- ═══ عمود rate — قرارٌ بالتجربة لا بالرأي ═══
-- §15.2-ج تُغفل عمودَ المعدّل بينما §8.2 من الوثيقة نفسها تطلب «معدلَ
-- الاستحقاق» في مجموعة «القيم والحدود» — تعارضٌ داخليٌّ في النصّ.
-- التجربةُ المقيسة (2026-07-27): إدخالُ معدّلاتٍ واقعيةٍ في pct decimal(5,2):
--     40 ✔ · 100 ✔ · 858 ✔ · **3500 → 999.99 بُتر** · **14000 → 999.99 بُتر**
-- و3500 قيمةٌ **حقيقيةٌ في contractequipments**. فإعادةُ استعمال pct تفقد
-- المال صامتةً (sql_mode غير صارم). ⇒ عمود `rate` decimal(14,4) مستقل،
-- والتعارضُ النصّي مثبَّتٌ هنا ليراجعه كاتبُ الوثيقة.
--
-- ═══ السريان: إعادةُ استعمالٍ لا ازدواج ═══
-- §15.2-ج تسمّيهما valid_from/valid_to، والجدولُ القائم فيه effective_from/to
-- بالدلالة نفسها سلفًا. إضافةُ زوجٍ ثانٍ تخالف روحَ «أعِد الاستعمال» وتفتح
-- بابَ تناقضٍ بين زوجين — فيُعاد استعمالُ القائم، والخريطةُ موثَّقةٌ هنا:
--     valid_from ≡ effective_from   ·   valid_to ≡ effective_to
--
-- ═══ القيد الفريد — انحرافٌ معلَنٌ عن حرف النصّ بحجته ═══
-- §15.2-ج تنصّ: UQ (operator_id, valid_from) — «سياسةٌ جديدةٌ بسريانٍ لا
-- تعديلٌ رجعي». لكنّ حرفَه يمنع أن يكون للمشغّل أساسان في الفترة نفسها
-- (تشغيلٌ فعليٌّ + استعداد)، والوثيقةُ نفسها في §8.2 تعدّد سبعةَ أسسٍ وتجعل
-- «مركّب» أحدَها — والمركّبُ **بلا صيغةٍ معتمدةٍ بعد** فلا يُحسب.
-- فلو طُبِّق الحرفُ لَتعذّر تمثيلُ مشغّلٍ يُدفع له عن التشغيل والاستعداد معًا.
-- المطبَّق: UQ (company_id, operator_id, work_model, pay_basis, effective_from)
-- — يحقق مقصدَ النصّ (لا تعديلَ رجعيًّا؛ الجديدُ بسريانٍ جديد) ويسمح بالواقع.
-- **مرفوعٌ لكاتب الوثيقة للمراجعة.**
--
-- التطبيق عبر database/migrate.php حصرًا بعميل utf8mb4.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① ops_state يصير اختياريًّا (صفوفُ المشغّل لا حالةَ ساعةٍ لها)
ALTER TABLE `contract_hour_policies`
  MODIFY COLUMN `ops_state`
    ENUM('actual_work','standby','tech_breakdown','supplier_stop','operator_stop',
         'client_stop','fuel_logistics_stop','planned_stop','force_majeure',
         'pending_approval','other','unlogged') NULL DEFAULT NULL
    COMMENT 'حالةُ الساعة (وضع client/supplier) — NULL لصفوف المشغّل. وأُضيف unlogged لتوحيد القاموس';

-- ② أعمدة §15.2-ج (ALTER إضافي — القائمُ لا يُمسّ)
ALTER TABLE `contract_hour_policies`
  ADD COLUMN `operator_id` INT UNSIGNED NULL     COMMENT 'المشغّل (employees) — وضعُ سياسة المشغّل؛ NULL في وضع حكم الساعة' AFTER `contract_ref`,
  ADD COLUMN `work_model` VARCHAR(16) NULL     COMMENT '§15.2-ج: hour·ton·trip·meter' AFTER `operator_id`,
  ADD COLUMN `pay_basis` VARCHAR(16) NULL     COMMENT '§15.2-ج: actual·standby·attendance·ton·trip·meter·composite' AFTER `work_model`,
  ADD COLUMN `rate` DECIMAL(14,4) NULL     COMMENT 'معدلُ الاستحقاق لوحدة الأساس (§8.2) — عمودٌ مستقلٌّ لأن pct(5,2) يبتر فوق 999.99' AFTER `pay_basis`,
  ADD COLUMN `min_amount` DECIMAL(18,2) NULL     COMMENT '§15.2-ج: الحد الأدنى اليومي' AFTER `rate`,
  ADD COLUMN `max_amount` DECIMAL(18,2) NULL     COMMENT '§15.2-ج: الحد الأقصى اليومي — قيدُ min ≤ max يُفرض بالتطبيق' AFTER `min_amount`,
  ADD COLUMN `scope_type` VARCHAR(16) NULL     COMMENT '§15.2-ج: project·equip_type — NULL = سياسةٌ افتراضية' AFTER `max_amount`,
  ADD COLUMN `scope_id` INT UNSIGNED NULL     COMMENT '§15.2-ج: معرّفُ النطاق المقابل لـscope_type' AFTER `scope_type`,
  ADD COLUMN `currency` VARCHAR(8) NULL     COMMENT '§15.2-ج: عملةُ المعدّل — لا جمعَ عملتين' AFTER `scope_id`,
  ADD COLUMN `deductions_note` VARCHAR(200) NULL     COMMENT '§8.2 القيم والحدود: الخصومات — توثيقٌ يقرؤه المخلِّص' AFTER `currency`,
  ADD COLUMN `exceptions_note` VARCHAR(200) NULL     COMMENT '§8.2: الاستثناءات — توثيقٌ يقرؤه المخلِّص' AFTER `deductions_note`,
  ADD COLUMN `approved_at` DATETIME NULL     COMMENT '§8.2 الهوية والسريان: تاريخ اعتماد السياسة' AFTER `exceptions_note`,
  ADD COLUMN `approved_by` INT UNSIGNED NULL AFTER `approved_at`,
  ADD COLUMN `is_trial` TINYINT(1) NOT NULL DEFAULT 0     COMMENT 'سياسةٌ تجريبيةُ البذر — تُستبدل قيمُها قبل الاستعمال الحقيقي' AFTER `approved_by`,
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0     COMMENT 'عقدُ البوابة الثلاثي (is_deleted/deleted_at/deleted_by)' AFTER `note`,
  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`;

-- ③ القيدُ القديم يقبل NULL في ops_state (MySQL: UNIQUE لا يقيّد NULLات) —
--    فيبقى حارسًا لوضع حكم الساعة وحده، ويُضاف قيدُ وضع المشغّل.
ALTER TABLE `contract_hour_policies`
  ADD UNIQUE KEY `uq_operator_policy`
    (`company_id`, `operator_id`, `work_model`, `pay_basis`, `effective_from`),
  ADD KEY `ix_operator_lookup` (`company_id`, `operator_id`, `effective_from`, `effective_to`);

-- ④ ترحيلُ صفوف operator_pay_policies بأسماء الوثيقة وقيمها
--    (basis→pay_basis · actual_work→actual · النطاقان→scope_type+scope_id)
INSERT INTO `contract_hour_policies`
  (`company_id`, `party_scope`, `contract_ref`, `operator_id`, `work_model`, `pay_basis`,
   `rate`, `min_amount`, `max_amount`, `scope_type`, `scope_id`, `currency`,
   `deductions_note`, `exceptions_note`, `approved_at`, `approved_by`, `is_trial`,
   `ops_state`, `ruling`, `pct`, `effective_from`, `effective_to`, `note`,
   `is_deleted`, `deleted_at`, `deleted_by`, `created_by`, `created_at`, `updated_at`)
SELECT
  o.`company_id`, 'operator', o.`contract_ref`, o.`employee_id`, o.`work_model`,
  CASE o.`basis` WHEN 'actual_work' THEN 'actual' ELSE o.`basis` END,
  o.`rate`, o.`min_amount`, o.`max_amount`,
  CASE WHEN o.`scope_project_id` IS NOT NULL THEN 'project'
       WHEN o.`scope_equipment_type` IS NOT NULL THEN 'equip_type' ELSE NULL END,
  COALESCE(o.`scope_project_id`, o.`scope_equipment_type`),
  o.`currency`, o.`deductions_note`, o.`exceptions_note`, o.`approved_at`, o.`approved_by`,
  o.`is_trial`,
  NULL, 'full', NULL,          -- لا حالةَ ساعةٍ لصف المشغّل؛ ruling إلزاميٌّ فيُملأ محايدًا
  o.`effective_from`, o.`effective_to`, o.`note`,
  COALESCE(o.`is_deleted`, 0), o.`deleted_at`, o.`deleted_by`,
  o.`created_by`, o.`created_at`, o.`updated_at`
FROM `operator_pay_policies` o;

-- ⑤ توحيدُ القاموسين (قرار المالك ③): حالةُ unlogged صار لها حكمٌ صريحٌ
--    للعميل والمورد — فلا تمرّ ساعةٌ بلا حكم (القطعُ الصامت الموثَّق في
--    unit_source_tables_test يُغلق هنا).
INSERT INTO `contract_hour_policies`
  (`company_id`, `party_scope`, `ops_state`, `ruling`, `note`, `created_at`, `updated_at`)
SELECT c.`company_id`, c.`ps`, 'unlogged', 'case_by_case',
       'ساعةٌ غيرُ مصنَّفة — تُصنَّف قبل الحكم (توحيد القاموسين 2026-07-27)',
       NOW(), NOW()
FROM (
  SELECT DISTINCT p.`company_id`, s.`ps`
  FROM `contract_hour_policies` p
  JOIN (SELECT 'client' AS ps UNION ALL SELECT 'supplier') s
  WHERE p.`party_scope` IN ('client','supplier')
) c
WHERE NOT EXISTS (
  SELECT 1 FROM `contract_hour_policies` x
  WHERE x.`company_id` = c.`company_id` AND x.`party_scope` = c.`ps`
    AND x.`ops_state` = 'unlogged'
);

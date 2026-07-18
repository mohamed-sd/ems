-- ═══════════════════════════════════════════════════════════════════════════
-- D02 §3.8: سياسة استحقاق عقد الساعة — بإصداراتها
-- ───────────────────────────────────────────────────────────────────────────
-- قرار الإدارة نصًّا: «لا يجوز للنظام اعتماد قاعدةٍ ماليةٍ واحدةٍ لجميع
-- العملاء» — فلكل عقدٍ سياسةُ استحقاقٍ مستقلة تحدّد حكم كل حالةٍ تشغيلية.
-- والنظام يوفّر سياسةً افتراضيةً (contract_ref = NULL) تُنسخ عند إنشاء العقد،
-- **والعقدُ المعتمد هو المرجع النهائي**.
--
-- ⚠️ النطاق: هذه السياسة تخصّ **عقود التأجير بالساعة وحدها** (قرار الإدارة:
-- «في عقود النقل بالطن والتخريم بالمتر لا تُفوتر ساعات الاستعداد أو التوقف
-- على العميل؛ لأن استحقاقه يُحسب بالطن أو المتر المنجز والمعتمد»). فالمحرّك
-- يتخطّاها كليًّا لعقود الطن والمتر ويأخذ الكمية المنجزة كما هي.
--
-- ⚠️ الإصدارات (قرار المستخدم 2026-07-18): تعديلُ السياسة لا يمسّ أحكامًا
-- سابقة. آليتان متكاملتان: `effective_from/to` هنا يحدّدان السارية بتاريخ
-- العمل، و`unit_party_awards.policy_snapshot` يحفظ القاعدة المطبَّقة **لحظة
-- الحكم**. فيُجاب سؤال المدقّق: «لماذا استحقّ هذا العميل 8 ساعاتٍ في مارس
-- و6 في أبريل بالظروف نفسها؟» — وهو معيار أنظمة الفوترة.
--
-- معنى النسبة (قرار الإدارة): **نصف الكمية بالسعر الكامل** لا العكس —
-- أي qty_due = award_qty × pct، وهو ما بُني عليه العمود المولَّد سلفًا.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_hour_policies` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,

  -- لكل طرفٍ سياستُه: ساعةٌ مستحقةٌ للعميل قد لا تكون مستحقةً للمورد
  `party_scope`    ENUM('client','supplier','operator') NOT NULL,
  `contract_ref`   INT UNSIGNED NULL COMMENT 'NULL = السياسة الافتراضية للشركة (تُنسخ عند إنشاء العقد)',

  -- الحالة التشغيلية كما يصنّفها سجلّ الدوام (D02 §3.5)
  `ops_state`      ENUM('actual_work','standby','tech_breakdown','supplier_stop',
                        'operator_stop','client_stop','fuel_logistics_stop',
                        'planned_stop','force_majeure','pending_approval','other')
                     NOT NULL,

  -- الحكم التعاقدي لهذه الحالة عند هذا الطرف
  `ruling`         ENUM('full','pct','none','pending','case_by_case') NOT NULL,
  `pct`            DECIMAL(5,2) NULL COMMENT 'عند ruling=pct — نسبةٌ من الكمية لا من السعر',

  -- الإصدارات: السارية بتاريخ العمل (NULL في from = سارية منذ الأزل)
  `effective_from` DATE NULL,
  `effective_to`   DATE NULL,

  `note`           VARCHAR(200) NULL COMMENT 'بند العقد أو سببُ الحكم',
  `created_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME NULL COMMENT 'حذفٌ ناعم — شرط بوابة المستأجر',

  PRIMARY KEY (`id`),
  -- قاعدةٌ واحدةٌ لكل (طرف + عقد + حالة + بداية سريان): التعديل إصدارٌ جديد
  UNIQUE KEY `uq_policy_rule` (`company_id`,`party_scope`,`contract_ref`,`ops_state`,`effective_from`),
  KEY `ix_lookup` (`company_id`,`party_scope`,`contract_ref`,`ops_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D02 §3.8 — سياسة استحقاق عقد الساعة لكل طرفٍ وحالةٍ بإصداراتها';

-- ── السياسة الافتراضية للشركة ─────────────────────────────────────────────
-- ⚠️ هذه **افتراضاتٌ صالحةٌ للتشغيل والعرض، لا بديلٌ عن نصوص العقود**. القاعدة
-- الحاكمة: ما كان حكمُه رهنَ نصٍّ تعاقديٍّ يُترك `case_by_case` فيُعلن المحرّك
-- تعذّرَه بسببه بدل أن يخترع رقمًا (قاعدة عدم التلفيق). والمؤكَّد وحده يُحسم:
--   • تشغيلٌ فعلي        ⇒ مستحقٌّ كاملًا للطرفين (لا خلاف)
--   • عطلٌ فني / غياب مشغّل ⇒ غير مستحقٍّ (الخدمة لم تُقدَّم بسببٍ علينا)
--   • استعدادٌ وتوقف عميل  ⇒ case_by_case — «تختلف من عقد إلى آخر» نصًّا
--   • توقف المورد        ⇒ غير مستحقٍّ للعميل؛ ولا مستحقَ للمورد عن توقفه
--   • قوة قاهرة          ⇒ معلّقةٌ للتسوية حالةً بحالة وفق بند العقد
INSERT INTO `contract_hour_policies`
  (`company_id`,`party_scope`,`contract_ref`,`ops_state`,`ruling`,`pct`,`note`)
SELECT c.id, s.party_scope, NULL, s.ops_state, s.ruling, s.pct, s.note
FROM (SELECT DISTINCT company_id AS id FROM contracts WHERE company_id IS NOT NULL) c
CROSS JOIN (
  -- العميل
  SELECT 'client' AS party_scope, 'actual_work'      AS ops_state, 'full'         AS ruling, NULL AS pct, 'تشغيلٌ فعليٌّ في النشاط المتفق عليه' AS note
  UNION ALL SELECT 'client','standby',          'case_by_case', NULL, 'يحسمه بند الاستعداد في عقد العميل'
  UNION ALL SELECT 'client','client_stop',      'case_by_case', NULL, 'يحسمه بند توقف العميل في عقده'
  UNION ALL SELECT 'client','pending_approval', 'pending',      NULL, 'مطالبةٌ معلّقةٌ حتى اعتماد العميل أو التسوية'
  UNION ALL SELECT 'client','tech_breakdown',   'none',         NULL, 'عطلٌ علينا — الخدمة لم تُقدَّم'
  UNION ALL SELECT 'client','operator_stop',    'none',         NULL, 'غياب مشغّلٍ — سببٌ على الشركة'
  UNION ALL SELECT 'client','supplier_stop',    'none',         NULL, 'توقفٌ سببه المورد — لا يُحمَّل على العميل'
  UNION ALL SELECT 'client','planned_stop',     'case_by_case', NULL, 'يحسمه حد السماح أو بند الصيانة في العقد'
  UNION ALL SELECT 'client','force_majeure',    'pending',      NULL, 'تسويةٌ حالةً بحالة وفق بند القوة القاهرة'
  UNION ALL SELECT 'client','fuel_logistics_stop','none',       NULL, 'لوجستياتٌ علينا ما لم ينصّ العقد'
  UNION ALL SELECT 'client','other',            'case_by_case', NULL, 'يلزم تصنيفٌ صريحٌ قبل الحكم'
  -- المورد (مستقلٌّ تمامًا عن العميل)
  UNION ALL SELECT 'supplier','actual_work',      'full',         NULL, 'ساعةُ تشغيلٍ فعليٍّ مستحقةٌ للمورد'
  UNION ALL SELECT 'supplier','standby',          'case_by_case', NULL, 'يحسمه بند الاستعداد في عقد المورد — قد يخالف العميل'
  UNION ALL SELECT 'supplier','client_stop',      'case_by_case', NULL, 'قد تكون مستحقةً للمورد ولو لم تُفوتر للعميل'
  UNION ALL SELECT 'supplier','pending_approval', 'pending',      NULL, 'بانتظار حسم المطالبة'
  UNION ALL SELECT 'supplier','tech_breakdown',   'none',         NULL, 'عطلٌ فني — لا مستحقَ عن ساعة توقف'
  UNION ALL SELECT 'supplier','operator_stop',    'none',         NULL, 'غياب مشغّل — لا مستحق'
  UNION ALL SELECT 'supplier','supplier_stop',    'none',         NULL, 'توقفٌ سببه المورد — ولا يُخصم قبل تحديد المسؤولية'
  UNION ALL SELECT 'supplier','planned_stop',     'case_by_case', NULL, 'يحسمه عقد المورد'
  UNION ALL SELECT 'supplier','force_majeure',    'pending',      NULL, 'تسويةٌ وفق بند عقد المورد'
  UNION ALL SELECT 'supplier','fuel_logistics_stop','case_by_case',NULL,'حسب مسؤولية التزويد في عقد المورد'
  UNION ALL SELECT 'supplier','other',            'case_by_case', NULL, 'يلزم تصنيفٌ صريحٌ قبل الحكم'
) s
WHERE NOT EXISTS (
  SELECT 1 FROM `contract_hour_policies` p
   WHERE p.company_id = c.id AND p.party_scope = s.party_scope
     AND p.contract_ref IS NULL AND p.ops_state = s.ops_state
);

-- ═══════════════════════════════════════════════════════════════════════════
-- D02 §2.6 · المستويات الأربعة: واقعةٌ واحدة وأحكامُ أطرافٍ مستقلة
-- ───────────────────────────────────────────────────────────────────────────
-- المبدأ الحاكم (قرار الإدارة 2026-07-18): «يبدأ النظام من الواقعة التشغيلية
-- الواحدة، ثم يطبّق بصورةٍ مستقلة: عقد العميل، وعقد المورد، وعقد المشغّل،
-- وعقد المشرف. لا يُشترط تطابق الاستحقاقات بين الأطراف؛ فالتطابق في الحقيقة
-- التشغيلية فقط، أما وصفُها التعاقدي وأثرُها المالي فيحدّده عقدُ كل طرف.»
--
-- الواقع المقيس على بيانات co4 قبل التنفيذ — الحالة المختلطة هي الغالبة:
--   • 184 صفًّا: عقد العميل بالمتر الطولي · عقد المورد بالساعة (في الواقعة نفسها)
--   • 31 صفًّا:  الطرفان بالساعة
-- فالمحرّك القائم — الذي يستنتج وحدةً واحدةً ويشترط مطابقة العقدين لها —
-- عاجزٌ بنيويًّا عن الحالة الغالبة. هذا الجدول يرفع ذلك العجز.
--
-- ⚠️ حدّ هذه المهاجرة: تُنشئ البنية ولا تولّد حكمًا. توليد الأحكام في المحرّك
-- (EffectFanout) بمهاجرةٍ لاحقةٍ لخريطة الأثر — فالبنية تسبق السلوك.
--
-- التطبيق بعميل utf8mb4 عبر database/migrate.php حصرًا (لا DDL يدوي).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① توسيع نوع الأثر — إلحاقٌ بذيل الـENUM حصرًا ──────────────────────────
-- گوتشا حاكمة: effect_type من نوع ENUM لا varchar. الكتابة بقيمةٍ خارجه
-- **لا تُخطئ — بل تُخزَّن سلسلةً فارغة**، فيبدو الرابط ناجحًا وتنكسر العطالة
-- صامتةً. لذلك يسبق هذا التوسيعُ أيَّ كتابةٍ لحكمٍ جديد.
-- القيم التسع القائمة تبقى بمواضعها كما هي — الإضافة في الذيل فقط.
ALTER TABLE `fin_event_links`
  MODIFY COLUMN `effect_type` ENUM(
    'revenue_event','supplier_due','employee_due','cost_record','receivable',
    'journal_entry','payment','metric_update','budget_consumption',
    'party_award'
  ) NOT NULL;

-- ── ② جدول أحكام استحقاق الأطراف ───────────────────────────────────────────
-- صفٌّ لكل طرفٍ عن كل واقعة: بوحدة عقده هو، وكميته هو، ونسبته هو، وحالته هو.
-- فلا رقمَ وحداتٍ واحدًا يخدم العميل والمورد والمشغّل — وهو الخلل الذي يعالجه.
--
-- انحرافان مقصودان عن مواصفة الدستور §3.7، وسببهما:
--   • unit_price + currency: المواصفة أغفلتهما، والحكم بلا سعرٍ ولا عملةٍ لا
--     يُنتج مالًا. والدرس مدفوعٌ سلفًا — fin_cost_records اضطُرّ لاكتساب
--     currency لاحقًا حين تبيّن أن جمع الجنيه فوق الدولار خطأ.
--   • policy_snapshot: المواصفة تحفظ اسم البند (policy_rule) لا القاعدة. فإذا
--     عُدّلت سياسة العقد لاحقًا تعذّر إثباتُ أي قاعدةٍ حكمت على واقعةٍ قديمة.
--     اللقطة تجيب سؤال المدقّق: «لماذا استحقّ 8 في مارس و6 في أبريل؟»
CREATE TABLE IF NOT EXISTS `unit_party_awards` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,

  -- ── مرجع الواقعة: مصدرها ومعرّفها (لا FK — المصدر قد يكون timesheet أو وحدة) ──
  `source_kind`       ENUM('timesheet','unit_record') NOT NULL,
  `source_ref`        INT UNSIGNED NOT NULL COMMENT 'معرّف الصف في جدول المصدر',

  -- ── الطرف وعقده ──
  `party`             ENUM('client','supplier','operator','supervisor') NOT NULL,
  `party_ref`         INT UNSIGNED NULL COMMENT 'العميل/المورد/الموظف حسب الطرف',
  `contract_ref`      INT UNSIGNED NULL COMMENT 'عقد هذا الطرف — أو سياسة حافزه',

  -- ── حكم هذا الطرف: بوحدة عقده هو ──
  `award_unit_type`   ENUM('hour','ton','meter','cbm','day','shift','trip') NOT NULL,
  `award_qty`         DECIMAL(14,2) NOT NULL COMMENT 'الكمية بوحدة عقد هذا الطرف',
  `entitlement_state` ENUM('due','partial','not_due','pending','rejected','settlement')
                        NOT NULL DEFAULT 'due',
  `entitlement_pct`   DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `qty_due`           DECIMAL(14,2)
                        GENERATED ALWAYS AS (ROUND(`award_qty` * `entitlement_pct` / 100, 2)) STORED
                        COMMENT 'محسوبٌ خادميًّا — لا يُكتب يدويًّا',

  -- ── التسعير: بعملة عقد هذا الطرف كما وقعت، بلا تحويل ──
  `unit_price`        DECIMAL(14,2) NULL COMMENT 'سعر وحدة عقد هذا الطرف',
  `currency`          VARCHAR(8) NULL COMMENT 'عملة عقده — لا تُجمع فوق غيرها',

  -- ── أثر السياسة وتتبّعها ──
  `policy_rule`       VARCHAR(60) NULL COMMENT 'اسم البند المطبَّق',
  `policy_snapshot`   JSON NULL COMMENT 'لقطة القاعدة وقت الحكم — للتدقيق الرجعي',
  `unavailable_reason` VARCHAR(200) NULL COMMENT 'سبب التعذّر معلنًا — لا رقمَ ملفَّق',

  `created_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL COMMENT 'حذفٌ ناعم — شرط بوابة المستأجر',

  PRIMARY KEY (`id`),
  -- العطالة بقيدٍ في القاعدة لا بفحصٍ في الكود: حكمٌ واحدٌ لكل طرفٍ عن كل واقعة.
  -- يصمد أمام التزامن والتنفيذ المتوازي — بخلاف الفحص قبل الإدراج.
  UNIQUE KEY `uq_party_award` (`company_id`,`source_kind`,`source_ref`,`party`),
  KEY `ix_state`  (`company_id`,`party`,`entitlement_state`),
  KEY `ix_source` (`company_id`,`source_kind`,`source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D02 §3.7 — أحكام استحقاق الأطراف: لكل طرفٍ وحدتُه وكميتُه ونسبتُه';

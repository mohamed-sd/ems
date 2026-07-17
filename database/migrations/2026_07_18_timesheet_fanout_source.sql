-- ═══════════════════════════════════════════════════════════════════════════
-- D02 م1-①: المروحة تقرأ من سجلّ الدوام — مصدر timesheet في محرّك تفريع الأثر
-- ───────────────────────────────────────────────────────────────────────────
-- دستور الوحدة التشغيلية (EQUIP-UNT-D02): «كل تجميعٍ لاحقٍ قراءةٌ مشتقةٌ من
-- سجلّ الوحدات اليومي — لا إدخالَ موازيًا». صفُّ الدوام المعتمد (المستوى الرابع)
-- يولّد مروحته: إيراد العميل + مستحق المورد + تكلفة المعدة والمشروع.
--
-- قرارات التسعير (فحص 2026-07-18 على بيانات co4):
--   • السعر من سطر معدة العقد (contractequipments/suppliercontractequipments
--     بمفتاح contract_id + equip_type) والعملة من البيانات الأساسية للعقد
--     (price_currency_contract) — قرار المستخدم النصّي.
--   • الأثر يُكتب بعملة عقده كما وقعت (لا تحويل ولا سعر صرفٍ مخترَع) —
--     فجدول fin_cost_records يكسب عمود currency (الصفوف القائمة SDG بحق).
--   • عقد المورد: الساري بتاريخ العمل، وإلا النشطُ الوحيد، وإلا تعذّرٌ معلن
--     (عقدا المورد 3 بسعرين مختلفين 858/10 — الغموض حقيقي فلا يُخمَّن).
--   • وحدة الفوترة تُطابَق: عقدٌ يفوتر بالمتر وصفٌّ سجّل ساعاتٍ فقط ⇒ لا
--     تسعير ملفَّق — أثرٌ معلَن التعذّر (184 صف co4 حالتها هذه اليوم).
--
-- parent_kind في fin_event_links يكسب 'timesheet' — إلحاقٌ بذيل الـENUM حصرًا
-- (القيم القائمة بمواضعها لا تمسّ). التطبيق بعميل utf8mb4 (migrate.php).
-- ═══════════════════════════════════════════════════════════════════════════

-- ① دفتر الروابط: الأب الجديد timesheet (ذيل الـENUM)
ALTER TABLE `fin_event_links`
  MODIFY COLUMN `parent_kind` ENUM('request','unit_record','event','timesheet')
    COLLATE utf8mb4_unicode_ci NOT NULL;

-- ② عملة سجلّ التكلفة: الأثر بعملة عقده — القائم كله SDG بحق (مصدره وحدات SDG)
ALTER TABLE `fin_cost_records`
  ADD COLUMN `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG'
    COMMENT 'عملة العقد مصدر التكلفة كما وقعت — لا تحويل عند التسجيل' AFTER `total_cost`;

-- ③ خريطة الآثار لمصدر الدوام — لكل شركةٍ لها مستخدمون (إدراج عاطل بالمفتاح الفريد)
INSERT INTO `fin_effect_map`
  (`company_id`, `source_kind`, `effect_type`, `effect_label`, `target_table`, `is_active`, `param_value`, `unavailable_reason`, `display_order`)
SELECT c.id, m.source_kind, m.effect_type, m.effect_label, m.target_table, m.is_active, m.param_value, m.unavailable_reason, m.display_order
FROM (SELECT DISTINCT company_id AS id FROM users WHERE company_id IS NOT NULL) c
CROSS JOIN (
    SELECT 'timesheet' AS source_kind, 'revenue_event' AS effect_type,
           'إيراد على العميل من يوم الدوام بسعر عقده' AS effect_label,
           'fin_financial_events' AS target_table, 1 AS is_active, NULL AS param_value,
           NULL AS unavailable_reason, 1 AS display_order
    UNION ALL SELECT 'timesheet', 'supplier_due', 'مستحق المورد من يوم الدوام بسعر عقده',
           'fin_dues', 1, NULL, NULL, 2
    UNION ALL SELECT 'timesheet', 'cost_record', 'تكلفة يوم الدوام على المعدة والمشروع',
           'fin_cost_records', 1, NULL, NULL, 3
    UNION ALL SELECT 'timesheet', 'employee_due', 'مستحق المشغّل عن ساعاته المسجّلة',
           'fin_dues', 0, NULL,
           'ساعات المشغّل مسجّلة (timesheet.operator_hours) لكن قاعدة الأجر/الحافز قرارٌ إداريٌّ لم يُقَرّ — يُفعَّل يوم تُضبط القاعدة ولا تُلفَّق مستحقات.', 4
    UNION ALL SELECT 'timesheet', 'metric_update', 'مخصّص صيانةٍ محمَّلٌ على المعدة',
           'fin_cost_records', 0, 0.0000,
           'معدّل مخصّص الصيانة للوحدة قرارٌ محاسبي — اضبط param_value بقيمةٍ موجبة لتفعيله.', 5
) m
WHERE NOT EXISTS (
    SELECT 1 FROM `fin_effect_map` e
    WHERE e.company_id = c.id AND e.source_kind = m.source_kind AND e.effect_type = m.effect_type
);

<?php
/**
 * سجل الجداول — قلب مبدأ Fail-Closed في بوابة العزل (ADR-02 · المرحلة 1)
 * ───────────────────────────────────────────────────────────────────────────
 * كل جدولٍ في القاعدة مصنَّفٌ هنا صراحةً. أي جدولٍ غير مسجَّل تُرفض كل
 * عملياته عبر البوابة (TenantGateException + تسجيل tenant_gate_violation) —
 * الجدول الجديد يُسجَّل واعيًا مع هجرته، لا افتراضات صامتة.
 *
 * الفئات:
 *   T_TENANT     بيانات مستأجرٍ (يحمل company_id) — يُحقن العزل قراءةً وكتابة.
 *   T_CHILD      ابنٌ بلا company_id — يُعزل عبر أبيه (EXISTS على الأب). إن كان
 *                الأب T_CATALOG فالعزل عبره «عامّ أو مِلكي» (قراءةً).
 *   T_CATALOG    كتالوجٌ مشترك: صفوفٌ عامّة (company_id=NULL للجميع) + صفوف كل
 *                شركة. القراءة «العامّ أو مِلكي»؛ الكتابة التعديلية «مِلكي حصرًا»
 *                (لا تُعدَّل/تُحذَف الصفوف العامّة)؛ الإدراج يُحقن شركة السياق.
 *   T_GLOBAL     مرجع نظامٍ عام — قراءته متاحة، كتابته للمدير الأعلى فقط.
 *   T_RESTRICTED جداول منصةٍ/محرّكاتٍ لم تُهاجَر بعد — البوابة ترفضها كليًا
 *                حتى يُعرَّف عقدها مع هجرة وحدتها (fail-closed).
 *
 * 'soft' = يملك أعمدة الحذف الناعم (الحذف عبر البوابة = ناعم حصريًا؛
 * والقراءة تستبعد المحذوف افتراضيًا).
 *
 * القسم T_TENANT مولَّدٌ آليًا من information_schema (2026-07-07 · 121 جدولًا
 * بعد استبعاد جدول اختبارٍ شارد). عدّله بوعي: إضافة جدولٍ = قرار تصنيفٍ
 * موثَّق في العقد docs/TENANT_GATE_CONTRACT_ar.md §3.
 */

namespace App\Core;

class TenantRegistry
{
    const T_TENANT = 'tenant';
    const T_CHILD = 'child';
    const T_CATALOG = 'catalog';
    const T_GLOBAL = 'global';
    const T_RESTRICTED = 'restricted';
    /** طبقة المزوّد (دفعة هـ-0): جداول كونسول المنصّة — الوصول عبر بوابةٍ عابرة
     *  (crossTenant من جلسة المدير الأعلى عبر ems_platform_db حصرًا)؛ بوابةُ
     *  المستأجر ترفضها كالمقيَّدة تمامًا (عبور مراقبةٍ مُسجَّل خارج الإنفاذ). */
    const T_PLATFORM = 'platform';

    private static $tables = array(
        // ── بيانات المستأجرين (مولَّد من القاعدة الحية) ─────────────────────
        'activities' => array('type' => self::T_TENANT, 'soft' => true),
        'activity_logs' => array('type' => self::T_TENANT, 'soft' => false),
        'admin_subscription_requests' => array('type' => self::T_TENANT, 'soft' => false),
        'audit_logs' => array('type' => self::T_TENANT, 'soft' => false),
        'clients' => array('type' => self::T_TENANT, 'soft' => true),
        'commercial_risks' => array('type' => self::T_TENANT, 'soft' => true),
        'contract_amendments' => array('type' => self::T_TENANT, 'soft' => true),
        // INJAZ-S05 §ت.2: التزامات العقد — عائلة «الوحدة التعاقدية» (مع contract_hour_policies/unit_party_awards)
        'contract_commitments' => array('type' => self::T_TENANT, 'soft' => true),
        // CON-02 §4/§8 (هجرة 2026-07-28): مصفوفةُ التزامات عقد العميل — بندٌ ×
        // ملتزمٌ × أثرٌ على الفوترة. بياناتُ مستأجرٍ تحمل company_id، وsoft=true
        // لأن تغيير الملتزم قرارٌ تعاقديٌّ يُراجَع ويُعكس فلا يُمحى من سجل التدقيق.
        'contract_obligations' => array('type' => self::T_TENANT, 'soft' => true),
        // CON-02 §6/§8 (هجرة 2026-07-28 · المستوى المتوسط ①): قواعدُ الجزاء
        // والحافز — نوعا غرامةٍ ونوعا حافزٍ بسقفٍ ومرساةٍ وسريان. بياناتُ مستأجرٍ
        // تحمل company_id، وsoft=true لأن قاعدةَ الجزاء نصُّ عقدٍ يُراجَع ويُعكس
        // فلا يُمحى من سجل التدقيق (نفسُ حجّة contract_obligations).
        'contract_penalty_rules' => array('type' => self::T_TENANT, 'soft' => true),
        // CON-02 §6 (هجرة 2026-07-28 · المستوى المتوسط ④): احتسابُ الجزاء/الحافز
        // لفترةٍ بعينها بدورة اعتماده (ق-13). soft=true لأن الاحتسابَ المُجاز
        // أنتج قيدًا في الدفتر — فمحوُه يقطع أثرَ التدقيق عن قيدٍ حيّ.
        'contract_penalty_assessments' => array('type' => self::T_TENANT, 'soft' => true),
        // UX-08 §5.2/§8.1: المستخلص وبنوده — مطالبةُ الفترة من الوحدات المعتمدة.
        // كلاهما يحمل company_id (البنودُ كذلك عمدًا: تُقرأ مجمَّعةً في التقارير
        // بلا JOIN على رأسها، وحقنُ العزل المباشر أرخصُ وأأمن من EXISTS الأب).
        'claims' => array('type' => self::T_TENANT, 'soft' => true),
        'claim_lines' => array('type' => self::T_TENANT, 'soft' => false),
        // H-01 §4 (المرحلة ①): حاوياتُ العقد ومشتقاتُها. الحاوياتُ والدورات
        // `soft` (بنيةُ عقدٍ تُراجَع وتُعكس فلا تُمحى)، والاستهلاكُ والتبديلُ
        // سجلّان إلحاقيّان لا حذفَ فيهما.
        // H-05 §2-③ (هجرة 2026-07-30): الموقعُ/المنجم كيانُ الهرم الثالث —
        // ابنُ المشروع وأبو العقود. بياناتُ مستأجرٍ بcompany_id، وsoft=true
        // لأن الموقعَ مرجعُ تسجيلٍ تاريخيٍّ لا يُمحى (تُقرأ به الوحداتُ القديمة).
        'sites' => array('type' => self::T_TENANT, 'soft' => true),
        // M-25 (UX-10 §8): سلسلةُ قراءات العدّاد — **للإدراج فقط**، والتصحيحُ
        // قراءةٌ جديدةٌ أو تصفيرٌ موثَّقٌ يفتح سلسلةً، لا محوٌ لماضٍ.
        'meter_readings' => array('type' => self::T_TENANT, 'soft' => false),
        'op_containers' => array('type' => self::T_TENANT, 'soft' => true),
        // H-09-① (ENT-01 §8): مسيّرُ الرواتب — الرأسُ ناعمُ الإخفاء، والأسطرُ
        // وقائعُ احتسابٍ تُكنس بالسلسلة من رأسها لا بحذفٍ ناعمٍ مستقل.
        'payroll_runs' => array('type' => self::T_TENANT, 'soft' => true),
        'payroll_lines' => array('type' => self::T_TENANT, 'soft' => false),
        'payroll_run_blocks' => array('type' => self::T_TENANT, 'soft' => false),
        // H-09-② (ENT-01 §4): كتالوجُ أنواع الغياب ومدخلاتُ الزمن بمستنداتها.
        'payroll_absence_types' => array('type' => self::T_TENANT, 'soft' => false),
        'payroll_time_inputs' => array('type' => self::T_TENANT, 'soft' => false),
        // H-09-④ (ENT-01 §4): بوابةُ السلفيات وخصومُ المسيّر وحدُّ حماية الصافي.
        'employee_advances' => array('type' => self::T_TENANT, 'soft' => true),
        'payroll_deductions' => array('type' => self::T_TENANT, 'soft' => false),
        'payroll_settings' => array('type' => self::T_TENANT, 'soft' => false),
        'container_consumption' => array('type' => self::T_TENANT, 'soft' => false),
        'container_swaps' => array('type' => self::T_TENANT, 'soft' => false),
        'operator_rotations' => array('type' => self::T_TENANT, 'soft' => false),
        // M-01: الدفعةُ المقدَّمة المقبوضةُ فعلًا — سلفةٌ تُستردّ لا إيراد.
        // `soft` لأنها مستندُ قبضٍ لا يُمحى: الإلغاءُ حالةٌ لا حذف.
        'contract_advances' => array('type' => self::T_TENANT, 'soft' => true),
        // M-02: الإشعارُ الدائن/المدين — يصحّح فاتورةً صادرةً بلا أن يمسّها.
        // `soft` لأنه مستندٌ ماليٌّ لا يُمحى: الإلغاءُ حالةٌ لا حذف.
        'credit_debit_notes' => array('type' => self::T_TENANT, 'soft' => true),
        'contract_events' => array('type' => self::T_TENANT, 'soft' => true),
        'contract_notes' => array('type' => self::T_TENANT, 'soft' => false),
        'contractequipments' => array('type' => self::T_TENANT, 'soft' => false),
        'contracts' => array('type' => self::T_TENANT, 'soft' => true),
        // M-09 (CON-02 §2-③): شرطُ تعديل السعر وقراءاتُ مؤشره وسجلُّ مراجعاته.
        // المراجعاتُ والقراءاتُ **للإدراج فقط** (لا حذفَ ناعمًا — سجلُّ وقائع).
        'contract_price_terms' => array('type' => self::T_TENANT, 'soft' => true),
        'contract_price_index_readings' => array('type' => self::T_TENANT, 'soft' => true),
        'contract_price_revisions' => array('type' => self::T_TENANT, 'soft' => false),
        'driver_contract_notes' => array('type' => self::T_TENANT, 'soft' => false),
        // H-08-① (هجرة 2026-08-16 · CON-01 §7.1): رأسُ العقد الموحّد لعقود
        // الأشخاص — بياناتُ مستأجرٍ بcompany_id (صاحبُ العمل)، وsoft=true لأن
        // العقدَ مستندٌ لا يُمحى: الإنهاءُ والأرشفةُ حالاتٌ في آلته لا حذف.
        'employee_contracts' => array('type' => self::T_TENANT, 'soft' => true),
        // H-08-② (هجرة 2026-08-18 · CON-01 §3.2): مكوّناتُ الأجر — ابنُ الرأس
        // بcompany_id مباشرٍ (سابقة claim_lines)، وsoft=true لأن المكوّنَ نصُّ
        // عقدٍ يُستبدل بحالةٍ (replaced/ended) ولا يُمحى من أثر التدقيق.
        'pay_components' => array('type' => self::T_TENANT, 'soft' => true),
        // H-08-③ (هجرة 2026-08-19 · CON-01 §3.3): قواعدُ الحوافز — نصُّ عقدٍ
        // يُستبدل بحالةٍ لا يُمحى (soft)؛ والتوزيعُ ابنُ قاعدته يُستبدل ذريًّا
        // (replaceChildren يكنسه صلبًا داخل معاملته — فلا soft له).
        'incentive_rules' => array('type' => self::T_TENANT, 'soft' => true),
        'incentive_allocations' => array('type' => self::T_TENANT, 'soft' => false),
        // H-08-④ (هجرة 2026-08-20 · CON-01 §3.3): جهاتُ التحمّل Σ=100 لكل
        // مالكٍ (مكوّن/قاعدة) — soft لأن استبدالَ الجهات قرارُ تحميلٍ يُراجَع
        // فيُطوى القديمُ ولا يُمحى (الاستبدالُ الذري في الخدمة).
        'cost_bearers' => array('type' => self::T_TENANT, 'soft' => true),
        // H-11 (هجرة 2026-08-21 · ENT-01 §2): لقطةُ العقد الثابتة — Insert-only
        // (لا soft: الصفُّ يُدرج ويُبطل بأعمدته ولا يُحذف أبدًا — أثرُ احتسابٍ حي).
        'contract_snapshots' => array('type' => self::T_TENANT, 'soft' => false),
        // H-10 (هجرة 2026-08-22 · CON-01 §5): ملاحقُ عقد الموظف — مستندُ تغييرٍ
        // قانونيٌّ لا يُمحى (soft)؛ والقائمُ contract_amendments لعقود العملاء.
        'employee_contract_amendments' => array('type' => self::T_TENANT, 'soft' => true),
        // H-03 (هجرة 2026-08-23 · UX-03 §2.2): خطةُ عمل الغد وسطورُها —
        // الرأسُ soft (خطةٌ معتمدةٌ تُرجَع بسببٍ لا تُمحى)؛ السطورُ ابنُ رأسها
        // (CASCADE) تُدار داخل دورته فلا soft لها.
        'daily_plans' => array('type' => self::T_TENANT, 'soft' => true),
        'daily_plan_lines' => array('type' => self::T_TENANT, 'soft' => false),
        'drivercontractequipments' => array('type' => self::T_TENANT, 'soft' => false),
        'drivercontracts' => array('type' => self::T_TENANT, 'soft' => false),
        'employee_roles' => array('type' => self::T_TENANT, 'soft' => false),
        'employees' => array('type' => self::T_TENANT, 'soft' => false),
        'equipment_drivers' => array('type' => self::T_TENANT, 'soft' => false),
        'equipment_operators' => array('type' => self::T_TENANT, 'soft' => false),
        'equipments' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_accountants' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_approval_matrix' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_approvals' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_assets' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_bank_accounts' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_bank_statement_lines' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_budget_lines' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_budgets' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_cash_forecasts' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_chart_of_accounts' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_closing_items' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_cost_centers' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_cost_records' => array('type' => self::T_TENANT, 'soft' => true),
        // سجلُّ العملات وأسعارُ صرفها (FES-01 §3.3 · هجرة 2026-07-28): بياناتُ
        // مستأجرٍ لأن عملةَ الأساس مُعلَنةٌ لكل شركةٍ في admin_companies.currency.
        'fin_currencies' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_depreciation' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_dues' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_fx_rates' => array('type' => self::T_TENANT, 'soft' => true),
        // immutable_key: صفٌّ يحمل هذا العمود غيرَ فارغٍ = حدثُ ناقلٍ منشور —
        // البوابة ترفض تعديل مضمونه/حذفه (عقيدة اللاتعديل · حارس الحصانة §12 · A0).
        // القيود اليدوية (idempotency_key = NULL) تبقى قابلةً للإدارة.
        //
        // immutable_allow: فصل «المضمون» عن «حالة المعالجة» — المعيار المتبع في
        // Event Sourcing ونمط Transactional Outbox: ظرفُ الحقيقة وحمولتها لا
        // يتغيران أبدًا (المبلغ · النوع · الكيان · المراجع · التاريخ)، بينما
        // أعمدة موضع المعالجة مصمَّمةٌ للتحديث وإلا تجمّد الحدث بلا دورة.
        // تحديثٌ يلمس عمودًا خارج القائمة ⇒ يُرفض كما كان تمامًا (ولو معه عمودٌ مسموح).
        //   state            موضع الحدث في سير عمل D04 (مسودة→…→مقيد→مقفل)
        //   journal_entry_id ربط القيد المتولّد — تلازمٌ كتابيٌّ مع state='posted'
        //   fes_status · event_version · approved_by/at · posted_by/at · event_status
        //                    عقدُ FES §7.2 (H-12 · 2026-07-30): آلةُ الحالات الأربعَ عشرةَ
        //                    بقفلها التفاؤلي وتدقيقِ فاعليها — «موضعُ معالجة» بالتعريف،
        //                    والمضمونُ (المبلغ · الطرف · الفترة · المراجع) يبقى محصَّنًا.
        // إضافة أي عمودٍ هنا قرارٌ متعمَّد يوثَّق (أقل امتيازٍ ممكن).
        'fin_financial_events' => array(
            'type' => self::T_TENANT, 'soft' => true,
            'immutable_key' => 'idempotency_key',
            'immutable_allow' => array('state', 'journal_entry_id',
                'fes_status', 'event_version', 'approved_by', 'approved_at',
                'posted_by', 'posted_at', 'event_status'),
        ),
        // H-12 (FES §3.2): آثارُ الحدث المستقلة — سجلٌّ إلحاقيٌّ (لا حذفَ ناعمًا:
        // الأثرُ يُبطل بعكس حدثه لا بمحوه)، وUQ المركّب يمنع تكرارَ الأثر للطرف.
        'fin_event_effects' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_financial_periods' => array('type' => self::T_TENANT, 'soft' => false),
        // ── بوابة الطلب المالي D05 (المرحلتان 1+2 · 2026-07-16) — أرشفةٌ لا حذف (soft=false
        // بالتصميم: الحالات الست عشرة تملك دورة الحياة، وسجلّ الطلب إلحاقيٌّ لا يُمحى) ──
        'fin_requests' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_request_lines' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_request_documents' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_request_events' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_event_links' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_effect_map' => array('type' => self::T_TENANT, 'soft' => false), // §6.1: خريطة تفريع الأثر التصريحية
        'fin_operator_pay' => array('type' => self::T_TENANT, 'soft' => false), // §12: وضع دفع المشغّل (بالراتب/بالمستحق) — يحكم مروحة employee_due
        // (operator_pay_policies أُسقط 2026-07-27 — سياساتُ المشغّلين في contract_hour_policies بوضع party_scope=operator · §15.2-ج)
        'equipment_documents' => array('type' => self::T_TENANT, 'soft' => true), // UX-10 §8.1: وثائق المعدة والمشغّل بتواريخ انتهائها — فهرس expiry للتنبيه
        // D02 §3.7: أحكام استحقاق الأطراف — حكمٌ لكل طرفٍ بوحدة عقده هو (soft=true:
        // الحكم قرارٌ تعاقديٌّ يُراجَع ويُعكس، فلا يُمحى من سجلّ التدقيق)
        'unit_party_awards' => array('type' => self::T_TENANT, 'soft' => true),
        // D02 §3.8: سياسة استحقاق عقد الساعة — قواعدُ بياناتٍ لا كود (نمط fin_effect_map)
        'contract_hour_policies' => array('type' => self::T_TENANT, 'soft' => true),
        // ── D02 §15 المرحلة ١: مصدر الحقيقة — الجداول الثلاثة (2026-07-18) ──
        // soft=false في الثلاثة بالتصميم (نمط fin_requests لا نمط fin_payments):
        // الحالات الثلاث عشرة تملك دورة الحياة، و«الوحدة المرفوضة لا تُحذف؛ حالةٌ
        // بسببها في السجل» (§4.3) — فلا حذفَ ناعمًا يُعبَّر عنه بعمود.
        'unit_entries' => array('type' => self::T_TENANT, 'soft' => false),
        // سجلّ الزمن التشغيلي — إلحاقيٌّ (§3.3): سطرٌ لكل فترةٍ بحالتها ومسؤولها
        'unit_time_log' => array('type' => self::T_TENANT, 'soft' => false),
        // سلسلة الاعتماد — إلحاقيٌّ (§4.2): «فيُقرأ تاريخُ السلسلة كاملًا»
        'unit_approvals' => array('type' => self::T_TENANT, 'soft' => false),
        // D02 §3.10: أعلام تجاوز الطاقة وتخليصها — علمٌ يُرفع ويُخلَّص ولا يُمحى
        'unit_capacity_flags' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_request_routing' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_funding_facilities' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_funding_schedules' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_internal_allocations' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_journal_entries' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_journal_lines' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_notifications' => array('type' => self::T_TENANT, 'soft' => false),
        'fin_payments' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_receivables' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_tax_codes' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_tax_transactions' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_unit_records' => array('type' => self::T_TENANT, 'soft' => true),
        'fin_units' => array('type' => self::T_TENANT, 'soft' => true),
        'fleet_depreciation_profile' => array('type' => self::T_TENANT, 'soft' => true),
        'fleet_depreciation_profile_audit' => array('type' => self::T_TENANT, 'soft' => false),
        'fleet_equipment_compliance' => array('type' => self::T_TENANT, 'soft' => true),
        'fleet_equipment_component' => array('type' => self::T_TENANT, 'soft' => true),
        'fleet_equipment_history' => array('type' => self::T_TENANT, 'soft' => false),
        'fleet_equipment_protection' => array('type' => self::T_TENANT, 'soft' => true),
        'fleet_model' => array('type' => self::T_TENANT, 'soft' => true),
        'fleet_model_service_spec' => array('type' => self::T_TENANT, 'soft' => false),
        'housing_unit' => array('type' => self::T_TENANT, 'soft' => false),
        'job_titles' => array('type' => self::T_TENANT, 'soft' => false),
        'messages' => array('type' => self::T_TENANT, 'soft' => false),
        'mnt_breakdown' => array('type' => self::T_TENANT, 'soft' => true),
        'mnt_inspection' => array('type' => self::T_TENANT, 'soft' => true),
        'mnt_inspection_line' => array('type' => self::T_TENANT, 'soft' => false),
        'mnt_inspection_template' => array('type' => self::T_CATALOG, 'soft' => false),
        'mnt_lookup' => array('type' => self::T_TENANT, 'soft' => true),
        'mnt_order' => array('type' => self::T_TENANT, 'soft' => true),
        'mnt_order_labor' => array('type' => self::T_TENANT, 'soft' => false),
        'mnt_order_part' => array('type' => self::T_TENANT, 'soft' => false),
        'mnt_plan' => array('type' => self::T_TENANT, 'soft' => true),
        'mnt_plan_task' => array('type' => self::T_TENANT, 'soft' => false),
        'operations' => array('type' => self::T_TENANT, 'soft' => false),
        'opportunities' => array('type' => self::T_TENANT, 'soft' => true),
        'pricelists' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_custody' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_issue' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_issue_line' => array('type' => self::T_TENANT, 'soft' => false),
        'proc_item' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_lookup' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_order' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_order_line' => array('type' => self::T_TENANT, 'soft' => false),
        'proc_orderpoint' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_receipt_custody' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_receipt_line' => array('type' => self::T_TENANT, 'soft' => false),
        'proc_request' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_request_line' => array('type' => self::T_TENANT, 'soft' => false),
        'proc_stock_move' => array('type' => self::T_TENANT, 'soft' => false),
        'proc_supplier' => array('type' => self::T_TENANT, 'soft' => true),
        'proc_warehouse' => array('type' => self::T_TENANT, 'soft' => true),
        'products' => array('type' => self::T_TENANT, 'soft' => true),
        'project' => array('type' => self::T_TENANT, 'soft' => true),
        'quotations' => array('type' => self::T_TENANT, 'soft' => true),
        // INJAZ-S05 §6.12: بنود فحص الجاهزية الستة لكل عقد
        'readiness_lines' => array('type' => self::T_TENANT, 'soft' => true),
        // التسويةُ الموحّدة (UX-02 §15.3 · هجرة 2026-07-28): الرأسُ بيانات مستأجرٍ،
        // والبنودُ تحمل company_id فتُعزل مباشرةً (لا عبر أبيها) — وحذفُها بالسلسلة
        // من الرأس لا بالحذف الناعم (بنودٌ لا مستنداتٌ مستقلة).
        'settlements' => array('type' => self::T_TENANT, 'soft' => true),
        'settlement_lines' => array('type' => self::T_TENANT, 'soft' => false),
        // M-15 (CON-03 §2-⑥/⑦ · §6): قواعدُ التحميل المسعَّرة وقواعدُ الجزاء.
        'supplier_charge_rules' => array('type' => self::T_TENANT, 'soft' => true),
        'supplier_penalty_rules' => array('type' => self::T_TENANT, 'soft' => true),
        // M-16 (CON-03 §3 · §6): بطاقةُ الطاقة النظرية والجاهزيةِ ومهلةِ الإحلال.
        'supplier_capacity' => array('type' => self::T_TENANT, 'soft' => true),
        // M-18 (ENT-02 §4): تصفيةُ إنهاء العقد — «بمفتاح (العقد × التصفية)».
        'supplier_contract_closures' => array('type' => self::T_TENANT, 'soft' => true),
        // M-17 (CON-03 §4-التقييم): الأوزانُ المكتوبةُ والتقييمُ الدوريُّ وأسطرُ مؤشراته.
        'supplier_evaluation_weights' => array('type' => self::T_TENANT, 'soft' => true),
        'supplier_evaluations' => array('type' => self::T_TENANT, 'soft' => true),
        'supplier_evaluation_lines' => array('type' => self::T_TENANT, 'soft' => false),
        // M-12 (ENT-02 §3/§7): بوابةُ سلفيات الموردين وسجلُّ استردادها.
        'supplier_advance_requests' => array('type' => self::T_TENANT, 'soft' => true),
        'supplier_advance_recoveries' => array('type' => self::T_TENANT, 'soft' => false),
        'supplier_contract_notes' => array('type' => self::T_TENANT, 'soft' => false),
        // H-07 (CON-03 §6): رأسُ عقد المورد الحديث وبنودُه — بناءٌ بجانب
        // `supplierscontracts` الموروث الذي يبقى الكاتبَ (N-04 مرحلة ①).
        'supplier_contracts' => array('type' => self::T_TENANT, 'soft' => true),
        'supplier_contract_lines' => array('type' => self::T_TENANT, 'soft' => true),
        'suppliercontractequipments' => array('type' => self::T_TENANT, 'soft' => false),
        'suppliers' => array('type' => self::T_TENANT, 'soft' => true),
        'supplierscontracts' => array('type' => self::T_TENANT, 'soft' => false),
        'tenders' => array('type' => self::T_TENANT, 'soft' => true),
        // وحدة البلاغات (2026-07-18): التذكرة وسجلّاتها بلا حذفٍ ناعم — الإلغاء
        // حالةٌ تُسجَّل لا محوٌ للسجل؛ وسجلّا الأحداث والتحويلات للإدراج فقط
        // (لا تعديلَ ولا حذفَ في طبقة التطبيق)؛ والأنواع والتصنيفات كتالوج
        // مشترك (صفوف عامة بـcompany_id=NULL + إضافات كل شركة).
        'ticket_attachments' => array('type' => self::T_TENANT, 'soft' => false),
        'ticket_categories' => array('type' => self::T_CATALOG, 'soft' => false),
        'ticket_escalation_rules' => array('type' => self::T_TENANT, 'soft' => false),
        'ticket_events' => array('type' => self::T_TENANT, 'soft' => false),
        'ticket_recurrence_templates' => array('type' => self::T_TENANT, 'soft' => false),
        'ticket_sla_policies' => array('type' => self::T_TENANT, 'soft' => false),
        'ticket_transfers' => array('type' => self::T_TENANT, 'soft' => false),
        'ticket_types' => array('type' => self::T_CATALOG, 'soft' => false),
        'ticket_watchers' => array('type' => self::T_TENANT, 'soft' => false),
        'tickets' => array('type' => self::T_TENANT, 'soft' => false),
        // إشعارات وحدة البلاغات (منع تكرارٍ يوميّ، بلا حذف)
        'tkt_notifications' => array('type' => self::T_TENANT, 'soft' => false),
        'timesheet' => array('type' => self::T_TENANT, 'soft' => false),
        'timesheet_approval_notes' => array('type' => self::T_TENANT, 'soft' => false),
        'timesheet_approvals' => array('type' => self::T_TENANT, 'soft' => false),
        'timesheet_failure_hours' => array('type' => self::T_TENANT, 'soft' => false),
        'transfer_attachments' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_cost_lines' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_cost_rules' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_events' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_lines' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_orders' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_permits' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_requests' => array('type' => self::T_TENANT, 'soft' => true),
        'transfer_types' => array('type' => self::T_TENANT, 'soft' => true),
        'trs_locations' => array('type' => self::T_TENANT, 'soft' => true),
        'trs_notifications' => array('type' => self::T_TENANT, 'soft' => true),
        'units_of_measure' => array('type' => self::T_TENANT, 'soft' => true),
        'users' => array('type' => self::T_TENANT, 'soft' => true),
        'worker_backup' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_contract' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_evaluation' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_leave_absence' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_movement' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_qualification' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_restricted_site' => array('type' => self::T_TENANT, 'soft' => false),
        'worker_settlement' => array('type' => self::T_TENANT, 'soft' => false),
        'workforce_requirement' => array('type' => self::T_TENANT, 'soft' => false),

        // ── أبناء يُعزلون عبر آبائهم ─────────────────────────────────────────
        'worker_settlement_line' => array('type' => self::T_CHILD, 'soft' => false,
            'parent' => 'worker_settlement', 'fk' => 'settlement_id'),
        'worker_evaluation_kpi' => array('type' => self::T_CHILD, 'soft' => false,
            'parent' => 'worker_evaluation', 'fk' => 'evaluation_id'),
        'mnt_inspection_template_line' => array('type' => self::T_CHILD, 'soft' => false,
            'parent' => 'mnt_inspection_template', 'fk' => 'template_id'),
        // Views القوى العاملة المحسوبة (بلا عمود company_id): مفتاحها employee_id
        // فتُعزَل عبر الأب employees كأي ابنٍ (EXISTS على الأب المملوك). قراءةٌ عمليًا —
        // أي كتابةٍ فيها يرفضها MySQL نفسه (views تجميعية غير قابلة للتحديث).
        'v_worker_worklog' => array('type' => self::T_CHILD, 'soft' => false,
            'parent' => 'employees', 'fk' => 'employee_id'),
        'v_worker_presence' => array('type' => self::T_CHILD, 'soft' => false,
            'parent' => 'employees', 'fk' => 'employee_id'),

        // ── مراجع نظامٍ عامة (قراءة للجميع، كتابة للمدير الأعلى) ────────────
        // H-08-① (CON-01 §3.1): كتالوجُ نماذج الأجر الخمسة عشر — قائمةٌ محكومةٌ
        // عامةٌ بلا company_id («نموذجٌ غيرُ مذكورٍ في القائمة → 422»).
        'pay_models' => array('type' => self::T_GLOBAL, 'soft' => false),
        'roles' => array('type' => self::T_GLOBAL, 'soft' => false),
        'modules' => array('type' => self::T_GLOBAL, 'soft' => false),
        'link_groups' => array('type' => self::T_GLOBAL, 'soft' => false),
        'nav_items' => array('type' => self::T_GLOBAL, 'soft' => false),
        'nav_redirects' => array('type' => self::T_GLOBAL, 'soft' => false),
        'role_permissions' => array('type' => self::T_GLOBAL, 'soft' => false),
        'report_role_permissions' => array('type' => self::T_GLOBAL, 'soft' => false),
        'equipments_types' => array('type' => self::T_GLOBAL, 'soft' => false, 'managed' => true),
        'failure_codes' => array('type' => self::T_GLOBAL, 'managed' => true, 'soft' => false),
        'admin_subscription_plans' => array('type' => self::T_GLOBAL, 'soft' => false),

        // ── طبقة المزوّد (عقد دفعة هـ-0 · 2026-07-16): كونسول المنصّة عبر
        //    ems_platform_db حصرًا؛ بوابة المستأجر ترفضها كليًا ────────────────
        'admin_companies' => array('type' => self::T_PLATFORM, 'soft' => false),
        'admin_audit_log' => array('type' => self::T_PLATFORM, 'soft' => false),
        'admin_subscription_requests' => array('type' => self::T_PLATFORM, 'soft' => false),
        'super_admins' => array('type' => self::T_PLATFORM, 'soft' => false),
        'super_admin_password_resets' => array('type' => self::T_PLATFORM, 'soft' => false),
        'company_user_password_resets' => array('type' => self::T_PLATFORM, 'soft' => false),
        'api_tokens' => array('type' => self::T_PLATFORM, 'soft' => false),

        // ── منصة/محرّكات لم تُهاجَر — مرفوضة عبر البوابة حتى تعريف عقدها ────
        'schema_migrations' => array('type' => self::T_RESTRICTED, 'soft' => false),
        'ems_sequences' => array('type' => self::T_RESTRICTED, 'soft' => false), // K8: بنية ترقيم خادمية — الوصول عبر ServerId حصرًا لا الشاشات
        'ems_event_consumers' => array('type' => self::T_RESTRICTED, 'soft' => false), // K4: بنية الموزّع — عبر EventDispatcher حصرًا
        'ems_event_deliveries' => array('type' => self::T_RESTRICTED, 'soft' => false), // K4
        'ems_event_dead_letter' => array('type' => self::T_RESTRICTED, 'soft' => false), // K4
        'positions' => array('type' => self::T_TENANT, 'soft' => true), // K6/ADR-07: جسر المنصب — معزول بالشركة، جاهز للبوابة من يومه الأول
        'ems_state_transitions' => array('type' => self::T_RESTRICTED, 'soft' => false), // K7: سجل تدقيق المحرك — append-only عبر StateMachine حصرًا
        'ems_business_events' => array('type' => self::T_RESTRICTED, 'soft' => false), // ADR-15: الجذر المحايد — append-only عبر EventPublisher حصرًا؛ الدفتر المالي إسقاطه
        'approval_requests' => array('type' => self::T_RESTRICTED, 'soft' => false),
        'approval_steps' => array('type' => self::T_RESTRICTED, 'soft' => false),
        'approval_workflow_rules' => array('type' => self::T_RESTRICTED, 'soft' => false),
    );

    /** تعريف جدولٍ أو null إن لم يكن مسجَّلًا. */
    public static function get($table)
    {
        return isset(self::$tables[$table]) ? self::$tables[$table] : null;
    }

    /** كل السجل (للاختبارات والتدقيق). */
    public static function all()
    {
        return self::$tables;
    }
}

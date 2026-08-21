<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
require_once __DIR__ . '/../includes/sensitive_read_log.php'; // INJ-FIX-01 §أ② — نقطةُ قرارِ الحقلِ الحساسِ في العرض
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($client_id <= 0) {
    ems_gov_flash_redirect('clients.php', 'معرف العميل غير صحيح ❌', 'GOV-REF-404', '');
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): تنطيق الشركة والحذف الناعم مسؤولية
// البوابة — كشفُ الأعمدة القديم أُسقط (السجل يضمن clients/project بأعمدة العزل)،
// والسوبر يمرّ عبر forAllTenants المسجَّل (نفس سلوك الأصل: بلا تنطيق شركة).
$cp_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('client profile super') : ems_tenant_db();

try {
    $client_rows = $cp_gate->scopedQuery(array(
        'scope'  => array('c' => 'clients'),
        'enrich' => array('u' => 'users'),
    ), "SELECT c.*, u.name AS creator_name
        FROM clients c
        LEFT JOIN users u ON u.id = c.created_by
        WHERE {TENANT_SCOPE} AND c.id = ? AND COALESCE(c.is_deleted,0)=0
        LIMIT 1", array($client_id));
} catch (\Throwable $t) { $client_rows = array(); }
$client = !empty($client_rows) ? $client_rows[0] : null;

if (!$client) {
    ems_gov_flash_redirect('clients.php', 'العميل غير موجود او خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
    exit();
}

// شرط مشاريع العميل (بلا شرط شركةٍ يدوي — {TENANT_SCOPE} يتكفّل به)
$project_cond = "p.client_id = ? AND COALESCE(p.is_deleted,0)=0";

$projects_total = 0;
$projects_active = 0;
$contracts_count = 0;
$suppliers_count = 0;
$equipments_count = 0;
$drivers_count = 0;
$total_hours = 0;

$cp_agg = function (array $decl, $sql, array $params) use ($cp_gate) {
    try {
        $rows = $cp_gate->scopedQuery($decl, $sql, $params);
        return !empty($rows) ? $rows[0]['c'] : 0;
    } catch (\Throwable $t) {
        return 0;
    }
};

$projects_total = intval($cp_agg(array('scope' => array('p' => 'project')),
    "SELECT COUNT(*) AS c FROM project p WHERE {TENANT_SCOPE} AND $project_cond", array($client_id)));

$projects_active = intval($cp_agg(array('scope' => array('p' => 'project')),
    "SELECT COUNT(*) AS c FROM project p WHERE {TENANT_SCOPE} AND $project_cond AND p.status = 1", array($client_id)));

$contracts_count = intval($cp_agg(array('scope' => array('ct' => 'contracts', 'p' => 'project')),
    "SELECT COUNT(*) AS c
     FROM contracts ct
     INNER JOIN project p ON p.id = ct.project_id
     WHERE {TENANT_SCOPE} AND $project_cond AND ct.status = 1", array($client_id)));

$suppliers_count = intval($cp_agg(array('scope' => array('o' => 'operations', 'p' => 'project', 'e' => 'equipments')),
    "SELECT COUNT(DISTINCT e.suppliers) AS c
     FROM operations o
     INNER JOIN project p ON p.id = o.project_id
     INNER JOIN equipments e ON e.id = o.equipment
     WHERE {TENANT_SCOPE} AND $project_cond", array($client_id)));

$equipments_count = intval($cp_agg(array('scope' => array('o' => 'operations', 'p' => 'project')),
    "SELECT COUNT(DISTINCT o.equipment) AS c
     FROM operations o
     INNER JOIN project p ON p.id = o.project_id
     WHERE {TENANT_SCOPE} AND $project_cond", array($client_id)));

$drivers_count = intval($cp_agg(array('scope' => array('o' => 'operations', 'p' => 'project', 'ed' => 'equipment_drivers')),
    "SELECT COUNT(DISTINCT ed.employee_id) AS c
     FROM operations o
     INNER JOIN project p ON p.id = o.project_id
     INNER JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
     WHERE {TENANT_SCOPE} AND $project_cond AND ed.status = 1", array($client_id)));

$total_hours = floatval($cp_agg(array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'p' => 'project')),
    "SELECT IFNULL(SUM(t.operator_hours + t.operator_standby_hours), 0) AS c
     FROM timesheet t
     INNER JOIN operations o ON o.id = t.operator
     INNER JOIN project p ON p.id = o.project_id
     WHERE {TENANT_SCOPE} AND $project_cond AND t.status = 1", array($client_id)));

try {
    $projects_breakdown = $cp_gate->scopedQuery(array(
        'scope'  => array('p' => 'project'),
        'enrich' => array('o' => 'operations', 'e' => 'equipments', 't' => 'timesheet'),
    ), "SELECT
            p.id,
            p.name,
            p.project_code,
            COUNT(DISTINCT o.equipment) AS equipments_count,
            COUNT(DISTINCT e.suppliers) AS suppliers_count,
            IFNULL(SUM(t.operator_hours + t.operator_standby_hours), 0) AS hours_sum
        FROM project p
        LEFT JOIN operations o ON o.project_id = p.id
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
        WHERE {TENANT_SCOPE} AND $project_cond
        GROUP BY p.id, p.name, p.project_code
        ORDER BY hours_sum DESC
        LIMIT 10", array($client_id));
} catch (\Throwable $t) {
    $projects_breakdown = array();
}

// ══════════════════════════════════════════════════════════════════════════════
// جامعُ المبالغِ بالعملة — «لا تُجمع عملتانِ في رقم»
// ══════════════════════════════════════════════════════════════════════════════
// والعملةُ الفارغةُ ليست عملةً: 120 عرضًا من 140 في هذه القاعدةِ بعملةٍ فارغة.
// فتُجمع في دلوٍ مُعلَنٍ باسمِه «بلا عملة» — لا تُلحق بعملةٍ أخرى فتفسدَها،
// ولا تُطرح صامتةً فيختفيَ مالٌ موجود.
if (!function_exists('cp_money_add')) {
    function cp_money_add(array &$bucket, $cur, $val)
    {
        $k = (trim((string) $cur) !== '') ? trim((string) $cur) : 'بلا عملة';
        $bucket[$k] = (isset($bucket[$k]) ? $bucket[$k] : 0.0) + (float) $val;
    }
}
// صياغةُ دلوِ العملاتِ نصًّا مقروءًا
if (!function_exists('cp_money_fmt')) {
    function cp_money_fmt(array $bucket, $dec = 0)
    {
        if (empty($bucket)) { return array('—'); }
        $out = array();
        foreach ($bucket as $cur => $v) { $out[] = number_format($v, $dec) . ' ' . $cur; }
        return $out;
    }
}
// ══════════════════════════════════════════════════════════════════════════════
// فرصُ العميلِ البيعية — وصلُ مسارِ الفرصِ ببطاقتِه
// ══════════════════════════════════════════════════════════════════════════════
// الفرصةُ تصل العميلَ بمسارٍ واحدٍ مباشرٍ (opportunities.client_id) — لا تعدُّدَ
// مساراتٍ كما في الأنشطة، فلا عمودَ «مصدرِ وصلٍ» هنا.
//
// ◆ **ولا تُجمع عملتانِ في رقم**: قيمُ الفرصِ بـUSD وSDG معًا في هذه القاعدةِ
//   (خمسةُ عملاءَ لهم الاثنتان). فقيمةُ المسارِ تُجمَع **لكلِّ عملةٍ على حدة**
//   كما تفعل شاشةُ الفرصِ نفسُها ($pipeline_by_cur) — ومجموعُ العملتَين في
//   خانةٍ واحدةٍ رقمٌ لا معنى له.
// ◆ ولا حدَّ (LIMIT) على الجلب: فرصُ العميلِ الواحدِ محدودةٌ بطبعِها، والعدُّ
//   يُحسب من الصفوفِ نفسِها — فلا تنشقُّ إحصاءةٌ عن قائمةٍ بترها حدٌّ.
$OPP_OPEN_STAGES = array('جديدة', 'قيد الدراسة', 'مؤهلة', 'عرض مقدم', 'تفاوض');
$OPP_REVENUE_MODELS = array(
    'hourly' => 'تأجير بالساعة',
    'ton'    => 'نقل بالطن',
    'meter'  => 'تخريم بالمتر',
    'mixed'  => 'مزيج',
);

// نغمةُ شارةِ المرحلة — نظيرةُ opp_stage_tone() في شاشةِ الفرصِ حرفًا،
// باسمٍ خاصٍّ كي لا تتصادمَ الدالّتانِ لو اجتمع الملفّان يومًا
if (!function_exists('cp_opp_stage_tone')) {
    /* ◆ النغمةُ لا اللونُ: كانت تُرجع اسمَ صنفٍ محليٍّ (`won`/`lost`) يعرّف لونَه
         في كتلةِ الصفحة. صارت تُرجع إحدى نغماتِ `ems-profile.css` الثماني —
         فمرحلةٌ جديدةٌ في الأعمالِ لا تضيف قاعدةَ CSS، وشاشةُ الفرصِ وبطاقةُ
         العميلِ تتكلّمان اللغةَ نفسَها بلا نسختين. */
    function cp_opp_stage_tone($stage)
    {
        switch (trim((string) $stage)) {
            case 'فوز':         return 'ok';
            case 'خسارة':       return 'danger';
            case 'مستبعدة':     return 'neutral';
            case 'تفاوض':       return 'warn';
            case 'عرض مقدم':    return 'gold';
            case 'مؤهلة':       return 'cyan';
            case 'قيد الدراسة': return 'purple';
            default:             return 'info';
        }
    }
}

$client_opportunities = array();
$opp_total = 0;
$opp_open = 0;
$opp_won = 0;
$opp_lost = 0;
$opp_excluded = 0;
$opp_conversion = 0;
$opp_pipeline_by_cur = array(); // قيمةُ المسارِ المفتوحِ لكلِّ عملةٍ على حدة
$opp_won_by_cur = array();

/* ── مَن يرى فرصَ العميلِ في بطاقتِه؟ (قرارُ المالك 2026-08-19) ─────────────
   كان العرضُ مشروطًا بصلاحيةِ وحدةِ «الفرص البيعية»، فقِيس حيًّا أن **اثنَين
   من 75 مستخدمًا** في الشركةِ يريانه — والإدارةُ التنفيذيةُ والسوبر أدمن
   ليسا منهما (get_module_permissions لا تستثني '-1'). فكان القسمُ مبنيًّا
   ومحجوبًا عمّن طلبه.
   والبطاقةُ نفسُها لا تشترط صلاحيةَ وحدةٍ أصلًا: من يفتحها يرى مشاريعَ
   العميلِ وعقودَه ومعداتِه وساعاتِه. فحجبُ الفرصِ وحدَها كان تشدُّدًا في
   موضعٍ واحدٍ لا سياسةً متّسقة — والعرضُ الآن لمن يفتح البطاقة.
   ◆ والعزلُ لم يُمسّ: {TENANT_SCOPE} هو الحدُّ الأمنيُّ وهو قائم.
   ◆ وزرُّ الرأسِ يبقى مشروطًا بالصلاحية — لا نفتح بابًا يُردُّ فاتحُه. */
{
    try {
        $client_opportunities = $cp_gate->scopedQuery(array(
            'scope'  => array('o' => 'opportunities'),
            'enrich' => array('u' => 'users'),
        ), "SELECT o.id, o.opp_code, o.title, o.stage, o.expected_revenue, o.currency,
                   o.probability, o.revenue_model, o.expected_close_date, o.source,
                   o.study_decision, o.attractiveness, o.funding_needed,
                   u.name AS creator_name
            FROM opportunities o
            LEFT JOIN users u ON u.id = o.created_by
            WHERE {TENANT_SCOPE} AND o.client_id = ? AND COALESCE(o.is_deleted,0) = 0
            ORDER BY o.expected_close_date DESC, o.id DESC", array($client_id));
    } catch (\Throwable $t) {
        $client_opportunities = array();
        error_log('client_profile.php opportunities list: ' . $t->getMessage());
    }

    foreach ($client_opportunities as $opp) {
        $opp_total++;
        $stg = trim((string) $opp['stage']);
        $rev = (float) $opp['expected_revenue'];
        // جامعٌ واحدٌ لكلِّ مالِ البطاقة (cp_money_add) — والعملةُ الفارغةُ لها
        // دلوُها المُعلَن «بلا عملة»، لا شرطةٌ تختلف عن بقيةِ الأقسام
        if (in_array($stg, $OPP_OPEN_STAGES, true)) {
            $opp_open++;
            cp_money_add($opp_pipeline_by_cur, $opp['currency'], $rev);
        }
        if ($stg === 'فوز') {
            $opp_won++;
            cp_money_add($opp_won_by_cur, $opp['currency'], $rev);
        }
        if ($stg === 'خسارة')   { $opp_lost++; }
        if ($stg === 'مستبعدة') { $opp_excluded++; }
    }
    // معدَّلُ التحويلِ يُقاس على المحسومِ وحدَه (فوز+خسارة) — لا على الكلِّ،
    // فالمفتوحُ لم يُحسم بعدُ فإدخالُه في المقامِ يكذب معكوسًا (نفسُ قاعدةِ شاشةِ الفرص)
    $opp_decided = $opp_won + $opp_lost;
    $opp_conversion = $opp_decided > 0 ? round(($opp_won / $opp_decided) * 100, 1) : 0;
}

// ══════════════════════════════════════════════════════════════════════════════
// مناقصاتُ العميل — وصلُ سجلِّ المناقصاتِ ببطاقتِه
// ══════════════════════════════════════════════════════════════════════════════
// ◆ **المناقصةُ لا تحمل `client_id`**. تصلُ العميلَ بمسارَين:
//     ① **جهةً طارحة** — `tenders.authority_id` يشير إلى `clients`
//       (شاشةُ المناقصاتِ تملأ هذا الحقلَ من قائمةِ العملاءِ نفسِها).
//     ② **عبر فرصة** — `tenders.opportunity_id` ⇐ `opportunities.client_id`.
// ◆ والمساران **يتباعدان فعلًا** لا نظريًّا: المقيسُ حيًّا أن `TND-1012`
//   جهتُها الطارحةُ العميلُ 5 وفرصتُها للعميل 6 — فهي تظهر في بطاقتَين
//   بمسارَين مختلفَين، وكلُّ ظهورٍ صادقٌ في بابِه. فعمودُ «مصدر الوصل»
//   ضرورةٌ لا زينة، وإلا بدا الرقمُ متضخِّمًا بلا تفسير.
// ◆ وبعضُ الفرصِ بلا عميلٍ (`client_id` فارغ) فمناقصاتُها تصل بالجهةِ وحدَها.
$TND_PARTICIPATION_SUBMITTED = 'مقدمة';

// نغمةُ شارةِ النتيجة — نظيرةُ tnd_result_class() في شاشةِ المناقصاتِ معنًى
if (!function_exists('cp_tnd_result_class')) {
    function cp_tnd_result_class($result)
    {
        switch (trim((string) $result)) {
            case 'فوز':   return 'ok';
            case 'خسارة': return 'danger';
            case 'إلغاء': return 'neutral';
            default:      return 'warn';
        }
    }
}

/* نغمةُ «مصدرِ الوصل» — الوصلُ المباشرُ لا يُخلط بالموروثِ عن فرصةٍ أو عقد،
   والفرقُ نغمةٌ لا صنفٌ لكلِّ نوعِ كِيان. */
if (!function_exists('cp_via_tone')) {
    function cp_via_tone($entityType)
    {
        switch (trim((string) $entityType)) {
            case 'client':      return 'ok';       // مباشر
            case 'opportunity': return 'cyan';     // عبر فرصة
            case 'contract':    return 'purple';   // عبر عقد
            default:            return 'neutral';
        }
    }
}

$client_tenders = array();
$tnd_total = 0;
$tnd_submitted = 0;
$tnd_won = 0;
$tnd_lost = 0;
$tnd_win_rate = 0;

/* العرضُ لمن يفتح البطاقةَ — والزرُّ وحدَه بالصلاحية (انظر قرارَ الفرصِ أعلاه) */
{
    try {
        $client_tenders = $cp_gate->scopedQuery(array(
            'scope'  => array('t' => 'tenders'),
            'enrich' => array('o' => 'opportunities', 'ca' => 'clients', 'u' => 'users'),
        ), "SELECT t.id, t.tender_code, t.name, t.authority_id, t.opportunity_id,
                   t.closing_date, t.participation_state, t.result, t.result_reason, t.notes,
                   o.opp_code AS opp_code, o.title AS opp_title, o.client_id AS opp_client_id,
                   ca.client_name AS authority_name,
                   u.name AS creator_name
            FROM tenders t
            LEFT JOIN opportunities o ON o.id = t.opportunity_id AND COALESCE(o.is_deleted,0) = 0
            LEFT JOIN clients ca ON ca.id = t.authority_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0) = 0
              AND (t.authority_id = ? OR o.client_id = ?)
            ORDER BY t.closing_date DESC, t.id DESC", array($client_id, $client_id));
    } catch (\Throwable $t) {
        $client_tenders = array();
        error_log('client_profile.php tenders list: ' . $t->getMessage());
    }

    foreach ($client_tenders as $i => $tnd) {
        $tnd_total++;
        if (trim((string) $tnd['participation_state']) === $TND_PARTICIPATION_SUBMITTED) { $tnd_submitted++; }
        $res = trim((string) $tnd['result']);
        if ($res === 'فوز')   { $tnd_won++; }
        if ($res === 'خسارة') { $tnd_lost++; }

        // مصدرُ الوصل: الجهةُ الطارحةُ تسبق، فإن لم تكن فالمسارُ عبرَ الفرصة
        $client_tenders[$i]['via_label'] = (intval($tnd['authority_id']) === intval($client_id))
            ? 'جهةٌ طارحة'
            : 'عبر فرصة';
        // الفرصةُ المرتبطةُ صفةٌ للمناقصةِ نفسِها — تُعرض أيًّا كان المسار
        $opp_lbl = '—';
        if (!empty($tnd['opportunity_id'])) {
            $code  = trim((string) ($tnd['opp_code'] ?? ''));
            $title = trim((string) ($tnd['opp_title'] ?? ''));
            if ($code !== '' || $title !== '') {
                $opp_lbl = trim($code . ($code !== '' && $title !== '' ? ' — ' : '') . $title);
            } else {
                $opp_lbl = 'فرصة #' . intval($tnd['opportunity_id']);
            }
        }
        $client_tenders[$i]['opp_label'] = $opp_lbl;
    }
    // معدَّلُ الفوزِ على المحسومِ وحدَه (فوز+خسارة) — «قيد التقييم» و«إلغاء»
    // ليسا حكمًا على العرضِ فإدخالُهما في المقامِ يكذب معكوسًا
    $tnd_decided = $tnd_won + $tnd_lost;
    $tnd_win_rate = $tnd_decided > 0 ? round(($tnd_won / $tnd_decided) * 100, 1) : 0;
}

// ══════════════════════════════════════════════════════════════════════════════
// عروضُ الأسعار — وصلٌ مباشرٌ بـquotations.client_id
// ══════════════════════════════════════════════════════════════════════════════
// الحلقةُ التي كانت مفقودةً في القمع: فرصة ← **عرضُ سعر** ← مناقصة ← عقد.
$QUO_STATES = array('مسودة', 'مقدم', 'مقبول', 'مرفوض');
$client_quotations = array();
$quo_total = 0; $quo_accepted = 0; $quo_rejected = 0; $quo_open = 0;
$quo_accepted_by_cur = array();
try {
    $client_quotations = $cp_gate->scopedQuery(array(
        'scope'  => array('q' => 'quotations'),
        'enrich' => array('o' => 'opportunities', 'u' => 'users'),
    ), "SELECT q.id, q.quotation_code, q.opportunity_id, q.currency, q.amount_total,
               q.validity_date, q.payment_terms, q.state,
               o.opp_code AS opp_code, o.title AS opp_title, u.name AS creator_name
        FROM quotations q
        LEFT JOIN opportunities o ON o.id = q.opportunity_id AND COALESCE(o.is_deleted,0) = 0
        LEFT JOIN users u ON u.id = q.created_by
        WHERE {TENANT_SCOPE} AND q.client_id = ? AND COALESCE(q.is_deleted,0) = 0
        ORDER BY q.validity_date DESC, q.id DESC", array($client_id));
} catch (\Throwable $t) {
    $client_quotations = array();
    error_log('client_profile.php quotations: ' . $t->getMessage());
}
foreach ($client_quotations as $i => $q) {
    $quo_total++;
    $st = trim((string) $q['state']);
    if ($st === 'مقبول') { $quo_accepted++; cp_money_add($quo_accepted_by_cur, $q['currency'], $q['amount_total']); }
    elseif ($st === 'مرفوض') { $quo_rejected++; }
    else { $quo_open++; }
    $lbl = '—';
    if (!empty($q['opportunity_id'])) {
        $cd = trim((string) ($q['opp_code'] ?? '')); $ti = trim((string) ($q['opp_title'] ?? ''));
        $lbl = ($cd !== '' || $ti !== '') ? trim($cd . ($cd !== '' && $ti !== '' ? ' — ' : '') . $ti)
                                          : 'فرصة #' . intval($q['opportunity_id']);
    }
    $client_quotations[$i]['opp_label'] = $lbl;
}
$quo_decided = $quo_accepted + $quo_rejected;
$quo_win_rate = $quo_decided > 0 ? round(($quo_accepted / $quo_decided) * 100, 1) : 0;

// ══════════════════════════════════════════════════════════════════════════════
// عقودُ العميل — عبرَ مشاريعِه (لا `client_id` في contracts)
// ══════════════════════════════════════════════════════════════════════════════
$client_contracts = array();
try {
    $client_contracts = $cp_gate->scopedQuery(array(
        'scope' => array('ct' => 'contracts', 'p' => 'project'),
    ), "SELECT ct.id, ct.contract_signing_date, ct.actual_start, ct.actual_end, ct.status,
               ct.contract_duration_months, ct.hours_monthly_target,
               p.name AS project_name, p.project_code
        FROM contracts ct
        INNER JOIN project p ON p.id = ct.project_id
        WHERE {TENANT_SCOPE} AND $project_cond AND COALESCE(ct.is_deleted,0) = 0
        ORDER BY ct.contract_signing_date DESC, ct.id DESC", array($client_id));
} catch (\Throwable $t) {
    $client_contracts = array();
    error_log('client_profile.php contracts: ' . $t->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// الوضعُ المالي — كشفُ الحساب · المستخلصات · الفواتيرُ الضريبية
// ══════════════════════════════════════════════════════════════════════════════
// ◆ كشفُ الحساب: النسخةُ النافذةُ `issued` وأحدثُها مدةً هي الرصيدُ القائم؛
//   و`superseded` نسخةٌ عُكست بأخرى — تُعرض في الجدولِ ولا تُحسب رصيدًا.
$client_statements = array();
$stmt_current = null;
try {
    $client_statements = $cp_gate->scopedQuery(array(
        'scope' => array('s' => 'fin_client_statements'),
    ), "SELECT s.id, s.stmt_code, s.period_from, s.period_to, s.currency,
               s.opening_balance, s.invoices_total, s.credit_notes_total, s.collections_total,
               s.advance_deduction, s.retention_held, s.closing_balance,
               s.oldest_unpaid_date, s.overdue_days, s.client_match_state, s.state
        FROM fin_client_statements s
        WHERE {TENANT_SCOPE} AND s.client_id = ?
        ORDER BY s.period_to DESC, s.id DESC", array($client_id));
} catch (\Throwable $t) {
    $client_statements = array();
    error_log('client_profile.php statements: ' . $t->getMessage());
}
foreach ($client_statements as $s) {
    if (trim((string) $s['state']) === 'issued') { $stmt_current = $s; break; } // الأحدثُ أولًا بالترتيب
}

$client_claims = array();
$clm_total = 0; $clm_invoiced = 0;
$clm_net_by_cur = array(); $clm_retention_by_cur = array();
try {
    $client_claims = $cp_gate->scopedQuery(array(
        'scope'  => array('k' => 'claims'),
        'enrich' => array('p' => 'project'),
    ), "SELECT k.id, k.claim_no, k.contract_id, k.project_id, k.period_from, k.period_to,
               k.currency, k.gross_amount, k.retention_amount, k.net_amount, k.tax_amount,
               k.invoice_no, k.invoice_date, k.state,
               p.name AS project_name
        FROM claims k
        LEFT JOIN project p ON p.id = k.project_id AND COALESCE(p.is_deleted,0) = 0
        WHERE {TENANT_SCOPE} AND k.client_id = ? AND COALESCE(k.is_deleted,0) = 0
        ORDER BY k.period_to DESC, k.id DESC", array($client_id));
} catch (\Throwable $t) {
    $client_claims = array();
    error_log('client_profile.php claims: ' . $t->getMessage());
}
foreach ($client_claims as $k) {
    $clm_total++;
    if (trim((string) $k['state']) === 'invoiced') { $clm_invoiced++; }
    cp_money_add($clm_net_by_cur, $k['currency'], $k['net_amount']);
    cp_money_add($clm_retention_by_cur, $k['currency'], $k['retention_amount']);
}

// tax_invoices بلا حذفٍ ناعم (soft=false في السجل) — فلا مرشِّحَ is_deleted
$client_invoices = array();
$inv_total = 0; $inv_cancelled = 0;
$inv_total_by_cur = array(); $inv_tax_by_cur = array();
try {
    $client_invoices = $cp_gate->scopedQuery(array(
        'scope' => array('v' => 'tax_invoices'),
    ), "SELECT v.id, v.serial_no, v.claim_id, v.currency, v.net_amount, v.tax_amount,
               v.total_amount, v.tax_rate, v.state, v.issued_at
        FROM tax_invoices v
        WHERE {TENANT_SCOPE} AND v.client_id = ?
        ORDER BY v.issued_at DESC, v.id DESC", array($client_id));
} catch (\Throwable $t) {
    $client_invoices = array();
    error_log('client_profile.php invoices: ' . $t->getMessage());
}
foreach ($client_invoices as $v) {
    $inv_total++;
    if (trim((string) $v['state']) === 'cancelled') { $inv_cancelled++; continue; } // الملغاةُ لا تُجمع
    cp_money_add($inv_total_by_cur, $v['currency'], $v['total_amount']);
    cp_money_add($inv_tax_by_cur, $v['currency'], $v['tax_amount']);
}

// ══════════════════════════════════════════════════════════════════════════════
// حجوزاتُ الأسطول — وصلٌ مباشرٌ بـfleet_reservations.client_id
// ══════════════════════════════════════════════════════════════════════════════
$client_reservations = array();
$rsv_total = 0; $rsv_active = 0;
$RSV_ACTIVE_STATES = array('مبدئي', 'مؤكَّد');
try {
    $client_reservations = $cp_gate->scopedQuery(array(
        'scope'  => array('f' => 'fleet_reservations'),
        'enrich' => array('e' => 'equipments'),
    ), "SELECT f.id, f.reservation_no, f.equipment_id, f.qty, f.start_date, f.end_date,
               f.state, f.purpose, f.hold_until,
               e.code AS equipment_code, e.name AS equipment_name
        FROM fleet_reservations f
        LEFT JOIN equipments e ON e.id = f.equipment_id
        WHERE {TENANT_SCOPE} AND f.client_id = ? AND COALESCE(f.is_deleted,0) = 0
        ORDER BY f.start_date DESC, f.id DESC", array($client_id));
} catch (\Throwable $t) {
    $client_reservations = array();
    error_log('client_profile.php reservations: ' . $t->getMessage());
}
foreach ($client_reservations as $f) {
    $rsv_total++;
    if (in_array(trim((string) $f['state']), $RSV_ACTIVE_STATES, true)) { $rsv_active++; }
}

// ══════════════════════════════════════════════════════════════════════════════
// أنشطةُ العميلِ التجارية — وصلُ سجلِّ الأنشطةِ ببطاقةِ العميل
// ══════════════════════════════════════════════════════════════════════════════
// «نشاطُ العميل» ليس المعلَّقَ عليه مباشرةً وحدَه: النشاطُ يُعلَّق في
// Clients/activities.php على أحدِ ثلاثةٍ (عميل · فرصة · عقد)، والثلاثةُ تعود
// إليه — الفرصةُ بـopportunities.client_id، والعقدُ عبر مشروعِه
// project.client_id (نفسُ المسارِ الذي تَعُدُّ به هذه الشاشةُ عقودَه أعلاه).
// فتُجمع المساراتُ الثلاثةُ في استعلامٍ واحد، ويُعلَن مصدرُ الوصلِ في عمودٍ
// صريحٍ فلا يختلطُ المباشرُ بالموروثِ عن فرصةٍ أو عقد.
//
// وصلٌ LEFT لا استعلامٌ فرعيّ: البوابةُ تحقن شرطَ العزلِ في WHERE العليا،
// فاسمٌ مستعارٌ محبوسٌ داخلَ قوسٍ لا يبلغُه الشرطُ المحقون.
$acts_link_joins = "FROM activities a
        LEFT JOIN opportunities o ON a.entity_type = 'opportunity' AND o.id = a.entity_id AND COALESCE(o.is_deleted,0) = 0
        LEFT JOIN contracts ct    ON a.entity_type = 'contract'    AND ct.id = a.entity_id AND COALESCE(ct.is_deleted,0) = 0
        LEFT JOIN project pj      ON pj.id = ct.project_id AND COALESCE(pj.is_deleted,0) = 0";

// ثلاثةُ معاملاتٍ بترتيبِ ظهورِها — مباشرٌ ثم فرصةٌ ثم عقد
$acts_link_cond = "COALESCE(a.is_deleted,0) = 0 AND (
            (a.entity_type = 'client'      AND a.entity_id = ?)
         OR (a.entity_type = 'opportunity' AND o.client_id = ?)
         OR (a.entity_type = 'contract'    AND pj.client_id = ?)
        )";

$acts_link_params = array($client_id, $client_id, $client_id);

$acts_total = 0;
$acts_negotiation = 0;
$acts_last_date = '';
$client_activities = array();

/* العرضُ لمن يفتح البطاقةَ — نفسُ قرارِ الفرصِ أعلاه وللعلّةِ نفسِها */
{
    // التعدادُ من المصدرِ نفسِه — فالبطاقةُ تُعلن الكلَّ ولو عُرض منه حدٌّ
    try {
        $acts_stat_rows = $cp_gate->scopedQuery(array(
            'scope'  => array('a' => 'activities'),
            'enrich' => array('o' => 'opportunities', 'ct' => 'contracts', 'pj' => 'project'),
        ), "SELECT COUNT(*) AS total,
                   SUM(CASE WHEN a.is_negotiation = 1 THEN 1 ELSE 0 END) AS negotiation,
                   MAX(a.activity_date) AS last_date
            $acts_link_joins
            WHERE {TENANT_SCOPE} AND $acts_link_cond", $acts_link_params);
        if (!empty($acts_stat_rows)) {
            $acts_total       = intval($acts_stat_rows[0]['total']);
            $acts_negotiation = intval($acts_stat_rows[0]['negotiation']);
            $acts_last_date   = (string) ($acts_stat_rows[0]['last_date'] ?? '');
        }
    } catch (\Throwable $t) {
        error_log('client_profile.php activities stats: ' . $t->getMessage());
    }

    try {
        $client_activities = $cp_gate->scopedQuery(array(
            'scope'  => array('a' => 'activities'),
            'enrich' => array('o' => 'opportunities', 'ct' => 'contracts', 'pj' => 'project',
                              'u' => 'users', 'au' => 'users'),
        ), "SELECT a.id, a.activity_code, a.activity_type, a.entity_type, a.entity_id,
                   a.subject, a.activity_date, a.is_negotiation, a.outcome, a.notes,
                   o.opp_code AS opp_code, o.title AS opp_title,
                   pj.name AS contract_project_name,
                   u.name AS creator_name, au.name AS assigned_name
            $acts_link_joins
            LEFT JOIN users u  ON u.id = a.created_by
            LEFT JOIN users au ON au.id = a.assigned_user_id
            WHERE {TENANT_SCOPE} AND $acts_link_cond
            ORDER BY a.activity_date DESC, a.id DESC
            LIMIT 200", $acts_link_params);
    } catch (\Throwable $t) {
        $client_activities = array();
        error_log('client_profile.php activities list: ' . $t->getMessage());
    }
}

// وسمُ مصدرِ الوصلِ + اسمُ السجلِّ الوسيط — يُحسب مرةً هنا لا في حلقةِ العرض
$acts_via_labels = array('client' => 'مباشر', 'opportunity' => 'عبر فرصة', 'contract' => 'عبر عقد');
foreach ($client_activities as $__i => $__a) {
    $via = isset($acts_via_labels[$__a['entity_type']]) ? $acts_via_labels[$__a['entity_type']] : $__a['entity_type'];
    $through = '—';
    if ($__a['entity_type'] === 'opportunity') {
        $through = trim((string) ($__a['opp_code'] ?? '')) !== ''
            ? $__a['opp_code'] . ' — ' . (string) $__a['opp_title']
            : (string) ($__a['opp_title'] ?? '—');
    } elseif ($__a['entity_type'] === 'contract') {
        $through = 'عقد #' . intval($__a['entity_id'])
            . (trim((string) ($__a['contract_project_name'] ?? '')) !== '' ? ' — ' . $__a['contract_project_name'] : '');
    }
    $client_activities[$__i]['via_label']     = $via;
    $client_activities[$__i]['through_label'] = ($through === '' ? '—' : $through);
}
unset($__i, $__a);

$page_title = 'إيكوبيشن | بطاقة العميل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
/* ── عُدّةُ بطاقةِ الكِيان: هذه الشاشةُ **تصف** بطاقتَها ولا ترسمها ──────────
   كانت هنا ١٣٣ سطرَ `<style>` تعرّف شبكةً وبطاقةً وأربعَ عائلاتٍ من الشارات —
   وكلُّها الآن في `assets/css/ems-profile.css` مرةً واحدةً لكلِّ بطاقاتِ النظام
   (عميل · موظف · وما يأتي: مشروع · مورِّد · معدّة). */
require_once __DIR__ . '/../includes/profile_kit.php';
?>

<div class="main client-profile-page ems-profile ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة العميل';
    $header_icon    = 'fas fa-id-card';
    /* ══ ترويسةٌ عاريةٌ: رجوعٌ و«عن الشاشة» ولا ثالثَ (قرارُ المالك 2026-08-19)
       كانت هنا **ثمانيةُ أزرارٍ** تلتفُّ سطرَين فوقَ البطاقة: مشاريعُ العميلِ
       والفرصُ والمناقصاتُ وعروضُ الأسعارِ والمستخلصاتُ والفواتيرُ وكشفُ الحسابِ
       وسجلُّ الأنشطة. وكانت **تكرارًا لا ملاحة**: كلُّ وجهةٍ منها لها قسمُها
       المُصيَّرُ داخلَ البطاقةِ نفسِها، ولها تبويبُها في شريطِ «رحلةِ العميل»
       أسفلَ الترويسة، ولها بابُها في السايدبار. فثلاثةُ أبوابٍ لبابٍ واحد.

       ◆ والحذفُ لا يُيتِّم مستخدمًا: شريطُ `ems_entity_tabs('client')` باقٍ،
         وحالةُ الفراغِ في ذيلِ البطاقةِ تحمل بابَها الخاصَّ إلى «مشاريعِ
         العميل» — فمن فتح بطاقةً فارغةً وجد الطريق.
       ◆ وزرُّ «عن الشاشة» لا يُصرَّح هنا: يحقنه `ems-screen-about.js` في
         `.head_actions` بعد التحميل — والحاويةُ تُصيَّر ولو خلت المصفوفة. */
    $header_actions = array();
    $header_back    = array('href' => 'clients.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    /* UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا.
       والإرشادُ يشير إلى بابٍ قائم: «رأسُ الشاشة» لم يعد يحمل زرَّ المشاريع. */
    echo ems_states_bundle('لا مشاريعَ مسجَّلةً لهذا العميلِ بعدُ', 'افتح تبويبَ «المشاريعُ والمواقع» من شريطِ رحلةِ العميلِ وأضف أولَ مشروعٍ له');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('client', 'نظرةٌ عامة'); ?>

    <?php
    /* ══ لوحُ الهوية ═══════════════════════════════════════════════════════
       كان سطرًا واحدًا طويلًا تفصلُه شُرَطٌ رأسيةٌ: «القطاع | الهاتف | البريد
       | أضيف بواسطة» — يُقرأ بالبحثِ لا بالنظر، ويلتفُّ التفافًا رديئًا على
       الشاشاتِ الضيقة. صار حقائقَ معنونةً في شبكةٍ، والغائبُ يُعلَن «—» بلونٍ
       باهتٍ فلا يتنكّر شَرْطةٌ في هيئةِ قيمة. */
    echo ems_profile_hero(array(
        'name'   => $client['client_name'],
        'icon'   => 'fas fa-building',
        'status' => array(
            'text' => $client['status'],
            'tone' => ($client['status'] === 'نشط') ? 'ok' : 'danger',
            'icon' => ($client['status'] === 'نشط') ? 'fas fa-circle-check' : 'fas fa-circle-minus',
        ),
        'chips'  => array(
            array('text' => $client['client_code'], 'icon' => 'fas fa-hashtag', 'mono' => true),
            array('text' => $client['entity_type'] ?: 'نوعٌ غيرُ محدد', 'icon' => 'fas fa-sitemap'),
            array('text' => $client['sector_category'] ?: 'قطاعٌ غيرُ محدد', 'icon' => 'fas fa-industry'),
        ),
        'facts'  => array(
            array('label' => 'الهاتف',        'value' => $client['phone']),
            array('label' => 'البريد',        'value' => $client['email']),
            array('label' => 'أضيف بواسطة',   'value' => $client['creator_name']),
        ),
    ));
    ?>

    <?php
    /* ══ بناءُ البطاقةِ أربعُ مجموعاتٍ لا شريطٌ واحدٌ طويل ═══════════════════
       عشرون مؤشرًا في شبكةٍ واحدةٍ جدارٌ لا لوحةُ قيادة. فتُقسَم رحلةُ العميلِ
       إلى مجموعاتٍ مسمّاةٍ: مسارٌ تجاريٌّ ← تنفيذٌ ← مالٌ ← علاقة. وكلُّ
       مجموعةٍ **تختفي بكاملِها** إن خلت مصادرُها — لا عنوانَ فوقَ فراغ. */
    $cp_grp_deliver = (!empty($projects_breakdown) || !empty($client_contracts) || !empty($client_reservations));
    $cp_grp_pipe    = (!empty($client_opportunities) || !empty($client_quotations) || !empty($client_tenders));
    $cp_grp_money   = (!empty($client_statements) || !empty($client_claims) || !empty($client_invoices));
    $cp_grp_rel     = (!empty($client_activities));
    ?>

    <?php
    /* شريطُ الحصيلةِ العامة — سبعةُ أعدادٍ تسبق التفصيل. */
    echo ems_profile_stats(array(
        array('value' => $projects_total,                 'label' => 'إجمالي المشاريع'),
        array('value' => $projects_active,                'label' => 'المشاريع النشطة',   'tone' => $projects_active > 0 ? 'ok' : 'muted'),
        array('value' => $contracts_count,                'label' => 'العقود النشطة',      'tone' => $contracts_count > 0 ? 'ok' : 'muted'),
        array('value' => $suppliers_count,                'label' => 'الموردون المرتبطون'),
        array('value' => $equipments_count,               'label' => 'المعدات المرتبطة'),
        array('value' => $drivers_count,                  'label' => 'المشغلون المرتبطون'),
        array('value' => number_format($total_hours, 0),  'label' => 'إجمالي ساعات التشغيل', 'unit' => 'ساعة'),
    ));
    ?>

    <?php /* ══════════════ ① المسارُ التجاري ══════════════ */ ?>
    <?php if ($cp_grp_pipe): ?>
    <?php
    echo ems_profile_group_open(array(
        'title' => 'المسار التجاري',
        'icon'  => 'fas fa-filter-circle-dollar',
        'meta'  => 'فرصة ← عرضُ سعر ← مناقصة',
    ));

    /* ◆ المؤشراتُ تُبنى مصفوفةً ثم تُصيَّر دفعةً واحدة — فالشرطُ يضيف عنصرًا
         ولا يفتح وسمًا، ولا يبقى شريطٌ مفتوحٌ بلا إغلاقٍ إن سقط شرط. */
    $cp_pipe_stats = array();
    if ($opp_total > 0) {
        $cp_pipe_stats[] = array(
            'value' => intval($opp_total),
            'label' => 'الفرص البيعية' . ($opp_open > 0 ? ' (منها ' . intval($opp_open) . ' مفتوحة)' : ''),
        );
        $cp_pipe_stats[] = array(
            'value' => ($opp_won + $opp_lost) > 0 ? number_format($opp_conversion, 1) . '٪' : '—',
            'label' => 'معدل تحويل الفرص' . (($opp_won + $opp_lost) > 0
                     ? ' (' . intval($opp_won) . ' فوز من ' . intval($opp_won + $opp_lost) . ' محسومة)'
                     : ' — لا فرصةَ محسومةً بعدُ'),
            'tone'  => ($opp_won + $opp_lost) > 0 ? '' : 'muted',
        );
        /* ◆ العملةُ الفارغةُ ليست عملة: `cp_money_fmt` تُخرج سطرًا لكلِّ عملةٍ
             ودلوًا مستقلًّا لـ«بلا عملة» — ولا يُجمع رقمان بعملتين أبدًا. */
        $cp_pipe_stats[] = array(
            'values'  => cp_money_fmt($opp_pipeline_by_cur),
            'label'   => 'قيمة المسار المفتوح' . (count($opp_pipeline_by_cur) > 1 ? ' — كلُّ عملةٍ على حدة' : ''),
            'variant' => 'money',
        );
    }
    if ($quo_total > 0) {
        $cp_pipe_stats[] = array(
            'value' => intval($quo_total),
            'label' => 'عروض الأسعار' . ($quo_open > 0 ? ' (منها ' . intval($quo_open) . ' قيد التداول)' : ''),
        );
        $cp_pipe_stats[] = array(
            'value' => $quo_decided > 0 ? number_format($quo_win_rate, 1) . '٪' : '—',
            'label' => 'معدل قبول العروض' . ($quo_decided > 0
                     ? ' (' . intval($quo_accepted) . ' مقبول من ' . intval($quo_decided) . ' محسوم)'
                     : ' — لا عرضَ محسومًا بعدُ'),
            'tone'  => $quo_decided > 0 ? '' : 'muted',
        );
        $cp_pipe_stats[] = array(
            'values'  => cp_money_fmt($quo_accepted_by_cur),
            'label'   => 'قيمة العروض المقبولة' . (count($quo_accepted_by_cur) > 1 ? ' — كلُّ عملةٍ على حدة' : ''),
            'variant' => 'money',
        );
    }
    ?>
    <?php
    if ($tnd_total > 0) {
        $cp_pipe_stats[] = array(
            'value' => intval($tnd_total),
            'label' => 'المناقصات' . ($tnd_submitted > 0 ? ' (منها ' . intval($tnd_submitted) . ' مقدَّمة)' : ''),
        );
        $cp_pipe_stats[] = array(
            'value' => ($tnd_won + $tnd_lost) > 0 ? number_format($tnd_win_rate, 1) . '٪' : '—',
            'label' => 'معدل الفوز بالمناقصات' . (($tnd_won + $tnd_lost) > 0
                     ? ' (' . intval($tnd_won) . ' فوز من ' . intval($tnd_won + $tnd_lost) . ' محسومة)'
                     : ' — لا مناقصةَ محسومةً بعدُ'),
            'tone'  => ($tnd_won + $tnd_lost) > 0 ? '' : 'muted',
        );
    }
    echo ems_profile_stats($cp_pipe_stats);
    ?>

    <?php if (!empty($client_opportunities)): ?>
    <?php echo ems_profile_section_open(array(
        'title' => 'فرص العميل البيعية',
        'icon'  => 'fas fa-bullseye',
        'note'  => 'مسارُ الفرصِ من الجديدةِ إلى المحسومة.'
                 . (($opp_won || $opp_lost || $opp_excluded)
                    ? ' المحسومة: ' . intval($opp_won) . ' فوز · ' . intval($opp_lost) . ' خسارة · ' . intval($opp_excluded) . ' مستبعدة.'
                    : ''),
    )); ?>
                <div class="table-container">
                    <!-- الترتيبُ الافتراضيُّ بتاريخِ الإغلاقِ المتوقعِ نازلًا (العمود 6) -->
                    <table class="display" id="clientOpportunitiesTable" data-order='[[6,"desc"]]'>
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>الفرصة</th>
                                <th>المرحلة</th>
                                <th>الاحتمالية</th>
                                <th>القيمة المتوقعة</th>
                                <th>نموذج الإيراد</th>
                                <th>الإغلاق المتوقع</th>
                                <th>قرار الدراسة</th>
                                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_opportunities as $opp):
                                $tone = cp_opp_stage_tone($opp['stage']);
                                $rmodel = isset($OPP_REVENUE_MODELS[$opp['revenue_model']])
                                    ? $OPP_REVENUE_MODELS[$opp['revenue_model']]
                                    : ($opp['revenue_model'] ?: '—');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($opp['opp_code']); ?></td>
                                    <td><?php echo htmlspecialchars($opp['title']); ?></td>
                                    <td><span class="ems-profile__pill ems-profile__pill--<?php echo htmlspecialchars($tone); ?>"><?php echo htmlspecialchars($opp['stage']); ?></span></td>
                                    <td><?php echo number_format((float) $opp['probability'], 0); ?>٪</td>
                                    <td>
                                        <?php echo number_format((float) $opp['expected_revenue'], 2); ?>
                                        <span class="ems-profile__unit"><?php echo htmlspecialchars($opp['currency'] ?: '—'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($rmodel); ?></td>
                                    <td><?php echo htmlspecialchars($opp['expected_close_date'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($opp['study_decision'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
    <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

    <?php if (!empty($client_quotations)): ?>
    <?php echo ems_profile_section_open(array('title' => 'عروض أسعار العميل', 'icon' => 'fas fa-file-invoice-dollar')); ?>
            <div class="ems-profile__section-note">
                العرضُ حلقةُ الوصلِ بين الفرصةِ والعقد.
                <?php if ($quo_decided > 0): ?>
                    المحسوم: <?php echo intval($quo_accepted); ?> مقبول · <?php echo intval($quo_rejected); ?> مرفوض.
                <?php endif; ?>
                <?php if (isset($quo_accepted_by_cur['بلا عملة'])): ?>
                    <strong>تنبيه: بعضُ العروضِ بلا عملةٍ مسجَّلة — تُجمع في خانةٍ مستقلّةٍ ولا تُضاف لعملةٍ أخرى.</strong>
                <?php endif; ?>
            </div>
            <div class="table-container">
                <!-- الترتيبُ الافتراضيُّ بتاريخِ السريانِ نازلًا (العمود 5) -->
                <table class="display" id="clientQuotationsTable" data-order='[[5,"desc"]]'>
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الفرصة المرتبطة</th>
                            <th>الحالة</th>
                            <th>القيمة</th>
                            <th>شروط الدفع</th>
                            <th>سريان العرض</th>
                            <th>المُنشئ</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($client_quotations as $q):
                            $qs = trim((string) $q['state']);
                            $qtone = ($qs === 'مقبول') ? 'ok' : (($qs === 'مرفوض') ? 'danger' : (($qs === 'مقدم') ? 'warn' : 'neutral'));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($q['quotation_code']); ?></td>
                                <td><?php echo htmlspecialchars($q['opp_label']); ?></td>
                                <td><span class="ems-profile__pill ems-profile__pill--<?php echo $qtone; ?>"><?php echo htmlspecialchars($qs); ?></span></td>
                                <td>
                                    <?php echo number_format((float) $q['amount_total'], 2); ?>
                                    <span class="ems-profile__unit"><?php echo htmlspecialchars(trim((string) $q['currency']) !== '' ? $q['currency'] : 'بلا عملة'); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($q['payment_terms'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($q['validity_date'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($q['creator_name'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

    <?php if (!empty($client_tenders)): ?>
    <?php echo ems_profile_section_open(array('title' => 'مناقصات العميل', 'icon' => 'fas fa-gavel')); ?>
            <div class="ems-profile__section-note">
                تشمل المناقصاتِ التي العميلُ فيها <strong>جهةٌ طارحة</strong>، والمرتبطةَ بإحدى فرصِه — ومصدرُ الوصلِ مُعلَنٌ في عمودِه.
                <?php if ($tnd_won || $tnd_lost): ?>
                    المحسومة: <?php echo intval($tnd_won); ?> فوز · <?php echo intval($tnd_lost); ?> خسارة.
                <?php endif; ?>
            </div>
            <div class="table-container">
                <!-- الترتيبُ الافتراضيُّ بتاريخِ الإقفالِ نازلًا (العمود 7) -->
                <table class="display" id="clientTendersTable" data-order='[[7,"desc"]]'>
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>المناقصة</th>
                            <th>مصدر الوصل</th>
                            <th>الجهة الطارحة</th>
                            <th>الفرصة المرتبطة</th>
                            <th>حالة المشاركة</th>
                            <th>النتيجة</th>
                            <th>تاريخ الإقفال</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($client_tenders as $tnd): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tnd['tender_code']); ?></td>
                                <td><?php echo htmlspecialchars($tnd['name'] ?: '—'); ?></td>
                                <td><span class="ems-profile__pill ems-profile__pill--<?php echo ($tnd['via_label'] === 'جهةٌ طارحة') ? 'ok' : 'neutral'; ?>"><?php echo htmlspecialchars($tnd['via_label']); ?></span></td>
                                <td><?php echo htmlspecialchars($tnd['authority_name'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($tnd['opp_label']); ?></td>
                                <td><?php echo htmlspecialchars($tnd['participation_state']); ?></td>
                                <td><span class="ems-profile__pill ems-profile__pill--<?php echo htmlspecialchars(cp_tnd_result_class($tnd['result'])); ?>"><?php echo htmlspecialchars($tnd['result']); ?></span></td>
                                <td><?php echo htmlspecialchars($tnd['closing_date'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

    <?php echo ems_profile_group_close(); /* ① */ ?>
    <?php endif; ?>

    <?php /* ══════════════ ② التنفيذُ والتسليم ══════════════ */ ?>
    <?php if ($cp_grp_deliver): ?>
    <?php echo ems_profile_group_open(array(
        'title' => 'التنفيذ والتسليم',
        'icon'  => 'fas fa-helmet-safety',
        'meta'  => 'عقدٌ ← مشروعٌ ← أسطولٌ على الأرض',
    )); ?>

    <?php /* لا جدولَ فارغًا: القسمُ لا يُصيَّر أصلًا بلا صفوف (قرارُ المالك 2026-08-19) */ ?>
    <?php if (!empty($projects_breakdown)): ?>
    <?php echo ems_profile_section_open(array('title' => 'مشاريع العميل', 'icon' => 'fas fa-diagram-project')); ?>
            <div class="table-container">
                <table class="display" id="clientProjectsTable">
                    <thead>
                        <tr>
                            <th>المشروع</th>
                            <th>كود المشروع</th>
                            <th>المعدات</th>
                            <th>الموردون</th>
                            <th>الساعات</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects_breakdown as $row): ?>
                            <tr>
                                <td><a href="../Projects/project_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['project_code'] ?: '-'); ?></td>
                                <td><?php echo intval($row['equipments_count']); ?></td>
                                <td><?php echo intval($row['suppliers_count']); ?></td>
                                <td><?php echo number_format($row['hours_sum'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>


    <?php if (!empty($client_contracts)): ?>
    <?php echo ems_profile_section_open(array('title' => 'عقود العميل', 'icon' => 'fas fa-file-signature')); ?>
            <div class="ems-profile__section-note">العقودُ تصل العميلَ عبرَ مشاريعِه — لا عمودَ عميلٍ في جدولِ العقود.</div>
            <div class="table-container">
                <table class="display" id="clientContractsTable" data-order='[[2,"desc"]]'>
                    <thead>
                        <tr>
                            <th>العقد</th>
                            <th>المشروع</th>
                            <th>تاريخ التوقيع</th>
                            <th>البداية الفعلية</th>
                            <th>النهاية الفعلية</th>
                            <th>المدة (شهور)</th>
                            <th>الهدف الشهري (ساعة)</th>
                            <th>الحالة</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($client_contracts as $ct): ?>
                            <tr>
                                <td>عقد #<?php echo intval($ct['id']); ?></td>
                                <td><?php echo htmlspecialchars($ct['project_name'] ?: '—'); ?><?php echo $ct['project_code'] ? ' (' . htmlspecialchars($ct['project_code']) . ')' : ''; ?></td>
                                <td><?php echo htmlspecialchars($ct['contract_signing_date'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($ct['actual_start'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($ct['actual_end'] ?: '—'); ?></td>
                                <td><?php echo intval($ct['contract_duration_months']) ?: '—'; ?></td>
                                <td><?php echo intval($ct['hours_monthly_target']) ? number_format($ct['hours_monthly_target'], 0) : '—'; ?></td>
                                <td><span class="ems-profile__pill ems-profile__pill--<?php echo intval($ct['status']) === 1 ? 'ok' : 'neutral'; ?>"><?php echo intval($ct['status']) === 1 ? 'نشط' : 'غير نشط'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

    <?php if (!empty($client_reservations)): ?>
    <?php echo ems_profile_section_open(array('title' => 'حجوزات الأسطول', 'icon' => 'fas fa-calendar-check')); ?>
            <div class="ems-profile__section-note">القائمُ منها الآن: <?php echo intval($rsv_active); ?> من <?php echo intval($rsv_total); ?> (مبدئيٌّ أو مؤكَّد).</div>
            <div class="table-container">
                <table class="display" id="clientReservationsTable" data-order='[[4,"desc"]]'>
                    <thead>
                        <tr>
                            <th>رقم الحجز</th>
                            <th>المعدة</th>
                            <th>العدد</th>
                            <th>الغرض</th>
                            <th>من</th>
                            <th>إلى</th>
                            <th>الحالة</th>
                            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($client_reservations as $f):
                            $fs = trim((string) $f['state']);
                            $ftone = ($fs === 'مؤكَّد' || $fs === 'محوَّل لعقد') ? 'ok' : (($fs === 'ملغى') ? 'danger' : (($fs === 'منتهٍ') ? 'neutral' : 'warn'));
                            $eq = trim((string) ($f['equipment_name'] ?? ''));
                            $eqc = trim((string) ($f['equipment_code'] ?? ''));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($f['reservation_no']); ?></td>
                                <td><?php echo $eq !== '' ? htmlspecialchars($eq . ($eqc !== '' ? ' (' . $eqc . ')' : '')) : '— بالفئة —'; ?></td>
                                <td><?php echo intval($f['qty']); ?></td>
                                <td><?php echo htmlspecialchars($f['purpose'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($f['start_date'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($f['end_date'] ?: '—'); ?></td>
                                <td><span class="ems-profile__pill ems-profile__pill--<?php echo $ftone; ?>"><?php echo htmlspecialchars($fs); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

    <?php echo ems_profile_group_close(); /* ② */ ?>
    <?php endif; ?>

    <?php /* ══════════════ ③ الوضعُ المالي ══════════════ */ ?>
    <?php if ($cp_grp_money): ?>
    <?php echo ems_profile_group_open(array(
        'title' => 'الوضع المالي',
        'icon'  => 'fas fa-scale-balanced',
        'meta'  => 'مستخلصٌ ← فاتورةٌ ← تحصيلٌ ← رصيد',
    )); ?>

        <?php
        $cp_money_stats = array();
        if ($stmt_current !== null) {
            /* الرصيدُ يُعرض بعملتِه لصيقةً به — لا رقمَ ماليٍّ بلا عملةٍ تُقرأ معه */
            $cp_money_stats[] = array(
                'value'   => number_format((float) $stmt_current['closing_balance'], 2),
                'unit'    => $stmt_current['currency'],
                'label'   => 'الرصيد الختامي — كشف ' . $stmt_current['stmt_code'] . ' حتى ' . $stmt_current['period_to'],
                'variant' => 'money',
            );
            $cp_money_stats[] = array(
                'value'   => number_format((float) $stmt_current['collections_total'], 2),
                'unit'    => $stmt_current['currency'],
                'label'   => 'المحصَّل في المدة',
                'variant' => 'money',
                'tone'    => (float) $stmt_current['collections_total'] > 0 ? 'ok' : 'muted',
            );
            /* ◆ عدّادُ تأخُّرٍ موجبٌ ليس رقمًا محايدًا — يُلوَّن إنذارًا */
            $cp_money_stats[] = array(
                'value' => intval($stmt_current['overdue_days']),
                'label' => 'أيام التأخر' . (!empty($stmt_current['oldest_unpaid_date'])
                         ? ' — أقدمُ غيرِ مسدَّدٍ ' . $stmt_current['oldest_unpaid_date'] : ''),
                'tone'  => intval($stmt_current['overdue_days']) > 0 ? 'danger' : 'ok',
                'unit'  => 'يوم',
            );
            $cp_money_stats[] = array(
                'value'   => $stmt_current['client_match_state'],
                'label'   => 'مطابقة العميل على الكشف',
                'variant' => 'date',
            );
        }
        if ($clm_total > 0) {
            $cp_money_stats[] = array(
                'value' => intval($clm_total),
                'label' => 'المستخلصات' . ($clm_invoiced > 0 ? ' (منها ' . intval($clm_invoiced) . ' مفوترة)' : ''),
            );
            $cp_money_stats[] = array(
                'values'  => cp_money_fmt($clm_net_by_cur, 2),
                'label'   => 'صافي المستخلصات' . (count($clm_net_by_cur) > 1 ? ' — كلُّ عملةٍ على حدة' : ''),
                'variant' => 'money',
            );
            $cp_money_stats[] = array(
                'values'  => cp_money_fmt($clm_retention_by_cur, 2),
                'label'   => 'المحتجَز التعاقدي',
                'variant' => 'money',
            );
        }
        if ($inv_total > 0) {
            $cp_money_stats[] = array(
                'value' => intval($inv_total),
                'label' => 'الفواتير الضريبية' . ($inv_cancelled > 0 ? ' (منها ' . intval($inv_cancelled) . ' ملغاة)' : ''),
            );
            $cp_money_stats[] = array(
                'values'  => cp_money_fmt($inv_total_by_cur, 2),
                'label'   => 'إجمالي المفوتر' . ($inv_cancelled > 0 ? ' — الملغاةُ مطروحة' : ''),
                'variant' => 'money',
            );
        }
        echo ems_profile_stats($cp_money_stats);
        ?>

        <?php if (!empty($client_statements)): ?>
        <?php echo ems_profile_section_open(array('title' => 'كشوف حساب العميل', 'icon' => 'fas fa-file-invoice')); ?>
                <div class="ems-profile__section-note">النسخةُ <strong>issued</strong> هي النافذة؛ و<strong>superseded</strong> نسخةٌ عُكست بأخرى — تُعرض للأثرِ ولا تُحسب رصيدًا.</div>
                <div class="table-container">
                    <table class="display" id="clientStatementsTable" data-order='[[2,"desc"]]'>
                        <thead>
                            <tr>
                                <th>الكشف</th>
                                <th>من</th>
                                <th>إلى</th>
                                <th>رصيد أول المدة</th>
                                <th>فواتير</th>
                                <th>إشعارات دائنة</th>
                                <th>تحصيل</th>
                                <th>محتجَز</th>
                                <th>رصيد آخر المدة</th>
                                <th>تأخر (يوم)</th>
                                <th>الحالة</th>
                                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_statements as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['stmt_code']); ?></td>
                                    <td><?php echo htmlspecialchars($s['period_from']); ?></td>
                                    <td><?php echo htmlspecialchars($s['period_to']); ?></td>
                                    <td><?php echo ems_sensitive_display($conn, "fin_client_statements.opening_balance", number_format((float) $s["opening_balance"], 2), "client:" . (int)($client_id ?? 0), "ملف العميل"); ?> <span class="ems-profile__unit"><?php echo htmlspecialchars($s['currency']); ?></span></td>
                                    <td><?php echo number_format((float) $s['invoices_total'], 2); ?></td>
                                    <td><?php echo number_format((float) $s['credit_notes_total'], 2); ?></td>
                                    <td><?php echo number_format((float) $s['collections_total'], 2); ?></td>
                                    <td><?php echo number_format((float) $s['retention_held'], 2); ?></td>
                                    <td><strong><?php echo number_format((float) $s['closing_balance'], 2); ?></strong></td>
                                    <td><?php echo intval($s['overdue_days']); ?></td>
                                    <td><span class="ems-profile__pill ems-profile__pill--<?php echo trim((string) $s['state']) === 'issued' ? 'ok' : 'neutral'; ?>"><?php echo htmlspecialchars($s['state']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php echo ems_profile_section_close(); ?>
        <?php endif; ?>

        <?php if (!empty($client_claims)): ?>
        <?php echo ems_profile_section_open(array('title' => 'مستخلصات العميل', 'icon' => 'fas fa-receipt')); ?>
                <div class="table-container">
                    <table class="display" id="clientClaimsTable" data-order='[[3,"desc"]]'>
                        <thead>
                            <tr>
                                <th>رقم المستخلص</th>
                                <th>المشروع</th>
                                <th>من</th>
                                <th>إلى</th>
                                <th>الإجمالي</th>
                                <th>المحتجَز</th>
                                <th>الصافي</th>
                                <th>الضريبة</th>
                                <th>الفاتورة</th>
                                <th>الحالة</th>
                                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_claims as $k):
                                $ks = trim((string) $k['state']);
                                $ktone = ($ks === 'invoiced') ? 'ok' : (($ks === 'draft') ? 'neutral' : 'warn');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($k['claim_no']); ?></td>
                                    <td><?php echo htmlspecialchars($k['project_name'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($k['period_from']); ?></td>
                                    <td><?php echo htmlspecialchars($k['period_to']); ?></td>
                                    <td><?php echo number_format((float) $k['gross_amount'], 2); ?> <span class="ems-profile__unit"><?php echo htmlspecialchars($k['currency']); ?></span></td>
                                    <td><?php echo number_format((float) $k['retention_amount'], 2); ?></td>
                                    <td><strong><?php echo number_format((float) $k['net_amount'], 2); ?></strong></td>
                                    <td><?php echo number_format((float) $k['tax_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($k['invoice_no'] ?: '—'); ?></td>
                                    <td><span class="ems-profile__pill ems-profile__pill--<?php echo $ktone; ?>"><?php echo htmlspecialchars($ks); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php echo ems_profile_section_close(); ?>
        <?php endif; ?>

        <?php if (!empty($client_invoices)): ?>
        <?php echo ems_profile_section_open(array('title' => 'الفواتير الضريبية', 'icon' => 'fas fa-file-invoice-dollar')); ?>
                <div class="table-container">
                    <table class="display" id="clientInvoicesTable" data-order='[[5,"desc"]]'>
                        <thead>
                            <tr>
                                <th>الرقم التسلسلي</th>
                                <th>الصافي</th>
                                <th>نسبة الضريبة</th>
                                <th>الضريبة</th>
                                <th>الإجمالي</th>
                                <th>تاريخ الإصدار</th>
                                <th>الحالة</th>
                                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_invoices as $v): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['serial_no']); ?></td>
                                    <td><?php echo number_format((float) $v['net_amount'], 2); ?> <span class="ems-profile__unit"><?php echo htmlspecialchars($v['currency']); ?></span></td>
                                    <td><?php echo $v['tax_rate'] !== null ? number_format((float) $v['tax_rate'], 2) . '٪' : '—'; ?></td>
                                    <td><?php echo number_format((float) $v['tax_amount'], 2); ?></td>
                                    <td><strong><?php echo number_format((float) $v['total_amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($v['issued_at']); ?></td>
                                    <td><span class="ems-profile__pill ems-profile__pill--<?php echo trim((string) $v['state']) === 'issued' ? 'ok' : 'danger'; ?>"><?php echo htmlspecialchars($v['state']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php echo ems_profile_section_close(); ?>
        <?php endif; ?>
    <?php echo ems_profile_group_close(); /* ③ */ ?>
    <?php endif; ?>

    <?php /* ══════════════ ④ العلاقةُ والتواصل ══════════════ */ ?>
    <?php if ($cp_grp_rel): ?>
    <?php echo ems_profile_group_open(array(
        'title' => 'العلاقة والتواصل',
        'icon'  => 'fas fa-comments',
        'meta'  => 'زياراتٌ · اجتماعاتٌ · جولاتُ تفاوض',
    )); ?>

        <?php echo ems_profile_stats(array(
            array(
                'value' => intval($acts_total),
                'label' => 'الأنشطة التجارية' . ($acts_negotiation > 0 ? ' (منها ' . intval($acts_negotiation) . ' تفاوضية)' : ''),
            ),
            /* ◆ بطاقةُ «آخر نشاط» تحمل تاريخًا لا عددًا — فحجمُها أصغرُ كي لا يلتفَّ السطر */
            array(
                'value'   => $acts_last_date !== '' ? $acts_last_date : '—',
                'label'   => 'آخر نشاط',
                'variant' => 'date',
                'tone'    => $acts_last_date !== '' ? '' : 'muted',
            ),
        )); ?>

    <?php if (!empty($client_activities)): ?>
    <?php echo ems_profile_section_open(array('title' => 'أنشطة العميل التجارية', 'icon' => 'fas fa-handshake')); ?>
                <div class="ems-profile__section-note">
                    تشمل الأنشطةَ المعلَّقةَ على العميلِ مباشرةً، وعلى فرصِه، وعلى عقودِ مشاريعِه — ومصدرُ الوصلِ مُعلَنٌ في عمودِ «مصدر الوصل».
                    <?php if ($acts_total > count($client_activities)): ?>
                        <strong>(معروضٌ أحدثُ <?php echo count($client_activities); ?> من <?php echo intval($acts_total); ?>)</strong>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                    <!-- الترتيبُ الافتراضيُّ بالتاريخِ نازلًا (العمود 5) — وإلا فرضَ DataTables
                         ترتيبَ العمودِ الأولِ صاعدًا وضاع ترتيبُ ORDER BY في الاستعلام -->
                    <table class="display" id="clientActivitiesTable" data-order='[[5,"desc"]]'>
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>النوع</th>
                                <th>الموضوع</th>
                                <th>مصدر الوصل</th>
                                <th>السجل الوسيط</th>
                                <th>التاريخ</th>
                                <th>المسؤول</th>
                                <th>المخرجات</th>
                                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_activities as $act): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($act['activity_code']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($act['activity_type']); ?>
                                        <?php if (intval($act['is_negotiation']) === 1): ?>
                                            <span class="ems-profile__pill ems-profile__pill--gold">تفاوض</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($act['subject'] ?: '—'); ?></td>
                                    <td><span class="ems-profile__pill ems-profile__pill--<?php echo htmlspecialchars(cp_via_tone($act['entity_type'])); ?>"><?php echo htmlspecialchars($act['via_label']); ?></span></td>
                                    <td><?php echo htmlspecialchars($act['through_label']); ?></td>
                                    <td><?php echo htmlspecialchars($act['activity_date'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($act['assigned_name'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($act['outcome'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

    <?php echo ems_profile_group_close(); /* ④ */ ?>
    <?php endif; ?>

    <?php
    /* ── لا شيءَ يُعرض؟ فسببٌ واحدٌ صريحٌ لا ثلاثُ بطاقاتٍ اختفت بلا بيان ──────
       إخفاءُ الجدولِ الفارغِ يُنظّف الشاشة، لكنّه إن أخفى كلَّ شيءٍ ترك
       المستخدمَ أمام بطاقةٍ صامتةٍ لا يدري أفارغةٌ هي أم معطوبة. فتُعلَن
       الحالةُ مرةً واحدةً ببابٍ يُفتح منها — وهو ما تشترطه بوابةُ AC-U6.
       والمقاماتُ تُقرأ من المصدرِ لا من الجداولِ المعروضة: عميلٌ له مشاريعُ
       فوقَ العشرِ يظهر جدولُه أصلًا، والصفرُ هنا صفرٌ حقيقيّ. */
    /* ◆ المقامُ هنا $projects_total (تعدادُ المصدر) لا $projects_breakdown
       (الصفوفُ المعروضة): الثانيةُ تفرغ أيضًا حين **يفشل** استعلامُها —
       فالبناءُ عليها يقول «لا سجلاتِ» لعميلٍ له مشاريعُ، وذاك صفرٌ كاذبٌ لا
       فراغٌ صادق. والفشلُ يُعلَن حالةَ خطأٍ أدناه لا يُبتلع صامتًا. */
    $cp_projects_failed = ($projects_total > 0 && empty($projects_breakdown));
    if ($cp_projects_failed) {
        echo ems_state(
            'error',
            'تعذّر عرضُ ملخصِ المشاريع',
            'للعميلِ ' . intval($projects_total) . ' مشروعًا في السجلِّ لكنَّ قراءةَ الملخصِ أخفقت — أعد المحاولةَ، وإن استمر الخللُ أبلغ عن مشكلةٍ من هذه الشاشة'
        );
    }
    /* المجموعاتُ الأربعُ تُعرض لمن يفتح البطاقة، فالمقامُ أعدادُها لا صلاحياتُها.
       ولا يكفي أن تخلوَ الجداولُ المعروضة: نقيس **كلَّ** مصادرِ البطاقةِ —
       فلو خلا المسارُ والتنفيذُ والمالُ والعلاقةُ جميعًا فتلك بطاقةٌ فارغةٌ حقًّا. */
    $cp_nothing = !$cp_projects_failed
        && !$cp_grp_pipe && !$cp_grp_deliver && !$cp_grp_money && !$cp_grp_rel
        && $projects_total === 0;
    if ($cp_nothing):
        $cp_empty_hint = 'لم يُسجَّل لهذا العميلِ مشروعٌ ولا فرصةٌ ولا عرضُ سعرٍ ولا مناقصةٌ'
            . ' ولا عقدٌ ولا حركةٌ ماليةٌ ولا نشاطٌ تجاريٌّ بعدُ — تبدأ رحلتُه بأولِ مشروعٍ أو فرصة.';
        echo ems_state(
            'empty',
            'بطاقةُ العميلِ بلا سجلاتٍ بعدُ',
            $cp_empty_hint,
            '<a class="add-btn" href="../Projects/projects.php?client_id=' . intval($client_id) . '">'
                . '<i class="fas fa-diagram-project"></i> افتح مشاريعَ العميل</a>'
        );
    endif;
    ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>


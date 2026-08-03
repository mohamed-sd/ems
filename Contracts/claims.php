<?php
/**
 * Contracts/claims.php — المستخلصات: مطالبةُ الفترة من الوحدات المعتمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-08 §5.2 · §8.2 · FES §8. الرحلةُ أربعُ خطواتٍ ظاهرةٌ في الشاشة:
 *     وحداتٌ معتمدة → مستخلص → فاتورةٌ ضريبية → ذمّةُ العميل
 * والمنطقُ كلُّه في `claim_helpers.php` — هذه واجهةٌ محضة.
 *
 * صفرُ إدخالِ كمية (المبدآن ١٣ و١٤): المُدخَلُ العقدُ والفترةُ وحدهما، وكلُّ ما
 * سواهما مشتقٌّ — العميلُ من سلسلة (تشغيل → مشروع → عميل)، والسعرُ من سطر معدة
 * العقد، والصافي محسوب.
 */

require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/claim_helpers.php';
require_once __DIR__ . '/note_helpers.php';      // M-02 — الإشعارُ الدائن/المدين
require_once __DIR__ . '/advance_helpers.php';   // M-01 — دفترُ الدفعة المقدَّمة

if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }

function clm_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function clm_num($v) { return ($v === null || $v === '') ? '—' : number_format((float) $v, 2); }
function clm_back($msg) { header('Location: claims.php?msg=' . urlencode($msg)); exit(); }

$role           = strval($_SESSION['user']['role']);
$is_super_admin = ($role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.')); exit();
}

// ── فصلُ اليدين في طبقة المنح (قرارُ المالك 2026-07-28 · نمطُ تسويات الموردين) ──
// `can_add`  = يدُ المبيعات: التوليدُ والرفعُ للمالية والإلغاء
// `can_edit` = يدُ المالية: **الإجازة** (فاتورةٌ ضريبيةٌ + ذمّةُ العميل)
// فلا يجتمعان لدورٍ واحدٍ في المنح — والشاشةُ تقرأ العمودَين بهذا المعنى حرفيًّا.
$perms      = check_page_permissions($conn, 'Contracts/claims.php');
$can_view   = $is_super_admin ? true : !empty($perms['can_view']);
$can_add    = $is_super_admin ? true : !empty($perms['can_add']);
$can_approve = $is_super_admin ? true : !empty($perms['can_edit']);
if (!$can_view) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('لا توجد صلاحية عرض المستخلصات ❌')); exit();
}

$gate = claim_gate($is_super_admin);

if (empty($_SESSION['clm_csrf_token'])) {
    $_SESSION['clm_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$clm_csrf = $_SESSION['clm_csrf_token'];
function clm_check_csrf() {
    return isset($_POST['clm_csrf']) && isset($_SESSION['clm_csrf_token'])
        && hash_equals($_SESSION['clm_csrf_token'], (string) $_POST['clm_csrf']);
}

// ══════════════════════════════════════════════════════════════════════════════
// الأفعال الثلاثة: توليد · اعتماد · إلغاء
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if (!$can_add) { clm_back('لا توجد صلاحية إنشاء ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = claim_generate($conn, $_POST['contract_id'] ?? 0,
        trim($_POST['period_from'] ?? ''), trim($_POST['period_to'] ?? ''), $current_user_id);
    if ($res['status'] === 'created') {
        clm_back('وُلّد المستخلص بـ' . $res['lines'] . ' بندًا · إجمالي ' . number_format($res['gross'], 2)
                 . ($res['reason'] !== '' ? (' — ' . $res['reason']) : '') . ' ✅');
    } elseif ($res['status'] === 'exists') {
        clm_back($res['reason'] . ' ⚠️');
    } elseif ($res['status'] === 'empty') {
        clm_back($res['reason'] . ' ⚠️');
    }
    clm_back('تعذّر التوليد: ' . ($res['reason'] !== '' ? $res['reason'] : 'خطأٌ داخلي') . ' ❌');
}

// رفعٌ للمالية (يدُ المبيعات) — draft → review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!$can_add) { clm_back('لا توجد صلاحية رفع ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = claim_submit(intval($_POST['id'] ?? 0), $current_user_id);
    if ($res['status'] === 'submitted') { clm_back($res['reason'] . ' — بانتظار إجازة المالية ✅'); }
    clm_back($res['reason'] !== '' ? ($res['reason'] . ' ⚠️') : 'تعذّر الرفع ❌');
}

// إجازةٌ مالية (يدُ المالية) — فاتورةٌ ضريبيةٌ + ذمّةُ العميل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    if (!$can_approve) { clm_back('الإجازةُ صلاحيةُ المالية — لا يعتمد المستخلصَ من أنشأه ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = claim_approve($conn, intval($_POST['id'] ?? 0),
        (($_POST['tax_code'] ?? '') !== '' ? $_POST['tax_code'] : null), $current_user_id);
    if ($res['status'] === 'approved') {
        clm_back('أُجيز المستخلص · فاتورة ' . $res['invoice_no'] . ' · وفُتحت ذمّةُ العميل ✅');
    } elseif ($res['status'] === 'exists') {
        clm_back('المستخلصُ معتمدٌ سلفًا (فاتورة ' . $res['invoice_no'] . ') ⚠️');
    }
    clm_back('تعذّرت الإجازة: ' . ($res['reason'] !== '' ? $res['reason'] : 'خطأٌ داخلي') . ' ❌');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!$can_add) { clm_back('لا توجد صلاحية ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $cid = intval($_POST['id'] ?? 0);
    try {
        $c = $gate->selectOne('claims', array('where' => array('id' => $cid)));
        if (!$c) { clm_back('المستخلصُ غير موجود ❌'); }
        if (in_array((string) $c['state'], array('approved', 'invoiced', 'collected'), true)) {
            clm_back('لا يُلغى مستخلصٌ معتمد — التصحيحُ بمستخلصٍ عاكسٍ موثَّق ❌');
        }
        $gate->update('claims', array('state' => 'cancelled'), array('id' => $cid));
        clm_back('أُلغي المستخلصُ ورُدَّت وقائعُه للاستخلاص ✅');
    } catch (\Throwable $t) {
        error_log('claim cancel: ' . $t->getMessage());
        clm_back('تعذّر الإلغاء ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// M-06 · فعلُ النزاع على بند المستخلص (ENT-03 §3-⑤)
// ──────────────────────────────────────────────────────────────────────────────
// كان النزاعُ **عرضًا باهتًا بلا فعل**: العَلَمُ يُقرأ ويُحترم في الاحتساب ولا
// يُرفع من الشاشة ولا يُحسم. واليدان: **الرفعُ لمن يُعدّ** (`can_add` — المبيعاتُ
// أو مراجعُ العميل)، و**الحسمُ ليدِ الإجازة** (`can_approve` — المالية).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispute_raise') {
    if (!$can_add) { clm_back('رفعُ النزاع صلاحيةُ من يُعدّ ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    require_once __DIR__ . '/../app/Services/Revenue/ClaimDisputeService.php';
    $r = \App\Services\Revenue\ClaimDisputeService::raise($conn, $gate, $company_id,
        intval($_POST['line_id'] ?? 0),
        array('reason' => strval($_POST['dispute_reason'] ?? ''),
              'doc_ref' => strval($_POST['dispute_doc_ref'] ?? '')), $current_user_id);
    clm_back($r['ok']
        ? ('رُفع النزاعُ على البند — والبقيةُ تمضي (نزاعٌ مفتوح: ' . $r['open_count'] . ') ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispute_resolve') {
    if (!$can_approve) { clm_back('حسمُ النزاع صلاحيةُ المالية ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    require_once __DIR__ . '/../app/Services/Revenue/ClaimDisputeService.php';
    $r = \App\Services\Revenue\ClaimDisputeService::resolve($conn, $gate, $company_id,
        intval($_POST['line_id'] ?? 0), strval($_POST['resolution'] ?? ''),
        strval($_POST['resolution_note'] ?? ''), $current_user_id);
    clm_back($r['ok']
        ? ('حُسم النزاعُ — الصافي ' . $r['net'] . ' · نزاعٌ مفتوح: ' . $r['open_count'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

// ══════════════════════════════════════════════════════════════════════════════
// M-02 · الإشعارُ الدائن/المدين — يصحّح الفاتورةَ الصادرةَ بلا أن يمسّها
// ──────────────────────────────────────────────────────────────────────────────
// **اليدان نفسُهما**: `can_add` = المبيعاتُ تُنشئ وترفع · `can_edit` = الماليةُ
// تُجيز. فلا منحةَ جديدةٌ ولا وحدةَ جديدة — الإشعارُ تصحيحٌ للمستخلص فيَرِث
// حراستَه. والخدمةُ تمنع فوقهما اعتمادَ المرءِ ما أعدّ.
// ══════════════════════════════════════════════════════════════════════════════
// M-01: تسجيلُ قبضِ دفعةٍ مقدَّمة — **يدُ المالية** (`can_edit`)، فهي التي تقبض.
// والمبلغُ يُدخَل من سند القبض ولا يُشتق من نسبةٍ (قاعدةُ عدم التلفيق).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'advance_record') {
    if (!$can_approve) { clm_back('تسجيلُ قبض الدفعة صلاحيةُ المالية ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = advance_record($conn, $gate, intval($_POST['contract_id'] ?? 0),
        $_POST['amount'] ?? 0, strval($_POST['received_date'] ?? ''),
        strval($_POST['doc_ref'] ?? ''), strval($_POST['note'] ?? ''), $current_user_id);
    if (!$res['ok']) { clm_back($res['reason'] . ' ❌'); }
    clm_back(!empty($res['existing'])
        ? ($res['reason'] . ' ⚠️')
        : ('سُجّل قبضُ الدفعة ' . $res['advance_no']
           . ' — ومن الآن يُستقطع منها بسقفها ولا يتجاوزه ✅'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'note_create') {
    if (!$can_add) { clm_back('إنشاءُ الإشعار صلاحيةُ المبيعات ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = cdnote_create($gate, intval($_POST['claim_id'] ?? 0),
        strval($_POST['note_kind'] ?? ''), $_POST['amount'] ?? 0,
        strval($_POST['reason'] ?? ''), strval($_POST['doc_ref'] ?? ''),
        (($_POST['claim_line_id'] ?? '') !== '' ? intval($_POST['claim_line_id']) : null),
        // مفتاحُ عطالةٍ مشتقٌّ من المدخلات: ضغطتان على الزرّ لا تفتحان ذمّتين
        substr(sha1(strval($_POST['claim_id'] ?? '') . '|' . strval($_POST['note_kind'] ?? '')
                    . '|' . strval($_POST['amount'] ?? '') . '|' . strval($_POST['doc_ref'] ?? '')), 0, 40),
        $current_user_id);
    if (!$res['ok']) { clm_back($res['reason'] . ' ❌'); }
    clm_back(!empty($res['existing'])
        ? ('إشعارٌ مطابقٌ قائمٌ سلفًا: ' . $res['note_no'] . ' ⚠️')
        : ('أُنشئ الإشعار ' . $res['note_no'] . ' مسودةً — ارفعه للمالية ✅'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'note_submit') {
    if (!$can_add) { clm_back('لا توجد صلاحية رفع ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = cdnote_submit($gate, intval($_POST['note_id'] ?? 0), $current_user_id);
    clm_back($res['ok'] ? 'رُفع الإشعارُ للمالية ✅' : ($res['reason'] . ' ❌'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'note_approve') {
    if (!$can_approve) { clm_back('إجازةُ الإشعار صلاحيةُ المالية ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = cdnote_approve($conn, $gate, intval($_POST['note_id'] ?? 0), $current_user_id);
    if (!$res['ok']) { clm_back($res['reason'] . ' ❌'); }
    clm_back(!empty($res['existing'])
        ? ($res['reason'] . ' ⚠️')
        : 'أُجيز الإشعارُ وتحرّكت ذمّةُ العميل بمقداره — والفاتورةُ الأصليةُ كما صدرت ✅');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'note_cancel') {
    if (!$can_add) { clm_back('لا توجد صلاحية ❌'); }
    if (!clm_check_csrf()) { clm_back('رمز الحماية غير صالح ❌'); }
    $res = cdnote_cancel($gate, intval($_POST['note_id'] ?? 0), $current_user_id);
    clm_back($res['ok'] ? 'أُلغي الإشعار ✅' : ($res['reason'] . ' ❌'));
}

// ══════════════════════════════════════════════════════════════════════════════
// القراءة
// ══════════════════════════════════════════════════════════════════════════════
$states = claim_states();
$filter_state = isset($_GET['state']) && isset($states[$_GET['state']]) ? $_GET['state'] : '';

// ── مرشِّحُ «المعلَّقة» — وجهةُ تنبيه لوحة المبيعات (UX-01 §8.7) ──────────────
// حالتان لا واحدة (`draft` + `review`) فلا يكفي `?state=`؛ ومن غيره يهبط
// التنبيهُ على قائمةٍ كاملةٍ لا يطابق عددُها عددَه — ورقمٌ لا يُنقر إلى مصدره
// ممنوع. والحالاتُ من `claim_pending_states()` نفسِها التي يعدّ بها العدّاد.
$filter_pending = isset($_GET['pending']) && $_GET['pending'] !== '' && $_GET['pending'] !== '0';

$where = "COALESCE(cl.is_deleted,0) = 0";
$params = array();
if ($filter_pending) {
    $pend = claim_pending_states();
    $where .= " AND cl.state IN ('" . implode("','", $pend) . "')";
} elseif ($filter_state !== '') { $where .= " AND cl.state = ?"; $params[] = $filter_state; }

$claims = array();
try {
    $claims = $gate->scopedQuery(
        array('scope' => array('cl' => 'claims'),
              'enrich' => array('c' => 'clients', 'p' => 'project')),
        "SELECT cl.*, c.client_name, p.name AS project_name
           FROM claims cl
           LEFT JOIN clients c ON c.id = cl.client_id
           LEFT JOIN project p ON p.id = cl.project_id
          WHERE {TENANT_SCOPE} AND {$where}
          ORDER BY cl.id DESC LIMIT 300", $params);
} catch (\Throwable $t) { error_log('claims list: ' . $t->getMessage()); }

// العقودُ المتاحة للتوليد — باسم مشروعها وعميلها (اشتقاقٌ لا إدخال)
$contract_rows = array();
try {
    $contract_rows = $gate->scopedQuery(
        array('scope' => array('c' => 'contracts'),
              'enrich' => array('p' => 'project', 'cl' => 'clients')),
        "SELECT c.id, c.price_currency_contract AS cur, p.name AS project_name, cl.client_name
           FROM contracts c
           LEFT JOIN project p ON p.id = c.project_id
           LEFT JOIN clients cl ON cl.id = p.client_id
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0) = 0
          ORDER BY c.id DESC", array());
} catch (\Throwable $t) { error_log('claims contracts: ' . $t->getMessage()); }

$tax_rows = array();
try { $tax_rows = $gate->select('fin_tax_codes', array('orderBy' => 'id ASC')); }
catch (\Throwable $t) { /* الضريبةُ اختيارية */ }

// ── الجاهزُ للفوترة ولم يُفوتر — وجهةُ التنبيه الأول (UX-01 §8.7) ──────────
// لوحُ عرضٍ محضٌ لا يكتب شيئًا: يجيب سؤالَ «أين الـN يومًا التي أخبرني بها
// التنبيه؟» بالعقد ومداه ومبلغه، وزرُّه يفتح نموذجَ التوليد مملوءًا بهما —
// فالتنبيهُ يقود إلى الفعل لا إلى قائمةٍ يبحث فيها المستخدم بنفسه.
$show_unbilled = isset($_GET['unbilled']) && $_GET['unbilled'] !== '' && $_GET['unbilled'] !== '0';
$unbilled_rows = $show_unbilled ? claim_unbilled_summary($gate) : array();
// العدُّ من الدالة لا بجمع الصفوف — الصفوفُ مجمَّعةٌ بالعملة أيضًا، فيومٌ
// بعملتين يظهر في صفّين. وهو الرقمُ نفسُه الذي عرضه تنبيهُ اللوحة بالضبط.
$unbilled_days = $show_unbilled ? claim_unbilled_days($gate) : 0;
$unbilled_contracts = array();
foreach ($unbilled_rows as $u) { $unbilled_contracts[intval($u['contract_id'])] = true; }

// بنودُ مستخلصٍ مفتوح
$open_id = isset($_GET['open']) ? intval($_GET['open']) : 0;
$open_claim = null; $open_lines = array();
if ($open_id > 0) {
    try {
        $open_claim = $gate->selectOne('claims', array('where' => array('id' => $open_id)));
        if ($open_claim) {
            // مواءمةُ PLAN-03 §5 (M-06): الأسطرُ ببند بيعها — والفاقدُه يُعلَن
            require_once __DIR__ . '/../app/Services/Revenue/ClaimDisputeService.php';
            $open_lines = \App\Services\Revenue\ClaimDisputeService::linesOf($gate, $open_id);
        }
    } catch (\Throwable $t) { error_log('claim open: ' . $t->getMessage()); }
}

$page_title = 'إيكوبيشن | المستخلصات';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'المستخلصات';
    $header_icon  = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'توليد مستخلص');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // TKT-15 · زر الإبلاغ السياقي — المستخلص والفاتورة (§2-⑦)
    require_once __DIR__ . '/../includes/report_button.php';
    ems_report_button(array('screen' => 'claims', 'contract_id' => $contract_id ?? null));
    ?>

    <p class="text-muted" style="margin:4px 2px 12px">
        <i class="fa fa-route"></i>
        أيامٌ <strong>حوّلتها المالية</strong> ← <strong>مستخلص</strong> ← رفعٌ للمالية ← فاتورةٌ ضريبية ← ذمّةُ العميل.
        اختر العقدَ والفترةَ فقط — الكمياتُ والأسعارُ والعميلُ تُشتق كلُّها.
        <br><i class="fa fa-shield-halved"></i>
        <strong>البنودُ من قيود الإيراد المعترَف بها وحدها</strong>: يومٌ لم يكتمل اعتمادُه الرباعي
        ولم تحوّله المالية <strong>لا يُستخلص</strong> — والمستخلصُ يفوتر ولا يُنشئ إيرادًا ثانيًا.
    </p>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info" style="margin-bottom:14px;font-weight:700">
            <?php echo clm_e($_GET['msg']); ?></div>
    <?php endif; ?>

    <?php if ($can_add): ?>
    <form id="procForm" action="claims.php" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-wand-magic-sparkles"></i> توليد مستخلص الفترة</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="action" value="generate">
            <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
            <div class="form-section">
                <div class="form-group"><label>العقد <span class="mnt-req-hint">(العميلُ يُشتق منه)</span></label>
                    <select name="contract_id" required>
                        <option value="">— اختر العقد —</option>
                        <?php foreach ($contract_rows as $c): ?>
                            <option value="<?php echo intval($c['id']); ?>">
                                عقد #<?php echo intval($c['id']); ?>
                                <?php echo $c['project_name'] ? ' — ' . clm_e($c['project_name']) : ''; ?>
                                <?php echo $c['client_name'] ? ' · ' . clm_e($c['client_name']) : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>من تاريخ</label>
                    <input type="date" name="period_from" required></div>
                <div class="form-group"><label>إلى تاريخ</label>
                    <input type="date" name="period_to" required></div>
            </div>
            <div style="margin-top:10px">
                <button type="submit" class="btn-save"><i class="fas fa-bolt"></i> ولّد المستخلص</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <?php if ($show_unbilled): ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header"><h5><i class="fas fa-hourglass-half"></i>
            جاهزٌ للفوترة ولم يُفوتر — <?php echo intval($unbilled_days); ?> يومَ عملٍ
            في <?php echo count($unbilled_contracts); ?> عقد</h5></div>
        <div class="card-body table-container">
            <?php if (empty($unbilled_rows)): ?>
                <p class="text-muted" style="margin:6px 2px">
                    <i class="fa fa-circle-check"></i>
                    لا يومَ عملٍ جاهزًا للفوترة خارجَ المستخلصات — كلُّ ما حوّلته الماليةُ مُطالَبٌ به.
                </p>
            <?php else: ?>
            <p class="text-muted" style="margin:2px 2px 10px">
                <i class="fa fa-shield-halved"></i>
                أيامٌ <strong>حوّلتها المالية</strong> ولم يشملها مستخلصٌ قائم. اختر العقدَ لتُولّد مستخلصَ مداه.
                <br><i class="fa fa-coins"></i>
                <strong>صفٌّ لكل عملة</strong>: العقدُ الواحد قد تكون أيامُه بعملتين، ولا تُجمعان في رقمٍ
                واحدٍ ما لم يُدخَل سعرُ صرفهما.
            </p>
            <table class="display" style="width:100%">
                <thead><tr><th>العقد</th><th>المشروع</th><th>العميل</th><th>العملة</th><th>الأيام</th>
                    <th>المدى</th><th>القيمة</th><th></th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                    <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                    <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($unbilled_rows as $u): ?>
                    <tr>
                        <td>عقد #<?php echo intval($u['contract_id']); ?></td>
                        <td><?php echo clm_e($u['project_name']) ?: '—'; ?></td>
                        <td><?php echo clm_e($u['client_name']) ?: '—'; ?></td>
                        <td><?php echo clm_e($u['currency']) ?: '—'; ?></td>
                        <td><strong><?php echo intval($u['days']); ?></strong></td>
                        <td><?php echo clm_e($u['first_date']); ?> → <?php echo clm_e($u['last_date']); ?></td>
                        <td><strong><?php echo clm_num($u['amount']); ?></strong></td>
                        <td>
                            <?php if ($can_add): ?>
                            <a class="btn btn-sm ems-unbilled-fill"
                               href="#procForm"
                               data-contract="<?php echo intval($u['contract_id']); ?>"
                               data-from="<?php echo clm_e($u['first_date']); ?>"
                               data-to="<?php echo clm_e($u['last_date']); ?>">
                               <i class="fas fa-wand-magic-sparkles"></i> ولّد مداه</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <a href="claims.php" class="btn btn-sm" style="margin-top:10px">إغلاق</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($open_claim): ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header"><h5><i class="fas fa-list-ol"></i>
            بنود المستخلص <?php echo clm_e($open_claim['claim_no']); ?>
            — <?php echo clm_e($open_claim['period_from']); ?> إلى <?php echo clm_e($open_claim['period_to']); ?></h5></div>
        <div class="card-body table-container">
            <table class="display" style="width:100%">
                <thead><tr><th>التاريخ</th><th>المعدة</th><th>الوحدة</th><th>الكمية</th>
                    <th>سعر الوحدة</th><th>القيمة</th><th>الأصل</th><th>بند البيع</th>
                    <th>النزاع (§3-⑤)</th></tr></thead>
                <tbody>
                <?php foreach ($open_lines as $l):
                    $dstate = isset($l['dispute_state']) ? (string) $l['dispute_state'] : 'none';
                ?>
                    <tr<?php echo intval($l['dispute_flag']) === 1 ? ' style="opacity:.6"' : ''; ?>>
                        <td><?php echo clm_e($l['work_date']); ?></td>
                        <td><?php echo clm_e($l['equipment_ref']); ?></td>
                        <td><?php echo clm_e($l['unit_type']); ?></td>
                        <td><?php echo clm_num($l['qty']); ?></td>
                        <td><?php echo clm_num($l['unit_price']); ?></td>
                        <td><strong><?php echo clm_num($l['amount']); ?></strong></td>
                        <td><span class="text-muted">دوام #<?php echo intval($l['source_ref']); ?></span></td>
                        <td><?php if (isset($l['contract_line_id']) && intval($l['contract_line_id']) > 0): ?>
                                بند <?php echo intval($l['sale_line_no']); ?>
                                <small>(<?php echo clm_e($l['sale_line_model']); ?>)</small>
                            <?php else: ?>
                                <span class="badge badge-warning" title="النزاعُ يُروى بلا بندٍ يُنسَب إليه — صِل السطرَ من شاشة الربط 178">⚠ غيرُ موصول</span>
                            <?php endif; ?></td>
                        <td>
                        <?php if ($dstate === 'open'): ?>
                            <span class="badge badge-warning">متنازَعٌ عليه</span>
                            <small><?php echo clm_e($l['dispute_reason']); ?>
                                · مستند <?php echo clm_e($l['dispute_doc_ref']); ?></small>
                            <?php if ($can_approve): ?>
                            <form method="post" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                <input type="hidden" name="action" value="dispute_resolve">
                                <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                                <select name="resolution">
                                    <option value="rejected">رُدَّ الاعتراض (البند يعود)</option>
                                    <option value="upheld">أُقرَّ الاعتراض (البند يسقط)</option>
                                </select>
                                <input type="text" name="resolution_note" placeholder="سببُ الحسم" required>
                                <button type="submit" class="btn-save">احسِم</button>
                            </form>
                            <?php endif; ?>
                        <?php elseif ($dstate === 'resolved'): ?>
                            <span class="badge <?php echo (string)$l['resolution'] === 'upheld'
                                ? 'badge-danger' : 'badge-success'; ?>">
                                <?php echo (string)$l['resolution'] === 'upheld'
                                    ? 'أُقرَّ — البندُ ساقط' : 'رُدَّ — البندُ محتسَب'; ?></span>
                            <small><?php echo clm_e($l['resolution_note']); ?></small>
                        <?php elseif ($can_add): ?>
                            <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
                                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                <input type="hidden" name="action" value="dispute_raise">
                                <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                                <input type="text" name="dispute_reason" placeholder="سببُ الاعتراض" required>
                                <input type="text" name="dispute_doc_ref" placeholder="مستندُه" required>
                                <button type="submit" class="btn-save">ارفع نزاعًا</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:10px"><strong>الإجمالي:</strong>
                <?php echo clm_num($open_claim['gross_amount']); ?>
                <?php echo clm_e($open_claim['currency']); ?>
                · <strong>الصافي:</strong> <?php echo clm_num($open_claim['net_amount']); ?>
                <?php if ((float)$open_claim['tax_amount'] > 0): ?>
                    · <strong>الضريبة:</strong> <?php echo clm_num($open_claim['tax_amount']); ?>
                <?php endif; ?>
            </p>
            <a href="claims.php" class="btn btn-sm">إغلاق</a>
        </div>
    </div>

    <?php
    // ── M-01: رصيدُ الدفعة المقدَّمة لعقد هذا المستخلص ────────────────────────
    // يُعرض هنا لأن **الاستقطاع يقع في هذا المستخلص**: فالرقمُ الذي خُصم يُقرأ
    // بجانب رصيده لا في شاشةٍ أخرى. والقبضُ يُسجَّل من هنا لأن الماليةَ التي
    // تُجيز هي التي تقبض.
    $adv_cid = intval($open_claim['contract_id']);
    $adv = advance_balance($gate, $adv_cid);
    $adv_pct = 0.0;
    try {
        $adv_c = $gate->selectOne('contracts', array(
            'columns' => array('advance_recovery_pct'), 'where' => array('id' => $adv_cid)));
        if ($adv_c) { $adv_pct = (float) $adv_c['advance_recovery_pct']; }
    } catch (\Throwable $t) { /* النسبةُ زينةُ عرض */ }
    ?>
    <?php if ($adv_pct > 0 || $adv['received'] > 0 || $adv['recovered'] > 0): ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header"><h5><i class="fas fa-hand-holding-dollar"></i>
            الدفعةُ المقدَّمة — عقد #<?php echo $adv_cid; ?></h5></div>
        <div class="card-body">
            <p class="text-muted" style="margin:0 0 8px;line-height:1.9">
                <strong>المقبوض:</strong> <?php echo clm_num($adv['received']); ?>
                · <strong>المستهلَك:</strong> <?php echo clm_num($adv['recovered']); ?>
                · <strong>المتبقي:</strong>
                <strong style="color:<?php echo ($adv['balance'] < 0) ? '#b91c1c' : '#15803d'; ?>">
                    <?php echo clm_num($adv['balance']); ?></strong>
                <?php if ($adv_pct > 0): ?> · نسبةُ الاسترداد <?php echo clm_num($adv_pct); ?>٪<?php endif; ?>
                <br>
                <?php if ($adv['received'] <= 0 && $adv_pct > 0): ?>
                    <i class="fa fa-triangle-exclamation"></i>
                    <strong>للعقد نسبةُ استردادٍ ولا دفعةَ مقبوضةً مسجَّلة</strong> —
                    فلا يُستقطع شيءٌ (لا استردادَ لدَينٍ لم يُقرَض). سجّل سندَ القبض ليبدأ الاستقطاع.
                <?php elseif ($adv['balance'] <= 0 && $adv['received'] > 0): ?>
                    <i class="fa fa-circle-check"></i>
                    استُردّت بالكامل — <strong>ولا استقطاعَ في هذا المستخلص ولا فيما بعده</strong>.
                <?php elseif ($adv['balance'] > 0): ?>
                    <i class="fa fa-circle-info"></i>
                    الاستقطاعُ في كل فترةٍ = الأقلُّ من (النسبة × أساس الفترة) و<strong>المتبقي</strong>.
                <?php endif; ?>
            </p>
            <?php if ($can_approve): ?>
            <form action="claims.php" method="post" class="allforms allforms-visible"
                  style="box-shadow:none;padding:0;margin-top:8px">
                <input type="hidden" name="action" value="advance_record">
                <input type="hidden" name="contract_id" value="<?php echo $adv_cid; ?>">
                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label>المبلغ المقبوض *
                        <span class="mnt-req-hint">(من سند القبض — لا يُشتق من نسبة)</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" required></div>
                    <div class="form-group"><label>تاريخ القبض *</label>
                        <input type="date" name="received_date" required></div>
                    <div class="form-group"><label>مرجعُ السند *</label>
                        <input type="text" name="doc_ref" maxlength="120" required></div>
                    <div class="form-group"><label>ملاحظة</label>
                        <input type="text" name="note" maxlength="255"></div>
                </div></div>
                <div style="margin-top:10px">
                    <button type="submit" class="btn-save"><i class="fas fa-receipt"></i> سجّل قبضَ الدفعة</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ── M-02: إشعاراتُ هذا المستخلص — **بجانبه لا داخله** ────────────────────
    // الفاتورةُ الصادرة لا تُعدَّل، فالتصحيحُ صفوفٌ مستقلةٌ تشير إليها. والصافي
    // المعروضُ **مشتقٌّ** لا مخزَّن: الفاتورة − الدائن + المدين.
    $open_notes = cdnote_for_claim($gate, $open_id);
    $open_net   = cdnote_claim_net($gate, $open_claim);
    $note_states = cdnote_states();
    $note_kinds  = cdnote_kinds();
    $invoiced_states = array('invoiced', 'collected');
    ?>
    <div class="card" style="margin-bottom:14px">
        <div class="card-header"><h5><i class="fas fa-file-circle-exclamation"></i>
            الإشعاراتُ الدائنة/المدينة على <?php echo clm_e($open_claim['claim_no']); ?></h5></div>
        <div class="card-body table-container">
            <p class="text-muted" style="margin:2px 2px 10px">
                <i class="fa fa-shield-halved"></i>
                <strong>الفاتورةُ الصادرة لا تُعدَّل</strong> — تُصحَّح بإشعارٍ يشير إليها
                ويحرّك ذمّةَ العميل بمقداره. والأصلُ يبقى كما صدر.
                <br><i class="fa fa-calculator"></i>
                <strong>صافي المطالبة:</strong>
                <?php echo clm_num($open_net['invoiced']); ?>
                − دائن <?php echo clm_num($open_net['credit']); ?>
                + مدين <?php echo clm_num($open_net['debit']); ?>
                = <strong><?php echo clm_num($open_net['net']); ?></strong>
                <?php echo clm_e($open_claim['currency']); ?>
            </p>

            <?php if ($open_notes): ?>
            <table class="display" style="width:100%">
                <thead><tr><th>الرقم</th><th>الاتجاه</th><th>المبلغ</th><th>السبب</th>
                    <th>المستند</th><th>السطر</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($open_notes as $n): ?>
                    <tr<?php echo ((string) $n['state'] === 'cancelled') ? ' style="opacity:.55"' : ''; ?>>
                        <td><code><?php echo clm_e($n['note_no']); ?></code></td>
                        <td><?php echo ((string) $n['note_kind'] === 'credit')
                                ? '<span class="badge badge-success">دائن −</span>'
                                : '<span class="badge badge-warning">مدين +</span>'; ?></td>
                        <td><strong><?php echo clm_num($n['amount']); ?></strong>
                            <?php echo clm_e($n['currency']); ?></td>
                        <td><?php echo clm_e($n['reason']); ?></td>
                        <td><?php echo clm_e($n['doc_ref']); ?></td>
                        <td><?php echo $n['claim_line_id'] !== null
                                ? ('#' . intval($n['claim_line_id'])) : 'المستخلص كلُّه'; ?></td>
                        <td><?php echo clm_e($note_states[$n['state']] ?? $n['state']); ?></td>
                        <td style="white-space:nowrap">
                            <?php if ($can_add && (string) $n['state'] === 'draft'): ?>
                            <form action="claims.php" method="post" style="display:inline">
                                <input type="hidden" name="action" value="note_submit">
                                <input type="hidden" name="note_id" value="<?php echo intval($n['id']); ?>">
                                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                <button type="submit" class="action-btn edit" title="رفعٌ للمالية">
                                    <i class="fa fa-paper-plane"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($can_approve && (string) $n['state'] === 'review'): ?>
                            <form action="claims.php" method="post" style="display:inline"
                                  onsubmit="return confirm('إجازةُ الإشعار تحرّك ذمّةَ العميل بمقداره. متابعة؟');">
                                <input type="hidden" name="action" value="note_approve">
                                <input type="hidden" name="note_id" value="<?php echo intval($n['id']); ?>">
                                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                <button type="submit" class="action-btn edit" title="إجازة">
                                    <i class="fa fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($can_add && in_array((string) $n['state'], array('draft', 'review'), true)): ?>
                            <form action="claims.php" method="post" style="display:inline">
                                <input type="hidden" name="action" value="note_cancel">
                                <input type="hidden" name="note_id" value="<?php echo intval($n['id']); ?>">
                                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                <button type="submit" class="action-btn delete" title="إلغاء">
                                    <i class="fa fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="text-muted" style="margin:6px 2px">لا إشعاراتٍ على هذا المستخلص.</p>
            <?php endif; ?>

            <?php if ($can_add && in_array((string) $open_claim['state'], $invoiced_states, true)): ?>
            <form action="claims.php" method="post" class="allforms allforms-visible"
                  style="box-shadow:none;padding:0;margin-top:12px">
                <input type="hidden" name="action" value="note_create">
                <input type="hidden" name="claim_id" value="<?php echo $open_id; ?>">
                <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label>الاتجاه *</label>
                        <select name="note_kind" required>
                            <?php foreach ($note_kinds as $k => $lbl): ?>
                                <option value="<?php echo clm_e($k); ?>"><?php echo clm_e($lbl); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>المبلغ *
                        <span class="mnt-req-hint">(موجبٌ دائمًا — الاتجاهُ يحمل الإشارة)</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" required></div>
                    <div class="form-group"><label>السطر <span class="mnt-req-hint">(اختياري)</span></label>
                        <select name="claim_line_id">
                            <option value="">المستخلص كلُّه</option>
                            <?php foreach ($open_lines as $l): ?>
                                <option value="<?php echo intval($l['id']); ?>">
                                    #<?php echo intval($l['id']); ?> — <?php echo clm_e($l['work_date']); ?>
                                    · <?php echo clm_num($l['amount']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>السبب *</label>
                        <input type="text" name="reason" maxlength="255" required
                               placeholder="لماذا يُصحَّح؟"></div>
                    <div class="form-group"><label>مرجعُ المستند *</label>
                        <input type="text" name="doc_ref" maxlength="120" required
                               placeholder="رقمُ المستند المؤيِّد"></div>
                </div></div>
                <div style="margin-top:10px">
                    <button type="submit" class="btn-save"><i class="fas fa-file-circle-plus"></i> أنشئ الإشعار</button>
                </div>
            </form>
            <?php elseif ($can_add): ?>
                <p class="text-muted" style="margin:10px 2px">
                    <i class="fa fa-circle-info"></i>
                    لا إشعارَ إلا على مستخلصٍ صدرت فاتورتُه — وما لم يُفوتر بعدُ يُصحَّح في موضعه.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-table"></i> المستخلصات</h5></div>
        <div class="card-body table-container">
            <div style="margin-bottom:10px">
                <a href="claims.php" class="action-btn<?php echo ($filter_state === '' && !$filter_pending) ? ' active' : ''; ?>">الكل</a>
                <a href="claims.php?pending=1"
                   class="action-btn<?php echo $filter_pending ? ' active' : ''; ?>"
                   title="مسودةٌ عندك أو مرفوعةٌ تنتظر إجازة المالية">معلَّقة</a>
                <?php foreach ($states as $k => $lbl): ?>
                    <a href="claims.php?state=<?php echo clm_e($k); ?>"
                       class="action-btn<?php echo (!$filter_pending && $filter_state === $k) ? ' active' : ''; ?>"><?php echo clm_e($lbl); ?></a>
                <?php endforeach; ?>
            </div>
            <table class="display" style="width:100%">
                <thead><tr>
                    <th>الرقم</th><th>العميل</th><th>المشروع</th><th>الفترة</th>
                    <th>الإجمالي</th><th>الصافي</th><th>الضريبة</th><th>الفاتورة</th><th>الحالة</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($claims as $c): ?>
                    <tr>
                        <td><strong><?php echo clm_e($c['claim_no']); ?></strong></td>
                        <td><?php echo clm_e($c['client_name'] ?: '—'); ?></td>
                        <td><?php echo clm_e($c['project_name'] ?: '—'); ?></td>
                        <td><?php echo clm_e($c['period_from']); ?> → <?php echo clm_e($c['period_to']); ?></td>
                        <td><?php echo clm_num($c['gross_amount']); ?></td>
                        <td><strong><?php echo clm_num($c['net_amount']); ?></strong>
                            <span class="text-muted"><?php echo clm_e($c['currency']); ?></span></td>
                        <td><?php echo clm_num($c['tax_amount']); ?></td>
                        <td><?php echo $c['invoice_no'] ? clm_e($c['invoice_no']) : '—'; ?></td>
                        <td><?php echo clm_e($states[$c['state']] ?? $c['state']); ?></td>
                        <td>
                            <div class="action-btns" style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="claims.php?open=<?php echo intval($c['id']); ?>" class="action-btn view" title="البنود">
                                    <i class="fa fa-eye"></i></a>
                                <?php if ($can_add && (string)$c['state'] === 'draft'): ?>
                                    <form action="claims.php" method="post" style="display:inline"
                                          onsubmit="return confirm('رفعُ المستخلص للمالية يقفل تعديلَه عندك. متابعة؟');">
                                        <input type="hidden" name="action" value="submit">
                                        <input type="hidden" name="id" value="<?php echo intval($c['id']); ?>">
                                        <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                        <button type="submit" class="action-btn edit" title="رفعٌ للمالية">
                                            <i class="fa fa-paper-plane"></i></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_approve && (string)$c['state'] === 'review'): ?>
                                    <form action="claims.php" method="post" style="display:inline"
                                          onsubmit="return confirm('إجازةُ المستخلص تولّد فاتورةً ضريبيةً وتفتح ذمّةً على العميل. متابعة؟');">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?php echo intval($c['id']); ?>">
                                        <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                        <?php if ($tax_rows): ?>
                                        <select name="tax_code" style="width:auto;display:inline-block">
                                            <option value="">بلا ضريبة</option>
                                            <?php foreach ($tax_rows as $t): ?>
                                                <option value="<?php echo clm_e($t['code']); ?>"><?php echo clm_e($t['code']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <button type="submit" class="action-btn edit" title="إجازةٌ مالية"><i class="fa fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_add && in_array((string)$c['state'], array('draft', 'review'), true)): ?>
                                    <form action="claims.php" method="post" style="display:inline"
                                          onsubmit="return confirm('إلغاءُ المستخلص يردّ وقائعَه للاستخلاص. متابعة؟');">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id" value="<?php echo intval($c['id']); ?>">
                                        <input type="hidden" name="clm_csrf" value="<?php echo clm_e($clm_csrf); ?>">
                                        <button type="submit" class="action-btn delete" title="إلغاء"><i class="fa fa-ban"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$claims): ?>
                <p class="text-muted" style="margin-top:12px">
                    لا مستخلصاتٍ بعد — ابدأ بتوليد مستخلصِ فترةٍ لعقدٍ من الزرّ أعلاه.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// فتحُ فورم التوليد وطيُّه — بلا jQuery ولا ready، فلا يُسقطه عطبُ مُعالجٍ آخر
(function () {
    var btn  = document.getElementById('toggleForm');
    var form = document.getElementById('procForm');
    if (!btn || !form) { return; }
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        form.classList.toggle('allforms-visible');
        if (form.classList.contains('allforms-visible')) {
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
})();

// «ولّد مداه» — يملأ نموذجَ التوليد بالعقد ومداه من لوح الجاهز للفوترة.
// بلا jQuery ولا ready للسبب نفسِه أعلاه: مُعالجُ ready واحدٌ يعطب يُسقط الباقي.
(function () {
    var form = document.getElementById('procForm');
    if (!form) { return; }
    var links = document.querySelectorAll('.ems-unbilled-fill');
    for (var i = 0; i < links.length; i++) {
        links[i].addEventListener('click', function (e) {
            e.preventDefault();
            var c = form.querySelector('[name="contract_id"]');
            var f = form.querySelector('[name="period_from"]');
            var t = form.querySelector('[name="period_to"]');
            if (c) { c.value = this.getAttribute('data-contract'); }
            if (f) { f.value = this.getAttribute('data-from'); }
            if (t) { t.value = this.getAttribute('data-to'); }
            form.classList.add('allforms-visible');
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }
})();
</script>
</body>
</html>

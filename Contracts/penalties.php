<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/penalties.php — احتسابُ الجزاءات والحوافز (CON-02 §6 · §7-⑤ · ق-17)
 * ───────────────────────────────────────────────────────────────────────────
 * **شاشةٌ مستقلةٌ بجوار `claims.php`** — والمالية تدخلها للإجازة بمنحةٍ كما تدخل
 * الموازنة (ق-17).
 *
 * **الملكية (ق-13):** النظامُ يحتسب · المبيعات (12) تراجع · مديرُ المالية (19)
 * يُجيز **ويملك الإعفاء بسببٍ إلزاميٍّ موثَّق**. ولا اعتمادَ ذاتٍ: مَن راجع لا يُجيز.
 *
 * **لا خصمٌ صامت (§6):** كلُّ بندٍ يظهر باسمه ومعادلتِه ومرجعِه — الأساسُ
 * والمعدَّلُ والسقفُ والمبلغُ قبله وبعده، فيُرى **أين قُصَّ الرقم ولماذا**.
 *
 * **ق-7:** الإجازةُ تنشر قيدًا في الدفتر عبر `EventPublisher`، ثم يقرؤه
 * المستخلصُ من `fin_event_links` — لا بندَ مستخلصٍ معزولٍ بلا قيد.
 *
 * ⚠️ CSRF مركزيٌّ تلقائيّ — لا رموزَ يدويةً هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/PenaltyService.php';
require_once __DIR__ . '/claim_helpers.php';

use App\Services\Contract\PenaltyService as PEN;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/penalties.php';
$can_view = $can_assess = $can_approve = false;
if ($is_super_admin) {
    $can_view = $can_assess = $can_approve = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view    = (intval($row['can_view']) === 1);
        $can_assess  = (intval($row['can_add'])  === 1);  // الاحتسابُ والمراجعة — المبيعات
        $can_approve = (intval($row['can_edit']) === 1);  // الإجازةُ والإعفاء — المالية
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الجزاءات ❌', 'GOV-PERM-403', ''); exit();
}

$gate = ems_tenant_db();
if (!function_exists('pen_e')) { function pen_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('pen_back')) {
    function pen_back($msg, $c, $f, $t) {
        ems_gov_redirect('Location: penalties.php?contract=' . intval($c) . '&from=' . urlencode($f)
               . '&to=' . urlencode($t) . '&msg=' . rawurlencode($msg));
        exit();
    }
}

$sel = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-01');
$to   = (isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   ? $_GET['to']   : date('Y-m-t');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pen_action'])) {
    $act = strval($_POST['pen_action']);
    $c = intval($_POST['contract'] ?? 0);
    $f = strval($_POST['from'] ?? $from); $t = strval($_POST['to'] ?? $to);

    if ($act === 'assess') {
        if (!$can_assess) { pen_back('الاحتسابُ صلاحيةُ المبيعات ❌', $c, $f, $t); }
        $r = PEN::assess($conn, $gate, $company_id, $c, $f, $t, $uid);
        $m = $r['ok'] ? ('احتُسب ' . $r['computed'] . ' بندًا') : 'تعذّر الاحتساب';
        if (!empty($r['skipped'])) { $m .= ' · تُرك: ' . count($r['skipped']) . ' (انظر التعليل أدناه)'; }
        pen_back($m . ($r['ok'] ? ' ✅' : ' ❌'), $c, $f, $t);

    } elseif ($act === 'review') {
        if (!$can_assess) { pen_back('المراجعةُ صلاحيةُ المبيعات ❌', $c, $f, $t); }
        $r = PEN::review($gate, $company_id, intval($_POST['aid'] ?? 0), $uid);
        pen_back($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌'), $c, $f, $t);

    } elseif ($act === 'approve') {
        if (!$can_approve) { pen_back('الإجازةُ صلاحيةُ مدير الإدارة المالية (ق-13) ❌', $c, $f, $t); }
        $r = PEN::approve($conn, $gate, $company_id, intval($_POST['aid'] ?? 0), $uid);
        pen_back($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌'), $c, $f, $t);

    } elseif ($act === 'waive') {
        if (!$can_approve) { pen_back('الإعفاءُ صلاحيةُ مدير الإدارة المالية (ق-13) ❌', $c, $f, $t); }
        /* ══ INJ-0028 · «إعفاءٌ فوق السقف يُرفض ويُصعَّد» ══════════════════════════
             الإعفاءُ **يُسقط استحقاقًا قائمًا** — فهو قرارٌ ماليٌّ بقيمةِ الغرامة،
             ويخضع لسقفِ تفويضِ من يوقّعه كما يخضع الصرفُ. وكان يمرُّ بمجرّدِ
             `can_edit` مهما بلغت القيمة.
             ◆ وقيمةُ الغرامةِ تُقرأ من صفِّها لا من النموذج. */
        $__aid = intval($_POST['aid'] ?? 0);
        $__amt = 0.0;
        $__q = $conn->prepare('SELECT amount FROM contract_penalty_assessments
                                WHERE id = ? AND company_id = ? LIMIT 1');
        if ($__q) {
            $__q->bind_param('ii', $__aid, $company_id);
            $__q->execute();
            $__x = $__q->get_result()->fetch_row();
            $__q->close();
            if ($__x) { $__amt = (float) $__x[0]; }
        }
        require_once __DIR__ . '/../app/Core/AuthorityGuard.php';
        $__ent = \App\Core\AuthorityGuard::tenantEntity($conn, $company_id);
        if ($__ent && $__amt > 0) {
            $__sig = \App\Core\AuthorityGuard::sign($conn, array(
                'document_type' => 'penalty_waiver', 'document_id' => $__aid, 'step' => 'waive',
                'person_id' => (int) $uid, 'company_id' => (int) $company_id,
                'entity_id' => $__ent, 'amount' => $__amt,
            ));
            if (empty($__sig['ok']) && (int) $__sig['code'] === 409) {
                $__esc = $conn->prepare("INSERT INTO exec_approvals
                    (company_id, request_no, received_date, doc_type, document, requesting_dept,
                     raise_reason, amount, currency, status, source_kind, created_by, created_by_name)
                    VALUES (?, ?, CURDATE(), 'إعفاء غرامة', ?, 'المالية والخزينة',
                            ?, ?, 'USD', 'قيد المراجعة', 'escalation', ?, ?)");
                if ($__esc) {
                    $__rq = 'ESC-PEN-' . $__aid . '-' . date('ymdHis');
                    $__doc = 'إعفاءُ غرامةٍ #' . $__aid;
                    $__why = 'تجاوزُ سقفِ التفويضِ للإعفاء — ' . $__sig['reason'];
                    $__amtS = (string) $__amt;
                    $__nm = 'طالبُ الإعفاء #' . (int) $uid;
                    $__uidI = (int) $uid;
                    $__esc->bind_param('sssssis', $__rq, $__doc, $__why, $__amtS, $__uidI, $__nm);
                    $__esc->execute();
                    $__esc->close();
                }
                pen_back('PEN-CAP-409: ' . $__sig['reason'] . ' — **رُفع الإعفاءُ لصندوقِ الاعتمادِ الأعلى** ⤴',
                    $c, $f, $t);
            }
        }
        $r = PEN::waive($conn, $gate, $company_id, $__aid,
                        strval($_POST['reason'] ?? ''), $uid);
        pen_back($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌'), $c, $f, $t);

    } elseif ($act === 'release_retention') {
        // ق-20: قرارٌ يدويٌّ من الدور 19 بعد انتهاء العقد. والحارسُ في الخدمة
        // أيضًا (دورًا وتاريخًا ورصيدًا وعطالة) — فالمنحةُ هنا بابٌ لا سياج.
        if (!$can_approve) { pen_back('ردُّ الضمان صلاحيةُ مدير الإدارة المالية (ق-20) ❌', $c, $f, $t); }
        $r = claim_retention_release($conn, $c, $uid, $current_role);
        pen_back($r['reason'] . ($r['status'] === 'released' ? ' ✅' : ' ❌'), $c, $f, $t);
    }
    pen_back('إجراءٌ غير معروف ❌', $c, $f, $t);
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array(
        'scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project'),
    ), "SELECT c.id, p.name AS project_name FROM contracts c
        LEFT JOIN project p ON p.id = c.project_id
        WHERE {TENANT_SCOPE} AND c.is_deleted = 0 ORDER BY c.id");
} catch (\Throwable $t2) { $contracts = array(); }

$rows = array(); $rules = array(); $held = 0.0;
if ($sel > 0) {
    try {
        $rows = $gate->scopedQuery(array(
            'scope'  => array('a' => 'contract_penalty_assessments'),
            'enrich' => array('ur' => 'users', 'ua' => 'users'),
        ), "SELECT a.*, ur.name AS reviewer, ua.name AS approver
              FROM contract_penalty_assessments a
              LEFT JOIN users ur ON ur.id = a.reviewed_by
              LEFT JOIN users ua ON ua.id = a.approved_by
             WHERE {TENANT_SCOPE} AND a.is_deleted = 0 AND a.client_contract_id = ?
               AND a.period_from >= ? AND a.period_to <= ?
             ORDER BY a.kind, a.id", array($sel, $from, $to));
    } catch (\Throwable $t2) { $rows = array(); error_log('penalties list: ' . $t2->getMessage()); }
    try {
        $rules = $gate->scopedQuery(array('scope' => array('r' => 'contract_penalty_rules')),
            "SELECT r.id, r.rule_kind, r.rate, r.min_readiness_pct, r.fixed_amount,
                    r.cap_percent, r.periodicity, r.valid_from, r.valid_to
               FROM contract_penalty_rules r
              WHERE {TENANT_SCOPE} AND r.is_deleted = 0 AND r.client_contract_id = ?
              ORDER BY r.id", array($sel));
    } catch (\Throwable $t2) { $rules = array(); }
    try { $held = claim_retention_balance($gate, $sel); } catch (\Throwable $t2) { $held = 0.0; }
}

// ── ق-20: شرطُ ردّ الضمان — **بعد تجاوز actual_end حصرًا** وبيد الدور 19 ──
// نفسُ شروط `claim_retention_release`، تُقرأ هنا للعرض لا للحكم: الخدمةُ هي
// الحارس، والشاشةُ تشرح **لماذا** لا يظهر الزرُّ بدل أن تخفيه صامتةً.
$ret_end = ''; $ret_ended = false; $ret_released = false;
if ($sel > 0) {
    try {
        $cr = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
            "SELECT c.actual_end FROM contracts c
              WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0) = 0 AND c.id = ? LIMIT 1", array($sel));
        if ($cr) { $ret_end = (string) $cr[0]['actual_end']; }
        $ret_ended = ($ret_end !== '' && $ret_end !== '0000-00-00'
                      && strtotime($ret_end) < strtotime(date('Y-m-d')));
        $rl = $gate->scopedQuery(array(
            'scope'  => array('cl' => 'claim_lines'), 'enrich' => array('c' => 'claims'),
        ), "SELECT cl.claim_id FROM claim_lines cl LEFT JOIN claims c ON c.id = cl.claim_id
            WHERE {TENANT_SCOPE} AND cl.source_kind = 'retention_release' AND c.contract_id = ? LIMIT 1",
            array($sel));
        $ret_released = !empty($rl);
    } catch (\Throwable $t2) { }
}

// شاراتُ العقد (ق-21) — اشتقاقٌ حيٌّ عند فتح الشاشة، لا عمودَ حالةٍ يبيت
require_once __DIR__ . '/../includes/contract_badges.php';
$badges = array('breaking' => array(), 'ending' => null);
if ($sel > 0) {
    try { $badges = contract_capacity_badges($gate, $sel); } catch (\Throwable $t2) { }
}

$KIND = array('penalty' => 'غرامة', 'incentive' => 'حافز', 'min_guarantee' => 'حدٌّ أدنى مضمون');
$RK = array('shortfall_pct' => 'غرامةُ العجز', 'readiness_min' => 'غرامةُ الجاهزية',
            'bonus_qty_pct' => 'حافزُ تجاوز الكمية', 'bonus_fixed' => 'حافزٌ مقطوع (جودة/سلامة)',
            'min_guaranteed' => 'فارقُ الحد الأدنى');
$STATE = array('computed' => 'محتسَب', 'reviewed' => 'روجِع', 'approved' => 'مُجاز',
               'waived' => 'مُعفًى', 'posted' => 'منشورٌ في الدفتر');
function pen_n($v) { return ($v === null) ? '—' : number_format((float) $v, 2); }

$page_title = 'احتساب الجزاءات والحوافز';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'penalties';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main pen-main ems-unified-page-shell">
    <?php
    $header_title = 'احتساب الجزاءات والحوافز';
    $header_icon = 'fa fa-gavel';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا احتساباتِ جزاءاتٍ أو حوافزَ في هذه الفترة', 'اختر عقدًا وفترةً ثم اضغط «احتسِب الفترة» — ولا احتسابَ بلا قواعدَ مسجَّلةٍ على العقد');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('contract', ''); ?>

    <?php if (!empty($_GET['msg'])): $isS = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isS ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isS ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo pen_e($_GET['msg']); ?></div>
    <?php endif; ?>

    <div class="pen-rule">
        <i class="fas fa-scale-balanced"></i>
        <div>
            <strong>النظامُ يحتسب · المبيعاتُ تراجع · المالية تُجيز.</strong>
            والإعفاءُ بيد المالية <em>بسببٍ إلزاميٍّ موثَّق</em>. ولا خصمٌ صامت: كلُّ بندٍ يظهر
            بأساسه ومعدَّله وسقفه — والمبلغِ قبل السقف وبعده.
            <br><strong>والغرامةُ اتجاهٌ واحد:</strong> تُحتسب حين نكون <em>نحن</em> المقصّرين؛
            وتقصيرُ العميل تعالجه مصفوفةُ الالتزامات فيصير استعدادًا مفوترًا.
        </div>
    </div>

    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> العقدُ والفترة</div>
        <div class="filter-body">
            <form method="get" action="" class="pen-filter">
                <div class="filter-field">
                    <label for="emsf_77_2f103"><i class="fa fa-file-contract"></i> العقد</label>
                    <select name="contract" class="form-control" id="emsf_77_2f103">
                        <option value="">-- اختر --</option>
                        <?php foreach ($contracts as $c): ?>
                            <option value="<?php echo intval($c['id']); ?>" <?php echo intval($c['id']) === $sel ? 'selected' : ''; ?>>
                                عقد #<?php echo intval($c['id']); ?> — <?php echo pen_e($c['project_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field"><label for="emsf_78_9c216"><i class="fa fa-calendar"></i> من</label>
                    <input type="date" name="from" class="form-control" id="emsf_78_9c216" value="<?php echo pen_e($from); ?>"></div>
                <div class="filter-field"><label for="emsf_79_cd642"><i class="fa fa-calendar"></i> إلى</label>
                    <input type="date" name="to" class="form-control" id="emsf_79_cd642" value="<?php echo pen_e($to); ?>"></div>
                <div class="filter-actions"><button type="submit" class="btn-primary"><i class="fa fa-search"></i> عرض</button></div>
            </form>
        </div>
    </div>

    <?php if ($sel > 0): ?>
        <?php $bh = contract_badges_html($badges); if ($bh !== ''): ?>
            <div class="pen-badges"><?php echo $bh; ?></div>
        <?php endif; ?>

        <div class="pen-summary">
            <div><i class="fas fa-scroll"></i> <strong><?php echo count($rules); ?></strong> قاعدةً مسجَّلةً على العقد</div>
            <div><i class="fas fa-list-check"></i> <strong><?php echo count($rows); ?></strong> احتسابًا في الفترة</div>
            <div><i class="fas fa-vault"></i> ضمانُ حسن التنفيذ المحتجَز تراكميًّا:
                <strong><?php echo pen_n($held); ?></strong>
                <?php if ($ret_released): ?>
                    <span class="pen-ret-note is-done"><i class="fas fa-check"></i> رُدَّ سلفًا</span>
                <?php elseif ($held > 0 && !$ret_ended && $ret_end !== ''): ?>
                    <span class="pen-ret-note">يُردُّ بعد <?php echo pen_e($ret_end); ?> (ق-20)</span>
                <?php endif; ?>
            </div>
            <?php if ($can_approve && $held > 0 && $ret_ended && !$ret_released): ?>
                <form method="post" action="" class="pen-inline"
                      onsubmit="return confirm('ردُّ ضمان حسن التنفيذ كاملًا (<?php echo pen_n($held); ?>
        <?php echo csrf_field(); ?>)؟\n\nيُنشأ مستخلصٌ ختاميٌّ مسودةٌ ببندٍ موجب، ولا يصير مالًا حتى تُجيزه يدٌ ثانية.\nولا خصمَ للغرامات المعلّقة — خُصمت في مستخلصاتها.')">
                    <input type="hidden" name="pen_action" value="release_retention">
                    <input type="hidden" name="contract" value="<?php echo $sel; ?>">
                    <input type="hidden" name="from" value="<?php echo pen_e($from); ?>">
                    <input type="hidden" name="to" value="<?php echo pen_e($to); ?>">
                    <button type="submit" class="btn-primary pen-release"><i class="fas fa-unlock"></i> ردُّ الضمان</button>
                </form>
            <?php endif; ?>
            <?php if ($can_assess): ?>
                <form method="post" action="" class="pen-inline">
        <?php echo csrf_field(); ?>
                    <input type="hidden" name="pen_action" value="assess">
                    <input type="hidden" name="contract" value="<?php echo $sel; ?>">
                    <input type="hidden" name="from" value="<?php echo pen_e($from); ?>">
                    <input type="hidden" name="to" value="<?php echo pen_e($to); ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-calculator"></i> احتسِب الفترة</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($rules)): ?>
            <div class="pen-note"><i class="fas fa-circle-info"></i>
                لا قواعدَ جزاءٍ مسجَّلةٌ على هذا العقد — فلا شيءَ يُحتسب. القواعدُ نصُّ عقدٍ تُسجَّل
                في <code>contract_penalty_rules</code> بنوعٍ من الأربعة المنصوصة (ق-9).</div>
        <?php endif; ?>

        <div class="card"><div class="card-body">
            <div class="table-container">
                <table class="display pen-table no-datatable">
                    <thead><tr>
                        <th>نوع البند</th><th>نسخة القاعدة المستعملة</th><th>الفترة</th>
                        <th>الملتزَم</th><th>المنفَّذ</th><th>الفارق</th>
                        <th>الأساس المحتسب</th><th>قبل السقف</th><th>السقف</th><th>المبلغ</th>
                        <th>الحالة</th><th>إجراءات</th>
                        <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                        <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
                        <th class="ems-fn-th" data-fn="1">العقد</th>
                        <th class="ems-fn-th" data-fn="1">بند العقد المرجعي</th>
                        <th class="ems-fn-th" data-fn="1">سبب الاستحقاق</th>
                        <th class="ems-fn-th" data-fn="1">النسبة</th>
                        <th class="ems-fn-th" data-fn="1">القيمة</th>
                        <th class="ems-fn-th" data-fn="1">الطرف المتحمل</th>
                        <th class="ems-fn-th" data-fn="1">التحميل على المورد؟</th>
                        <th class="ems-fn-th" data-fn="1">مرجع تسوية المورد</th>
                        <th class="ems-fn-th" data-fn="1">احتسبه</th>
                        <th class="ems-fn-th none" data-fn="1">اعتمده</th>
                        <th class="ems-fn-th none" data-fn="1">المستخلص المدرَج فيه</th>
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                        <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                        <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                        <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                        <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                        <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                        <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                        <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                        </tr></thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="12" class="pen-empty">لا احتساباتٍ في هذه الفترة — اضغط «احتسِب الفترة».</td></tr>
                    <?php else: foreach ($rows as $a):
                        $isPen = ($a['kind'] === 'penalty'); ?>
                        <tr class="pen-row-<?php echo pen_e($a['state']); ?>">
                            <td><span class="pen-kind pen-kind-<?php echo pen_e($a['kind']); ?>">
                                <?php echo pen_e($KIND[$a['kind']] ?? $a['kind']); ?></span></td>
                            <td class="pen-small"><?php echo pen_e($RK[$a['rule_kind']] ?? $a['rule_kind']); ?>
                                <?php if ($a['readiness_pct'] !== null): ?>
                                    <br><span class="pen-muted">الجاهزية: <?php echo pen_n($a['readiness_pct']); ?>٪</span>
                                <?php endif; ?></td>
                            <td class="pen-small pen-nowrap"><?php echo pen_e($a['period_from']); ?><br><?php echo pen_e($a['period_to']); ?></td>
                            <td class="pen-num"><?php echo pen_n($a['committed_qty']); ?></td>
                            <td class="pen-num"><?php echo pen_n($a['actual_qty']); ?></td>
                            <td class="pen-num"><?php echo pen_n($a['gap_qty']); ?></td>
                            <td class="pen-num"><?php echo pen_n($a['base_amount']); ?></td>
                            <td class="pen-num"><?php echo pen_n($a['raw_amount']); ?></td>
                            <td class="pen-num"><?php echo pen_n($a['cap_amount']); ?>
                                <?php if ($a['cap_amount'] !== null && (float) $a['raw_amount'] > (float) $a['cap_amount']): ?>
                                    <br><span class="pen-capped">قُصّ بالسقف</span>
                                <?php endif; ?></td>
                            <td class="pen-num pen-amount <?php echo $isPen ? 'is-neg' : 'is-pos'; ?>">
                                <?php echo ($isPen ? '−' : '+') . pen_n($a['amount']); ?>
                                <br><span class="pen-muted"><?php echo pen_e($a['currency']); ?></span></td>
                            <td class="pen-small">
                                <span class="pen-state pen-state-<?php echo pen_e($a['state']); ?>">
                                    <?php echo pen_e($STATE[$a['state']] ?? $a['state']); ?></span>
                                <?php if ($a['state'] === 'waived' && $a['waive_reason']): ?>
                                    <br><span class="pen-muted" title="<?php echo pen_e($a['waive_reason']); ?>">
                                        سببُ الإعفاء مسجَّل</span>
                                <?php endif; ?>
                                <?php if ($a['reviewer']): ?><br><span class="pen-muted">راجع: <?php echo pen_e($a['reviewer']); ?></span><?php endif; ?>
                                <?php if ($a['approver']): ?><br><span class="pen-muted">أجاز: <?php echo pen_e($a['approver']); ?></span><?php endif; ?>
                            </td>
                            <td class="pen-small">
                                <?php if ($can_assess && $a['state'] === 'computed'): ?>
                                    <form method="post" action="" class="pen-inline">
        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="pen_action" value="review">
                                        <input type="hidden" name="aid" value="<?php echo intval($a['id']); ?>">
                                        <input type="hidden" name="contract" value="<?php echo $sel; ?>">
                                        <input type="hidden" name="from" value="<?php echo pen_e($from); ?>">
                                        <input type="hidden" name="to" value="<?php echo pen_e($to); ?>">
                                        <button type="submit" class="pen-btn"><i class="fas fa-check"></i> مراجعة</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_approve && $a['state'] === 'reviewed'): ?>
                                    <form method="post" action="" class="pen-inline"
                                          onsubmit="return confirm('إجازةُ هذا البند؟ سيُنشر قيدُه في الدفتر ويظهر في المستخلص.')">
        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="pen_action" value="approve">
                                        <input type="hidden" name="aid" value="<?php echo intval($a['id']); ?>">
                                        <input type="hidden" name="contract" value="<?php echo $sel; ?>">
                                        <input type="hidden" name="from" value="<?php echo pen_e($from); ?>">
                                        <input type="hidden" name="to" value="<?php echo pen_e($to); ?>">
                                        <button type="submit" class="pen-btn is-ok"><i class="fas fa-stamp"></i> إجازة</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_approve && in_array($a['state'], array('computed', 'reviewed'), true)): ?>
                                    <button type="button" class="pen-btn is-warn pen-waive"
                                            data-aid="<?php echo intval($a['id']); ?>"><i class="fas fa-ban"></i> إعفاء</button>
                                <?php endif; ?>
                                <?php if ($a['state'] === 'posted'): ?>
                                    <span class="pen-muted">قيد #<?php echo intval($a['event_id']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    <?php else: ?>
        <div class="card"><div class="card-body pen-empty">اختر عقدًا وفترةً.</div></div>
    <?php endif; ?>

    <div id="penWaive" class="pen-modal" hidden>
        <form method="post" action="" class="pen-modal-card">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="pen_action" value="waive">
            <input type="hidden" name="aid" id="penWaiveId" value="">
            <input type="hidden" name="contract" value="<?php echo $sel; ?>">
            <input type="hidden" name="from" value="<?php echo pen_e($from); ?>">
            <input type="hidden" name="to" value="<?php echo pen_e($to); ?>">
            <h5><i class="fas fa-ban"></i> إعفاءٌ من الجزاء</h5>
            <p class="pen-muted">الإعفاءُ قرارٌ ماليٌّ موثَّق — والسببُ إلزاميٌّ يبقى في السجل (ق-13).</p>
            <label for="emsf_80_5121b">سببُ الإعفاء *</label>
            <textarea name="reason" rows="3" maxlength="255" required placeholder="لماذا يُعفى هذا البند؟" id="emsf_80_5121b"></textarea>
            <div class="pen-modal-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> تأكيدُ الإعفاء</button>
                <button type="button" class="btn-secondary" id="penWaiveCancel"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
$(function () {
    const m = $('#penWaive');
    $(document).on('click', '.pen-waive', function () { $('#penWaiveId').val($(this).data('aid')); m.prop('hidden', false); });
    $('#penWaiveCancel').on('click', function () { m.prop('hidden', true); });
    m.on('click', function (e) { if (e.target === this) { m.prop('hidden', true); } });
});
</script>

<style>
    /* UXW-01 ①: الألوانُ من الرموزِ حصرًا — var(--c-<hex>, <hex>) بردمِ القيمةِ الأصلية */
    .pen-main .pen-rule { display:flex; gap:12px; align-items:flex-start; border:1px solid var(--c-ce93d8,#ce93d8);
        border-right:5px solid var(--c-7b1fa2,#7b1fa2); border-radius:10px; background:var(--c-faf5fc,#faf5fc); padding:12px 14px; margin-bottom:14px; line-height:1.75; }
    .pen-main .pen-rule i { color:var(--c-7b1fa2,#7b1fa2); font-size:1.25rem; margin-top:3px; }
    .pen-main .pen-summary { display:flex; gap:18px; align-items:center; flex-wrap:wrap; background:var(--white,#fff);
        border:1px solid var(--bdr); border-radius:12px; padding:12px 14px; margin-bottom:14px; }
    .pen-main .pen-note { border:1px solid var(--c-ffe082,#ffe082); background:var(--c-fffbe9,#fffbe9); border-radius:10px; padding:11px 13px; margin-bottom:12px; }
    .pen-main .pen-ret-note { font-size:.8rem; color:var(--c-a06b00,#a06b00); margin-inline-start:6px; }
    .pen-main .pen-ret-note.is-done { color:var(--c-2e7d32,#2e7d32); font-weight:700; }
    .pen-main .pen-release { background:var(--c-e8f5e9,#e8f5e9); border-color:var(--c-a5d6a7,#a5d6a7); }
    /* مظهرُ الشارات يخرج مع مكوِّنها (includes/contract_badges.php) — فلا يفترق بين شاشتين */
    .pen-main .pen-inline { display:inline; margin:0; }
    .pen-main .pen-kind { padding:3px 10px; border-radius:20px; font-size:.83rem; font-weight:700; white-space:nowrap; }
    .pen-main .pen-kind-penalty { background:var(--c-ffebee,#ffebee); color:var(--c-c62828,#c62828); border:1px solid var(--c-ef9a9a,#ef9a9a); }
    .pen-main .pen-kind-incentive { background:var(--c-e8f5e9,#e8f5e9); color:var(--c-2e7d32,#2e7d32); border:1px solid var(--c-a5d6a7,#a5d6a7); }
    .pen-main .pen-kind-min_guarantee { background:var(--c-e3f2fd,#e3f2fd); color:var(--c-1565c0,#1565c0); border:1px solid var(--c-90caf9,#90caf9); }
    .pen-main .pen-state { padding:2px 8px; border-radius:12px; font-size:.8rem; font-weight:700; }
    .pen-main .pen-state-computed { background:var(--c-eceff1,#eceff1); color:var(--c-546e7a,#546e7a); }
    .pen-main .pen-state-reviewed { background:var(--c-fff8e1,#fff8e1); color:var(--c-a06b00,#a06b00); }
    .pen-main .pen-state-posted { background:var(--c-e8f5e9,#e8f5e9); color:var(--c-2e7d32,#2e7d32); }
    .pen-main .pen-state-waived { background:var(--c-f3e5f5,#f3e5f5); color:var(--c-6a1b9a,#6a1b9a); }
    .pen-main .pen-row-waived { opacity:.62; }
    .pen-main .pen-num { font-variant-numeric:tabular-nums; text-align:left; }
    .pen-main .pen-amount { font-weight:900; }
    .pen-main .pen-amount.is-neg { color:var(--c-c62828,#c62828); }
    .pen-main .pen-amount.is-pos { color:var(--c-2e7d32,#2e7d32); }
    .pen-main .pen-capped { color:var(--c-a06b00,#a06b00); font-size:.78rem; font-weight:700; }
    .pen-main .pen-small { font-size:.84rem; }
    .pen-main .pen-nowrap { white-space:nowrap; }
    .pen-main .pen-muted { color:var(--c-999,#999); font-size:.82rem; }
    .pen-main .pen-empty { text-align:center; color:var(--c-888,#888); padding:22px; }
    .pen-main .pen-btn { background:var(--c-f5f5f5,#f5f5f5); border:1px solid var(--c-ccc,#ccc); border-radius:8px; padding:3px 9px;
        cursor:pointer; font-size:.82rem; margin:1px; white-space:nowrap; }
    .pen-main .pen-btn.is-ok { background:var(--c-e8f5e9,#e8f5e9); border-color:var(--c-a5d6a7,#a5d6a7); }
    .pen-main .pen-btn.is-warn { background:var(--c-f3e5f5,#f3e5f5); border-color:var(--c-ce93d8,#ce93d8); }
    .pen-main .table-container { overflow-x:auto; }
    .pen-modal { position:fixed; inset:0; background:var(--c-black-45, rgba(0,0,0,.45)); display:flex; align-items:center; justify-content:center; z-index:9999; }
    .pen-modal[hidden] { display:none; }
    .pen-modal-card { background:var(--white,#fff); border-radius:14px; padding:20px; width:min(520px,92vw); box-shadow:0 10px 40px var(--c-black-30, rgba(0,0,0,.3)); }
    .pen-modal-card label { display:block; margin:10px 0 4px; font-weight:700; }
    .pen-modal-card textarea { width:100%; }
    .pen-modal-actions { display:flex; gap:10px; margin-top:16px; }
</style>

</body>

</html>

<?php
/**
 * Portal/ceo_approvals.php — اعتمادات المدير التنفيذي (M-00 §8-2 — على جدولها الأصلي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 20 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. الصفوف في الجدول الأصلي `exec_approvals` (هجرة 2026_11_14
 * — أُنجز لحاق CMP03_FOLLOWUP) معزولةً بالكيان، ويصلها الرفعُ الآليُّ من بوابة
 * الطلب المالي عند تجاوز السقف (BR-CEO-05)، والمقرَّرُ محصَّنٌ بقادح BR-CEO-08.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';
require_once '../includes/m00_exec_helpers.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-INFO-200', '');
    exit();
}

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Portal/ceo_approvals.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/ceo_approvals.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم الطلب',
  2 => 'تاريخ الورود',
  3 => 'نوع المستند',
  4 => 'المستند',
  5 => 'الإدارة الطالبة',
  6 => 'سبب الرفع للأعلى',
  7 => 'القيمة',
  8 => 'العملة',
  9 => 'سقف الإدارة',
  10 => 'التجاوز',
  11 => 'المعتمِدون قبلي',
  12 => 'المهلة',
  13 => 'قراري',
  14 => 'سبب القرار',
  15 => 'تاريخ القرار',
  16 => 'المُنشئ — الاسم والصفة',
  17 => 'المعتمِد — الاسم والصفة',
  18 => 'مرجع التفويض',
  19 => 'الحالة',
);
/* أعمدة الجدول الأصلي بترتيب حقول الفورم f0..f17 (الأخير الحالة) */
$DB_FIELDS = array(
    'request_no', 'received_date', 'doc_type', 'document', 'requesting_dept',
    'raise_reason', 'amount', 'currency', 'dept_cap', 'overage',
    'prior_approvers', 'deadline', 'decision', 'decision_reason', 'decision_date',
    'approver_name', 'authority_ref',
);
/* خريطة عرض: فهرس عمود المستند → عمود القاعدة (null = الكيان) */
$COLDB = array(null, 'request_no', 'received_date', 'doc_type', 'document',
    'requesting_dept', 'raise_reason', 'amount', 'currency', 'dept_cap', 'overage',
    'prior_approvers', 'deadline', 'decision', 'decision_reason', 'decision_date',
    '__creator', 'approver_name', 'authority_ref', '__status');

/* ── الحفظ: فورم الإضافة الموحد → الجدول الأصلي ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $in = array();
    foreach ($DB_FIELDS as $i => $col) { $in[$col] = trim((string) ($_POST['f' . $i] ?? '')); }
    $status = trim((string) ($_POST['f17'] ?? '')) ?: 'مسودة';
    foreach (array('amount', 'dept_cap', 'overage') as $numCol) {
        $in[$numCol] = str_replace(array(',', ' '), '', $in[$numCol]);
        if ($in[$numCol] !== '' && !is_numeric($in[$numCol])) { $in[$numCol] = ''; }
    }
    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الفارغ NULL من هنا لا NULLIF في SQL — خلطُ الترتيبات على اتصال الويب يرفضها
    foreach ($in as $k => $v) { if ($v === '') { $in[$k] = null; } }
    $st = $conn->prepare("INSERT INTO exec_approvals
        (company_id, request_no, received_date, doc_type, document, requesting_dept,
         raise_reason, amount, currency, dept_cap, overage, prior_approvers, deadline,
         decision, decision_reason, decision_date, approver_name, authority_ref,
         status, source_kind, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'يدوي', 0, ?, ?)");
    $st->bind_param('issssssssssssssssssis',
        $company_id, $in['request_no'], $in['received_date'], $in['doc_type'],
        $in['document'], $in['requesting_dept'], $in['raise_reason'], $in['amount'],
        $in['currency'], $in['dept_cap'], $in['overage'], $in['prior_approvers'],
        $in['deadline'], $in['decision'], $in['decision_reason'], $in['decision_date'],
        $in['approver_name'], $in['authority_ref'], $status, $uid, $creator);
    $ok = $st->execute();
    if (!$ok) { error_log('ceo_approvals add: ' . $st->error); }
    $st->close();
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
    exit();
}

/* ── القرار: الأفعال الأربعة (M-00 ④-٢ اعتماد · اعتماد بشرط · رد · تأجيل) ──
 * القرارُ للإدارة التنفيذية وحدها (دور 9 أو السوبر) — كلُّ خيارٍ بشرط حقله:
 * الشرطُ للمشروط، والسببُ للرد، والتاريخُ للتأجيل. الاعتمادُ (المطلق والمشروط)
 * على صفٍّ حقيقيٍّ يُنشر حقيقتَه exec.approval.granted (نقطة حدث §11)،
 * والمقرَّرُ لا يُقرَّر ثانيةً — وقادحُ BR-CEO-08 في القاعدة يمنع تعديلَه. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'decide') {
    $goBack = function ($m) { header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($m)); exit(); };
    $actorRole = strval($_SESSION['user']['role'] ?? '');
    if (!$is_super_admin && $actorRole !== '9') {
        http_response_code(403);
        exit('الاعتمادُ الأعلى قرارُ الإدارة التنفيذية وحدها');
    }
    $rowId    = intval($_POST['row'] ?? 0);
    $decision = trim((string) ($_POST['decision'] ?? ''));
    $reason   = trim((string) ($_POST['reason'] ?? ''));
    $until    = trim((string) ($_POST['until'] ?? ''));
    $OPTS = array('اعتماد' => 'معتمد', 'اعتماد بشرط' => 'معتمد بشرط', 'رد' => 'مردود', 'تأجيل' => 'مؤجل');
    if ($rowId <= 0 || !isset($OPTS[$decision])) { $goBack('قرارٌ غير معروف ❌'); }
    if ($decision === 'اعتماد بشرط' && $reason === '') { $goBack('الاعتمادُ المشروط يستلزم نصَّ الشرط ❌'); }
    if ($decision === 'رد' && $reason === '') { $goBack('الردُّ يستلزم سببًا مكتوبًا ❌'); }
    if ($decision === 'تأجيل' && $until === '') { $goBack('التأجيلُ يستلزم تاريخًا ❌'); }

    $st = $conn->prepare("SELECT * FROM exec_approvals WHERE id = ?"
        . ($is_super_admin && $company_id <= 0 ? '' : ' AND company_id = ?'));
    if ($is_super_admin && $company_id <= 0) { $st->bind_param('i', $rowId); }
    else { $st->bind_param('ii', $rowId, $company_id); }
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { $goBack('الصفُّ غير موجودٍ في نطاقك ❌'); }
    // القابل للقرار: الحيُّ والمؤجَّل (يُعاد بتُّه) — والمقرَّر لا يُقرَّر ثانية
    if (!in_array((string) $row['status'], array('مسودة', 'قيد المراجعة', 'مؤجل'), true)) {
        $goBack('الصفُّ مقرَّرٌ سلفًا (' . $row['status'] . ') — لا قرارَ على قرار ❌');
    }

    $actorName = (trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid)) . ' (الإدارة التنفيذية)';
    $decisionReason = ($reason !== '') ? $reason : ($decision === 'اعتماد' ? 'اعتمادٌ مطلق' : (string) ($row['decision_reason'] ?? ''));
    if ($decision === 'تأجيل') { $decisionReason = trim('مؤجل إلى ' . $until . ($reason !== '' ? ' — ' . $reason : '')); }
    $decisionDate = date('Y-m-d');
    $authorityRef = trim((string) ($row['authority_ref'] ?? '')) !== '' ? (string) $row['authority_ref'] : 'سلطة أصلية';
    $newStatus = $OPTS[$decision];

    // التأجيلُ يبقي أعمدةَ القرار فارغةً (يُعاد بتُّه لاحقًا) — وقادحُ القاعدة
    // يصون المقرَّر: أيُّ تعديلٍ لاحقٍ لأعمدة القرار يُرفض من القاعدة نفسها.
    if ($decision === 'تأجيل') {
        $st = $conn->prepare("UPDATE exec_approvals
            SET status = ?, decision_reason = ?, approver_name = ? WHERE id = ?");
        $st->bind_param('sssi', $newStatus, $decisionReason, $actorName, $rowId);
    } else {
        $st = $conn->prepare("UPDATE exec_approvals
            SET decision = ?, decision_reason = ?, decision_date = ?,
                approver_name = ?, authority_ref = ?, status = ?
            WHERE id = ?");
        $st->bind_param('ssssssi', $decision, $decisionReason, $decisionDate,
            $actorName, $authorityRef, $newStatus, $rowId);
    }
    $ok = $st->execute();
    $err = $st->error;
    $st->close();
    if (!$ok) { $goBack('تعذر حفظ القرار: ' . ($err ?: '؟') . ' ❌'); }
    if (function_exists('log_security_event')) {
        log_security_event('CEO_APPROVAL_DECIDED', 'EXAP-' . $rowId . ' → ' . $decision);
    }

    // §11 ExecApproved: الاعتمادُ الفعلي (مطلقًا أو بشرط) على صفٍّ حقيقيٍّ حقيقةٌ تُنشر
    if ((int) $row['is_seed'] === 0 && in_array($decision, array('اعتماد', 'اعتماد بشرط'), true)) {
        try {
            require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'exec.approval.granted',
                'category'        => 'operational',
                'source_module'   => 'system',
                'company_id'      => $company_id,
                'entity_type'     => 'exec_approval',
                'entity_id'       => $rowId,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => $uid ?: 1,
                'idempotency_key' => 'exec_approval:EXAP-' . $rowId,
                'notes'           => 'اعتمادٌ أعلى: ' . mb_substr((string) ($row['document'] ?? $row['request_no']), 0, 120),
                'payload'         => array(
                    'approval_ref' => 'EXAP-' . $rowId,
                    'request_no'   => (string) $row['request_no'],
                    'document'     => (string) ($row['document'] ?? ''),
                    'department'   => (string) ($row['requesting_dept'] ?? ''),
                    'amount'       => (string) ($row['amount'] ?? ''),
                    'currency'     => (string) ($row['currency'] ?? ''),
                    'decision'     => $decision,
                    'condition'    => ($decision === 'اعتماد بشرط') ? $reason : '',
                    'approved_by'  => $uid,
                ),
            ));
        } catch (\Throwable $t) { error_log('ceo_approvals fact #' . $rowId . ': ' . $t->getMessage()); }
    }
    $goBack('قُيّد القرار: ' . $decision . ' ✅');
}

/* ── القراءة: صفوف الكيان من الجدول الأصلي ──────────────────────────────── */
$rows = array();
$sql = "SELECT * FROM exec_approvals"
     . ($is_super_admin && $company_id <= 0 ? '' : ' WHERE company_id = ?')
     . ' ORDER BY id DESC LIMIT 500';
$st = $conn->prepare($sql);
if (!($is_super_admin && $company_id <= 0)) { $st->bind_param('i', $company_id); }
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** قيمة خلية بفهرس عمود المستند — الحوكمة الآلية حية وسائرها من الجدول أو «—» */
function m00_cell_at($idx, $row, $entityName, $COLDB) {
    $col = $COLDB[$idx] ?? null;
    if ($col === null) { return $entityName; }
    if ($col === '__status') { return (string) $row['status']; }
    if ($col === '__creator') { return $row['created_by_name'] ?: '—'; }
    $v = isset($row[$col]) ? trim((string) $row[$col]) : '';
    if ($v !== '' && in_array($col, array('amount', 'dept_cap', 'overage'), true) && is_numeric($v)) {
        $v = rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }
    return $v !== '' ? $v : '—';
}

$page_title = 'إيكوبيشن | اعتمادات المدير التنفيذي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'اعتمادات المدير التنفيذي';
    $header_icon = 'fa fa-stamp';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — اعتمادات المدير التنفيذي</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم الطلب</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>تاريخ الورود</label>
                    <input type="date" name="f1"></div>
                <div class="form-group"><label>نوع المستند</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>المستند</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>الإدارة الطالبة</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>سبب الرفع للأعلى</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>القيمة</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>سقف الإدارة</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0"></div>
                <div class="form-group"><label>التجاوز</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0"></div>
                <div class="form-group"><label>المعتمِدون قبلي</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>المهلة</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>قراري</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>سبب القرار</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>تاريخ القرار</label>
                    <input type="date" name="f14"></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>مرجع التفويض</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f17"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <?php
    // لوحة القرار الرباعي (M-00 ④-٢) — للإدارة التنفيذية وحدها وعلى القابل للبت
    $decidable = array();
    foreach ($rows as $r) {
        if (in_array((string) $r['status'], array('مسودة', 'قيد المراجعة', 'مؤجل'), true)) { $decidable[] = $r; }
    }
    $canDecide = $is_super_admin || strval($_SESSION['user']['role'] ?? '') === '9';
    if ($canDecide && $decidable): ?>
    <form method="post" action="" class="allforms allforms-visible" id="cmp03DecideForm">
        <input type="hidden" name="cmp03_action" value="decide">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-gavel"></i> قرار الاعتماد الأعلى — الخيارات الأربعة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>الصف المعروض للبت</label>
                    <select name="row" required>
                        <?php foreach ($decidable as $d):
                            $lbl = 'EXAP-' . intval($d['id'])
                                 . ' — ' . (string) ($d['request_no'] ?? '؟')
                                 . ' · ' . (string) ($d['document'] ?? ($d['doc_type'] ?? '—'))
                                 . ' (' . (string) $d['status'] . ')'; ?>
                        <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>قراري</label>
                    <select name="decision" id="cmp03DecisionSel" required>
                        <option value="اعتماد">اعتماد</option>
                        <option value="اعتماد بشرط">اعتماد بشرط</option>
                        <option value="رد">رد</option>
                        <option value="تأجيل">تأجيل</option>
                    </select></div>
                <div class="form-group" id="cmp03ReasonWrap"><label id="cmp03ReasonLbl">سبب القرار (إلزامي للمشروط والرد)</label>
                    <input type="text" name="reason" maxlength="300" placeholder="الشرط أو السبب"></div>
                <div class="form-group" id="cmp03UntilWrap" style="display:none"><label>مؤجل إلى (إلزامي للتأجيل)</label>
                    <input type="date" name="until"></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-stamp"></i> قيد القرار</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="ceo_approvalsTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الطلب</th>
            <th>تاريخ الورود</th>
            <th>نوع المستند</th>
            <th>المستند</th>
            <th>الإدارة الطالبة</th>
            <th>سبب الرفع للأعلى</th>
            <th>القيمة</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>سقف الإدارة</th>
            <th>التجاوز</th>
            <th>المعتمِدون قبلي</th>
            <th>المهلة</th>
            <th>قراري</th>
            <th>سبب القرار</th>
            <th>تاريخ القرار</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="20" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach (array_keys($COLS) as $i): $v = m00_cell_at($i, $r, $entityName, $COLDB); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
    // لوحة القرار: إظهار حقل الخيار المختار (شرط/سبب أم تاريخ التأجيل)
    var sel = document.getElementById('cmp03DecisionSel');
    var rw = document.getElementById('cmp03ReasonWrap');
    var rl = document.getElementById('cmp03ReasonLbl');
    var uw = document.getElementById('cmp03UntilWrap');
    if (sel && rw && uw) {
        var sync = function () {
            var v = sel.value;
            uw.style.display = (v === 'تأجيل') ? '' : 'none';
            rw.style.display = '';
            if (rl) {
                rl.textContent = (v === 'اعتماد بشرط') ? 'نص الشرط (إلزامي)'
                    : (v === 'رد') ? 'سبب الرد (إلزامي)'
                    : (v === 'تأجيل') ? 'ملاحظة التأجيل (اختياري)'
                    : 'سبب القرار (اختياري للاعتماد المطلق)';
            }
        };
        sel.addEventListener('change', sync);
        sync();
    }
})();
</script>

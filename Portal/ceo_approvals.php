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

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once '../includes/gov_columns.php';
require_once '../includes/m00_exec_helpers.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
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
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح بالكتابة في هذه الشاشة ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
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
  19 => 'تاريخ الإنشاء',
  20 => 'تاريخ الاعتماد',
  21 => 'المرجع الأب',
  22 => 'الحالة',
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
    '__creator', 'approver_name', 'authority_ref', 
    /* أعمدةُ الحوكمةِ الناقصة — مصادرُها مخزَّنةٌ ولا تُدخَل يدويًّا */
    'created_at', 'approved_at', 'source_request_id',
    '__status');

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
    /* رمزُ الخطأ يُلتقط من **الجملةِ** قبل إغلاقها: `$conn->errno` يبقى صفرًا بعد
       فشلِ جملةٍ محضَّرة — فقراءتُه تقرأ نجاحًا حيث وقع تكرار. */
    $__errno = (int) $st->errno;
    if (!$ok) { error_log('ceo_approvals add: ' . $st->error); }
    $st->close();
    /* ══ INJ-0424 · «حفظُ صفَّين برقمِ المستندِ نفسِه يُرفض **برسالةٍ تسمّي الصفَّ
         القائم**؛ وتحديثُ الصفحةِ بعد الحفظِ لا ينشئ صفًّا ثانيًا» ══════════════
         والمنعُ قيدٌ في القاعدة (`UNIQUE(company_id, request_no)`) لا فحصٌ هنا —
         ففحصُ PHP يُهزَم بطلبين متزامنين، و**1062 حكمُ القاعدةِ لا ظنُّنا**. */
    $__dup = !$ok && $__errno === 1062;
    $__ref = '';
    if ($__dup) {
        $__q = $conn->prepare("SELECT id, status FROM exec_approvals
                                 WHERE company_id = ? AND `request_no` = ? LIMIT 1");
        if ($__q) {
            $__q->bind_param('is', $company_id, $in['request_no']);
            $__q->execute();
            $__rs = $__q->get_result();
            if ($__rs && ($__row = $__rs->fetch_assoc())) {
                $__ref = ' — الصفُّ القائم #' . (int) $__row['id'] . ' (' . $__row['status'] . ')';
            }
            $__q->close();
        }
    }
    ems_gov_flash_redirect(basename(__FILE__),
        $ok ? 'حُفظ الصف ✅'
            : ($__dup ? ('رقمُ الطلب «' . $in['request_no'] . '» مسجَّلٌ سلفًا — لم يُنشأ صفٌّ ثانٍ' . $__ref . ' ❌')
                      : 'تعذر الحفظ ❌'),
        $ok ? 'GOV-OK-200' : ($__dup ? 'GOV-FAIL-409' : 'GOV-OK-200'), '');
    exit();
}

/* ── القرار: الأفعال الأربعة (M-00 ④-٢ اعتماد · اعتماد بشرط · رد · تأجيل) ──
 * القرارُ للإدارة التنفيذية وحدها (دور 9 أو السوبر) — كلُّ خيارٍ بشرط حقله:
 * الشرطُ للمشروط، والسببُ للرد، والتاريخُ للتأجيل. الاعتمادُ (المطلق والمشروط)
 * على صفٍّ حقيقيٍّ يُنشر حقيقتَه exec.approval.granted (نقطة حدث §11)،
 * والمقرَّرُ لا يُقرَّر ثانيةً — وقادحُ BR-CEO-08 في القاعدة يمنع تعديلَه. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'decide') {
    $goBack = function ($m) { ems_gov_flash_redirect(basename(__FILE__), $m, 'GOV-INFO-200', ''); exit(); };
    $actorRole = strval($_SESSION['user']['role'] ?? '');
    if (!$is_super_admin && $actorRole !== '9') {
        ems_gov_flash_redirect('../main/dashboard.php', 'الاعتمادُ الأعلى قرارُ الإدارة التنفيذية وحدها ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
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

    /* ══ INJ-0017 (P1) — «حارسُ منعِ اعتمادِ الذات» لا يُستدعى هنا ═══════════
       كان فرعُ القرارِ يقرأ الصفَّ ويحدّثه بلا مقارنةِ `created_by` بالمقرِّر —
       فمن رفع الطلبَ يقرّره إن كان في الدور 9. والحارسُ مبنيٌّ منذ M-45. */
    require_once __DIR__ . '/../includes/self_approval_guard.php';
    $__sa = ems_assert_not_self_approval($conn, 'exec_approvals', 'id', $rowId,
        'اعتمادٌ تنفيذيٌّ ' . (string) ($row['request_no'] ?? ('#' . $rowId)), $company_id);
    if ($__sa !== null) { $goBack($__sa['reason']); }

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
<style>
/* UXW-01: أنماطُ الشاشةِ في كتلةٍ واحدة — لا نمطَ موضعيًّا ولا لونَ خارجَ الرموز */
.ems-exap-form-actions { margin-top:12px; display:flex; gap:10px; }
.form-group.is-hidden { display:none; }
</style>
<div class="main ems-unified-page-shell ems-doc-cycle" dir="rtl">
    <?php
    $header_title = 'اعتمادات المدير التنفيذي';
    $header_icon = 'fa fa-stamp';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    echo ems_states_bundle('لا اعتماداتٍ معروضةً على الإدارةِ التنفيذيةِ بعدُ', 'أضف صفًّا بزر «إضافة» — أو يصل الرفعُ الآليُّ من بوابةِ الطلبِ المالي عند تجاوزِ السقف');
    echo ems_next_step('قرارُ الإدارةِ التنفيذية على المعروض: اعتمادٌ أو اعتمادٌ بشرطٍ أو ردٌّ أو تأجيل — والمقرَّرُ لا يُقرَّرُ ثانية');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — اعتمادات المدير التنفيذي</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1070_9b99e">رقم الطلب</label>
                    <input type="text" name="f0" required maxlength="190" id="emsf_1070_9b99e"></div>
                <div class="form-group"><label for="emsf_1071_48870">تاريخ الورود</label>
                    <input type="date" name="f1" id="emsf_1071_48870"></div>
                <div class="form-group"><label for="emsf_1072_15911">نوع المستند</label>
                    <input type="text" name="f2" maxlength="190" id="emsf_1072_15911"></div>
                <div class="form-group"><label for="emsf_1073_9c4cb">المستند</label>
                    <input type="text" name="f3" maxlength="190" id="emsf_1073_9c4cb"></div>
                <div class="form-group"><label for="emsf_1074_42a74">الإدارة الطالبة</label>
                    <input type="text" name="f4" maxlength="190" id="emsf_1074_42a74"></div>
                <div class="form-group"><label for="emsf_1075_842b2">سبب الرفع للأعلى</label>
                    <input type="text" name="f5" maxlength="190" id="emsf_1075_842b2"></div>
                <div class="form-group"><label for="emsf_1076_0bf1a">القيمة</label>
                    <input type="text" inputmode="decimal" name="f6" placeholder="0" id="emsf_1076_0bf1a"></div>
                <div class="form-group"><label for="emsf_1077_da9da">العملة</label>
                    <input type="text" name="f7" maxlength="190" id="emsf_1077_da9da"></div>
                <div class="form-group"><label for="emsf_1078_f6fbb">سقف الإدارة</label>
                    <input type="text" inputmode="decimal" name="f8" placeholder="0" id="emsf_1078_f6fbb"></div>
                <div class="form-group"><label for="emsf_1079_d9c37">التجاوز</label>
                    <input type="text" inputmode="decimal" name="f9" placeholder="0" id="emsf_1079_d9c37"></div>
                <div class="form-group"><label for="emsf_1080_cb8a3">المعتمِدون قبلي</label>
                    <input type="text" name="f10" maxlength="190" id="emsf_1080_cb8a3"></div>
                <div class="form-group"><label for="emsf_1081_1b3d7">المهلة</label>
                    <input type="text" name="f11" maxlength="190" id="emsf_1081_1b3d7"></div>
                <div class="form-group"><label for="emsf_1082_3b324">قراري</label>
                    <input type="text" name="f12" maxlength="190" id="emsf_1082_3b324"></div>
                <div class="form-group"><label for="emsf_1083_d8638">سبب القرار</label>
                    <input type="text" name="f13" maxlength="190" id="emsf_1083_d8638"></div>
                <div class="form-group"><label for="emsf_1084_c76ef">تاريخ القرار</label>
                    <input type="date" name="f14" id="emsf_1084_c76ef"></div>
                <div class="form-group"><label for="emsf_1085_e0899">المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f15" maxlength="190" id="emsf_1085_e0899"></div>
                <div class="form-group"><label for="emsf_1086_e0b45">مرجع التفويض</label>
                    <input type="text" name="f16" maxlength="190" id="emsf_1086_e0b45"></div>
                <div class="form-group"><label for="emsf_1087_f3217">الحالة</label>
                    <select name="f17" id="emsf_1087_f3217"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="معتمد">معتمد</option><option value="موقوف">موقوف</option><option value="ملغي">ملغي</option></select></div>
            </div></div>
            <div class="ems-exap-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
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
        <?= csrf_field() ?>
        <input type="hidden" name="cmp03_action" value="decide">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-gavel"></i> قرار الاعتماد الأعلى — الخيارات الأربعة</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_1088_419e3">الصف المعروض للبت</label>
                    <select name="row" required id="emsf_1088_419e3">
                        <?php foreach ($decidable as $d):
                            $lbl = 'EXAP-' . intval($d['id'])
                                 . ' — ' . (string) ($d['request_no'] ?? '؟')
                                 . ' · ' . (string) ($d['document'] ?? ($d['doc_type'] ?? '—'))
                                 . ' (' . (string) $d['status'] . ')'; ?>
                        <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="cmp03DecisionSel">قراري</label>
                    <select name="decision" id="cmp03DecisionSel" required>
                        <option value="اعتماد">اعتماد</option>
                        <option value="اعتماد بشرط">اعتماد بشرط</option>
                        <option value="رد">رد</option>
                        <option value="تأجيل">تأجيل</option>
                    </select></div>
                <div class="form-group" id="cmp03ReasonWrap"><label id="cmp03ReasonLbl" for="emsf_1089_ef825">سبب القرار (إلزامي للمشروط والرد)</label>
                    <input type="text" name="reason" maxlength="300" placeholder="الشرط أو السبب" id="emsf_1089_ef825"></div>
                <div class="form-group is-hidden" id="cmp03UntilWrap"><label for="emsf_1090_e4372">مؤجل إلى (إلزامي للتأجيل)</label>
                    <input type="date" name="until" id="emsf_1090_e4372"></div>
            </div></div>
            <div class="ems-exap-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-stamp"></i> قيد القرار</button>
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
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولَّد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="23" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
            uw.classList.toggle('is-hidden', v !== 'تأجيل');
            rw.classList.remove('is-hidden');
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

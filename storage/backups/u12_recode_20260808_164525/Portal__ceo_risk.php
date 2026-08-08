<?php
/**
 * Portal/ceo_risk.php — المخاطر والقرارات العليا (M-00 §8-2 — على جدولها الأصلي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 17 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. الصفوف في الجدول الأصلي `exec_decisions` (هجرة
 * 2026_11_14 — أُنجز لحاق CMP03_FOLLOWUP) معزولةً بالكيان، والقرارُ المحسوم
 * محصَّنٌ بقادح BR-CEO-08 في القاعدة: تعديلُه مرفوضٌ والتغييرُ قرارٌ جديد.
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
$__pp = check_page_permissions($conn, 'Portal/ceo_risk.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/ceo_risk.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم القرار',
  2 => 'تاريخ الرفع',
  3 => 'الجهة الرافعة',
  4 => 'نوع القضية',
  5 => 'وصف القضية',
  6 => 'الأثر المقدَّر',
  7 => 'العملة',
  8 => 'الخيارات المطروحة',
  9 => 'الخيار المختار',
  10 => 'مبرر الاختيار',
  11 => 'الجهة المكلَّفة بالتنفيذ',
  12 => 'مهلة التنفيذ',
  13 => 'تاريخ المتابعة',
  14 => 'المعتمِد — الاسم والصفة',
  15 => 'تاريخ القرار',
  16 => 'الحالة',
);
/* أعمدة الجدول الأصلي بترتيب حقول الفورم f0..f15 (الأخير الحالة) */
$DB_FIELDS = array(
    'decision_no', 'raised_date', 'raising_dept', 'issue_type', 'issue_desc',
    'est_impact', 'currency', 'options_text', 'chosen_option', 'choice_reason',
    'assigned_dept', 'exec_deadline', 'followup_date', 'approver_name', 'decision_date',
);
/* خريطة عرض: فهرس عمود المستند → عمود القاعدة (null = حوكمة آلية) */
$COLDB = array(null, 'decision_no', 'raised_date', 'raising_dept', 'issue_type',
    'issue_desc', 'est_impact', 'currency', 'options_text', 'chosen_option',
    'choice_reason', 'assigned_dept', 'exec_deadline', 'followup_date',
    'approver_name', 'decision_date', '__status');

/* ── الحفظ: فورم الإضافة الموحد → الجدول الأصلي ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $in = array();
    foreach ($DB_FIELDS as $i => $col) { $in[$col] = trim((string) ($_POST['f' . $i] ?? '')); }
    $status = trim((string) ($_POST['f15'] ?? '')) ?: 'مسودة';

    // ═══ BR-CEO-04: القرارُ المحسوم لا يُقبل بلا جهةٍ مكلَّفةٍ ومهلةِ تنفيذ ═══
    // الحسمُ بتاريخ قرارٍ أو حالةٍ حاسمة، والمسودةُ وقيدُ الدراسة خارج الشرط.
    $decisive = ($in['decision_date'] !== '')
        || in_array($status, array('محسوم', 'معتمد', 'نافذ', 'قيد التنفيذ', 'مقفل'), true);
    if ($decisive) {
        $missing = array();
        if ($in['assigned_dept'] === '') { $missing[] = 'الجهة المكلَّفة بالتنفيذ'; }
        if ($in['exec_deadline'] === '') { $missing[] = 'مهلة التنفيذ'; }
        if ($missing) {
            ems_gov_flash_redirect('' . basename(__FILE__), 'BR-CEO-04: قرارٌ محسومٌ بلا ' . implode(' و', $missing) . ' — أكمل الحقلين ثم احفظ ❌', 'GOV-FAIL-409', '');
            exit();
        }
    }

    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الفارغ NULL من هنا لا NULLIF في SQL — خلطُ الترتيبات على اتصال الويب يرفضها
    $bindIn = $in;
    foreach ($bindIn as $k => $v) { if ($v === '') { $bindIn[$k] = null; } }
    $st = $conn->prepare("INSERT INTO exec_decisions
        (company_id, decision_no, raised_date, raising_dept, issue_type, issue_desc,
         est_impact, currency, options_text, chosen_option, choice_reason,
         assigned_dept, exec_deadline, followup_date, approver_name, decision_date,
         status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
    $st->bind_param('issssssssssssssssis',
        $company_id, $bindIn['decision_no'], $bindIn['raised_date'], $bindIn['raising_dept'],
        $bindIn['issue_type'], $bindIn['issue_desc'], $bindIn['est_impact'], $bindIn['currency'],
        $bindIn['options_text'], $bindIn['chosen_option'], $bindIn['choice_reason'],
        $bindIn['assigned_dept'], $bindIn['exec_deadline'], $bindIn['followup_date'],
        $bindIn['approver_name'], $bindIn['decision_date'], $status, $uid, $creator);
    $ok = $st->execute();
    $newId = $ok ? (int) $conn->insert_id : 0;
    $st->close();

    // M-00 §11 (ExecDecisionMade): الصفُّ الحقيقي المحسوم حقيقةٌ تُنشر من نقطة
    // الحدث + متابعةٌ تُجدول (SRC-10) على صاحب القرار بموعد المتابعة المدخل.
    if ($ok && $decisive && $newId > 0) {
        try {
            require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'exec.decision.made',
                'category'        => 'operational',
                'source_module'   => 'system',
                'company_id'      => $company_id,
                'entity_type'     => 'exec_decision',
                'entity_id'       => $newId,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => $uid ?: 1,
                'idempotency_key' => 'exec_decision:EXDC-' . $newId,
                'notes'           => 'قرارٌ تنفيذي: ' . mb_substr($in['issue_desc'] !== '' ? $in['issue_desc'] : $in['decision_no'], 0, 120),
                'payload'         => array(
                    'decision_ref' => 'EXDC-' . $newId,
                    'decision_no'  => $in['decision_no'],
                    'issue_type'   => $in['issue_type'],
                    'chosen'       => $in['chosen_option'],
                    'assignee'     => $in['assigned_dept'],
                    'deadline'     => $in['exec_deadline'],
                    'follow_up'    => $in['followup_date'],
                    'status'       => $status,
                ),
            ));

            require_once dirname(__DIR__) . '/app/Services/Work/WorkItemService.php';
            $fu = strtotime($in['followup_date']);
            if ($fu === false) { $fu = strtotime($in['exec_deadline']); }
            if ($fu === false || $fu < time()) { $fu = time() + 7 * 86400; }
            \App\Services\Work\WorkItemService::create($conn, array(
                'company_id' => $company_id, 'source_type' => 'SRC-10', 'source_ref' => 'EXDC-' . $newId,
                'source_screen' => 'Portal/ceo_risk.php',
                'owner_user_id' => $uid, 'assigned_user_id' => $uid,
                'org_unit_id' => 1, 'project_id' => 0, 'site_id' => 0,
                'title' => 'متابعة قرارٍ تنفيذي — ' . ($in['decision_no'] !== '' ? $in['decision_no'] : ('EXDC-' . $newId)),
                'deliverable' => 'إفادةُ تنفيذ الجهة المكلَّفة: ' . $in['assigned_dept'],
                'evidence_required' => 'ما يُثبت التنفيذ ضمن المهلة: ' . $in['exec_deadline'],
                'due_at' => date('Y-m-d H:i:s', $fu),
                'priority' => 'P2', 'created_by' => $uid, 'parent_ref' => 'EXDC-' . $newId,
            ));
        } catch (\Throwable $t) { error_log('ceo_risk decision fact #' . $newId . ': ' . $t->getMessage()); }
    }

    ems_gov_flash_redirect('' . basename(__FILE__), $ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌', 'GOV-OK-200', '');
    exit();
}

/* ── القراءة: صفوف الكيان من الجدول الأصلي ──────────────────────────────── */
$rows = array();
$sql = "SELECT * FROM exec_decisions"
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
    return $v !== '' ? $v : '—';
}

$page_title = 'إيكوبيشن | المخاطر والقرارات العليا';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'المخاطر والقرارات العليا';
    $header_icon = 'fa fa-scale-unbalanced';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    // R9 (M-16 ورقة 32): زاوية نطاقية على السجل المركزي — المؤسسية والحرجة
    $legacy_ru_codes = array('RU-01');
    $legacy_view_note = 'زاوية الرئيس: المخاطر المؤسسية (RU-01) — والقرارات العليا أدناه تبقى على سجلها الأصلي';
    include __DIR__ . '/../Risk/_legacy_view_panel.php';
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — المخاطر والقرارات العليا</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم القرار</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>تاريخ الرفع</label>
                    <input type="date" name="f1"></div>
                <div class="form-group"><label>الجهة الرافعة</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>نوع القضية</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>وصف القضية</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>الأثر المقدَّر</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>الخيارات المطروحة</label>
                    <input type="text" name="f7" maxlength="190"></div>
                <div class="form-group"><label>الخيار المختار</label>
                    <input type="text" name="f8" maxlength="190"></div>
                <div class="form-group"><label>مبرر الاختيار</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>الجهة المكلَّفة بالتنفيذ</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>مهلة التنفيذ</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>تاريخ المتابعة</label>
                    <input type="date" name="f12"></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>تاريخ القرار</label>
                    <input type="date" name="f14"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f15"><option value="مسودة">مسودة</option><option value="قيد المراجعة">قيد المراجعة</option><option value="قيد الحسم">قيد الحسم</option><option value="محسوم">محسوم</option><option value="قيد المتابعة">قيد المتابعة</option><option value="مغلق بعد المعالجة">مغلق بعد المعالجة</option><option value="مؤجل">مؤجل</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="ceo_riskTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم القرار</th>
            <th>تاريخ الرفع</th>
            <th>الجهة الرافعة</th>
            <th>نوع القضية</th>
            <th>وصف القضية</th>
            <th>الأثر المقدَّر</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>الخيارات المطروحة</th>
            <th>الخيار المختار</th>
            <th>مبرر الاختيار</th>
            <th>الجهة المكلَّفة بالتنفيذ</th>
            <th>مهلة التنفيذ</th>
            <th>تاريخ المتابعة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th>تاريخ القرار</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="17" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
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
})();
</script>

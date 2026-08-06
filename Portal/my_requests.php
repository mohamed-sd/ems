<?php
/**
 * Portal/my_requests.php — طلباتي (WFM-01 §5-4/5-5 · WF-05/WF-07)
 * ───────────────────────────────────────────────────────────────────────────
 * التقديم من القاموس الحاكم (62 نوعًا — الورقة 04) والنموذج يُشتق لا يُخترع.
 * «من هو عنده الآن ومنذ متى» ظاهرٌ لكل طلب (AC-WFM-07). والمعالجة للمستقبِل:
 * قرارٌ (اعتماد/رفض/إعادة بسبب) ثم تنفيذٌ وإغلاقٌ **ببنية الرد التسعة** —
 * لا إغلاقَ بتغيير حالة (WF-05). كلُّ الأحكام في RequestService لا هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../app/Services/Work/RequestService.php';

use App\Services\Work\RequestService as RQ;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=غير+مصرح"); exit(); }

$__pp = check_page_permissions($conn, 'Portal/my_requests.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/my_requests.php');
    header('Location: ../main/dashboard.php?msg=' . urlencode($__why));
    exit();
}

/* ── الأفعال ─────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    if ($act === 'rq_submit') {
        $r = RQ::submit($conn, array(
            'company_id' => $company_id ?: intval($_POST['company_id'] ?? 0),
            'request_type_code' => (string) ($_POST['type_code'] ?? ''),
            'requester_user_id' => $uid,
            'org_unit_id' => intval($_POST['org_unit_id'] ?? 0) ?: 1,
            'project_id' => intval($_POST['project_id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'fields_json' => json_encode(array('تفاصيل' => trim((string) ($_POST['details'] ?? ''))), JSON_UNESCAPED_UNICODE),
            'created_by' => $uid,
        ));
        $msg = $r['ok'] ? ('قُدّم ' . $r['request_no'] . ' ✅') : ($r['reason'] . ' ❌');
    } elseif ($act === 'rq_decide') {
        $r = RQ::decide($conn, intval($_POST['req_id'] ?? 0), (string) ($_POST['decision'] ?? ''), $uid, trim((string) ($_POST['note'] ?? '')));
        $msg = $r['ok'] ? 'قُرّر ✅' : ($r['reason'] . ' ❌');
    } elseif ($act === 'rq_execute') {
        $r = RQ::executeAndClose($conn, intval($_POST['req_id'] ?? 0), $uid, array(
            'decision' => 'approved',
            'decided_capacity' => (string) ($_SESSION['user']['role_name'] ?? $_SESSION['user']['role'] ?? ''),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'action_required' => trim((string) ($_POST['action_required'] ?? '')),
            'result_doc_ref' => trim((string) ($_POST['result_doc_ref'] ?? '')),
            'executed_summary' => trim((string) ($_POST['executed_summary'] ?? '')),
            'next_step' => trim((string) ($_POST['next_step'] ?? '')),
        ));
        $msg = $r['ok'] ? 'نُفِّذ وأُغلق بالرد التسعة ✅' : ($r['reason'] . ' ❌');
    } elseif ($act === 'rq_cancel') {
        $r = RQ::cancel($conn, intval($_POST['req_id'] ?? 0), $uid, trim((string) ($_POST['reason'] ?? '')));
        $msg = $r['ok'] ? 'أُلغي وعُكس أثره ✅' : ($r['reason'] . ' ❌');
    } else { $msg = 'فعل غير معروف ❌'; }
    header('Location: my_requests.php?msg=' . urlencode($msg));
    exit();
}

/* ── القراءة: طلباتي + ما ينتظر معالجتي ─────────────────────────────────── */
$types = array();
$r = mysqli_query($conn, "SELECT code, name_ar, owner_dept, receiver, sla_hours, deliverable, approval_chain
                            FROM request_types WHERE status = 'active' ORDER BY display_order");
while ($x = mysqli_fetch_assoc($r)) { $types[] = $x; }

$co = $is_super_admin && $company_id <= 0 ? '1=1' : "rq.company_id = {$company_id}";
$mineRows = array(); $inboxRows = array();
$sql = "SELECT rq.*, rt.name_ar AS type_name, uh.name AS holder_name, rr.decision AS resp_decision,
               rr.result_doc_ref, rr.executed_summary, rr.next_step
          FROM requests rq
          JOIN request_types rt ON rt.code = rq.request_type_code
          LEFT JOIN users uh ON uh.id = rq.current_holder_user_id
          LEFT JOIN request_responses rr ON rr.request_id = rq.id
         WHERE {$co} AND (rq.requester_user_id = {$uid} OR rq.current_holder_user_id = {$uid})
         ORDER BY rq.id DESC LIMIT 300";
$r = mysqli_query($conn, $sql);
while ($r && ($x = mysqli_fetch_assoc($r))) {
    if (intval($x['current_holder_user_id']) === $uid && !in_array($x['status'], array('closed', 'cancelled', 'rejected'), true)) { $inboxRows[] = $x; }
    if (intval($x['requester_user_id']) === $uid) { $mineRows[] = $x; }
}

$STATE_AR = array('draft' => 'مسودة', 'submitted' => 'مرفوع', 'routed' => 'قيد التنفيذ', 'in_approval' => 'بانتظار الاعتماد',
    'approved' => 'معتمد', 'rejected' => 'مرفوضة', 'executing' => 'قيد التنفيذ', 'executed' => 'مكتملة',
    'closed' => 'مقفل', 'returned' => 'معادة', 'cancelled' => 'ملغاة');

$page_title = 'إيكوبيشن | طلباتي';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'طلباتي';
    $header_icon = 'fa fa-file-signature';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>'; }
    ?>

    <div class="card" style="margin-bottom:12px;">
        <div class="card-header"><strong><i class="fas fa-plus-circle"></i> تقديم طلب — من القاموس الحاكم (<?php echo count($types); ?> نوعًا)</strong></div>
        <div class="card-body">
            <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <input type="hidden" name="action" value="rq_submit">
                <?php if ($is_super_admin && $company_id <= 0): ?><input type="hidden" name="company_id" value="4"><?php endif; ?>
                <div style="min-width:280px"><label style="font-size:.85rem">نوع الطلب</label>
                    <select name="type_code" class="form-control" required onchange="rqHint(this)">
                        <option value="">— اختر —</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['code']); ?>"
                            data-hint="يستقبله: <?php echo htmlspecialchars($t['receiver']); ?> · السلسلة: <?php echo htmlspecialchars($t['approval_chain']); ?> · المهلة <?php echo intval($t['sla_hours']); ?> ساعة · المخرَج: <?php echo htmlspecialchars($t['deliverable']); ?>">
                            <?php echo htmlspecialchars($t['code'] . ' · ' . $t['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div style="flex:1;min-width:220px"><label style="font-size:.85rem">الموضوع</label>
                    <input name="title" class="form-control" required maxlength="300"></div>
                <div style="flex:1;min-width:220px"><label style="font-size:.85rem">التفاصيل</label>
                    <input name="details" class="form-control" maxlength="500"></div>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> تقديم</button>
            </form>
            <div id="rqHint" class="text-muted" style="margin-top:8px;font-size:.85rem"></div>
        </div>
    </div>
    <script>function rqHint(s){var o=s.options[s.selectedIndex];document.getElementById('rqHint').textContent=o?o.getAttribute('data-hint')||'':'';}</script>

    <?php if ($inboxRows): ?>
    <div class="card" style="margin-bottom:12px;border-right:4px solid #d4b06a;">
        <div class="card-header"><strong><i class="fas fa-inbox"></i> تنتظر معالجتي</strong>
            <span class="badge bg-warning"><?php echo count($inboxRows); ?></span></div>
        <div class="card-body"><div class="table-responsive">
            <table class="alltables display no-datatable" style="width:100%">
                <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>المقدّم منذ</th><th>مهلته</th><th>الحالة</th><th>المعالجة</th></tr></thead>
                <tbody><?php foreach ($inboxRows as $q): $id = intval($q['id']); ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string) $q['request_no']); ?></code></td>
                    <td><?php echo htmlspecialchars((string) $q['type_name']); ?></td>
                    <td style="white-space:normal;max-width:240px"><?php echo htmlspecialchars((string) $q['title']); ?></td>
                    <td><?php echo htmlspecialchars((string) $q['submitted_at']); ?></td>
                    <td><?php echo htmlspecialchars((string) $q['sla_due_at']); ?></td>
                    <td><?php echo htmlspecialchars($STATE_AR[$q['status']] ?? $q['status']); ?></td>
                    <td style="min-width:220px">
                        <?php if (in_array($q['status'], array('submitted', 'routed', 'in_approval'), true)): ?>
                            <form method="post" style="display:inline"><input type="hidden" name="action" value="rq_decide"><input type="hidden" name="req_id" value="<?php echo $id; ?>"><input type="hidden" name="decision" value="approve">
                                <button class="btn btn-sm btn-success">اعتماد</button></form>
                            <details style="display:inline-block"><summary class="btn btn-sm btn-outline-danger" style="display:inline-block">رفض/إعادة</summary>
                                <form method="post" style="margin-top:4px"><input type="hidden" name="action" value="rq_decide"><input type="hidden" name="req_id" value="<?php echo $id; ?>">
                                    <select name="decision" class="form-control form-control-sm" style="margin-bottom:4px"><option value="return">إعادة لاستكمال</option><option value="reject">رفض</option></select>
                                    <input name="note" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="السبب (إلزامي)" required>
                                    <button class="btn btn-sm btn-danger">تأكيد</button></form></details>
                        <?php elseif ($q['status'] === 'approved'): ?>
                            <details><summary class="btn btn-sm btn-primary">تنفيذ وإغلاق (الرد التسعة)</summary>
                                <form method="post" style="margin-top:4px;max-width:300px"><input type="hidden" name="action" value="rq_execute"><input type="hidden" name="req_id" value="<?php echo $id; ?>">
                                    <input name="result_doc_ref" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="⑦ المستند الناتج (إلزامي)" required>
                                    <input name="executed_summary" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="⑧ التنفيذ الذي تم (إلزامي)" required>
                                    <input name="action_required" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="⑥ ما يجب فعله">
                                    <input name="next_step" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="⑨ الخطوة اللاحقة">
                                    <input name="notes" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="⑤ الملاحظات">
                                    <button class="btn btn-sm btn-primary">إغلاق بالرد الكامل</button></form></details>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div></div>
    </div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h6><i class="fas fa-list"></i> طلباتي المقدَّمة — وكلُّ طلبٍ يُعرف أين توقف (AC-WFM-07)</h6>
        <div class="table-responsive">
        <table class="alltables display" id="myRequestsTable">
            <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>قُدّم</th><th>الحالة</th>
                <th>عند مَن الآن</th><th>القرار والرد</th><th>إجراء</th></tr></thead>
            <tbody><?php foreach ($mineRows as $q): $id = intval($q['id']); ?>
            <tr>
                <td><code><?php echo htmlspecialchars((string) $q['request_no']); ?></code></td>
                <td><?php echo htmlspecialchars((string) $q['type_name']); ?></td>
                <td style="white-space:normal;max-width:240px"><?php echo htmlspecialchars((string) $q['title']); ?></td>
                <td><?php echo htmlspecialchars((string) $q['submitted_at']); ?></td>
                <td><?php echo htmlspecialchars($STATE_AR[$q['status']] ?? $q['status']); ?>
                    <?php if ($q['status_reason']): ?><div style="color:#9a6a00;font-size:.78rem"><?php echo htmlspecialchars((string) $q['status_reason']); ?></div><?php endif; ?></td>
                <td><?php echo htmlspecialchars((string) ($q['holder_name'] ?: ($q['status'] === 'closed' ? 'أُغلق' : '—'))); ?></td>
                <td style="white-space:normal;max-width:260px;font-size:.85rem">
                    <?php if ($q['resp_decision']): ?>
                        <strong><?php echo htmlspecialchars((string) $q['resp_decision']); ?></strong>
                        · المستند: <?php echo htmlspecialchars((string) $q['result_doc_ref']); ?>
                        · <?php echo htmlspecialchars((string) $q['executed_summary']); ?>
                        <?php if ($q['next_step']): ?>· التالي: <?php echo htmlspecialchars((string) $q['next_step']); ?><?php endif; ?>
                    <?php else: ?>—<?php endif; ?></td>
                <td><?php if (in_array($q['status'], array('submitted', 'routed', 'returned'), true)): ?>
                    <details><summary class="btn btn-sm btn-outline-danger" style="display:inline-block">سحب</summary>
                        <form method="post" style="margin-top:4px"><input type="hidden" name="action" value="rq_cancel"><input type="hidden" name="req_id" value="<?php echo $id; ?>">
                            <input name="reason" class="form-control form-control-sm" style="margin-bottom:4px" placeholder="السبب" required>
                            <button class="btn btn-sm btn-danger">تأكيد السحب</button></form></details>
                    <?php endif; ?></td>
            </tr>
            <?php endforeach; ?></tbody>
        </table>
        </div>
    </div></div>
</div>

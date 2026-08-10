<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Tickets/ticket_workstreams_board.php — لوحة المسارات المتوازية
 * (update0004 · TKT-17 · TKT-01 §3/§5)
 * رأس واحد ومسارات متوازية: لكل مسار إدارته ومكلفه وحالته ومهلته ومانعه —
 * ولا يُغلق الرأس قبل إغلاق الإلزامية كلها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Tickets/TicketStateService.php';
require_once __DIR__ . '/../app/Services/Tickets/TicketEffectService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Tickets\TicketStateService as TS;
use App\Services\Tickets\TicketEffectService as TE;

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
if ($is_super_admin && $company_id <= 0) { $company_id = 4; }

$MODULE_CODE = 'Tickets/ticket_workstreams_board.php';
$can_view = $is_super_admin; $can_edit = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_edit FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                          WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; $can_edit = intval($row['can_edit']) === 1; }
    $st->close();
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية للوحة المسارات ❌', 'GOV-PERM-403', ''); exit(); }

$msg = strval($_GET['msg'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ws_action']) && $can_edit) {
    $act = strval($_POST['ws_action']);
    $wsId = intval($_POST['ws_id'] ?? 0);
    $r = array('ok' => false, 'reason' => 'فعل غير معرف');
    if ($act === 'receive') { $r = TS::receive($conn, $wsId, $uid); $r['reason'] = 'استُلم — مهلة الإنجاز تقاس من الآن'; }
    elseif ($act === 'start') { $r = TS::startWork($conn, $wsId); $r['reason'] = 'قيد المعالجة'; }
    elseif ($act === 'hold') { $r = TS::hold($conn, $wsId, strval($_POST['reason_code'] ?? ''), strval($_POST['expected_until'] ?? '')); }
    elseif ($act === 'effect') { $r = TE::recordLightEffect($conn, $wsId, strval($_POST['effect_type'] ?? 'reply'), $uid, strval($_POST['body'] ?? '')); $r['reason'] = 'أثر مسجل'; }
    elseif ($act === 'done') { $r = TS::markDone($conn, $wsId, $uid); }
    elseif ($act === 'close') { $r = TS::closeWorkstream($conn, $wsId, $uid, strval($_POST['mode'] ?? 'reporter_confirm')); }
    elseif ($act === 'reopen') { $r = TS::reopen($conn, $wsId, $uid); $r['reason'] = isset($r['reopen_count']) ? ('أعيد فتحه — العداد ' . $r['reopen_count']) : ($r['reason'] ?? ''); }
    ems_gov_redirect("Location: ticket_workstreams_board.php?tk=" . intval($_POST['tk'] ?? 0) . "&msg=" . rawurlencode(($r['reason'] ?? '') . (!empty($r['ok']) ? ' ✅' : ' ❌')));
    exit();
}

$tkFilter = intval($_GET['tk'] ?? 0);
$where = $tkFilter > 0 ? "AND t.id = {$tkFilter}" : "AND t.head_state = 'open'";
$rows = array();
$r = $conn->query(
    "SELECT t.id tk, t.ticket_no, t.priority, t.head_state, t.operational_summary, t.complaint,
            w.ws_id, w.workstream_type, w.seq_no, w.state ws_state, w.activation_state, w.mandatory,
            w.assignee_person_id, u.name assignee_name, ou.name_ar unit_name,
            w.response_due_at, w.resolve_due_at, w.reopen_count,
            (SELECT h.reason_code FROM ticket_holds h WHERE h.ws_id = w.ws_id AND h.ended_at IS NULL LIMIT 1) hold_reason,
            (SELECT COUNT(*) FROM ticket_effects e WHERE e.ws_id = w.ws_id) effects
       FROM tickets t
       JOIN ticket_workstreams w ON w.tk_id = t.id
       LEFT JOIN users u ON u.id = w.assignee_person_id
       LEFT JOIN org_units ou ON ou.unit_id = w.org_unit_id
      WHERE t.company_id = {$company_id} {$where}
      ORDER BY t.id DESC, w.ws_id LIMIT 400");
while ($r && ($x = $r->fetch_assoc())) { $rows[intval($x['tk'])][] = $x; }

$stateLabels = array('new' => 'جديد', 'received' => 'مستلَم', 'in_progress' => 'قيد المعالجة',
    'on_hold' => 'معلَّق بسبب', 'done_pending' => 'منجَز بانتظار التأكيد', 'closed' => 'مغلق',
    'reopened' => 'أعيد فتحه', 'admin_closed' => 'مغلق إداريًّا');

$page_title = 'إيكوبيشن | لوحة مسارات البلاغات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'المسارات المتوازية — رأس واحد وخمس أيادٍ'; $header_icon = 'fa fa-code-branch';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'الواقعة واحدة ورقمها واحد وقد تعنيها خمس إدارات — لكل مسار مكلفه ومهلته ومانعه، '
        . 'وتأخر المشتريات لا يبرئ الصيانة، ولا يُغلق الرأس قبل الإلزامية كلها.',
        array('الاستلام يبدأ مهلة الإنجاز — وهي أهم مقياس',
              'التعليق بسبب محكوم ومدة متوقعة — وتجاوزها يصعّد التعليق نفسه',
              'لا إغلاق بلا أثر ولا يغلق المكلف بلاغه بنفسه'));
    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }
    ?>

    <?php if (!$rows) { ems_state_empty('لا رؤوس مفتوحة — نظيف ✨'); } ?>
    <?php foreach ($rows as $tk => $streams): $h = $streams[0]; ?>
    <div class="card"><div class="card-header"><h5>
        <?php echo htmlspecialchars($h['ticket_no']); ?> —
        <?php echo htmlspecialchars(mb_substr((string) ($h['operational_summary'] ?: $h['complaint']), 0, 70)); ?>
        · <span class="badge <?php echo $h['priority'] === 'critical' ? 'badge-danger' : 'badge-warning'; ?>"><?php echo htmlspecialchars($h['priority']); ?></span>
        · الرأس: <strong><?php echo $h['head_state'] === 'open' ? 'مفتوح' : 'مغلق'; ?></strong> (مشتق)
    </h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>رقم المسار</th><th>الإدارة المالكة</th><th>المكلف</th><th>مسار إلزامي؟</th><th>الحالة</th>
                <th>المانع</th><th>مهلة الإنجاز</th><th>الأثر</th><th>إعادات</th><th>إجراء</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">البلاغ الأصل</th>
                <th class="ems-fn-th" data-fn="1">نوع المسار</th>
                <th class="ems-fn-th" data-fn="1">المستند الناتج</th>
                <th class="ems-fn-th" data-fn="1">مهلة المسار</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الاستلام</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الإنجاز</th>
                <th class="ems-fn-th" data-fn="1">حالة المسار</th>
                <th class="ems-fn-th" data-fn="1">سبب التعليق</th>
                <th class="ems-fn-th" data-fn="1">مدة التعليق</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                </tr></thead>
            <tbody>
            <?php foreach ($streams as $w): ?>
                <tr>
                    <td><?php echo htmlspecialchars($w['workstream_type'] . ($w['seq_no'] > 1 ? ' #' . $w['seq_no'] : '')); ?>
                        <?php if ($w['activation_state'] === 'pending') { echo ' <small style="color:#e67e22">(شرطي بانتظار حدثه)</small>'; }
                              elseif ($w['activation_state'] === 'skipped') { echo ' <small style="color:#888">(انتفى شرطه)</small>'; } ?></td>
                    <td><?php echo htmlspecialchars($w['unit_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($w['assignee_name'] ?: ($w['assignee_person_id'] ? '#' . $w['assignee_person_id'] : '—')); ?></td>
                    <td><?php echo intval($w['mandatory']) === 1 ? 'نعم' : 'لا'; ?></td>
                    <td><?php echo htmlspecialchars($stateLabels[$w['ws_state']] ?? $w['ws_state']); ?></td>
                    <td><?php echo htmlspecialchars($w['hold_reason'] ?: '—'); ?></td>
                    <td><small><?php echo htmlspecialchars($w['resolve_due_at'] ?: '—'); ?></small></td>
                    <td><?php echo intval($w['effects']); ?></td>
                    <td><?php echo intval($w['reopen_count']); ?></td>
                    <td>
                    <?php if ($can_edit && $w['activation_state'] === 'opened' && !in_array($w['ws_state'], array('closed', 'admin_closed'), true)): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="tk" value="<?php echo $tk; ?>">
                            <input type="hidden" name="ws_id" value="<?php echo intval($w['ws_id']); ?>">
                            <?php if ($w['ws_state'] === 'new'): ?>
                                <button name="ws_action" value="receive" class="btn-primary">أستلم</button>
                            <?php elseif (in_array($w['ws_state'], array('received', 'reopened'), true)): ?>
                                <button name="ws_action" value="start" class="btn-primary">أبدأ</button>
                            <?php elseif ($w['ws_state'] === 'in_progress'): ?>
                                <button name="ws_action" value="effect" class="btn-primary" title="أثر reply">سجّل أثرًا</button>
                                <button name="ws_action" value="done" class="btn-primary">أنجزت</button>
                            <?php elseif ($w['ws_state'] === 'done_pending'): ?>
                                <button name="ws_action" value="close" class="btn-primary">أؤكد الإغلاق (مبلّغًا)</button>
                                <button name="ws_action" value="reopen" class="action-btn delete">أعد فتحه</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endforeach; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

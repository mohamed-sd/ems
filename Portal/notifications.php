<?php
/**
 * Portal/notifications.php — التنبيهات (WI-NTF · WF-06 · AC-WFM-08)
 * ───────────────────────────────────────────────────────────────────────────
 * «التنبيهُ إحاطةٌ — ولا يصير مهمةً إلا بفعلٍ مطلوب»: ذو الفعل يحمل زرَّ
 * تحويلٍ صريحًا يولّد مهمةً مرتبطةً (task_item_id) فلا يضيع في الزحام.
 * القراءةُ تُختم read_at — والاحتفاظ 90 يومًا (قرار 8).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/resolve_manager.php';
require_once '../app/Services/Work/WorkItemService.php';

use App\Services\Work\WorkItemService as WI;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=غير+مصرح"); exit(); }

$__pp = check_page_permissions($conn, 'Portal/notifications.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/notifications.php');
    header('Location: ../main/dashboard.php?msg=' . urlencode($__why));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    $nid = intval($_POST['notif_id'] ?? 0);
    if ($act === 'ntf_read') {
        $st = $conn->prepare("UPDATE personal_notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL");
        $st->bind_param('ii', $nid, $uid);
        $st->execute();
        $st->close();
        $msg = 'قُرئ ✅';
    } elseif ($act === 'ntf_read_all') {
        $st = $conn->prepare("UPDATE personal_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
        $st->bind_param('i', $uid);
        $st->execute();
        $st->close();
        $msg = 'قُرئ الكل ✅';
    } elseif ($act === 'ntf_to_task') {
        // WF-06: التحويل الصريح — تنبيهٌ بفعلٍ مطلوب يولّد مهمةً مرتبطة
        $st = $conn->prepare("SELECT * FROM personal_notifications WHERE id = ? AND user_id = ? LIMIT 1");
        $st->bind_param('ii', $nid, $uid);
        $st->execute();
        $n = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$n) { $msg = 'التنبيه غير موجود ❌'; }
        elseif (!empty($n['task_item_id'])) { $msg = 'له مهمة مرتبطة سلفًا (WI-' . intval($n['task_item_id']) . ') ❌'; }
        else {
            $r = WI::create($conn, array(
                'company_id' => intval($n['company_id']), 'source_type' => 'SRC-14',
                'source_ref' => 'NTF-' . intval($n['id']), 'source_screen' => 'Portal/notifications.php',
                'owner_user_id' => $uid, 'assigned_user_id' => $uid, 'org_unit_id' => 1,
                'title' => 'متابعة تنبيه: ' . $n['title'],
                'deliverable' => 'إجراء التنبيه منفَّذًا', 'details' => (string) $n['body'],
                'due_at' => date('Y-m-d H:i:s', time() + 172800), 'created_by' => $uid,
            ));
            if ($r['ok']) {
                $tid = intval($r['id']);
                $conn->query("UPDATE personal_notifications SET task_item_id = {$tid}, read_at = COALESCE(read_at, NOW()) WHERE id = " . intval($n['id']));
                $msg = 'وُلّدت المهمة WI-' . $tid . ' ✅';
            } else { $msg = $r['reason'] . ' ❌'; }
        }
    } else { $msg = 'فعل غير معروف ❌'; }
    header('Location: notifications.php?msg=' . urlencode($msg));
    exit();
}

$co = $is_super_admin && $company_id <= 0 ? '1=1' : "company_id = {$company_id}";
$rows = array();
$r = mysqli_query($conn, "SELECT * FROM personal_notifications
                           WHERE {$co} AND user_id = {$uid}
                             AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                           ORDER BY (read_at IS NULL) DESC, id DESC LIMIT 400");
while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[] = $x; }
$unread = 0;
foreach ($rows as $x) { if ($x['read_at'] === null) { $unread++; } }

$page_title = 'إيكوبيشن | التنبيهات';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'التنبيهات';
    $header_icon = 'fa fa-bell';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    require_once __DIR__ . '/../includes/screen_contract.php';
    ems_screen_about('إحاطاتي — وما يتطلب فعلًا أحوّله مهمةً بزرٍّ صريح فلا يضيع في الزحام.');

    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h6 style="margin:0"><i class="fas fa-envelope"></i> غير المقروء: <span class="badge bg-danger"><?php echo $unread; ?></span>
                <span class="text-muted" style="font-size:.8rem">— الاحتفاظ 90 يومًا (قرار 8)</span></h6>
            <?php if ($unread): ?>
            <form method="post"><input type="hidden" name="action" value="ntf_read_all">
                <button class="btn btn-sm btn-outline-secondary">تعليم الكل مقروءًا</button></form>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
        <table class="alltables display no-datatable" style="width:100%">
            <thead><tr><th style="width:36px"></th><th>التنبيه</th><th>وقته</th><th>رابط الأصل</th><th>الإجراء</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted">لا تنبيهات</td></tr>
            <?php else: foreach ($rows as $n): $nid = intval($n['id']); $isUnread = ($n['read_at'] === null); ?>
                <tr style="<?php echo $isUnread ? 'font-weight:600;background:#fffdf4;' : 'color:#777;'; ?>">
                    <td><?php echo $n['requires_action'] ? '<i class="fas fa-bolt" style="color:#d4870a" title="يتطلب فعلًا"></i>'
                                                        : '<i class="far fa-circle" style="color:#aaa" title="إحاطة"></i>'; ?></td>
                    <td style="white-space:normal;max-width:420px"><strong><?php echo htmlspecialchars((string) $n['title']); ?></strong>
                        <?php if ($n['body']): ?><div style="font-size:.85rem"><?php echo htmlspecialchars((string) $n['body']); ?></div><?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string) $n['created_at']); ?></td>
                    <td><?php if ($n['link']): ?><a href="../<?php echo htmlspecialchars(ltrim((string) $n['link'], '/')); ?>">فتح الأصل</a><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <?php if ($isUnread): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="action" value="ntf_read"><input type="hidden" name="notif_id" value="<?php echo $nid; ?>">
                            <button class="btn btn-sm btn-outline-secondary">قُرئ</button></form>
                        <?php endif; ?>
                        <?php if ($n['requires_action']): ?>
                            <?php if (!empty($n['task_item_id'])): ?>
                                <a class="btn btn-sm btn-outline-primary" href="my_tasks.php?view=today">WI-<?php echo intval($n['task_item_id']); ?></a>
                            <?php else: ?>
                            <form method="post" style="display:inline"><input type="hidden" name="action" value="ntf_to_task"><input type="hidden" name="notif_id" value="<?php echo $nid; ?>">
                                <button class="btn btn-sm btn-primary" title="WF-06: التنبيه ذو الفعل يتحول مهمة">تحويل لمهمة</button></form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

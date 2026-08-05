<?php
/**
 * main/my_workspace.php — مساحة عملي: الحاوية الثمانية فوق الإدارات
 * (update0004 · NAV-07 · NAV-01 §3)
 * ───────────────────────────────────────────────────────────────────────────
 * «ما يخص الشخص لا الإدارة يخرج من قوائم الإدارات إلى مساحة واحدة فوقها» —
 * تُبنى فوق البوابة الشخصية (H-18) ومساحات العمل (H-19) القائمتين: حاوية
 * تنقّل لا شاشة معاملة (المبدأ ⑥-ب) — كل عنصر يقفز إلى موضعه الحي.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
if ($company_id <= 0 && $role !== '-1') { header("Location: ../login.php"); exit(); }
if ($company_id <= 0) { $company_id = 4; }

$q1 = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_assoc() : null; return $x ? intval(reset($x)) : 0; };

// العدادات الثمانية — من مصادرها الحية
$myApprovals = $q1("SELECT COUNT(*) FROM permission_approval_steps s
                     JOIN permission_change_requests r ON r.req_id = s.req_id
                    WHERE r.company_id = {$company_id} AND r.state = 'pending' AND s.decision IS NULL");
$myRequests = $q1("SELECT COUNT(*) FROM fin_requests WHERE company_id = {$company_id}
                    AND created_by = {$uid} AND state NOT IN ('closed','rejected','paid')");
$myTickets = $q1("SELECT COUNT(*) FROM tickets t JOIN ticket_participants p ON p.tk_id = t.id
                   WHERE t.company_id = {$company_id} AND p.person_id = {$uid} AND t.head_state = 'open'");
$myTasks = $q1("SELECT COUNT(*) FROM ticket_workstreams w JOIN tickets t ON t.id = w.tk_id
                 WHERE t.company_id = {$company_id} AND w.assignee_person_id = {$uid}
                   AND w.state IN ('new','received','in_progress','reopened')");
$myMsgs = $q1("SELECT COUNT(*) FROM fin_notifications WHERE company_id = {$company_id}
                AND is_read = 0 AND (target_user_id = {$uid} OR target_user_id IS NULL)");

$tiles = array(
    array('① لوحة دوري', 'ما ينتظرني اليوم بترتيب الإلحاح', 'fa fa-tachometer-alt', '../main/dashboard.php', null),
    array('② مهامي', 'كل ما ينتظر تنفيذي — لا ما ينتظر قراري', 'fa fa-tasks', '../Portal/my_tasks.php', $myTasks),
    array('③ موافقاتي', 'صندوق الاعتماد الجامع: كل ما ينتظر قراري', 'fa fa-check-double', '../Finance/approvals_inbox.php', $myApprovals),
    array('④ طلباتي', 'ما رفعته وحالته ومن يقف عنده', 'fa fa-paper-plane', '../FinRequests/my_requests.php', $myRequests),
    array('⑤ طلب جديد', 'طلب مالي جديد — والنوع يُختار داخله', 'fa fa-plus-circle', '../FinRequests/finance_gateway.php?new=1', null),
    array('⑥ المراسلات والتنبيهات', 'الداخلية وغير المقروءة', 'fa fa-bell', '../chats/index.php', $myMsgs),
    array('⑦ بلاغاتي', 'رفع بلاغ · المفتوحة · ما ينتظر ردي — فالبلاغ يخص الشخص لا الإدارة', 'fa fa-bullhorn', '../Tickets/ticket_contextual_open.php', $myTickets),
    array('⑧ ملفي', 'ملفي الشخصي وكشوفي ووثائقي', 'fa fa-id-card', '../Portal/my_portal.php', null),
    // NAV-01 v6 §7 (update0007 S-03/S-04): عنصران إلزاميان لكل حسابٍ بلا استثناء
    array('⑨ إنجازي', 'ما أنجزتُه أمسِ والأسبوعَ والشهرَ — وبمدةٍ أحددها بتاريخين · بلغة عملي', 'fa fa-trophy', '../Portal/my_achievement.php', null),
    array('⑩ بوابتي', 'ما يخصّني ومحيطي المباشر: معدتي · ورديتي · موقعي — بحسب دوري', 'fa fa-door-open', '../Portal/my_portal.php?view=gateway', null),
);

$page_title = 'إيكوبيشن | مساحة عملي';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مساحة عملي — فوق الإدارات كلها'; $header_icon = 'fa fa-user-circle';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'ما يخصك أنت لا إدارتك: ثمانية عناصر تُفتح من أي موضع وتحل عشر روابط مكررة '
        . 'في أربع عشرة قائمة (NAV-01 §3) — حاوية تنقل تقفز بك إلى موضع الفعل.',
        array('البلاغ والموافقة والطلب تخص الشخص — فمكانها هنا لا في قائمة كل إدارة'));
    ?>
    <div class="card"><div class="card-body" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
        <?php foreach ($tiles as $t): ?>
        <a href="<?php echo htmlspecialchars($t[3]); ?>" style="text-decoration:none;color:inherit">
            <div style="border:1px solid #ddd;border-radius:10px;padding:16px;height:100%;position:relative">
                <?php if ($t[4] !== null && $t[4] > 0): ?>
                    <span class="badge badge-danger" style="position:absolute;top:10px;inset-inline-end:10px;font-size:13px"><?php echo intval($t[4]); ?></span>
                <?php endif; ?>
                <div style="font-size:26px;color:#b8860b"><i class="<?php echo htmlspecialchars($t[2]); ?>"></i></div>
                <div style="font-weight:bold;margin-top:8px"><?php echo htmlspecialchars($t[0]); ?></div>
                <small style="color:#666"><?php echo htmlspecialchars($t[1]); ?></small>
            </div>
        </a>
        <?php endforeach; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

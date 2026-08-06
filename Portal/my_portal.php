<?php
/**
 * Portal/my_portal.php — بوابتي (H-18 · الشاشة 187)
 * ───────────────────────────────────────────────────────────────────────────
 * USR-01 §8-①: «بطاقاتٌ تتشكّل بالصفة والمفاتيح · عنصرٌ مغلق: **لا يظهر
 * أصلًا** (لا رسالةَ منع) · كلُّ رقمٍ ينقر لمصدره».
 * «ماذا يخصّني أنا؟» — والبوابةُ لا تملك بيانًا: كلُّ بطاقةٍ من مالكها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/PortalFeedService.php';
require_once __DIR__ . '/../app/Services/Portal/CapacityService.php';

use App\Services\Portal\PortalFeedService as PFS;
use App\Services\Portal\CapacityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$gate = $is_super ? ems_tenant_db()->forAllTenants('my portal super') : ems_tenant_db();

// الصفةُ الفعّالة — من الجلسة أو أولُ نشطةٍ للحساب
$myCaps = CAP::activeOf($conn, $gate, $uid);
$capId = intval($_SESSION['active_capacity']['id'] ?? 0);
if ($capId <= 0) {
    foreach ($myCaps as $c) { if ((string)$c['state'] === 'active') { $capId = intval($c['id']); break; } }
}
$feed = $capId > 0 ? PFS::feed($conn, $gate, $company_id, $uid, $capId) : null;

/* ── بوابتي فوق المحرك (م-د · WFM-01): عدّادات حيّة من work_items/requests/
   approval_links/work_notifications/achievement_records — كل رقمٍ ينقر
   لشاشته (عرف USR-01: البوابة لا تملك بيانًا؛ كلُّ بطاقةٍ من مالكها) ── */
$wfmCards = array();
if ($uid > 0) {
    $qn = function ($sql) use ($conn) { $r = mysqli_query($conn, $sql);
        return $r ? intval(mysqli_fetch_row($r)[0]) : 0; };
    $coW = $company_id > 0 ? "company_id = {$company_id} AND" : '';
    $wfmCards = array(
        array('مهامي المفتوحة', $qn("SELECT COUNT(*) FROM work_items WHERE {$coW} assigned_user_id = {$uid}
              AND status NOT IN ('closed_accepted','cancelled')"), 'my_tasks.php'),
        array('مهامي المتأخرة', $qn("SELECT COUNT(*) FROM work_items WHERE {$coW} assigned_user_id = {$uid}
              AND (status = 'overdue' OR (due_at < NOW() AND status IN ('assigned','accepted','in_progress')))"), 'my_tasks.php?view=late'),
        array('طلباتي الحية', $qn("SELECT COUNT(*) FROM requests WHERE {$coW} requester_user_id = {$uid}
              AND status IN ('submitted','routed','in_approval','approved','executing','returned')"), 'my_requests.php'),
        array('موافقات بيدي الآن', $qn("SELECT COUNT(*) FROM requests WHERE {$coW} current_holder_user_id = {$uid}
              AND status IN ('submitted','routed','in_approval')"), 'approvals_inbox.php'),
        array('تنبيهات غير مقروءة', $qn("SELECT COUNT(*) FROM personal_notifications WHERE {$coW} user_id = {$uid}
              AND read_at IS NULL"), 'notifications.php'),
        array('إنجازي — 30 يومًا', $qn("SELECT COUNT(*) FROM achievement_records WHERE {$coW} person_user_id = {$uid}
              AND reversed_at IS NULL AND recognized_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"), 'my_achievement.php'),
        array('رسائل غير مقروءة', $qn("SELECT COUNT(*) FROM messages WHERE {$coW} receiver_id = {$uid}
              AND is_read = 0 AND COALESCE(is_deleted_receiver,0) = 0"), '../chats/index.php'),
    );
}

// سجلُّ نشاطي — يراه صاحبُه (§5)
$activity = array();
$r = $conn->query("SELECT * FROM portal_activity_log
                    WHERE company_id={$company_id} AND account_id={$uid}
                    ORDER BY id DESC LIMIT 20");
while ($r && ($row = $r->fetch_assoc())) { $activity[] = $row; }

$page_title = 'إيكوبيشن | بوابتي';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بوابتي'; $header_icon = 'fa fa-id-card';
    $header_actions = array(
        array('href' => 'my_achievement.php', 'icon' => 'fa fa-chart-simple', 'label' => 'إنجازي'),
        array('href' => 'my_evaluation.php', 'icon' => 'fa fa-user-check', 'label' => 'التقييم'),
        array('href' => '../user_capacities.php', 'icon' => 'fa fa-people-arrows', 'label' => 'مبدّل المساحة'),
    );
    include('../includes/page_header.php');
    ?>

    <?php if ($wfmCards): ?>
    <!-- مساحة عملي فوق المحرك (WFM-01) — كل رقمٍ ينقر لشاشته -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-briefcase"></i> مساحة عملي — من المحرك حيًّا</h5></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px">
            <?php foreach ($wfmCards as $wc): ?>
                <a href="<?php echo htmlspecialchars($wc[2]); ?>" style="text-decoration:none;color:inherit">
                <div style="border:1px solid #e5e0d5;border-radius:10px;padding:12px;background:#fffdf7;text-align:center">
                    <div style="font-size:1.5rem;font-weight:800"><?php echo intval($wc[1]); ?></div>
                    <div style="color:#777;font-size:.85rem"><?php echo htmlspecialchars($wc[0]); ?></div>
                </div></a>
            <?php endforeach; ?>
        </div>
    </div></div>
    <?php endif; ?>

    <?php if ($feed === null): ?>
        <div class="alert alert-warning">لا صفةَ نشطةً لحسابك — الاشتقاقُ بيد مدير الصلاحيات (شاشة 182).</div>
    <?php elseif (!$feed['ok']): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($feed['reason']); ?></div>
    <?php else: ?>

    <div class="card"><div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
            <?php foreach ($feed['cards'] as $c): ?>
                <div style="border:1px solid #e5e0d5;border-radius:10px;padding:14px;background:#fffdf7">
                    <div style="color:#888;font-size:.85rem"><?php echo htmlspecialchars($c['title']); ?></div>
                    <div style="font-size:1.15rem;font-weight:800;margin:6px 0">
                        <?php echo htmlspecialchars($c['value']); ?></div>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <small style="color:#aaa"><?php echo htmlspecialchars($c['period']); ?></small>
                        <?php if ($c['source_link'] !== null): ?>
                            <a href="../<?php echo htmlspecialchars($c['source_link']); ?>">المصدر ▸</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($feed['hidden_sections']): ?>
            <p style="color:#a15c00;margin-top:12px"><i class="fa fa-lock"></i>
                أقسامٌ محجوبةٌ بقرارٍ موثَّق: <?php echo count($feed['hidden_sections']); ?>
                — إدارتُها في لوحة الظهور (ADM-01)</p>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        سجلُّ نشاطي (لا يُعدَّل ولا يُحذف)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الوقت</th><th>الفعل</th><th>الهدف</th><th>النتيجة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الموظف</th>
              <th class="ems-fn-th" data-fn="1">الصفة</th>
              <th class="ems-fn-th" data-fn="1">الإدارة</th>
              <th class="ems-fn-th" data-fn="1">الموقع</th>
              <th class="ems-fn-th" data-fn="1">المعدة المكلَّف عليها</th>
              <th class="ems-fn-th" data-fn="1">المشغّل الآخر على المعدة</th>
              <th class="ems-fn-th" data-fn="1">مهام مفتوحة</th>
              <th class="ems-fn-th" data-fn="1">موافقات تنتظرني</th>
              <th class="ems-fn-th" data-fn="1">طلباتي المعلَّقة</th>
              <th class="ems-fn-th" data-fn="1">بلاغاتي المفتوحة</th>
              <th class="ems-fn-th" data-fn="1">إنجاز الشهر</th>
              <th class="ems-fn-th" data-fn="1">آخر دخول</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              </tr></thead>
            <tbody>
            <?php foreach ($activity as $a): ?>
                <tr><td><small><?php echo htmlspecialchars((string)$a['at']); ?></small></td>
                    <td><?php echo htmlspecialchars((string)$a['action_code']); ?></td>
                    <td><?php echo htmlspecialchars($a['target_type'] . ' ' . $a['target_id']); ?></td>
                    <td><?php echo (string)$a['result'] === 'ok'
                        ? "<span class='badge badge-success'>ok</span>"
                        : "<span class='badge badge-danger'>denied</span>"; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

<?php
/**
 * Maintenance/dashboard_mnt.php — لوحة إدارة الصيانة (UX-00 §7 · UX-01 §5 · §8.8 · UX-04 §6)
 * ─────────────────────────────────────────────────────────────────────────
 * ثاني لوحات الأدوار على قالب المدير المالي: المكوّنات السبعة ثابتةَ البنية،
 * تفتتح بأسئلة الدور (UX-04 §1): أيُّ معدةٍ حرجةٍ متوقفةٌ الآن؟ ما الأوامر
 * المفتوحة فوق مدتها؟ ما الوقائيةُ المستحقة؟ وأين تتكرر الأعطال؟
 *
 * «الرئيسية» تحوّل الدورَ 13 إلى هنا (EMS_ROLE_BOARD_ROLES) — والمشرف 14
 * يرثها بقرار المالك. كل رقمٍ ينقر إلى مصدره، والتنبيهاتُ من UX-01 §8.8 نصًّا
 * («قطعةٌ منتظرة» مؤجَّلٌ بلا مصدرٍ — قاعدة عدم التلفيق، انظر role_board).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/role_board.php';
require_once __DIR__ . '/../includes/finreq_badges.php';

$current_role    = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin  = ($current_role === '-1');
$company_id      = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit(); }

$page_permissions = check_page_permissions($conn, 'Maintenance/dashboard_mnt.php');
$can_view = $is_super_admin ? true : $page_permissions['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض لوحة الصيانة ❌', 'GOV-PERM-403', ''); exit(); }

$rb_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('mnt board super') : ems_tenant_db();
$today = date('Y-m-d');

/* ── ① مؤشرات اليوم (UX-04 §6) — كل رقمٍ ينقر إلى مصدره ── */
$eq_in_maint = (int) roleBoardScalar($rb_gate, array('scope' => array('e' => 'equipments')),
    "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE} AND e.availability_status='تحت الصيانة'");
$eq_broken = (int) roleBoardScalar($rb_gate, array('scope' => array('o' => 'operations')),
    "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.equipment_health='معطلة'");
$orders_open = (int) roleBoardScalar($rb_gate, array('scope' => array('o' => 'mnt_order')),
    "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0 AND o.state IN('بلاغ','تنفيذ','فحص')");
$orders_overdue = (int) roleBoardScalar($rb_gate, array('scope' => array('o' => 'mnt_order')),
    "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
     AND o.state IN('بلاغ','تنفيذ','فحص') AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$pm_week = (int) roleBoardScalar($rb_gate, array('scope' => array('p' => 'mnt_plan')),
    "SELECT COUNT(*) FROM mnt_plan p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
     AND p.next_due_date IS NOT NULL AND p.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
// أعطالٌ متكررة بتصنيفها (UX-04 §6): كودُ عطلٍ ورد في أكثر من أمرٍ خلال 90 يومًا
$repeat_faults = (int) roleBoardScalar($rb_gate, array('scope' => array('o' => 'mnt_order')),
    "SELECT COUNT(*) FROM (SELECT o.failure_code_id FROM mnt_order o WHERE {TENANT_SCOPE}
      AND COALESCE(o.is_deleted,0)=0 AND o.failure_code_id IS NOT NULL
      AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
      GROUP BY o.failure_code_id HAVING COUNT(*) > 1) rf");
$cost_month = roleBoardScalar($rb_gate, array('scope' => array('o' => 'mnt_order')),
    "SELECT COALESCE(SUM(o.total_cost),0) FROM mnt_order o WHERE {TENANT_SCOPE}
     AND COALESCE(o.is_deleted,0)=0 AND o.state='إغلاق' AND DATE_FORMAT(o.closed_at,'%Y-%m')=?", array(date('Y-m')));
$insp_week = (int) roleBoardScalar($rb_gate, array('scope' => array('i' => 'mnt_inspection')),
    "SELECT COUNT(*) FROM mnt_inspection i WHERE {TENANT_SCOPE} AND COALESCE(i.is_deleted,0)=0
     AND i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

$cards = array(
    array('fa-truck-pickup',          $eq_in_maint,               'معدات تحت الصيانة الآن',   $eq_in_maint > 0 ? 'err' : 'ok', '../Maintenance/orders.php'),
    array('fa-heart-crack',           $eq_broken,                 'معدات معطلة (التشغيل)',    $eq_broken > 0 ? 'err' : 'ok',   '../Maintenance/orders.php'),
    array('fa-screwdriver-wrench',    $orders_open,               'أوامر صيانة مفتوحة',       'or',                            '../Maintenance/orders.php'),
    array('fa-hourglass-end',         $orders_overdue,            'مفتوحة فوق 7 أيام',        $orders_overdue > 0 ? 'err' : 'ok', '../Maintenance/orders.php'),
    array('fa-calendar-check',        $pm_week,                   'وقائية مستحقة هذا الأسبوع', $pm_week > 0 ? 'or' : 'ok',     '../Maintenance/preventive_plans.php'),
    array('fa-rotate-left',           $repeat_faults,             'أعطال متكررة (90 يومًا)',   $repeat_faults > 0 ? 'err' : 'ok', '../Maintenance/orders.php'),
    array('fa-coins',                 number_format($cost_month, 0), 'تكلفة صيانة الشهر',      'or',                            '../Maintenance/orders.php'),
    array('fa-clipboard-list',        $insp_week,                 'فحوص آخر 7 أيام',          'ok',                            '../Maintenance/inspections.php'),
);

/* ── ②-⑦ عبر المحرك الموحّد ── */
$rb_badges    = ems_finreq_nav_badges($conn);
$rb_tasks     = roleBoardTasks($conn, $rb_gate, 13);
$rb_approvals = roleBoardApprovals($conn, $rb_gate, 13, $rb_badges);
$rb_alerts    = roleBoardAlerts($conn, $rb_gate, 13);
$rb_quick     = roleBoardQuickActions($conn, 13, $current_user_id);
$rb_recent    = roleBoardRecent($conn, $current_user_id);

// ⑥ نبض الأداء — أوامرُ أُنشئت مقابل أُغلقت آخر 7 أيام
$rb_pulse = array('labels' => array(), 'in' => array(), 'out' => array());
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-{$d} days"));
    $rb_pulse['labels'][] = date('m/d', strtotime($day));
    $rb_pulse['in'][]  = roleBoardScalar($rb_gate, array('scope' => array('o' => 'mnt_order')),
        "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0 AND DATE(o.created_at)=?", array($day));
    $rb_pulse['out'][] = roleBoardScalar($rb_gate, array('scope' => array('o' => 'mnt_order')),
        "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0 AND o.state='إغلاق' AND DATE(o.closed_at)=?", array($day));
}
$rb_pulse_title  = 'نبض الأداء — أوامرُ أُنشئت مقابل أُغلقت (7 أيام)';
$rb_pulse_series = array('أُنشئت', 'أُغلقت');

$page_title = 'إيكوبيشن | لوحة إدارة الصيانة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main mnt-board-main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة إدارة الصيانة'; $header_icon = 'fa fa-gauge-high';
    $header_actions = array();
    $header_back = array('href' => '../Maintenance/orders.php', 'class' => '', 'icon' => 'fas fa-screwdriver-wrench', 'label' => 'أوامر الصيانة');
    include('../includes/page_header.php');
    ?>
    <p class="text-muted" style="margin:4px 2px 10px"><i class="fas fa-mug-hot"></i> أسئلة أول اليوم: أي معدةٍ متوقفة؟ ما المفتوح فوق مدته؟ ما الوقائية المستحقة؟ — اضغط أي رقمٍ لفتح مصدره. (<?php echo $today; ?>)</p>

    <!-- ① مؤشرات اليوم -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:12px">
        <?php foreach ($cards as $c): list($icon, $val, $lbl, $tone, $href) = $c;
            $color = $tone === 'ok' ? '#166534' : ($tone === 'err' ? '#991b1b' : '#92400e'); ?>
        <a href="<?php echo $href; ?>" style="text-decoration:none;color:inherit">
            <div class="card" style="height:100%"><div class="card-body" style="text-align:center">
                <i class="fas <?php echo $icon; ?>" style="font-size:20px;opacity:.65"></i>
                <div style="font-size:22px;font-weight:800;margin:6px 0;color:<?php echo $color; ?>"><?php echo htmlspecialchars((string)$val); ?></div>
                <div class="text-muted" style="font-size:13px"><?php echo htmlspecialchars($lbl); ?></div>
            </div></div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php include __DIR__ . '/../includes/role_board_widgets.php'; ?>
</div>
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
</body>
</html>

<?php
/**
 * Portal/vp_approval_inbox.php — صندوقُ اعتماداتِ النائبِ الموحَّد (VP-06)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **طلبٌ × نائبٌ مستلمٌ — شاشةٌ واحدةٌ والمحرّكُ يحدّد أيَّ نائبٍ
 * يستلم أيَّ طلب** (نصُّ الحبّة) — رحلةٌ عابرةُ نطاقات (CROSS_JOURNEY).
 *
 * ◆ الرافدان بالاسم: بوّابةُ الطلباتِ الماليّةِ بحالاتِ الانتظارِ
 *   (`fin_requests` — والتوجيهُ لمستوى النائبِ يحدّده محرّكُ الاعتمادِ
 *   بمصفوفتِه لا هذه الشاشة) · واعتماداتُ القمّةِ الواردةُ
 *   (`exec_approvals` غيرُ المقرَّرة، وما جاوز سقفَ الإدارةِ يظهر تجاوزُه).
 * ◆ نطاقُ النيابةِ يُعلَم على الصفِّ من سجلِّ التكليفاتِ (نمطُ VP-02) —
 *   والرؤيةُ أوسعُ من الصلاحية. والفعلُ عند فاعلِه: صندوقُ الاعتماداتِ
 *   التنفيذيُّ وشاشةُ الطلبات — لا كتابةَ هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/vp_approval_inbox.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('vp approval inbox super') : ems_tenant_db();
require_once __DIR__ . '/../includes/exec_indicator_engine.php';
$myEmp = isset($_SESSION['user']['employee_id']) ? intval($_SESSION['user']['employee_id']) : 0;
$scope = ems_exec_deputy_scope($gate, $myEmp);

$REQMAP = array('sales' => 'sales', 'suppliers' => 'suppliers', 'maintenance' => 'maintenance', 'transport' => 'transport',
                'tickets' => 'tickets', 'movement' => 'movement', 'warehouse' => 'warehouse', 'procurement' => 'procurement_ops',
                'workforce' => 'operators', 'treasury' => 'finance', 'revenue' => 'finance', 'assets' => 'fleet');
$rows = array(); $nPend = 0; $nMine = 0;
try {
    foreach ($gate->select('fin_requests', array('orderBy' => 'id DESC', 'limit' => 2000)) as $x0) {
        $st = (string) $x0['state'];
        if ($st !== 'under_review' && $st !== 'pending_approval') { continue; }
        $nPend++;
        $mod = (string) $x0['source_module'];
        $uc = isset($REQMAP[$mod]) ? $REQMAP[$mod] : '';
        $in = $uc !== '' && isset($scope[$uc]);
        if ($in) { $nMine++; }
        $rows[] = array(
            'kind' => 'طلب مالي ' . ($st === 'pending_approval' ? 'بانتظار الاعتماد' : 'قيد المراجعة'),
            'ref' => (string) $x0['request_no'],
            'src' => $mod !== '' ? $mod : 'عام',
            'desc' => mb_substr((string) $x0['statement'], 0, 60) . ' بمبلغ ' . number_format((float) $x0['amount'], 0) . ' ' . (string) $x0['currency'],
            'mine' => $in ? 'ضمن نطاق نيابتك' : 'خارج النطاق، قراءة',
            'at' => substr((string) $x0['created_at'], 0, 10),
            'link' => '../FinRequests/requests_board.php', 'label' => 'بوابة الطلبات',
        );
    }
} catch (\Throwable $t) { error_log('vp_inbox req: ' . $t->getMessage()); }
try {
    foreach ($gate->select('exec_approvals', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        if (trim((string) $x0['decision']) !== '') { continue; }
        $rows[] = array(
            'kind' => 'اعتماد قمة وارد' . ((float) $x0['overage'] > 0 ? ' بتجاوز سقف' : ''),
            'ref' => (string) $x0['request_no'],
            'src' => (string) $x0['requesting_dept'],
            'desc' => mb_substr((string) $x0['raise_reason'], 0, 60) . ' بمبلغ ' . number_format((float) $x0['amount'], 0) . ' ' . (string) $x0['currency'],
            'mine' => 'يوجهه محرك الاعتماد بمصفوفته',
            'at' => (string) $x0['received_date'],
            'link' => 'approvals_inbox.php', 'label' => 'صندوق الاعتمادات',
        );
    }
} catch (\Throwable $t) { error_log('vp_inbox exec: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | صندوق اعتمادات النائب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'صندوق اعتمادات النائب الموحد: المحرك يحدد من يستلم والفعل عند فاعله'; $header_icon = 'fa fa-stamp'; $header_actions = array();
    $header_back = array('href' => 'vp_dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة قيادة النائب');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">وارد الصندوق الموحد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nPend) ?></div><div class="ems-stat-label">طلبات مالية منتظرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nMine) ?></div><div class="ems-stat-label">ضمن نطاق نيابتك المسجل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">2</div><div class="ems-stat-label">رافدان مسميان</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>النوع</th><th>المرجع</th><th>الجهة المصدر</th><th>البيان</th><th>النطاق</th><th>التاريخ</th><th>فعله عند فاعله</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['src']) ?></td>
                    <td><?= htmlspecialchars($x0['desc']) ?></td>
                    <td><?= htmlspecialchars($x0['mine']) ?></td>
                    <td><?= htmlspecialchars($x0['at']) ?></td>
                    <td><a href="<?= htmlspecialchars($x0['link']) ?>"><?= htmlspecialchars($x0['label']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7">لا وارد منتظرا في الرافدين</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        شاشة واحدة والمحرك يحدد اي نائب يستلم اي طلب، نص الحبة نفسه: التوجيه لمستوى النائب
        يحكمه محرك الاعتماد بمصفوفته، ونطاق نيابتك يعلم على الصف من سجل التكليفات والرؤية اوسع من الصلاحية.
        الفعل في صندوق الاعتمادات وبوابة الطلبات ولا كتابة هنا.
    </div>
</div>
<?php include '../infooter.php'; ?>

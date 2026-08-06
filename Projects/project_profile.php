<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌');
    exit();
}

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($project_id <= 0) {
    header('Location: projects.php?msg=معرف+المشروع+غير+صحيح+❌');
    exit();
}

// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق شركة).
// (سقطت فحوص db_table_has_column بسقوط الهجرات الذاتية: company_id/is_deleted/client_id أعمدةٌ قائمة)
$pp_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('project profile super') : ems_tenant_db();

$project = null;
try {
    $pp_rows = $pp_gate->scopedQuery(array('scope' => array('p' => 'project'), 'enrich' => array('c' => 'clients')),
        "SELECT p.*, c.client_name
                  FROM project p
                  LEFT JOIN clients c ON c.id = p.client_id
                  WHERE p.id = ? AND COALESCE(p.is_deleted,0)=0 AND {TENANT_SCOPE}
                  LIMIT 1", array($project_id));
    $project = !empty($pp_rows) ? $pp_rows[0] : null;
} catch (\Throwable $t) { error_log('project_profile.php load: ' . $t->getMessage()); }

if (!$project) {
    header('Location: projects.php?msg=المشروع+غير+موجود+او+خارج+نطاق+الشركة+❌');
    exit();
}

$contracts_count = 0;
$active_contracts = 0;
$suppliers_count = 0;
$equipments_count = 0;
$drivers_count = 0;
$timesheet_hours = 0;
// جدول mines غير موجود في القاعدة أصلًا (الاستعلام القديم كان يفشل بصمت والعدّاد يبقى 0)
$mines_count = 0;

try {
    $r = $pp_gate->scopedQuery(array('scope' => array('contracts' => 'contracts')),
        "SELECT COUNT(*) AS c FROM contracts WHERE project_id = ? AND {TENANT_SCOPE}", array($project_id));
    if ($r) { $contracts_count = intval($r[0]['c']); }
    $r = $pp_gate->scopedQuery(array('scope' => array('contracts' => 'contracts')),
        "SELECT COUNT(*) AS c FROM contracts WHERE project_id = ? AND status = 1 AND {TENANT_SCOPE}", array($project_id));
    if ($r) { $active_contracts = intval($r[0]['c']); }
    $r = $pp_gate->scopedQuery(array('scope' => array('o' => 'operations', 'e' => 'equipments')),
        "SELECT COUNT(DISTINCT e.suppliers) AS c
                         FROM operations o
                         INNER JOIN equipments e ON e.id = o.equipment
                         WHERE o.project_id = ? AND {TENANT_SCOPE}", array($project_id));
    if ($r) { $suppliers_count = intval($r[0]['c']); }
    $r = $pp_gate->scopedQuery(array('scope' => array('o' => 'operations')),
        "SELECT COUNT(DISTINCT o.equipment) AS c FROM operations o WHERE o.project_id = ? AND {TENANT_SCOPE}", array($project_id));
    if ($r) { $equipments_count = intval($r[0]['c']); }
    $r = $pp_gate->scopedQuery(array('scope' => array('o' => 'operations', 'ed' => 'equipment_drivers')),
        "SELECT COUNT(DISTINCT ed.employee_id) AS c
                         FROM operations o
                         INNER JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                         WHERE o.project_id = ? AND ed.status = 1 AND {TENANT_SCOPE}", array($project_id));
    if ($r) { $drivers_count = intval($r[0]['c']); }
    $r = $pp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations')),
        "SELECT IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS c
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE o.project_id = ? AND t.status = 1 AND {TENANT_SCOPE}", array($project_id));
    if ($r) { $timesheet_hours = floatval($r[0]['c']); }
} catch (\Throwable $t) { error_log('project_profile.php kpis: ' . $t->getMessage()); }

$suppliers_breakdown = array();
try {
    $suppliers_breakdown = $pp_gate->scopedQuery(array(
        'scope' => array('o' => 'operations', 'e' => 'equipments', 's' => 'suppliers'),
        'enrich' => array('t' => 'timesheet')),
        "SELECT
                                s.id,
                                s.name,
                                COUNT(DISTINCT o.equipment) AS equipments_count,
                                IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS hours_sum
                            FROM operations o
                            INNER JOIN equipments e ON e.id = o.equipment
                            INNER JOIN suppliers s ON s.id = e.suppliers
                            LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                            WHERE o.project_id = ? AND {TENANT_SCOPE}
                            GROUP BY s.id, s.name
                            ORDER BY hours_sum DESC
                            LIMIT 10", array($project_id));
} catch (\Throwable $t) { error_log('project_profile.php breakdown: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | بطاقة المشروع';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<style>
.project-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.project-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.project-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.project-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main project-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المشروع';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array(
        array('href' => '../Contracts/contracts.php?filter_project_id=' . intval($project_id), 'class' => 'add-btn', 'icon' => 'fas fa-file-contract', 'label' => 'عقود المشروع'),
        array('href' => 'project_mines.php?project_id=' . intval($project_id), 'class' => 'add-btn', 'icon' => 'fas fa-mountain', 'label' => 'مناجم المشروع'),
    );
    $header_back = array('href' => 'projects.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($project['name']); ?></h2>
        <div class="label">
            العميل: <?php echo htmlspecialchars($project['client_name'] ?: $project['client']); ?> |
            كود المشروع: <?php echo htmlspecialchars($project['project_code'] ?: '-'); ?> |
            كود المنجم: <?php echo htmlspecialchars($project['mine_code'] ?: '-'); ?> |
            الحالة: <?php echo intval($project['status']) === 1 ? 'نشط' : 'غير نشط'; ?>
        </div>
        <div class="label" style="margin-top:6px;">
            الموقع: <?php echo htmlspecialchars($project['location'] ?: '-'); ?> |
            الولاية: <?php echo htmlspecialchars($project['state'] ?: '-'); ?> |
            المنطقة: <?php echo htmlspecialchars($project['region'] ?: '-'); ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $contracts_count; ?></div><div class="label">إجمالي العقود</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $active_contracts; ?></div><div class="label">العقود النشطة</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $suppliers_count; ?></div><div class="label">الموردون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $equipments_count; ?></div><div class="label">المعدات</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $drivers_count; ?></div><div class="label">المشغلون</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $mines_count; ?></div><div class="label">المناجم</div></div>
        <div class="profile-card"><div class="kpi"><?php echo number_format($timesheet_hours, 0); ?></div><div class="label">ساعات التشغيل</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-truck-loading"></i> الموردون المرتبطون بالمشروع</h5></div>
        <div class="card-body">
            <table id="projectSuppliersTable" class="display" style="width:100%;">
                <thead><tr><th>المورد</th><th>عدد المعدات</th><th>الساعات</th></tr></thead>
                <tbody>
                    <?php if ($suppliers_breakdown): foreach ($suppliers_breakdown as $row): ?>
                        <tr>
                            <td><a href="../Suppliers/supplier_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo intval($row['equipments_count']); ?></td>
                            <td><?php echo number_format($row['hours_sum'], 0); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#projectSuppliersTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>

<?php // NAV-01 §5-④ (update0006 B-03): البلاغاتُ المتصلة بالموقع/المشروع
$rt_kind = 'site'; $rt_ref = $project_id;
include __DIR__ . '/../includes/related_tickets_tab.php'; ?>

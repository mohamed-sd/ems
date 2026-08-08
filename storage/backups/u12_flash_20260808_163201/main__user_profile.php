<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-038 · SCN-039 · SCN-040 · SCN-042 · SCN-043 · SCN-044 · SCN-045 · SCN-046 · SCN-047 · SCN-048 · SCN-049 · SCN-050 · SCN-051 · SCN-052 · SCN-053 · SCN-054 · SCN-055 · SCN-056
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

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    header('Location: users.php?msg=معرف+المستخدم+غير+صحيح+❌');
    exit();
}

// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
// (سقطت فحوص db_table_has_column: الأعمدة company_id/is_deleted/created_by قائمة بالترحيلات)
$up_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('user profile super') : ems_tenant_db();

$user_data = null;
try {
    $up_rows = $up_gate->scopedQuery(array('scope' => array('u' => 'users'), 'enrich' => array('p' => 'project')),
        "SELECT u.*, r.name AS role_name, p.name AS project_name
               FROM users u
               LEFT JOIN roles r ON r.id = u.role
               LEFT JOIN project p ON p.id = u.project_id
               WHERE u.id = ? AND COALESCE(u.is_deleted,0)=0 AND {TENANT_SCOPE}
               LIMIT 1", array($user_id));
    $user_data = !empty($up_rows) ? $up_rows[0] : null;
} catch (\Throwable $t) { error_log('user_profile.php load: ' . $t->getMessage()); }

if (!$user_data) {
    header('Location: users.php?msg=المستخدم+غير+موجود+او+خارج+نطاق+الشركة+❌');
    exit();
}

$projects_created = 0;
$clients_created = 0;
$suppliers_created = 0;
$last_login = !empty($user_data['last_login_at']) ? $user_data['last_login_at'] : '-';

try {
    $r = $up_gate->scopedQuery(array('scope' => array('project' => 'project')),
        "SELECT COUNT(*) AS c FROM project WHERE created_by = ? AND {TENANT_SCOPE}", array($user_id));
    if ($r) { $projects_created = intval($r[0]['c']); }
    $r = $up_gate->scopedQuery(array('scope' => array('clients' => 'clients')),
        "SELECT COUNT(*) AS c FROM clients WHERE created_by = ? AND {TENANT_SCOPE}", array($user_id));
    if ($r) { $clients_created = intval($r[0]['c']); }
    $r = $up_gate->scopedQuery(array('scope' => array('suppliers' => 'suppliers')),
        "SELECT COUNT(*) AS c FROM suppliers WHERE created_by = ? AND {TENANT_SCOPE}", array($user_id));
    if ($r) { $suppliers_created = intval($r[0]['c']); }
} catch (\Throwable $t) { error_log('user_profile.php kpis: ' . $t->getMessage()); }

$project_assignments = array();
try {
    $project_assignments = $up_gate->scopedQuery(array('scope' => array('project' => 'project')),
        "SELECT id, name, project_code, status
                                           FROM project
                                           WHERE id = ? AND {TENANT_SCOPE}
                                           LIMIT 1", array(intval($user_data['project_id'])));
} catch (\Throwable $t) { error_log('user_profile.php assignment: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | بطاقة المستخدم';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<style>
.user-profile-page .profile-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px; }
.user-profile-page .profile-card { background:#fff; border:1px solid #ece6d8; border-radius:12px; padding:12px; }
.user-profile-page .kpi { font-weight:800; font-size:1.4rem; color:#0f766e; }
.user-profile-page .label { color:#6b7280; font-size:.9rem; }
</style>

<div class="main user-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المستخدم';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array();
    $header_back    = array('href' => 'users.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-card" style="margin-bottom:12px;">
        <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars($user_data['name']); ?></h2>
        <div class="label">
            اسم المستخدم: <?php echo htmlspecialchars($user_data['username']); ?> |
            الدور: <?php echo htmlspecialchars($user_data['role_name'] ?: $user_data['role']); ?> |
            الهاتف: <?php echo htmlspecialchars($user_data['phone'] ?: '-'); ?>
        </div>
        <div class="label" style="margin-top:6px;">
            الحالة: <?php echo htmlspecialchars($user_data['status']); ?> |
            آخر دخول: <?php echo htmlspecialchars($last_login); ?> |
            تاريخ الإنشاء: <?php echo htmlspecialchars($user_data['created_at']); ?>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card"><div class="kpi"><?php echo $projects_created; ?></div><div class="label">مشاريع أنشأها</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $clients_created; ?></div><div class="label">عملاء أضافهم</div></div>
        <div class="profile-card"><div class="kpi"><?php echo $suppliers_created; ?></div><div class="label">موردون أضافهم</div></div>
        <div class="profile-card"><div class="kpi"><?php echo !empty($user_data['project_id']) ? 1 : 0; ?></div><div class="label">لديه مشروع مكلّف</div></div>
        <div class="profile-card"><div class="kpi"><?php echo !empty($user_data['contract_id']) ? 1 : 0; ?></div><div class="label">لديه عقد مكلّف</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-project-diagram"></i> المشروع المكلّف به</h5></div>
        <div class="card-body">
            <table id="userProjectTable" class="display" style="width:100%;">
                <thead><tr><th>اسم المشروع</th><th>كود المشروع</th><th>الحالة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
                <tbody>
                    <?php if (!empty($project_assignments)): $p = $project_assignments[0]; ?>
                        <tr>
                            <td><a href="../Projects/project_profile.php?id=<?php echo intval($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($p['project_code'] ?: '-'); ?></td>
                            <td><?php echo intval($p['status']) === 1 ? 'نشط' : 'غير نشط'; ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="3">لا يوجد مشروع مكلّف به</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    $('#userProjectTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});
</script>

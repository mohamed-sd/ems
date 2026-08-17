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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    ems_gov_flash_redirect('users.php', 'معرف المستخدم غير صحيح ❌', 'GOV-REF-404', '');
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
    ems_gov_flash_redirect('users.php', 'المستخدم غير موجود او خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
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
require_once __DIR__ . '/../includes/profile_kit.php';   // عُدّةُ بطاقةِ الكِيان — التأليفُ بديلُ النسخ
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* كتلةُ `<style>` المحليةُ سقطت: **نسخةٌ خامسةٌ** من لغةِ البطاقةِ نفسِها،
        وفيها لونانِ مثبَّتانِ احتياطيًّا خارجَ الرموز.
        البطاقةُ على `assets/css/ems-profile.css` عبر `includes/profile_kit.php`. */ ?>

<div class="main user-profile-page ems-profile ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المستخدم';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array();
    $header_back    = array('href' => 'users.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا مشروعَ مكلَّفًا بهذا المستخدم', 'كلِّفه بمشروعٍ من شاشةِ المستخدمين ثم عُدْ إلى بطاقته');
    ?>

    <?php
    /* ══ لوحُ الهوية ═══════════════════════════════════════════════════════
       كان سطرَينِ تفصلُهما شُرَطٌ رأسية، والحالةُ نصٌّ بلا لون. */
    $up_active = (mb_strpos((string) $user_data['status'], 'نشط') !== false
               && mb_strpos((string) $user_data['status'], 'غير') === false);
    echo ems_profile_hero(array(
        'name'   => $user_data['name'],
        'icon'   => 'fas fa-user-gear',
        'status' => array(
            'text' => $user_data['status'],
            'tone' => $up_active ? 'ok' : 'danger',
            'icon' => $up_active ? 'fas fa-circle-check' : 'fas fa-circle-minus',
        ),
        'chips'  => array(
            array('text' => $user_data['username'], 'icon' => 'fas fa-at', 'mono' => true),
            array('text' => $user_data['role_name'] ?: $user_data['role'], 'icon' => 'fas fa-user-shield'),
        ),
        'facts'  => array(
            array('label' => 'الهاتف',         'value' => $user_data['phone']),
            array('label' => 'آخرُ دخول',      'value' => ($last_login === '-') ? '' : $last_login),
            array('label' => 'تاريخُ الإنشاء', 'value' => $user_data['created_at']),
        ),
    ));

    /* ◆ «لديه مشروع/عقد مكلّف» كانا مؤشرَين قيمتُهما **0 أو 1** — والرقمُ
         الثنائيُّ في شريطِ مؤشراتٍ يُقرأ عددًا لا حالة. صارا شارتَي نعم/لا
         بنغمةٍ، وبقي العدُّ الحقيقيُّ (ثلاثةُ أعدادِ إنشاء) في الشريط. */
    echo ems_profile_stats(array(
        array('value' => $projects_created,  'label' => 'مشاريعُ أنشأها'),
        array('value' => $clients_created,   'label' => 'عملاءُ أضافهم'),
        array('value' => $suppliers_created, 'label' => 'موردون أضافهم'),
    ));

    echo ems_profile_section_open(array(
        'title' => 'المشروع المكلّف به',
        'icon'  => 'fas fa-diagram-project',
        'meta'  => (!empty($user_data['contract_id']) ? 'وله عقدٌ مكلَّفٌ به' : 'بلا عقدٍ مكلَّف'),
    ));
    ?>
            <table id="userProjectTable" class="display profile-table">
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
    <?php echo ems_profile_section_close(); ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>


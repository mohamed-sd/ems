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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($project_id <= 0) {
    ems_gov_flash_redirect('projects.php', 'معرف المشروع غير صحيح ❌', 'GOV-REF-404', '');
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
    ems_gov_flash_redirect('projects.php', 'المشروع غير موجود او خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../includes/profile_kit.php';   // عُدّةُ بطاقةِ الكِيان — التأليفُ بديلُ النسخ
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* كتلةُ `<style>` المحليةُ سقطت كاملةً: كانت **نسخةً رابعةً** من لغةِ
        البطاقةِ نفسِها (`.profile-card` · `.kpi` · `.label` · `.profile-grid`)
        — وفيها لونٌ مثبَّتٌ احتياطيًّا خارجَ الرموز. والبطاقةُ الآن
        على `assets/css/ems-profile.css` عبر `includes/profile_kit.php`، وعرضُ
        جدولِها في `ems-screens.css` مع أخواتِه. */ ?>

<div class="main project-profile-page ems-profile ems-unified-page-shell">
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
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا موردَ مرتبطًا بهذا المشروعِ بعد',
        'يظهر المورّدُ هنا حالَ إسنادِ معدةٍ من معداتِه إلى تشغيلٍ في هذا المشروع');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('project', 'نظرةٌ عامة'); ?>

    <?php
    /* ══ لوحُ الهوية ═══════════════════════════════════════════════════════
       كان سطرَينِ طويلَينِ تفصلُهما شُرَطٌ رأسية (سبعُ قيمٍ في سطرَين) —
       والحالةُ نصٌّ بلا لون. صار: الحالةُ شارةً، والأكوادُ رقائقَ، والموقعُ
       حقائقَ معنونةً. والغائبُ يُعلَن «—» بدلَ شَرْطةٍ تتنكّر في هيئةِ قيمة. */
    echo ems_profile_hero(array(
        'name'   => $project['name'],
        'icon'   => 'fas fa-diagram-project',
        'status' => array(
            'text' => intval($project['status']) === 1 ? 'نشط' : 'غير نشط',
            'tone' => intval($project['status']) === 1 ? 'ok' : 'danger',
            'icon' => intval($project['status']) === 1 ? 'fas fa-circle-check' : 'fas fa-circle-minus',
        ),
        'chips'  => array(
            array('text' => $project['project_code'], 'icon' => 'fas fa-hashtag', 'mono' => true),
            array('text' => $project['mine_code'], 'icon' => 'fas fa-mountain', 'mono' => true),
        ),
        'facts'  => array(
            array('label' => 'العميل',   'value' => $project['client_name'] ?: $project['client']),
            array('label' => 'الموقع',   'value' => $project['location']),
            array('label' => 'الولاية',  'value' => $project['state']),
            array('label' => 'المنطقة',  'value' => $project['region']),
        ),
    ));

    /* شريطُ الحصيلة — سبعةُ أعدادٍ كانت سبعَ بطاقاتٍ يدويةً بلغةٍ محلية. */
    echo ems_profile_stats(array(
        array('value' => $contracts_count,  'label' => 'إجمالي العقود'),
        array('value' => $active_contracts, 'label' => 'العقود النشطة', 'tone' => $active_contracts > 0 ? 'ok' : 'muted'),
        array('value' => $suppliers_count,  'label' => 'الموردون'),
        array('value' => $equipments_count, 'label' => 'المعدات'),
        array('value' => $drivers_count,    'label' => 'المشغلون'),
        array('value' => $mines_count,      'label' => 'المناجم'),
        array('value' => number_format($timesheet_hours, 0), 'label' => 'ساعات التشغيل', 'unit' => 'ساعة'),
    ));

    echo ems_profile_section_open(array(
        'title' => 'الموردون المرتبطون بالمشروع',
        'icon'  => 'fas fa-truck-loading',
        'meta'  => 'أعلى عشرةٍ بالساعات',
    ));
    ?>
            <table id="projectSuppliersTable" class="display pp-suppliers-table">
                <thead><tr><th>المورد</th><th>عدد المعدات</th><th>الساعات</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
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
    <?php echo ems_profile_section_close(); ?>

    <?php /* NAV-01 §5-④: البلاغاتُ المتصلة — نُقلت داخلَ الغلافِ بعد أن كانت
             تُضمَّن خلفَ إغلاقِه فتظهر بجانبِ الشاشة. */
    $rt_kind = 'site'; $rt_ref = $project_id;
    include __DIR__ . '/../includes/related_tickets_tab.php'; ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<?php /* UXW-01 ⑤: التهيئةُ المحليةُ حُذفت — المكوّنُ المركزيُّ في assets/js/ui-unification.js
         يلتقط الجدولَ ويضبط لغةَ ar.json نفسَها. ولا سمةَ سلوكٍ لازمةٌ هنا: التهيئةُ
         المحذوفةُ لم تكن تضبط ترتيبًا ولا طولَ صفحةٍ ولا أعمدةً غيرَ قابلةٍ للفرز. */ ?>

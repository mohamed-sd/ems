<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$drivers_has_company = db_table_has_column($conn, 'employees', 'company_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');
$drivercontracts_has_company = db_table_has_column($conn, 'drivercontracts', 'company_id');
$drivers_has_driver_photo = db_table_has_column($conn, 'employees', 'employee_photo');
$drivers_has_identity_photo = db_table_has_column($conn, 'employees', 'identity_photo');
$drivers_has_project_id = db_table_has_column($conn, 'employees', 'project_id');

// ملاحظة: أعمدة employees دائمة (أُنشئت في الترحيل) — لا حاجة لإضافتها ديناميكياً.
$employees_has_employee_type = db_table_has_column($conn, 'employees', 'employee_type');

if (!$is_super_admin && !$drivers_has_company) {
    die('لا يمكن تطبيق العزل التام للموظفين لأن عمود company_id غير متاح في جدول الموظفين.');
}

// بوابة العزل — تستبدل سُلَّم النطاق اليدوي (وفيه احتياطي created_by القديم عبر
// drivercontracts/project/users). employees لها company_id مقيسةً فالعزل مباشر.
$emp_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('employees super view') : ems_tenant_db();

// ════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'Employees/employees.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن صلاحية عرض
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المشغلين+❌");
    exit();
}

$page_title = "إيكوبيشن | الموظفون";

// معالجة إضافة/تعديل مشغل عند إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    // التحقق من الصلاحية (إضافة أو تعديل)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) {
        header("Location: employees.php?msg=لا+توجد+صلاحية+تعديل+المشغلين+❌");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: employees.php?msg=لا+توجد+صلاحية+إضافة+مشغلين+جدد+❌");
        exit();
    }
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // قيمٌ خام للبوابة (النمط المُثبَت) — الربط بالمعاملات يتكفّل بالهروب، والفوارغ
    // القابلة للإلغاء NULL حقيقيّ. حقول الرخصة لم تعد تُكتب من هنا (صفحة المشغّلين).
    $employee_code = trim($_POST['employee_code']);
    $data = array(
        'name'                   => trim($_POST['name']),
        'employee_code'          => $employee_code,
        'nickname'               => trim($_POST['nickname']),
        'identity_type'          => $_POST['identity_type'],
        'identity_number'        => trim($_POST['identity_number']),
        'identity_expiry_date'   => !empty($_POST['identity_expiry_date']) ? $_POST['identity_expiry_date'] : null,
        'employee_photo'         => trim(isset($_POST['employee_photo']) ? $_POST['employee_photo'] : ''),
        'identity_photo'         => trim(isset($_POST['identity_photo']) ? $_POST['identity_photo'] : ''),
        'years_in_field'         => !empty($_POST['years_in_field']) ? intval($_POST['years_in_field']) : null,
        'years_on_equipment'     => !empty($_POST['years_on_equipment']) ? intval($_POST['years_on_equipment']) : null,
        'skill_level'            => $_POST['skill_level'],
        'certificates'           => trim($_POST['certificates']),
        'owner_supervisor'       => trim($_POST['owner_supervisor']),
        'supplier_id'            => !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null,
        'project_id'             => !empty($_POST['project_id']) ? intval($_POST['project_id']) : null,
        'employment_affiliation' => $_POST['employment_affiliation'],
        'salary_type'            => $_POST['salary_type'],
        'monthly_salary'         => !empty($_POST['monthly_salary']) ? floatval($_POST['monthly_salary']) : null,
        'email'                  => trim($_POST['email']),
        'phone'                  => trim($_POST['phone']),
        'phone_alternative'      => trim($_POST['phone_alternative']),
        'address'                => trim($_POST['address']),
        'performance_rating'     => $_POST['performance_rating'],
        'behavior_record'        => $_POST['behavior_record'],
        'accident_record'        => $_POST['accident_record'],
        'health_status'          => $_POST['health_status'],
        'health_issues'          => trim($_POST['health_issues']),
        'vaccinations_status'    => $_POST['vaccinations_status'],
        'previous_employer'      => trim($_POST['previous_employer']),
        'employment_duration'    => trim($_POST['employment_duration']),
        'reference_contact'      => trim($_POST['reference_contact']),
        'general_notes'          => trim($_POST['general_notes']),
        'employee_status'        => $_POST['employee_status'],
        // تصنيف مسار التوظيف (ENUM): الفراغ يُكتب NULL لا '' — فالـENUM يبتلع
        // القيمة الخارجة عن قائمته سلسلةً فارغةً بلا خطأ، فيبدو الحفظ ناجحًا
        // ويسقط التصنيف صامتًا.
        'employment_classification' => (isset($_POST['employment_classification']) && $_POST['employment_classification'] !== '')
                                        ? $_POST['employment_classification'] : null,
        'start_date'             => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'status'                 => $_POST['status'],
    );

    // التحقق من فرادة كود المشغل على مستوى الشركة (البوابة تعزل تلقائيًّا)
    if (!empty($employee_code)) {
        $dupWhere  = "employee_code = ?";
        $dupParams = array($employee_code);
        if ($id > 0) { $dupWhere .= " AND id != ?"; $dupParams[] = $id; }
        $dup = $emp_gate->selectOne('employees', array('columns' => array('id'), 'whereRaw' => $dupWhere, 'params' => $dupParams));
        if ($dup) {
            header("Location: employees.php?msg=كود+المشغل+موجود+مسبقاً+❌");
            exit;
        }
    }

    try {
        if ($id > 0) {
            // تحديث معزول بالشركة عبر البوابة
            $emp_gate->update('employees', $data, array('id' => $id));
            $saved_emp_id = $id;
            $ok_msg = "تم+تعديل+الموظف+بنجاح+✅";
        } else {
            // إضافة (company_id تُحقن آليًّا)
            $saved_emp_id = (int) $emp_gate->insert('employees', $data);
            $ok_msg = "تم+إضافة+الموظف+بنجاح+✅";
        }
        $emp_scope = (!$is_super_admin && $drivers_has_company) ? " AND company_id = $company_id" : "";
        ems_save_employee_extra($conn, $saved_emp_id, $emp_scope); // employee_type + المسمى/الدور + الحقول العامة (بلا حقول الرخصة)
        ems_sync_equipment_operator($conn, $saved_emp_id, $emp_scope); // إنشاء/تحديث سجل المشغّل تلقائياً إن كان سائقاً/مشغّلاً
        header("Location: employees.php?msg=$ok_msg");
        exit;
    } catch (\Throwable $e) {
        header("Location: employees.php?msg=" . urlencode("حدث خطأ أثناء الحفظ ❌: " . $e->getMessage()));
        exit;
    }
}

if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        header("Location: employees.php?msg=لا+توجد+صلاحية+حذف+المشغلين+❌");
        exit();
    }

    $delete_id = intval($_GET['delete_id']);
    if ($delete_id <= 0) {
        header("Location: employees.php?msg=معرف+المشغل+غير+صحيح+❌");
        exit();
    }

    // حارسا الحذف — معزولان عبر البوابة (كانا بلا عزل شركةٍ أصلًا: تسرّبٌ كامنٌ أُغلق)
    $active_contracts = 0;
    $active_equipment_assignments = 0;
    try {
        $active_contracts = $emp_gate->count('drivercontracts', array(
            'whereRaw' => 'employee_id = ? AND status = 1', 'params' => array($delete_id),
        ));
        $active_equipment_assignments = $emp_gate->count('equipment_drivers', array(
            'whereRaw' => 'employee_id = ? AND status = 1', 'params' => array($delete_id),
        ));
    } catch (\Throwable $e) { /* سياق ناقص → يحسمه deleteRow أدناه (0 صفوف) */ }

    if ($active_contracts > 0 || $active_equipment_assignments > 0) {
        header("Location: employees.php?msg=لا+يمكن+حذف+المشغل+لارتباطه+بعقود+أو+تشغيل+نشط+❌");
        exit();
    }

    // حذفٌ صلبٌ كالأصل عبر قناة deleteRow (القدرة الثامنة): كيانٌ بلا أبٍ إلزاميّ —
    // مقيَّدٌ بشركة السياق ومُسجَّل (البوابة ترفض DELETE الخام).
    try {
        if ($emp_gate->deleteRow('employees', $delete_id, 'employees screen delete') > 0) {
            header("Location: employees.php?msg=تم+حذف+المشغل+بنجاح+✅");
            exit();
        }
    } catch (\Throwable $e) { /* يسقط لرسالة التعذّر */ }

    header("Location: employees.php?msg=تعذر+حذف+المشغل+أو+أنه+خارج+نطاق+الشركة+❌");
    exit();
}

include("../inheader.php");
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/ems.main.all.style.css">

<style>
    .equipments-fleet-main .form-section {
        margin-bottom: 14px;
        border: 1px solid var(--bdr);
        border-radius: var(--rl);
        background: linear-gradient(180deg, var(--s1) 0%, #fffbf5 100%);
        box-shadow: var(--sh);
        overflow: hidden;
    }

    .equipments-fleet-main .form-section-header {
        background: linear-gradient(135deg, var(--s0), #2a1b0c);
        color: #fff;
        padding: 12px 14px;
        font-weight: 800;
        font-size: .95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        user-select: none;
        transition: all .2s ease;
        border-bottom: 1px solid rgba(255, 207, 144, .18);
    }

    .equipments-fleet-main .form-section-header:hover {
        filter: brightness(1.04);
    }

    .equipments-fleet-main .form-section-header i {
        color: #1a1a1a;
    }

    .equipments-fleet-main .form-section-header .toggle-icon {
        /* السهم في أقصى اليسار، والشارة الرقمية بجانبه مباشرة */
        order: 100;
        margin-right: 0;
        margin-left: 0;
        color: #000;
        font-size: 1rem;
        transition: transform .25s ease;
    }

    /* شارة الرقم التلقائية (نظام الفورم الموحّد) تلتصق بالسهم في أقصى اليسار،
       فلا يبقى أي رقم عائم في منتصف رأس البلوك */
    .equipments-fleet-main .form-section-header::after {
        order: 99;
        margin-right: auto;
        margin-left: 0;
    }

    .equipments-fleet-main .form-section-header.collapsed .toggle-icon {
        transform: rotate(-90deg);
    }

    .equipments-fleet-main .form-section-body {
        padding: 14px;
        max-height: 1000px;
        overflow: hidden;
        transition: max-height .25s ease;
    }

    .equipments-fleet-main .form-section-body.collapsed {
        max-height: 0;
        padding-top: 0;
        padding-bottom: 0;
    }

    .equipments-fleet-main .checkbox-group {
        background: var(--s2);
        padding: 14px;
        border-radius: var(--r);
        border: 1px solid var(--bdr);
    }

    .equipments-fleet-main .checkbox-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        margin-bottom: 6px;
        background: #fff;
        border-radius: 8px;
        cursor: pointer;
        transition: all .2s ease;
        border: 1px solid transparent;
    }

    .equipments-fleet-main .checkbox-group label:hover {
        background: var(--s3);
        border-color: rgba(247, 147, 26, .2);
        transform: translateX(-2px);
    }

    .equipments-fleet-main .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--or);
    }

    /* شارة تصنيف مسار التوظيف — نفس هيئة .status-pill المعتمدة، وألوانها
       تُحقن سطريًّا لكل قيمة لأنها دلالةُ مرحلةٍ لا حالةَ تفعيل. */
    .emp-class-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: .74rem;
        font-weight: 800;
        white-space: nowrap;
        border: 1px solid rgba(0, 0, 0, .06);
    }

    .emp-class-pill.emp-class-none {
        background: #f9fafb;
        color: #9ca3af;
        font-weight: 700;
    }

    .link-alert-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 6px;
        padding: 2px 9px;
        border-radius: 999px;
        background: linear-gradient(135deg, #fff7d6, #ffe8bf);
        color: #7c2d12;
        border: 1px solid rgba(217, 119, 6, 0.28);
        font-size: .72rem;
        font-weight: 800;
        box-shadow: 0 1px 4px rgba(217, 119, 6, 0.18);
        animation: linkAlertPulse 1.6s ease-in-out infinite;
        vertical-align: middle;
    }

    .link-alert-chip i {
        color: #b45309;
        font-size: .75rem;
    }

    @keyframes linkAlertPulse {
        0%,
        100% {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(217, 119, 6, 0.18);
        }

        50% {
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(217, 119, 6, 0.28);
        }
    }

    /* خلفية الجزء الرئيسي بيضاء */
    .main.drivers-main {
        background: #fff;
    }

    /* أيقونات حقول الفورم سوداء */
    .equipments-fleet-main .form-section-body label i,
    .equipments-fleet-main .form-section-body > div > label i {
        color: #1a1a1a;
    }

    /* عمود الإجراءات: نفس هوية أزرار جدول العرض في صفحة المشاريع (شكل دوائر ذهبية) */
    .drivers-main .action-btns {
        display: flex;
        gap: 6px;
        justify-content: center;
        flex-wrap: nowrap;
    }

    .drivers-main .action-btns .action-btn {
        width: 34px;
        height: 34px;
        margin: 0;
        padding: 0;
        border-radius: 8px;
        font-size: .9rem;
        border: 1px solid rgba(247, 147, 26, .28);
        background: linear-gradient(135deg, #fff8ec 0%, #fffaf2 100%);
        color: #9a7b00;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
    }

    .drivers-main .action-btns .action-btn:hover {
        color: #fff;
        background: linear-gradient(135deg, #f7931a 0%, #d97706 100%);
        border-color: rgba(247, 147, 26, .7);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
    }

    .drivers-main .action-btns .action-btn.history {
        border-color: rgba(111, 66, 193, .22);
        background: rgba(111, 66, 193, .10);
        color: #6f42c1;
    }

    .drivers-main .action-btns .action-btn.history:hover {
        background: #6f42c1;
        color: #fff;
        border-color: rgba(111, 66, 193, .6);
    }

    .drivers-main .action-btns .action-btn.delete {
        border-color: rgba(220, 38, 38, .25);
        background: rgba(220, 38, 38, .08);
        color: #dc2626;
    }

    .drivers-main .action-btns .action-btn.delete:hover {
        background: linear-gradient(135deg, #dc2626 0%, #a01818 100%);
        color: #fff;
        border-color: rgba(220, 38, 38, .6);
    }
</style>

<?php
include('../insidebar.php');
?>

<div class="main equipments-fleet-main drivers-main">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    // NOTE: the gradient button inline styles are preserved as-is for now (separate CSS-consolidation task).
    $header_title = 'الموظفون';
    $header_icon  = 'fas fa-id-card';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة موظف جديد');
    }
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('drivers', 'المشغلين', $can_add) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- الاستيراد يتم عبر نافذة معالج إطار Excel الموحّد (تُطبع في نهاية الصفحة عبر ems_excel_render). -->

    <!-- فورم إضافة / تعديل مشغل -->
    <form id="projectForm" action="" method="post" class="allforms">
         <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل موظف </h5>
            </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <input type="hidden" name="id" id="drivers_id" value="">

                <!-- 1. المعلومات الأساسية والتعريفية -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-info-circle"></i>
                        <span>المعلومات الأساسية والتعريفية</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user"></i> اسم المشغل/السائق <span
                                        style="color: red;">*</span></label>
                                <input type="text" name="name" id="name" placeholder="مثال: محمد أحمد علي" required />
                            </div>
                            <div>
                                <label><i class="fas fa-barcode"></i> الرمز/الكود الفريد</label>
                                <input type="text" name="employee_code" id="employee_code"
                                    placeholder="مثال: OPR-001-2026" />
                            </div>
                            <div>
                                <label><i class="fas fa-signature"></i> اسم الشهرة/الكنية</label>
                                <input type="text" name="nickname" id="nickname" placeholder="مثال: أبو محمد" />
                            </div>
                            <div>
                                <label><i class="fas fa-user-tag"></i> نوع الموظف <span style="color: red;">*</span></label>
                                <select name="employee_type" id="employee_type" onchange="emsToggleEmpType()" required>
                                    <?php foreach (ems_employee_types() as $__et) {
                                        echo '<option value="' . htmlspecialchars($__et, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($__et, ENT_QUOTES, 'UTF-8') . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-id-badge"></i> المسمى الوظيفي
                                    <a href="job_titles.php" target="_blank" title="إدارة المسميات" style="font-size:.75rem;margin-inline-start:6px;"><i class="fas fa-gear"></i></a>
                                </label>
                                <select name="job_title_id" id="job_title_id">
                                    <option value="">— اختر المسمى الوظيفي —</option>
                                    <?php
                                    // مسمّيات الشركة عبر البوابة (بعد تعبئة M6 يكافئ نمطَ «NULL أو شركتي» القديم)
                                    foreach ($emp_gate->select('job_titles', array('columns' => array('id', 'name'), 'whereRaw' => 'status = 1', 'orderBy' => 'sort_order, name')) as $__jt) {
                                        echo '<option value="' . intval($__jt['id']) . '">' . htmlspecialchars($__jt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-people-arrows"></i> دور الموظف
                                    <a href="employee_roles.php" target="_blank" title="إدارة الأدوار" style="font-size:.75rem;margin-inline-start:6px;"><i class="fas fa-gear"></i></a>
                                </label>
                                <select name="employee_role_id" id="employee_role_id">
                                    <option value="">— اختر الدور —</option>
                                    <?php
                                    // أدوار الشركة عبر البوابة (بعد M6)
                                    foreach ($emp_gate->select('employee_roles', array('columns' => array('id', 'name'), 'whereRaw' => 'status = 1', 'orderBy' => 'sort_order, name')) as $__er) {
                                        echo '<option value="' . intval($__er['id']) . '">' . htmlspecialchars($__er['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-day"></i> تاريخ الميلاد</label>
                                <input type="date" name="birth_date" id="birth_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-flag"></i> الجنسية</label>
                                <input type="text" name="nationality" id="nationality" placeholder="مثال: سوداني" />
                            </div>
                            <div>
                                <label><i class="fas fa-droplet"></i> فصيلة الدم</label>
                                <input type="text" name="blood_type" id="blood_type" placeholder="مثال: O+" />
                            </div>
                            <div>
                                <label><i class="fab fa-whatsapp"></i> واتساب</label>
                                <input type="text" name="whatsapp" id="whatsapp" placeholder="مثال: +249912345678" />
                            </div>
                            <div>
                                <label><i class="fas fa-user-shield"></i> جهة الطوارئ (الاسم)</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name" />
                            </div>
                            <div>
                                <label><i class="fas fa-people-arrows"></i> صلة جهة الطوارئ</label>
                                <input type="text" name="emergency_contact_relation" id="emergency_contact_relation" placeholder="مثال: أخ" />
                            </div>
                            <div>
                                <label><i class="fas fa-phone-volume"></i> هاتف الطوارئ</label>
                                <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. بيانات الهوية والتوثيق -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-id-card"></i>
                        <span>بيانات الهوية والتوثيق</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-address-card"></i> نوع الهوية</label>
                                <select name="identity_type" id="identity_type">
                                    <option value="">-- اختر نوع الهوية --</option>
                                    <option value="بطاقة هوية وطنية">بطاقة هوية وطنية</option>
                                    <option value="جواز سفر">جواز سفر</option>
                                    <option value="بطاقة لاجئ">بطاقة لاجئ</option>
                                    <option value="رخصة قيادة">رخصة قيادة</option>
                                    <option value="بطاقة أخرى">بطاقة أخرى</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-hashtag"></i> رقم الهوية</label>
                                <input type="text" name="identity_number" id="identity_number"
                                    placeholder="مثال: 123456789123" />
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-times"></i> تاريخ انتهاء الهوية</label>
                                <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-camera"></i> صورة السائق ( تحت التجهيز)</label>
                                <input type="text" name="employee_photo" id="employee_photo"
                                    placeholder="سيتم تفعيل رفع صورة السائق لاحقاً" readonly />
                            </div>
                            <div>
                                <label><i class="fas fa-id-card"></i> صورة هوية السائق ( تحت التجهيز)</label>
                                <input type="text" name="identity_photo" id="identity_photo"
                                    placeholder="سيتم تفعيل رفع صورة الهوية لاحقاً" readonly />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- حقول السائق/المشغّل (الرخصة والمعدات المتخصّصة) نُقِلت إلى صفحة «السائقون والمشغّلون» -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-id-card-clip"></i>
                        <span>رخصة القيادة وبيانات التشغيل</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <p style="margin:6px 0;color:#555;line-height:1.9;">
                            <i class="fas fa-circle-info"></i>
                            بيانات رخصة القيادة والمعدات المتخصّصة باتت تُدار من صفحة
                            <a href="equipment_operators.php" target="_blank"><strong>السائقون والمشغّلون</strong></a>
                            (لكل موظفٍ سائق/مشغّل سجلٌّ واحد، فلا تتكرّر البيانات).
                            احفظ الموظف هنا أولاً، ثم سجّله سائقاً/مشغّلاً وأدخِل رخصته هناك.
                        </p>
                    </div>
                </div>

                <!-- 5. سنوات الخبرة والكفاءة -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-medal"></i>
                        <span>سنوات الخبرة والكفاءة</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-briefcase"></i> سنوات العمل في المجال</label>
                                <input type="number" name="years_in_field" id="years_in_field" placeholder="مثال: 8"
                                    min="0" max="50" />
                            </div>
                            <div>
                                <label><i class="fas fa-wrench"></i> سنوات العمل على هذه المعدات</label>
                                <input type="number" name="years_on_equipment" id="years_on_equipment"
                                    placeholder="مثال: 5" min="0" max="50" />
                            </div>
                            <div>
                                <label><i class="fas fa-star"></i> مستوى الكفاءة المهنية</label>
                                <select name="skill_level" id="skill_level">
                                    <option value="">-- اختر المستوى --</option>
                                    <option value="مبتدئ (أقل من سنة)">مبتدئ (أقل من سنة)</option>
                                    <option value="متدرب (1-2 سنة)">متدرب (1-2 سنة)</option>
                                    <option value="كفء (3-5 سنوات)">كفء (3-5 سنوات)</option>
                                    <option value="خبير (5-10 سنوات)">خبير (5-10 سنوات)</option>
                                    <option value="سيد حرفة (أكثر من 10 سنوات)">سيد حرفة (أكثر من 10 سنوات)</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-graduation-cap"></i> الشهادات والتدريبات</label>
                                <textarea name="certificates" id="certificates" rows="3"
                                    placeholder="مثال: شهادة تشغيل حفارات من معهد التعدين، دورة السلامة الصناعية"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. علاقة العمل والتبعية -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-users"></i>
                        <span>علاقة العمل والتبعية</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user-tie"></i> اسم المالك/المشرف المباشر</label>
                                <input type="text" name="owner_supervisor" id="owner_supervisor"
                                    placeholder="مثال: محمد علي (مالك المعدة)" />
                            </div>
                            <div>
                                <label><i class="fas fa-building"></i> المورد الذي يعمل معه</label>
                                <select name="supplier_id" id="supplier_id">
                                    <option value="">-- اختر المورد --</option>
                                    <?php
                                    // موردو الشركة عبر البوابة
                                    foreach ($emp_gate->select('suppliers', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC')) as $supplier) {
                                        echo "<option value='" . intval($supplier['id']) . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-project-diagram"></i> المشروع المرتبط</label>
                                <select name="project_id" id="project_id">
                                    <option value="">-- اختر المشروع --</option>
                                    <?php
                                    // مشاريع الشركة عبر البوابة
                                    $emp_projects = $emp_gate->select('project', array('columns' => array('id', 'name', 'project_code'), 'whereRaw' => 'status = 1', 'orderBy' => 'name ASC'));
                                    { foreach ($emp_projects as $project) {
                                        $project_display = htmlspecialchars($project['name']);
                                        if (!empty($project['project_code'])) {
                                            $project_display .= " (" . htmlspecialchars($project['project_code']) . ")";
                                        }
                                        echo "<option value='" . $project['id'] . "'>" . $project_display . "</option>";
                                    } }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-sitemap"></i> تبعية المشغل</label>
                                <select name="employment_affiliation" id="employment_affiliation">
                                    <option value="">-- اختر التبعية --</option>
                                    <option value="تابع لمالك المعدة مباشرة">تابع لمالك المعدة مباشرة</option>
                                    <option value="تابع للمورد/الوسيط">تابع للمورد/الوسيط</option>
                                    <option value="تابع لشركة متخصصة في التشغيل">تابع لشركة متخصصة في التشغيل</option>
                                    <option value="مقاول مستقل">مقاول مستقل</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-money-bill-wave"></i> نوع الراتب/الأجر</label>
                                <select name="salary_type" id="salary_type">
                                    <option value="">-- اختر النوع --</option>
                                    <option value="يومي">يومي</option>
                                    <option value="أسبوعي">أسبوعي</option>
                                    <option value="شهري">شهري</option>
                                    <option value="حسب الإنتاجية">حسب الإنتاجية</option>
                                    <option value="حسب المشروع">حسب المشروع</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-dollar-sign"></i> المبلغ الشهري التقريبي</label>
                                <input type="number" step="0.01" name="monthly_salary" id="monthly_salary"
                                    placeholder="مثال: 1500" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. البيانات التواصلية -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-address-book"></i>
                        <span>البيانات التواصلية</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                                <input type="email" name="email" id="email" placeholder="operator@example.com" />
                            </div>
                            <div>
                                <label><i class="fas fa-phone"></i> رقم الهاتف الأساسي <span
                                        style="color: red;">*</span></label>
                                <input type="tel" name="phone" id="phone" placeholder="+249-9-123-4567" required />
                            </div>
                            <div>
                                <label><i class="fas fa-phone-alt"></i> رقم هاتف بديل</label>
                                <input type="tel" name="phone_alternative" id="phone_alternative"
                                    placeholder="+249-9-765-4321" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-map-marker-alt"></i> العنوان</label>
                                <textarea name="address" id="address" rows="2"
                                    placeholder="مثال: شارع النيل، الخرطوم"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8. تقييم الأداء والسلوك -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-star-half-alt"></i>
                        <span>تقييم الأداء والسلوك</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-chart-line"></i> تقييم الكفاءة التشغيلية</label>
                                <select name="performance_rating" id="performance_rating">
                                    <option value="">-- اختر التقييم --</option>
                                    <option value="ممتاز">⭐⭐⭐⭐⭐ ممتاز</option>
                                    <option value="جيد جداً">⭐⭐⭐⭐ جيد جداً</option>
                                    <option value="جيد">⭐⭐⭐ جيد</option>
                                    <option value="مقبول">⭐⭐ مقبول</option>
                                    <option value="ضعيف">⭐ ضعيف</option>
                                    <option value="غير محدد">غير محدد</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-user-check"></i> سجل السلوك والانضباط</label>
                                <select name="behavior_record" id="behavior_record">
                                    <option value="">-- اختر السجل --</option>
                                    <option value="ممتاز (لا توجد شكاوى)">✅ ممتاز (لا توجد شكاوى)</option>
                                    <option value="جيد (شكاوى نادرة)">ðŸ‘ جيد (شكاوى نادرة)</option>
                                    <option value="مقبول (بعض الشكاوى)">⚠️ مقبول (بعض الشكاوى)</option>
                                    <option value="ضعيف (شكاوى متكررة)">❌ ضعيف (شكاوى متكررة)</option>
                                    <option value="غير محدد">غير محدد</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-exclamation-triangle"></i> سجل الحوادث والأعطال</label>
                                <select name="accident_record" id="accident_record">
                                    <option value="">-- اختر السجل --</option>
                                    <option value="نظيف (لا توجد حوادث)">✅ نظيف (لا توجد حوادث)</option>
                                    <option value="حادث واحد (طفيف)">⚠️ حادث واحد (طفيف)</option>
                                    <option value="حادثان (متوسط)">ðŸš¨ حادثان (متوسط)</option>
                                    <option value="ثلاثة حوادث فأكثر (خطير)">☠️ ثلاثة حوادث فأكثر (خطير)</option>
                                    <option value="غير محدد">غير محدد</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 9. الصحة والسلامة -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-heartbeat"></i>
                        <span>الصحة والسلامة</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-heart"></i> الحالة الصحية</label>
                                <select name="health_status" id="health_status">
                                    <option value="">-- اختر الحالة --</option>
                                    <option value="سليم تماماً">✅ سليم تماماً</option>
                                    <option value="بحالة جيدة">ðŸ‘ بحالة جيدة</option>
                                    <option value="بحالة مقبولة">⚠️ بحالة مقبولة</option>
                                    <option value="محتاج متابعة طبية">ðŸ¥ محتاج متابعة طبية</option>
                                    <option value="غير محدد">غير محدد</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-syringe"></i> التطعيمات والفحوصات</label>
                                <select name="vaccinations_status" id="vaccinations_status">
                                    <option value="">-- اختر الحالة --</option>
                                    <option value="محدثة">✅ محدثة</option>
                                    <option value="قديمة">⏰ قديمة</option>
                                    <option value="لا يوجد فحص">❌ لا يوجد فحص</option>
                                    <option value="قيد الفحص">⏳ قيد الفحص</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-notes-medical"></i> المشاكل الصحية المعروفة</label>
                                <textarea name="health_issues" id="health_issues" rows="2"
                                    placeholder="مثال: ضعف البصر الطفيف، مشاكل الظهر"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 10. المراجع والسجل -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-history"></i>
                        <span>المراجع والسجل</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-building"></i> اسم جهة التوظيف السابقة</label>
                                <input type="text" name="previous_employer" id="previous_employer"
                                    placeholder="مثال: شركة الذهب للتعدين" />
                            </div>
                            <div>
                                <label><i class="fas fa-clock"></i> مدة العمل معهم</label>
                                <input type="text" name="employment_duration" id="employment_duration"
                                    placeholder="مثال: 3 سنوات" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-user-friends"></i> مرجع للاتصال</label>
                                <input type="text" name="reference_contact" id="reference_contact"
                                    placeholder="مثال: محمود أحمد - مدير الأسطول (09-123-4567)" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-comment-dots"></i> ملاحظات عامة</label>
                                <textarea name="general_notes" id="general_notes" rows="3"
                                    placeholder="مثال: مشغل موثوق وذو كفاءة عالية، يحتاج إلى تدريب على السلامة"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 11. الحالة والتفعيل -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-toggle-on"></i>
                        <span>الحالة والتفعيل</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user-tag"></i> تصنيف الموظف</label>
                                <select name="employment_classification" id="employment_classification">
                                    <option value="">-- اختر التصنيف --</option>
                                    <option value="مرشح">📝 مرشح</option>
                                    <option value="متدرب">🎓 متدرب</option>
                                    <option value="مقبول">✅ مقبول</option>
                                    <option value="مستقيل">🚪 مستقيل</option>
                                    <option value="مفصول">⛔ مفصول</option>
                                </select>
                                <small style="color:#666; display:block; margin-top:5px;">
                                    <i class="fas fa-info-circle"></i>
                                    مسار التوظيف من التقديم إلى الاعتماد — مستقلٌّ عن حالة المشغل التشغيلية
                                </small>
                            </div>
                            <div>
                                <label><i class="fas fa-info-circle"></i> حالة المشغل <span
                                        style="color: red;">*</span></label>
                                <select name="employee_status" id="employee_status" required>
                                    <option value="">-- اختر الحالة --</option>
                                    <option value="نشط"> نشط</option>
                                    <option value="معلق">⏸️ معلق</option>
                                    <option value="مفصول"> مفصول</option>
                                    <option value="في إجازة"> في إجازة</option>
                                    <option value="تحت التقييم">⏳ تحت التقييم</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-check"></i> تاريخ البدء الفعلي</label>
                                <input type="date" name="start_date" id="start_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-power-off"></i> حالة النظام <span
                                        style="color: red;">*</span></label>
                                <select name="status" id="status" required>
                                    <option value="">-- اختر الحالة --</option>
                                    <option value="1">✅ مفعل</option>
                                    <option value="0">❌ موقف</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pu-form-actions">
                    <button type="button" class="btn-submit" onclick="expandAllSections()">
                        <i class="fas fa-expand-alt"></i> فتح جميع المجموعات
                    </button>
                    <button type="button" class="btn-submit" onclick="collapseAllSections()">
                        <i class="fas fa-compress-alt"></i> إغلاق جميع المجموعات
                    </button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> حفظ المشغل
                    </button>
                    <button type="button" class="btn-cancel"
                        onclick="document.getElementById('projectForm').classList.remove('allforms-visible');">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- لوحة الفلاتر الموحّدة (نفس تصميم شاشة العملاء) — تُملأ خياراتها ديناميكياً من بيانات الجدول -->
    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label><i class="fa fa-user-tag"></i> نوع الموظف</label>
                <select id="empFilterType" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-id-badge"></i> المسمى الوظيفي</label>
                <select id="empFilterJobTitle" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-people-arrows"></i> دور الموظف</label>
                <select id="empFilterEmpRole" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-star"></i> مستوى المهارة</label>
                <select id="empFilterSkill" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-briefcase"></i> جهة التوظيف</label>
                <select id="empFilterAffiliation" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-money-bill-wave"></i> نوع الراتب</label>
                <select id="empFilterSalaryType" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-truck-field"></i> المورد</label>
                <select id="empFilterSupplier" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-folder-open"></i> المشروع</label>
                <select id="empFilterProject" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-user-tag"></i> التصنيف</label>
                <select id="empFilterEmpClass" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-user-check"></i> حالة الموظف</label>
                <select id="empFilterEmpStatus" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-toggle-on"></i> الحالة</label>
                <select id="empFilterStatus" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-ok" id="empFilterApply"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" id="empFilterReset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-scroll-wrap">
            <table id="driversTable" class="display nowrap no-datatable">
                <thead>
                    <tr>
                        <th>الإجراءات</th>
                        <th>#</th>
                        <th>كود الموظف</th>
                        <th>نوع العقد</th>
                        <th>اسم الموظف</th>
                        <th>المورد</th>
                        <th>المشروع</th>
                        <th>عدد العقود</th>
                        <th>التصنيف</th>
                        <th>حالة الحساب</th>
                    
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                        <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                        </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب المشغلين مع البيانات الإضافية — العزل عبر {TENANT_SCOPE}
                    // (سُلَّم created_by الاحتياطي أُزيل؛ عدّاد العقود الفرعي يُعلَن جدولُه)
                    $emp_rows = $emp_gate->scopedQuery(array(
                        'scope'  => array('d' => 'employees'),
                        'enrich' => array('s' => 'suppliers', 'p' => 'project', 'jt' => 'job_titles', 'er' => 'employee_roles', 'drivercontracts' => 'drivercontracts'),
                    ), "SELECT d.*, s.name as supplier_name, p.name as project_name, p.project_code,
                             jt.name AS job_title_name, er.name AS employee_role_name,
                             (SELECT COUNT(*) FROM drivercontracts WHERE employee_id = d.id) as numcontracts
                             FROM employees d
                             LEFT JOIN suppliers s ON d.supplier_id = s.id
                             LEFT JOIN project p ON d.project_id = p.id
                             LEFT JOIN job_titles jt ON jt.id = d.job_title_id
                             LEFT JOIN employee_roles er ON er.id = d.employee_role_id
                             WHERE {TENANT_SCOPE}
                             ORDER BY d.id DESC", array());
                    $i = 1;

                    { foreach ($emp_rows as $row) {
                        $statusBadge = $row['status'] == "1" ? '<span class="status-pill status-active">✅ مفعّل</span>' : '<span class="status-pill status-inactive">❌ موقف</span>';
                        $driver_name_cell = "<a class='client-name-link' href='employee_profile.php?id=" . intval($row['id']) . "'><strong>" . htmlspecialchars($row['name']) . "</strong></a>";
                        if (intval($row['numcontracts']) === 0) {
                            $driver_name_cell .= " <span class='link-alert-chip' title='المشغل ليس لديه عقد'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                        }

                        // بناء خلية الإجراءات أولاً لوضعها كأول عمود في الجدول
                        $actions_cell = "<div class='action-btns'>";
                        if ($can_edit) {
                            $actions_cell .= "<a href='javascript:void(0)'
                                       class='action-btn edit editBtn'
                                       data-id='" . $row['id'] . "'
                                       title='تعديل'>
                                        <i class='fas fa-edit'></i>
                                    </a>";
                        }
                        $actions_cell .= "<a href='employee_contracts.php?id=" . $row['id'] . "'
                                       class='action-btn view'
                                       title='عرض العقود'>
                                        <i class='fas fa-file-contract'></i>
                                    </a>
                                    <a href='employee_profile.php?id=" . $row['id'] . "'
                                       class='action-btn view'
                                       title='بطاقة وبيانات السائق'>
                                        <i class='fas fa-id-card-alt'></i>
                                    </a>
                                    <a href='employee_equipment_history.php?id=" . $row['id'] . "'
                                       class='action-btn history'
                                       title='تاريخ القيادة'>
                                        <i class='fas fa-history'></i>
                                    </a>";
                        if ($can_delete) {
                            $actions_cell .= "<a href='employees.php?delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من حذف المشغل؟\")' title='حذف'>
                                        <i class='fas fa-trash'></i>
                                    </a>";
                        }
                        $actions_cell .= "</div>";

                        $project_display = '-';
                        if (!empty($row['project_name'])) {
                            $project_display = htmlspecialchars($row['project_name']);
                            if (!empty($row['project_code'])) {
                                $project_display .= " <code style='font-size:0.7rem;'>" . htmlspecialchars($row['project_code']) . "</code>";
                            }
                        }

                        // شارة تصنيف مسار التوظيف — ألوانها تتبع دلالة المرحلة:
                        // قيد المسار (رمادي/أزرق) · معتمد (أخضر) · منتهٍ (أحمر).
                        $cls_value = (string) ($row['employment_classification'] ?? '');
                        $cls_map = array(
                            'مرشح'   => array('#eef2ff', '#3730a3', '📝'),
                            'متدرب'  => array('#fff7ed', '#9a3412', '🎓'),
                            'مقبول'  => array('#ecfdf5', '#065f46', '✅'),
                            'مستقيل' => array('#f3f4f6', '#4b5563', '🚪'),
                            'مفصول'  => array('#fef2f2', '#991b1b', '⛔'),
                        );
                        if ($cls_value !== '' && isset($cls_map[$cls_value])) {
                            list($cls_bg, $cls_fg, $cls_icon) = $cls_map[$cls_value];
                            $classificationBadge = "<span class='emp-class-pill' style='background:$cls_bg;color:$cls_fg;'>"
                                . $cls_icon . ' ' . htmlspecialchars($cls_value) . '</span>';
                        } else {
                            $classificationBadge = "<span class='emp-class-pill emp-class-none'>— غير مصنّف</span>";
                        }

                        // سمات الصفّ لفلاتر اللوحة الموحّدة (تُقرأ في بحث DataTables المخصّص)
                        $st_label = $row['status'] == "1" ? 'مفعّل' : 'موقف';
                        $row_data = "data-type='"        . htmlspecialchars((string) ($row['employee_type'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-jobtitle='"    . htmlspecialchars((string) ($row['job_title_name'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-emprole='"     . htmlspecialchars((string) ($row['employee_role_name'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-skill='"       . htmlspecialchars((string) ($row['skill_level'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-affiliation='" . htmlspecialchars((string) ($row['employment_affiliation'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-salarytype='"  . htmlspecialchars((string) ($row['salary_type'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-supplier='"    . htmlspecialchars((string) ($row['supplier_name'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-project='"     . htmlspecialchars((string) ($row['project_name'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-empstatus='"   . htmlspecialchars((string) ($row['employee_status'] ?? ''), ENT_QUOTES) . "' "
                                  . "data-empclass='"    . htmlspecialchars($cls_value, ENT_QUOTES) . "' "
                                  . "data-status='"      . htmlspecialchars($st_label, ENT_QUOTES) . "'";

                        echo "<tr $row_data>";
                        echo "<td>" . $actions_cell . "</td>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><code>" . htmlspecialchars($row['employee_code'] ?: 'N/A') . "</code></td>";
                        echo "<td><span class='badge badge-info'>" . htmlspecialchars($row['employee_type'] ?? '-') . "</span></td>";
                        echo "<td>" . $driver_name_cell . "</td>";
                        echo "<td>" . htmlspecialchars($row['supplier_name'] ?: '-') . "</td>";
                        echo "<td>" . $project_display . "</td>";
                        echo "<td><span class='badge badge-info'>" . $row['numcontracts'] . " عقد</span></td>";
                        echo "<td>" . $classificationBadge . "</td>";
                        echo "<td>" . $statusBadge . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

</div>

<!-- jQuery (Required first) -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<!-- <script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script> -->
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    // دالة طي/فتح المجموعات
    function toggleSection(header) {
        const body = header.nextElementSibling;
        header.classList.toggle('collapsed');
        body.classList.toggle('collapsed');
    }

    // فتح جميع المجموعات
    function expandAllSections() {
        document.querySelectorAll('.form-section-header').forEach(header => {
            header.classList.remove('collapsed');
            header.nextElementSibling.classList.remove('collapsed');
        });
    }

    // إغلاق جميع المجموعات
    function collapseAllSections() {
        document.querySelectorAll('.form-section-header').forEach(header => {
            header.classList.add('collapsed');
            header.nextElementSibling.classList.add('collapsed');
        });
    }

    // إظهار/إخفاء الأقسام الخاصة بأنواع التشغيل (op-only) حسب نوع الموظف
    var EMS_OP_TYPES = <?php echo json_encode(ems_operation_employee_types(), JSON_UNESCAPED_UNICODE); ?>;
    function emsToggleEmpType() {
        var sel = document.getElementById('employee_type');
        var v = sel ? sel.value : '';
        var show = (EMS_OP_TYPES.indexOf(v) !== -1) || v === '';
        document.querySelectorAll('#projectForm .op-only').forEach(function (el) {
            el.style.display = show ? '' : 'none';
        });
    }
    document.addEventListener('DOMContentLoaded', emsToggleEmpType);

    (function () {
        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            var empTable = $('#driversTable').DataTable({
                dom: 'Bfrtip',
                scrollX: true,
                scrollCollapse: true,
                buttons: [
                    { extend: 'copy', text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                "language": {
                    "url": "/ems/assets/i18n/datatables/ar.json"
                }
            });

            // ── لوحة الفلاتر الموحّدة: تُملأ خياراتها من بيانات الصفوف ثم تُصفّي عبر بحث مخصّص ──
            var EMP_FILTERS = [
                { key: 'type',        sel: '#empFilterType' },
                { key: 'jobtitle',    sel: '#empFilterJobTitle' },
                { key: 'emprole',     sel: '#empFilterEmpRole' },
                { key: 'skill',       sel: '#empFilterSkill' },
                { key: 'affiliation', sel: '#empFilterAffiliation' },
                { key: 'salarytype',  sel: '#empFilterSalaryType' },
                { key: 'supplier',    sel: '#empFilterSupplier' },
                { key: 'project',     sel: '#empFilterProject' },
                { key: 'empclass',    sel: '#empFilterEmpClass' },
                { key: 'empstatus',   sel: '#empFilterEmpStatus' },
                { key: 'status',      sel: '#empFilterStatus' }
            ];
            // املأ كل قائمة من القيم المتمايزة الموجودة فعلاً في الجدول (نمط شاشة العملاء)
            var $empRows = $(empTable.rows().nodes());
            EMP_FILTERS.forEach(function (f) {
                var $sel = $(f.sel); if (!$sel.length) return;
                var seen = {}, vals = [];
                $empRows.each(function () {
                    var v = ($(this).attr('data-' + f.key) || '').trim();
                    if (v && !seen[v]) { seen[v] = 1; vals.push(v); }
                });
                vals.sort(function (a, b) { return a.localeCompare(b, 'ar'); });
                vals.forEach(function (v) {
                    $sel.append('<option value="' + v.replace(/"/g, '&quot;') + '">' + v + '</option>');
                });
            });
            // بحث DataTables مخصّص يقارن قيم الفلاتر بسمات data على الصفّ (مقيّد بهذا الجدول فقط)
            $.fn.dataTable.ext.search.push(function (settings, dataArr, dataIndex) {
                if (settings.nTable.id !== 'driversTable') return true;
                var node = empTable.row(dataIndex).node();
                if (!node) return true;
                for (var i = 0; i < EMP_FILTERS.length; i++) {
                    var want = $(EMP_FILTERS[i].sel).val();
                    if (want) {
                        var have = ($(node).attr('data-' + EMP_FILTERS[i].key) || '').trim();
                        if (have !== want) return false;
                    }
                }
                return true;
            });
            $('.emp-filter-select').on('change', function () { empTable.draw(); });
            $('#empFilterApply').on('click', function () { empTable.draw(); });
            $('#empFilterReset').on('click', function () {
                EMP_FILTERS.forEach(function (f) { $(f.sel).val(''); });
                empTable.draw();
            });
        });

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');

        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.classList.toggle('allforms-visible');

                if (projectForm.classList.contains('allforms-visible')) {
                    // تنظيف جميع الحقول
                    projectForm.reset();
                    $("#drivers_id").val("");
                    emsToggleEmpType();
                    // فتح المجموعة الأولى فقط
                    collapseAllSections();
                    document.querySelector('.form-section-header').classList.remove('collapsed');
                    document.querySelector('.form-section-body').classList.remove('collapsed');

                    // التمرير للفورم
                    $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                    $("#name").focus();
                }
            });
        }

        // عند الضغط على زر تعديل - تحميل البيانات عبر AJAX
        $(document).on("click", ".editBtn", function () {
            const id = $(this).data("id");

            // جلب البيانات الكاملة عبر AJAX
            $.ajax({
                url: 'get_employee_data.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        const driver = data.driver;

                        // ملء جميع الحقول
                        $("#drivers_id").val(driver.id);
                        $("#name").val(driver.name);
                        $("#employee_code").val(driver.employee_code);
                        $("#nickname").val(driver.nickname);
                        // الحقول الجديدة لسجل الموظفين
                        $("#employee_type").val(driver.employee_type || 'سائق/مشغّل');
                        $("#job_title_id").val(driver.job_title_id || '');
                        $("#employee_role_id").val(driver.employee_role_id || '');
                        $("#birth_date").val(driver.birth_date || '');
                        $("#nationality").val(driver.nationality || '');
                        $("#blood_type").val(driver.blood_type || '');
                        $("#whatsapp").val(driver.whatsapp || '');
                        $("#emergency_contact_name").val(driver.emergency_contact_name || '');
                        $("#emergency_contact_relation").val(driver.emergency_contact_relation || '');
                        $("#emergency_contact_phone").val(driver.emergency_contact_phone || '');
                        $("#license_issue_date").val(driver.license_issue_date || '');
                        $("#license_grade").val(driver.license_grade || '');
                        $("#identity_type").val(driver.identity_type);
                        $("#identity_number").val(driver.identity_number);
                        $("#identity_expiry_date").val(driver.identity_expiry_date);
                        $("#employee_photo").val(driver.employee_photo || '');
                        $("#identity_photo").val(driver.identity_photo || '');
                        $("#license_number").val(driver.license_number);
                        $("#license_type").val(driver.license_type);
                        $("#license_expiry_date").val(driver.license_expiry_date);
                        $("#license_issuer").val(driver.license_issuer);

                        // المعدات المتخصصة (checkboxes)
                        const equipment = driver.specialized_equipment ? driver.specialized_equipment.split(', ') : [];
                        $("input[name='specialized_equipment[]']").prop("checked", false);
                        equipment.forEach(function (eq) {
                            $("input[name='specialized_equipment[]'][value='" + eq.trim() + "']").prop("checked", true);
                        });

                        $("#years_in_field").val(driver.years_in_field);
                        $("#years_on_equipment").val(driver.years_on_equipment);
                        $("#skill_level").val(driver.skill_level);
                        $("#certificates").val(driver.certificates);
                        $("#owner_supervisor").val(driver.owner_supervisor);
                        $("#supplier_id").val(driver.supplier_id);
                        $("#project_id").val(driver.project_id);
                        $("#employment_affiliation").val(driver.employment_affiliation);
                        $("#salary_type").val(driver.salary_type);
                        $("#monthly_salary").val(driver.monthly_salary);
                        $("#email").val(driver.email);
                        $("#phone").val(driver.phone);
                        $("#phone_alternative").val(driver.phone_alternative);
                        $("#address").val(driver.address);
                        $("#performance_rating").val(driver.performance_rating);
                        $("#behavior_record").val(driver.behavior_record);
                        $("#accident_record").val(driver.accident_record);
                        $("#health_status").val(driver.health_status);
                        $("#health_issues").val(driver.health_issues);
                        $("#vaccinations_status").val(driver.vaccinations_status);
                        $("#previous_employer").val(driver.previous_employer);
                        $("#employment_duration").val(driver.employment_duration);
                        $("#reference_contact").val(driver.reference_contact);
                        $("#general_notes").val(driver.general_notes);
                        $("#employee_status").val(driver.employee_status);
                        $("#employment_classification").val(driver.employment_classification || "");
                        $("#start_date").val(driver.start_date);
                        $("#status").val(driver.status);

                        // عرض الفورم وفتح جميع المجموعات
                        projectForm.classList.add('allforms-visible');
                        expandAllSections();
                        emsToggleEmpType();
                        $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                    } else {
                        alert('❌ خطأ في تحميل البيانات: ' + (data.message || 'سبب غير معروف'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    let errorMsg = 'حدث خطأ في الاتصال بالخادم';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        errorMsg += ' (Status: ' + status + ')';
                    }
                    alert('❌ ' + errorMsg);
                }
            });
        });

    })();

    // الاستيراد القديم أُزيل — يتولّاه الآن معالج إطار Excel الموحّد (assets/js/ems-excel.js).
</script>
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>

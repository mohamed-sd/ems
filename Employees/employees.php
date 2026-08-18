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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المشغلين ❌', 'GOV-PERM-403', '');
    exit();
}

$page_title = "إيكوبيشن | الموظفون";

// معالجة إضافة/تعديل مشغل عند إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    // التحقق من الصلاحية (إضافة أو تعديل)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) {
        ems_gov_flash_redirect('employees.php', 'لا توجد صلاحية تعديل المشغلين ❌', 'GOV-PERM-403', '');
        exit();
    } elseif (!$is_editing && !$can_add) {
        ems_gov_flash_redirect('employees.php', 'لا توجد صلاحية إضافة مشغلين جدد ❌', 'GOV-PERM-403', '');
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

    /* ══ INJ-0004 (P0) — حقولُ الأجورِ **لا تُكتب إلا بمنحٍ صريح** ═══════════
       ◆ گوتشا خطيرةٌ يفتحها الحجبُ نفسُه: بعدَ حجبِ الراتبِ عن غيرِ المخوَّل
         يصل نموذجُ التعديلِ بحقلٍ فارغ — فلو أُدرج في التحديثِ كما كان لمَحا
         الراتبَ الحقيقيَّ بـNULL صامتًا. أي أن الحمايةَ نفسَها تصير محوًا.
       ◆ فالحقلُ يُدرج في الحمولةِ **فقط** حين يملك الفاعلُ منحةَ الأجور؛ ولغيرِه
         يُترك خارجَها فيبقى المخزَّنُ كما هو (لا يُقرأ ولا يُكتب). */
    $emp_payroll_allowed = false;
    try {
        require_once __DIR__ . '/../app/Services/Portal/VisibilityGuard.php';
        if (class_exists('\\App\\Services\\Portal\\VisibilityGuard')) {
            $emp_v = \App\Services\Portal\VisibilityGuard::check(
                $conn, $emp_gate, intval($_SESSION['user']['company_id'] ?? 0),
                array('account_id' => intval($_SESSION['user']['id'] ?? 0),
                      'role' => strval($_SESSION['user']['role'] ?? ''),
                      'capacity_type' => 'employee', 'scope_type' => '', 'scope_id' => null),
                'card.payroll', array('account_id' => $id));
            $emp_payroll_allowed = (($emp_v['decision'] ?? '') === 'allow');
        }
    } catch (\Throwable $t) {
        error_log('employees.php payroll guard: ' . $t->getMessage());  // فشلٌ مغلق
    }
    if ($emp_payroll_allowed) {
        $data['salary_type']    = $_POST['salary_type'] ?? null;
        $data['monthly_salary'] = !empty($_POST['monthly_salary']) ? floatval($_POST['monthly_salary']) : null;
        require_once __DIR__ . '/../includes/sensitive_read_log.php';
        ems_log_sensitive_read($conn, 'salary', 'employee:' . $id, 'Employees/employees.php');
    }

    // التحقق من فرادة كود المشغل على مستوى الشركة (البوابة تعزل تلقائيًّا)
    if (!empty($employee_code)) {
        $dupWhere  = "employee_code = ?";
        $dupParams = array($employee_code);
        if ($id > 0) { $dupWhere .= " AND id != ?"; $dupParams[] = $id; }
        $dup = $emp_gate->selectOne('employees', array('columns' => array('id'), 'whereRaw' => $dupWhere, 'params' => $dupParams));
        if ($dup) {
            ems_gov_flash_redirect('employees.php', 'كود المشغل موجود مسبقاً ❌', 'GOV-FAIL-409', '');
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
        ems_gov_flash_redirect('employees.php', "$ok_msg", 'GOV-INFO-200', '');
        exit;
    } catch (\Throwable $e) {
        ems_gov_flash_redirect('employees.php', "حدث خطأ أثناء الحفظ ❌: " . $e->getMessage(), 'GOV-FAIL-409', '');
        exit;
    }
}

if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        ems_gov_flash_redirect('employees.php', 'لا توجد صلاحية حذف المشغلين ❌', 'GOV-PERM-403', '');
        exit();
    }

    $delete_id = intval($_GET['delete_id']);
    if ($delete_id <= 0) {
        ems_gov_flash_redirect('employees.php', 'معرف المشغل غير صحيح ❌', 'GOV-REF-404', '');
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
        ems_gov_flash_redirect('employees.php', 'لا يمكن حذف المشغل لارتباطه بعقود أو تشغيل نشط ❌', 'GOV-FAIL-409', '');
        exit();
    }

    // حذفٌ صلبٌ كالأصل عبر قناة deleteRow (القدرة الثامنة): كيانٌ بلا أبٍ إلزاميّ —
    // مقيَّدٌ بشركة السياق ومُسجَّل (البوابة ترفض DELETE الخام).
    try {
        if ($emp_gate->deleteRow('employees', $delete_id, 'employees screen delete') > 0) {
            ems_gov_flash_redirect('employees.php', 'تم حذف المشغل بنجاح ✅', 'GOV-OK-200', '');
            exit();
        }
    } catch (\Throwable $e) { /* يسقط لرسالة التعذّر */ }

    ems_gov_flash_redirect('employees.php', 'تعذر حذف المشغل أو أنه خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
    exit();
}

// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/ems.main.all.style.css">

<?php
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا موظفين مسجَّلين ضمن نطاقك بعدُ',
        'أضف موظفًا جديدًا من زرِّ «إضافة موظف جديد» أو استوردهم من معالجِ Excel الموحَّد');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('employee', 'نظرةٌ عامة'); ?>

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
        <?= csrf_field() ?>
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
                                <label for="name"><i class="fas fa-user"></i> اسم المشغل/السائق <span class="emp-1">*</span></label>
                                <input type="text" name="name" id="name" placeholder="مثال: محمد أحمد علي" required />
                            </div>
                            <div>
                                <label for="employee_code"><i class="fas fa-barcode"></i> الرمز/الكود الفريد</label>
                                <input type="text" name="employee_code" id="employee_code"
                                    placeholder="مثال: OPR-001-2026" />
                            </div>
                            <div>
                                <label for="nickname"><i class="fas fa-signature"></i> اسم الشهرة/الكنية</label>
                                <input type="text" name="nickname" id="nickname" placeholder="مثال: أبو محمد" />
                            </div>
                            <div>
                                <label for="employee_type"><i class="fas fa-user-tag"></i> نوع الموظف <span class="emp-1">*</span></label>
                                <select name="employee_type" id="employee_type" onchange="emsToggleEmpType()" required>
                                    <?php foreach (ems_employee_types() as $__et) {
                                        echo '<option value="' . htmlspecialchars($__et, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($__et, ENT_QUOTES, 'UTF-8') . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div>
                                <label for="job_title_id"><i class="fas fa-id-badge"></i> المسمى الوظيفي
                                    <a href="job_titles.php" target="_blank" title="إدارة المسميات" class="emp-2"><i class="fas fa-gear"></i></a>
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
                                <label for="employee_role_id"><i class="fas fa-people-arrows"></i> دور الموظف
                                    <a href="employee_roles.php" target="_blank" title="إدارة الأدوار" class="emp-2"><i class="fas fa-gear"></i></a>
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
                                <label for="birth_date"><i class="fas fa-calendar-day"></i> تاريخ الميلاد</label>
                                <input type="date" name="birth_date" id="birth_date" />
                            </div>
                            <div>
                                <label for="nationality"><i class="fas fa-flag"></i> الجنسية</label>
                                <input type="text" name="nationality" id="nationality" placeholder="مثال: سوداني" />
                            </div>
                            <div>
                                <label for="blood_type"><i class="fas fa-droplet"></i> فصيلة الدم</label>
                                <input type="text" name="blood_type" id="blood_type" placeholder="مثال: O+" />
                            </div>
                            <div>
                                <label for="whatsapp"><i class="fab fa-whatsapp"></i> واتساب</label>
                                <input type="text" name="whatsapp" id="whatsapp" placeholder="مثال: +249912345678" />
                            </div>
                            <div>
                                <label for="emergency_contact_name"><i class="fas fa-user-shield"></i> جهة الطوارئ (الاسم)</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name" />
                            </div>
                            <div>
                                <label for="emergency_contact_relation"><i class="fas fa-people-arrows"></i> صلة جهة الطوارئ</label>
                                <input type="text" name="emergency_contact_relation" id="emergency_contact_relation" placeholder="مثال: أخ" />
                            </div>
                            <div>
                                <label for="emergency_contact_phone"><i class="fas fa-phone-volume"></i> هاتف الطوارئ</label>
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
                                <label for="identity_type"><i class="fas fa-address-card"></i> نوع الهوية</label>
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
                                <label for="identity_number"><i class="fas fa-hashtag"></i> رقم الهوية</label>
                                <input type="text" name="identity_number" id="identity_number"
                                    placeholder="مثال: 123456789123" />
                            </div>
                            <div>
                                <label for="identity_expiry_date"><i class="fas fa-calendar-times"></i> تاريخ انتهاء الهوية</label>
                                <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                            </div>
                            <div>
                                <label for="employee_photo"><i class="fas fa-camera"></i> صورة السائق ( تحت التجهيز)</label>
                                <input type="text" name="employee_photo" id="employee_photo"
                                    placeholder="سيتم تفعيل رفع صورة السائق لاحقاً" readonly />
                            </div>
                            <div>
                                <label for="identity_photo"><i class="fas fa-id-card"></i> صورة هوية السائق ( تحت التجهيز)</label>
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
                        <p class="emp-3">
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
                                <label for="years_in_field"><i class="fas fa-briefcase"></i> سنوات العمل في المجال</label>
                                <input type="number" name="years_in_field" id="years_in_field" placeholder="مثال: 8"
                                    min="0" max="50" />
                            </div>
                            <div>
                                <label for="years_on_equipment"><i class="fas fa-wrench"></i> سنوات العمل على هذه المعدات</label>
                                <input type="number" name="years_on_equipment" id="years_on_equipment"
                                    placeholder="مثال: 5" min="0" max="50" />
                            </div>
                            <div>
                                <label for="skill_level"><i class="fas fa-star"></i> مستوى الكفاءة المهنية</label>
                                <select name="skill_level" id="skill_level">
                                    <option value="">-- اختر المستوى --</option>
                                    <option value="مبتدئ (أقل من سنة)">مبتدئ (أقل من سنة)</option>
                                    <option value="متدرب (1-2 سنة)">متدرب (1-2 سنة)</option>
                                    <option value="كفء (3-5 سنوات)">كفء (3-5 سنوات)</option>
                                    <option value="خبير (5-10 سنوات)">خبير (5-10 سنوات)</option>
                                    <option value="سيد حرفة (أكثر من 10 سنوات)">سيد حرفة (أكثر من 10 سنوات)</option>
                                </select>
                            </div>
                            <div class="emp-4">
                                <label for="certificates"><i class="fas fa-graduation-cap"></i> الشهادات والتدريبات</label>
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
                                <label for="owner_supervisor"><i class="fas fa-user-tie"></i> اسم المالك/المشرف المباشر</label>
                                <input type="text" name="owner_supervisor" id="owner_supervisor"
                                    placeholder="مثال: محمد علي (مالك المعدة)" />
                            </div>
                            <div>
                                <label for="supplier_id"><i class="fas fa-building"></i> المورد الذي يعمل معه</label>
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
                                <label for="project_id"><i class="fas fa-project-diagram"></i> المشروع المرتبط</label>
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
                                <label for="employment_affiliation"><i class="fas fa-sitemap"></i> تبعية المشغل</label>
                                <select name="employment_affiliation" id="employment_affiliation">
                                    <option value="">-- اختر التبعية --</option>
                                    <option value="تابع لمالك المعدة مباشرة">تابع لمالك المعدة مباشرة</option>
                                    <option value="تابع للمورد/الوسيط">تابع للمورد/الوسيط</option>
                                    <option value="تابع لشركة متخصصة في التشغيل">تابع لشركة متخصصة في التشغيل</option>
                                    <option value="مقاول مستقل">مقاول مستقل</option>
                                </select>
                            </div>
                            <?php
                            /* INJ-0004: قسمُ الأجورِ لا يُصيَّر أصلًا لغيرِ المخوَّل — والحجبُ
                               في الخادمِ فوقَه (الحقلُ لا يُقرأ ولا يُكتب)، فهذا إعلانٌ للمستخدم
                               لا حماية. ◆ ولا يُصيَّر «معطَّلًا» بل يُحذف: الحقلُ المعطَّلُ
                               يُرسَل فارغًا فيُقرأ محوًا. */
                            $emp_form_payroll = false;
                            try {
                                require_once __DIR__ . '/../app/Services/Portal/VisibilityGuard.php';
                                if (class_exists('\\App\\Services\\Portal\\VisibilityGuard')) {
                                    $__fv = \App\Services\Portal\VisibilityGuard::check(
                                        $conn, $emp_gate, intval($_SESSION['user']['company_id'] ?? 0),
                                        array('account_id' => intval($_SESSION['user']['id'] ?? 0),
                                              'role' => strval($_SESSION['user']['role'] ?? ''),
                                              'capacity_type' => 'employee', 'scope_type' => '', 'scope_id' => null),
                                        'card.payroll', array('account_id' => 0));
                                    $emp_form_payroll = (($__fv['decision'] ?? '') === 'allow');
                                }
                            } catch (\Throwable $t) { error_log('employees form payroll guard: ' . $t->getMessage()); }
                            if ($emp_form_payroll): ?>
                            <div>
                                <label for="salary_type"><i class="fas fa-money-bill-wave"></i> نوع الراتب/الأجر</label>
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
                                <label for="monthly_salary"><i class="fas fa-dollar-sign"></i> المبلغ الشهري التقريبي</label>
                                <input type="number" step="0.01" name="monthly_salary" id="monthly_salary"
                                    placeholder="مثال: 1500" />
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning emp-5">
                                <i class="fa fa-lock"></i> قسمُ الأجورِ محجوبٌ — يُفتح بمنحٍ صريحٍ على عنصرِ
                                <code>card.payroll</code> من لوحة الظهور (M-14 · SCN-872).
                            </div>
                            <?php endif; ?>
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
                                <label for="email"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                                <input type="email" name="email" id="email" placeholder="operator@example.com" />
                            </div>
                            <div>
                                <label for="phone"><i class="fas fa-phone"></i> رقم الهاتف الأساسي <span class="emp-1">*</span></label>
                                <input type="tel" name="phone" id="phone" placeholder="+249-9-123-4567" required />
                            </div>
                            <div>
                                <label for="phone_alternative"><i class="fas fa-phone-alt"></i> رقم هاتف بديل</label>
                                <input type="tel" name="phone_alternative" id="phone_alternative"
                                    placeholder="+249-9-765-4321" />
                            </div>
                            <div class="emp-4">
                                <label for="address"><i class="fas fa-map-marker-alt"></i> العنوان</label>
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
                                <label for="performance_rating"><i class="fas fa-chart-line"></i> تقييم الكفاءة التشغيلية</label>
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
                                <label for="behavior_record"><i class="fas fa-user-check"></i> سجل السلوك والانضباط</label>
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
                                <label for="accident_record"><i class="fas fa-exclamation-triangle"></i> سجل الحوادث والأعطال</label>
                                <select name="accident_record" id="accident_record">
                                    <option value="">-- اختر السجل --</option>
                                    <option value="نظيف (لا توجد حوادث)">✅ نظيف (لا توجد حوادث)</option>
                                    <option value="حادث واحد (طفيف)">⚠️ حادث واحد (طفيف)</option>
                                    <option value=" ()">🚨  ()</option>
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
                                <label for="health_status"><i class="fas fa-heart"></i> الحالة الصحية</label>
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
                                <label for="vaccinations_status"><i class="fas fa-syringe"></i> التطعيمات والفحوصات</label>
                                <select name="vaccinations_status" id="vaccinations_status">
                                    <option value="">-- اختر الحالة --</option>
                                    <option value="محدثة">✅ محدثة</option>
                                    <option value="قديمة">⏰ قديمة</option>
                                    <option value="لا يوجد فحص">❌ لا يوجد فحص</option>
                                    <option value="قيد الفحص">⏳ قيد الفحص</option>
                                </select>
                            </div>
                            <div class="emp-4">
                                <label for="health_issues"><i class="fas fa-notes-medical"></i> المشاكل الصحية المعروفة</label>
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
                                <label for="previous_employer"><i class="fas fa-building"></i> اسم جهة التوظيف السابقة</label>
                                <input type="text" name="previous_employer" id="previous_employer"
                                    placeholder="مثال: شركة الذهب للتعدين" />
                            </div>
                            <div>
                                <label for="employment_duration"><i class="fas fa-clock"></i> مدة العمل معهم</label>
                                <input type="text" name="employment_duration" id="employment_duration"
                                    placeholder="مثال: 3 سنوات" />
                            </div>
                            <div class="emp-4">
                                <label for="reference_contact"><i class="fas fa-user-friends"></i> مرجع للاتصال</label>
                                <input type="text" name="reference_contact" id="reference_contact"
                                    placeholder="مثال: محمود أحمد - مدير الأسطول (09-123-4567)" />
                            </div>
                            <div class="emp-4">
                                <label for="general_notes"><i class="fas fa-comment-dots"></i> ملاحظات عامة</label>
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
                                <label for="employment_classification"><i class="fas fa-user-tag"></i> تصنيف الموظف</label>
                                <select name="employment_classification" id="employment_classification">
                                    <option value="">-- اختر التصنيف --</option>
                                    <option value="مرشح">📝 مرشح</option>
                                    <option value="متدرب">🎓 متدرب</option>
                                    <option value="مقبول">✅ مقبول</option>
                                    <option value="مستقيل">🚪 مستقيل</option>
                                    <option value="مفصول">⛔ مفصول</option>
                                </select>
                                <small class="emp-6">
                                    <i class="fas fa-info-circle"></i>
                                    مسار التوظيف من التقديم إلى الاعتماد — مستقلٌّ عن حالة المشغل التشغيلية
                                </small>
                            </div>
                            <div>
                                <label for="employee_status"><i class="fas fa-info-circle"></i> حالة المشغل <span class="emp-1">*</span></label>
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
                                <label for="start_date"><i class="fas fa-calendar-check"></i> تاريخ البدء الفعلي</label>
                                <input type="date" name="start_date" id="start_date" />
                            </div>
                            <div>
                                <label for="status"><i class="fas fa-power-off"></i> حالة النظام <span class="emp-1">*</span></label>
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
                    <button type="button" class="btn-primary" onclick="expandAllSections()">
                        <i class="fas fa-expand-alt"></i> فتح جميع المجموعات
                    </button>
                    <button type="button" class="btn-primary" onclick="collapseAllSections()">
                        <i class="fas fa-compress-alt"></i> إغلاق جميع المجموعات
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> حفظ المشغل
                    </button>
                    <button type="button" class="btn-secondary"
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
                <label for="empFilterType"><i class="fa fa-user-tag"></i> نوع الموظف</label>
                <select id="empFilterType" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterJobTitle"><i class="fa fa-id-badge"></i> المسمى الوظيفي</label>
                <select id="empFilterJobTitle" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterEmpRole"><i class="fa fa-people-arrows"></i> دور الموظف</label>
                <select id="empFilterEmpRole" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterSkill"><i class="fa fa-star"></i> مستوى المهارة</label>
                <select id="empFilterSkill" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterAffiliation"><i class="fa fa-briefcase"></i> جهة التوظيف</label>
                <select id="empFilterAffiliation" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterSalaryType"><i class="fa fa-money-bill-wave"></i> نوع الراتب</label>
                <select id="empFilterSalaryType" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterSupplier"><i class="fa fa-truck-field"></i> المورد</label>
                <select id="empFilterSupplier" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterProject"><i class="fa fa-folder-open"></i> المشروع</label>
                <select id="empFilterProject" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterEmpClass"><i class="fa fa-user-tag"></i> التصنيف</label>
                <select id="empFilterEmpClass" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterEmpStatus"><i class="fa fa-user-check"></i> حالة الموظف</label>
                <select id="empFilterEmpStatus" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-field">
                <label for="empFilterStatus"><i class="fa fa-toggle-on"></i> الحالة</label>
                <select id="empFilterStatus" class="form-control emp-filter-select"><option value="">— الكل —</option></select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-primary" id="empFilterApply"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-secondary" id="empFilterReset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
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
                        <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                        <th class="ems-fn-th" data-fn="1">الاسم الرباعي</th>
                        <th class="ems-fn-th" data-fn="1">رقم الهوية</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ الميلاد</th>
                        <th class="ems-fn-th" data-fn="1">الجنسية</th>
                        <th class="ems-fn-th" data-fn="1">المؤهل</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ التعيين</th>
                        <th class="ems-fn-th" data-fn="1">الإدارة</th>
                        <th class="ems-fn-th" data-fn="1">المسمى الوظيفي</th>
                        <th class="ems-fn-th" data-fn="1">المستوى التنظيمي</th>
                        <th class="ems-fn-th" data-fn="1">الموقع</th>
                        <th class="ems-fn-th" data-fn="1">الحساب البنكي</th>
                        <th class="ems-fn-th" data-fn="1">البنك</th>
                        <th class="ems-fn-th none" data-fn="1">حالة الخدمة</th>
                        <th class="ems-fn-th none" data-fn="1">سجّله</th>
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                        <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                        <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
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
                                $project_display .= " <code data-ems-c='emp-7'>" . htmlspecialchars($row['project_code']) . "</code>";
                            }
                        }

                        // شارة تصنيف مسار التوظيف — ألوانها تتبع دلالة المرحلة:
                        // قيد المسار (رمادي/أزرق) · معتمد (أخضر) · منتهٍ (أحمر).
                        // UXW-01 ①+②: اللونُ بصنفٍ من رموزِ اللوحةِ لا بنمطٍ موضعيٍّ بقيمٍ صلبة.
                        $cls_value = (string) ($row['employment_classification'] ?? '');
                        $cls_map = array(
                            'مرشح'   => array('emp-class-candidate', '📝'),
                            'متدرب'  => array('emp-class-trainee', '🎓'),
                            'مقبول'  => array('emp-class-accepted', '✅'),
                            'مستقيل' => array('emp-class-resigned', '🚪'),
                            'مفصول'  => array('emp-class-dismissed', '⛔'),
                        );
                        if ($cls_value !== '' && isset($cls_map[$cls_value])) {
                            list($cls_class, $cls_icon) = $cls_map[$cls_value];
                            $classificationBadge = "<span class='emp-class-pill $cls_class'>"
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

<style>
    /* UXW-01 ①: شاراتُ تصنيفِ مسارِ التوظيف — الألوانُ نفسُها برموزِ اللوحة */
    .emp-class-candidate { background: var(--c-eef2ff, #eef2ff); color: var(--c-3730a3); }
    .emp-class-trainee { background: var(--c-fff7ed); color: var(--c-9a3412); }
    .emp-class-accepted { background: var(--c-ecfdf5); color: var(--c-065f46); }
    .emp-class-resigned { background: var(--c-f3f4f6); color: var(--c-4b5563); }
    .emp-class-dismissed { background: var(--c-fef2f2); color: var(--c-991b1b); }
</style>
<!-- jQuery (Required first) -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
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
        // UXW-01 ⑤: لا تهيئةَ DataTable محليةً — التهيئةُ للمكوّنِ المركزيِّ وحدَه
        // (assets/js/ui-unification.js — ومعه زرُّ التصدير الموحَّد)؛ وكلُّ ما
        // يعتمد واجهةَ الجدولِ ينتظر تهيئتَه هنا.
        function empWhenTable(cb, tries) {
            if (window.jQuery && $.fn.dataTable && $.fn.dataTable.isDataTable('#driversTable')) {
                cb($('#driversTable').DataTable());
                return;
            }
            tries = (tries === undefined) ? 60 : tries;
            if (tries > 0) { setTimeout(function () { empWhenTable(cb, tries - 1); }, 150); }
        }

        $(document).ready(function () {
            empWhenTable(function (empTable) {

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
                        // INJ-0004: الحقلُ الحساسُ قد يكون محذوفًا من الاستجابةِ أصلًا —
                        // فلا يُملأ إلا إن وصل، وإلا كتب undefined فوقَ قيمةٍ صحيحة.
                        if (driver.salary_type !== undefined) { $("#salary_type").val(driver.salary_type); }
                        if (driver.monthly_salary !== undefined) { $("#monthly_salary").val(driver.monthly_salary); }
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

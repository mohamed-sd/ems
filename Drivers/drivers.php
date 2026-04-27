<?php
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

$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');
$drivercontracts_has_company = db_table_has_column($conn, 'drivercontracts', 'company_id');
$drivers_has_driver_photo = db_table_has_column($conn, 'drivers', 'driver_photo');
$drivers_has_identity_photo = db_table_has_column($conn, 'drivers', 'identity_photo');

if (!$drivers_has_company) {
    @mysqli_query($conn, "ALTER TABLE drivers ADD COLUMN company_id INT NULL AFTER id");
    @mysqli_query($conn, "ALTER TABLE drivers ADD INDEX idx_drivers_company_id (company_id)");
    $drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
}

if (!$drivers_has_driver_photo) {
    @mysqli_query($conn, "ALTER TABLE drivers ADD COLUMN driver_photo VARCHAR(255) NULL AFTER identity_expiry_date");
    $drivers_has_driver_photo = db_table_has_column($conn, 'drivers', 'driver_photo');
}

if (!$drivers_has_identity_photo) {
    @mysqli_query($conn, "ALTER TABLE drivers ADD COLUMN identity_photo VARCHAR(255) NULL AFTER driver_photo");
    $drivers_has_identity_photo = db_table_has_column($conn, 'drivers', 'identity_photo');
}

if (!$is_super_admin && !$drivers_has_company) {
    die('لا يمكن تطبيق العزل التام للمشغلين لأن عمود company_id غير متاح في جدول drivers.');
}

$driver_scope_where = "id = %d";
if (!$is_super_admin) {
    if ($drivers_has_company) {
        $driver_scope_where .= " AND company_id = $company_id";
    } else {
        $driver_scope_where .= " AND EXISTS (
            SELECT 1
            FROM drivercontracts dsc
            INNER JOIN project sp ON sp.id = dsc.project_id
            INNER JOIN users su ON su.id = sp.created_by
            WHERE dsc.driver_id = drivers.id
              AND su.company_id = $company_id
        )";
    }
}

$driver_insert_col = (!$is_super_admin && $drivers_has_company) ? ", company_id" : "";
$driver_insert_val = (!$is_super_admin && $drivers_has_company) ? ", '$company_id'" : "";

// ════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'Drivers/drivers.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن صلاحية عرض
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المشغلين+❌");
    exit();
}

$page_title = "إيكوبيشن | المشغلين";

// معالجة إضافة/تعديل مشغل عند إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    // التحقق من الصلاحية (إضافة أو تعديل)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    
    if ($is_editing && !$can_edit) {
        header("Location: drivers.php?msg=لا+توجد+صلاحية+تعديل+المشغلين+❌");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: drivers.php?msg=لا+توجد+صلاحية+إضافة+مشغلين+جدد+❌");
        exit();
    }
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // 1. المعلومات الأساسية والتعريفية
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $driver_code = mysqli_real_escape_string($conn, trim($_POST['driver_code']));
    $nickname = mysqli_real_escape_string($conn, trim($_POST['nickname']));
    
    // 2. بيانات الهوية والتوثيق
    $identity_type = mysqli_real_escape_string($conn, $_POST['identity_type']);
    $identity_number = mysqli_real_escape_string($conn, trim($_POST['identity_number']));
    $identity_expiry_date = !empty($_POST['identity_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['identity_expiry_date']) : NULL;
    $driver_photo = mysqli_real_escape_string($conn, trim(isset($_POST['driver_photo']) ? $_POST['driver_photo'] : ''));
    $identity_photo = mysqli_real_escape_string($conn, trim(isset($_POST['identity_photo']) ? $_POST['identity_photo'] : ''));
    
    // 3. رخصة القيادة والمهارات
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number']));
    $license_type = mysqli_real_escape_string($conn, $_POST['license_type']);
    $license_expiry_date = !empty($_POST['license_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['license_expiry_date']) : NULL;
    $license_issuer = mysqli_real_escape_string($conn, trim($_POST['license_issuer']));
    
    // 4. التخصص والمهارات
    $specialized_equipment = isset($_POST['specialized_equipment']) ? implode(', ', $_POST['specialized_equipment']) : '';
    $specialized_equipment = mysqli_real_escape_string($conn, $specialized_equipment);
    
    // 5. سنوات الخبرة والكفاءة
    $years_in_field = !empty($_POST['years_in_field']) ? intval($_POST['years_in_field']) : NULL;
    $years_on_equipment = !empty($_POST['years_on_equipment']) ? intval($_POST['years_on_equipment']) : NULL;
    $skill_level = mysqli_real_escape_string($conn, $_POST['skill_level']);
    $certificates = mysqli_real_escape_string($conn, trim($_POST['certificates']));
    
    // 6. علاقة العمل والتبعية
    $owner_supervisor = mysqli_real_escape_string($conn, trim($_POST['owner_supervisor']));
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $employment_affiliation = mysqli_real_escape_string($conn, $_POST['employment_affiliation']);
    $salary_type = mysqli_real_escape_string($conn, $_POST['salary_type']);
    $monthly_salary = !empty($_POST['monthly_salary']) ? floatval($_POST['monthly_salary']) : NULL;
    
    // 7. البيانات التواصلية
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $phone_alternative = mysqli_real_escape_string($conn, trim($_POST['phone_alternative']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    
    // 8. تقييم الأداء والسلوك
    $performance_rating = mysqli_real_escape_string($conn, $_POST['performance_rating']);
    $behavior_record = mysqli_real_escape_string($conn, $_POST['behavior_record']);
    $accident_record = mysqli_real_escape_string($conn, $_POST['accident_record']);
    
    // 9. الصحة والسلامة
    $health_status = mysqli_real_escape_string($conn, $_POST['health_status']);
    $health_issues = mysqli_real_escape_string($conn, trim($_POST['health_issues']));
    $vaccinations_status = mysqli_real_escape_string($conn, $_POST['vaccinations_status']);
    
    // 10. المراجع والسجل
    $previous_employer = mysqli_real_escape_string($conn, trim($_POST['previous_employer']));
    $employment_duration = mysqli_real_escape_string($conn, trim($_POST['employment_duration']));
    $reference_contact = mysqli_real_escape_string($conn, trim($_POST['reference_contact']));
    $general_notes = mysqli_real_escape_string($conn, trim($_POST['general_notes']));
    
    // 11. الحالة والتفعيل
    $driver_status = mysqli_real_escape_string($conn, $_POST['driver_status']);
    $start_date = !empty($_POST['start_date']) ? mysqli_real_escape_string($conn, $_POST['start_date']) : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($id > 0) {
        // التحقق من فرادة كود المشغل على مستوى الشركة (عند التعديل)
        if (!empty($driver_code)) {
            $code_company_scope = $drivers_has_company ? " AND company_id = $company_id" : "";
            $code_check_edit = mysqli_query($conn, "SELECT id FROM drivers WHERE driver_code = '$driver_code' AND id != $id$code_company_scope LIMIT 1");
            if ($code_check_edit && mysqli_num_rows($code_check_edit) > 0) {
                header("Location: drivers.php?msg=كود+المشغل+موجود+مسبقاً+❌");
                exit;
            }
        }
        // تحديث
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $license_expiry_sql = $license_expiry_date ? "'$license_expiry_date'" : "NULL";
        $start_date_sql = $start_date ? "'$start_date'" : "NULL";
        $years_in_field_sql = $years_in_field !== NULL ? $years_in_field : "NULL";
        $years_on_equipment_sql = $years_on_equipment !== NULL ? $years_on_equipment : "NULL";
        $supplier_id_sql = $supplier_id !== NULL ? $supplier_id : "NULL";
        $monthly_salary_sql = $monthly_salary !== NULL ? $monthly_salary : "NULL";
        
        $scope_where = sprintf($driver_scope_where, $id);
        $update_query = "UPDATE drivers SET 
            name='$name', driver_code='$driver_code', nickname='$nickname',
            identity_type='$identity_type', identity_number='$identity_number', identity_expiry_date=$identity_expiry_sql,
            driver_photo='$driver_photo', identity_photo='$identity_photo',
            license_number='$license_number', license_type='$license_type', license_expiry_date=$license_expiry_sql, license_issuer='$license_issuer',
            specialized_equipment='$specialized_equipment',
            years_in_field=$years_in_field_sql, years_on_equipment=$years_on_equipment_sql, skill_level='$skill_level', certificates='$certificates',
            owner_supervisor='$owner_supervisor', supplier_id=$supplier_id_sql, employment_affiliation='$employment_affiliation', 
            salary_type='$salary_type', monthly_salary=$monthly_salary_sql,
            email='$email', phone='$phone', phone_alternative='$phone_alternative', address='$address',
            performance_rating='$performance_rating', behavior_record='$behavior_record', accident_record='$accident_record',
            health_status='$health_status', health_issues='$health_issues', vaccinations_status='$vaccinations_status',
            previous_employer='$previous_employer', employment_duration='$employment_duration', reference_contact='$reference_contact', general_notes='$general_notes',
            driver_status='$driver_status', start_date=$start_date_sql, status='$status' 
            WHERE $scope_where";
            
        if (mysqli_query($conn, $update_query)) {
            header("Location: drivers.php?msg=تم+تعديل+المشغل+بنجاح+✅");
            exit;
        } else {
            header("Location: drivers.php?msg=حدث+خطأ+أثناء+التعديل+❌: " . mysqli_error($conn));
            exit;
        }
    } else {
        // إضافة
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $license_expiry_sql = $license_expiry_date ? "'$license_expiry_date'" : "NULL";
        $start_date_sql = $start_date ? "'$start_date'" : "NULL";
        $years_in_field_sql = $years_in_field !== NULL ? $years_in_field : "NULL";
        $years_on_equipment_sql = $years_on_equipment !== NULL ? $years_on_equipment : "NULL";
        $supplier_id_sql = $supplier_id !== NULL ? $supplier_id : "NULL";
        $monthly_salary_sql = $monthly_salary !== NULL ? $monthly_salary : "NULL";

        // التحقق من فرادة كود المشغل على مستوى الشركة (عند الإضافة)
        if (!empty($driver_code)) {
            $code_company_scope = $drivers_has_company ? " AND company_id = $company_id" : "";
            $code_check_insert = mysqli_query($conn, "SELECT id FROM drivers WHERE driver_code = '$driver_code'$code_company_scope LIMIT 1");
            if ($code_check_insert && mysqli_num_rows($code_check_insert) > 0) {
                header("Location: drivers.php?msg=كود+المشغل+موجود+مسبقاً+❌");
                exit;
            }
        }

        $insert_query = "INSERT INTO drivers (
            name, driver_code, nickname,
            identity_type, identity_number, identity_expiry_date, driver_photo, identity_photo,
            license_number, license_type, license_expiry_date, license_issuer,
            specialized_equipment,
            years_in_field, years_on_equipment, skill_level, certificates,
            owner_supervisor, supplier_id, employment_affiliation, salary_type, monthly_salary,
            email, phone, phone_alternative, address,
            performance_rating, behavior_record, accident_record,
            health_status, health_issues, vaccinations_status,
            previous_employer, employment_duration, reference_contact, general_notes,
            driver_status, start_date, status$driver_insert_col
        ) VALUES (
            '$name', '$driver_code', '$nickname',
            '$identity_type', '$identity_number', $identity_expiry_sql, '$driver_photo', '$identity_photo',
            '$license_number', '$license_type', $license_expiry_sql, '$license_issuer',
            '$specialized_equipment',
            $years_in_field_sql, $years_on_equipment_sql, '$skill_level', '$certificates',
            '$owner_supervisor', $supplier_id_sql, '$employment_affiliation', '$salary_type', $monthly_salary_sql,
            '$email', '$phone', '$phone_alternative', '$address',
            '$performance_rating', '$behavior_record', '$accident_record',
            '$health_status', '$health_issues', '$vaccinations_status',
            '$previous_employer', '$employment_duration', '$reference_contact', '$general_notes',
            '$driver_status', $start_date_sql, '$status'$driver_insert_val
        )";
        
        if (mysqli_query($conn, $insert_query)) {
            header("Location: drivers.php?msg=تم+إضافة+المشغل+بنجاح+✅");
            exit;
        } else {
            header("Location: drivers.php?msg=حدث+خطأ+أثناء+الإضافة+❌: " . mysqli_error($conn));
            exit;
        }
    }
}

if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        header("Location: drivers.php?msg=لا+توجد+صلاحية+حذف+المشغلين+❌");
        exit();
    }

    $delete_id = intval($_GET['delete_id']);
    if ($delete_id <= 0) {
        header("Location: drivers.php?msg=معرف+المشغل+غير+صحيح+❌");
        exit();
    }

    $scope_where = sprintf($driver_scope_where, $delete_id);
    $active_contracts = 0;
    $active_equipment_assignments = 0;

    $contracts_sql = "SELECT COUNT(*) AS total FROM drivercontracts WHERE driver_id = $delete_id AND status = 1";
    $contracts_result = mysqli_query($conn, $contracts_sql);
    if ($contracts_result) {
        $contracts_row = mysqli_fetch_assoc($contracts_result);
        $active_contracts = intval($contracts_row['total']);
    }

    $assignments_sql = "SELECT COUNT(*) AS total FROM equipment_drivers WHERE driver_id = $delete_id AND status = 1";
    $assignments_result = mysqli_query($conn, $assignments_sql);
    if ($assignments_result) {
        $assignments_row = mysqli_fetch_assoc($assignments_result);
        $active_equipment_assignments = intval($assignments_row['total']);
    }

    if ($active_contracts > 0 || $active_equipment_assignments > 0) {
        header("Location: drivers.php?msg=لا+يمكن+حذف+المشغل+لارتباطه+بعقود+أو+تشغيل+نشط+❌");
        exit();
    }

    $delete_sql = "DELETE FROM drivers WHERE $scope_where";
    if (mysqli_query($conn, $delete_sql) && mysqli_affected_rows($conn) > 0) {
        header("Location: drivers.php?msg=تم+حذف+المشغل+بنجاح+✅");
        exit();
    }

    header("Location: drivers.php?msg=تعذر+حذف+المشغل+أو+أنه+خارج+نطاق+الشركة+❌");
    exit();
}

include("../inheader.php");
?>

<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<style>
.form-section {
    background: var(--bg);
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    border: 2px solid var(--border);
}

.form-section-header {
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    color: #fff;
    padding: 12px 15px;
    border-radius: var(--radius);
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
    transition: all var(--ease);
}

.form-section-header:hover {
    transform: translateX(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.form-section-header i {
    font-size: 1.2rem;
}

.form-section-header .toggle-icon {
    margin-right: auto;
    transition: transform 0.3s ease;
}

.form-section-header.collapsed .toggle-icon {
    transform: rotate(-90deg);
}

.form-section-body {
    padding: 5px;
    max-height: 1000px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.form-section-body.collapsed {
    max-height: 0;
    padding: 0;
}

.checkbox-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    margin-bottom: 5px;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.checkbox-group label:hover {
    background: var(--gold-light);
    transform: translateX(-3px);
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--gold);
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
    0%, 100% { transform: translateY(0); box-shadow: 0 1px 4px rgba(217, 119, 6, 0.18); }
    50% { transform: translateY(-1px); box-shadow: 0 5px 12px rgba(217, 119, 6, 0.28); }
}
</style>

<?php 
include('../insidebar.php'); 
?>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-id-card"></i></div>
            إدارة المشغلين - النظام الشامل
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة مشغل جديد
            </a>
            <?php endif; ?>
            <a href="download_drivers_template.php" class="btn btn-success" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px;">
                <i class="fas fa-file-excel"></i> تحميل نموذج Excel
            </a>
            <a href="download_drivers_template_csv.php" class="btn btn-info" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px;">
                <i class="fas fa-file-csv"></i> تحميل نموذج CSV
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-upload"></i> استيراد من Excel/CSV
            </button>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): 
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- مودال الاستيراد من Excel/CSV -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff;">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="fas fa-file-upload"></i> استيراد المشغلين من ملف Excel أو CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="text-align: right; direction: rtl;">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظات هامة:</strong>
                        <ul style="margin-top: 10px; padding-right: 20px;">
                            <li>يدعم النظام الملفات من نوع: <code>.xlsx</code>، <code>.xls</code>، <code>.csv</code></li>
                            <li>الحد الأقصى لحجم الملف: <strong>5 ميجا</strong></li>
                            <li>الحد الأقصى لعدد الصفوف: <strong>1000 صف</strong></li>
                            <li>تأكد من صحة البيانات وتطابقها مع نموذج الاستيراد</li>
                            <li>الحقول الإلزامية: <strong>اسم المشغل، رقم الهاتف، حالة المشغل، حالة النظام</strong></li>
                            <li>يمكن تحميل النموذج الجاهز أعلاه وملء البيانات فيه</li>
                        </ul>
                    </div>

                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="importFile" class="form-label" style="font-weight: 600;">
                                <i class="fas fa-file-alt"></i> اختر ملف Excel أو CSV
                            </label>
                            <input type="file" class="form-control" id="importFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>

                        <div id="importProgress" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                            </div>
                            <p class="text-center mt-2">جاري معالجة الملف، يرجى الانتظار...</p>
                        </div>

                        <div id="importResult"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إغلاق
                    </button>
                    <button type="button" class="btn btn-primary" id="startImportBtn">
                        <i class="fas fa-upload"></i> بدء الاستيراد
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل مشغل -->
    <form id="projectForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff;">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مشغل - نموذج شامل</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="drivers_id" value="">
                
                <!-- 1. المعلومات الأساسية والتعريفية -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-info-circle"></i>
                        <span>1. المعلومات الأساسية والتعريفية</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user"></i> اسم المشغل/السائق <span style="color: red;">*</span></label>
                                <input type="text" name="name" id="name" placeholder="مثال: محمد أحمد علي" required />
                            </div>
                            <div>
                                <label><i class="fas fa-barcode"></i> الرمز/الكود الفريد</label>
                                <input type="text" name="driver_code" id="driver_code" placeholder="مثال: OPR-001-2026" />
                            </div>
                            <div>
                                <label><i class="fas fa-signature"></i> اسم الشهرة/الكنية</label>
                                <input type="text" name="nickname" id="nickname" placeholder="مثال: أبو محمد" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. بيانات الهوية والتوثيق -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-id-card"></i>
                        <span>2. بيانات الهوية والتوثيق</span>
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
                                <input type="text" name="identity_number" id="identity_number" placeholder="مثال: 123456789123" />
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-times"></i> تاريخ انتهاء الهوية</label>
                                <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-camera"></i> صورة السائق (تجهيزي - غير مفعلة الآن)</label>
                                <input type="text" name="driver_photo" id="driver_photo" placeholder="سيتم تفعيل رفع صورة السائق لاحقاً" readonly />
                            </div>
                            <div>
                                <label><i class="fas fa-id-card"></i> صورة هوية السائق (تجهيزي - غير مفعلة الآن)</label>
                                <input type="text" name="identity_photo" id="identity_photo" placeholder="سيتم تفعيل رفع صورة الهوية لاحقاً" readonly />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. رخصة القيادة والمهارات -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-car"></i>
                        <span>3. رخصة القيادة والمهارات</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-id-badge"></i> رقم رخصة القيادة</label>
                                <input type="text" name="license_number" id="license_number" placeholder="مثال: DL-2024-456789" />
                            </div>
                            <div>
                                <label><i class="fas fa-certificate"></i> نوع رخصة القيادة</label>
                                <select name="license_type" id="license_type">
                                    <option value="">-- اختر نوع الرخصة --</option>
                                    <option value="فئة أ (دراجات نارية)">فئة أ (دراجات نارية)</option>
                                    <option value="فئة ب (سيارات خصوصية)">فئة ب (سيارات خصوصية)</option>
                                    <option value="فئة ج (شاحنات خفيفة)">فئة ج (شاحنات خفيفة)</option>
                                    <option value="فئة د (شاحنات ثقيلة)">فئة د (شاحنات ثقيلة)</option>
                                    <option value="فئة هـ (حافلات)">فئة هـ (حافلات)</option>
                                    <option value="متعددة الفئات">متعددة الفئات</option>
                                    <option value="غير محدد">غير محدد</option>
                                </select>
                            </div>
                            <div>
                                <label><i class="fas fa-calendar-times"></i> تاريخ انتهاء الرخصة</label>
                                <input type="date" name="license_expiry_date" id="license_expiry_date" />
                            </div>
                            <div>
                                <label><i class="fas fa-building"></i> جهة إصدار الرخصة</label>
                                <input type="text" name="license_issuer" id="license_issuer" placeholder="مثال: إدارة المرور - الخرطوم" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. التخصص والمهارات -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-cogs"></i>
                        <span>4. التخصص والمهارات</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div>
                            <label style="display: block; margin-bottom: 10px; font-weight: 700;">
                                <i class="fas fa-tools"></i> نوع المعدة المتخصص فيها (يمكن اختيار أكثر من واحد)
                            </label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="specialized_equipment[]" value="حفارة (Excavator)"> حفارة (Excavator)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="مثقاب/مكنة تخريم (Drill Machine)"> مثقاب/مكنة تخريم (Drill Machine)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="دوزر (Dozer)"> دوزر (Dozer)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="شاحنة قلابة (Dump Truck)"> شاحنة قلابة (Dump Truck)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="شاحنة تناكر/صهريج (Tanker Truck)"> شاحنة تناكر/صهريج (Tanker Truck)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="جرافة (Loader)"> جرافة (Loader)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="ممهدة (Grader)"> ممهدة (Grader)</label>
                                <label><input type="checkbox" name="specialized_equipment[]" value="معدات أخرى"> معدات أخرى</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. سنوات الخبرة والكفاءة -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-medal"></i>
                        <span>5. سنوات الخبرة والكفاءة</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-briefcase"></i> سنوات العمل في المجال</label>
                                <input type="number" name="years_in_field" id="years_in_field" placeholder="مثال: 8" min="0" max="50" />
                            </div>
                            <div>
                                <label><i class="fas fa-wrench"></i> سنوات العمل على هذه المعدات</label>
                                <input type="number" name="years_on_equipment" id="years_on_equipment" placeholder="مثال: 5" min="0" max="50" />
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
                                <textarea name="certificates" id="certificates" rows="3" placeholder="مثال: شهادة تشغيل حفارات من معهد التعدين، دورة السلامة الصناعية"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. علاقة العمل والتبعية -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-users"></i>
                        <span>6. علاقة العمل والتبعية</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-user-tie"></i> اسم المالك/المشرف المباشر</label>
                                <input type="text" name="owner_supervisor" id="owner_supervisor" placeholder="مثال: محمد علي (مالك المعدة)" />
                            </div>
                            <div>
                                <label><i class="fas fa-building"></i> المورد الذي يعمل معه</label>
                                <select name="supplier_id" id="supplier_id">
                                    <option value="">-- اختر المورد --</option>
                                    <?php
                                    $supplier_scope_sql = "1=1";
                                    if (!$is_super_admin && $suppliers_has_company) {
                                        $supplier_scope_sql = "company_id = $company_id";
                                    }
                                    $suppliers_query = "SELECT id, name FROM suppliers WHERE $supplier_scope_sql ORDER BY name";
                                    $suppliers_result = mysqli_query($conn, $suppliers_query);
                                    while ($supplier = mysqli_fetch_assoc($suppliers_result)) {
                                        echo "<option value='" . $supplier['id'] . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                                    }
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
                                <input type="number" step="0.01" name="monthly_salary" id="monthly_salary" placeholder="مثال: 1500" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. البيانات التواصلية -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-address-book"></i>
                        <span>7. البيانات التواصلية</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                                <input type="email" name="email" id="email" placeholder="operator@example.com" />
                            </div>
                            <div>
                                <label><i class="fas fa-phone"></i> رقم الهاتف الأساسي <span style="color: red;">*</span></label>
                                <input type="tel" name="phone" id="phone" placeholder="+249-9-123-4567" required />
                            </div>
                            <div>
                                <label><i class="fas fa-phone-alt"></i> رقم هاتف بديل</label>
                                <input type="tel" name="phone_alternative" id="phone_alternative" placeholder="+249-9-765-4321" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-map-marker-alt"></i> العنوان</label>
                                <textarea name="address" id="address" rows="2" placeholder="مثال: شارع النيل، الخرطوم"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8. تقييم الأداء والسلوك -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-star-half-alt"></i>
                        <span>8. تقييم الأداء والسلوك</span>
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
                        <span>9. الصحة والسلامة</span>
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
                                <textarea name="health_issues" id="health_issues" rows="2" placeholder="مثال: ضعف البصر الطفيف، مشاكل الظهر"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 10. المراجع والسجل -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-history"></i>
                        <span>10. المراجع والسجل</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-building"></i> اسم جهة التوظيف السابقة</label>
                                <input type="text" name="previous_employer" id="previous_employer" placeholder="مثال: شركة الذهب للتعدين" />
                            </div>
                            <div>
                                <label><i class="fas fa-clock"></i> مدة العمل معهم</label>
                                <input type="text" name="employment_duration" id="employment_duration" placeholder="مثال: 3 سنوات" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-user-friends"></i> مرجع للاتصال</label>
                                <input type="text" name="reference_contact" id="reference_contact" placeholder="مثال: محمود أحمد - مدير الأسطول (09-123-4567)" />
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label><i class="fas fa-comment-dots"></i> ملاحظات عامة</label>
                                <textarea name="general_notes" id="general_notes" rows="3" placeholder="مثال: مشغل موثوق وذو كفاءة عالية، يحتاج إلى تدريب على السلامة"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 11. الحالة والتفعيل -->
                <div class="form-section">
                    <div class="form-section-header" onclick="toggleSection(this)">
                        <i class="fas fa-toggle-on"></i>
                        <span>11. الحالة والتفعيل</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-info-circle"></i> حالة المشغل <span style="color: red;">*</span></label>
                                <select name="driver_status" id="driver_status" required>
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
                                <label><i class="fas fa-power-off"></i> حالة النظام <span style="color: red;">*</span></label>
                                <select name="status" id="status" required>
                                    <option value="">-- اختر الحالة --</option>
                                    <option value="1">✅ مفعل</option>
                                    <option value="0">❌ موقف</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border); justify-content: center;">
                    <button type="submit" class="btn-submit" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-save"></i> حفظ المشغل
                    </button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('projectForm').style.display='none';">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn-submit" style="background: linear-gradient(135deg, #10b981, #059669);" onclick="expandAllSections()">
                        <i class="fas fa-expand-alt"></i> فتح جميع المجموعات
                    </button>
                    <button type="button" class="btn-submit" style="background: linear-gradient(135deg, #f59e0b, #d97706);" onclick="collapseAllSections()">
                        <i class="fas fa-compress-alt"></i> إغلاق جميع المجموعات
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة المشغلين</h5>
        </div>
        <div class="card-body">
            <table id="driversTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الكود</th>
                        <th>اسم المشغل</th>
                        <th>رقم الهاتف</th>
                        <th>المورد</th>
                        <th>الصور</th>
                        <th>مستوى الكفاءة</th>
                        <th>عدد العقود</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب المشغلين مع البيانات الإضافية
                    $drivers_scope_sql = "1=1";
                    if (!$is_super_admin) {
                        if ($drivers_has_company) {
                            $drivers_scope_sql = "d.company_id = $company_id";
                        } else {
                            $drivers_scope_sql = "EXISTS (
                                SELECT 1
                                FROM drivercontracts dsc
                                INNER JOIN project sp ON sp.id = dsc.project_id
                                INNER JOIN users su ON su.id = sp.created_by
                                WHERE dsc.driver_id = d.id
                                  AND su.company_id = $company_id
                            )";
                        }
                    }

                    $drivercontracts_scope_sql = (!$is_super_admin && $drivercontracts_has_company)
                        ? " AND drivercontracts.company_id = $company_id"
                        : "";

                    $query = "SELECT d.*, s.name as supplier_name,
                             (SELECT COUNT(*) FROM drivercontracts WHERE driver_id = d.id$drivercontracts_scope_sql) as numcontracts
                             FROM drivers d
                             LEFT JOIN suppliers s ON d.supplier_id = s.id
                             WHERE $drivers_scope_sql
                             ORDER BY d.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $statusBadge = $row['status'] == "1" ? '<span class="status-pill status-active">✅ مفعّل</span>' : '<span class="status-pill status-inactive">❌ موقف</span>';
                        $driver_name_cell = "<strong>" . htmlspecialchars($row['name']) . "</strong>";
                        if (intval($row['numcontracts']) === 0) {
                            $driver_name_cell .= " <span class='link-alert-chip' title='المشغل ليس لديه عقد'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                        }
                        
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><code>" . htmlspecialchars($row['driver_code'] ?: 'N/A') . "</code></td>";
                        echo "<td>" . $driver_name_cell . "</td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['supplier_name'] ?: '-') . "</td>";
                        $driver_photo_status = !empty($row['driver_photo']) ? '✅ صورة السائق' : '⏳ صورة السائق';
                        $identity_photo_status = !empty($row['identity_photo']) ? '✅ صورة الهوية' : '⏳ صورة الهوية';
                        echo "<td><small>" . $driver_photo_status . "<br>" . $identity_photo_status . "</small></td>";
                        echo "<td>" . htmlspecialchars($row['skill_level'] ?: 'غير محدد') . "</td>";
                        echo "<td><span class='badge badge-info'>" . $row['numcontracts'] . " عقد</span></td>";
                        echo "<td>" . $statusBadge . "</td>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' 
                                       class='action-btn edit editBtn' 
                                       data-id='" . $row['id'] . "' 
                                       title='تعديل'>
                                        <i class='fas fa-edit'></i>
                                    </a>";
                        }
                        echo "<a href='drivercontracts.php?id=" . $row['id'] . "' 
                                       class='action-btn view' 
                                       title='عرض العقود'>
                                        <i class='fas fa-file-contract'></i>
                                    </a>
                                    <a href='driver_profile.php?id=" . $row['id'] . "' 
                                       class='action-btn view' 
                                       title='بطاقة وبيانات السائق'>
                                        <i class='fas fa-id-card-alt'></i>
                                    </a>
                                    <a href='driver_truck_history.php?id=" . $row['id'] . "' 
                                       class='action-btn history' 
                                       title='تاريخ القيادة'>
                                        <i class='fas fa-history'></i>
                                    </a>";
                        if ($can_delete) {
                            echo "<a href='drivers.php?delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من حذف المشغل؟\")' title='حذف'>
                                        <i class='fas fa-trash'></i>
                                    </a>";
                        }
                        echo "</div></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- jQuery (Required first) -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
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

    (function () {
        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            $('#driversTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                "language": {
                    "url": "https:/ems/assets/i18n/datatables/ar.json"
                }
            });
        });

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        
        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                
                if (projectForm.style.display === "block") {
                    // تنظيف جميع الحقول
                    projectForm.reset();
                    $("#drivers_id").val("");
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
                url: 'get_driver_data.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        const driver = data.driver;
                        
                        // ملء جميع الحقول
                        $("#drivers_id").val(driver.id);
                        $("#name").val(driver.name);
                        $("#driver_code").val(driver.driver_code);
                        $("#nickname").val(driver.nickname);
                        $("#identity_type").val(driver.identity_type);
                        $("#identity_number").val(driver.identity_number);
                        $("#identity_expiry_date").val(driver.identity_expiry_date);
                        $("#driver_photo").val(driver.driver_photo || '');
                        $("#identity_photo").val(driver.identity_photo || '');
                        $("#license_number").val(driver.license_number);
                        $("#license_type").val(driver.license_type);
                        $("#license_expiry_date").val(driver.license_expiry_date);
                        $("#license_issuer").val(driver.license_issuer);
                        
                        // المعدات المتخصصة (checkboxes)
                        const equipment = driver.specialized_equipment ? driver.specialized_equipment.split(', ') : [];
                        $("input[name='specialized_equipment[]']").prop("checked", false);
                        equipment.forEach(function(eq) {
                            $("input[name='specialized_equipment[]'][value='" + eq.trim() + "']").prop("checked", true);
                        });
                        
                        $("#years_in_field").val(driver.years_in_field);
                        $("#years_on_equipment").val(driver.years_on_equipment);
                        $("#skill_level").val(driver.skill_level);
                        $("#certificates").val(driver.certificates);
                        $("#owner_supervisor").val(driver.owner_supervisor);
                        $("#supplier_id").val(driver.supplier_id);
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
                        $("#driver_status").val(driver.driver_status);
                        $("#start_date").val(driver.start_date);
                        $("#status").val(driver.status);
                        
                        // عرض الفورم وفتح جميع المجموعات
                        projectForm.style.display = "block";
                        expandAllSections();
                        $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                    } else {
                        alert('خطأ في تحميل البيانات');
                    }
                },
                error: function() {
                    alert('حدث خطأ في الاتصال بالخادم');
                }
            });
        });

    })();

    // معالجة الاستيراد من Excel/CSV
    $(document).ready(function() {
        $('#startImportBtn').on('click', function() {
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('يرجى اختيار ملف للاستيراد');
                return;
            }
            
            // التحقق من نوع الملف
            const allowedExtensions = ['xlsx', 'xls', 'csv'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                alert('نوع الملف غير مدعوم. يرجى رفع ملف Excel أو CSV');
                return;
            }
            
            // التحقق من حجم الملف (5 ميجا)
            if (file.size > 5 * 1024 * 1024) {
                alert('حجم الملف كبير جداً (الحد الأقصى 5 ميجا)');
                return;
            }
            
            // إنشاء FormData
            const formData = new FormData();
            formData.append('file', file);
            
            // إخفاء زر البدء وعرض شريط التقدم
            $('#startImportBtn').prop('disabled', true);
            $('#importProgress').show();
            $('#importResult').html('');
            
            // إرسال الطلب عبر AJAX
            $.ajax({
                url: 'import_drivers_excel.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    $('#importProgress').hide();
                    $('#startImportBtn').prop('disabled', false);
                    
                    if (response.success) {
                        // عرض رسالة النجاح
                        let resultHTML = '<div class="alert alert-success" style="text-align: right; direction: rtl;">';
                        resultHTML += '<i class="fas fa-check-circle"></i> <strong>' + response.message + '</strong>';
                        
                        // إذا كانت هناك أخطاء، عرضها
                        if (response.errors && response.errors.length > 0) {
                            resultHTML += '<hr><strong>تفاصيل الأخطاء:</strong><ul class="mb-0">';
                            response.errors.forEach(function(error) {
                                resultHTML += '<li>الصف ' + error.row + ': ' + error.error + '</li>';
                            });
                            resultHTML += '</ul>';
                        }
                        
                        resultHTML += '</div>';
                        resultHTML += '<button type="button" class="btn btn-success" onclick="location.reload();">';
                        resultHTML += '<i class="fas fa-sync-alt"></i> تحديث الصفحة لعرض البيانات الجديدة';
                        resultHTML += '</button>';
                        
                        $('#importResult').html(resultHTML);
                        
                        // إعادة تعيين الفورم
                        $('#importForm')[0].reset();
                        
                    } else {
                        // عرض رسالة الخطأ
                        let resultHTML = '<div class="alert alert-danger" style="text-align: right; direction: rtl;">';
                        resultHTML += '<i class="fas fa-exclamation-triangle"></i> <strong>' + response.message + '</strong>';
                        resultHTML += '</div>';
                        
                        $('#importResult').html(resultHTML);
                    }
                },
                error: function(xhr, status, error) {
                    $('#importProgress').hide();
                    $('#startImportBtn').prop('disabled', false);
                    
                    let resultHTML = '<div class="alert alert-danger" style="text-align: right; direction: rtl;">';
                    resultHTML += '<i class="fas fa-exclamation-triangle"></i> <strong>حدث خطأ أثناء الاتصال بالخادم</strong>';
                    resultHTML += '<br>تفاصيل الخطأ: ' + error;
                    resultHTML += '</div>';
                    
                    $('#importResult').html(resultHTML);
                }
            });
        });
        
        // إعادة تعيين المودال عند إغلاقه
        $('#importModal').on('hidden.bs.modal', function() {
            $('#importForm')[0].reset();
            $('#importProgress').hide();
            $('#importResult').html('');
            $('#startImportBtn').prop('disabled', false);
        });
    });
</script>

</body>
</html>




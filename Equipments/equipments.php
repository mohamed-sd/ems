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

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');

if (!$equipments_has_company) {
    @mysqli_query($conn, "ALTER TABLE equipments ADD COLUMN company_id INT(11) NULL DEFAULT NULL");
    @mysqli_query($conn, "CREATE INDEX idx_equipments_company_id ON equipments (company_id)");
    $equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
}

if ($equipments_has_company && $suppliers_has_company) {
    @mysqli_query(
        $conn,
        "UPDATE equipments m
         INNER JOIN suppliers s ON s.id = m.suppliers
         SET m.company_id = s.company_id
         WHERE (m.company_id IS NULL OR m.company_id = 0)
           AND s.company_id IS NOT NULL
           AND s.company_id > 0"
    );
}

if (!$is_super_admin && !$equipments_has_company) {
    die('تعذر تفعيل عزل الشركات لجدول المعدات');
}

$project_scope_sql = "1=1";
if (!$is_super_admin) {
    $project_scope_sql = "(
        EXISTS (SELECT 1 FROM users su WHERE su.id = project.created_by AND su.company_id = $company_id)
        OR EXISTS (
            SELECT 1
            FROM clients sc
            INNER JOIN users scu ON scu.id = sc.created_by
            WHERE sc.id = project.company_client_id AND scu.company_id = $company_id
        )
    )";
}

// ════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'equipments');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن صلاحية عرض
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المعدات+❌");
    exit();
}

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_project_id = $is_role10 ? intval($_SESSION['user']['project_id']) : 0;

$selected_project_id = 0;
$show_all_projects = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_project_id'])) {
    if ($is_role10) {
        header("Location: equipments.php");
        exit();
    }
    $selected_project_value = trim($_POST['selected_project_id']);
    if ($selected_project_value === 'all') {
        $_SESSION['equipments_project_id'] = 'all';
    } elseif (is_numeric($selected_project_value) && intval($selected_project_value) > 0) {
        $_SESSION['equipments_project_id'] = intval($selected_project_value);
    } else {
        unset($_SESSION['equipments_project_id']);
    }
    header("Location: equipments.php");
    exit();
}

if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
    if ($is_role10) {
        header("Location: equipments.php");
        exit();
    }
    $_SESSION['equipments_project_id'] = intval($_GET['project_id']);
    header("Location: equipments.php");
    exit();
}

if (isset($_SESSION['equipments_project_id'])) {
    if ($_SESSION['equipments_project_id'] === 'all') {
        $show_all_projects = true;
        $selected_project_id = 0;
    } else {
        $selected_project_id = intval($_SESSION['equipments_project_id']);
    }
}

if ($is_role10) {
    $show_all_projects = false;
    $selected_project_id = $user_project_id;
}

$selected_project = null;
if ($selected_project_id > 0) {
    $project_check_query = "SELECT id, name, project_code FROM project WHERE id = $selected_project_id AND status = '1' AND $project_scope_sql";
    $project_check_result = mysqli_query($conn, $project_check_query);
    if ($project_check_result && mysqli_num_rows($project_check_result) > 0) {
        $selected_project = mysqli_fetch_assoc($project_check_result);
    } else {
        unset($_SESSION['equipments_project_id']);
        $selected_project_id = 0;
    }
}

$projects_result = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' AND $project_scope_sql ORDER BY name");

$page_title = "إدارة المعدات";
include("../inheader.php");
include("../insidebar.php");

// معالجة رسالة النجاح
$success_msg = '';
if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<!-- Font Awesome من CDN لضمان ظهور الأيقونات بشكل صحيح -->
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<?php

// معالجة الحفظ أو التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10") {
        $success_msg = "❌ ليس لديك صلاحية لتعديل أو إضافة المعدات";
        goto skip_save;
    }

    // الحقول الأساسية
    $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
    $code      = mysqli_real_escape_string($conn, trim($_POST['code']));
    $type      = mysqli_real_escape_string($conn, $_POST['type']);
    $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
    $status    = mysqli_real_escape_string($conn, $_POST['status']);
    $edit_id   = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // المعلومات الأساسية والتعريفية
    $serial_number = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $chassis_number = mysqli_real_escape_string($conn, trim($_POST['chassis_number'] ?? ''));

    // بيانات الصنع والموديل
    $manufacturer = mysqli_real_escape_string($conn, trim($_POST['manufacturer'] ?? ''));
    $model = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : 'NULL';
    $import_year = !empty($_POST['import_year']) ? intval($_POST['import_year']) : 'NULL';

    // الحالة الفنية والمواصفات
    $equipment_condition = mysqli_real_escape_string($conn, $_POST['equipment_condition'] ?? 'في حالة جيدة');
    $operating_hours = !empty($_POST['operating_hours']) ? intval($_POST['operating_hours']) : 'NULL';
    $engine_condition = mysqli_real_escape_string($conn, $_POST['engine_condition'] ?? 'جيدة');
    $tires_condition = mysqli_real_escape_string($conn, $_POST['tires_condition'] ?? 'N/A');

    // بيانات الملكية
    $actual_owner_name = mysqli_real_escape_string($conn, trim($_POST['actual_owner_name'] ?? ''));
    $owner_type = mysqli_real_escape_string($conn, $_POST['owner_type'] ?? '');
    $owner_phone = mysqli_real_escape_string($conn, trim($_POST['owner_phone'] ?? ''));
    $owner_supplier_relation = mysqli_real_escape_string($conn, $_POST['owner_supplier_relation'] ?? '');

    // الوثائق والتسجيلات
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number'] ?? ''));
    $license_authority = mysqli_real_escape_string($conn, trim($_POST['license_authority'] ?? ''));
    $license_expiry_date = !empty($_POST['license_expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['license_expiry_date']) . "'" : 'NULL';
    $inspection_certificate_number = mysqli_real_escape_string($conn, trim($_POST['inspection_certificate_number'] ?? ''));
    $last_inspection_date = !empty($_POST['last_inspection_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_inspection_date']) . "'" : 'NULL';

    // الموقع والتوفر
    $current_location = mysqli_real_escape_string($conn, trim($_POST['current_location'] ?? ''));
    $availability_status = mysqli_real_escape_string($conn, $_POST['availability_status'] ?? 'متاحة للعمل');

    // البيانات المالية والقيمة
    $estimated_value = !empty($_POST['estimated_value']) ? floatval($_POST['estimated_value']) : 'NULL';
    $daily_rental_price = !empty($_POST['daily_rental_price']) ? floatval($_POST['daily_rental_price']) : 'NULL';
    $monthly_rental_price = !empty($_POST['monthly_rental_price']) ? floatval($_POST['monthly_rental_price']) : 'NULL';
    $insurance_status = mysqli_real_escape_string($conn, $_POST['insurance_status'] ?? '');

    // ملاحظات وسجل الصيانة
    $general_notes = mysqli_real_escape_string($conn, trim($_POST['general_notes'] ?? ''));
    $last_maintenance_date = !empty($_POST['last_maintenance_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_maintenance_date']) . "'" : 'NULL';



    // التحقق من عدم تجاوز العدد المتعاقد عليه (فقط عند الإضافة)
    if ($edit_id == 0  && $suppliers && $type) {
        // الحصول على عدد المعدات المتعاقد عليها لهذا المورد ونوع المعدة
        $supplier_contract_query = "SELECT sc.id, sce.equip_count
                                   FROM supplierscontracts sc
                                   JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
                                   WHERE sc.supplier_id = $suppliers
                                   AND sce.equip_type = '$type'
                                   AND sc.status = 1
                                   LIMIT 1";
        $supplier_contract_result = mysqli_query($conn, $supplier_contract_query);

        if ($supplier_contract_result && mysqli_num_rows($supplier_contract_result) > 0) {
            $supplier_contract = mysqli_fetch_assoc($supplier_contract_result);
            $contracted_count = intval($supplier_contract['equip_count']);

            // حساب عدد المعدات المضافة حالياً
            $added_count_query = "SELECT COUNT(*) as added_count
                                 FROM equipments
                                 WHERE suppliers = $suppliers
                                 AND type = '$type'
                                 AND status = 1";
            $added_count_result = mysqli_query($conn, $added_count_query);
            $added_count_row = $added_count_result ? mysqli_fetch_assoc($added_count_result) : null;
            $current_added = intval($added_count_row['added_count'] ?? 0);

            // التحقق من عدم تجاوز العدد المتعاقد عليه
            if ($current_added >= $contracted_count) {
                $success_msg = "⚠️ تحذير: تم الوصول للحد الأقصى! العدد المتعاقد عليه: $contracted_count | المضاف حالياً: $current_added. لا يمكن إضافة المزيد من المعدات.";
                goto skip_save;
            }
        }
    }

    if ($edit_id > 0) {
        // تعديل
        $scope_update = ($is_super_admin || !$equipments_has_company) ? "" : " AND company_id = $company_id";
        $sql = "UPDATE equipments
                SET
                    suppliers='$suppliers',
                    code='$code',
                    type='$type',
                    name='$name',
                    status='$status',
                    serial_number='$serial_number',
                    chassis_number='$chassis_number',
                    manufacturer='$manufacturer',
                    model='$model',
                    manufacturing_year=$manufacturing_year,
                    import_year=$import_year,
                    equipment_condition='$equipment_condition',
                    operating_hours=$operating_hours,
                    engine_condition='$engine_condition',
                    tires_condition='$tires_condition',
                    actual_owner_name='$actual_owner_name',
                    owner_type='$owner_type',
                    owner_phone='$owner_phone',
                    owner_supplier_relation='$owner_supplier_relation',
                    license_number='$license_number',
                    license_authority='$license_authority',
                    license_expiry_date=$license_expiry_date,
                    inspection_certificate_number='$inspection_certificate_number',
                    last_inspection_date=$last_inspection_date,
                    current_location='$current_location',
                    availability_status='$availability_status',
                    estimated_value=$estimated_value,
                    daily_rental_price=$daily_rental_price,
                    monthly_rental_price=$monthly_rental_price,
                    insurance_status='$insurance_status',
                    general_notes='$general_notes',
                    last_maintenance_date=$last_maintenance_date
                WHERE id='$edit_id'$scope_update";
        $msg = "تم+تعديل+المعدة+بنجاح+✅";
    } else {
        // إضافة
        $insert_company_col = (!$is_super_admin && $equipments_has_company) ? ", company_id" : "";
        $insert_company_val = (!$is_super_admin && $equipments_has_company) ? ", '$company_id'" : "";
        $sql = "INSERT INTO equipments
                (suppliers, code, type, name, status, serial_number, chassis_number,
                 manufacturer, model, manufacturing_year, import_year,
                 equipment_condition, operating_hours, engine_condition, tires_condition,
                 actual_owner_name, owner_type, owner_phone, owner_supplier_relation,
                 license_number, license_authority, license_expiry_date,
                 inspection_certificate_number, last_inspection_date,
                 current_location, availability_status,
                 estimated_value, daily_rental_price, monthly_rental_price, insurance_status,
             general_notes, last_maintenance_date$insert_company_col)
                VALUES
                ('$suppliers', '$code', '$type', '$name', '$status', '$serial_number', '$chassis_number',
                 '$manufacturer', '$model', $manufacturing_year, $import_year,
                 '$equipment_condition', $operating_hours, '$engine_condition', '$tires_condition',
                 '$actual_owner_name', '$owner_type', '$owner_phone', '$owner_supplier_relation',
                 '$license_number', '$license_authority', $license_expiry_date,
                 '$inspection_certificate_number', $last_inspection_date,
                 '$current_location', '$availability_status',
                 $estimated_value, $daily_rental_price, $monthly_rental_price, '$insurance_status',
             '$general_notes', $last_maintenance_date$insert_company_val)";
        $msg = "تمت+إضافة+المعدة+بنجاح+✅";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: equipments.php?msg=$msg");
        exit;
    } else {
        $success_msg = "خطأ في الحفظ: " . mysqli_error($conn);
    }

    skip_save:
}

// في حالة تعديل تجهيز البيانات
$editData = [];
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10" && isset($_GET['edit'])) {
    $success_msg = "❌ ليس لديك صلاحية لتعديل المعدات";
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $scope_edit = ($is_super_admin || !$equipments_has_company) ? "" : " AND company_id = $company_id";
    $res = mysqli_query($conn, "SELECT * FROM equipments WHERE id='$editId'$scope_edit");
    if ($res && mysqli_num_rows($res) > 0) {
        $editData = mysqli_fetch_assoc($res);
    }
}
?>

<div class="main">
    <!-- عنوان الصفحة -->
    <!-- Unified header: pre-built final structure (data-ems-unified-header skips the JS rebuild). Styling: ems.main.all.style.css (.header) -->
    <div class="header" data-ems-unified-header="1">
        <div class="actions"<?php if ($_SESSION['user']['role'] == "10") echo ' style="display:none;"'; ?>>
            <?php if ($_SESSION['user']['role'] != "10") { ?>
            <!-- ── نظام Excel الموحّد (Unified Excel Framework) ── -->
            <?php
            require_once __DIR__ . '/../includes/excel_ui.php';
            $__xlBase = ems_excel_endpoint_url();
            ?>
            <a href="<?php echo htmlspecialchars($__xlBase . '?entity=equipments&action=template', ENT_QUOTES, 'UTF-8'); ?>" class="btn" style="background: linear-gradient(135deg, #16a34a 0%, #059669 100%); color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25); transition: all 0.3s ease;">
                <i class="fas fa-file-excel"></i> تحميل النموذج
            </a>
            <a href="<?php echo htmlspecialchars($__xlBase . '?entity=equipments&action=export', ENT_QUOTES, 'UTF-8'); ?>" class="btn" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25); transition: all 0.3s ease;">
                <i class="fas fa-file-export"></i> تصدير Excel
            </a>
            <button type="button" class="btn" data-ems-excel-import="equipments" data-ems-excel-title="المعدات" style="background: linear-gradient(135deg, #e8b800 0%, #d4a800 100%); color: #0c1c3e; padding: 10px 20px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(232, 184, 0, 0.25); transition: all 0.3s ease;">
                <i class="fas fa-file-import"></i> استيراد Excel
            </button>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة معدة جديدة
            </a>
            <?php } ?>
        </div>
        <div class="title">
            <h1 class="title-content">
                <div class="title-icon"><i class="fas fa-cogs"></i></div>
                إدارة المعدات
            </h1>
        </div>
        <div class="back">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <?php if (!empty($success_msg)):
        $isSuccess = strpos($success_msg, '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if ($_SESSION['user']['role'] != "10") { ?>
    <!-- فورم إضافة / تعديل معدة -->
    <form id="projectForm" action="" method="post" class="allforms<?php echo !empty($editData) ? ' allforms-visible' : ''; ?>">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-<?php echo !empty($editData) ? 'edit' : 'plus-circle'; ?>"></i>
                    <?php echo !empty($editData) ? "تعديل الآلية" : "إضافة آلية جديدة"; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <?php if (!empty($editData)) { ?>
                        <input type="hidden" name="edit_id" value="<?php echo isset($editData['id']) ? $editData['id'] : ''; ?>">
                    <?php } ?>

                    <div>
                        <label>
                            <i class="fas fa-truck-loading"></i>
                            المورد <span class="required-indicator">*</span>
                        </label>
                        <select name="suppliers" id="suppliers" required>
                            <option value="">-- اختر المورد --</option>
                            <?php
                            $supplier_scope_sql = "status = 1";
                            if (!$is_super_admin && $suppliers_has_company) {
                                $supplier_scope_sql .= " AND company_id = $company_id";
                            }
                            $supplier_query = "SELECT id, name FROM suppliers WHERE $supplier_scope_sql ORDER BY name";
                            $supplier_result = mysqli_query($conn, $supplier_query);
                            if ($supplier_result) while($supplier = mysqli_fetch_assoc($supplier_result)) {
                                $selected = (!empty($editData) && $editData['suppliers'] == $supplier['id']) ? 'selected' : '';
                                echo "<option value='{$supplier['id']}' $selected>{$supplier['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-barcode"></i>
                            كود المعدة <span class="required-indicator">*</span>
                        </label>
                        <input type="text" name="code" id="code" placeholder="أدخل كود المعدة"
                               value="<?php echo isset($editData['code']) ? htmlspecialchars($editData['code']) : ''; ?>" required />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-list-alt"></i>
                            نوع المعدة <span class="required-indicator">*</span>
                        </label>
                        <select name="type" id="type" required>
                            <option value="">-- حدد نوع المعدة --</option>
                            <?php
                            $type_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                            $type_result = mysqli_query($conn, $type_query);
                            if ($type_result) {
                                while($type_row = mysqli_fetch_assoc($type_result)) {
                                    $selected = (!empty($editData) && $editData['type'] == $type_row['id']) ? 'selected' : '';
                                    echo "<option value='" . intval($type_row['id']) . "' $selected>" . htmlspecialchars($type_row['type']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-tag"></i>
                            اسم المعدة <span class="required-indicator">*</span>
                        </label>
                        <input type="text" name="name" id="name" placeholder="أدخل اسم المعدة"
                               value="<?php echo isset($editData['name']) ? htmlspecialchars($editData['name']) : ''; ?>" required />
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: المعلومات الأساسية والتعريفية -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-id-card"></i> المعلومات الأساسية والتعريفية</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-hashtag"></i>
                            رقم المعدة/الرقم التسلسلي
                        </label>
                        <input type="text" name="serial_number" id="serial_number" placeholder="مثال: EXC-2024-001"
                               value="<?php echo isset($editData['serial_number']) ? htmlspecialchars($editData['serial_number']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-barcode"></i>
                            رقم الهيكل/الهيكل الأساسي (VIN/Chassis)
                        </label>
                        <input type="text" name="chassis_number" id="chassis_number" placeholder="مثال: CAT320-ABC123456"
                               value="<?php echo isset($editData['chassis_number']) ? htmlspecialchars($editData['chassis_number']) : ''; ?>" />
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: بيانات الصنع والموديل -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-industry"></i> بيانات الصنع والموديل</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-building"></i>
                            الماركة/الشركة المصنعة
                        </label>
                        <input type="text" name="manufacturer" id="manufacturer" placeholder="مثال: كاتربيلر، كوماتسو، هيونداي"
                               value="<?php echo isset($editData['manufacturer']) ? htmlspecialchars($editData['manufacturer']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-car"></i>
                            الموديل/الطراز
                        </label>
                        <input type="text" name="model" id="model" placeholder="مثال: 320D, PC200, HD1024"
                               value="<?php echo isset($editData['model']) ? htmlspecialchars($editData['model']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-calendar"></i>
                            سنة الصنع
                        </label>
                        <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="مثال: 2018" min="1950" max="2099"
                               value="<?php echo isset($editData['manufacturing_year']) ? $editData['manufacturing_year'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-calendar-plus"></i>
                            سنة الاستيراد/البدء
                        </label>
                        <input type="number" name="import_year" id="import_year" placeholder="مثال: 2020" min="1950" max="2099"
                               value="<?php echo isset($editData['import_year']) ? $editData['import_year'] : ''; ?>" />
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: الحالة الفنية والمواصفات -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-wrench"></i> الحالة الفنية والمواصفات</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-cogs"></i>
                            حالة المعدة
                        </label>
                        <select name="equipment_condition" id="equipment_condition">
                            <option value="جديدة (لم تستخدم)" <?php echo (!empty($editData) && $editData['equipment_condition']=="جديدة (لم تستخدم)") ? "selected" : ""; ?>>جديدة (لم تستخدم)</option>
                            <option value="جديدة نسبياً (أقل من سنة استخدام)" <?php echo (!empty($editData) && $editData['equipment_condition']=="جديدة نسبياً (أقل من سنة استخدام)") ? "selected" : ""; ?>>جديدة نسبياً (أقل من سنة استخدام)</option>
                            <option value="في حالة جيدة" <?php echo (empty($editData) || $editData['equipment_condition']=="في حالة جيدة") ? "selected" : ""; ?>>في حالة جيدة</option>
                            <option value="في حالة متوسطة" <?php echo (!empty($editData) && $editData['equipment_condition']=="في حالة متوسطة") ? "selected" : ""; ?>>في حالة متوسطة</option>
                            <option value="في حالة ضعيفة" <?php echo (!empty($editData) && $editData['equipment_condition']=="في حالة ضعيفة") ? "selected" : ""; ?>>في حالة ضعيفة</option>
                            <option value="محتاجة إصلاح فوري" <?php echo (!empty($editData) && $editData['equipment_condition']=="محتاجة إصلاح فوري") ? "selected" : ""; ?>>محتاجة إصلاح فوري</option>
                            <option value="معطلة مؤقتاً" <?php echo (!empty($editData) && $editData['equipment_condition']=="معطلة مؤقتاً") ? "selected" : ""; ?>>معطلة مؤقتاً</option>
                            <option value="مستعملة بكثافة" <?php echo (!empty($editData) && $editData['equipment_condition']=="مستعملة بكثافة") ? "selected" : ""; ?>>مستعملة بكثافة</option>
                        </select>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-clock"></i>
                            ساعات التشغيل (للمعدات الثقيلة)
                        </label>
                        <input type="number" name="operating_hours" id="operating_hours" placeholder="مثال: 5400 ساعة" min="0"
                               value="<?php echo isset($editData['operating_hours']) ? $editData['operating_hours'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-car-crash"></i>
                            حالة المحرك
                        </label>
                        <select name="engine_condition" id="engine_condition">
                            <option value="ممتازة" <?php echo (!empty($editData) && $editData['engine_condition']=="ممتازة") ? "selected" : ""; ?>>ممتازة</option>
                            <option value="جيدة" <?php echo (empty($editData) || $editData['engine_condition']=="جيدة") ? "selected" : ""; ?>>جيدة</option>
                            <option value="متوسطة" <?php echo (!empty($editData) && $editData['engine_condition']=="متوسطة") ? "selected" : ""; ?>>متوسطة</option>
                            <option value="محتاجة صيانة" <?php echo (!empty($editData) && $editData['engine_condition']=="محتاجة صيانة") ? "selected" : ""; ?>>محتاجة صيانة</option>
                            <option value="محتاجة إصلاح" <?php echo (!empty($editData) && $editData['engine_condition']=="محتاجة إصلاح") ? "selected" : ""; ?>>محتاجة إصلاح</option>
                        </select>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-circle-notch"></i>
                            حالة الإطارات (للشاحنات)
                        </label>
                        <select name="tires_condition" id="tires_condition">
                            <option value="N/A" <?php echo (empty($editData) || $editData['tires_condition']=="N/A") ? "selected" : ""; ?>>N/A</option>
                            <option value="جديدة" <?php echo (!empty($editData) && $editData['tires_condition']=="جديدة") ? "selected" : ""; ?>>جديدة</option>
                            <option value="جيدة" <?php echo (!empty($editData) && $editData['tires_condition']=="جيدة") ? "selected" : ""; ?>>جيدة</option>
                            <option value="متوسطة" <?php echo (!empty($editData) && $editData['tires_condition']=="متوسطة") ? "selected" : ""; ?>>متوسطة</option>
                            <option value="محتاجة تبديل" <?php echo (!empty($editData) && $editData['tires_condition']=="محتاجة تبديل") ? "selected" : ""; ?>>محتاجة تبديل</option>
                        </select>
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: بيانات الملكية -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-user-tie"></i> بيانات الملكية</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-user"></i>
                            اسم المالك الفعلي
                        </label>
                        <input type="text" name="actual_owner_name" id="actual_owner_name" placeholder="مثال: محمد علي أحمد"
                               value="<?php echo isset($editData['actual_owner_name']) ? htmlspecialchars($editData['actual_owner_name']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-briefcase"></i>
                            نوع المالك
                        </label>
                        <select name="owner_type" id="owner_type">
                            <option value="">-- اختر نوع المالك --</option>
                            <option value="مالك فردي" <?php echo (!empty($editData) && $editData['owner_type']=="مالك فردي") ? "selected" : ""; ?>>مالك فردي</option>
                            <option value="شركة متخصصة" <?php echo (!empty($editData) && $editData['owner_type']=="شركة متخصصة") ? "selected" : ""; ?>>شركة متخصصة</option>
                            <option value="مؤسسة" <?php echo (!empty($editData) && $editData['owner_type']=="مؤسسة") ? "selected" : ""; ?>>مؤسسة</option>
                            <option value="أخرى" <?php echo (!empty($editData) && $editData['owner_type']=="أخرى") ? "selected" : ""; ?>>أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-phone"></i>
                            رقم هاتف المالك
                        </label>
                        <input type="text" name="owner_phone" id="owner_phone" placeholder="مثال: +249-9-123-4567"
                               value="<?php echo isset($editData['owner_phone']) ? htmlspecialchars($editData['owner_phone']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-handshake"></i>
                            علاقة المالك بالمورد
                        </label>
                        <select name="owner_supplier_relation" id="owner_supplier_relation">
                            <option value="">-- اختر العلاقة --</option>
                            <option value="مالك مباشر (يتعاقد معنا مباشرة)" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="مالك مباشر (يتعاقد معنا مباشرة)") ? "selected" : ""; ?>>مالك مباشر (يتعاقد معنا مباشرة)</option>
                            <option value="تحت وساطة المورد (المورد يدير المعدة نيابة عنه)" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="تحت وساطة المورد (المورد يدير المعدة نيابة عنه)") ? "selected" : ""; ?>>تحت وساطة المورد (المورد يدير المعدة نيابة عنه)</option>
                            <option value="تابع للمورد (مملوكة للمورد نفسه)" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="تابع للمورد (مملوكة للمورد نفسه)") ? "selected" : ""; ?>>تابع للمورد (مملوكة للمورد نفسه)</option>
                            <option value="غير محدد" <?php echo (!empty($editData) && $editData['owner_supplier_relation']=="غير محدد") ? "selected" : ""; ?>>غير محدد</option>
                        </select>
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: الوثائق والتسجيلات -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-file-contract"></i> الوثائق والتسجيلات</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-address-card"></i>
                            رقم الترخيص/التسجيل
                        </label>
                        <input type="text" name="license_number" id="license_number" placeholder="مثال: VEH-2024-12345"
                               value="<?php echo isset($editData['license_number']) ? htmlspecialchars($editData['license_number']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-landmark"></i>
                            جهة الترخيص
                        </label>
                        <input type="text" name="license_authority" id="license_authority" placeholder="مثال: المرور، وزارة النقل"
                               value="<?php echo isset($editData['license_authority']) ? htmlspecialchars($editData['license_authority']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-calendar-times"></i>
                            تاريخ انتهاء الترخيص
                        </label>
                        <input type="date" name="license_expiry_date" id="license_expiry_date"
                               value="<?php echo isset($editData['license_expiry_date']) ? $editData['license_expiry_date'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-certificate"></i>
                            رقم شهادة الفحص
                        </label>
                        <input type="text" name="inspection_certificate_number" id="inspection_certificate_number" placeholder="رقم شهادة الفحص الفنية"
                               value="<?php echo isset($editData['inspection_certificate_number']) ? htmlspecialchars($editData['inspection_certificate_number']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-calendar-check"></i>
                            تاريخ آخر فحص
                        </label>
                        <input type="date" name="last_inspection_date" id="last_inspection_date"
                               value="<?php echo isset($editData['last_inspection_date']) ? $editData['last_inspection_date'] : ''; ?>" />
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: الموقع والتوفر -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-map-marker-alt"></i> الموقع والتوفر</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-location-arrow"></i>
                            الموقع الحالي
                        </label>
                        <input type="text" name="current_location" id="current_location" placeholder="مثال: منجم الذهب الشرقي، مستودع الخرطوم"
                               value="<?php echo isset($editData['current_location']) ? htmlspecialchars($editData['current_location']) : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-traffic-light"></i>
                            حالة التوفر
                        </label>
                        <select name="availability_status" id="availability_status">
                            <option value="متاحة للعمل" <?php echo (empty($editData) || $editData['availability_status']=="متاحة للعمل") ? "selected" : ""; ?>>متاحة للعمل</option>
                            <option value="قيد الاستخدام" <?php echo (!empty($editData) && $editData['availability_status']=="قيد الاستخدام") ? "selected" : ""; ?>>قيد الاستخدام</option>
                            <option value="تحت الصيانة" <?php echo (!empty($editData) && $editData['availability_status']=="تحت الصيانة") ? "selected" : ""; ?>>تحت الصيانة</option>
                            <option value="محجوزة" <?php echo (!empty($editData) && $editData['availability_status']=="محجوزة") ? "selected" : ""; ?>>محجوزة</option>
                            <option value="معطلة" <?php echo (!empty($editData) && $editData['availability_status']=="معطلة") ? "selected" : ""; ?>>معطلة</option>
                            <option value="في المستودع" <?php echo (!empty($editData) && $editData['availability_status']=="في المستودع") ? "selected" : ""; ?>>في المستودع</option>
                            <option value="مبيعة/مسحوبة" <?php echo (!empty($editData) && $editData['availability_status']=="مبيعة/مسحوبة") ? "selected" : ""; ?>>مبيعة/مسحوبة</option>
                        </select>
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: البيانات المالية والقيمة -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-dollar-sign"></i> البيانات المالية والقيمة</h6>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-money-bill-wave"></i>
                            القيمة المقدرة للمعدة (بالدولار)
                        </label>
                        <input type="number" name="estimated_value" id="estimated_value" placeholder="مثال: 150000" min="0" step="0.01"
                               value="<?php echo isset($editData['estimated_value']) ? $editData['estimated_value'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-calendar-day"></i>
                            سعر التأجير اليومي (بالدولار)
                        </label>
                        <input type="number" name="daily_rental_price" id="daily_rental_price" placeholder="مثال: 500" min="0" step="0.01"
                               value="<?php echo isset($editData['daily_rental_price']) ? $editData['daily_rental_price'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            سعر التأجير الشهري (بالدولار)
                        </label>
                        <input type="number" name="monthly_rental_price" id="monthly_rental_price" placeholder="مثال: 10000" min="0" step="0.01"
                               value="<?php echo isset($editData['monthly_rental_price']) ? $editData['monthly_rental_price'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-shield-alt"></i>
                            التأمين/الضمان
                        </label>
                        <select name="insurance_status" id="insurance_status">
                            <option value="">-- اختر حالة التأمين --</option>
                            <option value="مؤمن بالكامل" <?php echo (!empty($editData) && $editData['insurance_status']=="مؤمن بالكامل") ? "selected" : ""; ?>>مؤمن بالكامل</option>
                            <option value="مؤمن جزئياً" <?php echo (!empty($editData) && $editData['insurance_status']=="مؤمن جزئياً") ? "selected" : ""; ?>>مؤمن جزئياً</option>
                            <option value="غير مؤمن" <?php echo (!empty($editData) && $editData['insurance_status']=="غير مؤمن") ? "selected" : ""; ?>>غير مؤمن</option>
                            <option value="جاري التأمين" <?php echo (!empty($editData) && $editData['insurance_status']=="جاري التأمين") ? "selected" : ""; ?>>جاري التأمين</option>
                        </select>
                    </div>

                    <!-- ================================= -->
                    <!-- قسم: ملاحظات وسجل الصيانة -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-tools"></i> ملاحظات وسجل الصيانة</h6>
                    </div>

                    <div class="form-grid-full">
                        <label>
                            <i class="fas fa-comment-alt"></i>
                            ملاحظات عامة
                        </label>
                        <textarea name="general_notes" id="general_notes" rows="3" placeholder="مثال: معدة موثوقة، تحتاج إلى صيانة دورية كل 3 أشهر"><?php echo isset($editData['general_notes']) ? htmlspecialchars($editData['general_notes']) : ''; ?></textarea>
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-wrench"></i>
                            تاريخ آخر صيانة
                        </label>
                        <input type="date" name="last_maintenance_date" id="last_maintenance_date"
                               value="<?php echo isset($editData['last_maintenance_date']) ? $editData['last_maintenance_date'] : ''; ?>" />
                    </div>

                    <div>
                        <label>
                            <i class="fas fa-toggle-on"></i>
                            الحالة <span class="required-indicator">*</span>
                        </label>
                        <select name="status" id="status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1" <?php echo (!empty($editData) && $editData['status']=="1") ? "selected" : ""; ?>>متاحة</option>
                            <option value="0" <?php echo (!empty($editData) && $editData['status']=="0") ? "selected" : ""; ?>>مشغولة</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit">
                            <i class="fas fa-save"></i>
                            <?php echo !empty($editData) ? "تحديث المعدة" : "حفظ المعدة"; ?>
                        </button>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('projectForm').classList.remove('allforms-visible'); document.getElementById('projectForm').reset();">
                            <i class="fas fa-times"></i>
                            إلغاء
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php } ?>

    <!-- جدول المعدات -->
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i>
                قائمة المعدات
            </h5>
        </div>
        <div class="card-body">
            <!-- نظام الفلاتر -->
            <div class="filters-container">
                <div class="filters-header">
                    <h6><i class="fas fa-filter"></i> فلترة المعدات</h6>
                    <button type="button" class="btn-clear-filters" id="clearFiltersBtn">
                        <i class="fas fa-times-circle"></i> إلغاء الفلاتر
                    </button>
                </div>

                <div class="filters-grid">
                    <div class="filter-item">
                        <label><i class="fas fa-truck-loading"></i> فلترة بالمورد</label>
                        <select id="filterSupplier" class="filter-select">
                            <option value="">— جميع الموردين —</option>
                            <?php
                            $supplier_filter_scope_sql = "status = 1";
                            if (!$is_super_admin && $suppliers_has_company) {
                                $supplier_filter_scope_sql .= " AND company_id = $company_id";
                            }
                            $supplier_filter_query = "SELECT id, name FROM suppliers WHERE $supplier_filter_scope_sql ORDER BY name";
                            $supplier_filter_result = mysqli_query($conn, $supplier_filter_query);
                            if ($supplier_filter_result) while($supplier = mysqli_fetch_assoc($supplier_filter_result)) {
                                echo "<option value='" . htmlspecialchars($supplier['name']) . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-list-alt"></i> فلترة بالنوع</label>
                        <select id="filterType" class="filter-select">
                            <option value="">— جميع الأنواع —</option>
                            <?php
                            $type_filter_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                            $type_filter_result = mysqli_query($conn, $type_filter_query);
                            if ($type_filter_result) while($type_row = mysqli_fetch_assoc($type_filter_result)) {
                                echo "<option value='" . htmlspecialchars($type_row['type']) . "'>" . htmlspecialchars($type_row['type']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-toggle-on"></i> فلترة بالحالة</label>
                        <select id="filterStatus" class="filter-select">
                            <option value="">— جميع الحالات —</option>
                            <option value="نشط">نشط</option>
                            <option value="غير نشط">غير نشط</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-traffic-light"></i> فلترة بالتوفر</label>
                        <select id="filterAvailability" class="filter-select">
                            <option value="">— جميع حالات التوفر —</option>
                            <option value="متاحة للعمل">متاحة للعمل</option>
                            <option value="مشغولة حالياً">مشغولة حالياً</option>
                            <option value="تحت الصيانة">تحت الصيانة</option>
                            <option value="معطلة مؤقتاً">معطلة مؤقتاً</option>
                        </select>
                    </div>
                </div>

                <div class="filters-summary" id="filtersSummary" style="display: none;">
                    <span class="summary-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="summary-text"></span>
                </div>
            </div>

            <!-- أزرار إظهار/إخفاء المجموعات -->
            <div class="column-groups-toggle">
                <button type="button" class="toggle-group-btn active" data-group="basic" title="المعلومات الأساسية">
                    <i class="fas fa-info-circle"></i> أساسية
                </button>
                <button type="button" class="toggle-group-btn active" data-group="identification" title="بيانات التعريف">
                    <i class="fas fa-id-card"></i> التعريف
                </button>
                <button type="button" class="toggle-group-btn" data-group="manufacturing" title="بيانات الصنع">
                    <i class="fas fa-industry"></i> الصنع
                </button>
                <button type="button" class="toggle-group-btn" data-group="technical" title="الحالة الفنية">
                    <i class="fas fa-wrench"></i> فنية
                </button>
                <button type="button" class="toggle-group-btn active" data-group="ownership" title="بيانات الملكية">
                    <i class="fas fa-user-tie"></i> الملكية
                </button>
                <button type="button" class="toggle-group-btn active" data-group="status" title="الحالة والإجراءات">
                    <i class="fas fa-toggle-on"></i> الحالة
                </button>
                <button type="button" class="toggle-all-btn" title="إظهار/إخفاء الكل">
                    <i class="fas fa-eye"></i> الكل
                </button>
            </div>

            <table id="projectsTable" class="display nowrap">
                <thead>
                    <tr>
                        <th data-group="basic"><i class="fas fa-hashtag"></i> #</th>
                        <th data-group="basic"><i class="fas fa-truck-loading"></i> المورد</th>
                        <th data-group="basic"><i class="fas fa-barcode"></i> كود المعدة</th>
                        <th data-group="identification"><i class="fas fa-hashtag"></i> رقم تسلسلي</th>
                        <th data-group="basic"><i class="fas fa-list-alt"></i> النوع</th>
                        <th data-group="basic"><i class="fas fa-tag"></i> الاسم</th>
                        <th data-group="manufacturing"><i class="fas fa-car"></i> الموديل</th>
                        <th data-group="manufacturing"><i class="fas fa-calendar"></i> سنة الصنع</th>
                        <th data-group="technical"><i class="fas fa-cogs"></i> حالة المعدة</th>
                        <th data-group="ownership"><i class="fas fa-user"></i> المالك</th>
                        <th data-group="technical"><i class="fas fa-traffic-light"></i> التوفر</th>
                        <th data-group="status"><i class="fas fa-toggle-on"></i> الحالة</th>
                        <th data-group="status"><i class="fas fa-sliders-h"></i> إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $equipment_scope_where = "1=1";
                    if (!$is_super_admin) {
                        $equipment_scope_where = "m.company_id = $company_id";
                    } elseif ($selected_project_id > 0) {
                        $equipment_scope_where = "EXISTS (
                            SELECT 1 FROM operations so
                            WHERE so.equipment = m.id AND so.project_id = $selected_project_id
                        )";
                    }

                    $query2 = "
                        SELECT
                            m.id,
                            s.name AS supplier_name,
                            m.type,
                            m.code,
                            m.name,
                            m.status,
                            m.serial_number,
                            m.model,
                            m.manufacturing_year,
                            m.equipment_condition,
                            m.actual_owner_name,
                            m.availability_status,
                            o.project_id,
                            o.status AS operation_status,
                            COUNT(DISTINCT d.id) AS drivers_count
                        FROM equipments m
                        JOIN suppliers s ON m.suppliers = s.id
                        LEFT JOIN operations o
                            ON o.equipment = m.id
                            AND o.status = '1'
                        LEFT JOIN equipment_drivers ed
                            ON ed.equipment_id = m.id
                        LEFT JOIN drivers d
                            ON d.id = ed.driver_id
                            AND ed.status = '1'
                        WHERE $equipment_scope_where
                        GROUP BY m.id
                        ORDER BY m.id DESC
                    ";
                    $result = mysqli_query($conn, $query2);
                    $i = 1;
                    if ($result) while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td><strong>" . $i++ . "</strong></td>";
                        echo "<td><strong class='supplier-name'>" . htmlspecialchars($row['supplier_name']) . "</strong></td>";
                        echo "<td><span class='mono code-badge'>" . htmlspecialchars($row['code']) . "</span></td>";

                        // رقم تسلسلي
                        $serial = !empty($row['serial_number'])
                            ? "<span class='mono'>" . htmlspecialchars($row['serial_number']) . "</span>"
                            : "<span class='text-muted'>غير محدد</span>";
                        echo "<td>" . $serial . "</td>";

                        // نوع المعدة
                        $type_icon = $row['type'] == "1" ? "fa-tractor" : "fa-truck-moving";
                        $type_text = $row['type'] == "1" ? "حفار" : "قلاب";
                        echo "<td><span class='badge-type'><i class='fas $type_icon'></i> $type_text</span></td>";

                        // اسم المعدة (تهيئة المتغير)
                        $name_display = "<a class='client-name-link' href='equipment_profile.php?id=" . intval($row['id']) . "'><strong>" . htmlspecialchars($row['name']) . "</strong></a>";

                        // المشروع النشط
                        if (!empty($row['project'])) {
                            $p_res = mysqli_query($conn, "SELECT name FROM project WHERE id='" . $row['project'] . "'");
                            if ($p_res && mysqli_num_rows($p_res) > 0) {
                                $p = mysqli_fetch_assoc($p_res);
                                $name_display .= "<br><span class='project-link'><i class='fas fa-project-diagram'></i> " . htmlspecialchars($p['name']) . "</span>";
                            }
                        }

                        // عدد السائقين النشطين
                        if ($row['drivers_count'] > 0) {
                            $name_display .= "<br><span class='extra-info'><i class='fas fa-users'></i> " . $row['drivers_count'] . " سائق</span>";
                        }

                        echo "<td>" . $name_display . "</td>";

                        // الموديل
                        $model = !empty($row['model']) ? htmlspecialchars($row['model']) : "<span class='text-muted'>غير محدد</span>";
                        echo "<td>" . $model . "</td>";

                        // سنة الصنع
                        $manufacturing_year = !empty($row['manufacturing_year']) ? $row['manufacturing_year'] : "<span class='text-muted'>غير محدد</span>";
                        echo "<td>" . $manufacturing_year . "</td>";

                        // حالة المعدة
                        $equipment_condition = !empty($row['equipment_condition']) ? htmlspecialchars($row['equipment_condition']) : "<span class='text-muted'>غير محدد</span>";
                        echo "<td>" . $equipment_condition . "</td>";

                        // المالك
                        $owner = !empty($row['actual_owner_name']) ? htmlspecialchars($row['actual_owner_name']) : "<span class='text-muted'>غير محدد</span>";
                        echo "<td>" . $owner . "</td>";

                        // التوفر
                        $availability = !empty($row['availability_status']) ? htmlspecialchars($row['availability_status']) : "متاحة للعمل";
                        echo "<td>" . $availability . "</td>";

                        // الحالة
                        if (!empty($row['project_id']) && $row['operation_status'] == "1") {
                            echo "<td><span class='badge-working'><i class='fas fa-spinner fa-spin'></i> قيد التشغيل</span></td>";
                        } else {
                            if ($row['status'] == "1") {
                                echo "<td><span class='badge-available'><i class='fas fa-check-circle'></i> متاحة</span></td>";
                            } else {
                                echo "<td><span class='badge-busy'><i class='fas fa-times-circle'></i> مشغولة</span></td>";
                            }
                        }

                        // الإجراءات
                                                echo "<td>";
                                                echo "<a href='javascript:void(0)' class='action-btn view viewEquipmentBtn' data-id='" . $row['id'] . "' title='عرض التفاصيل'>
                                                        <i class='fas fa-eye'></i>
                                                    </a>";
                                                if ($_SESSION['user']['role'] == "3" || $_SESSION['user']['role'] == "10") {
                                                                                                                echo "<a href='add_drivers.php?equipment_id=" . $row['id'] . "' class='action-btn btn-driver' title='إدارة المشغلين'>
                                                                        <i class='fas fa-user-cog'></i>
                                                                    </a>";
                                                } else {
                                                                                                                echo "<a href='equipments.php?edit=" . $row['id'] . "' class='action-btn btn-edit' title='تعديل'>
                                                                        <i class='fas fa-edit'></i>
                                                                    </a>";
                                                        // يمكن إضافة زر حذف هنا إذا لزم الأمر
                                                }
                                                echo "</td>";

                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- نافذة تفاصيل المعدة تُولَّد ديناميكياً عبر النظام الموحّد EmsDetailsModal (assets/js/ems-details-modal.js) -->

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
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
    (function () {
        $(document).ready(function () {
            var table = $('#projectsTable').DataTable({
                responsive: true,
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

            // نظام إظهار/إخفاء المجموعات
            var columnGroups = {
                'basic': [0, 1, 2, 4, 5],        // #، المورد، كود المعدة، النوع، الاسم
                'identification': [3],            // رقم تسلسلي
                'manufacturing': [6, 7],          // الموديل، سنة الصنع
                'technical': [8, 10],             // حالة المعدة، التوفر
                'ownership': [9],                 // المالك
                'status': [11, 12]                // الحالة، الإجراءات
            };

            // حفظ حالة المجموعات (الصنع والفنية مخفيتين بشكل افتراضي)
            var groupsState = {
                'basic': true,
                'identification': true,
                'manufacturing': false,
                'technical': false,
                'ownership': true,
                'status': true
            };

            // إخفاء الأعمدة المخفية بشكل افتراضي عند التحميل
            columnGroups['manufacturing'].forEach(function(colIndex) {
                table.column(colIndex).visible(false);
            });
            columnGroups['technical'].forEach(function(colIndex) {
                table.column(colIndex).visible(false);
            });

            // نظام الفلترة الاحترافي
            var activeFilters = {
                supplier: '',
                type: '',
                status: '',
                availability: ''
            };

            // تهيئة الفلاتر
            $('#filterSupplier, #filterType, #filterStatus, #filterAvailability').on('change', function() {
                var filterType = $(this).attr('id').replace('filter', '').toLowerCase();
                activeFilters[filterType] = $(this).val();
                applyFilters();
                updateFiltersSummary();
            });

            // تطبيق الفلاتر
            function applyFilters() {
                $.fn.dataTable.ext.search.push(
                    function(settings, data, dataIndex) {
                        // data[1] = المورد
                        // data[4] = النوع (يحتوي على نص مثل "حفار" أو "قلاب")
                        // data[11] = الحالة (يحتوي على "نشط" أو "غير نشط")
                        // data[10] = التوفر

                        var supplierMatch = true;
                        var typeMatch = true;
                        var statusMatch = true;
                        var availabilityMatch = true;

                        // فلترة المورد
                        if (activeFilters.supplier !== '') {
                            supplierMatch = data[1].indexOf(activeFilters.supplier) !== -1;
                        }

                        // فلترة النوع
                        if (activeFilters.type !== '') {
                            typeMatch = data[4].indexOf(activeFilters.type) !== -1;
                        }

                        // فلترة الحالة
                        if (activeFilters.status !== '') {
                            statusMatch = data[11].indexOf(activeFilters.status) !== -1;
                        }

                        // فلترة التوفر
                        if (activeFilters.availability !== '') {
                            availabilityMatch = data[10].indexOf(activeFilters.availability) !== -1;
                        }

                        return supplierMatch && typeMatch && statusMatch && availabilityMatch;
                    }
                );

                table.draw();

                // إزالة دالة البحث بعد التطبيق لتجنب التكرار
                $.fn.dataTable.ext.search.pop();
            }

            // تحديث ملخص الفلاتر
            function updateFiltersSummary() {
                var activeCount = 0;
                var summaryParts = [];

                if (activeFilters.supplier) {
                    activeCount++;
                    summaryParts.push('المورد: ' + activeFilters.supplier);
                }
                if (activeFilters.type) {
                    activeCount++;
                    summaryParts.push('النوع: ' + activeFilters.type);
                }
                if (activeFilters.status) {
                    activeCount++;
                    summaryParts.push('الحالة: ' + activeFilters.status);
                }
                if (activeFilters.availability) {
                    activeCount++;
                    summaryParts.push('التوفر: ' + activeFilters.availability);
                }

                var $summary = $('#filtersSummary');
                if (activeCount > 0) {
                    $summary.find('.summary-text').text(
                        'تم تطبيق ' + activeCount + ' فلتر: ' + summaryParts.join(' | ')
                    );
                    $summary.slideDown(300);
                } else {
                    $summary.slideUp(300);
                }
            }

            // إلغاء جميع الفلاتر
            $('#clearFiltersBtn').on('click', function() {
                activeFilters = {
                    supplier: '',
                    type: '',
                    status: '',
                    availability: ''
                };

                $('#filterSupplier, #filterType, #filterStatus, #filterAvailability').val('');
                applyFilters();
                updateFiltersSummary();

                // تأثير بصري
                $(this).addClass('btn-clear-active');
                setTimeout(function() {
                    $('#clearFiltersBtn').removeClass('btn-clear-active');
                }, 300);
            });

            // وظيفة إظهار/إخفاء مجموعة
            function toggleGroup(groupName) {
                var columns = columnGroups[groupName];
                var isVisible = groupsState[groupName];

                columns.forEach(function(colIndex) {
                    table.column(colIndex).visible(!isVisible);
                });

                groupsState[groupName] = !isVisible;
            }

            // معالج النقر على أزرار المجموعات
            $('.toggle-group-btn').on('click', function() {
                var groupName = $(this).data('group');
                toggleGroup(groupName);
                $(this).toggleClass('active');
            });

            // زر إظهار/إخفاء الكل
            var allVisible = true;
            $('.toggle-all-btn').on('click', function() {
                allVisible = !allVisible;

                Object.keys(columnGroups).forEach(function(groupName) {
                    var columns = columnGroups[groupName];
                    columns.forEach(function(colIndex) {
                        table.column(colIndex).visible(allVisible);
                    });
                    groupsState[groupName] = allVisible;
                });

                if (allVisible) {
                    $('.toggle-group-btn').addClass('active');
                    $(this).html('<i class="fas fa-eye"></i> الكل');
                } else {
                    $('.toggle-group-btn').removeClass('active');
                    $(this).html('<i class="fas fa-eye-slash"></i> إخفاء الكل');
                }
            });
        });

        const toggleFormBtn = document.getElementById('toggleForm');
        const equipmentForm = document.getElementById('projectForm');
        const projectSelect = document.getElementById('selected_project_id');

        if (toggleFormBtn && equipmentForm) {
            toggleFormBtn.addEventListener('click', function () {
                equipmentForm.classList.toggle('allforms-visible');
            });
        }

        if (projectSelect) {
            projectSelect.addEventListener('change', function () {
                if (this.value) {
                    document.getElementById('projectSelectForm').submit();
                }
            });
        }

        // تحميل بيانات التعديل عند تحميل الصفحة
        <?php if (!empty($editData)) { ?>
        $(document).ready(function() {
            // عرض الفورم
            $('#projectForm').addClass('allforms-visible');

            // التمرير للفورم
            $('html, body').animate({
                scrollTop: $('#projectForm').offset().top - 100
            }, 500);
        });
        <?php } ?>

        // Equipment view modal — عبر النظام الموحّد EmsDetailsModal
        const eqCanEdit = <?php echo ($_SESSION['user']['role'] != "3" && $_SESSION['user']['role'] != "10") ? 'true' : 'false'; ?>;

        function eqVal(value) {
            return (value !== null && value !== undefined && value !== '') ? value : 'غير محدد';
        }
        function formatCurrency(value) {
            if (value === null || value === undefined || value === '') return 'غير محدد';
            const num = parseFloat(value);
            if (Number.isNaN(num)) return value;
            return '$' + num.toLocaleString();
        }
        function formatType(value) {
            if (!value) return 'غير محدد';
            return String(value) === '1' ? 'حفار' : 'قلاب';
        }
        function formatStatus(value) {
            if (value === null || value === undefined || value === '') return 'غير محدد';
            return String(value) === '1' ? 'متاحة' : 'مشغولة';
        }

        function buildEquipmentFields(data) {
            return [
                { label: 'كود المعدة', value: eqVal(data.code), icon: 'fas fa-barcode' },
                { label: 'اسم المعدة', value: eqVal(data.name), icon: 'fas fa-tag', size: 'lg' },
                { label: 'نوع المعدة', value: formatType(data.type), icon: 'fas fa-tools' },
                { label: 'المورد', value: eqVal(data.supplier_name), icon: 'fas fa-truck-loading', size: 'lg' },
                { label: 'المشروع', value: eqVal(data.project_name), icon: 'fas fa-project-diagram', size: 'lg' },
                { label: 'المنجم', value: eqVal(data.mine_name), icon: 'fas fa-mountain' },
                { label: 'الرقم التسلسلي', value: eqVal(data.serial_number), icon: 'fas fa-hashtag' },
                { label: 'رقم الهيكل', value: eqVal(data.chassis_number), icon: 'fas fa-car' },
                { label: 'الشركة المصنعة', value: eqVal(data.manufacturer), icon: 'fas fa-industry' },
                { label: 'الموديل', value: eqVal(data.model), icon: 'fas fa-car-side' },
                { label: 'سنة الصنع', value: eqVal(data.manufacturing_year), icon: 'fas fa-calendar' },
                { label: 'سنة الاستيراد', value: eqVal(data.import_year), icon: 'fas fa-calendar-plus' },
                { label: 'حالة المعدة', value: eqVal(data.equipment_condition), icon: 'fas fa-cogs' },
                { label: 'ساعات التشغيل', value: data.operating_hours ? (data.operating_hours + ' ساعة') : 'غير محدد', icon: 'fas fa-clock' },
                { label: 'حالة المحرك', value: eqVal(data.engine_condition), icon: 'fas fa-car-crash' },
                { label: 'حالة الإطارات', value: eqVal(data.tires_condition), icon: 'fas fa-circle-notch' },
                { label: 'اسم المالك', value: eqVal(data.actual_owner_name), icon: 'fas fa-user' },
                { label: 'نوع المالك', value: eqVal(data.owner_type), icon: 'fas fa-briefcase' },
                { label: 'هاتف المالك', value: eqVal(data.owner_phone), icon: 'fas fa-phone' },
                { label: 'علاقة المالك بالمورد', value: eqVal(data.owner_supplier_relation), icon: 'fas fa-handshake' },
                { label: 'رقم الترخيص', value: eqVal(data.license_number), icon: 'fas fa-address-card' },
                { label: 'جهة الترخيص', value: eqVal(data.license_authority), icon: 'fas fa-landmark' },
                { label: 'انتهاء الترخيص', value: eqVal(data.license_expiry_date), icon: 'fas fa-calendar-times' },
                { label: 'رقم شهادة الفحص', value: eqVal(data.inspection_certificate_number), icon: 'fas fa-certificate' },
                { label: 'آخر فحص', value: eqVal(data.last_inspection_date), icon: 'fas fa-calendar-check' },
                { label: 'الموقع الحالي', value: eqVal(data.current_location), icon: 'fas fa-map-marker-alt', size: 'lg' },
                { label: 'حالة التوفر', value: eqVal(data.availability_status), icon: 'fas fa-traffic-light' },
                { label: 'القيمة المقدرة', value: formatCurrency(data.estimated_value), icon: 'fas fa-money-bill-wave' },
                { label: 'سعر التأجير اليومي', value: formatCurrency(data.daily_rental_price), icon: 'fas fa-calendar-day' },
                { label: 'سعر التأجير الشهري', value: formatCurrency(data.monthly_rental_price), icon: 'fas fa-calendar-alt' },
                { label: 'التأمين/الضمان', value: eqVal(data.insurance_status), icon: 'fas fa-shield-alt' },
                { label: 'ملاحظات عامة', value: eqVal(data.general_notes), icon: 'fas fa-comment-alt', size: 'full' },
                { label: 'آخر صيانة', value: eqVal(data.last_maintenance_date), icon: 'fas fa-wrench' },
                { label: 'الحالة', value: formatStatus(data.status), icon: 'fas fa-toggle-on', type: 'status', tone: String(data.status) === '1' ? 'active' : 'inactive' }
            ];
        }

        function equipmentActions(equipmentId) {
            const actions = [];
            if (eqCanEdit) {
                actions.push({ label: 'تعديل المعدة', icon: 'fas fa-edit', variant: 'primary',
                    onClick: function () { window.location.href = 'equipments.php?edit=' + equipmentId; } });
            }
            actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });
            return actions;
        }

        $(document).on('click', '.viewEquipmentBtn', function() {
            const equipmentId = $(this).data('id');
            if (!equipmentId) return;

            // فتح فوري بحالة تحميل
            EmsDetailsModal.open({
                title: 'بيانات المعدة',
                icon: 'fas fa-truck-monster',
                sections: [{ title: 'تحميل البيانات', icon: 'fas fa-spinner',
                    html: '<div style="padding:20px;text-align:center;color:var(--t2)"><i class="fas fa-spinner fa-spin"></i> جار التحميل...</div>' }],
                actions: equipmentActions(equipmentId)
            });

            $.ajax({
                url: 'get_equipment_details.php',
                type: 'GET',
                data: { id: equipmentId },
                dataType: 'json',
                success: function(response) {
                    if (!response.success || !response.data) {
                        EmsDetailsModal.setSection(0, { title: 'خطأ', icon: 'fas fa-exclamation-triangle',
                            html: '<div style="padding:16px;text-align:center;color:#c0392b">تعذر تحميل البيانات</div>' });
                        return;
                    }
                    EmsDetailsModal.open({
                        title: 'بيانات المعدة',
                        icon: 'fas fa-truck-monster',
                        fields: buildEquipmentFields(response.data),
                        actions: equipmentActions(equipmentId)
                    });
                },
                error: function() {
                    EmsDetailsModal.setSection(0, { title: 'خطأ', icon: 'fas fa-exclamation-triangle',
                        html: '<div style="padding:16px;text-align:center;color:#c0392b">تعذر الاتصال بالخادم</div>' });
                }
            });
        });

        // إغلاق متوافق مع الاستدعاءات القديمة
        function closeEquipmentModal() { if (window.EmsDetailsModal) EmsDetailsModal.close(); }

        // Toggle Form Functionality
    })();
</script>

<style>
/* نظام الفلترة الاحترافي */
.filters-container {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 22px;
    box-shadow: var(--shadow-sm);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
}

.filters-header h6 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filters-header h6 i {
    color: var(--gold);
    font-size: 1.2rem;
}

.btn-clear-filters {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    background: var(--red-soft);
    color: var(--red);
    border: 1.5px solid rgba(220,38,38,.18);
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all var(--ease);
    font-family: 'Cairo', sans-serif;
}

.btn-clear-filters:hover {
    background: var(--red);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 16px rgba(220,38,38,.35);
}

.btn-clear-active {
    animation: btnClearPulse 0.3s ease;
}

@keyframes btnClearPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 12px;
}

.filter-item {
    display: flex;
    flex-direction: column;
}

.filter-item label {
    font-weight: 700;
    color: var(--txt);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
}

.filter-item label i {
    color: var(--gold);
}

.filter-select {
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.92rem;
    font-family: 'Cairo', sans-serif;
    transition: all var(--ease);
    background: var(--surface);
    color: var(--txt);
    cursor: pointer;
}

.filter-select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-soft);
}

.filter-select:hover {
    border-color: var(--navy);
}

.filters-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: var(--blue-soft);
    border: 1.5px solid rgba(37,99,235,.25);
    border-radius: var(--radius);
    margin-top: 16px;
    animation: slideDown 0.3s ease;
}

.filters-summary .summary-icon {
    flex-shrink: 0;
    color: var(--blue);
    font-size: 1.1rem;
}

.filters-summary .summary-text {
    color: var(--blue);
    font-weight: 600;
    font-size: 0.9rem;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .filters-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .btn-clear-filters {
        width: 100%;
        justify-content: center;
    }
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

#importExcelModal input[type="file"]:hover {
    border-color: #94a3b8;
    background: #f1f5f9;
}

#importExcelModal button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(22,163,74,0.35);
}

#importExcelModal button[type="button"]:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
</style>

</div> <!-- closing main div -->
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>
</html>

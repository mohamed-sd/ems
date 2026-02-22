<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

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
    $project_check_query = "SELECT id, name, project_code FROM project WHERE id = $selected_project_id AND status = '1'";
    $project_check_result = mysqli_query($conn, $project_check_query);
    if ($project_check_result && mysqli_num_rows($project_check_result) > 0) {
        $selected_project = mysqli_fetch_assoc($project_check_result);
    } else {
        unset($_SESSION['equipments_project_id']);
        $selected_project_id = 0;
    }
}

$projects_result = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name");

$page_title = "إدارة المعدات";
include("../inheader.php");
// include("../insidebar.php");

// معالجة رسالة النجاح
$success_msg = '';
if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<!-- Font Awesome من CDN لضمان ظهور الأيقونات بشكل صحيح -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

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
            $added_count_row = mysqli_fetch_assoc($added_count_result);
            $current_added = intval($added_count_row['added_count']);
            
            // التحقق من عدم تجاوز العدد المتعاقد عليه
            if ($current_added >= $contracted_count) {
                $success_msg = "⚠️ تحذير: تم الوصول للحد الأقصى! العدد المتعاقد عليه: $contracted_count | المضاف حالياً: $current_added. لا يمكن إضافة المزيد من المعدات.";
                goto skip_save;
            }
        }
    }

    if ($edit_id > 0) {
        // تعديل
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
                WHERE id='$edit_id'";
        $msg = "تم+تعديل+المعدة+بنجاح+✅";
    } else {
        // إضافة
        $sql = "INSERT INTO equipments 
                (suppliers, code, type, name, status, serial_number, chassis_number, 
                 manufacturer, model, manufacturing_year, import_year, 
                 equipment_condition, operating_hours, engine_condition, tires_condition,
                 actual_owner_name, owner_type, owner_phone, owner_supplier_relation,
                 license_number, license_authority, license_expiry_date, 
                 inspection_certificate_number, last_inspection_date,
                 current_location, availability_status,
                 estimated_value, daily_rental_price, monthly_rental_price, insurance_status,
                 general_notes, last_maintenance_date) 
                VALUES 
                ('$suppliers', '$code', '$type', '$name', '$status', '$serial_number', '$chassis_number',
                 '$manufacturer', '$model', $manufacturing_year, $import_year,
                 '$equipment_condition', $operating_hours, '$engine_condition', '$tires_condition',
                 '$actual_owner_name', '$owner_type', '$owner_phone', '$owner_supplier_relation',
                 '$license_number', '$license_authority', $license_expiry_date,
                 '$inspection_certificate_number', $last_inspection_date,
                 '$current_location', '$availability_status',
                 $estimated_value, $daily_rental_price, $monthly_rental_price, '$insurance_status',
                 '$general_notes', $last_maintenance_date)";
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
    $res = mysqli_query($conn, "SELECT * FROM equipments WHERE id='$editId'");
    if ($res && mysqli_num_rows($res) > 0) {
        $editData = mysqli_fetch_assoc($res);
    }
}
?>

<div class="main">
    <?php if (!$is_role10) { ?>
    <div class="project-picker">
        <form method="post" id="projectSelectForm">
            <label for="selected_project_id">اختر المشروع</label>
            <select name="selected_project_id" id="selected_project_id" required>
                <option value="">-- اختر المشروع --</option>
                <option value="all" <?php echo $show_all_projects ? 'selected' : ''; ?>>الكل</option>
                <?php
                if ($projects_result) {
                    while ($project_row = mysqli_fetch_assoc($projects_result)) {
                        $selected = ($selected_project_id == $project_row['id']) ? 'selected' : '';
                        $project_label = htmlspecialchars($project_row['name']);
                        if (!empty($project_row['project_code'])) {
                            $project_label .= ' (' . htmlspecialchars($project_row['project_code']) . ')';
                        }
                        echo "<option value='" . intval($project_row['id']) . "' $selected>" . $project_label . "</option>";
                    }
                }
                ?>
            </select>
        </form>
    </div>
    <?php } ?>

    <?php if ($show_all_projects) { ?>
        <div class="page-header">
            <h1 class="page-title">
                <div class="title-icon"><i class="fas fa-layer-group"></i></div>
                عرض جميع المشاريع
            </h1>
            <div class="page-header-actions">
                <a href="../main/dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    <?php } elseif (!empty($selected_project)) { ?>
        <!-- عنوان المشروع المحدد -->
        <div class="page-header">
            <h1 class="page-title">
                <div class="title-icon"><i class="fas fa-hard-hat"></i></div>
                <div>
                    <div><?php echo htmlspecialchars($selected_project['name']); ?></div>
                    <?php if (!empty($selected_project['project_code'])) { ?>
                        <small class="page-subtitle">
                            <i class="fas fa-barcode"></i>
                            كود المشروع: <?php echo htmlspecialchars($selected_project['project_code']); ?>
                        </small>
                    <?php } ?>
                </div>
            </h1>
            <div class="page-header-actions">
                <a href="../main/dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                    <i class="fas fa-plus-circle"></i> إضافة معدة جديدة
                </a>
            </div>
        </div>
    <?php } else { ?>
        <div class="card notice-card">
            <strong>يرجى اختيار مشروع لعرض البيانات.</strong>
        </div>
    <?php } ?>


    <?php if (!empty($success_msg)): 
        $isSuccess = strpos($success_msg, '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($selected_project) && !$show_all_projects) { ?>
        <div class="card notice-card">
            <strong>يرجى اختيار مشروع لعرض البيانات.</strong>
        </div>
        <script>
            document.getElementById('selected_project_id').addEventListener('change', function () {
                if (this.value) {
                    document.getElementById('projectSelectForm').submit();
                }
            });
        </script>
    <?php } ?>

    <!-- قسم الإحصائيات -->
    <div id="contractStats" class="contract-stats">
        <h5 class="stats-title">
            <i class="fas fa-chart-line"></i>
            إحصائيات عقد المنجم
        </h5>
        
        <!-- جدول الموردين -->
        <div id="suppliersSection" class="suppliers-section">
            <h6 class="suppliers-title">
                <i class="fas fa-users"></i> عقود الموردين في هذا المشروع
            </h6>
            <div class="table-scroll">
                <table class="suppliers-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المورد</th>
                            <th>الساعات المتعاقد عليها</th>
                            <th>عدد المعدات</th>
                            <th><span class="legend-dot legend-basic">■</span> أساسية</th>
                            <th><span class="legend-dot legend-backup">■</span> احتياطية</th>
                            <th>توزيع المعدات والساعات</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody">
                        <tr>
                            <td colspan="7" class="suppliers-empty">
                                <i class="fas fa-info-circle"></i> لا توجد بيانات
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="suppliers-total-row">
                            <td colspan="2" class="suppliers-total-label">الإجمالي</td>
                            <td id="total_supplier_hours" class="suppliers-total-value">0</td>
                            <td id="total_supplier_equipment" class="suppliers-total-value">0</td>
                            <td id="total_supplier_basic" class="suppliers-total-value">0</td>
                            <td id="total_supplier_backup" class="suppliers-total-value">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-card-value" id="stat_total_hours">0</div>
                <div class="stat-card-label">إجمالي الساعات المتعاقد عليها</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-cogs"></i></div>
                <div class="stat-card-value" id="stat_equipment_count">0</div>
                <div class="stat-card-label">عدد المعدات المسجلة</div>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['user']['role'] != "10") { ?>
    <!-- فورم إضافة / تعديل معدة -->
    <form id="projectForm" action="" method="post" style="display:<?php echo !empty($editData) ? 'block' : 'none'; ?>;">
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
                    
                    <!-- المشروع مخفي لأنه محدد مسبقاً -->
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo $selected_project_id; ?>">
                    
                    <div>
                        <label>
                            <i class="fas fa-mountain"></i>
                            المنجم <span class="required-indicator">*</span>
                        </label>
                        <select name="mine_id" id="mine_id" >
                            <option value="">-- اختر المنجم --</option>
                            <?php
                            $mines_query = "SELECT id, mine_name FROM mines WHERE project_id = $selected_project_id AND status='1' ORDER BY mine_name";
                            $mines_result = mysqli_query($conn, $mines_query);
                            while ($mine = mysqli_fetch_assoc($mines_result)) {
                                $selected = (!empty($editData) && $editData['mine_id'] == $mine['id']) ? "selected" : "";
                                echo "<option value='" . $mine['id'] . "' $selected>" . htmlspecialchars($mine['mine_name']) . "</option>";
                            }
                            ?>
                        </select>
                        <span class="dropdown-hint">اختر المنجم أولاً</span>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-file-signature"></i>
                            عقد المشروع <span class="required-indicator">*</span>
                        </label>
                        <select name="contract_id" id="contract_id"  disabled>
                            <option value="">-- اختر العقد --</option>
                        </select>
                        <span class="dropdown-hint">يتم تحميله بعد اختيار المنجم</span>
                    </div>
                    
                    <div>
                        <label>
                            <i class="fas fa-truck-loading"></i>
                            المورد <span class="required-indicator">*</span>
                        </label>
                        <select name="suppliers" id="suppliers" required>
                            <option value="">-- اختر المورد --</option>
                            <?php
                            $supplier_query = "SELECT id, name FROM suppliers WHERE status = 1 ORDER BY name";
                            $supplier_result = mysqli_query($conn, $supplier_query);
                            while($supplier = mysqli_fetch_assoc($supplier_result)) {
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
                        <button type="button" class="btn-secondary" onclick="document.getElementById('projectForm').style.display='none'; document.getElementById('projectForm').reset();">
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
                    $project_filter_where = '';
                    if ($selected_project_id > 0) {
                        $project_filter_where = "WHERE o.project_id = $selected_project_id";
                      
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
                        $project_filter_where
                        GROUP BY m.id
                        ORDER BY m.id DESC
                    ";
                    $result = mysqli_query($conn, $query2);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
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
                        $name_display = "<strong>" . htmlspecialchars($row['name']) . "</strong>";
                        
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

<!-- Modal عرض تفاصيل المعدة -->
<div id="viewEquipmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> عرض بيانات المعدة</h5>
            <button class="close-modal" id="closeEquipmentModal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المعدة</div>
                    <div class="view-item-value" id="view_eq_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-tag"></i> اسم المعدة</div>
                    <div class="view-item-value" id="view_eq_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-tools"></i> نوع المعدة</div>
                    <div class="view-item-value" id="view_eq_type">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-truck-loading"></i> المورد</div>
                    <div class="view-item-value" id="view_eq_supplier">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> المشروع</div>
                    <div class="view-item-value" id="view_eq_project">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-mountain"></i> المنجم</div>
                    <div class="view-item-value" id="view_eq_mine">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-hashtag"></i> الرقم التسلسلي</div>
                    <div class="view-item-value" id="view_eq_serial">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-car"></i> رقم الهيكل</div>
                    <div class="view-item-value" id="view_eq_chassis">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> الشركة المصنعة</div>
                    <div class="view-item-value" id="view_eq_manufacturer">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-car-side"></i> الموديل</div>
                    <div class="view-item-value" id="view_eq_model">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-calendar"></i> سنة الصنع</div>
                    <div class="view-item-value" id="view_eq_year">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-calendar-plus"></i> سنة الاستيراد</div>
                    <div class="view-item-value" id="view_eq_import_year">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-cogs"></i> حالة المعدة</div>
                    <div class="view-item-value" id="view_eq_condition">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-clock"></i> ساعات التشغيل</div>
                    <div class="view-item-value" id="view_eq_hours">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-car-crash"></i> حالة المحرك</div>
                    <div class="view-item-value" id="view_eq_engine">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-circle-notch"></i> حالة الإطارات</div>
                    <div class="view-item-value" id="view_eq_tires">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> اسم المالك</div>
                    <div class="view-item-value" id="view_eq_owner">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-briefcase"></i> نوع المالك</div>
                    <div class="view-item-value" id="view_eq_owner_type">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-phone"></i> هاتف المالك</div>
                    <div class="view-item-value" id="view_eq_owner_phone">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-handshake"></i> علاقة المالك بالمورد</div>
                    <div class="view-item-value" id="view_eq_owner_relation">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-address-card"></i> رقم الترخيص</div>
                    <div class="view-item-value" id="view_eq_license">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-landmark"></i> جهة الترخيص</div>
                    <div class="view-item-value" id="view_eq_license_authority">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-calendar-times"></i> انتهاء الترخيص</div>
                    <div class="view-item-value" id="view_eq_license_expiry">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-certificate"></i> رقم شهادة الفحص</div>
                    <div class="view-item-value" id="view_eq_inspection">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-calendar-check"></i> آخر فحص</div>
                    <div class="view-item-value" id="view_eq_last_inspection">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> الموقع الحالي</div>
                    <div class="view-item-value" id="view_eq_location">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-traffic-light"></i> حالة التوفر</div>
                    <div class="view-item-value" id="view_eq_availability">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-money-bill-wave"></i> القيمة المقدرة</div>
                    <div class="view-item-value" id="view_eq_value">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-calendar-day"></i> سعر التأجير اليومي</div>
                    <div class="view-item-value" id="view_eq_daily">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-calendar-alt"></i> سعر التأجير الشهري</div>
                    <div class="view-item-value" id="view_eq_monthly">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-shield-alt"></i> التأمين/الضمان</div>
                    <div class="view-item-value" id="view_eq_insurance">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-comment-alt"></i> ملاحظات عامة</div>
                    <div class="view-item-value" id="view_eq_notes">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-wrench"></i> آخر صيانة</div>
                    <div class="view-item-value" id="view_eq_last_maintenance">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div class="view-item-value" id="view_eq_status">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <?php if ($_SESSION['user']['role'] != "3" && $_SESSION['user']['role'] != "10") { ?>
            <a id="viewEquipmentEditBtn" class="btn-modal btn-modal-save" style="text-decoration: none;">
                <i class="fas fa-edit"></i> تعديل المعدة
            </a>
            <?php } ?>
            <button type="button" class="btn-modal btn-modal-cancel" id="closeEquipmentModalFooter">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

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
                    "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
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
                equipmentForm.style.display = equipmentForm.style.display === "none" ? "block" : "none";
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
            $('#projectForm').show();
            
            // تحميل المنجم والعقد
            const mineId = '<?php echo isset($editData['mine_id']) ? $editData['mine_id'] : ''; ?>';
            const contractId = '<?php echo isset($editData['contract_id']) ? $editData['contract_id'] : ''; ?>';
            
            if (mineId) {
                $('#mine_id').val(mineId);
                
                // تحميل العقود للمنجم المحدد
                $.ajax({
                    url: 'get_mine_contracts.php',
                    type: 'GET',
                    data: { mine_id: mineId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.contracts.length > 0) {
                            $('#contract_id').html('<option value="">-- اختر العقد --</option>');
                            response.contracts.forEach(function(contract) {
                                $('#contract_id').append(`<option value="${contract.id}">${contract.name}</option>`);
                            });
                            $('#contract_id').prop('disabled', false);
                            
                            // تحديد العقد المحفوظ
                            if (contractId) {
                                $('#contract_id').val(contractId);
                                $('#contract_id').trigger('change');
                            }
                        }
                    }
                });
            }
            
            // التمرير للفورم
            $('html, body').animate({
                scrollTop: $('#projectForm').offset().top - 100
            }, 500);
        });
        <?php } ?>
        
        // Cascading Dropdowns (للمنجم والعقد فقط)
        $('#mine_id').on('change', function() {
            const mineId = $(this).val();
            const contractSelect = $('#contract_id');
            
            contractSelect.html('<option value="">-- اختر العقد --</option>').prop('disabled', true).removeClass('loading');
            $('#contractStats').hide();
            $('#suppliersSection').hide();
            
            if (!mineId) return;
            
            // إضافة حالة loading
            contractSelect.addClass('loading').html('<option value="">جاري التحميل...</option>');
            
            // جلب العقود
            $.ajax({
                url: 'get_mine_contracts.php',
                type: 'GET',
                data: { mine_id: mineId },
                dataType: 'json',
                success: function(response) {
                    contractSelect.removeClass('loading');
                    if (response.success && response.contracts.length > 0) {
                        contractSelect.html('<option value="">-- اختر العقد --</option>');
                        response.contracts.forEach(function(contract) {
                            contractSelect.append(`<option value="${contract.id}">${contract.name}</option>`);
                        });
                        contractSelect.prop('disabled', false);
                    } else {
                        contractSelect.html('<option value="">⚠️ لا توجد عقود</option>');
                    }
                },
                error: function() {
                    contractSelect.removeClass('loading').html('<option value="">❌ خطأ في التحميل</option>');
                }
            });
        });
        
        $('#contract_id').on('change', function() {
            const contractId = $(this).val();
            
            $('#contractStats').hide();
            $('#suppliersSection').hide();
            
            if (!contractId) return;
            
            // إضافة حالة loading
            $('#contractStats').show().find('.stat-card-value').html('<i class="fas fa-spinner fa-spin"></i>');
            
            // جلب إحصائيات العقد
            $.ajax({
                url: 'get_contract_stats.php',
                type: 'GET',
                data: { contract_id: contractId },
                dataType: 'json',
                success: function(response) {
                    console.log('Contract Stats Response:', response);
                    
                    if (response.success) {
                        // تحديث جدول الموردين
                        const tbody = $('#suppliersTableBody');
                        tbody.empty();
                        
                        if (response.suppliers && response.suppliers.length > 0) {
                            response.suppliers.forEach(function(supplier, index) {
                                // بناء نص التوزيع
                                let breakdownHtml = '';
                                if (supplier.equipment_breakdown && supplier.equipment_breakdown.length > 0) {
                                    const breakdownList = supplier.equipment_breakdown.map(item => {
                                        const operatingClass = item.operating_count > 0 ? 'is-active' : 'is-muted';
                                        const remainingClass = item.remaining_count > 0 ? 'is-warning' : 'is-muted';
                                        const basicInfo = item.count_basic > 0 ? `<span class="breakdown-tag is-basic">أساسي:${item.count_basic}</span>` : '';
                                        const backupInfo = item.count_backup > 0 ? `<span class="breakdown-tag is-backup">احتياطي:${item.count_backup}</span>` : '';
                                        return `<div class="breakdown-item">
                                                    <i class="fas fa-tools"></i>
                                                    <strong>${item.type || 'غير محدد'}</strong>:
                                                    المتعاقد ${item.count} ${basicInfo} ${backupInfo} |
                                                    <span class="breakdown-count ${operatingClass}">المشغّل ${item.operating_count || 0}</span> |
                                                    <span class="breakdown-count ${remainingClass}">المتبقي ${item.remaining_count || 0}</span> |
                                                    <i class="fas fa-clock"></i> ${parseFloat(item.hours).toLocaleString()} ساعة
                                                </div>`;
                                    }).join('');
                                    breakdownHtml = breakdownList;
                                } else {
                                    breakdownHtml = '<span class="breakdown-empty">لا توجد تفاصيل</span>';
                                }
                                
                                const row = `
                                    <tr>
                                        <td class="text-center">${index + 1}</td>
                                        <td><strong>${supplier.supplier_name}</strong></td>
                                        <td class="text-center">${parseFloat(supplier.hours).toLocaleString()}</td>
                                        <td class="text-center">${supplier.equipment_count}</td>
                                        <td class="suppliers-basic-count">${supplier.equipment_count_basic || 0}</td>
                                        <td class="suppliers-backup-count">${supplier.equipment_count_backup || 0}</td>
                                        <td class="suppliers-breakdown">${breakdownHtml}</td>
                                    </tr>
                                `;
                                tbody.append(row);
                            });
                            
                            // تحديث الإجماليات
                            let totalBasic = 0, totalBackup = 0;
                            response.suppliers.forEach(supplier => {
                                totalBasic += supplier.equipment_count_basic || 0;
                                totalBackup += supplier.equipment_count_backup || 0;
                            });
                            $('#total_supplier_hours').text(parseFloat(response.summary.total_supplier_hours).toLocaleString());
                            $('#total_supplier_equipment').text(response.summary.total_supplier_equipment);
                            $('#total_supplier_basic').text(totalBasic);
                            $('#total_supplier_backup').text(totalBackup);
                            
                            $('#suppliersSection').fadeIn();
                        } else {
                            tbody.html(`
                                <tr>
                                    <td colspan="7" class="suppliers-empty">
                                        <i class="fas fa-info-circle"></i> لا توجد عقود موردين لهذا المشروع
                                    </td>
                                </tr>
                            `);
                            $('#total_supplier_hours').text('0');
                            $('#total_supplier_equipment').text('0');
                            $('#total_supplier_basic').text('0');
                            $('#total_supplier_backup').text('0');
                            $('#total_added_equipment').text('0');
                            $('#total_remaining_equipment').text('0');
                            $('#suppliersSection').fadeIn();
                        }
                        
                        // تحديث الإحصائيات الأساسية
                        $('#stat_total_hours').text(parseFloat(response.contract.total_hours).toLocaleString());
                        $('#stat_equipment_count').text(response.contract.equipment_count || '0');
                        
                        $('#contractStats').fadeIn();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    console.error('Response Text:', xhr.responseText);
                    $('#contractStats').hide();
                    alert('خطأ: ' + (xhr.responseText || error));
                }
            });
        });

        // Equipment view modal
        const viewEquipmentModal = document.getElementById('viewEquipmentModal');
        const closeEquipmentModalBtn = document.getElementById('closeEquipmentModal');
        const closeEquipmentModalFooter = document.getElementById('closeEquipmentModalFooter');

        function setViewValue(elementId, value) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const safeValue = (value !== null && value !== undefined && value !== '') ? value : 'غير محدد';
            el.textContent = safeValue;
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

        $(document).on('click', '.viewEquipmentBtn', function() {
            const equipmentId = $(this).data('id');
            if (!equipmentId || !viewEquipmentModal) return;

            viewEquipmentModal.style.display = 'flex';

            const loadingText = 'جار التحميل...';
            [
                'view_eq_code','view_eq_name','view_eq_type','view_eq_supplier','view_eq_project','view_eq_mine',
                'view_eq_serial','view_eq_chassis','view_eq_manufacturer','view_eq_model','view_eq_year',
                'view_eq_import_year','view_eq_condition','view_eq_hours','view_eq_engine','view_eq_tires',
                'view_eq_owner','view_eq_owner_type','view_eq_owner_phone','view_eq_owner_relation',
                'view_eq_license','view_eq_license_authority','view_eq_license_expiry','view_eq_inspection',
                'view_eq_last_inspection','view_eq_location','view_eq_availability','view_eq_value',
                'view_eq_daily','view_eq_monthly','view_eq_insurance','view_eq_notes','view_eq_last_maintenance',
                'view_eq_status'
            ].forEach(id => setViewValue(id, loadingText));

            const editBtn = document.getElementById('viewEquipmentEditBtn');
            if (editBtn) {
                editBtn.setAttribute('href', 'equipments.php?edit=' + equipmentId);
            }

            $.ajax({
                url: 'get_equipment_details.php',
                type: 'GET',
                data: { id: equipmentId },
                dataType: 'json',
                success: function(response) {
                    if (!response.success || !response.data) {
                        setViewValue('view_eq_name', 'تعذر تحميل البيانات');
                        return;
                    }

                    const data = response.data;
                    setViewValue('view_eq_code', data.code);
                    setViewValue('view_eq_name', data.name);
                    setViewValue('view_eq_type', formatType(data.type));
                    setViewValue('view_eq_supplier', data.supplier_name);
                    setViewValue('view_eq_project', data.project_name);
                    setViewValue('view_eq_mine', data.mine_name);
                    setViewValue('view_eq_serial', data.serial_number);
                    setViewValue('view_eq_chassis', data.chassis_number);
                    setViewValue('view_eq_manufacturer', data.manufacturer);
                    setViewValue('view_eq_model', data.model);
                    setViewValue('view_eq_year', data.manufacturing_year);
                    setViewValue('view_eq_import_year', data.import_year);
                    setViewValue('view_eq_condition', data.equipment_condition);
                    setViewValue('view_eq_hours', data.operating_hours ? data.operating_hours + ' ساعة' : 'غير محدد');
                    setViewValue('view_eq_engine', data.engine_condition);
                    setViewValue('view_eq_tires', data.tires_condition);
                    setViewValue('view_eq_owner', data.actual_owner_name);
                    setViewValue('view_eq_owner_type', data.owner_type);
                    setViewValue('view_eq_owner_phone', data.owner_phone);
                    setViewValue('view_eq_owner_relation', data.owner_supplier_relation);
                    setViewValue('view_eq_license', data.license_number);
                    setViewValue('view_eq_license_authority', data.license_authority);
                    setViewValue('view_eq_license_expiry', data.license_expiry_date);
                    setViewValue('view_eq_inspection', data.inspection_certificate_number);
                    setViewValue('view_eq_last_inspection', data.last_inspection_date);
                    setViewValue('view_eq_location', data.current_location);
                    setViewValue('view_eq_availability', data.availability_status);
                    setViewValue('view_eq_value', formatCurrency(data.estimated_value));
                    setViewValue('view_eq_daily', formatCurrency(data.daily_rental_price));
                    setViewValue('view_eq_monthly', formatCurrency(data.monthly_rental_price));
                    setViewValue('view_eq_insurance', data.insurance_status);
                    setViewValue('view_eq_notes', data.general_notes);
                    setViewValue('view_eq_last_maintenance', data.last_maintenance_date);
                    setViewValue('view_eq_status', formatStatus(data.status));
                },
                error: function() {
                    setViewValue('view_eq_name', 'تعذر الاتصال بالخادم');
                }
            });
        });

        function closeEquipmentModal() {
            if (viewEquipmentModal) {
                viewEquipmentModal.style.display = 'none';
            }
        }

        if (closeEquipmentModalBtn) {
            closeEquipmentModalBtn.addEventListener('click', closeEquipmentModal);
        }

        if (closeEquipmentModalFooter) {
            closeEquipmentModalFooter.addEventListener('click', closeEquipmentModal);
        }

        if (viewEquipmentModal) {
            viewEquipmentModal.addEventListener('click', function(event) {
                if (event.target === viewEquipmentModal) {
                    closeEquipmentModal();
                }
            });
        }
        
        // Toggle Form Functionality
    })();
</script>

</div> <!-- closing main div -->
</body>
</html>

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

// ══════════════════════════════════════════════════════════════════════════════
// فحص أعمدة الجداول
// ══════════════════════════════════════════════════════════════════════════════
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');
$supplierscontracts_has_company = db_table_has_column($conn, 'supplierscontracts', 'company_id');

$suppliers_has_is_deleted = db_table_has_column($conn, 'suppliers', 'is_deleted');
$suppliers_has_deleted_at = db_table_has_column($conn, 'suppliers', 'deleted_at');
$suppliers_has_deleted_by = db_table_has_column($conn, 'suppliers', 'deleted_by');

if (!$suppliers_has_is_deleted) {
    @mysqli_query($conn, "ALTER TABLE suppliers ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
}
if (!$suppliers_has_deleted_at) {
    @mysqli_query($conn, "ALTER TABLE suppliers ADD COLUMN deleted_at DATETIME NULL");
}
if (!$suppliers_has_deleted_by) {
    @mysqli_query($conn, "ALTER TABLE suppliers ADD COLUMN deleted_by INT NULL");
}

$suppliers_has_is_deleted = db_table_has_column($conn, 'suppliers', 'is_deleted');
$suppliers_not_deleted_sql = $suppliers_has_is_deleted ? "COALESCE(is_deleted,0)=0" : "1=1";
$suppliers_not_deleted_s_sql = str_replace('is_deleted', 's.is_deleted', $suppliers_not_deleted_sql);

$supplier_scope_list_sql = "1=1";
if (!$is_super_admin) {
    if ($suppliers_has_company) {
        $supplier_scope_list_sql = "s.company_id = $company_id";
    } else {
        $supplier_scope_list_sql = "EXISTS (
            SELECT 1
            FROM supplierscontracts ssc
            INNER JOIN project sp ON sp.id = ssc.project_id
            INNER JOIN users su ON su.id = sp.created_by
            WHERE ssc.supplier_id = s.id
              AND su.company_id = $company_id
        )";
    }
}

// بناء أعمدة وقيم INSERT الخاصة بنطاق الشركة
$supplier_scope_insert_col = (!$is_super_admin && $suppliers_has_company)
    ? ", company_id"
    : "";
$supplier_scope_insert_val = (!$is_super_admin && $suppliers_has_company)
    ? ", '$company_id'"
    : "";

// بناء شرط WHERE الخاص بنطاق الشركة للتحديث والحذف
$supplier_scope_update_where = "id = %d AND $suppliers_not_deleted_sql";
if (!$is_super_admin) {
    if ($suppliers_has_company) {
        $supplier_scope_update_where .= " AND company_id = $company_id";
    } else {
        $supplier_scope_update_where .= " AND EXISTS (
            SELECT 1
            FROM supplierscontracts ssc
            INNER JOIN project sp ON sp.id = ssc.project_id
            INNER JOIN users su ON su.id = sp.created_by
            WHERE ssc.supplier_id = suppliers.id
              AND su.company_id = $company_id
        )";
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// 🔐 التحقق من صلاحيات المستخدم
// ══════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'suppliers');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+الموردين+❌");
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل مورد
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {

    // التحقق من الصلاحية (إضافة أو تعديل)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) {
        header("Location: suppliers.php?msg=لا+توجد+صلاحية+تعديل+الموردين+❌");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: suppliers.php?msg=لا+توجد+صلاحية+إضافة+موردين+جدد+❌");
        exit();
    }

    // ── المعلومات الأساسية ────────────────────────────────────────────────────
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $supplier_code = mysqli_real_escape_string($conn, trim($_POST['supplier_code']));
    $supplier_type = mysqli_real_escape_string($conn, $_POST['supplier_type']);
    $dealing_nature = mysqli_real_escape_string($conn, $_POST['dealing_nature']);
    $equipment_types = isset($_POST['equipment_types']) ? implode(', ', $_POST['equipment_types']) : '';
    $equipment_types = mysqli_real_escape_string($conn, $equipment_types);

    // ── البيانات القانونية ────────────────────────────────────────────────────
    $commercial_registration = mysqli_real_escape_string($conn, trim($_POST['commercial_registration']));
    $identity_type = mysqli_real_escape_string($conn, $_POST['identity_type']);
    $identity_number = mysqli_real_escape_string($conn, trim($_POST['identity_number']));
    $identity_expiry_date = !empty($_POST['identity_expiry_date'])
        ? mysqli_real_escape_string($conn, $_POST['identity_expiry_date'])
        : null;

    // ── البيانات التواصلية ────────────────────────────────────────────────────
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $phone_alternative = mysqli_real_escape_string($conn, trim($_POST['phone_alternative']));
    $full_address = mysqli_real_escape_string($conn, trim($_POST['full_address']));
    $contact_person_name = mysqli_real_escape_string($conn, trim($_POST['contact_person_name']));
    $contact_person_phone = mysqli_real_escape_string($conn, trim($_POST['contact_person_phone']));
    $financial_registration_status = mysqli_real_escape_string($conn, $_POST['financial_registration_status']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($supplier_code !== '') {
        $duplicate_query = "SELECT s.id
                            FROM suppliers s
                            WHERE s.supplier_code = '$supplier_code'
                              AND $suppliers_not_deleted_s_sql
                              AND $supplier_scope_list_sql";
        if ($id > 0) {
            $duplicate_query .= " AND s.id != $id";
        }
        $duplicate_query .= " LIMIT 1";

        $duplicate_result = mysqli_query($conn, $duplicate_query);
        if ($duplicate_result && mysqli_num_rows($duplicate_result) > 0) {
            $error_msg = ($id > 0)
                ? "كود+المورد+موجود+مسبقاً+داخل+الشركة+❌"
                : "لا+يمكن+إضافة+مورد+بنفس+الكود+داخل+الشركة+❌";
            header("Location: suppliers.php?msg=$error_msg");
            exit();
        }
    }

    $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";

    if ($id > 0) {
        // ── تحديث مورد موجود ──────────────────────────────────────────────────
        $scope_where = sprintf($supplier_scope_update_where, $id);
        $sql = "UPDATE suppliers SET
            name                         = '$name',
            supplier_code                = '$supplier_code',
            supplier_type                = '$supplier_type',
            dealing_nature               = '$dealing_nature',
            equipment_types              = '$equipment_types',
            commercial_registration      = '$commercial_registration',
            identity_type                = '$identity_type',
            identity_number              = '$identity_number',
            identity_expiry_date         = $identity_expiry_sql,
            email                        = '$email',
            phone                        = '$phone',
            phone_alternative            = '$phone_alternative',
            full_address                 = '$full_address',
            contact_person_name          = '$contact_person_name',
            contact_person_phone         = '$contact_person_phone',
            financial_registration_status = '$financial_registration_status',
            status                       = '$status'
            WHERE $scope_where";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=تم+تعديل+المورد+بنجاح+✅");
        exit;
    } else {
        // ── إضافة مورد جديد ───────────────────────────────────────────────────
        $sql = "INSERT INTO suppliers
            (name, supplier_code, supplier_type, dealing_nature, equipment_types,
             commercial_registration, identity_type, identity_number, identity_expiry_date,
             email, phone, phone_alternative, full_address, contact_person_name,
             contact_person_phone, financial_registration_status, status$supplier_scope_insert_col)
            VALUES
            ('$name', '$supplier_code', '$supplier_type', '$dealing_nature', '$equipment_types',
             '$commercial_registration', '$identity_type', '$identity_number', $identity_expiry_sql,
             '$email', '$phone', '$phone_alternative', '$full_address', '$contact_person_name',
             '$contact_person_phone', '$financial_registration_status', '$status'$supplier_scope_insert_val)";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=تمت+إضافة+المورد+بنجاح+✅");
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة حذف المورد
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

    // التحقق من صلاحية الحذف
    if (!$can_delete) {
        header("Location: suppliers.php?msg=لا+توجد+صلاحية+حذف+الموردين+❌");
        exit();
    }

    // التحقق من أن المورد تابع لشركة المستخدم
    $scope_where = sprintf($supplier_scope_update_where, $delete_id);
    $scope_check_query = "SELECT id FROM suppliers WHERE $scope_where LIMIT 1";
    $scope_check_result = mysqli_query($conn, $scope_check_query);
    if (!$scope_check_result || mysqli_num_rows($scope_check_result) === 0) {
        header("Location: suppliers.php?msg=لا+يمكن+حذف+مورد+لا+يتبع+لشركتك+❌");
        exit();
    }

    // التحقق من وجود معدات أو عقود مرتبطة
    $check_equip = mysqli_query($conn, "SELECT COUNT(*) as count FROM equipments WHERE suppliers = $delete_id");
    $equip_count = mysqli_fetch_assoc($check_equip)['count'];

    $check_contracts = mysqli_query($conn, "SELECT COUNT(*) as count FROM supplierscontracts WHERE supplier_id = $delete_id");
    $contracts_count = mysqli_fetch_assoc($check_contracts)['count'];

    if ($equip_count > 0 || $contracts_count > 0) {
        header("Location: suppliers.php?msg=لا+يمكن+حذف+المورد+لأنه+مرتبط+بمعدات+أو+عقود+موجودة+❌");
        exit();
    }

    $delete_query = "UPDATE suppliers
                     SET is_deleted = 1,
                         deleted_at = NOW(),
                         deleted_by = $current_user_id,
                         status = 0,
                         updated_at = NOW()
                     WHERE $scope_where";
    if (mysqli_query($conn, $delete_query)) {
        header("Location: suppliers.php?msg=تم+حذف+المورد+بنجاح+✅");
        exit();
    } else {
        header("Location: suppliers.php?msg=حدث+خطأ+أثناء+الحذف+❌");
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// إحصائيات الموردين
// ══════════════════════════════════════════════════════════════════════════════
$suppliers_total_count = 0;
$suppliers_active_count = 0;
$suppliers_inactive_count = 0;
$suppliers_with_contracts_count = 0;
$suppliers_contracts_total = 0;
$suppliers_hours_total = 0;

$supplier_scope_stats_sql = $supplier_scope_list_sql;
$supplier_status_active_sql = "(s.status = 1 OR s.status = '1' OR TRIM(s.status) = 'نشط' OR TRIM(LOWER(s.status)) = 'active' OR TRIM(LOWER(s.status)) = 'true')";

$suppliers_stats_query = "SELECT
    COUNT(*) AS total_suppliers,
    SUM(CASE WHEN $supplier_status_active_sql THEN 1 ELSE 0 END) AS active_suppliers
  FROM suppliers s
  WHERE $supplier_scope_stats_sql AND ($suppliers_not_deleted_s_sql)";
$suppliers_stats_result = mysqli_query($conn, $suppliers_stats_query);
if ($suppliers_stats_result && ($suppliers_stats_row = mysqli_fetch_assoc($suppliers_stats_result))) {
    $suppliers_total_count = intval($suppliers_stats_row['total_suppliers']);
    $suppliers_active_count = intval($suppliers_stats_row['active_suppliers']);
}
$suppliers_inactive_count = max(0, $suppliers_total_count - $suppliers_active_count);

$suppliers_contracts_scope = (!$is_super_admin && $supplierscontracts_has_company)
    ? "ssc.company_id = $company_id"
    : "1=1";

$suppliers_contracts_stats_query = "SELECT
    COUNT(*) AS total_contracts,
    COUNT(DISTINCT ssc.supplier_id) AS suppliers_with_contracts,
    COALESCE(SUM(ssc.forecasted_contracted_hours), 0) AS total_contracted_hours
  FROM supplierscontracts ssc
  INNER JOIN suppliers s ON s.id = ssc.supplier_id
  WHERE $suppliers_contracts_scope
    AND $supplier_scope_stats_sql
    AND ($suppliers_not_deleted_s_sql)";
$suppliers_contracts_stats_result = mysqli_query($conn, $suppliers_contracts_stats_query);
if ($suppliers_contracts_stats_result && ($suppliers_contracts_stats_row = mysqli_fetch_assoc($suppliers_contracts_stats_result))) {
    $suppliers_contracts_total = intval($suppliers_contracts_stats_row['total_contracts']);
    $suppliers_with_contracts_count = intval($suppliers_contracts_stats_row['suppliers_with_contracts']);
    $suppliers_hours_total = floatval($suppliers_contracts_stats_row['total_contracted_hours']);
}
?>
<?php
$page_title = 'إيكوبيشن | الموردون';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main suppliers-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'إدارة الموردين';
    $header_icon    = 'fas fa-truck-loading';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مورد جديد');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'suppliers-header-link', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'suppliers-toggle-stats-text');
    $header_actions[] = array('href' => 'download_suppliers_template_csv.php', 'class' => 'suppliers-header-link suppliers-header-link-csv', 'icon' => 'fas fa-file-csv', 'label' => 'تحميل نموذج CSV');
    $header_actions[] = array('href' => 'download_suppliers_template.php', 'class' => 'suppliers-header-link suppliers-header-link-excel', 'icon' => 'fas fa-file-excel', 'label' => 'تحميل نموذج Excel');
    if ($can_add) {
        $header_actions[] = array('id' => 'openImportModal', 'class' => 'suppliers-header-link suppliers-header-link-import', 'icon' => 'fas fa-file-import', 'label' => 'استيراد من Excel');
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

    <div class="stats-section suppliers-hidden" id="suppliersStatsSection">
        <div class="stats-grid">
            <div class="stats-card stats-primary">
                <div class="stats-icon"><i class="fas fa-truck-loading"></i></div>
                <div class="stats-title">إجمالي الموردين</div>
                <div class="stats-value"><?php echo $suppliers_total_count; ?></div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-user-check"></i></div>
                <div class="stats-title">الموردون النشطون</div>
                <div class="stats-value"><?php echo $suppliers_active_count; ?></div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-user-slash"></i></div>
                <div class="stats-title">الموردون المعلقون</div>
                <div class="stats-value"><?php echo $suppliers_inactive_count; ?></div>
            </div>
            <div class="stats-card stats-cyan">
                <div class="stats-icon"><i class="fas fa-file-contract"></i></div>
                <div class="stats-title">إجمالي العقود</div>
                <div class="stats-value"><?php echo $suppliers_contracts_total; ?></div>
            </div>
            <div class="stats-card stats-purple">
                <div class="stats-icon"><i class="fas fa-link"></i></div>
                <div class="stats-title">موردون لديهم عقود</div>
                <div class="stats-value"><?php echo $suppliers_with_contracts_count; ?></div>
            </div>
            <div class="stats-card stats-orange">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-title">إجمالي الساعات المتعاقد عليها</div>
                <div class="stats-value"><?php echo number_format($suppliers_hours_total); ?></div>
            </div>
        </div>
    </div>

    <!-- ══ فورم إضافة / تعديل مورد ══════════════════════════════════════════ -->
    <form id="projectForm" action="" method="post" class="allforms">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مورد</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="supplier_id" value="">

                <!-- 1. المعلومات الأساسية والتعريفية -->
                <div class="form-section">
                    <h6><i class="fas fa-info-circle"></i> المعلومات الأساسية والتعريفية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اسم المورد <span class="required">*</span></label>
                            <input type="text" name="name" id="supplier_name" required value="مورد" />
                        </div>
                        <div class="form-group">
                            <label>الرمز/الكود للمورد</label>
                            <input type="text" name="supplier_code" id="supplier_code" value="MOR1" />
                        </div>
                        <div class="form-group">
                            <label>نوع المورد <span class="required">*</span></label>
                            <select name="supplier_type" id="supplier_type" required>
                                <option value="">-- اختر --</option>
                                <option value="فرد">فرد</option>
                                <option value="شركة">شركة</option>
                                <option value="وسيط">وسيط</option>
                                <option value="مالك">مالك</option>
                                <option value="جهة حكومية">جهة حكومية</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>طبيعة التعامل <span class="required">*</span></label>
                            <select name="dealing_nature" id="dealing_nature" required>
                                <option value="">-- اختر --</option>
                                <option value="متعاقد مباشر">متعاقد مباشر</option>
                                <option value="وسيط">وسيط</option>
                                <option value="مورد معدات مباشر (مالك)">مورد معدات مباشر (مالك)</option>
                                <option value="وكيل توزيع">وكيل توزيع</option>
                                <option value="تاجر وسيط">تاجر وسيط</option>
                            </select>
                        </div>
                    </div>

                    <!-- أنواع المعدات (يمكن اختيار أكثر من نوع) -->
                    <div class="form-group allforms-span-full">
                        <label>المعدات (يمكن اختيار أكثر من نوع)</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="حفارات" checked>
                                <span>حفارات</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="مكنات تخريم">
                                <span>مكنات تخريم</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="دوازر">
                                <span>دوازر</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="شاحنات قلابة">
                                <span>شاحنات قلابة</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="شاحنات تناكر">
                                <span>شاحنات تناكر</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="جرافات">
                                <span>جرافات</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="معدات معالجة">
                                <span>معدات معالجة</span>
                            </label>

                             <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="السيارات والكرفانات">
                                <span> السيارات والكرفانات</span>
                            </label>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="equipment_types[]" value="معدات أخرى">
                                    <span>معدات أخرى</span>
                            </label>

                        </div>
                    </div>
                </div>

                <!-- 2. البيانات القانونية والتعريفية -->
                <div class="form-section">
                    <h6><i class="fas fa-file-contract"></i> البيانات القانونية والتعريفية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>رقم التسجيل التجاري/الرخصة</label>
                            <input type="text" name="commercial_registration" id="commercial_registration" />
                        </div>
                        <div class="form-group">
                            <label>نوع الهوية</label>
                            <select name="identity_type" id="identity_type">
                                <option value="">-- اختر --</option>
                                <option value="بطاقة هوية وطنية">بطاقة هوية وطنية</option>
                                <option value="جواز سفر">جواز سفر</option>
                                <option value="رقم تسجيل تجاري">رقم تسجيل تجاري</option>
                                <option value="رقم ضريبة دخل">رقم ضريبة دخل</option>
                                <option value="رخصة عمل">رخصة عمل</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>رقم الهوية/التسجيل</label>
                            <input type="text" name="identity_number" id="identity_number" />
                        </div>
                        <div class="form-group">
                            <label>تاريخ انتهاء الهوية</label>
                            <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                        </div>
                    </div>
                </div>

                <!-- 3. البيانات التواصلية -->
                <div class="form-section">
                    <h6><i class="fas fa-address-book"></i>  بيانات التواصل</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>البريد الإلكتروني الرئيسي</label>
                            <input type="email" name="email" id="supplier_email" />
                        </div>
                        <div class="form-group">
                            <label>رقم الهاتف الأساسي <span class="required">*</span></label>
                            <input type="text" name="phone" id="supplier_phone" required value="092899930030" />
                        </div>
                        <div class="form-group">
                            <label>رقم هاتف بديل</label>
                            <input type="text" name="phone_alternative" id="phone_alternative" />
                        </div>
                        <div class="form-group">
                            <label>اسم الشخص المفوض</label>
                            <input type="text" name="contact_person_name" id="contact_person_name" />
                        </div>
                        <div class="form-group">
                            <label>هاتف الشخص المفوض</label>
                            <input type="text" name="contact_person_phone" id="contact_person_phone" />
                        </div>
                        <div class="form-group">
                            <label>حالة التسجيل المالي</label>
                            <select name="financial_registration_status" id="financial_registration_status">
                                <option value="">-- اختر --</option>
                                <option value="مسجل رسمياً">مسجل رسمياً</option>
                                <option value="غير مسجل">غير مسجل</option>
                                <option value="تحت التسجيل">تحت التسجيل</option>
                                <option value="معفى من التسجيل">معفى من التسجيل</option>
                            </select>
                        </div>
                        <div class="form-group allforms-span-full">
                            <label>العنوان الكامل</label>
                            <textarea name="full_address" id="full_address" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>الحالة <span class="required">*</span></label>
                            <select name="status" id="supplier_status" required>
                                <option value="">اختر الحالة</option>
                                <option value="1">نشط</option>
                                <option value="0">معلق</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> حفظ المورد
                    </button>
                    <button type="button" class="btn-cancel" onclick="toggleForm()">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- ══ جدول الموردين ══════════════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة الموردين</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display nowrap alltables suppliers-table-nowrap" style="width:100%;">
                    <thead>
                        <tr>
                            <th><i class="fas fa-sliders-h"></i> الإجراءات</th>
                            <th><i class="fas fa-barcode"></i> كود المورد</th>
                            <th><i class="fas fa-truck-loading"></i> اسم المورد</th>
                            <th><i class="fas fa-cogs"></i> عدد الآليات</th>
                            <th><i class="fas fa-clock"></i> الساعات المتعاقد عليها</th>
                            <th><i class="fas fa-phone"></i> رقم الهاتف</th>
                            <th><i class="fas fa-info-circle"></i> الحالة</th>
                            <th><i class="fas fa-file-contract"></i> عقود المورد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ── بناء شرط نطاق الشركة للاستعلام ──────────────────
                        $supplier_scope_sql = "1=1";
                        if (!$is_super_admin) {
                            if ($suppliers_has_company) {
                                $supplier_scope_sql = "s.company_id = $company_id";
                            } else {
                                $supplier_scope_sql = "EXISTS (
                                    SELECT 1
                                    FROM supplierscontracts ssc
                                    INNER JOIN project sp ON sp.id = ssc.project_id
                                    INNER JOIN users su ON su.id = sp.created_by
                                    WHERE ssc.supplier_id = s.id
                                      AND su.company_id = $company_id
                                )";
                            }
                        }

                        $contracts_count_scope = (!$is_super_admin && $supplierscontracts_has_company)
                            ? " AND supplierscontracts.company_id = $company_id"
                            : "";

                        // جلب الموردين مع إجمالي الساعات وعدد العقود والمعدات
                        $query = "SELECT s.*,
                          (SELECT COUNT(*) FROM equipments        WHERE equipments.suppliers        = s.id)                           AS 'equipments',
                          (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id$contracts_count_scope) AS 'num_contracts',
                          (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id$contracts_count_scope) AS 'total_hours'
                          FROM suppliers s
                                                    WHERE $supplier_scope_sql AND ($suppliers_not_deleted_s_sql)
                          ORDER BY s.id DESC";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $supplier_name_cell = "<a class='client-name-link' href='supplier_profile.php?id=" . intval($row['id']) . "'>" . htmlspecialchars($row['name']) . "</a>";
                            if (intval($row['num_contracts']) === 0) {
                                $supplier_name_cell .= " <span class='link-alert-chip' title='المورد ليس لديه عقد'><i class='fas fa-exclamation-triangle'></i>تنبيه</span>";
                            }

                            // إعداد data-attributes للتعديل والعرض
                            $data_attrs =
                                "data-id='" . $row['id'] . "' " .
                                "data-name='" . htmlspecialchars($row['name'], ENT_QUOTES) . "' " .
                                "data-supplier_code='" . htmlspecialchars((string) ($row['supplier_code'] ?? ''), ENT_QUOTES) . "' " .
                                "data-supplier_type='" . htmlspecialchars((string) ($row['supplier_type'] ?? ''), ENT_QUOTES) . "' " .
                                "data-dealing_nature='" . htmlspecialchars((string) ($row['dealing_nature'] ?? ''), ENT_QUOTES) . "' " .
                                "data-equipment_types='" . htmlspecialchars((string) ($row['equipment_types'] ?? ''), ENT_QUOTES) . "' " .
                                "data-commercial_registration='" . htmlspecialchars((string) ($row['commercial_registration'] ?? ''), ENT_QUOTES) . "' " .
                                "data-identity_type='" . htmlspecialchars((string) ($row['identity_type'] ?? ''), ENT_QUOTES) . "' " .
                                "data-identity_number='" . htmlspecialchars((string) ($row['identity_number'] ?? ''), ENT_QUOTES) . "' " .
                                "data-identity_expiry_date='" . htmlspecialchars((string) ($row['identity_expiry_date'] ?? ''), ENT_QUOTES) . "' " .
                                "data-email='" . htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES) . "' " .
                                "data-phone='" . htmlspecialchars((string) ($row['phone'] ?? ''), ENT_QUOTES) . "' " .
                                "data-phone_alternative='" . htmlspecialchars((string) ($row['phone_alternative'] ?? ''), ENT_QUOTES) . "' " .
                                "data-full_address='" . htmlspecialchars((string) ($row['full_address'] ?? ''), ENT_QUOTES) . "' " .
                                "data-contact_person_name='" . htmlspecialchars((string) ($row['contact_person_name'] ?? ''), ENT_QUOTES) . "' " .
                                "data-contact_person_phone='" . htmlspecialchars((string) ($row['contact_person_phone'] ?? ''), ENT_QUOTES) . "' " .
                                "data-financial_registration_status='" . htmlspecialchars((string) ($row['financial_registration_status'] ?? ''), ENT_QUOTES) . "' " .
                                "data-status='" . $row['status'] . "'";

                            echo "<tr>";

                            $action_btns = "<td><div class='action-btns'>";
                            $action_btns .= "<a href='javascript:void(0)' class='viewBtn action-btn view' $data_attrs title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
                            if ($can_edit) {
                                $action_btns .= "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                            }
                            if ($can_delete) {
                                $action_btns .= "<a href='?delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من حذف هذا المورد؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                            }
                            $action_btns .= "</div></td>";

                            echo $action_btns;
                            echo "<td><strong>" . htmlspecialchars((string) ($row['supplier_code'] ?? '-')) . "</strong></td>";
                            echo "<td>" . $supplier_name_cell . "</td>";
                            echo "<td><span class='stat-cell'>" . $row['equipments'] . "</span></td>";
                            echo "<td><span class='status-active'>" . number_format($row['total_hours']) . " ساعة</span></td>";
                            echo "<td><i class='fas fa-phone phone-icon'></i>" . htmlspecialchars($row['phone']) . "</td>";

                            // عرض الحالة بالألوان
                            if ($row['status'] == "1") {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle suppliers-status-icon'></i>نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle suppliers-status-icon'></i>معلق</span></td>";
                            }

                            echo "<td>
                             <a href='supplierscontracts.php?id=" . intval($row['id']) . "'
                                       class='suppliers-contracts-link'
                                       title='عرض عقود المورد'>
                                        <i class='fas fa-file-contract'></i>
                                        <span class='suppliers-contracts-badge'>" . intval($row['num_contracts']) . "</span>
                             </a>
                        </td>";

                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ Modal عرض تفاصيل المورد ═══════════════════════════════════════════ -->
    <div id="viewSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-truck-loading"></i> تفاصيل المورد</h5>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">

                <!-- المعلومات الأساسية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-info-circle"></i> المعلومات الأساسية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">اسم المورد:</span>
                            <span class="info-value" id="view_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الرمز/الكود:</span>
                            <span class="info-value" id="view_supplier_code">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نوع المورد:</span>
                            <span class="info-value" id="view_supplier_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">طبيعة التعامل:</span>
                            <span class="info-value" id="view_dealing_nature">-</span>
                        </div>
                        <div class="info-item suppliers-span-full">
                            <span class="info-label">المعدات:</span>
                            <span class="info-value" id="view_equipment_types">-</span>
                        </div>
                    </div>
                </div>

                <!-- البيانات القانونية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-file-contract"></i> البيانات القانونية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">رقم التسجيل التجاري:</span>
                            <span class="info-value" id="view_commercial_registration">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نوع الهوية:</span>
                            <span class="info-value" id="view_identity_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم الهوية:</span>
                            <span class="info-value" id="view_identity_number">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ انتهاء الهوية:</span>
                            <span class="info-value" id="view_identity_expiry_date">-</span>
                        </div>
                    </div>
                </div>

                <!-- البيانات التواصلية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-address-book"></i> البيانات التواصلية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">البريد الإلكتروني:</span>
                            <span class="info-value" id="view_email">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم الهاتف الأساسي:</span>
                            <span class="info-value" id="view_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم هاتف بديل:</span>
                            <span class="info-value" id="view_phone_alternative">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">جهة الاتصال:</span>
                            <span class="info-value" id="view_contact_person_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">هاتف جهة الاتصال:</span>
                            <span class="info-value" id="view_contact_person_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">حالة التسجيل المالي:</span>
                            <span class="info-value" id="view_financial_registration_status">-</span>
                        </div>
                        <div class="info-item suppliers-span-full">
                            <span class="info-label">العنوان الكامل:</span>
                            <span class="info-value" id="view_full_address">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الحالة:</span>
                            <span class="info-value" id="view_status">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer suppliers-modal-footer">
                <button onclick="closeViewModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
        </div>
    </div>

    <!-- ══ Modal استيراد من Excel ════════════════════════════════════════════ -->
    <div id="importExcelModal" class="modal">
        <div class="modal-content suppliers-import-modal-content">
            <div class="modal-header">
                <h5><i class="fas fa-file-excel"></i> استيراد موردين من Excel</h5>
                <button class="close-modal" onclick="closeImportModal()">&times;</button>
            </div>
            <form id="importExcelForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="suppliers-import-notice">
                        <div class="suppliers-import-notice-head">
                            <i class="fas fa-info-circle"></i>
                            <strong>تعليمات الاستيراد:</strong>
                        </div>
                        <ul class="suppliers-import-list">
                            <li>قم بتحميل نموذج Excel أو CSV أولاً</li>
                            <li>املأ البيانات في النموذج (كود المورد، الاسم، رقم الهاتف مطلوبة)</li>
                            <li>احفظ الملف ثم قم برفعه هنا</li>
                            <li>الصيغ المدعومة: .xlsx, .xls, .csv</li>
                            <li>الحد الأقصى: 1000 صف، 5 ميجا</li>
                        </ul>
                    </div>

                    <div class="suppliers-import-upload-wrap">
                        <label class="suppliers-import-upload-label">
                            <i class="fas fa-file-upload"></i> اختر ملف Excel أو CSV
                        </label>
                        <input type="file" name="excel_file" id="excelFileInput" accept=".xlsx,.xls,.csv"
                            class="suppliers-import-file-input" required>
                    </div>

                    <div id="importProgress" class="suppliers-hidden suppliers-import-progress">
                        <div class="suppliers-import-progress-bar"></div>
                        <p>جاري الاستيراد...</p>
                    </div>

                    <div id="importResult" class="suppliers-hidden suppliers-import-result"></div>
                </div>
                <div class="modal-footer suppliers-modal-footer">
                    <button type="submit" class="btn-save suppliers-import-submit-btn">
                        <i class="fas fa-upload"></i> رفع واستيراد
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeImportModal()">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

        // ════════════════════════════════════════════════
        // تشغيل DataTable بالعربية
        // ════════════════════════════════════════════════
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                scrollX: true,
                autoWidth: false,
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: '📋 نسخ' },
                    { extend: 'excel', text: '📊 Excel' },
                    { extend: 'csv', text: '📄 CSV' },
                    { extend: 'pdf', text: '📕 PDF' },
                    { extend: 'print', text: '🖨️ طباعة' }
                ],
                "language": {
                    "url": "/ems/assets/i18n/datatables/ar.json"
                }
            });
        });

        // ════════════════════════════════════════════════
        // إظهار / إخفاء فورم الإضافة
        // ════════════════════════════════════════════════
        const toggleSupplierFormBtn = document.getElementById('toggleForm');
        const supplierForm = document.getElementById('projectForm');
        const statsToggleBtn = $('#toggleStats');
        const statsSection = $('#suppliersStatsSection');

        function updateStatsToggleState(isVisible) {
            if (!statsToggleBtn.length) {
                return;
            }

            const icon = statsToggleBtn.find('i').first();
            const text = statsToggleBtn.find('.suppliers-toggle-stats-text');

            if (isVisible) {
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
                text.text('إخفاء الإحصائيات');
            } else {
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
                text.text('إظهار الإحصائيات');
            }
        }

        updateStatsToggleState(statsSection.is(':visible'));

        statsToggleBtn.on('click', function () {
            if (statsSection.is(':visible')) {
                statsSection.stop(true, true).slideUp(250, function () {
                    statsSection.addClass('suppliers-hidden');
                    updateStatsToggleState(false);
                });
            } else {
                statsSection.removeClass('suppliers-hidden').hide();
                statsSection.stop(true, true).slideDown(250, function () {
                    updateStatsToggleState(true);
                });
            }
        });

        if (toggleSupplierFormBtn && supplierForm) {
            toggleSupplierFormBtn.addEventListener('click', function () {
                supplierForm.classList.toggle('allforms-visible');
                // تنظيف الحقول عند الإضافة
                $("#supplier_id").val("");
                $("#supplier_name").val("");
                $("#supplier_phone").val("");
                $("#supplier_status").val("");
            });
        }

        // ════════════════════════════════════════════════
        // زر التعديل — تحميل بيانات المورد في الفورم
        // ════════════════════════════════════════════════
        $(document).on("click", ".editBtn", function () {
            const $this = $(this);

            // البيانات الأساسية
            $("#supplier_id").val($this.data("id"));
            $("#supplier_name").val($this.data("name"));
            $("#supplier_code").val($this.data("supplier_code"));
            $("#supplier_type").val($this.data("supplier_type"));
            $("#dealing_nature").val($this.data("dealing_nature"));

            // المعدات (checkbox) — تحديد القيم المخزنة
            const equipmentTypes = $this.data("equipment_types")
                ? $this.data("equipment_types").toString().split(', ')
                : [];
            $("input[name='equipment_types[]']").prop("checked", false);
            equipmentTypes.forEach(function (type) {
                $("input[name='equipment_types[]'][value='" + type.trim() + "']").prop("checked", true);
            });

            // البيانات القانونية
            $("#commercial_registration").val($this.data("commercial_registration"));
            $("#identity_type").val($this.data("identity_type"));
            $("#identity_number").val($this.data("identity_number"));
            $("#identity_expiry_date").val($this.data("identity_expiry_date"));

            // البيانات التواصلية
            $("#supplier_email").val($this.data("email"));
            $("#supplier_phone").val($this.data("phone"));
            $("#phone_alternative").val($this.data("phone_alternative"));
            $("#full_address").val($this.data("full_address"));
            $("#contact_person_name").val($this.data("contact_person_name"));
            $("#contact_person_phone").val($this.data("contact_person_phone"));
            $("#financial_registration_status").val($this.data("financial_registration_status"));
            $("#supplier_status").val($this.data("status"));

            // عرض الفورم والتمرير إليه
            $("#projectForm").addClass('allforms-visible');
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // ════════════════════════════════════════════════
        // زر عرض التفاصيل — فتح المودال
        // ════════════════════════════════════════════════
        $(document).on("click", ".viewBtn", function () {
            const $this = $(this);

            // ملء البيانات الأساسية
            $("#view_name").text($this.data("name") || "-");
            $("#view_supplier_code").text($this.data("supplier_code") || "-");
            $("#view_supplier_type").text($this.data("supplier_type") || "-");
            $("#view_dealing_nature").text($this.data("dealing_nature") || "-");
            $("#view_equipment_types").text($this.data("equipment_types") || "-");

            // البيانات القانونية
            $("#view_commercial_registration").text($this.data("commercial_registration") || "-");
            $("#view_identity_type").text($this.data("identity_type") || "-");
            $("#view_identity_number").text($this.data("identity_number") || "-");
            $("#view_identity_expiry_date").text($this.data("identity_expiry_date") || "-");

            // البيانات التواصلية
            $("#view_email").text($this.data("email") || "-");
            $("#view_phone").text($this.data("phone") || "-");
            $("#view_phone_alternative").text($this.data("phone_alternative") || "-");
            $("#view_full_address").text($this.data("full_address") || "-");
            $("#view_contact_person_name").text($this.data("contact_person_name") || "-");
            $("#view_contact_person_phone").text($this.data("contact_person_phone") || "-");
            $("#view_financial_registration_status").text($this.data("financial_registration_status") || "-");

            // عرض الحالة بناء على القيمة المخزنة فعلياً (قد تأتي رقمًا أو نصًا)
            const rawStatus = $this.data("status");
            const isActive = String(rawStatus) === "1";
            const status = isActive ? "نشط ✅" : "معلق ⏸️";
            $("#view_status").text(status);

            $("#viewSupplierModal").fadeIn(300);
        });

        // إغلاق مودال العرض
        window.closeViewModal = function () {
            $("#viewSupplierModal").fadeOut(300);
        };

        // إغلاق المودال عند الضغط خارج المحتوى
        $(document).on("click", "#viewSupplierModal", function (e) {
            if (e.target.id === "viewSupplierModal") { closeViewModal(); }
        });

        // ════════════════════════════════════════════════
        // دالة toggleForm — إظهار/إخفاء النموذج وتنظيفه
        // ════════════════════════════════════════════════
        window.toggleForm = function () {
            var form = $("#projectForm");
            if (form.hasClass('allforms-visible')) {
                form.slideUp();
                form.removeClass('allforms-visible');
            } else {
                // مسح جميع الحقول
                $("#supplier_id").val("");
                $("#supplier_name").val("");
                $("#supplier_code").val("");
                $("#supplier_type").val("");
                $("#dealing_nature").val("");
                $("input[name='equipment_types[]']").prop("checked", false);
                $("#commercial_registration").val("");
                $("#identity_type").val("");
                $("#identity_number").val("");
                $("#identity_expiry_date").val("");
                $("#supplier_email").val("");
                $("#supplier_phone").val("");
                $("#phone_alternative").val("");
                $("#full_address").val("");
                $("#contact_person_name").val("");
                $("#contact_person_phone").val("");
                $("#financial_registration_status").val("");
                $("#supplier_status").val("");
                form.addClass('allforms-visible');
                form.slideDown();
                $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
            }
        };

        // ════════════════════════════════════════════════
        // Modal استيراد من Excel
        // ════════════════════════════════════════════════

        // فتح المودال
        $('#openImportModal').on('click', function () {
            $('#importExcelModal').fadeIn(300);
            $('#importResult').addClass('suppliers-hidden').empty();
            $('#excelFileInput').val('');
        });

        // إغلاق المودال
        window.closeImportModal = function () {
            $('#importExcelModal').fadeOut(300);
            $('#importExcelForm')[0].reset();
            $('#importProgress').addClass('suppliers-hidden');
            $('#importResult').addClass('suppliers-hidden').empty();
        };

        // إغلاق عند الضغط خارج المحتوى
        $(document).on('click', '#importExcelModal', function (e) {
            if (e.target.id === 'importExcelModal') { closeImportModal(); }
        });

        // معالجة رفع واستيراد الملف
        $('#importExcelForm').on('submit', function (e) {
            e.preventDefault();

            const fileInput = document.getElementById('excelFileInput');
            if (!fileInput.files.length) {
                alert('الرجاء اختيار ملف أولاً');
                return;
            }

            const formData = new FormData();
            formData.append('excel_file', fileInput.files[0]);
            formData.append('action', 'import_excel');

            $('#importProgress').removeClass('suppliers-hidden');
            $('#importResult').addClass('suppliers-hidden').empty();

            $.ajax({
                url: 'import_suppliers_excel.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    $('#importProgress').addClass('suppliers-hidden');

                    let resultHtml = '<div class="suppliers-import-result-card';

                    if (response.success) {
                        resultHtml += ' suppliers-import-result-card-success">';
                        resultHtml += '<div class="suppliers-import-result-head">';
                        resultHtml += '<i class="fas fa-check-circle suppliers-import-result-icon"></i>';
                        resultHtml += '<strong class="suppliers-import-result-title">' + response.message + '</strong>';
                        resultHtml += '</div>';
                        resultHtml += '<div class="suppliers-import-result-body">';
                        resultHtml += '<p><strong>✅ تمت الإضافة:</strong> ' + response.added + ' مورد</p>';
                        if (response.skipped > 0) {
                            resultHtml += '<p><strong>⭕ تم التخطي:</strong> ' + response.skipped + ' مورد</p>';
                        }
                        if (response.errors && response.errors.length > 0) {
                            resultHtml += '<details class="suppliers-import-result-details">';
                            resultHtml += '<summary>عرض الأخطاء (' + response.errors.length + ')</summary>';
                            resultHtml += '<ul>';
                            response.errors.forEach(function (error) {
                                resultHtml += '<li>' + error + '</li>';
                            });
                            resultHtml += '</ul></details>';
                        }
                        resultHtml += '</div>';
                        resultHtml += '<button type="button" onclick="location.reload()" class="suppliers-import-refresh-btn">تحديث الصفحة</button>';
                    } else {
                        resultHtml += ' suppliers-import-result-card-error">';
                        resultHtml += '<div class="suppliers-import-result-head">';
                        resultHtml += '<i class="fas fa-exclamation-circle suppliers-import-result-icon"></i>';
                        resultHtml += '<div><strong class="suppliers-import-result-title">فشل الاستيراد</strong>';
                        resultHtml += '<p class="suppliers-import-result-error-text">' + response.message + '</p></div>';
                        resultHtml += '</div>';
                    }

                    resultHtml += '</div>';
                    $('#importResult').html(resultHtml).removeClass('suppliers-hidden').hide().fadeIn();
                },
                error: function (xhr) {
                    $('#importProgress').addClass('suppliers-hidden');
                    let errorMsg = 'حدث خطأ غير متوقع';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $('#importResult').html(
                        '<div class="suppliers-import-result-card suppliers-import-result-card-error">' +
                        '<div class="suppliers-import-result-head">' +
                        '<i class="fas fa-times-circle suppliers-import-result-icon"></i>' +
                        '<div><strong class="suppliers-import-result-title">خطأ في الاتصال</strong>' +
                        '<p class="suppliers-import-result-error-text">' + errorMsg + '</p></div>' +
                        '</div></div>'
                    ).removeClass('suppliers-hidden').hide().fadeIn();
                }
            });
        });

    })();
</script>

<style>
    .suppliers-main .stats-section {
        border: 1px solid var(--bdr);
        border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255, 255, 255, .95) 0%, var(--s2) 100%);
        box-shadow: var(--sh);
        padding: 14px;
        margin-bottom: 14px;
    }

    .suppliers-main .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(170px, 1fr));
        gap: 12px;
    }

    .suppliers-main .stats-card {
        background: var(--s1);
        border: 1px solid var(--bdr);
        border-radius: 12px;
        padding: 12px;
        box-shadow: 0 2px 8px rgba(26, 18, 8, .07);
        position: relative;
        overflow: hidden;
    }

    .suppliers-main .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--or), var(--or2));
        opacity: .9;
    }

    .suppliers-main .stats-card .stats-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .suppliers-main .stats-card .stats-title {
        margin-top: 10px;
        color: var(--t2);
        font-size: .82rem;
        font-weight: 700;
    }

    .suppliers-main .stats-card .stats-value {
        margin-top: 7px;
        color: var(--t1);
        font-size: 1.62rem;
        line-height: 1;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }

    .suppliers-main .stats-primary .stats-icon { background: rgba(37, 99, 235, .14); color: #1d4ed8; }
    .suppliers-main .stats-success .stats-icon { background: rgba(22, 163, 74, .14); color: #15803d; }
    .suppliers-main .stats-danger .stats-icon { background: rgba(220, 38, 38, .14); color: #b91c1c; }
    .suppliers-main .stats-purple .stats-icon { background: rgba(124, 58, 237, .14); color: #6d28d9; }
    .suppliers-main .stats-cyan .stats-icon { background: rgba(8, 145, 178, .14); color: #0e7490; }
    .suppliers-main .stats-orange .stats-icon { background: rgba(217, 119, 6, .14); color: #b45309; }

    #projectsTable.suppliers-table-nowrap,
    #projectsTable.suppliers-table-nowrap th,
    #projectsTable.suppliers-table-nowrap td {
        white-space: nowrap;
    }

    .suppliers-main .suppliers-contracts-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        text-decoration: none;
    }

    .suppliers-main .suppliers-contracts-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        border-radius: 999px;
        font-size: .8rem;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, #1f4f7a, #2f6fa5);
    }

    @media (max-width: 900px) {
        .suppliers-main .stats-grid {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }

    @media (max-width: 560px) {
        .suppliers-main .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

</body>

</html>

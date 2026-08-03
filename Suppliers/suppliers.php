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

// ══════════════════════════════════════════════════════════════════════════════
// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15)
// كشفُ الأعمدة والـDDL وقت التشغيل أُسقطا (أعمدة العزل والحذف الناعم مضمونة
// بالترحيلات والسجل)؛ بُناة شروط النطاق البديلة زالت — {TENANT_SCOPE} والبوابة
// مسؤولا النطاق، والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
// ══════════════════════════════════════════════════════════════════════════════
$sup_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('suppliers screen super') : ems_tenant_db();

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

// ── H-20: عزلُ مشرف المورد بنيويًّا — يرى موردَه وحده قراءةً (403 مسجَّلة) ──
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
$spg_scope = \App\Services\Portal\SupplierPortalGuard::enforce($conn, $_SESSION['user'], 0, 'Suppliers/suppliers.php');
if ($spg_scope !== null) {
    // مشرفُ المورد لا يضيف موردين ولا يعدّلهم ولا يحذفهم — قراءةُ نطاقِه فقط
    $can_add = false; $can_edit = false; $can_delete = false;
}
$spg_filter = ($spg_scope !== null) ? " AND s.id = " . intval($spg_scope) : "";

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

    // ── القيم تُمرَّر خامًا — البوابة prepared بالكامل (لا escape يدوي) ──────────
    $name = trim($_POST['name']);
    $supplier_code = trim($_POST['supplier_code']);
    $supplier_type = $_POST['supplier_type'];
    $dealing_nature = $_POST['dealing_nature'];
    $equipment_types = isset($_POST['equipment_types']) ? implode(', ', $_POST['equipment_types']) : '';

    // ── البيانات القانونية ────────────────────────────────────────────────────
    $commercial_registration = trim($_POST['commercial_registration']);
    $identity_type = $_POST['identity_type'];
    $identity_number = trim($_POST['identity_number']);
    $identity_expiry_date = !empty($_POST['identity_expiry_date']) ? $_POST['identity_expiry_date'] : null;

    // ── البيانات التواصلية ────────────────────────────────────────────────────
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $phone_alternative = trim($_POST['phone_alternative']);
    $full_address = trim($_POST['full_address']);
    $contact_person_name = trim($_POST['contact_person_name']);
    $contact_person_phone = trim($_POST['contact_person_phone']);
    $financial_registration_status = $_POST['financial_registration_status'];
    $status = $_POST['status'];

    if ($supplier_code !== '') {
        $dup_extra = ''; $dup_params = array($supplier_code);
        if ($id > 0) { $dup_extra = " AND s.id != ?"; $dup_params[] = $id; }
        try {
            $duplicate_rows = $sup_gate->scopedQuery(array(
                'scope' => array('s' => 'suppliers'),
            ), "SELECT s.id FROM suppliers s
                WHERE {TENANT_SCOPE} AND s.supplier_code = ? AND COALESCE(s.is_deleted,0)=0$dup_extra
                LIMIT 1", $dup_params);
        } catch (\Throwable $t) { $duplicate_rows = array(); }
        if (!empty($duplicate_rows)) {
            $error_msg = ($id > 0)
                ? "كود+المورد+موجود+مسبقاً+داخل+الشركة+❌"
                : "لا+يمكن+إضافة+مورد+بنفس+الكود+داخل+الشركة+❌";
            header("Location: suppliers.php?msg=$error_msg");
            exit();
        }
    }

    $supplier_fields = array(
        'name'                          => $name,
        'supplier_code'                 => $supplier_code,
        'supplier_type'                 => $supplier_type,
        'dealing_nature'                => $dealing_nature,
        'equipment_types'               => $equipment_types,
        'commercial_registration'       => $commercial_registration,
        'identity_type'                 => $identity_type,
        'identity_number'               => $identity_number,
        'identity_expiry_date'          => $identity_expiry_date,
        'email'                         => $email,
        'phone'                         => $phone,
        'phone_alternative'             => $phone_alternative,
        'full_address'                  => $full_address,
        'contact_person_name'           => $contact_person_name,
        'contact_person_phone'          => $contact_person_phone,
        'financial_registration_status' => $financial_registration_status,
        'status'                        => $status,
    );

    if ($id > 0) {
        // ── تحديث مورد موجود (النطاق + عدم الحذف عبر البوابة) ────────────────
        try {
            $sup_gate->update('suppliers', $supplier_fields, array('id' => $id), 'COALESCE(is_deleted,0)=0');
        } catch (\Throwable $t) {
            error_log('suppliers.php update failed: ' . $t->getMessage());
        }
        header("Location: suppliers.php?msg=تم+تعديل+المورد+بنجاح+✅");
        exit;
    } else {
        // ── إضافة مورد جديد (company_id تحقنه البوابة) ────────────────────────
        try {
            $sup_gate->insert('suppliers', $supplier_fields);
        } catch (\Throwable $t) {
            error_log('suppliers.php insert failed: ' . $t->getMessage());
        }
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

    // التحقق من أن المورد تابع لشركة المستخدم (العزل عبر البوابة)
    try {
        $scope_check = $sup_gate->selectOne('suppliers', array(
            'columns' => array('id'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $scope_check = null; }
    if (!$scope_check) {
        header("Location: suppliers.php?msg=لا+يمكن+حذف+مورد+لا+يتبع+لشركتك+❌");
        exit();
    }

    // التحقق من وجود معدات أو عقود مرتبطة (count عبر البوابة)
    try {
        $equip_count = $sup_gate->count('equipments', array('where' => array('suppliers' => $delete_id)));
    } catch (\Throwable $t) { $equip_count = 0; }
    try {
        $contracts_count = $sup_gate->count('supplierscontracts', array('where' => array('supplier_id' => $delete_id)));
    } catch (\Throwable $t) { $contracts_count = 0; }

    if ($equip_count > 0 || $contracts_count > 0) {
        header("Location: suppliers.php?msg=لا+يمكن+حذف+المورد+لأنه+مرتبط+بمعدات+أو+عقود+موجودة+❌");
        exit();
    }

    // الحذف الناعم مع تعطيل الحالة (سلوك الأصل: status=0 + updated_at + ثلاثية الحذف)
    try {
        $sup_gate->update('suppliers', array(
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $current_user_id,
            'status'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ), array('id' => $delete_id), 'COALESCE(is_deleted,0)=0');
        header("Location: suppliers.php?msg=تم+حذف+المورد+بنجاح+✅");
        exit();
    } catch (\Throwable $t) {
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

$supplier_status_active_sql = "(s.status = 1 OR s.status = '1' OR TRIM(s.status) = 'نشط' OR TRIM(LOWER(s.status)) = 'active' OR TRIM(LOWER(s.status)) = 'true')";

try {
    $stats_rows = $sup_gate->scopedQuery(array(
        'scope' => array('s' => 'suppliers'),
    ), "SELECT
        COUNT(*) AS total_suppliers,
        SUM(CASE WHEN $supplier_status_active_sql THEN 1 ELSE 0 END) AS active_suppliers
      FROM suppliers s
      WHERE {TENANT_SCOPE} AND (COALESCE(s.is_deleted,0)=0)");
} catch (\Throwable $t) { $stats_rows = array(); }
if (!empty($stats_rows)) {
    $suppliers_total_count = intval($stats_rows[0]['total_suppliers']);
    $suppliers_active_count = intval($stats_rows[0]['active_suppliers']);
}
$suppliers_inactive_count = max(0, $suppliers_total_count - $suppliers_active_count);

try {
    $cstats_rows = $sup_gate->scopedQuery(array(
        'scope' => array('ssc' => 'supplierscontracts', 's' => 'suppliers'),
    ), "SELECT
        COUNT(*) AS total_contracts,
        COUNT(DISTINCT ssc.supplier_id) AS suppliers_with_contracts,
        COALESCE(SUM(ssc.forecasted_contracted_hours), 0) AS total_contracted_hours
      FROM supplierscontracts ssc
      INNER JOIN suppliers s ON s.id = ssc.supplier_id
      WHERE {TENANT_SCOPE}
        AND (COALESCE(s.is_deleted,0)=0)");
} catch (\Throwable $t) { $cstats_rows = array(); }
if (!empty($cstats_rows)) {
    $suppliers_contracts_total = intval($cstats_rows[0]['total_contracts']);
    $suppliers_with_contracts_count = intval($cstats_rows[0]['suppliers_with_contracts']);
    $suppliers_hours_total = floatval($cstats_rows[0]['total_contracted_hours']);
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
    $header_title   = 'الموردون';
    $header_icon    = 'fas fa-truck-loading';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مورد جديد');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'suppliers-header-link', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'suppliers-toggle-stats-text');
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('suppliers', 'الموردين', $can_add) as $__xlAction) {
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
           <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مورد</h5>
            </div>
        <div class="card">
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
                        <div class="form-group">
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
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display nowrap alltables suppliers-table-nowrap" style="width:100%;">
                    <thead>
                        <tr>
                            <th> الإجراءات</th>
                            <th> كود المورد</th>
                            <th> اسم المورد</th>
                            <th> عدد الآليات</th>
                            <th> الساعات المتعاقد عليها</th>
                            <th> رقم الهاتف</th>
                            <th> الحالة</th>
                        
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
                        // جلب الموردين مع إجمالي الساعات وعدد العقود والمعدات —
                        // العزل عبر {TENANT_SCOPE}؛ العدّادات المترابطة تُقيَّد
                        // بمراسلة s.id المُنطَّق (equipments/supplierscontracts
                        // تُعلنان enrich — إعلانٌ بلا تنطيقٍ إضافي، سلامة FK).
                        try {
                            $suppliers_rows = $sup_gate->scopedQuery(array(
                                'scope'  => array('s' => 'suppliers'),
                                'enrich' => array('equipments' => 'equipments', 'supplierscontracts' => 'supplierscontracts'),
                            ), "SELECT s.*,
                              (SELECT COUNT(*) FROM equipments        WHERE equipments.suppliers        = s.id) AS 'equipments',
                              (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id) AS 'num_contracts',
                              (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id) AS 'total_hours'
                              FROM suppliers s
                              WHERE {TENANT_SCOPE} AND (COALESCE(s.is_deleted,0)=0){$spg_filter}
                              ORDER BY s.id DESC");
                        } catch (\Throwable $t) { $suppliers_rows = array(); }
                        foreach ($suppliers_rows as $row) {
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
                            // عقود المورد — زرّ إجراء مدمج من عمود «عقود المورد» (مع عدد العقود)
                            $action_btns .= "<a href='supplierscontracts.php?id=" . intval($row['id']) . "' class='action-btn view supplierContractsBtn' title='عقود المورد'><i class='fas fa-file-contract'></i><span style='font-size:11px;font-weight:700;margin-inline-start:3px;'>" . intval($row['num_contracts']) . "</span></a>";
                            $action_btns .= "</div></td>";

                            echo $action_btns;
                            echo "<td><strong>" . htmlspecialchars((string) ($row['supplier_code'] ?? '-')) . "</strong></td>";
                            echo "<td>" . $supplier_name_cell . "</td>";
                            echo "<td><span class='action-btn'>" . $row['equipments'] . "</span></td>";
                            echo "<td><span class='status-active'>" . number_format($row['total_hours']) . " ساعة</span></td>";
                            echo "<td><i class='fas fa-phone phone-icon'></i>" . htmlspecialchars($row['phone']) . "</td>";

                            // عرض الحالة بالألوان
                            if ($row['status'] == "1") {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle suppliers-status-icon'></i>نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle suppliers-status-icon'></i>معلق</span></td>";
                            }

                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
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
            const isActive = String($this.data("status")) === "1";

            EmsDetailsModal.open({
                title: 'تفاصيل المورد',
                icon: 'fas fa-truck-loading',
                fields: [
                    { label: 'اسم المورد', value: $this.data("name"), icon: 'fas fa-truck', size: 'lg' },
                    { label: 'الرمز/الكود', value: $this.data("supplier_code"), icon: 'fas fa-barcode' },
                    { label: 'نوع المورد', value: $this.data("supplier_type"), icon: 'fas fa-tags' },
                    { label: 'طبيعة التعامل', value: $this.data("dealing_nature"), icon: 'fas fa-handshake' },
                    { label: 'المعدات', value: $this.data("equipment_types"), icon: 'fas fa-truck-monster', size: 'full' },
                    { label: 'رقم التسجيل التجاري', value: $this.data("commercial_registration"), icon: 'fas fa-file-contract' },
                    { label: 'نوع الهوية', value: $this.data("identity_type"), icon: 'fas fa-id-card' },
                    { label: 'رقم الهوية', value: $this.data("identity_number"), icon: 'fas fa-id-badge' },
                    { label: 'تاريخ انتهاء الهوية', value: $this.data("identity_expiry_date"), icon: 'fas fa-calendar-times' },
                    { label: 'البريد الإلكتروني', value: $this.data("email"), icon: 'fas fa-envelope', size: 'lg' },
                    { label: 'رقم الهاتف الأساسي', value: $this.data("phone"), icon: 'fas fa-phone' },
                    { label: 'رقم هاتف بديل', value: $this.data("phone_alternative"), icon: 'fas fa-phone-alt' },
                    { label: 'جهة الاتصال', value: $this.data("contact_person_name"), icon: 'fas fa-user' },
                    { label: 'هاتف جهة الاتصال', value: $this.data("contact_person_phone"), icon: 'fas fa-mobile-alt' },
                    { label: 'حالة التسجيل المالي', value: $this.data("financial_registration_status"), icon: 'fas fa-money-check' },
                    { label: 'العنوان الكامل', value: $this.data("full_address"), icon: 'fas fa-map-marker-alt', size: 'full' },
                    { label: 'الحالة', value: isActive ? 'نشط' : 'معلق', icon: 'fas fa-toggle-on', type: 'status', tone: isActive ? 'active' : 'inactive' }
                ]
            });
        });

        // إغلاق مودال العرض (متوافق مع الاستدعاءات القديمة)
        window.closeViewModal = function () {
            if (window.EmsDetailsModal) EmsDetailsModal.close();
        };

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
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>

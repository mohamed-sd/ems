<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/equipment_card_fields.php'; // كرت المعدة: حقول الهوية (عرض/حفظ مشترك)

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$equipments_has_model_id = db_table_has_column($conn, 'equipments', 'model_id'); // ربط المعدة بالموديل (سجل النوع والموديل)
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');

if (!$equipments_has_company) {
    ems_runtime_ddl($conn, "ALTER TABLE equipments ADD COLUMN company_id INT(11) NULL DEFAULT NULL", 'Equipments/equipments.php');
    ems_runtime_ddl($conn, "CREATE INDEX idx_equipments_company_id ON equipments (company_id)", 'Equipments/equipments.php');
    $equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
}

// ملاحظة عزل (هجرة البوابة · 2026-07-13): كان هنا UPDATE عابرٌ للشركات يملأ
// equipments.company_id من المورّد عند كل تحميل صفحة. أُزيل: العمود بات مملوءًا
// كليًّا (تُحقّق: 0 صفٍّ بلا شركة) ويُحقَن آليًّا عند الإدراج عبر البوابة، فكتابةٌ
// عابرةٌ للشركات لكل طلبٍ تناقض عقد العزل.

if (!$is_super_admin && !$equipments_has_company) {
    die('تعذر تفعيل عزل الشركات لجدول المعدات');
}

// بوابة العزل لهذه الشاشة: غيرُ السوبر → شركتُه؛ السوبر → عرضٌ عابرٌ مُسجَّل.
// تحكم كلَّ قراءة/كتابة (معدات/موردون/مشاريع/موديلات/عقود) في هذه الصفحة.
$eq_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('equipments super view') : ems_tenant_db();

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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المعدات ❌', 'GOV-PERM-403', '');
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
    // فحص ملكية المشروع المختار ضمن نطاق العزل (السوبر: أيُّ مشروع)
    $selected_project = $eq_gate->selectOne('project', array(
        'columns'  => array('id', 'name', 'project_code'),
        'whereRaw' => "id = ? AND status = '1'",
        'params'   => array($selected_project_id),
    ));
    if ($selected_project === null) {
        unset($_SESSION['equipments_project_id']);
        $selected_project_id = 0;
    }
}

// (أُزيل استعلام قائمة المشاريع الذي كان هنا — لم يكن يُقرأ في أيّ موضع؛
//  منتقي المشروع يأتي من صفحة select_project.php عبر ?project_id=.)

$page_title = "إدارة المعدات";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include("../insidebar.php");
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// معالجة رسالة النجاح
$success_msg = '';
if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<!-- Font Awesome من CDN لضمان ظهور الأيقونات بشكل صحيح -->
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<?php

// معالجة الحفظ أو التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10") {
        $success_msg = "❌ ليس لديك صلاحية لتعديل أو إضافة المعدات";
        goto skip_save;
    }

    // قيمٌ خام للبوابة — الربط بالمعاملات يتكفّل بالهروب (لا هروب يدويّ)؛ والحقول
    // الفارغة القابلة للإلغاء تُمرَّر NULL حقيقيًّا لا نصًّا 'NULL'. مُلخِّصات:
    //   $S=نصّ مقلَّم، $D=نصّ بقيمة افتراضية (بلا تقليم كالأصل)، $Ni/$Nf=عدد/NULL، $Nd=تاريخ/NULL.
    $edit_id   = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $model_id  = (isset($_POST['model_id']) && $_POST['model_id'] !== '') ? intval($_POST['model_id']) : 0;
    $suppliers = isset($_POST['suppliers']) ? $_POST['suppliers'] : '';   // يُستعمل أيضًا في فحص العقد
    $type      = isset($_POST['type']) ? $_POST['type'] : '';

    $S  = function ($k) { return trim($_POST[$k] ?? ''); };
    $D  = function ($k, $def) { return isset($_POST[$k]) ? $_POST[$k] : $def; };
    $Ni = function ($k) { return !empty($_POST[$k]) ? intval($_POST[$k]) : null; };
    $Nf = function ($k) { return !empty($_POST[$k]) ? floatval($_POST[$k]) : null; };
    $Nd = function ($k) { return !empty($_POST[$k]) ? $_POST[$k] : null; };

    $data = array(
        'suppliers'                     => $suppliers,
        'code'                          => $S('code'),
        'type'                          => $type,
        'name'                          => $S('name'),
        'status'                        => $D('status', ''),
        'serial_number'                 => $S('serial_number'),
        'chassis_number'                => $S('chassis_number'),
        'manufacturer'                  => $S('manufacturer'),
        'model'                         => $S('model'),
        'manufacturing_year'            => $Ni('manufacturing_year'),
        'import_year'                   => $Ni('import_year'),
        'equipment_condition'           => $D('equipment_condition', 'في حالة جيدة'),
        'operating_hours'               => $Ni('operating_hours'),
        'engine_condition'              => $D('engine_condition', 'جيدة'),
        'tires_condition'               => $D('tires_condition', 'N/A'),
        // N-21: أعمدة المالك مهجورة — بيانات الملكية في المجال المقيَّد حصرًا
        'license_number'                => $S('license_number'),
        'license_authority'             => $S('license_authority'),
        'license_expiry_date'           => $Nd('license_expiry_date'),
        'inspection_certificate_number' => $S('inspection_certificate_number'),
        'last_inspection_date'          => $Nd('last_inspection_date'),
        'current_location'              => $S('current_location'),
        'availability_status'           => $D('availability_status', 'متاحة للعمل'),
        'estimated_value'               => $Nf('estimated_value'),
        'daily_rental_price'            => $Nf('daily_rental_price'),
        'monthly_rental_price'          => $Nf('monthly_rental_price'),
        'insurance_status'              => $D('insurance_status', ''),
        'general_notes'                 => $S('general_notes'),
        'last_maintenance_date'         => $Nd('last_maintenance_date'),
    );
    if ($equipments_has_model_id) {
        $data['model_id'] = $model_id > 0 ? $model_id : null;
    }



    // التحقق من عدم تجاوز العدد المتعاقد عليه (فقط عند الإضافة)
    if ($edit_id == 0  && $suppliers && $type) {
        // عقد المورّد لهذا النوع (معزولًا) — scopedQuery: النطاق sc، والإثراء sce (LEFT
        // بدل JOIN الداخلي مع sce.id IS NOT NULL لحفظ دلالة الأصل).
        $contract_rows = $eq_gate->scopedQuery(array(
            'scope'  => array('sc' => 'supplierscontracts'),
            'enrich' => array('sce' => 'suppliercontractequipments'),
        ),
            "SELECT sc.id, sce.equip_count
               FROM supplierscontracts sc
               LEFT JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
              WHERE {TENANT_SCOPE}
                AND sc.supplier_id = ?
                AND sce.equip_type = ?
                AND sc.status = 1
                AND sce.id IS NOT NULL
              LIMIT 1",
            array($suppliers, $type)
        );

        if (!empty($contract_rows)) {
            $contracted_count = intval($contract_rows[0]['equip_count']);

            // عدد المعدات المضافة حاليًا لنفس المورّد والنوع (معزولًا عبر البوابة)
            $current_added = $eq_gate->count('equipments', array(
                'whereRaw' => "suppliers = ? AND type = ? AND status = 1",
                'params'   => array($suppliers, $type),
            ));

            // التحقق من عدم تجاوز العدد المتعاقد عليه
            if ($current_added >= $contracted_count) {
                $success_msg = "⚠️ تحذير: تم الوصول للحد الأقصى! العدد المتعاقد عليه: $contracted_count | المضاف حالياً: $current_added. لا يمكن إضافة المزيد من المعدات.";
                goto skip_save;
            }
        }
    }

    try {
        // الإدراج/التعديل عبر البوابة: تُحقن company_id آليًّا وتُعزَل الكتابة بالشركة
        // (السوبر عبر forAllTenants يعدّل أيَّ صفّ كسلوك الأصل). الربط بالمعاملات = لا حقن.
        if ($edit_id > 0) {
            $eq_gate->update('equipments', $data, array('id' => $edit_id));
            $card_eq_id = $edit_id;
            $msg = "تم+تعديل+المعدة+بنجاح+✅";
        } else {
            $card_eq_id = (int) $eq_gate->insert('equipments', $data);
            $msg = "تمت+إضافة+المعدة+بنجاح+✅";
        }

        // حفظ حقول كرت المعدة (الهوية/العدّاد) — مساعدٌ مخصَّص، مقيَّدٌ بالشركة
        $card_scope = ($is_super_admin || !$equipments_has_company) ? "" : " AND company_id = $company_id";
        if (function_exists('ems_save_equipment_card_fields')) {
            ems_save_equipment_card_fields($conn, $card_eq_id, ($edit_id <= 0), $card_scope);
        }
        ems_gov_flash_redirect('equipments.php', "$msg", 'GOV-INFO-200', '');
        exit;
    } catch (\Throwable $e) {
        $success_msg = "خطأ في الحفظ: " . $e->getMessage();
    }

    skip_save:
}

// في حالة تعديل تجهيز البيانات
$editData = [];
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10" && isset($_GET['edit'])) {
    $success_msg = "❌ ليس لديك صلاحية لتعديل المعدات";
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    // جلب المعدة للتعديل ضمن نطاق العزل (السوبر: أيّ معدة)
    $row = $eq_gate->selectOne('equipments', array('where' => array('id' => $editId)));
    if ($row !== null) {
        $editData = $row;
    }
}
?>

<div class="main">
    <!-- عنوان الصفحة -->
    <!-- Unified header: pre-built final structure (data-ems-unified-header skips the JS rebuild). Styling: ems.main.all.style.css (.header) -->
    <?php
/* AS-04/AS-05 (UXR-01): الرأسُ الموحَّدُ بدلَ الرأسِ اليدويِّ المُحاكي —
   مصدرٌ واحدٌ للبنيةِ، والأفعالُ وزرُّ العودةِ منقولانِ كما هما. */
$header_icon = 'fas fa-cogs';
$header_title_html = htmlspecialchars('إدارة المعدات', ENT_QUOTES, 'UTF-8');
ob_start(); ?>
            <?php if ($_SESSION['user']['role'] != "10") { ?>
            <!-- ── نظام Excel الموحّد (Unified Excel Framework) ── -->
            <?php
            require_once __DIR__ . '/../includes/excel_ui.php';
            $__xlBase = ems_excel_endpoint_url();
            ?>
            <a href="<?php echo htmlspecialchars($__xlBase . '?entity=equipments&action=template', ENT_QUOTES, 'UTF-8'); ?>" class="btn eq-xl-tpl">
                <i class="fas fa-file-excel"></i> تحميل النموذج
            </a>
            <a href="<?php echo htmlspecialchars($__xlBase . '?entity=equipments&action=export', ENT_QUOTES, 'UTF-8'); ?>" class="btn eq-xl-exp">
                <i class="fas fa-file-export"></i> تصدير Excel
            </a>
            <button type="button" class="btn eq-1" data-ems-excel-import="equipments" data-ems-excel-title="المعدات">
                <i class="fas fa-file-import"></i> استيراد Excel
            </button>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة معدة جديدة
            </a>
            <?php } ?><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
ob_start(); ?><a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a><?php
$header_back = array('raw' => trim((string) ob_get_clean()));
include __DIR__ . '/../includes/page_header.php';
?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('equipment', 'نظرةٌ عامة'); ?>

    <?php if (!empty($success_msg)):
        $isSuccess = strpos($success_msg, '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php
    /* UXW-01 بوابة ٩: حزمةُ الحالاتِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ
       افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components
       التي تُحمِّلها القشرة. */
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا معداتٍ مسجلةً في سجلِّ المعداتِ بعدُ',
                               'أضف معدةً جديدةً بزرِّ «إضافة معدة جديدة» أو راجع فلاترَ العرض');
    }
    ?>

    <?php if ($_SESSION['user']['role'] != "10") { ?>
    <!-- فورم إضافة / تعديل معدة -->
    <!-- UXW-01: حقلُ رمزِ الحماية كان محشورًا داخل سمةِ class فلا يُرسَل — نُقل إلى جسمِ الفورم -->
    <form id="projectForm" action="" method="post" class="allforms<?php echo !empty($editData) ? ' allforms-visible' : ''; ?>">
        <?= csrf_field() ?>
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
                        <label for="suppliers">
                            <i class="fas fa-truck-loading"></i>
                            المورد <span class="required-indicator">*</span>
                        </label>
                        <select name="suppliers" id="suppliers" required>
                            <option value="">-- اختر المورد --</option>
                            <?php
                            // موردو الشركة (السوبر: الكل) عبر البوابة
                            $supplier_rows = $eq_gate->select('suppliers', array(
                                'columns'  => array('id', 'name'),
                                'whereRaw' => "status = 1",
                                'orderBy'  => 'name ASC',
                            ));
                            foreach ($supplier_rows as $supplier) {
                                $selected = (!empty($editData) && $editData['suppliers'] == $supplier['id']) ? 'selected' : '';
                                echo "<option value='" . intval($supplier['id']) . "' $selected>" . htmlspecialchars($supplier['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label for="code">
                            <i class="fas fa-barcode"></i>
                            كود المعدة <span class="required-indicator">*</span>
                        </label>
                        <input type="text" name="code" id="code" placeholder="أدخل كود المعدة"
                               value="<?php echo isset($editData['code']) ? htmlspecialchars($editData['code']) : ''; ?>" required />
                    </div>

                    <div>
                        <label for="type">
                            <i class="fas fa-list-alt"></i>
                            نوع المعدة <span class="required-indicator">*</span>
                        </label>
                        <select name="type" id="type" required>
                            <option value="">-- حدد نوع المعدة --</option>
                            <?php
                            // أنواع المعدات — كتالوج عام (managed) عبر البوابة
                            $type_rows = $eq_gate->select('equipments_types', array(
                                'columns'  => array('id', 'type'),
                                'whereRaw' => "status = 1",
                                'orderBy'  => 'type ASC',
                            ));
                            foreach ($type_rows as $type_row) {
                                $selected = (!empty($editData) && $editData['type'] == $type_row['id']) ? 'selected' : '';
                                echo "<option value='" . intval($type_row['id']) . "' $selected>" . htmlspecialchars($type_row['type']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label for="name">
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
                        <label for="serial_number">
                            <i class="fas fa-hashtag"></i>
                            رقم المعدة/الرقم التسلسلي
                        </label>
                        <input type="text" name="serial_number" id="serial_number" placeholder="مثال: EXC-2024-001"
                               value="<?php echo isset($editData['serial_number']) ? htmlspecialchars($editData['serial_number']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="chassis_number">
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

                    <?php if ($equipments_has_model_id): ?>
                    <div>
                        <label for="model_id">
                            <i class="fas fa-clipboard-list"></i>
                            الموديل المرجعي (سجل النوع والموديل)
                        </label>
                        <select name="model_id" id="model_id">
                            <option value="">-- اختر من السجل (اختياري) --</option>
                            <?php
                            // سجل النوع والموديل (fleet_model) — شركةُ السياق عبر البوابة (السوبر: الكل).
                            // fleet_model ناعمُ الحذف فتستثني البوابة is_deleted تلقائيًّا (يطابق is_deleted=0).
                            $fm_rows = $eq_gate->select('fleet_model', array(
                                'columns'  => array('id', 'code', 'model_name', 'manufacturer', 'equipment_type_id', 'operating_category'),
                                'whereRaw' => "status = 'active'",
                                'orderBy'  => 'code ASC',
                            ));
                            $cur_model_id = isset($editData['model_id']) ? intval($editData['model_id']) : 0;
                            foreach ($fm_rows as $fm_row) {
                                $sel = ($cur_model_id === intval($fm_row['id'])) ? 'selected' : '';
                                $label = $fm_row['code'] . ' — ' . $fm_row['model_name'];
                                echo "<option value='" . intval($fm_row['id']) . "' $sel"
                                    . " data-type='" . intval($fm_row['equipment_type_id']) . "'"
                                    . " data-manufacturer='" . htmlspecialchars($fm_row['manufacturer'] ?? '', ENT_QUOTES) . "'"
                                    . " data-model='" . htmlspecialchars($fm_row['model_name'] ?? '', ENT_QUOTES) . "'"
                                    . " data-category='" . htmlspecialchars($fm_row['operating_category'] ?? '', ENT_QUOTES) . "'>"
                                    . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                        <small class="eq-2">عند الاختيار تُملأ تلقائياً حقول النوع والماركة والموديل من السجل.</small>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="manufacturer">
                            <i class="fas fa-building"></i>
                            الماركة/الشركة المصنعة
                        </label>
                        <input type="text" name="manufacturer" id="manufacturer" placeholder="مثال: كاتربيلر، كوماتسو، هيونداي"
                               value="<?php echo isset($editData['manufacturer']) ? htmlspecialchars($editData['manufacturer']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="model">
                            <i class="fas fa-car"></i>
                            الموديل/الطراز
                        </label>
                        <input type="text" name="model" id="model" placeholder="مثال: 320D, PC200, HD1024"
                               value="<?php echo isset($editData['model']) ? htmlspecialchars($editData['model']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="manufacturing_year">
                            <i class="fas fa-calendar"></i>
                            سنة الصنع
                        </label>
                        <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="مثال: 2018" min="1950" max="2099"
                               value="<?php echo isset($editData['manufacturing_year']) ? $editData['manufacturing_year'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="import_year">
                            <i class="fas fa-calendar-plus"></i>
                            سنة الاستيراد/البدء
                        </label>
                        <input type="number" name="import_year" id="import_year" placeholder="مثال: 2020" min="1950" max="2099"
                               value="<?php echo isset($editData['import_year']) ? $editData['import_year'] : ''; ?>" />
                    </div>

                    <?php
                    // ─── كرت المعدة: حقول الهوية والمصدر + العدّاد (مشترك) ───
                    if (function_exists('ems_render_equipment_card_fields')) {
                        ems_render_equipment_card_fields($editData, 'form-section-header');
                    }
                    ?>

                    <!-- ================================= -->
                    <!-- قسم: الحالة الفنية والمواصفات -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-wrench"></i> الحالة الفنية والمواصفات</h6>
                    </div>

                    <div>
                        <label for="equipment_condition">
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
                        <label for="operating_hours">
                            <i class="fas fa-clock"></i>
                            ساعات التشغيل (للمعدات الثقيلة)
                        </label>
                        <input type="number" name="operating_hours" id="operating_hours" placeholder="مثال: 5400 ساعة" min="0"
                               value="<?php echo isset($editData['operating_hours']) ? $editData['operating_hours'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="engine_condition">
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
                        <label for="tires_condition">
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

                    <!-- N-21: قسم بيانات الملكية نُزع — بيانات الملكية في المجال المقيَّد (equipment_ownership_registry) خلف صلاحية فردية حصرًا -->
                    <!-- ================================= -->
                    <!-- قسم: الوثائق والتسجيلات -->
                    <!-- ================================= -->
                    <div class="form-section-header">
                        <h6><i class="fas fa-file-contract"></i> الوثائق والتسجيلات</h6>
                    </div>

                    <div>
                        <label for="license_number">
                            <i class="fas fa-address-card"></i>
                            رقم الترخيص/التسجيل
                        </label>
                        <input type="text" name="license_number" id="license_number" placeholder="مثال: VEH-2024-12345"
                               value="<?php echo isset($editData['license_number']) ? htmlspecialchars($editData['license_number']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="license_authority">
                            <i class="fas fa-landmark"></i>
                            جهة الترخيص
                        </label>
                        <input type="text" name="license_authority" id="license_authority" placeholder="مثال: المرور، وزارة النقل"
                               value="<?php echo isset($editData['license_authority']) ? htmlspecialchars($editData['license_authority']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="license_expiry_date">
                            <i class="fas fa-calendar-times"></i>
                            تاريخ انتهاء الترخيص
                        </label>
                        <input type="date" name="license_expiry_date" id="license_expiry_date"
                               value="<?php echo isset($editData['license_expiry_date']) ? $editData['license_expiry_date'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="inspection_certificate_number">
                            <i class="fas fa-certificate"></i>
                            رقم شهادة الفحص
                        </label>
                        <input type="text" name="inspection_certificate_number" id="inspection_certificate_number" placeholder="رقم شهادة الفحص الفنية"
                               value="<?php echo isset($editData['inspection_certificate_number']) ? htmlspecialchars($editData['inspection_certificate_number']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="last_inspection_date">
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
                        <label for="current_location">
                            <i class="fas fa-location-arrow"></i>
                            الموقع الحالي
                        </label>
                        <input type="text" name="current_location" id="current_location" placeholder="مثال: منجم الذهب الشرقي، مستودع الخرطوم"
                               value="<?php echo isset($editData['current_location']) ? htmlspecialchars($editData['current_location']) : ''; ?>" />
                    </div>

                    <div>
                        <label for="availability_status">
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
                        <label for="estimated_value">
                            <i class="fas fa-money-bill-wave"></i>
                            القيمة المقدرة للمعدة (بالدولار)
                        </label>
                        <input type="number" name="estimated_value" id="estimated_value" placeholder="مثال: 150000" min="0" step="0.01"
                               value="<?php echo isset($editData['estimated_value']) ? $editData['estimated_value'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="daily_rental_price">
                            <i class="fas fa-calendar-day"></i>
                            سعر التأجير اليومي (بالدولار)
                        </label>
                        <input type="number" name="daily_rental_price" id="daily_rental_price" placeholder="مثال: 500" min="0" step="0.01"
                               value="<?php echo isset($editData['daily_rental_price']) ? $editData['daily_rental_price'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="monthly_rental_price">
                            <i class="fas fa-calendar-alt"></i>
                            سعر التأجير الشهري (بالدولار)
                        </label>
                        <input type="number" name="monthly_rental_price" id="monthly_rental_price" placeholder="مثال: 10000" min="0" step="0.01"
                               value="<?php echo isset($editData['monthly_rental_price']) ? $editData['monthly_rental_price'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="insurance_status">
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
                        <label for="general_notes">
                            <i class="fas fa-comment-alt"></i>
                            ملاحظات عامة
                        </label>
                        <textarea name="general_notes" id="general_notes" rows="3" placeholder="مثال: معدة موثوقة، تحتاج إلى صيانة دورية كل 3 أشهر"><?php echo isset($editData['general_notes']) ? htmlspecialchars($editData['general_notes']) : ''; ?></textarea>
                    </div>

                    <div>
                        <label for="last_maintenance_date">
                            <i class="fas fa-wrench"></i>
                            تاريخ آخر صيانة
                        </label>
                        <input type="date" name="last_maintenance_date" id="last_maintenance_date"
                               value="<?php echo isset($editData['last_maintenance_date']) ? $editData['last_maintenance_date'] : ''; ?>" />
                    </div>

                    <div>
                        <label for="status">
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
                    <button type="button" class="btn-secondary" id="clearFiltersBtn">
                        <i class="fas fa-times-circle"></i> إلغاء الفلاتر
                    </button>
                </div>

                <div class="filters-grid">
                    <div class="filter-item">
                        <label for="filterSupplier"><i class="fas fa-truck-loading"></i> فلترة بالمورد</label>
                        <select id="filterSupplier" class="filter-select">
                            <option value="">— جميع الموردين —</option>
                            <?php
                            // فلتر الموردين — نفس مصدر البوابة المعزول
                            $supplier_filter_rows = $eq_gate->select('suppliers', array(
                                'columns'  => array('id', 'name'),
                                'whereRaw' => "status = 1",
                                'orderBy'  => 'name ASC',
                            ));
                            foreach ($supplier_filter_rows as $supplier) {
                                echo "<option value='" . htmlspecialchars($supplier['name']) . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="filterType"><i class="fas fa-list-alt"></i> فلترة بالنوع</label>
                        <select id="filterType" class="filter-select">
                            <option value="">— جميع الأنواع —</option>
                            <?php
                            // فلتر الأنواع — كتالوج عام عبر البوابة
                            $type_filter_rows = $eq_gate->select('equipments_types', array(
                                'columns'  => array('id', 'type'),
                                'whereRaw' => "status = 1",
                                'orderBy'  => 'type ASC',
                            ));
                            foreach ($type_filter_rows as $type_row) {
                                echo "<option value='" . htmlspecialchars($type_row['type']) . "'>" . htmlspecialchars($type_row['type']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="filterStatus"><i class="fas fa-toggle-on"></i> فلترة بالحالة</label>
                        <select id="filterStatus" class="filter-select">
                            <option value="">— جميع الحالات —</option>
                            <option value="نشط">نشط</option>
                            <option value="غير نشط">غير نشط</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="filterAvailability"><i class="fas fa-traffic-light"></i> فلترة بالتوفر</label>
                        <select id="filterAvailability" class="filter-select">
                            <option value="">— جميع حالات التوفر —</option>
                            <option value="متاحة للعمل">متاحة للعمل</option>
                            <option value="مشغولة حالياً">مشغولة حالياً</option>
                            <option value="تحت الصيانة">تحت الصيانة</option>
                            <option value="معطلة مؤقتاً">معطلة مؤقتاً</option>
                        </select>
                    </div>
                </div>

                <div class="filters-summary eq-3" id="filtersSummary">
                    <span class="summary-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="summary-text"></span>
                </div>
            </div>

            <!-- أزرار إظهار/إخفاء المجموعات — UXW-01 بوابة ٣: العائلةُ المؤسسية ems-btn -->
            <div class="column-groups-toggle">
                <button type="button" class="ems-btn-group-toggle active" data-group="basic" title="المعلومات الأساسية">
                    <i class="fas fa-info-circle"></i> أساسية
                </button>
                <button type="button" class="ems-btn-group-toggle active" data-group="identification" title="بيانات التعريف">
                    <i class="fas fa-id-card"></i> التعريف
                </button>
                <button type="button" class="ems-btn-group-toggle" data-group="manufacturing" title="بيانات الصنع">
                    <i class="fas fa-industry"></i> الصنع
                </button>
                <button type="button" class="ems-btn-group-toggle" data-group="technical" title="الحالة الفنية">
                    <i class="fas fa-wrench"></i> فنية
                </button>
                <button type="button" class="ems-btn-group-toggle active" data-group="status" title="الحالة والإجراءات">
                    <i class="fas fa-toggle-on"></i> الحالة
                </button>
                <button type="button" class="ems-btn-group-toggle-all" title="إظهار/إخفاء الكل">
                    <i class="fas fa-eye"></i> الكل
                </button>
            </div>

            <table id="projectsTable" class="display nowrap">
                <thead>
                    <tr>
                        <th data-group="basic"><i class="fas fa-hashtag"></i> #</th>
                        <th data-group="basic"><i class="fas fa-truck-loading"></i> المورد</th>
                        <th data-group="basic"><i class="fas fa-barcode"></i> كود المعدة</th>
                        <th data-group="identification"><i class="fas fa-hashtag"></i> الرقم التسلسلي</th>
                        <th data-group="basic"><i class="fas fa-list-alt"></i> نوع المعدة</th>
                        <th data-group="basic"><i class="fas fa-tag"></i> الاسم الوصفي</th>
                        <th data-group="manufacturing"><i class="fas fa-car"></i> الموديل</th>
                        <th data-group="manufacturing"><i class="fas fa-calendar"></i> سنة الصنع</th>
                        <th data-group="technical"><i class="fas fa-cogs"></i> حالة المعدة</th>
                        <th data-group="technical"><i class="fas fa-traffic-light"></i> التوفر</th>
                        <th data-group="status"><i class="fas fa-toggle-on"></i> الحالة</th>
                        <th data-group="status"><i class="fas fa-sliders-h"></i> إجراءات</th>
                        <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                        <th class="ems-fn-th" data-fn="1">مسار التوزيع</th>
                        <th class="ems-fn-th" data-fn="1">الفئة التشغيلية</th>
                        <th class="ems-fn-th" data-fn="1">بلد الصنع</th>
                        <th class="ems-fn-th" data-fn="1">حالة الرقم التسلسلي</th>
                        <th class="ems-fn-th" data-fn="1">رقم الشاسيه</th>
                        <th class="ems-fn-th" data-fn="1">رقم الموتور</th>
                        <th class="ems-fn-th" data-fn="1">رقم اللوحة</th>
                        <th class="ems-fn-th" data-fn="1">المصدر</th>
                        <th class="ems-fn-th" data-fn="1">المالك القانوني</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ الدخول</th>
                        <th class="ems-fn-th none" data-fn="1">العدّاد الافتتاحي</th>
                        <th class="ems-fn-th none" data-fn="1">العدّاد الحالي</th>
                        <th class="ems-fn-th none" data-fn="1">وحدة العدّاد</th>
                        <th class="ems-fn-th none" data-fn="1">تكلفة الشراء</th>
                        <th class="ems-fn-th none" data-fn="1">العمر الإنتاجي بالساعات</th>
                        <th class="ems-fn-th none" data-fn="1">معدل الإهلاك بالساعة</th>
                        <th class="ems-fn-th none" data-fn="1">قيمة الخردة</th>
                        <th class="ems-fn-th none" data-fn="1">الإهلاك المتراكم</th>
                        <th class="ems-fn-th none" data-fn="1">القيمة الدفترية</th>
                        <th class="ems-fn-th none" data-fn="1">الساعات المتراكمة بالسجل</th>
                        <th class="ems-fn-th none" data-fn="1">الساعات بالتشغيل</th>
                        <th class="ems-fn-th none" data-fn="1">فرق الساعات</th>
                        <th class="ems-fn-th none" data-fn="1">الممول</th>
                        <th class="ems-fn-th none" data-fn="1">نموذج التمويل</th>
                        <th class="ems-fn-th none" data-fn="1">حصص الملكية</th>
                        <th class="ems-fn-th none" data-fn="1">الموقع الحالي</th>
                        <th class="ems-fn-th none" data-fn="1">الحالة الأسطولية</th>
                        <th class="ems-fn-th none" data-fn="1">الجاهزية الفنية</th>
                        <th class="ems-fn-th none" data-fn="1">سجّله</th>
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                        <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                        <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                        <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                        <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                        </tr>
                </thead>
                <tbody>
                    <?php
                    $card_state_select = db_table_has_column($conn, 'equipments', 'card_state')
                        ? "m.card_state,"
                        : "'active' AS card_state,";

                    // العزل مسؤولية البوابة عبر {TENANT_SCOPE} (غيرُ السوبر → شركته؛ السوبر → 1=1).
                    // JOIN المورّد الداخلي أُبدل LEFT + s.id IS NOT NULL (شرط الإثراء)؛ وفلترُ
                    // المشروع للسوبر يُلحَق بعد الرمز عبر EXISTS على operations (المعلَنة إثراءً).
                    $list_extra  = " AND s.id IS NOT NULL";
                    $list_params = array();
                    if ($is_super_admin && $selected_project_id > 0) {
                        $list_extra .= " AND EXISTS (SELECT 1 FROM operations so WHERE so.equipment = m.id AND so.project_id = ?)";
                        $list_params[] = $selected_project_id;
                    }

                    $list_sql = "
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
                            m.availability_status,
                            $card_state_select
                            o.project_id,
                            o.status AS operation_status,
                            COUNT(DISTINCT d.id) AS drivers_count
                        FROM equipments m
                        LEFT JOIN suppliers s ON m.suppliers = s.id
                        LEFT JOIN operations o
                            ON o.equipment = m.id
                            AND o.status = '1'
                        LEFT JOIN equipment_drivers ed
                            ON ed.equipment_id = m.id
                        LEFT JOIN employees d
                            ON d.id = ed.employee_id
                            AND ed.status = '1'
                        WHERE {TENANT_SCOPE}$list_extra
                        GROUP BY m.id
                        ORDER BY m.id DESC
                    ";
                    $rows = $eq_gate->scopedQuery(array(
                        'scope'  => array('m' => 'equipments'),
                        'enrich' => array('s' => 'suppliers', 'o' => 'operations', 'ed' => 'equipment_drivers', 'd' => 'employees'),
                    ), $list_sql, $list_params);
                    $i = 1;
                    foreach ($rows as $row) {
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
                            $p = $eq_gate->selectOne('project', array(
                                'columns' => array('name'),
                                'where'   => array('id' => $row['project']),
                            ));
                            if ($p !== null) {
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

                        // N-21: عمود المالك نُزع — لا بُعد مالك في أي عرض تشغيلي

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
                                                                                                                echo "<a href='add_drivers.php?equipment_id=" . $row['id'] . "' class='action-btn btn-secondary' title='إدارة المشغلين'>
                                                                        <i class='fas fa-user-cog'></i>
                                                                    </a>";
                                                } else {
                                                                                                                echo "<a href='equipments.php?edit=" . $row['id'] . "' class='action-btn btn-secondary' title='تعديل'>
                                                                        <i class='fas fa-edit'></i>
                                                                    </a>";
                                                        // يمكن إضافة زر حذف هنا إذا لزم الأمر
                                                }
                                                // ── كرت المعدة: شارة الحالة + اعتماد ──
                                                $card_state = isset($row['card_state']) ? $row['card_state'] : 'active';
                                                if ($card_state === 'active') {
                                                    echo "<span class='badge-available' title='كرت معتمد' data-ems-c='eq-4'><i class='fas fa-id-card'></i> نشط</span>";
                                                } else {
                                                    echo "<span class='badge-busy' title='كرت مسودة' data-ems-c='eq-4'><i class='fas fa-id-card'></i> مسودة</span>";
                                                    if ($can_edit) {
                                                        // UXW-01: كان رمزُ الحماية وسمَ قالبٍ حرفيًّا داخلَ سلسلةٍ مطبوعةٍ فلا يُنفَّذ — صار نداءً حقيقيًّا
                                                        echo "<form method='post' action='approve_card.php' class='d-inline' onsubmit=\"return confirm('اعتماد كرت هذه المعدة؟');\">"
                                                            . csrf_field()
                                                            . "<input type='hidden' name='equipment_id' value='" . intval($row['id']) . "'>"
                                                            . "<input type='hidden' name='return' value='equipments.php'>"
                                                            . "<button type='submit' class='action-btn' data-ems-c='eq-5' title='اعتماد الكرت'><i class='fas fa-circle-check'></i></button>"
                                                            . "</form>";
                                                    }
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
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    (function () {
        $(document).ready(function () {
            /* UXW-01 بوابة ٥: تهيئةُ جدولِ المعدات انتقلت إلى المكوّنِ المركزي
               (assets/js/ui-unification.js — initializeMissingDataTables):
               لغةٌ عربيةٌ وضبطُ أعمدةٍ وزرُّ إكسل موحَّد. هنا يُربَط ما يخصُّ
               الشاشةَ (الفلاتر ومجموعاتُ الأعمدة) بعد التهيئةِ المركزية —
               فإن سبقت التهيئةُ رُبط فورًا، وإلا انتظرنا حدثَ init.dt. */
            function bindEquipmentsTable() {
            var table = $('#projectsTable').DataTable();

            // نظام إظهار/إخفاء المجموعات — خريطة الفهارس تُمرّر للوحدة الموحّدة.
            // الحالة الافتراضية تؤخذ من كلاس active على الأزرار (الصنع/الفنية مخفيتان).
            var columnGroups = {
                'basic': [0, 1, 2, 4, 5],        // #، المورد، كود المعدة، النوع، الاسم
                'identification': [3],            // رقم تسلسلي
                'manufacturing': [6, 7],          // الموديل، سنة الصنع
                'technical': [8, 9],              // حالة المعدة، التوفر
                'status': [10, 11]                // الحالة، الإجراءات — N-21: عمود المالك نُزع
            };

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
                $(this).addClass('btn-secondary');
                setTimeout(function() {
                    $('#clearFiltersBtn').removeClass('btn-secondary');
                }, 300);
            });

            // إظهار/إخفاء المجموعات — موحّد عبر assets/js/column-groups.js
            // (الأصنافُ اعتُمدت باسمِ العائلةِ المؤسسية ems-btn — المحدِّدان يمرَّران صراحةً)
            if (window.EmsColumnGroups) {
                EmsColumnGroups.init({
                    storageKey: 'equipmentsGroupStates',
                    mode: 'datatable',
                    table: table,
                    columnMap: columnGroups,
                    buttons: '.ems-btn-group-toggle[data-group]',
                    allButton: '.ems-btn-group-toggle-all'
                });
            }
            }

            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#projectsTable')) {
                bindEquipmentsTable();
            } else {
                $('#projectsTable').one('init.dt', bindEquipmentsTable);
            }
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

        // ── وراثة بيانات الموديل (سجل النوع والموديل) عبر AJAX ──
        var fleetModelSelect = document.getElementById('model_id');
        if (fleetModelSelect) {
            fleetModelSelect.addEventListener('change', function () {
                if (!this.value) { return; }
                fetch('get_model_data.php?model_id=' + encodeURIComponent(this.value), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j || !j.success || !j.data) { return; }
                        var d = j.data;
                        function setVal(id, v) {
                            var el = document.getElementById(id);
                            if (el && v !== null && v !== undefined && v !== '') {
                                el.value = v;
                                // تحديث واجهة القائمة الموحّدة (ems-select) عند ضبط القيمة برمجياً
                                el.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                        if (d.equipment_type_id && d.equipment_type_id !== 0) { setVal('type', d.equipment_type_id); }
                        setVal('manufacturer', d.manufacturer);
                        setVal('model', d.model_name);
                        setVal('operating_category', d.operating_category);
                        setVal('capacity', d.std_capacity);
                        setVal('capacity_uom', d.std_capacity_uom);
                    })
                    .catch(function () { /* وراثة اختيارية — تجاهل الفشل */ });
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
                { label: 'الموديل المرجعي (السجل)', value: (data.fleet_model_code ? (data.fleet_model_code + (data.fleet_model_name ? ' — ' + data.fleet_model_name : '')) : 'غير محدد'), icon: 'fas fa-clipboard-list' },
                { label: 'حالة الكرت', value: (data.card_state === 'active' ? 'نشط (معتمد)' : 'مسودة'), icon: 'fas fa-id-card' },
                { label: 'تنبيهات الوثائق', value: (function(){ var e=parseInt(data.docs_expired||0), s=parseInt(data.docs_soon||0); if(!e&&!s) return 'لا تنبيهات'; var p=[]; if(e)p.push(e+' منتهية'); if(s)p.push(s+' قاربت'); return p.join(' · '); })(), icon: 'fas fa-file-circle-exclamation' },
                { label: 'الفئة التشغيلية', value: eqVal(data.operating_category), icon: 'fas fa-layer-group' },
                { label: 'بلد الصنع', value: eqVal(data.origin_country), icon: 'fas fa-globe' },
                { label: 'رقم الموتور', value: eqVal(data.engine_no), icon: 'fas fa-cog' },
                { label: 'رقم اللوحة', value: eqVal(data.plate_no), icon: 'fas fa-id-card-alt' },
                { label: 'السعة', value: (data.capacity ? (data.capacity + ' ' + (data.capacity_uom || '')) : 'غير محدد'), icon: 'fas fa-weight-hanging' },
                { label: 'المقاسات الفنية', value: eqVal(data.dimensions), icon: 'fas fa-vector-square' },
                { label: 'نوع المصدر', value: eqVal(data.source_type), icon: 'fas fa-handshake' },
                { label: 'تاريخ الدخول', value: eqVal(data.entry_date), icon: 'fas fa-calendar-day' },
                { label: 'تكلفة الشراء', value: (data.acquisition_cost ? (data.acquisition_cost + ' ' + (data.acquisition_currency || '')) : 'غير محدد'), icon: 'fas fa-money-check-dollar' },
                { label: 'العدّاد الافتتاحي', value: (data.opening_meter ? (data.opening_meter + ' ' + (data.meter_uom || '')) : 'غير محدد'), icon: 'fas fa-gauge' },
                { label: 'مصدر العدّاد', value: eqVal(data.meter_source), icon: 'fas fa-satellite-dish' },
                { label: 'سنة الصنع', value: eqVal(data.manufacturing_year), icon: 'fas fa-calendar' },
                { label: 'سنة الاستيراد', value: eqVal(data.import_year), icon: 'fas fa-calendar-plus' },
                { label: 'حالة المعدة', value: eqVal(data.equipment_condition), icon: 'fas fa-cogs' },
                { label: 'ساعات التشغيل', value: data.operating_hours ? (data.operating_hours + ' ساعة') : 'غير محدد', icon: 'fas fa-clock' },
                { label: 'حالة المحرك', value: eqVal(data.engine_condition), icon: 'fas fa-car-crash' },
                { label: 'حالة الإطارات', value: eqVal(data.tires_condition), icon: 'fas fa-circle-notch' },



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
                    html: '<div class="eq-6"><i class="fas fa-spinner fa-spin"></i> جار التحميل...</div>' }],
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
                            html: '<div class="eq-7">تعذر تحميل البيانات</div>' });
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
                        html: '<div class="eq-7">تعذر الاتصال بالخادم</div>' });
                }
            });
        });

        // إغلاق متوافق مع الاستدعاءات القديمة
        function closeEquipmentModal() { if (window.EmsDetailsModal) EmsDetailsModal.close(); }

        // Toggle Form Functionality
    })();
</script>

</div> <!-- closing main div -->
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>
</html>

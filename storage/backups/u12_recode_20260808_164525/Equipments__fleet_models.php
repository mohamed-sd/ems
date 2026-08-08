<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/security.php'; // validate_file_upload / generate_safe_filename

$perms = get_page_permissions($conn);
if (!$perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا توجد صلاحية لعرض هذه الصفحة', 'GOV-PERM-403', '');
    exit();
}

// ── عزل الشركة (نفس نمط equipments.php) ─────────────────────────────
$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-INFO-200', '');
    exit();
}

// قيمة الشركة المخزّنة (NULL للسوبر أدمن)
$company_val   = $is_super_admin ? null : $company_id;
// قيد نطاق الشركة للاستعلامات النصّية (company_id دائماً intval)
$company_scope = $is_super_admin ? '' : " AND company_id = $company_id";

// بوابة العزل (نفس نمط equipments.php): غيرُ السوبر → شركته؛ السوبر → forAllTenants مُسجَّل.
$fm_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('fleet_models super view') : ems_tenant_db();

// ── قوائم ثابتة (Static dropdowns حسب المتطلّب) ──────────────────────
$operating_categories = ['حفر', 'تحميل', 'نقل', 'تمهيد وتسوية', 'دك وضغط', 'خدمات مساندة', 'توليد طاقة', 'رفع', 'أخرى'];
$fuel_types           = ['ديزل', 'بنزين', 'كهرباء', 'هجين', 'غاز', 'أخرى'];
$capacity_uoms        = ['م³', 'طن', 'لتر', 'كجم', 'حصان (HP)', 'كيلوواط (kW)', 'متر', 'بوصة', 'أخرى'];
$spec_item_types      = ['فلتر زيت', 'فلتر هواء', 'فلتر وقود', 'فلتر هيدروليك', 'زيت محرك', 'زيت هيدروليك', 'زيت ناقل حركة', 'زيت فرامل', 'سير', 'إطار', 'بطارية', 'شمعات احتراق', 'قطعة غيار', 'أخرى'];
$spec_uoms            = ['قطعة', 'لتر', 'كجم', 'متر', 'طقم', 'علبة', 'أخرى'];

// ── مجلد رفع صور سطور المواصفات ──────────────────────────────────────
$upload_dir = __DIR__ . '/../assets/uploads/fleet_models/';
$upload_rel = 'assets/uploads/fleet_models/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0775, true);
}

/**
 * معالجة صورة سطر مواصفة واحد (مصفوفة الملفات spec_photo[]).
 * يعيد المسار النسبي الجديد، أو المسار القائم (عند التعديل بلا رفع جديد)، أو null.
 */
function fleet_handle_line_photo($idx, $existing, $upload_dir, $upload_rel)
{
    if (
        isset($_FILES['spec_photo']) &&
        isset($_FILES['spec_photo']['error'][$idx]) &&
        $_FILES['spec_photo']['error'][$idx] === UPLOAD_ERR_OK
    ) {
        $f = [
            'name'     => $_FILES['spec_photo']['name'][$idx],
            'type'     => $_FILES['spec_photo']['type'][$idx],
            'tmp_name' => $_FILES['spec_photo']['tmp_name'][$idx],
            'error'    => $_FILES['spec_photo']['error'][$idx],
            'size'     => $_FILES['spec_photo']['size'][$idx],
        ];
        if (function_exists('validate_file_upload')) {
            $check = validate_file_upload($f, ['jpg', 'jpeg', 'png', 'webp'], 3 * 1024 * 1024);
            if (!empty($check['valid'])) {
                $name = function_exists('generate_safe_filename')
                    ? generate_safe_filename($f['name'])
                    : (bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)));
                if (@move_uploaded_file($f['tmp_name'], $upload_dir . $name)) {
                    return $upload_rel . $name;
                }
            }
        }
    }
    return ($existing !== '' && $existing !== null) ? $existing : null;
}

$errors  = [];
$flash   = isset($_GET['msg']) ? $_GET['msg'] : '';

// ── حذف ناعم (Soft delete: is_deleted=1 + status=inactive) ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!$perms['can_delete']) {
        ems_gov_flash_redirect('fleet_models.php', '❌ لا توجد صلاحية للحذف', 'GOV-PERM-403', '');
        exit();
    }
    $del_id = (int) $_POST['delete_id'];
    // حذفٌ ناعمٌ معزولٌ بالشركة عبر البوابة (is_deleted=1 + status=inactive معًا كالأصل)
    try {
        $fm_gate->update('fleet_model', array('is_deleted' => 1, 'status' => 'inactive'), array('id' => $del_id));
    } catch (\Throwable $e) { /* غير مملوك/سوبر بلا سياق → لا تغيير */ }
    ems_gov_flash_redirect('fleet_models.php', '🗑️ تم حذف الموديل (تعطيل ناعم)', 'GOV-OK-200', '');
    exit();
}

// ── إضافة / تعديل ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id            = !empty($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $code               = trim($_POST['code'] ?? '');
    $manufacturer       = trim($_POST['manufacturer'] ?? '');
    $model_name         = trim($_POST['model_name'] ?? '');
    $equipment_type_id  = (isset($_POST['equipment_type_id']) && $_POST['equipment_type_id'] !== '') ? (int) $_POST['equipment_type_id'] : null;
    $operating_category = trim($_POST['operating_category'] ?? '');
    $fuel_type          = trim($_POST['fuel_type'] ?? '');
    $std_capacity       = (isset($_POST['std_capacity']) && $_POST['std_capacity'] !== '') ? (float) $_POST['std_capacity'] : null;
    $std_capacity_uom   = trim($_POST['std_capacity_uom'] ?? '');
    $tech_reference     = trim($_POST['tech_reference'] ?? '');
    // المورد الافتراضي: إدخال يدوي حرّ (غير مربوط بجدول الموردين)
    $default_supplier_name = trim($_POST['default_supplier_name'] ?? '');
    $status             = (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'inactive' : 'active';

    if ($code === '')       $errors[] = 'كود الموديل مطلوب';
    if ($model_name === '') $errors[] = 'اسم الموديل مطلوب';

    // تحقّق تفرّد الكود داخل الشركة (البوابة تعزل + تستثني المحذوف)
    if (empty($errors)) {
        $dupWhere  = "code = ?";
        $dupParams = array($code);
        if ($edit_id > 0) { $dupWhere .= " AND id <> ?"; $dupParams[] = $edit_id; }
        $dup = $fm_gate->selectOne('fleet_model', array('columns' => array('id'), 'whereRaw' => $dupWhere, 'params' => $dupParams));
        if ($dup) {
            $errors[] = 'كود الموديل مستخدم مسبقاً في شركتك';
        }
    }

    // التحقّق من ملكية السجل عند التعديل (البوابة تعزل بالشركة)
    if (empty($errors) && $edit_id > 0) {
        $own = $fm_gate->selectOne('fleet_model', array('columns' => array('id'), 'where' => array('id' => $edit_id)));
        if (!$own) {
            $errors[] = 'الموديل غير موجود أو لا يخصّ شركتك';
        }
    }

    if (empty($errors)) {
        try {
            // بيانات الموديل (بلا company_id — تُحقن آليًّا عند الإدراج عبر البوابة)
            $model_data = array(
                'code'                  => $code,
                'manufacturer'          => $manufacturer,
                'model_name'            => $model_name,
                'equipment_type_id'     => $equipment_type_id,
                'operating_category'    => $operating_category,
                'fuel_type'             => $fuel_type,
                'std_capacity'          => $std_capacity,
                'std_capacity_uom'      => $std_capacity_uom,
                'tech_reference'        => $tech_reference,
                'default_supplier_name' => $default_supplier_name,
                'status'                => $status,
            );
            if ($edit_id > 0) {
                $fm_gate->update('fleet_model', $model_data, array('id' => $edit_id));
                $model_id = $edit_id;
            } else {
                $model_data['created_by'] = $user_id;
                $model_id = (int) $fm_gate->insert('fleet_model', $model_data);
            }

            // ── سطور المواصفات: استبدالٌ كامل ذرّيّ (replaceChildren = حذف القديم + إدراج
            //    الجديد في معاملة واحدة، بنطاقٍ مزدوج: الشركة + الموديل المملوك المتحقَّق) ──
            if (!empty($model_id)) {
                $items = isset($_POST['spec_item_type']) && is_array($_POST['spec_item_type']) ? $_POST['spec_item_type'] : [];
                $n = count($items);
                $spec_rows = array();
                for ($i = 0; $i < $n; $i++) {
                    $it    = trim($items[$i] ?? '');
                    $rref  = trim($_POST['spec_recommended_ref'][$i] ?? '');
                    $qtyR  = ($_POST['spec_qty'][$i] ?? '') !== '' ? (float) $_POST['spec_qty'][$i] : null;
                    $uomR  = trim($_POST['spec_uom'][$i] ?? '');
                    $aref  = trim($_POST['spec_alt_ref'][$i] ?? '');
                    $noteR = trim($_POST['spec_note'][$i] ?? '');
                    $exist = trim($_POST['spec_existing_photo'][$i] ?? '');
                    $photo = fleet_handle_line_photo($i, $exist, $upload_dir, $upload_rel);

                    // تجاهل السطر الفارغ تماماً
                    if ($it === '' && $rref === '' && $qtyR === null && $uomR === '' && $aref === '' && $noteR === '' && !$photo) {
                        continue;
                    }
                    // بلا model_id (يضبطه replaceChildren) وبلا company_id (يُحقن بالإدراج)
                    $spec_rows[] = array(
                        'item_type'       => $it,
                        'recommended_ref' => $rref,
                        'qty'             => $qtyR,
                        'uom'             => $uomR,
                        'alt_ref'         => $aref,
                        'photo_path'      => $photo,
                        'note'            => $noteR,
                    );
                }
                $fm_gate->replaceChildren('fleet_model', $model_id, 'fleet_model_service_spec', 'model_id', $spec_rows, 'fleet_models specs');

                // ── ربط ملف الافتراضات المالية (إضافي وآمن) ──
                if (db_table_has_column($conn, 'fleet_model', 'depreciation_profile_id')) {
                    $dep_id = (isset($_POST['depreciation_profile_id']) && $_POST['depreciation_profile_id'] !== '') ? (int) $_POST['depreciation_profile_id'] : null;
                    $fm_gate->update('fleet_model', array('depreciation_profile_id' => $dep_id), array('id' => $model_id));
                }
            }

            ems_gov_flash_redirect('fleet_models.php', $edit_id > 0 ? '✅ تم تحديث الموديل' : '✅ تم إضافة الموديل', 'GOV-OK-200', '');
            exit();
        } catch (\Throwable $e) {
            $errors[] = 'تعذّر الحفظ: ' . $e->getMessage();
        }
    }
}

// ── جلب بيانات التعديل ───────────────────────────────────────────────
$editData  = null;
$editSpecs = [];
if (isset($_GET['edit_id'])) {
    $eid = (int) $_GET['edit_id'];
    // جلبٌ معزولٌ بالشركة (البوابة تستثني المحذوف تلقائيًّا)
    $editData = $fm_gate->selectOne('fleet_model', array('where' => array('id' => $eid)));

    if ($editData) {
        // سطور المواصفات للموديل (ابنٌ مستأجَر — معزولٌ بالشركة)
        $editSpecs = $fm_gate->select('fleet_model_service_spec', array(
            'where'   => array('model_id' => $editData['id']),
            'orderBy' => 'id ASC',
        ));
    }
}

// ── القوائم المنسدلة (أنواع المعدات / الموردون / المصنّعون) ───────────
// أنواع المعدات — كتالوج عام (T_GLOBAL) عبر البوابة
$equipment_types = $fm_gate->select('equipments_types', array(
    'columns'  => array('id', 'type'),
    'whereRaw' => "status = 'active'",
    'orderBy'  => 'type ASC',
));

// المورد الافتراضي صار إدخالاً يدوياً حرّاً (غير مربوط بجدول الموردين) — لا حاجة لجلب الموردين.

// مصنّعون مقترحون (datalist): من الموديلات + المعدات — استدعاءان معزولان ثم دمجٌ في PHP
// (البوابة تمنع UNION؛ العزل مسؤوليتها). fleet_model يستثني المحذوف تلقائيًّا (soft).
$mfr_set = array();
foreach ($fm_gate->select('fleet_model', array('columns' => array('manufacturer'), 'whereRaw' => "manufacturer IS NOT NULL AND manufacturer <> ''")) as $r) {
    $mfr_set[$r['manufacturer']] = true;
}
foreach ($fm_gate->select('equipments', array('columns' => array('manufacturer'), 'whereRaw' => "manufacturer IS NOT NULL AND manufacturer <> ''")) as $r) {
    $mfr_set[$r['manufacturer']] = true;
}
$manufacturers = array_keys($mfr_set);
sort($manufacturers, SORT_STRING);

// ملفات الافتراضات المالية المعتمدة (للربط) — إن كان الجدول/العمود متاحاً
$dep_profiles    = [];
$has_dep_profile = db_table_has_column($conn, 'fleet_model', 'depreciation_profile_id')
    && db_table_has_column($conn, 'fleet_depreciation_profile', 'id');
if ($has_dep_profile) {
    // ملفات إهلاكٍ معتمدة، معزولةً بالشركة (البوابة تستثني المحذوف تلقائيًّا)
    $dep_profiles = $fm_gate->select('fleet_depreciation_profile', array(
        'columns'  => array('id', 'code', 'asset_category'),
        'whereRaw' => "state = 'approved'",
        'orderBy'  => 'code ASC',
    ));
}

$page_title = "إيكوبيشن | الأنواع والموديلات";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include("../insidebar.php");
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// أداة تهريب موحّدة
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>

<div style="background-color:#fff; padding: 10px;;" class="main">

    <?php
    $header_title   = 'الأنواع والموديلات';
    $header_icon    = 'fas fa-clipboard-list';
    $header_actions = array();
    if ($perms['can_add']) {
        $header_actions[] = array('tag' => 'button', 'id' => 'toggleForm', 'class' => 'add', 'icon' => 'fa-solid fa-plus-circle', 'label' => 'إضافة موديل جديد');
    }
    $header_actions[] = array('tag' => 'a', 'href' => 'fleet_depreciation_profiles.php', 'class' => 'btn', 'icon' => 'fas fa-coins', 'label' => 'ملفات الإهلاك المالي');
    // نظام Excel الموحّد
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('fleet_models', 'سجل النوع والموديل', $perms['can_add']) as $__xlAction) { $header_actions[] = $__xlAction; }
    $header_back = array('href' => 'equipments_fleet.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($flash)): ?>
        <div class="success-message is-success" style="margin:10px 0;"><?= $e($flash); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="success-message is-error" style="margin:10px 0;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= $e(implode(' — ', $errors)); ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة / تعديل -->
    <form id="projectForm" method="post" enctype="multipart/form-data"
          class="allforms<?= (!empty($editData) || !empty($errors)) ? ' allforms-visible' : ''; ?>">
         <div class="card-header">
                <h5><i class="fas fa-clipboard-list"></i>
                    <?= !empty($editData) ? 'تعديل الموديل' : 'إضافة موديل جديد'; ?>
                </h5>
            </div>
        <div class="card">
            <div class="card-body">
                <?php if (!empty($editData)): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label>كود الموديل <span style="color:#c0392b">*</span></label>
                        <input type="text" name="code" required
                               value="<?= $e($editData['code'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>الصانع / الماركة</label>
                        <input type="text" name="manufacturer" list="manufacturerList" autocomplete="off"
                               value="<?= $e($editData['manufacturer'] ?? ''); ?>">
                        <datalist id="manufacturerList">
                            <?php foreach ($manufacturers as $m): ?>
                                <option value="<?= $e($m); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div>
                        <label>اسم / رقم الموديل <span style="color:#c0392b">*</span></label>
                        <input type="text" name="model_name" required
                               value="<?= $e($editData['model_name'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>نوع المعدة</label>
                        <select name="equipment_type_id">
                            <option value="">-- اختر النوع --</option>
                            <?php foreach ($equipment_types as $t): ?>
                                <option value="<?= (int) $t['id']; ?>"
                                    <?= (!empty($editData) && (int) $editData['equipment_type_id'] === (int) $t['id']) ? 'selected' : ''; ?>>
                                    <?= $e($t['type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>فئة التشغيل</label>
                        <select name="operating_category">
                            <option value="">-- اختر الفئة --</option>
                            <?php foreach ($operating_categories as $c): ?>
                                <option value="<?= $e($c); ?>"
                                    <?= (!empty($editData) && ($editData['operating_category'] ?? '') === $c) ? 'selected' : ''; ?>>
                                    <?= $e($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>نوع الوقود</label>
                        <select name="fuel_type">
                            <option value="">-- اختر الوقود --</option>
                            <?php foreach ($fuel_types as $f): ?>
                                <option value="<?= $e($f); ?>"
                                    <?= (!empty($editData) && ($editData['fuel_type'] ?? '') === $f) ? 'selected' : ''; ?>>
                                    <?= $e($f); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>السعة / القدرة القياسية</label>
                        <input type="number" step="0.01" name="std_capacity"
                               value="<?= $e($editData['std_capacity'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>وحدة القياس</label>
                        <select name="std_capacity_uom">
                            <option value="">-- اختر الوحدة --</option>
                            <?php foreach ($capacity_uoms as $u): ?>
                                <option value="<?= $e($u); ?>"
                                    <?= (!empty($editData) && ($editData['std_capacity_uom'] ?? '') === $u) ? 'selected' : ''; ?>>
                                    <?= $e($u); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>المورد الافتراضي</label>
                        <input type="text" name="default_supplier_name" autocomplete="off"
                               placeholder="اكتب اسم المورد (إدخال يدوي)"
                               value="<?= $e($editData['default_supplier_name'] ?? ''); ?>">
                    </div>

                    <?php if ($has_dep_profile): ?>
                    <div>
                        <label><i class="fas fa-coins"></i> ملف الافتراضات المالية (معتمد)</label>
                        <select name="depreciation_profile_id">
                            <option value="">-- بدون --</option>
                            <?php foreach ($dep_profiles as $dp): ?>
                                <option value="<?= (int) $dp['id']; ?>"
                                    <?= (!empty($editData) && (int) ($editData['depreciation_profile_id'] ?? 0) === (int) $dp['id']) ? 'selected' : ''; ?>>
                                    <?= $e($dp['code'] . ' — ' . $dp['asset_category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#777">المعدّات التابعة لهذا الموديل ترث افتراضات الإهلاك منه.</small>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label>مرجع فني / كتالوج</label>
                        <input type="text" name="tech_reference"
                               value="<?= $e($editData['tech_reference'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>الحالة</label>
                        <select name="status">
                            <option value="active"   <?= (!empty($editData) && $editData['status'] === 'active') ? 'selected' : ''; ?>>نشط</option>
                            <option value="inactive" <?= (!empty($editData) && $editData['status'] === 'inactive') ? 'selected' : ''; ?>>غير نشط</option>
                        </select>
                    </div>
                </div>

                <!-- ── سطور المواصفات القياسية للصيانة (One2many) ── -->
                <div class="spec-section">
                    <div class="spec-section-head">
                        <h6><i class="fas fa-screwdriver-wrench"></i> المواصفات القياسية للصيانة</h6>
                        <button type="button" class="btn btn-primary btn-sm" id="addSpecRow">
                            <i class="fas fa-plus"></i> إضافة بند
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table spec-table" id="specTable">
                            <thead>
                                <tr>
                                    <th>نوع البند</th>
                                    <th>المرجع الموصى به</th>
                                    <th>الكمية</th>
                                    <th>الوحدة</th>
                                    <th>مرجع بديل</th>
                                    <th>صورة</th>
                                    <th>ملاحظة</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="specRows">
                                <?php
                                $renderSpec = function ($row = null) use ($e, $spec_item_types, $spec_uoms) {
                                    $iv = function ($k) use ($row, $e) { return $e($row[$k] ?? ''); };
                                    $photo = $row['photo_path'] ?? '';
                                    ?>
                                    <tr class="spec-row">
                                        <td>
                                            <input type="text" name="spec_item_type[]" list="specItemTypes"
                                                   value="<?= $iv('item_type'); ?>" placeholder="فلتر / زيت ...">
                                        </td>
                                        <td><input type="text" name="spec_recommended_ref[]" value="<?= $iv('recommended_ref'); ?>"></td>
                                        <td><input type="number" step="0.01" name="spec_qty[]" value="<?= $iv('qty'); ?>" style="max-width:90px"></td>
                                        <td>
                                            <input type="text" name="spec_uom[]" list="specUoms" value="<?= $iv('uom'); ?>" style="max-width:100px">
                                        </td>
                                        <td><input type="text" name="spec_alt_ref[]" value="<?= $iv('alt_ref'); ?>"></td>
                                        <td>
                                            <input type="hidden" name="spec_existing_photo[]" value="<?= $e($photo); ?>">
                                            <?php if (!empty($photo)): ?>
                                                <a href="../<?= $e($photo); ?>" target="_blank" class="spec-thumb-link">
                                                    <img src="../<?= $e($photo); ?>" class="spec-thumb" alt="صورة">
                                                </a>
                                            <?php endif; ?>
                                            <input type="file" name="spec_photo[]" accept="image/*" class="spec-file">
                                        </td>
                                        <td><input type="text" name="spec_note[]" value="<?= $iv('note'); ?>"></td>
                                        <td class="text-center">
                                            <button type="button" class="action-btn delete removeSpecRow" title="حذف البند">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                };
                                if (!empty($editSpecs)) {
                                    foreach ($editSpecs as $sp) { $renderSpec($sp); }
                                } else {
                                    $renderSpec(null); // سطر فارغ افتراضي
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        <span><?= !empty($editData) ? 'تحديث الموديل' : 'حفظ الموديل'; ?></span>
                    </button>
                    <button type="button" id="projectFormCancelBtn" class="btn-cancel">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- datalists مشتركة لسطور المواصفات -->
    <datalist id="specItemTypes">
        <?php foreach ($spec_item_types as $it): ?><option value="<?= $e($it); ?>"></option><?php endforeach; ?>
    </datalist>
    <datalist id="specUoms">
        <?php foreach ($spec_uoms as $u): ?><option value="<?= $e($u); ?>"></option><?php endforeach; ?>
    </datalist>

    <!-- جدول الموديلات -->
    <div class="card models-list-card">
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display fleet-models-table">
                    <thead>
                        <tr>
                            <th>الإجراءات</th>
                            <th>#</th>
                            <th>كود الموديل</th>
                            <th>الصانع</th>
                            <th>الموديل</th>
                            <th>نوع المعدة</th>
                            <th>الفئة التشغيلية</th>
                            <th>عدد الوحدات</th>
                            <th>الحالة</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">الاسم</th>
                            <th class="ems-fn-th" data-fn="1">بلد الصنع</th>
                            <th class="ems-fn-th" data-fn="1">القدرة</th>
                            <th class="ems-fn-th" data-fn="1">سعة الجردل أو الحمولة</th>
                            <th class="ems-fn-th" data-fn="1">استهلاك الوقود التقديري</th>
                            <th class="ems-fn-th" data-fn="1">دورية تغيير الزيت</th>
                            <th class="ems-fn-th" data-fn="1">دورية الفلاتر</th>
                            <th class="ems-fn-th" data-fn="1">العمر الإنتاجي بالساعات</th>
                            <th class="ems-fn-th" data-fn="1">القطع القياسية</th>
                            <th class="ems-fn-th" data-fn="1">عرّفه</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        // القائمة معزولةً عبر البوابة: النطاق fm؛ إثراء dp (T_TENANT، LEFT) +
                        // العدّاد الفرعيّ على equipments (يُعلَن كي لا يُرفض جدولٌ مستأجرٌ غير معلَن؛
                        // et كتالوجٌ عامّ لا يحتاج إعلانًا). is_deleted تُبقى صراحةً كالأصل.
                        $dep_select = $has_dep_profile ? ", dp.code AS dep_code, dp.asset_category AS dep_category" : "";
                        $dep_join   = $has_dep_profile ? " LEFT JOIN fleet_depreciation_profile dp ON dp.id = fm.depreciation_profile_id" : "";
                        $listSql =
                            "SELECT fm.*, et.type AS type_name, fm.default_supplier_name AS supplier_name$dep_select,
                                    (SELECT COUNT(*) FROM equipments eq WHERE eq.model_id = fm.id) AS unit_count
                             FROM fleet_model fm
                             LEFT JOIN equipments_types et ON et.id = fm.equipment_type_id" . $dep_join . "
                             WHERE {TENANT_SCOPE} AND fm.is_deleted = 0
                             ORDER BY fm.id DESC";
                        $list = $fm_gate->scopedQuery(array(
                            'scope'  => array('fm' => 'fleet_model'),
                            'enrich' => array('dp' => 'fleet_depreciation_profile', 'eq' => 'equipments'),
                        ), $listSql, array());

                        // سطور المواصفات لكل الموديلات المعروضة (لنافذة العرض) — النطاق s، إثراء fm
                        $specsByModel = [];
                        $specRes = $fm_gate->scopedQuery(array(
                            'scope'  => array('s' => 'fleet_model_service_spec'),
                            'enrich' => array('fm' => 'fleet_model'),
                        ),
                            "SELECT s.* FROM fleet_model_service_spec s
                             LEFT JOIN fleet_model fm ON fm.id = s.model_id
                             WHERE {TENANT_SCOPE} AND fm.id IS NOT NULL AND fm.is_deleted = 0
                             ORDER BY s.model_id ASC, s.id ASC",
                            array()
                        );
                        foreach ($specRes as $sp) { $specsByModel[(int) $sp['model_id']][] = $sp; }

                        $modelInfo = [];
                        $i = 1;
                        foreach ($list as $row):
                            $modelInfo[(int) $row['id']] = [
                                'code'               => $row['code'],
                                'manufacturer'       => $row['manufacturer'],
                                'model_name'         => $row['model_name'],
                                'type_name'          => $row['type_name'],
                                'operating_category' => $row['operating_category'],
                                'fuel_type'          => $row['fuel_type'],
                                'std_capacity'       => $row['std_capacity'],
                                'std_capacity_uom'   => $row['std_capacity_uom'],
                                'tech_reference'     => $row['tech_reference'],
                                'supplier_name'      => $row['supplier_name'],
                                'unit_count'         => (int) $row['unit_count'],
                                'status'             => $row['status'],
                                'dep_profile'        => $has_dep_profile && !empty($row['dep_code'])
                                    ? ($row['dep_code'] . ' — ' . $row['dep_category']) : '',
                            ];
                            ?>
                            <tr>
                                <td class="text-center">
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewSpecBtn" data-id="<?= (int) $row['id']; ?>" title="عرض المواصفات">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if ($perms['can_edit']): ?>
                                            <a href="fleet_models.php?edit_id=<?= (int) $row['id']; ?>" class="action-btn edit" title="تعديل">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($perms['can_delete']): ?>
                                            <form method="post" class="d-inline delete-model-form" onsubmit="return confirm('هل تريد حذف هذا الموديل؟ (تعطيل ناعم)');">
                                                <input type="hidden" name="delete_id" value="<?= (int) $row['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="حذف">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= $i++; ?></td>
                                <td><?= $e($row['code']); ?></td>
                                <td><?= $e($row['manufacturer']); ?></td>
                                <td><?= $e($row['model_name']); ?></td>
                                <td><?= $e($row['type_name'] ?? '—'); ?></td>
                                <td><?= $e($row['operating_category'] ?: '—'); ?></td>
                                <td class="text-center"><?= (int) $row['unit_count']; ?></td>
                                <td>
                                    <?= $row['status'] === 'active'
                                        ? "<span class='status-active'>نشط</span>"
                                        : "<span class='status-inactive'>غير نشط</span>"; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    /* مساحة خارجية حول منطقة المحتوى الرئيسية + خلفية بيضاء للصفحة */
    .fleet-models-main { margin: 10px; background: #fff; }

    /* خلفية بطاقة الجدول رمادي فاتح (نفس درجة --light-gray في صفحة المشاريع).
       محدّد عالي الأولوية لتجاوز القاعدة العامة body.ems-site .main .card { background:#fff !important } */
    body.ems-site .main.fleet-models-main .models-list-card { background: #F2F3F5 !important; }

    /* عمود الإجراءات: الأزرار الثلاثة في صفّ واحد بجانب بعضها */
    .fleet-models-table th:first-child,
    .fleet-models-table td:first-child { white-space: nowrap; }
    .fleet-models-table .action-btns {
        display: flex;
        flex-wrap: nowrap;
        gap: 6px;
        align-items: center;
        justify-content: center;
    }
    .fleet-models-table .action-btns .delete-model-form { display: inline-flex; margin: 0; }

    .spec-section { margin-top: 18px; border-top: 1px dashed #d8d8d8; padding-top: 14px; }
    .spec-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .spec-section-head h6 { margin: 0; font-weight: 700; }
    .spec-table th, .spec-table td { vertical-align: middle; padding: 6px; }
    .spec-table input[type="text"], .spec-table input[type="number"] { width: 100%; }
    .spec-thumb { width: 38px; height: 38px; object-fit: cover; border-radius: 6px; display: block; margin-bottom: 4px; }
    .spec-file { font-size: 11px; max-width: 130px; }
    .delete-model-form { margin: 0; }

    /* نافذة عرض المواصفات */
    .fm-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 20000; display: none; align-items: center; justify-content: center; padding: 20px; }
    .fm-modal { background: #fff; border-radius: 12px; max-width: 920px; width: 100%; max-height: 88vh; overflow: auto; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
    .fm-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #eee; position: sticky; top: 0; background: #fff; z-index: 1; }
    .fm-modal-head h5 { margin: 0; font-weight: 800; }
    .fm-modal-close { background: none; border: none; font-size: 26px; line-height: 1; cursor: pointer; color: #888; }
    .fm-modal-body { padding: 16px 18px; }
    .fm-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; margin-bottom: 16px; }
    .fm-chip { background: #f7f7f9; border: 1px solid #ececf0; border-radius: 8px; padding: 8px 10px; display: flex; flex-direction: column; gap: 2px; }
    .fm-chip span { font-size: 11px; color: #888; }
    .fm-chip b { font-size: 13px; color: #222; }
    .fm-spec-h { font-weight: 800; margin: 6px 0 10px; }
    .fm-empty { color: #888; padding: 16px; text-align: center; background: #fafafa; border-radius: 8px; }
</style>

<!-- نافذة عرض المواصفات القياسية -->
<div id="specViewModal" class="fm-modal-overlay">
    <div class="fm-modal">
        <div class="fm-modal-head">
            <h5 id="specViewTitle"><i class="fas fa-clipboard-list"></i> تفاصيل الموديل</h5>
            <button type="button" class="fm-modal-close" id="specViewClose">&times;</button>
        </div>
        <div class="fm-modal-body" id="specViewBody"></div>
    </div>
</div>

<script>
    var FM_MODELS = <?= json_encode($modelInfo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var FM_SPECS  = <?= json_encode($specsByModel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<!-- قالب سطر مواصفة جديد (لإضافته عبر JS) -->
<template id="specRowTemplate">
    <tr class="spec-row">
        <td><input type="text" name="spec_item_type[]" list="specItemTypes" placeholder="فلتر / زيت ..."></td>
        <td><input type="text" name="spec_recommended_ref[]"></td>
        <td><input type="number" step="0.01" name="spec_qty[]" style="max-width:90px"></td>
        <td><input type="text" name="spec_uom[]" list="specUoms" style="max-width:100px"></td>
        <td><input type="text" name="spec_alt_ref[]"></td>
        <td>
            <input type="hidden" name="spec_existing_photo[]" value="">
            <input type="file" name="spec_photo[]" accept="image/*" class="spec-file">
        </td>
        <td><input type="text" name="spec_note[]"></td>
        <td class="text-center">
            <button type="button" class="action-btn delete removeSpecRow" title="حذف البند">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<!-- JS -->
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script>
    $(document).ready(function () {
        $('#projectsTable').DataTable({
            order: [],                                  // إبقاء ترتيب الاستعلام (الأحدث أولاً)
            columnDefs: [{ orderable: false, targets: 0 }], // عمود الإجراءات (الأول) غير قابل للفرز
            language: { url: "/ems/assets/i18n/datatables/ar.json" }
        });

        $('#toggleForm').on('click', function () {
            const $form = $('#projectForm');
            if ($form.hasClass('allforms-visible')) {
                $form.removeClass('allforms-visible').slideUp(200);
            } else {
                $form.addClass('allforms-visible').hide().slideDown(250);
            }
        });

        // زر الإلغاء: إخفاء فورم الإضافة/التعديل (مطابق لسلوك شاشة المشاريع)
        $('#projectFormCancelBtn').on('click', function () {
            $('#projectForm').removeClass('allforms-visible').slideUp(200);
        });

        // إضافة سطر مواصفة
        $('#addSpecRow').on('click', function () {
            const tpl = document.getElementById('specRowTemplate');
            const clone = document.importNode(tpl.content, true);
            document.getElementById('specRows').appendChild(clone);
        });

        // حذف سطر مواصفة (يبقي سطراً واحداً على الأقل)
        $('#specRows').on('click', '.removeSpecRow', function () {
            const $rows = $('#specRows .spec-row');
            if ($rows.length > 1) {
                $(this).closest('.spec-row').remove();
            } else {
                $(this).closest('.spec-row').find('input').val('');
            }
        });

        // ── نافذة عرض المواصفات القياسية ──
        function fmEsc(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function fmNum(v) {
            if (v === null || v === undefined || v === '') return '';
            var n = parseFloat(v);
            if (isNaN(n)) return fmEsc(v);
            return (n % 1 === 0) ? String(n) : String(n);
        }
        function openSpecModal(id) {
            var info = FM_MODELS[id] || {};
            var specs = FM_SPECS[id] || [];
            var title = fmEsc(info.code || '') + (info.model_name ? ' — ' + fmEsc(info.model_name) : '');
            document.getElementById('specViewTitle').innerHTML =
                '<i class="fas fa-clipboard-list"></i> ' + (title || 'تفاصيل الموديل');

            function chip(label, val) {
                if (val === null || val === undefined || val === '') return '';
                return '<div class="fm-chip"><span>' + label + '</span><b>' + fmEsc(val) + '</b></div>';
            }
            var html = '<div class="fm-info-grid">';
            html += chip('الصانع', info.manufacturer);
            html += chip('النوع', info.type_name);
            html += chip('فئة التشغيل', info.operating_category);
            html += chip('الوقود', info.fuel_type);
            html += chip('السعة القياسية', info.std_capacity ? (fmNum(info.std_capacity) + ' ' + (info.std_capacity_uom || '')) : '');
            html += chip('المورد الافتراضي', info.supplier_name);
            html += chip('ملف الإهلاك المالي', info.dep_profile);
            html += chip('مرجع فني', info.tech_reference);
            html += chip('عدد الوحدات', info.unit_count);
            html += chip('الحالة', info.status === 'active' ? 'نشط' : (info.status === 'inactive' ? 'غير نشط' : info.status));
            html += '</div>';

            html += '<h6 class="fm-spec-h"><i class="fas fa-screwdriver-wrench"></i> المواصفات القياسية للصيانة</h6>';
            if (!specs.length) {
                html += '<div class="fm-empty">لا توجد مواصفات مسجّلة لهذا الموديل.</div>';
            } else {
                html += '<div class="table-container"><table class="table spec-table"><thead><tr>' +
                    '<th>نوع البند</th><th>المرجع الموصى به</th><th>الكمية</th><th>الوحدة</th>' +
                    '<th>مرجع بديل</th><th>صورة</th><th>ملاحظة</th></tr></thead><tbody>';
                specs.forEach(function (s) {
                    var img = s.photo_path
                        ? '<a href="../' + fmEsc(s.photo_path) + '" target="_blank"><img src="../' + fmEsc(s.photo_path) + '" class="spec-thumb" alt="صورة"></a>'
                        : '—';
                    html += '<tr>' +
                        '<td>' + fmEsc(s.item_type) + '</td>' +
                        '<td>' + fmEsc(s.recommended_ref) + '</td>' +
                        '<td>' + fmNum(s.qty) + '</td>' +
                        '<td>' + fmEsc(s.uom) + '</td>' +
                        '<td>' + fmEsc(s.alt_ref) + '</td>' +
                        '<td>' + img + '</td>' +
                        '<td>' + fmEsc(s.note) + '</td>' +
                        '</tr>';
                });
                html += '</tbody></table></div>';
            }
            document.getElementById('specViewBody').innerHTML = html;
            document.getElementById('specViewModal').style.display = 'flex';
        }

        $('#projectsTable').on('click', '.viewSpecBtn', function () {
            openSpecModal($(this).data('id'));
        });
        $('#specViewClose').on('click', function () {
            document.getElementById('specViewModal').style.display = 'none';
        });
        document.getElementById('specViewModal').addEventListener('click', function (e) {
            if (e.target === this) { this.style.display = 'none'; }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { document.getElementById('specViewModal').style.display = 'none'; }
        });
    });
</script>
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>

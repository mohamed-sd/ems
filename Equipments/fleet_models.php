<?php
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
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا توجد صلاحية لعرض هذه الصفحة'));
    exit();
}

// ── عزل الشركة (نفس نمط equipments.php) ─────────────────────────────
$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=" . urlencode('لا توجد بيئة شركة صالحة للمستخدم ❌'));
    exit();
}

// قيمة الشركة المخزّنة (NULL للسوبر أدمن)
$company_val   = $is_super_admin ? null : $company_id;
// قيد نطاق الشركة للاستعلامات النصّية (company_id دائماً intval)
$company_scope = $is_super_admin ? '' : " AND company_id = $company_id";

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
        header('Location: fleet_models.php?msg=' . urlencode('❌ لا توجد صلاحية للحذف'));
        exit();
    }
    $del_id = (int) $_POST['delete_id'];
    $sql = "UPDATE fleet_model SET is_deleted = 1, status = 'inactive' WHERE id = ?" . $company_scope;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    header('Location: fleet_models.php?msg=' . urlencode('🗑️ تم حذف الموديل (تعطيل ناعم)'));
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
    $default_supplier_id = (isset($_POST['default_supplier_id']) && $_POST['default_supplier_id'] !== '') ? (int) $_POST['default_supplier_id'] : null;
    $status             = (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'inactive' : 'active';

    if ($code === '')       $errors[] = 'كود الموديل مطلوب';
    if ($model_name === '') $errors[] = 'اسم الموديل مطلوب';

    // تحقّق تفرّد الكود داخل الشركة
    if (empty($errors)) {
        $dupSql = "SELECT id FROM fleet_model WHERE code = ? AND is_deleted = 0" . $company_scope;
        if ($edit_id > 0) $dupSql .= " AND id <> " . $edit_id;
        $dupSt = $conn->prepare($dupSql);
        $dupSt->bind_param("s", $code);
        $dupSt->execute();
        if ($dupSt->get_result()->fetch_assoc()) {
            $errors[] = 'كود الموديل مستخدم مسبقاً في شركتك';
        }
    }

    // التحقّق من ملكية السجل عند التعديل
    if (empty($errors) && $edit_id > 0) {
        $own = $conn->prepare("SELECT id FROM fleet_model WHERE id = ? AND is_deleted = 0" . $company_scope);
        $own->bind_param("i", $edit_id);
        $own->execute();
        if (!$own->get_result()->fetch_assoc()) {
            $errors[] = 'الموديل غير موجود أو لا يخصّ شركتك';
        }
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            $sql = "UPDATE fleet_model SET code=?, manufacturer=?, model_name=?, equipment_type_id=?, operating_category=?, fuel_type=?, std_capacity=?, std_capacity_uom=?, tech_reference=?, default_supplier_id=?, status=? WHERE id=? AND is_deleted=0" . $company_scope;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssissdssisi",
                $code, $manufacturer, $model_name, $equipment_type_id, $operating_category,
                $fuel_type, $std_capacity, $std_capacity_uom, $tech_reference, $default_supplier_id,
                $status, $edit_id
            );
            $stmt->execute();
            $model_id = $edit_id;
        } else {
            $sql = "INSERT INTO fleet_model (company_id, code, manufacturer, model_name, equipment_type_id, operating_category, fuel_type, std_capacity, std_capacity_uom, tech_reference, default_supplier_id, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isssissdssisi",
                $company_val, $code, $manufacturer, $model_name, $equipment_type_id, $operating_category,
                $fuel_type, $std_capacity, $std_capacity_uom, $tech_reference, $default_supplier_id,
                $status, $user_id
            );
            $stmt->execute();
            $model_id = $stmt->insert_id;
        }

        // ── سطور المواصفات: حذف القديم ثم إعادة الإدراج ──
        if (!empty($model_id)) {
            $delSpec = $conn->prepare("DELETE FROM fleet_model_service_spec WHERE model_id = ?");
            $delSpec->bind_param("i", $model_id);
            $delSpec->execute();

            $items = isset($_POST['spec_item_type']) && is_array($_POST['spec_item_type']) ? $_POST['spec_item_type'] : [];
            $n = count($items);
            if ($n > 0) {
                $insSpec = $conn->prepare("INSERT INTO fleet_model_service_spec (model_id, company_id, item_type, recommended_ref, qty, uom, alt_ref, photo_path, note) VALUES (?,?,?,?,?,?,?,?,?)");
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
                    $insSpec->bind_param("iissdssss", $model_id, $company_val, $it, $rref, $qtyR, $uomR, $aref, $photo, $noteR);
                    $insSpec->execute();
                }
            }

            // ── ربط ملف الافتراضات المالية (إضافي وآمن) ──
            if (db_table_has_column($conn, 'fleet_model', 'depreciation_profile_id')) {
                $dep_id = (isset($_POST['depreciation_profile_id']) && $_POST['depreciation_profile_id'] !== '') ? (int) $_POST['depreciation_profile_id'] : null;
                $du = $conn->prepare("UPDATE fleet_model SET depreciation_profile_id = ? WHERE id = ?" . ($is_super_admin ? '' : " AND company_id = $company_id"));
                $du->bind_param("ii", $dep_id, $model_id);
                $du->execute();
            }
        }

        header('Location: fleet_models.php?msg=' . urlencode($edit_id > 0 ? '✅ تم تحديث الموديل' : '✅ تم إضافة الموديل'));
        exit();
    }
}

// ── جلب بيانات التعديل ───────────────────────────────────────────────
$editData  = null;
$editSpecs = [];
if (isset($_GET['edit_id'])) {
    $eid = (int) $_GET['edit_id'];
    $st = $conn->prepare("SELECT * FROM fleet_model WHERE id = ? AND is_deleted = 0" . $company_scope);
    $st->bind_param("i", $eid);
    $st->execute();
    $editData = $st->get_result()->fetch_assoc();

    if ($editData) {
        $ss = $conn->prepare("SELECT * FROM fleet_model_service_spec WHERE model_id = ? ORDER BY id ASC");
        $ss->bind_param("i", $editData['id']);
        $ss->execute();
        $rs = $ss->get_result();
        while ($r = $rs->fetch_assoc()) { $editSpecs[] = $r; }
    }
}

// ── القوائم المنسدلة (أنواع المعدات / الموردون / المصنّعون) ───────────
$equipment_types = [];
$rt = $conn->query("SELECT id, type FROM equipments_types WHERE status = 'active' ORDER BY type ASC");
if ($rt) while ($r = $rt->fetch_assoc()) { $equipment_types[] = $r; }

$suppliers = [];
$supplier_scope = $is_super_admin ? '' : " AND company_id = $company_id";
$rsup = $conn->query("SELECT id, name FROM suppliers WHERE status = 1$supplier_scope ORDER BY name ASC");
if ($rsup) while ($r = $rsup->fetch_assoc()) { $suppliers[] = $r; }

// مصنّعون مقترحون (datalist): من الموديلات + المعدات
$manufacturers = [];
$rm = $conn->query(
    "SELECT DISTINCT manufacturer FROM fleet_model WHERE manufacturer IS NOT NULL AND manufacturer <> '' AND is_deleted = 0" . $company_scope .
    " UNION SELECT DISTINCT manufacturer FROM equipments WHERE manufacturer IS NOT NULL AND manufacturer <> ''" . $company_scope .
    " ORDER BY manufacturer ASC"
);
if ($rm) while ($r = $rm->fetch_assoc()) { $manufacturers[] = $r['manufacturer']; }

// ملفات الافتراضات المالية المعتمدة (للربط) — إن كان الجدول/العمود متاحاً
$dep_profiles    = [];
$has_dep_profile = db_table_has_column($conn, 'fleet_model', 'depreciation_profile_id')
    && db_table_has_column($conn, 'fleet_depreciation_profile', 'id');
if ($has_dep_profile) {
    $rdp = @mysqli_query($conn, "SELECT id, code, asset_category FROM fleet_depreciation_profile WHERE is_deleted = 0 AND state = 'approved'" . $company_scope . " ORDER BY code ASC");
    if ($rdp) while ($r = $rdp->fetch_assoc()) { $dep_profiles[] = $r; }
}

$page_title = "إيكوبيشن | سجل النوع والموديل";
include("../inheader.php");
include("../insidebar.php");

// أداة تهريب موحّدة
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>

<div class="main fleet-models-main">

    <?php
    $header_title   = 'سجل النوع والموديل';
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

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-clipboard-list"></i>
                    <?= !empty($editData) ? 'تعديل الموديل' : 'إضافة موديل جديد'; ?>
                </h5>
            </div>

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
                        <select name="default_supplier_id">
                            <option value="">-- اختر المورد --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int) $s['id']; ?>"
                                    <?= (!empty($editData) && (int) $editData['default_supplier_id'] === (int) $s['id']) ? 'selected' : ''; ?>>
                                    <?= $e($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

                <div style="margin-top:14px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-save"></i> حفظ
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
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> قائمة الموديلات</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display fleet-models-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الكود</th>
                            <th>الصانع</th>
                            <th>الموديل</th>
                            <th>النوع</th>
                            <th>الفئة</th>
                            <th>عدد الوحدات</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $dep_select = $has_dep_profile ? ", dp.code AS dep_code, dp.asset_category AS dep_category" : "";
                        $dep_join   = $has_dep_profile ? " LEFT JOIN fleet_depreciation_profile dp ON dp.id = fm.depreciation_profile_id" : "";
                        $listSql =
                            "SELECT fm.*, et.type AS type_name, su.name AS supplier_name$dep_select,
                                    (SELECT COUNT(*) FROM equipments eq WHERE eq.model_id = fm.id) AS unit_count
                             FROM fleet_model fm
                             LEFT JOIN equipments_types et ON et.id = fm.equipment_type_id
                             LEFT JOIN suppliers su ON su.id = fm.default_supplier_id" . $dep_join . "
                             WHERE fm.is_deleted = 0" . ($is_super_admin ? '' : " AND fm.company_id = $company_id") . "
                             ORDER BY fm.id DESC";
                        $list = $conn->query($listSql);

                        // سطور المواصفات لكل الموديلات المعروضة (لنافذة العرض)
                        $specsByModel = [];
                        $specRes = $conn->query(
                            "SELECT s.* FROM fleet_model_service_spec s
                             JOIN fleet_model fm ON fm.id = s.model_id
                             WHERE fm.is_deleted = 0" . ($is_super_admin ? '' : " AND fm.company_id = $company_id") . "
                             ORDER BY s.model_id ASC, s.id ASC"
                        );
                        if ($specRes) while ($sp = $specRes->fetch_assoc()) { $specsByModel[(int) $sp['model_id']][] = $sp; }

                        $modelInfo = [];
                        $i = 1;
                        if ($list) while ($row = $list->fetch_assoc()):
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
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
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
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script>
    $(document).ready(function () {
        $('#projectsTable').DataTable({
            responsive: true,
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

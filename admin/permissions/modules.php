<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة الصفحات والمديولات';
$current_page = 'permissions';

include '../config.php';

function moduleColumnExists($conn, $column_name) {
    $safe_column_name = mysqli_real_escape_string($conn, $column_name);
    $result = $conn->query("SHOW COLUMNS FROM `modules` LIKE '" . $safe_column_name . "'");
    return $result && $result->num_rows > 0;
}

$module_has_icon_column = moduleColumnExists($conn, 'icon');
$default_module_icon = 'fa fa-link';
$common_sidebar_icons = array(
    array('class' => 'fa fa-link', 'label' => 'رابط عام'),
    array('class' => 'fa fa-home', 'label' => 'الرئيسية'),
    array('class' => 'fa fa-users', 'label' => 'العملاء'),
    array('class' => 'fa fa-user-shield', 'label' => 'الصلاحيات'),
    array('class' => 'fa fa-users-cog', 'label' => 'المستخدمون'),
    array('class' => 'fa fa-folder-open', 'label' => 'المشاريع'),
    array('class' => 'fa fa-list-alt', 'label' => 'القوائم'),
    array('class' => 'fa fa-mountain', 'label' => 'المناجم'),
    array('class' => 'fa fa-truck-loading', 'label' => 'الموردون'),
    array('class' => 'fa fa-tractor', 'label' => 'المعدات'),
    array('class' => 'fa fa-truck-moving', 'label' => 'الأسطول'),
    array('class' => 'fa fa-id-card', 'label' => 'المشغلون'),
    array('class' => 'fa fa-cogs', 'label' => 'التشغيل'),
    array('class' => 'fa fa-business-time', 'label' => 'ساعات العمل'),
    array('class' => 'fa fa-calendar-days', 'label' => 'ساعات اليوم'),
    array('class' => 'fa fa-file-contract', 'label' => 'العقود'),
    array('class' => 'fa fa-check-double', 'label' => 'الموافقات'),
    array('class' => 'fa fa-chart-pie', 'label' => 'التقارير'),
    array('class' => 'fa fa-chart-line', 'label' => 'تحليل'),
    array('class' => 'fa fa-chart-column', 'label' => 'إحصائيات'),
    array('class' => 'fa fa-gear', 'label' => 'الإعدادات'),
    array('class' => 'fa fa-key', 'label' => 'الأمان'),
    array('class' => 'fa fa-clipboard-list', 'label' => 'نماذج'),
    array('class' => 'fa fa-screwdriver-wrench', 'label' => 'الأنواع'),
    array('class' => 'fa fa-hard-hat', 'label' => 'الموقع'),
    array('class' => 'fa fa-layer-group', 'label' => 'مديولات'),
    array('class' => 'fa fa-database', 'label' => 'بيانات'),
    array('class' => 'fa fa-bell', 'label' => 'تنبيهات')
);

// جلب role_id من URL إن وجد (للانتقال من صفحة الأدوار)
$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $select_columns = "`id`, `name`, `code`, `owner_role_id`, `is_link`";
    if ($module_has_icon_column) {
        $select_columns .= ", `icon`";
    }
    $stmt = $conn->prepare("SELECT " . $select_columns . " FROM `modules` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    if ($editData && !isset($editData['icon'])) {
        $editData['icon'] = $default_module_icon;
    }
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $owner_role_id = !empty($_POST['owner_role_id']) ? (int)$_POST['owner_role_id'] : null;
    $is_link = isset($_POST['is_link']) && $_POST['is_link'] == '1' ? 1 : 0;
    $icon = trim($_POST['icon'] ?? $default_module_icon);
    $icon = preg_replace('/[^a-zA-Z0-9\-\s]/', '', $icon);
    $icon = trim(preg_replace('/\s+/', ' ', $icon));
    if ($icon === '') {
        $icon = $default_module_icon;
    }

    // التحقق من صحة البيانات
    if (empty($name) || empty($code)) {
        $error_msg = 'اسم الصفحة والكود مطلوبان ❌';
    } else {
        if (!empty($_POST['edit_id'])) {
            // تعديل
            $id = (int) $_POST['edit_id'];
            if ($module_has_icon_column) {
                $stmt = $conn->prepare(
                    "UPDATE `modules` SET `name` = ?, `code` = ?, `owner_role_id` = ?, `is_link` = ?, `icon` = ? WHERE `id` = ?"
                );
                $stmt->bind_param("ssiisi", $name, $code, $owner_role_id, $is_link, $icon, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE `modules` SET `name` = ?, `code` = ?, `owner_role_id` = ?, `is_link` = ? WHERE `id` = ?"
                );
                $stmt->bind_param("ssiii", $name, $code, $owner_role_id, $is_link, $id);
            }
        } else {
            // التحقق من عدم تكرار نفس الصفحة لنفس الدور
            $check_stmt = $conn->prepare(
                "SELECT id FROM `modules` WHERE `code` = ? AND `owner_role_id` <=> ? LIMIT 1"
            );
            $check_stmt->bind_param("si", $code, $owner_role_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                $error_msg = 'هذه الصفحة مضافة مسبقاً لنفس الدور المسؤول ❌';
            } else {
                // إضافة
                if ($module_has_icon_column) {
                    $stmt = $conn->prepare(
                        "INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`) VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssiis", $name, $code, $owner_role_id, $is_link, $icon);
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssii", $name, $code, $owner_role_id, $is_link);
                }
            }
        }

        if (!isset($error_msg) && $stmt->execute()) {
            header("Location: modules.php?msg=تم+البحفاظ+على+البيانات+بنجاح+✔");
            exit;
        } elseif (!isset($error_msg)) {
            $error_msg = 'حدث خطأ: ' . htmlspecialchars($stmt->error) . ' ❌';
        }
    }
}

/* حذف */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM `modules` WHERE `id` = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: modules.php?msg=تم+حذف+الصفحة+بنجاح+✔");
    } else {
        header("Location: modules.php?msg=حدث+خطأ+في+الحذف+❌");
    }
    exit;
}

// جلب جميع الأدوار
$stmt = $conn->prepare("SELECT `id`, `name` FROM `roles` ORDER BY `level`, `name`");
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/layout_head.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<style>
.page-shell {
    background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
    min-height: calc(100vh - 100px);
    padding: 2rem;
}

.page-header h2 {
    color: var(--navy);
    font-weight: 700;
    margin-bottom: 2rem;
}

.card {
    background: white;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
    margin-bottom: 2rem;
}

.card-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
}

.card-body {
    padding: 2rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-grid label {
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 0.5rem;
    display: block;
}

.form-grid input,
.form-grid select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
}

.success-message {
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    border-right: 4px solid;
}

.success-message.is-success {
    background: linear-gradient(135deg, #d1f3d1 0%, #c8f0c8 100%);
    color: #059669;
    border-right-color: #059669;
}

.success-message.is-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
    border-right-color: #ef4444;
}

.table-container {
    overflow-x: auto;
}

.display thead th {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
    color: white;
    font-weight: 600;
    border: none;
}

.display tbody tr:hover {
    background-color: #f8f9fa;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.back-btn {
    background: #e5e7eb;
    color: var(--navy);
}

.back-btn:hover {
    background: #d1d5db;
}

.add-btn {
    background: linear-gradient(135deg, var(--blue) 0%, #1d4ed8 100%);
    color: white;
}

.add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-primary {
    background: var(--blue);
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

.text-center {
    text-align: center;
}

#moduleForm {
    display: none;
    margin-bottom: 2rem;
}

#moduleForm.show {
    display: block;
}
</style>

<div class="page-shell">
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">
            <i class="fas fa-layer-group"></i> إدارة الصفحات والمديولات
        </h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة صفحة جديدة
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✔') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة / تعديل -->
    <form id="moduleForm" action="" method="post">
        <div class="card">
            <div class="card-header">
                <h5 style="margin: 0;">
                    <i class="fas fa-edit"></i> 
                    <?= !empty($editData) ? 'تعديل الصفحة' : 'إضافة صفحة جديدة'; ?>
                </h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="<?= htmlspecialchars($editData['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-grid">
                    <!-- اسم الصفحة -->
                    <div>
                        <label><i class="fas fa-book"></i> اسم الصفحة *</label>
                        <input type="text" name="name" id="name" placeholder="مثال: إدارة العملاء" 
                               value="<?= htmlspecialchars($editData['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>

                    <!-- كود الصفحة -->
                    <div>
                        <label><i class="fas fa-code"></i> كود الصفحة *</label>
                        <input type="text" name="code" id="code" placeholder="مثال: clients" 
                               value="<?= htmlspecialchars($editData['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>

                    <!-- الدور المسؤول -->
                    <div>
                        <label><i class="fas fa-user-tie"></i> الدور المسؤول *</label>
                        <select name="owner_role_id" id="owner_role_id" required>
                            <option value="">-- اختر الدور المسؤول --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id']; ?>" 
                                    <?= ($selected_role_id && $selected_role_id == $role['id'] && !$editData) ? 'selected' : ''; ?>
                                    <?= (!empty($editData) && $editData['owner_role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- رابط -->
                    <div style="display: flex; align-items: center; padding-top: 1.5rem;">
                        <input type="checkbox" name="is_link" id="is_link" value="1" 
                               <?= (!empty($editData) && $editData['is_link'] == 1) ? 'checked' : ''; ?> />
                        <label for="is_link" style="margin: 0; margin-right: 8px; cursor: pointer;">
                            <i class="fas fa-link"></i> رابط
                        </label>
                    </div>

                    <button type="submit" class="add-btn" style="grid-column: 1 / -1; justify-self: center;">
                        <i class="fas fa-save"></i> حفظ الصفحة
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الصفحات -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <h5 style="margin:0;"><i class="fas fa-list"></i> جميع الصفحات والمديولات</h5>
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="font-weight:700; margin:0;"><i class="fas fa-user-tie"></i> فلترة حسب الدور:</label>
                <select id="roleFilterSelect" style="padding:7px 14px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Cairo',sans-serif; font-size:.88rem; min-width:180px;">
                    <option value="">-- جميع الأدوار --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="clearRoleFilter" class="back-btn" style="display:none;" title="مسح الفلتر">
                    <i class="fas fa-times"></i> مسح
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="modulesTable" class="display">
                    <thead>
                        <tr>
                            <th width="80"><i class="fas fa-barcode"></i> #</th>
                            <th><i class="fas fa-book"></i> اسم الصفحة</th>
                            <th width="150"><i class="fas fa-code"></i> الكود</th>
                            <th><i class="fas fa-user-tie"></i> الدور المسؤول</th>
                            <th width="80"><i class="fas fa-link"></i> رابط</th>
                            <th width="150"><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("
                            SELECT 
                                m.`id`, 
                                m.`name`, 
                                m.`code`, 
                                m.`owner_role_id`,
                                m.`is_link`,
                                r.`name` AS role_name
                            FROM `modules` m
                            LEFT JOIN `roles` r ON m.`owner_role_id` = r.`id`
                            ORDER BY m.`name`
                        ");
                        
                        if ($result) {
                            $i = 1;
                            while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td><strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td>
                                        <code style="background: var(--gold-soft); color: var(--navy); padding: 4px 8px; border-radius: 6px; font-weight: 600;">
                                            <?= htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?>
                                        </code>
                                    </td>
                                    <td data-search="<?= htmlspecialchars($row['role_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <a href="role_permissions.php?role_id=<?= $row['owner_role_id']; ?>" 
                                           style="color: var(--blue); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                           title="عرض صلاحيات هذا الدور">
                                            <i class="fas fa-user-shield"></i> 
                                            <?= htmlspecialchars($row['role_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['is_link'] == 1): ?>
                                            <span style="display: inline-block; background: var(--teal-soft); color: var(--teal); padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✔ نعم
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-block; background: #f0f0f0; color: #999; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✖ لا
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?edit_id=<?= $row['id']; ?>&action=edit" class="btn btn-sm btn-primary" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete_id=<?= $row['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذه الصفحة؟');" class="btn btn-sm btn-danger" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                            endwhile;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../../includes/js/jquery-3.7.1.main.js"></script>
<script src="../../includes/js/jquery.dataTables.main.js"></script>
<script>
$(document).ready(function () {
    // تهيئة DataTable
    var modulesTable = $('#modulesTable').DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        },
        columnDefs: [
            { "orderable": false, "targets": [5] }
        ]
    });

    // فلترة حسب الدور المسؤول
    $('#roleFilterSelect').on('change', function () {
        var val = $.trim($(this).val());
        modulesTable.column(3).search(val, false, false).draw();
        $('#clearRoleFilter').toggle(val !== '');
    });

    $('#clearRoleFilter').on('click', function () {
        $('#roleFilterSelect').val('');
        modulesTable.column(3).search('', false, false).draw();
        $(this).hide();
    });

    // إظهار/إخفاء النموذج
    $('#toggleForm').on('click', function () {
        $('#moduleForm').slideToggle(300);
        $('html, body').animate({
            scrollTop: $('#moduleForm').offset().top - 100
        }, 500);
    });

    // إذا كان هناك edit_id في URL، أعرض النموذج
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('edit_id')) {
        $('#moduleForm').addClass('show');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة الأدوار والصلاحيات';
$current_page = 'permissions';

include '../config.php';

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT `id`, `name`, `parent_role_id`, `level`, `status`, `created_at` FROM `roles` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_role_id = !empty($_POST['parent_role_id']) && $_POST['parent_role_id'] !== '' ? (int) $_POST['parent_role_id'] : null;
    $level = (int) ($_POST['level'] ?? 1);
    $status = (int) ($_POST['status'] ?? 1);

    // التحقق من صحة البيانات
    if (empty($name)) {
        $error_msg = 'اسم الصلاحية مطلوب ❌';
    } else {
        if (!empty($_POST['edit_id'])) {
            // تعديل
            $id = (int) $_POST['edit_id'];
            $stmt = $conn->prepare(
                "UPDATE `roles` SET `name` = ?, `parent_role_id` = ?, `level` = ?, `status` = ? WHERE `id` = ?"
            );
            $stmt->bind_param("siiii", $name, $parent_role_id, $level, $status, $id);
        } else {
            // إضافة
            $stmt = $conn->prepare(
                "INSERT INTO `roles` (`name`, `parent_role_id`, `level`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param("siii", $name, $parent_role_id, $level, $status);
        }

        if ($stmt->execute()) {
            header("Location: roles.php?msg=تم+البحفاظ+على+البيانات+بنجاح+✔");
            exit;
        } else {
            $error_msg = 'حدث خطأ: ' . htmlspecialchars($stmt->error) . ' ❌';
        }
    }
}

/* حذف */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    // التحقق من عدم استخدام هذا الدور كدور أب
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `roles` WHERE `parent_role_id` = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        header("Location: roles.php?msg=لا+يمكن+حذف+هذا+الدور+لأنه+يمتلك+أدوار+فرعية+❌");
    } else {
        $stmt = $conn->prepare("DELETE FROM `roles` WHERE `id` = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: roles.php?msg=تم+حذف+الدور+بنجاح+✔");
        } else {
            header("Location: roles.php?msg=حدث+خطأ+في+الحذف+❌");
        }
    }
    exit;
}

// جلب جميع الأدوار الرئيسية (بدون دور أب)
$stmt = $conn->prepare("SELECT `id`, `name` FROM `roles` WHERE `parent_role_id` IS NULL ORDER BY `name`");
$stmt->execute();
$parent_roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/layout_head.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../../assets/css/admin-style.css">

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

.form-grid input:focus,
.form-grid select:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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

#roleForm {
    display: none;
    margin-bottom: 2rem;
}

#roleForm.show {
    display: block;
}
</style>

<div class="page-shell">
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">
            <i class="fas fa-shield-alt"></i> إدارة الأدوار والصلاحيات
        </h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة دور جديد
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
    <form id="roleForm" action="" method="post">
        <div class="card">
            <div class="card-header">
                <h5 style="margin: 0;">
                    <i class="fas fa-edit"></i> 
                    <?= !empty($editData) ? 'تعديل الدور' : 'إضافة دور جديد'; ?>
                </h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="<?= htmlspecialchars($editData['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-grid">
                    <!-- اسم الدور -->
                    <div>
                        <label><i class="fas fa-tag"></i> اسم الدور *</label>
                        <input type="text" name="name" id="name" placeholder="مثال: مدير المشاريع" 
                               value="<?= htmlspecialchars($editData['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>

                    <!-- الدور الأب (الصلاحية الأب) -->
                    <div>
                        <label><i class="fas fa-sitemap"></i> الدور الأب (اختياري)</label>
                        <select id="parent_role_id" name="parent_role_id">
                            <option value="">-- بدون دور أب (مدير رئيسي) --</option>
                            <?php foreach ($parent_roles as $pRole): ?>
                                <option value="<?= $pRole['id']; ?>"
                                    <?= (!empty($editData) && $editData['parent_role_id'] == $pRole['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pRole['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- المستوى -->
                    <div>
                        <label><i class="fas fa-layer-group"></i> مستوى الأهمية</label>
                        <select name="level" id="level" required>
                            <option value="1" <?= (empty($editData) || $editData['level'] == 1) ? 'selected' : ''; ?>>مدير</option>
                            <option value="2" <?= (!empty($editData) && $editData['level'] == 2) ? 'selected' : ''; ?>>مشرف</option>
                        </select>
                    </div>

                    <!-- الحالة -->
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة الدور *</label>
                        <select name="status" id="status" required>
                            <option value="1" <?= (empty($editData) || $editData['status'] == 1) ? 'selected' : ''; ?>>نشط ✔</option>
                            <option value="0" <?= (!empty($editData) && $editData['status'] == 0) ? 'selected' : ''; ?>>غير نشط ✖</option>
                        </select>
                    </div>

                    <button type="submit" class="add-btn" style="grid-column: 1 / -1; justify-self: center;">
                        <i class="fas fa-save"></i> حفظ الدور
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الأدوار -->
    <div class="card">
        <div class="card-header">
            <h5 style="margin: 0;">
                <i class="fas fa-list"></i> جميع الأدوار
            </h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="rolesTable" class="display">
                    <thead>
                        <tr>
                            <th width="80"><i class="fas fa-barcode"></i> #</th>
                            <th><i class="fas fa-tag"></i> اسم الدور</th>
                            <th><i class="fas fa-sitemap"></i> الدور الأب</th>
                            <th width="100"><i class="fas fa-layer-group"></i> المستوى</th>
                            <th width="100"><i class="fas fa-toggle-on"></i> الحالة</th>
                            <th width="120"><i class="fas fa-calendar"></i> تاريخ الإنشاء</th>
                            <th width="120"><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("
                            SELECT r.*, COALESCE(pr.name, 'لا يوجد') AS parent_name
                            FROM roles r
                            LEFT JOIN roles pr ON r.parent_role_id = pr.id
                            ORDER BY r.id
                        ");
                        
                        if ($result) {
                            $i = 1;
                            while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td><strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?= htmlspecialchars($row['parent_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center">
                                        <span style="background: var(--gold-soft); color: var(--gold); padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                            <?= $row['level'] == 1 ? 'مدير' : 'مشرف'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['status'] == 1): ?>
                                            <span style="background: var(--teal-soft); color: var(--teal); padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✔ نشط
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #f0f0f0; color: #999; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✖ معطل
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center">
                                        <a href="?edit_id=<?= $row['id']; ?>&action=edit" class="btn btn-sm btn-primary" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete_id=<?= $row['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا الدور؟');" class="btn btn-sm btn-danger" title="حذف">
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
    $('#rolesTable').DataTable({
        responsive: true,
        language: {
            url: "/ems/assets/i18n/datatables/ar.json"
        },
        columnDefs: [
            { "orderable": false, "targets": [6] }
        ]
    });

    // إظهار/إخفاء النموذج
    $('#toggleForm').on('click', function () {
        $('#roleForm').slideToggle(300);
        $('html, body').animate({
            scrollTop: $('#roleForm').offset().top - 100
        }, 500);
    });

    // إذا كان هناك edit_id في URL، أعرض النموذج
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('edit_id')) {
        $('#roleForm').addClass('show');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>



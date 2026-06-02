<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$perms = get_page_permissions($conn);

// التحقق من صلاحية عرض هذه الصفحة
if (!$perms['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا توجد صلاحية لعرض هذه الصفحة'));
    exit();
}


$page_title = "إيكوبيشن | أنواع المعدات";
include("../inheader.php");
include("../insidebar.php");

/* حذف النوع معطل (Backend) */
if (isset($_GET['delete_id'])) {
    http_response_code(403);
    exit('Deletion is temporarily disabled.');
}

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM equipments_types WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = trim($_POST['form']);
    $type = trim($_POST['type']);
    $status = $_POST['status'];

    if (!empty($_POST['edit_id'])) {
        $id = (int) $_POST['edit_id'];
        $stmt = $conn->prepare(
            "UPDATE equipments_types SET form = ?, type = ?, status = ? WHERE id = ?"
        );
        $stmt->bind_param("sssi", $form, $type, $status, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO equipments_types (form, type, status) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $form, $type, $status);
    }

    $stmt->execute();
    header("Location: equipments_types.php");
    exit;
}
?>

<div class="main equipments-types-main">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'إدارة أنواع المعدات';
    $header_icon    = 'fas fa-cubes';
    $header_actions = array();
    if ($perms['can_add']) {
        $header_actions[] = array('tag' => 'button', 'id' => 'toggleForm', 'class' => 'add', 'icon' => 'fa-solid fa-plus-circle', 'label' => 'إضافة نوع جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <!-- تنبيه الحذف (معطل) -->
    <div id="deleteAlert" class="alert alert-warning text-center equipments-types-alert-hidden">
        <i class="fa-solid fa-circle-info"></i>
        لا يمكن حذف نوع المعدات حاليًا، فقط يمكن تعطيله
    </div>

    <!-- نموذج إضافة / تعديل -->
    <form id="projectForm" method="post" class="allforms<?= !empty($editData) ? ' allforms-visible' : ''; ?>">

        <div class="card">
            <div class="card-header">
                <h5>
                    <?= !empty($editData) ? 'تعديل نوع المعدة' : 'إضافة نوع جديد'; ?>
                </h5>
            </div>

            <div class="card-body">
                <div class="form-grid">

                    <?php if (!empty($editData)): ?>
                        <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label>الفئة</label>
                        <select name="form" required>
                            <option value="">-- اختر الفئة --</option>
                            <option value="1" <?= (!empty($editData) && $editData['form'] === '1') ? 'selected' : ''; ?>>
                                معدات ثقيلة </option>
                            <option value="2" <?= (!empty($editData) && $editData['form'] === '2') ? 'selected' : ''; ?>>
                                شاحنات </option>
                            <option value="3" <?= (!empty($editData) && $editData['form'] === '3') ? 'selected' : ''; ?>>
                                خرمات </option>

                        </select>
                    </div>
                    <div>

                        <label>نوع المعدة</label>
                        <input type="text" name="type" required
                            value="<?= htmlspecialchars($editData['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div>
                        <label>الحالة</label>
                        <select name="status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="active" <?= (!empty($editData) && $editData['status'] === 'active') ? 'selected' : ''; ?>>
                                نشط
                            </option>
                            <option value="inactive" <?= (!empty($editData) && $editData['status'] === 'inactive') ? 'selected' : ''; ?>>
                                غير نشط
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-save"></i> حفظ
                    </button>

                </div>
            </div>
        </div>
    </form>

    <!-- جدول الأنواع -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> قائمة أنواع المعدات</h5>
        </div>

        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display equipments-types-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الفئة</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        $result = $conn->query("SELECT * FROM equipments_types");
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $i++; ?></td>
                                <td>
                                    <?php
                                    $form_badges = [
                                        '1' => ['class' => 'badge-heavy', 'icon' => 'fa-tractor', 'text' => 'معدات ثقيلة'],
                                        '2' => ['class' => 'badge-truck', 'icon' => 'fa-truck-moving', 'text' => 'شاحنات'],
                                        '3' => ['class' => 'badge-drill', 'icon' => 'fa-drill', 'text' => 'خرمات']
                                    ];

                                    $form_value = $row['form'];
                                    if (isset($form_badges[$form_value])) {
                                        $badge = $form_badges[$form_value];
                                        echo "<span class='{$badge['class']}'><i class='fas {$badge['icon']}'></i> {$badge['text']}</span>";
                                    } else {
                                        echo "<span class='badge-default'><i class='fas fa-question'></i> غير محدد</span>";
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?= $row['status'] === 'active'
                                        ? "<span class='status-active'>نشط</span>"
                                        : "<span class='status-inactive'>غير نشط</span>"; ?>
                                </td>
                                <td class="text-center">

                                    <div class="action-btns">
                                        <?php if ($perms['can_edit']): ?>
                                            <a href="equipments_types.php?edit_id=<?= $row['id']; ?>" class="action-btn edit"
                                                title="تعديل">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($perms['can_delete']): ?>
                                            <button type="button" class="action-btn delete delete-disabled" title="حذف">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
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

<!-- JS -->
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>

<script>
    $(document).ready(function () {

        $('#projectsTable').DataTable({
            responsive: true,
            language: {
                url: "/ems/assets/i18n/datatables/ar.json"
            }
        });

        $('#toggleForm').on('click', function () {
            const $form = $('#projectForm');
            if ($form.hasClass('allforms-visible')) {
                $form.removeClass('allforms-visible').slideUp(200);
            } else {
                $form.addClass('allforms-visible').hide().slideDown(250);
            }
        });

        $('.delete-disabled').on('click', function () {
            const alertBox = $('#deleteAlert');
            alertBox.fadeIn();

            setTimeout(() => {
                alertBox.fadeOut();
            }, 3000);
        });

    });
</script>

</body>

</html>

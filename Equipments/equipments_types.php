<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

$page_title = "إيكوبيشن | أنواع الآليات";
include("../inheader.php");
include("../insidebar.php");

/* منع الحذف مؤقتاً (Backend) */
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

    $type   = trim($_POST['type']);
    $status = $_POST['status'];

    if (!empty($_POST['edit_id'])) {
        $id = (int) $_POST['edit_id'];
        $stmt = $conn->prepare(
            "UPDATE equipments_types SET type = ?, status = ? WHERE id = ?"
        );
        $stmt->bind_param("ssi", $type, $status, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO equipments_types (type, status) VALUES (?, ?)"
        );
        $stmt->bind_param("ss", $type, $status);
    }

    $stmt->execute();
    header("Location: equipments_types.php");
    exit;
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
.delete-disabled {
    background: none;
    border: none;
    cursor: pointer;
    color: #6c757d;
    font-size: 16px;
}
.delete-disabled:hover {
    color: #dc3545;
}
</style>

<div class="main">

    <h2>إدارة أنواع الآليات</h2>

    <button id="toggleForm" style="float:left;" class="btn btn-warning mb-3">
        <i class="fa-solid fa-plus"></i> إضافة نوع جديد
    </button>

    <!-- رسالة الحذف (مخفية) -->
    <div id="deleteAlert" class="alert alert-warning text-center" style="display:none;">
        <i class="fa-solid fa-circle-info"></i>
        تم إيقاف الحذف مؤقتاً حفاظاً على سلامة البيانات
    </div>

    <!-- فورم إضافة / تعديل -->
    <form id="projectForm" method="post"
          style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <?= !empty($editData) ? 'تعديل نوع الآلية' : 'إضافة نوع آلية جديدة'; ?>
                </h5>
            </div>

            <div class="card-body">
                <div class="form-grid">

                    <?php if (!empty($editData)): ?>
                        <input type="hidden" name="edit_id"
                               value="<?= (int)$editData['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label>النوع</label>
                        <input type="text" name="type" required
                               value="<?= htmlspecialchars($editData['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div>
                        <label>الحالة</label>
                        <select name="status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="active" <?= (!empty($editData) && $editData['status'] === 'active') ? 'selected' : ''; ?>>
                                نشطة
                            </option>
                            <option value="inactive" <?= (!empty($editData) && $editData['status'] === 'inactive') ? 'selected' : ''; ?>>
                                غير نشطة
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
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">قائمة أنواع الآليات</h5>
        </div>

        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>النوع</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
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
                        <td><?= htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?= $row['status'] === 'active'
                                ? "<span style='color:green'>نشط</span>"
                                : "<span style='color:red'>غير نشط</span>"; ?>
                        </td>
                        <td class="text-center">

                            <!-- تعديل -->
                            <a href="equipments_types.php?edit_id=<?= $row['id']; ?>"
                               class="text-primary me-2" title="تعديل">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>

                            <!-- حذف (موقوف) -->
                            <button type="button" class="delete-disabled" title="حذف">
                                <i class="fa-solid fa-trash"></i>
                            </button>

                        </td>
                    </tr>
                <?php endwhile; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JS -->
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
$(document).ready(function () {

    $('#projectsTable').DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        }
    });

    $('#toggleForm').on('click', function () {
        $('#projectForm').slideToggle();
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

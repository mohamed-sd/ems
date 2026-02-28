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

<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    .delete-disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .delete-disabled:hover {
        opacity: 0.6;
    }

    .badge-heavy {
        background-color: #1a6fbb;
        color: #fff;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-block;
    }

    .badge-truck {
        background-color: #e07b00;
        color: #fff;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-block;
    }
</style>

<div class="main">

    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-cubes"></i></div>
            <h1 class="page-title">إدارة أنواع الآليات</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <button id="toggleForm" class="add">
                <i class="fa-solid fa-plus-circle"></i> إضافة نوع جديد
            </button>
        </div>
    </div>

    <!-- رسالة الحذف (مخفية) -->
    <div id="deleteAlert" class="alert alert-warning text-center" style="display:none;">
        <i class="fa-solid fa-circle-info"></i>
        تم إيقاف الحذف مؤقتاً حفاظاً على سلامة البيانات
    </div>

    <!-- فورم إضافة / تعديل -->
    <form id="projectForm" method="post" style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">

        <div class="card">
            <div class="card-header">
                <h5>
                    <?= !empty($editData) ? 'تعديل نوع الآلية' : 'إضافة نوع آلية جديدة'; ?>
                </h5>
            </div>

            <div class="card-body">
                <div class="form-grid">

                    <?php if (!empty($editData)): ?>
                        <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label>القسم</label>
                          <select name="form" required>
                            <option value="">-- اختر القسم --</option>
                            <option value="1"  <?= (!empty($editData) && $editData['form'] === '1') ? 'selected' : ''; ?>> معدات ثقيلة </option>
                            <option value="2" <?= (!empty($editData) && $editData['form'] === '2') ? 'selected' : ''; ?>> شاحنات  </option>
                        </select>
                    </div>
                    <div>                           

                        <label>إسم النوع</label>
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
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> قائمة أنواع الآليات</h5>
        </div>

        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>القسم</th>
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
                                <td>
                                   <?= $row['form'] === '1'
                                        ? "<span class='badge-heavy'>معدات ثقيلة</span>"
                                        : "<span class='badge-truck'>شاحنات</span>"; ?>
                                </td>
                                <td><?= htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?= $row['status'] === 'active'
                                        ? "<span class='status-active'>نشط</span>"
                                        : "<span class='status-inactive'>غير نشط</span>"; ?>
                                </td>
                                <td class="text-center">

                                    <div class="action-btns">
                                        <a href="equipments_types.php?edit_id=<?= $row['id']; ?>" class="action-btn edit"
                                            title="تعديل">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>

                                        <button type="button" class="action-btn delete delete-disabled" title="حذف">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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
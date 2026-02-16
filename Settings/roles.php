<?php
session_start();
// تضمين ملف الجلسات
include '../includes/sessions.php';
// تعريف عنوان الصفحة
$page_title = 'Equipation | الصلاحيات';
// تضمين الهيدر
include '../inheader.php';
// تضمين الشريط الجانبي
include '../insidebar.php';

require '../config.php';

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = trim($_POST['type']);
    $status = $_POST['status'];

    if (!empty($_POST['edit_id'])) {
        $id = (int) $_POST['edit_id'];
        $stmt = $conn->prepare(
            "UPDATE roles SET name = ?, status = ? WHERE id = ?"
        );
        $stmt->bind_param("ssi", $type, $status, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO roles (name, status ,created_at) VALUES (?,?,current_timestamp())"
        );
        $stmt->bind_param("ss", $type, $status);
    }

    $stmt->execute();
    header("Location: roles.php");
    exit;
}

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!--  Page Main Section  -->
<div class="main">

    <h2> إدارة صلاحيات المشروع </h2>

    <button id="toggleForm" style="float:left;" class="btn btn-warning mb-3">
        <i class="fa-solid fa-plus"></i> إضافة
    </button>

    <!-- فورم إضافة / تعديل -->
    <form id="projectForm" method="post" style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <?= !empty($editData) ? 'تعديل صلاحية' : 'إضافة صلاحية جديدة'; ?>
                </h5>
            </div>

            <div class="card-body">
                <div class="form-grid">

                    <?php if (!empty($editData)): ?>
                        <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label>الصلاحية</label>
                        <input type="text" name="type" required
                            value="<?= htmlspecialchars($editData['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div>
                        <label>الحالة</label>
                        <select name="status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1" <?= (!empty($editData) && $editData['status'] === '1') ? 'selected' : ''; ?>>
                                نشطة
                            </option>
                            <option value="0" <?= (!empty($editData) && $editData['status'] === '0') ? 'selected' : ''; ?>>
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

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">قائمة الصلاحيات</h5>
        </div>

        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الصلاحية</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $result = $conn->query("SELECT * FROM roles");
                    $i = 1;
                    while ($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?= $row['status'] === '1'
                                    ? "<span style='color:green'>نشط</span>"
                                    : "<span style='color:red'>غير نشط</span>"; ?>
                            </td>
                            <td class="text-center">

                                <!-- تعديل -->
                                <a href="roles.php?edit_id=<?= $row['id']; ?>" class="text-primary me-2" title="تعديل">
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
    $('#projectsTable').DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        }
    });
    $(document).ready(function () {
        $('#toggleForm').on('click', function () {
            $('#projectForm').slideToggle();
        });
    });
</script>
</body>

</html>
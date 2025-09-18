<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
$page_title = "إيكوبيشن | الآليات ";
include("../inheader.php");
include("../insidebar.php");
include '../config.php';

// معالجة الحفظ أو التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
    $code      = mysqli_real_escape_string($conn, $_POST['code']);
    $type      = mysqli_real_escape_string($conn, $_POST['type']);
    $name      = mysqli_real_escape_string($conn, $_POST['name']);
    $status    = mysqli_real_escape_string($conn, $_POST['status']);
    $edit_id   = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    if ($edit_id > 0) {
        // تعديل
        $sql = "UPDATE equipments 
                SET suppliers='$suppliers', code='$code', type='$type', name='$name', status='$status' 
                WHERE id='$edit_id'";
    } else {
        // إضافة
        $sql = "INSERT INTO equipments (suppliers, code, type, name, status) 
                VALUES ('$suppliers', '$code', '$type', '$name', '$status')";
    }

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='equipments.php';</script>";
        exit;
    } else {
        echo "<script>alert('❌ خطأ في الحفظ: " . mysqli_error($conn) . "');</script>";
    }
}

// في حالة تعديل تجهيز البيانات
$editData = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM equipments WHERE id='$editId'");
    if ($res && mysqli_num_rows($res) > 0) {
        $editData = mysqli_fetch_assoc($res);
    }
}
?>

<div class="main">
    <?php if($_SESSION['user']['role'] == "4"){?>
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> <?php echo !empty($editData) ? "تعديل معدة" : "إضافة معدة"; ?>
        </a>
    </div>
    <?php } ?>
    <!-- فورم إضافة / تعديل معدة -->
    <form id="projectForm" action="" method="post" style="display:<?php echo !empty($editData) ? 'block' : 'none'; ?>;">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"> <?php echo !empty($editData) ? "تعديل الآلية" : "إضافة آلية جديدة"; ?> </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <?php if (!empty($editData)) { ?>
                        <input type="hidden" name="edit_id" value="<?php echo isset($editData['id']) ? $editData['id'] : ''; ?>">
                    <?php } ?>
                    <div>
                        <label> المورد </label>
                        <select name="suppliers" required>
                            <option value="">-- اختر المورد --</option>
                            <?php
                            $dr_res = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE status='1'");
                            while ($dr = mysqli_fetch_assoc($dr_res)) {
                                $selected = (!empty($editData) && $editData['suppliers'] == $dr['id']) ? "selected" : "";
                                echo "<option value='" . $dr['id'] . "' $selected>" . $dr['name'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label> كود المعدة </label>
                        <input type="text" name="code" id="code" placeholder="كود المعدة" 
                               value="<?php echo isset($editData['code']) ? $editData['code'] : ''; ?>" required />
                    </div>
                    <div>
                        <label> نوع المعدة </label>
                        <select name="type" id="type">
                            <option value=""> -- حدد نوع المعدة --- </option>
                            <option value="1" <?php echo (!empty($editData) && $editData['type']=="1") ? "selected" : ""; ?>> حفار </option>
                            <option value="2" <?php echo (!empty($editData) && $editData['type']=="2") ? "selected" : ""; ?>> قلاب </option>
                        </select>
                    </div>
                    <div>
                        <label> اسم المعدة </label>
                        <input type="text" name="name" id="name" placeholder="اسم المعدة" 
                               value="<?php echo isset($editData['name']) ? $editData['name'] : ''; ?>" required />
                    </div>
                    <div>
                        <label> الحالة </label>
                        <select name="status" id="status" required>
                            <option value=""> -- اختر الحالة -- </option>
                            <option value="1" <?php echo (!empty($editData) && $editData['status']=="1") ? "selected" : ""; ?>> متاحة </option>
                            <option value="0" <?php echo (!empty($editData) && $editData['status']=="0") ? "selected" : ""; ?>> مشغولة </option>
                        </select>
                    </div>
                    <button type="submit">حفظ المعدة</button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المعدات -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"> قائمة المعدات</h5>
        </div>
        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المورد</th>
                        <th>كود المعدة</th>
                        <th>النوع</th>
                        <th>الاسم</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query2 = "SELECT m.id, s.name AS supplier_name, m.type, m.code, m.name , m.status 
                               FROM equipments m
                               JOIN suppliers s ON m.suppliers = s.id
                               ORDER BY m.id DESC";
                    $result = mysqli_query($conn, $query2);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $row['supplier_name'] . "</td>";
                        echo "<td>" . $row['code'] . "</td>";
                        echo $row['type'] == "1" ? "<td style='color:green;'> حفار </td>" : "<td style='color:green;'> قلاب </td>";
                        echo "<td>" . $row['name'] . "</td>";
                        echo $row['status'] == "1" ? "<td style='color:green;'> متاحة </td>" : "<td style='color:red;'> مشغولة </td>";

                        // روابط الإجراءات
                        if ($_SESSION['user']['role'] == "3") {
                            echo "<td>
                                <a href='add_drivers.php?equipment_id=" . $row['id'] . "' style='color:#007bff'> مشغل </a>
                              </td>";
                        } else {
                            echo "<td> 
                                <a href='equipments.php?edit=" . $row['id'] . "' style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                                <a href='#' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> 
                              
                              </td>";
                        }

                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
    (function () {
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        const toggleFormBtn = document.getElementById('toggleForm');
        const equipmentForm = document.getElementById('projectForm');

        toggleFormBtn.addEventListener('click', function () {
            equipmentForm.style.display = equipmentForm.style.display === "none" ? "block" : "none";
        });
    })();
</script>

</body>
</html>

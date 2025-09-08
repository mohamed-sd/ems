<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
$page_title = "إيكوبيشن | التشغيل ";
include("../inheader.php");
?>

<?php include('../insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة تشغيل
    </a>

    <!-- فورم إضافة تشغيل -->
    <form id="projectForm" action="" method="post" style="display:none; margin-top:20px;">
        <div class="form-grid">
            <select name="equipment" required>
                <option value="">-- اختر المعدة --</option>
                <?php
                include '../config.php';
                $eq_res = mysqli_query($conn, "SELECT id, code, name FROM equipments WHERE id NOT IN ( SELECT operations.equipment FROM `operations` WHERE `status` LIKE '1' )");
                while ($eq = mysqli_fetch_assoc($eq_res)) {
                    echo "<option value='" . $eq['id'] . "'>" . $eq['code'] . " - " . $eq['name'] . "</option>";
                }
                ?>
            </select>

            <!-- المشروع -->
            <select name="project" required>
                <option value="">-- اختر المشروع --</option>
                <?php
                $pr_res = mysqli_query($conn, "SELECT id, name FROM projects");
                while ($pr = mysqli_fetch_assoc($pr_res)) {
                    echo "<option value='" . $pr['id'] . "'>" . $pr['name'] . "</option>";
                }
                ?>
            </select>

            <input type="date" name="start" required placeholder="تاريخ البداية" />
            <input type="date" name="end" required placeholder="تاريخ النهاية" />
            <input type="hidden" step="0.01" name="hours" placeholder="عدد الساعات" value="0"/>
            <select name="status" required>
                <option value="1">نشط</option>
                <option value="0">منتهي</option>
            </select>
            <br />
            <button type="submit">حفظ التشغيل</button>
        </div>
    </form>

    <br /> <br /> <br />

    <!-- جدول التشغيل -->
    <h3>قائمة التشغيل</h3>
    <br />
    <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:right;">المعدة</th>

                <th style="text-align:right;">المورد</th>
                <th style="text-align:right;">المشروع</th>


                <th style="text-align:right;">تاريخ البداية</th>
                <th style="text-align:right;">تاريخ النهاية</th>
                <!-- <th style="text-align:right;">عدد الساعات</th> -->
                <th style="text-align:right;">الحالة</th>
                <th style="text-align:right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // إضافة تشغيل جديد
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['equipment'])) {
                $equipment = intval($_POST['equipment']);
                $project = intval($_POST['project']);
                $start = mysqli_real_escape_string($conn, $_POST['start']);
                $end = mysqli_real_escape_string($conn, $_POST['end']);
                $hours = floatval($_POST['hours']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);

                mysqli_query($conn, "INSERT INTO operations (equipment, project, start, end, hours, status) 
                                     VALUES ('$equipment', '$project', '$start', '$end', '$hours', '$status')");
            }

            // جلب بيانات التشغيل
            $query = "SELECT o.id, o.start, o.end, o.hours, o.status, 
                             e.code AS equipment_code, e.name AS equipment_name,
                             p.name AS project_name ,s.name AS suppliers_name
                      FROM operations o
                      LEFT JOIN equipments e ON o.equipment = e.id
                      LEFT JOIN projects p ON o.project = p.id

                      LEFT JOIN suppliers s ON e.suppliers = s.id
                      ORDER BY o.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . $i++ . "</td>";
                echo "<td>" . $row['equipment_code'] . " - " . $row['equipment_name'] . "</td>";
                echo "<td>" . $row['suppliers_name'] . "</td>";

                echo "<td>" . $row['project_name'] . "</td>";

                echo "<td>" . $row['start'] . "</td>";
                echo "<td>" . $row['end'] . "</td>";
                // echo "<td>" . $row['hours'] . "</td>";
                echo $row['status'] == "1" ? "<td style='color:green'> نشطة </td>" : "<td style='color:red'> خاملة </td>";
                echo "<td>
                        <a href='edit_operation.php?id=" . $row['id'] . "' style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                        <a href='delete_operation.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a>
                      </td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>

</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
        // تشغيل DataTable بالعربية
        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip', // Buttons + Search + Pagination
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

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const form = document.getElementById('projectForm');

        toggleFormBtn.addEventListener('click', function () {
            form.style.display = form.style.display === "none" ? "block" : "none";
        });
    })();
</script>

</body>

</html>
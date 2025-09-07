<?php
$page_title = "إيكوبيشن | الآليات ";
include("../inheader.php");
?>

<?php include('../insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة معدة
    </a>

    <!-- فورم إضافة معدة -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="form-grid">

            <?php if (isset($_GET['id'])) { ?>
                <input type="hidden" name="suppliers" value="<?php echo $_GET['id']; ?>" placeholder="المورد" required />
            <?php } ?>
            <div>
            <label> المورد </label>
             <select name="suppliers" required>
                <option value="">-- اختر المورد --</option>
                <?php
                include '../config.php';
                $dr_res = mysqli_query($conn, "SELECT id, name FROM suppliers");
                while ($dr = mysqli_fetch_assoc($dr_res)) {
                    echo "<option value='" . $dr['id'] . "'>" . $dr['name'] . "</option>";
                }
                ?>
            </select>
            </div>
            <div>
            <label> كود المعدة </label>
            <input type="text" name="code" placeholder="كود المعدة" required />
            </div>
            <div>
            <label> نوع المعدة </label>
            <select name="type">
                <option value=""> -- حدد نوع المعدة --- </option>
                <option value="1"> حفار </option>
                <option value="2"> قلاب </option>
            </select>
            </div>
            <div>
            <label> اسم المعدة </label>
            <input type="text" name="name" placeholder="اسم المعدة" required />
            </div>
            <div>
            <label> الحالة </label>
            <select name="status" required>
                <option value=""> -- اختر الحالة -- </option>
                <option value="1"> متاحة </option>
                <option value="0"> مشغولة </option>
            </select>
            </div>
            <br />
            <button type="submit">حفظ المعدة</button>
        </div>
    </form>

    <br /><br /><br />

    <!-- جدول المعدات -->
    <h3>قائمة المعدات</h3>
    <br />
    <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">المورد</th>
                <th style="text-align: right;">كود المعدة</th>
                <th style="text-align: right;">النوع</th>
                <th style="text-align: right;">الاسم</th>
                <th style="text-align: right;">الحالة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';

            // إضافة معدة جديدة عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
                $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
                $code = mysqli_real_escape_string($conn, $_POST['code']);
                $type = mysqli_real_escape_string($conn, $_POST['type']);
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                mysqli_query($conn, "INSERT INTO equipments (suppliers, code, type, name, status) VALUES ('$suppliers', '$code', '$type', '$name', '$status')");
            }

            // جلب المعدات
            $query = "SELECT `id`, `suppliers`, `code`, `type`, `name`, `status` FROM `equipments` ORDER BY id DESC";

            $query2 = "SELECT m.id, s.name AS supplier_name, m.type, m.code, m.name , m.status 
FROM equipments m

JOIN suppliers s ON m.suppliers = s.id";

            $result = mysqli_query($conn, $query2);
            $i = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . $i++ . "</td>";
                echo "<td>" . $row['supplier_name'] . "</td>";
                echo "<td>" . $row['code'] . "</td>";
                echo "<td>" . $row['type'] . "</td>";
                echo "<td>" . $row['name'] . "</td>";
                echo $row['status'] == "1" ? "<td style='color:green;'> متاحة </td>" : "<td style='color:red;'> مشغولة </td>"  ;
                echo "<td>
                <a href='add_drivers.php?equipment_id=" . $row['id'] . "' style='color:#007bff'><i class='fa fa-edit'></i></a</a> | 
                        <a href='edit.php?id=" . $row['id'] . "' style='color:#007bff'><i class='fa fa-edit'></i></a</a> | 
                        <a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                        <a href='' style='color: #28a745'> <i class='fa fa-eye'></i> </a>
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
        const equipmentForm = document.getElementById('projectForm');

        toggleFormBtn.addEventListener('click', function () {
            equipmentForm.style.display = equipmentForm.style.display === "none" ? "block" : "none";
        });
    })();
</script>

</body>

</html>
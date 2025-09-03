<?php 
  $page_title = "إيكوبيشن | الموردين"; 
  include("../includes/inheader.php"); 
?>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> إضافة مورد
    </a>

    <!-- فورم إضافة مورد -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <input type="text" name="name" placeholder="اسم المورد" required />
        <input type="text" name="phone" placeholder="رقم الهاتف" required />
        <select name="status" required>
            <option value="">حالة المورد</option>
            <option value="نشط">نشط</option>
            <option value="معلق">معلق</option>
            <option value="محذوف">محذوف</option>
        </select>
        <br/>
        <button type="submit">حفظ المورد</button>
    </form>

    <br/><br/><br/>

    <!-- جدول الموردين -->
    <h3>قائمة الموردين</h3>
    <br/>
    <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">اسم المورد</th>
                <th style="text-align: right;">عدد الآليات</th>
                <th style="text-align: right;">رقم الهاتف</th>
                <th style="text-align: right;">الحالة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            // إضافة مورد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                mysqli_query($conn, "INSERT INTO suppliers (name, phone, status) VALUES ('$name', '$phone', '$status')");
            }

            // جلب الموردين
            $query = "SELECT `id`, `name`, `phone`, `status` , (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = suppliers.id ) as 'equipments' FROM `suppliers` ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['name']."</td>";
                echo "<td>".$row['equipments']."</td>";
                echo "<td>".$row['phone']."</td>";
                echo "<td>".$row['status']."</td>";
                echo "<td>
                        <a href='edit.php?id=".$row['id']."' style='color:#007bff'> <i class='fa fa-edit'></i></a> </a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                        <a href='suppliers_details.php?id=".$row['id']."' style='color: #28a745'><i class='fa fa-eye'></i></a>
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
<!-- JS -->
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
(function() {
    // تشغيل DataTable بالعربية
    $(document).ready(function() {
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
    const toggleSupplierFormBtn = document.getElementById('toggleForm');
    const supplierForm = document.getElementById('projectForm');

    toggleSupplierFormBtn.addEventListener('click', function() {
        supplierForm.style.display = supplierForm.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>
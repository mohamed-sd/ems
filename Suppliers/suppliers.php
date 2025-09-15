<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($id > 0) {
        // تحديث
        mysqli_query($conn, "UPDATE suppliers SET name='$name', phone='$phone', status='$status' WHERE id=$id");
        header("Location: suppliers.php?msg=تم+تعديل+المورد+بنجاح+✅");
        exit;
    } else {
        // إضافة
        mysqli_query($conn, "INSERT INTO suppliers (name, phone, status) VALUES ('$name', '$phone', '$status')");
        header("Location: suppliers.php?msg=تمت+إضافة+المورد+بنجاح+✅");
        exit;
    }
}
?>
<?php
$page_title = "إيكوبيشن | الموردين";
include("../inheader.php");
?>
<?php include('../insidebar.php'); ?>

<div class="main">
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> إضافة مورد
        </a>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div style="background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin:10px 0; text-align:center;">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مورد -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"> اضافة/ تعديل مشروع </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <input type="hidden" name="id" id="supplier_id" value="">
                        <input type="text" name="name" id="supplier_name" placeholder="اسم المورد" required />
                    </div>
                    <div>
                        <input type="text" name="phone" id="supplier_phone" placeholder="رقم الهاتف" required />
                    </div>
                    <div>
                        <select name="status" id="supplier_status" required>
                            <option value="">حالة المورد</option>
                            <option value="1">نشط</option>
                            <option value="0">معلق</option>
                        </select>
                    </div>
                    <button type="submit">حفظ المورد</button>
                </div>
            </div>
        </div>
    </form>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"> قائمة الموردين </h5>
        </div>
        <div class="card-body">
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
                    // جلب الموردين
                    $query = "SELECT `id`, `name`, `phone`, `status` , 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = suppliers.id ) as 'equipments' 
                      FROM `suppliers` ORDER BY id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $row['name'] . "</td>";
                        echo "<td>" . $row['equipments'] . "</td>";
                        echo "<td>" . $row['phone'] . "</td>";

                        // الحالة بالألوان
                        if ($row['status'] == "1") {
                            echo "<td style='color:green'>نشط</td>";
                        } else {
                            echo "<td style='color:red'>معلق</td>";
                        }

                        echo "<td>
                        <a href='javascript:void(0)' 
                           class='editBtn' 
                           data-id='" . $row['id'] . "' 
                           data-name='" . $row['name'] . "' 
                           data-phone='" . $row['phone'] . "' 
                           data-status='" . $row['status'] . "' 
                           style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                        <a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                        <a href='suppliers_details.php?id=" . $row['id'] . "' style='color: #28a745'><i class='fa fa-eye'></i></a>
                      </td>";
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
        // تشغيل DataTable
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

        // اظهار/اخفاء الفورم
        const toggleSupplierFormBtn = document.getElementById('toggleForm');
        const supplierForm = document.getElementById('projectForm');
        toggleSupplierFormBtn.addEventListener('click', function () {
            supplierForm.style.display = supplierForm.style.display === "none" ? "block" : "none";
            // تنظيف الحقول عند الإضافة
            $("#supplier_id").val("");
            $("#supplier_name").val("");
            $("#supplier_phone").val("");
            $("#supplier_status").val("");
        });

        // عند الضغط على زر تعديل
        $(document).on("click", ".editBtn", function () {
            $("#supplier_id").val($(this).data("id"));
            $("#supplier_name").val($(this).data("name"));
            $("#supplier_phone").val($(this).data("phone"));
            $("#supplier_status").val($(this).data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });
    })();
</script>

</body>

</html>
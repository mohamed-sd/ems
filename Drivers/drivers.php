<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
$page_title = "إيكوبيشن | السائقين ";
include("../inheader.php");
?>
<?php include('../insidebar.php'); ?>
<div class="main">
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> اضافة مشغل
        </a>
    </div>
    <!-- فورم إضافة مشروع -->
    <form id="projectForm" action="" method="post">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"> اضافة/ تعديل مشغل </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <input type="hidden" name="id" id="drivers_id" value="">
                        <label for="name"> اسم المشغل </label>
                        <input type="text" name="name" id="name" placeholder="اسم المشغل" required />
                    </div>
                    <div>
                        <label for="phone"> رقم الهاتف </label>
                        <input type="text" name="phone" id="phone" placeholder="رقم الهاتف " required />
                    </div>
                    <div>
                        <label> الحالة </label>
                        <select name="status" id="status">
                            <option value=""> -- اختار الحالة -- </option>
                            <option value="1"> يعمل </option>
                            <option value="0"> عاطل </option>
                        </select>
                    </div>
                    <br />
                    <button type="submit"> حفظ </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"> قائمة المشغلين</h5>
        </div>
        <div class="card-body">
            <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم السائق</th>
                        <th> الهاتف </th>
                        <th> الحالة </th>
                        <th> إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    include '../config.php';

                    // إضافة سائق جديد عند إرسال الفورم
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                        $name = mysqli_real_escape_string($conn, $_POST['name']);
                        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                        $status = mysqli_real_escape_string($conn, $_POST['status']);


                        // mysqli_query($conn, "INSERT INTO drivers (name, phone) VALUES ('$name', '$phone')");

                        if ($id > 0) {
                            // تحديث
                            mysqli_query($conn, "UPDATE drivers SET name='$name', phone='$phone' , status='$status' WHERE id=$id");
                            echo "<script>window.location.href='drivers.php';</script>";
                            exit;
                        } else {
                            // إضافة
                            mysqli_query($conn, "INSERT INTO drivers (name, phone , status) VALUES ('$name', '$phone' , '$status')");
                            echo "<script>window.location.href='drivers.php';</script>";
                            exit;
                        }
                    }

                    // جلب المشاريع
                    $query = "SELECT `id`, `name`, `phone` , `status` FROM drivers ORDER BY id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $row['name'] . "</td>";
                        echo "<td>" . $row['phone'] . "</td>";
                        if ($row['status'] == "1") {
                            echo "<td style='color:green'>يعمل</td>";
                        } else {
                            echo "<td style='color:red'>عاطل</td>";
                        }
                        echo "<td>
                         <a href='javascript:void(0)' 
                           class='editBtn' 
                           data-id='" . $row['id'] . "' 
                           data-name='" . $row['name'] . "' 
                           data-phone='" . $row['phone'] . "' 
                           data-status='" . $row['status'] . "' 
    
                           style='color:#007bff'><i class='fa fa-edit'></i></a>  | 
                        <a href='#' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> 
                        <a href='drivercontracts.php?id=" . $row['id'] . "' style='color: #28a745'><i class='fa fa-eye'></i></a>
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
        const toggleProjectFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        toggleProjectFormBtn.addEventListener('click', function () {
            projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
            // تنظيف الحقول عند الإضافة
            $("#drivers_id").val("");
            $("#name").val("");
            $("#phone").val("");
            $("#status").val("");
        });

        // عند الضغط على زر تعديل
        $(document).on("click", ".editBtn", function () {
            $("#drivers_id").val($(this).data("id"));
            $("#name").val($(this).data("name"));
            $("#phone").val($(this).data("phone"));
            $("#status").val($(this).data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });


    })();
</script>

</body>

</html>
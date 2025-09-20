<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include 'config.php';

// إضافة أو تعديل مستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name     = $_POST['name'];
    $username = $_POST['username'];
    $password = $_POST['password']; // بدون تشفير
    $phone    = $_POST['phone'];
    $role     = $_POST['role'];
    $project  = ($role == "5" && !empty($_POST['project_id'])) ? $_POST['project_id'] : 0;
    $uid      = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

    if ($uid > 0) {
        // تعديل
        $sql = "UPDATE users 
                SET name='$name', username='$username', password='$password', phone='$phone', role='$role', project_id='$project', updated_at=NOW() 
                WHERE id='$uid'";
        mysqli_query($conn, $sql);
    } else {
        // إضافة
        $sql = "INSERT INTO users (name, username, password, phone, role, project_id, parent_id, created_at, updated_at) 
                VALUES ('$name', '$username', '$password', '$phone', '$role', '$project', '0', NOW(), NOW())";
        mysqli_query($conn, $sql);
    }
}

// حذف مستخدم
// if (isset($_GET['delete'])) {
//     $delete_id = intval($_GET['delete']);
//     mysqli_query($conn, "DELETE FROM users WHERE id='$delete_id'");
//     header("Location: users.php");
//     exit();
// }

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> إيكوبيشن | المستخدمين </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css" />
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
</head>

<body>

    <?php include('sidebar.php'); ?>

    <div class="main">

        <div class="aligin">
            <a href="javascript:void(0)" id="toggleForm" class="add">
                <i class="fa fa-plus"></i> إضافة مستخدم
            </a>
        </div>

        <form id="projectForm" action="" method="post" style="display:none;">
            <input type="hidden" name="uid" id="uid" value="0" />
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"> اضافة/ تعديل مستخدم </h5>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div>
                            <label> الاسم ثلاثي </label>
                            <input type="text" name="name" id="name" placeholder=" الاسم ثلاثي" required />
                        </div>
                        <div>
                            <label> اسم المستخدم </label>
                            <input type="text" name="username" id="username" placeholder="اسم المستخدم" required />
                        </div>
                        <div>
                            <label> كلمة المرور </label>
                            <input type="password" name="password" id="password" placeholder="كلمه المرور " required />
                        </div>
                        <div>
                            <label class="form-label">الدور / الصلاحية</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value=""> -- حدد الصلاحية -- </option>
                                <option value="1"> مدير المشاريع </option>
                                <option value="2"> مدير الموردين </option>
                                <option value="3"> مدير المشغلين </option>
                                <option value="4"> مدير الاسطول </option>
                                <option value="5"> مدير موقع </option>
                            </select>
                        </div>
                        <div>
                            <label>رقم الهاتف</label>
                            <input type="text" name="phone" id="phone" placeholder="رقم الهاتف" required />
                        </div>

                        <div id="projectDiv" style="display:none;">
                            <label class="form-label">المشروع</label>
                            <select id="project_id" name="project_id" class="form-control">
                                <option value="">-- اختر المشروع --</option>
                                <?php
                                $sql = "SELECT id, name FROM projects where status = '1' ORDER BY name ASC";
                                $result = mysqli_query($conn, $sql);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success mt-3">حفظ المستخدم</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"> قائمة المستخدمين</h5>
            </div>
            <div class="card-body">
                <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم </th>
                            <th>اسم المستخدم </th>
                            <th>كلمه المرور </th>
                            <th>الدور </th>
                            <th>رقم الهاتف</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT id, name, username, password, phone, role FROM users WHERE parent_id='0' AND role!='-1' ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);

                        $roles = array(
                            "1" => "مدير المشاريع",
                            "2" => "مدير الموردين",
                            "3" => "مدير المشغلين",
                            "4" => "مدير الاسطول",
                            "5" => "مدير موقع"
                        );

                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $i++ . "</td>";
                            echo "<td>" . $row['name'] . "</td>";
                            echo "<td>" . $row['username'] . "</td>";
                            echo "<td>" . $row['password'] . "</td>";
                            echo "<td>" . (isset($roles[$row['role']]) ? $roles[$row['role']] : "غير معروف") . "</td>";
                            echo "<td>" . $row['phone'] . "</td>";
                            echo "<td>
                                <a href='javascript:void(0)' class='editBtn' data-id='{$row['id']}' data-name='{$row['name']}' data-username='{$row['username']}' data-password='{$row['password']}' data-phone='{$row['phone']}' data-role='{$row['role']}' style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                                <a href='?delete={$row['id']}' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a>
                              </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- jQuery + DataTables -->
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
        document.addEventListener("DOMContentLoaded", function () {
            const roleSelect = document.getElementById("role");
            const projectDiv = document.getElementById("projectDiv");
            const projectSelect = document.getElementById("project_id");

            roleSelect.addEventListener("change", function () {
                if (this.value === "5") {
                    projectDiv.style.display = "block";
                    projectSelect.setAttribute("required", "required");
                } else {
                    projectDiv.style.display = "none";
                    projectSelect.removeAttribute("required");
                    projectSelect.value = "";
                }
            });

            // DataTable
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

            // إظهار/إخفاء الفورم
            const toggleFormBtn = document.getElementById('toggleForm');
            const form = document.getElementById('projectForm');
            toggleFormBtn.addEventListener('click', function () {
                form.reset();
                document.getElementById("uid").value = 0;
                form.style.display = (form.style.display === "none") ? "block" : "none";
            });

            // تعبئة الفورم عند التعديل
            $(document).on('click', '.editBtn', function () {
                $('#uid').val($(this).data('id'));
                $('#name').val($(this).data('name'));
                $('#username').val($(this).data('username'));
                $('#password').val($(this).data('password'));
                $('#phone').val($(this).data('phone'));
                $('#role').val($(this).data('role')).trigger('change');
                form.style.display = "block";
            });
        });
    </script>

</body>
</html>

<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include 'config.php';

// إضافة أو تعديل مستخدم (بدون تشفير كلمة المرور)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = isset($_POST['password']) ? mysqli_real_escape_string($conn, $_POST['password']) : '';
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $project = ($role == "5" && !empty($_POST['project_id'])) ? intval($_POST['project_id']) : 0;
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

    if ($uid > 0) {
        // تحقق من التكرار عند التعديل (يتجاهل السجل الحالي)
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != '$uid' LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            // إذا تم إدخال كلمة مرور جديدة، نحدّثها كما هي؛ وإلا لا نغيّرها
            $sql_pass = "";
            if (!empty($password)) {
                $sql_pass = ", password='$password'";
            }

            $sql = "UPDATE users 
                    SET name='$name', username='$username', phone='$phone', role='$role', project_id='$project', updated_at=NOW() $sql_pass
                    WHERE id='$uid'";
            if (mysqli_query($conn, $sql)) {
                echo "<script>alert('✅ تم التعديل بنجاح'); window.location.href='users.php';</script>";
            } else {
                echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
            }
        }
    } else {
        // تحقق من التكرار عند الإضافة
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            // إدراج كلمة المرور كنص عادي (غير مشفر)
            $sql = "INSERT INTO users (name, username, password, phone, role, project_id, parent_id, created_at, updated_at) 
                    VALUES ('$name', '$username', '$password', '$phone', '$role', '$project', '0', NOW(), NOW())";
            if (mysqli_query($conn, $sql)) {
                echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='users.php';</script>";
            } else {
                echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
            }
        }
    }
}

// ملاحظة: التعامل مع حذف المستخدم موجود في الواجهة (رابط ?delete=...) لكن كود الحذف كان معلقاً في النسخة الأصلية.
// إذا أردت أفعّل الحذف أضيفه لك هنا بأمان مع تحقق الصلاحيات.
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
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');
        
        * {
            font-family: 'Cairo', sans-serif;
        }
        
        body {
            background: #f5f7fa;
        }
        
        .main {
            padding: 2rem;
            background: #f5f7fa;
        }
        
        /* Page Title */
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Action Buttons */
        .aligin {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .aligin .add {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .aligin .add:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }
        
        /* Card Styling */
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-grid > div {
            display: flex;
            flex-direction: column;
        }
        
        .form-grid label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid input,
        .form-grid select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-grid input:focus,
        .form-grid select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }
        
        .form-grid input::placeholder {
            color: #adb5bd;
        }
        
        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            color: white;
        }
        
        /* DataTable Custom Styling */
        #projectsTable {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 15px;
            overflow: hidden;
        }
        
        #projectsTable thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        #projectsTable thead th {
            padding: 1rem;
            font-weight: 700;
            text-align: center;
            border: none;
            font-size: 1rem;
        }
        
        #projectsTable tbody tr {
            transition: all 0.3s ease;
            background: white;
        }
        
        #projectsTable tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
        }
        
        #projectsTable tbody td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            font-weight: 500;
            vertical-align: middle;
        }
        
        /* Action Links */
        #projectsTable a {
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-block;
            padding: 0.3rem;
        }
        
        #projectsTable a:hover {
            transform: scale(1.2);
        }
        
        #projectsTable a.editBtn {
            color: #007bff;
        }
        
        #projectsTable a.editBtn:hover {
            color: #0056b3;
        }
        
        #projectsTable a[href*='delete'] {
            color: #dc3545;
        }
        
        #projectsTable a[href*='delete']:hover {
            color: #bd2130;
        }
        
        /* Role Badge */
        .role-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .role-badge.role-1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .role-badge.role-2 {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .role-badge.role-3 {
            background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
            color: white;
        }
        
        .role-badge.role-4 {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }
        
        .role-badge.role-5 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        /* Small Text */
        .text-muted {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        /* Alert Styling */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem;
            font-weight: 500;
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* DataTables Buttons */
        .dt-buttons {
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .dt-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* Password Display */
        .password-cell {
            font-family: monospace;
            background: #f8f9fa;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            color: #495057;
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .aligin {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <?php include('sidebar.php'); ?>

    <div class="main">
        
        <div class="page-header">
            <h2><i class="fas fa-users"></i> إدارة المستخدمين</h2>
        </div>

        <div class="aligin">
            <a href="javascript:void(0)" id="toggleForm" class="add">
                <i class="fas fa-user-plus"></i> إضافة مستخدم جديد
            </a>
        </div>

        <form id="projectForm" action="" method="post" style="display:none;">
            <input type="hidden" name="uid" id="uid" value="0" />
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit"></i> إضافة / تعديل مستخدم</h5>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div>
                            <label><i class="fas fa-user"></i> الاسم الثلاثي</label>
                            <input type="text" name="name" id="name" placeholder="الاسم الثلاثي" required />
                        </div>
                        <div>
                            <label><i class="fas fa-id-badge"></i> اسم المستخدم</label>
                            <input type="text" name="username" id="username" placeholder="اسم المستخدم" required />
                        </div>
                        <div>
                            <label><i class="fas fa-lock"></i> كلمة المرور</label>
                            <input type="password" name="password" id="password" placeholder="كلمة المرور" />
                            <small class="text-muted"><i class="fas fa-info-circle"></i> اتركه فارغاً إذا لا تريد تغييره عند التعديل</small>
                        </div>
                        <div>
                            <label><i class="fas fa-user-shield"></i> الدور / الصلاحية</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="">-- حدد الصلاحية --</option>
                                <option value="1">👨‍💼 مدير المشاريع</option>
                                <option value="2">🏢 مدير الموردين</option>
                                <option value="3">👷 مدير المشغلين</option>
                                <option value="4">🚚 مدير الأسطول</option>
                                <option value="5">📍 مدير موقع</option>
                            </select>
                        </div>
                        <div>
                            <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                            <input type="text" name="phone" id="phone" placeholder="رقم الهاتف" required />
                        </div>

                        <div id="projectDiv" style="display:none;">
                            <label><i class="fas fa-project-diagram"></i> المشروع</label>
                            <select id="project_id" name="project_id" class="form-control">
                                <option value="">-- اختر المشروع --</option>
                                <?php
                                $sql = "SELECT id, name FROM project where status = '1' ORDER BY name ASC";
                                $result = mysqli_query($conn, $sql);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: center;">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-eraser"></i> مسح الحقول
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ المستخدم
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> قائمة المستخدمين</h5>
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
                        $query = "SELECT id, name, username, password, phone, role , project_id FROM users WHERE parent_id='0' AND role!='-1' ORDER BY id DESC";
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
                            $project_id = $row['project_id'];
                            $project_name = "";
                            $select_project = mysqli_query($conn, "SELECT name FROM `project` WHERE `id` = $project_id");
                            while ($project_row = mysqli_fetch_array($select_project)) {
                                $project_name = $project_row['name'];
                            }

                            if ($row['role'] == "5") {
                                $project = " (<font color='blue'>" . htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8') . "</font>)";
                            } else {
                                $project = "";
                            }

                            echo "<tr>";
                            echo "<td><strong>" . $i++ . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td><strong>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
                            echo "<td><span class='password-cell'>" . htmlspecialchars($row['password'] ,ENT_QUOTES, 'UTF-8')  . "</span></td>";
                            echo "<td><span class='role-badge role-" . $row['role'] . "'>" . (isset($roles[$row['role']]) ? $roles[$row['role']] : "غير معروف") . "</span>" . $project . "</td>";
                            echo "<td><i class='fas fa-phone' style='color:#667eea; margin-left:5px;'></i>" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>
                                <a href='javascript:void(0)' class='editBtn' 
                                   data-id='{$row['id']}' 
                                   data-name='" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-username='" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-phone='" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-role='{$row['role']}'
                                   data-project='{$row['project_id']}'
                                   title='تعديل'><i class='fas fa-edit'></i></a> | 
                                <a href='?delete={$row['id']}' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>
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
            const form = document.getElementById('projectForm');

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
                $('#phone').val($(this).data('phone'));
                $('#role').val($(this).data('role')).trigger('change');
                
                // تعبئة المشروع إذا كان الدور = 5
                setTimeout(function() {
                    var projectId = $('.editBtn:focus').data('project');
                    if (projectId) {
                        $('#project_id').val(projectId);
                    }
                }, 300);
                
                $('#password').val(""); // لا نملأ الحقل بكلمة المرور الحالية
                form.style.display = "block";
                
                // التمرير إلى النموذج
                $('html, body').animate({
                    scrollTop: $(form).offset().top - 20
                }, 500);
            });
        });
    </script>

</body>
</html>

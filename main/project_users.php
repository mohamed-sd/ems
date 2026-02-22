<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

$page_title = "إيكوبيشن | المستخدمين";

// معالجة إضافة/تعديل مستخدم عند إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $project = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
    $parent_id = intval($_SESSION['user']['id']);

    // تحقق من تكرار اسم المستخدم
    $check_query = "SELECT id FROM users WHERE username = '$username'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        header("Location: project_users.php?msg=اسم+المستخدم+موجود+مسبقاً+❌");
        exit;
    }

    // إضافة مستخدم جديد
    $sql = "INSERT INTO users (name, username, password, phone, role, project_id, parent_id, created_at, updated_at) 
            VALUES ('$name', '$username', '$password', '$phone', '$role', '$project', '$parent_id', NOW(), NOW())";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: project_users.php?msg=تم+إضافة+المستخدم+بنجاح+✅");
        exit;
    } else {
        header("Location: project_users.php?msg=حدث+خطأ+أثناء+الإضافة+❌");
        exit;
    }
}

include("../inheader.php");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<?php 
// include('../insidebar.php');
 ?>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users-cog"></i></div>
            إدارة المستخدمين
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة مستخدم جديد
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): 
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مستخدم -->
    <form id="projectForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مستخدم</h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user"></i> الاسم ثلاثي *</label>
                        <input type="text" name="name" id="name" placeholder="أدخل الاسم ثلاثي" required />
                    </div>
                    <div>
                        <label><i class="fas fa-at"></i> اسم المستخدم *</label>
                        <input type="text" name="username" id="username" placeholder="أدخل اسم المستخدم" required />
                    </div>
                    <div>
                        <label><i class="fas fa-lock"></i> كلمة المرور *</label>
                        <input type="password" name="password" id="password" placeholder="أدخل كلمة المرور" required />
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> رقم الهاتف *</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" required />
                    </div>
                    <div>
                        <label><i class="fas fa-shield-alt"></i> الصلاحية / الدور *</label>
                        <select name="role" id="role" required>
                            <option value="">-- اختر الصلاحية --</option>
                            <option value="6">📝 مدخل ساعات عمل</option>
                            <option value="7">✓ مراجع ساعات مورد</option>
                            <option value="8">✓ مراجع ساعات مشغل</option>
                            <option value="9">🔧 مراجع الأعطال</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> حفظ المستخدم
                    </button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('projectForm').style.display='none';">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة المستخدمين</h5>
        </div>
        <div class="card-body">
            <table id="usersTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>رقم الهاتف</th>
                        <th>الصلاحية</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب المستخدمين
                    $userid = $_SESSION['user']['id'];

                    $roles = array(
                        "6" => "📝 مدخل ساعات عمل",
                        "7" => "✓ مراجع ساعات مورد",
                        "8" => "✓ مراجع ساعات مشغل",
                        "9" => "🔧 مراجع الأعطال",
                    );

                    $query = "SELECT id, name, username, phone, role, created_at
                             FROM users WHERE parent_id = '$userid' ORDER BY id DESC";
                    
                    $result = mysqli_query($conn, $query);
                    $i = 1;

                    while ($row = mysqli_fetch_assoc($result)) {
                        $roleText = isset($roles[$row['role']]) ? $roles[$row['role']] : '<span style="color: #999;">غير معروف</span>';
                        $createdDate = date('Y-m-d', strtotime($row['created_at']));

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                        echo "<td><code style=\"background: #f0f2f8; padding: 4px 8px; border-radius: 6px;\">" . htmlspecialchars($row['username']) . "</code></td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . $roleText . "</td>";
                        echo "<td>" . $createdDate . "</td>";
                        echo "<td>
                                <div class='action-btns'>
                                    <a href='javascript:void(0)' 
                                       class='action-btn edit' 
                                       title='تعديل'>
                                        <i class='fas fa-edit'></i>
                                    </a>
                                    <a href='javascript:void(0)' 
                                       class='action-btn delete' 
                                       onclick=\"return confirm('هل أنت متأكد من حذف هذا المستخدم؟')\"
                                       title='حذف'>
                                        <i class='fas fa-trash'></i>
                                    </a>
                                </div>
                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- jQuery (Required first) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            $('#usersTable').DataTable({
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
                    "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // التحكم في إظهار وإخفاء الفورم
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');

        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                // تنظيف الحقول عند الإضافة
                if (projectForm.style.display === "block") {
                    $("#name").val("");
                    $("#username").val("");
                    $("#password").val("");
                    $("#phone").val("");
                    $("#role").val("");
                    $("#name").focus();
                    $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                }
            });
        }
    })();
</script>

</body>

</html>
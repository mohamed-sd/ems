<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | المستخدمين </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.css"/>
</head>
<body>

  <?php include('includes/sidebar.php'); ?>

 <div class="main">

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> إضافة مستخدم
    </a>

    <!-- فورم إضافة مورد -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <input type="text" name="name" placeholder=" الاسم ثلاثي" required />
        <input type="text" name="username" placeholder="اسم المستخدم" required />
        <input type="text" name="password" placeholder="كلمه المرور " required />
        <label class="form-label">الدور / الصلاحية</label>
  <select name="role" class="form-control" required>
    <option value="admin">مدير (Admin)</option>
    <option value="user">مشرف (User)</option>
  </select>


        <input type="text" name="phone" placeholder="رقم الهاتف" required />
       
        <br/>
        <button type="submit">حفظ المستخدم</button>
    </form>

    <br/><br/><br/>

    <!-- جدول الموردين -->
    <h3>قائمة المستخدمين</h3>
    <br/>
    <table id="suppliersTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">الاسم </th>
                <th style="text-align: right;">اسم المستخدم </th>
                <th style="text-align: right;">كلمه المرور  </th>
                <th style="text-align: right;">الدور   </th>


                <th style="text-align: right;">رقم الهاتف</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include 'config.php';
            
            // إضافة مورد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                $name     = $_POST['name'];
                $username = $_POST['username'];
                $password = $_POST['password']; // يفضل تشفيره لاحقاً
                 $phone    = $_POST['phone'];
                $role     = $_POST['role']; 
                mysqli_query($conn, "INSERT INTO users (name, username, password, phone, role, created_at, updated_at) 
            VALUES ('$name', '$username', '$password', '$phone', '$role', NOW(), NOW())");
            }

            // جلب المستخدمين
            $query = "SELECT id, name, username,password ,phone, role , created_at, updated_at FROM users ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['name']."</td>";
                echo "<td>".$row['username']."</td>";
                 echo "<td>".$row['password']."</td>";
                echo "<td>".$row['role']."</td>";

                echo "<td>".$row['phone']."</td>";
                echo "<td>
                        <a href='edit.php?id=".$row['id']."'>تعديل</a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | 
                        <a href='suppliers_details.php?id=".$row['id']."'>عرض</a>
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

<script>
(function() {
    // تشغيل DataTable بالعربية
    $(document).ready(function() {
        $('#suppliersTable').DataTable({
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
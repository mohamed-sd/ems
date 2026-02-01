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
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | الموردين</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    
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
        .main h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
        }
        
        /* Action Buttons Container */
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
        
        /* Modern Action Buttons */
        .aligin .add {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .aligin .add::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .aligin .add:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .aligin .add:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
        }
        
        /* Success Message */
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            text-align: center;
            font-weight: 600;
            border-right: 4px solid #28a745;
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.2);
            animation: slideInDown 0.5s ease;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Form Styling */
        #projectForm {
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            padding: 1.5rem;
            border: none;
        }
        
        .card-header h5 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Form Fields */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-grid input,
        .form-grid select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .form-grid input:focus,
        .form-grid select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }
        
        .form-grid button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .form-grid button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* DataTable Styling */
        table.dataTable thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            padding: 1rem;
            border: none;
        }
        
        table.dataTable tbody tr {
            transition: all 0.3s ease;
        }
        
        table.dataTable tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f0ff 100%);
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        table.dataTable tbody td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        /* Action Buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin: 0 0.25rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white !important;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white !important;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white !important;
        }
        
        .btn-contracts {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white !important;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        /* Stats in Table */
        .stat-cell {
            font-weight: 600;
            color: #667eea;
            font-size: 1.05rem;
        }
        
        /* DataTables Buttons */
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border: none !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            margin-left: 0.5rem !important;
            transition: all 0.3s ease !important;
        }
        
        .dt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
        }
    </style>
</head>
<body>
<?php include('../insidebar.php'); ?>

<div class="main">
    <h2><i class="fas fa-truck-loading"></i> إدارة الموردين</h2>
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> إضافة مورد
        </a>
    </div>

    
    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle" style="margin-left: 0.5rem;"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مورد -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit"></i> إضافة / تعديل مورد
                </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <input type="hidden" name="id" id="supplier_id" value="">
                    <input type="text" name="name" id="supplier_name" placeholder="اسم المورد" required />
                    <input type="text" name="phone" id="supplier_phone" placeholder="رقم الهاتف" required />
                    <select name="status" id="supplier_status" required>
                        <option value="">حالة المورد</option>
                        <option value="1">نشط</option>
                        <option value="0">معلق</option>
                    </select>
                    <button type="submit">
                        <i class="fas fa-save" style="margin-left: 0.5rem;"></i>
                        حفظ المورد
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list-alt"></i> قائمة الموردين
            </h5>
        </div>
        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-truck-loading"></i> اسم المورد</th>
                        <th><i class="fas fa-hard-hat"></i> عدد الآليات</th>
                        <th><i class="fas fa-file-contract"></i> عدد العقود</th>
                        <th><i class="fas fa-clock"></i> إجمالي الساعات المتعاقد عليها</th>
                        <th><i class="fas fa-phone"></i> رقم الهاتف</th>
                        <th><i class="fas fa-info-circle"></i> الحالة</th>
                        <th><i class="fas fa-cogs"></i> إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // جلب الموردين مع إجمالي الساعات
                    $query = "SELECT `id`, `name`, `phone`, `status` , 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = suppliers.id ) as 'equipments' ,
                      (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = suppliers.id ) as 'num_contracts',
                      (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = suppliers.id ) as 'total_hours'
                      FROM `suppliers` ORDER BY id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td><strong>" . $i++ . "</strong></td>";
                        echo "<td><strong style='color:#667eea'>" . $row['name'] . "</strong></td>";
                        echo "<td><span class='stat-cell'>" . $row['equipments'] . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['num_contracts'] . "</span></td>";
                        echo "<td><strong style='color:#28a745; font-size:1.1rem'>" . number_format($row['total_hours']) . " ساعة</strong></td>";
                        echo "<td><i class='fas fa-phone-alt' style='color:#667eea; margin-left:0.3rem;'></i>" . $row['phone'] . "</td>";

                        // الحالة بالألوان
                        if ($row['status'] == "1") {
                            echo "<td><span class='status-badge status-active'><i class='fas fa-check-circle' style='margin-left:0.3rem;'></i>نشط</span></td>";
                        } else {
                            echo "<td><span class='status-badge status-inactive'><i class='fas fa-times-circle' style='margin-left:0.3rem;'></i>معلق</span></td>";
                        }

                        echo "<td style='white-space:nowrap;'>
                        <a href='javascript:void(0)' 
                           class='editBtn action-btn btn-edit' 
                           data-id='" . $row['id'] . "' 
                           data-name='" . $row['name'] . "' 
                           data-phone='" . $row['phone'] . "' 
                           data-status='" . $row['status'] . "' 
                           title='تعديل'><i class='fas fa-edit'></i></a>
                        <a href='supplierscontracts.php?id=" . $row['id'] . "' class='action-btn btn-contracts' title='العقود'><i class='fas fa-file-contract'></i></a>
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
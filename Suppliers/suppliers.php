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
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
    <link rel="stylesheet" type="text/css" href="../assets/css/admin-style.css" />
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

        :root {
            --primary-color: #01072a;
            --secondary-color: #e2ae03;
            --dark-color: #2d2b22;
            --light-color: #f5f5f5;
            --border-color: #e0e0e0;
            --text-color: #010326;
            --gold-color: #debf0f;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --accent-color: #1a1a2e;
        }
        
        body {
            background: var(--light-color);
        }
        
        .main {
            padding: 2rem;
            background: var(--light-color);
            width: calc(100% - 250px);
        }

        @media (max-width: 768px) {
            .main {
                margin-right: 0;
                padding: 15px 10px;
            }
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        /* Page Title */
        .main h2 {
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 900;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .main h2 i {
            color: var(--secondary-color);
            font-size: 24px;
        }
        
        /* Action Buttons Container */
        .aligin {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px var(--shadow-color);
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Modern Action Buttons */
        .aligin .add {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px var(--shadow-color);
            position: relative;
            overflow: hidden;
            background: var(--gold-color);
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
        }
        
        /* Success Message */
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px var(--shadow-color);
            font-weight: 600;
            border-right: 4px solid #28a745;
            animation: slideDown 0.4s ease;
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
            box-shadow: 0 10px 40px var(--shadow-color);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            padding: 1.5rem;
            border: none;
        }
        
        .card-header h5 {
            color: white;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h5 i {
            color: var(--secondary-color);
            font-size: 18px;
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
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-weight: 500;
            background: white;
            color: var(--text-color);
        }
        
        .form-grid input:focus,
        .form-grid select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(226, 174, 3, 0.15);
            outline: none;
        }
        
        .form-grid button {
            background: var(--secondary-color);
            color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px var(--shadow-color);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-grid button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px var(--shadow-color);
        }
        
        /* DataTable Styling */
        .dataTables_wrapper {
            font-family: 'Cairo', sans-serif;
        }

        table.dataTable {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        table.dataTable thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            color: white;
            font-weight: 600;
            padding: 15px;
            text-align: center;
            border: none;
            font-size: 15px;
        }

        table.dataTable thead th:first-child {
            border-radius: 10px 0 0 10px;
        }

        table.dataTable thead th:last-child {
            border-radius: 0 10px 10px 0;
        }

        table.dataTable thead th i {
            color: var(--secondary-color);
            margin-left: 8px;
        }
        
        table.dataTable tbody tr {
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        table.dataTable tbody tr:hover {
            background: rgba(226, 174, 3, 0.08);
            transform: scale(1.01);
            box-shadow: 0 4px 15px var(--shadow-color);
        }
        
        table.dataTable tbody td {
            padding: 15px;
            vertical-align: middle;
            text-align: center;
            border: none;
            font-size: 14px;
        }
        
        /* Action Buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 8px;
            margin: 0 4px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 15px;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px) scale(1.15);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            color: white !important;
        }
        
        .btn-contracts {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #b89302 100%);
            color: white !important;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.2);
            color: #155724;
        }
        
        .status-inactive {
            background: rgba(220, 53, 69, 0.2);
            color: #721c24;
        }
        
        /* Stats in Table */
        .stat-cell {
            background-color: var(--accent-color);
            padding: 4px 14px;
            border-radius: 30px;
            width: fit-content;
            font-weight: 800;
            color: var(--secondary-color);
            font-size: 1rem;
        }
        
        /* DataTables Buttons */
        .dt-buttons {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .dt-button {
            background: var(--secondary-color) !important;
            color: var(--primary-color) !important;
            border: none !important;
            padding: 10px 20px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px var(--shadow-color) !important;
            font-family: 'Cairo', sans-serif !important;
        }
        
        .dt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px var(--shadow-color) !important;
        }

        .dt-button.active {
            background: var(--primary-color) !important;
            color: white !important;
        }

        /* Pagination */
        .dataTables_paginate .paginate_button {
            padding: 8px 12px;
            margin: 2px;
            border-radius: 6px;
            background: white;
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        /* Search Box */
        .dataTables_filter input {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .dataTables_filter input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(226, 174, 3, 0.15);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main {
                padding: 1rem;
            }

            .main h2 {
                font-size: 18px;
                margin-bottom: 1rem;
            }

            .aligin {
                padding: 1rem;
            }

            .aligin .add {
                width: 100%;
                justify-content: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1rem;
            }

            table.dataTable {
                font-size: 12px;
            }

            table.dataTable thead th {
                padding: 10px 5px;
            }

            table.dataTable tbody td {
                padding: 10px 5px;
            }

            .action-btn {
                width: 30px;
                height: 30px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
<?php include('../insidebar.php'); ?>

<div class="main">
    <h2><i class="fas fa-truck-loading"></i> إدارة الموردين</h2>
    <div class="aligin">
        <a href="javascript:void(0)" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> إضافة مورد جديد
        </a>
    </div>

    
    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل مورد -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-edit"></i> إضافة / تعديل مورد
                </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <input type="hidden" name="id" id="supplier_id" value="">
                    <input type="text" name="name" id="supplier_name" placeholder="اسم المورد" required />
                    <input type="text" name="phone" id="supplier_phone" placeholder="رقم الهاتف" required />
                    <select name="status" id="supplier_status" required>
                        <option value="">اختر الحالة</option>
                        <option value="1">نشط</option>
                        <option value="0">معلق</option>
                    </select>
                    <button type="submit">
                        <i class="fas fa-save"></i>
                        حفظ المورد
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i> قائمة الموردين
            </h5>
        </div>
        <div class="card-body">
            <table id="projectsTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-truck-loading"></i> اسم المورد</th>
                        <th><i class="fas fa-cogs"></i> عدد الآليات</th>
                        <th><i class="fas fa-file-contract"></i> عدد العقود</th>
                        <th><i class="fas fa-clock"></i> الساعات المتعاقد عليها</th>
                        <th><i class="fas fa-phone"></i> رقم الهاتف</th>
                        <th><i class="fas fa-info-circle"></i> الحالة</th>
                        <th><i class="fas fa-sliders-h"></i> الإجراءات</th>
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
                        echo "<td><strong style='color:var(--primary-color)'>" . $row['name'] . "</strong></td>";
                        echo "<td><span class='stat-cell'>" . $row['equipments'] . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['num_contracts'] . "</span></td>";
                        echo "<td><strong style='color:#28a745; font-size:1rem'>" . number_format($row['total_hours']) . " ساعة</strong></td>";
                        echo "<td><i class='fas fa-phone' style='color:var(--secondary-color); margin-left:6px;'></i>" . $row['phone'] . "</td>";

                        // الحالة بالألوان
                        if ($row['status'] == "1") {
                            echo "<td><span class='status-badge status-active'><i class='fas fa-check-circle' style='margin-left:6px;'></i>نشط</span></td>";
                        } else {
                            echo "<td><span class='status-badge status-inactive'><i class='fas fa-times-circle' style='margin-left:6px;'></i>معلق</span></td>";
                        }

                        echo "<td>
                        <a href='javascript:void(0)' 
                           class='editBtn action-btn btn-edit' 
                           data-id='" . $row['id'] . "' 
                           data-name='" . addslashes($row['name']) . "' 
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
                    { extend: 'copy', text: '📋 نسخ' },
                    { extend: 'excel', text: '📊 Excel' },
                    { extend: 'csv', text: '📄 CSV' },
                    { extend: 'pdf', text: '📕 PDF' },
                    { extend: 'print', text: '🖨️ طباعة' }
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
<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // المعلومات الأساسية
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $supplier_code = mysqli_real_escape_string($conn, trim($_POST['supplier_code']));
    $supplier_type = mysqli_real_escape_string($conn, $_POST['supplier_type']);
    $dealing_nature = mysqli_real_escape_string($conn, $_POST['dealing_nature']);
    $equipment_types = isset($_POST['equipment_types']) ? implode(', ', $_POST['equipment_types']) : '';
    $equipment_types = mysqli_real_escape_string($conn, $equipment_types);
    
    // البيانات القانونية
    $commercial_registration = mysqli_real_escape_string($conn, trim($_POST['commercial_registration']));
    $identity_type = mysqli_real_escape_string($conn, $_POST['identity_type']);
    $identity_number = mysqli_real_escape_string($conn, trim($_POST['identity_number']));
    $identity_expiry_date = !empty($_POST['identity_expiry_date']) ? mysqli_real_escape_string($conn, $_POST['identity_expiry_date']) : null;
    
    // البيانات التواصلية
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $phone_alternative = mysqli_real_escape_string($conn, trim($_POST['phone_alternative']));
    $full_address = mysqli_real_escape_string($conn, trim($_POST['full_address']));
    $contact_person_name = mysqli_real_escape_string($conn, trim($_POST['contact_person_name']));
    $contact_person_phone = mysqli_real_escape_string($conn, trim($_POST['contact_person_phone']));
    $financial_registration_status = mysqli_real_escape_string($conn, $_POST['financial_registration_status']);
    
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($id > 0) {
        // تحديث
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $sql = "UPDATE suppliers SET 
            name='$name', 
            supplier_code='$supplier_code',
            supplier_type='$supplier_type',
            dealing_nature='$dealing_nature',
            equipment_types='$equipment_types',
            commercial_registration='$commercial_registration',
            identity_type='$identity_type',
            identity_number='$identity_number',
            identity_expiry_date=$identity_expiry_sql,
            email='$email',
            phone='$phone',
            phone_alternative='$phone_alternative',
            full_address='$full_address',
            contact_person_name='$contact_person_name',
            contact_person_phone='$contact_person_phone',
            financial_registration_status='$financial_registration_status',
            status='$status' 
            WHERE id=$id";
        mysqli_query($conn, $sql);
        header("Location: suppliers.php?msg=تم+تعديل+المورد+بنجاح+✅");
        exit;
    } else {
        // إضافة
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        $sql = "INSERT INTO suppliers 
            (name, supplier_code, supplier_type, dealing_nature, equipment_types, 
             commercial_registration, identity_type, identity_number, identity_expiry_date,
             email, phone, phone_alternative, full_address, contact_person_name, 
             contact_person_phone, financial_registration_status, status) 
            VALUES 
            ('$name', '$supplier_code', '$supplier_type', '$dealing_nature', '$equipment_types',
             '$commercial_registration', '$identity_type', '$identity_number', $identity_expiry_sql,
             '$email', '$phone', '$phone_alternative', '$full_address', '$contact_person_name',
             '$contact_person_phone', '$financial_registration_status', '$status')";
        mysqli_query($conn, $sql);
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
    <link rel="stylesheet" href="../assets/css/main_admin_style.css" />
    
    <style>
        .form-section {
            background: var(--bg);
            padding: 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1.2rem;
            border: 1.5px solid var(--border);
        }

        .form-section h6 {
            color: var(--txt);
            font-weight: 800;
            font-size: .95rem;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section h6 i {
            color: var(--gold);
            font-size: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-weight: 700;
            color: var(--sub);
            font-size: .8rem;
        }

        .form-group label .required {
            color: var(--red);
            font-weight: 700;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: .88rem;
            font-weight: 500;
            color: var(--txt);
            background: var(--bg);
            transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
            outline: none;
            font-family: 'Cairo', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--gold);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(232,184,0,.14);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            padding: 12px;
            background: #fff;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: var(--bg);
            border-radius: 10px;
            cursor: pointer;
            transition: all var(--ease);
            border: 1.5px solid transparent;
        }

        .checkbox-label:hover {
            border-color: rgba(232,184,0,.3);
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--gold);
        }

        .checkbox-label span {
            font-weight: 500;
            color: var(--txt);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 18px;
            padding-top: 12px;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--navy), var(--navy-l));
            color: #fff;
            border: none;
            padding: 12px 26px;
            border-radius: 50px;
            font-weight: 700;
            font-size: .9rem;
            cursor: pointer;
            transition: all var(--ease);
            box-shadow: 0 4px 16px rgba(12,28,62,.22);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(12,28,62,.28);
        }

        .btn-cancel {
            background: rgba(100,116,139,.12);
            color: var(--sub);
            border: none;
            padding: 12px 26px;
            border-radius: 50px;
            font-weight: 700;
            font-size: .9rem;
            cursor: pointer;
            transition: all var(--ease);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: var(--sub);
            color: #fff;
            transform: translateY(-2px);
        }

        .info-section {
            background: var(--bg);
            padding: 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1.2rem;
            border: 1.5px solid var(--border);
        }

        .section-title {
            color: var(--txt);
            font-weight: 800;
            font-size: .95rem;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--gold);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 12px;
            background: #fff;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
        }

        .info-label {
            font-weight: 700;
            color: var(--sub);
            font-size: .75rem;
            text-transform: uppercase;
        }

        .info-value {
            font-weight: 700;
            color: var(--txt);
            font-size: .9rem;
        }

        .stat-cell {
            background: var(--navy);
            padding: 4px 12px;
            border-radius: 50px;
            width: fit-content;
            font-weight: 800;
            color: var(--gold);
            font-size: .85rem;
        }

        .phone-icon {
            color: var(--gold);
            margin-left: 6px;
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }

            .checkbox-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php 
// include('../insidebar.php'); 
?>

<div class="main">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-truck-loading"></i></div>
            <h1 class="page-title">إدارة الموردين</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add">
                <i class="fa fa-plus-circle"></i> إضافة مورد جديد
            </a>
        </div>
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
                <input type="hidden" name="id" id="supplier_id" value="">
                
                <!-- 1. المعلومات الأساسية والتعريفية -->
                <div class="form-section">
                    <h6><i class="fas fa-info-circle"></i> المعلومات الأساسية والتعريفية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اسم المورد <span class="required">*</span></label>
                            <input type="text" name="name" id="supplier_name" required />
                        </div>
                        
                        <div class="form-group">
                            <label>الرمز/الكود للمورد</label>
                            <input type="text" name="supplier_code" id="supplier_code" />
                        </div>
                        
                        <div class="form-group">
                            <label>نوع المورد <span class="required">*</span></label>
                            <select name="supplier_type" id="supplier_type" required>
                                <option value="">-- اختر --</option>
                                <option value="فرد">فرد</option>
                                <option value="شركة">شركة</option>
                                <option value="وسيط">وسيط</option>
                                <option value="مالك">مالك</option>
                                <option value="جهة حكومية">جهة حكومية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>طبيعة التعامل <span class="required">*</span></label>
                            <select name="dealing_nature" id="dealing_nature" required>
                                <option value="">-- اختر --</option>
                                <option value="متعاقد مباشر">متعاقد مباشر</option>
                                <option value="وسيط">وسيط</option>
                                <option value="مورد معدات مباشر (مالك)">مورد معدات مباشر (مالك)</option>
                                <option value="وكيل توزيع">وكيل توزيع</option>
                                <option value="تاجر وسيط">تاجر وسيط</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>المعدات (يمكن اختيار أكثر من نوع)</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="حفارات">
                                <span>حفارات</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="مكنات تخريم">
                                <span>مكنات تخريم</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="دوازر">
                                <span>دوازر</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="شاحنات قلابة">
                                <span>شاحنات قلابة</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="شاحنات تناكر">
                                <span>شاحنات تناكر</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="جرافات">
                                <span>جرافات</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="equipment_types[]" value="معدات معالجة">
                                <span>معدات معالجة</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. البيانات القانونية والتعريفية -->
                <div class="form-section">
                    <h6><i class="fas fa-file-contract"></i> البيانات القانونية والتعريفية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>رقم التسجيل التجاري/الرخصة</label>
                            <input type="text" name="commercial_registration" id="commercial_registration" />
                        </div>
                        
                        <div class="form-group">
                            <label>نوع الهوية</label>
                            <select name="identity_type" id="identity_type">
                                <option value="">-- اختر --</option>
                                <option value="بطاقة هوية وطنية">بطاقة هوية وطنية</option>
                                <option value="جواز سفر">جواز سفر</option>
                                <option value="رقم تسجيل تجاري">رقم تسجيل تجاري</option>
                                <option value="رقم ضريبة دخل">رقم ضريبة دخل</option>
                                <option value="رخصة عمل">رخصة عمل</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>رقم الهوية/التسجيل</label>
                            <input type="text" name="identity_number" id="identity_number" />
                        </div>
                        
                        <div class="form-group">
                            <label>تاريخ انتهاء الهوية</label>
                            <input type="date" name="identity_expiry_date" id="identity_expiry_date" />
                        </div>
                    </div>
                </div>

                <!-- 3. البيانات التواصلية -->
                <div class="form-section">
                    <h6><i class="fas fa-address-book"></i> البيانات التواصلية</h6>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>البريد الإلكتروني الرئيسي</label>
                            <input type="email" name="email" id="supplier_email" />
                        </div>
                        
                        <div class="form-group">
                            <label>رقم الهاتف الأساسي <span class="required">*</span></label>
                            <input type="text" name="phone" id="supplier_phone" required />
                        </div>
                        
                        <div class="form-group">
                            <label>رقم هاتف بديل</label>
                            <input type="text" name="phone_alternative" id="phone_alternative" />
                        </div>
                        
                        <div class="form-group">
                            <label>اسم جهة الاتصال الأساسية</label>
                            <input type="text" name="contact_person_name" id="contact_person_name" />
                        </div>
                        
                        <div class="form-group">
                            <label>هاتف جهة الاتصال</label>
                            <input type="text" name="contact_person_phone" id="contact_person_phone" />
                        </div>
                        
                        <div class="form-group">
                            <label>حالة التسجيل المالي</label>
                            <select name="financial_registration_status" id="financial_registration_status">
                                <option value="">-- اختر --</option>
                                <option value="مسجل رسميا">مسجل رسميا</option>
                                <option value="غير مسجل">غير مسجل</option>
                                <option value="تحت التسجيل">تحت التسجيل</option>
                                <option value="معفى من التسجيل">معفى من التسجيل</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>العنوان الكامل</label>
                            <textarea name="full_address" id="full_address" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>الحالة <span class="required">*</span></label>
                            <select name="status" id="supplier_status" required>
                                <option value="">اختر الحالة</option>
                                <option value="1">نشط</option>
                                <option value="0">معلق</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        حفظ المورد
                    </button>
                    <button type="button" class="btn-cancel" onclick="toggleForm()">
                        <i class="fas fa-times"></i>
                        إلغاء
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
            <div class="table-container">
            <table id="projectsTable" class="display" style="width:100%;">
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
                    $query = "SELECT s.*, 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = s.id ) as 'equipments' ,
                      (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id ) as 'num_contracts',
                      (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = s.id ) as 'total_hours'
                      FROM `suppliers` s ORDER BY s.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        // إعداد data attributes للتعديل
                        $data_attrs = "data-id='" . $row['id'] . "' " .
                            "data-name='" . htmlspecialchars($row['name'], ENT_QUOTES) . "' " .
                            "data-supplier_code='" . htmlspecialchars($row['supplier_code'], ENT_QUOTES) . "' " .
                            "data-supplier_type='" . htmlspecialchars($row['supplier_type'], ENT_QUOTES) . "' " .
                            "data-dealing_nature='" . htmlspecialchars($row['dealing_nature'], ENT_QUOTES) . "' " .
                            "data-equipment_types='" . htmlspecialchars($row['equipment_types'], ENT_QUOTES) . "' " .
                            "data-commercial_registration='" . htmlspecialchars($row['commercial_registration'], ENT_QUOTES) . "' " .
                            "data-identity_type='" . htmlspecialchars($row['identity_type'], ENT_QUOTES) . "' " .
                            "data-identity_number='" . htmlspecialchars($row['identity_number'], ENT_QUOTES) . "' " .
                            "data-identity_expiry_date='" . htmlspecialchars($row['identity_expiry_date'], ENT_QUOTES) . "' " .
                            "data-email='" . htmlspecialchars($row['email'], ENT_QUOTES) . "' " .
                            "data-phone='" . htmlspecialchars($row['phone'], ENT_QUOTES) . "' " .
                            "data-phone_alternative='" . htmlspecialchars($row['phone_alternative'], ENT_QUOTES) . "' " .
                            "data-full_address='" . htmlspecialchars($row['full_address'], ENT_QUOTES) . "' " .
                            "data-contact_person_name='" . htmlspecialchars($row['contact_person_name'], ENT_QUOTES) . "' " .
                            "data-contact_person_phone='" . htmlspecialchars($row['contact_person_phone'], ENT_QUOTES) . "' " .
                            "data-financial_registration_status='" . htmlspecialchars($row['financial_registration_status'], ENT_QUOTES) . "' " .
                            "data-status='" . $row['status'] . "'";
                        
                        echo "<tr>";
                        echo "<td><strong>" . $i++ . "</strong></td>";
                        echo "<td><span class='client-name-link'>" . htmlspecialchars($row['name']) . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['equipments'] . "</span></td>";
                        echo "<td><span class='stat-cell'>" . $row['num_contracts'] . "</span></td>";
                        echo "<td><span class='status-active'>" . number_format($row['total_hours']) . " ساعة</span></td>";
                        echo "<td><i class='fas fa-phone phone-icon'></i>" . htmlspecialchars($row['phone']) . "</td>";

                        // الحالة بالألوان
                        if ($row['status'] == "1") {
                                     echo "<td><span class='status-active'><i class='fas fa-check-circle' style='margin-left:6px;'></i>نشط</span></td>";
                        } else {
                                     echo "<td><span class='status-inactive'><i class='fas fa-times-circle' style='margin-left:6px;'></i>معلق</span></td>";
                        }

                                echo "<td>
                                <div class='action-btns'>
                                <a href='javascript:void(0)' 
                                    class='viewBtn action-btn view' 
                                    $data_attrs
                                    title='عرض التفاصيل'><i class='fas fa-eye'></i></a>
                                <a href='javascript:void(0)' 
                                    class='editBtn action-btn edit' 
                                    $data_attrs
                                    title='تعديل'><i class='fas fa-edit'></i></a>
                                <a href='supplierscontracts.php?id=" . $row['id'] . "' class='action-btn contracts' title='العقود'><i class='fas fa-file-contract'></i></a>
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
    
    <!-- Modal عرض تفاصيل المورد -->
    <div id="viewSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>
                    <i class="fas fa-truck-loading"></i>
                    تفاصيل المورد
                </h5>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- المعلومات الأساسية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-info-circle"></i> المعلومات الأساسية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">اسم المورد:</span>
                            <span class="info-value" id="view_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الرمز/الكود:</span>
                            <span class="info-value" id="view_supplier_code">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نوع المورد:</span>
                            <span class="info-value" id="view_supplier_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">طبيعة التعامل:</span>
                            <span class="info-value" id="view_dealing_nature">-</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">المعدات:</span>
                            <span class="info-value" id="view_equipment_types">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- البيانات القانونية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-file-contract"></i> البيانات القانونية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">رقم التسجيل التجاري:</span>
                            <span class="info-value" id="view_commercial_registration">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">نوع الهوية:</span>
                            <span class="info-value" id="view_identity_type">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم الهوية:</span>
                            <span class="info-value" id="view_identity_number">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ انتهاء الهوية:</span>
                            <span class="info-value" id="view_identity_expiry_date">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- البيانات التواصلية -->
                <div class="info-section">
                    <h5 class="section-title"><i class="fas fa-address-book"></i> البيانات التواصلية</h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">البريد الإلكتروني:</span>
                            <span class="info-value" id="view_email">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم الهاتف الأساسي:</span>
                            <span class="info-value" id="view_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">رقم هاتف بديل:</span>
                            <span class="info-value" id="view_phone_alternative">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">جهة الاتصال:</span>
                            <span class="info-value" id="view_contact_person_name">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">هاتف جهة الاتصال:</span>
                            <span class="info-value" id="view_contact_person_phone">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">حالة التسجيل المالي:</span>
                            <span class="info-value" id="view_financial_registration_status">-</span>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">العنوان الكامل:</span>
                            <span class="info-value" id="view_full_address">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الحالة:</span>
                            <span class="info-value" id="view_status">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1.5rem; border-top: 2px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeViewModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
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
            const $this = $(this);
            
            // البيانات الأساسية
            $("#supplier_id").val($this.data("id"));
            $("#supplier_name").val($this.data("name"));
            $("#supplier_code").val($this.data("supplier_code"));
            $("#supplier_type").val($this.data("supplier_type"));
            $("#dealing_nature").val($this.data("dealing_nature"));
            
            // المعدات (checkbox)
            const equipmentTypes = $this.data("equipment_types") ? $this.data("equipment_types").toString().split(', ') : [];
            $("input[name='equipment_types[]']").prop("checked", false);
            equipmentTypes.forEach(function(type) {
                $("input[name='equipment_types[]'][value='" + type.trim() + "']").prop("checked", true);
            });
            
            // البيانات القانونية
            $("#commercial_registration").val($this.data("commercial_registration"));
            $("#identity_type").val($this.data("identity_type"));
            $("#identity_number").val($this.data("identity_number"));
            $("#identity_expiry_date").val($this.data("identity_expiry_date"));
            
            // البيانات التواصلية
            $("#supplier_email").val($this.data("email"));
            $("#supplier_phone").val($this.data("phone"));
            $("#phone_alternative").val($this.data("phone_alternative"));
            $("#full_address").val($this.data("full_address"));
            $("#contact_person_name").val($this.data("contact_person_name"));
            $("#contact_person_phone").val($this.data("contact_person_phone"));
            $("#financial_registration_status").val($this.data("financial_registration_status"));
            $("#supplier_status").val($this.data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });
        
        // عند الضغط على زر عرض التفاصيل
        $(document).on("click", ".viewBtn", function () {
            const $this = $(this);
            
            // البيانات الأساسية
            $("#view_name").text($this.data("name") || "-");
            $("#view_supplier_code").text($this.data("supplier_code") || "-");
            $("#view_supplier_type").text($this.data("supplier_type") || "-");
            $("#view_dealing_nature").text($this.data("dealing_nature") || "-");
            $("#view_equipment_types").text($this.data("equipment_types") || "-");
            
            // البيانات القانونية
            $("#view_commercial_registration").text($this.data("commercial_registration") || "-");
            $("#view_identity_type").text($this.data("identity_type") || "-");
            $("#view_identity_number").text($this.data("identity_number") || "-");
            $("#view_identity_expiry_date").text($this.data("identity_expiry_date") || "-");
            
            // البيانات التواصلية
            $("#view_email").text($this.data("email") || "-");
            $("#view_phone").text($this.data("phone") || "-");
            $("#view_phone_alternative").text($this.data("phone_alternative") || "-");
            $("#view_full_address").text($this.data("full_address") || "-");
            $("#view_contact_person_name").text($this.data("contact_person_name") || "-");
            $("#view_contact_person_phone").text($this.data("contact_person_phone") || "-");
            $("#view_financial_registration_status").text($this.data("financial_registration_status") || "-");
            
            const status = $this.data("status") === "1" ? "نشط ✅" : "معلق ⏸️";
            $("#view_status").text(status);
            
            $("#viewSupplierModal").fadeIn(300);
        });
        
        // دالة closeViewModal لإغلاق Modal
        window.closeViewModal = function() {
            $("#viewSupplierModal").fadeOut(300);
        };
        
        // إغلاق Modal عند الضغط خارج المحتوى
        $(document).on("click", "#viewSupplierModal", function(e) {
            if (e.target.id === "viewSupplierModal") {
                closeViewModal();
            }
        });
        
        // دالة toggleForm لإظهار/إخفاء النموذج
        window.toggleForm = function() {
            var form = $("#projectForm");
            if (form.is(":visible")) {
                form.slideUp();
            } else {
                // مسح جميع الحقول
                $("#supplier_id").val("");
                $("#supplier_name").val("");
                $("#supplier_code").val("");
                $("#supplier_type").val("");
                $("#dealing_nature").val("");
                $("input[name='equipment_types[]']").prop("checked", false);
                $("#commercial_registration").val("");
                $("#identity_type").val("");
                $("#identity_number").val("");
                $("#identity_expiry_date").val("");
                $("#supplier_email").val("");
                $("#supplier_phone").val("");
                $("#phone_alternative").val("");
                $("#full_address").val("");
                $("#contact_person_name").val("");
                $("#contact_person_phone").val("");
                $("#financial_registration_status").val("");
                $("#supplier_status").val("");
                form.slideDown();
                $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
            }
        };
    })();
</script>

</body>

</html>
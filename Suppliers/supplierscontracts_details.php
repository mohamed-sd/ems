<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | تفاصيل عقد المورد</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    
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
        .main h3 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
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
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
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
            box-shadow: 0 6px 25px rgba(0,0,0,0.25);
        }
        
        .aligin .add:active {
            transform: translateY(-1px);
        }
        
        #renewalBtn {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        #settlementBtn {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
        }
        
        #pauseBtn {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        
        #resumeBtn {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }
        
        #terminateBtn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        #mergeBtn {
            background: linear-gradient(135deg, #e83e8c 0%, #d63384 100%);
        }
        
        #completeBtn {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
        }
        
        /* Report Container */
        .report {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        /* Info Cards Grid */
        .info-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border-right: 5px solid;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .info-card.primary { border-right-color: #667eea; }
        .info-card.success { border-right-color: #28a745; }
        .info-card.warning { border-right-color: #ffc107; }
        .info-card.danger { border-right-color: #dc3545; }
        .info-card.info { border-right-color: #17a2b8; }
        
        .info-card h5 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-card h5 i {
            font-size: 1.3rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-value {
            font-weight: 500;
            color: #212529;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        
        .status-badge.active {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .status-badge.inactive {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        /* Tables */
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .modern-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .modern-table thead th {
            padding: 1rem;
            font-weight: 700;
            text-align: center;
            font-size: 1rem;
        }
        
        .modern-table tbody tr {
            transition: all 0.3s ease;
            background: white;
        }
        
        .modern-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .modern-table tbody td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            font-weight: 500;
        }
        
        /* Modals Enhancement */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            border: none;
            padding: 1.5rem;
            background: #f8f9fa;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
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
        
        .info-card, .modern-table {
            animation: fadeInUp 0.6s ease;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .aligin {
                justify-content: center;
            }
            
            .aligin .add {
                flex: 1 1 45%;
            }
            
            .info-cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">

    <h3><i class="fas fa-file-contract"></i> تفاصيل عقد المورد</h3>

    <!-- أزرار الإجراءات -->
    <div class="aligin">
        <button class="add" id="renewalBtn" title="تجديد مدة العقد">
            <i class="fas fa-sync-alt"></i> تجديد العقد
        </button>
        <button class="add" id="settlementBtn" title="تسوية الساعات المتبقية">
            <i class="fas fa-balance-scale"></i> تسوية
        </button>
        <button class="add" id="pauseBtn" title="إيقاف مؤقت للعقد">
            <i class="fas fa-pause-circle"></i> إيقاف
        </button>
        <button class="add" id="resumeBtn" title="استئناف العقد المتوقف">
            <i class="fas fa-play-circle"></i> استئناف
        </button>
        <button class="add" id="terminateBtn" title="إنهاء العقد">
            <i class="fas fa-times-circle"></i> إنهاء
        </button>
        <button class="add" id="mergeBtn" title="دمج هذا العقد مع عقد آخر">
            <i class="fas fa-object-group"></i> دمج
        </button>
        <button class="add" id="completeBtn" title="تسجيل انتهاء العقد">
            <i class="fas fa-check-circle"></i> انتهاء العقد
        </button>
    </div>

<?php
include '../config.php';

$contract_id = intval($_GET['id']);

$sql = "SELECT 
            sc.id, sc.supplier_id, sc.project_id, sc.mine_id, sc.project_contract_id, sc.contract_signing_date, sc.grace_period_days, sc.contract_duration_months, sc.contract_duration_days,
            sc.actual_start, sc.actual_end, sc.transportation, sc.accommodation, sc.place_for_living, 
            sc.workshop, sc.hours_monthly_target, sc.forecasted_contracted_hours, sc.created_at, sc.updated_at,
            sc.daily_work_hours, sc.daily_operators, sc.first_party, sc.second_party, 
            sc.witness_one, sc.witness_two, sc.status, sc.pause_reason, sc.pause_date, sc.resume_date, sc.termination_type, sc.termination_reason, sc.merged_with,
            sc.equip_shifts_contract, sc.shift_contract, sc.equip_total_contract_daily, sc.total_contract_permonth, sc.total_contract_units,
            sc.price_currency_contract, sc.paid_contract, sc.payment_time, sc.guarantees, sc.payment_date,
            s.name AS supplier_name,
            op.name AS project_name,
            m.mine_name,
            m.mine_code
        FROM supplierscontracts sc
        LEFT JOIN suppliers s ON sc.supplier_id = s.id
        LEFT JOIN project op ON sc.project_id = op.id
        LEFT JOIN contracts c ON sc.project_contract_id = c.id
        LEFT JOIN mines m ON c.mine_id = m.id
        WHERE sc.id = $contract_id
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("خطأ في الاستعلام: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($result)) {

    // حساب المدة المتبقية من العقد باعتماد تاريخ اليوم وتاريخ الانتهاء
    $today = new DateTime();
    $actual_end_date = new DateTime($row['actual_end']);
    $interval = $today->diff($actual_end_date);
    $remaining_days = (int)$interval->format('%r%a');  




    // تحديد لون الحالة
    $status_color = 'green';
    $status_text = 'ساري';
    if (isset($row['status'])) {
        if ($row['status'] == 1) {
            $status_color = 'green';
            $status_text = 'ساري';
        } else {
            $status_color = 'red';
            $status_text = 'غير ساري';
        }
    } else {
        $row['status'] = 1;
    }
?>
    <!-- بطاقات ملخص العقد -->
    <div class="info-cards-grid">
        <!-- بطاقة معلومات المورد -->
        <div class="info-card" style="border-right-color: #ff6b6b;">
            <h5><i class="fas fa-industry"></i> معلومات المورد</h5>
            <div class="info-item">
                <span class="info-label">اسم المورد</span>
                <span class="info-value"><?php echo htmlspecialchars($row['supplier_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    <?php 
                    echo htmlspecialchars($row['project_name']);
                    if (!empty($row['mine_name'])) {
                        echo ' - ' . htmlspecialchars($row['mine_name']);
                        if (!empty($row['mine_code'])) {
                            echo ' (' . htmlspecialchars($row['mine_code']) . ')';
                        }
                    }
                    if (!empty($row['project_contract_id'])) {
                        echo ' - عقد #' . htmlspecialchars($row['project_contract_id']);
                    }
                    ?>
                </span>
            </div>
        </div>

        <!-- بطاقة الحالة -->
        <div class="info-card <?php echo ($row['status'] == 1) ? 'success' : 'danger'; ?>">
            <h5><i class="fas fa-info-circle"></i> حالة العقد</h5>
            <div class="text-center py-3">
                <span class="status-badge <?php echo ($row['status'] == 1) ? 'active' : 'inactive'; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
        </div>

        <!-- بطاقة المدة -->
        <div class="info-card primary">
            <h5><i class="fas fa-calendar-alt"></i> مدة العقد</h5>
            <div class="info-item">
                <span class="info-label">إجمالي المدة</span>
                <span class="info-value"><?php echo $row['contract_duration_days']; ?> يوم</span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-hourglass-half"></i> المتبقي</span>
                <span class="info-value" style="color: <?php echo $remaining_days > 30 ? '#28a745' : ($remaining_days > 0 ? '#ffc107' : '#dc3545'); ?>; font-weight: 700;">
                    <?php echo $remaining_days; ?> يوم
                </span>
            </div>
        </div>

        <!-- بطاقة التواريخ -->
        <div class="info-card info">
            <h5><i class="fas fa-calendar-check"></i> التواريخ الأساسية</h5>
            <div class="info-item">
                <span class="info-label">التوقيع</span>
                <span class="info-value"><?php echo $row['contract_signing_date']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">البدء الفعلي</span>
                <span class="info-value"><?php echo $row['actual_start']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الانتهاء المتوقع</span>
                <span class="info-value"><?php echo $row['actual_end']; ?></span>
            </div>
        </div>

        <!-- بطاقة الساعات -->
        <div class="info-card warning">
            <h5><i class="fas fa-clock"></i> الساعات التعاقدية</h5>
            <div class="info-item">
                <span class="info-label">الهدف الشهري</span>
                <span class="info-value"><?php echo $row['hours_monthly_target'] * 30; ?> ساعة</span>
            </div>
            <div class="info-item">
                <span class="info-label">الساعات المتوقعة</span>
                <span class="info-value"><?php echo $row['forecasted_contracted_hours']; ?> ساعة</span>
            </div>
            <div class="info-item">
                <span class="info-label">ساعات العمل اليومية</span>
                <span class="info-value"><?php echo $row['daily_work_hours']; ?> ساعة</span>
            </div>
        </div>

        <!-- بطاقة البيانات الإضافية للعقد -->
        <div class="info-card" style="display: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h5 style="color: white;"><i class="fas fa-file-contract"></i> بيانات العقد الإضافية</h5>
            <div class="info-item">
                <span class="info-label" style="color: rgba(255,255,255,0.9);">عدد الورديات</span>
                <span class="info-value" style="color: white; font-weight: 700;"><?php echo isset($row['equip_shifts_contract']) ? $row['equip_shifts_contract'] : 0; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label" style="color: rgba(255,255,255,0.9);">ساعات الوردية</span>
                <span class="info-value" style="color: white; font-weight: 700;"><?php echo isset($row['shift_contract']) ? $row['shift_contract'] : 0; ?> ساعة</span>
            </div>
            <div class="info-item">
                <span class="info-label" style="color: rgba(255,255,255,0.9);">الوحدات يومياً</span>
                <span class="info-value" style="color: white; font-weight: 700;"><?php echo isset($row['equip_total_contract_daily']) ? $row['equip_total_contract_daily'] : 0; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label" style="color: rgba(255,255,255,0.9);">وحدات الشهر</span>
                <span class="info-value" style="color: white; font-weight: 700;"><?php echo isset($row['total_contract_permonth']) ? $row['total_contract_permonth'] : 0; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label" style="color: rgba(255,255,255,0.9);">إجمالي الوحدات</span>
                <span class="info-value" style="color: white; font-weight: 700;"><?php echo isset($row['total_contract_units']) ? $row['total_contract_units'] : 0; ?></span>
            </div>
        </div>
    </div>

    <!-- بطاقات تفاصيل العقد -->
    <div class="info-cards-grid">
        <!-- معلومات المشروع -->
        <div class="info-card primary">
            <h5>
                <i class="fas fa-project-diagram"></i> معلومات المشروع
                <button class="btn btn-sm btn-outline-primary ms-auto" id="editProjectInfoBtn" style="padding: 0.25rem 0.75rem; border-radius: 8px;">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label">المشروع</span>
                <span class="info-value" id="projectDisplay"><?php echo htmlspecialchars($row['project_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">فترة السماح</span>
                <span class="info-value" id="graceDisplay"><?php echo $row['grace_period_days']; ?> يوم</span>
            </div>
            <div class="info-item">
                <span class="info-label">عدد المشغلين</span>
                <span class="info-value" id="operatorsDisplay"><?php echo $row['daily_operators']; ?></span>
            </div>
        </div>


        <!-- الخدمات -->
        <div class="info-card success">
            <h5>
                <i class="fas fa-concierge-bell"></i> الخدمات المقدمة
                <button class="btn btn-sm btn-outline-success ms-auto" id="editServicesBtn" style="padding: 0.25rem 0.75rem; border-radius: 8px;">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-bus"></i> النقل</span>
                <span class="info-value" id="transportationDisplay"><?php echo $row['transportation']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-hotel"></i> السكن</span>
                <span class="info-value" id="accommodationDisplay"><?php echo $row['accommodation']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> مكان السكن</span>
                <span class="info-value" id="placeLivingDisplay"><?php echo $row['place_for_living']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-wrench"></i> الورشة</span>
                <span class="info-value" id="workshopDisplay"><?php echo $row['workshop']; ?></span>
            </div>
        </div>

        <!-- أطراف العقد -->
        <div class="info-card info">
            <h5>
                <i class="fas fa-users"></i> أطراف العقد
                <button class="btn btn-sm btn-outline-info ms-auto" id="editPartiesBtn" style="padding: 0.25rem 0.75rem; border-radius: 8px;">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label">الطرف الأول</span>
                <span class="info-value" id="firstPartyDisplay"><?php echo $row['first_party']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الطرف الثاني</span>
                <span class="info-value" id="secondPartyDisplay"><?php echo $row['second_party']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الشاهد الأول</span>
                <span class="info-value" id="witnessOneDisplay"><?php echo $row['witness_one']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الشاهد الثاني</span>
                <span class="info-value" id="witnessTwoDisplay"><?php echo $row['witness_two']; ?></span>
            </div>
        </div>

        <!-- البيانات المالية -->
        <div class="info-card warning">
            <h5>
                <i class="fas fa-money-bill-wave"></i> البيانات المالية
                <button class="btn btn-sm btn-outline-warning ms-auto" id="editPaymentBtn" style="padding: 0.25rem 0.75rem; border-radius: 8px;">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-dollar-sign"></i> العملة</span>
                <span class="info-value" id="currencyDisplay"><?php echo !empty($row['price_currency_contract']) ? $row['price_currency_contract'] : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-money-check-alt"></i> المبلغ المدفوع</span>
                <span class="info-value" id="paidAmountDisplay"><?php echo !empty($row['paid_contract']) ? $row['paid_contract'] : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> وقت الدفع</span>
                <span class="info-value" id="paymentTimeDisplay"><?php echo !empty($row['payment_time']) ? $row['payment_time'] : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-shield-alt"></i> الضمانات</span>
                <span class="info-value" id="guaranteesDisplay"><?php echo !empty($row['guarantees']) ? $row['guarantees'] : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar-check"></i> تاريخ الدفع</span>
                <span class="info-value" id="paymentDateDisplay"><?php echo !empty($row['payment_date']) ? $row['payment_date'] : '-'; ?></span>
            </div>
        </div>

        <!-- معلومات النظام -->
        <div class="info-card" style="border-right-color: #6c757d;">
            <h5><i class="fas fa-database"></i> معلومات النظام</h5>
            <div class="info-item">
                <span class="info-label">تاريخ الإنشاء</span>
                <span class="info-value"><?php echo $row['created_at']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">آخر تحديث</span>
                <span class="info-value"><?php echo $row['updated_at']; ?></span>
            </div>
        </div>
    </div>

    <?php if ((isset($row['pause_reason']) && !empty($row['pause_reason'])) || (isset($row['termination_reason']) && !empty($row['termination_reason']))): ?>
    <!-- بطاقة التحذيرات والملاحظات -->
    <div class="info-card danger" style="margin-bottom: 2rem;">
        <h5><i class="fas fa-exclamation-triangle"></i> تحذيرات وملاحظات هامة</h5>
        <?php if (isset($row['pause_reason']) && !empty($row['pause_reason'])): ?>
        <div class="info-item">
            <span class="info-label">سبب الإيقاف</span>
            <span class="info-value"><?php echo $row['pause_reason']; ?></span>
        </div>
        <?php endif; ?>
        <?php if (isset($row['termination_reason']) && !empty($row['termination_reason'])): ?>
        <div class="info-item">
            <span class="info-label">سبب الإنهاء</span>
            <span class="info-value"><?php echo $row['termination_reason']; ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php 
$contractStatusValue = isset($row['status']) ? $row['status'] : 1;
$supplier_id = $row['supplier_id'];
$project_id = $row['project_id'];
$actual_end_date = $row['actual_end'];
$pause_date = isset($row['pause_date']) ? $row['pause_date'] : '';
$pause_reason = isset($row['pause_reason']) ? $row['pause_reason'] : '';

// حفظ بيانات العقد للتعديل
$grace_period = $row['grace_period_days'];
$daily_operators = $row['daily_operators'];
$transportation = $row['transportation'];
$accommodation = $row['accommodation'];
$place_for_living = $row['place_for_living'];
$workshop = $row['workshop'];
$first_party = $row['first_party'];
$second_party = $row['second_party'];
$witness_one = $row['witness_one'];
$witness_two = $row['witness_two'];

// البيانات المالية
$price_currency_contract = isset($row['price_currency_contract']) ? $row['price_currency_contract'] : '';
$paid_contract = isset($row['paid_contract']) ? $row['paid_contract'] : '';
$payment_time = isset($row['payment_time']) ? $row['payment_time'] : '';
$guarantees = isset($row['guarantees']) ? $row['guarantees'] : '';
$payment_date = isset($row['payment_date']) ? $row['payment_date'] : '';
} 
?>

<!-- جدول معدات العقد (بما فيها معدات العقد المدموج) -->
<div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-top: 2rem;">
    <h4 style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; color: #667eea; font-weight: 700;">
        <i class="fas fa-boxes"></i>
        معدات العقد
        <?php 
        if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
            echo "<span style='font-size: 0.9rem; color: #6c757d;'>(العقد #" . $contract_id . " + العقد #" . $row['merged_with'] . ")</span>";
        }
        ?>
    </h4>
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع المعدة</th>
                    <th>الحجم</th>
                    <th>العدد</th>
                    <th>أساسية</th>
                    <th>احتياطية</th>
                    <th>عدد الورديات</th>
                    <th>الساعات/اليوم</th>
                    <th>إجمالي الساعات</th>
                    <th>وحدات العمل/الشهر</th>
                    <th>الوحدة</th>
                    <th>إجمالي ساعات العقد</th>
                    <th>السعر</th>
                    <th>المشغلين</th>
                    <th>المشرفين</th>
                    <th>الفنيين</th>
                    <th>المساعدين</th>
                    <?php 
                    if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                        echo "<th>المصدر</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Function to get supplier contract equipments
                if (!function_exists('getSupplierContractEquipments')) {
                    function getSupplierContractEquipments($contract_id, $conn) {
                        $equipments = [];
                        $query = "SELECT * FROM suppliercontractequipments WHERE contract_id = " . intval($contract_id);
                        $result = mysqli_query($conn, $query);
                        if ($result) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $equipments[] = $row;
                            }
                        }
                        return $equipments;
                    }
                }
                
                $equipments = getSupplierContractEquipments($contract_id, $conn);
                
                if (!empty($equipments)) {
                    $i = 1;
                    foreach ($equipments as $equip) {
                        echo "<tr>";
                        echo "<td>" . $i . "</td>";
                        echo "<td><strong>" . htmlspecialchars($equip['equip_type']) . "</strong></td>";
                        echo "<td>" . $equip['equip_size'] . "</td>";
                        echo "<td><span style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;'>" . $equip['equip_count'] . "</span></td>";
                        echo "<td><span style='background: #007bff; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;'>" . (isset($equip['equip_count_basic']) ? $equip['equip_count_basic'] : 0) . "</span></td>";
                        echo "<td><span style='background: #ffc107; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;'>" . (isset($equip['equip_count_backup']) ? $equip['equip_count_backup'] : 0) . "</span></td>";
                        echo "<td><span style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;'>" . (isset($equip['equip_shifts']) ? $equip['equip_shifts'] : 0) . "</span></td>";
                        echo "<td>" . $equip['shift_hours'] . "</td>";
                        echo "<td>" . $equip['equip_total_month'] . "</td>";
                        echo "<td><strong style='color: #667eea;'>" . (isset($equip['equip_monthly_target']) ? $equip['equip_monthly_target'] : 0) . "</strong></td>";
                        echo "<td>" . $equip['equip_unit'] . "</td>";
                        echo "<td><strong style='color: #667eea;'>" . $equip['equip_total_contract'] . "</strong></td>";
                        echo "<td><strong style='color: #28a745;'>" . $equip['equip_price'] . " " . $equip['equip_price_currency'] . "</strong></td>";
                        echo "<td>" . $equip['equip_operators'] . "</td>";
                        echo "<td>" . $equip['equip_supervisors'] . "</td>";
                        echo "<td>" . $equip['equip_technicians'] . "</td>";
                        echo "<td>" . $equip['equip_assistants'] . "</td>";
                        if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                            // التحقق من هل هذه المعدة من العقد المدموج أم لا
                            $merged_equipments = getSupplierContractEquipments(intval($row['merged_with']), $conn);
                            $is_from_merged = false;
                            foreach ($merged_equipments as $m_equip) {
                                if ($m_equip['equip_type'] == $equip['equip_type'] && 
                                    $m_equip['equip_size'] == $equip['equip_size'] &&
                                    $m_equip['equip_count'] == $equip['equip_count']) {
                                    $is_from_merged = true;
                                    break;
                                }
                            }
                            echo "<td><span class='badge " . ($is_from_merged ? "bg-success" : "bg-primary") . "'>" . 
                                 ($is_from_merged ? "العقد #" . $row['merged_with'] : "العقد #" . $contract_id) . 
                                 "</span></td>";
                        }
                        echo "</tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='14' style='text-align: center; padding: 2rem;'>";
                    echo "<i class='fas fa-inbox' style='font-size: 3rem; color: #e9ecef; margin-bottom: 1rem;'></i>";
                    echo "<p style='color: #999; font-size: 1.1rem;'>لا توجد معدات لهذا العقد</p>";
                    echo "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// إزالة الجدول المنفصل للعقد المدموج (تم دمج معداته في الجدول الرئيسي)
?>

    <br/><br/><br/>

    <!-- جدول الملاحظات -->
    <div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-top: 2rem; margin-bottom: 3rem;">
        <h4 style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; color: #667eea; font-weight: 700;">
            <i class="fas fa-history"></i>
            سجل الملاحظات والتغييرات
        </h4>
        <div style="overflow-x: auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نوع الإجراء</th>
                        <th>الملاحظة</th>
                        <th>بواسطة</th>
                        <th>التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $notes_query = "SELECT * 
                                    FROM supplier_contract_notes 
                                    WHERE contract_id = $contract_id 
                                    ORDER BY created_at DESC";
                    $notes_result = mysqli_query($conn, $notes_query);
                    
                    if ($notes_result && mysqli_num_rows($notes_result) > 0) {
                        $j = 1;
                        while ($note = mysqli_fetch_assoc($notes_result)) {
                            // تحديد نوع الإجراء من النص
                            $note_text = htmlspecialchars($note['note']);
                            $action_icon = '<i class="fas fa-sticky-note"></i>';
                            $action_badge = 'info';
                            
                            if (strpos($note_text, 'تجديد') !== false) {
                                $action_icon = '<i class="fas fa-sync-alt"></i>';
                                $action_badge = 'primary';
                                $action_type = 'تجديد';
                            } elseif (strpos($note_text, 'تسوية') !== false) {
                                $action_icon = '<i class="fas fa-balance-scale"></i>';
                                $action_badge = 'secondary';
                                $action_type = 'تسوية';
                            } elseif (strpos($note_text, 'إيقاف') !== false) {
                                $action_icon = '<i class="fas fa-pause-circle"></i>';
                                $action_badge = 'warning';
                                $action_type = 'إيقاف';
                            } elseif (strpos($note_text, 'استئناف') !== false) {
                                $action_icon = '<i class="fas fa-play-circle"></i>';
                                $action_badge = 'success';
                                $action_type = 'استئناف';
                            } elseif (strpos($note_text, 'إنهاء') !== false || strpos($note_text, 'انهاء') !== false) {
                                $action_icon = '<i class="fas fa-times-circle"></i>';
                                $action_badge = 'danger';
                                $action_type = 'إنهاء';
                            } elseif (strpos($note_text, 'دمج') !== false) {
                                $action_icon = '<i class="fas fa-object-group"></i>';
                                $action_badge = 'purple';
                                $action_type = 'دمج';
                            } else {
                                $action_type = 'ملاحظة عامة';
                            }
                            
                            $badge_colors = [
                                'primary' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);',
                                'secondary' => 'background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);',
                                'warning' => 'background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);',
                                'success' => 'background: linear-gradient(135deg, #28a745 0%, #20c997 100%);',
                                'danger' => 'background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);',
                                'purple' => 'background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);',
                                'info' => 'background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);'
                            ];
                            
                            echo "<tr>";
                            echo "<td>" . $j . "</td>";
                            echo "<td><span style='" . $badge_colors[$action_badge] . " color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;'>" . $action_icon . " " . $action_type . "</span></td>";
                            echo "<td style='text-align: right;'>" . $note_text . "</td>";
                            echo "<td><i class='fas fa-user' style='color:#667eea; margin-left:5px;'></i>النظام</td>";
                            echo "<td><i class='far fa-clock' style='margin-left: 0.5rem;'></i>" . $note['created_at'] . "</td>";
                            echo "</tr>";
                            $j++;
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; padding: 2rem;'>";
                        echo "<i class='fas fa-inbox' style='font-size: 3rem; color: #e9ecef; margin-bottom: 1rem;'></i>";
                        echo "<p style='color: #999; font-size: 1.1rem;'>لا توجد ملاحظات لهذا العقد</p>";
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- زر العودة -->
    <div style="text-align: center; margin: 2rem 0;">
        <a href="suppliers.php" class="btn btn-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1rem 3rem; border-radius: 15px; font-weight: 700; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
            <i class="fas fa-arrow-right"></i> العودة إلى قائمة الموردين
        </a>
    </div>

</div>

<!-- Modal for Renewal -->
<div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                <h5 class="modal-title" id="renewalModalLabel">
                    <i class="fas fa-sync-alt"></i>
                    تجديد العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>معلومة:</strong> سيتم تجديد مدة العقد بالتواريخ الجديدة.
                </div>
                <div class="mb-4">
                    <label for="renewalStartDate" class="form-label">
                        <i class="far fa-calendar-alt" style="margin-left: 0.5rem;"></i>
                        تاريخ بدء التجديد <span style="color: red;">*</span>
                    </label>
                    <input type="date" id="renewalStartDate" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="renewalEndDate" class="form-label">
                        <i class="far fa-calendar-check" style="margin-left: 0.5rem;"></i>
                        تاريخ انتهاء التجديد <span style="color: red;">*</span>
                    </label>
                    <input type="date" id="renewalEndDate" class="form-control">
                </div>
                <div id="renewalDurationDisplay" style="display: none; padding: 1rem; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 10px; margin-top: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #1976d2; font-weight: 600;">
                        <i class="fas fa-calendar-days"></i>
                        <span>مدة العقد الجديدة: <strong id="calculatedDays">0</strong> يوم</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> الغاء
                </button>
                <button type="button" class="btn" id="confirmRenewal" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none;">
                    <i class="fas fa-check"></i> تجديد
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Settlement -->
<div class="modal fade" id="settlementModal" tabindex="-1" aria-labelledby="settlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);">
                <h5 class="modal-title" id="settlementModalLabel">
                    <i class="fas fa-balance-scale"></i>
                    تسوية العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>معلومة:</strong> يمكنك زيادة أو تخفيض ساعات العقد.
                </div>
                <div class="mb-4">
                    <label for="settlementType" class="form-label">
                        <i class="fas fa-exchange-alt" style="margin-left: 0.5rem;"></i>
                        نوع التسوية <span style="color: red;">*</span>
                    </label>
                    <select id="settlementType" class="form-select">
                        <option value="">-- اختر --</option>
                        <option value="increase">➕ زيادة ساعات</option>
                        <option value="decrease">➖ نقصان ساعات</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="settlementHours" class="form-label">
                        <i class="far fa-clock" style="margin-left: 0.5rem;"></i>
                        عدد الساعات <span style="color: red;">*</span>
                    </label>
                    <input type="number" id="settlementHours" class="form-control" min="1" placeholder="أدخل عدد الساعات">
                </div>
                <div class="mb-3">
                    <label for="settlementReason" class="form-label">
                        <i class="fas fa-comment-alt" style="margin-left: 0.5rem;"></i>
                        السبب (اختياري)
                    </label>
                    <textarea id="settlementReason" class="form-control" rows="3" placeholder="أدخل السبب"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn" id="confirmSettlement" style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); color: white; border: none;">
                    <i class="fas fa-check"></i> تسوية
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Pause -->
<div class="modal fade" id="pauseModal" tabindex="-1" aria-labelledby="pauseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                <h5 class="modal-title" id="pauseModalLabel">
                    <i class="fas fa-pause-circle"></i>
                    إيقاف العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>تنبيه:</strong> سيتم إيقاف العقد مؤقتاً. يمكنك استئنافه لاحقاً.
                </div>
                <div class="mb-4">
                    <label for="pauseDate" class="form-label">
                        <i class="far fa-calendar-alt" style="margin-left: 0.5rem;"></i>
                        تاريخ الإيقاف <span style="color: red;">*</span>
                    </label>
                    <input type="date" id="pauseDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label for="pauseReason" class="form-label">
                        <i class="fas fa-comment-alt" style="margin-left: 0.5rem;"></i>
                        سبب الإيقاف <span style="color: red;">*</span>
                    </label>
                    <textarea id="pauseReason" class="form-control" rows="4" placeholder="أدخل السبب المفصل للإيقاف"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn" id="confirmPause" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; border: none;">
                    <i class="fas fa-pause-circle"></i> إيقاف
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Resume -->
<div class="modal fade" id="resumeModal" tabindex="-1" aria-labelledby="resumeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <h5 class="modal-title" id="resumeModalLabel">
                    <i class="fas fa-play-circle"></i>
                    استئناف العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <strong>تأكيد:</strong> سيتم استئناف العقد وإعادة تفعيله.
                </div>
                
                <!-- عرض تاريخ الإيقاف تلقائياً -->
                <div class="mb-4" style="padding: 1.25rem; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 12px; border-right: 5px solid #ffc107; box-shadow: 0 2px 10px rgba(255, 193, 7, 0.2);">
                    <div style="display: flex; align-items: center; gap: 0.75rem; color: #856404; font-weight: 700; margin-bottom: 0.75rem; font-size: 1.05rem;">
                        <i class="fas fa-pause-circle" style="font-size: 1.3rem;"></i>
                        <span>معلومات الإيقاف</span>
                    </div>
                    <div style="color: #856404; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="far fa-calendar-times"></i>
                        <strong>تاريخ إيقاف العقد:</strong> 
                        <span style="background: white; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 700; color: #d39e00;">
                            <?php echo !empty($pause_date) ? date('Y-m-d', strtotime($pause_date)) : 'غير محدد'; ?>
                        </span>
                    </div>
                    <?php if (!empty($pause_reason)): ?>
                    <div style="color: #856404; font-size: 0.95rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed #ffc107;">
                        <i class="fas fa-comment-dots"></i>
                        <strong>سبب الإيقاف:</strong> <?php echo htmlspecialchars($pause_reason); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- إدخال تاريخ الاستئناف -->
                <div class="mb-4">
                    <label for="resumeDate" class="form-label" style="font-weight: 700; font-size: 1.05rem;">
                        <i class="far fa-calendar-check" style="margin-left: 0.5rem; color: #28a745;"></i>
                        تاريخ استئناف العقد <span style="color: red;">*</span>
                    </label>
                    <input type="date" id="resumeDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" style="font-size: 1.05rem; font-weight: 600;">
                    <small class="form-text text-muted" style="display: block; margin-top: 0.5rem;">
                        <i class="fas fa-info-circle"></i> التاريخ الافتراضي هو اليوم، يمكنك تعديله حسب الحاجة
                    </small>
                </div>
                
                <div id="pauseDurationDisplay" style="display: none; padding: 1rem; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #1976d2; font-weight: 600; margin-bottom: 1rem;">
                        <i class="fas fa-clock"></i>
                        <span>مدة الإيقاف: <strong id="calculatedPauseDays">0</strong> يوم</span>
                    </div>
                    
                    <!-- خيارات معالجة أيام الإيقاف -->
                    <div style="background: white; padding: 1rem; border-radius: 8px; border: 2px solid #1976d2;">
                        <div style="font-weight: 700; color: #1976d2; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-question-circle"></i>
                            <span>كيف تريد معالجة أيام الإيقاف؟</span>
                        </div>
                        <div class="form-check mb-2" style="padding-right: 1.8rem;">
                            <input class="form-check-input" type="radio" name="pauseHandling" id="extendContract" value="extend" checked style="float: right; margin-right: -1.8rem; margin-top: 0.3rem;">
                            <label class="form-check-label" for="extendContract" style="font-weight: 600; color: #495057; cursor: pointer;">
                                <i class="fas fa-plus-circle" style="color: #28a745; margin-left: 0.5rem;"></i>
                                تمديد العقد: إضافة أيام الإيقاف إلى تاريخ الانتهاء
                                <small style="display: block; color: #6c757d; font-weight: normal; margin-top: 0.25rem; margin-right: 1.5rem;">
                                    سيتم تأجيل تاريخ انتهاء العقد بعدد أيام الإيقاف
                                </small>
                            </label>
                        </div>
                        <div class="form-check" style="padding-right: 1.8rem;">
                            <input class="form-check-input" type="radio" name="pauseHandling" id="deductFromContract" value="deduct" style="float: right; margin-right: -1.8rem; margin-top: 0.3rem;">
                            <label class="form-check-label" for="deductFromContract" style="font-weight: 600; color: #495057; cursor: pointer;">
                                <i class="fas fa-minus-circle" style="color: #dc3545; margin-left: 0.5rem;"></i>
                                خصم من العقد: تقليل مدة العقد بأيام الإيقاف
                                <small style="display: block; color: #6c757d; font-weight: normal; margin-top: 0.25rem; margin-right: 1.5rem;">
                                    سيتم تقليل تاريخ انتهاء العقد بعدد أيام الإيقاف
                                </small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="resumeReason" class="form-label">
                        <i class="fas fa-comment-alt" style="margin-left: 0.5rem;"></i>
                        ملاحظات (اختياري)
                    </label>
                    <textarea id="resumeReason" class="form-control" rows="3" placeholder="أدخل أي ملاحظات"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn" id="confirmResume" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none;">
                    <i class="fas fa-play-circle"></i> استئناف
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Terminate -->
<div class="modal fade" id="terminateModal" tabindex="-1" aria-labelledby="terminateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                <h5 class="modal-title" id="terminateModalLabel">
                    <i class="fas fa-times-circle"></i>
                    إنهاء العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>تحذير:</strong> عملية الإنهاء نهائية ولا يمكن التراجع عنها!
                </div>
                <div class="mb-4">
                    <label for="terminationType" class="form-label">
                        <i class="fas fa-list-ul" style="margin-left: 0.5rem;"></i>
                        نوع الإنهاء <span style="color: red;">*</span>
                    </label>
                    <select id="terminationType" class="form-select">
                        <option value="">-- اختر النوع --</option>
                        <option value="amicable">🤝 رضائي</option>
                        <option value="hardship">⚠️ بسبب التعسر</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="terminationReason" class="form-label">
                        <i class="fas fa-comment-alt" style="margin-left: 0.5rem;"></i>
                        السبب المفصل <span style="color: red;">*</span>
                    </label>
                    <textarea id="terminationReason" class="form-control" rows="4" placeholder="أدخل السبب المفصل لإنهاء العقد" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-danger" id="confirmTerminate">
                    <i class="fas fa-times-circle"></i> إنهاء نهائياً
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Merge -->
<div class="modal fade" id="mergeModal" tabindex="-1" aria-labelledby="mergeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);">
                <h5 class="modal-title" id="mergeModalLabel">
                    <i class="fas fa-object-group"></i>
                    دمج العقود
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>معلومة:</strong> سيتم دمج المعدات والبيانات من هذا العقد إلى العقد المختار.
                </div>
                <div class="mb-4">
                    <label for="mergeWithId" class="form-label">
                        <i class="fas fa-file-contract" style="margin-left: 0.5rem;"></i>
                        اختر العقد للدمج معه <span style="color: red;">*</span>
                    </label>
                    <select id="mergeWithId" class="form-select">
                        <option value="">-- اختر عقد --</option>
                        <?php
                        $merge_query = "SELECT id, contract_signing_date FROM supplierscontracts WHERE supplier_id = $supplier_id AND project_id = $project_id AND id != $contract_id ORDER BY id DESC";
                        $merge_result = mysqli_query($conn, $merge_query);
                        while ($m_row = mysqli_fetch_assoc($merge_result)) {
                            echo "<option value='" . $m_row['id'] . "'>العقد #" . $m_row['id'] . " - " . $m_row['contract_signing_date'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- عرض المعدات الحالية والمعدات الخاصة بالعقد المختار -->
                <div id="mergeEquipmentsContainer" style="margin-top: 20px;">
                    <h6 class="mb-3">معدات العقود:</h6>
                    
                    <!-- معدات العقد الحالي -->
                    <div class="mb-4">
                        <h6 style="background-color: #f0f0f0; padding: 10px; border-right: 3px solid #0066cc;">
                            <i class="fa fa-cube"></i> معدات العقد الحالي (#<?php echo $contract_id; ?>)
                        </h6>
                        <div id="currentContractEquipments">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>نوع المعدة</th>
                                        <th>الحجم</th>
                                        <th>العدد</th>
                                        <th>الساعات/الشهر</th>
                                        <th>وحدات/الشهر</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_equipments = getSupplierContractEquipments($contract_id, $conn);
                                    if (!empty($current_equipments)) {
                                        foreach ($current_equipments as $equip) {
                                            echo "<tr>";
                                            echo "<td>" . $equip['equip_type'] . "</td>";
                                            echo "<td>" . $equip['equip_size'] . "</td>";
                                            echo "<td>" . $equip['equip_count'] . "</td>";
                                            echo "<td>" . $equip['shift_hours'] . "</td>";
                                            echo "<td>" . (isset($equip['equip_monthly_target']) ? $equip['equip_monthly_target'] : 0) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' style='text-align: center; color: #999;'>لا توجد معدات</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- معدات العقد المختار -->
                    <div class="mb-4">
                        <h6 style="background-color: #f0f0f0; padding: 10px; border-right: 3px solid #28a745;">
                            <i class="fa fa-cube"></i> معدات العقد المختار
                        </h6>
                        <div id="selectedContractEquipments" style="min-height: 100px;">
                            <p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn" id="confirmMerge" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; border: none;">
                    <i class="fas fa-object-group"></i> دمج العقد
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Complete Contract -->
<div class="modal fade" id="completeModal" tabindex="-1" aria-labelledby="completeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);">
                <h5 class="modal-title" id="completeModalLabel">
                    <i class="fas fa-check-circle"></i>
                    انتهاء العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>ملاحظة:</strong> تسجيل انتهاء العقد بشكل طبيعي.
                </div>
                <div class="mb-3">
                    <label for="completeNote" class="form-label">
                        <i class="fas fa-comment-alt" style="margin-left: 0.5rem;"></i>
                        ملاحظات الانتهاء <span style="color: red;">*</span>
                    </label>
                    <textarea id="completeNote" class="form-control" rows="4" placeholder="أدخل ملاحظات حول انتهاء العقد" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white;" id="confirmComplete">
                    <i class="fas fa-check-circle"></i> تسجيل الانتهاء
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal لتعديل معلومات المشروع -->
<div class="modal fade" id="editProjectInfoModal" tabindex="-1" aria-labelledby="editProjectInfoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title" id="editProjectInfoLabel">
                    <i class="fas fa-edit"></i> تعديل معلومات المشروع
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editGracePeriod" class="form-label">
                        <i class="fas fa-calendar-alt" style="margin-left: 0.5rem;"></i>
                        فترة السماح (بالأيام)
                    </label>
                    <input type="number" id="editGracePeriod" class="form-control" value="<?php echo $grace_period; ?>" min="0">
                </div>
                <div class="mb-3">
                    <label for="editDailyOperators" class="form-label">
                        <i class="fas fa-users-cog" style="margin-left: 0.5rem;"></i>
                        عدد المشغلين اليومي
                    </label>
                    <input type="number" id="editDailyOperators" class="form-control" value="<?php echo $daily_operators; ?>" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="saveProjectInfo">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal لتعديل الخدمات -->
<div class="modal fade" id="editServicesModal" tabindex="-1" aria-labelledby="editServicesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <h5 class="modal-title" id="editServicesLabel">
                    <i class="fas fa-edit"></i> تعديل الخدمات
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editTransportation" class="form-label">
                        <i class="fas fa-bus" style="margin-left: 0.5rem;"></i>
                        النقل (Transportation)
                    </label>
                    <select id="editTransportation" class="form-select">
                        <option value="">— اختر —</option>
                        <option value="مالك المعدة" <?php echo ($transportation == 'مالك المعدة') ? 'selected' : ''; ?>>مالك المعدة</option>
                        <option value="مالك المشروع" <?php echo ($transportation == 'مالك المشروع') ? 'selected' : ''; ?>>مالك المشروع</option>
                        <option value="بدون" <?php echo ($transportation == 'بدون') ? 'selected' : ''; ?>>بدون</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editAccommodation" class="form-label">
                        <i class="fas fa-hotel" style="margin-left: 0.5rem;"></i>
                        الإعاشة (Accommodation)
                    </label>
                    <select id="editAccommodation" class="form-select">
                        <option value="">— اختر —</option>
                        <option value="مالك المعدة" <?php echo ($accommodation == 'مالك المعدة') ? 'selected' : ''; ?>>مالك المعدة</option>
                        <option value="مالك المشروع" <?php echo ($accommodation == 'مالك المشروع') ? 'selected' : ''; ?>>مالك المشروع</option>
                        <option value="بدون" <?php echo ($accommodation == 'بدون') ? 'selected' : ''; ?>>بدون</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editPlaceLiving" class="form-label">
                        <i class="fas fa-map-marker-alt" style="margin-left: 0.5rem;"></i>
                        مكان السكن (Place for Living)
                    </label>
                    <select id="editPlaceLiving" class="form-select">
                        <option value="">— اختر —</option>
                        <option value="مالك المعدة" <?php echo ($place_for_living == 'مالك المعدة') ? 'selected' : ''; ?>>مالك المعدة</option>
                        <option value="مالك المشروع" <?php echo ($place_for_living == 'مالك المشروع') ? 'selected' : ''; ?>>مالك المشروع</option>
                        <option value="بدون" <?php echo ($place_for_living == 'بدون') ? 'selected' : ''; ?>>بدون</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editWorkshop" class="form-label">
                        <i class="fas fa-wrench" style="margin-left: 0.5rem;"></i>
                        الورشة (Workshop)
                    </label>
                    <select id="editWorkshop" class="form-select">
                        <option value="">— اختر —</option>
                        <option value="مالك المعدة" <?php echo ($workshop == 'مالك المعدة') ? 'selected' : ''; ?>>مالك المعدة</option>
                        <option value="مالك المشروع" <?php echo ($workshop == 'مالك المشروع') ? 'selected' : ''; ?>>مالك المشروع</option>
                        <option value="بدون" <?php echo ($workshop == 'بدون') ? 'selected' : ''; ?>>بدون</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-success" id="saveServices">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal لتعديل أطراف العقد -->
<div class="modal fade" id="editPartiesModal" tabindex="-1" aria-labelledby="editPartiesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                <h5 class="modal-title" id="editPartiesLabel">
                    <i class="fas fa-edit"></i> تعديل أطراف العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editFirstParty" class="form-label">
                        <i class="fas fa-user-tie" style="margin-left: 0.5rem;"></i>
                        الطرف الأول
                    </label>
                    <input type="text" id="editFirstParty" class="form-control" value="<?php echo htmlspecialchars($first_party); ?>" placeholder="اسم الطرف الأول">
                </div>
                <div class="mb-3">
                    <label for="editSecondParty" class="form-label">
                        <i class="fas fa-user-check" style="margin-left: 0.5rem;"></i>
                        الطرف الثاني
                    </label>
                    <input type="text" id="editSecondParty" class="form-control" value="<?php echo htmlspecialchars($second_party); ?>" placeholder="اسم الطرف الثاني">
                </div>
                <div class="mb-3">
                    <label for="editWitnessOne" class="form-label">
                        <i class="fas fa-eye" style="margin-left: 0.5rem;"></i>
                        الشاهد الأول
                    </label>
                    <input type="text" id="editWitnessOne" class="form-control" value="<?php echo htmlspecialchars($witness_one); ?>" placeholder="اسم الشاهد الأول">
                </div>
                <div class="mb-3">
                    <label for="editWitnessTwo" class="form-label">
                        <i class="fas fa-eye" style="margin-left: 0.5rem;"></i>
                        الشاهد الثاني
                    </label>
                    <input type="text" id="editWitnessTwo" class="form-control" value="<?php echo htmlspecialchars($witness_two); ?>" placeholder="اسم الشاهد الثاني">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-info" id="saveParties">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal لتعديل البيانات المالية -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                <h5 class="modal-title" id="editPaymentLabel">
                    <i class="fas fa-edit"></i> تعديل البيانات المالية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editCurrency" class="form-label">
                        <i class="fas fa-dollar-sign" style="margin-left: 0.5rem;"></i>
                        العملة
                    </label>
                    <select id="editCurrency" class="form-select">
                        <option value="">— اختر —</option>
                        <option value="دولار" <?php echo ($price_currency_contract == 'دولار') ? 'selected' : ''; ?>>دولار</option>
                        <option value="جنيه" <?php echo ($price_currency_contract == 'جنيه') ? 'selected' : ''; ?>>جنيه</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editPaidAmount" class="form-label">
                        <i class="fas fa-money-check-alt" style="margin-left: 0.5rem;"></i>
                        المبلغ المدفوع
                    </label>
                    <input type="text" id="editPaidAmount" class="form-control" value="<?php echo htmlspecialchars($paid_contract); ?>" placeholder="أدخل المبلغ">
                </div>
                <div class="mb-3">
                    <label for="editPaymentTime" class="form-label">
                        <i class="fas fa-clock" style="margin-left: 0.5rem;"></i>
                        وقت الدفع
                    </label>
                    <select id="editPaymentTime" class="form-select">
                        <option value="">— اختر —</option>
                        <option value="مقدم" <?php echo ($payment_time == 'مقدم') ? 'selected' : ''; ?>>مقدم</option>
                        <option value="مؤخر" <?php echo ($payment_time == 'مؤخر' || $payment_time == ' مؤخر') ? 'selected' : ''; ?>>مؤخر</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editGuarantees" class="form-label">
                        <i class="fas fa-shield-alt" style="margin-left: 0.5rem;"></i>
                        الضمانات
                    </label>
                    <textarea id="editGuarantees" class="form-control" rows="3" placeholder="تفاصيل الضمانات"><?php echo htmlspecialchars($guarantees); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="editPaymentDate" class="form-label">
                        <i class="fas fa-calendar-check" style="margin-left: 0.5rem;"></i>
                        تاريخ الدفع
                    </label>
                    <input type="date" id="editPaymentDate" class="form-control" value="<?php echo htmlspecialchars($payment_date); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-warning" id="savePayment">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (required for your AJAX calls) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const contractId = <?php echo $contract_id; ?>;
const contractStatus = <?php echo isset($contractStatusValue) ? $contractStatusValue : 1; ?>;
const actualEndDate = '<?php echo isset($actual_end_date) ? $actual_end_date : ''; ?>';  // تاريخ انتهاء العقد الفعلي

// دالة عامة للإجراءات
function performAction(action, data = {}) {
    $.ajax({
        url: 'supplier_contract_actions_handler.php',
        type: 'POST',
        data: Object.assign({action: action, contract_id: contractId}, data),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('خطأ: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('الخطأ:', error);
            alert('خطأ في الاتصال بالخادم: ' + (xhr.responseText || error));
        }
    });
}

// دالة للتحقق من إمكانية تنفيذ الإجراء
function canPerformAction(action) {
    const activeStatuses = {
        'renewal': [1],
        'settlement': [1],
        'pause': [1],
        'resume': [0],
        'terminate': [1, 0],
        'merge': [1]
    };
    
    if (!activeStatuses[action]) return true;
    
    if (!activeStatuses[action].includes(contractStatus)) {
        const statusMsg = {
            'renewal': 'العقد يجب أن يكون ساري لتجديده',
            'settlement': 'العقد يجب أن يكون ساري لتسويته',
            'pause': 'العقد يجب أن يكون ساري لإيقافه',
            'resume': 'العقد يجب أن يكون غير ساري لاستئنافه',
            'terminate': 'العقد يجب أن يكون ساري أو غير ساري لإنهاؤه',
            'merge': 'العقد يجب أن يكون ساري للدمج'
        };
        alert(statusMsg[action] || 'لا يمكن تنفيذ هذا الإجراء في الحالة الحالية');
        return false;
    }
    return true;
}

// أزرار الإجراءات - Bootstrap 5 syntax
$('#renewalBtn').click(function() {
    if (!canPerformAction('renewal')) return;
    // تعيين تاريخ البدء الافتراضي لتاريخ انتهاء العقد الفعلي
    if (actualEndDate) {
        $('#renewalStartDate').val(actualEndDate);
    }
    const modal = new bootstrap.Modal(document.getElementById('renewalModal'));
    modal.show();
});

// إعادة تعيين عرض المدة عند إغلاق المودال
document.getElementById('renewalModal').addEventListener('hidden.bs.modal', function() {
    $('#renewalDurationDisplay').hide();
    $('#calculatedDays').text('0');
});

// حساب المدة تلقائياً عند تغيير التواريخ
function calculateRenewalDuration() {
    const startDate = $('#renewalStartDate').val();
    const endDate = $('#renewalEndDate').val();
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (start < end) {
            const timeDiff = end.getTime() - start.getTime();
            const durationDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
            
            $('#calculatedDays').text(durationDays);
            $('#renewalDurationDisplay').slideDown(300);
        } else {
            $('#renewalDurationDisplay').slideUp(300);
        }
    } else {
        $('#renewalDurationDisplay').slideUp(300);
    }
}

$('#renewalStartDate, #renewalEndDate').on('change', calculateRenewalDuration);

$('#confirmRenewal').click(function() {
    const startDate = $('#renewalStartDate').val();
    const endDate = $('#renewalEndDate').val();
    if (!startDate || !endDate) {
        alert('الرجاء ملء جميع الحقول');
        return;
    }
    if (new Date(startDate) >= new Date(endDate)) {
        alert('تاريخ البدء يجب أن يكون قبل تاريخ الانتهاء');
        return;
    }
    
    // حساب عدد الأيام بين التاريخين
    const start = new Date(startDate);
    const end = new Date(endDate);
    const timeDiff = end.getTime() - start.getTime();
    const durationDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
    
    performAction('renewal', {
        new_start_date: startDate,
        new_end_date: endDate,
        contract_duration_days: durationDays
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('renewalModal')).hide();
    $('#renewalStartDate').val('');
    $('#renewalEndDate').val('');
    $('#renewalDurationDisplay').hide();
    $('#calculatedDays').text('0');
});

$('#settlementBtn').click(function() {
    if (!canPerformAction('settlement')) return;
    const modal = new bootstrap.Modal(document.getElementById('settlementModal'));
    modal.show();
});

$('#confirmSettlement').click(function() {
    const type = $('#settlementType').val();
    const hours = $('#settlementHours').val();
    if (!type || !hours) {
        alert('الرجاء ملء الحقول المطلوبة');
        return;
    }
    if (parseInt(hours) <= 0) {
        alert('عدد الساعات يجب أن يكون أكبر من صفر');
        return;
    }
    performAction('settlement', {
        settlement_type: type,
        settlement_hours: hours,
        settlement_reason: $('#settlementReason').val()
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('settlementModal')).hide();
    $('#settlementType').val('');
    $('#settlementHours').val('');
    $('#settlementReason').val('');
});

$('#pauseBtn').click(function() {
    if (!canPerformAction('pause')) return;
    const modal = new bootstrap.Modal(document.getElementById('pauseModal'));
    modal.show();
});

$('#confirmPause').click(function() {
    const reason = $('#pauseReason').val();
    const pauseDate = $('#pauseDate').val();
    if (!reason) {
        alert('الرجاء إدخال سبب الإيقاف');
        return;
    }
    if (!pauseDate) {
        alert('الرجاء تحديد تاريخ الإيقاف');
        return;
    }
    performAction('pause', {
        pause_reason: reason,
        pause_date: pauseDate
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('pauseModal')).hide();
    $('#pauseReason').val('');
    $('#pauseDate').val('<?php echo date('Y-m-d'); ?>');
});

$('#resumeBtn').click(function() {
    if (!canPerformAction('resume')) return;
    const modal = new bootstrap.Modal(document.getElementById('resumeModal'));
    modal.show();
    
    // حساب عدد أيام الإيقاف عند فتح الـ modal
    calculatePauseDuration();
});

// دالة لحساب مدة الإيقاف
function calculatePauseDuration() {
    const resumeDate = $('#resumeDate').val();
    const pauseDate = '<?php echo !empty($pause_date) ? $pause_date : ''; ?>';
    
    if (pauseDate && resumeDate) {
        const pause = new Date(pauseDate);
        const resume = new Date(resumeDate);
        
        if (resume >= pause) {
            const timeDiff = resume.getTime() - pause.getTime();
            const durationDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
            
            $('#calculatedPauseDays').text(durationDays);
            $('#pauseDurationDisplay').slideDown(300);
        } else {
            $('#pauseDurationDisplay').slideUp(300);
        }
    } else {
        $('#pauseDurationDisplay').slideUp(300);
    }
}

$('#resumeDate').on('change', calculatePauseDuration);

$('#confirmResume').click(function() {
    const resumeDate = $('#resumeDate').val();
    if (!resumeDate) {
        alert('الرجاء تحديد تاريخ الاستئناف');
        return;
    }
    
    const pauseDate = '<?php echo !empty($pause_date) ? $pause_date : ''; ?>';
    let pauseDays = 0;
    
    if (pauseDate && resumeDate) {
        const pause = new Date(pauseDate);
        const resume = new Date(resumeDate);
        const timeDiff = resume.getTime() - pause.getTime();
        pauseDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
    }
    
    // الحصول على خيار معالجة أيام الإيقاف
    const pauseHandling = $('input[name="pauseHandling"]:checked').val();
    
    performAction('resume', {
        resume_reason: $('#resumeReason').val(),
        resume_date: resumeDate,
        pause_days: pauseDays,
        pause_handling: pauseHandling
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('resumeModal')).hide();
    $('#resumeReason').val('');
    $('#resumeDate').val('<?php echo date('Y-m-d'); ?>');
    $('#pauseDurationDisplay').hide();
    $('#calculatedPauseDays').text('0');
});

$('#terminateBtn').click(function() {
    if (!canPerformAction('terminate')) return;
    const modal = new bootstrap.Modal(document.getElementById('terminateModal'));
    modal.show();
});

$('#confirmTerminate').click(function() {
    const type = $('#terminationType').val();
    if (!type) {
        alert('الرجاء اختيار نوع الإنهاء');
        return;
    }
    performAction('terminate', {
        termination_type: type,
        termination_reason: $('#terminationReason').val()
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('terminateModal')).hide();
    $('#terminationType').val('');
    $('#terminationReason').val('');
});

$('#mergeBtn').click(function() {
    if (!canPerformAction('merge')) return;
    const modal = new bootstrap.Modal(document.getElementById('mergeModal'));
    modal.show();
});

// Complete Contract Button Handler
$('#completeBtn').click(function() {
    const modal = new bootstrap.Modal(document.getElementById('completeModal'));
    modal.show();
});

$('#confirmComplete').click(function() {
    const note = $('#completeNote').val().trim();
    if (!note) {
        alert('الرجاء إدخال ملاحظات الانتهاء');
        return;
    }
    performAction('complete', {
        complete_note: note
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('completeModal')).hide();
    $('#completeNote').val('');
});

// أزرار التعديل
$('#editProjectInfoBtn').click(function() {
    const modal = new bootstrap.Modal(document.getElementById('editProjectInfoModal'));
    modal.show();
});

$('#editServicesBtn').click(function() {
    const modal = new bootstrap.Modal(document.getElementById('editServicesModal'));
    modal.show();
});

$('#editPartiesBtn').click(function() {
    const modal = new bootstrap.Modal(document.getElementById('editPartiesModal'));
    modal.show();
});

// حفظ معلومات المشروع
$('#saveProjectInfo').click(function() {
    const gracePeriod = $('#editGracePeriod').val();
    const dailyOperators = $('#editDailyOperators').val();
    
    $.ajax({
        url: '../Contracts/update_contract_details.php',
        type: 'POST',
        data: {
            action: 'update_project_info',
            contract_id: contractId,
            grace_period: gracePeriod,
            daily_operators: dailyOperators
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#graceDisplay').text(gracePeriod + ' يوم');
                $('#operatorsDisplay').text(dailyOperators);
                bootstrap.Modal.getInstance(document.getElementById('editProjectInfoModal')).hide();
                alert(response.message);
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء الحفظ');
        }
    });
});

// حفظ الخدمات
$('#saveServices').click(function() {
    const transportation = $('#editTransportation').val();
    const accommodation = $('#editAccommodation').val();
    const placeLiving = $('#editPlaceLiving').val();
    const workshop = $('#editWorkshop').val();
    
    $.ajax({
        url: '../Contracts/update_contract_details.php',
        type: 'POST',
        data: {
            action: 'update_services',
            contract_id: contractId,
            transportation: transportation,
            accommodation: accommodation,
            place_for_living: placeLiving,
            workshop: workshop
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#transportationDisplay').text(transportation);
                $('#accommodationDisplay').text(accommodation);
                $('#placeLivingDisplay').text(placeLiving);
                $('#workshopDisplay').text(workshop);
                bootstrap.Modal.getInstance(document.getElementById('editServicesModal')).hide();
                alert(response.message);
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء الحفظ');
        }
    });
});

// حفظ أطراف العقد
$('#saveParties').click(function() {
    const firstParty = $('#editFirstParty').val();
    const secondParty = $('#editSecondParty').val();
    const witnessOne = $('#editWitnessOne').val();
    const witnessTwo = $('#editWitnessTwo').val();
    
    $.ajax({
        url: '../Contracts/update_contract_details.php',
        type: 'POST',
        data: {
            action: 'update_parties',
            contract_id: contractId,
            first_party: firstParty,
            second_party: secondParty,
            witness_one: witnessOne,
            witness_two: witnessTwo
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#firstPartyDisplay').text(firstParty);
                $('#secondPartyDisplay').text(secondParty);
                $('#witnessOneDisplay').text(witnessOne);
                $('#witnessTwoDisplay').text(witnessTwo);
                bootstrap.Modal.getInstance(document.getElementById('editPartiesModal')).hide();
                alert(response.message);
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء الحفظ');
        }
    });
});

// فتح modal البيانات المالية
$('#editPaymentBtn').click(function() {
    const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
    modal.show();
});

// حفظ البيانات المالية
$('#savePayment').click(function() {
    const currency = $('#editCurrency').val();
    const paidAmount = $('#editPaidAmount').val();
    const paymentTime = $('#editPaymentTime').val();
    const guarantees = $('#editGuarantees').val();
    const paymentDate = $('#editPaymentDate').val();
    
    $.ajax({
        url: '../Contracts/update_contract_details.php',
        type: 'POST',
        data: {
            action: 'update_payment',
            contract_id: contractId,
            price_currency_contract: currency,
            paid_contract: paidAmount,
            payment_time: paymentTime,
            guarantees: guarantees,
            payment_date: paymentDate
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#currencyDisplay').text(currency || '-');
                $('#paidAmountDisplay').text(paidAmount || '-');
                $('#paymentTimeDisplay').text(paymentTime || '-');
                $('#guaranteesDisplay').text(guarantees || '-');
                $('#paymentDateDisplay').text(paymentDate || '-');
                bootstrap.Modal.getInstance(document.getElementById('editPaymentModal')).hide();
                alert(response.message);
                location.reload();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('حدث خطأ أثناء الحفظ');
        }
    });
});

// تحميل معدات العقد المختار عند التغيير
$('#mergeWithId').on('change', function() {
    const selectedContractId = $(this).val();
    
    if (!selectedContractId) {
        $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
        return;
    }
    
    // تحميل المعدات عبر AJAX
    $.ajax({
        url: 'get_supplier_contract_equipments.php',
        type: 'GET',
        data: { contract_id: selectedContractId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.equipments.length > 0) {
                    html = '<table class="table table-sm table-bordered">';
                    html += '<thead class="table-light"><tr>';
                    html += '<th>نوع المعدة</th>';
                    html += '<th>الحجم</th>';
                    html += '<th>العدد</th>';
                    html += '<th>الساعات/الشهر</th>';
                    html += '<th>وحدات/الشهر</th>';
                    html += '</tr></thead>';
                    html += '<tbody>';
                    
                    response.equipments.forEach(function(equip) {
                        html += '<tr>';
                        html += '<td>' + equip.equip_type + '</td>';
                        html += '<td>' + equip.equip_size + '</td>';
                        html += '<td>' + equip.equip_count + '</td>';
                        html += '<td>' + equip.shift_hours + '</td>';
                        html += '<td>' + (equip.equip_monthly_target || 0) + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                } else {
                    html = '<p style="text-align: center; color: #999;">لا توجد معدات لهذا العقد</p>';
                }
                $('#selectedContractEquipments').html(html);
            } else {
                $('#selectedContractEquipments').html('<p style="text-align: center; color: #c00;">خطأ: ' + response.message + '</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('الخطأ:', error);
            $('#selectedContractEquipments').html('<p style="text-align: center; color: #c00;">خطأ في تحميل المعدات</p>');
        }
    });
});

$('#confirmMerge').click(function() {
    const mergeId = $('#mergeWithId').val();
    if (!mergeId) {
        alert('الرجاء اختيار العقد للدمج معه');
        return;
    }
    if (parseInt(mergeId) === contractId) {
        alert('لا يمكنك دمج العقد مع نفسه');
        return;
    }
    performAction('merge', {
        merge_with_id: mergeId
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('mergeModal')).hide();
    $('#mergeWithId').val('');
    $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
});
</script>

</body>
</html>

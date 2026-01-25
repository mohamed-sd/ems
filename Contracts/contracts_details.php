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
    <title>إيكوبيشن | تفاصيل العقد</title>

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

    <h3><i class="fas fa-file-contract"></i> تفاصيل العقد</h3>

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
    </div>

<?php
include '../config.php';

$contract_id = intval($_GET['id']);

$sql = "SELECT 
            id, project, contract_signing_date, grace_period_days, contract_duration_months, contract_duration_days,
            actual_start, actual_end, transportation, accommodation, place_for_living, 
            workshop, hours_monthly_target, forecasted_contracted_hours, created_at, updated_at,
            daily_work_hours, daily_operators, first_party, second_party, 
            witness_one, witness_two, status, pause_reason, termination_type, termination_reason, merged_with
        FROM contracts
        WHERE id = $contract_id
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
    </div>

    <!-- بطاقات تفاصيل العقد -->
    <div class="info-cards-grid">
        <!-- معلومات المشروع -->
        <div class="info-card primary">
            <h5><i class="fas fa-project-diagram"></i> معلومات المشروع</h5>
            <div class="info-item">
                <span class="info-label">المشروع</span>
                <span class="info-value"><?php echo $row['project']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">فترة السماح</span>
                <span class="info-value"><?php echo $row['grace_period_days']; ?> يوم</span>
            </div>
            <div class="info-item">
                <span class="info-label">عدد المشغلين</span>
                <span class="info-value"><?php echo $row['daily_operators']; ?></span>
            </div>
        </div>

        <!-- الخدمات -->
        <div class="info-card success">
            <h5><i class="fas fa-concierge-bell"></i> الخدمات المقدمة</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-bus"></i> النقل</span>
                <span class="info-value"><?php echo $row['transportation']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-hotel"></i> السكن</span>
                <span class="info-value"><?php echo $row['accommodation']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> مكان السكن</span>
                <span class="info-value"><?php echo $row['place_for_living']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-wrench"></i> الورشة</span>
                <span class="info-value"><?php echo $row['workshop']; ?></span>
            </div>
        </div>

        <!-- أطراف العقد -->
        <div class="info-card info">
            <h5><i class="fas fa-users"></i> أطراف العقد</h5>
            <div class="info-item">
                <span class="info-label">الطرف الأول</span>
                <span class="info-value"><?php echo $row['first_party']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الطرف الثاني</span>
                <span class="info-value"><?php echo $row['second_party']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الشاهد الأول</span>
                <span class="info-value"><?php echo $row['witness_one']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الشاهد الثاني</span>
                <span class="info-value"><?php echo $row['witness_two']; ?></span>
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
$project_id = $row['project'];
$actual_end_date = $row['actual_end'];
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
                    <th>عدد الورديات</th>
                    <th>الساعات/اليوم</th>
                    <th>إجمالي الساعات</th>
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
                include 'contractequipments_handler.php';
                $equipments = getContractEquipments($contract_id, $conn);
                
                if (!empty($equipments)) {
                    $i = 1;
                    foreach ($equipments as $equip) {
                        echo "<tr>";
                        echo "<td>" . $i . "</td>";
                        echo "<td><strong>" . htmlspecialchars($equip['equip_type']) . "</strong></td>";
                        echo "<td>" . $equip['equip_size'] . "</td>";
                        echo "<td><span style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;'>" . $equip['equip_count'] . "</span></td>";
                        echo "<td><span style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;'>" . (isset($equip['equip_shifts']) ? $equip['equip_shifts'] : 0) . "</span></td>";
                        echo "<td>" . $equip['equip_target_per_month'] . "</td>";
                        echo "<td>" . $equip['equip_total_month'] . "</td>";
                        echo "<td>" . $equip['equip_unit'] . "</td>";
                        echo "<td><strong style='color: #667eea;'>" . $equip['equip_total_contract'] . "</strong></td>";
                        echo "<td><strong style='color: #28a745;'>" . $equip['equip_price'] . " " . $equip['equip_price_currency'] . "</strong></td>";
                        echo "<td>" . $equip['equip_operators'] . "</td>";
                        echo "<td>" . $equip['equip_supervisors'] . "</td>";
                        echo "<td>" . $equip['equip_technicians'] . "</td>";
                        echo "<td>" . $equip['equip_assistants'] . "</td>";
                        if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                            // التحقق من هل هذه المعدة من العقد المدموج أم لا
                            $merged_equipments = getContractEquipments(intval($row['merged_with']), $conn);
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
                        <th>التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $notes_query = "SELECT * FROM contract_notes WHERE contract_id = $contract_id ORDER BY created_at DESC";
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
                            echo "<td><i class='far fa-clock' style='margin-left: 0.5rem;'></i>" . $note['created_at'] . "</td>";
                            echo "</tr>";
                            $j++;
                        }
                    } else {
                        echo "<tr><td colspan='4' style='text-align: center; padding: 2rem;'>";
                        echo "<i class='fas fa-inbox' style='font-size: 3rem; color: #e9ecef; margin-bottom: 1rem;'></i>";
                        echo "<p style='color: #999; font-size: 1.1rem;'>لا توجد ملاحظات لهذا العقد</p>";
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
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
                        $merge_query = "SELECT id, contract_signing_date FROM contracts WHERE project = $project_id AND id != $contract_id ORDER BY id DESC";
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_equipments = getContractEquipments($contract_id, $conn);
                                    if (!empty($current_equipments)) {
                                        foreach ($current_equipments as $equip) {
                                            echo "<tr>";
                                            echo "<td>" . $equip['equip_type'] . "</td>";
                                            echo "<td>" . $equip['equip_size'] . "</td>";
                                            echo "<td>" . $equip['equip_count'] . "</td>";
                                            echo "<td>" . $equip['equip_target_per_month'] . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' style='text-align: center; color: #999;'>لا توجد معدات</td></tr>";
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
        url: 'contract_actions_handler.php',
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
    performAction('renewal', {
        new_start_date: startDate,
        new_end_date: endDate
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('renewalModal')).hide();
    $('#renewalStartDate').val('');
    $('#renewalEndDate').val('');
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
    if (!reason) {
        alert('الرجاء إدخال سبب الإيقاف');
        return;
    }
    performAction('pause', {
        pause_reason: reason
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('pauseModal')).hide();
    $('#pauseReason').val('');
});

$('#resumeBtn').click(function() {
    if (!canPerformAction('resume')) return;
    const modal = new bootstrap.Modal(document.getElementById('resumeModal'));
    modal.show();
});

$('#confirmResume').click(function() {
    performAction('resume', {
        resume_reason: $('#resumeReason').val()
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('resumeModal')).hide();
    $('#resumeReason').val('');
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

// تحميل معدات العقد المختار عند التغيير
$('#mergeWithId').on('change', function() {
    const selectedContractId = $(this).val();
    
    if (!selectedContractId) {
        $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
        return;
    }
    
    // تحميل المعدات عبر AJAX
    $.ajax({
        url: 'get_contract_equipments.php',
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
                    html += '</tr></thead>';
                    html += '<tbody>';
                    
                    response.equipments.forEach(function(equip) {
                        html += '<tr>';
                        html += '<td>' + equip.equip_type + '</td>';
                        html += '<td>' + equip.equip_size + '</td>';
                        html += '<td>' + equip.equip_count + '</td>';
                        html += '<td>' + equip.equip_target_per_month + '</td>';
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
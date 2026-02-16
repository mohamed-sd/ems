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
    <title>إيكوبيشن | تفاصيل ساعات العمل</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

        :root {
            --primary-color: #1a1a2e;
            --secondary-color: #16213e;
            --gold-color: #ffcc00;
            --text-color: #010326;
            --light-color: #f5f5f5;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        * {
            font-family: 'Cairo', sans-serif;
        }

        .main {
            padding: 2rem;
            background: var(--light-color);
            min-height: 100vh;
        }

        /* Page Title */
        .page-title {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Report Container */
        .report {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px var(--shadow-color);
            margin-bottom: 2rem;
        }

        /* Section Title */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid var(--gold-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--gold-color);
        }

        /* Info Cards Grid */
        .info-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border-right: 5px solid;
            box-shadow: 0 3px 15px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .info-card.primary {
            border-right-color: var(--primary-color);
        }

        .info-card.success {
            border-right-color: #28a745;
        }

        .info-card.warning {
            border-right-color: var(--gold-color);
        }

        .info-card.danger {
            border-right-color: #dc3545;
        }

        .info-card.info {
            border-right-color: #17a2b8;
        }

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
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-label i {
            color: var(--gold-color);
            font-size: 0.9rem;
        }

        .info-value {
            font-weight: 500;
            color: var(--text-color);
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

        .info-card, .report {
            animation: fadeInUp 0.6s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main {
                padding: 1rem;
            }
            
            .info-cards-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">
    <h3 class="page-title">
        <i class="fas fa-clock"></i>
        تفاصيل ساعات العمل
    </h3>

<?php
include '../config.php';
$project = intval($_GET['id']);
$sql = "SELECT  * , t.id,
               d.name AS driver_name,
               e.code AS equipment_name,
               e.name AS equipment_fullname,
               p.name AS project_name,
               t.shift,
               t.date
        FROM timesheet t
        JOIN drivers d ON t.driver = d.id
        JOIN operations o ON t.operator = o.id
        JOIN equipments e ON o.equipment = e.id
        JOIN project p ON o.project_id = p.id
        WHERE t.id = $project
        ORDER BY t.date DESC";

$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $shift_display = $row['shift'] == "D" ? "صباح" : "مساء";
?>

<div class="report">
    <!-- معلومات عامة -->
    <h4 class="section-title">
        <i class="fas fa-info-circle"></i>
        المعلومات العامة
    </h4>
    <div class="info-cards-grid">
        <div class="info-card primary">
            <h5><i class="fas fa-user-tie"></i> بيانات المشغل</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-id-card"></i> اسم المشغل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['driver_name']); ?></span>
            </div>
        </div>

        <div class="info-card info">
            <h5><i class="fas fa-truck-moving"></i> بيانات المعدة</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-barcode"></i> كود المعدة</span>
                <span class="info-value"><?php echo htmlspecialchars($row['equipment_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-tag"></i> اسم المعدة</span>
                <span class="info-value"><?php echo htmlspecialchars($row['equipment_fullname']); ?></span>
            </div>
        </div>

        <div class="info-card success">
            <h5><i class="fas fa-project-diagram"></i> بيانات المشروع</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-building"></i> اسم المشروع</span>
                <span class="info-value"><?php echo htmlspecialchars($row['project_name']); ?></span>
            </div>
        </div>

        <div class="info-card warning">
            <h5><i class="fas fa-calendar-alt"></i> الوردية والتاريخ</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-moon"></i> الوردية</span>
                <span class="info-value"><?php echo $shift_display; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar-day"></i> التاريخ</span>
                <span class="info-value"><?php echo htmlspecialchars($row['date']); ?></span>
            </div>
        </div>
    </div>

    <!-- ساعات العمل -->
    <h4 class="section-title">
        <i class="fas fa-business-time"></i>
        ساعات العمل
    </h4>
    <div class="info-cards-grid">
        <div class="info-card success">
            <h5><i class="fas fa-clock"></i> ساعات الوردية</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-hourglass-start"></i> ساعات الوردية</span>
                <span class="info-value"><?php echo htmlspecialchars($row['shift_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-check-circle"></i> الساعات المنفذة</span>
                <span class="info-value"><?php echo htmlspecialchars($row['executed_hours']); ?></span>
            </div>
        </div>

        <div class="info-card info">
            <h5><i class="fas fa-tools"></i> ساعات المعدات الإضافية</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-box"></i> ساعات الجردل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['bucket_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-wrench"></i> ساعات الجاكمر</span>
                <span class="info-value"><?php echo htmlspecialchars($row['jackhammer_hours']); ?></span>
            </div>
        </div>

        <div class="info-card warning">
            <h5><i class="fas fa-plus-circle"></i> الساعات الإضافية</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-plus"></i> الساعات الإضافية</span>
                <span class="info-value"><?php echo htmlspecialchars($row['extra_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-calculator"></i> مجموع الإضافي</span>
                <span class="info-value"><?php echo htmlspecialchars($row['extra_hours_total']); ?></span>
            </div>
        </div>

        <div class="info-card primary">
            <h5><i class="fas fa-pause-circle"></i> ساعات الاستعداد</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-user-clock"></i> استعداد العميل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['standby_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-check-double"></i> استعداد اعتماد</span>
                <span class="info-value"><?php echo htmlspecialchars($row['dependence_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-sum"></i> مجموع ساعات العمل</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($row['total_work_hours']); ?></strong></span>
            </div>
        </div>
    </div>

    <!-- ساعات الأعطال -->
    <h4 class="section-title">
        <i class="fas fa-exclamation-triangle"></i>
        ساعات الأعطال والتعطل
    </h4>
    <div class="info-cards-grid">
        <div class="info-card danger">
            <h5><i class="fas fa-user-times"></i> عطل HR</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> ساعات العطل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['hr_fault']); ?></span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-wrench"></i> عطل الصيانة</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> ساعات العطل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['maintenance_fault']); ?></span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-chart-line"></i> عطل التسويق</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> ساعات العطل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['marketing_fault']); ?></span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-clipboard-check"></i> عطل الاعتماد</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> ساعات العطل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['approval_fault']); ?></span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-ellipsis-h"></i> أعطال أخرى</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> ساعات أخرى</span>
                <span class="info-value"><?php echo htmlspecialchars($row['other_fault_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-sum"></i> مجموع التعطل</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($row['total_fault_hours']); ?></strong></span>
            </div>
        </div>
    </div>

    <!-- عداد الساعات -->
    <h4 class="section-title">
        <i class="fas fa-tachometer-alt"></i>
        عداد الساعات
    </h4>
    <div class="info-cards-grid">
        <div class="info-card info">
            <h5><i class="fas fa-play-circle"></i> عداد البداية</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> القراءة</span>
                <span class="info-value"><?php echo htmlspecialchars($row['start_hours'].":".$row['start_minutes'].":".$row['start_seconds']); ?></span>
            </div>
        </div>

        <div class="info-card info">
            <h5><i class="fas fa-stop-circle"></i> عداد النهاية</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> القراءة</span>
                <span class="info-value"><?php echo htmlspecialchars($row['end_hours'].":".$row['end_minutes'].":".$row['end_seconds']); ?></span>
            </div>
        </div>

        <div class="info-card success">
            <h5><i class="fas fa-calculator"></i> فرق العداد</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-minus"></i> الفرق</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($row['counter_diff']); ?></strong></span>
            </div>
        </div>
    </div>

    <!-- تفاصيل الأعطال -->
    <h4 class="section-title">
        <i class="fas fa-clipboard-list"></i>
        تفاصيل الأعطال
    </h4>
    <div class="info-cards-grid">
        <div class="info-card danger">
            <h5><i class="fas fa-bug"></i> نوع العطل</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-tag"></i> النوع</span>
                <span class="info-value"><?php echo htmlspecialchars($row['fault_type'] ?: '-'); ?></span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-cogs"></i> الجزء المعطل</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-puzzle-piece"></i> الجزء</span>
                <span class="info-value"><?php echo htmlspecialchars($row['fault_part'] ?: '-'); ?></span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-file-alt"></i> تفاصيل العطل</h5>
            <div class="info-item">
                <span class="info-value" style="text-align: justify; line-height: 1.6;">
                    <?php echo htmlspecialchars($row['fault_details'] ? $row['fault_details'] : 'لا توجد تفاصيل'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ساعات المشغل -->
    <h4 class="section-title">
        <i class="fas fa-user-clock"></i>
        ساعات المشغل
    </h4>
    <div class="info-cards-grid">
        <div class="info-card success">
            <h5><i class="fas fa-user-check"></i> ساعات عمل المشغل</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> ساعات العمل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['operator_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-plus-circle"></i> ساعات إضافية</span>
                <span class="info-value"><?php echo htmlspecialchars($row['extra_operator_hours']); ?></span>
            </div>
        </div>

        <div class="info-card warning">
            <h5><i class="fas fa-pause-circle"></i> ساعات الاستعداد</h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-truck"></i> استعداد الآلية</span>
                <span class="info-value"><?php echo htmlspecialchars($row['machine_standby_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-wrench"></i> استعداد الجاكمر</span>
                <span class="info-value"><?php echo htmlspecialchars($row['jackhammer_standby_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-box"></i> استعداد الجردل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['bucket_standby_hours']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-user-clock"></i> استعداد المشغل</span>
                <span class="info-value"><?php echo htmlspecialchars($row['operator_standby_hours']); ?></span>
            </div>
        </div>
    </div>

    <!-- الملاحظات -->
    <h4 class="section-title">
        <i class="fas fa-sticky-note"></i>
        الملاحظات
    </h4>
    <div class="info-cards-grid">
        <div class="info-card primary">
            <h5><i class="fas fa-comment-dots"></i> ملاحظات ساعات العمل</h5>
            <div class="info-item">
                <span class="info-value" style="text-align: justify; line-height: 1.6;">
                    <?php echo htmlspecialchars($row['work_notes'] ? $row['work_notes'] : 'لا توجد ملاحظات'); ?>
                </span>
            </div>
        </div>

        <div class="info-card danger">
            <h5><i class="fas fa-comment-alt"></i> ملاحظات ساعات التعطل</h5>
            <div class="info-item">
                <span class="info-value" style="text-align: justify; line-height: 1.6;">
                    <?php echo htmlspecialchars($row['fault_notes'] ? $row['fault_notes'] : 'لا توجد ملاحظات'); ?>
                </span>
            </div>
        </div>

        <div class="info-card info">
            <h5><i class="fas fa-user-edit"></i> ملاحظات المشغل</h5>
            <div class="info-item">
                <span class="info-value" style="text-align: justify; line-height: 1.6;">
                    <?php echo htmlspecialchars($row['operator_notes'] ? $row['operator_notes'] : 'لا توجد ملاحظات'); ?>
                </span>
            </div>
        </div>

        <div class="info-card warning">
            <h5><i class="fas fa-user-tie"></i> ملاحظات مشرفي الساعات</h5>
            <div class="info-item">
                <span class="info-value" style="text-align: justify; line-height: 1.6;">
                    <?php echo htmlspecialchars($row['time_notes'] ? $row['time_notes'] : 'لا توجد ملاحظات'); ?>
                </span>
            </div>
        </div>

        <div class="info-card success">
            <h5><i class="fas fa-clipboard"></i> ملاحظات عامة</h5>
            <div class="info-item">
                <span class="info-value" style="text-align: justify; line-height: 1.6;">
                    <?php echo htmlspecialchars($row['general_notes'] ? $row['general_notes'] : 'لا توجد ملاحظات'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php } ?>


    <br/> <br/> <br/>


</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

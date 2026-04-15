<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
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
    <link rel="stylesheet" href="/ems/assets/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="/ems/assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <!-- Google Fonts -->
    <link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

    <style>
        :root {
            --primary: #0f2444;
            --primary-light: #1a3a6e;
            --accent: #e8b84b;
            --surface: #f0f4fa;
            --surface-2: #ffffff;
            --text-primary: #0f1a2e;
            --text-secondary: #5a6a82;
            --text-muted: #9aa5b4;
            --border: #e2e8f4;
            --success: #059669;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --warning: #d97706;
            --warning-bg: #fffbeb;
            --warning-border: #fde68a;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
            --danger-border: #fca5a5;
            --info: #0369a1;
            --info-bg: #eff6ff;
            --info-border: #bae6fd;
            --shadow-sm: 0 1px 3px rgba(15,36,68,0.07), 0 1px 2px rgba(15,36,68,0.04);
            --shadow-md: 0 4px 16px rgba(15,36,68,0.09), 0 2px 6px rgba(15,36,68,0.05);
            --shadow-lg: 0 10px 40px rgba(15,36,68,0.13), 0 4px 12px rgba(15,36,68,0.07);
            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 24px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--surface);
            color: var(--text-primary);
            direction: rtl;
            font-size: 15px;
            line-height: 1.6;
        }

        /* ========== LAYOUT ========== */
        .main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 20px 60px;
            min-height: 100vh;
        }

        /* ========== PAGE HERO ========== */
        .page-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 60%, #2a5298 100%);
            border-radius: var(--radius-lg);
            padding: 32px 40px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: -60px; left: -60px;
            width: 220px; height: 220px;
            background: rgba(232,184,75,0.10);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-hero::after {
            content: '';
            position: absolute;
            bottom: -80px; left: 120px;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-hero-inner {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
            justify-content: space-between;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            border: 1.5px solid rgba(255,255,255,0.28);
            border-radius: 12px;
            padding: 9px 20px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-back:hover {
            background: rgba(232,184,75,0.22);
            border-color: rgba(232,184,75,0.55);
            color: #fff;
        }

        .hero-icon {
            width: 64px; height: 64px;
            background: rgba(232,184,75,0.18);
            border: 2px solid rgba(232,184,75,0.38);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            color: var(--accent);
            flex-shrink: 0;
        }

        .hero-title {
            color: #fff;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
        }

        .hero-subtitle {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            margin-top: 4px;
            font-weight: 400;
        }

        /* ========== SECTION BLOCK ========== */
        .section-block {
            background: var(--surface-2);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header-icon {
            width: 38px; height: 38px;
            background: rgba(232,184,75,0.18);
            border: 1.5px solid rgba(232,184,75,0.38);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent);
            font-size: 16px;
            flex-shrink: 0;
        }

        .section-header h4 {
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            margin: 0;
        }

        /* ========== CARDS GRID ========== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            padding: 24px;
        }

        /* ========== DETAIL CARD ========== */
        .detail-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: box-shadow 0.22s, transform 0.22s;
        }

        .detail-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .detail-card-header {
            padding: 13px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--border);
        }

        .detail-card-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .detail-card-icon.primary  { background: #eef2ff; color: var(--primary); }
        .detail-card-icon.success  { background: var(--success-bg); color: var(--success); }
        .detail-card-icon.warning  { background: var(--warning-bg); color: var(--warning); }
        .detail-card-icon.danger   { background: var(--danger-bg); color: var(--danger); }
        .detail-card-icon.info     { background: var(--info-bg); color: var(--info); }

        .detail-card-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .detail-card-body { padding: 14px 16px; }

        /* ========== DETAIL ROW ========== */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
            gap: 12px;
        }

        .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
        .detail-row:first-child { padding-top: 0; }

        .detail-label {
            font-size: 12.5px;
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .detail-value {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-primary);
            text-align: left;
            word-break: break-word;
        }

        .detail-value.mono {
            font-size: 14px;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.5px;
        }

        .detail-value.note-text {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-align: right;
            line-height: 1.7;
        }

        /* ========== HIGHLIGHT CHIPS ========== */
        .chip-total {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
        }

        .chip-success {
            background: var(--success-bg);
            color: var(--success);
            border: 1.5px solid var(--success-border);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        .chip-warning {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1.5px solid var(--warning-border);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        .chip-danger {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1.5px solid var(--danger-border);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        .chip-info {
            background: var(--info-bg);
            color: var(--info);
            border: 1.5px solid var(--info-border);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        /* Shift badge */
        .shift-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 16px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 13px;
        }

        .shift-badge.day {
            background: #fef9c3;
            color: #854d0e;
            border: 1.5px solid #fde047;
        }

        .shift-badge.night {
            background: #eef2ff;
            color: var(--primary);
            border: 1.5px solid #c7d2fe;
        }

        /* Counter display */
        .counter-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 12px 0 4px;
        }

        .counter-seg {
            background: var(--primary);
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            min-width: 46px;
            text-align: center;
        }

        .counter-sep {
            color: var(--text-muted);
            font-size: 20px;
            font-weight: 700;
            margin: 0 2px;
        }

        /* Empty / no notes */
        .no-data {
            color: var(--text-muted);
            font-size: 13px;
            font-style: italic;
            font-weight: 400;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main { padding: 14px 12px 48px; }
            .page-hero { padding: 22px 18px; border-radius: var(--radius-md); }
            .hero-title { font-size: 20px; }
            .hero-icon { width: 50px; height: 50px; font-size: 20px; }
            .cards-grid { padding: 16px; gap: 14px; }
            .section-header { padding: 14px 18px; }
        }

        @media (max-width: 480px) {
            .cards-grid { grid-template-columns: 1fr; padding: 12px; gap: 12px; }
            .hero-title { font-size: 17px; }
        }

        @media (min-width: 1200px) {
            .cards-grid.grid-4 { grid-template-columns: repeat(4, 1fr); }
            .cards-grid.grid-3 { grid-template-columns: repeat(3, 1fr); }
            .cards-grid.grid-5 { grid-template-columns: repeat(5, 1fr); }
        }

        @media (min-width: 768px) and (max-width: 1199px) {
            .cards-grid.grid-4 { grid-template-columns: repeat(2, 1fr); }
            .cards-grid.grid-5 { grid-template-columns: repeat(2, 1fr); }
        }

        /* ========== SCROLLBAR ========== */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--surface); }
        ::-webkit-scrollbar-thumb { background: #c1cfe0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body>

<div class="main">

    <!-- ===== PAGE HERO ===== -->
    <div class="page-hero">
        <div class="page-hero-inner">
            <div style="display:flex; align-items:center; gap:20px;">
                <div class="hero-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h1 class="hero-title">تفاصيل ساعات العمل</h1>
                    <p class="hero-subtitle">عرض تقرير مفصّل لجميع ساعات التشغيل والأعطال والمشغل</p>
                </div>
            </div>
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

<?php
include '../config.php';
$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die('Unauthorized company context');
}

$project = intval($_GET['id']);
$details_scope = "";
if (!$is_super_admin) {
    if (db_table_has_column($conn, 'timesheet', 'company_id')) {
        $details_scope = " AND t.company_id = $company_id";
    } else {
        $details_scope = " AND EXISTS (
            SELECT 1
            FROM project p2
            LEFT JOIN users su2 ON su2.id = p2.created_by
            LEFT JOIN clients sc2 ON sc2.id = p2.company_client_id
            LEFT JOIN users scu2 ON scu2.id = sc2.created_by
            WHERE p2.id = o.project_id
              AND (su2.company_id = $company_id OR scu2.company_id = $company_id)
        )";
    }
}

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
        WHERE t.id = $project" . $details_scope . "
        ORDER BY t.date DESC";

$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $shift_display = $row['shift'] == "D" ? "صباح" : "مساء";
    $shift_class   = $row['shift'] == "D" ? "day" : "night";
    $shift_icon    = $row['shift'] == "D" ? "fas fa-sun" : "fas fa-moon";
?>

    <!-- ============================= 1. المعلومات العامة ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-info-circle"></i></div>
            <h4>المعلومات العامة</h4>
        </div>
        <div class="cards-grid grid-4">

            <!-- المشغل -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon primary"><i class="fas fa-user-tie"></i></div>
                    <span class="detail-card-title">بيانات المشغل</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-id-card"></i> اسم المشغل</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['driver_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- المعدة -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-truck-moving"></i></div>
                    <span class="detail-card-title">بيانات المعدة</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-barcode"></i> الكود</span>
                        <span class="detail-value chip-info"><?php echo htmlspecialchars($row['equipment_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-tag"></i> الاسم</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['equipment_fullname']); ?></span>
                    </div>
                </div>
            </div>

            <!-- المشروع -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-project-diagram"></i></div>
                    <span class="detail-card-title">بيانات المشروع</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-building"></i> اسم المشروع</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['project_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- الوردية والتاريخ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-calendar-alt"></i></div>
                    <span class="detail-card-title">الوردية والتاريخ</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="<?php echo $shift_icon; ?>"></i> الوردية</span>
                        <span class="detail-value">
                            <span class="shift-badge <?php echo $shift_class; ?>">
                                <i class="<?php echo $shift_icon; ?>"></i>
                                <?php echo $shift_display; ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-calendar-day"></i> التاريخ</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['date']); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 2. ساعات العمل ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-business-time"></i></div>
            <h4>ساعات العمل</h4>
        </div>
        <div class="cards-grid grid-4">

            <!-- ساعات الوردية -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-clock"></i></div>
                    <span class="detail-card-title">ساعات الوردية</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-hourglass-start"></i> ساعات الوردية</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['shift_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-check-circle"></i> المنفذة</span>
                        <span class="detail-value"><span class="chip-success"><?php echo htmlspecialchars($row['executed_hours']); ?></span></span>
                    </div>
                </div>
            </div>

            <!-- ساعات معدات إضافية -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-tools"></i></div>
                    <span class="detail-card-title">ساعات معدات إضافية</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-box"></i> الجردل</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['bucket_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-wrench"></i> الجاكمر</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['jackhammer_hours']); ?></span>
                    </div>
                </div>
            </div>

            <!-- الساعات الإضافية -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-plus-circle"></i></div>
                    <span class="detail-card-title">الساعات الإضافية</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-plus"></i> إضافية</span>
                        <span class="detail-value"><span class="chip-warning"><?php echo htmlspecialchars($row['extra_hours']); ?></span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-calculator"></i> مجموع الإضافي</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['extra_hours_total']); ?></span>
                    </div>
                </div>
            </div>

            <!-- ساعات الاستعداد -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon primary"><i class="fas fa-pause-circle"></i></div>
                    <span class="detail-card-title">ساعات الاستعداد</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-user-clock"></i> استعداد العميل</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-check-double"></i> استعداد اعتماد</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['dependence_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-sigma"></i> مجموع ساعات العمل</span>
                        <span class="detail-value"><span class="chip-total"><?php echo htmlspecialchars($row['total_work_hours']); ?></span></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 3. ساعات الأعطال ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h4>ساعات الأعطال والتعطل</h4>
        </div>
        <div class="cards-grid grid-5">

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-user-times"></i></div>
                    <span class="detail-card-title">عطل HR</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> ساعات</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['hr_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-wrench"></i></div>
                    <span class="detail-card-title">عطل الصيانة</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> ساعات</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['maintenance_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-chart-line"></i></div>
                    <span class="detail-card-title">عطل التسويق</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> ساعات</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['marketing_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-clipboard-check"></i></div>
                    <span class="detail-card-title">عطل الاعتماد</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> ساعات</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['approval_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-ellipsis-h"></i></div>
                    <span class="detail-card-title">أعطال أخرى</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> ساعات أخرى</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['other_fault_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-sigma"></i> مجموع التعطل</span>
                        <span class="detail-value"><span class="chip-total"><?php echo htmlspecialchars($row['total_fault_hours']); ?></span></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 4. عداد الساعات ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-tachometer-alt"></i></div>
            <h4>عداد الساعات</h4>
        </div>
        <div class="cards-grid grid-3">

            <!-- عداد البداية -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-play-circle"></i></div>
                    <span class="detail-card-title">عداد البداية</span>
                </div>
                <div class="detail-card-body">
                    <div class="counter-display">
                        <span class="counter-seg"><?php echo str_pad($row['start_hours'],2,'0',STR_PAD_LEFT); ?></span>
                        <span class="counter-sep">:</span>
                        <span class="counter-seg"><?php echo str_pad($row['start_minutes'],2,'0',STR_PAD_LEFT); ?></span>
                        <span class="counter-sep">:</span>
                        <span class="counter-seg"><?php echo str_pad($row['start_seconds'],2,'0',STR_PAD_LEFT); ?></span>
                    </div>
                </div>
            </div>

            <!-- عداد النهاية -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-stop-circle"></i></div>
                    <span class="detail-card-title">عداد النهاية</span>
                </div>
                <div class="detail-card-body">
                    <div class="counter-display">
                        <span class="counter-seg"><?php echo str_pad($row['end_hours'],2,'0',STR_PAD_LEFT); ?></span>
                        <span class="counter-sep">:</span>
                        <span class="counter-seg"><?php echo str_pad($row['end_minutes'],2,'0',STR_PAD_LEFT); ?></span>
                        <span class="counter-sep">:</span>
                        <span class="counter-seg"><?php echo str_pad($row['end_seconds'],2,'0',STR_PAD_LEFT); ?></span>
                    </div>
                </div>
            </div>

            <!-- فرق العداد -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-calculator"></i></div>
                    <span class="detail-card-title">فرق العداد</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row" style="padding-top: 10px; padding-bottom: 10px; justify-content: center;">
                        <span class="chip-total" style="font-size: 18px; padding: 8px 28px;">
                            <i class="fas fa-minus"></i>
                            <?php echo htmlspecialchars($row['counter_diff']); ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 5. تفاصيل الأعطال ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-clipboard-list"></i></div>
            <h4>تفاصيل الأعطال</h4>
        </div>
        <div class="cards-grid grid-3">

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-bug"></i></div>
                    <span class="detail-card-title">نوع العطل</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-tag"></i> النوع</span>
                        <span class="detail-value">
                            <?php if($row['fault_type']): ?>
                                <span class="chip-danger"><?php echo htmlspecialchars($row['fault_type']); ?></span>
                            <?php else: ?>
                                <span class="no-data">—</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-cogs"></i></div>
                    <span class="detail-card-title">الجزء المعطل</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-puzzle-piece"></i> الجزء</span>
                        <span class="detail-value">
                            <?php if($row['fault_part']): ?>
                                <span class="chip-danger"><?php echo htmlspecialchars($row['fault_part']); ?></span>
                            <?php else: ?>
                                <span class="no-data">—</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-file-alt"></i></div>
                    <span class="detail-card-title">تفاصيل العطل</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['fault_details'] ? $row['fault_details'] : 'لا توجد تفاصيل'); ?>
                    </span>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 6. ساعات المشغل ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-user-clock"></i></div>
            <h4>ساعات المشغل</h4>
        </div>
        <div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px,1fr));">

            <!-- ساعات عمل المشغل -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-user-check"></i></div>
                    <span class="detail-card-title">ساعات عمل المشغل</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> ساعات العمل</span>
                        <span class="detail-value"><span class="chip-success"><?php echo htmlspecialchars($row['operator_hours']); ?></span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-plus-circle"></i> ساعات إضافية</span>
                        <span class="detail-value"><span class="chip-warning"><?php echo htmlspecialchars($row['extra_operator_hours']); ?></span></span>
                    </div>
                </div>
            </div>

            <!-- ساعات الاستعداد -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-pause-circle"></i></div>
                    <span class="detail-card-title">ساعات الاستعداد</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-truck"></i> استعداد الآلية</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['machine_standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-wrench"></i> استعداد الجاكمر</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['jackhammer_standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-box"></i> استعداد الجردل</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['bucket_standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-user-clock"></i> استعداد المشغل</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['operator_standby_hours']); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 7. الملاحظات ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-sticky-note"></i></div>
            <h4>الملاحظات</h4>
        </div>
        <div class="cards-grid grid-3" style="grid-template-columns: repeat(auto-fit, minmax(240px,1fr));">

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon primary"><i class="fas fa-comment-dots"></i></div>
                    <span class="detail-card-title">ملاحظات ساعات العمل</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['work_notes'] ? $row['work_notes'] : 'لا توجد ملاحظات'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-comment-alt"></i></div>
                    <span class="detail-card-title">ملاحظات ساعات التعطل</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['fault_notes'] ? $row['fault_notes'] : 'لا توجد ملاحظات'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-user-edit"></i></div>
                    <span class="detail-card-title">ملاحظات المشغل</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['operator_notes'] ? $row['operator_notes'] : 'لا توجد ملاحظات'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-user-tie"></i></div>
                    <span class="detail-card-title">ملاحظات مشرفي الساعات</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['time_notes'] ? $row['time_notes'] : 'لا توجد ملاحظات'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-clipboard"></i></div>
                    <span class="detail-card-title">ملاحظات عامة</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['general_notes'] ? $row['general_notes'] : 'لا توجد ملاحظات'); ?>
                    </span>
                </div>
            </div>

        </div>
    </div>

<?php } ?>

</div><!-- end .main -->

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>



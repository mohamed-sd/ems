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
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | ØªÙØ§ØµÙŠÙ„ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS Ø§Ù„Ù…ÙˆÙ‚Ø¹ -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">

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
                    <h1 class="hero-title">ØªÙØ§ØµÙŠÙ„ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</h1>
                    <p class="hero-subtitle">Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ù…ÙØµÙ‘Ù„ Ù„Ø¬Ù…ÙŠØ¹ Ø³Ø§Ø¹Ø§Øª Ø§Ù„ØªØ´ØºÙŠÙ„ ÙˆØ§Ù„Ø£Ø¹Ø·Ø§Ù„ ÙˆØ§Ù„Ù…Ø´ØºÙ„</p>
                </div>
            </div>
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
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
    $shift_display = $row['shift'] == "D" ? "ØµØ¨Ø§Ø­" : "Ù…Ø³Ø§Ø¡";
    $shift_class   = $row['shift'] == "D" ? "day" : "night";
    $shift_icon    = $row['shift'] == "D" ? "fas fa-sun" : "fas fa-moon";
?>

    <!-- ============================= 1. Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø¹Ø§Ù…Ø© ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-info-circle"></i></div>
            <h4>Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø¹Ø§Ù…Ø©</h4>
        </div>
        <div class="cards-grid grid-4">

            <!-- Ø§Ù„Ù…Ø´ØºÙ„ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon primary"><i class="fas fa-user-tie"></i></div>
                    <span class="detail-card-title">Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø´ØºÙ„</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-id-card"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø´ØºÙ„</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['driver_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Ø§Ù„Ù…Ø¹Ø¯Ø© -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-truck-moving"></i></div>
                    <span class="detail-card-title">Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¹Ø¯Ø©</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-barcode"></i> Ø§Ù„ÙƒÙˆØ¯</span>
                        <span class="detail-value chip-info"><?php echo htmlspecialchars($row['equipment_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-tag"></i> Ø§Ù„Ø§Ø³Ù…</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['equipment_fullname']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-project-diagram"></i></div>
                    <span class="detail-card-title">Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-building"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['project_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Ø§Ù„ÙˆØ±Ø¯ÙŠØ© ÙˆØ§Ù„ØªØ§Ø±ÙŠØ® -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-calendar-alt"></i></div>
                    <span class="detail-card-title">Ø§Ù„ÙˆØ±Ø¯ÙŠØ© ÙˆØ§Ù„ØªØ§Ø±ÙŠØ®</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="<?php echo $shift_icon; ?>"></i> Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</span>
                        <span class="detail-value">
                            <span class="shift-badge <?php echo $shift_class; ?>">
                                <i class="<?php echo $shift_icon; ?>"></i>
                                <?php echo $shift_display; ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-calendar-day"></i> Ø§Ù„ØªØ§Ø±ÙŠØ®</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['date']); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 2. Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-business-time"></i></div>
            <h4>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</h4>
        </div>
        <div class="cards-grid grid-4">

            <!-- Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ© -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-clock"></i></div>
                    <span class="detail-card-title">Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-hourglass-start"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['shift_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-check-circle"></i> Ø§Ù„Ù…Ù†ÙØ°Ø©</span>
                        <span class="detail-value"><span class="chip-success"><?php echo htmlspecialchars($row['executed_hours']); ?></span></span>
                    </div>
                </div>
            </div>

            <!-- Ø³Ø§Ø¹Ø§Øª Ù…Ø¹Ø¯Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ© -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-tools"></i></div>
                    <span class="detail-card-title">Ø³Ø§Ø¹Ø§Øª Ù…Ø¹Ø¯Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-box"></i> Ø§Ù„Ø¬Ø±Ø¯Ù„</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['bucket_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-wrench"></i> Ø§Ù„Ø¬Ø§ÙƒÙ…Ø±</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['jackhammer_hours']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¥Ø¶Ø§ÙÙŠØ© -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-plus-circle"></i></div>
                    <span class="detail-card-title">Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¥Ø¶Ø§ÙÙŠØ©</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-plus"></i> Ø¥Ø¶Ø§ÙÙŠØ©</span>
                        <span class="detail-value"><span class="chip-warning"><?php echo htmlspecialchars($row['extra_hours']); ?></span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-calculator"></i> Ù…Ø¬Ù…ÙˆØ¹ Ø§Ù„Ø¥Ø¶Ø§ÙÙŠ</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['extra_hours_total']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon primary"><i class="fas fa-pause-circle"></i></div>
                    <span class="detail-card-title">Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø§Ø³ØªØ¹Ø¯Ø§Ø¯</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-user-clock"></i> Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ø§Ù„Ø¹Ù…ÙŠÙ„</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-check-double"></i> Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ø§Ø¹ØªÙ…Ø§Ø¯</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['dependence_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-sigma"></i> Ù…Ø¬Ù…ÙˆØ¹ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</span>
                        <span class="detail-value"><span class="chip-total"><?php echo htmlspecialchars($row['total_work_hours']); ?></span></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 3. Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø£Ø¹Ø·Ø§Ù„ ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h4>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø£Ø¹Ø·Ø§Ù„ ÙˆØ§Ù„ØªØ¹Ø·Ù„</h4>
        </div>
        <div class="cards-grid grid-5">

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-user-times"></i></div>
                    <span class="detail-card-title">Ø¹Ø·Ù„ HR</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['hr_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-wrench"></i></div>
                    <span class="detail-card-title">Ø¹Ø·Ù„ Ø§Ù„ØµÙŠØ§Ù†Ø©</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['maintenance_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-chart-line"></i></div>
                    <span class="detail-card-title">Ø¹Ø·Ù„ Ø§Ù„ØªØ³ÙˆÙŠÙ‚</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['marketing_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-clipboard-check"></i></div>
                    <span class="detail-card-title">Ø¹Ø·Ù„ Ø§Ù„Ø§Ø¹ØªÙ…Ø§Ø¯</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª</span>
                        <span class="detail-value"><span class="chip-danger"><?php echo htmlspecialchars($row['approval_fault']); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-ellipsis-h"></i></div>
                    <span class="detail-card-title">Ø£Ø¹Ø·Ø§Ù„ Ø£Ø®Ø±Ù‰</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø£Ø®Ø±Ù‰</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['other_fault_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-sigma"></i> Ù…Ø¬Ù…ÙˆØ¹ Ø§Ù„ØªØ¹Ø·Ù„</span>
                        <span class="detail-value"><span class="chip-total"><?php echo htmlspecialchars($row['total_fault_hours']); ?></span></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 4. Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø³Ø§Ø¹Ø§Øª ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-tachometer-alt"></i></div>
            <h4>Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø³Ø§Ø¹Ø§Øª</h4>
        </div>
        <div class="cards-grid grid-3">

            <!-- Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-play-circle"></i></div>
                    <span class="detail-card-title">Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©</span>
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

            <!-- Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ù†Ù‡Ø§ÙŠØ© -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-stop-circle"></i></div>
                    <span class="detail-card-title">Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ù†Ù‡Ø§ÙŠØ©</span>
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

            <!-- ÙØ±Ù‚ Ø§Ù„Ø¹Ø¯Ø§Ø¯ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-calculator"></i></div>
                    <span class="detail-card-title">ÙØ±Ù‚ Ø§Ù„Ø¹Ø¯Ø§Ø¯</span>
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

    <!-- ============================= 5. ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø£Ø¹Ø·Ø§Ù„ ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-clipboard-list"></i></div>
            <h4>ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø£Ø¹Ø·Ø§Ù„</h4>
        </div>
        <div class="cards-grid grid-3">

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-bug"></i></div>
                    <span class="detail-card-title">Ù†ÙˆØ¹ Ø§Ù„Ø¹Ø·Ù„</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-tag"></i> Ø§Ù„Ù†ÙˆØ¹</span>
                        <span class="detail-value">
                            <?php if($row['fault_type']): ?>
                                <span class="chip-danger"><?php echo htmlspecialchars($row['fault_type']); ?></span>
                            <?php else: ?>
                                <span class="no-data">â€”</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-cogs"></i></div>
                    <span class="detail-card-title">Ø§Ù„Ø¬Ø²Ø¡ Ø§Ù„Ù…Ø¹Ø·Ù„</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-puzzle-piece"></i> Ø§Ù„Ø¬Ø²Ø¡</span>
                        <span class="detail-value">
                            <?php if($row['fault_part']): ?>
                                <span class="chip-danger"><?php echo htmlspecialchars($row['fault_part']); ?></span>
                            <?php else: ?>
                                <span class="no-data">â€”</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-file-alt"></i></div>
                    <span class="detail-card-title">ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ø¹Ø·Ù„</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['fault_details'] ? $row['fault_details'] : 'Ù„Ø§ ØªÙˆØ¬Ø¯ ØªÙØ§ØµÙŠÙ„'); ?>
                    </span>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 6. Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…Ø´ØºÙ„ ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-user-clock"></i></div>
            <h4>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…Ø´ØºÙ„</h4>
        </div>
        <div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px,1fr));">

            <!-- Ø³Ø§Ø¹Ø§Øª Ø¹Ù…Ù„ Ø§Ù„Ù…Ø´ØºÙ„ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-user-check"></i></div>
                    <span class="detail-card-title">Ø³Ø§Ø¹Ø§Øª Ø¹Ù…Ù„ Ø§Ù„Ù…Ø´ØºÙ„</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</span>
                        <span class="detail-value"><span class="chip-success"><?php echo htmlspecialchars($row['operator_hours']); ?></span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-plus-circle"></i> Ø³Ø§Ø¹Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©</span>
                        <span class="detail-value"><span class="chip-warning"><?php echo htmlspecialchars($row['extra_operator_hours']); ?></span></span>
                    </div>
                </div>
            </div>

            <!-- Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-pause-circle"></i></div>
                    <span class="detail-card-title">Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø§Ø³ØªØ¹Ø¯Ø§Ø¯</span>
                </div>
                <div class="detail-card-body">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-truck"></i> Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ø§Ù„Ø¢Ù„ÙŠØ©</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['machine_standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-wrench"></i> Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ø§Ù„Ø¬Ø§ÙƒÙ…Ø±</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['jackhammer_standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-box"></i> Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ø§Ù„Ø¬Ø±Ø¯Ù„</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['bucket_standby_hours']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-user-clock"></i> Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ø§Ù„Ù…Ø´ØºÙ„</span>
                        <span class="detail-value mono"><?php echo htmlspecialchars($row['operator_standby_hours']); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================= 7. Ø§Ù„Ù…Ù„Ø§Ø­Ø¸Ø§Øª ============================= -->
    <div class="section-block">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-sticky-note"></i></div>
            <h4>Ø§Ù„Ù…Ù„Ø§Ø­Ø¸Ø§Øª</h4>
        </div>
        <div class="cards-grid grid-3" style="grid-template-columns: repeat(auto-fit, minmax(240px,1fr));">

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon primary"><i class="fas fa-comment-dots"></i></div>
                    <span class="detail-card-title">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['work_notes'] ? $row['work_notes'] : 'Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù„Ø§Ø­Ø¸Ø§Øª'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon danger"><i class="fas fa-comment-alt"></i></div>
                    <span class="detail-card-title">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„ØªØ¹Ø·Ù„</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['fault_notes'] ? $row['fault_notes'] : 'Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù„Ø§Ø­Ø¸Ø§Øª'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon info"><i class="fas fa-user-edit"></i></div>
                    <span class="detail-card-title">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø§Ù„Ù…Ø´ØºÙ„</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['operator_notes'] ? $row['operator_notes'] : 'Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù„Ø§Ø­Ø¸Ø§Øª'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon warning"><i class="fas fa-user-tie"></i></div>
                    <span class="detail-card-title">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ù…Ø´Ø±ÙÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['time_notes'] ? $row['time_notes'] : 'Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù„Ø§Ø­Ø¸Ø§Øª'); ?>
                    </span>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-icon success"><i class="fas fa-clipboard"></i></div>
                    <span class="detail-card-title">Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø¹Ø§Ù…Ø©</span>
                </div>
                <div class="detail-card-body">
                    <span class="detail-value note-text">
                        <?php echo htmlspecialchars($row['general_notes'] ? $row['general_notes'] : 'Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù„Ø§Ø­Ø¸Ø§Øª'); ?>
                    </span>
                </div>
            </div>

        </div>
    </div>

<?php } ?>

</div><!-- end .main -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config.php';
require_once '../includes/permissions_helper.php';

// 🔐 التحقق من صلاحيات المستخدم
$page_permissions = check_page_permissions($conn, 'Contracts/contracts_details.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

if (!$can_view) {
    header("Location: ../index.php?msg=لا+توجد+صلاحية+عرض+تفاصيل+العقد+❌");
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0f2444;
            --primary-light: #1a3a6e;
            --accent: #e8b84b;
            --accent-light: #f5d080;
            --surface: #f8f9fc;
            --surface-2: #ffffff;
            --text-primary: #0f1a2e;
            --text-secondary: #5a6a82;
            --text-muted: #9aa5b4;
            --border: #e2e8f4;
            --success: #059669;
            --success-bg: #ecfdf5;
            --warning: #d97706;
            --warning-bg: #fffbeb;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
            --info: #0369a1;
            --info-bg: #eff6ff;
            --purple: #7c3aed;
            --purple-bg: #f5f3ff;
            --shadow-sm: 0 1px 3px rgba(15,36,68,0.08), 0 1px 2px rgba(15,36,68,0.04);
            --shadow-md: 0 4px 16px rgba(15,36,68,0.10), 0 2px 6px rgba(15,36,68,0.06);
            --shadow-lg: 0 10px 40px rgba(15,36,68,0.14), 0 4px 12px rgba(15,36,68,0.08);
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
        .page-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* ========== PAGE HEADER ========== */
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
            background: rgba(232,184,75,0.12);
            border-radius: 50%;
        }

        .page-hero::after {
            content: '';
            position: absolute;
            bottom: -80px; left: 120px;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        .page-hero-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .hero-icon {
            width: 64px; height: 64px;
            background: rgba(232,184,75,0.2);
            border: 2px solid rgba(232,184,75,0.4);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--accent);
            flex-shrink: 0;
        }

        .hero-title {
            color: #fff;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .hero-subtitle {
            color: rgba(255,255,255,0.65);
            font-size: 14px;
            margin-top: 4px;
            font-weight: 400;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 10px 22px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            font-family: 'Tajawal', sans-serif;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.22);
            color: #fff;
            transform: translateX(3px);
        }

        /* ========== ACTIONS BAR ========== */
        .actions-section {
            background: var(--surface-2);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }

        .actions-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .actions-header-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent);
            font-size: 15px;
        }

        .actions-header h5 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            border: none;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.22s;
            color: #fff;
            letter-spacing: 0.2px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            filter: brightness(1.08);
        }

        .add-btn:active { transform: translateY(0); }

        /* ========== SUMMARY CARDS ========== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: var(--surface-2);
            border-radius: var(--radius-md);
            padding: 22px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 4px;
            height: 100%;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
        }

        .summary-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .summary-card.card-success::before { background: var(--success); }
        .summary-card.card-primary::before { background: var(--primary); }
        .summary-card.card-info::before { background: var(--info); }
        .summary-card.card-warning::before { background: var(--warning); }
        .summary-card.card-danger::before { background: var(--danger); }

        .card-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .card-head-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .card-head-icon.success { background: var(--success-bg); color: var(--success); }
        .card-head-icon.primary { background: #eef2ff; color: var(--primary); }
        .card-head-icon.info { background: var(--info-bg); color: var(--info); }
        .card-head-icon.warning { background: var(--warning-bg); color: var(--warning); }
        .card-head-icon.danger { background: var(--danger-bg); color: var(--danger); }

        .card-head h5 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-secondary);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
        }

        .status-badge.active {
            background: var(--success-bg);
            color: var(--success);
            border: 1.5px solid #6ee7b7;
        }

        .status-badge.inactive {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1.5px solid #fca5a5;
        }

        .status-badge::before {
            content: '';
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-badge.active::before { background: var(--success); box-shadow: 0 0 0 3px #a7f3d0; animation: pulse-green 2s infinite; }
        .status-badge.inactive::before { background: var(--danger); }

        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 3px #a7f3d0; }
            50% { box-shadow: 0 0 0 6px #d1fae5; }
        }

        /* Info rows inside cards */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
            gap: 12px;
        }

        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-row:first-of-type { padding-top: 0; }

        .info-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            text-align: left;
        }

        .remaining-positive { color: var(--success) !important; }
        .remaining-warning { color: var(--warning) !important; }
        .remaining-danger { color: var(--danger) !important; }

        /* ========== SECTION WRAPPER ========== */
        .section-wrapper {
            background: var(--surface-2);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header-icon {
            width: 40px; height: 40px;
            background: rgba(232,184,75,0.2);
            border: 1.5px solid rgba(232,184,75,0.4);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent);
            font-size: 17px;
        }

        .section-header h4 {
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            margin: 0;
        }

        .section-header .badge-pill {
            margin-right: auto;
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ========== DETAIL CARDS GRID ========== */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            padding: 28px;
        }

        .detail-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all 0.25s;
        }

        .detail-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }

        .detail-card-header {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }

        .detail-card-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-card-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        .detail-card-icon.primary { background: #eef2ff; color: var(--primary); }
        .detail-card-icon.success { background: var(--success-bg); color: var(--success); }
        .detail-card-icon.info { background: var(--info-bg); color: var(--info); }
        .detail-card-icon.warning { background: var(--warning-bg); color: var(--warning); }
        .detail-card-icon.system { background: #f1f5f9; color: #64748b; }

        .detail-card-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .edit-btn-small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: transparent;
            border: 1.5px solid var(--border);
            color: var(--text-secondary);
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Tajawal', sans-serif;
        }

        .edit-btn-small:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #eef2ff;
        }

        .detail-card-body { padding: 16px 18px; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 7px 0;
            border-bottom: 1px dashed var(--border);
            gap: 10px;
        }

        .detail-row:last-child { border-bottom: none; padding-bottom: 0; }

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

        /* ========== ALERT CARDS ========== */
        .alert-section {
            margin: 0 28px 28px;
            background: var(--danger-bg);
            border: 1.5px solid #fca5a5;
            border-radius: var(--radius-md);
            padding: 20px;
        }

        .alert-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            color: var(--danger);
            font-weight: 800;
            font-size: 15px;
        }

        /* ========== TABLE ========== */
        .table-section {
            background: var(--surface-2);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .table-responsive-wrapper { overflow-x: auto; }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .modern-table thead tr {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .modern-table thead th {
            color: rgba(255,255,255,0.9);
            padding: 13px 14px;
            text-align: right;
            font-weight: 700;
            font-size: 12.5px;
            white-space: nowrap;
            letter-spacing: 0.3px;
            border: none;
        }

        .modern-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        .modern-table tbody tr:last-child { border-bottom: none; }
        .modern-table tbody tr:hover { background: #f0f4ff; }

        .modern-table tbody td {
            padding: 12px 14px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .badge-count {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            padding: 3px 12px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 12px;
        }

        .badge-basic {
            background: var(--info-bg);
            color: var(--info);
            border: 1px solid #bae6fd;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 12px;
        }

        .badge-backup {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid #fde68a;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 12px;
        }

        .badge-shifts {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #a7f3d0;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 12px;
        }

        .price-chip {
            background: var(--success-bg);
            color: var(--success);
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 42px;
            color: var(--border);
            display: block;
            margin-bottom: 14px;
        }

        .empty-state p {
            font-size: 15px;
            margin: 0;
        }

        /* ========== NOTES TABLE ========== */
        .action-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 12px;
            color: #fff;
        }

        /* ========== MODALS ========== */
        .modal-content {
            border-radius: var(--radius-lg) !important;
            border: none !important;
            box-shadow: var(--shadow-lg) !important;
            font-family: 'Tajawal', sans-serif;
            overflow: hidden;
        }

        .modal-header {
            padding: 20px 24px !important;
            border-bottom: none !important;
        }

        .modal-title {
            font-size: 17px !important;
            font-weight: 800 !important;
            color: #fff !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            padding: 24px !important;
        }

        .modal-footer {
            padding: 16px 24px !important;
            border-top: 1px solid var(--border) !important;
            background: var(--surface) !important;
            gap: 10px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--border) !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            font-size: 14px !important;
            font-family: 'Tajawal', sans-serif !important;
            color: var(--text-primary) !important;
            background: var(--surface-2) !important;
            transition: all 0.2s !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(15,36,68,0.1) !important;
            outline: none !important;
        }

        .btn {
            font-family: 'Tajawal', sans-serif !important;
            font-weight: 700 !important;
            border-radius: 10px !important;
            padding: 10px 20px !important;
            font-size: 14px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 7px !important;
            transition: all 0.2s !important;
        }

        .btn:hover { transform: translateY(-1px); }

        .btn-secondary {
            background: var(--surface) !important;
            border: 1.5px solid var(--border) !important;
            color: var(--text-secondary) !important;
        }

        .btn-secondary:hover {
            background: var(--border) !important;
        }

        .alert {
            border-radius: 12px !important;
            border: none !important;
            padding: 14px 18px !important;
            font-size: 13.5px !important;
            font-weight: 500 !important;
            display: flex !important;
            align-items: flex-start !important;
            gap: 10px !important;
        }

        .alert i { margin-top: 2px; flex-shrink: 0; }

        .alert-info { background: var(--info-bg) !important; color: var(--info) !important; }
        .alert-warning { background: var(--warning-bg) !important; color: var(--warning) !important; }
        .alert-danger { background: var(--danger-bg) !important; color: var(--danger) !important; }
        .alert-success { background: var(--success-bg) !important; color: var(--success) !important; }

        /* ========== RADIO BUTTONS ========== */
        .pause-option {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pause-option:hover { border-color: var(--primary); }
        .pause-option.selected { border-color: var(--primary); background: #eef2ff; }

        .form-check-input:checked { background-color: var(--primary) !important; border-color: var(--primary) !important; }

        /* ========== DURATION DISPLAY ========== */
        .duration-display {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border: 1.5px solid #c7d2fe;
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-light);
            font-weight: 700;
        }

        /* ========== PAUSE INFO ========== */
        .pause-info-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1.5px solid #fde68a;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .pause-info-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #92400e;
            font-weight: 800;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .pause-info-date {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #92400e;
            font-size: 13px;
        }

        .pause-info-date span {
            background: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 700;
            color: #d97706;
        }

        .pause-info-reason {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #fde68a;
            font-size: 12.5px;
            color: #92400e;
        }

        /* ========== MERGE CONTRACT EQUIPMENTS ========== */
        .equip-section-title {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .equip-section-title.current { background: #eef2ff; color: var(--primary); border-right: 3px solid var(--primary); }
        .equip-section-title.selected { background: var(--success-bg); color: var(--success); border-right: 3px solid var(--success); }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .page-wrapper { padding: 16px 12px; }
            .page-hero { padding: 24px 20px; border-radius: var(--radius-md); }
            .hero-title { font-size: 20px; }
            .hero-icon { width: 50px; height: 50px; font-size: 20px; }
            .page-hero-inner { gap: 14px; }
            .actions-section { padding: 18px 16px; }
            .action-bar { gap: 8px; }
            .add-btn { padding: 9px 14px; font-size: 13px; }
            .cards-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .detail-grid { padding: 16px; gap: 14px; }
            .section-header { padding: 16px 18px; }
            .table-section { border-radius: var(--radius-md); }
            .modern-table thead th { padding: 10px 10px; font-size: 11.5px; }
            .modern-table tbody td { padding: 10px 10px; font-size: 12.5px; }
        }

        @media (max-width: 480px) {
            .cards-grid { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; padding: 14px; }
            .back-btn { padding: 8px 14px; font-size: 13px; }
            .hero-title { font-size: 18px; }
            .add-btn { padding: 8px 12px; font-size: 12.5px; }
        }

        @media (min-width: 1200px) {
            .cards-grid { grid-template-columns: repeat(4, 1fr); }
            .detail-grid { grid-template-columns: repeat(3, 1fr); }
        }

        /* ========== SCROLLBAR ========== */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--surface); }
        ::-webkit-scrollbar-thumb { background: #c1cfe0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>

<body>

    <?php 
    // include('../insidebar.php');
    ?>

    <div class="page-wrapper">

        <!-- ===== PAGE HERO ===== -->
        <div class="page-hero">
            <div class="page-hero-inner">
                <div class="hero-left">
                    <div class="hero-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div>
                        <h1 class="hero-title">تفاصيل العقد</h1>
                        <p class="hero-subtitle">عرض وإدارة بيانات العقد والمعدات المرتبطة</p>
                    </div>
                </div>
                <a href="javascript:history.back()" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>

        <!-- ===== ACTIONS SECTION ===== -->
        <?php if ($can_add): ?>
        <div class="actions-section">
            <div class="actions-header">
                <div class="actions-header-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <h5>إجراءات العقد</h5>
            </div>
            <div class="action-bar">
                <button class="add-btn" id="renewalBtn" title="تجديد مدة العقد" style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);">
                    <i class="fas fa-sync-alt"></i> تجديد العقد
                </button>
                <button class="add-btn" id="settlementBtn" title="تسوية الساعات المتبقية" style="background: linear-gradient(135deg, #475569 0%, #334155 100%);">
                    <i class="fas fa-balance-scale"></i> تسوية
                </button>
                <button class="add-btn" id="pauseBtn" title="إيقاف مؤقت للعقد" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                    <i class="fas fa-pause-circle"></i> إيقاف
                </button>
                <button class="add-btn" id="resumeBtn" title="استئناف العقد المتوقف" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <i class="fas fa-play-circle"></i> استئناف
                </button>
                <button class="add-btn" id="terminateBtn" title="إنهاء العقد" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                    <i class="fas fa-times-circle"></i> إنهاء
                </button>
                <button class="add-btn" id="mergeBtn" title="دمج هذا العقد مع عقد آخر" style="background: linear-gradient(135deg, #db2777 0%, #be185d 100%);">
                    <i class="fas fa-object-group"></i> دمج
                </button>
                <button class="add-btn" id="completeBtn" title="تسجيل انتهاء العقد" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                    <i class="fas fa-check-circle"></i> انتهاء العقد
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php

        $equipmentTypeMap = [];
        $equipmentTypesQuery = "SELECT id, type FROM equipments_types ORDER BY type ASC";
        $equipmentTypesResult = mysqli_query($conn, $equipmentTypesQuery);
        if ($equipmentTypesResult) {
            while ($typeRow = mysqli_fetch_assoc($equipmentTypesResult)) {
                $equipmentTypeMap[(int) $typeRow['id']] = $typeRow['type'];
            }
        }

        $contract_id = intval($_GET['id']);

        $sql = "SELECT 
            c.id, c.mine_id, c.contract_signing_date, c.grace_period_days, c.contract_duration_months, c.contract_duration_days,
            c.actual_start, c.actual_end, c.transportation, c.accommodation, c.place_for_living, 
            c.workshop, c.hours_monthly_target, c.forecasted_contracted_hours, c.created_at, c.updated_at,
            c.daily_work_hours, c.daily_operators, c.first_party, c.second_party, 
            c.witness_one, c.witness_two, c.status, c.pause_reason, c.pause_date, c.resume_date, c.termination_type, c.termination_reason, c.merged_with,
            c.equip_shifts_contract, c.shift_contract, c.equip_total_contract_daily, c.total_contract_permonth, c.total_contract_units,
            c.price_currency_contract, c.paid_contract, c.payment_time, c.guarantees, c.payment_date,
            m.mine_name, m.mine_code, p.name AS project_name
        FROM contracts c
        LEFT JOIN mines m ON c.mine_id = m.id
        LEFT JOIN project p ON m.project_id = p.id
        WHERE c.id = $contract_id
        LIMIT 1";

        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("خطأ في الاستعلام: " . mysqli_error($conn));
        }

        while ($row = mysqli_fetch_assoc($result)) {

            $today = new DateTime();
            $actual_end_date = new DateTime($row['actual_end']);
            $interval = $today->diff($actual_end_date);
            $remaining_days = (int) $interval->format('%r%a');

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

            $remaining_class = $remaining_days > 30 ? 'remaining-positive' : ($remaining_days > 0 ? 'remaining-warning' : 'remaining-danger');
            ?>

            <!-- ===== SUMMARY CARDS ===== -->
            <div class="cards-grid">

                <!-- حالة العقد -->
                <div class="summary-card <?php echo ($row['status'] == 1) ? 'card-success' : 'card-danger'; ?>">
                    <div class="card-head">
                        <div class="card-head-icon <?php echo ($row['status'] == 1) ? 'success' : 'danger'; ?>">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h5>حالة العقد</h5>
                    </div>
                    <div style="padding: 10px 0 4px; display: flex; justify-content: center;">
                        <span class="status-badge <?php echo ($row['status'] == 1) ? 'active' : 'inactive'; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                </div>

                <!-- مدة العقد -->
                <div class="summary-card card-primary">
                    <div class="card-head">
                        <div class="card-head-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h5>مدة العقد</h5>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-ruler-horizontal"></i> إجمالي المدة</span>
                        <span class="info-value"><?php echo $row['contract_duration_days']; ?> يوم</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-hourglass-half"></i> المتبقي</span>
                        <span class="info-value <?php echo $remaining_class; ?>"><?php echo $remaining_days; ?> يوم</span>
                    </div>
                </div>

                <!-- التواريخ -->
                <div class="summary-card card-info">
                    <div class="card-head">
                        <div class="card-head-icon info">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h5>التواريخ الأساسية</h5>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-pen-nib"></i> التوقيع</span>
                        <span class="info-value"><?php echo $row['contract_signing_date']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-play"></i> البدء الفعلي</span>
                        <span class="info-value"><?php echo $row['actual_start']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-flag-checkered"></i> الانتهاء</span>
                        <span class="info-value"><?php echo $row['actual_end']; ?></span>
                    </div>
                </div>

                <!-- الساعات -->
                <div class="summary-card card-warning">
                    <div class="card-head">
                        <div class="card-head-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5>الساعات التعاقدية</h5>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-bullseye"></i> الهدف الشهري</span>
                        <span class="info-value"><?php echo $row['hours_monthly_target'] * 30; ?> ساعة</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-chart-line"></i> المتوقعة</span>
                        <span class="info-value"><?php echo $row['forecasted_contracted_hours']; ?> ساعة</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-sun"></i> اليومية</span>
                        <span class="info-value"><?php echo $row['daily_work_hours']; ?> ساعة</span>
                    </div>
                </div>

            </div>

            <!-- ===== DETAIL CARDS ===== -->
            <div class="section-wrapper">
                <div class="section-header">
                    <div class="section-header-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h4>بيانات العقد التفصيلية</h4>
                </div>

                <div class="detail-grid">

                    <!-- معلومات المنجم -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <div class="detail-card-header-left">
                                <div class="detail-card-icon primary"><i class="fas fa-mountain"></i></div>
                                <span class="detail-card-title">معلومات المنجم</span>
                            </div>
                            <?php if ($can_edit): ?>
                            <button class="edit-btn-small" id="editProjectInfoBtn">
                                <i class="fas fa-pen"></i> تعديل
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="detail-card-body">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-mountain"></i> المنجم</span>
                                <span class="detail-value" id="mineDisplay"><?php echo $row['mine_name'] . ' (' . $row['mine_code'] . ')'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-project-diagram"></i> المشروع</span>
                                <span class="detail-value"><?php echo $row['project_name']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-calendar-day"></i> فترة السماح</span>
                                <span class="detail-value" id="graceDisplay"><?php echo $row['grace_period_days']; ?> يوم</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-users-cog"></i> عدد المشغلين</span>
                                <span class="detail-value" id="operatorsDisplay"><?php echo $row['daily_operators']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- الخدمات -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <div class="detail-card-header-left">
                                <div class="detail-card-icon success"><i class="fas fa-concierge-bell"></i></div>
                                <span class="detail-card-title">الخدمات المقدمة</span>
                            </div>
                            <?php if ($can_edit): ?>
                            <button class="edit-btn-small" id="editServicesBtn">
                                <i class="fas fa-pen"></i> تعديل
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="detail-card-body">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-bus"></i> النقل</span>
                                <span class="detail-value" id="transportationDisplay"><?php echo $row['transportation']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-hotel"></i> السكن</span>
                                <span class="detail-value" id="accommodationDisplay"><?php echo $row['accommodation']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-map-marker-alt"></i> مكان السكن</span>
                                <span class="detail-value" id="placeLivingDisplay"><?php echo $row['place_for_living']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-wrench"></i> الورشة</span>
                                <span class="detail-value" id="workshopDisplay"><?php echo $row['workshop']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- أطراف العقد -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <div class="detail-card-header-left">
                                <div class="detail-card-icon info"><i class="fas fa-users"></i></div>
                                <span class="detail-card-title">أطراف العقد</span>
                            </div>
                            <?php if ($can_edit): ?>
                            <button class="edit-btn-small" id="editPartiesBtn">
                                <i class="fas fa-pen"></i> تعديل
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="detail-card-body">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-user-tie"></i> الطرف الأول</span>
                                <span class="detail-value" id="firstPartyDisplay"><?php echo $row['first_party']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-user-check"></i> الطرف الثاني</span>
                                <span class="detail-value" id="secondPartyDisplay"><?php echo $row['second_party']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-eye"></i> الشاهد الأول</span>
                                <span class="detail-value" id="witnessOneDisplay"><?php echo $row['witness_one']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-eye"></i> الشاهد الثاني</span>
                                <span class="detail-value" id="witnessTwoDisplay"><?php echo $row['witness_two']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- البيانات المالية -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <div class="detail-card-header-left">
                                <div class="detail-card-icon warning"><i class="fas fa-money-bill-wave"></i></div>
                                <span class="detail-card-title">البيانات المالية</span>
                            </div>
                            <?php if ($can_edit): ?>
                            <button class="edit-btn-small" id="editPaymentBtn">
                                <i class="fas fa-pen"></i> تعديل
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="detail-card-body">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-dollar-sign"></i> العملة</span>
                                <span class="detail-value" id="currencyDisplay"><?php echo !empty($row['price_currency_contract']) ? $row['price_currency_contract'] : '-'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-money-check-alt"></i> المبلغ المدفوع</span>
                                <span class="detail-value" id="paidAmountDisplay"><?php echo !empty($row['paid_contract']) ? $row['paid_contract'] : '-'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-clock"></i> وقت الدفع</span>
                                <span class="detail-value" id="paymentTimeDisplay"><?php echo !empty($row['payment_time']) ? $row['payment_time'] : '-'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-shield-alt"></i> الضمانات</span>
                                <span class="detail-value" id="guaranteesDisplay"><?php echo !empty($row['guarantees']) ? $row['guarantees'] : '-'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-calendar-check"></i> تاريخ الدفع</span>
                                <span class="detail-value" id="paymentDateDisplay"><?php echo !empty($row['payment_date']) ? $row['payment_date'] : '-'; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- معلومات النظام -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <div class="detail-card-header-left">
                                <div class="detail-card-icon system"><i class="fas fa-database"></i></div>
                                <span class="detail-card-title">معلومات النظام</span>
                            </div>
                        </div>
                        <div class="detail-card-body">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-plus-circle"></i> تاريخ الإنشاء</span>
                                <span class="detail-value"><?php echo $row['created_at']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-edit"></i> آخر تحديث</span>
                                <span class="detail-value"><?php echo $row['updated_at']; ?></span>
                            </div>
                        </div>
                    </div>

                </div>

                <?php if ((isset($row['pause_reason']) && !empty($row['pause_reason'])) || (isset($row['termination_reason']) && !empty($row['termination_reason']))): ?>
                <div class="alert-section">
                    <div class="alert-section-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        تحذيرات وملاحظات هامة
                    </div>
                    <?php if (isset($row['pause_reason']) && !empty($row['pause_reason'])): ?>
                    <div class="detail-row" style="padding: 8px 0;">
                        <span class="detail-label" style="color: var(--danger);"><i class="fas fa-pause-circle"></i> سبب الإيقاف</span>
                        <span class="detail-value"><?php echo $row['pause_reason']; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($row['termination_reason']) && !empty($row['termination_reason'])): ?>
                    <div class="detail-row" style="padding: 8px 0;">
                        <span class="detail-label" style="color: var(--danger);"><i class="fas fa-times-circle"></i> سبب الإنهاء</span>
                        <span class="detail-value"><?php echo $row['termination_reason']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

            <?php
            $contractStatusValue = isset($row['status']) ? $row['status'] : 1;
            $mine_id = $row['mine_id'];
            $actual_end_date = $row['actual_end'];
            $pause_date = isset($row['pause_date']) ? $row['pause_date'] : '';
            $pause_reason = isset($row['pause_reason']) ? $row['pause_reason'] : '';

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

            $price_currency_contract = isset($row['price_currency_contract']) ? $row['price_currency_contract'] : '';
            $paid_contract = isset($row['paid_contract']) ? $row['paid_contract'] : '';
            $payment_time = isset($row['payment_time']) ? $row['payment_time'] : '';
            $guarantees = isset($row['guarantees']) ? $row['guarantees'] : '';
            $payment_date = isset($row['payment_date']) ? $row['payment_date'] : '';
        }
        ?>

        <!-- ===== EQUIPMENTS TABLE ===== -->
        <div class="table-section">
            <div class="section-header">
                <div class="section-header-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <h4>
                    معدات العقد
                    <?php
                    if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                        echo "<span style='font-size: 13px; opacity: 0.75; font-weight: 500;'>(العقد #" . $contract_id . " + العقد #" . $row['merged_with'] . ")</span>";
                    }
                    ?>
                </h4>
            </div>
            <div class="table-responsive-wrapper">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نوع المعدة</th>
                            <th>الحجم</th>
                            <th>العدد</th>
                            <th>أساسية</th>
                            <th>احتياطية</th>
                            <th>ورديات</th>
                            <th>ساعات/يوم</th>
                            <th>إجمالي ساعات</th>
                            <th>وحدات/شهر</th>
                            <th>الوحدة</th>
                            <th>ساعات العقد</th>
                            <th>السعر</th>
                            <th>مشغلين</th>
                            <th>مشرفين</th>
                            <th>فنيين</th>
                            <th>مساعدين</th>
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
                                echo "<td><strong style='color:var(--text-muted);'>" . $i . "</strong></td>";
                                $equipTypeLabel = isset($equipmentTypeMap[(int) $equip['equip_type']])
                                    ? $equipmentTypeMap[(int) $equip['equip_type']]
                                    : $equip['equip_type'];
                                echo "<td><strong>" . htmlspecialchars($equipTypeLabel) . "</strong></td>";
                                echo "<td>" . $equip['equip_size'] . "</td>";
                                echo "<td><span class='badge-count'>" . $equip['equip_count'] . "</span></td>";
                                echo "<td><span class='badge-basic'>" . (isset($equip['equip_count_basic']) ? $equip['equip_count_basic'] : 0) . "</span></td>";
                                echo "<td><span class='badge-backup'>" . (isset($equip['equip_count_backup']) ? $equip['equip_count_backup'] : 0) . "</span></td>";
                                echo "<td><span class='badge-shifts'>" . (isset($equip['equip_shifts']) ? $equip['equip_shifts'] : 0) . "</span></td>";
                                echo "<td>" . $equip['shift_hours'] . "</td>";
                                echo "<td>" . $equip['equip_total_month'] . "</td>";
                                echo "<td><strong style='color:var(--primary);'>" . (isset($equip['equip_monthly_target']) ? $equip['equip_monthly_target'] : 0) . "</strong></td>";
                                echo "<td>" . $equip['equip_unit'] . "</td>";
                                echo "<td><strong style='color:var(--primary);'>" . $equip['equip_total_contract'] . "</strong></td>";
                                echo "<td><span class='price-chip'>" . $equip['equip_price'] . " " . $equip['equip_price_currency'] . "</span></td>";
                                echo "<td>" . $equip['equip_operators'] . "</td>";
                                echo "<td>" . $equip['equip_supervisors'] . "</td>";
                                echo "<td>" . $equip['equip_technicians'] . "</td>";
                                echo "<td>" . $equip['equip_assistants'] . "</td>";
                                if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                                    $merged_equipments = getContractEquipments(intval($row['merged_with']), $conn);
                                    $is_from_merged = false;
                                    foreach ($merged_equipments as $m_equip) {
                                        if (
                                            $m_equip['equip_type'] == $equip['equip_type'] &&
                                            $m_equip['equip_size'] == $equip['equip_size'] &&
                                            $m_equip['equip_count'] == $equip['equip_count']
                                        ) {
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
                            echo "<tr><td colspan='17'><div class='empty-state'><i class='fas fa-inbox'></i><p>لا توجد معدات لهذا العقد</p></div></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== NOTES TABLE ===== -->
        <div class="table-section">
            <div class="section-header">
                <div class="section-header-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h4>سجل الملاحظات والتغييرات</h4>
            </div>
            <div class="table-responsive-wrapper">
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
                        $notes_query = "SELECT cn.*, u.name as user_name 
                                    FROM contract_notes cn 
                                    LEFT JOIN users u ON cn.user_id = u.id 
                                    WHERE cn.contract_id = $contract_id 
                                    ORDER BY cn.created_at DESC";
                        $notes_result = mysqli_query($conn, $notes_query);

                        if ($notes_result && mysqli_num_rows($notes_result) > 0) {
                            $j = 1;
                            while ($note = mysqli_fetch_assoc($notes_result)) {
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
                                    'primary'   => 'background: linear-gradient(135deg, #1a3a6e 0%, #2a5298 100%);',
                                    'secondary' => 'background: linear-gradient(135deg, #475569 0%, #334155 100%);',
                                    'warning'   => 'background: linear-gradient(135deg, #d97706 0%, #b45309 100%);',
                                    'success'   => 'background: linear-gradient(135deg, #059669 0%, #047857 100%);',
                                    'danger'    => 'background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);',
                                    'purple'    => 'background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);',
                                    'info'      => 'background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);'
                                ];

                                echo "<tr>";
                                echo "<td><strong style='color:var(--text-muted);'>" . $j . "</strong></td>";
                                echo "<td><span class='action-badge-pill' style='" . $badge_colors[$action_badge] . "'>" . $action_icon . " " . $action_type . "</span></td>";
                                echo "<td style='text-align: right; max-width: 300px;'>" . $note_text . "</td>";
                                echo "<td><i class='fas fa-user' style='color:var(--primary); margin-left:5px;'></i>" . ($note['user_name'] ?? 'غير محدد') . "</td>";
                                echo "<td><i class='far fa-clock' style='margin-left: 0.5rem; color:var(--text-muted);'></i>" . $note['created_at'] . "</td>";
                                echo "</tr>";
                                $j++;
                            }
                        } else {
                            echo "<tr><td colspan='5'><div class='empty-state'><i class='fas fa-clipboard-list'></i><p>لا توجد ملاحظات لهذا العقد</p></div></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- end page-wrapper -->

    <!-- ============================================================ MODALS ============================================================ -->

    <!-- Modal for Renewal -->
    <div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);">
                    <h5 class="modal-title" id="renewalModalLabel">
                        <i class="fas fa-sync-alt"></i> تجديد العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>معلومة:</strong> سيتم تجديد مدة العقد بالتواريخ الجديدة.
                    </div>
                    <div class="mb-4">
                        <label for="renewalStartDate" class="form-label">
                            <i class="far fa-calendar-alt"></i> تاريخ بدء التجديد <span style="color: red;">*</span>
                        </label>
                        <input type="date" id="renewalStartDate" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="renewalEndDate" class="form-label">
                            <i class="far fa-calendar-check"></i> تاريخ انتهاء التجديد <span style="color: red;">*</span>
                        </label>
                        <input type="date" id="renewalEndDate" class="form-control">
                    </div>
                    <div id="renewalDurationDisplay" style="display: none;">
                        <div class="duration-display">
                            <i class="fas fa-calendar-days" style="font-size: 18px;"></i>
                            <span>مدة العقد الجديدة: <strong id="calculatedDays">0</strong> يوم</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn" id="confirmRenewal"
                        style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%); color: white; border: none;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #475569 0%, #334155 100%);">
                    <h5 class="modal-title" id="settlementModalLabel">
                        <i class="fas fa-balance-scale"></i> تسوية العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>معلومة:</strong> يمكنك زيادة أو تخفيض ساعات العقد.
                    </div>
                    <div class="mb-4">
                        <label for="settlementType" class="form-label">
                            <i class="fas fa-exchange-alt"></i> نوع التسوية <span style="color: red;">*</span>
                        </label>
                        <select id="settlementType" class="form-select">
                            <option value="">-- اختر --</option>
                            <option value="increase">➕ زيادة ساعات</option>
                            <option value="decrease">➖ نقصان ساعات</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="settlementHours" class="form-label">
                            <i class="far fa-clock"></i> عدد الساعات <span style="color: red;">*</span>
                        </label>
                        <input type="number" id="settlementHours" class="form-control" min="1" placeholder="أدخل عدد الساعات">
                    </div>
                    <div class="mb-3">
                        <label for="settlementReason" class="form-label">
                            <i class="fas fa-comment-alt"></i> السبب (اختياري)
                        </label>
                        <textarea id="settlementReason" class="form-control" rows="3" placeholder="أدخل السبب"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn" id="confirmSettlement"
                        style="background: linear-gradient(135deg, #475569 0%, #334155 100%); color: white; border: none;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                    <h5 class="modal-title" id="pauseModalLabel">
                        <i class="fas fa-pause-circle"></i> إيقاف العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تنبيه:</strong> سيتم إيقاف العقد مؤقتاً. يمكنك استئنافه لاحقاً.
                    </div>
                    <div class="mb-4">
                        <label for="pauseDate" class="form-label">
                            <i class="far fa-calendar-alt"></i> تاريخ الإيقاف <span style="color: red;">*</span>
                        </label>
                        <input type="date" id="pauseDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="pauseReason" class="form-label">
                            <i class="fas fa-comment-alt"></i> سبب الإيقاف <span style="color: red;">*</span>
                        </label>
                        <textarea id="pauseReason" class="form-control" rows="4" placeholder="أدخل السبب المفصل للإيقاف"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn" id="confirmPause"
                        style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: white; border: none;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <h5 class="modal-title" id="resumeModalLabel">
                        <i class="fas fa-play-circle"></i> استئناف العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <strong>تأكيد:</strong> سيتم استئناف العقد وإعادة تفعيله.
                    </div>

                    <div class="pause-info-box">
                        <div class="pause-info-title">
                            <i class="fas fa-pause-circle"></i> معلومات الإيقاف
                        </div>
                        <div class="pause-info-date">
                            <i class="far fa-calendar-times"></i>
                            <strong>تاريخ إيقاف العقد:</strong>
                            <span><?php echo !empty($pause_date) ? date('Y-m-d', strtotime($pause_date)) : 'غير محدد'; ?></span>
                        </div>
                        <?php if (!empty($pause_reason)): ?>
                        <div class="pause-info-reason">
                            <i class="fas fa-comment-dots"></i>
                            <strong>سبب الإيقاف:</strong> <?php echo htmlspecialchars($pause_reason); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="resumeDate" class="form-label">
                            <i class="far fa-calendar-check" style="color: var(--success);"></i>
                            تاريخ استئناف العقد <span style="color: red;">*</span>
                        </label>
                        <input type="date" id="resumeDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted" style="font-size: 12px; margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> التاريخ الافتراضي هو اليوم، يمكنك تعديله حسب الحاجة
                        </small>
                    </div>

                    <div id="pauseDurationDisplay" style="display: none;">
                        <div class="duration-display" style="margin-bottom: 14px;">
                            <i class="fas fa-clock" style="font-size: 18px;"></i>
                            <span>مدة الإيقاف: <strong id="calculatedPauseDays">0</strong> يوم</span>
                        </div>
                        <div style="background: var(--surface); border: 1.5px solid var(--border); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 700; color: var(--primary); margin-bottom: 12px; font-size: 14px;">
                                <i class="fas fa-question-circle"></i> كيف تريد معالجة أيام الإيقاف؟
                            </div>
                            <div class="pause-option" onclick="selectPauseOption(this, 'extend')">
                                <div class="form-check" style="padding-right: 1.8rem; pointer-events: none;">
                                    <input class="form-check-input" type="radio" name="pauseHandling" id="extendContract" value="extend" checked style="float: right; margin-right: -1.8rem; margin-top: 0.3rem;">
                                    <label class="form-check-label" for="extendContract" style="cursor: pointer;">
                                        <span style="color: var(--success); font-weight: 700;"><i class="fas fa-plus-circle"></i> تمديد العقد</span>
                                        <small style="display: block; color: var(--text-muted); font-weight: normal; margin-top: 3px;">سيتم تأجيل تاريخ انتهاء العقد بعدد أيام الإيقاف</small>
                                    </label>
                                </div>
                            </div>
                            <div class="pause-option" onclick="selectPauseOption(this, 'deduct')">
                                <div class="form-check" style="padding-right: 1.8rem; pointer-events: none;">
                                    <input class="form-check-input" type="radio" name="pauseHandling" id="deductFromContract" value="deduct" style="float: right; margin-right: -1.8rem; margin-top: 0.3rem;">
                                    <label class="form-check-label" for="deductFromContract" style="cursor: pointer;">
                                        <span style="color: var(--danger); font-weight: 700;"><i class="fas fa-minus-circle"></i> خصم من العقد</span>
                                        <small style="display: block; color: var(--text-muted); font-weight: normal; margin-top: 3px;">سيتم تقليل تاريخ انتهاء العقد بعدد أيام الإيقاف</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="resumeReason" class="form-label">
                            <i class="fas fa-comment-alt"></i> ملاحظات (اختياري)
                        </label>
                        <textarea id="resumeReason" class="form-control" rows="3" placeholder="أدخل أي ملاحظات"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn" id="confirmResume"
                        style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border: none;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                    <h5 class="modal-title" id="terminateModalLabel">
                        <i class="fas fa-times-circle"></i> إنهاء العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تحذير:</strong> عملية الإنهاء نهائية ولا يمكن التراجع عنها!
                    </div>
                    <div class="mb-4">
                        <label for="terminationType" class="form-label">
                            <i class="fas fa-list-ul"></i> نوع الإنهاء <span style="color: red;">*</span>
                        </label>
                        <select id="terminationType" class="form-select">
                            <option value="">-- اختر النوع --</option>
                            <option value="amicable">🤝 رضائي</option>
                            <option value="hardship">⚠️ بسبب التعسر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="terminationReason" class="form-label">
                            <i class="fas fa-comment-alt"></i> السبب المفصل <span style="color: red;">*</span>
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
                <div class="modal-header" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                    <h5 class="modal-title" id="mergeModalLabel">
                        <i class="fas fa-object-group"></i> دمج العقود
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>معلومة:</strong> سيتم دمج المعدات والبيانات من هذا العقد إلى العقد المختار.
                    </div>
                    <div class="mb-4">
                        <label for="mergeWithId" class="form-label">
                            <i class="fas fa-file-contract"></i> اختر العقد للدمج معه <span style="color: red;">*</span>
                        </label>
                        <select id="mergeWithId" class="form-select">
                            <option value="">-- اختر عقد --</option>
                            <?php
                            $merge_query = "SELECT c.id, c.contract_signing_date, m.mine_name 
                                            FROM contracts c
                                            LEFT JOIN mines m ON c.mine_id = m.id
                                            WHERE c.mine_id = $mine_id AND c.id != $contract_id 
                                            ORDER BY c.id DESC";
                            $merge_result = mysqli_query($conn, $merge_query);
                            while ($m_row = mysqli_fetch_assoc($merge_result)) {
                                echo "<option value='" . $m_row['id'] . "'>العقد #" . $m_row['id'] . " - " . $m_row['contract_signing_date'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div id="mergeEquipmentsContainer" style="margin-top: 20px;">
                        <h6 style="font-weight: 700; color: var(--text-primary); margin-bottom: 14px;">معدات العقود:</h6>

                        <div class="mb-4">
                            <div class="equip-section-title current">
                                <i class="fa fa-cube"></i> معدات العقد الحالي (#<?php echo $contract_id; ?>)
                            </div>
                            <div id="currentContractEquipments">
                                <table class="table table-sm table-bordered" style="font-size: 13px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>نوع المعدة</th><th>الحجم</th><th>العدد</th><th>الساعات/الشهر</th><th>وحدات/الشهر</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $current_equipments = getContractEquipments($contract_id, $conn);
                                        if (!empty($current_equipments)) {
                                            foreach ($current_equipments as $equip) {
                                                $equipTypeLabel = isset($equipmentTypeMap[(int) $equip['equip_type']])
                                                    ? $equipmentTypeMap[(int) $equip['equip_type']]
                                                    : $equip['equip_type'];
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($equipTypeLabel) . "</td>";
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

                        <div class="mb-4">
                            <div class="equip-section-title selected">
                                <i class="fa fa-cube"></i> معدات العقد المختار
                            </div>
                            <div id="selectedContractEquipments" style="min-height: 80px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 14px;">
                                اختر عقداً لعرض معداته
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn" id="confirmMerge"
                        style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); color: white; border: none;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
                    <h5 class="modal-title" id="completeModalLabel">
                        <i class="fas fa-check-circle"></i> انتهاء العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> تسجيل انتهاء العقد بشكل طبيعي.
                    </div>
                    <div class="mb-3">
                        <label for="completeNote" class="form-label">
                            <i class="fas fa-comment-alt"></i> ملاحظات الانتهاء <span style="color: red;">*</span>
                        </label>
                        <textarea id="completeNote" class="form-control" rows="4" placeholder="أدخل ملاحظات حول انتهاء العقد" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                    <button type="button" class="btn" id="confirmComplete"
                        style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); color: white; border: none;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #1a3a6e 0%, #2a5298 100%);">
                    <h5 class="modal-title" id="editProjectInfoLabel">
                        <i class="fas fa-edit"></i> تعديل معلومات المشروع
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editGracePeriod" class="form-label">
                            <i class="fas fa-calendar-alt"></i> فترة السماح (بالأيام)
                        </label>
                        <input type="number" id="editGracePeriod" class="form-control" value="<?php echo $grace_period; ?>" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="editDailyOperators" class="form-label">
                            <i class="fas fa-users-cog"></i> عدد المشغلين اليومي
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
                <div class="modal-header" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <h5 class="modal-title" id="editServicesLabel">
                        <i class="fas fa-edit"></i> تعديل الخدمات
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTransportation" class="form-label">
                            <i class="fas fa-bus"></i> النقل (Transportation)
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
                            <i class="fas fa-hotel"></i> الإعاشة (Accommodation)
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
                            <i class="fas fa-map-marker-alt"></i> مكان السكن (Place for Living)
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
                            <i class="fas fa-wrench"></i> الورشة (Workshop)
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
                <div class="modal-header" style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);">
                    <h5 class="modal-title" id="editPartiesLabel">
                        <i class="fas fa-edit"></i> تعديل أطراف العقد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editFirstParty" class="form-label">
                            <i class="fas fa-user-tie"></i> الطرف الأول
                        </label>
                        <input type="text" id="editFirstParty" class="form-control" value="<?php echo htmlspecialchars($first_party); ?>" placeholder="اسم الطرف الأول">
                    </div>
                    <div class="mb-3">
                        <label for="editSecondParty" class="form-label">
                            <i class="fas fa-user-check"></i> الطرف الثاني
                        </label>
                        <input type="text" id="editSecondParty" class="form-control" value="<?php echo htmlspecialchars($second_party); ?>" placeholder="اسم الطرف الثاني">
                    </div>
                    <div class="mb-3">
                        <label for="editWitnessOne" class="form-label">
                            <i class="fas fa-eye"></i> الشاهد الأول
                        </label>
                        <input type="text" id="editWitnessOne" class="form-control" value="<?php echo htmlspecialchars($witness_one); ?>" placeholder="اسم الشاهد الأول">
                    </div>
                    <div class="mb-3">
                        <label for="editWitnessTwo" class="form-label">
                            <i class="fas fa-eye"></i> الشاهد الثاني
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
                <div class="modal-header" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                    <h5 class="modal-title" id="editPaymentLabel">
                        <i class="fas fa-edit"></i> تعديل البيانات المالية
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCurrency" class="form-label">
                            <i class="fas fa-dollar-sign"></i> العملة
                        </label>
                        <select id="editCurrency" class="form-select">
                            <option value="">— اختر —</option>
                            <option value="دولار" <?php echo ($price_currency_contract == 'دولار') ? 'selected' : ''; ?>>دولار</option>
                            <option value="جنيه" <?php echo ($price_currency_contract == 'جنيه') ? 'selected' : ''; ?>>جنيه</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editPaidAmount" class="form-label">
                            <i class="fas fa-money-check-alt"></i> المبلغ المدفوع
                        </label>
                        <input type="text" id="editPaidAmount" class="form-control" value="<?php echo htmlspecialchars($paid_contract); ?>" placeholder="أدخل المبلغ">
                    </div>
                    <div class="mb-3">
                        <label for="editPaymentTime" class="form-label">
                            <i class="fas fa-clock"></i> وقت الدفع
                        </label>
                        <select id="editPaymentTime" class="form-select">
                            <option value="">— اختر —</option>
                            <option value="مقدم" <?php echo ($payment_time == 'مقدم') ? 'selected' : ''; ?>>مقدم</option>
                            <option value="مؤخر" <?php echo ($payment_time == 'مؤخر' || $payment_time == ' مؤخر') ? 'selected' : ''; ?>>مؤخر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editGuarantees" class="form-label">
                            <i class="fas fa-shield-alt"></i> الضمانات
                        </label>
                        <textarea id="editGuarantees" class="form-control" rows="3" placeholder="تفاصيل الضمانات"><?php echo htmlspecialchars($guarantees); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editPaymentDate" class="form-label">
                            <i class="fas fa-calendar-check"></i> تاريخ الدفع
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const contractId = <?php echo $contract_id; ?>;
        const contractStatus = <?php echo isset($contractStatusValue) ? $contractStatusValue : 1; ?>;
        const actualEndDate = '<?php echo isset($actual_end_date) ? $actual_end_date : ''; ?>';
        const canAddActions = <?php echo $can_add ? 'true' : 'false'; ?>;
        const canEditDetails = <?php echo $can_edit ? 'true' : 'false'; ?>;

        // دالة عامة للإجراءات
        function performAction(action, data = {}) {
            if (!canAddActions) {
                alert('لا توجد صلاحية تنفيذ إجراءات العقد');
                return;
            }

            $.ajax({
                url: 'contract_actions_handler.php',
                type: 'POST',
                data: Object.assign({ action: action, contract_id: contractId }, data),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('خطأ: ' + response.message);
                    }
                },
                error: function (xhr, status, error) {
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

        // Helper for pause option selection
        function selectPauseOption(el, value) {
            document.querySelectorAll('.pause-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            document.querySelector('input[name="pauseHandling"][value="' + value + '"]').checked = true;
        }

        // أزرار الإجراءات
        $('#renewalBtn').click(function () {
            if (!canPerformAction('renewal')) return;
            if (actualEndDate) {
                $('#renewalStartDate').val(actualEndDate);
            }
            const modal = new bootstrap.Modal(document.getElementById('renewalModal'));
            modal.show();
        });

        document.getElementById('renewalModal').addEventListener('hidden.bs.modal', function () {
            $('#renewalDurationDisplay').hide();
            $('#calculatedDays').text('0');
        });

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

        $('#confirmRenewal').click(function () {
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

            const start = new Date(startDate);
            const end = new Date(endDate);
            const timeDiff = end.getTime() - start.getTime();
            const durationDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

            performAction('renewal', {
                new_start_date: startDate,
                new_end_date: endDate,
                contract_duration_days: durationDays
            });
            bootstrap.Modal.getInstance(document.getElementById('renewalModal')).hide();
            $('#renewalStartDate').val('');
            $('#renewalEndDate').val('');
            $('#renewalDurationDisplay').hide();
            $('#calculatedDays').text('0');
        });

        $('#settlementBtn').click(function () {
            if (!canPerformAction('settlement')) return;
            const modal = new bootstrap.Modal(document.getElementById('settlementModal'));
            modal.show();
        });

        $('#confirmSettlement').click(function () {
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
            bootstrap.Modal.getInstance(document.getElementById('settlementModal')).hide();
            $('#settlementType').val('');
            $('#settlementHours').val('');
            $('#settlementReason').val('');
        });

        $('#pauseBtn').click(function () {
            if (!canPerformAction('pause')) return;
            const modal = new bootstrap.Modal(document.getElementById('pauseModal'));
            modal.show();
        });

        $('#confirmPause').click(function () {
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
            bootstrap.Modal.getInstance(document.getElementById('pauseModal')).hide();
            $('#pauseReason').val('');
            $('#pauseDate').val('<?php echo date('Y-m-d'); ?>');
        });

        $('#resumeBtn').click(function () {
            if (!canPerformAction('resume')) return;
            const modal = new bootstrap.Modal(document.getElementById('resumeModal'));
            modal.show();
            calculatePauseDuration();
        });

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

        $('#confirmResume').click(function () {
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

            const pauseHandling = $('input[name="pauseHandling"]:checked').val();

            performAction('resume', {
                resume_reason: $('#resumeReason').val(),
                resume_date: resumeDate,
                pause_days: pauseDays,
                pause_handling: pauseHandling
            });
            bootstrap.Modal.getInstance(document.getElementById('resumeModal')).hide();
            $('#resumeReason').val('');
            $('#resumeDate').val('<?php echo date('Y-m-d'); ?>');
            $('#pauseDurationDisplay').hide();
            $('#calculatedPauseDays').text('0');
        });

        $('#terminateBtn').click(function () {
            if (!canPerformAction('terminate')) return;
            const modal = new bootstrap.Modal(document.getElementById('terminateModal'));
            modal.show();
        });

        $('#confirmTerminate').click(function () {
            const type = $('#terminationType').val();
            if (!type) {
                alert('الرجاء اختيار نوع الإنهاء');
                return;
            }
            performAction('terminate', {
                termination_type: type,
                termination_reason: $('#terminationReason').val()
            });
            bootstrap.Modal.getInstance(document.getElementById('terminateModal')).hide();
            $('#terminationType').val('');
            $('#terminationReason').val('');
        });

        $('#mergeBtn').click(function () {
            if (!canPerformAction('merge')) return;
            const modal = new bootstrap.Modal(document.getElementById('mergeModal'));
            modal.show();
        });

        $('#completeBtn').click(function () {
            const modal = new bootstrap.Modal(document.getElementById('completeModal'));
            modal.show();
        });

        $('#confirmComplete').click(function () {
            const note = $('#completeNote').val().trim();
            if (!note) {
                alert('الرجاء إدخال ملاحظات الانتهاء');
                return;
            }
            performAction('complete', {
                complete_note: note
            });
            bootstrap.Modal.getInstance(document.getElementById('completeModal')).hide();
            $('#completeNote').val('');
        });

        // أزرار التعديل
        $('#editProjectInfoBtn').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('editProjectInfoModal'));
            modal.show();
        });

        $('#editServicesBtn').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('editServicesModal'));
            modal.show();
        });

        $('#editPartiesBtn').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('editPartiesModal'));
            modal.show();
        });

        // حفظ معلومات المشروع
        $('#saveProjectInfo').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }

            const gracePeriod = $('#editGracePeriod').val();
            const dailyOperators = $('#editDailyOperators').val();

            $.ajax({
                url: 'update_contract_details.php',
                type: 'POST',
                data: {
                    action: 'update_project_info',
                    contract_id: contractId,
                    grace_period: gracePeriod,
                    daily_operators: dailyOperators
                },
                dataType: 'json',
                success: function (response) {
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
                error: function () {
                    alert('حدث خطأ أثناء الحفظ');
                }
            });
        });

        // حفظ الخدمات
        $('#saveServices').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }

            const transportation = $('#editTransportation').val();
            const accommodation = $('#editAccommodation').val();
            const placeLiving = $('#editPlaceLiving').val();
            const workshop = $('#editWorkshop').val();

            $.ajax({
                url: 'update_contract_details.php',
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
                success: function (response) {
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
                error: function () {
                    alert('حدث خطأ أثناء الحفظ');
                }
            });
        });

        // حفظ أطراف العقد
        $('#saveParties').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }

            const firstParty = $('#editFirstParty').val();
            const secondParty = $('#editSecondParty').val();
            const witnessOne = $('#editWitnessOne').val();
            const witnessTwo = $('#editWitnessTwo').val();

            $.ajax({
                url: 'update_contract_details.php',
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
                success: function (response) {
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
                error: function () {
                    alert('حدث خطأ أثناء الحفظ');
                }
            });
        });

        // فتح modal البيانات المالية
        $('#editPaymentBtn').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
            modal.show();
        });

        // حفظ البيانات المالية
        $('#savePayment').click(function () {
            if (!canEditDetails) {
                alert('لا توجد صلاحية تعديل بيانات العقد');
                return;
            }

            const currency = $('#editCurrency').val();
            const paidAmount = $('#editPaidAmount').val();
            const paymentTime = $('#editPaymentTime').val();
            const guarantees = $('#editGuarantees').val();
            const paymentDate = $('#editPaymentDate').val();

            $.ajax({
                url: 'update_contract_details.php',
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
                success: function (response) {
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
                error: function () {
                    alert('حدث خطأ أثناء الحفظ');
                }
            });
        });

        // تحميل معدات العقد المختار عند التغيير
        $('#mergeWithId').on('change', function () {
            const selectedContractId = $(this).val();

            if (!selectedContractId) {
                $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
                return;
            }

            $.ajax({
                url: 'get_contract_equipments.php',
                type: 'GET',
                data: { contract_id: selectedContractId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        let html = '';
                        if (response.equipments.length > 0) {
                            html = '<table class="table table-sm table-bordered" style="font-size:13px;">';
                            html += '<thead class="table-light"><tr>';
                            html += '<th>نوع المعدة</th>';
                            html += '<th>الحجم</th>';
                            html += '<th>العدد</th>';
                            html += '<th>الساعات/الشهر</th>';
                            html += '<th>وحدات/الشهر</th>';
                            html += '</tr></thead>';
                            html += '<tbody>';

                            response.equipments.forEach(function (equip) {
                                html += '<tr>';
                                html += '<td>' + (equip.equip_type_name || equip.equip_type) + '</td>';
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
                error: function (xhr, status, error) {
                    console.error('الخطأ:', error);
                    $('#selectedContractEquipments').html('<p style="text-align: center; color: #c00;">خطأ في تحميل المعدات</p>');
                }
            });
        });

        $('#confirmMerge').click(function () {
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
            bootstrap.Modal.getInstance(document.getElementById('mergeModal')).hide();
            $('#mergeWithId').val('');
            $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
        });

        function goBack() {
            if (document.referrer !== '') {
                window.history.back();
            } else {
                window.location.href = 'index.html';
            }
        }
    </script>

</body>

</html>
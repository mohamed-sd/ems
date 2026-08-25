<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die('لا يمكن تحديد الشركة الحالية');
}

// بوابة العزل — تستبدل سُلَّم النطاق اليدوي (وفيه احتياطي project/users القديم)
$dcd_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('driver contract details super') : ems_tenant_db();
?>
<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'إيكوبيشن | ملف عقد الموظف';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
<link rel="stylesheet" href="/ems/assets/css/site-identity.css">
<style>
        @import url('/ems/assets/css/local-fonts.css');

        * {
            font-family: 'Cairo', sans-serif;
        }

        body {
            background: var(--c-f5f7fa, #f5f7fa);
        }

        .main {
            padding: 2rem;
            background: var(--c-f5f7fa, #f5f7fa);
        }

        /* Page Title */
        .main h3 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px var(--c-rgba00001, rgba(0,0,0,0.1));
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
            box-shadow: 0 2px 10px var(--c-rgba000008, rgba(0,0,0,0.08));
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
            box-shadow: 0 4px 15px var(--c-rgba000015, rgba(0,0,0,0.15));
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
            background: var(--c-rgba25525525503, rgba(255,255,255,0.3));
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .aligin .add:hover::before {
            width: 300px;
            height: 300px;
        }

        .aligin .add:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px var(--c-rgba000025, rgba(0,0,0,0.25));
        }

        .aligin .add:active {
            transform: translateY(-1px);
        }

        #renewalBtn {
            background: linear-gradient(135deg, var(--c-17a2b8, #17a2b8) 0%, var(--c-138496, #138496) 100%);
        }

        #settlementBtn {
            background: linear-gradient(135deg, var(--c-6c757d, #6c757d) 0%, var(--c-545b62, #545b62) 100%);
        }

        #pauseBtn {
            background: linear-gradient(135deg, var(--c-ffc107, #ffc107) 0%, var(--c-e0a800, #e0a800) 100%);
        }

        #resumeBtn {
            background: linear-gradient(135deg, var(--c-28a745, #28a745) 0%, var(--c-218838, #218838) 100%);
        }

        #terminateBtn {
            background: linear-gradient(135deg, var(--c-dc3545, #dc3545) 0%, var(--c-c82333, #c82333) 100%);
        }

        #mergeBtn {
            background: linear-gradient(135deg, var(--c-e83e8c, #e83e8c) 0%, var(--c-d63384, #d63384) 100%);
        }

        #completeBtn {
            background: linear-gradient(135deg, var(--c-6f42c1, #6f42c1) 0%, var(--c-5a32a3, #5a32a3) 100%);
        }

        /* Report Container */
        .report {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px var(--c-rgba00001, rgba(0,0,0,0.1));
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
            background: linear-gradient(135deg, var(--c-surface, #ffffff) 0%, var(--c-f8f9fa, #f8f9fa) 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border-right: 5px solid;
            box-shadow: 0 3px 15px var(--c-rgba000008, rgba(0,0,0,0.08));
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px var(--c-rgba000015, rgba(0,0,0,0.15));
        }

        .info-card.primary { border-right-color: var(--c-667eea, #667eea); }
        .info-card.success { border-right-color: var(--c-28a745, #28a745); }
        .info-card.warning { border-right-color: var(--c-ffc107, #ffc107); }
        .info-card.danger { border-right-color: var(--c-dc3545, #dc3545); }
        .info-card.info { border-right-color: var(--c-17a2b8, #17a2b8); }

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
            border-bottom: 1px solid var(--c-e9ecef, #e9ecef);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--c-495057, #495057);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            font-weight: 500;
            color: var(--c-212529, #212529);
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
            box-shadow: 0 2px 10px var(--c-rgba000015, rgba(0,0,0,0.15));
        }

        .status-badge.active {
            background: linear-gradient(135deg, var(--c-28a745, #28a745) 0%, var(--c-20c997, #20c997) 100%);
            color: white;
        }

        .status-badge.inactive {
            background: linear-gradient(135deg, var(--c-dc3545, #dc3545) 0%, var(--c-c82333, #c82333) 100%);
            color: white;
        }

        /* Tables */
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            box-shadow: 0 3px 15px var(--c-rgba000008, rgba(0,0,0,0.08));
            margin-bottom: 2rem;}

        .modern-table thead th {
            padding: 1rem;
            text-align: center;}

        .modern-table tbody tr {
            transition: all 0.3s ease;}

        .modern-table tbody tr:hover {
            transform: scale(1.01);
            box-shadow: 0 2px 10px var(--c-rgba00001, rgba(0,0,0,0.1));}

        .modern-table tbody td {
            padding: 1rem;
            text-align: center;}

        /* Modals Enhancement */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px var(--c-rgba00002, rgba(0,0,0,0.2));
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%);
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
            background: var(--c-f8f9fa, #f8f9fa);
        }

        .form-label {
            font-weight: 600;
            color: var(--c-495057, #495057);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--c-e9ecef, #e9ecef);
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--c-667eea, #667eea);
            box-shadow: 0 0 0 0.2rem var(--c-rgba102126234025, rgba(102, 126, 234, 0.25));
        }

        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--c-rgba00002, rgba(0,0,0,0.2));
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
        /* ═══ UXW-01 ②: أنماطٌ موضعيةٌ نُقلت أصنافًا صفحيةً ببادئةِ الشاشة dcd- ═══ */
        .dcd-icon-ml { margin-left: 0.5rem; }
        .dcd-icon-ml5 { margin-left: 5px; }
        .dcd-icon-lg { font-size: 1.3rem; }
        .dcd-req { color: red; }
        .dcd-on-primary { color: white; }
        .dcd-lbl-onprimary { color: var(--c-rgba25525525509, rgba(255,255,255,0.9)); }
        .dcd-val-onprimary { color: white; font-weight: 700; }
        .dcd-accent { color: var(--c-667eea, #667eea); }
        .dcd-ok { color: var(--c-28a745, #28a745); }
        .dcd-icon-ok { color: var(--c-28a745, #28a745); margin-left: 0.5rem; }
        .dcd-icon-danger { color: var(--c-dc3545, #dc3545); margin-left: 0.5rem; }
        .dcd-align-right,
        .modern-table tbody td.dcd-align-right { text-align: right; }
        .dcd-scroll-x { overflow-x: auto; }
        .dcd-mb-1r { margin-bottom: 1rem; }
        .dcd-mt-1r { margin-top: 1rem; }
        .dcd-mb-2r { margin-bottom: 2rem; }
        .dcd-mt-20 { margin-top: 20px; }
        .dcd-minh-100 { min-height: 100px; }

        .dcd-card-driver { border-right-color: var(--c-ff6b6b, #ff6b6b); }
        .dcd-card-system { border-right-color: var(--c-6c757d, #6c757d); }
        .dcd-card-extra {
            display: none;
            background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%);
            color: white;
        }
        .dcd-edit-btn { padding: 0.25rem 0.75rem; border-radius: 8px; }

        .dcd-panel {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px var(--c-rgba00001, rgba(0,0,0,0.1));
            margin-top: 2rem;
        }
        .dcd-panel-notes { margin-bottom: 3rem; }
        .dcd-section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: var(--c-667eea, #667eea);
            font-weight: 700;
        }
        .dcd-merge-hint { font-size: 0.9rem; color: var(--c-6c757d, #6c757d); }

        .dcd-legend-basic { color: var(--c-007bff, #007bff); font-weight: 600; }
        .dcd-legend-backup { color: var(--c-ffc107, #ffc107); font-weight: 600; }
        .dcd-pill-primary,
        .dcd-pill-success,
        .dcd-pill-basic,
        .dcd-pill-backup {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .dcd-pill-primary {
            background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%);
            color: white;
        }
        .dcd-pill-success {
            background: linear-gradient(135deg, var(--c-28a745, #28a745) 0%, var(--c-20c997, #20c997) 100%);
            color: white;
        }
        .dcd-pill-basic {
            background: var(--c-e3f2fd, #e3f2fd);
            color: var(--c-007bff, #007bff);
            border-right: 3px solid var(--c-007bff, #007bff);
        }
        .dcd-pill-backup {
            background: var(--c-fffde7, #fffde7);
            color: var(--c-f57f17, #f57f17);
            border-right: 3px solid var(--c-ffc107, #ffc107);
        }

        .dcd-empty-cell,
        .modern-table tbody td.dcd-empty-cell { text-align: center; padding: 2rem; }
        .dcd-empty-icon { font-size: 3rem; color: var(--c-e9ecef, #e9ecef); margin-bottom: 1rem; }
        .dcd-empty-text { color: var(--c-ink-400, #999); font-size: 1.1rem; }
        .dcd-empty-note { text-align: center; color: var(--c-ink-400, #999); }
        .dcd-err-note { text-align: center; color: var(--c-state-danger-strong, #c00); }

        .dcd-note-badge {
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dcd-badge-primary   { background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%); }
        .dcd-badge-secondary { background: linear-gradient(135deg, var(--c-6c757d, #6c757d) 0%, var(--c-545b62, #545b62) 100%); }
        .dcd-badge-warning   { background: linear-gradient(135deg, var(--c-ffc107, #ffc107) 0%, var(--c-e0a800, #e0a800) 100%); }
        .dcd-badge-success   { background: linear-gradient(135deg, var(--c-28a745, #28a745) 0%, var(--c-20c997, #20c997) 100%); }
        .dcd-badge-danger    { background: linear-gradient(135deg, var(--c-dc3545, #dc3545) 0%, var(--c-c82333, #c82333) 100%); }
        .dcd-badge-purple    { background: linear-gradient(135deg, var(--c-6f42c1, #6f42c1) 0%, var(--c-5a32a3, #5a32a3) 100%); }
        .dcd-badge-info      { background: linear-gradient(135deg, var(--c-17a2b8, #17a2b8) 0%, var(--c-138496, #138496) 100%); }

        .dcd-back-wrap { text-align: center; margin: 2rem 0; }
        .dcd-back-btn,
        .dcd-back-btn:hover {
            background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%);
            color: white;
            border: none;
            padding: 1rem 3rem;
            border-radius: 15px;
            font-weight: 700;
            box-shadow: 0 4px 15px var(--c-rgba10212623403, rgba(102, 126, 234, 0.3));
        }

        .dcd-hd-primary   { background: linear-gradient(135deg, var(--c-667eea, #667eea) 0%, var(--c-764ba2, #764ba2) 100%); }
        .dcd-hd-info      { background: linear-gradient(135deg, var(--c-17a2b8, #17a2b8) 0%, var(--c-138496, #138496) 100%); }
        .dcd-hd-secondary { background: linear-gradient(135deg, var(--c-6c757d, #6c757d) 0%, var(--c-545b62, #545b62) 100%); }
        .dcd-hd-warning   { background: linear-gradient(135deg, var(--c-ffc107, #ffc107) 0%, var(--c-e0a800, #e0a800) 100%); }
        .dcd-hd-success   { background: linear-gradient(135deg, var(--c-28a745, #28a745) 0%, var(--c-20c997, #20c997) 100%); }
        .dcd-hd-danger    { background: linear-gradient(135deg, var(--c-dc3545, #dc3545) 0%, var(--c-c82333, #c82333) 100%); }
        .dcd-hd-purple    { background: linear-gradient(135deg, var(--c-6f42c1, #6f42c1) 0%, var(--c-5a32a3, #5a32a3) 100%); }
        .dcd-hd-amber     { background: linear-gradient(135deg, var(--c-ffc107, #ffc107) 0%, var(--c-ff9800, #ff9800) 100%); }

        .dcd-btn-info,
        .dcd-btn-secondary,
        .dcd-btn-warning,
        .dcd-btn-success,
        .dcd-btn-purple { color: white; border: none; }
        .dcd-btn-info      { background: linear-gradient(135deg, var(--c-17a2b8, #17a2b8) 0%, var(--c-138496, #138496) 100%); }
        .dcd-btn-secondary { background: linear-gradient(135deg, var(--c-6c757d, #6c757d) 0%, var(--c-545b62, #545b62) 100%); }
        .dcd-btn-warning   { background: linear-gradient(135deg, var(--c-ffc107, #ffc107) 0%, var(--c-e0a800, #e0a800) 100%); }
        .dcd-btn-success   { background: linear-gradient(135deg, var(--c-28a745, #28a745) 0%, var(--c-20c997, #20c997) 100%); }
        .dcd-btn-purple    { background: linear-gradient(135deg, var(--c-6f42c1, #6f42c1) 0%, var(--c-5a32a3, #5a32a3) 100%); }
        .dcd-btn-purple-soft {
            background: linear-gradient(135deg, var(--c-6f42c1, #6f42c1) 0%, var(--c-5a32a3, #5a32a3) 100%);
            color: white;
        }

        .dcd-duration-box {
            display: none;
            padding: 1rem;
            background: linear-gradient(135deg, var(--c-e3f2fd, #e3f2fd) 0%, var(--c-bbdefb, #bbdefb) 100%);
            border-radius: 10px;
        }
        .dcd-duration-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--c-1976d2, #1976d2);
            font-weight: 600;
        }

        .dcd-pause-box {
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--c-fff3cd, #fff3cd) 0%, var(--c-s-ffeaa7, #ffeaa7) 100%);
            border-radius: 12px;
            border-right: 5px solid var(--c-ffc107, #ffc107);
            box-shadow: 0 2px 10px var(--c-rgba255193702, rgba(255, 193, 7, 0.2));
        }
        .dcd-pause-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--c-s-856404, #856404);
            font-weight: 700;
            margin-bottom: 0.75rem;
            font-size: 1.05rem;
        }
        .dcd-pause-row {
            color: var(--c-s-856404, #856404);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dcd-pause-date {
            background: white;
            padding: 0.4rem 1rem;
            border-radius: 6px;
            font-weight: 700;
            color: var(--c-d39e00, #d39e00);
        }
        .dcd-pause-reason {
            color: var(--c-s-856404, #856404);
            font-size: 0.95rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px dashed var(--c-ffc107, #ffc107);
        }

        .dcd-lbl-lg { font-weight: 700; font-size: 1.05rem; }
        .dcd-input-lg { font-size: 1.05rem; font-weight: 600; }
        .dcd-hint-block { display: block; margin-top: 0.5rem; }
        .dcd-choice-box {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 2px solid var(--c-1976d2, #1976d2);
        }
        .dcd-choice-title {
            font-weight: 700;
            color: var(--c-1976d2, #1976d2);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dcd-radio-wrap { padding-right: 1.8rem; }
        .dcd-radio-input { float: right; margin-right: -1.8rem; margin-top: 0.3rem; }
        .dcd-radio-label { font-weight: 600; color: var(--c-495057, #495057); cursor: pointer; }
        .dcd-radio-hint {
            display: block;
            color: var(--c-6c757d, #6c757d);
            font-weight: normal;
            margin-top: 0.25rem;
            margin-right: 1.5rem;
        }

        .dcd-eq-head,
        .dcd-eq-head-alt {
            background-color: var(--c-f0f0f0, #f0f0f0);
            padding: 10px;
        }
        .dcd-eq-head { border-right: 3px solid var(--c-0066cc, #0066cc); }
        .dcd-eq-head-alt { border-right: 3px solid var(--c-28a745, #28a745); }
    </style>


<?php 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php'); ?>
<?php require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); } ?>

<div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('ملف عقد الموظف', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 (9): halat al-shasha — loading/empty/error, hidden by default
echo ems_states_bundle('لا عقد موظف معروضا الآن', 'ارجع إلى قائمة الموظفين واختر عقدا مسجلا لعرض تفاصيله');
?>


    <h3><i class="fas fa-file-contract"></i> تفاصيل عقد السائق</h3>

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

$contract_id = intval($_GET['id']);

$sql = "SELECT
            sc.id, sc.employee_id, sc.project_id, sc.project_contract_id, sc.contract_signing_date, sc.grace_period_days, sc.contract_duration_months, sc.contract_duration_days,
            sc.actual_start, sc.actual_end, sc.transportation, sc.accommodation, sc.place_for_living,
            sc.workshop, sc.hours_monthly_target, sc.forecasted_contracted_hours, sc.created_at, sc.updated_at,
            sc.daily_work_hours, sc.daily_operators, sc.first_party, sc.second_party,
            sc.witness_one, sc.witness_two, sc.status, sc.pause_reason, sc.pause_date, sc.resume_date, sc.termination_type, sc.termination_reason, sc.merged_with,
            sc.equip_shifts_contract, sc.shift_contract, sc.equip_total_contract_daily, sc.total_contract_permonth, sc.total_contract_units,
            sc.price_currency_contract, sc.paid_contract, sc.payment_time, sc.guarantees, sc.payment_date,
            s.name AS driver_name,
            op.name AS project_name
        FROM drivercontracts sc
        LEFT JOIN employees s ON sc.employee_id = s.id
        LEFT JOIN project op ON sc.project_id = op.id
        LEFT JOIN contracts c ON sc.project_contract_id = c.id
        WHERE {TENANT_SCOPE} AND sc.id = ?
        LIMIT 1";

try {
    $dcd_rows = $dcd_gate->scopedQuery(array(
        'scope'  => array('sc' => 'drivercontracts'),
        'enrich' => array('s' => 'employees', 'op' => 'project', 'c' => 'contracts'),
    ), $sql, array($contract_id));
} catch (\Throwable $t) {
    die("خطأ في الاستعلام");
}

if (empty($dcd_rows)) {
    die('العقد غير موجود أو خارج نطاق الشركة');
}

foreach ($dcd_rows as $row) {

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
        <!-- بطاقة معلومات السائق -->
        <div class="info-card dcd-card-driver">
            <h5><i class="fas fa-industry"></i> معلومات السائق</h5>
            <div class="info-item">
                <span class="info-label">اسم السائق</span>
                <span class="info-value"><?php echo htmlspecialchars($row['driver_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    <?php
                    echo htmlspecialchars($row['project_name']);
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
                <span class="info-value" data-allow-style style="color: <?php echo $remaining_days > 30 ? 'var(--c-28a745, #28a745)' : ($remaining_days > 0 ? 'var(--c-ffc107, #ffc107)' : 'var(--c-dc3545, #dc3545)'); ?>; font-weight: 700;">
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
        <div class="info-card dcd-card-extra">
            <h5 class="dcd-on-primary"><i class="fas fa-file-contract"></i> بيانات العقد الإضافية</h5>
            <div class="info-item">
                <span class="info-label dcd-lbl-onprimary">عدد الورديات</span>
                <span class="info-value dcd-val-onprimary"><?php echo isset($row['equip_shifts_contract']) ? $row['equip_shifts_contract'] : 0; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label dcd-lbl-onprimary">ساعات الوردية</span>
                <span class="info-value dcd-val-onprimary"><?php echo isset($row['shift_contract']) ? $row['shift_contract'] : 0; ?> ساعة</span>
            </div>
            <div class="info-item">
                <span class="info-label dcd-lbl-onprimary">الوحدات يوميا</span>
                <span class="info-value dcd-val-onprimary"><?php echo isset($row['equip_total_contract_daily']) ? $row['equip_total_contract_daily'] : 0; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label dcd-lbl-onprimary">وحدات الشهر</span>
                <span class="info-value dcd-val-onprimary"><?php echo isset($row['total_contract_permonth']) ? $row['total_contract_permonth'] : 0; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label dcd-lbl-onprimary">إجمالي الوحدات</span>
                <span class="info-value dcd-val-onprimary"><?php echo isset($row['total_contract_units']) ? $row['total_contract_units'] : 0; ?></span>
            </div>
        </div>
    </div>

    <!-- بطاقات تفاصيل العقد -->
    <div class="info-cards-grid">
        <!-- معلومات المشروع -->
        <div class="info-card primary">
            <h5>
                <i class="fas fa-project-diagram"></i> معلومات المشروع
                <button class="btn btn-sm btn-secondary ms-auto dcd-edit-btn" id="editProjectInfoBtn">
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
                <button class="btn btn-sm btn-primary ms-auto dcd-edit-btn" id="editServicesBtn">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-bus"></i> النقل</span>
                <span class="info-value" id="transportationDisplay"><?php echo htmlspecialchars((string)$row['transportation'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-hotel"></i> السكن</span>
                <span class="info-value" id="accommodationDisplay"><?php echo htmlspecialchars((string)$row['accommodation'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> مكان السكن</span>
                <span class="info-value" id="placeLivingDisplay"><?php echo htmlspecialchars((string)$row['place_for_living'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-wrench"></i> الورشة</span>
                <span class="info-value" id="workshopDisplay"><?php echo htmlspecialchars((string)$row['workshop'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <!-- أطراف العقد -->
        <div class="info-card info">
            <h5>
                <i class="fas fa-users"></i> أطراف العقد
                <button class="btn btn-sm btn-secondary ms-auto dcd-edit-btn" id="editPartiesBtn">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label">الطرف الأول</span>
                <span class="info-value" id="firstPartyDisplay"><?php echo htmlspecialchars((string)$row['first_party'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الطرف الثاني</span>
                <span class="info-value" id="secondPartyDisplay"><?php echo htmlspecialchars((string)$row['second_party'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الشاهد الأول</span>
                <span class="info-value" id="witnessOneDisplay"><?php echo htmlspecialchars((string)$row['witness_one'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">الشاهد الثاني</span>
                <span class="info-value" id="witnessTwoDisplay"><?php echo htmlspecialchars((string)$row['witness_two'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <!-- البيانات المالية -->
        <div class="info-card warning">
            <h5>
                <i class="fas fa-money-bill-wave"></i> البيانات المالية
                <button class="btn btn-sm btn-secondary ms-auto dcd-edit-btn" id="editPaymentBtn">
                    <i class="fas fa-edit"></i> تعديل
                </button>
            </h5>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-dollar-sign"></i> العملة</span>
                <span class="info-value" id="currencyDisplay"><?php echo !empty($row['price_currency_contract']) ? htmlspecialchars((string)$row['price_currency_contract'], ENT_QUOTES, 'UTF-8') : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-money-check-alt"></i> المبلغ المدفوع</span>
                <span class="info-value" id="paidAmountDisplay"><?php echo !empty($row['paid_contract']) ? htmlspecialchars((string)$row['paid_contract'], ENT_QUOTES, 'UTF-8') : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-clock"></i> وقت الدفع</span>
                <span class="info-value" id="paymentTimeDisplay"><?php echo !empty($row['payment_time']) ? htmlspecialchars((string)$row['payment_time'], ENT_QUOTES, 'UTF-8') : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-shield-alt"></i> الضمانات</span>
                <span class="info-value" id="guaranteesDisplay"><?php echo !empty($row['guarantees']) ? htmlspecialchars((string)$row['guarantees'], ENT_QUOTES, 'UTF-8') : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar-check"></i> تاريخ الدفع</span>
                <span class="info-value" id="paymentDateDisplay"><?php echo !empty($row['payment_date']) ? htmlspecialchars((string)$row['payment_date'], ENT_QUOTES, 'UTF-8') : '-'; ?></span>
            </div>
        </div>

        <!-- معلومات النظام -->
        <div class="info-card dcd-card-system">
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
    <div class="info-card danger dcd-mb-2r">
        <h5><i class="fas fa-exclamation-triangle"></i> تحذيرات وملاحظات هامة</h5>
        <?php if (isset($row['pause_reason']) && !empty($row['pause_reason'])): ?>
        <div class="info-item">
            <span class="info-label">سبب الإيقاف</span>
            <span class="info-value"><?php echo htmlspecialchars((string)$row['pause_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
        <?php if (isset($row['termination_reason']) && !empty($row['termination_reason'])): ?>
        <div class="info-item">
            <span class="info-label">سبب الإنهاء</span>
            <span class="info-value"><?php echo htmlspecialchars((string)$row['termination_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php
$contractStatusValue = isset($row['status']) ? $row['status'] : 1;
$employee_id = $row['employee_id'];
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
<div class="dcd-panel">
    <h4 class="dcd-section-title">
        <i class="fas fa-boxes"></i>
        معدات العقد
        <?php
        if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
            echo "<span class='dcd-merge-hint'>(العقد #" . $contract_id . " + العقد #" . $row['merged_with'] . ")</span>";
        }
        ?>
    </h4>
    <div class="dcd-scroll-x">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع المعدة</th>
                    <th>الحجم</th>
                    <th>العدد</th>
                    <th><span class="dcd-legend-basic">■</span> أساسية</th>
                    <th><span class="dcd-legend-backup">■</span> احتياطية</th>
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
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr>
            </thead>
            <tbody>
                <?php
                // Function to get driver contract equipments
                if (!function_exists('getdriverContractEquipments')) {
                    function getdriverContractEquipments($contract_id, $conn) {
                        global $dcd_gate;
                        try {
                            return $dcd_gate->select('drivercontractequipments', array(
                                'where'   => array('contract_id' => intval($contract_id)),
                                'orderBy' => 'id ASC',
                            ));
                        } catch (\Throwable $t) {
                            return [];
                        }
                    }
                }

                $equipments = getdriverContractEquipments($contract_id, $conn);

                if (!empty($equipments)) {
                    $i = 1;
                    foreach ($equipments as $equip) {
                        echo "<tr>";
                        echo "<td>" . $i . "</td>";
                        echo "<td><strong>" . htmlspecialchars($equip['equip_type']) . "</strong></td>";
                        echo "<td>" . $equip['equip_size'] . "</td>";
                        echo "<td><span class='dcd-pill-primary'>" . $equip['equip_count'] . "</span></td>";
                        echo "<td><span class='dcd-pill-basic'>" . (isset($equip['equip_count_basic']) ? $equip['equip_count_basic'] : 0) . "</span></td>";
                        echo "<td><span class='dcd-pill-backup'>" . (isset($equip['equip_count_backup']) ? $equip['equip_count_backup'] : 0) . "</span></td>";
                        echo "<td><span class='dcd-pill-success'>" . (isset($equip['equip_shifts']) ? $equip['equip_shifts'] : 0) . "</span></td>";
                        echo "<td>" . $equip['shift_hours'] . "</td>";
                        echo "<td>" . $equip['equip_total_month'] . "</td>";
                        echo "<td><strong class='dcd-accent'>" . (isset($equip['equip_monthly_target']) ? $equip['equip_monthly_target'] : 0) . "</strong></td>";
                        echo "<td>" . $equip['equip_unit'] . "</td>";
                        echo "<td><strong class='dcd-accent'>" . $equip['equip_total_contract'] . "</strong></td>";
                        echo "<td><strong class='dcd-ok'>" . $equip['equip_price'] . " " . $equip['equip_price_currency'] . "</strong></td>";
                        echo "<td>" . $equip['equip_operators'] . "</td>";
                        echo "<td>" . $equip['equip_supervisors'] . "</td>";
                        echo "<td>" . $equip['equip_technicians'] . "</td>";
                        echo "<td>" . $equip['equip_assistants'] . "</td>";
                        if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                            // التحقق من هل هذه المعدة من العقد المدموج أم لا
                            $merged_equipments = getdriverContractEquipments(intval($row['merged_with']), $conn);
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
                    echo "<tr><td colspan='17' class='dcd-empty-cell'>";
                    echo "<i class='fas fa-inbox dcd-empty-icon'></i>";
                    echo "<p class='dcd-empty-text'>لا توجد معدات لهذا العقد</p>";
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
    <div class="dcd-panel dcd-panel-notes">
        <h4 class="dcd-section-title">
            <i class="fas fa-history"></i>
            سجل الملاحظات والتغييرات
        </h4>
        <div class="dcd-scroll-x">
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
                    // الملاحظات معزولةً (النطاق n؛ JOIN العقد الداخلي → LEFT + IS NOT NULL)
                    try {
                        $notes_rows = $dcd_gate->scopedQuery(array(
                            'scope'  => array('n' => 'driver_contract_notes'),
                            'enrich' => array('sc' => 'drivercontracts'),
                        ), "SELECT n.*
                                    FROM driver_contract_notes n
                                    LEFT JOIN drivercontracts sc ON sc.id = n.contract_id
                                    WHERE {TENANT_SCOPE} AND n.contract_id = ? AND sc.id IS NOT NULL
                                    ORDER BY n.created_at DESC", array($contract_id));
                    } catch (\Throwable $t) {
                        $notes_rows = array();
                    }

                    if (!empty($notes_rows)) {
                        $j = 1;
                        foreach ($notes_rows as $note) {
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
                                'primary'   => 'dcd-badge-primary',
                                'secondary' => 'dcd-badge-secondary',
                                'warning'   => 'dcd-badge-warning',
                                'success'   => 'dcd-badge-success',
                                'danger'    => 'dcd-badge-danger',
                                'purple'    => 'dcd-badge-purple',
                                'info'      => 'dcd-badge-info'
                            ];

                            echo "<tr>";
                            echo "<td>" . $j . "</td>";
                            echo "<td><span class='dcd-note-badge " . $badge_colors[$action_badge] . "'>" . $action_icon . " " . $action_type . "</span></td>";
                            echo "<td class='dcd-align-right'>" . $note_text . "</td>";
                            echo "<td><i class='fas fa-user dcd-accent dcd-icon-ml5'></i>النظام</td>";
                            echo "<td><i class='far fa-clock dcd-icon-ml'></i>" . $note['created_at'] . "</td>";
                            echo "</tr>";
                            $j++;
                        }
                    } else {
                        echo "<tr><td colspan='5' class='dcd-empty-cell'>";
                        echo "<i class='fas fa-inbox dcd-empty-icon'></i>";
                        echo "<p class='dcd-empty-text'>لا توجد ملاحظات لهذا العقد</p>";
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- زر العودة -->
    <div class="dcd-back-wrap">
        <a href="employees.php" class="btn btn-lg dcd-back-btn">
            <i class="fas fa-arrow-right"></i> العودة إلى قائمة السائقين
        </a>
    </div>

</div>

<!-- Modal for Renewal -->
<div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header dcd-hd-info">
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
                        <i class="far fa-calendar-alt dcd-icon-ml"></i>
                        تاريخ بدء التجديد <span class="dcd-req">*</span>
                    </label>
                    <input type="date" id="renewalStartDate" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="renewalEndDate" class="form-label">
                        <i class="far fa-calendar-check dcd-icon-ml"></i>
                        تاريخ انتهاء التجديد <span class="dcd-req">*</span>
                    </label>
                    <input type="date" id="renewalEndDate" class="form-control">
                </div>
                <div id="renewalDurationDisplay" class="dcd-duration-box dcd-mt-1r">
                    <div class="dcd-duration-row">
                        <i class="fas fa-calendar-days"></i>
                        <span>مدة العقد الجديدة: <strong id="calculatedDays">0</strong> يوم</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> الغاء
                </button>
                <button type="button" class="btn dcd-btn-info" id="confirmRenewal">
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
            <div class="modal-header dcd-hd-secondary">
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
                        <i class="fas fa-exchange-alt dcd-icon-ml"></i>
                        نوع التسوية <span class="dcd-req">*</span>
                    </label>
                    <select id="settlementType" class="form-select">
                        <option value="">-- اختر --</option>
                        <option value="increase">➕ زيادة ساعات</option>
                        <option value="decrease">➖ نقصان ساعات</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="settlementHours" class="form-label">
                        <i class="far fa-clock dcd-icon-ml"></i>
                        عدد الساعات <span class="dcd-req">*</span>
                    </label>
                    <input type="number" id="settlementHours" class="form-control" min="1" placeholder="أدخل عدد الساعات">
                </div>
                <div class="mb-3">
                    <label for="settlementReason" class="form-label">
                        <i class="fas fa-comment-alt dcd-icon-ml"></i>
                        السبب (اختياري)
                    </label>
                    <textarea id="settlementReason" class="form-control" rows="3" placeholder="أدخل السبب"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn dcd-btn-secondary" id="confirmSettlement">
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
            <div class="modal-header dcd-hd-warning">
                <h5 class="modal-title" id="pauseModalLabel">
                    <i class="fas fa-pause-circle"></i>
                    إيقاف العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>تنبيه:</strong> سيتم إيقاف العقد مؤقتا. يمكنك استئنافه لاحقا.
                </div>
                <div class="mb-4">
                    <label for="pauseDate" class="form-label">
                        <i class="far fa-calendar-alt dcd-icon-ml"></i>
                        تاريخ الإيقاف <span class="dcd-req">*</span>
                    </label>
                    <input type="date" id="pauseDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label for="pauseReason" class="form-label">
                        <i class="fas fa-comment-alt dcd-icon-ml"></i>
                        سبب الإيقاف <span class="dcd-req">*</span>
                    </label>
                    <textarea id="pauseReason" class="form-control" rows="4" placeholder="أدخل السبب المفصل للإيقاف"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn dcd-btn-warning" id="confirmPause">
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
            <div class="modal-header dcd-hd-success">
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
                <div class="mb-4 dcd-pause-box">
                    <div class="dcd-pause-title">
                        <i class="fas fa-pause-circle dcd-icon-lg"></i>
                        <span>معلومات الإيقاف</span>
                    </div>
                    <div class="dcd-pause-row">
                        <i class="far fa-calendar-times"></i>
                        <strong>تاريخ إيقاف العقد:</strong>
                        <span class="dcd-pause-date">
                            <?php echo !empty($pause_date) ? date('Y-m-d', strtotime($pause_date)) : 'غير محدد'; ?>
                        </span>
                    </div>
                    <?php if (!empty($pause_reason)): ?>
                    <div class="dcd-pause-reason">
                        <i class="fas fa-comment-dots"></i>
                        <strong>سبب الإيقاف:</strong> <?php echo htmlspecialchars($pause_reason); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- إدخال تاريخ الاستئناف -->
                <div class="mb-4">
                    <label for="resumeDate" class="form-label dcd-lbl-lg">
                        <i class="far fa-calendar-check dcd-icon-ok"></i>
                        تاريخ استئناف العقد <span class="dcd-req">*</span>
                    </label>
                    <input type="date" id="resumeDate" class="form-control dcd-input-lg" value="<?php echo date('Y-m-d'); ?>">
                    <small class="form-text text-muted dcd-hint-block">
                        <i class="fas fa-info-circle"></i> التاريخ الافتراضي هو اليوم، يمكنك تعديله حسب الحاجة
                    </small>
                </div>

                <div id="pauseDurationDisplay" class="dcd-duration-box dcd-mb-1r">
                    <div class="dcd-duration-row dcd-mb-1r">
                        <i class="fas fa-clock"></i>
                        <span>مدة الإيقاف: <strong id="calculatedPauseDays">0</strong> يوم</span>
                    </div>

                    <!-- خيارات معالجة أيام الإيقاف -->
                    <div class="dcd-choice-box">
                        <div class="dcd-choice-title">
                            <i class="fas fa-question-circle"></i>
                            <span>كيف تريد معالجة أيام الإيقاف؟</span>
                        </div>
                        <div class="form-check mb-2 dcd-radio-wrap">
                            <input class="form-check-input dcd-radio-input" type="radio" name="pauseHandling" id="extendContract" value="extend" checked>
                            <label class="form-check-label dcd-radio-label" for="extendContract">
                                <i class="fas fa-plus-circle dcd-icon-ok"></i>
                                تمديد العقد: إضافة أيام الإيقاف إلى تاريخ الانتهاء
                                <small class="dcd-radio-hint">
                                    سيتم تأجيل تاريخ انتهاء العقد بعدد أيام الإيقاف
                                </small>
                            </label>
                        </div>
                        <div class="form-check dcd-radio-wrap">
                            <input class="form-check-input dcd-radio-input" type="radio" name="pauseHandling" id="deductFromContract" value="deduct">
                            <label class="form-check-label dcd-radio-label" for="deductFromContract">
                                <i class="fas fa-minus-circle dcd-icon-danger"></i>
                                خصم من العقد: تقليل مدة العقد بأيام الإيقاف
                                <small class="dcd-radio-hint">
                                    سيتم تقليل تاريخ انتهاء العقد بعدد أيام الإيقاف
                                </small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="resumeReason" class="form-label">
                        <i class="fas fa-comment-alt dcd-icon-ml"></i>
                        ملاحظات (اختياري)
                    </label>
                    <textarea id="resumeReason" class="form-control" rows="3" placeholder="أدخل أي ملاحظات"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn dcd-btn-success" id="confirmResume">
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
            <div class="modal-header dcd-hd-danger">
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
                        <i class="fas fa-list-ul dcd-icon-ml"></i>
                        نوع الإنهاء <span class="dcd-req">*</span>
                    </label>
                    <select id="terminationType" class="form-select">
                        <option value="">-- اختر النوع --</option>
                        <option value="amicable">🤝 رضائي</option>
                        <option value="hardship">⚠️ بسبب التعسر</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="terminationReason" class="form-label">
                        <i class="fas fa-comment-alt dcd-icon-ml"></i>
                        السبب المفصل <span class="dcd-req">*</span>
                    </label>
                    <textarea id="terminationReason" class="form-control" rows="4" placeholder="أدخل السبب المفصل لإنهاء العقد" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-danger" id="confirmTerminate">
                    <i class="fas fa-times-circle"></i> إنهاء نهائيا
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Merge -->
<div class="modal fade" id="mergeModal" tabindex="-1" aria-labelledby="mergeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header dcd-hd-purple">
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
                        <i class="fas fa-file-contract dcd-icon-ml"></i>
                        اختر العقد للدمج معه <span class="dcd-req">*</span>
                    </label>
                    <select id="mergeWithId" class="form-select">
                        <option value="">-- اختر عقد --</option>
                        <?php
                        // مرشّحو الدمج معزولين عبر البوابة
                        try {
                            $merge_rows = $dcd_gate->scopedQuery(array(
                                'scope' => array('sc' => 'drivercontracts'),
                            ), "SELECT sc.id, sc.contract_signing_date FROM drivercontracts sc WHERE {TENANT_SCOPE} AND sc.employee_id = ? AND sc.project_id = ? AND sc.id != ? ORDER BY sc.id DESC", array($employee_id, $project_id, $contract_id));
                        } catch (\Throwable $t) {
                            $merge_rows = array();
                        }
                        foreach ($merge_rows as $m_row) {
                            echo "<option value='" . $m_row['id'] . "'>العقد #" . $m_row['id'] . " - " . $m_row['contract_signing_date'] . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- عرض المعدات الحالية والمعدات الخاصة بالعقد المختار -->
                <div id="mergeEquipmentsContainer" class="dcd-mt-20">
                    <h6 class="mb-3">معدات العقود:</h6>

                    <!-- معدات العقد الحالي -->
                    <div class="mb-4">
                        <h6 class="dcd-eq-head">
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
                                    $current_equipments = getdriverContractEquipments($contract_id, $conn);
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
                                        echo "<tr><td colspan='5' class='dcd-empty-note'>لا توجد معدات</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- معدات العقد المختار -->
                    <div class="mb-4">
                        <h6 class="dcd-eq-head-alt">
                            <i class="fa fa-cube"></i> معدات العقد المختار
                        </h6>
                        <div id="selectedContractEquipments" class="dcd-minh-100">
                            <p class="dcd-empty-note">اختر عقدا لعرض معداته</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn dcd-btn-purple" id="confirmMerge">
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
            <div class="modal-header dcd-hd-purple">
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
                        <i class="fas fa-comment-alt dcd-icon-ml"></i>
                        ملاحظات الانتهاء <span class="dcd-req">*</span>
                    </label>
                    <textarea id="completeNote" class="form-control" rows="4" placeholder="أدخل ملاحظات حول انتهاء العقد" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn dcd-btn-purple-soft" id="confirmComplete">
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
            <div class="modal-header dcd-hd-primary">
                <h5 class="modal-title" id="editProjectInfoLabel">
                    <i class="fas fa-edit"></i> تعديل معلومات المشروع
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editGracePeriod" class="form-label">
                        <i class="fas fa-calendar-alt dcd-icon-ml"></i>
                        فترة السماح (بالأيام)
                    </label>
                    <input type="number" id="editGracePeriod" class="form-control" value="<?php echo $grace_period; ?>" min="0">
                </div>
                <div class="mb-3">
                    <label for="editDailyOperators" class="form-label">
                        <i class="fas fa-users-cog dcd-icon-ml"></i>
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
            <div class="modal-header dcd-hd-success">
                <h5 class="modal-title" id="editServicesLabel">
                    <i class="fas fa-edit"></i> تعديل الخدمات
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editTransportation" class="form-label">
                        <i class="fas fa-bus dcd-icon-ml"></i>
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
                        <i class="fas fa-hotel dcd-icon-ml"></i>
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
                        <i class="fas fa-map-marker-alt dcd-icon-ml"></i>
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
                        <i class="fas fa-wrench dcd-icon-ml"></i>
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
                <button type="button" class="btn btn-primary" id="saveServices">
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
            <div class="modal-header dcd-hd-info">
                <h5 class="modal-title" id="editPartiesLabel">
                    <i class="fas fa-edit"></i> تعديل أطراف العقد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editFirstParty" class="form-label">
                        <i class="fas fa-user-tie dcd-icon-ml"></i>
                        الطرف الأول
                    </label>
                    <input type="text" id="editFirstParty" class="form-control" value="<?php echo htmlspecialchars($first_party); ?>" placeholder="اسم الطرف الأول">
                </div>
                <div class="mb-3">
                    <label for="editSecondParty" class="form-label">
                        <i class="fas fa-user-check dcd-icon-ml"></i>
                        الطرف الثاني
                    </label>
                    <input type="text" id="editSecondParty" class="form-control" value="<?php echo htmlspecialchars($second_party); ?>" placeholder="اسم الطرف الثاني">
                </div>
                <div class="mb-3">
                    <label for="editWitnessOne" class="form-label">
                        <i class="fas fa-eye dcd-icon-ml"></i>
                        الشاهد الأول
                    </label>
                    <input type="text" id="editWitnessOne" class="form-control" value="<?php echo htmlspecialchars($witness_one); ?>" placeholder="اسم الشاهد الأول">
                </div>
                <div class="mb-3">
                    <label for="editWitnessTwo" class="form-label">
                        <i class="fas fa-eye dcd-icon-ml"></i>
                        الشاهد الثاني
                    </label>
                    <input type="text" id="editWitnessTwo" class="form-control" value="<?php echo htmlspecialchars($witness_two); ?>" placeholder="اسم الشاهد الثاني">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-secondary" id="saveParties">
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
            <div class="modal-header dcd-hd-amber">
                <h5 class="modal-title" id="editPaymentLabel">
                    <i class="fas fa-edit"></i> تعديل البيانات المالية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="editCurrency" class="form-label">
                        <i class="fas fa-dollar-sign dcd-icon-ml"></i>
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
                        <i class="fas fa-money-check-alt dcd-icon-ml"></i>
                        المبلغ المدفوع
                    </label>
                    <input type="text" id="editPaidAmount" class="form-control" value="<?php echo htmlspecialchars($paid_contract); ?>" placeholder="أدخل المبلغ">
                </div>
                <div class="mb-3">
                    <label for="editPaymentTime" class="form-label">
                        <i class="fas fa-clock dcd-icon-ml"></i>
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
                        <i class="fas fa-shield-alt dcd-icon-ml"></i>
                        الضمانات
                    </label>
                    <textarea id="editGuarantees" class="form-control" rows="3" placeholder="تفاصيل الضمانات"><?php echo htmlspecialchars($guarantees); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="editPaymentDate" class="form-label">
                        <i class="fas fa-calendar-check dcd-icon-ml"></i>
                        تاريخ الدفع
                    </label>
                    <input type="date" id="editPaymentDate" class="form-control" value="<?php echo htmlspecialchars($payment_date); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-secondary" id="savePayment">
                    <i class="fas fa-save"></i> حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (required for your AJAX calls) -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
const contractId = <?php echo $contract_id; ?>;
const contractStatus = <?php echo isset($contractStatusValue) ? $contractStatusValue : 1; ?>;
const actualEndDate = '<?php echo isset($actual_end_date) ? $actual_end_date : ''; ?>';  // تاريخ انتهاء العقد الفعلي

// دالة عامة للإجراءات
function performAction(action, data = {}) {
    $.ajax({
        url: 'employee_contract_actions_handler.php',
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
        $('#selectedContractEquipments').html('<p class="dcd-empty-note">اختر عقدا لعرض معداته</p>');
        return;
    }

    // تحميل المعدات عبر AJAX
    $.ajax({
        url: 'get_employee_contract_equipments.php',
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
                    html = '<p class="dcd-empty-note">لا توجد معدات لهذا العقد</p>';
                }
                $('#selectedContractEquipments').html(html);
            } else {
                $('#selectedContractEquipments').html('<p class="dcd-err-note">خطأ: ' + response.message + '</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('الخطأ:', error);
            $('#selectedContractEquipments').html('<p class="dcd-err-note">خطأ في تحميل المعدات</p>');
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
    $('#selectedContractEquipments').html('<p class="dcd-empty-note">اختر عقدا لعرض معداته</p>');
});
</script>

</body>
</html>

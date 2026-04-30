<?php
/**
 * hours_approval.php
 * صفحة اعتماد ساعات العمل - نظام هرمي للمدراء الرئيسيين
 *
 * مستويات الاعتماد:
 *  Level 1 → مدير المشاريع (role 1)
 *  Level 2 → مدير الموردين (role 2)
 *  Level 3 → مدير الأسطول (role 3)
 *  Level 4 → مدير المشغلين (role 4) ← الاعتماد النهائي
 */

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config.php';

$role           = strval($_SESSION['user']['role']);
$user_id        = intval($_SESSION['user']['id']);
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$session_proj   = intval($_SESSION['user']['project_id'] ?? 0);
$session_mine   = intval($_SESSION['user']['mine_id']    ?? 0);

$equip_type_filter = intval($_GET['equip_type'] ?? 0);
if (!in_array($equip_type_filter, [0, 1, 2], true)) {
  $equip_type_filter = 0;
}
$equip_type_where = ($equip_type_filter > 0) ? " AND e.type = $equip_type_filter" : '';
$equip_type_label = ($equip_type_filter === 1) ? 'حفارات' : (($equip_type_filter === 2) ? 'قلابات' : 'الكل');

// role 5 = مدير الموقع (عرض فقط مقيّد بمشروعه ومنجمه)
$is_site_manager = ($role === '5');

$allowed_roles = ['-1', '1', '2', '3', '4', '5'];
if (!in_array($role, $allowed_roles)) {
    header('Location: ../main/dashboard.php');
    exit();
}

// التأكد من وجود الجداول (auto-migration)
$conn->query("CREATE TABLE IF NOT EXISTS `timesheet_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `approval_level` tinyint(1) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_by_name` varchar(255) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ts_level` (`timesheet_id`, `approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `timesheet_approval_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `column_name` varchar(100) NOT NULL,
  `column_label` varchar(255) NOT NULL,
  `note_text` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_by_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

// ─── خريطة الأدوار ───────────────────────────────────────────
$role_level_map  = ['1' => 1, '2' => 2, '3' => 3, '4' => 4];
$level_role_name = [
    1 => ['label' => 'مدير المشاريع',   'color' => '#0d6efd', 'icon' => 'fa-project-diagram'],
    2 => ['label' => 'مدير الموردين',   'color' => '#6f42c1', 'icon' => 'fa-truck-loading'],
  3 => ['label' => 'مدير الأسطول',    'color' => '#198754', 'icon' => 'fa-ship'],
  4 => ['label' => 'مدير المشغلين',   'color' => '#fd7e14', 'icon' => 'fa-user-cog'],
];

$my_level   = $role_level_map[$role] ?? 0;
$is_admin   = ($role === '-1');
$prev_level = $my_level - 1;

// ─── نطاق الشركة ─────────────────────────────────────────────
$company_scope_ts = '';
$company_scope_ta = '';
if (!$is_admin && $company_id > 0) {
    $company_scope_ts = " AND (t.company_id = $company_id OR t.company_id IS NULL)";
    $company_scope_ta = " AND (ta.company_id = $company_id OR ta.company_id IS NULL)";
}

// ─── استعلام السجلات المعلقة (قيد الاعتماد) ────────────────
$ops_project_col = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

// ─── نطاق مدير الموقع (مشروع + منجم محدد) ────────────────────
$site_scope_ts = '';
if ($is_site_manager) {
    if ($session_proj > 0) {
        $site_scope_ts .= " AND o.{$ops_project_col} = $session_proj";
    }
    if ($session_mine > 0 && db_table_has_column($conn, 'operations', 'mine_id')) {
        $site_scope_ts .= " AND o.mine_id = $session_mine";
    }
}

// بناء شرط "قيد الاعتماد" بناءً على الدور
if ($is_site_manager) {
    // مدير الموقع يرى جميع سجلاته بغض النظر عن حالة الاعتماد
    $pending_condition = "1=1";
} elseif ($is_admin) {
    // الأدمن يرى كل ما لم يُعتمد نهائياً (level 4)
    $pending_condition = "NOT EXISTS (
        SELECT 1 FROM timesheet_approvals ta2
        WHERE ta2.timesheet_id = t.id AND ta2.approval_level = 4 AND ta2.status = 1
    )";
} elseif ($my_level === 1) {
    // مدير المشاريع يرى كل ما لم يعتمده بعد
    $pending_condition = "NOT EXISTS (
        SELECT 1 FROM timesheet_approvals ta2
        WHERE ta2.timesheet_id = t.id AND ta2.approval_level = 1 AND ta2.status = 1
    )";
} else {
    // المدراء 2-4 يرون ما اعتمده المستوى قبلهم ولم يعتمدوه هم
    $pending_condition = "EXISTS (
        SELECT 1 FROM timesheet_approvals ta2
        WHERE ta2.timesheet_id = t.id AND ta2.approval_level = $prev_level AND ta2.status = 1
    ) AND NOT EXISTS (
        SELECT 1 FROM timesheet_approvals ta3
        WHERE ta3.timesheet_id = t.id AND ta3.approval_level = $my_level AND ta3.status = 1
    )";
}

$pending_sql = "
    SELECT t.*,
           d.name  AS driver_name,
           e.code  AS equip_code,
           e.name  AS equip_name,
           s.name  AS supplier_name,
           p.name  AS project_name,
           u.name  AS entry_user_name,
           (SELECT COUNT(*) FROM timesheet_approval_notes n
            WHERE n.timesheet_id = t.id AND n.status = 1) AS notes_count,
           (SELECT MAX(ta_l.approval_level) FROM timesheet_approvals ta_l
            WHERE ta_l.timesheet_id = t.id AND ta_l.status = 1) AS max_approved_level
    FROM timesheet t
    LEFT JOIN operations    o ON o.id      = t.operator
    LEFT JOIN equipments    e ON e.id      = o.equipment
    LEFT JOIN suppliers     s ON s.id      = e.suppliers
    LEFT JOIN project       p ON p.id      = o.$ops_project_col
    LEFT JOIN drivers       d ON d.id      = t.driver
    LEFT JOIN users         u ON u.id      = t.user_id
    WHERE t.status = 1
      AND $pending_condition
      $company_scope_ts
      $site_scope_ts
      $equip_type_where
    ORDER BY t.date DESC, t.id DESC
    LIMIT 500
";

$pending_result = $conn->query($pending_sql);
$pending_rows   = [];
if ($pending_result) {
    while ($r = $pending_result->fetch_assoc()) $pending_rows[] = $r;
}

// ─── السجلات المعتمدة نهائياً (آخر 100) ────────────────────
$approved_sql = "
    SELECT t.*,
           d.name  AS driver_name,
           e.code  AS equip_code,
           e.name  AS equip_name,
           s.name  AS supplier_name,
           p.name  AS project_name,
           u.name  AS entry_user_name,
           ta_final.approved_by_name AS final_approver,
           ta_final.approved_at      AS final_approved_at,
           (SELECT COUNT(*) FROM timesheet_approval_notes n
            WHERE n.timesheet_id = t.id AND n.status = 1) AS notes_count
    FROM timesheet t
    INNER JOIN timesheet_approvals ta_final
           ON ta_final.timesheet_id = t.id
           AND ta_final.approval_level = 4
           AND ta_final.status = 1
    LEFT JOIN operations    o ON o.id      = t.operator
    LEFT JOIN equipments    e ON e.id      = o.equipment
    LEFT JOIN suppliers     s ON s.id      = e.suppliers
    LEFT JOIN project       p ON p.id      = o.$ops_project_col
    LEFT JOIN drivers       d ON d.id      = t.driver
    LEFT JOIN users         u ON u.id      = t.user_id
    WHERE t.status = 1
      $company_scope_ts
      $site_scope_ts
      $equip_type_where
    ORDER BY ta_final.approved_at DESC
    LIMIT 100
";

$approved_result = $conn->query($approved_sql);
$approved_rows   = [];
if ($approved_result) {
    while ($r = $approved_result->fetch_assoc()) $approved_rows[] = $r;
}

// ─── جلب تفاصيل كل مستويات الاعتماد للسجلات المعتمدة نهائياً ───
// نجلب دفعة واحدة لكل السجلات لتفادي N+1 queries
$approved_ids = array_column($approved_rows, 'id');
$all_approval_details = []; // [timesheet_id][level] = row
if (!empty($approved_ids)) {
    $ids_in = implode(',', array_map('intval', $approved_ids));
    $adv_res = $conn->query(
        "SELECT timesheet_id, approval_level, approved_by_name, approved_at
         FROM timesheet_approvals
         WHERE timesheet_id IN ($ids_in) AND status = 1
         ORDER BY timesheet_id, approval_level ASC"
    );
    if ($adv_res) {
        while ($adv = $adv_res->fetch_assoc()) {
            $all_approval_details[intval($adv['timesheet_id'])][intval($adv['approval_level'])] = $adv;
        }
    }
}

// ─── بيانات الفلاتر (قوائم فريدة) ───────────────────────────
$_all_rows = $pending_rows;
$filter_projects = array_values(array_unique(array_filter(array_column($_all_rows, 'project_name'))));
$filter_suppliers = array_values(array_unique(array_filter(array_column($_all_rows, 'supplier_name'))));
$filter_drivers  = array_values(array_unique(array_filter(array_column($_all_rows, 'driver_name'))));
$filter_equips   = [];
foreach ($_all_rows as $_r) {
    $_en = trim(($_r['equip_code'] ?? '') . ' ' . ($_r['equip_name'] ?? ''));
    if ($_en) $filter_equips[] = $_en;
}
$filter_equips = array_values(array_unique($filter_equips));
sort($filter_projects); sort($filter_suppliers); sort($filter_drivers); sort($filter_equips);

// ─── إحصاءات سريعة ──────────────────────────────────────────
$stats = [
    'pending'  => count($pending_rows),
    'approved' => count($approved_rows),
    'my_level' => $my_level,
    'my_label' => $is_admin ? 'الأدمن' : ($level_role_name[$my_level]['label'] ?? 'مدير'),
];

// قائمة الأعمدة المتاحة للتعليق
$column_labels = [
    'date'              => 'التاريخ',
    'shift'             => 'الوردية',
    'shift_hours'       => 'ساعات الوردية',
    'executed_hours'    => 'الساعات المنفذة',
    'total_work_hours'  => 'إجمالي ساعات العمل',
    'total_fault_hours' => 'إجمالي الأعطال',
    'hr_fault'          => 'عطل بشري',
    'maintenance_fault' => 'عطل صيانة',
    'marketing_fault'   => 'عطل تسويق',
    'approval_fault'    => 'عطل اعتماد',
    'other_fault_hours' => 'أعطال أخرى',
    'standby_hours'     => 'ساعات الانتظار',
    'dependence_hours'  => 'ساعات الاعتماد',
    'extra_hours'       => 'ساعات إضافية',
    'operator_hours'    => 'ساعات المشغل',
    'counter_diff'      => 'فرق العداد',
    'fault_type'        => 'نوع العطل',
    'fault_department'  => 'القسم المسؤول',
    'work_notes'        => 'ملاحظات العمل',
    'fault_notes'       => 'ملاحظات الأعطال',
    'general_notes'     => 'ملاحظات عامة',
];

// الأعمدة تُتحكم بها عبر أزرار إظهار/إخفاء المجموعات في الصفحة

$page_title = 'اعتماد ساعات العمل';
include('../inheader.php');
?>
<!-- ============================================================
     CSS الصفحة
============================================================ -->
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
/* ── متغيرات اللون ── */
:root {
  --clr-primary    : #0d6efd;
  --clr-success    : #198754;
  --clr-warning    : #fd7e14;
  --clr-purple     : #6f42c1;
  --clr-danger     : #dc3545;
  --clr-bg-card    : #fff;
  --clr-sidebar-bg : #1e2a38;
  --clr-text-muted : #6c757d;
  --radius-card    : 14px;
  --shadow-card    : 0 2px 16px rgba(0,0,0,.08);
}

body { background: #f0f4f8; }

/* ── حاوية الصفحة ── */
.page-wrapper {
  padding: 20px 24px;
  min-height: 100vh;
  width: 100%;
}

/* ── بطاقات الإحصاء ── */
.stat-card {
  background    : var(--clr-bg-card);
  border-radius : var(--radius-card);
  box-shadow    : var(--shadow-card);
  padding       : 18px 22px;
  display       : flex;
  align-items   : center;
  gap           : 16px;
  border-right  : 5px solid transparent;
  transition    : transform .2s;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-card .stat-icon {
  width        : 52px;
  height       : 52px;
  border-radius: 12px;
  display      : flex;
  align-items  : center;
  justify-content: center;
  font-size    : 1.4rem;
  color        : #fff;
  flex-shrink  : 0;
}
.stat-card .stat-val  { font-size: 1.9rem; font-weight: 800; line-height: 1; }
.stat-card .stat-label{ font-size: .82rem; color: var(--clr-text-muted); }

/* ── بطاقة الجدول ── */
.table-card {
  background    : var(--clr-bg-card);
  border-radius : var(--radius-card);
  box-shadow    : var(--shadow-card);
  padding       : 22px;
  margin-bottom : 32px;
}
.table-card .card-header-custom {
  display       : flex;
  align-items   : center;
  gap           : 12px;
  margin-bottom : 18px;
  padding-bottom: 14px;
  border-bottom : 2px solid #eef2f7;
}
.card-header-custom .ch-icon {
  width        : 44px;
  height       : 44px;
  border-radius: 10px;
  display      : flex;
  align-items  : center;
  justify-content: center;
  font-size    : 1.1rem;
  color        : #fff;
}
.card-header-custom h5 { margin: 0; font-weight: 700; font-size: 1.05rem; }
.card-header-custom .badge-count {
  margin-right: auto;
  font-size   : .82rem;
  padding     : 4px 10px;
  border-radius: 20px;
}

/* ── الجدول ── */
table.ha-table { width: 100% !important; font-size: .83rem; }
table.ha-table thead th {
  background  : #1e2a38;
  color       : #fff;
  font-weight : 600;
  padding     : 10px 8px;
  white-space : nowrap;
  font-size   : .82rem;
}
table.ha-table tbody tr:hover { background: #f8fafb; }
table.ha-table td {
  padding    : 8px 8px;
  vertical-align: middle;
  white-space: nowrap;
}

/* ── شارات مستوى الاعتماد ── */
.lvl-badge {
  display     : inline-flex;
  align-items : center;
  gap         : 4px;
  padding     : 3px 8px;
  border-radius: 20px;
  font-size   : .73rem;
  font-weight : 600;
  white-space : nowrap;
}
.lvl-0 { background:#f0f4f8; color:#495057; }
.lvl-1 { background:#e8f0ff; color:#0d47a1; }
.lvl-2 { background:#f0e8ff; color:#4a148c; }
.lvl-3 { background:#fff3e0; color:#e65100; }
.lvl-4 { background:#e8f5e9; color:#1b5e20; }

/* ── زر الملاحظة ── */
.btn-note {
  border      : none;
  background  : transparent;
  color       : #6c757d;
  padding     : 2px 6px;
  border-radius: 6px;
  cursor      : pointer;
  font-size   : .9rem;
  transition  : background .15s;
  position    : relative;
}
.btn-note:hover    { background: #e9ecef; color: #0d6efd; }
.btn-note .note-cnt{
  position  : absolute;
  top       : -6px;
  left      : -4px;
  background: #dc3545;
  color     : #fff;
  font-size : .65rem;
  width     : 16px;
  height    : 16px;
  border-radius: 50%;
  display   : flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

/* ── تدرج الأدوار في شريط التقدم ── */
.approval-steps {
  display      : flex;
  align-items  : center;
  gap          : 0;
  margin-bottom: 20px;
}
.approval-step {
  flex          : 1;
  text-align    : center;
  padding       : 10px 6px;
  border-radius : 0;
  font-size     : .78rem;
  font-weight   : 600;
  color         : #fff;
  position      : relative;
  opacity       : .45;
  transition    : opacity .2s;
}
.approval-step.active  { opacity: 1; }
.approval-step.done    { opacity: .8; }
.approval-step:first-child { border-radius: 8px 0 0 8px; }
.approval-step:last-child  { border-radius: 0 8px 8px 0; }
.approval-step .step-num {
  width        : 24px;
  height       : 24px;
  border-radius: 50%;
  background   : rgba(255,255,255,.3);
  display      : inline-flex;
  align-items  : center;
  justify-content: center;
  margin-bottom: 3px;
  font-size    : .85rem;
}

/* ── Modal ── */
.modal-content { border-radius: 14px; }
.modal-header  { border-bottom: 2px solid #eef2f7; }
.note-item {
  background   : #f8f9fa;
  border-radius: 10px;
  padding      : 12px 16px;
  margin-bottom: 10px;
  border-right : 3px solid #0d6efd;
}
.note-item .note-col-badge {
  background  : #e8f0ff;
  color       : #0d47a1;
  padding     : 2px 8px;
  border-radius: 12px;
  font-size   : .73rem;
  font-weight : 600;
}
.note-item .note-meta {
  font-size : .75rem;
  color     : #6c757d;
  margin-top: 6px;
}

/* ── شريط الأدوات ── */
.toolbar-row {
  display     : flex;
  align-items : center;
  flex-wrap   : wrap;
  gap         : 10px;
  margin-bottom: 14px;
}

/* ── شريط الفلاتر ── */
.filters-bar {
  background   : #fff;
  border-radius: 14px;
  box-shadow   : 0 2px 12px rgba(0,0,0,.07);
  padding      : 14px 18px;
  margin-bottom: 18px;
  display      : flex;
  align-items  : flex-end;
  flex-wrap    : wrap;
  gap          : 12px;
  border-right : 4px solid #0d6efd;
}
.filters-bar .filter-group {
  display      : flex;
  flex-direction: column;
  gap          : 4px;
  min-width    : 160px;
  flex         : 1 1 160px;
}
.filters-bar .filter-group label {
  font-size    : .75rem;
  font-weight  : 700;
  color        : #495057;
  margin-bottom: 0;
}
.filters-bar .filter-group select {
  font-size    : .82rem;
  border-radius: 8px;
  border       : 1.5px solid #dee2e6;
  padding      : 5px 8px;
  color        : #212529;
  background   : #f8f9fa;
  transition   : border-color .15s;
}
.filters-bar .filter-group select:focus {
  border-color : #0d6efd;
  outline      : none;
  background   : #fff;
}
.filters-bar .filter-actions {
  display      : flex;
  align-items  : flex-end;
  gap          : 8px;
  flex-shrink  : 0;
}
.filters-bar .btn-filter-apply {
  background   : #0d6efd;
  color        : #fff;
  border       : none;
  border-radius: 8px;
  padding      : 6px 14px;
  font-size    : .82rem;
  font-weight  : 700;
  cursor       : pointer;
  transition   : background .15s;
  display      : inline-flex;
  align-items  : center;
  gap          : 5px;
}
.filters-bar .btn-filter-apply:hover { background: #0b5ed7; }
.filters-bar .btn-filter-reset {
  background   : transparent;
  color        : #6c757d;
  border       : 1.5px solid #dee2e6;
  border-radius: 8px;
  padding      : 5px 12px;
  font-size    : .82rem;
  cursor       : pointer;
  transition   : all .15s;
}
.filters-bar .btn-filter-reset:hover { border-color:#dc3545; color:#dc3545; }
.active-filters-info {
  font-size  : .75rem;
  color      : #0d47a1;
  background : #e8f0ff;
  border-radius: 8px;
  padding    : 4px 10px;
  font-weight: 600;
  display    : none;
}

/* ── تحسينات الريسبونسيف ── */
@media(max-width:992px){
  .filters-bar .filter-group { min-width: 140px; }
}
@media(max-width:576px){
  .filters-bar { padding: 12px; gap: 8px; }
  .filters-bar .filter-group { min-width: 100%; flex: 1 1 100%; }
  .filters-bar .filter-actions { width: 100%; }
  .filters-bar .btn-filter-apply,
  .filters-bar .btn-filter-reset { flex: 1; justify-content: center; }
  .cg-bar { padding: 10px; gap: 6px; }
  .cg-btn { font-size: .72rem; padding: 4px 8px; }
  .table-card { padding: 12px; }
  .card-header-custom h5 { font-size: .95rem; }
  .stat-card { padding: 12px 14px; gap: 10px; }
  .stat-card .stat-val { font-size: 1.5rem; }
  .approval-steps { display: none; }
}

/* ── تمييز الصفوف المحددة ── */
table.ha-table tr.selected-row td { background: #e8f4ff !important; }

/* ── Responsive ── */
@media(max-width:768px){
  .page-wrapper { padding: 12px; }
  .stat-card .stat-val { font-size: 1.4rem; }
}

/* ── دوائر حالة الاعتماد ── */
.apv-circles { display:inline-flex; align-items:center; gap:2px; }
.apv-circle {
  display        : inline-flex;
  align-items    : center;
  justify-content: center;
  width          : 22px;
  height         : 22px;
  border-radius  : 50%;
  font-size      : .65rem;
  cursor         : default;
  transition     : transform .15s;
  flex-shrink    : 0;
}
.apv-circle:hover { transform: scale(1.18); }
.apv-pending { background:#e9ecef; color:#adb5bd; border:1.5px solid #ced4da; }
.apv-done    { background:#198754; color:#fff;    border:1.5px solid #146c43; }
.apv-sep { color:#dee2e6; font-size:.7rem; margin:0 3px; user-select:none; }
.apv-eye {
  display        : inline-flex;
  align-items    : center;
  justify-content: center;
  width          : 24px;
  height         : 24px;
  border-radius  : 6px;
  color          : #06a530;
  border         : 1.5px solid #bad50a;
  font-size      : .72rem;
  text-decoration: none;
  transition     : background .15s, color .15s;
  flex-shrink    : 0;
}
.apv-eye:hover { background:
#022860; color:#fff; }
.apv-approve {
  display        : inline-flex;
  align-items    : center;
  justify-content: center;
  width          : 24px;
  height         : 24px;
  border-radius  : 6px;
  background     : #198754;
  color          : #fff;
  border         : none;
  font-size      : .72rem;
  cursor         : pointer;
  transition     : background .15s;
  flex-shrink    : 0;
}
.apv-approve:hover { background:#146c43; }

/* ── أزرار مجموعات الأعمدة ── */
.cg-btn {
  display      : inline-flex;
  align-items  : center;
  gap          : 5px;
  padding      : 5px 12px;
  border-radius: 20px;
  font-size    : .78rem;
  font-weight  : 600;
  cursor       : pointer;
  border       : 1.5px solid;
  background   : transparent;
  transition   : background .15s, color .15s;
}
.cg-btn.cg-hours   { color:#0d47a1; border-color:#0d47a1; }
.cg-btn.cg-faults  { color:#bf360c; border-color:#bf360c; }
.cg-btn.cg-notes   { color:#1b5e20; border-color:#1b5e20; }
.cg-btn.cg-active.cg-hours  { background:#0d47a1; color:#fff; }
.cg-btn.cg-active.cg-faults { background:#bf360c; color:#fff; }
.cg-btn.cg-active.cg-notes  { background:#1b5e20; color:#fff; }
.cg-bar {
  background   : #fff;
  border-radius: 12px;
  box-shadow   : 0 2px 10px rgba(0,0,0,.07);
  padding      : 12px 18px;
  margin-bottom: 18px;
  display      : flex;
  align-items  : center;
  flex-wrap    : wrap;
  gap          : 10px;
}

/* ── Tooltip مخصص لدوائر الاعتماد في جدول المعتمدين ── */
.apv-circle-wrap {
  position   : relative;
  display    : inline-flex;
  flex-shrink: 0;
}
.apv-circle-wrap .apv-tooltip {
  visibility      : hidden;
  opacity         : 0;
  position        : absolute;
  bottom          : calc(100% + 8px);
  left            : 50%;
  transform       : translateX(-50%);
  background      : #1e2a38;
  color           : #fff;
  border-radius   : 8px;
  padding         : 7px 11px;
  font-size       : .73rem;
  white-space     : nowrap;
  pointer-events  : none;
  z-index         : 999;
  text-align      : center;
  line-height     : 1.5;
  box-shadow      : 0 4px 14px rgba(0,0,0,.25);
  transition      : opacity .18s, visibility .18s;
}
.apv-circle-wrap .apv-tooltip::after {
  content      : '';
  position     : absolute;
  top          : 100%;
  left         : 50%;
  transform    : translateX(-50%);
  border-width : 5px;
  border-style : solid;
  border-color : #1e2a38 transparent transparent transparent;
}
.apv-circle-wrap:hover .apv-tooltip {
  visibility : visible;
  opacity    : 1;
}
.apv-tooltip .tt-role {
  font-weight : 700;
  font-size   : .76rem;
  color       : #7dd3a8;
  display     : block;
  margin-bottom: 2px;
}
.apv-tooltip .tt-name {
  display     : block;
  font-weight : 600;
}
.apv-tooltip .tt-date {
  display   : block;
  color     : #adb5bd;
  font-size : .68rem;
  margin-top: 2px;
}
</style>

<!-- ============================================================
     HTML
============================================================ -->


<div class="page-wrapper">

  <!-- ── شريط التنقل العلوي ── -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= $is_admin ? '../admin/dashboard.php' : '../main/dashboard.php' ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
        <i class="fa fa-home me-1"></i> لوحة التحكم
      </a>
      <a href="hours_approval_followup.php" class="btn btn-primary btn-sm fw-semibold">
        <i class="fa fa-route me-1"></i> متابعة الاعتمادات المنقولة
      </a>
    </div>
  </div>

  <!-- ── عنوان الصفحة ── -->
  <div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:52px;height:52px;background:linear-gradient(135deg,#0d6efd,#6f42c1);
                border-radius:14px;display:flex;align-items:center;justify-content:center;">
      <i class="fa fa-check-double text-white fs-4"></i>
    </div>
    <div>
      <h3 class="mb-0 fw-bold">اعتماد ساعات العمل</h3>
      <small class="text-muted">
        <?php
          if ($is_admin) echo 'عرض كامل - الأدمن';
          elseif ($is_site_manager) echo 'مدير الموقع — عرض فقط' . ($session_proj > 0 ? ' | مشروع #'.$session_proj : '');
          else echo 'مستوى الاعتماد ' . $my_level . ' — ' . ($level_role_name[$my_level]['label'] ?? '');
        ?>
      </small>
      <div class="mt-1">
        <span class="badge bg-light text-dark border">فلتر نوع المعدة: <?= htmlspecialchars($equip_type_label) ?></span>
      </div>
    </div>
  </div>

  <!-- ── شريط التقدم الهرمي ── -->
  <div class="approval-steps mb-4" style="border-radius:8px;overflow:hidden;">
    <?php foreach ($level_role_name as $lvl => $info):
      $cls = '';
      if (!$is_admin) {
          if ($lvl < $my_level)  $cls = 'done';
          elseif ($lvl == $my_level) $cls = 'active';
      } else {
          $cls = 'done';
      }
    ?>
    <div class="approval-step <?= $cls ?>"
         style="background:<?= $info['color'] ?>;">
      <div>
        <div class="step-num mx-auto"><?= $lvl ?></div>
        <div><?= $info['label'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── بطاقات الإحصاء ── -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card" style="border-color:#fd7e14;">
        <div class="stat-icon" style="background:#fd7e14;"><i class="fa fa-clock"></i></div>
        <div>
          <div class="stat-val"><?= count($pending_rows) ?></div>
          <div class="stat-label">قيد الاعتماد</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card" style="border-color:#198754;">
        <div class="stat-icon" style="background:#198754;"><i class="fa fa-check-circle"></i></div>
        <div>
          <div class="stat-val"><?= count($approved_rows) ?></div>
          <div class="stat-label">معتمد نهائياً (آخر 100)</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card" style="border-color:#0d6efd;">
        <div class="stat-icon" style="background:#0d6efd;"><i class="fa fa-layer-group"></i></div>
        <div>
          <div class="stat-val"><?= $is_admin ? '4' : $my_level ?></div>
          <div class="stat-label">مستوى الاعتماد الحالي</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card" style="border-color:#6f42c1;">
        <div class="stat-icon" style="background:#6f42c1;"><i class="fa fa-comment-dots"></i></div>
        <div>
          <div class="stat-val" id="total-notes-count">—</div>
          <div class="stat-label">إجمالي الملاحظات</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── فلتر نوع المعدة ── -->
  <div class="table-card py-3">
    <form method="get" class="toolbar-row mb-0">
      <label for="equip_type" class="fw-semibold mb-0">نوع المعدة:</label>
      <select name="equip_type" id="equip_type" class="form-select form-select-sm" style="max-width:220px;">
        <option value="0" <?= $equip_type_filter === 0 ? 'selected' : '' ?>>الكل</option>
        <option value="1" <?= $equip_type_filter === 1 ? 'selected' : '' ?>>حفارات</option>
        <option value="2" <?= $equip_type_filter === 2 ? 'selected' : '' ?>>قلابات</option>
      </select>
      <button type="submit" class="btn btn-sm btn-primary fw-semibold">
        <i class="fa fa-filter me-1"></i> تطبيق الفلتر
      </button>
      <?php if ($equip_type_filter !== 0): ?>
      <a href="hours_approval.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-rotate-left me-1"></i> إلغاء الفلتر
      </a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ── شريط إظهار/إخفاء مجموعات الأعمدة ── -->
  <div class="cg-bar">
    <span class="fw-semibold text-muted" style="font-size:.82rem;">
      <i class="fa fa-table-columns me-1"></i> إظهار أعمدة إضافية:
    </span>
    <button class="cg-btn cg-hours" data-group="hours" onclick="toggleColGroup(this)">
      <i class="fa fa-clock"></i> ساعات تفصيلية
    </button>
    <button class="cg-btn cg-faults" data-group="faults" onclick="toggleColGroup(this)">
      <i class="fa fa-tools"></i> تفاصيل الأعطال
    </button>
    <button class="cg-btn cg-notes" data-group="notes" onclick="toggleColGroup(this)">
      <i class="fa fa-sticky-note"></i> ملاحظات العمل
    </button>
    <small class="text-muted me-auto" style="font-size:.75rem;">
      <i class="fa fa-info-circle me-1"></i>
      المجموع = الساعات المنفذة + ساعات الانتظار
    </small>
  </div>

  <!-- ── شريط الفلاتر ── -->
  <div class="filters-bar" id="main-filters-bar">
    <div style="font-size:.8rem;font-weight:700;color:#0d6efd;flex-basis:100%;margin-bottom:2px;">
      <i class="fa fa-filter me-1"></i> فلاتر البحث
    </div>

    <div class="filter-group">
      <label for="filter-project"><i class="fa fa-project-diagram me-1"></i>المشروع</label>
      <select id="filter-project">
        <option value="">— كل المشاريع —</option>
        <?php foreach ($filter_projects as $fp_val): ?>
        <option value="<?= htmlspecialchars($fp_val) ?>"><?= htmlspecialchars($fp_val) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="filter-supplier"><i class="fa fa-truck me-1"></i>المورد</label>
      <select id="filter-supplier">
        <option value="">— كل الموردين —</option>
        <?php foreach ($filter_suppliers as $fs_val): ?>
        <option value="<?= htmlspecialchars($fs_val) ?>"><?= htmlspecialchars($fs_val) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="filter-driver"><i class="fa fa-user-hard-hat me-1"></i>المشغل</label>
      <select id="filter-driver">
        <option value="">— كل المشغلين —</option>
        <?php foreach ($filter_drivers as $fd_val): ?>
        <option value="<?= htmlspecialchars($fd_val) ?>"><?= htmlspecialchars($fd_val) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="filter-equip"><i class="fa fa-cogs me-1"></i>الآلية</label>
      <select id="filter-equip">
        <option value="">— كل الآليات —</option>
        <?php foreach ($filter_equips as $fe_val): ?>
        <option value="<?= htmlspecialchars($fe_val) ?>"><?= htmlspecialchars($fe_val) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-actions">
      <button class="btn-filter-apply" onclick="applyFilters()">
        <i class="fa fa-search"></i> تطبيق
      </button>
      <button class="btn-filter-reset" onclick="resetFilters()">
        <i class="fa fa-rotate-left"></i> إلغاء
      </button>
    </div>

    <span class="active-filters-info" id="active-filters-info">
      <i class="fa fa-check-circle me-1"></i>
      <span id="active-filters-text"></span>
    </span>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       جدول ١: التايمشيت قيد الاعتماد
  ══════════════════════════════════════════════════════════ -->
  <div class="table-card">
    <div class="card-header-custom">
      <div class="ch-icon" style="background:#fd7e14;"><i class="fa fa-hourglass-half"></i></div>
      <div>
        <h5>سجلات التايمشيت — قيد الاعتماد</h5>
        <small class="text-muted">
          <?php if ($is_admin): ?>
            الأدمن يرى جميع السجلات غير المعتمدة نهائياً
          <?php elseif ($my_level === 1): ?>
            السجلات التي لم تحظَ بعد باعتمادك (المستوى 1)
          <?php else: ?>
            السجلات المعتمدة من المستوى <?= $prev_level ?> وبانتظار اعتمادك (المستوى <?= $my_level ?>)
          <?php endif; ?>
        </small>
      </div>
      <span class="badge-count badge" style="background:#fd7e14;"><?= count($pending_rows) ?> سجل</span>
    </div>

    <!-- شريط الأدوات -->
    <?php if (!$is_admin && !$is_site_manager): ?>
    <div class="toolbar-row">
      <button class="btn btn-sm btn-success fw-bold" onclick="approveSelected()" id="btn-approve-sel">
        <i class="fa fa-check me-1"></i> اعتماد المحدد
      </button>
      <button class="btn btn-sm btn-outline-secondary" onclick="selectAllPending()" id="btn-sel-all">
        <i class="fa fa-check-square me-1"></i> تحديد الكل
      </button>
      <button class="btn btn-sm btn-outline-danger" onclick="deselectAllPending()">
        <i class="fa fa-times me-1"></i> إلغاء التحديد
      </button>
      <span class="text-muted small ms-auto" id="sel-count-label">لا توجد سجلات محددة</span>
    </div>
    <?php endif; ?>

    <!-- الجدول -->
    <div class="table-responsive">
      <table id="tbl-pending" class="ha-table display nowrap" style="width:100%">
        <thead>
          <tr>
            <?php if (!$is_admin && !$is_site_manager): ?>
            <th class="nosort" style="width:36px;">
              <input type="checkbox" id="chk-all-pending" onchange="toggleAllPending(this)">
            </th>
            <?php endif; ?>
            <th>#</th>
            <th>التاريخ</th>
            <th>الوردية</th>
            <th>المشروع</th>
            <th>المورد</th>
            <th>الآلية</th>
            <th>المشغل</th>
            <th>المنفذة</th>
            <th>الانتظار</th>
            <th>الأعطال</th>
            <th>المجموع </th>
            <th class="col-g-hours nosort">ساعات الوردية</th>
            <th class="col-g-hours nosort">ساعات الاعتماد</th>
            <th class="col-g-hours nosort">ساعات إضافية</th>
            <th class="col-g-hours nosort">ساعات المشغل</th>
            <th class="col-g-faults nosort">عطل صيانة</th>
            <th class="col-g-faults nosort">عطل بشري</th>
            <th class="col-g-faults nosort">أعطال أخرى</th>
            <th class="col-g-faults nosort">فرق العداد</th>
            <th class="col-g-faults nosort">نوع العطل</th>
            <th class="col-g-faults nosort">القسم المسؤول</th>
            <th class="col-g-notes nosort">ملاحظات العمل</th>
            <th class="col-g-faults nosort">ملاحظات الأعطال</th>
            <th class="nosort">ملاحظات</th>
            <th class="nosort" style="white-space:nowrap;">الاعتماد والتفاصيل</th>
          </tr>
        </thead>
        <tbody>
        <?php $idx = 1; foreach ($pending_rows as $row):
          // جلب تفاصيل الاعتمادات لكل سجل عند عرض مدير الموقع
          $approval_details = [];
          if ($is_site_manager) {
              $ad_res = $conn->query("SELECT approval_level, approved_by_name, approved_at FROM timesheet_approvals WHERE timesheet_id = ".intval($row['id'])." AND status = 1 ORDER BY approval_level ASC");
              if ($ad_res) while ($ad = $ad_res->fetch_assoc()) $approval_details[$ad['approval_level']] = $ad;
          }
          $_prow_equip = trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''));
        ?>
          <tr data-id="<?= $row['id'] ?>"
              data-project="<?= htmlspecialchars($row['project_name'] ?? '') ?>"
              data-supplier="<?= htmlspecialchars($row['supplier_name'] ?? '') ?>"
              data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
              data-equip="<?= htmlspecialchars($_prow_equip) ?>">
            <?php if (!$is_admin && !$is_site_manager): ?>
            <td>
              <input type="checkbox" class="row-chk" value="<?= $row['id'] ?>"
                     onchange="updateSelCount()">
            </td>
            <?php endif; ?>
            <td><?= $idx++ ?></td>
            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
            <td>
              <?php $shift = $row['shift'] ?? '';
                    $shift_ar = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : $shift);
                    $shift_bg = ($shift === 'D') ? '#fff3cd' : '#d1ecf1';
                    $shift_clr= ($shift === 'D') ? '#856404' : '#0c5460';
              ?>
              <span style="background:<?=$shift_bg?>;color:<?=$shift_clr?>;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600;">
                <?= $shift_ar ?>
              </span>
            </td>
            <td><span class="text-truncate d-block" style="max-width:120px;" title="<?= htmlspecialchars($row['project_name'] ?? '') ?>"><?= htmlspecialchars($row['project_name'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars(trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''))) ?: '—' ?></td>
            <td><?= htmlspecialchars($row['driver_name'] ?? '—') ?></td>
            <td><?= floatval($row['executed_hours'] ?? 0) ?></td>
            <td><?= floatval($row['standby_hours'] ?? 0) ?></td>
            <td><?= floatval($row['total_fault_hours'] ?? 0) ?></td>
            <td><strong><?= floatval($row['total_work_hours'] ?? 0) ?></strong></td>
            <td><?= floatval($row['shift_hours'] ?? 0) ?></td>
            <td><?= floatval($row['dependence_hours'] ?? 0) ?></td>
            <td><?= floatval($row['extra_hours'] ?? 0) ?></td>
            <td><?= floatval($row['operator_hours'] ?? 0) ?></td>
            <td><?= floatval($row['maintenance_fault'] ?? 0) ?></td>
            <td><?= floatval($row['hr_fault'] ?? 0) ?></td>
            <td><?= floatval($row['other_fault_hours'] ?? 0) ?></td>
            <td><?= floatval($row['counter_diff'] ?? 0) ?></td>
            <td><?= htmlspecialchars($row['fault_type'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['fault_department'] ?? '—') ?></td>
            <td class="text-truncate" style="max-width:100px;" title="<?= htmlspecialchars($row['work_notes'] ?? '') ?>"><?= htmlspecialchars($row['work_notes'] ?? '—') ?></td>
            <td class="text-truncate" style="max-width:100px;" title="<?= htmlspecialchars($row['fault_notes'] ?? '') ?>"><?= htmlspecialchars($row['fault_notes'] ?? '—') ?></td>
            <td>
              <button class="btn-note" onclick="openNotes(<?= $row['id'] ?>)"
                      title="عرض / إضافة ملاحظة">
                <i class="fa fa-comment-dots"></i>
                <?php if (intval($row['notes_count']) > 0): ?>
                  <span class="note-cnt"><?= $row['notes_count'] ?></span>
                <?php endif; ?>
              </button>
            </td>
            <!-- عمود الاعتماد والتفاصيل (مدمج) -->
            <td style="white-space:nowrap;">
              <div class="d-inline-flex align-items-center gap-1">
                <?php
                  $max_l = intval($row['max_approved_level'] ?? 0);
                  for ($lv = 1; $lv <= 4; $lv++):
                    $lv_info = $level_role_name[$lv];
                    $done    = ($max_l >= $lv);
                ?>
                <span class="apv-circle <?= $done ? 'apv-done' : 'apv-pending' ?>"
                      title="<?= $lv_info['label'] ?><?= $done ? '' : ' — لم يعتمد بعد' ?>">
                  <i class="fa fa-user"></i>
                </span>
                <?php endfor; ?>
                <span class="apv-sep">|</span>
                <a href="../Timesheet/timesheet_details.php?id=<?= intval($row['id']) ?>" target="_blank"
                   class="apv-eye" title="عرض تفاصيل التايمشيت">
                  <i class="fa fa-eye"></i>
                </a>
                <?php if (!$is_admin && !$is_site_manager): ?>
                <button class="apv-approve" onclick="approveSingle(<?= $row['id'] ?>)"
                        title="اعتماد هذا السجل">
                  <i class="fa fa-check"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       جدول ٢: التايمشيت المعتمد نهائياً
  ══════════════════════════════════════════════════════════ -->
  <div class="table-card">
    <div class="card-header-custom">
      <div class="ch-icon" style="background:#198754;"><i class="fa fa-shield-check"></i></div>
      <div>
        <h5>التايمشيت المعتمد نهائياً</h5>
        <small class="text-muted">آخر 100 سجل حصلوا على اعتماد المستوى الرابع (مدير المشغلين)</small>
      </div>
      <span class="badge-count badge bg-success"><?= count($approved_rows) ?> سجل</span>
    </div>

    <div class="table-responsive">
      <table id="tbl-approved" class="ha-table display nowrap" style="width:100%">
        <thead>
          <tr>
            <th>#</th>
            <th>التاريخ</th>
            <th>الوردية</th>
            <th>المشروع</th>
            <th>المورد</th>
            <th>الآلية</th>
            <th>المشغل</th>
            <th> المنفذة</th>
            <th> الانتظار</th>
            <th> الأعطال</th>
            <th>المجموع </th>
            <th class="col-g-hours nosort">ساعات الوردية</th>
            <th class="col-g-hours nosort">ساعات الاعتماد</th>
            <th class="col-g-hours nosort">ساعات إضافية</th>
            <th class="col-g-hours nosort">ساعات المشغل</th>
            <th class="col-g-faults nosort">عطل صيانة</th>
            <th class="col-g-faults nosort">عطل بشري</th>
            <th class="col-g-faults nosort">أعطال أخرى</th>
            <th class="col-g-faults nosort">فرق العداد</th>
            <th class="col-g-faults nosort">نوع العطل</th>
            <th class="col-g-faults nosort">القسم المسؤول</th>
            <th class="col-g-notes nosort">ملاحظات العمل</th>
            <th class="col-g-faults nosort">ملاحظات الأعطال</th>
            <th class="nosort">اعتمد بواسطة</th>
            <th class="nosort">تاريخ الاعتماد</th>
            <th class="nosort">ملاحظات</th>
            <th class="nosort" style="white-space:nowrap;"></th>
          </tr>
        </thead>
        <tbody>
        <?php $idx = 1; foreach ($approved_rows as $row):
          // تفاصيل اعتمادات هذا السجل (تم جلبها مسبقاً دفعة واحدة)
          $row_approvals = $all_approval_details[intval($row['id'])] ?? [];
          $_arow_equip = trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''));
        ?>
          <tr data-project="<?= htmlspecialchars($row['project_name'] ?? '') ?>"
              data-supplier="<?= htmlspecialchars($row['supplier_name'] ?? '') ?>"
              data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
              data-equip="<?= htmlspecialchars($_arow_equip) ?>">
            <td><?= $idx++ ?></td>
            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
            <td>
              <?php $shift = $row['shift'] ?? '';
                    $shift_ar = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : $shift);
                    $shift_bg = ($shift === 'D') ? '#fff3cd' : '#d1ecf1';
                    $shift_clr= ($shift === 'D') ? '#856404' : '#0c5460';
              ?>
              <span style="background:<?=$shift_bg?>;color:<?=$shift_clr?>;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600;">
                <?= $shift_ar ?>
              </span>
            </td>
            <td><span class="text-truncate d-block" style="max-width:120px;" title="<?= htmlspecialchars($row['project_name'] ?? '') ?>"><?= htmlspecialchars($row['project_name'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars(trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''))) ?: '—' ?></td>
            <td><?= htmlspecialchars($row['driver_name'] ?? '—') ?></td>
            <td><?= floatval($row['executed_hours'] ?? 0) ?></td>
            <td><?= floatval($row['standby_hours'] ?? 0) ?></td>
            <td><?= floatval($row['total_fault_hours'] ?? 0) ?></td>
            <td><strong><?= floatval($row['total_work_hours'] ?? 0) ?></strong></td>
            <td><?= floatval($row['shift_hours'] ?? 0) ?></td>
            <td><?= floatval($row['dependence_hours'] ?? 0) ?></td>
            <td><?= floatval($row['extra_hours'] ?? 0) ?></td>
            <td><?= floatval($row['operator_hours'] ?? 0) ?></td>
            <td><?= floatval($row['maintenance_fault'] ?? 0) ?></td>
            <td><?= floatval($row['hr_fault'] ?? 0) ?></td>
            <td><?= floatval($row['other_fault_hours'] ?? 0) ?></td>
            <td><?= floatval($row['counter_diff'] ?? 0) ?></td>
            <td><?= htmlspecialchars($row['fault_type'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['fault_department'] ?? '—') ?></td>
            <td class="text-truncate" style="max-width:100px;" title="<?= htmlspecialchars($row['work_notes'] ?? '') ?>"><?= htmlspecialchars($row['work_notes'] ?? '—') ?></td>
            <td class="text-truncate" style="max-width:100px;" title="<?= htmlspecialchars($row['fault_notes'] ?? '') ?>"><?= htmlspecialchars($row['fault_notes'] ?? '—') ?></td>

            <!-- ══ عمود "اعتمد بواسطة": 4 دوائر خضراء بـ tooltip ══ -->
            <td style="white-space:nowrap;">
              <div class="d-inline-flex align-items-center gap-1">
                <?php for ($lv = 1; $lv <= 4; $lv++):
                  $lv_info  = $level_role_name[$lv];
                  $lv_data  = $row_approvals[$lv] ?? null;
                  $apv_name = $lv_data ? htmlspecialchars($lv_data['approved_by_name']) : '—';
                  $apv_date = $lv_data ? date('Y-m-d H:i', strtotime($lv_data['approved_at'])) : '—';
                  $apv_role = htmlspecialchars($lv_info['label']);
                ?>
                <span class="apv-circle-wrap">
                  <span class="apv-circle apv-done">
                    <i class="fa fa-user"></i>
                  </span>
                  <span class="apv-tooltip">
                    <span class="tt-role"><?= $apv_role ?></span>
                    <span class="tt-name"><?= $apv_name ?></span>
                    <span class="tt-date"><i class="fa fa-calendar-alt" style="margin-left:3px;"></i><?= $apv_date ?></span>
                  </span>
                </span>
                <?php endfor; ?>
              </div>
            </td>
            <!-- ══════════════════════════════════════════════════════ -->

            <td>
              <span style="font-size:.78rem;color:#6c757d;">
                <?= date('Y-m-d H:i', strtotime($row['final_approved_at'] ?? 'now')) ?>
              </span>
            </td>
            <td>
              <button class="btn-note" onclick="openNotes(<?= $row['id'] ?>)"
                      title="عرض الملاحظات">
                <i class="fa fa-comment-dots"></i>
                <?php if (intval($row['notes_count']) > 0): ?>
                  <span class="note-cnt"><?= $row['notes_count'] ?></span>
                <?php endif; ?>
              </button>
              <span class="apv-sep">|</span>
                <a href="../Timesheet/timesheet_details.php?id=<?= intval($row['id']) ?>" target="_blank"
                   class="apv-eye" title="عرض تفاصيل التايمشيت">
                  <i class="fa fa-eye"></i>
                </a>
            </td>
            <td style="white-space:nowrap;">
              <div class="d-inline-flex align-items-center gap-1">
                <?php for ($lv = 1; $lv <= 4; $lv++): $lv_info = $level_role_name[$lv]; ?>
                <span class="apv-circle apv-done"
                      title="<?= $lv_info['label'] ?> — معتمد">
                  <i class="fa fa-user"></i>
                </span>
                <?php endfor; ?>
                
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- end page-wrapper -->

<!-- ══════════════════════════════════════════════════════════════
     Modal: الملاحظات
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-comment-dots me-2 text-primary"></i>
          ملاحظات السجل #<span id="modal-ts-id">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- قائمة الملاحظات الموجودة -->
        <div id="notes-list" class="mb-3">
          <div class="text-center text-muted py-3">
            <i class="fa fa-spinner fa-spin me-1"></i> جارٍ التحميل...
          </div>
        </div>

        <hr>

        <!-- نموذج إضافة ملاحظة جديدة -->
        <div>
          <h6 class="fw-bold mb-3"><i class="fa fa-plus-circle me-1 text-primary"></i> إضافة ملاحظة جديدة</h6>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold">العمود / الحقل المعني</label>
              <select class="form-select form-select-sm" id="note-col-select">
                <option value="">— اختر العمود —</option>
                <?php foreach ($column_labels as $col_key => $col_lbl): ?>
                <option value="<?= $col_key ?>" data-label="<?= htmlspecialchars($col_lbl) ?>">
                  <?= htmlspecialchars($col_lbl) ?>
                </option>
                <?php endforeach; ?>
                <option value="other" data-label="أخرى">أخرى</option>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label fw-semibold">نص الملاحظة</label>
              <textarea class="form-control form-control-sm" id="note-text-input"
                        rows="3" placeholder="اكتب ملاحظتك هنا..."></textarea>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm fw-bold" onclick="submitNote()">
              <i class="fa fa-save me-1"></i> حفظ الملاحظة
            </button>
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إغلاق</button>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     Modal: تأكيد الاعتماد
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-check-circle me-2 text-success"></i> تأكيد الاعتماد
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="confirm-approve-msg" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success fw-bold" id="btn-confirm-approve">
          <i class="fa fa-check me-1"></i> نعم، اعتمد
        </button>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Toasts ── -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="approvalToast" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-bold" id="toast-msg"></div>
      <button type="button" class="btn-close btn-close-white ms-auto me-2" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JS
══════════════════════════════════════════════════════════════ -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>

<script>
// ── متغيرات الحالة ──────────────────────────────────────────
var currentTsId      = 0;
var pendingApproveIds = [];
var dtPending   = null;
var dtApproved  = null;

// ── تهيئة DataTables + ربط الأحداث بعد تحميل DOM ────────────
$(function () {
  var dtOpts = {
    language : { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json' },
    responsive: true,
    pageLength: 25,
    order     : [[1, 'desc']],
    dom       : '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>rt<"row mt-2"<"col-sm-6"i><"col-sm-6"p>>',
  };

  dtPending = $('#tbl-pending').DataTable($.extend({}, dtOpts, {
    order: [[<?= (!$is_admin && !$is_site_manager) ? 2 : 1 ?>, 'desc']],
    columnDefs: [{ orderable: false, targets: '.nosort' }]
  }));
  // إخفاء المجموعات عند التحميل
  dtPending.columns('.col-g-hours').visible(false);
  dtPending.columns('.col-g-faults').visible(false);
  dtPending.columns('.col-g-notes').visible(false);

  dtApproved = $('#tbl-approved').DataTable($.extend({}, dtOpts, {
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: '.nosort' }]
  }));
  dtApproved.columns('.col-g-hours').visible(false);
  dtApproved.columns('.col-g-faults').visible(false);
  dtApproved.columns('.col-g-notes').visible(false);

  // حساب إجمالي الملاحظات
  var totalNotes = 0;
  $('.note-cnt').each(function(){ totalNotes += parseInt($(this).text()) || 0; });
  $('#total-notes-count').text(totalNotes);

  // ── زر تأكيد الاعتماد ───────────────────────────────────────
  $('#btn-confirm-approve').on('click', function () {
    if (pendingApproveIds.length === 0) return;
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جارٍ الاعتماد...';

    $.ajax({
      url      : 'hours_approval_handler.php',
      method   : 'POST',
      dataType : 'json',
      data     : { action: 'approve', ids: pendingApproveIds.join(',') },
      success  : function(res) {
        var modal = bootstrap.Modal.getInstance(document.getElementById('confirmApproveModal'));
        if (modal) modal.hide();
        if (res.success) {
          showToast('✅ ' + res.message, 'success');
          setTimeout(function(){ location.reload(); }, 1200);
        } else {
          showToast('❌ ' + res.message, 'danger');
        }
      },
      error: function(xhr) {
        showToast('❌ حدث خطأ في الاتصال: ' + xhr.status, 'danger');
      },
      complete: function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check me-1"></i> نعم، اعتمد';
      }
    });
  });
});

// ── دوال الاعتماد ────────────────────────────────────────────
// ── تبديل مجموعات الأعمدة ────────────────────────────────────
var groupState = { hours: false, faults: false, notes: false };
function toggleColGroup(btn) {
  var group = btn.getAttribute('data-group');
  groupState[group] = !groupState[group];
  var vis = groupState[group];
  if (dtPending)  dtPending.columns('.col-g-' + group).visible(vis);
  if (dtApproved) dtApproved.columns('.col-g-' + group).visible(vis);
  if (vis) $(btn).addClass('cg-active');
  else     $(btn).removeClass('cg-active');
}

function approveSingle(id) {
  pendingApproveIds = [id];
  $('#confirm-approve-msg').html(
    'هل تريد اعتماد السجل رقم <strong>#' + id + '</strong>؟'
  );
  var modal = new bootstrap.Modal(document.getElementById('confirmApproveModal'));
  modal.show();
}

function approveSelected() {
  var checked = [];
  $('.row-chk:checked').each(function() { checked.push($(this).val()); });
  if (checked.length === 0) {
    showToast('يرجى تحديد سجل واحد على الأقل', 'warning');
    return;
  }
  pendingApproveIds = checked;
  $('#confirm-approve-msg').html(
    'هل تريد اعتماد <strong>' + checked.length + '</strong> سجل محدد؟'
  );
  var modal = new bootstrap.Modal(document.getElementById('confirmApproveModal'));
  modal.show();
}

// ── دوال تحديد الكل ─────────────────────────────────────────
function selectAllPending() {
  $('.row-chk').prop('checked', true);
  $('#chk-all-pending').prop('checked', true);
  updateSelCount();
}
function deselectAllPending() {
  $('.row-chk').prop('checked', false);
  $('#chk-all-pending').prop('checked', false);
  updateSelCount();
}
function toggleAllPending(cb) {
  $('.row-chk').prop('checked', cb.checked);
  updateSelCount();
}
function updateSelCount() {
  const cnt = $('.row-chk:checked').length;
  $('#sel-count-label').text(cnt > 0 ? cnt + ' سجل محدد' : 'لا توجد سجلات محددة');
  // تمييز الصفوف
  $('#tbl-pending tbody tr').each(function(){
    const chk = $(this).find('.row-chk');
    if (chk.length && chk.is(':checked')) {
      $(this).addClass('selected-row');
    } else {
      $(this).removeClass('selected-row');
    }
  });
}

// ── دوال الملاحظات ───────────────────────────────────────────
function openNotes(tsId) {
  currentTsId = tsId;
  $('#modal-ts-id').text(tsId);
  $('#notes-list').html('<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin me-1"></i> جارٍ التحميل...</div>');
  $('#note-col-select').val('');
  $('#note-text-input').val('');

  var modal = new bootstrap.Modal(document.getElementById('notesModal'));
  modal.show();

  loadNotes(tsId);
}

function loadNotes(tsId) {
  $.ajax({
    url      : 'hours_approval_handler.php',
    method   : 'POST',
    dataType : 'json',
    data     : { action: 'get_notes', timesheet_id: tsId },
    success  : function(res) {
      if (!res.success) {
        $('#notes-list').html('<div class="alert alert-danger">' + res.message + '</div>');
        return;
      }
      if (res.notes.length === 0) {
        $('#notes-list').html('<div class="text-center text-muted py-2"><i class="fa fa-comment-slash me-1"></i>لا توجد ملاحظات بعد</div>');
        return;
      }
      let html = '';
      res.notes.forEach(function(n) {
        const roleTxt = n.created_by_role_label ? ' - ' + n.created_by_role_label : '';
        html += `
          <div class="note-item" id="note-item-${n.id}">
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="note-col-badge">${escHtml(n.column_label)}</span>
              <span class="fw-semibold small">${escHtml(n.created_by_name)}${escHtml(roleTxt)}</span>
            </div>
            <div class="note-text">${escHtml(n.note_text)}</div>
            <div class="note-meta"><i class="fa fa-calendar-alt me-1"></i>${escHtml(n.created_at)}</div>
          </div>
        `;
      });
      $('#notes-list').html(html);
    },
    error: function() {
      $('#notes-list').html('<div class="alert alert-danger">فشل تحميل الملاحظات</div>');
    }
  });
}

function submitNote() {
  var col   = $('#note-col-select').val();
  var label = $('#note-col-select option:selected').data('label') || col;
  var text  = $('#note-text-input').val().trim();

  if (!col) { showToast('يرجى اختيار العمود المعني', 'warning'); return; }
  if (!text){ showToast('يرجى كتابة نص الملاحظة', 'warning'); return; }

  $.ajax({
    url      : 'hours_approval_handler.php',
    method   : 'POST',
    dataType : 'json',
    data     : {
      action      : 'add_note',
      timesheet_id: currentTsId,
      column_name : col,
      column_label: label,
      note_text   : text
    },
    success: function(res) {
      if (res.success) {
        showToast('✅ تمت إضافة الملاحظة', 'success');
        $('#note-col-select').val('');
        $('#note-text-input').val('');
        loadNotes(currentTsId);
        // تحديث عداد الملاحظات في الجدول
        updateNoteCountBadge(currentTsId, 1);
      } else {
        showToast('❌ ' + res.message, 'danger');
      }
    },
    error: function() { showToast('❌ خطأ في الاتصال', 'danger'); }
  });
}

function updateNoteCountBadge(tsId, delta) {
  // نحدّث عداد الملاحظة في زر الملاحظة في الجدول
  $('tr[data-id="' + tsId + '"]').find('.btn-note').each(function(){
    const cntEl = $(this).find('.note-cnt');
    let current = parseInt(cntEl.text()) || 0;
    const newVal = Math.max(0, current + delta);
    if (newVal > 0) {
      if (cntEl.length) cntEl.text(newVal);
      else $(this).append('<span class="note-cnt">' + newVal + '</span>');
    } else {
      cntEl.remove();
    }
  });
}

// ── Toast ────────────────────────────────────────────────────
function showToast(msg, type) {
  const el = document.getElementById('approvalToast');
  el.className = 'toast align-items-center text-white border-0 bg-' + (type || 'success');
  document.getElementById('toast-msg').textContent = msg;
  const toast = new bootstrap.Toast(el, { delay: 3000 });
  toast.show();
}

// ── Escape HTML ──────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}

// ── الفلاتر ──────────────────────────────────────────────────
// دالة مخصصة لـ DataTables تفلتر بناءً على data attributes في <tr>
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
  var tblId = settings.nTable.id;
  if (tblId !== 'tbl-pending' && tblId !== 'tbl-approved') return true;

  var nTr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
  if (!nTr) return true;

  var $tr = $(nTr);
  var fp  = $('#filter-project').val();
  var fs  = $('#filter-supplier').val();
  var fd  = $('#filter-driver').val();
  var fe  = $('#filter-equip').val();

  if (fp && $tr.data('project')  !== fp) return false;
  if (fs && $tr.data('supplier') !== fs) return false;
  if (fd && $tr.data('driver')   !== fd) return false;
  if (fe && $tr.data('equip')    !== fe) return false;
  return true;
});

function applyFilters() {
  if (dtPending)  dtPending.draw();
  if (dtApproved) dtApproved.draw();
  updateFilterInfo();
}

function resetFilters() {
  $('#filter-project').val('');
  $('#filter-supplier').val('');
  $('#filter-driver').val('');
  $('#filter-equip').val('');
  applyFilters();
}

function updateFilterInfo() {
  var fp = $('#filter-project').val();
  var fs = $('#filter-supplier').val();
  var fd = $('#filter-driver').val();
  var fe = $('#filter-equip').val();
  var parts = [];
  if (fp) parts.push('مشروع: ' + fp);
  if (fs) parts.push('مورد: ' + fs);
  if (fd) parts.push('مشغل: ' + fd);
  if (fe) parts.push('آلية: ' + fe);
  var info = $('#active-filters-info');
  if (parts.length > 0) {
    $('#active-filters-text').text(parts.join(' | '));
    info.show();
  } else {
    info.hide();
  }
}

// تطبيق الفلاتر عند تغيير أي قائمة منسدلة
$(document).on('change', '#filter-project, #filter-supplier, #filter-driver, #filter-equip', function(){
  applyFilters();
});
</script>
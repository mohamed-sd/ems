<?php
/**
 * hours_approval_followup.php
 * شاشة متابعة الاعتمادات المنقولة بين المستويات
 */

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config.php';

if (!function_exists('ha_table_has_column')) {
    function ha_table_has_column($conn, $table, $column)
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $sql = "SHOW COLUMNS FROM {$table} LIKE '" . mysqli_real_escape_string($conn, $column) . "'";
        $res = @mysqli_query($conn, $sql);
        return $res && mysqli_num_rows($res) > 0;
    }
}

$role         = strval($_SESSION['user']['role']);
$user_id      = intval($_SESSION['user']['id']);
$company_id   = intval($_SESSION['user']['company_id'] ?? 0);
$session_proj = intval($_SESSION['user']['project_id'] ?? 0);
$session_mine = intval($_SESSION['user']['mine_id'] ?? 0);

$allowed_roles = array('-1', '1', '2', '3', '4', '5');
if (!in_array($role, $allowed_roles, true)) {
    header('Location: ../main/dashboard.php');
    exit();
}

// التأكد من وجود جداول الاعتماد والملاحظات
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

$is_admin = ($role === '-1');
$is_site_manager = ($role === '5');
$role_level_map = array('1' => 1, '2' => 2, '3' => 3, '4' => 4);
$my_level = $role_level_map[$role] ?? 0;
$next_level = ($my_level > 0 && $my_level < 4) ? $my_level + 1 : 0;

$level_role_name = array(
    1 => array('label' => 'مدير المشاريع', 'color' => '#0d6efd'),
    2 => array('label' => 'مدير الموردين', 'color' => '#6f42c1'),
    3 => array('label' => 'مدير الأسطول', 'color' => '#198754'),
    4 => array('label' => 'مدير المشغلين', 'color' => '#fd7e14'),
);

$ops_project_col = ha_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';
$has_ops_mine = ha_table_has_column($conn, 'operations', 'mine_id');

$company_scope_ts = '';
if (!$is_admin && $company_id > 0) {
    $company_scope_ts = " AND (t.company_id = $company_id OR t.company_id IS NULL)";
}

$site_scope_ts = '';
if ($is_site_manager) {
    if ($session_proj > 0) {
        $site_scope_ts .= " AND o.{$ops_project_col} = $session_proj";
    }
    if ($session_mine > 0 && $has_ops_mine) {
        $site_scope_ts .= " AND o.mine_id = $session_mine";
    }
}

// فلتر نوع المعدة: 0=الكل، 1=حفارات، 2=قلابات، 3=خرامات
$equip_type_filter = intval($_GET['equip_type'] ?? 0);
if (!in_array($equip_type_filter, [0, 1, 2, 3], true)) {
    $equip_type_filter = 0;
}
$equip_type_where = ($equip_type_filter > 0) ? " AND e.type = $equip_type_filter" : '';
$equip_type_label = ($equip_type_filter === 1) ? 'حفارات' : (($equip_type_filter === 2) ? 'قلابات' : (($equip_type_filter === 3) ? 'خرامات' : 'الكل'));

$my_followup_where = '1=0';
if ($is_admin) {
    $my_followup_where = "EXISTS (
        SELECT 1 FROM timesheet_approvals tx
        WHERE tx.timesheet_id = t.id AND tx.status = 1
    ) AND NOT EXISTS (
        SELECT 1 FROM timesheet_approvals tf
        WHERE tf.timesheet_id = t.id AND tf.approval_level = 4 AND tf.status = 1
    )";
} elseif ($my_level >= 1 && $my_level <= 3) {
    // كل مستوى يرى السجلات التي وصلت إليه (اعتمدها هو أو أي شخص في مستواه)
    // وتبقى مرئية حتى يكتمل الاعتماد النهائي (المستوى 4)
    $my_followup_where = "EXISTS (
        SELECT 1 FROM timesheet_approvals tm
        WHERE tm.timesheet_id = t.id
          AND tm.approval_level = $my_level
          AND tm.status = 1
    ) AND NOT EXISTS (
        SELECT 1 FROM timesheet_approvals tn
        WHERE tn.timesheet_id = t.id
          AND tn.approval_level = 4
          AND tn.status = 1
    )";
} elseif ($my_level === 4) {
    // المستوى الرابع: يرى السجلات التي وصلت إليه ولم يعتمدها بعد
    $my_followup_where = "EXISTS (
        SELECT 1 FROM timesheet_approvals tm
        WHERE tm.timesheet_id = t.id
          AND tm.approval_level = 3
          AND tm.status = 1
    ) AND NOT EXISTS (
        SELECT 1 FROM timesheet_approvals tn
        WHERE tn.timesheet_id = t.id
          AND tn.approval_level = 4
          AND tn.status = 1
    )";
}

$followup_sql = "
    SELECT t.*,
           d.name AS driver_name,
           e.code AS equip_code,
           e.name AS equip_name,
           e.type AS equip_type,
           s.name AS supplier_name,
           p.name AS project_name,
           (SELECT COUNT(*) FROM timesheet_approval_notes n WHERE n.timesheet_id = t.id AND n.status = 1) AS notes_count,
           (SELECT MAX(ta_l.approval_level) FROM timesheet_approvals ta_l WHERE ta_l.timesheet_id = t.id AND ta_l.status = 1) AS max_approved_level,
           (SELECT ta_m.approved_at FROM timesheet_approvals ta_m
             WHERE ta_m.timesheet_id = t.id " . ($my_level > 0 ? "AND ta_m.approval_level = $my_level AND ta_m.approved_by = $user_id" : "") . "
             AND ta_m.status = 1 ORDER BY ta_m.approved_at DESC LIMIT 1) AS my_approved_at
    FROM timesheet t
    LEFT JOIN operations o ON o.id = t.operator
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN suppliers s ON s.id = e.suppliers
    LEFT JOIN project p ON p.id = o.$ops_project_col
    LEFT JOIN drivers d ON d.id = t.driver
    WHERE t.status = 1
      AND $my_followup_where
      $company_scope_ts
      $site_scope_ts
      $equip_type_where
    ORDER BY COALESCE(my_approved_at, t.date) DESC, t.id DESC
    LIMIT 700
";

$followup_rows = array();
$followup_result = $conn->query($followup_sql);
if ($followup_result) {
    while ($r = $followup_result->fetch_assoc()) {
        $followup_rows[] = $r;
    }
}

$final_sql = "
    SELECT t.*,
           d.name AS driver_name,
           e.code AS equip_code,
           e.name AS equip_name,
           e.type AS equip_type,
           s.name AS supplier_name,
           p.name AS project_name,
           ta_final.approved_by_name AS final_approver,
           ta_final.approved_at AS final_approved_at,
           (SELECT COUNT(*) FROM timesheet_approval_notes n WHERE n.timesheet_id = t.id AND n.status = 1) AS notes_count
    FROM timesheet t
    INNER JOIN timesheet_approvals ta_final
      ON ta_final.timesheet_id = t.id
      AND ta_final.approval_level = 4
      AND ta_final.status = 1
    LEFT JOIN operations o ON o.id = t.operator
    LEFT JOIN equipments e ON e.id = o.equipment
    LEFT JOIN suppliers s ON s.id = e.suppliers
    LEFT JOIN project p ON p.id = o.$ops_project_col
    LEFT JOIN drivers d ON d.id = t.driver
    WHERE t.status = 1
      $company_scope_ts
      $site_scope_ts
      $equip_type_where
    ORDER BY ta_final.approved_at DESC
    LIMIT 250
";

$final_rows = array();
$final_result = $conn->query($final_sql);
if ($final_result) {
    while ($r = $final_result->fetch_assoc()) {
        $final_rows[] = $r;
    }
}

$filter_projects = array_values(array_unique(array_filter(array_column($followup_rows, 'project_name'))));
$filter_suppliers = array_values(array_unique(array_filter(array_column($followup_rows, 'supplier_name'))));
$filter_drivers = array_values(array_unique(array_filter(array_column($followup_rows, 'driver_name'))));
$filter_equips = array();
foreach ($followup_rows as $_r) {
  $_eq = trim(($_r['equip_code'] ?? '') . ' ' . ($_r['equip_name'] ?? ''));
  if ($_eq !== '') {
    $filter_equips[] = $_eq;
  }
}
$filter_equips = array_values(array_unique($filter_equips));
sort($filter_projects);
sort($filter_suppliers);
sort($filter_drivers);
sort($filter_equips);

$filter_projects_final = array_values(array_unique(array_filter(array_column($final_rows, 'project_name'))));
$filter_suppliers_final = array_values(array_unique(array_filter(array_column($final_rows, 'supplier_name'))));
$filter_drivers_final = array_values(array_unique(array_filter(array_column($final_rows, 'driver_name'))));
$filter_equips_final = array();
foreach ($final_rows as $_r) {
  $_eq = trim(($_r['equip_code'] ?? '') . ' ' . ($_r['equip_name'] ?? ''));
  if ($_eq !== '') {
    $filter_equips_final[] = $_eq;
  }
}
$filter_equips_final = array_values(array_unique($filter_equips_final));
sort($filter_projects_final);
sort($filter_suppliers_final);
sort($filter_drivers_final);
sort($filter_equips_final);

// بناء خريطة الاعتمادات لكل سجل (استعلام واحد لكل السجلات)
$approvals_map = array();
$_all_ts_ids = array();
foreach ($followup_rows as $_r) { $_all_ts_ids[] = intval($_r['id']); }
foreach ($final_rows   as $_r) { $_all_ts_ids[] = intval($_r['id']); }
$_all_ts_ids = array_values(array_unique($_all_ts_ids));
if (!empty($_all_ts_ids)) {
    $_ids_sql = implode(',', $_all_ts_ids);
    $_app_res = $conn->query("SELECT timesheet_id, approval_level, approved_by_name, approved_at
                               FROM timesheet_approvals
                               WHERE timesheet_id IN ($_ids_sql) AND status = 1");
    if ($_app_res) {
        while ($_app_row = $_app_res->fetch_assoc()) {
            $approvals_map[intval($_app_row['timesheet_id'])][intval($_app_row['approval_level'])] = array(
                'name' => $_app_row['approved_by_name'],
                'at'   => $_app_row['approved_at'],
            );
        }
    }
}

// أعداد الأعطال لكل تايم شيت (جدول timesheet_failure_hours)
$fault_counts_map_fup = array();
$notes_counts_map_fup = array();
$_fup_fc_check = @$conn->query("SHOW TABLES LIKE 'timesheet_failure_hours'");
if ($_fup_fc_check && $_fup_fc_check->num_rows > 0 && !empty($_all_ts_ids)) {
    $_fup_fc_ids = implode(',', $_all_ts_ids);
    $_fup_fc_res = $conn->query("SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_failure_hours WHERE timesheet_id IN ($_fup_fc_ids) AND status = 1 GROUP BY timesheet_id");
    if ($_fup_fc_res) {
        while ($_fup_fc_row = $_fup_fc_res->fetch_assoc()) {
            $fault_counts_map_fup[intval($_fup_fc_row['timesheet_id'])] = intval($_fup_fc_row['cnt']);
        }
    }
}

// تحميل عدد الملاحظات المسجلة لكل تايم شيت
$_fup_notes_check = @$conn->query("SHOW TABLES LIKE 'timesheet_approval_notes'");
if ($_fup_notes_check && $_fup_notes_check->num_rows > 0 && !empty($_all_ts_ids)) {
    $_fup_notes_ids = implode(',', $_all_ts_ids);
    $_fup_notes_res = $conn->query("SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_approval_notes WHERE timesheet_id IN ($_fup_notes_ids) AND status = 1 GROUP BY timesheet_id");
    if ($_fup_notes_res) {
        while ($_fup_notes_row = $_fup_notes_res->fetch_assoc()) {
            $notes_counts_map_fup[intval($_fup_notes_row['timesheet_id'])] = intval($_fup_notes_row['cnt']);
        }
    }
}

// قائمة الأعمدة المتاحة للتعليق (مطابقة لشاشة الاعتماد الرئيسية)
$column_labels = array(
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
);

$page_title = 'متابعة الاعتمادات المنقولة';
include('../inheader.php');
?>
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
:root {
  --ha-primary: #0f4c81;
  --ha-accent: #f59e0b;
  --ha-ok: #15803d;
  --ha-muted: #64748b;
  --ha-bg: #f1f5f9;
}
body { background: var(--ha-bg); }
.page-wrapper { 
    padding: 18px; 
    width: 100%;
}

.top-head {
  background: linear-gradient(135deg, #0f4c81, #1d6fa5);
  border-radius: 16px;
  padding: 16px;
  color: #fff;
  margin-bottom: 16px;
  box-shadow: 0 8px 26px rgba(15, 76, 129, 0.22);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.top-head .title { font-size: 1.1rem; font-weight: 800; margin: 0; }
.top-head .sub { font-size: .82rem; opacity: .95; }

.quick-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
.quick-stat {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}
.quick-stat .label { font-size: .78rem; color: var(--ha-muted); font-weight: 700; }
.quick-stat .value { font-size: 1.5rem; font-weight: 900; color: #0f172a; line-height: 1.1; }

.filters-wrap {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px;
  margin-bottom: 14px;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 10px;
}
.filters-wrap .fg {
  min-width: 150px;
  flex: 1 1 150px;
}
.filters-wrap label {
  display: block;
  font-size: .74rem;
  color: #475569;
  font-weight: 700;
  margin-bottom: 4px;
}
.filters-wrap select,
.filters-wrap input[type="date"] {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  min-height: 36px;
  padding: 5px 8px;
  background: #fff;
}
.filters-wrap .btns {
  display: flex;
  gap: 8px;
}
.filters-wrap .btns .btn {
  min-height: 36px;
  border-radius: 8px;
  font-weight: 700;
  font-size: .8rem;
}

.table-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 14px;
  margin-bottom: 16px;
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}
.card-title {
  margin: 0 0 12px;
  font-size: .98rem;
  font-weight: 800;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
}
.table-wrap { overflow-x: auto; }
.ha-table { width: 100% !important; font-size: .82rem; }
.ha-table thead th {
  background: #0f172a;
  color: #fff;
  white-space: nowrap;
  font-weight: 700;
}
.ha-table td { white-space: nowrap; }

.lvl-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 999px;
  padding: 3px 8px;
  font-size: .72rem;
  font-weight: 700;
  background: #e2e8f0;
  color: #334155;
}
.note-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 6px;
  padding: 3px 7px;
}
.note-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: .66rem;
  font-weight: 800;
  margin-right: 4px;
}

/* دوائر مسار الاعتماد */
.approval-track {
  display: inline-flex;
  align-items: center;
  gap: 0;
  direction: ltr;
}
.approval-track .ap-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}
.approval-track .ap-step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 12px;
  right: -13px;
  width: 13px;
  height: 2px;
  background: #cbd5e1;
  z-index: 0;
}
.approval-track .ap-step:not(:last-child).done::after {
  background: #16a34a;
}
.ap-circle {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .65rem;
  font-weight: 800;
  cursor: default;
  position: relative;
  z-index: 1;
  border: 2px solid #cbd5e1;
  background: #f1f5f9;
  color: #94a3b8;
  transition: all .2s;
}
.ap-circle.done {
  border-color: #16a34a;
  background: #16a34a;
  color: #fff;
}
.ap-circle .ap-lbl {
  font-size: .55rem;
  font-weight: 700;
  margin-top: 2px;
  color: #64748b;
  white-space: nowrap;
}
.ap-step-wrap {
  display: flex;
  align-items: center;
  gap: 0;
}
.ap-connector {
  width: 14px;
  height: 2px;
  background: #cbd5e1;
}
.ap-connector.done {
  background: #16a34a;
}

.modal-content { border-radius: 12px; }
.note-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  padding: 10px;
  margin-bottom: 8px;
}

@media (max-width: 1024px) {
  .quick-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .page-wrapper { padding: 10px; }
  .quick-stats { grid-template-columns: 1fr; }
  .filters-wrap .fg { min-width: 100%; flex: 1 1 100%; }
  .filters-wrap .btns { width: 100%; }
  .filters-wrap .btns .btn { flex: 1; }
}
</style>

<div class="page-wrapper">
  <div class="top-head">
    <div>
      <h3 class="title"><i class="fa fa-route"></i> متابعة الاعتمادات المنقولة</h3>
      <div class="sub">
        <?php if ($is_admin): ?>
          عرض إداري للسجلات المنقولة بين المستويات ولم تصل للاعتماد النهائي بعد
        <?php elseif ($my_level >= 1 && $my_level <= 3): ?>
          تعرض السجلات التي اعتمدتها شخصياً وما زالت بانتظار اعتماد مستوى: <?= htmlspecialchars($level_role_name[$next_level]['label']) ?>
        <?php elseif ($my_level === 4): ?>
          لا توجد مستويات أدنى بعد مستواك، ستجد فقط سجلات الاعتماد النهائي بالأسفل
        <?php else: ?>
          عرض متابعة عام
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="hours_approval.php" class="btn btn-light btn-sm fw-bold"><i class="fa fa-arrow-right"></i> شاشة الاعتماد</a>
      <a href="<?= $is_admin ? '../admin/dashboard.php' : '../main/dashboard.php' ?>" class="btn btn-outline-light btn-sm fw-bold"><i class="fa fa-home"></i> لوحة التحكم</a>
    </div>
  </div>

  <div class="toolbar-row mb-3">
    <form method="get" class="d-flex align-items-center gap-2">
      <label for="equip_type" class="fw-semibold mb-0">نوع المعدة:</label>
      <select name="equip_type" id="equip_type" class="form-select form-select-sm" style="max-width:200px;">
        <option value="0" <?= $equip_type_filter === 0 ? 'selected' : '' ?>>الكل</option>
        <option value="1" <?= $equip_type_filter === 1 ? 'selected' : '' ?>>حفارات</option>
        <option value="2" <?= $equip_type_filter === 2 ? 'selected' : '' ?>>قلابات</option>
        <option value="3" <?= $equip_type_filter === 3 ? 'selected' : '' ?>>خرامات</option>
      </select>
      <button type="submit" class="btn btn-sm btn-primary fw-semibold">
        <i class="fa fa-filter me-1"></i> تطبيق الفلتر
      </button>
      <?php if ($equip_type_filter > 0): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-times me-1"></i> إلغاء الفلتر
        </a>
      <?php endif; ?>
      <span class="badge bg-info ms-2">عرض: <?= htmlspecialchars($equip_type_label) ?></span>
    </form>
  </div>

  <div class="quick-stats">
    <div class="quick-stat">
      <div class="label">سجلات قيد المتابعة</div>
      <div class="value"><?= count($followup_rows) ?></div>
    </div>
    <div class="quick-stat">
      <div class="label">معتمدة نهائياً</div>
      <div class="value"><?= count($final_rows) ?></div>
    </div>
    <div class="quick-stat">
      <div class="label">مستوى المستخدم</div>
      <div class="value"><?= $is_admin ? 'Admin' : ($my_level ?: '-') ?></div>
    </div>
    <div class="quick-stat">
      <div class="label">المستوى التالي</div>
      <div class="value"><?= ($next_level > 0 && isset($level_role_name[$next_level])) ? htmlspecialchars($level_role_name[$next_level]['label']) : '—' ?></div>
    </div>
  </div>

  <div class="filters-wrap">
    <div class="fg">
      <label>نوع المعدة</label>
      <select id="f-equipment-type">
        <option value="">الكل</option>
        <option value="1">حفارات</option>
        <option value="2">قلابات</option>
        <option value="3">خرامات</option>
      </select>
    </div>
    <div class="fg">
      <label>فلتر المشروع (قيد المتابعة)</label>
      <select id="f-project">
        <option value="">الكل</option>
        <?php foreach ($filter_projects as $v): ?>
        <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>فلتر المورد</label>
      <select id="f-supplier">
        <option value="">الكل</option>
        <?php foreach ($filter_suppliers as $v): ?>
        <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>فلتر السائق / المشغل</label>
      <select id="f-driver">
        <option value="">الكل</option>
        <?php foreach ($filter_drivers as $v): ?>
        <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>فلتر الآلية</label>
      <select id="f-equip">
        <option value="">الكل</option>
        <?php foreach ($filter_equips as $v): ?>
        <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fg">
      <label>من تاريخ</label>
      <input type="date" id="f-date-from">
    </div>
    <div class="fg">
      <label>إلى تاريخ</label>
      <input type="date" id="f-date-to">
    </div>
    <div class="btns">
      <button type="button" class="btn btn-primary" onclick="applyFollowupFilters()"><i class="fa fa-filter"></i> تطبيق</button>
      <button type="button" class="btn btn-outline-secondary" onclick="resetFollowupFilters()"><i class="fa fa-rotate-left"></i> إلغاء</button>
    </div>
  </div>

  <div class="table-card">
    <h5 class="card-title"><i class="fa fa-hourglass-half text-warning"></i> السجلات المنقولة وتنتظر المستوى التالي</h5>
    <div class="table-wrap">
      <table id="tbl-followup" class="display nowrap ha-table">
        <thead>
          <tr>
            <th>#</th>
            <th>التاريخ</th>
            <th>المشروع</th>
            <th>المورد</th>
            <th>الآلية</th>
            <th>المشغل</th>
            <th>ساعات منفذة</th>
            <th>إجمالي العمل</th>
            <th>مسار الاعتماد</th>
            <th>الأعطال المصنفة</th>
            <th>الملاحظات المسجلة</th>
            <th>تفاصيل</th>
          </tr>
        </thead>
        <tbody>
        <?php $idx = 1; foreach ($followup_rows as $row):
          $_equip = trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''));
          $_max = intval($row['max_approved_level'] ?? 0);
        ?>
          <tr data-project="<?= htmlspecialchars($row['project_name'] ?? '') ?>"
              data-supplier="<?= htmlspecialchars($row['supplier_name'] ?? '') ?>"
              data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
              data-equip="<?= htmlspecialchars($_equip) ?>"
              data-type="<?= intval($row['equip_type'] ?? 0) ?>"
              data-date="<?= htmlspecialchars($row['date'] ?? '') ?>"
              data-id="<?= intval($row['id']) ?>">
            <td><?= $idx++ ?></td>
            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['project_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($_equip ?: '—') ?></td>
            <td><?= htmlspecialchars($row['driver_name'] ?? '—') ?></td>
            <td><?= floatval($row['executed_hours'] ?? 0) ?></td>
            <td><strong><?= floatval($row['total_work_hours'] ?? 0) ?></strong></td>
            <td>
              <?php
                $_ts_id  = intval($row['id']);
                $_ap_levels = array(
                  1 => array('label'=>'L1','title'=>'مدير المشاريع'),
                  2 => array('label'=>'L2','title'=>'مدير الموردين'),
                  3 => array('label'=>'L3','title'=>'مدير الأسطول'),
                  4 => array('label'=>'L4','title'=>'مدير المشغلين'),
                );
              ?>
              <div class="approval-track">
                <?php foreach ($_ap_levels as $_lv => $_linfo): ?>
                  <?php if ($_lv > 1): ?><div class="ap-connector <?= isset($approvals_map[$_ts_id][$_lv - 1]) ? 'done' : '' ?>"></div><?php endif; ?>
                  <?php
                    $_is_done   = isset($approvals_map[$_ts_id][$_lv]);
                    $_tip_name  = $_is_done ? htmlspecialchars($approvals_map[$_ts_id][$_lv]['name']) : 'لم يعتمد بعد';
                    $_tip_at    = $_is_done ? date('Y-m-d H:i', strtotime($approvals_map[$_ts_id][$_lv]['at'])) : '';
                    $_tooltip   = $_linfo['title'] . ': ' . $_tip_name . ($_tip_at ? ' — ' . $_tip_at : '');
                  ?>
                  <div class="ap-circle <?= $_is_done ? 'done' : '' ?>" title="<?= $_tooltip ?>">
                    <?= $_is_done ? '<i class="fa fa-check" style="font-size:.6rem"></i>' : $_linfo['label'] ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </td>
            <td style="text-align:center;">
              <?php
                $_fup_fc_cnt = intval($fault_counts_map_fup[$row['id']] ?? 0);
                $_fup_leg_has = !empty($row['fault_type']) || !empty($row['fault_part']);
                $_fup_badge = $_fup_fc_cnt > 0 ? $_fup_fc_cnt : ($_fup_leg_has ? 1 : 0);
              ?>
              <?php if ($_fup_badge > 0): ?>
              <button class="note-btn fup-fault-btn" data-ts-id="<?= intval($row['id']) ?>" title="عرض الأعطال" style="border-color:#dc3545;color:#dc3545;">
                <i class="fa fa-exclamation-triangle" style="color:#dc3545;"></i>
                <span class="note-badge" style="background:#dc3545;"><?= $_fup_badge ?></span>
              </button>
              <?php else: ?>
              <i class="fa fa-check-circle" style="color:#059669;font-size:.9rem;" title="لا توجد أعطال"></i>
              <?php endif; ?>
            </td>
            <td>
              <button class="note-btn" onclick="openNotes(<?= intval($row['id']) ?>)">
                <i class="fa fa-comment" <?php if (intval($row['notes_count']) > 0): ?>style="color:#ffaa33;"<?php endif; ?>></i>
                <?php if (intval($row['notes_count']) > 0): ?>
                <span class="note-badge"><?= intval($row['notes_count']) ?></span>
                <?php endif; ?>
              </button>
            </td>
            <td>
              <a class="btn btn-sm btn-outline-primary" target="_blank" href="../Timesheet/timesheet_details.php?id=<?= intval($row['id']) ?>">
                <i class="fa fa-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="table-card">
    <h5 class="card-title"><i class="fa fa-check-circle text-success"></i> سجلات مكتملة الاعتماد النهائي</h5>

    <div class="filters-wrap" style="margin-bottom: 12px;">
      <div class="fg">
        <label>نوع المعدة</label>
        <select id="ff-equipment-type">
          <option value="">الكل</option>
          <option value="1">حفارات</option>
          <option value="2">قلابات</option>
          <option value="3">خرامات</option>
        </select>
      </div>
      <div class="fg">
        <label>فلتر المشروع (الاعتماد النهائي)</label>
        <select id="ff-project">
          <option value="">الكل</option>
          <?php foreach ($filter_projects_final as $v): ?>
          <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>فلتر المورد</label>
        <select id="ff-supplier">
          <option value="">الكل</option>
          <?php foreach ($filter_suppliers_final as $v): ?>
          <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>فلتر السائق / المشغل</label>
        <select id="ff-driver">
          <option value="">الكل</option>
          <?php foreach ($filter_drivers_final as $v): ?>
          <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>فلتر الآلية</label>
        <select id="ff-equip">
          <option value="">الكل</option>
          <?php foreach ($filter_equips_final as $v): ?>
          <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>من تاريخ</label>
        <input type="date" id="ff-date-from">
      </div>
      <div class="fg">
        <label>إلى تاريخ</label>
        <input type="date" id="ff-date-to">
      </div>
      <div class="btns">
        <button type="button" class="btn btn-primary" onclick="applyFinalFilters()"><i class="fa fa-filter"></i> تطبيق</button>
        <button type="button" class="btn btn-outline-secondary" onclick="resetFinalFilters()"><i class="fa fa-rotate-left"></i> إلغاء</button>
      </div>
    </div>

    <div class="table-wrap">
      <table id="tbl-final" class="display nowrap ha-table">
        <thead>
          <tr>
            <th>#</th>
            <th>التاريخ</th>
            <th>المشروع</th>
            <th>المورد</th>
            <th>الآلية</th>
            <th>المشغل</th>
            <th>إجمالي العمل</th>
            <th>المعتمد النهائي</th>
            <th>تاريخ الاعتماد النهائي</th>
            <th>الأعطال</th>
            <th>ملاحظات</th>
            <th>تفاصيل</th>
          </tr>
        </thead>
        <tbody>
        <?php $idx = 1; foreach ($final_rows as $row):
          $_equip = trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''));
        ?>
          <tr data-project="<?= htmlspecialchars($row['project_name'] ?? '') ?>"
              data-supplier="<?= htmlspecialchars($row['supplier_name'] ?? '') ?>"
              data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
              data-equip="<?= htmlspecialchars($_equip) ?>"
              data-type="<?= intval($row['equip_type'] ?? 0) ?>"
              data-date="<?= htmlspecialchars($row['date'] ?? '') ?>"
              data-id="<?= intval($row['id']) ?>">
            <td><?= $idx++ ?></td>
            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['project_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($_equip ?: '—') ?></td>
            <td><?= htmlspecialchars($row['driver_name'] ?? '—') ?></td>
            <td><strong><?= floatval($row['total_work_hours'] ?? 0) ?></strong></td>
            <td><?= htmlspecialchars($row['final_approver'] ?? '—') ?></td>
            <td><?= !empty($row['final_approved_at']) ? date('Y-m-d H:i', strtotime($row['final_approved_at'])) : '—' ?></td>
            <td style="text-align:center;">
              <?php
                $_fin_fc_cnt = intval($fault_counts_map_fup[$row['id']] ?? 0);
                $_fin_leg_has = !empty($row['fault_type']) || !empty($row['fault_part']);
                $_fin_badge = $_fin_fc_cnt > 0 ? $_fin_fc_cnt : ($_fin_leg_has ? 1 : 0);
              ?>
              <?php if ($_fin_badge > 0): ?>
              <button class="note-btn fup-fault-btn" data-ts-id="<?= intval($row['id']) ?>" title="عرض الأعطال" style="border-color:#dc3545;color:#dc3545;">
                <i class="fa fa-exclamation-triangle" style="color:#dc3545;"></i>
                <span class="note-badge" style="background:#dc3545;"><?= $_fin_badge ?></span>
              </button>
              <?php else: ?>
              <i class="fa fa-check-circle" style="color:#059669;font-size:.9rem;" title="لا توجد أعطال"></i>
              <?php endif; ?>
            </td>
            <td>
              <button class="note-btn" onclick="openNotes(<?= intval($row['id']) ?>)">
                <i class="fa fa-comment" <?php if (intval($row['notes_count']) > 0): ?>style="color:#ffaa33;"<?php endif; ?>></i>
                <?php if (intval($row['notes_count']) > 0): ?>
                <span class="note-badge"><?= intval($row['notes_count']) ?></span>
                <?php endif; ?>
              </button>
            </td>
            <td>
              <a class="btn btn-sm btn-outline-primary" target="_blank" href="../Timesheet/timesheet_details.php?id=<?= intval($row['id']) ?>">
                <i class="fa fa-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: عرض الأعطال -->
<div class="modal fade" id="fupFaultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" dir="rtl">
      <div class="modal-header" style="background:linear-gradient(135deg,#7f1d1d,#b91c1c);color:#fff;">
        <h5 class="modal-title fw-bold"><i class="fa fa-exclamation-triangle me-2"></i> تفاصيل الأعطال — التايم شيت #<span id="fup-fault-ts-id">—</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="fupFaultModalBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa fa-comment-dots text-primary"></i> ملاحظات السجل #<span id="modal-ts-id">—</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="notes-list" class="mb-3"></div>
        <hr>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">العمود</label>
            <select class="form-select form-select-sm" id="note-col-select">
              <?php foreach ($column_labels as $col_key => $col_lbl): ?>
              <option value="<?= htmlspecialchars($col_key) ?>" data-label="<?= htmlspecialchars($col_lbl) ?>"><?= htmlspecialchars($col_lbl) ?></option>
              <?php endforeach; ?>
              <option value="other" data-label="أخرى">أخرى</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold">نص الملاحظة</label>
            <textarea id="note-text-input" class="form-control form-control-sm" rows="3" placeholder="اكتب الملاحظة..."></textarea>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button type="button" class="btn btn-primary btn-sm" onclick="submitNote()"><i class="fa fa-save"></i> حفظ</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إغلاق</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script>
var dtFollow = null;
var dtFinal = null;
var currentTsId = 0;

function normalizeDateValue(v) {
  if (!v) return null;
  var d = new Date(v);
  if (isNaN(d.getTime())) return null;
  d.setHours(0,0,0,0);
  return d;
}

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
  var id = settings.nTable.id;
  if (id !== 'tbl-followup' && id !== 'tbl-final') return true;

  var tr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
  if (!tr) return true;

  var $tr = $(tr);
  var p = '';
  var s = '';
  var d = '';
  var e = '';
  var equipType = '';
  var fromVal = '';
  var toVal = '';

  if (id === 'tbl-followup') {
    p = $('#f-project').val();
    s = $('#f-supplier').val();
    d = $('#f-driver').val();
    e = $('#f-equip').val();
    equipType = $('#f-equipment-type').val();
    fromVal = $('#f-date-from').val();
    toVal = $('#f-date-to').val();
  } else if (id === 'tbl-final') {
    p = $('#ff-project').val();
    s = $('#ff-supplier').val();
    d = $('#ff-driver').val();
    e = $('#ff-equip').val();
    equipType = $('#ff-equipment-type').val();
    fromVal = $('#ff-date-from').val();
    toVal = $('#ff-date-to').val();
  }

  if (p && String($tr.data('project') || '') !== p) return false;
  if (s && String($tr.data('supplier') || '') !== s) return false;
  if (d && String($tr.data('driver') || '') !== d) return false;
  if (e && String($tr.data('equip') || '') !== e) return false;
  if (equipType && String($tr.data('type') || '') !== equipType) return false;

  if (fromVal || toVal) {
    var rowDate = normalizeDateValue($tr.data('date'));
    if (!rowDate) return false;
    var fromDate = normalizeDateValue(fromVal);
    var toDate = normalizeDateValue(toVal);
    if (fromDate && rowDate < fromDate) return false;
    if (toDate && rowDate > toDate) return false;
  }

  return true;
});

$(function() {
  var opts = {
    language: { url: '/ems/assets/i18n/datatables/ar.json' },
    responsive: true,
    pageLength: 25,
    order: [[1, 'desc']]
  };

  dtFollow = $('#tbl-followup').DataTable(opts);
  dtFinal = $('#tbl-final').DataTable(opts);

  $('#f-project, #f-supplier, #f-driver, #f-equip, #f-equipment-type, #f-date-from, #f-date-to').on('change', function(){
    applyFollowupFilters();
  });

  $('#ff-project, #ff-supplier, #ff-driver, #ff-equip, #ff-equipment-type, #ff-date-from, #ff-date-to').on('change', function(){
    applyFinalFilters();
  });
});

function applyFollowupFilters() {
  if (dtFollow) dtFollow.draw();
}

function resetFollowupFilters() {
  $('#f-equipment-type').val('');
  $('#f-project').val('');
  $('#f-supplier').val('');
  $('#f-driver').val('');
  $('#f-equip').val('');
  $('#f-date-from').val('');
  $('#f-date-to').val('');
  applyFollowupFilters();
}

function applyFinalFilters() {
  if (dtFinal) dtFinal.draw();
}

function resetFinalFilters() {
  $('#ff-equipment-type').val('');
  $('#ff-project').val('');
  $('#ff-supplier').val('');
  $('#ff-driver').val('');
  $('#ff-equip').val('');
  $('#ff-date-from').val('');
  $('#ff-date-to').val('');
  applyFinalFilters();
}

function openNotes(tsId) {
  currentTsId = tsId;
  $('#modal-ts-id').text(tsId);
  $('#note-text-input').val('');
  $('#notes-list').html('<div class="text-muted text-center py-2"><i class="fa fa-spinner fa-spin"></i> جاري التحميل...</div>');
  var modal = new bootstrap.Modal(document.getElementById('notesModal'));
  modal.show();
  loadNotes(tsId);
}

function loadNotes(tsId) {
  $.ajax({
    url: 'hours_approval_handler.php',
    method: 'POST',
    dataType: 'json',
    data: { action: 'get_notes', timesheet_id: tsId },
    success: function(res) {
      if (!res || !res.success) {
        $('#notes-list').html('<div class="alert alert-danger">تعذر تحميل الملاحظات</div>');
        return;
      }
      if (!res.notes || res.notes.length === 0) {
        $('#notes-list').html('<div class="text-muted text-center py-2">لا توجد ملاحظات بعد</div>');
        return;
      }
      var html = '';
      res.notes.forEach(function(n) {
        html += '<div class="note-item">'
          + '<div style="font-size:.75rem;color:#334155;font-weight:700;margin-bottom:4px;">' + escHtml(n.column_label || '') + ' - ' + escHtml(n.created_by_name || '') + '</div>'
          + '<div style="font-size:.83rem;color:#0f172a;">' + escHtml(n.note_text || '') + '</div>'
          + '<div style="font-size:.72rem;color:#64748b;margin-top:5px;">' + escHtml(n.created_at || '') + '</div>'
          + '</div>';
      });
      $('#notes-list').html(html);
    },
    error: function() {
      $('#notes-list').html('<div class="alert alert-danger">فشل الاتصال بالخادم</div>');
    }
  });
}

function submitNote() {
  var col = $('#note-col-select').val();
  var lbl = $('#note-col-select option:selected').data('label') || col;
  var txt = $.trim($('#note-text-input').val());
  if (!txt) {
    alert('يرجى كتابة الملاحظة');
    return;
  }

  $.ajax({
    url: 'hours_approval_handler.php',
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'add_note',
      timesheet_id: currentTsId,
      column_name: col,
      column_label: lbl,
      note_text: txt
    },
    success: function(res) {
      if (res && res.success) {
        $('#note-text-input').val('');
        loadNotes(currentTsId);
      } else {
        alert((res && res.message) ? res.message : 'تعذر حفظ الملاحظة');
      }
    },
    error: function() {
      alert('فشل الاتصال بالخادم');
    }
  });
}

function escHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// ====== نظام عرض الأعطال ======
$(document).on('click', '.fup-fault-btn', function() {
  var tsId = $(this).data('ts-id');
  if (!tsId) return;
  $('#fup-fault-ts-id').text(tsId);
  $('#fupFaultModalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
  var modal = new bootstrap.Modal(document.getElementById('fupFaultModal'));
  modal.show();

  $.getJSON('../Timesheet/get_timesheet_failures.php?timesheet_id=' + tsId, function(res) {
    if (!res || !res.success || !res.data || res.data.length === 0) {
      $('#fupFaultModalBody').html(
        '<div class="text-center py-5" style="color:#6c757d;">' +
        '<i class="fas fa-check-circle" style="font-size:48px;color:#198754;display:block;margin-bottom:12px;"></i>' +
        '<p style="font-size:15px;font-weight:600;">لا توجد أعطال مصنفة من المنظومة الجديدة</p>' +
        '</div>'
      );
      return;
    }
    var html = '<div style="overflow-x:auto;">';
    html += '<table class="table table-sm table-hover" style="font-size:13px;min-width:650px;">';
    html += '<thead style="background:linear-gradient(135deg,#7f1d1d,#b91c1c);color:#fff;">' +
            '<tr>' +
            '<th style="padding:8px 12px;">#</th>' +
            '<th style="padding:8px 12px;">الكود الكامل</th>' +
            '<th style="padding:8px 12px;">نوع الحدث</th>' +
            '<th style="padding:8px 12px;">الفئة الرئيسية</th>' +
            '<th style="padding:8px 12px;">الفئة الفرعية</th>' +
            '<th style="padding:8px 12px;">تفصيل العطل</th>' +
            '</tr></thead><tbody>';
    res.data.forEach(function(f, i) {
      html += '<tr>' +
              '<td style="padding:7px 12px;color:#6c757d;">' + (i + 1) + '</td>' +
              '<td style="padding:7px 12px;"><span style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">' + escHtml(f.full_code || '—') + '</span></td>' +
              '<td style="padding:7px 12px;">' + escHtml(f.event_type_name || '—') + '</td>' +
              '<td style="padding:7px 12px;">' + escHtml(f.main_category_name || '—') + '</td>' +
              '<td style="padding:7px 12px;">' + escHtml(f.sub_category || '—') + '</td>' +
              '<td style="padding:7px 12px;font-weight:600;">' + escHtml(f.failure_detail || '—') + '</td>' +
              '</tr>';
    });
    html += '</tbody></table></div>';
    $('#fupFaultModalBody').html(html);
  }).fail(function() {
    $('#fupFaultModalBody').html('<div class="alert alert-danger">فشل الاتصال بالخادم</div>');
  });
});
</script>

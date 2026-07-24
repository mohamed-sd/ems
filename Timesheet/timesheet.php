<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
include '../config.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
  header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
  exit();
}

$session_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$ts_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet screen super') : ems_tenant_db();

if (!function_exists('normalize_timesheet_date')) {
  function normalize_timesheet_date($date_str)
  {
    $date_str = trim((string) $date_str);
    if ($date_str === '') {
      return '';
    }

    $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'];
    foreach ($formats as $fmt) {
      $dt = DateTime::createFromFormat($fmt, $date_str);
      if ($dt && $dt->format($fmt) === $date_str) {
        return $dt->format('Y-m-d');
      }
    }

    $ts = strtotime($date_str);
    if ($ts !== false) {
      return date('Y-m-d', $ts);
    }

    return '';
  }
}

if (!function_exists('sanitize_fault_item_text')) {
  function sanitize_fault_item_text($value)
  {
    return trim((string) $value);
  }
}

if (!function_exists('parse_fault_items_json')) {
  function parse_fault_items_json($raw_json)
  {
    $decoded = json_decode((string) $raw_json, true);
    if (!is_array($decoded)) {
      return [];
    }

    $items = [];
    foreach ($decoded as $row) {
      if (!is_array($row)) {
        continue;
      }

      $failure_code_id = isset($row['failure_code_id']) ? intval($row['failure_code_id']) : 0;
      $full_code = sanitize_fault_item_text(isset($row['full_code']) ? $row['full_code'] : '');
      if ($failure_code_id <= 0 && $full_code === '') {
        continue;
      }

      $items[] = [
        'failure_code_id' => $failure_code_id,
        'event_type_code' => sanitize_fault_item_text(isset($row['event_type_code']) ? $row['event_type_code'] : ''),
        'event_type_name' => sanitize_fault_item_text(isset($row['event_type_name']) ? $row['event_type_name'] : ''),
        'main_category_code' => sanitize_fault_item_text(isset($row['main_category_code']) ? $row['main_category_code'] : ''),
        'main_category_name' => sanitize_fault_item_text(isset($row['main_category_name']) ? $row['main_category_name'] : ''),
        'sub_category' => sanitize_fault_item_text(isset($row['sub_category']) ? $row['sub_category'] : ''),
        'failure_detail' => sanitize_fault_item_text(isset($row['failure_detail']) ? $row['failure_detail'] : ''),
        'full_code' => $full_code,
      ];
    }

    return $items;
  }
}

if (!function_exists('enrich_fault_items_from_failure_codes')) {
  // failure_codes جدول مرجعي عالمي (T_GLOBAL) — قراءته عبر البوابة بلا تنطيق شركة
  function enrich_fault_items_from_failure_codes($conn, $equipment_type, $items)
  {
    if (empty($items)) {
      return $items;
    }

    $equipment_type = intval($equipment_type);

    foreach ($items as $i => $item) {
      $failure_code_id = isset($item['failure_code_id']) ? intval($item['failure_code_id']) : 0;
      $full_code = isset($item['full_code']) ? strval($item['full_code']) : '';

      $where = array('status' => 1);
      if ($failure_code_id > 0) {
        $where['id'] = $failure_code_id;
      } elseif ($full_code !== '') {
        $where['full_code'] = $full_code;
      } else {
        continue;
      }
      if ($equipment_type > 0) {
        $where['equipment_type'] = $equipment_type;
      }

      $row = null;
      try {
        $row = ems_tenant_db()->selectOne('failure_codes', array(
          'columns' => array('id', 'event_type_code', 'event_type_name', 'main_category_code', 'main_category_name', 'sub_category', 'failure_detail', 'full_code'),
          'where'   => $where,
        ));
      } catch (\Throwable $t) { error_log('timesheet enrich fault: ' . $t->getMessage()); }
      if ($row) {
        $items[$i]['failure_code_id'] = intval($row['id']);
        $items[$i]['event_type_code'] = $row['event_type_code'];
        $items[$i]['event_type_name'] = $row['event_type_name'];
        $items[$i]['main_category_code'] = $row['main_category_code'];
        $items[$i]['main_category_name'] = $row['main_category_name'];
        $items[$i]['sub_category'] = $row['sub_category'];
        $items[$i]['failure_detail'] = $row['failure_detail'];
        $items[$i]['full_code'] = $row['full_code'];
      }
    }

    return $items;
  }
}

if (!function_exists('save_timesheet_failure_hours')) {
  // كتابة سطور الأعطال عبر البوابة ($gate) — الحذف الصلب لإعادة البناء عبر deleteRow المعلَّل،
  // وcompany_id تحقنه البوابة (لا يُمرَّر يدويًا).
  function save_timesheet_failure_hours($gate, $timesheet_id, $operation_id, $timesheet_date, $equipment_type, $company_id, $user_id, $items)
  {
    $timesheet_id = intval($timesheet_id);
    $operation_id = intval($operation_id);
    $equipment_type = intval($equipment_type);
    $user_id = intval($user_id);
    $timesheet_date = (string) $timesheet_date;

    $equipment_id = 0;
    if ($operation_id > 0) {
      try {
        $eq_row = $gate->selectOne('operations', array('columns' => array('equipment'), 'where' => array('id' => $operation_id)));
        if ($eq_row) { $equipment_id = intval($eq_row['equipment']); }
      } catch (\Throwable $t) { error_log('timesheet save faults op: ' . $t->getMessage()); }
    }

    try {
      $old_rows = $gate->select('timesheet_failure_hours', array('columns' => array('id'), 'where' => array('timesheet_id' => $timesheet_id)));
      foreach ($old_rows as $old_row) {
        $gate->deleteRow('timesheet_failure_hours', intval($old_row['id']), 'rebuild fault lines on timesheet save');
      }
    } catch (\Throwable $t) {
      error_log('timesheet save faults delete: ' . $t->getMessage());
      return false;
    }

    if (empty($items)) {
      return true;
    }

    foreach ($items as $item) {
      $failure_code_id = isset($item['failure_code_id']) ? intval($item['failure_code_id']) : 0;
      if ($failure_code_id <= 0) {
        continue;
      }

      try {
        $gate->insert('timesheet_failure_hours', array(
          'timesheet_id' => $timesheet_id,
          'operation_id' => $operation_id,
          'equipment_id' => $equipment_id,
          'failure_code_id' => $failure_code_id,
          'equipment_type' => $equipment_type,
          'event_type_code' => isset($item['event_type_code']) ? $item['event_type_code'] : '',
          'event_type_name' => isset($item['event_type_name']) ? $item['event_type_name'] : '',
          'main_category_code' => isset($item['main_category_code']) ? $item['main_category_code'] : '',
          'main_category_name' => isset($item['main_category_name']) ? $item['main_category_name'] : '',
          'sub_category' => isset($item['sub_category']) ? $item['sub_category'] : '',
          'failure_detail' => isset($item['failure_detail']) ? $item['failure_detail'] : '',
          'full_code' => isset($item['full_code']) ? $item['full_code'] : '',
          'timesheet_date' => $timesheet_date,
          'created_by' => $user_id,
        ));
      } catch (\Throwable $t) {
        error_log('timesheet save faults insert: ' . $t->getMessage());
        return false;
      }
    }

    return true;
  }
}

// If user doesn't have a project assigned, get the first available project from the company
if ($session_project_id <= 0) {
  if ($is_super_admin) {
    // Super admin can access all projects, so we'll need to handle this differently
    $session_project_id = 0; // Will be handled with a WHERE clause
  } else {
    // Regular user should have a project assigned, try to get one from their company (عبر البوابة)
    $proj = null;
    try {
      $proj = $ts_gate->selectOne('project', array('columns' => array('id'), 'where' => array('status' => 1)));
    } catch (\Throwable $t) { error_log('timesheet project fallback: ' . $t->getMessage()); }
    if ($proj) {
      $session_project_id = intval($proj['id']);
    } else {
      // No projects available for this company
      $session_project_id = 0;
    }
  }
}

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['operator'])) {
  $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
  $posted_fault_items_json = isset($_POST['fault_items_json']) ? $_POST['fault_items_json'] : '[]';

  // Secure form values
  $fields = [
    "operator",
    "employee_id",
    "shift",
    "date",
    "shift_hours",
    "executed_hours",
    "bucket_hours",
    "jackhammer_hours",
    "extra_hours",
    "extra_hours_total",
    "standby_hours",
    "dependence_hours",
    "total_work_hours",
    "work_notes",
    "hr_fault",
    "maintenance_fault",
    "marketing_fault",
    "approval_fault",
    "other_fault_hours",
    "total_fault_hours",
    "fault_notes",
    "start_seconds",
    "start_minutes",
    "start_hours",
    "end_seconds",
    "end_minutes",
    "end_hours",
    "counter_diff",
    "fault_type",
    "fault_department",
    "fault_part",
    "fault_details",
    "general_notes",
    "operator_hours",
    "machine_standby_hours",
    "jackhammer_standby_hours",
    "bucket_standby_hours",
    "extra_operator_hours",
    "operator_standby_hours",
    "operator_notes",
    "tons_count",
    "trips_count",
    "transport_type",
    "meters_type",
    "meters_count",
    "drilling_holes_count",
    "drilling_depth",
    // D02 §3.5 — الحالات الثلاث الناقصة (لكلٍّ حكمٌ تعاقديٌّ مستقل)
    "ts_supplier_stop_hours",
    "ts_planned_stop_hours",
    "ts_force_majeure_hours",
    "type",
    "user_id"
  ];

  // القيم خامًا — البوابة تربطها معاملات مُهيّأة (لا تهريب يدوي بعد الآن)
  $values = [];
  foreach ($fields as $f) {
    $values[$f] = isset($_POST[$f]) ? (string)$_POST[$f] : '';
  }

  // Ensure date is always valid for both storage and edit form input[type=date].
  $normalized_date = normalize_timesheet_date(isset($_POST['date']) ? $_POST['date'] : '');
  if ($normalized_date === '') {
    echo "<script>alert('❌ تنسيق التاريخ غير صحيح');</script>";
    exit;
  }
  $values['date'] = $normalized_date;

  // Parse multiple failure items from hidden JSON; fallback to legacy single fault field.
  $fault_items = parse_fault_items_json($posted_fault_items_json);
  if (empty($fault_items)) {
    $legacy_code = '';
    if (!empty($values['fault_details'])) {
      $parts = explode('|', $values['fault_details']);
      $legacy_code = trim($parts[0]);
    }

    if ($legacy_code !== '') {
      $fault_items[] = [
        'failure_code_id' => 0,
        'event_type_code' => '',
        'event_type_name' => isset($values['fault_type']) ? $values['fault_type'] : '',
        'main_category_code' => '',
        'main_category_name' => isset($values['fault_department']) ? $values['fault_department'] : '',
        'sub_category' => isset($values['fault_part']) ? $values['fault_part'] : '',
        'failure_detail' => trim(str_replace($legacy_code, '', isset($values['fault_details']) ? $values['fault_details'] : ''), " |"),
        'full_code' => $legacy_code,
      ];
    }
  }

  $fault_items = enrich_fault_items_from_failure_codes($conn, isset($values['type']) ? $values['type'] : 0, $fault_items);

  // ✅ التحقق من ساعات الأعطال - يجب أن يساوي مجموع حقول الأعطال مجموع ساعات التعطل
  $total_fault_hours = floatval($values['total_fault_hours']);
  if ($total_fault_hours > 0) {
    $hr_fault = floatval($values['hr_fault']);
    $maintenance_fault = floatval($values['maintenance_fault']);
    $marketing_fault = floatval($values['marketing_fault']);
    $approval_fault = floatval($values['approval_fault']);
    $other_fault_hours = floatval($values['other_fault_hours']);
    // D02 §3.5 — الثلاث الجديدة تدخل الميزان: وإلا رفض الحفظَ كلَّ صفٍّ تُملأ فيه
    $supplier_stop = floatval(isset($values['ts_supplier_stop_hours']) ? $values['ts_supplier_stop_hours'] : 0);
    $planned_stop  = floatval(isset($values['ts_planned_stop_hours']) ? $values['ts_planned_stop_hours'] : 0);
    $force_majeure = floatval(isset($values['ts_force_majeure_hours']) ? $values['ts_force_majeure_hours'] : 0);

    $total_faults_sum = $hr_fault + $maintenance_fault + $marketing_fault + $approval_fault + $other_fault_hours
      + $supplier_stop + $planned_stop + $force_majeure;

    // يجب أن يكون المجموع مساوياً لمجموع ساعات التعطل
    if ($total_faults_sum != $total_fault_hours) {
      echo "<script>alert('❌ خطأ في توزيع ساعات الأعطال!\\n\\nمجموع حقول الأعطال: " . $total_faults_sum . " ساعة\\nمجموع ساعات التعطل: " . $total_fault_hours . " ساعة\\n\\nيجب أن يكون مجموع الحقول التالية مساوياً لمجموع ساعات التعطل:\\n• عطل HR\\n• عطل صيانة\\n• عطل تسويق\\n• عطل اعتماد\\n• ساعات أعطال أخرى\\n• توقف على المورد\\n• توقف مخطط\\n• قوة قاهرة');</script>";
      exit;
    }
  }

  // الكتابة عبر معاملة البوابة المُدارة: سجل الدوام + إعادة بناء سطور الأعطال ذرّيًّا —
  // البوابة تحقن company_id وتفرض ملكية الصف عند التعديل (بديل نطاق EXISTS القديم حرفيًا).
  try {
    $ts_gate->runInTransaction(function ($g) use ($id, $fields, $values, $fault_items) {
      if ($id > 0) {
        // UPDATE (الملكية تفرضها البوابة على WHERE id + company)
        $update_data = array();
        foreach ($fields as $f) { $update_data[$f] = $values[$f]; }
        $g->update('timesheet', $update_data, array('id' => $id));
        $saved_timesheet_id = $id;
      } else {
        // INSERT (company_id تحقنه البوابة تلقائيًا)
        $insert_data = array();
        foreach ($fields as $f) { $insert_data[$f] = $values[$f]; }
        $saved_timesheet_id = intval($g->insert('timesheet', $insert_data));
      }

      $operation_id = isset($values['operator']) ? intval($values['operator']) : 0;
      $equipment_type = isset($values['type']) ? intval($values['type']) : 0;
      $created_by = isset($values['user_id']) ? intval($values['user_id']) : (isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0);
      $saved = save_timesheet_failure_hours(
        $g,
        $saved_timesheet_id,
        $operation_id,
        $values['date'],
        $equipment_type,
        0 /* company تحقنها البوابة */,
        $created_by,
        $fault_items
      );
      if (!$saved) {
        throw new \RuntimeException('فشل حفظ تفاصيل الأعطال المتعددة');
      }
    });

    $type_param = isset($_POST['type']) ? urlencode($_POST['type']) : '1';
    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='timesheet.php?type=" . $type_param . "';</script>";
    exit;
  } catch (\Throwable $t) {
    error_log('timesheet save: ' . $t->getMessage());
    echo "<script>alert('❌ خطأ في الحفظ: " . addslashes($t->getMessage()) . "');</script>";
  }
}

$type = isset($_GET['type']) ? $_GET['type'] : "";
if ($type !== "1" && $type !== "2" && $type !== "3") {
  header("Location: timesheet_type.php");
  exit();
}

$page_title = "إيكوبيشن | التايم شيت اليومي (إدخال الوحدات)";
include("../inheader.php");
include('../insidebar.php');
// تحديد النوع من الرابط (إن وجد)
$type_filter = "";
$type_filter_params = array();
if ($type != "") {
  $type_filter = " AND e.type IN (SELECT id FROM equipments_types WHERE form LIKE ? AND status = 'active') ";
  $type_filter_params[] = $type;
}

// نطاق EXISTS القديم (شركة منشئ المشروع/العميل) استُبدل بحقن company_id عبر {TENANT_SCOPE}

$today_rows = [];
$today_filter_sql = "(DATE(t.date) = CURDATE()"
  . " OR STR_TO_DATE(t.date, '%Y-%m-%d') = CURDATE()"
  . " OR STR_TO_DATE(t.date, '%d-%m-%Y') = CURDATE()"
  . " OR STR_TO_DATE(t.date, '%d/%m/%Y') = CURDATE())";

$type_sql = "";
$today_params = array();
if ($type !== "") {
  $type_sql = " AND t.type = ?";
  $today_params[] = $type;
}

$role6_sql = "";
if (isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === "6") {
  $user_filter = intval($_SESSION['user']['id']);
  $role6_sql = " AND t.user_id = $user_filter";
}

$today_rows_query = "SELECT t.id, t.shift, t.date, t.executed_hours,
                      t.standby_hours, t.total_fault_hours, t.bucket_hours, t.jackhammer_hours,
                      t.extra_hours, t.dependence_hours, t.total_work_hours, t.status,
                      e.code AS eq_code, e.name AS eq_name
                      FROM timesheet t
                      JOIN operations o ON t.operator = o.id
                      JOIN equipments e ON o.equipment = e.id
                      JOIN project p ON o.project_id = p.id
                      JOIN employees d ON t.employee_id = d.id
                      WHERE " . $today_filter_sql . $type_sql . $role6_sql . " AND {TENANT_SCOPE}
                      ORDER BY t.date DESC, t.id DESC";

try {
  $today_rows = $ts_gate->scopedQuery(
    array('scope' => array('t' => 'timesheet', 'o' => 'operations', 'e' => 'equipments', 'p' => 'project', 'd' => 'employees')),
    $today_rows_query, $today_params);
} catch (\Throwable $t) { error_log('timesheet today rows: ' . $t->getMessage()); }
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
  /* Counter Input Group Styling */
  .counter-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    width: 100%;
    max-width: 400px;
  }

  .counter-field {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex: 1;
  }

  .counter-field input {
    width: 100%;
    padding: 8px 6px;
    text-align: center;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    color: #0c1c3e;
    background: white;
    transition: all 0.3s ease;
  }

  .counter-field input:focus {
    outline: none;
    border-color: var(--gold, #e8b800);
    box-shadow: 0 0 0 3px rgba(232, 184, 0, 0.1);
  }

  .counter-field span {
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
    white-space: nowrap;
  }

  .counter-separator {
    font-size: 20px;
    font-weight: 700;
    color: var(--navy, #0c1c3e);
    margin: 0 4px;
    padding-bottom: 18px;
  }

  /* Remove spinner arrows for counter inputs */
  .counter-field input[type="number"]::-webkit-inner-spin-button,
  .counter-field input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  .counter-field input[type="number"] {
    -moz-appearance: textfield;
  }
</style>

<div class="main timesheet-entry-page ems-unified-page-shell">

  <?php
  // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
  $header_title   = 'التايم شيت اليومي (إدخال الوحدات)';
  $header_icon    = 'fas fa-clock';
  $header_actions = array(
      array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة ساعات عمل جديدة'),
      array('href' => 'view_timesheet.php?type=' . urlencode($type), 'class' => 'back-btn ts-view-link', 'icon' => 'fas fa-table', 'label' => 'شاشة العرض الكاملة'),
  );
  // ── نظام Excel الموحّد (Unified Excel Framework) ──
  require_once __DIR__ . '/../includes/excel_ui.php';
  foreach (ems_excel_header_actions('timesheet', 'ساعات العمل', true) as $__xlAction) { $header_actions[] = $__xlAction; }
  $header_back = array('href' => 'timesheet_type.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
  include('../includes/page_header.php');
  ?>

  <form id="projectForm" action="" method="post" class="allforms">
    <?php if ($_GET['type'] == "1") {
      // نوع المعدة كان حفار
      ?>
      <div class="card">
        <div class="card-header">
          <h5><i class="fas fa-edit"></i> إضافة / تعديل حفار</h5>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div>
              <label>الوردية</label>
              <select name="shift" id="shift" required>
                <option value="">-- اختر الوردية --</option>
                <option value="D">☀️ صباحية</option>
                <option value="N">🌙 مسائية</option>
              </select>
            </div>
            <div>
              <label>الالية</label>
              <select name="operator" id="operator" required>
                <option value="">-- اختر الالية --</option>
                <?php
                $project_filter = "";
                if ($session_project_id > 0) {
                  $project_filter = " AND o.project_id = '" . $session_project_id . "'";
                }
                $op_res = array();
                try {
                  $op_res = $ts_gate->scopedQuery(
                    array('scope' => array('o' => 'operations', 'e' => 'equipments', 'p' => 'project')),
                    "SELECT o.id, o.status, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o.project_id = p.id
                                            WHERE 1 $type_filter AND o.status = '1' AND o.equipment_category = 'أساسي'" . $project_filter . " AND {TENANT_SCOPE}", $type_filter_params);
                } catch (\Throwable $t) {
                  error_log('Timesheet operators query failed (type=1): ' . $t->getMessage());
                }
                foreach ($op_res as $op) {
                  echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
                }
                ?>
              </select>
            </div>
            <input type="hidden" name="id" id="timesheet_id" value="">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user']['id']; ?>">

            <div>
              <label>السائق</label>
              <select id="driver" name="employee_id">
                <option value="">-- اختر السائق --</option>
              </select>
            </div>
            <div>
              <label for="date"> التاريخ </label>
              <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" required />
            </div>

            <!-- ********************************************************** -->

            <div>
              <label>ساعات الوردية</label>
              <input type="number" name="shift_hours" id="shift_hours" value="0">
            </div>


            <div>
              <label> ⏱️ عداد البداية</label>
              <div class="counter-input-group">
                <div class="counter-field">
                  <input type="number" value="0" id="start_hours" name="start_hours" placeholder="00">
                  <span>ساعات</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="start_minutes" name="start_minutes" min="0" max="59" placeholder="00"
                    required>
                  <span>دقائق</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="start_seconds" name="start_seconds" min="0" max="59" placeholder="00"
                    required>
                  <span>ثواني</span>
                </div>
              </div>
            </div>

            <!-- ══ الكمية المفوترة — تظهر بوحدة عقد هذا التشغيل (D02 §2.6) ══
                 الساعات تُسجَّل دائمًا للتشغيل والصيانة والمشغّل؛ أما ما يُفوتر
                 فتحدّده وحدةُ العقد لا نوعُ المعدة. فمعدةٌ من النوع 1 على عقدٍ
                 يفوتر بالمتر كانت بلا خانةٍ تسجّل فيها أمتارها — فلا تُسعَّر. -->
            <!-- ⚠️ display:none !important إلزامي: ems-forms.css يفرض
                 `.form-grid > div { display:block !important }`، فتخسر أمامه
                 القيمةُ السطرية العادية وكذلك jQuery .hide()/.show(). ولهذا
                 يبدّل الجافاسكربت العرضَ بـsetProperty(...,'important'). -->
            <div id="billing_qty_block" style="grid-column:1/-1; display:none !important; margin:16px 0 8px;
                 border:2px solid #d4a017; border-radius:10px; padding:14px 16px; background:#fffdf5;">
              <h3 style="text-align:right; color:#8a6d00; margin:0 0 10px; font-weight:700; font-size:1rem;">
                <i class="fas fa-file-invoice-dollar"></i> الكمية المفوترة —
                <span id="billing_unit_label" style="color:#b8860b;"></span>
              </h3>
              <div id="billing_qty_fields" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                <div id="billing_field_meter" style="display:none;">
                  <label>عدد الأمتار المنفذة <span style="color:#c00;">*</span></label>
                  <input type="number" step="0.01" min="0" name="meters_count" id="billing_meters_count" value="0">
                </div>
                <div id="billing_field_ton" style="display:none;">
                  <label>عدد الأطنان المنفذة <span style="color:#c00;">*</span></label>
                  <input type="number" step="0.01" min="0" name="tons_count" id="billing_tons_count" value="0">
                </div>
              </div>
              <p id="billing_hint" style="margin:10px 0 0; font-size:.86rem; color:#8a6d00;"></p>
            </div>

            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              ساعات العمل </h3>

            <div>
              <label>الساعات المنفذة (محسوبة تلقائياً)</label>
              <input type="number" name="executed_hours" id="executed_hours" value="0" readonly
                style="background-color: #f0f0f0; cursor: not-allowed;">
            </div>
            <div>
              <label>ساعات جردل</label>
              <input type="number" name="bucket_hours" id="bucket_hours" value="0">
            </div>
            <div>
              <label>ساعات جاك همر</label>
              <input type="number" name="jackhammer_hours" id="jackhammer_hours" value="0">
            </div>
            <div>
              <label>ساعات إضافية</label>
              <input type="number" name="extra_hours" id="extra_hours" value="0">
            </div>
            <div>
              <label>مجموع الساعات الإضافية</label>
              <input type="number" name="extra_hours_total" id="extra_hours_total" value="0">
            </div>
            <div>
              <label>ساعات الاستعداد (بسبب العميل)</label>
              <input type="number" name="standby_hours" id="standby_hours" value="0">
            </div>
            <div>
              <label>ساعات الاستعداد ( اعتماد )</label>
              <input type="number" name="dependence_hours" id="dependence_hours" value="0">
            </div>
            <div>
              <label>مجموع ساعات العمل</label>
              <input type="number" name="total_work_hours" id="total_work_hours" value="0" readonly>
            </div>
            <div>
              <label>ملاحظات ساعات العمل</label>
              <textarea name="work_notes"></textarea>
            </div>
            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              ساعات الاعطال </h3>

            <!-- ⚠️ تنبيه مهم للمستخدم -->
            <div
              style="grid-column: 1/-1; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-right: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(255,193,7,0.15);">
              <div style="display: flex; align-items: start; gap: 12px;">
                <div style="font-size: 28px; line-height: 1; margin-top: -3px;">⚠️</div>
                <div style="flex: 1;">
                  <h4 style="margin: 0 0 8px 0; color: #856404; font-size: 1.05rem; font-weight: 700;">⚠️ تنبيه مهم - يرجى
                    القراءة</h4>
                  <p style="margin: 0; color: #856404; font-size: 0.9rem; line-height: 1.6;">
                    <strong>إذا كانت هناك ساعات أعطال (مجموع ساعات التعطل أكبر من صفر)،</strong><br>
                    <strong style="color: #d32f2f;">يجب أن يساوي مجموع الحقول التالية = مجموع ساعات التعطل
                      تماماً:</strong>
                  </p>
                  <ul style="margin: 8px 0 0 0; padding-right: 20px; color: #856404; font-size: 0.85rem;">
                    <li><strong>عطل HR</strong></li>
                    <li><strong>عطل صيانة</strong></li>
                    <li><strong>عطل تسويق</strong></li>
                    <li><strong>عطل اعتماد</strong></li>
                    <li><strong>ساعات أعطال أخرى</strong></li>
                  </ul>
                  <p style="margin: 8px 0 0 0; color: #d32f2f; font-size: 0.85rem; font-weight: 600;">
                    ❌ لن يتم قبول التايم شيت إذا كان المجموع غير مطابق!
                  </p>
                </div>
              </div>
            </div>

            <div>
              <label>عطل HR</label>
              <input type="number" name="hr_fault" id="hr_fault" value="0">
            </div>
            <div>
              <label>عطل صيانة</label>
              <input type="number" name="maintenance_fault" id="maintenance_fault" value="0">
            </div>
            <div>
              <label>عطل تسويق</label>
              <input type="number" name="marketing_fault" id="marketing_fault" value="0">
            </div>
            <div>
              <label>عطل اعتماد</label>
              <input type="number" name="approval_fault" id="approval_fault" value="0">
            </div>
            <div>
              <label>ساعات أعطال أخرى</label>
              <input type="number" name="other_fault_hours" id="other_fault_hours" value="0">
            </div>
            <!-- D02 §3.5 — ثلاثُ حالاتٍ لكلٍّ حكمٌ تعاقديٌّ مستقل؛ كانت تُبتلع
                 في «أعطال أخرى» فتضيع مسؤوليتُها ولا تستطيع سياسةُ الاستحقاق
                 أن تحكم على ما لا تميّزه. -->
            <div>
              <label>توقف على المورد</label>
              <input type="number" step="0.01" min="0" name="ts_supplier_stop_hours" id="ts_supplier_stop_hours" value="0">
            </div>
            <div>
              <label>توقف مخطط (صيانة دورية)</label>
              <input type="number" step="0.01" min="0" name="ts_planned_stop_hours" id="ts_planned_stop_hours" value="0">
            </div>
            <div>
              <label>قوة قاهرة</label>
              <input type="number" step="0.01" min="0" name="ts_force_majeure_hours" id="ts_force_majeure_hours" value="0">
            </div>
            <div>
              <label> مجموع ساعات التعطل</label>
              <input type="number" name="total_fault_hours" id="total_fault_hours" value="0" readonly>
            </div>
            <div>
              <label>ملاحظات ساعات الأعطال</label>
              <textarea name="fault_notes"></textarea>
            </div>


            <div>
              <label> ⏱️ عداد النهاية </label>
              <div class="counter-input-group">
                <div class="counter-field">
                  <input type="number" value="0" id="end_hours" name="end_hours" placeholder="00">
                  <span>ساعات</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="end_minutes" name="end_minutes" min="0" max="59" placeholder="00">
                  <span>دقائق</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="end_seconds" name="end_seconds" min="0" max="59" placeholder="00">
                  <span>ثواني</span>
                </div>
              </div>
            </div>

            <div>
              <label>⚡ فرق العداد</label>
              <input type="text" name="counter_diff" id="counter_diff_display" readonly>
              <input type="hidden" id="counter_diff" />
            </div>
            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              الاعطال </h3>

            <!-- قوائم منسدلة متتالية لنظام الأعطال (فورم الإضافة) -->
            <div
              style="grid-column: 1/-1; background: var(--card-bg, #f8f9fa); border: 1px solid var(--border, #dee2e6); border-radius: 8px; padding: 16px;">
              <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">📋 نوع الحدث</label>
                  <select id="fc_event_type"
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">-- اختر نوع الحدث --</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">🔧 الفئة الرئيسية</label>
                  <select id="fc_main_cat" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر الفئة --</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">⚙️ الجزء / السبب</label>
                  <select id="fc_sub_cat" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر الجزء --</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">📝 تفصيل العطل</label>
                  <select id="fc_detail" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر التفصيل --</option>
                  </select>
                </div>
              </div>
              <div id="fc_code_display_add"
                style="margin-top:10px; padding:8px 12px; background:#e9ecef; border-radius:6px; font-size:0.82rem; color:#495057; display:none;">
                <strong>كود العطل:</strong> <span id="fc_code_text_add"></span>
              </div>
            </div>

            <!-- الحقول المخفية التي تُحفظ في قاعدة البيانات -->
            <input type="hidden" name="fault_type" id="fault_type" />
            <input type="hidden" name="fault_department" id="fault_department" />
            <input type="hidden" name="fault_part" id="fault_part" />
            <input type="hidden" name="fault_details" id="fault_details" />
            <input type="hidden" name="fault_items_json" id="fault_items_json" value="[]" />

            <div style="grid-column: 1/-1; border:1px dashed #ced4da; border-radius:8px; padding:12px; background:#fff;">
              <div
                style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                <strong style="font-size:0.9rem;">الأعطال المضافة لهذا التايم شيت</strong>
                <button type="button" id="addFaultBtn"
                  style="padding:6px 12px; border-radius:6px; border:1px solid #0d6efd; background:#0d6efd; color:#fff;">+
                  إضافة العطل الحالي</button>
              </div>
              <div style="font-size:0.8rem; color:#6c757d; margin-bottom:8px;">ملاحظة: يمكنك إضافة أكثر من عطل في نفس
                اليوم، وسيتم حفظها في جدول ساعات الأعطال للتقارير والصيانة.</div>
              <div style="overflow:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                  <thead>
                    <tr style="background:#f8f9fa;">
                      <th style="padding:6px; border:1px solid #e9ecef;">الكود</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">نوع الحدث</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">الفئة</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">الجزء</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">التفصيل</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">إجراء</th>
                    </tr>
                  </thead>
                  <tbody id="faultsSelectedBody">
                    <tr>
                      <td colspan="6" style="padding:8px; text-align:center; border:1px solid #e9ecef; color:#6c757d;">لا
                        توجد أعطال مضافة بعد</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div style="grid-column: 1/-1;">
              <label>ملاحظات عامة</label>
              <textarea name="general_notes" id="general_notes"></textarea>
            </div>

            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              ساعات عمل المشغل </h3>

            <div>
              <label>⏱️ ساعات عمل المشغل</label>
              <input type="text" name="operator_hours" id="operator_hours" value="0">
            </div>
            <div>
              <label>⚙️ ساعات استعداد الآلية</label>
              <input type="text" name="machine_standby_hours" id="machine_standby_hours" value="0" readonly>
            </div>
            <div>
              <label>⚙️ ساعات استعداد الجاك همر</label>
              <input type="text" name="jackhammer_standby_hours" id="jackhammer_standby_hours" value="0">
            </div>
            <div>
              <label>⚙️ ساعات استعداد الجردل</label>
              <input type="text" name="bucket_standby_hours" id="bucket_standby_hours" value="0">
            </div>
            <div>
              <label>➕ الساعات الإضافية</label>
              <input type="text" name="extra_operator_hours" id="extra_operator_hours" class="form-control" value="0">
            </div>
            <div>
              <label>ðŸ‘· ساعات استعداد المشغل</label>
              <input type="text" name="operator_standby_hours" id="operator_standby_hours" class="form-control" value="0">
            </div>
            <div>
              <label>ðŸ“ ملاحظات المشغل</label>
              <textarea name="operator_notes" id="operator_notes" class="form-control"></textarea>

            </div>

            <input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />

            <button type="submit" style="margin-top: 20px;">
              <i class="fas fa-save"></i> حفظ الساعات
            </button>

          </div>
        </div>
      </div>
    <?php } elseif ($_GET['type'] == "2") {
      // نوع المهدةطلع قلاب
      ?>
      <div class="card">
        <div class="card-header">
          <h5><i class="fas fa-edit"></i> إضافة / تعديل قلاب</h5>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div>
              <label>الوردية</label>
              <select name="shift" id="shift" required>
                <option value="">-- اختر الوردية --</option>
                <option value="D">☀️ صباحية</option>
                <option value="N">🌙 مسائية</option>
              </select>
            </div>
            <div>
              <label>الالية</label>
              <select name="operator" id="operator" required>
                <option value="">-- اختر الالية --</option>
                <?php
                $project_filter = "";
                if ($session_project_id > 0) {
                  $project_filter = " AND o.project_id = '" . $session_project_id . "'";
                }
                $op_res = array();
                try {
                  $op_res = $ts_gate->scopedQuery(
                    array('scope' => array('o' => 'operations', 'e' => 'equipments', 'p' => 'project')),
                    "SELECT o.id, o.status, o.project_id AS project_id, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o.project_id = p.id
                                            WHERE 1 $type_filter AND o.status = '1' AND o.equipment_category = 'أساسي'" . $project_filter . " AND {TENANT_SCOPE}", $type_filter_params);
                } catch (\Throwable $t) {
                  error_log('Timesheet operators query failed (type=2): ' . $t->getMessage());
                }
                foreach ($op_res as $op) {
                  echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
                }
                ?>
              </select>
            </div>

            <input type="hidden" name="id" id="timesheet_id" value="">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user']['id']; ?>">
            <div>
              <label>السائق</label>
              <!-- <select name="employee_id"  required>
            <option value="">-- اختر السائق --</option>
            <?php
            // (كتلة معطَّلة في الواجهة — الاستعلام يبقى منفَّذًا عبر البوابة حفاظًا على البايتات داخل التعليق)
            $dr_res = array();
            try {
              $dr_res = $ts_gate->scopedQuery(array('scope' => array('employees' => 'employees')),
                "SELECT id, name FROM employees WHERE 1=1" . ems_operation_types_in_sql($conn, '') . " AND {TENANT_SCOPE}");
            } catch (\Throwable $t) { error_log('timesheet drivers list: ' . $t->getMessage()); }
            foreach ($dr_res as $dr) {
              echo "<option value='" . $dr['id'] . "'>" . $dr['name'] . "</option>";
            }
            ?>
          </select> -->



              <select id="driver" name="employee_id">
                <option value="">-- اختر السائق --</option>
              </select>


            </div>
            <div>
              <label> التاريخ </label>
              <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" required />
            </div>


            <!-- ********************************************************** -->

            <div>
              <label>ساعات الوردية</label>
              <input type="number" name="shift_hours" id="shift_hours" value="0">
            </div>

            <div>
              <label> ⏱️ عداد البداية</label>
              <div class="counter-input-group">
                <div class="counter-field">
                  <input type="number" value="0" id="start_hours" name="start_hours" placeholder="00">
                  <span>ساعات</span>
                </div>
              </div>
              <input type="hidden" value="0" id="start_minutes" name="start_minutes" min="0" max="59" required>
              <input type="hidden" value="0" id="start_seconds" name="start_seconds" min="0" max="59" required>
            </div>

            <div></div>
            <div></div>
            <div></div>
            <div></div>

            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              الساعات </h3>
            <div>
              <label>الساعات المنفذة</label>
              <input type="number" name="executed_hours" id="executed_hours" value="0">
            </div>


            <input type="hidden" name="bucket_hours" id="bucket_hours" value="0">
            <input type="hidden" name="jackhammer_hours" id="jackhammer_hours" value="0">
            <div>
              <label>ساعات إضافية </label>
              <input type="number" name="extra_hours" id="extra_hours" value="0">
            </div>

            <div>
              <label>مجموع الساعات الإضافية (محسوبة تلقائياً)</label>
              <input type="number" name="extra_hours_total" id="extra_hours_total" value="0" readonly
                style="background-color: #f0f0f0; cursor: not-allowed;">
            </div>
            <div>
              <label>ساعات الاستعداد (بسبب العميل)</label>
              <input type="number" name="standby_hours" id="standby_hours" value="0">
            </div>
            <div>
              <label>ساعات الاستعداد ( اعتماد )</label>
              <input type="number" name="dependence_hours" id="dependence_hours" value="0">
            </div>
            <div>
              <label>مجموع ساعات العمل</label>
              <input type="number" name="total_work_hours" id="total_work_hours" value="0" readonly>
            </div>
            <div>
              <label>ملاحظات ساعات العمل</label>
              <textarea name="work_notes" id="work_notes"></textarea>
            </div>


            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              🚚 الأوزان والنقلات </h3>
            <div>
              <label>🔄 نوع النقل</label>
              <select name="transport_type" id="transport_type">
                <option value="">-- اختر نوع النقل --</option>
                <option value="Waste">Waste (نفايات)</option>
                <option value="Ore">Ore (خام)</option>
              </select>
            </div>
            <div>
              <label>⚖️ وزن القلاب</label>
              <input type="number" step="0.01" name="tons_count" id="tons_count" value="0" placeholder="0.00">
            </div>
            <div>
              <label>🚛 عدد النقلات</label>
              <input type="number" name="trips_count" id="trips_count" value="0" placeholder="0">
            </div>



            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              ساعات الاعطال </h3>

            <!-- ⚠️ تنبيه مهم للمستخدم -->
            <div
              style="grid-column: 1/-1; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-right: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(255,193,7,0.15);">
              <div style="display: flex; align-items: start; gap: 12px;">
                <div style="font-size: 28px; line-height: 1; margin-top: -3px;">⚠️</div>
                <div style="flex: 1;">
                  <h4 style="margin: 0 0 8px 0; color: #856404; font-size: 1.05rem; font-weight: 700;">⚠️ تنبيه مهم - يرجى
                    القراءة</h4>
                  <p style="margin: 0; color: #856404; font-size: 0.9rem; line-height: 1.6;">
                    <strong>إذا كانت هناك ساعات أعطال (مجموع ساعات التعطل أكبر من صفر)،</strong><br>
                    <strong style="color: #d32f2f;">يجب أن يساوي مجموع الحقول التالية = مجموع ساعات التعطل
                      تماماً:</strong>
                  </p>
                  <ul style="margin: 8px 0 0 0; padding-right: 20px; color: #856404; font-size: 0.85rem;">
                    <li><strong>عطل HR</strong></li>
                    <li><strong>عطل صيانة</strong></li>
                    <li><strong>عطل تسويق</strong></li>
                    <li><strong>عطل اعتماد</strong></li>
                    <li><strong>ساعات أعطال أخرى</strong></li>
                  </ul>
                  <p style="margin: 8px 0 0 0; color: #d32f2f; font-size: 0.85rem; font-weight: 600;">
                    ❌ لن يتم قبول التايم شيت إذا كان المجموع غير مطابق!
                  </p>
                </div>
              </div>
            </div>

            <div>
              <label>عطل HR</label>
              <input type="number" name="hr_fault" id="hr_fault" value="0">
            </div>
            <div>
              <label>عطل صيانة</label>
              <input type="number" name="maintenance_fault" id="maintenance_fault" value="0">
            </div>
            <div>
              <label>عطل تسويق</label>
              <input type="number" name="marketing_fault" id="marketing_fault" value="0">
            </div>
            <div>
              <label>عطل اعتماد</label>
              <input type="number" name="approval_fault" id="approval_fault" value="0">
            </div>
            <div>
              <label>ساعات أعطال أخرى</label>
              <input type="number" name="other_fault_hours" id="other_fault_hours" value="0">
            </div>
            <!-- D02 §3.5 — ثلاثُ حالاتٍ لكلٍّ حكمٌ تعاقديٌّ مستقل؛ كانت تُبتلع
                 في «أعطال أخرى» فتضيع مسؤوليتُها ولا تستطيع سياسةُ الاستحقاق
                 أن تحكم على ما لا تميّزه. -->
            <div>
              <label>توقف على المورد</label>
              <input type="number" step="0.01" min="0" name="ts_supplier_stop_hours" id="ts_supplier_stop_hours" value="0">
            </div>
            <div>
              <label>توقف مخطط (صيانة دورية)</label>
              <input type="number" step="0.01" min="0" name="ts_planned_stop_hours" id="ts_planned_stop_hours" value="0">
            </div>
            <div>
              <label>قوة قاهرة</label>
              <input type="number" step="0.01" min="0" name="ts_force_majeure_hours" id="ts_force_majeure_hours" value="0">
            </div>
            <div>
              <label> مجموع ساعات التعطل</label>
              <input type="number" name="total_fault_hours" id="total_fault_hours" value="0" readonly>
            </div>
            <div>
              <label>ملاحظات ساعات الأعطال</label>
              <textarea name="fault_notes" id="fault_notes"></textarea>
            </div>

            <div>
              <label> ⏱️ عداد النهاية </label>
              <div class="counter-input-group">
                <div class="counter-field">
                  <input type="number" value="0" id="end_hours" name="end_hours" placeholder="00">
                  <span>ساعات</span>
                </div>
              </div>
              <input type="hidden" value="0" id="end_minutes" name="end_minutes" min="0" max="59">
              <input type="hidden" value="0" id="end_seconds" name="end_seconds" min="0" max="59">
            </div>

            <div>
              <label>⚡ فرق العداد</label>
              <input type="text" name="counter_diff" id="counter_diff_display" readonly>
              <input type="hidden" id="counter_diff" />
            </div>
            <div></div>


            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              الاعطال </h3>

            <!-- قوائم منسدلة متتالية لنظام الأعطال (فورم التعديل) -->
            <div
              style="grid-column: 1/-1; background: var(--card-bg, #f8f9fa); border: 1px solid var(--border, #dee2e6); border-radius: 8px; padding: 16px;">
              <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">

                <div>
                  <label style="font-size:0.85rem; font-weight:600;">📋 نوع الحدث</label>
                  <select id="fc_event_type_edit"
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">-- اختر نوع الحدث --</option>
                  </select>
                </div>

                <div>
                  <label style="font-size:0.85rem; font-weight:600;">🔧 الفئة الرئيسية</label>
                  <select id="fc_main_cat_edit" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر الفئة --</option>
                  </select>
                </div>

                <div>
                  <label style="font-size:0.85rem; font-weight:600;">⚙️ الجزء / السبب</label>
                  <select id="fc_sub_cat_edit" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر الجزء --</option>
                  </select>
                </div>

                <div>
                  <label style="font-size:0.85rem; font-weight:600;">📝 تفصيل العطل</label>
                  <select id="fc_detail_edit" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر التفصيل --</option>
                  </select>
                </div>

              </div>

              <div id="fc_code_display_edit"
                style="margin-top:10px; padding:8px 12px; background:#e9ecef; border-radius:6px; font-size:0.82rem; color:#495057; display:none;">
                <strong>كود العطل:</strong> <span id="fc_code_text_edit"></span>
              </div>
            </div>

            <!-- الحقول المخفية -->
            <input type="hidden" name="fault_type" id="fault_type" />
            <input type="hidden" name="fault_department" id="fault_department" />
            <input type="hidden" name="fault_part" id="fault_part" />
            <input type="hidden" name="fault_details" id="fault_details" />
            <input type="hidden" name="fault_items_json" id="fault_items_json" value="[]" />

            <div style="grid-column: 1/-1; border:1px dashed #ced4da; border-radius:8px; padding:12px; background:#fff;">
              <div
                style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                <strong style="font-size:0.9rem;">الأعطال المضافة لهذا التايم شيت</strong>
                <button type="button" id="addFaultBtn"
                  style="padding:6px 12px; border-radius:6px; border:1px solid #0d6efd; background:#0d6efd; color:#fff;">+
                  إضافة العطل الحالي</button>
              </div>
              <div style="font-size:0.8rem; color:#6c757d; margin-bottom:8px;">ملاحظة: يمكنك إضافة أكثر من عطل في نفس
                اليوم، وسيتم حفظها في جدول ساعات الأعطال للتقارير والصيانة.</div>
              <div style="overflow:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                  <thead>
                    <tr style="background:#f8f9fa;">
                      <th style="padding:6px; border:1px solid #e9ecef;">الكود</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">نوع الحدث</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">الفئة</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">الجزء</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">التفصيل</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">إجراء</th>
                    </tr>
                  </thead>
                  <tbody id="faultsSelectedBody">
                    <tr>
                      <td colspan="6" style="padding:8px; text-align:center; border:1px solid #e9ecef; color:#6c757d;">لا
                        توجد أعطال مضافة بعد</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div style="grid-column: 1/-1;">
              <label>ملاحظات عامة</label>
              <textarea name="general_notes" id="general_notes"></textarea>
            </div>


            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              ساعات عمل المشغل </h3>



            <div></div>

            <div>
              <label>⏱️ ساعات عمل المشغل</label>
              <input type="text" name="operator_hours" id="operator_hours" value="0">
            </div>
            <div>
              <label>⚙️ ساعات استعداد الآلية</label>
              <input type="text" name="machine_standby_hours" value="0" readonly>
            </div>
            <input type="hidden" name="jackhammer_standby_hours" id="jackhammer_standby_hours" value="0">
            <input type="hidden" name="bucket_standby_hours" id="bucket_standby_hours" value="0">
            <input type="hidden" name="extra_operator_hours" id="extra_operator_hours" class="form-control" value="0">
            <div>
              <label>ðŸ‘· ساعات استعداد المشغل</label>
              <input type="text" name="operator_standby_hours" class="form-control" value="0">
            </div>
            <div>
              <label>ðŸ“ ملاحظات المشغل</label>
              <textarea name="operator_notes" id="operator_notes" class="form-control"></textarea>
            </div>


            <div></div>
            <div></div>

            <input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />

            <button type="submit" style="margin-top: 20px;">
              <i class="fas fa-save"></i> حفظ الساعات
            </button>

          </div>
        </div>
      </div>
    <?php } elseif ($_GET['type'] == "3") {
      // نوع المعدة خرامات (drilling machines)
      ?>
      <div class="card">
        <div class="card-header">
          <h5><i class="fas fa-edit"></i> إضافة / تعديل خرامة</h5>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div>
              <label>الوردية</label>
              <select name="shift" id="shift" required>
                <option value="">-- اختر الوردية --</option>
                <option value="D">☀️ صباحية</option>
                <option value="N">🌙 مسائية</option>
              </select>
            </div>
            <div>
              <label>الالية</label>
              <select name="operator" id="operator" required>
                <option value="">-- اختر الالية --</option>
                <?php
                $project_filter = "";
                if ($session_project_id > 0) {
                  $project_filter = " AND o.project_id = '" . $session_project_id . "'";
                }
                $op_res = array();
                try {
                  $op_res = $ts_gate->scopedQuery(
                    array('scope' => array('o' => 'operations', 'e' => 'equipments', 'p' => 'project')),
                    "SELECT o.id, o.status, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o.project_id = p.id
                                            WHERE 1 $type_filter AND o.status = '1' AND o.equipment_category = 'أساسي'" . $project_filter . " AND {TENANT_SCOPE}", $type_filter_params);
                } catch (\Throwable $t) {
                  error_log('Timesheet operators query failed (type=3): ' . $t->getMessage());
                }
                foreach ($op_res as $op) {
                  echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
                }
                ?>
              </select>
            </div>
            <input type="hidden" name="id" id="timesheet_id" value="">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user']['id']; ?>">

            <div>
              <label>السائق</label>
              <select id="driver" name="employee_id">
                <option value="">-- اختر السائق --</option>
              </select>
            </div>
            <div>
              <label for="date"> التاريخ </label>
              <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" required />
            </div>

            <!-- ********************************************************** -->

            <div>
              <label>ساعات الوردية</label>
              <input type="number" name="shift_hours" id="shift_hours" value="0">
            </div>


            <div>
              <label> ⏱️ عداد البداية</label>
              <div class="counter-input-group">
                <div class="counter-field">
                  <input type="number" value="0" id="start_hours" name="start_hours" placeholder="00">
                  <span>ساعات</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="start_minutes" name="start_minutes" min="0" max="59" placeholder="00"
                    required>
                  <span>دقائق</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="start_seconds" name="start_seconds" min="0" max="59" placeholder="00"
                    required>
                  <span>ثواني</span>
                </div>
              </div>
            </div>

            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              ساعات العمل </h3>

            <div>
              <label>الساعات المنفذة</label>
              <input type="number" name="executed_hours" id="executed_hours" value="0">
            </div>

            <input type="hidden" name="bucket_hours" id="bucket_hours" value="0">

            <input type="hidden" name="jackhammer_hours" id="jackhammer_hours" value="0">

            <div>
              <label>ساعات إضافية</label>
              <input type="number" name="extra_hours" id="extra_hours" value="0">
            </div>
            <div>
              <label>مجموع الساعات الإضافية (محسوبة تلقائياً)</label>
              <input type="number" name="extra_hours_total" id="extra_hours_total" value="0" readonly
                style="background-color: #f0f0f0; cursor: not-allowed;">
            </div>
            <div>
              <label>ساعات الاستعداد (بسبب العميل)</label>
              <input type="number" name="standby_hours" id="standby_hours" value="0">
            </div>
            <div>
              <label>ساعات الاستعداد ( اعتماد )</label>
              <input type="number" name="dependence_hours" id="dependence_hours" value="0">
            </div>
            <div>
              <label>مجموع ساعات العمل</label>
              <input type="number" name="total_work_hours" id="total_work_hours" value="0" readonly>
            </div>
            <div>
              <label>ملاحظات ساعات العمل</label>
              <textarea name="work_notes"></textarea>
            </div>
            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              📏 الأمتار </h3>
            <div>
              <label>🔧 نوع الأمتار</label>
              <select name="meters_type" id="meters_type">
                <option value="">-- اختر نوع الأمتار --</option>
                <option value="أمتار الخام">أمتار الخام</option>
                <option value="أمتار الوست">أمتار الوست</option>
                <option value="امتار اخذ العينات">امتار اخذ العينات</option>
              </select>
            </div>
            <div>
              <label>📐 عدد الأمتار (محسوبة تلقائياً)</label>
              <input type="number" step="0.01" name="meters_count" id="meters_count" value="0" placeholder="0.00" readonly
                style="background-color: #f0f0f0; cursor: not-allowed;">
            </div>
            <div>
              <label>⛏️ عدد الحفر المخرمة</label>
              <input type="number" name="drilling_holes_count" id="drilling_holes_count" value="0" placeholder="0">
            </div>
            <div>
              <label>📊 أعماق الحفر (متر)</label>
              <input type="number" step="0.01" name="drilling_depth" id="drilling_depth" value="0" placeholder="0.00">
            </div>
            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;">
              ساعات الاعطال </h3>

            <!-- ⚠️ تنبيه مهم للمستخدم -->
            <div
              style="grid-column: 1/-1; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-right: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(255,193,7,0.15);">
              <div style="display: flex; align-items: start; gap: 12px;">
                <div style="font-size: 28px; line-height: 1; margin-top: -3px;">⚠️</div>
                <div style="flex: 1;">
                  <h4 style="margin: 0 0 8px 0; color: #856404; font-size: 1.05rem; font-weight: 700;">⚠️ تنبيه مهم - يرجى
                    القراءة</h4>
                  <p style="margin: 0; color: #856404; font-size: 0.9rem; line-height: 1.6;">
                    <strong>إذا كانت هناك ساعات أعطال (مجموع ساعات التعطل أكبر من صفر)،</strong><br>
                    <strong style="color: #d32f2f;">يجب أن يساوي مجموع الحقول التالية = مجموع ساعات التعطل
                      تماماً:</strong>
                  </p>
                  <ul style="margin: 8px 0 0 0; padding-right: 20px; color: #856404; font-size: 0.85rem;">
                    <li><strong>عطل HR</strong></li>
                    <li><strong>عطل صيانة</strong></li>
                    <li><strong>عطل تسويق</strong></li>
                    <li><strong>عطل اعتماد</strong></li>
                    <li><strong>ساعات أعطال أخرى</strong></li>
                  </ul>
                  <p style="margin: 8px 0 0 0; color: #d32f2f; font-size: 0.85rem; font-weight: 600;">
                    ❌ لن يتم قبول التايم شيت إذا كان المجموع غير مطابق!
                  </p>
                </div>
              </div>
            </div>

            <div>
              <label>عطل HR</label>
              <input type="number" name="hr_fault" id="hr_fault" value="0">
            </div>
            <div>
              <label>عطل صيانة</label>
              <input type="number" name="maintenance_fault" id="maintenance_fault" value="0">
            </div>
            <div>
              <label>عطل تسويق</label>
              <input type="number" name="marketing_fault" id="marketing_fault" value="0">
            </div>
            <div>
              <label>عطل اعتماد</label>
              <input type="number" name="approval_fault" id="approval_fault" value="0">
            </div>
            <div>
              <label>ساعات أعطال أخرى</label>
              <input type="number" name="other_fault_hours" id="other_fault_hours" value="0">
            </div>
            <!-- D02 §3.5 — ثلاثُ حالاتٍ لكلٍّ حكمٌ تعاقديٌّ مستقل؛ كانت تُبتلع
                 في «أعطال أخرى» فتضيع مسؤوليتُها ولا تستطيع سياسةُ الاستحقاق
                 أن تحكم على ما لا تميّزه. -->
            <div>
              <label>توقف على المورد</label>
              <input type="number" step="0.01" min="0" name="ts_supplier_stop_hours" id="ts_supplier_stop_hours" value="0">
            </div>
            <div>
              <label>توقف مخطط (صيانة دورية)</label>
              <input type="number" step="0.01" min="0" name="ts_planned_stop_hours" id="ts_planned_stop_hours" value="0">
            </div>
            <div>
              <label>قوة قاهرة</label>
              <input type="number" step="0.01" min="0" name="ts_force_majeure_hours" id="ts_force_majeure_hours" value="0">
            </div>
            <div>
              <label> مجموع ساعات التعطل</label>
              <input type="number" name="total_fault_hours" id="total_fault_hours" value="0" readonly>
            </div>
            <div>
              <label>ملاحظات ساعات الأعطال</label>
              <textarea name="fault_notes"></textarea>
            </div>


            <div>
              <label> ⏱️ عداد النهاية </label>
              <div class="counter-input-group">
                <div class="counter-field">
                  <input type="number" value="0" id="end_hours" name="end_hours" placeholder="00">
                  <span>ساعات</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="end_minutes" name="end_minutes" min="0" max="59" placeholder="00">
                  <span>دقائق</span>
                </div>
                <span class="counter-separator">:</span>
                <div class="counter-field">
                  <input type="number" value="0" id="end_seconds" name="end_seconds" min="0" max="59" placeholder="00">
                  <span>ثواني</span>
                </div>
              </div>
            </div>

            <div>
              <label>⚡ فرق العداد</label>
              <input type="text" name="counter_diff" id="counter_diff_display" readonly>
              <input type="hidden" id="counter_diff" />
            </div>
            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              الاعطال </h3>

            <!-- قوائم منسدلة متتالية لنظام الأعطال (فورم الإضافة 2) -->
            <div
              style="grid-column: 1/-1; background: var(--card-bg, #f8f9fa); border: 1px solid var(--border, #dee2e6); border-radius: 8px; padding: 16px;">
              <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">📋 نوع الحدث</label>
                  <select id="fc_event_type_f3"
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">-- اختر نوع الحدث --</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">🔧 الفئة الرئيسية</label>
                  <select id="fc_main_cat_f3" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر الفئة --</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">⚙️ الجزء / السبب</label>
                  <select id="fc_sub_cat_f3" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر الجزء --</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:0.85rem; font-weight:600;">📝 تفصيل العطل</label>
                  <select id="fc_detail_f3" disabled
                    style="width:100%; padding:6px 10px; border-radius:6px; border:1px solid #ccc; opacity:0.6;">
                    <option value="">-- اختر التفصيل --</option>
                  </select>
                </div>
              </div>
              <div id="fc_code_display_f3"
                style="margin-top:10px; padding:8px 12px; background:#e9ecef; border-radius:6px; font-size:0.82rem; color:#495057; display:none;">
                <strong>كود العطل:</strong> <span id="fc_code_text_f3"></span>
              </div>
            </div>

            <!-- الحقول المخفية -->
            <input type="hidden" name="fault_type" id="fault_type" />
            <input type="hidden" name="fault_department" id="fault_department" />
            <input type="hidden" name="fault_part" id="fault_part" />
            <input type="hidden" name="fault_details" id="fault_details" />
            <input type="hidden" name="fault_items_json" id="fault_items_json" value="[]" />

            <div style="grid-column: 1/-1; border:1px dashed #ced4da; border-radius:8px; padding:12px; background:#fff;">
              <div
                style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                <strong style="font-size:0.9rem;">الأعطال المضافة لهذا التايم شيت</strong>
                <button type="button" id="addFaultBtn"
                  style="padding:6px 12px; border-radius:6px; border:1px solid #0d6efd; background:#0d6efd; color:#fff;">+
                  إضافة العطل الحالي</button>
              </div>
              <div style="font-size:0.8rem; color:#6c757d; margin-bottom:8px;">ملاحظة: يمكنك إضافة أكثر من عطل في نفس
                اليوم، وسيتم حفظها في جدول ساعات الأعطال للتقارير والصيانة.</div>
              <div style="overflow:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                  <thead>
                    <tr style="background:#f8f9fa;">
                      <th style="padding:6px; border:1px solid #e9ecef;">الكود</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">نوع الحدث</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">الفئة</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">الجزء</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">التفصيل</th>
                      <th style="padding:6px; border:1px solid #e9ecef;">إجراء</th>
                    </tr>
                  </thead>
                  <tbody id="faultsSelectedBody">
                    <tr>
                      <td colspan="6" style="padding:8px; text-align:center; border:1px solid #e9ecef; color:#6c757d;">لا
                        توجد أعطال مضافة بعد</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div style="grid-column: 1/-1;">
              <label>ملاحظات عامة</label>
              <textarea name="general_notes" id="general_notes"></textarea>
            </div>

            <h3
              style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\">
              ساعات عمل المشغل </h3>

            <div>
              <label>⏱️ ساعات عمل المشغل</label>
              <input type="text" name="operator_hours" id="operator_hours" value="0">
            </div>
            <div>
              <label>⚙️ ساعات استعداد الآلية</label>
              <input type="text" name="machine_standby_hours" id="machine_standby_hours" value="0" readonly>
            </div>
            <div>
              <label>⚙️ ساعات استعداد الجاك همر</label>
              <input type="text" name="jackhammer_standby_hours" id="jackhammer_standby_hours" value="0">
            </div>
            <div>
              <label>⚙️ ساعات استعداد الجردل</label>
              <input type="text" name="bucket_standby_hours" id="bucket_standby_hours" value="0">
            </div>
            <div>
              <label>➕ الساعات الإضافية</label>
              <input type="text" name="extra_operator_hours" id="extra_operator_hours" class="form-control" value="0">
            </div>
            <div>
              <label>ðŸ'· ساعات استعداد المشغل</label>
              <input type="text" name="operator_standby_hours" id="operator_standby_hours" class="form-control" value="0">
            </div>
            <div>
              <label>ðŸ" ملاحظات المشغل</label>
              <textarea name="operator_notes" id="operator_notes" class="form-control"></textarea>
            </div>


            <div></div>

            <input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />

            <button type="submit" style="margin-top: 20px;">
              <i class="fas fa-save"></i> حفظ الساعات
            </button>

          </div>
        </div>
      </div>
    <?php } ?>
  </form>
  <div class="card">
    <div style="padding: 5px;background: var(--card-bg, #f8f9fa); border-bottom: 1px solid var(--border, #dee2e6);">
      <h5><i class="fas fa-list-alt"></i> قائمة ساعات العمل</h5>
    </div>
    <div class="card-body table-container">
      <table id="projectsTable" class="display">
        <thead>
          <tr>
            <th><i class="fas fa-hashtag"></i> #</th>
            <th><i class="fas fa-id-badge"></i> ID</th>
            <th><i class="fas fa-tool"></i> المعدة</th>
            <th><i class="fas fa-calendar"></i> التاريخ</th>
            <th><i class="fas fa-sun"></i> الوردية</th>
            <th><i class="fas fa-hourglass"></i> الساعات المنفذة</th>
            <th><i class="fas fa-cube"></i> الجردل</th>
            <th><i class="fas fa-gavel"></i> الجاكهمر</th>
            <th><i class="fas fa-plus-circle"></i> الإضافية</th>
            <th><i class="fas fa-pause"></i> الاستعداد</th>
            <th><i class="fas fa-wrench"></i> الأعطال</th>
            <th><i class="fas fa-briefcase"></i> ساعات العمل</th>
            <th><i class="fas fa-chart-bar"></i> الإجمالي</th>
            <th><i class="fas fa-toggle-on"></i> الحالة</th>
            <th><i class="fas fa-cogs"></i> إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php $row_index = 1;
          foreach ($today_rows as $row) {
            $totalwork = floatval($row['standby_hours']) + floatval($row['bucket_hours']) + floatval($row['jackhammer_hours']) + floatval($row['extra_hours']) + floatval($row['dependence_hours']);
            $totalall = floatval($row['total_work_hours']) + floatval($row['total_fault_hours']);

            if ($row['status'] == "1") {
              $status = "<font color='grey'>تحت المراجعة</font>";
            } elseif ($row['status'] == "2") {
              $status = "<font color='green'>تم الاعتماد</font>";
            } elseif ($row['status'] == "3") {
              $status = "<font color='red'>تم الرفض</font>";
            } else {
              $status = "غير معروف";
            }

            $shiftBadge = $row['shift'] == "D"
              ? "<span style='background: #ffeaa7; padding: 4px 12px; border-radius: 15px; font-weight: 600; color: #2d3436;'><i class='fas fa-sun'></i> صباحية</span>"
              : "<span style='background: #2d3436; padding: 4px 12px; border-radius: 15px; font-weight: 600; color: #fff;'><i class='fas fa-moon'></i> مسائية</span>";

            $id = intval($row['id']);
            echo "<tr>";
            echo "<td><span style='font-weight: 600;'>" . $row_index . "</span></td>";
            echo "<td><span style='font-weight: 700; color: #1f2937;'>" . $id . "</span></td>";
            echo "<td><span style='font-weight: 600; color: #2980b9;'>" . htmlspecialchars($row['eq_code'], ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($row['eq_name'], ENT_QUOTES, 'UTF-8') . "</span></td>";
            echo "<td>" . htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . $shiftBadge . "</td>";
            echo "<td><span style='background: #e8f5e9; font-weight: 600; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . floatval($row['executed_hours']) . "</span></td>";
            echo "<td><span style='background: #e8f5e9; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . floatval($row['bucket_hours']) . "</span></td>";
            echo "<td><span style='background: #e8f5e9; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . floatval($row['jackhammer_hours']) . "</span></td>";
            echo "<td><span style='background: #e8f5e9; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . floatval($row['extra_hours']) . "</span></td>";
            echo "<td><span style='background: #fff3e0; font-weight: 600; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . floatval($row['standby_hours']) . "</span></td>";
            echo "<td><span style='background: #fff3e0; font-weight: 600; color: #d63031; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . floatval($row['total_fault_hours']) . "</span></td>";
            echo "<td><span style='background: #e3f2fd; font-weight: 700; color: #2980b9; font-size: 1.05rem; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . $totalwork . "</span></td>";
            echo "<td><span style='background: #ffebee; font-weight: 700; color: #c0392b; font-size: 1.05rem; padding: 4px 8px; display: inline-block; border-radius: 4px;'>" . $totalall . "</span></td>";
            echo "<td><div style='text-align: center;'>" . $status . "</div></td>";
            echo "<td><div style='white-space: nowrap; text-align: center;'>"
              . "<a href='javascript:void(0)' class='editBtn' data-id='" . $id . "' title='تعديل' style='color:#3498db; font-size: 1.1rem; margin: 0 3px;'><i class='fas fa-edit'></i></a>"
              . "<a href='delete_timesheet.php?id=" . $id . "' onclick='return confirm(\"هل أنت متأكد؟\")' title='حذف' style='color: #e74c3c; font-size: 1.1rem; margin: 0 3px;'><i class='fas fa-trash'></i></a>"
              . "<a href='timesheet_details.php?id=" . $id . "' title='عرض التفاصيل' style='color: #8e44ad; font-size: 1.1rem; margin: 0 3px;'><i class='fas fa-eye'></i></a>"
              . "</div></td>";
            echo "</tr>";

            $row_index++;
          }
          ?>
        </tbody>
      </table>

    </div>
  </div>
</div>

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
  $(document).ready(function () {
    var table = $('#projectsTable').DataTable({
      processing: true,
      order: [[3, 'desc']], // Sort by date descending by default
      pageLength: 10,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
      scrollX: true,
      fixedHeader: true,
      dom: 'Blfrtip', // Buttons + Length + Search + Table + Info + Pagination
      buttons: [
        { extend: 'copy', text: 'نسخ' },
        { extend: 'excel', text: 'تصدير Excel' },
        { extend: 'csv', text: 'تصدير CSV' },
        { extend: 'pdf', text: 'تصدير PDF' },
        { extend: 'print', text: 'طباعة' }
      ],
      language: {
        url: "/ems/assets/i18n/datatables/ar.json",
        processing: '<i class="fas fa-spinner fa-spin fa-3x"></i><br>جاري التحميل...'
      }
    });

    // Update table when sidebar toggles
    const sidebarToggle = document.getElementById('toggleBtn');
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function () {
        setTimeout(function () {
          table.columns.adjust().draw();
        }, 400);
      });
    }

    // Toggle form visibility
    const toggleFormBtn = document.getElementById('toggleForm');
    const form = document.getElementById('projectForm');

    console.log('Toggle button:', toggleFormBtn);
    console.log('Form element:', form);

    if (toggleFormBtn && form) {
      toggleFormBtn.addEventListener('click', function (e) {
        e.preventDefault();
        console.log('Toggle clicked, current state:', form.classList.contains('allforms-visible'));
        form.classList.toggle('allforms-visible');
      });
    } else {
      console.error('Toggle button or form not found!');
    }
  });

  function loadMachineData() {
    let id = document.getElementById("cost_code").value;
    if (id === "") return;
    fetch("get_machine.php?id=" + id)
      .then(res => res.json())
      .then(data => {
        if (data) {
          document.querySelector("input[name='shift_hours']").value = data.hours / 2 || "";
          document.querySelector("input[name='machine_name']").value = data.plant_no || "";
          document.querySelector("input[name='project_name']").value = data.project_name || "";
          document.querySelector("input[name='owner_name']").value = data.owner || "";
        }
      })
      .catch(err => console.error("خطأ في جلب البيانات:", err));
  }

  // ✅ التحقق من ساعات الأعطال قبل إرسال النموذج
  const projectForm = document.getElementById('projectForm');
  if (projectForm) {
    projectForm.addEventListener('submit', function (e) {
      const totalFaultHours = parseFloat(document.querySelector("input[name='total_fault_hours']").value) || 0;

      if (totalFaultHours > 0) {
        const hrFault = parseFloat(document.querySelector("input[name='hr_fault']").value) || 0;
        const maintenanceFault = parseFloat(document.querySelector("input[name='maintenance_fault']").value) || 0;
        const marketingFault = parseFloat(document.querySelector("input[name='marketing_fault']").value) || 0;
        const approvalFault = parseFloat(document.querySelector("input[name='approval_fault']").value) || 0;
        const otherFaultHours = parseFloat(document.querySelector("input[name='other_fault_hours']").value) || 0;
        // D02 §3.5 — الثلاث الجديدة تدخل الميزان تمامًا كما في التحقق الخادمي
        const num = function (n) { var el = document.querySelector("input[name='" + n + "']"); return el ? (parseFloat(el.value) || 0) : 0; };
        const supplierStop = num('ts_supplier_stop_hours');
        const plannedStop  = num('ts_planned_stop_hours');
        const forceMajeure = num('ts_force_majeure_hours');

        const totalFaultsSum = hrFault + maintenanceFault + marketingFault + approvalFault + otherFaultHours
          + supplierStop + plannedStop + forceMajeure;

        // يجب أن يكون المجموع مساوياً لمجموع ساعات التعطل
        if (totalFaultsSum !== totalFaultHours) {
          e.preventDefault(); // منع إرسال النموذج
          alert('❌ خطأ في توزيع ساعات الأعطال!\n\n' +
            'مجموع حقول الأعطال: ' + totalFaultsSum.toFixed(2) + ' ساعة\n' +
            'مجموع ساعات التعطل: ' + totalFaultHours.toFixed(2) + ' ساعة\n\n' +
            'يجب أن يكون مجموع الحقول التالية مساوياً لمجموع ساعات التعطل:\n' +
            '• عطل HR\n' +
            '• عطل صيانة\n' +
            '• عطل تسويق\n' +
            '• عطل اعتماد\n' +
            '• ساعات أعطال أخرى\n' +
            '• توقف على المورد\n' +
            '• توقف مخطط\n' +
            '• قوة قاهرة');

          // التمرير إلى قسم الأعطال
          const faultsSection = document.querySelector("input[name='hr_fault']");
          if (faultsSection) {
            faultsSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => faultsSection.focus(), 500);
          }
          return false;
        }
      }
    });
  }

  document.querySelectorAll("#start_minutes, #start_seconds, #end_minutes, #end_seconds")
    .forEach(inp => {
      inp.addEventListener("input", function () {
        let max = 59, min = 0;
        if (this.value > max) this.value = max;
        if (this.value < min) this.value = min;
      });
    });


  // ✅ دالة لحساب العمليات الثلاثة
  function calculateCustomHours() {
    var machineType = "<?php echo $_GET['type']; ?>";
    let executed = 0;

    // حساب الساعات المنفذة تلقائياً فقط للحفارات (type=1)
    if (machineType === "1") {
      let bucketHours = parseFloat(document.querySelector("input[name='bucket_hours']").value) || 0;
      let jackhammer = parseFloat(document.querySelector("input[name='jackhammer_hours']").value) || 0;
      let extraHours = parseFloat(document.querySelector("input[name='extra_hours']").value) || 0;
      let standby = parseFloat(document.querySelector("input[name='standby_hours']").value) || 0;
      let dependence = parseFloat(document.querySelector("input[name='dependence_hours']").value) || 0;

      // الساعات المنفذة = ساعات الجردل + ساعات الجاك همر + الساعات الإضافية + ساعات الاستعداد (بسبب العميل) + ساعات الاستعداد (اعتماد)
      executed = bucketHours + jackhammer + extraHours + standby + dependence;
      document.querySelector("input[name='executed_hours']").value = executed;
    } else {
      // بالنسبة للقلابات (type=2) والخرامات (type=3)، نأخذ القيمة المدخلة يدوياً
      executed = parseFloat(document.querySelector("input[name='executed_hours']").value) || 0;
    }

    // ✅ نسخ قيمة ساعات إضافية إلى مجموع الساعات الإضافية تلقائياً
    let extraHours = parseFloat(document.querySelector("input[name='extra_hours']").value) || 0;
    document.querySelector("input[name='extra_hours_total']").value = extraHours;
    let extraTotal = extraHours;
    let shift = parseFloat(document.querySelector("input[name='shift_hours']").value) || 0;
    let maintenance = parseFloat(document.querySelector("input[name='maintenance_fault']").value) || 0;
    let marketing = parseFloat(document.querySelector("input[name='marketing_fault']").value) || 0;
    let standby = parseFloat(document.querySelector("input[name='standby_hours']").value) || 0;
    let dependence = parseFloat(document.querySelector("input[name='dependence_hours']").value) || 0;

    // العملية الأولى: مجموع ساعات العمل
    let totalWork;
    if (machineType === "1") {
      // للحفارات فقط: executed يتضمن standby و dependence
      totalWork = executed + extraTotal;
    } else {
      // للقلابات والخرامات: executed منفصل عن standby
      totalWork = executed + extraTotal + standby;
    }
    document.querySelector("input[name='total_work_hours']").value = totalWork;

    // العملية الثانية: ساعات أعطال أخرى
    let otherFault;
    if (machineType === "1") {
      // للحفارات فقط: executed يتضمن standby و dependence
      otherFault = shift - executed;
    } else {
      // للقلابات والخرامات
      otherFault = shift - executed - standby - dependence;
    }
    if (otherFault < 0) otherFault = 0;
    document.querySelector("input[name='total_fault_hours']").value = otherFault;

    // العملية الثالثة: ساعات استعداد المشغل
    let operatorStandby = 0;
    if (executed < shift) {
      operatorStandby = maintenance + marketing + dependence;
    }
    document.querySelector("input[name='operator_standby_hours']").value = operatorStandby;

    // اسناد قيمة استعدات الاليه
    document.querySelector("input[name='machine_standby_hours']").value = standby;
  }

  // شغل الحساب عند أي تغيير في الحقول
  document.querySelectorAll("input[name='executed_hours'], input[name='bucket_hours'], input[name='jackhammer_hours'], input[name='extra_hours'], input[name='standby_hours'], input[name='shift_hours'], input[name='maintenance_fault'], input[name='marketing_fault'] , input[name='dependence_hours'] , input[name='machine_standby_hours']  ")
    .forEach(el => el.addEventListener("input", calculateCustomHours));

  // ✅ استدعاء أول مرة
  calculateCustomHours();

  // ✅ دالة حساب عدد الأمتار للخرامات (type=3)
  var machineType = "<?php echo $_GET['type']; ?>";
  if (machineType === "3") {
    function calculateMetersCount() {
      let holesCount = parseFloat(document.querySelector("input[name='drilling_holes_count']").value) || 0;
      let drillingDepth = parseFloat(document.querySelector("input[name='drilling_depth']").value) || 0;

      // عدد الأمتار = عدد الحفر × عمق الحفر
      let metersCount = holesCount * drillingDepth;
      document.querySelector("input[name='meters_count']").value = metersCount.toFixed(2);
    }

    // ربط الدالة بحقلي عدد الحفر وأعماق الحفر
    document.querySelectorAll("input[name='drilling_holes_count'], input[name='drilling_depth']")
      .forEach(el => el.addEventListener("input", calculateMetersCount));

    // استدعاء أول مرة
    calculateMetersCount();
  }

  if (machineType === "1") {
    function calculateDiff() {
      // اجمع البداية
      let start =
        (parseInt(document.getElementById("start_hours").value || 0) * 3600) +
        (parseInt(document.getElementById("start_minutes").value || 0) * 60) +
        (parseInt(document.getElementById("start_seconds").value || 0));

      // اجمع النهاية
      let end =
        (parseInt(document.getElementById("end_hours").value || 0) * 3600) +
        (parseInt(document.getElementById("end_minutes").value || 0) * 60) +
        (parseInt(document.getElementById("end_seconds").value || 0));

      let executed = parseFloat(document.querySelector("input[name='executed_hours']").value) || 0;
      let extraTotal = parseFloat(document.querySelector("input[name='extra_hours_total']").value) || 0;

      let diff = end - start;
      if (diff < 0) diff = 0; // حماية

      // حوّل الفرق إلى ساعات/دقائق/ثواني
      let hours = (executed + extraTotal) - Math.floor(diff / 3600);
      let minutes = Math.floor((diff % 3600) / 60);
      let seconds = diff % 60;

      // عرض الفرق
      document.getElementById("counter_diff_display").value =
        hours + " ساعة " + minutes + " دقيقة " + seconds + " ثانية";

      // حفظ القيمة (بالثواني) للإرسال
      document.getElementById("counter_diff").value = diff;
    }
  } else {
    function calculateDiff() {
      let start = document.getElementById("start_hours").value || 0;
      let end = document.getElementById("end_hours").value || 0;
      document.getElementById("counter_diff_display").value = end - start;
    }
  }
  // شغل الحساب عند أي تغيير
  document.querySelectorAll("#start_hours, #start_minutes, #start_seconds, #end_hours, #end_minutes, #end_seconds")
    .forEach(el => el.addEventListener("input", calculateDiff));

  calculateDiff();




  function applyOperationShiftData(response) {
    if (response && typeof response.shift_hours !== 'undefined') {
      $("#shift_hours").val(response.shift_hours);
    } else {
      $("#shift_hours").val("0");
    }

    applyBillingUnit(response && response.billing ? response.billing : null);
    calculateCustomHours();
  }

  // ══ الكمية المفوترة: وحدةُ العقد تقرّر أي خانةٍ تُعرض (D02 §2.6) ══════════
  // القاعدة: الساعات تُسجَّل دائمًا (تشغيلٌ وصيانةٌ وأجرُ مشغّل)؛ ووحدةُ العقد
  // تحدّد ما يصير مالًا. فإن كان العقد يفوتر بالمتر أو الطن ولم تعرضهما شاشةُ
  // نوع المعدة، أظهرناهما هنا صراحةً — وإلا بقي الصفُّ بلا كميةٍ مفوترة فلا يُسعَّر.
  function applyBillingUnit(billing) {
    var block = document.getElementById("billing_qty_block");
    if (!block) { return; }                    // الأنواع التي تعرض خاناتها أصلًا
    // ⚠️ لا تستعمل jQuery .show()/.hide() هنا: ems-forms.css يفرض
    //    `.form-grid > div { display:block !important }` فيغلب القيمةَ السطرية
    //    العادية. الأولويةُ السطرية وحدها تغلبه.
    var setDisp = function (v) { block.style.setProperty("display", v, "important"); };

    if (!billing || !billing.unit || (billing.unit !== "meter" && billing.unit !== "ton")) {
      // عقدٌ بالساعة (أو مجهول): خانة الساعات المعروضة أصلًا هي وحدة الفوترة
      setDisp("none");
      return;
    }
    $("#billing_field_meter, #billing_field_ton").hide();
    $("#billing_unit_label").text(billing.label || "");
    $("#billing_field_" + billing.unit).show();
    $("#billing_hint").text(
      "عقد هذا التشغيل يفوتر بـ«" + (billing.label || "") + "» — سجّل الكمية المنفذة بهذه الوحدة. "
      + "الساعات تبقى مطلوبةً للتشغيل والصيانة، لكنها ليست وحدة الفوترة هنا.");
    setDisp("block");
  }

  $(document).ready(function () {
    function refreshOperationAndDriverLists() {
      var opId = $("#operator").val();
      var shiftVal = $("#shift").val();

      if (!shiftVal) {
        $("#operator").html("<option value=''>-- اختر الوردية أولاً --</option>");
        $("#driver").html("<option value=''>-- اختر الوردية أولاً --</option>");
        $("#shift_hours").val("0");
        return;
      }

      $.ajax({
        url: "get_operations.php",
        type: "GET",
        data: { type: <?php echo json_encode($type); ?>, shift: shiftVal },
        success: function (response) {
          var currentOperation = opId;
          $("#operator").html(response);
          if (currentOperation) {
            $("#operator").val(currentOperation);
          }
        },
        error: function () {
          $("#operator").html("<option value=''>-- تعذر تحميل الآليات --</option>");
        }
      });

      if (opId) {
        $.ajax({
          url: "get_drivers.php",
          type: "GET",
          data: { operation_id: opId, shift: shiftVal },
          success: function (response) {
            $("#driver").html(response);
          },
          error: function () {
            $("#driver").html("<option value=''>-- تعذر تحميل السائقين --</option>");
          }
        });

        $.ajax({
          url: "get_contract_hours.php",
          type: "GET",
          dataType: "json",
          data: { operation_id: opId },
          success: function (response) {
            applyOperationShiftData(response);
          },
          error: function () {
            applyOperationShiftData({ shift_hours: 0 });
          }
        });
      }
    }

    $("#shift").on("change", function () {
      refreshOperationAndDriverLists();
    });

    $("#operator").change(function () {
      var opId = $(this).val();
      var shiftVal = $("#shift").val();
      if (opId !== "") {
        $.ajax({
          url: "get_drivers.php",
          type: "GET",
          data: { operation_id: opId, shift: shiftVal },
          success: function (response) {
            console.log("تم تحميل السائقين");
            $("#driver").html(response);
          },
          error: function (xhr, status, error) {
            console.error("خطأ في جلب السائقين:", error);
            $("#driver").html("<option value=''>-- اختر السائق --</option>");
          }
        });

        $.ajax({
          url: "get_contract_hours.php",
          type: "GET",
          dataType: "json",
          data: { operation_id: opId },
          success: function (response) {
            console.log("تم تحميل بيانات الوردية من التشغيل:", response);
            applyOperationShiftData(response);
          },
          error: function (xhr, status, error) {
            console.error("خطأ في جلب بيانات الوردية:", error);
            applyOperationShiftData({ shift_hours: 0 });
          }
        });
      } else {
        $("#driver").html("<option value=''>-- اختر السائق --</option>");
        $("#shift_hours").val("0");
      }
    });
  });


  $(document).on("click", ".editBtn", function () {
    var id = $(this).data("id");
    if (!id) return;
    $.getJSON("get_timesheet.php", { id: id }, function (data) {
      if (!data || !data.id) {
        alert("لم أستطع جلب بيانات السجل.");
        return;
      }

      $("#timesheet_id").val(data.id);
      $("#operator").val(data.operator).trigger('change');

      // بعد تحميل بيانات العملية عبر AJAX نضبط السائق والوردية
      setTimeout(function () {
        $("#driver").val(data.employee_id);
        if (data.shift) {
          $("#shift").val(data.shift);
        }
      }, 400);

      $("#date").val(data.date);
      $("#shift_hours").val(data.shift_hours);
      $("#executed_hours").val(data.executed_hours);
      $("#bucket_hours").val(data.bucket_hours);
      $("#jackhammer_hours").val(data.jackhammer_hours);
      $("#extra_hours").val(data.extra_hours);
      $("#extra_hours_total").val(data.extra_hours_total);
      $("#standby_hours").val(data.standby_hours);
      $("#dependence_hours").val(data.dependence_hours);
      $("#total_work_hours").val(data.total_work_hours);
      $("#work_notes").val(data.work_notes);

      $("#hr_fault").val(data.hr_fault);
      $("#maintenance_fault").val(data.maintenance_fault);
      $("#marketing_fault").val(data.marketing_fault);
      $("#approval_fault").val(data.approval_fault);
      $("#other_fault_hours").val(data.other_fault_hours);
      $("#ts_supplier_stop_hours").val(data.ts_supplier_stop_hours || 0);
      $("#ts_planned_stop_hours").val(data.ts_planned_stop_hours || 0);
      $("#ts_force_majeure_hours").val(data.ts_force_majeure_hours || 0);
      $("#total_fault_hours").val(data.total_fault_hours);
      $("#fault_notes").val(data.fault_notes);

      $("#start_hours").val(data.start_hours);
      $("#start_minutes").val(data.start_minutes);
      $("#start_seconds").val(data.start_seconds);
      $("#end_hours").val(data.end_hours);
      $("#end_minutes").val(data.end_minutes);
      $("#end_seconds").val(data.end_seconds);
      $("#counter_diff_display").val(data.counter_diff_display || "");
      $("#counter_diff").val(data.counter_diff || 0);

      $("#fault_type").val(data.fault_type);
      $("#fault_department").val(data.fault_department);
      $("#fault_part").val(data.fault_part);
      $("#fault_details").val(data.fault_details);
      $("#general_notes").val(data.general_notes);

      $("#operator_hours").val(data.operator_hours);
      $("#machine_standby_hours").val(data.machine_standby_hours);
      $("#jackhammer_standby_hours").val(data.jackhammer_standby_hours);
      $("#bucket_standby_hours").val(data.bucket_standby_hours);
      $("#extra_operator_hours").val(data.extra_operator_hours);
      $("#operator_standby_hours").val(data.operator_standby_hours);
      $("#operator_notes").val(data.operator_notes);
      $("#tons_count, #billing_tons_count").val(data.tons_count || 0);
      $("#trips_count").val(data.trips_count || 0);
      $("#transport_type").val(data.transport_type || '');
      $("#meters_type").val(data.meters_type || '');
      $("#meters_count, #billing_meters_count").val(data.meters_count || 0);
      $("#drilling_holes_count").val(data.drilling_holes_count || 0);
      $("#drilling_depth").val(data.drilling_depth || 0);

      if (typeof window.loadTimesheetFaultItems === 'function') {
        window.loadTimesheetFaultItems(data.id);
      }

      $("#projectForm").addClass('allforms-visible');
      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    })
      .fail(function () {
        alert("خطأ في جلب بيانات التايم شيت.");
      });
  });

</script>

<!-- ==================== نظام الأعطال المتتالي ==================== -->
<script>
  (function () {
    // نوع المعدة من PHP (1=حفار, 2=قلاب, 3=خرامة)
    var equipType = parseInt('<?= intval($type) ?>') || 1;
    var faultItems = [];

    var BASE_URL = '<?= (strpos($_SERVER['PHP_SELF'], '/Timesheet/') !== false) ? '' : 'Timesheet/' ?>get_failure_codes.php';

    // ============================
    // دالة مساعدة: ملء القائمة
    // ============================
    function fillSelect(sel, options, placeholder) {
      sel.empty().append($('<option>').val('').text(placeholder)).prop('disabled', false).css('opacity', 1);
      $.each(options, function (i, o) {
        sel.append($('<option>').val(o.value).text(o.label).data('extra', o.extra || ''));
      });
    }
    function resetSelect(sel, placeholder) {
      sel.empty().append($('<option>').val('').text(placeholder)).prop('disabled', true).css('opacity', 0.6);
    }

    function firstExistingSelector(candidates) {
      for (var i = 0; i < candidates.length; i++) {
        if ($(candidates[i]).length) {
          return candidates[i];
        }
      }
      return null;
    }

    function getActiveFaultControls() {
      var selectors = {
        event: firstExistingSelector(['#fc_event_type', '#fc_event_type_edit', '#fc_event_type_f3']),
        main: firstExistingSelector(['#fc_main_cat', '#fc_main_cat_edit', '#fc_main_cat_f3']),
        sub: firstExistingSelector(['#fc_sub_cat', '#fc_sub_cat_edit', '#fc_sub_cat_f3']),
        detail: firstExistingSelector(['#fc_detail', '#fc_detail_edit', '#fc_detail_f3'])
      };

      if (!selectors.event || !selectors.main || !selectors.sub || !selectors.detail) {
        return null;
      }

      return {
        event: $(selectors.event),
        main: $(selectors.main),
        sub: $(selectors.sub),
        detail: $(selectors.detail)
      };
    }

    function syncLegacyFaultFields() {
      if (!faultItems.length) {
        $('#fault_type').val('');
        $('#fault_department').val('');
        $('#fault_part').val('');
        $('#fault_details').val('');
        return;
      }

      var first = faultItems[0];
      $('#fault_type').val(first.event_type_name || '');
      $('#fault_department').val(first.main_category_name || '');
      $('#fault_part').val(first.sub_category || '');
      $('#fault_details').val((first.full_code || '') + ' | ' + (first.failure_detail || ''));
    }

    function renderFaultItemsTable() {
      var body = $('#faultsSelectedBody');
      if (!body.length) {
        return;
      }

      body.empty();
      if (!faultItems.length) {
        body.append('<tr><td colspan="6" style="padding:8px; text-align:center; border:1px solid #e9ecef; color:#6c757d;">لا توجد أعطال مضافة بعد</td></tr>');
      } else {
        $.each(faultItems, function (i, item) {
          var rowHtml = '' +
            '<tr>' +
            '<td style="padding:6px; border:1px solid #e9ecef;">' + (item.full_code || '') + '</td>' +
            '<td style="padding:6px; border:1px solid #e9ecef;">' + (item.event_type_name || '') + '</td>' +
            '<td style="padding:6px; border:1px solid #e9ecef;">' + (item.main_category_name || '') + '</td>' +
            '<td style="padding:6px; border:1px solid #e9ecef;">' + (item.sub_category || '') + '</td>' +
            '<td style="padding:6px; border:1px solid #e9ecef;">' + (item.failure_detail || '') + '</td>' +
            '<td style="padding:6px; border:1px solid #e9ecef; text-align:center;">' +
            '<button type="button" class="removeFaultBtn" data-index="' + i + '" style="padding:4px 8px; border-radius:4px; border:1px solid #dc3545; background:#fff; color:#dc3545;">حذف</button>' +
            '</td>' +
            '</tr>';
          body.append(rowHtml);
        });
      }

      $('#fault_items_json').val(JSON.stringify(faultItems));
      syncLegacyFaultFields();
    }

    function getCurrentFaultSelection() {
      var controls = getActiveFaultControls();
      if (!controls) {
        return null;
      }

      var eventVal = controls.event.val();
      var mainVal = controls.main.val();
      var subVal = controls.sub.val();
      var fullCode = controls.detail.val();

      if (!eventVal || !mainVal || !subVal || !fullCode) {
        return null;
      }

      var detailOpt = controls.detail.find('option:selected');
      var failureCodeId = parseInt(detailOpt.data('extra')) || 0;

      return {
        failure_code_id: failureCodeId,
        event_type_code: eventVal,
        event_type_name: controls.event.find('option:selected').text(),
        main_category_code: mainVal,
        main_category_name: controls.main.find('option:selected').text(),
        sub_category: subVal,
        failure_detail: detailOpt.text(),
        full_code: fullCode
      };
    }

    // ============================
    // وظيفة إنشاء نظام لفورم معين
    // ============================
    function initFailureSystem(opts) {
      var eqType = opts.eqType;
      var selEvent = $(opts.selEvent);
      var selMain = $(opts.selMain);
      var selSub = $(opts.selSub);
      var selDet = $(opts.selDet);
      var codeDisp = $(opts.codeDisp);
      var codeTxt = $(opts.codeTxt);
      var hidType = $(opts.hidType);
      var hidDept = $(opts.hidDept);
      var hidPart = $(opts.hidPart);
      var hidDet = $(opts.hidDet);

      // 1. تحميل أنواع الأحداث
      $.getJSON(BASE_URL, { action: 'get_event_types', equipment_type: eqType }, function (res) {
        if (res && res.length) {
          fillSelect(selEvent, res.map(function (r) {
            return { value: r.event_type_code, label: r.event_type_name };
          }), '-- اختر نوع الحدث --');
        }
      });

      // 2. تغيير نوع الحدث → تحميل الفئات
      selEvent.on('change', function () {
        resetSelect(selMain, '-- اختر الفئة --');
        resetSelect(selSub, '-- اختر الجزء --');
        resetSelect(selDet, '-- اختر التفصيل --');
        codeDisp.hide();
        hidType.val($(this).find('option:selected').text());
        hidDept.val(''); hidPart.val(''); hidDet.val('');
        var code = $(this).val();
        if (!code) return;
        $.getJSON(BASE_URL, { action: 'get_main_cats', equipment_type: eqType, event_type_code: code }, function (res) {
          if (res && res.length) {
            fillSelect(selMain, res.map(function (r) {
              return { value: r.main_category_code, label: r.main_category_name };
            }), '-- اختر الفئة --');
          }
        });
      });

      // 3. تغيير الفئة الرئيسية → تحميل الأجزاء
      selMain.on('change', function () {
        resetSelect(selSub, '-- اختر الجزء --');
        resetSelect(selDet, '-- اختر التفصيل --');
        codeDisp.hide();
        hidDept.val($(this).find('option:selected').text());
        hidPart.val(''); hidDet.val('');
        var mainCode = $(this).val();
        if (!mainCode) return;
        $.getJSON(BASE_URL, { action: 'get_sub_cats', equipment_type: eqType, event_type_code: selEvent.val(), main_cat_code: mainCode }, function (res) {
          if (res && res.length) {
            fillSelect(selSub, res.map(function (r) {
              return { value: r, label: r };
            }), '-- اختر الجزء --');
          }
        });
      });

      // 4. تغيير الجزء → تحميل التفصيل
      selSub.on('change', function () {
        resetSelect(selDet, '-- اختر التفصيل --');
        codeDisp.hide();
        hidPart.val($(this).val());
        hidDet.val('');
        var sub = $(this).val();
        if (!sub) return;
        $.getJSON(BASE_URL, { action: 'get_details', equipment_type: eqType, event_type_code: selEvent.val(), main_cat_code: selMain.val(), sub_cat: sub }, function (res) {
          if (res && res.length) {
            fillSelect(selDet, res.map(function (r) {
              return { value: r.full_code, label: r.failure_detail, extra: r.id };
            }), '-- اختر التفصيل --');
          }
        });
      });

      // 5. تغيير التفصيل → تعبئة الحقول المخفية وعرض الكود
      selDet.on('change', function () {
        var fullCode = $(this).val();
        var detText = $(this).find('option:selected').text();
        if (!fullCode) { codeDisp.hide(); hidDet.val(''); return; }
        hidDet.val(fullCode + ' | ' + detText);
        codeTxt.text(fullCode);
        codeDisp.show();
      });

      // 6. دالة تحميل قيم موجودة (للتعديل)
      return {
        load: function (ft, fd, fp, fdet) {
          if (!fdet) return;
          var fullCode = fdet.split(' | ')[0].trim();
          $.getJSON(BASE_URL, { action: 'get_by_code', full_code: fullCode }, function (r) {
            if (!r) return;
            $.getJSON(BASE_URL, { action: 'get_event_types', equipment_type: eqType }, function (res2) {
              if (!res2 || !res2.length) return;
              fillSelect(selEvent, res2.map(function (x) {
                return { value: x.event_type_code, label: x.event_type_name };
              }), '-- اختر نوع الحدث --');
              selEvent.val(r.event_type_code);
              hidType.val(selEvent.find('option:selected').text());
              $.getJSON(BASE_URL, { action: 'get_main_cats', equipment_type: eqType, event_type_code: r.event_type_code }, function (res3) {
                if (!res3 || !res3.length) return;
                fillSelect(selMain, res3.map(function (x) {
                  return { value: x.main_category_code, label: x.main_category_name };
                }), '-- اختر الفئة --');
                selMain.val(r.main_category_code);
                hidDept.val(selMain.find('option:selected').text());
                $.getJSON(BASE_URL, { action: 'get_sub_cats', equipment_type: eqType, event_type_code: r.event_type_code, main_cat_code: r.main_category_code }, function (res4) {
                  if (!res4 || !res4.length) return;
                  fillSelect(selSub, res4.map(function (x) {
                    return { value: x, label: x };
                  }), '-- اختر الجزء --');
                  selSub.val(r.sub_category);
                  hidPart.val(r.sub_category);
                  $.getJSON(BASE_URL, { action: 'get_details', equipment_type: eqType, event_type_code: r.event_type_code, main_cat_code: r.main_category_code, sub_cat: r.sub_category }, function (res5) {
                    if (!res5 || !res5.length) return;
                    fillSelect(selDet, res5.map(function (x) {
                      return { value: x.full_code, label: x.failure_detail, extra: x.id };
                    }), '-- اختر التفصيل --');
                    selDet.val(r.full_code);
                    hidDet.val(r.full_code + ' | ' + r.failure_detail);
                    codeTxt.text(r.full_code);
                    codeDisp.show();
                  });
                });
              });
            });
          });
        }
      };
    }

    // ============================
    // تهيئة الأنظمة الثلاثة
    // ============================
    var sys1 = initFailureSystem({
      eqType: equipType === 3 ? 1 : equipType, // الفورم 1 للحفار أو القلاب
      selEvent: '#fc_event_type',
      selMain: '#fc_main_cat',
      selSub: '#fc_sub_cat',
      selDet: '#fc_detail',
      codeDisp: '#fc_code_display_add',
      codeTxt: '#fc_code_text_add',
      hidType: '#fault_type',
      hidDept: '#fault_department',
      hidPart: '#fault_part',
      hidDet: '#fault_details'
    });

    var sys3 = initFailureSystem({
      eqType: 3, // الفورم 3 للخرامة دائماً
      selEvent: '#fc_event_type_f3',
      selMain: '#fc_main_cat_f3',
      selSub: '#fc_sub_cat_f3',
      selDet: '#fc_detail_f3',
      codeDisp: '#fc_code_display_f3',
      codeTxt: '#fc_code_text_f3',
      hidType: 'input[name="fault_type"]:last',
      hidDept: 'input[name="fault_department"]:last',
      hidPart: 'input[name="fault_part"]:last',
      hidDet: 'input[name="fault_details"]:last'
    });

    var sysEdit = initFailureSystem({
      eqType: equipType,
      selEvent: '#fc_event_type_edit',
      selMain: '#fc_main_cat_edit',
      selSub: '#fc_sub_cat_edit',
      selDet: '#fc_detail_edit',
      codeDisp: '#fc_code_display_edit',
      codeTxt: '#fc_code_text_edit',
      hidType: '#fault_type',
      hidDept: '#fault_department',
      hidPart: '#fault_part',
      hidDet: '#fault_details'
    });

    $(document).on('click', '#addFaultBtn', function () {
      var selected = getCurrentFaultSelection();
      if (!selected) {
        alert('يرجى اختيار تسلسل العطل كاملاً قبل الإضافة.');
        return;
      }

      var exists = false;
      for (var i = 0; i < faultItems.length; i++) {
        if (faultItems[i].full_code === selected.full_code) {
          exists = true;
          break;
        }
      }
      if (exists) {
        alert('هذا العطل مضاف مسبقاً في نفس التايم شيت.');
        return;
      }

      faultItems.push(selected);
      renderFaultItemsTable();
    });

    $(document).on('click', '.removeFaultBtn', function () {
      var idx = parseInt($(this).data('index'));
      if (isNaN(idx) || idx < 0 || idx >= faultItems.length) {
        return;
      }
      faultItems.splice(idx, 1);
      renderFaultItemsTable();
    });

    window.loadTimesheetFaultItems = function (timesheetId) {
      faultItems = [];
      renderFaultItemsTable();
      if (!timesheetId) {
        return;
      }

      $.getJSON('get_timesheet_failures.php', { timesheet_id: timesheetId }, function (res) {
        if (!res || !res.success || !res.data || !res.data.length) {
          return;
        }
        faultItems = res.data.map(function (r) {
          return {
            failure_code_id: parseInt(r.failure_code_id) || 0,
            event_type_code: r.event_type_code || '',
            event_type_name: r.event_type_name || '',
            main_category_code: r.main_category_code || '',
            main_category_name: r.main_category_name || '',
            sub_category: r.sub_category || '',
            failure_detail: r.failure_detail || '',
            full_code: r.full_code || ''
          };
        });
        renderFaultItemsTable();
      });
    };

    renderFaultItemsTable();

    // ============================
    // تحميل بيانات التعديل عند فتح فورم التعديل
    // ============================
    $(document).on('click', '.editBtn', function () {
      var row = $(this).closest('tr');
      // الانتظار قليلاً حتى تُحمل بيانات السجل في الحقول
      setTimeout(function () {
        var ft = $('#fault_type').val();
        var fd = $('#fault_department').val();
        var fp = $('#fault_part').val();
        var fdet = $('#fault_details').val();
        if (fdet) {
          // تحديد نوع المعدة من حقل مخفي إن وجد أو استخدام equipType
          var rowType = row.data('type') || equipType;
          sysEdit.load(ft, fd, fp, fdet);
        }
      }, 400);
    });

  })();
</script>
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>

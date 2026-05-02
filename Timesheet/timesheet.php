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

$timesheet_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
$operations_project_column = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';
$session_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;

if (!function_exists('normalize_timesheet_date')) {
  function normalize_timesheet_date($date_str)
  {
    $date_str = trim((string)$date_str);
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

// If user doesn't have a project assigned, get the first available project from the company
if ($session_project_id <= 0) {
  if ($is_super_admin) {
    // Super admin can access all projects, so we'll need to handle this differently
    $session_project_id = 0; // Will be handled with a WHERE clause
  } else {
    // Regular user should have a project assigned, try to get one from their company
    $project_check = mysqli_query($conn, "SELECT id FROM project WHERE company_id = $company_id AND status = 1 LIMIT 1");
    if ($project_check && mysqli_num_rows($project_check) > 0) {
      $proj = mysqli_fetch_assoc($project_check);
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
  
  // Secure form values
  $fields = [
    "operator", "driver", "shift", "date", "shift_hours", "executed_hours",
    "bucket_hours", "jackhammer_hours", "extra_hours", "extra_hours_total",
    "standby_hours", "dependence_hours", "total_work_hours", "work_notes",
    "hr_fault", "maintenance_fault", "marketing_fault", "approval_fault",
    "other_fault_hours", "total_fault_hours", "fault_notes",
    "start_seconds", "start_minutes", "start_hours",
    "end_seconds", "end_minutes", "end_hours", "counter_diff",
    "fault_type", "fault_department", "fault_part", "fault_details",
    "general_notes", "operator_hours", "machine_standby_hours",
    "jackhammer_standby_hours", "bucket_standby_hours",
    "extra_operator_hours", "operator_standby_hours", "operator_notes",
    "tons_count", "trips_count", "transport_type",
    "meters_type", "meters_count", "drilling_holes_count", "drilling_depth",
    "type", "user_id"
  ];

  $values = [];
  foreach ($fields as $f) {
    $val = isset($_POST[$f]) ? mysqli_real_escape_string($conn, $_POST[$f]) : '';
    $values[$f] = $val;
  }

  // Ensure date is always valid for both storage and edit form input[type=date].
  $normalized_date = normalize_timesheet_date(isset($_POST['date']) ? $_POST['date'] : '');
  if ($normalized_date === '') {
    echo "<script>alert('❌ تنسيق التاريخ غير صحيح');</script>";
    exit;
  }
  $values['date'] = mysqli_real_escape_string($conn, $normalized_date);

  // ✅ التحقق من ساعات الأعطال - يجب أن يساوي مجموع حقول الأعطال مجموع ساعات التعطل
  $total_fault_hours = floatval($values['total_fault_hours']);
  if ($total_fault_hours > 0) {
    $hr_fault = floatval($values['hr_fault']);
    $maintenance_fault = floatval($values['maintenance_fault']);
    $marketing_fault = floatval($values['marketing_fault']);
    $approval_fault = floatval($values['approval_fault']);
    $other_fault_hours = floatval($values['other_fault_hours']);
    
    $total_faults_sum = $hr_fault + $maintenance_fault + $marketing_fault + $approval_fault + $other_fault_hours;
    
    // يجب أن يكون المجموع مساوياً لمجموع ساعات التعطل
    if ($total_faults_sum != $total_fault_hours) {
      echo "<script>alert('❌ خطأ في توزيع ساعات الأعطال!\\n\\nمجموع حقول الأعطال: " . $total_faults_sum . " ساعة\\nمجموع ساعات التعطل: " . $total_fault_hours . " ساعة\\n\\nيجب أن يكون مجموع الحقول التالية مساوياً لمجموع ساعات التعطل:\\n• عطل HR\\n• عطل صيانة\\n• عطل تسويق\\n• عطل اعتماد\\n• ساعات أعطال أخرى');</script>";
      exit;
    }
  }

  if ($id > 0) {
    // UPDATE
    $update_parts = [];
    foreach ($fields as $f) {
      $update_parts[] = "$f = '" . $values[$f] . "'";
    }
    $update_scope = "";
    if (!$is_super_admin) {
      if ($timesheet_has_company) {
        $update_scope = " AND company_id = $company_id";
      } else {
        $update_scope = " AND EXISTS (
          SELECT 1
          FROM operations o
          INNER JOIN project p ON p.id = o." . $operations_project_column . "
          LEFT JOIN users su ON su.id = p.created_by
          LEFT JOIN clients sc ON sc.id = p.client_id
          LEFT JOIN users scu ON scu.id = sc.created_by
          WHERE o.id = timesheet.operator
            AND (su.company_id = $company_id OR scu.company_id = $company_id)
        )";
      }
    }
    $sql = "UPDATE timesheet SET " . implode(',', $update_parts) . " WHERE id = $id" . $update_scope;
  } else {
    // INSERT
    $insert_company_col = (!$is_super_admin && $timesheet_has_company) ? ",company_id" : "";
    $insert_company_val = (!$is_super_admin && $timesheet_has_company) ? ", '" . $company_id . "'" : "";
    $sql = "INSERT INTO timesheet (" . implode(",", $fields) . $insert_company_col . ")
            VALUES ('" . implode("','", $values) . "'" . $insert_company_val . ")";
  }

  if (mysqli_query($conn, $sql)) {
    $type_param = isset($_POST['type']) ? urlencode($_POST['type']) : '1';
    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='timesheet.php?type=" . $type_param . "';</script>";
    exit;
  } else {
    echo "<script>alert('❌ خطأ في الحفظ: " . addslashes(mysqli_error($conn)) . "');</script>";
  }
}

$type = isset($_GET['type']) ? $_GET['type'] : "";
if ($type !== "1" && $type !== "2" && $type !== "3") {
  header("Location: timesheet_type.php");
  exit();
}

$page_title = "إيكوبيشن | ساعات العمل ";
include("../inheader.php");
// include('../insidebar.php');
// تحديد النوع من الرابط (إن وجد)
$type_filter = "";
if ($type != "") {
  $type_filter = " AND e.type IN (SELECT id FROM equipments_types WHERE form LIKE '$type' AND status = 'active') ";
}

$timesheet_project_scope_sql = "";
if (!$is_super_admin) {
  $timesheet_project_scope_sql = " AND EXISTS (
    SELECT 1
    FROM project p
    LEFT JOIN users su ON su.id = p.created_by
    LEFT JOIN clients sc ON sc.id = p.client_id
    LEFT JOIN users scu ON scu.id = sc.created_by
    WHERE p.id = o." . $operations_project_column . "
      AND (su.company_id = $company_id OR scu.company_id = $company_id)
  )";
}
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

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-clock"></i></div>
            إدارة ساعات العمل
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="timesheet_type.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
          <a href="view_timesheet.php?type=<?= urlencode($type) ?>" class="back-btn" style="background: var(--green-soft); color: var(--green); border-color: rgba(22,163,74,.22);">
            <i class="fas fa-table"></i> شاشة العرض الكاملة
          </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة ساعات عمل جديدة
            </a>
        </div>
    </div>
    <form id="projectForm" action="" method="post" style="display:none;">
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
                <label>الالية</label>
                <select name="operator" id="operator" required>
                  <option value="">-- اختر الالية --</option>
                  <?php
                  $project_filter = "";
                  if ($session_project_id > 0) {
                    $project_filter = " AND o." . $operations_project_column . " = '" . $session_project_id . "'";
                  }
                  $op_res = mysqli_query($conn, "SELECT o.id, o.status, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o." . $operations_project_column . " = p.id
                                            WHERE 1 $type_filter AND o.status = '1'" . $project_filter . " $timesheet_project_scope_sql");



                  if ($op_res) {
                    while ($op = mysqli_fetch_assoc($op_res)) {
                      echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
                    }
                  } else {
                    error_log('Timesheet operators query failed (type=1): ' . mysqli_error($conn));
                  }
                  ?>
                </select>
              </div>
              <input type="hidden" name="id" id="timesheet_id" value="">
              <input type="hidden" name="user_id" value="<?php echo $_SESSION['user']['id']; ?>">

              <div>
                <label>السائق</label>
                <select id="driver" name="driver">
                  <option value="">-- اختر السائق --</option>
                </select>
              </div>
              <div>
                <label>الوردية</label>
                <select name="shift" id="shift">
                  <option value=""> -- اختار الوردية -- </option>
                  <option value="D"> صباحية </option>
                  <option value="N"> مسائية </option>
                </select>
              </div>
              <div>
                <label for="date"> التاريخ </label>
                <input type="date" name="date" id="date" required />
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
                    <input type="number" value="0" id="start_minutes" name="start_minutes" min="0" max="59" placeholder="00" required>
                    <span>دقائق</span>
                  </div>
                  <span class="counter-separator">:</span>
                  <div class="counter-field">
                    <input type="number" value="0" id="start_seconds" name="start_seconds" min="0" max="59" placeholder="00" required>
                    <span>ثواني</span>
                  </div>
                </div>
              </div>

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> ساعات العمل </h3>

              <div>
                <label>الساعات المنفذة (محسوبة تلقائياً)</label>
                <input type="number" name="executed_hours" id="executed_hours" value="0" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
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
              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> ساعات الاعطال </h3>

              <!-- ⚠️ تنبيه مهم للمستخدم -->
              <div style="grid-column: 1/-1; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-right: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(255,193,7,0.15);">
                <div style="display: flex; align-items: start; gap: 12px;">
                  <div style="font-size: 28px; line-height: 1; margin-top: -3px;">⚠️</div>
                  <div style="flex: 1;">
                    <h4 style="margin: 0 0 8px 0; color: #856404; font-size: 1.05rem; font-weight: 700;">⚠️ تنبيه مهم - يرجى القراءة</h4>
                    <p style="margin: 0; color: #856404; font-size: 0.9rem; line-height: 1.6;">
                      <strong>إذا كانت هناك ساعات أعطال (مجموع ساعات التعطل أكبر من صفر)،</strong><br>
                      <strong style="color: #d32f2f;">يجب أن يساوي مجموع الحقول التالية = مجموع ساعات التعطل تماماً:</strong>
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
              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> الاعطال </h3>




              <div>
                <label>نوع العطل</label>
                <input type="text" name="fault_type" id="fault_type" />
              </div>
              <div>
                <label>قسم العطل</label>
                <input type="text" name="fault_department" id="fault_department" />
              </div>
              <div>
                <label>الجزء المعطل</label>
                <input type="text" name="fault_part" id="fault_part" />
              </div>
              <div>
                <label>تفاصيل العطل</label>
                <textarea name="fault_details" id="fault_details"></textarea>
              </div>
              <div>
                <label>ملاحظات عامة</label>
                <textarea name="general_notes" id="general_notes"></textarea>
              </div>

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> ساعات عمل المشغل </h3>

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
                <input type="text" name="operator_standby_hours" id="operator_standby_hours" class="form-control"
                  value="0">
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
                <label>الالية</label>
                <select name="operator" id="operator" required>
                  <option value="">-- اختر الالية --</option>
                  <?php
                  $project_filter = "";
                  if ($session_project_id > 0) {
                    $project_filter = " AND o." . $operations_project_column . " = '" . $session_project_id . "'";
                  }
                  $op_res = mysqli_query($conn, "SELECT o.id, o.status, o." . $operations_project_column . " AS project_id, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o." . $operations_project_column . " = p.id
                                            WHERE 1 $type_filter AND o.status = '1'" . $project_filter . " $timesheet_project_scope_sql");



                  if ($op_res) {
                    while ($op = mysqli_fetch_assoc($op_res)) {
                      echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
                    }
                  } else {
                    error_log('Timesheet operators query failed (type=2): ' . mysqli_error($conn));
                  }
                  ?>
                </select>
              </div>

              <input type="hidden" name="id" id="timesheet_id" value="">
              <input type="hidden" name="user_id" value="<?php echo $_SESSION['user']['id']; ?>">
              <div>
                <label>السائق</label>
                <!-- <select name="driver"  required>
            <option value="">-- اختر السائق --</option>
            <?php
            $driver_scope_sql = "1=1";
            if (!$is_super_admin) {
              if (db_table_has_column($conn, 'drivers', 'company_id')) {
                $driver_scope_sql = "company_id = $company_id";
              } else {
                $driver_scope_sql = "EXISTS (
                  SELECT 1
                  FROM equipment_drivers ed
                  INNER JOIN operations o ON o.equipment = ed.equipment_id
                  INNER JOIN project p ON p.id = o." . $operations_project_column . "
                  LEFT JOIN users su ON su.id = p.created_by
                  LEFT JOIN clients sc ON sc.id = p.client_id
                  LEFT JOIN users scu ON scu.id = sc.created_by
                  WHERE ed.driver_id = drivers.id
                    AND (su.company_id = $company_id OR scu.company_id = $company_id)
                )";
              }
            }
            $dr_res = mysqli_query($conn, "SELECT id, name FROM drivers WHERE $driver_scope_sql");
            while ($dr = mysqli_fetch_assoc($dr_res)) {
              echo "<option value='" . $dr['id'] . "'>" . $dr['name'] . "</option>";
            }
            ?>
          </select> -->



                <select id="driver" name="driver">
                  <option value="">-- اختر السائق --</option>
                </select>


              </div>
              <div>
                <label>الوردية</label>
                <select name="shift" id="shift">
                  <option value=""> -- اختار الوردية -- </option>
                  <option value="D"> صباحية </option>
                  <option value="N"> مسائية </option>
                </select>
              </div>
              <div>
                <label> التاريخ </label>
                <input type="date" name="date" id="date" required />
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

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> الساعات </h3>
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
                <input type="number" name="extra_hours_total" id="extra_hours_total" value="0" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
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


                    <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> 🚚 الأوزان والنقلات </h3>
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



              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> ساعات الاعطال </h3>

              <!-- ⚠️ تنبيه مهم للمستخدم -->
              <div style="grid-column: 1/-1; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-right: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(255,193,7,0.15);">
                <div style="display: flex; align-items: start; gap: 12px;">
                  <div style="font-size: 28px; line-height: 1; margin-top: -3px;">⚠️</div>
                  <div style="flex: 1;">
                    <h4 style="margin: 0 0 8px 0; color: #856404; font-size: 1.05rem; font-weight: 700;">⚠️ تنبيه مهم - يرجى القراءة</h4>
                    <p style="margin: 0; color: #856404; font-size: 0.9rem; line-height: 1.6;">
                      <strong>إذا كانت هناك ساعات أعطال (مجموع ساعات التعطل أكبر من صفر)،</strong><br>
                      <strong style="color: #d32f2f;">يجب أن يساوي مجموع الحقول التالية = مجموع ساعات التعطل تماماً:</strong>
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


              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> الاعطال </h3>              <div>
                <label>نوع العطل</label>
                <input type="text" name="fault_type" id="fault_type" />
              </div>
              <div>
                <label>قسم العطل</label>
                <input type="text" name="fault_department" id="fault_department" />
              </div>
              <div>
                <label>الجزء المعطل</label>
                <input type="text" name="fault_part" id="fault_part" />
              </div>
              <div>
                <label>تفاصيل العطل</label>
                <textarea name="fault_details" id="fault_details"></textarea>
              </div>
              <div>
                <label>ملاحظات عامة</label>
                <textarea name="general_notes" id="general_notes"></textarea>
              </div>


              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> ساعات عمل المشغل </h3>
        
          
             
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
                <label>الالية</label>
                <select name="operator" id="operator" required>
                  <option value="">-- اختر الالية --</option>
                  <?php
                  $project_filter = "";
                  if ($session_project_id > 0) {
                    $project_filter = " AND o." . $operations_project_column . " = '" . $session_project_id . "'";
                  }
                  $op_res = mysqli_query($conn, "SELECT o.id, o.status, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o." . $operations_project_column . " = p.id
                                            WHERE 1 $type_filter AND o.status = '1'" . $project_filter . " $timesheet_project_scope_sql");



                  if ($op_res) {
                    while ($op = mysqli_fetch_assoc($op_res)) {
                      echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
                    }
                  } else {
                    error_log('Timesheet operators query failed (type=3): ' . mysqli_error($conn));
                  }
                  ?>
                </select>
              </div>
              <input type="hidden" name="id" id="timesheet_id" value="">
              <input type="hidden" name="user_id" value="<?php echo $_SESSION['user']['id']; ?>">

              <div>
                <label>السائق</label>
                <select id="driver" name="driver">
                  <option value="">-- اختر السائق --</option>
                </select>
              </div>
              <div>
                <label>الوردية</label>
                <select name="shift" id="shift">
                  <option value=""> -- اختار الوردية -- </option>
                  <option value="D"> صباحية </option>
                  <option value="N"> مسائية </option>
                </select>
              </div>
              <div>
                <label for="date"> التاريخ </label>
                <input type="date" name="date" id="date" required />
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
                    <input type="number" value="0" id="start_minutes" name="start_minutes" min="0" max="59" placeholder="00" required>
                    <span>دقائق</span>
                  </div>
                  <span class="counter-separator">:</span>
                  <div class="counter-field">
                    <input type="number" value="0" id="start_seconds" name="start_seconds" min="0" max="59" placeholder="00" required>
                    <span>ثواني</span>
                  </div>
                </div>
              </div>

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> ساعات العمل </h3>

              <div>
                <label>الساعات المنفذة</label>
                <input type="number" name="executed_hours" id="executed_hours" value="0">
              </div>
             
                <input type="hidden"  name="bucket_hours" id="bucket_hours" value="0">
              
                <input type="hidden" name="jackhammer_hours" id="jackhammer_hours" value="0">
             
              <div>
                <label>ساعات إضافية</label>
                <input type="number" name="extra_hours" id="extra_hours" value="0">
              </div>
              <div>
                <label>مجموع الساعات الإضافية (محسوبة تلقائياً)</label>
                <input type="number" name="extra_hours_total" id="extra_hours_total" value="0" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
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
               <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> 📏 الأمتار </h3>
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
                <input type="number" step="0.01" name="meters_count" id="meters_count" value="0" placeholder="0.00" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
              </div>
              <div>
                <label>⛏️ عدد الحفر المخرمة</label>
                <input type="number" name="drilling_holes_count" id="drilling_holes_count" value="0" placeholder="0">
              </div>
              <div>
                <label>📊 أعماق الحفر (متر)</label>
                <input type="number" step="0.01" name="drilling_depth" id="drilling_depth" value="0" placeholder="0.00">
              </div>
              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;"> ساعات الاعطال </h3>

              <!-- ⚠️ تنبيه مهم للمستخدم -->
              <div style="grid-column: 1/-1; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-right: 5px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(255,193,7,0.15);">
                <div style="display: flex; align-items: start; gap: 12px;">
                  <div style="font-size: 28px; line-height: 1; margin-top: -3px;">⚠️</div>
                  <div style="flex: 1;">
                    <h4 style="margin: 0 0 8px 0; color: #856404; font-size: 1.05rem; font-weight: 700;">⚠️ تنبيه مهم - يرجى القراءة</h4>
                    <p style="margin: 0; color: #856404; font-size: 0.9rem; line-height: 1.6;">
                      <strong>إذا كانت هناك ساعات أعطال (مجموع ساعات التعطل أكبر من صفر)،</strong><br>
                      <strong style="color: #d32f2f;">يجب أن يساوي مجموع الحقول التالية = مجموع ساعات التعطل تماماً:</strong>
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
              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> الاعطال </h3>


            


              <div>
                <label>نوع العطل</label>
                <input type="text" name="fault_type" id="fault_type" />
              </div>
              <div>
                <label>قسم العطل</label>
                <input type="text" name="fault_department" id="fault_department" />
              </div>
              <div>
                <label>الجزء المعطل</label>
                <input type="text" name="fault_part" id="fault_part" />
              </div>
              <div>
                <label>تفاصيل العطل</label>
                <textarea name="fault_details" id="fault_details"></textarea>
              </div>
              <div>
                <label>ملاحظات عامة</label>
                <textarea name="general_notes" id="general_notes"></textarea>
              </div>

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> ساعات عمل المشغل </h3>

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
                <input type="text" name="operator_standby_hours" id="operator_standby_hours" class="form-control"
                  value="0">
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
        <div class="card-header">
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
            <!-- <th><i class="fas fa-toggle-on"></i> الحالة</th> -->
            <th><i class="fas fa-cogs"></i> إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <!-- Data will be loaded via AJAX -->
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
        serverSide: true,
        ajax: {
          url: 'get_timesheet_data.php',
          type: 'GET',
          data: {
            type: '<?php echo $type; ?>',
            today_only: '1'
          },
          error: function(xhr, error, thrown) {
            console.error('DataTables AJAX Error:', error, thrown);
          }
        },
        columns: [
          { data: 0, orderable: false }, // #
          { data: 1 }, // ID
          { data: 2 }, // المعدة
          { data: 3 }, // التاريخ
          { data: 4, orderable: false }, // الوردية
          { data: 5 }, // الساعات المنفذة
          { data: 6 }, // الجردل
          { data: 7 }, // الجاكهمر
          { data: 8 }, // الإضافية
          { data: 9 }, // الاستعداد
          { data: 10 }, // الأعطال
          { data: 11 }, // ساعات العمل
          { data: 12, orderable: false }, // الإجمالي
          // { data: 13 }, // الحالة
          { data: 14, orderable: false } // إجراءات
        ],
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
          url: "https:/ems/assets/i18n/datatables/ar.json",
          processing: '<i class="fas fa-spinner fa-spin fa-3x"></i><br>جاري التحميل...'
        }
      });

      // Update table when sidebar toggles
      const sidebarToggle = document.getElementById('toggleBtn');
      if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
          setTimeout(function() {
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
          console.log('Toggle clicked, current display:', form.style.display);
          if (form.style.display === "none" || form.style.display === "") {
            form.style.display = "block";
            console.log('Form shown');
          } else {
            form.style.display = "none";
            console.log('Form hidden');
          }
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
    projectForm.addEventListener('submit', function(e) {
      const totalFaultHours = parseFloat(document.querySelector("input[name='total_fault_hours']").value) || 0;
      
      if (totalFaultHours > 0) {
        const hrFault = parseFloat(document.querySelector("input[name='hr_fault']").value) || 0;
        const maintenanceFault = parseFloat(document.querySelector("input[name='maintenance_fault']").value) || 0;
        const marketingFault = parseFloat(document.querySelector("input[name='marketing_fault']").value) || 0;
        const approvalFault = parseFloat(document.querySelector("input[name='approval_fault']").value) || 0;
        const otherFaultHours = parseFloat(document.querySelector("input[name='other_fault_hours']").value) || 0;
        
        const totalFaultsSum = hrFault + maintenanceFault + marketingFault + approvalFault + otherFaultHours;
        
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
                '• ساعات أعطال أخرى');
          
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




  $(document).ready(function () {
    $("#operator").change(function () {
      var equipId = $(this).val();
      if (equipId !== "") {
        $.ajax({
          url: "get_drivers.php",
          type: "GET",
          data: { operation_id: equipId },
          success: function (response) {
            console.log("ðŸ“Œ Response:", response); // Debug
            $("#driver").html(response);
          },
          error: function (xhr, status, error) {
            console.error("❌ AJAX Error:", error);
          }
        });
      } else {
        $("#driver").html("<option value=''>-- اختر السائق --</option>");
      }
    });
  });




  $(document).ready(function () {
    $("#operator").change(function () {
      var opId = $(this).val();
      if (opId !== "") {
        $.ajax({
          url: "get_contract_hours.php",
          type: "GET",
          data: { operation_id: opId },
          success: function (response) {
            console.log("✅ تم جلب ساعات الوردية:", response);
            $("#shift_hours").val(response); // عرض القيمة داخل input
            
            // إعادة حساب الحقول الأخرى تلقائياً بعد تحميل ساعات الوردية
            calculateCustomHours();
          },
          error: function (xhr, status, error) {
            console.error("❌ خطأ في جلب ساعات الوردية:", error);
            $("#shift_hours").val("8"); // قيمة افتراضية في حالة الخطأ
          }
        });
      } else {
        $("#shift_hours").val("8");
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

      // بعد تحميل السائقين من AJAX نضبط السائق المحدد (ننتظر قليلاً)
      setTimeout(function () {
        $("#driver").val(data.driver);
      }, 300);

      $("#shift").val(data.shift);
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
      $("#tons_count").val(data.tons_count || 0);
      $("#trips_count").val(data.trips_count || 0);
      $("#transport_type").val(data.transport_type || '');
      $("#meters_type").val(data.meters_type || '');
      $("#meters_count").val(data.meters_count || 0);
      $("#drilling_holes_count").val(data.drilling_holes_count || 0);
      $("#drilling_depth").val(data.drilling_depth || 0);

      $("#projectForm").show();
      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    })
      .fail(function () {
        alert("خطأ في جلب بيانات التايم شيت.");
      });
  });


</script>



</body>

</html>



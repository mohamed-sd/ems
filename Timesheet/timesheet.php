<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}
include '../config.php';

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
    "type", "user_id"
  ];

  $values = [];
  foreach ($fields as $f) {
    $val = isset($_POST[$f]) ? mysqli_real_escape_string($conn, $_POST[$f]) : '';
    $values[$f] = $val;
  }

  if ($id > 0) {
    // UPDATE
    $update_parts = [];
    foreach ($fields as $f) {
      $update_parts[] = "$f = '" . $values[$f] . "'";
    }
    $sql = "UPDATE timesheet SET " . implode(',', $update_parts) . " WHERE id = $id";
  } else {
    // INSERT
    $sql = "INSERT INTO timesheet (" . implode(",", $fields) . ")
            VALUES ('" . implode("','", $values) . "')";
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
if ($type !== "1" && $type !== "2") {
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
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

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
                  $op_res = mysqli_query($conn, "SELECT o.id, o.status, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o.project_id = p.id    WHERE 1 $type_filter AND o.status = '1' AND o.project_id = '" . $_SESSION['user']['project_id'] . "'");



                  while ($op = mysqli_fetch_assoc($op_res)) {
                    echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
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

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> ساعات العمل </h3>

              <div>
                <label>الساعات المنفذة</label>
                <input type="number" name="executed_hours" id="executed_hours" value="0">
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
              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> ساعات الاعطال </h3>

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


              <div></div>
              <div></div>


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
                <label>👷 ساعات استعداد المشغل</label>
                <input type="text" name="operator_standby_hours" id="operator_standby_hours" class="form-control"
                  value="0">
              </div>
              <div>
                <label>📝 ملاحظات المشغل</label>
                <textarea name="operator_notes" id="operator_notes" class="form-control"></textarea>

              </div>

              <input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />

              <button type="submit" style="margin-top: 20px;">
                <i class="fas fa-save"></i> حفظ الساعات
              </button>

            </div>
          </div>
        </div>
        <?php } else {
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
                  include '../config.php';
                  $op_res = mysqli_query($conn, "SELECT o.id, o.status, o.project_id, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN project p ON o.project_id = p.id    WHERE 1 $type_filter AND o.status = '1' AND o.project_id = '" . $_SESSION['user']['project_id'] . "'");



                  while ($op = mysqli_fetch_assoc($op_res)) {
                    echo "<option value='" . $op['id'] . "'>" . $op['eq_code'] . " - " . $op['eq_name'] . "</option>";
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
            $dr_res = mysqli_query($conn, "SELECT id, name FROM drivers");
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
              <input type="hidden" name="extra_hours" id="extra_hours" value="0">

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
                <textarea name="work_notes" id="work_notes"></textarea>
              </div>

              <h3 style="grid-column: 1/-1; text-align: right; color: var(--txt); margin: 16px 0 8px; font-weight: 700; font-size: 1rem;\"> ساعات الاعطال </h3>

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
              <div></div>
              <div></div>
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
                <label>👷 ساعات استعداد المشغل</label>
                <input type="text" name="operator_standby_hours" class="form-control" value="0">
              </div>
              <div>
                <label>📝 ملاحظات المشغل</label>
                <textarea name="operator_notes" id="operator_notes" class="form-control"></textarea>
              </div>

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
          <!-- Data will be loaded via AJAX -->
        </tbody>
      </table>
      
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
  $(document).ready(function () {
    var table = $('#projectsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
          url: 'get_timesheet_data.php',
          type: 'GET',
          data: {
            type: '<?php echo $type; ?>'
          },
          error: function(xhr, error, thrown) {
            console.error('DataTables AJAX Error:', error, thrown);
          }
        },
        columns: [
          { data: 0, orderable: false }, // #
          { data: 1 }, // المعدة
          { data: 2 }, // التاريخ
          { data: 3, orderable: false }, // الوردية
          { data: 4 }, // الساعات المنفذة
          { data: 5 }, // الجردل
          { data: 6 }, // الجاكهمر
          { data: 7 }, // الإضافية
          { data: 8 }, // الاستعداد
          { data: 9 }, // الأعطال
          { data: 10 }, // ساعات العمل
          { data: 11, orderable: false }, // الإجمالي
          { data: 12 }, // الحالة
          { data: 13, orderable: false } // إجراءات
        ],
        order: [[2, 'desc']], // Sort by date descending by default
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
          url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json",
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
    let dependence = parseFloat(document.querySelector("input[name='dependence_hours']").value) || 0;
    let executed = parseFloat(document.querySelector("input[name='executed_hours']").value) || 0;
    let extraTotal = parseFloat(document.querySelector("input[name='extra_hours_total']").value) || 0;
    let standby = parseFloat(document.querySelector("input[name='standby_hours']").value) || 0;
    let shift = parseFloat(document.querySelector("input[name='shift_hours']").value) || 0;
    let maintenance = parseFloat(document.querySelector("input[name='maintenance_fault']").value) || 0;
    let marketing = parseFloat(document.querySelector("input[name='marketing_fault']").value) || 0;

    // العملية الأولى: مجموع ساعات العمل
    let totalWork = executed + extraTotal + standby;
    document.querySelector("input[name='total_work_hours']").value = totalWork;

    // العملية الثانية: ساعات أعطال أخرى
    let otherFault = shift - executed - standby - dependence;
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
  document.querySelectorAll("input[name='executed_hours'], input[name='extra_hours_total'], input[name='standby_hours'], input[name='shift_hours'], input[name='maintenance_fault'], input[name='marketing_fault'] , input[name='dependence_hours'] , input[name='machine_standby_hours']  ")
    .forEach(el => el.addEventListener("input", calculateCustomHours));

  // ✅ استدعاء أول مرة
  calculateCustomHours();

  var machineType = "<?php echo $_GET['type']; ?>";
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
            console.log("📌 Response:", response); // Debug
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
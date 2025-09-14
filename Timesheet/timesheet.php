<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}
$page_title = "إيكوبيشن | ساعات العمل ";
include("../inheader.php");
include('../insidebar.php');
// تحديد النوع من الرابط (إن وجد)
$type_filter = "";
if (isset($_GET['type']) && $_GET['type'] != "") {
  $type = $_GET['type'];
  $type_filter = " AND e.type = '$type' ";
}
?>

<div class="main">

  <a href="javascript:void(0)" id="toggleForm" class="add">
    <i class="fa fa-plus"></i> اضافة ساعات عمل
  </a>

  <form id="projectForm" action="" method="post" style="display:none; margin-top:20px;">

    <?php if ($_GET['type'] == "1") {
      // نوع المعدة كان حفار 
      ?>
      <div>
        <h2> اضافة حفار </h2>
        <br />
        <div class="form-grid">

          <div>
            <label>الالية</label>
            <select name="operator" id="operator" required>
              <option value="">-- اختر الالية --</option>
              <?php
              include '../config.php';
              $op_res = mysqli_query($conn, "SELECT o.id, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN projects p ON o.project = p.id    WHERE 1 $type_filter");



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
            <input type="date" name="date" id="date" required />
          </div>


          <!-- ********************************************************** -->

          <div>
            <label>ساعات الوردية</label>
            <input type="number" name="shift_hours" id="shift_hours" value="0">
          </div>

          <div></div>
          <div></div>
          <div></div>
          <h3> الساعات </h3>
          <div></div>
          <div></div>
          <div></div>

          <div>
            <label> ⏱️ عداد البداية</label>
            <input type="number" value="0" style="width: 30%" id="start_seconds" name="start_seconds" min="0" max="59"
              placeholder="ثواني" required>
            <input type="number" value="0" style="width: 30%" id="start_minutes" name="start_minutes" min="0" max="59"
              placeholder="دقائق" required>
            <input type="number" value="0" style="width: 30%" id="start_hours" name="start_hours" placeholder="ساعات">
          </div>

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

          <div></div>
          <div></div>
          <h3> ساعات الاعطال </h3>
          <div></div>
          <div></div>
          <div></div>

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
            <input style="width: 30%" type="number" value="0" id="end_seconds" name="end_seconds" min="0" max="59"
              placeholder="ثواني">
            <input style="width: 30%" type="number" value="0" id="end_minutes" name="end_minutes" min="0" max="59"
              placeholder="دقائق">
            <input style="width: 30%" type="number" value="0" id="end_hours" name="end_hours" placeholder="ساعات">
          </div>

          <div>
            <label>⚡ فرق العداد</label>
            <input type="text" name="counter_diff" id="counter_diff_display" readonly>
            <input type="hidden" id="counter_diff" />
          </div>

          <div></div>
          <div></div>
          <div></div>
          <h3> الاعطال </h3>

          <div></div>
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

          <div></div>
          <div></div>
          <div></div>

          <h3> ساعات عمل المشغل </h3>

          <div></div>
          <div></div>
          <div></div>

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
            <input type="text" name="operator_standby_hours" id="operator_standby_hours" class="form-control" value="0">
          </div>
          <div>
            <label>📝 ملاحظات المشغل</label>
            <textarea name="operator_notes" id="operator_notes" class="form-control"></textarea>

          </div>

          <input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />

          <button type="submit">حفظ الساعات</button>

        </div>
      </div>
    <?php } else {
      // نوع المهدةطلع قلاب
      ?>
      <div>
        <h2> اضافة قلاب </h2>
        <div class="form-grid">
          <div>
            <label>الالية</label>
            <select name="operator" id="operator" required>
              <option value="">-- اختر الالية --</option>
              <?php
              include '../config.php';
              $op_res = mysqli_query($conn, "SELECT o.id, e.code AS eq_code, e.name AS eq_name, p.name AS project_name , e.type
                                            FROM operations o
                                            JOIN equipments e ON o.equipment = e.id
                                            JOIN projects p ON o.project = p.id    WHERE 1 $type_filter");



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

          <div></div>
          <div></div>
          <div></div>
          <h3> الساعات </h3>
          <div></div>
          <div></div>
          <div></div>

          <div>
            <label> ⏱️ عداد البداية</label>
            <input type="hidden" value="0" style="width: 30%" id="start_seconds" name="start_seconds" min="0" max="59"
              placeholder="ثواني" required>
            <input type="hidden" value="0" style="width: 30%" id="start_minutes" name="start_minutes" min="0" max="59"
              placeholder="دقائق" required>
            <input type="number" value="0" id="start_hours" name="start_hours" placeholder="ساعات">
          </div>

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

          <div></div>
          <h3> ساعات الاعطال </h3>
          <div></div>
          <div></div>
          <div></div>

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
            <input style="width: 30%" type="hidden" value="0" id="end_seconds" name="end_seconds" min="0" max="59"
              placeholder="ثواني">
            <input style="width: 30%" type="hidden" value="0" id="end_minutes" name="end_minutes" min="0" max="59"
              placeholder="دقائق">
            <input type="number" value="0" id="end_hours" name="end_hours" placeholder="ساعات">
          </div>

          <div>
            <label>⚡ فرق العداد</label>
            <input type="text" name="counter_diff" id="counter_diff_display" readonly>
            <input type="hidden" id="counter_diff" />
          </div>

          <div></div>
          <div></div>
          <div></div>
          <h3> الاعطال </h3>

          <div></div>
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

          <div></div>
          <div></div>
          <div></div>

          <h3> ساعات عمل المشغل </h3>

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


          <button type="submit">حفظ الساعات</button>

        </div>
      </div>
    <?php } ?>


  </form>

  <br /><br /><br />

  <!-- جدول ساعات العمل -->
  <h3>قائمة ساعات العمل</h3>
  <br />
  <table id="projectsTable" class="display nowrap" style="width:100%; margin-top:20px;">
    <thead>
      <tr>
        <th style="text-align: right;"> # </th>
        <th style="text-align: right;"> المعدة </th>
        <th style="text-align: right;"> المشروع </th>
        <!-- <th style="text-align: right;"> المالك </th> -->
        <th style="text-align: right;"> التاريخ </th>
        <th style="text-align: right;"> الوردية </th>
        <th style="text-align: right;"> الساعات </th>
        <th style="text-align: right;"> الجردل </th>
        <th style="text-align: right;"> الجاكهمر </th>
        <th style="text-align: right;"> الاضافية </th>
        <!-- <th style="text-align: right;"> مجموع الاضافية</th> -->
        <th style="text-align: right;"> الاستعداد </th>
        <!-- <th style="text-align: right;"> استعداد الاعتماد </th> -->
        <!-- <th style="text-align: right;"> مجموع الساعات </th> -->
        <!-- <th style="text-align: right;"> ملاحظات العمل </th> -->
        <!-- <th style="text-align: right;"> عطل HR </th> -->
        <th style="text-align: right;"> الاعطال </th>
        <th style="text-align: right;"> الحالة </th>
        <!-- <th>جاك هامر</th> -->
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php


      if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['operator'])) {

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        // تأمين القيم من الفورم
        $fields = [
          "operator",
          "driver",
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
          "type",
          "user_id"
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
          // بناء الاستعلام
          $sql = "INSERT INTO timesheet (" . implode(",", $fields) . ")
            VALUES ('" . implode("','", $values) . "')";
        }

        if (mysqli_query($conn, $sql)) {
          echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='timesheet.php?type=" . urlencode($type) . "';</script>";
          exit;
        } else {
          echo "<script>alert('❌ خطأ في الحفظ: " . mysqli_real_escape_string($conn, mysqli_error($conn)) . "');</script>";
        }
      }

      $type = $_GET['type'];

      // عرض البيانات
      $query = "SELECT t.id, t.shift, t.date, t.executed_hours ,
        t.standby_hours , t.total_fault_hours ,bucket_hours,jackhammer_hours,
        extra_hours,extra_hours_total,dependence_hours,	total_work_hours, t.status ,
        work_notes,hr_fault,
                 e.code AS eq_code, e.name AS eq_name,
                 p.name AS project_name,
                 o.id AS operation_id,
                 d.name AS driver_name 
          FROM timesheet t
          JOIN operations o ON t.operator = o.id
          JOIN equipments e ON o.equipment = e.id
          JOIN projects p ON o.project = p.id
          JOIN drivers d ON t.driver = d.id
          WHERE t.type LIKE '$type'
          ORDER BY t.id DESC";


      $result = mysqli_query($conn, $query);
      $i = 1;
      while ($row = mysqli_fetch_assoc($result)) {

        // The Variable that take the status value
        switch ($row['status']) {
          case "1":
            $status = "<font color='grey'> تحت المراجعة </font>";
            break;
          case "2":
            $status = "<font color='green'> تم الاعتماد </font>";
            break;
          case "3":
            $status = "<font color='red'> تم الرفض </font>";
            break;
          default:
            $status = "غير معروف";
        }

        echo "<tr>";
        echo "<td>" . $i++ . "</td>";
        echo "<td>" . $row['eq_code'] . " - " . $row['eq_name'] . "</td>";
        echo "<td>" . $row['project_name'] . "</td>";
        // echo "<td> ... </td>";
        echo "<td>" . $row['date'] . "</td>";
        echo $row['shift'] == "D" ? "<td> صباحية </td>" : "<td> مسائية </td>";
        echo "<td>" . $row['executed_hours'] . "</td>";
        echo "<td>" . $row['bucket_hours'] . "</td>";
        echo "<td>" . $row['jackhammer_hours'] . "</td>";
        echo "<td>" . $row['extra_hours'] . "</td>";
        // echo "<td>" . $row['extra_hours_total'] . "</td>";
        echo "<td>" . $row['standby_hours'] . "</td>";
        // echo "<td>" . $row['dependence_hours'] . "</td>";
        // echo "<td>" . $row['total_work_hours'] . "</td>";
        // echo "<td>" . $row['work_notes'] . "</td>";
        // echo "<td>" . $row['hr_fault'] . "</td>";
        echo "<td>" . $row['total_fault_hours'] . "</td>";
        echo "<td>" . $status . "</td>";
        echo "<td>
        <a href='aprovment.php?t=".$type."&&type=1&&id=" . $row['id'] . "'  style='color: #28a745'> <i class='fa fa-check'></i> </a> |
        <a href='aprovment.php?t=".$type."&&type=2&&id=" . $row['id'] . "'  style='color: #dc3545'> <i class='fa fa-close'></i> </a> |
        <a href='javascript:void(0)' class='editBtn' data-id='" . $row['id'] . "' style='color:#007bff'><i class='fa fa-edit'></i></a>  | 
        <a href='delete_timesheet.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> |
        <a href='timesheet_details.php?id=" . $row['id'] . "'  style='color: #28a745'> <i class='fa fa-eye'></i> </a>  
        </td>";
        echo "</tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
  (function () {
    $(document).ready(function () {
      $('#projectsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip', // Buttons + Search + Pagination
        buttons: [
          { extend: 'copy', text: 'نسخ' },
          { extend: 'excel', text: 'تصدير Excel' },
          { extend: 'csv', text: 'تصدير CSV' },
          { extend: 'pdf', text: 'تصدير PDF' },
          { extend: 'print', text: 'طباعة' }
        ],
        "language": {
          "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        }
      });
    });

    const toggleFormBtn = document.getElementById('toggleForm');
    const form = document.getElementById('projectForm');

    toggleFormBtn.addEventListener('click', function () {
      form.style.display = form.style.display === "none" ? "block" : "none";
    });
  })();


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
            $("#shift_hours").val(response); // عرض القيمة داخل input
          },
          error: function (xhr, status, error) {
            $("#contract_hours").val("خطأ ⚠️");
            console.error(error);
          }
        });
      } else {
        $("#shift_hours").val("");
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
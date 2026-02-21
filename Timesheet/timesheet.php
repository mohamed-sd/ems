<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}
include '../config.php';
$type = isset($_GET['type']) ? $_GET['type'] : "";
if ($type !== "1" && $type !== "2") {
  header("Location: timesheet_type.php");
  exit();
}
$page_title = "إيكوبيشن | ساعات العمل ";
include("../inheader.php");
include('../insidebar.php');
// تحديد النوع من الرابط (إن وجد)
$type_filter = "";
if ($type != "") {
  $type_filter = " AND e.type = '$type' ";
}
?>
<div class="main">

  <div class="aligin" style="margin-bottom: 20px;">
    <a href="javascript:void(0)" id="toggleForm" class="add" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 12px 25px; border-radius: 8px; display: inline-block; transition: transform 0.3s ease;">
      <i class="fa fa-plus-circle"></i> إضافة ساعات عمل جديدة
    </a>
  </div>
  <form id="projectForm" action="" method="post" style="display:none; margin-top:20px;">
    <?php if ($_GET['type'] == "1") {
      // نوع المعدة كان حفار 
      ?>
      <div>
        <div class="card shadow-lg" style="border: none; border-radius: 15px; overflow: hidden; margin-bottom: 30px;">
          <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px;">
            <h5 class="mb-0" style="font-size: 1.3rem; font-weight: 600;">
              <i class="fa fa-edit"></i> إضافة / تعديل حفار
            </h5>
          </div>
          <div class="card-body" style="padding: 25px; background: #fafafa;">
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
                <input type="number" value="0" style="width: 30%" id="start_seconds" name="start_seconds" min="0" max="59"
                  placeholder="ثواني" required>
                <input type="number" value="0" style="width: 30%" id="start_minutes" name="start_minutes" min="0" max="59"
                  placeholder="دقائق" required>
                <input type="number" value="0" style="width: 30%" id="start_hours" name="start_hours" placeholder="ساعات">
              </div>

              <div></div>
              <div></div>
              <div></div>
              <div></div>

              <h3 style="text-align: right;"> ساعات العمل </h3>
              <div></div>
              <div></div>
              <div></div>
              <div></div>

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
              <h3 style="text-align: right;"> ساعات الاعطال </h3>
              <div></div>
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
              <h3 style="text-align: right;"> الاعطال </h3>
              <div></div>
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

              <h3 style="text-align: right;"> ساعات عمل المشغل </h3>

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

              <button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 35px; border-radius: 25px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); display: block; margin: 20px auto 0;">
                <i class="fa fa-save"></i> حفظ الساعات
              </button>

              <style>
                button[type="submit"]:hover {
                  transform: translateY(-3px);
                  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
                }
                button[type="submit"]:active {
                  transform: translateY(-1px);
                }
              </style>

            </div>
          </div>
        </div>
      </div>
    <?php } else {
      // نوع المهدةطلع قلاب
      ?>
      <div>
        <div class="card shadow-lg" style="border: none; border-radius: 15px; overflow: hidden; margin-bottom: 30px;">
          <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px;">
            <h5 class="mb-0" style="font-size: 1.3rem; font-weight: 600;">
              <i class="fa fa-edit"></i> إضافة / تعديل قلاب
            </h5>
          </div>
          <div class="card-body" style="padding: 25px; background: #fafafa;">
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
                                            JOIN project p ON o.project_id = p.id    WHERE 1 $type_filter AND o.status = '1' AND o.project_id = '" . $_SESSION['user']['project'] . "'");



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
                <input type="hidden" value="0" style="width: 30%" id="start_seconds" name="start_seconds" min="0" max="59"
                  placeholder="ثواني" required>
                <input type="hidden" value="0" style="width: 30%" id="start_minutes" name="start_minutes" min="0" max="59"
                  placeholder="دقائق" required>
                <input type="number" value="0" id="start_hours" name="start_hours" placeholder="ساعات">
              </div>

              <div></div>
              <div></div>
              <div></div>
              <div></div>

              <h3 style="text-align: right;"> الساعات </h3>
              <div></div>
              <div></div>
              <div></div>
              <div></div>




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
              <div></div>
              <div></div>
              <div></div>

              <h3 style="text-align: right;"> ساعات الاعطال </h3>
              <div></div>
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


              <h3 style="text-align: right;"> الاعطال </h3>
              <div></div>
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


              <h3 style="text-align: right;"> ساعات عمل المشغل </h3>
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

              <button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 35px; border-radius: 25px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); display: block; margin: 20px auto 0;">
                <i class="fa fa-save"></i> حفظ الساعات
              </button>

              <style>
                button[type="submit"]:hover {
                  transform: translateY(-3px);
                  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
                }
                button[type="submit"]:active {
                  transform: translateY(-1px);
                }
              </style>

            </div>
          </div>
        </div>
      </div>
    <?php } ?>
  </form>
  <div class="card shadow-lg" style="border: none; border-radius: 15px;">
    <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;">
      <h5 class="mb-0" style="font-size: 1.4rem; font-weight: 600;">
        <i class="fa fa-list-alt"></i> قائمة ساعات العمل
      </h5>
    </div>
    <div class="card-body" style="padding: 25px; overflow-x: auto;">
      <!-- أزرار التحكم في المجموعات -->
      <div class="group-controls" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
        <button id="toggleBasic" class="btn-group-toggle" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; padding: 10px 20px; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);">
          <i class="fa fa-info-circle"></i> البيانات الأساسية
        </button>
        <button id="toggleHours" class="btn-group-toggle" style="background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; border: none; padding: 10px 20px; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);">
          <i class="fa fa-clock-o"></i> ساعات التشغيل
        </button>
        <button id="toggleFaults" class="btn-group-toggle" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border: none; padding: 10px 20px; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);">
          <i class="fa fa-exclamation-triangle"></i> الأعطال
        </button>
        <button id="toggleTotals" class="btn-group-toggle" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border: none; padding: 10px 20px; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);">
          <i class="fa fa-calculator"></i> الإجماليات
        </button>
      </div>

      <style>
        .btn-group-toggle:hover {
          transform: translateY(-3px);
          box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
        }
        .btn-group-toggle:active {
          transform: translateY(-1px);
        }
        .btn-group-toggle.active {
          opacity: 0.6;
          box-shadow: inset 0 3px 10px rgba(0, 0, 0, 0.2) !important;
        }
      </style>

      <style>
        /* تحسين تصميم الجدول */
        #projectsTable {
          border-collapse: separate;
          border-spacing: 0;
          border-radius: 10px;
          overflow: hidden;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        #projectsTable thead tr:first-child th {
          font-size: 1.1rem;
          font-weight: 700;
          padding: 15px 10px;
          border: none;
          color: white;
          text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        #projectsTable thead tr:last-child th {
          font-weight: 600;
          padding: 12px 8px;
          font-size: 0.95rem;
          border-bottom: 3px solid #ddd;
          background: #f8f9fa;
          color: #333;
        }
        
        #projectsTable tbody tr {
          transition: all 0.3s ease;
        }
        
        #projectsTable tbody tr:hover {
          background: linear-gradient(90deg, #f8f9fa 0%, #e9ecef 100%);
          transform: scale(1.01);
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        #projectsTable tbody td {
          padding: 12px 8px;
          border-bottom: 1px solid #e9ecef;
          font-size: 0.9rem;
        }
        
        /* ألوان المجموعات */
        .group-basic { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); }
        .group-hours { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); }
        .group-faults { background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); }
        .group-totals { background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); }
        .group-actions { background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); }
      </style>

      <table id="projectsTable" class="display nowrap" style="width:100%; margin-top:20px;">
        <thead>
          <!-- صف المجموعات -->
          <tr>
            <th colspan="4" class="group-basic" style="text-align:center;">
              <i class="fa fa-info-circle" style="margin-left: 5px;"></i>
              البيانات الأساسية
            </th>
            <th colspan="4" class="group-hours" style="text-align:center;">
              <i class="fa fa-clock-o" style="margin-left: 5px;"></i>
              ساعات التشغيل
            </th>
            <th colspan="2" class="group-faults" style="text-align:center;">
              <i class="fa fa-exclamation-triangle" style="margin-left: 5px;"></i>
              الأعطال
            </th>
            <th colspan="2" class="group-totals" style="text-align:center;">
              <i class="fa fa-calculator" style="margin-left: 5px;"></i>
              الإجماليات
            </th>
            <th colspan="2" class="group-actions" style="text-align:center;">
              <i class="fa fa-cogs" style="margin-left: 5px;"></i>
              التحكم
            </th>
          </tr>

          <!-- صف الأعمدة الفعلية -->
          <tr>
            <th><i class="fa fa-hashtag"></i> #</th>
            <th><i class="fa fa-truck"></i> المعدة</th>
            <th><i class="fa fa-calendar"></i> التاريخ</th>
            <th><i class="fa fa-sun-o"></i> الوردية</th>

            <th><i class="fa fa-hourglass-half"></i> الساعات</th>
            <th><i class="fa fa-cube"></i> الجردل</th>
            <th><i class="fa fa-gavel"></i> الجاكهمر</th>
            <th><i class="fa fa-plus-circle"></i> الإضافية</th>

            <th><i class="fa fa-pause-circle"></i> الاستعداد</th>
            <th><i class="fa fa-wrench"></i> الأعطال</th>

            <th><i class="fa fa-briefcase"></i> ساعات العمل</th>
            <th><i class="fa fa-chart-bar"></i> إجمالي اليوم</th>

            <th><i class="fa fa-flag"></i> الحالة</th>
            <th><i class="fa fa-cog"></i> إجراءات</th>
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
          JOIN project p ON o.project_id = p.id
          JOIN drivers d ON t.driver = d.id
          WHERE t.type LIKE '$type' 
         ";

          if ($_SESSION['user']['role'] == "6") {
            // If the user is a driver, filter by their user ID
            $user_filter = $_SESSION['user']['id'];
           $query .= " AND t.user_id = '$user_filter'  ORDER BY t.id DESC ";
}else {
  $query .= " ORDER BY t.id DESC ";
}



          $result = mysqli_query($conn, $query);
          $i = 1;
          while ($row = mysqli_fetch_assoc($result)) {

            $totalwork = $row['standby_hours'] + $row['bucket_hours'] + $row['jackhammer_hours'] + $row['extra_hours'] + $row['dependence_hours'];
            $totalall = $row['total_work_hours'] + $row['total_fault_hours'];

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
            echo "<td style='font-weight: 600;'>" . $i++ . "</td>";
            echo "<td style='font-weight: 600; color: #2980b9;'>" . $row['eq_code'] . " - " . $row['eq_name'] . "</td>";
            // echo "<td>" . $row['project_name'] . "</td>";
            // echo "<td> ... </td>";
            echo "<td>" . $row['date'] . "</td>";
            echo $row['shift'] == "D" ? "<td><span style='background: #ffeaa7; padding: 4px 12px; border-radius: 15px; font-weight: 600; color: #2d3436;'><i class='fa fa-sun-o'></i> صباحية</span></td>" : "<td><span style='background: #2d3436; padding: 4px 12px; border-radius: 15px; font-weight: 600; color: #fff;'><i class='fa fa-moon-o'></i> مسائية</span></td>";
            echo "<td style='background: #e8f5e9; font-weight: 600;'>" . $row['executed_hours'] . "</td>";
            echo "<td style='background: #e8f5e9;'>" . $row['bucket_hours'] . "</td>";
            echo "<td style='background: #e8f5e9;'>" . $row['jackhammer_hours'] . "</td>";
            echo "<td style='background: #e8f5e9;'>" . $row['extra_hours'] . "</td>";

            // echo "<td>" . $row['extra_hours_total'] . "</td>";
            echo "<td style='background: #fff3e0; font-weight: 600;'>" . $row['standby_hours'] . "</td>";
            echo "<td style='background: #fff3e0; font-weight: 600; color: #d63031;'>" . $row['total_fault_hours'] . "</td>";

            echo "<td style='background: #e3f2fd; font-weight: 700; color: #2980b9; font-size: 1.05rem;'>" . $totalwork . "</td>";
            echo "<td style='background: #ffebee; font-weight: 700; color: #c0392b; font-size: 1.05rem;'>" . $totalall . "</td>";


            // echo "<td>" . $row['dependence_hours'] . "</td>";
            // echo "<td>" . $row['total_work_hours'] . "</td>";
            // echo "<td>" . $row['work_notes'] . "</td>";
            // echo "<td>" . $row['hr_fault'] . "</td>";
            echo "<td style='text-align: center;'>" . $status . "</td>";
            echo "<td style='white-space: nowrap; text-align: center;'>
        <a href='aprovment.php?t=" . $type . "&&type=1&&id=" . $row['id'] . "' title='قبول' style='color: #27ae60; font-size: 1.1rem; margin: 0 3px; transition: all 0.3s;'> <i class='fa fa-check-circle'></i> </a>
        <a href='aprovment.php?t=" . $type . "&&type=2&&id=" . $row['id'] . "' title='رفض' style='color: #e74c3c; font-size: 1.1rem; margin: 0 3px; transition: all 0.3s;'> <i class='fa fa-times-circle'></i> </a>
        <a href='javascript:void(0)' class='editBtn' data-id='" . $row['id'] . "' title='تعديل' style='color:#3498db; font-size: 1.1rem; margin: 0 3px; transition: all 0.3s;'><i class='fa fa-edit'></i></a>
        <a href='delete_timesheet.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' title='حذف' style='color: #e74c3c; font-size: 1.1rem; margin: 0 3px; transition: all 0.3s;'><i class='fa fa-trash'></i></a>
        <a href='timesheet_details.php?id=" . $row['id'] . "' title='عرض التفاصيل' style='color: #8e44ad; font-size: 1.1rem; margin: 0 3px; transition: all 0.3s;'> <i class='fa fa-eye'></i> </a>  
        </td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
      
      <style>
        /* إصلاح مشكلة عرض الصفحة */
        body {
          display: flex;
          overflow-x: hidden; /* منع التمرير الأفقي للجسم */
        }
        
        .main {
          flex: 1;
          padding: 20px;
          overflow-x: auto;
          width: calc(100vw - 280px); /* عرض الشاشة - عرض الـ sidebar المفتوح */
          max-width: calc(100vw - 280px);
          min-width: 0; /* مهم جداً للـ flex */
          transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* عند إغلاق الـ sidebar */
        body:has(.sidebar.closed) .main {
          width: calc(100vw - 80px); /* عرض الشاشة - عرض الـ sidebar المغلق */
          max-width: calc(100vw - 80px);
        }
        
        /* للشاشات الصغيرة */
        @media (max-width: 768px) {
          .main {
            max-width: 100vw;
            width: 100vw;
            padding: 15px;
          }
        }
        
        /* تحسين أيقونات الإجراءات */
        #projectsTable tbody td a {
          display: inline-block;
          padding: 5px;
          border-radius: 50%;
          transition: all 0.3s ease;
        }
        
        #projectsTable tbody td a:hover {
          transform: scale(1.3);
          background: rgba(0, 0, 0, 0.05);
        }
        
        /* تحسين زر إضافة ساعات عمل */
        .add:hover {
          transform: translateY(-3px);
          box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .add:active {
          transform: translateY(-1px);
        }
        
        /* تحسين الجدول للشاشات الكبيرة */
        .card-body {
          overflow-x: auto;
          position: relative;
        }
        
        #projectsTable_wrapper {
          overflow-x: auto;
          width: 100%;
        }
        
        #projectsTable {
          width: 100% !important;
          max-width: 100%;
        }
        
        /* تحسين DataTables buttons */
        .dt-buttons {
          margin-bottom: 15px;
          display: flex;
          gap: 8px;
          flex-wrap: wrap;
        }
        
        /* عرض صحيح للبطاقات */
        .card {
          width: 100%;
          max-width: 100%;
        }
      </style>
    </div>
  </div>
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
     var table =  $('#projectsTable').DataTable({
        responsive: true, 
        scrollX: true,
        fixedHeader: true,
      
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
        // أزرار Toggle للمجموعات مع تأثيرات بصرية
    $('#toggleBasic').on('click', function(){ 
      table.columns([0,1,2,3]).visible(!table.column(0).visible()); 
      $(this).toggleClass('active');
    });
    $('#toggleHours').on('click', function(){ 
      table.columns([4,5,6,7]).visible(!table.column(4).visible()); 
      $(this).toggleClass('active');
    });
    $('#toggleFaults').on('click', function(){ 
      table.columns([8,9]).visible(!table.column(8).visible()); 
      $(this).toggleClass('active');
    });
    $('#toggleTotals').on('click', function(){ 
      table.columns([10,11]).visible(!table.column(10).visible()); 
      $(this).toggleClass('active');
    });
    
    // تحديث الجدول عند تغيير حجم sidebar
    const sidebarToggle = document.getElementById('toggleBtn');
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function() {
        setTimeout(function() {
          table.columns.adjust().draw();
        }, 400); // تأخير بسيط للسماح بانتهاء انيميشن الـ sidebar
      });
    }
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
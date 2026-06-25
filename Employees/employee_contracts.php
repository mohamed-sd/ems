<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}

require_once '../config.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
  die('لا يمكن تحديد الشركة الحالية');
}

$drivers_scope_sql = '1=1';
if (!$is_super_admin) {
  if (db_table_has_column($conn, 'employees', 'company_id')) {
    $drivers_scope_sql = 'd.company_id = ' . $company_id;
  } else {
    $drivers_scope_sql = "EXISTS (
      SELECT 1
      FROM users du
      WHERE du.project_id = d.project
        AND du.company_id = " . $company_id . "
    )";
  }
}

$driver_contract_scope_sql = '1=1';
if (!$is_super_admin) {
  if (db_table_has_column($conn, 'drivercontracts', 'company_id')) {
    $driver_contract_scope_sql = 'sc.company_id = ' . $company_id;
  } else {
    $driver_contract_scope_sql = "EXISTS (
      SELECT 1
      FROM project p
      JOIN users du ON du.project_id = p.id
      WHERE p.id = sc.project_id
        AND du.company_id = " . $company_id . "
    )";
  }
}

$project_scope_sql = '1=1';
if (!$is_super_admin) {
  if (db_table_has_column($conn, 'project', 'company_id')) {
    $project_scope_sql = 'p.company_id = ' . $company_id;
  } else {
    $project_scope_sql = "EXISTS (
      SELECT 1
      FROM users du
      WHERE du.project_id = p.id
        AND du.company_id = " . $company_id . "
    )";
  }
}

// التحقق من وجود معرف السائق
if (!isset($_GET['id'])) {
  header("Location: employees.php");
  exit();
}

$employee_id = intval($_GET['id']);

$driver_check_sql = "SELECT d.id FROM employees d WHERE d.id = $employee_id AND $drivers_scope_sql LIMIT 1";
$driver_check_result = mysqli_query($conn, $driver_check_sql);
if (!$driver_check_result || mysqli_num_rows($driver_check_result) === 0) {
  header('Location: employees.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إيكوبيشن | عقود السائق</title>
  <link rel="stylesheet" href="/ems/assets/css/all.min.css">
  <!-- Call bootstrap 5 -->
  <link href="/ems/assets/css/bootstrap.min.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
  <link rel="stylesheet" href="/ems/assets/css/local-fonts.css">
  <link rel="stylesheet" href="/ems/assets/css/design-tokens.css">
  <link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css">
  <!-- Unified column-groups module (this page uses insidebar, not inheader) -->
  <script src="/ems/assets/js/column-groups.js"></script>
</head>

<body class="ems-site">

  <?php include('../insidebar.php'); ?>

  <div class="main driver-contracts-main ems-unified-page-shell">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title = 'إدارة عقود السائق';
    $header_icon  = 'fas fa-file-contract';
    $header_actions = array(
        array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'عقد جديد'),
    );
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('driver_contracts', 'عقود السائقين', true) as $__xlAction) { $header_actions[] = $__xlAction; }
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <!-- فورم إضافة عقد -->
    <form id="projectForm" action="" method="post" class="allforms">

      <div class="card">
        <div class="card-header">
          <h5>
            <i class="fas fa-file-signature"></i> إضافة / تعديل عقد السائق
          </h5>
        </div>
        <div class="card-body">

          <input type="hidden" name="id" id="contract_id" value="">
          <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>" required />

          <!-- القسم 1: اختيار المشروع والعقد -->
          <div class="section-title"><span class="chip">1</span> اختيار المشروع والعقد</div>
          <br>

          <div class="form-grid">
            <div class="field md-4">
              <label>اسم المشروع <font color="red">*</font></label>
              <div class="control">
                <select name="project_id" id="project_id" required>
                  <option value="">— اختر المشروع —</option>
                  <?php
                  $projects_query = "SELECT p.id, p.name FROM project p WHERE p.status = 1 AND $project_scope_sql ORDER BY p.name ASC";
                  $projects_result = mysqli_query($conn, $projects_query);
                  if ($projects_result) { while ($project = mysqli_fetch_assoc($projects_result)) {
                    echo "<option value='" . $project['id'] . "'>" . $project['name'] . "</option>";
                  } }
                  ?>
                </select>
              </div>
            </div>

            <div class="field md-4">
              <label>عقد المشروع <font color="red">*</font></label>
              <div class="control">
                <select name="project_contract_id" id="project_contract_id" required disabled>
                  <option value="">— اختر المشروع أولاً —</option>
                </select>
              </div>
            </div>
          </div>

          <!-- عرض معلومات ساعات العقد -->
          <div id="projectHoursInfo" class="project-hours-info drivercontracts-hidden">
            <div class="project-hours-grid">
              <div class="project-hours-card">
                <strong class="project-hours-title project-hours-title-blue">
                  <i class="fas fa-clock"></i> إجمالي ساعات العقد
                </strong>
                <div class="project-hours-value" id="contractTotalHours">0</div>
                <div id="equipmentBreakdown" class="equipment-breakdown">
                  <!-- سيتم ملء التفصيل هنا -->
                </div>
              </div>
              <div class="project-hours-card">
                <strong class="project-hours-title project-hours-title-red">
                  <i class="fas fa-handshake"></i> المتعاقد عليه مع سائقين
                </strong>
                <div class="project-hours-value project-hours-value-red" id="driversContractedHours">0</div>
              </div>
              <div class="project-hours-card">
                <strong class="project-hours-title project-hours-title-green">
                  <i class="fas fa-chart-line"></i> الساعات المتبقية
                </strong>
                <div class="project-hours-value project-hours-value-green" id="remainingHours">0</div>
              </div>
            </div>
          </div>

          <hr class="hr" />

          <!-- القسم 2: إجماليات الساعات (يومياً وللعقد) -->
          <div class="section-title"><span class="chip">2</span> إجماليات الساعات (يومياً وللعقد)</div>
          <br>

          <div class="totals">
            <div class="kpi">
              <div class="v" id="kpi_month_total">0</div>
              <div class="t">الساعات اليومية المطلوبة</div>
              <input type="hidden" name="hours_monthly_target" id="hours_monthly_target" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_contract_total">0</div>
              <div class="t">إجمالي ساعات العقد</div>
              <input type="hidden" name="forecasted_contracted_hours" id="forecasted_contracted_hours" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_equip_month">0</div>
              <div class="t">معدات × ساعات لليوم</div>
            </div>
          </div>

          <div class="contract-hours-note">
            <p>
              <i class="fas fa-info-circle"></i> <strong>ملاحظة:</strong> يتم حساب الإجماليات تلقائياً بناءً على
              البيانات المدخلة في الأقسام التالية
            </p>
          </div>

          <hr class="hr" />

          <div class="section-title"><span class="chip">3</span> البيانات الأساسية للعميل والعقد</div>
          <br>

          <div class="form-grid">

            <!-- صف 1: 3 خانات -->
            <div class="field md-3 sm-6">
              <label>تاريخ توقيع العقد </label>
              <div class="control"><input name="contract_signing_date" id="contract_signing_date" type="date"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>فترة السماح بين التوقيع والتنفيذ </label>
              <div class="control"><input name="grace_period_days" id="grace_period_days" type="number" min="0"
                  placeholder="عدد الأيام"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>بداية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_start" id="actual_start" type="date"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>نهاية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_end" id="actual_end" type="date"></div>
            </div>



            <!-- خانتان فارغتان -->


            <!-- صف 2: 3 خانات -->

            <div class="field md-3 sm-6">
              <label>مدة العقد بالأيام </label>
              <div class="control"><input name="contract_duration_days" id="contract_duration_days" type="number"
                  min="0" placeholder="يُحتسب تلقائياً" readonly></div>
            </div>





            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="price_currency_contract" id="price_currency_contract">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>المبلغ المدفوع</label>
              <div class="control"><input name="paid_contract" type="text"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وقت الدفع</label>
              <div class="control">
                <select name="payment_time" id="payment_time">
                  <option value="">— اختر —</option>
                  <option value="مقدم">مقدم</option>
                  <option value=" مؤخر">مؤخر </option>

                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> الضمانات</label>
              <div class="control"><input name="guarantees" type="text"></div>
            </div>

            <div class="field md-3 sm-6">
              <label> تاريخ الدفع</label>
              <div class="control"><input name="payment_date" id="payment_date" type="date"></div>
            </div>











            <div class="field md-3 sm-6">
              <label>عدد الورديات للعقد </label>
              <div class="control"><input name="equip_shifts_contract" type="number" min="0" placeholder="مثال: 2">
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> ساعات الوردية للعقد</label>
              <div class="control"><input name="shift_contract" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الوحدات يومياً للعقد </label>
              <div class="control"><input name="equip_total_contract" type="number" placeholder=" "></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر للعقد</label>
              <div class="control"><input name="total_contract_permonth" type="number" min="0"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>إجمالي وحدات العقد </label>
              <div class="control"><input name="total_contract" type="number" placeholder=" "></div>
            </div>

            <div class="field md-3 sm-6">
              <label>مدراء الموقع </label>
              <div class="control"><input type="number" name="daily_operators" id="daily_operators" min="0"
                  placeholder="مثال: 3"></div>
            </div>



            <div class="field md-3 sm-6">
              <label>الترحيل (Transportation)</label>
              <div class="control">
                <select name="transportation" id="transportation">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>السكن (Place for Living)</label>
              <div class="control">
                <select name="place_for_living" id="place_for_living">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>
            <!-- صف 3: 3 خانات -->
            <div class="field md-3 sm-6">
              <label>الإعاشة (Accommodation)</label>
              <div class="control">
                <select name="accommodation" id="accommodation">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>الورشة (Workshop)</label>
              <div class="control">
                <select name="workshop" id="workshop">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>
            <!-- خانتان فارغتان -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>
          </div>

          <hr class="hr" />

          <!-- القسم 4: بيانات ساعات العمل المطلوبة للمعدات -->
          <div id="equipmentSections">
            <div class="section-title"><span class="chip">4</span> بيانات ساعات العمل المطلوبة <strong>للمعدات</strong>
            </div>
            <br>
            <div class="equipment-section" data-index="1">
              <div class="equipment-box">
                <h6 class="equipment-box-title">المعدات رقم 1</h6>
                <div class="form-grid">
                  <div class="field md-3 sm-6">
                    <label>نوع المعدة</label>
                    <div class="control">
                      <select name="equip_type_1" class="equip-type">
                        <option value="">— اختر —</option>
                        <option value="حفار">حفار</option>
                        <option value="قلاب">قلاب</option>
                        <option value="خرامة">خرامة</option>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>حجم المعدة (Size)</label>
                    <div class="control"><input name="equip_size_1" type="number" placeholder="مثال: 340"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>عدد المعدات</label>
                    <div class="control"><input name="equip_count_1" type="number" min="0"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label><span class="label-dot basic">■</span> المعدات الأساسية</label>
                    <div class="control"><input name="equip_count_basic_1" type="number" min="0" class="basic-input">
                    </div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label><span class="label-dot backup">■</span> المعدات الاحتياطية</label>
                    <div class="control"><input name="equip_count_backup_1" type="number" min="0" class="backup-input">
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>عدد المشغلين</label>
                    <div class="control"><input name="equip_operators_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>عدد المساعدين</label>
                    <div class="control"><input name="equip_assistants_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>عدد الورديات</label>
                    <div class="control"><input name="equip_shifts_1" type="number" min="0" placeholder="مثال: 2"></div>
                  </div>
                  <!-- أوقات الورديات -->
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                    <div class="control"><input name="shift1_start_1" type="time" placeholder="مثال: 08:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                    <div class="control"><input name="shift1_end_1" type="time" placeholder="مثال: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                    <div class="control"><input name="shift2_start_1" type="time" placeholder="مثال: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                    <div class="control"><input name="shift2_end_1" type="time" placeholder="مثال: 00:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>وحدة القياس</label>
                    <div class="control">
                      <select name="equip_unit_1" class="equip-unit">
                        <option value="">— اختر —</option>
                        <option value="ساعة">ساعة</option>
                        <option value="طن">طن</option>
                        <option value="متر طولي">متر طولي</option>
                        <option value="متر مكعب">متر مكعب</option>
                      </select>
                    </div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>ساعات الوردية</label>
                    <div class="control"><input name="shift_hours_1" type="number" min="0"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>إجمالي الوحدات يومياً</label>
                    <div class="control"><input name="equip_total_month_1" type="number" readonly
                        placeholder="يُحتسب تلقائياً"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>وحدات العمل في الشهر</label>
                    <div class="control"><input name="equip_target_per_month_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>إجمالي وحدات العقد</label>
                    <div class="control"><input name="equip_total_contract_1" type="number" readonly
                        placeholder="يُحتسب تلقائياً"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>العملة</label>
                    <div class="control">
                      <select name="equip_price_currency_1">
                        <option value="">— اختر —</option>
                        <option value="دولار">دولار</option>
                        <option value="جنيه">جنيه</option>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>السعر\للوحدة</label>
                    <div class="control"><input name="equip_price_1" type="number" min="0" step="0.01"
                        placeholder="0.00"></div>
                  </div>

                  <div class="field md-3 sm-6">

                  </div>





                  <!-- خانتان فارغتان للحفاظ على 3 خانات لكل صف -->

                  <div class="field md-3 sm-6">
                    <label>عدد المشرفين</label>
                    <div class="control"><input name="equip_supervisors_1" type="number" min="0"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>عدد الفنيين</label>
                    <div class="control"><input name="equip_technicians_1" type="number" min="0"></div>
                  </div>
                  <!-- إكمال الصف بثلاث خانات -->
                  <div class="field md-3 sm-6"></div>
                  <div class="field md-3 sm-6"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="equipment-actions-row">
            <button type="button" class="primary add-equipment-btn" id="addEquipmentBtn">
              <i class="fas fa-plus-circle"></i> إضافة مزيد من المعدات
            </button>
          </div>

          <hr class="hr" />
          <div class="section-title"><span class="chip">5</span> بيانات إضافية</div>
          <br>

          <div class="form-grid">

            <div class="field md-3 sm-6 drivercontracts-hidden">
              <label>عدد ساعات العمل اليومية <font color="red"> * مهم </font></label>
              <div class="control"><input type="number" id="daily_work_hours" name="daily_work_hours" min="0"
                  placeholder="مثال: 8" value="20"></div>
            </div>
            <!-- Orgnization Break  -->



            <div class="field md-3 sm-6">
              <label>الطرف الأول </label>
              <div class="control"><input type="text" name="first_party" id="first_party"
                  placeholder="اسم الطرف الاول ">
              </div>
            </div>



            <div class="field md-3 sm-6">
              <label>الطرف الثاني </label>
              <div class="control"><input type="text" name="second_party" id="second_party"
                  placeholder="اسم الطرف الثاني ">
              </div>
            </div>

            <div class="field md-3 sm-6"> </div>

            <div class="field md-3 sm-6">
              <label>الشاهد الأول</label>
              <div class="control"><input type="text" name="witness_one" id="witness_one"
                  placeholder="اسم الشاهد الأول">
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>الشاهد الثاني</label>
              <div class="control"><input type="text" name="witness_two" id="witness_two"
                  placeholder="اسم الشاهد الثاني">
              </div>
            </div>
          </div>


          <div class="form-actions-row">
            <button type="reset" class="btn-reset-contract">
              <i class="fas fa-eraser"></i> تفريغ الحقول
            </button>
            <button type="submit" class="primary btn-submit-contract">
              <i class="fas fa-save"></i> حفظ البيانات
            </button>
          </div>
        </div>
      </div>
    </form>
    <div class="card">
      <div class="card-header">
        <h5>
          <i class="fas fa-list-alt"></i> قائمة العقود
        </h5>
      </div>

      <!-- أزرار التحكم في المجموعات -->
      <div class="card-body group-tools-body">
        <div class="group-tools-wrap">
          <span class="group-tools-title">
            <i class="fas fa-filter"></i> عرض المجموعات:
          </span>
          <button class="btn-group-toggle active" data-group="basic" title="المعلومات الأساسية">
            <i class="fas fa-info-circle"></i> أساسية
          </button>
          <button class="btn-group-toggle active" data-group="dates" title="التواريخ والمدد">
            <i class="far fa-calendar"></i> تواريخ
          </button>
          <button class="btn-group-toggle active" data-group="hours" title="الساعات والأهداف">
            <i class="fas fa-clock"></i> ساعات
          </button>
          <button class="btn-group-toggle" data-group="parties" title="أطراف العقد">
            <i class="fas fa-users"></i> أطراف
          </button>
          <button class="btn-group-toggle" data-group="services" title="الخدمات المقدمة">
            <i class="fas fa-hands-helping"></i> خدمات
          </button>
          <button class="btn-group-toggle" data-group="operations" title="التشغيل اليومي">
            <i class="fas fa-cogs"></i> تشغيل
          </button>
          <button class="btn-group-toggle active" data-group="status" title="الحالة والإجراءات">
            <i class="fas fa-check-circle"></i> حالة
          </button>
          <button class="btn-group-toggle-all" title="إظهار/إخفاء الكل">
            <i class="fas fa-eye"></i> الكل
          </button>
        </div>
      </div>

      <div class="card-body contracts-table-body">
        <table id="projectsTable" class="display nowrap contracts-table contracts-table-nowrap no-datatable">
          <thead>
            <tr>
              <th class="group-status"><i class="fas fa-cogs"></i> الإجراءات</th>
              <!-- المعلومات الأساسية -->
              <th class="group-basic"><i class="fas fa-hashtag"></i> رقم العقد</th>
              <th class="group-basic"><i class="fas fa-project-diagram"></i> المشروع</th>
              <th class="group-basic"><i class="fas fa-file-contract"></i> عقد المشروع</th>

              <!-- التواريخ والمدد -->
              <th class="group-dates"><i class="far fa-calendar"></i> تاريخ التوقيع</th>
              <th class="group-dates"><i class="fas fa-hourglass-half"></i> مدة السماح (أيام)</th>
              <th class="group-dates"><i class="fas fa-calendar-days"></i> مدة العقد (أيام)</th>
              <th class="group-dates"><i class="fas fa-play-circle"></i> بداية التنفيذ</th>
              <th class="group-dates"><i class="fas fa-stop-circle"></i> نهاية التنفيذ</th>

              <!-- الساعات والأهداف -->
              <th class="group-hours"><i class="far fa-clock"></i> هدف ساعات شهري</th>
              <th class="group-hours"><i class="fas fa-clock"></i> إجمالي ساعات متوقعة</th>

              <!-- أطراف العقد -->
              <th class="group-parties"><i class="fas fa-user-tie"></i> الطرف الأول</th>
              <th class="group-parties"><i class="fas fa-user-check"></i> الطرف الثاني</th>
              <th class="group-parties"><i class="fas fa-eye"></i> شاهد أول</th>
              <th class="group-parties"><i class="fas fa-eye"></i> شاهد ثاني</th>

              <!-- الخدمات المقدمة -->
              <th class="group-services"><i class="fas fa-truck"></i> النقل</th>
              <th class="group-services"><i class="fas fa-bed"></i> السكن</th>
              <th class="group-services"><i class="fas fa-home"></i> مكان المعيشة</th>
              <th class="group-services"><i class="fas fa-wrench"></i> الورشة</th>

              <!-- التشغيل اليومي -->
              <th class="group-operations"><i class="fas fa-business-time"></i> ساعات العمل يومياً</th>
              <th class="group-operations"><i class="fas fa-users-cog"></i> عدد المشغلين يومياً</th>

              <!-- البيانات المالية -->
              <th class="group-basic"><i class="fas fa-money-bill-wave"></i> العملة</th>
              <th class="group-basic"><i class="fas fa-dollar-sign"></i> المبلغ المدفوع</th>
              <th class="group-basic"><i class="fas fa-clock"></i> وقت الدفع</th>
              <th class="group-basic"><i class="fas fa-shield-alt"></i> الضمانات</th>
              <th class="group-basic"><i class="fas fa-calendar-check"></i> تاريخ الدفع</th>

              <!-- الحالة والإجراءات -->
              <th class="group-status"><i class="fas fa-info-circle"></i> الحالة</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // إضافة عقد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['employee_id']) && !empty($_POST['project_id']) && !empty($_POST['project_contract_id'])) {

              $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
              $driver_id_post = intval($_POST['employee_id']);
              $project_id = intval($_POST['project_id']);
              $project_contract_id = intval($_POST['project_contract_id']);


              $contract_signing_date = mysqli_real_escape_string($conn, $_POST['contract_signing_date']);
              $grace_period_days = intval($_POST['grace_period_days']);

              // حساب مدة العقد بالأيام من تاريخ البداية والنهاية
              $actual_start = mysqli_real_escape_string($conn, $_POST['actual_start']);
              $actual_end = mysqli_real_escape_string($conn, $_POST['actual_end']);

              // حساب عدد الأيام من تاريخ البداية إلى تاريخ الانتهاء (شامل يوم البداية ويوم النهاية)
              if (!empty($actual_start) && !empty($actual_end)) {
                $start_date = new DateTime($actual_start);
                $end_date = new DateTime($actual_end);
                $interval = $start_date->diff($end_date);
                // توحيد المنطق مع الواجهة: الفرق الفعلي بدون إضافة يوم إضافي
                $contract_duration_days = $interval->days;
              } else {
                $contract_duration_days = 0;
              }

              $transportation = mysqli_real_escape_string($conn, $_POST['transportation']);
              $accommodation = mysqli_real_escape_string($conn, $_POST['accommodation']);
              $place_for_living = mysqli_real_escape_string($conn, $_POST['place_for_living']);
              $workshop = mysqli_real_escape_string($conn, $_POST['workshop']);

              $hours_monthly_target = floatval($_POST['hours_monthly_target']);
              $forecasted_contracted_hours = floatval($_POST['forecasted_contracted_hours']);

              $daily_work_hours = floatval($_POST['daily_work_hours']);
              $daily_operators = intval($_POST['daily_operators']);
              $first_party = mysqli_real_escape_string($conn, $_POST['first_party']);
              $second_party = mysqli_real_escape_string($conn, $_POST['second_party']);
              $witness_one = mysqli_real_escape_string($conn, $_POST['witness_one']);
              $witness_two = mysqli_real_escape_string($conn, $_POST['witness_two']);

              // الحقول المالية الجديدة
              $price_currency_contract = isset($_POST['price_currency_contract']) ? mysqli_real_escape_string($conn, $_POST['price_currency_contract']) : '';
              $paid_contract = isset($_POST['paid_contract']) ? mysqli_real_escape_string($conn, $_POST['paid_contract']) : '';
              $payment_time = isset($_POST['payment_time']) ? mysqli_real_escape_string($conn, $_POST['payment_time']) : '';
              $guarantees = isset($_POST['guarantees']) ? mysqli_real_escape_string($conn, $_POST['guarantees']) : '';
              $payment_date = isset($_POST['payment_date']) ? mysqli_real_escape_string($conn, $_POST['payment_date']) : '';

              // الحقول الإضافية للعقد
              $equip_shifts_contract = isset($_POST['equip_shifts_contract']) ? intval($_POST['equip_shifts_contract']) : 0;
              $shift_contract = isset($_POST['shift_contract']) ? intval($_POST['shift_contract']) : 0;
              $equip_total_contract_daily = isset($_POST['equip_total_contract']) ? intval($_POST['equip_total_contract']) : 0;
              $total_contract_permonth = isset($_POST['total_contract_permonth']) ? intval($_POST['total_contract_permonth']) : 0;
              $total_contract_units = isset($_POST['total_contract']) ? intval($_POST['total_contract']) : 0;


              if ($driver_id_post !== $employee_id) {
                die('بيانات السائق غير متطابقة');
              }

              if ($id > 0) {
                // تعديل
                $sql = "UPDATE drivercontracts sc SET
            project_id='$project_id',
            project_contract_id='$project_contract_id',
            contract_signing_date='$contract_signing_date',
            grace_period_days='$grace_period_days',
            contract_duration_days='$contract_duration_days',
            equip_shifts_contract='$equip_shifts_contract',
            shift_contract='$shift_contract',
            equip_total_contract_daily='$equip_total_contract_daily',
            total_contract_permonth='$total_contract_permonth',
            total_contract_units='$total_contract_units',
            actual_start='$actual_start',
            actual_end='$actual_end',
            transportation='$transportation',
            accommodation='$accommodation',
            place_for_living='$place_for_living',
            workshop='$workshop',
            hours_monthly_target='$hours_monthly_target',
            forecasted_contracted_hours='$forecasted_contracted_hours',
            daily_work_hours='$daily_work_hours',
            daily_operators='$daily_operators',
            first_party='$first_party',
            second_party='$second_party',
            witness_one='$witness_one',
            witness_two='$witness_two',
            price_currency_contract='$price_currency_contract',
            paid_contract='$paid_contract',
            payment_time='$payment_time',
            guarantees='$guarantees',
            payment_date='$payment_date'
          WHERE sc.id=$id AND sc.employee_id=$employee_id AND $driver_contract_scope_sql";
              } else {
                // إضافة
                $insert_columns = "employee_id, project_id, project_contract_id, contract_signing_date, grace_period_days, contract_duration_days,
            equip_shifts_contract, shift_contract, equip_total_contract_daily, total_contract_permonth, total_contract_units,
            actual_start, actual_end, transportation, accommodation, place_for_living, workshop,
            hours_monthly_target, forecasted_contracted_hours,
            daily_work_hours, daily_operators, first_party, second_party, witness_one, witness_two,
            price_currency_contract, paid_contract, payment_time, guarantees, payment_date";

                $insert_values = "'$driver_id_post', '$project_id', '$project_contract_id', '$contract_signing_date', '$grace_period_days', '$contract_duration_days',
            '$equip_shifts_contract', '$shift_contract', '$equip_total_contract_daily', '$total_contract_permonth', '$total_contract_units',
            '$actual_start','$actual_end', '$transportation','$accommodation','$place_for_living','$workshop',
            '$hours_monthly_target','$forecasted_contracted_hours',
            '$daily_work_hours','$daily_operators','$first_party','$second_party','$witness_one','$witness_two',
            '$price_currency_contract','$paid_contract','$payment_time','$guarantees','$payment_date'";

                if (!$is_super_admin && db_table_has_column($conn, 'drivercontracts', 'company_id')) {
                  $insert_columns .= ', company_id';
                  $insert_values .= ', ' . $company_id;
                }

                $sql = "INSERT INTO drivercontracts (
            $insert_columns
          ) VALUES (
            $insert_values
          )";
              }
              $result = mysqli_query($conn, $sql);

              if ($result) {
                // الحصول على معرف العقد المُضاف حديثاً أو معرف العقد المُحدّث
                if ($id > 0) {
                  $contract_id = $id;
                } else {
                  $contract_id = mysqli_insert_id($conn);
                }

                // جمع بيانات المعدات من الفورم
                $equipment_array = [];
                $i = 1;
                // البحث عن أكبر index موجود
                $max_index = 0;
                foreach ($_POST as $key => $value) {
                  if (preg_match('/equip_type_(\d+)/', $key, $matches)) {
                    $max_index = max($max_index, (int) $matches[1]);
                  }
                }

                // جمع البيانات من جميع الأقسام
                for ($i = 1; $i <= $max_index; $i++) {
                  if (isset($_POST["equip_type_$i"]) && !empty($_POST["equip_type_$i"])) {
                    $equipment_array[] = [
                      'equip_type' => mysqli_real_escape_string($conn, $_POST["equip_type_$i"]),
                      'equip_size' => isset($_POST["equip_size_$i"]) ? intval($_POST["equip_size_$i"]) : 0,
                      'equip_count' => isset($_POST["equip_count_$i"]) ? intval($_POST["equip_count_$i"]) : 0,
                      'equip_count_basic' => isset($_POST["equip_count_basic_$i"]) ? intval($_POST["equip_count_basic_$i"]) : 0,
                      'equip_count_backup' => isset($_POST["equip_count_backup_$i"]) ? intval($_POST["equip_count_backup_$i"]) : 0,
                      'equip_shifts' => isset($_POST["equip_shifts_$i"]) ? intval($_POST["equip_shifts_$i"]) : 0,
                      'equip_unit' => isset($_POST["equip_unit_$i"]) ? mysqli_real_escape_string($conn, $_POST["equip_unit_$i"]) : '',
                      'shift1_start' => isset($_POST["shift1_start_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift1_start_$i"]) : '',
                      'shift1_end' => isset($_POST["shift1_end_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift1_end_$i"]) : '',
                      'shift2_start' => isset($_POST["shift2_start_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift2_start_$i"]) : '',
                      'shift2_end' => isset($_POST["shift2_end_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift2_end_$i"]) : '',
                      'shift_hours' => isset($_POST["shift_hours_$i"]) ? floatval($_POST["shift_hours_$i"]) : 0,
                      'equip_total_month' => isset($_POST["equip_total_month_$i"]) ? floatval($_POST["equip_total_month_$i"]) : 0,
                      'equip_monthly_target' => isset($_POST["equip_target_per_month_$i"]) ? floatval($_POST["equip_target_per_month_$i"]) : 0,
                      'equip_total_contract' => isset($_POST["equip_total_contract_$i"]) ? floatval($_POST["equip_total_contract_$i"]) : 0,
                      'equip_price' => isset($_POST["equip_price_$i"]) ? floatval($_POST["equip_price_$i"]) : 0,
                      'equip_price_currency' => isset($_POST["equip_price_currency_$i"]) ? mysqli_real_escape_string($conn, $_POST["equip_price_currency_$i"]) : '',
                      'equip_operators' => isset($_POST["equip_operators_$i"]) ? intval($_POST["equip_operators_$i"]) : 0,
                      'equip_supervisors' => isset($_POST["equip_supervisors_$i"]) ? intval($_POST["equip_supervisors_$i"]) : 0,
                      'equip_technicians' => isset($_POST["equip_technicians_$i"]) ? intval($_POST["equip_technicians_$i"]) : 0,
                      'equip_assistants' => isset($_POST["equip_assistants_$i"]) ? intval($_POST["equip_assistants_$i"]) : 0
                    ];
                  }
                }

                // إضافة بيانات المعدات الجديدة
                if (!empty($equipment_array)) {
                  // حذف المعدات القديمة أولاً
                  $delete_sql = "DELETE dce
                                 FROM drivercontractequipments dce
                                 JOIN drivercontracts sc ON sc.id = dce.contract_id
                                 WHERE dce.contract_id = $contract_id
                                   AND sc.employee_id = $employee_id
                                   AND $driver_contract_scope_sql";
                  mysqli_query($conn, $delete_sql);

                  // إضافة المعدات الجديدة
                  foreach ($equipment_array as $equip) {
                    $insert_equip_sql = "INSERT INTO drivercontractequipments (
                      contract_id, equip_type, equip_size, equip_count, equip_count_basic, equip_count_backup, equip_shifts, equip_unit,
                      shift1_start, shift1_end, shift2_start, shift2_end, shift_hours,
                      equip_total_month, equip_monthly_target, equip_total_contract,
                      equip_price, equip_price_currency, equip_operators, equip_supervisors,
                      equip_technicians, equip_assistants
                    ) VALUES (
                      $contract_id, '{$equip['equip_type']}', {$equip['equip_size']}, {$equip['equip_count']},
                      {$equip['equip_count_basic']}, {$equip['equip_count_backup']},
                      {$equip['equip_shifts']}, '{$equip['equip_unit']}', '{$equip['shift1_start']}',
                      '{$equip['shift1_end']}', '{$equip['shift2_start']}', '{$equip['shift2_end']}',
                      {$equip['shift_hours']}, {$equip['equip_total_month']}, {$equip['equip_monthly_target']},
                      {$equip['equip_total_contract']}, {$equip['equip_price']}, '{$equip['equip_price_currency']}',
                      {$equip['equip_operators']}, {$equip['equip_supervisors']}, {$equip['equip_technicians']},
                      {$equip['equip_assistants']}
                    )";
                    mysqli_query($conn, $insert_equip_sql);
                  }
                }
              }

              echo "<script>window.location.href='employee_contracts.php?id=$employee_id';</script>";
              exit;
            }

            // جلب العقود للسائق
            $query = "SELECT sc.*,
                      op.name AS project_name
                      FROM drivercontracts sc
                      LEFT JOIN project op ON sc.project_id = op.id
                      WHERE sc.employee_id = $employee_id AND $driver_contract_scope_sql
                      ORDER BY sc.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;


            if ($result) { while ($row = mysqli_fetch_assoc($result)) {

              // عرض حالة العقد من status
              $contractStatus = isset($row['status']) ? $row['status'] : 1;
              $statusColor = 'green';
              $statusText = 'ساري';
              if ($contractStatus == 1) {
                $statusColor = 'green';
                $statusText = 'ساري';
              } else {
                $statusColor = 'red';
                $statusText = 'غير ساري';
              }
              $status = "<font color='" . $statusColor . "'>" . $statusText . "</font>";

              echo "<tr>";

              $actions_html = "<div class='action-btns'>
                        <a href='javascript:void(0)' class='editBtn action-btn edit' title='تعديل'
             data-id='" . $row['id'] . "'
             data-project_id='" . $row['project_id'] . "'
             data-project_contract_id='" . (isset($row['project_contract_id']) ? $row['project_contract_id'] : '') . "'
             data-contract_signing_date='" . $row['contract_signing_date'] . "'
             data-grace_period_days='" . $row['grace_period_days'] . "'
             data-contract_duration_days='" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "'
             data-actual_start='" . $row['actual_start'] . "'
             data-actual_end='" . $row['actual_end'] . "'
             data-hours_monthly_target='" . $row['hours_monthly_target'] . "'
             daily_work_hours ='" . $row['daily_work_hours'] . "'
              daily_operators ='" . $row['daily_operators'] . "'
               first_party ='" . $row['first_party'] . "'
                second_party ='" . $row['second_party'] . "'
                 witness_one ='" . $row['witness_one'] . "'
                  witness_two ='" . $row['witness_two'] . "'
                  transportation ='" . $row['transportation'] . "'
                  accommodation ='" . $row['accommodation'] . "'
                  place_for_living ='" . $row['place_for_living'] . "'
                  workshop ='" . $row['workshop'] . "'
                  equip_shifts_contract ='" . (isset($row['equip_shifts_contract']) ? $row['equip_shifts_contract'] : 0) . "'
                  shift_contract ='" . (isset($row['shift_contract']) ? $row['shift_contract'] : 0) . "'
                  equip_total_contract_daily ='" . (isset($row['equip_total_contract_daily']) ? $row['equip_total_contract_daily'] : 0) . "'
                  total_contract_permonth ='" . (isset($row['total_contract_permonth']) ? $row['total_contract_permonth'] : 0) . "'
                  total_contract_units ='" . (isset($row['total_contract_units']) ? $row['total_contract_units'] : 0) . "'
                  price_currency_contract ='" . (isset($row['price_currency_contract']) ? $row['price_currency_contract'] : '') . "'
                  paid_contract ='" . (isset($row['paid_contract']) ? $row['paid_contract'] : '') . "'
                  payment_time ='" . (isset($row['payment_time']) ? $row['payment_time'] : '') . "'
                  guarantees ='" . (isset($row['guarantees']) ? $row['guarantees'] : '') . "'
                  payment_date ='" . (isset($row['payment_date']) ? $row['payment_date'] : '') . "'
             data-forecasted_contracted_hours='" . $row['forecasted_contracted_hours'] . "'><i class='fas fa-edit'></i></a>
                        <a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' class='action-btn delete' title='حذف'><i class='fas fa-trash-alt'></i></a>
                        <a href='employee_contracts_details.php?id=" . $row['id'] . "' class='action-btn view' title='عرض التفاصيل'><i class='fas fa-eye'></i></a>
                      </div>";

              echo "<td class='group-status'>" . $actions_html . "</td>";

              // المعلومات الأساسية
              echo "<td class='group-basic'>" . $row['id'] . "</td>";
              echo "<td class='group-basic'>" . (isset($row['project_name']) ? $row['project_name'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['project_contract_id']) ? 'عقد #' . $row['project_contract_id'] : '-') . "</td>";

              // التواريخ والمدد
              echo "<td class='group-dates'>" . $row['contract_signing_date'] . "</td>";
              echo "<td class='group-dates'>" . (isset($row['grace_period_days']) ? $row['grace_period_days'] : 0) . "</td>";
              echo "<td class='group-dates'>" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "</td>";
              echo "<td class='group-dates'>" . $row['actual_start'] . "</td>";
              echo "<td class='group-dates'>" . $row['actual_end'] . "</td>";

              // الساعات والأهداف
              echo "<td class='group-hours'>" . $row['hours_monthly_target'] . "</td>";
              echo "<td class='group-hours'>" . $row['forecasted_contracted_hours'] . "</td>";

              // أطراف العقد
              echo "<td class='group-parties'>" . (isset($row['first_party']) ? $row['first_party'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['second_party']) ? $row['second_party'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['witness_one']) ? $row['witness_one'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['witness_two']) ? $row['witness_two'] : '-') . "</td>";

              // الخدمات المقدمة
              $transportationText = isset($row['transportation']) && $row['transportation'] ? $row['transportation'] : '-';
              $accommodationText = isset($row['accommodation']) && $row['accommodation'] ? $row['accommodation'] : '-';
              $place_for_livingText = isset($row['place_for_living']) && $row['place_for_living'] ? $row['place_for_living'] : '-';
              $workshopText = isset($row['workshop']) && $row['workshop'] ? $row['workshop'] : '-';

              echo "<td class='group-services'>" . $transportationText . "</td>";
              echo "<td class='group-services'>" . $accommodationText . "</td>";
              echo "<td class='group-services'>" . $place_for_livingText . "</td>";
              echo "<td class='group-services'>" . $workshopText . "</td>";

              // التشغيل اليومي
              echo "<td class='group-operations'>" . (isset($row['daily_work_hours']) ? $row['daily_work_hours'] : '-') . "</td>";
              echo "<td class='group-operations'>" . (isset($row['daily_operators']) ? $row['daily_operators'] : '-') . "</td>";

              // البيانات المالية
              echo "<td class='group-basic'>" . (isset($row['price_currency_contract']) && $row['price_currency_contract'] ? $row['price_currency_contract'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['paid_contract']) && $row['paid_contract'] ? $row['paid_contract'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['payment_time']) && $row['payment_time'] ? $row['payment_time'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['guarantees']) && $row['guarantees'] ? $row['guarantees'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['payment_date']) && $row['payment_date'] ? $row['payment_date'] : '-') . "</td>";

              // الحالة والإجراءات
              echo "<td class='group-status'>" . $status . "</td>";
              echo "</tr>";
            } }
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

  <style>
    .driver-contracts-main .contracts-table-body {
      overflow-x: auto;
    }

    #projectsTable.contracts-table-nowrap,
    #projectsTable.contracts-table-nowrap th,
    #projectsTable.contracts-table-nowrap td {
      white-space: nowrap;
    }

    #projectsTable .action-btns {
      display: flex;
      gap: 6px;
      justify-content: center;
      flex-wrap: nowrap;
      white-space: nowrap;
    }

    #projectsTable .action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      text-decoration: none;
      border: none;
      cursor: pointer;
      font-size: .85rem;
      transition: all .2s ease;
    }

    #projectsTable .action-btn.view { background: rgba(232, 184, 0, .18); color: #9a7b00; }
    #projectsTable .action-btn.edit { background: rgba(12, 28, 62, .08); color: #0c1c3e; }
    #projectsTable .action-btn.delete { background: rgba(220, 38, 38, .12); color: #b91c1c; }

    #projectsTable .action-btn.view:hover { background: #e8b800; color: #0c1c3e; transform: translateY(-2px); }
    #projectsTable .action-btn.edit:hover { background: #0c1c3e; color: #fff; transform: translateY(-2px); }
    #projectsTable .action-btn.delete:hover { background: #dc2626; color: #fff; transform: translateY(-2px); }
  </style>


  <script>
    (function () {
      // تشغيل DataTable بالعربية


      $(document).ready(function () {
        if ($.fn.dataTable.isDataTable('#projectsTable')) {
          return; // already initialised elsewhere — avoid "Cannot reinitialise"
        }
        $('#projectsTable').DataTable({
          dom: 'Bfrtip', // Buttons + Search + Pagination
          scrollX: true,
          autoWidth: false,
          buttons: [
            { extend: 'copy', text: 'نسخ' },
            { extend: 'excel', text: 'تصدير Excel' },
            { extend: 'csv', text: 'تصدير CSV' },
            { extend: 'pdf', text: 'تصدير PDF' },
            { extend: 'print', text: 'طباعة' }
          ],
          "language": {
            "url": "/ems/assets/i18n/datatables/ar.json"
          }
        });
      });


      // التحكم في إظهار وإخفاء الفورم
      const toggleContractFormBtn = document.getElementById('toggleForm');
      const contractForm = document.getElementById('projectForm');

      toggleContractFormBtn.addEventListener('click', function () {
        contractForm.classList.toggle('allforms-visible');
      });
    })();

  </script>

  <script>
    const $el = (sel) => document.querySelector(sel);
    let equipmentIndex = 1;

    const fields = {
      contractDays: $el('#contract_duration_days'),
      actualStart: $el('#actual_start'),
      actualEnd: $el('#actual_end'),
      kpiMonthTotal: $el('#kpi_month_total'),
      kpiContractTotal: $el('#kpi_contract_total'),
      kpiEquipMonth: $el('#kpi_equip_month'),
      hoursMonthlyTarget: $el('#hours_monthly_target'),
      forecastedContractedHours: $el('#forecasted_contracted_hours'),
    };

    function num(v) {
      const n = parseFloat(v);
      return isFinite(n) ? n : 0;
    }

    function fmt(n) {
      return new Intl.NumberFormat('ar-EG').format(Math.max(0, Math.round(n)));
    }

    // حساب مدة العقد بالأيام من التاريخين
    function calculateDaysFromDates() {
      const startDate = fields.actualStart.value;
      const endDate = fields.actualEnd.value;

      if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        fields.contractDays.value = diffDays;
      } else {
        fields.contractDays.value = '';
      }

      // تحديث الإجماليات مباشرة عند تغيير التواريخ
      recalc();
    }

    // تحديث حساب الأيام عند تغيير التواريخ
    fields.actualStart.addEventListener('change', calculateDaysFromDates);
    fields.actualEnd.addEventListener('change', calculateDaysFromDates);

    // إضافة قسم معدات جديد
    function addEquipmentSection() {
      equipmentIndex++;
      const newSection = document.createElement('div');
      newSection.className = 'equipment-section';
      newSection.setAttribute('data-index', equipmentIndex);
      newSection.innerHTML = `
        <div class="equipment-box">
          <div class="equipment-box-head">
            <h6 class="equipment-box-title">المعدات رقم ${equipmentIndex}</h6>
            <button type="button" class="removeEquipmentBtn equipment-remove-btn" data-index="${equipmentIndex}">
              <i class="fa fa-trash"></i> حذف
            </button>
          </div>
          <div class="form-grid">
            <div class="field md-3 sm-6">
              <label>نوع المعدة</label>
              <div class="control">
                <select name="equip_type_${equipmentIndex}" class="equip-type">
                  <option value="">— اختر —</option>
                  <option value="حفار">حفار</option>
                  <option value="قلاب">قلاب</option>
                  <option value="خرامة">خرامة</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>حجم المعدة (Size)</label>
              <div class="control"><input name="equip_size_${equipmentIndex}" type="number" placeholder="مثال: 340"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المعدات</label>
              <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><span class="label-dot basic">■</span> المعدات الأساسية</label>
              <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" class="basic-input"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><span class="label-dot backup">■</span> المعدات الاحتياطية</label>
              <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" class="backup-input"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد المشغلين</label>
              <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المساعدين</label>
              <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد الورديات</label>
              <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2"></div>
            </div>

            <!-- أوقات الورديات -->
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
              <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" placeholder="مثال: 08:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
              <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" placeholder="مثال: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
              <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" placeholder="مثال: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
              <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" placeholder="مثال: 00:00"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وحدة القياس</label>
              <div class="control">
                <select name="equip_unit_${equipmentIndex}" class="equip-unit">
                  <option value="">— اختر —</option>
                  <option value="ساعة">ساعة</option>
                  <option value="طن">طن</option>
                  <option value="متر طولي">متر طولي</option>
                  <option value="متر مكعب">متر مكعب</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>ساعات الوردية</label>
              <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الساعات يومياً</label>
              <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر</label>
              <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>إجمالي ساعات العقد</label>
              <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="equip_price_currency_${equipmentIndex}">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>السعر</label>
              <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00"></div>
            </div>
            <div class="field md-3 sm-6">
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المشرفين</label>
              <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد الفنيين</label>
              <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0"></div>
            </div>
          </div>
        </div>
      `;
      document.getElementById('equipmentSections').appendChild(newSection);

      // إضافة event listeners للحقول الجديدة
      newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));

      // إضافة event listener لزر الحذف
      newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
        newSection.remove();
        recalc();
      });
    }

    function recalc() {
      const days = num(fields.contractDays.value);

      // حساب إجمالي المعدات
      let totalEquipMonth = 0;
      let totalEquipContract = 0;

      // حساب كل قسم معدات
      document.querySelectorAll('.equipment-section').forEach(section => {
        const index = section.getAttribute('data-index');
        const countInput = section.querySelector(`input[name="equip_count_${index}"]`);
        const targetInput = section.querySelector(`input[name="shift_hours_${index}"]`);
        const monthInput = section.querySelector(`input[name="equip_total_month_${index}"]`);
        const contractInput = section.querySelector(`input[name="equip_total_contract_${index}"]`);

        if (countInput && targetInput) {
          const count = num(countInput.value);
          const target = num(targetInput.value);
          const sectionMonth = count * target;
          // حساب إجمالي الساعات على أساس الأيام بدلاً من الشهور
          // نفترض أن الـ target هو الساعات اليومية للمعدة
          const sectionContract = sectionMonth * days;

          monthInput.value = sectionMonth;
          contractInput.value = sectionContract;

          totalEquipMonth += sectionMonth;
          totalEquipContract += sectionContract;
        }
      });

      const monthTotal = totalEquipMonth;
      const contractTotal = totalEquipContract;

      fields.kpiEquipMonth.textContent = fmt(totalEquipMonth);
      fields.kpiMonthTotal.textContent = fmt(monthTotal);
      fields.kpiContractTotal.textContent = fmt(contractTotal);

      fields.hoursMonthlyTarget.value = monthTotal;
      fields.forecastedContractedHours.value = contractTotal;
    }

    // تشغيل الحسبة عند تغيير أي مدخل
    document.addEventListener('input', function (e) {
      if (e.target.closest('#projectForm')) {
        recalc();
      }
    });

    // زر إضافة المعدات
    document.getElementById('addEquipmentBtn').addEventListener('click', function (e) {
      e.preventDefault();
      addEquipmentSection();
    });

    // جلب الفورم
    const contractForm = document.getElementById('projectForm');
    if (contractForm) {
      contractForm.addEventListener('reset', () => setTimeout(recalc, 0));
    }

    // أول تشغيل
    recalc();

    // جلب عقود المشروع عند تغيير المشروع
    $('#project_id').on('change', function () {
      const projectId = $(this).val();
      $('#project_contract_id').prop('disabled', true).html('<option value="">— جاري التحميل... —</option>');
      $('#projectHoursInfo').fadeOut();

      if (projectId) {
        $.ajax({
          url: 'get_mine_contracts.php',
          type: 'POST',
          data: { project_id: projectId },
          dataType: 'json',
          success: function (response) {
            if (response.success && response.contracts.length > 0) {
              let options = '<option value="">— اختر العقد —</option>';
              response.contracts.forEach(function (contract) {
                options += `<option value="${contract.id}">${contract.display_name}</option>`;
              });
              $('#project_contract_id').html(options).prop('disabled', false);
            } else {
              $('#project_contract_id').html('<option value="">— لا توجد عقود لهذا المشروع —</option>').prop('disabled', true);
            }
          },
          error: function () {
            $('#project_contract_id').html('<option value="">— خطأ في التحميل —</option>').prop('disabled', true);
          }
        });
      } else {
        $('#project_contract_id').html('<option value="">— اختر المشروع أولاً —</option>').prop('disabled', true);
      }
    });

    // جلب بيانات ساعات العقد عند تغيير العقد
    $('#project_contract_id').on('change', function () {
      const contractId = $(this).val();
      const driverContractId = $('#contract_id').val();
      if (contractId) {
        $.ajax({
          url: 'get_project_hours.php',
          type: 'POST',
          data: {
            project_contract_id: contractId,
            driver_contract_id: driverContractId || 0
          },
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              $('#contractTotalHours').text(new Intl.NumberFormat('ar-EG').format(response.contract_total_hours));
              $('#driversContractedHours').text(new Intl.NumberFormat('ar-EG').format(response.drivers_contracted_hours));
              $('#remainingHours').text(new Intl.NumberFormat('ar-EG').format(response.remaining_hours));

              // عرض تفصيل المعدات
              var breakdownDiv = $('#equipmentBreakdown');
              breakdownDiv.empty();

              if (response.equipment_breakdown && response.equipment_breakdown.length > 0) {
                var breakdownHtml = '<div class="breakdown-wrap"><strong class="breakdown-title">تفصيل الساعات:</strong>';

                response.equipment_breakdown.forEach(function (item) {
                  var percentage = ((item.hours / response.contract_total_hours) * 100).toFixed(1);
                  breakdownHtml += '<div class="breakdown-row">';
                  breakdownHtml += '<span><i class="fas fa-tools breakdown-icon"></i>' + item.type + '</span>';
                  breakdownHtml += '<span class="breakdown-hours">' + new Intl.NumberFormat('ar-EG').format(item.hours) + ' ساعة (' + percentage + '%)</span>';
                  breakdownHtml += '</div>';
                });

                breakdownHtml += '</div>';
                breakdownDiv.html(breakdownHtml);
              } else {
                breakdownDiv.html('<span class="breakdown-empty">لا توجد معدات مسجلة لهذا العقد</span>');
              }

              $('#projectHoursInfo').fadeIn();
            } else {
              $('#projectHoursInfo').fadeOut();
            }
          },
          error: function () {
            $('#projectHoursInfo').fadeOut();
          }
        });
      } else {
        $('#projectHoursInfo').fadeOut();
      }
    });

    // تعبئة الفورم عند التعديل
    $(document).on("click", ".editBtn", function () {
      $("#projectForm").addClass('allforms-visible');
      $("#contract_id").val($(this).data("id"));

      // تحميل المشروع والعقد
      const projectId = $(this).data("project_id");
      const projectContractId = $(this).data("project_contract_id");

      $("#project_id").val(projectId);

      // تحميل عقود المشروع
      if (projectId) {
        $.ajax({
          url: 'get_mine_contracts.php',
          type: 'POST',
          data: { project_id: projectId },
          dataType: 'json',
          success: function (response) {
            if (response.success && response.contracts.length > 0) {
              let options = '<option value="">— اختر العقد —</option>';
              response.contracts.forEach(function (contract) {
                const selected = contract.id == projectContractId ? 'selected' : '';
                options += `<option value="${contract.id}" ${selected}>${contract.display_name}</option>`;
              });
              $('#project_contract_id').html(options).prop('disabled', false);

              // تفعيل تحميل بيانات الساعات
              if (projectContractId) {
                $('#project_contract_id').trigger('change');
              }
            }
          }
        });
      }

      $("#projectForm [name='contract_signing_date']").val($(this).data("contract_signing_date"));
      $("#projectForm [name='grace_period_days']").val($(this).data("grace_period_days"));
      $("#projectForm [name='contract_duration_days']").val($(this).data("contract_duration_days"));
      $("#projectForm [name='actual_start']").val($(this).data("actual_start"));
      $("#projectForm [name='actual_end']").val($(this).data("actual_end"));

      // اعتمد التاريخين كمصدر الحقيقة قبل أي إعادة حساب
      calculateDaysFromDates();


      $("#projectForm [name='hours_monthly_target']").val($(this).data("hours_monthly_target"));
      $("#projectForm [name='forecasted_contracted_hours']").val($(this).data("forecasted_contracted_hours"));

      $("#projectForm [name='daily_work_hours']").val($(this).attr("daily_work_hours"));

      $("#projectForm [name='daily_operators']").val($(this).attr("daily_operators"));

      // تحميل الحقول الإضافية للعقد
      $("#projectForm [name='equip_shifts_contract']").val($(this).attr("equip_shifts_contract"));
      $("#projectForm [name='shift_contract']").val($(this).attr("shift_contract"));
      $("#projectForm [name='equip_total_contract']").val($(this).attr("equip_total_contract_daily"));
      $("#projectForm [name='total_contract_permonth']").val($(this).attr("total_contract_permonth"));
      $("#projectForm [name='total_contract']").val($(this).attr("total_contract_units"));

      $("#projectForm [name='first_party']").val($(this).attr("first_party"));
      $("#projectForm [name='second_party']").val($(this).attr("second_party"));
      $("#projectForm [name='witness_one']").val($(this).attr("witness_one"));
      $("#projectForm [name='witness_two']").val($(this).attr("witness_two"));
      $("#projectForm [name='transportation']").val($(this).attr("transportation"));
      $("#projectForm [name='accommodation']").val($(this).attr("accommodation"));
      $("#projectForm [name='place_for_living']").val($(this).attr("place_for_living"));
      $("#projectForm [name='workshop']").val($(this).attr("workshop"));

      // البيانات المالية الجديدة
      $("#projectForm [name='price_currency_contract']").val($(this).attr("price_currency_contract"));
      $("#projectForm [name='paid_contract']").val($(this).attr("paid_contract"));
      $("#projectForm [name='payment_time']").val($(this).attr("payment_time"));
      $("#projectForm [name='guarantees']").val($(this).attr("guarantees"));
      $("#projectForm [name='payment_date']").val($(this).attr("payment_date"));

      // تحميل المعدات الخاصة بالعقد
      const contractId = $(this).data("id");
      $.ajax({
        url: 'get_employee_contract_equipments.php',
        type: 'POST',
        data: { contract_id: contractId },
        dataType: 'json',
        success: function (equipments) {
          // مسح الأقسام القديمة ما عدا الأول
          $('#equipmentSections .equipment-section').not(':first').remove();
          equipmentIndex = 1;

          // تحميل المعدات
          if (equipments.length > 0) {
            equipments.forEach(function (equip, index) {
              const sectionIndex = index + 1;

              if (sectionIndex === 1) {
                // تحديث القسم الأول
                $(`select[name="equip_type_1"]`).val(equip.equip_type);
                $(`input[name="equip_size_1"]`).val(equip.equip_size);
                $(`input[name="equip_count_1"]`).val(equip.equip_count);
                $(`input[name="equip_count_basic_1"]`).val(equip.equip_count_basic || 0);
                $(`input[name="equip_count_backup_1"]`).val(equip.equip_count_backup || 0);
                $(`input[name="equip_shifts_1"]`).val(equip.equip_shifts);
                $(`select[name="equip_unit_1"]`).val(equip.equip_unit);
                $(`input[name="shift1_start_1"]`).val(equip.shift1_start);
                $(`input[name="shift1_end_1"]`).val(equip.shift1_end);
                $(`input[name="shift2_start_1"]`).val(equip.shift2_start);
                $(`input[name="shift2_end_1"]`).val(equip.shift2_end);
                $(`input[name="shift_hours_1"]`).val(equip.shift_hours);
                $(`input[name="equip_total_month_1"]`).val(equip.equip_total_month);
                $(`input[name="equip_total_contract_1"]`).val(equip.equip_total_contract);
                $(`input[name="equip_price_1"]`).val(equip.equip_price);
                $(`select[name="equip_price_currency_1"]`).val(equip.equip_price_currency);
                $(`input[name="equip_operators_1"]`).val(equip.equip_operators);
                $(`input[name="equip_supervisors_1"]`).val(equip.equip_supervisors);
                $(`input[name="equip_technicians_1"]`).val(equip.equip_technicians);
                $(`input[name="equip_assistants_1"]`).val(equip.equip_assistants);
                equipmentIndex = 1;
              } else {
                // إضافة أقسام جديدة
                equipmentIndex++;
                const newSection = document.createElement('div');
                newSection.className = 'equipment-section';
                newSection.setAttribute('data-index', equipmentIndex);
                newSection.innerHTML = `
                  <div class="equipment-box">
                    <div class="equipment-box-head">
                      <h6 class="equipment-box-title">المعدات رقم ${equipmentIndex}</h6>
                      <button type="button" class="removeEquipmentBtn equipment-remove-btn" data-index="${equipmentIndex}">
                        <i class="fa fa-trash"></i> حذف
                      </button>
                    </div>
                    <div class="form-grid">
                      <div class="field md-3 sm-6">
                        <label>نوع المعدة</label>
                        <div class="control">
                          <select name="equip_type_${equipmentIndex}" class="equip-type">
                            <option value="">— اختر —</option>
                            <option value="حفار" ${equip.equip_type === 'حفار' ? 'selected' : ''}>حفار</option>
                            <option value="قلاب" ${equip.equip_type === 'قلاب' ? 'selected' : ''}>قلاب</option>
                            <option value="خرامة" ${equip.equip_type === 'خرامة' ? 'selected' : ''}>خرامة</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>حجم المعدة (Size)</label>
                        <div class="control"><input name="equip_size_${equipmentIndex}" type="number" placeholder="مثال: 340" value="${equip.equip_size}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المعدات</label>
                        <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0" value="${equip.equip_count}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><span class="label-dot basic">■</span> المعدات الأساسية</label>
                        <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" value="${equip.equip_count_basic || 0}" class="basic-input"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><span class="label-dot backup">■</span> المعدات الاحتياطية</label>
                        <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" value="${equip.equip_count_backup || 0}" class="backup-input"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المساعدين</label>
                        <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0" value="${equip.equip_assistants}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الورديات</label>
                        <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2" value="${equip.equip_shifts}"></div>
                      </div>

                      <!-- أوقات الورديات -->
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                        <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" value="${equip.shift1_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                        <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" value="${equip.shift1_end || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                        <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" value="${equip.shift2_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                        <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" value="${equip.shift2_end || ''}"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>وحدة القياس</label>
                        <div class="control">
                          <select name="equip_unit_${equipmentIndex}" class="equip-unit">
                            <option value="">— اختر —</option>
                            <option value="ساعة" ${equip.equip_unit === 'ساعة' ? 'selected' : ''}>ساعة</option>
                            <option value="طن" ${equip.equip_unit === 'طن' ? 'selected' : ''}>طن</option>
                            <option value="متر طولي" ${equip.equip_unit === 'متر طولي' ? 'selected' : ''}>متر طولي</option>
                            <option value="متر مكعب" ${equip.equip_unit === 'متر مكعب' ? 'selected' : ''}>متر مكعب</option>
                          </select>
                        </div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>ساعات الوردية</label>
                        <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0" value="${equip.shift_hours}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي الساعات يومياً</label>
                        <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_month}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>وحدات العمل في الشهر</label>
                        <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0" value="${equip.equip_monthly_target || 0}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي ساعات العقد</label>
                        <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_contract}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>العملة</label>
                        <div class="control">
                          <select name="equip_price_currency_${equipmentIndex}">
                            <option value="">— اختر —</option>
                            <option value="دولار" ${equip.equip_price_currency === 'دولار' ? 'selected' : ''}>دولار</option>
                            <option value="جنيه" ${equip.equip_price_currency === 'جنيه' ? 'selected' : ''}>جنيه</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>السعر</label>
                        <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" value="${equip.equip_price}"></div>
                      </div>
                       <div class="field md-3 sm-6">
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المشرفين</label>
                        <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0" value="${equip.equip_supervisors}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الفنيين</label>
                        <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0" value="${equip.equip_technicians}"></div>
                      </div>
                    </div>
                  </div>
                `;
                document.getElementById('equipmentSections').appendChild(newSection);

                // إضافة event listeners
                newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
                newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
                  newSection.remove();
                  recalc();
                });
              }
            });
          }

          recalc();
        }
      });

      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    });

    // ==================== Group Toggle (unified) ====================
    // موحّد عبر assets/js/column-groups.js — مفتاح مستقل خاص بعقود السائقين.
    (function () {
      function go() {
        if (window.EmsColumnGroups) {
          EmsColumnGroups.init({ storageKey: 'driverContractGroupStates', mode: 'classic' });
        }
      }
      if (window.EmsColumnGroups) { go(); } else { window.addEventListener('DOMContentLoaded', go); }
    })();
  </script>

<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>

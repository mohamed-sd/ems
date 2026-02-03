<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إيكوبيشن | عقود السائقين</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- DataTables CSS -->

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Call bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <!-- CSS الموقع -->
  <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>
<style>
  .totals {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 10px;
  }

  .kpi {
    /* background: linear-gradient(180deg, #fff, #fffaf0); */
    border: 1px solid #ffcc00;
    border-radius: 14px;
    padding: 14px;
    text-align: center;
  }

  .kpi .v {
    font-weight: 900;
    font-size: clamp(18px, 3vw, 24px);
    color: #7a5a00;
  }

  .kpi .t {
    color: var(--muted);
    font-size: 12px;
  }

  .hr {
    height: 1px;
    /* background: linear-gradient(90deg, transparent, var(--yellow), transparent); */
    margin: 18px 0;
    border: none;
  }
</style>

<body>

  <?php include('../insidebar.php'); ?>

  <div class="main">

    <!-- <h2>العقود</h2> -->
    <div class="aligin">
      <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة عقد سائق
      </a>
    </div>

    <!-- فورم إضافة عقد -->
    <form id="projectForm" action="" method="post" style="display:none;">
      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"> اضافة/ تعديل العقد </h5>
        </div>
        <div class="card-body">

          <input type="hidden" name="driver_id" placeholder="اسم السائق" value="<?php echo $_GET['id'] ?>" required />



          <div class="section-title"><span class="chip">1</span> البيانات الأساسية للسائق والعقد</div>
          <div class="form-grid">

            <div class="field md-3 sm-6">
              <label class="form-label">المشروع</label>
              <div class="control">
                <select name="project_id">
                  <?php
                  include '../config.php';
                  $sql = "SELECT id, name FROM project where status = '1' ORDER BY name ASC  ";
                  $result = mysqli_query($conn, $sql);
                  ?>
                  <option value="">-- اختر المشروع --</option>
                  <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <option value="<?php echo $row['id']; ?>">
                      <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>تاريخ توقيع العقد </label>
              <div class="control"><input name="contract_signing_date" type="date"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>فترة السماح بين التوقيع والتنفيذ </label>
              <div class="control"><input name="grace_period_days" type="number" min="0" placeholder="عدد الأيام"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>مدة العقد بالشهور )</label>
              <div class="control"><input name="contract_duration_months" id="contract_duration_months" type="number"
                  min="0" placeholder="بالشهور"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>بداية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_start" type="date"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>نهاية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_end" type="date"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>الترحيل (Transportation)</label>
              <div class="control">
                <select name="transportation">
                  <option value="">— اختر —</option>
                  <option>مشمولة</option>
                  <option>غير مشمولة</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>الإعاشة (Accommodation)</label>
              <div class="control">
                <select name="accommodation">
                  <option value="">— اختر —</option>
                  <option>مشمولة</option>
                  <option>غير مشمولة</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>السكن (Place for Living)</label>
              <div class="control">
                <select name="place_for_living">
                  <option value="">— اختر —</option>
                  <option>مشمولة</option>
                  <option>غير مشمولة</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>الورشة (Workshop)</label>
              <div class="control">
                <select name="workshop">
                  <option value="">— اختر —</option>
                  <option>مشمولة</option>
                  <option>غير مشمولة</option>
                </select>
              </div>
            </div>
          </div>

          <hr class="hr" />

          <!-- القسم 2: بيانات ساعات العمل المطلوبة للمعدات -->
          <div class="section-title"><span class="chip">2</span> بيانات ساعات العمل المطلوبة <strong>للمعدات</strong>
          </div>
          <div class="form-grid">
            <div class="field md-4 sm-6">
              <label>نوع المعدة المطلوبة </label>
              <div class="control"><input name="equip_type" type="text" placeholder="مثال: حفار" value="حفار"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>حجم المعدة المطلوبة </label>
              <div class="control"><input name="equip_size" type="number" placeholder="مثال: 340" value="340"></span>
              </div>
            </div>
            <div class="field md-4 sm-6">
              <label>عدد المعدات المطلوبة</label>
              <div class="control"><input name="equip_count" id="equip_count" type="number" min="0" value="2"></div>
            </div>

            <!-- Orgnization Break  -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>

            <div class="field md-4 sm-6">
              <label>ساعات العمل المستهدفة للمعدة شهرياً</label>
              <div class="control"><input name="equip_target_per_month" id="equip_target_per_month" type="number"
                  min="0" value="600"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>إجمالي الساعات المستهدفة للمعدات شهرياً</label>
              <div class="control"><input name="equip_total_month" id="equip_total_month" type="number" readonly
                  placeholder="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>إجمالي ساعات العقد المستهدفة للمعدات</label>
              <div class="control"><input name="equip_total_contract" id="equip_total_contract" type="number" readonly
                  placeholder="يُحتسب تلقائياً"></div>
            </div>
          </div>

          <hr class="hr" />

          <!-- القسم 3: بيانات ساعات العمل المطلوبة للآليات -->
          <div class="section-title"><span class="chip">3</span> بيانات ساعات العمل المطلوبة <strong>للآليات</strong>
          </div>
          <div class="form-grid">
            <div class="field md-4 sm-6">
              <label>نوع الآلية المطلوبة</label>
              <div class="control"><input name="mach_type" type="text" placeholder="مثال: قلاب" value="قلاب"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>حجم حمولة الآلية</label>
              <div class="control"><input name="mach_size" type="number" placeholder="مثال: 340" value="340"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>عدد الآليات المطلوبة</label>
              <div class="control"><input name="mach_count" id="mach_count" type="number" min="0" value="8"></div>
            </div>

            <!-- Orgnization Break  -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>

            <div class="field md-4 sm-6">
              <label>ساعات العمل المستهدفة للآلية شهرياً</label>
              <div class="control"><input name="mach_target_per_month" id="mach_target_per_month" type="number" min="0"
                  value="600"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>إجمالي الساعات المستهدفة للآليات شهرياً</label>
              <div class="control"><input name="mach_total_month" id="mach_total_month" type="number" readonly
                  placeholder="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>إجمالي ساعات العقد المستهدفة للآليات</label>
              <div class="control"><input name="mach_total_contract" id="mach_total_contract" type="number" readonly
                  placeholder="يُحتسب تلقائياً"></div>
            </div>
          </div>

          <hr class="hr" />
          <div class="section-title"><span class="chip">5</span> بيانات إضافية</div>
          <div class="form-grid">
            <div class="field md-3 sm-6">
              <label>عدد ساعات العمل اليومية</label>
              <div class="control"><input type="number" name="daily_work_hours" min="0" placeholder="مثال: 8"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المشغلين للساعات اليومية</label>
              <div class="control"><input type="number" name="daily_operators" min="0" placeholder="مثال: 3"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>الطرف الأول </label>
              <div class="control"><input type="text" name="first_party" placeholder="اسم الطرف الاول "></div>
            </div>

            <!-- Orgnization Break  -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>

            <div class="field md-3 sm-6">
              <label>الطرف الثاني </label>
              <div class="control"><input type="text" name="second_party" placeholder="اسم الطرف الثاني"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>الشاهد الأول</label>
              <div class="control"><input type="text" name="witness_one" placeholder="اسم الشاهد الأول"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>الشاهد الثاني</label>
              <div class="control"><input type="text" name="witness_two" placeholder="اسم الشاهد الثاني"></div>
            </div>
          </div>


          <hr class="hr" />

          <!-- القسم 4: الإجماليات -->
          <div class="section-title"><span class="chip">4</span> إجماليات الساعات (شهرياً وللعقد)</div>
          <div class="totals">
            <div class="kpi">
              <div class="v" id="kpi_month_total">0</div>
              <div class="t">الساعات المستهدفة شهرياً - معدات وآليات</div>
              <input type="hidden" name="hours_monthly_target" id="hours_monthly_target" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_contract_total">0</div>
              <div class="t">ساعات العقد المستهدفة - معدات وآليات</div>
              <input type="hidden" name="forecasted_contracted_hours" id="forecasted_contracted_hours" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_equip_month">0</div>
              <div class="t">إجمالي معدات (شهري)</div>
            </div>
            <div class="kpi">
              <div class="v" id="kpi_mach_month">0</div>
              <div class="t">إجمالي آليات (شهري)</div>
            </div>
          </div>

          <div class="toolbar">
            <button type="reset" class="ghost">تفريغ الحقول</button>
          </div>

          <p class="muted" style="margin-top:8px">* يتم احتساب الحقول الإجمالية تلقائياً بناءً على المدخلات.</p>
          <button type="submit" class="primary">حفظ البيانات</button>
        </div>
      </div>
    </form>
    <!-- جدول العقود -->
    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"> قائمة العقودات </h5>
      </div>
      <div class="card-body">
        <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
          <thead>
            <tr>
              <th>تاريخ التوقيع</th>
              <th>مدة العقد (شهور)</th>
              <th>بداية التنفيذ</th>
              <th>نهاية التنفيذ</th>
              <th>ساعات الآليات/شهر</th>
              <th>إجمالي ساعات الآليات</th>
              <th> الحالة </th>
              <th> الاجراءات </th>
            </tr>
          </thead>
          <tbody>
            <?php
            include '../config.php';

            // إضافة عقد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['driver_id'])) {
              // $project = mysqli_real_escape_string($conn, $_POST['project']);
              $driver_id = $_GET['id'];
              $project_id = $_POST['project_id'];




              $contract_signing_date = $_POST['contract_signing_date'];
              $grace_period_days = $_POST['grace_period_days'];
              $contract_duration_months = $_POST['contract_duration_months'];
              $actual_start = $_POST['actual_start'];
              $actual_end = $_POST['actual_end'];
              $transportation = $_POST['transportation'];
              $accommodation = $_POST['accommodation'];
              $place_for_living = $_POST['place_for_living'];
              $workshop = $_POST['workshop'];

              $equip_type = $_POST['equip_type'];
              $equip_size = $_POST['equip_size'];
              $equip_count = $_POST['equip_count'];
              $equip_target_per_month = $_POST['equip_target_per_month'];
              $equip_total_month = $_POST['equip_total_month'];
              $equip_total_contract = $_POST['equip_total_contract'];

              $mach_type = $_POST['mach_type'];
              $mach_size = $_POST['mach_size'];
              $mach_count = $_POST['mach_count'];
              $mach_target_per_month = $_POST['mach_target_per_month'];
              $mach_total_month = $_POST['mach_total_month'];
              $mach_total_contract = $_POST['mach_total_contract'];

              $hours_monthly_target = $_POST['hours_monthly_target'];
              $forecasted_contracted_hours = $_POST['forecasted_contracted_hours'];

              $daily_work_hours = $_POST['daily_work_hours'];
              $daily_operators = $_POST['daily_operators'];
              $first_party = $_POST['first_party'];
              $second_party = $_POST['second_party'];
              $witness_one = $_POST['witness_one'];
              $witness_two = $_POST['witness_two'];


              mysqli_query($conn, "INSERT INTO drivercontracts (
    contract_signing_date, driver_id, grace_period_days, contract_duration_months,
    actual_start, actual_end, transportation, accommodation, place_for_living, workshop,
    equip_type, equip_size, equip_count, equip_target_per_month, equip_total_month, equip_total_contract,
    mach_type, mach_size, mach_count, mach_target_per_month, mach_total_month, mach_total_contract,
    hours_monthly_target, forecasted_contracted_hours,
    daily_work_hours, daily_operators, first_party, second_party, witness_one, witness_two,project_id
) VALUES (
    '$contract_signing_date', '$driver_id','$grace_period_days', '$contract_duration_months',
    '$actual_start','$actual_end', '$transportation','$accommodation','$place_for_living','$workshop',
    '$equip_type','$equip_size','$equip_count','$equip_target_per_month', '$equip_total_month', '$equip_total_contract',
    '$mach_type', '$mach_size','$mach_count','$mach_target_per_month','$mach_total_month','$mach_total_contract',
    '$hours_monthly_target','$forecasted_contracted_hours',
    '$daily_work_hours','$daily_operators','$first_party','$second_party','$witness_one','$witness_two','$project_id'
)");
              echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='drivercontracts.php?id=$driver_id';</script>";

            }
            $driver_id = $_GET['id'];
            // جلب العقود
            $query = "SELECT * FROM `drivercontracts` WHERE `driver_id` = $driver_id  ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;


            while ($row = mysqli_fetch_assoc($result)) {

              $status = $row['status'] == "1" ? "<font color='green'>ساري</font>" : "
              <font color='red'>منتهي</font>";

              echo "<tr>";
              echo "<td>" . $row['contract_signing_date'] . "</td>";
              echo "<td>" . $row['contract_duration_months'] . "</td>";
              echo "<td>" . $row['actual_start'] . "</td>";
              echo "<td>" . $row['actual_end'] . "</td>";

              echo "<td>" . $row['hours_monthly_target'] . "</td>";
              echo "<td>" . $row['equip_total_contract'] . "</td>";
              echo "<td>" . $status . "</td>";

              echo "<td>
                        <a href='' style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                        <a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                        <a href='showcontractdriver.php?id=" . $row['id'] . "' style='color: #28a745'><i class='fa fa-eye'></i></a>
                      </td>";
              echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  <!-- JS -->
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
      // تشغيل DataTable بالعربية


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


      // التحكم في إظهار وإخفاء الفورم
      const toggleContractFormBtn = document.getElementById('toggleForm');
      const contractForm = document.getElementById('projectForm');

      toggleContractFormBtn.addEventListener('click', function () {
        contractForm.style.display = contractForm.style.display === "none" ? "block" : "none";
      });
    })();

  </script>

  <script>
    const $el = (sel) => document.querySelector(sel);

    const fields = {
      contractMonths: $el('#contract_duration_months'),
      equipCount: $el('#equip_count'),
      equipTarget: $el('#equip_target_per_month'),
      equipTotalMonth: $el('#equip_total_month'),
      equipTotalContract: $el('#equip_total_contract'),
      machCount: $el('#mach_count'),
      machTarget: $el('#mach_target_per_month'),
      machTotalMonth: $el('#mach_total_month'),
      machTotalContract: $el('#mach_total_contract'),
      kpiMonthTotal: $el('#kpi_month_total'),
      kpiContractTotal: $el('#kpi_contract_total'),
      kpiEquipMonth: $el('#kpi_equip_month'),
      kpiMachMonth: $el('#kpi_mach_month'),
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

    function recalc() {
      const months = num(fields.contractMonths.value);

      // معدات
      const equipCount = num(fields.equipCount.value);
      const equipTarget = num(fields.equipTarget.value);
      const equipMonth = equipCount * equipTarget;
      const equipContract = equipMonth * months;

      // آليات
      const machCount = num(fields.machCount.value);
      const machTarget = num(fields.machTarget.value);
      const machMonth = machCount * machTarget;
      const machContract = machMonth * months;

      // تحديث الحقول
      fields.equipTotalMonth.value = equipMonth;
      fields.equipTotalContract.value = equipContract;
      fields.machTotalMonth.value = machMonth;
      fields.machTotalContract.value = machContract;

      const monthTotal = equipMonth + machMonth;
      const contractTotal = equipContract + machContract;

      fields.kpiEquipMonth.textContent = fmt(equipMonth);
      fields.kpiMachMonth.textContent = fmt(machMonth);
      fields.kpiMonthTotal.textContent = fmt(monthTotal);
      fields.kpiContractTotal.textContent = fmt(contractTotal);

      fields.hoursMonthlyTarget.value = monthTotal;
      fields.forecastedContractedHours.value = contractTotal;
    }

    // تشغيل الحسبة عند تغيير أي مدخل
    const inputs = document.querySelectorAll('#projectForm input, #projectForm select');
    inputs.forEach(el => el.addEventListener('input', recalc));

    // جلب الفورم
    const contractForm = document.getElementById('projectForm');
    if (contractForm) {
      contractForm.addEventListener('reset', () => setTimeout(recalc, 0));
    }

    // أول تشغيل
    recalc();
  </script>


</body>

</html>
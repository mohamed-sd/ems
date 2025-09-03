<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | المشاريع</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../includes/insidebar.php'); ?>

<div class="main">

   <!--  <h2>المشاريع</h2> -->

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة مشروع
    </a>

    <!-- فورم إضافة مشروع -->
    <form id="projectForm" action="" method="post">
        <h3>إضافة مشروع جديد</h3>
    <div class="form-grid">
    <div>
        <label>اسم المشروع</label>
        <input type="text" name="name" placeholder="اسم المشروع" required />
    </div>
    <div>
        <label>اسم العميل</label>
        <input type="text" name="client" placeholder="اسم العميل" required />
    </div>
    <div>
        <label>موقع المشروع</label>
        <input type="text" name="location" placeholder="موقع المشروع" required />
    </div>
    <div>
        <label>القيمة الإجمالية</label>
        <input type="number" name="total" placeholder="القيمة الإجمالية" required />
    </div>
    <button type="submit">حفظ المشروع</button>
    </div>




     <div class="section-title"><span class="chip">1</span> البيانات الأساسية للعميل والعقد</div>
        <div class="form-grid">
          <div class="field md-6 sm-6">
            <label>أسم العميل (Customer Name)</label>
            <div class="control"><input name="customer_name" type="text" placeholder="مثال: شركة اليمامة" required>
            </div>
          </div>
          <div class="field md-6 sm-6">
            <label>أسم المشروع (Project Name)</label>
            <div class="control"><input name="project_name" type="text" placeholder="مثال: طريق السواحل" required></div>
          </div>
          <div class="field md-6 sm-6">
            <label>موقع المشروع (Project Location)</label>
            <div class="control"><input name="project_location" type="text" placeholder="المدينة / الإحداثيات"></div>
          </div>
          <div class="field md-3 sm-6">
            <label>تاريخ توقيع العقد (Contract signing date)</label>
            <div class="control"><input name="contract_signing_date" type="date"></div>
          </div>
          <div class="field md-3 sm-6">
            <label>فترة السماح بين التوقيع والتنفيذ (Grace period)</label>
            <div class="control"><input name="grace_period_days" type="number" min="0" placeholder="عدد الأيام"><span
                class="unit">أيام</span></div>
          </div>
          <div class="field md-3 sm-6">
            <label>مدة العقد بالشهور (Contract  Per Month)</label>
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
            <label>نوع المعدة المطلوبة (Type of equipment)</label>
            <div class="control"><input name="equip_type" type="text" placeholder="مثال: حفار" value="حفار"></div>
          </div>
          <div class="field md-4 sm-6">
            <label>حجم المعدة المطلوبة (Size)</label>
            <div class="control"><input name="equip_size" type="number" placeholder="مثال: 340" value="340"></span></div>
          </div>
          <div class="field md-4 sm-6">
            <label>عدد المعدات المطلوبة</label>
            <div class="control"><input name="equip_count" id="equip_count" type="number" min="0" value="2"></div>
          </div>
          <div class="field md-4 sm-6">
            <label>ساعات العمل المستهدفة للمعدة شهرياً</label>
            <div class="control"><input name="equip_target_per_month" id="equip_target_per_month" type="number" min="0"
                value="600"></div>
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
          <button type="submit" class="primary">حفظ البيانات</button>
        </div>

        <p class="muted" style="margin-top:8px">* يتم احتساب الحقول الإجمالية تلقائياً بناءً على المدخلات.</p>
    </form>


    

    <br/> <br/> <br/>

    <!-- جدول المشاريع -->
    <h3>قائمة المشاريع</h3>
    <br/>
    <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align: right;">اسم المشروع</th>
                 <th style="text-align: right;"> عدد الاليات </th>

                <th style="text-align: right;"> العقود </th>
                <th style="text-align: right;">العميل</th>
                <th style="text-align: right;">الموقع</th>
                <th style="text-align: right;">القيمة الإجمالية</th>
                 <th style="text-align: right;"> عدد الموردين</th>

                <th style="text-align: right;">تاريخ الإضافة</th>
                <th style="text-align: right;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include '../config.php';
            
            // إضافة مشروع جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $client = mysqli_real_escape_string($conn, $_POST['client']);
                $location = mysqli_real_escape_string($conn, $_POST['location']);
                $total = floatval($_POST['total']);
                $date = date('Y-m-d H:i:s');
                mysqli_query($conn, "INSERT INTO projects (name, client, location, total, create_at) VALUES ('$name', '$client', '$location', '$total', '$date')");
            }

            // جلب المشاريع
            $query = "SELECT `id`, `name`, `client`, `location`, `total`, `create_at`, (SELECT COUNT(*) FROM contracts WHERE contracts.project = projects.id) as 'contracts' , (SELECT COUNT(*) FROM operations WHERE operations.project = projects.id) as 'operations',(SELECT COUNT(DISTINCT pm.suppliers) AS total_suppliers
FROM equipments pm
JOIN operations m ON pm.id = m.equipment
WHERE m.project =   projects.id) as 'total_suppliers'   FROM projects ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>".$i++."</td>";
                echo "<td>".$row['name']."</td>";
                echo "<td>".$row['operations']."</td>";
                echo "<td>".$row['contracts']."</td>";
                
                echo "<td>".$row['client']."</td>";
                echo "<td>".$row['location']."</td>";
                echo "<td>".$row['total']."</td>";
                echo "<td>".$row['total_suppliers']."</td>";

                echo "<td>".$row['create_at']."</td>";
                echo "<td>
                        <a href='edit.php?id=".$row['id']."' >تعديل</a> | 
                        <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href='projects_details.php?id=".$row['id']."'> عرض </a>
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

<script>
(function() {
    // تشغيل DataTable بالعربية
    $(document).ready(function() {
        $('#projectsTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });

    // التحكم في إظهار وإخفاء الفورم
    const toggleProjectFormBtn = document.getElementById('toggleForm');
    const projectForm = document.getElementById('projectForm');

    toggleProjectFormBtn.addEventListener('click', function() {
        projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
    });
})();
</script>

</body>
</html>

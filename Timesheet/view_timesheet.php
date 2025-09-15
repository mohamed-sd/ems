<?php
session_start();
// check user login
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

$page_title = "إيكوبيشن | ساعات العمل";
include("../inheader.php");
include('../insidebar.php');
include '../config.php';

$type = "1";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $start_date = $_POST['start_date'];
  $end_date   = $_POST['end_date'];
  $equipment_type = $_POST['equipment_type'];


  $shift_filter = isset($_POST['shift']) ? $_POST['shift'] : '';


  $where = "WHERE t.type = '$type' ";

  if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND t.date BETWEEN '$start_date' AND '$end_date' ";
  }
  if (!empty($equipment_type)) {
    $where .= " AND t.type = '$equipment_type' ";
  }else{
    $where .= " AND t.type = '1' ";
  }

  if (!empty($shift_filter)) {
    $where .= " AND t.shift = '$shift_filter' ";
}


} else {
  // ✅ أول مرة يفتح الصفحة: عرض سجلات اليوم فقط
  $today = date("Y-m-d");
  $where = "WHERE t.type = '$type' AND t.date = '$today' ";
}

// --- إحصائيات ---
$stats = [
  "total"        => 0,
  "approved"     => 0,
  "pending"      => 0,
  "rejected"     => 0,
  "total_hours"  => 0
];

$stat_query = mysqli_query($conn, "SELECT 
  COUNT(*) AS total,
  IFNULL(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END),0) AS approved,
  IFNULL(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END),0) AS pending,
  IFNULL(SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END),0) AS rejected,
  IFNULL(SUM(executed_hours),0) AS total_hours
  FROM timesheet t $where
");


if ($row = mysqli_fetch_assoc($stat_query)) {
  $stats = $row;
}

// --- البيانات ---
$query = "SELECT t.id, t.shift, t.date, t.executed_hours,
        t.standby_hours , t.total_fault_hours ,bucket_hours,jackhammer_hours,
        extra_hours, t.status ,
        e.code AS eq_code, e.name AS eq_name, e.type AS eq_type,
        p.name AS project_name,
        d.name AS driver_name
    FROM timesheet t
    JOIN operations o ON t.operator = o.id
    JOIN equipments e ON o.equipment = e.id
    JOIN projects p ON o.project = p.id
    JOIN drivers d ON t.driver = d.id
    $where
    ORDER BY t.id DESC";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= $page_title ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .stat-card { border-radius: 1rem; color: #fff; padding: 1rem; }
    .bg-total { background: linear-gradient(45deg,#000022,#000022); }
    .bg-approved { background: linear-gradient(45deg,#ffcc00,#ffcc00); }
    .bg-pending { background: linear-gradient(45deg,#000022,#000022); }
    .bg-rejected { background: linear-gradient(45deg,#ffcc00,#ffcc00); }
    .bg-hours { background: linear-gradient(45deg,#000022,#000022); }
    .filter-form .form-control, .filter-form .form-select {
      border-radius: .75rem;
    }
  </style>
</head>
<body>

<div class="container-fluid py-4">

  <!-- ✅ إحصائيات -->
  <div class="row g-3 mb-4 text-center">
    <!-- <div class="col-md-2 col-6">
      <div class="stat-card bg-total shadow-sm">
        <h5>إجمالي السجلات</h5>
        <h3><?= $stats['total'] ? : 0 ?></h3>
      </div>
    </div> -->
    <div class="col-md-3 col-6">
      <div class="stat-card bg-approved shadow-sm">
        <h5>المعتمدة</h5>
        <h3><?= $stats['approved'] ? : 0 ?></h3>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="stat-card bg-pending shadow-sm">
        <h5>قيد المراجعة</h5>
        <h3><?= $stats['pending'] ? : 0 ?></h3>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="stat-card bg-rejected shadow-sm">
        <h5>المرفوضة</h5>
        <h3><?= $stats['rejected'] ? : 0 ?></h3>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="stat-card bg-hours shadow-sm">
        <h5>إجمالي الساعات المنفذة</h5>
        <h3><?= $stats['total_hours'] ?: 0 ?></h3>
      </div>
    </div>
  </div>

  <!-- ✅ فورم الفلترة -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-warning text-white">
      <h5 class="mb-0" style="color: #000022;">🔍 فلترة النتائج</h5>
    </div>
    <div class="card-body">
      <form method="POST" class="row g-3 filter-form">
        <div class="col-md-2">
          <label class="form-label">تاريخ البداية</label>
          <input type="date" name="start_date" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">تاريخ النهاية</label>
          <input type="date" name="end_date" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">نوع المعدة</label>
          <select name="equipment_type" class="form-select">
            <option value="">-- الكل --</option>
            <option value="1">حفار</option>
            <option value="2">قلاب</option>
          </select>
        </div>

          <div class="col-md-2">
               <label class="form-label">🚛 الوردية:</label>

                 <select class="form-select" name="shift">
        <option value="">-- الكل --</option>
        <option value="D" <?php if(($_GET['shift'] ?? '')=="صباحية") echo "selected"; ?>>صباحية</option>
        <option value="N" <?php if(($_GET['shift'] ?? '')=="مسائية") echo "selected"; ?>>مسائية</option>
    </select>
                    </div>
        <div class="col-1 text-end">  </div>
        <div class="col-2 text-end">
          <br/>
          <button type="submit" class="btn btn-success px-4">تطبيق</button>
          <a href="view_timesheet.php" class="btn btn-secondary px-4">إعادة </a>
        </div>
      </form>
    </div>
  </div>

  <!-- ✅ جدول البيانات -->
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0">📋 قائمة ساعات العمل</h5>
    </div>
    <div class="card-body">
      <table id="projectsTable" class="display nowrap table table-striped table-bordered" style="width:100%">
        <thead>
          <tr>
            <th>#</th>
            <th>المعدة</th>
            <th>المشروع</th>
            <th>التاريخ</th>
            <th>الوردية</th>
            <th>الساعات</th>
            <th>الجردل</th>
            <th>الجاكهمر</th>
            <th>الإضافية</th>
            <th>الاستعداد</th>
            <th>الأعطال</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          while ($row = mysqli_fetch_assoc($result)) {
            switch ($row['status']) {
              case "1": $status = "<span class='badge bg-warning text-dark'>تحت المراجعة</span>"; break;
              case "2": $status = "<span class='badge bg-success'>تم الاعتماد</span>"; break;
              case "3": $status = "<span class='badge bg-danger'>تم الرفض</span>"; break;
              default: $status = "<span class='badge bg-secondary'>غير معروف</span>";
            }

            echo "<tr>";
            echo "<td>" . $i++ . "</td>";
            echo "<td>" . $row['eq_code'] . " - " . $row['eq_name'] . "</td>";
            echo "<td>" . $row['project_name'] . "</td>";
            echo "<td>" . $row['date'] . "</td>";
            echo $row['shift'] == "D" ? "<td>صباحية</td>" : "<td>مسائية</td>";
            echo "<td>" . $row['executed_hours'] . "</td>";
            echo "<td>" . $row['bucket_hours'] . "</td>";
            echo "<td>" . $row['jackhammer_hours'] . "</td>";
            echo "<td>" . $row['extra_hours'] . "</td>";
            echo "<td>" . $row['standby_hours'] . "</td>";
            echo "<td>" . $row['total_fault_hours'] . "</td>";
            echo "<td>" . $status . "</td>";
            echo "<td>
              <a href='aprovment.php?t=$type&&type=1&&id=" . $row['id'] . "' class='text-success'> <i class='fa fa-check'></i> </a> |
              <a href='aprovment.php?t=$type&&type=2&&id=" . $row['id'] . "' class='text-danger'> <i class='fa fa-close'></i> </a> |
              
              <a href='delete_timesheet.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' class='text-danger'><i class='fa fa-trash'></i></a> |
              <a href='timesheet_details.php?id=" . $row['id'] . "' class='text-success'> <i class='fa fa-eye'></i> </a>  
            </td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ✅ JS -->
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
$(document).ready(function () {
  $('#projectsTable').DataTable({
    responsive: true,
    dom: 'Bfrtip',
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

</script>
</body>
</html>

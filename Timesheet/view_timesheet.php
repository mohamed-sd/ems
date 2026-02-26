<?php
session_start();
// check user login
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

$page_title = "إيكوبيشن | ساعات العمل";
include("../inheader.php");
include '../config.php';

$type = "1";

$where = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $start_date     = mysqli_real_escape_string($conn, $_POST['start_date']);
  $end_date       = mysqli_real_escape_string($conn, $_POST['end_date']);
  $equipment_type = mysqli_real_escape_string($conn, $_POST['equipment_type']);
  $shift_filter   = isset($_POST['shift']) ? mysqli_real_escape_string($conn, $_POST['shift']) : '';

  // مصفوفة لتجميع الشروط
  $whereParts = [];

  if (!empty($start_date) && !empty($end_date)) {
    $whereParts[] = "t.date BETWEEN '$start_date' AND '$end_date'";
  }
  if (!empty($equipment_type)) {
    $whereParts[] = "t.type = '$equipment_type'";
  }
  if (!empty($shift_filter)) {
    $whereParts[] = "t.shift = '$shift_filter'";
  }

  if (count($whereParts) > 0) {
    $where = "WHERE " . implode(" AND ", $whereParts);
  }
} else {
  // ✅ أول مرة يفتح الصفحة: عرض سجلات اليوم فقط
  $today = date("Y-m-d");
  $where = "WHERE t.date = '$today'";
}

// --- إحصائيات ---
$stats = [
  "total" => 0,
  "approved" => 0,
  "pending" => 0,
  "rejected" => 0,
  "total_hours" => 0
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
    JOIN project p ON o.project_id = p.id
    JOIN drivers d ON t.driver = d.id
    $where
    ORDER BY t.id DESC";

$result = mysqli_query($conn, $query);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<?php 
include('../insidebar.php'); 
?>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-business-time"></i></div>
            ساعات العمل
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="view_timesheet.php" class="back-btn" style="background: var(--green-soft); color: var(--green); border-color: rgba(22,163,74,.22);">
                <i class="fas fa-redo"></i> إعادة تعيين
            </a>
        </div>
    </div>

    <!-- ✅ إحصائيات -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-card-value" id="stat_approved"><?= !empty($stats['approved']) ? $stats['approved'] : 0 ?></div>
            <div class="stat-card-label">سجلات معتمدة</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-card-value" id="stat_pending"><?= !empty($stats['pending']) ? $stats['pending'] : 0 ?></div>
            <div class="stat-card-label">قيد المراجعة</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-card-value" id="stat_rejected"><?= !empty($stats['rejected']) ? $stats['rejected'] : 0 ?></div>
            <div class="stat-card-label">سجلات مرفوضة</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-card-value" id="stat_hours"><?= !empty($stats['total_hours']) ? (int)$stats['total_hours'] : 0 ?></div>
            <div class="stat-card-label">إجمالي الساعات</div>
        </div>
    </div>

    <!-- ✅ فورم الفلترة -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> فلترة النتائج</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="form-grid">
                <div>
                    <label><i class="fas fa-calendar"></i> تاريخ البداية</label>
                    <input type="date" name="start_date" class="form-control" />
                </div>
                <div>
                    <label><i class="fas fa-calendar"></i> تاريخ النهاية</label>
                    <input type="date" name="end_date" class="form-control" />
                </div>
                <div>
                    <label><i class="fas fa-cogs"></i> نوع المعدة</label>
                    <select name="equipment_type" class="form-control">
                        <option value="">-- الكل --</option>
                        <option value="1">🔧 حفار</option>
                        <option value="2">🚛 قلاب</option>
                    </select>
                </div>
                <div>
                    <label><i class="fas fa-sun"></i> الوردية</label>
                    <select name="shift" class="form-control">
                        <option value="">-- الكل --</option>
                        <option value="D">☀️ صباحية</option>
                        <option value="N">🌙 مسائية</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-search"></i> تطبيق
                    </button>
                    <a href="view_timesheet.php" class="btn-cancel" style="text-decoration: none; padding: 11px 26px;">
                        <i class="fas fa-redo"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ✅ جدول البيانات -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> قائمة ساعات العمل</h5>
        </div>
        <div class="card-body">
            <table id="timesheetTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المعدة</th>
                        <th>المشروع</th>
                        <th>السائق</th>
                        <th>التاريخ</th>
                        <th>الوردية</th>
                        <th>الساعات المنفذة</th>
                        <th>الاستعداد</th>
                        <th>الأعطال</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        // تحديد الحالة
                        $statusBadge = '';
                        switch ($row['status']) {
                            case "1":
                                $statusBadge = '<span class="status-pill" style="background: rgba(232,184,0,.13); color: var(--gold); border: 1px solid rgba(232,184,0,.22);">⏳ قيد المراجعة</span>';
                                break;
                            case "2":
                                $statusBadge = '<span class="status-pill status-active">✓ معتمد</span>';
                                break;
                            case "3":
                                $statusBadge = '<span class="status-pill status-inactive">✗ مرفوض</span>';
                                break;
                            default:
                                $statusBadge = '<span class="status-pill">غير معروف</span>';
                        }

                        // تحديد الوردية
                        $shiftText = $row['shift'] == "D" ? "☀️ صباحية" : "🌙 مسائية";

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['eq_code'] . " - " . $row['eq_name']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['driver_name']) . "</td>";
                        echo "<td>" . $row['date'] . "</td>";
                        echo "<td>" . $shiftText . "</td>";
                        echo "<td><strong>" . $row['executed_hours'] . " ساعة</strong></td>";
                        echo "<td>" . $row['standby_hours'] . " ساعة</td>";
                        echo "<td>" . $row['total_fault_hours'] . " ساعة</td>";
                        echo "<td>" . $statusBadge . "</td>";
                        echo "<td>
                            <div class='action-btns'>
                                <a href='timesheet_details.php?id=" . $row['id'] . "' 
                                   class='action-btn view' 
                                   title='عرض التفاصيل'>
                                    <i class='fas fa-eye'></i>
                                </a>
                                <a href='aprovment.php?t=$type&type=1&id=" . $row['id'] . "' 
                                   class='action-btn' 
                                   style='background: var(--green-soft); color: var(--green);'
                                   title='الموافقة'>
                                    <i class='fas fa-check'></i>
                                </a>
                                <a href='aprovment.php?t=$type&type=2&id=" . $row['id'] . "' 
                                   class='action-btn delete' 
                                   title='الرفض'>
                                    <i class='fas fa-times'></i>
                                </a>
                                <a href='delete_timesheet.php?id=" . $row['id'] . "' 
                                   class='action-btn' 
                                   style='background: var(--red-soft); color: var(--red);'
                                   onclick=\"return confirm('هل أنت متأكد من الحذف؟')\"
                                   title='حذف'>
                                    <i class='fas fa-trash'></i>
                                </a>
                            </div>
                        </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- jQuery (Required first) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
        // تشغيل DataTable بالعربية
        $('#timesheetTable').DataTable({
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
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
    });
</script>

</body>

</html>
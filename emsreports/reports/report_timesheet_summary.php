<?php
// محول تلقائي للملف القديم — يحيل إلى النظام الجديد
$REPORT_CODE = 'timesheet_summary';
require __DIR__ . '/_report_template.php';

// تحديد كود التقرير المطلوب من الباراميترات
$viewParam = isset($_GET['view']) ? trim($_GET['view']) : '';
$groupParam = isset($_GET['group']) ? trim($_GET['group']) : '';
$detailParam = isset($_GET['detail']) ? intval($_GET['detail']) : 0;

$reportCode = 'timesheet_summary';
if ($viewParam === 'detailed') {
    $reportCode = 'timesheet_detailed';
} elseif ($groupParam === 'project') {
    $reportCode = 'timesheet_by_project';
} elseif ($groupParam === 'equipment') {
    $reportCode = 'timesheet_by_equipment';
} elseif ($groupParam === 'driver') {
    $reportCode = 'timesheet_by_driver';
} elseif ($groupParam === 'supplier') {
    $reportCode = 'supplier_timesheet';
} elseif ($groupParam === 'fleet') {
    $reportCode = 'fleet_timesheet';
} elseif ($viewParam === 'fleet' && $detailParam === 1) {
    $reportCode = 'fleet_equipment_detailed';
} elseif ($viewParam === 'fleet') {
    $reportCode = 'fleet_equipment_summary';
} elseif ($viewParam === 'drivers' && $detailParam === 1) {
    $reportCode = 'drivers_detailed';
} elseif ($viewParam === 'drivers') {
    $reportCode = 'drivers_summary';
} elseif ($viewParam === 'driver_contracts') {
    $reportCode = 'drivers_contracts';
} elseif ($viewParam === 'ops_detailed') {
    $reportCode = 'operations_detailed';
} elseif ($viewParam === 'ops_summary' || $viewParam === 'operations') {
    $reportCode = 'operations_summary';
}

// التحقق من الصلاحية (مع قبول timesheet_summary كبديل افتراضي)
$hasPermission = checkReportPermission($conn, $reportCode, $roleId);
if (!$hasPermission && $reportCode !== 'timesheet_summary') {
    $hasPermission = checkReportPermission($conn, 'timesheet_summary', $roleId);
}

if (!$hasPermission) {
    die('<div style="padding: 20px; text-align: center; color: red;">
        <h3>لا توجد صلاحية لعرض هذا التقرير</h3>
    </div>');
}

$page_title = 'تقرير ساعات العمل الشامل';

// معالجة المرشحات
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$driverId = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;

// بناء الاستعلام الأساسي
$whereClause = "WHERE 1=1";

if (!empty($dateFrom) && !empty($dateTo)) {
    $safeFrom = mysqli_real_escape_string($conn, $dateFrom);
    $safeTo = mysqli_real_escape_string($conn, $dateTo);
    $whereClause .= " AND STR_TO_DATE(t.date, '%Y-%m-%d') BETWEEN '$safeFrom' AND '$safeTo'";
}

if ($projectId) $whereClause .= " AND o.project_id = " . intval($projectId);
if ($supplierId) $whereClause .= " AND s.id = " . intval($supplierId);
if ($driverId) $whereClause .= " AND d.id = " . intval($driverId);

// الحصول على بيانات التايم شيت
$query = "SELECT 
            t.id,
            t.date,
            CONCAT(
                LPAD(IFNULL(t.start_hours, 0), 2, '0'), ':',
                LPAD(IFNULL(t.start_minutes, 0), 2, '0'), ':',
                LPAD(IFNULL(t.start_seconds, 0), 2, '0')
            ) AS start_time,
            CONCAT(
                LPAD(IFNULL(t.end_hours, 0), 2, '0'), ':',
                LPAD(IFNULL(t.end_minutes, 0), 2, '0'), ':',
                LPAD(IFNULL(t.end_seconds, 0), 2, '0')
            ) AS end_time,
            IFNULL(t.shift_hours, 0) AS shift_hours,
            IFNULL(t.executed_hours, 0) AS executed_hours,
            IFNULL(t.total_work_hours, 0) AS total_work_hours,
            FLOOR(IFNULL(t.total_work_hours, 0)) AS total_hours,
            ROUND((IFNULL(t.total_work_hours, 0) - FLOOR(IFNULL(t.total_work_hours, 0))) * 60) AS total_minutes,
            IFNULL(t.total_fault_hours, 0) AS total_fault_hours,
            t.fault_type,
            t.fault_notes,
            COALESCE(
                NULLIF(t.general_notes, ''),
                NULLIF(t.work_notes, ''),
                NULLIF(t.operator_notes, ''),
                NULLIF(t.time_notes, ''),
                '-'
            ) AS notes,
            o.id as operation_id,
            e.id as equipment_id,
            e.code as equipment_code,
            e.name as equipment_name,
            s.id as supplier_id,
            s.name as supplier_name,
            p.id as project_id,
            p.name as project_name,
            d.id as driver_id,
            d.name as driver_name
        FROM timesheet t
        LEFT JOIN operations o ON t.operator = o.id
        LEFT JOIN equipments e ON o.equipment = e.id
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN project p ON o.project_id = p.id
        LEFT JOIN drivers d ON t.driver = d.id
        $whereClause
        ORDER BY STR_TO_DATE(t.date, '%Y-%m-%d') DESC, t.id DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die('خطأ في قاعدة البيانات: ' . mysqli_error($conn));
}

$timesheetData = array();
$totalHoursFloat = 0;
$totalRecords = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $timesheetData[] = $row;
    $totalHoursFloat += floatval($row['total_work_hours']);
    $totalRecords++;
}

$totalHours = floor($totalHoursFloat);
$totalMinutes = round(($totalHoursFloat - $totalHours) * 60);
if ($totalMinutes >= 60) {
    $totalHours += floor($totalMinutes / 60);
    $totalMinutes = $totalMinutes % 60;
}

// معالجة التصدير
if (isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    
    if ($format === 'excel') {
        $headers = array(
            'التاريخ',
            'وقت البداية',
            'وقت النهاية',
            'إجمالي ساعات العمل',
            'إجمالي ساعات الأعطال',
            'المشروع',
            'الآلية',
            'المورد',
            'المشغل',
            'نوع الخلل',
            'ملاحظات'
        );
        
        $excelData = array();
        foreach ($timesheetData as $row) {
            $excelData[] = array(
                formatDateArabic($row['date']),
                $row['start_time'],
                $row['end_time'],
                number_format((float)$row['total_work_hours'], 2),
                number_format((float)$row['total_fault_hours'], 2),
                $row['project_name'] ?? '-',
                $row['equipment_code'] ?? '-',
                $row['supplier_name'] ?? '-',
                $row['driver_name'] ?? '-',
                $row['fault_type'] ?? '-',
                $row['notes'] ?? '-'
            );
        }
        
        exportToExcel('تقرير_ساعات_العمل_' . date('Y-m-d'), 'ساعات العمل', $headers, $excelData);
    } elseif ($format === 'pdf') {
        $htmlTable = createHTMLTable(
            array('التاريخ', 'وقت البداية', 'وقت النهاية', 'ساعات العمل', 'ساعات الأعطال', 'المشروع', 'الآلية', 'المورد', 'المشغل'),
            array_map(function($row) {
                return array(
                    formatDateArabic($row['date']),
                    $row['start_time'],
                    $row['end_time'],
                    number_format((float)$row['total_work_hours'], 2),
                    number_format((float)$row['total_fault_hours'], 2),
                    $row['project_name'] ?? '-',
                    $row['equipment_code'] ?? '-',
                    $row['supplier_name'] ?? '-',
                    $row['driver_name'] ?? '-'
                );
            }, $timesheetData)
        );
        
        exportToPDF('تقرير_ساعات_العمل', 'تقرير ساعات العمل الشامل', $htmlTable);
    }
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4472C4;
            --secondary-color: #70AD47;
            --danger-color: #E74C3C;
        }

        body {
            background-color: #f8f9fa;
        }

        .report-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #2c3e8f 100%);
            color: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .report-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .stats-row {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            border-right: 3px solid var(--primary-color);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .data-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        table {
            font-size: 12px;
        }

        table thead {
            background-color: var(--primary-color);
            color: white;
        }

        table thead th {
            font-weight: 600;
            padding: 12px 8px;
            border: none;
        }

        table tbody td {
            padding: 10px 8px;
            vertical-align: middle;
        }

        table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .export-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .btn-export {
            margin: 0 5px;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }

        .btn-back {
            margin-bottom: 20px;
        }

        .print-button {
            background-color: var(--primary-color);
            color: white;
        }

        .print-button:hover {
            background-color: #3359a3;
        }
    </style>
</head>
<body>
    <?php include '../../insidebar.php'; ?>

    <main class="container-fluid" style="padding: 20px;">

        <a href="../index.php" class="btn btn-secondary btn-sm btn-back">
            <i class="fas fa-arrow-right"></i> العودة للتقارير
        </a>

        <div class="report-header">
            <h1><i class="fas fa-clock"></i> تقرير ساعات العمل الشامل</h1>
        </div>

        <!-- قسم المرشحات -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">من التاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">إلى التاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المشروع</label>
                    <select name="project_id" class="form-select">
                        <option value="">جميع المشاريع</option>
                        <?php
                        $projects = mysqli_query($conn, "SELECT id, name FROM project ORDER BY name");
                        while ($proj = mysqli_fetch_assoc($projects)) {
                            $selected = ($projectId == $proj['id']) ? 'selected' : '';
                            echo '<option value="' . $proj['id'] . '" ' . $selected . '>' . htmlspecialchars($proj['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>

        <!-- قسم الإحصائيات -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value"><?php echo $totalRecords; ?></div>
                <div class="stat-label">عدد السجلات</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $totalHours . ':' . str_pad($totalMinutes, 2, '0', STR_PAD_LEFT); ?></div>
                <div class="stat-label">إجمالي الساعات</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($totalRecords > 0 ? ($totalHoursFloat / $totalRecords) : 0, 2); ?></div>
                <div class="stat-label">متوسط الساعات</div>
            </div>
        </div>

        <!-- قسم التصدير -->
        <div class="export-section">
            <form method="POST" style="display: inline;">
                <button type="submit" name="export_format" value="excel" class="btn btn-export" style="background-color: #27ae60; color: white;">
                    <i class="fas fa-file-excel"></i> تصدير Excel
                </button>
                <button type="submit" name="export_format" value="pdf" class="btn btn-export" style="background-color: #e74c3c; color: white;">
                    <i class="fas fa-file-pdf"></i> تصدير PDF
                </button>
                <button type="button" class="btn btn-export print-button" onclick="window.print()">
                    <i class="fas fa-print"></i> طباعة
                </button>
            </form>
        </div>

        <!-- قسم البيانات -->
        <div class="data-table">
            <table class="table table-hover mb-0" id="timesheetTable">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>وقت البداية</th>
                        <th>وقت النهاية</th>
                        <th>ساعات الوردية</th>
                        <th>ساعات التنفيذ</th>
                        <th>إجمالي ساعات العمل</th>
                        <th>ساعات الأعطال</th>
                        <th>المشروع</th>
                        <th>الآلية</th>
                        <th>المورد</th>
                        <th>المشغل</th>
                        <th>نوع الخلل</th>
                        <th>الملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timesheetData as $row): ?>
                        <tr>
                            <td><?php echo formatDateArabic($row['date']); ?></td>
                            <td><?php echo $row['start_time']; ?></td>
                            <td><?php echo $row['end_time']; ?></td>
                            <td><?php echo number_format((float)$row['shift_hours'], 2); ?></td>
                            <td><?php echo number_format((float)$row['executed_hours'], 2); ?></td>
                            <td><?php echo number_format((float)$row['total_work_hours'], 2); ?></td>
                            <td><?php echo number_format((float)$row['total_fault_hours'], 2); ?></td>
                            <td><?php echo $row['project_name'] ?? '-'; ?></td>
                            <td><?php echo $row['equipment_code'] ?? '-'; ?></td>
                            <td><?php echo $row['supplier_name'] ?? '-'; ?></td>
                            <td><?php echo $row['driver_name'] ?? '-'; ?></td>
                            <td><?php echo $row['fault_type'] ?? '-'; ?></td>
                            <td><?php echo $row['notes'] ?? '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($timesheetData)): ?>
            <div style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                <p>لا توجد بيانات لعرضها</p>
            </div>
        <?php endif; ?>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#timesheetTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
                },
                pageLength: 25,
                ordering: true,
                searching: true,
                responsive: true
            });
        });
    </script>

</body>
</html>

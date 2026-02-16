<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

$driver_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$driver = null;
if ($driver_id > 0) {
    $driver_result = mysqli_query($conn, "SELECT id, name, phone, status FROM drivers WHERE id = $driver_id");
    if ($driver_result && mysqli_num_rows($driver_result) > 0) {
        $driver = mysqli_fetch_assoc($driver_result);
    }
}

if (!$driver) {
    header("Location: drivers.php");
    exit();
}

$page_title = "إيكوبيشن | سجل قيادة الشاحنات";
include("../inheader.php");
include("../insidebar.php");
?>

<style>
    .history-header {
        background: linear-gradient(135deg, #01072a 0%, #2d2b22 100%);
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
        color: #fff;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .history-header h2 {
        margin: 0;
        font-weight: 700;
    }

    .history-meta {
        margin-top: 0.5rem;
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .current-assignment {
        background: rgba(40, 167, 69, 0.12) !important;
        border-right: 4px solid #28a745;
    }
</style>

<div class="main">
    <div class="history-header">
        <h2><i class="fa fa-history"></i> سجل قيادة الشاحنات</h2>
        <div class="history-meta">
            السائق: <?php echo htmlspecialchars($driver['name']); ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">تاريخ قيادة الشاحنات</h5>
        </div>
        <div class="card-body">
            <table id="historyTable" class="display" style="width:100%; margin-top: 10px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الشاحنة</th>
                        <th>من تاريخ</th>
                        <th>إلى تاريخ</th>
                        <th>المورد</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history_query = "
                        SELECT 
                            ed.id,
                            ed.equipment_id,
                            ed.driver_id,
                                                        ed.status,
                                                        ed.start_date,
                                                        ed.end_date,
                            e.code,
                            e.name,
                            e.type,
                            s.name AS supplier_name
                        FROM equipment_drivers ed
                        JOIN equipments e ON ed.equipment_id = e.id
                        LEFT JOIN suppliers s ON e.suppliers = s.id
                        WHERE ed.driver_id = $driver_id
                        ORDER BY ed.id DESC
                    ";
                    $history_result = mysqli_query($conn, $history_query);
                    $i = 1;
                    if ($history_result && mysqli_num_rows($history_result) > 0) {
                        while ($row = mysqli_fetch_assoc($history_result)) {
                            $is_current = ($row['status'] == '1');
                            $row_class = $is_current ? 'current-assignment' : '';
                            $status_text = $is_current ? 'يعمل حاليا' : 'سابق';
                            $truck_label = htmlspecialchars($row['name']);
                            if (!empty($row['code'])) {
                                $truck_label .= " (" . htmlspecialchars($row['code']) . ")";
                            }

                            echo "<tr class='" . $row_class . "'>";
                            echo "<td>" . $i++ . "</td>";
                            echo "<td>" . $truck_label . "</td>";
                            echo "<td>" . htmlspecialchars($row['start_date'] ?: '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['end_date'] ?: '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['supplier_name'] ?: '-') . "</td>";
                            echo "<td>" . $status_text . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
            <?php if (!$history_result || mysqli_num_rows($history_result) === 0) { ?>
                <div style="margin-top: 1rem; color: #6c757d;">لا يوجد سجل قيادة للشاحنات لهذا السائق.</div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script>
    (function () {
        $(document).ready(function () {
            $('#historyTable').DataTable({
                responsive: true,
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });
    })();
</script>

</body>
</html>

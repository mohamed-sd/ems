<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once '../includes/driver_contract_dates.php';

$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$driver = null;
// العزل عبر البوابة (كانت القراءتان بلا عزل شركةٍ — تسرّبٌ كامنٌ أُغلق)
$role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$hist_gate = ($role === '-1') ? ems_tenant_db()->forAllTenants('driver history super') : ems_tenant_db();
if ($employee_id > 0) {
    try {
        $driver = $hist_gate->selectOne('employees', array(
            'columns' => array('id', 'name', 'phone', 'status'),
            'where'   => array('id' => $employee_id),
        ));
    } catch (\Throwable $t) {
        $driver = null;
    }
}

if (!$driver) {
    header("Location: employees.php");
    exit();
}

$page_title = "إيكوبيشن | سجل قيادة السائق";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include("../insidebar.php");
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Employee Equipment History', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>

    <div class="history-header">
        <h2><i class="fa fa-history"></i> سجل قيادة السائق</h2>
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
                        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        </tr>
                </thead>
                <tbody>
                    <?php
                    $history_query = "
                        SELECT 
                            ed.id,
                            ed.equipment_id,
                            ed.employee_id,
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
                        WHERE ed.employee_id = $employee_id
                        ORDER BY ed.id DESC
                    ";
                    // JOIN المعدات الداخلي → LEFT + e.id IS NOT NULL (عقد الإثراء)
                    $history_query = str_replace(
                        array('JOIN equipments e ON', "WHERE ed.employee_id = $employee_id"),
                        array('LEFT JOIN equipments e ON', "WHERE {TENANT_SCOPE} AND e.id IS NOT NULL AND ed.employee_id = ?"),
                        $history_query
                    );
                    try {
                        $history_rows = $hist_gate->scopedQuery(array(
                            'scope'  => array('ed' => 'equipment_drivers'),
                            'enrich' => array('e' => 'equipments', 's' => 'suppliers'),
                        ), $history_query, array($employee_id));
                    } catch (\Throwable $t) {
                        $history_rows = array();
                    }
                    $i = 1;
                    if (!empty($history_rows)) {
                        foreach ($history_rows as $row) {
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
                            echo "<td>" . htmlspecialchars(ems_format_open_end($row['end_date'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['supplier_name'] ?: '-') . "</td>";
                            echo "<td>" . $status_text . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
            <?php if (empty($history_rows)) { ?>
                <div style="margin-top: 1rem; color: #6c757d;">لا يوجد سجل قيادة للشاحنات لهذا السائق.</div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
    (function () {
        $(document).ready(function () {
            $('#historyTable').DataTable({
                "language": {
                    "url": "https:/ems/assets/i18n/datatables/ar.json"
                }
            });
        });
    })();
</script>

</body>
</html>




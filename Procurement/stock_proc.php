<?php
/**
 * Procurement/stock_proc.php — المخزون التشغيلي (عرض فقط) — §9 / §15.7.
 * الرصيد محسوب بالتجميع من proc_stock_move (لا حقل رصيد مخزَّن): المتاح = الوارد + المرتجع − المصروف.
 * شاشة جديدة مستقلة للقراءة فقط — لا كتابة على أي جدول.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/stock_proc.php', $is_super_admin);
if (!$perms['can_view']) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+المخزون+❌");
    exit();
}

$scope = proc_scope('m.company_id', $is_super_admin, $company_id);

$page_title = 'إيكوبيشن | المخزون التشغيلي';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main proc-stock-main ems-unified-page-shell">
    <?php
    $header_title = 'المخزون التشغيلي';
    $header_icon  = 'fa fa-warehouse';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="success-message is-success" style="background:#eef6ff;color:#245">
        <i class="fas fa-circle-info"></i>
        القاعدة المحورية: <b>المتاح = الرصيد المادي − المحجوز</b>. الأرصدة هنا محسوبة من حركات المخزون الفعلية (الوارد + المرتجع − المصروف)، لا من رقمٍ مخزَّن.
    </div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الصنف</th><th>المخزن</th><th>الوارد</th><th>المرتجع</th><th>المصروف</th><th>المتاح</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT
                                COALESCE(it.name, CONCAT('#', m.item_id)) AS item_name,
                                COALESCE(w.name, '—') AS wh_name,
                                SUM(CASE WHEN m.move_type='استلام' THEN m.qty ELSE 0 END) AS q_in,
                                SUM(CASE WHEN m.move_type='إرجاع' THEN m.qty ELSE 0 END) AS q_ret,
                                SUM(CASE WHEN m.move_type='صرف' THEN m.qty ELSE 0 END) AS q_out
                            FROM proc_stock_move m
                            LEFT JOIN proc_item it ON it.id = m.item_id
                            LEFT JOIN proc_warehouse w ON w.id = m.warehouse_id
                            WHERE $scope
                            GROUP BY m.item_id, m.warehouse_id, it.name, w.name
                            ORDER BY item_name ASC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $in = (float)$row['q_in']; $ret = (float)$row['q_ret']; $out = (float)$row['q_out'];
                        $avail = $in + $ret - $out;
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars((string)$row['item_name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['wh_name']) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format($in, 2)) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format($ret, 2)) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format($out, 2)) . "</td>";
                        echo "<td><span class='action-btn' style='" . ($avail <= 0 ? 'color:#c0392b' : '') . "'>" . htmlspecialchars(number_format($avail, 2)) . "</span></td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    $('#procTable').DataTable({
        scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: '📋 نسخ' },
            { extend: 'excel', text: '📊 Excel' },
            { extend: 'print', text: '🖨️ طباعة' }
        ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
    });
});
</script>
</body>
</html>

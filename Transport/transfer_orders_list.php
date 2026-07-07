<?php
/**
 * Transport/transfer_orders_list.php — قائمة أوامر الترحيل — §4.
 * DataTables + فلاتر (المرحلة/النوع/الاتجاه/المتحمِّل/المشروع/متأخّر). عرض فقط + دخول للأمر.
 * شاشة جديدة مستقلة — قراءة من الجداول الجديدة + قراءة مرجعية من project/equipments.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx = trs_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌"); exit();
}

$perms = trs_page_perms($conn, 'Transport/transfer_orders_list.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+أوامر+الترحيل+❌"); exit(); }

$scope = $is_super_admin ? '1=1' : ('o.company_id = ' . intval($company_id));
$types_map     = trs_movement_types();
$dir_map       = trs_directions();
$bearer_map    = trs_bearers();
$stage_map     = trs_stages();

$page_title = 'إيكوبيشن | أوامر الترحيل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main trs-orders-main ems-unified-page-shell">
    <?php
    $header_title = 'أوامر الترحيل';
    $header_icon  = 'fa fa-truck-fast';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('tag' => 'a', 'href' => 'transfer_order_form.php', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'أمر جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php trs_msg_banner(); ?>

    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
            <div class="filter-field"><label><i class="fa fa-flag"></i> المرحلة</label>
                <select id="fStage" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label><i class="fa fa-tag"></i> النوع</label>
                <select id="fType" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label><i class="fa fa-arrows-turn-right"></i> الاتجاه</label>
                <select id="fDir" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label><i class="fa fa-hand-holding-dollar"></i> المتحمِّل</label>
                <select id="fBearer" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label><i class="fa fa-diagram-project"></i> المشروع</label>
                <select id="fProject" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-actions">
                <button type="button" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="ordTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>كود الحركة</th><th>النوع</th><th>الاتجاه</th><th>المرحلة</th>
                    <th>المشروع</th><th>من ← إلى</th><th>التاريخ المخطط</th><th>المتحمِّل</th><th>التكلفة (USD)</th><th>متأخّر</th>
                </tr></thead>
                <tbody>
                <?php
                $sql = "SELECT o.id, o.order_no, o.direction, o.stage, o.planned_date, o.cost_bearer, o.actual_cost_usd,
                               tt.name AS type_name, p.name AS project_name,
                               fl.name AS from_name, tl.name AS to_name,
                               (o.planned_date IS NOT NULL AND o.planned_date < CURDATE() AND o.stage NOT IN ('arrived','closed','cancelled')) AS is_delayed
                        FROM transfer_orders o
                        LEFT JOIN transfer_types tt ON tt.id = o.transfer_type_id
                        LEFT JOIN project p ON p.id = o.project_id
                        LEFT JOIN trs_locations fl ON fl.id = o.from_location_id
                        LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
                        WHERE $scope
                        ORDER BY o.id DESC";
                $res = mysqli_query($conn, $sql);
                if ($res) { while ($row = mysqli_fetch_assoc($res)) {
                    $dir_ar    = trs_label($dir_map, $row['direction']);
                    $bearer_ar = $row['cost_bearer'] ? trs_label($bearer_map, $row['cost_bearer']) : '—';
                    $cost      = $row['actual_cost_usd'] !== null ? number_format((float)$row['actual_cost_usd'], 2) : '0.00';
                    $delayed   = (int)$row['is_delayed'] === 1;
                    echo "<tr>";
                    echo "<td><div class='action-btns'><a href='transfer_order_form.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح الأمر'><i class='fas fa-up-right-from-square'></i></a></div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['order_no']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['type_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars($dir_ar) . "</td>";
                    echo "<td>" . trs_stage_badge($row['stage']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['from_name'] ?? '—')) . " ← " . htmlspecialchars((string)($row['to_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['planned_date'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars($bearer_ar) . "</td>";
                    echo "<td>" . $cost . "</td>";
                    echo "<td>" . ($delayed ? "<span class='action-btn' style='color:#c0392b'>متأخّر</span>" : "—") . "</td>";
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
<script>
(function () {
    $(document).ready(function () {
        var t = $('#ordTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip', order: [[1, 'desc']],
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });
        function fill(col, sel) {
            var s = $(sel), vals = [];
            t.column(col).data().each(function (v) {
                var x = $('<div>').html(v).text().trim();
                if (x !== '' && x !== '—' && vals.indexOf(x) === -1) vals.push(x);
            });
            vals.sort(); vals.forEach(function (v) { s.append('<option value="' + v.replace(/"/g, '&quot;') + '">' + v + '</option>'); });
        }
        fill(2, '#fType'); fill(3, '#fDir'); fill(4, '#fStage'); fill(5, '#fProject'); fill(8, '#fBearer');
        function bindCol(sel, col) {
            $(sel).on('change', function () {
                var v = $.fn.dataTable.util.escapeRegex($(this).val());
                t.column(col).search(v ? '^' + v + '$' : '', true, false).draw();
            });
        }
        bindCol('#fType', 2); bindCol('#fDir', 3); bindCol('#fStage', 4); bindCol('#fProject', 5); bindCol('#fBearer', 8);
        $('.filter .btn-ok').on('click', function () { t.draw(); });
        $('.filter .btn-reset').on('click', function () {
            $('#fType,#fDir,#fStage,#fProject,#fBearer').val('');
            t.column(2).search('').column(3).search('').column(4).search('').column(5).search('').column(8).search('').draw();
        });
    });
})();
</script>
</body>
</html>

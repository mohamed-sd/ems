<?php
/**
 * Transport/transfer_orders_list.php — قائمة أوامر الترحيل — §4.
 * DataTables + فلاتر (المرحلة/النوع/الاتجاه/المتحمِّل/المشروع/متأخّر). عرض فقط + دخول للأمر.
 * شاشة جديدة مستقلة — قراءة من الجداول الجديدة + قراءة مرجعية من project/equipments.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx = trs_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', ''); exit();
}

$perms = trs_page_perms($conn, 'Transport/transfer_orders_list.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض أوامر الترحيل ❌', 'GOV-PERM-403', ''); exit(); }

$types_map     = trs_movement_types();
$dir_map       = trs_directions();
$bearer_map    = trs_bearers();
$stage_map     = trs_stages();

$page_title = 'إيكوبيشن | أوامر الترحيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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
            <div class="filter-field"><label for="fStage"><i class="fa fa-flag"></i> المرحلة</label>
                <select id="fStage" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label for="fType"><i class="fa fa-tag"></i> النوع</label>
                <select id="fType" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label for="fDir"><i class="fa fa-arrows-turn-right"></i> الاتجاه</label>
                <select id="fDir" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label for="fBearer"><i class="fa fa-hand-holding-dollar"></i> المتحمِّل</label>
                <select id="fBearer" class="form-control"><option value="">-- الكل --</option></select></div>
            <div class="filter-field"><label for="fProject"><i class="fa fa-diagram-project"></i> المشروع</label>
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
                    <th>الإجراءات</th><th>كود الحركة</th><th>نوع الترحيل</th><th>اتجاه الحركة</th><th>المرحلة</th>
                    <th>المشروع</th><th>من</th><th>التاريخ المخطط</th><th>المتحمل للتكلفة</th><th>التكلفة (USD)</th><th>متأخّر</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم الأمر</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ الإصدار</th>
                    <th class="ems-fn-th" data-fn="1">مرجع الطلب</th>
                    <th class="ems-fn-th" data-fn="1">العناصر المنقولة</th>
                    <th class="ems-fn-th" data-fn="1">إلى</th>
                    <th class="ems-fn-th" data-fn="1">المسافة كم</th>
                    <th class="ems-fn-th" data-fn="1">وسيلة النقل</th>
                    <th class="ems-fn-th" data-fn="1">الناقل</th>
                    <th class="ems-fn-th" data-fn="1">رقم التصريح</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ المغادرة</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ الوصول</th>
                    <th class="ems-fn-th none" data-fn="1">مؤكِّد الوصول</th>
                    <th class="ems-fn-th none" data-fn="1">مستند إثبات الوصول</th>
                    <th class="ems-fn-th none" data-fn="1">قاعدة التحميل</th>
                    <th class="ems-fn-th none" data-fn="1">إجمالي التكلفة</th>
                    <th class="ems-fn-th none" data-fn="1">أصدره</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                    <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                    </tr></thead>
                <tbody>
                <?php
                // scopedQuery (عقد §10): النص الأصلي حرفيًا + {TENANT_SCOPE}؛ 4 إثراءات LEFT
                $order_rows = trs_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('o' => 'transfer_orders'),
                          'enrich' => array('tt' => 'transfer_types', 'p' => 'project',
                                            'fl' => 'trs_locations', 'tl' => 'trs_locations')),
                    "SELECT o.id, o.order_no, o.direction, o.stage, o.planned_date, o.cost_bearer, o.actual_cost_usd,
                            tt.name AS type_name, p.name AS project_name,
                            fl.name AS from_name, tl.name AS to_name,
                            (o.planned_date IS NOT NULL AND o.planned_date < CURDATE() AND o.stage NOT IN ('arrived','closed','cancelled')) AS is_delayed
                     FROM transfer_orders o
                     LEFT JOIN transfer_types tt ON tt.id = o.transfer_type_id
                     LEFT JOIN project p ON p.id = o.project_id
                     LEFT JOIN trs_locations fl ON fl.id = o.from_location_id
                     LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
                     WHERE {TENANT_SCOPE}
                     ORDER BY o.id DESC"
                );
                { foreach ($order_rows as $row) {
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

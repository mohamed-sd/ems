<?php
/**
 * Finance/cost_report_fin.php — التكاليف والربحية (fin_cost_records) — §3.15/§7.4.
 * الربحية = الإيراد − التكلفة (عمود مولّد). تجميع الربحية حسب المشروع.
 * شاشة مستقلة — عزل شركة + حذف ناعم. الأبعاد تُقرأ قراءةً فقط.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/cost_report_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+التكاليف+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$cost_types = fin_cost_types();

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: cost_report_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->softDelete('fin_cost_records', $d);
    header("Location: cost_report_fin.php?msg=تم+حذف+سجلّ+التكلفة+✅"); exit();
}

// ── حفظ سجلّ تكلفة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cost_type'])) {
    if (!$can_add) { header("Location: cost_report_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $cost_type  = trim($_POST['cost_type'] ?? '');
    $project_id = intval($_POST['project_id'] ?? 0) ?: null;
    $equip_id   = intval($_POST['equipment_id'] ?? 0) ?: null;
    $qty        = ($_POST['qty'] ?? '') === '' ? null : round(floatval($_POST['qty']), 2);
    $unit       = trim($_POST['unit'] ?? '');
    $unit_cost  = ($_POST['unit_cost'] ?? '') === '' ? null : round(floatval($_POST['unit_cost']), 4);
    $total_cost = round(floatval($_POST['total_cost'] ?? 0), 2);
    $revenue    = ($_POST['revenue'] ?? '') === '' ? null : round(floatval($_POST['revenue']), 2);
    if (!isset($cost_types[$cost_type]) || $total_cost < 0) { header("Location: cost_report_fin.php?msg=بيانات+غير+صحيحة+❌"); exit(); }

    fin_gate($is_super_admin)->insert('fin_cost_records', array(
        'cost_type' => $cost_type, 'equipment_id' => $equip_id, 'project_id' => $project_id,
        'period_ref' => date('Y-m'), 'qty' => $qty, 'unit' => $unit, 'unit_cost' => $unit_cost,
        'total_cost' => $total_cost, 'revenue' => $revenue, 'created_by' => $current_user_id,
    ));
    header("Location: cost_report_fin.php?msg=تمت+إضافة+سجلّ+التكلفة+✅"); exit();
}

$page_title = 'إيكوبيشن | التكاليف والربحية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main fin-cost-main ems-unified-page-shell">
    <?php
    $header_title = 'التكاليف والربحية'; $header_icon = 'fa fa-coins';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'سجلّ تكلفة'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> سجلّ تكلفة</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>نوع التكلفة <span class="required">*</span></label>
                <select name="cost_type"><?php foreach ($cost_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>المشروع</label><select name="project_id"><?php echo fin_project_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>المعدة</label><select name="equipment_id"><?php echo fin_equipment_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="qty"></div>
            <div class="form-group"><label>الوحدة</label><input type="text" name="unit" placeholder="ساعة/طن/لتر"></div>
            <div class="form-group"><label>تكلفة الوحدة</label><input type="number" step="0.0001" name="unit_cost"></div>
            <div class="form-group"><label>إجمالي التكلفة <span class="required">*</span></label><input type="number" step="0.01" min="0" name="total_cost" required></div>
            <div class="form-group"><label>الإيراد المقابل</label><input type="number" step="0.01" name="revenue"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-chart-line"></i> ربحية المشاريع (تجميع)</h5>
        <div class="table-container">
            <table id="profTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>المشروع</th><th>إجمالي التكلفة</th><th>إجمالي الإيراد</th><th>الربحية</th><th>الهامش</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">العقد</th>
              <th class="ems-fn-th" data-fn="1">المعدة</th>
              <th class="ems-fn-th" data-fn="1">تكلفة مباشرة — مشغّلون</th>
              <th class="ems-fn-th" data-fn="1">وقود</th>
              <th class="ems-fn-th" data-fn="1">صيانة</th>
              <th class="ems-fn-th" data-fn="1">مخزون</th>
              <th class="ems-fn-th" data-fn="1">ترحيل</th>
              <th class="ems-fn-th" data-fn="1">تكلفة غير مباشرة</th>
              <th class="ems-fn-th" data-fn="1">تكلفة تمويل</th>
              <th class="ems-fn-th" data-fn="1">إهلاك</th>
              <th class="ems-fn-th" data-fn="1">نسبة الهامش</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
                <tbody>
                <?php
                // نطاق المشروع للدورين 5/6 (fin_project_scope — يريان موقعهما حصرًا)
                $proj_scope = fin_project_scope($conn, $ctx);
                $cr_whereRaw = "COALESCE(cr.is_deleted,0)=0";
                $cr_params = array();
                if ($proj_scope !== null) { $cr_whereRaw .= " AND cr.project_id = ?"; $cr_params[] = $proj_scope; }
                // D02 م1-①: الآثار تُكتب بعملة عقدها — فالتجميع لكل عملةٍ صفٌّ
                // (لا يُجمع جنيهٌ فوق دولارٍ في رقمٍ واحد)
                $prof_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('cr' => 'fin_cost_records'), 'enrich' => array('p' => 'project')),
                    "SELECT p.name AS project_name, COALESCE(cr.currency,'SDG') AS cur,
                            COALESCE(SUM(cr.total_cost),0) AS tc,
                            COALESCE(SUM(cr.revenue),0) AS tr,
                            COALESCE(SUM(cr.revenue),0) - COALESCE(SUM(cr.total_cost),0) AS profit
                     FROM fin_cost_records cr
                     LEFT JOIN project p ON p.id = cr.project_id
                     WHERE {TENANT_SCOPE} AND " . $cr_whereRaw . "
                     GROUP BY cr.project_id, p.name, cur ORDER BY profit DESC",
                    $cr_params);
                { foreach ($prof_rows as $row) {
                    $tc = (float)$row['tc']; $tr = (float)$row['tr']; $pf = (float)$row['profit'];
                    $margin = $tr > 0 ? ($pf / $tr * 100) : 0;
                    $tone = $pf > 0 ? 'success' : ($pf < 0 ? 'danger' : 'secondary');
                    $curTag = ($row['cur'] !== 'SDG') ? " <span class='badge badge-secondary'>" . htmlspecialchars($row['cur']) . "</span>" : '';
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? 'بلا مشروع')) . $curTag . "</td>";
                    echo "<td>" . number_format($tc, 2) . "</td>";
                    echo "<td>" . number_format($tr, 2) . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . number_format($pf, 2) . "</span></td>";
                    echo "<td>" . number_format($margin, 1) . "%</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-coins"></i> سجلّ التكاليف</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>النوع</th><th>المشروع</th><th>الكمية</th><th>تكلفة الوحدة</th><th>إجمالي التكلفة</th><th>الإيراد المنسوب</th><th>الربحية</th></tr></thead>
                <tbody>
                <?php
                $cost_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('cr' => 'fin_cost_records'), 'enrich' => array('p' => 'project')),
                    "SELECT cr.*, p.name AS project_name FROM fin_cost_records cr
                     LEFT JOIN project p ON p.id = cr.project_id
                     WHERE {TENANT_SCOPE} AND " . $cr_whereRaw . " ORDER BY cr.id DESC",
                    $cr_params);
                { foreach ($cost_rows as $row) {
                    $pf = (float)$row['profit']; $tone = $pf > 0 ? 'success' : ($pf < 0 ? 'danger' : 'secondary');
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) { echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>"; }
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars($cost_types[$row['cost_type']] ?? $row['cost_type']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
                    echo "<td>" . ($row['qty'] !== null ? number_format((float)$row['qty'], 2) . ' ' . htmlspecialchars((string)$row['unit']) : '—') . "</td>";
                    $rowCur = (string)($row['currency'] ?? 'SDG');
                    $curSfx = ($rowCur !== '' && $rowCur !== 'SDG') ? ' <small>' . htmlspecialchars($rowCur) . '</small>' : '';
                    echo "<td>" . ($row['unit_cost'] !== null ? number_format((float)$row['unit_cost'], 2) : '—') . "</td>";
                    echo "<td>" . number_format((float)$row['total_cost'], 2) . $curSfx . "</td>";
                    echo "<td>" . ($row['revenue'] !== null ? number_format((float)$row['revenue'], 2) . $curSfx : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . number_format($pf, 2) . "</span></td>";
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
    $('#finTable, #profTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>

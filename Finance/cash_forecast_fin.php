<?php
/**
 * Finance/cash_forecast_fin.php — السيولة والتنبؤ النقدي (fin_cash_forecasts) — §3.18/§10.
 * الوضع المتوقّع = الافتتاحي + الداخل − الخارج (عمود مولّد). فجوة التمويل وأولوية النقد محسوبتان.
 * «توليد من البيانات الحية» يسحب الداخل من الذمم والخارج من المستحقات وأقساط التمويل.
 * شاشة مستقلة — عزل شركة + حذف ناعم. لا FK للخارج.
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

$perms = fin_page_perms($conn, 'Finance/cash_forecast_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+السيولة+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$horizons = fin_horizon_types(); $priorities = fin_cash_priorities();

/** حساب فجوة التمويل وأولوية النقد من الوضع المتوقّع. */
function fin_gap_and_priority($position, $min_required)
{
    $min = $min_required === null ? 0 : (float)$min_required;
    $gap = ($position < $min) ? round($min - $position, 2) : 0.0;
    if ($position < 0) { $prio = 'critical'; }
    elseif ($position < $min) { $prio = 'high'; }
    else { $prio = 'normal'; }
    return array($gap, $prio);
}

// ── توليد تنبؤ من البيانات الحية ──
if (isset($_GET['generate'])) {
    if (!$can_add) { header("Location: cash_forecast_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $horizon = in_array($_GET['generate'], array('daily','weekly','monthly'), true) ? $_GET['generate'] : 'monthly';
    $win = array('daily' => 1, 'weekly' => 7, 'monthly' => 30);
    $days = $win[$horizon];
    $until = date('Y-m-d', strtotime("+$days days"));

    $gate = fin_gate($is_super_admin);
    $ssum = function ($alias, $table, $sql, $params) use ($gate) {
        $r = $gate->scopedQuery(array('scope' => array($alias => $table)), $sql, $params);
        return $r ? (float) $r[0]['v'] : 0.0;
    };
    $inflow = $ssum('r', 'fin_receivables',
        "SELECT COALESCE(SUM(r.outstanding),0) v FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND (r.due_date IS NULL OR r.due_date<=?)",
        array($until));
    $out_dues = $ssum('d', 'fin_dues',
        "SELECT COALESCE(SUM(d.amount),0) v FROM fin_dues d WHERE {TENANT_SCOPE} AND d.direction='credit' AND d.settlement_state<>'paid' AND COALESCE(d.is_deleted,0)=0", array());
    $out_fund = $ssum('f', 'fin_funding_schedules',
        "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) v FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date<=?",
        array($until));
    $outflow = round($out_dues + $out_fund, 2);

    // نقد افتتاحي = آخر وضع متوقّع سابق أو قيمة تجريبية
    $prev = $gate->selectOne('fin_cash_forecasts', array('columns' => array('expected_position'), 'orderBy' => 'id DESC'));
    $opening = $prev ? (float) $prev['expected_position'] : 10000000.00;
    $min_required = 5000000.00;
    $position = $opening + $inflow - $outflow;
    list($gap, $prio) = fin_gap_and_priority($position, $min_required);
    $today = date('Y-m-d');

    $gate->insert('fin_cash_forecasts', array(
        'forecast_date' => $today, 'horizon_type' => $horizon, 'opening_cash' => $opening,
        'expected_inflow' => $inflow, 'expected_outflow' => $outflow, 'min_required' => $min_required,
        'funding_gap' => $gap, 'cash_priority' => $prio, 'source' => 'manual',
        'note' => 'توليد آلي من البيانات الحية', 'created_by' => $current_user_id,
    ));
    header("Location: cash_forecast_fin.php?msg=تم+توليد+تنبؤ+$horizon+(الوضع=" . number_format($position, 0) . ")+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: cash_forecast_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->softDelete('fin_cash_forecasts', $d);
    header("Location: cash_forecast_fin.php?msg=تم+حذف+التنبؤ+✅"); exit();
}

// ── حفظ تنبؤ يدوي ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['horizon_type'])) {
    if (!$can_add) { header("Location: cash_forecast_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $fdate   = trim($_POST['forecast_date'] ?? '') ?: date('Y-m-d');
    $horizon = in_array($_POST['horizon_type'] ?? '', array('daily','weekly','monthly'), true) ? $_POST['horizon_type'] : 'monthly';
    $opening = round(floatval($_POST['opening_cash'] ?? 0), 2);
    $inflow  = round(floatval($_POST['expected_inflow'] ?? 0), 2);
    $outflow = round(floatval($_POST['expected_outflow'] ?? 0), 2);
    $minreq  = ($_POST['min_required'] ?? '') === '' ? null : round(floatval($_POST['min_required']), 2);
    $position = $opening + $inflow - $outflow;
    list($gap, $prio) = fin_gap_and_priority($position, $minreq);

    fin_gate($is_super_admin)->insert('fin_cash_forecasts', array(
        'forecast_date' => $fdate, 'horizon_type' => $horizon, 'opening_cash' => $opening,
        'expected_inflow' => $inflow, 'expected_outflow' => $outflow, 'min_required' => $minreq,
        'funding_gap' => $gap, 'cash_priority' => $prio, 'source' => 'manual',
        'note' => trim($_POST['note'] ?? ''), 'created_by' => $current_user_id,
    ));
    header("Location: cash_forecast_fin.php?msg=تم+حفظ+التنبؤ+✅"); exit();
}

$page_title = 'إيكوبيشن | السيولة والتنبؤ النقدي';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-cash-main ems-unified-page-shell">
    <?php
    $header_title = 'السيولة والتنبؤ النقدي'; $header_icon = 'fa fa-water';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'تنبؤ يدوي');
        $header_actions[] = array('href' => '?generate=monthly', 'class' => 'add-btn', 'icon' => 'fas fa-bolt', 'label' => 'توليد من البيانات');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> تنبؤ نقدي يدوي</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>التاريخ</label><input type="date" name="forecast_date" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label>الأفق</label><select name="horizon_type"><?php foreach ($horizons as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>النقد الافتتاحي</label><input type="number" step="0.01" name="opening_cash" value="0"></div>
            <div class="form-group"><label>التدفّق الداخل</label><input type="number" step="0.01" name="expected_inflow" value="0"></div>
            <div class="form-group"><label>التدفّق الخارج</label><input type="number" step="0.01" name="expected_outflow" value="0"></div>
            <div class="form-group"><label>الحد الأدنى المطلوب</label><input type="number" step="0.01" name="min_required" value="5000000"></div>
            <div class="form-group" style="grid-column:1/-1"><label>ملاحظة</label><input type="text" name="note"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-water"></i> التنبؤات النقدية</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>التاريخ</th><th>الأفق</th><th>افتتاحي</th><th>داخل</th><th>خارج</th><th>الوضع المتوقّع</th><th>فجوة التمويل</th><th>الأولوية</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
                <tbody>
                <?php
                $fc_rows = fin_gate($is_super_admin)->select('fin_cash_forecasts', array('orderBy' => 'id DESC'));
                { foreach ($fc_rows as $row) {
                    $pos = (float)$row['expected_position']; $gap = (float)($row['funding_gap'] ?? 0);
                    $prio = (string)($row['cash_priority'] ?? 'normal');
                    $pos_tone = $pos < 0 ? 'danger' : ($pos < (float)($row['min_required'] ?? 0) ? 'warn' : 'success');
                    $prio_tone = $prio === 'critical' ? 'danger' : ($prio === 'high' ? 'warn' : 'success');
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['forecast_date']) . "</td>";
                    echo "<td>" . htmlspecialchars($horizons[$row['horizon_type']] ?? $row['horizon_type']) . "</td>";
                    echo "<td>" . number_format((float)$row['opening_cash'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['expected_inflow'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['expected_outflow'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . $pos_tone . "'>" . number_format($pos, 2) . "</span></td>";
                    echo "<td>" . ($gap > 0 ? "<span class='badge badge-danger'>" . number_format($gap, 2) . "</span>" : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $prio_tone . "'>" . htmlspecialchars($priorities[$prio] ?? $prio) . "</span></td>";
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
    $('#finTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>

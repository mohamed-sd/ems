<?php
/**
 * Finance/supplier_statement_fin.php — كشف حساب المورد الشهري (§12.5).
 * ★ تجميع قراءة فقط ★ — بنود المورد له/عليه من fin_dues + مدفوعاته من fin_payments
 * + وحداته المعتمدة من fin_unit_records، بالفترة المختارة. الصافي وحالة التسوية.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/supplier_statement_fin.php', $is_super_admin);
if (!$perms['can_view']) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+العرض+❌"); exit(); }
$cid = intval($company_id);

$sel_sup = isset($_GET['sup']) ? intval($_GET['sup']) : 0;

// H-20: كشفُ المشرف كشفُ موردِه حصرًا — طلبُ غيرِه 403 مسجَّلة، وغيابُ
// المعامل يُحقن بمورده (لا يفتح فارغًا على قائمة الكل)
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
$spg_scope = \App\Services\Portal\SupplierPortalGuard::enforce($conn, $_SESSION['user'], $sel_sup, 'Finance/supplier_statement_fin.php');
if ($spg_scope !== null) { $sel_sup = $spg_scope; }

$sel_period = isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period']) ? $_GET['period'] : date('Y-m');
$due_types = fin_due_types(); $settle_states = fin_settlement_states(); $work_models = fin_work_models();

$sup_name = ''; $credit_sum = 0; $debit_sum = 0; $paid_sum = 0; $units_sum = 0;
// مجموعٌ مُعزَّلٌ عبر scopedQuery (§10): الرمز يحقن company_id، والفلاتر بمعاملات ?
$fin_ssum = function ($alias, $table, $sql, $params) use ($is_super_admin) {
    $rows = fin_gate($is_super_admin)->scopedQuery(array('scope' => array($alias => $table)), $sql, $params);
    return $rows ? (float) $rows[0]['v'] : 0.0;
};
if ($sel_sup > 0) {
    // قراءة الاسم عبر البوابة: includeDeleted لأن الأصل بلا فلتر soft (يعرض اسم
    // المورد المؤرشف — وفاءٌ ذهبي للسلوك، درس الموردين المؤرشفين في M2b). العزل
    // بالشركة تشديدٌ موثَّق (كان بلا فلتر شركة).
    $svrow = fin_gate($is_super_admin)->selectOne('suppliers', array(
        'columns' => array('name'), 'where' => array('id' => $sel_sup), 'includeDeleted' => true));
    $sup_name = $svrow ? $svrow['name'] : ('#' . $sel_sup);
    $credit_sum = $fin_ssum('d', 'fin_dues',
        "SELECT COALESCE(SUM(d.amount),0) v FROM fin_dues d WHERE {TENANT_SCOPE}
         AND d.party_type='supplier' AND d.party_ref=? AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit'
         AND (d.period_ref=? OR DATE_FORMAT(d.created_at,'%Y-%m')=?)",
        array($sel_sup, $sel_period, $sel_period));
    $debit_sum = $fin_ssum('d', 'fin_dues',
        "SELECT COALESCE(SUM(d.amount),0) v FROM fin_dues d WHERE {TENANT_SCOPE}
         AND d.party_type='supplier' AND d.party_ref=? AND COALESCE(d.is_deleted,0)=0 AND d.direction='debit'
         AND (d.period_ref=? OR DATE_FORMAT(d.created_at,'%Y-%m')=?)",
        array($sel_sup, $sel_period, $sel_period));
    $paid_sum = $fin_ssum('p', 'fin_payments',
        "SELECT COALESCE(SUM(p.amount),0) v FROM fin_payments p WHERE {TENANT_SCOPE}
         AND p.party_type='supplier' AND p.party_ref=? AND p.direction='disbursement'
         AND p.state IN('executed','reconciled') AND COALESCE(p.is_deleted,0)=0
         AND DATE_FORMAT(COALESCE(p.paid_at,p.created_at),'%Y-%m')=?",
        array($sel_sup, $sel_period));
    $units_sum = $fin_ssum('u', 'fin_unit_records',
        "SELECT COALESCE(SUM(u.approved_qty),0) v FROM fin_unit_records u WHERE {TENANT_SCOPE}
         AND u.supplier_entity_id=? AND u.match_state='approved' AND COALESCE(u.is_deleted,0)=0
         AND DATE_FORMAT(u.record_date,'%Y-%m')=?",
        array($sel_sup, $sel_period));
}
$net = $credit_sum - $debit_sum;

$page_title = 'إيكوبيشن | كشف حساب المورد';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-supstmt-main ems-unified-page-shell">
    <?php
    $header_title = 'كشف حساب المورد الشهري'; $header_icon = 'fa fa-file-contract';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong><i class="fas fa-truck-field"></i> المورد:</strong>
            <select name="sup" onchange="this.form.submit()" style="min-width:220px"><?php
                // H-20: المقيَّدُ يرى خيارَ موردِه وحده — لا تسريبَ لأسماء بقية الموردين
                if ($spg_scope !== null) {
                    echo '<option value="' . intval($spg_scope) . '" selected>' . htmlspecialchars($sup_name !== '' ? $sup_name : ('#' . intval($spg_scope))) . '</option>';
                } else {
                    echo fin_supplier_options($conn, $is_super_admin, $company_id, $sel_sup);
                }
            ?></select>
            <strong>الفترة:</strong>
            <input type="month" name="period" value="<?php echo htmlspecialchars($sel_period); ?>" onchange="this.form.submit()">
        </form>
    </div></div>

    <?php if ($sel_sup > 0): ?>
    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 12px"><i class="fas fa-file-invoice"></i> كشف <?php echo htmlspecialchars($sup_name); ?> — <?php echo htmlspecialchars($sel_period); ?></h5>
        <div class="form-grid">
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">له (مستحقات)</div><div style="font-size:20px;font-weight:700;color:#166534"><?php echo number_format($credit_sum, 2); ?></div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">عليه (سلف/خصومات/استرداد)</div><div style="font-size:20px;font-weight:700;color:#991b1b">(<?php echo number_format($debit_sum, 2); ?>)</div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">الصافي</div><div style="font-size:20px;font-weight:700"><span class="badge badge-<?php echo $net >= 0 ? 'primary' : 'danger'; ?>"><?php echo number_format($net, 2); ?></span></div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">المصروف له بالفترة</div><div style="font-size:20px;font-weight:700"><?php echo number_format($paid_sum, 2); ?></div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">وحداته المعتمدة بالفترة</div><div style="font-size:20px;font-weight:700"><?php echo number_format($units_sum, 2); ?></div></div></div>
        </div>

        <h5 style="margin:16px 0 10px"><i class="fas fa-list"></i> بنود الكشف (له / عليه)</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>التاريخ</th><th>النوع</th><th>الاتجاه</th><th>المبلغ</th><th>التسوية</th><th>الفترة</th></tr></thead>
                <tbody>
                <?php
                // القائمة عبر البوابة: العزل آلي + فلتر soft آلي (يطابق COALESCE الأصلي)
                $due_rows = fin_gate($is_super_admin)->select('fin_dues', array(
                    'where' => array('party_type' => 'supplier', 'party_ref' => $sel_sup),
                    'whereRaw' => "(period_ref = ? OR DATE_FORMAT(created_at,'%Y-%m') = ?)",
                    'params' => array($sel_period, $sel_period),
                    'orderBy' => 'id DESC',
                ));
                { foreach ($due_rows as $row) {
                    $ss = (string)$row['settlement_state'];
                    $tone = $ss === 'paid' ? 'success' : ($ss === 'settled' ? 'primary' : 'secondary');
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars(substr((string)$row['created_at'], 0, 10)) . "</td>";
                    echo "<td>" . htmlspecialchars($due_types[$row['due_type']] ?? $row['due_type']) . "</td>";
                    echo "<td>" . ($row['direction'] === 'credit' ? "<span class='badge badge-success'>له</span>" : "<span class='badge badge-danger'>عليه</span>") . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($settle_states[$ss] ?? $ss) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['period_ref'] ?? '—')) . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:16px 0 10px"><i class="fas fa-cubes"></i> وحداته المعتمدة بالفترة (من التطابق الثلاثي)</h5>
        <div class="table-container">
            <table class="alltables" style="width:100%">
                <thead><tr><th>الرقم</th><th>التاريخ</th><th>النموذج</th><th>الكمية المعتمدة</th><th>سعر عقده</th><th>قيمة مستحقه</th></tr></thead>
                <tbody>
                <?php
                $ur_rows = fin_gate($is_super_admin)->select('fin_unit_records', array(
                    'where' => array('supplier_entity_id' => $sel_sup, 'match_state' => 'approved'),
                    'whereRaw' => "DATE_FORMAT(record_date,'%Y-%m') = ?",
                    'params' => array($sel_period),
                    'orderBy' => 'record_date DESC',
                ));
                { foreach ($ur_rows as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars((string)$row['record_no']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['record_date']) . "</td>";
                    echo "<td>" . htmlspecialchars($work_models[$row['work_model']] ?? $row['work_model']) . "</td>";
                    echo "<td>" . number_format((float)$row['approved_qty'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['supplier_unit_price'], 2) . "</td>";
                    echo "<td><strong>" . number_format((float)$row['approved_qty'] * (float)$row['supplier_unit_price'], 2) . "</strong></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php else: ?>
    <div class="card"><div class="card-body"><p class="text-muted" style="margin:0"><i class="fas fa-arrow-up"></i> اختر موردًا لعرض كشفه.</p></div></div>
    <?php endif; ?>
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
    if (document.getElementById('finTable')) {
        $('#finTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    }
});
</script>
</body>
</html>

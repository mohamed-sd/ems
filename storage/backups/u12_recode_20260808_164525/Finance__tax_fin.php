<?php
/**
 * Finance/tax_fin.php — الضرائب / القيمة المضافة (fin_tax_codes + fin_tax_transactions).
 * ضريبة مخرجات (مبيعات) − ضريبة مدخلات (مشتريات) = صافي الضريبة المستحقة. مبلغ الضريبة عمود مولّد.
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
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/tax_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الضرائب ❌', 'GOV-PERM-403', ''); exit(); }
$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$tax_types = array('output' => 'مخرجات (مبيعات)', 'input' => 'مدخلات (مشتريات)', 'both' => 'كلاهما');

// ── حفظ رمز ضريبة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tax_code'])) {
    if (!$can_add) { ems_gov_flash_redirect('tax_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $code = trim($_POST['tax_code'] ?? ''); $name = trim($_POST['tax_name'] ?? '');
    $rate = round(floatval($_POST['rate'] ?? 0), 2);
    $type = isset($tax_types[$_POST['tax_type'] ?? '']) ? $_POST['tax_type'] : 'both';
    if ($code === '' || $name === '') { ems_gov_flash_redirect('tax_fin.php', 'بيانات الرمز غير مكتملة ❌', 'GOV-INFO-200', ''); exit(); }
    try {
        fin_gate($is_super_admin)->insert('fin_tax_codes', array(
            'code' => $code, 'name' => $name, 'rate' => $rate, 'tax_type' => $type, 'created_by' => $current_user_id,
        ));
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { ems_gov_flash_redirect('tax_fin.php', 'رمز الضريبة مكرر ❌', 'GOV-INFO-200', ''); exit(); }
        error_log('fin_tax_codes insert refused: ' . $e->getMessage());
        ems_gov_flash_redirect('tax_fin.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-INFO-200', ''); exit();
    }
    ems_gov_flash_redirect('tax_fin.php', 'تمت إضافة رمز الضريبة ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ حركة ضريبية ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direction'])) {
    if (!$can_add) { ems_gov_flash_redirect('tax_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $dir = ($_POST['direction'] ?? '') === 'input' ? 'input' : 'output';
    $tcid = intval($_POST['tax_code_id'] ?? 0) ?: null;
    $base = round(floatval($_POST['base_amount'] ?? 0), 2);
    $rate = round(floatval($_POST['tax_rate'] ?? 0), 2);
    $ref = trim($_POST['source_ref'] ?? '');
    $period = trim($_POST['period_ref'] ?? '') ?: date('Y-m');
    if ($base <= 0) { ems_gov_flash_redirect('tax_fin.php', 'الوعاء غير صحيح ❌', 'GOV-REF-404', ''); exit(); }
    fin_gate($is_super_admin)->insert('fin_tax_transactions', array(
        'tax_code_id' => $tcid, 'direction' => $dir, 'base_amount' => $base, 'tax_rate' => $rate,
        'source_ref' => $ref, 'period_ref' => $period, 'created_by' => $current_user_id,
    ));
    ems_gov_flash_redirect('tax_fin.php', 'تمت إضافة الحركة الضريبية ✅', 'GOV-OK-200', ''); exit();
}

if (isset($_GET['del_code'])) {
    if (!$can_delete) { ems_gov_flash_redirect('tax_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $d = intval($_GET['del_code']);
    fin_gate($is_super_admin)->softDelete('fin_tax_codes', $d);
    ems_gov_flash_redirect('tax_fin.php', 'تم حذف الرمز ✅', 'GOV-OK-200', ''); exit();
}
if (isset($_GET['del_tx'])) {
    if (!$can_delete) { ems_gov_flash_redirect('tax_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $d = intval($_GET['del_tx']);
    fin_gate($is_super_admin)->softDelete('fin_tax_transactions', $d);
    ems_gov_flash_redirect('tax_fin.php', 'تم حذف الحركة ✅', 'GOV-OK-200', ''); exit();
}

// إقرار الضريبة للفترة المختارة (افتراضي: الشهر الحالي)
$sel_period = isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period']) ? $_GET['period'] : date('Y-m');
$tax_ssum = function ($dir) use ($is_super_admin, $sel_period) {
    $r = fin_gate($is_super_admin)->scopedQuery(array('scope' => array('t' => 'fin_tax_transactions')),
        "SELECT COALESCE(SUM(t.tax_amount),0) v FROM fin_tax_transactions t WHERE {TENANT_SCOPE}
         AND COALESCE(t.is_deleted,0)=0 AND t.direction=? AND t.period_ref=?", array($dir, $sel_period));
    return $r ? (float) $r[0]['v'] : 0.0;
};
$out_tax = $tax_ssum('output');
$in_tax  = $tax_ssum('input');
$net_tax = $out_tax - $in_tax;

$page_title = 'إيكوبيشن | الضرائب والقيمة المضافة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main fin-tax-main ems-unified-page-shell">
    <?php
    $header_title = 'الضرائب والقيمة المضافة'; $header_icon = 'fa fa-percent';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleCode', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'رمز ضريبة');
        $header_actions[] = array('id' => 'toggleTx', 'class' => 'add-btn', 'icon' => 'fas fa-receipt', 'label' => 'حركة ضريبية');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <!-- إقرار الضريبة -->
    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
            <strong><i class="fas fa-file-invoice"></i> إقرار الضريبة للفترة:</strong>
            <input type="month" name="period" value="<?php echo htmlspecialchars($sel_period); ?>" onchange="this.form.submit()">
        </form>
        <div class="form-grid">
            <div class="card" style="text-align:center"><div class="card-body">
                <div class="text-muted">ضريبة المخرجات (مبيعات)</div>
                <div style="font-size:22px;font-weight:700;margin:6px 0"><?php echo number_format($out_tax, 2); ?></div>
            </div></div>
            <div class="card" style="text-align:center"><div class="card-body">
                <div class="text-muted">ضريبة المدخلات (مشتريات)</div>
                <div style="font-size:22px;font-weight:700;margin:6px 0">(<?php echo number_format($in_tax, 2); ?>)</div>
            </div></div>
            <div class="card" style="text-align:center"><div class="card-body">
                <div class="text-muted"><?php echo $net_tax >= 0 ? 'صافي الضريبة المستحقة للسداد' : 'رصيد ضريبي قابل للاسترداد'; ?></div>
                <div style="font-size:22px;font-weight:700;margin:6px 0"><span class="badge badge-<?php echo $net_tax >= 0 ? 'danger' : 'success'; ?>"><?php echo number_format(abs($net_tax), 2); ?></span></div>
            </div></div>
        </div>
    </div></div>

    <!-- فورم رمز -->
    <form id="codeForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-percent"></i> رمز ضريبة</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>الكود <span class="required">*</span></label><input type="text" name="tax_code" required placeholder="VAT15"></div>
            <div class="form-group"><label>الاسم <span class="required">*</span></label><input type="text" name="tax_name" required></div>
            <div class="form-group"><label>النسبة %</label><input type="number" step="0.01" name="rate" value="15"></div>
            <div class="form-group"><label>النوع</label><select name="tax_type"><?php foreach ($tax_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#codeForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <!-- فورم حركة -->
    <form id="txForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-receipt"></i> حركة ضريبية</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>النوع</label><select name="direction"><option value="output">مخرجات (مبيعات)</option><option value="input">مدخلات (مشتريات)</option></select></div>
            <div class="form-group"><label>رمز الضريبة</label>
                <select name="tax_code_id" id="tx_code"><option value="">— بلا —</option>
                <?php $code_opts = fin_gate($is_super_admin)->scopedQuery(array('scope' => array('t' => 'fin_tax_codes')),
                    "SELECT t.id, CONCAT(t.code,' (',t.rate,'%)') AS label, t.rate FROM fin_tax_codes t
                     WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0)=0 AND t.active=1 ORDER BY t.code");
                foreach ($code_opts as $x) { echo "<option value='" . intval($x['id']) . "' data-rate='" . htmlspecialchars($x['rate']) . "'>" . htmlspecialchars($x['label']) . "</option>"; } ?>
                </select></div>
            <div class="form-group"><label>الوعاء الضريبي <span class="required">*</span></label><input type="number" step="0.01" min="0" name="base_amount" required></div>
            <div class="form-group"><label>النسبة %</label><input type="number" step="0.01" name="tax_rate" id="tx_rate" value="15"></div>
            <div class="form-group"><label>المرجع</label><input type="text" name="source_ref" placeholder="فاتورة/أمر"></div>
            <div class="form-group"><label>الفترة</label><input type="month" name="period_ref" value="<?php echo date('Y-m'); ?>"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#txForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-list"></i> الحركات الضريبية</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>النوع</th><th>الرمز</th><th>الوعاء</th><th>النسبة</th><th>الضريبة</th><th>المرجع</th><th>الفترة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
                <tbody>
                <?php
                $tx_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('t' => 'fin_tax_transactions'), 'enrich' => array('c' => 'fin_tax_codes')),
                    "SELECT t.*, c.code AS code FROM fin_tax_transactions t LEFT JOIN fin_tax_codes c ON c.id=t.tax_code_id
                     WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0)=0 ORDER BY t.id DESC");
                { foreach ($tx_rows as $row) {
                    $dir = $row['direction'] === 'input' ? 'مدخلات' : 'مخرجات';
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_tx=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td><span class='badge badge-" . ($row['direction'] === 'output' ? 'primary' : 'secondary') . "'>" . $dir . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['code'] ?? '—')) . "</td>";
                    echo "<td>" . number_format((float)$row['base_amount'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['tax_rate'], 2) . "%</td>";
                    echo "<td><strong>" . number_format((float)$row['tax_amount'], 2) . "</strong></td>";
                    echo "<td>" . htmlspecialchars((string)($row['source_ref'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['period_ref']) . "</td>";
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
    $('#toggleCode').on('click', function () { $('#codeForm').toggleClass('allforms-visible'); });
    $('#toggleTx').on('click', function () { $('#txForm').toggleClass('allforms-visible'); });
    $('#tx_code').on('change', function () { var r = $(this).find(':selected').data('rate'); if (r !== undefined && r !== '') $('#tx_rate').val(r); });
});
</script>
</body>
</html>

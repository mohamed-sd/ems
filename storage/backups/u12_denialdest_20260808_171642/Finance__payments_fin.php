<?php
/**
 * Finance/payments_fin.php — المدفوعات والخزينة (fin_payments) — §3.14/§7.3.
 * لا صرفَ لموردٍ قبل تسوية مستحقاته كاملةً (يُفرَض خادميّاً عند التنفيذ).
 * التحصيل المرتبط بذمّة يحدّث المحصّل والمتبقّي. شاشة مستقلة — عزل شركة + حذف ناعم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/payments_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المدفوعات ❌', 'GOV-PERM-403', ''); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$directions = fin_payment_directions(); $methods = fin_payment_methods(); $party_types = fin_party_types();

// ── تنفيذ الدفع/التحصيل (محرك الخزينة + حارس التسوية) ──
if (isset($_GET['execute_id'])) {
    if (!$can_edit) { ems_gov_flash_redirect('payments_fin.php', 'لا توجد صلاحية التنفيذ ❌', 'GOV-PERM-403', ''); exit(); }
    if (!fin_verify_action_token()) { ems_gov_flash_redirect('payments_fin.php', 'رمز الحماية غير صالح ❌', 'GOV-FAIL-409', ''); exit(); } // إصلاح #2
    if (!fin_can_perform($conn, $ctx['role'], 'treasurer')) { ems_gov_flash_redirect('payments_fin.php', 'الصرف/التحصيل يخصّ أمين الخزينة فقط ❌', 'GOV-FAIL-409', ''); exit(); } // فصل الواجبات
    $pid = intval($_GET['execute_id']);
    $gate = fin_gate($is_super_admin);
    $pay = $gate->selectOne('fin_payments', array('where' => array('id' => $pid)));
    if (!$pay || $pay['state'] !== 'draft') { ems_gov_flash_redirect('payments_fin.php', 'لا يمكن تنفيذ هذا الدفع ❌', 'GOV-FAIL-409', ''); exit(); }

    // إصلاح #9: لا يتجاوز التحصيل المتبقّي على الذمّة
    $recv = null;
    if ($pay['direction'] === 'collection' && intval($pay['receivable_id']) > 0) {
        $rid = intval($pay['receivable_id']);
        $recv = $gate->selectOne('fin_receivables', array('columns' => array('outstanding', 'collected', 'amount'), 'where' => array('id' => $rid)));
        $out = $recv ? (float) $recv['outstanding'] : 0;
        if ((float)$pay['amount'] > $out + 0.01) {
            ems_gov_flash_redirect(ems_flash_to('payments_fin.php', number_format($out, 2) . ")+❌"), 'لا تنفيذ: المبلغ يتجاوز المتبقّي على الذمّة (', 'GOV-INFO-200', ''); exit();
        }
    }

    // حارس: لا صرفَ لموردٍ قبل تسوية مستحقاته كاملةً
    if ($pay['direction'] === 'disbursement' && $pay['party_type'] === 'supplier') {
        $sref = intval($pay['party_ref']);
        $n = $gate->count('fin_dues', array('where' => array('party_type' => 'supplier', 'party_ref' => $sref, 'settlement_state' => 'pending')));
        if ($n > 0) { ems_gov_flash_redirect('payments_fin.php', 'لا صرف: للمورد مستحقات غير مُسوّاة ($n) ❌', 'GOV-FAIL-409', ''); exit(); }
    }

    // التنفيذ + أثره ذرّيًا (§9): الدفع + (تسوية المورد أو تحديث الذمّة) معًا
    $gate->runInTransaction(function ($g) use ($pid, $current_user_id, $pay, $recv) {
        $g->update('fin_payments', array('state' => 'executed', 'paid_at' => date('Y-m-d H:i:s'), 'executed_by' => $current_user_id), array('id' => $pid));
        if ($pay['direction'] === 'disbursement' && $pay['party_type'] === 'supplier') {
            $g->update('fin_dues', array('settlement_state' => 'paid'),
                array('party_type' => 'supplier', 'party_ref' => intval($pay['party_ref']), 'settlement_state' => 'settled'));
        }
        if ($pay['direction'] === 'collection' && intval($pay['receivable_id']) > 0 && $recv) {
            // تعبيرا LEAST/CASE محسوبان PHP-side (نفس نتيجة MySQL يسارًا→يمينًا)
            $amt = (float) $pay['amount']; $amount = (float) $recv['amount'];
            $new_collected = min($amount, (float) $recv['collected'] + $amt);
            $new_state = ($new_collected >= $amount) ? 'collected' : 'partial';
            $g->update('fin_receivables', array('collected' => $new_collected, 'state' => $new_state), array('id' => intval($pay['receivable_id'])));
        }
    }, 'payment execute + effect');
    // §9.3: حقيقة الخزينة على الجذر — الأداء وقع لحدثِ طلبٍ (إن كانت الدفعة مربوطة)
    if (!empty($pay['event_id'])) {
        $tKey = $pay['direction'] === 'collection' ? 'treasury.collected' : 'treasury.paid';
        fin_publish_request_fact($conn, intval($pay['event_id']), $tKey,
            $pay['direction'] === 'collection' ? 'collected' : 'paid',
            array('payment_no' => strval($pay['payment_no']), 'method' => strval($pay['method'])));
    }
    // (فجوة 4) إشعار المدير المالي بحركة الخزينة
    $dir_lbl = $pay['direction'] === 'collection' ? 'تحصيل' : 'صرف';
    fin_notify($conn, $company_id, 'finance_manager', 'نُفِّذ ' . $dir_lbl . ' ' . $pay['payment_no'] . ' بمبلغ ' . number_format((float)$pay['amount'], 0), 'payments_fin.php');
    ems_gov_flash_redirect('payments_fin.php', 'تم تنفيذ الحركة بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── حذف ناعم (مسودة فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('payments_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $d = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->update('fin_payments',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $d), "state='draft'");
    ems_gov_flash_redirect('payments_fin.php', 'تم حذف الحركة ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ حركة جديدة (مسودة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direction'])) {
    if (!$can_add) { ems_gov_flash_redirect('payments_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $direction  = ($_POST['direction'] ?? '') === 'collection' ? 'collection' : 'disbursement';
    $party_type = in_array($_POST['party_type'] ?? '', array('supplier','customer','employee','other'), true) ? $_POST['party_type'] : 'other';
    $party_ref  = intval($_POST['party_ref'] ?? 0) ?: null;
    $method     = in_array($_POST['method'] ?? '', array('cash','bank','transfer','cheque'), true) ? $_POST['method'] : 'bank';
    $amount     = round(floatval($_POST['amount'] ?? 0), 2);
    $receivable_id = intval($_POST['receivable_id'] ?? 0) ?: null;
    $memo       = trim($_POST['memo'] ?? '');
    if ($amount <= 0) { ems_gov_flash_redirect('payments_fin.php', 'المبلغ غير صحيح ❌', 'GOV-REF-404', ''); exit(); }

    $payment_no = fin_gen_code($conn, 'fin_payments', 'FIN-PY', $company_id);
    fin_gate($is_super_admin)->insert('fin_payments', array(
        'payment_no' => $payment_no, 'direction' => $direction, 'party_type' => $party_type,
        'party_ref' => $party_ref, 'method' => $method, 'amount' => $amount,
        'receivable_id' => $receivable_id, 'memo' => $memo, 'state' => 'draft', 'created_by' => $current_user_id,
    ));
    ems_gov_flash_redirect('payments_fin.php', 'تم حفظ الحركة (مسودة) ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | المدفوعات والخزينة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main fin-pay-main ems-unified-page-shell">
    <?php
    $header_title = 'المدفوعات والخزينة'; $header_icon = 'fa fa-money-bill-transfer';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'حركة خزينة'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-money-bill-transfer"></i> حركة صرف / تحصيل</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>الاتجاه <span class="required">*</span></label>
                <select name="direction"><?php foreach ($directions as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>نوع الطرف</label>
                <select name="party_type" id="p_ptype"><?php foreach ($party_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>المورد</label><select name="party_ref" id="p_sup"><?php echo fin_supplier_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>طريقة الدفع</label>
                <select name="method"><?php foreach ($methods as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>المبلغ <span class="required">*</span></label><input type="number" step="0.01" min="0" name="amount" required></div>
            <div class="form-group"><label>ذمّة عميل (للتحصيل)</label>
                <select name="receivable_id"><option value="">— بلا —</option>
                <?php
                $recv_opts = fin_gate($is_super_admin)->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
                    "SELECT r.id, CONCAT(COALESCE(r.doc_ref,CONCAT('ذمّة #',r.id)),' — متبقّي ',r.outstanding) AS label
                     FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 ORDER BY r.id DESC");
                foreach ($recv_opts as $x) { echo "<option value='" . intval($x['id']) . "'>" . htmlspecialchars($x['label']) . "</option>"; }
                ?></select></div>
            <div class="form-group" style="grid-column:1/-1"><label>بيان</label><input type="text" name="memo"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>رقم الطلب</th><th>الاتجاه</th><th>الطرف</th><th>الطريقة</th><th>المبلغ</th><th>البيان</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
              <th class="ems-fn-th" data-fn="1">الإدارة الطالبة</th>
              <th class="ems-fn-th" data-fn="1">نوع المستفيد</th>
              <th class="ems-fn-th" data-fn="1">المستفيد</th>
              <th class="ems-fn-th" data-fn="1">الحساب البنكي</th>
              <th class="ems-fn-th" data-fn="1">البنك</th>
              <th class="ems-fn-th" data-fn="1">مصدر التمويل المستخدم</th>
              <th class="ems-fn-th" data-fn="1">المرجع</th>
              <th class="ems-fn-th" data-fn="1">وصف الصرف</th>
              <th class="ems-fn-th" data-fn="1">المعادل بعملة الدفاتر</th>
              <th class="ems-fn-th" data-fn="1">بند الموازنة</th>
              <th class="ems-fn-th" data-fn="1">المتاح قبل</th>
              <th class="ems-fn-th" data-fn="1">قدّمه</th>
              <th class="ems-fn-th" data-fn="1">اعتماد الإدارة</th>
              <th class="ems-fn-th none" data-fn="1">الاعتماد المالي</th>
              <th class="ems-fn-th none" data-fn="1">اعتماد الإدارة العامة</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ السداد</th>
              <th class="ems-fn-th none" data-fn="1">رقم سند الصرف</th>
              <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
              </tr></thead>
                <tbody>
                <?php
                // نطاق نوع الطرف (fin_party_scope): الموردون/المشتريات يرون دفعات
                // الموردين حصرًا — لا رواتب موظفين ولا تحصيلات عملاء. المالية ترى الكل.
                $party_scope = fin_party_scope($ctx);
                $pay_opts = array('orderBy' => 'id DESC');
                if ($party_scope !== null) {
                    $pay_opts['whereRaw'] = 'party_type = ?';
                    $pay_opts['params'] = array($party_scope);
                }
                $pay_rows = fin_gate($is_super_admin)->select('fin_payments', $pay_opts);
                { foreach ($pay_rows as $row) {
                    $st = (string)$row['state'];
                    $st_lbl = array('draft' => 'مسودة', 'approved' => 'معتمدة', 'executed' => 'منفّذة', 'reconciled' => 'مطابَقة');
                    $st_tone = $st === 'executed' ? 'success' : ($st === 'reconciled' ? 'dark' : 'secondary');
                    $dir = $row['direction'] === 'collection' ? 'تحصيل' : 'صرف';
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_edit && $st === 'draft') {
                        echo "<a href='?execute_id=" . intval($row['id']) . "&_t=" . fin_action_token() . "' class='action-btn edit' title='تنفيذ' onclick='return confirm(\"تنفيذ الحركة؟ (لا صرف قبل تسوية المورد)\")'><i class='fas fa-circle-check'></i></a>";
                    }
                    if ($can_delete && $st === 'draft') {
                        echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    }
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['payment_no']) . "</td>";
                    echo "<td>" . $dir . "</td>";
                    echo "<td>" . htmlspecialchars($party_types[$row['party_type']] ?? $row['party_type']) . "</td>";
                    echo "<td>" . htmlspecialchars($methods[$row['method']] ?? $row['method']) . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['memo'] ?? '')) . "</td>";
                    echo "<td><span class='badge badge-" . $st_tone . "'>" . ($st_lbl[$st] ?? $st) . "</span></td>";
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

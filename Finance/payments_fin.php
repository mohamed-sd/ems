<?php
/**
 * Finance/payments_fin.php — المدفوعات والخزينة (fin_payments) — §3.14/§7.3.
 * لا صرفَ لموردٍ قبل تسوية مستحقاته كاملةً (يُفرَض خادميّاً عند التنفيذ).
 * التحصيل المرتبط بذمّة يحدّث المحصّل والمتبقّي. شاشة مستقلة — عزل شركة + حذف ناعم.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/payments_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+المدفوعات+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$directions = fin_payment_directions(); $methods = fin_payment_methods(); $party_types = fin_party_types();

// ── تنفيذ الدفع/التحصيل (محرك الخزينة + حارس التسوية) ──
if (isset($_GET['execute_id'])) {
    if (!$can_edit) { header("Location: payments_fin.php?msg=لا+توجد+صلاحية+التنفيذ+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: payments_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); } // إصلاح #2
    if (!fin_can_perform($conn, $ctx['role'], 'treasurer')) { header("Location: payments_fin.php?msg=الصرف/التحصيل+يخصّ+أمين+الخزينة+فقط+❌"); exit(); } // فصل الواجبات
    $pid = intval($_GET['execute_id']);
    $pr = mysqli_query($conn, "SELECT * FROM fin_payments WHERE id=$pid AND company_id=$company_id AND COALESCE(is_deleted,0)=0 LIMIT 1");
    $pay = $pr ? mysqli_fetch_assoc($pr) : null;
    if (!$pay || $pay['state'] !== 'draft') { header("Location: payments_fin.php?msg=لا+يمكن+تنفيذ+هذا+الدفع+❌"); exit(); }

    // إصلاح #9: لا يتجاوز التحصيل المتبقّي على الذمّة
    if ($pay['direction'] === 'collection' && intval($pay['receivable_id']) > 0) {
        $rid = intval($pay['receivable_id']);
        $orow = mysqli_query($conn, "SELECT outstanding FROM fin_receivables WHERE id=$rid AND company_id=$company_id LIMIT 1");
        $out = ($orow && ($o = mysqli_fetch_assoc($orow))) ? (float)$o['outstanding'] : 0;
        if ((float)$pay['amount'] > $out + 0.01) {
            header("Location: payments_fin.php?msg=لا+تنفيذ:+المبلغ+يتجاوز+المتبقّي+على+الذمّة+(" . number_format($out, 2) . ")+❌"); exit();
        }
    }

    // حارس: لا صرفَ لموردٍ قبل تسوية مستحقاته كاملةً
    if ($pay['direction'] === 'disbursement' && $pay['party_type'] === 'supplier') {
        $sref = intval($pay['party_ref']);
        $chk = mysqli_query($conn, "SELECT COUNT(*) AS n FROM fin_dues
                                    WHERE company_id=$company_id AND party_type='supplier' AND party_ref=$sref
                                      AND settlement_state='pending' AND COALESCE(is_deleted,0)=0");
        $n = ($chk && ($r = mysqli_fetch_assoc($chk))) ? intval($r['n']) : 0;
        if ($n > 0) { header("Location: payments_fin.php?msg=لا+صرف:+للمورد+مستحقات+غير+مُسوّاة+($n)+❌"); exit(); }
    }

    mysqli_query($conn, "UPDATE fin_payments SET state='executed', paid_at=NOW(), executed_by=$current_user_id WHERE id=$pid AND company_id=$company_id");
    // أثر: صرف لمورد ⇒ مستحقاته المسوّاة تصبح مصروفة؛ تحصيل مرتبط بذمّة ⇒ تحديث المحصّل
    if ($pay['direction'] === 'disbursement' && $pay['party_type'] === 'supplier') {
        $sref = intval($pay['party_ref']);
        mysqli_query($conn, "UPDATE fin_dues SET settlement_state='paid' WHERE company_id=$company_id AND party_type='supplier' AND party_ref=$sref AND settlement_state='settled'");
    }
    if ($pay['direction'] === 'collection' && intval($pay['receivable_id']) > 0) {
        $rid = intval($pay['receivable_id']); $amt = (float)$pay['amount'];
        mysqli_query($conn, "UPDATE fin_receivables SET collected = LEAST(amount, collected + $amt),
                             state = CASE WHEN collected + $amt >= amount THEN 'collected' ELSE 'partial' END
                             WHERE id=$rid AND company_id=$company_id");
    }
    header("Location: payments_fin.php?msg=تم+تنفيذ+الحركة+بنجاح+✅"); exit();
}

// ── حذف ناعم (مسودة فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: payments_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_id']);
    mysqli_query($conn, "UPDATE fin_payments SET is_deleted=1, deleted_at=NOW(), deleted_by=$current_user_id WHERE id=$d AND company_id=$company_id AND state='draft'");
    header("Location: payments_fin.php?msg=تم+حذف+الحركة+✅"); exit();
}

// ── حفظ حركة جديدة (مسودة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direction'])) {
    if (!$can_add) { header("Location: payments_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $direction  = ($_POST['direction'] ?? '') === 'collection' ? 'collection' : 'disbursement';
    $party_type = in_array($_POST['party_type'] ?? '', array('supplier','customer','employee','other'), true) ? $_POST['party_type'] : 'other';
    $party_ref  = intval($_POST['party_ref'] ?? 0) ?: null;
    $method     = in_array($_POST['method'] ?? '', array('cash','bank','transfer','cheque'), true) ? $_POST['method'] : 'bank';
    $amount     = round(floatval($_POST['amount'] ?? 0), 2);
    $receivable_id = intval($_POST['receivable_id'] ?? 0) ?: null;
    $memo       = trim($_POST['memo'] ?? '');
    if ($amount <= 0) { header("Location: payments_fin.php?msg=المبلغ+غير+صحيح+❌"); exit(); }

    $payment_no = fin_gen_code($conn, 'fin_payments', 'FIN-PY', $company_id);
    $sql = "INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref, method, amount, receivable_id, memo, state, created_by)
            VALUES (?,?,?,?,?,?,?,?,?, 'draft', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isssisdisi', $company_id, $payment_no, $direction, $party_type, $party_ref, $method, $amount, $receivable_id, $memo, $current_user_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: payments_fin.php?msg=تم+حفظ+الحركة+(مسودة)+✅"); exit();
}

$page_title = 'إيكوبيشن | المدفوعات والخزينة';
include '../inheader.php';
include '../insidebar.php';
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
                $rq = mysqli_query($conn, "SELECT id, CONCAT(COALESCE(doc_ref,CONCAT('ذمّة #',id)),' — متبقّي ',outstanding) AS label FROM fin_receivables WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0 AND outstanding>0 ORDER BY id DESC");
                if ($rq) { while ($x = mysqli_fetch_assoc($rq)) echo "<option value='" . intval($x['id']) . "'>" . htmlspecialchars($x['label']) . "</option>"; }
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
                <thead><tr><th>الإجراءات</th><th>الرقم</th><th>الاتجاه</th><th>الطرف</th><th>الطريقة</th><th>المبلغ</th><th>البيان</th><th>الحالة</th></tr></thead>
                <tbody>
                <?php
                $scope_p = fin_scope('company_id', $is_super_admin, $company_id);
                $sql = "SELECT * FROM fin_payments WHERE $scope_p AND COALESCE(is_deleted,0)=0 ORDER BY id DESC";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
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

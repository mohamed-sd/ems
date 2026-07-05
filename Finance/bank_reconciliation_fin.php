<?php
/**
 * Finance/bank_reconciliation_fin.php — المطابقة البنكية.
 * fin_bank_accounts + fin_bank_statement_lines؛ مطابقة بنود الكشف مع fin_payments المنفّذة.
 * مطابقة آلية بالمبلغ+الاتجاه. رصيد البنك مقابل رصيد الدفاتر. شاشة مستقلة — لا FK للخارج.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/bank_reconciliation_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+المطابقة+❌"); exit(); }
$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$sel_acct = isset($_GET['acct']) ? intval($_GET['acct']) : 0;

// ── مطابقة آلية بالمبلغ والاتجاه (CSRF) ──
if (isset($_GET['automatch']) && $sel_acct > 0) {
    if (!$can_edit) { header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=لا+توجد+صلاحية+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=رمز+الحماية+غير+صالح+❌"); exit(); }
    $matched = 0;
    $lines = mysqli_query($conn, "SELECT id, direction, amount FROM fin_bank_statement_lines
                                  WHERE company_id=$company_id AND bank_account_id=$sel_acct AND reconciled=0 ORDER BY id");
    if ($lines) { while ($ln = mysqli_fetch_assoc($lines)) {
        $want = $ln['direction'] === 'deposit' ? 'collection' : 'disbursement';
        $amt = (float)$ln['amount'];
        // دفعة منفّذة بالمبلغ نفسه لم تُطابَق بعد
        $pq = mysqli_query($conn, "SELECT p.id FROM fin_payments p
            WHERE p.company_id=$company_id AND COALESCE(p.is_deleted,0)=0 AND p.state IN('executed','reconciled')
              AND p.direction='$want' AND ABS(p.amount - $amt) < 0.01
              AND NOT EXISTS (SELECT 1 FROM fin_bank_statement_lines b WHERE b.company_id=$company_id AND b.matched_payment_id=p.id)
            ORDER BY p.id LIMIT 1");
        if ($pq && ($p = mysqli_fetch_assoc($pq))) {
            $pid = intval($p['id']);
            mysqli_query($conn, "UPDATE fin_bank_statement_lines SET matched_payment_id=$pid, reconciled=1 WHERE id=" . intval($ln['id']) . " AND company_id=$company_id");
            mysqli_query($conn, "UPDATE fin_payments SET state='reconciled' WHERE id=$pid AND company_id=$company_id AND state='executed'");
            $matched++;
        }
    } }
    header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=تمت+مطابقة+$matched+بند+آليًا+✅"); exit();
}

// ── إلغاء مطابقة بند ──
if (isset($_GET['unmatch_line'])) {
    if (!$can_edit) { header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=لا+توجد+صلاحية+❌"); exit(); }
    $lid = intval($_GET['unmatch_line']);
    $pid = (int) (mysqli_query($conn, "SELECT matched_payment_id FROM fin_bank_statement_lines WHERE id=$lid AND company_id=$company_id")->fetch_assoc()['matched_payment_id'] ?? 0);
    mysqli_query($conn, "UPDATE fin_bank_statement_lines SET matched_payment_id=NULL, reconciled=0 WHERE id=$lid AND company_id=$company_id");
    if ($pid > 0) mysqli_query($conn, "UPDATE fin_payments SET state='executed' WHERE id=$pid AND company_id=$company_id AND state='reconciled'");
    header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=تم+إلغاء+المطابقة+✅"); exit();
}

// ── حفظ حساب بنكي ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_name'])) {
    if (!$can_add) { header("Location: bank_reconciliation_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $name = trim($_POST['acct_name'] ?? ''); $bank = trim($_POST['bank_name'] ?? '');
    $accno = trim($_POST['account_number'] ?? ''); $open = round(floatval($_POST['opening_balance'] ?? 0), 2);
    if ($name === '') { header("Location: bank_reconciliation_fin.php?msg=اسم+الحساب+مطلوب+❌"); exit(); }
    $sql = "INSERT INTO fin_bank_accounts (company_id, name, bank_name, account_number, opening_balance, created_by) VALUES (?,?,?,?,?,?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isssdi', $company_id, $name, $bank, $accno, $open, $current_user_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: bank_reconciliation_fin.php?msg=تمت+إضافة+الحساب+البنكي+✅"); exit();
}

// ── حفظ بند كشف ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direction'])) {
    if (!$can_add) { header("Location: bank_reconciliation_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $ba = intval($_POST['bank_account_id'] ?? 0);
    $dir = ($_POST['direction'] ?? '') === 'withdrawal' ? 'withdrawal' : 'deposit';
    $date = trim($_POST['txn_date'] ?? '') ?: date('Y-m-d');
    $desc = trim($_POST['description'] ?? '');
    $amt = round(floatval($_POST['amount'] ?? 0), 2);
    if ($ba <= 0 || $amt <= 0) { header("Location: bank_reconciliation_fin.php?acct=$ba&msg=بيانات+البند+غير+صحيحة+❌"); exit(); }
    $sql = "INSERT INTO fin_bank_statement_lines (company_id, bank_account_id, txn_date, description, direction, amount, created_by) VALUES (?,?,?,?,?,?,?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iisssdi', $company_id, $ba, $date, $desc, $dir, $amt, $current_user_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: bank_reconciliation_fin.php?acct=$ba&msg=تمت+إضافة+البند+✅"); exit();
}

if (isset($_GET['del_acct'])) {
    if (!$can_delete) { header("Location: bank_reconciliation_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    mysqli_query($conn, "UPDATE fin_bank_accounts SET is_deleted=1, deleted_at=NOW(), deleted_by=$current_user_id WHERE id=" . intval($_GET['del_acct']) . " AND company_id=$company_id");
    header("Location: bank_reconciliation_fin.php?msg=تم+حذف+الحساب+✅"); exit();
}

// ملخّص المطابقة للحساب المختار
$bank_balance = 0; $book_movement = 0; $unrec_lines = 0; $unmatched_pay = 0; $rec_lines = 0;
if ($sel_acct > 0) {
    $acc = mysqli_query($conn, "SELECT * FROM fin_bank_accounts WHERE id=$sel_acct AND company_id=$company_id AND COALESCE(is_deleted,0)=0 LIMIT 1");
    $acc = $acc ? mysqli_fetch_assoc($acc) : null;
    if ($acc) {
        $opening = (float)$acc['opening_balance'];
        $dep = (float) (mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) v FROM fin_bank_statement_lines WHERE company_id=$company_id AND bank_account_id=$sel_acct AND direction='deposit'")->fetch_assoc()['v'] ?? 0);
        $wd  = (float) (mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) v FROM fin_bank_statement_lines WHERE company_id=$company_id AND bank_account_id=$sel_acct AND direction='withdrawal'")->fetch_assoc()['v'] ?? 0);
        $bank_balance = $opening + $dep - $wd;
        $rec_lines   = (int) (mysqli_query($conn, "SELECT COUNT(*) c FROM fin_bank_statement_lines WHERE company_id=$company_id AND bank_account_id=$sel_acct AND reconciled=1")->fetch_assoc()['c'] ?? 0);
        $unrec_lines = (int) (mysqli_query($conn, "SELECT COUNT(*) c FROM fin_bank_statement_lines WHERE company_id=$company_id AND bank_account_id=$sel_acct AND reconciled=0")->fetch_assoc()['c'] ?? 0);
        $unmatched_pay = (int) (mysqli_query($conn, "SELECT COUNT(*) c FROM fin_payments p WHERE p.company_id=$company_id AND COALESCE(p.is_deleted,0)=0 AND p.state IN('executed','reconciled') AND NOT EXISTS (SELECT 1 FROM fin_bank_statement_lines b WHERE b.company_id=$company_id AND b.matched_payment_id=p.id)")->fetch_assoc()['c'] ?? 0);
    }
}

$page_title = 'إيكوبيشن | المطابقة البنكية';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-bank-main ems-unified-page-shell">
    <?php
    $header_title = 'المطابقة البنكية'; $header_icon = 'fa fa-building-columns';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleAcct', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'حساب بنكي');
        if ($sel_acct > 0) $header_actions[] = array('id' => 'toggleLine', 'class' => 'add-btn', 'icon' => 'fas fa-file-lines', 'label' => 'بند كشف');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <strong><i class="fas fa-building-columns"></i> الحساب البنكي:</strong>
            <select name="acct" onchange="this.form.submit()" style="min-width:240px"><?php echo fin_bank_account_options($conn, $is_super_admin, $company_id, $sel_acct); ?></select>
            <?php if ($sel_acct > 0 && $can_edit): ?>
                <a href="?acct=<?php echo $sel_acct; ?>&automatch=1&_t=<?php echo fin_action_token(); ?>" class="btn-save" style="text-decoration:none" onclick="return confirm('مطابقة آلية بالمبلغ والاتجاه؟')"><i class="fas fa-wand-magic-sparkles"></i> مطابقة آلية</a>
            <?php endif; ?>
        </form>
        <?php if ($sel_acct > 0): ?>
        <div class="form-grid" style="margin-top:12px">
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">رصيد الكشف البنكي</div><div style="font-size:20px;font-weight:700"><?php echo number_format($bank_balance, 2); ?></div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">بنود مُطابَقة</div><div style="font-size:20px;font-weight:700"><span class="badge badge-success"><?php echo $rec_lines; ?></span></div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">بنود غير مُطابَقة</div><div style="font-size:20px;font-weight:700"><span class="badge badge-<?php echo $unrec_lines > 0 ? 'danger' : 'success'; ?>"><?php echo $unrec_lines; ?></span></div></div></div>
            <div class="card" style="text-align:center"><div class="card-body"><div class="text-muted">مدفوعات غير مُطابَقة</div><div style="font-size:20px;font-weight:700"><span class="badge badge-<?php echo $unmatched_pay > 0 ? 'warn' : 'success'; ?>"><?php echo $unmatched_pay; ?></span></div></div></div>
        </div>
        <?php endif; ?>
    </div></div>

    <form id="acctForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-building-columns"></i> حساب بنكي</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>اسم الحساب <span class="required">*</span></label><input type="text" name="acct_name" required></div>
            <div class="form-group"><label>البنك</label><input type="text" name="bank_name"></div>
            <div class="form-group"><label>رقم الحساب</label><input type="text" name="account_number"></div>
            <div class="form-group"><label>الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" value="0"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#acctForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <?php if ($sel_acct > 0): ?>
    <form id="lineForm" action="" method="post" class="allforms">
        <input type="hidden" name="bank_account_id" value="<?php echo $sel_acct; ?>">
        <div class="card-header"><h5><i class="fas fa-file-lines"></i> بند كشف حساب</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>التاريخ</label><input type="date" name="txn_date" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label>النوع</label><select name="direction"><option value="deposit">إيداع</option><option value="withdrawal">سحب</option></select></div>
            <div class="form-group"><label>المبلغ <span class="required">*</span></label><input type="number" step="0.01" min="0" name="amount" required></div>
            <div class="form-group" style="grid-column:1/-1"><label>الوصف</label><input type="text" name="description"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#lineForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-list"></i> بنود كشف الحساب</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>التاريخ</th><th>الوصف</th><th>النوع</th><th>المبلغ</th><th>المطابقة</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT * FROM fin_bank_statement_lines WHERE company_id=$company_id AND bank_account_id=$sel_acct ORDER BY txn_date ASC, id ASC";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
                    $rec = intval($row['reconciled']) === 1;
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_edit && $rec) echo "<a href='?acct=$sel_acct&unmatch_line=" . intval($row['id']) . "' class='action-btn delete' title='إلغاء المطابقة' onclick='return confirm(\"إلغاء المطابقة؟\")'><i class='fas fa-link-slash'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['txn_date']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['description'] ?? '')) . "</td>";
                    echo "<td>" . ($row['direction'] === 'deposit' ? 'إيداع' : 'سحب') . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td>" . ($rec ? "<span class='badge badge-success'>مُطابَق #" . intval($row['matched_payment_id']) . "</span>" : "<span class='badge badge-danger'>غير مُطابَق</span>") . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
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
    $('#toggleAcct').on('click', function () { $('#acctForm').toggleClass('allforms-visible'); });
    $('#toggleLine').on('click', function () { $('#lineForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>

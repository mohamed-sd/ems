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
    // المطابقة الآلية: قراءتان معزولتان (بنود غير مطابَقة + مدفوعات مرشَّحة) تُدمجان
    // في PHP بدل NOT EXISTS مزدوج النطاق — كل قراءة عبر البوابة، وكل زوجٍ مطابَق ذرّي §9.
    $g = fin_gate($is_super_admin);
    $matched = 0;
    $lines = $g->select('fin_bank_statement_lines', array(
        'columns' => array('id', 'direction', 'amount'),
        'where'   => array('bank_account_id' => $sel_acct, 'reconciled' => 0),
        'orderBy' => 'id',
    ));
    // مدفوعات منفّذة/مطابَقة مرشَّحة (الحذف الناعم يُستبعَد آليًا) + مجموعة المطابَق سلفًا (شركة كاملة)
    $cands = $g->select('fin_payments', array(
        'columns'  => array('id', 'direction', 'amount'),
        'whereRaw' => "state IN('executed','reconciled')",
        'orderBy'  => 'id',
    ));
    $usedRows = $g->select('fin_bank_statement_lines', array(
        'columns'  => array('matched_payment_id'),
        'whereRaw' => 'matched_payment_id IS NOT NULL',
    ));
    $used = array();
    foreach ($usedRows as $ur) { $used[intval($ur['matched_payment_id'])] = true; }
    foreach ($lines as $ln) {
        $want = $ln['direction'] === 'deposit' ? 'collection' : 'disbursement';
        $amt = (float)$ln['amount'];
        foreach ($cands as $c) {
            $cid = intval($c['id']);
            if (isset($used[$cid]) || $c['direction'] !== $want || abs((float)$c['amount'] - $amt) >= 0.01) { continue; }
            $g->runInTransaction(function ($gate) use ($cid, $ln) {
                $gate->update('fin_bank_statement_lines', array('matched_payment_id' => $cid, 'reconciled' => 1), array('id' => intval($ln['id'])));
                $gate->update('fin_payments', array('state' => 'reconciled'), array('id' => $cid), "state='executed'");
            }, 'bank recon: automatch pair');
            $used[$cid] = true;
            $matched++;
            break;
        }
    }
    header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=تمت+مطابقة+$matched+بند+آليًا+✅"); exit();
}

// ── إلغاء مطابقة بند ──
if (isset($_GET['unmatch_line'])) {
    if (!$can_edit) { header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=لا+توجد+صلاحية+❌"); exit(); }
    $lid = intval($_GET['unmatch_line']);
    $g = fin_gate($is_super_admin);
    $lineRow = $g->selectOne('fin_bank_statement_lines', array('columns' => array('matched_payment_id'), 'where' => array('id' => $lid)));
    $pid = $lineRow ? intval($lineRow['matched_payment_id']) : 0;
    $g->runInTransaction(function ($gate) use ($lid, $pid) {
        $gate->update('fin_bank_statement_lines', array('matched_payment_id' => null, 'reconciled' => 0), array('id' => $lid));
        if ($pid > 0) { $gate->update('fin_payments', array('state' => 'executed'), array('id' => $pid), "state='reconciled'"); }
    }, 'bank recon: unmatch line');
    header("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=تم+إلغاء+المطابقة+✅"); exit();
}

// ── حفظ حساب بنكي ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_name'])) {
    if (!$can_add) { header("Location: bank_reconciliation_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $name = trim($_POST['acct_name'] ?? ''); $bank = trim($_POST['bank_name'] ?? '');
    $accno = trim($_POST['account_number'] ?? ''); $open = round(floatval($_POST['opening_balance'] ?? 0), 2);
    if ($name === '') { header("Location: bank_reconciliation_fin.php?msg=اسم+الحساب+مطلوب+❌"); exit(); }
    fin_gate($is_super_admin)->insert('fin_bank_accounts', array(
        'name' => $name, 'bank_name' => $bank, 'account_number' => $accno,
        'opening_balance' => $open, 'created_by' => $current_user_id,
    ));
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
    fin_gate($is_super_admin)->insert('fin_bank_statement_lines', array(
        'bank_account_id' => $ba, 'txn_date' => $date, 'description' => $desc,
        'direction' => $dir, 'amount' => $amt, 'created_by' => $current_user_id,
    ));
    header("Location: bank_reconciliation_fin.php?acct=$ba&msg=تمت+إضافة+البند+✅"); exit();
}

if (isset($_GET['del_acct'])) {
    if (!$can_delete) { header("Location: bank_reconciliation_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    fin_gate($is_super_admin)->softDelete('fin_bank_accounts', intval($_GET['del_acct']));
    header("Location: bank_reconciliation_fin.php?msg=تم+حذف+الحساب+✅"); exit();
}

// ملخّص المطابقة للحساب المختار
$bank_balance = 0; $book_movement = 0; $unrec_lines = 0; $unmatched_pay = 0; $rec_lines = 0;
if ($sel_acct > 0) {
    $g = fin_gate($is_super_admin);
    $acc = $g->selectOne('fin_bank_accounts', array('where' => array('id' => $sel_acct)));
    if ($acc) {
        $opening = (float)$acc['opening_balance'];
        $ssum = function ($dir) use ($g, $sel_acct) {
            $r = $g->scopedQuery(array('scope' => array('l' => 'fin_bank_statement_lines')),
                "SELECT COALESCE(SUM(l.amount),0) v FROM fin_bank_statement_lines l WHERE {TENANT_SCOPE} AND l.bank_account_id=? AND l.direction=?",
                array($sel_acct, $dir));
            return $r ? (float)$r[0]['v'] : 0.0;
        };
        $dep = $ssum('deposit');
        $wd  = $ssum('withdrawal');
        $bank_balance = $opening + $dep - $wd;
        $rec_lines   = $g->count('fin_bank_statement_lines', array('where' => array('bank_account_id' => $sel_acct, 'reconciled' => 1)));
        $unrec_lines = $g->count('fin_bank_statement_lines', array('where' => array('bank_account_id' => $sel_acct, 'reconciled' => 0)));
        // مدفوعات غير مطابَقة (شركة كاملة) = مرشَّحة ليست ضمن مجموعة المطابَق — قراءتان معزولتان بدل NOT EXISTS
        $candIds  = $g->select('fin_payments', array('columns' => array('id'), 'whereRaw' => "state IN('executed','reconciled')"));
        $usedRows = $g->select('fin_bank_statement_lines', array('columns' => array('matched_payment_id'), 'whereRaw' => 'matched_payment_id IS NOT NULL'));
        $usedSet = array();
        foreach ($usedRows as $ur) { $usedSet[intval($ur['matched_payment_id'])] = true; }
        $unmatched_pay = 0;
        foreach ($candIds as $cp) { if (!isset($usedSet[intval($cp['id'])])) { $unmatched_pay++; } }
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
                $line_rows = fin_gate($is_super_admin)->select('fin_bank_statement_lines', array(
                    'where' => array('bank_account_id' => $sel_acct), 'orderBy' => 'txn_date ASC, id ASC'));
                foreach ($line_rows as $row) {
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
                }
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

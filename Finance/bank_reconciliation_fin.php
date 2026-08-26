<?php
/**
 * Finance/bank_reconciliation_fin.php — المطابقة البنكية.
 * fin_bank_accounts + fin_bank_statement_lines؛ مطابقة بنود الكشف مع fin_payments المنفّذة.
 * مطابقة آلية بالمبلغ+الاتجاه. رصيد البنك مقابل رصيد الدفاتر. شاشة مستقلة — لا FK للخارج.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/../includes/w7_codes.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/bank_reconciliation_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المطابقة ❌', 'GOV-PERM-403', ''); exit(); }
$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$sel_acct = isset($_GET['acct']) ? intval($_GET['acct']) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// H-13 (SPEC-01 #19) — الدورةُ الكاملةُ فوق القائم: رأسُ كشفٍ وأسطرٌ بمفاتيحها
// ومضاهاةٌ بقاعدتها وفروقٌ بأسبابها وإقفالٌ يمنع.
// **القائمُ لا يُمسّ**: `fin_bank_statement_lines` وشاشتُه القديمةُ تبقيان أدناه
// (مطابقةٌ بالمبلغ والاتجاه)، والدورةُ الجديدةُ تُضاف فوقها — لا تحلّ محلَّها
// قسرًا. والترحيلُ بينهما قرارُ مالكٍ لا أثرُ نشر.
// ═══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/../app/Services/Finance/BankReconService.php';
use App\Services\Finance\BankReconService as BRS;

$h13_gate = fin_gate($is_super_admin);
$h13_stmt = isset($_GET['stmt']) ? intval($_GET['stmt']) : 0;
$h13_redirect = function ($msg, $stmt = 0) {
    $q = $stmt > 0 ? ('&stmt=' . $stmt) : '';
    ems_gov_flash_redirect(ems_flash_to('bank_reconciliation_fin.php', $q), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['h13'] ?? '') !== '') {
    if (!$can_edit) { $h13_redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $act = strval($_POST['h13']);

    if ($act === 'import') {
        // الأسطرُ تُلصق نصًّا: تاريخ | وصف | اتجاه | مبلغ | مرجع
        $raw = trim(strval($_POST['lines_raw'] ?? ''));
        $rows = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $ln) {
            $ln = trim($ln);
            if ($ln === '') { continue; }
            $p = array_map('trim', explode('|', $ln));
            $rows[] = array(
                'txn_date' => isset($p[0]) ? $p[0] : '',
                'description' => isset($p[1]) ? $p[1] : '',
                'direction' => isset($p[2]) ? $p[2] : '',
                'amount' => isset($p[3]) ? $p[3] : 0,
                'bank_ref' => isset($p[4]) ? $p[4] : '',
            );
        }
        $r = BRS::import($conn, $h13_gate, $company_id, array(
            'bank_account_id' => intval($_POST['bank_account_id'] ?? 0),
            'statement_ref' => strval($_POST['statement_ref'] ?? ''),
            'period_from' => strval($_POST['period_from'] ?? ''),
            'period_to' => strval($_POST['period_to'] ?? ''),
            'opening_balance' => strval($_POST['opening_balance'] ?? '0'),
            'closing_balance' => strval($_POST['closing_balance'] ?? '0'),
        ), $rows, $current_user_id);
        $msg = $r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌');
        $h13_redirect($msg, (int) $r['statement_id']);
    }
    if ($act === 'automatch') {
        $r = BRS::autoMatch($conn, $h13_gate, $company_id, intval($_POST['statement_id'] ?? 0), $current_user_id);
        $h13_redirect(($r['ok'] ? $r['reason'] . ' ✅' : $r['code'] . ' — ' . $r['reason'] . ' ❌'),
                      intval($_POST['statement_id'] ?? 0));
    }
    if ($act === 'open_diff') {
        $r = BRS::openDifference($conn, $h13_gate, $company_id, intval($_POST['match_id'] ?? 0),
                                 strval($_POST['why'] ?? ''), $current_user_id);
        $h13_redirect(($r['ok'] ? 'فتح الفرق بسببه ✅' : $r['code'] . ' — ' . $r['reason'] . ' ❌'),
                      intval($_POST['statement_id'] ?? 0));
    }
    if ($act === 'resolve') {
        $r = BRS::resolveDifference($conn, $h13_gate, $company_id, intval($_POST['match_id'] ?? 0),
                                    strval($_POST['decision'] ?? ''), strval($_POST['why'] ?? ''),
                                    $current_user_id);
        $h13_redirect(($r['ok']
            ? ('حسم الفرق' . ($r['event_id'] ? (' · قيد تسوية #' . $r['event_id']) : ' بلا قيد') . ' ✅')
            : $r['code'] . ' — ' . $r['reason'] . ' ❌'), intval($_POST['statement_id'] ?? 0));
    }
    if ($act === 'accept') {
        // «قبولُ المضاهاة» (§19) — استقرارُ السطر على «مطابق» بقرارٍ ظاهر
        try {
            $h13_gate->update('bank_statement_lines', array('match_state' => 'matched'),
                array('id' => intval($_POST['line_id'] ?? 0)));
            $h13_redirect('قبلت المضاهاة ✅', intval($_POST['statement_id'] ?? 0));
        } catch (\Throwable $t) { $h13_redirect('تعذر القبول ❌', intval($_POST['statement_id'] ?? 0)); }
    }
    if ($act === 'close') {
        $r = BRS::close($conn, $h13_gate, $company_id, intval($_POST['statement_id'] ?? 0), $current_user_id);
        $h13_redirect(($r['ok'] ? $r['reason'] . ' ✅' : $r['code'] . ' — ' . $r['reason'] . ' ❌'),
                      intval($_POST['statement_id'] ?? 0));
    }
}

$h13_statements = BRS::statements($h13_gate);
$h13_lines = $h13_stmt > 0 ? BRS::linesOf($h13_gate, $h13_stmt) : array();
$h13_stats = $h13_stmt > 0 ? BRS::stats($h13_gate, $h13_stmt) : null;
$h13_head  = $h13_stmt > 0 ? BRS::statementOf($h13_gate, $h13_stmt) : null;

// ── مطابقة آلية بالمبلغ والاتجاه (CSRF) ──
if (isset($_GET['automatch']) && $sel_acct > 0) {
    if (!$can_edit) { ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=لا+توجد+صلاحية+❌"); exit(); }
    if (!fin_verify_action_token()) { ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=رمز+الحماية+غير+صالح+❌"); exit(); }
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
    ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=تمت+مطابقة+$matched+بند+آليا+✅"); exit();
}

// ── إلغاء مطابقة بند ──
if (isset($_GET['unmatch_line'])) {
    if (!$can_edit) { ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=لا+توجد+صلاحية+❌"); exit(); }
    $lid = intval($_GET['unmatch_line']);
    $g = fin_gate($is_super_admin);
    $lineRow = $g->selectOne('fin_bank_statement_lines', array('columns' => array('matched_payment_id'), 'where' => array('id' => $lid)));
    $pid = $lineRow ? intval($lineRow['matched_payment_id']) : 0;
    $g->runInTransaction(function ($gate) use ($lid, $pid) {
        $gate->update('fin_bank_statement_lines', array('matched_payment_id' => null, 'reconciled' => 0), array('id' => $lid));
        if ($pid > 0) { $gate->update('fin_payments', array('state' => 'executed'), array('id' => $pid), "state='reconciled'"); }
    }, 'bank recon: unmatch line');
    ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$sel_acct&msg=تم+إلغاء+المطابقة+✅"); exit();
}

// ── حفظ حساب بنكي ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_name'])) {
    if (!$can_add) { ems_gov_flash_redirect('bank_reconciliation_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $name = trim($_POST['acct_name'] ?? ''); $bank = trim($_POST['bank_name'] ?? '');
    $accno = trim($_POST['account_number'] ?? ''); $open = round(floatval($_POST['opening_balance'] ?? 0), 2);
    if ($name === '') { ems_gov_flash_redirect('bank_reconciliation_fin.php', 'اسم الحساب مطلوب ❌', 'GOV-FAIL-409', ''); exit(); }
    fin_gate($is_super_admin)->insert('fin_bank_accounts', array(
        'name' => $name, 'bank_name' => $bank, 'account_number' => $accno,
        'opening_balance' => $open, 'created_by' => $current_user_id,
    ));
    ems_gov_flash_redirect('bank_reconciliation_fin.php', 'تمت إضافة الحساب البنكي ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ بند كشف ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direction'])) {
    if (!$can_add) { ems_gov_flash_redirect('bank_reconciliation_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $ba = intval($_POST['bank_account_id'] ?? 0);
    $dir = ($_POST['direction'] ?? '') === 'withdrawal' ? 'withdrawal' : 'deposit';
    $date = trim($_POST['txn_date'] ?? '') ?: date('Y-m-d');
    $desc = trim($_POST['description'] ?? '');
    $amt = round(floatval($_POST['amount'] ?? 0), 2);
    if ($ba <= 0 || $amt <= 0) { ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$ba&msg=بيانات+البند+غير+صحيحة+❌"); exit(); }
    fin_gate($is_super_admin)->insert('fin_bank_statement_lines', array(
        'bank_account_id' => $ba, 'txn_date' => $date, 'description' => $desc,
        'direction' => $dir, 'amount' => $amt, 'created_by' => $current_user_id,
    ));
    ems_gov_redirect("Location: bank_reconciliation_fin.php?acct=$ba&msg=تمت+إضافة+البند+✅"); exit();
}

if (isset($_GET['del_acct'])) {
    if (!$can_delete) { ems_gov_flash_redirect('bank_reconciliation_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    fin_gate($is_super_admin)->softDelete('fin_bank_accounts', intval($_GET['del_acct']));
    ems_gov_flash_redirect('bank_reconciliation_fin.php', 'تم حذف الحساب ✅', 'GOV-OK-200', ''); exit();
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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا كشوف بنكية مستوردة لهذا الحساب', 'استورد كشفا من نموذج «كشوف البنك ودورة المطابقة» أعلاه أو اختر حسابا آخر');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php fin_msg_banner(); ?>

    <!-- ═══ H-13 · الدورةُ الكاملة (SPEC-01 #19) ═══ -->
    <div class="card"><div class="card-body">
        <h5 class="fin-bank-h5"><i class="fas fa-file-import"></i> كشوف البنك ودورة المطابقة</h5>
        <p class="fin-bank-intro">
            <strong>استيراد لا يكرر</strong> (بصمة السطر: كشف × مرجع × تاريخ × اتجاه × مبلغ — والملف
            نفسه يستورد مرارا بصفر سطر مكرر) · <strong>ومضاهاة بقاعدتها</strong> (المرجع أولا ثم
            المبلغ والتاريخ ± 3 أيام — والقاعدة تحفظ) · <strong>وفرق يفتح بسبب ويحسم بقيد تسوية
            بمرجعه</strong> والسند الأصلي لا يمس · <strong>ولا إقفال وفرق مفتوح</strong>.
        </p>
        <?php if ($can_edit): ?>
        <form method="post" class="allforms allforms-visible fin-bank-form-flat">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="h13" value="import">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_207_78620">الحساب البنكي <span class="required">*</span></label>
                    <select name="bank_account_id" required id="emsf_207_78620">
                        <?php
                        $accs = $h13_gate->select('fin_bank_accounts', array('orderBy' => 'name ASC'));
                        foreach ($accs as $a) {
                            echo "<option value='" . intval($a['id']) . "'>" . htmlspecialchars((string)$a['name']) . "</option>";
                        } ?>
                    </select></div>
                <div class="form-group"><label for="emsf_208_dbbfd">مرجع الكشف <span class="required">*</span></label>
                    <input type="text" name="statement_ref" required maxlength="60" placeholder="من البنك" id="emsf_208_dbbfd"></div>
                <div class="form-group"><label for="emsf_209_613dc">من تاريخ <span class="required">*</span></label>
                    <input type="date" name="period_from" required id="emsf_209_613dc"></div>
                <div class="form-group"><label for="emsf_210_780de">إلى تاريخ <span class="required">*</span></label>
                    <input type="date" name="period_to" required id="emsf_210_780de"></div>
                <div class="form-group"><label for="emsf_211_616ad">رصيد افتتاحي</label>
                    <input type="number" step="0.01" name="opening_balance" value="0" id="emsf_211_616ad"></div>
                <div class="form-group"><label for="emsf_212_024c7">رصيد ختامي</label>
                    <input type="number" step="0.01" name="closing_balance" value="0" id="emsf_212_024c7"></div>
            </div>
            <div class="form-group fin-bank-mt10">
                <label for="emsf_213_646db">أسطر الكشف — سطر لكل حركة:
                    <code>التاريخ | الوصف | deposit أو withdrawal | المبلغ | المرجع البنكي</code></label>
                <textarea name="lines_raw" rows="5" class="fin-bank-raw"
                    placeholder="2026-07-01 | تحصيل عميل | deposit | 1000.00 | REF-001" id="emsf_213_646db"></textarea>
                <small class="fin-bank-hint">سطر <strong>بلا مرجع بنكي يرفض ويعلن</strong> — ولا يخترع له مفتاح.</small>
            </div></div>
            <div class="form-actions"><button type="submit" class="btn-primary">
                <i class="fas fa-file-import"></i> استورد الكشف</button></div>
        </form>
        <?php endif; ?>

        <div class="table-container fin-bank-mt12">
        <table class="alltables display nowrap no-datatable fin-bank-tbl" data-no-dt="hard">
            <thead><tr><th>#</th><th>رقم الحساب</th><th>المرجع</th><th>المدى</th><th>الأسطر</th>
                <th>الحالة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($h13_statements as $s): ?>
                <tr><td><?php echo intval($s['id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($s['account_name'] ?? '—')); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$s['statement_ref']); ?></strong></td>
                    <td class="fin-bank-ltr"><?php echo htmlspecialchars((string)$s['period_from']); ?>
                        → <?php echo htmlspecialchars((string)$s['period_to']); ?></td>
                    <td><?php echo intval($s['lines_count']); ?></td>
                    <td><?php
                        $lbl = array('imported' => 'مستورد', 'matching' => 'قيد المضاهاة',
                                     'reconciled' => 'مطابَق', 'closed' => 'مقفل');
                        $cls = (string)$s['state'] === 'closed' ? 'badge-secondary' : 'badge-info';
                        echo "<span class='badge {$cls}'>" . htmlspecialchars($lbl[(string)$s['state']] ?? (string)$s['state']) . "</span>";
                    ?></td>
                    <td><a class="action-btn" href="?stmt=<?php echo intval($s['id']); ?>">
                        <i class="fa fa-eye"></i> افتح</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$h13_statements): ?><tr><td colspan="7"><em>لا كشوف مستوردة بعد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php if ($h13_head): ?>
    <div class="card"><div class="card-body">
        <h5 class="fin-bank-h5"><i class="fas fa-scale-balanced"></i>
            كشف <?php echo htmlspecialchars((string)$h13_head['statement_ref']); ?></h5>
        <div class="fin-bank-chips">
            <span class="badge badge-success fin-bank-chip">نسبة المطابقة
                <?php echo htmlspecialchars((string)$h13_stats['rate']); ?>٪</span>
            <span class="badge fin-bank-chip <?php echo $h13_stats['open_diff'] > 0 ? 'badge-danger' : 'badge-success'; ?>">فروق مفتوحة <?php echo intval($h13_stats['open_diff']); ?></span>
            <span class="badge badge-secondary fin-bank-chip">بلا نظير
                <?php echo intval($h13_stats['none']); ?></span>
        </div>
        <?php if ($can_edit && (string)$h13_head['state'] !== 'closed'): ?>
        <div class="fin-bank-actions">
            <form method="post" class="fin-bank-inline">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="h13" value="automatch">
                <input type="hidden" name="statement_id" value="<?php echo intval($h13_stmt); ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-wand-magic-sparkles"></i> مضاهاة آلية</button>
            </form>
            <form method="post" class="fin-bank-inline">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="h13" value="close">
                <input type="hidden" name="statement_id" value="<?php echo intval($h13_stmt); ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-lock"></i> أقفل الكشف</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="table-container">
        <table class="alltables display nowrap no-datatable fin-bank-tbl" data-no-dt="hard">
            <thead><tr><th>#</th><th>تاريخ الإقفال</th><th>الوصف</th><th>الاتجاه</th><th>المبلغ</th>
                <th>المرجع</th><th>النظير</th><th>القاعدة</th><th>الفرق</th><th>الحال</th>
                <?php if ($can_edit) echo '<th>القرار</th>'; ?>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
                <th class="ems-fn-th" data-fn="1">الشهر</th>
                <th class="ems-fn-th" data-fn="1">البنك</th>
                <th class="ems-fn-th" data-fn="1">الرصيد حسب الدفتر</th>
                <th class="ems-fn-th" data-fn="1">الرصيد حسب الكشف</th>
                <th class="ems-fn-th" data-fn="1">حركات في الدفتر لم تظهر بالبنك</th>
                <th class="ems-fn-th" data-fn="1">حركات بالبنك لم تقيد</th>
                <th class="ems-fn-th" data-fn="1">شيكات معلقة</th>
                <th class="ems-fn-th" data-fn="1">رسوم بنكية غير مقيدة</th>
                <th class="ems-fn-th" data-fn="1">تسويات معتمدة</th>
                <th class="ems-fn-th" data-fn="1">الرصيد بعد التسوية</th>
                <th class="ems-fn-th none" data-fn="1">اعتمده</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                </tr></thead>
            <tbody>
            <?php foreach ($h13_lines as $l):
                $ms = (string)$l['match_state'];
                $msLbl = array('unmatched' => 'لم يضاه', 'matched' => 'مطابق',
                               'difference' => 'فرق بقيمته', 'no_counterpart' => 'بلا نظير');
                $msCls = $ms === 'matched' ? 'badge-success' : ($ms === 'difference' ? 'badge-warning' : 'badge-secondary');
            ?>
                <tr><td><?php echo intval($l['line_no']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['txn_date']); ?></td>
                    <td class="fin-bank-wrap"><?php echo htmlspecialchars((string)($l['description'] ?? '')); ?></td>
                    <td><?php echo (string)$l['direction'] === 'deposit' ? 'إيداع' : 'سحب'; ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$l['amount']); ?></strong></td>
                    <td class="fin-bank-ltr"><?php echo htmlspecialchars((string)$l['bank_ref']); ?></td>
                    <td><?php echo $l['payment_id'] !== null ? ('سند #' . intval($l['payment_id'])) : '—'; ?></td>
                    <td class="fin-bank-wrap"><small><?php echo htmlspecialchars((string)($l['rule_note'] ?? '')); ?></small></td>
                    <td><?php echo $l['difference'] !== null && abs((float)$l['difference']) > 0.004
                        ? ('<strong class="fin-bank-diff">' . htmlspecialchars((string)$l['difference']) . '</strong>')
                        : '—'; ?></td>
                    <td><span class="badge <?php echo $msCls; ?>"><?php echo htmlspecialchars($msLbl[$ms] ?? $ms); ?></span>
                        <?php if ((string)($l['match_row_state'] ?? '') === 'open_difference'): ?>
                            <div><small class="fin-bank-diff"><?php echo htmlspecialchars((string)$l['difference_reason']); ?></small></div>
                        <?php elseif ($l['adjustment_event_id'] !== null): ?>
                            <div><small>قيد تسوية #<?php echo intval($l['adjustment_event_id']); ?></small></div>
                        <?php endif; ?></td>
                    <?php if ($can_edit && (string)$h13_head['state'] !== 'closed'): ?>
                    <td class="fin-bank-wrap">
                        <?php $mid = intval($l['match_id']); $mrs = (string)($l['match_row_state'] ?? ''); ?>
                        <?php if ($mid > 0 && $mrs === 'matched' && $ms === 'difference'): ?>
                            <form method="post" class="fin-bank-row-form">
        <?php echo csrf_field(); ?>
                                <input type="hidden" name="h13" value="open_diff">
                                <input type="hidden" name="match_id" value="<?php echo $mid; ?>">
                                <input type="hidden" name="statement_id" value="<?php echo intval($h13_stmt); ?>">
                                <input type="text" name="why" required maxlength="200" placeholder="سبب الفرق" class="fin-bank-why-130" aria-label="سبب الفرق">
                                <button type="submit" class="badge badge-warning fin-bank-mini-btn">افتح فرقا</button>
                            </form>
                        <?php elseif ($mid > 0 && $mrs === 'open_difference'): ?>
                            <form method="post" class="fin-bank-row-form-wrap">
        <?php echo csrf_field(); ?>
                                <input type="hidden" name="h13" value="resolve">
                                <input type="hidden" name="match_id" value="<?php echo $mid; ?>">
                                <input type="hidden" name="statement_id" value="<?php echo intval($h13_stmt); ?>">
                                <select name="decision" aria-label="قرار حسم الفرق"><option value="adjust">قيد تسوية</option><option value="reject">رفض</option></select>
                                <input type="text" name="why" required maxlength="200" placeholder="قرار بسببه" class="fin-bank-why-120" aria-label="قرار بسببه">
                                <button type="submit" class="badge badge-success fin-bank-mini-btn">احسم</button>
                            </form>
                        <?php elseif ($ms !== 'matched'): ?>
                            <form method="post" class="fin-bank-inline">
        <?php echo csrf_field(); ?>
                                <input type="hidden" name="h13" value="accept">
                                <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                                <input type="hidden" name="statement_id" value="<?php echo intval($h13_stmt); ?>">
                                <button type="submit" class="badge badge-secondary fin-bank-mini-btn">اقبل المضاهاة</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$h13_lines): ?><tr><td colspan="11"><em>لا أسطر</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h5 class="fin-bank-h5-tight"><i class="fas fa-clock-rotate-left"></i> الدورة القديمة (تبقى للقراءة والتاريخ)</h5>
        <p class="fin-bank-note">
            `fin_bank_statement_lines` ومطابقتها بالمبلغ والاتجاه — <strong>لا تحذف ولا ترحل قسرا</strong>،
            والانتقال إلى الدورة أعلاه قرار مالك.</p>
    </div></div>

    <div class="card"><div class="card-body">
        <form method="get" class="fin-bank-filter">
            <strong><i class="fas fa-building-columns"></i> الحساب البنكي:</strong>
            <select name="acct" aria-label="الحساب البنكي المعروضة بنوده" class="fin-bank-select" onchange="this.form.submit()"><?php echo fin_bank_account_options($conn, $is_super_admin, $company_id, $sel_acct); ?></select>
            <?php if ($sel_acct > 0 && $can_edit): ?>
                <a href="?acct=<?php echo $sel_acct; ?>&automatch=1&_t=<?php echo fin_action_token(); ?>" class="btn-primary fin-bank-plain-link" onclick="return confirm('مطابقة آلية بالمبلغ والاتجاه؟')"><i class="fas fa-wand-magic-sparkles"></i> مطابقة آلية</a>
            <?php endif; ?>
        </form>
        <?php if ($sel_acct > 0): ?>
        <div class="form-grid fin-bank-mt12">
            <div class="card fin-bank-kpi"><div class="card-body"><div class="text-muted">رصيد الكشف البنكي</div><div class="fin-bank-kpi-value"><?php echo number_format($bank_balance, 2); ?></div></div></div>
            <div class="card fin-bank-kpi"><div class="card-body"><div class="text-muted">بنود مطابقة</div><div class="fin-bank-kpi-value"><span class="badge badge-success"><?php echo $rec_lines; ?></span></div></div></div>
            <div class="card fin-bank-kpi"><div class="card-body"><div class="text-muted">بنود غير مطابقة</div><div class="fin-bank-kpi-value"><span class="badge badge-<?php echo $unrec_lines > 0 ? 'danger' : 'success'; ?>"><?php echo $unrec_lines; ?></span></div></div></div>
            <div class="card fin-bank-kpi"><div class="card-body"><div class="text-muted">مدفوعات غير مطابقة</div><div class="fin-bank-kpi-value"><span class="badge badge-<?php echo $unmatched_pay > 0 ? 'warn' : 'success'; ?>"><?php echo $unmatched_pay; ?></span></div></div></div>
        </div>
        <?php endif; ?>
    </div></div>

    <form id="acctForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-building-columns"></i> حساب بنكي</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_214_ebac3">اسم الحساب <span class="required">*</span></label><input type="text" name="acct_name" required id="emsf_214_ebac3"></div>
            <div class="form-group"><label for="emsf_215_190a4">البنك</label><input type="text" name="bank_name" id="emsf_215_190a4"></div>
            <div class="form-group"><label for="emsf_216_dd679">رقم الحساب</label><input type="text" name="account_number" id="emsf_216_dd679"></div>
            <div class="form-group"><label for="emsf_217_4b4a7">الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" value="0" id="emsf_217_4b4a7"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-secondary" onclick="$('#acctForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <?php if ($sel_acct > 0): ?>
    <form id="lineForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="bank_account_id" value="<?php echo $sel_acct; ?>">
        <div class="card-header"><h5><i class="fas fa-file-lines"></i> بند كشف حساب</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_218_edc49">التاريخ</label><input type="date" name="txn_date" id="emsf_218_edc49" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label for="emsf_219_5b23f">النوع</label><select name="direction" id="emsf_219_5b23f"><option value="deposit">إيداع</option><option value="withdrawal">سحب</option></select></div>
            <div class="form-group"><label for="emsf_220_77d51">المبلغ <span class="required">*</span></label><input type="number" step="0.01" min="0" name="amount" required id="emsf_220_77d51"></div>
            <div class="form-group fin-bank-full"><label for="emsf_221_2b7d1">الوصف</label><input type="text" name="description" id="emsf_221_2b7d1"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-secondary" onclick="$('#lineForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 class="fin-bank-h5-list"><i class="fas fa-list"></i> بنود كشف الحساب</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables fin-bank-tbl" data-state-save="false">
                <thead><tr><th>الإجراءات</th><th>التاريخ</th><th>الوصف</th><th>النوع</th><th>المبلغ</th><th>طابقه</th></tr></thead>
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
                    echo "<td>" . ($rec ? "<span class='badge badge-success'>مطابق #" . intval($row['matched_payment_id']) . "</span>" : "<span class='badge badge-danger'>غير مطابق</span>") . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php endif; ?>

  <?php /* ── TRS-16 · بنودُ فروقِ المطابقةِ البنكيّة — **ولا فرقَ مدفونٌ في حقل**:
       كلُّ فرقٍ سطرٌ بنوعِه وسببِه ومسؤولِه وإجرائه حتّى الإغلاق. والجلسةُ
       لا تُقفَل وفيها فرقٌ مفتوح (`BANK_CLOSE_WITH_OPEN_DIFF`). */
  $__w11_diffs = array();
  try { $__w11_diffs = fin_gate($is_super_admin)->select('tre_recon_difference',
            array('orderBy' => 'id DESC', 'limit' => 400)); }
  catch (\Throwable $t) { error_log('bank_reconciliation diffs: ' . $t->getMessage()); }
  ?>
  <h5 class="mt-4">بنود فروق المطابقة البنكية</h5>
  <div class="table-wrap"><table class="data-table">
    <thead><tr>
      <th>الكشف</th><th>النوع</th><th>السبب</th><th>المبلغ</th>
      <th>المسؤول</th><th>الاجراء</th><th>الحالة</th><th>وقت الفتح</th>
    </tr></thead>
    <tbody>
    <?php foreach ($__w11_diffs as $__d): ?>
      <tr>
        <td>#<?= (int) $__d['statement_id'] ?></td>
        <td><?= htmlspecialchars(ems_w7_ar((string) $__d['diff_kind'], $conn)) ?></td>
        <td><?= htmlspecialchars((string) $__d['cause']) ?></td>
        <td><?= number_format((float) $__d['amount'], 2) ?></td>
        <td><?= htmlspecialchars((string) $__d['responsible_role']) ?></td>
        <td><?= htmlspecialchars((string) $__d['action_taken']) ?></td>
        <td><?= htmlspecialchars(ems_w7_ar((string) $__d['state'], $conn)) ?></td>
        <td><?= htmlspecialchars((string) $__d['opened_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
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
    /* UXW-01 ⑤: التهيئةُ المحليةُ أُزيلت — المكوّنُ المركزيُّ في assets/js/ui-unification.js
       يلتقط #finTable آليًّا، وجدولا الكشوفِ والأسطرِ يبقيان ساكنَين بسمةِ الخروجِ الصريحة. */
    $('#toggleAcct').on('click', function () { $('#acctForm').toggleClass('allforms-visible'); });
    $('#toggleLine').on('click', function () { $('#lineForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>

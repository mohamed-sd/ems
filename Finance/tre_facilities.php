<?php
/**
 * Finance/tre_facilities.php — التسهيلات البنكية (DEP-06 · الشاشة 14 · GOV_EXEC §5)
 * ───────────────────────────────────────────────────────────────────────────
 * سجل التسهيلات: الحد والمستخدم والمتاح والضمانات والاستحقاقات، عين الخزينة
 * على الالتزامات البنكية. الحبة: تسهيل بنكي لكل بنك سطر واحد. البنك والعملة
 * يقرآن من سجل الحسابات البنكية، والمتاح يشتق (الحد ناقص المستخدم) ولا يكتب،
 * والقوائم المحكومة تقرا من مخطط الجدول نفسه. اعتماد الاقتراض بقاعدة
 * AAM-012 يقيد مرجعه عند نفاذ قيم السلم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Core/EventPublisher.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super   = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }
$msg = '';

/** القائمة المحكومة تقرا من قاموس المخطط — المصدر واحد ولا نسخ للمفردات. */
function tre_fac_enum(mysqli $conn, $col)
{
    $out = array();
    $st = $conn->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $tbl = 'tre_bank_facility';
    $st->bind_param('ss', $tbl, $col);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $st->close();
    if ($row && preg_match_all("~'((?:[^'\\\\]|\\\\.)*)'~u", (string) $row[0], $m)) {
        foreach ($m[1] as $v) { $out[] = str_replace("\\'", "'", $v); }
    }
    return $out;
}
$typeOpts  = tre_fac_enum($conn, 'facility_type');
$stateOpts = tre_fac_enum($conn, 'facility_state');
$stActive = isset($stateOpts[0]) ? $stateOpts[0] : '';

/* ── معالج تسجيل تسهيل جديد ─────────────────────────────────────────────── */
$__pc = ems_post_contract($conn, array(
    'action'  => 'tre.facility.register',
    'perm'    => 'can_add',
    'trigger' => 'bank_account_id',
    'idem'    => array(
        'bank' => intval($_POST['bank_account_id'] ?? 0),
        'typ'  => (string) ($_POST['facility_type'] ?? ''),
        'lim'  => (string) ($_POST['limit_amount'] ?? ''),
        'exp'  => (string) ($_POST['expiry_date'] ?? ''),
    ),
    'validate' => function (array $in) use ($typeOpts) {
        $bank = intval($in['bank_account_id'] ?? 0);
        $typ = (string) ($in['facility_type'] ?? '');
        $lim = floatval($in['limit_amount'] ?? 0);
        $exp = (string) ($in['expiry_date'] ?? '');
        $col = trim((string) ($in['collateral_ref'] ?? ''));
        $aam = trim((string) ($in['aam_ref'] ?? ''));
        if ($bank <= 0 || $exp === '') { return array('ok' => false, 'msg' => 'البنك وتاريخ الانتهاء الزاميان (422)'); }
        if (!in_array($typ, $typeOpts, true)) { return array('ok' => false, 'msg' => 'نوع التسهيل من قائمته المحكومة وحدها (422)'); }
        if ($lim <= 0) { return array('ok' => false, 'msg' => 'حد التسهيل موجب الزاما (422)'); }
        return array('ok' => true, 'data' => compact('bank', 'typ', 'lim', 'exp', 'col', 'aam'));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $d = $__pc['data'];
    $gateW = ems_tenant_db();
    try {
        $acct = $gateW->selectOne('fin_bank_accounts', array('where' => array('id' => $d['bank'])));
        if (!$acct) {
            $msg = 'الحساب البنكي خارج نطاقك او غير موجود (404)';
        } else {
            $gateW->insert('tre_bank_facility', array(
                'bank_account_id' => $d['bank'], 'facility_type' => $d['typ'],
                'limit_amount' => $d['lim'], 'expiry_date' => $d['exp'],
                'collateral_ref' => ($d['col'] === '' ? null : $d['col']),
                'aam_ref' => ($d['aam'] === '' ? null : $d['aam']),
                'created_by' => $uid, 'src_ref' => 'tre_facilities.php@' . $__pc['code'],
            ));
            $newId = intval($conn->insert_id);
            $msg = 'سجل التسهيل رقم ' . $newId;
            ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], (string) $newId);
        }
    } catch (\Throwable $t) {
        $msg = 'تعذر التسجيل: قيد المخطط رده، راجع المدخلات (422)';
        error_log('tre_facilities insert: ' . $t->getMessage());
    }
}

/* ── معالج تغيير الحالة — غير السارية بسبب مكتوب وواقعة ترفع ────────────── */
$__cc = ems_post_contract($conn, array(
    'action'  => 'tre.facility.state_change',
    'perm'    => 'can_edit',
    'trigger' => 'fac_id',
    'idem'    => array('fid' => intval($_POST['fac_id'] ?? 0), 'st' => (string) ($_POST['new_state'] ?? '')),
    'validate' => function (array $in) use ($stateOpts) {
        $fid = intval($in['fac_id'] ?? 0);
        $ns = (string) ($in['new_state'] ?? '');
        $note = trim((string) ($in['state_note'] ?? ''));
        if ($fid <= 0 || !in_array($ns, $stateOpts, true)) { return array('ok' => false, 'msg' => 'الحالة من قائمتها المحكومة وحدها (422)'); }
        if (!in_array($ns, array($stateOpts[0] ?? '', $stateOpts[1] ?? ''), true) && $note === '') {
            return array('ok' => false, 'msg' => 'الحالة غير السارية بسبب مكتوب الزاما (422)');
        }
        return array('ok' => true, 'data' => compact('fid', 'ns', 'note'));
    },
));
if (!$__cc['ok'] && $__cc['msg'] !== '') { $msg = $__cc['msg']; }
if ($__cc['replay'])                     { $msg = $__cc['msg']; }
if ($__cc['run'] && $__cc['ok']) {
    $d = $__cc['data'];
    $gateW = ems_tenant_db();
    $done = 0;
    try {
        $row = $gateW->selectOne('tre_bank_facility', array('where' => array('id' => $d['fid'])));
        if ($row && (string) $row['facility_state'] !== $d['ns']) {
            $gateW->update('tre_bank_facility', array(
                'facility_state' => $d['ns'],
                'state_note' => ($d['note'] === '' ? null : $d['note']),
            ), array('id' => $d['fid']));
            $done = 1;
        }
    } catch (\Throwable $t) { error_log('tre_facilities state: ' . $t->getMessage()); }
    if ($done === 1) {
        $msg = 'تحولت حالة التسهيل رقم ' . $d['fid'];
        ems_pc_idem_mark($conn, $__cc['idem'], $__cc['code'], (string) $d['fid']);
        try {
            \EventPublisher::publishFact($conn, array(
                'company_id' => $company_id, 'event_key' => 'tre.facility_state_changed',
                'category' => 'operational', 'source_module' => 'treasury',
                'entity_type' => 'tre_bank_facility', 'entity_id' => $d['fid'],
                'created_by' => $uid, 'payload' => array('state' => $d['ns'], 'note' => $d['note']),
                'idempotency_key' => 'trefac-' . $d['fid'] . '-' . $d['ns'],
            ));
        } catch (\Throwable $t) { error_log('tre_facilities event: ' . $t->getMessage()); }
    } else { $msg = 'لم تتحول: التسهيل خارج نطاقك او حالته هي نفسها (409)'; }
}

/* ── القراءة: التسهيلات ببنوكها والمتاح يشتق هنا لا يخزن ────────────────── */
$pp = check_page_permissions($conn, 'Finance/tre_facilities.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('tre_facilities super') : ems_tenant_db();

$banks = array(); $rows = array();
try { foreach ($gate->select('fin_bank_accounts', array('limit' => 400)) as $b) { $banks[(int) $b['id']] = $b; } }
catch (\Throwable $t) { error_log('tre_facilities banks: ' . $t->getMessage()); }
try { $rows = $gate->select('tre_bank_facility', array('orderBy' => 'expiry_date', 'limit' => 400)); }
catch (\Throwable $t) { error_log('tre_facilities list: ' . $t->getMessage()); }

$today = ems_fmt_now();
$totLimit = 0.0; $totUsed = 0.0; $expSoon = 0;
foreach ($rows as $i => $r) {
    $avail = (float) $r['limit_amount'] - (float) $r['used_amount'];
    $rows[$i]['__avail'] = $avail;
    if ((string) $r['facility_state'] === $stActive) {
        $totLimit += (float) $r['limit_amount'];
        $totUsed += (float) $r['used_amount'];
        if ((string) $r['expiry_date'] <= ems_fmt_date(strtotime('+60 days'))) { $expSoon++; }
    }
}

$page_title = 'إيكوبيشن | التسهيلات البنكية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php $header_title = 'التسهيلات البنكية'; $header_icon = 'fa fa-building-columns'; $header_actions = array();
    $header_back = false;
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">تسهيلات مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($totLimit, 0) ?></div><div class="ems-stat-label">حدود سارية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($totLimit - $totUsed, 0) ?></div><div class="ems-stat-label">المتاح الكلي</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $expSoon ?></div><div class="ems-stat-label">ينتهي خلال ستين يوما</div></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (!empty($pp['can_add'])): ?>
    <h3 class="ems-section-title">تسهيل جديد</h3>
    <form method="post" class="ems-form">
        <?= csrf_field() ?>
        <div><label for="tbf_bank">البنك من سجل الحسابات</label><select name="bank_account_id" id="tbf_bank" class="form-control" required>
            <option value="">اختر</option>
            <?php foreach ($banks as $b): ?><option value="<?= intval($b['id']) ?>"><?= htmlspecialchars((string) $b['bank_name'] . ' / ' . (string) $b['name'] . ' (' . (string) $b['currency'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="tbf_typ">نوع التسهيل</label><select name="facility_type" id="tbf_typ" class="form-control" required>
            <?php foreach ($typeOpts as $o): ?><option value="<?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="tbf_lim">حد التسهيل</label><input type="number" step="0.01" min="0.01" name="limit_amount" id="tbf_lim" class="form-control" required></div>
        <div><label for="tbf_exp">تاريخ الانتهاء</label><input type="date" name="expiry_date" id="tbf_exp" class="form-control" required></div>
        <div><label for="tbf_col">مرجع الضمانات</label><input type="text" name="collateral_ref" id="tbf_col" class="form-control" placeholder="مرجع سجل الضمانات"></div>
        <div><label for="tbf_aam">مرجع اعتماد الاقتراض</label><input type="text" name="aam_ref" id="tbf_aam" class="form-control" placeholder="يقيد عند نفاذ قيم السلم"></div>
        <button class="btn btn-primary">سجل التسهيل</button>
    </form>
    <?php endif; ?>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تسهيلات بنكية مسجلة بعد',
        'سجل التسهيل الاول من النموذج اعلاه، والمتاح يشتق من الحد والمستخدم ولا يكتب يدويا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr>
            <th>معرف التسهيل</th><th>البنك</th><th>نوع التسهيل</th><th>حد التسهيل</th><th>العملة</th>
            <th>مرجع الاعتماد</th><th>المستخدم</th><th>المتاح</th><th>الضمانات المقدمة</th>
            <th>تاريخ الانتهاء</th><th>جدول السداد</th><th>حالة التسهيل</th>
            <th>المنشئ</th><th>تاريخ الانشاء</th><th>حالة البيانات</th><th>مرجع المصدر</th>
            <?php if (!empty($pp['can_edit'])): ?><th>الاجراء</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r):
            $b = isset($banks[(int) $r['bank_account_id']]) ? $banks[(int) $r['bank_account_id']] : null; ?>
            <tr>
                <td><?= intval($r['id']) ?></td>
                <td><?= htmlspecialchars($b ? (string) $b['bank_name'] . ' / ' . (string) $b['name'] : ('رقم ' . intval($r['bank_account_id'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['facility_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format((float) $r['limit_amount'], 2) ?></td>
                <td><?= htmlspecialchars($b ? (string) $b['currency'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['aam_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format((float) $r['used_amount'], 2) ?></td>
                <td><?= number_format((float) $r['__avail'], 2) ?></td>
                <td><?= htmlspecialchars((string) ($r['collateral_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['expiry_date'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['schedule_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['facility_state'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= intval($r['created_by']) ?></td>
                <td><?= htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['data_state'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['src_ref'], ENT_QUOTES, 'UTF-8') ?></td>
                <?php if (!empty($pp['can_edit'])): ?>
                <td>
                    <form method="post" class="ems-inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="fac_id" value="<?= intval($r['id']) ?>">
                        <select name="new_state" class="form-control form-control-sm">
                            <?php foreach ($stateOpts as $o): ?><option value="<?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $r['facility_state'] === $o ? 'selected' : '' ?>><?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="state_note" class="form-control form-control-sm" placeholder="سبب التحول">
                        <button class="btn btn-sm btn-outline-secondary">حول الحالة</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

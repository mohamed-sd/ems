<?php
/**
 * Procurement/wh_custodians.php — إسناد أمناء المخازن (WH-03 · الحزمة الحاكمة -3)
 * ───────────────────────────────────────────────────────────────────────────
 * «أمين المخزن يتغير ولا يمحى»: الإسناد سجل تابع لسجل المخازن بحبته
 * مخزن × شخص × فترة إسناد — والأمين النافذ اليوم يشتق من هذا السجل ولا
 * يكتب يدويا في سجل المخزن، ونطاق الأمين مخزنه وحده (تصفية لا نسخ شاشات —
 * الدستور م 114). القوائم المحكومة تقرأ من مخطط الجدول نفسه فلا تنسخ
 * مفرداتها هنا، والإقفال بلا تسليم واقعة ترفع حدثا رقابيا.
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

/** القائمة المحكومة تقرأ من مخطط العمود نفسه (عبر قاموس المخطط) — المصدر واحد ولا نسخ للمفردات. */
function wh_cust_enum(mysqli $conn, $col)
{
    $out = array();
    $st = $conn->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $tbl = 'proc_wh_custodian';
    $st->bind_param('ss', $tbl, $col);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $st->close();
    if ($row && preg_match_all("~'((?:[^'\\\\]|\\\\.)*)'~u", (string) $row[0], $m)) {
        foreach ($m[1] as $v) { $out[] = str_replace("\\'", "'", $v); }
    }
    return $out;
}
$typeOpts  = wh_cust_enum($conn, 'assign_type');
$shiftOpts = wh_cust_enum($conn, 'shift_name');
$scopeOpts = wh_cust_enum($conn, 'perm_scope');
$stateOpts = wh_cust_enum($conn, 'assign_state');   // [0]=نافذ [1]=منتهٍ بتسليم [2]=منتهٍ بلا تسليم
$stActive = isset($stateOpts[0]) ? $stateOpts[0] : '';
$stClosedHand = isset($stateOpts[1]) ? $stateOpts[1] : '';
$stClosedNone = isset($stateOpts[2]) ? $stateOpts[2] : '';

/* ── معالج الإسناد الجديد ────────────────────────────────────────────────── */
$__pc = ems_post_contract($conn, array(
    'action'  => 'proc.wh.custodian_assign',
    'perm'    => 'can_add',
    'trigger' => 'employee_id',
    'idem'    => array(
        'wh'   => intval($_POST['warehouse_id'] ?? 0),
        'emp'  => intval($_POST['employee_id'] ?? 0),
        'from' => (string) ($_POST['date_from'] ?? ''),
        'typ'  => (string) ($_POST['assign_type'] ?? ''),
    ),
    'validate' => function (array $in) use ($typeOpts, $shiftOpts, $scopeOpts) {
        $wh = intval($in['warehouse_id'] ?? 0); $emp = intval($in['employee_id'] ?? 0);
        $typ = (string) ($in['assign_type'] ?? ''); $shift = (string) ($in['shift_name'] ?? '');
        $scope = (string) ($in['perm_scope'] ?? '');
        $from = (string) ($in['date_from'] ?? ''); $to = trim((string) ($in['date_to'] ?? ''));
        if ($wh <= 0 || $emp <= 0 || $from === '') { return array('ok' => false, 'msg' => 'المخزن والشخص وتاريخ البداية الزامية (422)'); }
        if (!in_array($typ, $typeOpts, true))   { return array('ok' => false, 'msg' => 'نوع الاسناد من قائمته المحكومة وحدها (422)'); }
        if (!in_array($shift, $shiftOpts, true)) { return array('ok' => false, 'msg' => 'الوردية من قائمتها المحكومة وحدها (422)'); }
        if (!in_array($scope, $scopeOpts, true)) { return array('ok' => false, 'msg' => 'نطاق الصلاحية من قائمته المحكومة وحدها (422)'); }
        if ($to !== '' && $to < $from) { return array('ok' => false, 'msg' => 'نهاية الفترة قبل بدايتها (422)'); }
        return array('ok' => true, 'data' => compact('wh', 'emp', 'typ', 'shift', 'scope', 'from', 'to'));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $d = $__pc['data'];
    $gateW = ems_tenant_db();
    // قاعدة التفرد: لا اسنادان اساسيان نافذان لمخزن واحد بوردية واحدة بفترتين متداخلتين
    $clash = 0;
    if ($d['typ'] === (isset($typeOpts[0]) ? $typeOpts[0] : '')) {
        try {
            $open = $gateW->select('proc_wh_custodian', array('where' => array(
                'warehouse_id' => $d['wh'], 'assign_type' => $d['typ'],
                'shift_name' => $d['shift'], 'assign_state' => $stActive), 'limit' => 200));
        } catch (\Throwable $t) { $open = array(); error_log('wh_custodians clash: ' . $t->getMessage()); }
        $newTo = ($d['to'] === '') ? '9999-12-31' : $d['to'];
        foreach ($open as $o) {
            $oTo = ($o['date_to'] === null || (string) $o['date_to'] === '') ? '9999-12-31' : (string) $o['date_to'];
            if ((string) $o['date_from'] <= $newTo && $oTo >= $d['from']) { $clash++; }
        }
    }
    if ($clash > 0) {
        $msg = 'يوجد اسناد اساسي نافذ متداخل الفترة لهذا المخزن وهذه الوردية، اقفل النافذ اولا (409)';
    } else {
        try {
            $gateW->insert('proc_wh_custodian', array(
                'warehouse_id' => $d['wh'], 'employee_id' => $d['emp'],
                'assign_type' => $d['typ'], 'shift_name' => $d['shift'],
                'date_from' => $d['from'], 'date_to' => ($d['to'] === '' ? null : $d['to']),
                'perm_scope' => $d['scope'], 'created_by' => $uid,
                'src_ref' => 'wh_custodians.php@' . $__pc['code'],
            ));
            $newId = intval($conn->insert_id);
            $msg = 'سجل الاسناد رقم ' . $newId;
            ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], (string) $newId);
        } catch (\Throwable $t) {
            $msg = 'تعذر التسجيل: قيد المخطط رده، راجع المدخلات (422)';
            error_log('wh_custodians insert: ' . $t->getMessage());
        }
    }
}

/* ── معالج الإقفال — بتسليم بمرجع محضره او بلا تسليم فواقعة ترفع ─────────── */
$__cc = ems_post_contract($conn, array(
    'action'  => 'proc.wh.custodian_close',
    'perm'    => 'can_edit',
    'trigger' => 'close_id',
    'idem'    => array('cid' => intval($_POST['close_id'] ?? 0), 'mode' => (string) ($_POST['close_mode'] ?? '')),
    'validate' => function (array $in) use ($stClosedHand, $stClosedNone) {
        $cid = intval($in['close_id'] ?? 0);
        $mode = (string) ($in['close_mode'] ?? '');
        $ref = trim((string) ($in['handover_ref'] ?? ''));
        $note = trim((string) ($in['close_note'] ?? ''));
        if ($cid <= 0 || !in_array($mode, array($stClosedHand, $stClosedNone), true)) { return array('ok' => false, 'msg' => 'الاقفال بحالة من حالتي الاقفال المحكومتين (422)'); }
        if ($mode === $stClosedHand && $ref === '') { return array('ok' => false, 'msg' => 'الاقفال بتسليم يوجب مرجع محضر التسليم (422)'); }
        if ($note === '') { return array('ok' => false, 'msg' => 'سبب الاقفال الزامي، لا اقفال صامتا (422)'); }
        return array('ok' => true, 'data' => compact('cid', 'mode', 'ref', 'note'));
    },
));
if (!$__cc['ok'] && $__cc['msg'] !== '') { $msg = $__cc['msg']; }
if ($__cc['replay'])                     { $msg = $__cc['msg']; }
if ($__cc['run'] && $__cc['ok']) {
    $d = $__cc['data'];
    $gateW = ems_tenant_db();
    $done = 0;
    try {
        $row = $gateW->selectOne('proc_wh_custodian', array('where' => array('id' => $d['cid'])));
        if ($row && (string) $row['assign_state'] === $stActive) {
            $gateW->update('proc_wh_custodian', array(
                'assign_state' => $d['mode'],
                'handover_ref' => ($d['ref'] === '' ? null : $d['ref']),
                'close_note' => $d['note'],
                'date_to' => ($row['date_to'] === null || (string) $row['date_to'] === '')
                    ? ems_fmt_now() : (string) $row['date_to'],
            ), array('id' => $d['cid'], 'assign_state' => $stActive));
            $done = 1;
        }
    } catch (\Throwable $t) { error_log('wh_custodians close: ' . $t->getMessage()); }
    if ($done === 1) {
        $msg = 'اقفل الاسناد رقم ' . $d['cid'];
        ems_pc_idem_mark($conn, $__cc['idem'], $__cc['code'], (string) $d['cid']);
        if ($d['mode'] === $stClosedNone) {
            try {
                \EventPublisher::publishFact($conn, array(
                    'company_id' => $company_id, 'event_key' => 'wh.custodian_closed_unreturned',
                    'category' => 'operational', 'source_module' => 'procurement',
                    'entity_type' => 'proc_wh_custodian', 'entity_id' => $d['cid'],
                    'created_by' => $uid, 'payload' => array('note' => $d['note']),
                    'idempotency_key' => 'whcust-close-' . $d['cid'],
                ));
            } catch (\Throwable $t) { error_log('wh_custodians event: ' . $t->getMessage()); }
        }
    } else { $msg = 'لم يقفل: الاسناد غير نافذ او خارج نطاقك (409)'; }
}

/* ── القراءة: الاسنادات بمخازنها واشخاصها والنافذ اليوم يشتق هنا لا يخزن ── */
$pp = check_page_permissions($conn, 'Procurement/wh_custodians.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('wh_custodians super') : ems_tenant_db();
$pickWh = intval($_GET['wh'] ?? 0);

$whs = array(); $emps = array(); $rows = array();
try { foreach ($gate->select('proc_warehouse', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'name', 'limit' => 400)) as $w) { $whs[(int) $w['id']] = $w; } }
catch (\Throwable $t) { error_log('wh_custodians whs: ' . $t->getMessage()); }
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 500);
    if ($pickWh > 0) { $opts['where'] = array('warehouse_id' => $pickWh); }
    $rows = $gate->select('proc_wh_custodian', $opts);
} catch (\Throwable $t) { error_log('wh_custodians list: ' . $t->getMessage()); }
$empPick = array();
try {
    foreach ($gate->select('employees', array('columns' => array('id', 'name', 'employee_code'),
        'orderBy' => 'name', 'limit' => 900)) as $e2) {
        if (isset($e2['is_deleted']) && (int) $e2['is_deleted'] === 1) { continue; }
        $empPick[] = $e2;
        $emps[(int) $e2['id']] = $e2;
    }
} catch (\Throwable $t) { error_log('wh_custodians emps: ' . $t->getMessage()); }
foreach ($rows as $r) {
    $eid = (int) $r['employee_id'];
    if (isset($emps[$eid])) { continue; }
    try {
        $e2 = $gate->selectOne('employees', array('where' => array('id' => $eid)));
        if ($e2) { $emps[$eid] = $e2; }
    } catch (\Throwable $t) { error_log('wh_custodians emp: ' . $t->getMessage()); }
}

$today = ems_fmt_now();
$activeToday = 0; $openN = 0; $closedNoHand = 0;
foreach ($rows as $i => $r) {
    $isActive = ((string) $r['assign_state'] === $stActive)
        && ((string) $r['date_from'] <= $today)
        && ($r['date_to'] === null || (string) $r['date_to'] >= $today);
    $rows[$i]['__active_today'] = $isActive;
    if ($isActive) { $activeToday++; }
    if ((string) $r['assign_state'] === $stActive) { $openN++; }
    if ((string) $r['assign_state'] === $stClosedNone) { $closedNoHand++; }
}

$page_title = 'إيكوبيشن | إسناد أمناء المخازن';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php $header_title = 'إسناد أمناء المخازن'; $header_icon = 'fa fa-user-shield'; $header_actions = array();
    $header_back = array('href' => 'warehouses.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل المخازن');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">اسنادات مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $activeToday ?></div><div class="ems-stat-label">نافذ اليوم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $openN ?></div><div class="ems-stat-label">مفتوح الفترة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $closedNoHand ?></div><div class="ems-stat-label">اقفل بلا تسليم</div></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (!empty($pp['can_add'])): ?>
    <h3 class="ems-section-title">اسناد جديد</h3>
    <form method="post" class="ems-form">
        <?= csrf_field() ?>
        <div><label for="whc_wh">المخزن</label><select name="warehouse_id" id="whc_wh" class="form-control" required>
            <option value="">اختر</option>
            <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>"><?= htmlspecialchars((string) $w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="whc_emp">الشخص من سجل الموارد</label><select name="employee_id" id="whc_emp" class="form-control" required>
            <option value="">اختر</option>
            <?php foreach ($empPick as $e): ?><option value="<?= intval($e['id']) ?>"><?= htmlspecialchars((string) $e['name'] . ' (' . (string) $e['employee_code'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="whc_typ">نوع الاسناد</label><select name="assign_type" id="whc_typ" class="form-control" required>
            <?php foreach ($typeOpts as $o): ?><option value="<?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="whc_sh">الوردية</label><select name="shift_name" id="whc_sh" class="form-control" required>
            <?php foreach ($shiftOpts as $o): ?><option value="<?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="whc_from">من تاريخ</label><input type="date" name="date_from" id="whc_from" class="form-control" required></div>
        <div><label for="whc_to">الى تاريخ</label><input type="date" name="date_to" id="whc_to" class="form-control"></div>
        <div><label for="whc_scope">نطاق الصلاحية</label><select name="perm_scope" id="whc_scope" class="form-control" required>
            <?php foreach ($scopeOpts as $o): ?><option value="<?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn btn-primary">سجل الاسناد</button>
    </form>
    <?php endif; ?>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="whc_pick">المخزن</label><select name="wh" id="whc_pick" onchange="this.form.submit()">
            <option value="0">الكل</option>
            <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>" <?= $pickWh === (int) $w['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اسنادات امناء بعد',
        'الاسناد سجل بفترته لا حقل يمحى، سجل الاسناد الاول من النموذج اعلاه والنافذ اليوم يشتق تلقائيا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr>
            <th>معرف الاسناد</th><th>المخزن</th><th>مرجع الشخص</th><th>اسم الامين</th>
            <th>نوع الاسناد</th><th>الوردية</th><th>من تاريخ</th><th>الى تاريخ</th>
            <th>نطاق الصلاحية</th><th>مرجع محضر التسليم</th><th>النافذ اليوم</th><th>حالة الاسناد</th>
            <th>المنشئ</th><th>تاريخ الانشاء</th><th>حالة البيانات</th><th>مرجع المصدر</th>
            <?php if (!empty($pp['can_edit'])): ?><th>الاجراء</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r):
            $w = isset($whs[(int) $r['warehouse_id']]) ? $whs[(int) $r['warehouse_id']] : null;
            $e = isset($emps[(int) $r['employee_id']]) ? $emps[(int) $r['employee_id']] : null; ?>
            <tr>
                <td><?= intval($r['id']) ?></td>
                <td><?= htmlspecialchars($w ? (string) $w['name'] : ('رقم ' . intval($r['warehouse_id'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($e ? (string) $e['employee_code'] : ('رقم ' . intval($r['employee_id'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($e ? (string) $e['name'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['assign_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['shift_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['date_from'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['perm_scope'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['handover_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $r['__active_today'] ? 'نعم' : 'لا' ?></td>
                <td><?= htmlspecialchars((string) $r['assign_state'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= intval($r['created_by']) ?></td>
                <td><?= htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['data_state'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['src_ref'], ENT_QUOTES, 'UTF-8') ?></td>
                <?php if (!empty($pp['can_edit'])): ?>
                <td>
                    <?php if ((string) $r['assign_state'] === $stActive): ?>
                    <form method="post" class="ems-inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="close_id" value="<?= intval($r['id']) ?>">
                        <select name="close_mode" class="form-control form-control-sm">
                            <option value="<?= htmlspecialchars($stClosedHand, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stClosedHand, ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="<?= htmlspecialchars($stClosedNone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stClosedNone, ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                        <input type="text" name="handover_ref" class="form-control form-control-sm" placeholder="مرجع محضر التسليم">
                        <input type="text" name="close_note" class="form-control form-control-sm" placeholder="سبب الاقفال" required>
                        <button class="btn btn-sm btn-outline-danger">اقفل</button>
                    </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

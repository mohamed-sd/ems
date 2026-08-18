<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Workforce/employee_settlements.php — تسويات الموظفين (UX-02 §15.3 · UX-05 §2.2)
 * ───────────────────────────────────────────────────────────────────────────
 * «الاستحقاق الأولي ← التحميلات ← التسوية ← صافي المستحق ← اعتماده ← طلب الدفع».
 *
 * **الخدمةُ واحدةٌ للطرفين** (`SettlementService`): هذه الشاشةُ توأمُ
 * `Suppliers/settlements.php` بحروفها — الحالاتُ الست نفسُها، وفصلُ اليدين
 * نفسُه، والصافي السالبُ يفتح ذمّةً مدينةً كما هو. ولا سطرَ منطقٍ هنا: الفرقُ
 * كلُّه `party_type = 'employee'` بدل `'supplier'`.
 *
 * **لماذا شاشتان لا شاشةٌ بمُبدِّل**: الملكيةُ تختلف — الموارد البشرية (4) تُعدّ
 * تسويةَ موظفها، وإدارةُ الموردين (2) تُعدّ تسويةَ موردها، ولا يرى أحدُهما
 * دفترَ الآخر. والصلاحيةُ في هذا النظام تُمنح **بالشاشة** (`modules.code`)،
 * فمُبدِّلٌ داخل شاشةٍ واحدة يعني حارسًا يدويًّا لكل طرفٍ داخل الكود — وهو
 * بابُ خطأٍ لا يُغلق. شاشتان بمنحتين أصدقُ وأبسط.
 *
 * **فصلُ اليدين** (§15.4): الموارد البشرية (4) تُعدّ وتراجع، ومديرُ الإدارة
 * المالية (19) يُجيز. والخدمةُ تمنع فوقه اعتمادَ المرءِ ما أعدّ بنيويًّا.
 *
 * ⚠️ **حدٌّ معلَنٌ لا مخبوء — الخصومُ اليدوية**: `SettlementService::collectLines`
 * يجلب للموظف استحقاقَه وتحميلاتِه **من دفتر الذمم وحده**، ولا يجلب له قطعَ
 * غيارٍ ولا أوامرَ صيانة (وهي للمورد بطبيعتها). أما **السلفُ والجزاءاتُ** فلا
 * مصدرَ مستنديًّا لها بعد (نطاقُ M-11/M-21) — فتُدخَل اليومَ في دفتر الذمم
 * يدويًّا. وهذا مكتوبٌ في الشاشة للمستخدم لا مخبوءٌ في تعليق: حدٌّ معلَنٌ
 * أصدقُ من مصدرٍ مُخترَع (قاعدةُ عدم التلفيق).
 *
 * **الصلاحيةُ صارمة**: لا اعتمادَ على `check_page_permissions` — فهي **تُفتح
 * على مصراعيها** حين لا تجد الوحدة (توافقيةٌ مع شاشاتٍ قديمة)، وهذه شاشةٌ يخرج
 * منها مال. الوحدةُ تُطلب صراحةً وغيابُها منعٌ لا إذن.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Settlement/SettlementService.php';

use App\Services\Settlement\SettlementService as SVC;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها الحرفي، وغيابُها منع ────────────────────
$MODULE_CODE = 'Workforce/employee_settlements.php';
$can_view = $can_edit = $can_approve = false;
if ($is_super_admin) {
    $can_view = $can_edit = $can_approve = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view    = (intval($row['can_view']) === 1);
        $can_edit    = (intval($row['can_add'])  === 1);   // الإعداد والتوليد
        $can_approve = (intval($row['can_edit']) === 1);   // الإجازة
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض تسويات الموظفين ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = ems_tenant_db();

// ── توليدُ تسوية ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if (!$can_edit) { ems_gov_flash_redirect('employee_settlements.php', 'لا توجد صلاحية الإعداد ❌', 'GOV-PERM-403', ''); exit(); }
    $party = intval($_POST['party_ref'] ?? 0);
    $from  = trim(strval($_POST['period_from'] ?? ''));
    $to    = trim(strval($_POST['period_to'] ?? ''));

    // العطالةُ في الخدمة: تسويةٌ واحدةٌ لكل (طرف × فترة) تُرجع 409 بمرجع القائم
    // — فالطلبُ المكرر يفتح التسويةَ نفسَها ولا يولّد ثانيةً ولا يخصم مرتين.
    $res = SVC::generate($gate, $conn, 'employee', $party, $from, $to, $uid);
    if ($res['ok']) {
        $m = 'تولّدت+التسوية+—+' . intval($res['entitlements']) . '+استحقاقًا+و' .
             intval($res['charges']) . '+تحميلًا';
        if (intval($res['unpriced']) > 0) {
            $m .= '+·+' . intval($res['unpriced']) . '+بندًا+بلا+سعرِ+صرف';
        }
        ems_gov_redirect("Location: employee_settlements.php?msg={$m}+✅&open=" . intval($res['settlement_id']));
    } else {
        ems_gov_flash_redirect(ems_flash_to('employee_settlements.php', "+❌" . ($res['settlement_id'] ? '&open=' . intval($res['settlement_id']) : '')), $res['reason'], 'GOV-INFO-200', '');
    }
    exit();
}

// ── رفعٌ للمراجعة · اعتراضٌ · حسم · اعتماد ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = strval($_POST['action']);
    $sid = intval($_POST['sid'] ?? 0);
    $res = array('ok' => false, 'reason' => 'إجراءٌ غير معروف');

    // ⚠️ التسويةُ المفتوحةُ يجب أن تكون **تسويةَ موظف**: بلا هذا الفحص يصير
    // معرّفٌ مُلفَّقٌ في `sid` بابًا خلفيًّا يعتمد به موظفُ الموارد البشرية
    // تسويةَ موردٍ لا يملك شاشتَها (الصلاحيةُ بالشاشة، فالحارسُ بالشاشة).
    $owned = false;
    if ($sid > 0) {
        try {
            $chk = $gate->selectOne('settlements', array(
                'columns' => array('id', 'party_type'), 'where' => array('id' => $sid)));
            $owned = ($chk && (string) $chk['party_type'] === 'employee');
        } catch (\Throwable $t) { error_log('emp settlement own chk: ' . $t->getMessage()); }
    }

    if (!$owned) {
        $res['reason'] = 'التسويةُ غير موجودةٍ أو ليست تسويةَ موظف';
    } elseif ($act === 'submit' && $can_edit) {
        $res = SVC::submit($gate, $sid, $uid);
    } elseif ($act === 'object' && $can_edit) {
        $res = SVC::objectLine($gate, intval($_POST['line_id'] ?? 0),
                               strval($_POST['note'] ?? ''), $uid);
    } elseif ($act === 'resolve' && $can_edit) {
        $res = SVC::resolveObjection($gate, intval($_POST['line_id'] ?? 0), $uid);
    } elseif ($act === 'approve' && $can_approve) {
        $res = SVC::approve($gate, $conn, $sid, $uid);
        if ($res['ok'] && $res['net_direction'] === 'receivable') {
            $res['reason'] = 'اعتُمدت — والصافي سالبٌ ففُتحت ذمّةٌ مدينةٌ على الموظف';
        }
    } else {
        $res['reason'] = 'لا توجد صلاحية لهذا الإجراء';
    }

    $msg = $res['ok'] ? (($res['reason'] !== '') ? $res['reason'] . ' ✅' : 'تم ✅')
                      : ($res['reason'] . ' ❌');
    ems_gov_redirect("Location: employee_settlements.php?msg=" . rawurlencode($msg) . "&open={$sid}");
    exit();
}

// ── القراءة ──────────────────────────────────────────────────────────────
$open = isset($_GET['open']) ? intval($_GET['open']) : 0;

$settlements = array();
try {
    $settlements = $gate->select('settlements', array(
        'whereRaw' => "party_type = 'employee'",
        'orderBy'  => 'id DESC',
        'limit'    => 100,
    ));
} catch (\Throwable $t) { error_log('employee settlements list: ' . $t->getMessage()); }

// الموظفون المرشَّحون: مَن له حكمٌ في دفتر الذمم — لا كلُّ مَن في الملف.
// «لا تسويةَ من عدم» (§15.4)، فعرضُ 300 موظفٍ بلا حكمٍ يجعل الشاشةَ متاهةً
// ويُغري بتوليدٍ يعود 422. والقائمةُ تُبنى من المصدر نفسِه الذي تجلب منه الخدمة.
$employees = array();
try {
    $employees = $gate->scopedQuery(
        array('scope' => array('d' => 'fin_dues'), 'enrich' => array('e' => 'employees')),
        "SELECT d.party_ref AS id, MAX(e.name) AS name,
                COUNT(*) AS due_rows,
                SUM(d.direction = 'credit') AS credits,
                SUM(d.direction = 'debit')  AS debits,
                SUM(d.settlement_id IS NULL) AS open_rows
           FROM fin_dues d
           LEFT JOIN employees e ON e.id = d.party_ref
          WHERE {TENANT_SCOPE} AND d.party_type = 'employee'
            AND COALESCE(d.is_deleted, 0) = 0
          GROUP BY d.party_ref
          ORDER BY open_rows DESC, name", array());
} catch (\Throwable $t) { error_log('employee settlements parties: ' . $t->getMessage()); }

// أرقامُ طلبات الدفع المولَّدة — جلبةٌ واحدةٌ لا استعلامٌ لكل صف
$reqMap = array();
$reqIds = array();
foreach ($settlements as $s) {
    if (!empty($s['payment_request_id'])) { $reqIds[] = intval($s['payment_request_id']); }
}
if ($reqIds) {
    try {
        $in = implode(',', array_map('intval', $reqIds));
        $rows = $gate->scopedQuery(
            array('scope' => array('r' => 'fin_requests')),
            "SELECT r.id, r.request_no FROM fin_requests r
              WHERE {TENANT_SCOPE} AND r.id IN ({$in})",
            array()
        );
        foreach ($rows as $r) { $reqMap[intval($r['id'])] = (string) $r['request_no']; }
    } catch (\Throwable $t) { error_log('employee settlement req nos: ' . $t->getMessage()); }
}

$lines = array();
if ($open > 0) {
    try {
        $lines = $gate->select('settlement_lines', array(
            'where' => array('settlement_id' => $open), 'orderBy' => 'line_kind, id',
        ));
    } catch (\Throwable $t) { error_log('employee settlement lines: ' . $t->getMessage()); }
}

$STATE_AR = array(
    'draft' => 'مسودة', 'review' => 'قيد المراجعة', 'approved' => 'معتمدة',
    'payment_requested' => 'طُلب الدفع', 'paid' => 'مدفوعة', 'cancelled' => 'ملغاة',
);
$CHARGE_AR = array(
    'fuel' => 'وقود', 'parts' => 'قطع غيار', 'maintenance' => 'صيانة',
    'transport' => 'نقل', 'advance' => 'سلفة', 'penalty' => 'جزاء',
);

$page_title = 'إيكوبيشن | تسويات الموظفين';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main emp-settlements-main ems-unified-page-shell">
    <?php
    $header_title = 'تسويات الموظفين';
    $header_icon  = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا تسوياتِ موظفين مولَّدةً بعدُ', 'اختر الموظفَ والفترةَ في «تسويةٌ جديدة» ثم اضغطْ «ولّد التسوية»');
    ?>
    <style>
        .es-msg-body { padding: var(--space-3) var(--space-4); }
        .es-note-text { color: var(--c-4b5563); margin: 0; line-height: var(--leading-loose); }
        .es-warn-body { border-right: 4px solid var(--c-f59e0b, #f59e0b); }
        .es-warn-text { color: var(--c-note-ink); margin: 0; line-height: var(--leading-loose); }
        .es-muted-text { color: var(--c-ink-500); margin: 0; line-height: var(--leading-loose); }
        .es-section-title { margin: 0 0 10px; }
        .es-bare-form { box-shadow: none; padding: 0; }
        .es-submit-row { margin-top: 10px; }
        .es-scroll-x { overflow-x: auto; }
        .es-table-full { width: 100%; }
        .es-empty-cell { text-align: center; color: var(--c-ink-500); padding: 18px; }
        .es-row-open { background: var(--c-fffbeb); }
        .es-row-objected { background: var(--c-fef2f2); }
        .es-net-receivable { color: var(--c-state-danger-deep); }
        .es-net-payable { color: var(--c-state-ok-deep); }
        .es-debt-note { color: var(--c-state-danger-deep); }
        .es-no-pay-note { color: var(--c-ink-500); }
        .es-objection-note { color: var(--c-state-danger-deep); }
        .es-nowrap { white-space: nowrap; }
        .es-inline-form { display: inline; }
        .es-objection-input { width: 150px; padding: 3px 6px; font-size: var(--text-caption); }
    </style>

    <?php if (isset($_GET['msg'])): ?>
    <div class="card"><div class="card-body es-msg-body">
        <?php echo htmlspecialchars(strval($_GET['msg'])); ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <p class="es-note-text">
            <i class="fas fa-circle-info"></i>
            اختر الموظفَ والفترة، والنظامُ <strong>يجلب البنودَ من مصادرها</strong> —
            استحقاقُه وتحميلاتُه من دفتر ذممه، كلٌّ برابط أصله. لا تُدخل مبلغًا واحدًا بيدك.
            وحين تفوق التحميلاتُ استحقاقَه يصير الصافي سالبًا فتُفتح <strong>ذمّةٌ مدينةٌ عليه</strong>.
            <br>
            <strong>مَن يُعدّ لا يُجيز:</strong> الموارد البشرية تُعدّ وتراجع، ومديرُ الإدارة المالية يُجيز.
        </p>
    </div></div>

    <div class="card"><div class="card-body es-warn-body">
        <p class="es-warn-text">
            <i class="fas fa-triangle-exclamation"></i>
            <strong>حدٌّ معلَن — خصومُ السلف والجزاءات:</strong>
            هذه الشاشةُ تجلب للموظف ما في <strong>دفتر ذممه</strong> فقط. ولا مصدرَ
            مستنديًّا بعدُ لسلفةِ موظفٍ ولا لجزائه — فحتى يُبنى مصدراهما، تُدخَل
            هذه الخصومُ في دفتر الذمم يدويًّا ثم تظهر هنا تحميلًا.
            <strong>ما لا يُدخَل هناك لا يُخصَم هنا</strong> — والشاشةُ لا تخترع خصمًا لا أصلَ له.
        </p>
    </div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-body">
        <h5 class="es-section-title"><i class="fas fa-plus"></i> تسويةٌ جديدة</h5>
        <?php if (!$employees): ?>
            <p class="es-muted-text">
                <i class="fas fa-circle-info"></i>
                لا موظفَ له حكمٌ في دفتر الذمم بعد — فلا تسويةَ تُولَّد.
                «لا تسويةَ من عدم»: يظهر الموظفُ هنا حالما يُسجَّل له استحقاقٌ أو تحميل.
            </p>
        <?php else: ?>
        <form action="" method="post" class="allforms allforms-visible es-bare-form">
        <?= csrf_field() ?>
            <input type="hidden" name="generate" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="emsf_1685_d632b">الموظف *</label>
                    <select name="party_ref" required id="emsf_1685_d632b">
                        <option value="">— اختر —</option>
                        <?php foreach ($employees as $e) {
                            $nm = ($e['name'] !== null && $e['name'] !== '')
                                  ? (string) $e['name'] : ('موظف #' . intval($e['id']));
                            echo "<option value='" . intval($e['id']) . "'>"
                               . htmlspecialchars($nm)
                               . ' — ' . intval($e['open_rows']) . ' بندًا مفتوحًا'
                               . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group"><label for="emsf_1686_96bca">من *</label>
                    <input type="date" name="period_from" required id="emsf_1686_96bca"></div>
                <div class="form-group"><label for="emsf_1687_5f1c3">إلى *</label>
                    <input type="date" name="period_to" required id="emsf_1687_5f1c3"></div>
            </div></div>
            <div class="es-submit-row">
                <button type="submit" class="btn btn-primary"><i class="fas fa-wand-magic-sparkles"></i> ولّد التسوية</button>
            </div>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h5 class="es-section-title"><i class="fas fa-list"></i> التسويات</h5>
        <div class="es-scroll-x">
        <table class="table table-striped es-table-full">
            <thead><tr>
                <th>الرقم</th><th>الموظف</th><th>الفترة</th>
                <th>الأولي</th><th>التحميلات</th><th>الصافي</th>
                <th>الحالة</th><th>طلب الدفع</th><th>اعتراضات</th><th></th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                </tr></thead>
            <tbody>
            <?php if (!$settlements): ?>
                <tr><td colspan="10" class="es-empty-cell">
                    لا تسويةَ بعد — ابدأ بتوليد واحدةٍ من النموذج أعلاه.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($settlements as $s):
                $net = (float) $s['net_amount'];
                $isRecv = ((string) $s['net_direction'] === 'receivable');
            ?>
                <tr<?php echo (intval($s['id']) === $open) ? ' class="es-row-open"' : ''; ?>>
                    <td><code><?php echo htmlspecialchars((string) $s['settlement_no']); ?></code></td>
                    <td><?php echo htmlspecialchars((string) $s['party_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['period_from'] . ' → ' . $s['period_to']); ?></td>
                    <td><?php echo number_format((float) $s['gross_amount'], 2); ?></td>
                    <td><?php echo number_format((float) $s['charges_amount'], 2); ?></td>
                    <td>
                        <strong class="<?php echo $isRecv ? "es-net-receivable" : "es-net-payable"; ?>">
                            <?php echo number_format($net, 2) . ' ' . htmlspecialchars((string) $s['currency']); ?>
                        </strong>
                        <?php if ($isRecv): ?>
                            <br><small class="es-debt-note">دَينٌ على الموظف</small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-secondary">
                        <?php echo htmlspecialchars(isset($STATE_AR[$s['state']]) ? $STATE_AR[$s['state']] : (string) $s['state']); ?>
                    </span></td>
                    <td>
                        <?php if (!empty($s['payment_request_id'])):
                            $rq = isset($reqMap[intval($s['payment_request_id'])])
                                  ? $reqMap[intval($s['payment_request_id'])] : null; ?>
                            <a href="../FinRequests/request_form.php?id=<?php echo intval($s['payment_request_id']); ?>"
                               title="افتح طلبَ الدفع ورحلتَه">
                                <?php echo htmlspecialchars($rq !== null ? $rq : ('#' . intval($s['payment_request_id']))); ?> ↗
                            </a>
                        <?php elseif ($isRecv): ?>
                            <small class="es-no-pay-note">لا دفعَ — دَينٌ عليه</small>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if (intval($s['open_objections']) > 0): ?>
                            <span class="badge badge-warning"><?php echo intval($s['open_objections']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="es-nowrap">
                        <a class="btn btn-sm btn-secondary" href="?open=<?php echo intval($s['id']); ?>">البنود</a>
                        <?php if ($can_edit && (string) $s['state'] === 'draft'): ?>
                        <form action="" method="post" class="es-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="sid" value="<?php echo intval($s['id']); ?>">
                            <button class="btn btn-sm btn-primary" type="submit">للمراجعة</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($can_approve && (string) $s['state'] === 'review'): ?>
                        <form action="" method="post" class="es-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="sid" value="<?php echo intval($s['id']); ?>">
                            <button class="btn btn-sm btn-primary" type="submit">إجازة</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php if ($open > 0): ?>
    <div class="card"><div class="card-body">
        <h5 class="es-section-title"><i class="fas fa-list-ul"></i> بنودُ التسوية #<?php echo $open; ?></h5>
        <div class="es-scroll-x">
        <table class="table table-striped es-table-full">
            <thead><tr>
                <th>النوع</th><th>البيان</th><th>الأصل</th><th>التاريخ</th>
                <th>المبلغ</th><th>المعادل</th><th>الاعتراض</th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$lines): ?>
                <tr><td colspan="8" class="es-empty-cell">لا بنود.</td></tr>
            <?php endif; ?>
            <?php foreach ($lines as $l):
                $isCharge = ((string) $l['line_kind'] === 'charge');
            ?>
                <tr<?php echo intval($l['objected']) === 1 ? ' class="es-row-objected"' : ''; ?>>
                    <td>
                        <?php if ($isCharge): ?>
                            <span class="badge badge-warning">تحميل<?php
                                echo isset($CHARGE_AR[$l['charge_type']]) ? ' · ' . $CHARGE_AR[$l['charge_type']] : ''; ?></span>
                        <?php else: ?>
                            <span class="badge badge-success">استحقاق</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string) $l['description']); ?></td>
                    <td><code><?php echo htmlspecialchars($l['source_kind'] . '#' . $l['source_ref']); ?></code></td>
                    <td><?php echo htmlspecialchars((string) $l['work_date']); ?></td>
                    <td><?php echo number_format((float) $l['amount'], 2) . ' ' . htmlspecialchars((string) $l['currency']); ?></td>
                    <td>
                        <?php if ($l['base_amount'] === null): ?>
                            <span class="badge badge-warning">بانتظار سعر</span>
                        <?php else: ?>
                            <?php echo number_format((float) $l['base_amount'], 2); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (intval($l['objected']) === 1): ?>
                            <span class="es-objection-note"><?php echo htmlspecialchars((string) $l['objection_note']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="es-nowrap">
                        <?php if ($can_edit && intval($l['objected']) === 0): ?>
                        <form action="" method="post" class="es-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="object">
                            <input type="hidden" name="sid" value="<?php echo $open; ?>">
                            <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                            <input type="text" name="note" placeholder="سبب الاعتراض" required
                                   class="es-objection-input" aria-label="سبب الاعتراض">
                            <button class="btn btn-sm btn-secondary" type="submit">اعتراض</button>
                        </form>
                        <?php elseif ($can_edit): ?>
                        <form action="" method="post" class="es-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="sid" value="<?php echo $open; ?>">
                            <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                            <button class="btn btn-sm btn-secondary" type="submit">حسم</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>
    <?php endif; ?>

</div>

<?php include '../infooter.php'; ?>

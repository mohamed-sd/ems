<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Suppliers/settlements.php — تسويات الموردين (UX-05 §5.2 · UX-02 §15.3)
 * ───────────────────────────────────────────────────────────────────────────
 * «الاستحقاق الأولي ← التحميلات ← التسوية ← صافي المستحق ← اعتماده ← طلب الدفع».
 *
 * **صفرُ إدخالِ مبلغ**: البنودُ تُجلب من مصادرها (دفترُ الطرف · صرفُ القطع ·
 * أمرُ الصيانة) كلٌّ برابط أصله — المستخدمُ يختار الطرفَ والفترةَ فقط.
 *
 * **فصلُ اليدين** (قرارُ المالك 2026-07-28): إدارةُ الموردين (2) تُعدّ وتراجع،
 * ومديرُ الإدارة المالية (19) يُجيز. والخدمةُ تمنع اعتمادَ المرءِ ما أعدّ بنيويًّا.
 *
 * **الصافي السالب**: حين تفوق التحميلاتُ الاستحقاقَ تُفتح ذمّةٌ مدينةٌ على المورد
 * — فالدَّينُ يُطارَد ولا يُنسى.
 *
 * **الصلاحيةُ صارمة**: لا اعتمادَ على `check_page_permissions` هنا لأنها **تُفتح
 * على مصراعيها** حين لا تجد الوحدة (توافقيةٌ مع شاشاتٍ قديمة) — وهذه شاشةٌ يخرج
 * منها مال. الوحدةُ تُطلب صراحةً وغيابُها منعٌ لا إذن.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../includes/ladder_gate.php';
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
$MODULE_CODE = 'Suppliers/settlements.php';
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض التسويات ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = ems_tenant_db();

// ── توليدُ تسوية ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if (!$can_edit) { ems_gov_flash_redirect('settlements.php', 'لا توجد صلاحية الإعداد ❌', 'GOV-PERM-403', ''); exit(); }
    $party = intval($_POST['party_ref'] ?? 0);
    $from  = trim(strval($_POST['period_from'] ?? ''));
    $to    = trim(strval($_POST['period_to'] ?? ''));

    $res = SVC::generate($gate, $conn, 'supplier', $party, $from, $to, $uid);
    if ($res['ok']) {
        $m = 'تولدت+التسوية+—+' . intval($res['entitlements']) . '+استحقاقا+و' .
             intval($res['charges']) . '+تحميلا';
        if (intval($res['unpriced']) > 0) {
            $m .= '+·+' . intval($res['unpriced']) . '+بندا+بلا+سعر+صرف';
        }
        ems_gov_redirect("Location: settlements.php?msg={$m}+✅&open=" . intval($res['settlement_id']));
    } else {
        ems_gov_flash_redirect(ems_flash_to('settlements.php', "+❌" . ($res['settlement_id'] ? '&open=' . intval($res['settlement_id']) : '')), $res['reason'], 'GOV-INFO-200', '');
    }
    exit();
}

// ── رفعٌ للمراجعة · اعتراضٌ · حسم · اعتماد ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = strval($_POST['action']);
    $sid = intval($_POST['sid'] ?? 0);
    $res = array('ok' => false, 'reason' => 'إجراء غير معروف');

    if ($act === 'submit' && $can_edit) {
        $res = SVC::submit($gate, $sid, $uid);
    } elseif ($act === 'object' && $can_edit) {
        $res = SVC::objectLine($gate, intval($_POST['line_id'] ?? 0),
                               strval($_POST['note'] ?? ''), $uid);
    } elseif ($act === 'resolve' && $can_edit) {
        $res = SVC::resolveObjection($gate, intval($_POST['line_id'] ?? 0), $uid);
    } elseif ($act === 'approve' && $can_approve) {
        /* ══ وصلُ السلّم LD-13 — تسويةُ المورد · INJ-CHAIN-CLOSE-01 ══
           ◆ كان الاعتمادُ يقع بلا قراءةِ سلّمِه، فبقي ترتيبُ الخطواتِ
             و«لا يدَ تمشي خطوتَين» غيرَ منفَّذَين في سلّمِ التسوية.
           ◆ والنمطُ `monitor` افتراضًا: يُسجَّل الخرقُ ولا يُمنع — فقلبُ
             المنعِ تغييرُ وصولٍ حيٍّ يلزمه قياسٌ مستقرٌّ بصفرِ خرق. */
        $__lg = ems_ladder_guard($conn, 'LD-13', $company_id, 'settlement', $sid, $uid);
        if (!$__lg['ok']) {
            /* FR-APP-003 — الرمزُ الموحَّدُ في الردِّ: رفضٌ بلا رمزٍ لا يُتتبَّع */
            $res = array('ok' => false, 'code' => EMS_LADDER_DENY_CODE,
                         'reason' => $__lg['reason'] . ' (' . EMS_LADDER_DENY_CODE . ')',
                         'net_direction' => '');
        } else {
        $res = SVC::approve($gate, $conn, $sid, $uid);
        if ($res['ok'] && $res['net_direction'] === 'receivable') {
            $res['reason'] = 'اعتمدت — والصافي سالب ففتحت ذمة مدينة على المورد';
        }
        }
    } elseif ($act === 'invoice' && $can_edit) {
        // M-13 · «استلامُ فاتورة المورد ومطابقتُها بالصافي المعتمد» (ENT-02 §4)
        $res = SVC::markInvoiced($gate, $conn, $sid, array(
            'invoice_no'       => $_POST['invoice_no'] ?? '',
            'invoice_date'     => $_POST['invoice_date'] ?? '',
            'invoice_amount'   => $_POST['invoice_amount'] ?? '',
            'invoice_currency' => $_POST['invoice_currency'] ?? '',
            'diff_reason'      => $_POST['diff_reason'] ?? '',
            'diff_doc_ref'     => $_POST['diff_doc_ref'] ?? '',
        ), $uid);
    } elseif ($act === 'close' && $can_approve) {
        $res = SVC::close($gate, $conn, $sid, $uid);
    } else {
        $res['reason'] = 'لا توجد صلاحية لهذا الإجراء';
    }

    $msg = $res['ok'] ? (($res['reason'] !== '') ? $res['reason'] . ' ✅' : 'تم ✅')
                      : ($res['reason'] . ' ❌');
    ems_gov_redirect("Location: settlements.php?msg=" . rawurlencode($msg) . "&open={$sid}");
    exit();
}

// ── القراءة ──────────────────────────────────────────────────────────────
$open = isset($_GET['open']) ? intval($_GET['open']) : 0;

$settlements = array();
try {
    $settlements = $gate->select('settlements', array(
        'whereRaw' => "party_type = 'supplier'",
        'orderBy'  => 'id DESC',
        'limit'    => 100,
    ));
} catch (\Throwable $t) { error_log('settlements list: ' . $t->getMessage()); }

$suppliers = array();
try {
    $suppliers = $gate->select('suppliers', array(
        'columns' => array('id', 'name'), 'orderBy' => 'name',
    ));
} catch (\Throwable $t) { error_log('settlements suppliers: ' . $t->getMessage()); }

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
    } catch (\Throwable $t) { error_log('settlement req nos: ' . $t->getMessage()); }
}

$lines = array();
if ($open > 0) {
    try {
        $lines = $gate->select('settlement_lines', array(
            'where' => array('settlement_id' => $open), 'orderBy' => 'line_kind, id',
        ));
    } catch (\Throwable $t) { error_log('settlement lines: ' . $t->getMessage()); }
}

$STATE_AR = array(
    'draft' => 'مسودة', 'review' => 'قيد المراجعة', 'approved' => 'معتمدة',
    'payment_requested' => 'طلب الدفع', 'invoiced' => 'مفوترة', 'paid' => 'مدفوعة',
    'closed' => 'مقفلة', 'cancelled' => 'ملغاة',
);
$CHARGE_AR = array(
    'fuel' => 'وقود', 'parts' => 'قطع غيار', 'maintenance' => 'صيانة',
    'transport' => 'نقل', 'advance' => 'سلفة', 'penalty' => 'جزاء',
);

$page_title = 'إيكوبيشن | تسويات الموردين';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'settlement'; $sft_active = 'settlement';
include __DIR__ . '/../includes/sales_family_tabs.php';
?>

<div class="main sup-settlements-main ems-unified-page-shell">
    <?php
    $header_title = 'تسويات الموردين';
    $header_icon  = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا تسوية مورد مولدة بعد', 'اختر المورد والفترة من نموذج «تسوية جديدة» واضغط «ولد التسوية» — البنود تجلب من مصادرها');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <?php if (isset($_GET['msg'])): ?>
    <div class="card"><div class="card-body sup-set-msg-body">
        <?php echo htmlspecialchars(strval($_GET['msg'])); ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <p class="sup-set-lead">
            <i class="fas fa-circle-info"></i>
            اختر المورد والفترة، والنظام <strong>يجلب البنود من مصادرها</strong> —
            استحقاقه من دفتر ذممه، وتحميلاته (وقود · قطع · صيانة · نقل · سلف · جزاءات)
            كل برابط أصله. لا تدخل مبلغا واحدا بيدك.
            وحين تفوق التحميلات استحقاقه يصير الصافي سالبا فتفتح <strong>ذمة مدينة عليه</strong>.
            <br>
            <strong>من يعد لا يجيز:</strong> إدارة الموردين تعد وتراجع، ومدير الإدارة المالية يجيز.
        </p>
    </div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-body">
        <h5 class="sup-set-h5"><i class="fas fa-plus"></i> تسوية جديدة</h5>
        <form action="" method="post" class="allforms allforms-visible sup-set-gen-form">
        <?= csrf_field() ?>
            <input type="hidden" name="generate" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="emsf_470_fe909">المورد *</label>
                    <select name="party_ref" required id="emsf_470_fe909">
                        <option value="">— اختر —</option>
                        <?php foreach ($suppliers as $s) {
                            echo "<option value='" . intval($s['id']) . "'>"
                               . htmlspecialchars((string) $s['name']) . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group"><label for="emsf_471_744bc">من *</label>
                    <input type="date" name="period_from" required id="emsf_471_744bc"></div>
                <div class="form-group"><label for="emsf_472_bb57c">إلى *</label>
                    <input type="date" name="period_to" required id="emsf_472_bb57c"></div>
            </div></div>
            <div class="sup-set-actions-sm">
                <button type="submit" class="btn btn-primary"><i class="fas fa-wand-magic-sparkles"></i> ولد التسوية</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h5 class="sup-set-h5"><i class="fas fa-list"></i> التسويات</h5>
        <div class="sup-set-scroll">
        <table class="table table-striped sup-set-table">
            <thead><tr>
                <th>رقم التسوية</th><th>المورد</th><th>الفترة</th>
                <th>الأولي</th><th>تحميلات علينا</th><th>صافي الساعات المستحقة</th>
                <th>الحالة</th><th>طلب الدفع</th><th>اعتراضات المورد</th><th></th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">العقد</th>
                <th class="ems-fn-th" data-fn="1">الوحدة</th>
                <th class="ems-fn-th" data-fn="1">الساعات المتعاقدة</th>
                <th class="ems-fn-th" data-fn="1">الساعات المنفذة</th>
                <th class="ems-fn-th" data-fn="1">العجز</th>
                <th class="ems-fn-th" data-fn="1">التعطل المحمل على المورد</th>
                <th class="ems-fn-th" data-fn="1">ساعات مخصومة بالتسوية</th>
                <th class="ems-fn-th" data-fn="1">سعر الساعة</th>
                <th class="ems-fn-th" data-fn="1">الاستحقاق قبل التسويات</th>
                <th class="ems-fn-th" data-fn="1">تحميلات على المورد</th>
                <th class="ems-fn-th" data-fn="1">جزاءات</th>
                <th class="ems-fn-th" data-fn="1">حوافز</th>
                <th class="ems-fn-th none" data-fn="1">صافي المستحق</th>
                <th class="ems-fn-th none" data-fn="1">أعدها</th>
                <th class="ems-fn-th none" data-fn="1">اعتمدها</th>
                <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
                </tr></thead>
            <tbody>
            <?php if (!$settlements): ?>
                <tr><td colspan="9" class="sup-set-empty-cell">
                    لا تسوية بعد — ابدأ بتوليد واحدة من النموذج أعلاه.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($settlements as $s):
                $net = (float) $s['net_amount'];
                $isRecv = ((string) $s['net_direction'] === 'receivable');
            ?>
                <tr<?php echo (intval($s['id']) === $open) ? ' class="sup-set-row-open"' : ''; ?>>
                    <td><code><?php echo htmlspecialchars((string) $s['settlement_no']); ?></code></td>
                    <td><?php echo htmlspecialchars((string) $s['party_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['period_from'] . ' → ' . $s['period_to']); ?></td>
                    <td><?php echo number_format((float) $s['gross_amount'], 2); ?></td>
                    <td><?php echo number_format((float) $s['charges_amount'], 2); ?></td>
                    <td>
                        <strong class="<?php echo $isRecv ? 'sup-set-net-due' : 'sup-set-net-ok'; ?>">
                            <?php echo number_format($net, 2) . ' ' . htmlspecialchars((string) $s['currency']); ?>
                        </strong>
                        <?php if ($isRecv): ?>
                            <br><small class="sup-set-danger-note">دين على المورد</small>
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
                               title="افتح طلب الدفع ورحلته">
                                <?php echo htmlspecialchars($rq !== null ? $rq : ('#' . intval($s['payment_request_id']))); ?> ↗
                            </a>
                        <?php elseif ($isRecv): ?>
                            <small class="sup-set-muted">لا دفع — دين عليه</small>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if (intval($s['open_objections']) > 0): ?>
                            <span class="badge badge-warning"><?php echo intval($s['open_objections']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="sup-set-nowrap">
                        <a class="btn btn-sm btn-secondary" href="?open=<?php echo intval($s['id']); ?>">البنود</a>
                        <?php if ($can_edit && (string) $s['state'] === 'draft'): ?>
                        <form action="" method="post" class="sup-set-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="sid" value="<?php echo intval($s['id']); ?>">
                            <button class="btn btn-sm btn-primary" type="submit">للمراجعة</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($can_approve && (string) $s['state'] === 'review'): ?>
                        <form action="" method="post" class="sup-set-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="sid" value="<?php echo intval($s['id']); ?>">
                            <button class="btn btn-sm btn-primary" type="submit">إجازة</button>
                        </form>
                        <?php endif; ?>
                        <?php // M-13 · استلامُ الفاتورة ومطابقتُها بالصافي المعتمد
                        if ($can_edit && in_array((string) $s['state'],
                                array('approved', 'payment_requested'), true)): ?>
                        <a class="btn btn-sm btn-secondary" href="?open=<?php echo intval($s['id']); ?>&invoice=1">فاتورة</a>
                        <?php endif; ?>
                        <?php if ($can_approve && (string) $s['state'] === 'paid'): ?>
                        <form action="" method="post" class="sup-set-inline-form"
                              onsubmit="return confirm('الإقفال نهائي — والتصحيح بعده بعكس موثق لا بتعديل. متابعة؟');">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="close">
                            <input type="hidden" name="sid" value="<?php echo intval($s['id']); ?>">
                            <button class="btn btn-sm btn-secondary" type="submit">إقفال</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php
    // ── M-13 · نموذجُ استلام الفاتورة ومطابقتِها ─────────────────────────
    $openRow = null;
    if ($open > 0) {
        try { $openRow = $gate->selectOne('settlements', array('where' => array('id' => $open))); }
        catch (\Throwable $t) { $openRow = null; }
    }
    if ($openRow !== null && !empty($_GET['invoice']) && $can_edit
        && in_array((string) $openRow['state'], array('approved', 'payment_requested'), true)): ?>
    <div class="card"><div class="card-body">
        <h5 class="sup-set-h5"><i class="fas fa-file-invoice-dollar"></i>
            فاتورة المورد للتسوية #<?php echo $open; ?></h5>
        <p class="sup-set-muted">
            الصافي المعتمد: <strong><?php echo number_format((float) $openRow['net_amount'], 2); ?></strong>
            <?php echo htmlspecialchars((string) $openRow['currency']); ?> —
            والفاتورة <strong>مستند ضريبي يطابق به لا مصدر اعتراف</strong>:
            الصافي لا يتغير، و<strong>الاختلاف يفتح فرقا بقرار لا تعديلا صامتا</strong> (ENT-02 §4/§5).
        </p>
        <form action="" method="post">
        <?= csrf_field() ?>
            <input type="hidden" name="action" value="invoice">
            <input type="hidden" name="sid" value="<?php echo $open; ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_473_3a766">رقم الفاتورة <span class="sup-set-req">*</span></label>
                    <input type="text" name="invoice_no" required maxlength="64" id="emsf_473_3a766"></div>
                <div class="form-group"><label for="emsf_474_de030">تاريخ الفاتورة <span class="sup-set-req">*</span></label>
                    <input type="date" name="invoice_date" required id="emsf_474_de030"></div>
                <div class="form-group"><label for="emsf_475_e17b1">مبلغ الفاتورة <span class="sup-set-req">*</span></label>
                    <input type="number" step="0.01" min="0" name="invoice_amount" required id="emsf_475_e17b1"></div>
                <div class="form-group"><label for="emsf_476_5bec7">العملة</label>
                    <input type="text" name="invoice_currency" id="emsf_476_5bec7" maxlength="8"
                           value="<?php echo htmlspecialchars((string) $openRow['currency']); ?>"></div>
                <div class="form-group"><label for="emsf_477_7d79c">سبب الفرق <small>— إلزامي متى اختلفت</small></label>
                    <input type="text" name="diff_reason" maxlength="255" id="emsf_477_7d79c"></div>
                <div class="form-group"><label for="emsf_478_9a61b">مستند الفرق <small>— إلزامي متى اختلفت</small></label>
                    <input type="text" name="diff_doc_ref" maxlength="120" id="emsf_478_9a61b"></div>
            </div>
            <div class="sup-set-actions">
                <button class="btn btn-sm btn-secondary" type="submit">تسجيل الفاتورة ومطابقتها</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>

    <?php if ($openRow !== null && $openRow['invoice_no'] !== null): ?>
    <div class="card"><div class="card-body">
        <h5 class="sup-set-h5"><i class="fas fa-file-invoice"></i> الفاتورة والمطابقة</h5>
        <p>
            رقمها <strong><?php echo htmlspecialchars((string) $openRow['invoice_no']); ?></strong>
            بتاريخ <?php echo htmlspecialchars((string) $openRow['invoice_date']); ?>
            · مبلغها <strong><?php echo number_format((float) $openRow['invoice_amount'], 2); ?></strong>
            · الصافي المعتمد <strong><?php echo number_format((float) $openRow['net_amount'], 2); ?></strong>
            ·
            <?php if (abs((float) $openRow['invoice_diff']) < 0.005): ?>
                <span class="badge badge-success">مطابقة</span>
            <?php else: ?>
                <span class="badge badge-warning">فرق <?php echo number_format((float) $openRow['invoice_diff'], 2); ?></span>
                <br><small>السبب: <?php echo htmlspecialchars((string) $openRow['invoice_diff_reason']); ?>
                    · المستند: <?php echo htmlspecialchars((string) $openRow['invoice_diff_doc_ref']); ?></small>
            <?php endif; ?>
        </p>
    </div></div>
    <?php endif; ?>

    <?php if ($open > 0): ?>
    <div class="card"><div class="card-body">
        <h5 class="sup-set-h5"><i class="fas fa-list-ul"></i> بنود التسوية #<?php echo $open; ?></h5>
        <div class="sup-set-scroll">
        <table class="table table-striped sup-set-table">
            <thead><tr>
                <th>النوع</th><th>البيان</th><th>الأصل</th><th>تاريخ الإنشاء</th>
                <th>المبلغ</th><th>المعادل بعملة الدفاتر</th><th>الاعتراض</th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$lines): ?>
                <tr><td colspan="8" class="sup-set-empty-cell">لا بنود.</td></tr>
            <?php endif; ?>
            <?php foreach ($lines as $l):
                $isCharge = ((string) $l['line_kind'] === 'charge');
            ?>
                <tr<?php echo intval($l['objected']) === 1 ? ' class="sup-set-row-objected"' : ''; ?>>
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
                            <span class="sup-set-danger-note"><?php echo htmlspecialchars((string) $l['objection_note']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="sup-set-nowrap">
                        <?php if ($can_edit && intval($l['objected']) === 0): ?>
                        <form action="" method="post" class="sup-set-inline-form">
        <?= csrf_field() ?>
                            <input type="hidden" name="action" value="object">
                            <input type="hidden" name="sid" value="<?php echo $open; ?>">
                            <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                            <input type="text" name="note" placeholder="سبب الاعتراض" required
                                   class="sup-set-obj-input" aria-label="سبب الاعتراض">
                            <button class="btn btn-sm btn-secondary" type="submit">اعتراض</button>
                        </form>
                        <?php elseif ($can_edit): ?>
                        <form action="" method="post" class="sup-set-inline-form">
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

</body>
</html>

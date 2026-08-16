<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Workforce/payroll_runs.php — مسيّرُ الرواتب: بوابةُ اللقطة (H-09-①)
 * ───────────────────────────────────────────────────────────────────────────
 * الشريحةُ ① **بوابةٌ لا محرّكُ احتساب**: تفتح الدورة وتربط لقطةَ كل عقدٍ
 * مؤهَّل، وتُظهر ثلاثةَ أشياء بوضوح — المحسوبَ · **المنتظرَ للشريحتين ②③**
 * · و**قائمةَ الموانع والمستبعَدين بأسبابها**. فلا رقمَ يُعرض بلا مصدره،
 * ولا نقصَ يُبتلع صفرًا.
 *
 * كلُّ فعلٍ عبر `PayrollRunService` — لا كتابةَ خامًا في الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Payroll/PayrollRunService.php';
require_once __DIR__ . '/../app/Services/Payroll/TimePathService.php';
require_once __DIR__ . '/../app/Services/Payroll/ProductionPathService.php';
require_once __DIR__ . '/../app/Services/Payroll/OffsetService.php';
require_once __DIR__ . '/../app/Services/Payroll/PayrollStateMachine.php';

use App\Services\Payroll\PayrollRunService as PRS;
use App\Services\Payroll\TimePathService as TPS;
use App\Services\Payroll\ProductionPathService as PPS;
use App\Services\Payroll\OffsetService as OFS;
use App\Services\Payroll\PayrollStateMachine as PSM;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Workforce/payroll_runs.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add'])  === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض مسيّر الرواتب ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('payroll runs super') : ems_tenant_db();

$CAT_LABELS = array('all' => 'الكل', 'permanent' => 'دائم', 'project' => 'مشروعي',
                    'operator' => 'مشغّل', 'supplier_worker' => 'عاملُ مورد');
$STATE_LABELS = array('Open' => 'مفتوحة', 'Calculated' => 'محتسَبة', 'Blocked' => 'موقوفة',
                      'Review' => 'مراجعة', 'Approved' => 'معتمَدة', 'Paid' => 'مدفوعة', 'Closed' => 'مقفلة');

$selected = intval($_GET['run_id'] ?? 0);
$redirect = function ($msg, $rid) { ems_gov_redirect("Location: payroll_runs.php?run_id=" . intval($rid)
    . "&msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['pr_action'] ?? '');

    if ($action === 'open_run') {
        if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', 0); }
        $r = PRS::openRun($conn, $gate, $company_id, array(
            'period_from'     => $_POST['period_from'] ?? '',
            'period_to'       => $_POST['period_to'] ?? '',
            'category_filter' => $_POST['category_filter'] ?? 'all',
        ), $uid);
        $redirect($r['ok'] ? 'فُتحت الدورة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'),
                  $r['run_id'] ? $r['run_id'] : 0);
    }

    if ($action === 'bind') {
        $rid2 = intval($_POST['run_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $rid2); }
        $r = PRS::bindSnapshots($conn, $gate, $company_id, $rid2, $uid);
        $redirect($r['ok'] ? ('ربطُ اللقطات: ' . $r['lines'] . ' سطرًا · ' . $r['reason'] . ' ✅')
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $rid2);
    }

    if ($action === 'time_path') {
        $rid2 = intval($_POST['run_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $rid2); }
        $r = TPS::compute($conn, $gate, $company_id, $rid2, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $rid2);
    }

    if ($action === 'production_path') {
        $rid2 = intval($_POST['run_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $rid2); }
        $r = PPS::compute($conn, $gate, $company_id, $rid2, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $rid2);
    }

    if ($action === 'offsets') {
        $rid2 = intval($_POST['run_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $rid2); }
        $r = OFS::computeOffsets($conn, $gate, $company_id, $rid2, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $rid2);
    }

    if ($action === 'transition') {
        $rid2 = intval($_POST['run_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $rid2); }
        $r = PSM::transition($conn, $gate, $company_id, $rid2, strval($_POST['to_state'] ?? ''), $uid,
            array('payment_ref' => $_POST['payment_ref'] ?? ''));
        $redirect($r['ok'] ? ('الدورةُ صارت «' . PSM::labelAr($r['state']) . '» ✅')
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $rid2);
    }

    if ($action === 'time_input') {
        $rid2 = intval($_POST['run_id'] ?? 0);
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $rid2); }
        $r = TPS::recordTimeInput($conn, $gate, $company_id, $rid2, array(
            'person_id' => $_POST['person_id'] ?? 0,
            'kind'      => $_POST['kind'] ?? '',
            'qty'       => $_POST['qty'] ?? 0,
            'doc_ref'   => $_POST['doc_ref'] ?? '',
            'note'      => $_POST['input_note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'سُجّل مدخلُ الزمن بمستنده ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $rid2);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$runs = array();
try {
    $runs = $gate->scopedQuery(array('scope' => array('r' => 'payroll_runs')),
        "SELECT r.* FROM payroll_runs r
          WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
          ORDER BY r.period_from DESC, r.id DESC");
} catch (\Throwable $t) { $runs = array(); }
if ($selected <= 0 && $runs) { $selected = intval($runs[0]['id']); }

$run    = $selected > 0 ? PRS::runOf($gate, $selected) : null;
$lines  = $selected > 0 ? PRS::linesOf($gate, $selected) : array();
$blocks = $selected > 0 ? PRS::blocksOf($gate, $selected) : array();

$computed = 0.0; $pending = 0;
foreach ($lines as $l) {
    if ($l['amount'] !== null) { $computed += (float) $l['amount']; }
    if ((string) $l['calc_state'] === 'pending_slice') { $pending++; }
}

$page_title = 'إيكوبيشن | مسيّر الرواتب';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// M-14 BR-GOV-07: المسيّر رواتبُ الناس — القراءةُ تُسجَّل كما تُسجَّل الكتابة
require_once __DIR__ . '/../includes/sensitive_read_log.php';
ems_log_sensitive_read($conn, 'payroll_run', $selected > 0 ? ('run:' . $selected) : 'screen:list', 'Workforce/payroll_runs.php');

/* ══ INJ-0225 · الأثرُ يقع والقيمةُ تُحجَب ══════════════════════════════════
     نصُّ القبول: «حسابٌ بلا منحةِ الأجور يرى العدّاداتِ **ولا يرى مبلغَ أيِّ
     سطرٍ في جسمِ الاستجابة**؛ ومن يملكها يراها **ويُسجَّل اطّلاعُه**».
     والمقيسُ قبلَه: الشاشةُ **تكتب سطرَ الاطّلاعِ ولا تحجب** — فالأثرُ يقع
     والقيمةُ تعبر لمن لا يملكها. والسجلُّ بلا حجبٍ يوثّق التسريبَ ولا يمنعه.
   ◆ **والحجبُ في الخادمِ لا في المتصفّح**: قيمةٌ مخفيةٌ بـCSS تُقرأ بـ«عرضِ
     المصدر» — فالشرطُ يقول «في جسمِ الاستجابة» ويعني ما يقول.
   ◆ والحاكمُ `VisibilityPolicyService` **مغلقٌ افتراضًا**: من لا منحةَ له
     لا يرى، ومن له منحةٌ يرى ويُسجَّل — وهو ما بُني ولم يُتبنَّ. */
require_once __DIR__ . '/../includes/field_visibility.php';
$__maySeePay = ems_may_see_field($conn, 'payroll.amount',
    $selected > 0 ? ('run:' . $selected) : 'screen:list', 'Workforce/payroll_runs.php');
/** يُرجع المبلغَ لمن يملك، و«محجوب» لمن لا يملك — ولا يطبع الرقمَ إطلاقًا. */
$__money = function ($v, $fmt = true) use ($__maySeePay) {
    if (!$__maySeePay) { return 'محجوب'; }
    if ($v === null) { return 'لم يُحتسب'; }
    return $fmt ? number_format((float) $v, 2) : (string) $v;
};
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مسيّر الرواتب — بوابةُ اللقطة'; $header_icon = 'fa fa-money-check-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleRunForm',
            'icon' => 'fa fa-plus', 'label' => 'دورة جديدة', 'class' => 'add');
    }
    $header_back = array('href' => 'contract_registry.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'سجل العقود');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="alert alert-warning" style="margin-bottom:14px">
        <i class="fa fa-circle-info"></i>
        <strong>الشريحة ① — بوابةُ اللقطة.</strong>
        يُحتسب هنا ما لا يحتاج زمنًا ولا إنتاجًا (مبلغٌ ثابتٌ · نسبةٌ من الأساسي).
        وما عداه يظهر سطرًا بحالة <strong>«ينتظر الشريحة»</strong> بلا مبلغ —
        <em>لا احتسابَ ناقصٌ صامت</em> (ENT-01 §5).
    </div>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="runForm">
        <?= csrf_field() ?>
        <input type="hidden" name="pr_action" value="open_run">
        <div class="card"><div class="card-header"><h5><i class="fa fa-calendar"></i> دورةُ مسيّرٍ جديدة</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group"><label for="emsf_1751_f3df5">من <span style="color:#c00">*</span></label>
                <input type="date" name="period_from" required id="emsf_1751_f3df5"></div>
            <div class="form-group"><label for="emsf_1752_32b51">إلى <span style="color:#c00">*</span></label>
                <input type="date" name="period_to" required id="emsf_1752_32b51"></div>
            <div class="form-group">
                <label for="emsf_1753_3f066">الفئة</label>
                <select name="category_filter" id="emsf_1753_3f066">
                    <?php foreach ($CAT_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:12px"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> فتح</button></div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>الدورة:</strong>
            <select name="run_id" onchange="this.form.submit()" style="min-width:380px">
                <?php foreach ($runs as $r): ?>
                    <option value="<?php echo intval($r['id']); ?>" <?php echo $selected === intval($r['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($r['id']); ?> — <?php echo htmlspecialchars((string)$r['period_from']); ?>
                        → <?php echo htmlspecialchars((string)$r['period_to']); ?>
                        · <?php echo htmlspecialchars($CAT_LABELS[$r['category_filter']] ?? $r['category_filter']); ?>
                        · <?php echo htmlspecialchars($STATE_LABELS[$r['state']] ?? $r['state']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($run !== null && $can_edit
                      && in_array((string)$run['state'], array('Open','Blocked'), true)): ?>
                </form>
                <form method="post" style="display:inline">
        <?= csrf_field() ?>
                    <input type="hidden" name="pr_action" value="bind">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-primary"><i class="fa fa-link"></i> اربط اللقطات</button>
                </form>
            <?php else: ?></form><?php endif; ?>
            <?php if ($run !== null && $can_edit
                      && in_array((string)$run['state'], array('Calculated','Blocked'), true)): ?>
                <form method="post" style="display:inline">
        <?= csrf_field() ?>
                    <input type="hidden" name="pr_action" value="time_path">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-primary"><i class="fa fa-clock"></i> المسارُ الزمني</button>
                </form>
                <form method="post" style="display:inline">
        <?= csrf_field() ?>
                    <input type="hidden" name="pr_action" value="production_path">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-primary"><i class="fa fa-cubes"></i> المسارُ الإنتاجي</button>
                </form>
                <form method="post" style="display:inline">
        <?= csrf_field() ?>
                    <input type="hidden" name="pr_action" value="offsets">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-primary"><i class="fa fa-scale-balanced"></i> المقاصّة</button>
                </form>
            <?php endif; ?>

        <?php if ($run !== null && $can_edit):
            $red = PSM::redRows($gate, $selected);
            $allowed = PSM::allowedFrom((string) $run['state']);
        ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <strong>الانتقال:</strong>
            <?php foreach ($allowed as $to): ?>
                <form method="post" style="display:inline">
        <?= csrf_field() ?>
                    <input type="hidden" name="pr_action" value="transition">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <input type="hidden" name="to_state" value="<?php echo htmlspecialchars($to); ?>">
                    <?php if ($to === PSM::PAID): ?>
                        <input type="text" name="payment_ref" placeholder="مرجع الصرف (إلزامي)"
                               required style="max-width:200px" aria-label="مرجع الصرف (إلزامي)">
                    <?php endif; ?>
                    <button type="submit" class="btn-primary">→ <?php echo htmlspecialchars(PSM::labelAr($to)); ?></button>
                </form>
            <?php endforeach; ?>
            <?php if (!$allowed): ?><span style="color:#666">حالةٌ نهائية — التصحيحُ بحدثٍ عاكسٍ لا بتعديل</span><?php endif; ?>
        </div>
        <?php if (!$red['ok']): ?>
            <div class="alert alert-danger" style="margin-top:10px">
                <i class="fa fa-circle-exclamation"></i>
                <strong>صفوفٌ حمراءُ تمنع الاعتماد:</strong>
                <?php echo htmlspecialchars(implode(' · ', $red['reasons'])); ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($run !== null): ?>
        <div style="margin-top:14px;line-height:1.9">
            <span class="badge <?php echo (string)$run['state'] === 'Blocked' ? 'badge-danger' : 'badge-info'; ?>">
                <?php echo htmlspecialchars($STATE_LABELS[$run['state']] ?? $run['state']); ?></span>
            · أشخاص: <strong><?php echo intval($run['persons_count']); ?></strong>
            · أسطر: <strong><?php echo intval($run['lines_count']); ?></strong>
            · موانع: <strong><?php echo intval($run['blocked_count']); ?></strong>
            · <span title="مجموعُ ما اكتمل احتسابُه في هذه الشريحة">محتسَبٌ الآن:
                <strong><?php echo number_format($computed, 2); ?></strong></span>
            <?php if ($pending > 0): ?>
                · <span class="badge badge-warning"><?php echo $pending; ?> سطرًا ينتظر الشريحتين ②③</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div></div>

    <?php if ($blocks): ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-ban"></i> قائمةُ الموانع والمستبعَدين (<?php echo count($blocks); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>النوع</th><th>العقد</th><th>الشخص</th><th>الرمز</th><th>السبب</th></tr></thead>
            <tbody>
            <?php foreach ($blocks as $b): ?>
                <tr>
                    <td><?php echo (string)$b['kind'] === 'excluded'
                        ? "<span class='badge badge-secondary'>مستبعَد</span>"
                        : "<span class='badge badge-danger'>مانع</span>"; ?></td>
                    <td>#<?php echo intval($b['contract_id']); ?></td>
                    <td><?php echo $b['person_id'] ? ('#' . intval($b['person_id'])) : '—'; ?></td>
                    <td><small><?php echo htmlspecialchars((string)$b['block_code']); ?>
                        · <?php echo intval($b['block_http']); ?></small></td>
                    <td><?php echo htmlspecialchars((string)$b['reason']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>

    <?php if ($run !== null && $can_edit): ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-keyboard"></i> مدخلاتُ الزمن — <strong>بمستندها إلزامًا</strong></h5></div>
    <div class="card-body">
        <p style="color:#666;margin-bottom:10px">
            «ولا خصمَ بلا مستند» (ENT-01 §4) — والزيادةُ مثلُه: ساعةُ إضافيٍّ بلا مرجعٍ
            رقمٌ يزيد أجرًا بلا سند. ولا مصدرَ آليًّا لهذه المدخلات في النظام اليوم.
        </p>
        <form method="post" class="ems-form">
        <?= csrf_field() ?>
            <input type="hidden" name="pr_action" value="time_input">
            <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_1754_c6a9e">رقم الشخص <span style="color:#c00">*</span></label>
                    <input type="number" name="person_id" min="1" required id="emsf_1754_c6a9e"></div>
                <div class="form-group">
                    <label for="emsf_1755_df58c">النوع <span style="color:#c00">*</span></label>
                    <select name="kind" required id="emsf_1755_df58c">
                        <option value="overtime_hours">ساعاتُ إضافي</option>
                        <option value="night_shifts">ورديّاتٌ ليلية</option>
                        <option value="unpaid_days">أيامٌ غيرُ مدفوعة</option>
                    </select>
                </div>
                <div class="form-group"><label for="emsf_1756_dd6a7">الكمية <span style="color:#c00">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="qty" required id="emsf_1756_dd6a7"></div>
                <div class="form-group"><label for="emsf_1757_ed8d0">مرجع المستند <span style="color:#c00">*</span></label>
                    <input type="text" name="doc_ref" required maxlength="120"
                           placeholder="إذنُ عملٍ إضافي 2047/114" id="emsf_1757_ed8d0"></div>
                <div class="form-group"><label for="emsf_1758_457e2">ملاحظة</label><input type="text" name="input_note" maxlength="255" id="emsf_1758_457e2"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> تسجيل</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <?php $register = $selected > 0 ? PSM::register($gate, $selected) : array(); if ($register): ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-table-list"></i> سجلُّ المراجعة — صفٌّ لكل شخص</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الشخص</th><th>أجرٌ وإنتاج</th><th>حوافز</th><th>إضافي</th>
                <th>خصم الغياب</th><th>إجمالي الخصومات</th><th>صافي المستحق</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم المسيّر</th>
                <th class="ems-fn-th" data-fn="1">الشهر</th>
                <th class="ems-fn-th" data-fn="1">كود الموظف</th>
                <th class="ems-fn-th" data-fn="1">الاسم</th>
                <th class="ems-fn-th" data-fn="1">الأجر الأساسي</th>
                <th class="ems-fn-th" data-fn="1">البدلات</th>
                <th class="ems-fn-th" data-fn="1">الحافز</th>
                <th class="ems-fn-th" data-fn="1">إجمالي الاستحقاق</th>
                <th class="ems-fn-th" data-fn="1">خصم التأخير</th>
                <th class="ems-fn-th" data-fn="1">جزاءات</th>
                <th class="ems-fn-th" data-fn="1">قسط سلفة</th>
                <th class="ems-fn-th" data-fn="1">تأمينات</th>
                <th class="ems-fn-th" data-fn="1">الحساب البنكي</th>
                <th class="ems-fn-th" data-fn="1">أعدّه</th>
                <th class="ems-fn-th" data-fn="1">راجعه</th>
                <th class="ems-fn-th none" data-fn="1">اعتمده</th>
                <th class="ems-fn-th none" data-fn="1">رقم أمر الدفع</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php $tNet = 0.0; foreach ($register as $rr): $tNet += (float) $rr['net']; ?>
                <tr <?php echo intval($rr['red_rows']) > 0 ? 'style="background:#ffecec"' : ''; ?>>
                    <td>#<?php echo intval($rr['person_id']); ?>
                        <?php if (intval($rr['red_rows']) > 0): ?>
                            <span class="badge badge-danger"
                                  title="صفٌّ بلا احتسابٍ تامٍّ — لا يُعتمد (ENT-01 §7)">أحمر</span>
                        <?php endif; ?>
                        <a href="?run_id=<?php echo $selected; ?>&slip=<?php echo intval($rr['person_id']); ?>"
                           title="كشفُ الفرد بطبقاته"><i class="fas fa-file-invoice"></i></a></td>
                    <td><?php echo htmlspecialchars($__money($rr['pay'])); ?></td>
                    <td><?php echo htmlspecialchars($__money($rr['incentive'])); ?></td>
                    <td><?php echo htmlspecialchars($__money($rr['overtime'])); ?></td>
                    <td><?php echo htmlspecialchars($__money($rr['absence'])); ?></td>
                    <td><?php echo htmlspecialchars($__money($rr['deductions'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($__money($rr['net'])); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <th colspan="6">المجموع (تذييلٌ ثابت — §7)</th>
                <th><?php echo number_format($tNet, 2); ?></th>
            </tr></tfoot>
        </table>
    </div></div></div>
    <?php endif; ?>

    <?php
    $slipPerson = intval($_GET['slip'] ?? 0);
    if ($slipPerson > 0 && $selected > 0):
        $slip = PSM::payslip($gate, $selected, $slipPerson);
    ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-file-invoice"></i> كشفُ الفرد #<?php echo $slipPerson; ?> — بطبقاته</h5></div>
    <div class="card-body">
        <?php foreach ($slip['layers'] as $layerName => $layer): ?>
            <h6 style="margin-top:12px"><strong><?php echo htmlspecialchars($layerName); ?></strong>
                — <?php echo number_format((float)$layer['total'], 2); ?></h6>
            <ul style="line-height:1.8">
            <?php foreach ($layer['rows'] as $row): ?>
                <li>
                    <?php echo htmlspecialchars((string)($row['component_ref'] ?? ($row['source_type'] ?? ''))); ?>
                    <?php if (isset($row['amount'])): ?>
                        — <strong><?php echo htmlspecialchars($__money($row['amount'])); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($row['doc_ref'])): ?>
                        · <small>سند: <?php echo htmlspecialchars((string)$row['doc_ref']); ?></small>
                    <?php endif; ?>
                    <?php if (!empty($row['note'])): ?>
                        <br><small style="color:#666"><?php echo htmlspecialchars((string)$row['note']); ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <hr>
        <p>الإجمالي <strong><?php echo htmlspecialchars($__money($slip['gross'])); ?></strong>
           · الخصومات <strong><?php echo number_format($slip['deductions'], 2); ?></strong>
           · <span style="font-size:1.2em">الصافي <strong><?php echo htmlspecialchars($__money($slip['net'])); ?></strong></span></p>
        <p style="color:#666">اللقطاتُ المستنَدُ إليها:
            <?php foreach ($slip['snapshot_ids'] as $sid): ?>
                <span class="badge badge-secondary">لقطة #<?php echo intval($sid); ?></span>
            <?php endforeach; ?>
            — «تعديلُ العقد لاحقًا لا يمسّ ما احتُسب».</p>
    </div></div>
    <?php endif; ?>

    <?php
    $deductions = $selected > 0 ? OFS::deductionsOf($gate, $selected) : array();
    if ($deductions):
        $dedTotal = 0.0;
        foreach ($deductions as $d) { $dedTotal += (float) $d['amount']; }
    ?>
    <div class="card"><div class="card-header">
        <h5><i class="fa fa-scale-balanced"></i> المقاصّة — خصومٌ بمراجعها
            (<?php echo count($deductions); ?> · <?php echo number_format($dedTotal, 2); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الشخص</th><th>المصدر</th><th>المستحق</th><th>المخصوم</th>
                <th>المستند</th><th>الحالة</th><th>البيان</th>
            </tr></thead>
            <tbody>
            <?php foreach ($deductions as $d): ?>
                <tr>
                    <td>#<?php echo intval($d['person_id']); ?></td>
                    <td><?php echo htmlspecialchars((string)$d['source_type']); ?>
                        #<?php echo intval($d['source_id']); ?></td>
                    <td><?php echo htmlspecialchars($d['requested_amount'] !== null ? $__money($d['requested_amount'], false) : '—'); ?></td>
                    <td><strong><?php echo htmlspecialchars($__money($d['amount'], false)); ?></strong></td>
                    <td><small><?php echo htmlspecialchars((string)$d['doc_ref']); ?></small></td>
                    <td><?php echo intval($d['rescheduled']) === 1
                        ? "<span class='badge badge-warning'>مُرحَّلٌ بحدِّ الحماية</span>"
                        : "<span class='badge badge-success'>كاملًا</span>"; ?></td>
                    <td><small><?php echo htmlspecialchars((string)$d['note']); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> أسطرُ الاحتساب بلقطاتها</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الشخص</th><th>النوع</th><th>المسار</th><th>المكوّن</th><th>الطريقة</th>
                <th>المعدل</th><th>الأيام</th><th>الجهة</th><th>٪</th><th>المبلغ</th><th>الحالة</th><th>اللقطة</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td>#<?php echo intval($l['person_id']); ?></td>
                    <td><?php
                        $k = (string) $l['line_kind'];
                        $kindLabels = array(
                            'absence_deduction' => "<span class='badge badge-danger'>خصمُ غياب</span>",
                            'overtime'   => "<span class='badge badge-info'>إضافي</span>",
                            'production' => "<span class='badge badge-success'>إنتاج</span>",
                            'incentive'  => "<span class='badge badge-success'>حافز</span>",
                        );
                        echo isset($kindLabels[$k]) ? $kindLabels[$k] : 'مكوّن';
                    ?></td>
                    <td><?php echo (string)$l['path'] === 'project' ? 'مشروعي' : 'مؤسسي'; ?></td>
                    <td><?php echo htmlspecialchars((string)($l['component_type'] ?? $l['component_ref'])); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($l['calc_method'] ?? '')); ?></small></td>
                    <td><?php echo $l['rate'] !== null ? htmlspecialchars((string)$l['rate']) : '—'; ?></td>
                    <td><?php echo $l['entitled_days'] !== null
                        ? (htmlspecialchars((string)$l['entitled_days']) . '/' . htmlspecialchars((string)$l['period_days']))
                        : '—'; ?></td>
                    <td><?php echo htmlspecialchars((string)($l['bearer_type'] ?? '—')); ?>
                        <?php echo $l['bearer_id'] ? ('#' . intval($l['bearer_id'])) : ''; ?></td>
                    <td><?php echo $l['percent'] !== null ? htmlspecialchars((string)$l['percent']) : '—'; ?></td>
                    <td><?php if ($l['amount'] !== null): ?>
                            <strong><?php echo htmlspecialchars($__money($l['amount'], false)); ?></strong>
                        <?php else: ?>
                            <span class="badge badge-warning" title="<?php echo htmlspecialchars((string)$l['note']); ?>">
                                لم يُحتسب بعد</span>
                        <?php endif; ?></td>
                    <td><?php echo (string)$l['calc_state'] === 'computed'
                        ? "<span class='badge badge-success'>محسوب</span>"
                        : "<span class='badge badge-warning'>ينتظر الشريحة</span>"; ?></td>
                    <td><small title="كلُّ سطرٍ يحمل لقطتَه — تعديلُ العقد لاحقًا لا يمسّه">
                        لقطة #<?php echo intval($l['snapshot_id']); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var b = document.getElementById('toggleRunForm'), f = document.getElementById('runForm');
        if (b && f) { b.addEventListener('click', function () { f.classList.toggle('allforms-visible'); }); }
    });
})();
</script>
</body>
</html>

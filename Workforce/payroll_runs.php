<?php
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
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Payroll/PayrollRunService.php';
require_once __DIR__ . '/../app/Services/Payroll/TimePathService.php';
require_once __DIR__ . '/../app/Services/Payroll/ProductionPathService.php';

use App\Services\Payroll\PayrollRunService as PRS;
use App\Services\Payroll\TimePathService as TPS;
use App\Services\Payroll\ProductionPathService as PPS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
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
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+مسيّر+الرواتب+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('payroll runs super') : ems_tenant_db();

$CAT_LABELS = array('all' => 'الكل', 'permanent' => 'دائم', 'project' => 'مشروعي',
                    'operator' => 'مشغّل', 'supplier_worker' => 'عاملُ مورد');
$STATE_LABELS = array('Open' => 'مفتوحة', 'Calculated' => 'محتسَبة', 'Blocked' => 'موقوفة',
                      'Review' => 'مراجعة', 'Approved' => 'معتمَدة', 'Paid' => 'مدفوعة', 'Closed' => 'مقفلة');

$selected = intval($_GET['run_id'] ?? 0);
$redirect = function ($msg, $rid) { header("Location: payroll_runs.php?run_id=" . intval($rid)
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
include '../inheader.php';
include '../insidebar.php';
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
        <input type="hidden" name="pr_action" value="open_run">
        <div class="card"><div class="card-header"><h5><i class="fa fa-calendar"></i> دورةُ مسيّرٍ جديدة</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group"><label>من <span style="color:#c00">*</span></label>
                <input type="date" name="period_from" required></div>
            <div class="form-group"><label>إلى <span style="color:#c00">*</span></label>
                <input type="date" name="period_to" required></div>
            <div class="form-group">
                <label>الفئة</label>
                <select name="category_filter">
                    <?php foreach ($CAT_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> فتح</button></div>
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
                    <input type="hidden" name="pr_action" value="bind">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-save"><i class="fa fa-link"></i> اربط اللقطات</button>
                </form>
            <?php else: ?></form><?php endif; ?>
            <?php if ($run !== null && $can_edit
                      && in_array((string)$run['state'], array('Calculated','Blocked'), true)): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="pr_action" value="time_path">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-save"><i class="fa fa-clock"></i> المسارُ الزمني</button>
                </form>
                <form method="post" style="display:inline">
                    <input type="hidden" name="pr_action" value="production_path">
                    <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
                    <button type="submit" class="btn-save"><i class="fa fa-cubes"></i> المسارُ الإنتاجي</button>
                </form>
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
            <input type="hidden" name="pr_action" value="time_input">
            <input type="hidden" name="run_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group"><label>رقم الشخص <span style="color:#c00">*</span></label>
                    <input type="number" name="person_id" min="1" required></div>
                <div class="form-group">
                    <label>النوع <span style="color:#c00">*</span></label>
                    <select name="kind" required>
                        <option value="overtime_hours">ساعاتُ إضافي</option>
                        <option value="night_shifts">ورديّاتٌ ليلية</option>
                        <option value="unpaid_days">أيامٌ غيرُ مدفوعة</option>
                    </select>
                </div>
                <div class="form-group"><label>الكمية <span style="color:#c00">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="qty" required></div>
                <div class="form-group"><label>مرجع المستند <span style="color:#c00">*</span></label>
                    <input type="text" name="doc_ref" required maxlength="120"
                           placeholder="إذنُ عملٍ إضافي 2047/114"></div>
                <div class="form-group"><label>ملاحظة</label><input type="text" name="input_note" maxlength="255"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> تسجيل</button></div>
        </form>
    </div></div>
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
                            <strong><?php echo htmlspecialchars((string)$l['amount']); ?></strong>
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

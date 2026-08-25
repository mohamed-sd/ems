<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_payment_schedule.php — خطةُ دفع العقد (P-05)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §3.5: «خطةُ الدفع بأنماطها الثمانية + **أنواعُ المقدم الأربعة** —
 * **توليدٌ آليٌّ من الرأس والجدول**».
 * والشاشةُ تُري **الفصلَ نفسَه**: كلُّ سطرِ مقدمٍ بنوعه ومعالجته، والمقبوضُ
 * مفروزًا **التزامًا وإيرادًا لا يُجمعان** — والمتأخرُ معلَنٌ لا مكتشَف.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractPaymentScheduleService.php';

use App\Services\Contract\ContractPaymentScheduleService as CPS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_payment_schedule.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) { $can_view = $can_add = $can_edit = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add']) === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض خطة الدفع ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('payment schedule super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_payment_schedule.php', ($c > 0 ? ('&contract=' . $c) : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['ps_action'] ?? '') !== '') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['ps_action']);
    $cid = intval($_POST['contract_id'] ?? 0);

    if ($act === 'generate') {
        $opt = array(
            'pattern'  => strval($_POST['pattern'] ?? 'monthly_claim'),
            'due_days' => intval($_POST['due_days'] ?? 30),
        );
        if (strval($_POST['adv_type'] ?? '') !== '') {
            $opt['advance'] = array(
                'type'  => strval($_POST['adv_type']),
                'basis' => strval($_POST['adv_basis'] ?? 'percent'),
                'percent' => strval($_POST['adv_percent'] ?? ''),
                'amount'  => strval($_POST['adv_amount'] ?? ''),
                'due_date' => strval($_POST['adv_due'] ?? ''),
                'treatment' => strval($_POST['adv_treatment'] ?? ''),
                'treatment_basis' => strval($_POST['adv_basis_text'] ?? ''),
            );
        }
        $r = CPS::generate($conn, $gate, $company_id, $cid, $opt, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'add_row') {
        $a = array(
            'payment_kind' => strval($_POST['kind'] ?? ''),
            'pattern' => strval($_POST['row_pattern'] ?? 'milestone_payments'),
            'amount_expected' => strval($_POST['amount'] ?? ''),
            'currency' => strval($_POST['currency'] ?? ''),
            'due_date' => strval($_POST['due_date'] ?? ''),
            'due_condition' => strval($_POST['due_condition'] ?? ''),
            'note' => strval($_POST['note'] ?? ''),
        );
        if (strval($_POST['kind'] ?? '') === 'advance') {
            $a['advance'] = array(
                'type' => strval($_POST['row_adv_type'] ?? ''),
                'treatment' => strval($_POST['row_adv_treatment'] ?? ''),
                'treatment_basis' => strval($_POST['row_adv_basis'] ?? ''),
            );
        }
        $r = CPS::addRow($conn, $gate, $company_id, $cid, $a, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'receive') {
        $r = CPS::markReceived($conn, $gate, $company_id, intval($_POST['row_id'] ?? 0),
                               strval($_POST['amount'] ?? ''), strval($_POST['received_date'] ?? ''),
                               strval($_POST['doc_ref'] ?? ''), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'new_version') {
        $r = CPS::newVersion($conn, $gate, $company_id, $cid, strval($_POST['effective_from'] ?? ''),
                             intval($_POST['amendment_id'] ?? 0), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'refresh') {
        $n = CPS::refreshStates($gate, $cid);
        $redirect('حدثت حالات ' . $n . ' سطرا — والمتأخر يعلن ✅', $cid);
    }
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.first_party, c.second_party, c.actual_start, c.actual_end,
                c.contract_status, c.retention_pct, c.price_currency_contract
           FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC LIMIT 200");
} catch (\Throwable $t) { $contracts = array(); }

$rows = $CID > 0 ? CPS::liveRows($gate, $CID) : array();
$all  = $CID > 0 ? CPS::allRows($gate, $CID) : array();
$vers = $CID > 0 ? CPS::versionsOf($gate, $CID) : array();
$sum  = $CID > 0 ? CPS::summary($gate, $CID) : array();
$head = null;
foreach ($contracts as $c) { if ((int) $c['id'] === $CID) { $head = $c; } }

$PATTERN_AR = CPS::PATTERN_AR;
$KIND_AR    = CPS::KIND_AR;
$ADV_AR     = CPS::ADVANCE_AR;
$STATE_AR   = CPS::STATE_AR;

$page_title = 'إيكوبيشن | خطة دفع العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'payment';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'خطة دفع العقد'; $header_icon = 'fa fa-money-check-dollar';
    $header_actions = array();
    $header_back = array('href' => 'contract_monthly_plan.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'الجدول الشهري');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا خطة دفع نافذة على هذا العقد بعد',
                           'اختر عقدا من جدول العقود ثم ولد الخطة من الرأس والجدول الشهري');
    ?>

    <style>
    .ps-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0}
    .ps-table{width:100%}
    .ps-wrap{white-space:normal}
    .ps-badges{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
    .ps-badge-pad{padding:6px 12px}
    .ps-gen-form{margin-bottom:16px}
    .ps-gen-title{margin:0 0 10px}
    .ps-grid-10{display:flex;gap:10px;flex-wrap:wrap}
    .ps-grid-8{display:flex;gap:8px;flex-wrap:wrap}
    .ps-grid-16{display:flex;gap:16px;flex-wrap:wrap;margin-top:14px}
    .ps-row-overdue{background:var(--c-fef2f2, #fef2f2)}
    .ps-inline-form{display:flex;gap:4px}
    .ps-form-main{flex:1;min-width:340px}
    .ps-form-end{align-self:flex-end}
    .ps-w80{width:80px}
    .ps-w100{width:100px}
    .ps-w110{width:110px}
    .ps-w120{width:120px}
    .ps-w130{width:130px}
    .ps-w140{width:140px}
    .ps-mw220{min-width:220px}
    .ps-mw240{min-width:240px}
    .ps-mw280{min-width:280px}
    </style>

    <div class="card"><div class="card-body">
        <p class="ps-note">
            <i class="fas fa-circle-info"></i>
            الخطة تقول <strong>ما استحق ومتى</strong> — لا ما قبض ولا ما فوتر.
            و<strong>أنواع المقدم الأربعة لا تخلط</strong>: المستهلك <strong>دين يستهلك من
            المستخلصات</strong>، ورسوم الحجز ودفعة المعلم <strong>إيراد</strong>،
            ورسوم التعبئة <strong>بحسب نص العقد فتعلن ولا تفترض</strong> —
            <strong>والخلط بينها يقلب التزاما إلى إيراد أو العكس</strong>.
            و<strong>Σ الخطة ليست قيمة العقد</strong>: المقدم يستهلك من المستخلصات فلا يجمع معها.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable ps-table" data-no-dt="1">
            <thead><tr><th>#</th><th>الطرفان</th><th>المدة</th><th>الحال</th><th>احتجاز%</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): ?>
                <tr><td>#<?php echo intval($c['id']); ?></td>
                    <td class="ps-wrap"><?php echo htmlspecialchars((string)$c['first_party']
                        . ' ← ' . (string)$c['second_party']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['actual_start'] . ' → ' . (string)$c['actual_end']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['contract_status']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['retention_pct']); ?></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-money-check-dollar"></i> الخطة</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><tr><td colspan="6"><em>لا عقود</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($head): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-money-check-dollar"></i>
        خطة العقد #<?php echo $CID; ?> — <?php echo htmlspecialchars((string)$head['second_party']); ?></h5></div>
    <div class="card-body">
        <?php if ($rows): ?>
        <div class="ps-badges">
            <span class="badge badge-secondary ps-badge-pad">النسخة
                <?php echo intval($sum['version']); ?></span>
            <span class="badge badge-info ps-badge-pad">متوقع
                <?php echo $sum['expected'] . ' ' . htmlspecialchars((string)$sum['currency']); ?></span>
            <span class="badge badge-success ps-badge-pad">مستلم <?php echo $sum['received']; ?></span>
            <span class="badge badge-secondary ps-badge-pad">متبق <?php echo $sum['remaining']; ?></span>
            <?php if ($sum['overdue_rows'] > 0): ?>
                <span class="badge badge-warning ps-badge-pad">
                    متأخر <?php echo $sum['overdue']; ?> في <?php echo $sum['overdue_rows']; ?> سطرا</span>
            <?php endif; ?>
            <span class="badge badge-secondary ps-badge-pad">
                مقبوض التزاما <?php echo $sum['liability']; ?> ·
                إيرادا <?php echo $sum['revenue']; ?> — <strong>لا يجمعان</strong></span>
        </div>
        <?php endif; ?>

        <?php if (!$rows && $can_edit): ?>
        <form method="post" class="ems-form ps-gen-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="ps_action" value="generate">
            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
            <h6 class="ps-gen-title"><i class="fa fa-wand-magic-sparkles"></i>
                توليد آلي من الرأس والجدول الشهري</h6>
            <div class="ps-grid-10">
                <div class="form-group"><label for="emsf_59_524ba">النمط</label>
                    <select name="pattern" id="emsf_59_524ba">
                        <?php foreach ($PATTERN_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label for="emsf_60_9d874">مهلة السداد (يوما)</label>
                    <input type="number" name="due_days" value="30" min="0" class="ps-w110" id="emsf_60_9d874"></div>
                <div class="form-group"><label for="emsf_61_5e04e">نوع المقدم (اختياري)</label>
                    <select name="adv_type" id="emsf_61_5e04e">
                        <option value="">— بلا مقدم —</option>
                        <?php foreach ($ADV_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label for="emsf_62_d9bbb">نسبته %</label>
                    <input type="number" step="0.001" name="adv_percent" class="ps-w100" id="emsf_62_d9bbb"></div>
                <div class="form-group"><label for="emsf_63_7a62a">تاريخ استحقاقه</label>
                    <input type="date" name="adv_due" id="emsf_63_7a62a"></div>
                <div class="form-group"><label for="emsf_64_f5a33">معالجته (للتعبئة وحدها)</label>
                    <select name="adv_treatment" id="emsf_64_f5a33">
                        <option value="">— محكومة بالنوع —</option>
                        <option value="liability">التزام (دين)</option>
                        <option value="revenue">إيراد</option>
                    </select></div>
                <div class="form-group ps-mw280"><label for="emsf_65_00dd6">نص العقد الحاكم (للتعبئة)</label>
                    <input type="text" name="adv_basis_text" maxlength="255"
                           placeholder="البند 7-3: رسوم التعبئة غير مستردة…" id="emsf_65_00dd6"></div>
            </div>
            <button type="submit" class="btn-primary"><i class="fa fa-wand-magic-sparkles"></i> ولد الخطة</button>
        </form>
        <?php endif; ?>

        <?php if ($rows): ?>
        <div class="table-container">
        <table class="alltables display nowrap no-datatable ps-table" data-no-dt="1">
            <thead><tr><th>#</th><th>النوع</th><th>المقدم ومعالجته</th><th>الأساس</th><th>المتوقع</th>
                <th>المستلم</th><th>المتبقي</th><th>الاستحقاق</th><th>الشهر</th><th>الحال</th>
                <th>مرجع التحصيل</th><th></th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $st = (string) $r['state']; ?>
                <tr<?php echo $st === 'overdue' ? ' class="ps-row-overdue"' : ''; ?>>
                    <td><?php echo intval($r['seq']); ?></td>
                    <td><?php echo htmlspecialchars($KIND_AR[(string)$r['payment_kind']] ?? (string)$r['payment_kind']); ?></td>
                    <td class="ps-wrap"><?php
                        if ($r['advance_type'] !== null) {
                            echo htmlspecialchars($ADV_AR[(string)$r['advance_type']] ?? (string)$r['advance_type']);
                            echo ' <span class="badge ' . ((string)$r['treatment'] === 'liability'
                                ? 'badge-warning">التزام' : 'badge-success">إيراد') . '</span>';
                            if ($r['treatment_basis'] !== null) {
                                echo '<br><small>' . htmlspecialchars((string)$r['treatment_basis']) . '</small>';
                            }
                        } else { echo '—'; }
                    ?></td>
                    <td><?php echo $r['percent_value'] !== null
                        ? (htmlspecialchars((string)$r['percent_value']) . '%') : 'مبلغ'; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['amount_expected']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['received_amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['remaining_amount']); ?></td>
                    <td class="ps-wrap"><?php echo $r['due_date'] !== null
                        ? htmlspecialchars((string)$r['due_date'])
                        : ('<em>' . htmlspecialchars((string)$r['due_condition']) . '</em>'); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['period_month'] ?? '—')); ?></td>
                    <td><span class="badge <?php echo $st === 'completed' ? 'badge-success'
                        : ($st === 'overdue' ? 'badge-warning' : 'badge-secondary'); ?>">
                        <?php echo htmlspecialchars($STATE_AR[$st] ?? $st); ?></span></td>
                    <td><?php echo htmlspecialchars((string)($r['collection_ref'] ?? '—')); ?></td>
                    <td><?php if ($can_edit && (float) $r['remaining_amount'] > 0.004): ?>
                        <form method="post" class="ps-inline-form">
        <?php echo csrf_field(); ?>
                            <input type="hidden" name="ps_action" value="receive">
                            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                            <input type="hidden" name="row_id" value="<?php echo intval($r['id']); ?>">
                            <input type="number" step="0.01" name="amount" class="ps-w100" placeholder="المبلغ المقبوض" required aria-label="المبلغ المقبوض">
                            <input type="date" name="received_date" class="ps-w140" required aria-label="تاريخ القبض">
                            <input type="text" name="doc_ref" class="ps-w130" placeholder="سند القبض" required aria-label="سند القبض">
                            <button type="submit" class="action-btn"><i class="fa fa-hand-holding-dollar"></i> اقبض</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_edit): ?>
        <div class="ps-grid-16">
            <form method="post" class="ems-form ps-form-main">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="ps_action" value="add_row">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <h6><i class="fa fa-plus"></i> سطرٌ يدويّ — <strong>للمعالم والدفعات الخاصة</strong></h6>
                <div class="ps-grid-8">
                    <div class="form-group"><label for="emsf_66_2cc1e">النوع</label>
                        <select name="kind" id="emsf_66_2cc1e">
                            <option value="milestone">معلَم</option>
                            <option value="final">ختامية</option>
                            <option value="single">دفعةٌ واحدة</option>
                            <option value="advance">مقدَّم</option>
                        </select></div>
                    <div class="form-group"><label for="emsf_67_a326d">المبلغ</label>
                        <input type="number" step="0.01" name="amount" class="ps-w120" id="emsf_67_a326d"></div>
                    <div class="form-group"><label for="emsf_68_1dc6e">العملة</label>
                        <input type="text" name="currency" maxlength="8" class="ps-w80" id="emsf_68_1dc6e"
                               value="<?php echo htmlspecialchars((string)$rows[0]['currency']); ?>"></div>
                    <div class="form-group"><label for="emsf_69_10456">تاريخ الاستحقاق</label>
                        <input type="date" name="due_date" id="emsf_69_10456"></div>
                    <div class="form-group ps-mw220"><label for="emsf_70_a103a">أو شرطه</label>
                        <input type="text" name="due_condition" maxlength="200" id="emsf_70_a103a"></div>
                    <div class="form-group"><label for="emsf_71_e8e8a">نوع المقدم (إن كان مقدما)</label>
                        <select name="row_adv_type" id="emsf_71_e8e8a">
                            <option value="">—</option>
                            <?php foreach ($ADV_AR as $k => $v): ?>
                                <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?></select></div>
                    <div class="form-group"><label for="emsf_72_f41b4">معالجته (للتعبئة)</label>
                        <select name="row_adv_treatment" id="emsf_72_f41b4">
                            <option value="">— محكومة بالنوع —</option>
                            <option value="liability">التزام</option>
                            <option value="revenue">إيراد</option>
                        </select></div>
                    <div class="form-group ps-mw240"><label for="emsf_73_ddeb5">نص العقد الحاكم</label>
                        <input type="text" name="row_adv_basis" maxlength="255" id="emsf_73_ddeb5"></div>
                </div>
                <button type="submit" class="btn-primary"><i class="fa fa-plus"></i> أضف السطر</button>
            </form>

            <form method="post" class="ems-form ps-mw280">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="ps_action" value="new_version">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <h6><i class="fa fa-code-branch"></i> نسخة جديدة بملحق</h6>
                <div class="form-group"><label for="emsf_74_2ae97">سريانها</label>
                    <input type="date" name="effective_from" required id="emsf_74_2ae97"></div>
                <div class="form-group"><label for="emsf_75_d54e3">رقم الملحق (اختياري)</label>
                    <input type="number" name="amendment_id" class="ps-w120" id="emsf_75_d54e3"></div>
                <button type="submit" class="btn-primary"><i class="fa fa-code-branch"></i>
                    افتح نسخة — <strong>والقديمة محفوظة</strong></button>
            </form>

            <form method="post" class="ps-form-end">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="ps_action" value="refresh">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-rotate"></i> حدث الحالات</button>
            </form>
        </div>
        <?php endif; ?>
        <?php elseif ($head): ?>
            <p><em>لا خطة دفع نافذة لهذا العقد — ولدها من الرأس والجدول الشهري.</em></p>
        <?php endif; ?>
    </div></div>

    <?php if ($vers): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        النسخ — <strong>والقديمة محفوظة</strong></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable ps-table" data-no-dt="1">
            <thead><tr><th>النسخة</th><th>من</th><th>إلى</th><th>الأسطر</th><th>المتوقع</th></tr></thead>
            <tbody>
            <?php foreach ($vers as $v): ?>
                <tr><td><span class="badge <?php echo $v['effective_to'] === null
                        ? 'badge-success' : 'badge-secondary'; ?>"><?php echo intval($v['version']); ?></span></td>
                    <td><?php echo htmlspecialchars((string)$v['effective_from']); ?></td>
                    <td><?php echo $v['effective_to'] === null ? '<strong>نافذة</strong>'
                        : htmlspecialchars((string)$v['effective_to']); ?></td>
                    <td><?php echo intval($v['rows_n']); ?></td>
                    <td><?php echo htmlspecialchars((string)$v['expected']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

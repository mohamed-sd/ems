<?php
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
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit();
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
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+خطة+الدفع+❌"); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('payment schedule super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$redirect = function ($msg, $c = 0) {
    header("Location: contract_payment_schedule.php?msg=" . rawurlencode($msg)
        . ($c > 0 ? ('&contract=' . $c) : ''));
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
        $redirect('حُدّثت حالاتُ ' . $n . ' سطرًا — والمتأخرُ يُعلَن ✅', $cid);
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
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            الخطةُ تقول <strong>ما استُحقّ ومتى</strong> — لا ما قُبض ولا ما فُوتر.
            و<strong>أنواعُ المقدم الأربعة لا تُخلط</strong>: المستهلَكُ <strong>دَينٌ يُستهلك من
            المستخلصات</strong>، ورسومُ الحجز ودفعةُ المعلَم <strong>إيراد</strong>،
            ورسومُ التعبئة <strong>بحسب نص العقد فتُعلَن ولا تُفترض</strong> —
            <strong>والخلطُ بينها يقلب التزامًا إلى إيرادٍ أو العكس</strong>.
            و<strong>Σ الخطة ليست قيمةَ العقد</strong>: المقدمُ يُستهلك من المستخلصات فلا يُجمع معها.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>#</th><th>الطرفان</th><th>المدة</th><th>الحال</th><th>احتجاز%</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): ?>
                <tr><td>#<?php echo intval($c['id']); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)$c['first_party']
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
        خطةُ العقد #<?php echo $CID; ?> — <?php echo htmlspecialchars((string)$head['second_party']); ?></h5></div>
    <div class="card-body">
        <?php if ($rows): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <span class="badge badge-secondary" style="padding:6px 12px">النسخة
                <?php echo intval($sum['version']); ?></span>
            <span class="badge badge-info" style="padding:6px 12px">متوقَّع
                <?php echo $sum['expected'] . ' ' . htmlspecialchars((string)$sum['currency']); ?></span>
            <span class="badge badge-success" style="padding:6px 12px">مستلم <?php echo $sum['received']; ?></span>
            <span class="badge badge-secondary" style="padding:6px 12px">متبقٍّ <?php echo $sum['remaining']; ?></span>
            <?php if ($sum['overdue_rows'] > 0): ?>
                <span class="badge badge-warning" style="padding:6px 12px">
                    متأخر <?php echo $sum['overdue']; ?> في <?php echo $sum['overdue_rows']; ?> سطرًا</span>
            <?php endif; ?>
            <span class="badge badge-secondary" style="padding:6px 12px">
                مقبوضٌ التزامًا <?php echo $sum['liability']; ?> ·
                إيرادًا <?php echo $sum['revenue']; ?> — <strong>لا يُجمعان</strong></span>
        </div>
        <?php endif; ?>

        <?php if (!$rows && $can_edit): ?>
        <form method="post" class="ems-form" style="margin-bottom:16px">
            <input type="hidden" name="ps_action" value="generate">
            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
            <h6 style="margin:0 0 10px"><i class="fa fa-wand-magic-sparkles"></i>
                توليدٌ آليٌّ من الرأس والجدول الشهري</h6>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <div class="form-group"><label>النمط</label>
                    <select name="pattern">
                        <?php foreach ($PATTERN_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>مهلةُ السداد (يومًا)</label>
                    <input type="number" name="due_days" value="30" min="0" style="width:110px"></div>
                <div class="form-group"><label>نوعُ المقدم (اختياري)</label>
                    <select name="adv_type">
                        <option value="">— بلا مقدم —</option>
                        <?php foreach ($ADV_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>نسبتُه %</label>
                    <input type="number" step="0.001" name="adv_percent" style="width:100px"></div>
                <div class="form-group"><label>تاريخُ استحقاقه</label>
                    <input type="date" name="adv_due"></div>
                <div class="form-group"><label>معالجتُه (للتعبئة وحدَها)</label>
                    <select name="adv_treatment">
                        <option value="">— محكومةٌ بالنوع —</option>
                        <option value="liability">التزام (دَين)</option>
                        <option value="revenue">إيراد</option>
                    </select></div>
                <div class="form-group" style="min-width:280px"><label>نصُّ العقد الحاكم (للتعبئة)</label>
                    <input type="text" name="adv_basis_text" maxlength="255"
                           placeholder="البند 7-3: رسومُ التعبئة غيرُ مستردة…"></div>
            </div>
            <button type="submit" class="btn-save"><i class="fa fa-wand-magic-sparkles"></i> ولّد الخطة</button>
        </form>
        <?php endif; ?>

        <?php if ($rows): ?>
        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>#</th><th>النوع</th><th>المقدمُ ومعالجتُه</th><th>الأساس</th><th>المتوقَّع</th>
                <th>المستلم</th><th>المتبقي</th><th>الاستحقاق</th><th>الشهر</th><th>الحال</th>
                <th>مرجعُ التحصيل</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $st = (string) $r['state']; ?>
                <tr<?php echo $st === 'overdue' ? " style='background:#fef2f2'" : ''; ?>>
                    <td><?php echo intval($r['seq']); ?></td>
                    <td><?php echo htmlspecialchars($KIND_AR[(string)$r['payment_kind']] ?? (string)$r['payment_kind']); ?></td>
                    <td style="white-space:normal"><?php
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
                    <td style="white-space:normal"><?php echo $r['due_date'] !== null
                        ? htmlspecialchars((string)$r['due_date'])
                        : ('<em>' . htmlspecialchars((string)$r['due_condition']) . '</em>'); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['period_month'] ?? '—')); ?></td>
                    <td><span class="badge <?php echo $st === 'completed' ? 'badge-success'
                        : ($st === 'overdue' ? 'badge-warning' : 'badge-secondary'); ?>">
                        <?php echo htmlspecialchars($STATE_AR[$st] ?? $st); ?></span></td>
                    <td><?php echo htmlspecialchars((string)($r['collection_ref'] ?? '—')); ?></td>
                    <td><?php if ($can_edit && (float) $r['remaining_amount'] > 0.004): ?>
                        <form method="post" style="display:flex;gap:4px">
                            <input type="hidden" name="ps_action" value="receive">
                            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                            <input type="hidden" name="row_id" value="<?php echo intval($r['id']); ?>">
                            <input type="number" step="0.01" name="amount" placeholder="المبلغ" required style="width:100px">
                            <input type="date" name="received_date" required style="width:140px">
                            <input type="text" name="doc_ref" placeholder="سندُ القبض" required style="width:130px">
                            <button type="submit" class="action-btn"><i class="fa fa-hand-holding-dollar"></i> اقبض</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_edit): ?>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:14px">
            <form method="post" class="ems-form" style="flex:1;min-width:340px">
                <input type="hidden" name="ps_action" value="add_row">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <h6><i class="fa fa-plus"></i> سطرٌ يدويّ — <strong>للمعالم والدفعات الخاصة</strong></h6>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <div class="form-group"><label>النوع</label>
                        <select name="kind">
                            <option value="milestone">معلَم</option>
                            <option value="final">ختامية</option>
                            <option value="single">دفعةٌ واحدة</option>
                            <option value="advance">مقدَّم</option>
                        </select></div>
                    <div class="form-group"><label>المبلغ</label>
                        <input type="number" step="0.01" name="amount" style="width:120px"></div>
                    <div class="form-group"><label>العملة</label>
                        <input type="text" name="currency" maxlength="8" style="width:80px"
                               value="<?php echo htmlspecialchars((string)$rows[0]['currency']); ?>"></div>
                    <div class="form-group"><label>تاريخُ الاستحقاق</label>
                        <input type="date" name="due_date"></div>
                    <div class="form-group" style="min-width:220px"><label>أو شرطُه</label>
                        <input type="text" name="due_condition" maxlength="200"></div>
                    <div class="form-group"><label>نوعُ المقدم (إن كان مقدمًا)</label>
                        <select name="row_adv_type">
                            <option value="">—</option>
                            <?php foreach ($ADV_AR as $k => $v): ?>
                                <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?></select></div>
                    <div class="form-group"><label>معالجتُه (للتعبئة)</label>
                        <select name="row_adv_treatment">
                            <option value="">— محكومةٌ بالنوع —</option>
                            <option value="liability">التزام</option>
                            <option value="revenue">إيراد</option>
                        </select></div>
                    <div class="form-group" style="min-width:240px"><label>نصُّ العقد الحاكم</label>
                        <input type="text" name="row_adv_basis" maxlength="255"></div>
                </div>
                <button type="submit" class="btn-save"><i class="fa fa-plus"></i> أضف السطر</button>
            </form>

            <form method="post" class="ems-form" style="min-width:280px">
                <input type="hidden" name="ps_action" value="new_version">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <h6><i class="fa fa-code-branch"></i> نسخةٌ جديدةٌ بملحق</h6>
                <div class="form-group"><label>سريانُها</label>
                    <input type="date" name="effective_from" required></div>
                <div class="form-group"><label>رقمُ الملحق (اختياري)</label>
                    <input type="number" name="amendment_id" style="width:120px"></div>
                <button type="submit" class="btn-save"><i class="fa fa-code-branch"></i>
                    افتح نسخةً — <strong>والقديمةُ محفوظة</strong></button>
            </form>

            <form method="post" style="align-self:flex-end">
                <input type="hidden" name="ps_action" value="refresh">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <button type="submit" class="btn-save"><i class="fa fa-rotate"></i> حدّث الحالات</button>
            </form>
        </div>
        <?php endif; ?>
        <?php elseif ($head): ?>
            <p><em>لا خطةَ دفعٍ نافذةً لهذا العقد — ولّدها من الرأس والجدول الشهري.</em></p>
        <?php endif; ?>
    </div></div>

    <?php if ($vers): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        النسخُ — <strong>والقديمةُ محفوظة</strong></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>النسخة</th><th>من</th><th>إلى</th><th>الأسطر</th><th>المتوقَّع</th></tr></thead>
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

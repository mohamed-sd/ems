<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_guarantees.php — ضمانات العقد (P-06)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §3.1: «**فصلٌ إلزامي**: المحتجَزُ **أصلٌ لدى العميل**، وخطابُ الضمان
 * **التزامٌ محتملٌ خارج الميزانية لا يُخصم من مستخلص**».
 * والشاشةُ تُري **الرقمين في عمودين لا في مجموع** — والفرقُ مخزَّنٌ لا محكيّ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractGuaranteeService.php';

use App\Services\Contract\ContractGuaranteeService as CGS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_guarantees.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الضمانات ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('guarantees super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$redirect = function ($msg, $c = 0) {
    header("Location: contract_guarantees.php?msg=" . rawurlencode($msg)
        . ($c > 0 ? ('&contract=' . $c) : ''));
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['g_action'] ?? '') !== '') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['g_action']);
    $cid = intval($_POST['contract_id'] ?? 0);
    if ($act === 'add') {
        $r = CGS::add($conn, $gate, $company_id, $cid, array(
            'kind' => strval($_POST['kind'] ?? ''),
            'nature' => strval($_POST['nature'] ?? ''),
            'deductible_from_claim' => intval($_POST['deductible'] ?? 0),
            'amount' => strval($_POST['amount'] ?? ''),
            'percent_value' => strval($_POST['percent_value'] ?? ''),
            'currency' => strval($_POST['currency'] ?? ''),
            'issuer' => strval($_POST['issuer'] ?? ''),
            'instrument_ref' => strval($_POST['instrument_ref'] ?? ''),
            'issue_date' => strval($_POST['issue_date'] ?? ''),
            'expiry_date' => strval($_POST['expiry_date'] ?? ''),
            'due_release_date' => strval($_POST['due_release_date'] ?? ''),
            'release_condition' => strval($_POST['release_condition'] ?? ''),
            'note' => strval($_POST['note'] ?? ''),
        ), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'state') {
        $r = CGS::setState($conn, $gate, $company_id, intval($_POST['gid'] ?? 0),
                           strval($_POST['state'] ?? ''), strval($_POST['state_reason'] ?? ''),
                           strval($_POST['state_at'] ?? ''), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.first_party, c.second_party, c.contract_status, c.retention_pct,
                c.price_currency_contract, c.guarantees
           FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC LIMIT 200");
} catch (\Throwable $t) { $contracts = array(); }

$rows = $CID > 0 ? CGS::rowsOf($gate, $CID) : array();
$exp  = $CID > 0 ? CGS::exposure($gate, $CID) : array();
$bal  = $CID > 0 ? CGS::retentionBalance($gate, $CID) : array();
$head = null;
foreach ($contracts as $c) { if ((int) $c['id'] === $CID) { $head = $c; } }

$KIND_AR = CGS::KIND_AR; $NATURE_AR = CGS::NATURE_AR; $STATE_AR = CGS::STATE_AR;

$page_title = 'إيكوبيشن | ضمانات العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'guarantees';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'ضمانات العقد'; $header_icon = 'fa fa-shield-halved';
    $header_actions = array();
    $header_back = array('href' => 'contract_payment_schedule.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'خطة الدفع');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            <strong>فصلٌ إلزامي</strong>: المحتجَزُ النقديُّ <strong>أصلٌ لدى العميل</strong> يُخصم من
            المستخلص ويُتابَع بذمةٍ مؤجَّلةٍ <strong>وتاريخِ ردّ</strong>؛
            وخطابُ الضمان البنكيُّ والتأمينُ والكفالةُ <strong>التزامٌ محتملٌ خارج الميزانية</strong> —
            <strong>لا يُخصم من مستخلصٍ ولا يظهر أصلًا</strong>.
            <strong>والخلطُ بينها خطأٌ محاسبيٌّ</strong> لا خيارُ مستخدم.
            و<strong>رصيدُ المحتجَز يُقرأ من المستخلصات</strong> لا من هذا السجل.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>#</th><th>العميل</th><th>الحال</th><th>احتجاز%</th><th>نصُّ الضمانات (الأصلي)</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): ?>
                <tr><td>#<?php echo intval($c['id']); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)$c['second_party']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['contract_status']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['retention_pct']); ?></td>
                    <td style="white-space:normal"><small><?php
                        echo htmlspecialchars(mb_substr((string)($c['guarantees'] ?? ''), 0, 80)); ?></small></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-shield-halved"></i> الضمانات</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($head): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-shield-halved"></i>
        ضماناتُ العقد #<?php echo $CID; ?> — <?php echo htmlspecialchars((string)$head['second_party']); ?></h5></div>
    <div class="card-body">
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px">
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:10px 16px;min-width:230px">
                <div style="color:#6b7280;font-size:.85em">أصلٌ لدى العميل — <strong>محتجزٌ نقديٌّ فعليّ</strong></div>
                <div style="font-size:1.4em;font-weight:700"><?php echo $exp['asset']; ?>
                    <?php echo htmlspecialchars((string)$exp['currency']); ?></div>
                <small><?php echo htmlspecialchars((string)($bal['note'] ?? '')); ?></small>
            </div>
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:10px 16px;min-width:230px">
                <div style="color:#6b7280;font-size:.85em"><strong>التزامٌ محتملٌ خارج الميزانية</strong></div>
                <div style="font-size:1.4em;font-weight:700"><?php echo $exp['off_balance']; ?>
                    <?php echo htmlspecialchars((string)$exp['currency']); ?></div>
                <small><strong>لا يظهر أصلًا ولا يُخصم من مستخلص</strong></small>
            </div>
            <div style="align-self:center">
                <span class="badge badge-warning" style="padding:8px 14px"><strong>رقمان لا يُجمعان</strong></span>
                <?php if (($exp['expiring'] ?? 0) > 0): ?>
                    <span class="badge badge-warning" style="padding:8px 14px">
                        <?php echo intval($exp['expiring']); ?> أداةً انقضى سريانُها</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>#</th><th>النوع</th><th>الطبيعة</th><th>يُخصم؟</th><th>القيمة</th><th>%</th>
                <th>المُصدر</th><th>المرجع</th><th>انتهاءُ السريان</th><th>ردُّ المحتجَز</th>
                <th>الحال</th><th>النصُّ الأصلي</th><th></th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $nat = (string) $r['nature']; ?>
                <tr<?php echo (int)$r['needs_review'] === 1 ? " style='background:#fff7ed'" : ''; ?>>
                    <td><?php echo intval($r['id']); ?></td>
                    <td><?php echo htmlspecialchars($KIND_AR[(string)$r['kind']] ?? (string)$r['kind']); ?>
                        <?php echo (int)$r['needs_review'] === 1
                            ? '<span class="badge badge-warning">ينتظر الإقرار</span>' : ''; ?></td>
                    <td><span class="badge <?php echo $nat === 'asset' ? 'badge-info' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars($NATURE_AR[$nat] ?? $nat); ?></span></td>
                    <td><?php echo (int)$r['deductible_from_claim'] === 1
                        ? 'نعم' : '<strong>لا</strong>'; ?></td>
                    <td><?php echo htmlspecialchars((string)$r['amount']); ?></td>
                    <td><?php echo $r['percent_value'] !== null
                        ? htmlspecialchars((string)$r['percent_value']) : '—'; ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)($r['issuer'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['instrument_ref'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['expiry_date'] ?? '—')); ?></td>
                    <td style="white-space:normal"><?php echo $r['due_release_date'] !== null
                        ? htmlspecialchars((string)$r['due_release_date'])
                        : ('<em>' . htmlspecialchars((string)($r['release_condition'] ?? '—')) . '</em>'); ?></td>
                    <td><span class="badge <?php echo (string)$r['state'] === 'active'
                        ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars($STATE_AR[(string)$r['state']] ?? (string)$r['state']); ?></span>
                        <?php echo $r['state_reason'] !== null
                            ? ('<br><small>' . htmlspecialchars((string)$r['state_reason']) . '</small>') : ''; ?></td>
                    <td style="white-space:normal"><small><?php
                        echo htmlspecialchars((string)($r['source_text'] ?? '—')); ?></small></td>
                    <td><?php if ($can_edit && !in_array((string)$r['state'], array('released','called'), true)): ?>
                        <form method="post" style="display:flex;gap:4px">
                            <input type="hidden" name="g_action" value="state">
                            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                            <input type="hidden" name="gid" value="<?php echo intval($r['id']); ?>">
                            <select name="state" style="width:110px">
                                <?php foreach ($STATE_AR as $k => $v): ?>
                                    <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                                <?php endforeach; ?></select>
                            <input type="text" name="state_reason" placeholder="السبب" style="width:130px">
                            <input type="date" name="state_at" style="width:140px">
                            <button type="submit" class="action-btn"><i class="fa fa-check"></i></button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="13"><em>لا ضماناتٍ مسجَّلة</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_edit): ?>
        <form method="post" class="ems-form" style="margin-top:14px">
            <input type="hidden" name="g_action" value="add">
            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
            <h6><i class="fa fa-plus"></i> أداةُ ضمانٍ جديدة —
                <strong>والنوعُ يحسم الطبيعةَ وقابليةَ الخصم</strong></h6>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <div class="form-group"><label>النوع</label>
                    <select name="kind">
                        <?php foreach ($KIND_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>القيمة</label>
                    <input type="number" step="0.01" name="amount" style="width:130px"></div>
                <div class="form-group"><label>النسبة %</label>
                    <input type="number" step="0.001" name="percent_value" style="width:100px"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="currency" maxlength="8" style="width:80px"
                           value="<?php echo htmlspecialchars((string)$head['price_currency_contract']); ?>"></div>
                <div class="form-group"><label>المُصدر</label>
                    <input type="text" name="issuer" maxlength="190" style="width:200px"></div>
                <div class="form-group"><label>رقمُ الخطاب/الوثيقة</label>
                    <input type="text" name="instrument_ref" maxlength="120" style="width:150px"></div>
                <div class="form-group"><label>تاريخُ الإصدار</label>
                    <input type="date" name="issue_date"></div>
                <div class="form-group"><label>انتهاءُ السريان <small>(لغير المحتجَز)</small></label>
                    <input type="date" name="expiry_date"></div>
                <div class="form-group"><label>تاريخُ ردِّ المحتجَز</label>
                    <input type="date" name="due_release_date"></div>
                <div class="form-group" style="min-width:260px"><label>أو شرطُ الرد</label>
                    <input type="text" name="release_condition" maxlength="200"></div>
                <div class="form-group" style="min-width:220px"><label>ملاحظة</label>
                    <input type="text" name="note" maxlength="255"></div>
            </div>
            <button type="submit" class="btn-save"><i class="fa fa-plus"></i> سجّل الأداة</button>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

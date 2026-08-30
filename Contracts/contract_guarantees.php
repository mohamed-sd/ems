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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_guarantees.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) { $can_view = $can_add = $can_edit = true; }
else {
    /* `RPR-03` §٦ — **المسارُ الواحد**: القرارُ من `check_page_permissions()`
           لا من استعلامٍ خاصٍّ بهذا الملفّ. **والفرقُ طبقةُ القوالب**
           (`GOV-AUTH-01`): القراءةُ الخامّةُ لا ترى القالبَ النافذَ، فتُخفى
           الشاشةُ من السايدبارِ وتُفتح بالرابطِ المباشر.
        ⛔ **وفرعُ السوبر أدمن أعلاه لم يُمَسّ** — والأسماءُ كما كانت. */
    $__perm = check_page_permissions($conn, $MODULE_CODE);
    $can_view = (bool) $__perm['can_view'];
    $can_add = (bool) $__perm['can_add'];
    $can_edit = (bool) $__perm['can_edit'];
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الضمانات ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('guarantees super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_guarantees.php', ($c > 0 ? ('&contract=' . $c) : '')), $msg, 'GOV-INFO-200', '');
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
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا أدوات ضمان مسجلة على هذا العقد بعد',
                           'اختر عقدا من جدول العقود ثم سجل أول أداة بنموذج «أداة ضمان جديدة»');
    ?>

    <style>
    .cg-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0}
    .cg-table{width:100%}
    .cg-wrap{white-space:normal}
    .cg-cards{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px}
    .cg-card{border:1px solid var(--c-d1d5db, #d1d5db);border-radius:8px;padding:10px 16px;min-width:230px}
    .cg-card-label{color:var(--c-ink-500, #6b7280);font-size:.85em}
    .cg-card-value{font-size:1.4em;font-weight:700}
    .cg-card-side{align-self:center}
    .cg-badge-pad{padding:8px 14px}
    .cg-row-review{background:var(--c-fff7ed, #fff7ed)}
    .cg-inline-form{display:flex;gap:4px}
    .cg-add-form{margin-top:14px}
    .cg-add-grid{display:flex;gap:8px;flex-wrap:wrap}
    .cg-w80{width:80px}
    .cg-w100{width:100px}
    .cg-w110{width:110px}
    .cg-w130{width:130px}
    .cg-w140{width:140px}
    .cg-w150{width:150px}
    .cg-w200{width:200px}
    .cg-mw220{min-width:220px}
    .cg-mw260{min-width:260px}
    </style>

    <div class="card"><div class="card-body">
        <p class="cg-note">
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
        <table class="alltables display nowrap no-datatable cg-table" data-no-dt="1">
            <thead><tr><th>#</th><th>العميل</th><th>الحال</th><th>احتجاز%</th><th>نصُّ الضمانات (الأصلي)</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): ?>
                <tr><td>#<?php echo intval($c['id']); ?></td>
                    <td class="cg-wrap"><?php echo htmlspecialchars((string)$c['second_party']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['contract_status']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['retention_pct']); ?></td>
                    <td class="cg-wrap"><small><?php
                        echo htmlspecialchars(mb_substr((string)($c['guarantees'] ?? ''), 0, 80)); ?></small></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-shield-halved"></i> الضمانات</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($head): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-shield-halved"></i>
        ضمانات العقد #<?php echo $CID; ?> — <?php echo htmlspecialchars((string)$head['second_party']); ?></h5></div>
    <div class="card-body">
        <div class="cg-cards">
            <div class="cg-card">
                <div class="cg-card-label">أصلٌ لدى العميل — <strong>محتجزٌ نقديٌّ فعليّ</strong></div>
                <div class="cg-card-value"><?php echo $exp['asset']; ?>
                    <?php echo htmlspecialchars((string)$exp['currency']); ?></div>
                <small><?php echo htmlspecialchars((string)($bal['note'] ?? '')); ?></small>
            </div>
            <div class="cg-card">
                <div class="cg-card-label"><strong>التزامٌ محتملٌ خارج الميزانية</strong></div>
                <div class="cg-card-value"><?php echo $exp['off_balance']; ?>
                    <?php echo htmlspecialchars((string)$exp['currency']); ?></div>
                <small><strong>لا يظهر أصلا ولا يخصم من مستخلص</strong></small>
            </div>
            <div class="cg-card-side">
                <span class="badge badge-warning cg-badge-pad"><strong>رقمان لا يجمعان</strong></span>
                <?php if (($exp['expiring'] ?? 0) > 0): ?>
                    <span class="badge badge-warning cg-badge-pad">
                        <?php echo intval($exp['expiring']); ?> أداة انقضى سريانها</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container">
        <table class="alltables display nowrap no-datatable cg-table" data-no-dt="1">
            <thead><tr><th>#</th><th>النوع</th><th>الطبيعة</th><th>يخصم؟</th><th>القيمة</th><th>%</th>
                <th>المصدر</th><th>المرجع</th><th>انتهاء السريان</th><th>رد المحتجز</th>
                <th>الحال</th><th>النص الأصلي</th><th></th>
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
            <?php foreach ($rows as $r): $nat = (string) $r['nature']; ?>
                <tr<?php echo (int)$r['needs_review'] === 1 ? ' class="cg-row-review"' : ''; ?>>
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
                    <td class="cg-wrap"><?php echo htmlspecialchars((string)($r['issuer'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['instrument_ref'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['expiry_date'] ?? '—')); ?></td>
                    <td class="cg-wrap"><?php echo $r['due_release_date'] !== null
                        ? htmlspecialchars((string)$r['due_release_date'])
                        : ('<em>' . htmlspecialchars((string)($r['release_condition'] ?? '—')) . '</em>'); ?></td>
                    <td><span class="badge <?php echo (string)$r['state'] === 'active'
                        ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars($STATE_AR[(string)$r['state']] ?? (string)$r['state']); ?></span>
                        <?php echo $r['state_reason'] !== null
                            ? ('<br><small>' . htmlspecialchars((string)$r['state_reason']) . '</small>') : ''; ?></td>
                    <td class="cg-wrap"><small><?php
                        echo htmlspecialchars((string)($r['source_text'] ?? '—')); ?></small></td>
                    <td><?php if ($can_edit && !in_array((string)$r['state'], array('released','called'), true)): ?>
                        <form method="post" class="cg-inline-form">
        <?php echo csrf_field(); ?>
                            <input type="hidden" name="g_action" value="state">
                            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                            <input type="hidden" name="gid" value="<?php echo intval($r['id']); ?>">
                            <select name="state" class="cg-w110" aria-label="الحال الجديد لأداة الضمان">
                                <?php foreach ($STATE_AR as $k => $v): ?>
                                    <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                                <?php endforeach; ?></select>
                            <input type="text" name="state_reason" class="cg-w130" placeholder="سبب تغيير الحال" aria-label="سبب تغيير حال الأداة">
                            <input type="date" name="state_at" class="cg-w140" aria-label="تاريخ تغيير حال الأداة">
                            <button type="submit" class="action-btn"><i class="fa fa-check"></i></button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="13"><em>لا ضمانات مسجلة</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_edit): ?>
        <form method="post" class="ems-form cg-add-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="g_action" value="add">
            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
            <h6><i class="fa fa-plus"></i> أداة ضمان جديدة —
                <strong>والنوع يحسم الطبيعة وقابلية الخصم</strong></h6>
            <div class="cg-add-grid">
                <div class="form-group"><label for="emsf_35_741f3">النوع</label>
                    <select name="kind" id="emsf_35_741f3">
                        <?php foreach ($KIND_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label for="emsf_36_4f9e4">القيمة</label>
                    <input type="number" step="0.01" name="amount" class="cg-w130" id="emsf_36_4f9e4"></div>
                <div class="form-group"><label for="emsf_37_afa0b">النسبة %</label>
                    <input type="number" step="0.001" name="percent_value" class="cg-w100" id="emsf_37_afa0b"></div>
                <div class="form-group"><label for="emsf_38_b0f47">العملة</label>
                    <input type="text" name="currency" maxlength="8" class="cg-w80" id="emsf_38_b0f47"
                           value="<?php echo htmlspecialchars((string)$head['price_currency_contract']); ?>"></div>
                <div class="form-group"><label for="emsf_39_1693c">المصدر</label>
                    <input type="text" name="issuer" maxlength="190" class="cg-w200" id="emsf_39_1693c"></div>
                <div class="form-group"><label for="emsf_40_d6e85">رقم الخطاب/الوثيقة</label>
                    <input type="text" name="instrument_ref" maxlength="120" class="cg-w150" id="emsf_40_d6e85"></div>
                <div class="form-group"><label for="emsf_41_c32f3">تاريخ الإصدار</label>
                    <input type="date" name="issue_date" id="emsf_41_c32f3"></div>
                <div class="form-group"><label for="emsf_42_def6e">انتهاء السريان <small>(لغير المحتجز)</small></label>
                    <input type="date" name="expiry_date" id="emsf_42_def6e"></div>
                <div class="form-group"><label for="emsf_43_97f1d">تاريخ رد المحتجز</label>
                    <input type="date" name="due_release_date" id="emsf_43_97f1d"></div>
                <div class="form-group cg-mw260"><label for="emsf_44_7e7b1">أو شرط الرد</label>
                    <input type="text" name="release_condition" maxlength="200" id="emsf_44_7e7b1"></div>
                <div class="form-group cg-mw220"><label for="emsf_45_cd491">ملاحظة</label>
                    <input type="text" name="note" maxlength="255" id="emsf_45_cd491"></div>
            </div>
            <button type="submit" class="btn-primary"><i class="fa fa-plus"></i> سجل الأداة</button>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

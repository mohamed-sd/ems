<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_lifecycle.php — اقتصاد دورة حياة العقد (P-11)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §6 الجدولُ الحاكم · §6.1 القواعدُ الملزمةُ الخمس.
 * والشاشةُ تُري **جدولَ §6 نفسَه** ثم **خطةَ عملٍ بأرقام العقد الحيّة** —
 * و**تُقرّر ولا تُنفّذ**: لكلِّ فعلٍ بيتُه.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractLifecycleService.php';

use App\Services\Contract\ContractLifecycleService as CLC;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_lifecycle.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض دورة الحياة ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('lifecycle super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$SEL  = isset($_GET['state']) ? trim(strval($_GET['state'])) : '';
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_lifecycle.php', ($c > 0 ? ('&contract=' . $c) : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['lc_action'] ?? '') === 'record') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $cid = intval($_POST['contract_id'] ?? 0);
    $r = CLC::record($conn, $gate, $company_id, $cid, array(
        'state' => strval($_POST['state'] ?? ''),
        'effect_date' => strval($_POST['effect_date'] ?? ''),
        'decision_ref' => strval($_POST['decision_ref'] ?? ''),
        'claim_amount' => strval($_POST['claim_amount'] ?? ''),
        'claim_currency' => strval($_POST['claim_currency'] ?? ''),
        'contract_article' => strval($_POST['contract_article'] ?? ''),
        'claim_doc_ref' => strval($_POST['claim_doc_ref'] ?? ''),
        'note' => strval($_POST['note'] ?? ''),
    ), $uid);
    $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.second_party, c.contract_status, c.actual_start, c.actual_end
           FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC LIMIT 200");
} catch (\Throwable $t) { $contracts = array(); }

$events = $CID > 0 ? CLC::eventsOf($gate, $CID) : array();
$plan = ($CID > 0 && $SEL !== '') ? CLC::plan($gate, $CID, $SEL) : null;
$STATE_AR = CLC::STATE_AR;
$EFF = CLC::EFFECT_AR;
$MTX = CLC::MATRIX;
$strip = function ($s) { return str_replace('**', '', (string) $s); };

$page_title = 'إيكوبيشن | اقتصاد دورة حياة العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'lifecycle';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'اقتصاد دورة حياة العقد'; $header_icon = 'fa fa-scale-unbalanced';
    $header_actions = array();
    $header_back = array('href' => 'contract_baseline.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'خط أساس العقد');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            لكلِّ حالةٍ <strong>أثرٌ ماليٌّ محدد</strong> على خمسة عناصر — و<strong>الأثرُ محكومٌ بالحالة لا يُختار</strong>:
            من أراد أثرًا مخالفًا <strong>يلزمه تغييرُ الحالة</strong>.
            و<strong>المنفَّذُ غيرُ المفوتر لا يسقط بالإنهاء</strong> مهما كان سببُه —
            <strong>والعملُ المنفَّذ حقٌّ مكتسب</strong>.
            و<strong>لا خصمَ ولا تعويضَ إلا بمادةٍ من العقد وحسابٍ موثَّق</strong> — وإلا فهي مطالبةٌ تفاوضيةٌ لا خصمٌ نظامي.
            و<strong>لا أثرَ رجعي</strong>: لكلِّ حالةٍ تاريخُ أثرٍ محدد.
            <br><strong>وهذه الشاشةُ تُقرّر ولا تُنفّذ</strong> — الردُّ والفوترةُ وإقفالُ الحاويات لكلٍّ بيتُه.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-table"></i>
        جدولُ §6 — <strong>الحالاتُ الثماني بأثرها الخماسي</strong></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الحالة</th><th>المقدَّم</th><th>الضمان</th>
                <th>المنفَّذُ غيرُ المفوتر</th><th>الغرامات</th><th>الحاويات</th>
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
            <?php foreach ($MTX as $s => $e): ?>
                <tr><td><strong><?php echo htmlspecialchars($STATE_AR[$s]); ?></strong></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars($strip($EFF['advance'][$e['advance']])); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars($strip($EFF['retention'][$e['retention']])); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars($strip($EFF['unbilled'][$e['unbilled']])); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars($strip($EFF['penalty'][$e['penalty']])); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars($strip($EFF['container'][$e['container']])); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>#</th><th>العميل</th><th>المدة</th><th>حالُ العقد</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): ?>
                <tr><td>#<?php echo intval($c['id']); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)$c['second_party']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['actual_start'] . ' → ' . (string)$c['actual_end']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['contract_status']); ?></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-scale-unbalanced"></i> دورةُ الحياة</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($CID > 0): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i>
        خطةُ العمل بأرقام العقد #<?php echo $CID; ?> الحيّة</h5></div>
    <div class="card-body">
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
            <input type="hidden" name="contract" value="<?php echo $CID; ?>">
            <div class="form-group"><label>الحالة</label>
                <select name="state">
                    <?php foreach ($STATE_AR as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $k === $SEL ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?></select></div>
            <button type="submit" class="btn-save"><i class="fa fa-calculator"></i> احسب الأثر</button>
        </form>

        <?php if ($plan !== null && $plan['ok']): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <span class="badge badge-secondary" style="padding:6px 12px">رصيدُ المقدَّم
                <?php echo $plan['figures']['advance_balance']; ?></span>
            <span class="badge badge-secondary" style="padding:6px 12px">المحتجَز
                <?php echo $plan['figures']['retention_balance']; ?></span>
            <span class="badge badge-info" style="padding:6px 12px">منفَّذ
                <?php echo $plan['figures']['executed']; ?></span>
            <span class="badge badge-success" style="padding:6px 12px">مفوتَر
                <?php echo $plan['figures']['billed']; ?></span>
            <span class="badge <?php echo $plan['figures']['unbilled'] > 0.004
                ? 'badge-warning' : 'badge-secondary'; ?>" style="padding:6px 12px">
                منفَّذٌ غيرُ مفوتر <?php echo $plan['figures']['unbilled']; ?></span>
        </div>
        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>المجال</th><th>القاعدة</th><th>الرقمُ الحيّ</th><th>بيتُ التنفيذ</th></tr></thead>
            <tbody>
            <?php foreach ($plan['actions'] as $a): ?>
                <tr<?php echo mb_strpos((string)$a['area'], '⚠') !== false ? " style='background:#fff7ed'" : ''; ?>>
                    <td><strong><?php echo htmlspecialchars((string)$a['area']); ?></strong></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars($strip($a['rule'])); ?></td>
                    <td><?php echo $a['figure'] !== null ? htmlspecialchars((string)$a['figure']) : '—'; ?></td>
                    <td><small><?php echo htmlspecialchars((string)$a['home']); ?></small></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <?php if ($can_edit): ?>
        <form method="post" class="ems-form" style="margin-top:14px">
            <input type="hidden" name="lc_action" value="record">
            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
            <h6><i class="fa fa-plus"></i> سجّل واقعةً — <strong>وأثرُها يُكتب من الجدول لا من الطلب</strong></h6>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <div class="form-group"><label>الحالة</label>
                    <select name="state">
                        <?php foreach ($STATE_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>تاريخُ الأثر <span style="color:#c00">*</span></label>
                    <input type="date" name="effect_date" required></div>
                <div class="form-group"><label>مرجعُ القرار <small>(إلزاميٌّ للإنهاء والإلغاء)</small></label>
                    <input type="text" name="decision_ref" maxlength="120" style="width:170px"></div>
                <div class="form-group"><label>تعويض/غرامة</label>
                    <input type="number" step="0.01" name="claim_amount" style="width:130px"></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="claim_currency" maxlength="8" style="width:80px"></div>
                <div class="form-group" style="min-width:280px"><label>مادةُ العقد الحاكمة
                    <small>(إلزاميةٌ مع أيِّ مبلغ)</small></label>
                    <input type="text" name="contract_article" maxlength="200"></div>
                <div class="form-group"><label>مستندُ الحساب</label>
                    <input type="text" name="claim_doc_ref" maxlength="120" style="width:150px"></div>
                <div class="form-group" style="min-width:220px"><label>ملاحظة</label>
                    <input type="text" name="note" maxlength="255"></div>
            </div>
            <button type="submit" class="btn-save"><i class="fa fa-save"></i> سجّل الواقعة</button>
        </form>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        وقائعُ العقد — <?php echo count($events); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الحالة</th><th>تاريخُ الأثر</th><th>مرجعُ القرار</th>
                <th>الأثرُ الخماسي</th><th>المطالبة</th><th>المادة</th></tr></thead>
            <tbody>
            <?php foreach ($events as $e): ?>
                <tr><td><strong><?php echo htmlspecialchars($STATE_AR[(string)$e['state']]); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$e['effect_date']); ?></td>
                    <td><?php echo htmlspecialchars((string)($e['decision_ref'] ?? '—')); ?></td>
                    <td style="white-space:normal"><small>
                        <?php echo htmlspecialchars($strip($EFF['advance'][(string)$e['advance_effect']])); ?> ·
                        <?php echo htmlspecialchars($strip($EFF['retention'][(string)$e['retention_effect']])); ?> ·
                        <?php echo htmlspecialchars($strip($EFF['unbilled'][(string)$e['unbilled_effect']])); ?> ·
                        <?php echo htmlspecialchars($strip($EFF['penalty'][(string)$e['penalty_effect']])); ?> ·
                        <?php echo htmlspecialchars($strip($EFF['container'][(string)$e['container_effect']])); ?>
                    </small></td>
                    <td><?php echo $e['claim_amount'] !== null
                        ? (htmlspecialchars((string)$e['claim_amount']) . ' ' . htmlspecialchars((string)$e['claim_currency']))
                        : '—'; ?></td>
                    <td style="white-space:normal"><small><?php
                        echo htmlspecialchars((string)($e['contract_article'] ?? '—')); ?></small></td></tr>
            <?php endforeach; ?>
            <?php if (!$events): ?><tr><td colspan="6"><em>لا وقائع</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

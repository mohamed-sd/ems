<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/plan_actual_link.php — ربطُ الخطة بالفعلي (P-09)
 * ───────────────────────────────────────────────────────────────────────────
 * الملحق §3-`P-09`: «مفاتيحُ ربط الخطة بالفعلي على **الوحدة وسطر المستخلص**».
 * والشاشةُ تُري **الفجوتين**: تنفيذًا (منفَّذ − مخطَّط) وفوترةً (مفوتَر − منفَّذ)
 * — و**غيرَ الموصول عدًّا لا مخفيًّا**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/PlanActualLinkService.php';

use App\Services\Contract\PlanActualLinkService as PAL;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/plan_actual_link.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الربط ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('plan actual super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$FROM = isset($_GET['from']) ? trim(strval($_GET['from'])) : '';
$TO   = isset($_GET['to']) ? trim(strval($_GET['to'])) : '';
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('plan_actual_link.php', ($c > 0 ? ('&contract=' . $c) : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['pal_action'] ?? '') !== '') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['pal_action']);
    $cid = intval($_POST['contract_id'] ?? 0);
    if ($act === 'link_all' || $act === 'dry') {
        $r = PAL::linkContract($conn, $gate, $company_id, $cid, $uid, ($act === 'link_all'));
        $redirect($r['note'] . ' ✅', $cid);
    }
    if ($act === 'link_one') {
        $r = (strval($_POST['row_kind'] ?? '') === 'claim')
             ? PAL::linkClaimLine($conn, $gate, $company_id, intval($_POST['row_id'] ?? 0),
                   array('contract_line_id' => intval($_POST['line_id'] ?? 0)), $uid, true)
             : PAL::linkUnit($conn, $gate, $company_id, intval($_POST['row_id'] ?? 0),
                   array('contract_line_id' => intval($_POST['line_id'] ?? 0)), $uid, true);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.first_party, c.second_party, c.contract_status, c.actual_start, c.actual_end
           FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC LIMIT 200");
} catch (\Throwable $t) { $contracts = array(); }

$cov = PAL::coverage($gate, $CID);
$pv  = $CID > 0 ? PAL::planVsActual($gate, $CID, $FROM, $TO) : array('rows' => array(),
        'totals' => array('planned' => 0, 'actual' => 0, 'billed' => 0), 'note' => '');
$unlinked = array();
if ($CID > 0) {
    try {
        $unlinked = $gate->scopedQuery(array('scope' => array('u' => 'unit_entries')),
            "SELECT u.id, u.entry_no, u.entry_date, u.unit_type, u.qty, u.state
               FROM unit_entries u
              WHERE {TENANT_SCOPE} AND u.contract_id = ? AND u.contract_line_id IS NULL
              ORDER BY u.entry_date DESC LIMIT 50", array($CID));
    } catch (\Throwable $t) { $unlinked = array(); }
}

$page_title = 'إيكوبيشن | ربط الخطة بالفعلي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'actual';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'ربط الخطة بالفعلي'; $header_icon = 'fa fa-link';
    $header_actions = array();
    $header_back = array('href' => 'contract_monthly_plan.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'الجدول الشهري');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            «المخطَّطُ» و«المنفَّذُ» و«المفوتَرُ» ثلاثةُ أرقامٍ كانت <strong>لا تلتقي على مفتاح</strong>،
            فمقارنتُها <strong>تخمينٌ بالتاريخ والعقد</strong>. وبمفاتيح
            <code>contract_line_id</code> و<code>plan_period_id</code> و<code>operational_site_id</code>
            صارت <strong>تلتقي على مفتاحٍ واحد</strong>.
            و<strong>الوصلُ يُشتقّ ولا يُخمَّن</strong>: بندان يصلحان ⇒ <strong>يُعلَن الالتباسُ ولا يُختار بالحدس</strong>.
            و<strong>غيرُ الموصول يُعدّ ولا يُخفى</strong>.
        </p>
        <div style="margin-top:10px">
            <span class="badge badge-secondary" style="padding:6px 12px">
                وحداتٌ موصولة <?php echo $cov['units_linked'] . '/' . $cov['units_total']; ?></span>
            <span class="badge badge-secondary" style="padding:6px 12px">
                أسطرُ مستخلصٍ موصولة <?php echo $cov['claims_linked'] . '/' . $cov['claims_total']; ?></span>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>#</th><th>العميل</th><th>المدة</th><th>الحال</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): ?>
                <tr><td>#<?php echo intval($c['id']); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)$c['second_party']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['actual_start'] . ' → ' . (string)$c['actual_end']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['contract_status']); ?></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-link"></i> الربط</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($CID > 0): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-table-columns"></i>
        المخطَّطُ · المنفَّذُ · المفوتَر — للعقد #<?php echo $CID; ?></h5></div>
    <div class="card-body">
        <form method="get" class="ems-form" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
            <input type="hidden" name="contract" value="<?php echo $CID; ?>">
            <div class="form-group"><label for="emsf_81_e9776">من شهر</label>
                <input type="text" name="from" placeholder="2091-01" style="width:110px"
                       value="<?php echo htmlspecialchars($FROM); ?>" id="emsf_81_e9776"></div>
            <div class="form-group"><label for="emsf_82_2cd30">إلى شهر</label>
                <input type="text" name="to" placeholder="2091-12" style="width:110px"
                       value="<?php echo htmlspecialchars($TO); ?>" id="emsf_82_2cd30"></div>
            <div style="align-self:flex-end"><button type="submit" class="btn-primary">
                <i class="fa fa-filter"></i> اعرض</button></div>
        </form>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <span class="badge badge-secondary" style="padding:6px 12px">مخطَّط
                <?php echo $pv['totals']['planned']; ?></span>
            <span class="badge badge-info" style="padding:6px 12px">منفَّذ
                <?php echo $pv['totals']['actual']; ?></span>
            <span class="badge badge-success" style="padding:6px 12px">مفوتَر
                <?php echo $pv['totals']['billed']; ?></span>
        </div>

        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>البند</th><th>الوصف</th><th>الشهر</th><th>الوحدة</th>
                <th>مخطَّط</th><th>منفَّذ</th><th>مفوتَر</th>
                <th>فجوةُ التنفيذ</th><th>فجوةُ الفوترة</th>
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
            <?php foreach ($pv['rows'] as $r): ?>
                <tr><td>#<?php echo intval($r['line_no']); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)$r['description']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$r['period_month']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$r['unit']); ?></td>
                    <td><?php echo $r['planned']; ?></td>
                    <td><?php echo $r['actual']; ?></td>
                    <td><?php echo $r['billed']; ?></td>
                    <td><span class="badge <?php echo abs($r['gap_exec']) < 0.005
                        ? 'badge-success' : 'badge-warning'; ?>"><?php echo $r['gap_exec']; ?></span></td>
                    <td><span class="badge <?php echo abs($r['gap_bill']) < 0.005
                        ? 'badge-success' : 'badge-warning'; ?>"><?php echo $r['gap_bill']; ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (!$pv['rows']): ?><tr><td colspan="9"><em>لا جدولَ شهريًّا نافذًا لبنود هذا العقد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_edit): ?>
        <div style="display:flex;gap:8px;margin-top:12px">
            <form method="post" style="display:inline">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="pal_action" value="dry">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-magnifying-glass"></i>
                    اعرض المرشَّح <strong>(بلا كتابة)</strong></button>
            </form>
            <form method="post" style="display:inline">
        <?php echo csrf_field(); ?>
                <input type="hidden" name="pal_action" value="link_all">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-link"></i>
                    صِل ما يمكن وصلُه — <strong>والملتبسُ يُعلَن</strong></button>
            </form>
        </div>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-link-slash"></i>
        وحداتٌ <strong>غيرُ موصولة</strong> — <?php echo count($unlinked); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الرقم</th><th>التاريخ</th><th>الوحدة</th><th>الكمية</th><th>الحال</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($unlinked as $u): ?>
                <tr style="background:#fff7ed">
                    <td><?php echo htmlspecialchars((string)$u['entry_no']); ?></td>
                    <td><?php echo htmlspecialchars((string)$u['entry_date']); ?></td>
                    <td><?php echo htmlspecialchars((string)$u['unit_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$u['qty']); ?></td>
                    <td><?php echo htmlspecialchars((string)$u['state']); ?></td>
                    <td><?php if ($can_edit): ?>
                        <form method="post" style="display:flex;gap:4px">
        <?php echo csrf_field(); ?>
                            <input type="hidden" name="pal_action" value="link_one">
                            <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                            <input type="hidden" name="row_kind" value="unit">
                            <input type="hidden" name="row_id" value="<?php echo intval($u['id']); ?>">
                            <input type="number" name="line_id" placeholder="بندٌ صريح (اختياري)" style="width:150px" aria-label="بندٌ صريح (اختياري)">
                            <button type="submit" class="action-btn"><i class="fa fa-link"></i> صِل</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$unlinked): ?><tr><td colspan="6"><em>لا وحدةَ غيرَ موصولة — <strong>الفجوةُ صفر</strong></em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_baseline.php — خط أساس العقد (P-10)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §3.6: «عند الاعتماد **تُقفل كلُّ المكوّنات** — **ومن هنا فقط تبدأ
 * الفوترة**» · §9-⑱ · والملحق §2-②: **البوابةُ تبدأ مطفأة**.
 * والشاشةُ تُري **الفجوات مسمّاةً قبل القفل** ووضعَ البوابة صراحةً.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractBaselineService.php';

use App\Services\Contract\ContractBaselineService as CBS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_baseline.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض خط الأساس ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('baseline super') : ems_tenant_db();
$CID  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_baseline.php', ($c > 0 ? ('&contract=' . $c) : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['bl_action'] ?? '') !== '') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['bl_action']);
    $cid = intval($_POST['contract_id'] ?? 0);
    if ($act === 'open') {
        $r = CBS::open($conn, $gate, $company_id, $cid, $uid);
        $redirect($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌'), $cid);
    }
    if ($act === 'state') {
        $r = CBS::transition($conn, $gate, $company_id, $cid, strval($_POST['to'] ?? ''), $uid,
                             strval($_POST['note'] ?? ''));
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'amend') {
        $r = CBS::amend($conn, $gate, $company_id, $cid, strval($_POST['note'] ?? ''),
                        intval($_POST['amendment_id'] ?? 0), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.second_party, c.contract_status, c.actual_start, c.actual_end
           FROM contracts c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC LIMIT 200");
} catch (\Throwable $t) { $contracts = array(); }

$cur  = $CID > 0 ? CBS::current($gate, $CID) : null;
$rd   = $CID > 0 ? CBS::readiness($gate, $CID) : array('ok' => false, 'components' => array(),
                                                       'gaps' => array(), 'counts' => array(), 'note' => '');
$vers = $CID > 0 ? CBS::versionsOf($gate, $CID) : array();
$gt   = $CID > 0 ? CBS::billingGate($gate, $CID) : null;
$MODE = CBS::gateMode();
$PILOT = CBS::pilotContracts();
$STATE_AR = CBS::STATE_AR;
$COMP = CBS::COMPONENTS;

$page_title = 'إيكوبيشن | خط أساس العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'baseline';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'خط أساس العقد'; $header_icon = 'fa fa-lock';
    $header_actions = array();
    $header_back = array('href' => 'plan_actual_link.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'ربط الخطة بالفعلي');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            <strong>عند القفل تُقفل كلُّ المكوّنات — ومن هنا فقط تبدأ الفوترة</strong>.
            و<strong>لا يُقفل خطُّ أساسٍ بفجوة</strong>: المكوّناتُ الستةُ تُعدّ وتُسمّى فجوتُها.
            و<strong>لا يعتمد خطَّ الأساس من راجعه</strong> — يدان لا يدٌ واحدة.
            <br>
            <strong>والبوابةُ تبدأ مطفأة</strong> (الملحق §2-②): القاعدةُ تسري على <strong>الجديد لا على القائم</strong>،
            والعقودُ القائمةُ <strong>تُفوتر كما هي</strong> — <strong>ولا تُقلب على الجميع دفعةً واحدة</strong>.
        </p>
        <div style="margin-top:10px">
            <span class="badge <?php echo $MODE === 'enforce' ? 'badge-warning'
                : ($MODE === 'monitor' ? 'badge-info' : 'badge-secondary'); ?>" style="padding:6px 12px">
                وضعُ البوابة: <strong><?php echo htmlspecialchars($MODE); ?></strong></span>
            <span class="badge badge-secondary" style="padding:6px 12px">
                العقودُ الرائدة: <?php echo $PILOT ? htmlspecialchars(implode(' · ', $PILOT)) : '<strong>لا شيء</strong>'; ?></span>
        </div>
    </div></div>

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
                        <i class="fa fa-lock"></i> خطُّ الأساس</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($CID > 0): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-list-check"></i>
        المكوّناتُ الستة — للعقد #<?php echo $CID; ?></h5></div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <span class="badge <?php echo $cur ? 'badge-info' : 'badge-warning'; ?>" style="padding:6px 12px">
                خطُّ الأساس: <strong><?php echo $cur
                    ? htmlspecialchars($STATE_AR[(string)$cur['state']]) . ' · نسخة ' . intval($cur['version'])
                    : 'غيرُ مفتوح'; ?></strong></span>
            <span class="badge <?php echo $rd['ok'] ? 'badge-success' : 'badge-warning'; ?>" style="padding:6px 12px">
                <?php echo $rd['ok'] ? 'المكوّناتُ مكتملة' : (count($rd['gaps']) . ' فجوة'); ?></span>
            <?php if ($gt !== null): ?>
                <span class="badge <?php echo $gt['allow'] ? 'badge-success' : 'badge-warning'; ?>"
                    style="padding:6px 12px">الفوترة:
                    <?php echo $gt['allow'] ? 'مسموحة' : '<strong>ممنوعة</strong>'; ?></span>
            <?php endif; ?>
        </div>
        <?php if ($gt !== null): ?>
            <p style="color:#6b7280"><small><?php
                echo htmlspecialchars(str_replace('**', '', (string)$gt['reason'])); ?></small></p>
        <?php endif; ?>

        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>المكوّن</th><th>الحال</th><th>العدّ</th></tr></thead>
            <tbody>
            <?php
            $countMap = array('lines' => 'lines', 'monthly_plan' => 'plan_months',
                              'plan_sealed' => 'plan_sealed', 'resource_plan' => 'resource_rows',
                              'payment_schedule' => 'payment_rows', 'sites' => 'sites');
            foreach ($COMP as $k => $label): $okc = !empty($rd['components'][$k]); ?>
                <tr<?php echo $okc ? '' : " style='background:#fff7ed'"; ?>>
                    <td><?php echo htmlspecialchars($label); ?></td>
                    <td><span class="badge <?php echo $okc ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $okc ? 'مكتمل' : 'فجوة'; ?></span></td>
                    <td><?php echo isset($rd['counts'][$countMap[$k]])
                        ? intval($rd['counts'][$countMap[$k]]) : '—'; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($rd['gaps']): ?>
            <ul style="margin-top:10px;color:#b45309">
            <?php foreach ($rd['gaps'] as $g): ?>
                <li><?php echo htmlspecialchars(str_replace('**', '', (string)$g)); ?></li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($can_edit): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <?php if (!$cur): ?>
            <form method="post"><input type="hidden" name="bl_action" value="open">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-folder-open"></i> افتح خطَّ الأساس</button>
            </form>
            <?php else: ?>
            <form method="post" style="display:flex;gap:6px;align-items:flex-end">
                <input type="hidden" name="bl_action" value="state">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <div class="form-group"><label for="emsf_48_99ab5">إلى حال</label>
                    <select name="to" id="emsf_48_99ab5">
                        <?php foreach ($STATE_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label for="emsf_49_f9441">ملاحظة/سبب</label>
                    <input type="text" name="note" maxlength="255" style="width:220px" id="emsf_49_f9441"></div>
                <button type="submit" class="btn-primary"><i class="fa fa-forward"></i> انتقل</button>
            </form>
            <form method="post" style="display:flex;gap:6px;align-items:flex-end">
                <input type="hidden" name="bl_action" value="amend">
                <input type="hidden" name="contract_id" value="<?php echo $CID; ?>">
                <div class="form-group"><label for="emsf_50_55026">سببُ الملحق <span style="color:#c00">*</span></label>
                    <input type="text" name="note" maxlength="255" required style="width:220px" id="emsf_50_55026"></div>
                <div class="form-group"><label for="emsf_51_164a9">رقمُ الملحق</label>
                    <input type="number" name="amendment_id" style="width:110px" id="emsf_51_164a9"></div>
                <button type="submit" class="btn-primary"><i class="fa fa-code-branch"></i>
                    ملحقٌ — <strong>نسخةٌ جديدةٌ والقديمةُ تبقى</strong></button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div></div>

    <?php if ($vers): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        النسخُ — <strong>والمُستبدَلةُ تبقى</strong></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>النسخة</th><th>الحال</th><th>راجع</th><th>اعتمد</th><th>أقفل</th>
                <th>البصمة</th><th>المكوّنات وقتَ القفل</th><th>السبب</th>
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
            <?php foreach ($vers as $v): ?>
                <tr><td><?php echo intval($v['version']); ?></td>
                    <td><span class="badge <?php echo (string)$v['state'] === 'locked'
                        ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars($STATE_AR[(string)$v['state']]); ?></span></td>
                    <td><?php echo htmlspecialchars((string)($v['reviewed_at'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($v['approved_at'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($v['locked_at'] ?? '—')); ?></td>
                    <td><small><?php echo htmlspecialchars(substr((string)($v['fingerprint'] ?? '—'), 0, 12)); ?></small></td>
                    <td><small>بند <?php echo intval($v['comp_lines']); ?> ·
                        شهر <?php echo intval($v['comp_plan_months']); ?> ·
                        مختوم <?php echo intval($v['comp_plan_sealed']); ?> ·
                        دفع <?php echo intval($v['comp_payment_rows']); ?> ·
                        نطاق <?php echo intval($v['comp_sites']); ?></small></td>
                    <td style="white-space:normal"><small><?php
                        echo htmlspecialchars((string)($v['state_note'] ?? '—')); ?></small></td></tr>
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

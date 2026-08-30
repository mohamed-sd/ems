<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_sites.php — نطاقاتُ العقد التشغيلية (P-01)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §2.1: «نطاقٌ داخل العقد **باسمه وتاريخه وبنوده**».
 * ولوحُ «عقودٌ بلا نطاق» يجعل الفجوةَ **ظاهرةً لا مضمَرة**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractSiteService.php';

use App\Services\Contract\ContractSiteService as CSS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_sites.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض نطاقات العقود ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('contract sites super') : ems_tenant_db();
$sel  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_sites.php', ($c > 0 ? '&contract=' . $c : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['cs_action'] ?? '') !== '') {
    $act = strval($_POST['cs_action']);
    $cid = intval($_POST['contract_id'] ?? 0);

    if ($act === 'add') {
        if (!$can_add) { $redirect('لا توجد صلاحية ❌', $cid); }
        $r = CSS::add($conn, $gate, $company_id, $cid, array(
            'site_id' => intval($_POST['site_id'] ?? 0),
            'scope_name' => strval($_POST['scope_name'] ?? ''),
            'start_date' => strval($_POST['start_date'] ?? ''),
            'end_date' => strval($_POST['end_date'] ?? ''),
            'state' => strval($_POST['state'] ?? 'active'),
            'is_primary' => isset($_POST['is_primary']) ? 1 : 0,
            'note' => strval($_POST['note'] ?? ''),
        ), $uid);
        $redirect($r['ok'] ? 'أضيف النطاق ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'primary') {
        if (!$can_edit) { $redirect('لا توجد صلاحية ❌', $cid); }
        $r = CSS::setPrimary($conn, $gate, $company_id, intval($_POST['scope_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'نقلت الرئاسة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'close') {
        if (!$can_edit) { $redirect('لا توجد صلاحية ❌', $cid); }
        $r = CSS::close($conn, $gate, $company_id, intval($_POST['scope_id'] ?? 0),
                        strval($_POST['close_reason'] ?? ''), $uid);
        $redirect($r['ok'] ? 'أقفل النطاق بسببه ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

$contracts = CSS::contracts($gate);
$noScope   = CSS::contractsWithoutScope($gate);
$scopes    = $sel > 0 ? CSS::scopesOf($gate, $sel) : array();
$head      = null;
foreach ($contracts as $c) { if ((int) $c['id'] === $sel) { $head = $c; } }
$sites     = ($head !== null) ? CSS::sites($gate) : array();

$page_title = 'إيكوبيشن | نطاقات العقد التشغيلية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'sites';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'نطاقات العقد التشغيلية'; $header_icon = 'fa fa-map-location-dot';
    $header_actions = array();
    $header_back = array('href' => 'contracts.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'العقود');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا
    echo ems_states_bundle('لا نطاقات تشغيلية مسجلة على العقد المختار',
                           'اختر عقدا من جدول العقود أعلاه ثم أضف نطاقا بنموذج «إضافة نطاق»');
    ?>

    <style>
    .cs-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0 0 10px}
    .cs-badge-pad{padding:6px 12px}
    .cs-table{width:100%}
    .cs-ltr{direction:ltr}
    .cs-muted{color:var(--c-ink-500)}
    .cs-wrap{white-space:normal}
    .cs-inline{display:inline}
    .cs-chip-btn{border:0;padding:5px 8px}
    .cs-close-form{display:flex;gap:4px;margin-top:4px}
    .cs-w130{width:130px}
    .cs-add-form{margin-top:14px}
    .cs-req{color:var(--c-state-danger-strong)}
    .cs-check-label{display:flex;align-items:center;gap:8px}
    .cs-check{width:auto}
    .cs-actions{margin-top:12px}
    </style>

    <div class="card"><div class="card-body">
        <p class="cs-note">
            <i class="fas fa-circle-info"></i>
            <strong>نطاق العقد التشغيلي</strong> مفهوم بأربعة أبعاد (اسم · مدة · موقع · بنود) —
            وكان مختزلا في عمود واحد يشير إلى موقع واحد، فعقد يعمل في موقعين
            <strong>لا يمكن تمثيله أصلا</strong>. والقواعد:
            <strong>النطاق داخل مدة عقده</strong> · <strong>والموقع مرة واحدة</strong> ·
            <strong>ورئيس واحد على الأكثر</strong> · <strong>والإقفال بسبب مكتوب</strong>.
        </p>
        <?php if ($noScope): ?>
            <span class="badge badge-danger cs-badge-pad">
                <i class="fas fa-triangle-exclamation"></i>
                <?php echo count($noScope); ?> عقدا <strong>بلا نطاق تشغيلي</strong></span>
        <?php else: ?>
            <span class="badge badge-success cs-badge-pad">
                <i class="fas fa-check"></i> صفر عقد بلا نطاق</span>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable cs-table" data-no-dt="1">
            <thead><tr><th>#</th><th>المشروع</th><th>الطرف الثاني</th><th>المدة</th>
                <th>الحال</th><th>النطاقات</th><th></th>
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
            <?php foreach ($contracts as $c):
                $n = count(CSS::scopesOf($gate, (int) $c['id'])); ?>
                <tr><td><?php echo intval($c['id']); ?></td>
                    <td>#<?php echo intval($c['project_id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['second_party'] ?? '—')); ?></td>
                    <td class="cs-ltr"><?php echo htmlspecialchars((string)($c['actual_start'] ?? '…')); ?>
                        → <?php echo htmlspecialchars((string)($c['actual_end'] ?? '…')); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['contract_status'] ?? '—')); ?></td>
                    <td><span class="badge <?php echo $n > 0 ? 'badge-success' : 'badge-danger'; ?>"><?php echo $n; ?></span></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-eye"></i> افتح</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($head): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-map-location-dot"></i>
        نطاقات العقد #<?php echo $sel; ?></h5></div>
    <div class="card-body">
        <div class="table-container">
        <table class="alltables display nowrap no-datatable cs-table" data-no-dt="1">
            <thead><tr><th>#</th><th>الاسم</th><th>الموقع</th><th>المدة</th><th>الحال</th>
                <th>رئيسي</th><?php if ($can_edit) echo '<th>إجراء</th>'; ?></tr></thead>
            <tbody>
            <?php foreach ($scopes as $s): ?>
                <tr><td><?php echo intval($s['id']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$s['scope_name']); ?></strong>
                        <?php if ($s['close_reason'] !== null && $s['close_reason'] !== ''): ?>
                            <div><small class="cs-muted"><?php echo htmlspecialchars((string)$s['close_reason']); ?></small></div>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)($s['site_name'] ?? ('#' . intval($s['site_id'])))); ?>
                        <small>(<?php echo (string)$s['site_kind'] === 'mine' ? 'منجم' : 'موقع'; ?>)</small></td>
                    <td class="cs-ltr"><?php echo htmlspecialchars((string)($s['start_date'] ?? '…')); ?>
                        → <?php echo htmlspecialchars((string)($s['end_date'] ?? '…')); ?></td>
                    <td><span class="badge <?php echo (string)$s['state'] === 'active' ? 'badge-success'
                        : ((string)$s['state'] === 'closed' ? 'badge-secondary' : 'badge-warning'); ?>">
                        <?php echo htmlspecialchars(CSS::labelAr((string)$s['state'])); ?></span></td>
                    <td><?php echo intval($s['is_primary']) === 1
                        ? '<span class="badge badge-info">رئيسي</span>' : '—'; ?></td>
                    <?php if ($can_edit): ?>
                    <td class="cs-wrap">
                        <?php if (intval($s['is_primary']) !== 1 && (string)$s['state'] !== 'closed'): ?>
                            <form method="post" class="cs-inline">
        <?php echo csrf_field(); ?>
                                <input type="hidden" name="cs_action" value="primary">
                                <input type="hidden" name="contract_id" value="<?php echo $sel; ?>">
                                <input type="hidden" name="scope_id" value="<?php echo intval($s['id']); ?>">
                                <button type="submit" class="badge badge-info cs-chip-btn">اجعله رئيسيا</button>
                            </form>
                        <?php endif; ?>
                        <?php if ((string)$s['state'] !== 'closed'): ?>
                            <form method="post" class="cs-close-form">
        <?php echo csrf_field(); ?>
                                <input type="hidden" name="cs_action" value="close">
                                <input type="hidden" name="contract_id" value="<?php echo $sel; ?>">
                                <input type="hidden" name="scope_id" value="<?php echo intval($s['id']); ?>">
                                <input type="text" name="close_reason" required maxlength="200"
                                       placeholder="سبب الإقفال" class="cs-w130" aria-label="سبب الإقفال">
                                <button type="submit" class="badge badge-danger cs-chip-btn">أقفل</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$scopes): ?><tr><td colspan="7"><em>لا نطاقات — والعقد بلا نطاق لا يسند إليه عمل</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($can_add): ?>
        <form method="post" class="ems-form cs-add-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="cs_action" value="add">
            <input type="hidden" name="contract_id" value="<?php echo $sel; ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_104_49f39">الموقع <span class="cs-req">*</span></label>
                    <select name="site_id" required id="emsf_104_49f39">
                        <?php foreach ($sites as $s): ?>
                            <option value="<?php echo intval($s['id']); ?>">
                                <?php echo htmlspecialchars((string)$s['name']); ?>
                                (<?php echo (string)$s['site_kind'] === 'mine' ? 'منجم' : 'موقع'; ?>)</option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label for="emsf_105_4b108">اسم النطاق <small>— فارغ = اسم الموقع</small></label>
                    <input type="text" name="scope_name" maxlength="190" id="emsf_105_4b108"></div>
                <div class="form-group"><label for="emsf_106_1593e">من تاريخ</label><input type="date" name="start_date" id="emsf_106_1593e"></div>
                <div class="form-group"><label for="emsf_107_66d13">إلى تاريخ</label><input type="date" name="end_date" id="emsf_107_66d13"></div>
                <div class="form-group"><label for="emsf_108_4d681">الحالة</label>
                    <select name="state" id="emsf_108_4d681"><option value="active">نافذ</option>
                        <option value="planned">مخطط</option><option value="paused">موقوف</option></select></div>
                <div class="form-group"><label class="cs-check-label" for="emsf_109_b3899">
                    <input type="checkbox" name="is_primary" aria-label="اجعله النطاق الرئيسي للعقد" value="1" class="cs-check"> نطاق رئيسي</label></div>
                <div class="form-group"><label>ملاحظة</label></div>
                <div class="form-group"><label>ملاحظة</label><input type="text" name="note" maxlength="200" id="emsf_109_b3899"></div>
            </div>
            <div class="cs-actions"><button type="submit" class="btn-primary">
                <i class="fa fa-plus"></i> أضف نطاقا</button></div>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

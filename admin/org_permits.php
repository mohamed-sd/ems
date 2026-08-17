<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * admin/org_permits.php — شاشة أذونات المواقع (update0004 · ORG-17)
 * ───────────────────────────────────────────────────────────────────────────
 * ORG-01 §5: مصفوفة الأذونات المشتركة — كل إذن بموافقيه المحددين بالترتيب،
 * ولا يُنفَّذ منفردًا إذا تطلّب مشتركًا. القرار عبر PermitGate بحرّاسه
 * (409 تسلسل · 403 تفويض · 423 سريان).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once __DIR__ . '/../app/Services/Org/PermitGate.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Org\PermitGate as PG;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$company_id = ems_scope_company($conn);

$MODULE_CODE = 'admin/org_permits.php';
$can_view = $is_super_admin; $can_add = $is_super_admin; $can_edit = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = intval($row['can_view']) === 1;
        $can_add = intval($row['can_add']) === 1;
        $can_edit = intval($row['can_edit']) === 1;
    }
    $st->close();
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لشاشة الأذونات ❌', 'GOV-PERM-403', ''); exit(); }

$msg = strval($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permit_action'])) {
    $act = strval($_POST['permit_action']);
    if ($act === 'request' && $can_add) {
        $r = PG::request($conn, array(
            'company_id' => $company_id,
            'permit_type_code' => strval($_POST['permit_type_code'] ?? ''),
            'subject_ref' => strval($_POST['subject_ref'] ?? ''),
            'site_id' => intval($_POST['site_id'] ?? 0),
            'requested_by' => $uid,
            'reason' => strval($_POST['reason'] ?? '') ?: null,
            'doc_ref' => strval($_POST['doc_ref'] ?? '') ?: null,
        ));
        ems_gov_redirect("Location: org_permits.php?id=" . intval($r['req_id']) . "&msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    }
    if (($act === 'approve' || $act === 'reject') && $can_edit) {
        $r = PG::approve($conn, intval($_POST['req_id'] ?? 0), $uid,
            $act === 'reject' ? 'reject' : 'approve', strval($_POST['reason'] ?? '') ?: null);
        ems_gov_redirect("Location: org_permits.php?id=" . intval($_POST['req_id'] ?? 0) . "&msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    }
    ems_gov_flash_redirect('org_permits.php', 'لا صلاحية ❌', 'GOV-PERM-403', '');
    exit();
}

$ptypes = array();
$r = $conn->query("SELECT permit_type_code, name_ar FROM permit_types WHERE active=1 ORDER BY permit_type_code");
while ($r && ($x = $r->fetch_assoc())) { $ptypes[] = $x; }
$sitesList = array();
$r = $conn->query("SELECT id, name FROM sites WHERE company_id={$company_id} AND is_deleted=0 ORDER BY name");
while ($r && ($x = $r->fetch_assoc())) { $sitesList[] = $x; }

$rows = array();
$r = $conn->query(
    "SELECT r.req_id, r.subject_ref, r.state, r.valid_until, r.created_at, r.site_id,
            t.name_ar type_name, s.name site_name, u.name requester
       FROM permit_requests r
       JOIN permit_types t ON t.permit_type_code = r.permit_type_code
       LEFT JOIN sites s ON s.id = r.site_id
       LEFT JOIN users u ON u.id = r.requested_by
      WHERE r.company_id = {$company_id}
      ORDER BY (r.state='pending') DESC, r.req_id DESC LIMIT 300");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$detail = null; $steps = array();
$detailId = intval($_GET['id'] ?? 0);
if ($detailId > 0) {
    $detail = PG::fetch($conn, $detailId);
    if ($detail && intval($detail['company_id']) === $company_id) {
        $steps = PG::steps($conn, $detailId);
    } else { $detail = null; }
}

$stateLabels = array('draft' => 'مسودة', 'pending' => 'بانتظار الموافقات', 'approved' => 'ساري',
    'rejected' => 'مرفوض', 'expired' => 'منتهي المدة', 'used' => 'مستعمَل');

$page_title = 'إيكوبيشن | أذونات المواقع';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'أذونات المواقع — الدخول والخروج والتفعيل والإيقاف'; $header_icon = 'fa fa-key';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'طلب إذن');
    }
    include('../includes/page_header.php');

    ems_screen_about(
        'كل دخول أو خروج أو تفعيل أو إيقاف له إذن بموافقين محددين بالترتيب (ORG-01 §5) — '
        . 'يمرّ بصندوق الاعتماد الجامع بندًا واحدًا، وما نُفّذ منفردًا وهو يتطلب مشتركًا يُرفض بنيويًّا.',
        array('اطلب الإذن قبل الفعل — الحارس يفحصه عند التنفيذ',
              'الموافقات بترتيبها: خطوة قبل سابقتها تُرفض 409',
              'الإذن الساري يُستهلك مرة واحدة، والمنتهي يُطلب تجديده (423)'));

    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا طلباتِ أذوناتٍ مسجَّلةً بعدُ',
        'اطلب إذنًا جديدًا من زرِّ «طلب إذن» — والإذنُ يسبق الفعلَ دائمًا');
    ?>

    <?php if ($can_add): ?>
    <form method="post" action="org_permits.php" class="allforms" id="permitForm">
        <input type="hidden" name="permit_action" value="request">
        <h5>طلب إذن جديد</h5>
        <div class="form-row orgprm-grid4">
            <div class="form-group"><label for="emsf_1977_c41c7">نوع الإذن *</label>
                <select name="permit_type_code" required id="emsf_1977_c41c7"><option value="">— اختر —</option>
                    <?php foreach ($ptypes as $t) { echo '<option value="' . htmlspecialchars($t['permit_type_code']) . '">' . htmlspecialchars($t['name_ar']) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label for="emsf_1978_03e31">مرجع الموضوع * (EQ:12 · EMP:7 …)</label>
                <input type="text" name="subject_ref" required placeholder="EQ:12" id="emsf_1978_03e31"></div>
            <div class="form-group"><label for="emsf_1979_549da">الموقع *</label>
                <select name="site_id" required id="emsf_1979_549da"><option value="">— اختر —</option>
                    <option value="0">مركزي (مخزن/شركة)</option>
                    <?php foreach ($sitesList as $s) { echo '<option value="' . intval($s['id']) . '">' . htmlspecialchars($s['name']) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label for="emsf_1980_a6baa">السبب</label><input type="text" name="reason" id="emsf_1980_a6baa"></div>
            <div class="form-group"><label for="emsf_1981_dfd26">مستند مرجعي</label><input type="text" name="doc_ref" id="emsf_1981_dfd26"></div>
        </div>
        <button type="submit" class="btn-primary">رفع الطلب</button>
    </form>
    <?php endif; ?>

    <?php if ($detail): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-key"></i>
        الإذن #<?php echo intval($detail['req_id']); ?> — <?php echo htmlspecialchars($stateLabels[$detail['state']] ?? $detail['state']); ?>
        <?php if ($detail['valid_until']): ?> · ساري حتى <?php echo htmlspecialchars($detail['valid_until']); ?><?php endif; ?>
    </h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap orgprm-w100" data-no-dt="1">
            <thead><tr><th>الخطوة</th><th>الدور الموافق</th><th>إلزامية</th><th>القرار</th><th>بواسطة</th><th>متى</th><th></th></tr></thead>
            <tbody>
            <?php $nextOpen = true; foreach ($steps as $s): ?>
                <tr>
                    <td><?php echo intval($s['seq_no']); ?></td>
                    <td><?php echo htmlspecialchars($s['approver_role']); ?></td>
                    <td><?php echo intval($s['mandatory']) === 1 ? 'نعم' : 'لا'; ?></td>
                    <td><?php echo $s['decision'] === null ? '—' : ($s['decision'] === 'approve' ? '✅ وافق' : '❌ رفض'); ?></td>
                    <td><?php echo $s['approver_person_id'] ? intval($s['approver_person_id']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($s['at'] ?: '—'); ?></td>
                    <td>
                    <?php if ($can_edit && $detail['state'] === 'pending' && $s['decision'] === null && $nextOpen): $nextOpen = false; ?>
                        <form method="post" class="orgprm-inline">
                            <input type="hidden" name="permit_action" value="approve">
                            <input type="hidden" name="req_id" value="<?php echo intval($detail['req_id']); ?>">
                            <button type="submit" class="btn-primary">أوافق (بدوري)</button>
                        </form>
                        <form method="post" class="orgprm-inline">
                            <input type="hidden" name="permit_action" value="reject">
                            <input type="hidden" name="req_id" value="<?php echo intval($detail['req_id']); ?>">
                            <input type="hidden" name="reason" value="رفض من الشاشة">
                            <button type="submit" class="action-btn delete">أرفض</button>
                        </form>
                    <?php elseif ($s['decision'] === null && !$nextOpen): echo '<small class="orgprm-muted">بانتظار ما قبلها</small>'; endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body"><div class="table-container">
        <table class="alltables display nowrap orgprm-w100">
            <thead><tr><th>#</th><th>نوع الإذن</th><th>الموضوع</th><th>الموقع</th><th>الطالب</th>
                <th>الحالة</th><th>ساري حتى</th><th>أُنشئ</th><th></th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم الإذن</th>
                <th class="ems-fn-th" data-fn="1">المستفيد</th>
                <th class="ems-fn-th" data-fn="1">نوع المستفيد</th>
                <th class="ems-fn-th" data-fn="1">الغرض</th>
                <th class="ems-fn-th" data-fn="1">المرافقون</th>
                <th class="ems-fn-th" data-fn="1">المركبة</th>
                <th class="ems-fn-th" data-fn="1">رقم اللوحة</th>
                <th class="ems-fn-th" data-fn="1">من تاريخ</th>
                <th class="ems-fn-th" data-fn="1">إلى تاريخ</th>
                <th class="ems-fn-th" data-fn="1">جهة الإصدار</th>
                <th class="ems-fn-th" data-fn="1">المصادقة الأمنية</th>
                <th class="ems-fn-th" data-fn="1">أصدره</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><?php echo intval($x['req_id']); ?></td>
                    <td><?php echo htmlspecialchars($x['type_name']); ?></td>
                    <td><?php echo htmlspecialchars($x['subject_ref']); ?></td>
                    <td><?php echo htmlspecialchars($x['site_name'] ?: ($x['site_id'] ? ('#' . $x['site_id']) : 'مركزي')); ?></td>
                    <td><?php echo htmlspecialchars($x['requester'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($stateLabels[$x['state']] ?? $x['state']); ?></td>
                    <td><?php echo htmlspecialchars($x['valid_until'] ?: '—'); ?></td>
                    <td><small><?php echo htmlspecialchars($x['created_at']); ?></small></td>
                    <td><a class="btn-primary" href="?id=<?php echo intval($x['req_id']); ?>">الموافقات ▸</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<style>
    /* UXW-01 ①+②: أصنافٌ محلَّ الأنماطِ الموضعية — والألوانُ برموزِ اللوحة */
    .orgprm-grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .orgprm-w100 { width: 100%; }
    .orgprm-inline { display: inline; }
    .orgprm-muted { color: var(--c-s-888); }
</style>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
document.getElementById('toggleForm')?.addEventListener('click', function () {
    document.getElementById('permitForm')?.classList.toggle('allforms-visible');
});
</script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Portal/visibility_keys.php — مفاتيحُ الظهور (H-16 · الشاشة 183)
 * ───────────────────────────────────────────────────────────────────────────
 * ADM-01 §3-①: «شبكةُ (عنصر × نطاق) بثلاث حالات · معاينةُ الأثر قبل الحفظ:
 * "سيتأثر N حسابًا" · سببٌ إلزاميٌّ لكل تغييرٍ حساس».
 * المالك: الموارد البشرية / شؤون الموظفين (4) — **تمنح ظهورًا لا صلاحيةَ عمل**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/VisibilityPolicyService.php';

use App\Services\Portal\VisibilityPolicyService as VPS;
use App\Services\Portal\CapacityService as CAP;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$MODULE_CODE = 'Portal/visibility_keys.php';
$can_view = $can_edit = false;
if ($is_super_admin) { $can_view = $can_edit = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = intval($row['can_view']) === 1;
        $can_edit = intval($row['can_edit']) === 1;
    }
    $st->close();
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحيةَ عرضٍ لمفاتيح الظهور ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('visibility keys super') : ems_tenant_db();
$redirect = function ($msg) { ems_gov_flash_redirect('visibility_keys.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['vk_action'] ?? '') === 'set') {
    if (!$can_edit) { $redirect('لا صلاحيةَ ضبطٍ — المفاتيحُ لشؤون الموظفين ❌'); }
    $r = VPS::setKey($conn, $gate, $company_id, array(
        'element_code' => strval($_POST['element_code'] ?? ''),
        'scope_type'   => strval($_POST['scope_type'] ?? ''),
        'scope_id'     => strval($_POST['scope_id'] ?? ''),
        'mode'         => strval($_POST['mode'] ?? ''),
        'reason'       => strval($_POST['reason'] ?? ''),
        'expires_at'   => strval($_POST['expires_at'] ?? ''),
    ), $uid);
    $redirect($r['ok'] ? ('ضُبط المفتاحُ — سيتأثر ' . $r['affected'] . ' حسابًا ✅')
                       : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

$elements = VPS::elements($conn);
$keys = VPS::keys($gate, 500);

$page_title = 'إيكوبيشن | مفاتيح الظهور';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مفاتيح الظهور'; $header_icon = 'fa fa-key';
    $header_actions = array();
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا مفاتيحَ ظهورٍ مضبوطةً بعدُ', 'اضبط أولَ مفتاحٍ من نموذجِ «ضبطُ مفتاح» أعلاه — ويُعلَن عددُ المتأثرين قبلَ الحفظ');
    ?>

    <style>
    .vkey-note      { color: var(--c-s-666); }
    .vkey-table     { width: 100%; }
    .vkey-form-foot { margin-top: 10px; }
    </style>

    <div class="card"><div class="card-body"><p class="vkey-note">
        المفاتيحُ <strong>تمنح ظهورًا لا صلاحيةَ عمل</strong> — بستة نطاقاتٍ وأولويةٍ محسومة:
        <strong>الحسابُ يغلب الفئةَ</strong> والفئةُ تغلب الإدارة/المشروع وهذه تغلب المورد/العميل،
        وما لم يُضبط <strong>موروثٌ</strong> وما لا سياسةَ له على <strong>افتراض عنصره</strong>
        (والحساسُ مغلق). <strong>الحساسُ لا يُفتح إلا بمدةٍ وسبب</strong> — ولا منحَ للذات.
    </p></div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-sliders"></i> ضبطُ مفتاح</h5></div>
    <div class="card-body">
        <form method="post" class="ems-form">
        <?= csrf_field() ?>
            <input type="hidden" name="vk_action" value="set">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_1249_f8679">العنصر *</label>
                    <select name="element_code" required id="emsf_1249_f8679">
                        <?php foreach ($elements as $e): ?>
                            <option value="<?php echo htmlspecialchars($e['element_code']); ?>">
                                <?php echo htmlspecialchars($e['title_ar'] . ' (' . $e['element_code'] . ')'
                                    . ((string)$e['sensitivity'] === 'sensitive' ? ' — حساس' : '')); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="emsf_1250_a823d">نوع النطاق *</label>
                    <select name="scope_type" required id="emsf_1250_a823d">
                        <option value="account">حسابٌ بعينه</option>
                        <option value="capacity_type">فئةُ صفة (H-15)</option>
                        <option value="department">إدارة</option>
                        <option value="project">مشروع</option>
                        <option value="supplier">مورد</option>
                        <option value="client">عميل</option>
                    </select></div>
                <div class="form-group"><label for="emsf_1251_463b4">معرّف النطاق *
                    <span class="mnt-req-hint">(رقمٌ — أو كودُ الفئة مثل operator)</span></label>
                    <input type="text" name="scope_id" required id="emsf_1251_463b4"></div>
                <div class="form-group"><label for="emsf_1252_70802">الوضع *</label>
                    <select name="mode" required id="emsf_1252_70802">
                        <option value="open">مفتوح</option>
                        <option value="closed">مغلق</option>
                        <option value="inherit">موروث</option>
                    </select></div>
                <div class="form-group"><label for="emsf_1253_e95e8">السبب <span class="mnt-req-hint">(إلزاميٌّ لغير الموروث)</span></label>
                    <input type="text" name="reason" maxlength="255" id="emsf_1253_e95e8"></div>
                <div class="form-group"><label for="emsf_1254_03a5c">ينتهي في <span class="mnt-req-hint">(إلزاميٌّ لفتح الحساس)</span></label>
                    <input type="datetime-local" name="expires_at" id="emsf_1254_03a5c"></div>
            </div>
            <div class="vkey-form-foot"><button type="submit" class="btn-primary">
                <i class="fa fa-check"></i> احفظ — وسيُعلَن عددُ المتأثرين</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> المفاتيحُ المضبوطة (<?php echo count($keys); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap vkey-table">
            <thead><tr><th>العنصر</th><th>النطاق</th><th>الوضع</th><th>السبب</th>
                <th>الفاعل</th><th>ينتهي</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم المفتاح</th>
                <th class="ems-fn-th" data-fn="1">الشاشة</th>
                <th class="ems-fn-th" data-fn="1">الملف</th>
                <th class="ems-fn-th" data-fn="1">الإدارة المالكة</th>
                <th class="ems-fn-th" data-fn="1">الدور المستفيد</th>
                <th class="ems-fn-th" data-fn="1">الإدارة العارضة</th>
                <th class="ems-fn-th" data-fn="1">الزاوية</th>
                <th class="ems-fn-th" data-fn="1">الأعمدة المعروضة</th>
                <th class="ems-fn-th" data-fn="1">الفلاتر الافتراضية</th>
                <th class="ems-fn-th" data-fn="1">الأفعال المسموحة</th>
                <th class="ems-fn-th" data-fn="1">الأفعال المحجوبة</th>
                <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
                <th class="ems-fn-th" data-fn="1">أنشأه</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                </tr></thead>
            <tbody>
            <?php foreach ($keys as $k): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$k['element_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($k['scope_type'] . ' × ' . $k['scope_id']); ?></td>
                    <td><?php $m = (string)$k['mode'];
                        echo $m === 'open' ? "<span class='badge badge-success'>مفتوح</span>"
                           : ($m === 'closed' ? "<span class='badge badge-danger'>مغلق</span>"
                                              : "<span class='badge badge-secondary'>موروث</span>"); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($k['reason'] ?? '')); ?></small></td>
                    <td>#<?php echo intval($k['granted_by']); ?>
                        <small><?php echo htmlspecialchars((string)$k['granted_at']); ?></small></td>
                    <td><?php echo $k['expires_at'] !== null
                        ? htmlspecialchars((string)$k['expires_at']) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

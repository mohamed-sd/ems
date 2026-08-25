<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-685 · SCN-687 · SCN-689 · SCN-690
/**
 * Portal/portal_elements.php — مكوّناتُ البوابة (H-16 · الشاشة 184)
 * ───────────────────────────────────────────────────────────────────────────
 * ADM-01 §3-②: «قاموسُ العناصر بأكوادها ومالكيها وحساسيتها · تفعيلٌ وإيقافٌ
 * — **بلا أي بياناتٍ شخصية**».
 * المالك: مديرُ البوابة (15) — **فصلُ الواجبات**: يملك القاموسَ ولا يفتح
 * عنصرًا لحسابٍ ولا يرى بياناتِ أحد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../app/Services/Portal/VisibilityPolicyService.php';

use App\Services\Portal\VisibilityPolicyService as VPS;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$MODULE_CODE = 'Portal/portal_elements.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية عرض لمكونات البوابة ❌', 'GOV-PERM-403', ''); exit(); }

$redirect = function ($msg) { ems_gov_flash_redirect('portal_elements.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pe_action'] ?? '') === 'toggle') {
    if (!$can_edit) { $redirect('القاموس لمدير البوابة وحده ❌'); }
    $code = strval($_POST['element_code'] ?? '');
    $el = VPS::element($conn, $code);
    if (!$el) { $redirect('العنصر غير موجود في القاموس ❌'); }
    $to = intval($el['active']) === 1 ? 0 : 1;
    $st = $conn->prepare("UPDATE portal_elements SET active = ? WHERE element_code = ?");
    $st->bind_param('is', $to, $code);
    $ok = $st->execute();
    $st->close();
    require_once __DIR__ . '/../includes/audit_trail.php';
    ems_audit_change($conn, 'portal', 'portal_elements', $to ? 'activate' : 'deactivate', 0,
        array('active' => intval($el['active'])), array('active' => $to, 'element' => $code),
        array('company_id' => $company_id, 'user_id' => $uid));
    $redirect($ok ? ($to ? 'فعل المكون ✅' : 'أوقف المكون — لن يصير لأحد ✅') : 'تعذر التبديل ❌');
}

$elements = VPS::elements($conn);

$page_title = 'إيكوبيشن | مكونات البوابة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مكونات البوابة (القاموس)'; $header_icon = 'fa fa-puzzle-piece';
    $header_actions = array();
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا عناصر مسجلة في قاموس البوابة', 'يسجل العنصر بكوده ووثيقته المالكة قبل أن يصير في أي شاشة');
    ?>

    <style>
    .pel-note   { color: var(--c-s-666); }
    .pel-table  { width: 100%; }
    .pel-inline { display: inline; }
    </style>

    <div class="card"><div class="card-body"><p class="pel-note">
        <strong>كل ما يمكن إظهاره له كود واحد في قاموس واحد — وما ليس في القاموس
        لا يصير أصلا.</strong> هذه الشاشة للقاموس وحده <strong>بلا أي بيانات شخصية</strong>؛
        وفتح العناصر وإغلاقها بيته <a href="visibility_keys.php">مفاتيح الظهور</a>
        (فصل واجبات: من يملك المحتوى لا يرى البيانات).
    </p></div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-book"></i> القاموس (<?php echo count($elements); ?> عنصرا)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap pel-table">
            <thead><tr><th>الكود</th><th>الاسم</th><th>الوثيقة المالكة</th>
                <th>الحساسية</th><th>الافتراض</th><th>الحال</th><th></th>
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
            <?php foreach ($elements as $e): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string)$e['element_code']); ?></code></td>
                    <td><?php echo htmlspecialchars((string)$e['title_ar']); ?></td>
                    <td><?php echo htmlspecialchars((string)$e['owner_doc']); ?></td>
                    <td><?php echo (string)$e['sensitivity'] === 'sensitive'
                        ? "<span class='badge badge-danger'>حساس — بمدة وسبب</span>"
                        : "<span class='badge badge-secondary'>عادي</span>"; ?></td>
                    <td><?php echo (string)$e['default_mode'] === 'open'
                        ? "<span class='badge badge-success'>مفتوح</span>"
                        : "<span class='badge badge-danger'>مغلق</span>"; ?></td>
                    <td><?php echo intval($e['active']) === 1
                        ? "<span class='badge badge-success'>فعال</span>"
                        : "<span class='badge badge-secondary'>موقوف — لا يصير</span>"; ?></td>
                    <td><?php if ($can_edit): ?>
                        <form method="post" class="pel-inline">
        <?= csrf_field() ?>
                            <input type="hidden" name="pe_action" value="toggle">
                            <input type="hidden" name="element_code" value="<?php echo htmlspecialchars((string)$e['element_code']); ?>">
                            <button type="submit" class="btn-primary">
                                <?php echo intval($e['active']) === 1 ? 'أوقفه' : 'فعله'; ?></button>
                        </form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

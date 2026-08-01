<?php
/**
 * Portal/visibility_keys.php — مفاتيحُ الظهور (H-16 · الشاشة 183)
 * ───────────────────────────────────────────────────────────────────────────
 * ADM-01 §3-①: «شبكةُ (عنصر × نطاق) بثلاث حالات · معاينةُ الأثر قبل الحفظ:
 * "سيتأثر N حسابًا" · سببٌ إلزاميٌّ لكل تغييرٍ حساس».
 * المالك: الموارد البشرية / شؤون الموظفين (4) — **تمنح ظهورًا لا صلاحيةَ عمل**.
 */
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
if (!$can_view) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحيةَ عرضٍ لمفاتيح الظهور ❌')); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('visibility keys super') : ems_tenant_db();
$redirect = function ($msg) { header("Location: visibility_keys.php?msg=" . rawurlencode($msg)); exit(); };

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
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مفاتيح الظهور'; $header_icon = 'fa fa-key';
    $header_actions = array();
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body"><p style="color:#666">
        المفاتيحُ <strong>تمنح ظهورًا لا صلاحيةَ عمل</strong> — بستة نطاقاتٍ وأولويةٍ محسومة:
        <strong>الحسابُ يغلب الفئةَ</strong> والفئةُ تغلب الإدارة/المشروع وهذه تغلب المورد/العميل،
        وما لم يُضبط <strong>موروثٌ</strong> وما لا سياسةَ له على <strong>افتراض عنصره</strong>
        (والحساسُ مغلق). <strong>الحساسُ لا يُفتح إلا بمدةٍ وسبب</strong> — ولا منحَ للذات.
    </p></div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-sliders"></i> ضبطُ مفتاح</h5></div>
    <div class="card-body">
        <form method="post" class="ems-form">
            <input type="hidden" name="vk_action" value="set">
            <div class="form-grid">
                <div class="form-group"><label>العنصر *</label>
                    <select name="element_code" required>
                        <?php foreach ($elements as $e): ?>
                            <option value="<?php echo htmlspecialchars($e['element_code']); ?>">
                                <?php echo htmlspecialchars($e['title_ar'] . ' (' . $e['element_code'] . ')'
                                    . ((string)$e['sensitivity'] === 'sensitive' ? ' — حساس' : '')); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>نوع النطاق *</label>
                    <select name="scope_type" required>
                        <option value="account">حسابٌ بعينه</option>
                        <option value="capacity_type">فئةُ صفة (H-15)</option>
                        <option value="department">إدارة</option>
                        <option value="project">مشروع</option>
                        <option value="supplier">مورد</option>
                        <option value="client">عميل</option>
                    </select></div>
                <div class="form-group"><label>معرّف النطاق *
                    <span class="mnt-req-hint">(رقمٌ — أو كودُ الفئة مثل operator)</span></label>
                    <input type="text" name="scope_id" required></div>
                <div class="form-group"><label>الوضع *</label>
                    <select name="mode" required>
                        <option value="open">مفتوح</option>
                        <option value="closed">مغلق</option>
                        <option value="inherit">موروث</option>
                    </select></div>
                <div class="form-group"><label>السبب <span class="mnt-req-hint">(إلزاميٌّ لغير الموروث)</span></label>
                    <input type="text" name="reason" maxlength="255"></div>
                <div class="form-group"><label>ينتهي في <span class="mnt-req-hint">(إلزاميٌّ لفتح الحساس)</span></label>
                    <input type="datetime-local" name="expires_at"></div>
            </div>
            <div style="margin-top:10px"><button type="submit" class="btn-save">
                <i class="fa fa-check"></i> احفظ — وسيُعلَن عددُ المتأثرين</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> المفاتيحُ المضبوطة (<?php echo count($keys); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>العنصر</th><th>النطاق</th><th>الوضع</th><th>السبب</th>
                <th>الفاعل</th><th>ينتهي</th></tr></thead>
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

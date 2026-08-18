<?php
/** بوابة الطلب المالي D05 — صندوق الإدارة: المراجعة (توصية/إعادة) والاعتماد الإداري/الرفض (§3.1) */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = strval($_SESSION['user']['role']);
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin dept inbox super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/dept_inbox.php');
if (!$is_super && !$__pp['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا صلاحية', 'GOV-PERM-403', '');
    exit();
}

// إدارات هذا الدور (مراجعًا أو معتمدًا) من التوجيه المفعّل
$routing_all = finreq_active_routing($gate);
$my_routes = array();
foreach ($routing_all as $rt) {
    if ($is_super || finreq_role_is($rt, $role, 'reviewer') || finreq_role_is($rt, $role, 'manager')) {
        $my_routes[$rt['source_module']] = $rt;
    }
}

$catalog = finreq_catalog();
$rej_classes = finreq_rejection_classes();
$rows = array();
if ($my_routes) {
    $ph = implode(',', array_fill(0, count($my_routes), '?'));
    $rows = $gate->select('fin_requests', array(
        'whereRaw' => "state = 'under_review' AND source_module IN ($ph)",
        'params' => array_values(array_keys($my_routes)),
        'orderBy' => 'id DESC', 'limit' => 300,
    ));
}

$page_title = 'إيكوبيشن | موافقات إدارتي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'موافقات إدارتي — تحت المراجعة';
    $header_icon    = 'fa fa-inbox';
    $header_actions = array();
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا طلباتٍ تحتَ المراجعةِ لإداراتِك الآن', 'راجِعِ الطلباتِ الواردةَ لاحقًا، أو افتحْ «التفاصيل والسجل» لطلبٍ سبقَ بتُّه');
    ?>
    <style>
    .fdi-alert      { margin-bottom: 14px; font-weight: 700; }
    .fdi-mb14       { margin-bottom: 14px; }
    .fdi-card-head  { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .fdi-summary    { display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 10px; }
    .fdi-actions    { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .fdi-form       { display: flex; gap: 8px; }
    .fdi-reason     { min-width: 200px; }
    .fdi-reason-sm  { min-width: 180px; }
    </style>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info fdi-alert"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <?php if (!$my_routes): ?>
        <div class="card"><div class="card-body"><h5>⛔ دورك ليس مراجعًا ولا معتمدًا لأي إدارةٍ مفعّلة</h5></div></div>
    <?php else: foreach ($rows as $r):
        $rt = $my_routes[$r['source_module']];
        $can_recommend = $is_super || finreq_role_is($rt, $role, 'reviewer');
        $can_approve   = $is_super || finreq_role_is($rt, $role, 'manager');
        $docs_ok = finreq_docs_satisfied($gate, $r);
    ?>
        <div class="card fdi-mb14">
            <div class="card-header fdi-card-head">
                <h5><i class="<?php echo htmlspecialchars($catalog[$r['request_type']]['icon']); ?>"></i>
                    <?php echo htmlspecialchars($r['request_no']); ?> — <?php echo htmlspecialchars($catalog[$r['request_type']]['label']); ?>
                    <?php echo finreq_state_badge($r['state']); ?>
                    <?php echo intval($r['duplicate_flag']) === 1 ? '<span class="badge bg-warning">⚠️ تكرار محتمل</span>' : ''; ?>
                    <?php echo $docs_ok ? '<span class="badge bg-success">مستنداته مكتملة</span>' : '<span class="badge bg-danger">بلا مستند إلزامي</span>'; ?>
                </h5>
                <a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i> التفاصيل والسجل</a>
            </div>
            <div class="card-body">
                <div class="fdi-summary">
                    <div><strong>الإدارة:</strong> <?php echo htmlspecialchars($rt['module_label']); ?></div>
                    <div><strong>المبرّر:</strong> <?php echo htmlspecialchars($r['justification']); ?></div>
                    <div><strong>المستفيد:</strong> <?php echo htmlspecialchars($r['beneficiary_name'] ?? '-'); ?></div>
                    <div><strong>المبلغ:</strong> <?php echo number_format(floatval($r['amount']), 2) . ' ' . htmlspecialchars($r['currency']); ?></div>
                    <div><strong>الحاجة:</strong> <?php echo htmlspecialchars($r['need_class']); ?></div>
                </div>
                <div class="fdi-actions">
                    <?php if ($can_recommend): ?>
                    <form action="request_actions.php" method="post" class="fdi-form">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="return_request">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="dept_inbox.php">
                        <input type="text" name="reason" class="fdi-reason" placeholder="سبب الإعادة للاستكمال" required aria-label="سبب الإعادة للاستكمال">
                        <button type="submit" class="btn btn-secondary"><i class="fa fa-rotate-left"></i> إعادة</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($can_approve): ?>
                    <form action="request_actions.php" method="post" class="fdi-form">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="dept_approve">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="dept_inbox.php">
                        <button type="submit" class="btn btn-primary" <?php echo $docs_ok ? '' : 'disabled title="المستند شرط العبور"'; ?>>
                            <i class="fa fa-check-double"></i> اعتماد إداري
                        </button>
                    </form>
                    <form action="request_actions.php" method="post" class="fdi-form">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="dept_inbox.php">
                        <select name="rejection_class" aria-label="تصنيفُ سببِ الرفض" required>
                            <option value="">— تصنيف الرفض —</option>
                            <?php foreach ($rej_classes as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="reason" class="fdi-reason-sm" placeholder="سبب الرفض (إلزامي)" required aria-label="سبب الرفض (إلزامي)">
                        <button type="submit" class="btn btn-danger"><i class="fa fa-ban"></i> رفض</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; if (!$rows): ?>
        <div class="card"><div class="card-body">📭 لا طلبات تحت المراجعة لإداراتك الآن</div></div>
    <?php endif; endif; ?>
</div>
</body>
</html>

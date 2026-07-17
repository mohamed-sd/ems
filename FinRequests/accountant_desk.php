<?php
/** بوابة الطلب المالي D05 — مكتب محاسب الإدارة (§3.2): ضبط الحساب والأبعاد وولادة الحدث event_id */
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
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin accountant desk super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/accountant_desk.php');
if (!$is_super && !$__pp['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا صلاحية'));
    exit();
}

$catalog = finreq_catalog();
$routing_all = finreq_active_routing($gate);
$route_map = array();
foreach ($routing_all as $rt) { $route_map[$rt['source_module']] = $rt; }

// المعتمَد إداريًّا بانتظار ولادة حدثه (event_id فارغ) — قاعدة العبور
$rows = $gate->select('fin_requests', array(
    'whereRaw' => "state = 'pending_approval' AND event_id IS NULL",
    'params' => array(),
    'orderBy' => 'id DESC', 'limit' => 300,
));

// دليل الحسابات للاختيار (قاعدة الحساب: شأن المحاسب لا الطالب) — القابل للترحيل النشط فقط
$accounts = $gate->select('fin_chart_of_accounts', array(
    'columns' => array('id', 'code', 'name'),
    'where'   => array('active' => 1, 'is_postable' => 1),
    'orderBy' => 'code ASC', 'limit' => 500,
));

$page_title = 'إيكوبيشن | مكتب محاسب الإدارة';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'مكتب محاسب الإدارة — ولادة الحدث المالي';
    $header_icon    = 'fa fa-calculator';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info" style="margin-bottom:14px;font-weight:700;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <?php foreach ($rows as $r):
        $rt = isset($route_map[$r['source_module']]) ? $route_map[$r['source_module']] : null;
    ?>
        <div class="card" style="margin-bottom:14px;">
            <div class="card-header" style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <h5><i class="<?php echo htmlspecialchars($catalog[$r['request_type']]['icon']); ?>"></i>
                    <?php echo htmlspecialchars($r['request_no']); ?> — <?php echo htmlspecialchars($catalog[$r['request_type']]['label']); ?>
                    <?php echo finreq_state_badge($r['state']); ?>
                    <span class="badge bg-info"><?php echo htmlspecialchars($rt ? $rt['module_label'] : $r['source_module']); ?></span>
                </h5>
                <a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i> التفاصيل والسجل</a>
            </div>
            <div class="card-body">
                <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px;">
                    <div><strong>المبرّر:</strong> <?php echo htmlspecialchars($r['justification']); ?></div>
                    <div><strong>المستفيد:</strong> <?php echo htmlspecialchars($r['beneficiary_name'] ?? '-'); ?> (<?php echo htmlspecialchars($r['beneficiary_type']); ?>)</div>
                    <div><strong>المبلغ:</strong> <?php echo number_format(floatval($r['amount']), 2) . ' ' . htmlspecialchars($r['currency']); ?></div>
                    <div><strong>المرجع المصدري:</strong> <?php echo htmlspecialchars($r['source_ref'] ?? '—'); ?></div>
                </div>
                <form action="request_actions.php" method="post" class="allforms allforms-visible">
                    <input type="hidden" name="action" value="acct_forward">
                    <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                    <div class="form-grid">
                        <div>
                            <label>الحساب من الدليل * (قاعدة الحساب §5)</label>
                            <select name="account_id" required>
                                <option value="">— اختر الحساب —</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo intval($a['id']); ?>"><?php echo htmlspecialchars($a['code'] . ' — ' . $a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label>بند الموازنة (اختياري)</label><input type="number" name="budget_line_id" min="1"></div>
                        <div><label>مركز التكلفة</label><input type="text" name="cost_center" maxlength="60" value="<?php echo htmlspecialchars($r['cost_center'] ?? ''); ?>"></div>
                        <div><label>المشروع</label><input type="number" name="project_id" min="1" value="<?php echo htmlspecialchars($r['project_id'] ?? ''); ?>"></div>
                        <div><label>المعدة</label><input type="number" name="equipment_id" min="1" value="<?php echo htmlspecialchars($r['equipment_id'] ?? ''); ?>"></div>
                        <div style="align-self:end;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-baby-carriage"></i> ولادة الحدث المالي (D04)</button>
                        </div>
                    </div>
                </form>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
                    <form action="request_actions.php" method="post" style="display:flex;gap:8px;">
                        <input type="hidden" name="action" value="return_request">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="accountant_desk.php">
                        <input type="text" name="reason" placeholder="إعادة للمصدر بسببٍ (نقص تصنيف/أبعاد)" required style="min-width:240px;">
                        <button type="submit" class="btn btn-outline-warning"><i class="fa fa-rotate-left"></i> إعادة للمصدر</button>
                    </form>
                    <?php if (intval($r['duplicate_flag']) === 1): ?>
                    <form action="request_actions.php" method="post" style="display:flex;gap:8px;">
                        <input type="hidden" name="action" value="merge">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="accountant_desk.php">
                        <input type="text" name="merge_into_no" placeholder="رقم الطلب الأصل FR-…" required style="min-width:160px;">
                        <input type="text" name="reason" placeholder="سبب الدمج" required style="min-width:150px;">
                        <button type="submit" class="btn btn-outline-secondary"><i class="fa fa-code-merge"></i> دمج المكرّر</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; if (!$rows): ?>
        <div class="card"><div class="card-body">📭 لا طلبات معتمدةً إداريًّا بانتظار ولادة حدثها</div></div>
    <?php endif; ?>
</div>
</body>
</html>

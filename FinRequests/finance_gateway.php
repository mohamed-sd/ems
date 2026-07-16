<?php
/** بوابة الطلب المالي D05 — بوابة الدخول للمالية: كل الوارد بحالته المشتقة + مؤشر الدستور */
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = strval($_SESSION['user']['role']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin gateway super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/finance_gateway.php');
if (!$is_super && !$__pp['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا صلاحية'));
    exit();
}

$catalog = finreq_catalog();
$states = finreq_states();

$state_filter = isset($_GET['state']) && isset($states[$_GET['state']]) ? $_GET['state'] : '';
$fr_filter = ''; $fr_params = array();
if ($state_filter !== '') { $fr_filter = ' AND state = ?'; $fr_params[] = $state_filter; }

$rows = $gate->select('fin_requests', array(
    'whereRaw' => '1=1' . $fr_filter,
    'params' => $fr_params,
    'orderBy' => 'id DESC', 'limit' => 500,
));
foreach ($rows as $k => $r) { $rows[$k] = finreq_sync_state($gate, $r); }

// بطاقات البوابة: العدّ بالحالة + مؤشر الدستور (نسبة أحداث الفترة المولودة عبر البوابة)
$counts = array();
foreach ($gate->scopedQuery(array('scope' => array('fr' => 'fin_requests')),
    "SELECT state, COUNT(*) AS c FROM fin_requests fr WHERE 1=1 AND {TENANT_SCOPE} GROUP BY state") as $c) {
    $counts[$c['state']] = intval($c['c']);
}
$gateway_events = 0; $total_events_30d = 0;
foreach ($gate->scopedQuery(array('scope' => array('fe' => 'fin_financial_events')),
    "SELECT SUM(entity_type = 'fin_request') AS via_gateway, COUNT(*) AS total
       FROM fin_financial_events fe
      WHERE fe.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND {TENANT_SCOPE}") as $m) {
    $gateway_events = intval($m['via_gateway']); $total_events_30d = intval($m['total']);
}

$page_title = 'إيكوبيشن | بوابة الطلبات المالية';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'بوابة الدخول للمالية — الطلبات الموحّدة';
    $header_icon    = 'fa fa-building-columns';
    $header_actions = array(array('href' => 'accountant_desk.php', 'class' => 'add-btn', 'icon' => 'fa fa-calculator', 'label' => 'مكتب المحاسب'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info" style="margin-bottom:14px;font-weight:700;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <div class="stats-grid" style="margin-bottom:14px;">
        <div class="stat-card"><div class="stat-label">تحت المراجعة</div><div class="stat-value"><?php echo $counts['under_review'] ?? 0; ?></div></div>
        <div class="stat-card"><div class="stat-label">بانتظار الاعتماد</div><div class="stat-value"><?php echo $counts['pending_approval'] ?? 0; ?></div></div>
        <div class="stat-card"><div class="stat-label">معتمد/مقيد</div><div class="stat-value"><?php echo ($counts['approved'] ?? 0) + ($counts['posted'] ?? 0); ?></div></div>
        <div class="stat-card"><div class="stat-label">مدفوع/مغلق</div><div class="stat-value"><?php echo ($counts['paid'] ?? 0) + ($counts['collected'] ?? 0) + ($counts['closed'] ?? 0); ?></div></div>
        <div class="stat-card">
            <div class="stat-label">مؤشر الدستور (30 يومًا): أحداثٌ عبر البوابة</div>
            <div class="stat-value"><?php echo $gateway_events; ?> / <?php echo $total_events_30d; ?>
                <?php if ($total_events_30d > 0): ?><small>(<?php echo round($gateway_events / $total_events_30d * 100); ?>%)</small><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-table"></i> كل الطلبات</h5>
            <form method="get" style="display:flex;gap:8px;">
                <select name="state" onchange="this.form.submit()">
                    <option value="">— كل الحالات —</option>
                    <?php foreach ($states as $k => $s): ?>
                        <option value="<?php echo $k; ?>" <?php echo $state_filter === $k ? 'selected' : ''; ?>><?php echo $s['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="card-body table-container">
            <table class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>الرقم</th><th>الإدارة</th><th>النوع</th><th>المستفيد</th>
                        <th>المبلغ</th><th>الحالة</th><th>الحدث</th><th>منشئه</th><th>أُنشئ</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['request_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['source_module']); ?></td>
                        <td><?php echo htmlspecialchars($catalog[$r['request_type']]['label'] ?? $r['request_type']); ?></td>
                        <td><?php echo htmlspecialchars($r['beneficiary_name'] ?? '-'); ?></td>
                        <td><?php echo number_format(floatval($r['amount']), 2) . ' ' . htmlspecialchars($r['currency']); ?></td>
                        <td><?php echo finreq_state_badge($r['state']); ?></td>
                        <td><?php echo $r['event_id'] ? ('#' . intval($r['event_id'])) : '—'; ?></td>
                        <td><?php echo intval($r['created_by']); ?></td>
                        <td><?php echo htmlspecialchars(substr($r['created_at'], 0, 10)); ?></td>
                        <td><a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="action-btn view" title="فتح"><i class="fa fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

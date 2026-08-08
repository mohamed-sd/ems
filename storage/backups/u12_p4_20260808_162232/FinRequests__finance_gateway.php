<?php
/** بوابة الطلب المالي D05 — بوابة الدخول للمالية: كل الوارد بحالته المشتقة + مؤشر الدستور */
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

// ── الاستثناءات (§8.3): طابور قرارات الطارئ (للمدير المالي) + مؤشر حدّ الـ5% الشهري ──
$exception_queue = array();
if ($is_super || $role === '17') {
    try {
        $exception_queue = $gate->select('fin_requests', array(
            'whereRaw' => "is_exception = 0 AND state IN ('under_review', 'pending_approval')
                AND EXISTS (SELECT 1 FROM fin_request_events e1
                             WHERE e1.request_id = fin_requests.id AND e1.event_type = 'exception_requested')
                AND NOT EXISTS (SELECT 1 FROM fin_request_events e2
                             WHERE e2.request_id = fin_requests.id AND e2.event_type = 'exception_denied'
                               AND e2.id > (SELECT MAX(e3.id) FROM fin_request_events e3
                                             WHERE e3.request_id = fin_requests.id AND e3.event_type = 'exception_requested'))",
            'params' => array(), 'orderBy' => 'id DESC', 'limit' => 50,
        ));
    } catch (\Throwable $t) { /* عرض */ }
}
$exc_month = 0; $req_month = 0;
try {
    $exc_month = intval($gate->count('fin_request_events', array(
        'whereRaw' => "event_type = 'exception' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
        'params' => array(),
    )));
    $req_month = intval($gate->count('fin_requests', array(
        'whereRaw' => "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", 'params' => array(),
    )));
} catch (\Throwable $t) { /* عرض */ }
$exc_pct = $req_month > 0 ? round($exc_month / $req_month * 100, 1) : 0;

$page_title = 'إيكوبيشن | الطلبات المالية';
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'الطلبات المالية';
    $header_icon    = 'fa fa-building-columns';
    $header_actions = array(
        array('href' => 'accountant_desk.php', 'class' => 'add-btn', 'icon' => 'fa fa-calculator', 'label' => 'مكتب المحاسب'),
        array('href' => 'effect_map.php', 'class' => 'add-btn', 'icon' => 'fa fa-diagram-project', 'label' => 'خريطة الأثر'),
        array('href' => 'cycle_time_board.php', 'class' => 'add-btn', 'icon' => 'fa fa-stopwatch', 'label' => 'لوحة زمن الدورة'),
    );
    if ($is_super || $role === '17') {
        $header_actions[] = array('href' => 'routing_admin.php', 'class' => 'add-btn', 'icon' => 'fa fa-route', 'label' => 'توجيه الإدارات');
    }
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
        <div class="stat-card">
            <div class="stat-label">الاستثناءات الشهرية (§8.3 — الحدّ 5%)</div>
            <div class="stat-value" style="<?php echo $exc_pct > 5 ? 'color:#dc2626;' : ''; ?>">
                <?php echo $exc_month; ?> / <?php echo $req_month; ?> <small>(<?php echo $exc_pct; ?>%)</small>
                <?php if ($exc_pct > 5): ?><small>⚠️ تجاوزٌ يستوجب مراجعة سببٍ جذري</small><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($exception_queue): ?>
    <div class="card" style="margin-bottom:14px;border-right:4px solid #dc2626;">
        <div class="card-header"><h5><i class="fa fa-bolt" style="color:#dc2626;"></i> طلبات الاستثناء الطارئ — قرارك حصرًا (§8.3)</h5></div>
        <div class="card-body">
            <?php foreach ($exception_queue as $xq):
                $last_reason = '';
                try {
                    $lr = $gate->select('fin_request_events', array(
                        'columns' => array('body'),
                        'where' => array('request_id' => intval($xq['id']), 'event_type' => 'exception_requested'),
                        'orderBy' => 'id DESC', 'limit' => 1,
                    ));
                    $last_reason = $lr ? strval($lr[0]['body']) : '';
                } catch (\Throwable $t) { /* عرض */ }
            ?>
            <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;padding:8px 0;border-bottom:1px dashed #e3d9c6;">
                <strong><?php echo htmlspecialchars($xq['request_no']); ?></strong>
                <span><?php echo htmlspecialchars($catalog[$xq['request_type']]['label'] ?? $xq['request_type']); ?></span>
                <span><?php echo number_format(floatval($xq['amount']), 2) . ' ' . htmlspecialchars($xq['currency']); ?></span>
                <span style="color:#6b4e2a;"><?php echo htmlspecialchars($last_reason); ?></span>
                <form action="request_actions.php" method="post" style="display:flex;gap:6px;"
                      onsubmit="var x=prompt('سبب الاعتماد (للتدقيق):');if(!x)return false;this.reason.value=x;">
                    <input type="hidden" name="action" value="exception_decide">
                    <input type="hidden" name="decision" value="approve">
                    <input type="hidden" name="id" value="<?php echo intval($xq['id']); ?>">
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-bolt"></i> اعتماد الطارئ</button>
                </form>
                <form action="request_actions.php" method="post" style="display:flex;gap:6px;"
                      onsubmit="var x=prompt('سبب الرفض:');if(!x)return false;this.reason.value=x;">
                    <input type="hidden" name="action" value="exception_decide">
                    <input type="hidden" name="decision" value="deny">
                    <input type="hidden" name="id" value="<?php echo intval($xq['id']); ?>">
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">رفض</button>
                </form>
                <a href="request_form.php?id=<?php echo intval($xq['id']); ?>" class="btn btn-sm btn-outline-primary">التفاصيل</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
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
                        <td style="white-space:nowrap;">
                            <a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="action-btn view" title="فتح"><i class="fa fa-eye"></i></a>
                            <a href="effect_map.php?q=<?php echo urlencode($r['request_no']); ?>" class="action-btn" title="خريطة الأثر"><i class="fa fa-diagram-project"></i></a>
                            <?php if ($is_super || $role === '17'): ?>
                                <?php if (in_array($r['state'], array('under_review', 'pending_approval'), true)): ?>
                                <form action="request_actions.php" method="post" style="display:inline;" onsubmit="var x=prompt('سبب التعليق (إلزامي):');if(!x)return false;this.reason.value=x;">
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                                    <input type="hidden" name="back" value="finance_gateway.php">
                                    <input type="hidden" name="reason" value="">
                                    <button type="submit" class="action-btn" title="تعليق" style="border:none;background:none;color:#c96a00;"><i class="fa fa-pause-circle"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['state'] === 'suspended'): ?>
                                <form action="request_actions.php" method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="resume">
                                    <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                                    <input type="hidden" name="back" value="finance_gateway.php">
                                    <button type="submit" class="action-btn" title="استئناف" style="border:none;background:none;color:#16a34a;"><i class="fa fa-play-circle"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['state'] === 'pending_approval'): ?>
                                <form action="request_actions.php" method="post" style="display:inline;" onsubmit="var x=prompt('سبب الإلغاء (إلزامي — وبعد الولادة تُعالَج آثاره في D04):');if(!x)return false;this.reason.value=x;">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                                    <input type="hidden" name="back" value="finance_gateway.php">
                                    <input type="hidden" name="reason" value="">
                                    <button type="submit" class="action-btn" title="إلغاء" style="border:none;background:none;color:#dc2626;"><i class="fa fa-ban"></i></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

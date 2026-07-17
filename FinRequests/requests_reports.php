<?php
/** بوابة الطلب المالي D05 — تقارير الطلبات (§12.3):
 *  ① الطلبات بالحالة والنوع والإدارة ② أعمار الطلبات المفتوحة
 *  ③ المعاد والمرفوض بأسبابه ④ سجلّ طلبٍ كامل (للتدقيق — القصة برقمٍ واحد).
 *  قراءةٌ خالصة — كل بياناتها من fin_requests والسجل الإلحاقي؛ صفر كتابة. */
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
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin requests reports super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/requests_reports.php');
if (!$is_super && !$__pp['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا صلاحية'));
    exit();
}

$catalog = finreq_catalog();
$states = finreq_states();
$rej_classes = finreq_rejection_classes();

// ── ① التجميع الثلاثي: بالحالة · بالنوع · بالإدارة ──
$by_state = array(); $by_type = array(); $by_module = array();
foreach ($gate->scopedQuery(array('scope' => array('fr' => 'fin_requests')),
    "SELECT state, COUNT(*) c, COALESCE(SUM(amount),0) amt FROM fin_requests fr WHERE {TENANT_SCOPE} GROUP BY state") as $r) {
    $by_state[$r['state']] = $r;
}
foreach ($gate->scopedQuery(array('scope' => array('fr' => 'fin_requests')),
    "SELECT request_type, COUNT(*) c, COALESCE(SUM(amount),0) amt FROM fin_requests fr WHERE {TENANT_SCOPE} GROUP BY request_type ORDER BY c DESC") as $r) {
    $by_type[] = $r;
}
foreach ($gate->scopedQuery(array('scope' => array('fr' => 'fin_requests')),
    "SELECT source_module, COUNT(*) c, COALESCE(SUM(amount),0) amt FROM fin_requests fr WHERE {TENANT_SCOPE} GROUP BY source_module ORDER BY c DESC") as $r) {
    $by_module[] = $r;
}

// ── ② أعمار الطلبات المفتوحة (سلال: 0-2 · 3-7 · 8-30 · +30 يومًا) ──
$open_states = "('draft','under_review','pending_approval','returned','suspended')";
$ages = array('0-2' => 0, '3-7' => 0, '8-30' => 0, '31+' => 0);
$oldest = array();
foreach ($gate->scopedQuery(array('scope' => array('fr' => 'fin_requests')),
    "SELECT id, request_no, state, source_module, amount, currency,
            TIMESTAMPDIFF(DAY, created_at, NOW()) age_days
       FROM fin_requests fr WHERE {TENANT_SCOPE} AND state IN $open_states
      ORDER BY age_days DESC LIMIT 200") as $r) {
    $d = intval($r['age_days']);
    if ($d <= 2) { $ages['0-2']++; }
    elseif ($d <= 7) { $ages['3-7']++; }
    elseif ($d <= 30) { $ages['8-30']++; }
    else { $ages['31+']++; }
    if (count($oldest) < 10) { $oldest[] = $r; }
}

// ── ③ المعاد والمرفوض بأسبابه (آخر سببٍ من السجل الإلحاقي) ──
$rejected = $gate->select('fin_requests', array(
    'columns' => array('id', 'request_no', 'request_type', 'source_module', 'amount', 'currency', 'rejection_class', 'decided_by'),
    'where' => array('state' => 'rejected'), 'orderBy' => 'id DESC', 'limit' => 100,
));
$returned = $gate->select('fin_requests', array(
    'columns' => array('id', 'request_no', 'request_type', 'source_module', 'amount', 'currency'),
    'where' => array('state' => 'returned'), 'orderBy' => 'id DESC', 'limit' => 100,
));
function rr_last_reason($gate, $rid, $types)
{
    $ph = implode(',', array_fill(0, count($types), '?'));
    $rows = $gate->select('fin_request_events', array(
        'columns' => array('body'),
        'whereRaw' => "request_id = ? AND event_type IN ($ph)",
        'params' => array_merge(array(intval($rid)), $types),
        'orderBy' => 'id DESC', 'limit' => 1,
    ));
    return $rows ? strval($rows[0]['body']) : '—';
}

// ── ④ سجل طلبٍ كامل (بحث برقم الطلب) ──
$q = isset($_GET['q']) ? trim(strval($_GET['q'])) : '';
$audit_req = null; $audit_log = array();
if ($q !== '') {
    $audit_req = $gate->selectOne('fin_requests', array('where' => array('request_no' => $q)));
    if ($audit_req) {
        $audit_log = $gate->select('fin_request_events', array(
            'where' => array('request_id' => intval($audit_req['id'])),
            'orderBy' => 'id ASC', 'limit' => 300,
        ));
    }
}

$page_title = 'إيكوبيشن | تقارير الطلبات المالية';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'تقارير الطلبات المالية (§12.3)';
    $header_icon    = 'fa fa-chart-column';
    $header_actions = array(
        array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'),
        array('href' => 'cycle_time_board.php', 'class' => 'add-btn', 'icon' => 'fa fa-stopwatch', 'label' => 'لوحة زمن الدورة'),
    );
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fa fa-layer-group"></i> ① الطلبات بالحالة والنوع والإدارة</h5></div>
        <div class="card-body" style="display:flex;gap:24px;flex-wrap:wrap;">
            <div style="flex:1;min-width:260px;">
                <h6>بالحالة</h6>
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>الحالة</th><th>العدد</th><th>المبلغ</th></tr></thead>
                    <tbody>
                    <?php foreach ($states as $k => $s): if (!isset($by_state[$k])) { continue; } ?>
                        <tr><td><?php echo finreq_state_badge($k); ?></td>
                            <td><?php echo intval($by_state[$k]['c']); ?></td>
                            <td><?php echo number_format(floatval($by_state[$k]['amt']), 0); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="flex:1;min-width:260px;">
                <h6>بالنوع</h6>
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>النوع</th><th>العدد</th><th>المبلغ</th></tr></thead>
                    <tbody>
                    <?php foreach ($by_type as $t): ?>
                        <tr><td><?php echo htmlspecialchars($catalog[$t['request_type']]['label'] ?? $t['request_type']); ?></td>
                            <td><?php echo intval($t['c']); ?></td>
                            <td><?php echo number_format(floatval($t['amt']), 0); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="flex:1;min-width:260px;">
                <h6>بالإدارة</h6>
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>الإدارة</th><th>العدد</th><th>المبلغ</th></tr></thead>
                    <tbody>
                    <?php foreach ($by_module as $m): ?>
                        <tr><td><code><?php echo htmlspecialchars($m['source_module']); ?></code></td>
                            <td><?php echo intval($m['c']); ?></td>
                            <td><?php echo number_format(floatval($m['amt']), 0); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fa fa-hourglass-half"></i> ② أعمار الطلبات المفتوحة</h5></div>
        <div class="card-body">
            <div class="stats-grid" style="margin-bottom:12px;">
                <div class="stat-card"><div class="stat-label">0-2 يوم</div><div class="stat-value"><?php echo $ages['0-2']; ?></div></div>
                <div class="stat-card"><div class="stat-label">3-7 أيام</div><div class="stat-value"><?php echo $ages['3-7']; ?></div></div>
                <div class="stat-card"><div class="stat-label">8-30 يومًا</div><div class="stat-value" style="<?php echo $ages['8-30'] > 0 ? 'color:#b45309;' : ''; ?>"><?php echo $ages['8-30']; ?></div></div>
                <div class="stat-card"><div class="stat-label">أكثر من 30</div><div class="stat-value" style="<?php echo $ages['31+'] > 0 ? 'color:#dc2626;' : ''; ?>"><?php echo $ages['31+']; ?></div></div>
            </div>
            <?php if ($oldest): ?>
            <h6>الأقدم عمرًا (أعلى 10)</h6>
            <table class="table table-striped no-datatable" data-no-dt="1">
                <thead><tr><th>الطلب</th><th>الإدارة</th><th>الحالة</th><th>العمر (يوم)</th><th>المبلغ</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($oldest as $o): ?>
                    <tr><td><strong><?php echo htmlspecialchars($o['request_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($o['source_module']); ?></td>
                        <td><?php echo finreq_state_badge($o['state']); ?></td>
                        <td><?php echo intval($o['age_days']); ?></td>
                        <td><?php echo number_format(floatval($o['amount']), 0) . ' ' . htmlspecialchars($o['currency']); ?></td>
                        <td><a href="requests_reports.php?q=<?php echo urlencode($o['request_no']); ?>">سجله ↓</a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>✅ لا طلبات مفتوحة الآن<?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fa fa-rotate-left"></i> ③ المعاد والمرفوض بأسبابه</h5></div>
        <div class="card-body" style="display:flex;gap:24px;flex-wrap:wrap;">
            <div style="flex:1;min-width:320px;">
                <h6>المرفوض (<?php echo count($rejected); ?>) — نهايةٌ موثقةٌ لا حذف</h6>
                <?php foreach ($rejected as $rj): ?>
                    <div style="padding:6px 0;border-bottom:1px dashed #e3d9c6;">
                        ⛔ <strong><?php echo htmlspecialchars($rj['request_no']); ?></strong>
                        · <span class="badge bg-danger"><?php echo htmlspecialchars($rej_classes[$rj['rejection_class']] ?? ($rj['rejection_class'] ?: 'بلا تصنيف')); ?></span>
                        · <?php echo number_format(floatval($rj['amount']), 0); ?>
                        <div style="color:#6b4e2a;font-size:.9em;">السبب: <?php echo htmlspecialchars(rr_last_reason($gate, $rj['id'], array('reject'))); ?></div>
                    </div>
                <?php endforeach; if (!$rejected): ?><div>لا مرفوضات</div><?php endif; ?>
            </div>
            <div style="flex:1;min-width:320px;">
                <h6>المعاد للاستكمال (<?php echo count($returned); ?>) — يعود لمنشئه بالسبب كاملًا</h6>
                <?php foreach ($returned as $rt): ?>
                    <div style="padding:6px 0;border-bottom:1px dashed #e3d9c6;">
                        ↩️ <strong><?php echo htmlspecialchars($rt['request_no']); ?></strong>
                        · <?php echo htmlspecialchars($rt['source_module']); ?>
                        · <?php echo number_format(floatval($rt['amount']), 0); ?>
                        <div style="color:#6b4e2a;font-size:.9em;">السبب: <?php echo htmlspecialchars(rr_last_reason($gate, $rt['id'], array('return'))); ?></div>
                    </div>
                <?php endforeach; if (!$returned): ?><div>لا مُعادات</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fa fa-magnifying-glass"></i> ④ سجلّ طلبٍ كامل — إعادة بناء القصة (§8.6)</h5></div>
        <div class="card-body">
            <form method="get" class="allforms allforms-visible" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:12px;">
                <div style="min-width:260px;">
                    <label>رقم الطلب (FR-…)</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="FR-2026-0001">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-book-open"></i> افرد القصة</button>
            </form>
            <?php if ($q !== '' && !$audit_req): ?>
                <div>🔍 لا طلب بهذا الرقم ضمن نطاق شركتك</div>
            <?php elseif ($audit_req): ?>
                <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px;">
                    <div><strong><?php echo htmlspecialchars($audit_req['request_no']); ?></strong></div>
                    <div><?php echo htmlspecialchars($catalog[$audit_req['request_type']]['label'] ?? $audit_req['request_type']); ?></div>
                    <div><?php echo finreq_state_badge($audit_req['state']); ?></div>
                    <div><strong>المبلغ:</strong> <?php echo number_format(floatval($audit_req['amount']), 2) . ' ' . htmlspecialchars($audit_req['currency']); ?></div>
                    <div><strong>المبرر:</strong> <?php echo htmlspecialchars($audit_req['justification'] ?? ''); ?></div>
                    <?php if (!empty($audit_req['event_id'])): ?>
                        <div><a href="effect_map.php?q=<?php echo urlencode($audit_req['request_no']); ?>">مروحة أثره ↗</a></div>
                    <?php endif; ?>
                </div>
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>الحدث</th><th>الفاعل</th><th>البيان</th><th>قبل → بعد</th><th>الطابع</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($audit_log as $ev): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><code><?php echo htmlspecialchars($ev['event_type']); ?></code></td>
                            <td><?php echo $ev['actor_user_id'] !== null ? intval($ev['actor_user_id']) : 'النظام'; ?><?php echo $ev['on_behalf_of'] ? ' (نيابةً عن ' . intval($ev['on_behalf_of']) . ')' : ''; ?></td>
                            <td><?php echo htmlspecialchars($ev['body'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(($ev['old_value'] !== null || $ev['new_value'] !== null) ? (($ev['old_value'] ?? '—') . ' → ' . ($ev['new_value'] ?? '—')) : ''); ?></td>
                            <td><?php echo htmlspecialchars($ev['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

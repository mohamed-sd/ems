<?php
/** بوابة الطلب المالي D05 — خريطة تفريع الأثر (§6): قراءة السلسلة كاملةً في الاتجاهين
 *  الطلب → الحدث (event_id) → القيود والدفعات والمستحقات (D04 بمرجع الحدث) —
 *  ومن أي حدثٍ صعودًا إلى طلبه. قراءةٌ حصرًا؛ محرك التوليد (المروحة الكاملة) مرحلة لاحقة. */
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
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin effect map super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/effect_map.php');
if (!$is_super && !$__pp['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا صلاحية'));
    exit();
}

$catalog = finreq_catalog();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$req = null; $ev = null; $journals = array(); $payments = array(); $dues = array(); $links = array();

if ($q !== '') {
    // البحث برقم الطلب أو بمعرّف الحدث (#N)
    if (preg_match('/^#?(\d+)$/', $q, $m)) {
        $ev = $gate->selectOne('fin_financial_events', array('where' => array('id' => intval($m[1])), 'includeDeleted' => true));
        if ($ev) {
            $req = $gate->selectOne('fin_requests', array('where' => array('event_id' => intval($ev['id']))));
        }
    } else {
        $req = $gate->selectOne('fin_requests', array('where' => array('request_no' => $q)));
        if ($req) { $req = finreq_sync_state($gate, $req); }
        if ($req && !empty($req['event_id'])) {
            $ev = $gate->selectOne('fin_financial_events', array('where' => array('id' => intval($req['event_id'])), 'includeDeleted' => true));
        }
    }
    if ($ev) {
        $eid = intval($ev['id']);
        $journals = $gate->select('fin_journal_entries', array('where' => array('event_id' => $eid), 'orderBy' => 'id ASC'));
        $payments = $gate->select('fin_payments', array('where' => array('event_id' => $eid), 'orderBy' => 'id ASC'));
        $dues     = $gate->select('fin_dues', array('where' => array('event_id' => $eid), 'orderBy' => 'id ASC'));
        $links    = $gate->select('fin_event_links', array(
            'whereRaw' => '(parent_kind = ? AND parent_ref = ?) OR event_id = ?',
            'params' => array('event', $eid, $eid), 'orderBy' => 'id ASC',
        ));
    }
}

$page_title = 'إيكوبيشن | خريطة تفريع الأثر';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'خريطة تفريع الأثر — الخيط في الاتجاهين';
    $header_icon    = 'fa fa-diagram-project';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-body">
            <form method="get" class="allforms allforms-visible" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div style="min-width:280px;">
                    <label>رقم الطلب (FR-…) أو معرّف الحدث (#N)</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="FR-2026-0001 أو #11">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-magnifying-glass"></i> تتبّع الخيط</button>
            </form>
        </div>
    </div>

    <?php if ($q !== '' && !$req && !$ev): ?>
        <div class="card"><div class="card-body">🔍 لا نتيجة — تحقق من الرقم ضمن نطاق شركتك</div></div>
    <?php endif; ?>

    <?php if ($req): ?>
        <div class="card" style="margin-bottom:14px;">
            <div class="card-header"><h5><i class="fa fa-file-lines"></i> ① المصدر: الطلب الموحّد</h5></div>
            <div class="card-body" style="display:flex;gap:18px;flex-wrap:wrap;">
                <div><strong><?php echo htmlspecialchars($req['request_no']); ?></strong></div>
                <div><?php echo htmlspecialchars($catalog[$req['request_type']]['label'] ?? $req['request_type']); ?></div>
                <div><?php echo finreq_state_badge($req['state']); ?></div>
                <div><strong>المبلغ:</strong> <?php echo number_format(floatval($req['amount']), 2) . ' ' . htmlspecialchars($req['currency']); ?></div>
                <div><strong>المبرّر:</strong> <?php echo htmlspecialchars($req['justification'] ?? ''); ?></div>
                <div><a href="request_form.php?id=<?php echo intval($req['id']); ?>">فتح الطلب وسجله ↗</a></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($ev): ?>
        <div class="card" style="margin-bottom:14px;">
            <div class="card-header"><h5><i class="fa fa-bolt"></i> ② الحدث المالي (D04)</h5></div>
            <div class="card-body" style="display:flex;gap:18px;flex-wrap:wrap;">
                <div><strong><?php echo htmlspecialchars($ev['event_no']); ?></strong> (#<?php echo intval($ev['id']); ?>)</div>
                <div><code><?php echo htmlspecialchars($ev['event_key']); ?></code></div>
                <div><span class="badge bg-info"><?php echo htmlspecialchars($ev['source_module']); ?></span></div>
                <div><strong>الحالة:</strong> <?php echo htmlspecialchars($ev['state']); ?></div>
                <div><strong>المبلغ:</strong> <?php echo number_format(floatval($ev['amount']), 2) . ' ' . htmlspecialchars($ev['currency']); ?></div>
                <div><strong>المرجع:</strong> <?php echo htmlspecialchars($ev['source_ref'] ?? '—'); ?></div>
            </div>
        </div>

        <div class="card" style="margin-bottom:14px;">
            <div class="card-header"><h5><i class="fa fa-book"></i> ③ القيود المتولّدة (<?php echo count($journals); ?>)</h5></div>
            <div class="card-body">
                <?php if ($journals): ?>
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>القيد</th><th>الحالة</th><th>إجمالي مدين</th><th>البيان</th></tr></thead>
                    <tbody>
                    <?php foreach ($journals as $j): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($j['entry_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($j['state']); ?></td>
                            <td><?php echo number_format(floatval($j['total_debit']), 2); ?></td>
                            <td><?php echo htmlspecialchars($j['memo'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>لا قيود بعد — تُولَد في دورة D04 بعد الاعتماد المالي<?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-bottom:14px;">
            <div class="card-header"><h5><i class="fa fa-money-check-dollar"></i> ④ الدفعات والمستحقات</h5></div>
            <div class="card-body" style="display:flex;gap:24px;flex-wrap:wrap;">
                <div style="flex:1;min-width:280px;">
                    <h6>الدفعات (<?php echo count($payments); ?>)</h6>
                    <?php foreach ($payments as $p): ?>
                        <div>💳 <strong><?php echo htmlspecialchars($p['payment_no']); ?></strong> — <?php echo number_format(floatval($p['amount']), 2); ?> · <?php echo htmlspecialchars($p['method']); ?> · <?php echo htmlspecialchars($p['state']); ?></div>
                    <?php endforeach; if (!$payments): ?><div>لا دفعات بعد</div><?php endif; ?>
                </div>
                <div style="flex:1;min-width:280px;">
                    <h6>المستحقات (<?php echo count($dues); ?>)</h6>
                    <?php foreach ($dues as $d): ?>
                        <div>🧾 <?php echo htmlspecialchars($d['party_type']); ?> — <?php echo number_format(floatval($d['amount']), 2); ?></div>
                    <?php endforeach; if (!$dues): ?><div>لا مستحقات مرتبطة</div><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($links): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-sitemap"></i> روابط المروحة الموثقة (fin_event_links)</h5></div>
            <div class="card-body">
                <table class="table table-striped no-datatable" data-no-dt="1">
                    <thead><tr><th>الأب</th><th>نوع الأثر</th><th>الجدول الهدف</th><th>المعرف</th></tr></thead>
                    <tbody>
                    <?php foreach ($links as $L): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($L['parent_kind']) . ' #' . intval($L['parent_ref']); ?></td>
                            <td><code><?php echo htmlspecialchars($L['effect_type']); ?></code></td>
                            <td><?php echo htmlspecialchars($L['target_table']); ?></td>
                            <td><?php echo $L['target_id'] !== null ? intval($L['target_id']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

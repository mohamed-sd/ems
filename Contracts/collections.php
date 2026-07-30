<?php
/**
 * Contracts/collections.php — الذممُ والتحصيل (M-05)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-03 §6: «قائمة: **الذممُ بأعمارها ملوّنةً** · **تحصيلٌ بمرجعٍ إلزامي** ·
 * **تخصيصُ الدفعة ظاهرٌ** · متابعةٌ بموعدٍ تالٍ».
 * §4: «التحصيلُ الجزئي — **أقدمُ فاتورةٍ أولًا** ما لم يحدد العميلُ مرجعًا صريحًا».
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Revenue/CollectionService.php';

use App\Services\Revenue\CollectionService as COL;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Contracts/collections.php';
$can_view = $can_add = false;
if ($is_super_admin) {
    $can_view = $can_add = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الذمم+والتحصيل+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('collections super') : ems_tenant_db();
$redirect = function ($msg) { header("Location: collections.php?msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['col_action'] ?? '') === 'record') {
    if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $r = COL::record($conn, $gate, $company_id, array(
        'client_id'     => $_POST['client_id'] ?? 0,
        'amount'        => $_POST['amount'] ?? 0,
        'bank_ref'      => $_POST['bank_ref'] ?? '',
        'received_on'   => $_POST['received_on'] ?? '',
        'receivable_id' => $_POST['receivable_id'] ?? 0,
        'currency'      => $_POST['currency'] ?? 'USD',
        'method'        => $_POST['method'] ?? 'bank',
        'memo'          => $_POST['memo'] ?? '',
    ), $uid);
    if ($r['ok']) {
        $msg = 'سُجّل القبضُ وخُصّص ' . $r['allocated']
             . ' على ' . count($r['allocations']) . ' ذمّة';
        if ($r['unallocated'] > 0) { $msg .= ' · ' . $r['reason']; }
        if ($r['claims_touched']) {
            $st = array();
            foreach ($r['claims_touched'] as $c) { $st[] = '#' . $c['claim_id'] . '→' . $c['state']; }
            $msg .= ' · ارتدَّت حالةُ: ' . implode(' · ', $st);
        }
        $redirect($msg . ' ✅');
    }
    $redirect($r['code'] . ' — ' . $r['reason'] . ' ❌');
}

$clients = array();
try {
    $clients = $gate->scopedQuery(array('scope' => array('c' => 'clients')),
        "SELECT c.id, c.client_name FROM clients c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 ORDER BY c.client_name");
} catch (\Throwable $t) { $clients = array(); }

$filterClient = intval($_GET['client_id'] ?? 0);
$ageing = COL::ageing($gate, $filterClient);

$recent = array();
try {
    $recent = $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
        "SELECT p.id, p.payment_no, p.amount, p.currency, p.bank_ref, p.received_on, p.state
           FROM fin_payments p
          WHERE {TENANT_SCOPE} AND p.direction = 'collection' AND COALESCE(p.is_deleted,0)=0
          ORDER BY p.id DESC LIMIT 30");
} catch (\Throwable $t) { $recent = array(); }

$page_title = 'إيكوبيشن | الذمم والتحصيل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الذمم والتحصيل'; $header_icon = 'fa fa-hand-holding-dollar';
    $header_actions = array();
    $header_back = array('href' => 'client_statement.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'كشف حساب العميل');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-money-check-dollar"></i> تسجيلُ قبض</h5></div>
    <div class="card-body">
        <p style="color:#666">
            <strong>المرجعُ البنكيُّ إلزامي</strong> («قبضٌ بمرجعٍ بنكيٍّ أو سند» §4)، و<strong>لا يُقبض
            المرجعُ نفسُه بالمبلغ نفسِه في اليوم نفسِه مرتين</strong>. والتخصيصُ
            <strong>لأقدم فاتورةٍ أولًا</strong> ما لم تُحدَّد ذمّةٌ بعينها — <strong>وكلُّ تخصيصٍ سطرٌ يُرى</strong>.
        </p>
        <form method="post" class="ems-form">
            <input type="hidden" name="col_action" value="record">
            <div class="form-grid">
                <div class="form-group"><label>العميل <span style="color:#c00">*</span></label>
                    <select name="client_id" required>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo intval($c['id']); ?>">
                                <?php echo htmlspecialchars((string)$c['client_name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>المبلغ <span style="color:#c00">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" required></div>
                <div class="form-group"><label>المرجع البنكي / السند <span style="color:#c00">*</span></label>
                    <input type="text" name="bank_ref" maxlength="120" required></div>
                <div class="form-group"><label>تاريخ القبض <span style="color:#c00">*</span></label>
                    <input type="date" name="received_on" required></div>
                <div class="form-group"><label>ذمّةٌ بعينها <small>— فارغٌ = أقدمُ فاتورةٍ أولًا</small></label>
                    <select name="receivable_id">
                        <option value="0">— أقدمُ فاتورةٍ أولًا —</option>
                        <?php foreach ($ageing as $r): if ((float)$r['outstanding'] <= 0) { continue; } ?>
                            <option value="<?php echo intval($r['id']); ?>">
                                <?php echo htmlspecialchars((string)$r['doc_ref']); ?>
                                — متبقٍّ <?php echo htmlspecialchars((string)$r['outstanding']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>العملة</label><input type="text" name="currency" value="USD" maxlength="8"></div>
                <div class="form-group"><label>الطريقة</label><input type="text" name="method" value="bank" maxlength="30"></div>
                <div class="form-group"><label>بيان</label><input type="text" name="memo" maxlength="200"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> سجّل القبض</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock"></i> الذممُ بأعمارها</h5></div>
    <div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <label>ترشيحٌ بعميل:</label>
            <select name="client_id" onchange="this.form.submit()">
                <option value="0">— الكل —</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo intval($c['id']); ?>" <?php echo $filterClient === intval($c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$c['client_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>المرجع</th><th>المبلغ</th><th>المحصَّل</th><th>المتبقي</th>
                <th>العمر (يوم)</th><th>الحال</th></tr></thead>
            <tbody>
            <?php foreach ($ageing as $r):
                $age = intval($r['age_days']);
                $cls = 'badge-success';
                if ($age >= 90) { $cls = 'badge-danger'; }
                elseif ($age >= 60) { $cls = 'badge-warning'; }
                elseif ($age >= 30) { $cls = 'badge-secondary'; }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($r['doc_ref'] ?? ('#' . intval($r['id'])))); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['collected']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$r['outstanding']); ?></strong></td>
                    <td><span class="badge <?php echo $cls; ?>"><?php echo $age; ?></span></td>
                    <td><?php echo htmlspecialchars((string)$r['state']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> آخرُ المقبوضات وتخصيصُها</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>الرقم</th><th>المرجع البنكي</th><th>التاريخ</th><th>المبلغ</th>
                <th>الحال</th><th>التخصيص</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $p): $alloc = COL::allocationsOf($gate, intval($p['id'])); ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$p['payment_no']); ?></td>
                    <td><?php echo (string)$p['bank_ref'] === 'legacy_no_ref'
                        ? '<span class="badge badge-warning">موروثٌ بلا مرجع</span>'
                        : htmlspecialchars((string)$p['bank_ref']); ?></td>
                    <td><?php echo htmlspecialchars((string)($p['received_on'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)$p['amount']); ?>
                        <?php echo htmlspecialchars((string)$p['currency']); ?></td>
                    <td><?php echo htmlspecialchars((string)$p['state']); ?></td>
                    <td><?php if (!$alloc): ?>
                            <span class="badge badge-warning">بلا تخصيصٍ مسجَّل</span>
                        <?php else: foreach ($alloc as $a): ?>
                            <div><?php echo htmlspecialchars((string)$a['doc_ref']); ?>:
                                <strong><?php echo htmlspecialchars((string)$a['amount']); ?></strong>
                                <small>(<?php echo (string)$a['basis'] === 'explicit'
                                    ? 'مرجعٌ صريح' : 'أقدمُ فاتورةٍ أولًا'; ?>)</small></div>
                        <?php endforeach; endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

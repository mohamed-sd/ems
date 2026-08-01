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
$unit = null; $fanEffects = array(); $fanMap = array();
$tsCtx = null; // D02 م1-①: سياق يوم الدوام مصدرًا للمروحة

// ── مروحة أثر يوم الدوام (D02 م1-①): بحثٌ بمرجع الصف (TS-…) ──
if ($q !== '' && preg_match('/^\s*TS-(\d+)\s*$/i', $q, $tsm)) {
    require_once __DIR__ . '/../app/Services/EffectFanout.php';
    $tsId = intval($tsm[1]);
    // البوابة تعزل بالشركة: صفٌّ خارج نطاقها = لا نتيجة (لا تسريب عبر المترجم)
    $tsRow = $gate->selectOne('timesheet', array('columns' => array('id', 'company_id'), 'where' => array('id' => $tsId)));
    if ($tsRow) {
        try { $tsCtx = \App\Services\EffectFanout::resolveTimesheet($conn, $tsId); }
        catch (\Throwable $t) { $tsCtx = null; }
    }
    if ($tsCtx) {
        foreach (\App\Services\EffectFanout::mapFor($gate, intval($tsCtx['company_id']), 'timesheet') as $mrow) {
            $fanMap[$mrow['effect_type']] = $mrow;
        }
        foreach ($gate->select('fin_event_links', array(
            'where' => array('parent_kind' => 'timesheet', 'parent_ref' => $tsId), 'orderBy' => 'id ASC',
        )) as $l) {
            $target = null;
            try { $target = $gate->selectOne($l['target_table'], array('where' => array('id' => intval($l['target_id'])), 'includeDeleted' => true)); }
            catch (\Throwable $t) { /* عرض */ }
            $fanEffects[$l['effect_type']] = array('link' => $l, 'target' => $target);
        }
    }
}

// ── مروحة أثر الوحدة المعتمدة (§6.1): بحثٌ برقم كشف الوحدة (FIN-UR-…) ──
if ($q !== '' && preg_match('/UR-/i', $q)) {
    $unit = $gate->selectOne('fin_unit_records', array('where' => array('record_no' => $q)));
    if ($unit) {
        require_once __DIR__ . '/../app/Services/EffectFanout.php';
        $uid = intval($unit['id']);
        // خريطة الآثار المعرَّفة (لعرض المتاح وغير المتاح جنبًا إلى جنب)
        foreach (\App\Services\EffectFanout::mapFor($gate, intval($unit['company_id']), 'unit_record') as $mrow) {
            $fanMap[$mrow['effect_type']] = $mrow;
        }
        // الآثار المتولّدة فعلًا مع صفوفها الهدف
        foreach ($gate->select('fin_event_links', array(
            'where' => array('parent_kind' => 'unit_record', 'parent_ref' => $uid), 'orderBy' => 'id ASC',
        )) as $l) {
            $target = null;
            try { $target = $gate->selectOne($l['target_table'], array('where' => array('id' => intval($l['target_id'])), 'includeDeleted' => true)); }
            catch (\Throwable $t) { /* عرض */ }
            $fanEffects[$l['effect_type']] = array('link' => $l, 'target' => $target);
        }
    }
}

if ($q !== '' && $unit === null) {
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

$page_title = 'إيكوبيشن | سجل الحركة المالية';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'سجل الحركة المالية — الخيط في الاتجاهين';
    $header_icon    = 'fa fa-diagram-project';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-body">
            <form method="get" class="allforms allforms-visible" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div style="min-width:280px;">
                    <label>رقم الطلب (FR-…) · الحدث (#N) · أو كشف الوحدة (FIN-UR-…)</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="FR-2026-0001 · #11 · FIN-UR-0001">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-magnifying-glass"></i> تتبّع الخيط</button>
            </form>
        </div>
    </div>

    <?php if ($q !== '' && !$req && !$ev && !$unit && !$tsCtx): ?>
        <div class="card"><div class="card-body">🔍 لا نتيجة — تحقق من الرقم ضمن نطاق شركتك</div></div>
    <?php endif; ?>

    <?php if ($tsCtx): ?>
        <?php $u_lbl = array('hour' => 'ساعة', 'ton' => 'طن', 'meter' => 'متر'); ?>
        <div class="card" style="margin-bottom:14px;">
            <div class="card-header"><h5><i class="fa fa-calendar-day"></i> المصدر: يوم الدوام المعتمد (سجلّ الوحدات — D02)</h5></div>
            <div class="card-body" style="display:flex;gap:18px;flex-wrap:wrap;">
                <div><strong><?php echo htmlspecialchars($tsCtx['source_ref']); ?></strong></div>
                <div><?php echo htmlspecialchars($tsCtx['work_date']); ?></div>
                <div><span class="badge bg-info"><?php echo $tsCtx['unit'] === null ? 'لا كميةَ مسجّلة'
                    : number_format((float)$tsCtx['qty'], 2) . ' ' . ($u_lbl[$tsCtx['unit']] ?? $tsCtx['unit']); ?></span></div>
                <div><strong>سعر العميل:</strong> <?php echo $tsCtx['client']['ok']
                    ? number_format((float)$tsCtx['client']['price'], 2) . ' ' . htmlspecialchars($tsCtx['client']['currency'])
                    : '<span style="color:#9a6a00;">— ' . htmlspecialchars($tsCtx['client']['reason']) . '</span>'; ?></div>
                <div><strong>سعر المورد:</strong> <?php echo $tsCtx['supplier']['ok']
                    ? number_format((float)$tsCtx['supplier']['price'], 2) . ' ' . htmlspecialchars($tsCtx['supplier']['currency'])
                    : '<span style="color:#9a6a00;">— ' . htmlspecialchars($tsCtx['supplier']['reason']) . '</span>'; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($unit): ?>
        <?php
        $model_lbl = array('hour' => 'ساعة', 'ton' => 'طن', 'meter' => 'متر');
        $uq = ($unit['approved_qty'] !== null ? $unit['approved_qty'] : $unit['ops_qty']);
        ?>
        <div class="card" style="margin-bottom:14px;">
            <div class="card-header"><h5><i class="fa fa-cube"></i> المصدر: الوحدة التشغيلية المعتمدة</h5></div>
            <div class="card-body" style="display:flex;gap:18px;flex-wrap:wrap;">
                <div><strong><?php echo htmlspecialchars($unit['record_no']); ?></strong></div>
                <div><?php echo htmlspecialchars($unit['record_date']); ?></div>
                <div><span class="badge bg-info"><?php echo number_format((float)$uq, 2) . ' ' . ($model_lbl[$unit['work_model']] ?? $unit['work_model']); ?></span></div>
                <div><strong>سعر العميل:</strong> <?php echo $unit['client_unit_price'] !== null ? number_format((float)$unit['client_unit_price'], 2) : '—'; ?></div>
                <div><strong>سعر المورد:</strong> <?php echo $unit['supplier_unit_price'] !== null ? number_format((float)$unit['supplier_unit_price'], 2) : '—'; ?></div>
                <div><strong>هامش الوحدة:</strong> <?php echo number_format((float)$unit['unit_margin'], 2); ?></div>
                <div><?php echo finreq_state_badge($unit['match_state'] === 'approved' ? 'approved' : 'under_review'); ?></div>
            </div>
        </div>

    <?php endif; ?>

    <?php if ($unit || $tsCtx): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-sitemap"></i> مروحة الأثر — الحدث الواحد والآثار المتعددة (§6.1)</h5></div>
            <div class="card-body">
                <p style="color:#6b4e2a;font-weight:600;">كل أثرٍ مربوطٌ بأبيه في <code>fin_event_links</code>؛ وغيرُ المتاح مُعلَنٌ بسببه لا صامت.</p>
                <div class="fanout-tree" style="border-right:3px solid #d4b06a;padding-right:16px;">
                <?php foreach ($fanMap as $etype => $meta):
                    $gen = isset($fanEffects[$etype]) ? $fanEffects[$etype] : null;
                    $target = $gen ? $gen['target'] : null;
                    $amount = null;
                    if ($target) {
                        $amount = isset($target['amount']) ? $target['amount']
                            : (isset($target['total_cost']) ? $target['total_cost'] : null);
                    }
                    $icon = array('revenue_event' => '💰', 'supplier_due' => '📦', 'cost_record' => '🚜',
                                  'employee_due' => '👷', 'metric_update' => '🔧');
                ?>
                    <div style="padding:10px 0;border-bottom:1px dashed #e3d9c6;display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
                        <span style="font-size:1.3rem;"><?php echo $icon[$etype] ?? '•'; ?></span>
                        <strong style="min-width:230px;"><?php echo htmlspecialchars($meta['effect_label']); ?></strong>
                        <?php if ($gen): ?>
                            <span class="badge bg-success">مولَّد</span>
                            <code><?php echo htmlspecialchars($gen['link']['target_table']); ?> #<?php echo intval($gen['link']['target_id']); ?></code>
                            <?php if ($amount !== null): ?><strong style="color:#1a7a3a;"><?php echo number_format((float)$amount, 2); ?></strong><?php endif; ?>
                        <?php elseif (intval($meta['is_active']) !== 1): ?>
                            <span class="badge bg-secondary">غير متاح</span>
                            <span style="color:#9a6a00;font-size:.9rem;"><?php echo htmlspecialchars($meta['unavailable_reason'] ?? ''); ?></span>
                        <?php else:
                            // مصدر الدوام: نعلن سببَ التعذّر الحقيقي (تسعيرٌ/عقدٌ/كمية) بدل «بانتظار» مبهمة
                            $blocked = '';
                            if ($tsCtx) {
                                if ($etype === 'revenue_event' && !$tsCtx['client']['ok'])       { $blocked = $tsCtx['client']['reason']; }
                                elseif (($etype === 'supplier_due' || $etype === 'cost_record') && !$tsCtx['supplier']['ok']) { $blocked = $tsCtx['supplier']['reason']; }
                            }
                        ?>
                            <?php if ($blocked !== ''): ?>
                                <span class="badge bg-secondary">متعذّر</span>
                                <span style="color:#9a6a00;font-size:.9rem;"><?php echo htmlspecialchars($blocked); ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning">بانتظار التوليد</span>
                                <span style="color:#6b4e2a;font-size:.9rem;">يُفرَّع عند الاعتماد الرابع أو بكنس المصالِح</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
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
            <div class="card-header"><h5><i class="fa fa-sitemap"></i> روابط الآثار المالية الموثقة (fin_event_links)</h5></div>
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

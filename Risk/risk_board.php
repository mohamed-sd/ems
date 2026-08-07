<?php
/**
 * Risk/risk_board.php — لوحة المخاطر العليا (M-16 · RU-01 · RK-08)
 * الرئيس يرى لوحةً لا قائمة آلاف المخاطر — والحرج لا يختفي عنه.
 * الشاشة التمثيلية «مساحة المخاطر» (UXR-0076): KPI سباعية + جدول + تصعيدات.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT);
$q1 = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    return $r ? (int) $r->fetch_assoc()['c'] : 0;
};
$kOpen = $q1("SELECT COUNT(*) c FROM risk_register rr WHERE rr.company_id={$company_id} AND rr.state<>'closed' AND rr.merged_into_id IS NULL {$scopeSql}");
$kCrit = $q1("SELECT COUNT(*) c FROM risk_register rr WHERE rr.company_id={$company_id} AND rr.state<>'closed' AND rr.merged_into_id IS NULL AND rr.current_level IN ('حرج','محظور') {$scopeSql}");
$kOverdue = $q1("SELECT COUNT(*) c FROM risk_register rr WHERE rr.company_id={$company_id} AND rr.state<>'closed' AND rr.merged_into_id IS NULL AND rr.review_due < CURDATE() {$scopeSql}");
$kEsc = $q1("SELECT COUNT(*) c FROM risk_escalations WHERE company_id={$company_id} AND acknowledged_at IS NULL");
$kSig = $q1("SELECT COUNT(*) c FROM risk_signals WHERE company_id={$company_id} AND state='pending'");
$kKri = $q1("SELECT COUNT(*) c FROM risk_kris WHERE company_id={$company_id} AND kri_state='critical'");

$top = array();
$r = $conn->query("SELECT rr.id, rr.risk_code, rr.title, rr.current_level, rr.state, ru.ru_code, ou.name_ar owner_unit
                     FROM risk_register rr JOIN risk_units ru ON ru.id = rr.ru_id
                     LEFT JOIN org_units ou ON ou.unit_id = rr.owner_unit_id
                    WHERE rr.company_id={$company_id} AND rr.state<>'closed' AND rr.merged_into_id IS NULL {$scopeSql}
                    ORDER BY FIELD(COALESCE(rr.current_level,''),'محظور','حرج','مرتفع','متوسط','منخفض',''), rr.updated_at DESC LIMIT 12");
while ($x = $r->fetch_assoc()) { $top[] = $x; }

$escRows = array();
$r = $conn->query("SELECT e.*, rr.risk_code FROM risk_escalations e LEFT JOIN risk_register rr ON rr.id = e.risk_id
                    WHERE e.company_id={$company_id} AND e.acknowledged_at IS NULL ORDER BY e.created_at DESC LIMIT 10");
while ($x = $r->fetch_assoc()) { $escRows[] = $x; }

$today = date('Y-m-d');
$page_title = 'إيكوبيشن | لوحة المخاطر العليا';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة المخاطر العليا';
    $header_icon = 'fas fa-tower-observation';
    $header_actions = array();
    $header_back = array();
    $header_context = array('المقام' => 'السجل المركزي (نطاقك)', 'الفترة' => 'لحظي ' . $today);
    include('../includes/page_header.php');
    ems_screen_about('لوحة الرئيس ومدير المخاطر: المحفظة بمستوياتها والتصعيدات المفتوحة — الخطر الحرج لا يختفي (RK-08).',
        array('التصعيد آلي بالمصفوفة — ولا يملك أحد إخفاءه ولا مدير المخاطر نفسه'));

    $kpis = array(
        array('مخاطر مفتوحة', $kOpen, 'خطر', $kOpen > 0 ? 'warn' : 'ok', 'risk_register.php'),
        array('حرجة/محظورة', $kCrit, 'خطر', $kCrit > 0 ? 'err' : 'ok', 'risk_register.php?level=حرج'),
        array('مراجعات متأخرة', $kOverdue, 'خطر', $kOverdue > 0 ? 'err' : 'ok', 'risk_reviews.php'),
        array('تصعيدات تنتظر الاستلام', $kEsc, 'تصعيد', $kEsc > 0 ? 'err' : 'ok', '#escList'),
        array('إشارات تنتظر الفرز', $kSig, 'إشارة', $kSig > 0 ? 'warn' : 'ok', 'risk_signals.php'),
        array('مؤشرات بلغت الحد الحرج', $kKri, 'مؤشر', $kKri > 0 ? 'err' : 'ok', 'risk_kris.php'),
    );
    ?>
    <div class="ems-grid">
        <?php foreach ($kpis as $k): ?>
        <a class="ems-kpi-card ems-col-4 ems-kpi-<?php echo $k[3]; ?>" href="<?php echo $k[4]; ?>" title="تعمّق: <?php echo $k[0]; ?>">
            <div class="ems-kpi-title"><?php echo $k[0]; ?></div>
            <div class="ems-kpi-value"><?php echo $k[1]; ?> <small><?php echo $k[2]; ?></small></div>
            <div class="ems-kpi-meta"><span>لحظي (<?php echo $today; ?>)</span><span>بلا مقارنة معلنة</span></div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="ems-grid" style="margin-top:16px">
        <div class="ems-card ems-col-8">
            <h6>أعلى المخاطر (بالمستوى ثم الحداثة)</h6>
            <div class="table-responsive"><table class="table table-sm table-striped">
                <thead><tr><th>الرمز</th><th>العنوان</th><th>الوحدة</th><th>الإدارة المالكة</th><th>المستوى</th><th>الحالة</th><th></th></tr></thead>
                <tbody><?php foreach ($top as $t): ?>
                <tr><td><?php echo $t['risk_code']; ?></td>
                    <td><?php echo htmlspecialchars($t['title']); ?></td>
                    <td><?php echo $t['ru_code']; ?></td>
                    <td><?php echo htmlspecialchars((string) $t['owner_unit'] ?: '—'); ?></td>
                    <td><span class="badge badge-<?php echo in_array($t['current_level'], array('حرج', 'محظور'), true) ? 'danger' : 'secondary'; ?>">
                        <?php echo $t['current_level'] !== null && $t['current_level'] !== '' ? $t['current_level'] : 'لم يقيَّم'; ?></span></td>
                    <td><?php echo $t['state']; ?></td>
                    <td><a href="risk_card.php?id=<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-outline-dark">فتح</a></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php if (empty($top)): ?><div id="rbEmpty"></div>
            <script>document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('rbEmpty').appendChild(EmsUI.emptyState({
                    reason: 'لا مخاطر مفتوحة في نطاقك — الإشارات تُفرز أولًا', createHref: 'risk_signals.php', createLabel: 'صندوق الإشارات' }));
            });</script><?php endif; ?>
        </div>
        <div class="ems-card ems-col-4" id="escList">
            <h6>التصعيدات المفتوحة (RK-08)</h6>
            <?php foreach ($escRows as $e): ?>
            <div style="padding:8px 0;border-bottom:1px dashed #eee;font-size:.82rem">
                <b><?php echo htmlspecialchars($e['reason_ar']); ?></b>
                <div class="text-muted"><?php echo ($e['risk_code'] ?: 'إشارة') . ' · إلى: ' . $e['to_authority'] . ' · ' . $e['created_at']; ?></div>
                <?php if ($RISK_AUTHORITY === 'ceo' || $RISK_FULL): ?>
                <button class="btn btn-sm btn-outline-success escAck" data-id="<?php echo (int) $e['id']; ?>">استلام</button>
                <?php endif; ?>
            </div>
            <?php endforeach; if (empty($escRows)): ?><small class="text-muted">لا تصعيدات تنتظر</small><?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.escAck').forEach(function (b) {
        b.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('do', 'escalation_ack');
            fd.append('escalation_id', b.dataset.id);
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) { if (j.ok) { location.reload(); } else { alert((j.code || '') + ': ' + (j.msg || '')); } });
        });
    });
});
</script>
</body>
</html>

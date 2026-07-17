<?php
/** بوابة الطلب المالي D05 — لوحة زمن الدورة (§12.1):
 *  زمن كل مرحلة (من سجل انتقالات المحرك) · الالتزام بـSLA · أعلى الاختناقات ·
 *  التصعيدات المفتوحة بمستوياتها · قائمة المستوى الرابع (المساءلة الأسبوعية §8.2)
 *  وخروقات مهلة الطارئ (§8.3). قراءةٌ خالصة — كل بياناتها مختومةٌ أصلًا. */
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
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin cycle board super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/cycle_time_board.php');
if (!$is_super && !$__pp['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا صلاحية'));
    exit();
}

$states = finreq_states();
$company_id = intval($_SESSION['user']['company_id'] ?? 0);

// ① أزمنة المراحل من سجل الانتقالات (قناة القراءة الموثقة — T_RESTRICTED)
$timings = ($company_id > 0 || !$is_super) ? finreq_stage_timings($conn, $company_id) : array();
if ($is_super && !$timings) {
    // السوبر بلا شركة سياق: اعرض شركة 4 المرجعية بدل صفحةٍ فارغة
    $timings = finreq_stage_timings($conn, 4);
}
uasort($timings, function ($a, $b) { return intval($b['avg_mins']) - intval($a['avg_mins']); });
$bottleneck = $timings ? array_key_first($timings) : null;

// ② الالتزام بـSLA الآن: النشط المختوم — ملتزمٌ/متجاوِز
$active = $gate->select('fin_requests', array(
    'whereRaw' => "sla_due_at IS NOT NULL AND state IN ('under_review', 'pending_approval')",
    'params' => array(), 'orderBy' => 'sla_due_at ASC', 'limit' => 500,
));
$on_time = 0; $overdue = 0;
foreach ($active as $a) {
    if (strtotime($a['sla_due_at']) >= time()) { $on_time++; } else { $overdue++; }
}
$compliance = ($on_time + $overdue) > 0 ? round($on_time / ($on_time + $overdue) * 100) : 100;

// ③ التصعيدات المفتوحة بمستوياتها + قائمة المستوى الرابع (§8.2)
$escalated = $gate->select('fin_requests', array(
    'whereRaw' => "escalation_level >= 1 AND state IN ('under_review', 'pending_approval', 'suspended')",
    'params' => array(), 'orderBy' => 'escalation_level DESC, sla_due_at ASC', 'limit' => 200,
));
$level3 = array();
foreach ($escalated as $e) { if (intval($e['escalation_level']) >= 3) { $level3[] = $e; } }

// ④ خروقات مهلة الطارئ (§8.3) — من السجل الإلحاقي
$exc_breaches = array();
try {
    $exc_breaches = $gate->select('fin_requests', array(
        'whereRaw' => "is_exception = 1 AND EXISTS (SELECT 1 FROM fin_request_events oe
                        WHERE oe.request_id = fin_requests.id AND oe.event_type = 'exception_overdue')",
        'params' => array(), 'orderBy' => 'id DESC', 'limit' => 50,
    ));
} catch (\Throwable $t) { /* عرض */ }

$stage_labels = array(
    'under_review' => 'مراجعة الإدارة واعتمادها', 'pending_approval' => 'مكتب المحاسب والمالية',
    'approved' => 'الاعتماد المالي', 'posted' => 'القيد', 'paid' => 'الدفع', 'collected' => 'التحصيل',
    'closed' => 'الإقفال', 'returned' => 'الإعادة للاستكمال', 'rejected' => 'الرفض',
    'withdrawn' => 'السحب', 'cancelled' => 'الإلغاء', 'suspended' => 'التعليق',
    'merged' => 'الدمج', 'expired' => 'الانتهاء', 'archived' => 'الأرشفة', 'draft' => 'المسودة',
);

$page_title = 'إيكوبيشن | لوحة زمن الدورة';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'لوحة زمن الدورة — الالتزام والاختناق والتصعيد';
    $header_icon    = 'fa fa-stopwatch';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="stats-grid" style="margin-bottom:14px;">
        <div class="stat-card"><div class="stat-label">الالتزام بـSLA (النشط الآن)</div>
            <div class="stat-value" style="<?php echo $compliance < 80 ? 'color:#dc2626;' : ''; ?>"><?php echo $compliance; ?>%</div></div>
        <div class="stat-card"><div class="stat-label">ضمن المدة / متجاوز</div>
            <div class="stat-value"><?php echo $on_time; ?> / <span style="color:#dc2626;"><?php echo $overdue; ?></span></div></div>
        <div class="stat-card"><div class="stat-label">تصعيداتٌ مفتوحة</div>
            <div class="stat-value"><?php echo count($escalated); ?></div></div>
        <div class="stat-card"><div class="stat-label">أعلى اختناق</div>
            <div class="stat-value" style="font-size:1.05rem;"><?php echo $bottleneck !== null ? htmlspecialchars($stage_labels[$bottleneck] ?? $bottleneck) : '—'; ?></div></div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fa fa-hourglass-half"></i> زمن كل مرحلة (من سجل الانتقالات — بالدقائق)</h5></div>
        <div class="card-body">
            <?php if ($timings): ?>
            <table class="table table-bordered no-datatable" data-no-dt="1">
                <thead><tr><th>المرحلة (الدخول إلى)</th><th>عدد العبور</th><th>متوسط المكوث (دقيقة)</th><th>أقصى مكوث</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($timings as $st => $t): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($stage_labels[$st] ?? $st); ?></strong> <?php echo isset($states[$st]) ? finreq_state_badge($st) : ''; ?></td>
                        <td><?php echo intval($t['entries']); ?></td>
                        <td><?php echo number_format(intval($t['avg_mins'])); ?></td>
                        <td><?php echo number_format(intval($t['max_mins'])); ?></td>
                        <td><?php echo $st === $bottleneck ? '<span class="badge bg-danger">🔥 الاختناق الأعلى</span>' : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>لا انتقالاتٍ مكتملةً بعد — تتراكم البيانات مع استعمال الدورة<?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fa fa-arrow-trend-up"></i> التصعيدات المفتوحة (§8.2)</h5></div>
        <div class="card-body">
            <?php if ($escalated): ?>
            <table class="table table-striped no-datatable" data-no-dt="1">
                <thead><tr><th>الطلب</th><th>الإدارة</th><th>الحالة</th><th>الاستحقاق</th><th>المستوى</th><th></th></tr></thead>
                <tbody>
                <?php $lv = array(1 => array('تذكير', 'info'), 2 => array('تنبيه', 'warning'), 3 => array('تصعيد', 'danger'));
                foreach ($escalated as $e): $L = $lv[min(3, intval($e['escalation_level']))]; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($e['request_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($e['source_module']); ?></td>
                        <td><?php echo finreq_state_badge($e['state']); ?></td>
                        <td><?php echo htmlspecialchars($e['sla_due_at'] ?? '—'); ?></td>
                        <td><span class="badge bg-<?php echo $L[1]; ?>">المستوى <?php echo intval($e['escalation_level']); ?> — <?php echo $L[0]; ?></span></td>
                        <td><a href="request_form.php?id=<?php echo intval($e['id']); ?>">فتح ↗</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>✅ لا تصعيدات مفتوحة — الدورة تجري ضمن مددها<?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:14px;border-right:4px solid #b45309;">
        <div class="card-header"><h5><i class="fa fa-gavel"></i> قائمة المساءلة الأسبوعية — المستوى ④ (§8.2)</h5></div>
        <div class="card-body">
            <?php if ($level3): ?>
                <p style="font-weight:700;color:#92400e;">كل ما بلغ المستوى الثالث يخضع لمراجعةٍ أسبوعيةٍ إلزامية (المدير المالي + مدير الإدارة المعنية):</p>
                <?php foreach ($level3 as $e): ?>
                    <div style="padding:6px 0;border-bottom:1px dashed #e3d9c6;">
                        ⚖️ <strong><?php echo htmlspecialchars($e['request_no']); ?></strong>
                        — <?php echo htmlspecialchars($e['source_module']); ?> · <?php echo finreq_state_badge($e['state']); ?>
                        · استحقاقه <?php echo htmlspecialchars($e['sla_due_at'] ?? '—'); ?>
                        <a href="request_form.php?id=<?php echo intval($e['id']); ?>" style="margin-right:8px;">سجله الكامل ↗</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>✅ لا شيء بلغ المستوى الثالث<?php endif; ?>
        </div>
    </div>

    <?php if ($exc_breaches): ?>
    <div class="card" style="border-right:4px solid #dc2626;">
        <div class="card-header"><h5><i class="fa fa-bolt"></i> خروقات مهلة الطارئ — 72 ساعة (§8.3)</h5></div>
        <div class="card-body">
            <?php foreach ($exc_breaches as $b): ?>
                <div style="padding:6px 0;border-bottom:1px dashed #e3d9c6;">
                    🚨 <strong><?php echo htmlspecialchars($b['request_no']); ?></strong>
                    — استثناءٌ معتمدٌ لم تُستكمل دورته رجعيًّا خلال 72 ساعة · <?php echo finreq_state_badge($b['state']); ?>
                    <a href="request_form.php?id=<?php echo intval($b['id']); ?>" style="margin-right:8px;">فتح ↗</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

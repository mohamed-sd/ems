<?php
/** بوابة الطلب المالي D05 — لوحة زمن الدورة (§12.1):
 *  زمن كل مرحلة (من سجل انتقالات المحرك) · الالتزام بـSLA · أعلى الاختناقات ·
 *  التصعيدات المفتوحة بمستوياتها · قائمة المستوى الرابع (المساءلة الأسبوعية §8.2)
 *  وخروقات مهلة الطارئ (§8.3). قراءةٌ خالصة — كل بياناتها مختومةٌ أصلًا. */
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
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin cycle board super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/cycle_time_board.php');
if (!$is_super && !$__pp['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا صلاحية', 'GOV-PERM-403', '');
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

// ═══ مؤشرات الأداء §12.3 — الثلاثة الأخيرة (قراءةٌ خالصة) ═══

// ⑥ زمن دورة الطلب (إنشاء ← إقفال): من ميلاده إلى أول ختمٍ بلغ فيه «مغلق».
//    المؤرشف يُحسب أيضًا — أرشفتُه بعد إقفاله لا تلغي زمن دورته.
// fin_request_events في الاستعلام الفرعي مُعلَنٌ enrich (بلا حقن عزل — يعزله
// ارتباطه بـfr المعزول أصلًا؛ نفس نمط الاستعلامات المرتبطة في إطار التقارير).
$cycle_hours = array();
foreach ($gate->scopedQuery(
    array('scope' => array('fr' => 'fin_requests'), 'enrich' => array('x' => 'fin_request_events')),
    "SELECT TIMESTAMPDIFF(HOUR, fr.created_at,
              (SELECT MIN(x.created_at) FROM fin_request_events x
                WHERE x.request_id = fr.id AND x.new_value = 'closed')) AS h
       FROM fin_requests fr
      WHERE {TENANT_SCOPE} AND fr.state IN ('closed', 'archived')") as $c) {
    if ($c['h'] !== null) { $cycle_hours[] = intval($c['h']); }
}
$cycle_avg = $cycle_hours ? round(array_sum($cycle_hours) / count($cycle_hours)) : null;
$cycle_min = $cycle_hours ? min($cycle_hours) : null;
$cycle_max = $cycle_hours ? max($cycle_hours) : null;

// ⑦ نسبة الإعادة والرفض — تُحسب من **السجل الإلحاقي** لا من الحالة الراهنة:
//    الطلب الذي أُعيد ثم استُكمل واعتُمد لم يعد «معادًا»، لكنه تعثّر فعلًا —
//    وحسابُه بالحالة يُخفيه فتظهر النسبة أقلَّ من حقيقتها. المقياس الصادق:
//    «كم طلبًا أُعيد/رُفض يومًا ما» ÷ «كم طلبًا أُرسل أصلًا».
$evt_distinct = function ($types) use ($gate) {
    $ph = implode(',', array_fill(0, count($types), '?'));
    $rows = $gate->scopedQuery(array('scope' => array('e' => 'fin_request_events')),
        "SELECT COUNT(DISTINCT e.request_id) v FROM fin_request_events e
          WHERE {TENANT_SCOPE} AND e.event_type IN ($ph)", $types);
    return $rows ? intval($rows[0]['v']) : 0;
};
$submitted_n = $evt_distinct(array('submit'));
$returned_n  = $evt_distinct(array('return'));
$rejected_n  = $evt_distinct(array('reject'));
$return_pct = $submitted_n > 0 ? round($returned_n / $submitted_n * 100, 1) : 0;
$reject_pct = $submitted_n > 0 ? round($rejected_n / $submitted_n * 100, 1) : 0;

// ⑧ اكتمال المستندات من أول إرسال — **بنيويًّا 100%**: بوابة العبور الصفرية
//    تمنع الإرسال بلا مستندٍ مكتملٍ من اليوم الأول (المستند شرط العبور §3.1)،
//    فلا يوجد طلبٌ أُرسل ناقصَ المستند أصلًا. والرقم ذو المعنى الإداري بجانبه:
//    الرفض المصنَّف «مستندات ناقصة» — يكشف من يُرفق مستندًا لا يفي بالغرض.
$docs_complete_pct = 100;
$rej_docs = 0;
try {
    $rej_docs = intval($gate->count('fin_requests', array(
        'where' => array('rejection_class' => 'incomplete_docs'),
    )));
} catch (\Throwable $t) { /* عرض */ }

$stage_labels = array(
    'under_review' => 'مراجعة الإدارة واعتمادها', 'pending_approval' => 'مكتب المحاسب والمالية',
    'approved' => 'الاعتماد المالي', 'posted' => 'القيد', 'paid' => 'الدفع', 'collected' => 'التحصيل',
    'closed' => 'الإقفال', 'returned' => 'الإعادة للاستكمال', 'rejected' => 'الرفض',
    'withdrawn' => 'السحب', 'cancelled' => 'الإلغاء', 'suspended' => 'التعليق',
    'merged' => 'الدمج', 'expired' => 'الانتهاء', 'archived' => 'الأرشفة', 'draft' => 'المسودة',
);

$page_title = 'إيكوبيشن | زمن دورة الطلبات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'زمن دورة الطلبات — الالتزام والاختناق والتصعيد';
    $header_icon    = 'fa fa-stopwatch';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا انتقالاتِ دورةٍ مسجَّلةً بعدُ ليُقاسَ زمنُها', 'افتحْ «بوابة المالية» من رأسِ الشاشةِ وسيِّرْ طلبًا ليبدأ سجلُّ الانتقالاتِ بالتراكم');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div class="stats-grid fcb-mb14">
        <div class="stat-card"><div class="stat-label">الالتزام بـSLA (النشط الآن)</div>
            <div class="stat-value<?php echo $compliance < 80 ? ' fcb-danger' : ''; ?>"><?php echo $compliance; ?>%</div></div>
        <div class="stat-card"><div class="stat-label">ضمن المدة / متجاوز</div>
            <div class="stat-value"><?php echo $on_time; ?> / <span class="fcb-danger"><?php echo $overdue; ?></span></div></div>
        <div class="stat-card"><div class="stat-label">تصعيداتٌ مفتوحة</div>
            <div class="stat-value"><?php echo count($escalated); ?></div></div>
        <div class="stat-card"><div class="stat-label">أعلى اختناق</div>
            <div class="stat-value fcb-stat-sm"><?php echo $bottleneck !== null ? htmlspecialchars($stage_labels[$bottleneck] ?? $bottleneck) : '—'; ?></div></div>
    </div>

    <div class="card fcb-mb14">
        <div class="card-header"><h5><i class="fa fa-gauge-high"></i> مؤشرات الأداء (§12.3)</h5></div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-label">⑥ زمن الدورة (إنشاء←إقفال) — متوسط</div>
                    <div class="stat-value"><?php echo $cycle_avg !== null ? $cycle_avg . ' <small>ساعة</small>' : '—'; ?></div>
                    <?php if ($cycle_min !== null): ?><div class="fcb-note">أسرع <?php echo $cycle_min; ?> · أبطأ <?php echo $cycle_max; ?> ساعة (<?php echo count($cycle_hours); ?> مقفلًا)</div><?php endif; ?>
                </div>
                <div class="stat-card"><div class="stat-label">⑦ نسبة الإعادة (من السجل)</div>
                    <div class="stat-value<?php echo $return_pct > 20 ? ' fcb-warn' : ''; ?>"><?php echo $return_pct; ?>%</div>
                    <div class="fcb-note"><?php echo $returned_n; ?> أُعيد من <?php echo $submitted_n; ?> مُرسَل</div>
                </div>
                <div class="stat-card"><div class="stat-label">⑦ نسبة الرفض (من السجل)</div>
                    <div class="stat-value<?php echo $reject_pct > 15 ? ' fcb-danger' : ''; ?>"><?php echo $reject_pct; ?>%</div>
                    <div class="fcb-note"><?php echo $rejected_n; ?> رُفض من <?php echo $submitted_n; ?> مُرسَل</div>
                </div>
                <div class="stat-card"><div class="stat-label">⑧ اكتمال المستندات من أول إرسال</div>
                    <div class="stat-value fcb-ok"><?php echo $docs_complete_pct; ?>%</div>
                    <div class="fcb-note">البوابة تمنع الإرسال الناقص بنيويًا (§3.1)<?php echo $rej_docs > 0 ? ' · ' . $rej_docs . ' رُفض لنقص مستند' : ''; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card fcb-mb14">
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

    <div class="card fcb-mb14">
        <div class="card-header"><h5><i class="fa fa-arrow-trend-up"></i> التصعيدات المفتوحة (§8.2)</h5></div>
        <div class="card-body">
            <?php if ($escalated): ?>
            <table class="table table-striped no-datatable" data-no-dt="1">
                <thead><tr><th>نوع الطلب</th><th>الإدارة</th><th>الحالة</th><th>الاستحقاق</th><th>المستوى</th><th></th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">عدد الطلبات</th>
              <th class="ems-fn-th" data-fn="1">متوسط زمن الحلقة الأولى</th>
              <th class="ems-fn-th" data-fn="1">الثانية</th>
              <th class="ems-fn-th" data-fn="1">الثالثة</th>
              <th class="ems-fn-th" data-fn="1">إجمالي زمن الدورة</th>
              <th class="ems-fn-th" data-fn="1">المستهدف</th>
              <th class="ems-fn-th" data-fn="1">الانحراف</th>
              <th class="ems-fn-th" data-fn="1">أطول حلقة</th>
              <th class="ems-fn-th" data-fn="1">المعتمِد الأبطأ</th>
              <th class="ems-fn-th" data-fn="1">عدد المتجاوز للمهلة</th>
              <th class="ems-fn-th" data-fn="1">نسبة الالتزام</th>
              <th class="ems-fn-th" data-fn="1">الإجراء</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
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

    <div class="card fcb-card-warn">
        <div class="card-header"><h5><i class="fa fa-gavel"></i> قائمة المساءلة الأسبوعية — المستوى ④ (§8.2)</h5></div>
        <div class="card-body">
            <?php if ($level3): ?>
                <p class="fcb-lead">كل ما بلغ المستوى الثالث يخضع لمراجعةٍ أسبوعيةٍ إلزامية (المدير المالي + مدير الإدارة المعنية):</p>
                <?php foreach ($level3 as $e): ?>
                    <div class="fcb-row">
                        ⚖️ <strong><?php echo htmlspecialchars($e['request_no']); ?></strong>
                        — <?php echo htmlspecialchars($e['source_module']); ?> · <?php echo finreq_state_badge($e['state']); ?>
                        · استحقاقه <?php echo htmlspecialchars($e['sla_due_at'] ?? '—'); ?>
                        <a href="request_form.php?id=<?php echo intval($e['id']); ?>" class="fcb-link">سجله الكامل ↗</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>✅ لا شيء بلغ المستوى الثالث<?php endif; ?>
        </div>
    </div>

    <?php if ($exc_breaches): ?>
    <div class="card fcb-card-danger">
        <div class="card-header"><h5><i class="fa fa-bolt"></i> خروقات مهلة الطارئ — 72 ساعة (§8.3)</h5></div>
        <div class="card-body">
            <?php foreach ($exc_breaches as $b): ?>
                <div class="fcb-row">
                    🚨 <strong><?php echo htmlspecialchars($b['request_no']); ?></strong>
                    — استثناءٌ معتمدٌ لم تُستكمل دورته رجعيًّا خلال 72 ساعة · <?php echo finreq_state_badge($b['state']); ?>
                    <a href="request_form.php?id=<?php echo intval($b['id']); ?>" class="fcb-link">فتح ↗</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

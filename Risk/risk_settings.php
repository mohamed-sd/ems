<?php
/**
 * Risk/risk_settings.php — إعدادات المخاطر والتصنيف (M-16 · الشاشة ١٧)
 * ─────────────────────────────────────────────────────────────────────────
 * الإطارُ الحاكمُ معروضًا مصدرًا واحدًا: مصفوفةُ السلطة (§14-2) · دورياتُ المراجعة ·
 * أبعادُ الأثرِ الثمانية (§12-2) · عناصرُ القياسِ السبعة (§12-3) · تقنياتُ التقييمِ
 * الإحدى عشرة (§12-4) · قواعدُ الإشاراتِ الستَّ عشرةَ (§13-5) وحالةُ تنفيذِ كلٍّ ·
 * ونوافذُ منعِ التكرارِ بالوحدة (ورقة 32).
 * وهي شاشةُ إعدادٍ لا شاشةُ اختراع: كلُّ رقمٍ فيها يُقرأ من مصدرِه الحيِّ (ثابتِ
 * الخدمةِ أو صفِّ القاعدة) — فلا يُكتب حكمٌ مرتين فيختلفا.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Risk/RiskService.php';
require_once __DIR__ . '/../app/Services/Risk/RiskSignalEngine.php';
require_once __DIR__ . '/../app/Services/Risk/RiskEvents.php';
ems_shell_axes($__pp);

use App\Services\Risk\RiskService;
use App\Services\Risk\RiskSignalEngine;
use App\Services\Risk\RiskEvents;

$AUTH_AR = array('risk_owner' => 'مالك الخطر نفسُه', 'owner_with_analyst' => 'المالك بمراجعة محلل المخاطر',
    'deputy' => 'النائب المختص', 'ceo' => 'الرئيس التنفيذي حصرًا');
$ESC_AR = array('منخفض' => 'لا تصعيد', 'متوسط' => 'إشعار مدير المخاطر',
    'مرتفع' => 'تصعيد لمدير المخاطر والنائب', 'حرج' => 'تصعيد فوري للرئيس في اليوم نفسه');

/* قواعدُ الإشارات: أيُّها منفَّذٌ فعلًا؟ تُقرأ من المحرّكِ لا تُكتب يدويًّا */
$engineMethods = get_class_methods('App\Services\Risk\RiskSignalEngine');
$SG_RULES = array(
    'SG-01' => 'عطل متكرر لمعدة واحدة — ثلاثة أعطال في تسعين يومًا',
    'SG-02' => 'توقف فوق الحد في وردية',
    'SG-03' => 'صيانة وقائية متأخرة — تجاوز الموعد بعشرة أيام',
    'SG-04' => 'رخصة أو وثيقة منتهية أو تنتهي — ثلاثون يومًا',
    'SG-05' => 'تأخر المورد في الإحلال — تجاوز المهلة',
    'SG-06' => 'ذمة تجاوزت الحد — متأخرة فوق ثلاثين يومًا',
    'SG-07' => 'عقد يقترب من الانتهاء بلا تجديد — تسعون يومًا',
    'SG-08' => 'انحراف كبير في سعر الصرف — تجاوز نطاق التحمل',
    'SG-09' => 'مخزون حرج تحت الحد — بلوغ حد إعادة الطلب',
    'SG-10' => 'فشل ضابط حرج — تصعيد فوري (لحظية من فعلها)',
    'SG-11' => 'تكرار بلاغ من النوع نفسه — ثلاثة في ثلاثين يومًا',
    'SG-12' => 'مخالفة حوكمة أو تجاوز حارس — رفض متكرر',
    'SG-13' => 'محاولة وصول غير معتادة — تجاوز المتوسط بضعفين',
    'SG-14' => 'واقعة كادت تقع (لحظية من فعلها)',
    'SG-15' => 'تجاوز مؤشر خطر حده الحرج (لحظية + محرك المؤشرات)',
    'SG-16' => 'انحراف التكلفة عن الميزانية — تجاوز النطاق المعتمد',
);
$INSTANT = array('SG-10' => 'RiskService::failCriticalControl',
                 'SG-14' => 'RiskService::logIncident',
                 'SG-15' => 'kri_update + RiskSignalEngine::readKris');
$sgLive = 0;
foreach ($SG_RULES as $code => $_) {
    $method = 'sg' . substr($code, 3);
    if (in_array($method, $engineMethods, true) || isset($INSTANT[$code])) { $sgLive++; }
}

/* نوافذُ التكرارِ بالوحدة */
$units = array();
$res = $conn->query("SELECT ru_code, name_ar, dedup_window_days, active FROM risk_units
                      WHERE company_id = {$company_id} ORDER BY ru_code");
while ($x = $res->fetch_assoc()) { $units[] = $x; }

/* المؤشراتُ الآليةُ ومفاتيحُ قراءتها */
$autoKris = array();
$res = $conn->query("SELECT name_ar, source_key, warn_num, critical_num, direction, current_value, kri_state, last_read_at
                       FROM risk_kris WHERE company_id = {$company_id} AND read_mode = 'آلي' AND active = 1
                      ORDER BY name_ar");
while ($x = $res->fetch_assoc()) { $autoKris[] = $x; }
$manualKris = (int) $conn->query("SELECT COUNT(*) c FROM risk_kris
                                   WHERE company_id = {$company_id} AND read_mode = 'يدوي' AND active = 1")->fetch_assoc()['c'];

$page_title = 'إيكوبيشن | إعدادات المخاطر والتصنيف';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'إعدادات المخاطر والتصنيف';
    $header_icon = 'fas fa-sliders';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'الإطار الحاكم من مصادره الحية',
        'قواعد الإشارات المنفَّذة' => $sgLive . ' من ' . count($SG_RULES),
        'أحداث منشورة معرَّفة' => RiskEvents::count(),
        'مؤشرات آلية' => count($autoKris),
        'مؤشرات يدوية' => $manualKris,
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'الإطارُ الحاكمُ معروضًا: مصفوفةُ السلطة ودورياتُ المراجعةِ وأبعادُ الأثرِ وعناصرُ القياسِ '
        . 'وتقنياتُ التقييمِ وقواعدُ الإشاراتِ ونوافذُ منعِ التكرار.',
        array('كلُّ رقمٍ هنا يُقرأ من مصدرِه الحيِّ — فلا يُكتب حكمٌ مرتين فيختلفا',
              'تعديلُ التصنيفِ ونوافذِه من شاشةِ وحداتِ المخاطر — والتعديلُ بترحيلٍ لا بحذف'));
    echo ems_states_bundle('لا إعداداتِ تصنيفٍ مبذورةً لهذا الكيان', 'الإطارُ الحاكمُ يُقرأ من مصادرِه الحيةِ — والبذرةُ المرجعيةُ شرطُ التشغيل');
    ?>
    <style>
        .rsk-w100 { width: 100%; }
        .rsk-mt12 { margin-top: 12px; }
        .rsk-forbidden-row { background: var(--danger-100); }
        .rsk-list { font-size: .84rem; margin: 0; padding-inline-start: 20px; }
        .rsk-danger-text { color: var(--c-b02a37, #b02a37); }
        .rsk-mono74 { font-size: .74rem; font-family: monospace; }
        .rsk-mono76 { font-family: monospace; font-size: .76rem; }
        .rsk-fs82 { font-size: .82rem; }
        .rsk-empty-note { font-size: .85rem; opacity: .75; }
        .rsk-footnote { font-size: .78rem; opacity: .75; margin-top: 6px; }
    </style>

    <div class="card"><div class="card-body table-responsive">
        <h6>مصفوفة الاعتماد والتصعيد (§14-2) — تُقرأ من ثابت RiskService</h6>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>الدرجة</th><th>من يقبل</th><th>دورية المراجعة</th><th>التصعيد</th></tr></thead>
            <tbody>
            <?php foreach (RiskService::AUTHORITY_MATRIX as $lv => $auth): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($lv); ?></strong></td>
                    <td><?php echo htmlspecialchars($AUTH_AR[$auth] ?? $auth); ?></td>
                    <td><?php echo (int) (RiskService::REVIEW_DAYS[$lv] ?? 0); ?> يومًا</td>
                    <td><?php echo htmlspecialchars($ESC_AR[$lv] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="rsk-forbidden-row">
                    <td><strong>محظور</strong></td>
                    <td><strong>لا يُقبل بحال</strong> — غائبٌ من المصفوفة عمدًا (RK-04)</td>
                    <td>—</td>
                    <td>تصعيد فوري وإيقاف النشاط</td>
                </tr>
            </tbody>
        </table>
    </div></div>

    <div class="row rsk-mt12">
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>أبعاد الأثر الثمانية (§12-2) — لا تُدمج في رقم واحد</h6>
            <ol class="rsk-list">
                <?php foreach ($RISK_IMPACT_DIMS as $k => $v): ?>
                <li><?php echo htmlspecialchars($v); ?>
                    <?php if ($k === 'safety'): ?><span class="rsk-danger-text">— لا يُقايَض بالمال</span><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
        </div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>عناصر القياس السبعة (§12-3)</h6>
            <ol class="rsk-list">
                <li>الخطر المتأصل — قبل أي ضابط</li>
                <li>فعالية الضوابط — بدليل تحقق لا بادعاء</li>
                <li>الخطر المتبقي — عليه يُبنى قرار القبول</li>
                <li>الخطر المستهدف — يُقاس عند الإغلاق</li>
                <li>سرعة التحقق — تُغيّر الأولوية لا الدرجة</li>
                <li>الأفق الزمني — للتخطيط والتحوّط</li>
                <li>درجة الثقة — تُعلَن ولا تُخفى</li>
            </ol>
        </div></div></div>
    </div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>قواعد إشارات الخطر الست عشرة (§13-5) — وحالة تنفيذ كل قاعدة</h6>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>الرمز</th><th>القاعدة</th><th>الوحدة</th><th>المنفِّذ</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($SG_RULES as $code => $desc):
                $method = 'sg' . substr($code, 3);
                $periodic = in_array($method, $engineMethods, true);
                $instant = isset($INSTANT[$code]);
                $impl = $periodic ? 'RiskSignalEngine::' . $method : ($instant ? $INSTANT[$code] : '—'); ?>
                <tr>
                    <td><strong><?php echo $code; ?></strong></td>
                    <td class="rsk-fs82"><?php echo htmlspecialchars($desc); ?></td>
                    <td><?php echo htmlspecialchars(RiskSignalEngine::RULE_UNIT[$code] ?? '—'); ?></td>
                    <td class="rsk-mono74"><?php echo htmlspecialchars($impl); ?></td>
                    <td><?php if ($periodic || $instant): ?>
                        <span class="badge badge-success">منفَّذة<?php echo $instant && !$periodic ? ' (لحظية)' : ' (دورية)'; ?></span>
                        <?php else: ?><span class="badge badge-secondary">غير منفَّذة</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>المؤشرات الآلية ومفاتيح قراءتها (المرحلة ١٢) — «تُقرأ من النظام آليًّا»</h6>
        <?php if (empty($autoKris)): ?>
        <p class="rsk-empty-note">لا مؤشرات آلية — والمرحلةُ الثانيةَ عشرةَ تلزم قراءةً آليةً لا يدوية.</p>
        <?php else: ?>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>المؤشر</th><th>مفتاح القارئ</th><th>الاتجاه</th>
                <th>حد الإنذار</th><th>الحد الحرج</th><th>القيمة</th><th>الحالة</th><th>آخر قراءة</th></tr></thead>
            <tbody>
            <?php foreach ($autoKris as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td class="rsk-mono76"><?php echo htmlspecialchars((string) $x['source_key']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['direction']); ?></td>
                    <td><?php echo $x['warn_num'] === null ? '—' : (float) $x['warn_num']; ?></td>
                    <td><?php echo $x['critical_num'] === null ? '—' : (float) $x['critical_num']; ?></td>
                    <td><?php echo htmlspecialchars((string) $x['current_value'] ?: '—'); ?></td>
                    <td><?php $s = (string) $x['kri_state'];
                        $cls = $s === 'critical' ? 'badge-danger' : ($s === 'warn' ? 'badge-warning' : 'badge-success'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($s); ?></span></td>
                    <td><?php echo htmlspecialchars((string) $x['last_read_at'] ?: 'لم تُقرأ بعد'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="rsk-footnote">
            القراءةُ من نبضةِ <code>tools/m16_risk_cron.php</code> — والمؤشرُ اليدويُّ يُعلَن يدويًّا ولا يُدَّعى آليًّا.</p>
        <?php endif; ?>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>نوافذ منع التكرار بالوحدة (ورقة 32) — «الإدارة العارضة لا تدخل المفتاح»</h6>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>الوحدة</th><th>الاسم</th><th>النافذة</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($units as $x): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($x['ru_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td><?php echo (int) $x['dedup_window_days']; ?> يومًا</td>
                    <td><?php echo (int) $x['active'] === 1 ? 'نشطة' : 'معطَّلة'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="rsk-footnote">
            المفتاح: وحدة + كيان + سبب جذري + نطاق — داخل نافذة الوحدة.
            <a href="risk_units.php">تعديل النوافذ</a></p>
    </div></div>
</div>
</body>
</html>

<?php
/**
 * Risk/risk_reports.php — تقارير المخاطر والتحليلات (M-16 · الشاشة ١٦)
 * ─────────────────────────────────────────────────────────────────────────
 * §8 التصنيفُ الرباعيُّ لا الثنائي: «التصديرُ لا يغيّر بيانَ المجالِ لكنه يكتب
 * سجلَّ تصديرٍ بتسعةِ بنودٍ إلزامًا — فليس قارئًا لا يكتب». فكلُّ تصديرٍ من هنا
 * يمرُّ بـRSK-REPORT-EXPORT ويسجّل: المصدِّرَ بصفته · المنظرَ · الأعمدةَ ·
 * الفلاترَ · المستبعَدَ بالصلاحيةِ · العددَ · الوقت.
 * والحقولُ الحساسةُ تُحجب من الخادمِ لا بأسلوبِ العرض (§6-3 · AC-06).
 */
require_once __DIR__ . '/_risk_common.php';
require_once __DIR__ . '/../includes/w14_grid.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT, 'rr');

/* ① الحصيلةُ بالوحدة — المقامُ معلَنٌ: المفتوحُ غيرُ المدموج */
$byUnit = array();
$res = $conn->query("SELECT ru.ru_code, ru.name_ar,
                            COUNT(*) total,
                            SUM(rr.state <> 'closed') open_n,
                            SUM(rr.current_level IN ('حرج','محظور')) critical_n,
                            SUM(rr.appetite_verdict IN ('فوق الشهية','فوق حد التحمل','محظور')) over_n,
                            SUM(rr.review_due < CURDATE() AND rr.state <> 'closed') late_n
                       FROM risk_register rr
                       JOIN risk_units ru ON ru.id = rr.ru_id AND ru.company_id = rr.company_id
                      WHERE rr.company_id = {$company_id} AND rr.merged_into_id IS NULL {$scopeSql}
                      GROUP BY ru.ru_code, ru.name_ar ORDER BY ru.ru_code");
while ($x = $res->fetch_assoc()) { $byUnit[] = $x; }

/* ② الحصيلةُ بالمستوى */
$byLevel = array();
$res = $conn->query("SELECT COALESCE(NULLIF(rr.current_level,''),'لم يقيَّم') lv, COUNT(*) n
                       FROM risk_register rr
                      WHERE rr.company_id = {$company_id} AND rr.merged_into_id IS NULL
                        AND rr.state <> 'closed' {$scopeSql}
                      GROUP BY lv");
while ($x = $res->fetch_assoc()) { $byLevel[$x['lv']] = (int) $x['n']; }

/* ③ فعاليةُ الضوابط */
$ctlEff = array();
$res = $conn->query("SELECT effectiveness, COUNT(*) n FROM risk_controls
                      WHERE company_id = {$company_id} AND active = 1 GROUP BY effectiveness");
while ($x = $res->fetch_assoc()) { $ctlEff[$x['effectiveness']] = (int) $x['n']; }

/* ④ سجلُّ التصديرِ الأخير (§9-4) — الشاهدُ على أن التصديرَ يكتب */
$exports = array();
$res = $conn->query("SELECT e.*, u.name exporter FROM risk_export_log e
                       LEFT JOIN users u ON u.id = e.exported_by
                      WHERE e.company_id = {$company_id}
                      ORDER BY e.id DESC LIMIT 25");
while ($x = $res->fetch_assoc()) { $exports[] = $x; }

/* الحقولُ الحساسةُ المستبعَدةُ بالصلاحية — تُعلَن في السجلِّ لا تُخفى */
$blocked = $RISK_FULL ? '' : 'الإدارة المالكة · مالك الخطر · مالك الضابط · قيمة المؤشر';

$page_title = 'إيكوبيشن | تقارير المخاطر والتحليلات';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تقارير المخاطر والتحليلات';
    $header_icon = 'fas fa-chart-pie';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'المفتوح غير المدموج في نطاقك',
        'وحدات بمخاطر' => count($byUnit),
        'مرات التصدير' => count($exports),
        'المستبعَد بالصلاحية' => $blocked === '' ? 'لا شيء' : 'حقول حساسة',
    );
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_rsk_risk_reports
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g84',
            'الدورية' => 'g85',
            'الفترة' => 'g86',
            'العائلة' => 'g87',
            'البند' => 'g88',
            'القيمة' => 'g89',
            'الاتجاه' => 'g90',
            'يستلزم قرارا؟' => 'g91',
            'مرجع الخطر' => 'g92',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('rsk_risk_reports');
        echo ems_w14_grid('emsList_rsk_risk_reports', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تقارير المخاطر الدورية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    ems_screen_about(
        'تقارير الحصيلة بالوحدة والمستوى وفعالية الضوابط. والتصدير ليس قارئا لا يكتب: '
        . 'كل ملف يخرج يسجل تسعة بنود في سجل التصدير (§9-4).',
        array('الحقول الحساسة محجوبة من الخادم لغير المخول — والمستبعد يعلن في السجل',
              'المقام معلن في كل رقم — ولا رقم بلا مقام يفسره'));
    echo ems_states_bundle('لا حصيلة مخاطر في نطاقك بعد', 'الحصيلة تبنى من السجل المفتوح غير المدموج — وتتسع باتساع نطاقك');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div class="row rsk-stat-row">
        <?php foreach (array('محظور' => 'badge-danger', 'حرج' => 'badge-danger', 'مرتفع' => 'badge-warning',
                             'متوسط' => 'badge-secondary', 'منخفض' => 'badge-secondary', 'لم يقيَّم' => 'badge-light') as $lv => $cls):
            $n = $byLevel[$lv] ?? 0; if ($n === 0 && $lv === 'محظور') { continue; } ?>
        <div class="col-md-2"><div class="ems-card rsk-stat-card">
            <div class="rsk-stat-num"><?php echo $n; ?></div>
            <div class="rsk-stat-label"><?php echo $lv; ?></div>
        </div></div>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body table-responsive">
        <h6>الحصيلة بوحدة المخاطر</h6>
        <?php if (empty($byUnit)): ?>
        <p class="rsk-empty-note">لا مخاطر في نطاقك — لا حصيلة تعرض.</p>
        <?php else: ?>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>الوحدة</th><th>الاسم</th><th>الإجمالي</th><th>مفتوح</th>
                <th>حرج/محظور</th><th>فوق الشهية</th><th>مراجعة متأخرة</th></tr></thead>
            <tbody>
            <?php foreach ($byUnit as $x): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($x['ru_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td><?php echo (int) $x['total']; ?></td>
                    <td><?php echo (int) $x['open_n']; ?></td>
                    <td><?php echo (int) $x['critical_n']; ?></td>
                    <td><?php echo (int) $x['over_n']; ?></td>
                    <td><?php echo (int) $x['late_n']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body">
        <h6>فعالية الضوابط — «لا يحتسب إلا بإثبات فعاليته» (RK-07)</h6>
        <table class="table table-sm rsk-maxw520">
            <tbody>
            <?php foreach (array('فعال', 'فعال جزئيا', 'غير فعال', 'غير مثبت') as $ef): ?>
                <tr><td><?php echo $ef; ?></td><td><strong><?php echo $ctlEff[$ef] ?? 0; ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form id="expForm" class="rsk-mt8">
            <button type="submit" class="ems-btn-primary">
                <i class="fa fa-file-export"></i> تصدير الحصيلة (RSK-REPORT-EXPORT)</button>
            <span id="expMsg" class="rsk-msg"></span>
        </form>
    </div></div>

    <div class="card rsk-mt12"><div class="card-body table-responsive">
        <h6>سجل التصدير — تسعة بنود لكل ملف يخرج (§9-4)</h6>
        <?php if (empty($exports)): ?>
        <p class="rsk-empty-note">لا تصدير بعد — والسجل يكتب بأول تصدير.</p>
        <?php else: ?>
        <table class="table table-sm table-striped rsk-w100">
            <thead><tr><th>المصدر</th><th>بصفته</th><th>الشاشة</th><th>المنظر</th>
                <th>الأعمدة</th><th>الفلاتر</th><th>المستبعد بالصلاحية</th><th>الصفوف</th><th>الوقت</th></tr></thead>
            <tbody>
            <?php foreach ($exports as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) $x['exporter'] ?: '—'); ?></td>
                    <td class="rsk-fs76"><?php echo htmlspecialchars((string) $x['actor_capacity']); ?></td>
                    <td class="rsk-fs76"><?php echo htmlspecialchars((string) $x['screen_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['view_key']); ?></td>
                    <td class="rsk-cell-w220"><?php echo htmlspecialchars(mb_substr((string) $x['columns_text'], 0, 90)); ?></td>
                    <td class="rsk-fs72"><?php echo htmlspecialchars(mb_substr((string) $x['filters_text'], 0, 60) ?: '—'); ?></td>
                    <td class="rsk-blocked-cell"><?php echo htmlspecialchars((string) $x['blocked_text'] ?: 'لا شيء'); ?></td>
                    <td><?php echo (int) $x['row_count']; ?></td>
                    <td><?php echo htmlspecialchars((string) $x['exported_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('expForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData();
            fd.append('do', 'report_export');
            fd.append('screen_code', 'Risk/risk_reports.php');
            fd.append('view_key', 'unit_summary');
            fd.append('columns_text', 'الوحدة · الاسم · الإجمالي · مفتوح · حرج/محظور · فوق الشهية · مراجعة متأخرة');
            fd.append('filters_text', <?php echo json_encode($RISK_FULL ? 'المحفظة الكاملة' : 'زاوية إدارتك', JSON_UNESCAPED_UNICODE); ?>);
            fd.append('blocked_text', <?php echo json_encode($blocked, JSON_UNESCAPED_UNICODE); ?>);
            fd.append('row_count', '<?php echo count($byUnit); ?>');
            fd.append('fmt', 'xlsx');
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) {
                var m = document.getElementById('expMsg');
                if (j.ok) { m.textContent = '✔ سجل التصدير #' + j.id + ' بتسعة بنود'; setTimeout(function () { location.reload(); }, 900); }
                else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
            });
        });
    });
    </script>
</div>
</body>
</html>

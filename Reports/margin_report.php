<?php
/**
 * Reports/margin_report.php — هامشُ الواقعة والعقد (M-07 · الشاشة 202)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-03 §5: التقريرُ **من الاعترافات الثلاثة** (`unit_party_awards`:
 * إيرادُ العميل − تكلفةُ المورد − استحقاقُ المشغّل) — «البياناتُ كلُّها
 * موجودةٌ والتقريرُ غائب». قراءةٌ واشتقاقٌ بلا أثر — **ولا تُجمع عملتان
 * في رقم** (التعدُّدُ يُعلَن).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$co = $company_id;

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-01-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
$f = $conn->real_escape_string($from);
$t = $conn->real_escape_string($to);

// الاعترافاتُ الثلاثةُ مجمَّعةً بالعقد والعملة — كلُّ طرفٍ من حكمه هو
$rows = array();
$r = $conn->query("SELECT a.contract_ref, a.currency,
        ROUND(SUM(CASE WHEN a.party='client'   THEN a.qty_due * a.unit_price ELSE 0 END),2) revenue,
        ROUND(SUM(CASE WHEN a.party='supplier' THEN a.qty_due * a.unit_price ELSE 0 END),2) supplier_cost,
        ROUND(SUM(CASE WHEN a.party='operator' THEN a.qty_due * a.unit_price ELSE 0 END),2) operator_cost,
        COUNT(DISTINCT a.source_ref) events_n
   FROM unit_party_awards a
  WHERE a.company_id={$co} AND a.deleted_at IS NULL
    AND DATE(a.created_at) BETWEEN '{$f}' AND '{$t}'
  GROUP BY a.contract_ref, a.currency
  ORDER BY revenue DESC");
while ($r && ($x = $r->fetch_assoc())) {
    $x['margin'] = round((float)$x['revenue'] - (float)$x['supplier_cost'] - (float)$x['operator_cost'], 2);
    $x['margin_pct'] = (float)$x['revenue'] > 0
        ? round(100.0 * $x['margin'] / (float)$x['revenue'], 1) : null;
    $rows[] = $x;
}
// تعدُّدُ العملات لعقدٍ واحدٍ يُعلَن — «لا تُجمع عملتان في رقم»
$byContract = array(); $multiCurrency = array();
foreach ($rows as $x) {
    $cid = (string) ($x['contract_ref'] ?? '—');
    $byContract[$cid] = ($byContract[$cid] ?? 0) + 1;
    if ($byContract[$cid] > 1) { $multiCurrency[$cid] = true; }
}

/* INJ-0150: قرارُ رؤيةِ الهامشِ يُحسب مرةً للصفحةِ كلِّها — لا لكلِّ صفٍّ،
   وإلا كُتب سطرُ اطّلاعٍ لكلِّ سطرٍ في الجدولِ فأغرق السجل. */
require_once __DIR__ . '/../includes/field_visibility.php';
$__seeMargin = ems_may_see_field($conn, 'contract.margin', 'margin_report:' . date('Ymd'),
    'Reports/margin_report.php');

$page_title = 'إيكوبيشن | هامش الواقعة والعقد';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'هامش الواقعة والعقد'; $header_icon = 'fa fa-percent';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('الهامشُ من الاعترافات الثلاثة للواقعة الواحدة (حكمُ العميل − حكمُ المورد − '
        . 'حكمُ المشغّل) مجمَّعًا بالعقد والعملة — كلُّ رقمٍ من سجل الأحكام لا من تقدير. '
        . 'وعقدٌ بعملتين يظهر سطرين معلَنين لا رقمًا ملفَّقًا.',
        array('حدّد الفترة', 'اقرأ الهامشَ السالبَ أولًا — هو القرار'));
    // UXW-01 ⑨: حالتا التحميلِ والخطأِ (حالةُ الفراغِ قائمةٌ أسفلُ) — مخفيتانِ افتراضًا
    echo ems_state('loading', 'جارٍ حسابُ الهامشِ من أحكامِ الوقائع', '', '', true);
    echo ems_state('error', 'تعذّر عرضُ تقريرِ الهامش', 'أعد المحاولةَ — وإن استمر الخللُ أبلغ عن مشكلةٍ من هذه الشاشة', '', true);
    ?>

    <div class="card"><div class="card-body">
                <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
        <div class="filter">
            <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
            <div class="filter-body">
        <form method="get" class="rpt-mrg-filter">
            <label for="emsf_463_167fb">من</label><input type="date" name="from" id="emsf_463_167fb" value="<?php echo htmlspecialchars($from); ?>">
            <label for="emsf_464_398bb">إلى</label><input type="date" name="to" id="emsf_464_398bb" value="<?php echo htmlspecialchars($to); ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اعرض</button>
        </form>
            </div>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <?php if (!$rows): ems_state_empty('لا اعترافاتٍ في الفترة — الأحكامُ تولَد مع اعتماد الوحدات',
            'وسّع المدة', '?from=' . date('Y-01-01', strtotime('-1 year')) . '&to=' . $to); else: ?>
        <div class="table-container"><table class="alltables display nowrap rpt-mrg-table">
            <thead><tr><th>العقد</th><th>العملة</th><th>الوقائع</th>
                <th>إيراد العميل</th><th>تكلفة المورد</th><th>استحقاق المشغّل</th>
                <th>الهامش</th><th>الهامش٪</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">الفترة</th>
                <th class="ems-fn-th" data-fn="1">المشروع</th>
                <th class="ems-fn-th" data-fn="1">الوحدة</th>
                <th class="ems-fn-th" data-fn="1">الإيراد المعترَف به</th>
                <th class="ems-fn-th" data-fn="1">تكلفة المشغّلين</th>
                <th class="ems-fn-th" data-fn="1">تكلفة الوقود</th>
                <th class="ems-fn-th" data-fn="1">تكلفة الصيانة</th>
                <th class="ems-fn-th" data-fn="1">تكلفة المخزون</th>
                <th class="ems-fn-th" data-fn="1">تكلفة الترحيل</th>
                <th class="ems-fn-th" data-fn="1">تكلفة التمويل</th>
                <th class="ems-fn-th" data-fn="1">الإهلاك</th>
                <th class="ems-fn-th" data-fn="1">إجمالي التكلفة</th>
                <th class="ems-fn-th" data-fn="1">نسبة الهامش</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): $cid = (string)($x['contract_ref'] ?? '—'); ?>
                <tr class="<?php echo $x['margin'] < 0 ? 'rpt-mrg-neg-row' : ''; ?>">
                    <td><a href="../Contracts/contract_card.php?id=<?php echo intval($cid); ?>">عقد #<?php
                        echo htmlspecialchars($cid); ?> ▸</a>
                        <?php if (isset($multiCurrency[$cid])): ?>
                            <span class="badge badge-warning" title="عقدٌ بعملتين — سطران لا رقم">⚠ عملات</span>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$x['currency']); ?></td>
                    <td><?php echo intval($x['events_n']); ?></td>
                    <?php /* ── INJ-0150 · السعرُ والهامشُ لا يعبرانِ بلا منحةٍ فردية ──────
                             نصُّ القبول: «دورٌ بلا منحةٍ فرديةٍ **لا يجد حقولَ السعر
                             والهامش في استجابة الخادم**؛ وكلُّ اطّلاعٍ مخوَّلٍ يكتب
                             صفًّا في سجل الاطّلاع». والهامشُ أشدُّ رقمٍ في النظام:
                             يكشف ما يُربَح على كلِّ عميلٍ ومورّد. */ ?>
                    <?php if ($__seeMargin): ?>
                    <td><?php echo htmlspecialchars((string)$x['revenue']); ?></td>
                    <td><?php echo htmlspecialchars((string)$x['supplier_cost']); ?></td>
                    <td><?php echo htmlspecialchars((string)$x['operator_cost']); ?></td>
                    <td><strong class="<?php echo $x['margin'] < 0 ? 'cd-bal-neg' : 'cd-bal-pos'; ?>">
                        <?php echo htmlspecialchars((string)$x['margin']); ?></strong></td>
                    <td><?php echo $x['margin_pct'] !== null
                        ? htmlspecialchars($x['margin_pct'] . '٪') : '—'; ?></td>
                    <?php else: ?>
                    <td class="ems-field-withheld" title="محجوبٌ — يحتاج منحةً فردية">—</td>
                    <td class="ems-field-withheld">—</td>
                    <td class="ems-field-withheld">—</td>
                    <td class="ems-field-withheld">—</td>
                    <td class="ems-field-withheld">—</td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <p class="rpt-mrg-note">استحقاقُ المشغّل هنا من أحكام الوقائع (M-24) —
            وعقودُ الاستعداد المقطوعة ليست فيه (لا سعرَ ساعةٍ لها) فهامشُها يقرؤه كشفُ الرواتب.</p>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

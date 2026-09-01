<?php
/**
 * Finance/financial_statements_fin.php — القوائم المالية النظامية.
 * ★ قراءة فقط بالكامل ★ — تُشتَقّ من القيود المرحّلة (fin_journal_lines + الحسابات).
 * قائمة الدخل · المركز المالي (يتوازن آليًا) · التدفق النقدي (من المدفوعات المنفّذة).
 * لا جداول جديدة، لا كتابة، لا لمس للنظام القائم.
 * ★ الجداول الثلاثة بلا DataTables قصدًا (no-datatable) ★ — صفوفُ الأقسام والمجاميع
 * ممتدّةٌ بـcolspan داخل tbody فلا يوافق عددُ خلاياها ترويسةَ الجدول: التهيئة التلقائية
 * كانت ترمي «Incorrect column count» (tn/18) في نافذتَي تنبيهٍ حاجزتين عند كل فتح،
 * والفرزُ/الترقيمُ/البحثُ يُبعثر أقسامَ القائمة عن مجاميعها. القائمة تُعرَض كما تُشتَقّ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/financial_statements_fin.php', $is_super_admin);
if (!$perms['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية العرض ❌', 'GOV-PERM-403', ''); exit(); }

$cid = intval($company_id);

/**
 * أرصدة الحسابات من القيود المرحّلة عبر scopedQuery (§10).
 * تكافؤ SQL مقصود: الأصل INNER JOIN بدلالة فلترة في ON → هنا LEFT JOIN + الفلاتر
 * في WHERE (je.state='posted' يستبعد صفوف je=NULL، a.id IS NOT NULL يستبعد بلا حساب)
 * = نفس النتيجة حرفيًا، ويوافق شرط البوابة «الإثراء LEFT حصرًا». العزل على jl.
 */
function fin_ledger_balances($is_super)
{
    return fin_gate($is_super)->scopedQuery(
        array('scope' => array('jl' => 'fin_journal_lines'),
              'enrich' => array('je' => 'fin_journal_entries', 'a' => 'fin_chart_of_accounts')),
        "SELECT a.id, a.code, a.name, a.account_type,
                COALESCE(SUM(jl.debit),0) AS dr, COALESCE(SUM(jl.credit),0) AS cr
         FROM fin_journal_lines jl
         LEFT JOIN fin_journal_entries je ON je.id = jl.entry_id
         LEFT JOIN fin_chart_of_accounts a ON a.id = jl.account_id
         WHERE {TENANT_SCOPE} AND je.state='posted' AND COALESCE(je.is_deleted,0)=0 AND a.id IS NOT NULL
         GROUP BY a.id, a.code, a.name, a.account_type
         HAVING dr <> 0 OR cr <> 0
         ORDER BY a.code ASC");
}

$bal = fin_ledger_balances($is_super_admin);
// تجميع حسب النوع (رصيد طبيعي)
$T = array('asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0);
$byType = array('asset' => array(), 'liability' => array(), 'equity' => array(), 'revenue' => array(), 'expense' => array());
foreach ($bal as $b) {
    $t = $b['account_type']; $dr = (float)$b['dr']; $cr = (float)$b['cr'];
    // أصول/مصروف: رصيد مدين (dr-cr) · خصوم/حقوق/إيراد: رصيد دائن (cr-dr)
    $natural = in_array($t, array('asset', 'expense'), true) ? ($dr - $cr) : ($cr - $dr);
    $b['balance'] = $natural; $T[$t] += $natural; $byType[$t][] = $b;
}
$net_profit = $T['revenue'] - $T['expense'];
$total_assets = $T['asset'];
$total_liab_equity = $T['liability'] + $T['equity'] + $net_profit; // صافي الربح ضمن حقوق الملكية (أرباح محتجزة)
$balanced = (round($total_assets, 2) === round($total_liab_equity, 2));

// التدفق النقدي (مبسّط) من المدفوعات المنفّذة — مجاميع مُعزَّلة عبر scopedQuery
$fin_pay_sum = function ($dir) use ($is_super_admin) {
    $r = fin_gate($is_super_admin)->scopedQuery(array('scope' => array('p' => 'fin_payments')),
        "SELECT COALESCE(SUM(p.amount),0) v FROM fin_payments p WHERE {TENANT_SCOPE}
         AND COALESCE(p.is_deleted,0)=0 AND p.state IN('executed','reconciled') AND p.direction=?",
        array($dir));
    return $r ? (float) $r[0]['v'] : 0.0;
};
$inflow  = $fin_pay_sum('collection');
$outflow = $fin_pay_sum('disbursement');
$net_cash = $inflow - $outflow;

$type_lbl = fin_account_types();

$page_title = 'إيكوبيشن | القوائم المالية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

function fin_stmt_rows($rows, $type_lbl)
{
    $out = '';
    foreach ($rows as $r) {
        $out .= "<tr><td>" . htmlspecialchars($r['code']) . "</td><td>" . htmlspecialchars($r['name']) . "</td>"
              . "<td class='fin-stmt-num'>" . number_format((float)$r['balance'], 2) . "</td></tr>";
    }
    return $out;
}
?>
<div class="main fin-stmt-main ems-unified-page-shell">
    <?php
    $header_title = 'القوائم المالية النظامية'; $header_icon = 'fa fa-file-invoice';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g212',
            'الفترة' => 'g213',
            'القائمة' => 'g214',
            'البند' => 'g215',
            'القيمة' => 'g216',
            'فترة المقارنة' => 'g217',
            'التغير' => 'g218',
            'ملاحظة إفصاح' => 'g219',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fina_financial_statements_fin');
        echo ems_w14_grid('emsList_fina_financial_statements_fin', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في القوائم المالية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا قيود مرحلة تشتق منها القوائم بعد', 'رحل قيدا واحدا على الأقل من دفتر اليومية لتظهر القوائم الثلاث');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <p class="text-muted fin-stmt-note"><i class="fas fa-circle-info"></i> مشتقة من <strong>القيود المرحلة فقط</strong> · عملة الأساس SDG · المركز المالي يتوازن آليا (نتيجة القيد المزدوج).</p>

    <!-- قائمة الدخل -->
    <div class="card"><div class="card-body">
        <h5 class="fin-stmt-h5"><i class="fas fa-arrow-trend-up"></i> قائمة الدخل (الأرباح والخسائر)</h5>
        <div class="table-container"><table class="alltables no-datatable fin-stmt-tbl" data-no-dt="1">
            <thead><tr><th>الكود</th><th>رقم الحساب</th><th class="fin-stmt-num">المبلغ</th></tr></thead>
            <tbody>
                <tr><th colspan="3" class="fin-stmt-sec-rev">الإيرادات</th></tr>
                <?php echo fin_stmt_rows($byType['revenue'], $type_lbl); ?>
                <tr><th colspan="2">إجمالي الإيرادات</th><th class="fin-stmt-num"><?php echo number_format($T['revenue'], 2); ?></th></tr>
                <tr><th colspan="3" class="fin-stmt-sec-neg">المصروفات</th></tr>
                <?php echo fin_stmt_rows($byType['expense'], $type_lbl); ?>
                <tr><th colspan="2">إجمالي المصروفات</th><th class="fin-stmt-num"><?php echo number_format($T['expense'], 2); ?></th></tr>
            </tbody>
            <tfoot><tr><th colspan="2">صافي الربح / (الخسارة)</th>
                <th class="fin-stmt-num"><span class="badge badge-<?php echo $net_profit >= 0 ? 'success' : 'danger'; ?>"><?php echo number_format($net_profit, 2); ?></span></th></tr></tfoot>
        </table></div>
    </div></div>

    <!-- المركز المالي -->
    <div class="card"><div class="card-body">
        <h5 class="fin-stmt-h5"><i class="fas fa-scale-balanced"></i> قائمة المركز المالي (الميزانية العمومية)</h5>
        <div class="table-container"><table class="alltables no-datatable fin-stmt-tbl" data-no-dt="1">
            <thead><tr><th>الكود</th><th>الحساب</th><th class="fin-stmt-num">الرصيد الحالي</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">نوع القائمة</th>
              <th class="ems-fn-th" data-fn="1">البند</th>
              <th class="ems-fn-th" data-fn="1">رصيد الفترة السابقة</th>
              <th class="ems-fn-th" data-fn="1">التغير</th>
              <th class="ems-fn-th" data-fn="1">نسبة التغير</th>
              <th class="ems-fn-th" data-fn="1">المستوى</th>
              <th class="ems-fn-th" data-fn="1">البند الأب</th>
              <th class="ems-fn-th" data-fn="1">حالة الإقفال</th>
              <th class="ems-fn-th" data-fn="1">أعدها</th>
              <th class="ems-fn-th" data-fn="1">راجعها</th>
              <th class="ems-fn-th" data-fn="1">اعتمدها</th>
              <th class="ems-fn-th" data-fn="1">المراجع الخارجي</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
            <tbody>
                <tr><th colspan="3" class="fin-stmt-sec-ast">الأصول</th></tr>
                <?php echo fin_stmt_rows($byType['asset'], $type_lbl); ?>
                <tr><th colspan="2">إجمالي الأصول</th><th class="fin-stmt-num"><?php echo number_format($total_assets, 2); ?></th></tr>
                <tr><th colspan="3" class="fin-stmt-sec-neg">الخصوم</th></tr>
                <?php echo fin_stmt_rows($byType['liability'], $type_lbl); ?>
                <tr><th colspan="3" class="fin-stmt-sec-eqt">حقوق الملكية</th></tr>
                <?php echo fin_stmt_rows($byType['equity'], $type_lbl); ?>
                <tr><td>—</td><td>الأرباح المحتجزة (نتيجة الفترة)</td><td class="fin-stmt-num"><?php echo number_format($net_profit, 2); ?></td></tr>
                <tr><th colspan="2">إجمالي الخصوم + حقوق الملكية</th><th class="fin-stmt-num"><?php echo number_format($total_liab_equity, 2); ?></th></tr>
            </tbody>
            <tfoot><tr><th colspan="2">التوازن (الأصول = الخصوم + حقوق الملكية)</th>
                <th class="fin-stmt-num"><span class="badge badge-<?php echo $balanced ? 'success' : 'danger'; ?>"><?php echo $balanced ? 'متوازن ✔' : 'غير متوازن ✘'; ?></span></th></tr></tfoot>
        </table></div>
    </div></div>

    <!-- التدفق النقدي -->
    <div class="card"><div class="card-body">
        <h5 class="fin-stmt-h5"><i class="fas fa-water"></i> قائمة التدفق النقدي (مبسطة — من المدفوعات المنفذة)</h5>
        <div class="table-container"><table class="alltables no-datatable fin-stmt-tbl" data-no-dt="1">
            <tbody>
                <tr><td>التدفق النقدي الداخل (تحصيل)</td><td class="fin-stmt-num"><?php echo number_format($inflow, 2); ?></td></tr>
                <tr><td>التدفق النقدي الخارج (صرف)</td><td class="fin-stmt-num">(<?php echo number_format($outflow, 2); ?>)</td></tr>
            </tbody>
            <tfoot><tr><th>صافي التدفق النقدي</th>
                <th class="fin-stmt-num"><span class="badge badge-<?php echo $net_cash >= 0 ? 'success' : 'danger'; ?>"><?php echo number_format($net_cash, 2); ?></span></th></tr></tfoot>
        </table></div>
    </div></div>
</div>
</body>
</html>

<?php
/**
 * Finance/financial_statements_fin.php — القوائم المالية النظامية.
 * ★ قراءة فقط بالكامل ★ — تُشتَقّ من القيود المرحّلة (fin_journal_lines + الحسابات).
 * قائمة الدخل · المركز المالي (يتوازن آليًا) · التدفق النقدي (من المدفوعات المنفّذة).
 * لا جداول جديدة، لا كتابة، لا لمس للنظام القائم.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/financial_statements_fin.php', $is_super_admin);
if (!$perms['can_view']) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+العرض+❌"); exit(); }

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
include '../inheader.php';
include '../insidebar.php';

function fin_stmt_rows($rows, $type_lbl)
{
    $out = '';
    foreach ($rows as $r) {
        $out .= "<tr><td>" . htmlspecialchars($r['code']) . "</td><td>" . htmlspecialchars($r['name']) . "</td>"
              . "<td style='text-align:end'>" . number_format((float)$r['balance'], 2) . "</td></tr>";
    }
    return $out;
}
?>
<div class="main fin-stmt-main ems-unified-page-shell">
    <?php
    $header_title = 'القوائم المالية النظامية'; $header_icon = 'fa fa-file-invoice';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <p class="text-muted" style="margin:4px 2px 10px"><i class="fas fa-circle-info"></i> مُشتَقّة من <strong>القيود المرحّلة فقط</strong> · عملة الأساس SDG · المركز المالي يتوازن آليًا (نتيجة القيد المزدوج).</p>

    <!-- قائمة الدخل -->
    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-arrow-trend-up"></i> قائمة الدخل (الأرباح والخسائر)</h5>
        <div class="table-container"><table class="alltables" style="width:100%">
            <thead><tr><th>الكود</th><th>الحساب</th><th style="text-align:end">المبلغ</th></tr></thead>
            <tbody>
                <tr><th colspan="3" style="background:#f0fdf4">الإيرادات</th></tr>
                <?php echo fin_stmt_rows($byType['revenue'], $type_lbl); ?>
                <tr><th colspan="2">إجمالي الإيرادات</th><th style="text-align:end"><?php echo number_format($T['revenue'], 2); ?></th></tr>
                <tr><th colspan="3" style="background:#fef2f2">المصروفات</th></tr>
                <?php echo fin_stmt_rows($byType['expense'], $type_lbl); ?>
                <tr><th colspan="2">إجمالي المصروفات</th><th style="text-align:end"><?php echo number_format($T['expense'], 2); ?></th></tr>
            </tbody>
            <tfoot><tr><th colspan="2">صافي الربح / (الخسارة)</th>
                <th style="text-align:end"><span class="badge badge-<?php echo $net_profit >= 0 ? 'success' : 'danger'; ?>"><?php echo number_format($net_profit, 2); ?></span></th></tr></tfoot>
        </table></div>
    </div></div>

    <!-- المركز المالي -->
    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-scale-balanced"></i> قائمة المركز المالي (الميزانية العمومية)</h5>
        <div class="table-container"><table class="alltables" style="width:100%">
            <thead><tr><th>الكود</th><th>الحساب</th><th style="text-align:end">الرصيد</th></tr></thead>
            <tbody>
                <tr><th colspan="3" style="background:#eff6ff">الأصول</th></tr>
                <?php echo fin_stmt_rows($byType['asset'], $type_lbl); ?>
                <tr><th colspan="2">إجمالي الأصول</th><th style="text-align:end"><?php echo number_format($total_assets, 2); ?></th></tr>
                <tr><th colspan="3" style="background:#fef2f2">الخصوم</th></tr>
                <?php echo fin_stmt_rows($byType['liability'], $type_lbl); ?>
                <tr><th colspan="3" style="background:#f5f3ff">حقوق الملكية</th></tr>
                <?php echo fin_stmt_rows($byType['equity'], $type_lbl); ?>
                <tr><td>—</td><td>الأرباح المحتجزة (نتيجة الفترة)</td><td style="text-align:end"><?php echo number_format($net_profit, 2); ?></td></tr>
                <tr><th colspan="2">إجمالي الخصوم + حقوق الملكية</th><th style="text-align:end"><?php echo number_format($total_liab_equity, 2); ?></th></tr>
            </tbody>
            <tfoot><tr><th colspan="2">التوازن (الأصول = الخصوم + حقوق الملكية)</th>
                <th style="text-align:end"><span class="badge badge-<?php echo $balanced ? 'success' : 'danger'; ?>"><?php echo $balanced ? 'متوازن ✔' : 'غير متوازن ✘'; ?></span></th></tr></tfoot>
        </table></div>
    </div></div>

    <!-- التدفق النقدي -->
    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-water"></i> قائمة التدفق النقدي (مبسّطة — من المدفوعات المنفّذة)</h5>
        <div class="table-container"><table class="alltables" style="width:100%">
            <tbody>
                <tr><td>التدفّق النقدي الداخل (تحصيل)</td><td style="text-align:end"><?php echo number_format($inflow, 2); ?></td></tr>
                <tr><td>التدفّق النقدي الخارج (صرف)</td><td style="text-align:end">(<?php echo number_format($outflow, 2); ?>)</td></tr>
            </tbody>
            <tfoot><tr><th>صافي التدفّق النقدي</th>
                <th style="text-align:end"><span class="badge badge-<?php echo $net_cash >= 0 ? 'success' : 'danger'; ?>"><?php echo number_format($net_cash, 2); ?></span></th></tr></tfoot>
        </table></div>
    </div></div>
</div>
</body>
</html>

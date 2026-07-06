<?php
/**
 * Finance/cron_finance_fin.php — محرّك المراقبة المجدوَل (§4).
 * مهام: تحديث حالة الذمم المتأخرة (overdue) بمقارنة تاريخ الاستحقاق باليوم.
 * يُشغَّل عبر جدولة النظام (CLI) أو GET بمفتاح. لا يلمس أي جدول قائم.
 */
$IS_CLI = (PHP_SAPI === 'cli');
require __DIR__ . '/../config.php';

// حارس بسيط للتشغيل عبر المتصفح (اختياري): ?key=finance-cron
if (!$IS_CLI) {
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    if ($key !== 'finance-cron') { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain; charset=UTF-8');
}

$today = date('Y-m-d');

// 1) ذمم متأخرة: لها متبقٍّ وتجاوزت الاستحقاق ولم تُحصّل بالكامل
$overdue = mysqli_query($conn, "UPDATE fin_receivables
    SET state='overdue'
    WHERE COALESCE(is_deleted,0)=0 AND outstanding > 0 AND due_date IS NOT NULL
      AND due_date < '$today' AND state IN('open','partial')");
$n_overdue = $overdue ? mysqli_affected_rows($conn) : 0;

// 2) ذمم عادت للسداد الكامل تُعلَّم collected
$collected = mysqli_query($conn, "UPDATE fin_receivables
    SET state='collected'
    WHERE COALESCE(is_deleted,0)=0 AND outstanding <= 0 AND state<>'collected'");
$n_collected = $collected ? mysqli_affected_rows($conn) : 0;

// 3) أقساط تمويل متأخرة (إصلاح #6): مستحقة غير مسدَّدة تجاوزت تاريخها
$fund_od = mysqli_query($conn, "UPDATE fin_funding_schedules
    SET state='overdue'
    WHERE state IN('due','partial') AND paid_amount < total_due AND due_date < '$today'");
$n_fund = $fund_od ? mysqli_affected_rows($conn) : 0;

// 4) (فجوة 3) الانحراف المستمر: إعادة احتساب فعلي الموازنة لكل الشركات ذات موازنات
require_once __DIR__ . '/fin_helpers.php';
$n_bud = 0;
$comps = mysqli_query($conn, "SELECT DISTINCT company_id FROM fin_budgets WHERE state IN('approved','active') AND COALESCE(is_deleted,0)=0");
if ($comps) { while ($c = mysqli_fetch_assoc($comps)) { $n_bud += fin_recalc_budget_actuals($conn, intval($c['company_id'])); } }

// 5) (فجوة 4) تنبيهات المراقبة المستمرة (بمنع تكرار يومي داخل fin_notify)
$n_ntf = 0;
$comps = mysqli_query($conn, "SELECT DISTINCT company_id FROM fin_financial_events");
if ($comps) { while ($c = mysqli_fetch_assoc($comps)) {
    $cid = intval($c['company_id']);
    // ذمم متأخرة → الخزينة والمدير المالي
    $r = mysqli_query($conn, "SELECT COUNT(*) n, COALESCE(SUM(outstanding),0) t FROM fin_receivables WHERE company_id=$cid AND COALESCE(is_deleted,0)=0 AND state='overdue' AND outstanding>0");
    if ($r && ($x = mysqli_fetch_assoc($r)) && intval($x['n']) > 0) {
        fin_notify($conn, $cid, 'treasurer', 'ذمم متأخرة: ' . $x['n'] . ' فاتورة بمبلغ ' . number_format((float)$x['t'], 0), 'dues_fin.php'); $n_ntf++;
        fin_notify($conn, $cid, 'finance_manager', 'ذمم متأخرة: ' . $x['n'] . ' فاتورة بمبلغ ' . number_format((float)$x['t'], 0), 'dues_fin.php'); $n_ntf++;
    }
    // أقساط تمويل خلال 7 أيام → المدير المالي
    $r = mysqli_query($conn, "SELECT COUNT(*) n, COALESCE(SUM(total_due-paid_amount),0) t FROM fin_funding_schedules WHERE company_id=$cid AND state<>'paid' AND due_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)");
    if ($r && ($x = mysqli_fetch_assoc($r)) && intval($x['n']) > 0) {
        fin_notify($conn, $cid, 'finance_manager', 'أقساط تمويل تستحق خلال 7 أيام: ' . $x['n'] . ' قسط بمبلغ ' . number_format((float)$x['t'], 0), 'funding_fin.php'); $n_ntf++;
    }
    // انحرافات فوق 10% → المدير المالي
    $r = mysqli_query($conn, "SELECT COUNT(*) n FROM fin_budget_lines l JOIN fin_budgets b ON b.id=l.budget_id WHERE l.company_id=$cid AND b.state IN('approved','active') AND COALESCE(b.is_deleted,0)=0 AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct) > 10");
    if ($r && ($x = mysqli_fetch_assoc($r)) && intval($x['n']) > 0) {
        fin_notify($conn, $cid, 'finance_manager', 'انحرافات موازنة فوق 10%: ' . $x['n'] . ' بند', 'budget_form_fin.php'); $n_ntf++;
    }
} }

$out = "[finance-cron $today] recv_overdue=$n_overdue recv_collected=$n_collected funding_overdue=$n_fund budget_lines_fed=$n_bud notifications=$n_ntf\n";
echo $out;

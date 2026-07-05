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

$out = "[finance-cron $today] recv_overdue=$n_overdue recv_collected=$n_collected funding_overdue=$n_fund\n";
echo $out;

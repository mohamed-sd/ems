<?php
/**
 * Finance/cfo_daily_board_fin.php — لوحة المدير المالي اليومية (§12.5).
 * ★ قراءة فقط ★ — عشر بطاقات تُقرأ في دقائق أول اليوم، كلٌّ تفتح على شاشتها المصدر:
 * النقد المتاح · متحصّلات اليوم · مدفوعات اليوم · صافي الأسبوع المتوقّع · وحدات أمس المعتمدة ·
 * هامش الوحدة الجاري · الذمم المتأخرة · المسوّى الجاهز للصرف · الانحرافات فوق الحدّ · أقساط 7 أيام.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/cfo_daily_board_fin.php', $is_super_admin);
if (!$perms['can_view']) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+العرض+❌"); exit(); }
$cid = intval($company_id);
fin_handle_notif_read($conn, $company_id, 'cfo_daily_board_fin.php');
$W = $is_super_admin ? "company_id > 0" : "company_id = $cid";
function cfoV($conn, $sql) { $r = mysqli_query($conn, $sql); return ($r && ($x = mysqli_fetch_assoc($r))) ? (float)array_values($x)[0] : 0; }

$today = date('Y-m-d'); $yesterday = date('Y-m-d', strtotime('-1 day'));
$week = date('Y-m-d', strtotime('+7 days')); $month = date('Y-m');

// ① النقد المتاح (أرصدة البنوك: افتتاحي + إيداعات − سحوبات)
$cash = cfoV($conn, "SELECT COALESCE(SUM(a.opening_balance),0)
        + COALESCE((SELECT SUM(CASE WHEN l.direction='deposit' THEN l.amount ELSE -l.amount END)
                    FROM fin_bank_statement_lines l WHERE l.$W),0)
        FROM fin_bank_accounts a WHERE a.$W AND COALESCE(a.is_deleted,0)=0");
// ② متحصّلات اليوم  ③ مدفوعات اليوم
$in_today  = cfoV($conn, "SELECT COALESCE(SUM(amount),0) FROM fin_payments WHERE $W AND COALESCE(is_deleted,0)=0 AND direction='collection'  AND state IN('executed','reconciled') AND DATE(COALESCE(paid_at,created_at))='$today'");
$out_today = cfoV($conn, "SELECT COALESCE(SUM(amount),0) FROM fin_payments WHERE $W AND COALESCE(is_deleted,0)=0 AND direction='disbursement' AND state IN('executed','reconciled') AND DATE(COALESCE(paid_at,created_at))='$today'");
// ④ صافي الأسبوع المتوقّع = ذمم تستحق خلال 7 أيام − (مستحقات معلّقة + أقساط 7 أيام)
$wk_in  = cfoV($conn, "SELECT COALESCE(SUM(outstanding),0) FROM fin_receivables WHERE $W AND COALESCE(is_deleted,0)=0 AND outstanding>0 AND due_date IS NOT NULL AND due_date<='$week'");
$wk_out = cfoV($conn, "SELECT COALESCE(SUM(amount),0) FROM fin_dues WHERE $W AND COALESCE(is_deleted,0)=0 AND direction='credit' AND settlement_state='pending'")
        + cfoV($conn, "SELECT COALESCE(SUM(total_due-paid_amount),0) FROM fin_funding_schedules WHERE $W AND state<>'paid' AND due_date<='$week'");
$wk_net = $wk_in - $wk_out;
// ⑤ وحدات أمس المعتمدة  ⑥ هامش الوحدة الجاري (هذا الشهر)
$units_yday = cfoV($conn, "SELECT COALESCE(SUM(approved_qty),0) FROM fin_unit_records WHERE $W AND COALESCE(is_deleted,0)=0 AND match_state='approved' AND record_date='$yesterday'");
$margin_mo  = cfoV($conn, "SELECT COALESCE(SUM(unit_margin),0) FROM fin_unit_records WHERE $W AND COALESCE(is_deleted,0)=0 AND match_state='approved' AND DATE_FORMAT(record_date,'%Y-%m')='$month'");
// ⑦ الذمم المتأخرة  ⑧ المسوّى الجاهز للصرف
$overdue = cfoV($conn, "SELECT COALESCE(SUM(outstanding),0) FROM fin_receivables WHERE $W AND COALESCE(is_deleted,0)=0 AND outstanding>0 AND due_date IS NOT NULL AND due_date<'$today'");
$settled_ready = cfoV($conn, "SELECT COALESCE(SUM(amount),0) FROM fin_dues WHERE $W AND COALESCE(is_deleted,0)=0 AND direction='credit' AND settlement_state='settled'");
// ⑨ الانحرافات فوق الحدّ (10%)  ⑩ أقساط التمويل خلال 7 أيام
$var_over = cfoV($conn, "SELECT COUNT(*) FROM fin_budget_lines l JOIN fin_budgets b ON b.id=l.budget_id WHERE b.$W AND COALESCE(b.is_deleted,0)=0 AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct)>10");
$inst7 = cfoV($conn, "SELECT COALESCE(SUM(total_due-paid_amount),0) FROM fin_funding_schedules WHERE $W AND state<>'paid' AND due_date BETWEEN '$today' AND '$week'");

$cards = array(
  array('fa-wallet',            number_format($cash, 0),        'النقد المتاح (البنوك)',        $cash >= 0 ? 'ok' : 'err',  'bank_reconciliation_fin.php'),
  array('fa-arrow-down',        number_format($in_today, 0),    'متحصّلات اليوم',               'ok',   'payments_fin.php'),
  array('fa-arrow-up',          number_format($out_today, 0),   'مدفوعات اليوم',                'or',   'payments_fin.php'),
  array('fa-scale-unbalanced',  number_format($wk_net, 0),      'صافي الأسبوع المتوقّع',        $wk_net >= 0 ? 'ok' : 'err', 'cash_forecast_fin.php'),
  array('fa-cubes',             number_format($units_yday, 2),  'وحدات أمس المعتمدة',           'or',   'unit_records_fin.php'),
  array('fa-percent',           number_format($margin_mo, 0),   'هامش الوحدة الجاري (الشهر)',   $margin_mo >= 0 ? 'ok' : 'err', 'unit_records_fin.php'),
  array('fa-hourglass-end',     number_format($overdue, 0),     'الذمم المتأخرة',               $overdue > 0 ? 'err' : 'ok', 'dues_fin.php'),
  array('fa-hand-holding-dollar', number_format($settled_ready, 0), 'المسوّى الجاهز للصرف',     'or',   'payments_fin.php'),
  array('fa-triangle-exclamation', number_format($var_over, 0), 'انحرافات فوق 10%',             $var_over > 0 ? 'err' : 'ok', 'budget_form_fin.php'),
  array('fa-landmark',          number_format($inst7, 0),       'أقساط تمويل خلال 7 أيام',      $inst7 > 0 ? 'or' : 'ok', 'funding_fin.php'),
);

$page_title = 'إيكوبيشن | لوحة المدير المالي اليومية';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-cfo-main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة المدير المالي اليومية'; $header_icon = 'fa fa-gauge-high';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <p class="text-muted" style="margin:4px 2px 10px"><i class="fas fa-mug-hot"></i> عشر بطاقات تُقرأ في دقائق أول اليوم — اضغط أي بطاقة لفتح سجلّها المصدر. (<?php echo $today; ?>)</p>
    <?php fin_notifications_panel($conn, $ctx, 'cfo_daily_board_fin.php'); ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:12px">
        <?php foreach ($cards as $c): list($icon, $val, $lbl, $tone, $href) = $c;
            $color = $tone === 'ok' ? '#166534' : ($tone === 'err' ? '#991b1b' : '#92400e'); ?>
        <a href="<?php echo $href; ?>" style="text-decoration:none;color:inherit">
            <div class="card" style="height:100%"><div class="card-body" style="text-align:center">
                <i class="fas <?php echo $icon; ?>" style="font-size:20px;opacity:.65"></i>
                <div style="font-size:22px;font-weight:800;margin:6px 0;color:<?php echo $color; ?>"><?php echo htmlspecialchars($val); ?></div>
                <div class="text-muted" style="font-size:13px"><?php echo htmlspecialchars($lbl); ?></div>
            </div></div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card" style="margin-top:14px"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-clipboard-check"></i> جدول القرار اليومي</h5>
        <div class="table-container"><table class="alltables" style="width:100%">
            <thead><tr><th>القرار</th><th>المؤشر</th><th>أين يُتّخذ</th></tr></thead>
            <tbody>
                <tr><td>ماذا نصرف اليوم؟</td><td>المسوّى الجاهز (<?php echo number_format($settled_ready, 0); ?>) مقابل النقد (<?php echo number_format($cash, 0); ?>)</td><td><a href="payments_fin.php">المدفوعات</a></td></tr>
                <tr><td>ماذا نحصّل اليوم؟</td><td>الذمم المتأخرة (<?php echo number_format($overdue, 0); ?>)</td><td><a href="dues_fin.php">الذمم</a></td></tr>
                <tr><td>هل نحتاج تمويلًا؟</td><td>صافي الأسبوع (<?php echo number_format($wk_net, 0); ?>) وأقساط 7 أيام (<?php echo number_format($inst7, 0); ?>)</td><td><a href="cash_forecast_fin.php">السيولة</a></td></tr>
                <tr><td>هل التشغيل يربح؟</td><td>هامش الوحدة الجاري (<?php echo number_format($margin_mo, 0); ?>)</td><td><a href="unit_records_fin.php">كشف الوحدات</a></td></tr>
                <tr><td>أين نتدخّل؟</td><td>انحرافات فوق الحد (<?php echo number_format($var_over, 0); ?>)</td><td><a href="budget_form_fin.php">الميزانيات</a></td></tr>
            </tbody>
        </table></div>
    </div></div>
</div>
</body>
</html>

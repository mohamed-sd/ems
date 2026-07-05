<?php
/**
 * Finance/executive_dashboard_fin.php — اللوحة التنفيذية المالية (§18).
 * ★ قراءة فقط بالكامل ★ — تجميع من جداول fin_ القائمة (لا كتابة، لا جداول جديدة).
 * ربحية · سيولة · ذمم وأعمارها · انحراف · تمويل · خط الاعتماد.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/executive_dashboard_fin.php', $is_super_admin);
if (!$perms['can_view']) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+العرض+❌"); exit(); }

$cid = intval($company_id);
$W = $is_super_admin ? "company_id > 0" : "company_id = $cid";
function finx($conn, $sql) { $r = mysqli_query($conn, $sql); return ($r && ($x = mysqli_fetch_assoc($r))) ? array_values($x)[0] : 0; }

// KPIs (قراءة فقط)
// إصلاح #1: توحيد العملة لعملة الأساس (SDG) لمنع خلط SDG/USD في التجميع
$B = fin_base_amount();
$revenue = (float) finx($conn, "SELECT COALESCE(SUM($B),0) FROM fin_financial_events WHERE $W AND COALESCE(is_deleted,0)=0 AND event_type='revenue' AND state IN('approved','posted','settled','closed')");
$expense = (float) finx($conn, "SELECT COALESCE(SUM($B),0) FROM fin_financial_events WHERE $W AND COALESCE(is_deleted,0)=0 AND event_type IN('expense','payable','payroll') AND state IN('approved','posted','settled','closed')");
$profit  = (float) finx($conn, "SELECT COALESCE(SUM(profit),0) FROM fin_cost_records WHERE $W AND COALESCE(is_deleted,0)=0");
$cashpos = (float) finx($conn, "SELECT COALESCE(expected_position,0) FROM fin_cash_forecasts WHERE $W AND COALESCE(is_deleted,0)=0 ORDER BY id DESC LIMIT 1");
$recv_out= (float) finx($conn, "SELECT COALESCE(SUM(outstanding),0) FROM fin_receivables WHERE $W AND COALESCE(is_deleted,0)=0 AND outstanding>0");
$recv_od = (int)   finx($conn, "SELECT COUNT(*) FROM fin_receivables WHERE $W AND COALESCE(is_deleted,0)=0 AND outstanding>0 AND due_date IS NOT NULL AND due_date < CURDATE()");
$dues_pend=(float) finx($conn, "SELECT COALESCE(SUM(amount),0) FROM fin_dues WHERE $W AND COALESCE(is_deleted,0)=0 AND direction='credit' AND settlement_state='pending'");
$var_open= (int)   finx($conn, "SELECT COUNT(*) FROM fin_budget_lines l JOIN fin_budgets b ON b.id=l.budget_id WHERE b." . ($is_super_admin ? "company_id>0" : "company_id=$cid") . " AND COALESCE(b.is_deleted,0)=0 AND l.variance <> 0");
$fund_due= (float) finx($conn, "SELECT COALESCE(SUM(total_due-paid_amount),0) FROM fin_funding_schedules WHERE $W AND state<>'paid'");
$pending_appr = (int) finx($conn, "SELECT COUNT(*) FROM fin_financial_events WHERE $W AND COALESCE(is_deleted,0)=0 AND state IN('dept_review','dept_approved','fin_review','audited')");

$cards = array(
  array('fa-arrow-trend-up', number_format($revenue, 0), 'الإيراد المعتمد', 'ok'),
  array('fa-arrow-trend-down', number_format($expense, 0), 'المصروف المعتمد', 'warn'),
  array('fa-coins', number_format($profit, 0), 'صافي الربحية', $profit >= 0 ? 'ok' : 'err'),
  array('fa-water', number_format($cashpos, 0), 'الوضع النقدي المتوقّع', $cashpos >= 0 ? 'ok' : 'err'),
  array('fa-hand-holding-dollar', number_format($recv_out, 0), 'ذمم مستحقة (' . $recv_od . ' متأخرة)', 'warn'),
  array('fa-truck-field', number_format($dues_pend, 0), 'مستحقات موردين معلّقة', 'or'),
  array('fa-triangle-exclamation', (string)$var_open, 'بنود بها انحراف', 'warn'),
  array('fa-landmark', number_format($fund_due, 0), 'التزامات تمويل متبقّية', 'or'),
  array('fa-hourglass-half', (string)$pending_appr, 'أحداث بانتظار الاعتماد', 'or'),
);

$page_title = 'إيكوبيشن | اللوحة التنفيذية المالية';
include '../inheader.php';
include '../insidebar.php';
$states = fin_event_states();
?>
<div class="main fin-exec-main ems-unified-page-shell">
    <?php
    $header_title = 'اللوحة التنفيذية المالية'; $header_icon = 'fa fa-chart-line';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <p class="text-muted" style="margin:4px 2px 0"><i class="fas fa-circle-info"></i> كل المبالغ موحّدة إلى عملة الأساس <strong>الجنيه (SDG)</strong> — تُحوَّل مبالغ USD بسعر الصرف.</p>

    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;margin:8px 0 16px">
        <?php foreach ($cards as $cds): list($icon, $val, $lbl, $tone) = $cds; ?>
        <div class="card"><div class="card-body" style="text-align:center">
            <i class="fas <?php echo $icon; ?>" style="font-size:22px;opacity:.7"></i>
            <div style="font-size:24px;font-weight:700;margin:6px 0"><?php echo htmlspecialchars($val); ?></div>
            <div class="text-muted"><?php echo htmlspecialchars($lbl); ?></div>
        </div></div>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-diagram-next"></i> خط سير الاعتماد (الأحداث حسب الحالة)</h5>
        <div class="table-container">
            <table class="alltables" style="width:100%">
                <thead><tr><th>الحالة</th><th>العدد</th><th>الإجمالي</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT state, COUNT(*) c, COALESCE(SUM(" . fin_base_amount() . "),0) t FROM fin_financial_events
                        WHERE $W AND COALESCE(is_deleted,0)=0 GROUP BY state
                        ORDER BY FIELD(state,'draft','dept_review','dept_approved','fin_review','audited','approved','posted','settled','rejected','closed')";
                if ($r = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($r)) {
                    echo "<tr><td><span class='badge badge-" . fin_state_tone($row['state']) . "'>" . htmlspecialchars($states[$row['state']] ?? $row['state']) . "</span></td>";
                    echo "<td>" . intval($row['c']) . "</td><td>" . number_format((float)$row['t'], 2) . "</td></tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-ranking-star"></i> ربحية المشاريع</h5>
        <div class="table-container">
            <table class="alltables" style="width:100%">
                <thead><tr><th>المشروع</th><th>التكلفة</th><th>الإيراد</th><th>الربحية</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT p.name pn, COALESCE(SUM(cr.total_cost),0) tc, COALESCE(SUM(cr.revenue),0) tr,
                               COALESCE(SUM(cr.revenue),0)-COALESCE(SUM(cr.total_cost),0) pf
                        FROM fin_cost_records cr LEFT JOIN project p ON p.id=cr.project_id
                        WHERE cr.$W AND COALESCE(cr.is_deleted,0)=0 GROUP BY cr.project_id, p.name ORDER BY pf DESC LIMIT 8";
                if ($r = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($r)) {
                    $pf = (float)$row['pf'];
                    echo "<tr><td>" . htmlspecialchars((string)($row['pn'] ?? 'بلا مشروع')) . "</td>";
                    echo "<td>" . number_format((float)$row['tc'], 2) . "</td><td>" . number_format((float)$row['tr'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . ($pf >= 0 ? 'success' : 'danger') . "'>" . number_format($pf, 2) . "</span></td></tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-hourglass-end"></i> أعمار الذمم المدينة</h5>
        <div class="table-container">
            <table class="alltables" style="width:100%">
                <thead><tr><th>الفئة العمرية</th><th>العدد</th><th>المتبقّي</th></tr></thead>
                <tbody>
                <?php
                $buckets = array(
                  'غير مستحقة بعد' => "due_date IS NULL OR due_date >= CURDATE()",
                  'متأخرة 1-30 يوم' => "due_date < CURDATE() AND due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                  'متأخرة 31-90 يوم' => "due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND due_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
                  'متأخرة +90 يوم' => "due_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
                );
                foreach ($buckets as $lbl => $cond) {
                    $r = mysqli_query($conn, "SELECT COUNT(*) c, COALESCE(SUM(outstanding),0) t FROM fin_receivables WHERE $W AND COALESCE(is_deleted,0)=0 AND outstanding>0 AND ($cond)");
                    $row = $r ? mysqli_fetch_assoc($r) : array('c' => 0, 't' => 0);
                    echo "<tr><td>" . htmlspecialchars($lbl) . "</td><td>" . intval($row['c']) . "</td><td>" . number_format((float)$row['t'], 2) . "</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>
</body>
</html>

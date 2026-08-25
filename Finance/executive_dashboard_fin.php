<?php
/**
 * Finance/executive_dashboard_fin.php — اللوحة التنفيذية المالية (§18).
 * ★ قراءة فقط بالكامل ★ — تجميع من جداول fin_ القائمة (لا كتابة، لا جداول جديدة).
 * ربحية · سيولة · ذمم وأعمارها · انحراف · تمويل · خط الاعتماد.
 *
 * مُهاجَرة خلف بوابة العزل (§10 scopedQuery حصرًا — الشاشة تجميعية قرائية):
 * كل عزل company_id مسؤولية البوابة عبر {TENANT_SCOPE}؛ استعلام الموازنة
 * INNER→LEFT + b.id IS NOT NULL بعزلٍ على السطور (نمط cfo_daily_board المثبَت،
 * والتطابق مقيس: صفر سطر موازنة عابر للشركات).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/executive_dashboard_fin.php', $is_super_admin);
if (!$perms['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية العرض ❌', 'GOV-PERM-403', ''); exit(); }

// قياس مُعزَّل مفرد الجدول عبر scopedQuery (§10) — بديل finx الخام (نمط cfo_daily_board)
$exScoped = function ($alias, $table, $sql, $params = array()) use ($is_super_admin) {
    $r = fin_gate($is_super_admin)->scopedQuery(array('scope' => array($alias => $table)), $sql, $params);
    return $r ? (float) array_values($r[0])[0] : 0.0;
};

// KPIs (قراءة فقط)
// إصلاح #1: توحيد العملة لعملة الأساس (SDG) لمنع خلط SDG/USD في التجميع
$B = fin_base_amount('e.amount', 'e.currency', 'e.fx_rate');
$revenue = $exScoped('e', 'fin_financial_events', "SELECT COALESCE(SUM($B),0) FROM fin_financial_events e WHERE {TENANT_SCOPE} AND COALESCE(e.is_deleted,0)=0 AND e.event_type='revenue' AND e.state IN('approved','posted','settled','closed')");
$expense = $exScoped('e', 'fin_financial_events', "SELECT COALESCE(SUM($B),0) FROM fin_financial_events e WHERE {TENANT_SCOPE} AND COALESCE(e.is_deleted,0)=0 AND e.event_type IN('expense','payable','payroll') AND e.state IN('approved','posted','settled','closed')");
$profit  = $exScoped('cr', 'fin_cost_records', "SELECT COALESCE(SUM(cr.profit),0) FROM fin_cost_records cr WHERE {TENANT_SCOPE} AND COALESCE(cr.is_deleted,0)=0");
$cashpos = $exScoped('cf', 'fin_cash_forecasts', "SELECT COALESCE(cf.expected_position,0) FROM fin_cash_forecasts cf WHERE {TENANT_SCOPE} AND COALESCE(cf.is_deleted,0)=0 ORDER BY cf.id DESC LIMIT 1");
$recv_out= $exScoped('r', 'fin_receivables', "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0");
$recv_od = (int) $exScoped('r', 'fin_receivables', "SELECT COUNT(*) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date < CURDATE()");
$dues_pend= $exScoped('d', 'fin_dues', "SELECT COALESCE(SUM(d.amount),0) FROM fin_dues d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit' AND d.settlement_state='pending'");
// انحرافات الموازنة: تكافؤ INNER→LEFT + b.id IS NOT NULL، والعزل على السطور (نفس شركة الموازنة)
$var_rows = fin_gate($is_super_admin)->scopedQuery(
    array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
    "SELECT COUNT(*) c FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id=l.budget_id
     WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0 AND l.variance <> 0");
$var_open = $var_rows ? (int) $var_rows[0]['c'] : 0;
$fund_due = $exScoped('f', 'fin_funding_schedules', "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid'");
$pending_appr = (int) $exScoped('e', 'fin_financial_events', "SELECT COUNT(*) FROM fin_financial_events e WHERE {TENANT_SCOPE} AND COALESCE(e.is_deleted,0)=0 AND e.state IN('dept_review','dept_approved','fin_review','audited')");

$cards = array(
  array('fa-arrow-trend-up', number_format($revenue, 0), 'الإيراد المعتمد', 'ok'),
  array('fa-arrow-trend-down', number_format($expense, 0), 'المصروف المعتمد', 'warn'),
  array('fa-coins', number_format($profit, 0), 'صافي الربحية', $profit >= 0 ? 'ok' : 'err'),
  array('fa-water', number_format($cashpos, 0), 'الوضع النقدي المتوقع', $cashpos >= 0 ? 'ok' : 'err'),
  array('fa-hand-holding-dollar', number_format($recv_out, 0), 'ذمم مستحقة (' . $recv_od . ' متأخرة)', 'warn'),
  array('fa-truck-field', number_format($dues_pend, 0), 'مستحقات موردين معلقة', 'or'),
  array('fa-triangle-exclamation', (string)$var_open, 'بنود بها انحراف', 'warn'),
  array('fa-landmark', number_format($fund_due, 0), 'التزامات تمويل متبقية', 'or'),
  array('fa-hourglass-half', (string)$pending_appr, 'أحداث بانتظار الاعتماد', 'or'),
);

$page_title = 'إيكوبيشن | اللوحة التنفيذية المالية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
$states = fin_event_states();
?>
<style>
/* UXW-01 ٢: أنماطُ هذه الشاشةِ الثابتةُ صارتْ أصنافًا ببادئةِ الشاشة */
.fin-exec-note { margin: 4px 2px 0; }
.fin-exec-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; margin: 8px 0 16px; }
.fin-exec-card { text-align: center; }
.fin-exec-cardicon { font-size: 22px; opacity: .7; }
.fin-exec-cardval { font-size: 24px; font-weight: 700; margin: 6px 0; }
.fin-exec-h5 { margin: 0 0 10px; }
.fin-exec-h5-next { margin: 18px 0 10px; }
.fin-exec-tbl { width: 100%; }
</style>
<div class="main fin-exec-main ems-unified-page-shell">
    <?php
    $header_title = 'اللوحة التنفيذية المالية'; $header_icon = 'fa fa-chart-line';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا أحداث مالية ولا ذمما في نطاق الشركة لتبنى منها اللوحة', 'سجل الحدث المالي الأول في «الأحداث المالية» فتظهر المؤشرات هنا');
    ?>

    <p class="text-muted fin-exec-note"><i class="fas fa-circle-info"></i> كل المبالغ موحدة إلى عملة الأساس <strong>الجنيه (SDG)</strong> — تحول مبالغ USD بسعر الصرف.</p>

    <div class="stats-grid fin-exec-grid">
        <?php foreach ($cards as $cds): list($icon, $val, $lbl, $tone) = $cds; ?>
        <div class="card"><div class="card-body fin-exec-card">
            <i class="fas fin-exec-cardicon <?php echo $icon; ?>"></i>
            <div class="fin-exec-cardval"><?php echo htmlspecialchars($val); ?></div>
            <div class="text-muted"><?php echo htmlspecialchars($lbl); ?></div>
        </div></div>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body">
        <h5 class="fin-exec-h5"><i class="fas fa-diagram-next"></i> خط سير الاعتماد (الأحداث حسب الحالة)</h5>
        <div class="table-container">
            <table class="alltables fin-exec-tbl">
                <thead><tr><th>الحالة</th><th>العدد</th><th>الإجمالي</th></tr></thead>
                <tbody>
                <?php
                $state_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('e' => 'fin_financial_events')),
                    "SELECT e.state, COUNT(*) c, COALESCE(SUM(" . fin_base_amount('e.amount', 'e.currency', 'e.fx_rate') . "),0) t FROM fin_financial_events e
                     WHERE {TENANT_SCOPE} AND COALESCE(e.is_deleted,0)=0 GROUP BY e.state
                     ORDER BY FIELD(e.state,'draft','dept_review','dept_approved','fin_review','audited','approved','posted','settled','rejected','closed')");
                foreach ($state_rows as $row) {
                    echo "<tr><td><span class='badge badge-" . fin_state_tone($row['state']) . "'>" . htmlspecialchars($states[$row['state']] ?? $row['state']) . "</span></td>";
                    echo "<td>" . intval($row['c']) . "</td><td>" . number_format((float)$row['t'], 2) . "</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

        <h5 class="fin-exec-h5-next"><i class="fas fa-ranking-star"></i> ربحية المشاريع</h5>
        <div class="table-container">
            <table class="alltables fin-exec-tbl">
                <thead><tr><th>المشروع</th><th>التكلفة</th><th>الإيراد</th><th>الربحية</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
                <tbody>
                <?php
                $prof_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('cr' => 'fin_cost_records'), 'enrich' => array('p' => 'project')),
                    "SELECT p.name pn, COALESCE(SUM(cr.total_cost),0) tc, COALESCE(SUM(cr.revenue),0) tr,
                            COALESCE(SUM(cr.revenue),0)-COALESCE(SUM(cr.total_cost),0) pf
                     FROM fin_cost_records cr LEFT JOIN project p ON p.id=cr.project_id
                     WHERE {TENANT_SCOPE} AND COALESCE(cr.is_deleted,0)=0 GROUP BY cr.project_id, p.name ORDER BY pf DESC LIMIT 8");
                foreach ($prof_rows as $row) {
                    $pf = (float)$row['pf'];
                    echo "<tr><td>" . htmlspecialchars((string)($row['pn'] ?? 'بلا مشروع')) . "</td>";
                    echo "<td>" . number_format((float)$row['tc'], 2) . "</td><td>" . number_format((float)$row['tr'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . ($pf >= 0 ? 'success' : 'danger') . "'>" . number_format($pf, 2) . "</span></td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

        <h5 class="fin-exec-h5-next"><i class="fas fa-hourglass-end"></i> أعمار الذمم المدينة</h5>
        <div class="table-container">
            <table class="alltables fin-exec-tbl">
                <thead><tr><th>الفئة العمرية</th><th>العدد</th><th>المتبقي</th></tr></thead>
                <tbody>
                <?php
                $buckets = array(
                  'غير مستحقة بعد' => "due_date IS NULL OR due_date >= CURDATE()",
                  'متأخرة 1-30 يوم' => "due_date < CURDATE() AND due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                  'متأخرة 31-90 يوم' => "due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND due_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
                  'متأخرة +90 يوم' => "due_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
                );
                foreach ($buckets as $lbl => $cond) {
                    $age_rows = fin_gate($is_super_admin)->scopedQuery(
                        array('scope' => array('r' => 'fin_receivables')),
                        "SELECT COUNT(*) c, COALESCE(SUM(r.outstanding),0) t FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND ($cond)");
                    $row = $age_rows ? $age_rows[0] : array('c' => 0, 't' => 0);
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

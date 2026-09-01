<?php
/**
 * Finance/cfo_daily_board_fin.php — لوحة المدير المالي اليومية (§12.5).
 * ★ قراءة فقط ★ — عشر بطاقات تُقرأ في دقائق أول اليوم، كلٌّ تفتح على شاشتها المصدر:
 * النقد المتاح · متحصّلات اليوم · مدفوعات اليوم · صافي الأسبوع المتوقّع · وحدات أمس المعتمدة ·
 * هامش الوحدة الجاري · الذمم المتأخرة · المسوّى الجاهز للصرف · الانحرافات فوق الحدّ · أقساط 7 أيام.
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

$perms = fin_page_perms($conn, 'Finance/cfo_daily_board_fin.php', $is_super_admin);
if (!$perms['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية العرض ❌', 'GOV-PERM-403', ''); exit(); }

// لوحتان لصاحبٍ واحدٍ لا تجتمعان: بطاقاتُ هذه اللوحةِ العشرُ صارت تُصيَّر في
// «الرئيسية» (قرارُ المالك 2026-08-21 — انظر roleBoardGenericConfig(17))، فمَن
// لوحتُه هناك يُردُّ إليها. والجسدُ أدناه يبقى **مسارَ رجوع**: إعادةُ الدورِ إلى
// هذه الشاشةِ سطرٌ واحدٌ في خريطة roleBoardRoute لا إعادةُ بناء.
require_once __DIR__ . '/../includes/role_board.php';
$cfo_role = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
$cfo_home = roleBoardRoute($cfo_role, roleBoardConfigRole(fin_gate($is_super_admin), $cfo_role));
if ($cfo_home === 'main/dashboard.php') { header('Location: ../main/dashboard.php'); exit(); }
$cid = intval($company_id);
fin_handle_notif_read($conn, $company_id, 'cfo_daily_board_fin.php');
// قياس مُعزَّل مفرد الجدول عبر scopedQuery (§10) — بديل cfoV الخام
$cfoScoped = function ($alias, $table, $sql, $params = array()) use ($is_super_admin) {
    $r = fin_gate($is_super_admin)->scopedQuery(array('scope' => array($alias => $table)), $sql, $params);
    return $r ? (float) array_values($r[0])[0] : 0.0;
};

$today = date('Y-m-d'); $yesterday = date('Y-m-d', strtotime('-1 day'));
$week = date('Y-m-d', strtotime('+7 days')); $month = date('Y-m');

// ① النقد المتاح = افتتاحي البنوك + صافي حركة الكشوف (قراءتان مُعزَّلتان تُجمعان — بديل الاستعلام المتداخل المُعزَّل مرتين)
$cash = $cfoScoped('a', 'fin_bank_accounts',
            "SELECT COALESCE(SUM(a.opening_balance),0) FROM fin_bank_accounts a WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0)=0")
      + $cfoScoped('l', 'fin_bank_statement_lines',
            "SELECT COALESCE(SUM(CASE WHEN l.direction='deposit' THEN l.amount ELSE -l.amount END),0) FROM fin_bank_statement_lines l WHERE {TENANT_SCOPE}");
// ② متحصّلات اليوم  ③ مدفوعات اليوم
$in_today  = $cfoScoped('p', 'fin_payments', "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.direction='collection'  AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=?", array($today));
$out_today = $cfoScoped('p', 'fin_payments', "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.direction='disbursement' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=?", array($today));
// ④ صافي الأسبوع المتوقّع = ذمم تستحق خلال 7 أيام − (مستحقات معلّقة + أقساط 7 أيام)
$wk_in  = $cfoScoped('r', 'fin_receivables', "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<=?", array($week));
$wk_out = $cfoScoped('d', 'fin_dues', "SELECT COALESCE(SUM(d.amount),0) FROM fin_dues d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit' AND d.settlement_state='pending'")
        + $cfoScoped('f', 'fin_funding_schedules', "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date<=?", array($week));
$wk_net = $wk_in - $wk_out;
// ⑤ وحدات أمس المعتمدة  ⑥ هامش الوحدة الجاري (هذا الشهر)
$units_yday = $cfoScoped('u', 'fin_unit_records', "SELECT COALESCE(SUM(u.approved_qty),0) FROM fin_unit_records u WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.match_state='approved' AND u.record_date=?", array($yesterday));
$margin_mo  = $cfoScoped('u', 'fin_unit_records', "SELECT COALESCE(SUM(u.unit_margin),0) FROM fin_unit_records u WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.match_state='approved' AND DATE_FORMAT(u.record_date,'%Y-%m')=?", array($month));
// ⑦ الذمم المتأخرة  ⑧ المسوّى الجاهز للصرف
$overdue = $cfoScoped('r', 'fin_receivables', "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<?", array($today));
$settled_ready = $cfoScoped('d', 'fin_dues', "SELECT COALESCE(SUM(d.amount),0) FROM fin_dues d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit' AND d.settlement_state='settled'");
// ⑨ الانحرافات فوق الحدّ (10%) — تكافؤ INNER→LEFT، العزل على السطور (نفس شركة الموازنة)
$var_rows = fin_gate($is_super_admin)->scopedQuery(
    array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
    "SELECT COUNT(*) c FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id=l.budget_id
     WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0 AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct)>10");
$var_over = $var_rows ? (float) array_values($var_rows[0])[0] : 0.0;
// ⑩ أقساط التمويل خلال 7 أيام
$inst7 = $cfoScoped('f', 'fin_funding_schedules', "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date BETWEEN ? AND ?", array($today, $week));

$cards = array(
  array('fa-wallet',            number_format($cash, 0),        'النقد المتاح (البنوك)',        $cash >= 0 ? 'ok' : 'err',  'bank_reconciliation_fin.php'),
  array('fa-arrow-down',        number_format($in_today, 0),    'متحصلات اليوم',               'ok',   'payments_fin.php'),
  array('fa-arrow-up',          number_format($out_today, 0),   'مدفوعات اليوم',                'or',   'payments_fin.php'),
  array('fa-scale-unbalanced',  number_format($wk_net, 0),      'صافي الأسبوع المتوقع',        $wk_net >= 0 ? 'ok' : 'err', 'cash_forecast_fin.php'),
  array('fa-cubes',             number_format($units_yday, 2),  'وحدات أمس المعتمدة',           'or',   'unit_records_fin.php'),
  array('fa-percent',           number_format($margin_mo, 0),   'هامش الوحدة الجاري (الشهر)',   $margin_mo >= 0 ? 'ok' : 'err', 'unit_records_fin.php'),
  array('fa-hourglass-end',     number_format($overdue, 0),     'الذمم المتأخرة',               $overdue > 0 ? 'err' : 'ok', 'dues_fin.php'),
  array('fa-hand-holding-dollar', number_format($settled_ready, 0), 'المسوى الجاهز للصرف',     'or',   'payments_fin.php'),
  array('fa-triangle-exclamation', number_format($var_over, 0), 'انحرافات فوق 10%',             $var_over > 0 ? 'err' : 'ok', 'budget_form_fin.php'),
  array('fa-landmark',          number_format($inst7, 0),       'أقساط تمويل خلال 7 أيام',      $inst7 > 0 ? 'or' : 'ok', 'funding_fin.php'),
);

/* ── لوحة الدور بالمكوّنات السبعة (UX-00 §7 · UX-01 §5 · §8.11) ──────────────
   البطاقات العشر أعلاه = المكوّن ① «مؤشرات اليوم» (قائمةٌ من قبل — بقرار
   المالك تبقى ويُضاف الناقص). هنا تُجمع بيانات ②-⑦ عبر محرك role_board. */
require_once __DIR__ . '/../includes/role_board.php';
require_once __DIR__ . '/../includes/finreq_badges.php';

$rb_gate      = fin_gate($is_super_admin);
$rb_badges    = ems_finreq_nav_badges($conn);
$rb_tasks     = roleBoardTasks($conn, $rb_gate, 17);                       // ② مهامي
$rb_approvals = roleBoardApprovals($conn, $rb_gate, 17, $rb_badges);       // ③ موافقاتي
$rb_alerts    = roleBoardAlerts($conn, $rb_gate, 17);                      // ④ التنبيهات (UX-01 §8.11 نصًّا)
$rb_quick     = roleBoardQuickActions($conn, 17, $current_user_id);        // ⑤ إنشاء سريع
$rb_recent    = roleBoardRecent($conn, $current_user_id);                  // ⑦ عملي الأخير

// ⑥ نبض الأداء — رسمٌ واحدٌ يخص قرار الدور: التحصيل مقابل الصرف آخر 7 أيام
$rb_pulse = array('labels' => array(), 'in' => array(), 'out' => array());
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-{$d} days"));
    $rb_pulse['labels'][] = date('m/d', strtotime($day));
    $rb_pulse['in'][]  = roleBoardScalar($rb_gate, array('scope' => array('p' => 'fin_payments')),
        "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
         AND p.direction='collection' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=?", array($day));
    $rb_pulse['out'][] = roleBoardScalar($rb_gate, array('scope' => array('p' => 'fin_payments')),
        "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
         AND p.direction='disbursement' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=?", array($day));
}
$rb_pulse_title  = 'نبض الأداء — التحصيل مقابل الصرف (7 أيام)';
$rb_pulse_series = array('تحصيل', 'صرف');

$page_title = 'إيكوبيشن | لوحة المدير المالي';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ٢: أنماطُ هذه الشاشةِ الثابتةُ صارت أصنافًا ببادئةِ الشاشة */
.fin-cfo-lead { margin: 4px 2px 10px; }
.fin-cfo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 12px; }
.fin-cfo-cardlink { text-decoration: none; color: inherit; }
.fin-cfo-card { height: 100%; }
.fin-cfo-cardbody { text-align: center; }
.fin-cfo-icon { font-size: 20px; opacity: .65; }
.fin-cfo-figure { font-size: 22px; font-weight: 800; margin: 6px 0; }
.fin-cfo-ok { color: var(--c-166534); }
.fin-cfo-err { color: var(--c-991b1b); }
.fin-cfo-warn { color: var(--c-92400e); }
.fin-cfo-cardlabel { font-size: 13px; }
.fin-cfo-panel { margin-top: 14px; }
.fin-cfo-h5 { margin: 0 0 10px; }
.fin-cfo-table { width: 100%; }
</style>
<div class="main fin-cfo-main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة المدير المالي'; $header_icon = 'fa fa-gauge-high';
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
            'معرف المؤشر' => 'g153',
            'المؤشر KPI Catalog' => 'g154',
            'القيمة' => 'g155',
            'الوحدة' => 'g156',
            'العملة' => 'g157',
            'الحالة' => 'g158',
            'آخر تحديث' => 'g159',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fina_dashboard_kpi');
        echo ems_w14_grid('emsList_exec_board_kpi', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في لوحة المالية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
    echo ems_states_bundle('لا أرقام يوم لهذا التاريخ بعد', 'افتح سجل المصدر من أي بطاقة — والأرقام تظهر متى سجلت حركات اليوم');
    ?>
    <p class="text-muted fin-cfo-lead"><i class="fas fa-mug-hot"></i> عشر بطاقات تقرأ في دقائق أول اليوم — اضغط أي بطاقة لفتح سجلها المصدر. (<?php echo $today; ?>)</p>
    <?php fin_notifications_panel($conn, $ctx, 'cfo_daily_board_fin.php'); ?>

    <div class="fin-cfo-grid">
        <?php foreach ($cards as $c): list($icon, $val, $lbl, $tone, $href) = $c;
            $toneCls = $tone === 'ok' ? 'fin-cfo-ok' : ($tone === 'err' ? 'fin-cfo-err' : 'fin-cfo-warn'); ?>
        <a class="fin-cfo-cardlink" href="<?php echo $href; ?>">
            <div class="card fin-cfo-card"><div class="card-body fin-cfo-cardbody">
                <i class="fas fin-cfo-icon <?php echo $icon; ?>"></i>
                <div class="fin-cfo-figure <?php echo $toneCls; ?>"><?php echo htmlspecialchars($val); ?></div>
                <div class="text-muted fin-cfo-cardlabel"><?php echo htmlspecialchars($lbl); ?></div>
            </div></div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php include __DIR__ . '/../includes/role_board_widgets.php'; ?>

    <div class="card fin-cfo-panel"><div class="card-body">
        <h5 class="fin-cfo-h5"><i class="fas fa-clipboard-check"></i> جدول القرار اليومي</h5>
        <div class="table-container"><table class="alltables fin-cfo-table">
            <thead><tr><th>القرار</th><th>المؤشر</th><th>أين يتخذ</th>
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
                <tr><td>ماذا نصرف اليوم؟</td><td>المسوى الجاهز (<?php echo number_format($settled_ready, 0); ?>) مقابل النقد (<?php echo number_format($cash, 0); ?>)</td><td><a href="payments_fin.php">المدفوعات</a></td></tr>
                <tr><td>ماذا نحصل اليوم؟</td><td>الذمم المتأخرة (<?php echo number_format($overdue, 0); ?>)</td><td><a href="dues_fin.php">الذمم</a></td></tr>
                <tr><td>هل نحتاج تمويلا؟</td><td>صافي الأسبوع (<?php echo number_format($wk_net, 0); ?>) وأقساط 7 أيام (<?php echo number_format($inst7, 0); ?>)</td><td><a href="cash_forecast_fin.php">السيولة</a></td></tr>
                <tr><td>هل التشغيل يربح؟</td><td>هامش الوحدة الجاري (<?php echo number_format($margin_mo, 0); ?>)</td><td><a href="unit_records_fin.php">كشف الوحدات</a></td></tr>
                <tr><td>أين نتدخل؟</td><td>انحرافات فوق الحد (<?php echo number_format($var_over, 0); ?>)</td><td><a href="budget_form_fin.php">الميزانيات</a></td></tr>
            </tbody>
        </table></div>
    </div></div>
</div>
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
</body>
</html>

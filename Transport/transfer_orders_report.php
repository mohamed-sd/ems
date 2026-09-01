<?php
/**
 * Transport/transfer_orders_report.php — تقرير أوامر الترحيل (RPR-W07 · TRP-13)
 * ───────────────────────────────────────────────────────────────────────────
 * **«مشتقٌّ من الأوامرِ ومحاضرِها وتكاليفها؛ لا إدخال»** (`TRP-13` نصًّا).
 * فالسطحُ **بلا نموذجِ إدخالٍ واحد**: يعرض سطرَ الفترةِ المشتقَّ ومعه **قاعدةُ
 * اشتقاقِه ومصادرُه بالاسم** — فلا رقمَ بلا قاعدة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Transport/transfer_orders_report.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('transfer_orders_report super') : ems_tenant_db();
$pick = isset($_GET['period']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['period']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'period DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('period' => $pick); }
    $rows = $gate->select('trp_kpi_period', $opts);
} catch (\Throwable $t) { error_log('transfer_orders_report list: ' . $t->getMessage()); }
try { foreach ($gate->select('trp_kpi_period', array('columns' => array('period'), 'limit' => 400)) as $c) { if ((string) $c['period'] !== '') { $choices[(string) $c['period']] = true; } } }
catch (\Throwable $t) { error_log('transfer_orders_report choices: ' . $t->getMessage()); }

$closed = 0; $total = 0.0;
foreach ($rows as $r) { $closed += (int) $r['orders_closed']; $total += (float) $r['total_cost']; }
$total = round($total, 2);

$page_title = 'إيكوبيشن | تقرير أوامر الترحيل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقرير أوامر الترحيل'; $header_icon = 'fa fa-file-lines'; $header_actions = array();
    $header_back = array('href' => 'transfer_dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة النقل والترحيل');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_trp_transfer_orders_report
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g161',
            'الفترة' => 'g162',
            'عدد الأوامر' => 'g163',
            'منها مقفلة' => 'g164',
            'متوسط زمن الرحلة' => 'g165',
            'الالتزام بالمواعيد' => 'g166',
            'حوادث وتلفيات' => 'g167',
            'إجمالي التكلفة' => 'g168',
            'تكلفة الكيلومتر' => 'g169',
            'التوزيع بالوسيلة' => 'g170',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('trp_transfer_orders_report');
        echo ems_w14_grid('emsList_trp_transfer_orders_report', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تقرير أوامر الترحيل'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">فترات مشتقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $closed ?></div><div class="ems-stat-label">أوامر مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $total ?></div><div class="ems-stat-label">إجمالي التكلفة</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_tr_p">الفترة</label><select name="period" id="w7_tr_p" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أسطر تقرير مشتقة بعد', 'الاشتقاق يجري من الأوامر ومحاضرها وتكاليفها. ولا يدخل من هنا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>الفترة</th><th>عدد الأوامر</th><th>منها مقفلة</th><th>متوسط زمن الرحلة</th><th>الالتزام بالمواعيد</th><th>حوادث وتلفيات</th><th>إجمالي التكلفة</th><th>تكلفة الكيلومتر</th><th>التوزيع بالوسيلة</th><th>قاعدة الاشتقاق</th><th>المصادر</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars((string) $r['period']) ?></td>
                <td><?= htmlspecialchars((string) $r['orders_total']) ?></td>
                <td><?= htmlspecialchars((string) $r['orders_closed']) ?></td>
                <td><?= htmlspecialchars((string) $r['avg_trip_hours']) ?></td>
                <td><?= htmlspecialchars((string) $r['on_time_pct']) ?></td>
                <td><?= htmlspecialchars((string) $r['incidents']) ?></td>
                <td><?= htmlspecialchars((string) $r['total_cost']) ?></td>
                <td><?= htmlspecialchars((string) $r['cost_per_km']) ?></td>
                <td><?= htmlspecialchars((string) $r['by_carrier']) ?></td>
                <td><small><?= htmlspecialchars((string) $r['derivation_rule']) ?></small></td>
                <td><small><?= htmlspecialchars((string) $r['derived_from']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="12">لا أسطر تقرير مشتقة بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

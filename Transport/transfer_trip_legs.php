<?php
/**
 * Transport/transfer_trip_legs.php — مراحل الرحلة (RPR-W07 · TRP-07)
 * ───────────────────────────────────────────────────────────────────────────
 * **«الرحلةُ قد تُقسَّم مراحلَ بمركباتٍ وسائقين ومساراتٍ مختلفة: كلُّ مرحلةٍ
 * سطرٌ بمبدئها ومنتهاها»** (`TRP-07`). و`startLeg` تردُّ
 * `PREVIOUS_LEG_NOT_HANDED_OVER` — **فالمرحلةُ لا تبدأ قبل تسليمِ سابقتِها**،
 * وإلّا صارت الحمولةُ في مكانَين في السجلّ.
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

$pp = check_page_permissions($conn, 'Transport/transfer_trip_legs.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('transfer_trip_legs super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'order_id DESC, leg_seq ASC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('trp_trip_leg', $opts);
} catch (\Throwable $t) { error_log('transfer_trip_legs list: ' . $t->getMessage()); }
try { foreach ($gate->select('trp_trip_leg', array('columns' => array('state'), 'limit' => 400)) as $c) { if ((string) $c['state'] !== '') { $choices[(string) $c['state']] = true; } } }
catch (\Throwable $t) { error_log('transfer_trip_legs choices: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('transfer_orders', array('columns' => array('id', 'order_no'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['order_no']; } }
catch (\Throwable $t) { error_log('transfer_trip_legs orders: ' . $t->getMessage()); }

$moving = 0; $km = 0.0;
foreach ($rows as $r) { if ((string) $r['state'] === 'in_transit') { $moving++; } $km += (float) $r['distance_km']; }
$km = round($km, 2);

$page_title = 'إيكوبيشن | مراحل الرحلة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مراحل الرحلة'; $header_icon = 'fa fa-route'; $header_actions = array();
    $header_back = array('href' => 'transfer_in_transit.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الرحلات الجارية');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_trp_transfer_trip_legs
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف المرحلة' => 'g80',
            'رقم الأمر' => 'g81',
            'تسلسل المرحلة' => 'g82',
            'من نقطة' => 'g83',
            'إلى نقطة' => 'g84',
            'الناقلة المكلفة' => 'g85',
            'السائق' => 'g86',
            'المسافة المقدرة' => 'g87',
            'بدء المرحلة' => 'g88',
            'انتهاء المرحلة' => 'g89',
            'تسليم المرحلة للتالية' => 'g90',
            'أحداث المرحلة' => 'g91',
            'حالة المرحلة' => 'g92',
            'المنشئ' => 'g93',
            'تاريخ الإنشاء' => 'g94',
            'حالة البيانات' => 'g95',
            'مرجع المصدر' => 'g96',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('trp_transfer_trip_legs');
        echo ems_w14_grid('emsList_trp_transfer_trip_legs', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في مراحل الرحلة'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">مراحل مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $moving ?></div><div class="ems-stat-label">جارية الآن</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $km ?></div><div class="ems-stat-label">مجموع المسافة</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_tl_s">حالة المرحلة</label><select name="state" id="w7_tl_s" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مراحل رحلة مسجلة', 'المرحلة تفتح على أمر ترحيل بتسلسلها. ولا تبدأ قبل تسليم سابقتها'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>أمر الترحيل</th><th>التسلسل</th><th>من نقطة</th><th>إلى نقطة</th><th>المسافة</th><th>بدء المرحلة</th><th>انتهاء المرحلة</th><th>سلمت للتالية</th><th>أحداث المرحلة</th><th>حالة المرحلة</th><th>قاعدة الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['leg_seq']) ?></td>
                <td><?= htmlspecialchars((string) $r['from_point']) ?></td>
                <td><?= htmlspecialchars((string) $r['to_point']) ?></td>
                <td><?= htmlspecialchars((string) $r['distance_km']) ?></td>
                <td><?= htmlspecialchars((string) $r['started_at']) ?></td>
                <td><?= htmlspecialchars((string) $r['ended_at']) ?></td>
                <td><?= ((int) $r['handover_to_next'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= htmlspecialchars((string) $r['events_count']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="12">لا مراحل رحلة مسجلة.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

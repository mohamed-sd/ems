<?php
/**
 * Maintenance/repeat_repairs.php — سجل إعادة الإصلاح (RPR-W07 · MNT-15)
 * ───────────────────────────────────────────────────────────────────────────
 * **«التكرارُ خلالَ الصلاحيةِ يفتح التحليل»** (`MNT-15`) — و`ضمن الصلاحية`
 * و`المدّة منذ الشهادة` **عمودانِ مشتقّانِ** من `mnt_return_cert.valid_until`
 * لا مُدخَلان، والبوّابةُ تعيد اشتقاقَهما وتقارنهما بالمخزَّن.
 * **و`RCA` لا يقتصر على المتكرِّر**: المحفِّزُ قد يكون سلامةً حرجةً أو كلفةً
 * مرتفعةً أو قرارًا يدويًّا — فالمحفِّزُ عمودٌ مستقلٌّ لا استنتاج.
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

$pp = check_page_permissions($conn, 'Maintenance/repeat_repairs.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('repeat_repairs super') : ems_tenant_db();
$pick = isset($_GET['rca_trigger']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['rca_trigger']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'repeat_date DESC, id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('rca_trigger' => $pick); }
    $rows = $gate->select('mnt_repeat_repair', $opts);
} catch (\Throwable $t) { error_log('repeat_repairs list: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_repeat_repair', array('columns' => array('rca_trigger'), 'limit' => 400)) as $c) { if ((string) $c['rca_trigger'] !== '') { $choices[(string) $c['rca_trigger']] = true; } } }
catch (\Throwable $t) { error_log('repeat_repairs choices: ' . $t->getMessage()); }
$equip = array();
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 500)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('repeat_repairs equip: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('mnt_order', array('columns' => array('id', 'code'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['code']; } }
catch (\Throwable $t) { error_log('repeat_repairs orders: ' . $t->getMessage()); }

$within = 0; $open = 0;
foreach ($rows as $r) { if ((int) $r['within_validity'] === 1) { $within++; } if ((string) $r['rca_state'] !== 'closed') { $open++; } }

$page_title = 'إيكوبيشن | سجل إعادة الإصلاح';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل إعادة الإصلاح'; $header_icon = 'fa fa-rotate-left'; $header_actions = array();
    $header_back = array('href' => 'orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر العمل');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <table id="emsList_mnt_repeat_repairs"></table>
    </div></div></div>
    <?php  ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_mnt_repeat_repairs
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الواقعة' => 'g101',
            'كود المعدة' => 'g102',
            'رقم الأمر الأصلي' => 'g103',
            'عقدة الشجرة' => 'g104',
            'تاريخ التكرار' => 'g105',
            'المدة منذ الشهادة' => 'g106',
            'ضمن صلاحية الشهادة؟' => 'g107',
            'تحليل السبب الجذري RCA' => 'g108',
            'رقم الأمر الجديد' => 'g109',
            'محفز RCA' => 'g110',
            'القرار' => 'g111',
            'حالة الواقعة' => 'g112',
            'المنشئ' => 'g113',
            'تاريخ الإنشاء' => 'g114',
            'حالة البيانات' => 'g115',
            'مرجع المصدر' => 'g116',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('mnt_repeat_repairs');
        echo ems_w14_grid('emsList_mnt_repeat_repairs', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في سجل إعادة الإصلاح'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">وقائع تكرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $within ?></div><div class="ems-stat-label">ضمن صلاحية الشهادة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $open ?></div><div class="ems-stat-label">تحليل مفتوح</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_rr_t">محفز التحليل</label><select name="rca_trigger" id="w7_rr_t" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع إعادة إصلاح', 'الواقعة تفتح حين يتكرر العطل على العقدة نفسها. والصلاحية تقرأ من الشهادة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>المعدة</th><th>الأمر الأصلي</th><th>عقدة الشجرة</th><th>تاريخ التكرار</th><th>المدة منذ الشهادة</th><th>ضمن الصلاحية</th><th>محفز التحليل</th><th>حالة التحليل</th><th>السبب الجذري</th><th>قاعدة الاشتقاق</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars(isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id'])) ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['origin_order_id']]) ? $orders[(int) $r['origin_order_id']] : ('#' . (int) $r['origin_order_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['tree_node']) ?></td>
                <td><?= htmlspecialchars((string) $r['repeat_date']) ?></td>
                <td><?= ($r['days_since_cert'] === null || (int) $r['days_since_cert'] === 0) ? '—' : (int) $r['days_since_cert'] ?></td>
                <td><?= ((int) $r['within_validity'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['rca_trigger'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['rca_state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $r['root_cause'], 0, 120)) ?></small></td>
                <td><small><?= htmlspecialchars((string) $r['derivation_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11">لا وقائع إعادة إصلاح.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

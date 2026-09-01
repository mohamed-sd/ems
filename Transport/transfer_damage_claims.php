<?php
/**
 * Transport/transfer_damage_claims.php — مطالبات التلف والحوادث (RPR-W07 · TRP-10)
 * ───────────────────────────────────────────────────────────────────────────
 * **«التلفُ الموثَّقُ في المحضرِ أو حادثُ الرحلةِ يفتح مطالبةً على المتسبِّبِ
 * بقاعدةِ المتحمِّل»** (`TRP-10`). و`openClaim` تردُّ
 * `LIABLE_WITHOUT_CONTRACT_RULE`: تحديدُ متسبِّبٍ بلا قاعدةِ عقدٍ **حكمٌ بلا
 * سند**، و`CLAIM_WITHOUT_INCIDENT_EVIDENCE`: مطالبةٌ بلا مرجعِ واقعةٍ ووصفِ
 * تلفٍ ليست مطالبة.
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

$pp = check_page_permissions($conn, 'Transport/transfer_damage_claims.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('transfer_damage_claims super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('trp_damage_claim', $opts);
} catch (\Throwable $t) { error_log('transfer_damage_claims list: ' . $t->getMessage()); }
try { foreach ($gate->select('trp_damage_claim', array('columns' => array('state'), 'limit' => 400)) as $c) { if ((string) $c['state'] !== '') { $choices[(string) $c['state']] = true; } } }
catch (\Throwable $t) { error_log('transfer_damage_claims choices: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('transfer_orders', array('columns' => array('id', 'order_no'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['order_no']; } }
catch (\Throwable $t) { error_log('transfer_damage_claims orders: ' . $t->getMessage()); }

$amount = 0.0; $undet = 0;
foreach ($rows as $r) { $amount += (float) $r['claim_amount']; if ((string) $r['liable_party'] === 'undetermined') { $undet++; } }
$amount = round($amount, 2);

$page_title = 'إيكوبيشن | مطالبات التلف والحوادث';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مطالبات التلف والحوادث'; $header_icon = 'fa fa-triangle-exclamation'; $header_actions = array();
    $header_back = array('href' => 'transfer_arrival.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'محاضر الاستلام');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_trp_transfer_damage_claims
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم المطالبة' => 'g97',
            'رقم الأمر' => 'g98',
            'مرجع الواقعة' => 'g99',
            'وصف التلف' => 'g100',
            'المتسبب المرجح' => 'g101',
            'قيمة المطالبة المقدرة' => 'g102',
            'مستندات الإثبات' => 'g103',
            'مسار المطالبة' => 'g104',
            'قرار التسوية' => 'g105',
            'قيمة التسوية' => 'g106',
            'حالة المطالبة' => 'g107',
            'المنشئ' => 'g108',
            'تاريخ الإنشاء' => 'g109',
            'المراجع' => 'g110',
            'المعتمد' => 'g111',
            'تاريخ الاعتماد' => 'g112',
            'حالة البيانات' => 'g113',
            'مرجع المصدر' => 'g114',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('trp_transfer_damage_claims');
        echo ems_w14_grid('emsList_trp_transfer_damage_claims', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في مطالبات التلف والحوادث'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">مطالبات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $amount ?></div><div class="ems-stat-label">قيمة المطالبات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $undet ?></div><div class="ems-stat-label">متسبب غير محدد</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_dc_s">حالة المطالبة</label><select name="state" id="w7_dc_s" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مطالبات تلف ولا حوادث', 'المطالبة تفتح من محضر استلام موثق أو حدث رحلة. بقاعدة المتحمل من العقد'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>رقم المطالبة</th><th>أمر الترحيل</th><th>مرجع الواقعة</th><th>وصف التلف</th><th>المتحمل</th><th>قاعدة المتحمل</th><th>قيمة المطالبة</th><th>مسار المطالبة</th><th>قيمة التسوية</th><th>حالة المطالبة</th><th>قاعدة الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars((string) $r['claim_no']) ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['incident_ref']) ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $r['damage_desc'], 0, 120)) ?></small></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['liable_party'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['liable_rule']) ?></small></td>
                <td><?= htmlspecialchars((string) $r['claim_amount']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['claim_route'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['settlement_amount']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="12">لا مطالبات تلف ولا حوادث.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

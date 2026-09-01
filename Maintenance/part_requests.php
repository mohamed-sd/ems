<?php
/**
 * Maintenance/part_requests.php — طلبات صرف القطع (RPR-W07 · MNT-10)
 * ───────────────────────────────────────────────────────────────────────────
 * **«الصيانةُ تطلب؛ المخازنُ تصرف بسندِها — ولا صرفَ لأمرٍ مقفل»** (`MNT-10` نصًّا).
 * فالسطحُ يعرض الطلبَ **بسندِ صرفِه ومطابقةِ استلامِه**؛ والصرفُ الفعليُّ ورقمُ
 * السندِ يأتيان من المخازنِ ولا يُكتبان هنا. والمنعُ (‏`ORDER_CLOSED_NO_ISSUE`)
 * في `MaintenanceCycleService::requestParts` **يُقاس وظيفيًّا لا يُدَّعى**.
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

$pp = check_page_permissions($conn, 'Maintenance/part_requests.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('part_requests super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('mnt_part_request', $opts);
} catch (\Throwable $t) { error_log('part_requests list: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_part_request', array('columns' => array('state'), 'limit' => 400)) as $c) { if ((string) $c['state'] !== '') { $choices[(string) $c['state']] = true; } } }
catch (\Throwable $t) { error_log('part_requests choices: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('mnt_order', array('columns' => array('id', 'code'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['code']; } }
catch (\Throwable $t) { error_log('part_requests orders: ' . $t->getMessage()); }

$issued = 0; $noDoc = 0;
foreach ($rows as $r) { if ((string) $r['issue_doc_ref'] !== '') { $issued++; } else { $noDoc++; } }

$page_title = 'إيكوبيشن | طلبات صرف القطع';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلبات صرف القطع'; $header_icon = 'fa fa-boxes-stacked'; $header_actions = array();
    $header_back = array('href' => 'orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر العمل');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <table id="emsList_mnt_part_requests"></table>
    </div></div></div>
    <?php  ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_mnt_part_requests
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الطلب' => 'g140',
            'رقم الأمر' => 'g141',
            'تاريخ الطلب' => 'g142',
            'المخزن' => 'g143',
            'البنود المطلوبة' => 'g144',
            'الأولوية' => 'g145',
            'مستلم العهدة' => 'g146',
            'رقم سند الصرف' => 'g147',
            'مطابقة الاستلام' => 'g148',
            'حالة الطلب' => 'g149',
            'المنشئ' => 'g150',
            'تاريخ الإنشاء' => 'g151',
            'حالة البيانات' => 'g152',
            'مرجع المصدر' => 'g153',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('mnt_part_requests');
        echo ems_w14_grid('emsList_mnt_part_requests', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في طلب صرف القطع لأمر العمل'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">طلبات صرف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $issued ?></div><div class="ems-stat-label">صرفت بسندها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $noDoc ?></div><div class="ems-stat-label">بلا سند صرف</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_pr_st">حالة الطلب</label><select name="state" id="w7_pr_st" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات صرف قطع', 'الطلب يفتح من أمر عمل غير مقفل. والصرف بسند من المخازن'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الطلب</th><th>أمر العمل</th><th>تاريخ الطلب</th><th>المخزن</th><th>البنود</th><th>الأولوية</th><th>سند الصرف</th><th>مطابقة الاستلام</th><th>حالة الطلب</th><th>قاعدة الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['req_no']) ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['request_date']) ?></td>
                <td><?= htmlspecialchars((string) $r['warehouse_ref']) ?></td>
                <td><?= htmlspecialchars((string) $r['lines_count']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['priority'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['issue_doc_ref']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['receipt_match'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10">لا طلبات صرف قطع.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

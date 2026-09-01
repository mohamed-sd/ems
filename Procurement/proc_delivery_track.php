<?php
/**
 * Procurement/proc_delivery_track.php — متابعة التوريد والاستلام (RPR-W09 · PRC-14)
 * ───────────────────────────────────────────────────────────────────────────
 * **«حدثُ توريدٍ × أمر — سطرُ متابعة»** (`PRC-14` نصًّا). و`مدة التأخر`
 * **عمودٌ مشتقٌّ** من فرقِ الوعدِ عن الواقعِ لا مُدخَل، والبوّابةُ `W9-16`
 * تعيد اشتقاقَه وتقارنه بالمخزَّن.
 *
 * ◆ **والتأخّرُ بلا سببٍ رقمٌ بلا معنى**: `logDelivery` تردُّ
 *   `DELAY_WITHOUT_REASON` على حدثِ تأخّرٍ بلا نصٍّ — و`chk_dlv_delay`
 *   يمنعه في المخطَّطِ أيضًا، فالمنعُ طبقتان لا نيّة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/proc_delivery_track.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_delivery_track super') : ems_tenant_db();
$pick = isset($_GET['kind']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['kind']) : '';

$rows = array(); $choices = array(); $orders = array(); $sups = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 500);
    if ($pick !== '') { $opts['where'] = array('event_kind' => $pick); }
    $rows = $gate->select('proc_delivery_event', $opts);
} catch (\Throwable $t) { error_log('delivery_track list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['event_kind'] !== '') { $choices[(string) $r['event_kind']] = true; } }
try { foreach ($gate->select('proc_order', array('columns' => array('id', 'code', 'supplier_id', 'expected_delivery_date', 'received_pct'), 'limit' => 900)) as $o) { $orders[(int) $o['id']] = $o; } }
catch (\Throwable $t) { error_log('delivery_track orders: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'), 'limit' => 900)) as $s) { $sups[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('delivery_track sups: ' . $t->getMessage()); }

$lateN = 0; $lateDays = 0; $arrived = 0;
foreach ($rows as $r) {
    if ((int) $r['delay_days'] > 0) { $lateN++; $lateDays += (int) $r['delay_days']; }
    if ((string) $r['event_kind'] === 'ARRIVED') { $arrived++; }
}

$page_title = 'إيكوبيشن | متابعة التوريد والاستلام';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'متابعة التوريد والاستلام'; $header_icon = 'fa fa-truck-fast'; $header_actions = array();
    $header_back = array('href' => 'orders_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الشراء');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_prc_proc_delivery_track
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف السطر' => 'g77',
            'رقم الأمر' => 'g78',
            'نوع الحدث' => 'g79',
            'تاريخ الحدث' => 'g80',
            'الكمية المشمولة' => 'g81',
            'رقم سند الإدخال' => 'g82',
            'نتيجة الفحص' => 'g83',
            'أيام التأخير' => 'g84',
            'إخطار المورد' => 'g85',
            'حالة السطر' => 'g86',
            'المنشئ' => 'g87',
            'تاريخ الإنشاء' => 'g88',
            'حالة البيانات' => 'g89',
            'مرجع المصدر' => 'g90',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('prc_proc_delivery_track');
        echo ems_w14_grid('emsList_prc_proc_delivery_track', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في متابعة التوريد والاستلام'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أحداث توريد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $arrived ?></div><div class="ems-stat-label">شحنات وصلت</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $lateN ?></div><div class="ems-stat-label">وصلت متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $lateDays ?></div><div class="ems-stat-label">مجموع أيام التأخر</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_dlv_k">نوع الحدث</label><select name="kind" id="w9_dlv_k" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أحداث توريد', 'الحدث يسجل الوعد والشحن والوصول والتأخر. ومدة التأخر مشتقة لا تكتب'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الأمر</th><th>المورد</th><th>نوع الحدث</th><th>تاريخ الحدث</th><th>الموعد الموعود</th><th>الكمية المتوقعة</th><th>الكمية الفعلية</th><th>أيام التأخر</th><th>سبب التأخر</th><th>سند الإدخال</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $o = isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars($o ? (string) $o['code'] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= htmlspecialchars($o && isset($sups[(int) $o['supplier_id']]) ? $sups[(int) $o['supplier_id']] : '') ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['event_kind'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['event_date']) ?></td>
                <td><?= htmlspecialchars($o ? (string) $o['expected_delivery_date'] : '') ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['qty_expected'], 3)) ?></td>
                <td><?= htmlspecialchars(number_format((float) $r['qty_actual'], 3)) ?></td>
                <td><?= (int) $r['delay_days'] ?></td>
                <td><?= htmlspecialchars((string) $r['delay_why']) ?></td>
                <td><?= ((int) $r['receipt_id'] > 0 ? (int) $r['receipt_id'] : '') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

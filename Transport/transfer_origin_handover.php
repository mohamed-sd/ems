<?php
/**
 * Transport/transfer_origin_handover.php — تجهيز المغادرة والتسليم الأصلي (RPR-W07 · TRP-06)
 * ───────────────────────────────────────────────────────────────────────────
 * **«لا مغادرةَ قبل اكتمالِ التجهيز»** (`TRP-06` نصًّا): حالةُ ما قبل النقلِ
 * والتحميلُ والتثبيتُ وتقييمُ مخاطرِ المسار. و`authorizeDeparture` تردُّ
 * `HANDOVER_INCOMPLETE` على بندٍ واحدٍ معلَّقٍ أو مخفق — **والمنعُ وظيفيٌّ
 * يُقاس** لا شرطٌ في مخطَّطٍ لا يُختبَر.
 * **ومعه بوّابةُ التصريح**: `PERMIT_EXPIRED` بسماحٍ من `repair01_w7_thresholds`
 * لا من رقمٍ مكتوبٍ في الشيفرة (`TRP-05`).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Transport/transfer_origin_handover.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('transfer_origin_handover super') : ems_tenant_db();
$pick = isset($_GET['result']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['result']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'order_id DESC, id ASC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('result' => $pick); }
    $rows = $gate->select('trp_origin_handover', $opts);
} catch (\Throwable $t) { error_log('transfer_origin_handover list: ' . $t->getMessage()); }
try { foreach ($gate->select('trp_origin_handover', array('columns' => array('result'), 'limit' => 400)) as $c) { if ((string) $c['result'] !== '') { $choices[(string) $c['result']] = true; } } }
catch (\Throwable $t) { error_log('transfer_origin_handover choices: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('transfer_orders', array('columns' => array('id', 'order_no'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['order_no']; } }
catch (\Throwable $t) { error_log('transfer_origin_handover orders: ' . $t->getMessage()); }

$blocking = 0; $risky = 0;
foreach ($rows as $r) { if (in_array((string) $r['result'], array('pending','failed'), true)) { $blocking++; } if ((string) $r['route_risk'] === 'high') { $risky++; } }

$page_title = 'إيكوبيشن | تجهيز المغادرة والتسليم الأصلي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تجهيز المغادرة والتسليم الأصلي'; $header_icon = 'fa fa-truck-ramp-box'; $header_actions = array();
    $header_back = array('href' => 'transfer_orders_list.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الترحيل');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">بنود تجهيز</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $blocking ?></div><div class="ems-stat-label">تحجب المغادرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $risky ?></div><div class="ems-stat-label">مخاطر مسار مرتفعة</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_oh_r">النتيجة</label><select name="result" id="w7_oh_r" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بنود تجهيز مغادرة', 'التجهيز يفتح على أمر ترحيل. ولا مغادرة قبل اكتماله'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>أمر الترحيل</th><th>بند التجهيز</th><th>النتيجة</th><th>محضر التسليم الأصلي</th><th>صور ما قبل النقل</th><th>مخاطر المسار</th><th>وقت الإنجاز</th><th>حالة البند</th><th>قاعدة الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['item_ar']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['result'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['handover_ref']) ?></td>
                <td><?= htmlspecialchars((string) $r['photo_ref']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['route_risk'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['done_at']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10">لا بنود تجهيز مغادرة.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

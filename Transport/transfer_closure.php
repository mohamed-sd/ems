<?php
/**
 * Transport/transfer_closure.php — إقفال أمر الترحيل (RPR-W07 · TRP-12)
 * ───────────────────────────────────────────────────────────────────────────
 * **«الـGrain مفصول: البنودُ في سجلِّها والإقفالُ هنا»** (`TRP-12` نصًّا).
 * فالإقفالُ **واقعةٌ واحدةٌ لكلِّ أمرٍ بمعتمِدِها**، و`إجمالي التكلفة`
 * و`عدد البنود` و`التوزيع بالمتحمِّل` **ثلاثةُ أعمدةٍ مشتقّةٍ** يُعاد بناؤها
 * من `transfer_cost_lines` — والبوّابةُ تقارن المخزَّنَ بالمقيس.
 * ⛔ **ولا إقفالَ قبل محضرِ الاستلام** ولا اعتمادَ بلا ترحيلِ قراءةِ العدّاد،
 * ومَن أنشأ الإقفالَ لا يعتمده (`SOD_SELF_APPROVAL`).
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

$pp = check_page_permissions($conn, 'Transport/transfer_closure.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('transfer_closure super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';

$rows = array(); $choices = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 400);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('trp_closure', $opts);
} catch (\Throwable $t) { error_log('transfer_closure list: ' . $t->getMessage()); }
try { foreach ($gate->select('trp_closure', array('columns' => array('state'), 'limit' => 400)) as $c) { if ((string) $c['state'] !== '') { $choices[(string) $c['state']] = true; } } }
catch (\Throwable $t) { error_log('transfer_closure choices: ' . $t->getMessage()); }
$orders = array();
try { foreach ($gate->select('transfer_orders', array('columns' => array('id', 'order_no'), 'orderBy' => 'id DESC', 'limit' => 800)) as $o) { $orders[(int) $o['id']] = (string) $o['order_no']; } }
catch (\Throwable $t) { error_log('transfer_closure orders: ' . $t->getMessage()); }

$approved = 0; $total = 0.0;
foreach ($rows as $r) { if ((string) $r['state'] === 'approved') { $approved++; } $total += (float) $r['total_cost']; }
$total = round($total, 2);

$page_title = 'إيكوبيشن | إقفال أمر الترحيل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إقفال أمر الترحيل'; $header_icon = 'fa fa-file-circle-check'; $header_actions = array();
    $header_back = array('href' => 'transfer_close_cost.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'بنود تكلفة الرحلة');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">أوامر بإقفال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $approved ?></div><div class="ems-stat-label">معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $total ?></div><div class="ems-stat-label">إجمالي التكلفة</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_cl_s">حالة الإقفال</label><select name="state" id="w7_cl_s" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أوامر ترحيل مقفلة', 'الإقفال يفتح بعد محضر الاستلام. والتكلفة تشتق من البنود ولا تدخل'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>أمر الترحيل</th><th>محضر الاستلام</th><th>عدد بنود التكلفة</th><th>إجمالي التكلفة</th><th>التوزيع بالمتحمل</th><th>ترحيل قراءة العداد</th><th>الإحالة للمالية</th><th>حالة الإقفال</th><th>قاعدة الحالة</th><th>قاعدة الاشتقاق</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $r['order_id']]) ? $orders[(int) $r['order_id']] : ('#' . (int) $r['order_id'])) ?></td>
                <td><?= ($r['delivery_doc_id'] === null || (int) $r['delivery_doc_id'] === 0) ? '—' : (int) $r['delivery_doc_id'] ?></td>
                <td><?= htmlspecialchars((string) $r['cost_lines_count']) ?></td>
                <td><?= htmlspecialchars((string) $r['total_cost']) ?></td>
                <td><?= htmlspecialchars((string) $r['bearer_split']) ?></td>
                <td><?= ((int) $r['meter_posted'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><?= htmlspecialchars((string) $r['finance_ref']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
                <td><small><?= htmlspecialchars((string) $r['derivation_rule']) ?></small></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11">لا أوامر ترحيل مقفلة.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>

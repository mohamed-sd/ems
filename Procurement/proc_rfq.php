<?php
/**
 * Procurement/proc_rfq.php — طلب العروض ودعوات الموردين (RPR-W09 · PRC-06 · PRC-07)
 * ───────────────────────────────────────────────────────────────────────────
 * **«طلبُ عروضٍ × حزمة — طلبٌ واحد»** (`PRC-06` نصًّا). وهو **كيانٌ مستقلٌّ عن
 * `supplier_rfqs`** بقرارٍ مقيس (`W9-D-02`): حبّةُ ذاك **طلبٌ × عقدِ عميل**
 * وحبّةُ هذا **طلبٌ × حزمةِ شراء** — حبّتانِ مختلفتانِ فكيانان، والتمييزُ
 * مكتوبٌ كيلا يُدمَجا لاحقًا بحسنِ نيّة.
 *
 * ◆ **والمظروفُ لا يُقرأ قبل موعدِه**: `openEnvelopes` تردُّ `RFQ_NOT_DUE_NO_OPEN`
 *   قبل `open_at`. فالعمودُ `موعد الفتح` هنا **بوّابةٌ لا معلومة**.
 *
 * ◆ **والدعوةُ سطرٌ بردِّها**: `offered` أو `declined` بسببٍ مكتوبٍ أو `silent` —
 *   والصمتُ يُسجَّل صمتًا ولا يُقرأ رفضًا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/proc_rfq.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_rfq super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';
$open = isset($_GET['rfq']) ? (int) $_GET['rfq'] : 0;

$rows = array(); $choices = array(); $invites = array(); $pkgs = array(); $sups = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 300);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('proc_rfq', $opts);
} catch (\Throwable $t) { error_log('proc_rfq list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['state'] !== '') { $choices[(string) $r['state']] = true; } }
try { foreach ($gate->select('proc_package', array('columns' => array('id', 'code', 'title'), 'limit' => 500)) as $p) { $pkgs[(int) $p['id']] = $p; } }
catch (\Throwable $t) { error_log('proc_rfq pkgs: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'), 'limit' => 900)) as $s) { $sups[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('proc_rfq sups: ' . $t->getMessage()); }
if ($open > 0) {
    try { $invites = $gate->select('proc_rfq_invite', array('where' => array('rfq_id' => $open), 'orderBy' => 'id')); }
    catch (\Throwable $t) { error_log('proc_rfq invites: ' . $t->getMessage()); }
}
$notOpened = 0; $awarded = 0;
foreach ($rows as $r) { if ((string) $r['state'] !== 'opened' && (string) $r['state'] !== 'awarded') { $notOpened++; } if ((string) $r['state'] === 'awarded') { $awarded++; } }

$page_title = 'إيكوبيشن | طلب العروض ودعوات الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلب العروض ودعوات الموردين'; $header_icon = 'fa fa-envelope-open-text'; $header_actions = array();
    $header_back = array('href' => 'proc_packages.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'حزم التجميع');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">طلبات عروض</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $notOpened ?></div><div class="ems-stat-label">مظاريف لم تفتح</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $awarded ?></div><div class="ems-stat-label">رست</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_rfq_st">حالة الطلب</label><select name="state" id="w9_rfq_st" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات عروض', 'الطلب يصدر عن حزمة واحدة. والمظروف لا يقرأ قبل موعد فتحه المعلن'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز الطلب</th><th>الحزمة</th><th>العنوان</th><th>آخر موعد للتقديم</th><th>موعد فتح المظاريف</th><th>تاريخ الفتح</th><th>الدعوات</th><th>العروض</th><th>الحالة</th><th>الدعوات</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $p = isset($pkgs[(int) $r['package_id']]) ? $pkgs[(int) $r['package_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['code']) ?></td>
                <td><?= htmlspecialchars($p ? (string) $p['code'] : ('#' . (int) $r['package_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['title']) ?></td>
                <td><?= htmlspecialchars((string) $r['due_date']) ?></td>
                <td><?= htmlspecialchars((string) $r['open_at']) ?></td>
                <td><?= htmlspecialchars((string) $r['opened_at']) ?></td>
                <td><?= (int) $r['invite_count'] ?></td>
                <td><?= (int) $r['offer_count'] ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><a href="?rfq=<?= (int) $r['id'] ?>">عرض المدعوين</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <?php if ($open > 0): ?>
    <h3 class="ems-section-title">الموردون المدعوون</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>المورد</th><th>قناة الدعوة</th><th>تاريخ الدعوة</th><th>الرد</th><th>تاريخ الرد</th><th>سبب الاعتذار</th></tr></thead>
        <tbody>
        <?php if ($invites): foreach ($invites as $v): ?>
            <tr>
                <td><?= htmlspecialchars(isset($sups[(int) $v['supplier_id']]) ? $sups[(int) $v['supplier_id']] : ('#' . (int) $v['supplier_id'])) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $v['channel'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $v['invited_at']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $v['response'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $v['responded_at']) ?></td>
                <td><?= htmlspecialchars((string) $v['decline_why']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="6">لا موردين مدعوين لهذا الطلب</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
</body></html>

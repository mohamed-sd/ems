<?php
/**
 * Financing/fin_monthly_close.php — الإقفالات الشهرية وكشف الحساب (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * اقفال شهري واحد: ممول وعملية وشهر تقويمي وعملة — يضم الاقفالات التعاقدية ولا يحل محلها.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-16)
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/w12_view.php';

$ctx = w12_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w12_perms($conn, 'Financing/fin_monthly_close.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_monthly_close',
                 array('orderBy' => 'op_id, accounting_month', 'limit' => 500));

$page_title = 'إيكوبيشن | الإقفالات الشهرية وكشف الحساب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإقفالات الشهرية وكشف الحساب'; $header_icon = 'fa fa-calendar-check'; $header_actions = array();
    $header_back = array('href' => 'fin_contract_close.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الإقفالات التعاقدية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاقفالات الشهرية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "approved") ?></div><div class="ems-stat-label">معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "financier_stmt_match", "matched") ?></div><div class="ems-stat-label">مطابقة لكشف الممول</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_sum($rows, "contract_closes_n") ?></div><div class="ems-stat-label">اقفالات تعاقدية مضمومة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اقفالات شهرية', 'الشهري شهر تقويمي بحده ولا يقبل فترة تعاقدية'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_monthly_close')); ?>
    <table id="emsList_fin_monthly_close" class="data-table">
        <thead><tr><th>كود الاقفال</th><th>العملية</th><th>الممول</th><th>الشهر المحاسبي</th><th>بداية الشهر</th><th>نهاية الشهر</th><th>اقفالات تعاقدية بالشهر</th><th>رصيد أول الشهر</th><th>المستحق بالشهر</th><th>المدفوع بالشهر</th><th>المخصص بالشهر</th><th>غير المخصص</th><th>المتأخر بالشهر</th><th>رصيد آخر الشهر</th><th>مطابقة كشف الممول</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["close_code"]) ?></td>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["accounting_month"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["month_start"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["month_end"]) ?></td>
                    <td><?= (int) $r["contract_closes_n"] ?></td>
                    <td><?= ems_w12_num($r["open_balance"]) ?></td>
                    <td><?= ems_w12_num($r["due_in_month"]) ?></td>
                    <td><?= ems_w12_num($r["paid_in_month"]) ?></td>
                    <td><?= ems_w12_num($r["allocated_in_month"]) ?></td>
                    <td><?= ems_w12_num($r["unallocated_in_month"]) ?></td>
                    <td><?= ems_w12_num($r["arrears_in_month"]) ?></td>
                    <td><?= ems_w12_num($r["close_balance"]) ?></td>
                    <td><?= ems_w12_state((string) $r["financier_stmt_match"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

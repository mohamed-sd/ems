<?php
/**
 * Financing/fin_final_close.php — إقفال التمويل (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * اقفال نهائي واحد لعملية مرة واحدة — لا يعتمد باستحقاق مفتوح ولا بانحراف حاجب ولا بلا اخلاء طرف.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-24)
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

$perms = w12_perms($conn, 'Financing/fin_final_close.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_final_close',
                 array('orderBy' => 'id DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | إقفال التمويل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إقفال التمويل'; $header_icon = 'fa fa-flag-checkered'; $header_actions = array();
    $header_back = array('href' => 'deviations.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'انحرافات التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاقفالات النهائية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "approved") ?></div><div class="ems-stat-label">معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "ownership_transferred", "1") ?></div><div class="ems-stat-label">اكتمل نقل الملكية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_nonzero($rows, "open_deviations_n") ?></div><div class="ems-stat-label">محجوبة بانحراف</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اقفالات نهائية', 'النهائي يقرا اخر اقفال دوري ولا يحل محله'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_final_close')); ?>
    <table id="emsList_fin_final_close" class="data-table">
        <thead><tr><th>كود الاقفال</th><th>العملية</th><th>الممول</th><th>تاريخ الطلب</th><th>تاريخ الاقفال</th><th>آخر اقفال دوري</th><th>المتبقي من الأصل</th><th>المتبقي من العائد</th><th>استحقاقات مفتوحة</th><th>انحرافات حاجبة</th><th>نقل الملكية</th><th>مستند الملكية</th><th>إخلاء الطرف</th><th>تسوية مبكرة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["close_code"]) ?></td>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["requested_on"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["closed_on"]) ?></td>
                    <td><?= (int) $r["last_periodic_close_id"] ?></td>
                    <td><?= ems_w12_num($r["residual_principal"]) ?></td>
                    <td><?= ems_w12_num($r["residual_profit"]) ?></td>
                    <td><?= (int) $r["open_dues_n"] ?></td>
                    <td><?= (int) $r["open_deviations_n"] ?></td>
                    <td><?= ((int) $r["ownership_transferred"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= htmlspecialchars((string) $r["ownership_doc_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["clearance_doc_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["early_settlement_ref"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

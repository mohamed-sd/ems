<?php
/**
 * Financing/fin_offers.php — عروض التمويل والتفاوض (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * عرض تمويل واحد بطبقة اصدارات — والتفاوض ينشئ نسخة ولا يدهس سابقتها.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-05)
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

$perms = w12_perms($conn, 'Financing/fin_offers.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_funding_offer',
                 array('orderBy' => 'offer_code, version_no DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | عروض التمويل والتفاوض';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'عروض التمويل والتفاوض'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'fin_needs.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'احتياجات التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاصدارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "offer_code") ?></div><div class="ems-stat-label">عروض متمايزة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "shortlisted") ?></div><div class="ems-stat-label">ضمن القائمة القصيرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "accepted") ?></div><div class="ems-stat-label">مقبولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عروض تمويل مسجلة', 'العرض يقابل حاجة معتمدة ومن ممول مؤهل'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_offers')); ?>
    <table id="emsList_fin_offers" class="data-table">
        <thead><tr><th>كود العرض</th><th>الاصدار</th><th>الممول</th><th>نموذج التمويل</th><th>اصل التمويل</th><th>العملة</th><th>نسبة العائد</th><th>المدة بالاشهر</th><th>فترة السماح</th><th>مستند العرض</th><th>ساري حتى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["offer_code"]) ?></td>
                    <td><?= (int) $r["version_no"] ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["model_code"]) ?></td>
                    <td><?= ems_w12_num($r["principal"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= ems_w12_num($r["profit_rate"]) ?></td>
                    <td><?= (int) $r["tenor_months"] ?></td>
                    <td><?= (int) $r["grace_months"] ?></td>
                    <td><?= htmlspecialchars((string) $r["offer_doc_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["valid_until"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

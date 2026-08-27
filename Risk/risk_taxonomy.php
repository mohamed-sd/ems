<?php
/**
 * Risk/risk_taxonomy.php — تصنيف المخاطر (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الشجرة الحاكمة للعائلات الأربع وكل خطر يسند لعقدة واحدة ولا نص حر
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ نطاقِه
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد.**
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w14_view.php';

$ctx = w14_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w14_perms($conn, 'Risk/risk_taxonomy.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'rsk_taxonomy',
                 array('orderBy' => 'family_code, depth_no, node_code', 'limit' => 500));

$page_title = 'إيكوبيشن | تصنيف المخاطر';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تصنيف المخاطر'; $header_icon = 'fa fa-sitemap'; $header_actions = array();
    $header_back = array('href' => 'risk_settings.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'إعدادات المخاطر');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد العقد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "family_code") ?></div><div class="ems-stat-label">العائلات المستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "active") ?></div><div class="ems-stat-label">عقد نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "depth_no", "1") ?></div><div class="ems-stat-label">عقد الجذر</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عقد تصنيف', 'التصنيف شجرة معتمدة لا قائمة تكتب'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_risk_taxonomy')); ?>
    <table id="emsList_risk_taxonomy" class="data-table">
        <thead><tr><th>رمز العقدة</th><th>العائلة</th><th>الفئة</th><th>النوع</th><th>العقدة الأم</th><th>المستوى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["node_code"]) ?></td>
                    <td><?= ems_w14_state((string) $r["family_code"]) ?></td>
                    <td><?= ems_w14_txt($r["category_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["type_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["parent_code"]) ?></td>
                    <td><?= (int) $r["depth_no"] ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

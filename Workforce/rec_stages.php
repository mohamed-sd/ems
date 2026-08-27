<?php
/**
 * Workforce/rec_stages.php — مراحل التوظيف (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * كل مرحلة سطر بنتيجتها ومقيمها ولا قفز مرحلة
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `PeopleCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w13_view.php';

$ctx = w13_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w13_perms($conn, 'Workforce/rec_stages.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'rec_stage_log',
                 array('orderBy' => 'app_id, log_id', 'limit' => 800));

$page_title = 'إيكوبيشن | مراحل التوظيف';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مراحل التوظيف'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'rec_applications.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'طلبات الترشح');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع المراحل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "app_id") ?></div><div class="ems-stat-label">الترشحات ذات المراحل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "to_stage") ?></div><div class="ems-stat-label">المراحل المستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "by_person") ?></div><div class="ems-stat-label">مراحل لها مقيم مسجل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مراحل مسجلة', 'المرحلة سطر بنتيجتها لا حالة تدهس سابقتها'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_rec_stages')); ?>
    <table id="emsList_rec_stages" class="data-table">
        <thead><tr><th>رقم الواقعة</th><th>الترشح</th><th>المرحلة السابقة</th><th>المرحلة التالية</th><th>المقيم</th><th>التاريخ والوقت</th><th>الملاحظة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["log_id"] ?></td>
                    <td><?= (int) $r["app_id"] ?></td>
                    <td><?= ems_w13_state((string) $r["from_stage"]) ?></td>
                    <td><?= ems_w13_state((string) $r["to_stage"]) ?></td>
                    <td><?= (int) $r["by_person"] ?></td>
                    <td><?= ems_w13_txt($r["at"]) ?></td>
                    <td><?= ems_w13_txt($r["note"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

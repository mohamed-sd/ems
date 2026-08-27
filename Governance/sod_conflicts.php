<?php
/**
 * Governance/sod_conflicts.php — فصل الواجبات المتعارضة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * التعارض يعرف مرة ويكشف دوما ولا يجمع فاعل واحد طرفي عملية واحدة
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

$perms = w14_perms($conn, 'Governance/sod_conflicts.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_sod_conflict',
                 array('orderBy' => 'conflict_code, id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | فصل الواجبات المتعارضة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'فصل الواجبات المتعارضة'; $header_icon = 'fa fa-code-branch'; $header_actions = array();
    $header_back = array('href' => 'auth_profiles.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الأدوار وقوالب صلاحياتها');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد التعارضات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "detected") ?></div><div class="ems-stat-label">تعارضات مكتشفة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "accepted") ?></div><div class="ems-stat-label">تعارضات مقبولة باستثناء</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "process_key") ?></div><div class="ems-stat-label">العمليات المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تعارضات معرفة', 'التعارض قاعدة تعرف مرة لا ملاحظة عابرة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_sod_conflicts')); ?>
    <table id="emsList_sod_conflicts" class="data-table">
        <thead><tr><th>رمز التعارض</th><th>التعارض</th><th>الطرف الأول</th><th>الطرف الثاني</th><th>الدور المكتشف</th><th>المستخدم المكتشف</th><th>الاستثناء</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["conflict_code"]) ?></td>
                    <td><?= ems_w14_txt($r["title_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["side_a"]) ?></td>
                    <td><?= ems_w14_txt($r["side_b"]) ?></td>
                    <td><?= (int) $r["detected_role_id"] ?></td>
                    <td><?= (int) $r["detected_user_id"] ?></td>
                    <td><?= ems_w14_txt($r["exception_no"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

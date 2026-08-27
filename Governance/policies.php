<?php
/**
 * Governance/policies.php — سجل السياسات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل قاعدة منع وكل مسار اعتماد يستند لسياسة نافذة بإصدارها ولا سياسة بلا مالك
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

$perms = w14_perms($conn, 'Governance/policies.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_policy',
                 array('orderBy' => 'policy_no, version_no DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | سجل السياسات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل السياسات'; $header_icon = 'fa fa-book'; $header_actions = array();
    $header_back = array('href' => 'entities_registry.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الشركات والكيانات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد إصدارات السياسات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "effective") ?></div><div class="ems-stat-label">سياسات نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "draft") ?></div><div class="ems-stat-label">مسودات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "review_due") ?></div><div class="ems-stat-label">سياسات لها موعد مراجعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سياسات مسجلة', 'السياسة إصدار بمالكه لا نص متداول'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_policies')); ?>
    <table id="emsList_policies" class="data-table">
        <thead><tr><th>رقم السياسة</th><th>الإصدار</th><th>العنوان</th><th>المجال</th><th>الإدارة المالكة</th><th>تاريخ النفاذ</th><th>موعد المراجعة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["policy_no"]) ?></td>
                    <td><?= (int) $r["version_no"] ?></td>
                    <td><?= ems_w14_txt($r["title_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["domain_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["owner_dept"]) ?></td>
                    <td><?= ems_w14_txt($r["effective_from"]) ?></td>
                    <td><?= ems_w14_txt($r["review_due"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

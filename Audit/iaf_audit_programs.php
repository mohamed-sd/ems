<?php
/**
 * Audit/iaf_audit_programs.php — برامج المراجعة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * البرنامج يربط الهدف بالاختبار ولكل خطوة هدفها وأسلوبها وحجم عينتها ومنفذها
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

$perms = w14_perms($conn, 'Audit/iaf_audit_programs.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'iaf_program',
                 array('orderBy' => 'program_no, step_no', 'limit' => 500));

$page_title = 'إيكوبيشن | برامج المراجعة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'برامج المراجعة'; $header_icon = 'fa fa-diagram-project'; $header_actions = array();
    $header_back = array('href' => 'iaf_engagements.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مهام المراجعة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد خطوات البرامج</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "approved") ?></div><div class="ems-stat-label">خطوات معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "completed") ?></div><div class="ems-stat-label">خطوات منجزة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "engagement_no") ?></div><div class="ems-stat-label">المهام المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا برامج مسجلة', 'البرنامج خطوات بأهدافها لا قائمة مهام'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_iaf_audit_programs')); ?>
    <table id="emsList_iaf_audit_programs" class="data-table">
        <thead><tr><th>رقم البرنامج</th><th>الخطوة</th><th>المهمة</th><th>الهدف</th><th>أسلوب الاختبار</th><th>المجتمع</th><th>حجم العينة</th><th>منهجية السحب</th><th>المنفذ</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["program_no"]) ?></td>
                    <td><?= (int) $r["step_no"] ?></td>
                    <td><?= ems_w14_txt($r["engagement_no"]) ?></td>
                    <td><?= ems_w14_txt($r["objective_ar"]) ?></td>
                    <td><?= ems_w14_state((string) $r["test_method"]) ?></td>
                    <td><?= ems_w14_txt($r["population_ar"]) ?></td>
                    <td><?= (int) $r["sample_size"] ?></td>
                    <td><?= ems_w14_txt($r["sampling_basis"]) ?></td>
                    <td><?= (int) $r["performer_id"] ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

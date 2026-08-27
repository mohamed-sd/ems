<?php
/**
 * Governance/committees.php — اللجان وحوكمة الاجتماعات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * اللجان النافذة بتشكيلها وصلاحياتها ودورية انعقادها
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

$perms = w14_perms($conn, 'Governance/committees.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_committee',
                 array('orderBy' => 'committee_code', 'limit' => 300));

$page_title = 'إيكوبيشن | اللجان وحوكمة الاجتماعات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'اللجان وحوكمة الاجتماعات'; $header_icon = 'fa fa-users-rectangle'; $header_actions = array();
    $header_back = array('href' => 'doc_types.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل أنواع المستندات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد اللجان</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "active") ?></div><div class="ems-stat-label">لجان نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "dissolved") ?></div><div class="ems-stat-label">لجان منحلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "charter_ref") ?></div><div class="ems-stat-label">لجان لها ميثاق</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا لجان مسجلة', 'اللجنة تشكيل بميثاقه لا اسم في محضر'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز اللجنة</th><th>الاسم</th><th>الاختصاص</th><th>الميثاق</th><th>رئيس اللجنة</th><th>عدد الأعضاء</th><th>دورية الانعقاد</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["committee_code"]) ?></td>
                    <td><?= ems_w14_txt($r["name_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["mandate_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["charter_ref"]) ?></td>
                    <td><?= (int) $r["chair_person"] ?></td>
                    <td><?= (int) $r["member_count"] ?></td>
                    <td><?= ems_w14_state((string) $r["meeting_cycle"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

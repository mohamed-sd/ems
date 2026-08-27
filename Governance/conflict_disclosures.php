<?php
/**
 * Governance/conflict_disclosures.php — تضارب المصالح (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الإفصاح واجب والقرار للحوكمة ولا يشارك صاحب الإفصاح في قرار محل التضارب
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

$perms = w14_perms($conn, 'Governance/conflict_disclosures.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_conflict_disclosure',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | تضارب المصالح';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تضارب المصالح'; $header_icon = 'fa fa-user-shield'; $header_actions = array();
    $header_back = array('href' => 'auth_profiles.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الأدوار وقوالب صلاحياتها');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإفصاحات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "disclosed") ?></div><div class="ems-stat-label">إفصاحات قيد التقييم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "recused") ?></div><div class="ems-stat-label">حالات تجنيب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "rejected") ?></div><div class="ems-stat-label">حالات مرفوضة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إفصاحات مسجلة', 'الإفصاح سطر بقراره لا استمارة تحفظ'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_conflict_disclosures')); ?>
    <table id="emsList_conflict_disclosures" class="data-table">
        <thead><tr><th>رقم الإفصاح</th><th>صاحب الإفصاح</th><th>طبيعة التضارب</th><th>الطرف المقابل</th><th>المقيم</th><th>القرار</th><th>التجنيب عن</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["disclosure_no"]) ?></td>
                    <td><?= (int) $r["person_id"] ?></td>
                    <td><?= ems_w14_txt($r["nature_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["counterparty_ar"]) ?></td>
                    <td><?= (int) $r["assessed_by"] ?></td>
                    <td><?= ems_w14_state((string) $r["decision"]) ?></td>
                    <td><?= ems_w14_txt($r["recused_from"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

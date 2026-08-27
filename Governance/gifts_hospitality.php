<?php
/**
 * Governance/gifts_hospitality.php — الهدايا والضيافة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الإفصاح فوق الحد المضبوط إلزامي والقبول أو الرد بقرار وفق السياسة
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

$perms = w14_perms($conn, 'Governance/gifts_hospitality.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_gift_disclosure',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | الهدايا والضيافة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الهدايا والضيافة'; $header_icon = 'fa fa-gift'; $header_actions = array();
    $header_back = array('href' => 'conflict_disclosures.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تضارب المصالح');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإفصاحات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "accepted") ?></div><div class="ems-stat-label">هدايا مقبولة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "returned") ?></div><div class="ems-stat-label">هدايا مردودة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "person_id") ?></div><div class="ems-stat-label">الأشخاص المفصحون</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إفصاحات هدايا', 'الإفصاح سطر بقراره لا خانة تعليم'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_gifts_hospitality')); ?>
    <table id="emsList_gifts_hospitality" class="data-table">
        <thead><tr><th>رقم الإفصاح</th><th>المفصح</th><th>النوع</th><th>الجهة المانحة</th><th>القيمة التقديرية</th><th>العملة</th><th>القرار</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["gift_no"]) ?></td>
                    <td><?= (int) $r["person_id"] ?></td>
                    <td><?= ems_w14_state((string) $r["gift_kind"]) ?></td>
                    <td><?= ems_w14_txt($r["giver_ar"]) ?></td>
                    <td><?= ems_w14_num($r["est_value"]) ?></td>
                    <td><?= ems_w14_txt($r["currency"]) ?></td>
                    <td><?= ems_w14_state((string) $r["decision"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

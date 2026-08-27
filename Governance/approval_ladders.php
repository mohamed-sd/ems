<?php
/**
 * Governance/approval_ladders.php — سلاليم الاعتماد (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * السلم يعرف هنا بمستوياته وشروط انتقاله ويقرأ في محرك الاعتماد
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

$perms = w14_perms($conn, 'Governance/approval_ladders.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_ladders',
                 array('orderBy' => 'ladder_code', 'limit' => 500));

$page_title = 'إيكوبيشن | سلاليم الاعتماد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سلاليم الاعتماد'; $header_icon = 'fa fa-stairs'; $header_actions = array();
    $header_back = array('href' => 'authority_caps.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سقوف الصلاحية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد السلاليم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "is_active", "1") ?></div><div class="ems-stat-label">سلاليم نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "cap_state", "unresolved") ?></div><div class="ems-stat-label">سلاليم بسقف غير محسوم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "entity_type") ?></div><div class="ems-stat-label">الكيانات المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سلاليم معرفة', 'السلم تعريف بمستوياته لا رقم في شاشة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز السلم</th><th>الاسم</th><th>الكيان</th><th>الإجراء</th><th>نوع السقف</th><th>قيمة السقف</th><th>حالة السقف</th><th>مرجع الوثيقة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["ladder_code"]) ?></td>
                    <td><?= ems_w14_txt($r["name_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["entity_type"]) ?></td>
                    <td><?= ems_w14_txt($r["action"]) ?></td>
                    <td><?= ems_w14_state((string) $r["cap_kind"]) ?></td>
                    <td><?= ems_w14_num($r["cap_amount"]) ?></td>
                    <td><?= ems_w14_state((string) $r["cap_state"]) ?></td>
                    <td><?= ems_w14_txt($r["doc_ref"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

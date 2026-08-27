<?php
/**
 * Risk/risk_closure.php — سجل الإغلاق والأدلة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * لا يغلق الخطر إلا بإثبات ومن اقترح الإغلاق لا يعتمده
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

$perms = w14_perms($conn, 'Risk/risk_closure.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'rsk_closure',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | سجل الإغلاق والأدلة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الإغلاق والأدلة'; $header_icon = 'fa fa-circle-check'; $header_actions = array();
    $header_back = array('href' => 'risk_acceptance.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'قبول المخاطر');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإغلاقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "approved") ?></div><div class="ems-stat-label">إغلاقات معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "reopened") ?></div><div class="ems-stat-label">إغلاقات أعيد فتحها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "evidence_ref") ?></div><div class="ems-stat-label">إغلاقات لها دليل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إغلاقات مسجلة', 'الإغلاق إثبات بدليله لا قرار صامت'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الإغلاق</th><th>الخطر</th><th>أساس الإغلاق</th><th>إعادة التقييم</th><th>الدليل</th><th>المقترح</th><th>المعتمد</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["closure_no"]) ?></td>
                    <td><?= ems_w14_txt($r["risk_code"]) ?></td>
                    <td><?= ems_w14_state((string) $r["closure_basis"]) ?></td>
                    <td><?= ems_w14_txt($r["reassessment_ref"]) ?></td>
                    <td><?= ems_w14_txt($r["evidence_ref"]) ?></td>
                    <td><?= (int) $r["proposed_by"] ?></td>
                    <td><?= (int) $r["approved_by"] ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

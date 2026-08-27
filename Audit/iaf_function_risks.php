<?php
/**
 * Audit/iaf_function_risks.php — مخاطر وظيفة المراجعة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * مخاطر الوظيفة نفسها ترفع لخط الرفع بالميثاق لا للإدارة التنفيذية
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

$perms = w14_perms($conn, 'Audit/iaf_function_risks.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'iaf_function_risk',
                 array('orderBy' => 'risk_no', 'limit' => 300));

$page_title = 'إيكوبيشن | مخاطر وظيفة المراجعة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مخاطر وظيفة المراجعة'; $header_icon = 'fa fa-user-secret'; $header_actions = array();
    $header_back = array('href' => 'iaf_quality.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تقييم الجودة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المخاطر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "risk_kind", "INDEPENDENCE_LOSS") ?></div><div class="ems-stat-label">مخاطر فقد الاستقلال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "treated") ?></div><div class="ems-stat-label">مخاطر معالجة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "reported_to") ?></div><div class="ems-stat-label">مخاطر مرفوعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مخاطر مسجلة', 'الخطر سطر بمعالجته لا ملاحظة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الخطر</th><th>النوع</th><th>الخطر</th><th>المستوى</th><th>المعالجة</th><th>خط الرفع</th><th>موعد المراجعة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["risk_no"]) ?></td>
                    <td><?= ems_w14_state((string) $r["risk_kind"]) ?></td>
                    <td><?= ems_w14_txt($r["title_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["level_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["treatment_ar"]) ?></td>
                    <td><?= ems_w14_state((string) $r["reported_to"]) ?></td>
                    <td><?= ems_w14_txt($r["review_due"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

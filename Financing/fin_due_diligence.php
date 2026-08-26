<?php
/**
 * Financing/fin_due_diligence.php — وثائق التأهيل والعناية الواجبة (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * وثيقة تاهيل وعناية واحدة — ولا تعاقد مع ممول بلا وثيقة محققة سارية.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-03)
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/w12_view.php';

$ctx = w12_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w12_perms($conn, 'Financing/fin_due_diligence.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_financier_document',
                 array('orderBy' => 'entity_id, expires_on', 'limit' => 400));

$page_title = 'إيكوبيشن | وثائق التأهيل والعناية الواجبة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'وثائق التأهيل والعناية الواجبة'; $header_icon = 'fa fa-file-circle-check'; $header_actions = array();
    $header_back = array('href' => 'financiers_registry.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الممولين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الوثائق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "verified") ?></div><div class="ems-stat-label">وثائق محققة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "expired") ?></div><div class="ems-stat-label">وثائق منتهية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "entity_id") ?></div><div class="ems-stat-label">ممولون لهم ملف</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وثائق تاهيل مسجلة', 'التاهيل شرط سابق لفتح باب العروض'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الممول</th><th>نوع الوثيقة</th><th>مرجع الوثيقة</th><th>تاريخ الاصدار</th><th>تاريخ الانتهاء</th><th>المحقق</th><th>تاريخ التحقق</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["doc_kind"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["doc_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["issued_on"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["expires_on"]) ?></td>
                    <td><?= (int) $r["verified_by"] ?></td>
                    <td><?= htmlspecialchars((string) $r["verified_at"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

<?php
/**
 * Financing/fin_financier_contacts.php — جهات اتصال الممولين والمفوضين (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * جهة اتصال او مفوض واحد عبر الزمن — والتفويض بمستنده لا بالقول.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-02)
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

$perms = w12_perms($conn, 'Financing/fin_financier_contacts.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_financier_contact',
                 array('orderBy' => 'entity_id, valid_from DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | جهات اتصال الممولين والمفوضين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'جهات اتصال الممولين والمفوضين'; $header_icon = 'fa fa-address-book'; $header_actions = array();
    $header_back = array('href' => 'financiers_registry.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الممولين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد جهات الاتصال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "is_authorized", "1") ?></div><div class="ems-stat-label">المفوضون بالتوقيع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "entity_id") ?></div><div class="ems-stat-label">ممولون لهم جهات اتصال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "active") ?></div><div class="ems-stat-label">سارية</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا جهات اتصال مسجلة', 'الممول كيان قانوني وجهات اتصاله ابناء له'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الممول</th><th>الاسم</th><th>الصفة</th><th>مفوض بالتوقيع</th><th>مستند التفويض</th><th>الهاتف</th><th>البريد</th><th>ساري من</th><th>ساري حتى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["person_name"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["role_ar"]) ?></td>
                    <td><?= ((int) $r["is_authorized"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= htmlspecialchars((string) $r["mandate_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["phone"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["email"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["valid_from"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["valid_to"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

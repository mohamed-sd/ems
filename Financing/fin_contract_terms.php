<?php
/**
 * Financing/fin_contract_terms.php — بنود وشروط التمويل (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * بند تعاقدي واحد بمرجع بنده في المستند — سطر لا عمود مخترع.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-08)
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

$perms = w12_perms($conn, 'Financing/fin_contract_terms.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_contract_term',
                 array('orderBy' => 'contract_id, term_key', 'limit' => 800));

$page_title = 'إيكوبيشن | بنود وشروط التمويل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'بنود وشروط التمويل'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'fin_contracts.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل عقود التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد البنود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "contract_id") ?></div><div class="ems-stat-label">عقود لها بنود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "is_binding", "1") ?></div><div class="ems-stat-label">بنود ملزمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "term_key") ?></div><div class="ems-stat-label">انواع البنود</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بنود مسجلة', 'كل بند سطر بمرجعه في مستند العقد'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>العقد</th><th>البند</th><th>القيمة</th><th>رقم البند في المستند</th><th>ملزم</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["contract_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["term_key"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["term_value"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["clause_ref"]) ?></td>
                    <td><?= ((int) $r["is_binding"] === 1 ? "نعم" : "لا") ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

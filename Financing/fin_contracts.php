<?php
/**
 * Financing/fin_contracts.php — سجل عقود التمويل (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * عقد تمويل واحد بمستنده — ومن اعده لا يوقعه.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-07)
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

$perms = w12_perms($conn, 'Financing/fin_contracts.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_finance_contract',
                 array('orderBy' => 'id DESC', 'limit' => 400));

$page_title = 'إيكوبيشن | سجل عقود التمويل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل عقود التمويل'; $header_icon = 'fa fa-file-contract'; $header_actions = array();
    $header_back = array('href' => 'fin_precontract_review.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'مراجعة ما قبل التعاقد');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد العقود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "active") ?></div><div class="ems-stat-label">عقود سارية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "closed") ?></div><div class="ems-stat-label">عقود مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "principal")) ?></div><div class="ems-stat-label">اجمالي اصل التمويل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عقود تمويل مسجلة', 'العقد يفتح العملية ويولد جدول الاقساط'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>كود العقد</th><th>الممول</th><th>العملية</th><th>نموذج التمويل</th><th>اصل التمويل</th><th>العملة</th><th>تاريخ التوقيع</th><th>البداية</th><th>النهاية</th><th>عدد الفترات التعاقدية</th><th>مستند العقد</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["contract_code"]) ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["model_code"]) ?></td>
                    <td><?= ems_w12_num($r["principal"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["signed_on"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["start_on"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["end_on"]) ?></td>
                    <td><?= (int) $r["periods_total"] ?></td>
                    <td><?= htmlspecialchars((string) $r["contract_doc_ref"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

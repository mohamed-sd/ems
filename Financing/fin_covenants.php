<?php
/**
 * Financing/fin_covenants.php — مصفوفة الالتزامات التمويلية (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * التزام واحد بقاعدة قياسه ودوريته — وعتبته من السجل لا رقما في شيفرة.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-09)
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

$perms = w12_perms($conn, 'Financing/fin_covenants.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_contract_covenant',
                 array('orderBy' => 'contract_id, covenant_key', 'limit' => 600));

$page_title = 'إيكوبيشن | مصفوفة الالتزامات التمويلية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مصفوفة الالتزامات التمويلية'; $header_icon = 'fa fa-scale-balanced'; $header_actions = array();
    $header_back = array('href' => 'fin_contracts.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل عقود التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الالتزامات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "breached") ?></div><div class="ems-stat-label">التزامات مخلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "waived") ?></div><div class="ems-stat-label">تنازلات موثقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "contract_id") ?></div><div class="ems-stat-label">عقود لها التزامات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا التزامات مسجلة', 'الالتزام يقاس بقاعدته ويوثق اخلاله او التنازل عنه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_covenants')); ?>
    <table id="emsList_fin_covenants" class="data-table">
        <thead><tr><th>العقد</th><th>الالتزام</th><th>الوصف</th><th>الطرف الملتزم</th><th>قاعدة القياس</th><th>العتبة المرجعية</th><th>الدورية</th><th>مستند الاثبات</th><th>مرجع الاخلال</th><th>مستند التنازل</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["contract_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["covenant_key"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["covenant_ar"]) ?></td>
                    <td><?= ems_w12_state((string) $r["obligation_on"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["measure_rule"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["threshold_key"]) ?></td>
                    <td><?= ems_w12_state((string) $r["frequency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["evidence_doc"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["breach_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["waiver_ref"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

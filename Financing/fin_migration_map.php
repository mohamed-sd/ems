<?php
/**
 * Financing/fin_migration_map.php — خريطة الترحيل ومصفوفة التسوية (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * سطر تاريخي مجمع واحد بحجيته ومرجع صفه — طبقة مستقلة لا تدخل نموذج أمر الدفع ولا تخصص على قسط.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-27)
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

$perms = w12_perms($conn, 'Financing/fin_migration_map.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_legacy_payment_aggregate',
                 array('orderBy' => 'op_id, period_label', 'limit' => 600));

$page_title = 'إيكوبيشن | خريطة الترحيل ومصفوفة التسوية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'خريطة الترحيل ومصفوفة التسوية'; $header_icon = 'fa fa-right-left'; $header_actions = array();
    $header_back = array('href' => 'fin_payment_orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الدفع والسداد الفعلي');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد السطور التاريخية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "evidence_grade", "documented") ?></div><div class="ems-stat-label">بحجية مستندية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "evidence_grade", "aggregate") ?></div><div class="ems-stat-label">بحجية تجميعية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_sum($rows, "ledger_rows") ?></div><div class="ems-stat-label">صفوف دفتر مجمعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سطور تاريخية مرحلة', 'الطبقة التاريخية توسم ولا تخلط بنموذج المستقبل'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>العملية</th><th>الطبقة</th><th>الفترة كما وردت</th><th>المدفوع مجمعا</th><th>عدد صفوف الدفتر</th><th>العملة</th><th>الحجية</th><th>مرجع الصف الأصلي</th><th>قابل للتخصيص</th><th>حالة البيانات</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["layer"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["period_label"]) ?></td>
                    <td><?= ems_w12_num($r["paid_aggregate"]) ?></td>
                    <td><?= (int) $r["ledger_rows"] ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= ems_w12_state((string) $r["evidence_grade"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["source_row_ref"]) ?></td>
                    <td><?= ((int) $r["allocatable"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= ems_w12_state((string) $r["data_state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

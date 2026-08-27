<?php
/**
 * Financing/fin_precontract_review.php — مراجعة ما قبل التعاقد (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * مراجعة واحدة براي القانوني والمالية والمخاطر — والحجب بسبب مكتوب.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-06)
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

$perms = w12_perms($conn, 'Financing/fin_precontract_review.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_precontract_review',
                 array('orderBy' => 'id DESC', 'limit' => 300));

$page_title = 'إيكوبيشن | مراجعة ما قبل التعاقد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مراجعة ما قبل التعاقد'; $header_icon = 'fa fa-clipboard-check'; $header_actions = array();
    $header_back = array('href' => 'fin_offers.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'عروض التمويل');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المراجعات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "verdict", "cleared") ?></div><div class="ems-stat-label">مجازة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "verdict", "blocked") ?></div><div class="ems-stat-label">محجوبة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "verdict", "pending") ?></div><div class="ems-stat-label">قيد الدراسة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مراجعات مسجلة', 'لا توقيع عقد قبل اجازة المراجعة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_precontract_review')); ?>
    <table id="emsList_fin_precontract_review" class="data-table">
        <thead><tr><th>كود المراجعة</th><th>العرض</th><th>راي القانوني</th><th>راي المالية</th><th>راي المخاطر</th><th>الحكم</th><th>سبب الحجب</th><th>المقرر</th><th>تاريخ القرار</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["review_code"]) ?></td>
                    <td><?= (int) $r["offer_id"] ?></td>
                    <td><?= (int) $r["legal_by"] ?></td>
                    <td><?= (int) $r["finance_by"] ?></td>
                    <td><?= (int) $r["risk_by"] ?></td>
                    <td><?= ems_w12_state((string) $r["verdict"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["blocking_reason"]) ?></td>
                    <td><?= (int) $r["decided_by"] ?></td>
                    <td><?= htmlspecialchars((string) $r["decided_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

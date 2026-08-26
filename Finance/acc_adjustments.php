<?php
/**
 * Finance/acc_adjustments.php — الاستحقاقات والمقدمات والمخصصات (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * تسويات نهاية الفترة (ACC-17) — استحقاق لم يفوتر ومصروف مقدم يستهلك ومخصص بمستنده.
 *
 * ◆ **الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03`): القراءةُ
 *   تمرُّ ببوّابةِ المستأجرِ التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر،
 *   ولا رقمَ يخلط كيانَين بلا وسمٍ مسجَّل.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ الدورةِ
 *   بحارسِها ورمزِ ردِّها، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/w11_view.php';

$ctx = fin_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/acc_adjustments.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('acc_period_adjustment', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_adjustments.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | الاستحقاقات والمقدمات والمخصصات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الاستحقاقات والمقدمات والمخصصات'; $header_icon = 'fa fa-scale-balanced'; $header_actions = array();
    $header_back = array('href' => 'journal_form_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القيود اليومية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">قيود التسوية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "adj_kind", "accrual") ?></div><div class="ems-stat-label">استحقاقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "adj_kind", "prepaid") ?></div><div class="ems-stat-label">مقدمات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "adj_kind", "provision") ?></div><div class="ems-stat-label">مخصصات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا قيود تسوية', 'كل تسوية بمستند اساسها وبسببها المكتوب'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>النوع</th><th>الفترة</th><th>الحساب</th><th>المبلغ</th><th>العملة</th><th>مستند الاساس</th><th>يعكس في التالية</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["adj_no"]) ?></td>
                    <td><?= ems_w11_adj_kind((string) $r["adj_kind"]) ?></td>
                    <td><?= (int) $r["period_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["account_code"]) ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["basis_doc"]) ?></td>
                    <td><?= ((int) $r["reverse_next"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

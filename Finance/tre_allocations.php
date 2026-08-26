<?php
/**
 * Finance/tre_allocations.php — تخصيص التحصيل على الفواتير (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * تخصيص التحصيل (TRS-07) — الدفعة الواحدة قد تغطي عدة فواتير وكل تخصيص سطر.
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

$perms = fin_page_perms($conn, 'Finance/tre_allocations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('fin_collection_allocations', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_allocations.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | تخصيص التحصيل على الفواتير';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تخصيص التحصيل على الفواتير'; $header_icon = 'fa fa-arrows-split-up-and-left'; $header_actions = array();
    $header_back = array('href' => 'tre_pay_batch.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الدفع');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">اسطر التخصيص</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "payment_id") ?></div><div class="ems-stat-label">سندات مخصصة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "receivable_id") ?></div><div class="ems-stat-label">فواتير مغطاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "amount"), 2) ?></div><div class="ems-stat-label">مجموع المخصص</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اسطر تخصيص', 'المتبقي على كل فاتورة يشتق من التخصيصات لا يكتب بيد'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>سند القبض</th><th>الذمة</th><th>نوع الهدف</th><th>المبلغ</th><th>عملة السند</th><th>المبلغ بالاساس</th><th>اساس التخصيص</th><th>الوقت</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["payment_id"] ?></td>
                    <td><?= (int) $r["receivable_id"] ?></td>
                    <td><?= ems_w11_state((string) $r["target_kind"]) ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["pay_currency"]) ?></td>
                    <td><?= number_format((float) $r["base_amount"], 2) ?></td>
                    <td><?= ems_w11_state((string) $r["basis"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["created_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

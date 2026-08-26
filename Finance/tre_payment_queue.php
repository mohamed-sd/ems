<?php
/**
 * Finance/tre_payment_queue.php — صف الدفع المعتمد (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * صف الدفع المعتمد (TRS-08) — اسقاط فوق طلبات الادارات لا سجل مواز. الطلب ينشا عند مالك الاستحقاق.
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

$perms = fin_page_perms($conn, 'Finance/tre_payment_queue.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('fin_requests', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_payment_queue.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | صف الدفع المعتمد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'صف الدفع المعتمد'; $header_icon = 'fa fa-list-ol'; $header_actions = array();
    $header_back = array('href' => 'tre_pay_batch.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الدفع');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">طلبات الصف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "company_id") ?></div><div class="ems-stat-label">جهات طالبة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "amount"), 2) ?></div><div class="ems-stat-label">مجموع المطلوب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "currency") ?></div><div class="ems-stat-label">عملات مطلوبة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات في صف الدفع', 'الطلب لا ينشا في الخزينة بل يصل اليها اسقاطا'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>النوع</th><th>المبلغ</th><th>العملة</th><th>الحالة</th><th>الوقت</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["request_no"]) ?></td>
                    <td><?= ems_w11_state((string) $r["request_type"]) ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["created_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

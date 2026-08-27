<?php
/**
 * Finance/acc_reopen_governance.php — حوكمة إعادة فتح الفترات (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * حوكمة اعادة الفتح (ACC-25) — استثناء محكوم بمبرر وموافقة ونطاق زمني ووحدات محددة.
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

$perms = fin_page_perms($conn, 'Finance/acc_reopen_governance.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('acc_period_reopen_request', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_reopen_governance.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | حوكمة إعادة فتح الفترات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'حوكمة إعادة فتح الفترات'; $header_icon = 'fa fa-unlock-keyhole'; $header_actions = array();
    $header_back = array('href' => 'periods_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الفترات المحاسبية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الطلبات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "pending") ?></div><div class="ems-stat-label">قيد الدراسة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "applied") ?></div><div class="ems-stat-label">معتمدة ومطبقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "reclosed") ?></div><div class="ems-stat-label">اعيد اقفالها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات اعادة فتح', 'اعادة الفتح استثناء محكوم لا فعل عادي'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_reopen_governance')); ?>
    <table id="emsList_acc_reopen_governance" class="data-table">
        <thead><tr><th>الرقم</th><th>الفترة</th><th>المبرر</th><th>من تاريخ</th><th>الى تاريخ</th><th>الوحدات</th><th>قاعدة الصلاحية</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["request_no"]) ?></td>
                    <td><?= (int) $r["period_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["justification"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["scope_from"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["scope_to"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["scope_units"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["authority_rule_id"]) ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

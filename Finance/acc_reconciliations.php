<?php
/**
 * Finance/acc_reconciliations.php — مطابقات الحسابات (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * مطابقات الحسابات (ACC-20) — كل حساب رقابي يطابق مع مصدره التفصيلي ولا فرق مدفون في حقل.
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

$perms = fin_page_perms($conn, 'Finance/acc_reconciliations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('acc_account_recon', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_reconciliations.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | مطابقات الحسابات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مطابقات الحسابات'; $header_icon = 'fa fa-code-compare'; $header_actions = array();
    $header_back = array('href' => 'journal_form_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القيود اليومية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">جلسات المطابقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "closed") ?></div><div class="ems-stat-label">جلسات مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_sum($rows, "open_diffs") ?></div><div class="ems-stat-label">فروق مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "account_code") ?></div><div class="ems-stat-label">حسابات مطابقة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا جلسات مطابقة', 'الجلسة لا تقفل وفيها فرق مفتوح'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_reconciliations')); ?>
    <table id="emsList_acc_reconciliations" class="data-table">
        <thead><tr><th>الفترة</th><th>الحساب الرقابي</th><th>المصدر التفصيلي</th><th>رصيد الدفتر</th><th>رصيد المصدر</th><th>الفرق</th><th>فروق مفتوحة</th><th>الحالة</th><th>تاريخ الاقفال</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["period_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["account_code"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["control_source"]) ?></td>
                    <td><?= number_format((float) $r["gl_balance"], 2) ?></td>
                    <td><?= number_format((float) $r["source_balance"], 2) ?></td>
                    <td><?= number_format((float) $r["difference"], 2) ?></td>
                    <td><?= (int) $r["open_diffs"] ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["closed_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

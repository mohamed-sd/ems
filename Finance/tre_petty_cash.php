<?php
/**
 * Finance/tre_petty_cash.php — عهد النثرية وتسويتها (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * عهد النثرية (TRS-17) — العهدة بحد وسقف زمني ولا تجديد قبل تسوية السابقة بمستنداتها.
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

$perms = fin_page_perms($conn, 'Finance/tre_petty_cash.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_petty_custody', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_petty_cash.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | عهد النثرية وتسويتها';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'عهد النثرية وتسويتها'; $header_icon = 'fa fa-wallet'; $header_actions = array();
    $header_back = array('href' => 'payments_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'المدفوعات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">العهد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "open") ?></div><div class="ems-stat-label">عهد مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "settled") ?></div><div class="ems-stat-label">عهد مسواة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "spent_amount"), 2) ?></div><div class="ems-stat-label">مجموع المصروف</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عهد نثرية', 'لا تجديد قبل تسوية العهدة السابقة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>الامين</th><th>حد العهدة</th><th>العملة</th><th>المصروف</th><th>تاريخ الفتح</th><th>السقف الزمني</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["custody_no"]) ?></td>
                    <td><?= (int) $r["holder_id"] ?></td>
                    <td><?= number_format((float) $r["ceiling_amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= number_format((float) $r["spent_amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["opened_at"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["due_date"]) ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

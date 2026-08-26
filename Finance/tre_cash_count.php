<?php
/**
 * Finance/tre_cash_count.php — الجرد النقدي للخزائن (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * الجرد النقدي (TRS-18) — بلجنة لا بامين الصندوق وحده والفرق يعالج فورا بمساره.
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

$perms = fin_page_perms($conn, 'Finance/tre_cash_count.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_cash_count', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_cash_count.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | الجرد النقدي للخزائن';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الجرد النقدي للخزائن'; $header_icon = 'fa fa-magnifying-glass-dollar'; $header_actions = array();
    $header_back = array('href' => 'tre_vessels.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الحسابات والصناديق');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">جلسات الجرد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "approved") ?></div><div class="ems-stat-label">جلسات معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "count_kind", "surprise") ?></div><div class="ems-stat-label">جرد مفاجئ</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_diffs($rows) ?></div><div class="ems-stat-label">جلسات بفرق</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا جلسات جرد', 'الجرد بلجنة والفرق يعالج فورا لا يدفن'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>الصندوق</th><th>النوع</th><th>الرصيد الدفتري</th><th>الرصيد المعدود</th><th>الفرق</th><th>حجم اللجنة</th><th>معالجة الفرق</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["count_no"]) ?></td>
                    <td><?= (int) $r["box_id"] ?></td>
                    <td><?= ((string) $r["count_kind"] === "surprise" ? "مفاجئ" : "دوري") ?></td>
                    <td><?= number_format((float) $r["book_balance"], 2) ?></td>
                    <td><?= number_format((float) $r["counted_balance"], 2) ?></td>
                    <td><?= number_format((float) $r["difference"], 2) ?></td>
                    <td><?= (int) $r["committee_size"] ?></td>
                    <td><?= htmlspecialchars((string) $r["action_ref"]) ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

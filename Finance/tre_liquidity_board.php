<?php
/**
 * Finance/tre_liquidity_board.php — لوحة الخزينة والسيولة (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * لوحة الخزينة (TRS-01) — قراءة حية مشتقة من الارصدة والحركات وخطة السيولة.
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

$perms = fin_page_perms($conn, 'Finance/tre_liquidity_board.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_cash_move', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_liquidity_board.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | لوحة الخزينة والسيولة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة الخزينة والسيولة'; $header_icon = 'fa fa-gauge-high'; $header_actions = array();
    $header_back = array('href' => 'cash_forecast_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'خطة السيولة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">حركات مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_dir($rows, "in"), 2) ?></div><div class="ems-stat-label">وارد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_dir($rows, "out"), 2) ?></div><div class="ems-stat-label">صادر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_dir($rows, "in") - ems_w11_dir($rows, "out"), 2) ?></div><div class="ems-stat-label">صافي الحركة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا حركات نقد', 'اللوحة قراءة مشتقة لا سجل يحرر'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>الوعاء</th><th>الاتجاه</th><th>المبلغ</th><th>العملة</th><th>المرجع</th><th>فرق صرف</th><th>الوقت</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["move_no"]) ?></td>
                    <td><?= ems_w11_vessel((string) $r["vessel_kind"]) ?></td>
                    <td><?= ((string) $r["direction"] === "in" ? "وارد" : "صادر") ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["ref_kind"]) ?></td>
                    <td><?= ((int) $r["is_fx_diff"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= htmlspecialchars((string) $r["moved_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

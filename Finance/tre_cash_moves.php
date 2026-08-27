<?php
/**
 * Finance/tre_cash_moves.php — حركة الخزينة والصناديق (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * حركة الخزينة (TRS-10) — كل حركة نقد بسطر موثق بمرجعه وفرق الصرف حركة مستقلة لا تعديلا صامتا.
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

$perms = fin_page_perms($conn, 'Finance/tre_cash_moves.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_cash_move', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_cash_moves.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | حركة الخزينة والصناديق';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'حركة الخزينة والصناديق'; $header_icon = 'fa fa-right-left'; $header_actions = array();
    $header_back = array('href' => 'tre_liquidity_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الخزينة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الحركات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_dir($rows, "in"), 2) ?></div><div class="ems-stat-label">وارد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_dir($rows, "out"), 2) ?></div><div class="ems-stat-label">صادر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "is_fx_diff", 1) ?></div><div class="ems-stat-label">حركات فرق صرف</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا حركات نقد', 'الحركة بمرجعها والرصيد مشتق منها لا مكتوب بيد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tre_cash_moves')); ?>
    <table id="emsList_tre_cash_moves" class="data-table">
        <thead><tr><th>الرقم</th><th>الوعاء</th><th>معرف الوعاء</th><th>الاتجاه</th><th>المبلغ</th><th>العملة</th><th>المرجع</th><th>فرق صرف</th><th>البيان</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["move_no"]) ?></td>
                    <td><?= ems_w11_vessel((string) $r["vessel_kind"]) ?></td>
                    <td><?= (int) $r["vessel_id"] ?></td>
                    <td><?= ((string) $r["direction"] === "in" ? "وارد" : "صادر") ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["ref_kind"]) ?></td>
                    <td><?= ((int) $r["is_fx_diff"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= htmlspecialchars((string) $r["note"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

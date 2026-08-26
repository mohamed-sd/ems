<?php
/**
 * Finance/tre_instruments.php — سجل الأدوات المالية (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * سجل الادوات المالية (TRS-06) — كل شيك اداة مسجلة بدورتها من الاستلام الى التحصيل او الارتجاع.
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

$perms = fin_page_perms($conn, 'Finance/tre_instruments.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_instrument', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_instruments.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | سجل الأدوات المالية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الأدوات المالية'; $header_icon = 'fa fa-money-check'; $header_actions = array();
    $header_back = array('href' => 'tre_pay_batch.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الدفع');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الادوات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "deposited") ?></div><div class="ems-stat-label">مودعة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "collected") ?></div><div class="ems-stat-label">محصلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "bounced") ?></div><div class="ems-stat-label">مرتجعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا ادوات مسجلة', 'الاداة بدورتها لا بحقل حالة صامت'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>النوع</th><th>البنك</th><th>تاريخ الاستحقاق</th><th>المبلغ</th><th>العملة</th><th>الحالة</th><th>سبب الارتجاع</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["instrument_no"]) ?></td>
                    <td><?= ems_w11_instr((string) $r["kind"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["bank_name"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["due_date"]) ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["bounce_reason"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

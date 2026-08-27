<?php
/**
 * Finance/acc_closing_checklist.php — قائمة إقفال الفترة (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * قائمة اقفال الفترة (ACC-22) — لا اقفال قبل اكتمال البنود او توثيق استثناء كل ناقص بقرار.
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

$perms = fin_page_perms($conn, 'Finance/acc_closing_checklist.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('fin_closing_items', array('orderBy' => 'period_id DESC, id', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_closing_checklist.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | قائمة إقفال الفترة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'قائمة إقفال الفترة'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'periods_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الفترات المحاسبية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">بنود القائمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "item_state", "done") ?></div><div class="ems-stat-label">بنود مكتملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_blocking($rows) ?></div><div class="ems-stat-label">بنود تحجب الاقفال</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_excepted($rows) ?></div><div class="ems-stat-label">استثناءات موثقة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بنود في قائمة الاقفال', 'البند الناقص يحجب الاقفال ما لم يوثق استثناؤه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_closing_checklist')); ?>
    <table id="emsList_acc_closing_checklist" class="data-table">
        <thead><tr><th>الفترة</th><th>البند</th><th>الزامي</th><th>الحالة</th><th>يحجب الاقفال</th><th>سبب الاستثناء</th><th>تاريخ الاكتمال</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["period_id"] ?></td>
                    <td><?= ems_w11_step((string) $r["step"]) ?></td>
                    <td><?= ((int) $r["required"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= ems_w11_item_state((string) $r["item_state"]) ?></td>
                    <td><?= ((int) $r["blocks_close"] === 1 ? "نعم" : "لا") ?></td>
                    <td><?= htmlspecialchars((string) $r["exception_reason"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["done_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

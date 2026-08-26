<?php
/**
 * Tickets/tkt_reopen.php — إعادة الفتح (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * اعتراض المبلغ او تكرار المشكلة يعيد الفتح بسجل ويعود البلاغ لمسار معالجته
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `PeopleCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w13_view.php';

$ctx = w13_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w13_perms($conn, 'Tickets/tkt_reopen.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_reopen',
                 array('orderBy' => 'ticket_id DESC, seq_no', 'limit' => 500));

$page_title = 'إيكوبيشن | إعادة الفتح';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إعادة الفتح'; $header_icon = 'fa fa-rotate-left'; $header_actions = array();
    $header_back = array('href' => 'tkt_verification.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التحقق والإغلاق');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع اعادة الفتح</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "reopen_reason", "REPORTER_OBJECTION") ?></div><div class="ems-stat-label">باعتراض المبلغ</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "reopen_reason", "RECURRENCE") ?></div><div class="ems-stat-label">بتكرار المشكلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "back_to_dept") ?></div><div class="ems-stat-label">الادارات المعادة اليها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع اعادة فتح', 'اعادة الفتح واقعة بسببها لا حالة تنقلب صامتة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>البلاغ</th><th>التسلسل</th><th>الدورة السابقة</th><th>سبب اعادة الفتح</th><th>التفصيل</th><th>طالب اعادة الفتح</th><th>العودة الى ادارة</th><th>التاريخ والوقت</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ticket_id"] ?></td>
                    <td><?= (int) $r["seq_no"] ?></td>
                    <td><?= (int) $r["prior_cycle_no"] ?></td>
                    <td><?= ems_w13_state((string) $r["reopen_reason"]) ?></td>
                    <td><?= ems_w13_txt($r["note"]) ?></td>
                    <td><?= (int) $r["raised_by"] ?></td>
                    <td><?= ems_w13_txt($r["back_to_dept"]) ?></td>
                    <td><?= ems_w13_txt($r["raised_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

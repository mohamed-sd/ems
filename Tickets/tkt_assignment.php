<?php
/**
 * Tickets/tkt_assignment.php — سجل الإسناد (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * كل تغيير مكلف سطر بسببه ولا مكلف بلا وقت استلام
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

$perms = w13_perms($conn, 'Tickets/tkt_assignment.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_assignment_history',
                 array('orderBy' => 'ticket_id DESC, seq_no', 'limit' => 800));

$page_title = 'إيكوبيشن | سجل الإسناد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الإسناد'; $header_icon = 'fa fa-user-check'; $header_actions = array();
    $header_back = array('href' => 'tkt_routing.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل التوجيه');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع الاسناد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "received_at") ?></div><div class="ems-stat-label">اسنادات مستلمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_empty($rows, "received_at") ?></div><div class="ems-stat-label">اسنادات بلا وقت استلام</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "to_dept") ?></div><div class="ems-stat-label">ادارات المكلفين</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع اسناد', 'الاسناد واقعة بسببها ووقت استلامها'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_assignment')); ?>
    <table id="emsList_tkt_assignment" class="data-table">
        <thead><tr><th>البلاغ</th><th>التسلسل</th><th>المكلف السابق</th><th>المكلف الجديد</th><th>ادارة المكلف</th><th>سبب التغيير</th><th>المسند</th><th>تاريخ الاسناد</th><th>وقت الاستلام</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ticket_id"] ?></td>
                    <td><?= (int) $r["seq_no"] ?></td>
                    <td><?= (int) $r["from_person_id"] ?></td>
                    <td><?= (int) $r["to_person_id"] ?></td>
                    <td><?= ems_w13_txt($r["to_dept"]) ?></td>
                    <td><?= ems_w13_txt($r["reason"]) ?></td>
                    <td><?= (int) $r["assigned_by"] ?></td>
                    <td><?= ems_w13_txt($r["assigned_at"]) ?></td>
                    <td><?= ems_w13_txt($r["received_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

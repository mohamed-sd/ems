<?php
/**
 * Tickets/tkt_resolution_actions.php — إجراءات المعالجة (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * كل اجراء سطر بمرجعه في شاشة الادارة المعالجة والمركز لا ينفذ الحل
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

$perms = w13_perms($conn, 'Tickets/tkt_resolution_actions.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_resolution_action',
                 array('orderBy' => 'ticket_id DESC, seq_no', 'limit' => 800));

$page_title = 'إيكوبيشن | إجراءات المعالجة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إجراءات المعالجة'; $header_icon = 'fa fa-screwdriver-wrench'; $header_actions = array();
    $header_back = array('href' => 'tkt_assignment.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الإسناد');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاجراءات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "executor_dept") ?></div><div class="ems-stat-label">الادارات المنفذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "ticket_id") ?></div><div class="ems-stat-label">بلاغات لها اجراءات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_filled($rows, "dept_doc_ref") ?></div><div class="ems-stat-label">اجراءات لها مستند</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اجراءات معالجة', 'الاجراء سطر بمرجعه في شاشة ادارته'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_resolution_actions')); ?>
    <table id="emsList_tkt_resolution_actions" class="data-table">
        <thead><tr><th>البلاغ</th><th>التسلسل</th><th>الادارة المنفذة</th><th>المنفذ</th><th>الاجراء</th><th>مرجع شاشة الادارة</th><th>مستند الاجراء</th><th>تاريخ الاجراء</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ticket_id"] ?></td>
                    <td><?= (int) $r["seq_no"] ?></td>
                    <td><?= ems_w13_txt($r["executor_dept"]) ?></td>
                    <td><?= (int) $r["executor_person_id"] ?></td>
                    <td><?= ems_w13_txt($r["action_ar"]) ?></td>
                    <td><?= ems_w13_txt($r["dept_screen_ref"]) ?></td>
                    <td><?= ems_w13_txt($r["dept_doc_ref"]) ?></td>
                    <td><?= ems_w13_txt($r["acted_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

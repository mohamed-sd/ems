<?php
/**
 * Tickets/tkt_communications.php — سجل التواصل (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * دور المركز تواصل موثق وكل تواصل سطر بقناته ووقته
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

$perms = w13_perms($conn, 'Tickets/tkt_communications.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'ticket_communications',
                 array('orderBy' => 'tk_id DESC, cm_id', 'limit' => 800));

$page_title = 'إيكوبيشن | سجل التواصل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل التواصل'; $header_icon = 'fa fa-comments'; $header_actions = array();
    $header_back = array('href' => 'tkt_parties.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أطراف البلاغ');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد وقائع التواصل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "tk_id") ?></div><div class="ems-stat-label">بلاغات لها تواصل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "channel") ?></div><div class="ems-stat-label">القنوات المستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "person_id") ?></div><div class="ems-stat-label">الاشخاص المتواصلون</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع تواصل', 'التواصل سطر بقناته لا مكالمة بلا اثر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_communications')); ?>
    <table id="emsList_tkt_communications" class="data-table">
        <thead><tr><th>البلاغ</th><th>الشخص</th><th>القناة</th><th>النص</th><th>التاريخ والوقت</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["tk_id"] ?></td>
                    <td><?= (int) $r["person_id"] ?></td>
                    <td><?= ems_w13_state((string) $r["channel"]) ?></td>
                    <td><?= ems_w13_txt($r["note"]) ?></td>
                    <td><?= ems_w13_txt($r["at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

<?php
/**
 * Tickets/tkt_parties.php — أطراف البلاغ (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * اربعة اطراف متمايزة من بلغ وعم بلغ ومن يملك التذكرة ومن يملك الحل
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

$perms = w13_perms($conn, 'Tickets/tkt_parties.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_party',
                 array('orderBy' => 'ticket_id DESC, party_role', 'limit' => 800));

$page_title = 'إيكوبيشن | أطراف البلاغ';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'أطراف البلاغ'; $header_icon = 'fa fa-user-group'; $header_actions = array();
    $header_back = array('href' => 'ticket_dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة مركز البلاغات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد صفوف الاطراف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "ticket_id") ?></div><div class="ems-stat-label">البلاغات ذات الاطراف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "party_role") ?></div><div class="ems-stat-label">الادوار المستعملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "party_role", "RESOLUTION_OWNER") ?></div><div class="ems-stat-label">بلاغات لها مالك حل</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اطراف مسجلة', 'الطرف صف بمفتاح فاعله لا اسم في راس البلاغ'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_parties')); ?>
    <table id="emsList_tkt_parties" class="data-table">
        <thead><tr><th>البلاغ</th><th>الدور</th><th>صنف الفاعل</th><th>مفتاح الفاعل</th><th>ادارة الفاعل</th><th>نوع محل البلاغ</th><th>مسجل الطرف</th><th>تاريخ التسجيل</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["ticket_id"] ?></td>
                    <td><?= ems_w13_state((string) $r["party_role"]) ?></td>
                    <td><?= ems_w13_state((string) $r["actor_kind"]) ?></td>
                    <td><?= (int) $r["actor_id"] ?></td>
                    <td><?= ems_w13_txt($r["actor_dept"]) ?></td>
                    <td><?= ems_w13_txt($r["subject_type_code"]) ?></td>
                    <td><?= (int) $r["recorded_by"] ?></td>
                    <td><?= ems_w13_txt($r["recorded_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

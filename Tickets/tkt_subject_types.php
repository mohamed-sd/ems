<?php
/**
 * Tickets/tkt_subject_types.php — كتالوج أنواع محل البلاغ (RPR-W13)
 * ───────────────────────────────────────────────────────────────────────────
 * المركز يتعامل مع كل الادارات فالكتالوج يمكن الاضافة ولا يحصر قائمة قصيرة
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

$perms = w13_perms($conn, 'Tickets/tkt_subject_types.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w13_rows($is_super, 'tkt_subject_type',
                 array('orderBy' => 'owner_dept, type_code', 'limit' => 300));

$page_title = 'إيكوبيشن | كتالوج أنواع محل البلاغ';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'كتالوج أنواع محل البلاغ'; $header_icon = 'fa fa-diagram-project'; $header_actions = array();
    $header_back = array('href' => 'ticket_dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة مركز البلاغات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الانواع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_count($rows, "active", "1") ?></div><div class="ems-stat-label">انواع مفعلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "owner_dept") ?></div><div class="ems-stat-label">الادارات المالكة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w13_distinct($rows, "entity_kind") ?></div><div class="ems-stat-label">اصناف الكيانات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا انواع محل بلاغ', 'محل البلاغ نوع بسجله المرجعي لا نص حر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_tkt_subject_types')); ?>
    <table id="emsList_tkt_subject_types" class="data-table">
        <thead><tr><th>رمز النوع</th><th>النوع</th><th>صنف الكيان</th><th>السجل المرجعي</th><th>مفتاح السجل</th><th>الادارة المالكة</th><th>مفعل</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w13_txt($r["type_code"]) ?></td>
                    <td><?= ems_w13_txt($r["name_ar"]) ?></td>
                    <td><?= ems_w13_state((string) $r["entity_kind"]) ?></td>
                    <td><?= ems_w13_txt($r["ref_table"]) ?></td>
                    <td><?= ems_w13_txt($r["ref_key"]) ?></td>
                    <td><?= ems_w13_txt($r["owner_dept"]) ?></td>
                    <td><?= (int) $r["active"] ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

<?php
/**
 * Governance/compliance_calendar.php — تقويم الامتثال (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * مشتق من الالتزامات والتراخيص والإقرارات وما تأخر يعلم ولا إدخال حر فيه
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ نطاقِه
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد.**
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w14_view.php';

$ctx = w14_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w14_perms($conn, 'Governance/compliance_calendar.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_compliance_due',
                 array('orderBy' => 'due_date, obligation_no', 'limit' => 800));

$page_title = 'إيكوبيشن | تقويم الامتثال';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقويم الامتثال'; $header_icon = 'fa fa-calendar-check'; $header_actions = array();
    $header_back = array('href' => 'obligations.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الالتزامات التنظيمية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاستحقاقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "due") ?></div><div class="ems-stat-label">استحقاقات قائمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "late") ?></div><div class="ems-stat-label">استحقاقات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "owner_dept") ?></div><div class="ems-stat-label">الإدارات المسؤولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا استحقاقات', 'التقويم مشتق من الالتزام لا مكتوب يدويا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_compliance_calendar')); ?>
    <table id="emsList_compliance_calendar" class="data-table">
        <thead><tr><th>الالتزام</th><th>تاريخ الاستحقاق</th><th>الإدارة المسؤولة</th><th>مصدر الاشتقاق</th><th>مرجع التنفيذ</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["obligation_no"]) ?></td>
                    <td><?= ems_w14_txt($r["due_date"]) ?></td>
                    <td><?= ems_w14_txt($r["owner_dept"]) ?></td>
                    <td><?= ems_w14_txt($r["derived_from"]) ?></td>
                    <td><?= ems_w14_txt($r["settled_ref"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

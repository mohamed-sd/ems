<?php
/**
 * Governance/conduct_acknowledgements.php — إقرارات مدونة السلوك (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل موظف يقر بمدونة السلوك عند التعيين وعند كل إصدار جديد والناقص يعلم
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

$perms = w14_perms($conn, 'Governance/conduct_acknowledgements.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_conduct_ack',
                 array('orderBy' => 'code_version DESC, employee_id', 'limit' => 800));

$page_title = 'إيكوبيشن | إقرارات مدونة السلوك';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إقرارات مدونة السلوك'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'policies.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل السياسات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإقرارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "acknowledged") ?></div><div class="ems-stat-label">إقرارات مكتملة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "overdue") ?></div><div class="ems-stat-label">إقرارات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "code_version") ?></div><div class="ems-stat-label">الإصدارات المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إقرارات مسجلة', 'الإقرار سطر بدليله لا خانة اختيار'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_conduct_acknowledgements')); ?>
    <table id="emsList_conduct_acknowledgements" class="data-table">
        <thead><tr><th>الموظف</th><th>إصدار المدونة</th><th>السياسة</th><th>الموعد</th><th>تاريخ الإقرار</th><th>الدليل</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["employee_id"] ?></td>
                    <td><?= ems_w14_txt($r["code_version"]) ?></td>
                    <td><?= ems_w14_txt($r["policy_no"]) ?></td>
                    <td><?= ems_w14_txt($r["due_date"]) ?></td>
                    <td><?= ems_w14_txt($r["acked_at"]) ?></td>
                    <td><?= ems_w14_txt($r["evidence_ref"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

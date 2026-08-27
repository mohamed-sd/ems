<?php
/**
 * Audit/iaf_evidence_requests.php — طلبات الأدلة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الدليل يطلب رسميا بمهلة والتأخر في التزويد واقعة تسجل وتصعَّد
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

$perms = w14_perms($conn, 'Audit/iaf_evidence_requests.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'iaf_evidence_request',
                 array('orderBy' => 'due_date, request_no', 'limit' => 500));

$page_title = 'إيكوبيشن | طلبات الأدلة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلبات الأدلة'; $header_icon = 'fa fa-inbox'; $header_actions = array();
    $header_back = array('href' => 'iaf_audit_programs.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'برامج المراجعة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الطلبات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "overdue") ?></div><div class="ems-stat-label">طلبات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "escalated") ?></div><div class="ems-stat-label">طلبات مصعدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "auditee_dept") ?></div><div class="ems-stat-label">الجهات الخاضعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات أدلة', 'الطلب سطر بمهلته لا رسالة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الطلب</th><th>المهمة</th><th>الجهة الخاضعة</th><th>المطلوب</th><th>المهلة</th><th>تاريخ التزويد</th><th>أيام التأخر</th><th>مستوى التصعيد</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["request_no"]) ?></td>
                    <td><?= ems_w14_txt($r["engagement_no"]) ?></td>
                    <td><?= ems_w14_txt($r["auditee_dept"]) ?></td>
                    <td><?= ems_w14_txt($r["item_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["due_date"]) ?></td>
                    <td><?= ems_w14_txt($r["provided_at"]) ?></td>
                    <td><?= (int) $r["delay_days"] ?></td>
                    <td><?= (int) $r["escalation_level"] ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

<?php
/**
 * Governance/investigations.php — التحقيقات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * التحقيق بتكليف وصلاحيات ونطاق ولكل نوع مالكه والنتيجة ترفع لجهة أثرها
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

$perms = w14_perms($conn, 'Governance/investigations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_investigation',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | التحقيقات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التحقيقات'; $header_icon = 'fa fa-magnifying-glass'; $header_actions = array();
    $header_back = array('href' => 'integrity_reports.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'بلاغات النزاهة المحمية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد التحقيقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "inv_kind", "INTEGRITY") ?></div><div class="ems-stat-label">تحقيقات نزاهة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "inv_kind", "SPECIAL_INDEPENDENT") ?></div><div class="ems-stat-label">تحقيقات مستقلة بتكليف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "conflict_flag", "1") ?></div><div class="ems-stat-label">تحقيقات بها تعارض معلن</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تحقيقات مسجلة', 'التحقيق ملف بتكليفه لا محضر متداول'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_investigations')); ?>
    <table id="emsList_investigations" class="data-table">
        <thead><tr><th>رقم التحقيق</th><th>النوع</th><th>الإدارة المالكة</th><th>المصدر</th><th>التكليف المكتوب</th><th>المحقق</th><th>التنحي عن</th><th>أحيل إلى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["inv_no"]) ?></td>
                    <td><?= ems_w14_state((string) $r["inv_kind"]) ?></td>
                    <td><?= ems_w14_txt($r["owner_dept"]) ?></td>
                    <td><?= ems_w14_state((string) $r["origin"]) ?></td>
                    <td><?= ems_w14_txt($r["mandate_doc_ref"]) ?></td>
                    <td><?= (int) $r["investigator_id"] ?></td>
                    <td><?= ems_w14_txt($r["recusal_of"]) ?></td>
                    <td><?= ems_w14_txt($r["referred_to"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

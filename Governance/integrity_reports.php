<?php
/**
 * Governance/integrity_reports.php — بلاغات النزاهة المحمية (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * قناة محمية بسرية مشددة وهوية المبلغ محجوبة إلا لمستوى مخول ولا انتقام
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

$perms = w14_perms($conn, 'Governance/integrity_reports.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_integrity_report',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | بلاغات النزاهة المحمية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'بلاغات النزاهة المحمية'; $header_icon = 'fa fa-shield-halved'; $header_actions = array();
    $header_back = array('href' => 'exceptions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'طلبات الاستثناء');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد البلاغات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "is_anonymous", "1") ?></div><div class="ems-stat-label">بلاغات بهوية محجوبة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "received") ?></div><div class="ems-stat-label">بلاغات قبل الفرز</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "referred") ?></div><div class="ems-stat-label">بلاغات محالة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بلاغات مسجلة', 'البلاغ سطر برمزه لا اسم يعرض'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_integrity_reports')); ?>
    <table id="emsList_integrity_reports" class="data-table">
        <thead><tr><th>رقم البلاغ</th><th>القناة</th><th>هوية محجوبة</th><th>الموضوع</th><th>تاريخ الاستلام</th><th>تاريخ الفرز</th><th>أحيل إلى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["report_no"]) ?></td>
                    <td><?= ems_w14_txt($r["channel"]) ?></td>
                    <td><?= (int) $r["is_anonymous"] ?></td>
                    <td><?= ems_w14_txt($r["subject_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["received_at"]) ?></td>
                    <td><?= ems_w14_txt($r["triage_at"]) ?></td>
                    <td><?= ems_w14_txt($r["referred_to"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

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
require_once __DIR__ . '/../includes/w14_grid.php';

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

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_iaf_evidence_requests')); ?>
    <?php /* GUIDE_COLS:govui_field_close:emsList_iaf_evidence_requests
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الطلب' => 'g1',
        'معرف المهمة' => 'g2',
        'الجهة الخاضعة' => 'g3',
        'الدليل المطلوب' => 'g4',
        'مرجع المستند المتوقع' => 'g5',
        'تاريخ الطلب' => 'g6',
        'المهلة' => 'g7',
        'تاريخ الاستلام' => 'g8',
        'أيام التأخير' => 'g9',
        'اكتمال الدليل' => 'g10',
        'أثر النقص على المهمة' => 'g11',
        'حالة الطلب' => 'g12',
        'المنشئ' => 'g13',
        'تاريخ الإنشاء' => 'g14',
        'حالة البيانات' => 'g15',
        'مرجع المصدر' => 'g16',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('iaf_evidence_requests');
    echo ems_w14_grid('emsList_iaf_evidence_requests', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في طلبات الأدلة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>

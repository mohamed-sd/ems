<?php
/**
 * Governance/audit_followup.php — متابعة نتائج المراجعة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * تتابع خطة الإدارة ومهلها والمتكرر يعلم ونتيجة المراجعة تبقى عند المراجعة
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

$perms = w14_perms($conn, 'Governance/audit_followup.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_audit_followup',
                 array('orderBy' => 'plan_due, followup_no', 'limit' => 500));

$page_title = 'إيكوبيشن | متابعة نتائج المراجعة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'متابعة نتائج المراجعة'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'corrective_actions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الإجراءات التصحيحية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المتابعات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "follow_state", "overdue") ?></div><div class="ems-stat-label">متابعات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "follow_state", "escalated") ?></div><div class="ems-stat-label">متابعات مصعدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "finding_source", "external") ?></div><div class="ems-stat-label">ملاحظات من مراجعة خارجية</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا متابعات مسجلة', 'المتابعة خطة إدارة لا نتيجة مراجعة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_audit_followup')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف النتيجة' => 'followup_no',
        'جهة المراجعة' => '@finding_source',
        'مرجع التقرير' => 'finding_no',
        'النتيجة' => 'finding_ar',
        'التصنيف' => 'finding_class',
        'متكررة؟' => '#recurring',
        'خطة الإدارة' => 'mgmt_plan_ar',
        'المالك' => 'plan_owner_dept',
        'المهلة' => 'plan_due',
        'مرجع الإجراء التصحيحي' => 'action_no',
        'حالة النتيجة' => '@follow_state',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        /* التكرارُ رقمٌ في السجلِّ — والصفرُ ليس تكرارًا */
        'recurring' => function ($r) {
            $n = (int) $r['recurrence_no'];
            return $n > 1 ? ('نعم - ' . $n . ' مرات') : ($n === 1 ? 'لا' : '');
        },
    );
    echo ems_w14_grid('emsList_audit_followup', $GUIDE_COLS, $rows, $D, 'لا متابعات مسجلة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>

<?php
/**
 * Governance/corrective_actions.php — الإجراءات التصحيحية (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل إجراء بمالك ومهلة ودليل إغلاق والمتأخر يتصدر ويصعد
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

$perms = w14_perms($conn, 'Governance/corrective_actions.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_corrective_action',
                 array('orderBy' => 'due_date, action_no', 'limit' => 500));

$page_title = 'إيكوبيشن | الإجراءات التصحيحية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإجراءات التصحيحية'; $header_icon = 'fa fa-screwdriver-wrench'; $header_actions = array();
    $header_back = array('href' => 'breaches.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الإخلالات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإجراءات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "overdue") ?></div><div class="ems-stat-label">إجراءات متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "verified") ?></div><div class="ems-stat-label">إجراءات متحقق منها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "owner_dept") ?></div><div class="ems-stat-label">الإدارات المالكة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إجراءات مسجلة', 'الإجراء سطر بمهلته لا نية مكتوبة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_corrective_actions')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الإجراء' => 'action_no',
        'مصدر الإجراء' => '@source_kind',
        'مرجع المصدر' => 'source_ref',
        'وصف الإجراء' => 'title_ar',
        'المالك' => '#owner',
        'الإدارة' => 'owner_dept',
        'المهلة' => 'due_date',
        'أيام التأخير' => '#days',
        'دليل الإغلاق' => 'evidence_ref',
        'تحقق الفاعلية' => '#verified',
        'حالة الإجراء' => '@state',
        'المنشئ' => '#assigner',
    );
    $D = array(
        'owner' => function ($r) { return ems_w14_person($r['owner_person']); },
        'days'  => function ($r) {
            return ems_w14_days_to($r['due_date'], trim((string) $r['verified_at']) !== '');
        },
        /* الفاعليّةُ تُتحقَّق بمن ومتى — لا بعلامةٍ مفردة */
        'verified' => function ($r) {
            $p = ems_w14_person($r['verified_by']);
            $a = trim((string) $r['verified_at']);
            return ($p !== '' && $a !== '') ? ($p . ' / ' . $a) : ($p !== '' ? $p : $a);
        },
        'assigner' => function ($r) { return ems_w14_person($r['assigned_by']); },
    );
    echo ems_w14_grid('emsList_corrective_actions', $GUIDE_COLS, $rows, $D, 'لا إجراءات مسجلة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>

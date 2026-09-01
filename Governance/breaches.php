<?php
/**
 * Governance/breaches.php — سجل الإخلالات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل إخلال بقاعدة أو التزام يسجل بأثره ومعالجته ولا يغلق بلا إجراء ودليل
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

$perms = w14_perms($conn, 'Governance/breaches.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_breach',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | سجل الإخلالات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الإخلالات'; $header_icon = 'fa fa-triangle-exclamation'; $header_actions = array();
    $header_back = array('href' => 'investigations.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التحقيقات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإخلالات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "opened") ?></div><div class="ems-stat-label">إخلالات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "closed") ?></div><div class="ems-stat-label">إخلالات مغلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "deviation_no") ?></div><div class="ems-stat-label">إخلالات لها انحراف مرجعي</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إخلالات مسجلة', 'الإخلال حالة بأساسها لا ملاحظة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_breaches')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الإخلال' => 'case_no',
        'القاعدة/الالتزام المخل به' => '#rule',
        'مصدر الرصد' => '@opened_basis',
        'الجهة المخلة' => '#opener',
        'وصف الواقعة' => 'title_ar',
        'الأثر المقدر' => 'impact_ar',
        'درجة الخطورة' => '@severity',
        'الإجراء التصحيحي المتفرع' => 'action_no',
        'تصعيد؟' => '#escalated',
        'حالة الإخلال' => '@state',
        'تاريخ الإنشاء' => 'created_at',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        /* القاعدةُ المكسورةُ: الضابطُ أوّلًا فالسياسةُ فالالتزام — ولا يُفتح بلا ضابط */
        'rule' => function ($r) {
            foreach (array('control_ref', 'policy_no', 'obligation_no', 'deviation_no') as $k) {
                $v = trim((string) $r[$k]);
                if ($v !== '') { return $v; }
            }
            return '';
        },
        'opener' => function ($r) { return ems_w14_person($r['opened_by']); },
        /* التصعيدُ واقعةٌ مقيسة: فُتِح تحقيقٌ متفرّعٌ عن الإخلال */
        'escalated' => function ($r) {
            return trim((string) $r['investigation_no']) !== '' ? 'نعم' : 'لا';
        },
    );
    echo ems_w14_grid('emsList_breaches', $GUIDE_COLS, $rows, $D, 'لا إخلالات مسجلة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('سجل الاخلالات', 'سجل الإخلالات'),
        'table'    => 'gov_breach',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/breaches.php',
    )); ?>
</div>
</div>
</body></html>

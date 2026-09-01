<?php
/**
 * Governance/gov_board.php — لوحة الحوكمة والالتزام (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * قراءة حية مشتقة من حالات الحوكمة والاجراءات ولا ادخال فيها
 *
 * ◆ **الحبّةُ مؤشرٌ لا حالةُ إخلال** (`GOV-01` · ورقةُ الدليل: «مؤشر × فترة —
 *   قراءة حية»): كان السطحُ يقرأ `gov_breach` فيعرض **صفَّ إخلالٍ** في لوحةٍ
 *   حبّتُها **مؤشِّر** — وهو خرقُ §11 الأوّل. ⇒ فالقراءةُ من `gov_dashboard_kpi`
 *   ببنيةِ `*_dashboard_kpi` المستقرّةِ في الإداراتِ الأخرى.
 * ◆ **واللوحةُ إسقاطٌ لا مصدرُ حقيقة**: قيمةُ كلِّ مؤشرٍ تُكتب من مصدرِها
 *   المالكِ، والسطحُ يعرض ما كُتب — ⛔ ولا يحسبها هنا فيصير مصدرًا ثانيًا.
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

$perms = w14_perms($conn, 'Governance/gov_board.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_dashboard_kpi',
                 array('orderBy' => 'kpi_id, id', 'limit' => 300));

$page_title = 'إيكوبيشن | لوحة الحوكمة والالتزام';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة الحوكمة والالتزام'; $header_icon = 'fa fa-scale-balanced'; $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الرئيسية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد المؤشرات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "value") ?></div><div class="ems-stat-label">مؤشرات لها قيمة مقيسة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "uom") ?></div><div class="ems-stat-label">وحدات القياس</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "updated_on") ?></div><div class="ems-stat-label">مؤشرات لها آخر تحديث</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مؤشر معرف بعد للوحة الحوكمة', 'اللوحة قراءة مشتقة لا شاشة ادخال'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_gov_board')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف المؤشر' => 'kpi_id',
        'المؤشر - KPI Catalog' => 'kpi_ref',
        'القيمة' => '#value',
        'الوحدة' => 'uom',
        'الحالة' => '@state',
        'آخر تحديث' => 'updated_on',
    );
    $D = array(
        /* القيمةُ رقمٌ مقيسٌ — والصفرُ قيمةٌ والفراغُ غياب */
        'value' => function ($r) {
            $v = $r['value'];
            return ($v === null || (string) $v === '') ? '' : ems_w14_num($v);
        },
    );
    echo ems_w14_grid('emsList_gov_board', $GUIDE_COLS, $rows, $D, 'لا مؤشر معرف بعد للوحة الحوكمة'); /* /GUIDE_COLS */ ?></div>
</div>
</body></html>

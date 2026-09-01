<?php
/**
 * Financing/fin_ref_dictionary.php — القوائم وقاموس البيانات (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * قائمة او تعريف حقل واحد بمالكه — المرجع الذي تقرأ منه الشاشات.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-26)
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/w12_view.php';

$ctx = w12_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w12_perms($conn, 'Financing/fin_ref_dictionary.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_ref_list',
                 array('orderBy' => 'list_key, item_code', 'limit' => 800));

$page_title = 'إيكوبيشن | القوائم وقاموس البيانات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'القوائم وقاموس البيانات'; $header_icon = 'fa fa-book'; $header_actions = array();
    $header_back = array('href' => 'fin_models.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'نماذج التمويل ومعالجتها');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> القوائم وقاموس البيانات بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'USD' => 'g419',
            'مرابحة' => 'g420',
            'CONFIRMED_DOCUMENT' => 'g421',
            'Open' => 'g422',
            'High' => 'g423',
            'SUPPLIER' => 'g424',
            'على الشركة' => 'g425',
            'بنك' => 'g426',
            'نشط' => 'g427',
            'مكتمل' => 'g428',
            'مطروحة' => 'g429',
            'مستلم' => 'g430',
            'معتمد' => 'g431',
            'نافذ' => 'g432',
            'مستحق تاريخيا' => 'g433',
            'Direct' => 'g434',
            'إعادة جدولة' => 'g435',
            'على الممول' => 'g436',
            'مقفلة مغطاة' => 'g437',
            'مؤهلة للإقفال' => 'g438',
            'الشيت' => 'g439',
            'الحقل' => 'g440',
            'النوع' => 'g441',
            'تصنيف الفراغ (ما يعنيه الفراغ)' => 'g442',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_ref_list');
        echo ems_w14_grid('emsList_fin_ref', $GUIDE_COLS, $__gridRows, $D, 'لا مفردة مرجعية مسجلة بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد السطور</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "list_key") ?></div><div class="ems-stat-label">قوائم متمايزة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "active", "1") ?></div><div class="ems-stat-label">سطور نشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "owner_role") ?></div><div class="ems-stat-label">جهات مالكة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تعريفات مسجلة', 'التعريف مرجع الشاشة لا تعليق في شيفرة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_ref_dictionary')); ?>
    <table id="emsList_fin_ref_dictionary" class="data-table">
        <thead><tr><th>القائمة</th><th>الرمز</th><th>الحقل</th><th>التعريف</th><th>الجهة المالكة</th><th>نشط</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w12_state((string) $r["list_key"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["item_code"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["field_name"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["definition"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["owner_role"]) ?></td>
                    <td><?= ((int) $r["active"] === 1 ? "نعم" : "لا") ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

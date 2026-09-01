<?php
/**
 * Financing/fin_contract_terms.php — بنود وشروط التمويل (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * بند تعاقدي واحد بمرجع بنده في المستند — سطر لا عمود مخترع.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-08)
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

$perms = w12_perms($conn, 'Financing/fin_contract_terms.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_contract_term',
                 array('orderBy' => 'contract_id, term_key', 'limit' => 800));

$page_title = 'إيكوبيشن | بنود وشروط التمويل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'بنود وشروط التمويل'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'fin_contracts.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل عقود التمويل');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> بنود وشروط التمويل بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود العقد' => 'g150',
            'كود العملية' => 'g151',
            'كود الممول' => 'g152',
            'اسم الممول (بحث)' => 'g153',
            'نموذج التمويل' => 'g154',
            'العملة' => 'g155',
            'سريان من' => 'g156',
            'رأس المال' => 'g157',
            'نسبة المقدم %' => 'g158',
            'قيمة المقدم' => 'g159',
            'نسبة الأرباح %' => 'g160',
            'قيمة الأرباح التعاقدية' => 'g161',
            'رسوم إدارية' => 'g162',
            'رسوم تأمين' => 'g163',
            'المدة (شهر)' => 'g164',
            'نظام الأقساط' => 'g165',
            'عدد الأقساط' => 'g166',
            'قيمة القسط' => 'g167',
            'تحويل الملكية في نهاية التمويل التفصيل' => 'g168',
            'حفظ مستندات الملكية التفصيل' => 'g169',
            'الحجية' => 'g170',
            'حالة البيانات' => 'g171',
            'Source_Row_Ref' => 'g172',
            'ملاحظات' => 'g173',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_contract_term');
        echo ems_w14_grid('emsList_fin_terms', $GUIDE_COLS, $__gridRows, $D, 'لا بند مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد البنود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "contract_id") ?></div><div class="ems-stat-label">عقود لها بنود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "is_binding", "1") ?></div><div class="ems-stat-label">بنود ملزمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "term_key") ?></div><div class="ems-stat-label">انواع البنود</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بنود مسجلة', 'كل بند سطر بمرجعه في مستند العقد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_contract_terms')); ?>
    <table id="emsList_fin_contract_terms" class="data-table">
        <thead><tr><th>العقد</th><th>البند</th><th>القيمة</th><th>رقم البند في المستند</th><th>ملزم</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["contract_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["term_key"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["term_value"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["clause_ref"]) ?></td>
                    <td><?= ((int) $r["is_binding"] === 1 ? "نعم" : "لا") ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

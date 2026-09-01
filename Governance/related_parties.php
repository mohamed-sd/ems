<?php
/**
 * Governance/related_parties.php — الأطراف ذات العلاقة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل تعامل مع طرف ذي علاقة يمر بإفصاح إلزامي ويوسم بين الكيانات منذ إنشائه
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

$perms = w14_perms($conn, 'Governance/related_parties.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_related_party',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | الأطراف ذات العلاقة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الأطراف ذات العلاقة'; $header_icon = 'fa fa-handshake'; $header_actions = array();
    $header_back = array('href' => 'conflict_disclosures.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تضارب المصالح');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الأطراف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "intercompany_flag", "1") ?></div><div class="ems-stat-label">تعاملات بين كيانات المجموعة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "disclosure_no") ?></div><div class="ems-stat-label">تعاملات لها إفصاح</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "active") ?></div><div class="ems-stat-label">أطراف نشطة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أطراف ذات علاقة', 'الطرف سطر بتعامله لا اسم في ملاحظة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_related_parties')); ?>
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الطرف' => 'party_no',
        'اسم الطرف' => 'party_name',
        'نوع الصلة' => 'relation_ar',
        'الشخص المرتبط داخليا' => '#person',
        'التعاملات القائمة' => 'deal_ref',
        'قيمة التعاملات' => '#amount',
        'شروط التعامل' => 'transaction_type',
        'مرجع إفصاح AAM' => 'disclosure_no',
        'حالة السطر' => '@state',
        'تاريخ الإنشاء' => 'created_at',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        'person' => function ($r) { return ems_w14_person($r['person_id']); },
        /* القيمةُ بعملتِها — ورقمٌ بلا عملةٍ لا يُقارَن */
        'amount' => function ($r) {
            $v = trim((string) $r['deal_amount']);
            if ($v === '' || (float) $v == 0.0) { return ''; }
            return ems_w14_num($v) . ' ' . trim((string) $r['deal_currency']);
        },
    );
    echo ems_w14_grid('emsList_related_parties', $GUIDE_COLS, $rows, $D, 'لا أطراف ذات علاقة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('الاطراف ذات العلاقه', 'الأطراف ذات العلاقة'),
        'table'    => 'gov_related_party',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/related_parties.php',
    )); ?>
</div>
</div>
</body></html>

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
require_once __DIR__ . '/../includes/w14_grid.php';

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
    <?php /* GUIDE_COLS:govui_field_close
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'رقم البلاغ' => 'report_no',
        'قناة الورود' => '@channel',
        'هوية المبلغ - مقيدة' => '#reporter',
        'موضوع البلاغ' => 'subject_ar',
        'الجهة/الشخص المعني' => 'referred_to',
        'الوصف المقيد' => 'description_ar',
        'التقييم الأولي' => '#triage',
        'مرجع التحقيق' => 'investigation_no',
        'إجراء الحماية' => '#protection',
        'حالة البلاغ' => '@state',
        'تاريخ الإنشاء' => 'received_at',
        'مرجع المصدر' => 'src_ref',
    );
    $D = array(
        /* الهويّةُ مقيَّدةٌ بنصِّ الورقة: الرمزُ يُعرَض والاسمُ لا يُكشف هنا —
           والمجهولُ يُعلَن مجهولًا لا فارغًا. */
        'reporter' => function ($r) {
            if ((int) $r['is_anonymous'] === 1) { return 'بلاغ مجهول المصدر'; }
            $t = trim((string) $r['reporter_token']);
            return $t !== '' ? ('رمز المبلغ ' . $t) : '';
        },
        'triage' => function ($r) {
            $p = ems_w14_person($r['triage_by']);
            $a = trim((string) $r['triage_at']);
            return ($p !== '' && $a !== '') ? ($p . ' / ' . $a) : ($p !== '' ? $p : $a);
        },
        'protection' => function ($r) {
            return ((int) $r['retaliation_flag'] === 1) ? 'حماية من الانتقام مفعلة' : '';
        },
    );
    echo ems_w14_grid('emsList_integrity_reports', $GUIDE_COLS, $rows, $D, 'لا بلاغات مسجلة'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('بلاغات النزاهه المحميه', 'بلاغات النزاهة المحمية'),
        'table'    => 'gov_integrity_report',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Governance/integrity_reports.php',
    )); ?>
</div>
</div>
</body></html>

<?php
/**
 * Portal/exec_leadership_appointments.php — موافقات التعيين في المسميات القيادية (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * التعيين القيادي بقاعدة سلطته ومعه افصاح الاطراف ذات العلاقة قبل القرار
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة الموارد البشرية** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
 *
 * ◆ **والرؤيةُ لا تساوي السلطة**: هذا سطحُ قراءةٍ بلا فعلِ كتابة؛ والقرارُ
 *   يمرُّ بمحرّكِ الاعتمادِ نفسِه عند مالكِ المستند لا من هنا.
 *
 * ◆ **والنطاقُ من محرّكٍ واحد**: الرئيسُ يرى الشركةَ والنائبُ نطاقَه
 *   والموظّفُ صفوفَه — بالشيفرةِ نفسِها. ⛔ ولا ثلاثةَ أنظمة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w15_view.php';

$ctx = w15_ctx();
$is_super = $ctx['is_super'];
if (!$is_super && $ctx['company_id'] <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w15_perms($conn, 'Portal/exec_leadership_appointments.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('assignment_kind' => 'leadership'), 'orderBy' => 'requested_at DESC, id DESC', 'limit' => 300, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exec_assignments', $vis, $opt);

$page_title = 'إيكوبيشن | موافقات التعيين في المسميات القيادية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'موافقات التعيين في المسميات القيادية'; $header_icon = 'fa fa-user-tie'; $header_actions = array();
    $header_back = array('href' => 'ceo_assignments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التكليفات والإنابات المؤقتة');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> موافقات التعيين القيادية بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الطلب' => 'g317',
            'نوع الطلب' => 'g318',
            'المسمى القيادي من الهيكل' => 'g319',
            'الوحدة التنظيمية' => 'g320',
            'المرشح/المعني' => 'g321',
            'مرجع الشخص بالموارد' => 'g322',
            'سقف الاعتماد الممنوح' => 'g323',
            'بدل التكليف' => 'g324',
            'إفصاح طرف ذي علاقة' => 'g325',
            'مراجعة الموارد البشرية' => 'g326',
            'مراجعة الحوكمة' => 'g327',
            'قاعدة AAM المفعلة' => 'g328',
            'قرار الرئيس' => 'g329',
            'مرجع الإحالة للسلطة المحجوزة' => 'g330',
            'تاريخ النفاذ' => 'g331',
            'حالة الطلب' => 'g332',
            'المنشئ' => 'g333',
            'تاريخ الإنشاء' => 'g334',
            'المراجع' => 'g335',
            'المعتمد' => 'g336',
            'تاريخ الاعتماد' => 'g337',
            'حالة البيانات' => 'g338',
            'مرجع المصدر' => 'g339',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_leadership_appointment');
        echo ems_w14_grid('emsList_exec_leadership_appointment', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في موافقات التعيين القيادية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الطلبات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "presented") ?></div><div class="ems-stat-label">طلبات معروضة للقرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "conflict_state", "conflict") ?></div><div class="ems-stat-label">طلبات عليها تعارض</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "authority_ref") ?></div><div class="ems-stat-label">طلبات بمرجع سلطة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات تعيين قيادي', 'الطلب يصل بإفصاحه لا بدونه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_leadership_appointments')); ?>
    <table id="emsList_exec_leadership_appointments" class="data-table">
        <thead><tr><th>رقم الطلب</th><th>المرشح</th><th>المسمى</th><th>النطاق</th><th>حالة التعارض</th><th>من</th><th>إلى</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["assignment_no"]) ?></td>
                    <td><?= ems_w15_txt($r["subject_name"]) ?></td>
                    <td><?= ems_w15_txt($r["role_name"]) ?></td>
                    <td><?= ems_w15_txt($r["scope_note"]) ?></td>
                    <td><?= ems_w15_state((string) $r["conflict_state"]) ?></td>
                    <td><?= ems_w15_txt($r["effective_from"]) ?></td>
                    <td><?= ems_w15_txt($r["effective_to"]) ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

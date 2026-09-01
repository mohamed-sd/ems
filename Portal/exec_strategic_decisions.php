<?php
/**
 * Portal/exec_strategic_decisions.php — القرارات الاستراتيجية (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * سجل منفصل عن اليومي ولا يصل القرار للقمة الا بعد مراجعات الادارات وتوصية نيابية
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **سجل قرارات القيادة** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_strategic_decisions.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('issue_type' => 'استراتيجي'), 'orderBy' => 'raised_date DESC, id DESC', 'limit' => 300, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exec_decisions', $vis, $opt);

$page_title = 'إيكوبيشن | القرارات الاستراتيجية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'القرارات الاستراتيجية'; $header_icon = 'fa fa-chess'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> القرارات الاستراتيجية بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'Decision_ID' => 'g271',
            'الكيان' => 'g272',
            'نوع القرار' => 'g273',
            'Proposal_Source' => 'g274',
            'Business_Case' => 'g275',
            'الخيارات المطروحة' => 'g276',
            'الخيار المختار' => 'g277',
            'مبرر الاختيار' => 'g278',
            'Financial_Impact' => 'g279',
            'Risk_Assessment' => 'g280',
            'Legal_Review_Status' => 'g281',
            'Finance_Review_Status' => 'g282',
            'Compliance_Review_Status' => 'g283',
            'Risk_Review_Status' => 'g284',
            'Relevant_Deputy_Recommendation' => 'g285',
            'فحص اكتمال بوابة المراجعات' => 'g286',
            'Recommendation' => 'g287',
            'Decision' => 'g288',
            'Effective_Date' => 'g289',
            'Owner مرجع مالك التنفيذ' => 'g290',
            'Milestones' => 'g291',
            'Follow_Up' => 'g292',
            'Closure' => 'g293',
            'المنشئ' => 'g294',
            'تاريخ الإنشاء' => 'g295',
            'المراجع' => 'g296',
            'المعتمد' => 'g297',
            'تاريخ الاعتماد' => 'g298',
            'حالة البيانات' => 'g299',
            'مرجع المصدر' => 'g300',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_strategic_decision');
        echo ems_w14_grid('emsList_exec_strategic_decision', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في القرارات الاستراتيجية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد القرارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "status", "مفتوح") ?></div><div class="ems-stat-label">قرارات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "chosen_option") ?></div><div class="ems-stat-label">قرارات باختيار محسوم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "authority_ref") ?></div><div class="ems-stat-label">قرارات بمرجع سلطة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا قرارات استراتيجية', 'القرار يصل بعد مراجعاته لا قبلها'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_strategic_decisions')); ?>
    <table id="emsList_exec_strategic_decisions" class="data-table">
        <thead><tr><th>رقم القرار</th><th>تاريخ الرفع</th><th>الإدارة الرافعة</th><th>الموضوع</th><th>البدائل</th><th>الاختيار</th><th>مبرر الاختيار</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["decision_no"]) ?></td>
                    <td><?= ems_w15_txt($r["raised_date"]) ?></td>
                    <td><?= ems_w15_txt($r["raising_dept"]) ?></td>
                    <td><?= ems_w15_txt($r["issue_desc"]) ?></td>
                    <td><?= ems_w15_txt($r["options_text"]) ?></td>
                    <td><?= ems_w15_txt($r["chosen_option"]) ?></td>
                    <td><?= ems_w15_txt($r["choice_reason"]) ?></td>
                    <td><?= ems_w15_state((string) $r["status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

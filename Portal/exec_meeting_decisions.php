<?php
/**
 * Portal/exec_meeting_decisions.php — قرارات الاجتماعات (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * كل قرار صف مستقل بمرجع اجتماعه ومالكه وادارته ولا قرارات متعددة في خلية
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

$perms = w15_perms($conn, 'Portal/exec_meeting_decisions.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'decision_date DESC, id DESC', 'limit' => 400, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exec_decisions', $vis, $opt);

$page_title = 'إيكوبيشن | قرارات الاجتماعات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'قرارات الاجتماعات'; $header_icon = 'fa fa-clipboard-list'; $header_actions = array();
    $header_back = array('href' => 'exec_strategic_decisions.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'القرارات الاستراتيجية');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> قرارات الاجتماعات بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'Decision_ID' => 'g340',
            'Meeting_ID' => 'g341',
            'Topic' => 'g342',
            'Decision' => 'g343',
            'Owner مرجع مسؤول التنفيذ' => 'g344',
            'Department مرجع الإدارة' => 'g345',
            'Due_Date' => 'g346',
            'Priority' => 'g347',
            'Status' => 'g348',
            'Evidence يرفقه المنفذ' => 'g349',
            'Closed_Date' => 'g350',
            'المنشئ' => 'g351',
            'تاريخ الإنشاء' => 'g352',
            'حالة البيانات' => 'g353',
            'مرجع المصدر' => 'g354',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_meeting_decision');
        echo ems_w14_grid('emsList_exec_meeting_decision', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في قرارات الاجتماعات'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد القرارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "parent_ref") ?></div><div class="ems-stat-label">قرارات بمرجع اجتماع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "assigned_dept") ?></div><div class="ems-stat-label">قرارات لها إدارة مكلفة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "followup_date") ?></div><div class="ems-stat-label">قرارات لها موعد متابعة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا قرارات مسجلة', 'القرار صف مستقل بمرجع اجتماعه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_meeting_decisions')); ?>
    <table id="emsList_exec_meeting_decisions" class="data-table">
        <thead><tr><th>رقم القرار</th><th>مرجع الاجتماع</th><th>تاريخ القرار</th><th>الموضوع</th><th>القرار</th><th>الإدارة المكلفة</th><th>موعد المتابعة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["decision_no"]) ?></td>
                    <td><?= ems_w15_txt($r["parent_ref"]) ?></td>
                    <td><?= ems_w15_txt($r["decision_date"]) ?></td>
                    <td><?= ems_w15_txt($r["issue_desc"]) ?></td>
                    <td><?= ems_w15_txt($r["chosen_option"]) ?></td>
                    <td><?= ems_w15_txt($r["assigned_dept"]) ?></td>
                    <td><?= ems_w15_txt($r["followup_date"]) ?></td>
                    <td><?= ems_w15_state((string) $r["status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

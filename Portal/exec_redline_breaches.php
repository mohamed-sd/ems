<?php
/**
 * Portal/exec_redline_breaches.php — تجاوزات الخطوط الحمراء (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * طلبات استثناء قبل التنفيذ من قاعدة مانعة وتظل مغلقة حتى يصدر القرار بسلطته
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة الحوكمة والالتزام** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_redline_breaches.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('risk_level' => 'legal_forbidden'), 'orderBy' => 'req_id DESC', 'limit' => 400, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exception_requests', $vis, $opt);

$page_title = 'إيكوبيشن | تجاوزات الخطوط الحمراء';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تجاوزات الخطوط الحمراء'; $header_icon = 'fa fa-ban'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> تجاوزات الخطوط الحمراء بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'Exception_ID' => 'g152',
            'Source_Department' => 'g153',
            'Source_Record' => 'g154',
            'Rule_Breached' => 'g155',
            'Red_Line_Type' => 'g156',
            'Severity' => 'g157',
            'Financial_Exposure' => 'g158',
            'Operational_Exposure' => 'g159',
            'Legal_Exposure' => 'g160',
            'Compliance_Exposure' => 'g161',
            'Waivability_Type' => 'g162',
            'Requested_Exception' => 'g163',
            'Business_Justification' => 'g164',
            'Department_Recommendation' => 'g165',
            'Deputy_Recommendation' => 'g166',
            'Escalation_To' => 'g167',
            'CEO_Decision' => 'g168',
            'Conditions' => 'g169',
            'Valid_From' => 'g170',
            'Valid_To' => 'g171',
            'Evidence' => 'g172',
            'Closure' => 'g173',
            'المنشئ' => 'g174',
            'تاريخ الإنشاء' => 'g175',
            'حالة البيانات' => 'g176',
            'مرجع المصدر' => 'g177',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_redline_breach');
        echo ems_w14_grid('emsList_exec_redline_breach', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تجاوزات الخطوط الحمراء'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد طلبات التجاوز</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Pending") ?></div><div class="ems-stat-label">تنتظر القرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Approved") ?></div><div class="ems-stat-label">صدر فيها قرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "guard_code") ?></div><div class="ems-stat-label">القواعد المطلوب تجاوزها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات تجاوز', 'القاعدة المانعة تبقى مغلقة حتى قرار بسلطته'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_redline_breaches')); ?>
    <table id="emsList_exec_redline_breaches" class="data-table">
        <thead><tr><th>رقم الطلب</th><th>القاعدة</th><th>السبب</th><th>نطاق الطلب</th><th>من</th><th>إلى</th><th>الأثر المتوقع</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["req_id"] ?></td>
                    <td><?= ems_w15_txt($r["guard_code"]) ?></td>
                    <td><?= ems_w15_txt($r["reason"]) ?></td>
                    <td><?= ems_w15_state((string) $r["scope_type"]) ?></td>
                    <td><?= ems_w15_txt($r["valid_from"]) ?></td>
                    <td><?= ems_w15_txt($r["valid_to"]) ?></td>
                    <td><?= ems_w15_txt($r["expected_impact"]) ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

<?php
/**
 * Portal/exec_daily_deviations.php — انحرافات وقرارات اليوم (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * كل انحراف صف مستقل بمسؤوله والانحراف يبقى عند مالكه ولا تفتح له حالة حوكمة تلقائيا
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة التشغيل** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_daily_deviations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'occurred_at DESC, id DESC', 'limit' => 400, 'scope_col' => 'project_id');
$rows = w15_rows($is_super, 'ctl_deviation', $vis, $opt);

$page_title = 'إيكوبيشن | انحرافات وقرارات اليوم';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'انحرافات وقرارات اليوم'; $header_icon = 'fa fa-triangle-exclamation'; $header_actions = array();
    $header_back = array('href' => 'exec_daily_report.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التقرير اليومي التنفيذي');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> انحرافات وقرارات اليوم بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف البند' => 'g69',
            'معرف سطر اليوم' => 'g70',
            'نوع البند' => 'g71',
            'المشروع' => 'g72',
            'الموقع' => 'g73',
            'الوصف' => 'g74',
            'المسؤول' => 'g75',
            'المرجع الأصلي' => 'g76',
            'الإجراء المقرر Decision Event' => 'g77',
            'موعد الحل Decision Event' => 'g78',
            'الحالة' => 'g79',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_daily_deviation');
        echo ems_w14_grid('emsList_exec_daily_deviation', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في انحرافات وقرارات اليوم'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الانحرافات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "risk_ref") ?></div><div class="ems-stat-label">انحرافات فتحت محفزا للمخاطر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "governance_ref") ?></div><div class="ems-stat-label">انحرافات فتحت حالة حوكمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "owner_dept") ?></div><div class="ems-stat-label">الإدارات المالكة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا انحرافات مسجلة', 'الانحراف يبقى عند مالكه ويعرض هنا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_daily_deviations')); ?>
    <table id="emsList_exec_daily_deviations" class="data-table">
        <thead><tr><th>رقم الانحراف</th><th>الإدارة المالكة</th><th>النوع</th><th>وقت الوقوع</th><th>المدة</th><th>التصنيف</th><th>القاعدة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["deviation_no"]) ?></td>
                    <td><?= ems_w15_txt($r["owner_dept"]) ?></td>
                    <td><?= ems_w15_state((string) $r["deviation_kind"]) ?></td>
                    <td><?= ems_w15_txt($r["occurred_at"]) ?></td>
                    <td><?= ems_w15_num($r["duration_hours"]) ?></td>
                    <td><?= ems_w15_state((string) $r["classification"]) ?></td>
                    <td><?= ems_w15_txt($r["rule_code"]) ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

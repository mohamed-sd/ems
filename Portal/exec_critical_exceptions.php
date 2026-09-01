<?php
/**
 * Portal/exec_critical_exceptions.php — الاستثناءات الحرجة (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * حالات حرجة واقعة وصلت للقمة لقبول تعرض او قرار علاج والقرار بسلطته لا بموقعه
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

$perms = w15_perms($conn, 'Portal/exec_critical_exceptions.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('risk_level' => 'high'), 'orderBy' => 'req_id DESC', 'limit' => 400, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exception_requests', $vis, $opt);

$page_title = 'إيكوبيشن | الاستثناءات الحرجة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الاستثناءات الحرجة'; $header_icon = 'fa fa-circle-exclamation'; $header_actions = array();
    $header_back = array('href' => 'exec_redline_breaches.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تجاوزات الخطوط الحمراء');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> الاستثناءات الحرجة بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف البند' => 'g198',
            'رقم الاستثناء' => 'g199',
            'الإدارة المصدر' => 'g200',
            'قاعدة المنع/السياسة' => 'g201',
            'المبرر' => 'g202',
            'المدة المطلوبة' => 'g203',
            'رأي الحوكمة' => 'g204',
            'رأي النائب المختص' => 'g205',
            'التعرض المقدر' => 'g206',
            'Risk_Appetite_Status' => 'g207',
            'القرار' => 'g208',
            'قبول المخاطرة موثق؟' => 'g209',
            'شروط إضافية' => 'g210',
            'حالة البند' => 'g211',
            'المنشئ' => 'g212',
            'تاريخ الإنشاء' => 'g213',
            'حالة البيانات' => 'g214',
            'مرجع المصدر' => 'g215',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_critical_exception');
        echo ems_w14_grid('emsList_exec_critical_exception', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الاستثناءات الحرجة'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاستثناءات الحرجة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Pending") ?></div><div class="ems-stat-label">تنتظر القرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Active") ?></div><div class="ems-stat-label">استثناءات سارية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "Expired") ?></div><div class="ems-stat-label">استثناءات منتهية</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا استثناءات حرجة', 'الحالة الحرجة تصل بسلطتها لا بموقعها'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_critical_exceptions')); ?>
    <table id="emsList_exec_critical_exceptions" class="data-table">
        <thead><tr><th>رقم الطلب</th><th>القاعدة</th><th>السبب</th><th>مستوى التعرض</th><th>تاريخ الانتهاء</th><th>مرات الاستخدام</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["req_id"] ?></td>
                    <td><?= ems_w15_txt($r["guard_code"]) ?></td>
                    <td><?= ems_w15_txt($r["reason"]) ?></td>
                    <td><?= ems_w15_state((string) $r["risk_level"]) ?></td>
                    <td><?= ems_w15_txt($r["valid_to"]) ?></td>
                    <td><?= (int) $r["usage_count"] ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

<?php
/**
 * Financing/fin_close_audit.php — تقرير المراجعة والإغلاق (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * بند مراجعة واحد — يقرأ الاقفالات الثلاثة منفصلة ويعرض روابطها لا دمجها.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-28)
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

$perms = w12_perms($conn, 'Financing/fin_close_audit.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_close_link',
                 array('orderBy' => 'id DESC', 'limit' => 800));

$page_title = 'إيكوبيشن | تقرير المراجعة والإغلاق';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تقرير المراجعة والإغلاق'; $header_icon = 'fa fa-magnifying-glass-chart'; $header_actions = array();
    $header_back = array('href' => 'financing_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة المحفظة');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> تقرير المراجعة والاغلاق بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'القسم' => 'g449',
            'البند' => 'g450',
            'القيمة/النتيجة' => 'g451',
            'الحالة' => 'g452',
            'ملاحظة' => 'g453',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_close_audit');
        echo ems_w14_grid('emsList_fin_audit', $GUIDE_COLS, $__gridRows, $D, 'لا بند مراجعة مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الروابط</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "parent_kind", "MONTHLY") ?></div><div class="ems-stat-label">ضم شهري لتعاقدي</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "parent_kind", "FINAL") ?></div><div class="ems-stat-label">قراءة نهائي لدوري</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "link_rule") ?></div><div class="ems-stat-label">قواعد ربط متمايزة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا روابط اقفال مسجلة', 'الشهري يضم التعاقدي والنهائي يقرأ آخر دوري'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_close_audit')); ?>
    <table id="emsList_fin_close_audit" class="data-table">
        <thead><tr><th>صنف الأب</th><th>الأب</th><th>صنف الابن</th><th>الابن</th><th>قاعدة الربط</th><th>السبب</th><th>التاريخ</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w12_state((string) $r["parent_kind"]) ?></td>
                    <td><?= (int) $r["parent_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["child_kind"]) ?></td>
                    <td><?= (int) $r["child_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["link_rule"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["why"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["created_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

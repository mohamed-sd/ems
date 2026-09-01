<?php
/**
 * Financing/fin_financier_dues.php — استحقاقات الممول (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * استحقاق ممول واحد — مشتق من الاقساط لا مكتوب بيد.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-14)
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

$perms = w12_perms($conn, 'Financing/fin_financier_dues.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'financing_installments',
                 array('orderBy' => 'due_date', 'limit' => 600));

$page_title = 'إيكوبيشن | استحقاقات الممول';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'استحقاقات الممول'; $header_icon = 'fa fa-hand-holding-dollar'; $header_actions = array();
    $header_back = array('href' => 'installments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الأقساط ومواعيد السداد');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> استحقاقات الممول بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود الاستحقاق' => 'g253',
            'كود العملية' => 'g254',
            'كود الممول' => 'g255',
            'اسم الممول (بحث)' => 'g256',
            'العملة' => 'g257',
            'المستحق حتى الأفق' => 'g258',
            'المدفوع (الدفتر)' => 'g259',
            'صافي المستحق غير المسدد' => 'g260',
            'أساس الاحتساب' => 'g261',
            'Record_Basis' => 'g262',
            'Derivation_Rule' => 'g263',
            'Confidence' => 'g264',
            'Needs_Review' => 'g265',
            'حالة البيانات' => 'g266',
            'Source_Row_Ref' => 'g267',
            'ملاحظات' => 'g268',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_financier_due');
        echo ems_w14_grid('emsList_fin_dues', $GUIDE_COLS, $__gridRows, $D, 'لا استحقاق مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاستحقاقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "overdue") ?></div><div class="ems-stat-label">متأخرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "paid") ?></div><div class="ems-stat-label">مسددة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "amount_total")) ?></div><div class="ems-stat-label">اجمالي الاستحقاق</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا استحقاقات قائمة', 'الاستحقاق يشتق من جدول الاقساط ومن ما خصص عليه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_financier_dues')); ?>
    <table id="emsList_fin_financier_dues" class="data-table">
        <thead><tr><th>العملية</th><th>رقم القسط</th><th>تاريخ الاستحقاق</th><th>اصل</th><th>عائد</th><th>اجمالي القسط</th><th>المخصص</th><th>العملة</th><th>الاقفال التعاقدي</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= (int) $r["seq_no"] ?></td>
                    <td><?= htmlspecialchars((string) $r["due_date"]) ?></td>
                    <td><?= ems_w12_num($r["amount_principal"]) ?></td>
                    <td><?= ems_w12_num($r["amount_profit"]) ?></td>
                    <td><?= ems_w12_num($r["amount_total"]) ?></td>
                    <td><?= ems_w12_num($r["allocated_amount"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= (int) $r["contract_close_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

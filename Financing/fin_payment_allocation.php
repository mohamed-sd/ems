<?php
/**
 * Financing/fin_payment_allocation.php — تخصيص السداد على الأقساط (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * سطر تخصيص واحد: دفعة وقسط واقفال — من امر دفع منفذ وحده لا من صف مجمع.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-18)
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

$perms = w12_perms($conn, 'Financing/fin_payment_allocation.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_payment_allocation',
                 array('orderBy' => 'id DESC', 'limit' => 800));

$page_title = 'إيكوبيشن | تخصيص السداد على الأقساط';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تخصيص السداد على الأقساط'; $header_icon = 'fa fa-arrows-split-up-and-left'; $header_actions = array();
    $header_back = array('href' => 'fin_payment_orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الدفع والسداد الفعلي');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> تخصيص السداد على الاقساط بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود التخصيص' => 'g350',
            'Payment_ID' => 'order_id',
            'FOP_ID' => 'g351',
            'FINS_ID' => 'g352',
            'Contractual_Close_ID' => 'g353',
            'Monthly_Close_ID' => 'g354',
            'نوع المكون' => 'g355',
            'العملة' => 'g356',
            'المبلغ المخصص' => 'g357',
            'Outstanding قبل' => 'g358',
            'تاريخ التخصيص' => 'g359',
            'قاعدة التخصيص' => 'g360',
            'علم تجاوز' => 'g361',
            'حالة البيانات' => 'g362',
            'اختبار Σ لكل دفعة' => 'g363',
            'اختبار ≤ المستحق' => 'g364',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_payment_allocation');
        echo ems_w14_grid('emsList_fin_alloc', $GUIDE_COLS, $__gridRows, $D, 'لا تخصيص سداد مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد سطور التخصيص</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "order_id") ?></div><div class="ems-stat-label">أوامر دفع مخصصة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "installment_id") ?></div><div class="ems-stat-label">أقساط مغطاة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "amount")) ?></div><div class="ems-stat-label">اجمالي المخصص</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سطور تخصيص', 'التخصيص يبدأ من امر دفع منفذ'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_payment_allocation')); ?>
    <table id="emsList_fin_payment_allocation" class="data-table">
        <thead><tr><th>أمر الدفع</th><th>القسط</th><th>صنف الاقفال</th><th>الاقفال</th><th>المكون</th><th>المبلغ</th><th>المخصص</th><th>تاريخ التخصيص</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["order_id"] ?></td>
                    <td><?= (int) $r["installment_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["close_kind"]) ?></td>
                    <td><?= (int) $r["close_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["part_kind"]) ?></td>
                    <td><?= ems_w12_num($r["amount"]) ?></td>
                    <td><?= (int) $r["allocated_by"] ?></td>
                    <td><?= htmlspecialchars((string) $r["allocated_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

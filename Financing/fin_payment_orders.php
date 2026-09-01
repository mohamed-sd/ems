<?php
/**
 * Financing/fin_payment_orders.php — أوامر الدفع والسداد الفعلي (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * امر دفع مستقبلي واحد بطالبه ومعتمده ومرجعه البنكي — والطبقة التاريخية المجمعة في شاشة خريطة الترحيل.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-17)
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

$perms = w12_perms($conn, 'Financing/fin_payment_orders.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_payment_order',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | أوامر الدفع والسداد الفعلي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'أوامر الدفع والسداد الفعلي'; $header_icon = 'fa fa-money-check-dollar'; $header_actions = array();
    $header_back = array('href' => 'fin_financier_dues.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'استحقاقات الممول');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> اوامر الدفع والسداد الفعلي بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود السداد' => 'g327',
            'كود العملية' => 'g328',
            'العملة' => 'g329',
            'المبلغ المدفوع (مجمع)' => 'g330',
            'عدد صفوف الدفتر' => 'g331',
            'الفترة' => 'g332',
            'كود أمر الدفع' => 'g333',
            'معتمد الأمر' => 'g334',
            'المرجع البنكي' => 'g335',
            'أمر الدفع' => 'g336',
            'الحجية' => 'g337',
            'حالة البيانات' => 'g338',
            'Source_Row_Ref' => 'g339',
            'ملاحظات' => 'g340',
            'تاريخ الطلب' => 'g341',
            'المبلغ المطلوب' => 'g342',
            'المبلغ المعتمد' => 'g343',
            'حالة الأمر' => 'g344',
            'معتمد الأمر (مستقبلي)' => 'g345',
            'تاريخ التنفيذ الفعلي' => 'g346',
            'المبلغ المنفذ' => 'g347',
            'البنك/طريقة السداد' => 'g348',
            'مرجع الخزينة/المالية' => 'treasury_ref',
            'حالة المطابقة' => 'g349',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_payment_order');
        echo ems_w14_grid('emsList_fin_orders', $GUIDE_COLS, $__gridRows, $D, 'لا امر دفع مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد أوامر الدفع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "approved") ?></div><div class="ems-stat-label">معتمدة بانتظار التنفيذ</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "executed") ?></div><div class="ems-stat-label">منفذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "executed_amount")) ?></div><div class="ems-stat-label">اجمالي المنفذ</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أوامر دفع مسجلة', 'امر الدفع يطلب ويعتمد وينفذ بيدين لا بيد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_payment_orders')); ?>
    <table id="emsList_fin_payment_orders" class="data-table">
        <thead><tr><th>كود أمر الدفع</th><th>الطبقة</th><th>العملية</th><th>الممول</th><th>تاريخ الطلب</th><th>الطالب</th><th>المبلغ المطلوب</th><th>المبلغ المعتمد</th><th>المعتمد</th><th>تاريخ التنفيذ</th><th>المبلغ المنفذ</th><th>طريقة السداد</th><th>المرجع البنكي</th><th>مرجع الخزينة</th><th>طلب الاعتراف</th><th>حالة المطابقة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["order_code"]) ?></td>
                    <td><?= ems_w12_state((string) $r["source_kind"]) ?></td>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= htmlspecialchars((string) $r["requested_at"]) ?></td>
                    <td><?= (int) $r["requested_by"] ?></td>
                    <td><?= ems_w12_num($r["requested_amount"]) ?></td>
                    <td><?= ems_w12_num($r["approved_amount"]) ?></td>
                    <td><?= (int) $r["approved_by"] ?></td>
                    <td><?= htmlspecialchars((string) $r["executed_on"]) ?></td>
                    <td><?= ems_w12_num($r["executed_amount"]) ?></td>
                    <td><?= ems_w12_state((string) $r["method"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["bank_ref"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["treasury_ref"]) ?></td>
                    <td><?= (int) $r["recognition_request_id"] ?></td>
                    <td><?= ems_w12_state((string) $r["match_state"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

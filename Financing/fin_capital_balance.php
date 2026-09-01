<?php
/**
 * Financing/fin_capital_balance.php — رصيد رأس المال والعائد (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * سطر رصيد حي واحد لعملية — مشتق كليا من الاقفال التعاقدي وما خصص عليه.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-20)
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

$perms = w12_perms($conn, 'Financing/fin_capital_balance.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_contract_close',
                 array('orderBy' => 'op_id, contract_period_no DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | رصيد رأس المال والعائد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'رصيد رأس المال والعائد'; $header_icon = 'fa fa-chart-line'; $header_actions = array();
    $header_back = array('href' => 'fin_payment_allocation.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تخصيص السداد على الأقساط');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> رصيد راس المال والعائد بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'كود العملية' => 'g365',
            'كود الممول' => 'g366',
            'اسم الممول (بحث)' => 'g367',
            'العملة' => 'g368',
            'رأس المال المعتمد' => 'g369',
            'المسدد من الأصل' => 'g370',
            'المتبقي من الأصل' => 'g371',
            'العائد التعاقدي' => 'g372',
            'المدفوع للعائد' => 'g373',
            'المتبقي من العائد' => 'g374',
            'إجمالي المدفوع (الدفتر)' => 'g375',
            'إجمالي الالتزام المتبقي' => 'g376',
            'آخر حركة' => 'g377',
            'الحالة المالية' => 'g378',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_capital_balance');
        echo ems_w14_grid('emsList_fin_capital', $GUIDE_COLS, $__gridRows, $D, 'لا رصيد مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_distinct($rows, "op_id") ?></div><div class="ems-stat-label">عمليات لها رصيد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "close_principal")) ?></div><div class="ems-stat-label">اجمالي أصل قائم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "close_profit")) ?></div><div class="ems-stat-label">اجمالي عائد قائم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_num(ems_w12_sumf($rows, "allocated_paid")) ?></div><div class="ems-stat-label">اجمالي المخصص</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أرصدة قائمة', 'الرصيد يشتق من الاقفالات التعاقدية ولا يكتب بيد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_capital_balance')); ?>
    <table id="emsList_fin_capital_balance" class="data-table">
        <thead><tr><th>العملية</th><th>الممول</th><th>الفترة التعاقدية</th><th>أصل افتتاحي</th><th>أصل مستحق</th><th>المخصص</th><th>أصل قائم</th><th>عائد قائم</th><th>العملة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= (int) $r["contract_period_no"] ?></td>
                    <td><?= ems_w12_num($r["open_principal"]) ?></td>
                    <td><?= ems_w12_num($r["due_principal"]) ?></td>
                    <td><?= ems_w12_num($r["allocated_paid"]) ?></td>
                    <td><?= ems_w12_num($r["close_principal"]) ?></td>
                    <td><?= ems_w12_num($r["close_profit"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

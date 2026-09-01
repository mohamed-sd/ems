<?php
/**
 * Financing/fin_contract_close.php — الإقفالات التعاقدية (RPR-W12)
 * ───────────────────────────────────────────────────────────────────────────
 * اقفال تعاقدي واحد: ممول وعملية وفترة تعاقدية — كيان مستقل لا حالة للشهري.
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في `FinancingCycleService`
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. (‏المتطلَّب FIN-15)
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

$perms = w12_perms($conn, 'Financing/fin_contract_close.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w12_rows($is_super, 'fin_contract_close',
                 array('orderBy' => 'op_id, contract_period_no', 'limit' => 500));

$page_title = 'إيكوبيشن | الإقفالات التعاقدية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإقفالات التعاقدية'; $header_icon = 'fa fa-file-invoice'; $header_actions = array();
    $header_back = array('href' => 'installments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الأقساط ومواعيد السداد');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> الاقفالات التعاقدية بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'Close_ID' => 'g269',
            'FOP_ID' => 'g270',
            'FCON_ID' => 'close_code',
            'Financier_ID' => 'g271',
            'العملة' => 'g272',
            'نوع الفترة' => 'g273',
            'بداية الفترة' => 'g274',
            'نهاية الفترة' => 'g275',
            'شهر نهاية الفترة (وسم)' => 'g276',
            'Monthly_Close_ID' => 'g277',
            'رصيد أصل افتتاحي' => 'g278',
            'رصيد عائد افتتاحي' => 'g279',
            'أصل مستحق بالفترة' => 'g280',
            'عائد مستحق بالفترة' => 'g281',
            'إجمالي مستحق الفترة' => 'g282',
            'رسوم مستحقة' => 'g283',
            'تعديلات معتمدة ±' => 'g284',
            'مدفوعات مخصصة للفترة' => 'g285',
            'رصيد أصل ختامي' => 'g286',
            'رصيد عائد ختامي' => 'g287',
            'إجمالي الرصيد الختامي' => 'g288',
            'المتأخر من الفترة' => 'g289',
            'أيام التأخير' => 'g290',
            'الاستحقاق التالي' => 'g291',
            'حالة الإقفال' => 'g292',
            'المنشئ' => 'g293',
            'المراجع' => 'g294',
            'المعتمد' => 'g295',
            'تاريخ الاعتماد' => 'g296',
            'مرجع كشف الحساب' => 'g297',
            'حالة البيانات' => 'g298',
            'ملاحظات' => 'g299',
            'اختبار الترحيل (Opening=Closing السابق)' => 'g300',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('fin_contract_close');
        echo ems_w14_grid('emsList_fin_cclose', $GUIDE_COLS, $__gridRows, $D, 'لا اقفال تعاقدي مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الاقفالات التعاقدية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "state", "approved") ?></div><div class="ems-stat-label">معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_count($rows, "rollforward_ok", "1") ?></div><div class="ems-stat-label">ترحيل رصيد سليم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w12_nonzero($rows, "arrears_amount") ?></div><div class="ems-stat-label">اقفالات بمتأخر</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا اقفالات تعاقدية', 'الاقفال التعاقدي يقرا منفصلا عن الشهري وعن النهائي'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_fin_contract_close')); ?>
    <table id="emsList_fin_contract_close" class="data-table">
        <thead><tr><th>كود الاقفال</th><th>العملية</th><th>الممول</th><th>رقم الفترة التعاقدية</th><th>بداية الفترة</th><th>نهاية الفترة</th><th>اصل افتتاحي</th><th>عائد افتتاحي</th><th>اصل مستحق</th><th>عائد مستحق</th><th>المخصص للفترة</th><th>اصل ختامي</th><th>عائد ختامي</th><th>المتأخر</th><th>أيام التأخير</th><th>العملة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["close_code"]) ?></td>
                    <td><?= (int) $r["op_id"] ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= (int) $r["contract_period_no"] ?></td>
                    <td><?= htmlspecialchars((string) $r["period_start"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["period_end"]) ?></td>
                    <td><?= ems_w12_num($r["open_principal"]) ?></td>
                    <td><?= ems_w12_num($r["open_profit"]) ?></td>
                    <td><?= ems_w12_num($r["due_principal"]) ?></td>
                    <td><?= ems_w12_num($r["due_profit"]) ?></td>
                    <td><?= ems_w12_num($r["allocated_paid"]) ?></td>
                    <td><?= ems_w12_num($r["close_principal"]) ?></td>
                    <td><?= ems_w12_num($r["close_profit"]) ?></td>
                    <td><?= ems_w12_num($r["arrears_amount"]) ?></td>
                    <td><?= (int) $r["arrears_days"] ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= ems_w12_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>

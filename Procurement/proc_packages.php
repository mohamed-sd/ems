<?php
/**
 * Procurement/proc_packages.php — تجميع الطلبات وخطة الشراء (RPR-W09 · PRC-04 · PRC-05)
 * ───────────────────────────────────────────────────────────────────────────
 * **«حزمةُ شراءٍ × فترة — حزمةٌ واحدة»** (`PRC-04` نصًّا)، و**أعضاؤها جدولٌ
 * وسيطٌ لا حقلٌ تجميعيّ** (`PRC-05`). فالطلبُ المضمومُ سطرٌ بمُعرِّفِه وسببِ ضمِّه،
 * ولذلك `uq_pkg_req` يمنع ضمَّ طلبٍ إلى حزمتَين — **والمنعُ في المخطَّطِ لا في النيّة**.
 *
 * و`عدد الطلبات` و`عدد البنود` **عمودانِ مشتقّانِ** يعيد `recomputePackage`
 * بناءَهما، والبوّابةُ `W9-12` تقارن المخزَّنَ بالمقيس.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/proc_packages.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('proc_packages super') : ems_tenant_db();
$pick = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $_GET['state']) : '';
$open = isset($_GET['pkg']) ? (int) $_GET['pkg'] : 0;

$rows = array(); $choices = array(); $members = array(); $reqs = array();
try {
    $opts = array('orderBy' => 'id DESC', 'limit' => 300);
    if ($pick !== '') { $opts['where'] = array('state' => $pick); }
    $rows = $gate->select('proc_package', $opts);
} catch (\Throwable $t) { error_log('proc_packages list: ' . $t->getMessage()); }
foreach ($rows as $r) { if ((string) $r['state'] !== '') { $choices[(string) $r['state']] = true; } }
if ($open > 0) {
    try { $members = $gate->select('proc_package_member', array('where' => array('package_id' => $open), 'orderBy' => 'id')); }
    catch (\Throwable $t) { error_log('proc_packages members: ' . $t->getMessage()); }
    try { foreach ($gate->select('proc_request', array('columns' => array('id', 'code', 'requesting_dept'), 'limit' => 900)) as $q) { $reqs[(int) $q['id']] = $q; } }
    catch (\Throwable $t) { error_log('proc_packages reqs: ' . $t->getMessage()); }
}
$totalMembers = 0; $totalLines = 0;
foreach ($rows as $r) { $totalMembers += (int) $r['member_count']; $totalLines += (int) $r['line_count']; }

$page_title = 'إيكوبيشن | تجميع الطلبات وخطة الشراء';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تجميع الطلبات وخطة الشراء'; $header_icon = 'fa fa-layer-group'; $header_actions = array();
    $header_back = array('href' => 'requests_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'طلبات الشراء');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_prc_proc_packages
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الحزمة' => 'g64',
            'فترة التجميع' => 'g65',
            'نطاق الحزمة' => 'g66',
            'الطلبات المضمومة' => 'g67',
            'عدد البنود' => 'g68',
            'التقدير الإجمالي' => 'g69',
            'مبرر التمرير المنفرد' => 'g70',
            'قناة الشراء' => 'g71',
            'حالة الحزمة' => 'g72',
            'المنشئ' => 'g73',
            'تاريخ الإنشاء' => 'g74',
            'حالة البيانات' => 'g75',
            'مرجع المصدر' => 'g76',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('prc_proc_packages');
        echo ems_w14_grid('emsList_prc_proc_packages', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تجميع الطلبات وخطة الشراء'); /* /GUIDE_COLS */ ?>
    <?php /* ④ نموذجُ الإضافةِ — **مشتقٌّ من الدليلِ لا مكتوب** (SILENT_DROP_FIX §2·2-④)
         حقولُه من `repair01_fields` وأعمدتُه من `$GUIDE_COLS` أعلاه،
         ⛔ ولا اسمَ حقلٍ يُكتب هنا — والقابلُ للإدخالِ ثلاثةُ أصنافٍ لا غير. */
    require_once __DIR__ . '/../includes/w14_guide_form.php';
    ems_w14_guide_form(array(
        'surfaces' => array('تجميع الطلبات وخطه الشراء', 'أعضاء حزمة التجميع'),
        'table'    => 'prc_proc_packages',
        'cols'     => $GUIDE_COLS,
        'screen'   => 'Procurement/proc_packages.php',
    )); ?>

    </div></div></div>
    <?php  ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <table id="emsList_prc_packages"></table>
    </div></div></div>
    <?php  ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <table id="emsList_prc_package_lines"></table>
    </div></div></div>
    <?php  ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_prc_package_lines
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف العضوية' => 'g157',
            'معرف الحزمة' => 'g158',
            'رقم طلب الشراء' => 'g159',
            'بنود الطلب المشمولة' => 'g160',
            'تاريخ الضم' => 'g161',
            'حالة العضوية' => 'g162',
            'المنشئ' => 'g163',
            'تاريخ الإنشاء' => 'g164',
            'حالة البيانات' => 'g165',
            'مرجع المصدر' => 'g166',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('prc_package_lines');
        echo ems_w14_grid('emsList_prc_package_lines', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في تجميع الطلبات وخطة الشراء'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">حزم التجميع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $totalMembers ?></div><div class="ems-stat-label">طلبات مضمومة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $totalLines ?></div><div class="ems-stat-label">بنود داخل الحزم</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_pkg_st">حالة الحزمة</label><select name="state" id="w9_pkg_st" onchange="this.form.submit()">
            <option value="">الكل</option>
            <?php foreach (array_keys($choices) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $pick === $c ? 'selected' : '' ?>><?= htmlspecialchars(ems_w7_ar($c, $conn)) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا حزم تجميع', 'الحزمة تضم طلبات شراء لفترة واحدة بسبب مكتوب. والطلب لا يضم إلى حزمتين'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز الحزمة</th><th>العنوان</th><th>من</th><th>إلى</th><th>سبب التجميع</th><th>الطلبات</th><th>البنود</th><th>الحالة</th><th>الأعضاء</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['code']) ?></td>
                <td><?= htmlspecialchars((string) $r['title']) ?></td>
                <td><?= htmlspecialchars((string) $r['period_from']) ?></td>
                <td><?= htmlspecialchars((string) $r['period_to']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['strategy'], $conn)) ?></td>
                <td><?= (int) $r['member_count'] ?></td>
                <td><?= (int) $r['line_count'] ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['state'], $conn)) ?></td>
                <td><a href="?pkg=<?= (int) $r['id'] ?>">عرض الأعضاء</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <?php if ($open > 0): ?>
    <h3 class="ems-section-title">أعضاء الحزمة</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رمز الطلب</th><th>الجهة الطالبة</th><th>سبب الضم</th><th>تاريخ الضم</th></tr></thead>
        <tbody>
        <?php if ($members): foreach ($members as $m): $q = isset($reqs[(int) $m['request_id']]) ? $reqs[(int) $m['request_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars($q ? (string) $q['code'] : ('#' . (int) $m['request_id'])) ?></td>
                <td><?= htmlspecialchars($q ? (string) $q['requesting_dept'] : '') ?></td>
                <td><?= htmlspecialchars((string) $m['join_reason']) ?></td>
                <td><?= htmlspecialchars((string) $m['joined_at']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="4">لا طلبات مضمومة في هذه الحزمة</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
</body></html>

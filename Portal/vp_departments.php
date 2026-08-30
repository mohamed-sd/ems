<?php
/**
 * Portal/vp_departments.php — الإدارات: نطاقي والشركة (VP-02)
 * ───────────────────────────────────────────────────────────────────────────
 * **بطاقةُ متابعةٍ لكلِّ إدارة** (‏`VP-02` نصًّا: «Visibility أوسعُ من
 * Authority وظيفةً لا وصفًا: Toggle صريحٌ My Scope / All Departments») —
 * Grain: **إدارةٌ × وضعُ العرض**.
 *
 * ◆ **النطاقُ من سجلِّ التكليفاتِ لا من افتراض**: نطاقُ النائبِ = الوحداتُ
 *   التنظيميّةُ التي يقوم فيها نائبًا (`org_assignments.deputy_person_id`
 *   = شخصُ المستخدمِ و`state='active'`) — وصفتُه أسماءُ أنواعِ التكليفِ
 *   التي ينوب عنها. وخارجُ النطاقِ يُعرَض **قراءةً فقط** ويُوسَم.
 * ◆ **كلُّ خانةِ حالةٍ من مصدرٍ منسوبٍ للإدارةِ بالاسمِ — أو تصرِّح
 *   بغيابِه**: الطلباتُ المعلّقةُ من بوّابةِ الطلباتِ الماليّةِ بوحدةِ
 *   مصدرِها · المخاطرُ الحرجةُ من سجلِّ المخاطرِ بوحدةِ مالكِها · السقفُ
 *   من سقوفِ الإدارات · الالتزامُ من سياساتِ الإدارةِ الساريةِ · المؤشِّراتُ
 *   الدوريّةُ لمن له سجلُّ مؤشِّراتٍ (الصيانةُ والنقلُ والماليّة) — وما لا
 *   عمودَ مصدرٍ له بعدُ (التقريرُ اليوميُّ والإقفالُ الشهريُّ لغيرِ
 *   الماليّة) يقول ذلك ولا يُختلَق له رقم.
 * ◆ قراءةٌ صِرفٌ `select` معزولًا والتجميعُ في الذاكرة — صفرُ حرفِ SQL
 *   على جدولِ مستأجِرٍ في الملفّ (GAP-29).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/vp_departments.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('vp departments super') : ems_tenant_db();
$mode = isset($_GET['mode']) && (string) $_GET['mode'] === 'all' ? 'all' : 'my';

/* اليومُ من ساعةِ القاعدةِ (VT-07) */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

/* شخصُ المستخدمِ ونطاقُ نيابتِه من سجلِّ التكليفات */
$myEmp = isset($_SESSION['user']['employee_id']) ? intval($_SESSION['user']['employee_id']) : 0;
$myUnits = array(); $myKinds = array(); $typeNames = array();
try { foreach ($gate->select('org_assignment_types', array('columns' => array('type_code', 'name_ar'), 'limit' => 100)) as $t0) { $typeNames[(string) $t0['type_code']] = (string) $t0['name_ar']; } }
catch (\Throwable $t) { error_log('vp_departments types: ' . $t->getMessage()); }
try {
    foreach ($gate->select('org_assignments', array('columns' => array('deputy_person_id', 'org_unit_id', 'assignment_type_code', 'state'), 'limit' => 2000)) as $a0) {
        if ((int) $a0['deputy_person_id'] !== $myEmp || $myEmp <= 0) { continue; }
        if ((string) $a0['state'] !== 'active') { continue; }
        $myUnits[(int) $a0['org_unit_id']] = true;
        $tc = (string) $a0['assignment_type_code'];
        $myKinds[isset($typeNames[$tc]) ? $typeNames[$tc] : $tc] = true;
    }
} catch (\Throwable $t) { error_log('vp_departments asg: ' . $t->getMessage()); }
$deputyRole = $myKinds ? ('نائب عن ' . implode(' و', array_keys($myKinds))) : 'ليس نائبا مسجلا في سجل التكليفات';

/* الوحداتُ والمصادرُ المنسوبةُ للإدارة */
$units = array(); $reqPend = array(); $riskCrit = array(); $caps = array(); $polAct = array(); $kpiN = array();
try { $units = $gate->select('org_units', array('orderBy' => 'unit_id ASC', 'limit' => 200)); } catch (\Throwable $t) { error_log('vp_departments units: ' . $t->getMessage()); }
try {
    foreach ($gate->select('fin_requests', array('columns' => array('source_module', 'state'), 'limit' => 5000)) as $q0) {
        $st = (string) $q0['state'];
        if ($st !== 'draft' && $st !== 'under_review' && $st !== 'pending_approval') { continue; }
        $m0 = (string) $q0['source_module'];
        $reqPend[$m0] = (isset($reqPend[$m0]) ? $reqPend[$m0] : 0) + 1;
    }
} catch (\Throwable $t) { error_log('vp_departments req: ' . $t->getMessage()); }
try {
    foreach ($gate->select('risk_register', array('columns' => array('owner_unit_id', 'current_level', 'state'), 'limit' => 5000)) as $k0) {
        if ((string) $k0['current_level'] !== 'حرج') { continue; }
        $u0 = (int) $k0['owner_unit_id'];
        $riskCrit[$u0] = (isset($riskCrit[$u0]) ? $riskCrit[$u0] : 0) + 1;
    }
} catch (\Throwable $t) { error_log('vp_departments risk: ' . $t->getMessage()); }
try {
    foreach ($gate->select('exec_dept_caps', array('limit' => 2000)) as $c0) {
        $ef = (string) $c0['effective_from']; $et = (string) $c0['effective_to'];
        if ($ef !== '' && $ef !== null && $ef > $today) { continue; }
        if ($et !== '' && $et !== null && $et < $today) { continue; }
        $caps[(string) $c0['dept_name']] = number_format((float) $c0['cap_amount'], 0) . ' ' . (string) $c0['currency'];
    }
} catch (\Throwable $t) { error_log('vp_departments caps: ' . $t->getMessage()); }
try {
    foreach ($gate->select('dept_policies', array('columns' => array('scope_type', 'scope_id', 'state'), 'limit' => 2000)) as $p0) {
        if ((string) $p0['scope_type'] !== 'department') { continue; }
        if ((string) $p0['state'] !== 'active' && (string) $p0['state'] !== 'approved') { continue; }
        $u0 = (int) $p0['scope_id'];
        $polAct[$u0] = (isset($polAct[$u0]) ? $polAct[$u0] : 0) + 1;
    }
} catch (\Throwable $t) { error_log('vp_departments pol: ' . $t->getMessage()); }
/* المؤشِّراتُ الدوريّةُ لمن له سجلٌّ باسمِه — عدُّ صفوفٍ لا اختلاقُ قيمة */
foreach (array('maintenance' => 'mnt_kpi_period', 'transport' => 'trp_kpi_period', 'finance' => 'fin_quality_kpis') as $uc0 => $tb0) {
    try { $kpiN[$uc0] = count($gate->select($tb0, array('columns' => array('id'), 'limit' => 2000))); }
    catch (\Throwable $t) { $kpiN[$uc0] = null; error_log('vp_departments kpi ' . $uc0 . ': ' . $t->getMessage()); }
}
/* جسرُ وحدةِ المصدرِ في بوّابةِ الطلبات الى رمزِ الإدارة */
$REQMAP = array('sales' => 'sales', 'suppliers' => 'suppliers', 'maintenance' => 'maintenance', 'transport' => 'transport',
                'tickets' => 'tickets', 'movement' => 'movement', 'warehouse' => 'warehouse', 'procurement' => 'procurement_ops',
                'workforce' => 'operators', 'treasury' => 'finance', 'revenue' => 'finance', 'assets' => 'fleet');

$unitName = array();
foreach ($units as $u0) { $unitName[(int) $u0['unit_id']] = (string) $u0['name_ar']; }
$LAYER = array('operational' => 'تشغيلية', 'parallel' => 'موازية', 'oversight' => 'رقابية');

$rows = array(); $nAll = 0; $nMine = 0; $nCritTot = 0; $nCapped = 0;
foreach ($units as $u0) {
    if ((int) $u0['active'] !== 1) { continue; }
    $uid = (int) $u0['unit_id']; $uc = (string) $u0['unit_code']; $nm = (string) $u0['name_ar'];
    $inScope = isset($myUnits[$uid]);
    $nAll++; if ($inScope) { $nMine++; }
    $crit = isset($riskCrit[$uid]) ? $riskCrit[$uid] : 0; $nCritTot += $crit;
    $cap = isset($caps[$nm]) ? $caps[$nm] : 'بلا سقف سار';
    if (isset($caps[$nm])) { $nCapped++; }
    $pend = 0;
    foreach ($REQMAP as $m0 => $c0) { if ($c0 === $uc && isset($reqPend[$m0])) { $pend += $reqPend[$m0]; } }
    $lay = isset($LAYER[(string) $u0['layer']]) ? $LAYER[(string) $u0['layer']] : (string) $u0['layer'];
    $par = $u0['parent_unit_id'] !== null && isset($unitName[(int) $u0['parent_unit_id']])
         ? ('تتبع ' . $unitName[(int) $u0['parent_unit_id']]) : 'تتبع القمة مباشرة';
    if ($mode === 'my' && !$inScope) { continue; }
    $rows[] = array(
        'uid'    => 'بطاقة ادارة رقم ' . $uid,
        'name'   => $nm,
        'lay'    => $lay . '، ' . $par,
        'scope'  => $inScope ? 'نعم، ضمن نطاقي' : 'لا',
        'ro'     => $inScope ? 'قراءة وتصرف بحسب الصلاحية' : 'خارج النطاق، قراءة فقط',
        'kpi'    => isset($kpiN[$uc]) && $kpiN[$uc] !== null ? ($kpiN[$uc] . ' مؤشر دوري بسجلها') : 'بلا سجل مؤشرات باسمها بعد',
        'pend'   => $pend > 0 ? ($pend . ' طلبات معلقة ببوابة الطلبات') : 'لا معلق ببوابة الطلبات',
        'over'   => 'سجل الالتزامات المستحقة فارغ الان',
        'crit'   => $crit > 0 ? ($crit . ' مخاطر حرجة بملكيتها') : 'لا خطر حرجا بملكيتها',
        'budget' => $cap,
        'daily'  => 'بلا مصدر منسوب للادارة بعد',
        'close'  => $uc === 'finance' ? 'سجل الاقفال الشهري المالي فارغ الان' : 'بلا مصدر منسوب للادارة بعد',
        'comp'   => isset($polAct[$uid]) ? ($polAct[$uid] . ' سياسات سارية عليها') : 'لا سياسة سارية مسجلة',
        'link'   => 'dept_board.php',
    );
}

$page_title = 'إيكوبيشن | الإدارات نطاقي والشركة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الادارات نطاقي والشركة: بطاقة متابعة لكل ادارة والرؤية اوسع من الصلاحية'; $header_icon = 'fa fa-sitemap'; $header_actions = array();
    $header_back = array('href' => 'dept_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الادارات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAll) ?></div><div class="ems-stat-label">ادارات الشركة النشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nMine) ?></div><div class="ems-stat-label">ضمن نطاق نيابتي المسجل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nCritTot) ?></div><div class="ems-stat-label">مخاطر حرجة مملوكة للادارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nCapped) ?></div><div class="ems-stat-label">ادارات بسقف اعتماد سار</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>وضع العرض</label>
                <select name="mode" onchange="this.form.submit()">
                    <option value="my" <?= $mode === 'my' ? 'selected' : '' ?>>نطاقي</option>
                    <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>كل الادارات قراءة</option>
                </select>
            </div>
            <div class="ems-filter-item">
                <label>صفة النيابة</label>
                <input type="text" aria-label="صفة النيابة" value="<?= htmlspecialchars($deputyRole) ?>" readonly>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>معرف البطاقة</th>
                    <th>الادارة</th>
                    <th>التبعية التنظيمية</th>
                    <th>ضمن نطاقي؟</th>
                    <th>حد التصرف</th>
                    <th>المؤشرات الدورية</th>
                    <th>الطلبات المعلقة</th>
                    <th>الاجراءات المتأخرة</th>
                    <th>المخاطر الحرجة</th>
                    <th>سقف الاعتماد الساري</th>
                    <th>التقرير اليومي</th>
                    <th>الاقفال الشهري</th>
                    <th>الالتزام والسياسات</th>
                    <th>النزول للتفصيل</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['uid']) ?></td>
                    <td><?= htmlspecialchars($x0['name']) ?></td>
                    <td><?= htmlspecialchars($x0['lay']) ?></td>
                    <td><?= htmlspecialchars($x0['scope']) ?></td>
                    <td><?= htmlspecialchars($x0['ro']) ?></td>
                    <td><?= htmlspecialchars($x0['kpi']) ?></td>
                    <td><?= htmlspecialchars($x0['pend']) ?></td>
                    <td><?= htmlspecialchars($x0['over']) ?></td>
                    <td><?= htmlspecialchars($x0['crit']) ?></td>
                    <td><?= htmlspecialchars($x0['budget']) ?></td>
                    <td><?= htmlspecialchars($x0['daily']) ?></td>
                    <td><?= htmlspecialchars($x0['close']) ?></td>
                    <td><?= htmlspecialchars($x0['comp']) ?></td>
                    <td><a href="<?= htmlspecialchars($x0['link']) ?>">لوحة الادارات</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="14"><?= $mode === 'my' ? 'لا ادارة ضمن نطاق نيابتك المسجل في سجل التكليفات. بدل وضع العرض الى كل الادارات قراءة' : 'لا ادارات نشطة مسجلة' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>الرؤية اوسع من الصلاحية وظيفة لا وصفا:</strong>
        النطاق من سجل التكليفات، الوحدات التي تنوب فيها بصفة سارية، وما خرج عنه يعرض قراءة فقط.
        كل خانة حالة تسمي مصدرها المنسوب للادارة، الطلبات من بوابة الطلبات المالية بوحدة مصدرها،
        والمخاطر الحرجة من سجل المخاطر بوحدة مالكها، والسقف من سقوف الادارات السارية اليوم،
        والالتزام من سياسات الادارة السارية. وما لا مصدر له منسوبا بعد يصرح بذلك ولا يختلق له رقم.
        قراءة صرف ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>

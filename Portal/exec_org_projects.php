<?php
/**
 * Portal/exec_org_projects.php — الإداراتُ والمشروعات (CEO-02)
 * ───────────────────────────────────────────────────────────────────────────
 * **بطاقةُ حالةٍ واحدةٌ قابلةٌ للنزول** (‏`CEO-02`) — Grain:
 * **إدارةٌ/مشروعٌ — بطاقة**، والنوعُ مفتاحُ العرض.
 *
 * ◆ بطاقةُ الإدارةِ من `org_units` النشطةِ وروافدِها المنسوبةِ بالاسم
 *   (نمطُ `VP-02` نفسُه): المخاطرُ الحرجةُ بوحدةِ مالكِها · السقفُ الساري
 *   بيومِ القاعدةِ · السياساتُ الساريةُ · الطلباتُ المعلّقةُ بوحدةِ مصدرِها.
 * ◆ بطاقةُ المشروعِ من سجلِّ المشروعاتِ (‏`project`) وروافدِه المنسوبةِ
 *   بمعرِّفِه: الطلباتُ المعلّقةُ (`fin_requests.project_id`) · آخرُ يومِ
 *   موقعٍ (‏`site_day`) · والمسؤولُ من التكليفاتِ حيث سُجِّل.
 * ◆ **وما لا مصدرَ له منسوبًا يقول ذلك ولا يُختلَق له رقم** — ورأسُ كلِّ
 *   خانةٍ يسمّي مصدرَها في الحاشية. قراءةٌ صِرفٌ `select` معزولًا
 *   والتجميعُ في الذاكرة (GAP-29) — صفرُ POST.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/exec_org_projects.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('exec org projects super') : ems_tenant_db();
$kindQ = isset($_GET['kind']) ? (string) $_GET['kind'] : '';

/* اليومُ من ساعةِ القاعدةِ (VT-07) */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

$units = array(); $projects = array(); $emp = array();
try { $units = $gate->select('org_units', array('orderBy' => 'unit_id ASC', 'limit' => 200)); } catch (\Throwable $t) { error_log('exec_orgp units: ' . $t->getMessage()); }
try { $projects = $gate->select('project', array('orderBy' => 'id ASC', 'limit' => 500)); } catch (\Throwable $t) { error_log('exec_orgp proj: ' . $t->getMessage()); }
try { foreach ($gate->select('employees', array('columns' => array('id', 'name'), 'limit' => 3000)) as $e0) { $emp[(int) $e0['id']] = (string) $e0['name']; } }
catch (\Throwable $t) { error_log('exec_orgp emp: ' . $t->getMessage()); }

/* رؤساءُ الوحداتِ من التكليفاتِ الساريةِ لأنواعِ رأسِ الوحدة */
$headTypes = array(); $heads = array();
try { foreach ($gate->select('org_assignment_types', array('columns' => array('type_code', 'is_unit_head'), 'limit' => 100)) as $t0) { if ((int) $t0['is_unit_head'] === 1) { $headTypes[(string) $t0['type_code']] = true; } } }
catch (\Throwable $t) { error_log('exec_orgp types: ' . $t->getMessage()); }
try {
    foreach ($gate->select('org_assignments', array('columns' => array('person_id', 'assignment_type_code', 'org_unit_id', 'state'), 'limit' => 2000)) as $a0) {
        if ((string) $a0['state'] !== 'active') { continue; }
        if (!isset($headTypes[(string) $a0['assignment_type_code']])) { continue; }
        $u0 = (int) $a0['org_unit_id'];
        if (!isset($heads[$u0])) { $heads[$u0] = (int) $a0['person_id']; }
    }
} catch (\Throwable $t) { error_log('exec_orgp heads: ' . $t->getMessage()); }

/* الروافدُ المنسوبةُ — كما في بطاقةِ VP-02 */
$reqPendMod = array(); $reqPendProj = array(); $riskCrit = array(); $caps = array(); $polAct = array();
try {
    foreach ($gate->select('fin_requests', array('columns' => array('source_module', 'project_id', 'state'), 'limit' => 5000)) as $q0) {
        $st = (string) $q0['state'];
        if ($st !== 'draft' && $st !== 'under_review' && $st !== 'pending_approval') { continue; }
        $m0 = (string) $q0['source_module'];
        $reqPendMod[$m0] = (isset($reqPendMod[$m0]) ? $reqPendMod[$m0] : 0) + 1;
        $pj = (int) $q0['project_id'];
        if ($pj > 0) { $reqPendProj[$pj] = (isset($reqPendProj[$pj]) ? $reqPendProj[$pj] : 0) + 1; }
    }
} catch (\Throwable $t) { error_log('exec_orgp req: ' . $t->getMessage()); }
try {
    foreach ($gate->select('risk_register', array('columns' => array('owner_unit_id', 'current_level'), 'limit' => 5000)) as $k0) {
        if ((string) $k0['current_level'] !== 'حرج') { continue; }
        $u0 = (int) $k0['owner_unit_id'];
        $riskCrit[$u0] = (isset($riskCrit[$u0]) ? $riskCrit[$u0] : 0) + 1;
    }
} catch (\Throwable $t) { error_log('exec_orgp risk: ' . $t->getMessage()); }
try {
    foreach ($gate->select('exec_dept_caps', array('limit' => 2000)) as $c0) {
        $ef = (string) $c0['effective_from']; $et = (string) $c0['effective_to'];
        if ($ef !== '' && $ef !== null && $ef > $today) { continue; }
        if ($et !== '' && $et !== null && $et < $today) { continue; }
        $caps[(string) $c0['dept_name']] = number_format((float) $c0['cap_amount'], 0) . ' ' . (string) $c0['currency'];
    }
} catch (\Throwable $t) { error_log('exec_orgp caps: ' . $t->getMessage()); }
try {
    foreach ($gate->select('dept_policies', array('columns' => array('scope_type', 'scope_id', 'state'), 'limit' => 2000)) as $p0) {
        if ((string) $p0['scope_type'] !== 'department') { continue; }
        if ((string) $p0['state'] !== 'active' && (string) $p0['state'] !== 'approved') { continue; }
        $u0 = (int) $p0['scope_id'];
        $polAct[$u0] = (isset($polAct[$u0]) ? $polAct[$u0] : 0) + 1;
    }
} catch (\Throwable $t) { error_log('exec_orgp pol: ' . $t->getMessage()); }
$lastDay = array();
try {
    foreach ($gate->select('site_day', array('columns' => array('project_id', 'day_date'), 'orderBy' => 'day_date ASC', 'limit' => 5000)) as $d0) {
        $lastDay[(int) $d0['project_id']] = (string) $d0['day_date'];
    }
} catch (\Throwable $t) { error_log('exec_orgp days: ' . $t->getMessage()); }
$REQMAP = array('sales' => 'sales', 'suppliers' => 'suppliers', 'maintenance' => 'maintenance', 'transport' => 'transport',
                'tickets' => 'tickets', 'movement' => 'movement', 'warehouse' => 'warehouse', 'procurement' => 'procurement_ops',
                'workforce' => 'operators', 'treasury' => 'finance', 'revenue' => 'finance', 'assets' => 'fleet');
$LAYER = array('operational' => 'تشغيلية', 'parallel' => 'موازية', 'oversight' => 'رقابية');

$cards = array(); $nD = 0; $nP = 0; $nCrit = 0;
foreach ($units as $u0) {
    if ((int) $u0['active'] !== 1) { continue; }
    $uid = (int) $u0['unit_id']; $uc = (string) $u0['unit_code']; $nm = (string) $u0['name_ar'];
    $nD++;
    $crit = isset($riskCrit[$uid]) ? $riskCrit[$uid] : 0; $nCrit += $crit;
    $pend = 0;
    foreach ($REQMAP as $m0 => $c0) { if ($c0 === $uc && isset($reqPendMod[$m0])) { $pend += $reqPendMod[$m0]; } }
    $hid = isset($heads[$uid]) ? $heads[$uid] : 0;
    $cards[] = array(
        'kind'  => 'ادارة', 'kkey' => 'dept',
        'ref'   => 'ادارة رقم ' . $uid,
        'name'  => $nm,
        'lay'   => isset($LAYER[(string) $u0['layer']]) ? $LAYER[(string) $u0['layer']] : (string) $u0['layer'],
        'head'  => $hid > 0 ? (isset($emp[$hid]) ? $emp[$hid] : ('موظف رقم ' . $hid)) : 'بلا رأس وحدة مسجل',
        'stat'  => $crit > 0 ? 'فيها خطر حرج' : 'مستقرة بمقاييسها المنسوبة',
        'pend'  => $pend > 0 ? ($pend . ' معلقة ببوابة الطلبات') : 'لا معلق',
        'crit'  => $crit > 0 ? ($crit . ' حرجة بملكيتها') : 'لا حرج',
        'budget' => isset($caps[$nm]) ? $caps[$nm] : 'بلا سقف سار',
        'comp'  => isset($polAct[$uid]) ? ($polAct[$uid] . ' سياسات سارية') : 'لا سياسة سارية',
        'last'  => 'غير منطبق للادارة',
        'link'  => 'dept_board.php',
    );
}
foreach ($projects as $p0) {
    if ((int) (isset($p0['is_deleted']) ? $p0['is_deleted'] : 0) === 1) { continue; }
    $pid = (int) $p0['id'];
    $nP++;
    $pend = isset($reqPendProj[$pid]) ? $reqPendProj[$pid] : 0;
    $cards[] = array(
        'kind'  => 'مشروع', 'kkey' => 'project',
        'ref'   => 'مشروع رقم ' . $pid,
        'name'  => (string) $p0['name'],
        'lay'   => 'عميله ' . ((string) $p0['client'] !== '' ? (string) $p0['client'] : 'غير مسجل')
                 . ' وموقعه ' . ((string) $p0['location'] !== '' ? (string) $p0['location'] : 'غير مسجل'),
        'head'  => 'من شاشة فريق العمل',
        'stat'  => (string) $p0['status'] !== '' ? (string) $p0['status'] : ((string) $p0['state'] !== '' ? (string) $p0['state'] : 'بلا حالة مسجلة'),
        'pend'  => $pend > 0 ? ($pend . ' معلقة ببوابة الطلبات') : 'لا معلق',
        'crit'  => 'سجل المخاطر بلا نسب مشروع بعد',
        'budget' => 'غير منطبق ببابه',
        'comp'  => 'غير منطبق ببابه',
        'last'  => isset($lastDay[$pid]) ? ('آخر يوم موقع ' . $lastDay[$pid]) : 'لا يوم موقع بعد',
        'link'  => '../Projects/projects.php',
    );
}

$view = array();
foreach ($cards as $x0) { if ($kindQ === '' || $x0['kkey'] === $kindQ) { $view[] = $x0; } }

$page_title = 'إيكوبيشن | الإدارات والمشروعات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الادارات والمشروعات: بطاقة حالة واحدة قابلة للنزول لكل منهما'; $header_icon = 'fa fa-diagram-project'; $header_actions = array();
    $header_back = array('href' => 'exec_command_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nD) ?></div><div class="ems-stat-label">ادارات نشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nP) ?></div><div class="ems-stat-label">مشروعات في السجل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nCrit) ?></div><div class="ems-stat-label">مخاطر حرجة مملوكة للادارات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($view)) ?></div><div class="ems-stat-label">بطاقات معروضة بالمرشح</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>نوع البطاقة</label>
                <select name="kind" onchange="this.form.submit()">
                    <option value="">الكل</option>
                    <option value="dept" <?= $kindQ === 'dept' ? 'selected' : '' ?>>الادارات</option>
                    <option value="project" <?= $kindQ === 'project' ? 'selected' : '' ?>>المشروعات</option>
                </select>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>النوع</th>
                    <th>المعرف</th>
                    <th>الاسم</th>
                    <th>التبعية او العميل والموقع</th>
                    <th>المسؤول</th>
                    <th>الحالة العامة</th>
                    <th>الطلبات المعلقة</th>
                    <th>المخاطر الحرجة</th>
                    <th>سقف الاعتماد</th>
                    <th>السياسات السارية</th>
                    <th>آخر تقرير يومي</th>
                    <th>النزول</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($view as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['name']) ?></td>
                    <td><?= htmlspecialchars($x0['lay']) ?></td>
                    <td><?= htmlspecialchars($x0['head']) ?></td>
                    <td><?= htmlspecialchars($x0['stat']) ?></td>
                    <td><?= htmlspecialchars($x0['pend']) ?></td>
                    <td><?= htmlspecialchars($x0['crit']) ?></td>
                    <td><?= htmlspecialchars($x0['budget']) ?></td>
                    <td><?= htmlspecialchars($x0['comp']) ?></td>
                    <td><?= htmlspecialchars($x0['last']) ?></td>
                    <td><a href="<?= htmlspecialchars($x0['link']) ?>">نزول</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$view): ?>
                <tr><td colspan="12">لا بطاقات بالمرشح المختار</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>بطاقة حالة قابلة للنزول، وكل خانة بمصدرها المنسوب:</strong>
        الادارات من سجل الوحدات النشطة، ورأسها من التكليفات السارية لانواع رأس الوحدة،
        والمخاطر الحرجة بوحدة مالكها والسقف الساري بيوم القاعدة والسياسات من سجلها
        والطلبات المعلقة ببوابة الطلبات بوحدة مصدرها او بمعرف المشروع.
        المشروعات من سجل المشروعات وآخر يوم موقع من سجل ايام المواقع.
        وما لا مصدر له منسوبا يقول ذلك ولا يختلق له رقم. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>

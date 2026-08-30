<?php
/**
 * Suppliers/supplier_targets.php — مستهدفاتُ الموردين (SUP-16)
 * ───────────────────────────────────────────────────────────────────────────
 * **مشتقٌّ من السريانِ التعاقديّ** (‏`SUP-16` نصًّا: «مستهدفٌ شهريٌّ واحد —
 * مشتقٌّ من م10 · Derived View») — الحبّةُ: **موردٌ × شهر**.
 *
 * ◆ **المصادرُ الثلاثةُ بالاسم — قراءةٌ صِرفٌ ولا إدخال**:
 *   `v_supplier_targets_monthly` (‏المستهدفُ الشهريُّ من الحصصِ الساريةِ —
 *   منظورُ م10 نفسُه بقاعدتِه `basis`) · `v_supplier_share_units` (‏الوحداتُ
 *   التعاقديّةُ الساريةُ: الحاويةُ وقيمتُها ومداها) · `settlements`
 *   (‏المنفَّذُ المقيسُ: `supplier_executed_hours` لتسوياتِ الموردِ الواقعةِ
 *   في الشهر).
 * ◆ **وقاعدةُ كلِّ مشتقٍّ في الشاشة**: نسبةُ التحقّقِ = المنفَّذُ ÷ المستهدف ·
 *   وعلمُ «شهرٌ بلا تنفيذ» لشهرٍ انقضى ومنفَّذُه صفر · والمستهدفُ المنقضي
 *   مجموعُ مستهدفاتِ الشهورِ حتى الشهرِ الجاري.
 * ◆ حقولُ الوحدةِ التعاقديّةِ (‏الحاويةُ ومداها وقيمتُها) تُعرض **مجمَّعةً
 *   على صفِّ الموردِ×الشهر** — وهو حبّةُ المتطلبِ نفسُها — بقاعدةٍ مذكورة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_targets.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier targets super') : ems_tenant_db();
$thisMonth = (string) ($conn->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m')")->fetch_row()[0]);
$monthQ = isset($_GET['m']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['m']) : '';
$supQ = isset($_GET['sup']) ? intval($_GET['sup']) : 0;

$rows = array(); $units = array(); $months = array();
try {
    $opts = array('orderBy' => 'target_month DESC, supplier_id ASC', 'limit' => 600);
    $w = array();
    if ($monthQ !== '') { $w['target_month'] = $monthQ; }
    if ($supQ > 0) { $w['supplier_id'] = $supQ; }
    if ($w) { $opts['where'] = $w; }
    $rows = $gate->select('v_supplier_targets_monthly', $opts);
} catch (\Throwable $t) { error_log('supplier_targets rows: ' . $t->getMessage()); }
try { foreach ($gate->select('v_supplier_targets_monthly', array('columns' => array('target_month'), 'orderBy' => 'target_month DESC', 'limit' => 200)) as $m0) { $months[(string) $m0['target_month']] = true; } }
catch (\Throwable $t) { error_log('supplier_targets months: ' . $t->getMessage()); }
try {
    foreach ($gate->select('v_supplier_share_units', array('limit' => 2000)) as $u0) {
        $units[(int) $u0['supplier_id']][] = $u0;
    }
} catch (\Throwable $t) { error_log('supplier_targets units: ' . $t->getMessage()); }

/* المنفَّذُ المقيس: تسوياتُ الموردِ الواقعةُ في الشهر — **قراءةً معزولةً
   بواجهةِ البوّابةِ ثمَّ تجميعًا في الذاكرة**: لا حرفَ SQL في الشاشةِ أصلًا
   (‏سقّاطةُ GAP-29 ترصد النصَّ الحرفيَّ على جداولِ المستأجِر). */
$exec = array();
try {
    $stl = $gate->select('settlements', array(
        'columns' => array('party_type', 'party_ref', 'period_from', 'supplier_executed_hours', 'is_deleted'),
        'limit' => 5000,
    ));
    foreach ($stl as $x0) {
        if ((string) $x0['party_type'] !== 'supplier') { continue; }
        if ((int) (isset($x0['is_deleted']) ? $x0['is_deleted'] : 0) === 1) { continue; }
        $pm = substr((string) $x0['period_from'], 0, 7);
        $sid1 = (int) $x0['party_ref'];
        $exec[$sid1][$pm] = (isset($exec[$sid1][$pm]) ? $exec[$sid1][$pm] : 0.0)
                          + (float) (isset($x0['supplier_executed_hours']) ? $x0['supplier_executed_hours'] : 0);
    }
} catch (\Throwable $t) { error_log('supplier_targets exec: ' . $t->getMessage()); }

/* المستهدفُ المنقضي لكلِّ مورد — مجموعُ مستهدفاتِ الشهورِ حتى الجاري */
$elapsed = array();
foreach ($rows as $r0) {
    $sid0 = (int) $r0['supplier_id'];
    if ((string) $r0['target_month'] <= $thisMonth) {
        $elapsed[$sid0] = (isset($elapsed[$sid0]) ? $elapsed[$sid0] : 0) + (float) $r0['monthly_target'];
    }
}
$n = count($rows); $zeroMonths = 0; $totT = 0.0; $totE = 0.0;
foreach ($rows as $r0) {
    $sid0 = (int) $r0['supplier_id'];
    $pm = (string) $r0['target_month'];
    $e0 = isset($exec[$sid0][$pm]) ? $exec[$sid0][$pm] : 0.0;
    $totT += (float) $r0['monthly_target']; $totE += $e0;
    if ($pm < $thisMonth && $e0 <= 0) { $zeroMonths++; }
}

$page_title = 'إيكوبيشن | مستهدفات الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مستهدفات الموردين: مورد في شهر، مشتق من السريان التعاقدي'; $header_icon = 'fa fa-bullseye'; $header_actions = array();
    $header_back = array('href' => 'suppliers.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الموردون');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($n) ?></div><div class="ems-stat-label">صفوف مستهدف، مورد في شهر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($totT, 1) ?></div><div class="ems-stat-label">مجموع المستهدف المعروض</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($totE, 1) ?></div><div class="ems-stat-label">المنفذ المقيس من التسويات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($zeroMonths) ?></div><div class="ems-stat-label">شهور منقضية بلا تنفيذ</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>الشهر</label>
                <select name="m" onchange="this.form.submit()">
                    <option value="">كل الشهور</option>
                    <?php foreach (array_keys($months) as $m1): ?>
                    <option value="<?= htmlspecialchars($m1) ?>" <?= $m1 === $monthQ ? 'selected' : '' ?>><?= htmlspecialchars($m1) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>المورد</th>
                    <th>الشهر</th>
                    <th>الوحدات التعاقدية السارية</th>
                    <th>مداها ومقدارها</th>
                    <th>مستهدف الشهر</th>
                    <th>المنفذ بالشهر</th>
                    <th>نسبة التحقق</th>
                    <th>شهر بلا تنفيذ؟</th>
                    <th>المستهدف المنقضي للمورد</th>
                    <th>مصدر المستهدف</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r0):
                $sid0 = (int) $r0['supplier_id'];
                $pm = (string) $r0['target_month'];
                $tgt = (float) $r0['monthly_target'];
                $e0 = isset($exec[$sid0][$pm]) ? $exec[$sid0][$pm] : 0.0;
                $pct = $tgt > 0 ? round(100 * $e0 / $tgt, 1) : null;
                $flag = ($pm < $thisMonth && $e0 <= 0);
                $slotTxt = 'غير مسجلة'; $rangeTxt = 'غير منطبق';
                if (isset($units[$sid0])) {
                    $codes = array(); $lo = ''; $hi = ''; $sumU = 0.0;
                    foreach ($units[$sid0] as $u0) {
                        $codes[] = (string) $u0['container_no'];
                        $sumU += (float) $u0['share_units'];
                        if ($lo === '' || $u0['effective_from'] < $lo) { $lo = (string) $u0['effective_from']; }
                        if ($hi === '' || (string) $u0['effective_to'] > $hi) { $hi = (string) $u0['effective_to']; }
                    }
                    $slotTxt = count($codes) . ': ' . implode('، ', array_slice($codes, 0, 3)) . (count($codes) > 3 ? '…' : '');
                    $rangeTxt = 'من ' . $lo . ' الى ' . $hi . '، بمقدار ' . number_format($sumU, 1);
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r0['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($pm) ?></td>
                    <td><?= htmlspecialchars($slotTxt) ?></td>
                    <td><?= htmlspecialchars($rangeTxt) ?></td>
                    <td><?= number_format($tgt, 1) ?></td>
                    <td><?= number_format($e0, 1) ?></td>
                    <td><?= $pct === null ? 'غير منطبق' : $pct . '%' ?></td>
                    <td><?= $flag ? 'نعم' : 'لا' ?></td>
                    <td><?= isset($elapsed[$sid0]) ? number_format($elapsed[$sid0], 1) : '0' ?></td>
                    <td><?= htmlspecialchars((string) $r0['basis']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="10">لا مستهدفات مشتقة بعد. المصدر منظور المستهدفات الشهرية من الحصص السارية</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        قواعد الاشتقاق بمصادرها: المستهدف من منظور الحصص السارية بقاعدته المذكورة في عمود المصدر،
        والمنفذ مجموع ساعات المورد المنفذة في تسويات الشهر، ونسبة التحقق قسمة المنفذ على المستهدف،
        وعلم الشهر بلا تنفيذ لشهر انقضى ومنفذه صفر، والمستهدف المنقضي مجموع مستهدفات الشهور حتى الجاري.
        قراءة صرف ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>

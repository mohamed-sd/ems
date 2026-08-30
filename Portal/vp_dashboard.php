<?php
/**
 * Portal/vp_dashboard.php — لوحةُ قيادةِ النائب (VP-01)
 * ───────────────────────────────────────────────────────────────────────────
 * **نفسُ المحرّكِ العامِّ وDefault Scope يتغيّر بالنائب** (‏`VP-01` نصًّا)
 * — Grain: **مؤشِّرٌ × محورٍ ضمن Scope النائب**.
 *
 * ◆ المحرّكُ المشتركُ في `includes/exec_indicator_engine.php` (يستهلكه
 *   `CEO-01` بنطاقِ الشركة): أحدَ عشرَ محورًا بمالكِ كلٍّ منها — وهنا
 *   يُرشَّح افتراضًا على **نطاقِ نيابةِ المستخدمِ من سجلِّ التكليفاتِ**
 *   (الوحداتُ التي ينوب فيها بصفةٍ سارية)، والرؤيةُ أوسعُ من الصلاحية:
 *   مفتاحٌ صريحٌ يفتح المحاورَ كلَّها **قراءةً** موسومةً بخارجِ النطاق.
 * ◆ كلُّ مؤشِّرٍ بكودِه وقيمتِه واتجاهِه ومعادلتِه ومصدرِه في صفِّه —
 *   لا رقمَ بلا قاعدة. قراءةٌ صِرفٌ وصفرُ حرفِ SQL على جدولِ مستأجِر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/vp_dashboard.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('vp dashboard super') : ems_tenant_db();
require_once __DIR__ . '/../includes/exec_indicator_engine.php';
$axes = ems_exec_indicator_axes($gate, $conn);
$myEmp = isset($_SESSION['user']['employee_id']) ? intval($_SESSION['user']['employee_id']) : 0;
$scope = ems_exec_deputy_scope($gate, $myEmp);
$mode = isset($_GET['mode']) && (string) $_GET['mode'] === 'all' ? 'all' : 'my';

$nAxes = count($axes); $nMine = 0; $nInd = 0; $nAttn = 0;
foreach ($axes as $ax) {
    $in = isset($scope[$ax['unit_code']]);
    if ($in) { $nMine++; }
    foreach ($ax['items'] as $it) {
        $nInd++;
        if ($it['status'] === 'فيه حرج' || $it['status'] === 'يستدعي القمة' || $it['status'] === 'بانتظار قرار') { $nAttn++; }
    }
}
$scopeLabel = $scope ? ('نيابة سارية في ' . count($scope) . ' وحدات من سجل التكليفات') : 'ليس نائبا مسجلا في سجل التكليفات';

$page_title = 'إيكوبيشن | لوحة قيادة النائب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة قيادة النائب: المحرك العام نفسه والنطاق الافتراضي نطاق نيابتك'; $header_icon = 'fa fa-gauge'; $header_actions = array();
    $header_back = array('href' => 'vp_departments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الادارات نطاقي والشركة');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAxes) ?></div><div class="ems-stat-label">محاور المحرك العام</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nMine) ?></div><div class="ems-stat-label">محاور ضمن نطاق نيابتي</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nInd) ?></div><div class="ems-stat-label">مؤشرات مقيسة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAttn) ?></div><div class="ems-stat-label">تستدعي نظر القيادة</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>وضع العرض</label>
                <select name="mode" onchange="this.form.submit()">
                    <option value="my" <?= $mode === 'my' ? 'selected' : '' ?>>نطاق نيابتي</option>
                    <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>المحاور كلها قراءة</option>
                </select>
            </div>
            <div class="ems-filter-item">
                <label>صفة النطاق</label>
                <input type="text" value="<?= htmlspecialchars($scopeLabel) ?>" readonly>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>المحور</th>
                    <th>ضمن نطاقي؟</th>
                    <th>الادارة المالكة</th>
                    <th>كود المؤشر</th>
                    <th>المؤشر</th>
                    <th>القيمة</th>
                    <th>المستهدف او الحد</th>
                    <th>الاتجاه</th>
                    <th>الحالة</th>
                    <th>آخر تحديث</th>
                    <th>المعادلة والمصدر</th>
                    <th>النزول للمصدر</th>
                </tr>
            </thead>
            <tbody>
            <?php $shown = 0;
            foreach ($axes as $ax):
                $in = isset($scope[$ax['unit_code']]);
                if ($mode === 'my' && !$in) { continue; }
                foreach ($ax['items'] as $it): $shown++; ?>
                <tr>
                    <td><?= htmlspecialchars($ax['axis']) ?></td>
                    <td><?= $in ? 'نعم' : 'خارج النطاق، قراءة فقط' ?></td>
                    <td><?= htmlspecialchars($ax['owner']) ?></td>
                    <td><?= htmlspecialchars($it['code']) ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td><?= htmlspecialchars($it['value']) ?></td>
                    <td><?= htmlspecialchars($it['target']) ?></td>
                    <td><?= htmlspecialchars($it['trend']) ?></td>
                    <td><?= htmlspecialchars($it['status']) ?></td>
                    <td><?= htmlspecialchars($it['updated']) ?></td>
                    <td><?= htmlspecialchars($it['src']) ?></td>
                    <td><a href="../<?= htmlspecialchars($it['link']) ?>">نزول</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if ($shown === 0): ?>
                <tr><td colspan="12">لا محور ضمن نطاق نيابتك المسجل في سجل التكليفات. بدل وضع العرض الى المحاور كلها قراءة</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>الرؤية اوسع من الصلاحية:</strong>
        النطاق الافتراضي وحدات نيابتك السارية من سجل التكليفات، وما خرج عنه يفتح قراءة موسوما.
        المحرك هو محرك لوحة القيادة التنفيذية نفسه، الحي يقاس لحظة الطلب والدوري من آخر لقطة اقفال معتمدة
        باسم فترتها، وكل مؤشر بمعادلته ومصدره في صفه. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>

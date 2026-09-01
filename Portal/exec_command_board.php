<?php
/**
 * Portal/exec_command_board.php — لوحةُ القيادةِ التنفيذيّة (CEO-01)
 * ───────────────────────────────────────────────────────────────────────────
 * **أقوى لوحةٍ في الشركة** (‏`CEO-01` نصًّا: «Field Universe بأحدَ عشرَ
 * محورًا يحدّده هذا الدليل») — Grain: **مؤشِّرٌ × محورٌ × نطاق**.
 *
 * ◆ المحرّكُ في `includes/exec_indicator_engine.php` — **واحدٌ للرئيسِ
 *   والنائب** (`VP-01` يستهلكه بنطاقٍ افتراضيٍّ مختلف): أحدَ عشرَ محورًا،
 *   كلُّ مؤشِّرٍ بكودِه من كتالوجِ النِّسبِ حيث يُطابق، وقيمتِه من مصدرِه
 *   الحيِّ أو من آخرِ لقطةِ إقفالٍ معتمدةٍ باسمِ فترتِها، واتجاهِه
 *   بمقارنةِ اللقطةِ السابقةِ — **ولا رقمَ بلا معادلةٍ ومصدرٍ في صفِّه**.
 * ◆ نطاقُ هذه اللوحةِ **الشركةُ** — والنزولُ لمشروعٍ أو موقعٍ من رابطِ
 *   النزولِ لمصدرِ كلِّ مؤشِّرٍ حيث تعيش تفاصيلُه بنطاقِها.
 * ◆ قراءةٌ صِرفٌ — صفرُ POST وصفرُ حرفِ SQL على جدولِ مستأجِر (GAP-29).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/exec_command_board.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('exec command board super') : ems_tenant_db();
require_once __DIR__ . '/../includes/exec_indicator_engine.php';
$axes = ems_exec_indicator_axes($gate, $conn);

$axisQ = isset($_GET['axis']) ? (string) $_GET['axis'] : '';
$nAxes = count($axes); $nInd = 0; $nLive = 0; $nAttn = 0;
foreach ($axes as $ax) {
    foreach ($ax['items'] as $it) {
        $nInd++;
        if ($it['updated'] === 'الان، لحظة الطلب') { $nLive++; }
        if ($it['status'] === 'فيه حرج' || $it['status'] === 'يستدعي القمة' || $it['status'] === 'بانتظار قرار') { $nAttn++; }
    }
}

$page_title = 'إيكوبيشن | لوحة القيادة التنفيذية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'لوحة القيادة التنفيذية: مؤشر في محور في نطاق، وكل رقم بمعادلته ومصدره'; $header_icon = 'fa fa-gauge-high'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الرئيس');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> لوحة القيادة التنفيذية بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف المؤشر' => 'g1',
            'الكيان' => 'g2',
            'المحور' => 'g3',
            'كود المؤشر KPI Catalog' => 'g4',
            'اسم المؤشر' => 'g5',
            'النطاق' => 'g6',
            'مرجع النطاق' => 'g7',
            'القيمة' => 'g8',
            'الوحدة/العملة' => 'g9',
            'المستهدف' => 'g10',
            'الانحراف' => 'g11',
            'الاتجاه' => 'g12',
            'الحالة' => 'g13',
            'الإدارة المالكة' => 'g14',
            'رابط النزول للمصدر' => 'g15',
            'آخر تحديث' => 'g16',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_board_kpi');
        echo ems_w14_grid('emsList_exec_board_kpi', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في لوحة القيادة التنفيذية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAxes) ?></div><div class="ems-stat-label">محاور اللوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nInd) ?></div><div class="ems-stat-label">مؤشرات مقيسة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nLive) ?></div><div class="ems-stat-label">تقاس لحظة الطلب</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAttn) ?></div><div class="ems-stat-label">تستدعي نظر القيادة</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>المحور</label>
                <select name="axis" onchange="this.form.submit()">
                    <option value="">المحاور كلها</option>
                    <?php foreach ($axes as $ax): ?>
                    <option value="<?= htmlspecialchars($ax['axis']) ?>" <?= $ax['axis'] === $axisQ ? 'selected' : '' ?>><?= htmlspecialchars($ax['axis']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ems-filter-item">
                <label>النطاق</label>
                <input type="text" aria-label="النطاق" value="الشركة، والنزول لمشروع او موقع من رابط كل مؤشر" readonly>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>المحور</th>
                    <th>الادارة المالكة</th>
                    <th>كود المؤشر</th>
                    <th>المؤشر</th>
                    <th>القيمة</th>
                    <th>الوحدة</th>
                    <th>المستهدف او الحد</th>
                    <th>الاتجاه</th>
                    <th>الحالة</th>
                    <th>آخر تحديث</th>
                    <th>المعادلة والمصدر</th>
                    <th>النزول للمصدر</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($axes as $ax): if ($axisQ !== '' && $ax['axis'] !== $axisQ) { continue; } ?>
                <?php foreach ($ax['items'] as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($ax['axis']) ?></td>
                    <td><?= htmlspecialchars($ax['owner']) ?></td>
                    <td><?= htmlspecialchars($it['code']) ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td><?= htmlspecialchars($it['value']) ?></td>
                    <td><?= htmlspecialchars($it['unit']) ?></td>
                    <td><?= htmlspecialchars($it['target']) ?></td>
                    <td><?= htmlspecialchars($it['trend']) ?></td>
                    <td><?= htmlspecialchars($it['status']) ?></td>
                    <td><?= htmlspecialchars($it['updated']) ?></td>
                    <td><?= htmlspecialchars($it['src']) ?></td>
                    <td><a href="../<?= htmlspecialchars($it['link']) ?>">نزول</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>قواعد اللوحة:</strong>
        الحي يقاس من سجله لحظة الطلب، والدوري من آخر لقطة اقفال معتمدة باسم فترتها ولا يعاد حساب المقفل،
        والاتجاه بمقارنة اللقطة السابقة وما لا سابقة له يقول ذلك.
        كود المؤشر من كتالوج النسب المعتمد حيث يطابق، وما لا كود له بعد يقول غير مفهرس.
        قراءة صرف ولا ادخال من هذه اللوحة.
    </div>
</div>
<?php include '../infooter.php'; ?>

<?php
/**
 * Reports/guard_denials_report.php — تقرير المنع (GOV-01 §9-⑥ · الشاشة 204)
 * ───────────────────────────────────────────────────────────────────────────
 * «متى مُنع النظام ومن حاول ولماذا — ومنه يُعرف أي حماية تُعيق العمل فتُراجَع»:
 * سجل المنع **مقياس ملاءمة للحماية نفسها** لا سجل مخالفات للمستخدمين — الحماية
 * التي تُمنع مئة مرة في الشهر إما الواقع يخالف السياسة أو السياسة تحتاج مراجعة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$days = max(1, min(365, intval($_GET['days'] ?? 30)));
$sum = array(); $rows = array();
$r = $conn->query("SELECT d.guard_code, g.name_ar, g.guard_class, COUNT(*) n
                     FROM guard_denials d JOIN guard_policies g ON g.guard_code = d.guard_code
                    WHERE d.company_id = {$company_id} AND d.at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                    GROUP BY d.guard_code, g.name_ar, g.guard_class ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) { $sum[] = $x; }
$r = $conn->query("SELECT d.*, g.name_ar FROM guard_denials d JOIN guard_policies g ON g.guard_code = d.guard_code
                    WHERE d.company_id = {$company_id} AND d.at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                    ORDER BY d.at DESC LIMIT 300");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$page_title = 'إيكوبيشن | تقرير المنع';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ②: أنماطُ تقريرِ المنع — rpt-gd */
.rpt-gd-filter{display:flex;gap:10px;align-items:center}
.rpt-gd-days{width:90px}
.rpt-gd-counters{display:flex;gap:10px;flex-wrap:wrap}
.rpt-gd-counter{font-size:14px;padding:8px 14px}
.rpt-gd-table{width:100%}
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تقرير المنع — مقياس ملاءمة الحمايات'; $header_icon = 'fa fa-ban';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('متى مُنع النظام ومن حاول ولماذا — الحماية التي تُمنع كثيرًا إما الواقع '
        . 'يخالف السياسة أو السياسة تحتاج مراجعة. مقياس ملاءمة لا سجل مخالفات.',
        array('اقرأ العدّاد لكل حماية', 'راجع صنف ما ارتفع منعه'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا وقائعَ منعٍ مسجلةً في هذه المدة', 'وسّع عددَ الأيامِ ثمّ اضغط عرض — وإن بقيت النتيجةُ صفرًا فافحص عملَ الرصد');
    ?>
    <div class="card"><div class="card-body">
        <form method="get" class="rpt-gd-filter">
            <label for="emsf_462_55a08">آخر</label><input type="number" name="days" min="1" max="365" id="emsf_462_55a08" class="rpt-gd-days" value="<?php echo $days; ?>"><label>يومًا</label>
            <button class="btn-primary" type="submit">عرض</button>
        </form>
    </div></div>
    <div class="card"><div class="card-body">
        <h6>عدّاد المنع لكل حماية (مقياس الملاءمة)</h6>
        <div class="rpt-gd-counters">
        <?php foreach ($sum as $s): ?>
            <div class="badge <?php echo intval($s['n']) >= 100 ? 'badge-danger' : 'badge-secondary'; ?> rpt-gd-counter">
                <?php echo htmlspecialchars($s['name_ar']); ?>: <strong><?php echo intval($s['n']); ?></strong>
                <?php if (intval($s['n']) >= 100): ?> — تُراجَع<?php endif; ?>
            </div>
        <?php endforeach; if (empty($sum)): ?><em>صفر منع في المدة — انضباط أو توقف رصد</em><?php endif; ?>
        </div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="table-container"><table class="alltables display rpt-gd-table">
        <thead><tr><th>الوقت</th><th>الحماية</th><th>المستخدم</th><th>المرجع</th><th>السبب</th></tr></thead><tbody>
        <?php foreach ($rows as $x): ?>
        <tr>
            <td><?php echo htmlspecialchars($x['at']); ?></td>
            <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
            <td>#<?php echo intval($x['person_id']); ?></td>
            <td><?php echo htmlspecialchars((string) $x['attempted_ref']); ?></td>
            <td><?php echo htmlspecialchars((string) $x['reason_code']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>

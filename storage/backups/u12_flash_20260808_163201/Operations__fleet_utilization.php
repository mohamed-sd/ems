<?php
/**
 * Operations/fleet_utilization.php — الاستغلالُ ومردودُ المعدة (RENTAL-CORE ③)
 * ───────────────────────────────────────────────────────────────────────────
 * لغةُ قطاع التأجير. وقد اخترنا منها ما يصدُق على هذا الأسطول تحديدًا:
 * الاستغلالُ المادّي والهامش — لا مردودَ رأس المال أساسًا، لأن 212 من 219 معدةً
 * مورَّدةٌ لا مملوكة. ومردودُ رأس المال يُعرض حيث توفّرت تكلفةُ الاقتناء ويُعلَن
 * غيابُه صراحةً حيث لم تتوفر — لا صفرًا مضلِّلًا.
 * قراءةٌ واشتقاقٌ بلا أثر — لا كتابةَ في هذه الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Rental/AvailabilityService.php';
require_once __DIR__ . '/../app/Services/Rental/UtilizationService.php';

use App\Services\Rental\UtilizationService as UT;

if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }
if (!function_exists('fu_e')) { function fu_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('fu_n')) { function fu_n($v, $d = 0) { return number_format((float) $v, $d); } }

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) { header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.')); exit(); }
$fu_gate = ems_tenant_db();

$vd = function ($s) { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); };
$from = $vd($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01', strtotime('-2 months'));
$to   = $vd($_GET['to'] ?? '')   ? $_GET['to']   : date('Y-m-d');
if (strtotime($to) < strtotime($from)) { $to = $from; }
$type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;

$rows    = UT::byEquipment($fu_gate, $from, $to, $type_filter);
$summary = UT::summary($rows);
$byType  = UT::byType($rows);

// ترتيبٌ افتراضيٌّ: الأدنى استغلالًا أولًا — «اقرأ المتعطِّلَ أولًا، هو القرار»
usort($rows, function ($a, $b) {
    if ($a['util_pct'] === $b['util_pct']) { return $b['revenue'] <=> $a['revenue']; }
    return $a['util_pct'] <=> $b['util_pct'];
});

$types = array();
$r = @mysqli_query($conn, "SELECT id, type FROM equipments_types ORDER BY type");
while ($r && ($x = mysqli_fetch_assoc($r))) { $types[] = $x; }

$page_title = 'إيكوبيشن | استغلالُ الأسطول ومردوده';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
<?php
$header_title = 'استغلالُ الأسطول ومردودُه';
$header_icon = 'fa fa-gauge-high';
$header_actions = array();
$header_back = array('href' => '../main/role_board.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
include('../includes/page_header.php');
if (function_exists('ems_screen_about')) {
    ems_screen_about(
        'التأجيرُ يدير رأسَ مالٍ لا مخزونًا — فالسؤالُ: أيُّ معدةٍ تُطعم وأيُّها تُعطَّل؟ '
        . 'الاستغلالُ المادّي أيامُ التأجير ÷ أيام المدة، والهامشُ إيرادُ العميل − تكلفةُ المورد. '
        . 'ومردودُ رأس المال يظهر حيث توفّرت تكلفةُ الاقتناء ويُعلَن غيابُه حيث لم تتوفر.',
        array('حدّد المدة', 'اقرأ الأدنى استغلالًا أولًا', 'قارن الفئات — لا الآلات وحدها')
    );
}
?>
  <div class="card"><div class="card-body">
    <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      <div><label>من</label><input type="date" name="from" value="<?php echo fu_e($from); ?>" class="form-control"></div>
      <div><label>إلى</label><input type="date" name="to" value="<?php echo fu_e($to); ?>" class="form-control"></div>
      <div><label>الفئة</label>
        <select name="type" class="form-control">
          <option value="0">— كل الفئات —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo (int) $t['id']; ?>" <?php echo $type_filter === (int) $t['id'] ? 'selected' : ''; ?>>
              <?php echo fu_e($t['type']); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><button type="submit" class="btn btn-primary"><i class="fa fa-chart-line"></i> اقِس</button></div>
    </form>
  </div></div>

  <!-- المؤشراتُ العليا -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:14px">
    <div class="card"><div class="card-body" style="text-align:center">
      <div style="font-size:1.8rem;font-weight:700"><?php echo (int) $summary['fleet']; ?></div>
      <div class="text-muted">معدةً في الأسطول</div></div></div>
    <div class="card"><div class="card-body" style="text-align:center">
      <div style="font-size:1.8rem;font-weight:700;color:#2C6749"><?php echo (int) $summary['rented']; ?></div>
      <div class="text-muted">عملت في المدة</div></div></div>
    <div class="card"><div class="card-body" style="text-align:center">
      <div style="font-size:1.8rem;font-weight:700;color:#9A3412"><?php echo (int) $summary['idle']; ?></div>
      <div class="text-muted">لم تعمل إطلاقًا</div></div></div>
    <div class="card"><div class="card-body" style="text-align:center">
      <div style="font-size:1.8rem;font-weight:700"><?php echo fu_n($summary['avg_util'], 1); ?>٪</div>
      <div class="text-muted">متوسطُ الاستغلال</div></div></div>
    <div class="card"><div class="card-body" style="text-align:center">
      <div style="font-size:1.5rem;font-weight:700;color:#2C6749"><?php echo fu_n($summary['margin']); ?></div>
      <div class="text-muted">الهامش<?php echo $summary['margin_pct'] !== null ? ' (' . fu_n($summary['margin_pct'], 1) . '٪)' : ''; ?></div></div></div>
  </div>

  <?php if ($summary['mixed_currency'] > 0 || $summary['with_oec'] < $summary['fleet']): ?>
  <div class="alert alert-warning" style="margin-top:12px">
    <?php if ($summary['with_oec'] < $summary['fleet']): ?>
      <div><i class="fa fa-circle-info"></i> مردودُ رأس المال محسوبٌ لـ<b><?php echo (int) $summary['with_oec']; ?></b>
        معدةً فقط من <?php echo (int) $summary['fleet']; ?> — الباقي بلا تكلفةِ اقتناءٍ مسجَّلة.
        <b>الغيابُ مُعلَنٌ لا مصفَّر.</b></div>
    <?php endif; ?>
    <?php if ($summary['mixed_currency'] > 0): ?>
      <div><i class="fa fa-triangle-exclamation"></i> <b><?php echo (int) $summary['mixed_currency']; ?></b>
        معدةً بوقائعَ بعملتين — أرقامُها مجموعةٌ خامًا فاقرأها بحذر (لا تُجمع عملتان في رقم).</div>
    <?php endif; ?>
    <?php if ($summary['negative_margin'] > 0): ?>
      <div><i class="fa fa-arrow-trend-down"></i> <b><?php echo (int) $summary['negative_margin']; ?></b>
        معدةً بهامشٍ سالب — اقرأها أولًا.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- بالفئة -->
  <div class="card" style="margin-top:14px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-layer-group"></i> بالفئة — أيُّ فئةٍ تُطعم؟</h5>
    <div class="table-responsive"><table class="table table-sm" data-no-dt="1">
      <thead><tr><th>الفئة</th><th>الأسطول</th><th>عملت</th><th>الاستغلال</th><th>الإيراد</th><th>التكلفة</th><th>الهامش</th><th>نسبة الهامش</th></tr></thead>
      <tbody>
      <?php foreach ($byType as $t): ?>
        <tr>
          <td><?php echo fu_e($t['type_name']); ?></td>
          <td><?php echo (int) $t['fleet']; ?></td>
          <td><?php echo (int) $t['rented']; ?></td>
          <td><b style="color:<?php echo $t['util_pct'] < 20 ? '#9A3412' : '#2C6749'; ?>">
              <?php echo fu_n($t['util_pct'], 1); ?>٪</b></td>
          <td><?php echo fu_n($t['revenue']); ?></td>
          <td><?php echo fu_n($t['cost']); ?></td>
          <td style="font-weight:700;color:<?php echo $t['margin'] < 0 ? '#9A3412' : '#2C6749'; ?>">
              <?php echo fu_n($t['margin']); ?></td>
          <td><?php echo $t['margin_pct'] === null ? '—' : fu_n($t['margin_pct'], 1) . '٪'; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($byType)): ?><tr><td colspan="8" class="text-center text-muted">لا بيانات</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div></div>

  <!-- لكل معدة -->
  <div class="card" style="margin-top:14px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-truck"></i> لكل معدة — الأدنى استغلالًا أولًا</h5>
    <div class="table-responsive"><table class="table display" id="fuTable">
      <thead><tr>
        <th>الكود</th><th>المعدة</th><th>الفئة</th><th>أيامُ التأجير</th><th>الاستغلال</th>
        <th>الإيراد</th><th>تكلفةُ المورد</th><th>الهامش</th><th>نسبةُ الهامش</th>
        <th>تكلفةُ الاقتناء</th><th>مردودُ رأس المال</th>
        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
        <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
        </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr<?php echo $r['margin'] < 0 ? ' style="background:#fff3f0"' : ''; ?>>
          <td><?php echo fu_e($r['code']); ?></td>
          <td><?php echo fu_e($r['name']); ?></td>
          <td><?php echo fu_e($r['type_name']); ?></td>
          <td><?php echo (int) $r['rented_days']; ?> / <?php echo (int) $r['span_days']; ?></td>
          <td><b style="color:<?php echo $r['util_pct'] < 20 ? '#9A3412' : '#2C6749'; ?>">
              <?php echo fu_n($r['util_pct'], 1); ?>٪</b></td>
          <td><?php echo fu_n($r['revenue']); ?></td>
          <td><?php echo fu_n($r['supplier_cost']); ?></td>
          <td style="font-weight:700;color:<?php echo $r['margin'] < 0 ? '#9A3412' : '#2C6749'; ?>">
              <?php echo fu_n($r['margin']); ?></td>
          <td><?php echo $r['margin_pct'] === null ? '—' : fu_n($r['margin_pct'], 1) . '٪'; ?></td>
          <td><?php echo $r['oec'] === null ? '<span class="text-muted">غير مسجَّلة</span>' : fu_n($r['oec']); ?></td>
          <td><?php echo $r['oec_yield_pct'] === null
                ? '<span class="text-muted">—</span>' : fu_n($r['oec_yield_pct'], 1) . '٪'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="text-muted" style="margin-top:8px">
      الهامشُ هنا من الوقائع المالية المنسوبة للمعدة (<code>equipment_id</code>) — وهو المقياسُ الصادق لأسطولٍ
      جُلُّه مورَّدٌ لا مملوك. ومردودُ رأس المال مقياسُ الأسطول المملوك، فيظهر حيث تُسجَّل تكلفةُ الاقتناء.
    </p>
  </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script>
$(function () {
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#fuTable')) {
        $('#fuTable').DataTable({ stateSave: false, pageLength: 25, order: [] });
    }
});
</script>

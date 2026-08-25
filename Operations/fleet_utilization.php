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
if ($company_id <= 0) { ems_gov_flash_redirect('../login.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', ''); exit(); }
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

$page_title = 'إيكوبيشن | استغلال الأسطول ومردوده';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01: أنماطُ الشاشةِ في كتلةٍ واحدة — لا نمطَ موضعيًّا ولا لونَ خارجَ الرموز */
.fu-filter { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
.fu-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-top: 14px; }
.fu-kpi-body { text-align: center; }
.fu-kpi-num { font-size: 1.8rem; font-weight: 700; }
.fu-kpi-num-sm { font-size: 1.5rem; font-weight: 700; }
.fu-ok { color: var(--c-2c6749, #2C6749); }
.fu-warn { color: var(--c-9a3412); }
.fu-alert { margin-top: 12px; }
.fu-card { margin-top: 14px; }
.fu-tight { margin: 0 0 10px; }
.fu-strong { font-weight: 700; }
.fu-row-neg { background: var(--c-fff3f0, #fff3f0); }
.fu-note { margin-top: 8px; }
</style>
<div class="main ems-unified-page-shell">
<?php
$header_title = 'استغلال الأسطول ومردوده';
$header_icon = 'fa fa-gauge-high';
$header_actions = array();
$header_back = array('href' => '../main/role_board.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
include('../includes/page_header.php');
if (function_exists('ems_screen_about')) {
    ems_screen_about(
        'التأجير يدير رأس مال لا مخزونا — فالسؤال: أي معدة تطعم وأيها تعطل؟ '
        . 'الاستغلال المادي أيام التأجير ÷ أيام المدة، والهامش إيراد العميل − تكلفة المورد. '
        . 'ومردود رأس المال يظهر حيث توفرت تكلفة الاقتناء ويعلن غيابه حيث لم تتوفر.',
        array('حدد المدة', 'اقرأ الأدنى استغلالا أولا', 'قارن الفئات — لا الآلات وحدها')
    );
}
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
echo ems_states_bundle('لا معدات أسطول بوقائع في هذه المدة', 'وسع المدة أو أزل مرشح الفئة ثم اضغط «اقس»');
?>
  <div class="card"><div class="card-body">
        <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
    <form method="get" class="fu-filter">
      <div><label for="emsf_364_d11dc">من</label><input type="date" name="from" id="emsf_364_d11dc" class="form-control" value="<?php echo fu_e($from); ?>"></div>
      <div><label for="emsf_365_be9da">إلى</label><input type="date" name="to" id="emsf_365_be9da" class="form-control" value="<?php echo fu_e($to); ?>"></div>
      <div><label for="emsf_366_1e7d1">الفئة</label>
        <select name="type" class="form-control" id="emsf_366_1e7d1">
          <option value="0">— كل الفئات —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo (int) $t['id']; ?>" <?php echo $type_filter === (int) $t['id'] ? 'selected' : ''; ?>>
              <?php echo fu_e($t['type']); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><button type="submit" class="btn btn-primary"><i class="fa fa-chart-line"></i> اقس</button></div>
    </form>
        </div>
    </div>
  </div></div>

  <!-- المؤشراتُ العليا -->
  <div class="fu-kpis">
    <div class="card"><div class="card-body fu-kpi-body">
      <div class="fu-kpi-num"><?php echo (int) $summary['fleet']; ?></div>
      <div class="text-muted">معدة في الأسطول</div></div></div>
    <div class="card"><div class="card-body fu-kpi-body">
      <div class="fu-kpi-num fu-ok"><?php echo (int) $summary['rented']; ?></div>
      <div class="text-muted">عملت في المدة</div></div></div>
    <div class="card"><div class="card-body fu-kpi-body">
      <div class="fu-kpi-num fu-warn"><?php echo (int) $summary['idle']; ?></div>
      <div class="text-muted">لم تعمل إطلاقا</div></div></div>
    <div class="card"><div class="card-body fu-kpi-body">
      <div class="fu-kpi-num"><?php echo fu_n($summary['avg_util'], 1); ?>٪</div>
      <div class="text-muted">متوسط الاستغلال</div></div></div>
    <div class="card"><div class="card-body fu-kpi-body">
      <div class="fu-kpi-num-sm fu-ok"><?php echo fu_n($summary['margin']); ?></div>
      <div class="text-muted">الهامش<?php echo $summary['margin_pct'] !== null ? ' (' . fu_n($summary['margin_pct'], 1) . '٪)' : ''; ?></div></div></div>
  </div>

  <?php if ($summary['mixed_currency'] > 0 || $summary['with_oec'] < $summary['fleet']): ?>
  <div class="alert alert-warning fu-alert">
    <?php if ($summary['with_oec'] < $summary['fleet']): ?>
      <div><i class="fa fa-circle-info"></i> مردود رأس المال محسوب ل<b><?php echo (int) $summary['with_oec']; ?></b>
        معدة فقط من <?php echo (int) $summary['fleet']; ?> — الباقي بلا تكلفة اقتناء مسجلة.
        <b>الغياب معلن لا مصفر.</b></div>
    <?php endif; ?>
    <?php if ($summary['mixed_currency'] > 0): ?>
      <div><i class="fa fa-triangle-exclamation"></i> <b><?php echo (int) $summary['mixed_currency']; ?></b>
        معدة بوقائع بعملتين — أرقامها مجموعة خاما فاقرأها بحذر (لا تجمع عملتان في رقم).</div>
    <?php endif; ?>
    <?php if ($summary['negative_margin'] > 0): ?>
      <div><i class="fa fa-arrow-trend-down"></i> <b><?php echo (int) $summary['negative_margin']; ?></b>
        معدة بهامش سالب — اقرأها أولا.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- بالفئة -->
  <div class="card fu-card"><div class="card-body">
    <h5 class="fu-tight"><i class="fa fa-layer-group"></i> بالفئة — أي فئة تطعم؟</h5>
    <div class="table-responsive"><table class="table table-sm" data-no-dt="hard">
      <thead><tr><th>الفئة</th><th>الأسطول</th><th>عملت</th><th>الاستغلال</th><th>الإيراد</th><th>التكلفة</th><th>الهامش</th><th>نسبة الهامش</th></tr></thead>
      <tbody>
      <?php foreach ($byType as $t): ?>
        <tr>
          <td><?php echo fu_e($t['type_name']); ?></td>
          <td><?php echo (int) $t['fleet']; ?></td>
          <td><?php echo (int) $t['rented']; ?></td>
          <td><b class="<?php echo $t['util_pct'] < 20 ? 'fu-warn' : 'fu-ok'; ?>">
              <?php echo fu_n($t['util_pct'], 1); ?>٪</b></td>
          <td><?php echo fu_n($t['revenue']); ?></td>
          <td><?php echo fu_n($t['cost']); ?></td>
          <td class="fu-strong <?php echo $t['margin'] < 0 ? 'fu-warn' : 'fu-ok'; ?>">
              <?php echo fu_n($t['margin']); ?></td>
          <td><?php echo $t['margin_pct'] === null ? '—' : fu_n($t['margin_pct'], 1) . '٪'; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($byType)): ?><tr><td colspan="8" class="text-center text-muted">لا بيانات</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div></div>

  <!-- لكل معدة -->
  <div class="card fu-card"><div class="card-body">
    <h5 class="fu-tight"><i class="fa fa-truck"></i> لكل معدة — الأدنى استغلالا أولا</h5>
    <div class="table-responsive"><table class="table display" id="fuTable" data-order='[]' data-page-length="25" data-state-save="false">
      <thead><tr>
        <th>الكود</th><th>المعدة</th><th>الفئة</th><th>أيام التأجير</th><th>الاستغلال</th>
        <th>الإيراد</th><th>تكلفة المورد</th><th>الهامش</th><th>نسبة الهامش</th>
        <th>تكلفة الاقتناء</th><th>مردود رأس المال</th>
        <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
        <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
        <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
        </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr<?php echo $r['margin'] < 0 ? ' class="fu-row-neg"' : ''; ?>>
          <td><?php echo fu_e($r['code']); ?></td>
          <td><?php echo fu_e($r['name']); ?></td>
          <td><?php echo fu_e($r['type_name']); ?></td>
          <td><?php echo (int) $r['rented_days']; ?> / <?php echo (int) $r['span_days']; ?></td>
          <td><b class="<?php echo $r['util_pct'] < 20 ? 'fu-warn' : 'fu-ok'; ?>">
              <?php echo fu_n($r['util_pct'], 1); ?>٪</b></td>
          <td><?php echo fu_n($r['revenue']); ?></td>
          <td><?php echo fu_n($r['supplier_cost']); ?></td>
          <td class="fu-strong <?php echo $r['margin'] < 0 ? 'fu-warn' : 'fu-ok'; ?>">
              <?php echo fu_n($r['margin']); ?></td>
          <td><?php echo $r['margin_pct'] === null ? '—' : fu_n($r['margin_pct'], 1) . '٪'; ?></td>
          <td><?php echo $r['oec'] === null ? '<span class="text-muted">غير مسجلة</span>' : fu_n($r['oec']); ?></td>
          <td><?php echo $r['oec_yield_pct'] === null
                ? '<span class="text-muted">—</span>' : fu_n($r['oec_yield_pct'], 1) . '٪'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="text-muted fu-note">
      الهامش هنا من الوقائع المالية المنسوبة للمعدة (<code>equipment_id</code>) — وهو المقياس الصادق لأسطول
      جله مورد لا مملوك. ومردود رأس المال مقياس الأسطول المملوك، فيظهر حيث تسجل تكلفة الاقتناء.
    </p>
  </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>


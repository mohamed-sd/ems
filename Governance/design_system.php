<?php
/**
 * Governance/design_system.php — النظامُ التصميميُّ مرجعًا حيًّا من الكود
 * ───────────────────────────────────────────────────────────────────────────
 * UXW-01: المخرَجُ الثاني — «النظامُ التصميميُّ مرجعًا حيًّا من الكودِ لا صورًا».
 * تقرأ هذه الشاشةُ الرموزَ من assets/css/design-tokens.css نفسِه وتعرض
 * المكوّناتِ الحيةَ (الحالاتِ · الأزرارَ · الشاراتِ · بطاقةَ السقفِ الموقوف)
 * من أصنافِها المعتمدةِ — فما يظهر هنا هو ما يظهر في الشاشاتِ حرفًا.
 * قراءةٌ فقط — لا تكتب في شيء.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/ux_components.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$SCREEN         = 'Governance/design_system.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}

// شاشةُ قراءةٍ — لا POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(405); exit('شاشة عرض لا تكتب'); }

// ═══ ④ قراءةُ الرموزِ من ملفِها الحي — المصدرُ الكودُ لا لقطة ═══
$tokensCss = (string) @file_get_contents(__DIR__ . '/../assets/css/design-tokens.css');
$FAMILIES = array(
    'الألوان — الهويةُ والدلالة' => '/--(?:brand|success|warning|danger|info|gray|white|c-brand|c-state|c-nav)[\w-]*:\s*[^;]+/',
    'الخطوطُ والمقاسات'          => '/--(?:font|text|leading|weight)[\w-]*:\s*[^;]+/',
    'المسافات'                    => '/--space[\w-]*:\s*[^;]+/',
    'أنصافُ الأقطار'              => '/--radius[\w-]*:\s*[^;]+/',
    'الحدود'                      => '/--(?:border|table-border)[\w-]*:\s*[^;]+/',
    'الظلال'                      => '/--shadow[\w-]*:\s*[^;]+/',
    'مقاساتُ الأيقونات'           => '/--icon[\w-]*:\s*[^;]+/',
    'نقاطُ الانكسار'              => '/--bp[\w-]*:\s*[^;]+/',
    'طبقاتُ الترتيب'              => '/--z[\w-]*:\s*[^;]+/',
    'القشرةُ والحركة'             => '/--(?:sidebar|topbar|container|transition)[\w-]*:\s*[^;]+/',
);
$fam = array();
foreach ($FAMILIES as $name => $rx) {
    preg_match_all($rx, $tokensCss, $m);
    $fam[$name] = array_values(array_unique($m[0]));
}
$legacyCount = preg_match_all('/--c-(?:[0-9a-f]{6}|rgba\d+)/', $tokensCss);

$PAGE_TITLE = 'النظام التصميمي — مرجع حي من الكود';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-palette';
  $header_desc = 'كل ما هنا مقروء من assets/css/design-tokens.css ومكونات النظام الحية لحظة الفتح — لا صور ولا نسخ. وبعد الاعتماد لا قيمة خارج هذه العوائل (بوابة المنع ١).';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php echo ems_states_bundle('تعذرت قراءة ملف الرموز', 'تحقق من وجود assets/css/design-tokens.css'); ?>

  <h2>عوائل الرموز العشر — من الملف الحي</h2>
  <?php foreach ($fam as $name => $tokens): ?>
    <section class="ems-ds-family">
      <h3><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
        <span class="status-badge status-review"><?php echo count($tokens); ?> رمزا</span></h3>
      <div class="ems-ds-grid">
        <?php foreach ($tokens as $t):
            list($var, $val) = array_pad(explode(':', $t, 2), 2, '');
            $var = trim($var); $val = trim($val);
            $isColor = (bool) preg_match('/#[0-9a-fA-F]{3,8}|rgba?\(/', $val);
        ?>
        <div class="ems-ds-token">
          <?php if ($isColor): ?><span class="ems-ds-swatch" data-token="<?php echo htmlspecialchars($var, ENT_QUOTES, 'UTF-8'); ?>"></span><?php endif; ?>
          <code><?php echo htmlspecialchars($var, ENT_QUOTES, 'UTF-8'); ?></code>
          <small><?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="ems-ds-family">
    <h3>الذيل الموروث <span class="status-badge status-pending"><?php echo (int) $legacyCount; ?> لونا</span></h3>
    <p>دين مرئي لا لوحة تصميم — ألوان رفعت من الشاشات إلى الرموز حرفا وتنتظر التوحيد بقرار مالك أمام العين.</p>
  </section>

  <h2>المكونات الحية</h2>
  <section class="ems-ds-family">
    <h3>حالات الشاشة التسع (ems-states.css)</h3>
    <?php echo ems_state('empty', 'لا توجد بيانات لهذه الفترة', 'غير الفترة أو تحقق من توفر السجلات'); ?>
    <?php echo ems_state('error', 'تعذر عرض البيانات', 'أعد المحاولة — وإن استمر الخلل أبلغ عن مشكلة من هذه الشاشة'); ?>
    <?php echo ems_state('noperm', 'لا صلاحية لهذه الشاشة', 'اطلب المنحة من مدير الصلاحيات — وسبب المنع يكتب لا يخفى'); ?>
    <?php echo ems_state('readonly', 'قراءة فقط — التحرير من الشاشة المالكة'); ?>
    <?php echo ems_state('offline', 'غير متصل — عملك يحفظ محليا ويرفع حين تعود الشبكة'); ?>
    <?php echo ems_state('syncpending', 'مزامنة معلقة — 3 عمليات محفوظة لم ترفع بعد'); ?>
    <?php echo ems_state('stale', 'بيانات متقادمة — آخر تحديث قبل ساعتين'); ?>
  </section>

  <section class="ems-ds-family">
    <h3>الخطوة التالية وبطاقة السقف الموقوف</h3>
    <?php echo ems_next_step('مراجعة محاسب الموقع ثم اعتماد مدير الموقع'); ?>
    <?php echo ems_cap_hold_card('معاملة العرض التوضيحية'); ?>
  </section>

  <section class="ems-ds-family">
    <h3>الأزرار المعتمدة</h3>
    <div class="ems-ds-row">
      <button type="button" class="btn btn-primary">الإجراء الرئيسي</button>
      <button type="button" class="btn btn-secondary">ثانوي</button>
      <button type="button" class="btn btn-outline-primary">محدود</button>
      <button type="button" class="btn btn-danger">خطر</button>
      <button type="button" class="btn btn-sm btn-light">صغير</button>
    </div>
  </section>

  <section class="ems-ds-family">
    <h3>وسوم الحالة</h3>
    <div class="ems-ds-row">
      <span class="status-badge status-active">نشط</span>
      <span class="status-badge status-pending">بانتظار خطوة</span>
      <span class="status-badge status-review">قيد المراجعة</span>
      <span class="status-badge status-stopped">موقوف</span>
    </div>
  </section>

  <style>
    .ems-ds-family { margin-block: var(--space-6); padding: var(--space-5); border: var(--border-subtle); border-radius: var(--radius-lg); background: var(--white); }
    .ems-ds-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: var(--space-3); }
    .ems-ds-token { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2); border: var(--border-subtle); border-radius: var(--radius-sm); }
    .ems-ds-token small { color: var(--gray-500); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ems-ds-swatch { inline-size: var(--icon-lg); block-size: var(--icon-lg); border-radius: var(--radius-sm); border: var(--border-subtle); flex: none; }
    .ems-ds-row { display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; }
    .ems-ds-family .ems-state { margin-block: var(--space-3); }
  </style>
  <script>
    // تلوينُ العيّناتِ من الرمزِ نفسِه — القيمةُ تُقرأ من المتغيرِ الحي لا تُنسخ
    document.querySelectorAll('.ems-ds-swatch').forEach(function (el) {
      el.style.background = 'var(' + el.dataset.token + ')';
    });
  </script>
</div>

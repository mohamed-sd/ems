<?php
/**
 * Procurement/wh_count.php — الجردُ والتسويات (★ المخازن · update0007 S-09)
 * ───────────────────────────────────────────────────────────────────────────
 * الرصيدُ الدفتريُّ يُحسب من الحركات (لا عمودَ رصيدٍ يُعدَّل) — والجردُ
 * الفعليُّ يُدخل، والفرقُ يُسوّى بحركةِ «تسوية زيادة/عجز» بسببٍ موثَّق.
 */
// ═══ CS-01 · ترتيبُ الملفِّ الإلزاميّ ═══════════════════════════════════════
// جلسةٌ ← إعدادٌ ← حارسُ شاشةٍ ← حارسُ فعلٍ (العقدُ السبعي) ← معالجُ POST ←
// استعلاماتُ العرضِ ← ترويسةٌ ← سايدبارٌ ← جسم.
// ◆ RF-02: كان الإدراجُ في السطرِ 29 و‎insidebar‎ (الذي يُنفِّذ حارسَ العرض) في
//   السطرِ 56 — فيُرحَّل الأثرُ ثم يُقال للمستخدم «لا صلاحية». الحارسُ الآن فوقَه.
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Procurement/StockMoveService.php';

// ③ حارسُ الشاشةِ — قبلَ أيِّ معالجٍ وقبلَ أيِّ استعلام.
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$wh  = intval($_REQUEST['wh'] ?? 0);
$msg = '';

// ④+⑤ العقدُ السبعيُّ ثم الأثر — والخروجُ عند أيِّ فشلٍ قبلَ أيِّ كتابة.
$__pc = ems_post_contract($conn, array(
    'action'  => 'proc.stock.count_adjust',
    'perm'    => 'can_edit',
    'trigger' => 'adjust_item',
    'idem'    => array(
        'item'   => intval($_POST['adjust_item'] ?? 0),
        'wh'     => $wh,
        'actual' => (string) floatval($_POST['actual_qty'] ?? 0),
        'book'   => (string) floatval($_POST['book_qty'] ?? 0),
        'reason' => trim($_POST['reason'] ?? ''),
    ),
    'validate' => function (array $in) {
        $why  = trim($in['reason'] ?? '');
        $diff = floatval($in['actual_qty'] ?? 0) - floatval($in['book_qty'] ?? 0);
        if ($why === '')            { return array('ok' => false, 'msg' => 'سبب التسوية إلزامي (422)'); }
        if (abs($diff) < 0.001)     { return array('ok' => false, 'msg' => 'لا فرق — لا تسوية تكتب'); }
        if (intval($in['adjust_item'] ?? 0) <= 0) { return array('ok' => false, 'msg' => 'صنف غير صالح (422)'); }
        return array('ok' => true, 'data' => array(
            'item' => intval($in['adjust_item']), 'diff' => $diff, 'why' => $why,
        ));
    },
));
if ($__pc['run'] === false && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['run'] && !$__pc['ok'])                  { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    // CS-05: الشاشةُ لا تكتب جدولًا — تنادي خدمةَ نطاقها (الحكمُ والمعاملةُ فيها).
    $svc = new \App\Services\Procurement\StockMoveService($conn);
    $res = $svc->adjustCount($company_id, (int) $__pc['data']['item'], $wh,
        (float) $__pc['data']['diff'], (string) $__pc['data']['why'], $uid);
    $msg = $res['msg'];
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], 'proc_stock_move#' . $res['move_id']); }
}

$whs = array(); $rows = array();
$r = mysqli_query($conn, "SELECT id, name FROM proc_warehouse WHERE company_id=$company_id ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $whs[] = $x;
/* INJ-0561: مدى تاريخِ الحركاتِ — يُطبَّق في شرطِ الوصلِ لا في `WHERE` لأنَّ
   الوصلَ يساريّ: شرطٌ في `WHERE` يُقصي الأصنافَ بلا حركةٍ في الفترة، وهي
   معلومةٌ مطلوبةٌ في الجرد (رصيدٌ دفتريٌّ صفر). */
$__cFrom = isset($_GET['from']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['from']) ? $_GET['from'] : '';
$__cTo   = isset($_GET['to'])   && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['to'])   ? $_GET['to']   : '';
$__cJoin = '';
if ($__cFrom !== '') { $__cJoin .= " AND m.moved_at >= '" . $conn->real_escape_string($__cFrom) . " 00:00:00'"; }
if ($__cTo   !== '') { $__cJoin .= " AND m.moved_at <= '" . $conn->real_escape_string($__cTo)   . " 23:59:59'"; }
if ($wh > 0) {
    $r = mysqli_query($conn,
        "SELECT i.id, i.name,
                COALESCE(SUM(CASE WHEN m.move_type IN ('استلام','تحويل وارد','مرتجع','تسوية زيادة') THEN m.qty
                                  WHEN m.move_type IN ('صرف','تحويل صادر','تسوية عجز') THEN -m.qty ELSE 0 END),0) AS book
         FROM proc_item i
         LEFT JOIN proc_stock_move m ON m.item_id = i.id AND m.warehouse_id = $wh AND m.company_id = $company_id{$__cJoin}
         WHERE i.company_id = $company_id
         GROUP BY i.id, i.name HAVING book <> 0 OR COUNT(m.id) > 0
         ORDER BY i.name");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
}

$page_title = 'الجرد والتسويات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-clipboard-list';
$header_title_html = htmlspecialchars('الجرد والتسويات', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_wh_count
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الجلسة' => 'g79',
            'المخزن' => 'g80',
            'أسلوب الجرد' => 'g81',
            'تاريخ الجرد' => 'g82',
            'لجنة الجرد' => 'g83',
            'عدد الأصناف المجرودة' => 'g84',
            'بنود الفروق تفصيلها خ10-2' => 'g85',
            'مرجع التحقيق' => 'g86',
            'حالة الجلسة' => 'g87',
            'المنشئ' => 'g88',
            'تاريخ الإنشاء' => 'g89',
            'المراجع' => 'g90',
            'المعتمد' => 'g91',
            'تاريخ الاعتماد' => 'g92',
            'حالة البيانات' => 'g93',
            'مرجع المصدر' => 'g94',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('wh_count');
        echo ems_w14_grid('emsList_wh_count', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الجرد ومعالجة الفروقات'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا */
echo ems_states_bundle('لا أصناف للجرد في هذا الاختيار',
    'اختر المخزن وفترة الحركات — فيعرض الرصيد الدفتري المحسوب من الحركات ويجرى الجرد عليه');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php /* INJ-0561: كان الترشيحُ بالمخزنِ وحدَه — بلا فترةٍ رغم أن كلَّ حركةٍ
           تحمل `moved_at`. فأُضيف مدى تاريخٍ يُطبَّق في وصلِ الحركاتِ نفسِه،
           وعدّادُ الفلاترِ وزرُّ التفريغِ من شريطِ العُدَّةِ المشترك. */ ?>
  <form method="get" class="filter" data-ems-period="1">
    <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> المخزن وفترة الحركات</div>
    <div class="filter-body">
      <div class="filter-field"><label for="emsf_1349_f75ee">المخزن</label>
        <select name="wh" class="form-control" id="emsf_1349_f75ee"><option value="">—</option>
        <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>" <?= $w['id'] == $wh ? 'selected' : '' ?>><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="filter-field"><label for="wcFrom">من تاريخ</label>
        <input type="date" id="wcFrom" name="from" class="form-control" value="<?= htmlspecialchars($__cFrom, ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="filter-field"><label for="wcTo">إلى تاريخ</label>
        <input type="date" id="wcTo" name="to" class="form-control" value="<?= htmlspecialchars($__cTo, ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="filter-actions"><button type="submit" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button></div>
    </div>
  </form>
  <?php /* INJ-0564: كان الجدولُ لا يُصيَّر إلا بعد اختيارِ مخزنٍ — فالشاشةُ تُفتح
           بلا جدولٍ ولا تفسير. صار يُصيَّر دائمًا: الحالةُ الفارغةُ المشتركةُ
           تشرح ما ينقص (اختيارُ مخزن). */ ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>رقم الصنف</th><th>الدفتري (محسوب من الحركات)</th><th>الفعلي المجرود</th><th>سبب الفرق</th><th>قرار التسوية</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الجرد</th>
              <th class="ems-fn-th" data-fn="1">المخزن</th>
              <th class="ems-fn-th" data-fn="1">أسلوب الجرد</th>
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">اسم الصنف</th>
              <th class="ems-fn-th" data-fn="1">الرصيد الدفتري</th>
              <th class="ems-fn-th" data-fn="1">العد الفعلي</th>
              <th class="ems-fn-th" data-fn="1">الفرق</th>
              <th class="ems-fn-th" data-fn="1">نسبة الفرق</th>
              <th class="ems-fn-th" data-fn="1">قيمة الفرق</th>
              <th class="ems-fn-th" data-fn="1">رقم قيد التسوية</th>
              <th class="ems-fn-th" data-fn="1">عده</th>
              <th class="ems-fn-th" data-fn="1">راجعه</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
    <tbody>
    <?php foreach ($rows as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong><?= floatval($it['book']) ?></strong></td>
        <form method="post">
        <?= csrf_field() ?>
          <input type="hidden" name="adjust_item" value="<?= intval($it['id']) ?>">
          <input type="hidden" name="book_qty" value="<?= floatval($it['book']) ?>">
          <td><input type="number" step="0.01" name="actual_qty" class="form-control form-control-sm whc-qty-input" aria-label="الفعلي المجرود" value="<?= floatval($it['book']) ?>"></td>
          <td><input type="text" name="reason" class="form-control form-control-sm" placeholder="سبب الفرق" aria-label="سبب الفرق"></td>
          <td><button class="action-btn" type="submit">سو</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php /* ── RPR-W09 · WH-15 · WH-16 ─────────────────────────────────────────
       **الجردُ كان بلا جلسةٍ تُحفظ**: هذه الشاشةُ كانت تستعلم `proc_item`
       و`proc_warehouse` وحدَهما، وتكتب تسويةً مباشرةً في `proc_stock_move` —
       فلا رأسَ جلسةٍ ولا عادَّ ولا مراجعَ ولا معتمِد، **و«جلسةُ جردٍ × مخزن»
       في `WH-15` بلا كيانٍ يحملها**. وأُضيف `proc_count_session` وبنودُه،
       والفرقُ **لا يُقفل بلا قرارِ تسويةٍ مُسبَّب** (`COUNT_DIFF_WITHOUT_SETTLEMENT`).
       والدفتريُّ في البندِ **مشتقٌّ من الحركاتِ لا مكتوبٌ من العادّ**. */ ?>
  <?php
  $__w9ses = array(); $__w9lines = array(); $__w9open = 0;
  try {
      $__g9 = ems_tenant_db();
      $__w9ses = $__g9->select('proc_count_session', array('orderBy' => 'id DESC', 'limit' => 100));
      foreach ($__w9ses as $__s) {
          foreach ($__g9->select('proc_count_line', array('where' => array('session_id' => (int) $__s['id']), 'limit' => 500)) as $__l) {
              $__w9lines[] = $__l + array('ses' => (string) $__s['code']);
              if (abs((float) $__l['qty_diff']) > 0.0001
                  && (trim((string) $__l['settle_action']) === '' || trim((string) $__l['settle_why']) === '')) { $__w9open++; }
          }
      }
  } catch (\Throwable $__t) { error_log('wh_count w9 sessions: ' . $__t->getMessage()); }
  ?>
  <h3 class="ems-section-title">جلسات الجرد المسجلة</h3>
  <div class="ems-stat-cards">
    <div class="ems-stat-card"><div class="ems-stat-value"><?= count($__w9ses) ?></div><div class="ems-stat-label">جلسات جرد</div></div>
    <div class="ems-stat-card"><div class="ems-stat-value"><?= count($__w9lines) ?></div><div class="ems-stat-label">بنود مجرودة</div></div>
    <div class="ems-stat-card"><div class="ems-stat-value"><?= $__w9open ?></div><div class="ems-stat-label">فروق بلا قرار تسوية</div></div>
  </div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>رمز الجلسة</th><th>نوع الجرد</th><th>تاريخ الجرد</th><th>البنود</th><th>بنود بفرق</th><th>قيمة الفرق</th><th>الحالة</th></tr></thead>
    <tbody>
    <?php if ($__w9ses): foreach ($__w9ses as $__s): ?>
      <tr>
        <td><?= htmlspecialchars((string) $__s['code'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $__s['count_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $__s['count_date'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= intval($__s['line_count']) ?></td>
        <td><?= intval($__s['diff_count']) ?></td>
        <td><?= htmlspecialchars(number_format((float) $__s['diff_value'], 2), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string) $__s['state'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7">لا جلسات جرد مسجلة بعد</td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</div>

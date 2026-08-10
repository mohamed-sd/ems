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
        if ($why === '')            { return array('ok' => false, 'msg' => 'سببُ التسوية إلزامي (422)'); }
        if (abs($diff) < 0.001)     { return array('ok' => false, 'msg' => 'لا فرقَ — لا تسويةَ تُكتب'); }
        if (intval($in['adjust_item'] ?? 0) <= 0) { return array('ok' => false, 'msg' => 'صنفٌ غيرُ صالح (422)'); }
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
if ($wh > 0) {
    $r = mysqli_query($conn,
        "SELECT i.id, i.name,
                COALESCE(SUM(CASE WHEN m.move_type IN ('استلام','تحويل وارد','مرتجع','تسوية زيادة') THEN m.qty
                                  WHEN m.move_type IN ('صرف','تحويل صادر','تسوية عجز') THEN -m.qty ELSE 0 END),0) AS book
         FROM proc_item i
         LEFT JOIN proc_stock_move m ON m.item_id = i.id AND m.warehouse_id = $wh AND m.company_id = $company_id
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
$header_title_html = htmlspecialchars('الجردُ والتسويات', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="get" class="ems-form" style="display:flex;gap:10px;align-items:end;margin-bottom:14px">
    <div><label for="emsf_1349_f75ee">المخزن</label><select name="wh" class="form-control" onchange="this.form.submit()" id="emsf_1349_f75ee"><option value="">—</option>
      <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>" <?= $w['id'] == $wh ? 'selected' : '' ?>><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
  </form>
  <?php if ($wh > 0): ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>رقم الصنف</th><th>الدفتري (محسوبٌ من الحركات)</th><th>الفعليُّ المجرود</th><th>سبب الفرق</th><th>قرار التسوية</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الجرد</th>
              <th class="ems-fn-th" data-fn="1">المخزن</th>
              <th class="ems-fn-th" data-fn="1">أسلوب الجرد</th>
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">اسم الصنف</th>
              <th class="ems-fn-th" data-fn="1">الرصيد الدفتري</th>
              <th class="ems-fn-th" data-fn="1">العدّ الفعلي</th>
              <th class="ems-fn-th" data-fn="1">الفرق</th>
              <th class="ems-fn-th" data-fn="1">نسبة الفرق</th>
              <th class="ems-fn-th" data-fn="1">قيمة الفرق</th>
              <th class="ems-fn-th" data-fn="1">رقم قيد التسوية</th>
              <th class="ems-fn-th" data-fn="1">عدّه</th>
              <th class="ems-fn-th" data-fn="1">راجعه</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
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
          <input type="hidden" name="adjust_item" value="<?= intval($it['id']) ?>">
          <input type="hidden" name="book_qty" value="<?= floatval($it['book']) ?>">
          <td><input type="number" step="0.01" name="actual_qty" class="form-control form-control-sm" value="<?= floatval($it['book']) ?>" style="max-width:110px"></td>
          <td><input type="text" name="reason" class="form-control form-control-sm" placeholder="سببُ الفرق" aria-label="سببُ الفرق"></td>
          <td><button class="action-btn" type="submit">سوِّ</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

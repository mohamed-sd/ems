<?php
/**
 * Procurement/wh_transfer.php — التحويلُ بين المخازن (★ المخازن · update0007 S-09)
 * ───────────────────────────────────────────────────────────────────────────
 * حركتان ذريّتان في معاملةٍ واحدة: «تحويل صادر» من المصدر و«تحويل وارد» إلى
 * الوجهة — بمرجعٍ واحدٍ يربطهما، فلا يظهر صنفٌ في مخزنين ولا يختفي من كليهما.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Procurement/StockMoveService.php';

// CS-01 · RF-02 — حارسُ الشاشةِ فوقَ المعالج. كانت الحركتان تُكتبان في السطرين
// 34 و36 و‎insidebar‎ (منفِّذُ الحارس) في السطرِ 60 — أثرٌ قبلَ إذن.
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

$__pc = ems_post_contract($conn, array(
    'action'  => 'proc.stock.warehouse_transfer',
    'perm'    => 'can_edit',
    'trigger' => 'item_id',
    'idem'    => array(
        'item' => intval($_POST['item_id'] ?? 0),
        'from' => intval($_POST['from_wh'] ?? 0),
        'to'   => intval($_POST['to_wh'] ?? 0),
        'qty'  => (string) floatval($_POST['qty'] ?? 0),
    ),
    'validate' => function (array $in) {
        $item = intval($in['item_id'] ?? 0); $from = intval($in['from_wh'] ?? 0);
        $to   = intval($in['to_wh'] ?? 0);   $qty  = floatval($in['qty'] ?? 0);
        if ($item <= 0 || $from <= 0 || $to <= 0 || $qty <= 0) {
            return array('ok' => false, 'msg' => 'الصنف والمخزنان والكمية إلزامية (422)');
        }
        if ($from === $to) { return array('ok' => false, 'msg' => 'المصدر والوجهة مخزن واحد (422)'); }
        return array('ok' => true, 'data' => compact('item', 'from', 'to', 'qty'));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    // CS-05: الحكمُ والمعاملةُ والقفلُ في خدمةِ النطاقِ لا في السطح.
    $svc = new \App\Services\Procurement\StockMoveService($conn);
    $res = $svc->transfer($company_id, (int) $__pc['data']['item'], (int) $__pc['data']['from'],
        (int) $__pc['data']['to'], (float) $__pc['data']['qty'], $__pc['idem'], $uid);
    $msg = $res['msg'];
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], $res['ref']); }
}

$items = array(); $whs = array();
$r = mysqli_query($conn, "SELECT id, name FROM proc_item WHERE company_id=$company_id ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $items[] = $x;
$r = mysqli_query($conn, "SELECT id, name FROM proc_warehouse WHERE company_id=$company_id ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $whs[] = $x;
$recent = array();
$r = mysqli_query($conn, "SELECT m.moved_at, i.name item, w.name wh, m.move_type, m.qty, m.note
                          FROM proc_stock_move m JOIN proc_item i ON i.id=m.item_id JOIN proc_warehouse w ON w.id=m.warehouse_id
                          WHERE m.company_id=$company_id AND m.ref_type='wh_transfer' ORDER BY m.id DESC LIMIT 20");
if ($r) while ($x = mysqli_fetch_assoc($r)) $recent[] = $x;

$page_title = 'التحويل بين المخازن';
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
$header_icon = 'fa fa-random';
$header_title_html = htmlspecialchars('التحويل بين المخازن', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا */
echo ems_states_bundle('لا تحويلات بين المخازن بعد',
    'سجل التحويل من النموذج أعلاه — حركتان ذريتان بمرجع واحد فلا يظهر الصنف في مخزنين');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="ems-form wht-form">
        <?= csrf_field() ?>
    <div><label for="emsf_1350_8dfe1">الصنف</label><select name="item_id" class="form-control" required id="emsf_1350_8dfe1"><option value="">—</option>
      <?php foreach ($items as $i): ?><option value="<?= intval($i['id']) ?>"><?= htmlspecialchars($i['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label for="emsf_1351_042e3">من مخزن</label><select name="from_wh" class="form-control" required id="emsf_1351_042e3"><option value="">—</option>
      <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>"><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label for="emsf_1352_887b9">إلى مخزن</label><select name="to_wh" class="form-control" required id="emsf_1352_887b9"><option value="">—</option>
      <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>"><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label for="emsf_1353_4bc3c">الكمية</label><input type="number" step="0.01" name="qty" class="form-control wht-qty-input" required id="emsf_1353_4bc3c"></div>
    <button class="btn btn-primary">حول</button>
  </form>
  <h6>آخر التحويلات</h6>
  <table class="table table-sm" data-no-dt>
    <thead><tr><th>الوقت</th><th>رقم الصنف</th><th>من مخزن</th><th>الحركة</th><th>الكمية</th><th>مرجع التفويض</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الأمر</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإصدار</th>
              <th class="ems-fn-th" data-fn="1">إلى مخزن</th>
              <th class="ems-fn-th" data-fn="1">سبب التحويل</th>
              <th class="ems-fn-th" data-fn="1">الوحدة</th>
              <th class="ems-fn-th" data-fn="1">قيمة التحويل</th>
              <th class="ems-fn-th" data-fn="1">الناقل</th>
              <th class="ems-fn-th" data-fn="1">أمر الترحيل المرتبط</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الخروج</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الاستلام</th>
              <th class="ems-fn-th" data-fn="1">المستلم</th>
              <th class="ems-fn-th" data-fn="1">أصدره</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody><?php foreach ($recent as $m2): ?>
      <tr><td><?= htmlspecialchars($m2['moved_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($m2['item'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($m2['wh'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($m2['move_type'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= floatval($m2['qty']) ?></td>
          <td><?= htmlspecialchars($m2['note'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</div>

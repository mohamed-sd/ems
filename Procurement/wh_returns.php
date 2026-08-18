<?php
/**
 * Procurement/wh_returns.php — المرتجعات (★ المخازن · update0007 S-09)
 * ───────────────────────────────────────────────────────────────────────────
 * مرتجعُ صرفٍ يعود للمخزن بحركة «مرتجع» بمرجع سندِ الصرف الأصلي —
 * فلا رصيدَ يُعدَّل يدويًّا ولا مرتجعَ بلا أصلٍ يُرجَع إليه.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Procurement/StockReturnService.php';

// CS-01 · RF-02 — الحارسُ فوقَ المعالج.
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

/* ══ FN-07 (P0) — «المرتجعُ يقارن سطرًا واحدًا ولا يطرح ما سبق» ═══════════
   كان التحققُ يقرأ **سطرًا واحدًا** من سندِ الصرف (‎LIMIT 1‎) ويقارن به، ولا
   يطرح ما أُرجع قبلَه — فيمكن إرجاعُ أكثرَ من المصروفِ بتكرارِ الإرجاع، وهي
   ثغرةُ كميةٍ تُخرج مخزونًا بلا سند. الحكمُ الآن في خدمةِ النطاقِ (CS-05):
   مجموعُ المصروفِ للصنفِ في السند − مجموعُ ما أُرجع = المتاحُ للإرجاع.
   وقيدُ القاعدةِ (قادحٌ) يرفض التجاوزَ ولو تُجوِّز التطبيق (RSK-M3). */
$__pc = ems_post_contract($conn, array(
    'action'  => 'proc.stock.return_to_warehouse',
    'perm'    => 'can_add',
    'trigger' => 'issue_line',
    'idem'    => array(
        'line'   => (string) ($_POST['issue_line'] ?? ''),
        'qty'    => (string) floatval($_POST['qty'] ?? 0),
        'reason' => trim($_POST['reason'] ?? ''),
    ),
    'validate' => function (array $in) {
        $line = (string) ($in['issue_line'] ?? '');
        $qty  = floatval($in['qty'] ?? 0);
        $why  = trim($in['reason'] ?? '');
        if ($line === '' || $qty <= 0 || $why === '') {
            return array('ok' => false, 'msg' => 'سندُ الصرف والكميةُ والسببُ إلزامية (422)');
        }
        $p = explode(':', $line);
        if (count($p) !== 2 || (int) $p[0] <= 0 || (int) $p[1] <= 0) {
            return array('ok' => false, 'msg' => 'مرجعُ سطرِ الصرف غيرُ صالح (422)');
        }
        return array('ok' => true, 'data' => array(
            'issue' => (int) $p[0], 'item' => (int) $p[1], 'qty' => $qty, 'why' => $why,
        ));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $svc = new \App\Services\Procurement\StockReturnService($conn);
    $res = $svc->returnToWarehouse(
        $company_id, (int) $__pc['data']['issue'], (int) $__pc['data']['item'],
        (float) $__pc['data']['qty'], (string) $__pc['data']['why'], $uid
    );
    $msg = $res['msg'];
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], 'proc_stock_move#' . $res['move_id']); }
}

/* قائمةُ سطورِ الصرفِ مع **المتاحِ للإرجاع** لكلِّ سطرٍ — فالرقمُ يُرى قبلَ
   المحاولةِ لا بعدَ الرفض. والقيمةُ ‎سند:صنف‎ لأن السندَ قد يحمل الصنفَ في
   أكثرَ من سطر (وكانت القائمةُ تُكرِّر السندَ بقيمةٍ واحدةٍ فتُخلَط السطور). */
$issues = array();
$r = mysqli_query($conn, "SELECT i.id, i.issue_no, il.item_id, it.name item,
                                 SUM(il.qty) issued,
                                 COALESCE((SELECT SUM(m.qty) FROM proc_stock_move m
                                            WHERE m.company_id = i.company_id AND m.move_type='مرتجع'
                                              AND m.ref_type='issue' AND m.ref_id = i.id
                                              AND m.item_id = il.item_id),0) returned
                          FROM proc_issue i
                          JOIN proc_issue_line il ON il.issue_id = i.id
                          JOIN proc_item it ON it.id = il.item_id
                          WHERE i.company_id = $company_id
                          GROUP BY i.id, i.issue_no, il.item_id, it.name
                          ORDER BY i.id DESC LIMIT 40");
if ($r) while ($x = mysqli_fetch_assoc($r)) { $x['available'] = floatval($x['issued']) - floatval($x['returned']); $issues[] = $x; }
$recent = array();
$r = mysqli_query($conn, "SELECT m.moved_at, i.name item, m.qty, m.ref_id, m.note
                          FROM proc_stock_move m JOIN proc_item i ON i.id=m.item_id
                          WHERE m.company_id=$company_id AND m.move_type='مرتجع' ORDER BY m.id DESC LIMIT 20");
if ($r) while ($x = mysqli_fetch_assoc($r)) $recent[] = $x;

$page_title = 'المرتجعات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ②: أنماطُ هذه الشاشةِ الثابتةُ أصنافًا ببادئةِ الشاشة */
.proc-wr-form { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; margin-bottom: 16px; }
.proc-wr-qty  { max-width: 110px; }
</style>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-undo';
$header_title_html = htmlspecialchars('المرتجعات', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا مرتجعَ مسجَّلًا بعدُ',
                       'اختر سطرَ سندِ صرفٍ من القائمةِ أعلاه وسجّلْ كميةَ الإرجاعِ وسببَها');
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="ems-form proc-wr-form">
        <?= csrf_field() ?>
    <div><label for="wr_issue_line">سطرُ سندِ الصرف الأصلي</label>
      <select id="wr_issue_line" name="issue_line" class="form-control" required><option value="">—</option>
      <?php foreach ($issues as $i): if ($i['available'] <= 0) { continue; } ?>
        <option value="<?= intval($i['id']) ?>:<?= intval($i['item_id']) ?>"><?= htmlspecialchars(
            ($i['issue_no'] ?: '#' . $i['id']) . ' — ' . $i['item']
            . ' · مصروف ' . (float) $i['issued']
            . ' · أُرجع ' . (float) $i['returned']
            . ' · المتاح ' . (float) $i['available'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?></select></div>
    <div><label for="wr_qty">الكميةُ المرتجعة</label><input id="wr_qty" type="number" step="0.01" min="0.01" name="qty" class="form-control proc-wr-qty" required></div>
    <div><label for="wr_reason">السبب</label><input id="wr_reason" type="text" name="reason" class="form-control" required></div>
    <button class="btn btn-primary">أرجع</button>
  </form>
  <h6>آخرُ المرتجعات</h6>
  <table class="table table-sm" data-no-dt>
    <thead><tr><th>الوقت</th><th>الصنف</th><th>الكمية</th><th>مرجعُ الصرف</th><th>السبب</th>
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
    <tbody><?php foreach ($recent as $m2): ?>
      <tr><td><?= htmlspecialchars($m2['moved_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($m2['item'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= floatval($m2['qty']) ?></td>
          <td>#<?= intval($m2['ref_id']) ?></td>
          <td><?= htmlspecialchars($m2['note'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</div>

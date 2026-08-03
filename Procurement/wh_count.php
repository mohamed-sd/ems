<?php
/**
 * Procurement/wh_count.php — الجردُ والتسويات (★ المخازن · update0007 S-09)
 * ───────────────────────────────────────────────────────────────────────────
 * الرصيدُ الدفتريُّ يُحسب من الحركات (لا عمودَ رصيدٍ يُعدَّل) — والجردُ
 * الفعليُّ يُدخل، والفرقُ يُسوّى بحركةِ «تسوية زيادة/عجز» بسببٍ موثَّق.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$wh  = intval($_REQUEST['wh'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['adjust_item'])) {
    $item   = intval($_POST['adjust_item']);
    $actual = floatval($_POST['actual_qty'] ?? 0);
    $book   = floatval($_POST['book_qty'] ?? 0);
    $why    = trim($_POST['reason'] ?? '');
    $diff   = $actual - $book;
    if ($why === '') { $msg = 'سببُ التسوية إلزامي (422)'; }
    elseif (abs($diff) < 0.001) { $msg = 'لا فرقَ — لا تسويةَ تُكتب'; }
    else {
        $type = $diff > 0 ? 'تسوية زيادة' : 'تسوية عجز';
        $ok = mysqli_query($conn, "INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
              VALUES ($company_id,$item,$wh,'$type'," . abs($diff) . ",'stock_count','" . mysqli_real_escape_string($conn, $why) . "',NOW(),$uid)");
        $msg = $ok ? "سُوّي الفرق (" . ($diff > 0 ? '+' : '−') . abs($diff) . ") بحركة «{$type}»" : 'فشل: ' . mysqli_error($conn);
    }
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
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-clipboard-list"></i> الجردُ والتسويات</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="get" class="ems-form" style="display:flex;gap:10px;align-items:end;margin-bottom:14px">
    <div><label>المخزن</label><select name="wh" class="form-control" onchange="this.form.submit()"><option value="">—</option>
      <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>" <?= $w['id'] == $wh ? 'selected' : '' ?>><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
  </form>
  <?php if ($wh > 0): ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الصنف</th><th>الدفتري (محسوبٌ من الحركات)</th><th>الفعليُّ المجرود</th><th>السبب</th><th>تسوية</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
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
          <td><input type="text" name="reason" class="form-control form-control-sm" placeholder="سببُ الفرق"></td>
          <td><button class="action-btn" type="submit">سوِّ</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

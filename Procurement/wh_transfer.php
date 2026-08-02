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

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $item = intval($_POST['item_id'] ?? 0);
    $from = intval($_POST['from_wh'] ?? 0);
    $to   = intval($_POST['to_wh'] ?? 0);
    $qty  = floatval($_POST['qty'] ?? 0);
    if ($item <= 0 || $from <= 0 || $to <= 0 || $qty <= 0) { $msg = 'الصنفُ والمخزنان والكميةُ إلزامية (422)'; }
    elseif ($from === $to) { $msg = 'المصدرُ والوجهةُ مخزنٌ واحد (422)'; }
    else {
        // الرصيدُ المتاحُ في المصدر يُحسب من الحركات — لا عمودَ رصيدٍ يُعدَّل
        $r = mysqli_query($conn, "SELECT COALESCE(SUM(CASE WHEN move_type IN ('استلام','تحويل وارد','مرتجع','تسوية زيادة') THEN qty ELSE -qty END),0) b
                                  FROM proc_stock_move WHERE company_id=$company_id AND item_id=$item AND warehouse_id=$from");
        $bal = $r ? floatval(mysqli_fetch_assoc($r)['b']) : 0;
        if ($bal < $qty) { $msg = "الرصيدُ المتاحُ في المصدر $bal فقط — والتحويلُ يُرفض 409"; }
        else {
            mysqli_begin_transaction($conn);
            $ref = 'TRF-' . date('ymd-His');
            $ok1 = mysqli_query($conn, "INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
                    VALUES ($company_id,$item,$from,'تحويل صادر',$qty,'wh_transfer','$ref',NOW(),$uid)");
            $ok2 = mysqli_query($conn, "INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
                    VALUES ($company_id,$item,$to,'تحويل وارد',$qty,'wh_transfer','$ref',NOW(),$uid)");
            if ($ok1 && $ok2) { mysqli_commit($conn); $msg = "حُوّل $qty بمرجع $ref — حركتان ذريّتان"; }
            else { mysqli_rollback($conn); $msg = 'فشلت المعاملةُ فأُلغيت الحركتان معًا: ' . mysqli_error($conn); }
        }
    }
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
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-random"></i> التحويلُ بين المخازن</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="ems-form" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:16px">
    <div><label>الصنف</label><select name="item_id" class="form-control" required><option value="">—</option>
      <?php foreach ($items as $i): ?><option value="<?= intval($i['id']) ?>"><?= htmlspecialchars($i['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label>من مخزن</label><select name="from_wh" class="form-control" required><option value="">—</option>
      <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>"><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label>إلى مخزن</label><select name="to_wh" class="form-control" required><option value="">—</option>
      <?php foreach ($whs as $w): ?><option value="<?= intval($w['id']) ?>"><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label>الكمية</label><input type="number" step="0.01" name="qty" class="form-control" required style="max-width:110px"></div>
    <button class="btn btn-primary">حوّل</button>
  </form>
  <h6>آخرُ التحويلات</h6>
  <table class="table table-sm" data-no-dt>
    <thead><tr><th>الوقت</th><th>الصنف</th><th>المخزن</th><th>الحركة</th><th>الكمية</th><th>المرجع</th></tr></thead>
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

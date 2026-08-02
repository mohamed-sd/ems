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

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $issue = intval($_POST['issue_id'] ?? 0);
    $qty   = floatval($_POST['qty'] ?? 0);
    $why   = trim($_POST['reason'] ?? '');
    if ($issue <= 0 || $qty <= 0 || $why === '') { $msg = 'سندُ الصرف والكميةُ والسببُ إلزامية (422)'; }
    else {
        // أصلُ الصرف: صنفُه ومخزنُه وكميتُه — والمرتجعُ لا يتجاوز المصروف
        $r = mysqli_query($conn, "SELECT il.item_id, i.warehouse_id, il.qty
                                  FROM proc_issue_line il JOIN proc_issue i ON i.id = il.issue_id
                                  WHERE il.issue_id = $issue AND i.company_id = $company_id LIMIT 1");
        if (!$r || !($src = mysqli_fetch_assoc($r))) { $msg = 'سندُ صرفٍ غيرُ موجود (404)'; }
        elseif ($qty > floatval($src['qty'])) { $msg = 'المرتجعُ يتجاوز المصروفَ الأصلي — يُرفض 409'; }
        else {
            $ok = mysqli_query($conn, "INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,ref_type,ref_id,note,moved_at,created_by)
                  VALUES ($company_id,{$src['item_id']},{$src['warehouse_id']},'مرتجع',$qty,'issue',$issue,'" . mysqli_real_escape_string($conn, $why) . "',NOW(),$uid)");
            $msg = $ok ? "أُرجع $qty بمرجع سند الصرف #$issue" : 'فشل: ' . mysqli_error($conn);
        }
    }
}

$issues = array();
$r = mysqli_query($conn, "SELECT i.id, i.issue_no, it.name item, il.qty
                          FROM proc_issue i JOIN proc_issue_line il ON il.issue_id=i.id
                          JOIN proc_item it ON it.id=il.item_id
                          WHERE i.company_id=$company_id ORDER BY i.id DESC LIMIT 40");
if ($r) while ($x = mysqli_fetch_assoc($r)) $issues[] = $x;
$recent = array();
$r = mysqli_query($conn, "SELECT m.moved_at, i.name item, m.qty, m.ref_id, m.note
                          FROM proc_stock_move m JOIN proc_item i ON i.id=m.item_id
                          WHERE m.company_id=$company_id AND m.move_type='مرتجع' ORDER BY m.id DESC LIMIT 20");
if ($r) while ($x = mysqli_fetch_assoc($r)) $recent[] = $x;

$page_title = 'المرتجعات';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-undo"></i> المرتجعات</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="ems-form" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:16px">
    <div><label>سندُ الصرف الأصلي</label><select name="issue_id" class="form-control" required><option value="">—</option>
      <?php foreach ($issues as $i): ?>
        <option value="<?= intval($i['id']) ?>"><?= htmlspecialchars(($i['issue_no'] ?: '#' . $i['id']) . ' — ' . $i['item'] . ' (' . $i['qty'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?></select></div>
    <div><label>الكميةُ المرتجعة</label><input type="number" step="0.01" name="qty" class="form-control" required style="max-width:110px"></div>
    <div><label>السبب</label><input type="text" name="reason" class="form-control" required></div>
    <button class="btn btn-primary">أرجع</button>
  </form>
  <h6>آخرُ المرتجعات</h6>
  <table class="table table-sm" data-no-dt>
    <thead><tr><th>الوقت</th><th>الصنف</th><th>الكمية</th><th>مرجعُ الصرف</th><th>السبب</th></tr></thead>
    <tbody><?php foreach ($recent as $m2): ?>
      <tr><td><?= htmlspecialchars($m2['moved_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($m2['item'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= floatval($m2['qty']) ?></td>
          <td>#<?= intval($m2['ref_id']) ?></td>
          <td><?= htmlspecialchars($m2['note'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</div>

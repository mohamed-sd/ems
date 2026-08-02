<?php
/**
 * Tickets/intake_classify.php — الاستقبالُ والتصنيف (★ المركز · update0007-ب F9)
 * «يصحّح تصنيفًا خاطئًا وُجّه به البلاغُ إلى غير أهله» (TKT-01 §9-⑤) —
 * الجديدةُ وغيرُ المصنَّفة تُصنَّف فيُعاد توجيهُها آليًّا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx = tkt_ctx();
$company_id = $ctx['company_id'];
$uid = $ctx['user_id'];
if (intval($ctx['role']) !== 24 && !$ctx['is_super']) { http_response_code(403); die('التصنيفُ لمركز البلاغات (403)'); }
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['classify_tk'])) {
    $tid = intval($_POST['classify_tk']);
    $cat = intval($_POST['category_id'] ?? 0);
    $typ = intval($_POST['type_id'] ?? 0);
    if ($cat <= 0 || $typ <= 0) { $msg = 'الفئةُ والنوعُ إلزاميان (422)'; }
    else {
        $st = mysqli_prepare($conn, "UPDATE tickets SET category_id = ?, ticket_type_id = ?, stage = 'classified'
                                     WHERE id = ? AND company_id = ? AND stage IN ('new','classified')");
        mysqli_stmt_bind_param($st, 'iiii', $cat, $typ, $tid, $company_id);
        mysqli_stmt_execute($st);
        if (mysqli_stmt_affected_rows($st) > 0) {
            mysqli_query($conn, "INSERT INTO ticket_events (company_id, ticket_id, event_type, body, actor_user_id)
                                 VALUES ($company_id, $tid, 'reclassified', 'صُنّف من شاشة الاستقبال', $uid)");
            $msg = "صُنّف البلاغُ #$tid وسيُعاد توجيهُه آليًّا";
        } else { $msg = 'تجاوز مرحلةَ التصنيف (409)'; }
    }
}

$rows = array();
$r = mysqli_query($conn, "SELECT t.id, t.ticket_no, t.complaint, t.created_at, t.stage,
                                 c.name cat_name, tt.name type_name
                          FROM tickets t
                          LEFT JOIN ticket_categories c ON c.id = t.category_id
                          LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                          WHERE t.company_id = $company_id AND (t.stage = 'new' OR t.category_id IS NULL)
                          ORDER BY t.created_at LIMIT 60");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
$cats = array(); $types = array();
$r = mysqli_query($conn, "SELECT id, name FROM ticket_categories ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $cats[] = $x;
$r = mysqli_query($conn, "SELECT id, name FROM ticket_types WHERE active = 1 ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $types[] = $x;

$page_title = 'الاستقبال والتصنيف';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-inbox"></i> الاستقبالُ والتصنيف — الجديدةُ وغيرُ المصنَّفة</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>البلاغ</th><th>الوصف</th><th>منذ</th><th>تصنيفُه الحالي</th><th>التصنيف</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center" style="color:#198754">✔ لا جديدَ بلا تصنيف</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><a href="tickets_list.php?open=<?= intval($t['id']) ?>"><?= htmlspecialchars($t['ticket_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars(mb_substr($t['complaint'], 0, 55), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr($t['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(($t['cat_name'] !== null ? $t['cat_name'] : '؟') . ' / ' . ($t['type_name'] !== null ? $t['type_name'] : '؟'), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="classify_tk" value="<?= intval($t['id']) ?>">
            <select name="category_id" class="form-control form-control-sm" required style="max-width:130px">
              <option value="">— الفئة —</option>
              <?php foreach ($cats as $c2): ?><option value="<?= intval($c2['id']) ?>"><?= htmlspecialchars($c2['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <select name="type_id" class="form-control form-control-sm" required style="max-width:130px">
              <option value="">— النوع —</option>
              <?php foreach ($types as $t2): ?><option value="<?= intval($t2['id']) ?>"><?= htmlspecialchars($t2['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <button class="action-btn" type="submit">صنّف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

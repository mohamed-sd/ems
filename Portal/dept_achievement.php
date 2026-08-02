<?php
/**
 * Portal/dept_achievement.php — إنجازُ الإدارة (NAV-01 v6 §7-④ · update0007 S-05)
 * ───────────────────────────────────────────────────────────────────────────
 * «مجموعةٌ في كل قائمةِ إدارةٍ تعرض إنجازَ الإدارة — لا إنجازَ الفرد؛
 * فإنجازُ الفرد شخصيٌّ وإنجازُ الإدارة إداري.»
 *
 * يجمع من مصادر الدورة الحية بحسب وحدة إدارة المستخدم (خريطةُ dept_inbox):
 * وحداتٌ معتمدة · بلاغاتٌ أُغلقت · أوامرُ صيانةٍ أُقفلت — بمدةٍ حرة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../Tickets/tkt_helpers.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role       = intval($_SESSION['user']['role'] ?? 0);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '')   ? $_GET['to']   : date('Y-m-d');

/* وحدةُ الإدارة من خريطة الدور (المصححة في update0007 G-04) */
require_once __DIR__ . '/../Tickets/dept_inbox_map.php';
$unit = ems_dept_unit_of_role($role);

$q = function ($sql) use ($conn) { $r = mysqli_query($conn, $sql);
    return $r ? floatval(mysqli_fetch_row($r)[0] ?? 0) : 0; };
$cw = "company_id = $company_id";

$metrics = array(
    array('وحداتٌ معتمدةٌ في المدة', $q("SELECT COUNT(*) FROM unit_entries WHERE $cw
          AND state IN ('site_approved','parties_approved','sales_approved','converted')
          AND entry_date BETWEEN '$from' AND '$to'")),
    array('ساعاتٌ منفَّذةٌ معتمدة', $q("SELECT COALESCE(SUM(qty),0) FROM unit_entries WHERE $cw
          AND state IN ('site_approved','parties_approved','sales_approved','converted')
          AND entry_date BETWEEN '$from' AND '$to'")),
    array('مساراتُ بلاغاتٍ أغلقتها الإدارة', $q("SELECT COUNT(*) FROM ticket_workstreams ws
          JOIN tickets t ON t.id = ws.tk_id AND t.$cw
          WHERE ws.org_unit_id = " . intval($unit) . " AND ws.state = 'closed'
          AND ws.closed_at BETWEEN '$from' AND '$to 23:59:59'")),
    array('أوامرُ صيانةٍ أُقفلت', $q("SELECT COUNT(*) FROM mnt_order WHERE $cw
          AND state = 'إغلاق' AND updated_at BETWEEN '$from' AND '$to 23:59:59'")),
);

$page_title = 'إنجاز الإدارة';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-chart-line"></i> إنجازُ الإدارة</h4></div>
  <form method="get" class="ems-form" style="display:flex;gap:8px;align-items:end;margin-bottom:14px">
    <div><label>من</label><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
    <div><label>إلى</label><input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
    <button class="btn btn-primary">عرض</button>
  </form>
  <div style="display:flex;flex-wrap:wrap;gap:12px">
    <?php foreach ($metrics as $m2): ?>
    <div style="min-width:200px;padding:14px;border:1px solid #dee2e6;border-radius:8px;background:#f8f9fa">
      <div style="font-size:1.6em;font-weight:bold"><?= number_format($m2[1], is_float($m2[1]) && $m2[1] != intval($m2[1]) ? 1 : 0) ?></div>
      <div class="text-muted"><?= $m2[0] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="text-muted" style="font-size:.85em;margin-top:12px">إنجازُ الفرد في «إنجازي» بمساحة عملي — وهذا إنجازُ الإدارة (NAV-01 §7-④).</p>
</div>

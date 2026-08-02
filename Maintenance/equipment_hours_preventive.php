<?php
/**
 * Maintenance/equipment_hours_preventive.php — ساعاتُ المعدة والوقائية
 * (NAV-01 v6 §6.1 «قاعدةُ الصيانة» · update0007 S-06)
 * ───────────────────────────────────────────────────────────────────────────
 * «التايم شيتُ عند الصيانة ليس نسخةً — هو الساعاتُ المتراكمةُ للمعدة مقابل
 * جدول الغيار: فيظهر (متبقٍّ ثلاثون ساعةً على الغيار) لا (مَن عمل اليوم).
 * البياناتُ واحدةٌ والسؤالُ مختلف.»
 *
 * المصادر: unit_entries (ساعاتُ المعدة المعتمدة) · mnt_plan (خططُ الوقائية
 * بأساس العدّاد last_done_meter/interval_value).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = !empty($_SESSION['user']['is_super_admin']);
$cw = $is_super ? '1=1' : "mp.company_id = $company_id";

/* لكل خطةٍ وقائيةٍ بأساس العدّاد: المتراكمُ منذ آخر غيارٍ والمتبقي */
$rows = array();
$sql = "SELECT mp.id, mp.name AS plan_name, mp.interval_value, mp.tolerance,
               mp.last_done_meter, mp.last_done_date, mp.next_due_meter,
               e.id AS eq_id, e.name AS eq_name,
               COALESCE((SELECT SUM(ue.qty) FROM unit_entries ue
                         WHERE ue.equipment_id = e.id AND ue.state IN ('approved','converted')
                           AND (mp.last_done_date IS NULL OR ue.entry_date > mp.last_done_date)), 0) AS hours_since
        FROM mnt_plan mp
        JOIN equipments e ON e.id = mp.equipment_id
        WHERE $cw AND mp.is_deleted = 0 AND mp.state <> 'Retired'
          AND mp.trigger_basis IN ('meter','hours','Meter')
        ORDER BY (mp.interval_value - COALESCE((SELECT SUM(ue.qty) FROM unit_entries ue
                  WHERE ue.equipment_id = e.id AND ue.state IN ('approved','converted')
                    AND (mp.last_done_date IS NULL OR ue.entry_date > mp.last_done_date)), 0)) ASC";
$res = mysqli_query($conn, $sql);
if ($res) { while ($x = mysqli_fetch_assoc($res)) $rows[] = $x; }

$page_title = 'ساعاتُ المعدة والوقائية';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-stopwatch"></i> ساعاتُ المعدة مقابل جدول الغيار</h4></div>
  <p class="text-muted" style="font-size:.9em">زاويةُ الصيانة للتايم شيت: المتراكمُ منذ آخر إنجازٍ مقابل الفترة — لا «من عمل اليوم».</p>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>المعدة</th><th>الخطة</th><th>الفترة (ساعة)</th>
      <th>المتراكمُ منذ آخر غيار</th><th>المتبقي</th><th>الحالة</th>
    </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="6" class="text-center text-muted">لا خططَ وقائيةً بأساس العدّاد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $interval = floatval($r['interval_value']);
        $since    = floatval($r['hours_since']);
        $left     = $interval - $since;
        $tol      = floatval($r['tolerance']);
        if ($left < 0)          { $badge = array('#dc3545', 'متجاوزة'); }
        elseif ($left <= $tol)  { $badge = array('#fd7e14', 'مستحقةٌ الآن'); }
        elseif ($left <= $interval * 0.15) { $badge = array('#ffc107', 'تقترب'); }
        else                    { $badge = array('#198754', 'ضمن الفترة'); }
    ?>
      <tr>
        <td><a href="../Equipments/equipment_profile.php?id=<?= intval($r['eq_id']) ?>"><?= htmlspecialchars($r['eq_name'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars($r['plan_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format($interval, 0) ?></td>
        <td><?= number_format($since, 1) ?></td>
        <td><strong><?= number_format($left, 1) ?></strong> ساعة</td>
        <td><span class="badge" style="background:<?= $badge[0] ?>"><?= $badge[1] ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

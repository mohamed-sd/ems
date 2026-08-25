<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* كشفُ وحداتِ العميلِ النطاقي (unit.stmt.client) — قراءةٌ محضةٌ من
   v_monthly_performance: أرقامٌ محسوبةٌ من القيدِ اليوميِّ لا مُدخَلة. */
$company_id = (int) ($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$st = $conn->prepare("SELECT v.period, v.contract_id, p.name project_name,
                             SUM(v.run_hours) run_hours, SUM(v.billable_standby_hours) billable_standby,
                             SUM(v.breakdown_hours) breakdown_hours, SUM(v.days_worked) days_worked,
                             SUM(v.tons) tons, SUM(v.meters) meters
                      FROM v_monthly_performance v
                      LEFT JOIN project p ON p.id = v.project_id
                      WHERE v.company_id = ?
                      GROUP BY v.period, v.contract_id, p.name
                      ORDER BY v.period DESC, v.contract_id LIMIT 200");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$page_title = 'إيكوبيشن | كشف وحدات العميل';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'claim'; $sft_active = 'perf';
include __DIR__ . '/../includes/sales_family_tabs.php';

require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="content-wrapper" dir="rtl">
  <section class="content-header"><h1>كشف وحدات العميل</h1>
    <p class="ucs-sub">لكل شهر وعقد: ساعات التشغيل والاستعداد المفوتر (F-11) — محسوبة من القيد اليومي لا مدخلة.</p>
  </section>
  <section class="content">
  <?php echo ems_states_bundle('لا قيود في الكشف بعد', 'الكشف يمتلئ من القيد اليومي بعد اعتماده'); ?>
  <div class="box"><div class="box-body table-responsive">
    <?php if (!$rows): ?><p class="ucs-empty-note">لا قيود بعد — الكشف يمتلئ من القيد اليومي.</p>
    <?php else: ?>
    <table class="table table-bordered table-striped">
      <thead><tr><th>الشهر</th><th>العقد</th><th>المشروع</th><th>تشغيل (ساعة)</th><th>استعداد مفوتر (ساعة)</th><th>تعطل (ساعة)</th><th>أيام العمل</th><th>أطنان</th><th>أمتار</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int) $r['contract_id'] ?: '—' ?></td>
          <td><?= htmlspecialchars((string) ($r['project_name'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float) $r['run_hours'], 1) ?> ساعة</td>
          <td><?= number_format((float) $r['billable_standby'], 1) ?> ساعة</td>
          <td><?= number_format((float) $r['breakdown_hours'], 1) ?> ساعة</td>
          <td><?= (int) $r['days_worked'] ?> يوما</td>
          <td><?= number_format((float) $r['tons'], 1) ?></td>
          <td><?= number_format((float) $r['meters'], 1) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div></div></section>
</div>
</body>
</html>

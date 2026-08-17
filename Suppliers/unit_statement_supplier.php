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

/* كشفُ وحداتِ الموردِ النطاقي (unit.stmt.supplier) — قراءةٌ محضة: الأداءُ من
   v_monthly_performance بالموردِ، والتسويةُ من settlements (F-07/F-08). */
$company_id = (int) ($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$st = $conn->prepare("SELECT v.period, v.supplier_entity_id, s.name supplier_name,
                             SUM(v.run_hours) run_hours, SUM(v.standby_hours) standby_hours,
                             SUM(v.supplier_liable_hours) supplier_liable, SUM(v.days_worked) days_worked
                      FROM v_monthly_performance v
                      LEFT JOIN suppliers s ON s.id = v.supplier_entity_id
                      WHERE v.company_id = ? AND v.supplier_entity_id IS NOT NULL
                      GROUP BY v.period, v.supplier_entity_id, s.name
                      ORDER BY v.period DESC LIMIT 200");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$settle = array();
$st = $conn->prepare("SELECT settlement_no, party_name, period_from, period_to, client_executed_hours,
                             supplier_executed_hours, borne_by_treasury, state
                      FROM settlements
                      WHERE company_id = ? AND is_deleted = 0 AND party_type = 'supplier'
                      ORDER BY id DESC LIMIT 40");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $settle[] = $x; }
$st->close();

$page_title = 'إيكوبيشن | كشفُ وحداتِ المورد';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
  /* أنماطُ الشاشةِ الصفحية — الألوانُ رموزٌ من design-tokens.css حصرًا */
  .uss-sub { color: var(--c-s-666); margin: 4px 0 0; }
  .uss-muted { color: var(--c-s-777); }
</style>
<div class="content-wrapper" dir="rtl">
  <section class="content-header"><h1>كشفُ وحداتِ المورد</h1>
    <p class="uss-sub">أداءُ كلِّ موردٍ شهرًا شهرًا من القيدِ اليومي — وتسوياتُه بالمنفَّذِ المحسوبِ والمتحمَّلِ من الخزينة.</p>
  </section>
  <section class="content">
    <?= ems_states_bundle('لا قيودَ منسوبةً لموردين ضمنَ هذا النطاق', 'سجِّل القيدَ اليوميَّ منسوبًا لمورده ثم عُد لهذا الكشف') ?>
    <div class="box"><div class="box-header with-border"><h3 class="box-title">الأداءُ الشهري (من القيدِ اليومي)</h3></div>
      <div class="box-body table-responsive">
      <?php if (!$rows): ?><p class="uss-muted">لا قيودَ منسوبةً لموردين بعدُ.</p>
      <?php else: ?>
      <table class="table table-bordered table-striped">
        <thead><tr><th>الشهر</th><th>المورد</th><th>تشغيلٌ (ساعة)</th><th>استعدادٌ (ساعة)</th><th>تعطلٌ يتحمّله (ساعة)</th><th>أيامُ العمل</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['supplier_name'] ?: ('#' . $r['supplier_entity_id'])), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $r['run_hours'], 1) ?> ساعة</td>
            <td><?= number_format((float) $r['standby_hours'], 1) ?> ساعة</td>
            <td><?= number_format((float) $r['supplier_liable'], 1) ?> ساعة</td>
            <td><?= (int) $r['days_worked'] ?> يومًا</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div></div>
    <div class="box"><div class="box-header with-border"><h3 class="box-title">التسويات (F-07 منفَّذُ الموردِ · F-08 المتحمَّلُ من الخزينة)</h3></div>
      <div class="box-body table-responsive">
      <?php if (!$settle): ?><p class="uss-muted">لا تسوياتِ موردين بعدُ.</p>
      <?php else: ?>
      <table class="table table-bordered table-striped">
        <thead><tr><th>التسوية</th><th>المورد</th><th>الفترة</th><th>منفَّذُ العميل (ساعة)</th><th>منفَّذُ المورد (ساعة)</th><th>المتحمَّلُ من الخزينة (ساعة)</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($settle as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string) $r['settlement_no'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $r['party_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['period_from'] . ' ← ' . $r['period_to'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $r['client_executed_hours'], 1) ?> ساعة</td>
            <td><?= number_format((float) $r['supplier_executed_hours'], 1) ?> ساعة</td>
            <td><?php $b = (float) $r['borne_by_treasury']; ?>
                <span class="label <?= $b > 0 ? 'label-danger' : 'label-success' ?>">
                  <?= $b > 0 ? ('⚠ ' . number_format($b, 1) . ' ساعة خسارة') : '✔ صفرٌ — لا خسارة' ?></span></td>
            <td><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div></div>
  </section>
</div>
<?php include __DIR__ . '/../infooter.php'; ?>

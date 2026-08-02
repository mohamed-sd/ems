<?php
/**
 * Suppliers/shares_coverage.php — حصصُ الموردين والتغطية (★ · update0007-ب F12)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل موردٍ: التزامُه (Σ حاوياته) · المستهلَكُ (من دفتر القدرات محسوبًا لا
 * مخزَّنًا — CAP-01 §13) · التغطيةُ المعطاةُ والمستلمة (بنودٌ مستقلةٌ لا ترفع
 * الحصة) · والفجوة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$r = mysqli_query($conn,
    "SELECT s.id supplier_id, s.name,
            COALESCE(SUM(oc.cap_qty), 0) committed,
            COALESCE((SELECT SUM(l.qty) FROM capacity_consumption_ledger l
                      WHERE l.effect_target_type = 'supplier' AND l.effect_target_ref = s.id
                        AND l.effect_type = 'supplier_share' AND l.reverses_led_id IS NULL), 0) consumed,
            COALESCE((SELECT SUM(cl.qty) FROM coverage_settlement_lines cl
                      JOIN substitute_coverages c2 ON c2.cov_id = cl.cov_id
                      WHERE cl.party = 'covering_supplier' AND cl.effect = 'exceptional_line'
                        AND c2.covering_supplier_id = s.id), 0) coverage_given
     FROM suppliers s
     LEFT JOIN op_containers oc ON oc.supplier_id = s.id AND oc.is_deleted = 0
     WHERE s.company_id = $company_id
     GROUP BY s.id, s.name
     HAVING committed > 0 OR consumed > 0 OR coverage_given > 0
     ORDER BY committed DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'حصص الموردين والتغطية';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-chart-pie"></i> حصصُ الموردين والتغطية</h4></div>
  <p class="text-muted" style="font-size:.9em">المستهلَكُ محسوبٌ من دفتر القدرات لا من عمودٍ مخزَّن — والتغطيةُ الاستثنائيةُ بندٌ لا يرفع الحصة (CAP-01 §7).</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المورد</th><th>الالتزام</th><th>المستهلَك (الدفتر)</th><th>التنفيذ ٪</th><th>تغطيةٌ أعطاها</th><th>الفجوة</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted">لا حصصَ موزَّعة</td></tr><?php endif; ?>
    <?php foreach ($rows as $s):
        $pct = floatval($s['committed']) > 0 ? round(floatval($s['consumed']) / floatval($s['committed']) * 100, 1) : 0;
        $gap = floatval($s['committed']) - floatval($s['consumed']); ?>
      <tr>
        <td><a href="supplier_profile.php?id=<?= intval($s['supplier_id']) ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= number_format(floatval($s['committed']), 1) ?></td>
        <td><?= number_format(floatval($s['consumed']), 1) ?></td>
        <td><span class="badge" style="background:<?= $pct >= 90 ? '#198754' : ($pct >= 60 ? '#fd7e14' : '#dc3545') ?>"><?= $pct ?>٪</span></td>
        <td><?= number_format(floatval($s['coverage_given']), 1) ?> <span class="text-muted">(بندٌ مستقل)</span></td>
        <td><?= number_format($gap, 1) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
/**
 * Finance/budget_master.php — الموازنةُ العامةُ والتوحيد (★ المالية · S-12)
 * توحيدُ ميزانيات الإدارات كلِّها في عرضٍ واحدٍ للمالية — بمجاميعها وانحرافها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$year = intval($_GET['year'] ?? date('Y'));
$rows = array(); $tp = 0; $ta = 0;
$r = mysqli_query($conn, "SELECT b.dept_module, COUNT(DISTINCT b.id) budgets,
                                 SUM(l.planned_amount) planned, SUM(l.actual_amount) actual
                          FROM fin_budgets b LEFT JOIN fin_budget_lines l ON l.budget_id = b.id
                          WHERE b.company_id = $company_id AND b.is_deleted = 0 AND b.fiscal_year = $year
                          GROUP BY b.dept_module ORDER BY planned DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) { $rows[] = $x; $tp += floatval($x['planned']); $ta += floatval($x['actual']); }

$page_title = 'الموازنة العامة والتوحيد';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-university"></i> الموازنةُ العامة — <?= $year ?></h4></div>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الإدارة</th><th>ميزانيات</th><th>المخطط</th><th>الفعلي</th><th>الانحراف</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
    <tbody>
    <?php foreach ($rows as $b): $var = floatval($b['actual']) - floatval($b['planned']); ?>
      <tr><td><?= htmlspecialchars($b['dept_module'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= intval($b['budgets']) ?></td>
          <td><?= number_format(floatval($b['planned']), 2) ?></td>
          <td><?= number_format(floatval($b['actual']), 2) ?></td>
          <td style="color:<?= $var > 0 ? '#dc3545' : '#198754' ?>"><?= number_format($var, 2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr style="font-weight:bold;background:#f8f9fa">
      <td>الإجمالي الموحَّد</td><td></td>
      <td><?= number_format($tp, 2) ?></td><td><?= number_format($ta, 2) ?></td>
      <td><?= number_format($ta - $tp, 2) ?></td></tr></tfoot>
  </table>
</div>

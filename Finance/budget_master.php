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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-university';
$header_title_html = htmlspecialchars('الموازنةُ العامة — ' . ($year), ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الثلاثُ من المكوّنِ المركزيّ
echo ems_states_bundle('لا ميزانياتَ معتمدةً لهذه السنة', 'تحققْ من اعتمادِ ميزانياتِ الإداراتِ للسنةِ المالية');
?>
  <style>
    /* UXW-01 ②: أصنافُ الصفحةِ بدلَ الأنماطِ الموضعية — ألوانُها رموزٌ حصرًا */
    .bm-var-over  { color: var(--c-dc3545); }
    .bm-var-under { color: var(--c-198754); }
    .bm-total-row { font-weight: bold; background: var(--c-f8f9fa); }
  </style>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الإدارة</th><th>ميزانيات</th><th>المخطط</th><th>الفعلي</th><th>الانحراف</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">السنة المالية</th>
              <th class="ems-fn-th" data-fn="1">بند الموازنة</th>
              <th class="ems-fn-th" data-fn="1">رقم الحساب</th>
              <th class="ems-fn-th" data-fn="1">المعتمد السنوي</th>
              <th class="ems-fn-th" data-fn="1">التوزيع الربعي 1</th>
              <th class="ems-fn-th" data-fn="1">الربع 2</th>
              <th class="ems-fn-th" data-fn="1">الربع 3</th>
              <th class="ems-fn-th" data-fn="1">الربع 4</th>
              <th class="ems-fn-th" data-fn="1">المصروف حتى تاريخه</th>
              <th class="ems-fn-th" data-fn="1">الملتزم به</th>
              <th class="ems-fn-th" data-fn="1">المتاح</th>
              <th class="ems-fn-th" data-fn="1">نسبة الصرف</th>
              <th class="ems-fn-th" data-fn="1">اعتمدها</th>
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
          <td class="<?= $var > 0 ? 'bm-var-over' : 'bm-var-under' ?>"><?= number_format($var, 2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="bm-total-row">
      <td>الإجمالي الموحَّد</td><td></td>
      <td><?= number_format($tp, 2) ?></td><td><?= number_format($ta, 2) ?></td>
      <td><?= number_format($ta - $tp, 2) ?></td></tr></tfoot>
  </table>
</div>

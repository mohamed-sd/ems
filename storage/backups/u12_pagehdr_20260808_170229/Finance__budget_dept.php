<?php
/**
 * Finance/budget_dept.php — ميزانيةُ إدارتي (★ · update0007 S-12)
 * ───────────────────────────────────────────────────────────────────────────
 * «الميزانيةُ والانحراف تقريرٌ بفلتر النطاق لا شاشةٌ مكررةٌ في ثلاثَ عشرةَ
 * قائمة» (NAV-02 v7 §4.2-②) — كلُّ إدارةٍ ترى ميزانيتَها هي من fin_budgets
 * بفلتر dept_module، والأرقامُ نطاقُها لا كلُّ الشركة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../Tickets/dept_inbox_map.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role       = intval($_SESSION['user']['role'] ?? 0);
$year       = intval($_GET['year'] ?? date('Y'));

/* فلترُ النطاق: وحدةُ الدور ← موديولُ الإدارة في fin_budgets */
$unitDept = array(1 => 'operations', 8 => 'operations', 9 => 'maintenance', 10 => 'workforce',
                  11 => 'procurement', 12 => 'warehouse', 13 => 'transport', 2 => 'sales',
                  3 => 'finance', 5 => 'fleet', 14 => 'hr', 15 => 'suppliers',
                  4 => 'financing', 6 => 'governance', 7 => 'tickets');
$unit = ems_dept_unit_of_role($role);
$dept = isset($unitDept[$unit]) ? $unitDept[$unit] : '';
$isFinance = in_array($role, array(17, 18, 19, 20, 21, 22), true);
$where = "b.company_id=$company_id AND b.is_deleted=0 AND b.fiscal_year=$year";
if (!$isFinance && $dept !== '') $where .= " AND b.dept_module='" . mysqli_real_escape_string($conn, $dept) . "'";

$rows = array();
$r = mysqli_query($conn, "SELECT b.id, b.budget_no, b.dept_module, b.period_no, b.state,
                                 SUM(l.planned_amount) planned, SUM(l.actual_amount) actual
                          FROM fin_budgets b LEFT JOIN fin_budget_lines l ON l.budget_id = b.id
                          WHERE $where GROUP BY b.id ORDER BY b.period_no");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'ميزانية إدارتي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-coins"></i> ميزانيةُ إدارتي — <?= $isFinance ? 'كلُّ الإدارات (زاويةُ المالية)' : htmlspecialchars($dept !== '' ? ems_dept_label($dept) : 'دورُك بلا وحدة', ENT_QUOTES, 'UTF-8') ?></h4></div>
  <form method="get" style="margin-bottom:12px"><label>السنة</label>
    <input type="number" name="year" value="<?= $year ?>" class="form-control" style="max-width:120px;display:inline-block" onchange="this.form.submit()"></form>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الرقم</th><th>الإدارة</th><th>الفترة</th><th>المخطط</th><th>المصروف الفعلي</th><th>الانحراف</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">بند الموازنة</th>
              <th class="ems-fn-th" data-fn="1">المعتمد للسنة</th>
              <th class="ems-fn-th" data-fn="1">المعتمد للفترة</th>
              <th class="ems-fn-th" data-fn="1">الملتزم به</th>
              <th class="ems-fn-th" data-fn="1">المتاح</th>
              <th class="ems-fn-th" data-fn="1">نسبة الصرف</th>
              <th class="ems-fn-th" data-fn="1">سبب الانحراف</th>
              <th class="ems-fn-th" data-fn="1">طلبات تعديل قائمة</th>
              <th class="ems-fn-th" data-fn="1">المسؤول</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">لا ميزانيةَ لنطاقك في <?= $year ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $b):
        $var = floatval($b['actual']) - floatval($b['planned']); ?>
      <tr>
        <td><?= htmlspecialchars($b['budget_no'] !== null && $b['budget_no'] !== '' ? $b['budget_no'] : '#' . $b['id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(ems_dept_label($b['dept_module']), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= intval($b['period_no']) ?></td>
        <td><?= number_format(floatval($b['planned']), 2) ?></td>
        <td><?= number_format(floatval($b['actual']), 2) ?></td>
        <td style="color:<?= $var > 0 ? '#dc3545' : '#198754' ?>"><?= number_format($var, 2) ?></td>
        <td><?= htmlspecialchars($b['state'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

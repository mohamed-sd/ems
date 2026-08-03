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
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-chart-pie"></i> حصصُ الموردين والتغطية</h4></div>
  <p class="text-muted" style="font-size:.9em">المستهلَكُ محسوبٌ من دفتر القدرات لا من عمودٍ مخزَّن — والتغطيةُ الاستثنائيةُ بندٌ لا يرفع الحصة (CAP-01 §7).</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المورد</th><th>نسبة الحصة من الالتزام</th><th>المستهلَك (الدفتر)</th><th>التنفيذ ٪</th><th>تغطيةٌ أعطاها</th><th>الفجوة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الحصة</th>
              <th class="ems-fn-th" data-fn="1">العقد</th>
              <th class="ems-fn-th" data-fn="1">نموذج العمل</th>
              <th class="ems-fn-th" data-fn="1">وحدة العمل</th>
              <th class="ems-fn-th" data-fn="1">نوع المعدة</th>
              <th class="ems-fn-th" data-fn="1">الحصة المخصَّصة (وحدات)</th>
              <th class="ems-fn-th" data-fn="1">حصة المبيعات لهذا النوع</th>
              <th class="ems-fn-th" data-fn="1">مجموع حصص الموردين لهذا النوع</th>
              <th class="ems-fn-th" data-fn="1">المتبقي من حصة المبيعات</th>
              <th class="ems-fn-th" data-fn="1">معدات أساسية</th>
              <th class="ems-fn-th" data-fn="1">معدات احتياطية</th>
              <th class="ems-fn-th" data-fn="1">الموزَّع على المعدات</th>
              <th class="ems-fn-th" data-fn="1">غير الموزَّع</th>
              <th class="ems-fn-th" data-fn="1">الساعات المشتقة من عقد العميل (محسوبة)</th>
              <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الانتهاء</th>
              <th class="ems-fn-th none" data-fn="1">حالة الحصة</th>
              <th class="ems-fn-th none" data-fn="1">مرجع العقد أو الملحق</th>
              <th class="ems-fn-th none" data-fn="1">مرجع الترسية</th>
              <th class="ems-fn-th none" data-fn="1">خصّصها</th>
              <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th none" data-fn="1">رقم السطر</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ القيد</th>
              <th class="ems-fn-th none" data-fn="1">الحصة المرجعية</th>
              <th class="ems-fn-th none" data-fn="1">الوحدة التعاقدية</th>
              <th class="ems-fn-th none" data-fn="1">المعدة</th>
              <th class="ems-fn-th none" data-fn="1">الفترة</th>
              <th class="ems-fn-th none" data-fn="1">الساعات المستهلكة</th>
              <th class="ems-fn-th none" data-fn="1">الرصيد قبل</th>
              <th class="ems-fn-th none" data-fn="1">الرصيد بعد</th>
              <th class="ems-fn-th none" data-fn="1">المستند المصدر</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
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

<?php
/**
 * Finance/entitlement_gate.php — بوابةُ الاستحقاق المالي (★ المالية · update0007 S-07)
 * ───────────────────────────────────────────────────────────────────────────
 * «الماليةُ بوابةٌ قبل الترحيل المالي — لا شرطٌ لإثبات الواقعة» (CAP-01 §12).
 * تعرض الأثرَ الأوليَّ المنتظرَ (fin_dues بحالة الاقتراح) وتُمرّره بوابةً:
 * الاعتمادُ عبر UnitJourneyService::postEntitlement — لا كتابةَ مباشرة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$sql = "SELECT d.id, d.due_no, d.party_kind, d.beneficiary_ref, d.amount, d.currency,
               d.state, d.source_kind, d.created_at
        FROM fin_dues d
        WHERE d.company_id = $company_id
          AND d.state IN ('proposed','pending_gate','awaiting_approval')
        ORDER BY d.created_at LIMIT 200";
$res = mysqli_query($conn, $sql);
if ($res) { while ($x = mysqli_fetch_assoc($res)) $rows[] = $x; }

$page_title = 'بوابة الاستحقاق المالي';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar">
    <h4><i class="fa fa-door-closed"></i> بوابةُ الاستحقاق المالي</h4>
    <span class="badge" style="background:#fd7e14;font-size:.95em">بانتظار البوابة: <?= count($rows) ?></span>
  </div>
  <p class="text-muted" style="font-size:.9em">الأثرُ الأوليُّ ينتظر اعتمادَ مدير الإدارة + المالية — ولا يصير Posted قبلهما (POL-01).</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الرقم</th><th>الطرف</th><th>المبلغ</th><th>المصدر</th><th>الحالة</th><th>منذ</th><th>إجراء</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              <th class="ems-gov-th" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted">لا أثرَ أوليًّا ينتظر البوابة</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['due_no'] ?? ('#' . $r['id']), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['party_kind'] . ' / ' . $r['beneficiary_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format(floatval($r['amount']), 2) ?> <?= htmlspecialchars($r['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['source_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['state'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr($r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
        <td><a class="action-btn" href="../Finance/approvals_inbox.php">افتح في صندوق الاعتماد</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

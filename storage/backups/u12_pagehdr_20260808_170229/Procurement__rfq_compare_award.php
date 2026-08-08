<?php
/**
 * Procurement/rfq_compare_award.php — مقارنةُ العروض والترسية (★ المشتريات · S-10)
 * ───────────────────────────────────────────────────────────────────────────
 * الخطوةُ الناقصةُ بين طلب العروض والأمر (NAV-02 §12-②): عروضُ كل طلبٍ
 * تُعرض جنبًا إلى جنبٍ بأسعارها وجاهزيتها — والترسيةُ صفٌّ في rfq_awards
 * بسببٍ موثَّق، فلا ترسيةَ صامتة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$rfq = intval($_REQUEST['rfq'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['award_quote'])) {
    $qid    = intval($_POST['award_quote']);
    $reason = trim($_POST['award_reason'] ?? '');
    if ($reason === '') { $msg = 'سببُ الترسية إلزامي — لا ترسيةَ صامتة (422)'; }
    else {
        $r = mysqli_query($conn, "SELECT q.rfq_id, q.line_id, q.supplier_id, q.unit_price, q.currency, q.qty_offered
                                  FROM rfq_quotes q WHERE q.id = $qid AND q.company_id = $company_id");
        if ($r && ($q = mysqli_fetch_assoc($r))) {
            $dup = mysqli_query($conn, "SELECT id FROM rfq_awards WHERE rfq_id = {$q['rfq_id']} AND line_id " .
                                       ($q['line_id'] === null ? 'IS NULL' : '= ' . intval($q['line_id'])));
            if ($dup && mysqli_num_rows($dup) > 0) { $msg = 'البندُ مُرسًى من قبل — 409 بمرجع الترسية القائمة'; }
            else {
                mysqli_begin_transaction($conn);
                $ok1 = mysqli_query($conn, "INSERT INTO rfq_awards (company_id, rfq_id, line_id, supplier_id, quote_id, qty_awarded, unit_price, currency, reason, awarded_by, awarded_at)
                        VALUES ($company_id, {$q['rfq_id']}, " . ($q['line_id'] === null ? 'NULL' : intval($q['line_id'])) . ",
                                {$q['supplier_id']}, $qid, " . floatval($q['qty_offered']) . ", " . floatval($q['unit_price']) . ",
                                '" . mysqli_real_escape_string($conn, $q['currency']) . "',
                                '" . mysqli_real_escape_string($conn, $reason) . "', $uid, NOW())");
                $ok2 = mysqli_query($conn, "UPDATE supplier_rfqs SET state='awarded', awarded_at=NOW(), awarded_by=$uid
                                            WHERE id = {$q['rfq_id']} AND company_id = $company_id");
                if ($ok1 && $ok2) { mysqli_commit($conn); $msg = 'رُسّي العرضُ #' . $qid . ' بسببٍ موثَّق'; }
                else { mysqli_rollback($conn); $msg = 'فشلت الترسيةُ فأُلغيت: ' . mysqli_error($conn); }
            }
        } else { $msg = 'عرضٌ غيرُ موجود (404)'; }
    }
}

$rfqs = array(); $quotes = array();
$r = mysqli_query($conn, "SELECT id, rfq_no, title, state FROM supplier_rfqs
                          WHERE company_id=$company_id AND is_deleted=0 AND state IN ('sent','opened','quoted')
                          ORDER BY id DESC LIMIT 40");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rfqs[] = $x;
if ($rfq > 0) {
    $r = mysqli_query($conn, "SELECT q.id, s.name supplier, q.unit_price, q.currency, q.qty_offered,
                                     q.readiness_days, q.record_rating, q.note
                              FROM rfq_quotes q JOIN suppliers s ON s.id = q.supplier_id
                              WHERE q.rfq_id = $rfq AND q.company_id = $company_id
                              ORDER BY q.unit_price");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $quotes[] = $x;
}

$page_title = 'مقارنة العروض والترسية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-balance-scale"></i> مقارنةُ العروض والترسية</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="get" class="ems-form" style="margin-bottom:14px">
    <select name="rfq" class="form-control" style="max-width:420px" onchange="this.form.submit()">
      <option value="">— اختر طلبَ عروض —</option>
      <?php foreach ($rfqs as $f): ?>
        <option value="<?= intval($f['id']) ?>" <?= $f['id'] == $rfq ? 'selected' : '' ?>>
          <?= htmlspecialchars(($f['rfq_no'] ?: '#' . $f['id']) . ' — ' . $f['title'] . ' (' . $f['state'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($rfq > 0): ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المورد</th><th>سعرُ الوحدة</th><th>الكمية</th><th>جاهزية (يوم)</th><th>تقييمُ السجل</th><th>ملاحظة</th><th>ترسية</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإرسال</th>
              <th class="ems-fn-th" data-fn="1">مرجع طلب الشراء</th>
              <th class="ems-fn-th" data-fn="1">الأصناف</th>
              <th class="ems-fn-th" data-fn="1">الموردون المدعوون</th>
              <th class="ems-fn-th" data-fn="1">تاريخ إقفال العروض</th>
              <th class="ems-fn-th" data-fn="1">الإجمالي</th>
              <th class="ems-fn-th" data-fn="1">مدة التوريد</th>
              <th class="ems-fn-th" data-fn="1">شروط الدفع</th>
              <th class="ems-fn-th" data-fn="1">التقييم الفني</th>
              <th class="ems-fn-th" data-fn="1">الترتيب</th>
              <th class="ems-fn-th" data-fn="1">القرار</th>
              <th class="ems-fn-th" data-fn="1">مبرر الاختيار</th>
              <th class="ems-fn-th" data-fn="1">أعدّه</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($quotes)): ?><tr><td colspan="7" class="text-center text-muted">لا عروضَ مقدَّمةً لهذا الطلب</td></tr><?php endif; ?>
    <?php $best = $quotes ? floatval($quotes[0]['unit_price']) : 0;
    foreach ($quotes as $q): $isBest = floatval($q['unit_price']) <= $best; ?>
      <tr<?= $isBest ? ' style="background:#f0fff4"' : '' ?>>
        <td><?= htmlspecialchars($q['supplier'], ENT_QUOTES, 'UTF-8') ?><?= $isBest ? ' <span class="badge" style="background:#198754">الأدنى</span>' : '' ?></td>
        <td><?= number_format(floatval($q['unit_price']), 2) ?> <?= htmlspecialchars($q['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= floatval($q['qty_offered']) ?></td>
        <td><?= intval($q['readiness_days']) ?></td>
        <td><?= htmlspecialchars($q['record_rating'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($q['note'] ?? '', 0, 40), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="rfq" value="<?= $rfq ?>">
            <input type="hidden" name="award_quote" value="<?= intval($q['id']) ?>">
            <input type="text" name="award_reason" class="form-control form-control-sm" placeholder="سببُ الترسية" style="max-width:160px" required>
            <button class="action-btn" type="submit">رسِّ</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

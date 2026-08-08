<?php
/**
 * Financing/deviations.php — الانحرافاتُ الثلاث (★ · update0007-ب F3)
 * ───────────────────────────────────────────────────────────────────────────
 * FIN-01: عقودٌ بلا حركة · فروقُ سداد · خروجٌ غيرُ مسجَّل — تُرصد وتُغلق
 * **بقرارٍ موثَّقٍ لا بصمت** (financing_deviations.decision + doc_ref).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = intval($_SESSION['user']['role'] ?? 0);
$granted = ($role === 26) || !empty($_SESSION['user']['is_super_admin']);
if (!$granted) {
    $g = mysqli_query($conn, "SELECT 1 FROM ownership_access_grants WHERE person_id = $uid AND state = 'active' LIMIT 1");
    $granted = $g && mysqli_num_rows($g) > 0;
}
if (!$granted) { http_response_code(403); die('المجالُ المقيَّد (FIN-01 §1.1)'); }
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['close_dev'])) {
    $did = intval($_POST['close_dev']);
    $dec = trim($_POST['decision'] ?? '');
    $doc = trim($_POST['decision_doc'] ?? '');
    if ($dec === '') { $msg = 'القرارُ إلزاميٌّ — الانحرافُ يُغلق بقرارٍ لا بصمت (422)'; }
    else {
        $st = mysqli_prepare($conn, "UPDATE financing_deviations SET state='closed', decision=?, decision_doc_ref=?,
                                     closed_by=?, closed_at=NOW() WHERE dev_id=? AND company_id=? AND state<>'closed'");
        mysqli_stmt_bind_param($st, 'ssiii', $dec, $doc, $uid, $did, $company_id);
        mysqli_stmt_execute($st);
        $msg = mysqli_stmt_affected_rows($st) > 0 ? "أُغلق الانحرافُ #$did بقراره" : 'مغلقٌ من قبل (409)';
    }
}

/* الرصدُ الآلي للأصناف الثلاثة — يُحدث صفوفًا للجديد فقط (idempotent بالموضوع) */
mysqli_query($conn, "INSERT INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, state)
    SELECT o.company_id, 'no_movement', CONCAT('FINOP-', o.op_id),
           CONCAT('عمليةُ ', o.op_code, ' نشطةٌ بلا قسطٍ مسدَّدٍ منذ 90 يومًا'), 'high', 'open'
    FROM financing_operations o
    WHERE o.company_id = $company_id AND o.state = 'active'
      AND NOT EXISTS (SELECT 1 FROM financing_installments i WHERE i.op_id = o.op_id AND i.state = 'paid'
                      AND i.paid_date > DATE_SUB(CURDATE(), INTERVAL 90 DAY))
      AND o.signed_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
      AND NOT EXISTS (SELECT 1 FROM financing_deviations d WHERE d.subject_ref = CONCAT('FINOP-', o.op_id)
                      AND d.dev_type = 'no_movement' AND d.state <> 'closed')");
mysqli_query($conn, "INSERT INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, state)
    SELECT o.company_id, 'payment_gap', CONCAT('FINOP-', o.op_id),
           CONCAT('أقساطٌ متأخرةٌ عن استحقاقها في ', o.op_code), 'high', 'open'
    FROM financing_operations o
    WHERE o.company_id = $company_id
      AND EXISTS (SELECT 1 FROM financing_installments i WHERE i.op_id = o.op_id
                  AND i.state <> 'paid' AND i.due_date < CURDATE())
      AND NOT EXISTS (SELECT 1 FROM financing_deviations d WHERE d.subject_ref = CONCAT('FINOP-', o.op_id)
                      AND d.dev_type = 'payment_gap' AND d.state <> 'closed')");

$rows = array();
$r = mysqli_query($conn, "SELECT * FROM financing_deviations WHERE company_id = $company_id
                          ORDER BY state = 'closed', FIELD(priority,'high','medium','low'), created_at DESC LIMIT 100");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
$types = array('no_movement' => 'عقدٌ بلا حركة', 'payment_gap' => 'فروقُ سداد', 'unrecorded_exit' => 'خروجٌ غيرُ مسجَّل');

$page_title = 'الانحرافات الثلاث';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-exclamation-triangle"></i> الانحرافاتُ الثلاث</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الصنف</th><th>الموضوع</th><th>الوصف</th><th>الأولوية</th><th>الحالة</th><th>الإغلاقُ بقرار</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الورقة</th>
              <th class="ems-fn-th" data-fn="1">نوع الانحراف</th>
              <th class="ems-fn-th" data-fn="1">عملية التمويل</th>
              <th class="ems-fn-th" data-fn="1">الممول</th>
              <th class="ems-fn-th" data-fn="1">العين</th>
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">المسجَّل</th>
              <th class="ems-fn-th" data-fn="1">المتوقع</th>
              <th class="ems-fn-th" data-fn="1">الفرق</th>
              <th class="ems-fn-th" data-fn="1">نسبة الفرق</th>
              <th class="ems-fn-th" data-fn="1">السبب المرجَّح</th>
              <th class="ems-fn-th" data-fn="1">الأدلة</th>
              <th class="ems-fn-th" data-fn="1">المستند المطلوب</th>
              <th class="ems-fn-th" data-fn="1">قرار التسوية</th>
              <th class="ems-fn-th" data-fn="1">قيد التسوية</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإقفال</th>
              <th class="ems-fn-th none" data-fn="1">المسجَّل في الدفاتر</th>
              <th class="ems-fn-th none" data-fn="1">المتوقع بالعقد</th>
              <th class="ems-fn-th none" data-fn="1">ترجيح السبب</th>
              <th class="ems-fn-th none" data-fn="1">القرار المتخذ</th>
              <th class="ems-fn-th none" data-fn="1">حلّله</th>
              <th class="ems-fn-th none" data-fn="1">اعتمده</th>
              <th class="ems-fn-th none" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th none" data-fn="1">كود العين</th>
              <th class="ems-fn-th none" data-fn="1">المالك السابق</th>
              <th class="ems-fn-th none" data-fn="1">النسبة المسجَّلة</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ البيع الفعلي</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ اكتشاف الخروج</th>
              <th class="ems-fn-th none" data-fn="1">أيام التسجيل الزائد</th>
              <th class="ems-fn-th none" data-fn="1">المالك الجديد</th>
              <th class="ems-fn-th none" data-fn="1">مستند البيع</th>
              <th class="ems-fn-th none" data-fn="1">قيمة البيع</th>
              <th class="ems-fn-th none" data-fn="1">أثر التصحيح على الإهلاك</th>
              <th class="ems-fn-th none" data-fn="1">أثر التصحيح على العائد</th>
              <th class="ems-fn-th none" data-fn="1">اكتشفه</th>
              <th class="ems-fn-th none" data-fn="1">اعتمد التصحيح</th>
              <th class="ems-fn-th none" data-fn="1">رقم السجل</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ التوقيع</th>
              <th class="ems-fn-th none" data-fn="1">قيمة العقد</th>
              <th class="ems-fn-th none" data-fn="1">آخر حركة في الدفتر</th>
              <th class="ems-fn-th none" data-fn="1">أيام السكون</th>
              <th class="ems-fn-th none" data-fn="1">الحالة المفترضة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted">صفرُ انحرافٍ مفتوح — والرصدُ آليٌّ عند كل فتح</td></tr><?php endif; ?>
    <?php foreach ($rows as $d): $open = $d['state'] !== 'closed'; ?>
      <tr>
        <td><?= htmlspecialchars($types[$d['dev_type']] ?? $d['dev_type'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($d['subject_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($d['description'], 0, 60), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($d['priority'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $open ? '<span class="badge" style="background:#fd7e14">مفتوح</span>'
                       : '<span class="badge" style="background:#198754">مغلقٌ بقرار</span>' ?></td>
        <td>
          <?php if ($open): ?>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="close_dev" value="<?= intval($d['dev_id']) ?>">
            <input type="text" name="decision" class="form-control form-control-sm" placeholder="القرار" style="max-width:160px" required>
            <input type="text" name="decision_doc" class="form-control form-control-sm" placeholder="مرجعُ المستند" style="max-width:120px">
            <button class="action-btn" type="submit">أغلق</button>
          </form>
          <?php else: ?><?= htmlspecialchars($d['decision'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

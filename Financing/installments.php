<?php
/**
 * Financing/installments.php — الأقساطُ والسداد (★ · update0007-ب F2)
 * ───────────────────────────────────────────────────────────────────────────
 * أقساطُ العمليات باستحقاقها — وتسجيلُ السداد يؤرّخ ويُرجع الرصيدَ محسوبًا
 * (outstanding يُحدَّث من مجموع المسدَّد لا يُحرَّر). المجالُ مقيَّد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = intval($_SESSION['user']['role'] ?? 0);
$granted = ($role === 26) || !empty($_SESSION['user']['is_super_admin']);
if (!$granted) {
    $g = mysqli_query($conn, "SELECT 1 FROM ownership_access_grants WHERE person_id = $uid AND state = 'active' LIMIT 1");
    $granted = $g && mysqli_num_rows($g) > 0;
}
if (!$granted) { ems_gov_flash_redirect('../main/dashboard.php', 'المجالُ المقيَّد (FIN-01 §1.1) ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك'); }

$op_filter = intval($_GET['op'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['pay_inst'])) {
    $iid = intval($_POST['pay_inst']);
    $ref = trim($_POST['payment_ref'] ?? '');
    if ($ref === '') { $msg = 'مرجعُ السداد إلزامي — لا سدادَ بلا مستند (422)'; }
    else {
        $r = mysqli_query($conn, "SELECT i.inst_id, i.op_id, i.amount_total FROM financing_installments i
                                  JOIN financing_operations o ON o.op_id = i.op_id AND o.company_id = $company_id
                                  /* ⇐ INJ-0334 · التعدادُ الحيّ scheduled/due/paid/overdue/rescheduled.
                                       فـ`pending` و`Due` و`Pending` **لا وجودَ لها**: لم يكن يُسدَّد
                                       إلا ما حالتُه `due` — و**١١١ قسطًا متأخرًا (`overdue`) لا سبيلَ
                                       إلى سدادِه أصلًا**. والمسموحُ هو غيرُ المسدَّد: مجدولٌ أو
                                       مستحقٌّ أو متأخر — ولا يُسدَّد `paid` مرتين ولا `rescheduled`. */
                                  WHERE i.inst_id = $iid AND i.state IN ('scheduled','due','overdue')");
        if ($r && ($inst = mysqli_fetch_assoc($r))) {
            /* ══ INJ-0046 · «لكل بيانٍ موضعٌ واحدٌ يُنشأ فيه ويُعدَّل» (CS-05) ═══════
                 كانت الشاشةُ تنفّذ السدادَ بـSQL خامٍّ ولا تنادي
                 `FinancingService::payInstallment` — **وهي المنفّذُ المعتمد**.
                 فينقص عن الصفِّ ما تكتبه الخدمةُ: `fx_rate_at_payment` و
                 `functional_equivalent`؛ **ولا تنتقل العمليةُ إلى `settled`**
                 عند بلوغِ الرصيدِ صفرًا. ومنطقُ الرصيدِ نفسُه كان مختلفًا:
                 الشاشةُ تحسب (رأسُ المال − Σ الأصلِ المسدَّد) والخدمةُ تنقص
                 `amount_total` من الرصيدِ القائم — **رقمان لحقيقةٍ واحدة**.
               ◆ فصار المنفِّذُ واحدًا، والشاشةُ تُمرّر سعرَ الصرفِ ومعادِلَه من
                 `includes/fx.php` — «الماليةُ تُسعّر يوميًّا» ولا يُخترع سعر. */
            require_once dirname(__DIR__) . '/app/Services/Financing/FinancingService.php';
            require_once __DIR__ . '/../includes/fx.php';
            $__fx = null; $__eq = null;
            $__cur = '';
            $__cq = mysqli_query($conn, 'SELECT o.currency, i.seq_no, i.amount_total
                                           FROM financing_installments i
                                           JOIN financing_operations o ON o.op_id = i.op_id
                                          WHERE i.inst_id = ' . (int) $iid . ' LIMIT 1');
            $__ci = $__cq ? mysqli_fetch_assoc($__cq) : null;
            if ($__ci) {
                $__cur = (string) $__ci['currency'];
                if (function_exists('ems_fx_rate') && $__cur !== '') {
                    $__r = ems_fx_rate($__cur, date('Y-m-d'));
                    if ($__r !== null && $__r > 0) {
                        $__fx = (float) $__r;
                        $__eq = round(((float) $__ci['amount_total']) * $__fx, 2);
                    }
                }
            }
            $__seq = $__ci ? (int) $__ci['seq_no'] : 0;
            $__res = \App\Services\Financing\FinancingService::payInstallment(
                $conn, $company_id, (int) $inst['op_id'], $__seq,
                date('Y-m-d'), $ref, $__fx, $__eq);
            if (!empty($__res['ok'])) {
                /* ── INJ-0052 · «كلُّ سدادٍ يترك سطرَ تدقيق» ───────────────────────
                     الكتابةُ مباشرةٌ لا عبر البوابةِ المُدقِّقة، فيُنادى الموصِّلُ
                     صراحةً بعد **نجاحِ المعاملةِ** لا قبلَها — فأثرٌ لسدادٍ أُلغي
                     أسوأُ من غيابه. والمصدرُ مُضمَّنٌ عند موضعِ الاستعمال. */
                require_once __DIR__ . '/../includes/audit_trail.php';
                ems_audit_change($conn, 'financing', 'financing_installments', 'pay', (int) $iid,
                    array('state' => 'due', 'payment_ref' => null, 'paid_date' => null),
                    array('state' => 'paid', 'payment_ref' => $ref, 'paid_date' => date('Y-m-d')),
                    array('company_id' => $company_id, 'user_id' => $uid));
                /* والرسالةُ من الخدمةِ — فما تقوله الشاشةُ هو ما فعله المنفِّذ */
                $__st = mysqli_query($conn, 'SELECT state, outstanding_balance FROM financing_operations
                                              WHERE op_id = ' . (int) $inst['op_id'] . ' LIMIT 1');
                $__op = $__st ? mysqli_fetch_assoc($__st) : null;
                $msg = 'سُدّد القسطُ #' . $iid . ' بمرجع ' . $ref . ' — ' . $__res['reason']
                     . ($__op ? (' · الرصيدُ ' . rtrim(rtrim((string) $__op['outstanding_balance'], '0'), '.')
                                 . ' وحالةُ العملية «' . $__op['state'] . '»') : '');
            }
            else { $msg = 'تعذّر السداد: ' . (string) $__res['reason'] . ' (' . (int) $__res['code'] . ')'; }
        } else { $msg = 'قسطٌ غيرُ مستحقٍّ أو مسدَّدٌ من قبل (409)'; }
    }
}

$rows = array();
$w = "o.company_id = $company_id" . ($op_filter ? " AND i.op_id = $op_filter" : '');
$r = mysqli_query($conn, "SELECT i.*, o.op_code, o.currency op_cur FROM financing_installments i
                          JOIN financing_operations o ON o.op_id = i.op_id
                          WHERE $w ORDER BY i.state = 'paid', i.due_date LIMIT 200");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الأقساط والسداد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-doc-cycle" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-calendar-check';
$header_title_html = htmlspecialchars('الأقساطُ والسداد' . ($op_filter ? ' — عملية #' . $op_filter : ''), ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑫: شاشةُ دورةِ سدادٍ تنطق بحالةٍ حية (مستحق · متأخر · مسدَّد) — فتُعلن خطوتَها التالية
echo ems_next_step('سدادُ القسطِ المستحقِّ بمرجعِ سندٍ — وعند بلوغِ الرصيدِ صفرًا تُقفَل العملية');
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا أقساطَ ضمنَ هذا النطاق', 'تُولَّد الأقساطُ آليًّا عند إنشاءِ عمليةِ التمويل — راجع العملياتِ أو غيّر الترشيح');
?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('financing', 'الأقساطُ والسداد'); ?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($op_filter): $ff_op_id = $op_filter; $ff_active = 'installments';
        include __DIR__ . '/../includes/financing_file_tabs.php'; endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>عملية التمويل</th><th>#</th><th>تاريخ الاستحقاق</th><th>أصل القسط</th><th>ربح القسط</th><th>إجمالي القسط</th><th>الحالة</th><th>تاريخ السداد الفعلي</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم القسط</th>
              <th class="ems-fn-th" data-fn="1">الممول</th>
              <th class="ems-fn-th" data-fn="1">الرصيد قبل</th>
              <th class="ems-fn-th" data-fn="1">الرصيد بعد</th>
              <th class="ems-fn-th" data-fn="1">أيام التأخير</th>
              <th class="ems-fn-th" data-fn="1">غرامة تأخير</th>
              <th class="ems-fn-th" data-fn="1">رقم سند الصرف</th>
              <th class="ems-fn-th" data-fn="1">رقم القيد</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted">لا أقساطَ<?= $op_filter ? ' لهذه العملية' : '' ?> — تُولَّد عند إنشاء العملية</td></tr><?php endif; ?>
    <?php foreach ($rows as $i):
        $paid = strtolower($i['state']) === 'paid';
        $late = !$paid && $i['due_date'] < date('Y-m-d'); ?>
      <tr<?= $late ? ' class="inst-row-late"' : '' ?>>
        <td><a href="operation_profile.php?id=<?= intval($i['op_id']) ?>"><?= htmlspecialchars($i['op_code'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= intval($i['seq_no']) ?></td>
        <td><?= htmlspecialchars($i['due_date'], ENT_QUOTES, 'UTF-8') ?><?= $late ? ' <span class="badge inst-badge-late">متأخر</span>' : '' ?></td>
        <td><?= number_format(floatval($i['amount_principal']), 2) ?></td>
        <td><?= number_format(floatval($i['amount_profit']), 2) ?></td>
        <td><strong><?= number_format(floatval($i['amount_total']), 2) ?></strong> <?= htmlspecialchars($i['currency'] ?: $i['op_cur'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $paid ? '<span class="badge inst-badge-paid">مسدَّد ' . htmlspecialchars($i['paid_date'], ENT_QUOTES, 'UTF-8') . '</span>'
                       : '<span class="badge inst-badge-due">مستحق</span>' ?></td>
        <td>
          <?php if (!$paid): ?>
          <form method="post" class="inst-pay-form">
        <?= csrf_field() ?>
            <input type="hidden" name="pay_inst" value="<?= intval($i['inst_id']) ?>">
            <input type="text" name="payment_ref" class="form-control form-control-sm inst-pay-ref" placeholder="مرجعُ السند" required aria-label="مرجعُ السند">
            <button class="action-btn" type="submit">سدّد</button>
          </form>
          <?php else: ?><?= htmlspecialchars($i['payment_ref'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

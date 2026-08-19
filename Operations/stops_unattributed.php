<?php
/**
 * Operations/stops_unattributed.php — التوقفاتُ بلا مسؤول (★ الموقع · update0007-ب F8)
 * «صفرُ واقعةٍ بلا مسؤولٍ شرطُ إقفال اليوم» (UAT §9-①) — ساعاتُ التعطل التي
 * لم تُسند لجهةٍ تُعرض هنا وتُسند بقرارٍ — فلا يُقفل يومٌ وفيه توقفٌ يتيم.
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
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['assign_ts'])) {
    $tid  = intval($_POST['assign_ts']);
    $dept = trim($_POST['fault_department'] ?? '');
    if ($dept === '') { $msg = 'الجهةُ المسؤولةُ إلزامية (422)'; }
    else {
        /* ═══════════════════════════════════════════════════════════════════
         * INJ-0139 — «كلُّ فعلٍ كاتبٍ في وحدةِ التشغيلِ يُنتج صفَّ تدقيقٍ واحدًا
         * يحمل الجدولَ والمعرّفَ والقيمةَ **قبل وبعد**».
         * ◆ والمقيسُ أنَّ `Operations/` كان بـ**صفرِ** نداءٍ لـ`ems_audit_change`
         *   بينما 259 ملفًّا في المستودعِ تناديه — فإسنادُ مسؤوليةِ توقفٍ (وهو
         *   قرارٌ يُحمّل إدارةً تكلفةً) كان يقع **بلا أثرٍ يُراجَع**.
         * ◆ والقيمةُ «قبل» **تُقرأ من الصفِّ** لا تُفترض نُلًّا: الشرطُ يسمح
         *   بالإسنادِ على فارغٍ أو نُلٍّ، فالفرقُ بينهما أثرٌ حقيقيٌّ يُسجَّل.
         * ◆ ولا يُسجَّل إلا **عند وقوعِ تغييرٍ فعليٍّ** (`affected_rows > 0`) —
         *   فإعادةُ الإسنادِ نفسِه لا تكتب صفَّ ضوضاء.
         * ═══════════════════════════════════════════════════════════════════ */
        $beforeDept = null;
        $pre = mysqli_prepare($conn, 'SELECT fault_department FROM timesheet WHERE id = ? AND company_id = ?');
        if ($pre) {
            mysqli_stmt_bind_param($pre, 'ii', $tid, $company_id);
            mysqli_stmt_execute($pre);
            $pr = mysqli_stmt_get_result($pre);
            if ($pr && ($prow = mysqli_fetch_assoc($pr))) { $beforeDept = $prow['fault_department']; }
            mysqli_stmt_close($pre);
        }
        $st = mysqli_prepare($conn, "UPDATE timesheet SET fault_department = ? WHERE id = ? AND company_id = ?
                                     AND (fault_department IS NULL OR fault_department = '')");
        mysqli_stmt_bind_param($st, 'sii', $dept, $tid, $company_id);
        mysqli_stmt_execute($st);
        $changed = mysqli_stmt_affected_rows($st) > 0;
        /* ◆ **يُحمَّل المُوصِلُ عند موضعِ الاستعمال** كما تفعل بقيةُ الشاشات
             (`admin/permissions/role_permissions.php:88` نمطًا). ولولا ذلك لكان
             `function_exists` **كاذبًا دائمًا** فيُتخطّى التدقيقُ صامتًا — وهو
             عينُ «حارسٍ قائمٍ نصًّا غائبٍ فعلًا». */
        require_once __DIR__ . '/../includes/audit_trail.php';
        if ($changed) {
            ems_audit_change($conn, 'operations', 'stops_unattributed.php', 'assign_fault_department',
                $tid,
                array('fault_department' => $beforeDept),
                array('fault_department' => $dept),
                array('company_id' => $company_id, 'user_id' => $uid,
                      'note' => 'إسنادُ مسؤوليةِ توقفٍ غيرِ مُسنَد'));
        }
        $msg = $changed ? "أُسند التوقفُ #$tid إلى «{$dept}»" : 'مُسندٌ من قبل (409)';
    }
}

$rows = array();
$r = mysqli_query($conn, "SELECT t.id, t.date, t.operator, t.shift, t.total_fault_hours, t.fault_type, t.fault_details
                          FROM timesheet t
                          WHERE t.company_id = $company_id AND t.total_fault_hours > 0
                            AND (t.fault_department IS NULL OR t.fault_department = '')
                          ORDER BY t.date DESC LIMIT 100");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
$depts = array('العميل','المورد','الصيانة','التشغيل','المشغّل','قوةٌ قاهرة');

$page_title = 'التوقفات بلا مسؤول';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-user-slash';
$header_title_html = htmlspecialchars('التوقفاتُ بلا مسؤول', ENT_QUOTES, 'UTF-8');
ob_start(); ?><span class="badge stu-badge-count"><?= count($rows) ?> — ولا يُقفل يومٌ وفيها واحد</span><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
echo ems_states_bundle('لا توقفاتٍ بلا جهةٍ مسؤولةٍ في هذا الكيان', 'راجعِ التوقفاتِ المسنَدةَ في سجلِّ الوردياتِ إن كنت تبحث عن واقعةٍ بعينها');
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>#</th><th>التاريخ</th><th>أثر أجر المشغّل</th><th>الوردية</th><th>ساعاتُ التعطل</th><th>النوع</th><th>الإسناد</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم التوقف</th>
              <th class="ems-fn-th" data-fn="1">الموقع</th>
              <th class="ems-fn-th" data-fn="1">كود المعدة</th>
              <th class="ems-fn-th" data-fn="1">الوحدة التعاقدية</th>
              <th class="ems-fn-th" data-fn="1">من الساعة</th>
              <th class="ems-fn-th" data-fn="1">إلى الساعة</th>
              <th class="ems-fn-th" data-fn="1">المدة بالساعات</th>
              <th class="ems-fn-th" data-fn="1">تصنيف التوقف</th>
              <th class="ems-fn-th" data-fn="1">السبب التفصيلي</th>
              <th class="ems-fn-th" data-fn="1">الطرف المتحمل</th>
              <th class="ems-fn-th" data-fn="1">مرجع بند العقد</th>
              <th class="ems-fn-th" data-fn="1">أمر الصيانة المرتبط</th>
              <th class="ems-fn-th" data-fn="1">أثر الفوترة</th>
              <th class="ems-fn-th" data-fn="1">أثر استحقاق المورد</th>
              <th class="ems-fn-th" data-fn="1">سجّله</th>
              <th class="ems-fn-th none" data-fn="1">أسنده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center stu-ok">✔ صفرُ توقفٍ بلا مسؤول</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><?= intval($t['id']) ?></td>
        <td><?= htmlspecialchars($t['date'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($t['operator'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($t['shift'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong><?= floatval($t['total_fault_hours']) ?></strong></td>
        <td><?= htmlspecialchars($t['fault_type'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" class="stu-assign-form">
        <?= csrf_field() ?>
            <input type="hidden" name="assign_ts" value="<?= intval($t['id']) ?>">
            <select name="fault_department" aria-label="الجهةُ المسؤولةُ عن التوقف" class="form-control form-control-sm stu-dept-select" required>
              <option value="">— الجهة —</option>
              <?php foreach ($depts as $d): ?><option><?= $d ?></option><?php endforeach; ?>
            </select>
            <button class="action-btn" type="submit">أسند</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

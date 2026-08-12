<?php
/**
 * Operations/swap_request.php — طلبُ التبديل (NAV-01 v6 §6.3 · update0007 S-02)
 * ───────────────────────────────────────────────────────────────────────────
 * «التبديلُ طلبٌ بموافقتين لا فعلٌ مباشر»:
 *   ① يطلب: مديرُ التشغيل من لوحة المواقع · أو مديرُ الموقع من موقعه.
 *   ② يوافق: القوى التشغيلية (تأهيلُ البديل ورخصتُه) + الإدارةُ المعنية
 *     (الموردون إن مسّ حصةً · الصيانةُ إن مسّت جاهزية).
 *   ③ الأثرُ المقدَّر يُحسب قبل الإرسال ويُعرض على الموافقين.
 *   ④ لا يغيّر الحصةَ التعاقدية ولا يصفّر عجزًا (CAP-01 §7).
 *   ⑤ بمدةٍ محددةٍ تُجدَّد بقرار — ولا تبديلَ مفتوحُ المدة.
 *   ⑥ بندٌ في صندوق الاعتماد الجامع — لا رسائلُ ولا اتصالات.
 *
 * التنفيذُ يقف على substitute_coverages (CAP-01) — فالطلبُ المعتمدُ تغطيةٌ
 * من الدرجة المناسبة، وأثرُها محاسبيًّا محكومٌ هناك.
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
$uid        = intval($_SESSION['user']['id'] ?? 0);
$site       = intval($_REQUEST['site'] ?? 0);
$equipment  = intval($_REQUEST['equipment'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $reason   = trim($_POST['reason_code'] ?? '');
    $sub_eq   = intval($_POST['substitute_equipment'] ?? 0);
    $sub_op   = intval($_POST['substitute_operator'] ?? 0);
    $until    = trim($_POST['valid_until'] ?? '');
    $est_h    = floatval($_POST['estimated_hours'] ?? 0);
    // §6.3-⑤: لا تبديلَ مفتوحُ المدة · و§6.1-①: سببٌ من قائمةٍ محكومة
    if ($reason === '' || $until === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
        $msg = 'السببُ المحكومُ وتاريخُ النهاية إلزاميان — ولا تبديلَ مفتوحُ المدة (422)';
    } elseif ($sub_eq <= 0 && $sub_op <= 0) {
        $msg = 'حدّد بديلًا: معدةً أو مشغّلًا (422)';
    } else {
        // المقعدُ المغطّى من حاوية المعدة في الموقع
        $seat = 0;
        $r = mysqli_query($conn, "SELECT id FROM op_containers WHERE project_id=$site AND equipment_id=$equipment AND is_deleted=0 LIMIT 1");
        if ($r && ($x = mysqli_fetch_assoc($r))) $seat = intval($x['id']);
        // الأثرُ المقدَّر يُحمل في الطلب — ويُعرض على الموافقين (§6.3-③)
        $level = 'cross_supplier'; // الافتراضُ الأوسط — والخدمةُ تعيد الحسم بالدرجة عند الاعتماد
        $ins = mysqli_prepare($conn,
            "INSERT INTO substitute_coverages (covering_supplier_id, covered_seat_id, reason_code,
                    valid_from, valid_to, level, estimated_hours, state, approvals_ref)
             VALUES (NULL, ?, ?, CURDATE(), ?, ?, ?, 'Pending', NULL)");
        mysqli_stmt_bind_param($ins, 'isssd', $seat, $reason, $until, $level, $est_h);
        if (mysqli_stmt_execute($ins)) {
            $covId = mysqli_stmt_insert_id($ins);
            /* INJ-0139 — أثرُ تدقيقٍ لكلِّ فعلٍ كاتبٍ في وحدةِ التشغيل.
               و«قبل» هنا **مصفوفةٌ خاويةٌ بحقٍّ**: الصفُّ يُنشأ من عدمٍ، فكلُّ
               حقلٍ تغييرٌ من نُلٍّ — وهو ما يحسبه المُوصِلُ المشتركُ نفسُه. */
            /* ◆ يُحمَّل المُوصِلُ عند موضعِ الاستعمال — وإلا كان الشرطُ كاذبًا
                 دائمًا فيُتخطّى التدقيقُ صامتًا. */
            require_once __DIR__ . '/../includes/audit_trail.php';
            {
                ems_audit_change($conn, 'operations', 'swap_request.php', 'create',
                    (int) $covId,
                    array(),
                    array('covered_seat_id' => $seat, 'reason_code' => $reason,
                          'valid_to' => $until, 'level' => $level,
                          'estimated_hours' => $est_h, 'state' => 'Pending'),
                    array('company_id' => $company_id,
                          'user_id' => intval($_SESSION['user']['id'] ?? 0),
                          'note' => 'طلبُ تبديلِ تغطيةٍ — بانتظارِ الاعتماد'));
            }
            // البندُ يظهر في صندوق الاعتماد الجامع عبر مجمِّعه (ApprovalsInboxService
            // يقرأ substitute_coverages بحالة Pending) — لا رسائلَ ولا جدولَ وسيطًا.
            $msg = "أُرسل الطلبُ #$covId — بانتظار موافقة القوى التشغيلية ثم الإدارة المعنية";
        } else {
            $msg = 'تعذّر الحفظ: ' . mysqli_error($conn);
        }
    }
}

/* بدائلُ متاحة: معداتٌ احتياطيةٌ بالموقع · ومشغّلون متاحون */
$subEq = array(); $subOp = array();
$r = mysqli_query($conn, "SELECT e.id, e.name FROM equipments e
                          JOIN op_containers oc ON oc.equipment_id = e.id AND oc.role_kind = 'standby'
                          WHERE oc.project_id = $site AND oc.is_deleted = 0");
if ($r) while ($x = mysqli_fetch_assoc($r)) $subEq[] = $x;
$r = mysqli_query($conn, "SELECT id, name FROM employees WHERE company_id = $company_id AND is_operator = 1 LIMIT 60");
if ($r) while ($x = mysqli_fetch_assoc($r)) $subOp[] = $x;

$page_title = 'طلب تبديل';
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
$header_icon = 'fa fa-exchange-alt';
$header_title_html = htmlspecialchars('طلبُ تبديلٍ — بموافقتين', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <form method="post" class="ems-form" style="max-width:640px">
    <input type="hidden" name="site" value="<?= $site ?>">
    <input type="hidden" name="equipment" value="<?= $equipment ?>">
    <div class="form-group">
      <label for="emsf_998_3378c">السببُ — من قائمةٍ محكومةٍ لا نصٍّ حر</label>
      <select name="reason_code" class="form-control" required id="emsf_998_3378c">
        <option value="">— اختر —</option>
        <option value="breakdown">عطل</option>
        <option value="scheduled_maintenance">صيانةٌ مجدولة</option>
        <option value="transfer_out">خروجٌ للترحيل</option>
        <option value="expired_document">وثيقةٌ منتهية</option>
        <option value="operator_shortage">نقصُ مشغّل</option>
      </select>
    </div>
    <div class="form-group"><label for="emsf_999_051ff">المعدةُ البديلة (احتياطيُّ الموقع)</label>
      <select name="substitute_equipment" class="form-control" id="emsf_999_051ff"><option value="0">—</option>
        <?php foreach ($subEq as $e): ?><option value="<?= intval($e['id']) ?>"><?= htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label for="emsf_1000_69769">المشغّلُ البديل</label>
      <select name="substitute_operator" class="form-control" id="emsf_1000_69769"><option value="0">—</option>
        <?php foreach ($subOp as $o): ?><option value="<?= intval($o['id']) ?>"><?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label for="emsf_1001_a275b">حتى تاريخ — إلزامي</label>
      <input type="date" name="valid_until" class="form-control" required id="emsf_1001_a275b"></div>
    <div class="form-group"><label for="emsf_1002_98ab4">الساعاتُ المقدَّرةُ المتأثرة (الأثرُ يُعرض على الموافقين)</label>
      <input type="number" step="0.5" name="estimated_hours" class="form-control" value="0" id="emsf_1002_98ab4"></div>
    <button type="submit" class="btn btn-primary">إرسالُ الطلب لصندوق الاعتماد</button>
  </form>
  <p class="text-muted" style="font-size:.85em;margin-top:10px">
    لا يغيّر التبديلُ الحصةَ التعاقديةَ ولا يصفّر عجزَ المتعطل (CAP-01 §7) —
    والموافقتان: القوى التشغيلية (تأهيلُ البديل) ثم الإدارةُ المعنية.</p>
</div>

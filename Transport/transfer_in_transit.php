<?php
/**
 * Transport/transfer_in_transit.php — الحركةُ في الطريق (★ النقل · update0007 S-08)
 * ───────────────────────────────────────────────────────────────────────────
 * دورةُ الترحيل الناقصة (NAV-02 §12-③): ما بين المغادرة والوصول.
 * أوامرُ `in_transit` بمركباتها وسائقيها ومساراتها — وزرُّ «وصلت» يقدّم
 * المرحلةَ إلى arrived ويؤرّخ الوصولَ ويسجّل الحدث.
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['arrive_id'])) {
    // CS-05 / AC-F6 — التقدُّمُ والواقعةُ في الخدمة، والسطحُ يعرض الرسالة.
    $oid = intval($_POST['arrive_id']);
    require_once __DIR__ . '/../app/Services/Transport/TransferDeliveryService.php';
    $res = \App\Services\Transport\TransferDeliveryService::confirmArrival($conn, $company_id, $oid, $uid);
    $msg = $res['msg'];
}

$rows = array();
$r = mysqli_query($conn,
    "SELECT o.id, o.order_no, o.departure_datetime, o.planned_date, o.route,
            fl.name AS from_loc, tl.name AS to_loc, e.name AS vehicle, emp.name AS driver
     FROM transfer_orders o
     LEFT JOIN trs_locations fl ON fl.id = o.from_location_id
     LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
     LEFT JOIN equipments e ON e.id = o.vehicle_id
     LEFT JOIN employees emp ON emp.id = o.driver_id
     WHERE o.company_id = $company_id AND o.is_deleted = 0 AND o.stage = 'in_transit'
     ORDER BY o.departure_datetime");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الحركة في الطريق';
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
$header_icon = 'fa fa-truck-moving';
$header_title_html = htmlspecialchars('الحركةُ في الطريق', ENT_QUOTES, 'UTF-8');
ob_start(); ?><span class="badge trs-it-count"><?= count($rows) ?> في الطريق</span><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا حركةَ في الطريقِ الآن', 'أكّدِ المغادرةَ من أمرِ الترحيلِ لتظهرَ الرحلةُ في هذه الشاشة');
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>أمر الترحيل</th><th>من → إلى</th><th>المركبة</th><th>السائق</th><th>تاريخ المغادرة</th><th>منذ</th><th>إجراء</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الرحلة</th>
              <th class="ems-fn-th" data-fn="1">وقت المغادرة</th>
              <th class="ems-fn-th" data-fn="1">مشرف الانطلاق</th>
              <th class="ems-fn-th" data-fn="1">الموقع الحالي المسجَّل</th>
              <th class="ems-fn-th" data-fn="1">آخر تحديث</th>
              <th class="ems-fn-th" data-fn="1">المسافة المقطوعة</th>
              <th class="ems-fn-th" data-fn="1">المتبقي</th>
              <th class="ems-fn-th" data-fn="1">الوصول المتوقع</th>
              <th class="ems-fn-th" data-fn="1">التأخر بالساعات</th>
              <th class="ems-fn-th" data-fn="1">سبب التأخر</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">لا معدةَ في الطريق — وصفرُ عالقٍ</td></tr><?php endif; ?>
    <?php foreach ($rows as $o):
        $dep = $o['departure_datetime'] ? strtotime($o['departure_datetime']) : null;
        $hrs = $dep ? round((time() - $dep) / 3600, 1) : null; ?>
      <tr<?= ($hrs !== null && $hrs > 48) ? ' class="trs-it-late"' : '' ?>>
        <td><?= htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(($o['from_loc'] ?: '؟') . ' ← ' . ($o['to_loc'] ?: '؟'), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['vehicle'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['driver'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['departure_datetime'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $hrs !== null ? $hrs . ' ساعة' : '—' ?></td>
        <td><form method="post" class="trs-it-inline">
        <?= csrf_field() ?><input type="hidden" name="arrive_id" value="<?= intval($o['id']) ?>">
            <button class="action-btn" type="submit"><i class="fa fa-flag-checkered"></i> وصلت</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
/**
 * Financing/cost_allocation.php — توزيعُ تكلفة التمويل (★ · update0007-ب F6)
 * ───────────────────────────────────────────────────────────────────────────
 * «تكلفةُ التمويل تُحمَّل على المشروع تشغيليًّا بلا كشف مصدرها التمويلي —
 * والتوجيهُ في الخادم» (ORG-01 §3-④ · CAP-01): ربحُ كل عمليةٍ يوزَّع على
 * مشاريع أعيانها بنسبة حصصها — تقريرُ توزيعٍ للمجال المقيَّد.
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
if (!$granted) { ems_gov_flash_redirect('../main/dashboard.php', 'المجالُ المقيَّد (FIN-01 §1.1) ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك'); }

/* توزيعُ ربح العملية على مشاريع أعيانها: أينما تعمل المعدةُ اليومَ (op_containers) */
$rows = array();
$r = mysqli_query($conn,
    "SELECT o.op_code, o.profit_amount, o.installments_no, o.currency,
            s.asset_id, e.name eq_name,
            COALESCE(s.approved_percent, s.percent) pct,
            p.name project_name
     FROM financing_operations o
     JOIN asset_ownership_shares s ON s.op_id = o.op_id AND (s.valid_to IS NULL OR s.valid_to >= CURDATE())
     LEFT JOIN equipments e ON e.id = s.asset_id AND s.asset_kind = 'equipment'
     LEFT JOIN op_containers oc ON oc.equipment_id = s.asset_id AND oc.is_deleted = 0
     LEFT JOIN project p ON p.id = oc.project_id
     WHERE o.company_id = $company_id AND o.state = 'active'
     GROUP BY o.op_id, s.share_id, p.id
     ORDER BY o.op_code, s.asset_id");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'توزيع تكلفة التمويل';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-percentage';
$header_title_html = htmlspecialchars('توزيعُ تكلفة التمويل على المشاريع', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا عملياتِ تمويلٍ نشطةً بأعيانٍ مملوكةٍ لتوزيعِ تكلفتها', 'فعِّل عمليةَ تمويلٍ وسجِّل حصصَ أعيانها ليظهر التوزيعُ على المشاريع');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <p class="text-muted fin-ca-note">الربحُ الشهريُّ لكل عمليةٍ (الإجماليُّ ÷ الأقساط) يوزَّع على مشروع كل عينٍ بنسبة حصتها — والتشغيلُ يرى تكلفةً محمَّلةً بلا مصدر.</p>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>عملية التمويل</th><th>العين المموَّلة</th><th>الحصة ٪</th><th>المشروع</th><th>ربحُ العملية شهريًّا</th><th>المحمَّلُ على المشروع</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">تكلفة التمويل للفترة</th>
              <th class="ems-fn-th" data-fn="1">ساعات تشغيل العين</th>
              <th class="ems-fn-th" data-fn="1">الساعات في المشروع</th>
              <th class="ems-fn-th" data-fn="1">نسبة التوزيع</th>
              <th class="ems-fn-th" data-fn="1">رقم القيد</th>
              <th class="ems-fn-th" data-fn="1">وزّعه</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
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
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted">لا عملياتِ تمويلٍ نشطةً بأعيانها</td></tr><?php endif; ?>
    <?php foreach ($rows as $a):
        $monthly = intval($a['installments_no']) > 0 ? floatval($a['profit_amount']) / intval($a['installments_no']) : 0;
        $alloc = $monthly * floatval($a['pct']) / 100; ?>
      <tr>
        <td><?= htmlspecialchars($a['op_code'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($a['eq_name'] ?: '#' . $a['asset_id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= floatval($a['pct']) ?></td>
        <td><?= htmlspecialchars($a['project_name'] ?: 'غيرُ مخصَّصةٍ حاليًّا', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format($monthly, 2) ?> <?= htmlspecialchars($a['currency'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><strong><?= number_format($alloc, 2) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

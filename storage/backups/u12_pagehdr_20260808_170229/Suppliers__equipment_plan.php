<?php
/**
 * Suppliers/equipment_plan.php — خطةُ معدات المورد (★ · update0007-ب F13)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل موردٍ: معداتُه المخصَّصةُ بدورها (أساسيةٌ/احتياطية) ومقاعدُها ومواقعُها
 * وفتراتُ سريانها — «الاحتياطيُّ صفرُ ساعاتٍ قبل التفعيل» (CAP-01 §8.3).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$sup = intval($_GET['supplier_id'] ?? 0);

$sups = array(); $rows = array();
$r = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE company_id = $company_id ORDER BY name");
if ($r) while ($x = mysqli_fetch_assoc($r)) $sups[] = $x;
if ($sup > 0) {
    $r = mysqli_query($conn,
        "SELECT oc.seat_no, oc.role_kind, oc.cap_qty, oc.valid_from, oc.valid_to, oc.state,
                e.id eq_id, e.name eq_name, p.name project_name
         FROM op_containers oc
         LEFT JOIN equipments e ON e.id = oc.equipment_id
         LEFT JOIN project p ON p.id = oc.project_id
         WHERE oc.company_id = $company_id AND oc.supplier_id = $sup AND oc.is_deleted = 0
         ORDER BY p.id, oc.seat_no");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
}

$page_title = 'خطة معدات المورد';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-truck"></i> خطةُ معدات المورد</h4></div>
  <form method="get" class="ems-form" style="margin-bottom:14px">
    <select name="supplier_id" class="form-control" style="max-width:340px" onchange="this.form.submit()">
      <option value="">— اختر موردًا —</option>
      <?php foreach ($sups as $s): ?>
        <option value="<?= intval($s['id']) ?>" <?= $s['id'] == $sup ? 'selected' : '' ?>><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($sup > 0): ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المشروع</th><th>المقعد</th><th>نوع المعدة</th><th>دور المعدة</th><th>الطاقة</th><th>السريان</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الخطة</th>
              <th class="ems-fn-th" data-fn="1">المورد</th>
              <th class="ems-fn-th" data-fn="1">العقد</th>
              <th class="ems-fn-th" data-fn="1">العدد المتعهَّد</th>
              <th class="ems-fn-th" data-fn="1">الموديل المتوقع</th>
              <th class="ems-fn-th" data-fn="1">سنة الصنع الدنيا</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الجاهزية المتعهَّد</th>
              <th class="ems-fn-th" data-fn="1">الوصول الفعلي</th>
              <th class="ems-fn-th" data-fn="1">التأخر بالأيام</th>
              <th class="ems-fn-th" data-fn="1">جزاء التأخر</th>
              <th class="ems-fn-th" data-fn="1">سقف الاحتياطي المسموح</th>
              <th class="ems-fn-th" data-fn="1">كود المعدة</th>
              <th class="ems-fn-th" data-fn="1">المورد المتعاقد معه</th>
              <th class="ems-fn-th" data-fn="1">المالك الحقيقي</th>
              <th class="ems-fn-th" data-fn="1">علاقة المورد بالمالك</th>
              <th class="ems-fn-th none" data-fn="1">الموديل</th>
              <th class="ems-fn-th none" data-fn="1">سنة الصنع</th>
              <th class="ems-fn-th none" data-fn="1">الرقم التسلسلي</th>
              <th class="ems-fn-th none" data-fn="1">العقد المرتبط</th>
              <th class="ems-fn-th none" data-fn="1">الوحدة التعاقدية</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ التفعيل</th>
              <th class="ems-fn-th none" data-fn="1">العدّاد عند التفعيل</th>
              <th class="ems-fn-th none" data-fn="1">العدّاد الحالي</th>
              <th class="ems-fn-th none" data-fn="1">حالة الجاهزية</th>
              <th class="ems-fn-th none" data-fn="1">حق الرجوع</th>
              <th class="ems-fn-th none" data-fn="1">درجة سرية الملكية</th>
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
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">لا خطةَ معداتٍ لهذا المورد</td></tr><?php endif; ?>
    <?php foreach ($rows as $c):
        $standby = ($c['role_kind'] === 'standby'); ?>
      <tr>
        <td><?= htmlspecialchars($c['project_name'] !== null ? $c['project_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($c['seat_no'] !== null ? $c['seat_no'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $c['eq_id'] ? '<a href="../Equipments/equipment_profile.php?id=' . intval($c['eq_id']) . '">'
              . htmlspecialchars($c['eq_name'], ENT_QUOTES, 'UTF-8') . '</a>' : '<span class="text-muted">فجوةٌ — بلا معدة</span>' ?></td>
        <td><?= $standby ? '<span class="badge" style="background:#6c757d">احتياطية — صفرُ ساعاتٍ قبل التفعيل</span>'
                          : '<span class="badge" style="background:#0d6efd">أساسية</span>' ?></td>
        <td><?= number_format(floatval($c['cap_qty']), 1) ?></td>
        <td><?= htmlspecialchars(($c['valid_from'] !== null ? $c['valid_from'] : '؟') . ' ← ' . ($c['valid_to'] !== null ? $c['valid_to'] : 'ساري'), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($c['state'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

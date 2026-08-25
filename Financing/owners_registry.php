<?php
/**
 * Financing/owners_registry.php — الملكيةُ والمُلّاك (★ · update0007-ب F4)
 * ───────────────────────────────────────────────────────────────────────────
 * سجلُّ ملكية المعدات وحصصُها القائمة — أشدُّ المجال المقيَّد سريةً:
 * «المعدةُ تحمل أسماءَ المُلّاك» (ORG-01 v4) — وكلُّ اطّلاعٍ يُسجَّل.
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
if (!$granted) { ems_gov_flash_redirect('../main/dashboard.php', 'المجال المقيد — سجل الملاك بمنح فردي (FIN-01 §1.1) ❌', 'GOV-PERM-403', 'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك'); }

/* كلُّ اطّلاعٍ على السجل يُسجَّل — سجلُّ اطّلاع المجال المقيَّد */
mysqli_query($conn, "INSERT INTO action_execution_log (company_id, action_code, person_id, subject_ref, result)
                     VALUES ($company_id, 'ownership.registry.view', $uid, 'owners_registry', 'allowed')");

/* ── INJ-0051 · وفي **سجلِّ الاطّلاعِ على الحقولِ الحساسة** أيضًا ─────────────────
     نصُّ القبول: «كلُّ فتحٍ للشاشة يكتب سطرًا في `sensitive_read_log` بالكود
     المقروء». و`action_execution_log` سجلُّ أفعالٍ لا سجلُّ اطّلاع — وشاشةُ
     المراجعةِ `Governance/read_log.php` تقرأ الثاني لا الأول. فيُكتب فيهما معًا:
     الأوّلُ لأثرِ الفعل، والثاني لمن يراجع من اطّلع على اسمِ المالك. */
require_once __DIR__ . '/../includes/sensitive_read_log.php';
ems_log_sensitive_read($conn, 'owner_name', 'owners_registry:' . $company_id,
    'Financing/owners_registry.php');

$rows = array();
$r = mysqli_query($conn,
    "SELECT r.equipment_id, e.name eq_name, r.owner_type owner_kind, r.actual_owner_name owner_ref, r.note ownership_note,
            s.approved_percent, s.percent, s.valid_from, s.valid_to, le.legal_name financier
     FROM equipment_ownership_registry r
     LEFT JOIN equipments e ON e.id = r.equipment_id
     LEFT JOIN asset_ownership_shares s ON s.asset_id = r.equipment_id AND s.asset_kind = 'equipment'
                                        AND (s.valid_to IS NULL OR s.valid_to >= CURDATE())
     LEFT JOIN legal_entities le ON le.entity_id = s.financier_entity_id
     WHERE r.company_id = $company_id
     ORDER BY r.equipment_id");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الملكية والملاك';
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
$header_icon = 'fa fa-user-shield';
$header_title_html = htmlspecialchars('الملكية والملاك — سجل مقيد', ENT_QUOTES, 'UTF-8');
ob_start(); ?><span class="badge fin-own-badge-audit">كل اطلاع مسجل</span><?php
$header_actions = array(array('raw' => trim((string) ob_get_clean())));
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا معدات مسجلة في سجل الملاك والحصص', 'سجل ملكية المعدة وحصة ممولها لتظهر في هذا السجل');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>كود المعدة</th><th>نوع المالك</th><th>مرجع التفويض</th><th>الممول الحالي</th><th>حصته ٪</th><th>سريانها</th><th>ملاحظة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">المالك القانوني</th>
              <th class="ems-fn-th" data-fn="1">نوع الملكية</th>
              <th class="ems-fn-th" data-fn="1">نسبة الملكية</th>
              <th class="ems-fn-th" data-fn="1">المنتفع الاقتصادي</th>
              <th class="ems-fn-th" data-fn="1">مرتهن الضمان</th>
              <th class="ems-fn-th" data-fn="1">عملية التمويل المرتبطة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ بدء الملكية</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الانتهاء</th>
              <th class="ems-fn-th" data-fn="1">مستند الملكية</th>
              <th class="ems-fn-th" data-fn="1">حق الرجوع</th>
              <th class="ems-fn-th" data-fn="1">درجة السرية</th>
              <th class="ems-fn-th" data-fn="1">من يملك صلاحية الاطلاع</th>
              <th class="ems-fn-th" data-fn="1">رقم الحصة</th>
              <th class="ems-fn-th" data-fn="1">كود العين</th>
              <th class="ems-fn-th" data-fn="1">الممول أو المالك</th>
              <th class="ems-fn-th none" data-fn="1">النسبة المسجلة</th>
              <th class="ems-fn-th none" data-fn="1">النسبة المصححة</th>
              <th class="ems-fn-th none" data-fn="1">سبب التصحيح</th>
              <th class="ems-fn-th none" data-fn="1">من تاريخ</th>
              <th class="ems-fn-th none" data-fn="1">إلى تاريخ</th>
              <th class="ems-fn-th none" data-fn="1">المشتري عند التخارج</th>
              <th class="ems-fn-th none" data-fn="1">مستند التخارج</th>
              <th class="ems-fn-th none" data-fn="1">رأس المال المساهم به</th>
              <th class="ems-fn-th none" data-fn="1">تقييم الحصة</th>
              <th class="ems-fn-th none" data-fn="1">مستند الحصة</th>
              <th class="ems-fn-th none" data-fn="1">مجموع الحصص النشطة</th>
              <th class="ems-fn-th none" data-fn="1">حالة قيد المئة</th>
              <th class="ems-fn-th none" data-fn="1">سجلها</th>
              <th class="ems-fn-th none" data-fn="1">اعتمدها</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">السجل فارغ</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['eq_name'] ?: '#' . $o['equipment_id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['owner_kind'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['owner_ref'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['financier'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $o['approved_percent'] !== null ? floatval($o['approved_percent']) : ($o['percent'] !== null ? floatval($o['percent']) : '—') ?></td>
        <td><?= $o['valid_from'] ? htmlspecialchars($o['valid_from'] . ' ← ' . ($o['valid_to'] ?: 'ساري'), ENT_QUOTES, 'UTF-8') : '—' ?></td>
        <td><?= htmlspecialchars(mb_substr($o['ownership_note'] ?? '', 0, 40), ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p><a class="action-btn" href="asset_disposal.php"><i class="fa fa-exchange-alt"></i> التصرف في الأصل — نقل حصة أو بيعها</a></p>
</div>

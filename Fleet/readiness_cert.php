<?php
/**
 * Fleet/readiness_cert.php — شهاداتُ جاهزيةِ المعدات
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`readiness_lines`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ الجاهزيةُ سطرٌ لكلِّ بندٍ مطلوبٍ بعقدٍ — والفجوةُ فرقُ «المطلوب» عن «المتاح»، تُقرأ ولا تُصحَّح من هنا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Fleet/readiness_cert.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحيات يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$rows = array(); $failed = false;
$sql = "SELECT t.`readiness_code`, t.`name`, t.`contract_ref`, t.`required`, t.`available`, t.`state`, t.`gap_note`, t.`created_at` FROM `readiness_lines` t
         WHERE t.company_id = ? AND (is_deleted = 0)
         ORDER BY t.id DESC LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else { $res = $st->get_result(); while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; } }
    $st->close();
}

$page_title = 'شهادات جاهزية المعدات';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-certificate';
$header_title_html = htmlspecialchars('شهادات جاهزية المعدات', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا سطر جاهزية مسجلا بعد',
    'سطور الجاهزية تقيد من بنود العقد المطلوبة — راجع بنود العقد لتظهر هنا');
?>
<style>
.fl-rc-alert  { margin:10px 0; }
.fl-rc-kpi    { padding:10px 14px; margin:10px 0; border-inline-start:4px solid var(--c-0d6efd); display:inline-block; }
.fl-rc-kpi-lbl{ font-size:.78rem; opacity:.75; }
.fl-rc-kpi-val{ font-size:1.4rem; font-weight:700; }
.fl-rc-table  { width:100%; }
.fl-rc-none   { text-align:center; opacity:.7; }
.fl-rc-note   { font-size:.8rem; margin-top:8px; }
</style>
  <?php if ($failed): ?>
  <div class="alert alert-danger fl-rc-alert">
    <strong>تعذرت قراءة البيانات.</strong>
    فرق بين «لا صف» و«تعذر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card fl-rc-kpi">
    <div class="fl-rc-kpi-lbl">صفوف معروضة</div>
    <div class="fl-rc-kpi-val"><?php echo number_format(count($rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped fl-rc-table">
      <thead><tr>
        <th>رمز الجاهزية</th>
        <th>البند</th>
        <th>العقد</th>
        <th>المطلوب</th>
        <th>المتاح</th>
        <th>الحال</th>
        <th>ملاحظة الفجوة</th>
        <th>أنشئ</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="99" class="fl-rc-none">لا صف مسجل بعد.</td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $x['readiness_code'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['contract_ref'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['required'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['available'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['state'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['gap_note'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted fl-rc-note">
      قراءة محضة — الجاهزية سطر لكل بند مطلوب بعقد — والفجوة فرق «المطلوب» عن «المتاح»، تقرأ ولا تصحح من هنا. وأحدث 500 صف.
    </p>
  </div></div>
  <?php endif; ?>
</div>

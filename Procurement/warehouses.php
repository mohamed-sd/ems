<?php
/**
 * Procurement/warehouses.php — المخازنُ وأنواعُها
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`proc_warehouse`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ سجلُّ المخازنِ المرجعيّ — والإضافةُ والتعديلُ من بياناتِ المشترياتِ المرجعية، فمسارا تعريفٍ يتفرّقان أسوأُ من شاشةٍ ناقصة.
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

$__pp = check_page_permissions($conn, 'Procurement/warehouses.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحياتُ يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$rows = array(); $failed = false;
$sql = "SELECT t.`code`, t.`name`, t.`type`, t.`location`, t.`status`, t.`notes`, t.`created_at` FROM `proc_warehouse` t
         WHERE t.company_id = ? AND (is_deleted = 0)
         ORDER BY t.code LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else { $res = $st->get_result(); while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; } }
    $st->close();
}

$page_title = 'المخازنُ وأنواعُها';
include '../inheader.php';
include '../insidebar.php';
// UXW-01 §8-2: موضعُ الشاشةِ من رحلةِ أمرِ الصيانة
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('mnt_order', 'قطعُ الغيارِ والمخزون');
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-warehouse';
$header_title_html = htmlspecialchars('المخازنُ وأنواعُها', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا */
echo ems_states_bundle('لا مستودعاتٍ مسجَّلةً بعد',
    'تُعرَّف المستودعاتُ وأنواعُها من بياناتِ المشترياتِ المرجعية — فتظهر هنا فورَ تسجيلِها');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <?php if ($failed): ?>
  <div class="alert alert-danger whx-alert-gap">
    <strong>تعذّرت قراءةُ البيانات.</strong>
    فرقٌ بين «لا صفَّ» و«تعذّر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card whx-kpi">
    <div class="whx-kpi-label">صفوفٌ معروضة</div>
    <div class="whx-kpi-value"><?php echo number_format(count($rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped whx-w100">
      <thead><tr>
        <th>الرمز</th>
        <th>المخزن</th>
        <th>النوع</th>
        <th>الموقع</th>
        <th>الحال</th>
        <th>ملاحظات</th>
        <th>أُنشئ</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="99" class="whx-empty-cell">لا صفَّ مسجَّلٌ بعد.</td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $x['code'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['type'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['location'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['status'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['notes'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted whx-foot-note">
      قراءةٌ محضة — سجلُّ المخازنِ المرجعيّ — والإضافةُ والتعديلُ من بياناتِ المشترياتِ المرجعية، فمسارا تعريفٍ يتفرّقان أسوأُ من شاشةٍ ناقصة. وأحدثُ 500 صفٍّ.
    </p>
  </div></div>
  <?php endif; ?>
</div>

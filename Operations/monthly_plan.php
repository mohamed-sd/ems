<?php
/**
 * Operations/monthly_plan.php — الخطةُ الشهريةُ للتشغيل
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`scr_op_monthly`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ الخطةُ الشهريةُ مقابلَ المنفَّذ — وخطةُ الغدِ اليوميةُ في daily_plan.php وهي غيرُ هذه.
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

$__pp = check_page_permissions($conn, 'Operations/monthly_plan.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحياتُ يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$rows = array(); $failed = false;
$sql = "SELECT t.`month_ref`, t.`code_operator`, t.`equipment_name`, t.`site_name`, t.`days_work`, t.`hours_operations`, t.`hours_standby`, t.`pct_achievement`, t.`status_label` FROM `scr_op_monthly` t
         WHERE t.company_id = ? AND (1=1)
         ORDER BY t.month_ref DESC, id DESC LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else { $res = $st->get_result(); while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; } }
    $st->close();
}

$page_title = 'الخطةُ الشهريةُ للتشغيل';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-calendar-days';
$header_title_html = htmlspecialchars('الخطةُ الشهريةُ للتشغيل', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($failed): ?>
  <div class="alert alert-danger" style="margin:10px 0">
    <strong>تعذّرت قراءةُ البيانات.</strong>
    فرقٌ بين «لا صفَّ» و«تعذّر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card" style="padding:10px 14px;margin:10px 0;border-inline-start:4px solid #0d6efd;display:inline-block">
    <div style="font-size:.78rem;opacity:.75">صفوفٌ معروضة</div>
    <div style="font-size:1.4rem;font-weight:700"><?php echo number_format(count($rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped" style="width:100%">
      <thead><tr>
        <th>الشهر</th>
        <th>المشغّل</th>
        <th>المعدة</th>
        <th>الموقع</th>
        <th>أيامُ عمل</th>
        <th>ساعاتُ تشغيل</th>
        <th>ساعاتُ استعداد</th>
        <th>نسبةُ الإنجاز</th>
        <th>الحال</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="99" style="text-align:center;opacity:.7">لا صفَّ مسجَّلٌ بعد.</td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $x['month_ref'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['code_operator'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['equipment_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['site_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['days_work'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['hours_operations'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['hours_standby'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['pct_achievement'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['status_label'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted" style="font-size:.8rem;margin-top:8px">
      قراءةٌ محضة — الخطةُ الشهريةُ مقابلَ المنفَّذ — وخطةُ الغدِ اليوميةُ في daily_plan.php وهي غيرُ هذه. وأحدثُ 500 صفٍّ.
    </p>
  </div></div>
  <?php endif; ?>
</div>

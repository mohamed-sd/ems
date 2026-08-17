<?php
/**
 * Tickets/ticket_kpi.php — مؤشراتُ البلاغات
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`tickets`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ المؤشراتُ تُحسب على البلاغاتِ الحيّةِ لا على جدولٍ موازٍ — فرقمانِ لشيءٍ واحدٍ يتفرّقان.
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

$__pp = check_page_permissions($conn, 'Tickets/ticket_kpi.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحياتُ يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$rows = array(); $failed = false;
$sql = "SELECT t.`ticket_no`, t.`stage`, t.`priority`, t.`business_impact`, t.`call_date`, t.`resolution_due_at`, t.`close_date` FROM `tickets` t
         WHERE t.company_id = ? AND (1=1)
         ORDER BY t.id DESC LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else { $res = $st->get_result(); while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; } }
    $st->close();
}

$page_title = 'مؤشراتُ البلاغات';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-chart-simple';
$header_title_html = htmlspecialchars('مؤشراتُ البلاغات', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
echo ems_states_bundle('لا بلاغاتِ ضمن نطاقِك بعدُ', 'المؤشراتُ تُحسب على البلاغاتِ الحيّةِ لحظةَ تسجيلِها من شاشةِ البلاغات');
?>
  <style>
    .tkpi-alert-gap { margin: 10px 0; }
    .tkpi-stat-card { padding: 10px 14px; margin: 10px 0; border-inline-start: 4px solid var(--c-0d6efd); display: inline-block; }
    .tkpi-stat-label { font-size: .78rem; opacity: .75; }
    .tkpi-stat-value { font-size: 1.4rem; font-weight: 700; }
    .tkpi-w100 { width: 100%; }
    .tkpi-empty-cell { text-align: center; opacity: .7; }
    .tkpi-footnote { font-size: .8rem; margin-top: 8px; }
  </style>
  <?php if ($failed): ?>
  <div class="alert alert-danger tkpi-alert-gap">
    <strong>تعذّرت قراءةُ البيانات.</strong>
    فرقٌ بين «لا صفَّ» و«تعذّر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card tkpi-stat-card">
    <div class="tkpi-stat-label">صفوفٌ معروضة</div>
    <div class="tkpi-stat-value"><?php echo number_format(count($rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped tkpi-w100">
      <thead><tr>
        <th>رقمُ البلاغ</th>
        <th>المرحلة</th>
        <th>الأولوية</th>
        <th>الأثرُ التشغيلي</th>
        <th>تاريخُ البلاغ</th>
        <th>المهلة</th>
        <th>أُغلق</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="99" class="tkpi-empty-cell">لا صفَّ مسجَّلٌ بعد.</td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $x['ticket_no'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['stage'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['priority'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['business_impact'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['call_date'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['resolution_due_at'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['close_date'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted tkpi-footnote">
      قراءةٌ محضة — المؤشراتُ تُحسب على البلاغاتِ الحيّةِ لا على جدولٍ موازٍ — فرقمانِ لشيءٍ واحدٍ يتفرّقان. وأحدثُ 500 صفٍّ.
    </p>
  </div></div>
  <?php endif; ?>
</div>

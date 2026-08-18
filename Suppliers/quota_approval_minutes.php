<?php
/**
 * Suppliers/quota_approval_minutes.php — محاضرُ اعتمادِ وحداتِ المورد
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`substitute_coverages`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ التغطيةُ البديلةُ باعتمادين (CAP-01) — والطلبُ يُنشأ من Operations/swap_request.php والاعتمادُ من صندوقِ الاعتمادِ الجامع؛ وهذه الشاشةُ محضرُها.
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

$__pp = check_page_permissions($conn, 'Suppliers/quota_approval_minutes.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحياتُ يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$rows = array(); $failed = false;
$sql = "SELECT t.`cov_id`, t.`level`, t.`covered_seat_id`, t.`reason_code`, t.`valid_from`, t.`valid_to`, t.`estimated_hours`, t.`state`, t.`approvals_ref` FROM `substitute_coverages` t
         WHERE t.company_id = ? AND (1=1)
         ORDER BY t.cov_id DESC LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else { $res = $st->get_result(); while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; } }
    $st->close();
}

$page_title = 'محاضرُ اعتمادِ وحداتِ المورد';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-file-signature';
$header_title_html = htmlspecialchars('محاضرُ اعتمادِ وحداتِ المورد', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا محضرَ اعتمادِ تغطيةٍ بديلةٍ مسجَّلًا بعدُ', 'يُنشأ الطلبُ من شاشةِ طلبِ الإحلال ثم يُعتمد من صندوقِ الاعتمادِ الجامع');
?>
  <style>
    .sup-qam-alert      { margin: 10px 0; }
    .sup-qam-kpi        { padding: 10px 14px; margin: 10px 0; border-inline-start: 4px solid var(--c-0d6efd, #0d6efd); display: inline-block; }
    .sup-qam-kpi-label  { font-size: .78rem; opacity: .75; }
    .sup-qam-kpi-value  { font-size: 1.4rem; font-weight: 700; }
    .sup-qam-table      { width: 100%; }
    .sup-qam-empty-cell { text-align: center; opacity: .7; }
    .sup-qam-note       { font-size: .8rem; margin-top: 8px; }
  </style>
  <?php if ($failed): ?>
  <div class="alert alert-danger sup-qam-alert">
    <strong>تعذّرت قراءةُ البيانات.</strong>
    فرقٌ بين «لا صفَّ» و«تعذّر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card sup-qam-kpi">
    <div class="sup-qam-kpi-label">صفوفٌ معروضة</div>
    <div class="sup-qam-kpi-value"><?php echo number_format(count($rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped sup-qam-table">
      <thead><tr>
        <th>#</th>
        <th>الدرجة</th>
        <th>المقعدُ المغطّى</th>
        <th>السبب</th>
        <th>من</th>
        <th>إلى</th>
        <th>ساعاتٌ مقدَّرة</th>
        <th>الحال</th>
        <th>مرجعُ الاعتماد</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="99" class="sup-qam-empty-cell">لا صفَّ مسجَّلٌ بعد.</td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $x['cov_id'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['level'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['covered_seat_id'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['reason_code'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['valid_from'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['valid_to'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['estimated_hours'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['state'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['approvals_ref'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted sup-qam-note">
      قراءةٌ محضة — التغطيةُ البديلةُ باعتمادين (CAP-01) — والطلبُ يُنشأ من Operations/swap_request.php والاعتمادُ من صندوقِ الاعتمادِ الجامع؛ وهذه الشاشةُ محضرُها. وأحدثُ 500 صفٍّ.
    </p>
  </div></div>
  <?php endif; ?>
</div>

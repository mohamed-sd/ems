<?php
/**
 * main/soon.php — «قريبًا» (NAV-09 ⓪-4)
 * ───────────────────────────────────────────────────────────────────────────
 * وجهةُ كل شاشةٍ قانونيةٍ لم تُبنَ بعد. ليست صفحةً فارغة: تعرض لزائرها
 * **عقدَ الشاشة المنتظرة** من مصفوفة العرض — اسمَها ومالكَها ونطاقَ هذا
 * المستخدم وزاويتَه وما سيُسمح له وما سيُحجب — فيعرف ماذا سيجد حين تولد.
 * وتحترم الرباعية: من لا ظهورَ لدورِه فيها يُصرف عنها (حكم ٦).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$screen = preg_replace('/[^a-z0-9_.]/', '', strtolower($_GET['screen'] ?? ''));
$es = mysqli_real_escape_string($conn, $screen);

$meta = null;
$r = mysqli_query($conn, "SELECT canonical_file, title_ar, owner_dept, state, real_path
                          FROM nav09_file_map WHERE canonical_file = '$es'");
if ($r) { $meta = mysqli_fetch_assoc($r); }

/* بُنيت لاحقًا؟ الرابطُ القديم يصل — التحويلُ يحمي الجميع (SCR-01 §5) */
if ($meta && $meta['state'] !== 'soon' && $meta['real_path'] !== null) {
    header('Location: ' . (function_exists('ems_url') ? ems_url($meta['real_path']) : '/ems/' . $meta['real_path']));
    exit();
}

/* ظهوراتُ هذا المستخدم في الشاشة المنتظرة — من مصفوفة العرض بالرباعية */
$role = intval($_SESSION['user']['role'] ?? 0);
$exposures = array();
if ($meta) {
    $r = mysqli_query($conn, "SELECT dept, role_kind, scope_text, angle, allowed_text, blocked_text
                              FROM screen_view_rows
                              WHERE canonical_file = '$es' AND active = 1
                                AND (role_id = $role OR role_id IS NULL)");
    if ($r) { while ($x = mysqli_fetch_assoc($r)) { $exposures[] = $x; } }
}

$page_title = 'قريبًا';
/* inheader.php ليس زينةً: هو مَن يكتب <!DOCTYPE> و<meta viewport> ومكتبةَ
   الأيقونات. كانت هذه الشاشةُ وحدَها تستدعي insidebar بلا رأسٍ إطلاقًا،
   فيسقط المتصفّحُ في Quirks Mode ويقرأ العرضَ 981px على هاتفٍ عرضُه 375px —
   فلا تعمل @media (max-width:768px) أصلًا: لا لوحَ منزلقًا ولا زرَّ قائمةٍ،
   والشريطُ شريحةٌ بعرض 1px وطولِ 2122px بأيقوناتٍ فارغة. */
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Soon', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا عقدَ شاشةٍ مسجَّلًا لهذا الاسمِ القانوني', 'ارجعْ إلى القائمةِ واختر شاشةً من شريطِ التنقل');
?>
<style>
  .soon-wrap { max-width: 660px; margin: 40px auto; text-align: center; }
  .soon-icon { font-size: 3em; color: var(--c-f4c430, #f4c430); }
  .soon-title { margin: 12px 0 4px; }
  .soon-table { max-width: 560px; margin: 18px auto; text-align: right; }
  .soon-th-name { width: 170px; }
  .soon-allowed { color: var(--c-198754); }
  .soon-blocked { color: var(--c-dc3545); }
</style>

  <?php if (!$meta): ?>
    <div class="alert alert-warning">شاشةٌ غيرُ معروفةٍ في القاموس القانوني — <a href="dashboard.php">العودة</a></div>
  <?php else: ?>
  <div class="soon-wrap">
    <div class="soon-icon"><i class="fa fa-hard-hat"></i></div>
    <h3 class="soon-title"><?= htmlspecialchars($meta['title_ar'], ENT_QUOTES, 'UTF-8') ?></h3>
    <p class="text-muted">هذه الشاشةُ في خطة البناء — وموضعُها في النظام محجوزٌ ومعرَّفٌ بعقدها أدناه.</p>
    <table class="table table-sm soon-table" data-no-dt>
      <tr><th class="soon-th-name">الاسمُ القانوني</th><td><code><?= htmlspecialchars($meta['canonical_file'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
      <tr><th>الإدارةُ المالكة</th><td><?= htmlspecialchars($meta['owner_dept'], ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php foreach (array_slice($exposures, 0, 1) as $e2): ?>
      <tr><th>نطاقُك فيها</th><td><?= htmlspecialchars($e2['scope_text'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>زاويتُك</th><td><?= htmlspecialchars($e2['angle'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>ما سيُسمح لك</th><td class="soon-allowed"><?= htmlspecialchars($e2['allowed_text'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>ما سيُحجب</th><td class="soon-blocked"><?= htmlspecialchars($e2['blocked_text'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php endforeach; ?>
    </table>
    <a class="action-btn" href="javascript:history.back()"><i class="fa fa-arrow-right"></i> عودة</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>

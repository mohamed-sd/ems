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
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <?php if (!$meta): ?>
    <div class="alert alert-warning">شاشةٌ غيرُ معروفةٍ في القاموس القانوني — <a href="dashboard.php">العودة</a></div>
  <?php else: ?>
  <div style="max-width:660px;margin:40px auto;text-align:center">
    <div style="font-size:3em;color:#f4c430"><i class="fa fa-hard-hat"></i></div>
    <h3 style="margin:12px 0 4px"><?= htmlspecialchars($meta['title_ar'], ENT_QUOTES, 'UTF-8') ?></h3>
    <p class="text-muted">هذه الشاشةُ في خطة البناء — وموضعُها في النظام محجوزٌ ومعرَّفٌ بعقدها أدناه.</p>
    <table class="table table-sm" data-no-dt style="max-width:560px;margin:18px auto;text-align:right">
      <tr><th style="width:170px">الاسمُ القانوني</th><td><code><?= htmlspecialchars($meta['canonical_file'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
      <tr><th>الإدارةُ المالكة</th><td><?= htmlspecialchars($meta['owner_dept'], ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php foreach (array_slice($exposures, 0, 1) as $e2): ?>
      <tr><th>نطاقُك فيها</th><td><?= htmlspecialchars($e2['scope_text'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>زاويتُك</th><td><?= htmlspecialchars($e2['angle'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>ما سيُسمح لك</th><td style="color:#198754"><?= htmlspecialchars($e2['allowed_text'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>ما سيُحجب</th><td style="color:#dc3545"><?= htmlspecialchars($e2['blocked_text'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php endforeach; ?>
    </table>
    <a class="action-btn" href="javascript:history.back()"><i class="fa fa-arrow-right"></i> عودة</a>
  </div>
  <?php endif; ?>
</div>

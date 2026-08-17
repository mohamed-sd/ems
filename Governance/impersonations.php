<?php
/**
 * Governance/impersonations.php — جلساتُ النيابةِ الموسومة (GOV-AUTH-01 §6-2)
 * ───────────────────────────────────────────────────────────────────────────
 * الشاشةُ الثالثة: «والمراجعُ يراها لحظةَ جريانِها لا بعدَ شهر» — الجاريةُ من
 * v_active_impersonations والتاريخُ كاملًا تحتَها. عرضٌ رقابيٌّ لا يكتب —
 * فتحُ الجلساتِ وإغلاقُها من مسارِ النيابةِ الذي يُفعَّل مع التبديل.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/ux_components.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$SCREEN         = 'Governance/impersonations.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(405); exit('شاشةُ رقابةٍ لا تكتب'); }

// ═══ ④ العرض ═══
$g = $conn->query(
    "SELECT COUNT(*) total,
            SUM(closed_at IS NULL) open_n,
            SUM(closed_at IS NOT NULL AND notified_at IS NULL) unnotified
       FROM impersonation_sessions")->fetch_assoc();
$rows = $conn->query(
    "SELECT i.imp_id, a.username actor, t.username target, i.reason,
            i.opened_at, i.closed_at, i.valid_to, i.notified_at
       FROM impersonation_sessions i
       JOIN users a ON a.id = i.actor_user
       JOIN users t ON t.id = i.target_user
      ORDER BY (i.closed_at IS NULL) DESC, i.opened_at DESC LIMIT 500");

$PAGE_TITLE = 'جلسات النيابة';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-user-shield';
  $header_desc = 'لا دخولَ بحسابِ الغير: جلسةٌ موسومةٌ بالفاعلِ الحقيقيِّ والمُنابِ عنه والسببِ والمدةِ — وكلُّ فعلٍ فيها مزدوجُ النسبةِ في دفترِ الأفعال. والمراجعُ يراها لحظةَ جريانِها.';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <div class="row">
    <div class="col"><div class="kpi-card"><div>الجلساتُ كلُّها</div><strong><?php echo (int) $g['total']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>جاريةٌ الآن</div><strong><?php echo (int) $g['open_n']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>مغلقةٌ بلا إخطار</div><strong><?php echo (int) $g['unnotified']; ?></strong></div></div>
  </div>

  <?php echo ems_states_bundle('لا جلساتِ نيابةٍ مسجَّلة', 'مسارُ النيابةِ يُفعَّل مع التبديلِ — وستظهر هنا لحظةَ فتحِها'); ?>

  <?php if ($rows !== false && $rows->num_rows > 0): ?>
  <div class="table-responsive">
    <table class="table" id="impersonationsTable">
      <thead><tr>
        <th>الفاعلُ الحقيقي</th><th>موضعَ من</th><th>السبب</th>
        <th>فُتحت</th><th>تنتهي</th><th>الحالة</th><th>الإخطار</th>
      </tr></thead>
      <tbody>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['actor'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r['target'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $r['opened_at'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $r['valid_to'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><span class="status-badge <?php echo $r['closed_at'] === null ? 'status-pending' : 'status-active'; ?>">
            <?php echo $r['closed_at'] === null ? 'جاريةٌ الآن' : 'مغلقة'; ?></span></td>
          <td><?php echo $r['notified_at'] !== null ? 'أُخطر صاحبُ الموضع' : '—'; ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <?php echo ems_state('empty', 'لا جلساتِ نيابةٍ مسجَّلة', 'مسارُ النيابةِ يُفعَّل مع التبديلِ — وستظهر هنا لحظةَ فتحِها'); ?>
  <?php endif; ?>
</div>

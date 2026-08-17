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
/* ═══ ③-ب مسارُ النيابةِ — فُعِّل مع التبديلِ بقرارِ المالك (2026-08-17):
   الفتحُ بسببٍ ومدةٍ (سقفُها 24 ساعةً قياسَ أسرتِها) والإغلاقُ بيدِ فاعلِها.
   حدودُ السلطةِ في ImpersonationService (الخطُّ الإداريُّ A3) وقيودُ القاعدةِ
   ترفض الذاتَ والرقابيّين والسببَ الفارغ. ═══ */
require_once __DIR__ . '/../app/Services/Gov/ImpersonationService.php';
require_once __DIR__ . '/../includes/audit_trail.php';
$FLASH = array('ok' => null, 'msg' => '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = isset($_POST['imp_action']) ? (string) $_POST['imp_action'] : '';
    if ($act === 'open') {
        $res = \App\Services\Gov\ImpersonationService::open(
            $conn, $_SESSION['user'],
            (int) ($_POST['target_user'] ?? 0),
            (string) ($_POST['reason'] ?? ''),
            (int) ($_POST['hours'] ?? 4));
        if ($res['ok']) {
            ems_audit_change($conn, 'governance', $SCREEN, 'imp.open', (int) $res['imp_id'],
                array(), array('target_user' => (int) $_POST['target_user'], 'reason' => (string) $_POST['reason']));
        }
        $_SESSION['imp_flash'] = array('ok' => $res['ok'],
            'msg' => $res['ok'] ? 'فُتحت الجلسةُ وأُخطر صاحبُ الموضعِ فورًا' : $res['reason']);
    } elseif ($act === 'close') {
        // التدوينُ قبلَ الإغلاقِ — فالنسبةُ المزدوجةُ تُختم ما دامت الجلسةُ نشطةً لحظةَ الكتابة
        ems_audit_change($conn, 'governance', $SCREEN, 'imp.close', 0, array(), array('closed' => 1));
        \App\Services\Gov\ImpersonationService::close($conn, (int) $_SESSION['user']['id']);
        $_SESSION['imp_flash'] = array('ok' => true, 'msg' => 'أُغلقت جلستُك الجارية');
    }
    // PRG — يقطع إعادةَ الإرسالِ وأيَّ معالجةِ عرضٍ لاحقةٍ للطلبِ الكاتب
    header('Location: impersonations.php');
    exit();
}
if (isset($_SESSION['imp_flash'])) { $FLASH = $_SESSION['imp_flash']; unset($_SESSION['imp_flash']); }

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

  <?php echo ems_states_bundle('لا جلساتِ نيابةٍ مسجَّلة', 'تُفتح الجلسةُ من النموذجِ أدناه بسببٍ ومدةٍ — وتظهر هنا لحظةَ فتحِها'); ?>

  <?php if ($FLASH['ok'] !== null): ?>
    <div class="<?php echo $FLASH['ok'] ? 'ems-state-readonly' : 'ems-state-noperm'; ?> ems-state" role="status">
      <?php echo htmlspecialchars($FLASH['msg'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php $__mine = \App\Services\Gov\ImpersonationService::active(); ?>
  <div class="card"><div class="card-body">
    <?php if ($__mine !== null): ?>
      <form method="post" class="imp-open-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="imp_action" value="close">
        <span>جلستُك الجاريةُ موضعَ <b><?php echo htmlspecialchars($__mine['target_name'], ENT_QUOTES, 'UTF-8'); ?></b>
              — تنتهي في <?php echo htmlspecialchars($__mine['valid_to'], ENT_QUOTES, 'UTF-8'); ?></span>
        <button type="submit" class="btn btn-secondary btn-sm">إنهاءُ الجلسة</button>
      </form>
    <?php else: ?>
      <form method="post" class="imp-open-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="imp_action" value="open">
        <label for="impTarget">فتحُ جلسةِ نيابةٍ موضعَ</label>
        <select id="impTarget" name="target_user" required aria-label="صاحبُ الموضعِ المُنابُ عنه">
          <option value="">— اختر الموظف —</option>
          <?php
          $__us = $conn->query("SELECT id, username FROM users
                                 WHERE status = 1 AND id <> " . (int) $_SESSION['user']['id'] . "
                                   AND company_id = " . (int) $company_id . " ORDER BY username LIMIT 400");
          while ($__u = $__us->fetch_assoc()) {
              echo '<option value="' . (int) $__u['id'] . '">' . htmlspecialchars($__u['username'], ENT_QUOTES, 'UTF-8') . '</option>';
          }
          ?>
        </select>
        <input type="text" id="impReason" name="reason" required maxlength="255"
               aria-label="سببُ الجلسةِ — إلزامي" placeholder="السبب — لا جلسةَ بسببٍ فارغ">
        <select id="impHours" name="hours" aria-label="مدةُ الجلسةِ بالساعات">
          <option value="1">ساعة</option><option value="4" selected>4 ساعات</option>
          <option value="8">8 ساعات</option><option value="24">24 ساعة</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">فتحُ الجلسة</button>
      </form>
      <div class="imp-open-note">لا ترفع الجلسةُ صلاحيتَك — تعمل بسلطتِك على خطِّك الإداريِّ وحدَه، وكلُّ فعلٍ مزدوجُ النسبةِ ويُخطَر صاحبُ الموضعِ فورًا. ولا نيابةَ على الرقابيّين.</div>
    <?php endif; ?>
  </div></div>
  <style>
    .imp-open-form { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
    .imp-open-form input[type="text"] { min-width: 260px; }
    .imp-open-note { margin-top: var(--space-2); color: var(--gray-500); font-size: var(--text-caption); }
  </style>

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

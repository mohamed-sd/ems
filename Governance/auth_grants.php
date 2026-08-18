<?php
/**
 * Governance/auth_grants.php — منحُ الصلاحيةِ الفعليُّ (GOV-AUTH-01 A1 · §8-3 ⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * الشاشةُ الثانية: المنحُ بمصادرِه الأربعةِ من v_effective_authority — والفعلُ
 * الوحيدُ هنا سحبُ منحٍ (بيدِ الحوكمةِ حصرًا). الإصدارُ الجديدُ للمنحِ المؤقتِ
 * يمرُّ بجداولِه (تفويضٌ · رفعٌ) لا من هنا — فلا بابَ خلفيًّا للسلطة.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/ux_components.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$SCREEN         = 'Governance/auth_grants.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}

// ═══ ④ حارسُ الفعل + ⑤ رمزُ الحماية ═══
$__canRevoke = $is_super_admin || !empty($__pp['can_edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$__canRevoke) { http_response_code(403); exit('السحبُ بيدِ الحوكمةِ حصرًا — اطلبِ المنحة'); }
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('رمزُ الحمايةِ غيرُ صالح — أعدْ تحميلَ الصفحة');
    }
}

// ═══ ⑥ معالجُ POST — سحبُ منحٍ واحدٍ مسبَّبًا ═══
/* AC-F2: حارسُ الكتابةِ المركزيُّ **قبلَ** أولِ عبارةِ كتابة — يجمع الجلسةَ
   والرمزَ والصلاحيةَ ويسجّل المنع، ويخرج بـ403 فلا يبلغ التنفيذُ الكتابةَ. */
ems_require_action($conn, $SCREEN, 'edit', array('deny_msg' => 'السحبُ بيدِ الحوكمةِ حصرًا — اطلبِ المنحة'));
$flash = null; $flashKind = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_grant') {
    $gid = (int) ($_POST['grant_id'] ?? 0);
    $why = trim((string) ($_POST['revoke_reason'] ?? ''));
    if ($gid > 0 && $why !== '') {
        $st = $conn->prepare("UPDATE gov_authority_grants
                                 SET revoked_at = NOW(), reason = CONCAT(reason, ' | سُحب: ', ?)
                               WHERE grant_id = ? AND revoked_at IS NULL");
        $st->bind_param('si', $why, $gid);
        if ($st->execute() && $st->affected_rows > 0) { $flash = 'سُحب المنحُ وسُجِّل سببُه'; $flashKind = 'success'; }
        else { $flash = 'لم يتغير شيء — المنحُ مسحوبٌ سلفًا أو غيرُ موجود'; $flashKind = 'warning'; }
        $st->close();
    } else {
        $flash = 'السحبُ يلزمه المنحُ وسببٌ غيرُ فارغ'; $flashKind = 'danger';
    }
}

// ═══ ⑦ العرض ═══
$g = $conn->query(
    "SELECT COUNT(*) total,
            SUM(source='profile') by_profile,
            SUM(source IN ('delegation','elevation')) temp_n,
            SUM(valid_to IS NOT NULL AND valid_to < NOW() AND revoked_at IS NULL) stale_n
       FROM gov_authority_grants")->fetch_assoc();
$rows = $conn->query(
    "SELECT g.grant_id, u.username, p.profile_code, p.title_ar, g.source,
            g.valid_from, g.valid_to, g.revoked_at, g.reason
       FROM gov_authority_grants g
       JOIN users u ON u.id = g.user_id
       JOIN gov_role_profiles p ON p.profile_id = g.profile_id
      ORDER BY (g.revoked_at IS NULL) DESC, g.created_at DESC LIMIT 500");
$SRC_AR = array('profile' => 'قالبُ المسمَّى', 'escalation' => 'تصعيدٌ رأسيّ',
                'delegation' => 'تفويضٌ مؤقَّت', 'elevation' => 'رفعٌ استثنائيّ');

$PAGE_TITLE = 'منح الصلاحية';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-key';
  $header_desc = 'المنحُ الفعليُّ بمصادرِه الأربعةِ — والصلاحيةُ الفعليةُ تُحسب في v_effective_authority وحدَه. الفعلُ الوحيدُ هنا سحبٌ مسبَّبٌ بيدِ الحوكمة.';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php if ($flash !== null): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashKind, ENT_QUOTES, 'UTF-8'); ?>" role="status">
      <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col"><div class="kpi-card"><div>المنحُ الكلي</div><strong><?php echo (int) $g['total']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>بالقالب</div><strong><?php echo (int) $g['by_profile']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>مؤقَّت</div><strong><?php echo (int) $g['temp_n']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>منتهٍ ينتظر الكنس</div><strong><?php echo (int) $g['stale_n']; ?></strong></div></div>
  </div>

  <?php echo ems_states_bundle('لا منحَ مسجَّلًا', 'الإلحاقُ الآليُّ يجري بهجرةِ GOV-AUTH-01'); ?>

  <?php if ($rows !== false && $rows->num_rows > 0): ?>
  <div class="table-responsive">
    <table class="table" id="authGrantsTable">
      <thead><tr>
        <th>المستخدم</th><th>القالب</th><th>المصدر</th><th>من</th><th>إلى</th><th>الحالة</th>
        <?php if ($__canRevoke): ?><th>سحبٌ مسبَّب</th><?php endif; ?>
      </tr></thead>
      <tbody>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><code><?php echo htmlspecialchars($r['profile_code'], ENT_QUOTES, 'UTF-8'); ?></code>
              <?php echo htmlspecialchars($r['title_ar'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($SRC_AR[$r['source']] ?? $r['source'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $r['valid_from'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo $r['valid_to'] === null ? 'دائمٌ بالقالب' : htmlspecialchars($r['valid_to'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><span class="status-badge <?php echo $r['revoked_at'] !== null ? 'status-stopped' : 'status-active'; ?>">
            <?php echo $r['revoked_at'] !== null ? 'مسحوب' : 'ساري'; ?></span></td>
          <?php if ($__canRevoke): ?>
          <td>
            <?php if ($r['revoked_at'] === null): ?>
            <form method="post" class="ems-inline-form">
              <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
              <input type="hidden" name="action" value="revoke_grant">
              <input type="hidden" name="grant_id" value="<?php echo (int) $r['grant_id']; ?>">
              <label class="ems-visually-hidden" for="rv<?php echo (int) $r['grant_id']; ?>">سببُ السحب</label>
              <input type="text" name="revoke_reason" id="rv<?php echo (int) $r['grant_id']; ?>"
                     placeholder="سببُ السحب — إلزاميّ" maxlength="120" required>
              <button type="submit" class="btn btn-sm btn-danger">اسحب</button>
            </form>
            <?php else: ?>—<?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <?php echo ems_state('empty', 'لا منحَ مسجَّلًا', 'الإلحاقُ الآليُّ يجري بهجرةِ GOV-AUTH-01'); ?>
  <?php endif; ?>
</div>

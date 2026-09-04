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
/* AC-F2 · AC-P1A: الحارسُ المركزيُّ **أولَ ما يواجه الطلبَ الكاتب** — يجمع
   الجلسةَ والرمزَ والصلاحيةَ، ويردُّ 403 **برمزِه الحوكميِّ** فيراه السجلُّ
   والفاحصُ معًا.
   ◆ وموضعُه قبلَ حارسِ الشاشةِ الخاصِّ مقصود: كان بعدَه فيسبقه المنعُ المحليُّ
     برسالةٍ **بلا رمز** — فيُمنع الطلبُ فعلًا ويُعلن المسبارُ «لم يُمنع»،
     ومنعٌ لا يراه السجلُّ منعٌ لا يُحتسب. (قِيس: 77 بايتَ ردٍّ بلا رمز.) */
ems_require_action($conn, $SCREEN, 'write', array('deny_msg' => 'السحب بيد الحوكمة حصرا — اطلب المنحة'));

$__canRevoke = $is_super_admin || !empty($__pp['can_edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$__canRevoke) { http_response_code(403); exit('GOV-PERM-403-WRITE — السحب بيد الحوكمة حصرا — اطلب المنحة'); }
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('رمز الحماية غير صالح — أعد تحميل الصفحة');
    }
}

// ═══ ⑥ معالجُ POST — سحبُ منحٍ واحدٍ مسبَّبًا ═══
$flash = null; $flashKind = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_grant') {
    $gid = (int) ($_POST['grant_id'] ?? 0);
    $why = trim((string) ($_POST['revoke_reason'] ?? ''));
    if ($gid > 0 && $why !== '') {
        /* ⛔ **والشاشةُ عميلٌ لا بابٌ خامس** (PERM-01-DEC §0-3 وقبولُ ق-١:
             «صفرُ شاشاتٍ أخرى تكتب في سجلِّ المنح»). كانت هنا `UPDATE` مباشرةٌ
             تليها كتابةُ أثرٍ **خارجَ المعاملة** — فتعذُّرُ الأثرِ يترك سحبًا
             بلا سجلّ. صارت نداءً واحدًا للمنفذِ المحروس: يفحص التجميدَ، ويكتب
             بالبوّابة، ويجعل الأثرَ شرطَ إتمامٍ في معاملةٍ واحدة. */
        require_once __DIR__ . '/../app/Services/Security/PolicyWriteService.php';
        $__res = \App\Services\Security\PolicyWriteService::revokeGrant(
            $conn, $gid, $why, (int) ($_SESSION['user']['id'] ?? 0));
        $flash = $__res['msg'];
        $flashKind = $__res['ok'] ? 'success' : ($__res['code'] === 'ALREADY_REVOKED' ? 'warning' : 'danger');
    } else {
        $flash = 'السحب يلزمه المنح وسبب غير فارغ'; $flashKind = 'danger';
    }
}

/* ═══ ⑥-ب معالجُ الإسناد — ق-١ من PERM-01-DEC ══════════════════════════════
   ◆ **بيتٌ واحدٌ لا شاشةٌ ثانية**: سجلُّ المنحِ هو موضعُ الإسنادِ طبعًا — وشاشةٌ
     جديدةٌ تعني بابًا يُسجَّل ويُحرَس ويُربَط من جديدٍ بلا حاجة.
   ⛔ **والإسنادُ للدورِ 15 وحدَه** بنصِّ الأمر: «مالك الإسناد: الدور 15 وحده».
     والسوبرُ ليس مُسنِدًا — فصفةُ الإدارةِ التقنيّةِ ليست ولايةً على السياسة.
   ⛔ **ولا تكتب هذه الشاشةُ في سجلِّ المنحِ بحرف**: تنادي المنفذَ المحروسَ
     وتعرض جوابَه — «إن بنيتَ بابًا خامسًا تُرفض الشاشةُ أوّلًا». */
$__isAssigner = (strval($_SESSION['user']['role'] ?? '') === '15');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_profile') {
    if (!$__isAssigner) {
        $flash = 'الاسناد بيد ادارة الصلاحيات (الدور 15) وحدها'; $flashKind = 'danger';
    } else {
        require_once __DIR__ . '/../app/Services/Security/PolicyWriteService.php';
        $__res = \App\Services\Security\PolicyWriteService::assignProfile(
            $conn,
            (int) ($_POST['assign_user'] ?? 0),
            (int) ($_POST['assign_profile'] ?? 0),
            trim((string) ($_POST['assign_reason'] ?? '')),
            $uid
        );
        $flash = $__res['msg'];
        $flashKind = $__res['ok'] ? 'success' : ($__res['code'] === 'FROZEN' ? 'warning' : 'danger');
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
$SRC_AR = array('profile' => 'قالب المسمى', 'escalation' => 'تصعيد رأسي',
                'delegation' => 'تفويض مؤقت', 'elevation' => 'رفع استثنائي');

$PAGE_TITLE = 'منح الصلاحية';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-key';
  $header_desc = 'المنح الفعلي بمصادره الأربعة — والصلاحية الفعلية تحسب في v_effective_authority وحده. الفعل الوحيد هنا سحب مسبب بيد الحوكمة.';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php if ($flash !== null): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashKind, ENT_QUOTES, 'UTF-8'); ?>" role="status">
      <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <?php
  /* ── إسنادُ قالبٍ لموظّف (ق-١) — للدورِ 15 وحدَه ─────────────────────────
     ◆ **ويُعرض حالُ التجميدِ قبلَ المحاولةِ لا بعدَها**: نموذجٌ يُملأ ثمَّ يُردُّ
       يضيّع وقتَ المستخدمِ ويبدو عطبًا؛ فيُقال له ابتداءً إنَّ البابَ مغلقٌ
       ومن يفتحه. */
  if ($__isAssigner):
      require_once __DIR__ . '/../app/Services/Security/PolicyWriteService.php';
      $__frozen = \App\Services\Security\PolicyWriteService::isFrozen(
          \App\Services\Security\PolicyWriteService::FREEZE_GRANTS);
      /* ◆ **والشاشةُ تعرض ولا تسأل القاعدة**: القراءةُ في المنفذِ بالبوّابةِ —
           فاستعلامٌ خامٌّ في مسارِ إدارةٍ يرفع سجلَّ الدَّينِ `RP-04`. */
      $__freeUsers = \App\Services\Security\PolicyWriteService::assignableUsers();
      $__profiles  = \App\Services\Security\PolicyWriteService::activeProfiles();
  ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-user-plus"></i></span>
      اسناد قالب لموظف
    </div>
    <div class="filter-body">
      <?php if ($__frozen): ?>
        <div class="alert alert-warning" role="status">
          تجميد المنح نافذ، فلا يصدر اسناد جديد حتى يرفعه المالك. والنموذج معطل عمدا.
        </div>
      <?php endif; ?>
      <form method="post" class="ems-form">
        <input type="hidden" name="csrf_token"
               value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="assign_profile">
        <div class="form-group px-w-320">
          <label for="assign_user">الموظف (بلا قالب نافذ)</label>
          <select name="assign_user" id="assign_user" class="form-control" required
                  <?php echo $__frozen ? 'disabled' : ''; ?>>
            <option value="">اختر</option>
            <?php foreach ($__freeUsers as $__u): ?>
              <option value="<?php echo (int) $__u['id']; ?>">
                <?php echo htmlspecialchars($__u['name'] . ' (دور ' . $__u['role'] . ')',
                    ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="assign_profile">القالب النافذ</label>
          <select name="assign_profile" id="assign_profile" class="form-control" required
                  <?php echo $__frozen ? 'disabled' : ''; ?>>
            <option value="">اختر</option>
            <?php foreach ($__profiles as $__p): ?>
              <option value="<?php echo (int) $__p['profile_id']; ?>">
                <?php echo htmlspecialchars($__p['profile_code']
                    . ($__p['title_ar'] ? ': ' . $__p['title_ar'] : ''), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="assign_reason">السبب (مطلوب)</label>
          <input type="text" name="assign_reason" id="assign_reason" class="form-control"
                 maxlength="255" required <?php echo $__frozen ? 'disabled' : ''; ?>
                 placeholder="مثال: تعيين جديد بادارة المشتريات">
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-primary" <?php echo $__frozen ? 'disabled' : ''; ?>>
            اسند القالب
          </button>
        </div>
      </form>
      <p class="text-muted">
        قالب واحد لكل موظف. الاسناد يمر ببوابة التجميد ويكتب اثرا يسمي من اسند ولمن واي قالب ولماذا.
        وتحرير بنود القالب ليس من هنا.
      </p>
    </div>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col"><div class="kpi-card"><div>المنح الكلي</div><strong><?php echo (int) $g['total']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>بالقالب</div><strong><?php echo (int) $g['by_profile']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>مؤقت</div><strong><?php echo (int) $g['temp_n']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>منته ينتظر الكنس</div><strong><?php echo (int) $g['stale_n']; ?></strong></div></div>
  </div>

  <?php echo ems_states_bundle('لا منح مسجلا', 'الإلحاق الآلي يجري بهجرة GOV-AUTH-01'); ?>

  <?php if ($rows !== false && $rows->num_rows > 0): ?>
  <div class="table-responsive">
    <table class="table" id="authGrantsTable">
      <thead><tr>
        <th>المستخدم</th><th>القالب</th><th>المصدر</th><th>من</th><th>إلى</th><th>الحالة</th>
        <?php if ($__canRevoke): ?><th>سحب مسبب</th><?php endif; ?>
      </tr></thead>
      <tbody>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><code><?php echo htmlspecialchars($r['profile_code'], ENT_QUOTES, 'UTF-8'); ?></code>
              <?php echo htmlspecialchars($r['title_ar'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($SRC_AR[$r['source']] ?? $r['source'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $r['valid_from'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo $r['valid_to'] === null ? 'دائم بالقالب' : htmlspecialchars($r['valid_to'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><span class="status-badge <?php echo $r['revoked_at'] !== null ? 'status-stopped' : 'status-active'; ?>">
            <?php echo $r['revoked_at'] !== null ? 'مسحوب' : 'ساري'; ?></span></td>
          <?php if ($__canRevoke): ?>
          <td>
            <?php if ($r['revoked_at'] === null): ?>
            <form method="post" class="ems-inline-form">
              <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
              <input type="hidden" name="action" value="revoke_grant">
              <input type="hidden" name="grant_id" value="<?php echo (int) $r['grant_id']; ?>">
              <label class="ems-visually-hidden" for="rv<?php echo (int) $r['grant_id']; ?>">سبب السحب</label>
              <input type="text" name="revoke_reason" id="rv<?php echo (int) $r['grant_id']; ?>"
                     placeholder="سبب السحب — إلزامي" maxlength="120" required>
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
    <?php echo ems_state('empty', 'لا منح مسجلا', 'الإلحاق الآلي يجري بهجرة GOV-AUTH-01'); ?>
  <?php endif; ?>
</div>

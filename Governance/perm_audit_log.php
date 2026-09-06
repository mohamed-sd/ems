<?php
/**
 * Governance/perm_audit_log.php — محاسبةُ الصلاحيات (PERM-02 ②)
 * ───────────────────────────────────────────────────────────────────────────
 * PERM-01 §7-⑤ نصًّا: «سؤالُ من فتح هذه الشاشة لهذا الدور بلا جوابٍ اليوم».
 * وقد صار `perm_change_log` يُكتب عند كلِّ فعلٍ في `PolicyWriteService`، وبقي
 * **بلا قارئٍ في الواجهة** — وسجلٌّ لا يُقرأ لا يحاسب أحدًا.
 *
 * ◆ **والشاشةُ تعرض ولا تسأل القاعدة**: القراءةُ في الخدمةِ بالبوّابة، فسجلُّ
 *   الأثرِ سجلُّ مستأجِرٍ واستعلامٌ خامٌّ عليه هنا يرفع سقّاطةَ GAP-29.
 * ◆ **وثلاثةُ أسطحٍ للمحاسبةِ لا واحد**: ما تغيّر (سجلُّ الأثر) · وما فُتح
 *   اضطرارًا (الاستثناءاتُ الحيّة) · ومن يحمل ماذا الآن (المنحُ النافذة).
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/ux_components.php';
require_once __DIR__ . '/../app/Services/Security/PolicyWriteService.php';

use App\Services\Security\PolicyWriteService as PW;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$SCREEN         = 'Governance/perm_audit_log.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}
/* ⛔ **شاشةُ قراءةٍ محضة**: المحاسبةُ تُقرأ ولا تُحرَّر، فسجلٌّ يُعدَّل لا يحاسب. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    exit('شاشة محاسبة لا تكتب. سجل الاثر يقرأ ولا يعدل.');
}

// ═══ ④ القراءة ═══
$F = array(
    'layer'  => trim((string) ($_GET['layer'] ?? '')),
    'verb'   => trim((string) ($_GET['verb'] ?? '')),
    'actor'  => (int) ($_GET['actor'] ?? 0),
    'limit'  => 300,
);
$ROWS  = PW::changeLog($F);
$VOCAB = PW::changeLogVocab();
$EXC   = PW::liveExceptions();

$ids = array();
foreach ($ROWS as $r) { $ids[] = (int) $r['actor_user_id']; }
foreach ($ROWS as $r) { if ((string) $r['subject_kind'] === 'user') { $ids[] = (int) $r['subject_id']; } }
foreach ($EXC as $e)  { $ids[] = (int) $e['person_id']; }
$NAMES = PW::nameMap($ids);

$ACTORS = array();
foreach ($ROWS as $r) {
    $a = (int) $r['actor_user_id'];
    if ($a > 0) { $ACTORS[$a] = isset($NAMES[$a]) ? $NAMES[$a] : ('#' . $a); }
}
asort($ACTORS);

/* المنحُ النافذةُ الآن — سجلٌّ عامٌّ فيُقرأ مباشرةً بلا سقّاطة. */
$LIVE = $conn->query(
    "SELECT g.grant_id, g.user_id, u.name AS uname, u.role,
            p.profile_code, p.title_ar, g.valid_from, g.issued_by, g.reason
       FROM gov_authority_grants g
       JOIN users u ON u.id = g.user_id
       JOIN gov_role_profiles p ON p.profile_id = g.profile_id
      WHERE g.revoked_at IS NULL
      ORDER BY p.profile_code, u.name");

$LAYER_AR = array(
    'profile' => 'قالب', 'profile_item' => 'بند قالب', 'grant' => 'منحة',
    'exception' => 'فتح اضطراري', 'freeze' => 'بوابة سياسة',
);
$VERB_AR = array(
    'create' => 'تأليف', 'update' => 'تعديل', 'insert' => 'اضافة', 'revoke' => 'سحب',
    'approve' => 'اعتماد', 'activate' => 'تفعيل', 'retire' => 'تقاعد', 'clone' => 'نسخ',
    'open' => 'فتح', 'close' => 'اغلاق', 'delete' => 'حذف',
);

$PAGE_TITLE = 'محاسبة الصلاحيات';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$ar = function ($map, $k) { return isset($map[$k]) ? $map[$k] : $k; };
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-clipboard-check';
  $header_desc = 'من غير ماذا لمن ومتى ولماذا. ثلاثة اسطح: سجل الاثر، والفتح الاضطراري الحي، والمنح النافذة الان.';
  $header_back = array('href' => 'auth_grants.php', 'icon' => 'fas fa-arrow-right', 'label' => 'شاشة المنح');
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php if (PW::$lastReadError !== ''): ?>
    <div class="alert alert-danger" role="alert">
      تعذر قراءة سجل الاثر فلا تصح المحاسبة الان. السبب: <?php echo $h(PW::$lastReadError); ?>
    </div>
  <?php endif; ?>

  <?php /* ── ① سجلُّ الأثر ────────────────────────────────────────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-timeline"></i></span>
      سجل تغيير الصلاحيات (<?php echo count($ROWS); ?>)
    </div>
    <div class="filter-body">
      <form method="get" class="ems-form pe-row-mid-end">
        <div class="form-group px-w-320">
          <label for="f_layer">الطبقة</label>
          <select id="f_layer" name="layer" class="form-control">
            <option value="">الكل</option>
            <?php foreach ($VOCAB['layer'] as $v): ?>
              <option value="<?php echo $h($v); ?>" <?php echo $v === $F['layer'] ? 'selected' : ''; ?>>
                <?php echo $h($ar($LAYER_AR, $v)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="f_verb">الفعل</label>
          <select id="f_verb" name="verb" class="form-control">
            <option value="">الكل</option>
            <?php foreach ($VOCAB['verb'] as $v): ?>
              <option value="<?php echo $h($v); ?>" <?php echo $v === $F['verb'] ? 'selected' : ''; ?>>
                <?php echo $h($ar($VERB_AR, $v)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="f_actor">الفاعل</label>
          <select id="f_actor" name="actor" class="form-control">
            <option value="0">الكل</option>
            <?php foreach ($ACTORS as $aid => $an): ?>
              <option value="<?php echo (int) $aid; ?>" <?php echo (int) $aid === $F['actor'] ? 'selected' : ''; ?>>
                <?php echo $h($an); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> رشح</button>
        <a class="btn btn-secondary" href="perm_audit_log.php">الغ الترشيح</a>
      </form>

      <?php if (!$ROWS): ?>
        <?php echo ems_state('empty', 'لا سطر في سجل الاثر',
            'السجل يمتلئ عند اول فعل من شاشة القوالب او شاشة المنح'); ?>
      <?php else: ?>
      <div class="table-container">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>متى</th><th>الفاعل</th><th>الطبقة</th><th>الفعل</th>
              <th>الموضوع</th><th>قبل</th><th>بعد</th><th>السبب</th><th>المصدر</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ROWS as $r):
              $sk = (string) $r['subject_kind'];
              $sid = (int) $r['subject_id'];
              $subj = $sk === 'user'
                  ? ('موظف: ' . (isset($NAMES[$sid]) ? $NAMES[$sid] : ('#' . $sid)))
                  : ($sk === 'profile' ? ('قالب #' . $sid)
                     : ($sk === 'freeze' ? ('بوابة ' . (string) $r['screen_code']) : ($sk . ' #' . $sid)));
            ?>
              <tr>
                <td dir="ltr"><?php echo $h($r['changed_at']); ?></td>
                <td><?php echo $h(isset($NAMES[(int) $r['actor_user_id']])
                        ? $NAMES[(int) $r['actor_user_id']]
                        : ('#' . (int) $r['actor_user_id'])); ?>
                    <small class="text-muted">(دور <?php echo $h($r['actor_role']); ?>)</small></td>
                <td><span class="badge bg-secondary"><?php echo $h($ar($LAYER_AR, (string) $r['layer'])); ?></span></td>
                <td><?php echo $h($ar($VERB_AR, (string) $r['verb'])); ?></td>
                <td><?php echo $h($subj); ?>
                    <?php if ((string) $r['screen_code'] !== '' && $sk !== 'freeze'): ?>
                      <br><code><?php echo $h($r['screen_code']); ?></code>
                    <?php endif; ?></td>
                <td class="text-muted"><?php echo $h($r['before_val']); ?></td>
                <td><strong><?php echo $h($r['after_val']); ?></strong></td>
                <td><?php echo $h($r['reason']); ?></td>
                <td class="text-muted"><small><?php echo $h($r['source_screen']); ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ── ② الفتحُ الاضطراريُّ الحيّ ─────────────────────────────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-triangle-exclamation"></i></span>
      فتح اضطراري حي (<?php echo count($EXC); ?>)
    </div>
    <div class="filter-body">
      <?php if (!$EXC): ?>
        <div class="alert alert-success" role="status">
          لا فتح اضطراري ساريا الان. وهذا هو الوضع المعتاد.
        </div>
      <?php else: ?>
      <div class="table-container">
        <table class="table table-striped" data-no-datatable>
          <thead><tr><th>الموظف</th><th>الشاشة</th><th>من</th><th>الى</th><th>المجيزون</th><th>السبب</th></tr></thead>
          <tbody>
            <?php foreach ($EXC as $e): $pid = (int) $e['person_id']; ?>
              <tr>
                <td><?php echo $h(isset($NAMES[$pid]) ? $NAMES[$pid] : ('#' . $pid)); ?></td>
                <td><code><?php echo $h($e['permission_code']); ?></code></td>
                <td dir="ltr"><?php echo $h($e['valid_from']); ?></td>
                <td dir="ltr"><strong><?php echo $h($e['valid_to']); ?></strong></td>
                <td dir="ltr"><?php echo $h($e['approvals_ref']); ?></td>
                <td><?php echo $h($e['reason']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ── ③ المنحُ النافذةُ الآن ──────────────────────────────────────── */ ?>
  <div class="ems-card">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-users-gear"></i></span>
      من يحمل ماذا الان
    </div>
    <div class="filter-body">
      <div class="table-container">
        <table class="table table-striped">
          <thead><tr><th>الموظف</th><th>الدور</th><th>القالب</th><th>منذ</th><th>سبب الاسناد</th></tr></thead>
          <tbody>
            <?php while ($LIVE && ($g = $LIVE->fetch_assoc())): ?>
              <tr>
                <td><?php echo $h($g['uname']); ?></td>
                <td><?php echo $h($g['role']); ?></td>
                <td><code><?php echo $h($g['profile_code']); ?></code> <?php echo $h($g['title_ar']); ?></td>
                <td dir="ltr"><?php echo $h($g['valid_from']); ?></td>
                <td><?php echo $h($g['reason']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

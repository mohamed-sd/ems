<?php
/**
 * Governance/auth_profile_edit.php — بنّاءُ قوالبِ الصلاحيات (PERM-02 ①)
 * ───────────────────────────────────────────────────────────────────────────
 * الشقُّ الذي كان مفقودًا: كلُّ القوالبِ الاثنين والثلاثين وُلدت بأدواتِ سطرِ
 * أوامرٍ وهجرات، و`auth_profiles.php` عرضٌ محضٌ يردُّ 405 على كلِّ كتابة. فمديرُ
 * الصلاحيّاتِ كان يملك **إسنادَ** قالبٍ ولا يملك **صنعَه**.
 *
 * ◆ **والشاشةُ عميلٌ لا بابٌ خامس**: لا تكتب حرفًا في جداولِ السياسة، بل تنادي
 *   `PolicyWriteService` وتعرض جوابَه. وكلُّ فعلٍ يمرُّ بحرّاسِه الأربعة.
 * ◆ **ومسارُ الحياةِ واحدٌ لا يُختصر**: مسودّةٌ ثمَّ بنودٌ ثمَّ اعتمادٌ ثمَّ
 *   تفعيلٌ ثمَّ إسناد. ولا يُعدَّل نافذٌ في مكانِه بل يُنسَخ إصدارًا جديدًا.
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
require_once __DIR__ . '/../app/Services/Security/PolicyWriteService.php';

use App\Services\Security\PolicyWriteService as PW;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$SCREEN         = 'Governance/auth_profile_edit.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}

/* ⛔ **والتأليفُ بيدِ الدورِ 15 وحدَه** بنصِّ ق-١: «مالك الإسناد الدور 15 وحده».
     والسوبرُ ليس صاحبَ ولايةٍ على السياسةِ بل على البنيةِ التقنيّة. */
$__isAuthor = (strval($_SESSION['user']['role'] ?? '') === '15');

// ═══ ④ حارسُ الفعل ورمزُ الحماية ═══
ems_require_action($conn, $SCREEN, 'write',
    array('deny_msg' => 'تأليف القوالب بيد ادارة الصلاحيات وحدها'));

// ═══ ⑤ المعالجات ═══
$flash = null; $flashKind = 'info';
/* ◆ **والسياقُ يُحمَل في الطلبِ الكاتبِ أيضًا**: كان يُقرأ من `?id=` وحدَه،
     فكلُّ فعلٍ على قالبٍ يُرسَل إلى المسارِ المجرَّدِ فيرتدُّ المستخدمُ إلى
     شاشةِ «قالبٍ جديد» ويظنُّ أنَّ فعلَه ضاع — وقد وقع مقيسًا في التجربة. */
$PID = (int) ($_GET['id'] ?? 0);
if ($PID <= 0 && isset($_POST['profile_id'])) { $PID = (int) $_POST['profile_id']; }

$act = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (string) ($_POST['action'] ?? '') : '';
if ($act !== '' && !$__isAuthor) {
    $flash = 'تأليف القوالب بيد ادارة الصلاحيات (الدور 15) وحدها'; $flashKind = 'danger'; $act = '';
}

$why = trim((string) ($_POST['reason'] ?? ''));
$res = null;

switch ($act) {
    case 'create':
        $res = PW::createProfile($conn, array(
            'profile_code' => (string) ($_POST['profile_code'] ?? ''),
            'title_ar'     => (string) ($_POST['title_ar'] ?? ''),
            'dept_code'    => (string) ($_POST['dept_code'] ?? 'عام'),
            'grade'        => (string) ($_POST['grade'] ?? 'G3'),
            'data_scope'   => (string) ($_POST['data_scope'] ?? 'ادارته'),
        ), $why, $uid);
        if (!empty($res['ok'])) { $PID = (int) $res['id']; }
        break;

    case 'update':
        $res = PW::updateProfile($conn, (int) ($_POST['profile_id'] ?? 0), array(
            'title_ar'   => (string) ($_POST['title_ar'] ?? ''),
            'dept_code'  => (string) ($_POST['dept_code'] ?? ''),
            'grade'      => (string) ($_POST['grade'] ?? 'G3'),
            'data_scope' => (string) ($_POST['data_scope'] ?? ''),
        ), $why, $uid);
        break;

    case 'add_item':
        $res = PW::setProfileItem($conn, (int) ($_POST['profile_id'] ?? 0),
            (string) ($_POST['screen_code'] ?? ''),
            array('allow' => 1,
                  'can_add'    => !empty($_POST['n_add']),
                  'can_edit'   => !empty($_POST['n_edit']),
                  'can_delete' => !empty($_POST['n_del'])),
            $why, $uid, (string) ($_POST['item_kind'] ?? 'screen'));
        break;

    case 'save_items':
        /* ◆ **ولا يُكتب إلّا ما تغيّر**: صفٌّ لم يتبدّل لا يفتح معاملةً ولا يترك
             سطرَ أثرٍ كاذبًا يقول «غُيّر» ولم يُغيَّر. */
        $pid = (int) ($_POST['profile_id'] ?? 0);
        $cur = array();
        foreach (PW::profileItems($pid, null) as $it) {
            $cur[(string) $it['item_kind']][(string) $it['item_ref']] = $it;
        }
        $sent = isset($_POST['item']) && is_array($_POST['item']) ? $_POST['item'] : array();
        /* ⛔ **والمدارُ ما صُيِّر لا ما وصل**: خانةُ الاختيارِ غيرُ المؤشَّرةِ
             **لا تُرسَل أصلًا**، فصفٌّ نُزعت علامتُه الوحيدةُ (`مسموح` بلا أعلامِ
             كتابةٍ — نمطُ `1000`) يصل **بلا مفتاحٍ البتّة**. وكان الدورانُ على
             المُرسَلِ وحدَه، فالصفُّ لا يُزار ولا يُكتب ويردُّ «لا بند تغير فلا
             كتابة» — أي أنّ **منعَ شاشةِ عرضٍ كان متعذِّرًا من الواجهة**، وهي
             8,122 بندًا من 10,629 (76.4٪). مقيسٌ حيًّا على القالب 534.
           ◆ **فيُرسَل مع كلِّ صفٍّ شاهدُ تصييرٍ خفيّ** (`seen[نوع][مرجع]`)،
             ويدور المُعالجُ عليه: فما صُيِّر يُفحَص، وغيابُ العَلَمِ يُقرأ
             **إطفاءً** لا صمتًا. وقاعدةُ «لا يُكتب إلّا ما تغيّر» على حالها. */
        $seen = isset($_POST['seen']) && is_array($_POST['seen']) ? $_POST['seen'] : array();
        $done = 0; $failMsg = '';
        foreach ($seen as $kind => $refs) {
            if (!is_array($refs) || !isset($cur[$kind])) { continue; }
            foreach (array_keys($refs) as $ref) {
                $ref = (string) $ref;
                if (!isset($cur[$kind][$ref])) { continue; }
                $f = (isset($sent[$kind][$ref]) && is_array($sent[$kind][$ref]))
                    ? $sent[$kind][$ref] : array();
                $row  = $cur[$kind][$ref];
                $want = array('allow' => !empty($f['allow']), 'can_add' => !empty($f['add']),
                              'can_edit' => !empty($f['edit']), 'can_delete' => !empty($f['del']));
                $have = array('allow' => (int) $row['allow'] === 1,
                              'can_add' => (int) $row['can_add'] === 1,
                              'can_edit' => (int) $row['can_edit'] === 1,
                              'can_delete' => (int) $row['can_delete'] === 1);
                if ($want == $have) { continue; }
                $r = PW::setProfileItem($conn, $pid, (string) $ref, $want, $why, $uid, (string) $kind);
                if (!empty($r['ok'])) { $done++; } elseif ($failMsg === '') { $failMsg = (string) $r['msg']; }
            }
        }
        $res = $failMsg !== ''
            ? array('ok' => false, 'code' => 'PARTIAL', 'msg' => $failMsg, 'id' => 0)
            : array('ok' => true, 'code' => 'OK', 'id' => 0,
                    'msg' => $done > 0 ? ('حفظ ' . $done . ' بندا') : 'لا بند تغير فلا كتابة');
        break;

    case 'approve':
        $res = PW::approveProfile($conn, (int) ($_POST['profile_id'] ?? 0), $why, $uid);
        break;

    case 'activate':
        $res = PW::activateProfile($conn, (int) ($_POST['profile_id'] ?? 0), $why, $uid);
        break;

    case 'retire':
        $res = PW::retireProfile($conn, (int) ($_POST['profile_id'] ?? 0), $why, $uid);
        break;

    case 'clone':
        $res = PW::cloneProfile($conn, (int) ($_POST['profile_id'] ?? 0),
            (string) ($_POST['new_code'] ?? ''), $why, $uid);
        if (!empty($res['ok'])) { $PID = (int) $res['id']; }
        break;

    case 'freeze':
        $res = PW::setFreeze($conn, (string) ($_POST['scope'] ?? ''),
            ((string) ($_POST['to'] ?? '') === '1'), $why, $uid);
        break;
}

if ($res !== null) {
    $flash = (string) $res['msg'];
    $flashKind = !empty($res['ok']) ? 'success'
        : (in_array((string) $res['code'], array('FROZEN', 'NOTHING', 'ALREADY', 'ALREADY_APPROVED'), true)
           ? 'warning' : 'danger');
}

// ═══ ⑥ العرض ═══
$P = $PID > 0 ? PW::readProfile($PID) : null;
$ITEMS = $P ? PW::profileItems($PID, null) : array();
$IS_DRAFT = $P && (string) $P['state'] === 'draft';

/* عددُ الحاملين الأحياء وسجلُّ الاعتماد — قراءةٌ عرضٍ لا قرار. */
$HOLDERS = 0; $APPROVED = 0;
if ($P) {
    $st = $conn->prepare("SELECT COUNT(*) FROM gov_authority_grants
                           WHERE profile_id = ? AND revoked_at IS NULL");
    $st->bind_param('i', $PID); $st->execute();
    $HOLDERS = (int) ($st->get_result()->fetch_row()[0] ?? 0); $st->close();
    $st = $conn->prepare("SELECT COUNT(*) FROM gov_profile_activation_approval
                           WHERE profile_id = ? AND version = ?");
    $v = (int) $P['version'];
    $st->bind_param('ii', $PID, $v); $st->execute();
    $APPROVED = (int) ($st->get_result()->fetch_row()[0] ?? 0); $st->close();
}

/* ── مفرداتُ كلِّ نوعٍ من سجلِّه الحاكم (PERM-03) ───────────────────────────
     ◆ **والشاشةُ تختار من المُعلَنِ ولا تكتب نصًّا حرًّا**: رمزٌ لا يقابل مدخلًا
       حاكمًا لا يحكم شيئًا، ويُقرأ ضمانًا وهو فراغ. */
require_once __DIR__ . '/../includes/perm_layers.php';
$INPROF = array();
foreach ($ITEMS as $it) { $INPROF[(string) $it['item_kind']][(string) $it['item_ref']] = 1; }

$VOCAB = array('screen' => array());
$q = $conn->query("SELECT code, name FROM modules ORDER BY code");
while ($q && ($x = $q->fetch_assoc())) {
    if (!isset($INPROF['screen'][(string) $x['code']])) {
        $VOCAB['screen'][] = array('ref' => (string) $x['code'], 'label' => (string) $x['name']);
    }
}
foreach (array('action', 'cap', 'scope', 'field') as $k) {
    $VOCAB[$k] = array();
    foreach (ems_layer_vocabulary($conn, $k) as $v) {
        if (!isset($INPROF[$k][$v['ref']])) { $VOCAB[$k][] = $v; }
    }
}
$KIND_AR = array('screen' => 'شاشة', 'action' => 'فعل', 'cap' => 'سقف',
                 'scope' => 'مجال بيانات', 'field' => 'حقل حساس');

$FRZ_G = PW::isFrozen(PW::FREEZE_GRANTS);
$FRZ_A = PW::isFrozen(PW::FREEZE_ACTIVATION);
$GRADES = array('G1','G2','G3','G4','G5','G6','G7','G8','G9');
$ST_AR  = array('draft' => 'مسودة', 'active' => 'نافذ', 'retired' => 'متقاعد');

$PAGE_TITLE = 'بناء قوالب الصلاحيات';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
$CSRF = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-sitemap';
  $header_desc = 'تأليف القالب وضبط بنوده واعتماده وتفعيله. المسار واحد: مسودة ثم بنود ثم اعتماد ثم تفعيل ثم اسناد.';
  $header_back = array('href' => 'auth_profiles.php', 'icon' => 'fas fa-arrow-right', 'label' => 'كل القوالب');
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php if ($flash !== null): ?>
    <div class="alert alert-<?php echo $h($flashKind); ?>" role="status"><?php echo $h($flash); ?></div>
  <?php endif; ?>

  <?php if (!$__isAuthor): ?>
    <div class="alert alert-warning" role="status">
      هذه الشاشة للعرض في حسابك. تأليف القوالب وتعديلها بيد ادارة الصلاحيات (الدور 15) وحدها.
    </div>
  <?php endif; ?>

  <?php /* ── بوّاباتُ السياسة: تُعرض قبلَ المحاولةِ لا بعدَها ───────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title"><span class="filter-title-icon"><i class="fa fa-shield-halved"></i></span> بوابات السياسة</div>
    <div class="filter-body">
      <p class="text-muted">
        البوابة المغلقة ترد الكتابة عند القادح في قاعدة البيانات، لا في الشاشة وحدها.
        ارفعها بسبب مكتوب حين تعمل، واغلقها حين تنتهي.
      </p>
      <div class="pe-row-wide">
        <?php foreach (array(PW::FREEZE_GRANTS => array('اصدار المنح', $FRZ_G),
                             PW::FREEZE_ACTIVATION => array('تفعيل القوالب', $FRZ_A)) as $sc => $info): ?>
          <form method="post" class="ems-form pe-row-end">
            <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
            <input type="hidden" name="action" value="freeze">
            <input type="hidden" name="scope" value="<?php echo $h($sc); ?>">
            <input type="hidden" name="to" value="<?php echo $info[1] ? '0' : '1'; ?>">
            <div class="form-group px-w-320">
              <label><?php echo $h($info[0]); ?>:
                <strong class="<?php echo $info[1] ? 'text-danger' : 'text-success'; ?>">
                  <?php echo $info[1] ? 'مغلقة' : 'مفتوحة'; ?></strong>
              </label>
              <input type="text" name="reason" class="form-control" required
                     placeholder="سبب <?php echo $info[1] ? 'الفتح' : 'الاغلاق'; ?>">
            </div>
            <button class="btn btn-<?php echo $info[1] ? 'success' : 'secondary'; ?>" type="submit"
                    <?php echo $__isAuthor ? '' : 'disabled'; ?>>
              <?php echo $info[1] ? 'افتح' : 'اغلق'; ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (!$P): ?>
  <?php /* ── تأليفُ قالبٍ جديد ─────────────────────────────────────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title"><span class="filter-title-icon"><i class="fa fa-plus-circle"></i></span> قالب جديد</div>
    <div class="filter-body">
      <form method="post" class="ems-form">
        <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group px-w-320">
          <label for="profile_code">رمز القالب</label>
          <input type="text" id="profile_code" name="profile_code" class="form-control" required
                 pattern="[A-Za-z0-9][A-Za-z0-9\-_]{1,19}" placeholder="FIN-B1">
        </div>
        <div class="form-group px-w-320">
          <label for="title_ar">اسم القالب</label>
          <input type="text" id="title_ar" name="title_ar" class="form-control" required
                 placeholder="محاسب الادارة المالية">
        </div>
        <div class="form-group px-w-320">
          <label for="dept_code">الادارة</label>
          <input type="text" id="dept_code" name="dept_code" class="form-control" value="عام">
        </div>
        <div class="form-group px-w-320">
          <label for="grade">الدرجة</label>
          <select id="grade" name="grade" class="form-control">
            <?php foreach ($GRADES as $g): ?>
              <option value="<?php echo $h($g); ?>" <?php echo $g === 'G3' ? 'selected' : ''; ?>><?php echo $h($g); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="c_reason">سبب التأليف</label>
          <input type="text" id="c_reason" name="reason" class="form-control" required>
        </div>
        <button class="btn btn-primary" type="submit" <?php echo $__isAuthor ? '' : 'disabled'; ?>>
          <i class="fa fa-plus"></i> الف القالب مسودة
        </button>
      </form>
    </div>
  </div>
  <div class="alert alert-info" role="status">
    اختر قالبا من <a href="auth_profiles.php">قائمة القوالب</a> لبناء بنوده، او الف قالبا جديدا من النموذج اعلاه.
  </div>

  <?php else: ?>
  <?php /* ── بطاقةُ القالبِ وأفعالُ دورةِ حياتِه ───────────────────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-id-card"></i></span>
      <?php echo $h($P['profile_code'] . ' · ' . $P['title_ar']); ?>
      <span class="badge bg-<?php echo $IS_DRAFT ? 'secondary' : ((string) $P['state'] === 'active' ? 'success' : 'dark'); ?>">
        <?php echo $h($ST_AR[(string) $P['state']] ?? $P['state']); ?>
      </span>
    </div>
    <div class="filter-body">
      <table class="table table-sm pe-table-narrow">
        <tbody>
          <tr><th>الادارة</th><td><?php echo $h($P['dept_code']); ?></td>
              <th>الدرجة</th><td><?php echo $h($P['grade']); ?></td></tr>
          <tr><th>الاصدار</th><td><?php echo (int) $P['version']; ?></td>
              <th>نطاق البيانات</th><td><?php echo $h($P['data_scope']); ?></td></tr>
          <tr><th>بنود مسموحة</th><td><?php echo (int) $P['screens_target']; ?></td>
              <th>حاملون احياء</th><td><?php echo (int) $HOLDERS; ?></td></tr>
          <tr><th>سجل الاعتماد</th>
              <td colspan="3"><?php echo $APPROVED > 0 ? 'معتمد لهذا الاصدار' : 'لا اعتماد لهذا الاصدار'; ?></td></tr>
        </tbody>
      </table>

      <div class="pe-row-mid-top">
        <?php if ($IS_DRAFT && $APPROVED === 0): ?>
          <form method="post" class="ems-form pe-row-end">
            <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
            <div class="form-group px-w-320">
              <label for="ap_reason">سند الاعتماد</label>
              <input type="text" id="ap_reason" name="reason" class="form-control" required>
            </div>
            <button class="btn btn-primary" type="submit" <?php echo $__isAuthor ? '' : 'disabled'; ?>>
              <i class="fa fa-stamp"></i> اعتمد
            </button>
          </form>
        <?php endif; ?>

        <?php if ($IS_DRAFT && $APPROVED > 0): ?>
          <form method="post" class="ems-form pe-row-end">
            <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
            <div class="form-group px-w-320">
              <label for="ac_reason">سبب التفعيل</label>
              <input type="text" id="ac_reason" name="reason" class="form-control" required>
            </div>
            <button class="btn btn-success" type="submit"
                    <?php echo ($__isAuthor && !$FRZ_A) ? '' : 'disabled'; ?>>
              <i class="fa fa-play"></i> فعل القالب
            </button>
          </form>
        <?php endif; ?>

        <?php if ((string) $P['state'] === 'active'): ?>
          <form method="post" class="ems-form pe-row-end">
            <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
            <input type="hidden" name="action" value="clone">
            <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
            <div class="form-group px-w-320">
              <label for="new_code">رمز الاصدار الجديد</label>
              <input type="text" id="new_code" name="new_code" class="form-control" required
                     pattern="[A-Za-z0-9][A-Za-z0-9\-_]{1,19}">
            </div>
            <div class="form-group px-w-320">
              <label for="cl_reason">سبب النسخ</label>
              <input type="text" id="cl_reason" name="reason" class="form-control" required>
            </div>
            <button class="btn btn-secondary" type="submit" <?php echo $__isAuthor ? '' : 'disabled'; ?>>
              <i class="fa fa-copy"></i> انسخ اصدارا جديدا
            </button>
          </form>
        <?php endif; ?>

        <?php if ((string) $P['state'] !== 'retired'): ?>
          <form method="post" class="ems-form pe-row-end">
            <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
            <input type="hidden" name="action" value="retire">
            <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
            <div class="form-group px-w-320">
              <label for="rt_reason">سبب التقاعد</label>
              <input type="text" id="rt_reason" name="reason" class="form-control" required>
            </div>
            <button class="btn btn-danger" type="submit"
                    <?php echo ($__isAuthor && $HOLDERS === 0) ? '' : 'disabled'; ?>>
              <i class="fa fa-box-archive"></i> اقعد القالب
            </button>
          </form>
          <?php if ($HOLDERS > 0): ?>
            <div class="alert alert-info pe-span-all" role="status">
              للقالب <?php echo (int) $HOLDERS; ?> حاملا حيا. اسحب منحهم من
              <a href="auth_grants.php">شاشة المنح</a> قبل التقاعد، فالنظام مغلق افتراضيا
              ومن فقد قالبه يمنع من كل شاشة.
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($IS_DRAFT): ?>
  <?php /* ── بياناتُ القالب ────────────────────────────────────────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title"><span class="filter-title-icon"><i class="fa fa-pen"></i></span> بيانات القالب</div>
    <div class="filter-body">
      <form method="post" class="ems-form">
        <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
        <div class="form-group px-w-320">
          <label for="u_title">اسم القالب</label>
          <input type="text" id="u_title" name="title_ar" class="form-control" required
                 value="<?php echo $h($P['title_ar']); ?>">
        </div>
        <div class="form-group px-w-320">
          <label for="u_dept">الادارة</label>
          <input type="text" id="u_dept" name="dept_code" class="form-control" value="<?php echo $h($P['dept_code']); ?>">
        </div>
        <div class="form-group px-w-320">
          <label for="u_grade">الدرجة</label>
          <select id="u_grade" name="grade" class="form-control">
            <?php foreach ($GRADES as $g): ?>
              <option value="<?php echo $h($g); ?>" <?php echo $g === (string) $P['grade'] ? 'selected' : ''; ?>><?php echo $h($g); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="u_scope">نطاق البيانات</label>
          <input type="text" id="u_scope" name="data_scope" class="form-control" value="<?php echo $h($P['data_scope']); ?>">
        </div>
        <div class="form-group px-w-320">
          <label for="u_reason">سبب التعديل</label>
          <input type="text" id="u_reason" name="reason" class="form-control" required>
        </div>
        <button class="btn btn-primary" type="submit" <?php echo $__isAuthor ? '' : 'disabled'; ?>>حفظ البيانات</button>
      </form>
    </div>
  </div>

  <?php /* ── ضمُّ شاشةٍ ─────────────────────────────────────────────────────── */ ?>
  <div class="ems-card ems-mb-16">
    <div class="filter-title"><span class="filter-title-icon"><i class="fa fa-square-plus"></i></span> ضم بند الى القالب</div>
    <div class="filter-body">
      <p class="text-muted">
        البند مرجع لا تعريف جديد: الشاشة من سجل الوحدات، والفعل والسقف من سجل حدود السلطة،
        والحقل من سياسات الحقول الحساسة، والمجال من سجل المساحات. فلا يخترع القالب شيئا.
      </p>
      <form method="post" class="ems-form">
        <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
        <div class="form-group px-w-320">
          <label for="item_kind">نوع البند</label>
          <select id="item_kind" name="item_kind" class="form-control" onchange="emsKindSwitch(this.value)">
            <?php foreach ($KIND_AR as $k => $lab): ?>
              <option value="<?php echo $h($k); ?>"><?php echo $h($lab); ?>
                (<?php echo count($VOCAB[$k]); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="screen_code">المرجع (من سجله الحاكم)</label>
          <input list="voc_screen" id="screen_code" name="screen_code" class="form-control" required
                 placeholder="اكتب جزءا من الرمز">
          <?php foreach ($KIND_AR as $k => $lab): ?>
            <datalist id="voc_<?php echo $h($k); ?>">
              <?php foreach ($VOCAB[$k] as $v): ?>
                <option value="<?php echo $h($v['ref']); ?>"><?php echo $h($v['label']); ?></option>
              <?php endforeach; ?>
            </datalist>
          <?php endforeach; ?>
        </div>
        <div class="form-group px-w-320">
          <label>اعلام الكتابة</label>
          <label><input type="checkbox" name="n_add" value="1"> اضافة</label>
          <label><input type="checkbox" name="n_edit" value="1"> تعديل</label>
          <label><input type="checkbox" name="n_del" value="1"> حذف</label>
        </div>
        <div class="form-group px-w-320">
          <label for="i_reason">سبب الضم</label>
          <input type="text" id="i_reason" name="reason" class="form-control" required>
        </div>
        <button class="btn btn-primary" type="submit" <?php echo $__isAuthor ? '' : 'disabled'; ?>>
          <i class="fa fa-plus"></i> ضم البند
        </button>
      </form>
      <script>
      /* تبديلُ قائمةِ المفرداتِ بتبديلِ النوع — والقائمةُ من الخادمِ لا تُبنى هنا */
      function emsKindSwitch(k){
        var i=document.getElementById('screen_code');
        if(i){ i.setAttribute('list','voc_'+k); i.value=''; }
      }
      </script>
    </div>
  </div>
  <?php endif; ?>

  <?php /* ── بنودُ القالب ───────────────────────────────────────────────────── */ ?>
  <div class="ems-card">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-list-check"></i></span>
      بنود القالب (<?php echo count($ITEMS); ?>)
    </div>
    <div class="filter-body">
      <?php if (!$ITEMS): ?>
        <div class="alert alert-info" role="status">لا بند بعد. ضم شاشة من النموذج اعلاه.</div>
      <?php else: ?>
        <?php if (!$IS_DRAFT): ?>
          <div class="alert alert-warning" role="status">
            القالب ليس مسودة فالبنود للعرض. لتعديلها انسخه اصدارا جديدا ثم عدل النسخة.
          </div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
          <input type="hidden" name="action" value="save_items">
          <input type="hidden" name="profile_id" value="<?php echo $PID; ?>">
          <div class="table-container">
            <table class="table table-striped" data-no-datatable>
              <thead>
                <tr><th>النوع</th><th>المرجع</th><th>مسموح</th><th>اضافة</th><th>تعديل</th><th>حذف</th></tr>
              </thead>
              <tbody>
                <?php foreach ($ITEMS as $it):
                    $ref = (string) $it['item_ref'];
                    $kd  = (string) $it['item_kind']; ?>
                  <tr class="<?php echo (int) $it['allow'] === 1 ? '' : 'text-muted'; ?>">
                    <td><span class="badge bg-secondary"><?php
                        echo $h(isset($KIND_AR[$kd]) ? $KIND_AR[$kd] : $kd); ?></span></td>
                    <td><code><?php echo $h($ref); ?></code>
                      <?php /* شاهدُ التصيير: يُرسَل ولو أُطفئت خاناتُ الصفِّ كلُّها،
                               فيَعرف المُعالجُ أنَّ الصفَّ عُرض وقُرِّر فيه. ولا
                               يُرسَل إن كان الجدولُ للعرضِ فقط — فلا قرارَ يُدَّعى. */
                        if ($IS_DRAFT && $__isAuthor): ?>
                        <input type="hidden" name="seen[<?php echo $h($kd); ?>][<?php echo $h($ref); ?>]" value="1">
                      <?php endif; ?>
                    </td>
                    <?php foreach (array('allow' => 'allow', 'add' => 'can_add',
                                         'edit' => 'can_edit', 'del' => 'can_delete') as $k => $col): ?>
                      <td>
                        <input type="checkbox"
                               name="item[<?php echo $h($kd); ?>][<?php echo $h($ref); ?>][<?php echo $k; ?>]"
                               value="1"
                               <?php echo (int) $it[$col] === 1 ? 'checked' : ''; ?>
                               <?php echo ($IS_DRAFT && $__isAuthor) ? '' : 'disabled'; ?>>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($IS_DRAFT): ?>
            <div class="form-group px-w-320 pe-mt-mid">
              <label for="s_reason">سبب التعديل</label>
              <input type="text" id="s_reason" name="reason" class="form-control" required>
            </div>
            <button class="btn btn-primary" type="submit" <?php echo $__isAuthor ? '' : 'disabled'; ?>>
              <i class="fa fa-save"></i> حفظ البنود
            </button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

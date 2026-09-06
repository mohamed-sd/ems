<?php
/**
 * Governance/break_glass_open.php — فتحُ الطوارئِ الموقوت (PERM-04 ①)
 * ───────────────────────────────────────────────────────────────────────────
 * ⛔ **العلّةُ المقيسة**: `PolicyWriteService::openException()` مبنيٌّ ومحروسٌ
 *   بخمسةِ قيودٍ ومُختبَرٌ بواحدٍ وعشرين تأكيدًا — و**صفرُ شاشةٍ تناديه**.
 *   و`Governance/break_glass.php` تكتب في `cmp03_screen_rows` (مخزنُ توثيقٍ
 *   بينيّ) لا في سجلِّ الاستثناءات. فالنظامُ **مغلقٌ افتراضيًّا** ولا حسابَ
 *   سوبرَ حيًّا، **ومسارُ الطوارئِ لا يُبلَغ من الواجهة**. وهذا نصُّ ما حذّر
 *   منه ق-٢: «لا يُنقَل مستخدمٌ إلى الإغلاقِ الافتراضيِّ قبلَ وجودِ فتحٍ محكوم».
 *
 * ◆ **والشاشةُ تُبلِّغ الحارسَ ولا تحكم**: تجمع المدخلاتِ وتنادي المنفذَ
 *   المحروسَ وتعرض جوابَه حرفًا. ولا تكتب في سجلِّ الاستثناءاتِ بحرف.
 *
 * ⛔ **والمُشغِّلُ ليس المجيز**: الدورُ 15 يسجّل الطلبَ ويسمّي مجيزَه، والخدمةُ
 *   تردُّ إن كان المجيزُ هو الطالبَ أو من الدورِ 15 نفسِه — «طالبٌ ≠ مجيزٌ ≠
 *   مُسنِد» بنصِّ ق-٢. والشاشةُ تقول ذلك قبلَ المحاولةِ لا بعدَها.
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
$SCREEN         = 'Governance/break_glass_open.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}
$__isOperator = (strval($_SESSION['user']['role'] ?? '') === '15');

// ═══ ④ حارسُ الفعل ورمزُ الحماية ═══
ems_require_action($conn, $SCREEN, 'write',
    array('deny_msg' => 'فتح الطوارئ بيد ادارة الصلاحيات وحدها، والاجازة بيد الحوكمة'));

// ═══ ⑤ المعالجات — نداءٌ واحدٌ للمنفذ ═══
$flash = null; $flashKind = 'info';
$act = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (string) ($_POST['action'] ?? '') : '';
if ($act !== '' && !$__isOperator) {
    $flash = 'تسجيل فتح الطوارئ بيد ادارة الصلاحيات (الدور 15) وحدها'; $flashKind = 'danger'; $act = '';
}

if ($act === 'open') {
    $res = PW::openException($conn,
        (int) ($_POST['bg_user'] ?? 0),
        trim((string) ($_POST['bg_screen'] ?? '')),
        array(
            'approver_gov'  => (int) ($_POST['bg_gov'] ?? 0),
            'approver_fin'  => (int) ($_POST['bg_fin'] ?? 0),
            'hours'         => (int) ($_POST['bg_hours'] ?? 0),
            'reason'        => trim((string) ($_POST['bg_reason'] ?? '')),
            'compensating'  => trim((string) ($_POST['bg_comp'] ?? '')),
        ));
    $flash = (string) $res['msg'];
    $flashKind = !empty($res['ok']) ? 'success' : 'danger';
}

if ($act === 'close') {
    /* ◆ **والإغلاقُ المبكِّرُ سحبٌ لا حذف**: الاستثناءُ ينتهي بنفسِه بالوقت،
         وهذا يسحبه قبلَ أوانِه حين تزول الحاجة. ويمرُّ بالبوّابةِ نفسِها. */
    $exId = (int) ($_POST['ex_id'] ?? 0);
    $why  = trim((string) ($_POST['close_reason'] ?? ''));
    if ($exId <= 0 || $why === '') {
        $flash = 'الاغلاق يلزمه رقم الاستثناء وسبب مكتوب'; $flashKind = 'danger';
    } else {
        $res = PW::closeException($conn, $exId, $why, $uid);
        $flash = (string) $res['msg'];
        $flashKind = !empty($res['ok']) ? 'success' : 'danger';
    }
}

// ═══ ⑥ العرض ═══
$LIVE = PW::liveExceptions();
$USERS = array();
$q = $conn->query("SELECT id, name, role FROM users
                    WHERE is_deleted = 0 AND status = 'active' AND company_id = " . (int) $company_id . "
                    ORDER BY CAST(role AS UNSIGNED), name");
while ($q && ($x = $q->fetch_assoc())) { $USERS[] = $x; }

$SCREENS = array();
$q = $conn->query("SELECT code, name FROM modules ORDER BY code");
while ($q && ($x = $q->fetch_assoc())) { $SCREENS[] = $x; }

/* حرّاسُ `never` — تُعرض قبلَ المحاولةِ فلا يُملأ نموذجٌ يُردّ. */
$NEVER = array();
$q = $conn->query("SELECT guard_code, name_ar FROM guard_override_policies
                    WHERE overridable = 'never' ORDER BY guard_code");
while ($q && ($x = $q->fetch_assoc())) { $NEVER[] = $x; }

$ids = array();
foreach ($LIVE as $e) { $ids[] = (int) $e['person_id']; }
$NAMES = PW::nameMap($ids);

$PAGE_TITLE = 'فتح الطوارئ الموقوت';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
$CSRF = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-triangle-exclamation';
  $header_desc = 'النظام مغلق افتراضيا، وهذا مخرج الطوارئ الوحيد. يفتح ولا يغلق، وينتهي بنفسه بالوقت، وكل استعماله مسجل.';
  $header_back = array('href' => 'perm_audit_log.php', 'icon' => 'fas fa-arrow-right', 'label' => 'المحاسبة');
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php if ($flash !== null): ?>
    <div class="alert alert-<?php echo $h($flashKind); ?>" role="status"><?php echo $h($flash); ?></div>
  <?php endif; ?>

 <div class="alert alert-warning" role="status">
 <strong>خمسة قيود لا يتجاوز واحد منها:</strong>
 حراس <code>never</code> لا تكسر مهما كان السبب -
 المجيز ليس الطالب ولا من الدور 15 -
 الشاشات المالية تلزمها ثنائية: مجيز حوكمة ومجيز مالي مختلفان -
 السقف 4 ساعات و8 بمجيز ثان ولا يتجاوز 24 بحال -
 وصنف <code>with_compensating_control</code> يلزمه ضابط معوض مكتوب.
 </div>

 <?php if (!$__isOperator): ?>
 <div class="alert alert-info" role="status">
 هذه الشاشة للعرض في حسابك. تسجيل الفتح بيد ادارة الصلاحيات (الدور 15) وحدها.
 </div>
 <?php endif; ?>

  <?php /* ── ① فتحٌ جديد ─────────────────────────────────────────────────── */ ?>
 <div class="ems-card ems-mb-16">
 <div class="filter-title">
 <span class="filter-title-icon"><i class="fa fa-unlock-keyhole"></i></span>
 افتح شاشة اضطرارا
 </div>
 <div class="filter-body">
 <form method="post" class="ems-form">
 <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
 <input type="hidden" name="action" value="open">
 <div class="form-group px-w-320">
 <label for="bg_user">من يفتح له</label>
 <select id="bg_user" name="bg_user" class="form-control" required
 <?php echo $__isOperator ? '' : 'disabled'; ?>>
 <option value="">اختر</option>
 <?php foreach ($USERS as $u): ?>
              <option value="<?php echo (int) $u['id']; ?>">
                <?php echo $h($u['name'] . ' (دور ' . $u['role'] . ')'); ?></option>
            <?php endforeach; ?>
 </select>
 </div>
 <div class="form-group px-w-320">
 <label for="bg_screen">الشاشة</label>
 <input list="bg_screens" id="bg_screen" name="bg_screen" class="form-control" required
 placeholder="اكتب جزءا من المسار" <?php echo $__isOperator ? '' : 'disabled'; ?>>
          <datalist id="bg_screens">
            <?php foreach ($SCREENS as $s): ?>
              <option value="<?php echo $h($s['code']); ?>"><?php echo $h($s['name']); ?></option>
            <?php endforeach; ?>
 </datalist>
 </div>
 <div class="form-group px-w-320">
 <label for="bg_gov">مجيز الحوكمة (ليس انت ولا صاحب الطلب)</label>
 <select id="bg_gov" name="bg_gov" class="form-control" required
 <?php echo $__isOperator ? '' : 'disabled'; ?>>
 <option value="">اختر</option>
 <?php foreach ($USERS as $u): if ((string) $u['role'] === '15') { continue; } ?>
              <option value="<?php echo (int) $u['id']; ?>">
                <?php echo $h($u['name'] . ' (دور ' . $u['role'] . ')'); ?></option>
            <?php endforeach; ?>
 </select>
 </div>
 <div class="form-group px-w-320">
 <label for="bg_fin">مجيز مالي (للشاشات المالية)</label>
 <select id="bg_fin" name="bg_fin" class="form-control" <?php echo $__isOperator ? '' : 'disabled'; ?>>
 <option value="0">لا ينطبق</option>
 <?php foreach ($USERS as $u): if ((string) $u['role'] === '15') { continue; } ?>
              <option value="<?php echo (int) $u['id']; ?>">
                <?php echo $h($u['name'] . ' (دور ' . $u['role'] . ')'); ?></option>
            <?php endforeach; ?>
 </select>
 </div>
 <div class="form-group px-w-320">
 <label for="bg_hours">المدة بالساعات (4 - و8 بمجيز ثان)</label>
 <input type="number" id="bg_hours" name="bg_hours" class="form-control" min="1" max="24"
 value="4" required <?php echo $__isOperator ? '' : 'disabled'; ?>>
 </div>
 <div class="form-group px-w-320">
 <label for="bg_comp">ضابط معوض (حيث يلزم)</label>
 <input type="text" id="bg_comp" name="bg_comp" class="form-control" maxlength="200"
 <?php echo $__isOperator ? '' : 'disabled'; ?>>
 </div>
 <div class="form-group px-w-320">
 <label for="bg_reason">السبب (مطلوب)</label>
 <input type="text" id="bg_reason" name="bg_reason" class="form-control" maxlength="255" required
 <?php echo $__isOperator ? '' : 'disabled'; ?>
 placeholder="مثال: قفل خاطئ منع المحاسب من اقفال الفترة">
 </div>
 <button class="btn btn-danger" type="submit" <?php echo $__isOperator ? '' : 'disabled'; ?>>
 <i class="fa fa-unlock"></i> افتح اضطرارا
 </button>
 </form>
 </div>
 </div>

 <?php /* ── ② الاستثناءاتُ الحيّة ───────────────────────────────────────── */ ?>
 <div class="ems-card ems-mb-16">
 <div class="filter-title">
 <span class="filter-title-icon"><i class="fa fa-hourglass-half"></i></span>
 فتح ساري الان (<?php echo count($LIVE); ?>)
    </div>
    <div class="filter-body">
      <?php if (!$LIVE): ?>
 <div class="alert alert-success" role="status">
 لا فتح اضطراري ساريا. وهذا هو الوضع المعتاد.
 </div>
 <?php else: ?>
 <div class="table-container">
 <table class="table table-striped" data-no-datatable>
 <thead><tr><th>الموظف</th><th>الشاشة</th><th>ينتهي</th><th>المجيزون</th><th>السبب</th><th>اغلاق مبكر</th></tr></thead>
 <tbody>
 <?php foreach ($LIVE as $e): $pid = (int) $e['person_id']; ?>
                <tr>
                  <td><?php echo $h(isset($NAMES[$pid]) ? $NAMES[$pid] : ('#' . $pid)); ?></td>
                  <td><code><?php echo $h($e['permission_code']); ?></code></td>
                  <td dir="ltr"><strong><?php echo $h($e['valid_to']); ?></strong></td>
                  <td dir="ltr"><?php echo $h($e['approvals_ref']); ?></td>
                  <td><?php echo $h($e['reason']); ?></td>
                  <td>
                    <form class="pe-row-tight" method="post">
                      <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
                      <input type="hidden" name="action" value="close">
                      <input type="hidden" name="ex_id" value="<?php echo (int) $e['ex_id']; ?>">
 <input type="text" name="close_reason" class="form-control" required
 placeholder="سبب الاغلاق" <?php echo $__isOperator ? '' : 'disabled'; ?>>
                      <button class="btn btn-secondary" type="submit"
                              <?php echo $__isOperator ? '' : 'disabled'; ?>>اغلق</button>
 </form>
 </td>
 </tr>
 <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ── ③ حرّاسُ `never` ─────────────────────────────────────────────── */ ?>
 <div class="ems-card">
 <div class="filter-title">
 <span class="filter-title-icon"><i class="fa fa-ban"></i></span>
 حراس لا يكسر زجاجها بحال (<?php echo count($NEVER); ?>)
    </div>
    <div class="filter-body">
      <?php if (!$NEVER): ?>
 <div class="alert alert-info" role="status">لا حارس مصنف <code>never</code> في السجل.</div>
 <?php else: ?>
 <div class="table-container">
 <table class="table table-sm" data-no-datatable>
 <thead><tr><th>الحارس</th><th>الاسم</th></tr></thead>
 <tbody>
 <?php foreach ($NEVER as $n): ?>
                <tr><td><code><?php echo $h($n['guard_code']); ?></code></td>
                    <td><?php echo $h($n['name_ar']); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
/**
 * Finance/acc_approval_record.php — تسجيلُ قرارِ الاعتماد (PERM-03 ③)
 * ───────────────────────────────────────────────────────────────────────────
 * ⛔ **العلّةُ المقيسة**: `ApprovalGate` مبنيّةٌ وكاملةُ الحرّاس (ترتيبٌ · تعارضٌ ·
 *   دورٌ · سقف)، و`fin_approval_chain` لا يكتب فيها غيرُها — **ولم تكن تُنادى
 *   من شاشةِ إنتاجٍ واحدة**. فخمسُ تركيباتِ فصلِ واجباتٍ تُعلن نقطةَ إنفاذِها
 *   `ApprovalGate::record` و**النقطةُ لا تُبلَغ أصلًا**: ضابطٌ مكتوبٌ لا يعمل.
 *   وشاشةُ `acc_approval_chain.php` تعرض السلسلةَ ولا تُنشئ فيها قرارًا.
 *
 * ◆ **وهذه الشاشةُ لا تحكم بل تُبلِّغ الحارسَ**: تجمع المدخلاتِ وتنادي البوّابةَ
 *   وتعرض جوابَها حرفًا — ولا تكتب في سلسلةِ الاعتمادِ ولا تفحص دورًا ولا سقفًا
 *   بنفسِها. فالحكمُ في موضعٍ واحدٍ يُختبر مرّةً واحدة.
 *
 * ◆ **والفعلُ يُفحص بالقالبِ قبلَ التسجيل** (PERM-03): البوّابةُ تسأل
 *   `ems_can_action` عن الفعلِ المقابلِ لترتيبِ النوع — فمن نُقل إلى قالبٍ
 *   أضيقَ يُردّ ولو بقي دورُه.
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
require_once __DIR__ . '/../app/Services/Finance/ApprovalGate.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Finance/acc_approval_record.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}

// ═══ ④ حارسُ الفعل ورمزُ الحماية ═══
ems_require_action($conn, $SCREEN, 'write',
    array('deny_msg' => 'تسجيل قرار الاعتماد يحتاج صلاحية كتابة على هذه الشاشة'));

// ═══ ⑤ المعالج — نداءٌ واحدٌ للبوّابة ═══
$flash = null; $flashKind = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record') {
    $res = \App\Services\Finance\ApprovalGate::record($conn, array(
        'company_id'    => $company_id,
        'source_kind'   => trim((string) ($_POST['source_kind'] ?? '')),
        'source_ref'    => trim((string) ($_POST['source_ref'] ?? '')),
        'apr_code'      => trim((string) ($_POST['apr_code'] ?? '')),
        'decision'      => (string) ($_POST['decision'] ?? 'approved'),
        'actor_user_id' => $uid,
        'actor_role_id' => $role_id,
        'amount'        => (string) ($_POST['amount'] ?? ''),
        'currency'      => (string) ($_POST['currency'] ?? 'USD'),
        'reason_code'   => (string) ($_POST['reason_code'] ?? ''),
        'note'          => (string) ($_POST['note'] ?? ''),
    ));
    $ok = is_array($res) && !empty($res['ok']);
    /* ◆ **وجوابُ البوّابةِ يُعرض حرفًا**: مفتاحُه `reason` — والشاشةُ لا تعيد
         صياغةَ حكمٍ ولا تخترع رسالةً، فالمستخدمُ يقرأ سببَ الردِّ كما قالته. */
    $flash = is_array($res) ? (string) ($res['reason'] ?? 'تم') : 'تعذر قراءة جواب البوابة';
    $flashKind = $ok ? 'success' : 'danger';
}

// ═══ ⑥ العرض ═══
$TYPES = array();
/* ◆ **والأنواعُ الأربعةُ المُعلَنةُ وحدَها تُعرَض**: في الجدولِ صفٌّ خامسٌ
     رمزُه `FIN_-000` وحقلُ أدوارِه نصٌّ حرٌّ لا أرقام — بقيّةُ استيرادٍ لا نوعُ
     اعتماد. فيُستبعَد من النموذجِ ويُسمّى تحتَه ولا يُطوى. */
$q = $conn->query("SELECT code, seq, title, owner_label, needs_cap
                     FROM fin_approval_types WHERE active = 1 AND code LIKE 'APR-%' ORDER BY seq");
while ($q && ($x = $q->fetch_assoc())) { $TYPES[] = $x; }

$CHAIN = $conn->query(
    "SELECT c.decided_at, c.source_kind, c.source_ref, c.apr_code, c.decision,
            c.actor_user_id, c.actor_capacity, c.amount, c.currency, u.name AS actor_name
       FROM fin_approval_chain c
       LEFT JOIN users u ON u.id = c.actor_user_id
      WHERE c.company_id = " . (int) $company_id . "
      ORDER BY c.decided_at DESC LIMIT 100");

/* الأفعالُ التي يحملها قالبُ الفاعلِ — تُعرض قبلَ المحاولةِ لا بعدَها. */
$MYACTIONS = function_exists('ems_profile_layer') ? array_keys(ems_profile_layer($conn, 'action', $uid)) : array();
$ACT_BY_SEQ = \App\Services\Finance\ApprovalGate::ACTION_BY_SEQ;

$PAGE_TITLE = 'تسجيل قرار الاعتماد';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
$CSRF = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-stamp';
  $header_desc = 'الانواع الاربعة لا يغني احدها عن الاخر. القرار يمر ببوابة الاعتماد وحدها: ترتيب ثم تعارض ثم دور ثم فعل القالب ثم سقف.';
  $header_back = array('href' => 'acc_approval_chain.php', 'icon' => 'fas fa-arrow-right', 'label' => 'سلسلة الاعتماد');
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php if ($flash !== null): ?>
    <div class="alert alert-<?php echo $h($flashKind); ?>" role="status"><?php echo $h($flash); ?></div>
  <?php endif; ?>

  <div class="ems-card ems-mb-16">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-shield-halved"></i></span>
      ما يحمله قالبك من افعال الاعتماد
    </div>
    <div class="filter-body">
      <?php if (!$MYACTIONS): ?>
        <div class="alert alert-info" role="status">
          قالبك لا يحمل بند فعل اعتماد. وهذا لا يمنعك اليوم اذا لم ينقل هذا النوع الى قالبك بعد،
          لكن ما ان يضم اليه بند فعل واحد حتى تصير محكوما به وحده.
        </div>
      <?php else: ?>
        <div class="table-container">
          <table class="table table-sm" data-no-datatable>
            <thead><tr><th>النوع</th><th>الفعل المعلن</th><th>يحمله قالبك؟</th></tr></thead>
            <tbody>
              <?php foreach ($TYPES as $t):
                    $a = $ACT_BY_SEQ[(int) $t['seq']] ?? null;
                    $has = $a !== null && in_array($a, $MYACTIONS, true); ?>
                <tr>
                  <td><?php echo $h($t['code'] . ' · ' . $t['title']); ?></td>
                  <td><code><?php echo $h($a ?? 'لا فعل معلن'); ?></code></td>
                  <td><?php echo $has ? 'نعم' : 'لا'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="ems-card ems-mb-16">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-file-signature"></i></span>
      سجل قرارا على مستند
    </div>
    <div class="filter-body">
      <form method="post" class="ems-form">
        <input type="hidden" name="csrf_token" value="<?php echo $CSRF; ?>">
        <input type="hidden" name="action" value="record">
        <div class="form-group px-w-320">
          <label for="source_kind">نوع المستند</label>
          <input type="text" id="source_kind" name="source_kind" class="form-control" required
                 maxlength="40" placeholder="مثال: fin_request">
        </div>
        <div class="form-group px-w-320">
          <label for="source_ref">رقم المستند</label>
          <input type="text" id="source_ref" name="source_ref" class="form-control" required
                 maxlength="60" placeholder="مثال: FR-2026-0091">
        </div>
        <div class="form-group px-w-320">
          <label for="apr_code">نوع الاعتماد</label>
          <select id="apr_code" name="apr_code" class="form-control" required>
            <?php foreach ($TYPES as $t): ?>
              <option value="<?php echo $h($t['code']); ?>">
                <?php echo $h($t['code'] . ' · ' . $t['title'] . ' (' . $t['owner_label'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="decision">القرار</label>
          <select id="decision" name="decision" class="form-control">
            <option value="approved">اعتماد</option>
            <option value="rejected">رفض</option>
            <option value="escalated">تصعيد</option>
          </select>
        </div>
        <div class="form-group px-w-320">
          <label for="amount">المبلغ (يلزم حيث يقاس على السقف)</label>
          <input type="number" step="0.01" id="amount" name="amount" class="form-control">
        </div>
        <div class="form-group px-w-320">
          <label for="currency">العملة</label>
          <input type="text" id="currency" name="currency" class="form-control" value="USD" maxlength="8">
        </div>
        <div class="form-group px-w-320">
          <label for="note">ملاحظة</label>
          <input type="text" id="note" name="note" class="form-control" maxlength="400">
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa fa-stamp"></i> سجل القرار</button>
      </form>
      <p class="text-muted">
        البوابة ترد الطلب بسببه المسمى: نوع سابق لم يعتمد، او الشخص نفسه جمع نوعين متعارضين،
        او الدور ليس صاحب النوع، او القالب لا يحمل الفعل، او المبلغ فوق السقف.
        وسجل الانواع يحمل صفا خامسا رمزه FIN_-000 حقل ادواره نص حر لا ارقام، فاستبعد من
        القائمة لانه بقية استيراد لا نوع اعتماد.
      </p>
    </div>
  </div>

  <div class="ems-card">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa fa-list-ol"></i></span>
      اخر القرارات المسجلة
    </div>
    <div class="filter-body">
      <?php if (!$CHAIN || $CHAIN->num_rows === 0): ?>
        <?php echo ems_state('empty', 'لا قرار مسجلا بعد', 'سجل اول قرار من النموذج اعلاه'); ?>
      <?php else: ?>
        <div class="table-container">
          <table class="table table-striped">
            <thead><tr><th>متى</th><th>المستند</th><th>النوع</th><th>القرار</th><th>الفاعل</th><th>المبلغ</th></tr></thead>
            <tbody>
              <?php while ($c = $CHAIN->fetch_assoc()): ?>
                <tr>
                  <td dir="ltr"><?php echo $h($c['decided_at']); ?></td>
                  <td><?php echo $h($c['source_kind'] . ' / ' . $c['source_ref']); ?></td>
                  <td><code><?php echo $h($c['apr_code']); ?></code></td>
                  <td><?php echo $h($c['decision']); ?></td>
                  <td><?php echo $h($c['actor_name'] ?: ('#' . $c['actor_user_id'])); ?>
                      <small class="text-muted"><?php echo $h($c['actor_capacity']); ?></small></td>
                  <td dir="ltr"><?php echo $c['amount'] !== null ? $h(number_format((float) $c['amount'], 2) . ' ' . $c['currency']) : ''; ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
/**
 * Governance/authority_caps.php — حدودُ المبالغِ التسعة (قرارُ المالكِ ⑥ 2026-08-18)
 * ───────────────────────────────────────────────────────────────────────────
 * «شاشةٌ واحدةٌ لحدودِ المبالغِ تعرض السقوفَ التسعةَ كلَّها بخانةٍ قابلةٍ
 *  للتحريرِ لكلِّ سقف — ولا يُحذف القديمُ بل يُحفظ في السجلِّ مع من غيّره
 *  ومتى ولماذا — والتعديلُ باعتمادِ المالكِ وحدَه — ويسري على الجديدِ فقط».
 * القادحُ trg_cap_owner_only يفرض المالكَ (الدور 9) بنيويًّا لا شاشةً.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/ux_components.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../includes/audit_trail.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role           = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/authority_caps.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}
ems_shell_axes($__pp);

// ═══ ③-ب التعديل — باعتمادِ المالكِ وحدَه (والقادحُ خلفَ الشاشةِ يفرضه بنيويًّا) ═══
/* AC-F2: حارسُ الكتابةِ المركزيُّ **قبلَ** أولِ عبارةِ كتابة — fail-closed. */
ems_require_action($conn, $SCREEN, 'write', array('deny_msg' => 'تعديلُ السقوفِ بيدِ المالكِ وحدَه'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lc     = preg_replace('/[^A-Z0-9-]/', '', (string) ($_POST['ladder_code'] ?? ''));
    $amt    = ($_POST['cap_amount'] ?? '') === '' ? null : (float) $_POST['cap_amount'];
    $cur    = preg_replace('/[^A-Z]/', '', strtoupper((string) ($_POST['cap_currency'] ?? 'USD')));
    if ($cur === '') { $cur = 'USD'; }
    $eff    = (string) ($_POST['effective_from'] ?? date('Y-m-d H:i'));
    $eff    = str_replace('T', ' ', $eff);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $ok = false; $msg = '';
    if ($role !== 9 && !$is_super_admin) {
        $msg = 'تعديلُ السقفِ باعتمادِ المالكِ وحدَه (الإدارةُ التنفيذية)';
    } elseif ($lc === '' || $reason === '') {
        $msg = 'السلّمُ والسببُ إلزاميانِ — السجلُّ يُقرأ بعدَ سنة';
    } else {
        $st = $conn->prepare(
            "INSERT INTO gov_cap_history (ladder_code, cap_amount, cap_currency, effective_from, changed_by, reason)
             VALUES (?, ?, ?, ?, ?, ?)");
        $st->bind_param('sdssis', $lc, $amt, $cur, $eff, $uid, $reason);
        if ($st->execute()) {
            // السقفُ النافذُ للسلاليمِ المبنيّةِ يُحدَّث — والقديمُ محفوظٌ في السجل
            $state = $amt === null ? 'unresolved' : 'resolved';
            $upd = $conn->prepare(
                "UPDATE gov_ladders SET cap_amount = ?, cap_currency = ?, cap_state = ?
                  WHERE ladder_code = ? AND cap_kind = 'amount'");
            $upd->bind_param('dsss', $amt, $cur, $state, $lc);
            $upd->execute();
            $upd->close();
            ems_audit_change($conn, 'governance', $SCREEN, 'cap.change', 0,
                array(), array('ladder' => $lc, 'amount' => $amt, 'currency' => $cur, 'reason' => $reason));
            $ok = true; $msg = "سُجِّل سقفُ {$lc} الجديدُ — يسري على المعاملاتِ الجديدةِ فقط";
        } else {
            $msg = ($conn->errno === 1644)
                 ? 'رفضه القيدُ: تعديلُ السقفِ باعتمادِ المالكِ وحدَه'
                 : 'تعذَّر: ' . $st->error;
        }
        $st->close();
    }
    $_SESSION['caps_flash'] = array('ok' => $ok, 'msg' => $msg);
    header('Location: authority_caps.php');
    exit();
}
$FLASH = isset($_SESSION['caps_flash']) ? $_SESSION['caps_flash'] : null;
unset($_SESSION['caps_flash']);

// ═══ ④ العرض ═══
$LADDER_NAMES = array(
    'LD-05' => 'الاعتمادُ الماليُّ الأوليّ', 'LD-06' => 'إصدارُ المستخلصِ والفاتورة',
    'LD-07' => 'الاعتمادُ الماليُّ النهائيّ', 'LD-08' => 'طلبُ الدفع', 'LD-09' => 'الخزينة',
    'LD-10' => 'طلبُ الشراء', 'LD-11' => 'أمرُ الشراء', 'LD-12' => 'الاستلام', 'LD-13' => 'التسويةُ النهائية',
);
$current = array();
$r = $conn->query(
    "SELECT h.ladder_code, h.cap_amount, h.cap_currency, h.effective_from, h.reason, u.username changed_by_name
       FROM gov_cap_history h
       JOIN (SELECT ladder_code, MAX(id) mid FROM gov_cap_history
              WHERE effective_from <= NOW() GROUP BY ladder_code) x
         ON x.ladder_code = h.ladder_code AND x.mid = h.id
       LEFT JOIN users u ON u.id = h.changed_by
      ORDER BY h.ladder_code");
while ($x = $r->fetch_assoc()) { $current[$x['ladder_code']] = $x; }
$built = array();
$r = $conn->query("SELECT ladder_code FROM gov_ladders WHERE cap_kind = 'amount'");
while ($x = $r->fetch_row()) { $built[$x[0]] = true; }
$hist = $conn->query(
    "SELECT h.ladder_code, h.cap_amount, h.cap_currency, h.effective_from, h.reason,
            u.username changed_by_name, h.created_at
       FROM gov_cap_history h LEFT JOIN users u ON u.id = h.changed_by
      ORDER BY h.id DESC LIMIT 200");

$PAGE_TITLE = 'حدود المبالغ';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = 'حدودُ المبالغِ التسعة';
  $header_icon = 'fa fa-scale-balanced';
  $header_desc = 'السقوفُ مرنةٌ من هذه الشاشةِ بلا تعديلٍ برمجيّ: القديمُ لا يُحذف بل يُحفظ بمن غيَّره ومتى ولماذا · والتعديلُ باعتمادِ المالكِ وحدَه · ويسري على المعاملاتِ الجديدةِ فقط. وغيرُ المحسومِ يوقف المعاملةَ ببطاقةِ «بانتظارِ اعتمادِ السقف» لا كعطل.';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <?php echo ems_states_bundle('لا سقوفَ مسجَّلةً بعد', 'تُبذر من هجرةِ السقوفِ ثم تُدار من هنا'); ?>

  <?php if ($FLASH !== null): ?>
    <div class="<?php echo $FLASH['ok'] ? 'ems-state-readonly' : 'ems-state-noperm'; ?> ems-state" role="status">
      <?php echo htmlspecialchars($FLASH['msg'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <div class="card"><div class="card-body">
    <table class="table" id="capsTable" data-no-dt="hard">
      <thead><tr>
        <th>السلّم</th><th>اسمُه</th><th>السقفُ الساري</th><th>العملة</th><th>سريانُه منذ</th>
        <th>آخرُ من غيَّره</th><th>الحال</th>
        <?php if ($role === 9 || $is_super_admin): ?><th>تعديل</th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($LADDER_NAMES as $lc => $nm):
          $c = isset($current[$lc]) ? $current[$lc] : null;
          $isBuilt = isset($built[$lc]); ?>
        <tr>
          <td class="rtl-number"><?php echo htmlspecialchars($lc); ?></td>
          <td><?php echo htmlspecialchars($nm); ?></td>
          <td class="rtl-number"><?php echo $c !== null && $c['cap_amount'] !== null
              ? number_format((float) $c['cap_amount'], 2) : '—'; ?></td>
          <td><?php echo htmlspecialchars(isset($c['cap_currency']) ? $c['cap_currency'] : 'USD'); ?></td>
          <td class="rtl-number"><?php echo htmlspecialchars(isset($c['effective_from']) ? $c['effective_from'] : '—'); ?></td>
          <td><?php echo htmlspecialchars(isset($c['changed_by_name']) && $c['changed_by_name'] !== null ? $c['changed_by_name'] : '—'); ?></td>
          <td><?php
              if (!$isBuilt) { echo '<span class="status-badge status-review">سلّمُه لم يُبنَ — يُعرض معَ بنائِه</span>'; }
              elseif ($c === null || $c['cap_amount'] === null) { echo '<span class="status-badge status-pending">بانتظارِ اعتمادِ السقف — وقفٌ آمنٌ لا عطل</span>'; }
              else { echo '<span class="status-badge status-active">ساري</span>'; }
          ?></td>
          <?php if ($role === 9 || $is_super_admin): ?>
          <td>
            <form method="post" class="caps-edit-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="ladder_code" value="<?php echo htmlspecialchars($lc); ?>">
              <input type="number" step="0.01" min="0" name="cap_amount" aria-label="السقفُ الجديدُ لهذا السلّم"
                     value="<?php echo $c !== null && $c['cap_amount'] !== null ? htmlspecialchars((string) $c['cap_amount']) : ''; ?>">
              <input type="text" name="cap_currency" maxlength="8" aria-label="العملة" value="<?php echo htmlspecialchars(isset($c['cap_currency']) ? $c['cap_currency'] : 'USD'); ?>" class="caps-cur">
              <input type="datetime-local" name="effective_from" aria-label="تاريخُ السريان" value="<?php echo date('Y-m-d') . 'T' . date('H:i'); ?>">
              <input type="text" name="reason" required maxlength="300" aria-label="سببُ التغيير — إلزامي" placeholder="لماذا — يُقرأ بعدَ سنة">
              <button type="submit" class="btn btn-primary btn-sm">اعتماد</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>

  <div class="card"><div class="card-body">
    <h3 class="caps-hist-title">السجلُّ الكامل — لا يُحذف قديم</h3>
    <table class="table" id="capsHistTable" data-no-dt="hard">
      <thead><tr><th>السلّم</th><th>المبلغ</th><th>العملة</th><th>سريانُه</th><th>من</th><th>لماذا</th><th>سُجِّل</th></tr></thead>
      <tbody>
      <?php while ($h = $hist->fetch_assoc()): ?>
        <tr>
          <td class="rtl-number"><?php echo htmlspecialchars($h['ladder_code']); ?></td>
          <td class="rtl-number"><?php echo $h['cap_amount'] !== null ? number_format((float) $h['cap_amount'], 2) : '—'; ?></td>
          <td><?php echo htmlspecialchars($h['cap_currency']); ?></td>
          <td class="rtl-number"><?php echo htmlspecialchars($h['effective_from']); ?></td>
          <td><?php echo htmlspecialchars($h['changed_by_name'] !== null ? $h['changed_by_name'] : '—'); ?></td>
          <td><?php echo htmlspecialchars($h['reason']); ?></td>
          <td class="rtl-number"><?php echo htmlspecialchars($h['created_at']); ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div></div>

  <style>
    .caps-edit-form { display: flex; flex-wrap: wrap; gap: var(--space-1); align-items: center; }
    .caps-edit-form input[type="number"] { width: 110px; }
    .caps-edit-form .caps-cur { width: 64px; }
    .caps-edit-form input[name="reason"] { min-width: 200px; }
    .caps-hist-title { font-size: var(--text-h3); margin-block-end: var(--space-3); }
  </style>
</div>


<?php
/**
 * Governance/tech_gov_center.php — مركزُ الحوكمةِ التقني: الروابطُ اليتيمة
 * ───────────────────────────────────────────────────────────────────────────
 * UXW-01 §8 + أمرُ المالك: وسومُ حوكمةِ المشروعِ ليست لغةَ منتجٍ —
 * فروابطُها نُقلت إلى هنا بقرارٍ معلَّقٍ (gov_orphan_links)
 * حتى يقرِّر المالكُ صفًّا صفًّا: يعتمد موضعَها المقترحَ أو ينقلها أو يتقاعدها.
 *
 * ◆ الترتيبُ الملزم: ① جلسة → ② إعداد → ③ حارسُ شاشة → ④ حارسُ فعل
 *   → ⑤ رمزُ حماية → ⑥ معالجُ POST → ⑦ العرض
 * ◆ القرارُ يُسجَّل هنا ولا يُنفَّذ آليًّا: تفعيلُ الرابطِ في قائمتِه فعلُ
 *   هجرةٍ لاحقةٍ يقيسه فاحصُ التنقل — فلا تكتب هذه الشاشةُ في nav_items.
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
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/tech_gov_center.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}

// ═══ ④ حارسُ الفعل ═══
$__canDecide = $is_super_admin || !empty($__pp['can_edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$__canDecide) {
    http_response_code(403);
    exit('غير مصرَّحٍ بتسجيلِ قرارٍ في هذه الشاشة — اطلبِ المنحةَ من مدير الصلاحيات');
}

// ═══ ⑤ رمزُ الحماية ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('رمزُ الحمايةِ غيرُ صالح — أعدْ تحميلَ الصفحة');
    }
}

// ═══ ⑥ معالجُ POST — تسجيلُ قرارِ المالكِ على صفٍّ واحد ═══
$flash = null; $flashKind = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_decision') {
    $oid = (int) ($_POST['orphan_id'] ?? 0);
    $dec = (string) ($_POST['decision'] ?? '');
    $allowed = array('approved', 'relocated', 'retired', 'pending');
    if ($oid > 0 && in_array($dec, $allowed, true)) {
        $st = $conn->prepare("UPDATE gov_orphan_links
                                 SET owner_decision=?, decided_at=IF(?='pending', NULL, NOW())
                               WHERE id=?");
        $st->bind_param('ssi', $dec, $dec, $oid);
        if ($st->execute() && $st->affected_rows > 0) {
            $flash = 'سُجِّل القرارُ — والتفعيلُ في القائمةِ فعلُ هجرةٍ لاحقةٍ مقيس';
            $flashKind = 'success';
        } else {
            $flash = 'لم يتغير شيء — تحقق من الصفِّ المختار';
            $flashKind = 'warning';
        }
        $st->close();
    } else {
        $flash = 'قرارٌ غيرُ معروف — الخياراتُ: اعتمادُ المقترحِ · نقلٌ · تقاعدٌ · إرجاءٌ';
        $flashKind = 'danger';
    }
}

// ═══ ⑦ العرض ═══
$g = $conn->query(
    "SELECT COUNT(*) total,
            SUM(owner_decision='pending')   pend,
            SUM(owner_decision='approved')  ok,
            SUM(owner_decision='relocated') moved,
            SUM(owner_decision='retired')   ret
       FROM gov_orphan_links"
)->fetch_assoc();
$rows = $conn->query(
    "SELECT id, sheet_role, label_ar, route, live_class, proposed_group, owner_decision, decided_at
       FROM gov_orphan_links
      ORDER BY owner_decision='pending' DESC, sheet_role, label_ar"
);

$DECISION_AR = array(
    'pending'   => 'بانتظارِ قرارِ المالك',
    'approved'  => 'اعتُمد المقترح',
    'relocated' => 'يُنقل لموضعٍ آخر',
    'retired'   => 'يتقاعد',
);

$PAGE_TITLE = 'مركز الحوكمة التقني — الروابط اليتيمة';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-scale-balanced';
  $header_desc = 'روابطُ التنقلِ التي أخرجها الدفترُ الحاكمُ من واجهةِ التشغيل — محفوظةٌ هنا بقرارٍ معلَّقٍ حتى يبتَّ المالكُ صفًّا صفًّا. التسجيلُ هنا قرارٌ لا تنفيذ.';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  echo ems_next_step('قرارُ المالكِ صفًّا صفًّا ثم هجرةُ إعادةِ الإسناد');
  ?>

  <?php if ($flash !== null): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashKind, ENT_QUOTES, 'UTF-8'); ?>" role="status">
      <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col"><div class="kpi-card"><div>الكل</div><strong><?php echo (int) $g['total']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>بانتظارُ القرار</div><strong><?php echo (int) $g['pend']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>اعتُمد المقترح</div><strong><?php echo (int) $g['ok']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>يُنقل</div><strong><?php echo (int) $g['moved']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>يتقاعد</div><strong><?php echo (int) $g['ret']; ?></strong></div></div>
  </div>

  <?php echo ems_states_bundle('لا روابطَ يتيمةً مسجَّلة', 'اكتمل البتُّ فيها كلِّها أو لم تُبذَر الورقةُ بعد'); ?>

  <?php if ($rows !== false && $rows->num_rows > 0): ?>
  <div class="table-responsive">
    <table class="table" id="orphanLinksTable">
      <thead>
        <tr>
          <th>القائمةُ في الدفتر</th>
          <th>الرابط</th>
          <th>المسار</th>
          <th>التصنيفُ الحي</th>
          <th>المجموعةُ المقترحة</th>
          <th>قرارُ المالك</th>
          <?php if ($__canDecide): ?><th>تسجيلُ القرار</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['sheet_role'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r['label_ar'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><code><?php echo htmlspecialchars($r['route'], ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td><?php echo htmlspecialchars($r['live_class'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r['proposed_group'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <span class="status-badge <?php echo $r['owner_decision'] === 'pending' ? 'status-pending' : 'status-active'; ?>">
              <?php echo htmlspecialchars($DECISION_AR[$r['owner_decision']] ?? $r['owner_decision'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </td>
          <?php if ($__canDecide): ?>
          <td>
            <form method="post" class="ems-inline-form">
              <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
              <input type="hidden" name="action" value="record_decision">
              <input type="hidden" name="orphan_id" value="<?php echo (int) $r['id']; ?>">
              <label class="ems-visually-hidden" for="dec<?php echo (int) $r['id']; ?>">قرارُ هذا الرابط</label>
              <select name="decision" id="dec<?php echo (int) $r['id']; ?>">
                <option value="approved">اعتمادُ المقترح</option>
                <option value="relocated">نقلٌ لموضعٍ آخر</option>
                <option value="retired">تقاعد</option>
                <option value="pending">إرجاء</option>
              </select>
              <button type="submit" class="btn btn-sm btn-primary">سجِّل</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <?php echo ems_state('empty', 'لا روابطَ يتيمةً مسجَّلة', 'اكتمل البتُّ فيها كلِّها أو لم تُبذَر الورقةُ بعد'); ?>
  <?php endif; ?>
</div>

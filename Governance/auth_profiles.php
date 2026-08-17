<?php
/**
 * Governance/auth_profiles.php — قوالبُ الصلاحياتِ المعيارية (GOV-AUTH-01 §5)
 * ───────────────────────────────────────────────────────────────────────────
 * الشاشةُ الأولى من ثلاثِ الخطوةِ الخامسة. عرضٌ رقابيٌّ: 171 قالبًا من ورقةِ
 * الدفترِ بحالتِها وبنودِها المزروعةِ من الحيِّ — ولا تعديلَ هنا: «لا يُعدَّل
 * قالبٌ نافذٌ في مكانِه بل يُصدَر إصدارٌ جديدٌ ويُرحَّل حاملوه».
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
$SCREEN         = 'Governance/auth_profiles.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(405); exit('شاشةُ عرضٍ لا تكتب — التعديلُ إصدارٌ جديدٌ بمسارِ الحوكمة'); }

// ═══ ④ العرض ═══
$g = $conn->query(
    "SELECT COUNT(*) total,
            SUM(state='draft') drafts,
            SUM(state='active') actives,
            SUM(EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id = p.profile_id)) seeded
       FROM gov_role_profiles p")->fetch_assoc();
$rows = $conn->query(
    "SELECT p.profile_code, p.dept_code, p.grade, p.title_ar, p.screens_target,
            p.approval_cap_label, p.state, p.version,
            (SELECT COUNT(*) FROM gov_profile_items i WHERE i.profile_id = p.profile_id) items_n
       FROM gov_role_profiles p
      ORDER BY p.dept_code, p.grade");

$PAGE_TITLE = 'قوالب الصلاحيات المعيارية';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  $header_title = $PAGE_TITLE;
  $header_icon = 'fa fa-id-card';
  $header_desc = 'تسعُ درجاتٍ في كلِّ إدارةٍ — رؤوسُها من ورقةِ الدفترِ حرفًا وبنودُها المزروعةُ من الصلاحياتِ الحية. كلُّها بحالةِ مسودةٍ حتى اعتمادِ تقريرِ الفروق — والتعديلُ إصدارٌ جديدٌ لا مساسٌ بالنافذ.';
  $header_back = false;
  include __DIR__ . '/../includes/page_header.php';
  ?>

  <div class="row">
    <div class="col"><div class="kpi-card"><div>القوالب</div><strong><?php echo (int) $g['total']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>مسودة</div><strong><?php echo (int) $g['drafts']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>نافذة</div><strong><?php echo (int) $g['actives']; ?></strong></div></div>
    <div class="col"><div class="kpi-card"><div>مبذورةُ البنود</div><strong><?php echo (int) $g['seeded']; ?></strong></div></div>
  </div>

  <?php echo ems_states_bundle('لا قوالبَ مبذورة', 'شغِّل هجرةَ GOV-AUTH-01 لبذرِ الورقة'); ?>

  <?php if ($rows !== false && $rows->num_rows > 0): ?>
  <div class="table-responsive">
    <table class="table" id="authProfilesTable">
      <thead><tr>
        <th>الرمز</th><th>الإدارة</th><th>الدرجة</th><th>المسمَّى</th>
        <th>شاشاتُ الهدف</th><th>البنودُ المزروعة</th><th>سقفُ الاعتماد</th><th>الإصدار</th><th>الحالة</th>
      </tr></thead>
      <tbody>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><code><?php echo htmlspecialchars($r['profile_code'], ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td><?php echo htmlspecialchars($r['dept_code'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r['grade'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r['title_ar'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo (int) $r['screens_target']; ?></td>
          <td><?php echo (int) $r['items_n']; ?></td>
          <td><?php echo htmlspecialchars($r['approval_cap_label'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td>v<?php echo (int) $r['version']; ?></td>
          <td><span class="status-badge <?php echo $r['state'] === 'active' ? 'status-active' : 'status-pending'; ?>">
            <?php echo $r['state'] === 'active' ? 'نافذ' : ($r['state'] === 'retired' ? 'متقاعد' : 'مسودةٌ — بانتظارِ اعتمادِ الفروق'); ?>
          </span></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <?php echo ems_state('empty', 'لا قوالبَ مبذورة', 'شغِّل هجرةَ GOV-AUTH-01 لبذرِ الورقة'); ?>
  <?php endif; ?>
</div>

<?php
/**
 * Governance/read_log.php — سجلُّ الاطّلاعِ على الحقولِ الحساسة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0247
 *
 * **المعيارُ نصًّا** (M-14 · TABLE 53 · SCN-723-أ):
 *   «سجلُّ الاطّلاعِ الحساس | المستخدمُ · الوقتُ · الحقلُ · الشاشةُ · سببُ
 *    الاطّلاع» — و«كلُّ قراءةٍ لبياناتِ ملكيةٍ أو راتبٍ أو حسابٍ بنكيٍّ تُسجَّل
 *    بالمستخدم والوقت والسجل».
 *
 * والجدولُ `sensitive_read_log` **يُكتب سلفًا** من الحرّاس (`SensitiveFieldGuard`
 * · `ConfidentialityGuard` · حارسُ الملكية · `includes/sensitive_read_log.php`)
 * — وكان **بلا عارضٍ**: يُكتب ولا يُقرأ. فالأثرُ الذي لا يُراجَع ليس أثرًا.
 *
 * ── قراءةٌ محضة ─────────────────────────────────────────────────────────────
 * لا فعلَ كاتبًا في هذه الشاشة: السجلُّ يُكتب من الحرّاسِ لحظةَ الاطّلاعِ ولا
 * يُصحَّح من هنا — فسجلُّ تدقيقٍ قابلٌ للتحرير من شاشتِه ليس سجلَّ تدقيق.
 *
 * ◆ والنطاقُ نطاقُ الشركةِ حصرًا (`company_id`) — ويستثنى السوبر.
 * ◆ و«المرفوض» يُعرض كما يُعرض «المسموح»: محاولةُ اطّلاعٍ مردودةٌ **حدثٌ
 *   حوكميٌّ** لا فراغ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Governance/read_log.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض سجل الاطلاع الحساس', 'GOV-PERM-403', 'سجل الاطلاع للحوكمة والمراجعة');
}
ems_shell_axes($__pp);

/* ── المرشّحات: مدًى زمنيٌّ · النتيجةُ · الحقل ─────────────────────────────── */
$from   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
$result = in_array(($_GET['result'] ?? ''), array('allowed', 'denied'), true) ? $_GET['result'] : '';
$elem   = trim((string) ($_GET['element'] ?? ''));

$where = array();
$types = ''; $vals = array();
if (!$is_super_admin) { $where[] = 'l.company_id = ?'; $types .= 'i'; $vals[] = $company_id; }
$where[] = 'DATE(l.`at`) BETWEEN ? AND ?'; $types .= 'ss'; $vals[] = $from; $vals[] = $to;
if ($result !== '') { $where[] = 'l.result = ?'; $types .= 's'; $vals[] = $result; }
if ($elem !== '')   { $where[] = 'l.element_code LIKE ?'; $types .= 's'; $vals[] = '%' . $elem . '%'; }
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ◆ الاستعلامُ الفاشلُ يُميَّز عن «لا صفوف» — و`config.php` يضبط mysqli على عدمِ
     الرمي، فجدولٌ ناقصُ عمودٍ يعود `false` صامتًا ويُقرأ «السجلُّ خالٍ». */
$rows = array(); $queryFailed = false;
$sql = "SELECT l.read_id, l.person_id, l.element_code, l.subject_type, l.subject_id,
               l.`at`, l.ip, l.result, l.grant_ref, l.context,
               u.name AS person_name, u.username, r.name AS role_name
          FROM sensitive_read_log l
          LEFT JOIN users u ON u.id = l.person_id
          LEFT JOIN roles r ON r.id = u.role
          {$sqlWhere}
         ORDER BY l.read_id DESC
         LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) {
    $queryFailed = true;
} else {
    if ($types !== '') { $st->bind_param($types, ...$vals); }
    if (!$st->execute()) { $queryFailed = true; }
    else {
        $res = $st->get_result();
        while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; }
    }
    $st->close();
}

/* ── عدّاداتُ الرأس — على المدى نفسِه ─────────────────────────────────────── */
$cnt = array('allowed' => 0, 'denied' => 0, 'people' => 0, 'elements' => 0);
if (!$queryFailed) {
    $c2 = $conn->prepare("SELECT l.result, COUNT(*) n, COUNT(DISTINCT l.person_id) p,
                                 COUNT(DISTINCT l.element_code) e
                            FROM sensitive_read_log l {$sqlWhere} GROUP BY l.result");
    if ($c2) {
        if ($types !== '') { $c2->bind_param($types, ...$vals); }
        if ($c2->execute()) {
            $r2 = $c2->get_result();
            while ($r2 && ($x = $r2->fetch_assoc())) {
                $cnt[$x['result']] = (int) $x['n'];
                $cnt['people']   = max($cnt['people'], (int) $x['p']);
                $cnt['elements'] = max($cnt['elements'], (int) $x['e']);
            }
        }
        $c2->close();
    }
}

$page_title = 'سجل الاطلاع على الحقول الحساسة';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-eye';
$header_title_html = htmlspecialchars('سجل الاطلاع على الحقول الحساسة', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا اطلاع على حقل حساس مسجل في المدى المختار', 'وسع المدى الزمني من حقلي «من» و«إلى» ثم اضغط «تصفية»');
?>

<style>
/* UXW-01 ②: أنماطُ الشاشةِ الموضعيةُ نُقلت أصنافًا — سجلُّ الاطّلاعِ الحساس */
.gov-rl-alert   { margin: 10px 0; }
.gov-rl-kpis    { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
.gov-rl-kpi     { padding: 10px 14px; }
.gov-rl-kpi-ok     { border-inline-start: 4px solid var(--c-198754); }
.gov-rl-kpi-denied { border-inline-start: 4px solid var(--c-dc3545); }
.gov-rl-kpi-people { border-inline-start: 4px solid var(--c-0d6efd); }
.gov-rl-kpi-elems  { border-inline-start: 4px solid var(--c-6f42c1); }
.gov-rl-kpi-label { font-size: .78rem; opacity: .75; }
.gov-rl-kpi-val   { font-size: 1.4rem; font-weight: 700; }
.gov-rl-filter  { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin: 10px 0; }
.gov-rl-fg      { margin: 0; }
.gov-rl-table   { width: 100%; }
.gov-rl-empty   { text-align: center; opacity: .7; }
.gov-rl-nowrap  { white-space: nowrap; }
.gov-rl-badge-allowed { background: var(--c-198754); }
.gov-rl-badge-denied  { background: var(--c-dc3545); }
.gov-rl-note    { font-size: .8rem; margin-top: 8px; }
</style>

  <?php if ($queryFailed): ?>
  <div class="alert alert-danger gov-rl-alert">
    <strong>تعذر قراءة السجل.</strong>
    فرق بين «لا اطلاع وقع» و«تعذر السؤال» — وهذه الثانية. راجع سجل الأخطاء.
  </div>
  <?php else: ?>

  <div class="gov-rl-kpis">
    <div class="ems-card gov-rl-kpi gov-rl-kpi-ok">
      <div class="gov-rl-kpi-label">اطلاع مسموح</div>
      <div class="gov-rl-kpi-val"><?php echo number_format($cnt['allowed']); ?></div>
    </div>
    <div class="ems-card gov-rl-kpi gov-rl-kpi-denied">
      <div class="gov-rl-kpi-label">محاولة مردودة</div>
      <div class="gov-rl-kpi-val"><?php echo number_format($cnt['denied']); ?></div>
    </div>
    <div class="ems-card gov-rl-kpi gov-rl-kpi-people">
      <div class="gov-rl-kpi-label">مطلعون مميزون</div>
      <div class="gov-rl-kpi-val"><?php echo number_format($cnt['people']); ?></div>
    </div>
    <div class="ems-card gov-rl-kpi gov-rl-kpi-elems">
      <div class="gov-rl-kpi-label">حقول مميزة</div>
      <div class="gov-rl-kpi-val"><?php echo number_format($cnt['elements']); ?></div>
    </div>
  </div>

    <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
  <div class="filter">
      <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
      <div class="filter-body">
  <form method="get" class="ems-form gov-rl-filter">
    <div class="form-group gov-rl-fg">
      <label for="rl_from">من</label>
      <input type="date" id="rl_from" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="form-group gov-rl-fg">
      <label for="rl_to">إلى</label>
      <input type="date" id="rl_to" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="form-group gov-rl-fg">
      <label for="rl_res">النتيجة</label>
      <select id="rl_res" name="result" class="form-control form-control-sm">
        <option value="">الكل</option>
        <option value="allowed" <?php echo $result === 'allowed' ? 'selected' : ''; ?>>مسموح</option>
        <option value="denied"  <?php echo $result === 'denied'  ? 'selected' : ''; ?>>مردود</option>
      </select>
    </div>
    <div class="form-group gov-rl-fg">
      <label for="rl_el">الحقل</label>
      <input type="text" id="rl_el" name="element" class="form-control form-control-sm" value="<?php echo htmlspecialchars($elem, ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثلا: salary">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">تصفية</button>
  </form>
      </div>
  </div>

  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped gov-rl-table">
      <thead><tr>
        <th>#</th><th>المستخدم</th><th>الدور</th><th>الوقت</th><th>الحقل</th>
        <th>السجل</th><th>الشاشة / السبب</th><th>النتيجة</th><th>مرجع المنح</th><th>IP</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10" class="gov-rl-empty">
          لا اطلاع مسجل في هذا المدى — والسجل يكتب لحظة فتح حقل حساس فعلا.
        </td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo (int) $x['read_id']; ?></td>
          <td><?php echo htmlspecialchars((string) ($x['person_name'] ?: ('#' . $x['person_id'])), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['role_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td class="gov-rl-nowrap"><?php echo htmlspecialchars((string) $x['at'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><code><?php echo htmlspecialchars((string) $x['element_code'], ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td><?php echo htmlspecialchars((string) $x['subject_type'] . ' #' . $x['subject_id'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['context'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <?php if ((string) $x['result'] === 'denied'): ?>
              <span class="badge gov-rl-badge-denied">مردود</span>
            <?php else: ?>
              <span class="badge gov-rl-badge-allowed">مسموح</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars((string) $x['grant_ref'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td class="gov-rl-nowrap"><?php echo htmlspecialchars((string) $x['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted gov-rl-note">
      قراءة محضة — السجل يكتب من الحراس لحظة الاطلاع ولا يصحح من هنا؛
      فسجل تدقيق قابل للتحرير من شاشته ليس سجل تدقيق.
      وأحدث 500 صف في المدى المختار.
    </p>
  </div></div>

  <?php endif; ?>
</div>

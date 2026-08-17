<?php
/**
 * Operations/sites_board.php — لوحةُ المواقع (NAV-01 v6 §6.2 · update0007 S-01)
 * ───────────────────────────────────────────────────────────────────────────
 * «مديرُ الموقع يرى موقعَه وحدَه — ومديرُ التشغيل يرى المواقعَ كلَّها. ولا
 * يُحلّ هذا بفلترٍ بل بشاشةٍ مصمَّمةٍ للتنقل بين المواقع: كلُّ موقعٍ زرٌّ
 * بحالته، والضغطُ يفتح معداتِه ومشغّليها في مكانهما.»
 *
 * «ولا يُظهر مالكَ المعدة ولا مموّلَها» — المجالُ المقيَّد (FIN-01 §1.1).
 * زرُّ التبديل يفتح **طلبًا** لا فعلًا مباشرًا (§6.3 → S-02).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = !empty($_SESSION['user']['is_super_admin']);
$sel_site   = isset($_GET['site']) ? intval($_GET['site']) : 0;
$cscope     = $is_super ? '1=1' : 'p.company_id = ' . $company_id;

/* أزرارُ المواقع بحالتها: المعداتُ · العاملةُ · الفجوة */
$sites = array();
$sql = "SELECT p.id, p.name,
               COUNT(DISTINCT oc.equipment_id) AS eq_total,
               SUM(CASE WHEN e.status = 1 THEN 1 ELSE 0 END) AS eq_active,
               COALESCE(SUM(CASE WHEN oc.seat_no IS NOT NULL AND oc.equipment_id IS NULL THEN 1 ELSE 0 END), 0) AS seat_gap
        FROM project p
        LEFT JOIN op_containers oc ON oc.project_id = p.id AND oc.is_deleted = 0
        LEFT JOIN equipments e ON e.id = oc.equipment_id
        WHERE $cscope
        GROUP BY p.id, p.name ORDER BY p.id";
$res = mysqli_query($conn, $sql);
if ($res) { while ($x = mysqli_fetch_assoc($res)) $sites[] = $x; }

/* معداتُ الموقع المفتوح بمشغّلي وردياتها */
$rows = array();
if ($sel_site > 0) {
    $sql2 = "SELECT e.id AS eq_id, e.name AS eq_name, e.status,
                    oc.seat_no, oc.shift_no,
                    emp.name AS operator_name
             FROM op_containers oc
             JOIN equipments e ON e.id = oc.equipment_id
             LEFT JOIN employees emp ON emp.id = oc.operator_employee_id
             WHERE oc.project_id = $sel_site AND oc.is_deleted = 0 AND oc.equipment_id IS NOT NULL
             ORDER BY e.id, oc.shift_no";
    $r2 = mysqli_query($conn, $sql2);
    if ($r2) {
        while ($x = mysqli_fetch_assoc($r2)) {
            $k = $x['eq_id'];
            if (!isset($rows[$k])) $rows[$k] = array('name' => $x['eq_name'], 'status' => $x['status'], 'shifts' => array());
            $rows[$k]['shifts'][intval($x['shift_no'] ?: 1)] = $x['operator_name'];
        }
    }
}

$page_title = 'لوحة المواقع';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01: أنماطُ الشاشةِ في كتلةٍ واحدة — لا نمطَ موضعيًّا ولا لونَ خارجَ الرموز */
.ems-sites-tiles { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.ems-site-tile { display:block; min-width:150px; padding:10px 14px; border:1px solid var(--c-dee2e6); border-radius:8px; background:var(--c-f8f9fa); color:var(--c-1f2937); text-decoration:none; }
.ems-site-tile.is-active { background:var(--c-0d6efd); color:var(--c-s-fff); }
.ems-row-stopped { background:var(--c-fff3f4); }
.ems-badge-stopped { background:var(--c-dc3545); }
.ems-badge-working { background:var(--c-198754); }
.ems-sites-note { font-size:.85em; }
</style>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-map';
$header_title_html = htmlspecialchars('لوحةُ المواقع', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
echo ems_states_bundle('لا مواقعَ مسجَّلةً لهذا الكيان', 'تحقق من تسجيلِ المشاريعِ أو من نطاقِ صلاحيتك');
?>

  <div class="ems-sites-tiles">
    <?php foreach ($sites as $s):
        $ok  = intval($s['eq_active']); $tot = intval($s['eq_total']); $gap = intval($s['seat_gap']);
        $mark = ($gap > 0 || $ok < $tot) ? '⚠' : '✔';
    ?>
    <a href="?site=<?= intval($s['id']) ?>" class="ems-site-tile<?= $s['id'] == $sel_site ? ' is-active' : '' ?>">
      <strong><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
      <?= $tot ?> معدة<br>
      <?= $mark ?> <?= $ok ?> عاملة<br>
      فجوة: <?= $gap ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($sel_site > 0): ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>المعدة</th><th>الحالة</th><th>المشغّل (وردية ١)</th><th>المشغّل (وردية ٢)</th><th>إجراء</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="text-center text-muted">لا معداتَ مخصَّصةً لهذا الموقع</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $eqId => $r):
        $stopped = intval($r['status']) !== 1; ?>
      <tr<?= $stopped ? ' class="ems-row-stopped"' : '' ?>>
        <td><a href="../Equipments/equipment_profile.php?id=<?= intval($eqId) ?>"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= $stopped ? '<span class="badge ems-badge-stopped">متوقفة</span>' : '<span class="badge ems-badge-working">عاملة</span>' ?></td>
        <td><?= htmlspecialchars($r['shifts'][1] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['shifts'][2] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <a class="action-btn" href="swap_request.php?site=<?= $sel_site ?>&equipment=<?= intval($eqId) ?>">
            <i class="fa fa-exchange-alt"></i> <?= $stopped ? 'بديل' : 'تبديل' ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="text-muted ems-sites-note">زرُّ التبديل يفتح طلبًا بموافقتين لا فعلًا مباشرًا (NAV-01 §6.3) — ولا يُعرض مالكُ المعدة ولا مموّلُها (المجالُ المقيَّد).</p>
  <?php endif; ?>
</div>

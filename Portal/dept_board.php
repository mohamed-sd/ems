<?php
/**
 * Portal/dept_board.php — ورقة الإدارة فوق محرّك العمل (WFM-MAP-01 · الموجة ١)
 * ───────────────────────────────────────────────────────────────────────────
 * شاشة واحدة معيَّرة للإدارات الـ17 — لا 17 نسخة: تقرأ دور الجلسة ← وحدته
 * التنظيمية من خريطة الـ17 (Tickets/dept_inbox_map) ← تعرض من المحرّك:
 *   مهام أعضائها (work_items) · طلبات بيدها (requests) · إنجازات 30 يومًا
 *   (achievement_records) · المتأخرات · مؤشرات جامعة.
 * العضوية من الهيكل لا من قوائم ثابتة: أدوار الوحدة + نطاق المدير
 * (ems_manager_scope_user_ids — قرار 9: مستوى مباشر + تعمّق).
 * الأدوار الجامعة (التنفيذي 9 · الحوكمة 15 · السوبر) ترى لوحة اكتمال
 * الإدارات كلها وتتعمق بإدارةٍ بنقرة (الورقة 14 من WFM-MAP-01).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/resolve_manager.php';
require_once __DIR__ . '/../Tickets/dept_inbox_map.php';

$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$role           = intval($_SESSION['user']['role'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', ''); exit(); }

$__pp = check_page_permissions($conn, 'Portal/dept_board.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, $role, 'Portal/dept_board.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}

/* ── خريطة الوحدة ← اسم إدارة قاموس الطلبات (request_types.owner_dept) ──── */
function ems_dept_owner_name($unitId)
{
    static $m = array(
        1  => 'إدارة التشغيل',    2  => 'المبيعات والعقود',  3  => 'المالية والخزينة',
        4  => 'التمويل والملكية', 5  => 'إدارة الأسطول',     6  => 'الحوكمة والالتزام',
        7  => 'مركز البلاغات',    8  => 'إدارة الموقع',      9  => 'إدارة الصيانة',
        10 => 'القوى التشغيلية',  11 => 'إدارة المشتريات',   12 => 'إدارة المخازن',
        13 => 'النقل والترحيل',   14 => 'الموارد البشرية',   15 => 'إدارة الموردين',
    );
    return isset($m[intval($unitId)]) ? $m[intval($unitId)] : '';
}

/** أدوار الوحدة من الخريطة المشتركة (الإدارة وحدةٌ لا دور — درس dept_achievement ح-07) */
function ems_dept_roles_of_unit($unitId)
{
    $out = array();
    for ($rid = 1; $rid <= 40; $rid++) {
        if (ems_dept_unit_of_role($rid) === intval($unitId)) { $out[] = $rid; }
    }
    return $out;
}

/** أعضاء الإدارة: مستخدمو أدوار الوحدة النشطون بشركة الجلسة */
function ems_dept_member_ids(mysqli $conn, $companyId, $unitId)
{
    $roles = ems_dept_roles_of_unit($unitId);
    if (!$roles) { return array(); }
    $rin = implode(',', array_map('intval', $roles));
    $co  = intval($companyId);
    $ids = array();
    $r = mysqli_query($conn, "SELECT id FROM users WHERE company_id = {$co} AND role IN ({$rin})
                              AND COALESCE(status,'active') = 'active'");
    while ($r && ($x = mysqli_fetch_row($r))) { $ids[] = intval($x[0]); }
    return $ids;
}

/** مؤشرات إدارةٍ من المحرّك — تُستعمل للوحة الواحدة وللوحة الاكتمال الجامعة */
function ems_dept_engine_kpis(mysqli $conn, $companyId, array $memberIds)
{
    $z = array('members' => count($memberIds), 'open_tasks' => 0, 'overdue_tasks' => 0,
               'live_requests' => 0, 'late_requests' => 0, 'ach_30d' => 0);
    if (!$memberIds) { return $z; }
    $in = implode(',', array_map('intval', $memberIds));
    $co = intval($companyId);
    $q = function ($sql) use ($conn) { $r = mysqli_query($conn, $sql);
        return $r ? intval(mysqli_fetch_row($r)[0]) : 0; };
    $z['open_tasks']    = $q("SELECT COUNT(*) FROM work_items WHERE company_id = {$co}
                              AND assigned_user_id IN ({$in})
                              AND status NOT IN ('closed_accepted','cancelled')");
    $z['overdue_tasks'] = $q("SELECT COUNT(*) FROM work_items WHERE company_id = {$co}
                              AND assigned_user_id IN ({$in})
                              AND (status = 'overdue' OR (due_at < NOW()
                                   AND status IN ('assigned','accepted','in_progress')))");
    $z['live_requests'] = $q("SELECT COUNT(*) FROM requests WHERE company_id = {$co}
                              AND current_holder_user_id IN ({$in})
                              AND status IN ('submitted','routed','in_approval','approved','executing','returned')");
    $z['late_requests'] = $q("SELECT COUNT(*) FROM requests WHERE company_id = {$co}
                              AND current_holder_user_id IN ({$in})
                              AND sla_due_at IS NOT NULL AND sla_due_at < NOW()
                              AND status IN ('submitted','routed','in_approval','approved','executing','returned')");
    $z['ach_30d']       = $q("SELECT COUNT(*) FROM achievement_records WHERE company_id = {$co}
                              AND person_user_id IN ({$in}) AND reversed_at IS NULL
                              AND recognized_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    return $z;
}

/* ── تكليف المدير من ورقته (SRC-01 · م-د): الورقة منبعُ عناصرَ لا عارضة فقط.
   القيود: المكلَّف عضو إدارة المكلِّف نفسها · المحرك يحرس السبعة والمتحقق
   يُحل آليًّا (WF-04) — لا إنشاء حر (WF-01: خارج الإدارة لا يُكلَّف من هنا) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dept_assign') {
    require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';
    $unit0 = ems_dept_unit_of_role($role);
    $members0 = $unit0 > 0 ? ems_dept_member_ids($conn, $company_id, $unit0) : array();
    $to = intval($_POST['to_user'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $deliv = trim((string) ($_POST['deliverable'] ?? ''));
    $dueD  = trim((string) ($_POST['due_date'] ?? ''));
    if (!in_array($to, $members0, true)) {
        $msg = 'المكلَّف ليس من أعضاء إدارتك ❌';
    } else {
        $res = \App\Services\Work\WorkItemService::create($conn, array(
            'company_id' => $company_id, 'source_type' => 'SRC-01',
            'source_ref' => 'DEPT-' . $unit0 . '-' . date('YmdHis'),
            'source_screen' => 'Portal/dept_board.php',
            'owner_user_id' => $uid, 'assigned_user_id' => $to,
            'org_unit_id' => $unit0, 'title' => $title,
            'deliverable' => $deliv !== '' ? $deliv : 'إنجاز التكليف بدليله',
            'priority' => in_array($_POST['priority'] ?? '', array('P0','P1','P2','P3','P4'), true) ? $_POST['priority'] : 'P3',
            'due_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueD) ? $dueD . ' 17:00:00' : date('Y-m-d H:i:s', time() + 86400 * 3),
            'created_by' => $uid,
        ));
        $msg = !empty($res['ok']) ? ('كُلّف وأُخطر ✅ #' . $res['id']) : (($res['reason'] ?? 'تعذر') . ' ❌');
    }
    ems_gov_flash_redirect('dept_board.php', $msg, 'GOV-INFO-200', '');
    exit();
}

/* ── حل الإدارة المعروضة ─────────────────────────────────────────────────── */
$myUnit    = ems_dept_unit_of_role($role);
$isUmbrella = ($is_super_admin || $role === 9 || $role === 15);
$reqUnit   = isset($_GET['unit']) ? intval($_GET['unit']) : 0;
/* التعمق بإدارة غير إدارتي حكرٌ على الأدوار الجامعة — وغيرها يُثبَّت على وحدته */
$unit = ($reqUnit >= 1 && $reqUnit <= 15 && $isUmbrella) ? $reqUnit : $myUnit;

$deptName = '';
if ($unit > 0) {
    $r = mysqli_query($conn, 'SELECT name_ar FROM org_units WHERE unit_id = ' . intval($unit) . ' LIMIT 1');
    if ($r && ($x = mysqli_fetch_row($r))) { $deptName = (string) $x[0]; }
}

$page_title = 'ورقة الإدارة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/* ═══ الوضع الجامع: لوحة اكتمال الإدارات (التنفيذي 9 · الحوكمة 15 · السوبر) ═══ */
if ($unit <= 0 && $isUmbrella): ?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-table-cells-large';
$header_title_html = htmlspecialchars('لوحة الإدارات — الاكتمال التشغيلي فوق المحرك', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <p style="color:#666">كل صفٍّ إدارةٌ من خريطة الـ17 — أرقامها حيةٌ من المحرّك (مهام · طلبات · إنجاز 30ي). تعمّق بنقرة الاسم.</p>
  <table class="table table-striped">
    <thead><tr>
      <th>الإدارة</th><th>الأعضاء</th><th>مهام مفتوحة</th><th>متأخرات</th>
      <th>طلبات بيدها</th><th>طلبات متأخرة</th><th>إنجازات 30ي</th>
    </tr></thead>
    <tbody>
    <?php $coScan = $is_super_admin && $company_id <= 0 ? 4 : $company_id;
    for ($u = 1; $u <= 15; $u++):
        $members = ems_dept_member_ids($conn, $coScan, $u);
        $k = ems_dept_engine_kpis($conn, $coScan, $members);
        $nm = ems_dept_owner_name($u); ?>
      <tr>
        <td><a href="dept_board.php?unit=<?= $u ?>"><?= htmlspecialchars($nm) ?></a></td>
        <td><?= $k['members'] ?></td>
        <td><?= $k['open_tasks'] ?></td>
        <td style="<?= $k['overdue_tasks'] > 0 ? 'color:#c0392b;font-weight:bold' : '' ?>"><?= $k['overdue_tasks'] ?></td>
        <td><?= $k['live_requests'] ?></td>
        <td style="<?= $k['late_requests'] > 0 ? 'color:#c0392b;font-weight:bold' : '' ?>"><?= $k['late_requests'] ?></td>
        <td><?= $k['ach_30d'] ?></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>
</div>
<?php exit(); endif;

/* ═══ وضع الإدارة الواحدة ═══════════════════════════════════════════════ */
if ($unit <= 0): ?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): الرأسُ الموحَّدُ في هذا الفرعِ أيضًا — الشاشةُ ثلاثةُ
   فروعٍ، ولا يصحُّ أن يحمل الرأسَ فرعٌ ويُحرَم منه فرعان. */
$header_icon = 'fa fa-table-cells-large';
$header_title_html = htmlspecialchars('ورقة الإدارة', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <div class="alert alert-warning">دورك الحالي غير مربوطٍ بإدارةٍ في خريطة الـ17 — ورقة الإدارة تخص أدوار الإدارات التشغيلية، وللأدوار الجامعة لوحة الإدارات.</div>
</div>
<?php exit(); endif;

$members = ems_dept_member_ids($conn, $company_id, $unit);
/* نطاق المدير من الهيكل (قرار 9) يوسّع العضوية لا يبدلها — مرؤوس خارج أدوار
   الوحدة (تكليف عرضي) يظهر في ورقة مديره ولا يسقط من العد */
foreach (ems_manager_scope_user_ids($conn, $uid, 2) as $sid) {
    if (!in_array($sid, $members, true)) { $members[] = $sid; }
}
$kpi = ems_dept_engine_kpis($conn, $company_id, $members);
$in  = $members ? implode(',', array_map('intval', $members)) : '0';
$co  = intval($company_id);

/* طلبات أنواع الإدارة (قاموس الورقة 04) — الترشيح بالعربية في PHP لا في SQL
   (عائلة خلط الترتيبات: لا مقارنة نصٍّ عربيٍّ مربوطًا في MariaDB) */
$deptOwner = ems_dept_owner_name($unit);
$typeCodes = array();
$r = mysqli_query($conn, 'SELECT code, owner_dept, name_ar FROM request_types');
$typeNames = array();
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $typeNames[$x['code']] = $x['name_ar'];
    if (trim((string) $x['owner_dept']) === $deptOwner) { $typeCodes[] = $x['code']; }
}
$typeIn = $typeCodes ? "'" . implode("','", array_map(function ($c) use ($conn) {
    return mysqli_real_escape_string($conn, $c); }, $typeCodes)) . "'" : "''";

$liveReq = "status IN ('submitted','routed','in_approval','approved','executing','returned')";

/* ① مهام الأعضاء الحية */
$tasks = array();
$r = mysqli_query($conn,
    "SELECT wi.id, wi.title, wi.status, wi.priority, wi.due_at, wi.source_type, wi.source_ref,
            wi.created_at, ua.name AS assignee, uo.name AS owner
       FROM work_items wi
       LEFT JOIN users ua ON ua.id = wi.assigned_user_id
       LEFT JOIN users uo ON uo.id = wi.owner_user_id
      WHERE wi.company_id = {$co} AND wi.assigned_user_id IN ({$in})
        AND wi.status NOT IN ('closed_accepted','cancelled')
      ORDER BY COALESCE(wi.due_at, wi.created_at) ASC LIMIT 200");
while ($r && ($x = mysqli_fetch_assoc($r))) { $tasks[] = $x; }

/* ② المتأخرات (مهام + طلبات) */
$lateTasks = array();
$r = mysqli_query($conn,
    "SELECT wi.id, wi.title, wi.status, wi.priority, wi.due_at, wi.escalation_level, ua.name AS assignee
       FROM work_items wi LEFT JOIN users ua ON ua.id = wi.assigned_user_id
      WHERE wi.company_id = {$co} AND wi.assigned_user_id IN ({$in})
        AND (wi.status = 'overdue' OR (wi.due_at < NOW() AND wi.status IN ('assigned','accepted','in_progress')))
      ORDER BY wi.due_at ASC LIMIT 100");
while ($r && ($x = mysqli_fetch_assoc($r))) { $lateTasks[] = $x; }

/* ③ طلبات بيد الإدارة: حاملها عضوٌ أو نوعها ملك الإدارة */
$reqs = array();
$r = mysqli_query($conn,
    "SELECT rq.id, rq.request_no, rq.request_type_code, rq.title, rq.status, rq.sla_due_at,
            rq.submitted_at, uh.name AS holder, ur.name AS requester
       FROM requests rq
       LEFT JOIN users uh ON uh.id = rq.current_holder_user_id
       LEFT JOIN users ur ON ur.id = rq.requester_user_id
      WHERE rq.company_id = {$co} AND rq.{$liveReq}
        AND (rq.current_holder_user_id IN ({$in}) OR rq.request_type_code IN ({$typeIn}))
      ORDER BY COALESCE(rq.sla_due_at, rq.submitted_at) ASC LIMIT 200");
while ($r && ($x = mysqli_fetch_assoc($r))) { $reqs[] = $x; }

/* ④ إنجازات 30 يومًا */
$achs = array();
$r = mysqli_query($conn,
    "SELECT ar.id, ar.title, ar.source_kind, ar.source_ref, ar.attribution, ar.weight_pct,
            ar.recognized_at, up.name AS person
       FROM achievement_records ar LEFT JOIN users up ON up.id = ar.person_user_id
      WHERE ar.company_id = {$co} AND ar.person_user_id IN ({$in}) AND ar.reversed_at IS NULL
        AND ar.recognized_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      ORDER BY ar.recognized_at DESC LIMIT 200");
while ($r && ($x = mysqli_fetch_assoc($r))) { $achs[] = $x; }

$stLabel = function ($s) {
    static $m = array('draft' => 'مسودة', 'assigned' => 'مسندة', 'accepted' => 'مقبولة',
        'in_progress' => 'جارية', 'blocked' => 'معطلة', 'done_pending_verify' => 'تنتظر التحقق',
        'closed_accepted' => 'مغلقة', 'returned' => 'معادة', 'reopened' => 'أعيد فتحها',
        'overdue' => 'متأخرة', 'scheduled' => 'مجدولة', 'cancelled' => 'ملغاة',
        'submitted' => 'مقدَّم', 'routed' => 'موجَّه', 'in_approval' => 'في الاعتماد',
        'approved' => 'معتمد', 'executing' => 'قيد التنفيذ', 'executed' => 'نُفِّذ', 'closed' => 'مغلق');
    return isset($m[$s]) ? $m[$s] : $s;
};
$attrLabel = function ($a) {
    static $m = array('executive' => 'تنفيذي', 'supervisory' => 'إشرافي', 'decision' => 'قرار');
    return isset($m[$a]) ? $m[$a] : $a;
};
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): الرأسُ الموحَّدُ في فرعِ الإدارةِ الواحدة. */
$header_icon = 'fa fa-table-cells-large';
$header_title_html = htmlspecialchars('ورقة الإدارة — ' . ($deptName ?: $deptOwner), ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($isUmbrella): ?>
    <p><a href="dept_board.php" class="btn btn-sm btn-secondary">↩ لوحة الإدارات كلها</a></p>
  <?php endif; ?>
  <p style="color:#666" title="لماذا أرى هذا؟">
    العضوية من الهيكل لا من قوائم: أدوار الوحدة «<?= htmlspecialchars($deptOwner) ?>» في خريطة الـ17
    + نطاقك الإداري من الهرم (<?= count($members) ?> عضوًا).
  </p>
  <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars((string) $_GET['msg']) ?></div>
  <?php endif; ?>
  <?php
  /* تكليف SRC-01: لمن له مرؤوسون في الهيكل (المدير) وفي إدارته هو لا في تعمق الأدوار الجامعة */
  $iAmManager = (count(ems_manager_scope_user_ids($conn, $uid, 1)) > 0) && ($unit === $myUnit);
  if ($iAmManager && $members): ?>
  <details style="margin-bottom:14px"><summary style="cursor:pointer;font-weight:bold">
    <i class="fa fa-user-plus"></i> تكليف عضوٍ من إدارتي (SRC-01)</summary>
    <form method="post" class="ems-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:10px">
      <input type="hidden" name="action" value="dept_assign">
      <div><label for="emsf_1170_47df2">المكلَّف</label>
        <select name="to_user" class="form-control" required id="emsf_1170_47df2">
          <?php $r = mysqli_query($conn, 'SELECT id, name FROM users WHERE id IN (' . implode(',', array_map('intval', $members)) . ') ORDER BY name');
          while ($r && ($u = mysqli_fetch_assoc($r))): if (intval($u['id']) === $uid) { continue; } ?>
            <option value="<?= intval($u['id']) ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endwhile; ?>
        </select></div>
      <div style="flex:2;min-width:220px"><label for="emsf_1171_6a5e4">المهمة</label>
        <input type="text" name="title" class="form-control" required maxlength="300" id="emsf_1171_6a5e4"></div>
      <div style="flex:1;min-width:160px"><label for="emsf_1172_eff16">المخرج المطلوب</label>
        <input type="text" name="deliverable" class="form-control" maxlength="300" id="emsf_1172_eff16"></div>
      <div><label for="emsf_1173_5ada4">المهلة</label><input type="date" name="due_date" class="form-control" id="emsf_1173_5ada4"></div>
      <div><label for="emsf_1174_bb9ed">الأولوية</label>
        <select name="priority" class="form-control" id="emsf_1174_bb9ed">
          <option>P3</option><option>P2</option><option>P1</option><option>P0</option><option>P4</option>
        </select></div>
      <button class="btn btn-primary">كلِّف</button>
    </form>
  </details>
  <?php endif; ?>

  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <?php $tiles = array(
        array('أعضاء الإدارة', $kpi['members'], ''),
        array('مهام مفتوحة', $kpi['open_tasks'], ''),
        array('مهام متأخرة', $kpi['overdue_tasks'], $kpi['overdue_tasks'] > 0 ? '#c0392b' : ''),
        array('طلبات بيد الإدارة', $kpi['live_requests'], ''),
        array('طلبات كسرت مهلتها', $kpi['late_requests'], $kpi['late_requests'] > 0 ? '#c0392b' : ''),
        array('إنجازات 30 يومًا', $kpi['ach_30d'], ''),
    );
    foreach ($tiles as $t): ?>
      <div style="flex:1;min-width:150px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:14px;text-align:center">
        <div style="font-size:26px;font-weight:bold;<?= $t[2] ? 'color:' . $t[2] : '' ?>"><?= number_format((float) $t[1]) ?></div>
        <div style="color:#777"><?= htmlspecialchars($t[0]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <h5><i class="fa fa-list-check"></i> مهام أعضاء الإدارة (<?= count($tasks) ?>)</h5>
  <table class="table table-striped">
    <thead><tr><th>#</th><th>العنوان</th><th>المنفذ</th><th>المالك</th><th>المصدر</th><th>الأولوية</th><th>الحالة</th><th>المهلة</th><th>تاريخ الإنشاء</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?= intval($t['id']) ?></td>
        <td><?= htmlspecialchars($t['title']) ?></td>
        <td><?= htmlspecialchars($t['assignee'] ?? '—') ?></td>
        <td><?= htmlspecialchars($t['owner'] ?? '—') ?></td>
        <td><?= htmlspecialchars($t['source_type'] . ' · ' . $t['source_ref']) ?></td>
        <td><?= htmlspecialchars($t['priority']) ?></td>
        <td><?= htmlspecialchars($stLabel($t['status'])) ?></td>
        <td><?= htmlspecialchars($t['due_at'] ?? '—') ?></td>
        <td><?= htmlspecialchars($t['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 style="margin-top:22px;color:#c0392b"><i class="fa fa-triangle-exclamation"></i> المتأخرات (<?= count($lateTasks) ?>)</h5>
  <table class="table table-striped">
    <thead><tr><th>#</th><th>العنوان</th><th>المنفذ</th><th>الأولوية</th><th>الحالة</th><th>المهلة الفائتة</th><th>درجة التصعيد</th></tr></thead>
    <tbody>
    <?php foreach ($lateTasks as $t): ?>
      <tr>
        <td><?= intval($t['id']) ?></td>
        <td><?= htmlspecialchars($t['title']) ?></td>
        <td><?= htmlspecialchars($t['assignee'] ?? '—') ?></td>
        <td><?= htmlspecialchars($t['priority']) ?></td>
        <td><?= htmlspecialchars($stLabel($t['status'])) ?></td>
        <td><?= htmlspecialchars($t['due_at'] ?? '—') ?></td>
        <td><?= intval($t['escalation_level']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 style="margin-top:22px"><i class="fa fa-envelope-open-text"></i> طلبات بيد الإدارة (<?= count($reqs) ?>)</h5>
  <table class="table table-striped">
    <thead><tr><th>الرقم</th><th>النوع</th><th>العنوان</th><th>مقدِّمه</th><th>بيد من الآن</th><th>الحالة</th><th>مهلة الرد</th><th>قُدِّم في</th></tr></thead>
    <tbody>
    <?php foreach ($reqs as $rq): ?>
      <tr>
        <td><?= htmlspecialchars($rq['request_no'] ?? ('#' . $rq['id'])) ?></td>
        <td><?= htmlspecialchars(isset($typeNames[$rq['request_type_code']]) ? $typeNames[$rq['request_type_code']] : $rq['request_type_code']) ?></td>
        <td><?= htmlspecialchars($rq['title']) ?></td>
        <td><?= htmlspecialchars($rq['requester'] ?? '—') ?></td>
        <td><?= htmlspecialchars($rq['holder'] ?? '—') ?></td>
        <td><?= htmlspecialchars($stLabel($rq['status'])) ?></td>
        <td><?= htmlspecialchars($rq['sla_due_at'] ?? '—') ?></td>
        <td><?= htmlspecialchars($rq['submitted_at'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 style="margin-top:22px"><i class="fa fa-medal"></i> إنجازات الإدارة — 30 يومًا (<?= count($achs) ?>)</h5>
  <table class="table table-striped">
    <thead><tr><th>#</th><th>العنوان</th><th>صاحبه</th><th>الصفة</th><th>الوزن ٪</th><th>المصدر</th><th>تاريخ الاعتراف</th></tr></thead>
    <tbody>
    <?php foreach ($achs as $a): ?>
      <tr>
        <td><?= intval($a['id']) ?></td>
        <td><?= htmlspecialchars($a['title']) ?></td>
        <td><?= htmlspecialchars($a['person'] ?? '—') ?></td>
        <td><?= htmlspecialchars($attrLabel($a['attribution'])) ?></td>
        <td><?= htmlspecialchars($a['weight_pct']) ?></td>
        <td><?= htmlspecialchars($a['source_kind'] . ' · ' . $a['source_ref']) ?></td>
        <td><?= htmlspecialchars($a['recognized_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>


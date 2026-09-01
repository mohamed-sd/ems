<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): WFM-001
/**
 * Portal/my_tasks.php — مهامي (WFM-01 حيًّا فوق work_items)
 * ───────────────────────────────────────────────────────────────────────────
 * WF-01: واجهةُ قراءةٍ وتنفيذٍ لا مصدرُ بيانات — لا إنشاءَ هنا؛ العناصر تأتي
 * من مصادرها الأربعة عشر عبر محرّك WorkItemService، وكلُّ صفٍّ يرجع لأصله.
 * WFM-066: ثمانيةُ عروضٍ (اليوم · المتأخرة · القادمة · المعطلة · المعادة ·
 * المفوَّضة إليّ · التي كلّفتُ بها · فريقي للمدير من الهيكل لا من قوائم).
 * الأفعال بمخوَّليها في المحرّك (WF-04: لا يُغلق أحدٌ مهمتَه) والحارس خادمي.
 * «لماذا أرى هذا؟» — سلسلة تفسير الظهور الخماسية (AC-WFM-13).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/w14_grid.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once '../includes/resolve_manager.php';
require_once '../app/Services/Work/WorkItemService.php';

use App\Services\Work\WorkItemService as WI;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', ''); exit(); }

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Portal/my_tasks.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/my_tasks.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}

/* ◆ «مهامي» تملك `?view=` — تاباتُها العشرُ معناه، لا سجلُّ المناظرِ المركزي.
     والإعلانُ **قبلَ** `page_header.php` وإلّا ردَّ المُخنِقُ كلَّ نقرةِ تابٍ إلى
     الرابطِ العاري (302) فعادت الشاشةُ إلى «اليوم» أبدًا — وهو العطبُ المقيسُ
     الذي شُرح في ذيلِ `includes/nav_views.php`. */
require_once __DIR__ . '/../includes/nav_views.php';
ems_nav_view_claim();

$VIEW = isset($_GET['view']) ? preg_replace('/[^a-z_]/', '', (string) $_GET['view']) : 'today';

/* ── الأفعال: انتقالات المحرّك بمخوَّليها — الحارس في الخدمة لا في الواجهة ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'wi_transition') {
    $itemId = intval($_POST['item_id'] ?? 0);
    $to     = preg_replace('/[^a-z_]/', '', (string) ($_POST['to'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $evid   = trim((string) ($_POST['evidence'] ?? ''));
    if ($evid !== '' && $itemId > 0) {
        // الدليل قبل الإكمال (WF-02: دليل الإغلاق) — صفٌّ في task_evidence + مرجع العنصر
        $it = WI::fetch($conn, $itemId);
        if ($it) {
            $st = $conn->prepare("INSERT INTO task_evidence (company_id, item_id, kind, ref, created_by) VALUES (?,?, 'note', ?, ?)");
            $co = intval($it['company_id']);
            $st->bind_param('iisi', $co, $itemId, $evid, $uid);
            $st->execute();
            $st->close();
            $st = $conn->prepare("UPDATE work_items SET evidence_ref = ? WHERE id = ?");
            $ev = mb_substr($evid, 0, 200);
            $st->bind_param('si', $ev, $itemId);
            $st->execute();
            $st->close();
        }
    }
    if ($to === 'reassign') {
        $r = WI::reassign($conn, $itemId, intval($_POST['to_user'] ?? 0), $uid, $reason);
    } else {
        $r = WI::transition($conn, $itemId, $to, $uid, $reason);
    }
    $msg = $r['ok'] ? 'نفذ ✅' : ($r['reason'] . ' ❌');
    ems_gov_redirect('Location: my_tasks.php?view=' . urlencode($VIEW) . '&msg=' . urlencode($msg));
    exit();
}

/* ── العروض الثمانية (WFM-066) ─────────────────────────────────────────── */
$mine = "(wi.assigned_user_id = {$uid})";
$views = array(
    /* ══ «اليوم» كانت تشترط موعدًا قريبًا فابتلعت ما بُدئ فعلًا ═══════════════
         المقيسُ قبلَه: `in_progress` **لا يطابق عرضًا واحدًا** متى كانت المهلةُ
         أبعدَ من الليلة — «القادمة» تستثني `in_progress` من حالاتِها، و«اليوم»
         تشترط `due_at` قبلَ الغد. فالمنفِّذُ يضغط «بدء» **فتختفي مهمتُه من
         شاشتِه كلِّها** ولا تعود حتى تتأخّر. وأغلبُ المهامِّ مهلتُها أطولُ من يوم.
       ◆ والقاعدة: **ما بُدئ فهو عملُ اليوم بحكمِ بدئِه لا بحكمِ موعدِه** — فلا
         يُسأل `due_at` عن حالةٍ يجيب عنها الفعل. و`scheduled` مضمومةٌ هنا كما
         هي في «القادمة»، وإلّا سقطت هي الأخرى متى استحقّت اليوم. */
    'today'          => array('اليوم', "{$mine} AND wi.status IN ('assigned','accepted','in_progress','scheduled') AND (wi.status = 'in_progress' OR wi.due_at IS NULL OR wi.due_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY))"),
    'late'           => array('المتأخرة', "{$mine} AND (wi.status = 'overdue' OR (wi.due_at < NOW() AND wi.status IN ('assigned','accepted','in_progress')))"),
    'upcoming'       => array('القادمة', "{$mine} AND wi.status IN ('assigned','accepted','scheduled') AND wi.due_at >= DATE_ADD(CURDATE(), INTERVAL 1 DAY)"),
    'blocked'        => array('المعطلة', "{$mine} AND wi.status = 'blocked'"),
    'returned'       => array('المعادة إلي', "{$mine} AND wi.status IN ('returned','reopened')"),
    'delegated'      => array('المفوضة إلي', "{$mine} AND wi.delegation_ref IS NOT NULL"),
    'assignments'    => array('تكليفاتي', "{$mine} AND wi.item_type = 'assignment' AND wi.status NOT IN ('closed_accepted','cancelled')"),
    'assigned_by_me' => array('التي كلفت بها', "(wi.created_by = {$uid} OR wi.owner_user_id = {$uid}) AND wi.assigned_user_id <> {$uid} AND wi.status NOT IN ('closed_accepted','cancelled')"),
    'verify'         => array('تنتظر تحققي', "wi.verifier_user_id = {$uid} AND wi.status = 'done_pending_verify'"),
    'team'           => array('فريقي', ''),
);
$teamIds = ems_manager_scope_user_ids($conn, $uid, 2);
if ($teamIds) {
    $views['team'][1] = "wi.assigned_user_id IN (" . implode(',', $teamIds) . ") AND wi.status NOT IN ('closed_accepted','cancelled')";
} else { unset($views['team']); }
if (!isset($views[$VIEW])) { $VIEW = 'today'; }

$counts = array();
foreach ($views as $k => $v) {
    $co = $is_super_admin && $company_id <= 0 ? '1=1' : "wi.company_id = {$company_id}";
    $r = mysqli_query($conn, "SELECT COUNT(*) FROM work_items wi WHERE {$co} AND {$v[1]}");
    $counts[$k] = $r ? intval(mysqli_fetch_row($r)[0]) : 0;
}

$co = $is_super_admin && $company_id <= 0 ? '1=1' : "wi.company_id = {$company_id}";
$rows = array();
$r = mysqli_query($conn,
    "SELECT wi.*, uo.name AS owner_name, ua.name AS assignee_name, p.name AS project_name
       FROM work_items wi
       LEFT JOIN users uo ON uo.id = wi.owner_user_id
       LEFT JOIN users ua ON ua.id = wi.assigned_user_id
       LEFT JOIN project p ON p.id = wi.project_id
      WHERE {$co} AND {$views[$VIEW][1]}
      ORDER BY FIELD(wi.priority,'P0','P1','P2','P3','P4'), wi.due_at IS NULL, wi.due_at ASC
      LIMIT 300");
while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[] = $x; }

/* ── سقفُ الثلاثمئةِ يُعلَن ولا يُسكت عنه ─────────────────────────────────────
   الشارةُ تعدُّ **كلَّ** صفوفِ العرضِ والجدولُ يُصيِّر 300 منها. فكان المديرُ
   يقرأ «فريقي 1406» ويجد 300 صفًّا **بلا أيِّ إشارةٍ إلى ما سقط** — و«صمتُ
   الاقتطاع» يُقرأ تغطيةً كاملةً وهو نقصُ 1,106 صفًّا. فيُعلَن الفارقُ برقمِه. */
$ROW_CAP   = 300;
$truncated = max(0, intval($counts[$VIEW]) - count($rows));

/* تسميات الحالات — مرادفات طقم الحالات السبعة (ui-unification) */
$STATE_AR = array(
    'draft' => 'مسودة', 'scheduled' => 'مسودة', 'assigned' => 'مرفوع', 'accepted' => 'قيد التنفيذ',
    'in_progress' => 'قيد التنفيذ', 'blocked' => 'موقوف', 'done_pending_verify' => 'بانتظار الاعتماد',
    'closed_accepted' => 'مكتملة', 'returned' => 'معادة', 'rejected' => 'مرفوضة',
    'cancelled' => 'ملغاة', 'reassigned' => 'مرفوع', 'delegated' => 'مرفوع',
    'overdue' => 'موقوف', 'reopened' => 'معكوسة',
);
$SRC_AR = array(
    'SRC-01' => 'تكليف مدير', 'SRC-02' => 'تكليف نائب', 'SRC-03' => 'مرحلة مستند', 'SRC-04' => 'خطوة اعتماد',
    'SRC-05' => 'طلب موظف', 'SRC-06' => 'بلاغ', 'SRC-07' => 'استثناء', 'SRC-08' => 'مهمة دورية',
    'SRC-09' => 'إجراء تصحيحي', 'SRC-10' => 'قرار اجتماع', 'SRC-11' => 'تصعيد مهلة', 'SRC-12' => 'عابرة إدارتين',
    'SRC-13' => 'سجل مرتبط', 'SRC-14' => 'طارئة تشغيلية',
);

$explain = null;
if (isset($_GET['explain'])) { $explain = WI::explainAppearance($conn, intval($_GET['explain']), $uid); }

$page_title = 'إيكوبيشن | مهامي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell" dir="rtl">
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_my_tasks
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف المهمة' => 'g27',
            'نوع المهمة' => 'g28',
            'مصدر المهمة' => 'g29',
            'الشاشة الأصلية' => 'g30',
            'المرجع' => 'g31',
            'مهلة المهمة' => 'g32',
            'الأولوية' => 'g33',
            'حالة المهمة' => 'g34',
            'سبب التأجيل' => 'g35',
            'وقت الإنجاز' => 'g36',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('my_tasks');
        echo ems_w14_grid('emsList_my_tasks', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في المهام المسندة'); /* /GUIDE_COLS */ ?>
    </div></div></div>

    <?php
    $header_title = 'مهامي';
    $header_icon = 'fa fa-list-check';
    $header_actions = array(); // WF-01: لا إنشاءَ من مساحة عملي
    $header_back = false;
    include '../includes/page_header.php';
    require_once __DIR__ . '/../includes/screen_contract.php';
    ems_screen_about('كل ما ينتظر تنفيذي أنا — بعروضه العشرة، وكل عنصر يرجع لأصله بضغطة ويشرح «لماذا أراه» بسلسلته الخماسية.');
    echo ems_states_bundle('لا عناصر في هذا العرض', 'بدل العرض أو تحقق من مصادر التكليف');

    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <?php if ($explain): ?>
    <div class="card ems-wi-accent-card">
        <div class="card-header"><strong><i class="fas fa-circle-question"></i> لماذا يظهر لي هذا العنصر؟</strong>
            <a class="btn btn-sm btn-secondary ems-wi-close" href="my_tasks.php?view=<?php echo htmlspecialchars($VIEW); ?>">إغلاق</a></div>
        <div class="card-body">
            <?php foreach ($explain['steps'] as $i => $s): ?>
                <div class="ems-wi-line"><span class="badge <?php echo $s['ok'] ? 'bg-success' : 'bg-danger'; ?>"><?php echo $i + 1; ?></span>
                    <strong><?php echo htmlspecialchars($s['q']); ?></strong> — <?php echo htmlspecialchars($s['a']); ?></div>
            <?php endforeach; ?>
            <?php if (!$explain['complete']): ?>
                <div class="alert alert-warning ems-wi-chain-warn">سلسلة ناقصة — والحجب أسلم من الظهور بلا تفسير (AC-WFM-13)</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="ems-wi-views">
        <?php foreach ($views as $k => $v): ?>
            <a href="my_tasks.php?view=<?php echo $k; ?>"
               class="btn btn-sm <?php echo $k === $VIEW ? 'btn-primary' : 'btn-secondary'; ?>">
                <?php echo htmlspecialchars($v[0]); ?> <span class="badge bg-light text-dark"><?php echo $counts[$k]; ?></span></a>
        <?php endforeach; ?>
    </div>

    <?php if ($VIEW === 'assignments'):
        /* WI-ASG: «التكليفُ إطارٌ والمهمةُ عملٌ داخله» — التفويضاتُ النافذةُ إليّ تُعرض مع تكليفاتي */
        $dels = array();
        $r = mysqli_query($conn, "SELECT wd.kind, wd.scope_ref, wd.starts_at, wd.ends_at, uf.name AS from_name
                                    FROM work_delegations wd LEFT JOIN users uf ON uf.id = wd.from_user_id
                                   WHERE wd.company_id = " . intval($co === '1=1' ? 4 : $company_id) . "
                                     AND wd.to_user_id = {$uid} AND wd.status = 'active'
                                     AND NOW() BETWEEN wd.starts_at AND wd.ends_at ORDER BY wd.ends_at");
        while ($r && ($x = mysqli_fetch_assoc($r))) { $dels[] = $x; }
        $KIND_AR = array('task_assign' => 'إسناد مهمة', 'role_assign' => 'تكليف بدور', 'deputize' => 'إنابة',
                         'delegate_approval' => 'تفويض اعتماد', 'reassign' => 'إعادة إسناد', 'workload_move' => 'نقل عبء');
    ?>
    <div class="card ems-wi-accent-card">
        <div class="card-header"><strong><i class="fas fa-user-shield"></i> تفويضات وإنابات نافذة إلي</strong>
            <span class="badge bg-secondary"><?php echo count($dels); ?></span></div>
        <div class="card-body ems-wi-dels-body">
            <?php if (!$dels): ?><span class="text-muted">لا تفويض نافذا إليك الآن — «انتهاء التفويض يوقف التوليد في اللحظة» (WF-08)</span>
            <?php else: foreach ($dels as $d): ?>
                <div class="ems-wi-line">· <strong><?php echo htmlspecialchars($KIND_AR[$d['kind']] ?? $d['kind']); ?></strong>
                    من <?php echo htmlspecialchars((string) ($d['from_name'] ?: '—')); ?>
                    — النطاق: <code><?php echo htmlspecialchars((string) $d['scope_ref']); ?></code>
                    · حتى <?php echo htmlspecialchars((string) $d['ends_at']); ?></div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($truncated > 0): ?>
    <div class="alert alert-warning" role="status">
        <i class="fa fa-scissors" aria-hidden="true"></i>
        هذا العرض فيه <strong><?php echo intval($counts[$VIEW]); ?></strong> عنصرا،
        والمعروض هنا <strong><?php echo count($rows); ?></strong> فقط (سقف الصفحة <?php echo $ROW_CAP; ?>)
        — <strong><?php echo $truncated; ?></strong> لا تظهر.
        ضيق العرض بتاب أدق حتى لا تبنى قراءة على قائمة ناقصة.
    </div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="my_tasksTable">
            <thead><tr>
                <th>رقم المهمة</th><th>العنوان والمخرج</th><th>المصدر</th><th>المستند المرتبط</th>
                <th>النطاق</th><th>مسندها</th><th>المنفذ</th><th>المهلة</th><th>المتبقي</th>
                <th>الأولوية</th><th>الحالة</th><th>الإجراء</th>
                <!-- XF-01: «وقت الانجاز» عمود قائم في work_items (completed_at) لم يكن
                     يصير، والحلقة تقرا كل اعمدة الجدول فالقيمة حاضرة. -->
                <th class="ems-fn-th" data-fn="1" data-fn-src="completed_at">وقت الإنجاز</th>
                <th class="ems-gov-th" data-gov="entity" data-slice="1">الكيان</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="13" class="text-center text-muted">لا عناصر في هذا العرض — والعناصر تأتي من مصادرها لا من هنا (WF-01)</td></tr>
            <?php else: foreach ($rows as $t):
                $id = intval($t['id']);
                $isExec = ($uid === intval($t['assigned_user_id']));
                $isOwner = ($uid === intval($t['owner_user_id']) || $uid === intval($t['created_by']));
                $isVerifier = ($uid === intval($t['verifier_user_id'])) || (intval($t['verifier_user_id']) === 0 && $isOwner && !$isExec);
                $due = $t['due_at'] ? strtotime($t['due_at']) : null;
                $left = $due ? ($due - time()) : null;
                $leftTxt = $left === null ? '—' : ($left < 0 ? 'متجاوزة ' . ceil(-$left / 3600) . ' س' : (floor($left / 86400) . ' ي ' . floor(($left % 86400) / 3600) . ' س'));
                $scope = array();
                if ($t['project_name']) { $scope[] = $t['project_name']; }
                elseif ($t['org_unit_id']) { $scope[] = 'إدارة ' . $t['org_unit_id']; }
                if ($t['site_id']) { $scope[] = 'موقع ' . $t['site_id']; }
                /* XF-01: وسمُ الصفِّ يُطبَع من هذه الكتلةِ نفسِها — فلا تُشقُّ كتلةُ
                   الـHTML بـ`<?php ?>` جديدٍ داخلَ الوسم (المقياسُ يعُدُّ المقاطع). */
                echo '<tr data-xf="' . htmlspecialchars(json_encode(array(
                    'completed_at' => (string) ($t['completed_at'] ?? ''),
                ), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '">';
            ?>
                    <td><code>WI-<?php echo $id; ?></code><br>
                        <a href="my_tasks.php?view=<?php echo $VIEW; ?>&explain=<?php echo $id; ?>" title="لماذا أرى هذا؟"
                           class="ems-wi-whylink"><i class="fas fa-circle-question"></i> لماذا؟</a></td>
                    <td class="ems-wi-title-cell"><strong><?php echo htmlspecialchars((string) $t['title']); ?></strong>
                        <div class="text-muted ems-wi-sub">المخرج: <?php echo htmlspecialchars((string) $t['deliverable']); ?></div>
                        <?php if ($t['status_reason']): ?><div class="ems-wi-reason"><?php echo htmlspecialchars((string) $t['status_reason']); ?></div><?php endif; ?></td>
                    <td><span class="badge bg-secondary" title="<?php echo htmlspecialchars((string) $t['source_type']); ?>"><?php echo htmlspecialchars($SRC_AR[$t['source_type']] ?? $t['source_type']); ?></span></td>
                    <td><?php if ($t['source_screen']): ?>
                        <a href="../<?php echo htmlspecialchars((string) $t['source_screen']); ?>" title="فتح الأصل بضغطة (WF-01)">
                            <?php echo htmlspecialchars((string) $t['source_ref']); ?></a>
                        <?php else: echo htmlspecialchars((string) $t['source_ref']); endif; ?></td>
                    <td><?php echo htmlspecialchars($scope ? implode(' · ', $scope) : 'شخصي'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($t['owner_name'] ?: '—')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($t['assignee_name'] ?: '—')); ?></td>
                    <td><?php echo $t['due_at'] ? htmlspecialchars(date('Y-m-d H:i', $due)) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($leftTxt); ?></td>
                    <td><span class="badge <?php echo in_array($t['priority'], array('P0', 'P1'), true) ? 'bg-danger' : ($t['priority'] === 'P2' ? 'bg-warning text-dark' : 'bg-light text-dark'); ?>"><?php echo htmlspecialchars((string) $t['priority']); ?></span></td>
                    <td><?php echo htmlspecialchars($STATE_AR[$t['status']] ?? $t['status']); ?></td>
                    <td class="ems-wi-actions-cell">
                        <?php $s = (string) $t['status']; ?>
                        <?php if ($isExec && $s === 'assigned'): ?>
                            <form method="post" class="ems-wi-inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="accepted">
                                <button class="btn btn-sm btn-secondary">استلام</button></form>
                        <?php endif; ?>
                        <?php if ($isExec && in_array($s, array('accepted', 'returned', 'reopened', 'blocked'), true)): ?>
                            <form method="post" class="ems-wi-inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="in_progress">
                                <button class="btn btn-sm btn-secondary">بدء</button></form>
                        <?php endif; ?>
                        <?php if ($isExec && in_array($s, array('accepted', 'in_progress'), true)): ?>
                            <details class="ems-wi-inline-block"><summary class="btn btn-sm btn-secondary ems-wi-inline-block">توقف</summary>
                                <form method="post" class="ems-wi-dropform">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="blocked">
                                    <select name="reason" class="form-control form-control-sm ems-wi-field-gap" aria-label="سبب التوقف">
                                        <option value="awaiting_part">انتظار قطعة</option><option value="awaiting_client">قرار عميل</option>
                                        <option value="awaiting_higher_approval">اعتماد أعلى</option><option value="awaiting_external">طرف خارجي</option>
                                        <option value="force_majeure">قوة قاهرة</option></select>
                                    <button class="btn btn-sm btn-secondary">تأكيد التوقف</button></form></details>
                            <details class="ems-wi-inline-block"><summary class="btn btn-sm btn-primary ems-wi-inline-block">إكمال</summary>
                                <form method="post" class="ems-wi-dropform">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="done_pending_verify">
                                    <input name="evidence" class="form-control form-control-sm ems-wi-field-gap" placeholder="دليل الإنجاز (إلزامي منطقا: <?php echo htmlspecialchars((string) $t['evidence_required']); ?>)" required aria-label="دليل الإنجاز (إلزامي منطقا: )">
                                    <button class="btn btn-sm btn-primary">تقديم للتحقق</button></form></details>
                        <?php endif; ?>
                        <?php if ($isVerifier && $s === 'done_pending_verify'): ?>
                            <form method="post" class="ems-wi-inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="closed_accepted">
                                <button class="btn btn-sm btn-primary" title="الدليل: <?php echo htmlspecialchars((string) $t['evidence_ref']); ?>">قبول وإغلاق</button></form>
                            <details class="ems-wi-inline-block"><summary class="btn btn-sm btn-danger ems-wi-inline-block">إعادة</summary>
                                <form method="post" class="ems-wi-dropform">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="returned">
                                    <input name="reason" class="form-control form-control-sm ems-wi-field-gap" placeholder="سبب الإعادة (إلزامي)" required aria-label="سبب الإعادة (إلزامي)">
                                    <button class="btn btn-sm btn-danger">إعادة للمنفذ</button></form></details>
                        <?php endif; ?>
                        <?php if ($isOwner && in_array($s, array('assigned', 'accepted', 'in_progress', 'blocked', 'overdue'), true)): ?>
                            <details class="ems-wi-inline-block"><summary class="btn btn-sm btn-secondary ems-wi-inline-block">نقل</summary>
                                <form method="post" class="ems-wi-dropform">
        <?= csrf_field() ?><input type="hidden" name="action" value="wi_transition"><input type="hidden" name="item_id" value="<?php echo $id; ?>"><input type="hidden" name="to" value="reassign">
                                    <input name="to_user" class="form-control form-control-sm ems-wi-field-gap" placeholder="معرف المنفذ الجديد" required aria-label="معرف المنفذ الجديد">
                                    <input name="reason" class="form-control form-control-sm ems-wi-field-gap" placeholder="السبب (إلزامي — العد يستمر)" required aria-label="السبب (إلزامي — العد يستمر)">
                                    <button class="btn btn-sm btn-secondary">نقل</button></form></details>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string) $t['company_id']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

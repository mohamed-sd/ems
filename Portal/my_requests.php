<?php
/**
 * Portal/my_requests.php — طلباتي (WFM-01 §5-4/5-5 · WF-05/WF-07)
 * ───────────────────────────────────────────────────────────────────────────
 * التقديم من القاموس الحاكم (62 نوعًا — الورقة 04) والنموذج يُشتق لا يُخترع.
 * «من هو عنده الآن ومنذ متى» ظاهرٌ لكل طلب (AC-WFM-07). والمعالجة للمستقبِل:
 * قرارٌ (اعتماد/رفض/إعادة بسبب) ثم تنفيذٌ وإغلاقٌ **ببنية الرد التسعة** —
 * لا إغلاقَ بتغيير حالة (WF-05). كلُّ الأحكام في RequestService لا هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../app/Services/Work/RequestService.php';

use App\Services\Work\RequestService as RQ;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', ''); exit(); }

$__pp = check_page_permissions($conn, 'Portal/my_requests.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/my_requests.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}

/* ── الأفعال ─────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');

    /* ══ RPR-W15 · «مساحةُ عملي Launcher + Projection ولا تصير Owner» ═════════
         نصُّ قرارِ المالكِ الثالثِ حرفًا. وكان هذا السطحُ **يخزّن حقيقةَ الطلبِ
         عندَه** في مخزنٍ عامٍّ، فيكسر مصدرَ الحقيقة: طلبُ الإجازةِ ليس عند
         الموارد، وطلبُ الصيانةِ ليس عند الصيانة.
         ◆ والآن: كلُّ نوعٍ **نافذٍ في السجلِّ المركزيِّ** `gov_request_type`
           يُطلَق بـ`RequestLauncher` **فيُنشَأ عند مالكِه** بخدمةِ مالكِه —
           والحالةُ تُعرَض إسقاطًا بمرجعٍ حيّ.
         ⛔ **ولا يُقبل النوعُ المسجَّلُ في المخزنِ العامّ** بعد اليوم؛ وما فيه
           من صفوفٍ سابقةٍ **دَينٌ معدودٌ** في `Enterprise Debt Closure`. */
    if ($act === 'rq_launch') {
        require_once __DIR__ . '/../app/Services/Workspace/RequestLauncher.php';
        $__payload = isset($_POST['payload']) && is_array($_POST['payload']) ? $_POST['payload'] : array();
        $__res = \App\Services\Workspace\RequestLauncher::launch(
            $conn,
            array('id' => $uid, 'company_id' => $company_id,
                  'role' => strval($_SESSION['user']['role'] ?? ''),
                  'gate' => ems_tenant_db()),
            (string) ($_POST['type_code'] ?? ''),
            $__payload
        );
        $msg = $__res['verdict'] === \App\Services\Workspace\RequestLauncher::OK
            ? ('أنشئ الطلب عند إدارته المالكة')
            : ($__res['why'] . ' ❌');
        ems_gov_flash_redirect('my_requests.php', $msg, 'GOV-INFO-200', '');
        exit();
    }

    if ($act === 'rq_submit') {
        /* النوعُ المسجَّلُ في السجلِّ المركزيِّ يُطلَق ولا يُخزَّن هنا. */
        require_once __DIR__ . '/../app/Services/Workspace/RequestLauncher.php';
        $__t = \App\Services\Workspace\RequestLauncher::type(
            ems_tenant_db(), $company_id, (string) ($_POST['type_code'] ?? ''));
        if ($__t !== null && (string) $__t['state'] === 'active') {
            ems_gov_flash_redirect('my_requests.php',
                'هذا النوع ينشأ عند إدارته المالكة ولا يخزن هنا', 'GOV-FAIL-409', '');
            exit();
        }
        $r = RQ::submit($conn, array(
            'company_id' => $company_id ?: intval($_POST['company_id'] ?? 0),
            'request_type_code' => (string) ($_POST['type_code'] ?? ''),
            'requester_user_id' => $uid,
            'org_unit_id' => intval($_POST['org_unit_id'] ?? 0) ?: 1,
            'project_id' => intval($_POST['project_id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'fields_json' => json_encode(array('تفاصيل' => trim((string) ($_POST['details'] ?? ''))), JSON_UNESCAPED_UNICODE),
            'created_by' => $uid,
        ));
        $msg = $r['ok'] ? ('قدم ' . $r['request_no'] . ' ✅') : ($r['reason'] . ' ❌');
    } elseif ($act === 'rq_decide') {
        $r = RQ::decide($conn, intval($_POST['req_id'] ?? 0), (string) ($_POST['decision'] ?? ''), $uid, trim((string) ($_POST['note'] ?? '')));
        $msg = $r['ok'] ? 'قرر ✅' : ($r['reason'] . ' ❌');
    } elseif ($act === 'rq_execute') {
        $r = RQ::executeAndClose($conn, intval($_POST['req_id'] ?? 0), $uid, array(
            'decision' => 'approved',
            'decided_capacity' => (string) ($_SESSION['user']['role_name'] ?? $_SESSION['user']['role'] ?? ''),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'action_required' => trim((string) ($_POST['action_required'] ?? '')),
            'result_doc_ref' => trim((string) ($_POST['result_doc_ref'] ?? '')),
            'executed_summary' => trim((string) ($_POST['executed_summary'] ?? '')),
            'next_step' => trim((string) ($_POST['next_step'] ?? '')),
        ));
        $msg = $r['ok'] ? 'نفذ وأغلق بالرد التسعة ✅' : ($r['reason'] . ' ❌');
    } elseif ($act === 'rq_cancel') {
        $r = RQ::cancel($conn, intval($_POST['req_id'] ?? 0), $uid, trim((string) ($_POST['reason'] ?? '')));
        $msg = $r['ok'] ? 'ألغي وعكس أثره ✅' : ($r['reason'] . ' ❌');
    } else { $msg = 'فعل غير معروف ❌'; }
    ems_gov_flash_redirect('my_requests.php', $msg, 'GOV-INFO-200', '');
    exit();
}

/* ══ RPR-W15 · الإسقاطُ الحيُّ لطلباتِ صاحبِ الحساب ══════════════════════════
     مروحةُ دخولٍ على جداولِ المُلّاكِ بعمودِ صاحبِ الطلبِ المسجَّلِ في السجلِّ
     المركزيّ — ⛔ **ولا نسخةَ محلّيّةً ولا فهرسَ مخزَّن**. وتعديلُ الحالةِ عند
     المالكِ ينعكس هنا في القراءةِ التالية بلا مزامنة. */
require_once __DIR__ . '/../app/Services/Workspace/RequestLauncher.php';
$w15Catalogue = array();
$w15Projected = array();
try {
    $w15Gate = ems_tenant_db();
    if ($is_super_admin && $company_id <= 0) { $w15Gate = $w15Gate->forAllTenants('w15 my requests super view'); }
    $w15Catalogue = \App\Services\Workspace\RequestLauncher::catalogue($w15Gate, $company_id);
    $w15Projected = \App\Services\Workspace\RequestLauncher::projection(
        $conn, $w15Gate,
        array('id' => $uid, 'company_id' => $company_id,
              'role' => strval($_SESSION['user']['role'] ?? '')));
} catch (\Throwable $t) { error_log('w15 my_requests projection: ' . $t->getMessage()); }

/* ── القراءة: طلباتي + ما ينتظر معالجتي ─────────────────────────────────── */
$types = array();
$r = mysqli_query($conn, "SELECT code, name_ar, owner_dept, receiver, sla_hours, deliverable, approval_chain
                            FROM request_types WHERE status = 'active' ORDER BY display_order");
while ($x = mysqli_fetch_assoc($r)) { $types[] = $x; }

$co = $is_super_admin && $company_id <= 0 ? '1=1' : "rq.company_id = {$company_id}";
$mineRows = array(); $inboxRows = array();
$sql = "SELECT rq.*, rt.name_ar AS type_name, uh.name AS holder_name, rr.decision AS resp_decision,
               rr.result_doc_ref, rr.executed_summary, rr.next_step
          FROM requests rq
          JOIN request_types rt ON rt.code = rq.request_type_code
          LEFT JOIN users uh ON uh.id = rq.current_holder_user_id
          LEFT JOIN request_responses rr ON rr.request_id = rq.id
         WHERE {$co} AND (rq.requester_user_id = {$uid} OR rq.current_holder_user_id = {$uid})
         ORDER BY rq.id DESC LIMIT 300";
$r = mysqli_query($conn, $sql);
while ($r && ($x = mysqli_fetch_assoc($r))) {
    if (intval($x['current_holder_user_id']) === $uid && !in_array($x['status'], array('closed', 'cancelled', 'rejected'), true)) { $inboxRows[] = $x; }
    if (intval($x['requester_user_id']) === $uid) { $mineRows[] = $x; }
}

$STATE_AR = array('draft' => 'مسودة', 'submitted' => 'مرفوع', 'routed' => 'قيد التنفيذ', 'in_approval' => 'بانتظار الاعتماد',
    'approved' => 'معتمد', 'rejected' => 'مرفوضة', 'executing' => 'قيد التنفيذ', 'executed' => 'مكتملة',
    'closed' => 'مقفل', 'returned' => 'معادة', 'cancelled' => 'ملغاة');

/* «لماذا أرى هذا؟» للطلب — السلسلة الخماسية نفسها (AC-WFM-13) */
$explainRq = null;
if (isset($_GET['explain'])) {
    $exq = null;
    foreach (array_merge($mineRows, $inboxRows) as $q0) {
        if (intval($q0['id']) === intval($_GET['explain'])) { $exq = $q0; break; }
    }
    if ($exq) {
        $isRequester = intval($exq['requester_user_id']) === $uid;
        $isHolder = intval($exq['current_holder_user_id']) === $uid;
        $explainRq = array('complete' => ($isRequester || $isHolder), 'steps' => array(
            array('q' => 'ما أصل هذا العنصر؟', 'ok' => true,
                  'a' => 'طلب من قاموس الأنواع (' . $exq['request_type_code'] . ' · ' . $exq['type_name'] . ') — الورقة 04'),
            array('q' => 'بأي قاعدة وجه؟', 'ok' => true,
                  'a' => 'قاعدة القاموس: يستقبله وتعتمده سلسلته المعرفة — لا اجتهاد (WF-07)'),
            array('q' => 'بأي صفة أراه؟', 'ok' => ($isRequester || $isHolder),
                  'a' => $isRequester ? 'أنت مقدمه — تتابع أين توقف' : ($isHolder ? 'أنت حامله الحالي — القرار أو التنفيذ عندك' : 'لست طرفه — يحجب')),
            array('q' => 'ما نطاقي فيه؟', 'ok' => true,
                  'a' => 'كيانك وسياق الطلب (إدارة/مشروع) — والعزل محقون بنيويا'),
            array('q' => 'أصالة أم تفويضا؟', 'ok' => true, 'a' => 'أصالة بصفتك المذكورة'),
        ));
    }
}

$page_title = 'إيكوبيشن | طلباتي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'طلباتي';
    $header_icon = 'fa fa-file-signature';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    require_once __DIR__ . '/../includes/screen_contract.php';
    ems_screen_about('أقدم طلبا من القاموس الحاكم وأتابع أين توقف — والمعالجة قرار ثم تنفيذ بالرد التسعة لا تغيير حالة.');

    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لم تقدم طلبا بعد ولا ينتظر قرارك طلب', 'قدم طلبا من نموذج «تقديم طلب — من القاموس الحاكم» أعلاه');
    ?>

    <style>
    .mrq-card         { margin-bottom: 12px; }
    .mrq-card-explain { margin-bottom: 12px; border-right: 4px solid var(--c-6c5ce7, #6c5ce7); }
    .mrq-card-inbox   { margin-bottom: 12px; border-right: 4px solid var(--c-d4b06a, #d4b06a); }
    .mrq-close        { float: left; }
    .mrq-step         { margin: 4px 0; }
    .mrq-newform      { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
    .mrq-w280         { min-width: 280px; }
    .mrq-f1           { flex: 1; min-width: 220px; }
    .mrq-lbl          { font-size: .85rem; }
    .mrq-hint         { margin-top: 8px; font-size: .85rem; }
    .mrq-table        { width: 100%; }
    .mrq-subj         { white-space: normal; max-width: 240px; }
    .mrq-actcell      { min-width: 220px; }
    .mrq-inline-form  { display: inline; }
    .mrq-ib           { display: inline-block; }
    .mrq-subform      { margin-top: 4px; }
    .mrq-subform-wide { margin-top: 4px; max-width: 300px; }
    .mrq-mb4          { margin-bottom: 4px; }
    .mrq-why          { font-size: .78rem; }
    .mrq-reason       { color: var(--c-9a6a00, #9a6a00); font-size: .78rem; }
    .mrq-resp         { white-space: normal; max-width: 260px; font-size: .85rem; }
    </style>

    <?php if ($explainRq): ?>
    <div class="card mrq-card-explain">
        <div class="card-header"><strong><i class="fas fa-circle-question"></i> لماذا يظهر لي هذا الطلب؟</strong>
            <a class="btn btn-sm btn-secondary mrq-close" href="my_requests.php">إغلاق</a></div>
        <div class="card-body">
            <?php foreach ($explainRq['steps'] as $i => $s): ?>
                <div class="mrq-step"><span class="badge <?php echo $s['ok'] ? 'bg-success' : 'bg-danger'; ?>"><?php echo $i + 1; ?></span>
                    <strong><?php echo htmlspecialchars($s['q']); ?></strong> — <?php echo htmlspecialchars($s['a']); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ══ RPR-W15 · إطلاقُ طلبٍ يملك النطاقُ تعريفَه ═══════════════════
             النوعُ من السجلِّ المركزيّ، والسجلُّ يُنشأ **عند إدارتِه المالكة**،
             وهذه الشاشةُ تعرض حالتَه ولا تخزّنها. */ ?>
    <?php if ($w15Catalogue): ?>
    <div class="card mrq-card">
        <div class="card-header"><strong><i class="fas fa-paper-plane"></i> إطلاق طلب ينشأ عند إدارته المالكة</strong></div>
        <div class="card-body">
            <form method="post" class="mrq-newform">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rq_launch">
                <div class="mrq-w280"><label class="mrq-lbl" for="w15_type">نوع الطلب</label>
                    <select name="type_code" class="form-control" required id="w15_type">
                        <option value="">— اختر —</option>
                        <?php foreach ($w15Catalogue as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['type_code']); ?>">
                            <?php echo htmlspecialchars($t['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="mrq-f1"><label class="mrq-lbl" for="w15_note">التفاصيل</label>
                    <input name="payload[description]" class="form-control" maxlength="500" id="w15_note"></div>
                <div class="mrq-f1"><label class="mrq-lbl" for="w15_from">من تاريخ</label>
                    <input type="date" name="payload[date_from]" class="form-control" id="w15_from"></div>
                <div class="mrq-f1"><label class="mrq-lbl" for="w15_to">إلى تاريخ</label>
                    <input type="date" name="payload[date_to]" class="form-control" id="w15_to"></div>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> إطلاق</button>
            </form>
            <div class="mrq-hint">الطلب ينشأ في سجل الإدارة المالكة وحالته تعرض هنا كما هي عندها.</div>
        </div>
    </div>

    <div class="card mrq-card">
        <div class="card-header"><strong><i class="fas fa-list"></i> طلباتي عند إداراتها (<?php echo count($w15Projected); ?>)</strong></div>
        <div class="card-body">
            <div class="table-wrap"><table class="data-table mrq-table">
                <thead><tr><th>النوع</th><th>الإدارة المالكة</th><th>الرقم عند مالكه</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                <tbody>
                <?php foreach ($w15Projected as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['type_name']); ?></td>
                        <td><?php echo htmlspecialchars($p['owner_dept']); ?></td>
                        <td><?php echo (int) $p['row_id']; ?></td>
                        <td><?php echo htmlspecialchars($p['state']); ?></td>
                        <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mrq-card">
        <div class="card-header"><strong><i class="fas fa-plus-circle"></i> تقديم طلب — من القاموس الحاكم (<?php echo count($types); ?> نوعا)</strong></div>
        <div class="card-body">
            <form method="post" class="mrq-newform">
        <?= csrf_field() ?>
                <input type="hidden" name="action" value="rq_submit">
                <?php if ($is_super_admin && $company_id <= 0): ?><input type="hidden" name="company_id" value="4"><?php endif; ?>
                <div class="mrq-w280"><label class="mrq-lbl" for="emsf_1202_ee646">نوع الطلب</label>
                    <select name="type_code" class="form-control" required onchange="rqHint(this)" id="emsf_1202_ee646">
                        <option value="">— اختر —</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['code']); ?>"
                            data-hint="يستقبله: <?php echo htmlspecialchars($t['receiver']); ?> · السلسلة: <?php echo htmlspecialchars($t['approval_chain']); ?> · المهلة <?php echo intval($t['sla_hours']); ?> ساعة · المخرج: <?php echo htmlspecialchars($t['deliverable']); ?>">
                            <?php echo htmlspecialchars($t['code'] . ' · ' . $t['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="mrq-f1"><label class="mrq-lbl" for="emsf_1203_03af9">الموضوع</label>
                    <input name="title" class="form-control" required maxlength="300" id="emsf_1203_03af9"></div>
                <div class="mrq-f1"><label class="mrq-lbl" for="emsf_1204_8fe54">التفاصيل</label>
                    <input name="details" class="form-control" maxlength="500" id="emsf_1204_8fe54"></div>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> تقديم</button>
            </form>
            <div id="rqHint" class="text-muted mrq-hint"></div>
        </div>
    </div>
    <script>function rqHint(s){var o=s.options[s.selectedIndex];document.getElementById('rqHint').textContent=o?o.getAttribute('data-hint')||'':'';}</script>

    <?php if ($inboxRows): ?>
    <div class="card mrq-card-inbox">
        <div class="card-header"><strong><i class="fas fa-inbox"></i> تنتظر معالجتي</strong>
            <span class="badge bg-warning"><?php echo count($inboxRows); ?></span></div>
        <div class="card-body"><div class="table-responsive">
            <table class="alltables display no-datatable mrq-table">
                <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>المقدم منذ</th><th>مهلته</th><th>الحالة</th><th>المعالجة</th></tr></thead>
                <tbody><?php foreach ($inboxRows as $q): $id = intval($q['id']); ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string) $q['request_no']); ?></code></td>
                    <td><?php echo htmlspecialchars((string) $q['type_name']); ?></td>
                    <td class="mrq-subj"><?php echo htmlspecialchars((string) $q['title']); ?></td>
                    <td><?php echo htmlspecialchars((string) $q['submitted_at']); ?></td>
                    <td><?php echo htmlspecialchars((string) $q['sla_due_at']); ?></td>
                    <td><?php echo htmlspecialchars($STATE_AR[$q['status']] ?? $q['status']); ?></td>
                    <td class="mrq-actcell">
                        <?php if (in_array($q['status'], array('submitted', 'routed', 'in_approval'), true)): ?>
                            <form method="post" class="mrq-inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="rq_decide"><input type="hidden" name="req_id" value="<?php echo $id; ?>"><input type="hidden" name="decision" value="approve">
                                <button class="btn btn-sm btn-primary">اعتماد</button></form>
                            <details class="mrq-ib"><summary class="btn btn-sm btn-danger mrq-ib">رفض/إعادة</summary>
                                <form method="post" class="mrq-subform">
        <?= csrf_field() ?><input type="hidden" name="action" value="rq_decide"><input type="hidden" name="req_id" value="<?php echo $id; ?>">
                                    <select name="decision" aria-label="نوع القرار — إعادة لاستكمال أو رفض" class="form-control form-control-sm mrq-mb4"><option value="return">إعادة لاستكمال</option><option value="reject">رفض</option></select>
                                    <input name="note" class="form-control form-control-sm mrq-mb4" placeholder="السبب (إلزامي)" required aria-label="السبب (إلزامي)">
                                    <button class="btn btn-sm btn-danger">تأكيد</button></form></details>
                        <?php elseif ($q['status'] === 'approved'): ?>
                            <details><summary class="btn btn-sm btn-primary">تنفيذ وإغلاق (الرد التسعة)</summary>
                                <form method="post" class="mrq-subform-wide">
        <?= csrf_field() ?><input type="hidden" name="action" value="rq_execute"><input type="hidden" name="req_id" value="<?php echo $id; ?>">
                                    <input name="result_doc_ref" aria-label="المستند الناتج عن التنفيذ (إلزامي)" class="form-control form-control-sm mrq-mb4" placeholder="⑦ المستند الناتج (إلزامي)" required>
                                    <input name="executed_summary" aria-label="ملخص التنفيذ الذي تم (إلزامي)" class="form-control form-control-sm mrq-mb4" placeholder="⑧ التنفيذ الذي تم (إلزامي)" required>
                                    <input name="action_required" aria-label="الإجراء المطلوب فعله" class="form-control form-control-sm mrq-mb4" placeholder="⑥ ما يجب فعله">
                                    <input name="next_step" aria-label="الخطوة اللاحقة" class="form-control form-control-sm mrq-mb4" placeholder="⑨ الخطوة اللاحقة">
                                    <input name="notes" aria-label="ملاحظات الرد" class="form-control form-control-sm mrq-mb4" placeholder="⑤ الملاحظات">
                                    <button class="btn btn-sm btn-primary">إغلاق بالرد الكامل</button></form></details>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div></div>
    </div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h6><i class="fas fa-list"></i> طلباتي المقدمة — وكل طلب يعرف أين توقف (AC-WFM-07)</h6>
        <div class="table-responsive">
        <table class="alltables display" id="myRequestsTable">
            <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>قدم</th><th>الحالة</th>
                <th>عند من الآن</th><th>القرار والرد</th><th>إجراء</th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                </tr></thead>
            <tbody><?php foreach ($mineRows as $q): $id = intval($q['id']); ?>
            <tr>
                <td><code><?php echo htmlspecialchars((string) $q['request_no']); ?></code><br>
                    <a href="my_requests.php?explain=<?php echo $id; ?>" class="mrq-why"
                       title="السلسلة الخماسية"><i class="fas fa-circle-question"></i> لماذا؟</a></td>
                <td><?php echo htmlspecialchars((string) $q['type_name']); ?></td>
                <td class="mrq-subj"><?php echo htmlspecialchars((string) $q['title']); ?></td>
                <td><?php echo htmlspecialchars((string) $q['submitted_at']); ?></td>
                <td><?php echo htmlspecialchars($STATE_AR[$q['status']] ?? $q['status']); ?>
                    <?php if ($q['status_reason']): ?><div class="mrq-reason"><?php echo htmlspecialchars((string) $q['status_reason']); ?></div><?php endif; ?></td>
                <td><?php echo htmlspecialchars((string) ($q['holder_name'] ?: ($q['status'] === 'closed' ? 'أغلق' : '—'))); ?></td>
                <td class="mrq-resp">
                    <?php if ($q['resp_decision']): ?>
                        <strong><?php echo htmlspecialchars((string) $q['resp_decision']); ?></strong>
                        · المستند: <?php echo htmlspecialchars((string) $q['result_doc_ref']); ?>
                        · <?php echo htmlspecialchars((string) $q['executed_summary']); ?>
                        <?php if ($q['next_step']): ?>· التالي: <?php echo htmlspecialchars((string) $q['next_step']); ?><?php endif; ?>
                    <?php else: ?>—<?php endif; ?></td>
                <td><?php if (in_array($q['status'], array('submitted', 'routed', 'returned'), true)): ?>
                    <details><summary class="btn btn-sm btn-danger mrq-ib">سحب</summary>
                        <form method="post" class="mrq-subform">
        <?= csrf_field() ?><input type="hidden" name="action" value="rq_cancel"><input type="hidden" name="req_id" value="<?php echo $id; ?>">
                            <input name="reason" class="form-control form-control-sm mrq-mb4" placeholder="السبب" required aria-label="السبب">
                            <button class="btn btn-sm btn-danger">تأكيد السحب</button></form></details>
                    <?php endif; ?></td>
            </tr>
            <?php endforeach; ?></tbody>
        </table>
        </div>
    </div></div>
</div>

<?php
/**
 * Portal/my_kpi.php — لوحتي (WI-KPI · IAM-023 السبعة العامة · قرار 9)
 * ───────────────────────────────────────────────────────────────────────────
 * «المؤشرُ تجميعُ الإنجاز لا الإنجاز»: اشتقاقٌ آليٌّ من عناصر العمل والطلبات
 * والإنجازات والمهل — صفرُ إدخال. وكلُّ رقمٍ يقود لمصدره (لا رقم بلا رابط).
 * المؤشر الذي لا يخص الصفة لا يُعرض صفرًا مضلِّلًا (IAM-024).
 * لوحة المدير: نطاق الهيكل المباشر + تعمّق (WFM-071/072 · قرار 9).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once '../includes/permissions_helper.php';
require_once '../includes/resolve_manager.php';
require_once '../app/Services/Work/AchievementService.php';

use App\Services\Work\AchievementService as ACH;

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', ''); exit(); }

$__pp = check_page_permissions($conn, 'Portal/my_kpi.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/my_kpi.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}

/* المدى الحر (IAM-023: بين تاريخين) */
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
$co   = ems_scope_company($conn);

function kpi_row(mysqli $conn, $co, $userId, $from, $to)
{
    $u = intval($userId);
    $w = "company_id = " . intval($co);
    $out = array();
    // ① المهام: المفتوح والمغلق ونسبة الالتزام بالمهلة
    $r = mysqli_query($conn, "SELECT
            SUM(status NOT IN ('closed_accepted','cancelled','rejected')) open_n,
            SUM(status = 'closed_accepted' AND closed_at BETWEEN '{$from}' AND DATE_ADD('{$to}', INTERVAL 1 DAY)) closed_n,
            SUM(status = 'closed_accepted' AND closed_at BETWEEN '{$from}' AND DATE_ADD('{$to}', INTERVAL 1 DAY)
                AND (due_at IS NULL OR closed_at <= due_at)) closed_on_time,
            SUM(status = 'overdue') overdue_n,
            SUM(status = 'blocked') blocked_n
        FROM work_items WHERE {$w} AND assigned_user_id = {$u}");
    $out['tasks'] = mysqli_fetch_assoc($r) ?: array();
    // ② زمن الاستجابة (الإسناد → الاستلام) بالساعات — متوسطًا
    $r = mysqli_query($conn, "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at))/60, 1) rt
        FROM work_items WHERE {$w} AND assigned_user_id = {$u}
          AND accepted_at IS NOT NULL AND created_at BETWEEN '{$from}' AND DATE_ADD('{$to}', INTERVAL 1 DAY)");
    $out['response_h'] = ($x = mysqli_fetch_assoc($r)) ? $x['rt'] : null;
    // ③ الطلبات المقدمة وما أُغلق منها
    $r = mysqli_query($conn, "SELECT COUNT(*) n, SUM(status='closed') closed_n
        FROM requests WHERE {$w} AND requester_user_id = {$u}
          AND created_at BETWEEN '{$from}' AND DATE_ADD('{$to}', INTERVAL 1 DAY)");
    $out['requests'] = mysqli_fetch_assoc($r) ?: array();
    // ④ الإنجاز الحي (المعكوس خارج المؤشر — AC-WFM-14)
    $out['ach'] = ACH::personSummary($conn, $co, $u, $from, $to);
    return $out;
}

$mine = kpi_row($conn, $co, $uid, $from, $to);

/* لوحة المدير: المستوى المباشر + تعمّق درجة (قرار 9) */
$depth = intval($_GET['depth'] ?? 1);
$team = ems_manager_scope_user_ids($conn, $uid, max(1, min(3, $depth)));
$teamRows = array();
if ($team) {
    $in = implode(',', $team);
    $r = mysqli_query($conn, "SELECT id, name FROM users WHERE id IN ($in) ORDER BY name");
    while ($r && ($x = mysqli_fetch_assoc($r))) {
        $teamRows[] = array('u' => $x, 'k' => kpi_row($conn, $co, intval($x['id']), $from, $to));
    }
}

$page_title = 'إيكوبيشن | لوحتي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';

function kpi_card($label, $value, $link, $hint = '')
{
    $v = htmlspecialchars((string) $value);
    $l = htmlspecialchars($label);
    $h = htmlspecialchars($hint);
    echo "<a href=\"{$link}\" style=\"text-decoration:none;color:inherit\"><div class=\"card\" style=\"min-width:150px;text-align:center\" title=\"{$h}\">
        <div class=\"card-body\" style=\"padding:12px\"><div style=\"font-size:1.6rem;font-weight:700\">{$v}</div>
        <div class=\"text-muted\" style=\"font-size:.85rem\">{$l}</div></div></div></a>";
}
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'لوحتي';
    $header_icon = 'fa fa-gauge';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    require_once __DIR__ . '/../includes/screen_contract.php';
    ems_screen_about('مؤشراتي مشتقة آليا من عملي — كل رقم يقود لمصدره، وفريقي من الهيكل لا من قوائم.');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا مؤشرات عمل في هذا المدى', 'وسع المدى بين التاريخين أعلاه — أو ابدأ بمهمة من «مهامي» ليشتق المؤشر');
    ?>
    <style>
    .mkpi-filter  { display: flex; gap: 10px; align-items: end; margin-bottom: 12px; flex-wrap: wrap; }
    .mkpi-lbl     { font-size: .85rem; }
    .mkpi-cards   { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
    .mkpi-table   { width: 100%; }
    .mkpi-hint    { font-size: .85rem; }
    .mkpi-overdue { color: var(--c-b02a37, #b02a37); font-weight: 700; }
    </style>
        <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
    <form method="get" class="mkpi-filter">
        <div><label class="mkpi-lbl" for="emsf_377_da0ec">من</label><input type="date" name="from" aria-label="بداية مدى المؤشرات" class="form-control" value="<?php echo htmlspecialchars($from); ?>" id="emsf_377_da0ec"></div>
        <div><label class="mkpi-lbl" for="emsf_378_7cf8e">إلى</label><input type="date" name="to" aria-label="نهاية مدى المؤشرات" class="form-control" value="<?php echo htmlspecialchars($to); ?>" id="emsf_378_7cf8e"></div>
        <?php if ($team): ?><div><label class="mkpi-lbl" for="emsf_379_76884">عمق الفريق</label>
            <select name="depth" class="form-control" id="emsf_379_76884"><option value="1" <?php echo $depth === 1 ? 'selected' : ''; ?>>مباشر</option>
            <option value="2" <?php echo $depth === 2 ? 'selected' : ''; ?>>مستويان</option></select></div><?php endif; ?>
        <button class="btn btn-primary">تحديث</button>
    </form>
        </div>
    </div>

    <h6><i class="fas fa-user"></i> مؤشراتي — كل رقم يقود لمصدره</h6>
    <div class="mkpi-cards">
        <?php
        $t = $mine['tasks'];
        $onTimePct = intval($t['closed_n'] ?? 0) > 0 ? round(100 * intval($t['closed_on_time']) / intval($t['closed_n'])) . '٪' : '—';
        kpi_card('مهامي المفتوحة', intval($t['open_n'] ?? 0), 'my_tasks.php?view=today');
        kpi_card('أغلق في المدى', intval($t['closed_n'] ?? 0), 'my_tasks.php?view=today', 'قبول المتحقق — لا التصريح');
        kpi_card('الالتزام بالمهلة', $onTimePct, 'my_tasks.php?view=late', 'من المغلق داخل مهلته');
        kpi_card('متأخرة الآن', intval($t['overdue_n'] ?? 0), 'my_tasks.php?view=late');
        kpi_card('متعطلة', intval($t['blocked_n'] ?? 0), 'my_tasks.php?view=blocked');
        kpi_card('زمن الاستجابة (س)', $mine['response_h'] ?? '—', 'my_tasks.php?view=today', 'من الإسناد إلى الاستلام');
        kpi_card('طلباتي', intval($mine['requests']['n'] ?? 0) . ' / ' . intval($mine['requests']['closed_n'] ?? 0) . ' مغلق', 'my_requests.php');
        kpi_card('إنجازي الحي', intval($mine['ach']['total'] ?? 0), 'my_achievement.php', 'تنفيذي ' . intval($mine['ach']['executive']) . ' · إشرافي ' . intval($mine['ach']['supervisory']));
    ?>
    </div>

    <?php if ($teamRows): ?>
    <h6><i class="fas fa-people-group"></i> فريقي — من الهيكل لا من قوائم (WFM-072)</h6>
    <div class="card"><div class="card-body"><div class="table-responsive">
        <table class="alltables display no-datatable mkpi-table">
            <thead><tr><th>الموظف</th><th>مفتوح</th><th>متأخر</th><th>متعطل</th><th>أغلق بالمدى</th>
                <th>الالتزام بالمهلة</th><th>استجابة (س)</th><th>إنجاز حي</th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody><?php foreach ($teamRows as $row): $k = $row['k']; $t = $k['tasks'];
                $pct = intval($t['closed_n'] ?? 0) > 0 ? round(100 * intval($t['closed_on_time']) / intval($t['closed_n'])) . '٪' : '—'; ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) $row['u']['name']); ?></td>
                    <td><?php echo intval($t['open_n'] ?? 0); ?></td>
                    <td class="<?php echo intval($t['overdue_n'] ?? 0) > 0 ? 'mkpi-overdue' : ''; ?>"><?php echo intval($t['overdue_n'] ?? 0); ?></td>
                    <td><?php echo intval($t['blocked_n'] ?? 0); ?></td>
                    <td><?php echo intval($t['closed_n'] ?? 0); ?></td>
                    <td><?php echo $pct; ?></td>
                    <td><?php echo htmlspecialchars((string) ($k['response_h'] ?? '—')); ?></td>
                    <td><?php echo intval($k['ach']['total'] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div></div></div>
    <?php else: ?>
    <div class="text-muted mkpi-hint"><i class="fas fa-circle-info"></i>
        لا مرؤوسين في الهيكل — لوحة الفريق تظهر للمديرين وحدهم (لا تعرض صفرا مضللا · IAM-024)</div>
    <?php endif; ?>
</div>

<?php
/**
 * Activity Logs — Main Screen
 * /ActivityLogs/activity_logs.php
 *
 * Phase 1: Role Cards overview.
 * Phase 2: Log table via DataTables + AJAX endpoint for clear-logs action.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';
require_once '../app/bootstrap.php';
require_once '../includes/permissions_helper.php';

use App\Repositories\ActivityLogRepository;
use App\Services\ActivityLogService;

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

$repo = new ActivityLogRepository($conn);

// ── AJAX: cursor-paginated log rows ──────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'logs') {
    header('Content-Type: application/json; charset=utf-8');

    $filters = [];
    $intKeys = ['company_id', 'user_id', 'role_id', 'project_id', 'record_id', 'response_status'];
    $strKeys = ['action_type', 'module_name', 'screen_name', 'http_method', 'date_from', 'date_to'];

    foreach ($intKeys as $k) {
        if (!empty($_GET[$k]))
            $filters[$k] = intval($_GET[$k]);
    }
    foreach ($strKeys as $k) {
        if (!empty($_GET[$k]))
            $filters[$k] = trim($_GET[$k]);
    }

    if (!$is_super_admin && $company_id > 0) {
        $filters['company_id'] = $company_id;
    }

    $afterCreatedAt = trim((string) ($_GET['after_created_at'] ?? ''));
    $afterId = intval($_GET['after_id'] ?? 0);
    $limit = min(intval($_GET['limit'] ?? 2000), 5000);

    $rows = $repo->getPage($filters, $afterCreatedAt, $afterId, $limit);
    echo json_encode(
        ['success' => true, 'rows' => $rows, 'count' => count($rows)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit();
}

// ── AJAX: single log detail ───────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detail') {
    header('Content-Type: application/json; charset=utf-8');
    $id = intval($_GET['id'] ?? 0);
    $row = $id > 0 ? $repo->getDetail($id) : null;
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'السجل غير موجود']);
        exit();
    }
    if (!$is_super_admin && $company_id > 0 && intval($row['company_id'] ?? 0) !== $company_id) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit();
    }
    echo json_encode(['success' => true, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// ── AJAX: clear logs for a specific role ─────────────────────────────────
if (isset($_POST['ajax']) && $_POST['ajax'] === 'clear_logs') {
    header('Content-Type: application/json; charset=utf-8');

    // Only super-admin or company admin allowed.
    if (!$is_super_admin && $company_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit();
    }

    $roleId = intval($_POST['role_id'] ?? 0);
    if ($roleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرّف الدور غير صالح']);
        exit();
    }

    try {
        // Build DELETE query scoped by role (and company for non-super-admin).
        if ($is_super_admin) {
            $stmt = $conn->prepare("DELETE FROM activity_logs WHERE role_id = ?");
            $stmt->bind_param('i', $roleId);
        } else {
            $stmt = $conn->prepare("DELETE FROM activity_logs WHERE role_id = ? AND company_id = ?");
            $stmt->bind_param('ii', $roleId, $company_id);
        }
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => 'تم تفريغ ' . $deleted . ' سجل بنجاح'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()]);
    }
    exit();
}

// ── Phase 1: role cards ───────────────────────────────────────────────────
$roleSummary = $repo->getRoleSummary($is_super_admin ? 0 : $company_id);

// ── Phase 2: initial rows (SSR for DataTables) ────────────────────────────
$selectedRoleId = isset($_GET['role_id']) ? intval($_GET['role_id']) : 0;
$initialRows = [];
if ($selectedRoleId > 0) {
    // Load up to 5000 rows; DataTables handles client-side paging/search from here.
    $initialRows = $repo->getInitialPage(
        $is_super_admin ? 0 : $company_id,
        $selectedRoleId,
        5000
    );
}

$currentRoleCard = null;
if ($selectedRoleId > 0) {
    foreach ($roleSummary as $c) {
        if (intval($c['role_id']) === $selectedRoleId) {
            $currentRoleCard = $c;
            break;
        }
    }
}

$selectedRoleName = htmlspecialchars($currentRoleCard['role_name'] ?? 'جميع الإدارات', ENT_QUOTES, 'UTF-8');
$selectedRoleCount = number_format(intval($currentRoleCard['total_logs'] ?? 0));

$roleIconMap = [
    '-1' => ['icon' => 'fa-user-shield', 'color' => '#c84a0c', 'bg' => '#fff3ee'],
    '1' => ['icon' => 'fa-briefcase', 'color' => '#1255a8', 'bg' => '#eef4ff'],
    '2' => ['icon' => 'fa-truck', 'color' => '#16a34a', 'bg' => '#f0fdf4'],
    '3' => ['icon' => 'fa-hard-hat', 'color' => '#9333ea', 'bg' => '#faf5ff'],
    '4' => ['icon' => 'fa-cogs', 'color' => '#0891b2', 'bg' => '#f0f9ff'],
    '5' => ['icon' => 'fa-user', 'color' => '#ca8a04', 'bg' => '#fefce8'],
    '6' => ['icon' => 'fa-clock', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    '7' => ['icon' => 'fa-eye', 'color' => '#0d9488', 'bg' => '#f0fdfa'],
    '8' => ['icon' => 'fa-eye', 'color' => '#be185d', 'bg' => '#fdf2f8'],
    '9' => ['icon' => 'fa-exclamation', 'color' => '#b45309', 'bg' => '#fffbeb'],
];

$actionLabels = [
    'view' => ['label' => 'عرض', 'badge' => 'bg-secondary'],
    'create' => ['label' => 'إنشاء', 'badge' => 'bg-success'],
    'update' => ['label' => 'تعديل', 'badge' => 'bg-warning'],
    'delete' => ['label' => 'حذف', 'badge' => 'bg-danger'],
    'send' => ['label' => 'إرسال', 'badge' => 'bg-primary'],
    'login' => ['label' => 'دخول', 'badge' => 'bg-primary'],
    'logout' => ['label' => 'خروج', 'badge' => 'bg-dark'],
    'export' => ['label' => 'تصدير', 'badge' => 'bg-info text-dark'],
    'print' => ['label' => 'طباعة', 'badge' => 'bg-info text-dark'],
    'search' => ['label' => 'بحث', 'badge' => 'bg-light text-dark border'],
    'click' => ['label' => 'نقرة', 'badge' => 'bg-secondary'],
];

function renderLogRow(array $row, array $actionLabels): string
{
    $e = fn($s) => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
    $t = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '—';

    $actionType = $row['action_type'] ?? '';
    $info = $actionLabels[$actionType] ?? ['label' => $actionType ?: '—', 'badge' => 'bg-secondary'];
    $badge = '<span class="badge ' . $info['badge'] . '">' . $e($info['label']) . '</span>';

    $status = intval($row['response_status'] ?? 0);
    $statusClass = $status >= 500 ? 'text-danger fw-bold'
        : ($status >= 400 ? 'text-warning fw-bold'
            : ($status >= 200 ? 'text-success' : 'text-muted'));

    $statusDisplay = ($status !== 0) ? strval($status) : '—';
    $recordDisplay = ($row['record_id'] !== null && $row['record_id'] !== '') ? $e($row['record_id']) : '—';
    $buttonDisplay = ($row['button_name'] !== null && $row['button_name'] !== '') ? $e($row['button_name']) : '—';

    return '<tr data-id="' . intval($row['id']) . '">
        <td class="px-3 text-nowrap small" data-date="' . substr($e($t), 0, 10) . '">' . $e($t) . '</td>
        <td class="small">' . (
            ($row['employee_name'] ?? '') !== ''
                ? '<strong>' . $e($row['employee_name']) . '</strong><br><small class="text-muted"><i class="fa fa-user-circle"></i> ' . $e($row['user_name'] ?? ($row['user_id'] ?? '—')) . '</small>'
                : $e($row['user_name'] ?? ($row['user_id'] ?? '—')) . '<br><small class="text-muted">— غير مرتبط بموظف —</small>'
        ) . '</td>
        <td class="small">' . $e($row['role_name'] ?? '—') . '</td>
        <td class="small">' . $e($row['module_name'] ?? '—') . '</td>
        <td class="small">' . $e($row['screen_name'] ?? '—') . '</td>
        <td data-action="' . $e($actionType) . '">' . $badge . '</td>
        <td class="small text-truncate" style="max-width:100px" title="' . $e($row['button_name'] ?? '') . '">' . $buttonDisplay . '</td>
        <td class="small">' . $recordDisplay . '</td>
        <td class="small ' . $statusClass . '">' . $statusDisplay . '</td>
        <td class="text-center">
            <button class="action-btn view detail-btn"
                    data-id="' . intval($row['id']) . '" title="تفاصيل">
                <i class="fa fa-eye"></i>
            </button>
        </td>
        <td class="d-none">' . $e($row['http_method'] ?? '') . '</td>
    </tr>';
}

$page_title = 'سجل النشاطات';
?>
<?php require_once '../inheader.php'; ?>
<?php require_once '../insidebar.php'; ?>

<div class="main activity-logs-main ems-unified-page-shell">


        <?php
        // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
        $header_icon       = 'fa fa-history';
        $header_title_html = 'سجل النشاطات
                <p class="small mb-0" style="color: #fff;">تتبع جميع عمليات المستخدمين في النظام</p>';
        $header_actions = array();
        // ── نظام Excel الموحّد (تصدير فقط — سجل تدقيق) ──
        require_once __DIR__ . '/../includes/excel_ui.php';
        foreach (ems_excel_header_actions('activity_logs', 'سجل النشاطات', false, ['exportOnly' => true]) as $__xlAction) { $header_actions[] = $__xlAction; }
        $header_back    = ($selectedRoleId > 0)
            ? array('href' => 'activity_logs.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع')
            : false;
        include(__DIR__ . '/../includes/page_header.php');
        ?>



        <!-- ══════════════════════════════════════════
             PHASE 1 — Role Cards
        ═══════════════════════════════════════════ -->
        <?php if ($selectedRoleId === 0): ?>
            <?php if (empty($roleSummary)): ?>
                <div class="alert alert-info text-center mb-2">
                    <i class="fa fa-info-circle me-2"></i>
                    لا توجد سجلات نشاط حتى الآن.
                </div>
            <?php else: ?>
                <div id="roleCardsGrid" class="mb-4 role-cards-flex">
                    <?php foreach ($roleSummary as $card):
                        $rid = strval($card['role_id'] ?? '');
                        $ico = $roleIconMap[$rid] ?? ['icon' => 'fa-user', 'color' => '#6b7280', 'bg' => '#f9fafb'];
                        $name = htmlspecialchars($card['role_name'] ?? 'دور #' . $rid, ENT_QUOTES, 'UTF-8');
                        $total = number_format(intval($card['total_logs']));
                        $lastAt = $card['last_activity'] ? date('Y-m-d H:i', strtotime($card['last_activity'])) : '—';
                        ?>
                        <div class="role-card-wrap">
                            <a href="activity_logs.php?role_id=<?= intval($card['role_id']) ?>" class="text-decoration-none">
                                <div class="card h-100 role-card">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <div class="activity-role-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                            <i class="fa <?= $ico['icon'] ?> fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="role-card-name text-truncate"><?= $name ?></div>
                                            <div class="role-card-last">
                                                <i class="fa fa-clock me-1"></i>آخر نشاط: <?= $lastAt ?>
                                            </div>
                                        </div>
                                        <div class="role-card-stat">
                                            <span class="role-card-stat-num"><?= $total ?></span>
                                            <span class="role-card-stat-label">سجل</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════
             PHASE 2 — Log Table
        ═══════════════════════════════════════════ -->
        <?php if ($selectedRoleId > 0):
            $rid = strval($selectedRoleId);
            $ico = $roleIconMap[$rid] ?? ['icon' => 'fa-user', 'color' => '#6b7280', 'bg' => '#f9fafb'];
            ?>



            <!-- Filters Bar -->
            <form class="allforms allforms-visible activity-filters-form" onsubmit="return false;">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fa fa-filter"></i> فلترة سجلات النشاط</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">

                        <!-- نوع الإجراء — يبحث على data-action في الـ <td> -->
                        <div class="form-group">
                            <label>نوع الإجراء</label>
                            <select id="f_action_type">
                                <option value="">الكل</option>
                                <?php foreach ($actionLabels as $k => $v): ?>
                                    <option value="<?= $k ?>"><?= $v['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- من تاريخ -->
                        <div class="form-group">
                            <label>من تاريخ</label>
                            <input type="date" id="f_date_from">
                        </div>

                        <!-- إلى تاريخ -->
                        <div class="form-group">
                            <label>إلى تاريخ</label>
                            <input type="date" id="f_date_to">
                        </div>

                        <!-- نوع الميثود — column(9 hidden) -->
                        <div class="form-group">
                            <label>طريقة HTTP</label>
                            <select id="f_http_method">
                                <option value="">الكل</option>
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>

                        <!-- أزرار -->
                        <div class="form-group allforms-span-full activity-filter-actions">
                            <button type="button" class="btn-cancel" id="resetFiltersBtn">
                                <i class="fa fa-times me-1"></i>إعادة
                            </button>
                            <button type="button" class="btn-save activity-clear-btn" id="clearLogsBtn"
                                data-role-id="<?= intval($selectedRoleId) ?>"
                                data-role-name="<?= htmlspecialchars($currentRoleCard['role_name'] ?? 'الدور #' . $selectedRoleId, ENT_QUOTES) ?>">
                                <i class="fa fa-trash me-1"></i>تفريغ السجلات
                            </button>
                        </div>

                    </div>
                </div>
            </form>

            <!-- Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="alltables display nowrap" id="logsTable">
                            <thead>
                                <tr>
                                    <th class="px-3" style="min-width:140px">التاريخ والوقت</th><!-- col 0 -->
                                    <th>الموظف / الحساب</th><!-- col 1 -->
                                    <th>الدور</th><!-- col 2 -->
                                    <th>الوحدة</th><!-- col 3 -->
                                    <th>الشاشة</th><!-- col 4 -->
                                    <th>الإجراء</th><!-- col 5 -->
                                    <th>الزر</th><!-- col 6 -->
                                    <th>رقم السجل</th><!-- col 7 -->
                                    <th>الاستجابة</th><!-- col 8 -->
                                    <th class="text-center" data-orderable="false">تفاصيل</th><!-- col 9 -->
                                    <th class="d-none">http_method</th><!-- col 10 hidden -->
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <?php foreach ($initialRows as $row): ?>
                                    <?php echo renderLogRow($row, $actionLabels); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>
</div>

<style>
    /* ═══════════════════════════════════════════════════════════════
       Activity Logs — موحّد مع ثيم النظام الأصفر الرسمي
       (لوحة التحكم + صفحة العملاء): بطاقات بيضاء، حدود --bdr،
       لمسة ذهبية على الحافة، ظلال --sh، وتدرّجات ذهبية للأيقونات.
    ═══════════════════════════════════════════════════════════════ */

    /* ── خلفية الشاشة بيضاء + كل الكاردات رمادي فاتح ── */
    body:has(.activity-logs-main) {
        background: #fff !important;
    }

    .ems-site .main.activity-logs-main {
        background: #fff !important;
    }

    /* خصوصية أعلى من قاعدة النظام العامة 'body.ems-site .main .card { background:#fff }' */
    body.ems-site .main.activity-logs-main .card {
        background: var(--light-gray) !important;
    }

    .activity-logs-main .activity-page-hero-icon {
        width: 50px !important;
        height: 50px !important;
        border-radius: 50% !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: linear-gradient(135deg, var(--or), var(--or2)) !important;
        color: #1f1f1f !important;
        font-size: 1.2rem !important;
        box-shadow: 0 4px 12px rgba(244, 197, 66, .4) !important;
        flex-shrink: 0 !important;
    }

    .activity-logs-main .activity-page-hero-content {
        display: flex !important;
        flex-direction: column !important;
        gap: 2px !important;
        min-width: 0 !important;
    }

    .activity-logs-main .activity-page-hero-label {
        color: var(--t3) !important;
        font-size: .78rem !important;
        font-weight: 700 !important;
        letter-spacing: .02em !important;
    }

    .activity-logs-main .activity-page-hero-title {
        margin: 0 !important;
        font-size: 1.1rem !important;
        font-weight: 900 !important;
        color: var(--t1) !important;
    }

    .activity-logs-main .activity-page-hero-count {
        margin-inline-start: auto !important;
        padding: 7px 14px !important;
        border-radius: 999px !important;
        background: linear-gradient(135deg, var(--or), var(--or2)) !important;
        color: #1f1f1f !important;
        font-weight: 800 !important;
        font-size: .85rem !important;
        box-shadow: 0 4px 12px rgba(244, 197, 66, .35) !important;
        white-space: nowrap !important;
    }

    /* ── بطاقات الأدوار (تنقّل) — رمادي فاتح + نص أسود + رقم كبير لليسار ── */
    .activity-logs-main .role-cards-flex {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: stretch !important;
        gap: 14px !important;
    }

    .activity-logs-main .role-card-wrap {
        flex: 1 1 calc(25% - 14px) !important;
        max-width: calc(25% - 11px) !important;
        min-width: 240px !important;
    }

    .activity-logs-main .role-card-wrap>a {
        display: block !important;
        height: 100% !important;
    }

    /* بطاقات الأدوار متداخلة (ليست أبناء مباشرين للـ shell)، لذا تفرض عليها
       'body.ems-site .main .card' خلفية بيضاء (0,3,1). نرفع الخصوصية إلى (0,4,1)
       حتى يظهر الرمادي الفاتح + الحدّ الذهبي. */
    body.ems-site .main.activity-logs-main .role-card {
        height: 100% !important;
        border: 1px solid #888 !important;
        border-radius: 25px !important;
        box-shadow: var(--sh) !important;
        background: var(--light-gray) !important;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease !important;
    }

    body.ems-site .main.activity-logs-main .role-card:hover {
        transform: translateY(-3px) !important;
        box-shadow: var(--sh2) !important;
        border-color: var(--or) !important;
    }

    /* كل العناصر داخل البطاقة باللون الأسود */
    .activity-logs-main .role-card,
    .activity-logs-main .role-card *,
    .activity-logs-main .role-card i {
        color: #000 !important;
    }

    .activity-logs-main .activity-role-icon {
        width: 46px !important;
        height: 46px !important;
        background: rgba(0, 0, 0, .06) !important;
        flex-shrink: 0 !important;
    }

    .activity-logs-main .role-card-name {
        font-weight: 800 !important;
        font-size: .98rem !important;
    }

    .activity-logs-main .role-card-last {
        font-size: .76rem !important;
        margin-top: 4px !important;
        font-weight: 600 !important;
    }

    /* رقم الإحصائية — كبير ومحاذى لليسار (نهاية البطاقة في RTL) */
    .activity-logs-main .role-card-stat {
        margin-inline-start: auto !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        line-height: 1 !important;
        flex-shrink: 0 !important;
        padding-inline-start: 10px !important;
    }

    .activity-logs-main .role-card-stat-num {
        font-size: 2.1rem !important;
        font-weight: 900 !important;
        letter-spacing: -.5px !important;
    }

    .activity-logs-main .role-card-stat-label {
        font-size: .72rem !important;
        font-weight: 700 !important;
        margin-top: 2px !important;
    }

    /* ── صف أزرار الفلاتر ── */
    .activity-logs-main .activity-filter-actions {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        justify-content: flex-end !important;
    }

    /* "تفريغ السجلات" إجراء حذف → لون تحذيري مميّز عن زر الحفظ */
    .activity-logs-main .allforms .activity-clear-btn {
        background: var(--err) !important;
        box-shadow: 0 6px 15px rgba(220, 38, 38, .32) !important;
        color: #fff !important;
    }

    .activity-logs-main .allforms .activity-clear-btn:hover {
        background: #b91c1c !important;
    }

    .activity-logs-main .allforms .activity-clear-btn i {
        color: #fff !important;
    }

    /* ── استجابة الشاشات ── */
    @media (max-width:1199px) {
        .activity-logs-main .role-card-wrap {
            flex-basis: calc(50% - 10px) !important;
            max-width: calc(50% - 7px) !important;
        }
    }

    @media (max-width:575px) {
        .activity-logs-main .role-card-wrap {
            flex-basis: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }

        .activity-logs-main .activity-page-hero {
            flex-wrap: wrap !important;
            align-items: flex-start !important;
        }

        .activity-logs-main .activity-page-hero-count {
            margin-inline-start: 0 !important;
        }
    }
</style>

<!-- ══════════════════════════════════════════
     Detail Modal
═══════════════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">
                    <i class="fa fa-search-plus me-2"></i>تفاصيل السجل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-4 text-muted" id="detailLoading">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                </div>
                <div id="detailContent" style="display:none">
                    <div class="row g-3" id="detailMeta"></div>
                    <hr>
                    <div class="card border-0 bg-light-subtle">
                        <div class="card-body py-3">
                            <label class="form-label fw-semibold small text-muted mb-2">جميع حقول السجل</label>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:220px">الحقل</th>
                                            <th>القيمة</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailAllFields"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">القيمة القديمة (old_value)</label>
                            <pre id="detailOldValue" class="bg-light rounded p-3 small"
                                style="max-height:300px;overflow:auto;white-space:pre-wrap"></pre>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">القيمة الجديدة (new_value)</label>
                            <pre id="detailNewValue" class="bg-light rounded p-3 small"
                                style="max-height:300px;overflow:auto;white-space:pre-wrap"></pre>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">بيانات الطلب
                                (request_payload)</label>
                            <pre id="detailPayload" class="bg-light rounded p-3 small"
                                style="max-height:300px;overflow:auto;white-space:pre-wrap"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     Confirm Clear Modal
═══════════════════════════════════════════ -->
<div class="modal fade" id="clearConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-exclamation-triangle me-2"></i>تأكيد تفريغ السجلات</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">سيتم حذف جميع سجلات نشاط الدور:</p>
                <p class="fw-bold fs-6" id="clearConfirmRoleName"></p>
                <div class="alert alert-warning mb-0 py-2 small">
                    <i class="fa fa-warning me-1"></i>هذا الإجراء لا يمكن التراجع عنه.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger btn-sm" id="clearConfirmBtn">
                    <i class="fa fa-trash me-1"></i>نعم، تفريغ السجلات
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // ──────────────────────────────────────────────────────────────────────────
    // Loader: ينتظر jQuery ثم DataTables ثم يشغّل الكود مرة واحدة فقط.
    // Guard يمنع التهيئة المزدوجة حتى لو استُدعيت الدالة مرتين.
    // ──────────────────────────────────────────────────────────────────────────
    (function () {
        var DT_BASE = '/ems/assets/vendor/datatables/js/';
        var DT_SCRIPTS = [
            DT_BASE + 'jquery.dataTables.min.js',
            DT_BASE + 'dataTables.responsive.min.js',
            DT_BASE + 'dataTables.buttons.min.js',
            DT_BASE + 'buttons.html5.min.js',
            DT_BASE + 'buttons.print.min.js'
        ];


        function loadScript(src, cb) {
            // إذا كان محملاً بالفعل استدع cb مباشرة بدون إنشاء tag جديد
            if (document.querySelector('script[src="' + src + '"]')) { return cb(); }
            var s = document.createElement('script');
            s.src = src;
            s.onload = cb;
            s.onerror = function () { console.error('Failed: ' + src); cb(); };
            document.head.appendChild(s);
        }

        function loadSequential(list, done) {
            if (!list.length) return done();
            loadScript(list[0], function () { loadSequential(list.slice(1), done); });
        }

        function waitForJQuery(cb) {
            if (typeof window.jQuery !== 'undefined') return cb(window.jQuery);
            setTimeout(function () { waitForJQuery(cb); }, 30);
        }

        // ── الدخول الوحيد لتشغيل الكود ──────────────────────────────────────
        waitForJQuery(function ($) {
            // انتظر DataTables — سواء محملة مسبقاً أو ستُحمَّل الآن
            function waitForDT(cb) {
                if ($.fn && $.fn.dataTable) return cb();
                loadSequential(DT_SCRIPTS, cb);
            }

            waitForDT(function () {
                $(function () { initActivityLogs($); });
            });
        });
    })();

    function initActivityLogs($) {
        'use strict';

        // ── Guard: يمنع التهيئة المزدوجة لـ DataTables ──────────────────────
        if (window._logsTableInitialized) return;
        window._logsTableInitialized = true;

        var ROLE_ID = <?= intval($selectedRoleId) ?>;
        var IS_PHASE2 = ROLE_ID > 0;

        if (!IS_PHASE2) return;

        // ══════════════════════════════════════════════════════════════════════
        // DataTables init
        // ══════════════════════════════════════════════════════════════════════
        // إذا كان الجدول مهيَّأً مسبقاً بـ DataTables (حالة نادرة) أزله أولاً
        if ($.fn.dataTable.isDataTable('#logsTable')) {
            $('#logsTable').DataTable().destroy();
        }

        var logsTable = $('#logsTable').DataTable({
            language: { url: '/ems/assets/i18n/datatables/ar.json' },
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100, 250],
            order: [[0, 'desc']], // newest first
            // Column 9 (details button) is not sortable
            columnDefs: [
                { targets: 9, orderable: false, searchable: false },
                { targets: 10, visible: false, searchable: true }   // http_method hidden col
            ],
            dom: '<"row align-items-center mb-2"<"col-sm-4"l><"col-sm-4 text-center" B><"col-sm-4"f>>rtip',
            buttons: {
                dom: {
                    button: { tag: 'button', className: 'btn btn-sm' }
                },
                buttons: [
                    {
                        // CSV يعمل بدون JSZip ويُفتح مباشرة في Excel
                        extend: 'csvHtml5',
                        text: '<i class="fa fa-file-excel-o me-1"></i> تصدير CSV',
                        className: 'btn-outline-success',
                        title: 'سجل_النشاطات',
                        fieldSeparator: ',',
                        charset: 'utf-8',
                        bom: true,          // BOM يجعل Excel يقرأ العربية صح
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print me-1"></i> طباعة',
                        className: 'btn-outline-secondary',
                        title: 'سجل النشاطات',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
                    }
                ]
            },
            // Rebind detail buttons after every draw (paging, search, sort)
            drawCallback: function () {
                bindDetailBtns();
            }
        });

        // ══════════════════════════════════════════════════════════════════════
        // Custom search functions
        // ══════════════════════════════════════════════════════════════════════

        // نوع الإجراء — يقرأ data-action من الخلية مباشرة (يتجاوز HTML للـ badge)
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex, rowData, counter) {
            if (settings.nTable.id !== 'logsTable') return true;

            var actionFilter = $('#f_action_type').val();
            var dateFrom = $('#f_date_from').val();
            var dateTo = $('#f_date_to').val();
            var methodFilter = $('#f_http_method').val();

            // فلتر نوع الإجراء — يقرأ data-action من خلية العمود 5
            // نستخدم الـ row node مباشرة لتجنب مشاكل responsive مع cell().node()
            if (actionFilter) {
                var rowNode = logsTable.row(dataIndex).node();
                var actionCell = rowNode ? $(rowNode).find('td').eq(5).attr('data-action') : '';
                if ((actionCell || '') !== actionFilter) return false;
            }

            // فلتر التاريخ — يقرأ data-date من أول خلية في الصف
            // data[0] قد يحتوي على whitespace أو HTML لذا نقرأ من الـ attribute مباشرة
            if (dateFrom || dateTo) {
                var rowNode = logsTable.row(dataIndex).node();
                var rowDate = rowNode ? ($(rowNode).find('td').eq(0).attr('data-date') || '').trim() : '';
                if (!rowDate) {
                    // fallback: trim data[0] وخذ أول 10 أحرف
                    rowDate = (data[0] || '').replace(/<[^>]+>/g, '').trim().substring(0, 10);
                }
                if (dateFrom && rowDate < dateFrom) return false;
                if (dateTo && rowDate > dateTo) return false;
            }

            // فلتر HTTP method — column 10 (hidden)
            if (methodFilter) {
                var rowMethod = (data[10] || '').trim().toUpperCase();
                if (rowMethod !== methodFilter.toUpperCase()) return false;
            }

            return true;
        });

        // ربط الفلاتر بالأحداث
        $('#f_action_type, #f_http_method').on('change', function () {
            logsTable.draw();
        });
        $('#f_date_from, #f_date_to').on('change', function () {
            logsTable.draw();
        });

        // زر إعادة الفلاتر
        $('#resetFiltersBtn').on('click', function () {
            $('#f_action_type, #f_http_method').val('');
            $('#f_date_from, #f_date_to').val('');
            logsTable.draw();
        });

        // ══════════════════════════════════════════════════════════════════════
        // Clear logs — زر تفريغ السجلات
        // ══════════════════════════════════════════════════════════════════════
        var clearRoleId = 0;
        var clearRoleName = '';

        // فتح مودال التأكيد
        $('#clearLogsBtn').on('click', function () {
            clearRoleId = parseInt($(this).data('role-id'));
            clearRoleName = $(this).data('role-name');
            $('#clearConfirmRoleName').text(clearRoleName);
            getModal('clearConfirmModal').show();
        });

        // تنفيذ الحذف
        $('#clearConfirmBtn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>جاري التفريغ...');

            $.ajax({
                url: 'activity_logs.php',
                method: 'POST',
                data: { ajax: 'clear_logs', role_id: clearRoleId },
                dataType: 'json'
            })
                .done(function (data) {
                    getModal('clearConfirmModal').hide();
                    if (data.success) {
                        // امسح جميع صفوف الجدول في DataTables
                        logsTable.clear().draw();
                        // حدّث عداد البادج
                        $('#roleLogCount').text('0 سجل');
                        $('#heroLogCount').text('0 سجل');
                        showToast('success', data.message || 'تم تفريغ السجلات بنجاح');
                    } else {
                        showToast('danger', data.message || 'حدث خطأ أثناء التفريغ');
                    }
                })
                .fail(function () {
                    getModal('clearConfirmModal').hide();
                    showToast('danger', 'تعذّر الاتصال بالخادم');
                })
                .always(function () {
                    $btn.prop('disabled', false).html('<i class="fa fa-trash me-1"></i>نعم، تفريغ السجلات');
                });
        });

        // ══════════════════════════════════════════════════════════════════════
        // Detail Modal
        // ══════════════════════════════════════════════════════════════════════
        function escHtml(s) {
            if (s === null || s === undefined || s === '') return '—';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function prettyJson(v) {
            if (v === null || v === undefined || v === '') return '—';
            try { return JSON.stringify(JSON.parse(v), null, 2); } catch (e) { return String(v); }
        }

        function openDetailModal(id) {
            var modal = getModal('detailModal');
            var loading = document.getElementById('detailLoading');
            var content = document.getElementById('detailContent');

            loading.style.display = '';
            content.style.display = 'none';
            modal.show();

            fetch('activity_logs.php?ajax=detail&id=' + parseInt(id))
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (data) {
                    loading.style.display = 'none';
                    if (!data.success) {
                        content.innerHTML = '<p class="text-danger">' + escHtml(data.message || 'خطأ') + '</p>';
                        content.style.display = '';
                        return;
                    }
                    var r = data.row;

                    var metaFields = [
                        ['ID', r.id], ['التاريخ', r.created_at], ['IP', r.ip_address],
                        ['الموظف', r.employee_name || '— غير مرتبط —'],
                        ['الحساب (المستخدم)', r.user_name || r.user_id], ['الدور', r.role_name],
                        ['الوحدة', r.module_name], ['الشاشة', r.screen_name],
                        ['الإجراء', r.action_type], ['الزر', r.button_name],
                        ['رقم السجل', r.record_id], ['URL', r.url],
                        ['طريقة', r.http_method], ['الاستجابة', r.response_status], ['جلسة', r.session_id]
                    ];
                    document.getElementById('detailMeta').innerHTML = metaFields.map(function (f) {
                        if (f[1] === null || f[1] === undefined || f[1] === '') return '';
                        return '<div class="col-12 col-sm-6 col-md-4 col-lg-3">' +
                            '<small class="text-muted d-block">' + f[0] + '</small>' +
                            '<span class="small fw-medium">' + escHtml(f[1]) + '</span></div>';
                    }).join('');

                    var jsonFields = ['old_value', 'new_value', 'request_payload'];
                    document.getElementById('detailAllFields').innerHTML = Object.keys(r).map(function (k) {
                        var raw = r[k];
                        var val = jsonFields.indexOf(k) !== -1 ? prettyJson(raw)
                            : (raw === null || raw === undefined || raw === '' ? '—' : String(raw));
                        var cell = val.indexOf('\n') !== -1
                            ? '<pre class="mb-0 small" style="white-space:pre-wrap;max-height:220px;overflow:auto">' + escHtml(val) + '</pre>'
                            : '<span class="small">' + escHtml(val) + '</span>';
                        return '<tr><td class="small fw-semibold text-muted">' + escHtml(k) + '</td><td>' + cell + '</td></tr>';
                    }).join('');

                    document.getElementById('detailOldValue').textContent = prettyJson(r.old_value);
                    document.getElementById('detailNewValue').textContent = prettyJson(r.new_value);
                    document.getElementById('detailPayload').textContent = prettyJson(r.request_payload);
                    content.style.display = '';
                })
                .catch(function (err) {
                    console.error('Detail fetch error', err);
                    loading.style.display = 'none';
                    document.getElementById('detailContent').innerHTML =
                        '<p class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i>حدث خطأ أثناء التحميل</p>';
                    document.getElementById('detailContent').style.display = '';
                });
        }

        function bindDetailBtns() {
            document.querySelectorAll('.detail-btn').forEach(function (btn) {
                btn.removeEventListener('click', handleDetailClick);
                btn.addEventListener('click', handleDetailClick);
            });
        }
        function handleDetailClick() { openDetailModal(this.dataset.id); }

        // ══════════════════════════════════════════════════════════════════════
        // Helpers
        // ══════════════════════════════════════════════════════════════════════

        // Lazy Bootstrap modal resolver (BS5 → BS4/jQuery → manual fallback)
        var _modals = {};
        function getModal(id) {
            if (_modals[id]) return _modals[id];
            var el = document.getElementById(id);
            if (!el) return null;
            if (window.bootstrap && window.bootstrap.Modal) {
                _modals[id] = new window.bootstrap.Modal(el);
            } else if (window.jQuery && $.fn.modal) {
                _modals[id] = {
                    show: function () { $(el).modal('show'); },
                    hide: function () { $(el).modal('hide'); }
                };
            } else {
                _modals[id] = {
                    show: function () {
                        el.style.display = 'block'; el.classList.add('show');
                        document.body.classList.add('modal-open');
                        var bd = document.createElement('div');
                        bd.id = '_bd_' + id; bd.className = 'modal-backdrop fade show';
                        document.body.appendChild(bd);
                    },
                    hide: function () {
                        el.style.display = 'none'; el.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        var bd = document.getElementById('_bd_' + id);
                        if (bd) bd.remove();
                    }
                };
                el.querySelectorAll('[data-bs-dismiss="modal"],[data-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { _modals[id].hide(); });
                });
            }
            return _modals[id];
        }

        // Simple toast notification
        function showToast(type, msg) {
            var colors = { success: '#16a34a', danger: '#dc2626', warning: '#ca8a04' };
            var color = colors[type] || '#1255a8';
            var toast = document.createElement('div');
            toast.style.cssText =
                'position:fixed;bottom:24px;left:24px;z-index:9999;padding:12px 20px;border-radius:8px;' +
                'background:' + color + ';color:#fff;font-size:.9rem;box-shadow:0 4px 16px rgba(0,0,0,.2);' +
                'display:flex;align-items:center;gap:8px;max-width:360px;animation:slideInToast .25s ease';
            toast.innerHTML = '<i class="fa fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i>' + escHtml(msg);
            document.body.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0'; toast.style.transition = 'opacity .3s';
                setTimeout(function () { toast.remove(); }, 350);
            }, 3500);
        }

        // Initial bind for SSR rows
        bindDetailBtns();

    }
</script>

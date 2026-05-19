<?php
/**
 * Activity Logs — Main Screen
 * /ActivityLogs/activity_logs.php
 *
 * Phase 1: Role Cards overview.
 * Phase 2: Filtered log table (cursor-paginated) per role.
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

// Only admins and company admins can view logs.
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

    // Enforce company scope for non-super-admin.
    if (!$is_super_admin && $company_id > 0) {
        $filters['company_id'] = $company_id;
    }

    $afterCreatedAt = trim((string) ($_GET['after_created_at'] ?? ''));
    $afterId = intval($_GET['after_id'] ?? 0);
    $limit = min(intval($_GET['limit'] ?? 50), 200);

    $rows = $repo->getPage($filters, $afterCreatedAt, $afterId, $limit);
    echo json_encode(
        ['success' => true, 'rows' => $rows, 'count' => count($rows)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit();
}

// ── AJAX: single log detail for modal ────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detail') {
    header('Content-Type: application/json; charset=utf-8');
    $id = intval($_GET['id'] ?? 0);
    $row = $id > 0 ? $repo->getDetail($id) : null;
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'السجل غير موجود']);
        exit();
    }
    // Enforce scope.
    if (!$is_super_admin && $company_id > 0 && intval($row['company_id'] ?? 0) !== $company_id) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit();
    }
    echo json_encode(['success' => true, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// ── Phase 1: role cards data ──────────────────────────────────────────────
$roleSummary = $repo->getRoleSummary($is_super_admin ? 0 : $company_id);

// ── Phase 2: initial log table (when role_id is in GET) ──────────────────
$selectedRoleId = isset($_GET['role_id']) ? intval($_GET['role_id']) : 0;
$initialRows = [];
if ($selectedRoleId > 0) {
    $initialRows = $repo->getInitialPage(
        $is_super_admin ? 0 : $company_id,
        $selectedRoleId,
        1000
    );
}

$initialCursorId = 0;
$initialCursorCreatedAt = '';
if (!empty($initialRows)) {
    $lastInitialRow = end($initialRows);
    $initialCursorId = intval($lastInitialRow['id'] ?? 0);
    $initialCursorCreatedAt = strval($lastInitialRow['created_at'] ?? '');
    reset($initialRows);
}

// Role icon / colour palette.
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

function actionBadge(string $type, array $labels): string
{
    $info = $labels[$type] ?? ['label' => $type, 'badge' => 'bg-secondary'];
    return '<span class="badge ' . $info['badge'] . '">' . htmlspecialchars($info['label'], ENT_QUOTES) . '</span>';
}

$page_title = 'سجل النشاطات';
?>
<?php require_once '../insidebar.php'; ?>
<?php require_once '../inheader.php'; ?>

<main class="main-content">
    <div class="container-fluid py-4 px-3 px-md-4">

        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 gap-2 flex-wrap">
            <div>
                <h4 class="mb-1 fw-bold" style="color:var(--ni5,#1255a8)">
                    <i class="fa fa-history me-2"></i>سجل النشاطات
                </h4>
                <p class="text-muted small mb-0">تتبع جميع عمليات المستخدمين في النظام</p>
            </div>
            <?php if ($selectedRoleId > 0): ?>
                <a href="activity_logs.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-right me-1"></i>العودة للأدوار
                </a>
            <?php endif; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════
       PHASE 1 — Role Cards
  ════════════════════════════════════════════════════ -->
        <?php if ($selectedRoleId === 0): ?>
            <div class="row g-3 mb-2" id="roleCardsGrid">
                <?php if (empty($roleSummary)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fa fa-info-circle me-2"></i>
                            لا توجد سجلات نشاط حتى الآن. السجلات ستظهر هنا بعد أول دخول للمستخدمين.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($roleSummary as $card):
                        $rid = strval($card['role_id'] ?? '');
                        $ico = $roleIconMap[$rid] ?? ['icon' => 'fa-user', 'color' => '#6b7280', 'bg' => '#f9fafb'];
                        $name = htmlspecialchars($card['role_name'] ?? 'دور #' . $rid, ENT_QUOTES, 'UTF-8');
                        $total = number_format(intval($card['total_logs']));
                        $lastAt = $card['last_activity'] ? date('Y-m-d H:i', strtotime($card['last_activity'])) : '—';
                        ?>
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                            <a href="activity_logs.php?role_id=<?= intval($card['role_id']) ?>" class="text-decoration-none">
                                <div class="card h-100 border-0 shadow-sm role-card"
                                    style="border-right:4px solid <?= $ico['color'] ?> !important;background:<?= $ico['bg'] ?>;transition:transform .15s,box-shadow .15s">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <div class="role-icon-wrap rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width:52px;height:52px;background:<?= $ico['color'] ?>22">
                                            <i class="fa <?= $ico['icon'] ?> fa-lg" style="color:<?= $ico['color'] ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold text-dark text-truncate" style="font-size:.95rem"><?= $name ?>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                <i class="fa fa-list-ul me-1" style="color:<?= $ico['color'] ?>"></i>
                                                <?= $total ?> سجل
                                            </div>
                                            <div class="text-muted" style="font-size:.72rem;margin-top:3px">
                                                <i class="fa fa-clock me-1"></i> آخر نشاط: <?= $lastAt ?>
                                            </div>
                                        </div>
                                        <i class="fa fa-chevron-left text-muted" style="font-size:.8rem"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════
       PHASE 2 — Log Table
  ════════════════════════════════════════════════════ -->
        <?php if ($selectedRoleId > 0):
            $currentRoleCard = null;
            foreach ($roleSummary as $c) {
                if (intval($c['role_id']) === $selectedRoleId) {
                    $currentRoleCard = $c;
                    break;
                }
            }
            $rid = strval($selectedRoleId);
            $ico = $roleIconMap[$rid] ?? ['icon' => 'fa-user', 'color' => '#6b7280', 'bg' => '#f9fafb'];
            ?>

            <!-- Selected role badge -->
            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <div class="role-badge-lg d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                    style="background:<?= $ico['bg'] ?>;border:1px solid <?= $ico['color'] ?>33">
                    <i class="fa <?= $ico['icon'] ?>" style="color:<?= $ico['color'] ?>"></i>
                    <span class="fw-semibold" style="color:<?= $ico['color'] ?>">
                        <?= htmlspecialchars($currentRoleCard['role_name'] ?? 'الدور #' . $selectedRoleId, ENT_QUOTES) ?>
                    </span>
                    <span class="badge rounded-pill text-white ms-1" style="background:<?= $ico['color'] ?>">
                        <?= number_format(intval($currentRoleCard['total_logs'] ?? 0)) ?> سجل
                    </span>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-end" id="filterForm">
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm">نوع الإجراء</label>
                            <select id="f_action_type" class="form-select form-select-sm">
                                <option value="">الكل</option>
                                <?php foreach ($actionLabels as $k => $v): ?>
                                    <option value="<?= $k ?>"><?= $v['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm">الوحدة</label>
                            <input type="text" id="f_module_name" class="form-control form-control-sm"
                                placeholder="مثال: contracts">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm">الشاشة</label>
                            <input type="text" id="f_screen_name" class="form-control form-control-sm"
                                placeholder="مثال: contracts_list">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm">رقم السجل</label>
                            <input type="number" id="f_record_id" class="form-control form-control-sm" placeholder="ID">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm">من تاريخ</label>
                            <input type="date" id="f_date_from" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label form-label-sm">إلى تاريخ</label>
                            <input type="date" id="f_date_to" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                            <label class="form-label form-label-sm">حالة الاستجابة</label>
                            <input type="number" id="f_response_status" class="form-control form-control-sm"
                                placeholder="200">
                        </div>
                        <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                            <label class="form-label form-label-sm">طريقة HTTP</label>
                            <select id="f_http_method" class="form-select form-select-sm">
                                <option value="">الكل</option>
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                            </select>
                        </div>
                        <div class="col-12 col-auto d-flex gap-2 align-items-end">
                            <button class="btn btn-primary btn-sm px-3" id="applyFiltersBtn">
                                <i class="fa fa-filter me-1"></i>تطبيق
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="resetFiltersBtn">
                                <i class="fa fa-times me-1"></i>إعادة
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 no-datatable" id="logsTable" data-no-dt="1">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="px-3" style="min-width:140px">التاريخ والوقت</th>
                                    <th>المستخدم</th>
                                    <th>الدور</th>
                                    <th>الوحدة</th>
                                    <th>الشاشة</th>
                                    <th>الإجراء</th>
                                    <th>الزر</th>
                                    <th>رقم السجل</th>
                                    <th>الاستجابة</th>
                                    <th class="text-center">تفاصيل</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <?php foreach ($initialRows as $row): ?>
                                    <?php echo renderLogRow($row, $actionLabels); ?>
                                <?php endforeach; ?>
                                <?php if (empty($initialRows)): ?>
                                    <tr id="emptyRow">
                                        <td colspan="10" class="text-center py-5 text-muted">
                                            <i class="fa fa-inbox fa-2x mb-2 d-block"></i>لا توجد سجلات
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Load more -->
                    <div class="d-flex justify-content-center py-3" id="loadMoreWrap" <?= empty($initialRows) || count($initialRows) < 1000 ? 'style="display:none!important"' : '' ?>>
                        <button class="btn btn-outline-primary btn-sm px-4" id="loadMoreBtn">
                            <i class="fa fa-chevron-down me-1"></i>تحميل المزيد
                        </button>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div><!-- /container -->
</main>

<!-- ═══════════════════════════════════════════════════
     Detail Modal
════════════════════════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">
                    <i class="fa fa-search-plus me-2"></i>تفاصيل السجل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
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

<?php
// ── Inline helper to render one <tr> ─────────────────────────────────────
function renderLogRow(array $row, array $actionLabels): string
{
    $e = fn($s) => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
    $t = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '—';

    $actionType = $row['action_type'] ?? '';
    $info = $actionLabels[$actionType] ?? ['label' => $e($actionType), 'badge' => 'bg-secondary'];
    $badge = '<span class="badge ' . $info['badge'] . '">' . $e($info['label']) . '</span>';

    $status = intval($row['response_status'] ?? 0);
    $statusClass = $status >= 500 ? 'text-danger fw-bold'
        : ($status >= 400 ? 'text-warning fw-bold'
            : ($status >= 200 ? 'text-success' : 'text-muted'));

    return '<tr data-id="' . intval($row['id']) . '">
        <td class="px-3 text-nowrap small">' . $e($t) . '</td>
      <td class="small">' . $e($row['user_name'] ?? ($row['user_id'] ?? '—')) . '</td>
        <td class="small">' . $e($row['role_name'] ?? '—') . '</td>
        <td class="small">' . $e($row['module_name'] ?? '—') . '</td>
        <td class="small">' . $e($row['screen_name'] ?? '—') . '</td>
        <td>' . $badge . '</td>
        <td class="small text-truncate" style="max-width:100px" title="' . $e($row['button_name'] ?? '') . '">' . $e($row['button_name'] ?? '—') . '</td>
        <td class="small">' . ($row['record_id'] ? $e($row['record_id']) : '—') . '</td>
        <td class="small ' . $statusClass . '">' . ($status ?: '—') . '</td>
        <td class="text-center">
          <button class="btn btn-outline-primary btn-xs py-0 px-2 detail-btn" data-id="' . intval($row['id']) . '" title="تفاصيل">
            <i class="fa fa-eye"></i>
          </button>
        </td>
      </tr>';
}
?>

<script>
    (function () {
        'use strict';

        const ROLE_ID = <?= intval($selectedRoleId) ?>;
        const IS_PHASE2 = ROLE_ID > 0;
        let cursorAfterId = <?= intval($initialCursorId) ?>;
        let cursorAfterCreatedAt = <?= json_encode($initialCursorCreatedAt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function initLogsDataTable() {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) return false;
            var $ = window.jQuery;
            var tableEl = document.getElementById('logsTable');
            if (!tableEl) return false;

            if ($.fn.dataTable.isDataTable(tableEl)) {
                $(tableEl).DataTable().order([7, 'desc']).draw(false);
                return true;
            }

            $(tableEl).DataTable({
                responsive: true,
                autoWidth: false,
                order: [[7, 'desc']],
                language: { url: '/ems/assets/i18n/datatables/ar.json' }
            });
            return true;
        }

        // ── Role card hover effect ──────────────────────────────────────────────
        document.querySelectorAll('.role-card').forEach(function (card) {
            card.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = '0 8px 24px rgba(0,0,0,.12)';
            });
            card.addEventListener('mouseleave', function () {
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        });

        // Local init only; retries briefly until DataTables library is ready.
        (function bootLogsTable() {
            var tries = 0;
            var timer = setInterval(function () {
                tries++;
                if (initLogsDataTable() || tries >= 20) {
                    clearInterval(timer);
                }
            }, 250);
            initLogsDataTable();
        })();

        if (!IS_PHASE2) return;

        // ── Cursor state ───────────────────────────────────────────────────────
        let activeFilters = { role_id: ROLE_ID };
        let isLoading = false;

        const tbody = document.getElementById('logsTableBody');
        const loadMoreWrap = document.getElementById('loadMoreWrap');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        const emptyRow = document.getElementById('emptyRow');

        // ── Build URL for AJAX ─────────────────────────────────────────────────
        function buildUrl(filters, afterId, afterCreatedAt, limit) {
            var params = new URLSearchParams(filters);
            params.set('ajax', 'logs');
            if (afterId > 0) params.set('after_id', afterId);
            if (afterCreatedAt) params.set('after_created_at', afterCreatedAt);
            params.set('limit', limit || 50);
            return 'activity_logs.php?' + params.toString();
        }

        // ── Render rows from API response ──────────────────────────────────────
        var actionLabels = <?= json_encode($actionLabels, JSON_UNESCAPED_UNICODE) ?>;
        var badgeMap = {
            'view': ['عرض', 'bg-secondary'],
            'create': ['إنشاء', 'bg-success'],
            'update': ['تعديل', 'bg-warning'],
            'delete': ['حذف', 'bg-danger'],
            'send': ['إرسال', 'bg-primary'],
            'login': ['دخول', 'bg-primary'],
            'logout': ['خروج', 'bg-dark'],
            'export': ['تصدير', 'bg-info text-dark'],
            'print': ['طباعة', 'bg-info text-dark'],
            'search': ['بحث', 'bg-light text-dark border'],
            'click': ['نقرة', 'bg-secondary'],
        };

        function escHtml(s) {
            if (!s && s !== 0) return '—';
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function renderRow(r) {
            var t = r.created_at ? r.created_at.substring(0, 19) : '—';
            var bm = badgeMap[r.action_type] || [escHtml(r.action_type), 'bg-secondary'];
            var badge = '<span class="badge ' + bm[1] + '">' + bm[0] + '</span>';

            var status = parseInt(r.response_status || 0);
            var sc = status >= 500 ? 'text-danger fw-bold'
                : status >= 400 ? 'text-warning fw-bold'
                    : status >= 200 ? 'text-success' : 'text-muted';

            return '<tr data-id="' + parseInt(r.id) + '">' +
                '<td class="px-3 text-nowrap small">' + escHtml(t) + '</td>' +
                '<td class="small">' + escHtml(r.user_name || r.user_id) + '</td>' +
                '<td class="small">' + escHtml(r.role_name) + '</td>' +
                '<td class="small">' + escHtml(r.module_name) + '</td>' +
                '<td class="small">' + escHtml(r.screen_name) + '</td>' +
                '<td>' + badge + '</td>' +
                '<td class="small text-truncate" style="max-width:100px" title="' + escHtml(r.button_name) + '">' + escHtml(r.button_name) + '</td>' +
                '<td class="small">' + escHtml(r.record_id) + '</td>' +
                '<td class="small ' + sc + '">' + (status || '—') + '</td>' +
                '<td class="text-center"><button class="btn btn-outline-primary btn-xs py-0 px-2 detail-btn" data-id="' + parseInt(r.id) + '"><i class="fa fa-eye"></i></button></td>' +
                '</tr>';
        }

        // ── Load more ──────────────────────────────────────────────────────────
        function fetchMore(replace) {
            if (isLoading) return;
            isLoading = true;
            loadMoreBtn && (loadMoreBtn.disabled = true);

            var url = buildUrl(activeFilters, replace ? 0 : cursorAfterId, replace ? '' : cursorAfterCreatedAt, 50);

            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) { isLoading = false; return; }

                    if (replace) {
                        tbody.innerHTML = '';
                    }
                    if (emptyRow) emptyRow.remove();

                    if (!data.rows || data.rows.length === 0) {
                        if (replace || tbody.innerHTML.trim() === '') {
                            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fa fa-inbox fa-2x mb-2 d-block"></i>لا توجد سجلات</td></tr>';
                        }
                        loadMoreWrap && (loadMoreWrap.style.display = 'none');
                    } else {
                        var html = data.rows.map(renderRow).join('');
                        tbody.insertAdjacentHTML('beforeend', html);
                        cursorAfterId = parseInt(data.rows[data.rows.length - 1].id);
                        cursorAfterCreatedAt = data.rows[data.rows.length - 1].created_at || '';
                        loadMoreWrap && (loadMoreWrap.style.display = data.rows.length < 50 ? 'none' : '');
                    }

                    // Re-bind detail buttons
                    bindDetailBtns();
                    isLoading = false;
                    loadMoreBtn && (loadMoreBtn.disabled = false);
                })
                .catch(function (err) {
                    console.error('fetchMore error', err);
                    isLoading = false;
                    loadMoreBtn && (loadMoreBtn.disabled = false);
                });
        }

        // ── Filters ────────────────────────────────────────────────────────────
        document.getElementById('applyFiltersBtn') && document.getElementById('applyFiltersBtn').addEventListener('click', function () {
            var filters = { role_id: ROLE_ID };
            ['action_type', 'module_name', 'screen_name', 'record_id', 'date_from', 'date_to', 'response_status', 'http_method']
                .forEach(function (k) {
                    var el = document.getElementById('f_' + k);
                    if (el && el.value.trim()) filters[k] = el.value.trim();
                });
            activeFilters = filters;
            cursorAfterId = 0;
            fetchMore(true);
        });

        document.getElementById('resetFiltersBtn') && document.getElementById('resetFiltersBtn').addEventListener('click', function () {
            ['action_type', 'module_name', 'screen_name', 'record_id', 'date_from', 'date_to', 'response_status', 'http_method']
                .forEach(function (k) {
                    var el = document.getElementById('f_' + k);
                    if (el) el.value = '';
                });
            activeFilters = { role_id: ROLE_ID };
            cursorAfterId = 0;
            fetchMore(true);
        });

        loadMoreBtn && loadMoreBtn.addEventListener('click', function () {
            fetchMore(false);
        });

        // ── Detail Modal ───────────────────────────────────────────────────────
        function bindDetailBtns() {
            document.querySelectorAll('.detail-btn').forEach(function (btn) {
                btn.removeEventListener('click', handleDetailClick);
                btn.addEventListener('click', handleDetailClick);
            });
        }

        function handleDetailClick() {
            var id = this.dataset.id;
            openDetailModal(id);
        }

        var detailModalEl = document.getElementById('detailModal');
        var detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;

        function openDetailModal(id) {
            if (!detailModal) {
                console.error('Modal element not found');
                return;
            }

            var body = document.getElementById('detailModalBody');
            var loading = document.getElementById('detailLoading');
            var content = document.getElementById('detailContent');

            if (!loading || !content) {
                console.error('Modal elements not found');
                return;
            }

            loading.style.display = '';
            content.style.display = 'none';
            detailModal.show();

            var url = 'activity_logs.php?ajax=detail&id=' + parseInt(id);
            console.log('Fetching detail from:', url);

            fetch(url)
                .then(function (r) {
                    console.log('Response status:', r.status);
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    console.log('Response data:', data);
                    loading.style.display = 'none';
                    if (!data.success) {
                        console.error('API error:', data.message);
                        content.innerHTML = '<p class="text-danger">' + (data.message || 'خطأ في تحميل البيانات') + '</p>';
                        content.style.display = '';
                        return;
                    }

                    var r = data.row;
                    console.log('Rendering detail for row:', r);

                    var metaFields = [
                        ['ID', r.id], ['التاريخ', r.created_at], ['IP', r.ip_address],
                        ['المستخدم', (r.user_name || r.user_id)], ['الدور', r.role_name], ['الوحدة', r.module_name],
                        ['الشاشة', r.screen_name], ['الإجراء', r.action_type], ['الزر', r.button_name],
                        ['رقم السجل', r.record_id], ['URL', r.url], ['طريقة', r.http_method],
                        ['الاستجابة', r.response_status], ['جلسة', r.session_id]
                    ];

                    var metaHtml = metaFields.map(function (f) {
                        if (!f[1] && f[1] !== 0) return '';
                        return '<div class="col-12 col-sm-6 col-md-4 col-lg-3">' +
                            '<small class="text-muted d-block">' + f[0] + '</small>' +
                            '<span class="small fw-medium">' + escHtml(f[1]) + '</span></div>';
                    }).join('');

                    document.getElementById('detailMeta').innerHTML = metaHtml;

                    function prettyJson(v) {
                        if (!v) return '—';
                        try { return JSON.stringify(JSON.parse(v), null, 2); }
                        catch (e) { return String(v); }
                    }

                    var jsonFields = ['old_value', 'new_value', 'request_payload'];
                    var allRowsHtml = Object.keys(r).map(function (k) {
                        var raw = r[k];
                        var val = jsonFields.indexOf(k) !== -1 ? prettyJson(raw) : (raw === null || raw === '' ? '—' : String(raw));
                        var renderedVal = val.indexOf('\n') !== -1
                            ? ('<pre class="mb-0 small" style="white-space:pre-wrap;max-height:220px;overflow:auto">' + escHtml(val) + '</pre>')
                            : ('<span class="small">' + escHtml(val) + '</span>');
                        return '<tr><td class="small fw-semibold text-muted">' + escHtml(k) + '</td><td>' + renderedVal + '</td></tr>';
                    }).join('');

                    console.log('All rows HTML length:', allRowsHtml.length);

                    var allFieldsTable = document.getElementById('detailAllFields');
                    if (allFieldsTable) {
                        console.log('Updating detailAllFields');
                        allFieldsTable.innerHTML = allRowsHtml;
                    } else {
                        console.warn('detailAllFields element not found');
                    }

                    var oldValEl = document.getElementById('detailOldValue');
                    var newValEl = document.getElementById('detailNewValue');
                    var payloadEl = document.getElementById('detailPayload');

                    if (oldValEl) oldValEl.textContent = prettyJson(r.old_value);
                    if (newValEl) newValEl.textContent = prettyJson(r.new_value);
                    if (payloadEl) payloadEl.textContent = prettyJson(r.request_payload);

                    content.style.display = '';
                })
                .catch(function (err) {
                    console.error('Detail fetch error', err);
                    loading.style.display = 'none';
                    var errMsg = document.getElementById('detailContent');
                    if (errMsg) {
                        errMsg.innerHTML = '<p class="text-danger"><i class="fa fa-exclamation-triangle"></i> حدث خطأ أثناء التحميل</p>';
                        errMsg.style.display = '';
                    }
                });
        }

        bindDetailBtns();

    }());
</script>

<style>
    .btn-xs {
        font-size: .72rem;
        line-height: 1.4;
    }

    .sticky-top {
        top: 0;
        z-index: 1;
    }

    pre {
        font-family: 'Courier New', monospace;
        font-size: .78rem;
    }

    #logsTable th {
        font-size: .78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    #logsTable td {
        font-size: .82rem;
        vertical-align: middle;
    }
</style>

<?php
/**
 * Governance/job_queue.php — طابور المهام
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: المرحلة «نراقب الأحداثَ والمهام» · مجموعة «طابورُ المهام»
 * أفعالُها في قاموسِ الأفعال: 'job.enqueue', 'job.claim'
 *
 * ◆ الترتيبُ الملزمُ في هذا الملفّ:
 *   ① جلسة → ② إعداد → ③ حارسُ شاشة → ④ حارسُ فعل → ⑤ رمزُ حماية
 *   → ⑥ معالجُ POST → ⑦ العرض
 * ◆ ولا تكتب هذه الشاشةُ في جدولٍ مباشرةً — تنادي خدمةً والخدمةُ تكتب.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../app/Services/Queue/JobQueueService.php';
require_once __DIR__ . '/../app/Services/Queue/JobScheduleService.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/job_queue.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة — can_view من سجلِّ الوحدات قبلَ أيِّ قراءةٍ أو كتابة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}

// ═══ ④ حارسُ الفعل — الكتابةُ تحتاج منحةً صريحةً لا مجرَّدَ عرض ═══
$__canWrite = $is_super_admin || !empty($__pp['can_add']) || !empty($__pp['can_edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$__canWrite) {
    http_response_code(403);
    exit('غير مصرَّحٍ بالكتابة في هذه الشاشة — اطلبِ المنحةَ من مدير الصلاحيات');
}

// ═══ ⑤ رمزُ الحماية — قبلَ أيِّ معالجةٍ لا بعدَها ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('رمزُ الحمايةِ غيرُ صالح — أعدْ تحميلَ الصفحة');
    }
}

// ═══ ⑥ معالجُ POST — ينادي الخدمةَ ولا يكتب في جدول ═══
$flash = null; $flashKind = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $__action = (string) ($_POST['action'] ?? '');
    if ($__action === 'release_locks') {
        $n = \App\Services\Queue\JobQueueService::releaseExpiredLocks($conn);
        $flash = 'حُرّر ' . $n . ' قفلًا منقضيًا'; $flashKind = 'success';
    } elseif ($__action === 'materialize') {
        $r = \App\Services\Queue\JobScheduleService::materialize($conn);
        $flash = 'أُدرج ' . $r['enqueued'] . ' وتُخطّي ' . $r['skipped'];
        $flashKind = 'success';
    }
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('q.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT q.job_id, q.job_type, q.state, q.source, q.source_ref, q.worker_id,
            q.claimed_at, q.lock_expires_at, q.attempts, q.max_attempts,
            q.fail_code, LEFT(COALESCE(q.last_error,''), 60) last_error, q.created_at, q.seed_tag
       FROM ems_job_queue q
      WHERE {$where}
      ORDER BY q.job_id DESC LIMIT 200"
);
$stats = $conn->query(
    "SELECT SUM(state='queued') queued, SUM(state='claimed') claimed,
            SUM(state IN ('processing','running')) running, SUM(state='done') done,
            SUM(state IN ('failed','dead','dlq')) failed,
            SUM(state='claimed' AND lock_expires_at < NOW(3)) stuck
       FROM ems_job_queue WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'طابور المهام';
$TILES = array(
    array('في الطابور', (int) $stats['queued']), array('ملتقَطة', (int) $stats['claimed']),
    array('قيد التنفيذ', (int) $stats['running']), array('تمّت', (int) $stats['done']),
    array('فاشلة أو معزولة', (int) $stats['failed']),
    array('مقفولةٌ منتهيةُ المهلة (CK-14)', (int) $stats['stuck']),
);
$COLS = array('#','النوع','الحالة','المصدر','مرجع المصدر','العامل','وقت الالتقاط','انتهاء القفل','محاولات','الحد','رمز الفشل','آخر خطأ','أُنشئت','وسم البذر');
$EMPTY_TITLE = 'لا مهامَّ في الطابورِ بعدُ';
$EMPTY_HINT  = 'تُدرَج المهامُّ آليًّا من الجدولةِ الدوريةِ أو من الخدماتِ الناشرة';
include __DIR__ . '/../includes/eng01_screen_view.php';

<?php
/**
 * Governance/job_schedule.php — جدولة المهام الدورية
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: المرحلة «نراقب الأحداثَ والمهام» · مجموعة «طابورُ المهام»
 * أفعالُها في قاموسِ الأفعال: 'job.schedule.define'
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
require_once __DIR__ . '/../app/Services/Queue/JobScheduleService.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/job_schedule.php';
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
    exit('غير مصرح بالكتابة في هذه الشاشة — اطلب المنحة من مدير الصلاحيات');
}

// ═══ ⑤ رمزُ الحماية — قبلَ أيِّ معالجةٍ لا بعدَها ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('رمز الحماية غير صالح — أعد تحميل الصفحة');
    }
}

// ═══ ⑥ معالجُ POST — ينادي الخدمةَ ولا يكتب في جدول ═══
$flash = null; $flashKind = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $__action = (string) ($_POST['action'] ?? '');
    if ($__action === 'define') {
        $r = \App\Services\Queue\JobScheduleService::define($conn, array(
            'job_type'            => $_POST['job_type'] ?? '',
            'cron_expr'           => $_POST['cron_expr'] ?? '',
            'max_runtime_seconds' => $_POST['max_runtime_seconds'] ?? 600,
            'alert_after_seconds' => $_POST['alert_after_seconds'] ?? 3600,
            'owner_role_id'       => $_POST['owner_role_id'] ?? 0,
            'is_active'           => isset($_POST['is_active']) ? 1 : 0,
            'company_id'          => 0,
            'created_by'          => $uid,
        ));
        $flash = $r['reason']; $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'alert_stalled') {
        $n = \App\Services\Queue\JobScheduleService::alertStalled($conn);
        $flash = 'رفع ' . $n . ' إنذار توقف'; $flashKind = 'success';
    }
}

// ═══ ⑦ العرض ═══
$rows = $conn->query(
    "SELECT s.job_type, s.cron_expr, s.max_runtime_seconds, s.alert_after_seconds,
            s.owner_role_id, s.last_success_at,
            TIMESTAMPDIFF(MINUTE, s.last_success_at, NOW()) AS since_min,
            s.is_active, s.replaces_manual
       FROM ems_job_schedule s ORDER BY s.job_type"
);
$stalled = \App\Services\Queue\JobScheduleService::stalled($conn);
$tot = (int) $conn->query('SELECT COUNT(*) FROM ems_job_schedule WHERE is_active=1')->fetch_row()[0];
$repl = (int) $conn->query("SELECT COUNT(*) FROM ems_job_schedule WHERE replaces_manual IS NOT NULL AND replaces_manual NOT LIKE '—%'")->fetch_row()[0];
$PAGE_TITLE = 'جدولة المهام الدورية';
$TILES = array(
    array('جدولات نشطة', $tot),
    array('ألغت أمرا يدويا', $repl),
    array('متوقفة فوق مهلة الإنذار (CK-15)', count($stalled)),
);
$COLS = array('النوع','التعبير الزمني','أقصى زمن تشغيل','مهلة الإنذار','الدور المسؤول','آخر نجاح','منذ (دقيقة)','مفعلة','الأمر اليدوي الملغى');
$EMPTY_TITLE = 'لا جدولات دورية معرفة بعد';
$EMPTY_HINT  = 'عرف جدولة لكل مهمة آلية حتى لا يبقى أمر يدوي بلا بديل';
include __DIR__ . '/../includes/eng01_screen_view.php';

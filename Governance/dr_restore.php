<?php
/**
 * Governance/dr_restore.php — الاستعادة ومحضرها
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: المرحلة «نراقب الأحداثَ والمهام» · مجموعة «الاستعادة»
 * أفعالُها في قاموسِ الأفعال: 'dr.restore.drill'
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
require_once __DIR__ . '/../app/Services/Dr/RestoreDrillService.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/dr_restore.php';
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
    if ($__action === 'record_drill') {
        $r = \App\Services\Dr\RestoreDrillService::record($conn, array(
            'company_id'               => $company_id,
            'drill_kind'               => $_POST['drill_kind'] ?? 'pitr',
            'started_at'               => $_POST['started_at'] ?? '',
            'finished_at'              => $_POST['finished_at'] ?? '',
            'target_point'             => $_POST['target_point'] ?? '',
            'rpo_target_minutes'       => $_POST['rpo_target_minutes'] ?? 15,
            'rpo_actual_minutes'       => $_POST['rpo_actual_minutes'] ?? '',
            'rto_actual_seconds'       => $_POST['rto_actual_seconds'] ?? '',
            'rows_before'              => $_POST['rows_before'] ?? 0,
            'rows_after_expected_gone' => $_POST['rows_after_expected_gone'] ?? 0,
            'rows_after_actual'        => $_POST['rows_after_actual'] ?? 0,
            'verdict'                  => $_POST['verdict'] ?? '',
            'operator_note'            => $_POST['operator_note'] ?? '',
            'runbook_ref'              => 'docs/ENG01_RESTORE_RUNBOOK_ar.md',
            'actor'                    => $uid,
            'actor_role'               => $role_id,
        ));
        $flash = $r['ok'] ? ('سجل المحضر ' . $r['drill_no']) : $r['reason'];
        $flashKind = $r['ok'] ? 'success' : 'error';
    }
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT drill_no, drill_kind, started_at, finished_at, target_point,
            rpo_target_minutes, rpo_actual_minutes, rto_actual_seconds,
            rows_before, rows_after_expected_gone, rows_after_actual, verdict, runbook_ref
       FROM dr_drills WHERE {$where} ORDER BY id DESC LIMIT 100"
);
$logBin = $conn->query("SHOW VARIABLES LIKE 'log_bin'")->fetch_assoc();
$ret = $conn->query("SELECT @@binlog_expire_logs_seconds/86400")->fetch_row()[0];
$days = \App\Services\Dr\RestoreDrillService::daysSinceLastPass($conn, $company_id ?: 1);
$PAGE_TITLE = 'الاستعادة ومحضرها';
$TILES = array(
    array('سجل الثنائيات (CK-16)', $logBin ? $logBin['Value'] : '—'),
    array('مدة الاحتفاظ (يوما)', round((float) $ret, 1)),
    array('أيام منذ آخر تجربة ناجحة', $days === null ? 'لم تجرب' : $days),
);
$COLS = array('رقم المحضر','النوع','البدء','الانتهاء','نقطة الاستعادة','هدف RPO (د)','RPO المقيس (د)','RTO (ث)','صفوف قبل','يجب ألا تعود','عادت فعلا','الحكم','المحضر');
$EMPTY_TITLE = 'لا محاضر استعادة مسجلة بعد';
$EMPTY_HINT  = 'يسجل المحضر من هذه الشاشة بعد تنفيذ تجربة الاستعادة وفق دليل التشغيل';
include __DIR__ . '/../includes/eng01_screen_view.php';

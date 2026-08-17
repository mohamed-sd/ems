<?php
/**
 * Governance/bus_board.php — لوحة الناقل
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: المرحلة «نراقب الأحداثَ والمهام» · مجموعة «ناقلُ الأحداث»
 * أفعالُها في قاموسِ الأفعال: 'bus.board.view'
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

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/bus_board.php';
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
    // شاشةُ قراءةٍ فقط — لا فعلَ كتابةٍ مسجَّلٌ لها (Read Only)
    $flash = 'هذه شاشةُ عرضٍ لا تكتب'; $flashKind = 'info';
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT e.event_key AS 'رمز الحدث',
            COUNT(*) AS 'وقائع',
            SUM(e.delivered_ok) AS 'تسليمات ناجحة',
            SUM(e.delivered_failed) AS 'تسليمات فاشلة',
            SUM(e.in_dlq) AS 'في صندوق الموتى',
            MAX(e.created_at) AS 'آخر نشر'
       FROM ems_business_events e
      WHERE {$where}
      GROUP BY e.event_key
      ORDER BY COUNT(*) DESC LIMIT 200"
);
$g = $conn->query(
    "SELECT (SELECT COUNT(*) FROM ems_business_events WHERE {$where}) facts,
            (SELECT COUNT(*) FROM ems_event_deliveries WHERE outbox_id > 0) deliveries,
            (SELECT COUNT(*) FROM ems_event_deliveries WHERE state='processed' AND outbox_id > 0) processed,
            (SELECT COUNT(*) FROM ems_event_deliveries WHERE state='dlq') dlq,
            (SELECT COUNT(DISTINCT event_name) FROM event_consumers WHERE active=1) subs"
)->fetch_assoc();
$PAGE_TITLE = 'لوحة الناقل';
$TILES = array(
    array('وقائعُ الجذر', (int) $g['facts']),
    array('تسليماتٌ حقيقية', (int) $g['deliveries']),
    array('سُلّمت بنجاح', (int) $g['processed']),
    array('صندوق الموتى', (int) $g['dlq']),
    array('أنواعٌ لها مشتركون', (int) $g['subs']),
);
$COLS = null; // أعمدةُ الاستعلامِ بأسمائِها
$EMPTY_TITLE = 'لا وقائعَ منشورةً على الناقلِ بعدُ';
$EMPTY_HINT  = 'تُنشَر الوقائعُ آليًّا عند اعتمادِ المستنداتِ وحركاتِ النظام';
include __DIR__ . '/../includes/eng01_screen_view.php';

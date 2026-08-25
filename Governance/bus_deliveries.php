<?php
/**
 * Governance/bus_deliveries.php — تسليمات الأحداث وحالاتها
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: المرحلة «نراقب الأحداثَ والمهام» · مجموعة «ناقلُ الأحداث»
 * أفعالُها في قاموسِ الأفعال: 'bus.deliver', 'bus.dlq.decide'
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
require_once __DIR__ . '/../app/Services/Bus/EventDeliveryWorker.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/bus_deliveries.php';
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
    $w = new \App\Services\Bus\EventDeliveryWorker($conn, 'ui-' . $uid);
    if ($__action === 'deliver') {
        $r = $w->deliverOne((int) ($_POST['delivery_id'] ?? 0));
        $flash = $r === null ? 'لم يلتقط — سبقه عامل آخر' : ('نتيجة التسليم: ' . $r);
        $flashKind = ($r === 'processed') ? 'success' : ($r === null ? 'info' : 'error');
    } elseif ($__action === 'dlq_decide') {
        $r = $w->decideDlq((int) ($_POST['delivery_id'] ?? 0), (string) ($_POST['decision'] ?? ''),
                           (string) ($_POST['reason'] ?? ''), $uid);
        $flash = $r['reason']; $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'release_stale') {
        $n = $w->releaseStale(3600);
        $flash = 'حرر ' . $n . ' تسليما عالقا'; $flashKind = 'success';
    }
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('d.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT d.id, e.event_key, d.consumer_key, d.state, d.attempt_no,
            d.next_attempt_at, d.processed_at, d.result_ref, d.fail_code,
            LEFT(COALESCE(d.fail_text,''), 60) fail_text, d.seed_tag
       FROM ems_event_deliveries d
  LEFT JOIN ems_business_events e ON e.id = d.outbox_id
      WHERE {$where}
      ORDER BY d.id DESC LIMIT 200"
);
$stats = $conn->query(
    "SELECT SUM(state='published') published, SUM(state='claimed') claimed,
            SUM(state='processing') processing, SUM(state='processed') processed,
            SUM(state='failed') failed, SUM(state='dlq') dlq
       FROM ems_event_deliveries WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'تسليمات الأحداث وحالاتها';
$TILES = array(
    array('منشور', (int) $stats['published']), array('ملتقط', (int) $stats['claimed']),
    array('قيد التنفيذ', (int) $stats['processing']), array('نجح', (int) $stats['processed']),
    array('فشل', (int) $stats['failed']), array('صندوق الموتى', (int) $stats['dlq']),
);
$COLS = array('#','رمز الحدث','المستهلك','الحالة','المحاولة','الإعادة القادمة','وقت النجاح','مرجع الأثر','رمز الفشل','سبب الفشل','وسم البذر');
$EMPTY_TITLE = 'لا تسليمات مسجلة بعد';
$EMPTY_HINT  = 'تظهر التسليمات عند نشر وقائع لأحداث لها مشتركون معلنون';
include __DIR__ . '/../includes/eng01_screen_view.php';

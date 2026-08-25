<?php
/**
 * Governance/bus_outbox.php — صندوق الأحداث الصادر
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: المرحلة «نراقب الأحداثَ والمهام» · مجموعة «ناقلُ الأحداث»
 * أفعالُها في قاموسِ الأفعال: 'bus.event.publish'
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
require_once __DIR__ . '/../app/Services/Bus/EventOutboxFanout.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Governance/bus_outbox.php';
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
    if ($__action === 'recount') {
        // إعادةُ عدِّ المستهلكينَ المعلَنينَ لحدثٍ — خدمةٌ لا كتابةٌ من هنا
        $oid = (int) ($_POST['outbox_id'] ?? 0);
        $row = $conn->query("SELECT event_key, company_id FROM ems_business_events WHERE id=" . $oid)->fetch_assoc();
        if (!$row) { $flash = 'صف صادر غير موجود'; $flashKind = 'error'; }
        else {
            $n = \App\Services\Bus\EventOutboxFanout::open($conn, $oid, $row['event_key'], (int) $row['company_id']);
            $flash = 'أعيد فتح ' . $n . ' صف تسليم للحدث #' . $oid; $flashKind = 'success';
        }
    }
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('e.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT e.id, e.event_no, e.event_key, e.entity_type, e.entity_id, e.created_at,
            e.consumers_declared, e.delivered_ok, e.delivered_failed, e.in_dlq, e.seed_tag
       FROM ems_business_events e
      WHERE {$where}
      ORDER BY e.id DESC LIMIT 200"
);
$stats = $conn->query(
    "SELECT COUNT(*) total,
            SUM(delivered_ok > 0) delivered,
            SUM(in_dlq = 1) dlq,
            SUM(delivered_ok = 0 AND delivered_failed = 0) pending
       FROM ems_business_events WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'صندوق الأحداث الصادر';
$TILES = array(
    array('وقائع منشورة', (int) $stats['total']),
    array('سلمت لمستهلك واحد فأكثر', (int) $stats['delivered']),
    array('بانتظار التسليم', (int) $stats['pending']),
    array('في صندوق الموتى', (int) $stats['dlq']),
);
$COLS = array('#','رقم الحدث','رمز الحدث','نوع الواقعة','معرفها','وقت النشر','مستهلكون معلنون','نجح','فشل','صندوق الموتى','وسم البذر');
$EMPTY_TITLE = 'صندوق الصادر فارغ بعد';
$EMPTY_HINT  = 'تنشر الوقائع آليا عند اعتماد المستندات وحركات النظام';
include __DIR__ . '/../includes/eng01_screen_view.php';

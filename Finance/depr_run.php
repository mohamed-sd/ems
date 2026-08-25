<?php
/**
 * Finance/depr_run.php — احتساب إهلاك الفترة
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: خارج GOV-24 (سايدبار الحوكمة) — وموضعُها الحيُّ: مجموعة «الإهلاك» لدور المالية
 * أفعالُها في قاموسِ الأفعال: 'depr.run', 'depr.reverse'
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
require_once __DIR__ . '/../app/Services/Assets/DepreciationRunService.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Finance/depr_run.php';
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
    if ($__action === 'run') {
        $r = \App\Services\Assets\DepreciationRunService::run(
            $conn, $company_id, (string) ($_POST['period'] ?? ''), $uid);
        $flash = $r['summary']; $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'reverse') {
        $r = \App\Services\Assets\DepreciationRunService::reverse($conn, array(
            'rec_id' => $_POST['rec_id'] ?? 0,
            'reason' => $_POST['reason'] ?? '',
            'actor'  => $uid,
        ));
        $flash = $r['ok'] ? ('عكس ' . $r['reversed'] . ' بمرجع ' . $r['ref']) : $r['reason'];
        $flashKind = $r['ok'] ? 'success' : 'error';
    }
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('a.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT a.rec_id, a.machine_code, a.period, a.owner_type, a.depr_method,
            a.hours_from_shifts, a.depreciation_per_hour, a.depreciation_amount,
            a.journal_ref, a.depr_reversed_amount, a.depr_reversal_ref, a.depr_reversed_at
       FROM asset_hour_reconciliations a
      WHERE {$where}
        AND (a.depreciation_amount IS NOT NULL OR a.depr_reversed_amount IS NOT NULL)
      ORDER BY a.period DESC, a.rec_id DESC LIMIT 200"
);
$s = $conn->query(
    "SELECT SUM(depreciation_amount) live, SUM(depr_reversed_amount) rev,
            SUM(owner_type='supplier' AND depreciation_amount IS NOT NULL) bad
       FROM asset_hour_reconciliations WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'احتساب إهلاك الفترة';
$TILES = array(
    array('إهلاك قائم', number_format((float) $s['live'], 2)),
    array('مبالغ معكوسة بمرجعها', number_format((float) $s['rev'], 2)),
    array('إهلاك على معدة مورد (CK-18)', (int) $s['bad']),
);
$COLS = array('#','كود المعدة','الفترة','الملكية','الطريقة','ساعات التشغيل','معدل الساعة','الإهلاك','مرجع القيد','مبلغ معكوس','مرجع العكس','وقت العكس');
/* UXW-01 §8-2: موضعُ الشاشةِ من رحلةِ المعدة — الغلافُ يُخرِج الشريط */
$ENTITY_KEY = 'equipment';
$ENTITY_TAB = 'الإهلاك';
include __DIR__ . '/../includes/eng01_screen_view.php';

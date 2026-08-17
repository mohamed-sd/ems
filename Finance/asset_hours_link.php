<?php
/**
 * Finance/asset_hours_link.php — ربط الأصل بساعات تشغيله
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: خارج GOV-24 (سايدبار الحوكمة) — وموضعُها الحيُّ: مجموعة «الإهلاك» لدور المالية
 * أفعالُها في قاموسِ الأفعال: 'asset.hours.link'
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
require_once __DIR__ . '/../app/Services/Assets/AssetHoursService.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role_id        = intval($_SESSION['user']['role'] ?? 0);
$SCREEN         = 'Finance/asset_hours_link.php';
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
    if ($__action === 'link') {
        $r = \App\Services\Assets\AssetHoursService::link($conn, array(
            'company_id'        => $company_id,
            'equipment_id'      => $_POST['equipment_id'] ?? 0,
            'period'            => $_POST['period'] ?? '',
            'asset_id'          => $_POST['asset_id'] ?? '',
            'depr_method'       => $_POST['depr_method'] ?? '',
            'useful_life_hours' => $_POST['useful_life_hours'] ?? '',
            'rate_per_hour'     => $_POST['rate_per_hour'] ?? '',
            'actor'             => $uid,
        ));
        $flash = $r['ok']
            ? ('رُبط — ساعاتُ التشغيل من القيد اليومي: ' . $r['hours'] . ' · الملكية: ' . $r['owner_type'])
            : $r['reason'];
        $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'refresh_all') {
        $n = \App\Services\Assets\AssetHoursService::refreshAll($conn, $company_id);
        $flash = 'رُدمت الساعاتُ لـ' . $n . ' صفَّ معدة-شهر'; $flashKind = 'success';
    }
}

// ═══ ⑦ العرض ═══
$where = $is_super_admin ? '1=1' : ('a.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT a.rec_id, a.machine_code, a.period, a.owner_type, a.asset_id, a.depr_method,
            a.useful_life_hours, a.depreciation_per_hour, a.hours_from_shifts,
            a.hours_undepreciated, a.depreciation_amount, a.depr_reversed_amount, a.depr_reversal_ref
       FROM asset_hour_reconciliations a
      WHERE {$where} AND a.hours_from_shifts > 0
      ORDER BY a.period DESC, a.machine_code LIMIT 200"
);
$s = $conn->query(
    "SELECT COUNT(*) n, SUM(owner_type='supplier') sup,
            SUM(hours_from_shifts) hrs,
            SUM(owner_type='company' AND depreciation_amount IS NULL AND hours_from_shifts>0) undep
       FROM asset_hour_reconciliations WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'ربط الأصل بساعات تشغيله';
$TILES = array(
    array('صفوفُ معدة-شهر', (int) $s['n']),
    array('منها معداتُ موردين', (int) $s['sup']),
    array('ساعاتُ تشغيلٍ مقيسة', round((float) $s['hrs'], 1)),
    array('عملت بلا إهلاك (CK-17)', (int) $s['undep']),
);
$COLS = array('#','كود المعدة','الفترة','الملكية','الأصل','طريقة الإهلاك','العمر (ساعة)','معدل الساعة','ساعات التشغيل','ساعات بلا إهلاك','الإهلاك','مبلغ معكوس','مرجع العكس');
include __DIR__ . '/../includes/eng01_screen_view.php';

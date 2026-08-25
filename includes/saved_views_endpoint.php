<?php
/**
 * includes/saved_views_endpoint.php — نقطةُ المناظرِ المحفوظة (AC-U5 · SH-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نقطةٌ واحدةٌ لكلِّ الشاشاتِ: تقرأ مناظرَ الشاشةِ الحاليةِ للمستخدمِ ودورِه،
 *   وتحفظ منظرًا شخصيًّا. ولا تُنشئ شاشةً ولا تعدّل بيانات عمل.
 *
 * ◆ العقدُ السبعيُّ يُطبَّق كما على أيِّ كتابة: طريقةٌ ← رمزُ حمايةٍ ← صلاحيةُ
 *   عرضِ الشاشةِ المعنيّة ← تحققٌ ← ثم الكتابة. ومنظرٌ يُحفَظ لشاشةٍ لا يملك
 *   المستخدمُ عرضَها تسريبُ بنيةٍ — يكشف أسماءَ أعمدةِ شاشةٍ محجوبةٍ عنه.
 *
 * ◆ والملكية: يقرأ منظرَه الشخصيَّ إن وُجد، وإلا منظرَ دورِه الافتراضيّ.
 *   ولا يكتب أحدٌ منظرَ دورٍ من هنا — ذاك من شاشةِ الإعداداتِ بصلاحيتها.
 *
 * ◆ ولا حذف: «إزالةُ منظر» تعطيلٌ (`active=0`) — فمنظرٌ يعتمده تقريرٌ دوريٌّ
 *   لا يختفي بنقرة.
 */
require_once __DIR__ . '/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/permissions_helper.php';
require_once __DIR__ . '/catch_log.php';

header('Content-Type: application/json; charset=utf-8');

/** ردٌّ موحَّدٌ ثم خروج. */
function sv_out($ok, $data = null, $msg = '', $code = 200)
{
    http_response_code($code);
    echo json_encode(array('ok' => (bool) $ok, 'data' => $data, 'msg' => $msg),
        JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user'])) { sv_out(false, null, 'الجلسة منتهية', 401); }
$uid  = (int) ($_SESSION['user']['id'] ?? 0);
$role = (int) ($_SESSION['user']['role'] ?? 0);
$co   = (int) ($_SESSION['user']['company_id'] ?? 0);

$screen = trim((string) ($_REQUEST['screen'] ?? ''));
// المسارُ يُطبَّع ويُقيَّد بشكلِه: `Dir/file.php` — ولا يقبل صعودًا ولا مطلقًا.
$screen = ltrim(str_replace('\\', '/', $screen), '/');
if ($screen === '' || strpos($screen, '..') !== false
    || !preg_match('#^[A-Za-z0-9_./-]+\.php$#', $screen)) {
    sv_out(false, null, 'مسار شاشة غير صالح', 422);
}

/* ◆ صلاحيةُ عرضِ الشاشةِ المعنيّة شرطٌ للقراءةِ والكتابةِ معًا. */
$allowed = ((string) $role === '-1');
if (!$allowed) {
    $st = $conn->prepare(
        'SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
          WHERE m.code = ? AND rp.role_id = ? AND rp.can_view = 1 LIMIT 1');
    if ($st) {
        $st->bind_param('si', $screen, $role);
        if ($st->execute() && $st->get_result()->fetch_row()) { $allowed = true; }
        $st->close();
    }
}
if (!$allowed) { sv_out(false, null, 'لا صلاحية عرض لهذه الشاشة', 403); }

$action = (string) ($_REQUEST['do'] ?? 'list');

/* ── القراءة ─────────────────────────────────────────────────────────── */
if ($action === 'list') {
    $st = $conn->prepare(
        "SELECT id, view_name, columns_json, is_default, owner_kind
           FROM ems_saved_views
          WHERE company_id = ? AND screen = ? AND active = 1
            AND ((owner_kind = 'user' AND owner_id = ?) OR (owner_kind = 'role' AND owner_id = ?))
          ORDER BY owner_kind DESC, is_default DESC, view_name");
    if (!$st) { sv_out(false, null, 'تعذرت القراءة', 500); }
    $st->bind_param('isii', $co, $screen, $uid, $role);
    $st->execute();
    $rs = $st->get_result();
    $out = array();
    while ($r = $rs->fetch_assoc()) {
        $out[] = array(
            'id'      => (int) $r['id'],
            'name'    => (string) $r['view_name'],
            'columns' => $r['columns_json'] !== null ? json_decode($r['columns_json'], true) : null,
            'default' => (int) $r['is_default'] === 1,
            'mine'    => $r['owner_kind'] === 'user',
        );
    }
    $st->close();
    sv_out(true, $out);
}

/* ── الحفظ — كتابةٌ فتلزمها بقيةُ العقد ───────────────────────────────── */
if ($action === 'save') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        sv_out(false, null, 'الحفظ بPOST حصرا', 405);
    }
    if (function_exists('verify_csrf_token') && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        sv_out(false, null, 'رمز الحماية غير صالح', 403);
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) { sv_out(false, null, 'اسم المنظر إلزامي وقصير', 422); }

    $cols = $_POST['columns'] ?? '';
    $arr  = is_string($cols) ? json_decode($cols, true) : $cols;
    if (!is_array($arr)) { sv_out(false, null, 'قائمة الأعمدة غير صالحة', 422); }
    $arr = array_values(array_unique(array_map('intval', $arr)));
    sort($arr);
    $json = json_encode($arr);

    $st = $conn->prepare(
        "INSERT INTO ems_saved_views
            (company_id, screen, view_name, owner_kind, owner_id, columns_json, is_default, active, created_by)
         VALUES (?, ?, ?, 'user', ?, ?, 0, 1, ?)
         ON DUPLICATE KEY UPDATE columns_json = VALUES(columns_json), active = 1, updated_at = NOW()");
    if (!$st) { sv_out(false, null, 'تعذر الحفظ', 500); }
    $st->bind_param('issisi', $co, $screen, $name, $uid, $json, $uid);
    $ok = $st->execute();
    $st->close();
    sv_out((bool) $ok, null, $ok ? 'حفظ المنظر' : 'تعذر الحفظ', $ok ? 200 : 500);
}

sv_out(false, null, 'فعل غير معروف', 400);

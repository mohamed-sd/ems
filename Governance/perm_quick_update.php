<?php
/**
 * Governance/perm_quick_update.php — معالج تحديث صلاحية تقرير (PERM-SCR-01 ⑩)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **نقطة نهاية لا شاشة**: لا قشرة ولا سايدبار ولا تصيير. تستقبل POST من
 *   شاشة صلاحيات التقارير وترد JSON.
 * ◆ **و`report_role_permissions` جدول حضور لا اعلام**: وجود الصف يعني السماح
 *   وحذفه يعني المنع - فلا عمود `allow` يقلب.
 * ⛔ والحارس هنا هو الحارس نفسه الذي يحرس الشاشة: صلاحية المسار من
 *   `check_page_permissions` - لا يفتح المعالج بابا لا تفتحه الشاشة.
 * ⛔ ورمز الحماية مطلوب كما في اي كتابة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/**
 * رد موحد ثم خروج - فلا يخرج المعالج نصا خارج JSON ابدا.
 * ⛔ **ونوع المحتوى يعاد تأكيده هنا**: `config.php` يفرض `text/html` عند
 *    تضمينه (سطر 61) لان `header()` لا يرسل فورا فتبقى `headers_sent()` كاذبة
 *    - فيدهس اعلاني السابق. ومصفي المخرج يحقن وسم سكربت في كل رد `text/html`،
 *    فيفسد JSON ويسقط `r.json()` في المتصفح وتنقلب الحالة الظاهرة خطأ.
 *    والاصلاح محلي: يعاد التأكيد عند لحظة الرد لا قبل التضمين.
 */
function pq_out($ok, $message, $extra = array())
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(array('success' => (bool) $ok, 'message' => $message), $extra),
        JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    pq_out(false, 'الجلسة منتهية. اعد تسجيل الدخول.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    pq_out(false, 'الطلب يجب ان يكون POST');
}

include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';

if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    http_response_code(403);
    pq_out(false, 'رمز الحماية غير صالح');
}

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$__pp = check_page_permissions($conn, 'Governance/perm_reports.php');
if (!$is_super_admin && empty($__pp['can_edit'])) {
    http_response_code(403);
    pq_out(false, 'لا صلاحية لتعديل صلاحيات التقارير');
}

$roleId = (int) ($_POST['role_id'] ?? 0);
$code   = trim((string) ($_POST['report_code'] ?? ''));
$allow  = !empty($_POST['allow']) ? 1 : 0;

if ($roleId <= 0 || $code === '') {
    http_response_code(422);
    pq_out(false, 'الدور ورمز التقرير مطلوبان');
}

/* الدور يجب ان يكون قائما - فمنحة لدور محذوف صف يتيم. */
$exists = 0;
if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM roles WHERE id = ?')) {
    mysqli_stmt_bind_param($st, 'i', $roleId);
    mysqli_stmt_execute($st);
    mysqli_stmt_bind_result($st, $exists);
    mysqli_stmt_fetch($st);
    mysqli_stmt_close($st);
}
if ($exists <= 0) {
    http_response_code(422);
    pq_out(false, 'الدور غير موجود');
}

$has = 0;
if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM report_role_permissions WHERE role_id = ? AND report_code = ?')) {
    mysqli_stmt_bind_param($st, 'is', $roleId, $code);
    mysqli_stmt_execute($st);
    mysqli_stmt_bind_result($st, $has);
    mysqli_stmt_fetch($st);
    mysqli_stmt_close($st);
}

if ($allow === 1 && $has === 0) {
    if ($st = mysqli_prepare($conn, 'INSERT INTO report_role_permissions (role_id, report_code) VALUES (?, ?)')) {
        mysqli_stmt_bind_param($st, 'is', $roleId, $code);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        pq_out($ok, $ok ? 'منح' : 'تعذر المنح', array('allow' => 1));
    }
    pq_out(false, 'تعذر تجهيز الطلب');
}
if ($allow === 0 && $has > 0) {
    if ($st = mysqli_prepare($conn, 'DELETE FROM report_role_permissions WHERE role_id = ? AND report_code = ?')) {
        mysqli_stmt_bind_param($st, 'is', $roleId, $code);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        pq_out($ok, $ok ? 'منع' : 'تعذر المنع', array('allow' => 0));
    }
    pq_out(false, 'تعذر تجهيز الطلب');
}

/* الحالة المطلوبة هي القائمة - نجاح بلا كتابة، فالمعالج غير متكرر الاثر. */
pq_out(true, 'بلا تغيير', array('allow' => $allow));

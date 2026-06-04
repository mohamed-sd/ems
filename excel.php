<?php
/**
 * excel.php — المتحكّم الأمامي الموحّد لإطار Excel (نقطة الدخول الوحيدة).
 *
 * الاستخدام:
 *   GET  excel.php?entity=clients&action=template        → تنزيل النموذج
 *   GET  excel.php?entity=clients&action=export          → تنزيل التصدير
 *   POST excel.php?entity=clients&action=import_preview  → معاينة (JSON) + ملف + csrf_token
 *   POST excel.php?entity=clients&action=import_commit   → تنفيذ (JSON) + token + csrf_token
 *
 * يقوم config.php بتوفير الجلسة الآمنة، الاتصال $conn، توكن CSRF، ومُحمّل App\.
 *
 * @package EMS
 */

require_once __DIR__ . '/config.php';

use App\Services\Excel\ExcelService;

/*
 * ── معالجة الأخطاء المركزية ───────────────────────────────────────────────
 * نقطة واحدة لكل أخطاء إطار Excel: أي استثناء أو خطأ فادح (Fatal) يتحوّل هنا
 * إلى ردّ JSON واضح + تسجيل في السجل، بدل الشاشة البيضاء. أي شاشة في النظام
 * تمرّ عبر excel.php، فتُعالَج مشاكلها من هذا المكان وحده.
 */

/** كتابة الخطأ في سجل مركزي للتشخيص. */
function ems_excel_log_error(string $context, string $message): void
{
    $dir = (defined('EMS_LOGS_DIR') ? EMS_LOGS_DIR : __DIR__ . '/storage/logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents(
        $dir . '/excel.log',
        '[' . date('Y-m-d H:i:s') . "] {$context}: {$message}\n",
        FILE_APPEND | LOCK_EX
    );
}

/** إرسال ردّ خطأ موحّد (JSON) إن لم يُرسَل خرج بعد. */
function ems_excel_emit_error(int $code, string $message): void
{
    if (headers_sent()) {
        return; // بدأ بثّ ملف بالفعل — لا يمكن استبداله.
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
}

// يلتقط الأخطاء الفادحة (Fatal/Parse) التي لا يطالها try/catch.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        ems_excel_log_error('FATAL', $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
        ems_excel_emit_error(500, 'تعذّر إكمال عملية Excel بسبب خطأ في النظام. تم تسجيل المشكلة، يرجى إبلاغ الدعم الفني.');
    }
});

try {
    if (!isset($_SESSION['user'])) {
        ems_excel_emit_error(401, 'غير مصرّح — يرجى تسجيل الدخول');
        exit;
    }

    $entity = isset($_GET['entity']) ? preg_replace('/[^a-z_]/', '', strtolower($_GET['entity'])) : '';
    $action = isset($_GET['action']) ? preg_replace('/[^a-z_]/', '', strtolower($_GET['action'])) : '';

    $service = new ExcelService($conn);
    $def = $service->definition($entity);

    switch ($action) {
        case 'template':
            $service->template($def); // يبثّ ويُنهي.
            break;

        case 'export':
            $service->export($def); // يبثّ ويُنهي.
            break;

        case 'import_preview':
            $result = $service->importPreview($def);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'import_commit':
            $result = $service->importCommit($def);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            $service->fail(400, 'إجراء غير معروف. الإجراءات المتاحة: template, export, import_preview, import_commit');
    }
} catch (\Throwable $e) {
    // ExcelService::fail() يستدعي exit، فلا يصل هنا إلا الأخطاء غير المتوقّعة.
    ems_excel_log_error('EXCEPTION', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    ems_excel_emit_error(500, 'تعذّر إكمال عملية Excel: خطأ غير متوقّع. تم تسجيل المشكلة للمراجعة.');
    exit;
}

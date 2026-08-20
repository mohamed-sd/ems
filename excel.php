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

/*
 * FIX-01 · RF-03 خطوة ① (FIXA-0027) — طبقةُ الصلاحياتِ تُحمَّل **في الرأسِ قبلَ
 * أيِّ نداءٍ للتصريح**. كان ‎config.php‎ لا يحمّلها، فيسقط ‎ExcelService::authorize‎
 * في فرعِه المفتوح ‎(!function_exists(...)) return;‎ ويمرُّ التصديرُ الموحَّدُ بلا
 * أيِّ فحصِ تفويضٍ لكلِّ كيانٍ في المنصة. والفرعُ المفتوحُ أُغلق أيضًا — فالتحميلُ
 * وحدَه لا يكفي: لو غاب لسببٍ ما يجب أن يُقرأ منعًا لا سماحًا (CS-02).
 */
require_once __DIR__ . '/includes/permissions_helper.php';

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

    /* ══ عزلُ الإدارات (سابعًا-⑤) — التصديرُ قناةٌ من الثمان ══════════════════
       ◆ **وهو أخطرُ أبوابِ الاطّلاع**: الشاشةُ أُزيلت من السايدبارِ ومُنعت
         بالعنوانِ المباشرِ وحُجب معالجُها — **ويظل `excel.php?entity=…&export`
         يبثُّ جدولَها كاملًا**، لأنه يسأل عن الصلاحيةِ لا عن المساحة.
         **وملفٌّ يُنزَّل أخطرُ من شاشةٍ تُعرض: يخرج من النظامِ ولا يعود.**
       ◆ **والربطُ بالشاشةِ المالكةِ للكيان** لا بالكيانِ اسمًا: يُسأل الحارسُ عن
         مسارِ الشاشةِ التي يخدمها هذا الكيانُ في هذه المساحة.
       ◆ **والغيابُ ليس منعًا**: كيانٌ لا شاشةَ مسجَّلةً له يمرُّ كما كان. */
    $__sf = __DIR__ . '/includes/space_scope.php';
    if (is_file($__sf) && isset($_SESSION['user'])
        && strval(isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '') !== '-1'
        && in_array($action, array('export', 'template'), true)) {
        require_once $__sf;
        if (function_exists('ems_scope_forbids') && function_exists('ems_active_scope')) {
            $__scope = ems_active_scope();
            $__tbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def->table);
            $__st = $conn->prepare(
                "SELECT a.route FROM gov_space_appearances a
                  WHERE a.space_ar = ? AND a.cls = 'FORBIDDEN'
                    AND (LOWER(a.route) LIKE CONCAT('%', LOWER(?), '%')
                         OR LOWER(a.route) LIKE CONCAT('%', LOWER(?), '%'))
                  LIMIT 1");
            if ($__st) {
                $__st->bind_param('sss', $__scope, $entity, $__tbl);
                $__st->execute();
                $__r = $__st->get_result();
                $__hit = $__r ? $__r->fetch_row() : null;
                $__st->close();
                if ($__hit) {
                    http_response_code(403);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(array(
                        'ok' => false, 'code' => 'SCOPE-403',
                        'message' => 'هذا التصديرُ يخصُّ مساحةَ عملٍ أخرى — بدِّلِ المساحةَ لتنفيذِه.',
                        'route' => $__hit[0],
                    ), JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    }

    switch ($action) {
        case 'template':
            $service->template($def); // يبثّ ويُنهي.
            break;

        case 'export':
            /* BR-GOV-07 (م-هـ): التصدير أخطر أبواب الاطّلاع — يُفحص مركزيًّا
               هنا ضد قاموس الحقول الحساسة (scr_sensitive_fields): كل حقل
               «يُسجَّل الاطلاع؟ = نعم» في جدول الكيان يُقيَّد اطلاعًا باسم
               المصدِّر قبل بث الملف (القناة الموحدة sensitive_read_log). */
            try {
                require_once __DIR__ . '/includes/sensitive_read_log.php';
                $tbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def->table);
                $rs = $conn->query("SELECT field_name FROM scr_sensitive_fields
                                     WHERE table_name = '" . $conn->real_escape_string($tbl) . "'
                                       AND log_views_flag LIKE '%نعم%' AND status = 'معتمد'");
                while ($rs && ($sf = $rs->fetch_assoc())) {
                    ems_log_sensitive_read($conn, $tbl . '.' . $sf['field_name'],
                        'export:' . $entity, 'excel.php?action=export');
                }
            } catch (\Throwable $t) {
                ems_excel_log_error('SENSITIVE_LOG', $t->getMessage()); // لا يعطل التصدير
            }
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

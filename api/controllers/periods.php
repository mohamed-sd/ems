<?php
/**
 * api/controllers/periods.php — مزامنة فترات الورديات (N-08 · WRK-01 §2.1)
 *   POST /api/sync/periods — رفع دفعي لفترات مسجَّلة دون اتصال.
 *
 * **العطالة بالمفتاح الطبيعي (معدة × تاريخ × وردية × فترة)** — UQ بنيوي في
 * shift_period_logs: إعادة رفع الدفعة نفسها بعد انقطاع = صفر تكرار، وكل صف
 * مكرر يُرَدّ بحالة duplicate بمرجع سجله القائم (لا فشل يوقف الدفعة).
 * وهو شرط تحقق معيار قبول UX-03 («يوم موقع كامل من الجوال ثم مزامنة بلا تكرار»).
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** POST /api/sync/periods — دفعة فترات، كلٌّ بعقود ShiftPeriodService (WRK-01). */
function periods_sync_push(): void
{
    global $conn;
    $ctx = api_require_auth();
    $body = api_input();
    $rows = isset($body['periods']) && is_array($body['periods']) ? $body['periods'] : null;
    if ($rows === null) {
        api_fail('الحمولة: {"periods": [...]} — دفعة لا صفًّا حرًّا', 422);
    }

    require_once dirname(__DIR__, 2) . '/app/Services/Workforce/AttendanceService.php';
    $gate = function_exists('ems_tenant_db') ? ems_tenant_db() : null;
    if ($gate === null) {
        api_fail('بوابة العزل غير متاحة', 500);
    }

    $results = array();
    $applied = 0; $duplicates = 0; $rejected = 0;
    foreach ($rows as $i => $p) {
        $p = is_array($p) ? $p : array();
        $p['_sync'] = true; // DEC-01 ⑨: قناة المزامنة — التأخر فوق يوم يُعلَّم لا يُرفض
        $r = \App\Services\Workforce\AttendanceService::logPeriod($gate, (int) $ctx['company_id'], $p, (int) $ctx['user_id']);
        if ($r['ok']) {
            $applied++;
            $results[] = array('index' => $i, 'status' => 'applied', 'log_id' => $r['log_id']);
        } elseif ($r['code'] === 409) {
            // العطالة: المفتاح الطبيعي قائم — لا تكرار ولا فشل للدفعة
            $duplicates++;
            $results[] = array('index' => $i, 'status' => 'duplicate', 'reason' => $r['reason']);
        } else {
            $rejected++;
            $results[] = array('index' => $i, 'status' => 'rejected', 'code' => $r['code'], 'reason' => $r['reason']);
        }
    }
    api_ok(array(
        'applied' => $applied, 'duplicates' => $duplicates, 'rejected' => $rejected,
        'results' => $results,
    ), 'مزامنة الفترات: ' . $applied . ' مطبَّق · ' . $duplicates . ' مكرر (عاطل) · ' . $rejected . ' مرفوض بسببه');
}

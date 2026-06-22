<?php
/**
 * api/controllers/timesheet.php — مدير الموقع (التايم شيت).
 *   GET  /api/timesheet/refdata
 *   GET  /api/operations/by-type?type=&shift=
 *   GET  /api/operations/{id}/drivers?shift=
 *   GET  /api/operations/{id}/contract-hours
 *   GET  /api/failure-codes[?equipment_type=]
 *   GET  /api/timesheets (+filters)   GET /api/timesheets/{id}
 *   POST /api/timesheets   PUT /api/timesheets/{id}   DELETE /api/timesheets/{id}
 *
 * يطابق منطق Timesheet/timesheet.php (الحقول حسب النوع، الحسابات التلقائية،
 * تحقّق الأعطال) مع فرض عزل المشروع على كل المسارات (حتى التفاصيل المفردة).
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** استثناء تحقّق يحمل كود HTTP في getCode(). */
class TimesheetError extends \Exception
{
    public function __construct(string $message, int $http = 422)
    {
        parent::__construct($message, $http);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// أدوات عامة للإدراج/التحديث بعبارات مُجهّزة (كل القيم تُربط كنصوص — MySQL يحوّل)
// ═══════════════════════════════════════════════════════════════════════════

function ts_db_insert(mysqli $conn, string $table, array $assoc): int
{
    $cols = array_keys($assoc);
    $place = implode(', ', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES ($place)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new TimesheetError('تعذّر تجهيز الإدراج: ' . mysqli_error($conn), 500);
    }
    $vals = array_values($assoc);
    $types = str_repeat('s', count($vals));
    mysqli_stmt_bind_param($stmt, $types, ...$vals);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new TimesheetError('فشل الإدراج: ' . $err, 500);
    }
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return intval($id);
}

function ts_db_update(mysqli $conn, string $table, array $assoc, string $whereSql, array $whereVals): void
{
    $sets = [];
    foreach (array_keys($assoc) as $c) {
        $sets[] = "`$c` = ?";
    }
    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $whereSql";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new TimesheetError('تعذّر تجهيز التحديث: ' . mysqli_error($conn), 500);
    }
    $vals = array_merge(array_values($assoc), $whereVals);
    $types = str_repeat('s', count($vals));
    mysqli_stmt_bind_param($stmt, $types, ...$vals);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new TimesheetError('فشل التحديث: ' . $err, 500);
    }
    mysqli_stmt_close($stmt);
}

/** clause عزل المشروع لجدول operations (alias o). operations.project_id يحمل int. */
function ts_project_clause(array $ctx, int $projectId, string $alias = 'o'): string
{
    // المستخدم مقيّد بمشروعه؛ السوبر أدمن غير مقيّد إن لم يمرّر مشروعاً.
    if ($ctx['is_super'] && $projectId <= 0) {
        return '1=1';
    }
    return "CAST($alias.project_id AS UNSIGNED) = " . intval($projectId);
}

/** يتحقق أن التشغيل ضمن مشروع المستخدم (وإلا استثناء). يعيد equipment_id. */
function ts_assert_operation_in_project(mysqli $conn, array $ctx, int $projectId, int $operationId): int
{
    if ($operationId <= 0) {
        throw new TimesheetError('يجب اختيار الآلية (التشغيل)', 422);
    }
    $clause = ts_project_clause($ctx, $projectId, 'o');
    $stmt = mysqli_prepare($conn, "SELECT o.equipment FROM operations o WHERE o.id = ? AND $clause LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $operationId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        throw new TimesheetError('التشغيل المختار غير موجود ضمن مشروعك', 404);
    }
    return intval($row['equipment']);
}

/** يجلب ساعات/نوع وردية التشغيل (authoritative للحساب). */
function ts_operation_shift(mysqli $conn, int $operationId): array
{
    $has_shift = db_table_has_column($conn, 'operations', 'shift_type');
    $sel = $has_shift ? 'shift_type' : "'B' AS shift_type";
    $stmt = mysqli_prepare($conn, "SELECT shift_hours, $sel FROM operations WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $operationId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $shiftType = $row && isset($row['shift_type']) ? strtoupper(trim((string)$row['shift_type'])) : 'B';
    if (!in_array($shiftType, ['D', 'N', 'B'], true)) {
        $shiftType = 'B';
    }
    return [
        'shift_hours' => $row ? floatval($row['shift_hours']) : 0.0,
        'shift_type'  => $shiftType,
        'allowed'     => $shiftType === 'D' ? ['D'] : ($shiftType === 'N' ? ['N'] : ['D', 'N']),
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// الحسابات التلقائية + تحقّق الأعطال (مطابقة لـ timesheet.php)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * يحسب الحقول المشتقّة خادمياً ويتحقق من توزيع الأعطال.
 * يعدّل $r (assoc القيم) بالقيم المحسوبة. يرمي TimesheetError عند الفشل.
 */
function ts_compute(array &$r, int $type, float $shiftHours): void
{
    $f = static fn($k) => isset($r[$k]) ? floatval($r[$k]) : 0.0;

    $bucket = $f('bucket_hours');
    $jack = $f('jackhammer_hours');
    $extra = $f('extra_hours');
    $standby = $f('standby_hours');
    $dependence = $f('dependence_hours');
    $maintenance = $f('maintenance_fault');
    $marketing = $f('marketing_fault');

    // الساعات المنفّذة
    if ($type === 1) {
        $executed = $bucket + $jack + $extra + $standby + $dependence;
    } else {
        $executed = $f('executed_hours');
    }
    $r['executed_hours'] = $executed;

    // مجموع الإضافية
    $extraTotal = $extra;
    $r['extra_hours_total'] = $extraTotal;

    // إجمالي ساعات العمل
    if ($type === 1) {
        $totalWork = $executed + $extraTotal;
    } else {
        $totalWork = $executed + $extraTotal + $standby;
    }
    $r['total_work_hours'] = $totalWork;

    // إجمالي ساعات التعطّل (محسوب)
    if ($type === 1) {
        $totalFault = $shiftHours - $executed;
    } else {
        $totalFault = $shiftHours - $executed - $standby - $dependence;
    }
    if ($totalFault < 0) {
        $totalFault = 0.0;
    }
    $r['total_fault_hours'] = $totalFault;

    // استعداد المشغّل
    $r['operator_standby_hours'] = ($executed < $shiftHours) ? ($maintenance + $marketing + $dependence) : 0.0;
    // استعداد الآلية
    $r['machine_standby_hours'] = $standby;

    // الأمتار (خرّامة)
    if ($type === 3) {
        $r['meters_count'] = $f('drilling_holes_count') * $f('drilling_depth');
    }

    // فرق العدّاد
    if ($type === 1) {
        $startSec = (intval($f('start_hours')) * 3600) + (intval($f('start_minutes')) * 60) + intval($f('start_seconds'));
        $endSec = (intval($f('end_hours')) * 3600) + (intval($f('end_minutes')) * 60) + intval($f('end_seconds'));
        $diff = $endSec - $startSec;
        $r['counter_diff'] = $diff > 0 ? $diff : 0;
    } else {
        $r['counter_diff'] = $f('end_hours') - $f('start_hours');
    }

    // ✅ تحقّق الأعطال الإلزامي: مجموع الجهات = إجمالي التعطّل بالضبط
    if ($totalFault > 0) {
        $sum = $f('hr_fault') + $maintenance + $marketing + $f('approval_fault') + $f('other_fault_hours');
        if (abs($sum - $totalFault) > 0.001) {
            throw new TimesheetError(
                'خطأ في توزيع ساعات الأعطال: مجموع الجهات (' . round($sum, 2) .
                ') يجب أن يساوي إجمالي ساعات التعطّل (' . round($totalFault, 2) . ')',
                422
            );
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// تفاصيل الأعطال المتعددة (timesheet_failure_hours)
// ═══════════════════════════════════════════════════════════════════════════

/** إثراء عناصر الأعطال من جدول failure_codes (مطابق للويب). */
function ts_enrich_failures(mysqli $conn, int $type, array $items): array
{
    if (empty($items)) {
        return [];
    }
    foreach ($items as $i => $item) {
        $fid = isset($item['failure_code_id']) ? intval($item['failure_code_id']) : 0;
        $full = isset($item['full_code']) ? trim((string)$item['full_code']) : '';
        if ($fid > 0) {
            $where = 'id = ?';
            $bind = $fid;
            $btype = 'i';
        } elseif ($full !== '') {
            $where = 'full_code = ?';
            $bind = $full;
            $btype = 's';
        } else {
            continue;
        }
        $typeFilter = $type > 0 ? ' AND equipment_type = ' . $type : '';
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, equipment_type, event_type_code, event_type_name, main_category_code, main_category_name,
                    sub_category, failure_detail, full_code
             FROM failure_codes WHERE $where$typeFilter AND status = 1 LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, $btype, $bind);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            $items[$i] = array_merge($item, [
                'failure_code_id'    => intval($row['id']),
                'equipment_type'     => intval($row['equipment_type']),
                'event_type_code'    => $row['event_type_code'],
                'event_type_name'    => $row['event_type_name'],
                'main_category_code' => $row['main_category_code'],
                'main_category_name' => $row['main_category_name'],
                'sub_category'       => $row['sub_category'],
                'failure_detail'     => $row['failure_detail'],
                'full_code'          => $row['full_code'],
            ]);
        }
    }
    return $items;
}

/** حفظ تفاصيل الأعطال (delete-then-insert) داخل المعاملة الجارية. */
function ts_save_failures(mysqli $conn, int $timesheetId, int $operationId, string $date, int $type, int $companyId, int $userId, array $items): void
{
    if (!db_table_has_column($conn, 'timesheet_failure_hours', 'id')) {
        return;
    }
    $equipmentId = 0;
    if ($operationId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT equipment FROM operations WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $operationId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $equipmentId = $row ? intval($row['equipment']) : 0;
    }

    $del = mysqli_prepare($conn, 'DELETE FROM timesheet_failure_hours WHERE timesheet_id = ?');
    mysqli_stmt_bind_param($del, 'i', $timesheetId);
    if (!mysqli_stmt_execute($del)) {
        mysqli_stmt_close($del);
        throw new TimesheetError('فشل حذف تفاصيل الأعطال القديمة', 500);
    }
    mysqli_stmt_close($del);

    foreach ($items as $item) {
        $fid = isset($item['failure_code_id']) ? intval($item['failure_code_id']) : 0;
        if ($fid <= 0) {
            continue;
        }
        ts_db_insert($conn, 'timesheet_failure_hours', [
            'timesheet_id'       => (string)$timesheetId,
            'operation_id'       => (string)$operationId,
            'equipment_id'       => (string)$equipmentId,
            'failure_code_id'    => (string)$fid,
            'equipment_type'     => (string)$type,
            'event_type_code'    => (string)($item['event_type_code'] ?? ''),
            'event_type_name'    => (string)($item['event_type_name'] ?? ''),
            'main_category_code' => (string)($item['main_category_code'] ?? ''),
            'main_category_name' => (string)($item['main_category_name'] ?? ''),
            'sub_category'       => (string)($item['sub_category'] ?? ''),
            'failure_detail'     => (string)($item['failure_detail'] ?? ''),
            'full_code'          => (string)($item['full_code'] ?? ''),
            'timesheet_date'     => $date,
            'company_id'         => (string)$companyId,
            'created_by'         => (string)$userId,
        ]);
    }
}

/** يقرأ تفاصيل الأعطال لسجل. */
function ts_load_failures(mysqli $conn, int $timesheetId): array
{
    if (!db_table_has_column($conn, 'timesheet_failure_hours', 'id')) {
        return [];
    }
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, failure_code_id, equipment_type, event_type_code, event_type_name,
                main_category_code, main_category_name, sub_category, failure_detail, full_code
         FROM timesheet_failure_hours WHERE timesheet_id = ? AND status = 1 ORDER BY id ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $timesheetId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = [
                'id'                 => intval($row['id']),
                'failure_code_id'    => intval($row['failure_code_id']),
                'equipment_type'     => intval($row['equipment_type']),
                'event_type_code'    => $row['event_type_code'],
                'event_type_name'    => $row['event_type_name'],
                'main_category_code' => $row['main_category_code'],
                'main_category_name' => $row['main_category_name'],
                'sub_category'       => $row['sub_category'],
                'failure_detail'     => $row['failure_detail'],
                'full_code'          => $row['full_code'],
            ];
        }
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════
// الحفظ الموحّد (create/update) — يُستخدم من CRUD ومن المزامنة
// ═══════════════════════════════════════════════════════════════════════════

/** الحقول النصّية/الرقمية القابلة للكتابة في timesheet. */
function ts_writable_fields(): array
{
    return [
        'operator', 'driver', 'shift', 'date', 'shift_hours', 'executed_hours',
        'bucket_hours', 'jackhammer_hours', 'extra_hours', 'extra_hours_total',
        'standby_hours', 'dependence_hours', 'total_work_hours', 'work_notes',
        'hr_fault', 'maintenance_fault', 'marketing_fault', 'approval_fault',
        'other_fault_hours', 'total_fault_hours', 'fault_notes',
        'start_seconds', 'start_minutes', 'start_hours', 'end_seconds', 'end_minutes', 'end_hours',
        'counter_diff', 'fault_type', 'fault_department', 'fault_part', 'fault_details', 'general_notes',
        'operator_hours', 'machine_standby_hours', 'jackhammer_standby_hours', 'bucket_standby_hours',
        'extra_operator_hours', 'operator_standby_hours', 'operator_notes',
        'tons_count', 'trips_count', 'transport_type', 'meters_type', 'meters_count',
        'drilling_holes_count', 'drilling_depth', 'type',
    ];
}

/** تطبيع تاريخ Y-m-d (يقبل عدة صيغ). */
function ts_normalize_date(string $d): string
{
    $d = trim($d);
    if ($d === '') {
        return '';
    }
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $d);
        if ($dt && $dt->format($fmt) === $d) {
            return $dt->format('Y-m-d');
        }
    }
    $ts = strtotime($d);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

/**
 * يحفظ سجل تايم شيت (إنشاء أو تعديل) ضمن معاملة، مع الحسابات والتحقق والأعطال.
 * يعيد معرّف السجل على الخادم. يرمي TimesheetError عند الفشل.
 *
 * @param string|null $clientUuid لربط المزامنة (يُخزَّن مرّة عند الإنشاء)
 */
function ts_save(mysqli $conn, array $ctx, int $projectId, array $payload, ?int $id, ?string $clientUuid): int
{
    $type = intval($payload['type'] ?? 0);
    if (!in_array($type, [1, 2, 3], true)) {
        throw new TimesheetError('نوع الكشف غير صحيح (1/2/3)', 422);
    }

    $operationId = intval($payload['operator'] ?? 0);
    $equipmentId = ts_assert_operation_in_project($conn, $ctx, $projectId, $operationId);
    unset($equipmentId);

    $driver = intval($payload['driver'] ?? 0);
    if ($driver <= 0) {
        throw new TimesheetError('يجب اختيار السائق', 422);
    }

    $shift = strtoupper(trim((string)($payload['shift'] ?? '')));
    $opShift = ts_operation_shift($conn, $operationId);
    if ($shift === '' || ($shift !== 'D' && $shift !== 'N')) {
        throw new TimesheetError('الوردية غير صحيحة (D/N)', 422);
    }
    if (!in_array($shift, $opShift['allowed'], true)) {
        throw new TimesheetError('الوردية غير متاحة لهذا التشغيل', 422);
    }

    $date = ts_normalize_date((string)($payload['date'] ?? ''));
    if ($date === '') {
        throw new TimesheetError('تنسيق التاريخ غير صحيح', 422);
    }

    // بناء قيم الحقول من المدخلات (نصوص)، مع شطب المحسوبة لاحقاً.
    $r = [];
    foreach (ts_writable_fields() as $f) {
        $r[$f] = isset($payload[$f]) ? (string)$payload[$f] : '';
    }
    $r['operator'] = (string)$operationId;
    $r['driver'] = (string)$driver;
    $r['shift'] = $shift;
    $r['date'] = $date;
    $r['type'] = (string)$type;
    $r['shift_hours'] = (string)$opShift['shift_hours']; // authoritative

    // الحسابات + تحقّق الأعطال (يحوّل القيم المحسوبة).
    ts_compute($r, $type, $opShift['shift_hours']);

    // ضمان أن المحسوبات نصوص.
    foreach (['executed_hours', 'extra_hours_total', 'total_work_hours', 'total_fault_hours',
                 'operator_standby_hours', 'machine_standby_hours', 'meters_count', 'counter_diff'] as $k) {
        if (isset($r[$k])) {
            $r[$k] = (string)$r[$k];
        }
    }

    $userId = intval($ctx['id']);
    $companyId = intval($ctx['company_id']);
    $r['user_id'] = (string)$userId;

    $faultItems = [];
    if (isset($payload['fault_items']) && is_array($payload['fault_items'])) {
        $faultItems = ts_enrich_failures($conn, $type, $payload['fault_items']);
    }

    $timesheet_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
    $has_client_uuid = db_table_has_column($conn, 'timesheet', 'client_uuid');

    mysqli_begin_transaction($conn);
    try {
        if ($id && $id > 0) {
            // تحقق أن السجل ضمن المشروع قبل التحديث.
            $existing = ts_load_one($conn, $ctx, $projectId, $id, false);
            if (!$existing) {
                throw new TimesheetError('السجل غير موجود ضمن مشروعك', 404);
            }
            $whereSql = 'id = ?';
            $whereVals = [(string)$id];
            if (!$ctx['is_super'] && $timesheet_has_company) {
                $whereSql .= ' AND company_id = ?';
                $whereVals[] = (string)$companyId;
            }
            ts_db_update($conn, 'timesheet', $r, $whereSql, $whereVals);
            $savedId = $id;
        } else {
            $insert = $r;
            if (!$ctx['is_super'] && $timesheet_has_company) {
                $insert['company_id'] = (string)$companyId;
            }
            if ($has_client_uuid && $clientUuid !== null && $clientUuid !== '') {
                $insert['client_uuid'] = $clientUuid;
            }
            $savedId = ts_db_insert($conn, 'timesheet', $insert);
        }

        ts_save_failures($conn, $savedId, $operationId, $date, $type, $companyId, $userId, $faultItems);
        mysqli_commit($conn);
        return $savedId;
    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        if ($e instanceof TimesheetError) {
            throw $e;
        }
        throw new TimesheetError('تعذّر حفظ السجل: ' . $e->getMessage(), 500);
    }
}

/** يقرأ سجل تايم شيت واحداً ضمن المشروع (أو null). */
function ts_load_one(mysqli $conn, array $ctx, int $projectId, int $id, bool $withFailures = true): ?array
{
    $clause = ts_project_clause($ctx, $projectId, 'o');
    $companyClause = '';
    if (!$ctx['is_super'] && db_table_has_column($conn, 'timesheet', 'company_id')) {
        $companyClause = ' AND t.company_id = ' . intval($ctx['company_id']);
    }
    $sql = "SELECT t.*, e.code AS equipment_code, e.name AS equipment_name,
                   et.form AS type_form, et.type AS type_name,
                   d.name AS driver_name, o.id AS op_id
            FROM timesheet t
            JOIN operations o ON o.id = t.operator
            LEFT JOIN equipments e ON e.id = o.equipment
            LEFT JOIN equipments_types et ON et.id = e.type
            LEFT JOIN employees d ON d.id = t.driver
            WHERE t.id = ? AND $clause $companyClause
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    if ($withFailures) {
        $row['failures'] = ts_load_failures($conn, $id);
    }
    return $row;
}

// ═══════════════════════════════════════════════════════════════════════════
// نقاط النهاية — البيانات المرجعية والقوائم
// ═══════════════════════════════════════════════════════════════════════════

/** GET /api/timesheet/refdata — حزمة مرجعية كاملة للعمل offline. */
function timesheet_refdata(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    $project = api_fetch_project($ctx, $projectId);

    $opClause = ts_project_clause($ctx, $projectId, 'o');

    // العمليات (مع المعدة والنوع والوردية والساعات).
    $ops = [];
    $res = mysqli_query($conn, "
        SELECT o.id AS operation_id, o.equipment AS equipment_id, o.shift_type, o.shift_hours,
               e.code, e.name, e.type AS equipment_type_id,
               et.form AS type_form, et.type AS type_name
        FROM operations o
        JOIN equipments e ON e.id = o.equipment
        LEFT JOIN equipments_types et ON et.id = e.type
        WHERE o.status = 1 AND $opClause
        ORDER BY e.code ASC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $st = isset($r['shift_type']) ? strtoupper(trim((string)$r['shift_type'])) : 'B';
            if (!in_array($st, ['D', 'N', 'B'], true)) {
                $st = 'B';
            }
            $ops[] = [
                'operation_id'      => intval($r['operation_id']),
                'equipment_id'      => intval($r['equipment_id']),
                'code'              => $r['code'] ?? '',
                'name'              => $r['name'] ?? '',
                'equipment_type_id' => intval($r['equipment_type_id']),
                'type_form'         => $r['type_form'] !== null ? intval($r['type_form']) : 0,
                'type_name'         => $r['type_name'] ?? '',
                'shift_type'        => $st,
                'shift_hours'       => floatval($r['shift_hours']),
                'allowed_shifts'    => $st === 'D' ? ['D'] : ($st === 'N' ? ['N'] : ['D', 'N']),
            ];
        }
    }

    // ربط المعدات بالسائقين (لمعدات مشروعنا).
    $opEqIds = array_values(array_unique(array_map(fn($o) => $o['equipment_id'], $ops)));
    $equipmentDrivers = [];
    $drivers = [];
    if (!empty($opEqIds)) {
        $idsIn = implode(',', array_map('intval', $opEqIds));
        $res = mysqli_query($conn, "
            SELECT ed.equipment_id, ed.driver_id, ed.shift_type
            FROM equipment_drivers ed
            WHERE ed.status = 1 AND ed.equipment_id IN ($idsIn)");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $st = strtoupper(trim((string)($r['shift_type'] ?? 'B')));
                if (!in_array($st, ['D', 'N', 'B'], true)) {
                    $st = 'B';
                }
                $equipmentDrivers[] = [
                    'equipment_id' => intval($r['equipment_id']),
                    'driver_id'    => intval($r['driver_id']),
                    'shift_type'   => $st,
                ];
            }
        }
    }

    // السائقون (ضمن الشركة/المشروع).
    $drivers_has_company = db_table_has_column($conn, 'employees', 'company_id');
    $drivers_has_project = db_table_has_column($conn, 'employees', 'project_id');
    $dWhere = ['1=1'];
    if (db_table_has_column($conn, 'employees', 'status')) {
        $dWhere[] = 'd.status = 1';
    }
    if (!$ctx['is_super'] && $drivers_has_company) {
        $dWhere[] = 'd.company_id = ' . intval($ctx['company_id']);
    }
    if ($drivers_has_project && $projectId > 0) {
        $dWhere[] = "(d.project_id = $projectId OR d.project_id IS NULL)";
    }
    $res = mysqli_query($conn, 'SELECT d.id, d.name, d.phone, d.driver_code FROM employees d WHERE ' . implode(' AND ', $dWhere) . ems_operation_types_in_sql($conn, 'd') . ' ORDER BY d.name ASC');
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $drivers[] = ['id' => intval($r['id']), 'name' => $r['name'] ?? '', 'phone' => $r['phone'] ?? '', 'driver_code' => $r['driver_code'] ?? ''];
        }
    }

    // أنواع المعدات.
    $equipmentTypes = [];
    $statusClause = db_table_has_column($conn, 'equipments_types', 'status') ? " WHERE status = 'active'" : '';
    $res = mysqli_query($conn, "SELECT id, form, type FROM equipments_types$statusClause ORDER BY type ASC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $equipmentTypes[] = ['id' => intval($r['id']), 'form' => intval($r['form']), 'type' => $r['type'] ?? ''];
        }
    }

    // العقود (للمشروع).
    $contracts = [];
    if ($projectId > 0) {
        $del = db_table_has_column($conn, 'contracts', 'is_deleted') ? ' AND is_deleted = 0' : '';
        $stmt = mysqli_prepare($conn, "SELECT id, contract_signing_date FROM contracts WHERE project_id = ? AND status = 1$del ORDER BY contract_signing_date DESC");
        mysqli_stmt_bind_param($stmt, 'i', $projectId);
        mysqli_stmt_execute($stmt);
        $cres = mysqli_stmt_get_result($stmt);
        if ($cres) {
            while ($r = mysqli_fetch_assoc($cres)) {
                $contracts[] = ['id' => intval($r['id']), 'contract_signing_date' => (string)($r['contract_signing_date'] ?? '')];
            }
        }
        mysqli_stmt_close($stmt);
    }

    // شجرة أكواد الأعطال كاملة (status=1).
    $failureCodes = [];
    $res = mysqli_query($conn, "
        SELECT id, equipment_type, event_type_code, event_type_name, main_category_code, main_category_name,
               sub_category, failure_detail, full_code
        FROM failure_codes WHERE status = 1
        ORDER BY equipment_type, event_type_code, main_category_name, sub_category, failure_detail");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $failureCodes[] = [
                'id'                 => intval($r['id']),
                'equipment_type'     => intval($r['equipment_type']),
                'event_type_code'    => $r['event_type_code'],
                'event_type_name'    => $r['event_type_name'],
                'main_category_code' => $r['main_category_code'],
                'main_category_name' => $r['main_category_name'],
                'sub_category'       => $r['sub_category'],
                'failure_detail'     => $r['failure_detail'],
                'full_code'          => $r['full_code'],
            ];
        }
    }

    api_ok([
        'server_time'       => date('Y-m-d H:i:s'),
        'project'           => api_format_project($project),
        'operations'        => $ops,
        'equipment_drivers' => $equipmentDrivers,
        'drivers'           => $drivers,
        'equipment_types'   => $equipmentTypes,
        'contracts'         => $contracts,
        'failure_codes'     => $failureCodes,
    ], 'تم جلب البيانات المرجعية');
}

/** GET /api/operations/by-type?type=&shift= */
function timesheet_operations_by_type(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $type = api_str('type');
    if (!in_array($type, ['1', '2', '3'], true)) {
        api_fail('نوع الآلية غير صحيح (1/2/3)', 422);
    }
    $shift = strtoupper(api_str('shift'));

    $opClause = ts_project_clause($ctx, $projectId, 'o');
    $typeInt = intval($type);
    $typeFilter = " AND e.type IN (SELECT id FROM equipments_types WHERE form = $typeInt AND status = 'active')";
    $shiftFilter = '';
    if ($shift === 'D' || $shift === 'N') {
        $shiftFilter = " AND (o.shift_type = 'B' OR o.shift_type = '" . mysqli_real_escape_string($conn, $shift) . "')";
    }

    $out = [];
    $res = mysqli_query($conn, "
        SELECT o.id AS operation_id, e.code, e.name
        FROM operations o JOIN equipments e ON o.equipment = e.id
        WHERE o.status = '1' AND $opClause $typeFilter $shiftFilter
        ORDER BY e.code ASC, e.name ASC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = ['operation_id' => intval($r['operation_id']), 'code' => $r['code'] ?? '', 'name' => $r['name'] ?? ''];
        }
    }
    api_ok(['operations' => $out], 'تم جلب الآليات');
}

/** GET /api/operations/{id}/drivers?shift= */
function timesheet_operation_drivers(int $operationId): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $equipmentId = 0;
    try {
        $equipmentId = ts_assert_operation_in_project($conn, $ctx, $projectId, $operationId);
    } catch (TimesheetError $e) {
        api_fail($e->getMessage(), $e->getCode() ?: 404);
    }

    $shift = strtoupper(api_str('shift'));
    $shiftFilter = '';
    if ($shift === 'D' || $shift === 'N') {
        $shiftFilter = " AND (ed.shift_type = 'B' OR ed.shift_type = '" . mysqli_real_escape_string($conn, $shift) . "')";
    }
    $driverStatus = db_table_has_column($conn, 'employees', 'status') ? ' AND d.status = 1' : '';
    $driverCompany = (!$ctx['is_super'] && db_table_has_column($conn, 'employees', 'company_id'))
        ? ' AND d.company_id = ' . intval($ctx['company_id']) : '';

    $out = [];
    $stmt = mysqli_prepare($conn, "
        SELECT d.id, d.name, d.phone
        FROM equipment_drivers ed JOIN employees d ON ed.driver_id = d.id
        WHERE ed.equipment_id = ? AND ed.status = 1 $shiftFilter $driverStatus $driverCompany
        ORDER BY d.name ASC");
    mysqli_stmt_bind_param($stmt, 'i', $equipmentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = ['driver_id' => intval($r['id']), 'name' => $r['name'] ?? '', 'phone' => $r['phone'] ?? ''];
        }
    }
    mysqli_stmt_close($stmt);
    api_ok(['drivers' => $out], 'تم جلب السائقين');
}

/** GET /api/operations/{id}/contract-hours */
function timesheet_operation_contract_hours(int $operationId): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    try {
        ts_assert_operation_in_project($conn, $ctx, $projectId, $operationId);
    } catch (TimesheetError $e) {
        api_fail($e->getMessage(), $e->getCode() ?: 404);
    }
    $s = ts_operation_shift($conn, $operationId);
    api_ok([
        'shift_hours'    => $s['shift_hours'],
        'shift_type'     => $s['shift_type'],
        'allowed_shifts' => $s['allowed'],
    ], 'تم جلب ساعات العقد');
}

/** GET /api/failure-codes[?equipment_type=] */
function timesheet_failure_codes(): void
{
    global $conn;
    api_require_auth();
    $type = api_int('equipment_type', 0);
    $where = 'status = 1';
    if (in_array($type, [1, 2, 3], true)) {
        $where .= ' AND equipment_type = ' . $type;
    }
    $out = [];
    $res = mysqli_query($conn, "
        SELECT id, equipment_type, event_type_code, event_type_name, main_category_code, main_category_name,
               sub_category, failure_detail, full_code
        FROM failure_codes WHERE $where
        ORDER BY equipment_type, event_type_code, main_category_name, sub_category, failure_detail");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = [
                'id'                 => intval($r['id']),
                'equipment_type'     => intval($r['equipment_type']),
                'event_type_code'    => $r['event_type_code'],
                'event_type_name'    => $r['event_type_name'],
                'main_category_code' => $r['main_category_code'],
                'main_category_name' => $r['main_category_name'],
                'sub_category'       => $r['sub_category'],
                'failure_detail'     => $r['failure_detail'],
                'full_code'          => $r['full_code'],
            ];
        }
    }
    api_ok(['failure_codes' => $out], 'تم جلب أكواد الأعطال');
}

// ═══════════════════════════════════════════════════════════════════════════
// نقاط النهاية — سجلات التايم شيت (CRUD)
// ═══════════════════════════════════════════════════════════════════════════

/** تنسيق صف تايم شيت للإخراج. */
function ts_format_row(array $row): array
{
    $numeric = ['shift_hours', 'executed_hours', 'bucket_hours', 'jackhammer_hours', 'extra_hours',
        'extra_hours_total', 'standby_hours', 'dependence_hours', 'total_work_hours',
        'hr_fault', 'maintenance_fault', 'marketing_fault', 'approval_fault', 'other_fault_hours',
        'total_fault_hours', 'operator_hours', 'machine_standby_hours', 'jackhammer_standby_hours',
        'bucket_standby_hours', 'extra_operator_hours', 'operator_standby_hours',
        'tons_count', 'trips_count', 'meters_count', 'drilling_holes_count', 'drilling_depth', 'counter_diff'];
    $out = [
        'id'             => intval($row['id']),
        'operation_id'   => intval($row['operator']),
        'driver_id'      => intval($row['driver']),
        'driver_name'    => $row['driver_name'] ?? '',
        'equipment_code' => $row['equipment_code'] ?? '',
        'equipment_name' => $row['equipment_name'] ?? '',
        'type'           => intval($row['type']),
        'type_name'      => $row['type_name'] ?? '',
        'shift'          => $row['shift'] ?? '',
        'date'           => (string)($row['date'] ?? ''),
        'status'         => intval($row['status'] ?? 1),
        'work_notes'     => $row['work_notes'] ?? '',
        'fault_notes'    => $row['fault_notes'] ?? '',
        'general_notes'  => $row['general_notes'] ?? '',
        'operator_notes' => $row['operator_notes'] ?? '',
        'transport_type' => $row['transport_type'] ?? '',
        'meters_type'    => $row['meters_type'] ?? '',
        'start_hours'    => intval($row['start_hours'] ?? 0),
        'start_minutes'  => intval($row['start_minutes'] ?? 0),
        'start_seconds'  => intval($row['start_seconds'] ?? 0),
        'end_hours'      => intval($row['end_hours'] ?? 0),
        'end_minutes'    => intval($row['end_minutes'] ?? 0),
        'end_seconds'    => intval($row['end_seconds'] ?? 0),
        'client_uuid'    => $row['client_uuid'] ?? null,
        'updated_at'     => (string)($row['updated_at'] ?? ''),
    ];
    foreach ($numeric as $k) {
        $out[$k] = isset($row[$k]) ? floatval($row[$k]) : 0.0;
    }
    if (isset($row['failures'])) {
        $out['failures'] = $row['failures'];
    }
    return $out;
}

/** GET /api/timesheets (+filters) */
function timesheets_list(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $opClause = ts_project_clause($ctx, $projectId, 'o');
    $where = [$opClause];
    if (!$ctx['is_super'] && db_table_has_column($conn, 'timesheet', 'company_id')) {
        $where[] = 't.company_id = ' . intval($ctx['company_id']);
    }

    // الفلاتر.
    $type = api_int('type', 0);
    if (in_array($type, [1, 2, 3], true)) {
        $where[] = 't.type = ' . $type;
    }
    $opId = api_int('operation_id', 0);
    if ($opId > 0) {
        $where[] = 't.operator = ' . $opId;
    }
    $driverId = api_int('driver_id', 0);
    if ($driverId > 0) {
        $where[] = 't.driver = ' . $driverId;
    }
    $shift = strtoupper(api_str('shift'));
    if ($shift === 'D' || $shift === 'N') {
        $where[] = "t.shift = '" . mysqli_real_escape_string($conn, $shift) . "'";
    }
    $status = api_int('status', 0);
    if (in_array($status, [1, 2, 3], true)) {
        $where[] = 't.status = ' . $status;
    }
    $date = api_str('date');
    $startDate = api_str('start_date');
    $endDate = api_str('end_date');
    $month = api_str('month');
    if ($date !== '' && ts_normalize_date($date) !== '') {
        $where[] = "t.date = '" . mysqli_real_escape_string($conn, ts_normalize_date($date)) . "'";
    } elseif ($startDate !== '' || $endDate !== '') {
        if ($startDate !== '' && ts_normalize_date($startDate) !== '') {
            $where[] = "t.date >= '" . mysqli_real_escape_string($conn, ts_normalize_date($startDate)) . "'";
        }
        if ($endDate !== '' && ts_normalize_date($endDate) !== '') {
            $where[] = "t.date <= '" . mysqli_real_escape_string($conn, ts_normalize_date($endDate)) . "'";
        }
    } elseif ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $where[] = "t.date LIKE '" . mysqli_real_escape_string($conn, $month) . "-%'";
    }

    $whereSql = implode(' AND ', $where);

    // الإحصائيات.
    $statsRes = mysqli_query($conn, "
        SELECT IFNULL(SUM(t.executed_hours),0) AS executed_sum,
               IFNULL(SUM(t.standby_hours),0) AS standby_sum,
               IFNULL(SUM(t.total_fault_hours),0) AS fault_sum,
               IFNULL(SUM(t.executed_hours + t.standby_hours),0) AS work_sum
        FROM timesheet t JOIN operations o ON o.id = t.operator
        WHERE $whereSql");
    $stats = $statsRes ? mysqli_fetch_assoc($statsRes) : null;

    // السجلات.
    $rows = [];
    $res = mysqli_query($conn, "
        SELECT t.*, e.code AS equipment_code, e.name AS equipment_name,
               et.type AS type_name, d.name AS driver_name
        FROM timesheet t
        JOIN operations o ON o.id = t.operator
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN equipments_types et ON et.id = e.type
        LEFT JOIN employees d ON d.id = t.driver
        WHERE $whereSql
        ORDER BY t.date DESC, t.id DESC
        LIMIT 500");
    if ($res) {
        $ids = [];
        $tmp = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $tmp[] = $r;
            $ids[] = intval($r['id']);
        }
        // عدّ الأعطال لكل سجل.
        $faultCounts = [];
        if (!empty($ids) && db_table_has_column($conn, 'timesheet_failure_hours', 'id')) {
            $idsIn = implode(',', $ids);
            $fcRes = mysqli_query($conn, "SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_failure_hours WHERE timesheet_id IN ($idsIn) AND status = 1 GROUP BY timesheet_id");
            if ($fcRes) {
                while ($fr = mysqli_fetch_assoc($fcRes)) {
                    $faultCounts[intval($fr['timesheet_id'])] = intval($fr['cnt']);
                }
            }
        }
        foreach ($tmp as $r) {
            $fmt = ts_format_row($r);
            $fmt['fault_count'] = $faultCounts[intval($r['id'])] ?? 0;
            $rows[] = $fmt;
        }
    }

    api_ok([
        'stats' => [
            'executed' => round(floatval($stats['executed_sum'] ?? 0), 2),
            'standby'  => round(floatval($stats['standby_sum'] ?? 0), 2),
            'faults'   => round(floatval($stats['fault_sum'] ?? 0), 2),
            'total_work' => round(floatval($stats['work_sum'] ?? 0), 2),
        ],
        'count'      => count($rows),
        'timesheets' => $rows,
    ], 'تم جلب السجلات');
}

/** GET /api/timesheets/{id} */
function timesheets_get(int $id): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $row = ts_load_one($conn, $ctx, $projectId, $id, true);
    if (!$row) {
        api_fail('السجل غير موجود ضمن مشروعك', 404);
    }
    api_ok(['timesheet' => ts_format_row($row)], 'تم جلب السجل');
}

/** POST /api/timesheets */
function timesheets_create(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $payload = api_input();
    $clientUuid = isset($payload['client_uuid']) ? (string)$payload['client_uuid'] : null;
    try {
        $id = ts_save($conn, $ctx, $projectId, $payload, null, $clientUuid);
    } catch (TimesheetError $e) {
        api_fail($e->getMessage(), $e->getCode() ?: 422);
    }
    $row = ts_load_one($conn, $ctx, $projectId, $id, true);
    api_ok(['timesheet' => $row ? ts_format_row($row) : ['id' => $id]], 'تم حفظ السجل ✅', 201);
}

/** PUT /api/timesheets/{id} */
function timesheets_update(int $id): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    if ($id <= 0) {
        api_fail('معرّف السجل غير صحيح', 400);
    }
    $payload = api_input();
    try {
        $saved = ts_save($conn, $ctx, $projectId, $payload, $id, null);
    } catch (TimesheetError $e) {
        api_fail($e->getMessage(), $e->getCode() ?: 422);
    }
    $row = ts_load_one($conn, $ctx, $projectId, $saved, true);
    api_ok(['timesheet' => $row ? ts_format_row($row) : ['id' => $saved]], 'تم تحديث السجل ✅');
}

/** DELETE /api/timesheets/{id} */
function timesheets_delete(int $id): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    if ($id <= 0) {
        api_fail('معرّف السجل غير صحيح', 400);
    }
    // تأكد من ملكية المشروع.
    $row = ts_load_one($conn, $ctx, $projectId, $id, false);
    if (!$row) {
        api_fail('السجل غير موجود ضمن مشروعك', 404);
    }
    mysqli_begin_transaction($conn);
    try {
        if (db_table_has_column($conn, 'timesheet_failure_hours', 'id')) {
            $d1 = mysqli_prepare($conn, 'DELETE FROM timesheet_failure_hours WHERE timesheet_id = ?');
            mysqli_stmt_bind_param($d1, 'i', $id);
            mysqli_stmt_execute($d1);
            mysqli_stmt_close($d1);
        }
        $d2 = mysqli_prepare($conn, 'DELETE FROM timesheet WHERE id = ?');
        mysqli_stmt_bind_param($d2, 'i', $id);
        mysqli_stmt_execute($d2);
        mysqli_stmt_close($d2);
        mysqli_commit($conn);
    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        api_fail('تعذّر حذف السجل', 500);
    }
    api_ok(['id' => $id], 'تم حذف السجل ✅');
}

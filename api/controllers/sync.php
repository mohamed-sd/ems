<?php
/**
 * api/controllers/sync.php — مزامنة التايم شيت (Offline-First).
 *   POST /api/sync/timesheets  — رفع دفعي للتغييرات المعلّقة (idempotent عبر client_uuid)
 *   GET  /api/sync/pull?updated_since=  — سحب تزايدي لسجلات المشروع
 *
 * سياسة التعارض: «الأحدث يفوز» مع وضع علامة conflict — إن كان السجل على الخادم
 * أحدث من نسخة الجهاز (updated_at > client_updated_at) يُرفض الكتابة وتُعاد نسخة
 * الخادم بحالة conflict ليحلّها العميل؛ غير ذلك يُطبّق التعديل (applied).
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** يبحث عن سجل بالـ client_uuid ضمن نطاق المستخدم؛ يعيد id أو 0. */
function sync_find_by_uuid(mysqli $conn, array $ctx, int $projectId, string $uuid): int
{
    if ($uuid === '' || !db_table_has_column($conn, 'timesheet', 'client_uuid')) {
        return 0;
    }
    $companyClause = (!$ctx['is_super'] && db_table_has_column($conn, 'timesheet', 'company_id'))
        ? ' AND t.company_id = ' . intval($ctx['company_id']) : '';
    $clause = ts_project_clause($ctx, $projectId, 'o');
    $stmt = mysqli_prepare(
        $conn,
        "SELECT t.id FROM timesheet t JOIN operations o ON o.id = t.operator
         WHERE t.client_uuid = ? AND $clause $companyClause LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $uuid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? intval($row['id']) : 0;
}

/** يعيد updated_at لسجل (نص) أو ''. */
function sync_updated_at(mysqli $conn, int $id): string
{
    if (!db_table_has_column($conn, 'timesheet', 'updated_at')) {
        return '';
    }
    $stmt = mysqli_prepare($conn, 'SELECT updated_at FROM timesheet WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (string)$row['updated_at'] : '';
}

/** POST /api/sync/timesheets — رفع دفعي. */
function sync_push(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $input = api_input();
    $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
    if (empty($items)) {
        api_fail('لا توجد عناصر للمزامنة', 422);
    }

    $results = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $op = strtolower(trim((string)($item['op'] ?? '')));
        $clientUuid = (string)($item['client_uuid'] ?? '');
        $payload = isset($item['payload']) && is_array($item['payload']) ? $item['payload'] : [];
        $clientUpdatedAt = (string)($item['client_updated_at'] ?? '');

        $result = ['client_uuid' => $clientUuid, 'status' => 'error', 'server_id' => null, 'message' => ''];

        try {
            if ($clientUuid === '') {
                throw new TimesheetError('client_uuid مفقود', 422);
            }

            if ($op === 'create') {
                // idempotency: إن وُجد client_uuid مسبقاً فلا تُكرّر.
                $existing = sync_find_by_uuid($conn, $ctx, $projectId, $clientUuid);
                if ($existing > 0) {
                    $result['status'] = 'applied';
                    $result['server_id'] = $existing;
                    $result['message'] = 'موجود مسبقاً (لم يُكرّر)';
                } else {
                    $id = ts_save($conn, $ctx, $projectId, $payload, null, $clientUuid);
                    $result['status'] = 'applied';
                    $result['server_id'] = $id;
                    $result['message'] = 'تم الإنشاء';
                }
            } elseif ($op === 'update') {
                $serverId = intval($payload['id'] ?? 0);
                if ($serverId <= 0) {
                    $serverId = sync_find_by_uuid($conn, $ctx, $projectId, $clientUuid);
                }
                if ($serverId <= 0) {
                    // لم يُرفع بعد → أنشئه (idempotent عبر uuid).
                    $id = ts_save($conn, $ctx, $projectId, $payload, null, $clientUuid);
                    $result['status'] = 'applied';
                    $result['server_id'] = $id;
                    $result['message'] = 'أُنشئ (لم يكن مرفوعاً)';
                } else {
                    // تحقّق التعارض.
                    $serverUpdated = sync_updated_at($conn, $serverId);
                    if ($serverUpdated !== '' && $clientUpdatedAt !== ''
                        && strtotime($serverUpdated) > strtotime($clientUpdatedAt)) {
                        $serverRow = ts_load_one($conn, $ctx, $projectId, $serverId, true);
                        $result['status'] = 'conflict';
                        $result['server_id'] = $serverId;
                        $result['message'] = 'نسخة الخادم أحدث — يلزم حلّ التعارض';
                        $result['server_record'] = $serverRow ? ts_format_row($serverRow) : null;
                    } else {
                        $id = ts_save($conn, $ctx, $projectId, $payload, $serverId, null);
                        $result['status'] = 'applied';
                        $result['server_id'] = $id;
                        $result['message'] = 'تم التحديث';
                    }
                }
            } elseif ($op === 'delete') {
                $serverId = intval($payload['id'] ?? 0);
                if ($serverId <= 0) {
                    $serverId = sync_find_by_uuid($conn, $ctx, $projectId, $clientUuid);
                }
                if ($serverId <= 0) {
                    $result['status'] = 'applied';
                    $result['message'] = 'غير موجود (يُعدّ محذوفاً)';
                } else {
                    $exists = ts_load_one($conn, $ctx, $projectId, $serverId, false);
                    if ($exists) {
                        mysqli_begin_transaction($conn);
                        try {
                            if (db_table_has_column($conn, 'timesheet_failure_hours', 'id')) {
                                $d1 = mysqli_prepare($conn, 'DELETE FROM timesheet_failure_hours WHERE timesheet_id = ?');
                                mysqli_stmt_bind_param($d1, 'i', $serverId);
                                mysqli_stmt_execute($d1);
                                mysqli_stmt_close($d1);
                            }
                            $d2 = mysqli_prepare($conn, 'DELETE FROM timesheet WHERE id = ?');
                            mysqli_stmt_bind_param($d2, 'i', $serverId);
                            mysqli_stmt_execute($d2);
                            mysqli_stmt_close($d2);
                            mysqli_commit($conn);
                        } catch (\Throwable $e) {
                            mysqli_rollback($conn);
                            throw new TimesheetError('تعذّر الحذف', 500);
                        }
                    }
                    $result['status'] = 'applied';
                    $result['server_id'] = $serverId;
                    $result['message'] = 'تم الحذف';
                }
            } else {
                throw new TimesheetError('عملية غير معروفة: ' . $op, 422);
            }
        } catch (TimesheetError $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['message'] = 'خطأ غير متوقّع';
        }

        $results[] = $result;
    }

    api_ok([
        'server_time' => date('Y-m-d H:i:s'),
        'results'     => $results,
    ], 'تمت معالجة دفعة المزامنة');
}

/** GET /api/sync/pull?updated_since= — سحب تزايدي لسجلات المشروع. */
function sync_pull(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $updatedSince = api_str('updated_since');
    $clause = ts_project_clause($ctx, $projectId, 'o');
    $where = [$clause];
    if (!$ctx['is_super'] && db_table_has_column($conn, 'timesheet', 'company_id')) {
        $where[] = 't.company_id = ' . intval($ctx['company_id']);
    }
    $hasUpdated = db_table_has_column($conn, 'timesheet', 'updated_at');
    if ($hasUpdated && $updatedSince !== '') {
        $where[] = "t.updated_at > '" . mysqli_real_escape_string($conn, $updatedSince) . "'";
    }
    $whereSql = implode(' AND ', $where);

    $rows = [];
    $res = mysqli_query($conn, "
        SELECT t.*, e.code AS equipment_code, e.name AS equipment_name,
               et.type AS type_name, d.name AS driver_name
        FROM timesheet t
        JOIN operations o ON o.id = t.operator
        LEFT JOIN equipments e ON e.id = o.equipment
        LEFT JOIN equipments_types et ON et.id = e.type
        LEFT JOIN employees d ON d.id = t.employee_id
        WHERE $whereSql
        ORDER BY t.id DESC
        LIMIT 1000");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $r['failures'] = ts_load_failures($conn, intval($r['id']));
            $rows[] = ts_format_row($r);
        }
    }

    api_ok([
        'server_time' => date('Y-m-d H:i:s'),
        'timesheets'  => $rows,
        'count'       => count($rows),
    ], 'تم سحب التغييرات');
}

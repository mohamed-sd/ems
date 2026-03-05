<?php
if (!defined('APPROVAL_WORKFLOW_INCLUDED')) {
    define('APPROVAL_WORKFLOW_INCLUDED', true);
}

if (!function_exists('approval_now')) {
    function approval_now() {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('approval_response')) {
    function approval_response($success, $message, $extra = []) {
        return array_merge([
            'success' => (bool)$success,
            'message' => $message
        ], $extra);
    }
}

if (!function_exists('approval_get_user_id')) {
    function approval_get_user_id() {
        return isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    }
}

if (!function_exists('approval_get_user_role')) {
    function approval_get_user_role() {
        return isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    }
}

if (!function_exists('approval_user_can_match_role')) {
    function approval_user_can_match_role($role_required, $user_role) {
        $roles = array_map('trim', explode(',', strval($role_required)));
        $roles = array_filter($roles, function($r) { return $r !== ''; });

        if ($user_role === '-1') {
            return true;
        }

        return in_array(strval($user_role), $roles, true);
    }
}

if (!function_exists('approval_db_begin')) {
    function approval_db_begin($conn) {
        if (function_exists('mysqli_begin_transaction')) {
            return mysqli_begin_transaction($conn);
        }
        return mysqli_query($conn, 'START TRANSACTION');
    }
}

if (!function_exists('approval_db_commit')) {
    function approval_db_commit($conn) {
        return mysqli_commit($conn);
    }
}

if (!function_exists('approval_db_rollback')) {
    function approval_db_rollback($conn) {
        return mysqli_rollback($conn);
    }
}

if (!function_exists('approval_valid_identifier')) {
    function approval_valid_identifier($value) {
        return preg_match('/^[a-zA-Z0-9_]+$/', $value) === 1;
    }
}

if (!function_exists('approval_bind_types_from_values')) {
    function approval_bind_types_from_values($values) {
        $types = '';
        foreach ($values as $v) {
            if (is_int($v)) {
                $types .= 'i';
            } elseif (is_float($v)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}

if (!function_exists('approval_stmt_execute')) {
    function approval_stmt_execute($conn, $sql, $values = []) {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        if (!empty($values)) {
            $types = approval_bind_types_from_values($values);
            $refs = [];
            $bindParams = [];
            $bindParams[] = $types;

            foreach ($values as $key => $val) {
                $refs[$key] = $val;
                $bindParams[] = &$refs[$key];
            }

            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        return $stmt;
    }
}

if (!function_exists('approval_get_workflow_rules')) {
    function approval_get_workflow_rules($entity_type, $action, $conn) {
        $entity_type = mysqli_real_escape_string($conn, $entity_type);
        $action = mysqli_real_escape_string($conn, $action);

        $sql = "SELECT role_required, step_order
                FROM approval_workflow_rules
                WHERE entity_type = '$entity_type'
                  AND action = '$action'
                  AND is_active = 1
                ORDER BY step_order ASC";

        $result = mysqli_query($conn, $sql);
        $rules = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rules[] = [
                    'role_required' => $row['role_required'],
                    'step_order' => intval($row['step_order'])
                ];
            }
        }

        if (!empty($rules)) {
            return $rules;
        }

        $fallback_map = [
            'equipment:deactivate_equipment' => '4,-1',
            'equipment:reactivate_equipment' => '4,-1',
            'driver:activate_driver' => '3,-1',
            'driver:deactivate_driver' => '3,-1',
            'driver:reactivate_driver' => '3,-1'
        ];

        $lookup_key = trim($entity_type) . ':' . trim($action);
        if (isset($fallback_map[$lookup_key])) {
            return [
                ['role_required' => $fallback_map[$lookup_key], 'step_order' => 1]
            ];
        }

        return [
            ['role_required' => '-1', 'step_order' => 1]
        ];
    }
}

if (!function_exists('approval_get_request_by_id')) {
    function approval_get_request_by_id($request_id, $conn) {
        $request_id = intval($request_id);
        $sql = "SELECT * FROM approval_requests WHERE id = $request_id LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('approval_get_next_pending_step')) {
    function approval_get_next_pending_step($request_id, $conn) {
        $request_id = intval($request_id);
        $sql = "SELECT * FROM approval_steps
                WHERE request_id = $request_id AND status = 'pending'
                ORDER BY step_order ASC LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('approval_are_all_steps_approved')) {
    function approval_are_all_steps_approved($request_id, $conn) {
        $request_id = intval($request_id);
        $sql = "SELECT COUNT(*) AS cnt FROM approval_steps WHERE request_id = $request_id AND status <> 'approved'";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        return intval($row['cnt']) === 0;
    }
}

if (!function_exists('approval_execute_db_operation')) {
    function approval_execute_db_operation($operation, $conn) {
        $db_action = isset($operation['db_action']) ? $operation['db_action'] : '';
        $table = isset($operation['table']) ? $operation['table'] : '';

        if (!approval_valid_identifier($table)) {
            return approval_response(false, 'اسم الجدول غير صالح');
        }

        if ($db_action === 'update') {
            $data = isset($operation['data']) && is_array($operation['data']) ? $operation['data'] : [];
            $where = isset($operation['where']) && is_array($operation['where']) ? $operation['where'] : [];

            if (empty($data) || empty($where)) {
                return approval_response(false, 'بيانات تحديث غير مكتملة');
            }

            $set_parts = [];
            $where_parts = [];
            $values = [];

            foreach ($data as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم عمود غير صالح في update');
                }
                $set_parts[] = "$key = ?";
                $values[] = $value;
            }

            foreach ($where as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم شرط غير صالح في update');
                }
                $where_parts[] = "$key = ?";
                $values[] = $value;
            }

            $sql = "UPDATE $table SET " . implode(', ', $set_parts) . " WHERE " . implode(' AND ', $where_parts);
            $stmt = approval_stmt_execute($conn, $sql, $values);

            if (!$stmt) {
                return approval_response(false, 'فشل تنفيذ update: ' . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
            return approval_response(true, 'تم تنفيذ التحديث');
        }

        if ($db_action === 'insert') {
            $data = isset($operation['data']) && is_array($operation['data']) ? $operation['data'] : [];
            if (empty($data)) {
                return approval_response(false, 'بيانات الإدراج غير مكتملة');
            }

            $columns = [];
            $placeholders = [];
            $values = [];

            foreach ($data as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم عمود غير صالح في insert');
                }
                $columns[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }

            $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = approval_stmt_execute($conn, $sql, $values);
            if (!$stmt) {
                return approval_response(false, 'فشل تنفيذ insert: ' . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
            return approval_response(true, 'تم تنفيذ الإدراج');
        }

        if ($db_action === 'delete') {
            $where = isset($operation['where']) && is_array($operation['where']) ? $operation['where'] : [];
            if (empty($where)) {
                return approval_response(false, 'شروط الحذف غير مكتملة');
            }

            $where_parts = [];
            $values = [];

            foreach ($where as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم شرط غير صالح في delete');
                }
                $where_parts[] = "$key = ?";
                $values[] = $value;
            }

            $sql = "DELETE FROM $table WHERE " . implode(' AND ', $where_parts);
            $stmt = approval_stmt_execute($conn, $sql, $values);
            if (!$stmt) {
                return approval_response(false, 'فشل تنفيذ delete: ' . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
            return approval_response(true, 'تم تنفيذ الحذف');
        }

        return approval_response(false, 'نوع عملية قاعدة البيانات غير مدعوم');
    }
}

if (!function_exists('approval_execute_payload')) {
    function approval_execute_payload($request, $conn) {
        $payload = json_decode($request['payload'], true);
        if (!is_array($payload)) {
            return approval_response(false, 'payload غير صالح');
        }

        $operations = isset($payload['operations']) && is_array($payload['operations']) ? $payload['operations'] : [];
        if (empty($operations)) {
            return approval_response(false, 'لا توجد عمليات لتنفيذها');
        }

        foreach ($operations as $operation) {
            $result = approval_execute_db_operation($operation, $conn);
            if (empty($result['success'])) {
                return $result;
            }
        }

        return approval_response(true, 'تم تنفيذ الطلب النهائي بنجاح');
    }
}

if (!function_exists('approval_finalize_if_completed')) {
    function approval_finalize_if_completed($request_id, $conn) {
        $request = approval_get_request_by_id($request_id, $conn);
        if (!$request) {
            return approval_response(false, 'طلب الموافقة غير موجود');
        }

        if ($request['status'] !== 'pending') {
            return approval_response(false, 'الطلب ليس في حالة انتظار');
        }

        if (!approval_are_all_steps_approved($request_id, $conn)) {
            $next = approval_get_next_pending_step($request_id, $conn);
            $next_order = $next ? intval($next['step_order']) : null;
            mysqli_query($conn, "UPDATE approval_requests SET current_step = " . ($next_order === null ? 'NULL' : $next_order) . ", updated_at = NOW() WHERE id = " . intval($request_id));
            return approval_response(true, 'لا تزال هناك مراحل معلقة');
        }

        $executeResult = approval_execute_payload($request, $conn);
        if (empty($executeResult['success'])) {
            return $executeResult;
        }

        $request_id = intval($request_id);
        $sql = "UPDATE approval_requests
                SET status = 'approved',
                    current_step = NULL,
                    approved_at = NOW(),
                    executed_at = NOW(),
                    updated_at = NOW()
                WHERE id = $request_id";

        if (!mysqli_query($conn, $sql)) {
            return approval_response(false, 'تم تنفيذ البيانات ولكن فشل تحديث حالة الطلب: ' . mysqli_error($conn));
        }

        return approval_response(true, 'تم اعتماد وتنفيذ الطلب بنجاح');
    }
}

if (!function_exists('approval_create_request')) {
    function approval_create_request($entity_type, $entity_id, $action, $payload, $requested_by, $conn) {
        $entity_type = trim($entity_type);
        $entity_id = intval($entity_id);
        $action = trim($action);
        $requested_by = intval($requested_by);

        if ($entity_type === '' || $entity_id <= 0 || $action === '' || $requested_by <= 0) {
            return approval_response(false, 'بيانات طلب الموافقة غير مكتملة');
        }

        if (!is_array($payload)) {
            return approval_response(false, 'payload يجب أن يكون مصفوفة');
        }

        $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($payload_json === false) {
            return approval_response(false, 'فشل تحويل payload إلى JSON');
        }

        approval_db_begin($conn);

        try {
            $entity_type_esc = mysqli_real_escape_string($conn, $entity_type);
            $action_esc = mysqli_real_escape_string($conn, $action);

            $dupSql = "SELECT id FROM approval_requests
                       WHERE entity_type = '$entity_type_esc'
                         AND entity_id = $entity_id
                         AND action = '$action_esc'
                         AND status = 'pending'
                       LIMIT 1";
            $dupResult = mysqli_query($conn, $dupSql);
            if ($dupResult && mysqli_num_rows($dupResult) > 0) {
                $dupRow = mysqli_fetch_assoc($dupResult);
                approval_db_rollback($conn);
                return approval_response(false, 'يوجد طلب موافقة معلق مسبقاً لنفس العملية', [
                    'request_id' => intval($dupRow['id'])
                ]);
            }

            $insertSql = "INSERT INTO approval_requests (entity_type, entity_id, action, payload, requested_by, current_step, status, created_at)
                          VALUES (?, ?, ?, ?, ?, 1, 'pending', NOW())";
            $stmt = approval_stmt_execute($conn, $insertSql, [$entity_type, $entity_id, $action, $payload_json, $requested_by]);
            if (!$stmt) {
                throw new Exception('فشل إنشاء طلب الموافقة: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            $request_id = intval(mysqli_insert_id($conn));
            $rules = approval_get_workflow_rules($entity_type, $action, $conn);

            if (empty($rules)) {
                throw new Exception('لا توجد مراحل موافقة معرفة لهذا النوع من العمليات');
            }

            foreach ($rules as $rule) {
                $step_order = intval($rule['step_order']);
                $role_required = strval($rule['role_required']);

                $stepSql = "INSERT INTO approval_steps (request_id, role_required, step_order, status, created_at)
                            VALUES (?, ?, ?, 'pending', NOW())";
                $stepStmt = approval_stmt_execute($conn, $stepSql, [$request_id, $role_required, $step_order]);
                if (!$stepStmt) {
                    throw new Exception('فشل إنشاء مراحل الموافقة: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($stepStmt);
            }

            $requester_role = approval_get_user_role();
            $firstStep = approval_get_next_pending_step($request_id, $conn);

            if ($firstStep && approval_user_can_match_role($firstStep['role_required'], $requester_role)) {
                $step_id = intval($firstStep['id']);
                $autoSql = "UPDATE approval_steps
                            SET status = 'approved', approved_by = $requested_by, approved_at = NOW(), note = 'اعتماد تلقائي (منشئ الطلب يملك صلاحية المرحلة)'
                            WHERE id = $step_id";

                if (!mysqli_query($conn, $autoSql)) {
                    throw new Exception('فشل الاعتماد التلقائي للمرحلة الأولى');
                }
            }

            $finalizeResult = approval_finalize_if_completed($request_id, $conn);
            if (empty($finalizeResult['success'])) {
                throw new Exception($finalizeResult['message']);
            }

            $requestAfter = approval_get_request_by_id($request_id, $conn);
            $status = $requestAfter ? $requestAfter['status'] : 'pending';

            approval_db_commit($conn);

            return approval_response(true, $status === 'approved' ? 'تم اعتماد الطلب وتنفيذه مباشرة' : 'تم إرسال الطلب للموافقة', [
                'request_id' => $request_id,
                'status' => $status
            ]);
        } catch (Exception $ex) {
            approval_db_rollback($conn);
            return approval_response(false, $ex->getMessage());
        }
    }
}

if (!function_exists('approval_approve_request')) {
    function approval_approve_request($request_id, $approved_by, $conn, $note = '') {
        $request_id = intval($request_id);
        $approved_by = intval($approved_by);

        if ($request_id <= 0 || $approved_by <= 0) {
            return approval_response(false, 'بيانات الاعتماد غير صحيحة');
        }

        approval_db_begin($conn);

        try {
            $request = approval_get_request_by_id($request_id, $conn);
            if (!$request) {
                throw new Exception('طلب الموافقة غير موجود');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('لا يمكن اعتماد طلب غير معلق');
            }

            $step = approval_get_next_pending_step($request_id, $conn);
            if (!$step) {
                throw new Exception('لا توجد مرحلة معلقة للاعتماد');
            }

            $user_role = approval_get_user_role();
            if (!approval_user_can_match_role($step['role_required'], $user_role)) {
                throw new Exception('ليس لديك صلاحية لاعتماد هذه المرحلة');
            }

            $step_id = intval($step['id']);
            $note_esc = mysqli_real_escape_string($conn, $note);

            $sql = "UPDATE approval_steps
                    SET status = 'approved', approved_by = $approved_by, approved_at = NOW(), note = '$note_esc'
                    WHERE id = $step_id";

            if (!mysqli_query($conn, $sql)) {
                throw new Exception('فشل تحديث مرحلة الموافقة: ' . mysqli_error($conn));
            }

            $finalizeResult = approval_finalize_if_completed($request_id, $conn);
            if (empty($finalizeResult['success'])) {
                throw new Exception($finalizeResult['message']);
            }

            approval_db_commit($conn);

            $requestAfter = approval_get_request_by_id($request_id, $conn);
            return approval_response(true, $requestAfter && $requestAfter['status'] === 'approved' ? 'تم الاعتماد النهائي وتنفيذ العملية' : 'تم اعتماد المرحلة بنجاح', [
                'request_status' => $requestAfter ? $requestAfter['status'] : 'pending'
            ]);
        } catch (Exception $ex) {
            approval_db_rollback($conn);
            return approval_response(false, $ex->getMessage());
        }
    }
}

if (!function_exists('approval_reject_request')) {
    function approval_reject_request($request_id, $rejected_by, $conn, $reason = '') {
        $request_id = intval($request_id);
        $rejected_by = intval($rejected_by);

        if ($request_id <= 0 || $rejected_by <= 0) {
            return approval_response(false, 'بيانات الرفض غير صحيحة');
        }

        approval_db_begin($conn);

        try {
            $request = approval_get_request_by_id($request_id, $conn);
            if (!$request) {
                throw new Exception('طلب الموافقة غير موجود');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('لا يمكن رفض طلب غير معلق');
            }

            $step = approval_get_next_pending_step($request_id, $conn);
            if (!$step) {
                throw new Exception('لا توجد مرحلة معلقة للرفض');
            }

            $user_role = approval_get_user_role();
            if (!approval_user_can_match_role($step['role_required'], $user_role)) {
                throw new Exception('ليس لديك صلاحية رفض هذه المرحلة');
            }

            $step_id = intval($step['id']);
            $reason_esc = mysqli_real_escape_string($conn, $reason);

            $sqlStep = "UPDATE approval_steps
                        SET status = 'rejected', approved_by = $rejected_by, approved_at = NOW(), note = '$reason_esc'
                        WHERE id = $step_id";
            if (!mysqli_query($conn, $sqlStep)) {
                throw new Exception('فشل تحديث خطوة الرفض: ' . mysqli_error($conn));
            }

            $sqlReq = "UPDATE approval_requests
                       SET status = 'rejected',
                           rejection_reason = '$reason_esc',
                           rejected_at = NOW(),
                           current_step = NULL,
                           updated_at = NOW()
                       WHERE id = $request_id";
            if (!mysqli_query($conn, $sqlReq)) {
                throw new Exception('فشل تحديث حالة الطلب: ' . mysqli_error($conn));
            }

            approval_db_commit($conn);
            return approval_response(true, 'تم رفض الطلب بنجاح');
        } catch (Exception $ex) {
            approval_db_rollback($conn);
            return approval_response(false, $ex->getMessage());
        }
    }
}

if (!function_exists('approval_build_simple_update_payload')) {
    function approval_build_simple_update_payload($table, $where, $new_data, $old_data = []) {
        return [
            'summary' => [
                'table' => $table,
                'operation' => 'update',
                'old_values' => $old_data,
                'new_values' => $new_data
            ],
            'operations' => [
                [
                    'db_action' => 'update',
                    'table' => $table,
                    'where' => $where,
                    'data' => $new_data
                ]
            ]
        ];
    }
}

if (!function_exists('approval_build_simple_delete_payload')) {
    function approval_build_simple_delete_payload($table, $where, $old_data = []) {
        return [
            'summary' => [
                'table' => $table,
                'operation' => 'delete',
                'old_values' => $old_data,
                'new_values' => null
            ],
            'operations' => [
                [
                    'db_action' => 'delete',
                    'table' => $table,
                    'where' => $where
                ]
            ]
        ];
    }
}
?>
<?php
/**
 * تسجيل حدث على معدة في fleet_equipment_history (سجل «تحركات الآلية»).
 * تُكتب فقط عند وجود تغيّر فعلي — تحقّق من ذلك *قبل* الاستدعاء.
 * كل القيم عبر prepared statement، والعزل بـ company_id (من opts أو الجلسة).
 */
if (!function_exists('log_equipment_event')) {
    function log_equipment_event($conn, $equipment_id, $event_type, array $opts = [])
    {
        $equipment_id = intval($equipment_id);
        if ($equipment_id <= 0 || $event_type === '') {
            return;
        }

        $company_id   = isset($opts['company_id']) ? intval($opts['company_id']) : (intval($_SESSION['user']['company_id'] ?? 0) ?: null);
        $project_id   = isset($opts['project_id'])   ? intval($opts['project_id'])   : null;
        $operation_id = isset($opts['operation_id']) ? intval($opts['operation_id']) : null;
        $user_id      = isset($opts['user_id'])      ? intval($opts['user_id'])      : (intval($_SESSION['user']['id'] ?? 0) ?: null);
        $from = isset($opts['from']) ? (string) $opts['from'] : null;
        $to   = isset($opts['to'])   ? (string) $opts['to']   : null;
        $note = isset($opts['note']) ? (string) $opts['note'] : null;

        $sql = "INSERT INTO fleet_equipment_history
                (company_id, equipment_id, event_date, event_type, project_id, operation_id, from_value, to_value, note, created_by)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
        if ($st = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param(
                $st,
                'iisiisssi',
                $company_id,
                $equipment_id,
                $event_type,
                $project_id,
                $operation_id,
                $from,
                $to,
                $note,
                $user_id
            );
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }
    }
}

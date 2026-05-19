<?php
/**
 * ActivityLogRepository — Data Access Layer
 *
 * Handles all database interactions for the activity_logs table.
 * Never used directly by the HTTP layer — go through ActivityLogService.
 */

declare(strict_types=1);

namespace App\Repositories;

class ActivityLogRepository
{
    /** @var \mysqli */
    private \mysqli $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Write
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Insert one activity log record.
     *
     * @param  array<string,mixed> $data
     * @return int|false  Inserted row ID on success, false on failure.
     */
    public function insert(array $data): int|false
    {
        $fields = [
            'company_id', 'project_id', 'contract_id',
            'user_id', 'role_id', 'role_name',
            'session_id', 'ip_address', 'user_agent',
            'screen_name', 'module_name',
            'action_type', 'button_name', 'field_name',
            'record_id',
            'old_value', 'new_value',
            'url', 'http_method', 'request_payload', 'response_status',
        ];

        $cols   = [];
        $values = [];

        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $cols[] = "`$f`";
            $v      = $data[$f];

            // JSON columns: encode arrays/objects automatically.
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($v === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . mysqli_real_escape_string($this->conn, (string)$v) . "'";
            }
        }

        if (empty($cols)) {
            return false;
        }

        $sql = 'INSERT INTO `activity_logs` (' . implode(', ', $cols) . ') '
             . 'VALUES (' . implode(', ', $values) . ')';

        $result = mysqli_query($this->conn, $sql);

        if (!$result) {
            error_log('[ActivityLog] DB insert error: ' . mysqli_error($this->conn));
            return false;
        }

        return (int) mysqli_insert_id($this->conn);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Read — cursor-paginated queries (no SELECT *)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Fetch role summary cards (distinct roles with counts + last activity).
     *
     * @param  int $companyId  0 = all companies (super-admin view)
     * @return array<int,array<string,mixed>>
     */
    public function getRoleSummary(int $companyId = 0): array
    {
        $where = $companyId > 0 ? "WHERE al.company_id = $companyId" : '';

        $sql = "SELECT
                    al.role_id,
                    COALESCE(MAX(r.name), MAX(role_name), CONCAT('دور #', role_id)) AS role_name,
                    COUNT(al.id)       AS total_logs,
                    MAX(al.created_at) AS last_activity
                FROM activity_logs al
                LEFT JOIN roles r ON r.id = al.role_id
                $where
                GROUP BY al.role_id
                ORDER BY total_logs DESC";

        $res  = mysqli_query($this->conn, $sql);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Paginated list using cursor pagination (after_id).
     *
     * @param  array<string,mixed> $filters
     * @param  int                 $afterId   Cursor (last seen id); 0 = start
     * @param  int                 $limit
     * @return array<int,array<string,mixed>>
     */
    public function getPage(array $filters = [], string $afterCreatedAt = '', int $afterId = 0, int $limit = 50): array
    {
        $conditions = [];

        if ($afterCreatedAt !== '' && $afterId > 0) {
            $escapedCreatedAt = mysqli_real_escape_string($this->conn, $afterCreatedAt);
            $conditions[] = "(al.created_at < '$escapedCreatedAt' OR (al.created_at = '$escapedCreatedAt' AND al.id < " . intval($afterId) . "))";
        } elseif ($afterId > 0) {
            $conditions[] = "al.id < " . intval($afterId);
        }

        $this->applyFilters($conditions, $filters);

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $sql = "SELECT
                    al.id,
                    al.created_at,
                    al.company_id,
                    al.project_id,
                    al.user_id,
                    COALESCE(u.name, u.username, CONCAT('مستخدم #', al.user_id)) AS user_name,
                    al.role_id,
                    COALESCE(r.name, al.role_name, CONCAT('دور #', al.role_id)) AS role_name,
                    al.module_name,
                    al.screen_name,
                    al.action_type,
                    al.button_name,
                    al.record_id,
                    al.response_status,
                    al.http_method,
                    al.url
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                LEFT JOIN roles r ON r.id = al.role_id
                $where
                ORDER BY al.created_at DESC, al.id DESC
                LIMIT " . intval($limit);

        $res  = mysqli_query($this->conn, $sql);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Fetch full detail for a single log row (for the modal).
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function getDetail(int $id): ?array
    {
        $id  = intval($id);
        $sql = "SELECT
                    al.id, al.created_at,
                    al.company_id, al.project_id, al.contract_id,
                    al.user_id, al.role_id,
                    COALESCE(u.name, u.username, CONCAT('مستخدم #', al.user_id)) AS user_name,
                    COALESCE(r.name, al.role_name, CONCAT('دور #', al.role_id)) AS role_name,
                    al.session_id, al.ip_address, al.user_agent,
                    al.module_name, al.screen_name,
                    al.action_type, al.button_name, al.field_name,
                    al.record_id,
                    al.old_value, al.new_value,
                    al.url, al.http_method, al.request_payload, al.response_status
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                LEFT JOIN roles r ON r.id = al.role_id
                WHERE al.id = $id
                LIMIT 1";

        $res = mysqli_query($this->conn, $sql);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return $row;
        }
        return null;
    }

    /**
     * Initial load: last N rows with no cursor, no filters.
     *
     * @param  int $companyId
     * @param  int $roleId     0 = all roles
     * @param  int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getInitialPage(int $companyId = 0, int $roleId = 0, int $limit = 1000): array
    {
        $conditions = [];
        if ($companyId > 0) {
            $conditions[] = "al.company_id = $companyId";
        }
        if ($roleId > 0) {
            $conditions[] = "al.role_id = $roleId";
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $sql = "SELECT
                    al.id,
                    al.created_at,
                    al.company_id,
                    al.project_id,
                    al.user_id,
                    COALESCE(u.name, u.username, CONCAT('مستخدم #', al.user_id)) AS user_name,
                    al.role_id,
                    COALESCE(r.name, al.role_name, CONCAT('دور #', al.role_id)) AS role_name,
                    al.module_name,
                    al.screen_name,
                    al.action_type,
                    al.button_name,
                    al.record_id,
                    al.response_status,
                    al.http_method,
                    al.url
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                LEFT JOIN roles r ON r.id = al.role_id
                $where
                ORDER BY al.created_at DESC, al.id DESC
                LIMIT " . intval($limit);

        $res  = mysqli_query($this->conn, $sql);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────

    /** @param string[] &$conditions */
    private function applyFilters(array &$conditions, array $filters): void
    {
        $intFields = ['company_id', 'user_id', 'role_id', 'project_id', 'record_id', 'response_status'];
        foreach ($intFields as $f) {
            if (!empty($filters[$f])) {
                $conditions[] = "al.$f = " . intval($filters[$f]);
            }
        }

        $strFields = ['action_type', 'module_name', 'screen_name', 'http_method'];
        foreach ($strFields as $f) {
            if (!empty($filters[$f])) {
                $escaped      = mysqli_real_escape_string($this->conn, trim($filters[$f]));
                $conditions[] = "al.$f = '$escaped'";
            }
        }

        if (!empty($filters['date_from'])) {
            $d            = mysqli_real_escape_string($this->conn, $filters['date_from']);
            $conditions[] = "al.created_at >= '$d 00:00:00'";
        }
        if (!empty($filters['date_to'])) {
            $d            = mysqli_real_escape_string($this->conn, $filters['date_to']);
            $conditions[] = "al.created_at <= '$d 23:59:59'";
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Archiving helpers (future use)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Delete logs older than a given date.
     * Safe to call from a scheduled cron.
     */
    public function deleteOlderThan(string $date): int
    {
        $escaped = mysqli_real_escape_string($this->conn, $date);
        mysqli_query($this->conn, "DELETE FROM activity_logs WHERE created_at < '$escaped' LIMIT 10000");
        return (int) mysqli_affected_rows($this->conn);
    }
}

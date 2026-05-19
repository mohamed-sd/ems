<?php
/**
 * ActivityLogService — Central service for recording activity events.
 *
 * Usage (anywhere in the app):
 *
 *   ActivityLogService::log([
 *       'action_type'  => 'delete',
 *       'module_name'  => 'contracts',
 *       'screen_name'  => 'contracts_list',
 *       'record_id'    => 55,
 *       'old_value'    => $oldData,
 *   ]);
 *
 *   ActivityLogService::logCreate('drivers', 'drivers_form', 12, $newData);
 *   ActivityLogService::logUpdate('suppliers', 'suppliers_list', 7, $old, $new);
 *   ActivityLogService::logDelete('projects', 'projects_list', 3, $old);
 *   ActivityLogService::logLogin();
 *   ActivityLogService::logLogout();
 *   ActivityLogService::logAction('export', 'timesheets', 'timesheets_list');
 */

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;

class ActivityLogService
{
    /** @var array<int,array<string,mixed>> Pending entries flushed on shutdown. */
    private static array $pending = [];

    /** @var bool Whether the shutdown handler is already registered. */
    private static bool $shutdownRegistered = false;
    // ─────────────────────────────────────────────────────────────────────
    // Sensitive field names stripped from request_payload before persist.
    // ─────────────────────────────────────────────────────────────────────
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'csrf_token',
        'access_token', 'secret', 'authorization', '_token',
        'current_password', 'new_password', 'confirm_password',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Convenience shortcuts
    // ─────────────────────────────────────────────────────────────────────

    public static function logCreate(
        string $module,
        string $screen,
        int    $recordId = 0,
        mixed  $newValue = null,
        array  $extra    = []
    ): void {
        self::log(array_merge([
            'action_type' => 'create',
            'module_name' => $module,
            'screen_name' => $screen,
            'record_id'   => $recordId ?: null,
            'new_value'   => $newValue,
        ], $extra));
    }

    public static function logUpdate(
        string $module,
        string $screen,
        int    $recordId = 0,
        mixed  $oldValue = null,
        mixed  $newValue = null,
        array  $extra    = []
    ): void {
        self::log(array_merge([
            'action_type' => 'update',
            'module_name' => $module,
            'screen_name' => $screen,
            'record_id'   => $recordId ?: null,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
        ], $extra));
    }

    public static function logDelete(
        string $module,
        string $screen,
        int    $recordId = 0,
        mixed  $oldValue = null,
        array  $extra    = []
    ): void {
        self::log(array_merge([
            'action_type' => 'delete',
            'module_name' => $module,
            'screen_name' => $screen,
            'record_id'   => $recordId ?: null,
            'old_value'   => $oldValue,
        ], $extra));
    }

    public static function logLogin(array $extra = []): void
    {
        self::log(array_merge([
            'action_type' => 'login',
            'module_name' => 'auth',
            'screen_name' => 'login',
        ], $extra));
    }

    public static function logLogout(array $extra = []): void
    {
        self::log(array_merge([
            'action_type' => 'logout',
            'module_name' => 'auth',
            'screen_name' => 'logout',
        ], $extra));
    }

    /**
     * Generic action logger: export, print, search, click, view …
     */
    public static function logAction(
        string $actionType,
        string $module,
        string $screen,
        array  $extra = []
    ): void {
        self::log(array_merge([
            'action_type' => $actionType,
            'module_name' => $module,
            'screen_name' => $screen,
        ], $extra));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Core dispatcher
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Queue a log entry for async flush after the HTTP response is sent.
     * Never blocks the user-facing request.
     *
     * @param array<string,mixed> $data
     */
    public static function log(array $data): void
    {
        if (self::isAdminRequest()) {
            return;
        }

        try {
            self::$pending[] = self::buildEntry($data);
            self::registerShutdown();
        } catch (\Throwable $e) {
            error_log('[ActivityLogService] Exception: ' . $e->getMessage());
        }
    }

    /**
     * Flush all pending entries to the DB.
     * Called automatically in the shutdown handler — do not call manually
     * unless you need an immediate synchronous flush (e.g. CLI scripts).
     */
    public static function flush(): void
    {
        if (empty(self::$pending)) {
            return;
        }

        global $conn;

        if (!($conn instanceof \mysqli) || !@mysqli_ping($conn)) {
            error_log('[ActivityLogService] No DB connection during flush.');
            self::$pending = [];
            return;
        }

        $repo = new ActivityLogRepository($conn);

        foreach (self::$pending as $entry) {
            try {
                $repo->insert($entry);
            } catch (\Throwable $e) {
                error_log('[ActivityLogService] Insert failed: ' . $e->getMessage());
            }
        }

        self::$pending = [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Shutdown registration
    // ─────────────────────────────────────────────────────────────────────

    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;

        register_shutdown_function(function (): void {
            // Finish sending the response to the browser first,
            // so DB inserts happen after the user gets their page.
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            } else {
                // Apache mod_php fallback.
                if (!headers_sent()) {
                    header('Connection: close');
                    header('Content-Encoding: none');
                }
                @ob_end_flush();
                flush();
            }

            ActivityLogService::flush();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Merge caller-supplied data with auto-detected session/request context.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function buildEntry(array $data): array
    {
        $session = self::sessionContext();
        $request = self::requestContext();

        // Caller data wins over auto-detected values.
        return array_merge($session, $request, $data);
    }

    /** @return array<string,mixed> */
    private static function sessionContext(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        $user = $_SESSION['user'] ?? [];

        return [
            'company_id'  => isset($user['company_id'])  ? intval($user['company_id'])  : null,
            'project_id'  => isset($user['project_id'])  ? intval($user['project_id'])  : null,
            'contract_id' => isset($user['contract_id']) ? intval($user['contract_id']) : null,
            'user_id'     => isset($user['id'])          ? intval($user['id'])          : null,
            'role_id'     => isset($user['role'])        ? intval($user['role'])        : null,
            'role_name'   => self::resolveRoleName($user['role'] ?? null),
            'session_id'  => session_id() ?: null,
        ];
    }

    /** @return array<string,mixed> */
    private static function requestContext(): array
    {
        $url    = self::currentUrl();
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        return [
            'ip_address'       => self::clientIp(),
            'user_agent'       => isset($_SERVER['HTTP_USER_AGENT'])
                                    ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                                    : null,
            'url'              => $url,
            'http_method'      => $method,
            'request_payload'  => self::sanitizePayload(),
            'response_status'  => http_response_code() ?: 200,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private static function isAdminRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Normalise: strip query string, decode, lower-case.
        $path = strtolower(parse_url($uri, PHP_URL_PATH) ?? '');
        $path = str_replace('\\', '/', $path);

        // Match /admin/ prefix regardless of project subfolder.
        return (bool) preg_match('#/admin(/|$)#i', $path);
    }

    private static function currentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    private static function clientIp(): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                // Take the first IP from comma-separated lists.
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return null;
    }

    /**
     * Recursively strip sensitive keys from POST/GET data.
     *
     * @return array<string,mixed>|null
     */
    private static function sanitizePayload(): ?array
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $payload = $method === 'GET' ? $_GET : $_POST;

        if (empty($payload)) {
            return null;
        }

        return self::stripSensitive($payload);
    }

    /**
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function stripSensitive(array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string)$k), self::SENSITIVE_KEYS, true)) {
                $clean[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $clean[$k] = self::stripSensitive($v);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }

    private static function resolveRoleName(mixed $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        static $cache = [];
        $roleId = intval($role);
        if ($roleId === 0 && (string)$role !== '0') {
            return null;
        }

        if (array_key_exists($roleId, $cache)) {
            return $cache[$roleId];
        }

        global $conn;
        if (isset($conn) && $conn instanceof \mysqli) {
            $sql = 'SELECT name FROM roles WHERE id = ' . $roleId . ' LIMIT 1';
            $res = @mysqli_query($conn, $sql);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name !== '') {
                    return $cache[$roleId] = $name;
                }
            }
        }

        return $cache[$roleId] = 'دور #' . $roleId;
    }
}

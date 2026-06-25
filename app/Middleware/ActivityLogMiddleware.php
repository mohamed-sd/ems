<?php
/**
 * ActivityLogMiddleware — Intercepts every HTTP request and records a log entry.
 *
 * How to register (see bootstrap.php):
 *
 *   ActivityLogMiddleware::boot();   // call once, at top of config.php / bootstrap
 *
 * The middleware hooks into PHP's output buffering to capture the final
 * HTTP response code after the page has finished rendering, then enqueues
 * the log asynchronously so it never slows down the user-facing response.
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ActivityLogService;

class ActivityLogMiddleware
{
    // ─────────────────────────────────────────────────────────────────────
    // Action → type mapping derived from URL / request patterns
    // ─────────────────────────────────────────────────────────────────────
    private const POST_ACTION_MAP = [
        // Common POST parameter names → action types
        'delete'  => 'delete',
        'remove'  => 'delete',
        'create'  => 'create',
        'add'     => 'create',
        'insert'  => 'create',
        'update'  => 'update',
        'edit'    => 'update',
        'save'    => 'update',
        'export'  => 'export',
        'print'   => 'print',
        'search'  => 'search',
        'login'   => 'login',
        'logout'  => 'logout',
    ];

    /** @var bool Prevent double-registration. */
    private static bool $booted = false;

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Register the middleware.  Safe to call multiple times.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        // Skip admin routes completely — they have their own audit log.
        if (self::isAdminRequest()) {
            self::$booted = true;
            return;
        }

        self::$booted = true;

        // Register shutdown callback to log the request AFTER the response
        // is sent. ActivityLogService::log() handles the async flush via its
        // own shutdown handler (fastcgi_finish_request).
        register_shutdown_function([static::class, 'onShutdown']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Callbacks
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Called by PHP after the response finishes.
     * Hands off to ActivityLogService which flushes after fastcgi_finish_request.
     */
    public static function onShutdown(): void
    {
        if (self::isAdminRequest()) {
            return;
        }

        if (self::isAuthRequest()) {
            return;
        }

        // Chat AJAX endpoints write explicit logs to avoid duplicate "create" records.
        if (self::isChatApiRequest()) {
            return;
        }

        // Read-only helper AJAX endpoints (POST used only for loading options/data).
        if (self::isReadOnlyHelperRequest()) {
            return;
        }

        $actionType = self::detectActionType();
        if ($actionType === null) {
            return;
        }

        ActivityLogService::log([
            'action_type'     => $actionType,
            'button_name'     => self::detectButtonName($actionType),
            'record_id'       => self::detectRecordId(),
            'module_name'     => self::detectSourceModuleName(),
            'screen_name'     => self::detectSourceScreenName(),
            'url'             => self::detectSourceUrl(),
            'response_status' => http_response_code() ?: 200,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Detection helpers
    // ─────────────────────────────────────────────────────────────────────

    private static function detectActionType(): ?string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? '')));

        // save_operation is used for both add/edit in operations forms.
        // If operation_id exists, classify as update; otherwise create.
        if ($action === 'save_operation') {
            return self::hasUpdateHint() ? 'update' : 'create';
        }

        if (self::hasDeleteHint()) {
            return 'delete';
        }

        if ($action !== '') {
            if (isset(self::POST_ACTION_MAP[$action])) {
                return self::POST_ACTION_MAP[$action];
            }
            if (str_contains($action, 'delete') || str_contains($action, 'remove')) {
                return 'delete';
            }
            if (str_contains($action, 'update') || str_contains($action, 'edit')) {
                return 'update';
            }
            if (str_contains($action, 'create') || str_contains($action, 'add') || str_contains($action, 'insert')) {
                return 'create';
            }
            return $action;
        }

        if ($method === 'GET') {
            return null;
        }

        // For POST requests check common parameter hints.
        foreach (array_keys($_POST) as $key) {
            $key = strtolower($key);
            if (isset(self::POST_ACTION_MAP[$key])) {
                return self::POST_ACTION_MAP[$key];
            }
        }

        if (self::hasUpdateHint()) {
            return 'update';
        }

        return 'create';
    }

    private static function hasDeleteHint(): bool
    {
        $keys = array_merge(array_keys($_GET), array_keys($_POST));
        foreach ($keys as $key) {
            $k = strtolower((string)$key);
            if (in_array($k, ['delete', 'delete_id', 'remove', 'remove_id', 'trash', 'trash_id'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function hasUpdateHint(): bool
    {
        $updateIdFields = ['id', 'client_id', 'supplier_id', 'employee_id', 'operation_id', 'timesheet_id', 'record_id'];
        foreach ($updateIdFields as $field) {
            if (!isset($_POST[$field])) {
                continue;
            }
            if (is_scalar($_POST[$field]) && intval((string)$_POST[$field]) > 0) {
                return true;
            }
        }

        return false;
    }
    private static function detectRecordId(): ?int
    {
        $idFields = [
            'record_id',
            'operation_id',
            'id',
            'client_id',
            'supplier_id',
            'employee_id',
            'timesheet_id',
            'delete_id',
            'remove_id',
        ];

        foreach ($idFields as $field) {
            if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
                $id = intval((string) $_POST[$field]);
                if ($id > 0) {
                    return $id;
                }
            }
        }

        foreach ($idFields as $field) {
            if (isset($_GET[$field]) && is_scalar($_GET[$field])) {
                $id = intval((string) $_GET[$field]);
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return null;
    }

    private static function detectButtonName(string $actionType): ?string
    {
        $candidates = ['button_name', 'action', 'submit', 'btn', 'button', 'op'];
        foreach ($candidates as $field) {
            if (!isset($_POST[$field])) {
                continue;
            }
            $value = trim((string)$_POST[$field]);
            if ($value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        foreach (array_keys($_POST) as $key) {
            $k = strtolower((string)$key);
            if (isset(self::POST_ACTION_MAP[$k])) {
                return $k;
            }
        }

        foreach (['action', 'delete_id', 'remove_id'] as $field) {
            if (isset($_GET[$field]) && trim((string)$_GET[$field]) !== '') {
                return $field === 'action' ? trim((string)$_GET[$field]) : $actionType;
            }
        }

        return $actionType;
    }

    private static function detectSourceModuleName(): string
    {
        $path = self::detectSourcePath();
        $parts = array_filter(explode('/', str_replace('\\', '/', $path)));
        $parts = array_values($parts);

        // Strip the project sub-folder prefix (e.g. "ems").
        if (count($parts) > 1 && !str_contains($parts[0], '.php')) {
            $folder = $parts[1] ?? '';
        } else {
            $folder = $parts[0] ?? '';
        }

        // Remove .php suffix.
        return strtolower(preg_replace('/\.php$/i', '', $folder) ?: 'root');
    }

    private static function detectSourceScreenName(): string
    {
        $path   = self::detectSourcePath();
        $file   = basename(str_replace('\\', '/', $path));
        return strtolower(preg_replace('/\.php$/i', '', $file) ?: 'index');
    }

    private static function detectSourceUrl(): string
    {
        $source = $_SERVER['HTTP_REFERER'] ?? '';
        if ($source !== '') {
            return $source;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    private static function detectSourcePath(): string
    {
        $source = $_SERVER['HTTP_REFERER'] ?? '';
        if ($source !== '') {
            $path = parse_url($source, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }

    private static function isAdminRequest(): bool
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = strtolower(parse_url($uri, PHP_URL_PATH) ?? '');
        $path = str_replace('\\', '/', $path);
        return (bool) preg_match('#/admin(/|$)#i', $path);
    }

    private static function isAuthRequest(): bool
    {
        $path = strtolower(self::detectSourcePath());
        return (bool) preg_match('#(^|/)(login|logout)\.php$#i', $path);
    }

    private static function isChatApiRequest(): bool
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = strtolower(parse_url($uri, PHP_URL_PATH) ?? '');
        $path = str_replace('\\', '/', $path);

        return (bool) preg_match('#/chats/(send_message|send_broadcast|mark_read|get_messages|get_unread_count)\.php$#i', $path);
    }

    private static function isReadOnlyHelperRequest(): bool
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = strtolower(parse_url($uri, PHP_URL_PATH) ?? '');
        $path = str_replace('\\', '/', $path);

        // These endpoints fetch UI data only and should not be logged as create/update.
        if ((bool) preg_match('#/oprators/get_mine_contracts\.php$#i', $path)) {
            return true;
        }

        if ((bool) preg_match('#/oprators/get_contract_suppliers\.php$#i', $path)) {
            return true;
        }

        return false;
    }
}

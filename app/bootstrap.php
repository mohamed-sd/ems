<?php
/**
 * EMS Application Bootstrap — app/bootstrap.php
 *
 * Registered once from config.php.
 * Responsibilities:
 *  1. PSR-4-style autoloader for the app/ namespace.
 *  2. Define path constants.
 *  3. Boot the ActivityLogMiddleware.
 *  4. Create the queue spool directory if missing.
 */

declare(strict_types=1);

if (defined('EMS_APP_BOOTSTRAPPED')) {
    return;
}
define('EMS_APP_BOOTSTRAPPED', true);

// ── Path constants ────────────────────────────────────────────────────────
define('EMS_ROOT_DIR',      dirname(__DIR__));
define('EMS_APP_DIR',       __DIR__);
define('EMS_STORAGE_DIR',   EMS_ROOT_DIR . '/storage');
define('EMS_QUEUE_SPOOL_DIR', EMS_STORAGE_DIR . '/queue');
define('EMS_LOGS_DIR',      EMS_STORAGE_DIR . '/logs');

// ── Composer autoloader (PhpSpreadsheet & other vendor libs) ──────────────
// Loaded centrally so the whole App\ layer (e.g. the Unified Excel Framework)
// has vendor classes available without per-file `require vendor/autoload`.
$emsVendorAutoload = EMS_ROOT_DIR . '/vendor/autoload.php';
if (is_file($emsVendorAutoload)) {
    require_once $emsVendorAutoload;
}
unset($emsVendorAutoload);

// ── Create necessary directories (once, with .htaccess protection) ────────
foreach ([EMS_STORAGE_DIR, EMS_QUEUE_SPOOL_DIR, EMS_LOGS_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
        // Deny direct HTTP access to storage.
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\n");
        }
    }
}

// ── PSR-4 Autoloader for namespace App\\ ──────────────────────────────────
spl_autoload_register(function (string $class): void {
    // Only handle the App\ namespace.
    if (strpos($class, 'App\\') !== 0) {
        return;
    }

    // App\Services\ActivityLogService → app/Services/ActivityLogService.php
    $relative = str_replace('\\', '/', substr($class, 4)); // strip "App\"
    $file     = EMS_APP_DIR . '/' . $relative . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Boot Middleware ───────────────────────────────────────────────────────
// Skip CLI (cron/worker).
if (php_sapi_name() !== 'cli') {
    \App\Middleware\ActivityLogMiddleware::boot();
}

// ── Auto-create activity_logs table if not exists ────────────────────────
// This runs once (DDL is cheap when table already exists via IF NOT EXISTS).
if (isset($conn) && $conn instanceof \mysqli) {
    $sqlFile = EMS_ROOT_DIR . '/database/create_activity_logs.sql';
    if (file_exists($sqlFile)) {
        static $tableChecked = false;
        if (!$tableChecked) {
            $tableChecked = true;
            $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'activity_logs'");
            if ($chk && mysqli_num_rows($chk) === 0) {
                // Table missing — run the migration DDL only (skip the INSERT).
                $ddl = @file_get_contents($sqlFile);
                if ($ddl !== false) {
                    // Extract only the CREATE TABLE block.
                    if (preg_match('/(CREATE TABLE IF NOT EXISTS.*?;)/si', $ddl, $m)) {
                        @mysqli_query($conn, $m[1]);
                    }
                }
            }
        }
    }
}

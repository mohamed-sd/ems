#!/usr/bin/env php
<?php
/**
 * Activity Log Queue Worker
 *
 * Processes queued activity log jobs from the file spool.
 * Run via cron every minute:
 *
 *   * * * * * php /path/to/ems/scripts/activity_log_worker.php >> /dev/null 2>&1
 *
 * Or run continuously (with a supervisor like supervisord / nohup):
 *
 *   php scripts/activity_log_worker.php --daemon
 */

declare(strict_types=1);

// Ensure this only runs from CLI.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('EMS_ROOT_DIR', dirname(__DIR__));

// Bootstrap the application (autoloader, constants).
require_once EMS_ROOT_DIR . '/config.php';

use App\Queues\QueueManager;
use App\Jobs\SaveActivityLogJob;

$isDaemon = in_array('--daemon', $argv ?? [], true);
$batchSize = 50;

$queue = new QueueManager(EMS_QUEUE_SPOOL_DIR, 'activity_logs');

echo "[" . date('Y-m-d H:i:s') . "] Worker started. Pending: " . $queue->pendingCount() . "\n";

if ($isDaemon) {
    // Continuous loop — process until empty, then sleep.
    while (true) {
        $processed = $queue->work($batchSize);
        if ($processed === 0) {
            sleep(2);
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Processed: $processed jobs\n";
        }
    }
} else {
    // One-shot batch (for cron).
    $processed = $queue->work($batchSize);
    echo "[" . date('Y-m-d H:i:s') . "] Processed: $processed jobs. Remaining: " . $queue->pendingCount() . "\n";
}

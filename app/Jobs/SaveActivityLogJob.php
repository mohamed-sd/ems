<?php
/**
 * SaveActivityLogJob — Background job that persists a single activity log.
 *
 * Resolved by QueueManager::processFile() — must implement handle().
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\ActivityLogRepository;

class SaveActivityLogJob
{
    /**
     * Called by QueueManager with the serialised payload.
     *
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload): void
    {
        // Re-open the database connection for the background context.
        // config.php creates $conn in global scope; we require it here safely.
        global $conn;

        if (!($conn instanceof \mysqli) || mysqli_ping($conn) === false) {
            require_once dirname(__DIR__, 2) . '/config.php';
        }

        if (!($conn instanceof \mysqli)) {
            error_log('[SaveActivityLogJob] No DB connection available.');
            return;
        }

        $repo = new ActivityLogRepository($conn);
        $repo->insert($payload);
    }
}

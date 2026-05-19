<?php
/**
 * QueueManager — File-based async queue for PHP Pure environments.
 *
 * Since PHP Pure has no built-in queue daemon, we use a file-based
 * spool directory.  Each job is written as a JSON file.  A lightweight
 * background worker (worker.php) or a CLI cron reads and processes them.
 *
 * This class is intentionally framework-agnostic.
 */

declare(strict_types=1);

namespace App\Queues;

class QueueManager
{
    /** @var string Absolute path to the spool directory. */
    private string $spoolDir;

    /** @var string Queue channel name (sub-folder). */
    private string $channel;

    public function __construct(string $spoolDir, string $channel = 'default')
    {
        $this->spoolDir = rtrim($spoolDir, '/\\');
        $this->channel  = preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);

        $dir = $this->channelDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Push a job payload onto the queue.
     *
     * @param  string              $jobClass  Fully-qualified class name.
     * @param  array<string,mixed> $payload
     * @return bool
     */
    public function push(string $jobClass, array $payload): bool
    {
        $envelope = [
            'job'        => $jobClass,
            'payload'    => $payload,
            'queued_at'  => microtime(true),
            'attempts'   => 0,
        ];

        $filename = $this->channelDir() . '/' . $this->uniqueFilename();

        // Atomic write: temp file → rename (prevents partial reads).
        $tmp = $filename . '.tmp';
        $ok  = file_put_contents($tmp, json_encode($envelope, JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($ok === false) {
            error_log("[QueueManager] Failed to write job file: $tmp");
            return false;
        }
        return rename($tmp, $filename);
    }

    /**
     * Pop and process the next job.
     *
     * @param  int $maxJobs  Maximum jobs to process in this call.
     * @return int           Number of jobs processed.
     */
    public function work(int $maxJobs = 10): int
    {
        $files     = $this->pendingFiles();
        $processed = 0;

        foreach (array_slice($files, 0, $maxJobs) as $file) {
            if ($this->processFile($file)) {
                $processed++;
            }
        }
        return $processed;
    }

    /**
     * Count pending jobs in this channel.
     */
    public function pendingCount(): int
    {
        return count($this->pendingFiles());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────

    private function channelDir(): string
    {
        return $this->spoolDir . '/' . $this->channel;
    }

    private function uniqueFilename(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.job';
    }

    /** @return string[] */
    private function pendingFiles(): array
    {
        $files = glob($this->channelDir() . '/*.job') ?: [];
        sort($files);
        return $files;
    }

    private function processFile(string $file): bool
    {
        // Lock the file to prevent concurrent workers from picking it up.
        $fh = @fopen($file, 'r');
        if (!$fh) {
            return false;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return false;
        }

        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        if (!$raw) {
            @unlink($file);
            return false;
        }

        $envelope = json_decode($raw, true);
        if (!is_array($envelope) || empty($envelope['job'])) {
            @unlink($file);
            return false;
        }

        // Rename to .processing to prevent double-pickup.
        $processing = $file . '.processing';
        if (!rename($file, $processing)) {
            return false;
        }

        try {
            $jobClass = $envelope['job'];
            if (!class_exists($jobClass)) {
                error_log("[QueueManager] Job class not found: $jobClass");
                @unlink($processing);
                return false;
            }

            /** @var object $job */
            $job = new $jobClass();
            $job->handle($envelope['payload']);

            @unlink($processing);
            return true;
        } catch (\Throwable $e) {
            error_log("[QueueManager] Job failed [{$envelope['job']}]: " . $e->getMessage());
            // Move to dead-letter queue.
            $failed = $processing . '.failed';
            @rename($processing, $failed);
            return false;
        }
    }
}

<?php
/**
 * BaseObserver — Optional manual hook for explicit CRUD events.
 *
 * Extend this in any module file where you want explicit old/new diffs:
 *
 *   class ContractObserver extends BaseObserver
 *   {
 *       protected string $module = 'contracts';
 *       protected string $screen = 'contracts_list';
 *   }
 *
 *   $obs = new ContractObserver();
 *   $obs->creating($newData);
 *   $obs->updating($oldData, $newData, $recordId);
 *   $obs->deleting($oldData, $recordId);
 */

declare(strict_types=1);

namespace App\Observers;

use App\Services\ActivityLogService;

abstract class BaseObserver
{
    /** Override in subclass — maps to module_name. */
    protected string $module = '';

    /** Override in subclass — maps to screen_name. */
    protected string $screen = '';

    public function creating(mixed $newValue = null, int $recordId = 0): void
    {
        ActivityLogService::logCreate($this->module, $this->screen, $recordId, $newValue);
    }

    public function updating(mixed $oldValue = null, mixed $newValue = null, int $recordId = 0): void
    {
        ActivityLogService::logUpdate($this->module, $this->screen, $recordId, $oldValue, $newValue);
    }

    public function deleting(mixed $oldValue = null, int $recordId = 0): void
    {
        ActivityLogService::logDelete($this->module, $this->screen, $recordId, $oldValue);
    }

    public function viewing(int $recordId = 0): void
    {
        ActivityLogService::logAction('view', $this->module, $this->screen, [
            'record_id' => $recordId ?: null,
        ]);
    }

    public function exporting(): void
    {
        ActivityLogService::logAction('export', $this->module, $this->screen);
    }

    public function searching(array $criteria = []): void
    {
        ActivityLogService::logAction('search', $this->module, $this->screen, [
            'request_payload' => $criteria ?: null,
        ]);
    }
}

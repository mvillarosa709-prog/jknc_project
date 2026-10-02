<?php

namespace App\Observers;

use App\Models\TaskTimer;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;

class TaskTimerAuditObserver
{
    public function created(TaskTimer $timer): void
    {
        $this->record($timer, 'time_entry_created');
    }

    public function updated(TaskTimer $timer): void
    {
        $this->record($timer, 'time_entry_updated');
    }

    public function deleted(TaskTimer $timer): void
    {
        $this->record($timer, 'time_entry_deleted');
    }

    private function record(TaskTimer $timer, string $action): void
    {
        app(ActivityAuditService::class)->record(ActivityTypeRegistry::TASK, $timer->task_id, $action, [], [
            'task_timer_id' => $timer->id,
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateRecurringActivities extends Command
{
    protected $signature = 'activities:generate-recurring';
    protected $description = 'Create the next open instance for recurring Activity tasks';

    public function handle(): int
    {
        $created = 0;
        Task::query()->whereNotNull('recurrence')->whereNotNull('due_date')
            ->whereNotIn('status', ['Cancelled'])->get()->each(function (Task $task) use (&$created): void {
                $due = $task->due_date instanceof Carbon ? $task->due_date : Carbon::parse($task->due_date);
                if ($due->isFuture()) return;
                $frequency = strtolower((string) $task->recurrence);
                $next = match ($frequency) {
                    'daily' => $due->copy()->addDay(),
                    'weekly' => $due->copy()->addWeek(),
                    'monthly' => $due->copy()->addMonth(),
                    default => null,
                };
                if (! $next) return;
                $key = 'activity-recurring:'.$task->id.':'.$next->format('YmdHis');
                if (! Cache::add($key, true, now()->addYear())) return;
                $copy = $task->replicate(['actual_duration_minutes', 'manual_actual_duration_minutes', 'completed_at', 'cancelled_at']);
                $copy->status = 'Open';
                $copy->start_date = $task->start_date ? Carbon::parse($task->start_date)->addSeconds($due->diffInSeconds($next, false)) : null;
                $copy->due_date = $next->format('Y-m-d H:i:s');
                $copy->completed_at = null;
                $copy->cancelled_at = null;
                $copy->save();
                $copy->assignees()->sync($task->assignees->pluck('id')->all());
                $created++;
            });
        $this->info("Generated {$created} recurring task(s).");
        return self::SUCCESS;
    }
}

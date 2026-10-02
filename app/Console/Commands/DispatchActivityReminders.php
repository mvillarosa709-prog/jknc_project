<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\SystemRealtimeNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchActivityReminders extends Command
{
    protected $signature = 'activities:dispatch-reminders';
    protected $description = 'Dispatch due Activity task reminders once per reminder window';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        Task::query()->with(['primaryAssignee', 'assignees', 'preparedBy'])
            ->whereNotNull('due_date')
            ->whereNotNull('reminder_type')
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->get()
            ->each(function (Task $task) use ($now, &$sent): void {
                $due = $task->due_date instanceof Carbon ? $task->due_date : Carbon::parse($task->due_date);
                if (! $due) return;

                $minutes = (int) ($task->reminder_value ?? 0);
                $target = $task->reminder_type === 'before_due'
                    ? $due->copy()->subMinutes($minutes)
                    : $due;
                if ($now->lt($target) || $now->gte($target->copy()->addMinutes(1))) return;

                $recipients = $task->assignees->merge([$task->primaryAssignee, $task->preparedBy])
                    ->filter()->unique('id');
                foreach ($recipients as $user) {
                    $key = 'activity-reminder:'.$task->id.':'.$target->format('YmdHi').':'.$user->id;
                    if (Cache::add($key, true, now()->addDay())) {
                        $user->notify(new SystemRealtimeNotification(
                            'Task reminder',
                            $task->name.' is due '.($due->isPast() ? 'now' : 'soon').'.',
                            '/activities?task='.$task->id,
                            'Activities'
                        ));
                        $sent++;
                    }
                }
            });

        $this->info("Dispatched {$sent} reminder(s).");
        return self::SUCCESS;
    }
}

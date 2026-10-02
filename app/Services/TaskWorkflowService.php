<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskWorkflowService
{
    public function assertValidParent(?int $taskId, ?int $parentId): void
    {
        if (! $parentId) {
            return;
        }

        if ($taskId && $taskId === $parentId) {
            $this->cycleError('parent_task_id', 'A task cannot be its own parent.');
        }

        $parents = Task::withTrashed()->pluck('parent_task_id', 'id');
        $cursor = $parentId;
        $seen = [];

        while ($cursor) {
            if (isset($seen[$cursor])) {
                $this->cycleError('parent_task_id', 'The selected hierarchy already contains a cycle.');
            }

            if ($taskId && $cursor === $taskId) {
                $this->cycleError('parent_task_id', 'A task cannot be moved beneath one of its descendants.');
            }

            $seen[$cursor] = true;
            $cursor = $parents->get($cursor);
        }
    }

    public function assertValidDependencies(int $taskId, array $blockingTaskIds): void
    {
        $blockersByDependent = DB::table('task_dependencies')
            ->get(['blocking_task_id', 'dependent_task_id'])
            ->groupBy('dependent_task_id')
            ->map(fn (Collection $rows): array => $rows->pluck('blocking_task_id')->map(fn ($id) => (int) $id)->all());

        foreach (array_unique(array_map('intval', $blockingTaskIds)) as $blockerId) {
            if ($blockerId === $taskId || $this->dependsOn($blockerId, $taskId, $blockersByDependent)) {
                $this->cycleError('dependency_task_ids', 'Task dependencies cannot contain a cycle.');
            }
        }
    }

    public function synchronizeActualDuration(Task $task): Task
    {
        $seconds = $task->timers()
            ->whereNotNull('stopped_at')
            ->get()
            ->sum(function ($timer): int {
                $elapsed = (int) $timer->started_at->diffInSeconds($timer->stopped_at);

                return max(0, $elapsed - (int) $timer->paused_seconds);
            });

        $timerMinutes = $seconds === 0 ? 0 : (int) ceil($seconds / 60);
        $manualMinutes = (int) ($task->manual_actual_duration_minutes ?? 0);
        $aggregate = $timerMinutes + $manualMinutes;

        $task->forceFill([
            'actual_duration_minutes' => $aggregate === 0
                && $task->manual_actual_duration_minutes === null
                ? null
                : $aggregate,
        ])->save();

        return $task->fresh();
    }

    public function duplicateBranch(Task $source, array $options, int $userId): Task
    {
        $allTasks = Task::query()->with(['assignees:id', 'blockingTasks:id'])->get();
        $children = $allTasks->groupBy('parent_task_id');
        $sourceIds = $this->descendantIds($source->id, $children);
        $sourceTasks = $allTasks->whereIn('id', $sourceIds)->keyBy('id');
        $idMap = [];
        $baseDate = $this->taskAnchor($source);
        $targetStart = filled($options['target_start_date'] ?? null)
            ? Carbon::parse($options['target_start_date'])
            : null;

        return DB::transaction(function () use (
            $source,
            $sourceIds,
            $sourceTasks,
            $options,
            $userId,
            $baseDate,
            $targetStart,
            &$idMap
        ): Task {
            foreach ($sourceIds as $sourceId) {
                $original = $sourceTasks->get($sourceId);
                $copy = $original->replicate([
                    'prepared_by_user_id',
                    'actual_duration_minutes',
                    'manual_actual_duration_minutes',
                    'completed_at',
                    'cancelled_at',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]);

                $copy->parent_task_id = $sourceId === $source->id
                    ? ($options['parent_task_id'] ?? null)
                    : ($idMap[$original->parent_task_id] ?? null);
                $copy->status = 'Open';
                $copy->completed_at = null;
                $copy->cancelled_at = null;
                $copy->actual_duration_minutes = null;
                $copy->manual_actual_duration_minutes = null;
                $copy->prepared_by_user_id = $userId;

                foreach (['company_id', 'contact_id', 'deal_id', 'project_id'] as $field) {
                    if (array_key_exists($field, $options)) {
                        $copy->{$field} = $options[$field];
                    }
                }

                if ($targetStart && $baseDate) {
                    $copy->start_date = $this->shiftDate($original->start_date, $baseDate, $targetStart);
                    $copy->due_date = $this->shiftDate($original->due_date, $baseDate, $targetStart, 'Y-m-d H:i:s');
                }

                $copy->save();
                $idMap[$sourceId] = $copy->id;

                $assigneeIds = array_key_exists('assigned_user_ids', $options)
                    ? collect($options['assigned_user_ids'])
                    : $original->assignees->pluck('id');
                $copy->assignees()->sync($assigneeIds->mapWithKeys(fn ($id): array => [(int) $id => ['role' => 'assignee']])->all());
                $copy->assigned_to_user_id = $assigneeIds->first();
                $copy->save();
            }

            foreach ($sourceIds as $sourceId) {
                $original = $sourceTasks->get($sourceId);
                $mappedBlockers = $original->blockingTasks->pluck('id')
                    ->filter(fn ($id): bool => isset($idMap[$id]))
                    ->mapWithKeys(fn ($id): array => [$idMap[$id] => ['dependency_type' => 'BLOCKS', 'notes' => null]])
                    ->all();
                $sourceTasks->get($sourceId);
                Task::query()->findOrFail($idMap[$sourceId])->blockingTasks()->sync($mappedBlockers);
            }

            return Task::query()->findOrFail($idMap[$source->id]);
        });
    }

    public function saveAsTemplate(Task $source, int $userId): TaskTemplate
    {
        $allTasks = Task::query()->get();
        $children = $allTasks->groupBy('parent_task_id');
        $sourceIds = $this->descendantIds($source->id, $children);
        $tasks = $allTasks->whereIn('id', $sourceIds)->keyBy('id');
        $templateMap = [];
        $baseDate = $this->taskAnchor($source);

        return DB::transaction(function () use ($source, $sourceIds, $tasks, $userId, $baseDate, &$templateMap): TaskTemplate {
            foreach ($sourceIds as $taskId) {
                $task = $tasks->get($taskId);
                $template = TaskTemplate::query()->create([
                    'title' => $task->name,
                    'description' => $task->description,
                    'parent_template_id' => $taskId === $source->id ? null : ($templateMap[$task->parent_task_id] ?? null),
                    'priority' => strtoupper((string) ($task->priority ?: 'NORMAL')),
                    'estimated_duration_minutes' => $task->estimated_duration_minutes,
                    'relative_start_days' => $this->relativeDays($task->start_date, $baseDate),
                    'relative_due_days' => $this->relativeDays($task->due_date, $baseDate),
                    'instructions' => $task->description,
                    'status' => 'ACTIVE',
                    'created_by_user_id' => $userId,
                ]);
                $templateMap[$taskId] = $template->id;
            }

            return TaskTemplate::query()->findOrFail($templateMap[$source->id]);
        });
    }

    public function instantiateTemplate(TaskTemplate $root, array $options, int $userId): Task
    {
        $templates = TaskTemplate::query()->where('status', 'ACTIVE')->get();
        $children = $templates->groupBy('parent_template_id');
        $ids = $this->descendantIds($root->id, $children);
        $templates = $templates->whereIn('id', $ids)->keyBy('id');
        $taskMap = [];
        $start = Carbon::parse($options['start_date'] ?? now()->toDateString());

        return DB::transaction(function () use ($root, $ids, $templates, $options, $userId, $start, &$taskMap): Task {
            foreach ($ids as $templateId) {
                $template = $templates->get($templateId);
                $task = Task::query()->create([
                    'name' => $template->title,
                    'description' => $template->instructions ?: $template->description,
                    'parent_task_id' => $templateId === $root->id ? ($options['parent_task_id'] ?? null) : ($taskMap[$template->parent_template_id] ?? null),
                    'sort_order' => 0,
                    'start_date' => $template->relative_start_days === null ? null : $start->copy()->addDays($template->relative_start_days),
                    'due_date' => $template->relative_due_days === null ? null : $start->copy()->addDays($template->relative_due_days)->format('Y-m-d'),
                    'estimated_duration_minutes' => $template->estimated_duration_minutes,
                    'priority' => ucfirst(strtolower($template->priority ?: 'NORMAL')),
                    'status' => 'Open',
                    'task_template_id' => $template->id,
                    'company_id' => $options['company_id'] ?? null,
                    'contact_id' => $options['contact_id'] ?? null,
                    'deal_id' => $options['deal_id'] ?? null,
                    'project_id' => $options['project_id'] ?? null,
                    'assigned_to_user_id' => collect($options['assigned_user_ids'] ?? [])->first(),
                    'prepared_by_user_id' => $userId,
                ]);
                $task->assignees()->sync(collect($options['assigned_user_ids'] ?? [])->mapWithKeys(
                    fn ($id): array => [(int) $id => ['role' => 'assignee']]
                )->all());
                $taskMap[$templateId] = $task->id;
            }

            return Task::query()->findOrFail($taskMap[$root->id]);
        });
    }

    public function stateFlags(Task $task): array
    {
        try {
            $due = filled($task->due_date) ? Carbon::parse($task->due_date) : null;
        } catch (\Throwable) {
            $due = null;
        }
        $terminal = in_array(strtolower((string) $task->status), ['completed', 'cancelled'], true);
        $blocked = $task->blockingTasks->contains(
            fn (Task $blocker): bool => ! in_array(strtolower((string) $blocker->status), ['completed', 'cancelled'], true)
        );

        return [
            'blocked' => $blocked,
            'due_today' => ! $terminal && $due?->isToday() === true,
            'due_soon' => ! $terminal && $due && now()->lte($due) && $due->lte(now()->addDays(3)),
            'overdue' => ! $terminal && $due?->isPast() === true && ! $due?->isToday(),
            'stale' => ! $terminal && $task->updated_at?->lt(now()->subDays(7)) === true,
            'no_deadline' => blank($task->due_date),
            'unassigned' => ! $task->assigned_to_user_id && $task->assignees->isEmpty(),
            'needs_review' => strtolower((string) $task->status) === 'for review',
        ];
    }

    public function branchIds(int $rootId): array
    {
        return $this->descendantIds(
            $rootId,
            Task::query()->get(['id', 'parent_task_id', 'sort_order'])->groupBy('parent_task_id')
        );
    }

    private function descendantIds(int $rootId, Collection $children): array
    {
        $ids = [];
        $queue = [$rootId];

        while ($queue !== []) {
            $id = array_shift($queue);
            $ids[] = $id;
            foreach ($children->get($id, collect())->sortBy(['sort_order', 'id']) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    private function dependsOn(int $taskId, int $targetId, Collection $blockersByDependent, array $seen = []): bool
    {
        if (isset($seen[$taskId])) {
            return false;
        }

        $seen[$taskId] = true;
        foreach ($blockersByDependent->get($taskId, []) as $blockerId) {
            if ($blockerId === $targetId || $this->dependsOn($blockerId, $targetId, $blockersByDependent, $seen)) {
                return true;
            }
        }

        return false;
    }

    private function taskAnchor(Task $task): ?Carbon
    {
        $value = $task->start_date ?: $task->due_date;

        return filled($value) ? Carbon::parse($value) : null;
    }

    private function shiftDate($value, Carbon $base, Carbon $target, ?string $format = null)
    {
        if (blank($value)) {
            return null;
        }

        $shifted = $target->copy()->addSeconds($base->diffInSeconds(Carbon::parse($value), false));

        return $format ? $shifted->format($format) : $shifted;
    }

    private function relativeDays($value, ?Carbon $base): ?int
    {
        return blank($value) || ! $base ? null : $base->diffInDays(Carbon::parse($value), false);
    }

    private function cycleError(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTimer;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;
use App\Services\TaskWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskWorkflowController extends Controller
{
    public function __construct(
        private readonly TaskWorkflowService $workflow,
        private readonly ActivityAuditService $audit
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['all', 'my', 'team'])],
            'status' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', Rule::in([
                'due_today', 'due_soon', 'overdue', 'upcoming', 'in_progress',
                'waiting', 'for_review', 'blocked', 'stale', 'no_deadline', 'unassigned', 'needs_review',
            ])],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = Task::query()->with([
            'assignees:id,name,email',
            'primaryAssignee:id,name,email',
            'blockingTasks:id,name,status',
            'subtasks:id,parent_task_id,status',
            'company:id,company_name',
            'preparedBy:id,name,email',
        ]);

        if (($validated['scope'] ?? 'all') === 'my') {
            $userId = $request->user()->id;
            $query->where(fn ($scope) => $scope
                ->where('assigned_to_user_id', $userId)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->where('users.id', $userId)));
        } elseif (($validated['scope'] ?? 'all') === 'team') {
            $departmentId = $request->user()->employeeProfile?->department_id;
            abort_unless($departmentId, 403, 'A department is required for team task scope.');
            $query->where(function ($scope) use ($departmentId): void {
                $scope->whereHas('primaryAssignee.employeeProfile', fn ($employee) => $employee->where('department_id', $departmentId))
                    ->orWhereHas('assignees.employeeProfile', fn ($employee) => $employee->where('department_id', $departmentId));
            });
        }

        foreach (['company_id', 'assigned_to_user_id'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if (filled($validated['status'] ?? null)) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($validated['status'])]);
        }

        $tasks = $query->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (Task $task): array {
                $children = $task->subtasks;
                $completedChildren = $children->filter(fn (Task $child): bool => strtolower((string) $child->status) === 'completed')->count();

                return array_merge($task->toArray(), [
                    'system_flags' => $this->workflow->stateFlags($task),
                    'subtask_progress' => [
                        'completed' => $completedChildren,
                        'total' => $children->count(),
                        'percentage' => $children->isEmpty() ? null : round(($completedChildren / $children->count()) * 100, 2),
                    ],
                ]);
            });

        if (filled($validated['state'] ?? null)) {
            $state = $validated['state'];
            $tasks = $tasks->filter(function (array $task) use ($state): bool {
                return match ($state) {
                    'upcoming' => ! $task['system_flags']['overdue']
                        && ! $task['system_flags']['due_today']
                        && filled($task['due_date'])
                        && ! in_array(strtolower((string) $task['status']), ['completed', 'cancelled'], true),
                    'in_progress' => strtolower((string) $task['status']) === 'in progress',
                    'waiting' => in_array(strtolower((string) $task['status']), ['waiting', 'on hold', 'waiting/on hold'], true),
                    'for_review' => strtolower((string) $task['status']) === 'for review',
                    default => (bool) ($task['system_flags'][$state] ?? false),
                };
            })->values();
        }

        return response()->json(['tasks' => $tasks->values()]);
    }

    public function timer(Request $request, int $task, string $action)
    {
        $task = Task::query()->findOrFail($task);
        $this->authorizeTask($request, $task);

        $action = strtolower($action);
        abort_unless(in_array($action, ['start', 'pause', 'resume', 'stop'], true), 404);
        $validated = $request->validate(['notes' => ['nullable', 'string']]);

        $timer = DB::transaction(function () use ($request, $task, $action, $validated): TaskTimer {
            $timer = TaskTimer::query()
                ->where('task_id', $task->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('stopped_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($action === 'start') {
                if ($timer) {
                    throw ValidationException::withMessages(['timer' => ['This user already has an active timer for the task.']]);
                }

                return TaskTimer::query()->create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'started_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            if (! $timer) {
                throw ValidationException::withMessages(['timer' => ['No active timer exists for this user and task.']]);
            }

            if ($action === 'pause') {
                if ($timer->paused_at) {
                    throw ValidationException::withMessages(['timer' => ['The timer is already paused.']]);
                }
                $timer->update(['paused_at' => now()]);
            } elseif ($action === 'resume') {
                if (! $timer->paused_at) {
                    throw ValidationException::withMessages(['timer' => ['The timer is not paused.']]);
                }
                $timer->update([
                    'paused_seconds' => $timer->paused_seconds + (int) $timer->paused_at->diffInSeconds(now()),
                    'paused_at' => null,
                ]);
            } else {
                $pausedSeconds = $timer->paused_seconds;
                if ($timer->paused_at) {
                    $pausedSeconds += (int) $timer->paused_at->diffInSeconds(now());
                }
                $timer->update([
                    'stopped_at' => now(),
                    'paused_at' => null,
                    'paused_seconds' => $pausedSeconds,
                    'notes' => $validated['notes'] ?? $timer->notes,
                ]);
                $task = $this->workflow->synchronizeActualDuration($task);
            }

            return $timer->fresh();
        });

        return response()->json([
            'timer' => $timer->load('user:id,name,email'),
            'task' => $task->fresh(['timers']),
        ], $action === 'start' ? 201 : 200);
    }

    public function duplicate(Request $request, int $task)
    {
        $task = Task::query()->findOrFail($task);
        $this->authorizeTaskBranch($request, $task);

        $validated = $request->validate([
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'target_start_date' => ['nullable', 'date'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
                if (! empty($validated['parent_task_id'])) {
            $parentTask = Task::query()->findOrFail($validated['parent_task_id']);
            $this->authorizeTask($request, $parentTask);
        }

        $this->workflow->assertValidParent(null, $validated['parent_task_id'] ?? null);
        $copy = $this->workflow->duplicateBranch($task, $validated, $request->user()->id);
        $this->audit->record(ActivityTypeRegistry::TASK, $copy->id, 'task_branch_duplicated', [], [
            'source_task_id' => $task->id,
        ]);

        return response()->json($copy->load(['subtasks', 'assignees:id,name,email']), 201);
    }

    public function saveTemplate(Request $request, int $task)
    {
        $task = Task::query()->findOrFail($task);
        $this->authorizeTaskBranch($request, $task);

        $template = $this->workflow->saveAsTemplate($task, $request->user()->id);
        $this->audit->record(ActivityTypeRegistry::TASK, $task->id, 'task_template_created', [], [
            'task_template_id' => $template->id,
        ]);

        return response()->json($template, 201);
    }

    public function templates()
    {
        return response()->json([
            'templates' => TaskTemplate::query()
                ->with('childTemplates')
                ->whereNull('parent_template_id')
                ->where('status', 'ACTIVE')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function instantiateTemplate(Request $request, int $template)
    {
        $template = TaskTemplate::query()->findOrFail($template);
        abort_if($template->status !== 'ACTIVE', 422, 'Only active templates may be instantiated.');
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        if (! empty($validated['parent_task_id'])) {
            $parentTask = Task::query()->findOrFail($validated['parent_task_id']);
            $this->authorizeTask($request, $parentTask);
        }
       $task = $this->workflow->instantiateTemplate($template, $validated, $request->user()->id);
        $this->audit->record(ActivityTypeRegistry::TASK, $task->id, 'task_created_from_template', [], [
            'task_template_id' => $template->id,
        ]);

        return response()->json($task->load('subtasks'), 201);
    }

    public function outline(Request $request)
    {
        $validated = $request->validate([
            'outline' => ['required', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $items = collect(preg_split('/\R/', $validated['outline']) ?: [])
            ->map(function (string $line): ?array {
                if (trim($line) === '') {
                    return null;
                }
                preg_match('/^[\t ]*/', $line, $match);
                $indent = str_replace("\t", '  ', $match[0] ?? '');

                return ['name' => trim($line), 'depth' => intdiv(strlen($indent), 2)];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['outline' => ['The outline must contain at least one task.']]);
        }

        $created = DB::transaction(function () use ($items, $validated, $request) {
            $parents = [];
            $tasks = collect();
            foreach ($items as $position => $item) {
                $depth = min((int) $item['depth'], count($parents));
                $parentId = $depth > 0 ? ($parents[$depth - 1] ?? null) : null;
                $task = Task::query()->create([
                    'name' => $item['name'],
                    'parent_task_id' => $parentId,
                    'sort_order' => $position,
                    'status' => 'Open',
                    'priority' => 'Normal',
                    'company_id' => $validated['company_id'] ?? null,
                    'contact_id' => $validated['contact_id'] ?? null,
                    'deal_id' => $validated['deal_id'] ?? null,
                    'project_id' => $validated['project_id'] ?? null,
                    'assigned_to_user_id' => collect($validated['assigned_user_ids'] ?? [])->first(),
                    'prepared_by_user_id' => $request->user()->id,
                ]);
                $task->assignees()->sync(collect($validated['assigned_user_ids'] ?? [])->mapWithKeys(
                    fn ($id): array => [(int) $id => ['role' => 'assignee']]
                )->all());
                $parents[$depth] = $task->id;
                $parents = array_slice($parents, 0, $depth + 1, true);
                $tasks->push($task);
            }

            return $tasks;
        });

        return response()->json(['tasks' => $created], 201);
    }

    public function reorder(Request $request)
{
    $validated = $request->validate([
        'items' => ['required', 'array', 'min:1'],
        'items.*.id' => ['required', 'integer', 'distinct', 'exists:tasks,id'],
        'items.*.parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        'items.*.sort_order' => ['required', 'integer', 'min:0'],
    ]);

    DB::transaction(function () use ($validated, $request): void {
        foreach ($validated['items'] as $item) {
            $task = Task::query()->findOrFail($item['id']);
            $this->authorizeTask($request, $task);

            if (! empty($item['parent_task_id'])) {
                $parentTask = Task::query()->findOrFail($item['parent_task_id']);
            $this->authorizeTask($request, $parentTask);
            }

            $this->workflow->assertValidParent(
                (int) $item['id'],
                $item['parent_task_id'] ?? null
            );

            $task->update([
                'parent_task_id' => $item['parent_task_id'] ?? null,
                'sort_order' => $item['sort_order'],
            ]);
        }
    });

    return response()->json([
        'tasks' => Task::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(),
    ]);
}
public function bulk(Request $request)
{
    $validated = $request->validate([
        'task_ids' => ['required', 'array', 'min:1'],
        'task_ids.*' => ['integer', 'distinct', 'exists:tasks,id'],
        'action' => ['required', Rule::in(['update', 'archive'])],
        'changes' => ['nullable', 'array'],
        'changes.status' => ['nullable', Rule::in(['Open', 'In Progress', 'Waiting/On Hold', 'For Review', 'Completed', 'Cancelled'])],
        'changes.priority' => ['nullable', Rule::in(['Low', 'Normal', 'High', 'Urgent'])],
        'changes.company_id' => ['nullable', 'integer', 'exists:companies,id'],
        'changes.contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        'changes.deal_id' => ['nullable', 'integer', 'exists:deals,id'],
        'changes.project_id' => ['nullable', 'integer', 'exists:projects,id'],
        'changes.start_date' => ['nullable', 'date'],
        'changes.due_date' => ['nullable', 'date'],
        'changes.estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
        'changes.reminder_type' => ['nullable', 'string', 'max:40'],
        'changes.reminder_value' => ['nullable', 'integer', 'min:0'],
        'changes.assigned_user_ids' => ['nullable', 'array'],
        'changes.assigned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
    ]);

    $affected = DB::transaction(function () use ($validated, $request): int {
        $tasks = Task::query()
            ->whereIn('id', $validated['task_ids'])
            ->get();

        foreach ($tasks as $task) {
            $this->authorizeTask($request, $task);
        }

        if ($validated['action'] === 'archive') {
            $archiveIds = $tasks->flatMap(
                fn (Task $task): array => $this->workflow->branchIds($task->id)
            )->unique()->values();

            $tasks = Task::query()
                ->whereIn('id', $archiveIds)
                ->get();

            foreach ($tasks as $task) {
                $this->authorizeTask($request, $task);
            }

            foreach ($tasks as $task) {
                $task->delete();
            }

            return $tasks->count();
        }

        $changes = $validated['changes'] ?? [];
        $assigneeIds = collect($changes['assigned_user_ids'] ?? []);
        unset($changes['assigned_user_ids']);

        foreach ($tasks as $task) {
            if (($changes['status'] ?? null) === 'Completed') {
                $changes['completed_at'] = now();
            } elseif (array_key_exists('status', $changes)) {
                $changes['completed_at'] = null;
            }

            if (isset($validated['changes']['assigned_user_ids'])) {
                $changes['assigned_to_user_id'] = $assigneeIds->first();
            }

            $task->update($changes);

            if (isset($validated['changes']['assigned_user_ids'])) {
                $task->assignees()->sync(
                    $assigneeIds->mapWithKeys(
                        fn ($id): array => [
                            (int) $id => ['role' => 'assignee']
                        ]
                    )->all()
                );

                $this->audit->record(
                    ActivityTypeRegistry::TASK,
                    $task->id,
                    'assignment_changed',
                    [],
                    ['assigned_user_ids' => $assigneeIds->all()]
                );
            }
        }

        return $tasks->count();
    });

    return response()->json(['affected' => $affected]);
}
    private function authorizeTaskBranch(Request $request, Task $root): void
    {
        $taskIds = $this->workflow->branchIds($root->id);

        $tasks = Task::query()
            ->whereIn('id', $taskIds)
            ->get();

        abort_unless($tasks->count() === count($taskIds), 404);

        foreach ($tasks as $task) {
            $this->authorizeTask($request, $task);
        }
    }
private function authorizeTask(Request $request, Task $task): void
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || (int) $task->prepared_by_user_id === (int) $user->id
                || (int) $task->assigned_to_user_id === (int) $user->id
                || (string) $task->owner === (string) $user->email
                || $task->assignees()
                    ->where('users.id', $user->id)
                    ->exists()
            ),
            403
        );
    }
}

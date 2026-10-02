<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityAuditService;
use App\Services\ActivityLinkService;
use App\Services\ActivityTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MeetingWorkspaceController extends Controller
{
    public function __construct(
        private readonly ActivityAuditService $audit,
        private readonly ActivityLinkService $links
    ) {}

    public function show(Request $request, int $meeting)
    {
        $meeting = Meeting::query()->findOrFail($meeting);
        $this->authorizeMeeting($request, $meeting);

        return response()->json($this->workspacePayload($meeting));
    }

    public function update(Request $request, int $meeting)
    {
        $meeting = Meeting::query()->findOrFail($meeting);
        $this->authorizeMeeting($request, $meeting);

        $validated = $request->validate([
            'internal_participant_user_ids' => ['sometimes', 'nullable', 'array'],
            'internal_participant_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'external_attendees' => ['sometimes', 'nullable', 'array'],
            'external_attendees.*.name' => ['required_with:external_attendees', 'string', 'max:255'],
            'external_attendees.*.email' => ['nullable', 'email', 'max:255'],
            'external_attendees.*.organization' => ['nullable', 'string', 'max:255'],
            'structured_minutes' => ['sometimes', 'nullable', 'array'],
            'recording_metadata' => ['sometimes', 'nullable', 'array'],
            'transcript_metadata' => ['sometimes', 'nullable', 'array'],
            'files_metadata' => ['sometimes', 'nullable', 'array'],
            'finalize_minutes' => ['sometimes', 'boolean'],
        ]);

        $before = $meeting->only([
            'internal_participant_user_ids',
            'external_attendees',
            'structured_minutes',
            'recording_metadata',
            'transcript_metadata',
            'files_metadata',
            'finalized_minutes_at',
            'finalized_minutes_by_user_id',
        ]);

        $meeting->fill(collect($validated)->except('finalize_minutes')->all());

        if (($validated['finalize_minutes'] ?? false) && ! $meeting->finalized_minutes_at) {
            $meeting->finalized_minutes_at = now();
            $meeting->finalized_minutes_by_user_id = $request->user()->id;
        }

        $meeting->save();

        if (array_key_exists('structured_minutes', $validated)) {
            $meeting->minutesVersions()->create([
                'version_number' => ((int) $meeting->minutesVersions()->max('version_number')) + 1,
                'content' => $meeting->structured_minutes ?? [],
                'status' => ($validated['finalize_minutes'] ?? false) ? 'FINAL' : 'DRAFT',
                'created_by_user_id' => $request->user()->id,
            ]);
        }

        $this->audit->record(
            ActivityTypeRegistry::MEETING,
            $meeting->id,
            ($validated['finalize_minutes'] ?? false) ? 'minutes_finalized' : 'meeting_workspace_updated',
            ['before' => $before, 'after' => $meeting->only(array_keys($before))]
        );

        return response()->json($this->workspacePayload($meeting->fresh()));
    }

    public function storeActionItem(Request $request, int $meeting)
    {
        $meeting = Meeting::query()->findOrFail($meeting);
        $this->authorizeMeeting($request, $meeting);
        $validated = $request->validate($this->actionItemRules());
        $actionItem = $meeting->actionItems()->create($validated + [
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->audit->record(ActivityTypeRegistry::MEETING, $meeting->id, 'action_item_created', [], [
            'action_item_id' => $actionItem->id,
        ]);

        return response()->json($this->loadActionItem($actionItem), 201);
    }

    public function updateActionItem(
        Request $request,
        int $meeting,
        int $actionItem
    ) {
        $meeting = Meeting::query()->findOrFail($meeting);
        $this->authorizeMeeting(request(), $meeting);
        $actionItem = MeetingActionItem::query()->findOrFail($actionItem);
        $this->ensureMeetingActionItem($meeting, $actionItem);
        $before = $actionItem->only(['description', 'assigned_to_user_id', 'due_date', 'status']);
        $actionItem->update($request->validate($this->actionItemRules(true)));

        $this->audit->record(ActivityTypeRegistry::MEETING, $meeting->id, 'action_item_updated', [
            'before' => $before,
            'after' => $actionItem->only(array_keys($before)),
        ], ['action_item_id' => $actionItem->id]);

        return response()->json($this->loadActionItem($actionItem));
    }

    public function destroyActionItem(Request $request, int $meeting, int $actionItem)
    {
        $meeting = Meeting::query()->findOrFail($meeting);
        $this->authorizeMeeting($request, $meeting);
        $actionItem = MeetingActionItem::query()->findOrFail($actionItem);
        $this->ensureMeetingActionItem($meeting, $actionItem);
        $actionItemId = $actionItem->id;
        $actionItem->delete();

        $this->audit->record(ActivityTypeRegistry::MEETING, $meeting->id, 'action_item_deleted', [], [
            'action_item_id' => $actionItemId,
        ]);

        return response()->json(null, 204);
    }

    public function convertActionItem(
        Request $request,
        int $meeting,
        int $actionItem
    ) {
        $meeting = Meeting::query()->findOrFail($meeting);
        $this->authorizeMeeting($request, $meeting);
        $actionItem = MeetingActionItem::query()->findOrFail($actionItem);
        $this->ensureMeetingActionItem($meeting, $actionItem);

        if ($actionItem->task_id) {
            return response()->json($this->loadActionItem($actionItem));
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string'],
        ]);

        $actionItem = DB::transaction(function () use ($request, $meeting, $actionItem, $validated) {
            $task = Task::query()->create([
                'name' => $validated['name'] ?? $actionItem->description,
                'description' => $validated['description'] ?? 'Created from meeting action item #'.$actionItem->id,
                'due_date' => $actionItem->due_date?->format('Y-m-d'),
                'status' => 'Open',
                'priority' => $validated['priority'] ?? 'Normal',
                'assigned_to_user_id' => $actionItem->assigned_to_user_id,
                'owner' => $actionItem->assignedTo?->email,
                'company_id' => $meeting->company_id,
                'contact_id' => $meeting->contact_id,
                'deal_id' => $meeting->deal_id,
                'project_id' => $meeting->project_id,
                'related_to' => $meeting->related_to ?: $meeting->title,
            ]);
            $task->prepared_by_user_id = $request->user()->id;
            $task->save();

            if ($actionItem->assigned_to_user_id && Schema::hasTable('task_assignments')) {
                $task->assignees()->syncWithoutDetaching([
                    $actionItem->assigned_to_user_id => ['role' => 'assignee'],
                ]);
            }

            $actionItem->update(['task_id' => $task->id]);
            $this->links->create(
                ActivityTypeRegistry::MEETING,
                $meeting->id,
                ActivityTypeRegistry::TASK,
                $task->id,
                $request->user()->id
            );

            $this->audit->record(ActivityTypeRegistry::MEETING, $meeting->id, 'action_item_converted_to_task', [], [
                'action_item_id' => $actionItem->id,
                'task_id' => $task->id,
            ]);

            return $actionItem;
        });

        return response()->json($this->loadActionItem($actionItem), 201);
    }

    private function actionItemRules(bool $update = false): array
    {
        $presence = $update ? 'sometimes' : 'required';

        return [
            'description' => [$presence, 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:40'],
        ];
    }

    private function workspacePayload(Meeting $meeting): array
    {
        $meeting->load([
            'finalizedMinutesBy:id,name,email',
            'actionItems.assignedTo:id,name,email',
            'actionItems.task:id,name,status,due_date',
            'actionItems.createdBy:id,name,email',
            'minutesVersions.createdBy:id,name,email',
        ]);

        return [
            'meeting_id' => $meeting->id,
            'internal_participant_user_ids' => $meeting->internal_participant_user_ids ?? [],
            'internal_participants' => User::query()
                ->whereIn('id', $meeting->internal_participant_user_ids ?? [])
                ->get(['id', 'name', 'email']),
            'external_attendees' => $meeting->external_attendees ?? [],
            'structured_minutes' => $meeting->structured_minutes,
            'finalized_minutes_at' => $meeting->finalized_minutes_at?->toIso8601String(),
            'finalized_minutes_by' => $meeting->finalizedMinutesBy,
            'recording_metadata' => $meeting->recording_metadata ?? [],
            'transcript_metadata' => $meeting->transcript_metadata ?? [],
            'files_metadata' => $meeting->files_metadata ?? [],
            'action_items' => $meeting->actionItems,
            'minutes_versions' => $meeting->minutesVersions,
            'calendar' => [
                'provider' => $meeting->calendar_provider,
                'external_event_id' => $meeting->external_calendar_event_id,
                'conference_url' => $meeting->conference_url,
                'sync_status' => $meeting->calendar_sync_status,
                'synced_at' => $meeting->calendar_synced_at?->toIso8601String(),
                'sync_error' => $meeting->calendar_sync_error,
            ],
        ];
    }

    private function loadActionItem(MeetingActionItem $actionItem): MeetingActionItem
    {
        return $actionItem->fresh()->load([
            'assignedTo:id,name,email',
            'task:id,name,status,due_date',
            'createdBy:id,name,email',
        ]);
    }

    private function ensureMeetingActionItem(Meeting $meeting, MeetingActionItem $actionItem): void
    {
        abort_unless($actionItem->meeting_id === $meeting->id, 404);
    }

    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isSuperAdmin()
            || (int) $meeting->prepared_by_user_id === (int) $user->id
            || (string) $meeting->owner === (string) $user->email), 403);
    }
}

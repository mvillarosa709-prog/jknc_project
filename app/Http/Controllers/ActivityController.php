<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Event;
use App\Models\Employee;
use App\Models\EmailActivity;
use App\Models\EmailMailboxConnection;
use App\Models\Call;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Meeting;
use App\Models\MessageConversation;
use App\Services\OfficialBusinessActivityService;
use App\Services\ActivityAttachmentService;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;
use App\Services\EventCalendarSyncService;
use App\Services\MeetingCalendarSyncService;
use App\Services\TaskCalendarSyncService;
use App\Services\TaskWorkflowService;
use App\Support\ActivityDuplicateGuard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

use App\Models\Note;

class ActivityController extends Controller
{
    public function index(OfficialBusinessActivityService $officialBusinessActivities)
    {
        $currentUser = auth()->user();
        $currentEmployee = $currentUser?->employeeProfile;

        $currentDepartmentId = $currentEmployee?->department_id;

        $teamUsers = collect();

        if ($currentDepartmentId) {
            $teamUsers = Employee::query()
                ->where('department_id', $currentDepartmentId)
                ->with('user:id,email')
                ->get()
                ->map(function ($employee) {
                    return $employee->user?->email
                        ?: $employee->company_email
                        ?: $employee->work_email
                        ?: $employee->email;
                })
                ->filter()
                ->unique()
                ->values();
        }

        $tasks = Task::with([
            'notes',
            'assignees:id,name,email',
            'primaryAssignee:id,name,email',
            'parentTask:id,name',
            'subtasks:id,parent_task_id,sort_order,name,status,estimated_duration_minutes,actual_duration_minutes',
            'timers.user:id,name,email',
            'blockingTasks:id,name',
            'company:id,company_name,email',
            'contactRecord:id,first_name,last_name,email,phone',
            'deal:id,deal_code,deal_name',
            'project:id,project_code,name',
            'preparedBy:id,name,email',
        ])->latest()->get();

        $events = Event::with([
            'notes',
            'company:id,company_name,email',
            'contactRecord:id,first_name,last_name,email,phone',
            'deal:id,deal_code,deal_name',
            'project:id,project_code,name',
            'preparedBy:id,name,email',
        ])->latest()->get();

        $calls = Call::with([
            'notes',
            'company:id,company_name,email',
            'contactRecord:id,first_name,last_name,email,phone',
            'deal:id,deal_code,deal_name',
            'project:id,project_code,name',
            'preparedBy:id,name,email',
        ])->latest()->get();

        $meetings = Meeting::with([
            'notes',
            'company:id,company_name,email',
            'contactRecord:id,first_name,last_name,email,phone',
            'deal:id,deal_code,deal_name',
            'project:id,project_code,name',
            'preparedBy:id,name,email',
        ])->latest()->get();

        $emails = ! Schema::hasTable('email_activities') || ! $currentUser
            ? collect()
            : EmailActivity::query()
                ->visibleTo($currentUser)
                ->with([
                    'company:id,company_name,email',
                    'contactRecord:id,first_name,last_name,email',
                    'mailboxOwner:id,name,email',
                    'preparedBy:id,name,email',
                    'mailboxConnection:id,provider,email_address,connection_status',
                ])
                ->latest('occurred_at')
                ->latest('id')
                ->get();

        $messages = MessageConversation::query()
            ->when(
                $currentUser && ! $currentUser->isAdmin() && ! $currentUser->isSuperAdmin(),
                function ($query) use ($currentUser): void {
                    $query->where(function ($scope) use ($currentUser): void {
                        $scope->where('assigned_user_id', $currentUser->id)
                            ->orWhere('prepared_by_user_id', $currentUser->id);
                    });
                }
            )
            ->with([
                'company:id,company_name,email',
                'contact:id,first_name,last_name,email,phone',
                'assignedUser:id,name,email',
                'preparedBy:id,name,email',
                'latestEntry',
            ])
            ->withCount('entries')
            ->latest('last_message_at')
            ->latest('id')
            ->get();

        $officialBusinessTrips = $currentUser
            ? $officialBusinessActivities->context($currentUser)['records']
            : collect();

        // Keep the response compatible without allowing a GET request to write.
        $meetings->each(function (Meeting $meeting): void {
            if ($meeting->status === 'upcoming' && $this->meetingHasEnded($meeting)) {
                $meeting->setAttribute('status', 'completed');
            }
        });

        return response()->json([
            'tasks' => $tasks,
            'events' => $events,
            'calls' => $calls,
            'meetings' => $meetings,
            'emails' => $emails,
            'emailMailboxes' => $currentUser
                && Schema::hasTable('email_mailbox_connections')
                && ( $currentUser->isAdmin() || $currentUser->isSuperAdmin() || (Schema::hasTable('user_permissions') && $currentUser->hasPermission('access_activities')) )
                ? EmailMailboxConnection::query()->where('provider', 'gmail')->where('user_id', $currentUser->getKey())->get(['id', 'email_address', 'provider', 'connection_status', 'last_synced_at', 'last_error_at', 'sync_status', 'initial_sync_status', 'initial_sync_count'])
                : [],
            'messages' => $messages,
            'activities' => $this->unifiedActivities(
                $tasks,
                $events,
                $calls,
                $meetings,
                $emails,
                $messages,
                $officialBusinessTrips,
                $officialBusinessActivities
            ),
            'users' => User::pluck('email'),
            'taskUsers' => User::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'currentUser' => $currentUser?->email,
            'currentUserId' => $currentUser?->id,
            'currentDepartmentId' => $currentDepartmentId,
            'teamUsers' => $teamUsers,
            'relatedOptions' => $this->relatedOptions()->pluck('label')->all(),
            'relatedRecords' => $this->relatedOptions()->values()->all(),
            'contacts' => Contact::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn ($contact) => [
                    'id' => $contact->id,
                    'name' => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: ($contact->company_name ?? $contact->email ?? 'Contact #' . $contact->id),
                    'company_ids' => \App\Support\ActivityRelationships::companyIds($contact),
                    'company_name' => $contact->company_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'label' => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) . (($contact->company_name ?? '') ? ' - ' . $contact->company_name : ''),
                ])
                ->values(),
        ]);
    }

    private function unifiedActivities(
        $tasks,
        $events,
        $calls,
        $meetings,
        $emails,
        $messages,
        $officialBusinessTrips,
        OfficialBusinessActivityService $officialBusinessActivities
    ): array
    {
        return collect()
            ->merge($tasks->map(fn (Task $task): array => $this->serializeTaskActivity($task)))
            ->merge($events->map(fn (Event $event): array => $this->serializeEventActivity($event)))
            ->merge($calls->map(fn (Call $call): array => $this->serializeCallActivity($call)))
            ->merge($meetings->map(fn (Meeting $meeting): array => $this->serializeMeetingActivity($meeting)))
            ->merge($emails->map(fn (EmailActivity $email): array => $this->serializeEmailActivity($email)))
            ->merge($messages->map(fn (MessageConversation $message): array => $this->serializeMessageActivity($message)))
            ->merge($officialBusinessTrips->map(function ($trip) use ($officialBusinessActivities): array {
                $activity = $officialBusinessActivities->toUnifiedActivity($trip);
                $normalized = collect(app(ActivityAttachmentService::class)->forActivity(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $trip
                ))->reject(fn (array $attachment): bool => (bool) ($attachment['is_legacy'] ?? false));
                $activity['attachments'] = collect($activity['attachments'])->merge($normalized)->values()->all();

                return $activity;
            }))
            ->sortByDesc(fn (array $activity): string => $activity['occurred_at'] ?? '')
            ->values()
            ->all();
    }

    private function serializeTaskActivity(Task $task): array
    {
        [$duration, $durationSource] = $this->preferredDuration(
            $task->actual_duration_minutes,
            $task->estimated_duration_minutes,
            'actual',
            'estimated'
        );

        return $this->activityPayload(
            'task',
            $task,
            $task->name,
            $task->start_date ?: $task->due_date ?: $task->created_at,
            null,
            $duration,
            $durationSource,
            $task->status,
            $task->primaryAssignee?->name ?: $task->primaryAssignee?->email ?: $task->owner
        );
    }

    private function serializeEventActivity(Event $event): array
    {
        [$duration, $durationSource] = $this->preferredDuration(
            $event->duration_minutes ?? $this->hoursToMinutes($event->actual_hours),
            $this->hoursToMinutes($event->estimated_hours),
            'actual',
            'estimated'
        );

        return $this->activityPayload(
            'event',
            $event,
            $event->title,
            $event->from ?: $event->created_at,
            null,
            $duration,
            $durationSource,
            $event->status,
            $event->host,
            $event->related_account
        );
    }

    private function serializeCallActivity(Call $call): array
    {
        $duration = $call->duration_minutes ?? $this->durationToMinutes($call->duration);

        return $this->activityPayload(
            'call',
            $call,
            $call->purpose ?: $call->contact ?: 'Call',
            $call->start_time ?: $call->created_at,
            $call->start_time ? $call->start_hour : null,
            $duration,
            $duration === null ? null : 'recorded',
            $call->completed ? 'Completed' : 'Open',
            $call->owner ?: $call->from
        );
    }

    private function serializeMeetingActivity(Meeting $meeting): array
    {
        [$duration, $durationSource] = $this->preferredDuration(
            $meeting->duration_minutes ?? $this->durationToMinutes($meeting->actual_duration),
            $this->durationToMinutes($meeting->duration),
            'actual',
            'scheduled'
        );

        return $this->activityPayload(
            'meeting',
            $meeting,
            $meeting->title,
            $meeting->date ?: $meeting->created_at,
            $meeting->date ? $meeting->time : null,
            $duration,
            $durationSource,
            $meeting->getRawOriginal('status'),
            $meeting->owner,
            $meeting->related_account,
            $meeting->status
        );
    }

    private function serializeMessageActivity(MessageConversation $message): array
    {
        $company = $message->company;
        $contact = $message->contact;

        $contactName = $contact
            ? trim(collect([
                $contact->first_name,
                $contact->last_name,
            ])->filter()->implode(' '))
            : null;

        $occurredAt = $message->last_message_at
            ?: $message->created_at;

        return [
            'id' => 'message-'.$message->id,
            'source_id' => $message->id,
            'type' => 'message',
            'title' => filled($message->conversation_title)
                ? $message->conversation_title
                : ucfirst((string) $message->channel).' Conversation #'.$message->id,

            'owner' => $message->assignedUser?->name
                ?: $message->assignedUser?->email,

            'account_id' => $message->company_id,
            'account' => $company?->company_name,

            'contact_id' => $message->contact_id,
            'contact' => filled($contactName) ? $contactName : null,

            'deal_id' => null,
            'deal' => null,
            'project_id' => null,
            'project' => null,

            'related_record' => $company?->company_name
                ?: $contactName,

            'source_status' => filled($message->handling)
                ? (string) $message->handling
                : null,

            'display_status' => filled($message->handling)
                ? (string) $message->handling
                : null,

            'occurred_at' => $this->activityDateTime($occurredAt),

            // Duration is optional. Do not invent duration from message entries.
            'duration_minutes' => $message->duration_minutes,
            'duration_source' => $message->duration_minutes === null ? null : 'recorded',

            'conversation_summary' => $message->conversation_summary,
            'direction' => $message->direction,
            'participant_contact_ids' => $message->participant_contact_ids ?? [],

            'prepared_by_user_id' => $message->prepared_by_user_id,
            'prepared_by' => $message->preparedBy,
            'client_report_description' => $message->client_report_description,
            'include_in_client_report' => (bool) $message->include_in_client_report,

            'attachments' => app(ActivityAttachmentService::class)->forActivity(
                ActivityTypeRegistry::MESSAGE,
                $message
            ),
            'created_at' => $message->created_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),

            // Source-specific metadata may be useful to API consumers.
            'channel' => $message->channel,
            'entry_count' => (int) $message->entries_count,
        ];
    }

    private function serializeEmailActivity(EmailActivity $email): array
    {
        $contact = $email->contactRecord;
        $contactName = $contact
            ? trim(collect([$contact->first_name, $contact->last_name])->filter()->implode(' '))
            : null;

        return [
            'id' => 'email-'.$email->id,
            'source_id' => $email->id,
            'type' => 'email',
            'title' => $email->subject ?: 'Email #'.$email->id,
            'owner' => $email->mailboxOwner?->name ?: $email->mailboxOwner?->email,
            'account_id' => $email->company_id,
            'account' => $email->company?->company_name,
            'contact_id' => $email->contact_id,
            'contact' => filled($contactName) ? $contactName : null,
            'deal_id' => null,
            'deal' => null,
            'project_id' => null,
            'project' => null,
            'related_record' => $email->company?->company_name ?: $contactName,
            'source_status' => $email->handling,
            'display_status' => $email->handling,
            'occurred_at' => $email->occurred_at?->toIso8601String(),
            'duration_minutes' => null,
            'duration_source' => null,
            'prepared_by_user_id' => $email->prepared_by_user_id,
            'prepared_by' => $email->preparedBy,
            'client_report_description' => $email->client_report_description,
            'include_in_client_report' => (bool) $email->include_in_client_report,
            'attachments' => app(ActivityAttachmentService::class)->forActivity(ActivityTypeRegistry::EMAIL, $email),
            'provider' => $email->provider,
            'mailbox_key' => $email->mailbox_key,
            'provider_message_id' => $email->provider_message_id,
            'sender_email' => $email->sender_email,
            'recipients' => $email->recipients ?? [],
            'cc_recipients' => $email->cc_recipients ?? [],
            'bcc_recipients' => $email->bcc_recipients ?? [],
            'direction' => $email->direction,
            'thread_id' => $email->thread_id,
            'delivery_status' => $email->delivery_status,
            'summary' => $email->summary,
            'body' => $email->body,
            'has_attachments' => (bool) $email->has_attachments,
            'sent_at' => $email->sent_at?->toIso8601String(),
            'created_at' => $email->created_at?->toIso8601String(),
            'updated_at' => $email->updated_at?->toIso8601String(),
        ];
    }
    private function activityPayload(
        string $type,
        $record,
        ?string $title,
        $date,
        ?string $time,
        ?int $durationMinutes,
        ?string $durationSource,
        $status,
        $owner,
        $fallbackAccount = null,
        $displayStatus = null
    ): array {
        $company = $record->company;
        $contact = $record->contactRecord;
        $account = $company?->company_name ?: $fallbackAccount;
        $contactName = $contact
            ? trim(collect([$contact->first_name, $contact->last_name])->filter()->implode(' '))
            : null;
        $sourceStatus = filled($status) ? (string) $status : null;
        $normalizedDisplayStatus = filled($displayStatus) ? (string) $displayStatus : $sourceStatus;
        $deal = $record->deal;
        $project = $record->project;

        return [
            'id' => $type.'-'.$record->id,
            'source_id' => $record->id,
            'type' => $type,
            'title' => filled($title) ? $title : ucfirst($type).' #'.$record->id,
            'owner' => filled($owner) ? (string) $owner : null,
            'account_id' => $record->company_id,
            'account' => filled($account) ? (string) $account : null,
            'contact_id' => $record->contact_id,
            'contact' => filled($contactName) ? $contactName : null,
            'deal_id' => $record->deal_id,
            'deal' => $deal ? [
                'id' => $deal->id,
                'code' => $deal->deal_code,
                'name' => $deal->deal_name,
            ] : null,
            'project_id' => $record->project_id,
            'project' => $project ? [
                'id' => $project->id,
                'code' => $project->project_code,
                'name' => $project->name,
            ] : null,
            'related_record' => $record->related_to ?: $account ?: $contactName,
            'source_status' => $sourceStatus,
            'display_status' => $normalizedDisplayStatus,
            'occurred_at' => $this->activityDateTime($date, $time),
            'duration_minutes' => $durationMinutes,
            'duration_source' => $durationSource,
            'prepared_by_user_id' => $record->prepared_by_user_id,
            'prepared_by' => $record->preparedBy,
            'client_report_description' => $record->client_report_description,
            'include_in_client_report' => (bool) $record->include_in_client_report,
            'attachments' => $this->activityAttachments($type, $record),
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    private function activityAttachments(string $type, $record): array
    {
        $legacy = match ($type) {
            'call' => filled($record->audio_path) ? [[
                'kind' => 'audio',
                'path' => $record->audio_path,
                'url' => $record->audio_url,
                'source_activity_type' => ActivityTypeRegistry::CALL,
                'source_activity_id' => $record->id,
                'filename' => basename((string) $record->audio_path),
                'original_filename' => basename((string) $record->audio_path),
                'storage_path' => $record->audio_path,
                'safe_url' => $record->audio_url,
                'mime_type' => 'audio/*',
                'size_bytes' => null,
                'uploaded_by_user_id' => null,
                'uploaded_by' => null,
                'uploaded_at' => null,
                'category' => 'audio',
                'is_legacy' => true,
            ]] : [],
            'meeting' => collect([
                filled($record->video_path) ? [
                    'kind' => 'video',
                    'path' => $record->video_path,
                    'url' => $record->video_url,
                    'source_activity_type' => ActivityTypeRegistry::MEETING,
                    'source_activity_id' => $record->id,
                    'filename' => basename((string) $record->video_path),
                    'original_filename' => basename((string) $record->video_path),
                    'storage_path' => $record->video_path,
                    'safe_url' => $record->video_url,
                    'mime_type' => 'video/*',
                    'size_bytes' => null,
                    'uploaded_by_user_id' => null,
                    'uploaded_by' => null,
                    'uploaded_at' => null,
                    'category' => 'recording',
                    'is_legacy' => true,
                ] : null,
                filled($record->audio_path) ? [
                    'kind' => 'audio',
                    'path' => $record->audio_path,
                    'url' => $record->audio_url,
                    'source_activity_type' => ActivityTypeRegistry::MEETING,
                    'source_activity_id' => $record->id,
                    'filename' => basename((string) $record->audio_path),
                    'original_filename' => basename((string) $record->audio_path),
                    'storage_path' => $record->audio_path,
                    'safe_url' => $record->audio_url,
                    'mime_type' => 'audio/*',
                    'size_bytes' => null,
                    'uploaded_by_user_id' => null,
                    'uploaded_by' => null,
                    'uploaded_at' => null,
                    'category' => 'recording',
                    'is_legacy' => true,
                ] : null,
            ])->filter()->values()->all(),
            default => [],
        };

        $normalized = collect(app(ActivityAttachmentService::class)->forActivity(strtoupper($type), $record))
            ->reject(fn (array $attachment): bool => (bool) ($attachment['is_legacy'] ?? false))
            ->map(fn (array $attachment): array => $attachment + [
                'kind' => $attachment['category'],
                'path' => $attachment['storage_path'],
                'url' => $attachment['safe_url'],
            ])
            ->all();

        return collect($legacy)->merge($normalized)->values()->all();
    }

    private function activityDateTime($date, ?string $time = null): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            $value = $date instanceof \DateTimeInterface
                ? Carbon::instance($date)
                : Carbon::parse(trim((string) $date).(filled($time) ? ' '.trim($time) : ''));

            return $value->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function hoursToMinutes($hours): ?int
    {
        return is_numeric($hours) ? (int) round(((float) $hours) * 60) : null;
    }

    private function durationToMinutes($duration): ?int
    {
        if (blank($duration)) {
            return null;
        }

        if (is_numeric($duration)) {
            return (int) round((float) $duration);
        }

        $value = Str::lower(trim((string) $duration));

        if (preg_match('/^(\d{1,3}):(\d{1,2})$/', $value, $clock)) {
            return ((int) $clock[1] * 60) + (int) $clock[2];
        }

        preg_match('/(\d+(?:\.\d+)?)\s*(?:h|hr|hrs|hour|hours)\b/', $value, $hours);
        preg_match('/(\d+(?:\.\d+)?)\s*(?:m|min|mins|minute|minutes)\b/', $value, $minutes);

        if ($hours || $minutes) {
            return (int) round(((float) ($hours[1] ?? 0) * 60) + (float) ($minutes[1] ?? 0));
        }

        return null;
    }

    private function preferredDuration($actual, $planned, string $actualSource, string $plannedSource): array
    {
        if ($actual !== null) {
            return [(int) $actual, $actualSource];
        }

        if ($planned !== null) {
            return [(int) $planned, $plannedSource];
        }

        return [$actual === null ? null : (int) $actual, $actual === null ? null : $actualSource];
    }

    private function relatedOptions()
    {
        $contacts = Contact::query()
            ->latest('updated_at')
            ->get()
            ->map(function (Contact $contact): array {
                $fullName = trim(collect([
                    $contact->first_name,
                    $contact->middle_name,
                    $contact->last_name,
                    $contact->name_extension,
                ])->filter()->implode(' '));

                $label = $fullName !== '' ? $fullName : ($contact->email ?: $contact->phone ?: 'Contact #'.$contact->id);
                $metaParts = array_values(array_filter([
                    $contact->email,
                    $contact->phone,
                ]));

                return [
                    'key' => 'contact-'.$contact->id,
                    'type' => 'contact',
                    'id' => $contact->id,
                    'label' => $label,
                    'meta' => implode(' | ', $metaParts),
                    'tokens' => $this->searchTokens([
                        $label,
                        $contact->email,
                        $contact->phone,
                    ]),
                ];
            });

        $companies = Company::query()
            ->whereNotNull('company_name')
            ->latest('updated_at')
            ->get()
            ->map(function (Company $company): array {
                return [
                    'key' => 'company-'.$company->id,
                    'type' => 'company',
                    'id' => $company->id,
                    'label' => trim((string) $company->company_name),
                    'meta' => implode(' | ', array_values(array_filter([
                        $company->email,
                        $company->phone,
                    ]))),
                    'tokens' => $this->searchTokens([
                        $company->company_name,
                        $company->email,
                        $company->phone,
                    ]),
                ];
            });

        return collect()
            ->merge($contacts)
            ->merge($companies)
            ->filter(fn (array $record) => filled($record['label']))
            ->unique(fn (array $record) => Str::lower(trim($record['type'].'|'.$record['label'])))
            ->values();
    }

    private function searchTokens(array $values): string
    {
        return collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => Str::lower(Str::squish((string) $value)))
            ->implode(' ');
    }

    public function storeNote(Request $request)
{
    $validated = $request->validate([
        'content' => ['required', 'string'],
        'noteable_id' => ['required', 'integer'],
        'noteable_type' => ['required', 'string'],
    ]);

    $noteableType = $this->resolveNoteableType($validated['noteable_type']);
    $noteable = $noteableType::query()->findOrFail($validated['noteable_id']);

    $this->authorizeNoteable($request, $noteable);

    $note = $noteable->notes()->create([
        'content' => $validated['content'],
        'owner' => $request->user()->name
            ?? $request->user()->email
            ?? 'System User',
    ]);

    return response()->json($note, 201);
}

public function updateNote(Request $request, $id)
{
    $validated = $request->validate([
        'content' => ['required', 'string'],
    ]);

    $note = Note::query()->findOrFail($id);
    $noteable = $note->noteable;

    abort_unless($noteable, 404);

    $this->authorizeNoteable($request, $noteable);

    $note->update([
        'content' => $validated['content'],
    ]);

    return response()->json($note);
}

public function destroyNote(Request $request, $id)
{
    $note = Note::query()->findOrFail($id);
    $noteable = $note->noteable;

    abort_unless($noteable, 404);

    $this->authorizeNoteable($request, $noteable);

    $note->delete();

    return response()->json(null, 204);
}
public function analyzeMeeting(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeMeeting($request, $meeting);

        return response()->json([
            'message' => 'Automatic AI transcript is not enabled yet. Please upload transcript or meeting minutes manually.',
            'meeting' => $meeting->load('notes'),
        ], 422);
    }

    public function uploadTranscript(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeMeeting($request, $meeting);

        $validated = $request->validate([
            'transcript' => ['required', 'file', 'mimes:pdf,doc,docx,txt', 'max:10240'],
        ]);

        $path = $validated['transcript']->store('activities/meeting-transcripts/' . $meeting->id, 'public');

        $attachment = app(ActivityAttachmentService::class)->createFromStoredFile(
            ActivityTypeRegistry::MEETING,
            $meeting->id,
            $path,
            $validated['transcript']->getClientOriginalName(),
            $validated['transcript']->getMimeType(),
            $validated['transcript']->getSize(),
            'transcript',
            auth()->id()
        );
        app(ActivityAuditService::class)->record(
            ActivityTypeRegistry::MEETING,
            $meeting->id,
            'attachment_uploaded',
            [],
            ['attachment_id' => $attachment->id, 'category' => 'transcript']
        );

        $meeting->has_transcript = true;
        $meeting->save();

        Note::create([
            'content' => "TRANSCRIPT UPLOADED:\n" . $validated['transcript']->getClientOriginalName() . "\n" . asset('storage/' . $path),
            'owner' => auth()->user()?->name ?? 'System',
            'noteable_id' => $meeting->id,
            'noteable_type' => Meeting::class,
        ]);

        return response()->json($meeting->fresh()->load('notes'));
    }

    public function uploadMinutes(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeMeeting($request, $meeting);

        $validated = $request->validate([
            'minutes' => ['required', 'file', 'mimes:pdf,doc,docx,txt', 'max:10240'],
        ]);

        $path = $validated['minutes']->store('activities/meeting-minutes/' . $meeting->id, 'public');

        $attachment = app(ActivityAttachmentService::class)->createFromStoredFile(
            ActivityTypeRegistry::MEETING,
            $meeting->id,
            $path,
            $validated['minutes']->getClientOriginalName(),
            $validated['minutes']->getMimeType(),
            $validated['minutes']->getSize(),
            'minutes',
            auth()->id()
        );
        app(ActivityAuditService::class)->record(
            ActivityTypeRegistry::MEETING,
            $meeting->id,
            'attachment_uploaded',
            [],
            ['attachment_id' => $attachment->id, 'category' => 'minutes']
        );

        $meeting->has_minutes = true;
        $meeting->save();

        Note::create([
            'content' => "MEETING MINUTES UPLOADED:\n" . $validated['minutes']->getClientOriginalName() . "\n" . asset('storage/' . $path),
            'owner' => auth()->user()?->name ?? 'System',
            'noteable_id' => $meeting->id,
            'noteable_type' => Meeting::class,
        ]);

        return response()->json($meeting->fresh()->load('notes'));
    }

    public function storeTask(Request $request, TaskCalendarSyncService $calendar, ActivityDuplicateGuard $duplicateGuard)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_task_id' => 'nullable|integer|exists:tasks,id',
            'sort_order' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|string|max:255',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'actual_duration_minutes' => 'nullable|integer|min:0',
            'manual_actual_duration_minutes' => 'nullable|integer|min:0',
            'status' => 'nullable|string|max:80',
            'priority' => 'nullable|string|max:40',
            'recurrence' => 'nullable|string|max:40',
            'recurrence_config' => 'nullable|array',
            'reminder_type' => 'nullable|string|max:40',
            'reminder_value' => 'nullable|integer|min:0',
            'task_template_id' => 'nullable|integer|exists:task_templates,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer|exists:deals,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'related_to' => 'nullable|string|max:255',
            'owner' => 'nullable|string|max:255',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'integer|distinct|exists:users,id',
            'dependency_task_ids' => 'nullable|array',
            'dependency_task_ids.*' => 'integer|distinct|exists:tasks,id',
            'description' => 'nullable|string',
            'client_report_description' => 'nullable|string',
            'include_in_client_report' => 'nullable|boolean',
        ]);

        $assignedToUserId = $validated['assigned_to_user_id']
            ?? collect($validated['assigned_user_ids'] ?? [])->map(fn ($id) => (int) $id)->first();
        $taskOwner = $validated['owner'] ?? ($assignedToUserId ? User::find($assignedToUserId)?->email : null);
        $taskStartDate = filled($validated['start_date'] ?? null) ? Carbon::parse($validated['start_date']) : null;
        $taskDuplicateKey = [
            'prepared_by_user_id' => auth()->id(),
            'name' => Str::lower(trim($validated['name'])),
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ];

        $task = $duplicateGuard->create('task', $taskDuplicateKey, function () use ($validated, $assignedToUserId, $taskOwner, $taskStartDate) {
            return Task::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($validated['name']))])
                ->where('prepared_by_user_id', auth()->id())
                ->where('start_date', $taskStartDate)
                ->where('due_date', $validated['due_date'] ?? null)
                ->exists();
        }, function () use ($validated) {
            return DB::transaction(function () use ($validated) {
            $assignedUserIds = collect($validated['assigned_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $dependencyTaskIds = collect($validated['dependency_task_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            unset(
                $validated['assigned_user_ids'],
                $validated['dependency_task_ids']
            );

            if (array_key_exists('actual_duration_minutes', $validated)
                && ! array_key_exists('manual_actual_duration_minutes', $validated)) {
                $validated['manual_actual_duration_minutes'] = $validated['actual_duration_minutes'];
            }

            if (
                empty($validated['assigned_to_user_id']) &&
                $assignedUserIds->isNotEmpty()
            ) {
                $validated['assigned_to_user_id'] = $assignedUserIds->first();
            }

            if (
                empty($validated['owner']) &&
                !empty($validated['assigned_to_user_id'])
            ) {
                $validated['owner'] =
                    User::find($validated['assigned_to_user_id'])?->email;
            }

            if (($validated['status'] ?? 'Open') === 'Completed') {
                $validated['completed_at'] = now();
            }

            $task = new Task($validated);
            $task->prepared_by_user_id = auth()->id();
            $task->save();

            if ($assignedUserIds->isNotEmpty()) {
                $syncData = [];

                foreach ($assignedUserIds as $userId) {
                    $syncData[$userId] = [
                        'role' => 'assignee',
                    ];
                }

                $task->assignees()->sync($syncData);
            }

            if ($dependencyTaskIds->isNotEmpty()) {
                $syncData = [];

                foreach ($dependencyTaskIds as $blockingTaskId) {
                    if ((int) $blockingTaskId === (int) $task->id) {
                        continue;
                    }

                    $syncData[$blockingTaskId] = [
                        'dependency_type' => 'BLOCKS',
                        'notes' => null,
                    ];
                }

                $task->blockingTasks()->sync($syncData);
            }

            return $task;
            });
        });

        return $this->automaticCalendarResponse(
            $task,
            $calendar,
            [
                'notes',
                'assignees:id,name,email',
                'primaryAssignee:id,name,email',
                'parentTask:id,name',
                'blockingTasks:id,name',
                'company:id,company_name,email',
                'contactRecord:id,first_name,last_name,email,phone',
                'deal:id,deal_code,deal_name',
                'project:id,project_code,name',
                'preparedBy:id,name,email',
            ],
            'task'
        );
    }
    public function storeEvent(Request $request, EventCalendarSyncService $calendar, ActivityDuplicateGuard $duplicateGuard)
    {
       $validated = $request->validate([
    'title' => 'required|string',
    'related_account' => 'nullable|string',
    'event_type' => 'nullable|string',

    'from' => 'nullable|string',
    'to' => 'nullable|string',

    'venue' => 'nullable|string',
    'mode' => 'nullable|string',

    'participants' => 'nullable|integer',

    'role' => 'nullable|string',
    'status' => 'nullable|string',

    'estimated_hours' => 'nullable|numeric',
    'actual_hours' => 'nullable|numeric',
    'duration_minutes' => 'nullable|integer|min:0',

    'company_id' => 'nullable|integer|exists:companies,id',

    'contact_id' => 'nullable|integer|exists:contacts,id',

    'deal_id' => 'nullable|integer|exists:deals,id',

    'project_id' => 'nullable|integer|exists:projects,id',

    'related_to' => 'nullable|string',
    'host' => 'nullable|string',
    'client_report_description' => 'nullable|string',
    'include_in_client_report' => 'nullable|boolean',
]);

        if (! array_key_exists('duration_minutes', $validated)) {
            $validated['duration_minutes'] = $this->eventDurationMinutes($validated);
        }

        $event = $duplicateGuard->create('event', $validated, function () use ($validated) {
            return Event::query()
                ->whereRaw('LOWER(TRIM(title)) = ?', [Str::lower(trim($validated['title']))])
                ->where('prepared_by_user_id', auth()->id())
                ->where('from', $validated['from'] ?? null)
                ->where('to', $validated['to'] ?? null)
                ->where('host', $validated['host'] ?? null)
                ->where('company_id', $validated['company_id'] ?? null)
                ->where('contact_id', $validated['contact_id'] ?? null)
                ->where('deal_id', $validated['deal_id'] ?? null)
                ->where('project_id', $validated['project_id'] ?? null)
                ->exists();
        }, function () use ($validated) {
            $event = new Event($validated);
            $event->prepared_by_user_id = auth()->id();
            $event->save();

            return $event;
        });

        return $this->automaticCalendarResponse(
            $event,
            $calendar,
            $this->activityRelationshipLoads(),
            'event'
        );
    }

    public function storeCall(Request $request, ActivityDuplicateGuard $duplicateGuard)
    {
        $validated = $request->validate([
            'contact' => 'required|string',
            'to' => 'nullable|string',
            'from' => 'nullable|string',
            'type' => 'nullable|string',
            'start_time' => 'nullable|string',
            'start_hour' => 'nullable|string',
            'duration' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer|exists:deals,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'related_to' => 'nullable|string',
            'owner' => 'nullable|string',
            'completed' => 'nullable|boolean',
            'purpose' => 'nullable|string',
            'agenda' => 'nullable|string',
            'client_report_description' => 'nullable|string',
            'include_in_client_report' => 'nullable|boolean',
        ]);

        if (! array_key_exists('duration_minutes', $validated)) {
            $validated['duration_minutes'] = $this->durationToMinutes($validated['duration'] ?? null);
        }

        $call = $duplicateGuard->create('call', $validated, function () use ($validated) {
            return Call::query()
                ->whereRaw('LOWER(TRIM(contact)) = ?', [Str::lower(trim($validated['contact']))])
                ->where('prepared_by_user_id', auth()->id())
                ->where('purpose', $validated['purpose'] ?? null)
                ->where('start_time', $validated['start_time'] ?? null)
                ->where('start_hour', $validated['start_hour'] ?? null)
                ->where('owner', $validated['owner'] ?? null)
                ->where('company_id', $validated['company_id'] ?? null)
                ->where('contact_id', $validated['contact_id'] ?? null)
                ->where('deal_id', $validated['deal_id'] ?? null)
                ->where('project_id', $validated['project_id'] ?? null)
                ->exists();
        }, function () use ($validated) {
            $call = new Call($validated);
            $call->prepared_by_user_id = auth()->id();
            $call->save();

            return $call;
        });

        return response()->json(
            $call->load($this->activityRelationshipLoads())
        );
    }

    public function storeMeeting(Request $request, MeetingCalendarSyncService $calendar, ActivityDuplicateGuard $duplicateGuard)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'meeting_type' => 'nullable|string',
            'related_account' => 'nullable|string',
            'related_project' => 'nullable|string',
            'mode' => 'nullable|string|in:Online,Physical,Hybrid',
            'owner' => 'nullable|string',
            'date' => 'nullable|string',
            'time' => 'nullable|string',
            'duration' => 'nullable|string',
            'actual_duration' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'location' => 'nullable|string',
            'attendees' => 'nullable|numeric',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer|exists:deals,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'related_to' => 'nullable|string',
            'status' => 'nullable|string',
            'description' => 'nullable|string',
            'has_video' => 'nullable|boolean',
            'has_audio' => 'nullable|boolean',
            'has_transcript' => 'nullable|boolean',
            'has_minutes' => 'nullable|boolean',
            'client_report_description' => 'nullable|string',
            'include_in_client_report' => 'nullable|boolean',
            'external_attendees' => 'nullable|array',
            'external_attendees.*.name' => 'nullable|string|max:255',
            'external_attendees.*.email' => 'required|email|max:255',
            'add_to_calendar' => 'nullable|boolean',
            'create_google_meet' => 'nullable|boolean',
            'notify_external_attendees' => 'nullable|boolean',
        ]);

        $calendarOptions = $this->meetingCalendarOptions($validated);

        // Calculate initial status based on date/time scheduling
        if (empty($validated['status']) || $validated['status'] === 'upcoming') {
            if (!empty($validated['date']) && !empty($validated['time'])) {
                try {
                    $start = Carbon::parse($validated['date'] . ' ' . $validated['time']);
                    
                    $durationMinutes = 0;
                    if (!empty($validated['duration'])) {
                        preg_match_all('/(\d+)\s*(hr|hour|min|m)/i', $validated['duration'], $matches, PREG_SET_ORDER);
                        if (!empty($matches)) {
                            foreach ($matches as $match) {
                                $durationMinutes += str_starts_with(strtolower($match[2]), 'h') ? (int)$match[1] * 60 : (int)$match[1];
                            }
                        } else {
                            preg_match('/\d+/', $validated['duration'], $m);
                            $durationMinutes = !empty($m[0]) ? (int)$m[0] : 30;
                        }
                    } else {
                        $durationMinutes = 30;
                    }

                    $end = $start->copy()->addMinutes($durationMinutes);
                    $validated['status'] = now()->greaterThanOrEqualTo($end) ? 'completed' : 'upcoming';
                } catch (\Exception $e) {
                    $validated['status'] = 'upcoming';
                }
            } else {
                $validated['status'] = 'upcoming';
            }
        }

        if (! array_key_exists('duration_minutes', $validated)) {
            $validated['duration_minutes'] = $this->durationToMinutes(
                $validated['actual_duration'] ?? $validated['duration'] ?? null
            );
        }

        $meetingDuplicateKey = [
            'prepared_by_user_id' => auth()->id(),
            'title' => Str::lower(trim($validated['title'])),
            'date' => $validated['date'] ?? null,
            'time' => $validated['time'] ?? null,
        ];

        $meeting = $duplicateGuard->create('meeting', $meetingDuplicateKey, function () use ($validated) {
            return Meeting::query()
                ->whereRaw('LOWER(TRIM(title)) = ?', [Str::lower(trim($validated['title']))])
                ->where('prepared_by_user_id', auth()->id())
                ->where('date', $validated['date'] ?? null)
                ->where('time', $validated['time'] ?? null)
                ->exists();
        }, function () use ($validated, $calendarOptions) {
            return DB::transaction(function () use ($validated, $calendarOptions): Meeting {
                $meeting = new Meeting($validated);
                $meeting->prepared_by_user_id = auth()->id();

            if ($calendarOptions['add_to_calendar']) {
                $meeting->calendar_provider = 'google';
                $meeting->calendar_sync_status = 'pending';
                $meeting->calendar_sync_error = null;
            }

            $meeting->save();

                return $meeting;
            });
        });

        return $this->meetingCalendarResponse($meeting, $calendar, $calendarOptions);
    }

    public function updateTask(Request $request, $id, TaskCalendarSyncService $calendar)
    {
        $task = Task::findOrFail($id);
        $this->authorizeTask($request, $task);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_task_id' => 'nullable|integer|exists:tasks,id',
            'sort_order' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|string|max:255',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'actual_duration_minutes' => 'nullable|integer|min:0',
            'manual_actual_duration_minutes' => 'nullable|integer|min:0',
            'status' => 'nullable|string|max:80',
            'priority' => 'nullable|string|max:40',
            'recurrence' => 'nullable|string|max:40',
            'recurrence_config' => 'nullable|array',
            'reminder_type' => 'nullable|string|max:40',
            'reminder_value' => 'nullable|integer|min:0',
            'task_template_id' => 'nullable|integer|exists:task_templates,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer|exists:deals,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'related_to' => 'nullable|string|max:255',
            'owner' => 'nullable|string|max:255',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'integer|distinct|exists:users,id',
            'dependency_task_ids' => 'nullable|array',
            'dependency_task_ids.*' => 'integer|distinct|exists:tasks,id',
            'description' => 'nullable|string',
            'client_report_description' => 'nullable|string',
            'include_in_client_report' => 'nullable|boolean',
        ]);

        if (
            !empty($validated['parent_task_id']) &&
            (int) $validated['parent_task_id'] === (int) $task->id
        ) {
            return response()->json([
                'message' => 'A task cannot be its own parent.',
                'errors' => [
                    'parent_task_id' => [
                        'A task cannot be its own parent.',
                    ],
                ],
            ], 422);
        }

        app(TaskWorkflowService::class)->assertValidParent(
            (int) $task->id,
            isset($validated['parent_task_id']) ? (int) $validated['parent_task_id'] : null
        );

        if (
            collect($validated['dependency_task_ids'] ?? [])
                ->contains(
                    fn ($dependencyId) =>
                        (int) $dependencyId === (int) $task->id
                )
        ) {
            return response()->json([
                'message' => 'A task cannot depend on itself.',
                'errors' => [
                    'dependency_task_ids' => [
                        'A task cannot depend on itself.',
                    ],
                ],
            ], 422);
        }


        if ($request->has('dependency_task_ids')) {
            app(TaskWorkflowService::class)->assertValidDependencies(
                (int) $task->id,
                $validated['dependency_task_ids'] ?? []
            );
        }

        DB::transaction(function () use ($task, $validated, $request) {
            $assignedUserIds = collect(
                $validated['assigned_user_ids'] ?? []
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $dependencyTaskIds = collect(
                $validated['dependency_task_ids'] ?? []
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            unset(
                $validated['assigned_user_ids'],
                $validated['dependency_task_ids']
            );

            if (array_key_exists('actual_duration_minutes', $validated)
                && ! array_key_exists('manual_actual_duration_minutes', $validated)) {
                $validated['manual_actual_duration_minutes'] = $validated['actual_duration_minutes'];
            }

            if (
                $request->has('assigned_user_ids') &&
                empty($validated['assigned_to_user_id'])
            ) {
                $validated['assigned_to_user_id'] =
                    $assignedUserIds->first();
            }

            if (!empty($validated['assigned_to_user_id'])) {
                $validated['owner'] =
                    User::find($validated['assigned_to_user_id'])?->email
                    ?? ($validated['owner'] ?? $task->owner);
            }

            $newStatus = $validated['status'] ?? $task->status;

            if ($newStatus === 'Completed' && !$task->completed_at) {
                $validated['completed_at'] = now();
            }

            if ($newStatus !== 'Completed') {
                $validated['completed_at'] = null;
            }

            $task->update($validated);

            if ($request->has('assigned_user_ids')) {
                $syncData = [];

                foreach ($assignedUserIds as $userId) {
                    $syncData[$userId] = [
                        'role' => 'assignee',
                    ];
                }

                $task->assignees()->sync($syncData);
            }

            if ($request->has('dependency_task_ids')) {
                $syncData = [];

                foreach ($dependencyTaskIds as $blockingTaskId) {
                    if ((int) $blockingTaskId === (int) $task->id) {
                        continue;
                    }

                    $syncData[$blockingTaskId] = [
                        'dependency_type' => 'BLOCKS',
                        'notes' => null,
                    ];
                }

                $task->blockingTasks()->sync($syncData);
            }
        });

        return $this->automaticCalendarResponse(
            $task->fresh(),
            $calendar,
            [
                'notes',
                'assignees:id,name,email',
                'primaryAssignee:id,name,email',
                'parentTask:id,name',
                'blockingTasks:id,name',
                'company:id,company_name,email',
                'contactRecord:id,first_name,last_name,email,phone',
                'deal:id,deal_code,deal_name',
                'project:id,project_code,name',
                'preparedBy:id,name,email',
            ],
            'task'
        );
    }
    public function updateEvent(Request $request, $id, EventCalendarSyncService $calendar)
    {
        $validated = $request->validate([
    'title' => 'required|string',
    'related_account' => 'nullable|string',
    'event_type' => 'nullable|string',

    'from' => 'nullable|string',
    'to' => 'nullable|string',

    'venue' => 'nullable|string',
    'mode' => 'nullable|string',

    'participants' => 'nullable|integer',

    'role' => 'nullable|string',
    'status' => 'nullable|string',

    'estimated_hours' => 'nullable|numeric',
    'actual_hours' => 'nullable|numeric',
    'duration_minutes' => 'nullable|integer|min:0',

    'company_id' => 'nullable|integer|exists:companies,id',

    'contact_id' => 'nullable|integer|exists:contacts,id',

    'deal_id' => 'nullable|integer|exists:deals,id',

    'project_id' => 'nullable|integer|exists:projects,id',

    'related_to' => 'nullable|string',
    'host' => 'nullable|string',
    'client_report_description' => 'nullable|string',
    'include_in_client_report' => 'nullable|boolean',
]);

        $event = Event::findOrFail($id);
        $this->authorizeEvent($request, $event);
        if (! array_key_exists('duration_minutes', $validated)
            && (array_key_exists('actual_hours', $validated) || array_key_exists('from', $validated) || array_key_exists('to', $validated))) {
            $validated['duration_minutes'] = $this->eventDurationMinutes($validated + $event->only(['from', 'to', 'actual_hours']));
        }
        $event->update($validated);

        return $this->automaticCalendarResponse(
            $event->fresh(),
            $calendar,
            $this->activityRelationshipLoads(),
            'event'
        );
    }

    public function updateCall(Request $request, $id)
    {
        $validated = $request->validate([
            'contact' => 'required|string',
            'to' => 'nullable|string',
            'from' => 'nullable|string',
            'type' => 'nullable|string',
            'start_time' => 'nullable|string',
            'start_hour' => 'nullable|string',
            'duration' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer|exists:deals,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'related_to' => 'nullable|string',
            'owner' => 'nullable|string',
            'completed' => 'nullable|boolean',
            'purpose' => 'nullable|string',
            'agenda' => 'nullable|string',
            'client_report_description' => 'nullable|string',
            'include_in_client_report' => 'nullable|boolean',
        ]);

        $call = Call::findOrFail($id);
        $this->authorizeCall($request, $call);
        if (! array_key_exists('duration_minutes', $validated) && array_key_exists('duration', $validated)) {
            $validated['duration_minutes'] = $this->durationToMinutes($validated['duration']);
        }
        $call->update($validated);

        return response()->json(
            $call->fresh()->load($this->activityRelationshipLoads())
        );
    }

    public function updateMeeting(Request $request, $id, MeetingCalendarSyncService $calendar)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'meeting_type' => 'nullable|string',
            'related_account' => 'nullable|string',
            'related_project' => 'nullable|string',
            'mode' => 'nullable|string|in:Online,Physical,Hybrid',
            'owner' => 'nullable|string',
            'date' => 'nullable|string',
            'time' => 'nullable|string',
            'duration' => 'nullable|string',
            'actual_duration' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'location' => 'nullable|string',
            'attendees' => 'nullable|numeric',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'deal_id' => 'nullable|integer|exists:deals,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'related_to' => 'nullable|string',
            'status' => 'nullable|string',
            'description' => 'nullable|string',
            'has_video' => 'nullable|boolean',
            'has_audio' => 'nullable|boolean',
            'has_transcript' => 'nullable|boolean',
            'has_minutes' => 'nullable|boolean',
            'client_report_description' => 'nullable|string',
            'include_in_client_report' => 'nullable|boolean',
            'external_attendees' => 'nullable|array',
            'external_attendees.*.name' => 'nullable|string|max:255',
            'external_attendees.*.email' => 'required|email|max:255',
            'add_to_calendar' => 'nullable|boolean',
            'create_google_meet' => 'nullable|boolean',
            'notify_external_attendees' => 'nullable|boolean',
        ]);

        $calendarOptions = $this->meetingCalendarOptions($validated);

        $meeting = Meeting::findOrFail($id);
        $this->authorizeMeeting($request, $meeting);

        // Recalculate status during updates
        if (empty($validated['status']) || $validated['status'] === 'upcoming') {
            if (!empty($validated['date']) && !empty($validated['time'])) {
                try {
                    $start = Carbon::parse($validated['date'] . ' ' . $validated['time']);
                    
                    $durationMinutes = 0;
                    if (!empty($validated['duration'])) {
                        preg_match_all('/(\d+)\s*(hr|hour|min|m)/i', $validated['duration'], $matches, PREG_SET_ORDER);
                        if (!empty($matches)) {
                            foreach ($matches as $match) {
                                $durationMinutes += str_starts_with(strtolower($match[2]), 'h') ? (int)$match[1] * 60 : (int)$match[1];
                            }
                        } else {
                            preg_match('/\d+/', $validated['duration'], $m);
                            $durationMinutes = !empty($m[0]) ? (int)$m[0] : 30;
                        }
                    } else {
                        $durationMinutes = 30;
                    }

                    $end = $start->copy()->addMinutes($durationMinutes);
                    $validated['status'] = now()->greaterThanOrEqualTo($end) ? 'completed' : 'upcoming';
                } catch (\Exception $e) {
                    // Do nothing
                }
            }
        }

        if (! array_key_exists('duration_minutes', $validated)
            && (array_key_exists('actual_duration', $validated) || array_key_exists('duration', $validated))) {
            $validated['duration_minutes'] = $this->durationToMinutes(
                $validated['actual_duration'] ?? $validated['duration'] ?? null
            );
        }

        DB::transaction(function () use ($meeting, $validated, $calendarOptions): void {
            $meeting->fill($validated);

            if ($calendarOptions['add_to_calendar']) {
                $meeting->calendar_provider = 'google';
                $meeting->calendar_sync_status = 'pending';
                $meeting->calendar_sync_error = null;
            }

            $meeting->save();
        });

        return $this->meetingCalendarResponse($meeting->fresh(), $calendar, $calendarOptions);
    }

    private function automaticCalendarResponse($record, $calendar, array $loads, string $activityLabel)
    {
        $status = 200;
        $warning = null;
        $shouldSync = $calendar->isSchedulable($record);
        $shouldDelete = ! $shouldSync && filled($record->external_calendar_event_id);

        if ($shouldSync || $shouldDelete) {
            $record->forceFill([
                'calendar_provider' => 'google',
                'calendar_sync_status' => 'pending',
                'calendar_sync_error' => null,
            ])->save();

            try {
                if ($shouldSync) {
                    $result = $calendar->sync($record);
                    $record->forceFill([
                        'calendar_provider' => 'google',
                        'external_calendar_event_id' => $result['event_id'],
                        'calendar_sync_status' => 'synced',
                        'calendar_synced_at' => now(),
                        'calendar_sync_error' => null,
                    ])->save();
                } else {
                    $calendar->delete($record);
                    $record->forceFill([
                        'calendar_provider' => null,
                        'external_calendar_event_id' => null,
                        'calendar_sync_status' => null,
                        'calendar_synced_at' => null,
                        'calendar_sync_error' => null,
                    ])->save();
                }
            } catch (Throwable $exception) {
                report($exception);
                $warning = 'The '.$activityLabel.' was saved, but Google Calendar synchronization failed.';
                $status = 202;

                $record->forceFill([
                    'calendar_sync_status' => 'failed',
                    'calendar_sync_error' => Str::limit($exception->getMessage(), 2000),
                ])->save();
            }
        }

        $payload = $record->fresh()->load($loads)->toArray();

        if ($warning) {
            $payload['calendar_sync_warning'] = $warning;
        }

        return response()->json($payload, $status);
    }

    private function meetingCalendarOptions(array &$validated): array
    {
        $options = [
            'add_to_calendar' => (bool) ($validated['add_to_calendar'] ?? false),
            'create_google_meet' => (bool) ($validated['create_google_meet'] ?? false),
            'notify_external_attendees' => (bool) ($validated['notify_external_attendees'] ?? false),
        ];

        unset($validated['add_to_calendar'], $validated['create_google_meet']);
        $validated['notify_external_attendees'] = $options['notify_external_attendees'];

        $errors = [];

        if ($options['create_google_meet'] && ! $options['add_to_calendar']) {
            $errors['create_google_meet'][] = 'Google Meet creation requires calendar synchronization.';
        }

        if ($options['notify_external_attendees'] && ! $options['add_to_calendar']) {
            $errors['notify_external_attendees'][] = 'External notifications require calendar synchronization.';
        }

        if ($options['create_google_meet'] && ($validated['mode'] ?? null) === 'Physical') {
            $errors['create_google_meet'][] = 'Google Meet is available only for Online or Hybrid meetings.';
        }

        if ($options['add_to_calendar'] && blank($validated['date'] ?? null)) {
            $errors['date'][] = 'A meeting date is required for calendar synchronization.';
        }

        if ($options['add_to_calendar'] && blank($validated['time'] ?? null)) {
            $errors['time'][] = 'A meeting time is required for calendar synchronization.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $options;
    }

    private function meetingCalendarResponse(
        Meeting $meeting,
        MeetingCalendarSyncService $calendar,
        array $options
    ) {
        $status = 200;
        $warning = null;

        if ($options['add_to_calendar']) {
            try {
                $result = $calendar->sync(
                    $meeting,
                    $options['create_google_meet'],
                    $options['notify_external_attendees']
                );

                DB::transaction(function () use ($meeting, $result): void {
                    $meeting->forceFill([
                        'calendar_provider' => 'google',
                        'external_calendar_event_id' => $result['event_id'],
                        'conference_url' => $result['conference_url'],
                        'calendar_sync_status' => 'synced',
                        'calendar_synced_at' => now(),
                        'calendar_sync_error' => null,
                    ])->save();
                });
            } catch (Throwable $exception) {
                report($exception);
                $warning = 'The meeting was saved, but Google Calendar synchronization failed.';
                $status = 202;

                DB::transaction(function () use ($meeting, $exception): void {
                    $meeting->forceFill([
                        'calendar_sync_status' => 'failed',
                        'calendar_sync_error' => Str::limit($exception->getMessage(), 2000),
                    ])->save();
                });
            }
        }

        $payload = $meeting->fresh()->load($this->activityRelationshipLoads())->toArray();

        if ($warning) {
            $payload['calendar_sync_warning'] = $warning;
        }

        return response()->json($payload, $status);
    }

    private function resolveNoteableType(string $type): string
    {
        $type = ltrim(trim($type), '\\');
        $shortType = strtolower(
            str_contains($type, '\\')
                ? substr($type, strrpos($type, '\\') + 1)
                : $type
        );

        return match ($shortType) {
            'task' => Task::class,
            'event' => Event::class,
            'call' => Call::class,
            'meeting' => Meeting::class,
            default => abort(422, 'Unsupported note parent type.'),
        };
    }

    private function authorizeNoteable(Request $request, $noteable): void
    {
        if ($noteable instanceof Task) {
            $this->authorizeTask($request, $noteable);
            return;
        }

        if ($noteable instanceof Event) {
            $this->authorizeEvent($request, $noteable);
            return;
        }

        if ($noteable instanceof Call) {
            $this->authorizeCall($request, $noteable);
            return;
        }

        if ($noteable instanceof Meeting) {
            $this->authorizeMeeting($request, $noteable);
            return;
        }

        abort(404);
    }
    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || (int) $event->prepared_by_user_id === (int) $user->id
            ),
            403
        );
    }
    private function authorizeCall(Request $request, Call $call): void
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || (int) $call->prepared_by_user_id === (int) $user->id
                || (string) $call->owner === (string) $user->email
            ),
            403
        );
    }
    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || (int) $meeting->prepared_by_user_id === (int) $user->id
                || (string) $meeting->owner === (string) $user->email
            ),
            403
        );
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

    public function destroyTask(Request $request, $id, TaskCalendarSyncService $calendar)
    {
        $task = Task::findOrFail($id);
        $this->authorizeTask($request, $task);

        $warning = $this->deleteExternalCalendarEvent($task, $calendar, 'task');
        $task->delete();

        if ($warning) {
            return response()->json(['calendar_sync_warning' => $warning], 202);
        }

        return response()->json(null, 204);
    }

    public function destroyEvent(Request $request, $id, EventCalendarSyncService $calendar)
    {
        $event = Event::findOrFail($id);
        $this->authorizeEvent($request, $event);
        $warning = $this->deleteExternalCalendarEvent($event, $calendar, 'event');
        $event->delete();

        if ($warning) {
            return response()->json(['calendar_sync_warning' => $warning], 202);
        }

        return response()->json(null, 204);
    }

    public function destroyCall(Request $request, $id)
    {
        $call = Call::findOrFail($id);
        $this->authorizeCall($request, $call);
        if ($call->audio_path) {
            $this->deletePublicStoredFile($call->audio_path);
        }
        $call->delete();
        return response()->json(null, 204);
    }

    public function destroyMeeting(Request $request, $id, MeetingCalendarSyncService $calendar)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeMeeting($request, $meeting);
        $warning = $this->deleteExternalCalendarEvent($meeting, $calendar, 'meeting');
        if ($meeting->video_path) {
            $this->deletePublicStoredFile($meeting->video_path);
        }
        if ($meeting->audio_path) {
            $this->deletePublicStoredFile($meeting->audio_path);
        }
        $meeting->delete();

        if ($warning) {
            return response()->json(['calendar_sync_warning' => $warning], 202);
        }

        return response()->json(null, 204);
    }

    private function deleteExternalCalendarEvent($record, $calendar, string $activityLabel): ?string
    {
        if (blank($record->external_calendar_event_id)) {
            return null;
        }

        try {
            $calendar->delete($record);

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $record->forceFill([
                'calendar_sync_status' => 'failed',
                'calendar_sync_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();

            return 'The '.$activityLabel.' was deleted locally, but its Google Calendar event could not be deleted.';
        }
    }

    public function uploadVideo(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeMeeting($request, $meeting);

        $videoDir = Storage::disk('public')->path('videos');
        if (!file_exists($videoDir)) {
            mkdir($videoDir, 0755, true);
        }

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $chunkIndex = $request->input('chunkIndex', 0);
            $totalChunks = $request->input('totalChunks', 1);
            $originalName = $request->input('fileName', 'upload.mp4');
            
            $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'mp4';
            $filename = 'meeting_' . $id . '_' . md5($originalName) . '.' . $extension;
            $filePath = $videoDir . '/' . $filename;
            
            // Save or append chunk
            $out = fopen($filePath, $chunkIndex == 0 ? 'wb' : 'ab');
            if ($out) {
                $in = fopen($file->getRealPath(), 'rb');
                if ($in) {
                    while ($buff = fread($in, 4096)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                }
                fclose($out);
            }

            // If it's the last chunk, update the database
            if ($chunkIndex == $totalChunks - 1) {
                $meeting->video_path = 'storage/videos/' . $filename;
                $meeting->has_video = true;
                $meeting->save();
                return response()->json($meeting);
            }

            return response()->json(['status' => 'chunk_uploaded', 'index' => $chunkIndex]);
        }

        return response()->json(['error' => 'No file uploaded, or file is too large (exceeds server limit of '.ini_get('upload_max_filesize').')'], 400);
    }

    public function uploadCallAudio(Request $request, $id)
    {
        $call = Call::findOrFail($id);
        $this->authorizeCall($request, $call);
        
        $audioDir = Storage::disk('public')->path('audios');
        if (!file_exists($audioDir)) {
            mkdir($audioDir, 0755, true);
        }

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $chunkIndex = $request->input('chunkIndex', 0);
            $totalChunks = $request->input('totalChunks', 1);
            $originalName = $request->input('fileName', 'upload.mp3');
            
            $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'mp3';
            $filename = 'call_' . $id . '_' . md5($originalName) . '.' . $extension;
            $filePath = $audioDir . '/' . $filename;
            
            // Save or append chunk
            $out = fopen($filePath, $chunkIndex == 0 ? 'wb' : 'ab');
            if ($out) {
                $in = fopen($file->getRealPath(), 'rb');
                if ($in) {
                    while ($buff = fread($in, 4096)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                }
                fclose($out);
            }

            // If it's the last chunk, update the database
            if ($chunkIndex == $totalChunks - 1) {
                $call->audio_path = 'storage/audios/' . $filename;
                $call->save();
                return response()->json($call);
            }

            return response()->json(['status' => 'chunk_uploaded', 'index' => $chunkIndex]);
        }

        return response()->json(['error' => 'No file uploaded, or file is too large (exceeds server limit of '.ini_get('upload_max_filesize').')'], 400);
    }

    public function destroyCallAudio(Request $request, $id)
    {
        $call = Call::findOrFail($id);
        $this->authorizeCall($request, $call);
        
        if ($call->audio_path) {
            $this->deletePublicStoredFile($call->audio_path);
            $call->audio_path = null;
            $call->save();
        }

        return response()->json($call);
    }

    private function deletePublicStoredFile(?string $path): void
    {
        $path = $this->normalizePublicStoragePath($path);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function activityRelationshipLoads(): array
    {
        return [
            'company:id,company_name,email',
            'contactRecord:id,first_name,last_name,email,phone',
            'deal:id,deal_code,deal_name',
            'project:id,project_code,name',
            'preparedBy:id,name,email',
        ];
    }

    private function eventDurationMinutes(array $attributes): ?int
    {
        $actual = $this->hoursToMinutes($attributes['actual_hours'] ?? null);

        if ($actual !== null) {
            return $actual;
        }

        if (blank($attributes['from'] ?? null) || blank($attributes['to'] ?? null)) {
            return null;
        }

        try {
            $from = Carbon::parse($attributes['from']);
            $to = Carbon::parse($attributes['to']);

            return $to->greaterThanOrEqualTo($from) ? $from->diffInMinutes($to) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function meetingHasEnded(Meeting $meeting): bool
    {
        if (!$meeting->date || !$meeting->time) {
            return false;
        }

        try {
            $start = Carbon::parse($meeting->date.' '.$meeting->time);
            $durationMinutes = 30;

            if ($meeting->duration) {
                preg_match_all(
                    '/(\d+)\s*(hr|hour|min|m)/i',
                    $meeting->duration,
                    $matches,
                    PREG_SET_ORDER
                );

                if ($matches) {
                    $durationMinutes = 0;

                    foreach ($matches as $match) {
                        $value = (int) $match[1];
                        $durationMinutes += str_starts_with(strtolower($match[2]), 'h')
                            ? $value * 60
                            : $value;
                    }
                } elseif (preg_match('/\d+/', $meeting->duration, $match)) {
                    $durationMinutes = (int) $match[0];
                }
            }

            return now()->greaterThanOrEqualTo(
                $start->copy()->addMinutes($durationMinutes)
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizePublicStoragePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^public/#', '', $path);
        $path = preg_replace('#^storage/#', '', $path);

        // Backward compatibility for old records saved as /videos/file.mp4 or /audios/file.mp3.
        if (str_starts_with($path, 'videos/') || str_starts_with($path, 'audios/')) {
            return $path;
        }

        return $path;
    }
}

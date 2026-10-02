<?php

namespace App\Support;

use App\Models\Call;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailActivity;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MessageConversation;
use App\Models\OfficialBusinessTrip;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityTypeRegistry;
use App\Services\OfficialBusinessActivityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ActivityTimelineBuilder
{
    public function __construct(
        private readonly OfficialBusinessActivityService $officialBusinessActivities
    ) {}

    public function forContact(Contact $contact, User $user): array
    {
        return $this->linkedActivities('contact_id', (int) $contact->getKey(), $user);
    }

    public function forCompany(Company|array $company, User $user): array
    {
        $companyId = (int) ($company instanceof Company ? $company->getKey() : ($company['id'] ?? 0));

        return $this->linkedActivities('company_id', $companyId, $user);
    }

    private function linkedActivities(string $foreignKey, int $relatedId, User $user): array
    {
        if ($relatedId < 1) {
            return [];
        }

        return collect()
            ->merge($this->tasks($foreignKey, $relatedId))
            ->merge($this->events($foreignKey, $relatedId))
            ->merge($this->calls($foreignKey, $relatedId))
            ->merge($this->meetings($foreignKey, $relatedId))
            ->merge($this->emails($foreignKey, $relatedId, $user))
            ->merge($this->officialBusiness($foreignKey, $relatedId, $user))
            ->merge($this->messages($foreignKey, $relatedId, $user))
            ->unique(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id'])
            ->sortByDesc('sortAt')
            ->map(fn (array $activity): array => collect($activity)->except('sortAt')->all())
            ->values()
            ->all();
    }

    private function tasks(string $foreignKey, int $relatedId): Collection
    {
        return $this->records(Task::class, 'tasks', $foreignKey, $relatedId)
            ->map(fn (Task $task): array => $this->timelineActivity(
                ActivityTypeRegistry::TASK, $task, 'fa-square-check',
                $task->description ?: $task->name, $task->due_date, $task->owner,
                $task->status ?: 'Open', $task->name
            ));
    }

    private function events(string $foreignKey, int $relatedId): Collection
    {
        return $this->records(Event::class, 'events', $foreignKey, $relatedId)
            ->map(fn (Event $event): array => $this->timelineActivity(
                ActivityTypeRegistry::EVENT, $event, 'fa-calendar-days', $event->title,
                $event->from, $event->host, $event->status ?: 'Scheduled',
                $event->to ? 'Until '.$event->to : null
            ));
    }

    private function calls(string $foreignKey, int $relatedId): Collection
    {
        return $this->records(Call::class, 'calls', $foreignKey, $relatedId)
            ->map(function (Call $call): array {
                $startsAt = trim(collect([$call->start_time, $call->start_hour])->filter()->implode(' '));

                return $this->timelineActivity(
                    ActivityTypeRegistry::CALL, $call, 'fa-phone',
                    $call->purpose ?: $call->agenda ?: $call->contact ?: $call->to,
                    $startsAt, $call->owner ?: $call->from,
                    $call->completed ? 'Completed' : 'Pending', $call->agenda
                );
            });
    }

    private function meetings(string $foreignKey, int $relatedId): Collection
    {
        return $this->records(Meeting::class, 'meetings', $foreignKey, $relatedId)
            ->map(function (Meeting $meeting): array {
                $startsAt = trim(collect([$meeting->date, $meeting->time])->filter()->implode(' '));

                return $this->timelineActivity(
                    ActivityTypeRegistry::MEETING, $meeting, 'fa-video',
                    $meeting->description ?: $meeting->title, $startsAt, $meeting->owner,
                    Str::headline($meeting->status ?: 'Upcoming'), $meeting->title
                );
            });
    }

    private function officialBusiness(string $foreignKey, int $relatedId, User $user): Collection
    {
        if (! Schema::hasTable('official_business_trips') || ! Schema::hasColumn('official_business_trips', $foreignKey)) {
            return collect();
        }

        return $this->officialBusinessActivities->context($user)['records']
            ->filter(function (OfficialBusinessTrip $trip) use ($foreignKey, $relatedId, $user): bool {
                if ((int) $trip->{$foreignKey} === $relatedId) {
                    return true;
                }

                if ($foreignKey !== 'company_id'
                    || (int) $trip->workflow_version < 3
                    || ! Schema::hasColumn('official_business_trip_tickets', 'company_id')) {
                    return false;
                }

                $ticket = $trip->tripTickets()->where('company_id', $relatedId)->first();

                return $ticket !== null && Gate::forUser($user)->allows('view', $ticket);
            })
            ->map(function (OfficialBusinessTrip $trip): array {
                $activity = $this->timelineActivity(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $trip,
                    'fa-briefcase',
                    $trip->purpose ?: $trip->destination,
                    $trip->date_from,
                    $trip->employee_name,
                    $trip->status ?: 'Pending',
                    $trip->ob_reference_no
                );

                $activity['actual_departure'] = $trip->actual_departure?->toIso8601String();
                $activity['actual_return'] = $trip->actual_return?->toIso8601String();
                $activity['duration_minutes'] = $trip->actual_departure && $trip->actual_return
                    ? (int) $trip->actual_departure->diffInMinutes($trip->actual_return)
                    : null;

                return $activity;
            });
    }

    private function emails(string $foreignKey, int $relatedId, User $user): Collection
    {
        if (! Schema::hasTable('email_activities') || ! Schema::hasColumn('email_activities', $foreignKey)) {
            return collect();
        }

        return EmailActivity::query()
            ->visibleTo($user)
            ->where($foreignKey, $relatedId)
            ->latest()
            ->get()
            ->map(fn (EmailActivity $email): array => $this->timelineActivity(
                ActivityTypeRegistry::EMAIL, $email, 'fa-envelope',
                $email->summary ?: $email->subject ?: 'Email #'.$email->id,
                $email->occurred_at, $email->sender_email,
                $email->handling ?: 'Linked', $email->subject
            ));
    }

    private function messages(string $foreignKey, int $relatedId, User $user): Collection
    {
        if (! Schema::hasTable('message_conversations') || ! Schema::hasColumn('message_conversations', $foreignKey)) {
            return collect();
        }

        return MessageConversation::query()
            ->when(
                ! $user->isAdmin() && ! $user->isSuperAdmin(),
                function ($query) use ($user): void {
                    $query->where(function ($scope) use ($user): void {
                        $scope->where('assigned_user_id', $user->id)
                            ->orWhere('prepared_by_user_id', $user->id);
                    });
                }
            )
            ->with(['assignedUser:id,name,email'])
            ->where($foreignKey, $relatedId)
            ->latest()
            ->get()
            ->map(fn (MessageConversation $message): array => $this->timelineActivity(
            ActivityTypeRegistry::MESSAGE, $message, 'fa-message',
            $message->conversation_summary ?: $message->conversation_title ?: 'Message Conversation #'.$message->id,
            $message->last_message_at ?: $message->started_at ?: $message->created_at,
            $message->assignedUser?->name ?: $message->assignedUser?->email,
            $message->handling ?: 'Open', $message->conversation_title
        ));
    }

    /** @param class-string<Model> $model */
    private function records(string $model, string $table, string $foreignKey, int $relatedId, array $with = []): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return collect();
        }

        return $model::query()->with($with)->where($foreignKey, $relatedId)->latest()->get();
    }

    private function timelineActivity(string $sourceType, Model $record, string $icon, ?string $description, mixed $occurredAt, ?string $owner, string $status, ?string $notes): array
    {
        $type = Str::headline(strtolower($sourceType));
        $canonicalType = strtolower(str_replace('_', '-', $sourceType));

        return [
            'id' => $canonicalType.'-'.$record->getKey(),
            'source_id' => (int) $record->getKey(),
            'source_activity_type' => $sourceType,
            'source' => 'main',
            'readonly' => true,
            'type' => $type,
            'icon' => $icon,
            'description' => filled($description) ? $description : $type.' #'.$record->getKey(),
            'when' => $this->formatWhen($occurredAt),
            'owner' => filled($owner) ? $owner : '-',
            'status' => $status,
            'notes' => $notes,
            'dueAt' => null,
            'sortAt' => $this->sortAt($occurredAt, $record->created_at),
        ];
    }

    private function formatWhen(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('M d, Y h:i A');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function sortAt(mixed $value, mixed $fallback): int
    {
        try {
            return filled($value) ? Carbon::parse($value)->timestamp : Carbon::parse($fallback)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }
}

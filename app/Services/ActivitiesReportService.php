<?php

namespace App\Services;

use App\Models\Call;
use App\Models\EmailActivity;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MessageConversation;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ActivitiesReportService
{
    public function __construct(
        private readonly ActivityAttachmentService $attachments,
        private readonly OfficialBusinessActivityService $officialBusinessActivities
    ) {}

    public function build(User $user, array $parameters): array
    {
        $authorized = $this->authorizedRows($user);
        $selectedIds = collect($parameters['selected_ids'] ?? [])->filter()->values();

        if ($selectedIds->isNotEmpty()) {
            $byId = $authorized->keyBy('id');
            $rows = $selectedIds
                ->unique()
                ->map(fn (string $id) => $byId->get(strtolower(trim($id))))
                ->filter()
                ->values();
            $selectionMode = 'selected';
            $appliedFilters = [];
        } else {
            $rows = $this->applyFilters($authorized, $parameters)
                ->sortBy([
                    ['occurred_at', 'desc'],
                    ['id', 'asc'],
                ])
                ->values();
            $selectionMode = 'filtered';
            $appliedFilters = collect($parameters)
                ->except(['selected_ids', 'format'])
                ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
                ->all();
        }

        $withDuration = $rows->filter(fn (array $row): bool => $row['duration_minutes'] !== null);
        $total = $rows->count();
        $durationByType = $withDuration
            ->groupBy('source_activity_type')
            ->map(fn (Collection $typeRows, string $type): array => [
                'activity_type' => $type,
                'activities_with_duration' => $typeRows->count(),
                'duration_minutes' => (int) $typeRows->sum('duration_minutes'),
                'duration_hours' => round(((int) $typeRows->sum('duration_minutes')) / 60, 2),
            ])
            ->values()
            ->all();
        if ($selectionMode === 'selected') {
            $selectedDates = $rows->pluck('occurred_at')->filter()->map(fn ($value) => Carbon::parse($value));
            $periodFrom = $selectedDates->isEmpty() ? null : $selectedDates->min()->copy()->startOfDay();
            $periodTo = $selectedDates->isEmpty() ? null : $selectedDates->max()->copy()->endOfDay();
        } else {
            [$periodFrom, $periodTo] = $this->dateRange($parameters);
        }

        return [
            'selection_mode' => $selectionMode,
            'finance_authorized' => app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($user),
            'filters' => $appliedFilters,
            'generated_at' => now()->toIso8601String(),
            'report_period' => [
                'from' => $periodFrom?->toIso8601String(),
                'to' => $periodTo?->toIso8601String(),
            ],
            'duration_coverage' => [
                'activities_with_duration' => $withDuration->count(),
                'total_activities' => $total,
                'coverage_percentage' => $total === 0
                    ? 0.0
                    : round(($withDuration->count() / $total) * 100, 2),
                'total_duration_minutes' => (int) $withDuration->sum('duration_minutes'),
                'total_duration_hours' => round(((int) $withDuration->sum('duration_minutes')) / 60, 2),
                'by_activity_type' => $durationByType,
            ],
            'rows' => $rows->all(),
        ];
    }

    private function authorizedRows(User $user): Collection
    {
        $standardLoads = [
            'company:id,company_name',
            'contactRecord:id,first_name,last_name',
            'deal:id,deal_code,deal_name',
            'project:id,project_code,name',
            'preparedBy:id,name,email',
        ];

        $tasks = Task::query()
            ->with(array_merge($standardLoads, ['primaryAssignee:id,name,email', 'notes']))
            ->get()
            ->map(fn (Task $task): array => $this->row(
                ActivityTypeRegistry::TASK,
                $task,
                $task->name,
                $task->start_date ?: $task->due_date ?: $task->created_at,
                $this->preferredDuration($task->actual_duration_minutes, $task->estimated_duration_minutes),
                $task->status,
                $task->assigned_to_user_id,
                $task->primaryAssignee?->name ?: $task->owner
            ));

        $events = Event::query()
            ->with(array_merge($standardLoads, ['notes']))
            ->get()
            ->map(fn (Event $event): array => $this->row(
                ActivityTypeRegistry::EVENT,
                $event,
                $event->title,
                $event->from ?: $event->created_at,
                $this->preferredDuration($event->duration_minutes ?? $this->hoursToMinutes($event->actual_hours), $this->hoursToMinutes($event->estimated_hours)),
                $event->status,
                null,
                $event->host
            ));

        $calls = Call::query()
            ->with(array_merge($standardLoads, ['notes']))
            ->get()
            ->map(fn (Call $call): array => $this->row(
                ActivityTypeRegistry::CALL,
                $call,
                $call->purpose ?: $call->contact ?: 'Call',
                $this->dateTime($call->start_time ?: $call->created_at, $call->start_time ? $call->start_hour : null),
                $call->duration_minutes ?? $this->durationToMinutes($call->duration),
                $call->completed ? 'Completed' : 'Open',
                null,
                $call->owner ?: $call->from
            ));

        $meetings = Meeting::query()
            ->with(array_merge($standardLoads, ['notes']))
            ->get()
            ->map(fn (Meeting $meeting): array => $this->row(
                ActivityTypeRegistry::MEETING,
                $meeting,
                $meeting->title,
                $this->dateTime($meeting->date ?: $meeting->created_at, $meeting->date ? $meeting->time : null),
                $this->preferredDuration(
                    $meeting->duration_minutes ?? $this->durationToMinutes($meeting->actual_duration),
                    $this->durationToMinutes($meeting->duration)
                ),
                $this->meetingDisplayStatus($meeting),
                null,
                $meeting->owner
            ));

        $messages = MessageConversation::query()
            ->visibleTo($user)
            ->with(['company:id,company_name', 'contact:id,first_name,last_name', 'assignedUser:id,name,email', 'preparedBy:id,name,email'])
            ->get()
            ->map(fn (MessageConversation $message): array => $this->messageRow($message));

        $emails = ! Schema::hasTable('email_activities')
            ? collect()
            : EmailActivity::query()
                ->visibleTo($user)
                ->with(['company:id,company_name', 'contactRecord:id,first_name,last_name', 'preparedBy:id,name,email'])
                ->get()
                ->map(fn (EmailActivity $email): array => $this->emailRow($email));

        $officialBusiness = $this->officialBusinessActivities
            ->context($user)['records']
            ->flatMap(function (OfficialBusinessTrip $trip) use ($user): array|Collection {
                if ((int) $trip->workflow_version < 3
                    || ! $trip->is_client_travel
                    || ! Schema::hasColumn('official_business_trip_tickets', 'company_id')) {
                    return [$this->officialBusinessRow($trip, null, $user)];
                }

                $tickets = $trip->tripTickets()
                    ->with('company:id,company_name')
                    ->orderBy('id')
                    ->get()
                    ->filter(fn (OfficialBusinessTripTicket $ticket): bool => Gate::forUser($user)->allows('view', $ticket));

                return $tickets->isEmpty()
                    ? [$this->officialBusinessRow($trip, null, $user)]
                    : $tickets->map(fn (OfficialBusinessTripTicket $ticket): array => $this->officialBusinessRow($trip, $ticket, $user));
            });

        return collect()
            ->merge($tasks)
            ->merge($events)
            ->merge($calls)
            ->merge($meetings)
            ->merge($emails)
            ->merge($messages)
            ->merge($officialBusiness)
            ->values();
    }

    private function meetingDisplayStatus(Meeting $meeting): ?string
    {
        $status = filled($meeting->status)
            ? (string) $meeting->status
            : null;

        if (strtolower(trim((string) $status)) !== 'upcoming') {
            return $status;
        }

        if (! $meeting->date || ! $meeting->time) {
            return $status;
        }

        try {
            $start = Carbon::parse($meeting->date.' '.$meeting->time);

            $durationMinutes = $meeting->duration_minutes;

            if ($durationMinutes === null) {
                $durationMinutes = $this->durationToMinutes($meeting->duration);
            }

            if ($durationMinutes === null) {
                $durationMinutes = 30;
            }

            $end = $start->copy()->addMinutes((int) $durationMinutes);

            return now()->greaterThanOrEqualTo($end)
                ? 'Completed'
                : $status;
        } catch (\Throwable) {
            return $status;
        }
    }
    private function row(
        string $type,
        $record,
        ?string $title,
        $occurredAt,
        ?int $duration,
        $status,
        ?int $assignedToUserId,
        ?string $assignedTo
    ): array {
        $attachments = $this->attachments->forActivity($type, $record);

        return [
            'id' => strtolower(str_replace('_', '-', $type)).'-'.$record->id,
            'source_activity_type' => $type,
            'source_activity_id' => $record->id,
            'title' => $title ?: Str::headline(strtolower($type)).' #'.$record->id,
            'occurred_at' => $this->dateTime($occurredAt),
            'duration_minutes' => $duration,
            'duration_hours' => $duration === null ? null : round($duration / 60, 2),
            'status' => filled($status) ? (string) $status : null,
            'prepared_by_user_id' => $record->prepared_by_user_id,
            'prepared_by' => $record->preparedBy?->name ?: $record->preparedBy?->email,
            'assigned_to_user_id' => $assignedToUserId,
            'assigned_to' => filled($assignedTo) ? $assignedTo : null,
            'company_id' => $record->company_id,
            'company' => $record->company?->company_name,
            'contact_id' => $record->contact_id,
            'contact' => $record->contactRecord
                ? trim($record->contactRecord->first_name.' '.$record->contactRecord->last_name)
                : null,
            'project_id' => $record->project_id,
            'project' => $record->project?->name,
            'deal_id' => $record->deal_id,
            'deal' => $record->deal?->deal_name,
            'client_report_description' => $record->client_report_description,
            'report_description' => $record->client_report_description ?: ($title ?: Str::headline(strtolower($type)).' #'.$record->id),
            'include_in_client_report' => (bool) $record->include_in_client_report,
            'has_documentation' => $attachments !== []
                || ($record instanceof Meeting && (
                    filled($record->structured_minutes)
                    || filled($record->finalized_minutes_at)
                    || filled($record->recording_metadata)
                    || filled($record->transcript_metadata)
                    || filled($record->files_metadata)
                )),
            'attachments' => $attachments,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    private function messageRow(MessageConversation $message): array
    {
        $contact = $message->contact;
        $attachments = $this->attachments->forActivity(ActivityTypeRegistry::MESSAGE, $message);

        return [
            'id' => 'message-'.$message->id,
            'source_activity_type' => ActivityTypeRegistry::MESSAGE,
            'source_activity_id' => $message->id,
            'title' => $message->conversation_title ?: 'Message Conversation #'.$message->id,
            'occurred_at' => $this->dateTime($message->last_message_at ?: $message->created_at),
            'duration_minutes' => $message->duration_minutes,
            'duration_hours' => $message->duration_minutes === null ? null : round($message->duration_minutes / 60, 2),
            'status' => $message->handling,
            'prepared_by_user_id' => $message->prepared_by_user_id,
            'prepared_by' => $message->preparedBy?->name ?: $message->preparedBy?->email,
            'assigned_to_user_id' => $message->assigned_user_id,
            'assigned_to' => $message->assignedUser?->name ?: $message->assignedUser?->email,
            'company_id' => $message->company_id,
            'company' => $message->company?->company_name,
            'contact_id' => $message->contact_id,
            'contact' => $contact ? trim($contact->first_name.' '.$contact->last_name) : null,
            'project_id' => null,
            'project' => null,
            'deal_id' => null,
            'deal' => null,
            'client_report_description' => $message->client_report_description,
            'report_description' => $message->client_report_description
                ?: $message->conversation_summary
                ?: ($message->conversation_title ?: 'Message Conversation #'.$message->id),
            'include_in_client_report' => (bool) $message->include_in_client_report,
            'has_documentation' => $attachments !== [],
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];
    }

    private function emailRow(EmailActivity $email): array
    {
        $attachments = $this->attachments->forActivity(ActivityTypeRegistry::EMAIL, $email);
        $recipients = collect($email->recipients ?? [])->filter()->implode(', ');
        $safeSummary = collect([
            'Subject: '.($email->subject ?: '(No subject)'),
            'From: '.$email->sender_email,
            'To: '.($recipients ?: '-'),
            'Direction: '.$email->direction,
            filled($email->summary) ? 'Summary: '.$email->summary : null,
        ])->filter()->implode("\n");
        $reportDescription = $email->client_report_description ?: $safeSummary;

        if ($email->include_body_in_client_report && filled($email->body)) {
            $reportDescription .= "\n\n".$email->body;
        }

        return [
            'id' => 'email-'.$email->id,
            'source_activity_type' => ActivityTypeRegistry::EMAIL,
            'source_activity_id' => $email->id,
            'title' => $email->subject ?: 'Email #'.$email->id,
            'occurred_at' => $this->dateTime($email->occurred_at),
            'duration_minutes' => null,
            'duration_hours' => null,
            'status' => $email->handling,
            'prepared_by_user_id' => $email->prepared_by_user_id,
            'prepared_by' => $email->preparedBy?->name ?: $email->preparedBy?->email,
            'assigned_to_user_id' => $email->mailbox_owner_user_id,
            'assigned_to' => null,
            'company_id' => $email->company_id,
            'company' => $email->company?->company_name,
            'contact_id' => $email->contact_id,
            'contact' => $email->contactRecord
                ? trim($email->contactRecord->first_name.' '.$email->contactRecord->last_name)
                : null,
            'project_id' => null,
            'project' => null,
            'deal_id' => null,
            'deal' => null,
            'client_report_description' => $email->client_report_description,
            'report_description' => $reportDescription,
            'include_in_client_report' => (bool) $email->include_in_client_report,
            'has_documentation' => $email->has_attachments || $attachments !== [],
            'attachments' => $attachments,
            'email_metadata' => [
                'subject' => $email->subject,
                'sender' => $email->sender_email,
                'recipients' => $email->recipients ?? [],
                'cc_recipients' => $email->cc_recipients ?? [],
                'timestamp' => $email->occurred_at?->toIso8601String(),
                'direction' => $email->direction,
                'summary' => $email->summary,
            ],
            'created_at' => $email->created_at?->toIso8601String(),
            'updated_at' => $email->updated_at?->toIso8601String(),
        ];
    }

    private function officialBusinessRow(
        OfficialBusinessTrip $trip,
        ?OfficialBusinessTripTicket $ticket,
        User $user
    ): array
    {
        $canReadFinance = app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($user);
        $ticketStops = $ticket && Schema::hasTable('official_business_trip_ticket_stops')
            ? $ticket->stops
            : collect();
        $ticketPersonnel = $ticket ? $ticket->personnel : collect();
        $treatment = $canReadFinance && $ticket && Schema::hasTable('official_business_trip_ticket_financial_treatments')
            ? $ticket->financialTreatment
            : null;
        $hasCost = $canReadFinance && $ticket && Schema::hasTable('official_business_trip_ticket_costs')
            ? $ticket->costs()->exists()
            : false;
        $costTotal = $canReadFinance && $ticket && Schema::hasTable('official_business_trip_ticket_costs')
            ? (float) $ticket->costs()->sum('amount')
            : null;
        $attachments = $ticket
            ? $this->attachments->forActivity(ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET, $ticket)
            : $this->attachments->forActivity(ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL, $trip);

        return [
            'id' => $ticket ? 'official-business-trip-ticket-'.$ticket->id : 'official-business-travel-'.$trip->id,
            'source_activity_type' => ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
            'source_activity_id' => $ticket?->id ?? $trip->id,
            'source_activity_parent_id' => $ticket ? $trip->id : null,
            'ob_reference' => $trip->ob_reference_no,
            'ob_ticket_reference' => $ticket?->reference_no,
            'ob_destination' => $ticket
                ? $ticketStops->pluck('destination')->filter()->implode(' / ')
                : $trip->destination,
            'ob_activity' => $ticket
                ? $ticketStops->pluck('activity')->filter()->implode(' / ')
                : $trip->purpose,
            'ob_transportation' => $trip->transportation_mode,
            'ob_employee' => collect([$trip->employee_name])
                ->merge($ticketPersonnel->pluck('employee_name_snapshot'))
                ->filter()->unique()->implode(' / '),
            'ob_department' => collect([$trip->department])
                ->merge($ticketPersonnel->pluck('department_snapshot'))
                ->filter()->unique()->implode(' / '),
            'ob_credit_usage' => $canReadFinance && $ticket
                ? (float) ($treatment?->travel_credit_units ?? 0) > 0
                : null,
            'ob_additional_cost' => $canReadFinance && $ticket
                ? $hasCost
                : null,
            'ob_billability' => $canReadFinance
                ? ($treatment?->billing_treatment ?? $trip->travel_billability)
                : null,
            'ob_travel_credit_units' => $canReadFinance ? $treatment?->travel_credit_units : null,
            'ob_travel_credit_value' => $canReadFinance ? $treatment?->travel_credit_value : null,
            'ob_additional_cost_total' => $costTotal,
            'title' => $trip->purpose ?: $trip->destination ?: 'Official Business #'.$trip->id,
            'occurred_at' => $this->dateTime($trip->date_from),
            'duration_minutes' => $trip->actual_departure && $trip->actual_return
                ? (int) $trip->actual_departure->diffInMinutes($trip->actual_return)
                : null,
            'duration_hours' => $trip->actual_departure && $trip->actual_return
                ? round($trip->actual_departure->diffInMinutes($trip->actual_return) / 60, 2)
                : null,
            'status' => $trip->status,
            'prepared_by_user_id' => $trip->prepared_by_user_id,
            'prepared_by' => $trip->preparedBy?->name ?: $trip->preparedBy?->email,
            'assigned_to_user_id' => null,
            'assigned_to' => $trip->employee_name,
            'company_id' => $ticket?->company_id ?? $trip->company_id,
            'company' => $ticket?->client_name_snapshot
                ?: ($trip->company?->company_name ?: ($trip->is_client_travel ? $trip->client_name : null)),
            'contact_id' => $trip->contact_id,
            'contact' => $trip->contactRecord
                ? trim($trip->contactRecord->first_name.' '.$trip->contactRecord->last_name)
                : null,
            'project_id' => $trip->project_id,
            'project' => $trip->project?->name,
            'deal_id' => $trip->deal_id,
            'deal' => $trip->deal?->deal_name,
            'client_report_description' => $trip->client_report_description,
            'report_description' => $trip->client_report_description
                ?: ($trip->purpose ?: $trip->destination ?: 'Official Business #'.$trip->id),
            'include_in_client_report' => (bool) $trip->include_in_client_report,
            'has_documentation' => $attachments !== [],
            'attachments' => $attachments,
            'created_at' => $trip->created_at?->toIso8601String(),
            'updated_at' => $trip->updated_at?->toIso8601String(),
        ];
    }

    private function applyFilters(Collection $rows, array $filters): Collection
    {
        if (array_key_exists('ob_master_ids', $filters)) {
            $masterIds = collect($filters['ob_master_ids'])->map(fn ($id): int => (int) $id)->all();
            $rows = $rows->filter(fn (array $row): bool => $row['source_activity_type'] === ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL
                && in_array((int) ($row['source_activity_parent_id'] ?? $row['source_activity_id']), $masterIds, true));
        }

        if (filled($filters['ob_client'] ?? null)) {
            $clients = collect(explode(', ', Str::lower(trim((string) $filters['ob_client']))))
                ->map(fn (string $name): string => trim($name));
            $rows = $rows->filter(fn (array $row): bool => $row['source_activity_type'] === ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL
                && $clients->contains(Str::lower((string) ($row['company'] ?? ''))));
        }

        foreach ([
            'ob_reference', 'ob_ticket_reference', 'ob_destination', 'ob_activity',
            'ob_transportation', 'ob_employee', 'ob_department', 'ob_billability',
        ] as $field) {
            if (filled($filters[$field] ?? null)) {
                $needle = Str::lower(trim((string) $filters[$field]));
                $rows = $rows->filter(fn (array $row): bool => $row['source_activity_type'] === ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL
                    && Str::contains(Str::lower((string) ($row[$field] ?? '')), $needle));
            }
        }

        foreach (['ob_credit_usage', 'ob_additional_cost'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $expected = $filters[$field] === 'with';
                $rows = $rows->filter(fn (array $row): bool => $row['source_activity_type'] === ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL
                    && $row[$field] !== null && $row[$field] === $expected);
            }
        }

        $types = collect($filters['activity_types'] ?? [])
            ->map(fn (string $type): string => strtoupper(str_replace(['-', ' '], '_', $type)))
            ->all();

        if ($types) {
            $rows = $rows->whereIn('source_activity_type', $types);
        }

        foreach ([
            'company_id',
            'contact_id',
            'project_id',
            'deal_id',
            'prepared_by_user_id',
            'assigned_to_user_id',
        ] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null && $filters[$field] !== '') {
                $rows = $rows->where($field, (int) $filters[$field]);
            }
        }

        if (filled($filters['status'] ?? null)) {
            $status = Str::lower(trim((string) $filters['status']));
            $rows = $rows->filter(fn (array $row): bool => Str::lower((string) $row['status']) === $status);
        }

        if (filled($filters['duration_availability'] ?? null)) {
            $withDuration = $filters['duration_availability'] === 'with';
            $rows = $rows->filter(fn (array $row): bool => ($row['duration_minutes'] !== null) === $withDuration);
        }

        if (filled($filters['documentation_availability'] ?? null)) {
            $withDocumentation = $filters['documentation_availability'] === 'with';
            $rows = $rows->filter(fn (array $row): bool => $row['has_documentation'] === $withDocumentation);
        }

        if (array_key_exists('include_in_client_report', $filters)
            && $filters['include_in_client_report'] !== null
            && $filters['include_in_client_report'] !== '') {
            $rows = $rows->where('include_in_client_report', (bool) $filters['include_in_client_report']);
        }

        [$from, $to] = $this->dateRange($filters);

        if ($from || $to) {
            $rows = $rows->filter(function (array $row) use ($from, $to): bool {
                if (! $row['occurred_at']) {
                    return false;
                }

                $date = Carbon::parse($row['occurred_at']);

                return (! $from || $date->greaterThanOrEqualTo($from))
                    && (! $to || $date->lessThanOrEqualTo($to));
            });
        }

        return $rows;
    }

    private function dateRange(array $filters): array
    {
        if (filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)) {
            return [
                filled($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from'])->startOfDay() : null,
                filled($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to'])->endOfDay() : null,
            ];
        }

        if (filled($filters['month'] ?? null)) {
            $month = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();

            return [$month, $month->copy()->endOfMonth()];
        }

        if (filled($filters['quarter'] ?? null)) {
            $year = (int) ($filters['year'] ?? now()->year);
            $quarter = (int) $filters['quarter'];
            $start = Carbon::create($year, (($quarter - 1) * 3) + 1, 1)->startOfDay();

            return [$start, $start->copy()->addMonths(3)->subSecond()];
        }

        if (filled($filters['year'] ?? null)) {
            $start = Carbon::create((int) $filters['year'], 1, 1)->startOfDay();

            return [$start, $start->copy()->endOfYear()];
        }

        return match ($filters['date_preset'] ?? null) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'last_quarter' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'year_to_date' => [now()->startOfYear(), now()->endOfDay()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'last_30_days' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            default => [null, null],
        };
    }

    private function dateTime($date, ?string $time = null): ?string
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

        return ($hours || $minutes)
            ? (int) round(((float) ($hours[1] ?? 0) * 60) + (float) ($minutes[1] ?? 0))
            : null;
    }

    private function preferredDuration(?int $actual, ?int $planned): ?int
    {
        return $actual;
    }
}

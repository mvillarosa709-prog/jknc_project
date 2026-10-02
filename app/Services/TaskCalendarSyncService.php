<?php

namespace App\Services;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TaskCalendarSyncService
{
    /** @return array{event_id: string} */
    public function sync(Task $task): array
    {
        $dueDate = $this->dueDate($task);

        if (! $dueDate) {
            throw new RuntimeException('The task does not have a valid due date for calendar synchronization.');
        }

        $timezone = (string) config('services.google_calendar.timezone', 'Asia/Manila');
        $start = $this->calendarStart($task, $dueDate, $timezone);
        $end = $start->addMinutes(30);

        $eventId = $task->external_calendar_event_id ?: $this->eventId($task);
        $creating = blank($task->external_calendar_event_id);
        $payload = [
            'id' => $eventId,
            'summary' => $task->name,
            'description' => $this->description($task),
            'start' => [
                'dateTime' => $start->format('Y-m-d\TH:i:sP'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end->format('Y-m-d\TH:i:sP'),
                'timeZone' => $timezone,
            ],
        ];

        if (! $creating) {
            unset($payload['id']);
        }

        if ($this->bridge()->enabled()) {
            $remote = $this->bridge()->sync(
                $payload,
                $eventId,
                $creating,
                ['sendUpdates' => 'none']
            );

            return ['event_id' => $remote['event_id']];
        }

        [$calendarId, $accessToken, $baseUrl] = $this->configuration();

        $eventUrl = $this->eventUrl($baseUrl, $calendarId, $eventId);
        $response = $creating
            ? $this->client($accessToken)->post($this->eventsUrl($baseUrl, $calendarId), $payload)
            : $this->client($accessToken)->patch($eventUrl, $payload);

        if ($creating && $response->status() === 409) {
            $response = $this->client($accessToken)->get($eventUrl);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Google Calendar synchronization failed with HTTP '.$response->status().'.');
        }

        return ['event_id' => (string) ($response->json('id') ?? $eventId)];
    }

    public function delete(Task $task): void
    {
        if (blank($task->external_calendar_event_id)) {
            return;
        }

        if ($this->bridge()->enabled()) {
            $this->bridge()->delete(
                (string) $task->external_calendar_event_id,
                ['sendUpdates' => 'none']
            );

            return;
        }

        [$calendarId, $accessToken, $baseUrl] = $this->configuration();
        $response = $this->client($accessToken)->delete(
            $this->eventUrl($baseUrl, $calendarId, (string) $task->external_calendar_event_id)
        );

        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            throw new RuntimeException('Google Calendar deletion failed with HTTP '.$response->status().'.');
        }
    }

    public function isSchedulable(Task $task): bool
    {
        return $this->dueDate($task) !== null;
    }

    private function dueDate(Task $task): ?CarbonImmutable
    {
        if (blank($task->due_date)) {
            return null;
        }

        try {
            return CarbonImmutable::parse(
                trim((string) $task->due_date),
                (string) config('services.google_calendar.timezone', 'Asia/Manila')
            )->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function calendarStart(
        Task $task,
        CarbonImmutable $dueDate,
        string $timezone
    ): CarbonImmutable {
        try {
            $createdAt = CarbonImmutable::instance($task->created_at)
                ->setTimezone($timezone);

            return $dueDate->setTime(
                $createdAt->hour,
                $createdAt->minute,
                0
            );
        } catch (Throwable) {
            return $dueDate->setTime(9, 0, 0);
        }
    }

    private function description(Task $task): ?string
    {
        $lines = collect([
            filled($task->description) ? trim((string) $task->description) : null,
            filled($task->priority) ? 'Priority: '.$task->priority : null,
            filled($task->related_to) ? 'Related to: '.$task->related_to : null,
            filled($task->owner) ? 'Owner: '.$task->owner : null,
        ])->filter()->values();

        return $lines->isEmpty() ? null : $lines->implode("\n");
    }

    /** @return array{string, string, string} */
    private function configuration(): array
    {
        $calendarId = trim((string) config('services.google_calendar.calendar_id'));
        $accessToken = trim((string) config('services.google_calendar.access_token'));

        if ($calendarId === '' || $accessToken === '') {
            throw new RuntimeException('Google Calendar credentials are not configured.');
        }

        return [
            $calendarId,
            $accessToken,
            rtrim((string) config('services.google_calendar.api_base', 'https://www.googleapis.com/calendar/v3'), '/'),
        ];
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.google_calendar.timeout', 15));
    }

    private function bridge(): GoogleCalendarBridgeClient
    {
        return app(GoogleCalendarBridgeClient::class);
    }

    private function eventsUrl(string $baseUrl, string $calendarId): string
    {
        return $baseUrl.'/calendars/'.rawurlencode($calendarId).'/events?sendUpdates=none';
    }

    private function eventUrl(string $baseUrl, string $calendarId, string $eventId): string
    {
        return $baseUrl.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId).'?sendUpdates=none';
    }

    private function eventId(Task $task): string
    {
        return 'ordotask'.$task->getKey();
    }
}

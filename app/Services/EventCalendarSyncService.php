<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class EventCalendarSyncService
{
    /** @return array{event_id: string} */
    public function sync(Event $event): array
    {
        $schedule = $this->schedule($event);

        if (! $schedule) {
            throw new RuntimeException('The event does not have a valid start and end for calendar synchronization.');
        }

        $eventId = $event->external_calendar_event_id ?: $this->eventId($event);
        $creating = blank($event->external_calendar_event_id);
        $timezone = (string) config('services.google_calendar.timezone', 'Asia/Manila');
        $payload = [
            'id' => $eventId,
            'summary' => $event->title,
            'description' => $this->description($event),
            'location' => filled($event->venue) ? $event->venue : null,
            'start' => [
                'dateTime' => $schedule['start']->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $schedule['end']->toIso8601String(),
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

    public function delete(Event $event): void
    {
        if (blank($event->external_calendar_event_id)) {
            return;
        }

        if ($this->bridge()->enabled()) {
            $this->bridge()->delete(
                (string) $event->external_calendar_event_id,
                ['sendUpdates' => 'none']
            );

            return;
        }

        [$calendarId, $accessToken, $baseUrl] = $this->configuration();
        $response = $this->client($accessToken)->delete(
            $this->eventUrl($baseUrl, $calendarId, (string) $event->external_calendar_event_id)
        );

        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            throw new RuntimeException('Google Calendar deletion failed with HTTP '.$response->status().'.');
        }
    }

    public function isSchedulable(Event $event): bool
    {
        return $this->schedule($event) !== null;
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable}|null */
    private function schedule(Event $event): ?array
    {
        if (blank($event->from) || blank($event->to)
            || ! $this->hasExplicitTime((string) $event->from)
            || ! $this->hasExplicitTime((string) $event->to)) {
            return null;
        }

        try {
            $timezone = (string) config('services.google_calendar.timezone', 'Asia/Manila');
            $start = CarbonImmutable::parse(trim((string) $event->from), $timezone);
            $end = CarbonImmutable::parse(trim((string) $event->to), $timezone);

            return $end->greaterThan($start) ? ['start' => $start, 'end' => $end] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function hasExplicitTime(string $value): bool
    {
        return preg_match('/(?:T|\s)\d{1,2}:\d{2}(?::\d{2})?\b/', trim($value)) === 1;
    }

    private function description(Event $event): ?string
    {
        $lines = collect([
            filled($event->event_type) ? 'Type: '.$event->event_type : null,
            filled($event->mode) ? 'Mode: '.$event->mode : null,
            filled($event->role) ? 'Role: '.$event->role : null,
            filled($event->related_to) ? 'Related to: '.$event->related_to : null,
            filled($event->host) ? 'Host: '.$event->host : null,
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

    private function eventId(Event $event): string
    {
        return 'ordoevent'.$event->getKey();
    }
}

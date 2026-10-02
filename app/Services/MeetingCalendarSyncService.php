<?php

namespace App\Services;

use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MeetingCalendarSyncService
{
    /** @return array{event_id: string, conference_url: ?string} */
    public function sync(Meeting $meeting, bool $createMeet, bool $notifyExternalAttendees): array
    {
        // Every synced Online/Hybrid meeting needs a native Google Meet conference.
        $createMeet = in_array($meeting->mode, ['Online', 'Hybrid'], true);
        $eventId = $meeting->external_calendar_event_id ?: $this->eventId($meeting);
        $creating = blank($meeting->external_calendar_event_id);
        $payload = $this->eventPayload($meeting, $eventId, $createMeet);

        if (! $creating) {
            unset($payload['id']);
        }
        $options = [
            'conferenceDataVersion' => 1,
            'sendUpdates' => $notifyExternalAttendees ? 'all' : 'none',
        ];

        if ($this->bridge()->enabled()) {
            $remote = $this->bridge()->sync(
                $payload,
                $eventId,
                $creating,
                $options
            );

            $conferenceUrl = $createMeet
                ? ($remote['conference_url'] ?? ($creating ? null : $meeting->conference_url))
                : null;

            if ($createMeet && blank($conferenceUrl)) {
                throw new RuntimeException(
                    'Google Calendar did not return a Google Meet URL.'
                );
            }

            $this->publishConferenceLocation($meeting, $remote['event_id'], $conferenceUrl, $payload);

            return [
                'event_id' => $remote['event_id'],
                'conference_url' => filled($conferenceUrl)
                    ? (string) $conferenceUrl
                    : null,
            ];
        }

        $calendarId = trim(
            (string) config(
                'services.google_calendar.calendar_id'
            )
        );

        $accessToken = trim(
            (string) config(
                'services.google_calendar.access_token'
            )
        );

        if ($calendarId === '' || $accessToken === '') {
            throw new RuntimeException(
                'Google Calendar credentials are not configured.'
            );
        }

        $query = http_build_query($options);
        $calendar = rawurlencode($calendarId);
        $baseUrl = rtrim((string) config('services.google_calendar.api_base', 'https://www.googleapis.com/calendar/v3'), '/');
        $url = $creating
            ? "{$baseUrl}/calendars/{$calendar}/events?{$query}"
            : "{$baseUrl}/calendars/{$calendar}/events/".rawurlencode($eventId)."?{$query}";

        $response = $creating
            ? $this->client($accessToken)->post($url, $payload)
            : $this->client($accessToken)->patch($url, $payload);

        if ($creating && $response->status() === 409) {
            $response = $this->client($accessToken)->get(
                "{$baseUrl}/calendars/{$calendar}/events/".rawurlencode($eventId)
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException('Google Calendar synchronization failed with HTTP '.$response->status().'.');
        }

        $remote = $response->json();
        $videoEntryPoint = collect($remote['conferenceData']['entryPoints'] ?? [])
            ->firstWhere('entryPointType', 'video');
        $conferenceUrl = $createMeet
            ? ($remote['hangoutLink']
                ?? ($videoEntryPoint['uri'] ?? null)
                ?? ($creating ? null : $meeting->conference_url))
            : null;

        if ($createMeet && blank($conferenceUrl)) {
            throw new RuntimeException('Google Calendar did not return a Google Meet URL.');
        }

        $this->publishConferenceLocation($meeting, (string) ($remote['id'] ?? $eventId), $conferenceUrl, $payload);

        return [
            'event_id' => (string) ($remote['id'] ?? $eventId),
            'conference_url' => filled($conferenceUrl) ? (string) $conferenceUrl : null,
        ];
    }

    public function delete(Meeting $meeting): void
    {
        if (blank($meeting->external_calendar_event_id)) {
            return;
        }

        if ($this->bridge()->enabled()) {
            $this->bridge()->delete(
                (string) $meeting->external_calendar_event_id,
                ['sendUpdates' => 'none']
            );

            return;
        }

        $calendarId = trim((string) config('services.google_calendar.calendar_id'));
        $accessToken = trim((string) config('services.google_calendar.access_token'));

        if ($calendarId === '' || $accessToken === '') {
            throw new RuntimeException('Google Calendar credentials are not configured.');
        }

        $baseUrl = rtrim((string) config('services.google_calendar.api_base', 'https://www.googleapis.com/calendar/v3'), '/');
        $url = $baseUrl.'/calendars/'.rawurlencode($calendarId).'/events/'
            .rawurlencode((string) $meeting->external_calendar_event_id).'?sendUpdates=none';
        $response = $this->client($accessToken)->delete($url);

        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            throw new RuntimeException('Google Calendar deletion failed with HTTP '.$response->status().'.');
        }
    }

    private function bridge(): GoogleCalendarBridgeClient
    {
        return app(GoogleCalendarBridgeClient::class);
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.google_calendar.timeout', 15));
    }

    private function eventPayload(Meeting $meeting, string $eventId, bool $createMeet): array
    {
        $timezone = (string) config('services.google_calendar.timezone', config('app.timezone'));
        $startsAt = Carbon::parse(trim($meeting->date.' '.$meeting->time), $timezone);
        $duration = max(1, (int) ($meeting->duration_minutes ?: 30));
        $attendees = collect($meeting->external_attendees ?? [])
            ->map(function ($attendee): ?array {
                $email = is_array($attendee) ? ($attendee['email'] ?? null) : $attendee;

                return filter_var($email, FILTER_VALIDATE_EMAIL) ? ['email' => $email] : null;
            })
            ->filter()
            ->unique('email')
            ->values()
            ->all();

        $payload = [
            'id' => $eventId,
            'summary' => $meeting->title,
            'description' => $meeting->description,
            'location' => $this->calendarLocation($meeting, $createMeet ? $meeting->conference_url : null),
            'start' => [
                'dateTime' => $startsAt->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $startsAt->copy()->addMinutes($duration)->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'attendees' => $attendees,
        ];

        if ($meeting->mode === 'Physical') {
            // Explicitly remove a previous conference when changing mode.
            $payload['conferenceData'] = null;
        } elseif ($createMeet && (blank($meeting->external_calendar_event_id) || blank($meeting->conference_url))) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'ordo-meeting-'.$meeting->getKey(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $payload;
    }

    private function calendarLocation(Meeting $meeting, ?string $conferenceUrl): string
    {
        $location = trim((string) $meeting->location);

        if (filled($conferenceUrl) && ! str_contains($location, $conferenceUrl)) {
            return trim($location."\n".$conferenceUrl);
        }

        return $location;
    }

    private function publishConferenceLocation(
        Meeting $meeting,
        string $eventId,
        ?string $conferenceUrl,
        array $sentPayload
    ): void {
        if (blank($conferenceUrl)) {
            return;
        }

        $location = $this->calendarLocation($meeting, $conferenceUrl);

        if ($location === ($sentPayload['location'] ?? '')) {
            return;
        }

        // Google's embedded popup omits conferenceData; it does show location.
        // PATCH only the display field, preserving the native conference and guests.
        $options = ['conferenceDataVersion' => 1, 'sendUpdates' => 'none'];

        if ($this->bridge()->enabled()) {
            $this->bridge()->sync(['location' => $location], $eventId, false, $options);

            return;
        }

        $baseUrl = rtrim((string) config('services.google_calendar.api_base', 'https://www.googleapis.com/calendar/v3'), '/');
        $url = $baseUrl.'/calendars/'.rawurlencode((string) config('services.google_calendar.calendar_id'))
            .'/events/'.rawurlencode($eventId).'?'.http_build_query($options);
        $response = $this->client((string) config('services.google_calendar.access_token'))
            ->patch($url, ['location' => $location]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Calendar conference display update failed with HTTP '.$response->status().'.');
        }
    }

    private function eventId(Meeting $meeting): string
    {
        return 'ordomeeting'.$meeting->getKey();
    }
}

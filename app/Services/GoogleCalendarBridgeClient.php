<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarBridgeClient
{
    public function enabled(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        $url = trim((string) config('services.google_calendar.bridge_url'));
        $secret = trim((string) config('services.google_calendar.bridge_secret'));

        return $url !== '' && $secret !== '';
    }

    /** @return array{event_id: string, conference_url: ?string, data: array} */
    public function sync(
        array $event,
        ?string $eventId,
        bool $creating,
        array $options = []
    ): array {
        $payload = [
            'action' => $creating ? 'create' : 'update',
            'event' => $event,
            'options' => $options,
        ];

        if (! $creating) {
            if (blank($eventId)) {
                throw new RuntimeException(
                    'Calendar event ID is required for bridge updates.'
                );
            }

            $payload['event_id'] = $eventId;
        }

        $result = $this->request($payload);

        $remote = is_array($result['data'] ?? null)
            ? $result['data']
            : [];

        $resolvedEventId = trim((string) (
            $result['event_id']
            ?? $remote['id']
            ?? $eventId
            ?? ''
        ));

        if ($resolvedEventId === '') {
            throw new RuntimeException(
                'Google Calendar bridge did not return an event ID.'
            );
        }

        $videoEntryPoint = collect($remote['conferenceData']['entryPoints'] ?? [])
            ->firstWhere('entryPointType', 'video');
        $conferenceUrl = $result['conference_url']
            ?? $remote['hangoutLink']
            ?? ($videoEntryPoint['uri'] ?? null);

        return [
            'event_id' => $resolvedEventId,
            'conference_url' => filled($conferenceUrl)
                ? (string) $conferenceUrl
                : null,
            'data' => $remote,
        ];
    }

    public function delete(
        string $eventId,
        array $options = []
    ): void {
        if (blank($eventId)) {
            return;
        }

        $this->request([
            'action' => 'delete',
            'event_id' => $eventId,
            'options' => $options,
        ]);
    }

    /** @return array{status: int, calendar: ?string, timezone: ?string} */
    public function ping(): array
    {
        $result = $this->request([
            'action' => 'ping',
        ]);

        return [
            'status' => (int) ($result['status'] ?? 200),
            'calendar' => filled($result['calendar'] ?? null)
                ? (string) $result['calendar']
                : null,
            'timezone' => filled($result['timezone'] ?? null)
                ? (string) $result['timezone']
                : null,
        ];
    }

    private function request(array $payload): array
    {
        [$url, $secret] = $this->configuration();

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(
                (int) config(
                    'services.google_calendar.timeout',
                    15
                )
            )
            ->post(
                $url,
                array_merge(
                    ['secret' => $secret],
                    $payload
                )
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Calendar bridge failed with HTTP '
                .$response->status().'.'
            );
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new RuntimeException(
                'Google Calendar bridge returned an invalid response.'
            );
        }

        if (($result['ok'] ?? false) !== true) {
            $error = trim(
                (string) ($result['error'] ?? '')
            );

            throw new RuntimeException(
                $error !== ''
                    ? 'Google Calendar bridge request failed: '.$error
                    : 'Google Calendar bridge request failed.'
            );
        }

        return $result;
    }

    /** @return array{string, string} */
    private function configuration(): array
    {
        $url = trim(
            (string) config(
                'services.google_calendar.bridge_url'
            )
        );

        $secret = trim(
            (string) config(
                'services.google_calendar.bridge_secret'
            )
        );

        if ($url === '' || $secret === '') {
            throw new RuntimeException(
                'Google Calendar bridge is not configured.'
            );
        }

        return [$url, $secret];
    }
}
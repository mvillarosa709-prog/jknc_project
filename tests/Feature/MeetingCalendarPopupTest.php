<?php

use App\Models\Meeting;
use App\Services\GoogleCalendarBridgeClient;
use App\Services\MeetingCalendarSyncService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.google_calendar', [
        'calendar_id' => 'calendar@example.test',
        'access_token' => 'fake-token',
        'api_base' => 'https://calendar.test/v3',
        'bridge_url' => 'https://bridge.test/exec',
        'bridge_secret' => 'fake-secret',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::preventStrayRequests();
    $this->meeting = new Meeting([
        'title' => 'Calendar popup meeting',
        'mode' => 'Online',
        'date' => '2026-09-11',
        'time' => '16:30',
        'duration_minutes' => 60,
        'location' => '',
    ]);
    $this->meeting->id = 23;
    $this->meetUrl = 'https://meet.google.com/abc-defg-hij';
});

test('online and hybrid creates native Meet then publishes returned URL in popup location', function ($mode) {
    $this->meeting->mode = $mode;
    $this->meeting->location = $mode === 'Hybrid' ? 'Client Office' : '';
    Http::fake(fn () => Http::response([
        'id' => 'ordomeeting23',
        'conferenceData' => ['entryPoints' => [
            ['entryPointType' => 'video', 'uri' => $this->meetUrl],
        ]],
    ]));

    $result = app(MeetingCalendarSyncService::class)->sync($this->meeting, false, false);

    expect($result['conference_url'])->toBe($this->meetUrl);
    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);
    $create = $requests[0][0];
    $display = $requests[1][0];
    expect($create->method())->toBe('POST')
        ->and($create['conferenceData']['createRequest']['requestId'])->toBe('ordo-meeting-23')
        ->and($create['conferenceData']['createRequest']['conferenceSolutionKey']['type'])->toBe('hangoutsMeet')
        ->and($create->url())->toContain('conferenceDataVersion=1', 'sendUpdates=none')
        ->and($display->method())->toBe('PATCH')
        ->and($display->data())->toBe(['location' => trim($this->meeting->location."\n".$this->meetUrl)])
        ->and($display->url())->toContain('/events/ordomeeting23?', 'conferenceDataVersion=1', 'sendUpdates=none');
})->with(['Online', 'Hybrid']);

test('updates preserve conference data and venue without duplicating the Meet URL', function () {
    $this->meeting->mode = 'Hybrid';
    $this->meeting->external_calendar_event_id = 'ordomeeting23';
    $this->meeting->conference_url = $this->meetUrl;
    $this->meeting->location = 'Client Office';
    Http::fake(fn () => Http::response(['id' => 'ordomeeting23', 'hangoutLink' => $this->meetUrl]));
    $service = app(MeetingCalendarSyncService::class);
    $service->sync($this->meeting, true, false);
    $service->sync($this->meeting, true, false);

    Http::assertSentCount(2);
    foreach (Http::recorded() as [$request]) {
        expect($request->method())->toBe('PATCH')
            ->and($request->data())->not->toHaveKey('conferenceData')
            ->and($request->data())->not->toHaveKey('id')
            ->and($request['location'])->toBe("Client Office\n".$this->meetUrl);
    }
});

test('new calendar event requests a conference even when ORDO has a stored URL', function () {
    $this->meeting->conference_url = $this->meetUrl;
    Http::fake(fn () => Http::response(['id' => 'ordomeeting23', 'hangoutLink' => $this->meetUrl]));
    app(MeetingCalendarSyncService::class)->sync($this->meeting, true, false);
    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && data_get($r->data(), 'conferenceData.createRequest.requestId') === 'ordo-meeting-23');
});

test('physical meetings remove native conference and do not publish stored Meet URLs', function () {
    $this->meeting->mode = 'Physical';
    $this->meeting->location = 'Office';
    $this->meeting->external_calendar_event_id = 'ordomeeting23';
    $this->meeting->conference_url = $this->meetUrl;
    Http::fake(fn () => Http::response(['id' => 'ordomeeting23']));
    $result = app(MeetingCalendarSyncService::class)->sync($this->meeting, true, false);

    expect($result['conference_url'])->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r['location'] === 'Office'
        && array_key_exists('conferenceData', $r->data())
        && $r['conferenceData'] === null);
});

test('explicit attendee notifications apply only to main sync and display patch stays silent', function () {
    Http::fake(fn () => Http::response(['id' => 'ordomeeting23', 'hangoutLink' => $this->meetUrl]));
    app(MeetingCalendarSyncService::class)->sync($this->meeting, true, true);
    $requests = Http::recorded();
    expect($requests[0][0]->url())->toContain('sendUpdates=all')
        ->and($requests[1][0]->url())->toContain('sendUpdates=none');
});

test('bridge forwards conference options and extracts video entry points for the popup', function () {
    $bridge = Mockery::mock(GoogleCalendarBridgeClient::class)->makePartial();
    $bridge->shouldReceive('enabled')->andReturn(true);
    app()->instance(GoogleCalendarBridgeClient::class, $bridge);
    Http::fake(['https://bridge.test/exec' => Http::response([
        'ok' => true,
        'data' => [
            'id' => 'ordomeeting23',
            'conferenceData' => ['entryPoints' => [
                ['entryPointType' => 'phone', 'uri' => 'tel:+123'],
                ['entryPointType' => 'video', 'uri' => $this->meetUrl],
            ]],
        ],
    ])]);
    $result = app(MeetingCalendarSyncService::class)->sync($this->meeting, true, false);

    expect($result['conference_url'])->toBe($this->meetUrl);
    $requests = Http::recorded();
    expect($requests)->toHaveCount(2)
        ->and($requests[0][0]['action'])->toBe('create')
        ->and(data_get($requests[0][0]->data(), 'event.conferenceData.createRequest.requestId'))->toBe('ordo-meeting-23')
        ->and($requests[0][0]['options'])->toBe(['conferenceDataVersion' => 1, 'sendUpdates' => 'none'])
        ->and($requests[1][0]['action'])->toBe('update')
        ->and($requests[1][0]['event_id'])->toBe('ordomeeting23')
        ->and($requests[1][0]['event'])->toBe(['location' => $this->meetUrl])
        ->and($requests[1][0]['options'])->toBe(['conferenceDataVersion' => 1, 'sendUpdates' => 'none']);
});

test('pending conference can be retried with same event ID and request ID', function () {
    Http::fakeSequence()
        ->push(['id' => 'ordomeeting23', 'conferenceData' => ['createRequest' => ['status' => ['statusCode' => 'pending']]]])
        ->push([], 409)
        ->push(['id' => 'ordomeeting23', 'hangoutLink' => $this->meetUrl])
        ->push(['id' => 'ordomeeting23']);
    $service = app(MeetingCalendarSyncService::class);
    expect(fn () => $service->sync($this->meeting, true, false))
        ->toThrow(RuntimeException::class, 'Google Calendar did not return a Google Meet URL.');
    $result = $service->sync($this->meeting, true, false);
    expect($result['event_id'])->toBe('ordomeeting23')
        ->and($result['conference_url'])->toBe($this->meetUrl);
    $posts = Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST')->values();
    expect($posts[0][0]['conferenceData'])->toBe($posts[1][0]['conferenceData']);
});

test('failed popup publication reports failure for a safe retry', function () {
    Http::fakeSequence()
        ->push(['id' => 'ordomeeting23', 'hangoutLink' => $this->meetUrl])
        ->push([], 503);
    expect(fn () => app(MeetingCalendarSyncService::class)->sync($this->meeting, true, false))
        ->toThrow(RuntimeException::class, 'Google Calendar conference display update failed with HTTP 503.');
});

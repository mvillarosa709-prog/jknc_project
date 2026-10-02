<?php

use App\Jobs\ProcessMessagingWebhook;
use App\Models\ActivityAttachment;
use App\Models\MessageConversation;
use App\Models\MessageEntry;
use App\Models\MessagingWebhookEvent;
use App\Services\Messaging\MessengerProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Never run the application's MySQL-only historical data migrations in tests.
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    Http::preventStrayRequests();
    config(['messaging.messenger.enabled' => true, 'messaging.messenger.outbound_enabled' => false,
        'messaging.messenger.page_access_token' => 'test-only-token', 'messaging.messenger.page_id' => '100',
        'messaging.messenger.app_secret' => 'test-only-secret', 'messaging.messenger.verify_token' => 'test-only-verify',
        'messaging.messenger.inbox_user_id' => null]);
    foreach (['users', 'companies', 'contacts'] as $table) {
        Schema::create($table, fn (Blueprint $t) => $t->id());
    }
    foreach (['2026_08_17_181335_create_message_conversations_table.php',
        '2026_08_17_181337_create_message_entries_table.php'] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    Schema::table('message_conversations', fn (Blueprint $t) => $t->string('direction')->nullable());
    Schema::table('message_entries', function (Blueprint $t) {
        $t->string('channel')->nullable();
        $t->string('message_type')->default('text');
        $t->timestamp('delivered_at')->nullable();
        $t->timestamp('read_at')->nullable();
        $t->unique(['channel', 'external_message_id']);
    });
    Schema::create('activity_attachments', function (Blueprint $t) {
        $t->id();
        $t->string('activity_type');
        $t->unsignedBigInteger('activity_id');
        foreach (['filename', 'original_filename', 'external_url', 'category', 'mime_type'] as $column) {
            $t->text($column)->nullable();
        }
        $t->timestamp('uploaded_at')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
    });
    (require database_path('migrations/2026_09_14_143451_create_message_entry_attachments_table.php'))->up();
    (require database_path('migrations/2026_09_14_170000_create_messaging_provider_foundation.php'))->up();
    $this->payload = fn (array $event) => ['object' => 'page', 'entry' => [['id' => '100', 'messaging' => [$event]]]];
    $this->event = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'timestamp' => 1789372800000, 'message' => ['mid' => 'mid.test.1', 'text' => 'CODEX-INBOUND-TEST']];
    $this->postWebhook = function (array $payload, ?string $signature = null) {
        $raw = json_encode($payload);
        return $this->call('POST', '/webhooks/messenger', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256'.'='.hash_hmac('sha256', $raw, 'test-only-secret'),
        ], $raw);
    };
});

test('verification rejects wrong tokens and disabled configuration', function () {
    $this->get('/webhooks/messenger?hub.mode=subscribe&hub.verify_token=test-only-verify&hub.challenge=123')
        ->assertOk()->assertSeeText('123');
    $this->get('/webhooks/messenger?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=123')->assertForbidden();
    config(['messaging.messenger.enabled' => false]);
    $this->get('/webhooks/messenger')->assertStatus(503);
    ($this->postWebhook)(($this->payload)($this->event))->assertStatus(503);
});

test('signed account-scoped webhook persists encrypted payload and queues only an event ID', function () {
    Queue::fake();
    $payload = ($this->payload)($this->event);
    ($this->postWebhook)($payload, 'sha256=bad')->assertForbidden();
    $wrong = $payload;
    $wrong['entry'][0]['id'] = '999';
    ($this->postWebhook)($wrong)->assertForbidden();
    ($this->postWebhook)($payload)->assertOk();
    ($this->postWebhook)($payload)->assertOk();
    expect(MessagingWebhookEvent::count())->toBe(1);
    $stored = MessagingWebhookEvent::first();
    expect(DB::table('messaging_webhook_events')->value('payload'))->not->toContain('CODEX-INBOUND-TEST');
    Queue::assertPushed(ProcessMessagingWebhook::class, fn ($job) => $job->eventId === $stored->id);
    (new ProcessMessagingWebhook($stored->id))->handle(app(MessengerProvider::class));
    (new ProcessMessagingWebhook($stored->id))->handle(app(MessengerProvider::class));
    expect($stored->fresh()->status)->toBe('processed')->and($stored->fresh()->payload['diagnostic_version'])->toBe(1)
        ->and(json_encode($stored->fresh()->payload))->not->toContain('CODEX-INBOUND-TEST');
    expect(MessageEntry::count())->toBe(1);
});

test('inbound messages reuse the canonical conversation and link only safe explicit media', function () {
    $this->event['message']['attachments'] = [
        ['type' => 'image', 'payload' => ['url' => 'https://scontent.test.fbcdn.net/photo.jpg']],
        ['type' => 'file', 'payload' => ['url' => 'http://127.0.0.1/private']],
        ['type' => 'file', 'payload' => ['url' => 'https://evil.test/file.pdf']],
    ];
    $provider = app(MessengerProvider::class);
    $provider->ingest(($this->payload)($this->event));
    $provider->ingest(($this->payload)($this->event));
    $entry = MessageEntry::first();
    expect(MessageEntry::count())->toBe(1)->and(MessageConversation::count())->toBe(1);
    expect($entry->direction)->toBe('Incoming')->and($entry->delivery_status)->toBe('Received')
        ->and($entry->external_message_id)->toBe('mid.test.1')
        ->and($entry->technical_metadata['external_delivery_attempted'])->toBeFalse()
        ->and($entry->activityAttachments()->count())->toBe(1)
        ->and(ActivityAttachment::count())->toBe(1);
    $conversation = $entry->conversation;
    expect($conversation->external_conversation_id)->toBe('100:200')->and($conversation->contact_id)->toBeNull();
    $this->event['message']['mid'] = 'mid.test.2';
    $provider->ingest(($this->payload)($this->event));
    expect(MessageConversation::count())->toBe(1)->and(MessageEntry::count())->toBe(2);
});

test('out-of-order read and delivery events update provider echoes but never local Pending replies', function () {
    $provider = app(MessengerProvider::class);
    $receipt = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'timestamp' => 1789372802000, 'read' => ['watermark' => 1789372802000]];
    $provider->ingest(($this->payload)($receipt));
    $echo = $this->event;
    $echo['sender']['id'] = '100';
    $echo['recipient']['id'] = '200';
    $echo['message']['is_echo'] = true;
    $provider->ingest(($this->payload)($echo));
    $entry = MessageEntry::first();
    expect($entry->delivery_status)->toBe('Read')->and($entry->read_at)->not->toBeNull();
    $pending = $entry->conversation->entries()->create(['channel' => 'Messenger', 'direction' => 'Outgoing',
        'body' => 'local pending', 'delivery_status' => 'Pending', 'sent_at' => now(),
        'technical_metadata' => ['external_delivery_attempted' => false]]);
    $receipt = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'], 'timestamp' => 1789372803000,
        'delivery' => ['mids' => [$entry->external_message_id], 'watermark' => 1789372803000]];
    $provider->ingest(($this->payload)($receipt));
    expect($entry->fresh()->delivery_status)->toBe('Read')
        ->and($pending->fresh()->delivery_status)->toBe('Pending')
        ->and($pending->fresh()->technical_metadata['external_delivery_attempted'])->toBeFalse();
    expect(fn () => $provider->send($pending))->toThrow(LogicException::class);
    Http::assertNothingSent();
});

test('failed ingestion is atomic and does not leak its payload into job errors', function () {
    $payload = ($this->payload)($this->event);
    $payload['entry'][] = ['id' => 'wrong'];
    $record = MessagingWebhookEvent::create(['provider' => 'messenger', 'fingerprint' => hash('sha256', 'bad'), 'payload' => $payload]);
    expect(fn () => (new ProcessMessagingWebhook($record->id))->handle(app(MessengerProvider::class)))
        ->toThrow(RuntimeException::class, 'Messenger webhook processing failed; inspect event status.');
    expect(MessageEntry::count())->toBe(0)->and($record->fresh()->status)->toBe('failed');
});

test('explicit existing conversation and contact relationships survive provider matching', function () {
    DB::table('contacts')->insert(['id' => 1]);
    DB::table('companies')->insert(['id' => 1]);
    $conversation = MessageConversation::create(['channel' => 'Messenger', 'line_account' => '100',
        'external_conversation_id' => '100:200', 'company_id' => 1, 'contact_id' => 1]);
    app(MessengerProvider::class)->ingest(($this->payload)($this->event));
    expect(MessageConversation::count())->toBe(1)
        ->and(MessageEntry::first()->message_conversation_id)->toBe($conversation->id)
        ->and($conversation->fresh()->company_id)->toBe(1)->and($conversation->fresh()->contact_id)->toBe(1);
});

test('delivery mids arriving before an echo are retained and applied without a watermark', function () {
    $provider = app(MessengerProvider::class);
    $provider->ingest(($this->payload)(['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'timestamp' => 1789372802000, 'delivery' => ['mids' => ['mid.test.1']]]));
    $echo = $this->event;
    $echo['sender']['id'] = '100';
    $echo['recipient']['id'] = '200';
    $echo['message']['is_echo'] = true;
    $provider->ingest(($this->payload)($echo));
    expect(MessageEntry::first()->delivery_status)->toBe('Delivered');
});

test('malformed signed event collections are rejected before queueing', function () {
    Queue::fake();
    $this->event['message']['attachments'] = 'not-an-array';
    ($this->postWebhook)(($this->payload)($this->event))->assertStatus(400);
    Queue::assertNothingPushed();
    expect(MessagingWebhookEvent::count())->toBe(0);
});

test('verification returns the exact challenge and rejects malformed query parameters', function () {
    $this->get('/webhooks/messenger?hub.mode=subscribe&hub.verify_token=test-only-verify&hub.challenge=000123')
        ->assertOk()->assertContent('000123');
    foreach (['', '?hub.mode=unsubscribe&hub.verify_token=test-only-verify&hub.challenge=1',
        '?hub.mode=subscribe&hub.verify_token[]=test-only-verify&hub.challenge=1',
        '?hub.mode=subscribe&hub.verify_token=test-only-verify&hub.challenge='] as $query) {
        $this->get('/webhooks/messenger'.$query)->assertForbidden();
    }
});

test('missing malformed incorrect and body-mismatched signatures never queue data', function () {
    Queue::fake();
    $raw = json_encode(($this->payload)($this->event));
    $this->call('POST', '/webhooks/messenger', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)->assertForbidden();
    foreach (['sha256=bad', 'sha1='.str_repeat('0', 40), 'sha256='.str_repeat('0', 64),
        'sha256='.hash_hmac('sha256', $raw.' ', 'test-only-secret')] as $signature) {
        ($this->postWebhook)(($this->payload)($this->event), $signature)->assertForbidden();
    }
    Queue::assertNothingPushed();
    expect(MessagingWebhookEvent::count())->toBe(0);
});

test('signed text retries with different envelopes still create exactly one canonical entry', function () {
    Queue::fake();
    $this->event['message']['metadata'] = 'not-for-storage';
    ($this->postWebhook)(($this->payload)($this->event))->assertOk();
    $record = MessagingWebhookEvent::first();
    expect(json_encode($record->payload))->not->toContain('not-for-storage');
    (new ProcessMessagingWebhook($record->id))->handle(app(MessengerProvider::class));
    $this->event['message']['metadata'] = 'different-envelope';
    ($this->postWebhook)(($this->payload)($this->event))->assertOk();
    foreach (MessagingWebhookEvent::all() as $record) {
        (new ProcessMessagingWebhook($record->id))->handle(app(MessengerProvider::class));
    }
    $entry = MessageEntry::sole();
    expect($entry->direction)->toBe('Incoming')->and($entry->channel)->toBe('Messenger')
        ->and($entry->delivery_status)->toBe('Received')->and($entry->received_at)->not->toBeNull()
        ->and($entry->sent_at)->toBeNull()->and($entry->external_message_id)->toBe('mid.test.1');
});

test('media references without a usable URL are captured without unsafe frontend URLs', function () {
    $this->event['message']['attachments'] = [
        ['type' => 'image', 'payload' => ['attachment_id' => 'provider-media-1']],
        ['type' => 'file', 'payload' => ['url' => 'https://scontent.test.fbcdn.net/file?access_token=fake-sensitive-token']],
        ['type' => 'audio', 'payload' => ['url' => 'https://scontent.test.fbcdn.net/audio']],
    ];
    app(MessengerProvider::class)->ingest(($this->payload)($this->event));
    $entry = MessageEntry::sole();
    $references = $entry->technical_metadata['media_references'];
    expect($references[0]['provider_attachment_id'])->toBe('provider-media-1')
        ->and($references[0]['reference_state'])->toBe('unavailable')
        ->and($references[1]['reference_state'])->toBe('blocked')
        ->and(json_encode($entry->technical_metadata))->not->toContain('fake-sensitive-token')
        ->and($entry->activityAttachments()->count())->toBe(1)
        ->and($entry->activityAttachments()->first()->metadata['provider_type'])->toBe('audio');
});

test('unsupported events are acknowledged without creating peers or messages', function () {
    Queue::fake();
    ($this->postWebhook)(($this->payload)(['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'postback' => ['payload' => 'confidential-unsupported']]))->assertOk();
    expect(MessageEntry::count())->toBe(0)->and(MessageConversation::count())->toBe(0)
        ->and(DB::table('messaging_provider_peers')->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('provider identity separates people and rejects a corrupt conversation pointer', function () {
    $provider = app(MessengerProvider::class);
    $provider->ingest(($this->payload)($this->event));
    $this->event['sender']['id'] = '201';
    $this->event['message']['mid'] = 'second-person';
    $provider->ingest(($this->payload)($this->event));
    expect(MessageConversation::count())->toBe(2);
    DB::table('messaging_provider_peers')->where('peer_id', '201')->update(['message_conversation_id' => MessageConversation::first()->id]);
    $this->event['message']['mid'] = 'third-message';
    expect(fn () => $provider->ingest(($this->payload)($this->event)))->toThrow(LogicException::class, 'Provider conversation identity mismatch.');
    expect(MessageEntry::count())->toBe(2);
});

test('duplicate delivery and read receipts do not rewrite timestamps or touch another sender', function () {
    $provider = app(MessengerProvider::class);
    $echo = $this->event;
    $echo['sender']['id'] = '100';
    $echo['recipient']['id'] = '200';
    $echo['message']['is_echo'] = true;
    $provider->ingest(($this->payload)($echo));
    $entry = MessageEntry::sole();
    $delivery = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'], 'timestamp' => 1789372802000,
        'delivery' => ['mids' => [$entry->external_message_id]]];
    $provider->ingest(($this->payload)($delivery));
    $saved = (array) DB::table('messaging_provider_receipts')->first();
    $entryUpdated = $entry->fresh()->updated_at->toIso8601String();
    $this->travel(10)->seconds();
    $provider->ingest(($this->payload)($delivery));
    expect((array) DB::table('messaging_provider_receipts')->first())->toBe($saved)
        ->and($entry->fresh()->updated_at->toIso8601String())->toBe($entryUpdated);
    $read = ['sender' => ['id' => '201'], 'recipient' => ['id' => '100'], 'read' => ['watermark' => 1789372803000]];
    $provider->ingest(($this->payload)($read));
    expect($entry->fresh()->delivery_status)->toBe('Delivered');
    $read['sender']['id'] = '200';
    $provider->ingest(($this->payload)($read));
    $readAt = $entry->fresh()->read_at->toIso8601String();
    $this->travel(10)->seconds();
    $provider->ingest(($this->payload)($read));
    expect($entry->fresh()->delivery_status)->toBe('Read')->and($entry->fresh()->read_at->toIso8601String())->toBe($readAt);
    $this->travelBack();
});

test('receipt watermark preserves subsecond provider ordering', function () {
    $provider = app(MessengerProvider::class);
    $echo = $this->event;
    $echo['sender']['id'] = '100';
    $echo['recipient']['id'] = '200';
    $echo['message']['is_echo'] = true;
    $echo['timestamp'] = 1789372800999;
    $provider->ingest(($this->payload)($echo));
    $provider->ingest(($this->payload)(['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'read' => ['watermark' => 1789372800001]]));
    expect(MessageEntry::sole()->delivery_status)->toBe('Sent');
});

test('disabled workers preserve pending webhook events and synchronous configuration is rejected', function () {
    $record = MessagingWebhookEvent::create(['provider' => 'messenger', 'fingerprint' => hash('sha256', 'disabled'),
        'payload' => ($this->payload)($this->event)]);
    config(['messaging.messenger.enabled' => false]);
    (new ProcessMessagingWebhook($record->id))->handle(app(MessengerProvider::class));
    expect($record->fresh()->status)->toBe('pending')->and($record->fresh()->attempts)->toBe(0)
        ->and(MessageEntry::count())->toBe(0);
    config(['messaging.messenger.enabled' => true, 'messaging.messenger.queue_connection' => 'sync']);
    ($this->postWebhook)(($this->payload)($this->event))->assertStatus(503);
});

test('actual local Store Reply controller remains Pending and never calls a provider', function () {
    Schema::table('users', function (Blueprint $t) {
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->string('role')->nullable();
    });
    DB::table('users')->insert(['id' => 1, 'name' => 'Test owner', 'email' => 'owner@example.test', 'role' => 'admin']);
    $user = App\Models\User::findOrFail(1);
    auth()->setUser($user);
    $conversation = MessageConversation::create(['channel' => 'Messenger', 'assigned_user_id' => 1]);
    $request = Illuminate\Http\Request::create('/api/message-conversations/'.$conversation->id.'/messages', 'POST', [
        'body' => 'CODEX-LOCAL-PENDING-REGRESSION', 'direction' => 'Outgoing', 'delivery_status' => 'Pending',
        'attachment_ids' => [], 'technical_metadata' => ['external_delivery_attempted' => false],
    ]);
    $request->setUserResolver(fn () => $user);
    app()->instance('request', $request);
    $response = app(App\Http\Controllers\MessageConversationController::class)
        ->storeMessage($request, $conversation, app(App\Support\ActivityDuplicateGuard::class));
    expect($response->getStatusCode())->toBe(201);
    $entry = MessageEntry::sole();
    expect($entry->direction)->toBe('Outgoing')->and($entry->delivery_status)->toBe('Pending')
        ->and($entry->technical_metadata['external_delivery_attempted'])->toBeFalse()
        ->and($entry->external_message_id)->toBeNull();
    Http::assertNothingSent();
});

test('operational logs do not contain credentials message bodies or arbitrary metadata', function () {
    $logs = [];
    Illuminate\Support\Facades\Log::shouldReceive('info', 'notice', 'warning', 'error')
        ->andReturnUsing(function ($message, $context = []) use (&$logs) { $logs[] = [$message, $context]; });
    Queue::fake();
    $this->get('/webhooks/messenger?hub.mode=subscribe&hub.verify_token=private-wrong-token&hub.challenge=1')->assertForbidden();
    ($this->postWebhook)(($this->payload)($this->event), 'private-wrong-signature')->assertForbidden();
    ($this->postWebhook)(($this->payload)($this->event))->assertOk();
    $job = new ProcessMessagingWebhook(MessagingWebhookEvent::sole()->id);
    $job->handle(app(MessengerProvider::class));
    $job->handle(app(MessengerProvider::class));
    $serialized = json_encode($logs);
    expect($serialized)->toContain('messenger.verification_rejected', 'messenger.signature_rejected', 'messenger.message_processed', 'messenger.duplicate_job_ignored')
        ->not->toContain('private-wrong-token', 'private-wrong-signature', 'test-only-secret', 'test-only-verify', 'CODEX-INBOUND-TEST');
});

test('queue failure returns a safe retryable response while retaining the encrypted event', function () {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('confidential-queue-error'));
    $response = ($this->postWebhook)(($this->payload)($this->event));
    $response->assertStatus(503);
    expect($response->getContent())->not->toContain('confidential-queue-error');
    expect(MessagingWebhookEvent::sole()->status)->toBe('pending')
        ->and(MessageEntry::count())->toBe(0);
});

test('invalid timestamps and nested scalar payloads are rejected before persistence', function () {
    Queue::fake();
    $this->event['timestamp'] = 32503680000000;
    ($this->postWebhook)(($this->payload)($this->event))->assertStatus(400);
    $this->event['timestamp'] = 1789372800000;
    $this->event['message']['attachments'] = [['type' => 'image', 'payload' => 'invalid']];
    ($this->postWebhook)(($this->payload)($this->event))->assertStatus(400);
    expect(MessagingWebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

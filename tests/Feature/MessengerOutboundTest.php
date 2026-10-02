<?php

use App\Http\Controllers\MessageConversationController;
use App\Jobs\SendMessengerMessage;
use App\Models\MessageConversation;
use App\Models\MessageEntry;
use App\Models\MessengerOutboundAttempt;
use App\Models\User;
use App\Services\Messaging\MessengerOutbound;
use App\Services\Messaging\MessengerProvider;
use App\Support\ActivityDuplicateGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    Http::preventStrayRequests();
    Queue::fake();
    config(['messaging.messenger.enabled' => true, 'messaging.messenger.outbound_enabled' => true,
        'messaging.messenger.page_id' => '100', 'messaging.messenger.page_access_token' => 'private-test-token',
        'messaging.messenger.app_secret' => 'private-test-secret', 'messaging.messenger.graph_version' => 'v22.0',
        'messaging.messenger.queue_connection' => 'database', 'messaging.messenger.inbox_user_id' => null,
        'messaging.messenger.public_media_url' => 'https://media.example.test']);
    foreach (['users', 'contacts', 'companies'] as $table) {
        Schema::create($table, fn (Blueprint $t) => $t->id());
    }
    Schema::table('users', function (Blueprint $t) {
        $t->string('name')->nullable(); $t->string('email')->nullable(); $t->string('role')->nullable();
    });
    DB::table('users')->insert(['id' => 1, 'name' => 'Test', 'email' => 'test@example.test', 'role' => 'admin']);
    auth()->setUser(User::find(1));
    foreach (['2026_08_17_181335_create_message_conversations_table.php', '2026_08_17_181337_create_message_entries_table.php'] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    Schema::table('message_conversations', fn (Blueprint $t) => $t->string('direction')->nullable());
    Schema::table('message_entries', function (Blueprint $t) {
        $t->string('channel')->nullable(); $t->string('message_type')->default('text');
        $t->timestamp('delivered_at')->nullable(); $t->timestamp('read_at')->nullable();
        $t->unique(['channel', 'external_message_id']);
    });
    Schema::create('activity_attachments', function (Blueprint $t) {
        $t->id();
        $t->string('activity_type');
        $t->unsignedBigInteger('activity_id');
        $t->string('filename')->nullable();
        $t->string('original_filename')->nullable();
        $t->string('storage_disk')->nullable();
        $t->string('storage_path')->nullable();
        $t->string('external_url')->nullable();
        $t->string('mime_type')->nullable();
        $t->unsignedBigInteger('size_bytes')->nullable();
        $t->string('category')->nullable();
        $t->unsignedBigInteger('uploaded_by_user_id')->nullable();
        $t->timestamp('uploaded_at')->nullable();
        $t->text('metadata')->nullable();
        $t->timestamps();
    });
    foreach (['2026_09_14_143451_create_message_entry_attachments_table.php',
        '2026_09_14_170000_create_messaging_provider_foundation.php',
        '2026_09_15_100000_create_messenger_outbound_attempts_table.php'] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->provider = app(MessengerProvider::class);
    $this->outbound = app(MessengerOutbound::class);
    $this->payload = fn ($event) => ['object' => 'page', 'entry' => [['id' => '100', 'messaging' => [$event]]]];
    $this->event = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'timestamp' => now()->subMinute()->getTimestampMs(), 'message' => ['mid' => 'inbound-1', 'text' => 'Private inbound']];
    $this->provider->ingest(($this->payload)($this->event));
    $this->conversation = MessageConversation::sole();
    $this->store = fn ($id = null) => $this->outbound->store($this->conversation, 1, 'Private reply', $id ?? (string) Str::uuid());
    $this->reply = function (array $input) {
        $request = Request::create('/api/message-conversations/'.$this->conversation->id.'/messages', 'POST', $input);
        $request->setUserResolver(fn () => User::find(1));
        app()->instance('request', $request);
        return app(MessageConversationController::class)->storeMessage($request, $this->conversation, app(ActivityDuplicateGuard::class));
    };
});

test('disabled outbound keeps controller replies local Pending and cannot send historical entries', function () {
    config(['messaging.messenger.outbound_enabled' => false]);
    ($this->reply)(['body' => 'Local reply', 'delivery_status' => 'Sent', 'external_message_id' => 'spoof',
        'technical_metadata' => ['external_delivery_attempted' => true]]);
    $entry = MessageEntry::where('direction', 'Outgoing')->sole();
    expect($entry->delivery_status)->toBe('Pending')->and($entry->external_message_id)->toBeNull()
        ->and($entry->technical_metadata['external_delivery_attempted'])->toBeFalse();
    config(['messaging.messenger.outbound_enabled' => true]);
    $this->provider->send($entry);
    Http::assertNothingSent(); Queue::assertNothingPushed();
});

test('explicit controller send is durable queued and idempotent even beyond the old duplicate window', function () {
    $input = ['body' => 'Private reply', 'send_external' => true, 'client_request_id' => (string) Str::uuid()];
    $first = ($this->reply)($input)->getData(true);
    $this->travel(1)->minutes();
    $second = ($this->reply)($input)->getData(true);
    expect($second['id'])->toBe($first['id'])->and(MessageEntry::where('direction', 'Outgoing')->count())->toBe(1);
    Queue::assertPushed(SendMessengerMessage::class);
    expect($first['delivery_status'])->toBe('Pending')->and($first['sent_at'])->toBeNull()
        ->and(json_encode($first))->not->toContain('private-test-token', 'private-test-secret');
    Http::assertNothingSent();
});

test('Graph success saves provider identity and duplicate job execution never resends', function () {
    $entry = ($this->store)();
    Http::fake(['graph.facebook.com/*' => Http::response(['recipient_id' => '200', 'message_id' => 'out-1'])]);
    (new SendMessengerMessage($entry->id))->handle($this->provider);
    (new SendMessengerMessage($entry->id))->handle($this->provider);
    expect($entry->fresh()->delivery_status)->toBe('Sent')->and($entry->fresh()->external_message_id)->toBe('out-1')
        ->and($entry->fresh()->sent_at)->not->toBeNull()->and($entry->fresh()->technical_metadata['external_delivery_attempted'])->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v22.0/100/messages'
        && $request['recipient']['id'] === '200' && $request['messaging_type'] === 'RESPONSE'
        && str_starts_with($request['message']['metadata'], 'jknc:'));
});

test('missing or changed PSID fails closed without Graph', function () {
    $entry = ($this->store)();
    MessengerOutboundAttempt::query()->update(['peer_id' => null]);
    $this->provider->send($entry);
    expect($entry->fresh()->delivery_status)->toBe('Failed')
        ->and($entry->fresh()->technical_metadata['external_delivery_attempted'])->toBeFalse();
    Http::assertNothingSent();
});

test('permanent Graph rejections are safely recorded without raw errors or retry', function ($status) {
    $entry = ($this->store)();
    Http::fake(['*' => Http::response(['error' => [
        'code' => 190,
        'error_subcode' => 463,
        'message' => 'private-test-token private-test-secret',
    ]], $status)]);

    $this->provider->send($entry);
    $this->provider->send($entry);

    $entry = $entry->fresh();

    expect($entry->delivery_status)->toBe('Failed')
        ->and($entry->technical_metadata['failure_code'])->toBe('graph_http_'.$status)
        ->and($entry->technical_metadata['provider_error_code'])->toBe(190)
        ->and($entry->technical_metadata['provider_error_subcode'])->toBe(463)
        ->and($entry->toJson())->not->toContain('private-test-token', 'private-test-secret');
    Http::assertSentCount(1);
})->with([400, 401, 403]);

test('explicit 429 rejection retries with backoff then succeeds', function () {
    $entry = ($this->store)();
    Http::fake(['*' => Http::sequence()->push(['error' => ['code' => 4]], 429)->push(['recipient_id' => '200', 'message_id' => 'out-1'])]);
    expect(fn () => $this->provider->send($entry))->toThrow(RuntimeException::class);
    expect(MessengerOutboundAttempt::sole()->state)->toBe('retry')->and($entry->fresh()->delivery_status)->toBe('Pending');
    expect(fn () => $this->provider->send($entry))->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
    $this->travel(31)->seconds();
    $this->provider->send($entry);
    expect($entry->fresh()->delivery_status)->toBe('Sent'); Http::assertSentCount(2);
});

test('ambiguous 5xx or malformed success is not blindly retried', function ($status) {
    $entry = ($this->store)();
    Http::fake(['*' => Http::response(['unexpected' => 'private-test-token'], $status)]);
    $this->provider->send($entry); $this->provider->send($entry);
    expect(MessengerOutboundAttempt::sole()->state)->toBe('unknown')
        ->and($entry->fresh()->technical_metadata['failure_code'])->toBe('acceptance_unknown');
    Http::assertSentCount(1);
})->with([200, 500, 503]);

test('connection timeout is uncertain and cannot be replayed by a worker retry', function () {
    $entry = ($this->store)(); $calls = 0;
    Http::fake(function () use (&$calls) { $calls++; throw new Illuminate\Http\Client\ConnectionException('private-test-token'); });
    $this->provider->send($entry); $this->provider->send($entry);
    expect($calls)->toBe(1)->and(MessengerOutboundAttempt::sole()->state)->toBe('unknown');
});

test('echo before HTTP response reconciles one canonical entry then receipts never regress', function () {
    $entry = ($this->store)();
    $echo = $this->event;
    $echo['sender']['id'] = '100'; $echo['recipient']['id'] = '200'; $echo['timestamp'] = now()->getTimestampMs();
    $echo['message'] = ['mid' => 'out-1', 'is_echo' => true, 'metadata' => 'jknc:'.MessengerOutboundAttempt::sole()->correlation_id, 'text' => 'Private reply'];
    Http::fake(function () use ($echo) {
        $this->provider->ingest(($this->payload)($echo));
        return Http::response(['recipient_id' => '200', 'message_id' => 'out-1']);
    });
    $this->provider->send($entry);
    $this->provider->ingest(($this->payload)($echo));
    $delivery = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'],
        'timestamp' => now()->addSecond()->getTimestampMs(), 'delivery' => ['mids' => ['out-1']]];
    $this->provider->ingest(($this->payload)($delivery));
    expect($entry->fresh()->delivery_status)->toBe('Delivered');
    $read = ['sender' => ['id' => '200'], 'recipient' => ['id' => '100'], 'read' => ['watermark' => now()->addSeconds(2)->getTimestampMs()]];
    $this->provider->ingest(($this->payload)($read));
    $this->provider->ingest(($this->payload)($read));
    $this->provider->ingest(($this->payload)($delivery));
    $this->outbound->accepted(MessengerOutboundAttempt::sole()->id, 'out-1');
    expect(MessageEntry::count())->toBe(2)->and($entry->fresh()->delivery_status)->toBe('Read')
        ->and($entry->fresh()->read_at)->not->toBeNull()->and($entry->fresh()->delivered_at)->not->toBeNull();
});

test('linked attachments and expired response windows are never sent', function ($reason) {
    $entry = ($this->store)();
    if ($reason === 'attachment') {
        $id = DB::table('activity_attachments')->insertGetId(['activity_type' => 'MESSAGE', 'activity_id' => $this->conversation->id]);
        $entry->activityAttachments()->attach($id);
    } else {
        $this->travel(25)->hours();
    }
    $this->provider->send($entry);
    expect($entry->fresh()->delivery_status)->toBe('Failed'); Http::assertNothingSent();
    if ($reason === 'attachment') expect($entry->activityAttachments()->count())->toBe(1);
})->with(['attachment', 'expired']);

test('disabled queued sends are cancelled rather than delivered on later activation', function () {
    $entry = ($this->store)();
    config(['messaging.messenger.outbound_enabled' => false]);
    (new SendMessengerMessage($entry->id))->handle($this->provider);
    config(['messaging.messenger.outbound_enabled' => true]);
    (new SendMessengerMessage($entry->id))->handle($this->provider);
    expect(MessengerOutboundAttempt::sole()->state)->toBe('cancelled'); Http::assertNothingSent();
});

test('worker interruption preserves uncertain acceptance and never sends again', function () {
    $entry = ($this->store)();
    MessengerOutboundAttempt::query()->update(['state' => 'attempting', 'attempts' => 1, 'attempted_at' => now()->subMinute()]);
    $entry->update(['technical_metadata' => ['external_delivery_attempted' => true]]);
    $this->provider->send($entry);
    expect(MessengerOutboundAttempt::sole()->state)->toBe('unknown')->and($entry->fresh()->delivery_status)->toBe('Failed');
    Http::assertNothingSent();
});

test('failed-job hook safely records exhausted queue work', function () {
    $entry = ($this->store)();
    (new SendMessengerMessage($entry->id))->failed(new RuntimeException('private-test-token'));
    expect($entry->fresh()->delivery_status)->toBe('Failed')
        ->and($entry->fresh()->toJson())->not->toContain('private-test-token');
    Http::assertNothingSent();
});

test('echo recovers acknowledgement persistence failure without a second provider send', function () {
    $entry = ($this->store)();
    Http::fake(['*' => Http::response(['recipient_id' => '200', 'message_id' => 'out-1'])]);
    $failing = new class extends MessengerOutbound {
        public function accepted(int $attemptId, string $mid, ?int $timestamp = null): void { throw new RuntimeException('Simulated persistence failure'); }
    };
    expect(fn () => $failing->send($entry))->toThrow(RuntimeException::class);
    $this->provider->send($entry);
    $echo = ['sender' => ['id' => '100'], 'recipient' => ['id' => '200'], 'timestamp' => now()->getTimestampMs(),
        'message' => ['mid' => 'out-1', 'is_echo' => true, 'metadata' => 'jknc:'.MessengerOutboundAttempt::sole()->correlation_id]];
    $this->provider->ingest(($this->payload)($echo));
    expect($entry->fresh()->delivery_status)->toBe('Sent')->and(MessageEntry::count())->toBe(2);
    Http::assertSentCount(1);
});

test('outbound logs contain operational identifiers but no credentials or bodies', function () {
    $logs = [];
    Illuminate\Support\Facades\Log::shouldReceive('info', 'warning')->andReturnUsing(function ($message, $context = []) use (&$logs) { $logs[] = [$message, $context]; });
    $entry = ($this->store)();
    Http::fake(['*' => Http::response(['error' => ['message' => 'private-test-token Private reply']], 403)]);
    $this->provider->send($entry);
    expect(json_encode($logs))->toContain('messenger.outbound_queued', 'messenger.outbound_attempt', 'messenger.outbound_failure')
        ->not->toContain('private-test-token', 'private-test-secret', 'Private reply');
});

test('outbound readiness reports both switches without revealing credentials', function () {
    Illuminate\Support\Facades\Artisan::call('messenger:readiness');
    expect(Illuminate\Support\Facades\Artisan::output())->toContain('Messenger outbound enabled: YES', 'Outbound transport configured: YES', 'Outbound transport enabled: YES')
        ->not->toContain('private-test-token', 'private-test-secret');
    config(['messaging.messenger.enabled' => false]);
    expect(MessengerOutbound::enabled())->toBeFalse();
    Http::assertNothingSent();
});

test('echo without metadata racing HTTP is merged by exact acknowledged MID only', function () {
    $entry = ($this->store)();
    $echo = ['sender' => ['id' => '100'], 'recipient' => ['id' => '200'], 'timestamp' => now()->getTimestampMs(),
        'message' => ['mid' => 'out-1', 'is_echo' => true, 'text' => 'Private reply']];
    Http::fake(function () use ($echo) {
        $this->provider->ingest(($this->payload)($echo));
        return Http::response(['recipient_id' => '200', 'message_id' => 'out-1']);
    });
    $this->provider->send($entry);
    $this->provider->ingest(($this->payload)($echo));
    expect(MessageEntry::count())->toBe(2)->and($entry->fresh()->external_message_id)->toBe('out-1')
        ->and($entry->fresh()->user_id)->toBe(1)->and($entry->fresh()->technical_metadata['merged_echo_entry_id'])->not->toBeNull();
    Http::assertSentCount(1);
});

test('late echo uses provider time to reconcile a read that arrived before HTTP acknowledgement', function () {
    $entry = ($this->store)();
    $sentMs = now()->getTimestampMs();
    Http::fake(function () use ($sentMs) {
        $this->provider->ingest(($this->payload)(['sender' => ['id' => '200'], 'recipient' => ['id' => '100'], 'read' => ['watermark' => $sentMs + 1000]]));
        $this->travel(5)->seconds();
        return Http::response(['recipient_id' => '200', 'message_id' => 'out-1']);
    });
    $this->provider->send($entry);
    expect($entry->fresh()->delivery_status)->toBe('Sent');
    $this->provider->ingest(($this->payload)(['sender' => ['id' => '100'], 'recipient' => ['id' => '200'], 'timestamp' => $sentMs,
        'message' => ['mid' => 'out-1', 'is_echo' => true, 'metadata' => 'jknc:'.MessengerOutboundAttempt::sole()->correlation_id]]));
    expect($entry->fresh()->delivery_status)->toBe('Read');
});

test('single public image is sent through Messenger with the configured public media URL', function () {
    $attachmentId = DB::table('activity_attachments')->insertGetId([
        'activity_type' => 'MESSAGE',
        'activity_id' => $this->conversation->id,
        'filename' => 'photo.jpg',
        'original_filename' => 'photo.jpg',
        'storage_disk' => 'public',
        'storage_path' => 'activities/message/'.$this->conversation->id.'/photo.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 58193,
        'category' => 'image',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $requestId = (string) Str::uuid();

    $response = ($this->reply)([
        'body' => 'Image reply',
        'send_external' => true,
        'client_request_id' => $requestId,
        'attachment_ids' => [$attachmentId],
    ])->getData(true);

    $entry = MessageEntry::findOrFail($response['id']);

    expect($entry->activityAttachments()->count())->toBe(1)
        ->and($entry->delivery_status)->toBe('Pending');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'recipient_id' => '200',
            'message_id' => 'image-out-1',
        ]),
    ]);

    $this->provider->send($entry);

    expect($entry->fresh()->delivery_status)->toBe('Sent')
        ->and($entry->fresh()->external_message_id)->toBe('image-out-1');

    Http::assertSentCount(1);

    Http::assertSent(function ($request) use ($entry) {
        return $request->url() === 'https://graph.facebook.com/v22.0/100/messages'
            && $request['recipient']['id'] === '200'
            && $request['messaging_type'] === 'RESPONSE'
            && $request['message']['attachment']['type'] === 'image'
            && $request['message']['attachment']['payload']['url']
                === 'https://media.example.test/storage/activities/message/'.$entry->message_conversation_id.'/photo.jpg'
            && $request['message']['attachment']['payload']['is_reusable'] === false
            && str_starts_with($request['message']['metadata'], 'jknc:')
            && ! array_key_exists('text', $request['message']);
    });
});

test('unsupported Messenger attachment types fail closed without Graph delivery', function ($mime, $path) {
    $attachmentId = DB::table('activity_attachments')->insertGetId([
        'activity_type' => 'MESSAGE',
        'activity_id' => $this->conversation->id,
        'filename' => basename($path),
        'original_filename' => basename($path),
        'storage_disk' => 'public',
        'storage_path' => $path,
        'mime_type' => $mime,
        'size_bytes' => 1000,
        'category' => 'document',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entry = $this->outbound->store(
        $this->conversation,
        1,
        'Unsupported attachment',
        (string) Str::uuid(),
        [$attachmentId]
    );

    $this->provider->send($entry);

    expect($entry->fresh()->delivery_status)->toBe('Failed')
        ->and($entry->fresh()->technical_metadata['failure_code'])
        ->toBe('unsupported_attachment');

    Http::assertNothingSent();
})->with([
    ['application/pdf', 'activities/message/test/document.pdf'],
    ['audio/mpeg', 'activities/message/test/audio.mp3'],
    ['video/mp4', 'activities/message/test/video.mp4'],
]);

test('multiple Messenger attachments fail closed without Graph delivery', function () {
    $ids = collect(['one.jpg', 'two.jpg'])->map(function ($name) {
        return DB::table('activity_attachments')->insertGetId([
            'activity_type' => 'MESSAGE',
            'activity_id' => $this->conversation->id,
            'filename' => $name,
            'original_filename' => $name,
            'storage_disk' => 'public',
            'storage_path' => 'activities/message/'.$this->conversation->id.'/'.$name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
            'category' => 'image',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->all();

    $entry = $this->outbound->store(
        $this->conversation,
        1,
        'Multiple images',
        (string) Str::uuid(),
        $ids
    );

    $this->provider->send($entry);

    expect($entry->fresh()->delivery_status)->toBe('Failed')
        ->and($entry->fresh()->technical_metadata['failure_code'])
        ->toBe('multiple_attachments_not_supported');

    Http::assertNothingSent();
});

test('Messenger image request id is idempotent only for the same attachment set', function () {
    $attachmentId = DB::table('activity_attachments')->insertGetId([
        'activity_type' => 'MESSAGE',
        'activity_id' => $this->conversation->id,
        'filename' => 'photo.jpg',
        'original_filename' => 'photo.jpg',
        'storage_disk' => 'public',
        'storage_path' => 'activities/message/'.$this->conversation->id.'/photo.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 58193,
        'category' => 'image',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $requestId = (string) Str::uuid();

    $first = $this->outbound->store(
        $this->conversation,
        1,
        'Image reply',
        $requestId,
        [$attachmentId]
    );

    $second = $this->outbound->store(
        $this->conversation,
        1,
        'Image reply',
        $requestId,
        [$attachmentId]
    );

    expect($second->id)->toBe($first->id)
        ->and(MessageEntry::where('direction', 'Outgoing')->count())
        ->toBe(1);

    expect(fn () => $this->outbound->store(
        $this->conversation,
        1,
        'Image reply',
        $requestId,
        []
    ))->toThrow(
        \Symfony\Component\HttpKernel\Exception\HttpException::class
    );
});

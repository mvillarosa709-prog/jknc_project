<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    Http::preventStrayRequests();
    config(['messaging.messenger.outbound_enabled' => false]);
});

test('Messenger readiness reports flags without exposing any configuration values', function () {
    config(['messaging.messenger.enabled' => false, 'messaging.messenger.app_id' => 'private-app-id',
        'messaging.messenger.app_secret' => 'private-app-secret', 'messaging.messenger.page_id' => 'private-page-id',
        'messaging.messenger.page_access_token' => 'private-page-token', 'messaging.messenger.verify_token' => 'private-verify-token',
        'messaging.messenger.inbox_user_id' => null, 'messaging.messenger.queue_connection' => 'private-misplaced-secret',
        'messaging.messenger.queue' => 'private-queue-value', 'app.url' => 'https://private-host.test',
        'app.key' => 'private-app-key']);
    expect(Artisan::call('messenger:readiness'))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('Messenger enabled: NO', 'app_secret configured: YES', 'inbox_user_id configured: NO',
        'Messenger outbound enabled: NO', 'Outbound transport configured: NO', 'Outbound transport enabled: NO',
        'Webhook GET route: YES', 'Webhook POST route: YES', 'Queue connection: CUSTOM/MISSING')
        ->not->toContain('private-');
    Http::assertNothingSent();
});

test('Messenger diagnostics show only numeric linkage and queue summaries and do not mutate data', function () {
    foreach (['users', 'activity_attachments', 'messaging_provider_peers', 'messaging_provider_receipts'] as $table) {
        Schema::create($table, fn (Blueprint $t) => $t->id());
    }
    Schema::create('message_conversations', function (Blueprint $t) { $t->id(); $t->string('channel'); });
    Schema::create('message_entries', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('message_conversation_id'); $t->string('channel'); $t->string('direction');
        $t->string('external_message_id'); $t->timestamp('received_at'); $t->string('delivery_status');
        $t->json('technical_metadata')->nullable();
    });
    Schema::create('message_entry_attachments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('message_entry_id'); });
    Schema::create('messaging_webhook_events', function (Blueprint $t) { $t->id(); $t->string('provider'); $t->string('status'); });
    Schema::create('jobs', function (Blueprint $t) { $t->id(); $t->string('queue'); $t->integer('reserved_at')->nullable(); });
    Schema::create('failed_jobs', function (Blueprint $t) { $t->id(); $t->string('queue'); $t->text('payload'); });
    DB::table('message_conversations')->insert(['id' => 1, 'channel' => 'Messenger']);
    DB::table('message_entries')->insert(['id' => 2, 'message_conversation_id' => 1, 'channel' => 'Messenger',
        'direction' => 'Incoming', 'external_message_id' => 'private-provider-id', 'received_at' => now(), 'delivery_status' => 'Received']);
    DB::table('failed_jobs')->insert(['queue' => 'messaging', 'payload' => 'ProcessMessagingWebhook private-job-content']);
    config(['messaging.messenger.queue_connection' => 'database', 'messaging.messenger.queue' => 'messaging',
        'queue.connections.database.connection' => 'sqlite', 'queue.failed.database' => 'sqlite', 'queue.failed.driver' => 'database-uuids']);
    expect(Artisan::call('messenger:readiness', ['--diagnostics' => true, '--entry' => '2']))->toBe(0);
    expect(Artisan::output())->toContain('Entry ID: 2', 'Conversation ID: 1', 'Direction: Incoming', 'Delivery status: Received', 'Rows sharing this external_message_id: 1',
        'Failed Messenger jobs: 1', 'Queue waiting jobs: 0')->not->toContain('private-');
    expect(DB::table('message_entries')->count())->toBe(1)->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0);
    Http::assertNothingSent();
});

test('Messenger diagnostics redact infrastructure exceptions and reject invalid entry IDs', function () {
    Schema::shouldReceive('hasTable')->andThrow(new RuntimeException('private-database-error'));
    expect(Artisan::call('messenger:readiness', ['--diagnostics' => true]))->toBe(0);
    expect(Artisan::output())->toContain('diagnostics: UNAVAILABLE')->not->toContain('private-database-error');
    expect(Artisan::call('messenger:readiness', ['--entry' => 'invalid-secret']))->toBe(2);
    expect(Artisan::output())->not->toContain('invalid-secret');
});

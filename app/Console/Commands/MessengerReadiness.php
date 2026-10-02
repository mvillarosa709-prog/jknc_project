<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class MessengerReadiness extends Command
{
    protected $signature = 'messenger:readiness {--diagnostics : Read safe database/queue summaries} {--entry= : Inspect any internal Messenger MessageEntry ID}';
    protected $description = 'Read-only Messenger staging checks; never prints secrets, bodies or raw payloads';

    public function handle(): int
    {
        $this->line('Messenger enabled: '.(config('messaging.messenger.enabled') ? 'YES' : 'NO'));
        foreach (['app_id', 'app_secret', 'page_id', 'page_access_token', 'verify_token', 'inbox_user_id'] as $key) {
            $this->line($key.' configured: '.(filled(config('messaging.messenger.'.$key)) ? 'YES' : 'NO'));
        }
        $connection = config('messaging.messenger.queue_connection');
        $driver = config('queue.connections.'.$connection.'.driver');
        // Never echo arbitrary configuration: even a misplaced secret stays private.
        $this->line('Queue connection: '.(in_array($connection, ['database', 'redis', 'sqs', 'beanstalkd', 'sync', 'null'], true) ? $connection : 'CUSTOM/MISSING'));
        $this->line('Queue name: '.(config('messaging.messenger.queue') === 'messaging' ? 'messaging' : 'CUSTOM/MISSING'));
        $this->line('Persistent queue driver: '.(in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true) ? 'YES' : 'NO'));
        foreach (['verify' => 'GET', 'receive' => 'POST'] as $name => $method) {
            $route = Route::getRoutes()->getByName('webhooks.messenger.'.$name);
            $this->line('Webhook '.$method.' route: '.($route && $route->uri() === 'webhooks/messenger' && in_array($method, $route->methods(), true) ? 'YES' : 'NO'));
        }
        $this->line('APP_URL uses HTTPS: '.(str_starts_with((string) config('app.url'), 'https://') ? 'YES' : 'NO'));
        $this->line('APP_KEY configured: '.(filled(config('app.key')) ? 'YES' : 'NO'));
        $this->line('Debug disabled: '.(! config('app.debug') ? 'YES' : 'NO'));
        $this->line('Messenger outbound enabled: '.(config('messaging.messenger.outbound_enabled') ? 'YES' : 'NO'));
        $this->line('Outbound transport configured: '.(\App\Services\Messaging\MessengerOutbound::configured() ? 'YES' : 'NO'));
        $this->line('Outbound transport enabled: '.(\App\Services\Messaging\MessengerOutbound::enabled() ? 'YES' : 'NO'));
        $this->line('Outbound queue: '.(config('messaging.messenger.outbound_queue') === 'messenger-outbound' ? 'messenger-outbound' : 'CUSTOM/MISSING'));
        $this->line('Worker liveness: NOT PROBED; verify with your process manager');
        if ($this->option('entry') !== null && (! ctype_digit((string) $this->option('entry')) || (int) $this->option('entry') < 1)) {
            $this->error('Entry must be a positive internal numeric ID.');
            return self::INVALID;
        }
        if ($this->option('diagnostics')) {
            $this->diagnostics($connection, $driver);
        }
        $this->line('Read-only report; YES flags do not prove credential validity or live Meta connectivity.');

        return self::SUCCESS;
    }

    private function diagnostics(?string $connection, ?string $driver): void
    {
        try {
            foreach (['message_conversations', 'message_entries', 'activity_attachments', 'message_entry_attachments',
                'messaging_webhook_events', 'messaging_provider_peers', 'messaging_provider_receipts'] as $table) {
                if (! Schema::hasTable($table)) {
                    $this->line('Messenger schema: MISSING TABLES; review migrate:status');
                    return;
                }
            }
            $this->line('Messenger tables: PRESENT (check migrate:status for column/index migrations)');
            $owner = config('messaging.messenger.inbox_user_id');
            $this->line('Inbox user exists: '.(filled($owner) && DB::table('users')->where('id', $owner)->exists() ? 'YES' : 'NO'));
            $conversation = DB::table('message_conversations')->where('channel', 'Messenger')->orderByDesc('id')->first(['id']);
            $this->line('Latest Messenger conversation ID: '.($conversation ? (int) $conversation->id : 'NONE'));
            if ($this->option('entry') !== null) {
                $this->entryDiagnostics((int) $this->option('entry'));
            } else {
            $query = DB::table('message_entries')->where('channel', 'Messenger')->where('direction', 'Incoming');
            $entry = $query->orderByDesc('id')->first(['id', 'message_conversation_id', 'received_at', 'external_message_id', 'delivery_status', 'technical_metadata']);
            if ($entry) {
                $this->line('Incoming entry ID: '.(int) $entry->id);
                $this->line('Incoming entry conversation ID: '.(int) $entry->message_conversation_id);
                $this->line('Incoming received_at populated: '.($entry->received_at ? 'YES' : 'NO'));
                $this->line('Incoming status is Received: '.($entry->delivery_status === 'Received' ? 'YES' : 'NO'));
                $hasExternalId = filled($entry->external_message_id);
                $this->line('Incoming external_message_id populated: '.($hasExternalId ? 'YES' : 'NO'));
                $metadata = json_decode($entry->technical_metadata ?? '{}', true);
                $metadata = is_array($metadata) ? $metadata : [];
                $this->line('Provider metadata identifiers present: '.(($metadata['provider'] ?? null) === 'messenger'
                    && filled($metadata['account_id'] ?? null) && filled($metadata['peer_id'] ?? null) ? 'YES' : 'NO'));
                $this->line('External delivery attempted is false: '.(($metadata['external_delivery_attempted'] ?? null) === false ? 'YES' : 'NO'));
                $copies = $hasExternalId ? DB::table('message_entries')->where('channel', 'Messenger')
                    ->where('external_message_id', $entry->external_message_id)->count() : null;
                $this->line('Rows sharing this external_message_id: '.($copies ?? 'N/A'));
                $this->line('Explicit attachment pivots: '.DB::table('message_entry_attachments')->where('message_entry_id', $entry->id)->count());
            } else {
                $this->line('Incoming entry: NONE');
            }
            }
            foreach (['pending', 'processed', 'failed'] as $status) {
                $this->line('Webhook events '.$status.': '.DB::table('messaging_webhook_events')->where('provider', 'messenger')->where('status', $status)->count());
            }
            if ($driver === 'database') {
                $queueDb = DB::connection(config('queue.connections.'.$connection.'.connection'));
                $jobs = $queueDb->table(config('queue.connections.'.$connection.'.table', 'jobs'))
                    ->where('queue', config('messaging.messenger.queue'));
                $this->line('Queue waiting jobs: '.(clone $jobs)->whereNull('reserved_at')->count());
                $this->line('Queue reserved jobs: '.(clone $jobs)->whereNotNull('reserved_at')->count());
            } else {
                $this->line('Queue depth: use provider process/queue monitoring (not queried here)');
            }
            if (in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
                $failed = DB::connection(config('queue.failed.database'))->table(config('queue.failed.table', 'failed_jobs'))
                    ->where('queue', config('messaging.messenger.queue'))
                    ->where('payload', 'like', '%ProcessMessagingWebhook%')->count();
                $this->line('Failed Messenger jobs: '.$failed);
            } else {
                $this->line('Failed job storage: not a database backend');
            }
        } catch (\Throwable) {
            // Never print SQL bindings, connection exceptions, payloads or configuration values.
            $this->line('Database/queue diagnostics: UNAVAILABLE; check connectivity and migrations privately');
        }
    }

    private function entryDiagnostics(int $id): void
    {
        $baseColumns = [
            'id',
            'message_conversation_id',
            'direction',
            'delivery_status',
            'external_message_id',
        ];

        foreach (['sent_at', 'received_at', 'delivered_at', 'read_at'] as $column) {
            if (Schema::hasColumn('message_entries', $column)) {
                $baseColumns[] = $column;
            }
        }

        $entry = DB::table('message_entries')
            ->where('channel', 'Messenger')
            ->where('id', $id)
            ->first($baseColumns);

        if (! $entry) {
            $this->line('Messenger entry: NONE');

            return;
        }

        $this->line('Entry ID: '.(int) $entry->id);
        $this->line('Conversation ID: '.(int) $entry->message_conversation_id);

        $this->line(
            'Direction: '.(
                in_array($entry->direction, ['Incoming', 'Outgoing'], true)
                    ? $entry->direction
                    : 'OTHER'
            )
        );

        $this->line(
            'Delivery status: '.(
                in_array(
                    $entry->delivery_status,
                    ['Pending', 'Sent', 'Delivered', 'Read', 'Failed', 'Received'],
                    true
                )
                    ? $entry->delivery_status
                    : 'OTHER'
            )
        );

        foreach (['sent_at', 'received_at', 'delivered_at', 'read_at'] as $field) {
            if (! Schema::hasColumn('message_entries', $field)) {
                continue;
            }

            $value = $entry->{$field};

            $this->line(
                $field.': '.(
                    $value === null
                        ? 'NULL'
                        : (
                            preg_match(
                                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?$/D',
                                $value
                            )
                                ? $value
                                : 'INVALID'
                        )
                )
            );
        }

        $this->line(
            'Timestamp policy: application-local storage; historical rows may predate the fix'
        );

        $hasExternalId = filled($entry->external_message_id);

        $this->line(
            'External message ID present: '.($hasExternalId ? 'YES' : 'NO')
        );

        $copies = $hasExternalId
            ? DB::table('message_entries')
                ->where('channel', 'Messenger')
                ->where('external_message_id', $entry->external_message_id)
                ->count()
            : 0;

        $this->line('Rows sharing this external_message_id: '.$copies);

        try {
            $requiredPeerColumns = [
                'id',
                'provider',
                'message_conversation_id',
                'account_id',
                'peer_id',
                'delivery_watermark',
                'read_watermark',
            ];

            $peerReady = Schema::hasTable('messaging_provider_peers');

            foreach ($requiredPeerColumns as $column) {
                $peerReady =
                    $peerReady &&
                    Schema::hasColumn('messaging_provider_peers', $column);
            }

            if ($peerReady) {
                $peers = DB::table('messaging_provider_peers')
                    ->where('provider', 'messenger')
                    ->where(
                        'message_conversation_id',
                        $entry->message_conversation_id
                    )
                    ->limit(2)
                    ->get();

                if ($peers->count() === 1) {
                    $peer = $peers->first();

                    foreach (
                        ['account_id', 'peer_id', 'delivery_watermark', 'read_watermark']
                        as $field
                    ) {
                        $value = (string) $peer->{$field};

                        $this->line(
                            $field.': '.(
                                preg_match('/^[0-9]{1,80}$/D', $value)
                                    ? $value
                                    : 'MISSING/INVALID'
                            )
                        );
                    }

                    $receiptReady =
                        Schema::hasTable('messaging_provider_receipts')
                        && Schema::hasColumn(
                            'messaging_provider_receipts',
                            'messaging_provider_peer_id'
                        )
                        && Schema::hasColumn(
                            'messaging_provider_receipts',
                            'external_message_id'
                        );

                    if ($receiptReady && $hasExternalId) {
                        $this->line(
                            'Exact MID delivery receipts: '.
                            DB::table('messaging_provider_receipts')
                                ->where('messaging_provider_peer_id', $peer->id)
                                ->where(
                                    'external_message_id',
                                    $entry->external_message_id
                                )
                                ->count()
                        );
                    } else {
                        $this->line('Exact MID delivery receipts: UNAVAILABLE');
                    }

                    $webhookReady =
                        Schema::hasTable('messaging_webhook_events')
                        && Schema::hasColumn('messaging_webhook_events', 'provider')
                        && Schema::hasColumn('messaging_webhook_events', 'status')
                        && Schema::hasColumn('messaging_webhook_events', 'payload');

                    if ($webhookReady) {
                        $counts = [
                            'message' => 0,
                            'echo' => 0,
                            'delivery' => 0,
                            'read' => 0,
                        ];

                        $correlated = 0;
                        $unavailable = 0;

                        foreach (
                            \App\Models\MessagingWebhookEvent::where(
                                'provider',
                                'messenger'
                            )
                                ->where('status', 'processed')
                                ->lazyById(100) as $record
                        ) {
                            $summary = $record->payload;

                            if (($summary['diagnostic_version'] ?? null) !== 1) {
                                $unavailable++;

                                continue;
                            }

                            $matched = false;

                            foreach ($summary['events'] ?? [] as $event) {
                                if (
                                    ($event['account_id'] ?? null) !== $peer->account_id
                                    || ($event['peer_id'] ?? null) !== $peer->peer_id
                                ) {
                                    continue;
                                }

                                $kind = $event['kind'] ?? '';

                                if (array_key_exists($kind, $counts)) {
                                    $counts[$kind]++;
                                    $matched = true;
                                }
                            }

                            if ($matched) {
                                $correlated++;
                            }
                        }

                        $this->line(
                            'Correlated processed webhook records: '.$correlated
                        );

                        foreach ($counts as $kind => $count) {
                            $this->line(
                                'Correlated '.$kind.' events: '.$count
                            );
                        }

                        $this->line(
                            'Legacy processed records without summaries (all peers): '.
                            $unavailable
                        );

                        $this->line(
                            'Correlation is Page/peer/conversation scoped, not proof that a read watermark covers this specific entry. Zero retained reads does not prove none ever arrived.'
                        );
                    } else {
                        $this->line('Webhook receipt correlation: UNAVAILABLE');
                    }
                } else {
                    $this->line(
                        'Provider peer correlation: '.
                        ($peers->isEmpty() ? 'NONE' : 'AMBIGUOUS')
                    );
                }
            } else {
                $this->line('Provider peer diagnostics: UNAVAILABLE');
            }
        } catch (\Throwable) {
            $this->line('Provider receipt diagnostics: UNAVAILABLE');
        }
    }
}

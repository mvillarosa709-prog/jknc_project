<?php

namespace App\Services\Messaging;

use App\Jobs\SendMessengerMessage;
use App\Models\MessageConversation;
use App\Models\MessageEntry;
use App\Models\MessengerOutboundAttempt;
use App\Models\MessagingProviderPeer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessengerOutbound
{
    public static function configured(): bool
    {
        $config = config('messaging.messenger');
        return filled($config['page_access_token'] ?? null)
            && filled($config['app_secret'] ?? null)
            && preg_match('/^[0-9]{1,80}$/D', (string) ($config['page_id'] ?? ''))
            && preg_match('/^v[0-9]+\.[0-9]+$/D', (string) ($config['graph_version'] ?? ''))
            && in_array(config('queue.connections.'.($config['queue_connection'] ?? '').'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true);
    }

    public static function enabled(): bool
    {
        return config('messaging.messenger.enabled') && config('messaging.messenger.outbound_enabled') && self::configured();
    }

    /** Explicit send intent only. Never scan or promote existing local Pending messages. */
    public function store(MessageConversation $conversation, int $userId, string $body, string $requestId, array $attachmentIds = []): MessageEntry
    {
        abort_unless(self::enabled() && $conversation->channel === 'Messenger', 409, 'Messenger sending is unavailable; refresh the conversation.');
        $entry = DB::transaction(function () use ($conversation, $userId, $body, $requestId, $attachmentIds) {
            $conversation = MessageConversation::lockForUpdate()->findOrFail($conversation->id);
            $existing = MessengerOutboundAttempt::where('request_id', $requestId)->first();
            if ($existing) {
                $entry = MessageEntry::findOrFail($existing->message_entry_id);
                $existingAttachmentIds = $entry->activityAttachments()
                    ->pluck('activity_attachments.id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                $requestedAttachmentIds = collect($attachmentIds)
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                abort_unless(
                    $entry->message_conversation_id === $conversation->id
                    && $entry->user_id === $userId
                    && $entry->body === $body
                    && $existingAttachmentIds === $requestedAttachmentIds,
                    409,
                    'This send request was already used.'
                );

                return $entry;
            }
            $peers = MessagingProviderPeer::where('provider', 'messenger')
                ->where('message_conversation_id', $conversation->id)->limit(2)->get();
            $peer = $peers->count() === 1 ? $peers->first() : null;
            $entry = $conversation->entries()->create([
                'channel' => 'Messenger', 'user_id' => $userId, 'direction' => 'Outgoing',
                'body' => $body, 'message_type' => 'text', 'delivery_status' => 'Pending',
                'sent_at' => null, 'external_message_id' => null,
                'technical_metadata' => ['external_delivery_attempted' => false, 'provider' => 'messenger', 'outbound_requested' => true],
            ]);
            if ($attachmentIds !== []) {
                $entry->activityAttachments()->sync($attachmentIds);
            }

            MessengerOutboundAttempt::create(['message_entry_id' => $entry->id,
                'request_id' => $requestId, 'correlation_id' => (string) Str::uuid(),
                'account_id' => $peer?->account_id, 'peer_id' => $peer?->peer_id]);
            $conversation->update(['last_message_at' => now()]);
            return $entry;
        });
        try {
            SendMessengerMessage::dispatch($entry->id)->afterCommit();
            Log::info('messenger.outbound_queued', ['message_entry_id' => $entry->id, 'conversation_id' => $conversation->id]);
        } catch (\Throwable) {
            // Durable entry/request survives queue failure; replaying the same request safely requeues it.
            Log::warning('messenger.outbound_queue_unavailable', ['message_entry_id' => $entry->id]);
            abort(503, 'Reply stored, but queue unavailable. Retry the same submission.');
        }
        return $entry;
    }

    public function cancel(int $entryId): void
    {
        DB::transaction(function () use ($entryId) {
            $attempt = MessengerOutboundAttempt::where('message_entry_id', $entryId)->lockForUpdate()->first();
            if ($attempt && in_array($attempt->state, ['queued', 'retry'], true)) {
                $attempt->update(['state' => 'cancelled', 'error_code' => 'outbound_disabled']);
            }
        });
    }

    public function abandon(int $entryId): void
    {
        $attempt = MessengerOutboundAttempt::where('message_entry_id', $entryId)->first();
        if ($attempt && in_array($attempt->state, ['attempting', 'queued', 'retry'], true)) {
            $uncertain = $attempt->state === 'attempting';
            $this->failure($attempt, $uncertain ? 'acceptance_unknown' : 'queue_exhausted', $uncertain ? 'unknown' : 'failed');
        }
    }

    public function send(MessageEntry $entry): void
    {
        if (! self::enabled()) {
            throw new \LogicException('Messenger outbound delivery is not enabled.');
        }
        // A durable claim is committed BEFORE HTTP. No transaction retry encloses a remote side effect.
        $attempt = DB::transaction(function () use ($entry) {
            $attempt = MessengerOutboundAttempt::where('message_entry_id', $entry->id)->lockForUpdate()->first();
            $entry = MessageEntry::lockForUpdate()->findOrFail($entry->id);
            if ($attempt && $attempt->state === 'attempting' && $attempt->attempted_at?->lt(now()->subSeconds(30)) && ! $entry->external_message_id) {
                $attempt->update(['state' => 'unknown', 'error_code' => 'worker_interrupted']);
                $entry->update(['delivery_status' => 'Failed', 'technical_metadata' => array_merge($entry->technical_metadata ?? [], ['failure_code' => 'acceptance_unknown'])]);
            }
            if (! $attempt || $entry->external_message_id || ! in_array($attempt->state, ['queued', 'retry'], true)) {
                Log::info('messenger.duplicate_send_suppressed', ['message_entry_id' => $entry->id]);
                return null;
            }
            if ($attempt->retry_at?->isFuture()) {
                throw new \RuntimeException('Messenger retry backoff is active.');
            }
            $peer = MessagingProviderPeer::where('provider', 'messenger')->where('account_id', $attempt->account_id)
                ->where('peer_id', $attempt->peer_id)->where('message_conversation_id', $entry->message_conversation_id)->first();
            $conversation = $entry->conversation;
            $error = null;
            if (! $peer || ! preg_match('/^[0-9]{1,80}$/D', (string) $attempt->peer_id)
                || $attempt->account_id !== (string) config('messaging.messenger.page_id')
                || $attempt->peer_id === $attempt->account_id || $conversation->channel !== 'Messenger'
                || $conversation->line_account !== $attempt->account_id
                || $conversation->external_conversation_id !== $attempt->account_id.':'.$attempt->peer_id
                || $entry->channel !== 'Messenger' || $entry->direction !== 'Outgoing') {
                $error = 'recipient_identity_missing_or_changed';
            } else {
                $attachments = $entry->activityAttachments()->get();

                if ($attachments->count() > 1) {
                    $error = 'multiple_attachments_not_supported';
                } elseif ($attachments->count() === 1) {
                    $attachment = $attachments->first();

                    if (
                        ! is_string($attachment->mime_type)
                        || ! str_starts_with($attachment->mime_type, 'image/')
                        || $attachment->storage_disk !== 'public'
                        || blank($attachment->storage_path)
                    ) {
                        $error = 'unsupported_attachment';
                    } elseif (blank(config('messaging.messenger.public_media_url'))) {
                        $error = 'public_media_url_missing';
                    }
                } elseif ($entry->message_type !== 'text') {
                    $error = 'unsupported_message_type';
                }
            }

            if (! $error && trim($entry->body) === '') {
                $error = 'invalid_text_length';
            } elseif (! $error && mb_strlen($entry->body) > 2000) {
                $error = 'invalid_text_length';
            } elseif (! $conversation->entries()->where('direction', 'Incoming')->where('channel', 'Messenger')
                ->whereNotNull('external_message_id')->where('received_at', '>', now()->subHours(24))
                ->where('technical_metadata->provider', 'messenger')
                ->where('technical_metadata->account_id', $attempt->account_id)
                ->where('technical_metadata->peer_id', $attempt->peer_id)->exists()) {
                $error = 'response_window_closed';
            }
            if ($error) {
                $attempt->update(['state' => 'failed', 'error_code' => $error]);
                $entry->update(['delivery_status' => 'Failed', 'technical_metadata' => array_merge($entry->technical_metadata ?? [], ['failure_code' => $error])]);
                Log::warning('messenger.outbound_rejected', ['message_entry_id' => $entry->id, 'reason' => $error]);
                return null;
            }
            $attempt->update(['state' => 'attempting', 'attempts' => $attempt->attempts + 1, 'attempted_at' => now(), 'retry_at' => null]);
            $entry->update(['technical_metadata' => array_merge($entry->technical_metadata ?? [], [
                'external_delivery_attempted' => true, 'account_id' => $attempt->account_id, 'peer_id' => $attempt->peer_id,
                'failure_code' => null,
            ])]);
            return $attempt;
        });
        if (! $attempt) {
            return;
        }
        Log::info('messenger.outbound_attempt', ['message_entry_id' => $entry->id,
            'conversation_id' => $entry->message_conversation_id, 'attempt' => $attempt->attempts]);
        try {
            $attachment = $entry->activityAttachments()->first();

            $message = $attachment
                ? [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => [
                            'url' => rtrim((string) config('messaging.messenger.public_media_url'), '/')
                                .'/storage/'.ltrim($attachment->storage_path, '/'),
                            'is_reusable' => false,
                        ],
                    ],
                    'metadata' => 'jknc:'.$attempt->correlation_id,
                ]
                : [
                    'text' => $entry->body,
                    'metadata' => 'jknc:'.$attempt->correlation_id,
                ];

            $response = Http::withToken((string) config('messaging.messenger.page_access_token'))
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->post('https://graph.facebook.com/'.config('messaging.messenger.graph_version').'/'.$attempt->account_id.'/messages', [
                    'recipient' => ['id' => $attempt->peer_id],
                    'messaging_type' => 'RESPONSE',
                    'message' => $message,
                ]);
        } catch (\Throwable) {
            $this->failure($attempt, 'acceptance_unknown', 'unknown');
            return;
        }
        $mid = $response->json('message_id');
        if ($response->successful() && is_string($mid) && $mid !== '' && strlen($mid) <= 255
            && (string) $response->json('recipient_id') === $attempt->peer_id) {
            $this->accepted($attempt->id, $mid);
            return;
        }
        // Retain only Meta's numeric diagnostic identifiers. Never persist raw provider
        // messages, response bodies, credentials, trace IDs or request payloads.
        $providerErrorCode = $response->json('error.code');
        $providerErrorSubcode = $response->json('error.error_subcode');

        $providerErrorCode = is_numeric($providerErrorCode)
            ? (int) $providerErrorCode
            : null;

        $providerErrorSubcode = is_numeric($providerErrorSubcode)
            ? (int) $providerErrorSubcode
            : null;

        // Only an explicit rate-limit rejection is retried. 5xx/malformed/timeouts can mean acceptance.
        $retry = $response->status() === 429 && $providerErrorCode !== null && $attempt->attempts < 5;
        $state = $retry ? 'retry' : (($response->serverError() || $response->successful()) ? 'unknown' : 'failed');

        $this->failure(
            $attempt,
            $state === 'unknown' ? 'acceptance_unknown' : 'graph_http_'.$response->status(),
            $state,
            $providerErrorCode,
            $providerErrorSubcode
        );
        if ($retry) {
            throw new \RuntimeException('Messenger rate limited; safe retry scheduled.');
        }
    }

    private function failure(
        MessengerOutboundAttempt $attempt,
        string $code,
        string $state,
        ?int $providerErrorCode = null,
        ?int $providerErrorSubcode = null
    ): void
    {
        DB::transaction(function () use ($attempt, $code, $state, $providerErrorCode, $providerErrorSubcode) {
            $attempt = MessengerOutboundAttempt::lockForUpdate()->findOrFail($attempt->id);
            $entry = MessageEntry::lockForUpdate()->findOrFail($attempt->message_entry_id);
            if ($entry->external_message_id || $attempt->state === 'accepted') {
                return; // An echo may have confirmed acceptance while HTTP timed out.
            }
            $delay = [30, 120, 600, 1800][max(0, min($attempt->attempts - 1, 3))];
            $attempt->update(['state' => $state, 'error_code' => $code, 'retry_at' => $state === 'retry' ? now()->addSeconds($delay) : null]);
            $metadata = array_merge($entry->technical_metadata ?? [], [
                'failure_code' => $code,
            ]);

            if ($providerErrorCode !== null) {
                $metadata['provider_error_code'] = $providerErrorCode;
            }

            if ($providerErrorSubcode !== null) {
                $metadata['provider_error_subcode'] = $providerErrorSubcode;
            }

            $entry->update([
                'delivery_status' => $state === 'retry' ? 'Pending' : 'Failed',
                'technical_metadata' => $metadata,
            ]);
            Log::warning('messenger.outbound_failure', ['message_entry_id' => $entry->id, 'reason' => $code, 'state' => $state]);
        });
    }

    /** Called by HTTP acknowledgement AND authenticated echoes. Lock peer first, like inbound ingestion. */
    public function accepted(int $attemptId, string $mid, ?int $timestamp = null): void
    {
        $snapshot = MessengerOutboundAttempt::findOrFail($attemptId);
        DB::transaction(function () use ($snapshot, $mid, $timestamp) {
            $peer = MessagingProviderPeer::where('provider', 'messenger')->where('account_id', $snapshot->account_id)
                ->where('peer_id', $snapshot->peer_id)->lockForUpdate()->firstOrFail();
            $attempt = MessengerOutboundAttempt::lockForUpdate()->findOrFail($snapshot->id);
            $entry = MessageEntry::lockForUpdate()->findOrFail($attempt->message_entry_id);
            if ($entry->external_message_id && $entry->external_message_id !== $mid) {
                throw new \LogicException('Messenger acknowledgement identity conflict.');
            }
            if (! in_array($attempt->state, ['attempting', 'unknown', 'accepted', 'retry', 'failed'], true)) {
                return;
            }
            // Fallback if Meta omits correlation metadata and its echo wins the HTTP race.
            // The Send API's exact MID is authoritative; never merge on text/name/time similarity.
            $echo = MessageEntry::where('channel', 'Messenger')->where('external_message_id', $mid)
                ->whereKeyNot($entry->id)->lockForUpdate()->first();
            if ($echo) {
                if ($echo->external_message_id !== $mid || $echo->direction !== 'Outgoing'
                    || $echo->message_conversation_id !== $entry->message_conversation_id
                    || ($echo->technical_metadata['account_id'] ?? null) !== $attempt->account_id
                    || ($echo->technical_metadata['peer_id'] ?? null) !== $attempt->peer_id
                    || ($echo->technical_metadata['provider_observed'] ?? false) !== true
                    || ($echo->technical_metadata['external_delivery_attempted'] ?? true) !== false
                    || MessengerOutboundAttempt::where('message_entry_id', $echo->id)->exists()) {
                    throw new \LogicException('Messenger echo identity conflict.');
                }
                $entry->activityAttachments()->syncWithoutDetaching($echo->activityAttachments()->allRelatedIds()->all());
                $entry->sent_at ??= $echo->sent_at;
                $entry->delivered_at ??= $echo->delivered_at;
                $entry->read_at ??= $echo->read_at;
                if ($entry->read_at) $entry->delivery_status = 'Read';
                elseif ($entry->delivered_at) $entry->delivery_status = 'Delivered';
                $entry->technical_metadata = array_merge($echo->technical_metadata ?? [], $entry->technical_metadata ?? [], ['merged_echo_entry_id' => $echo->id]);
                // Preserve attachments and provenance above, then remove only the redundant provider echo.
                $echo->delete();
                Log::info('messenger.echo_reconciled', ['message_entry_id' => $entry->id, 'merged_echo_entry_id' => $echo->id]);
            }
            $entry->external_message_id = $mid;
            $entry->sent_at ??= $timestamp ? \Carbon\Carbon::createFromTimestampMs($timestamp)->setTimezone(config('app.timezone')) : now();
            if (! in_array($entry->delivery_status, ['Delivered', 'Read'], true)) {
                $entry->delivery_status = 'Sent';
            }
            $entry->technical_metadata = array_merge($entry->technical_metadata ?? [], [
                'provider' => 'messenger', 'provider_observed' => true, 'external_delivery_attempted' => true,
                'account_id' => $attempt->account_id, 'peer_id' => $attempt->peer_id, 'failure_code' => null,
                'provider_timestamp_ms' => $timestamp ?? $entry->technical_metadata['provider_timestamp_ms'] ?? (int) $entry->sent_at->valueOf(),
            ]);
            $entry->save();
            $attempt->update(['state' => 'accepted', 'error_code' => null, 'retry_at' => null]);
            app(MessengerProvider::class)->reconcileReceipts($peer);
            Log::info('messenger.outbound_success', ['message_entry_id' => $entry->id,
                'conversation_id' => $entry->message_conversation_id, 'provider_message_id' => $mid]);
        }, 3);
    }
}

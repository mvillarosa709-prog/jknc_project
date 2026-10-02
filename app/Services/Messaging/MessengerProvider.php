<?php

namespace App\Services\Messaging;

use App\Contracts\MessagingProvider;
use App\Models\ActivityAttachment;
use App\Models\MessageConversation;
use App\Models\MessageEntry;
use App\Models\MessagingProviderPeer;
use App\Services\ActivityAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MessengerProvider implements MessagingProvider
{
    public function send(MessageEntry $entry): void
    {
        app(MessengerOutbound::class)->send($entry);
    }

    public function reconcileReceipts(MessagingProviderPeer $peer): void
    {
        $this->receipts($peer, ['message' => ['is_echo' => true]]);
    }

    public function ingest(array $payload): void
    {
        if (! config('messaging.messenger.enabled')) {
            throw new \LogicException('Messenger ingestion is disabled.');
        }
        $page = (string) config('messaging.messenger.page_id');
        $payload = app(MessengerWebhookPayload::class)->normalize($payload, $page);
        foreach ($payload['entry'] ?? [] as $batch) {
            if ((string) ($batch['id'] ?? '') !== $page) {
                throw new \InvalidArgumentException('Unexpected Messenger account.');
            }
            foreach ($batch['messaging'] ?? [] as $event) {
                if (! is_array($event)) {
                    continue;
                }
                $echo = ($event['message']['is_echo'] ?? false) === true;
                $account = (string) ($event[$echo ? 'sender' : 'recipient']['id'] ?? '');
                $psid = (string) ($event[$echo ? 'recipient' : 'sender']['id'] ?? '');
                if ($account !== $page || ! preg_match('/^[0-9]{1,80}$/D', $psid)) {
                    continue;
                }
                DB::transaction(function () use ($event, $page, $psid, $echo) {
                    $identity = ['provider' => 'messenger', 'account_id' => $page, 'peer_id' => $psid];
                    MessagingProviderPeer::firstOrCreate($identity);
                    $peer = MessagingProviderPeer::where($identity)->lockForUpdate()->firstOrFail();
                    if (isset($event['message']['mid']) && is_string($event['message']['mid'])
                        && strlen($event['message']['mid']) <= 255) {
                        $profile = $echo ? null : $this->messengerProfile($peer);
                        $this->message($peer, $event, $echo, $profile);
                    }
                    $this->receipts($peer, $event);
                }, 3);
            }
        }
    }

    private function message(MessagingProviderPeer $peer, array $event, bool $echo, ?array $profile = null): void
    {
        $mid = $event['message']['mid'];
        if ($mid === '') {
            return;
        }
        $correlation = $event['message']['metadata'] ?? null;
        if ($echo && is_string($correlation) && str_starts_with($correlation, 'jknc:')) {
            $attempt = \App\Models\MessengerOutboundAttempt::where('correlation_id', substr($correlation, 5))
                ->where('account_id', $peer->account_id)->where('peer_id', $peer->peer_id)->first();
            if ($attempt && $attempt->attempts > 0) {
                app(MessengerOutbound::class)->accepted($attempt->id, $mid, $event['timestamp'] ?? null);
                Log::info('messenger.echo_reconciled', ['message_entry_id' => $attempt->message_entry_id]);
                return;
            }
        }
        // Provider IDs, not body fingerprints: never match a local Pending reply.
        $existing = MessageEntry::where('channel', 'Messenger')->where('external_message_id', $mid)->first();
        if ($existing) {
            if ($existing->external_message_id !== $mid
                || ($existing->technical_metadata['account_id'] ?? null) !== $peer->account_id
                || ($existing->technical_metadata['peer_id'] ?? null) !== $peer->peer_id) {
                throw new \LogicException('Provider message identity collision.');
            }
            Log::info('messenger.duplicate_message_ignored', ['message_entry_id' => $existing->id]);
            return;
        }
        if ($peer->message_conversation_id) {
            $conversation = MessageConversation::findOrFail($peer->message_conversation_id);
        } else {
            $matches = MessageConversation::where('channel', 'Messenger')
                ->where('line_account', $peer->account_id)
                ->where('external_conversation_id', $peer->account_id.':'.$peer->peer_id)->limit(2)->get();
            if ($matches->count() > 1) {
                throw new \LogicException('Ambiguous provider conversation identity.');
            }
            $conversation = $matches->first();
        }
        if ($conversation && ($conversation->channel !== 'Messenger'
            || $conversation->line_account !== $peer->account_id
            || $conversation->external_conversation_id !== $peer->account_id.':'.$peer->peer_id)) {
            throw new \LogicException('Provider conversation identity mismatch.');
        }
        $created = ! $conversation;
        if (! $conversation) {
            $conversation = MessageConversation::create([
                'conversation_title' => filled($profile['name'] ?? null)
                    ? (string) $profile['name']
                    : 'Messenger conversation',
                'channel' => 'Messenger', 'direction' => $echo ? 'Outgoing' : 'Incoming',
                'line_account' => $peer->account_id,
                'external_conversation_id' => $peer->account_id.':'.$peer->peer_id,
                'assigned_user_id' => config('messaging.messenger.inbox_user_id'),
                // A PSID is not a phone/email. Only an explicit mapping can link a contact.
                'contact_id' => $peer->contact_id,
                'is_linked' => (bool) $peer->contact_id,
                'technical_metadata' => array_filter([
                    'provider' => 'messenger',
                    'peer_id' => $peer->peer_id,
                    'messenger_sender_name' => $profile['name'] ?? null,
                    'messenger_sender_picture' => data_get($profile, 'picture.data.url'),
                ], static fn ($value) => $value !== null && $value !== ''),
            ]);
        }
        $peer->update(['message_conversation_id' => $conversation->id]);

        if (! $echo && filled($profile['name'] ?? null)) {
            $metadata = is_array($conversation->technical_metadata)
                ? $conversation->technical_metadata
                : [];

            $metadata['provider'] = 'messenger';
            $metadata['peer_id'] = $peer->peer_id;
            $metadata['messenger_sender_name'] = (string) $profile['name'];

            $picture = data_get($profile, 'picture.data.url');

            if (is_string($picture) && $picture !== '') {
                $metadata['messenger_sender_picture'] = $picture;
            }

            $conversation->technical_metadata = $metadata;

            if (
                blank($conversation->conversation_title)
                || $conversation->conversation_title === 'Messenger conversation'
                || $conversation->conversation_title === 'New Messenger Contact'
            ) {
                $conversation->conversation_title = (string) $profile['name'];
            }

            $conversation->save();
        }

        $at = $this->timestamp($event['timestamp'] ?? null);
        $references = [];
        foreach ($event['message']['attachments'] ?? [] as $media) {
            $url = $media['payload']['url'] ?? null;
            $safe = $this->safeMediaUrl($url);
            $references[] = [
                'provider_type' => $media['type'],
                'provider_attachment_id' => $media['payload']['attachment_id'] ?? null,
                'reference_state' => $safe ? 'remote' : ($url ? 'blocked' : 'unavailable'),
                // Preserve evidence without exposing an unsafe URL to any frontend renderer.
                'url_sha256' => is_string($url) ? hash('sha256', $url) : null,
            ];
        }
        $entry = $conversation->entries()->create([
            'channel' => 'Messenger', 'direction' => $echo ? 'Outgoing' : 'Incoming',
            'sender' => $echo ? $peer->account_id : $peer->peer_id,
            'recipient' => $echo ? $peer->peer_id : $peer->account_id,
            'body' => is_string($event['message']['text'] ?? null) ? $event['message']['text'] : '[Messenger attachment or unsupported message]',
            'message_type' => empty($event['message']['attachments']) ? 'text' : 'attachment',
            'delivery_status' => $echo ? 'Sent' : 'Received',
            'external_message_id' => $mid,
            'sent_at' => $echo ? $at : null, 'received_at' => $echo ? null : $at,
            'technical_metadata' => [
                'provider' => 'messenger', 'account_id' => $peer->account_id, 'peer_id' => $peer->peer_id,
                'provider_observed' => true, 'external_delivery_attempted' => false,
                'provider_timestamp_ms' => $at->getTimestampMs(), 'media_references' => $references,
            ],
        ]);
        foreach ($event['message']['attachments'] ?? [] as $index => $media) {
            $url = $media['payload']['url'] ?? null;
            $type = $media['type'] ?? '';
            if (! in_array($type, ['image', 'audio', 'video', 'file'], true) || ! $this->safeMediaUrl($url)) {
                continue;
            }
            $category = $type === 'file' ? 'document' : $type;
            $filename = 'messenger-'.$entry->id.'-'.$index;
            $attachment = ActivityAttachment::create([
                'activity_type' => 'MESSAGE', 'activity_id' => $conversation->id,
                'filename' => $filename, 'original_filename' => $filename,
                'external_url' => $url, 'category' => $category,
                // The webhook type identifies the category, not the actual MIME encoding.
                'mime_type' => null,
                'uploaded_at' => $at,
                'metadata' => ['provider' => 'messenger', 'external_message_id' => $mid, 'remote_reference' => true,
                    'provider_type' => $type, 'provider_attachment_id' => $media['payload']['attachment_id'] ?? null],
            ]);
            $entry->activityAttachments()->attach($attachment->id);
        }
        if (! $conversation->last_message_at || $at->gt($conversation->last_message_at)) {
            $conversation->update(['last_message_at' => $at]);
        }
        app(ActivityAuditService::class)->record('MESSAGE', $conversation->id,
            $echo ? 'provider_echo_observed' : 'message_received', [],
            ['provider' => 'messenger', 'message_entry_id' => $entry->id]);
        DB::afterCommit(fn () => Log::info('messenger.message_processed', [
            'conversation_id' => $conversation->id, 'message_entry_id' => $entry->id,
            'conversation_result' => $created ? 'created' : 'matched',
        ]));
    }

    private function messengerProfile(MessagingProviderPeer $peer): ?array
    {
        $token = (string) config('messaging.messenger.page_access_token');
        $version = (string) config('messaging.messenger.graph_version');

        if (
            $token === ''
            || ! preg_match('/^v[0-9]+\.[0-9]+$/D', $version)
            || ! preg_match('/^[0-9]{1,80}$/D', (string) $peer->peer_id)
        ) {
            return null;
        }

        $cacheKey = 'messenger:profile:'.$peer->account_id.':'.$peer->peer_id;

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($token, $version, $peer) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->withoutRedirecting()
                    ->get(
                        'https://graph.facebook.com/'.$version.'/'.$peer->peer_id,
                        ['fields' => 'id,name,picture']
                    );

                if (! $response->successful()) {
                    Log::warning('messenger.profile_lookup_failed', [
                        'peer_id' => $peer->peer_id,
                        'status' => $response->status(),
                        'meta_error_code' => $response->json('error.code'),
                        'meta_error_subcode' => $response->json('error.error_subcode'),
                    ]);

                    return null;
                }

                $id = $response->json('id');
                $name = $response->json('name');

                if (
                    ! is_string($id)
                    || $id !== (string) $peer->peer_id
                    || ! is_string($name)
                    || trim($name) === ''
                ) {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => trim($name),
                    'picture' => $response->json('picture'),
                ];
            } catch (\Throwable $e) {
                Log::warning('messenger.profile_lookup_exception', [
                    'peer_id' => $peer->peer_id,
                    'exception' => $e::class,
                ]);

                return null;
            }
        });
    }
    private function receipts(MessagingProviderPeer $peer, array $event): void
    {
        if (! isset($event['delivery']) && ! isset($event['read']) && empty($event['message']['is_echo'])) {
            return;
        }
        foreach (['delivery' => 'delivery_watermark', 'read' => 'read_watermark'] as $key => $column) {
            $watermark = $event[$key]['watermark'] ?? 0;
            if (is_numeric($watermark) && $watermark > 0 && $watermark <= 2147483647000) {
                $peer->{$column} = max((int) $peer->{$column}, (int) $watermark);
            }
        }
        if ($peer->isDirty()) {
            $peer->save();
        }
        foreach ($event['delivery']['mids'] ?? [] as $mid) {
            if (is_string($mid) && $mid !== '' && strlen($mid) <= 255) {
                $identity = ['messaging_provider_peer_id' => $peer->id, 'external_message_id' => $mid];
                if (! DB::table('messaging_provider_receipts')->where($identity)->exists()) {
                    DB::table('messaging_provider_receipts')->insert($identity + [
                        'delivered_at' => $this->timestamp($event['timestamp'] ?? $event['delivery']['watermark'] ?? null),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
        if (! $peer->message_conversation_id) {
            return;
        }
        $entries = MessageEntry::where('message_conversation_id', $peer->message_conversation_id)
            ->where('channel', 'Messenger')->where('direction', 'Outgoing')
            ->whereNotNull('external_message_id')->whereIn('delivery_status', ['Sent', 'Delivered'])->get();
        foreach ($entries as $entry) {
            if (($entry->technical_metadata['provider'] ?? null) !== 'messenger'
                || ($entry->technical_metadata['provider_observed'] ?? false) !== true || ! $entry->sent_at) {
                continue;
            }
            if (($entry->technical_metadata['account_id'] ?? null) !== $peer->account_id
                || ($entry->technical_metadata['peer_id'] ?? null) !== $peer->peer_id) {
                continue;
            }
            $sent = $entry->technical_metadata['provider_timestamp_ms'] ?? $entry->sent_at->getTimestampMs();
            $receiptRow = DB::table('messaging_provider_receipts')->where('messaging_provider_peer_id', $peer->id)
                ->where('external_message_id', $entry->external_message_id)->first();
            $receipt = $receiptRow && $receiptRow->external_message_id === $entry->external_message_id
                ? $receiptRow->delivered_at : null;
            if ($receipt || ($peer->delivery_watermark && $sent <= $peer->delivery_watermark)) {
                $entry->delivered_at ??= $receipt ? Carbon::parse($receipt) : $this->timestamp($peer->delivery_watermark);
                if ($entry->delivery_status !== 'Read') {
                    $entry->delivery_status = 'Delivered';
                }
            }
            if ($peer->read_watermark && $sent <= $peer->read_watermark) {
                $entry->read_at ??= $this->timestamp($peer->read_watermark);
                $entry->delivered_at ??= $entry->read_at;
                $entry->delivery_status = 'Read';
            }
            if ($entry->isDirty()) {
                $entry->save();
                DB::afterCommit(fn () => Log::info('messenger.receipt_updated', [
                    'message_entry_id' => $entry->id, 'delivery_status' => $entry->delivery_status,
                ]));
            }
        }
    }

    private function timestamp(mixed $milliseconds): Carbon
    {
        return is_numeric($milliseconds) && $milliseconds > 0 && $milliseconds <= 2147483647000
            ? Carbon::createFromTimestampMs((int) $milliseconds)->setTimezone(config('app.timezone')) : now();
    }

    private function safeMediaUrl(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 8192) {
            return false;
        }
        $parts = parse_url($url);
        if (! $parts || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (in_array(strtolower($key), ['access_token', 'appsecret_proof', 'app_secret', 'verify_token'], true)) {
                return false;
            }
        }
        $host = strtolower($parts['host'] ?? '');
        return ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443)
            && (str_ends_with($host, '.fbcdn.net') || str_ends_with($host, '.fbsbx.com'));
    }
}

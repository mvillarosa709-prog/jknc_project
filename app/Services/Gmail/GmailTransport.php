<?php

namespace App\Services\Gmail;

use App\Models\EmailActivity;
use App\Models\EmailMailboxConnection;
use App\Models\ActivityAttachment;
use App\Models\Company;
use App\Models\Contact;
use App\Services\ActivityAttachmentService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class GmailTransport
{
    public static function enabled(): bool
    {
        return (bool) config('services.gmail.enabled');
    }

    public function authorizationUrl(string $state): string
    {
        $this->ensureEnabled();
        return config('services.gmail.authorization_endpoint').'?'.http_build_query([
            'client_id' => config('services.gmail.client_id'),
            'redirect_uri' => config('services.gmail.redirect_uri'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => implode(' ', config('services.gmail.scopes', [])),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $this->ensureEnabled();
        return Http::asForm()->timeout(20)->post(rtrim(config('services.gmail.oauth_base'), '/').'/token', [
            'code' => $code,
            'client_id' => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'redirect_uri' => config('services.gmail.redirect_uri'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();
    }

    public function profile(string $accessToken): array
    {
        $this->ensureEnabled();
        return Http::withToken($accessToken)->timeout(20)
            ->get($this->apiUrl('/users/me/profile'))
            ->throw()->json();
    }

    public function revoke(EmailMailboxConnection $connection): void
    {
        if (! self::enabled() || ! $connection->encrypted_refresh_token) return;

        try {
            Http::asForm()->timeout(20)->post(rtrim(config('services.gmail.oauth_base'), '/').'/revoke', [
                'token' => Crypt::decryptString($connection->encrypted_refresh_token),
            ]);
        } catch (\Throwable) {
            // Local disconnect remains authoritative even if Google revocation is unavailable.
        }
    }

    public function sync(EmailMailboxConnection $connection): int
    {
        if (! self::enabled()) {
            return 0;
        }

        // Freeze the window across capped runs. Never advance the watermark past unfinished pages.
        $state = $connection->sync_checkpoint;
        if (! $state) {
            $boundary = now()->timestamp;
            $initial = ! $connection->last_synced_at;
            $after = $initial
                ? $boundary - max(1, (int) config('services.gmail.initial_sync_days', 14)) * 86400
                : $connection->last_synced_at->copy()->subMinutes(2)->timestamp;
            $state = ['query' => "in:inbox after:{$after} before:{$boundary}", 'boundary' => $boundary,
                'initial' => $initial, 'pending' => [], 'next' => null, 'listed' => false, 'seen' => []];
            $connection->forceFill(['sync_checkpoint' => $state, 'sync_status' => 'in_progress'])->save();
        }
        $count = 0;
        $maxMessages = max(1, (int) config('services.gmail.initial_sync_max_messages', 500));
        $deadline = microtime(true) + 60; // Leave headroom within the job's 120s timeout.
        $pages = 0;
        while (true) {
            if (! $state['pending'] && $state['listed'] && ! $state['next']) {
                $connection->forceFill([
                    'sync_checkpoint' => null, 'sync_status' => 'complete',
                    'initial_sync_status' => $state['initial'] ? 'complete' : $connection->initial_sync_status,
                    'last_synced_at' => \Carbon\Carbon::createFromTimestamp($state['boundary'], config('app.timezone')),
                    'connection_status' => 'connected', 'last_error_at' => null, 'last_error_message' => null,
                ])->save();
                return $count;
            }
            if ($count >= $maxMessages || microtime(true) >= $deadline) {
                $connection->forceFill([
                    'sync_checkpoint' => $state, 'sync_status' => 'continuing',
                    'initial_sync_status' => $state['initial']
                        ? ($count >= $maxMessages ? 'capped' : 'in_progress') : $connection->initial_sync_status,
                    'last_error_at' => null, 'last_error_message' => null,
                ])->save();
                return $count;
            }
            if (! $state['pending']) {
                $token = $state['next'];
                if (++$pages > $maxMessages + 1 || ($token && in_array($token, $state['seen'], true))) {
                    throw new \RuntimeException('Gmail pagination did not advance; checkpoint retained for review.');
                }
                $pageQuery = ['q' => $state['query'], 'maxResults' => min(500, max(1, (int) config('services.gmail.sync_limit', 50)))];
                if ($token) $pageQuery['pageToken'] = $token;
                $response = $this->request($connection)
                    ->get($this->apiUrl('/users/me/messages'), $pageQuery)
                    ->throw()->json();
                if ($token) $state['seen'][] = $token;
                $state['pending'] = array_column($response['messages'] ?? [], 'id');
                $state['next'] = $response['nextPageToken'] ?? null;
                $state['listed'] = true;
                $connection->forceFill(['sync_checkpoint' => $state])->save();
                continue;
            }
            $id = $state['pending'][0];
            $message = $this->request($connection)
                ->get($this->apiUrl('/users/me/messages/'.rawurlencode($id)), ['format' => 'full'])
                ->throw()->json();
            $this->storeIncoming($connection, $message);
            array_shift($state['pending']);
            $count++;
            $connection->forceFill([
                'sync_checkpoint' => $state,
                'initial_sync_count' => $connection->initial_sync_count + ($state['initial'] ? 1 : 0),
            ])->save();
        }
    }

    public function send(EmailActivity $activity): array
    {
        $this->ensureEnabled();
        $connection = $activity->mailboxConnection;
        abort_unless($connection && $connection->connection_status === 'connected', 422, 'The Gmail mailbox is not connected.');

        $email = (new Email())
            ->from($activity->sender_email)
            ->to(...$activity->recipients)
            ->subject($activity->subject ?: '(No subject)')
            ->text($activity->body ?: '');

        foreach ($activity->cc_recipients ?? [] as $address) $email->cc($address);
        foreach ($activity->bcc_recipients ?? [] as $address) $email->bcc($address);
        if ($activity->in_reply_to) $email->getHeaders()->addIdHeader('In-Reply-To', trim($activity->in_reply_to, '<>'));
        if ($activity->references_header) $email->getHeaders()->addTextHeader('References', $activity->references_header);
        if ($activity->rfc_message_id) $email->getHeaders()->addIdHeader('Message-ID', trim($activity->rfc_message_id, '<>'));

        $attachments = $activity->attachments ?? collect();
        foreach ($attachments as $attachment) {
            if ($attachment->storage_disk && $attachment->storage_path) {
                $email->attachFromPath(Storage::disk($attachment->storage_disk)->path($attachment->storage_path), [
                    'as' => $attachment->original_filename ?: $attachment->filename,
                    'mime' => $attachment->mime_type ?: 'application/octet-stream',
                ]);
            }
        }

        $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');
        $payload = ['raw' => $raw];
        if ($activity->thread_id) $payload['threadId'] = $activity->thread_id;

        return $this->request($connection)->post($this->apiUrl('/users/me/messages/send'), $payload)->throw()->json();
    }

    public function downloadAttachment(EmailActivity $activity, string $attachmentId): array
    {
        $messageId = $activity->provider_message_id;
        $payload = $this->request($activity->mailboxConnection)
            ->get($this->apiUrl('/users/me/messages/'.rawurlencode($messageId).'/attachments/'.rawurlencode($attachmentId)))
            ->throw()->json();

        return [
            'data' => base64_decode(strtr($payload['data'] ?? '', '-_', '+/')) ?: '',
            'size' => (int) ($payload['size'] ?? 0),
        ];
    }

    private function storeIncoming(EmailMailboxConnection $connection, array $message): EmailActivity
    {
        $headers = collect(data_get($message, 'payload.headers', []))->keyBy(fn ($header) => strtolower($header['name']));
        $parsed = $this->parsePayload($message['payload'] ?? []);
        $sender = $this->firstAddress($headers->get('from')['value'] ?? '');
        $recipients = $this->addresses($headers->get('to')['value'] ?? '');
        $cc = $this->addresses($headers->get('cc')['value'] ?? '');
        $bcc = $this->addresses($headers->get('bcc')['value'] ?? '');
        $rfcId = $headers->get('message-id')['value'] ?? null;
        $contact = Contact::query()->whereRaw('LOWER(email) = ?', [$sender])->first();
        $company = $contact ? Company::find(\App\Support\ActivityRelationships::companyId($contact)) : null;
        $company ??= Company::query()->whereRaw('LOWER(email) = ?', [$sender])->first();

        $email = EmailActivity::query()->updateOrCreate([
            'provider' => 'gmail',
            'mailbox_key' => Str::lower($connection->email_address),
            'provider_message_id' => $message['id'],
        ], [
            'mailbox_connection_id' => $connection->id,
            'mailbox_owner_user_id' => $connection->user_id,
            'company_id' => $company?->id,
            'contact_id' => $contact?->id,
            'thread_id' => $message['threadId'] ?? null,
            'rfc_message_id' => $rfcId,
            'in_reply_to' => $headers->get('in-reply-to')['value'] ?? null,
            'references_header' => $headers->get('references')['value'] ?? null,
            'sender_email' => $sender,
            'recipients' => $recipients ?: [$connection->email_address],
            'cc_recipients' => $cc,
            'bcc_recipients' => $bcc,
            'subject' => $headers->get('subject')['value'] ?? null,
            'direction' => 'Incoming',
            'occurred_at' => $this->dateHeader($headers->get('date')['value'] ?? null),
            'summary' => Str::limit(trim($parsed['text']), 240),
            'body' => trim($parsed['text']),
            'handling' => $company || $contact ? 'Linked' : 'Unlinked',
            'has_attachments' => count($parsed['attachments']) > 0,
            'attachment_metadata' => $parsed['attachments'],
            'technical_metadata' => ['gmail_label_ids' => $message['labelIds'] ?? []],
            'delivery_status' => 'Received',
        ]);

        $this->storeIncomingAttachments($connection, $email, $parsed['attachments']);

        return $email;
    }

    private function storeIncomingAttachments(EmailMailboxConnection $connection, EmailActivity $email, array $attachments): void
    {
        if (! Schema::hasTable('activity_attachments')) {
            return;
        }

        foreach ($attachments as $metadata) {
            $name = ActivityAttachmentService::safeFilename((string) ($metadata['name'] ?? 'attachment'));
            $size = (int) ($metadata['size'] ?? 0);
            $alreadyStored = ActivityAttachment::query()
                ->where('activity_type', 'EMAIL')
                ->where('activity_id', $email->id)
                ->where('metadata->gmail_attachment_id', $metadata['attachment_id'] ?? null)
                ->exists();

            if ($alreadyStored || empty($metadata['attachment_id'])) {
                continue;
            }

            $payload = $this->downloadAttachment($email->setRelation('mailboxConnection', $connection), $metadata['attachment_id']);
            if ($payload['data'] === '' || strlen($payload['data']) > (int) config('services.gmail.attachment_max_kb', 10240) * 1024) {
                continue;
            }

            $path = 'activities/email/'.$email->id.'/'.Str::random(40);
            if (! Storage::disk('local')->put($path, $payload['data'])) {
                throw new \RuntimeException('Private Gmail attachment storage failed.');
            }
            app(ActivityAttachmentService::class)->createFromStoredFile(
                'EMAIL',
                $email->id,
                $path,
                $name,
                ActivityAttachmentService::safeMime($metadata['mime_type'] ?? null),
                strlen($payload['data']),
                'email',
                $connection->user_id,
                ['gmail_attachment_id' => $metadata['attachment_id']],
            );
        }
    }

    private function request(EmailMailboxConnection $connection): PendingRequest
    {
        $this->ensureEnabled();
        if (blank($connection->encrypted_refresh_token)) {
            $connection->forceFill([
                'connection_status' => 'reconnect_required',
                'last_error_at' => now(),
                'last_error_message' => 'Gmail authorization requires reconnection.',
            ])->save();
            throw new \RuntimeException('Gmail authorization requires reconnection.');
        }

        $token = Crypt::decryptString($connection->encrypted_refresh_token);
        $response = Http::asForm()->timeout(20)->post(rtrim(config('services.gmail.oauth_base'), '/').'/token', [
            'client_id' => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'refresh_token' => $token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            $connection->forceFill(['connection_status' => 'reconnect_required', 'last_error_at' => now(), 'last_error_message' => 'Gmail authorization requires reconnection.'])->save();
            $response->throw();
        }

        return Http::withToken($response->json('access_token'))->acceptJson()->timeout(30);
    }

    private function apiUrl(string $path): string
    {
        return rtrim(config('services.gmail.api_base'), '/').$path;
    }

    private function parsePayload(array $payload): array
    {
        $text = '';
        $attachments = [];
        $walk = function (array $part) use (&$walk, &$text, &$attachments): void {
            $filename = $part['filename'] ?? '';
            $body = $part['body'] ?? [];
            if ($filename && ! empty($body['attachmentId'])) {
                $attachments[] = ['name' => $filename, 'mime_type' => $part['mimeType'] ?? null, 'size' => (int) ($body['size'] ?? 0), 'attachment_id' => $body['attachmentId']];
                return;
            }
            if (($part['mimeType'] ?? '') === 'text/plain' && ! empty($body['data'])) $text .= base64_decode(strtr($body['data'], '-_', '+/'))."\n";
            foreach ($part['parts'] ?? [] as $child) $walk($child);
        };
        $walk($payload);
        return ['text' => $text, 'attachments' => $attachments];
    }

    private function firstAddress(string $value): string
    {
        preg_match('/<([^>]+)>/', $value, $matches);
        return Str::lower(trim($matches[1] ?? preg_replace('/^.*?\s+/', '', $value)));
    }

    private function addresses(string $value): array
    {
        return collect(preg_split('/,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY))->map(fn ($item) => $this->firstAddress($item))->filter()->values()->all();
    }

    private function dateHeader(?string $value): \Carbon\Carbon
    {
        try { return $value ? \Carbon\Carbon::parse($value) : now(); } catch (\Throwable) { return now(); }
    }

    private function ensureEnabled(): void
    {
        if (! self::enabled()) {
            throw new \RuntimeException('Gmail is disabled.');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailActivity;
use App\Models\EmailMailboxConnection;
use App\Models\ActivityAttachment;
use App\Jobs\SendGmailEmail;
use App\Jobs\SyncGmailMailboxes;
use App\Services\Gmail\GmailTransport;
use App\Services\ActivityAttachmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailActivityController extends Controller
{
    public function connectGmail(Request $request, GmailTransport $gmail)
    {
        $this->authorizeMailboxAccess($request);
        abort_unless(GmailTransport::enabled() && config('services.gmail.client_id') && config('services.gmail.client_secret'), 503, 'Gmail is not configured.');
        $state = Str::random(48);
        $request->session()->put('gmail_oauth_state', ['value' => $state, 'user_id' => $request->user()->getKey()]);
        return redirect()->away($gmail->authorizationUrl($state));
    }

    public function gmailCallback(Request $request, GmailTransport $gmail)
    {
        $this->authorizeMailboxAccess($request);
        abort_unless(GmailTransport::enabled(), 503, 'Gmail is disabled.');
        $oauthState = $request->session()->pull('gmail_oauth_state');
        abort_unless(is_array($oauthState)
            && (int) ($oauthState['user_id'] ?? 0) === (int) $request->user()->getKey()
            && hash_equals((string) ($oauthState['value'] ?? ''), (string) $request->string('state')), 403, 'Invalid Gmail authorization state.');
        abort_unless($request->filled('code'), 422, 'Gmail authorization was not completed.');
        $tokens = $gmail->exchangeCode($request->string('code'));
        $profile = $gmail->profile($tokens['access_token']);
        $refreshToken = $tokens['refresh_token'] ?? null;
        $emailAddress = Str::lower(trim((string) ($profile['emailAddress'] ?? '')));
        $existing = EmailMailboxConnection::query()
            ->where('provider', 'gmail')
            ->where('user_id', $request->user()->getKey())
            ->first();
        abort_unless($emailAddress && ($refreshToken || $existing?->encrypted_refresh_token), 422, 'Gmail did not return a reusable offline connection.');

        $connection = EmailMailboxConnection::query()->updateOrCreate(
            ['provider' => 'gmail', 'user_id' => $request->user()->getKey()],
            [
                'email_address' => $emailAddress,
                'google_account_id' => $emailAddress,
                'encrypted_refresh_token' => $refreshToken
                    ? Crypt::encryptString($refreshToken)
                    : $existing->encrypted_refresh_token,
                'connection_status' => 'connected',
                'user_id' => $request->user()->getKey(),
                'connected_at' => now(),
                'disconnected_at' => null,
                'last_error_at' => null,
                'last_error_message' => null,
            ]
        );
        SyncGmailMailboxes::dispatch($connection->getKey());
        return redirect('/activities?email=connected');
    }

    public function disconnectGmail(Request $request, GmailTransport $gmail)
    {
        $this->authorizeMailboxAccess($request);
        $connection = EmailMailboxConnection::query()
            ->where('provider', 'gmail')
            ->where('user_id', $request->user()->getKey())
            ->first();
        if ($connection) {
            $gmail->revoke($connection);
            $connection->forceFill([
                'encrypted_refresh_token' => null,
                'connection_status' => 'disconnected',
                'disconnected_at' => now(),
            ])->save();
        }
        return response()->json(['status' => 'disconnected']);
    }
    public function index(Request $request)
    {
        $query = $this->authorizedQuery($request)
            ->with($this->relationships())
            ->when($request->filled('company_id'), fn (Builder $query) => $query->where('company_id', $request->integer('company_id')))
            ->when($request->filled('contact_id'), fn (Builder $query) => $query->where('contact_id', $request->integer('contact_id')))
            ->when($request->filled('direction'), fn (Builder $query) => $query->where('direction', $request->string('direction')))
            ->when($request->filled('mailbox_key'), fn (Builder $query) => $query->where('mailbox_key', Str::lower(trim($request->string('mailbox_key')))))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim($request->string('search'));
                $query->where(fn (Builder $query) => $query
                    ->where('subject', 'like', "%{$search}%")
                    ->orWhere('sender_email', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%"));
            });

        return response()->json($query->latest('occurred_at')->latest('id')->get());
    }

    public function show(Request $request, int $id)
    {
        $emailActivity = $this->authorizedQuery($request)->findOrFail($id);

        return response()->json($emailActivity->load($this->relationships()));
    }

    public function thread(Request $request, int $id)
    {
        $email = $this->authorizedQuery($request)->findOrFail($id);
        $thread = $email->thread_id
            ? $this->authorizedQuery($request)->where('thread_id', $email->thread_id)->orderBy('occurred_at')->orderBy('id')->get()
            : collect([$email]);
        return response()->json($thread->load($this->relationships()));
    }

    public function send(Request $request)
    {
        return response()->json($this->createOutgoing($request), 202);
    }

    public function reply(Request $request, int $id)
    {
        $parent = $this->authorizedQuery($request)->findOrFail($id);
        $request->merge([
            'to' => [$parent->sender_email],
            'subject' => $this->replySubject($parent->subject),
            'thread_id' => $parent->thread_id,
            'in_reply_to' => $parent->rfc_message_id,
            'references_header' => trim(collect([$parent->references_header, $parent->rfc_message_id])->filter()->implode(' ')),
        ]);
        return response()->json($this->createOutgoing($request, $parent), 202);
    }

    public function downloadAttachment(Request $request, int $id, string $attachmentId, GmailTransport $gmail)
    {
        $email = $this->authorizedQuery($request)->with('mailboxConnection')->findOrFail($id);
        $meta = collect($email->attachment_metadata ?? [])->firstWhere('attachment_id', $attachmentId);
        abort_unless($meta, 404);
        $payload = $gmail->downloadAttachment($email, $attachmentId);
        return response($payload['data'], 200, [
            'Content-Type' => ActivityAttachmentService::safeMime($meta['mime_type'] ?? null),
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', ActivityAttachmentService::safeFilename($meta['name'] ?? 'attachment'), 'attachment'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadStoredAttachment(Request $request, int $id, int $attachment)
    {
        $this->authorizedQuery($request)->findOrFail($id);
        $file = ActivityAttachment::query()->whereKey($attachment)
            ->where('activity_type', 'EMAIL')->where('activity_id', $id)
            ->where('storage_disk', 'local')->firstOrFail();
        abort_unless($file->storage_path && Storage::disk('local')->exists($file->storage_path), 404);
        $name = ActivityAttachmentService::safeFilename($file->original_filename ?: $file->filename);
        return Storage::disk('local')->download($file->storage_path, $name, [
            'Content-Type' => ActivityAttachmentService::safeMime($file->mime_type),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'mailbox_key' => ['required', 'string', 'max:191'],
            'provider_message_id' => ['required', 'string', 'max:191'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'sender_email' => ['required', 'email', 'max:255'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email', 'max:255', 'distinct:ignore_case'],
            'cc_recipients' => ['nullable', 'array'],
            'cc_recipients.*' => ['required', 'email', 'max:255', 'distinct:ignore_case'],
            'bcc_recipients' => ['nullable', 'array'],
            'bcc_recipients.*' => ['required', 'email', 'max:255', 'distinct:ignore_case'],
            'direction' => ['required', Rule::in(['Incoming', 'Outgoing'])],
            'occurred_at' => ['required', 'date'],
            'summary' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'has_attachments' => ['nullable', 'boolean'],
            'attachment_metadata' => ['nullable', 'array'],
            'technical_metadata' => ['nullable', 'array'],
            'client_report_description' => ['nullable', 'string'],
            'include_in_client_report' => ['nullable', 'boolean'],
            'include_body_in_client_report' => ['nullable', 'boolean'],
        ]);

        $validated['provider'] = Str::lower(trim($validated['provider']));
        if ($validated['provider'] === 'gmail') {
            abort_unless(GmailTransport::enabled(), 503, 'Gmail is disabled.');
        }
        $validated['mailbox_key'] = Str::lower(trim($validated['mailbox_key']));
        $validated['provider_message_id'] = trim($validated['provider_message_id']);
        $validated['sender_email'] = Str::lower(trim($validated['sender_email']));
        $validated['recipients'] = $this->normalizedEmails($validated['recipients']);
        $validated['cc_recipients'] = $this->normalizedEmails($validated['cc_recipients'] ?? []);
        $validated['bcc_recipients'] = $this->normalizedEmails($validated['bcc_recipients'] ?? []);

        [$contact, $company] = $this->resolveRelationships($validated);
        $validated['contact_id'] = $contact?->getKey();
        $validated['company_id'] = $company?->getKey();
        $validated['handling'] = $company || $contact ? 'Linked' : 'Unlinked';

        $identity = collect($validated)->only(['provider', 'mailbox_key', 'provider_message_id'])->all();
        $attributes = collect($validated)->except(['provider', 'mailbox_key', 'provider_message_id'])->all();
        try {
            $email = DB::transaction(function () use ($request, $attributes, $identity): EmailActivity {
                $email = EmailActivity::query()->firstOrCreate($identity, $attributes + [
                    'mailbox_owner_user_id' => $request->user()->getKey(),
                    'prepared_by_user_id' => $request->user()->getKey(),
                ]);

            if (! $email->wasRecentlyCreated) {
                $this->authorizeRecord($request, $email);
            }

            $email->fill($attributes);
            $email->save();

                return $email;
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            $email = EmailActivity::query()->where($identity)->first();
            if (! $email) {
                throw $exception;
            }
            $this->authorizeRecord($request, $email);
            $email->fill($attributes)->save();
        }

        return response()->json(
            $email->load($this->relationships()),
            $email->wasRecentlyCreated ? 201 : 200
        );
    }

    private function resolveRelationships(array $validated): array
    {
        $contact = filled($validated['contact_id'] ?? null)
            ? Contact::query()->find($validated['contact_id'])
            : (array_key_exists('contact_id', $validated) ? null : $this->knownContact($validated));
        $company = filled($validated['company_id'] ?? null)
            ? Company::query()->find($validated['company_id'])
            : null;

        if (! array_key_exists('company_id', $validated) && $contact) {
            $company = Company::find(\App\Support\ActivityRelationships::companyId($contact));
        }

        return [$contact, $company];
    }

    private function knownContact(array $validated): ?Contact
    {
        $candidates = $validated['direction'] === 'Incoming'
            ? [$validated['sender_email']]
            : $validated['recipients'];

        foreach ($candidates as $email) {
            $contact = Contact::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        return null;
    }

    private function normalizedEmails(array $emails): array
    {
        return collect($emails)
            ->map(fn ($email): string => Str::lower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function authorizedQuery(Request $request): Builder
    {
        return EmailActivity::query()->visibleTo($request->user());
    }

    private function authorizeRecord(Request $request, EmailActivity $email): void
    {
        abort_unless(
            EmailActivity::query()->visibleTo($request->user())->whereKey($email->getKey())->exists(),
            404
        );
    }

    private function relationships(): array
    {
        return [
            'company:id,company_name,email',
            'contactRecord:id,first_name,last_name,email',
            'mailboxOwner:id,name,email',
            'preparedBy:id,name,email',
            'mailboxConnection:id,provider,email_address,connection_status',
        ];
    }

    private function createOutgoing(Request $request, ?EmailActivity $parent = null): EmailActivity
    {
        abort_unless(GmailTransport::enabled(), 503, 'Gmail is disabled.');
        abort_unless($request->user()->hasPermission('access_activities') || $request->user()->isAdmin() || $request->user()->isSuperAdmin(), 403);
        $to = $this->normalizedEmails($this->inputAddresses($request->input('to')));
        $cc = $this->normalizedEmails($this->inputAddresses($request->input('cc')));
        $bcc = $this->normalizedEmails($this->inputAddresses($request->input('bcc')));
        $validated = Validator::make([
            'to' => $to, 'cc' => $cc, 'bcc' => $bcc,
            'subject' => trim((string) $request->input('subject')),
            'body' => trim((string) $request->input('body')),
            'attachments' => $request->file('attachments', []),
        ], [
            'to' => ['required', 'array', 'min:1'], 'to.*' => ['email', 'distinct:ignore_case'],
            'cc.*' => ['email', 'distinct:ignore_case'], 'bcc.*' => ['email', 'distinct:ignore_case'],
            'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:'.(int) config('services.gmail.attachment_max_kb', 10240),
                'mimetypes:'.implode(',', config('services.gmail.attachment_mimes', [])),
            ],
        ])->validate();
        $mailbox = EmailMailboxConnection::query()
            ->where('provider', 'gmail')
            ->where('user_id', $request->user()->getKey())
            ->where('connection_status', 'connected')
            ->whereNotNull('encrypted_refresh_token')
            ->when($request->filled('from_mailbox'), fn ($q) => $q->where('email_address', Str::lower(trim($request->string('from_mailbox')))))
            ->firstOrFail();
        $key = $request->header('Idempotency-Key') ?: $request->input('idempotency_key') ?: Str::uuid()->toString();
        Validator::make(['idempotency_key' => $key], ['idempotency_key' => ['required', 'string', 'max:100']])->validate();
        return DB::transaction(function () use ($request, $parent, $mailbox, $key, $to, $validated): EmailActivity {
            // Serialize concurrent creates within this mailbox, including files and dispatch.
            $mailbox = EmailMailboxConnection::query()->whereKey($mailbox->id)
                ->where('user_id', $request->user()->getKey())->where('connection_status', 'connected')
                ->lockForUpdate()->firstOrFail();
            $existing = EmailActivity::query()->where('mailbox_connection_id', $mailbox->id)
                ->where('mailbox_owner_user_id', $request->user()->getKey())
                ->where('idempotency_key', $key)->first();
            if ($existing) return $existing->load($this->relationships());
            [$contact, $company] = $this->resolveRelationships($request->all() + ['direction' => 'Outgoing', 'recipients' => $to]);
            $email = EmailActivity::query()->create([
                'provider' => 'gmail', 'mailbox_key' => $mailbox->email_address, 'provider_message_id' => 'pending-'.Str::uuid(),
                'mailbox_owner_user_id' => $mailbox->user_id, 'mailbox_connection_id' => $mailbox->id,
                'company_id' => $company?->id, 'contact_id' => $contact?->id, 'prepared_by_user_id' => $request->user()->id,
                'thread_id' => $request->input('thread_id') ?: $parent?->thread_id,
                'rfc_message_id' => '<'.Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST).'>',
                'in_reply_to' => $request->input('in_reply_to'), 'references_header' => $request->input('references_header'),
                'subject' => $validated['subject'], 'sender_email' => $mailbox->email_address, 'recipients' => $validated['to'],
                'cc_recipients' => $validated['cc'] ?? [], 'bcc_recipients' => $validated['bcc'] ?? [], 'direction' => 'Outgoing',
                'occurred_at' => now(), 'summary' => Str::limit($validated['body'], 240), 'body' => $validated['body'],
                'handling' => $company || $contact ? 'Linked' : 'Unlinked', 'delivery_status' => 'Pending', 'idempotency_key' => $key,
            ]);
            $this->storeUploadedAttachments($request, $email);
            SendGmailEmail::dispatch($email->id)->afterCommit();
            return $email->load($this->relationships());
        });
    }

    private function storeUploadedAttachments(Request $request, EmailActivity $email): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            $path = $file->store('activities/email/'.$email->id, 'local');
            app(ActivityAttachmentService::class)->createFromStoredFile('EMAIL', $email->id, $path, $file->getClientOriginalName(), $file->getMimeType(), $file->getSize(), 'email', $request->user()->id);
        }
    }

    private function inputAddresses(mixed $value): array
    {
        return collect(is_array($value) ? $value : preg_split('/[,;\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY))->map(fn ($item) => trim((string) $item))->filter()->values()->all();
    }

    private function replySubject(?string $subject): string
    {
        return str_starts_with(strtolower(trim((string) $subject)), 're:') ? (string) $subject : 'Re: '.(string) $subject;
    }

    private function authorizeMailboxAccess(Request $request): void
    {
        abort_unless($request->user() && ($request->user()->isAdmin() || $request->user()->isSuperAdmin() || (Schema::hasTable('user_permissions') && $request->user()->hasPermission('access_activities'))), 403);
    }
}

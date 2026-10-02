<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\MessageConversation;
use App\Models\MessageEntry;
use App\Models\User;
use App\Services\ActivityAttachmentService;
use App\Support\ActivityDuplicateGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = MessageConversation::query()
            ->with([
                'company:id,company_name,email,phone',
                'contact:id,first_name,middle_name,last_name,name_extension,company_name,email,phone',
                'assignedUser:id,name,email',
                'latestEntry',
                'preparedBy:id,name,email',
            ])
            ->withCount('entries');

        if (! $request->user()?->isAdmin() && ! $request->user()?->isSuperAdmin()) {
            $query->where(function ($scope) use ($request): void {
                $scope->where('assigned_user_id', $request->user()->id)
                    ->orWhere('prepared_by_user_id', $request->user()->id);
            });
        }

        if ($request->filled('channel') && $request->channel !== 'all') {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('handling') && $request->handling !== 'all') {
            $query->where('handling', $request->handling);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->integer('assigned_user_id'));
        }

        if ($request->boolean('unlinked')) {
            $query->where('is_linked', false);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('conversation_title', 'like', "%{$search}%")
                    ->orWhere('channel', 'like', "%{$search}%")
                    ->orWhere('line_account', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('company_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('contact', function ($contactQuery) use ($search) {
                        $contactQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('entries', function ($entryQuery) use ($search) {
                        $entryQuery->where('body', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json(
            $query
                ->orderByRaw('last_message_at IS NULL')
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function show(MessageConversation $conversation)
    {
        $this->authorizeConversation(request(), $conversation);
        $conversation->load([
                'company:id,company_name,email,phone',
                'contact:id,first_name,middle_name,last_name,name_extension,company_name,email,phone',
                'assignedUser:id,name,email',
                'preparedBy:id,name,email',
                'entries' => function ($query) {
                    $query
                        ->with([
                            'user:id,name,email',
                            'activityAttachments',
                        ])
                        ->orderBy('created_at');
                },
            ]);

        $this->serializeMessageEntryAttachments($conversation);
        $conversation->setAttribute('messenger_outbound_available', $conversation->channel === 'Messenger'
            && \App\Services\Messaging\MessengerOutbound::enabled());

        return response()->json($conversation);
    }

    public function store(Request $request, ActivityDuplicateGuard $duplicateGuard)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],

            'conversation_title' => ['nullable', 'string', 'max:255'],
            'conversation_summary' => ['nullable', 'string'],
            'channel' => ['required', 'string', 'max:40'],
            'direction' => ['nullable', 'string', 'in:Incoming,Outgoing,Conversation Thread'],
            'participant_contact_ids' => ['nullable', 'array'],
            'participant_contact_ids.*' => ['integer', 'distinct', 'exists:contacts,id'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'line_account' => ['nullable', 'string', 'max:255'],

            'handling' => ['nullable', 'string', 'max:40'],
            'is_linked' => ['nullable', 'boolean'],

            'external_conversation_id' => ['nullable', 'string', 'max:255'],
            'technical_metadata' => ['nullable', 'array'],
            'attachment_ids' => ['nullable', 'array', 'max:20'],
            'attachment_ids.*' => ['integer', 'distinct'],

            'body' => ['nullable', 'string'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'client_report_description' => ['nullable', 'string'],
            'include_in_client_report' => ['nullable', 'boolean'],
        ]);

        $company = !empty($validated['company_id']) ? Company::find($validated['company_id']) : null;
        $contact = !empty($validated['contact_id']) ? Contact::find($validated['contact_id']) : null;
        $currentUser = auth()->user();
        $contactName = $contact ? trim(collect([
            $contact->first_name,
            $contact->middle_name,
            $contact->last_name,
            $contact->name_extension,
        ])->filter()->implode(' ')) : '';
        $defaultTitle = $contactName ?: $company?->company_name ?: 'New Conversation';
        $title = $validated['conversation_title'] ?? $defaultTitle;

        $conversation = $duplicateGuard->create('message-conversation', $validated, function () use ($validated, $currentUser, $title) {
            $query = MessageConversation::query()
                ->where('prepared_by_user_id', $currentUser?->id)
                ->whereRaw('LOWER(TRIM(conversation_title)) = ?', [mb_strtolower(trim($title))])
                ->where('channel', $validated['channel'])
                ->where('company_id', $validated['company_id'] ?? null)
                ->where('contact_id', $validated['contact_id'] ?? null)
                ->where('assigned_user_id', $validated['assigned_user_id'] ?? $currentUser?->id);

            if (! empty($validated['external_conversation_id'])) {
                return $query->where('external_conversation_id', $validated['external_conversation_id'])->exists();
            }

            return $query
                ->where('created_at', '>=', now()->subSeconds(15))
                ->when(! empty($validated['body']), fn ($query) => $query->whereHas(
                    'entries',
                    fn ($entryQuery) => $entryQuery->where('body', $validated['body'])
                ))
                ->exists();
        }, function () use ($validated, $company, $contact, $currentUser, $defaultTitle) {
            return DB::transaction(function () use ($validated, $company, $contact, $currentUser, $defaultTitle) {

            $conversation = MessageConversation::create([
                'company_id' => $validated['company_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'assigned_user_id' => $validated['assigned_user_id']
                    ?? $currentUser?->id,

                'conversation_title' => $validated['conversation_title']
                    ?? $defaultTitle,

                'conversation_summary' => $validated['conversation_summary']
                    ?? $validated['client_report_description']
                    ?? null,

                'channel' => $validated['channel'],
                'direction' => $validated['direction'] ?? 'Conversation Thread',
                'participant_contact_ids' => $validated['participant_contact_ids'] ?? (
                    ! empty($validated['contact_id']) ? [(int) $validated['contact_id']] : []
                ),
                'started_at' => $validated['started_at'] ?? null,
                'ended_at' => $validated['ended_at'] ?? null,
                'duration_minutes' => $validated['duration_minutes'] ?? null,
                'line_account' => $validated['line_account'] ?? null,

                'handling' => $validated['handling'] ?? 'Open',

                'is_linked' => array_key_exists('is_linked', $validated)
                    ? (bool) $validated['is_linked']
                    : (bool) (
                        !empty($validated['company_id'])
                        || !empty($validated['contact_id'])
                    ),

                'last_message_at' => !empty($validated['body'])
                    ? now()
                    : null,

                'external_conversation_id' => $validated['external_conversation_id'] ?? null,
                'technical_metadata' => $validated['technical_metadata'] ?? null,
                'client_report_description' => $validated['client_report_description'] ?? null,
                'include_in_client_report' => (bool) ($validated['include_in_client_report'] ?? false),
            ]);
            $conversation->prepared_by_user_id = $currentUser?->id;
            $conversation->save();

            if (!empty($validated['body'])) {
                $recipient =
                    $validated['recipient']
                    ?? $contact?->phone
                    ?? $contact?->email
                    ?? $company?->phone
                    ?? $company?->email;

                MessageEntry::create([
                    'message_conversation_id' => $conversation->id,
                    'channel' => $conversation->channel,
                    'user_id' => $currentUser?->id,
                    'direction' => 'Outgoing',
                    'sender' => $currentUser?->email,
                    'recipient' => $recipient,
                    'body' => $validated['body'],
                    'message_type' => 'text',
                    'delivery_status' => 'Pending',
                    'sent_at' => now(),
                ]);
            }

            return $conversation;
            });
        });

        return response()->json(
            $conversation->load([
                'company:id,company_name,email,phone',
                'contact:id,first_name,middle_name,last_name,name_extension,company_name,email,phone',
                'assignedUser:id,name,email',
                'latestEntry',
                'preparedBy:id,name,email',
            ]),
            201
        );
    }

    public function update(Request $request, MessageConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_title' => ['nullable', 'string', 'max:255'],
            'conversation_summary' => ['nullable', 'string'],
            'channel' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'string', 'in:Incoming,Outgoing,Conversation Thread'],
            'participant_contact_ids' => ['nullable', 'array'],
            'participant_contact_ids.*' => ['integer', 'distinct', 'exists:contacts,id'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'line_account' => ['nullable', 'string', 'max:255'],
            'handling' => ['nullable', 'string', 'max:40'],
            'is_linked' => ['nullable', 'boolean'],
            'external_conversation_id' => ['nullable', 'string', 'max:255'],
            'technical_metadata' => ['nullable', 'array'],
            'attachment_ids' => ['nullable', 'array', 'max:20'],
            'attachment_ids.*' => ['integer', 'distinct'],
            'client_report_description' => ['nullable', 'string'],
            'include_in_client_report' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($conversation, $validated) {
            $conversation->update($validated);

            if ($conversation->channel !== 'Messenger') {
                return;
            }

            $peerId = data_get($conversation->technical_metadata, 'peer_id');

            if (! is_string($peerId) || $peerId === '') {
                return;
            }

            $peer = \App\Models\MessagingProviderPeer::query()
                ->where('provider', 'messenger')
                ->where('account_id', (string) $conversation->line_account)
                ->where('peer_id', $peerId)
                ->first();

            if (! $peer) {
                return;
            }

            if (array_key_exists('contact_id', $validated)) {
                $peer->contact_id = $validated['contact_id'] ?: null;
                $peer->save();

                $conversation->is_linked = ! empty($validated['contact_id'])
                    || ! empty($conversation->company_id);

                if (! empty($validated['contact_id'])) {
                    $contact = \App\Models\Contact::find($validated['contact_id']);

                    if ($contact) {
                        $contactName = trim(collect([
                            $contact->first_name,
                            $contact->middle_name,
                            $contact->last_name,
                            $contact->name_extension,
                        ])->filter()->implode(' '));

                        if ($contactName !== '') {
                            $conversation->conversation_title = $contactName;
                        }
                    }
                } elseif (
                    $conversation->conversation_title === 'Messenger conversation'
                    || blank($conversation->conversation_title)
                ) {
                    $conversation->conversation_title = 'New Messenger Contact';
                }

                $conversation->save();
            }
        });

        return response()->json(
            $conversation->fresh()->load([
                'company:id,company_name,email,phone',
                'contact:id,first_name,middle_name,last_name,name_extension,company_name,email,phone',
                'assignedUser:id,name,email',
                'latestEntry',
                'preparedBy:id,name,email',
            ])
        );
    }

    public function storeMessage(Request $request, MessageConversation $conversation, ActivityDuplicateGuard $duplicateGuard)
    {
        $this->authorizeConversation($request, $conversation);
        $validated = $request->validate([
            'send_external' => ['sometimes', 'boolean'],
            'client_request_id' => ['required_if:send_external,true', 'nullable', 'uuid'],
            'direction' => ['nullable', 'string', 'in:Incoming,Outgoing'],
            'sender' => ['nullable', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'message_type' => ['nullable', 'string', 'in:text,image,document,audio,video'],
            'delivery_status' => ['nullable', 'string', 'max:40'],
            'external_message_id' => ['nullable', 'string', 'max:255'],
            'technical_metadata' => ['nullable', 'array'],
            'attachment_ids' => ['nullable', 'array', 'max:20'],
            'attachment_ids.*' => ['integer', 'distinct'],
        ]);

        $attachmentIds = collect($validated['attachment_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($attachmentIds->isNotEmpty()) {
            $ownedAttachmentCount = \App\Models\ActivityAttachment::query()
                ->whereIn('id', $attachmentIds->all())
                ->where('activity_type', 'MESSAGE')
                ->where('activity_id', $conversation->id)
                ->count();

            if ($ownedAttachmentCount !== $attachmentIds->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attachment_ids' => [
                        'One or more attachments do not belong to this message conversation.',
                    ],
                ]);
            }
        }

        if ($validated['send_external'] ?? false) {
            abort_if($attachmentIds->count() > 1, 422, 'Messenger currently supports one outbound image per reply.');

            if ($attachmentIds->count() === 1) {
                $attachment = \App\Models\ActivityAttachment::query()
                    ->whereKey($attachmentIds->first())
                    ->where('activity_type', 'MESSAGE')
                    ->where('activity_id', $conversation->id)
                    ->firstOrFail();

                abort_unless(
                    is_string($attachment->mime_type)
                    && str_starts_with($attachment->mime_type, 'image/')
                    && $attachment->storage_disk === 'public'
                    && filled($attachment->storage_path),
                    422,
                    'Only locally stored public image attachments can currently be sent through Messenger.'
                );
            }

            abort_if(mb_strlen($validated['body']) > 2000, 422, 'Messenger replies must be at most 2000 characters.');

            $entry = app(\App\Services\Messaging\MessengerOutbound::class)->store(
                $conversation,
                (int) $request->user()->id,
                $validated['body'],
                $validated['client_request_id'],
                $attachmentIds->all()
            );
        } else {
        // Client metadata must never pretend that a locally stored Messenger reply was delivered.
        if ($conversation->channel === 'Messenger' && ($validated['direction'] ?? 'Outgoing') === 'Outgoing') {
            $validated['delivery_status'] = 'Pending';
            $validated['external_message_id'] = null;
            $validated['technical_metadata'] = ['external_delivery_attempted' => false];
        }
        $direction = $validated['direction'] ?? 'Outgoing';
        $currentUser = auth()->user();
        $sender = $validated['sender'] ?? ($direction === 'Outgoing' ? $currentUser?->email : null);
        $entry = $duplicateGuard->create('message-entry', ['conversation_id' => $conversation->id] + $validated, function () use ($validated, $conversation, $direction, $sender) {
            $query = MessageEntry::query()
                ->where('message_conversation_id', $conversation->id)
                ->where('direction', $direction)
                ->where('sender', $sender)
                ->where('recipient', $validated['recipient'] ?? null)
                ->where('body', $validated['body']);

            if (! empty($validated['external_message_id'])) {
                return MessageEntry::query()
                    ->where('channel', $conversation->channel)
                    ->where('external_message_id', $validated['external_message_id'])
                    ->exists();
            }

            return $query->where('created_at', '>=', now()->subSeconds(15))->exists();
        }, function () use ($validated, $conversation, $attachmentIds) {
            return DB::transaction(function () use ($validated, $conversation, $attachmentIds) {
            $direction = $validated['direction'] ?? 'Outgoing';
            $currentUser = auth()->user();

            $entry = MessageEntry::create([
                'message_conversation_id' => $conversation->id,
                'channel' => $conversation->channel,

                'user_id' => $direction === 'Outgoing'
                    ? $currentUser?->id
                    : null,

                'direction' => $direction,

                'sender' => $validated['sender']
                    ?? (
                        $direction === 'Outgoing'
                            ? $currentUser?->email
                            : null
                    ),

                'recipient' => $validated['recipient'] ?? null,

                'body' => $validated['body'],

                'message_type' => $validated['message_type'] ?? 'text',

                'delivery_status' => $validated['delivery_status']
                    ?? (
                        $direction === 'Outgoing'
                            ? 'Pending'
                            : 'Received'
                    ),

                'external_message_id' => $validated['external_message_id'] ?? null,

                'sent_at' => $direction === 'Outgoing'
                    ? now()
                    : null,

                'received_at' => $direction === 'Incoming'
                    ? now()
                    : null,

                'technical_metadata' => $validated['technical_metadata'] ?? null,
            ]);

            if ($attachmentIds->isNotEmpty()) {
                $entry->activityAttachments()->syncWithoutDetaching(
                    $attachmentIds->all()
                );
            }

            $conversation->update([
                'last_message_at' => now(),
            ]);

            return $entry;
            });
        });

        }
        $entry->load([
            'user:id,name,email',
            'activityAttachments.uploadedBy:id,name,email',
        ]);

        $entry->setRelation(
            'activityAttachments',
            $entry->activityAttachments
                ->map(
                    fn ($attachment) =>
                        app(ActivityAttachmentService::class)
                            ->serialize($attachment)
                )
                ->values()
        );

        return response()->json($entry, 201);
    }

    private function serializeMessageEntryAttachments(
        MessageConversation $conversation
    ): void {
        $serializer = app(ActivityAttachmentService::class);

        $conversation->entries->each(
            function ($entry) use ($serializer): void {
                $entry->setRelation(
                    'activityAttachments',
                    $entry->activityAttachments
                        ->map(
                            fn ($attachment) =>
                                $serializer->serialize($attachment)
                        )
                        ->values()
                );
            }
        );
    }
    public function destroy(MessageConversation $conversation)
    {
        $this->authorizeConversation(request(), $conversation);
        $conversation->delete();

        return response()->json(null, 204);
    }

    private function authorizeConversation(Request $request, MessageConversation $conversation): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isSuperAdmin()
            || (int) $conversation->assigned_user_id === (int) $user->id
            || (int) $conversation->prepared_by_user_id === (int) $user->id), 403);
    }
}

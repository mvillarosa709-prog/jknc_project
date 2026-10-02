{{-- Existing detail content, rendered inside the Activities main column. --}}

{{-- showMessageDetails --}}
<div
    x-show="showMessageDetails"
    x-cloak
    style="display:none;"
    class="w-full min-w-0"
>
    <div
        @click.stop
        class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    >

        {{-- Conversation header --}}
        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3
                            class="text-[17px] font-semibold text-slate-900"
                            x-text="selectedMessageDetails?.conversation_title || 'Conversation Details'"
                        ></h3>

                        <span
                            class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-semibold text-blue-700"
                            x-text="selectedMessageDetails?.channel || 'Message'"
                        ></span>

                        <span
                            class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold text-slate-600"
                            x-text="selectedMessageDetails?.handling || 'Open'"
                        ></span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">

                        <span>
                            <span class="font-medium text-slate-600">Line:</span>
                            <span x-text="selectedMessageDetails?.line_account || 'Not specified'"></span>
                        </span>

                        <span>
                            <span class="font-medium text-slate-600">Assigned:</span>
                            <span
                                x-text="
                                    selectedMessageDetails?.assigned_user?.name ||
                                    selectedMessageDetails?.assigned_user?.email ||
                                    'Unassigned'
                                "
                            ></span>
                        </span>

                    </div>
                </div>

                <button
                    type="button"
                    @click="returnToActivities()"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>

            </div>
        </div>

        <div class="px-5 py-5">

            <p
                x-show="messageRequestError"
                x-text="messageRequestError"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
            ></p>

            {{-- Relationships --}}
            <section class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">

                <div class="mb-3">
                    <h4 class="text-xs font-semibold text-slate-800">
                        Client Relationship
                    </h4>

                    <p class="mt-1 text-[11px] text-slate-500">
                        Link this conversation to the appropriate company and contact.
                    </p>
                </div>

                @include('partials.activity-relationships', ['relationshipForm' => 'messageRelationships'])

                <div class="mt-3 flex justify-end">
                    <button
                        type="button"
                        @click="saveMessageRelationships()"
                        class="rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700"
                    >
                        Save relationships
                    </button>
                </div>

            </section>

            {{-- Conversation --}}
            <section class="mt-5">

                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800">
                            Conversation
                        </h4>

                        <p class="mt-1 text-[11px] text-slate-500">
                            Messages recorded under this conversation.
                        </p>
                    </div>

                    <span
                        class="text-[11px] text-slate-400"
                        x-text="
                            (selectedMessageDetails?.entries?.length || 0) +
                            ((selectedMessageDetails?.entries?.length || 0) === 1 ? ' message' : ' messages')
                        "
                    ></span>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 sm:px-5">

                    <div class="space-y-5">

                        <template
                            x-for="entry in (selectedMessageDetails?.entries || [])"
                            :key="'message-entry-' + entry.id"
                        >
                            <div
                                class="flex w-full"
                                :class="
                                    String(entry.direction || '').toLowerCase() === 'outgoing'
                                        ? 'justify-end'
                                        : 'justify-start'
                                "
                            >
                                <div
                                    class="max-w-[88%] sm:max-w-[72%]"
                                    :class="
                                        String(entry.direction || '').toLowerCase() === 'outgoing'
                                            ? 'text-right'
                                            : 'text-left'
                                    "
                                >

                                    <div
                                        class="mb-1 flex items-center gap-2 text-[10px] text-slate-500"
                                        :class="
                                            String(entry.direction || '').toLowerCase() === 'outgoing'
                                                ? 'justify-end'
                                                : 'justify-start'
                                        "
                                    >
                                        <span
                                            class="font-semibold text-slate-600"
                                            x-text="
                                                String(entry.direction || '').toLowerCase() === 'outgoing'
                                                    ? 'You'
                                                    : (entry.sender || 'Incoming')
                                            "
                                        ></span>

                                        <span
                                            x-show="entry.message_type && entry.message_type !== 'text'"
                                            class="rounded-full bg-white px-2 py-0.5 ring-1 ring-slate-200"
                                            x-text="entry.message_type"
                                        ></span>
                                    </div>

                                    <div
                                        class="inline-block max-w-full rounded-2xl px-4 py-3 text-left shadow-sm"
                                        :class="
                                            String(entry.direction || '').toLowerCase() === 'outgoing'
                                                ? 'rounded-br-md bg-blue-600 text-white'
                                                : 'rounded-bl-md border border-slate-200 bg-white text-slate-800'
                                        "
                                    >
                                        <p
                                            class="whitespace-pre-wrap break-words text-sm leading-5"
                                            x-text="entry.body"
                                        ></p>

                                        <div
                                            x-show="(entry.activity_attachments || []).length"
                                            class="mt-3 space-y-2"
                                        >
                                            <template
                                                x-for="attachment in (entry.activity_attachments || [])"
                                                :key="'message-entry-attachment-' + attachment.id"
                                            >
                                                <div
                                                    class="mt-2 overflow-hidden rounded-lg"
                                                    :class="
                                                        String(entry.direction || '').toLowerCase() === 'outgoing'
                                                            ? 'bg-white/15'
                                                            : 'border border-slate-200 bg-slate-50'
                                                    "
                                                >
                                                    <!-- IMAGE -->
                                                    <template
                                                        x-if="
                                                            String(attachment.mime_type || '')
                                                                .toLowerCase()
                                                                .startsWith('image/')
                                                            && attachment.safe_url
                                                        "
                                                    >
                                                        <a
                                                            :href="attachment.safe_url"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="block"
                                                        >
                                                            <img
                                                                :src="attachment.safe_url"
                                                                :alt="attachment.original_filename || attachment.filename || 'Image attachment'"
                                                                class="max-h-64 w-full object-contain"
                                                                loading="lazy"
                                                            >
                                                        </a>
                                                    </template>

                                                    <!-- VIDEO -->
                                                    <template
                                                        x-if="
                                                            String(attachment.mime_type || '')
                                                                .toLowerCase()
                                                                .startsWith('video/')
                                                            && attachment.safe_url
                                                        "
                                                    >
                                                        <video
                                                            controls
                                                            preload="metadata"
                                                            class="max-h-64 w-full"
                                                            :src="attachment.safe_url"
                                                        ></video>
                                                    </template>

                                                    <!-- AUDIO -->
                                                    <template
                                                        x-if="
                                                            String(attachment.mime_type || '')
                                                                .toLowerCase()
                                                                .startsWith('audio/')
                                                            && attachment.safe_url
                                                        "
                                                    >
                                                        <div class="p-3">
                                                            <audio
                                                                controls
                                                                preload="metadata"
                                                                class="w-full"
                                                                :src="attachment.safe_url"
                                                            ></audio>
                                                        </div>
                                                    </template>

                                                    <!-- FILE DETAILS -->
                                                    <div class="flex min-w-0 items-center gap-3 px-3 py-2.5">

                                                        <div
                                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
                                                            :class="
                                                                String(entry.direction || '').toLowerCase() === 'outgoing'
                                                                    ? 'bg-white/15 text-white'
                                                                    : 'bg-white text-slate-500'
                                                            "
                                                        >
                                                            <i
                                                                class="far"
                                                                :class="
                                                                    String(attachment.mime_type || '').toLowerCase() === 'application/pdf'
                                                                        ? 'fa-file-pdf'
                                                                        : String(attachment.mime_type || '').toLowerCase().startsWith('image/')
                                                                            ? 'fa-file-image'
                                                                            : String(attachment.mime_type || '').toLowerCase().startsWith('audio/')
                                                                                ? 'fa-file-audio'
                                                                                : String(attachment.mime_type || '').toLowerCase().startsWith('video/')
                                                                                    ? 'fa-file-video'
                                                                                    : 'fa-file'
                                                                "
                                                            ></i>
                                                        </div>

                                                        <div class="min-w-0 flex-1">
                                                            <p
                                                                class="truncate text-xs font-semibold"
                                                                x-text="
                                                                    attachment.original_filename ||
                                                                    attachment.filename ||
                                                                    ('Attachment #' + attachment.id)
                                                                "
                                                            ></p>

                                                            <p
                                                                class="mt-0.5 text-[10px]"
                                                                :class="
                                                                    String(entry.direction || '').toLowerCase() === 'outgoing'
                                                                        ? 'text-blue-100'
                                                                        : 'text-slate-400'
                                                                "
                                                            >
                                                                <span
                                                                    x-text="
                                                                        attachment.category ||
                                                                        attachment.mime_type ||
                                                                        'Attachment'
                                                                    "
                                                                ></span>

                                                                <span
                                                                    x-show="attachment.size_bytes"
                                                                    x-text="
                                                                        ' - ' +
                                                                        messageAttachmentSize(
                                                                            attachment.size_bytes
                                                                        )
                                                                    "
                                                                ></span>
                                                            </p>
                                                        </div>

                                                        <a
                                                            x-show="attachment.safe_url"
                                                            :href="attachment.safe_url"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="shrink-0 text-[11px] font-semibold underline underline-offset-2"
                                                            :class="
                                                                String(entry.direction || '').toLowerCase() === 'outgoing'
                                                                    ? 'text-white hover:text-blue-100'
                                                                    : 'text-blue-600 hover:text-blue-800'
                                                            "
                                                        >
                                                            Open
                                                        </a>

                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] text-slate-400"
                                        :class="
                                            String(entry.direction || '').toLowerCase() === 'outgoing'
                                                ? 'justify-end'
                                                : 'justify-start'
                                        "
                                    >
                                        <span
                                            x-text="
                                                formatMessageDate(
                                                    entry.sent_at ||
                                                    entry.received_at ||
                                                    entry.created_at
                                                )
                                            "
                                        ></span>

                                        <span aria-hidden="true">&bull;</span>

                                        <span
                                            class="font-medium"
                                            x-text="entry.delivery_status || (
                                                String(entry.direction || '').toLowerCase() === 'incoming'
                                                    ? 'Received'
                                                    : 'Pending'
                                            )"
                                        ></span>

                                        <template x-if="entry.read_at">
                                            <span>
                                                &bull;
                                                Seen
                                                <span x-text="formatMessageDate(entry.read_at)"></span>
                                            </span>
                                        </template>

                                        <span x-show="entry.technical_metadata?.failure_code" class="text-rose-700"
                                            x-text="entry.technical_metadata?.failure_code === 'acceptance_unknown' ? 'Acceptance unknown - do not resend; ask an administrator to reconcile.' : (entry.technical_metadata?.failure_code || '')"></span>
                                        <template x-if="!entry.read_at && entry.delivered_at">
                                            <span>
                                                &bull;
                                                <span x-text="formatMessageDate(entry.delivered_at)"></span>
                                            </span>
                                        </template>
                                    </div>

                                    <div
                                        x-show="
                                            String(entry.direction || '').toLowerCase() === 'incoming' &&
                                            entry.recipient
                                        "
                                        class="mt-1 text-[10px] text-slate-400"
                                    >
                                        To:
                                        <span x-text="entry.recipient"></span>
                                    </div>

                                </div>
                            </div>
                        </template>

                        <div
                            x-show="selectedMessageDetails && !selectedMessageDetails.entries?.length"
                            class="py-10 text-center"
                        >
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                                <i class="far fa-comment-dots"></i>
                            </div>

                            <p class="mt-3 text-sm font-medium text-slate-600">
                                No messages yet
                            </p>

                            <p class="mt-1 text-xs text-slate-400">
                                Stored messages for this conversation will appear here.
                            </p>
                        </div>

                    </div>
                </div>

            </section>

            {{-- Local-only reply notice --}}
            <div x-show="!selectedMessageDetails?.messenger_outbound_available" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <div class="flex items-start gap-3">

                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-amber-600 shadow-sm">
                        <i class="fas fa-info-circle text-xs"></i>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-amber-900">
                            External delivery is not connected yet
                        </p>

                        <p class="mt-1 text-[11px] leading-5 text-amber-800">
                            Replies entered below are stored inside the JK&amp;C application as pending records.
                            They are not sent through
                            <span
                                class="font-semibold"
                                x-text="selectedMessageDetails?.channel || 'the external provider'"
                            ></span>
                            yet.
                        </p>
                    </div>

                </div>
            </div>

            {{-- Reply composer --}}
            <div x-show="selectedMessageDetails?.messenger_outbound_available" class="mt-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900">
                Messenger text delivery is enabled. Status refreshes every 10 seconds. Replies require a recent inbound message (24 hours).
                <p class="mt-1"
                   x-show="messageDraftHasSingleMessengerImage()">
                    One image is selected and can be sent through Messenger.
                </p>
                <p class="mt-1"
                   x-show="messageDraftAttachmentIds.length && !messageDraftHasSingleMessengerImage()">
                    This attachment selection cannot be sent through Messenger yet. The reply will be stored locally.
                </p>
            </div>
            <section class="mt-4">

                <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                    Reply
                </label>

                <textarea
                    rows="4"
                    x-model="newMessageEntry.body"
                    :disabled="messageSaving"
                    placeholder="Write a reply..."
                    class="w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                ></textarea>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label
                        class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        :class="messageAttachmentUploading ? 'pointer-events-none opacity-60' : ''"
                    >
                        <span aria-hidden="true">&#128206;</span>
                        <span x-text="messageAttachmentUploading ? 'Uploading...' : 'Attach File'"></span>

                        <input
                            x-ref="messageAttachmentInput"
                            type="file"
                            class="hidden"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.mp3,.wav,.m4a,.mp4,.mov"
                            @change="uploadMessageAttachment($event)"
                            :disabled="messageAttachmentUploading || messageSaving"
                        >
                    </label>

                    <span class="text-[11px] text-slate-400">
                        Maximum file size: 20 MB
                    </span>
                </div>

                {{-- Current reply attachments --}}
                <div
                    x-show="
                        messageAttachments.some(
                            attachment => isMessageDraftAttachment(attachment)
                        )
                    "
                    class="mt-3"
                >
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <p class="text-[11px] font-semibold text-slate-600">
                            Current reply attachments
                        </p>

                        <span
                            class="text-[10px] text-slate-400"
                            x-text="
                                messageAttachments.filter(
                                    attachment =>
                                        isMessageDraftAttachment(attachment)
                                ).length + ' selected'
                            "
                        ></span>
                    </div>

                    <div
                        class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <template
                            x-for="
                                attachment in messageAttachments.filter(
                                    attachment =>
                                        isMessageDraftAttachment(attachment)
                                )
                            "
                            :key="'message-draft-attachment-' + attachment.id"
                        >
                            <div
                                class="group relative overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                            >
                                {{-- Image preview --}}
                                <template
                                    x-if="
                                        String(
                                            attachment.mime_type || ''
                                        )
                                            .toLowerCase()
                                            .startsWith('image/') &&
                                        attachment.safe_url
                                    "
                                >
                                    <a
                                        :href="attachment.safe_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block bg-slate-100"
                                    >
                                        <img
                                            :src="attachment.safe_url"
                                            :alt="messageAttachmentName(attachment)"
                                            class="h-28 w-full object-cover"
                                            loading="lazy"
                                        >
                                    </a>
                                </template>

                                {{-- Non-image preview --}}
                                <template
                                    x-if="
                                        !String(
                                            attachment.mime_type || ''
                                        )
                                            .toLowerCase()
                                            .startsWith('image/')
                                    "
                                >
                                    <div
                                        class="flex h-20 items-center justify-center bg-slate-100 text-2xl text-slate-400"
                                    >
                                        <i
                                            class="far"
                                            :class="
                                                String(
                                                    attachment.mime_type || ''
                                                ).toLowerCase() ===
                                                'application/pdf'
                                                    ? 'fa-file-pdf'
                                                    : String(
                                                        attachment.mime_type || ''
                                                    )
                                                        .toLowerCase()
                                                        .startsWith('audio/')
                                                        ? 'fa-file-audio'
                                                        : String(
                                                            attachment.mime_type || ''
                                                        )
                                                            .toLowerCase()
                                                            .startsWith('video/')
                                                            ? 'fa-file-video'
                                                            : 'fa-file'
                                            "
                                        ></i>
                                    </div>
                                </template>

                                {{-- Remove from current draft only --}}
                                <button
                                    type="button"
                                    @click="
                                        removeMessageDraftAttachment(
                                            attachment.id
                                        )
                                    "
                                    title="Remove from reply"
                                    aria-label="Remove attachment from reply"
                                    class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/95 text-sm font-bold text-slate-500 shadow transition hover:bg-red-50 hover:text-red-600"
                                >
                                    &times;
                                </button>

                                <div class="p-2.5">
                                    <p
                                        class="truncate text-xs font-semibold text-slate-700"
                                        x-text="
                                            messageAttachmentName(
                                                attachment
                                            )
                                        "
                                    ></p>

                                    <div
                                        class="mt-1 flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="truncate text-[10px] text-slate-400"
                                            x-text="
                                                [
                                                    attachment.category,
                                                    messageAttachmentSize(
                                                        attachment.size_bytes
                                                    )
                                                ]
                                                    .filter(Boolean)
                                                    .join(' - ')
                                            "
                                        ></span>

                                        <a
                                            x-show="attachment.safe_url"
                                            :href="attachment.safe_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="shrink-0 text-[10px] font-semibold text-blue-600 hover:text-blue-800"
                                        >
                                            Open
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Conversation-level attachment loading --}}
                <div
                    x-show="messageAttachmentLoading"
                    class="mt-3 text-xs text-slate-400"
                >
                    Loading attachments...
                </div>

                {{-- Historical / conversation files --}}
                <div
                    x-show="
                        !messageAttachmentLoading &&
                        messageAttachments.some(
                            attachment =>
                                !isMessageDraftAttachment(attachment)
                        )
                    "
                    x-data="{ open: false }"
                    x-init="$watch('messageDraftSession', () => open = false)"
                    class="mt-3 rounded-lg border border-slate-200 bg-white"
                >
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <i
                                class="far fa-folder text-xs text-slate-400"
                            ></i>

                            <span
                                class="text-xs font-semibold text-slate-700"
                            >
                                Conversation Files
                            </span>

                            <span
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500"
                                x-text="
                                    messageAttachments.filter(
                                        attachment =>
                                            !isMessageDraftAttachment(
                                                attachment
                                            )
                                    ).length
                                "
                            ></span>
                        </div>

                        <i
                            class="fas fa-chevron-down text-[10px] text-slate-400 transition-transform"
                            :class="open ? 'rotate-180' : ''"
                        ></i>
                    </button>

                    <div
                        x-show="open"
                        x-collapse
                        class="border-t border-slate-100"
                    >
                        <div class="divide-y divide-slate-100">
                            <template
                                x-for="
                                    attachment in messageAttachments.filter(
                                        attachment =>
                                            !isMessageDraftAttachment(
                                                attachment
                                            )
                                    )
                                "
                                :key="'conversation-file-' + attachment.id"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 px-3 py-2.5"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2.5"
                                    >
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500"
                                        >
                                            <i
                                                class="far"
                                                :class="
                                                    String(
                                                        attachment.mime_type ||
                                                        ''
                                                    )
                                                        .toLowerCase()
                                                        .startsWith('image/')
                                                        ? 'fa-file-image'
                                                        : String(
                                                            attachment.mime_type ||
                                                            ''
                                                        )
                                                            .toLowerCase()
                                                            .startsWith('audio/')
                                                            ? 'fa-file-audio'
                                                            : String(
                                                                attachment.mime_type ||
                                                                ''
                                                            )
                                                                .toLowerCase()
                                                                .startsWith('video/')
                                                                ? 'fa-file-video'
                                                                : String(
                                                                    attachment.mime_type ||
                                                                    ''
                                                                )
                                                                    .toLowerCase() ===
                                                                    'application/pdf'
                                                                    ? 'fa-file-pdf'
                                                                    : 'fa-file'
                                                "
                                            ></i>
                                        </div>

                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-medium text-slate-700"
                                                x-text="
                                                    messageAttachmentName(
                                                        attachment
                                                    )
                                                "
                                            ></p>

                                            <p
                                                class="mt-0.5 text-[10px] text-slate-400"
                                                x-text="
                                                    [
                                                        attachment.category,
                                                        messageAttachmentSize(
                                                            attachment.size_bytes
                                                        )
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' - ')
                                                "
                                            ></p>
                                        </div>
                                    </div>

                                    <button type="button" @click="selectMessageDraftAttachment(attachment.id)" :disabled="messageSaving" class="shrink-0 text-[11px] font-semibold text-slate-600 hover:text-blue-600 disabled:opacity-50">Use in reply</button>
                                    <a
                                        x-show="attachment.safe_url"
                                        :href="attachment.safe_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="shrink-0 text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                    >
                                        Open
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

                    <div class="text-[10px] text-slate-400">
                        <p x-show="selectedMessageDetails?.messenger_outbound_available && !messageDraftAttachmentIds.length">
                            Messenger text will be sent through the connected Page.
                        </p>
                        <p x-show="selectedMessageDetails?.messenger_outbound_available && messageDraftHasSingleMessengerImage()">
                            The selected image will be sent through the connected Messenger Page.
                        </p>
                        <p x-show="selectedMessageDetails?.messenger_outbound_available && messageDraftAttachmentIds.length && !messageDraftHasSingleMessengerImage()">
                            This attachment selection is local-only and will not be sent through Messenger.
                        </p>
                        <p x-show="!selectedMessageDetails?.messenger_outbound_available">
                            Local record only &mdash; no external delivery will be attempted.
                        </p>
                        <p>
                            Draft text and selections reset when you leave, reopen, or reload this conversation. Uploaded files remain in Conversation Files.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="addMessageEntry()"
                        :disabled="messageSaving || messageAttachmentUploading || !newMessageEntry.body?.trim()"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-[#3157e8] px-4 text-xs font-semibold text-white transition hover:bg-[#2446c8] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <i
                            class="fas"
                            :class="messageSaving ? 'fa-spinner fa-spin' : 'fa-save'"
                        ></i>

                        <span x-text="
                            messageSaving
                                ? 'Submitting...'
                                : (
                                    selectedMessageDetails?.messenger_outbound_available &&
                                    messageDraftCanSendExternally()
                                        ? (
                                            messageDraftHasSingleMessengerImage()
                                                ? 'Send Image'
                                                : 'Send Message'
                                        )
                                        : 'Store Reply'
                                )
                        "></span>
                    </button>

                </div>

            </section>

        </div>
    </div>
</div>
{{-- visitDetailsOpen --}}
<div
        x-show="visitDetailsOpen"
        x-cloak

        class="w-full min-w-0"
    >
        <div class="w-full rounded-xl border border-gray-200 bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Visit Details</h2>
                    <p class="mt-1 text-[10px] text-gray-400" x-text="selectedVisitDetails?.visit_reference"></p>
                </div>
                <button type="button" @click="returnToActivities()" class="text-gray-400 hover:text-gray-700">&times;</button>
            </div>
            <template x-if="selectedVisitDetails">
                <div class="grid gap-4 text-xs sm:grid-cols-2">
                    <div><div class="text-[10px] uppercase tracking-wide text-gray-400">Visitor</div><div class="mt-1 font-medium text-gray-900" x-text="selectedVisitDetails.visitor?.name || '-'"></div><div class="mt-1 text-gray-500" x-text="selectedVisitDetails.visitor?.visitor_reference || '-'"></div></div>
                    <div><div class="text-[10px] uppercase tracking-wide text-gray-400">Company</div><div class="mt-1 text-gray-800" x-text="selectedVisitDetails.company || '-'"></div></div>
                    <div><div class="text-[10px] uppercase tracking-wide text-gray-400">Check In</div><div class="mt-1 text-gray-800" x-text="formatVisitorDate(selectedVisitDetails.checked_in_at)"></div><div class="mt-1 text-gray-500" x-text="visitorMovementSummary(selectedVisitDetails.check_in_movement)"></div></div>
                    <div><div class="text-[10px] uppercase tracking-wide text-gray-400">Check Out</div><div class="mt-1 text-gray-800" x-text="formatVisitorDate(selectedVisitDetails.checked_out_at)"></div><div class="mt-1 text-gray-500" x-text="visitorMovementSummary(selectedVisitDetails.check_out_movement)"></div></div>
                    <div><div class="text-[10px] uppercase tracking-wide text-gray-400">Activity</div><div class="mt-1 text-gray-800" x-text="selectedVisitDetails.activity?.label || 'Pending Link'"></div></div>
                    <div><div class="text-[10px] uppercase tracking-wide text-gray-400">Lifecycle</div><div class="mt-1 font-medium text-gray-800" x-text="selectedVisitDetails.status"></div></div>
                </div>
            </template>
            <div class="mt-5 flex justify-end">
                <button type="button" @click="returnToActivities()" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700">Close</button>
            </div>
        </div>
    </div>

{{-- O.B. V3 Client Trip Ticket Manager --}}
<div
    x-data="tripTicketManager()"
    x-show="isOpen"
    x-cloak
    @trip-ticket-manage.window="load($event.detail)"
    @keydown.escape.window="close()"
    class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4"
>
    <div
        x-show="isOpen"
        x-transition
        @click.outside="close()"
        class="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
    >
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-600">Client Trip Ticket Manager</p>
                <h2 class="mt-1 font-mono text-sm font-bold text-slate-900" x-text="ticket?.reference_no || 'Trip Ticket'"></h2>
                <p class="mt-1 text-sm text-slate-500" x-text="ticket?.client_name_snapshot || ''"></p>
            </div>

            <div class="flex items-center gap-3">
                <span
                    x-show="ticket"
                    class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase text-slate-600"
                    x-text="ticket?.status"
                ></span>

                <button
                    type="button"
                    @click="close()"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div x-show="loading" class="flex flex-1 items-center justify-center py-24">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i>
                <p class="mt-3 text-sm text-slate-500">Loading Trip Ticket...</p>
            </div>
        </div>

        <div x-show="!loading && errorMessage" class="mx-6 mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <span x-text="errorMessage"></span>
        </div>

        <div x-show="!loading && successMessage" class="mx-6 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <span x-text="successMessage"></span>
        </div>

        <div
            x-show="!loading && ticket && !editable"
            class="mx-6 mt-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
        >
            This Trip Ticket is read-only for your account.
        </div>

        <div x-show="!loading && ticket" class="flex-1 overflow-y-auto px-6 py-6">
            <div class="space-y-6">

                <!-- Completion Checklist -->
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Trip Ticket Completion Checklist</h3>
                            <p class="mt-1 text-xs text-slate-500">Every requirement must be satisfied before this ticket can be completed.</p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-[10px] font-bold uppercase"
                            :class="ticket?.status === 'Completed' || completionReady
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-amber-100 text-amber-700'"
                            x-text="ticket?.status === 'Completed'
                                ? 'Completed'
                                : (completionReady ? 'Ready' : 'Incomplete')"
                        ></span>
                    </div>

                    <div class="mt-4 grid gap-2 md:grid-cols-2">
                        <template x-for="item in visibleChecklist" :key="item.key">
                            <div
                                class="rounded-xl border bg-white px-4 py-3"
                                :class="item.complete ? 'border-emerald-200' : 'border-amber-200'"
                            >
                                <div class="flex items-start gap-3">
                                    <span
                                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] text-white"
                                        :class="item.complete ? 'bg-emerald-500' : 'bg-amber-500'"
                                    >
                                        <i class="fas" :class="item.complete ? 'fa-check' : 'fa-exclamation'"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <template x-if="item.key === 'master_for_completion' && !item.complete">
                                            <div>
                                                <p class="text-xs font-bold text-slate-800">Master O.B. must be For Completion</p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">The parent Official Business must be returned and moved to For Completion before this Trip Ticket can be completed.</p>
                                                <p class="mt-2 text-xs font-semibold text-slate-700">
                                                    Master O.B.:
                                                    <span x-text="ticket?.trip?.reference_no || 'Master O.B.'"></span>
                                                </p>
                                                <button
                                                    x-show="ticket?.official_business_trip_id"
                                                    type="button"
                                                    @click="viewMasterOfficialBusiness()"
                                                    class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100"
                                                >
                                                    <i class="fas fa-arrow-up-right-from-square mr-1"></i>
                                                    View Master O.B.
                                                </button>
                                            </div>
                                        </template>
                                        <template x-if="!(item.key === 'master_for_completion' && !item.complete)">
                                            <div>
                                                <p class="text-xs font-bold text-slate-800" x-text="item.label"></p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500" x-text="item.detail"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
                <!-- Stops -->
                <section class="rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">1. Client Stops</h3>
                            <p class="mt-1 text-xs text-slate-500" x-text="editable ? 'Resolve or cancel every client stop.' : 'Client stop details and resolution.'"></p>
                        </div>

                        <button
                            x-show="editable"
                            type="button"
                            @click="addStop()"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                        >
                            <i class="fas fa-plus mr-1"></i>
                            Add Client Stop
                        </button>
                    </div>

                    <p
                        x-show="!stops.length"
                        class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-5 text-center text-xs text-slate-400"
                    >
                        No client stops added yet.
                    </p>

                    <div class="mt-4 space-y-3">
                        <template x-for="(stop, index) in stops" :key="stop.id || `stop-${index}`">
                            <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-12">
                                <div class="md:col-span-1">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Seq.</label>
                                    <template x-if="editable">
                                        <input
                                            type="number"
                                            min="1"
                                            x-model.number="stop.sequence"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                    </template>
                                    <template x-if="!editable">
                                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" x-text="stop.sequence"></p>
                                    </template>
                                </div>

                                <div class="md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Destination</label>
                                    <template x-if="editable">
                                        <input
                                            type="text"
                                            x-model="stop.destination"
                                            placeholder="Client destination"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                    </template>
                                    <template x-if="!editable">
                                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" x-text="stop.destination || 'Not recorded'"></p>
                                    </template>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Activity</label>
                                    <template x-if="editable">
                                        <input
                                            type="text"
                                            x-model="stop.activity"
                                            placeholder="Activity / purpose"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                    </template>
                                    <template x-if="!editable">
                                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" x-text="stop.activity || 'Not recorded'"></p>
                                    </template>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Resolution</label>
                                    <template x-if="editable">
                                        <select
                                            x-model="stop.resolution_status"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                            <option value="Planned">Planned</option>
                                            <option value="Visited">Visited</option>
                                            <option value="Resolved">Resolved</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </template>
                                    <template x-if="!editable">
                                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" x-text="stop.resolution_status || 'Not recorded'"></p>
                                    </template>
                                </div>

                                <div class="flex items-end md:col-span-1">
                                    <button
                                        x-show="editable"
                                        type="button"
                                        @click="removeStop(index)"
                                        class="w-full rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
                <!-- Personnel -->
                <section class="rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">2. Client-Specific Personnel</h3>
                            <p class="mt-1 text-xs text-slate-500" x-text="editable ? 'Confirm who actually participated in this client\'s travel.' : 'Personnel who participated in this client\'s travel.'"></p>
                        </div>

                        <button
                            x-show="editable"
                            type="button"
                            @click="addPersonnel()"
                            :disabled="!employeeOptions.length"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <i class="fas fa-plus mr-1"></i>
                            Add Personnel
                        </button>
                    </div>

                    <p
                        x-show="!personnel.length"
                        class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-5 text-center text-xs text-slate-400"
                    >
                        No client-specific personnel added yet.
                    </p>

                    <div class="mt-4 space-y-3">
                        <template x-for="(person, index) in personnel" :key="person.id || `person-${index}`">
                            <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-12">

                                <div class="md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Employee</label>

                                    <template x-if="editable && person.id">
                                        <input
                                            type="text"
                                            :value="person.employee_name_snapshot"
                                            disabled
                                            class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600"
                                        >
                                    </template>

                                    <template x-if="editable && !person.id">
                                        <select
                                            x-model.number="person.employee_id"
                                            @change="applyEmployeeOption(person)"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                            <option value="">Select employee</option>

                                            <template
                                                x-for="employee in availableEmployeesFor(index)"
                                                :key="employee.id"
                                            >
                                                <option
                                                    :value="employee.id"
                                                    x-text="employeeLabel(employee)"
                                                ></option>
                                            </template>
                                        </select>
                                    </template>

                                    <template x-if="!editable">
                                        <p
                                            class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700"
                                            x-text="person.employee_name_snapshot || employeeLabel(person) || 'Not recorded'"
                                        ></p>
                                    </template>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Role</label>
                                    <template x-if="editable">
                                        <input
                                            type="text"
                                            x-model="person.role"
                                            placeholder="Traveler"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                    </template>
                                    <template x-if="!editable">
                                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" x-text="person.role || 'Not recorded'"></p>
                                    </template>
                                </div>

                                <div class="md:col-span-4">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Participation</label>
                                    <template x-if="editable">
                                        <select
                                            x-model="person.participation_status"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >
                                            <option value="Assigned">Assigned</option>
                                            <option value="Confirmed">Confirmed</option>
                                            <option value="Did Not Go">Did Not Go</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </template>
                                    <template x-if="!editable">
                                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" x-text="person.participation_status || 'Not recorded'"></p>
                                    </template>
                                </div>

                                <div class="flex items-end md:col-span-1">
                                    <button
                                        x-show="editable"
                                        type="button"
                                        @click="removePersonnel(index)"
                                        class="w-full rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div
                        x-show="editable"
                        class="mt-4 flex justify-end"
                    >
                        <button
                            type="button"
                            @click="saveDetails()"
                            :disabled="busy || !stops.length || !personnel.length"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span x-show="!busy">Save Stops & Personnel</span>
                            <span x-show="busy">Saving...</span>
                        </button>
                    </div>
                </section>

                <!-- Evidence -->
                <section class="rounded-2xl border border-slate-200 p-5">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">3. Client Evidence</h3>
                        <p class="mt-1 text-xs text-slate-500" x-text="editable ? 'Upload a file or provide an external evidence link.' : 'Evidence recorded for this client trip.'"></p>
                    </div>

                    <div class="mt-4 space-y-2">
                        <template x-for="evidence in evidenceItems" :key="evidence.id">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div>
                                    <p class="text-xs font-bold text-slate-800" x-text="evidence.original_filename || evidence.filename || 'Evidence'"></p>
                                    <p class="mt-1 text-[11px] text-slate-500" x-text="evidence.category || ''"></p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a
                                        x-show="evidence.safe_url"
                                        :href="evidence.safe_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                    >
                                        Open
                                    </a>
                                    <button
                                        x-show="editable"
                                        type="button"
                                        @click="removeEvidence(evidence)"
                                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </template>

                        <p x-show="!evidenceItems.length" class="text-xs text-slate-400">No evidence recorded yet.</p>
                    </div>

                    <div x-show="editable" class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-[10px] font-bold uppercase text-slate-500">Evidence Type</label>
                            <select x-model="evidenceForm.evidence_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <template x-for="type in evidenceTypes" :key="type">
                                    <option :value="type" x-text="type"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold uppercase text-slate-500">File</label>
                            <input
                                x-ref="evidenceFile"
                                type="file"
                                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                                    Take Photo
                                    <input type="file" accept="image/*" capture="environment" class="sr-only"
                                        @change="if ($event.target.files.length) { evidenceForm.evidence_type = 'Photo'; addEvidence($event.target.files[0]); } $event.target.value = ''">
                                </label>
                                <label class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                                    Record Video
                                    <input type="file" accept="video/*" capture="environment" class="sr-only"
                                        @change="if ($event.target.files.length) { evidenceForm.evidence_type = 'Other Client-Specific Evidence'; addEvidence($event.target.files[0]); } $event.target.value = ''">
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold uppercase text-slate-500">OR External URL</label>
                            <input
                                type="url"
                                x-model="evidenceForm.external_url"
                                placeholder="https://..."
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Description</label>
                            <textarea
                                x-model="evidenceForm.description"
                                rows="2"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                    </div>

                    <button
                        x-show="editable"
                        type="button"
                        @click="addEvidence()"
                        :disabled="busy"
                        class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50"
                    >
                        Add Evidence
                    </button>
                </section>

                <!-- Finance -->
                <section x-show="canViewFinance" class="rounded-2xl border border-slate-200 p-5">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">4. Billing & Financial Treatment</h3>
                        <p class="mt-1 text-xs text-slate-500">Resolve how this client's travel will be treated financially.</p>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-[10px] font-bold uppercase text-slate-500">Billing Treatment</label>
                            <select
                                x-model="finance.billing_treatment"
                                :disabled="!editable"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Select treatment</option>
                                <template x-for="type in billingTreatments" :key="type">
                                    <option :value="type" x-text="type"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold uppercase text-slate-500">Additional Costs Status</label>
                            <select
                                x-model="finance.additional_costs_status"
                                :disabled="!editable"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="Pending">Pending</option>
                                <option value="Not Applicable">Not Applicable</option>
                                <option value="Resolved">Resolved</option>
                            </select>
                        </div>

                        <div x-show="usesTravelCredit">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Travel Credit Units</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                x-model="finance.travel_credit_units"
                                :disabled="!editable"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                        </div>

                        <div x-show="usesTravelCredit">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Travel Credit Value</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                x-model="finance.travel_credit_value"
                                :disabled="!editable"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                        </div>

                        <div>
                            <label class="text-[10px] font-bold uppercase text-slate-500">Evidence Required?</label>
                            <select
                                x-model="finance.evidence_required"
                                :disabled="!editable"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold uppercase text-slate-500">Evidence Requirement Notes</label>
                            <input
                                type="text"
                                x-model="finance.evidence_requirement_notes"
                                :disabled="!editable"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Notes</label>
                            <textarea
                                x-model="finance.notes"
                                :disabled="!editable"
                                rows="2"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                    </div>

                    <button
                        x-show="editable"
                        type="button"
                        @click="saveFinance()"
                        :disabled="busy"
                        class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50"
                    >
                        Save Financial Treatment
                    </button>
                </section>

                <!-- Costs -->
                <section x-show="canViewFinance" class="rounded-2xl border border-slate-200 p-5">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">5. Additional Costs</h3>
                        <p class="mt-1 text-xs text-slate-500">Record additional costs when applicable.</p>
                    </div>

                    <div class="mt-4 space-y-2">
                        <template x-for="cost in costs" :key="cost.id">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div>
                                    <p class="text-xs font-bold text-slate-800">
                                        <span x-text="cost.category || 'Cost'"></span>
                                        -
                                        <span x-text="cost.amount"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500" x-text="cost.description"></p>
                                </div>

                                <button
                                    x-show="editable"
                                    type="button"
                                    @click="removeCost(cost)"
                                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"
                                >
                                    Remove
                                </button>
                            </div>
                        </template>

                        <p x-show="!costs.length" class="text-xs text-slate-400">No additional costs recorded.</p>
                    </div>

                    <div x-show="editable" class="mt-4 grid gap-3 md:grid-cols-2">
                        <input
                            type="text"
                            x-model="costForm.category"
                            placeholder="Category"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >

                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            x-model="costForm.amount"
                            placeholder="Amount"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >

                        <textarea
                            x-model="costForm.description"
                            rows="2"
                            placeholder="Description"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                        ></textarea>

                        <input
                            type="date"
                            x-model="costForm.incurred_on"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >

                        <select
                            x-model="costForm.billing_treatment"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Billing treatment</option>
                            <template x-for="type in billingTreatments" :key="type">
                                <option :value="type" x-text="type"></option>
                            </template>
                        </select>

                        <input
                            type="text"
                            x-model="costForm.evidence_reference"
                            placeholder="Evidence reference"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                        >
                    </div>

                    <button
                        x-show="editable"
                        type="button"
                        @click="addCost()"
                        :disabled="busy"
                        class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 disabled:opacity-50"
                    >
                        Add Cost
                    </button>
                </section>

            </div>
        </div>

        <div x-show="!loading && ticket" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <p class="text-xs text-slate-500">
                <span
    x-text="
        tripTicketDetail?.status === 'Completed'
            ? 'This Trip Ticket has been completed.'
            : (!tripTicketDetail?.permissions?.update
                ? 'This Trip Ticket is available for viewing only.'
                : 'Complete the checklist before finalizing this client Trip Ticket.')
    "
>Complete the checklist before finalizing this client Trip Ticket.</span>
            </p>

            <div class="flex gap-2">
                <button
                    type="button"
                    @click="refresh()"
                    :disabled="busy"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                 x-show="tripTicketDetail?.status !== 'Completed' && tripTicketDetail?.permissions?.update">
                    Refresh Checklist
                </button>

                <button
                    x-show="canComplete"
                    type="button"
                    @click="completeTicket()"
                    :disabled="busy || !completionReady"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Complete Trip Ticket
                </button>
            </div>
        </div>
    </div>
</div>
{{-- selectedOfficialBusinessDetails --}}
<div
        x-show="selectedOfficialBusinessDetails"
        x-cloak

        class="w-full min-w-0"
    >
        <section
            @click.stop
            class="flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" role="region"
            aria-labelledby="official-business-detail-title"
        >
            <header class="shrink-0 border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-blue-700">Official Business</span>
                            <span class="text-xs font-semibold text-slate-500" x-text="selectedOfficialBusinessDetails?.ref || selectedOfficialBusinessDetails?.ob_reference_no"></span>
                        </div>
                        <h2 id="official-business-detail-title" class="mt-2 truncate text-xl font-bold text-slate-900" x-text="selectedOfficialBusinessDetails?.employee || selectedOfficialBusinessDetails?.employee_name || 'Official Business Record'"></h2>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-500" x-text="selectedOfficialBusinessDetails?.purpose || 'Official business travel'"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            x-show="selectedOfficialBusinessDetails?.id"
                            :href="selectedOfficialBusinessDetails?.id ? `{{ url('/api/official-business') }}/${selectedOfficialBusinessDetails.id}/history.pdf` : '#'"
                            target="_blank"
                            rel="noopener"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            <i class="fas fa-history mr-1"></i>
                            Download O.B. History PDF
                        </a>

                        <button type="button" @click="returnToActivities()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close Official Business details">
                        <i class="fas fa-times"></i>
                    </button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                    <template x-for="card in officialBusinessSummaryCards" :key="card.label">
                        <div class="min-w-0 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400" x-text="card.label"></p>
                            <p class="mt-1 truncate text-xs font-semibold text-slate-800" :title="card.value" x-text="card.value"></p>
                        </div>
                    </template>
                </div>
            </header>

            <nav class="shrink-0 overflow-x-auto border-b border-slate-200 bg-slate-50 px-3 sm:px-6" aria-label="Official Business detail sections">
                <div class="flex min-w-max gap-1 py-2">
                    <template x-for="tab in officialBusinessDetailTabs" :key="tab.key">
                        <button
                            type="button"
                            @click="officialBusinessDetailTab = tab.key"
                            :class="officialBusinessDetailTab === tab.key ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:bg-white hover:text-slate-800'"
                            class="rounded-lg px-3 py-2 text-[11px] font-semibold transition"
                            x-text="tab.label"
                        ></button>
                    </template>
                </div>
            </nav>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="grid min-h-full grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <main class="min-w-0 px-4 py-5 sm:px-6">
                        <div x-show="officialBusinessDetailTab === 'overview'" class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <template x-for="field in officialBusinessOverviewFields" :key="field.label">
                                    <div :class="field.wide ? 'sm:col-span-2' : ''" class="rounded-xl border border-slate-200 p-4">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="field.label"></p>
                                        <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800" x-text="field.value"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div x-show="officialBusinessDetailTab === 'itinerary'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <template x-for="field in officialBusinessItineraryFields" :key="field.label">
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="field.label"></p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800" x-text="field.value"></p>
                                </div>
                            </template>
                        </div>

                        <div x-show="officialBusinessDetailTab === 'expenses'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <template x-for="field in officialBusinessExpenseFields" :key="field.label">
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="field.label"></p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800" x-text="field.value"></p>
                                </div>
                            </template>
                        </div>

                        <div x-show="officialBusinessDetailTab === 'files'">
                            <div x-show="officialBusinessFiles.length" class="space-y-2">
                                <template x-for="(file, index) in officialBusinessFiles" :key="file.path || file.url || index">
                                    <a :href="file.url || file.path" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-blue-700 transition hover:bg-blue-50">
                                        <i class="far fa-file text-slate-400"></i>
                                        <span class="truncate" x-text="file.name || file.path || ('File ' + (index + 1))"></span>
                                    </a>
                                </template>
                            </div>
                            <p x-show="!officialBusinessFiles.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No files attached.</p>
                        </div>

                        <div x-show="officialBusinessDetailTab === 'related'">
                            <p x-show="officialBusinessDetailLoading" class="py-10 text-center text-sm text-slate-400">Loading related activities...</p>
                            <div x-show="!officialBusinessDetailLoading && officialBusinessRelatedActivities.length" class="space-y-2">
                                <template x-for="activity in officialBusinessRelatedActivities" :key="activity.key">
                                    <div class="rounded-xl border border-slate-200 px-4 py-3">
                                        <p class="text-xs font-bold uppercase text-blue-600" x-text="formatActivityType(String(activity.type || '').toLowerCase())"></p>
                                        <p class="mt-1 text-sm font-semibold text-slate-800" x-text="activity.title"></p>
                                    </div>
                                </template>
                            </div>
                            <p x-show="!officialBusinessDetailLoading && !officialBusinessRelatedActivities.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No related activities.</p>
                        </div>

                        <div x-show="officialBusinessDetailTab === 'completion'" class="space-y-4">
                            <div
                                x-show="Number(selectedOfficialBusinessDetails?.workflow_version || selectedOfficialBusinessDetails?.workflowVersion || 1) >= 3 && selectedOfficialBusinessDetails?.is_client_travel"
                                class="space-y-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Client Trip Tickets</p>
                                    <span class="text-xs text-slate-400" x-text="`${selectedOfficialBusinessDetails?.trip_tickets?.length || 0} visible`"></span>
                                </div>
                                <template x-for="ticket in (selectedOfficialBusinessDetails?.trip_tickets || [])" :key="ticket.id">
                                    <article class="rounded-xl border border-slate-200 bg-white p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="font-mono text-xs font-semibold text-blue-700" x-text="ticket.reference_no"></p>
                                                <p class="mt-1 text-sm font-semibold text-slate-900" x-text="ticket.client_name_snapshot"></p>
                                            </div>
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase text-slate-600" x-text="ticket.status"></span>
                                        </div>
                                        <p class="mt-3 text-xs text-slate-500" x-text="`${ticket.stops_count} stop(s) - ${ticket.personnel_count} personnel - ${ticket.evidence_count} evidence item(s)`"></p>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <a :href="ticket.pdf_url" target="_blank" rel="noopener" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100">Trip Ticket PDF</a>
                                            <a :href="ticket.history_pdf_url" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">History PDF</a>
                                            <button
                                                type="button"
                                                @click="$dispatch('trip-ticket-manage', { ticketId: ticket.id, masterId: selectedOfficialBusinessDetails?.id })"
                                                class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800"
                                                x-text="ticket.can_update && !['completed','resolved','cancelled'].includes(String(ticket.status || '').toLowerCase()) ? 'Manage Trip Ticket' : 'View Trip Ticket'"
                                            ></button>
                                            <a x-show="ticket.verification_url" :href="ticket.verification_url" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Verify</a>
                                        </div>
                                    </article>
                                </template>
                                <p x-show="!(selectedOfficialBusinessDetails?.trip_tickets || []).length" class="rounded-xl border border-dashed border-slate-200 px-5 py-8 text-center text-sm text-slate-400">No visible Trip Tickets.</p>
                            </div>

                            <div
                                x-show="Array.isArray(selectedOfficialBusinessDetails?.completion_checklist) && selectedOfficialBusinessDetails.completion_checklist.length"
                                class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                            >
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Completion Requirements</p>
                                <div class="mt-3 space-y-2">
                                    <template x-for="item in (selectedOfficialBusinessDetails?.completion_checklist || [])" :key="item.key">
                                        <div class="flex items-start gap-3 rounded-lg border bg-white px-3 py-3" :class="item.complete ? 'border-emerald-200' : 'border-amber-200'">
                                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] text-white" :class="item.complete ? 'bg-emerald-500' : 'bg-amber-500'">
                                                <i class="fas" :class="item.complete ? 'fa-check' : 'fa-exclamation'"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800" x-text="item.label"></p>
                                                <p class="mt-0.5 text-xs leading-5 text-slate-500" x-text="item.detail"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <template x-for="field in officialBusinessCompletionFields" :key="field.label">
                                    <div :class="field.wide ? 'sm:col-span-2' : ''" class="rounded-xl border border-slate-200 p-4">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="field.label"></p>
                                        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800" x-text="field.value"></p>
                                    </div>
                                </template>
                            </div>

                            <div
                                x-show="officialBusinessCanComplete && selectedOfficialBusinessDetails?.completion_ready"
                                class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"
                            >
                                <p class="text-sm font-semibold text-emerald-900">
                                    Ready for completion
                                </p>

                                <p class="mt-1 text-xs leading-5 text-emerald-700">
                                    The traveler has returned. Complete this O.B. when the record is ready to be closed.
                                </p>

                                <button
                                    type="button"
                                    @click="completeOfficialBusiness(selectedOfficialBusinessDetails)"
                                    class="mt-4 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                >
                                    Complete O.B.
                                </button>
                            </div>

                            <div
                                x-show="
                                    Number(selectedOfficialBusinessDetails?.workflow_version || selectedOfficialBusinessDetails?.workflowVersion || 1) >= 3
                                    && String(selectedOfficialBusinessDetails?.status || '').toLowerCase() === 'for completion'
                                    && !selectedOfficialBusinessDetails?.completion_ready
                                "
                                class="rounded-xl border border-amber-200 bg-amber-50 p-4"
                            >
                                <p class="text-sm font-semibold text-amber-900">Completion requirements are missing</p>
                                <p class="mt-1 text-xs leading-5 text-amber-700">
                                    Resolve every item in the checklist before completing this O.B.
                                </p>
                            </div>
                        </div>

                        <div x-show="officialBusinessDetailTab === 'activity-log'">
                            <p x-show="officialBusinessDetailLoading" class="py-10 text-center text-sm text-slate-400">Loading activity log...</p>
                            <div x-show="!officialBusinessDetailLoading && officialBusinessAuditLog.length" class="space-y-3">
                                <template x-for="entry in officialBusinessAuditLog" :key="entry.id">
                                    <div class="rounded-xl border border-slate-200 px-4 py-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-800" x-text="String(entry.action || 'Activity').replaceAll('_', ' ')"></p>
                                            <time class="text-[10px] text-slate-400" x-text="formatActivityDate(entry.created_at)"></time>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500" x-text="entry.user?.name || entry.user?.email || 'System'"></p>
                                    </div>
                                </template>
                            </div>
                            <p x-show="!officialBusinessDetailLoading && !officialBusinessAuditLog.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No activity log entries.</p>
                        </div>
                    </main>

                    <aside class="border-t border-slate-200 bg-slate-50/80 p-4 lg:border-l lg:border-t-0 sm:p-5">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Approval Status
                                </p>

                                <p
                                    class="mt-2 text-sm font-bold text-slate-900"
                                    x-text="officialBusinessValue(selectedOfficialBusinessDetails?.status, 'Draft')"
                                ></p>

                                <div x-show="officialBusinessCanApproveSelected" class="mt-4 space-y-2">
                                    <button
                                        type="button"
                                        @click="approveOfficialBusiness(selectedOfficialBusinessDetails)"
                                        class="h-10 w-full rounded-lg bg-[#1d54e2] text-xs font-semibold text-white transition hover:bg-[#1541b0]"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        @click="revertOfficialBusiness(selectedOfficialBusinessDetails)"
                                        class="h-10 w-full rounded-lg border border-amber-300 bg-amber-50 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                                    >
                                        Revert / Return for Correction
                                    </button>
                                </div>
                            </div>

                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Travel Movement
                            </p>
                            <div class="mt-3 flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :class="officialBusinessTravelState.dotClass"></span>
                                <p class="text-sm font-bold text-slate-900" x-text="officialBusinessMovementStatus"></p>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500" x-text="'Actual movement is tracked separately from approval status.'"></p>

                            <button
                                x-show="Number(selectedOfficialBusinessDetails?.workflow_version || selectedOfficialBusinessDetails?.workflowVersion || 1) >= 3 && officialBusinessCanReady"
                                type="button"
                                @click="transitionOfficialBusinessStatus($data, selectedOfficialBusinessDetails, 'ready')"
                                class="mt-4 h-10 w-full rounded-lg bg-[#1d54e2] text-xs font-semibold text-white transition hover:bg-[#1541b0]"
                            >
                                Mark Ready for Travel
                            </button>

                            <button
                                x-show="Number(selectedOfficialBusinessDetails?.workflow_version || selectedOfficialBusinessDetails?.workflowVersion || 1) >= 3 ? officialBusinessCanDepart : (String(selectedOfficialBusinessDetails?.status || '').toLowerCase() === 'approved' && officialBusinessCapabilities?.travel_movement)"
                                type="button"
                                @click="departTravelDeskRecord(selectedOfficialBusinessDetails)"
                                class="mt-4 h-10 w-full rounded-lg bg-[#1d54e2] text-xs font-semibold text-white transition hover:bg-[#1541b0]"
                            >
                                Depart
                            </button>

                            <button
                                x-show="officialBusinessCanReturn"
                                type="button"
                                @click="returnTravelDeskRecord(selectedOfficialBusinessDetails)"
                                class="mt-4 h-10 w-full rounded-lg bg-[#1d54e2] text-xs font-semibold text-white transition hover:bg-[#1541b0]"
                            >
                                Return
                            </button>

                            <button
                                x-show="officialBusinessCanStartCompletion"
                                type="button"
                                @click="transitionOfficialBusinessStatus($data, selectedOfficialBusinessDetails, 'for-completion')"
                                class="mt-4 h-10 w-full rounded-lg bg-amber-600 text-xs font-semibold text-white transition hover:bg-amber-700"
                            >
                                Start Completion
                            </button>
                        </div>

                        <div class="mt-4 space-y-2">
                            <button type="button" @click="openOfficialBusinessForm(selectedOfficialBusinessDetails, 'view')" class="h-10 w-full rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                View Official Form
                            </button>
                            <button x-show="officialBusinessCanEditSelected" type="button" @click="openOfficialBusinessForm(selectedOfficialBusinessDetails, 'edit')" class="h-10 w-full rounded-lg border border-blue-200 bg-blue-50 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                Edit Official Form
                            </button>
                            <button type="button" @click="returnToActivities()" class="h-10 w-full rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                                Close
                            </button>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>

<script>
    window.tripTicketManager = function () {
        return {
            isOpen: false,
            loading: false,
            busy: false,
            ticketId: null,
            masterId: null,
            ticket: null,
            stops: [],
            personnel: [],
            employeeOptions: [],
            evidenceItems: [],
            costs: [],
            checklist: [],
            completionReady: false,
            errorMessage: '',
            successMessage: '',

            billingTreatments: [
                'Travel Credit',
                'Travel Credit + Additional Cost',
                'Billable to Client',
                'Included in Retainer',
                'Non-billable',
                'Management Approved',
                'Other',
            ],

            evidenceTypes: [
                'Photo',
                'Government Acknowledgment Slip',
                'Receiving Copy',
                'Official Receipt',
                'Courier Receipt',
                'Filing Confirmation',
                'Email or Screenshot',
                'Meeting Notes',
                'Other Client-Specific Evidence',
            ],

            evidenceForm: {
                evidence_type: 'Photo',
                external_url: '',
                description: '',
            },

            finance: {
                billing_treatment: '',
                travel_credit_units: '',
                travel_credit_value: '',
                evidence_required: '1',
                evidence_requirement_notes: '',
                additional_costs_status: 'Pending',
                notes: '',
            },

            costForm: {
                category: '',
                description: '',
                amount: '',
                incurred_on: '',
                billing_treatment: '',
                evidence_reference: '',
            },

            get editable() {
                const status = String(this.ticket?.status || '').toLowerCase();

                return Boolean(this.ticket?.permissions?.update)
                    && !['completed', 'resolved', 'cancelled'].includes(status);
            },

            get canViewFinance() {
                return Boolean(this.ticket?.permissions?.finance);
            },

            viewMasterOfficialBusiness() {
                const masterId = Number(this.ticket?.official_business_trip_id || this.ticket?.trip?.id || 0);

                if (!masterId) {
                    return;
                }

                this.close();
                this.$dispatch('view-master-official-business', {masterId});
            },

            get visibleChecklist() {
                if (this.canViewFinance) {
                    return this.checklist;
                }

                const financeKeys = [
                    'financial_treatment',
                    'additional_costs',
                ];

                const visible = this.checklist.filter(
                    item => !financeKeys.includes(item.key)
                );

                const financeItems = this.checklist.filter(
                    item => financeKeys.includes(item.key)
                );

                if (financeItems.length) {
                    visible.push({
                        key: 'finance_review',
                        label: 'Financial review',
                        complete: financeItems.every(item => item.complete),
                        detail: financeItems.every(item => item.complete)
                            ? 'Finance/Admin has completed the financial requirements.'
                            : 'Finance/Admin must complete the financial requirements before this Trip Ticket can be finalized.',
                    });
                }

                return visible;
            },

            get canComplete() {
                return this.editable;
            },

            get usesTravelCredit() {
                return ['Travel Credit', 'Travel Credit + Additional Cost']
                    .includes(this.finance.billing_treatment);
            },

            csrf() {
                return document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';
            },

            async api(url, options = {}) {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        ...(options.body instanceof FormData
                            ? {}
                            : {'Content-Type': 'application/json'}),
                        ...(options.headers || {}),
                    },
                    ...options,
                });

                if (response.status === 204) {
                    return null;
                }

                let payload = {};

                try {
                    payload = await response.json();
                } catch (error) {
                    payload = {};
                }

                if (!response.ok) {
                    const validationMessage = payload?.errors
                        ? Object.values(payload.errors).flat().join(' ')
                        : '';

                    const missing = Array.isArray(payload?.missing_requirements)
                        ? payload.missing_requirements
                            .map(item => item.detail || item.label)
                            .filter(Boolean)
                            .join(' ')
                        : '';

                    throw new Error(
                        [payload?.message, validationMessage, missing]
                            .filter(Boolean)
                            .join(' ') ||
                        `Request failed with HTTP ${response.status}.`
                    );
                }

                return payload;
            },

            resetMessages() {
                this.errorMessage = '';
                this.successMessage = '';
            },

            async load(detail) {
                this.ticketId = Number(detail?.ticketId || 0);
                this.masterId = Number(detail?.masterId || 0);

                if (!this.ticketId) {
                    return;
                }

                this.isOpen = true;
                this.loading = true;
                this.resetMessages();

                try {
                    await this.refresh();
                } catch (error) {
                    this.errorMessage = error.message || 'Unable to load Trip Ticket.';
                } finally {
                    this.loading = false;
                }
            },

            close() {
                if (this.busy) {
                    return;
                }

                this.isOpen = false;
                this.resetMessages();
            },

            hydrateTicket(ticket) {
                this.ticket = ticket || null;

                this.stops = Array.isArray(ticket?.stops)
                    ? ticket.stops.map(stop => ({...stop}))
                    : [];

                this.personnel = Array.isArray(ticket?.personnel)
                    ? ticket.personnel.map(person => ({...person}))
                    : [];
                this.employeeOptions = Array.isArray(ticket?.employee_options)
                    ? ticket.employee_options
                    : [];

                this.evidenceItems = Array.isArray(ticket?.evidence)
                    ? ticket.evidence
                    : [];

                this.costs = Array.isArray(ticket?.costs)
                    ? ticket.costs
                    : [];

                const financial = ticket?.financial_treatment || {};

                this.finance = {
                    billing_treatment: financial.billing_treatment || '',
                    travel_credit_units: financial.travel_credit_units ?? '',
                    travel_credit_value: financial.travel_credit_value ?? '',
                    evidence_required:
                        financial.evidence_required === false ||
                        financial.evidence_required === 0 ||
                        financial.evidence_required === '0'
                            ? '0'
                            : '1',
                    evidence_requirement_notes:
                        financial.evidence_requirement_notes || '',
                    additional_costs_status:
                        financial.additional_costs_status || 'Pending',
                    notes: financial.notes || '',
                };
            },

            async refresh() {
                if (!this.ticketId) {
                    return;
                }

                this.resetMessages();

                const [ticketPayload, checklistPayload] = await Promise.all([
                    this.api(`/api/official-business-trip-tickets/${this.ticketId}`),
                    this.api(`/api/official-business-trip-tickets/${this.ticketId}/completion-checklist`),
                ]);

                this.hydrateTicket(ticketPayload?.ticket || null);

                this.checklist = Array.isArray(checklistPayload?.completion_checklist)
                    ? checklistPayload.completion_checklist
                    : [];

                this.completionReady =
                    Boolean(checklistPayload?.completion_ready);
            },

            async run(action, successMessage) {
                this.busy = true;
                this.resetMessages();

                try {
                    await action();
                    await this.refresh();
                    this.successMessage = successMessage;
                } catch (error) {
                    this.errorMessage = error.message || 'The operation could not be completed.';
                } finally {
                    this.busy = false;
                }
            },

            addStop() {
                if (!this.editable) return;

                const nextSequence = this.stops.length
                    ? Math.max(...this.stops.map(stop => Number(stop.sequence) || 0)) + 1
                    : 1;

                this.stops.push({
                    id: null,
                    sequence: nextSequence,
                    destination: '',
                    activity: '',
                    planned_at: null,
                    actual_arrival_at: null,
                    resolved_at: null,
                    resolution_status: 'Resolved',
                });
            },

            removeStop(index) {
                if (!this.editable) return;

                this.stops.splice(index, 1);

                this.stops.forEach((stop, stopIndex) => {
                    stop.sequence = stopIndex + 1;
                });
            },

            addPersonnel() {
                if (!this.editable || !this.employeeOptions.length) return;

                this.personnel.push({
                    id: null,
                    employee_id: '',
                    employee_name_snapshot: '',
                    position_snapshot: '',
                    department_snapshot: '',
                    role: 'Traveler',
                    participation_status: 'Confirmed',
                });
            },

            removePersonnel(index) {
                if (!this.editable) return;

                this.personnel.splice(index, 1);
            },

            employeeLabel(employee) {
                const details = [
                    employee?.employee_code,
                    employee?.position,
                    employee?.department,
                ].filter(Boolean);

                return details.length
                    ? `${employee?.name || 'Employee'} - ${details.join(' / ')}`
                    : (employee?.name || 'Employee');
            },

            availableEmployeesFor(index) {
                const currentId = Number(this.personnel[index]?.employee_id || 0);

                const used = new Set(
                    this.personnel
                        .map((person, personIndex) =>
                            personIndex === index
                                ? null
                                : Number(person.employee_id || 0)
                        )
                        .filter(id => id > 0)
                );

                return this.employeeOptions.filter(employee =>
                    Number(employee.id) === currentId ||
                    !used.has(Number(employee.id))
                );
            },

            applyEmployeeOption(person) {
                const employee = this.employeeOptions.find(
                    option => Number(option.id) === Number(person.employee_id)
                );

                if (!employee) return;

                person.employee_name_snapshot = employee.name || '';
                person.position_snapshot = employee.position || '';
                person.department_snapshot = employee.department || '';
            },
            async saveDetails() {
                await this.run(async () => {
                    const now = new Date().toISOString();

                    const stops = this.stops.map(stop => ({
                        sequence: Number(stop.sequence),
                        destination: stop.destination || '',
                        activity: stop.activity || null,
                        planned_at: stop.planned_at || null,
                        actual_arrival_at: stop.actual_arrival_at || null,
                        resolved_at:
                            ['Resolved', 'Cancelled'].includes(stop.resolution_status)
                                ? (stop.resolved_at || now)
                                : null,
                        resolution_status: stop.resolution_status || 'Planned',
                    }));

                    const personnel = this.personnel.map(person => ({
                        employee_id: Number(person.employee_id),
                        role: person.role || null,
                        participation_status:
                            person.participation_status || 'Assigned',
                    }));

                    await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}`,
                        {
                            method: 'PUT',
                            body: JSON.stringify({stops, personnel}),
                        }
                    );
                }, 'Stops and personnel updated successfully.');
            },

            async addEvidence(fileOverride = null) {
                await this.run(async () => {
                    const file = fileOverride || this.$refs?.evidenceFile?.files?.[0] || null;

                    if (!file && !String(this.evidenceForm.external_url || '').trim()) {
                        throw new Error('Choose a file or enter an external evidence URL.');
                    }

                    const formData = new FormData();
                    formData.append(
                        'evidence_type',
                        this.evidenceForm.evidence_type
                    );

                    if (this.evidenceForm.description) {
                        formData.append(
                            'description',
                            this.evidenceForm.description
                        );
                    }

                    if (file) {
                        formData.append('attachment', file);
                    } else {
                        formData.append(
                            'external_url',
                            this.evidenceForm.external_url
                        );
                    }

                    await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}/evidence`,
                        {
                            method: 'POST',
                            body: formData,
                        }
                    );

                    this.evidenceForm.external_url = '';
                    this.evidenceForm.description = '';

                    if (this.$refs?.evidenceFile) {
                        this.$refs.evidenceFile.value = '';
                    }
                }, 'Evidence added successfully.');
            },

            async removeEvidence(evidence) {
                if (!confirm('Remove this evidence item?')) {
                    return;
                }

                await this.run(async () => {
                    await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}/evidence/${evidence.id}`,
                        {method: 'DELETE'}
                    );
                }, 'Evidence removed.');
            },

            async saveFinance() {
                await this.run(async () => {
                    if (!this.finance.billing_treatment) {
                        throw new Error('Select a billing treatment.');
                    }

                    await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}/financial-treatment`,
                        {
                            method: 'PUT',
                            body: JSON.stringify({
                                billing_treatment:
                                    this.finance.billing_treatment,
                                travel_credit_units:
                                    this.finance.travel_credit_units === ''
                                        ? null
                                        : this.finance.travel_credit_units,
                                travel_credit_value:
                                    this.finance.travel_credit_value === ''
                                        ? null
                                        : this.finance.travel_credit_value,
                                evidence_required:
                                    Number(this.finance.evidence_required),
                                evidence_requirement_notes:
                                    this.finance.evidence_requirement_notes || null,
                                additional_costs_status:
                                    this.finance.additional_costs_status,
                                notes: this.finance.notes || null,
                            }),
                        }
                    );
                }, 'Financial treatment updated successfully.');
            },

            async addCost() {
                await this.run(async () => {
                    if (!this.costForm.description) {
                        throw new Error('Enter a cost description.');
                    }

                    if (
                        this.costForm.amount === '' ||
                        Number(this.costForm.amount) < 0
                    ) {
                        throw new Error('Enter a valid cost amount.');
                    }

                    if (!this.costForm.billing_treatment) {
                        throw new Error('Select the cost billing treatment.');
                    }

                    await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}/costs`,
                        {
                            method: 'POST',
                            body: JSON.stringify({
                                category: this.costForm.category || null,
                                description: this.costForm.description,
                                amount: this.costForm.amount,
                                incurred_on:
                                    this.costForm.incurred_on || null,
                                billing_treatment:
                                    this.costForm.billing_treatment,
                                evidence_reference:
                                    this.costForm.evidence_reference || null,
                            }),
                        }
                    );

                    this.costForm = {
                        category: '',
                        description: '',
                        amount: '',
                        incurred_on: '',
                        billing_treatment: '',
                        evidence_reference: '',
                    };
                }, 'Additional cost recorded successfully.');
            },

            async removeCost(cost) {
                if (!confirm('Remove this cost item?')) {
                    return;
                }

                await this.run(async () => {
                    await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}/costs/${cost.id}`,
                        {method: 'DELETE'}
                    );
                }, 'Cost removed.');
            },

            async completeTicket() {
                if (!this.completionReady) {
                    this.errorMessage =
                        'Complete all Trip Ticket requirements first.';
                    return;
                }

                if (!confirm('Complete this client Trip Ticket? This will lock it from further changes.')) {
                    return;
                }

                this.busy = true;
                this.resetMessages();

                try {
                    const payload = await this.api(
                        `/api/official-business-trip-tickets/${this.ticketId}/complete`,
                        {method: 'POST'}
                    );

                    this.hydrateTicket(payload?.ticket || this.ticket);
                    this.checklist =
                        payload?.completion_checklist || [];
                    this.completionReady = true;
                    this.successMessage =
                        payload?.message || 'Trip Ticket completed successfully.';

                    setTimeout(() => {
                        window.location.reload();
                    }, 900);
                } catch (error) {
                    this.errorMessage =
                        error.message || 'Unable to complete Trip Ticket.';
                } finally {
                    this.busy = false;
                }
            },
        };
    };
</script>
<script>
    window.transitionOfficialBusinessStatus = async function (component, record, action) {
        if (!record?.id) return;

        try {
            const response = await fetch(`/api/official-business/${record.id}/${action}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || `Unable to update Official Business (${response.status}).`);

            await component.fetchOfficialBusinessRecords();
            component.selectedOfficialBusinessDetails = component.officialBusinessRecords.find(
                item => Number(item.id) === Number(record.id)
            ) || component.selectedOfficialBusinessDetails;
        } catch (error) {
            alert(error.message || 'Unable to update Official Business.');
        }
    };
</script>

{{-- selectedActivityDetails --}}
<div
        x-show="selectedActivityDetails"
        x-cloak

        class="w-full min-w-0"
    >
        <section
            @click.stop
            class="flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" role="region"
            aria-labelledby="activity-detail-title"
        >
            <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <div class="mb-1 flex items-center gap-2 text-xs font-semibold text-blue-600">
                        <i :class="activityIcon(selectedActivityDetails?.type)"></i>
                        <span x-text="formatActivityType(selectedActivityDetails?.type)"></span>
                    </div>
                    <h2 id="activity-detail-title" class="truncate text-lg font-bold text-slate-900" x-text="selectedActivityDetails?.title || 'Activity details'"></h2>
                </div>
                <button type="button" @click="returnToActivities()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close activity details">
                    <i class="fas fa-times text-sm"></i>
                </button>
                <button x-show="selectedActivityDetails?.type === 'email'" type="button" @click="openEmailReply()" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Reply</button>
            </header>

            <div class="overflow-y-auto px-5 py-5 sm:px-6">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <template x-for="row in activityDetailRows" :key="row.label">
                        <div :class="row.wide ? 'sm:col-span-2' : ''">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="row.label"></dt>
                            <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800" x-text="row.value"></dd>
                        </div>
                    </template>
                </dl>

                <div x-show="selectedActivityDetails?.attachments?.length" class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Attachments</h3>
                    <div class="mt-3 space-y-2">
                        <template x-for="(attachment, index) in selectedActivityDetails?.attachments || []" :key="attachment.id || attachment.path || index">
                            <a
                                :href="attachment.url || attachment.path || '#'"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-blue-600 transition hover:bg-blue-50"
                            >
                                <i class="far fa-file text-slate-400"></i>
                                <span class="truncate" x-text="attachment.original_name || attachment.name || attachment.filename || attachment.path || 'Attachment'"></span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </div>

{{-- selectedTaskDetails && selectedTaskType === 'task' --}}
<div
        x-show="selectedTaskDetails && selectedTaskType === 'task'"
        x-cloak

        class="w-full min-w-0"
    >
        <div class="mx-auto w-full">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 px-1">
                <p class="min-w-0 truncate text-[10px] font-medium text-slate-400" x-text="selectedTaskDetails?.name || 'Task details'"></p>
                <button type="button" @click="returnToActivities()" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <i class="fas fa-arrow-left text-[10px]"></i>
                    <span x-text="activityDetailBackLabel">Back to Activities</span>
                </button>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" role="region" aria-labelledby="task-workspace-title">
                <header class="border-b border-slate-200 px-4 py-5 sm:px-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <h2 id="task-workspace-title" class="break-words text-xl font-bold text-slate-900" x-text="selectedTaskDetails?.name || 'Task'">Task</h2>
                            <p class="mt-1 text-xs font-medium text-slate-500" x-text="selectedTaskDetails?.parent_task_id ? 'Subtask' : 'Main Task'"></p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-[10px] font-bold uppercase text-slate-600" x-text="selectedTaskDetails?.status || 'Open'"></span>
                            <span class="inline-flex h-9 min-w-[82px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 font-mono text-xs font-bold text-slate-800" x-text="formatTaskSeconds(taskWorkspaceElapsedSeconds)">00:00:00</span>
                            <button x-show="!taskWorkspaceActiveTimer" type="button" @click="runTaskTimerAction('start')" :disabled="taskWorkspaceBusy" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[10px] font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50">
                                <i class="fas fa-play text-[8px]"></i> Start
                            </button>
                            <button x-show="taskWorkspaceActiveTimer && !taskWorkspaceActiveTimer.paused_at" type="button" @click="runTaskTimerAction('pause')" :disabled="taskWorkspaceBusy" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 text-[10px] font-bold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50">
                                <i class="fas fa-pause text-[8px]"></i> Pause
                            </button>
                            <button x-show="taskWorkspaceActiveTimer?.paused_at" type="button" @click="runTaskTimerAction('resume')" :disabled="taskWorkspaceBusy" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[10px] font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50">
                                <i class="fas fa-play text-[8px]"></i> Resume
                            </button>
                            <button x-show="taskWorkspaceActiveTimer" type="button" @click="runTaskTimerAction('stop')" :disabled="taskWorkspaceBusy" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[10px] font-bold text-slate-600 transition hover:bg-slate-100 disabled:opacity-50">
                                <i class="fas fa-stop text-[8px]"></i> Stop
                            </button>
                            <button x-show="selectedTaskDetails?.status !== 'Completed'" type="button" @click="completeTaskFromWorkspace()" :disabled="taskWorkspaceBusy" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 text-[10px] font-bold text-blue-700 transition hover:bg-blue-100 disabled:opacity-50">
                                <i class="fas fa-check text-[8px]"></i> Done
                            </button>
                            <button type="button" @click="duplicateTaskFromWorkspace()" :disabled="taskWorkspaceBusy" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">Duplicate / Copy Client</button>
                            <button type="button" @click="saveTaskAsTemplateFromWorkspace()" :disabled="taskWorkspaceBusy" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">Save as Template</button>
                            <button type="button" @click="editTaskFromWorkspace()" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-700 transition hover:bg-slate-50">Edit</button>
                            <button type="button" @click="addSubtaskFromWorkspace()" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-700 transition hover:bg-slate-50"><i class="fas fa-plus text-[8px]"></i> Add Subtask</button>
                            <button type="button" @click="returnToActivities()" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:bg-slate-50 hover:text-slate-700" aria-label="Close task workspace"><i class="fas fa-times text-xs"></i></button>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-7">
                        <template x-for="card in taskWorkspaceSummaryCards" :key="card.label">
                            <div class="min-w-0 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-3">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400" x-text="card.label"></p>
                                <p class="mt-1.5 truncate text-xs font-bold text-slate-800" :title="card.value" x-text="card.value"></p>
                                <div x-show="card.progress !== null" class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-blue-500" :style="`width: ${card.progress || 0}%`"></div>
                                </div>
                                <p x-show="card.note" class="mt-1 text-[9px] text-slate-400" x-text="card.note"></p>
                            </div>
                        </template>
                    </div>
                </header>

                <nav class="overflow-x-auto border-b border-slate-200 px-4 sm:px-6" aria-label="Task detail sections">
                    <div class="flex min-w-max gap-1">
                        <template x-for="tab in taskWorkspaceTabs" :key="tab.key">
                            <button type="button" @click="taskWorkspaceTab = tab.key" :class="taskWorkspaceTab === tab.key ? 'border-blue-600 text-blue-700' : 'border-transparent text-black hover:text-black'" class="border-b-2 px-4 py-3 text-sm font-semibold transition" x-text="tab.label"></button>
                        </template>
                    </div>
                </nav>

                <div class="min-h-[290px] p-4 sm:p-6">
                    <div x-show="taskWorkspaceLoading" class="py-12 text-center text-sm text-slate-400">Loading task workspace...</div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'overview'" class="space-y-4">
                        <section class="overflow-hidden rounded-xl border border-slate-200">
                            <h3 class="border-b border-slate-200 bg-slate-50/60 px-4 py-3 text-xs font-bold text-slate-800">Task Information</h3>
                            <div class="grid grid-cols-1 gap-3 p-3 md:grid-cols-3">
                                <template x-for="field in taskWorkspaceInformationFields" :key="field.label">
                                    <div class="rounded-lg border border-slate-200 px-3 py-3">
                                        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400" x-text="field.label"></p>
                                        <p class="mt-1.5 text-xs font-medium text-slate-800" x-text="field.value"></p>
                                    </div>
                                </template>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-xl border border-slate-200">
                            <h3 class="border-b border-slate-200 bg-slate-50/60 px-4 py-3 text-xs font-bold text-slate-800">Description / Instructions</h3>
                            <p class="min-h-[54px] whitespace-pre-wrap px-4 py-4 text-xs leading-6 text-slate-700" x-text="selectedTaskDetails?.description || 'No description provided.'"></p>
                        </section>
                    </div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'subtasks'">
                        <div x-show="taskWorkspaceSubtasks.length" class="space-y-2">
                            <template x-for="subtask in taskWorkspaceSubtasks" :key="subtask.id">
                                <button type="button" @click="openTaskWorkspace(subtask)" class="flex w-full items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:border-blue-200 hover:bg-blue-50/30">
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800" x-text="subtask.name"></p><p class="mt-1 text-[10px] text-slate-400" x-text="formatTaskDate(subtask.due_date)"></p></div>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-bold text-slate-600" x-text="subtask.status || 'Open'"></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="!taskWorkspaceSubtasks.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No subtasks yet.</p>
                    </div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'related'">
                        <div x-show="taskWorkspaceRelated.length" class="space-y-2">
                            <template x-for="activity in taskWorkspaceRelated" :key="activity.key">
                                <div class="rounded-xl border border-slate-200 px-4 py-3"><p class="text-[10px] font-bold uppercase text-blue-600" x-text="formatActivityType(String(activity.type || '').toLowerCase())"></p><p class="mt-1 text-sm font-semibold text-slate-800" x-text="activity.title"></p></div>
                            </template>
                        </div>
                        <p x-show="!taskWorkspaceRelated.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No related activities.</p>
                    </div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'files'">
                        <div x-show="taskWorkspaceFiles.length" class="space-y-2">
                            <template x-for="(file, index) in taskWorkspaceFiles" :key="file.id || file.path || index">
                                <a :href="file.url || file.path" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50"><i class="far fa-file text-slate-400"></i><span class="truncate" x-text="file.original_name || file.name || file.filename || 'Attachment'"></span></a>
                            </template>
                        </div>
                        <p x-show="!taskWorkspaceFiles.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No files attached.</p>
                    </div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'time'" class="space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <template x-for="field in taskWorkspaceTimeFields" :key="field.label"><div class="rounded-xl border border-slate-200 p-4"><p class="text-[9px] font-bold uppercase tracking-wider text-slate-400" x-text="field.label"></p><p class="mt-2 text-lg font-bold text-slate-900" x-text="field.value"></p></div></template>
                        </div>
                        <div x-show="selectedTaskDetails?.timers?.length" class="space-y-2">
                            <template x-for="timer in selectedTaskDetails?.timers || []" :key="timer.id"><div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3"><div><p class="text-xs font-semibold text-slate-800" x-text="timer.user?.name || timer.user?.email || 'Task timer'"></p><p class="mt-1 text-[10px] text-slate-400" x-text="formatTaskDateTime(timer.started_at)"></p></div><span class="text-xs font-bold text-slate-700" x-text="formatTaskSeconds(taskTimerDurationSeconds(timer))"></span></div></template>
                        </div>
                    </div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'updates'">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><textarea x-model="newNoteContent" rows="3" class="w-full resize-none bg-transparent text-sm outline-none" placeholder="Add an update..."></textarea><div class="mt-2 flex justify-end"><button type="button" @click="saveNote(selectedTaskDetails, 'task')" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700" x-text="editingNote ? 'Update' : 'Post Update'"></button></div></div>
                        <div class="mt-4 space-y-3"><template x-for="note in selectedTaskDetails?.notes || []" :key="note.id"><div class="rounded-xl border border-slate-200 px-4 py-3"><div class="flex justify-between gap-3"><p class="text-xs font-bold text-slate-800" x-text="note.owner || 'Update'"></p><time class="text-[10px] text-slate-400" x-text="formatTaskDateTime(note.created_at)"></time></div><p class="mt-2 whitespace-pre-wrap text-sm text-slate-600" x-text="note.content"></p></div></template><p x-show="!(selectedTaskDetails?.notes || []).length" class="py-8 text-center text-sm text-slate-400">No updates yet.</p></div>
                    </div>

                    <div x-show="!taskWorkspaceLoading && taskWorkspaceTab === 'activity-log'">
                        <div x-show="taskWorkspaceAudit.length" class="space-y-3"><template x-for="entry in taskWorkspaceAudit" :key="entry.id"><div class="rounded-xl border border-slate-200 px-4 py-3"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold capitalize text-slate-800" x-text="String(entry.action || 'Activity').replaceAll('_', ' ')"></p><time class="text-[10px] text-slate-400" x-text="formatTaskDateTime(entry.created_at)"></time></div><p class="mt-1 text-xs text-slate-500" x-text="entry.user?.name || entry.user?.email || 'System'"></p></div></template></div>
                        <p x-show="!taskWorkspaceAudit.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400">No activity log entries.</p>
                    </div>
                </div>

                <footer class="flex justify-end border-t border-slate-200 px-4 py-4 sm:px-6">
                    <button type="button" @click="returnToActivities()" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Close</button>
                </footer>
            </section>
        </div>
    </div>

{{-- selectedTaskDetails && selectedTaskType !== 'task' --}}
<div x-show="selectedTaskDetails && selectedTaskType !== 'task'"
         x-data="{ taskDetailsTab: 'information' }"
         style="display: none;"
         class="w-full min-w-0">

        <div @click.stop class="bg-white shadow-sm w-full flex flex-col overflow-hidden rounded-xl border border-gray-200">

            <!-- Header -->
            <div class="px-5 py-4 flex items-center justify-between shrink-0 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-semibold text-gray-800" x-text="(selectedTaskType ? selectedTaskType.charAt(0).toUpperCase() + selectedTaskType.slice(1) : 'Task') + ' Details'">Task Details</h3>
                    <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5" x-text="selectedTaskDetails?.owner || selectedTaskDetails?.host || 'John Kelly'"></div>
                </div>
                <button @click="returnToActivities()" class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">&times;</button>
            </div>

        <!-- Panel Body -->
        <div class="flex flex-col flex-1 bg-white">

            <!-- Tabs -->
            <div class="flex px-6 pt-2 border-b border-gray-100">
                <button @click="taskDetailsTab = 'information'"
                        :class="taskDetailsTab === 'information' ? 'bg-[#1d54e2] text-white' : 'text-gray-500 hover:bg-gray-50'"
                        class="px-6 py-2 rounded-t-lg text-sm font-semibold transition">
                    Information
                </button>
                <button @click="taskDetailsTab = 'notes'"
                        :class="taskDetailsTab === 'notes' ? 'bg-[#1d54e2] text-white font-semibold' : 'text-gray-500 hover:bg-gray-50 font-medium'"
                        class="px-6 py-2 rounded-t-lg text-sm transition">
                    Notes
                </button>
            </div>

            <!-- Content Area -->
            <div class="p-6 overflow-y-auto flex-1">

                <!-- Information Tab Content -->
                <div x-show="taskDetailsTab === 'information'">
                    <h4 class="font-bold text-gray-900 text-sm mb-4" x-text="(selectedTaskType ? selectedTaskType.charAt(0).toUpperCase() + selectedTaskType.slice(1) : 'Task') + ' Details'">Task Details</h4>

                <div class="space-y-4">
                    <!-- Detail Row -->
                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Date/Time</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                            <span x-text="selectedTaskDetails?.start_time || selectedTaskDetails?.due_date || selectedTaskDetails?.from || '-'"></span>
                        </div>
                    </div>

                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Priority</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                            <span x-text="selectedTaskDetails?.priority || '-'"></span>
                        </div>
                    </div>

                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Status</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                            <span x-text="selectedTaskDetails?.status || selectedTaskDetails?.type || '-'"></span>
                        </div>
                    </div>

                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Reminder</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                            <span>-</span>
                        </div>
                    </div>

                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Description</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition min-h-[20px]">
                            <span x-text="selectedTaskDetails?.description || selectedTaskDetails?.purpose || ''"></span>
                        </div>
                    </div>

                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Related To</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                            <span x-text="selectedTaskDetails?.related_to || '-'"></span>
                        </div>
                    </div>

                    <div class="flex group">
                        <div class="w-32 text-sm text-gray-500">Last Modified</div>
                        <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                            <span>Just now</span>
                        </div>
                    </div>
                </div>



                <!-- Call Recording Section -->
                <div class="mt-6 pt-4 border-t border-gray-100" x-show="selectedTaskType === 'call'">
                    <h4 class="font-bold text-gray-900 text-sm mb-3">Call Recording</h4>

                    <!-- Persistent Custom Audio Player -->
                    <div x-show="selectedTaskDetails?.audio_path" class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4 shadow-sm flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i class="fas fa-microphone text-xs"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-gray-900 line-clamp-1" x-text="selectedTaskDetails?.audio_path ? selectedTaskDetails.audio_path.split('/').pop() : 'Recording'"></span>
                                    <span class="text-[10px] text-green-600 font-semibold uppercase tracking-wide">Recording Available</span>
                                </div>
                            </div>
                            <div class="flex gap-1">
                                <a :href="selectedTaskDetails?.audio_path" download class="w-8 h-8 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-blue-600 hover:border-blue-200 flex items-center justify-center transition shadow-sm" title="Download">
                                    <i class="fas fa-download text-xs"></i>
                                </a>
                                <button @click="deleteCallAudio(selectedTaskDetails?.id)" class="w-8 h-8 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-red-500 hover:border-red-200 flex items-center justify-center transition shadow-sm" title="Delete">
                                    <i class="far fa-trash-alt text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="flex items-center gap-3">
                            <button @click="toggleCallAudio()"
                                    class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition shrink-0 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">
                                <i :class="isCallAudioPlaying ? 'fas fa-pause text-xs' : 'fas fa-play text-xs ml-0.5'"></i>
                            </button>

                            <!-- Progress -->
                            <div class="flex flex-col flex-1 gap-1.5">
                                <div class="w-full h-2 bg-gray-200 rounded-full relative cursor-pointer overflow-hidden" @click="seekCallAudio($event)">
                                    <div class="absolute top-0 left-0 h-full bg-blue-500 rounded-full"
                                         :style="`width: ${callAudioDuration > 0 ? (callAudioCurrentTime / callAudioDuration) * 100 : 0}%`"></div>
                                </div>
                                <div class="flex justify-between items-center text-[10px] text-gray-500 font-mono">
                                    <span x-text="formatTimeGeneral(callAudioCurrentTime)">00:00</span>
                                    <span x-text="formatTimeGeneral(callAudioDuration)">00:00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Recording State -->
                    <div x-show="!selectedTaskDetails?.audio_path" class="bg-gray-50 border border-gray-200 border-dashed rounded-xl p-5 text-center mb-4">
                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-microphone-slash text-gray-400 text-sm"></i>
                        </div>
                        <p class="text-xs text-gray-500 font-medium">No recording available</p>
                    </div>

                    <!-- Hidden Audio Element -->
                    <audio x-ref="callAudioPlayer"
                           :src="selectedTaskDetails?.audio_url"
                           @timeupdate="updateCallAudioTime()"
                           @loadedmetadata="updateCallAudioTime()"
                           @ended="isCallAudioPlaying = false"
                           class="hidden"></audio>

                    <!-- Upload UI -->
                    <div class="pt-2">
                        <button @click="document.getElementById('audioUploadInput').click()"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-700 rounded-xl text-sm font-semibold hover:bg-white hover:border-[#1d54e2] hover:text-[#1d54e2] transition-all shadow-sm group"
                                :class="isUploadingAudio ? 'opacity-50 cursor-wait pointer-events-none' : ''">
                            <template x-if="!isUploadingAudio">
                                <i class="fas fa-upload text-xs group-hover:-translate-y-0.5 transition-transform"></i>
                            </template>
                            <template x-if="isUploadingAudio">
                                <i class="fas fa-spinner fa-spin text-xs"></i>
                            </template>
                            <span x-text="isUploadingAudio ? 'Uploading...' : (selectedTaskDetails?.audio_path ? 'Replace Recording' : 'Upload Call Recording')"></span>
                        </button>
                    </div>
                </div>

                <!-- Hidden Input -->
                <input type="file"
                       id="audioUploadInput"
                       x-ref="audioUploadInput"
                       class="hidden"
                       accept="audio/*,video/mp4"
                       @change="uploadCallAudio($event, selectedTaskDetails?.id)">
                </div>

                <!-- Notes Tab Content -->
                <div x-show="taskDetailsTab === 'notes'" style="display: none;">
                    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-4">
                        <textarea x-model="newNoteContent" class="w-full bg-transparent resize-none outline-none text-sm text-gray-900 placeholder-gray-400" rows="3" placeholder="Add a note..."></textarea>
                        <div class="flex justify-end mt-2">
                            <button @click="saveNote(selectedTaskDetails, selectedTaskType)" class="px-4 py-1.5 bg-[#1d54e2] text-white text-xs font-semibold rounded-lg hover:bg-[#1541b0] transition" x-text="editingNote ? 'Update Note' : 'Save Note'"></button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <template x-for="note in selectedTaskDetails?.notes || []" :key="note.id">
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-[#1d54e2] text-white flex items-center justify-center text-xs font-bold shrink-0" x-text="(note.owner || 'JK').split(' ').map(n => n[0]).join('').toUpperCase()">
                                </div>
                                <div class="flex-1 text-sm bg-white p-3 rounded-lg border border-gray-100 shadow-sm relative group">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-semibold text-gray-900" x-text="note.owner"></span>
                                        <div class="flex items-center gap-2">
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                                <i @click="editNote(note)" class="fas fa-pencil-alt text-[10px] text-gray-400 hover:text-blue-600 cursor-pointer"></i>
                                                <i @click="deleteNote(note.id, selectedTaskDetails)" class="far fa-trash-alt text-[10px] text-gray-400 hover:text-red-500 cursor-pointer"></i>
                                            </div>
                                            <span class="text-xs text-gray-500" x-text="new Date(note.created_at).toLocaleDateString() === new Date().toLocaleDateString() ? 'Today' : new Date(note.created_at).toLocaleDateString()"></span>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 whitespace-pre-line" x-text="note.content"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </div>

    </div>

{{-- selectedCoreActivityDetails --}}
<div
        x-show="selectedCoreActivityDetails"
        x-cloak

        class="w-full min-w-0"
    >
        <div
            @click.stop
            class="mx-auto flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-sm lg:min-h-0"
        >
            <section class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6 lg:px-8 lg:py-5">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-medium text-slate-400">
                            <span class="text-blue-600" x-text="coreActivityTypeLabel"></span>
                            <span aria-hidden="true">-</span>
                            <span class="truncate" x-text="coreActivityTitle"></span>
                        </div>
                        <h2 class="mt-2 break-words text-xl font-bold tracking-tight text-slate-900 sm:text-2xl" x-text="coreActivityTitle"></h2>
                        <p class="mt-1 text-xs text-slate-500" x-text="coreActivitySubtitle"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2">
                        <span class="inline-flex h-8 items-center rounded-lg px-2.5 text-[9px] font-bold uppercase tracking-wide" :class="activityStatusClass(coreActivityStatus)" x-text="coreActivityStatus"></span>
                        <span class="min-w-[70px] text-center font-mono text-[11px] font-bold text-slate-800" x-text="coreActivityTimerDisplay"></span>
                        <button type="button" disabled title="A persisted timer is not available for this activity type." class="inline-flex h-8 cursor-not-allowed items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-[9px] font-semibold text-emerald-700 opacity-55"><i class="fas fa-play text-[8px]"></i>Start</button>
                        <button type="button" disabled title="A persisted timer is not available for this activity type." class="inline-flex h-8 cursor-not-allowed items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 text-[9px] font-semibold text-amber-700 opacity-55"><i class="fas fa-pause text-[8px]"></i>Pause</button>
                        <button type="button" disabled title="A persisted timer is not available for this activity type." class="inline-flex h-8 cursor-not-allowed items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 text-[9px] font-semibold text-red-600 opacity-55"><i class="fas fa-stop text-[8px]"></i>Stop</button>
                        <button type="button" @click="completeCoreActivity()" :disabled="coreActivityIsCompleted" class="inline-flex h-8 items-center gap-1 rounded-lg bg-blue-600 px-3 text-[9px] font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"><i class="fas fa-check text-[8px]"></i>Complete</button>
                        <button type="button" @click="openCoreActivityEdit(false)" class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[9px] font-semibold text-slate-700 transition hover:border-blue-200 hover:text-blue-600">Edit</button>
                        <button type="button" @click="returnToActivities()" class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[9px] font-semibold text-slate-700 transition hover:bg-slate-100">Close</button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <template x-for="card in coreActivitySummaryCards" :key="card.label">
                        <div class="min-h-[68px] rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <p class="text-[8px] font-bold uppercase tracking-[0.08em] text-slate-400" x-text="card.label"></p>
                            <p class="mt-2 break-words text-[10px] font-bold text-slate-800" x-text="card.value"></p>
                        </div>
                    </template>
                </div>
            </section>

            <section class="flex min-h-0 flex-1 flex-col bg-white">
                <div class="overflow-x-auto border-b border-slate-200 px-3 sm:px-6">
                    <nav class="flex min-w-max gap-1" aria-label="Activity detail sections">
                        <template x-for="tab in coreActivityTabs" :key="tab.key">
                            <button
                                type="button"
                                @click="coreActivityDetailTab = tab.key"
                                class="border-b-2 px-3 py-3 text-[9px] font-semibold transition"
                                :class="coreActivityDetailTab === tab.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                                x-text="tab.label"
                            ></button>
                        </template>
                    </nav>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50/70 p-3 sm:p-5 lg:p-6">
                    <div x-show="coreActivityDetailTab === 'overview'" class="rounded-xl border border-slate-200 bg-white">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                            <h3 class="text-[11px] font-bold text-slate-800" x-text="coreActivityInformationTitle"></h3>
                            <span x-show="selectedCoreActivityType === 'meeting' && coreMeetingHasCalendarSync" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[8px] font-semibold text-emerald-700">
                                <i class="fas fa-check-circle"></i>
                                Calendar synced
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 lg:grid-cols-3">
                            <template x-for="field in coreActivityOverviewFields" :key="field.label">
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-3" :class="field.wide ? 'sm:col-span-2 lg:col-span-3' : ''">
                                    <p class="text-[8px] font-bold uppercase tracking-[0.08em] text-slate-400" x-text="field.label"></p>
                                    <p class="mt-1.5 whitespace-pre-line break-words text-[10px] font-medium leading-5 text-slate-800" x-text="field.value"></p>
                                </div>
                            </template>
                        </div>

                        <div x-show="selectedCoreActivityType === 'event' && coreActivityIsTraining" class="mx-3 mb-3 rounded-xl border border-blue-200 bg-blue-50/50 p-3">
                            <h4 class="mb-3 text-[10px] font-bold text-blue-900">Training Information</h4>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="field in coreEventTrainingFields" :key="field.label">
                                    <div class="rounded-lg border border-blue-100 bg-white/80 px-3 py-3" :class="field.wide ? 'sm:col-span-2 lg:col-span-3' : ''">
                                        <p class="text-[8px] font-bold uppercase tracking-[0.08em] text-slate-400" x-text="field.label"></p>
                                        <p class="mt-1.5 whitespace-pre-line break-words text-[10px] font-medium text-slate-800" x-text="field.value"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div x-show="selectedCoreActivityType === 'meeting' && coreMeetingUrl" class="mx-3 mb-3 flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[8px] font-bold uppercase tracking-[0.08em] text-slate-400">Google Meet</p>
                                <p class="mt-1 truncate text-[11px] font-semibold text-blue-700" x-text="coreMeetingUrl"></p>
                            </div>
                            <a :href="coreMeetingUrl" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 px-4 text-[9px] font-semibold text-white transition hover:bg-blue-700">Join Google Meet</a>
                        </div>
                    </div>

                    <div x-show="selectedCoreActivityType === 'meeting' && meetingWorkspaceLoading" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400">Loading meeting workspace...</div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'participants'" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <section class="rounded-xl border border-slate-200 bg-white p-4"><h3 class="text-xs font-bold text-slate-800">Internal Participants</h3><div class="mt-3 space-y-2"><template x-for="person in meetingWorkspace.internal_participants || []" :key="person.id"><div class="rounded-lg border border-slate-100 px-3 py-2"><p class="text-xs font-semibold text-slate-800" x-text="person.name"></p><p class="text-[10px] text-slate-400" x-text="person.email"></p></div></template><p x-show="!(meetingWorkspace.internal_participants || []).length" class="text-xs text-slate-400">No internal participants recorded.</p></div></section>
                        <section class="rounded-xl border border-slate-200 bg-white p-4"><h3 class="text-xs font-bold text-slate-800">External Attendees</h3><div class="mt-3 space-y-2"><template x-for="(person, index) in meetingWorkspace.external_attendees || []" :key="person.email || index"><div class="rounded-lg border border-slate-100 px-3 py-2"><p class="text-xs font-semibold text-slate-800" x-text="person.name || person.email || person"></p><p class="text-[10px] text-slate-400" x-text="person.organization || person.email || ''"></p></div></template><p x-show="!(meetingWorkspace.external_attendees || []).length" class="text-xs text-slate-400">No external attendees recorded.</p></div></section>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Recurrence</label>
                        <select x-model="newTask.recurrence" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100">
                            <option value="">Does not repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Reminder</label>
                        <select x-model="newTask.reminderType" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100">
                            <option value="">No reminder</option>
                            <option value="at_due">At due time</option>
                            <option value="before_due">Before due</option>
                        </select>
                        <input x-show="newTask.reminderType === 'before_due'" type="number" min="0" x-model.number="newTask.reminderValue" placeholder="Minutes before due" class="mt-2 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 outline-none focus:border-[#1d54e2]">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Blocked by</label>
                        <select multiple x-model="newTask.dependencyTaskIds" class="min-h-20 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                            <template x-for="candidate in tasks.filter(item => Number(item.id) !== Number(newTask.id))" :key="'dependency-' + candidate.id">
                                <option :value="String(candidate.id)" x-text="candidate.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-[10px] text-slate-400">Select tasks that must finish first. Cycles are rejected.</p>
                    </div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'minutes'" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-xs font-bold text-slate-800">Meeting Minutes</h3><p class="mt-1 text-[10px] text-slate-400" x-text="meetingWorkspace.finalized_minutes_at ? `Finalized ${formatTaskDateTime(meetingWorkspace.finalized_minutes_at)}` : 'Editable draft with version history'"></p></div><div class="flex flex-wrap gap-2"><button type="button" @click="printMeetingMinutes()" class="h-8 rounded-lg border border-slate-200 px-3 text-[10px] font-semibold text-slate-600">Print / PDF</button><button type="button" @click="downloadMeetingMinutesWord()" class="h-8 rounded-lg border border-slate-200 px-3 text-[10px] font-semibold text-slate-600">Word</button><button type="button" @click="saveMeetingWorkspaceSection('minutes')" :disabled="meetingWorkspaceBusy" class="h-8 rounded-lg bg-blue-600 px-3 text-[10px] font-semibold text-white disabled:opacity-50">Save Draft</button><button type="button" @click="saveMeetingWorkspaceSection('minutes', true)" :disabled="meetingWorkspaceBusy || meetingWorkspace.finalized_minutes_at" class="h-8 rounded-lg bg-emerald-600 px-3 text-[10px] font-semibold text-white disabled:opacity-50">Finalize</button></div></div>
                        <textarea x-model="meetingMinutesText" rows="14" class="mt-4 w-full resize-y rounded-xl border border-slate-200 px-4 py-3 text-sm leading-6 outline-none focus:border-blue-400" placeholder="Add headings, decisions, discussion notes, and next steps..."></textarea>
                        <div class="mt-4"><h4 class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Version History</h4><div class="mt-2 flex flex-wrap gap-2"><template x-for="version in meetingWorkspace.minutes_versions || []" :key="version.id"><button type="button" @click="meetingMinutesText = workspaceText(version.content)" class="rounded-lg border border-slate-200 px-3 py-2 text-[10px] text-slate-600" x-text="`v${version.version_number} - ${version.status}`"></button></template></div></div>
                    </div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'transcript'" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5"><div class="flex items-center justify-between gap-3"><div><h3 class="text-xs font-bold text-slate-800">Transcript</h3><p class="mt-1 text-[10px] text-slate-400">Paste or edit the supported transcription output.</p></div><button type="button" @click="saveMeetingWorkspaceSection('transcript')" :disabled="meetingWorkspaceBusy" class="h-8 rounded-lg bg-blue-600 px-3 text-[10px] font-semibold text-white disabled:opacity-50">Save Transcript</button></div><textarea x-model="meetingTranscriptText" rows="16" class="mt-4 w-full resize-y rounded-xl border border-slate-200 px-4 py-3 text-sm leading-6 outline-none focus:border-blue-400" placeholder="Transcript text..."></textarea></div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && ['recordings','files'].includes(coreActivityDetailTab)" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5"><h3 class="text-xs font-bold text-slate-800" x-text="coreActivityDetailTab === 'recordings' ? 'Recordings' : 'Files'"></h3><p class="mt-1 text-[10px] text-slate-400">Original uploaded sources remain attached to this meeting record.</p><pre class="mt-4 max-h-96 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-50 p-4 text-xs text-slate-600" x-text="workspaceText(coreActivityDetailTab === 'recordings' ? meetingWorkspace.recording_metadata : meetingWorkspace.files_metadata) || 'No items recorded.'"></pre></div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'action-items'" class="space-y-4"><section class="rounded-xl border border-slate-200 bg-white p-4"><h3 class="text-xs font-bold text-slate-800">New Action Item</h3><div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_auto]"><input x-model="newMeetingActionItem.description" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Action item"><input x-model="newMeetingActionItem.due_date" type="date" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><button type="button" @click="addMeetingActionItem()" :disabled="meetingWorkspaceBusy || !newMeetingActionItem.description.trim()" class="h-10 rounded-lg bg-blue-600 px-4 text-xs font-semibold text-white disabled:opacity-50">Add</button></div></section><template x-for="item in meetingWorkspace.action_items || []" :key="item.id"><section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-semibold text-slate-800" x-text="item.description"></p><p class="mt-1 text-[10px] text-slate-400" x-text="item.task ? `Linked Task: ${item.task.name}` : (item.due_date || 'No due date')"></p></div><button type="button" @click="convertMeetingActionItem(item)" :disabled="meetingWorkspaceBusy || item.task_id" class="h-9 shrink-0 rounded-lg border border-blue-200 bg-blue-50 px-4 text-[10px] font-semibold text-blue-700 disabled:opacity-50" x-text="item.task_id ? 'Task Linked' : 'Convert to Task'"></button></section></template></div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'related-activities'" class="space-y-3"><template x-for="activity in meetingWorkspaceRelated" :key="activity.key || `${activity.type}-${activity.id}`"><button type="button" @click="openActivityDetails(activity)" class="block w-full rounded-xl border border-slate-200 bg-white p-4 text-left hover:border-blue-200"><p class="text-[10px] font-bold uppercase text-blue-600" x-text="formatActivityType(String(activity.type || '').toLowerCase())"></p><p class="mt-1 text-sm font-semibold text-slate-800" x-text="activity.title"></p></button></template><p x-show="!meetingWorkspaceRelated.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-400">No related activities.</p></div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'time'" class="grid grid-cols-1 gap-3 sm:grid-cols-3"><template x-for="card in coreActivitySummaryCards.slice(-3)" :key="card.label"><div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-[9px] font-bold uppercase text-slate-400" x-text="card.label"></p><p class="mt-2 text-lg font-bold text-slate-900" x-text="card.value"></p></div></template></div>

                    <div x-show="selectedCoreActivityType === 'meeting' && !meetingWorkspaceLoading && coreActivityDetailTab === 'activity-log'" class="space-y-3"><template x-for="entry in meetingWorkspaceAudit" :key="entry.id"><div class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex justify-between gap-3"><p class="text-sm font-semibold capitalize text-slate-800" x-text="String(entry.action || 'Activity').replaceAll('_', ' ')"></p><time class="text-[10px] text-slate-400" x-text="formatTaskDateTime(entry.created_at)"></time></div><p class="mt-1 text-xs text-slate-500" x-text="entry.user?.name || entry.user?.email || 'System'"></p></div></template><p x-show="!meetingWorkspaceAudit.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-400">No activity log entries.</p></div>

                    <div x-show="selectedCoreActivityType !== 'meeting' && coreActivityDetailTab !== 'overview'" class="rounded-xl border border-slate-200 bg-white p-8 text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            <i class="far fa-folder-open"></i>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-slate-800" x-text="coreActivityActiveTabLabel"></h3>
                        <p class="mt-1 text-xs text-slate-500" x-text="coreActivityTabEmptyMessage"></p>
                    </div>
                </div>
            </section>
        </div>
    </div>

{{-- selectedMeetingDetails && !selectedCoreActivityDetails --}}
<div x-show="selectedMeetingDetails && !selectedCoreActivityDetails"
         style="display: none;"
         class="w-full min-w-0">

        <div @click.stop class="bg-white shadow-sm w-full flex flex-col overflow-hidden rounded-xl border border-gray-200">

            <!-- Header -->
            <div class="px-5 py-4 flex items-center justify-between shrink-0 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Meeting Details</h3>
                    <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5" x-text="selectedMeetingDetails?.owner"></div>
                </div>
                <button @click="returnToActivities()" class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">&times;</button>
            </div>

        <!-- Panel Body -->
        <div class="flex flex-col flex-1 bg-white">

            <!-- Tabs -->
            <div class="flex px-6 pt-2 border-b border-gray-100">
                <button @click="meetingDetailsTab = 'information'"
                        :class="meetingDetailsTab === 'information' ? 'bg-[#1d54e2] text-white' : 'text-gray-500 hover:bg-gray-50'"
                        class="px-6 py-2 rounded-t-lg text-sm font-semibold transition">
                    Information
                </button>
                <button @click="meetingDetailsTab = 'notes'"
                        :class="meetingDetailsTab === 'notes' ? 'bg-[#1d54e2] text-white font-semibold' : 'text-gray-500 hover:bg-gray-50 font-medium'"
                        class="px-6 py-2 rounded-t-lg text-sm transition">
                    Notes
                </button>
            </div>

            <!-- Content Area -->
            <div class="p-6 overflow-y-auto flex-1">

                <!-- Information Tab -->
                <div x-show="meetingDetailsTab === 'information'">
                    <h4 class="font-bold text-gray-900 text-sm mb-4">Basic Info</h4>

                    <div class="space-y-4">
                        <!-- Date & Time -->
                        <div class="flex group">
                            <div class="w-32 text-sm text-gray-500">Date & Time</div>
                            <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                                <span>
                                    <span x-text="selectedMeetingDetails?.date"></span>
                                    <span class="ml-2" x-text="selectedMeetingDetails?.time"></span>
                                </span>
                            </div>
                        </div>

                        <!-- Duration -->
                        <div class="flex group">
                            <div class="w-32 text-sm text-gray-500">Duration</div>
                            <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                                <span x-text="selectedMeetingDetails?.duration"></span>
                            </div>
                        </div>

                        <!-- Meeting Link / Location -->
                        <div class="flex group">
                            <div class="w-32 text-sm text-gray-500">Meeting Link / Location</div>
                            <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                                <template x-if="selectedMeetingDetails?.conference_url">
                                    <a
                                        :href="selectedMeetingDetails.conference_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        @click.stop
                                        class="inline-flex items-center gap-1.5 font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        <i class="fas fa-video text-xs"></i>
                                        <span>Join Google Meet</span>
                                    </a>
                                </template>

                                <span
                                    x-show="!selectedMeetingDetails?.conference_url"
                                    x-text="selectedMeetingDetails?.location || 'No location provided'"
                                ></span>
                            </div>
                        </div>

                        <!-- Attendees -->
                        <div class="flex group">
                            <div class="w-32 text-sm text-gray-500">Attendees</div>
                            <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                                <span x-text="selectedMeetingDetails?.attendees"></span>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="flex group">
                            <div class="w-32 text-sm text-gray-500">Status</div>
                            <div class="flex-1 text-sm text-gray-900 group-hover:bg-gray-50 rounded px-1 -mx-1 transition">
                                <span x-text="selectedMeetingDetails?.status"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <h4 class="font-bold text-gray-900 text-sm mt-6 mb-2">Description</h4>
                    <p class="text-sm text-gray-500" x-text="selectedMeetingDetails?.description"></p>

                    <!-- Download Options -->
                    <h4 class="font-bold text-gray-900 text-sm mt-6 mb-3">Download Options</h4>
                    <div class="space-y-3">
                        <!-- Video -->
                        <div class="flex items-center" x-show="selectedMeetingDetails?.has_video">
                            <div class="w-24 text-sm text-gray-500">Video</div>
                            <div class="flex items-center gap-2">
                                <button @click="downloadFile('video', 'mp4')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1d54e2] text-white rounded-lg text-xs font-semibold hover:bg-[#1541b0] transition shadow-sm"
                                        :class="selectedMeetingDetails?.status === 'upcoming' ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''">
                                    <i class="fas fa-download text-[10px]"></i>
                                    Download
                                </button>
                                <button @click="showVideoPlayer = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1d54e2] text-white rounded-lg text-xs font-semibold hover:bg-[#1541b0] transition shadow-sm"
                                        :class="selectedMeetingDetails?.status === 'upcoming' ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''">
                                    Open
                                </button>
                            </div>
                        </div>

                        <!-- Audio -->
                        <div class="flex items-center" x-show="selectedMeetingDetails?.has_audio">
                            <div class="w-24 text-sm text-gray-500">Audio</div>
                            <div class="flex items-center gap-2">
                                <button @click="downloadFile('audio', 'mp3')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1d54e2] text-white rounded-lg text-xs font-semibold hover:bg-[#1541b0] transition shadow-sm"
                                        :class="selectedMeetingDetails?.status === 'upcoming' ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''">
                                    <i class="fas fa-download text-[10px]"></i>
                                    Download
                                </button>
                            </div>
                        </div>

                        <!-- Transcript -->
                        <div class="flex items-center" x-show="selectedMeetingDetails?.has_transcript">
                            <div class="w-24 text-sm text-gray-500">Transcript</div>
                            <div class="relative">
                                <button @click="activeDownloadMenu = activeDownloadMenu === 'transcript' ? null : 'transcript'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1d54e2] text-white rounded-lg text-xs font-semibold hover:bg-[#1541b0] transition shadow-sm"
                                        :class="selectedMeetingDetails?.status === 'upcoming' ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''">
                                    <i class="fas fa-download text-[10px]"></i>
                                    Download
                                </button>

                                <!-- Dropdown -->
                                <div x-show="activeDownloadMenu === 'transcript'"
                                     @click.away="activeDownloadMenu = null"
                                     class="absolute left-0 mt-2 w-32 bg-white rounded-lg shadow-xl border border-gray-100 z-50 overflow-hidden"
                                     style="display: none;">
                                    <button @click="downloadFile('transcript', 'pdf')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <i class="far fa-file-pdf text-red-500"></i> PDF (.pdf)
                                    </button>
                                    <button @click="downloadFile('transcript', 'docs')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2 border-t border-gray-50">
                                        <i class="far fa-file-word text-blue-500"></i> DOCS (.docx)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Minutes -->
                        <div class="flex items-center" x-show="selectedMeetingDetails?.has_minutes">
                            <div class="w-24 text-sm text-gray-500">Minutes</div>
                            <div class="relative">
                                <button @click="activeDownloadMenu = activeDownloadMenu === 'minutes' ? null : 'minutes'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1d54e2] text-white rounded-lg text-xs font-semibold hover:bg-[#1541b0] transition shadow-sm"
                                        :class="selectedMeetingDetails?.status === 'upcoming' ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''">
                                    <i class="fas fa-download text-[10px]"></i>
                                    Download
                                </button>

                                <!-- Dropdown -->
                                <div x-show="activeDownloadMenu === 'minutes'"
                                     @click.away="activeDownloadMenu = null"
                                     class="absolute left-0 mt-2 w-32 bg-white rounded-lg shadow-xl border border-gray-100 z-50 overflow-hidden"
                                     style="display: none;">
                                    <button @click="downloadFile('minutes', 'pdf')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <i class="far fa-file-pdf text-red-500"></i> PDF (.pdf)
                                    </button>
                                    <button @click="downloadFile('minutes', 'docs')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2 border-t border-gray-50">
                                        <i class="far fa-file-word text-blue-500"></i> DOCS (.docx)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload -->
                        <div class="pt-2">
                            <button @click="document.getElementById('videoUploadInput').click()"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-700 rounded-xl text-sm font-semibold hover:bg-white hover:border-[#1d54e2] hover:text-[#1d54e2] transition-all shadow-sm group"
                                    :class="isUploading ? 'opacity-50 cursor-wait pointer-events-none' : ''">
                                <template x-if="!isUploading">
                                    <i class="fas fa-video text-xs group-hover:scale-110 transition-transform"></i>
                                </template>
                                <template x-if="isUploading">
                                    <i class="fas fa-spinner fa-spin text-xs"></i>
                                </template>
                                <span x-text="isUploading ? 'Uploading Video...' : 'Upload Video File'"></span>
                            </button>
                        </div>

                        <!-- Open Recording Button -->
                        <div class="pt-1" x-show="selectedMeetingDetails?.has_video">
                            <button @click="showVideoPlayer = true"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#1d54e2] text-white rounded-xl text-sm font-bold hover:bg-[#1541b0] transition-all shadow-md group">
                                <i class="fas fa-play-circle text-xs group-hover:scale-110 transition-transform"></i>
                                Open Recording
                            </button>
                        </div>


                        <!-- Manual Transcript / Minutes Upload -->
                        <div class="pt-4 border-t border-gray-100" x-show="selectedMeetingDetails?.status === 'completed'">
                            <div class="grid grid-cols-1 gap-2">
                                <button
                                    type="button"
                                    @click="document.getElementById('transcriptUploadInput').click()"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-700 rounded-xl text-sm font-bold hover:bg-white hover:border-[#1d54e2] hover:text-[#1d54e2] transition-all shadow-sm"
                                    :class="isUploadingTranscript ? 'opacity-50 cursor-wait pointer-events-none' : ''"
                                >
                                    <i class="fas" :class="isUploadingTranscript ? 'fa-spinner fa-spin' : 'fa-file-upload'"></i>
                                    <span x-text="isUploadingTranscript ? 'Uploading Transcript...' : 'Upload Transcript'"></span>
                                </button>

                                <button
                                    type="button"
                                    @click="document.getElementById('minutesUploadInput').click()"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-700 rounded-xl text-sm font-bold hover:bg-white hover:border-[#1d54e2] hover:text-[#1d54e2] transition-all shadow-sm"
                                    :class="isUploadingMinutes ? 'opacity-50 cursor-wait pointer-events-none' : ''"
                                >
                                    <i class="fas" :class="isUploadingMinutes ? 'fa-spinner fa-spin' : 'fa-file-alt'"></i>
                                    <span x-text="isUploadingMinutes ? 'Uploading Minutes...' : 'Upload Minutes'"></span>
                                </button>
                            </div>
                            <p class="text-[10px] text-gray-400 text-center mt-2 italic">AI transcript is not enabled yet. Upload transcript/minutes file manually.</p>
                        </div>
                        </div>
                    </div>

                    <!-- Hidden File Input for Upload -->
                    <input type="file"
                           id="videoUploadInput"
                           x-ref="videoUploadInput"
                           class="hidden"
                           accept="video/*"
                           @change="uploadVideo($event, selectedMeetingDetails.id)">

                    <input type="file"
                           id="transcriptUploadInput"
                           class="hidden"
                           accept=".pdf,.doc,.docx,.txt"
                           @change="uploadTranscriptFile($event, selectedMeetingDetails.id)">

                    <input type="file"
                           id="minutesUploadInput"
                           class="hidden"
                           accept=".pdf,.doc,.docx,.txt"
                           @change="uploadMinutesFile($event, selectedMeetingDetails.id)">


                <!-- Notes Tab -->
                <div x-show="meetingDetailsTab === 'notes'" style="display: none;">
                    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-4">
                        <textarea x-model="newNoteContent" class="w-full bg-transparent resize-none outline-none text-sm text-gray-900 placeholder-gray-400" rows="3" placeholder="Add a note..."></textarea>
                        <div class="flex justify-end mt-2">
                            <button @click="saveNote(selectedMeetingDetails, 'meeting')" class="px-4 py-1.5 bg-[#1d54e2] text-white text-xs font-semibold rounded-lg hover:bg-[#1541b0] transition" x-text="editingNote ? 'Update Note' : 'Save Note'"></button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <template x-for="note in selectedMeetingDetails?.notes || []" :key="note.id">
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-[#1d54e2] text-white flex items-center justify-center text-xs font-bold shrink-0" x-text="(note.owner || 'JK').split(' ').map(n => n[0]).join('').toUpperCase()">
                                </div>
                                <div class="flex-1 text-sm bg-white p-3 rounded-lg border border-gray-100 shadow-sm relative group">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-semibold text-gray-900" x-text="note.owner"></span>
                                        <div class="flex items-center gap-2">
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                                <i @click="editNote(note)" class="fas fa-pencil-alt text-[10px] text-gray-400 hover:text-blue-600 cursor-pointer"></i>
                                                <i @click="deleteNote(note.id, selectedMeetingDetails)" class="far fa-trash-alt text-[10px] text-gray-400 hover:text-red-500 cursor-pointer"></i>
                                            </div>
                                            <span class="text-xs text-gray-500" x-text="new Date(note.created_at).toLocaleDateString() === new Date().toLocaleDateString() ? 'Today' : new Date(note.created_at).toLocaleDateString()"></span>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 whitespace-pre-line" x-text="note.content"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

{{--
    Unclassified Records queue.

    The Document Reference column deliberately shows an em dash: intake items consume
    storage only and a Document Reference is assigned at official filing, never before.
--}}
<x-layouts.app title="Unclassified Records" :unclassified-count="$unclassifiedCount ?? null">
    {{-- The whole panel carries the upload scope so the header button and the
         dropzone share one picker and one busy state. The window guards stop a
         file dropped outside the zone navigating the browser away and losing it;
         they only engage once a drag has actually entered the zone.
         The camera is a separate Alpine root, so it hands the photo back over
         ordo:camera-photo rather than reaching in here directly. --}}
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo"
         x-data="queueUpload(@js(route('records.intake.store')))"
         x-on:ordo:camera-photo.window="acceptPhoto($event.detail)"
         x-on:dragover.window="if (dragging) $event.preventDefault()"
         x-on:drop.window="if (dragging) { $event.preventDefault(); dragging = false }">

        <x-ordo.page-head title="Unclassified Records"
                          subtitle="Upload first, then classify documents into the correct Classification Code and Index Code. Filing assigns the Document Reference and uses 1 Record Unit per record.">
            <x-slot:actions>
                <x-ordo.button type="button" variant="primary"
                               x-bind:disabled="busy"
                               x-on:click="$refs.input.click()">+ Upload Records</x-ordo.button>
            </x-slot:actions>
        </x-ordo.page-head>

        <x-ordo.flash wrapper />

        <div class="px-4 py-4">
            {{-- ============ DROPZONE ============
                 Follows the reference mock, which drops files straight onto this
                 page. It posts to the same endpoint as the dedicated upload form,
                 so there is one set of intake rules rather than two. --}}
            <div class="mb-4 rounded-xl border-[1.5px] border-dashed px-7 py-7 text-center transition-colors"
                 x-bind:class="dragging ? 'border-ordo-blue bg-[#eef4ff]' : 'border-[#b9c8ee] bg-[#f9fbff]'"
                 x-on:dragenter.prevent="dragging = true"
                 x-on:dragover.prevent="dragging = true"
                 x-on:dragleave.prevent="dragging = false"
                 x-on:drop.prevent="dragging = false; read($event.dataTransfer.files)">

                <strong class="mb-[5px] block text-[13px]">Drop files here to upload</strong>
                <span class="text-[10px] text-[#8b94a3]">
                    PDF, text, CSV, Word, Excel and images. You can upload multiple files at once.
                </span>

                <div class="mt-3 flex items-center justify-center gap-2">
                    <x-ordo.button type="button" variant="soft"
                                   x-bind:disabled="busy"
                                   x-on:click="$refs.input.click()">Choose Files</x-ordo.button>

                    {{-- Photograph a document into the queue. Sends it straight to
                         the same intake endpoint the picker and the drop use, so
                         there is still one set of intake rules on this page. --}}
                    <x-ordo.button type="button" variant="default"
                                   x-bind:disabled="busy"
                                   x-on:click="$dispatch('ordo:camera-open')">Capture from Camera</x-ordo.button>
                </div>

                <input type="file" multiple x-ref="input" class="hidden"
                       accept="{{ collect(config('ordo.document_extensions'))->map(fn ($ext) => ".{$ext}")->join(',') }}"
                       x-on:change="read($event.target.files)" />

                {{-- Why a drop failed. Server-authoritative: the same message
                     the dedicated form would have shown after its redirect. --}}
                <p x-show="error" x-cloak
                   class="mx-auto mt-3 max-w-[520px] rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-left text-[11px] text-[#b42318]"
                   x-text="error"></p>

                <template x-if="pending.length">
                    <ul class="mx-auto mt-4 max-w-[520px] space-y-1 text-left">
                        <template x-for="file in pending" x-bind:key="file.name">
                            <li class="flex items-center gap-2 rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px]">
                                <span class="min-w-0 flex-1 truncate text-[#475467]" x-text="file.name"></span>
                                <span class="shrink-0 text-ordo-faint" x-text="human(file.size)"></span>
                            </li>
                        </template>
                    </ul>
                </template>

                <p x-show="busy" x-cloak class="mt-3 text-[10px] text-ordo-muted">
                    Uploading&hellip;
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-b-0 border-ordo-line bg-[#fbfcfe] px-4 py-3">
                <form method="GET" action="{{ route('records.unclassified') }}" class="flex flex-1 flex-wrap items-center gap-2">
                    <input type="search" name="q" value="{{ $search }}"
                           placeholder="Search uploaded files..."
                           aria-label="Search uploaded files"
                           class="min-w-[200px] flex-1 rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />

                    <select name="type" aria-label="Filter by file type"
                            class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                        <option value="">All file types</option>
                        <option value="image" @selected($type === 'image')>Images</option>
                        <option value="pdf" @selected($type === 'pdf')>PDF</option>
                        <option value="other" @selected($type === 'other')>Other</option>
                    </select>

                    <x-ordo.button type="submit" variant="default">Apply</x-ordo.button>

                    @if ($search !== '' || $type !== '')
                        <a href="{{ route('records.unclassified') }}" class="text-[11px] text-ordo-blue">Reset</a>
                    @endif
                </form>

                <x-ordo.button type="button" variant="default" x-on:click="$dispatch('ordo:bulk-open')">
                    Bulk Classify Selected
                </x-ordo.button>
            </div>

            {{-- Row selection is held in a shared store so the bulk modal and the table agree. --}}
            {{-- Row selection is held in a shared store so the bulk modal and the table agree. --}}
            <div class="overflow-x-auto"
                 x-data="queueTable(@js($intakeItems->pluck('id')->all()))"
                 x-on:ordo:selection-cleared.window="$store.queueSelection.clear()">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#fbfcfe] text-[10px] font-semibold text-ordo-muted">
                            <th class="w-9 px-3 py-2.5">
                                <input type="checkbox" aria-label="Select every unclassified record"
                                       x-ref="selectAll"
                                       x-on:change="toggleAll($event.target.checked)" />
                            </th>
                            <th class="px-3 py-2.5">Doc Ref</th>
                            <th class="px-3 py-2.5">File</th>
                            <th class="px-3 py-2.5">Uploaded By</th>
                            <th class="px-3 py-2.5">Uploaded</th>
                            <th class="px-3 py-2.5">Size</th>
                            <th class="px-3 py-2.5">Auto Read</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($intakeItems as $item)
                            @php
                                $master = $item->files->first();
                                $reading = $item->files->firstWhere(fn ($file) => $file->ocr_status->label() !== 'Not read');
                                $readingStatus = $reading?->ocr_status ?? $master?->ocr_status;
                            @endphp
                            <tr class="border-t border-ordo-line text-[11px] hover:bg-[#fcfdff]"
                                x-bind:class="isSelected('{{ $item->id }}') ? 'bg-ordo-blue-soft' : ''"
                                data-item-row="{{ $item->id }}">
                                <td class="px-3 py-2.5">
                                    <input type="checkbox" value="{{ $item->id }}"
                                           aria-label="Select {{ $item->title }}"
                                           x-on:change="toggle('{{ $item->id }}', $event.target.checked)" />
                                </td>
                                <td class="px-3 py-2.5">
                                    {{-- Assigned at official filing, so an intake item has none yet. --}}
                                    <x-ordo.docref />
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="grid h-10 w-[34px] shrink-0 place-items-center rounded-md border border-ordo-line bg-[#f3f4f6] text-[10px] text-ordo-muted">
                                            {{ $master?->isImage() ? 'IMG' : ($master?->isPdf() ? 'PDF' : 'FILE') }}
                                        </div>
                                        <div class="min-w-0">
                                            <strong class="block max-w-[260px] truncate text-[11px]">{{ $item->title }}</strong>
                                            <small class="text-[9px] text-ordo-faint">
                                                {{ $item->files->count() }} file(s) &middot; {{ $master?->mime_type ?: 'unknown type' }}
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-ordo-muted">
                                    {{ $item->uploadedBy?->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-ordo-muted">
                                    {{ $item->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-2.5 text-ordo-muted">@fileSize($item->storage_bytes)</td>
                                <td class="px-3 py-2.5">
                                    <x-ordo.pill :tone="$readingStatus?->tone() ?? 'neutral'">
                                        {{ $readingStatus?->label() ?? 'Queued' }}
                                    </x-ordo.pill>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex justify-end gap-2">
                                        <x-ordo.button type="button" variant="soft"
                                                       x-on:click="$dispatch('ordo:classify-open', { id: '{{ $item->id }}' })">
                                            Classify
                                        </x-ordo.button>
                                        <x-ordo.button type="button" variant="default"
                                                       x-on:click="$dispatch('ordo:intake-details-open', { id: '{{ $item->id }}' })">Details</x-ordo.button>
                                        {{-- Payload travels on data- attributes: Blade does not compile directives such as
                                                     @js() inside a component's attribute values. --}}
                                        <x-ordo.button type="button" variant="danger"
                                                       data-discard="{{ $item->id }}"
                                                       data-title="{{ $item->title }}"
                                                       data-files="{{ $item->files->count() }}"
                                                       data-bytes="{{ (int) $item->storage_bytes }}"
                                                       x-on:click="$dispatch('ordo:discard-open', { id: $event.currentTarget.dataset.discard, title: $event.currentTarget.dataset.title, files: Number($event.currentTarget.dataset.files), bytes: Number($event.currentTarget.dataset.bytes) })">
                                            Discard
                                        </x-ordo.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <x-ordo.empty message="No unclassified records. Upload documents to begin." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($intakeItems->hasPages())
                <div class="mt-2.5">{{ $intakeItems->links() }}</div>
            @endif
        </div>
    </div>

    @include('records.partials.bulk-classify-modal')
    @include('records.partials.camera-capture')
    @include('records.partials.intake-details-drawer')
    @include('records.partials.classify-drawer')
    @include('records.partials.discard-modal')
</x-layouts.app>
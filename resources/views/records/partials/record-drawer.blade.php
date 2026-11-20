{{--
    Full Record Workspace Drawer.

    Left pane: the stored master document. Right pane: a seven-tab workspace over the
    record's confirmed metadata, files, extracted text, links, integration wiring,
    history and system metadata.

    Its own Alpine root: $refs and $nextTick are only injected on a root x-data scope.
--}}
{{-- Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it. --}}
<div class="fixed inset-0 z-50 bg-[rgba(15,23,42,.28)]" id="recordDrawer"
     x-data="recordDrawer(@js($workspaceRecords ?? []), @js($pickerUrl ?? ''), @js($reReadUrl ?? ''), @js($fileStatusUrl ?? ''), @js($supportingFileUrl ?? ''), @js($newVersionUrl ?? ''))"
     x-show="open" x-cloak
     x-on:ordo:record-open.window="show($event.detail?.id)"
     x-on:keydown.escape.window="if (open) close()"
     x-on:keydown.ctrl.s.window.prevent="if (open) { pane = 'ocr'; $nextTick(() => $refs.recordOcrSearchInput?.focus()); }"
     x-on:keydown.ctrl.f.window.prevent="if (open) { pane = 'ocr'; $nextTick(() => $refs.recordOcrSearchInput?.focus()); }"
     x-on:click.self="close()">

    <div class="absolute inset-2.5 overflow-hidden rounded-[14px] bg-white shadow-[0_24px_70px_rgba(15,23,42,.22)]"
         role="dialog" aria-modal="true" aria-labelledby="record-drawer-title"
         x-on:click.stop>

        <div class="grid h-full min-h-0 grid-cols-[minmax(430px,45%)_minmax(520px,55%)] max-[980px]:grid-cols-1 max-[980px]:grid-rows-[minmax(280px,42vh)_1fr]">

            {{-- ============ LEFT: DOCUMENT PREVIEW ============
                 Geometry measured off the reference stylesheet: the pane head is
                 22/22/18, the work area pads 28, and the preview body holds 520px
                 so a full page of a document is actually visible without the pane
                 scrolling first. --}}
            <section class="flex min-h-0 min-w-0 flex-col border-r border-ordo-line bg-white max-[980px]:border-r-0 max-[980px]:border-b">
                <div class="border-b border-ordo-line px-[22px] pb-[18px] pt-[22px]">
                    <h3 class="m-0 text-[15px] font-bold">Document Preview</h3>
                    <p class="mt-1.25 mb-0 text-[10px] text-[#8b94a3]">
                        View the stored master file and extracted OCR text.
                    </p>
                </div>

                <div class="min-h-0 flex-1 overflow-auto bg-ordo-bg p-7">
                    <div class="flex min-h-full flex-col rounded-[14px] border border-[#dfe5ed] bg-[#eef2f6] p-3.5 shadow-[0_2px_8px_rgba(15,23,42,.04)]">
                        <div class="mb-3 flex h-[38px] items-center justify-between rounded-full border border-ordo-line bg-white px-3.5 text-[10px] text-[#697386]">
                            <strong class="text-[10px] font-semibold text-[#667085]">Document Holder</strong>
                            <div class="flex items-center gap-1.5">
                                <button type="button" class="mini-btn" x-bind:class="pane === 'preview' && 'active'"
                                        x-on:click="pane = 'preview'">Preview</button>
                                <button type="button" class="mini-btn" x-bind:class="pane === 'ocr' && 'active'"
                                        x-on:click="pane = 'ocr'">OCR Text</button>
                                {{-- Re-queues this one file and follows it to a
                                     terminal state. The bar below is driven by the
                                     file's real status, not a timer. --}}
                                <button type="button" class="mini-btn ocr hidden"
                                        x-bind:disabled="reReading || ! master"
                                        x-on:click="reRead()">
                                    <span x-show="! reReading">Re-read Document</span>
                                    <span x-show="reReading" x-cloak>Reading&hellip;</span>
                                </button>
                            </div>
                        </div>

                        {{-- The document itself. No top margin: the holder head above already carries
                             the reference's 12px, and the reference gives
                             .doc-preview margin:0. --}}
                        <div class="min-h-0 flex-1 overflow-hidden rounded-lg border border-[#d9dee7] bg-white shadow-[0_2px_6px_rgba(15,23,42,.05)]">
                            <div class="grid h-full min-h-[520px] place-items-center" x-show="pane === 'preview'">
                                <template x-if="master && master.renderedUrl">
                                    {{-- Word and Excel documents, shown as a page ORDO generates
                                         from the file's own XML. The frame is sandboxed as
                                         well as the response being: two layers, because this
                                         is the only HTML ORDO serves, and it comes out of a
                                         file the reader uploaded. --}}
                                    <iframe x-bind:src="master.renderedUrl" sandbox=""
                                            title="Document preview"
                                            class="h-full min-h-[520px] w-full bg-white"></iframe>
                                </template><template x-if="master && master.isText">
                                    {{-- Plain text and CSV are the one document family a browser
                                         shows on its own, so a .txt finally previews instead of
                                         asking to be downloaded. Served as text/plain and
                                         sandboxed, so it is read, never run. --}}
                                    <iframe x-bind:src="master.previewUrl"
                                            title="Document preview"
                                            class="h-full min-h-[520px] w-full bg-white"></iframe>
                                </template><template x-if="master && master.isImage">
                                    <img x-bind:src="master.previewUrl" x-bind:alt="master.name"
                                         class="max-h-full w-full object-contain" />
                                </template>
                                <template x-if="master && master.isPdf">
                                    <iframe x-bind:src="master.previewUrl + '#toolbar=1&navpanes=0&view=FitH'"
                                            title="Document preview" class="h-full min-h-[520px] w-full"></iframe>
                                </template>
                                <template x-if="master && ! master.renderedUrl && ! master.isImage && ! master.isPdf && ! master.isText">
                                    {{-- A file the browser cannot render still has to be
                                         described, not just refused. The stored document
                                         is untouched and openable; this card exists so
                                         the reader knows what they are about to open. --}}
                                    <div class="file-preview-unavailable">
                                        <div class="big" aria-hidden="true">&#128196;</div>
                                        <strong>Preview not available</strong>
                                        <span>This file type cannot be shown in the browser. Download the original to read it.</span>

                                        <dl class="mt-3.5 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-left">
                                            <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">Type</dt>
                                            <dd class="m-0 break-all text-[10px] text-[#475467]" x-text="(master.extension || '—') + ' · ' + (master.mimeType || 'unknown')"></dd>
                                            <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">Size</dt>
                                            <dd class="m-0 text-[10px] text-[#475467]" x-text="humanSize(master.sizeBytes)"></dd>
                                            <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">File</dt>
                                            <dd class="m-0 break-all text-[10px] text-[#475467]" x-text="master.name"></dd>
                                        </dl>

                                        <a x-bind:href="master.downloadUrl"
                                           class="mt-3.5 inline-flex items-center justify-center gap-2 rounded-lg border border-ordo-line bg-white px-3 py-2 text-[11px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                                            Download Original
                                        </a>
                                    </div>
                                </template>
                                <template x-if="! master">
                                    <span class="text-[11px] text-ordo-faint">Preview</span>
                                </template>
                            </div>

                            {{-- Extracted text is read-only, deliberately. It is the
                                 record of what the document said; the reference mock
                                 lets a reader overwrite it in place, which would let
                                 the evidence be edited without trace. --}}
                            <div class="flex h-full min-h-[500px] flex-col overflow-hidden p-3.5" x-show="pane === 'ocr'" x-cloak>
                                <template x-if="master && master.ocrText">
                                    <div class="flex h-full min-h-0 flex-col">
                                        <div class="mb-3 flex items-center gap-2 border-b border-ordo-line pb-2.5">
                                            <div class="relative flex-1">
                                                <span class="absolute left-2.5 top-2 text-[12px] text-ordo-muted">⌕</span>
                                                <input type="search" x-model="ocrSearchQuery" x-ref="recordOcrSearchInput"
                                                       placeholder="Search text in document (Ctrl+F or Ctrl+S)..."
                                                       class="w-full rounded-lg border border-ordo-line bg-white py-1.5 pl-8 pr-3 text-[11px] outline-none focus:border-ordo-blue" />
                                            </div>
                                            <template x-if="ocrSearchQuery && ocrSearchQuery.trim()">
                                                <span class="shrink-0 text-[10px] text-ordo-muted"
                                                      x-text="ocrMatchCount + (ocrMatchCount === 1 ? ' match' : ' matches')"></span>
                                            </template>
                                            <template x-if="ocrSearchQuery">
                                                <button type="button" x-on:click="ocrSearchQuery = ''"
                                                        class="px-1.5 py-1 text-[10px] text-ordo-muted hover:text-ordo-ink">Clear</button>
                                            </template>
                                        </div>
                                        <div class="min-h-0 flex-1 overflow-auto">
                                            <pre class="whitespace-pre-wrap font-mono text-[11px] leading-[1.55] text-ordo-ink"
                                                 x-html="highlightedOcrText"></pre>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="! master || ! master.ocrText">
                                    <div class="ocr-empty">
                                        <div>
                                            <p class="m-0" x-text="ocrEmptyMessage()"></p>
                                            <template x-if="! master || master.ocrStatus !== 'queued' && master.ocrStatus !== 'reading'">
                                                <p class="mt-2 mb-0">
                                                    Reading runs on a background worker.
                                                </p>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Reading progress, below the document and above the file
                             bar, in the order the reference has them. At this
                             position a tall document leaves it below the fold, so
                             reRead() scrolls it into view when a read starts.
                             Present in the markup at all times so the pane does not
                             jump when it appears. --}}
                        <div class="mt-2.5 rounded-lg border border-ordo-line bg-white px-2.5 py-2.5"
                             data-reread-progress
                             x-show="reReadStatus !== ''" x-cloak>
                            <div class="flex items-baseline justify-between gap-2">
                                <div class="text-[10px] text-[#697386]" x-text="reReadStatus"></div>
                                <div class="shrink-0 text-[9px] text-ordo-faint" x-text="reReadProgress + '%'"></div>
                            </div>
                            <div class="mt-1.75 h-[6px] overflow-hidden rounded-full bg-[#eef1f5]">
                                <span class="block h-full rounded-full bg-ordo-blue transition-[width] duration-200"
                                      x-bind:style="'width:' + reReadProgress + '%'"></span>
                            </div>
                            <p class="mt-1.75 mb-0 text-[9px] leading-[1.5] text-[#98a2b3]" x-show="reReadMessage !== ''" x-cloak
                               x-text="reReadMessage"></p>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-ordo-line bg-white px-3 py-2.5">
                            <div class="min-w-0">
                                <div class="truncate text-[10px] font-semibold" x-text="master ? master.name : 'Master file'"></div>
                                <div class="mt-0.5 text-[9px] text-[#98a2b3]" x-text="masterMeta()"></div>
                            </div>
                            <a x-show="master && (master.renderedUrl || master.renderMode !== 'download')"
                               x-bind:href="master ? (master.renderedUrl || master.previewUrl) : '#'" target="_blank" rel="noopener"
                               class="shrink-0 rounded-lg border border-ordo-line bg-white px-3 py-2 text-[12px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                                Open File
                            </a>
                            <a x-show="master" x-bind:href="master ? master.downloadUrl : '#'"
                               class="shrink-0 rounded-lg border border-ordo-line bg-white px-3 py-2 text-[12px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                                Download
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ RIGHT: TABBED WORKSPACE ============ --}}
            <section class="flex min-h-0 min-w-0 flex-col bg-white">
                <div class="flex items-start justify-between border-b border-ordo-line px-[22px] pb-[18px] pt-[22px]">
                    <div class="min-w-0">
                        <h3 id="record-drawer-title" class="m-0 text-[15px] font-bold" x-text="current ? current.title : 'Record'"></h3>
                        <p class="mt-1 mb-0 truncate text-[10px] text-ordo-muted" x-text="current ? subtitle() : ''"></p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <div class="flex gap-2 border-b border-ordo-line bg-white px-[22px]">
                    <template x-for="tab in tabs" x-bind:key="tab.key">
                        <button type="button" class="border-0 border-b-2 border-transparent bg-transparent px-2 py-3 text-[11px] text-[#667085] transition-colors"
                                x-bind:class="activeTab === tab.key ? 'border-ordo-blue text-ordo-blue' : ''"
                                x-on:click="activeTab = tab.key"
                                x-text="tab.label"></button>
                    </template>
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-[22px] py-[22px]">
                    @include('records.partials.record-tabs.overview')
                    @include('records.partials.record-tabs.files')
                    @include('records.partials.record-tabs.extracted')
                    @include('records.partials.record-tabs.links')
                    @include('records.partials.record-tabs.integration')
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line bg-white px-[22px] py-3.5">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Close</x-ordo.button>
                    <x-ordo.button type="button" variant="danger"
                                   x-bind:disabled="! current || archived"
                                   x-on:click="$dispatch('ordo:archive-open', { id: current?.id })">Archive</x-ordo.button>
                    <x-ordo.button type="button" variant="primary"
                                   x-bind:disabled="! current || archived"
                                   x-on:click="$dispatch('ordo:metadata-open', { id: current?.id })">Edit Metadata</x-ordo.button>
                </div>
            </section>
        </div>
    </div>

    @include('records.partials.record-modals')

    {{-- The Files tab's third action. Mounted here rather than per page so it
         travels with the drawer whose Files tab dispatches it. --}}
    @include('records.partials.upload-as-record-modal')

    {{-- The picker is fetched for the record in view, so one copy serves every row. --}}
    <template x-if="pickerError">
        <div class="fixed bottom-4 left-1/2 z-[80] -translate-x-1/2 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318] shadow-[0_10px_30px_rgba(15,23,42,.14)]"
             x-text="pickerError"></div>
    </template>
    <div x-html="pickerHtml"></div>
</div>
{{--
    Split-screen Classification Workspace Drawer.

    Left pane: the master document preview. Right pane: the record form, showing only the
    default fields, with Advanced Details (Identifiers, Dates, Signatories, Financials)
    collapsed as AGENTS.md requires.

    This is its own Alpine root: $refs and $nextTick are only injected on a root x-data
    scope, so nesting it under another component's data would break them.
--}}
{{-- Visibility is owned by x-show alone. A Tailwind `hidden` class here would be
     display:none from a stylesheet, which [x-cloak]'s !important beats anyway. --}}
<div class="fixed inset-0 z-50 bg-[rgba(15,23,42,.28)]" id="classifyDrawer"
     x-data="classifyDrawer(
         @js($drawerItems),
         @js($classifications->pluck('name', 'id')),
         @js($indexesByClassification),
         @js($sources->pluck('name', 'id')),
         '{{ route('records.unclassified') }}',
         @js(route('records.files.re-read', ['file' => '__file__'])),
         @js(route('records.files.status', ['file' => '__file__'])),
          {{-- The Integration tab's "Register" links to the queue with ?open= so a
               named upload can be filed without hunting for it. Null on an
               ordinary visit, and an id this company does not own never arrives
               here — the controller resolves it against its own open items. --}}
          @js($openItemId ?? null),
         @js(old()),
         @js($errors->getMessages())
     )"
     x-init="if (initialItemId) show(initialItemId)"
     x-show="open" x-cloak
     x-on:ordo:classify-open.window="show($event.detail?.id)"
     x-on:keydown.escape.window="if (open) close()"
     x-on:keydown.ctrl.s.window.prevent="if (open) { pane = 'ocr'; $nextTick(() => $refs.ocrSearchInput?.focus()); }"
     x-on:keydown.ctrl.f.window.prevent="if (open) { pane = 'ocr'; $nextTick(() => $refs.ocrSearchInput?.focus()); }"
     x-on:click.self="close()">

    <div class="absolute inset-2.5 overflow-hidden rounded-[14px] bg-white shadow-[0_24px_70px_rgba(15,23,42,.22)]"
         role="dialog" aria-modal="true" aria-labelledby="classify-drawer-title"
         x-on:click.stop>

        <div class="grid h-full min-h-0 grid-cols-[minmax(430px,45%)_minmax(520px,55%)] max-[980px]:grid-cols-1 max-[980px]:grid-rows-[minmax(280px,42vh)_1fr]">

            {{-- ============ LEFT: DOCUMENT PREVIEW ============
                 The same geometry and the same Document Holder as the record
                 workspace, so one reader moving between the two drawers sees the
                 document presented identically on both. --}}
            <section class="flex min-h-0 min-w-0 flex-col border-r border-ordo-line bg-white max-[980px]:border-r-0 max-[980px]:border-b">
                <div class="border-b border-gray-200 px-[22px] pb-[18px] pt-[22px] bg-white">
                    <h3 class="m-0 text-base font-bold text-gray-900">Document Preview</h3>
                    <p class="mt-1 mb-0 text-xs text-gray-500">
                        Review the uploaded file while encoding its records information.
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
                                <button type="button" class="mini-btn ocr hidden"
                                        x-bind:disabled="reReading || ! master"
                                        x-on:click="reRead()">
                                    <span x-show="! reReading">Re-read Document</span>
                                    <span x-show="reReading" x-cloak>Reading&hellip;</span>
                                </button>
                            </div>
                        </div>

                        {{-- The document itself. No top margin: the holder head above
                             already carries the reference's 12px, and the reference
                             gives .doc-preview margin:0. --}}
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

                            {{-- Read-only, for the same reason as the record drawer:
                                 extracted text is the record of what the document
                                 said and is not something to edit in place. --}}
                            <div class="flex h-full min-h-[500px] flex-col overflow-hidden p-3.5" x-show="pane === 'ocr'" x-cloak>
                                <template x-if="master && master.ocrText">
                                    <div class="flex h-full min-h-0 flex-col">
                                        <div class="mb-3 flex items-center gap-2 border-b border-ordo-line pb-2.5">
                                            <div class="relative flex-1">
                                                <span class="absolute left-2.5 top-2 text-[12px] text-ordo-muted">⌕</span>
                                                <input type="search" x-model="ocrSearchQuery" x-ref="ocrSearchInput"
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
                                                    ORDO reads supported documents automatically after upload.
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
                             reRead() scrolls it into view when a read starts. --}}
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
                                <div class="truncate text-[10px] font-semibold" x-text="master ? master.name : 'Document'"></div>
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

            {{-- ============ RIGHT: RECORD FORM ============ --}}
            <section class="flex min-h-0 min-w-0 flex-col bg-white">
                <div class="flex items-start justify-between border-b border-gray-200 px-[22px] pb-[18px] pt-[22px] bg-white">
                    <div class="min-w-0">
                        <h3 id="classify-drawer-title" class="m-0 text-base font-bold text-gray-900">Classify Document</h3>
                        <p class="mt-1 mb-0 truncate text-xs text-gray-500" x-text="current ? current.title : 'Document'"></p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <form id="classify-drawer-form" class="min-h-0 flex-1 overflow-auto px-[22px] pb-6 pt-7"
                      method="POST"
                      x-bind:action="current ? formAction() : '#'"
                      x-on:submit="if (! current) $event.preventDefault()">
                    @csrf

                    <input type="hidden" name="record_classification_id" x-bind:value="form.record_classification_id" />
                    <input type="hidden" name="record_index_id" x-bind:value="form.record_index_id" />
                    <input type="hidden" name="_intake_id" x-bind:value="current ? current.id : ''" />

                    <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5">
                        <div class="min-w-0">
                            <div class="text-[10px] font-semibold text-[#4b5563]">Document Reference</div>
                            <div class="mt-0.5 text-[9px] leading-[1.45] text-[#98a2b3]">
                                Generated by ORDO when this record is filed. Nothing is assigned before filing.
                            </div>
                        </div>
                        <x-ordo.docref />
                    </div>

                    <div class="mb-4 grid grid-cols-3 gap-2 max-[1180px]:grid-cols-1">
                        <div class="auto-meta">
                            <span>Pages</span>
                            <strong x-text="master && master.pageCount ? master.pageCount : '—'"></strong>
                        </div>
                        <div class="auto-meta">
                            <span>File Size</span>
                            <strong x-text="current ? humanSize(current.storageBytes) : '—'"></strong>
                        </div>
                        <div class="auto-meta">
                            <span>Document Reading</span>
                            <strong x-text="master ? master.ocrStatusLabel : '—'"></strong>
                        </div>
                    </div>

                    <div class="mb-4 flex items-start gap-2 rounded-lg border border-[#d9e4ff] bg-[#f8faff] px-3 py-2.5 text-[10px] leading-[1.45] text-[#315fce]">
                        <span aria-hidden="true">✦</span>
                        <div>
                            <b>Automatic Document Intelligence</b><br>
                            ORDO reads the document and captures what it can detect. Review or correct any value before filing.
                        </div>
                    </div>

                    <div class="mb-3 text-[10px] font-bold uppercase tracking-[0.035em] text-[#344054]">Record Classification</div>

                    <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                        <x-ordo.field label="Classification" required full
                                      help="Select the main Records Classification Code.">
                            <select required x-model="form.record_classification_id"
                                    x-on:change="form.record_index_id = ''"
                                    aria-label="Classification"
                                    x-bind:aria-invalid="fieldError('record_classification_id') ? 'true' : 'false'"
                                    x-bind:class="fieldError('record_classification_id') ? 'border-[#b42318]' : ''"
                                    class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <option value="">Select classification</option>
                                <template x-for="option in classificationOptions" x-bind:key="option.id">
                                    <option x-bind:value="option.id" x-text="option.label"></option>
                                </template>
                            </select>
                            <p x-show="fieldError('record_classification_id')" x-cloak
                               class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('record_classification_id')"></p>
                        </x-ordo.field>

                        <x-ordo.field label="Index" required full
                                      help="Indexes are filtered automatically from the selected Classification.">
                            <select required x-model="form.record_index_id"
                                    aria-label="Index"
                                    x-bind:aria-invalid="fieldError('record_index_id') ? 'true' : 'false'"
                                    x-bind:class="fieldError('record_index_id') ? 'border-[#b42318]' : ''"
                                    class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <template x-if="! form.record_classification_id">
                                    <option value="">Select classification first</option>
                                </template>
                                <template x-if="form.record_classification_id">
                                    <option value="">Select index</option>
                                </template>
                                <template x-for="option in availableIndexes" x-bind:key="option.id">
                                    <option x-bind:value="option.id" x-text="option.label"></option>
                                </template>
                            </select>
                            <p x-show="fieldError('record_index_id')" x-cloak
                               class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('record_index_id')"></p>
                        </x-ordo.field>

                        <x-ordo.field label="Document Title" required full>
                            <input type="text" name="title" maxlength="255" required x-model="form.title"
                                   aria-label="Document Title"
                                   x-bind:aria-invalid="fieldError('title') ? 'true' : 'false'"
                                   x-bind:class="fieldError('title') ? 'border-[#b42318]' : ''"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            <p x-show="fieldError('title')" x-cloak
                               class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('title')"></p>
                        </x-ordo.field>

                        <x-ordo.field label="Document Date">
                            <input type="date" name="document_date" x-model="form.document_date"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <div class="flex flex-col gap-2.5">
                            <x-ordo.field label="Source">
                                <select name="source" x-model="form.source"
                                        class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                    <option value="">Select source</option>
                                    @foreach ($sources as $option)
                                        <option value="{{ $option->name }}">{{ $option->name }}</option>
                                    @endforeach
                                    <option value="Others">Others</option>
                                </select>
                            </x-ordo.field>

                            <x-ordo.field label="Specify Source" x-show="form.source === 'Others'" x-cloak>
                                <input type="text" name="source_other" maxlength="255" x-model="form.source_other"
                                       x-bind:required="form.source === 'Others'"
                                       x-bind:aria-invalid="fieldError('source_other') ? 'true' : 'false'"
                                       x-bind:class="fieldError('source_other') ? 'border-[#b42318]' : ''"
                                       placeholder="Enter the source name"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                <p x-show="fieldError('source_other')" x-cloak
                                   class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('source_other')"></p>
                            </x-ordo.field>
                        </div>
                    </div>

                    {{-- Advanced Details stays collapsed until the index asks for it, and
                         then renders only the sections that index actually applies. --}}
                    <details class="mt-3 border-t border-ordo-line pt-2.5"
                             x-bind:open="advancedRequired"
                             x-on:toggle="if ($event.target.open) $event.target.setAttribute('data-opened-by-user', 'true')">
                        <summary class="flex cursor-pointer items-center gap-2 text-[11px] text-ordo-blue">
                            Advanced Details
                            <span class="auto-read-badge">Auto-filled</span>
                            <span class="text-[9px] text-ordo-faint" x-show="advancedRequired" x-cloak>Required by this Index</span>
                        </summary>

                        <div class="mt-3 grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                            <x-ordo.field label="External Reference / Document No.">
                                <input type="text" name="external_reference_number" maxlength="255" x-model="form.external_reference_number"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Retention Period">
                                <input type="text" name="retention_period" maxlength="50" x-model="form.retention_period"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            {{-- Only the sections the selected Index declares. An Index with no
                                 advanced_field_config shows none of them, which is the progressive
                                 disclosure AGENTS.md asks for. --}}
                            <div class="col-span-full grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1"
                                 x-show="advancedSections.length > 0">
                                @foreach ([
                                    'identifiers' => 'Identifiers',
                                    'additional_dates' => 'Additional Dates',
                                    'signatories' => 'Signatories',
                                    'financials' => 'Financials',
                                ] as $key => $label)
                                    <x-ordo.field :label="$label" x-show="advancedSectionVisible('{{ $key }}')">
                                        <textarea name="{{ $key }}[]" rows="3" x-model="form.{{ $key }}"
                                                  placeholder="One value per line"
                                                  x-bind:aria-invalid="fieldError('{{ $key }}') ? 'true' : 'false'"
                                                  x-bind:class="fieldError('{{ $key }}') ? 'border-[#b42318]' : ''"
                                                  class="min-h-[74px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                                        <p x-show="fieldError('{{ $key }}')" x-cloak
                                           class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('{{ $key }}')"></p>
                                    </x-ordo.field>
                                @endforeach
                            </div>

                            <x-ordo.field label="Confidential" full>
                                <label class="flex items-center gap-2 text-[12px] text-[#374151]">
                                    <input type="checkbox" name="is_confidential" value="1" x-model="form.is_confidential"
                                           class="size-4 rounded border-ordo-line" />
                                    Restrict this record to authorised users.
                                </label>
                            </x-ordo.field>
                        </div>
                    </details>

                    <div class="mt-4 flex items-center gap-2 rounded-lg border border-ordo-blue-border bg-ordo-blue-soft px-3 py-2.5 text-[10px] text-ordo-blue">
                        <span aria-hidden="true">◆</span>
                        Filing assigns the Document Reference and consumes 1 Record Unit.
                        Supporting files, versions and record links add storage only.
                    </div>
                </form>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line bg-white px-[22px] py-3.5">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                    <x-ordo.button type="button" variant="soft"
                                   x-bind:disabled="! hasNext()"
                                   x-on:click="openNext()">Classify &amp; Next</x-ordo.button>
                    <x-ordo.button type="submit" variant="primary" form="classify-drawer-form"
                                   x-bind:disabled="! canSubmit()">Classify &amp; File</x-ordo.button>
                </div>
            </section>
        </div>
    </div>
</div>
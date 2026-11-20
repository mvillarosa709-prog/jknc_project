<div x-data="intakeDetails(@js($drawerItems))"
     x-on:ordo:intake-details-open.window="openFor($event.detail?.id)"
     x-on:keydown.escape.window="if (open) close()">
    <template x-if="open && current">
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-[rgba(15,23,42,.28)] p-2.5"
             x-on:click.self="close()">
            <section class="flex max-h-[92vh] w-full max-w-[980px] flex-col overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
                     role="dialog" aria-modal="true" aria-labelledby="intake-details-title">
                <header class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <div class="min-w-0">
                        <h2 id="intake-details-title" class="m-0 text-[14px] font-bold">Document Details</h2>
                        <p class="mt-1 mb-0 truncate text-[10px] text-ordo-muted" x-text="current.title"></p>
                    </div>
                    <button type="button" class="text-[20px] text-ordo-muted" x-on:click="close()" aria-label="Close">&times;</button>
                </header>

                <div class="grid min-h-0 flex-1 grid-cols-2 gap-4 overflow-auto p-4 max-[760px]:grid-cols-1">
                    <div class="min-h-[320px] overflow-hidden rounded-lg border border-ordo-line bg-[#f8fafc]">
                        <template x-if="currentFile?.renderedUrl">
                            <iframe x-bind:src="currentFile.renderedUrl" title="Uploaded document preview"
                                    sandbox="" class="h-full min-h-[420px] w-full bg-white"></iframe>
                        </template>
                        <template x-if="currentFile?.isPdf">
                            <iframe x-bind:src="currentFile.previewUrl + '#toolbar=1&navpanes=0'"
                                    title="Uploaded document preview" class="h-full min-h-[420px] w-full"></iframe>
                        </template>
                        <template x-if="currentFile?.isImage">
                            <img x-bind:src="currentFile.previewUrl" x-bind:alt="currentFile.name"
                                 class="h-full max-h-[560px] w-full object-contain" />
                        </template>
                        <template x-if="currentFile?.isText">
                            <iframe x-bind:src="currentFile.previewUrl" title="Uploaded document preview"
                                    sandbox="" class="h-full min-h-[420px] w-full"></iframe>
                        </template>
                        <template x-if="currentFile && ! currentFile.renderedUrl && ! currentFile.isPdf && ! currentFile.isImage && ! currentFile.isText">
                            <div class="grid min-h-[320px] place-items-center p-6 text-center">
                                <div>
                                    <strong class="block text-[12px]">Preview not available</strong>
                                    <p class="mt-2 text-[10px] text-ordo-muted">Download the original file to view this format.</p>
                                    <dl class="mx-auto mt-3 grid max-w-sm grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-left">
                                        <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">File</dt>
                                        <dd class="m-0 break-all text-[10px] text-[#475467]" x-text="currentFile.name"></dd>
                                        <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">Extension</dt>
                                        <dd class="m-0 text-[10px] text-[#475467]" x-text="currentFile.extension || '—'"></dd>
                                        <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">Size</dt>
                                        <dd class="m-0 text-[10px] text-[#475467]" x-text="humanSize(currentFile.sizeBytes)"></dd>
                                        <dt class="text-[9px] uppercase tracking-[0.04em] text-ordo-faint">MIME type</dt>
                                        <dd class="m-0 break-all text-[10px] text-[#475467]" x-text="currentFile.mimeType || 'Unknown'"></dd>
                                    </dl>
                                    <a x-bind:href="currentFile.downloadUrl" class="mt-3 inline-flex rounded-lg border border-ordo-line bg-white px-3 py-2 text-[10px] text-[#374151]">
                                        Download Original
                                    </a>
                                </div>
                            </div>
                        </template>
                        <template x-if="! currentFile">
                            <div class="grid min-h-[320px] place-items-center text-[11px] text-ordo-muted">No file is attached.</div>
                        </template>
                    </div>

                    <div class="space-y-4">
                        <dl class="grid grid-cols-[minmax(105px,auto)_1fr] gap-x-3 gap-y-3 text-[13px]">
                            <dt class="font-medium text-ordo-muted">Intake ID</dt>
                            <dd class="m-0 break-all font-mono text-ordo-ink" x-text="current.id"></dd>
                            <dt class="font-medium text-ordo-muted">Document title</dt>
                            <dd class="m-0 break-words text-ordo-ink" x-text="current.title || '—'"></dd>
                            <dt class="font-medium text-ordo-muted">Document date</dt>
                            <dd class="m-0 text-ordo-ink" x-text="current.documentDate || '—'"></dd>
                            <dt class="font-medium text-ordo-muted">Source</dt>
                            <dd class="m-0 text-ordo-ink" x-text="current.source || '—'"></dd>
                            <dt class="font-medium text-ordo-muted">External reference</dt>
                            <dd class="m-0 text-ordo-ink" x-text="current.externalReference || '—'"></dd>
                            <dt class="font-medium text-ordo-muted">OCR status</dt>
                            <dd class="m-0 text-ordo-ink" x-text="currentFile?.ocrStatusLabel || 'Queued'"></dd>
                        </dl>

                        <section class="rounded-lg border border-ordo-line bg-[#fbfcfe] p-3.5">
                            <h3 class="m-0 text-[14px] font-semibold text-ordo-ink">Extracted text (OCR)</h3>
                            <p class="mb-3 mt-1 text-[12px] leading-5 text-ordo-muted">
                                OCR (Optical Character Recognition) reads text from the uploaded document. The result appears here after processing is complete.
                            </p>
                            <div class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-md bg-white p-3 text-[14px] leading-6 text-ordo-ink"
                                 x-text="currentFile?.ocrText || currentFile?.ocrError || (currentFile?.ocrStatus === 'queued' ? 'Waiting for the OCR worker to process this document.' : currentFile?.ocrStatus === 'reading' ? 'OCR is processing this document…' : currentFile?.ocrStatus === 'needs_review' || currentFile?.ocrStatus === 'unsupported' ? 'OCR could not read this document. It can still be reviewed and filed manually.' : 'No text has been extracted yet.')"></div>
                        </section>
                    </div>
                </div>

                <footer class="flex justify-end gap-2 border-t border-ordo-line px-4 py-3">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Close</x-ordo.button>
                    <x-ordo.button type="button" variant="primary"
                                   x-on:click="$dispatch('ordo:classify-open', { id: current.id }); close()">
                        Classify
                    </x-ordo.button>
                </footer>
            </section>
        </div>
    </template>
</div>

{{-- Extracted Data: what ORDO read from the document, beside the confirmed record value. --}}
<div x-show="activeTab === 'extracted'" x-cloak>
    <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5">
        <div class="min-w-0">
            <div class="text-[10px] font-semibold text-[#4b5563]">Extracted Data</div>
            <div class="mt-0.5 text-[9px] leading-[1.45] text-[#98a2b3]">
                ORDO keeps the original detected values separately from the human-confirmed record information.
            </div>
        </div>

        {{-- The reference puts a re-read control here as well as in the Document
             Holder. Same handler, so the reader can start a re-read from whichever
             pane they happen to be reading in. --}}
        <x-ordo.button type="button" variant="soft"
                       class="shrink-0 whitespace-nowrap"
                       x-bind:disabled="reReading || ! master"
                       x-on:click="reRead()">Re-read Document</x-ordo.button>
    </div>

    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-[#fafbfc]">
                <th class="border-b border-ordo-line px-2.5 py-2 text-left text-[8px] uppercase tracking-[0.04em] text-[#98a2b3]">Field</th>
                <th class="border-b border-ordo-line px-2.5 py-2 text-left text-[8px] uppercase tracking-[0.04em] text-[#98a2b3]">Detected by ORDO</th>
                <th class="border-b border-ordo-line px-2.5 py-2 text-left text-[8px] uppercase tracking-[0.04em] text-[#98a2b3]">Confirmed Record Value</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="row in comparison" x-bind:key="row.label">
                <tr>
                    <td class="w-[24%] border-b border-ordo-line px-2.5 py-2 align-top text-[9px] font-bold text-[#475467]" x-text="row.label"></td>
                    <td class="border-b border-ordo-line px-2.5 py-2 align-top text-[9px] text-ordo-muted" x-text="row.detected"></td>
                    <td class="border-b border-ordo-line px-2.5 py-2 align-top text-[9px] text-ordo-ink" x-text="row.confirmed"></td>
                </tr>
            </template>
        </tbody>
    </table>

    <template x-if="extraDetails.length > 0">
        <div class="mt-3.5 rounded-xl border border-ordo-line bg-white p-3">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h4 class="m-0 text-[11px] font-bold">Other Detected / Confirmed Details</h4>
                <span class="text-[9px] text-ordo-muted" x-text="extraDetails.length + ' fields'"></span>
            </div>
            <div class="flex flex-col">
                <template x-for="(value, key) in extraDetails" x-bind:key="key">
                    <div class="system-row">
                        <div class="meta-label" x-text="key"></div>
                        <div class="text-right text-[10px]" x-text="value"></div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <div class="mt-3.5 rounded-xl border border-ordo-line bg-white p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h4 class="m-0 text-[11px] font-bold">Searchable Document Text</h4>
            <span class="text-[9px] text-ordo-muted"
                  x-text="master && master.ocrText ? master.ocrText.length + ' characters' : 'Not available'"></span>
        </div>
        <template x-if="master && master.ocrText">
            <pre class="max-h-[300px] overflow-auto whitespace-pre-wrap rounded-lg border border-ordo-line bg-ordo-bg p-3 font-mono text-[11px] leading-[1.55]"
                 x-text="master.ocrText"></pre>
        </template>
        <template x-if="! master || ! master.ocrText">
            <x-ordo.empty message="No searchable text is available for this document." />
        </template>

        {{-- Copying extracted text is safe; editing it is a separate decision and
             is not offered here. --}}
        <template x-if="master && master.ocrText">
            <div class="mt-2.5 flex justify-start">
                <x-ordo.button type="button" variant="default" x-on:click="copyOcrText()">
                    <span x-show="! copiedOcr && ! copyFailed">Copy Text</span>
                    <span x-show="copiedOcr" x-cloak>Copied</span>
                    <span x-show="copyFailed" x-cloak
                          title="Copying needs a secure (https) origin.">Copy unavailable</span>
                </x-ordo.button>
            </div>
        </template>
    </div>
</div>
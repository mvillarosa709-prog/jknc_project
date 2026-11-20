{{-- Overview: the confirmed, filed identity of this record. --}}
<div x-show="activeTab === 'overview'" x-cloak>
    <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5">
        <div class="min-w-0">
            <div class="text-[10px] font-semibold text-[#4b5563]">Document Reference</div>
            <div class="mt-0.5 text-[9px] leading-[1.45] text-[#98a2b3]">
                Unique 5-character uppercase ORDO reference for this document.
            </div>
        </div>
        <span class="shrink-0 rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]"
              x-text="current ? (current.documentReference ?? '—') : '—'"></span>
    </div>

    <div class="mb-4 grid grid-cols-3 gap-2 max-[1180px]:grid-cols-1">
        <div class="auto-meta">
            <span>Pages</span>
            <strong x-text="master && master.pageCount ? master.pageCount : '—'"></strong>
        </div>
        <div class="auto-meta">
            <span>Storage</span>
            <strong x-text="current ? humanSize(current.storageBytes) : '—'"></strong>
        </div>
        <div class="auto-meta">
            <span>Linked To</span>
            <strong x-text="current ? current.linkCount : '—'"></strong>
        </div>
    </div>

    <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5">
        <div class="min-w-0">
            <div class="text-[10px] font-semibold text-[#4b5563]">Record Origin</div>
            <div class="mt-0.5 text-[9px] leading-[1.45] text-[#98a2b3]">
                Stored for future module wiring, usage metering and audit.
            </div>
        </div>
        <div class="flex shrink-0 gap-1.5">
            <span class="rounded-full bg-[#f2f4f7] px-[7px] py-1 text-[8px] font-bold text-[#475467]">Uploaded</span>
            <span class="rounded-full bg-[#f2f4f7] px-[7px] py-1 text-[8px] font-bold text-[#475467]">Records Management</span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
        <x-ordo.field label="Record ID">
            <input type="text" disabled x-bind:value="current ? current.id : ''" />
        </x-ordo.field>
        <x-ordo.field label="Status">
            <input type="text" disabled x-bind:value="current ? current.statusLabel : ''" />
        </x-ordo.field>
        <x-ordo.field label="Document Title" full>
            <input type="text" disabled x-bind:value="current ? current.title : ''" />
        </x-ordo.field>
        <x-ordo.field label="Classification" full>
            <input type="text" disabled x-bind:value="current ? current.classificationLabel : ''" />
        </x-ordo.field>
        <x-ordo.field label="Index" full>
            <input type="text" disabled x-bind:value="current ? current.indexLabel : ''" />
        </x-ordo.field>
        <x-ordo.field label="Document Date">
            <input type="text" disabled x-bind:value="dash(current ? current.documentDate : null)" />
        </x-ordo.field>
        <x-ordo.field label="Source">
            <input type="text" disabled x-bind:value="dash(current ? current.source : null)" />
        </x-ordo.field>
        <x-ordo.field label="External Reference No.">
            <input type="text" disabled x-bind:value="dash(current ? current.externalReference : null)" />
        </x-ordo.field>
        <x-ordo.field label="Amount">
            <input type="text" disabled x-bind:value="current ? amount() : '—'" />
        </x-ordo.field>
        <x-ordo.field label="Notes" full>
            <textarea disabled x-bind:value="current ? (current.notes ?? '') : ''"></textarea>
        </x-ordo.field>
    </div>

    <div class="mt-4 grid grid-cols-4 gap-2 max-[1180px]:grid-cols-2">
        <div class="auto-meta">
            <span>Record Unit</span>
            <strong x-text="current ? current.recordUnits : '—'"></strong>
        </div>
        <div class="auto-meta">
            <span>Billable Status</span>
            <strong>Counted</strong>
        </div>
        <div class="auto-meta">
            <span>File Role</span>
            <strong>Master File</strong>
        </div>
        <div class="auto-meta">
            <span>Reading Status</span>
            <strong x-text="master ? master.ocrStatusLabel : '—'"></strong>
        </div>
    </div>

    {{-- Up to three, as the reference does: enough to show the shape of the
         connections without turning the tab into a link list. The Links tab is
         one click away for the rest. --}}
    <template x-if="current && current.linkCount > 0">
        <div class="mt-4 rounded-[10px] border border-ordo-line bg-white p-3.5">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="m-0 text-[12px] font-bold">Linked Across ORDO</h3>
                <span class="text-[10px] text-ordo-faint"
                      x-text="current.linkCount + ' connection' + (current.linkCount === 1 ? '' : 's')"></span>
            </div>

            <div class="flex flex-col">
                <template x-for="link in current.links.slice(0, 3)" x-bind:key="link.id">
                    <div class="py-[5px] text-[10px]">
                        <b x-text="link.module"></b>
                        &middot;
                        <span x-text="link.linkedLabel || link.linkedId || 'Linked item'"></span>
                    </div>
                </template>
            </div>

            <x-ordo.button type="button" variant="default" class="mt-2"
                           x-on:click="activeTab = 'links'">View All Links</x-ordo.button>
        </div>
    </template>
</div>
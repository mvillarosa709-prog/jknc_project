{{-- Linked To: the record's record_links, with Add Link and Unlink wired to RecordLinkService. --}}
<div x-show="activeTab === 'links'" x-cloak>
    <div class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5">
        <div class="min-w-0">
            <div class="text-[10px] font-semibold text-[#4b5563]">Linked To</div>
            <div class="mt-0.5 text-[9px] leading-[1.45] text-[#98a2b3]">
                Relationships connect this master record to ORDO modules, other records and external
                destinations without duplicating the file.
            </div>
        </div>
        <x-ordo.button type="button" variant="primary"
                       x-bind:disabled="! current || archived"
                       x-on:click="openPicker()"
                       class="shrink-0 whitespace-nowrap">+ Add Link</x-ordo.button>
    </div>

    {{-- A link never copies a file, so neither side gains storage or a Record Unit. --}}
    <div class="mb-3 flex items-center gap-2 rounded-lg border border-ordo-blue-border bg-[#f4f7ff] px-3 py-2 text-[9px] leading-[1.5] text-[#315fce]">
        <span class="shrink-0 font-semibold">0 Record Units &middot; 0 bytes</span>
        <span>Unlinking removes the link only. No file, Document Reference or Record Unit is affected.</span>
    </div>

    <template x-for="link in (current ? current.links : [])" x-bind:key="link.id">
        <div class="mb-2 grid grid-cols-[42px_1fr_auto] items-center gap-3 rounded-[10px] border border-ordo-line bg-white px-3 py-3 max-[520px]:grid-cols-[42px_1fr]">
            <div class="grid size-[38px] place-items-center rounded-[9px] bg-ordo-blue-soft text-[12px] font-bold text-ordo-blue"
                 x-text="initials(link.module)"></div>
            <div class="min-w-0">
                <strong class="block text-[11px]" x-text="link.linkedLabel || link.module"></strong>
                <small class="mt-0.5 block text-[9px] text-[#98a2b3]">
                    <span x-text="link.module"></span> &middot;
                    <span x-text="link.linkTypeLabel"></span>
                </small>
                <small class="mt-0.5 block text-[9px] text-[#98a2b3]">
                    <span x-text="link.linkedId"></span>
                </small>
                <small class="mt-0.5 block text-[9px] text-[#98a2b3]">
                    Linked <span x-text="link.createdAt"></span>
                </small>
                <template x-if="link.notes">
                    <small class="mt-1 block text-[9px] leading-[1.5] text-[#6b7280]" x-text="link.notes"></small>
                </template>
            </div>
            <form method="POST" x-bind:action="unlinkAction(link.id)"
                  class="max-[520px]:col-span-2 max-[520px]:justify-self-end">
                @csrf
                @method('DELETE')
                <x-ordo.button type="submit" variant="danger"
                               x-bind:disabled="archived">Unlink</x-ordo.button>
            </form>
        </div>
    </template>

    {{-- Two sentences, as the reference: the absence, then what can be done about
         it. A single "nothing here" reads as a dead end. --}}
    <template x-if="! current || current.links.length === 0">
        <x-ordo.empty>
            This record is not yet linked anywhere else.<br><br>
            The same master record can be linked to ORDO modules, other records, or an
            external party or system without duplicating the document.
        </x-ordo.empty>
    </template>
</div>
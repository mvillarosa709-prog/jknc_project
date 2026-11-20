{{--
    Global Record Picker.

    Reached from the Linked To tab's "+ Add Link" button. Searches the active
    company's records, or takes an ORDO module object when the linked thing does not
    live in Records yet.

    Like the archive and edit-metadata modals, this posts a plain form and redirects.
    Its own Alpine root so $refs resolve and Escape only closes this modal.
--}}
{{-- Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it. --}}
<div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.28)]" id="recordPickerModal"
     x-data="recordPicker(@js([
         'sourceId' => (string) $record->id,
         'sourceRef' => (string) $record->document_reference,
         'linkedIds' => $linkedIds,
         'candidates' => $candidates,
     ]))"
     x-on:ordo:link-open.window="openFor($event.detail?.id)"
     x-on:keydown.escape.window="if (open) close()"
     x-on:click.self="close()"
     x-show="open" x-cloak>

    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.self="close()">
        <div class="w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="record-picker-title"
             x-show="open" x-cloak
             x-on:click.stop>

            <form method="POST" x-bind:action="action()">
                @csrf

                <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <div>
                        <h3 id="record-picker-title" class="m-0 text-[14px] font-bold">Link a Record</h3>
                        <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                            Connect <span class="font-mono font-bold text-ordo-blue" x-text="sourceRef"></span>
                            to another record, or to an object in another ORDO module.
                        </p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                {{-- The storage guarantee, stated before anything is chosen. --}}
                <div class="px-4 pt-4">
                    <div class="mb-3 rounded-lg border border-l-[3px] border-ordo-blue bg-[#f8faff] px-3 py-2.5 text-[10px] leading-[1.6] text-[#475467]">
                        <b>No file is copied.</b>
                        A link only points one record at another. It adds
                        <b>0 Record Units</b> and <b>0 storage bytes</b> to both sides, and each record
                        keeps its own Document Reference and master file. The relationship is written on
                        both records, so it reads correctly from either side.
                    </div>
                </div>

                <div class="px-4 pb-4">
                    <div class="mb-2 flex gap-1.5" role="tablist" aria-label="Link target">
                        <button type="button" role="tab"
                                x-bind:aria-selected="mode === 'record'"
                                x-on:click="mode = 'record'"
                                x-bind:class="mode === 'record' ? 'border-ordo-blue bg-ordo-blue-soft text-ordo-blue' : 'border-ordo-line bg-white text-ordo-muted'"
                                class="rounded-lg border px-3 py-1.5 text-[11px] font-semibold transition-colors">
                            Another Record
                        </button>
                        <button type="button" role="tab"
                                x-bind:aria-selected="mode === 'module'"
                                x-on:click="mode = 'module'"
                                x-bind:class="mode === 'module' ? 'border-ordo-blue bg-ordo-blue-soft text-ordo-blue' : 'border-ordo-line bg-white text-ordo-muted'"
                                class="rounded-lg border px-3 py-1.5 text-[11px] font-semibold transition-colors">
                            ORDO Module Object
                        </button>
                    </div>

                    <x-ordo.field label="Relationship"
                                  help="Written from this record's side. The linked record gets the inverse, automatically.">
                        <select name="link_type" x-model="linkType"
                                class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                            @foreach ($relationships as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ordo.field>

                    {{-- Record target: a server-rendered candidate list, filtered client-side. --}}
                    <div class="mt-3" x-show="mode === 'record'">
                        <x-ordo.field label="Record" required>
                            <input type="search" x-model="query"
                                   placeholder="Search by Doc Ref, title, external reference..."
                                   aria-label="Search records to link"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <div class="mt-2 max-h-[240px] overflow-auto rounded-lg border border-ordo-line">
                            <template x-for="candidate in visibleCandidates()" x-bind:key="candidate.id">
                                <label class="flex cursor-pointer items-center gap-2.5 border-b border-[#eef0f3] px-3 py-2.5 last:border-b-0 hover:bg-[#fafbfc]"
                                       x-bind:class="isLinked(candidate.id) ? 'opacity-55' : ''">
                                    <input type="radio" name="target" x-bind:value="candidate.id"
                                           x-bind:disabled="isLinked(candidate.id)"
                                           x-model="target" />
                                    <span class="grid size-[30px] shrink-0 place-items-center rounded-[8px] bg-ordo-blue-soft text-[10px] font-bold text-ordo-blue"
                                          x-text="initials(candidate.documentReference)"></span>
                                    <span class="min-w-0 flex-1">
                                        <strong class="block truncate text-[11px]" x-text="candidate.title"></strong>
                                        <small class="mt-0.5 block truncate text-[9px] text-[#98a2b3]">
                                            <span x-text="candidate.documentReference"></span>
                                            <span x-show="candidate.indexLabel"> &middot; <span x-text="candidate.indexLabel"></span></span>
                                            <span x-show="isLinked(candidate.id)"> &middot; Already linked</span>
                                        </small>
                                    </span>
                                </label>
                            </template>

                            <template x-if="visibleCandidates().length === 0">
                                <div class="px-3 py-6 text-center text-[10px] leading-[1.6] text-ordo-faint">
                                    No other record in this company matches that search.
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Module target: name an ORDO module or pick a common destination like Transmittal. --}}
                    <div class="mt-3 grid grid-cols-2 gap-2.5 max-[560px]:grid-cols-1" x-show="mode === 'module'">
                        <div class="col-span-full">
                            <span class="text-[10px] font-semibold text-ordo-muted block mb-1">Common Destinations</span>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" class="rounded-md border border-ordo-line bg-[#f8faff] hover:border-ordo-blue px-2 py-1 text-[10px] text-ordo-blue"
                                        x-on:click="module = 'Transmittal'; referenceId = referenceId || 'TR-{{ date('Y') }}-001'; linkedLabel = linkedLabel || 'Transmittal Manifest'">
                                    + Transmittal
                                </button>
                                <button type="button" class="rounded-md border border-ordo-line bg-[#f8faff] hover:border-ordo-blue px-2 py-1 text-[10px] text-ordo-blue"
                                        x-on:click="module = 'Corporate Governance'; referenceId = referenceId || 'CG-{{ date('Y') }}-001'; linkedLabel = linkedLabel || 'Board Action Item'">
                                    + Corporate Governance
                                </button>
                                <button type="button" class="rounded-md border border-ordo-line bg-[#f8faff] hover:border-ordo-blue px-2 py-1 text-[10px] text-ordo-blue"
                                        x-on:click="module = 'Compliance / BIR'; referenceId = referenceId || 'BIR-{{ date('Y') }}-001'; linkedLabel = linkedLabel || 'Tax Filing Attachment'">
                                    + Compliance / BIR
                                </button>
                                <button type="button" class="rounded-md border border-ordo-line bg-[#f8faff] hover:border-ordo-blue px-2 py-1 text-[10px] text-ordo-blue"
                                        x-on:click="module = 'Legal'; referenceId = referenceId || 'LEG-{{ date('Y') }}-001'; linkedLabel = linkedLabel || 'Contract Attachment'">
                                    + Legal
                                </button>
                            </div>
                        </div>

                        <x-ordo.field label="ORDO Module" required>
                            <input type="text" name="module" x-model="module" maxlength="60"
                                   placeholder="e.g. Transmittal or Corporate Governance"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Reference" required help="Up to 26 characters.">
                            <input type="text" name="reference_id" x-model="referenceId" maxlength="26"
                                   x-bind:placeholder="module === 'Transmittal' ? 'e.g. TR-{{ date('Y') }}-001' : 'e.g. BR-2026-0142'"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Title" full help="How the linked object is named in the Linked To list.">
                            <input type="text" name="linked_label" x-model="linkedLabel" maxlength="255"
                                   x-bind:placeholder="module === 'Transmittal' ? 'e.g. Transmittal to Bureau of Internal Revenue' : 'e.g. Board Resolution 2026-0142'"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>
                    </div>

                    <x-ordo.field label="Notes" help="Optional. Stored on the link, not on either record.">
                        <textarea name="notes" x-model="notes" rows="2" maxlength="1000"
                                  class="min-h-[58px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                    </x-ordo.field>
                </div>

                <div class="flex items-center justify-between gap-2 border-t border-ordo-line px-4 py-3">
                    <span class="rounded-full border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] text-[9px] font-semibold text-ordo-blue">
                        0 Record Units &middot; 0 bytes
                    </span>
                    <div class="flex items-center gap-2">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="submit" variant="primary" x-bind:disabled="! canSubmit()">Create Link</x-ordo.button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
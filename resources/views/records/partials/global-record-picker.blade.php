{{--
    Global Record Picker — Integration test.

    The mirror image of the record drawer's picker. That one starts from a record
    and asks what to link to it; this one starts from the company's records
    because that is how a module integrating with Records actually arrives — with
    an object in hand and a question about which Record it belongs to.

    Choosing a record reveals the same link form the drawer uses, posting to the
    same records.links.store, so the write path is not a second implementation of
    the same thing.

    Its own Alpine root, like every other modal: $refs resolve and Escape closes
    this one and nothing else.
--}}
<div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.28)]" id="globalRecordPicker"
     x-data="globalRecordPicker(@js($relationships), @js(route('records.links.store', ['record' => '__record__'])), @js($pickerRecords->map(fn ($record): array => [
         'id' => (string) $record->id,
         'title' => (string) $record->title,
         'documentReference' => (string) $record->document_reference,
         'recordNumber' => $record->record_number,
     ])->values()->all()))"
     x-on:ordo:integration-picker-open.window="show()"
     x-on:keydown.escape.window="if (open) close()"
     x-on:click.self="close()"
     x-show="open" x-cloak>

    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.stop>
        <div class="w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="global-picker-title"
             x-show="open" x-cloak>

            <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                <div>
                    <h3 id="global-picker-title" class="m-0 text-[14px] font-bold">Global Record Picker</h3>
                    <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                        Integration test. Pick any Record in this company and connect it to an ORDO
                        module object.
                    </p>
                </div>
                <button type="button"
                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                        x-on:click="close()" aria-label="Close">&times;</button>
            </div>

            {{-- The storage guarantee, stated before anything is chosen. This is
                 the claim the Integration tab makes about Additive shared module,
                 so it is worth putting in front of the reader rather than in a
                 tooltip they may never hover. --}}
            <div class="px-4 pt-4">
                <div class="mb-3 rounded-lg border border-l-[3px] border-ordo-blue bg-[#f8faff] px-3 py-2.5 text-[10px] leading-[1.6] text-[#475467]">
                    <b>No file is copied.</b>
                    A link only points one Record at another. It adds <b>0 Record Units</b> and
                    <b>0 storage bytes</b> to both sides, and each Record keeps its own master file
                    and Document Reference.
                </div>
            </div>

            <div class="px-4 pb-4">
                {{-- Step one: which record. --}}
                <template x-if="! selected">
                    <div>
                        <x-ordo.field label="Find a Record"
                                      help="Only officially filed Records can be linked. Searches the {{ count($pickerRecords) }} most recent.">
                            <input type="search" x-model="query" placeholder="Search by title, Document Reference or Record ID..."
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <div class="mt-2 max-h-[240px] overflow-auto rounded-lg border border-ordo-line">
                            <template x-for="record in matches" x-bind:key="record.id">
                                <button type="button"
                                        class="flex w-full items-center gap-2.5 border-b border-ordo-line px-3 py-2 text-left last:border-b-0 hover:bg-[#f8faff]"
                                        x-on:click="choose(record.id, record.recordNumber || record.documentReference)">
                                    <span class="inline-flex items-center rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]"
                                          x-text="record.documentReference"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[11px] text-ordo-ink" x-text="record.title"></span>
                                        <span class="block text-[9px] text-ordo-faint" x-text="record.recordNumber || 'No Record ID'"></span>
                                    </span>
                                </button>
                            </template>

                            <p class="mb-0 px-3 py-4 text-center text-[11px] text-ordo-faint" x-show="matches.length === 0" x-cloak>
                                No filed Record matches that.
                            </p>

                            @if ($pickerRecords->isEmpty())
                                <p class="mb-0 px-3 py-4 text-center text-[11px] text-ordo-faint">
                                    This company has no filed Records to link yet.
                                </p>
                            @endif
                        </div>
                    </div>
                </template>

                {{-- Step two: what to link it to. The same fields the drawer's
                     picker posts, to the same endpoint. --}}
                <form method="POST" x-show="selected" x-bind:action="action()" x-cloak>
                    @csrf

                    <div class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-ordo-line bg-[#fbfcfe] px-3 py-2">
                        <span class="min-w-0 text-[11px]">
                            Linking from <b class="font-mono text-ordo-blue" x-text="reference"></b>
                        </span>
                        <button type="button" class="shrink-0 text-[10px] text-ordo-blue"
                                x-on:click="clear()">Change</button>
                    </div>

                    <x-ordo.field label="ORDO Module" required>
                        <input type="text" name="module" required maxlength="60"
                               placeholder="e.g. Transmittal"
                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                    </x-ordo.field>

                    <x-ordo.field label="Reference / Object ID" required>
                        <input type="text" name="reference_id" required maxlength="26"
                               placeholder="e.g. TRN-00086"
                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                    </x-ordo.field>

                    <x-ordo.field label="Object Title">
                        <input type="text" name="linked_label" maxlength="255"
                               placeholder="e.g. RCBC Account Opening"
                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                    </x-ordo.field>

                    <x-ordo.field label="Relationship" required>
                        <select name="link_type" required
                                class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                            @foreach ($relationships as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ordo.field>

                    <div class="mt-3 flex items-center justify-end gap-2">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="submit" variant="primary">Create Link</x-ordo.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
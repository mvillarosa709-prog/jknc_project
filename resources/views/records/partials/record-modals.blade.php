{{--
    Edit Metadata and Archive modals for the record workspace.

    Archiving is a lifecycle transition: the record keeps its Document Reference and Record
    Unit, stays in the register, and remains searchable.
--}}
{{-- Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it. --}}
{{-- This container is a full-screen z-[60] overlay nested inside the record drawer,
     which is only z-50. Without a binding here it paints over the drawer for as long
     as the drawer is open and swallows every click aimed at the drawer underneath —
     its own click.self handler runs instead, so the drawer's Close buttons look dead
     and only the window-scoped ESC handler still reaches them. --}}
<div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.28)]"
     x-data="recordModals(@js($sourceOptions->pluck('name')->values()), @js(old()), @js($errors->getMessages()))"
     x-init="if (@js($errors->any()) && @js(request()->query('edit'))) openMetadata(@js(request()->query('edit')), true)"
     x-show="mode !== null" x-cloak
     x-on:ordo:metadata-open.window="openMetadata($event.detail?.id)"
     x-on:ordo:archive-open.window="openArchive($event.detail?.id)"
     x-on:keydown.escape.window="close()"
     x-on:click.self="close()">

    {{-- EDIT METADATA --}}
    <div class="grid h-full place-items-center overflow-auto p-2.5" x-show="mode === 'edit'" x-cloak x-on:click.stop>
        <div class="max-h-[92vh] w-full max-w-[720px] overflow-y-auto rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="edit-metadata-title">
            <form method="POST" x-bind:action="editAction()">
                @csrf
                @method('PUT')

                <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <div>
                        <h3 id="edit-metadata-title" class="m-0 text-[14px] font-bold">Edit Record Metadata</h3>
                        <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                            Classification, Index and the Document Reference are fixed at filing and cannot be changed.
                        </p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                @if ($errors->any())
                    <div class="px-4 pt-4">
                        <div class="mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]">
                            <ul class="list-inside list-disc">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="space-y-4 px-4 py-4">
                    <section>
                        <h4 class="mb-2 border-b border-ordo-line pb-1.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#344054]">Basic Document Information</h4>
                        <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                            <x-ordo.field label="Document Ref.">
                                <input type="text" disabled x-bind:value="current ? (current.documentReference ?? '—') : ''" />
                            </x-ordo.field>
                            <x-ordo.field label="Record ID">
                                <input type="text" disabled x-bind:value="current ? current.id : ''" />
                            </x-ordo.field>
                            <x-ordo.field label="Classification Code">
                                <input type="text" disabled x-bind:value="current ? (current.classificationCode ?? '') : ''" />
                            </x-ordo.field>
                            <x-ordo.field label="Index Code">
                                <input type="text" disabled x-bind:value="current ? (current.indexCode ?? '') : ''" />
                            </x-ordo.field>
                            <x-ordo.field label="Document Title" required full>
                                <input type="text" name="title" maxlength="255" required x-model="form.title"
                                       x-bind:aria-invalid="fieldError('title') ? 'true' : 'false'"
                                       x-bind:class="fieldError('title') ? 'border-[#b42318]' : ''"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                <p x-show="fieldError('title')" x-cloak class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('title')"></p>
                            </x-ordo.field>
                            <x-ordo.field label="Document Date">
                                <input type="date" name="document_date" x-model="form.document_date"
                                       x-bind:aria-invalid="fieldError('document_date') ? 'true' : 'false'"
                                       x-bind:class="fieldError('document_date') ? 'border-[#b42318]' : ''"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                <p x-show="fieldError('document_date')" x-cloak class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('document_date')"></p>
                            </x-ordo.field>
                            <x-ordo.field label="Source">
                                <select name="source" x-model="form.source"
                                        x-bind:aria-invalid="fieldError('source_other') ? 'true' : 'false'"
                                        class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                    <option value="">Select source</option>
                                    @foreach ($sourceOptions as $source)
                                        <option value="{{ $source->name }}">{{ $source->name }}</option>
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
                                <p x-show="fieldError('source_other')" x-cloak class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('source_other')"></p>
                            </x-ordo.field>
                            <x-ordo.field label="External Reference No." full>
                                <input type="text" name="external_reference_number" maxlength="255" x-model="form.external_reference_number"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>
                        </div>
                    </section>

                    <section>
                        <h4 class="mb-2 border-b border-ordo-line pb-1.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#344054]">Financial Information</h4>
                        <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                            <x-ordo.field label="Amount">
                                <input type="number" name="amount" step="0.01" min="0" x-model="form.amount"
                                       x-bind:aria-invalid="fieldError('amount') ? 'true' : 'false'"
                                       x-bind:class="fieldError('amount') ? 'border-[#b42318]' : ''"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                <p x-show="fieldError('amount')" x-cloak class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('amount')"></p>
                            </x-ordo.field>
                            <x-ordo.field label="Currency">
                                <select name="currency" x-model="form.currency"
                                        x-bind:aria-invalid="fieldError('currency') ? 'true' : 'false'"
                                        x-bind:class="fieldError('currency') ? 'border-[#b42318]' : ''"
                                        class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                    <template x-if="form.currency && !['PHP', 'USD', 'EUR', 'GBP', 'SGD', 'JPY', 'AUD', 'CAD'].includes(form.currency)">
                                        <option x-bind:value="form.currency" x-text="form.currency"></option>
                                    </template>
                                    <option value="PHP">PHP — Philippine Peso</option>
                                    <option value="USD">USD — US Dollar</option>
                                    <option value="EUR">EUR — Euro</option>
                                    <option value="GBP">GBP — Pound Sterling</option>
                                    <option value="SGD">SGD — Singapore Dollar</option>
                                    <option value="JPY">JPY — Japanese Yen</option>
                                    <option value="AUD">AUD — Australian Dollar</option>
                                    <option value="CAD">CAD — Canadian Dollar</option>
                                </select>
                                <p x-show="fieldError('currency')" x-cloak class="mt-1 text-[10px] text-[#b42318]" x-text="fieldError('currency')"></p>
                            </x-ordo.field>
                            <x-ordo.field label="Financial Details" full help="One value per line.">
                                <textarea name="financials[]" rows="3" x-model="form.financials"
                                          class="min-h-[74px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                            </x-ordo.field>
                        </div>
                    </section>

                    <section>
                        <h4 class="mb-2 border-b border-ordo-line pb-1.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#344054]">Records Management</h4>
                        <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                            <x-ordo.field label="Retention Period">
                                <input type="text" name="retention_period" maxlength="50" x-model="form.retention_period"
                                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>
                            <x-ordo.field label="Confidentiality">
                                <label class="flex items-center gap-2 text-[12px] text-[#374151]">
                                    <input type="checkbox" name="is_confidential" value="1" x-model="form.is_confidential"
                                           class="size-4 rounded border-ordo-line" />
                                    Restrict this record to authorised users.
                                </label>
                                <p class="mt-1 text-[9px] text-ordo-muted">Confidential records are hidden from users without access.</p>
                            </x-ordo.field>
                            <x-ordo.field label="Record Notes" full>
                                <textarea name="notes" rows="3" x-model="form.notes"
                                          class="min-h-[74px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                            </x-ordo.field>
                        </div>
                    </section>

                    <section>
                        <h4 class="mb-2 border-b border-ordo-line pb-1.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#344054]">Additional Information</h4>
                        <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                            @foreach ([
                                'identifiers' => 'Identifiers',
                                'additional_dates' => 'Additional Dates',
                                'signatories' => 'Signatories',
                            ] as $key => $label)
                                <x-ordo.field :label="$label" help="One value per line.">
                                    <textarea name="{{ $key }}[]" rows="3" x-model="form.{{ $key }}"
                                              class="min-h-[74px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                                </x-ordo.field>
                            @endforeach
                        </div>
                    </section>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                    <x-ordo.button type="submit" variant="primary">Save Metadata</x-ordo.button>
                </div>
            </form>
        </div>
    </div>

    {{-- ARCHIVE --}}
    <div class="grid h-full place-items-center overflow-auto p-2.5" id="archiveModal" x-show="mode === 'archive'" x-cloak x-on:click.stop>
        <div class="w-full max-w-[520px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="archive-record-title">
            <form method="POST" x-bind:action="archiveAction()">
                @csrf

                <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <div>
                        <h3 id="archive-record-title" class="m-0 text-[14px] font-bold">Archive Record</h3>
                        <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                            Archiving keeps the record in the register with its Document Reference and Record Unit intact.
                        </p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <div class="px-4 py-4">
                    <div class="mb-3 flex items-center gap-2 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5 text-[11px]">
                        <span class="shrink-0 rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]"
                              x-text="archiveRef"></span>
                        <span class="truncate text-ordo-ink" x-text="archiveTitle"></span>
                    </div>

                    <x-ordo.field label="Reason" help="Recorded on the activity log. Optional.">
                        <input type="text" name="reason" maxlength="255" x-model="reason"
                               placeholder="e.g. Superseded by a later filing"
                               class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                    </x-ordo.field>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                    <x-ordo.button type="submit" variant="danger">Archive Record</x-ordo.button>
                </div>
            </form>
        </div>
    </div>
</div>
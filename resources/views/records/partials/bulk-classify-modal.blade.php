{{--
    Bulk Classify modal.

    One Classification and one Index are applied to every selected record, and each filed
    record receives its own Document Reference and consumes 1 Record Unit. Leaving Source
    blank keeps whatever each intake item already carried.

    Selection lives in the shared `queueSelection` store so this modal and the queue table
    stay in sync without either one owning the other's markup.
--}}
{{-- Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it. --}}
<div class="fixed inset-0 z-50 bg-[rgba(15,23,42,.28)]"
     x-data="bulkClassify(@js($indexesByClassification))"
     x-on:ordo:bulk-open.window="openModal()"
     x-on:keydown.escape.window="if (open) close()"
     x-on:click.self="close()"
     x-show="open" x-cloak>
    <div class="grid h-full place-items-center overflow-auto p-2.5">
        <div class="w-full max-w-[620px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="bulk-classify-title"
             x-on:click.stop>
            <form method="POST" action="{{ route('records.unclassified.bulk') }}">
                @csrf

                <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4 bg-gray-50">
                    <div>
                        <h3 id="bulk-classify-title" class="m-0 text-base font-bold text-gray-900">Bulk Classify</h3>
                        <p class="mt-1 mb-0 text-xs text-gray-500">
                            Applies one Classification and Index to every selected record. Each filed record
                            receives its own Document Reference and uses 1 Record Unit.
                        </p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <div class="grid grid-cols-2 gap-2.5 px-4 py-4 max-[860px]:grid-cols-1">
                    <x-ordo.field label="Selected Records" full>
                        <x-ordo.pill tone="blue" x-text="$store.queueSelection.count() + ' record(s)'"></x-ordo.pill>
                    </x-ordo.field>

                    <x-ordo.field label="Classification" required full
                                  help="Select the main Records Classification Code.">
                        <select name="record_classification_id" required x-model="classificationId"
                                x-on:change="indexId = ''"
                                class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                            <option value="">Select classification</option>
                            @foreach ($classifications as $classification)
                                <option value="{{ $classification->id }}">{{ $classification->code }} — {{ $classification->name }}</option>
                            @endforeach
                        </select>
                    </x-ordo.field>

                    <x-ordo.field label="Index" required full
                                  help="Indexes are filtered automatically from the selected Classification.">
                        <select name="record_index_id" required x-model="indexId"
                                class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                            <template x-if="! classificationId">
                                <option value="">Select classification first</option>
                            </template>
                            <template x-if="classificationId">
                                <option value="">Select index</option>
                            </template>
                            <template x-for="option in availableIndexes" x-bind:key="option.id">
                                <option x-bind:value="option.id" x-text="option.code + ' — ' + option.name"></option>
                            </template>
                        </select>
                    </x-ordo.field>

                    <x-ordo.field label="Source" full
                                  help="Leave blank to keep the source already captured at upload.">
                        <select name="source" x-model="source"
                                class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                            <option value="">No change / blank</option>
                            @foreach ($sources as $option)
                                <option value="{{ $option->name }}">{{ $option->name }}</option>
                            @endforeach
                            <option value="Others">Others</option>
                        </select>
                    </x-ordo.field>

                    <x-ordo.field label="Specify Source" full x-show="source === 'Others'" x-cloak>
                        <input type="text" name="source_other" maxlength="255"
                               x-bind:required="source === 'Others'"
                               placeholder="Enter the source name"
                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                    </x-ordo.field>

                    <template x-for="id in $store.queueSelection.ids" x-bind:key="id">
                        <input type="hidden" name="items[]" x-bind:value="id" />
                    </template>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                    <x-ordo.button type="submit" variant="primary"
                                   x-bind:disabled="! $store.queueSelection.count() || ! classificationId || ! indexId">
                        Bulk Classify
                    </x-ordo.button>
                </div>
            </form>
        </div>
    </div>
</div>
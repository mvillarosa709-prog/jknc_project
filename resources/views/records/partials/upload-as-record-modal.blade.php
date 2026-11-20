{{--
    Upload as Record, from the Files tab.

    Files tab action, so the form is mounted inside the record drawer and shares its
    z-50 context rather than sitting above it.

    Unlike the other two actions this one needs more than a file: filing as a
    Record means choosing a place in the scheme and consumes one Record Unit, so
    Classification and Index are required here and optional nowhere else.
--}}
<div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.28)]"
     x-data="uploadAsRecord(@js($uploadAsRecordUrl ?? ''), @js($uploadClassifications ?? []), @js($uploadIndexesByClassification ?? []))"
     x-show="visible" x-cloak
     x-on:ordo:upload-as-record-open.window="show()"
     x-on:keydown.escape.window="if (visible) close()"
     x-on:click.self="close()">

    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.stop>
        <div class="w-full max-w-[620px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="upload-as-record-title"
             x-show="visible" x-cloak>

            <form method="POST" enctype="multipart/form-data" x-bind:action="action">
                @csrf

                <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <div>
                        <h3 id="upload-as-record-title" class="m-0 text-[14px] font-bold">Upload as Record</h3>
                        <p class="mb-0 mt-1 max-w-[440px] text-[10px] leading-[1.45] text-[#98a2b3]">
                            This file becomes a Record in its own right, with its own Document Reference
                            and Record Unit. Use it when the document needs its own classification.
                        </p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <div class="px-4 py-4">
                    <x-ordo.flash />

                    <x-ordo.field label="Document" required>
                        <input type="file" name="file" required
                               accept="{{ collect(config('ordo.document_extensions'))->map(fn ($ext) => ".{$ext}")->join(',') }}"
                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                    </x-ordo.field>

                    <div class="mt-2.5 grid grid-cols-2 gap-2.5 max-[620px]:grid-cols-1">
                        <x-ordo.field label="Classification" required>
                            <select name="record_classification_id" x-model="classificationId" required
                                    class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <option value="">Select a classification&hellip;</option>
                                <template x-for="option in classificationOptions" x-bind:key="option.id">
                                    <option x-bind:value="option.id" x-text="option.label"></option>
                                </template>
                            </select>
                        </x-ordo.field>

                        <x-ordo.field label="Index" required>
                            <select name="record_index_id" x-model="indexId" required
                                    class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <option value="">Select an index&hellip;</option>
                                <template x-for="option in availableIndexes" x-bind:key="option.id">
                                    <option x-bind:value="option.id" x-text="option.label"></option>
                                </template>
                            </select>
                        </x-ordo.field>

                        <x-ordo.field label="Document Title">
                            <input type="text" name="title" maxlength="255"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Document Date">
                            <input type="date" name="document_date"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Source">
                            <input type="text" name="source" maxlength="255"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="External Reference No.">
                            <input type="text" name="external_reference_number" maxlength="255"
                                   class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Confidential" full>
                            <label class="flex items-center gap-2 text-[12px] text-[#374151]">
                                <input type="checkbox" name="is_confidential" value="1" x-model="confidential"
                                       class="size-4 rounded border-ordo-line" />
                                Restrict this record to authorised users.
                            </label>
                        </x-ordo.field>
                    </div>

                    <div class="mt-3 rounded-lg border border-ordo-blue-border bg-ordo-blue-soft px-3 py-2.5 text-[10px] text-ordo-blue">
                        Filing assigns the Document Reference and consumes <b>1 Record Unit</b>.
                        To attach a file to this record without consuming one, use
                        <b>+ Supporting File</b> instead.
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                    <x-ordo.button type="submit" variant="primary"
                                   x-bind:disabled="! canSubmit()">Upload &amp; File</x-ordo.button>
                </div>
            </form>
        </div>
    </div>
</div>
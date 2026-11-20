{{--
    Records Management Setup → Records Classification.

    The management workspace for the scheme. This is where an admin
    adds, edits, and numbers Classifications and Indexes.
--}}
<div x-data="classificationBrowser(@js($scheme))">

    @if ($canManage)
        <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3 border-b border-ordo-line pb-3">
            <div>
                <h4 class="m-0 text-[13px] font-bold text-ordo-ink">Records Classification Scheme</h4>
                <p class="mb-0 mt-0.5 text-[11px] text-ordo-muted">Add, edit, and organize classifications and indexes.</p>
            </div>
            <x-ordo.button type="button" variant="primary"
                           x-on:click="open('classification')">+ Add Classification</x-ordo.button>
        </div>
    @endif

    <div class="mb-3.5 flex flex-wrap items-center gap-3 rounded-lg border border-ordo-line bg-[#fbfcfe] px-3 py-2">
        <input type="search" name="setup_scheme_q" x-model="query"
               placeholder="Search Classification Code, Index Code or name..."
               aria-label="Search the classification scheme"
               class="min-w-[200px] flex-1 rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[11px] outline-none focus:border-ordo-blue" />

        <span class="text-[10px] text-ordo-muted" x-text="visibleCount + ' shown'"></span>
    </div>

    {{-- The tree rendered with full editing capabilities inside Setup --}}
    <x-ordo.classification-tree :classifications="$classifications" :can-manage="$canManage" :editable="true" />

    {{-- ============ ADD CLASSIFICATION MODAL ============ --}}
    <div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
         x-show="mode === 'classification'" x-cloak
         x-on:keydown.escape.window="close()"
         x-on:click.self="close()">

        <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
            <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                 role="dialog" aria-modal="true" aria-labelledby="add-classification-title"
                 x-show="mode === 'classification'" x-cloak
                 x-on:click.stop>
                <form method="POST" action="{{ route('records.classifications.store') }}">
                    @csrf
                    <input type="hidden" name="from_setup" value="1" />

                    <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                        <div>
                            <h4 id="add-classification-title" class="m-0 text-[14px] font-bold text-ordo-ink">Add Classification</h4>
                            <p class="mb-0 mt-1 text-[11px] text-ordo-muted">
                                The code becomes the prefix every index under it is numbered from.
                            </p>
                        </div>
                        <button type="button"
                                class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                x-on:click="close()" aria-label="Close">&times;</button>
                    </div>

                    <div class="px-5 py-4">
                        <div class="grid grid-cols-2 gap-3">
                            <x-ordo.field label="Classification Code" required>
                                <input type="text" name="code" required
                                       placeholder="e.g. 301"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Classification Name" required>
                                <input type="text" name="name" required
                                       placeholder="e.g. Entity &amp; Ownership"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Description" full>
                                <textarea name="description" rows="3"
                                          placeholder="Optional brief description"
                                          class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                            </x-ordo.field>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="submit" variant="primary">Save Classification</x-ordo.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ============ EDIT CLASSIFICATION MODAL ============ --}}
    <div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
         x-show="mode === 'edit_classification'" x-cloak
         x-on:keydown.escape.window="close()"
         x-on:click.self="close()">

        <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
            <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                 role="dialog" aria-modal="true" aria-labelledby="edit-classification-title"
                 x-show="mode === 'edit_classification'" x-cloak
                 x-on:click.stop>
                <form method="POST" x-bind:action="updateClassificationAction()">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="from_setup" value="1" />

                    <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                        <div>
                            <h4 id="edit-classification-title" class="m-0 text-[14px] font-bold text-ordo-ink">Edit Classification</h4>
                            <p class="mb-0 mt-1 text-[11px] text-ordo-muted">
                                Modify classification code, name, description, or active status.
                            </p>
                        </div>
                        <button type="button"
                                class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                x-on:click="close()" aria-label="Close">&times;</button>
                    </div>

                    <div class="px-5 py-4">
                        <div class="grid grid-cols-2 gap-3">
                            <x-ordo.field label="Classification Code" required>
                                <input type="text" name="code" required
                                       x-model="editClass.code"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Classification Name" required>
                                <input type="text" name="name" required
                                       x-model="editClass.name"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Description" full>
                                <textarea name="description" rows="3"
                                          x-model="editClass.description"
                                          class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                            </x-ordo.field>

                            <div class="col-span-2 pt-1">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1"
                                           x-model="editClass.is_active"
                                           class="h-4 w-4 rounded border-gray-300 text-ordo-blue focus:ring-ordo-blue" />
                                    <span class="text-[12px] font-medium text-ordo-ink">Active (selectable for new records)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="submit" variant="primary">Save Changes</x-ordo.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ============ ADD INDEX MODAL ============ --}}
    <div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
         x-show="mode === 'index'" x-cloak
         x-on:keydown.escape.window="close()"
         x-on:click.self="close()">

        <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
            <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                 role="dialog" aria-modal="true" aria-labelledby="add-index-title"
                 x-show="mode === 'index'" x-cloak
                 x-on:click.stop>
                <form method="POST" x-bind:action="indexAction()">
                    @csrf
                    <input type="hidden" name="from_setup" value="1" />
                    <input type="hidden" name="classification" x-bind:value="parent" />

                    <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                        <div>
                            <h4 id="add-index-title" class="m-0 text-[14px] font-bold text-ordo-ink"
                                x-text="parent ? 'Add Index under ' + parent : 'Add Index'"></h4>
                            <p class="mb-0 mt-1 text-[11px] text-ordo-muted">
                                Added beneath the selected parent classification.
                            </p>
                        </div>
                        <button type="button"
                                class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                x-on:click="close()" aria-label="Close">&times;</button>
                    </div>

                    <div class="px-5 py-4">
                        <div class="mb-3 rounded-lg border border-ordo-line bg-[#f8faff] px-3.5 py-2.5 text-[11px]">
                            <div class="text-[10px] uppercase font-semibold tracking-wider text-ordo-muted">Parent Classification</div>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="shrink-0 rounded-md border border-blue-200/80 bg-blue-50 px-2 py-0.5 font-mono text-[11px] font-bold text-blue-700"
                                      x-text="parent || '—'"></span>
                                <span class="truncate font-medium text-ordo-ink" x-text="parentName(parent)"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <x-ordo.field label="Index Code" required>
                                <input type="text" name="code" required
                                       x-bind:placeholder="'e.g. ' + (parent ? parent + '-1' : '301-1')"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Index Name" required>
                                <input type="text" name="name" required
                                       placeholder="e.g. Incorporation"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Description" full>
                                <textarea name="description" rows="3"
                                          class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                            </x-ordo.field>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="submit" variant="primary" x-bind:disabled="! parent">Save Index</x-ordo.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ============ EDIT INDEX MODAL ============ --}}
    <div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
         x-show="mode === 'edit_index'" x-cloak
         x-on:keydown.escape.window="close()"
         x-on:click.self="close()">

        <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
            <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                 role="dialog" aria-modal="true" aria-labelledby="edit-index-title"
                 x-show="mode === 'edit_index'" x-cloak
                 x-on:click.stop>
                <form method="POST" x-bind:action="updateIndexAction()">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="from_setup" value="1" />

                    <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                        <div>
                            <h4 id="edit-index-title" class="m-0 text-[14px] font-bold text-ordo-ink">Edit Index</h4>
                            <p class="mb-0 mt-1 text-[11px] text-ordo-muted">
                                Modify index code, name, description, or active status.
                            </p>
                        </div>
                        <button type="button"
                                class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                x-on:click="close()" aria-label="Close">&times;</button>
                    </div>

                    <div class="px-5 py-4">
                        <div class="mb-3 rounded-lg border border-ordo-line bg-[#f8faff] px-3.5 py-2.5 text-[11px]">
                            <div class="text-[10px] uppercase font-semibold tracking-wider text-ordo-muted">Parent Classification</div>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="shrink-0 rounded-md border border-blue-200/80 bg-blue-50 px-2 py-0.5 font-mono text-[11px] font-bold text-blue-700"
                                      x-text="editIdx.parent_code || '—'"></span>
                                <span class="truncate font-medium text-ordo-ink" x-text="parentName(editIdx.parent_code)"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <x-ordo.field label="Index Code" required>
                                <input type="text" name="code" required
                                       x-model="editIdx.code"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Index Name" required>
                                <input type="text" name="name" required
                                       x-model="editIdx.name"
                                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                            </x-ordo.field>

                            <x-ordo.field label="Description" full>
                                <textarea name="description" rows="3"
                                          x-model="editIdx.description"
                                          class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                            </x-ordo.field>

                            <div class="col-span-2 pt-1">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1"
                                           x-model="editIdx.is_active"
                                           class="h-4 w-4 rounded border-gray-300 text-ordo-blue focus:ring-ordo-blue" />
                                    <span class="text-[12px] font-medium text-ordo-ink">Active (selectable for new records)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="submit" variant="primary">Save Changes</x-ordo.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

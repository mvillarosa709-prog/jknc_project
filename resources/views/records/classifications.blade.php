{{--
    The official Classification and Index scheme.

    Laid out with clear card boundaries, distinct status indicators, and
    direct actions to edit classifications and indexes as well as add indexes.
--}}
<x-layouts.app title="Classifications" active-nav="classifications">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo"
         x-data="classificationBrowser(@js($scheme), @js($errors->any() ? (old('classification') ? 'index' : 'classification') : null), @js(old('classification', '')))">

        <x-ordo.flash wrapper />

        <x-ordo.page-head title="Classifications"
                          subtitle="Browse and manage the official Records Classification and Index structure.">
            <x-slot:actions>
                @if ($canManage)
                    <x-ordo.button type="button" variant="primary"
                                   x-on:click="open('classification')">+ Add Classification</x-ordo.button>
                @endif
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-5 py-4">

            {{-- Toolbar: live search plus the size of the scheme --}}
            <div class="-mx-5 mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-ordo-line bg-[#fbfcfe] px-5 py-3">
                <div class="relative min-w-[260px] flex-1 max-w-md">
                    <input type="search" name="q" x-model="query"
                           placeholder="Search Classification Code, Index Code or name..."
                           aria-label="Search the classification scheme"
                           class="w-full rounded-lg border border-ordo-line bg-white pl-9 pr-3 py-2 text-[12px] outline-none transition-colors focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-ordo-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <span class="text-[11px] font-medium text-ordo-muted bg-white border border-ordo-line rounded-lg px-2.5 py-1.5 shadow-2xs">
                    {{ $classifications->count() }} classifications
                </span>
            </div>

            {{-- ============ THE TREE ============ --}}
            <x-ordo.classification-tree :classifications="$classifications" :can-manage="$canManage" />

            {{-- Search Miss State --}}
            <template x-if="visibleCount === 0 && query !== ''">
                <div class="mt-4 rounded-xl border border-ordo-line bg-white px-5 py-12 text-center shadow-xs">
                    <svg class="mx-auto h-8 w-8 text-ordo-muted mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-[13px] font-medium text-ordo-ink">No classification or index matches that search.</p>
                    <p class="text-[11px] text-ordo-muted mt-1">Try checking for typos or searching by another keyword or code.</p>
                </div>
            </template>

            {{-- ============ ADD CLASSIFICATION MODAL ============ --}}
            <div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
                 x-show="mode === 'classification'" x-cloak
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 x-on:keydown.escape.window="close()"
                 x-on:click.self="close()">
                <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
                    <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                         role="dialog" aria-modal="true" aria-labelledby="add-classification-title"
                         x-show="mode === 'classification'" x-cloak
                         x-transition:enter="ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         x-on:click.stop>
                        <form method="POST" action="{{ route('records.classifications.store') }}">
                            @csrf

                            <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                                <div>
                                    <h3 id="add-classification-title" class="m-0 text-[14px] font-bold text-ordo-ink">Add Classification</h3>
                                    <p class="mb-0 mt-1 max-w-[420px] text-[11px] leading-relaxed text-ordo-muted">
                                        The code becomes the prefix every index under it is numbered from.
                                    </p>
                                </div>
                                <button type="button"
                                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                        x-on:click="close()" aria-label="Close">&times;</button>
                            </div>

                            <div class="px-5 py-4">
                                <x-ordo.flash />

                                <div class="grid grid-cols-2 gap-3">
                                    <x-ordo.field label="Classification Code" required>
                                        <input type="text" name="code" maxlength="20" required
                                               value="{{ old('code') }}"
                                               placeholder="e.g. 301"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Classification Name" required>
                                        <input type="text" name="name" maxlength="255" required
                                               value="{{ old('name') }}"
                                               placeholder="e.g. Entity &amp; Ownership"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Description" full>
                                        <textarea name="description" rows="3"
                                                  placeholder="Optional brief description of this classification's purpose"
                                                  class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue">{{ old('description') }}</textarea>
                                    </x-ordo.field>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                                <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                                <x-ordo.button type="submit" variant="primary">Save Classification</x-ordo.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ============ EDIT CLASSIFICATION MODAL ============ --}}
            <div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
                 x-show="mode === 'edit_classification'" x-cloak
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 x-on:keydown.escape.window="close()"
                 x-on:click.self="close()">
                <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
                    <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                         role="dialog" aria-modal="true" aria-labelledby="edit-classification-title"
                         x-show="mode === 'edit_classification'" x-cloak
                         x-transition:enter="ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         x-on:click.stop>
                        <form method="POST" x-bind:action="updateClassificationAction()">
                            @csrf
                            @method('PUT')

                            <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                                <div>
                                    <h3 id="edit-classification-title" class="m-0 text-[14px] font-bold text-ordo-ink">Edit Classification</h3>
                                    <p class="mb-0 mt-1 max-w-[420px] text-[11px] leading-relaxed text-ordo-muted">
                                        Update code, name, description, or active status.
                                    </p>
                                </div>
                                <button type="button"
                                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                        x-on:click="close()" aria-label="Close">&times;</button>
                            </div>

                            <div class="px-5 py-4">
                                <x-ordo.flash />

                                <div class="grid grid-cols-2 gap-3">
                                    <x-ordo.field label="Classification Code" required>
                                        <input type="text" name="code" maxlength="20" required
                                               x-model="editClass.code"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Classification Name" required>
                                        <input type="text" name="name" maxlength="255" required
                                               x-model="editClass.name"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Description" full>
                                        <textarea name="description" rows="3"
                                                  x-model="editClass.description"
                                                  class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue"></textarea>
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

                            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                                <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                                <x-ordo.button type="submit" variant="primary">Save Changes</x-ordo.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ============ ADD INDEX MODAL ============ --}}
            <div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
                 x-show="mode === 'index'" x-cloak
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 x-on:keydown.escape.window="close()"
                 x-on:click.self="close()">
                <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
                    <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                         role="dialog" aria-modal="true" aria-labelledby="add-index-title"
                         x-show="mode === 'index'" x-cloak
                         x-transition:enter="ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         x-on:click.stop>
                        <form method="POST" x-bind:action="indexAction()">
                            @csrf
                            <input type="hidden" name="classification" x-bind:value="parent" />

                            <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                                <div>
                                    <h3 id="add-index-title" class="m-0 text-[14px] font-bold text-ordo-ink">Add Index</h3>
                                    <p class="mb-0 mt-1 max-w-[420px] text-[11px] leading-relaxed text-ordo-muted">
                                        Added beneath the parent classification.
                                    </p>
                                </div>
                                <button type="button"
                                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                        x-on:click="close()" aria-label="Close">&times;</button>
                            </div>

                            <div class="px-5 py-4">
                                <x-ordo.flash />

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
                                        <input type="text" name="code" maxlength="20" required
                                               value="{{ old('classification') ? old('code') : '' }}"
                                               x-bind:placeholder="'e.g. ' + (parent ? parent + '-1' : '301-1')"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Index Name" required>
                                        <input type="text" name="name" maxlength="255" required
                                               value="{{ old('classification') ? old('name') : '' }}"
                                               placeholder="e.g. Incorporation"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Description" full>
                                        <textarea name="description" rows="3"
                                                  placeholder="Optional brief description of this index"
                                                  class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue">{{ old('classification') ? old('description') : '' }}</textarea>
                                    </x-ordo.field>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                                <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                                <x-ordo.button type="submit" variant="primary"
                                               x-bind:disabled="! parent">Save Index</x-ordo.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ============ EDIT INDEX MODAL ============ --}}
            <div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
                 x-show="mode === 'edit_index'" x-cloak
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 x-on:keydown.escape.window="close()"
                 x-on:click.self="close()">
                <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
                    <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
                         role="dialog" aria-modal="true" aria-labelledby="edit-index-title"
                         x-show="mode === 'edit_index'" x-cloak
                         x-transition:enter="ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         x-on:click.stop>
                        <form method="POST" x-bind:action="updateIndexAction()">
                            @csrf
                            @method('PUT')

                            <div class="flex items-start justify-between border-b border-ordo-line px-5 py-4 bg-[#fafbfc]">
                                <div>
                                    <h3 id="edit-index-title" class="m-0 text-[14px] font-bold text-ordo-ink">Edit Index</h3>
                                    <p class="mb-0 mt-1 max-w-[420px] text-[11px] leading-relaxed text-ordo-muted">
                                        Update index code, name, description, or active status.
                                    </p>
                                </div>
                                <button type="button"
                                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6]"
                                        x-on:click="close()" aria-label="Close">&times;</button>
                            </div>

                            <div class="px-5 py-4">
                                <x-ordo.flash />

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
                                        <input type="text" name="code" maxlength="20" required
                                               x-model="editIdx.code"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Index Name" required>
                                        <input type="text" name="name" maxlength="255" required
                                               x-model="editIdx.name"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Description" full>
                                        <textarea name="description" rows="3"
                                                  x-model="editIdx.description"
                                                  class="min-h-[80px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue"></textarea>
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

                            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-5 py-3.5 bg-[#fafbfc]">
                                <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                                <x-ordo.button type="submit" variant="primary">Save Changes</x-ordo.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-layouts.app>

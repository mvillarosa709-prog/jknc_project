{{--
    The official Classification and Index scheme.

    Laid out to match the reference mock: a two-level tree where each
    Classification owns a block of rows and its Indexes nest beneath it. Search
    filters whole blocks rather than single rows, so searching an Index Code keeps
    the Classification it belongs to on screen instead of orphaning it.

    Browsing is open to anyone who can see records. The create forms are posted
    through Form Requests that enforce the same gate as the buttons below, so a
    role that cannot manage the scheme is refused server-side even if it posts
    directly.
--}}
<x-layouts.app title="Classifications" active-nav="classifications">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo"
         x-data="classificationBrowser(@js($scheme), @js($errors->any() ? (old('classification') ? 'index' : 'classification') : null), @js(old('classification', '')))">

        <x-ordo.flash wrapper />

        <x-ordo.page-head title="Classifications"
                          subtitle="Browse the official Records Classification and Index structure.">
            <x-slot:actions>
                @if ($canManage)
                    {{-- The reference mock pairs the scheme screen with a shortcut
                         into setup. Kept alongside the gear rather than instead of
                         it, so the page states its own connection to setup. --}}
                    <x-ordo.button type="button" variant="default"
                                   x-on:click="$dispatch('ordo:setup-open', { tab: 'classifications' })">Manage Classification</x-ordo.button>

                    <x-ordo.button type="button" variant="primary"
                                   x-on:click="open('classification')">+ Add Classification</x-ordo.button>
                @endif
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-4 py-4">

            {{-- Toolbar: live search plus the size of the scheme. It sits flush
                 under the header with a rule beneath it, as the reference does,
                 rather than floating as a rounded box above the tree.

                 There is deliberately no "+ Add Index" here: an Index belongs to
                 a Classification, so it is added from that Classification's row.
                 A toolbar control here opened the dialog with no parent chosen,
                 and the dialog's Save button is disabled until one is. --}}
            <div class="-mx-4 mb-3 flex flex-wrap items-center gap-3 border-b border-ordo-line bg-[#fbfcfe] px-4 py-3">
                <input type="search" name="q" x-model="query"
                       placeholder="Search Classification Code, Index Code or name..."
                       aria-label="Search the classification scheme"
                       class="min-w-[220px] flex-1 rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />

                <span class="text-[10px] text-ordo-muted">{{ $classifications->count() }} classifications</span>
            </div>

            {{-- ============ THE TREE ============
                 Shared with the setup modal, which shows the same scheme. --}}
            <x-ordo.classification-tree :classifications="$classifications" :can-manage="$canManage" />

            {{-- The tree emptied out above, so say so rather than showing nothing. --}}
            <template x-if="visibleCount === 0 && query !== ''">
                <div class="mt-3 rounded-xl border border-ordo-line bg-ordo-panel px-5 py-[46px] text-center text-[12px] text-ordo-faint shadow-ordo">
                    No classification or index matches that search.
                </div>
            </template>

            {{-- ============ ADD CLASSIFICATION ============ --}}
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
                    <div class="w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
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

                            <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5 bg-[#fafbfc]">
                                <div>
                                    <h3 id="add-classification-title" class="m-0 text-[14px] font-bold text-ordo-ink">Add Classification</h3>
                                    {{-- Kept, but styled as helper text rather than a
                                         second heading: the code/name relationship
                                         is only discoverable after a failed post
                                         otherwise. --}}
                                    <p class="mb-0 mt-1 max-w-[420px] text-[10px] leading-[1.45] text-[#98a2b3]">
                                        The code becomes the prefix every index under it is numbered from.
                                    </p>
                                </div>
                                <button type="button"
                                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                                        x-on:click="close()" aria-label="Close">&times;</button>
                            </div>

                            <div class="px-4 py-4">
                                <x-ordo.flash />

                                <div class="grid grid-cols-2 gap-2.5">
                                    <x-ordo.field label="Classification Code" required>
                                        <input type="text" name="code" maxlength="20" required
                                               value="{{ old('code') }}"
                                               placeholder="e.g. 301"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Classification Name" required>
                                        <input type="text" name="name" maxlength="255" required
                                               value="{{ old('name') }}"
                                               placeholder="e.g. Entity &amp; Ownership"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Description" full>
                                        <textarea name="description" rows="4"
                                                  class="min-h-[88px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">{{ old('description') }}</textarea>
                                    </x-ordo.field>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3 bg-[#fafbfc]">
                                <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                                <x-ordo.button type="submit" variant="primary">Save</x-ordo.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ============ ADD INDEX ============ --}}
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
                    <div class="w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-ordo-line"
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

                            <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5 bg-[#fafbfc]">
                                <div>
                                    <h3 id="add-index-title" class="m-0 text-[14px] font-bold text-ordo-ink">Add Index</h3>
                                    <p class="mb-0 mt-1 max-w-[420px] text-[10px] leading-[1.45] text-[#98a2b3]">
                                        Added beneath the classification you started from.
                                    </p>
                                </div>
                                <button type="button"
                                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                                        x-on:click="close()" aria-label="Close">&times;</button>
                            </div>

                            <div class="px-4 py-4">
                                <x-ordo.flash />

                                <div class="mb-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5 text-[11px]">
                                    <div class="text-[9px] uppercase tracking-[0.05em] text-ordo-muted">Parent Classification</div>
                                    <div class="mt-0.5 flex items-center gap-2">
                                        <span class="shrink-0 rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]"
                                              x-text="parent || '—'"></span>
                                        <span class="truncate" x-text="parentName(parent)"></span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2.5">
                                    <x-ordo.field label="Index Code" required>
                                        <input type="text" name="code" maxlength="20" required
                                               value="{{ old('classification') ? old('code') : '' }}"
                                               x-bind:placeholder="'e.g. ' + (parent ? parent + '-1' : '301-1')"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Index Name" required>
                                        <input type="text" name="name" maxlength="255" required
                                               value="{{ old('classification') ? old('name') : '' }}"
                                               placeholder="e.g. Incorporation"
                                               class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                    </x-ordo.field>

                                    <x-ordo.field label="Description" full>
                                        <textarea name="description" rows="4"
                                                  class="min-h-[88px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">{{ old('classification') ? old('description') : '' }}</textarea>
                                    </x-ordo.field>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3 bg-[#fafbfc]">
                                <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                                <x-ordo.button type="submit" variant="primary"
                                               x-bind:disabled="! parent">Save</x-ordo.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
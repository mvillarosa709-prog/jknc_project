{{--
    The official Classification and Index scheme.

    Laid out with clear card boundaries, distinct status indicators, and
    pure browsing controls. To edit or add classifications, the admin uses
    the Manage Classification settings button in the page header.
--}}
<x-layouts.app title="Classifications" active-nav="classifications">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo"
         x-data="classificationBrowser(@js($scheme))">

        <x-ordo.flash wrapper />

        <x-ordo.page-head title="Classifications"
                          subtitle="Browse the official Records Classification and Index structure.">
            <x-slot:actions>
                @if ($canManage)
                    <x-ordo.button type="button" variant="default"
                                   x-on:click="$dispatch('ordo:setup-open', { tab: 'classifications' })"
                                   class="inline-flex items-center gap-1.5 font-medium shadow-2xs">
                        <svg class="h-4 w-4 text-ordo-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Manage Classification</span>
                    </x-ordo.button>
                @endif
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-5 py-4">

            {{-- Toolbar: search + classification dropdown + Apply + Reset --}}
            <div class="-mx-5 mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-ordo-line bg-[#fbfcfe] px-5 py-3">
                <div class="flex flex-1 flex-wrap items-center gap-2">
                    <div class="relative min-w-[240px] flex-1 max-w-md">
                        <input type="search" x-model="searchQuery"
                               x-on:keydown.enter.prevent="applyFilters()"
                               placeholder="Search Classification Code, Index Code or name..."
                               aria-label="Search the classification scheme"
                               class="w-full rounded-lg border border-ordo-line bg-white pl-9 pr-3 py-2 text-xs md:text-sm outline-none transition-colors focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue" />
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-ordo-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <select x-model="selectedClassification"
                            aria-label="Filter by classification"
                            class="rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none transition-colors focus:border-ordo-blue focus:ring-1 focus:ring-ordo-blue">
                        <option value="">All Classifications</option>
                        @foreach ($classifications as $classification)
                            <option value="{{ $classification->code }}">
                                {{ $classification->code }} — {{ $classification->name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="button" x-on:click="applyFilters()" class="inline-flex items-center justify-center rounded-lg bg-[#1e293b] px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm transition hover:bg-[#0f172a] focus:outline-none focus:ring-2 focus:ring-[#1e293b] focus:ring-offset-1">Apply</button>

                    <button type="button" x-on:click="resetFilters()" class="inline-flex items-center justify-center rounded-lg bg-[#334155] px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm transition hover:bg-[#1e293b] focus:outline-none focus:ring-2 focus:ring-[#334155] focus:ring-offset-1">
                        Reset
                    </button>
                </div>

                <span class="text-[11px] font-medium text-ordo-muted bg-white border border-ordo-line rounded-lg px-2.5 py-1.5 shadow-2xs shrink-0"
                      x-text="(appliedQuery || appliedClassification) ? visibleCount + ' shown' : '{{ $classifications->count() }} classifications'">
                    {{ $classifications->count() }} classifications
                </span>
            </div>

            {{-- ============ THE TREE (Read-only browsing on main page) ============ --}}
            <x-ordo.classification-tree :classifications="$classifications" :can-manage="false" :editable="false" />

            {{-- Search Miss State --}}
            <template x-if="visibleCount === 0 && (appliedQuery !== '' || appliedClassification !== '' || query !== '')">
                <div class="mt-4 rounded-xl border border-ordo-line bg-white px-5 py-12 text-center shadow-xs">
                    <svg class="mx-auto h-8 w-8 text-ordo-muted mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-[13px] font-medium text-ordo-ink">No classification or index matches that filter.</p>
                    <p class="text-[11px] text-ordo-muted mt-1">Try checking for typos or selecting another classification.</p>
                </div>
            </template>

        </div>
    </div>
</x-layouts.app>

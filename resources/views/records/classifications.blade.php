{{--
    The official Classification and Index scheme.

    Laid out to match the reference mock: a two-level tree where each
    Classification owns a block of rows and its Indexes nest beneath it.
--}}
<x-layouts.app title="Classifications" active-nav="classifications">
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm"
         x-data="classificationBrowser(@js($scheme))">

        <x-ordo.flash wrapper />

        <x-ordo.page-head title="Classifications"
                          subtitle="Browse the official Records Classification and Index structure.">
            <x-slot:actions>
                @if ($canManage)
                    <button type="button"
                            x-on:click="$dispatch('ordo:setup-open', { tab: 'classifications' })"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                        Manage Classification
                    </button>

                    <button type="button"
                            x-on:click="open('classification')"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#2563eb] px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1d4ed8] transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + Add Classification
                    </button>
                @endif
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-5 py-4">

            {{-- Toolbar: live search plus the size of the scheme. --}}
            <div class="-mx-5 -mt-4 mb-4 flex flex-wrap items-center gap-3 border-b border-gray-200 bg-[#fbfcfe] px-5 py-3">
                <input type="search" name="q" x-model="query"
                       placeholder="Search Classification Code, Index Code or name..."
                       aria-label="Search the classification scheme"
                       class="min-w-[240px] flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />

                <span class="text-xs font-medium text-gray-500">{{ $classifications->count() }} classifications</span>
            </div>

            {{-- ============ THE TREE ============ --}}
            <x-ordo.classification-tree :classifications="$classifications" :can-manage="$canManage" />

            {{-- Empty search state --}}
            <template x-if="visibleCount === 0 && query !== ''">
                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-5 py-10 text-center text-xs text-gray-500">
                    No classification or index matches that search.
                </div>
            </template>

            {{-- ============ ADD / EDIT FORMS MODAL ============ --}}
            <div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
                 x-show="mode !== null" x-cloak
                 x-on:keydown.escape.window="close()"
                 x-on:click.self="close()">

                <div class="grid h-full place-items-center overflow-auto p-4" x-on:click.self="close()">
                    <div class="w-full max-w-[620px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)] border border-gray-200"
                         role="dialog" aria-modal="true"
                         x-show="mode !== null" x-cloak
                         x-on:click.stop>

                        {{-- Modal Header --}}
                        <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4 bg-gray-50">
                            <div>
                                <h3 class="m-0 text-base font-bold text-gray-900"
                                    x-text="mode === 'index' && parent
                                        ? 'Add Index — ' + parent
                                        : (mode === 'edit_classification'
                                            ? 'Edit Classification — ' + editClassificationData.code
                                            : (mode === 'edit_index'
                                                ? 'Edit Index — ' + editIndexData.code
                                                : 'Add Classification'))"></h3>
                                <p class="mb-0 mt-0.5 text-xs text-gray-500"
                                   x-text="mode === 'classification'
                                       ? 'Define a top-level record classification category.'
                                       : (mode === 'index'
                                           ? 'Add an index code under parent classification ' + parent + '.'
                                           : 'Update details and settings for this scheme item.')"></p>
                            </div>
                            <button type="button"
                                    class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-xl leading-none text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 focus:outline-none"
                                    x-on:click="close()" aria-label="Close">&times;</button>
                        </div>

                        {{-- ADD CLASSIFICATION / ADD INDEX FORM --}}
                        <template x-if="mode === 'classification' || mode === 'index'">
                            <form method="POST"
                                  x-bind:action="mode === 'index' ? indexAction() : @js(route('records.classifications.store'))">
                                @csrf

                                <div class="px-5 py-4">
                                    {{-- ADD CLASSIFICATION --}}
                                    <template x-if="mode === 'classification'">
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                                    Quick Fill from Standard Scheme (Optional)
                                                </label>
                                                <select x-on:change="selectStandardScheme($event.target.value)"
                                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                                    <option value="">-- Choose standard classification (or type manually below) --</option>
                                                    <option value="101">101 — Client Master File</option>
                                                    <option value="201">201 — Human Resources &amp; Personnel</option>
                                                    <option value="301">301 — Entity &amp; Ownership</option>
                                                    <option value="401">401 — BIR &amp; Tax</option>
                                                    <option value="601">601 — Regulatory &amp; Compliance</option>
                                                    <option value="701">701 — Finance</option>
                                                    <option value="801">801 — Banking</option>
                                                    <option value="901">901 — Legal</option>
                                                    <option value="1001">1001 — Operations</option>
                                                    <option value="1101">1101 — Real Property Records</option>
                                                    <option value="2001">2001 — Correspondence</option>
                                                </select>
                                            </div>

                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Classification Code <span class="text-red-500">*</span></label>
                                                    <input type="text" name="code" required x-model="newClassification.code"
                                                           placeholder="e.g. 301"
                                                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Classification Name <span class="text-red-500">*</span></label>
                                                    <input type="text" name="name" required x-model="newClassification.name"
                                                           placeholder="e.g. Entity &amp; Ownership"
                                                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                                                <textarea name="description" rows="3" x-model="newClassification.description"
                                                          placeholder="Scope and purpose of this classification..."
                                                          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ADD INDEX --}}
                                    <template x-if="mode === 'index'">
                                        <div class="space-y-3">
                                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Parent Classification</span>
                                                <div class="text-xs font-semibold text-gray-800" x-text="parent + ' — ' + parentName(parent)"></div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Index Code <span class="text-red-500">*</span></label>
                                                    <input type="text" name="code" required
                                                           x-bind:placeholder="'e.g. ' + (parent ? parent + '-1' : '301-1')"
                                                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Index Name <span class="text-red-500">*</span></label>
                                                    <input type="text" name="name" required
                                                           placeholder="e.g. Incorporation Documents"
                                                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                                                <textarea name="description" rows="3"
                                                          placeholder="What documents belong under this index..."
                                                          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-3.5 bg-gray-50">
                                    <button type="button" x-on:click="close()"
                                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">Cancel</button>
                                    <button type="submit"
                                            class="rounded-lg bg-[#2563eb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1d4ed8]">Save</button>
                                </div>
                            </form>
                        </template>

                        {{-- EDIT CLASSIFICATION FORM --}}
                        <template x-if="mode === 'edit_classification'">
                            <form method="POST" x-bind:action="updateClassificationAction()">
                                @csrf

                                <div class="px-5 py-4 space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Classification Code</label>
                                            <input type="text" disabled x-bind:value="editClassificationData.code"
                                                   class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 cursor-not-allowed" />
                                            <p class="mt-1 text-[10px] text-gray-400">Classification codes cannot be altered once created.</p>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Classification Name <span class="text-red-500">*</span></label>
                                            <input type="text" name="name" required x-model="editClassificationData.name"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                                        <textarea name="description" rows="3" x-model="editClassificationData.description"
                                                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                    </div>

                                    <div class="flex items-center gap-2 pt-1">
                                        <input type="hidden" name="is_active" value="0" />
                                        <input type="checkbox" id="edit_class_active" name="is_active" value="1"
                                               x-model="editClassificationData.is_active"
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        <label for="edit_class_active" class="text-xs font-medium text-gray-700">Active (available for record filing and indexing)</label>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-3.5 bg-gray-50">
                                    <button type="button" x-on:click="close()"
                                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="rounded-lg bg-[#2563eb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1d4ed8]">
                                        Update Classification
                                    </button>
                                </div>
                            </form>
                        </template>

                        {{-- EDIT INDEX FORM --}}
                        <template x-if="mode === 'edit_index'">
                            <form method="POST" x-bind:action="updateIndexAction()">
                                @csrf

                                <div class="px-5 py-4 space-y-3">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Parent Classification</span>
                                        <div class="text-xs font-semibold text-gray-800" x-text="editIndexData.classification_code + ' — ' + parentName(editIndexData.classification_code)"></div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Index Code</label>
                                            <input type="text" disabled x-bind:value="editIndexData.code"
                                                   class="w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 cursor-not-allowed" />
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1">Index Name <span class="text-red-500">*</span></label>
                                            <input type="text" name="name" required x-model="editIndexData.name"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                                        <textarea name="description" rows="3" x-model="editIndexData.description"
                                                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                    </div>

                                    <div class="rounded-lg border border-gray-200 bg-[#f9fafb] p-3">
                                        <label class="block text-xs font-semibold text-gray-800 mb-1.5">Advanced Details Configuration</label>
                                        <p class="text-[11px] text-gray-500 mb-2">Select which advanced drawer sections are exposed when filing documents under this Index:</p>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="checkbox" name="sections[]" value="identifiers" x-model="editIndexData.sections.identifiers"
                                                       class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                <span>Identifiers (TIN, SEC, etc.)</span>
                                            </label>
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="checkbox" name="sections[]" value="dates" x-model="editIndexData.sections.dates"
                                                       class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                <span>Dates (Filing, Expiry, etc.)</span>
                                            </label>
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="checkbox" name="sections[]" value="signatories" x-model="editIndexData.sections.signatories"
                                                       class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                <span>Signatories</span>
                                            </label>
                                            <label class="flex items-center gap-2 text-gray-700">
                                                <input type="checkbox" name="sections[]" value="financials" x-model="editIndexData.sections.financials"
                                                       class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                <span>Financials (Amounts, VAT, etc.)</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 pt-1">
                                        <input type="hidden" name="is_active" value="0" />
                                        <input type="checkbox" id="edit_idx_active" name="is_active" value="1"
                                               x-model="editIndexData.is_active"
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        <label for="edit_idx_active" class="text-xs font-medium text-gray-700">Active (available for record filing)</label>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-3.5 bg-gray-50">
                                    <button type="button" x-on:click="close()"
                                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="rounded-lg bg-[#2563eb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1d4ed8]">
                                        Update Index
                                    </button>
                                </div>
                            </form>
                        </template>

                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

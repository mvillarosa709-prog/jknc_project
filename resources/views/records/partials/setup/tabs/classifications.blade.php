{{--
    Records Management Setup → Records Classification.

    The same tree and the same add-forms as the Classifications page, rendered
    through the same browser component, so the two screens cannot drift apart.
    The mock's "+ Add Classification" and "+ Add Index" open small modals here
    too, through that component's own form handling.
--}}
<div x-data="classificationBrowser(@js($scheme))">

    @if ($canManage)
        {{-- No "+ Add Index" here either: it opened the dialog with no parent
             chosen, and that dialog's Save stays disabled until one is. An Index
             is added from the Classification it belongs under, in the tree below. --}}
        <div class="mb-3">
            <x-ordo.button type="button" variant="primary"
                           x-on:click="open('classification')">+ Add Classification</x-ordo.button>
        </div>
    @endif

    <div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-ordo-line bg-[#fbfcfe] px-3 py-2.5">
        <input type="search" name="setup_scheme_q" x-model="query"
               placeholder="Search Classification Code, Index Code or name..."
               aria-label="Search the classification scheme"
               class="min-w-[200px] flex-1 rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[11px] outline-none focus:border-ordo-blue" />

        <span class="text-[10px] text-ordo-muted" x-text="visibleCount + ' shown'"></span>
    </div>

    {{-- The same tree the Classifications page renders, so the two copies of the
         scheme cannot drift apart. It carries its own empty state. --}}
    <x-ordo.classification-tree :classifications="$classifications" :can-manage="$canManage" />

    {{-- ============ ADD FORMS ============
         The component owns which form is open; the mock presents these in a
         dialog, and this reuses the component's modal rather than a second one. --}}
    <div class="fixed inset-0 z-[70] bg-[rgba(15,23,42,.35)] backdrop-blur-sm"
         x-show="mode !== null" x-cloak
         x-on:keydown.escape.window="if (mode !== null) close()"
         x-on:click.self="close()">

        <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.self="close()">
            <div class="w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
                 role="dialog" aria-modal="true"
                 x-show="mode !== null" x-cloak
                 x-on:click.stop>

                <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <h4 class="m-0 text-[13px] font-bold"
                        x-text="mode === 'index' && parent
                            ? 'Add Index — ' + parent
                            : 'Add Classification'"></h4>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <form method="POST"
                      x-bind:action="mode === 'index' ? indexAction() : @js(route('records.classifications.store'))">
                    @csrf
                    <input type="hidden" name="from_setup" value="1" />

                    <div class="px-4 py-4">
                        @if ($errors->any())
                            <div class="mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]">
                                <ul class="list-inside list-disc">
                                    @foreach ($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <template x-if="mode === 'classification'">
                            <div class="grid grid-cols-2 gap-2.5">
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
                                    <textarea name="description" rows="4"
                                              class="min-h-[88px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                                </x-ordo.field>
                            </div>
                        </template>

                        <template x-if="mode === 'index'">
                            <div class="grid grid-cols-2 gap-2.5">
                                <x-ordo.field label="Index Code" required>
                                    <input type="text" name="code" required
                                           placeholder="e.g. 301-1"
                                           class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                </x-ordo.field>

                                <x-ordo.field label="Index Name" required>
                                    <input type="text" name="name" required
                                           class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                                </x-ordo.field>

                                <x-ordo.field label="Parent Classification" full>
                                    <input type="text" disabled
                                           x-bind:value="parent + ' — ' + parentName(parent)"
                                           class="w-full rounded-lg border border-ordo-line bg-[#f3f4f6] px-2.5 py-2 text-[12px] text-ordo-muted" />
                                </x-ordo.field>

                                <x-ordo.field label="Description" full>
                                    <textarea name="description" rows="4"
                                              class="min-h-[88px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue"></textarea>
                                </x-ordo.field>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-ordo-line px-4 py-3">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        {{-- One label for both modes: the title above already says
                             which form this is, and a Save button that changes its
                             own wording mid-dialog is harder to read. --}}
                        <x-ordo.button type="submit" variant="primary">Save</x-ordo.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{--
    The Records Management Setup modal.

    Mounted once in the layout, so every Records page reaches it through the gear
    in its page header without any page opting in.

    Tab content is fetched when a tab is opened rather than rendered up front, so
    the six tabs cost nothing on an ordinary page load and only the tab on screen
    reaches the DOM. The writes inside the tabs are ordinary form posts: they are
    server-authoritative, and the controllers redirect back with `setup_tab` to
    reopen the modal on the tab the write came from.

    Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it.
--}}
<div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.28)]"
     x-data="recordsSetup(@js($setupTabUrl), @js($setupInitialTab), @js($setupInitiallyOpen))"
     x-on:ordo:setup-open.window="openSetup($event.detail?.tab ?? null)"
     x-on:keydown.escape.window="if (open) close()"
     x-on:click.self="close()"
     x-show="open" x-cloak>

    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.stop>
        <div class="flex max-h-[92vh] w-full max-w-[880px] flex-col overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="records-setup-title"
             x-show="open" x-cloak>

            {{-- ============ HEAD ============ --}}
            <div class="flex shrink-0 items-start justify-between border-b border-ordo-line px-4 py-3.5">
                <h3 id="records-setup-title" class="m-0 text-[14px] font-bold">Records Management Setup</h3>
                <button type="button"
                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                        x-on:click="close()" aria-label="Close">&times;</button>
            </div>

            {{-- ============ BODY ============ --}}
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">

                <div class="mb-4 flex flex-wrap gap-1 border-b border-ordo-line max-[720px]:overflow-x-auto max-[720px]:flex-nowrap">
                    <template x-for="entry in tabs" x-bind:key="entry.key">
                        <button type="button"
                                class="border-0 border-b-2 border-transparent bg-transparent px-2.5 py-2 text-[11px] transition-colors"
                                x-bind:class="tab === entry.key ? 'border-ordo-blue text-ordo-blue' : 'text-[#6b7280] hover:text-ordo-ink'"
                                x-on:click="select(entry.key)"
                                x-text="entry.label"></button>
                    </template>
                </div>

                <div x-show="busy" class="py-6 text-center text-[11px] text-ordo-muted">Loading&hellip;</div>

                <div x-show="error" x-cloak
                     class="rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2.5 text-[11px] text-[#b42318]"
                     x-text="error"></div>

                {{-- The fetched tab. Role=status so the swap is announced. --}}
                <div role="status" aria-live="polite" x-html="html"></div>
            </div>

            {{-- ============ FOOT ============ --}}
            <div class="flex shrink-0 justify-end border-t border-ordo-line px-4 py-3">
                <x-ordo.button type="button" variant="default" x-on:click="close()">Close</x-ordo.button>
            </div>
        </div>
    </div>
</div>

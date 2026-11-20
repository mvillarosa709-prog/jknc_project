{{--
    Report History.

    The list is fetched when the modal is opened rather than rendered on the page
    load, so a reader who never asks to see their history pays nothing for it.

    Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it.
--}}
<div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.28)]"
     x-show="historyOpen" x-cloak
     x-on:keydown.escape.window="closeHistory()"
     x-on:click.self="closeHistory()">

    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.stop>
        <div class="flex max-h-[92vh] w-full max-w-[720px] flex-col overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="report-history-title"
             x-show="historyOpen" x-cloak>

            <div class="flex shrink-0 items-start justify-between border-b border-ordo-line px-4 py-3.5">
                <div>
                    <h3 id="report-history-title" class="m-0 text-[14px] font-bold">Report History</h3>
                    <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                        Reports this company has presented or downloaded, newest first.
                    </p>
                </div>
                <button type="button"
                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                        x-on:click="closeHistory()" aria-label="Close">&times;</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                <div class="py-6 text-center text-[11px] text-ordo-muted" x-show="historyBusy" x-cloak>Loading&hellip;</div>

                <div x-show="historyError" x-cloak
                     class="rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2.5 text-[11px] text-[#b42318]"
                     x-text="historyError"></div>

                {{-- The fetched list. Role=status so the swap is announced. --}}
                <div role="status" aria-live="polite" x-html="historyHtml"></div>
            </div>

            <div class="flex shrink-0 justify-end border-t border-ordo-line px-4 py-3">
                <x-ordo.button type="button" variant="default" x-on:click="closeHistory()">Close</x-ordo.button>
            </div>
        </div>
    </div>
</div>
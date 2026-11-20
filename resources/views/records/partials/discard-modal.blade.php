{{-- Confirmation for the discard action on an unclassified intake item. --}}
<div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.28)]" id="discardModal"
     x-data="discardModal()"
     x-on:ordo:discard-open.window="show($event.detail?.id, $event.detail?.title, $event.detail?.files, $event.detail?.bytes)"
     x-on:keydown.escape.window="if (open) close()"
     x-on:click.self="close()"
     x-show="open" x-cloak>
    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.stop>
        <div class="w-full max-w-[520px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="alertdialog" aria-modal="true" aria-labelledby="discard-intake-title"
             x-show="open" x-cloak>
            <form method="POST" x-bind:action="action()">
                @csrf
                @method('DELETE')

                <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                    <div>
                        <h3 id="discard-intake-title" class="m-0 text-[14px] font-bold">Discard Unclassified Record</h3>
                        <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                            The record leaves the staging queue and can no longer be classified or filed.
                        </p>
                    </div>
                    <button type="button"
                            class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                            x-on:click="close()" aria-label="Close">&times;</button>
                </div>

                <div class="px-4 py-4">
                    <div class="mb-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5 text-[11px]">
                        <span class="block font-semibold" x-text="title ?? '—'"></span>
                        <span class="mt-0.5 block text-[9px] text-ordo-faint" x-text="summary"></span>
                    </div>

                    <div class="rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2.5 text-[10px] leading-[1.55] text-[#b42318]">
                        <b>Nothing is deleted.</b>
                        The stored file stays on disk and the activity trail is preserved, so this
                        discard can still be audited. Only an officially filed Record can be archived,
                        and even then it keeps its Document Reference.
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                    <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                    <x-ordo.button type="submit" variant="danger">Discard Record</x-ordo.button>
                </div>
            </form>
        </div>
    </div>
</div>
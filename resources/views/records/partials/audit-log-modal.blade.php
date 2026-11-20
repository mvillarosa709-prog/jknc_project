{{--
    Audit Log with the properties drawer.

    The drawer reads a @js payload shipped with the page, so opening an entry makes
    no request. The trail itself is append-only: nothing on this page writes to it.
--}}
{{-- Visibility is owned by x-show alone; a Tailwind `hidden` class would fight it. --}}
<div class="fixed inset-0 z-[60] bg-[rgba(15,23,42,.28)]" id="auditLogModal"
     x-data="auditDrawer(@js($drawerEntries))"
     x-on:ordo:audit-open.window="openFor($event.detail?.id)"
     x-on:keydown.escape.window="if (open) close()"
     x-on:click.self="close()"
     x-show="open" x-cloak>

    <div class="grid h-full place-items-center overflow-auto p-2.5" x-on:click.stop>
        <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="audit-drawer-title"
             x-show="open" x-cloak>

            <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                <div class="min-w-0">
                    <h3 id="audit-drawer-title" class="m-0 text-[14px] font-bold" x-text="current?.action ?? 'Audit Entry'"></h3>
                    <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                        Recorded <span x-text="current?.createdAt ?? '—'"></span>
                    </p>
                </div>
                <button type="button"
                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                        x-on:click="close()" aria-label="Close">&times;</button>
            </div>

            <div class="px-4 py-4">
                <div class="rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5 text-[11px] leading-[1.55] text-ordo-ink">
                    <span x-text="current?.description ?? ''"></span>
                </div>

                <div class="mt-3 rounded-lg border border-ordo-line px-3 py-2.5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="meta-label">Properties</span>
                        <span class="text-[9px] text-ordo-faint" x-text="propertyCount() + ' field(s)'"></span>
                    </div>

                    <div class="flex flex-col gap-1.5" x-show="propertyCount() > 0">
                        <template x-for="(value, key) in current?.properties ?? {}" x-bind:key="key">
                            <div class="flex items-start justify-between gap-3 rounded-lg border border-[#e6eaf0] bg-[#fafbfc] px-3 py-2">
                                <span class="meta-label shrink-0" x-text="key"></span>
                                <strong class="text-right text-[10px] break-words" x-text="show(value)"></strong>
                            </div>
                        </template>
                    </div>

                    <p class="text-[10px] text-ordo-faint" x-show="propertyCount() === 0">
                        No additional properties were recorded with this entry.
                    </p>
                </div>

                <div class="mt-3 rounded-lg border border-ordo-blue-border bg-[#f4f7ff] px-3 py-2.5 text-[9px] leading-[1.6] text-[#315fce]">
                    <b>This trail cannot be edited.</b>
                    Audit entries are append-only by design: an entry can be added
                    but never modified or removed, so the history of a record stays
                    intact for as long as the record does.
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                <x-ordo.button type="button" variant="default" x-on:click="close()">Close</x-ordo.button>
            </div>
        </div>
    </div>
</div>
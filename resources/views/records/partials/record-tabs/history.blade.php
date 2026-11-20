{{-- History: the append-only activity log for this record, newest first. --}}
<div x-show="activeTab === 'history'" x-cloak>
    <div class="mb-3 text-[10px] text-ordo-muted">
        History is the append-only record activity log. Entries can never be edited or deleted.
    </div>

    <template x-for="entry in history" x-bind:key="entry.id">
        <div class="grid grid-cols-[150px_1fr] gap-3 border-b border-ordo-line py-2.5 max-[520px]:grid-cols-1">
            <time class="text-[9px] text-[#98a2b3]" x-text="entry.createdAt"></time>
            <div>
                <strong class="block text-[10px]" x-text="entry.action"></strong>
                <div class="mt-0.5 text-[10px]" x-text="entry.description ?? ''"></div>
                <div class="mt-0.5 text-[9px] text-ordo-muted">
                    By <span x-text="entry.user ?? 'System'"></span>
                </div>
            </div>
        </div>
    </template>

    <template x-if="history.length === 0">
        <x-ordo.empty message="No activity has been recorded for this record yet." />
    </template>
</div>
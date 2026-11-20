{{--
    Audit Log: the append-only record activity trail.

    Every entry resolves to either a filed record or the intake item that captured
    it, because intake actions log against the intake item before a record exists.
--}}
<x-layouts.app title="Audit Log" active-nav="logs">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo">
        <x-ordo.flash wrapper />

        <x-ordo.page-head title="Audit Log"
                          subtitle="Append-only activity trail across intake, filing, edits, archiving and links.">
            <x-slot:actions>
                <details class="relative">
                    <summary class="inline-flex cursor-pointer list-none items-center justify-center rounded-lg border border-ordo-line bg-ordo-panel px-3 py-2 text-[12px] text-[#374151] hover:bg-[#f9fafb]">Export</summary>
                    <div class="absolute right-0 z-10 mt-1 min-w-[150px] rounded-lg border border-ordo-line bg-white p-1 shadow-ordo">
                        <a href="{{ $csvUrl }}" class="block rounded-md px-3 py-2 text-[11px] text-ordo-ink hover:bg-[#f8fafc]">Export CSV</a>
                        <a href="{{ $pdfUrl }}" class="block rounded-md px-3 py-2 text-[11px] text-ordo-ink hover:bg-[#f8fafc]">Export PDF</a>
                    </div>
                </details>
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-4 py-4">

            <form method="GET" action="{{ route('records.logs') }}"
                  class="flex flex-wrap items-center gap-2 rounded-lg border border-ordo-line bg-[#fbfcfe] px-4 py-3">
                <input type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="Search action or description..."
                       aria-label="Search the audit trail"
                       class="min-w-[220px] flex-1 rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />

                <select name="action" aria-label="Filter by action type"
                        class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                    <option value="">All action types</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $filters['from'] }}" title="From date"
                       aria-label="From date"
                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />

                <input type="date" name="to" value="{{ $filters['to'] }}" title="To date"
                       aria-label="To date"
                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />

                <x-ordo.button type="submit" variant="default">Apply</x-ordo.button>

                <a href="{{ route('records.logs') }}" class="text-[11px] text-ordo-blue">Reset</a>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#fbfcfe] text-[10px] font-semibold text-ordo-muted">
                            <th class="px-3 py-2.5">Timestamp</th>
                            <th class="px-3 py-2.5">Action</th>
                            <th class="px-3 py-2.5">Description</th>
                            <th class="px-3 py-2.5">Subject</th>
                            <th class="px-3 py-2.5">User</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($entries as $entry)
                            @php
                                $record = $entry->record;
                                $intake = $entry->intakeItem;

                                [$subjectType, $subjectLabel, $subjectMeta] = match (true) {
                                    $record !== null => ['Record', (string) $record->title, (string) $record->document_reference],
                                    $intake !== null => ['Intake Item', (string) $intake->title, (string) $intake->id],
                                    default => ['—', 'No subject', ''],
                                };
                            @endphp

                            <tr class="border-b border-[#eef0f3] align-top">
                                <td class="whitespace-nowrap px-3 py-2.5 text-[10px] text-ordo-muted">
                                    {{ $entry->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <x-ordo.pill :tone="str_starts_with($entry->action, 'intake.') ? 'neutral' : 'blue'">
                                        {{ $entry->action }}
                                    </x-ordo.pill>
                                </td>
                                <td class="px-3 py-2.5 text-[10px] leading-[1.5] text-ordo-ink">
                                    {{ $entry->description }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="text-[9px] uppercase tracking-[0.05em] text-[#98a2b3]">{{ $subjectType }}</div>
                                    <div class="mt-0.5 max-w-[240px] truncate text-[10px]">{{ $subjectLabel }}</div>
                                    @if ($subjectMeta !== '')
                                        <div class="mt-0.5 font-mono text-[9px] text-ordo-faint">{{ $subjectMeta }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-[10px] text-ordo-muted">
                                    {{ $entry->user?->name ?? 'System' }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <button type="button"
                                            class="mini-btn"
                                            x-on:click="$dispatch('ordo:audit-open', { id: @js((string) $entry->id) })">
                                        Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-ordo.empty message="No activity matches the selected filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($entries->hasPages())
                <div class="mt-4">
                    {{ $entries->links() }}
                </div>
            @endif

            <div class="mt-4 rounded-lg border border-ordo-blue-border bg-[#f4f7ff] px-3 py-2.5 text-[9px] leading-[1.6] text-[#315fce]">
                <b>Entries can be added but never changed.</b>
                The activity trail rejects edits and deletions at the database model level, so this
                history stays intact for as long as the records it describes. Exports honour the
                filters above, so a compliance pull covers exactly the range you filtered to.
            </div>
        </div>
    </div>

    @include('records.partials.audit-log-modal')
</x-layouts.app>
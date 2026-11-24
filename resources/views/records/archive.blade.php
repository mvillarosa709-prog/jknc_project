{{--
    The archive.

    Archived records are retired from the active register, never deleted: each row
    keeps its Document Reference and its Record Unit, so the document can be found
    and cited exactly as it was. Restoring is therefore a lifecycle change and the
    button says so, rather than implying the record is being re-filed.
--}}
<x-layouts.app title="Archive" active-nav="archive">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo">
        <x-ordo.page-head title="Archive"
                          subtitle="Historical or inactive records retained as part of the official records history." />

        <div class="px-4 py-4">
            <x-ordo.flash />

            <div class="mb-3 rounded-lg border border-ordo-blue-border bg-[#f4f7ff] px-3 py-2.5 text-[9px] leading-[1.6] text-[#315fce]">
                <b>Nothing here was deleted.</b>
                An archived record keeps its Document Reference, its Record Unit and its
                activity trail, and stays counted against the company's allocation. Restoring
                returns it to the active register without renumbering or re-allocating anything.
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-gray-600">
                            <th class="px-3.5 py-3">Record ID</th>
                            <th class="px-3.5 py-3">Doc Ref</th>
                            <th class="px-3.5 py-3">Document Title</th>
                            <th class="px-3.5 py-3">Classification</th>
                            <th class="px-3.5 py-3">Index</th>
                            <th class="px-3.5 py-3">Archived On</th>
                            <th class="px-3.5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($archived as $record)
                            <tr class="border-b border-ordo-line text-xs md:text-sm">
                                <td class="px-3.5 py-3 font-mono text-xs text-gray-500">
                                    {{ $record->id }}
                                </td>

                                <td class="px-3.5 py-3">
                                    <x-ordo.docref :reference="$record->document_reference" />
                                </td>

                                <td class="max-w-[280px] truncate px-3.5 py-3 font-semibold text-gray-900">
                                    {{ $record->title }}
                                </td>

                                <td class="px-3.5 py-3 text-xs md:text-sm text-gray-600">
                                    {{ $record->classification
                                        ? sprintf('%s — %s', $record->classification->code, $record->classification->name)
                                        : '—' }}
                                </td>

                                <td class="px-3.5 py-3 text-xs md:text-sm text-gray-600">
                                    {{ $record->index
                                        ? sprintf('%s — %s', $record->index->code, $record->index->name)
                                        : '—' }}
                                </td>

                                <td class="px-3.5 py-3 text-xs md:text-sm text-gray-600">
                                    {{ $record->archived_at?->format('Y-m-d H:i') ?? '—' }}
                                </td>

                                <td class="px-3 py-2.5 text-right">
                                    <form method="POST"
                                          action="{{ route('records.restore', ['record' => $record->id]) }}">
                                        @csrf
                                        <x-ordo.button type="submit" variant="success">Restore</x-ordo.button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-ordo.empty message="No archived records." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
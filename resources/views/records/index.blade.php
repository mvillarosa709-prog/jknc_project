<x-layouts.app title="All Records" active-nav="records">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo">
        <x-ordo.page-head title="All Records"
                          subtitle="Master Records Register — all classified and active records.">
            <x-slot:actions>
                <a href="{{ route('records.unclassified') }}">
                    <x-ordo.button variant="default">Unclassified</x-ordo.button>
                </a>
                <x-ordo.button variant="primary" href="{{ route('records.intake.upload') }}">+ Upload Records</x-ordo.button>
            </x-slot:actions>
        </x-ordo.page-head>

        <x-ordo.flash wrapper />

        <div class="px-4 py-4">
            <form method="GET" action="{{ route('records.index') }}" class="flex flex-col gap-2.5 rounded-lg border border-ordo-line bg-[#fbfcfe] px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" name="q" value="{{ $filters['q'] }}"
                           placeholder="Search Doc Ref, title, Record ID, reference, source..."
                           aria-label="Search records"
                           class="min-w-[240px] flex-1 rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none focus:border-ordo-blue" />

                    <select name="classification" aria-label="Filter by classification"
                            class="rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none focus:border-ordo-blue">
                        <option value="">Select classification</option>
                        @foreach ($classifications as $classification)
                            <option value="{{ $classification->code }}" @selected($filters['classification'] === $classification->code)>
                                {{ $classification->code }} — {{ $classification->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="index" aria-label="Filter by index"
                            class="rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none focus:border-ordo-blue">
                        <option value="">Select index</option>
                        @foreach ($indexes as $index)
                            <option value="{{ $index->code }}" @selected($filters['index'] === $index->code)>
                                {{ $index->code }} — {{ $index->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select name="source" aria-label="Filter by source"
                            class="rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none focus:border-ordo-blue">
                        <option value="">All Sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->name }}" @selected($filters['source'] === $source->name)>{{ $source->name }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ $filters['from'] }}" title="Document date from"
                           aria-label="Document date from"
                           class="rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none focus:border-ordo-blue" />

                    <input type="date" name="to" value="{{ $filters['to'] }}" title="Document date to"
                           aria-label="Document date to"
                           class="rounded-lg border border-ordo-line bg-white px-3 py-2 text-xs md:text-sm outline-none focus:border-ordo-blue" />

                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#1e293b] px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm transition hover:bg-[#0f172a] focus:outline-none focus:ring-2 focus:ring-[#1e293b] focus:ring-offset-1">Apply</button>

                    <a href="{{ route('records.index') }}" class="inline-flex items-center justify-center rounded-lg bg-[#334155] px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm transition hover:bg-[#1e293b] focus:outline-none focus:ring-2 focus:ring-[#334155] focus:ring-offset-1">Reset</a>
                </div>
            </form>

            <div class="overflow-x-auto"
                 x-data="recordRegister(@js($records->pluck('id')->all()))"
                 x-on:ordo:record-open.window="open($event.detail?.id)">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-gray-600">
                            <th class="px-3.5 py-3">Doc Ref</th>
                            <th class="px-3.5 py-3">Record ID</th>
                            <th class="px-3.5 py-3">Document Title</th>
                            <th class="px-3.5 py-3">Classification</th>
                            <th class="px-3.5 py-3">Index</th>
                            <th class="px-3.5 py-3">Source</th>
                            <th class="px-3.5 py-3">Document Date</th>
                            <th class="px-3.5 py-3">Amount</th>
                            <th class="px-3.5 py-3">Origin</th>
                            <th class="px-3.5 py-3">OCR</th>
                            <th class="px-3.5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            @php
                                $master = $record->files->first();
                                $searchable = $record->files->contains(fn ($file) => filled($file->ocr_text));
                            @endphp
                            <tr class="cursor-pointer border-t border-ordo-line text-xs md:text-sm text-ordo-blue hover:bg-[#fcfdff] transition-colors"
                                tabindex="0"
                                x-on:click="open('{{ $record->id }}')"
                                x-on:keydown.enter="open('{{ $record->id }}')">
                                <td class="px-3.5 py-3">
                                    <x-ordo.docref :reference="$record->document_reference" />
                                </td>
                                <td class="px-3.5 py-3 font-mono text-xs text-gray-500">{{ $record->id }}</td>
                                <td class="max-w-[300px] truncate px-3.5 py-3 font-semibold text-gray-900">{{ $record->title }}</td>
                                <td class="px-3.5 py-3 text-gray-700">
                                    {{ $record->classification?->code }}
                                    @if ($record->classification)
                                        <span class="text-gray-500">&mdash; {{ $record->classification->name }}</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-gray-700">
                                    {{ $record->index?->code }}
                                    @if ($record->index)
                                        <span class="text-gray-500">&mdash; {{ $record->index->name }}</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-gray-700">{{ $record->source ?: '—' }}</td>
                                <td class="px-3.5 py-3 text-gray-600">
                                    {{ $record->document_date?->format('Y-m-d') ?: '—' }}
                                </td>
                                <td class="px-3.5 py-3 text-gray-700">
                                    @if ($record->amount !== null)
                                        {{ rtrim(rtrim(number_format((float) $record->amount, 2), '0'), '.') }}
                                        <span class="text-xs text-gray-500">{{ $record->currency }}</span>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-3.5 py-3">
                                    <x-ordo.pill>Uploaded</x-ordo.pill>
                                </td>
                                <td class="px-3.5 py-3">
                                    <x-ordo.pill :tone="$searchable ? 'blue' : 'neutral'">
                                        {{ $searchable ? 'Searchable' : 'Not OCR' }}
                                    </x-ordo.pill>
                                </td>
                                <td class="px-3.5 py-3">
                                    <x-ordo.pill :tone="$record->status->tone()">{{ $record->status->label() }}</x-ordo.pill>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">
                                    <x-ordo.empty message="No records match the selected filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="mt-2.5">{{ $records->links() }}</div>
            @endif
        </div>
    </div>

    @include('records.partials.record-drawer')
</x-layouts.app>
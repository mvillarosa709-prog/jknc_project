{{--
    Records Management dashboard.

    Laid out as a two-column grid of cards in the order the reference mock
    presents them: headline figures, then composition and mix, then movement and
    completeness, then the metering and the cross-module picture, and finally the
    register's newest rows across the full width.

    Every figure here is real and server-computed. Nothing is drawn to fill the
    space: the trend chart, the link counts and the module bars all render an
    empty state instead, because this repository has no links and a handful of
    filings, and a fabricated trend in a records system would be worse than a
    blank panel.
--}}
<x-layouts.app title="Records Management" active-nav="dashboard">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo">
        <x-ordo.flash wrapper />

        <x-ordo.page-head title="Records Management"
                          subtitle="Live overview of the records repository, classification activity and record quality.">
            <x-slot:actions>
                <x-ordo.button variant="primary" href="{{ route('records.intake.upload') }}">+ Upload Records</x-ordo.button>
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-4 py-4">

            {{-- ============ HEADLINE FIGURES ============ --}}
            <div class="mb-3 grid grid-cols-4 gap-2.5 max-[1180px]:grid-cols-2 max-[620px]:grid-cols-1">
                <div class="integration-card">
                    <span>Total Records</span>
                    <strong>{{ number_format($counts['active']) }}</strong>
                    <small class="mt-1 block text-[9px] text-ordo-faint">Classified and active records</small>
                </div>

                <div class="integration-card">
                    <span>Unclassified Records</span>
                    <strong>{{ number_format($counts['open_intake']) }}</strong>
                    <small class="mt-1 block text-[9px] text-ordo-faint">Uploaded and waiting for classification</small>
                </div>

                <div class="integration-card">
                    <span>Records Added</span>
                    <strong>{{ number_format($counts['added_this_month']) }}</strong>
                    <small class="mt-1 block text-[9px] text-ordo-faint">This month</small>
                </div>

                <div class="integration-card">
                    <span>Records Updated</span>
                    <strong>{{ number_format($counts['updated_this_month']) }}</strong>
                    <small class="mt-1 block text-[9px] text-ordo-faint">This month</small>
                </div>
            </div>

            {{-- ============ COMPOSITION + MIX ============ --}}
            <div class="mb-3 grid grid-cols-2 gap-2.5 max-[1180px]:grid-cols-1">

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Records by Classification</h2>
                        <span class="text-[9px] text-ordo-muted">Repository composition</span>
                    </div>

                    <x-ordo.donut :center="number_format($counts['records'])" center-label="Total Records"
                                   :empty="'No records filed yet'"
                                   :slices="array_map(fn (array $row): array => [
                                       'label' => $row['code'].' — '.$row['name'],
                                       'value' => $row['records'],
                                       'href' => route('records.index', ['classification' => $row['code']]),
                                   ], $distribution)" />
                </div>

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Top Record Indexes</h2>
                        <span class="text-[9px] text-ordo-muted">Most populated</span>
                    </div>

                    <x-ordo.bar-list
                        :rows="array_map(fn (array $row): array => [
                            'label' => $row['code'].' — '.$row['name'],
                            'count' => $row['records'],
                            'href' => route('records.index', ['index' => $row['code']]),
                        ], $indexes)"
                        empty="No indexed records yet." />
                </div>
            </div>

            {{-- ============ MOVEMENT + SOURCES ============ --}}
            <div class="mb-3 grid grid-cols-2 gap-2.5 max-[1180px]:grid-cols-1">

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Records Activity</h2>
                        <span class="text-[9px] text-ordo-muted">Filed per month, last 12 months</span>
                    </div>

                    <x-ordo.activity-chart
                        :points="array_map(fn (array $point): array => [
                            'label' => $point['label'],
                            'value' => $point['filed'],
                        ], $activity)"
                        empty="No records have been filed yet, so there is no trend to draw." />
                </div>

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Records by Source</h2>
                        <span class="text-[9px] text-ordo-muted">Top sources</span>
                    </div>

                    <x-ordo.bar-list
                        :rows="array_map(fn (array $row): array => [
                            'label' => $row['source'],
                            'count' => $row['records'],
                        ], $bySource)"
                        empty="No records have been filed yet, so there is no source mix to show." />
                </div>
            </div>

            {{-- ============ COMPLETENESS + PROGRESS ============ --}}
            <div class="mb-3 grid grid-cols-2 gap-2.5 max-[1180px]:grid-cols-1">

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Records Quality</h2>
                        <span class="text-[9px] text-ordo-muted">Repository completeness</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 max-[620px]:grid-cols-1">
                        <x-ordo.stat-tile :value="number_format($counts['missing_source'])" label="Missing Source" />
                        <x-ordo.stat-tile :value="number_format($counts['missing_date'])" label="Missing Document Date" />
                        <x-ordo.stat-tile :value="number_format($counts['missing_title'])" label="Missing Title" />

                        {{-- Links through rather than restating the figure: the
                             unclassified count is already the headline card above,
                             and this is the one number a user can act on. --}}
                        <x-ordo.stat-tile :value="number_format($counts['open_intake'])" label="Unclassified Uploads"
                                           :href="route('records.unclassified')" tone="link" />
                    </div>

                    <p class="mb-0 mt-2.5 text-[9px] leading-[1.55] text-ordo-muted">
                        Of {{ number_format($counts['records']) }} record(s). A record missing any of these
                        cannot be searched on that field later, so the gap is worth closing before filing
                        rather than after.
                    </p>
                </div>

                @php
                    // Classified against everything that has been uploaded and is
                    // still waiting. Discarded uploads are excluded on both sides:
                    // they were removed, so counting them as unclassified work would
                    // keep the bar short forever.
                    $progressTotal = $counts['records'] + $counts['open_intake'];
                    $progressPercent = $progressTotal > 0
                        ? round($counts['records'] / $progressTotal * 100)
                        : 0;
                @endphp

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Classification Progress</h2>
                        <span class="text-[9px] text-ordo-muted">Uploaded repository</span>
                    </div>

                    <x-ordo.donut :center="$progressPercent.'%'" center-label="Classified"
                                   size="130"
                                   empty="Nothing uploaded yet"
                                   :colors="['#2f62e7', '#e5e7eb']"
                                   :slices="[
                                       ['label' => 'Classified', 'value' => $counts['records']],
                                       ['label' => 'Unclassified', 'value' => $counts['open_intake']],
                                   ]" />
                </div>
            </div>

            {{-- ============ METERING + CONNECTIONS ============ --}}
            <div class="mb-3 grid grid-cols-2 gap-2.5 max-[1180px]:grid-cols-1">

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Repository Usage</h2>
                        <span class="text-[9px] text-ordo-muted">Prepared for plan metering</span>
                    </div>

                    @foreach ([
                        ['label' => 'Record Capacity', 'used' => $usage['records']['used'], 'limit' => $usage['records']['limit'], 'percent' => $usage['records']['percent']],
                        ['label' => 'Storage', 'used' => $usage['storage']['used'], 'limit' => $usage['storage']['limit'], 'percent' => $usage['storage']['percent']],
                    ] as $meter)
                        @php
                            $isBytes = $meter['label'] === 'Storage';
                            $isEmpty = $meter['limit'] === null || $meter['limit'] <= 0;
                        @endphp

                        <div class="mb-2.5 last:mb-0">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="text-[10px] text-ordo-muted">{{ $meter['label'] }}</span>
                                <strong class="text-[10px] text-ordo-ink">
                                    @if ($isBytes)
                                        {{-- @fileSize, not Number::fileSize: the
                                             framework formatter needs the intl
                                             extension, which is not installed
                                             here, and the project ships its own. --}}
                                        @fileSize($meter['used'])
                                        @if (! $isEmpty) / @fileSize($meter['limit']) @endif
                                    @else
                                        {{ number_format($meter['used']) }}
                                        @if (! $isEmpty) / {{ number_format($meter['limit']) }} @endif
                                    @endif
                                </strong>
                            </div>

                            {{-- With no limit on file there is nothing to be a
                                 share of, so the meter is omitted and the card
                                 says the ceiling is unset rather than implying
                                 an empty allowance. --}}
                            @if ($isEmpty)
                                <p class="mb-0 mt-1 text-[9px] text-ordo-faint">No limit set</p>
                            @else
                                <div class="h-[7px] w-full overflow-hidden rounded-full bg-[#eef0f3]">
                                    <div @class([
                                            'h-full rounded-full transition-[width]',
                                            'bg-ordo-blue' => $meter['percent'] < 90,
                                            'bg-ordo-warn' => $meter['percent'] >= 90 && $meter['percent'] < 100,
                                            'bg-ordo-danger' => $meter['percent'] >= 100,
                                         ])
                                         style="width: {{ $meter['percent'] }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="mt-3 grid grid-cols-2 gap-2 max-[620px]:grid-cols-1">
                        <x-ordo.stat-tile :value="number_format($storage['files'])" label="Files Stored" />
                        <x-ordo.stat-tile :value="number_format($storage['supporting'])" label="Supporting Files" />
                        <x-ordo.stat-tile :value="number_format($storage['prior_versions'])" label="Prior Versions" />
                        <x-ordo.stat-tile :value="number_format($counts['generated'])" label="ORDO Generated"
                                           title="Uploads a module registered rather than a person. No module registration is enabled yet, so this reads zero until module integration lands." />
                    </div>
                </div>

                <div class="rounded-xl border border-ordo-line bg-white p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="m-0 text-[11px] font-bold">Connected Across ORDO</h2>
                        <span class="text-[9px] text-ordo-muted">Master record reuse</span>
                    </div>

                    <div class="mb-3 grid grid-cols-2 gap-2 max-[620px]:grid-cols-1">
                        <x-ordo.stat-tile :value="number_format($links['total'])" label="Total Links" />
                        <x-ordo.stat-tile :value="number_format($links['records'])" label="Linked Records" />
                    </div>

                    <x-ordo.bar-list
                        :rows="array_map(fn (string $module, int $count): array => [
                            'label' => $module,
                            'count' => $count,
                        ], array_keys($links['by_module']), $links['by_module'])"
                        empty="No cross-module links yet." />
                </div>
            </div>

            {{-- ============ RECENTLY ADDED ============ --}}
            <div class="rounded-xl border border-ordo-line bg-white p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="m-0 text-[11px] font-bold">Recently Added Records</h2>
                    <a href="{{ route('records.index') }}" class="text-[9px] text-ordo-blue transition-opacity hover:opacity-75">View all</a>
                </div>

                @if ($recentRecords->isEmpty())
                    <x-ordo.empty message="No records have been filed yet." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-[11px]">
                            <thead>
                                <tr class="border-b border-ordo-line text-left">
                                    <th class="px-2 py-2 font-semibold text-ordo-muted">Document Title</th>
                                    <th class="px-2 py-2 font-semibold text-ordo-muted">Classification</th>
                                    <th class="px-2 py-2 font-semibold text-ordo-muted">Index</th>
                                    <th class="px-2 py-2 font-semibold text-ordo-muted">Source</th>
                                    <th class="px-2 py-2 font-semibold text-ordo-muted">Document Date</th>
                                    <th class="px-2 py-2 font-semibold text-ordo-muted">Added By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentRecords as $record)
                                    <tr class="border-b border-[#eef0f3] last:border-b-0">
                                        <td class="px-2 py-2">
                                            <a href="{{ route('records.index', ['q' => $record->document_reference]) }}"
                                               class="text-ordo-blue transition-opacity hover:opacity-75">{{ $record->title }}</a>
                                        </td>
                                        <td class="px-2 py-2 text-ordo-muted">
                                            <span class="font-mono">{{ $record->classification_code }}</span>
                                            @if ($record->classification_name)
                                                — {{ $record->classification_name }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-ordo-muted">
                                            <span class="font-mono">{{ $record->index_code }}</span>
                                        </td>
                                        <td class="px-2 py-2 text-ordo-muted">{{ $record->source ?: '—' }}</td>
                                        <td class="px-2 py-2 text-ordo-muted">{{ $record->document_date?->format('M d, Y') ?? '—' }}</td>
                                        <td class="px-2 py-2 text-ordo-muted">{{ $record->created_by_name ?? 'System' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>

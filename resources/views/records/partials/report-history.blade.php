{{--
    The report history list.

    Rendered as a fragment by RecordReportController@history and injected by the
    builder's modal, so opening the history costs no query until the reader asks
    to see it.

    Every row is one output this company has taken onward. The figures shown are
    the ones frozen onto the row when it was recorded, not a recount against the
    register as it stands now — which is the whole reason the row stores them. A
    record filed since does not retroactively change what was reported.

    Re-downloading does not create a second entry. It is the act of fetching an
    output that was already issued, so treating it as a new one would fill the
    history with duplicates of reports the reader has already seen.
--}}
@if ($runs->isEmpty())
    <x-ordo.empty message="No reports have been presented or downloaded yet." />
@else
    <div class="flex flex-col gap-2">
        @foreach ($runs as $run)
            @php
                $figures = $run->figures();

                // Built here rather than in the browser so the re-download
                // carries the recorded filters exactly, including which ones were
                // deliberately left blank.
                $downloadUrl = route(
                    'records.reports.export',
                    array_merge(['type' => $run->type], array_filter($run->filterSet())),
                );
            @endphp

            <div class="rounded-lg border border-ordo-line bg-white px-3 py-2.5">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div class="min-w-0">
                        <strong class="text-[12px]">{{ $run->reportType()->label() }}</strong>
                        <span class="ml-2 rounded-full bg-ordo-blue-soft px-[7px] py-1 text-[9px] text-ordo-blue">
                            {{ $run->triggerType()->label() }}
                        </span>
                    </div>
                    <span class="shrink-0 text-[10px] text-ordo-faint">
                        {{ $run->created_at?->format('Y-m-d H:i') }}
                    </span>
                </div>

                {{-- The filters this run was built from, so an entry names the
                     slice of the register it covered rather than just naming a
                     report type. --}}
                <p class="mt-1.5 mb-2 text-[10px] leading-[1.55] text-ordo-muted">
                    {{ $run->filterSummary() }}
                </p>

                {{-- The recorded figures. --}}
                <div class="grid grid-cols-4 gap-2 max-[620px]:grid-cols-2">
                    @foreach ([
                        ['label' => 'Records', 'value' => $figures['records']],
                        ['label' => 'Classifications', 'value' => $figures['classifications']],
                        ['label' => 'Sources', 'value' => $figures['sources']],
                        ['label' => 'Reporting Year', 'value' => $figures['year']],
                    ] as $tile)
                        <div class="rounded-lg border border-ordo-line bg-[#fafbfc] px-2.5 py-1.5">
                            <div class="text-[9px] text-ordo-faint">{{ $tile['label'] }}</div>
                            <div class="text-[12px] font-bold">{{ $tile['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if ($run->comments)
                    <div class="mt-2 rounded-lg border border-ordo-line bg-[#fafbfc] px-2.5 py-2">
                        <div class="mb-1 text-[9px] text-ordo-faint">Management / consultant observations</div>
                        <p class="m-0 text-[10px] leading-[1.55] whitespace-pre-line text-ordo-ink">{{ $run->comments }}</p>
                    </div>
                @endif

                <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="text-[9px] text-ordo-faint">
                        Figures as recorded; the CSV reflects the register now.
                    </span>
                    <x-ordo.button type="button" variant="default"
                                   data-download-url="{{ $downloadUrl }}"
                                   x-on:click="downloadRun($el)">
                        Re-download CSV
                    </x-ordo.button>
                </div>
            </div>
        @endforeach
    </div>
@endif
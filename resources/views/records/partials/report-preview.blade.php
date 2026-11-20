{{--
    The generated report preview.

    Rendered as a fragment by RecordReportController@generate and injected by the
    builder, so the figures on screen and the rows behind Download CSV come from
    the same filter builder and cannot disagree.

    The management comments box is saved with the output when the report is
    presented or downloaded, so the observations written here travel with the
    report they were written for. Nothing written here is an official output
    until one of the controls below is used.
--}}
<div class="mt-4" data-report-preview>
    {{-- No heading here on purpose. The cover directly above already names the
         report being configured, and repeating it two hundred pixels further
         down read as two different things on one screen. --}}

    {{-- Headline figures. --}}
    <div class="mb-3.5 grid grid-cols-4 gap-3 max-[860px]:grid-cols-2">
        @foreach ([
            ['label' => 'Records Included', 'value' => $metrics['records']],
            ['label' => 'Classifications', 'value' => $metrics['classifications']],
            ['label' => 'Sources', 'value' => $metrics['sources']],
            ['label' => 'Reporting Year', 'value' => $metrics['year']],
        ] as $tile)
            <div class="kpi">
                <div class="label">{{ $tile['label'] }}</div>
                <div class="value">{{ $tile['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- The reference weights the two panels 1.1fr/.9fr: the chart needs the extra
         room for long Classification names, the summary is prose and reads fine
         narrower. --}}
    <div class="grid grid-cols-[1.1fr_.9fr] gap-3.5 max-[860px]:grid-cols-1">
        {{-- Records by Classification, busiest first. --}}
        <div class="rounded-xl border border-ordo-line bg-white p-3.5">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="m-0 text-[12px] font-bold">Records by Classification</h3>
                <span class="shrink-0 text-[10px] text-ordo-faint">{{ $type->label() }}</span>
            </div>

            @php
                $bars = collect($metrics['distribution'])
                    ->map(fn (array $group): array => [
                        'label' => $group['name'],
                        'count' => $group['count'],
                    ])
                    ->all();
            @endphp

            {{-- Named on its own: the code stays on the record and in the CSV, but
                 prefixing it here made every label longer and the chart noisier to
                 scan than the reference. --}}
            <x-ordo.bar-list :rows="$bars" empty="No records match the selected filters." />
        </div>

        {{-- Management Summary. --}}
        <div class="rounded-xl border border-ordo-line bg-white p-3.5">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="m-0 text-[12px] font-bold">Management Summary</h3>
                <span class="shrink-0 text-[10px] text-ordo-faint">Preview</span>
            </div>

            <p class="m-0 text-[10px] leading-[1.6] text-ordo-muted">
                This report contains {{ $metrics['records'] }} record(s) based on the selected
                filters. Use the output controls below to present or export the current report.
            </p>

            {{-- No font size: the reference sets none, so the box inherits a
                 readable size rather than the 10px the panel's prose runs at. --}}
            <textarea x-model="comments" rows="4" maxlength="5000"
                      placeholder="Optional management / consultant observations..."
                      class="mt-2.5 min-h-[100px] w-full resize-y rounded-lg border border-ordo-line bg-white px-2.5 py-2.5 outline-none focus:border-ordo-blue"></textarea>
        </div>
    </div>

    {{-- Output controls. Left-aligned and all of equal weight, as the reference
         has them: these are three ways to take the same report onward, not a
         primary action with two lesser ones beside it.

         Present and downloads record the report first. PDF is generated server-side
         so it downloads directly instead of opening the browser print dialog. --}}
    <div class="mt-3.5 flex flex-wrap items-center gap-2">
         <x-ordo.button type="button" variant="default" x-on:click="present()">Present</x-ordo.button>
         <details class="relative">
             <summary class="inline-flex cursor-pointer list-none items-center justify-center rounded-lg border border-ordo-line bg-ordo-panel px-3 py-2 text-[12px] text-[#374151] hover:bg-[#f9fafb]">Export</summary>
             <div class="absolute bottom-full left-0 z-10 mb-1 min-w-[150px] rounded-lg border border-ordo-line bg-white p-1 shadow-ordo">
                 <button type="button" x-on:click="download()" class="block w-full rounded-md px-3 py-2 text-left text-[11px] text-ordo-ink hover:bg-[#f8fafc]">Export CSV</button>
                 <button type="button" x-on:click="downloadPdf()" class="block w-full rounded-md px-3 py-2 text-left text-[11px] text-ordo-ink hover:bg-[#f8fafc]">Export PDF</button>
             </div>
         </details>
    </div>
</div>
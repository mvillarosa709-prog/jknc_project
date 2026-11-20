{{--
    Formal Records Management outputs.

    A card is chosen first, then the report is configured and previewed. The
    preview and the CSV download are produced by one filter builder on the server,
    so what is shown here is what is exported.

    Report History lists the outputs this company has actually taken onward. A
    preview is not recorded: configuring a report is not the same act as issuing
    one, so the history is a list of outputs rather than a list of clicks.
--}}
<x-layouts.app title="Reports" active-nav="reports">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo"
         x-data="reportBuilder(@js($indexesByCode), @js($generateUrl), @js($exportUrl), @js($storeRunUrl), @js($historyUrl))">

        <x-ordo.page-head title="Reports"
                          subtitle="Formal Records Management outputs for management, consultants and executives.">
            <x-slot:actions>
                <x-ordo.button type="button" variant="default"
                               title="Reports this company has presented or downloaded"
                               x-on:click="openHistory()">Report History</x-ordo.button>
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-4 py-4">
            <x-ordo.flash />

            @include('records.partials.report-cards', ['reportTypes' => $reportTypes])

            {{-- ============ REPORT BUILDER ============ --}}
            <template x-if="selected === null">
                <div class="mt-4 rounded-xl border border-ordo-line bg-ordo-panel px-5 py-[46px] text-center text-[12px] text-ordo-faint shadow-ordo">
                    Choose a report above to configure its filters and preview the output.
                </div>
            </template>

            <div class="mt-4 overflow-hidden rounded-xl border border-ordo-line bg-white" x-show="selected !== null" x-cloak>
                {{-- The cover is tinted rather than flat: it is the only thing
                     identifying which of the six reports is being configured,
                     and it sits directly above filters that apply to all six. --}}
                <div class="border-b border-ordo-line bg-gradient-to-br from-[#eef4ff] to-white px-6 py-6">
                    <h2 class="m-0 text-[20px] font-bold" x-text="label()"></h2>
                    <p class="mt-1.5 mb-0 text-[11px] text-[#6b7280]">
                        Configure filters, preview, then present or download.
                    </p>
                </div>

                <div class="px-4 py-4">
                    {{-- Two columns, as the reference lays its filters out. A
                         single row of four squeezes long Classification codes
                         and makes the four filters read as one control. --}}
                    <div class="grid grid-cols-2 gap-2.5 max-[620px]:grid-cols-1">
                        <div class="flex flex-col gap-[5px]">
                            <label class="text-[10px] font-semibold text-ordo-muted" for="report-classification">Classification</label>
                            <select id="report-classification" x-model="filters.classification"
                                    class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <option value="">All classifications</option>
                                @foreach ($classifications as $classification)
                                    <option value="{{ $classification->code }}">{{ $classification->code }} — {{ $classification->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-[5px]">
                            <label class="text-[10px] font-semibold text-ordo-muted" for="report-index">Index</label>
                            <select id="report-index" x-model="filters.index"
                                    class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <option value="">All indexes</option>
                                {{-- Server-rendered so the options exist before any
                                     JavaScript runs; the builder narrows this list
                                     by Classification code once a filter is chosen. --}}
                                @foreach ($indexes as $index)
                                    <option value="{{ $index->code }}">{{ $index->code }} — {{ $index->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-[5px]">
                            <label class="text-[10px] font-semibold text-ordo-muted" for="report-source">Source</label>
                            <select id="report-source" x-model="filters.source"
                                    class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                                <option value="">All sources</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source->name }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-[5px]">
                            <label class="text-[10px] font-semibold text-ordo-muted" for="report-year">Document Year</label>
                            <input id="report-year" type="number" min="1900" max="2999"
                                   x-model="filters.year" placeholder="e.g. 2026"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </div>
                    </div>

                    {{-- Left-aligned, as the reference has it: the button reads as
                         the next step in reading order rather than being pushed
                         to the far edge of a wide panel. --}}
                    <div class="mt-3.5 flex flex-wrap items-center gap-3">
                        <x-ordo.button type="button" variant="primary"
                                       x-on:click="generate()"
                                       x-bind:disabled="busy">
                            <span x-show="! busy">Generate Preview</span>
                            <span x-show="busy" x-cloak>Generating...</span>
                        </x-ordo.button>

                        <template x-if="error">
                            <span class="text-[10px] text-[#b42318]" x-text="error"></span>
                        </template>
                    </div>

                    {{-- The server-rendered preview, injected here. --}}
                    <div x-html="previewHtml"></div>
                </div>
            </div>
        </div>

        {{-- Inside the builder's scope: the modal reads the same history state
             the header button sets, rather than holding its own copy. --}}
        @include('records.partials.report-history-modal')
    </div>
</x-layouts.app>
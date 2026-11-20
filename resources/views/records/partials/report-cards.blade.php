{{--
    The six report types.

    Split out so the grid and the page tests read the same markup, and so a seventh
    type can be added by adding one enum case rather than editing markup here.
--}}
<div class="grid grid-cols-3 gap-2.5 max-[1100px]:grid-cols-2 max-[620px]:grid-cols-1">
    @foreach ($reportTypes as $reportType)
        <button type="button"
                class="rounded-xl border border-ordo-line bg-white p-3.5 text-left transition-colors hover:border-ordo-blue-border hover:bg-[#f8faff] max-[620px]:w-full"
                x-bind:class="selected === @js($reportType->value) ? 'border-ordo-blue-border bg-[#f4f7ff]' : ''"
                x-on:click="select(@js($reportType->value))">
            <h4 class="m-0 text-[12px] font-bold text-ordo-ink">{{ $reportType->label() }}</h4>
            <p class="mt-1 mb-2 text-[10px] leading-[1.55] text-ordo-muted">{{ $reportType->description() }}</p>
            <span class="inline-flex items-center gap-1 rounded-full bg-ordo-blue-soft px-[7px] py-1 text-[9px] text-ordo-blue">
                Generate report
            </span>
        </button>
    @endforeach
</div>
{{--
    A donut chart with a figure in the middle and a legend beside it.

    Drawn with a conic-gradient rather than an SVG or a chart library: the ring is
    one background declaration, so there is no drawing library to add to a project
    that currently ships none, and no client-side script to run before the shape
    appears.

    `slices` is a list of ['label' => string, 'value' => int, 'href' => ?string]
    and `colors` assigns a colour per slice, in order. Slice order and legend order
    are the same, so a colour can be read straight across.

    A total of zero draws no ring and says so, rather than drawing an empty circle
    that looks like a chart of nothing.
--}}
@props(['slices' => [], 'colors' => null, 'center' => null, 'centerLabel' => null, 'size' => 140, 'empty' => null])

@php
    $colors ??= ['#2f62e7', '#8fb0f5', '#c4d8fb', '#5f9a86', '#c9a227', '#8b5cf6'];

    $total = array_sum(array_map(static fn (array $slice): int => (int) ($slice['value'] ?? 0), $slices));

    // Built as a single conic-gradient. Each stop runs from where the previous one
    // finished to where this slice finishes, as a percentage of the total.
    $stops = [];
    $cursor = 0.0;

    foreach ($slices as $index => $slice) {
        $value = (int) ($slice['value'] ?? 0);

        if ($value <= 0 || $total <= 0) {
            continue;
        }

        $start = round($cursor, 4);
        $cursor += $value / $total * 100;
        $end = round($cursor, 4);
        $color = $colors[$index % count($colors)];

        $stops[] = sprintf('%s %s%% %s%%', $color, $start, $end);
    }

    $gradient = $stops === [] ? null : 'conic-gradient('.implode(', ', $stops).')';
@endphp

<div class="flex flex-wrap items-center gap-6">
    @if ($gradient === null)
        <div class="grid shrink-0 place-items-center rounded-full border-[14px] border-[#e5e7eb] text-center"
             style="width: {{ $size }}px; height: {{ $size }}px">
            <span class="text-[10px] text-ordo-faint">{{ $empty ?? 'No data' }}</span>
        </div>
    @else
        {{-- role=img with a text alternative: the ring is a picture of the
             figures, and the legend beside it is those figures as text. --}}
        <div class="relative grid shrink-0 place-items-center rounded-full"
             style="width: {{ $size }}px; height: {{ $size }}px; background: {{ $gradient }}"
             role="img"
             aria-label="{{ $centerLabel ? $centerLabel.': '.$total : 'Total '.$total }}">

            <div class="grid place-items-center rounded-full bg-white text-center"
                 style="width: {{ round($size * 0.58) }}px; height: {{ round($size * 0.58) }}px">
                @if ($center)
                    <strong class="block text-[20px] leading-none text-ordo-ink">{{ $center }}</strong>
                @endif

                @if ($centerLabel)
                    <span class="mt-1 block text-[9px] text-ordo-muted">{{ $centerLabel }}</span>
                @endif
            </div>
        </div>
    @endif

    @if ($slices !== [])
        <div class="flex min-w-[150px] flex-1 flex-col gap-1.5">
            @foreach ($slices as $index => $slice)
                @php $color = $colors[$index % count($colors)]; @endphp

                <div class="flex items-center gap-2 text-[11px]">
                    <span class="size-[7px] shrink-0 rounded-full" style="background: {{ $color }}" aria-hidden="true"></span>

                    @if (! empty($slice['href']))
                        {{-- No underline on hover, matching the reference mock's
                             .link rule. The blue already marks it as a link; the
                             fade is the only hover signal it gives. --}}
                        <a href="{{ $slice['href'] }}" class="min-w-0 flex-1 truncate text-ordo-blue transition-opacity hover:opacity-75">{{ $slice['label'] }}</a>
                    @else
                        <span class="min-w-0 flex-1 truncate text-ordo-blue">{{ $slice['label'] }}</span>
                    @endif

                    <strong class="shrink-0 font-normal text-ordo-ink">{{ number_format((int) $slice['value']) }}</strong>
                </div>
            @endforeach
        </div>
    @endif
</div>

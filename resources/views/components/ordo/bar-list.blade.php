{{--
    A ranked list of labelled bars, each with its count on the right.

    Backs the three ranked lists on the dashboard — Top Record Indexes, Records by
    Source and the per-module breakdown under Connected Across ORDO — and the
    Records by Classification chart on a generated report, so the bar geometry and
    the label/count alignment are written once.

    Geometry follows the reference mock's .bar-row: the count sits in a reserved
    70px column rather than being sized to its own content, so every figure lines
    up down the right-hand edge instead of drifting with the width of its digits.

    `rows` is a list of ['label' => string, 'count' => int, 'href' => ?string].
    Bars are scaled against the largest row, so the leading row always fills the
    track and the rest are proportional to it.
--}}
@props(['rows' => [], 'empty' => null, 'emptyHref' => null, 'emptyLabel' => null])

@php
    $counts = array_map(static fn (array $row): int => (int) ($row['count'] ?? 0), $rows);
    $max = $counts === [] ? 0 : max($counts);
@endphp

@if ($rows === [])
    <x-ordo.empty :message="$empty ?? 'Nothing to show yet.'">
        @if ($emptyHref)
            <a href="{{ $emptyHref }}" class="mt-2 inline-block text-[11px] text-ordo-blue transition-opacity hover:opacity-75">{{ $emptyLabel ?? 'View all' }}</a>
        @endif
    </x-ordo.empty>
@else
    <div class="flex flex-col gap-3">
        @foreach ($rows as $row)
            @php $percent = $max > 0 ? round((int) $row['count'] / $max * 100, 2) : 0; @endphp

            <div class="flex flex-col gap-1">
                @if (! empty($row['href']))
                    {{-- Blue already marks the label as a link, so the hover is a
                         fade rather than an underline — the reference mock's .link
                         rule draws a line in no state at all. --}}
                    <a href="{{ $row['href'] }}" class="truncate text-[11px] text-ordo-blue transition-opacity hover:opacity-75">{{ $row['label'] }}</a>
                @else
                    <span class="truncate text-[11px] text-ordo-ink">{{ $row['label'] }}</span>
                @endif

                <div class="flex items-center gap-2.5">
                    {{-- The fill is the solid blue, not the pale --color-ordo-blue-soft
                         used for tints elsewhere: a soft-blue bar on a light track is
                         all but invisible, which reads as no data at all. --}}
                    <span class="h-[8px] flex-1 overflow-hidden rounded-full bg-[#f1f5f9]">
                        {{-- The bar is decorative; the count beside it is the
                             figure, so it is not exposed as a progressbar. --}}
                        <span class="block h-full rounded-full bg-ordo-blue"
                              style="width: {{ $percent }}%"></span>
                    </span>

                    <span class="w-[70px] shrink-0 text-right text-[11px] text-ordo-muted">{{ number_format((int) $row['count']) }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif

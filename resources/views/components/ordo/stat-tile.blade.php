{{--
    A small metric tile: a figure with a caption beneath it.

    Used for the repeated figures across the dashboard — the Records Quality
    gaps, the Classification Progress split, the Repository Usage file counts and
    the Connected Across ORDO totals. Each of those is the same shape with a
    different number in it, so they share one component rather than eight copies
    of the same three lines of markup.

    `href` turns the tile into a link, which is how a figure that a user can act
    on (unclassified uploads, for instance) is distinguished from one that is
    only a report of where things stand.
--}}
@props(['value', 'label' => null, 'href' => null, 'tone' => 'default'])

@php
    $tones = [
        'default' => 'border-[#eef0f3] bg-[#fafbfc] text-ordo-ink',
        'link' => 'border-ordo-blue-border bg-ordo-blue-soft text-ordo-blue transition-colors hover:bg-[#e5eeff]',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}"
       class="block rounded-lg border px-3 py-2.5 {{ $tones[$tone] ?? $tones['default'] }}">
        <strong class="block text-[15px]">{{ $value }}</strong>
        @if ($label)
            <span class="text-[9px] opacity-90">{{ $label }}</span>
        @endif
    </a>
@else
    <div class="rounded-lg border px-3 py-2.5 {{ $tones[$tone] ?? $tones['default'] }}">
        <strong class="block text-[15px]">{{ $value }}</strong>
        @if ($label)
            <span class="text-[9px] text-ordo-muted">{{ $label }}</span>
        @endif
    </div>
@endif

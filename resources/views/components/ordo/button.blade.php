@props(['variant' => 'default', 'href' => null, 'type' => 'button'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-[12px] transition-colors disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        'default' => 'border-ordo-line bg-ordo-panel text-[#374151] hover:bg-[#f9fafb]',
        'primary' => 'border-ordo-blue bg-ordo-blue text-white hover:bg-[#2559d6]',
        'soft' => 'border-[#d7e5ff] bg-[#eef4ff] text-ordo-blue hover:bg-[#e5eeff]',
        'danger' => 'border-[#f4cccc] bg-[#fff8f8] text-[#b42318] hover:bg-[#fff0f0]',
        'success' => 'border-[#cdecdc] bg-[#f3fbf7] text-[#11734b] hover:bg-[#e9f7f0]',
        // The setup gear. A square variant rather than a new component, so it
        // keeps the same border, radius and focus behaviour as every other button.
        //
        // It repeats the default variant's chrome rather than inheriting it,
        // because the reference stylesheet gets that chrome the same way: its
        // `.btn.icon` is layered over `.btn`, so the gear button carries a white
        // fill, the #e5e7eb hairline and #374151 ink. Without them this variant
        // left the border on currentColor — the much darker body ink — and the
        // panel showing through, so the button did not read as the reference's.
        //
        // font-variant-emoji is the reason this stays a character at all. U+2699
        // is the exact glyph the reference uses, but it sits in the emoji block,
        // and browsers substitute the platform colour font for it on Windows.
        // Declaring text presentation here corrects the rendering without
        // touching the markup, so the button is still character-for-character
        // what the reference ships. Browsers that do not support it fall back to
        // the reference's own behaviour.
        'icon' => 'w-[36px] shrink-0 px-0 text-center border-ordo-line bg-ordo-panel text-[#374151] hover:bg-[#f9fafb] [font-variant-emoji:text]',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.($variants[$variant] ?? $variants['default'])]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base.' '.($variants[$variant] ?? $variants['default'])]) }}>{{ $slot }}</button>
@endif

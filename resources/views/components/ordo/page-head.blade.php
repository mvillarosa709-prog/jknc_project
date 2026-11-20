{{--
    The header every Records page opens with: title, subtitle, and the actions
    that belong to the page.

    Exists because the reference mock builds every page through one `shell()`
    function, which puts the setup gear into the head of all of them. Laravel has
    no such shared entry point, so each page used to hand-roll this block and the
    gear had to be added to every page separately. One component keeps that
    decision in one place: a new page gets the gear by using this component.

    The gear opens the setup modal, which lives once in the layout rather than
    once per page, so it is reached by a window-level event rather than a
    reference to a component that lives further up the tree.
--}}
@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 border-b border-ordo-line px-4 py-4']) }}>
    <div class="min-w-0">
        <h1 class="mb-[3px] text-[16px] font-bold">{{ $title }}</h1>

        @if ($subtitle)
            <p class="text-[11px] text-[#8b94a3]">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-2 max-[620px]:flex-wrap max-[620px]:justify-end">
        {{--
            U+2699 GEAR, the character the reference stylesheet uses for this
            button. Not drawn here and not a different glyph: the same
            codepoint, so the markup is character-for-character what the
            reference ships.

            It carries Emoji_Presentation, which is why it can look wrong here
            while the reference looks right — a browser on Windows substitutes
            the platform colour font for a bare character from the emoji block,
            and draws a full-colour pictogram in an otherwise monochrome chrome.
            That is a platform substitution rather than a difference in the
            markup, so it is corrected in CSS (see the icon variant in
            button.blade.php) and the character itself is left alone.
        --}}
        <x-ordo.button type="button" variant="icon"
                       title="Records setup"
                       aria-label="Records Management Setup"
                       x-on:click="$dispatch('ordo:setup-open')"
                       class="hidden">&#9881;</x-ordo.button>

        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
</div>

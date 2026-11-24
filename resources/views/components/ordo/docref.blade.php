@props(['reference' => null])

@if (filled($reference))
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]']) }}>
        {{ $reference }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'text-[12px] text-ordo-faint']) }}>&mdash;</span>
@endif

@props(['message' => 'Nothing to show yet.'])

{{--
    The message prop covers the common one-sentence case. The slot exists because
    the reference's empty states carry a second sentence after a line break — it
    explains what the reader can do next rather than only reporting absence — and
    a single escaped string cannot express that.
--}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-ordo-line bg-ordo-panel px-5 py-[46px] text-center text-[12px] text-ordo-faint shadow-ordo']) }}>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        {{ $message }}
    @endif
</div>

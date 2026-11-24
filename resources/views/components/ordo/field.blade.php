@props(['label' => null, 'help' => null, 'required' => false, 'full' => false])

{{-- $attributes is forwarded so a caller can control the wrapper itself — x-show in
     the classify drawer, for instance, which hides a field whose section the selected
     Index does not apply. --}}
<div {{ $attributes->class(['flex flex-col gap-[5px]', 'col-span-full' => $full]) }}>
    @if ($label)
        <label class="text-[10px] font-semibold text-ordo-muted">
            {{ $label }}
            @if ($required)<span class="text-ordo-danger">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($help)
        <p class="mt-1 text-[10px] leading-[1.45] text-[#98a2b3]">{{ $help }}</p>
    @endif
</div>

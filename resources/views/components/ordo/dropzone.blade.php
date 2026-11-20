@props(['label' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border-[1.5px] border-dashed border-[#b9c8ee] bg-[#f9fbff] px-7 py-7 text-center']) }}
     x-data="{ dragging: false }"
     x-bind:class="dragging ? 'border-ordo-blue bg-[#eef4ff]' : ''"
     x-on:dragover.prevent="dragging = true"
     x-on:dragleave.prevent="dragging = false"
     x-on:drop.prevent="dragging = false; $refs.input.files = $event.dataTransfer.files; $refs.form.requestSubmit()">
    @if ($label)
        <strong class="mb-[5px] block text-[13px]">{{ $label }}</strong>
    @endif
    @if ($hint)
        <span class="text-[10px] text-[#8b94a3]">{{ $hint }}</span>
    @endif
    @if (isset($action))
        <div class="mt-3">{{ $action }}</div>
    @endif
</div>

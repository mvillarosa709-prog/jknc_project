@php
    $dropdownId = $dropdownId ?? 'createFieldDropdownMenu';
    $extraOptions = $extraOptions ?? [];
@endphp

<div id="{{ $dropdownId }}" class="fixed z-[90] hidden max-h-80 w-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-xl">
    @foreach ($fieldTypes as $type)
        <button
            type="button"
            class="create-field-type-option flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-gray-700 hover:bg-blue-50"
            data-field-type="{{ $type['value'] }}"
            data-field-label="{{ $type['label'] }}"
        >
            <i class="fas {{ $type['icon'] }} w-4 text-center text-gray-500"></i>
            <span class="font-medium">{{ $type['label'] }}</span>
        </button>
    @endforeach

    @foreach ($extraOptions as $option)
        <button
            type="button"
            class="create-field-special-option flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-gray-700 hover:bg-blue-50"
            data-special-action="{{ $option['value'] }}"
        >
            <i class="fas {{ $option['icon'] }} w-4 text-center text-gray-500"></i>
            <span class="font-medium">{{ $option['label'] }}</span>
        </button>
    @endforeach
</div>

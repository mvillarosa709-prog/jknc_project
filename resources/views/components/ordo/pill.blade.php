@props(['tone' => 'neutral', 'title' => null])

@php
    $tones = [
        'neutral' => 'bg-[#f3f4f6] text-[#4b5563]',
        'blue' => 'bg-ordo-blue-soft text-ordo-blue',
        'green' => 'bg-[#e9f7f0] text-ordo-success',
        'amber' => 'bg-[#fff6e5] text-ordo-warn',
        'red' => 'bg-[#fff0f0] text-[#b42318]',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-[7px] py-1 text-[9px] '.($tones[$tone] ?? $tones['neutral'])]) }}
      @if ($title) title="{{ $title }}" @endif
>{{ $slot }}</span>

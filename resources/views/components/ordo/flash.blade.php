{{--
    Flash feedback: one banner for a confirmed action, one for a handled error,
    and one listing validation failures.

    Every page reports the same three channels the same way. They were previously
    copy-pasted into four views and each copy covered a different subset, so a
    message could be raised by a controller and rendered nowhere. Keeping the
    three banners here means a new page opts in by placing <x-ordo.flash /> rather
    than by remembering which subset it needs.

    The banner markup is unchanged from the blocks this replaced. Only where the
    upload page puts its errors has moved — see that page's call site.
--}}
@props(['wrapper' => false])

@if (session('status') || session('error') || $errors->any())
    <div @class(['px-4 pt-4' => $wrapper])>
        @if (session('status'))
            <div {{ $attributes->merge(['class' => 'mb-3 rounded-lg border border-[#cdecdc] bg-[#f3fbf7] px-3 py-2 text-[11px] text-[#11734b]']) }}>
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div {{ $attributes->merge(['class' => 'mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]']) }}>
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div {{ $attributes->merge(['class' => 'mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]']) }}>
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
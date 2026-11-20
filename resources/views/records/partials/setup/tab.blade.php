{{--
    Dispatches to the fragment for whichever setup tab was requested.

    The modal fetches one of these per tab, so this file is the seam between the
    modal's shell and the individual sections: the shell does not know what any
    tab contains, and a new tab is a new file plus one entry in the controller's
    TABS list.

    Flash messages and validation errors are rendered here rather than in each
    tab, so a rejected write says so wherever it was posted from.
--}}
@if (session('status'))
    <div class="mb-3 rounded-lg border border-[#cdecdc] bg-[#f3fbf7] px-3 py-2 text-[11px] text-[#11734b]">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@include('records.partials.setup.tabs.'.$tab)

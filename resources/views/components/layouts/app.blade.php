@php
    $railItems = [
        ['icon' => '♟', 'label' => 'Admin'],
        ['icon' => '⚑', 'label' => 'Town Hall'],
        ['icon' => '▦', 'label' => 'Corporate'],
        ['icon' => '▣', 'label' => 'Policies'],
        ['icon' => '≋', 'label' => 'Finance'],
        ['icon' => '♟', 'label' => 'Human Capital'],
        ['icon' => '◎', 'label' => 'Marketing'],
        ['icon' => '⌁', 'label' => 'Sales'],
        ['icon' => '▤', 'label' => 'Accounts'],
        ['icon' => '⚒', 'label' => 'Operations'],
        ['icon' => '▥', 'label' => 'Records', 'active' => true],
    ];

    $activeNav = $activeNav ?? 'unclassified';

    $subnavRoutes = [
        'unclassified' => 'records.unclassified',
        'dashboard' => 'records.dashboard',
        'records' => 'records.index',
        'logs' => 'records.logs',
        'classifications' => 'records.classifications',
        'archive' => 'records.archive.index',
        'reports' => 'records.reports.index',
    ];

    $subnavItems = [
        ['label' => 'Dashboard', 'view' => 'dashboard', 'active' => $activeNav === 'dashboard'],
        ['label' => 'Unclassified Records', 'view' => 'unclassified', 'active' => $activeNav === 'unclassified', 'badge' => $unclassifiedCount ?? null],
        ['label' => 'All Records', 'view' => 'records', 'active' => $activeNav === 'records'],
        ['label' => 'Audit Log', 'view' => 'logs', 'active' => $activeNav === 'logs'],
        ['label' => 'Classifications', 'view' => 'classifications', 'active' => $activeNav === 'classifications'],
        ['label' => 'Archive', 'view' => 'archive', 'active' => $activeNav === 'archive'],
        ['label' => 'Reports', 'view' => 'reports', 'active' => $activeNav === 'reports'],
    ];

    // ============ RECORDS MANAGEMENT SETUP ============
    //
    // The modal is mounted here, once, rather than on each page: the reference
    // mock puts the setup gear on every page, so mounting it per page would mean
    // eight copies of the same markup and eight places for it to drift.
    //
    // `setup_tab` is flashed by the controllers when a write posts from inside a
    // tab. They redirect back to the page the form was on, so without it the modal
    // would close on save and the user would lose their place in it.
    $setupTabUrl = route('records.setup.tab', ['tab' => '__TAB__']);
    $setupInitialTab = session('setup_tab', 'classifications');
    $setupInitiallyOpen = session()->has('setup_tab');
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    {{--
        The Reports builder posts a preview fragment with fetch rather than a form
        post, so it needs a token it can read from the DOM. A page with no form of
        its own therefore carries no token at all and the request comes back as
        expired. Keeping it here lets any page make an authenticated request
        without carrying a hidden form field just for the sake of it.
    --}}
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'Records' }} — ORDO Records Management</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="h-full bg-ordo-bg font-sans text-ordo-ink antialiased">
<div class="grid h-screen grid-rows-[72px_1fr] overflow-hidden max-[860px]:grid-rows-[62px_1fr]">

    <header class="z-20 grid grid-cols-[195px_250px_1fr_160px] items-center border-b border-ordo-line bg-ordo-panel max-[1180px]:grid-cols-[72px_220px_1fr_120px] max-[860px]:grid-cols-[56px_1fr_100px]">
        <div class="pl-[22px] font-serif leading-[0.9] text-[16px] max-[1180px]:p-0 max-[1180px]:pl-[14px] max-[1180px]:text-[0] max-[1180px]:before:content-['JK&C'] max-[1180px]:before:text-[14px]">
            John Kelly<br>&amp; Company
            <small class="mt-1 block font-sans text-[10px] text-ordo-muted max-[1180px]:hidden">ORDO</small>
        </div>

        <div class="h-full max-[860px]:hidden"></div>

        <div class="relative w-[min(520px,90%)] justify-self-center max-[860px]:w-[92%]">
            <span class="absolute left-[15px] top-[9px] text-ordo-faint">⌕</span>
            <input type="search" name="global_search" aria-label="Global search"
                   placeholder="Search records, title, code, source..."
                   class="w-full rounded-full border-0 bg-[#f3f4f6] py-[11px] pl-10 pr-4 outline-none" />
        </div>

        <div class="flex items-center justify-end gap-4 pr-6 text-ordo-muted max-[1180px]:pr-5">
            <span title="Notifications" aria-hidden="true">♧</span>
            <div class="grid size-[34px] place-items-center rounded-full bg-[#eef1f5] text-ordo-muted" title="User profile">
                {{ mb_substr(auth()->user()->name ?? 'U', 0, 1) }}
            </div>
        </div>
    </header>

    <div class="grid min-h-0 grid-cols-[195px_250px_1fr] max-[1180px]:grid-cols-[72px_220px_1fr] max-[860px]:grid-cols-[56px_1fr]"
         x-data="{ subnavOpen: false }">

        <aside class="overflow-auto border-r border-ordo-line bg-ordo-panel px-2.5 py-[18px]">
            <div class="flex items-center gap-2.5 px-2.5 pb-4 font-bold text-[13px] max-[1180px]:justify-center max-[1180px]:px-0">
                <div class="grid size-7 place-items-center rounded-lg bg-[#f3f4f6] text-[14px]">◆</div>
                <span class="max-[1180px]:hidden">Enterprise Menu<small class="block text-[10px] font-normal text-ordo-faint">Navigation</small></span>
            </div>
            <div class="flex flex-col gap-1">
                @foreach ($railItems as $item)
                    <div @class([
                        'flex items-center gap-2.5 rounded-[9px] border px-3 py-2.5 text-[13px]',
                        'border-transparent text-[#4b5563] max-[1180px]:justify-center',
                        'bg-ordo-blue-soft text-ordo-blue border-ordo-blue-border' => $item['active'] ?? false,
                    ])>
                        <div @class([
                            'grid size-7 shrink-0 place-items-center rounded-lg bg-[#f3f4f6] text-[14px]',
                            'bg-[#dbe7ff]' => $item['active'] ?? false,
                        ])>{{ $item['icon'] }}</div>
                        <span class="max-[1180px]:hidden">{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </aside>

        <aside id="ordo-subnav"
               class="overflow-auto border-r border-ordo-line bg-ordo-panel px-2.5 py-[18px] max-[860px]:fixed max-[860px]:bottom-0 max-[860px]:left-14 max-[860px]:top-[62px] max-[860px]:z-30 max-[860px]:w-[220px] max-[860px]:shadow-[10px_0_20px_rgba(15,23,42,.08)] max-[860px]:transition-transform max-[860px]:-translate-x-[105%] max-[860px]:duration-200"
               x-bind:class="subnavOpen ? 'max-[860px]:translate-x-0' : ''">
            <div class="px-2.5 pb-3 text-[11px] uppercase tracking-[0.04em] text-ordo-muted">Records</div>
            <div class="flex flex-col gap-0.5">
                @foreach ($subnavItems as $item)
                    <a href="{{ isset($subnavRoutes[$item['view']]) ? route($subnavRoutes[$item['view']]) : '#' }}"
                       @class([
                           'flex w-full items-center justify-between rounded-lg px-3 py-2 text-[13px]',
                           'bg-ordo-blue-soft text-ordo-blue' => $item['active'],
                           'text-[#4b5563] hover:bg-ordo-bg' => ! $item['active'],
                       ])>
                        <span>{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? null) !== null)
                            <span class="rounded-full bg-ordo-blue-soft px-[7px] py-1 text-[9px] text-ordo-blue">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </aside>

        <main class="overflow-auto bg-ordo-bg p-5 max-[860px]:col-start-2 max-[860px]:p-2.5">
            <button type="button" x-on:click="subnavOpen = ! subnavOpen"
                    class="mb-3 hidden rounded-lg border border-ordo-line bg-ordo-panel px-3 py-2 text-[12px] text-ordo-muted max-[860px]:block">
                Menu
            </button>
            {{ $slot }}
        </main>
    </div>
</div>

{{-- One copy of the setup modal for every page; see the note above. --}}
@include('records.partials.setup-modal')
</body>
</html>

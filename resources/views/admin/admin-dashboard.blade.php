@extends('layouts.app')
@section('title', $pageTitle ?? 'Admin Dashboard')

@section('content')
<div class="w-full h-full px-6 py-5">

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl min-h-[calc(100vh-7rem)] flex flex-col">

        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h1 class="text-[30px] font-semibold text-gray-800 leading-none">{{ $pageTitle ?? 'Admin Dashboard' }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $pageDescription ?? 'Review module submissions and route approvals through admin.' }}</p>
            </div>

            @if(($currentSection ?? null) === 'town-hall' || str_contains(strtolower($pageTitle ?? ''), 'town hall'))
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.townhall.acknowledgement-report') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition">
                        <i class="fas fa-clipboard-check text-xs"></i>
                        Acknowledgment Report
                    </a>

                    <a href="{{ route('admin.townhall.audit-trail') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                        <i class="fas fa-chart-line text-xs"></i>
                        Audit Trail
                    </a>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 px-5 pt-5">
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-blue-600 uppercase">Pending Approval</p>
                        <h2 class="text-3xl font-bold text-blue-700 mt-2">{{ $pendingCount }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-green-600 uppercase">Approved</p>
                        <h2 class="text-3xl font-bold text-green-700 mt-2">{{ $approvedCount }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-red-600 uppercase">Rejected</p>
                        <h2 class="text-3xl font-bold text-red-700 mt-2">{{ $rejectedCount }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-yellow-600 uppercase">Needs Revision</p>
                        <h2 class="text-3xl font-bold text-yellow-700 mt-2">{{ $revisionCount }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center">
                        <i class="fas fa-rotate-left"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-600 uppercase">Expired</p>
                        <h2 class="text-3xl font-bold text-gray-700 mt-2">{{ $expiredCount ?? 0 }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                        <i class="fas fa-box-archive"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 pt-5">
            <form method="GET" action="{{ $dashboardRoute }}" class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col xl:flex-row xl:items-center gap-3 xl:justify-between">
                <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                    <div class="relative w-full md:w-80">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Search"
                            class="w-full bg-white border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                        >
                    </div>

                    @if($moduleOptions->count() > 1)
                        <select name="module" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                            <option value="all">All Modules</option>
                            @foreach($moduleOptions as $moduleOption)
                                <option value="{{ $moduleOption }}" @selected($filters['module'] === $moduleOption)>{{ $moduleOption }}</option>
                            @endforeach
                        </select>
                    @endif

                    @if($departmentOptions->count() > 1)
                        <select name="department" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                            <option value="all">All Departments</option>
                            @foreach($departmentOptions as $departmentOption)
                                <option value="{{ $departmentOption }}" @selected($filters['department'] === $departmentOption)>{{ $departmentOption }}</option>
                            @endforeach
                        </select>
                    @endif

                    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        <option value="all">All Status</option>
                        @foreach($statusOptions as $statusOption)
                            <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ $statusOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ $dashboardRoute }}" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white transition">
                        Reset
                    </a>
                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="px-5 py-5 flex-1 flex flex-col">
            <div class="border border-gray-200 rounded-xl overflow-hidden flex-1">
                <div class="overflow-x-auto h-full">
                    <table class="w-full text-sm text-left border-collapse whitespace-nowrap">
                        <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Ref#</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Module</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">File Name</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Department</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Uploaded By</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Date Uploaded</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Approval Level</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Current Approver</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Priority</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white text-gray-700">
                        @forelse($items as $item)
                            @php
                                $statusClasses = match($item->status) {
                                    'Approved' => 'bg-green-50 text-green-700',
                                    'Rejected' => 'bg-red-50 text-red-700',
                                    'Needs Revision' => 'bg-yellow-50 text-yellow-700',
                                    'Pending Management Approval' => 'bg-blue-50 text-blue-700',
                                    'Pending Executive Approval' => 'bg-indigo-50 text-indigo-700',
                                    'Expired' => 'bg-gray-200 text-gray-700',
                                    default => 'bg-blue-50 text-blue-700',
                                };

                                $moduleClasses = match($item->module) {
                                    'Town Hall' => 'bg-blue-50 text-blue-700',
                                    'Contacts' => 'bg-purple-50 text-purple-700',
                                    'Company' => 'bg-indigo-50 text-indigo-700',
                                    'Deals' => 'bg-amber-50 text-amber-700',
                                    'Project' => 'bg-cyan-50 text-cyan-700',
                                    'Regular' => 'bg-sky-50 text-sky-700',
                                    'Services' => 'bg-teal-50 text-teal-700',
                                    'Products' => 'bg-emerald-50 text-emerald-700',
                                    default => 'bg-gray-100 text-gray-700',
                                };

                                $priorityClasses = $item->priority === 'High'
                                    ? 'bg-red-50 text-red-700'
                                    : 'bg-yellow-50 text-yellow-700';
                            @endphp

                            <tr class="border-t border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-3 border-r border-gray-200">{{ $item->ref_no }}</td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $moduleClasses }} font-medium">
                                        {{ $item->module }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    {{ $item->file_name }}
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    {{ $item->department }}
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    {{ $item->uploaded_by }}
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    {{ $item->date_uploaded }}
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    @if(!empty($item->approval_level))
                                        <span class="px-2 py-1 text-xs rounded-full bg-indigo-50 text-indigo-700 font-medium">
                                            {{ $item->approval_level }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    <div class="font-medium text-gray-800">
                                        {{ $item->current_approver ?? $item->approver ?? '—' }}
                                    </div>

                                    @if(!empty($item->current_approver_position) || !empty($item->current_approver_department))
                                        <div class="text-xs text-gray-400 mt-1">
                                            {{ $item->current_approver_position ?? '—' }}
                                            @if(!empty($item->current_approver_department))
                                                · {{ $item->current_approver_department }}
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $priorityClasses }} font-medium">
                                        {{ $item->priority }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses }} font-medium">
                                        {{ $item->status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        @if($item->approve_route)
                                            <form action="{{ $item->approve_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition">
                                                    {{ $item->approve_label ?? 'Approve' }}
                                                </button>
                                            </form>
                                        @endif

                                        @if($item->reject_route)
                                            <form action="{{ $item->reject_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                                                    Reject
                                                </button>
                                            </form>
                                        @endif

                                        @if($item->revise_route)
                                            <form action="{{ $item->revise_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-800 text-white hover:bg-black transition">
                                                    Revise
                                                </button>
                                            </form>
                                        @endif

                                        <a
                                            href="{{ $item->show_route }}"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
                                        >
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                                    No approvals found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-between text-[11px] text-gray-500 px-1">
                <div class="flex items-center gap-6">
                    <span>Total Files <span class="text-gray-800 font-semibold">{{ $items->total() }}</span></span>
                    <span>Pending <span class="text-yellow-600 font-semibold">{{ $pendingCount }}</span></span>
                    <span>Approved <span class="text-green-600 font-semibold">{{ $approvedCount }}</span></span>
                    <span>Rejected <span class="text-red-600 font-semibold">{{ $rejectedCount }}</span></span>
                    <span>Expired <span class="text-gray-700 font-semibold">{{ $expiredCount ?? 0 }}</span></span>
                </div>

                <div class="flex items-center gap-4">
                    <span>{{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }}</span>
                </div>
            </div>

            @if(method_exists($items, 'links'))
                <div class="mt-4">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

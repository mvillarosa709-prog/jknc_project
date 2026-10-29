@extends('layouts.app')

@section('content')
<div class="w-full h-full px-6 py-5">

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @php
        $submittedCount = $policies->where('workflow_status', 'Submitted')->count();
        $acceptedCount = $policies->where('workflow_status', 'Accepted')->count();
        $rejectedCount = $policies->where('approval_status', 'Rejected')->count();
        $revertedCount = $policies->where('workflow_status', 'Reverted')->count();
        $archivedCount = $policies->where('is_archived', true)->count();
    @endphp

    <div class="bg-white border border-gray-200 rounded-xl min-h-[calc(100vh-7rem)] flex flex-col">
        <div class="px-5 py-4 border-b border-gray-200">
            <h1 class="text-[30px] font-semibold text-gray-800 leading-none">Policies Approval Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Review policy submissions for approval</p>
        </div>

        <div class="px-5 py-5 flex-1 flex flex-col gap-5">

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-5">
                    <p class="text-xs font-semibold tracking-wide text-blue-600 uppercase">Submitted</p>
                    <p class="mt-2 text-[20px] font-bold text-blue-700">{{ $submittedCount }}</p>
                </div>

                <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-5">
                    <p class="text-xs font-semibold tracking-wide text-green-600 uppercase">Accepted</p>
                    <p class="mt-2 text-[20px] font-bold text-green-700">{{ $acceptedCount }}</p>
                </div>

                <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-5">
                    <p class="text-xs font-semibold tracking-wide text-red-600 uppercase">Rejected</p>
                    <p class="mt-2 text-[20px] font-bold text-red-700">{{ $rejectedCount }}</p>
                </div>

                <div class="rounded-2xl border border-yellow-100 bg-yellow-50 px-4 py-5">
                    <p class="text-xs font-semibold tracking-wide text-yellow-600 uppercase">Reverted</p>
                    <p class="mt-2 text-[20px] font-bold text-yellow-700">{{ $revertedCount }}</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-100 px-4 py-5">
                    <p class="text-xs font-semibold tracking-wide text-gray-600 uppercase">Archived</p>
                    <p class="mt-2 text-[20px] font-bold text-gray-700">{{ $archivedCount }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.policies.index') }}"
                  class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
                    <div class="lg:col-span-6">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Search</label>
                        <div class="relative w-full">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                            <input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Search"
                                class="w-full bg-white border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                            >
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Module</label>
                        <select
                            name="module"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500"
                        >
                            <option value="">All Modules</option>
                            <option value="Policies" {{ request('module') === 'Policies' ? 'selected' : '' }}>Policies</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Workflow Status</label>
                        <select
                            name="status"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="Submitted" {{ request('status') === 'Submitted' ? 'selected' : '' }}>Submitted</option>
                            <option value="Accepted" {{ request('status') === 'Accepted' ? 'selected' : '' }}>Accepted</option>
                            <option value="Reverted" {{ request('status') === 'Reverted' ? 'selected' : '' }}>Reverted</option>
                            <option value="Archived" {{ request('status') === 'Archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-3">
                    <a href="{{ route('admin.policies.index') }}"
                       class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-xl text-gray-700 hover:bg-white transition">
                        Clear Filters
                    </a>

                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition"
                    >
                        Apply Filter
                    </button>
                </div>
            </form>

            <div class="border border-gray-200 rounded-xl overflow-hidden flex-1">
                <div class="w-full overflow-x-auto">
                <table class="policy-dashboard-table w-full text-sm text-left border-collapse table-fixed">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="w-[16%] px-4 py-3 border-r border-gray-200 font-semibold">Ref#</th>
                            <th class="w-[8%] px-4 py-3 border-r border-gray-200 font-semibold">Module</th>
                            <th class="w-[24%] px-4 py-3 border-r border-gray-200 font-semibold">Policy Title</th>
                            <th class="w-[7%] px-4 py-3 border-r border-gray-200 font-semibold">Version</th>
                            <th class="w-[13%] px-4 py-3 border-r border-gray-200 font-semibold">Prepared By</th>
                            <th class="w-[10%] px-4 py-3 border-r border-gray-200 font-semibold">Date Uploaded</th>
                            <th class="w-[10%] px-4 py-3 border-r border-gray-200 font-semibold">Workflow Status</th>
                            <th class="w-[12%] px-4 py-3 font-semibold text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white text-gray-700">
                        @forelse($policies as $policy)
                            <tr
                                class="border-t border-gray-200 hover:bg-gray-50 transition cursor-pointer align-top"
                                onclick="window.location='{{ route('admin.policies.show', $policy->id) }}'"
                            >
                                <td class="px-4 py-3 border-r border-gray-200 align-top"><span class="policy-break policy-clamp-2">{{ $policy->code ?? $policy->id }}</span></td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-50 text-blue-700 font-medium">
                                        Policies
                                    </span>
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200 align-top">
                                    <div class="policy-break policy-clamp-2 font-medium text-gray-800">{{ $policy->policy ?? '-' }}</div>
                                    @if(!empty($policy->classification))
                                        <div class="policy-break policy-clamp-1 text-xs text-gray-400 mt-1">{{ $policy->classification }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200 align-top"><span class="policy-break policy-clamp-1">{{ $policy->version ?? '-' }}</span></td>
                                <td class="px-4 py-3 border-r border-gray-200 align-top"><span class="policy-break policy-clamp-2">{{ $policy->prepared_by ?? '-' }}</span></td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    {{ optional($policy->created_at)->format('Y-m-d') ?? '-' }}
                                </td>

                                <td class="px-4 py-3 border-r border-gray-200">
                                    @php
                                        $workflow = $policy->workflow_status ?? 'Submitted';

                                        $workflowClasses = match($workflow) {
                                            'Accepted' => 'bg-green-50 text-green-700',
                                            'Reverted' => 'bg-yellow-50 text-yellow-700',
                                            'Archived' => 'bg-gray-200 text-gray-700',
                                            default => 'bg-blue-50 text-blue-700',
                                        };
                                    @endphp

                                    <span class="px-2 py-1 text-xs rounded-full font-medium {{ $workflowClasses }}">
                                        {{ $workflow }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2 flex-wrap">
                                        @if(!$policy->is_archived && Auth::user()->hasPermission('approve_policies'))
                                            <form method="POST" action="{{ route('admin.policies.approve', $policy->id) }}" onclick="event.stopPropagation()">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition"
                                                >
                                                    Approve
                                                </button>
                                            </form>
                                        @endif

                                        <a
                                            href="{{ route('admin.policies.show', $policy->id) }}"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                        >
                                            Review
                                        </a>

                                        <a
                                            href="{{ route('policies.edit', $policy->id) }}"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 text-white hover:bg-slate-900 transition"
                                        >
                                            Edit
                                        </a>

                                        @if(!$policy->is_archived && Auth::user()->hasPermission('approve_policies'))
                                            <form method="POST" action="{{ route('admin.policies.reject', $policy->id) }}" onclick="event.stopPropagation()">
                                                @csrf
                                                <input type="hidden" name="review_note" value="Rejected by admin">
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition"
                                                >
                                                    Reject
                                                </button>
                                            </form>
                                        @endif

                                        @if(!$policy->is_archived && Auth::user()->hasPermission('approve_policies'))
                                            <form method="POST" action="{{ route('admin.policies.revise', $policy->id) }}" onclick="event.stopPropagation()">
                                                @csrf
                                                <input type="hidden" name="review_note" value="Needs revision">
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-yellow-300 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 transition"
                                                >
                                                    Revise
                                                </button>
                                            </form>
                                        @endif

                                        @if(!$policy->is_archived && Auth::user()->hasPermission('approve_policies'))
                                            <form method="POST" action="{{ route('admin.policies.archive', $policy->id) }}" onclick="event.stopPropagation()">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
                                                >
                                                    Archive
                                                </button>
                                            </form>
                                        @endif

                                        @if($policy->is_archived && Auth::user()->hasPermission('approve_policies'))
                                            <form method="POST" action="{{ route('admin.policies.unarchive', $policy->id) }}" onclick="event.stopPropagation()">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                                                >
                                                    Unarchive
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No policy submissions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-3 flex flex-col gap-3 border-t border-gray-100 pt-3">
                <div class="flex flex-col gap-3 text-[11px] text-gray-500 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-6">
                        <span>
                            Total Files
                            <span class="text-gray-800 font-semibold">
                                {{ method_exists($policies, 'total') ? $policies->total() : $policies->count() }}
                            </span>
                        </span>

                        <span>
                            Pending
                            <span class="text-yellow-600 font-semibold">{{ $submittedCount }}</span>
                        </span>

                        <span>
                            Approved
                            <span class="text-green-600 font-semibold">{{ $acceptedCount }}</span>
                        </span>

                        <span>
                            Rejected
                            <span class="text-red-600 font-semibold">{{ $rejectedCount }}</span>
                        </span>

                        <span>
                            Expired
                            <span class="text-gray-700 font-semibold">{{ $archivedCount }}</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-4">
                        <span>
                            {{ method_exists($policies, 'firstItem') ? ($policies->firstItem() ?? 0) : 0 }}
                            to
                            {{ method_exists($policies, 'lastItem') ? ($policies->lastItem() ?? 0) : $policies->count() }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="text-sm text-gray-700">
                        Showing
                        <span class="font-semibold">
                            {{ method_exists($policies, 'firstItem') ? ($policies->firstItem() ?? 0) : 0 }}
                        </span>
                        to
                        <span class="font-semibold">
                            {{ method_exists($policies, 'lastItem') ? ($policies->lastItem() ?? 0) : $policies->count() }}
                        </span>
                        of
                        <span class="font-semibold">
                            {{ method_exists($policies, 'total') ? $policies->total() : $policies->count() }}
                        </span>
                        results
                    </div>

                    @if(method_exists($policies, 'links'))
                        <div class="flex justify-end">
                            {{ $policies->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


@push('styles')
<style>
    .policy-dashboard-table {
        table-layout: fixed !important;
        width: 100% !important;
    }

    .policy-dashboard-table th,
    .policy-dashboard-table td {
        min-width: 0 !important;
        max-width: 100% !important;
        vertical-align: top !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
        white-space: normal !important;
    }

    .policy-break {
        display: block !important;
        min-width: 0 !important;
        max-width: 100% !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
        white-space: normal !important;
    }

    .policy-clamp-1 {
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    .policy-clamp-2 {
        display: -webkit-box !important;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden !important;
    }

    .policy-dashboard-table form,
    .policy-dashboard-table a,
    .policy-dashboard-table button {
        max-width: 100% !important;
    }
</style>
@endpush

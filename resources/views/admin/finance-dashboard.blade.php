@extends('layouts.app')
@section('title', 'Finance Dashboard')

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

    <div class="bg-white border border-gray-200 rounded-xl min-h-[calc(100vh-7rem)] flex flex-col">
        <div class="px-5 py-4 border-b border-gray-200">
            <h1 class="text-[30px] font-semibold text-gray-800 leading-none">Finance Approval Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Admin review for submitted finance records and deletion requests.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 px-5 pt-5">
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-blue-600 uppercase">Submitted</p>
                <h2 class="text-3xl font-bold text-blue-700 mt-2">{{ $counts['submitted'] }}</h2>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-green-600 uppercase">Accepted</p>
                <h2 class="text-3xl font-bold text-green-700 mt-2">{{ $counts['accepted'] }}</h2>
            </div>
            <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-red-600 uppercase">Delete Requests</p>
                <h2 class="text-3xl font-bold text-red-700 mt-2">{{ $counts['delete_requested'] }}</h2>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                <p class="text-xs font-semibold text-gray-600 uppercase">Deleted</p>
                <h2 class="text-3xl font-bold text-gray-700 mt-2">{{ $counts['deleted'] }}</h2>
            </div>
        </div>

        @if(!empty($inventoryHistoryBoard))
            <div class="px-5 pt-5">
                <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 via-white to-blue-50 p-4 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Inventory History Board</h2>
                            <p class="mt-1 text-sm text-gray-500">Shared transparency feed for inventory movements. Employees and admins can review stock in, stock out, transfer, return, and adjustment activity here.</p>
                            <p class="mt-2 text-xs text-gray-500">Available Quantity = Current Quantity - Reserved Quantity. Total Cost = Current Quantity x Unit Cost.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="document.getElementById('adminInventoryHistoryBoardRail')?.scrollBy({ left: -420, behavior: 'smooth' })"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-800"
                                aria-label="Scroll inventory history left"
                            >
                                <i class="fas fa-chevron-left text-xs"></i>
                            </button>
                            <button
                                type="button"
                                onclick="document.getElementById('adminInventoryHistoryBoardRail')?.scrollBy({ left: 420, behavior: 'smooth' })"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-800"
                                aria-label="Scroll inventory history right"
                            >
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                            <div class="rounded-full border border-blue-100 bg-white px-3 py-1 text-xs font-medium text-blue-700">
                                {{ count($inventoryHistoryBoard) }} recent updates
                            </div>
                        </div>
                    </div>

                    <div id="adminInventoryHistoryBoardRail" class="flex gap-3 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 no-scrollbar">
                        @foreach($inventoryHistoryBoard as $entry)
                            <article class="snap-start rounded-xl border border-gray-200 bg-white p-4 shadow-sm min-w-[280px] md:min-w-[360px]" style="flex: 0 0 calc((100% - 1.5rem) / 3);">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $entry['record_title'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $entry['record_number'] }}</p>
                                    </div>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ $entry['action'] }}</span>
                                </div>
                                <div class="mt-3 space-y-2 text-sm text-gray-600">
                                    <p>
                                        <span class="font-medium text-gray-800">{{ $entry['changed_by'] }}</span>
                                        <span class="text-gray-400">/</span>
                                        <span>{{ $entry['changed_at'] }}</span>
                                    </p>
                                    <p class="text-gray-700">{{ $entry['reason'] ?: 'Movement recorded in the inventory audit trail.' }}</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ $entry['action'] === 'Stock Transfer' ? 'From' : 'Quantity' }}</p>
                                            <p class="font-medium text-gray-800">
                                                {{ $entry['action'] === 'Stock Transfer'
                                                    ? collect([$entry['previous_location'] ?: null, $entry['previous_department'] ?: null])->filter()->implode(' / ') ?: '-'
                                                    : ($entry['quantity'] ?? '-') }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ $entry['action'] === 'Stock Transfer' ? 'To' : 'Available' }}</p>
                                            <p class="font-medium text-gray-800">
                                                {{ $entry['action'] === 'Stock Transfer'
                                                    ? collect([$entry['current_location'] ?: null, $entry['current_department'] ?: null])->filter()->implode(' / ') ?: '-'
                                                    : ($entry['available_quantity'] ?? '-') }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        {{ collect([$entry['location'] ?: null, $entry['department'] ?: null])->filter()->implode(' / ') ?: 'Location and department not set' }}
                                    </p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="px-5 pt-5">
            <form method="GET" action="{{ route('admin.finance.dashboard') }}" class="bg-gray-50 border border-gray-200 rounded-xl p-4 grid grid-cols-1 xl:grid-cols-[1fr_auto] gap-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="relative w-full">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Search"
                            class="w-full bg-white border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                        >
                    </div>
                    <select name="module" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        <option value="all">All Finance Sections</option>
                        @foreach($moduleLabels as $moduleKey => $moduleLabel)
                            <option value="{{ $moduleKey }}" @selected($filters['module'] === $moduleKey)>{{ $moduleLabel }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        <option value="all">All Workflow Statuses</option>
                        @foreach($workflowStatuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('admin.finance.dashboard') }}" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white transition">Reset</a>
                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Apply</button>
                </div>
            </form>
        </div>

        <div class="px-5 py-5 flex-1 flex flex-col">
            <div class="border border-gray-200 rounded-xl overflow-hidden flex-1">
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Record</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Section</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Title</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Requested By</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Updated</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Workflow</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Approval</th>
                            <th class="px-4 py-3 font-semibold text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white text-gray-700">
                        @forelse($records as $record)
                            @php
                                $workflow = $record->workflow_status ?: 'Uploaded';
                                $statusClasses = match($workflow) {
                                    'Accepted' => 'bg-green-50 text-green-700',
                                    'Submitted' => 'bg-blue-50 text-blue-700',
                                    'Delete Requested' => 'bg-red-50 text-red-700',
                                    'Deleted' => 'bg-gray-200 text-gray-700',
                                    'Archived' => 'bg-gray-100 text-gray-700',
                                    'Reverted' => 'bg-yellow-50 text-yellow-700',
                                    default => 'bg-orange-50 text-orange-700',
                                };
                                $deleteRequester = data_get($record->data, 'delete_requested_by_name');
                            @endphp
                            <tr class="border-t border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-3 border-r border-gray-200">{{ $record->record_number ?: 'FIN-'.$record->id }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $moduleLabels[$record->module_key] ?? Str::headline($record->module_key) }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <div class="font-medium text-gray-900">{{ $record->record_title ?: 'Finance Record #'.$record->id }}</div>
                                    @if($deleteRequester)
                                        <div class="text-xs text-red-600 mt-1">Delete requested by {{ $deleteRequester }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $record->user ?: ($deleteRequester ?: 'Unknown') }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ optional($record->updated_at)->format('M d, Y') }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses }} font-medium">{{ $workflow }}</span>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $record->approval_status ?: 'Pending' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2 flex-wrap">
                                        @if($workflow === 'Submitted' && $record->dashboard_can_approve)
                                            <form method="POST" action="{{ route('finance.approve', $record) }}">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition">Approve</button>
                                            </form>
                                        @endif

                                        @if($workflow === 'Submitted' && $record->dashboard_can_revert)
                                            <form method="POST" action="{{ route('finance.revert', $record) }}">
                                                @csrf
                                                <input
                                                    type="text"
                                                    name="reason"
                                                    required
                                                    maxlength="1000"
                                                    placeholder="Reason for revision"
                                                    class="w-52 rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-700 placeholder-gray-400 focus:border-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-100"
                                                >
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-yellow-600 text-white hover:bg-yellow-700 transition">Revise</button>
                                            </form>
                                        @endif

                                        @if($workflow === 'Submitted' && !$record->dashboard_can_approve)
                                            <span class="text-[11px] text-gray-500">Approval is limited to the assigned approver.</span>
                                        @endif

                                        @if($workflow === 'Delete Requested' && $record->dashboard_can_approve_delete)
                                            <form method="POST" action="{{ route('admin.finance.delete.approve', $record) }}">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition">Approve Delete</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.finance.delete.reject', $record) }}">
                                                @csrf
                                                <input type="hidden" name="review_note" value="Deletion request rejected from finance dashboard.">
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Reject Delete</button>
                                            </form>
                                        @endif

                                        @if(in_array($workflow, ['Accepted', 'Reverted'], true) && $record->dashboard_can_archive)
                                            <form method="POST" action="{{ route('finance.archive', $record) }}">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-700 text-white hover:bg-gray-800 transition">Archive</button>
                                            </form>
                                        @endif

                                        @if($workflow === 'Archived' && $record->dashboard_can_unarchive)
                                            <form method="POST" action="{{ route('admin.finance.unarchive', $record) }}">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Unarchive</button>
                                            </form>
                                        @endif

                                        <a href="{{ route('finance.preview.html', $record) }}" target="_blank" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Preview</a>
                                        <a href="{{ route('finance.record.open', $record) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Open Module</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No finance records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Human Capital Admin Dashboard')

@section('content')
<div class="w-full h-full px-6 py-5">

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold mb-1">Please check the following:</p>
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl min-h-[calc(100vh-7rem)] flex flex-col">

        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h1 class="text-[30px] font-semibold text-gray-800 leading-none">Human Capital Dashboard</h1>
                <p class="text-sm text-gray-500 mt-1">Review Human Capital records that need admin action.</p>
            </div>
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
                        <p class="text-xs font-semibold text-gray-600 uppercase">Total Records</p>
                        <h2 class="text-3xl font-bold text-gray-700 mt-2">{{ $totalCount }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                        <i class="fas fa-folder-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 pt-5">
            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <a href="{{ route('admin.human-capital.dashboard', ['view' => 'approvals']) }}" class="px-4 py-2 {{ $activeView === 'approvals' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Approvals
                </a>
                <a href="{{ route('admin.human-capital.dashboard', ['view' => 'logs']) }}" class="px-4 py-2 border-l border-gray-200 {{ $activeView === 'logs' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Human Capital Logs
                </a>
            </div>
        </div>

        @if($activeView === 'approvals')
        <div class="px-5 pt-5">
            <form method="GET" action="{{ route('admin.human-capital.dashboard') }}" class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col xl:flex-row xl:items-center gap-3 xl:justify-between">
                <input type="hidden" name="view" value="approvals">
                <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                    <div class="relative w-full md:w-96">
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
                        <option value="all">All Modules</option>
                        @foreach($moduleOptions as $moduleOption)
                            <option value="{{ $moduleOption }}" @selected($filters['module'] === $moduleOption)>{{ $moduleOption }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        <option value="all">All Status</option>
                        @foreach($statusOptions as $statusOption)
                            <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ $statusOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.human-capital.dashboard') }}" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white transition">
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
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Ref#</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Module</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Employee</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Record / Request</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Department</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Date Submitted</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Priority</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr class="border-t border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700 whitespace-nowrap">{{ $item->ref_no }}</td>
                                <td class="px-4 py-3 border-r border-gray-100 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                        {{ $item->module }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700 whitespace-nowrap">{{ $item->employee }}</td>
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700">{{ $item->record_name }}</td>
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700 whitespace-nowrap">{{ $item->department }}</td>
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700 whitespace-nowrap">{{ $item->date_submitted }}</td>
                                <td class="px-4 py-3 border-r border-gray-100 whitespace-nowrap">
                                    @php
                                        $priorityClass = strtolower($item->priority) === 'high'
                                            ? 'bg-red-50 text-red-700'
                                            : 'bg-yellow-50 text-yellow-700';
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $priorityClass }}">
                                        {{ $item->priority }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-100 whitespace-nowrap">
                                    @php
                                        $statusClass = match($item->status) {
                                            'Pending Approval' => 'bg-blue-50 text-blue-700',
                                            'Approved' => 'bg-green-50 text-green-700',
                                            'Rejected' => 'bg-red-50 text-red-700',
                                            'Needs Revision' => 'bg-yellow-50 text-yellow-700',
                                            default => 'bg-gray-50 text-gray-700',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ $item->details_route }}" class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                            View
                                        </a>

                                        @if($item->approve_route)
                                            <form method="POST" action="{{ $item->approve_route }}" onsubmit="return confirm('{{ $item->approve_label ?? 'Approve' }} this record?')">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-green-600 text-white hover:bg-green-700">
                                                    {{ $item->approve_label ?? 'Approve' }}
                                                </button>
                                            </form>
                                        @endif

                                        @if($item->reject_route)
                                            <form method="POST" action="{{ $item->reject_route }}" onsubmit="return confirm('{{ $item->reject_label ?? 'Reject' }} this record?')">
                                                @csrf
                                                @if($item->reject_note_name)
                                                    <input type="hidden" name="{{ $item->reject_note_name }}" value="Rejected from Human Capital admin dashboard.">
                                                @endif
                                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-red-600 text-white hover:bg-red-700">
                                                    {{ $item->reject_label ?? 'Reject' }}
                                                </button>
                                            </form>
                                        @endif

                                        @if($item->revise_route)
                                            <form method="POST" action="{{ $item->revise_route }}" onsubmit="return confirm('Send this record back for revision?')">
                                                @csrf
                                                @if($item->revise_note_name)
                                                    <input type="hidden" name="{{ $item->revise_note_name }}" value="Please revise and resubmit this request.">
                                                @endif
                                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-yellow-500 text-white hover:bg-yellow-600">
                                                    {{ $item->revise_label ?? 'Revise' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                                    No Human Capital records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>
        @else
        <div class="px-5 pt-5">
            <form method="GET" action="{{ route('admin.human-capital.dashboard') }}" class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col xl:flex-row xl:items-center gap-3 xl:justify-between">
                <input type="hidden" name="view" value="logs">
                <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                    <div class="relative w-full md:w-96">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <input
                            type="text"
                            name="log_search"
                            value="{{ $logFilters['search'] }}"
                            placeholder="Search"
                            class="w-full bg-white border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                        >
                    </div>

                    <select name="log_module" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        <option value="all">All Modules</option>
                        @foreach($logModuleOptions as $moduleOption)
                            <option value="{{ $moduleOption }}" @selected($logFilters['module'] === $moduleOption)>{{ $moduleOption }}</option>
                        @endforeach
                    </select>

                    <select name="log_action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        <option value="all">All Actions</option>
                        @foreach($logActionOptions as $actionOption)
                            <option value="{{ $actionOption }}" @selected($logFilters['action'] === $actionOption)>{{ str($actionOption)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.human-capital.dashboard', ['view' => 'logs']) }}" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white transition">
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
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Date / Time</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Module</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Action</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Record</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Details</th>
                            <th class="px-4 py-3 font-semibold">Changed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr class="border-t border-gray-200 hover:bg-gray-50 align-top">
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700 whitespace-nowrap">
                                    {{ optional($log->logged_at)->format('M d, Y g:i A') ?: '-' }}
                                </td>
                                <td class="px-4 py-3 border-r border-gray-100 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                        {{ $log->module }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-100 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                        {{ str($log->action)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700">
                                    <div class="font-semibold text-gray-900">{{ $log->subject_name ?: '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-100 text-gray-700">
                                    @php
                                        $oldValues = collect($log->old_values ?? []);
                                        $newValues = collect($log->new_values ?? []);
                                        $changedFields = $newValues->keys()->take(8);
                                    @endphp
                                    @if($changedFields->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach($changedFields as $field)
                                                <div>
                                                    <span class="font-semibold text-gray-900">{{ str($field)->replace('_', ' ')->title() }}:</span>
                                                    <span class="text-gray-500">{{ is_array($oldValues->get($field)) ? json_encode($oldValues->get($field)) : ($oldValues->get($field) ?? '-') }}</span>
                                                    <span class="text-gray-400">-></span>
                                                    <span class="text-gray-900">{{ is_array($newValues->get($field)) ? json_encode($newValues->get($field)) : ($newValues->get($field) ?? '-') }}</span>
                                                </div>
                                            @endforeach
                                            @if($newValues->count() > 8)
                                                <div class="text-xs text-gray-500">and {{ $newValues->count() - 8 }} more change(s)</div>
                                            @endif
                                        </div>
                                    @else
                                        <div>{{ $log->description ?: str($log->action)->replace('_', ' ')->title() }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                    <div class="font-medium">{{ $log->user_name ?: 'System User' }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->ip_address ?: '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center text-gray-400">
                                    No Human Capital logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

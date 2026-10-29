@extends('layouts.app')
@section('title', 'Correspondence Approval Dashboard')

@section('content')
<div class="w-full px-6 py-5">
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">Correspondence Approval Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Review corporate correspondence submissions for approval.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 p-6">
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <p class="text-xs font-bold uppercase text-blue-700">Submitted</p>
                <p class="text-2xl font-bold text-blue-700 mt-3">{{ $stats['submitted'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-green-100 bg-green-50 p-4">
                <p class="text-xs font-bold uppercase text-green-700">Accepted</p>
                <p class="text-2xl font-bold text-green-700 mt-3">{{ $stats['accepted'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-yellow-100 bg-yellow-50 p-4">
                <p class="text-xs font-bold uppercase text-yellow-700">Reverted</p>
                <p class="text-2xl font-bold text-yellow-700 mt-3">{{ $stats['reverted'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                <p class="text-xs font-bold uppercase text-orange-700">Uploaded</p>
                <p class="text-2xl font-bold text-orange-700 mt-3">{{ $stats['uploaded'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase text-gray-700">Archived</p>
                <p class="text-2xl font-bold text-gray-700 mt-3">{{ $stats['archived'] ?? 0 }}</p>
            </div>
        </div>

        <form method="GET" class="mx-6 mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_220px_220px_auto] gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Search</label>
                    <div class="relative w-full">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search"
                            class="w-full bg-white border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                        >
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Type</label>
                    <select name="type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="All">All Types</option>
                        @foreach(($types ?? []) as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Workflow Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">All Status</option>
                        @foreach(['Uploaded', 'Submitted', 'Accepted', 'Reverted', 'Archived'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('admin.correspondence.dashboard') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Clear
                    </a>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Apply
                    </button>
                </div>
            </div>
        </form>

        <div class="mx-6 mb-6 overflow-hidden rounded-xl border border-gray-200">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Ref#</th>
                            <th class="px-4 py-3 text-left font-semibold">Type</th>
                            <th class="px-4 py-3 text-left font-semibold">Company</th>
                            <th class="px-4 py-3 text-left font-semibold">Subject</th>
                            <th class="px-4 py-3 text-left font-semibold">From</th>
                            <th class="px-4 py-3 text-left font-semibold">To / For</th>
                            <th class="px-4 py-3 text-left font-semibold">Workflow</th>
                            <th class="px-4 py-3 text-left font-semibold">Approval</th>
                            <th class="px-4 py-3 text-left font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($correspondences as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 font-medium text-gray-900 whitespace-nowrap">{{ $item->ref_no ?: 'COR-' . str_pad($item->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-4">{{ $item->type ?: '—' }}</td>
                                <td class="px-4 py-4 max-w-[220px] truncate">{{ $item->company_name ?: '—' }}</td>
                                <td class="px-4 py-4 max-w-[260px]">
                                    <p class="font-medium text-gray-900 break-words">{{ $item->subject ?: '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ optional($item->correspondence_date)->format('M d, Y') ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-4 max-w-[160px] break-words">{{ $item->from_name ?: '—' }}</td>
                                <td class="px-4 py-4 max-w-[160px] break-words">{{ $item->to_for ?: '—' }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        @class([
                                            'bg-orange-50 text-orange-700' => $item->workflow_status === 'Uploaded',
                                            'bg-blue-50 text-blue-700' => $item->workflow_status === 'Submitted',
                                            'bg-green-50 text-green-700' => $item->workflow_status === 'Accepted',
                                            'bg-yellow-50 text-yellow-700' => $item->workflow_status === 'Reverted',
                                            'bg-gray-100 text-gray-700' => $item->workflow_status === 'Archived',
                                        ])">
                                        {{ $item->workflow_status ?: '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">{{ $item->approval_status ?: '—' }}</td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.correspondence.show', $item->id) }}" class="inline-flex rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-gray-500">No correspondence records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-3">
                {{ $correspondences->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

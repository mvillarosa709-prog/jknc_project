@extends('layouts.app')
@section('title', 'Transmittal Dashboard')

@section('content')
@php
    $tabs = [
        'submitted' => ['label' => 'Pending Approval', 'status' => 'Submitted'],
        'accepted' => ['label' => 'Approved', 'status' => 'Accepted'],
        'reverted' => ['label' => 'For Revision / Reverted', 'status' => 'Reverted'],
        'archived' => ['label' => 'Archived', 'status' => 'Archived'],
    ];
@endphp

<div class="w-full px-6 py-5">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-[30px] font-semibold text-gray-800 leading-none">Transmittal Dashboard</h1>
            <p class="mt-2 text-sm text-gray-500">Review submitted transmittals and manage approval decisions here.</p>
        </div>

        <a href="{{ route('transmittal.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            <i class="fas fa-arrow-left"></i>
            Back to Transmittal
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4 mb-5">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pending Approval</div>
            <div class="mt-2 text-3xl font-bold text-amber-800">{{ $counts['submitted'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Approved</div>
            <div class="mt-2 text-3xl font-bold text-emerald-800">{{ $counts['accepted'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-red-700">For Revision / Reverted</div>
            <div class="mt-2 text-3xl font-bold text-red-800">{{ $counts['reverted'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Archived</div>
            <div class="mt-2 text-3xl font-bold text-slate-800">{{ $counts['archived'] ?? 0 }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="border-b border-gray-200 px-5 pt-4">
            <div class="flex gap-6 overflow-x-auto text-sm font-semibold text-gray-600">
                @foreach($tabs as $key => $tab)
                    <a href="{{ route('admin.transmittal.dashboard', ['status' => $key]) }}"
                       class="whitespace-nowrap border-b-2 pb-3 {{ $activeTab === $key ? 'border-blue-600 text-blue-700' : 'border-transparent hover:text-gray-900' }}">
                        {{ $tab['label'] }}
                        <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            {{-- SEARCH BAR (MATCHING SS2 PILL SHAPE) --}}
            <div class="py-4">
                <div class="relative w-full">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input
                        id="dashboardTransmittalSearch"
                        type="text"
                        placeholder="Search"
                        oninput="filterDashboardTable(this.value)"
                        class="w-full bg-gray-100 focus:bg-white border border-transparent focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                    >
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Ref No.</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Mode</th>
                        <th class="px-4 py-3">From</th>
                        <th class="px-4 py-3">To</th>
                        <th class="px-4 py-3">Delivery</th>
                        <th class="px-4 py-3">Prepared By</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($transmittals as $transmittal)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $transmittal->transmittal_no ?? ('TRN-' . $transmittal->id) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ optional($transmittal->transmittal_date)->format('Y-m-d') ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transmittal->mode ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $transmittal->from_value ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $transmittal->to_value ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transmittal->delivery_summary ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transmittal->prepared_by_name ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match($transmittal->workflow_status) {
                                        'Submitted' => 'bg-amber-100 text-amber-700',
                                        'Accepted' => 'bg-emerald-100 text-emerald-700',
                                        'Reverted' => 'bg-red-100 text-red-700',
                                        'Archived' => 'bg-slate-100 text-slate-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge }}">
                                    {{ $transmittal->workflow_status ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('transmittal.preview', $transmittal->id) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        View
                                    </a>

                                    @if($transmittal->workflow_status === 'Submitted')
                                        <form method="POST" action="{{ route('corporate.approvals.approve', ['module' => 'transmittal', 'id' => $transmittal->id]) }}" onsubmit="return confirm('Approve this transmittal?');">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                Approve
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('corporate.approvals.revise', ['module' => 'transmittal', 'id' => $transmittal->id]) }}" onsubmit="return confirm('Send this transmittal back for revision?');">
                                            @csrf
                                            <input type="hidden" name="review_note" value="Please revise this transmittal.">
                                            <button type="submit" class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600">
                                                Revise
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('corporate.approvals.reject', ['module' => 'transmittal', 'id' => $transmittal->id]) }}" onsubmit="return confirm('Reject this transmittal?');">
                                            @csrf
                                            <input type="hidden" name="review_note" value="Transmittal rejected.">
                                            <button type="submit" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                Reject
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($transmittal->workflow_status, ['Accepted', 'Reverted'], true))
                                        <form method="POST" action="{{ route('corporate.approvals.archive', ['module' => 'transmittal', 'id' => $transmittal->id]) }}" onsubmit="return confirm('Archive this transmittal?');">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                                                Archive
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                No transmittals found for this tab.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-5 py-4">
            {{ $transmittals->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
function filterDashboardTable(query) {
    const term = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        // skip empty placeholder row
        if (row.querySelector('td[colspan]')) return;
        const text = row.innerText.toLowerCase();
        if (!term || text.includes(term)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
@endpush
@endsection

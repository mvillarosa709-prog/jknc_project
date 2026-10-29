@extends('layouts.app')

@section('title', 'Transmittals')

@push('styles')
    @vite(['resources/css/transmittal.css'])
@endpush

@push('scripts')
    @vite(['resources/js/transmittal.js'])
@endpush

@section('content')
<div class="page transmittal-index-page">
    @php
        $total = $summary->sum();

        $filterCounts = [
            'all' => $total,
            'uploaded' => $summary['draft'] ?? 0,
            'submitted' => $summary['pending_approval'] ?? 0,
            'accepted' => ($summary['approved'] ?? 0)
                + ($summary['sent'] ?? 0)
                + ($summary['acknowledged'] ?? 0),
            'reverted' => $summary['rejected'] ?? 0,
            'archived' => $summary['cancelled'] ?? 0,

        ];

        $filters = [
            'all' => 'All',
            'uploaded' => 'Uploaded',
            'submitted' => 'Submitted',
            'accepted' => 'Accepted',
            'reverted' => 'Reverted',
            'archived' => 'Archived',
        ];
    @endphp

    {{-- Dashboard Header --}}
    <div class="transmittal-heading">
        <div>
            <h1>Transmittal</h1>
        </div>

        <div class="heading-actions">
            <a
                class="button primary smart-upload-button"
                href="{{ route('transmittals.smart-upload') }}"
            >
                Smart Upload
            </a>

            <button
                class="button primary add-button"
                type="button"
                data-open-drawer
            >
                <span class="button-plus">＋</span>
                Add
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="metric-row">
        <div class="metric-card">
            <span class="metric-label">Total</span>
            <strong>{{ $total }}</strong>
        </div>

        <div class="metric-card">
            <span class="metric-label">Pending Approval</span>
            <strong>{{ $summary['pending_approval'] ?? 0 }}</strong>
        </div>

        <div class="metric-card">
            <span class="metric-label">Awaiting Acknowledgment</span>
            <strong>{{ $summary['sent'] ?? 0 }}</strong>
        </div>

        <div class="metric-card">
            <span class="metric-label">Received</span>
            <strong>{{ $summary['acknowledged'] ?? 0 }}</strong>
        </div>
    </div>

    {{-- Transmittal Register --}}
    <section class="panel register-panel" aria-label="Transmittal register">

        {{-- Status Filters --}}
        <nav
            class="status-tabs"
            aria-label="Filter transmittals by status"
        >
            @foreach ($filters as $key => $label)
                @if (
                    $key !== 'deleted'
                    || auth()->user()?->canApproveTransmittals()
                )
                    <a
                        class="status-tab {{ $filter === $key ? 'active' : '' }}"
                        href="{{ route('transmittals.index', array_filter([
                            'filter' => $key === 'all' ? null : $key,
                            'q' => $search,
                        ])) }}"
                        @if ($filter === $key)
                            aria-current="page"
                        @endif
                    >
                        {{ $label }}
                        <span class="tab-count">
                            {{ $filterCounts[$key] }}
                        </span>
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Search --}}
        <div class="register-tools">
            <p class="register-hint">
                @if ($filter === 'uploaded')
                    These transmittals are saved drafts. Continue working on them when ready.
                @elseif ($filter === 'submitted')
                    These transmittals are waiting for an administrator decision.
                @elseif ($filter === 'archived')
                    View archived transmittal records.
                @else
                    View transmittal records or filter them by status.
                @endif
            </p>

            <form
                class="register-search"
                method="get"
                action="{{ route('transmittals.index') }}"
            >
                @if ($filter !== 'all')
                    <input
                        type="hidden"
                        name="filter"
                        value="{{ $filter }}"
                    >
                @endif

                <label class="sr-only" for="register-search">
                    Search transmittals
                </label>

                <div class="relative w-full">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input
                        id="register-search"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Search"
                        class="w-full bg-gray-100 focus:bg-white border border-transparent focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                    >
                </div>
            </form>
        </div>

        {{-- Dashboard Table: No Action Columns --}}
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ref#</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Delivery Type</th>
                        <th>Workflow Status</th>
                        <th>Approval Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($transmittals as $transmittal)
                        @php
                            $workflow = [
                                'draft' => 'Uploaded',
                                'pending_approval' => 'Submitted',
                                'approved' => 'Accepted',
                                'sent' => 'Accepted',
                                'acknowledged' => 'Accepted',
                                'rejected' => 'Reverted',
                                'cancelled' => 'Archived',
                            ][$transmittal->status] ?? $transmittal->status;

                            $approval = [
                                'draft' => 'Draft',
                                'pending_approval' => 'Pending',
                                'approved' => 'Approved',
                                'sent' => 'Approved',
                                'acknowledged' => 'Approved',
                                'rejected' => 'Rejected',
                                'cancelled' => '—',
                            ][$transmittal->status] ?? '—';

                            $recipientNames = $transmittal->recipients
                                ->map(fn ($recipient) =>
                                    $recipient->name
                                    ?: ($recipient->organization ?: $recipient->email)
                                )
                                ->filter()
                                ->implode(', ');

                            $officeName = $transmittal->origin_office
                                ?: config('ordo.office_name');

                            $from = $transmittal->direction === 'receive'
                                ? ($recipientNames ?: '—')
                                : $officeName;

                            $to = $transmittal->direction === 'receive'
                                ? $officeName
                                : (
                                    $recipientNames
                                    ?: $transmittal->related_company
                                    ?: '—'
                                );
                        @endphp

                        <tr>
                            {{-- Ref#: Click to open the Workspace --}}
                            <td>
                                <a
                                    class="tracking-link"
                                    href="{{ route('transmittals.workspace', $transmittal) }}"
                                >
                                    {{ $transmittal->tracking_number ?: '—' }}
                                </a>
                            </td>

                            {{-- Date --}}
                            <td>
                                {{ $transmittal->transmittal_date?->format('M j, Y')
                                    ?? $transmittal->created_at?->format('M j, Y')
                                    ?? '—' }}
                            </td>

                            {{-- Mode --}}
                            <td>
                                <span class="mode-badge" style="color: #111827 !important; background: #f1f5f9; font-weight: 700;">
                                    {{ strtoupper($transmittal->direction ?? 'send') }}
                                </span>
                            </td>

                            {{-- From --}}
                            <td>{{ $from }}</td>

                            {{-- To --}}
                            <td
                                class="recipient-cell"
                                title="{{ $to }}"
                            >
                                {{ $to }}
                            </td>

                            {{-- Delivery Type --}}
                            <td>
                                {{ str($transmittal->delivery_type ?? 'by_person')
                                    ->replace('_', ' ')
                                    ->title() }}
                            </td>

                            {{-- Workflow Status --}}
                            <td>
                                <span class="status-badge status-{{ $transmittal->status }}">
                                    {{ $workflow }}
                                </span>
                            </td>

                            {{-- Approval Status --}}
                            <td>
                                <span class="status-badge status-{{ strtolower($approval) }}">
                                    {{ $approval }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <strong>No records found.</strong>

                                    @if ($filter === 'uploaded')
                                        <p>
                                            Save a draft from Add or Smart Upload
                                            to see it here.
                                        </p>
                                    @elseif ($filter === 'deleted')
                                        <p>No deleted records found.</p>
                                    @elseif ($filter === 'archived')
                                        <p>No archived records found.</p>
                                    @else
                                        <p>
                                            {{ $search
                                                ? 'Try changing your search.'
                                                : 'Start by adding a transmittal.' }}
                                        </p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="pagination">
            {{ $transmittals->links() }}
        </div>
    </section>

    {{-- Document Preview --}}
    <section
        class="add-document-preview"
        data-document-preview-pane
        aria-label="Live two-page transmittal preview"
    >
        <div class="add-preview-toolbar">
            <strong>DOCUMENT PREVIEW</strong>
            <span>LIVE</span>

            <button
                type="button"
                data-close-document-preview
                aria-label="Close document preview"
            >
                ×
            </button>
        </div>

        <div class="add-preview-pages">
            @include('transmittals._document-form', [
                'transmittal' => $editTransmittal,
                'preview' => true,
            ])
        </div>
    </section>

    {{-- Add Transmittal Drawer --}}
    @include('transmittals._form-drawer', [
        'transmittal' => $editTransmittal,
    ])
</div>
@endsection

@push('scripts')
    @if (request()->boolean('add') || $editTransmittal)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelector('.add-button')?.click();
            });
        </script>
    @endif
@endpush

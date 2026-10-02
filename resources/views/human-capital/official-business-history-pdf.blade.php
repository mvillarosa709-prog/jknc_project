<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $trip->ob_reference_no }} History</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11px; }
        h1 { margin: 0 0 4px; font-size: 21px; }
        h2 { margin: 18px 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .muted { color: #64748b; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid th, .grid td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; vertical-align: top; }
        .grid th { background: #f1f5f9; }
        .summary { width: 100%; border-collapse: collapse; }
        .summary td { padding: 3px 8px 3px 0; vertical-align: top; }
        .code { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
    <h1>Official Business History</h1>
    <div class="muted">Master O.B. audit history</div>

    <h2>Record Summary</h2>
    <table class="summary">
        <tr>
            <td><strong>O.B. Reference</strong></td>
            <td class="code">{{ $trip->ob_reference_no }}</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>{{ $trip->status }}</td>
        </tr>
        <tr>
            <td><strong>Employee</strong></td>
            <td>{{ $trip->employee_name ?: $trip->employee?->name ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Prepared By</strong></td>
            <td>{{ $trip->preparedBy?->name ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Purpose</strong></td>
            <td>{{ $trip->purpose ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Destination</strong></td>
            <td>{{ $trip->destination ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Travel Type</strong></td>
            <td>{{ $trip->is_client_travel ? 'Client Travel' : 'Internal' }}</td>
        </tr>
        <tr>
            <td><strong>Generated</strong></td>
            <td>{{ $generatedAt?->format('M d, Y H:i:s') }}</td>
        </tr>
    </table>

    <h2>Client Trip Tickets</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Trip Ticket</th>
                <th>Client</th>
                <th>Status</th>
                <th>Personnel</th>
            </tr>
        </thead>
        <tbody>
        @forelse($trip->tripTickets as $ticket)
            <tr>
                <td class="code">{{ $ticket->reference_no }}</td>
                <td>{{ $ticket->client_name_snapshot }}</td>
                <td>{{ $ticket->status }}</td>
                <td>
                    @forelse($ticket->personnel as $member)
                        {{ $member->employee_name_snapshot }}
                        @if(! $loop->last), @endif
                    @empty
                        None recorded
                    @endforelse
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    {{ $trip->is_client_travel ? 'No active client Trip Tickets recorded.' : 'Internal O.B. - no client Trip Tickets.' }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <h2>Chronological History</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Date and time</th>
                <th>Event</th>
                <th>Actor</th>
                @if($includeFinance ?? false)<th>Details</th>@endif
            </tr>
        </thead>
        <tbody>
        @forelse($history as $event)
            <tr>
                <td>{{ $event->created_at?->format('M d, Y H:i:s') }}</td>
                <td>{{ str_replace('_', ' ', ucfirst($event->action)) }}</td>
                <td>{{ $event->user?->name ?: 'System' }}</td>
                @if($includeFinance ?? false)
                    <td>{{ json_encode($event->metadata['details'] ?? $event->changes ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ ($includeFinance ?? false) ? 4 : 3 }}">No history events recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>

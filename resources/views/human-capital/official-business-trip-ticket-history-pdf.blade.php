<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $ticket->reference_no }} History</title>
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
    <h1>Official Business Trip Ticket History</h1>
    <div class="muted">Client-specific audit history</div>

    <h2>Record Summary</h2>
    <table class="summary">
        <tr>
            <td><strong>Trip Ticket</strong></td>
            <td class="code">{{ $ticket->reference_no }}</td>
        </tr>
        <tr>
            <td><strong>Client</strong></td>
            <td>{{ $ticket->client_name_snapshot }}</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>{{ $ticket->status }}</td>
        </tr>
        <tr>
            <td><strong>Master O.B.</strong></td>
            <td class="code">{{ $ticket->officialBusinessTrip?->ob_reference_no ?: 'Not assigned' }}</td>
        </tr>
        <tr>
            <td><strong>Purpose</strong></td>
            <td>{{ $ticket->stops->pluck('activity')->filter()->unique()->implode(' / ') ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Destination</strong></td>
            <td>{{ $ticket->stops->pluck('destination')->filter()->unique()->implode(' / ') ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Generated</strong></td>
            <td>{{ $generatedAt?->format('M d, Y H:i:s') }}</td>
        </tr>
    </table>

    <h2>Personnel</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Position</th>
                <th>Department</th>
                <th>Participation</th>
            </tr>
        </thead>
        <tbody>
        @forelse($ticket->personnel as $member)
            <tr>
                <td>{{ $member->employee_name_snapshot }}</td>
                <td>{{ $member->role }}</td>
                <td>{{ $member->position_snapshot ?: 'Not recorded' }}</td>
                <td>{{ $member->department_snapshot ?: 'Not recorded' }}</td>
                <td>{{ $member->participation_status }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No personnel recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <h2>Evidence References</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Type</th>
                <th>File / Reference</th>
                <th>Uploaded By</th>
                <th>Recorded</th>
            </tr>
        </thead>
        <tbody>
        @forelse($ticket->evidence as $evidence)
            <tr>
                <td>{{ $evidence->category }}</td>
                <td>{{ $evidence->original_filename ?: $evidence->filename }}</td>
                <td>{{ $evidence->uploadedBy?->name ?: 'System' }}</td>
                <td>{{ $evidence->uploaded_at?->format('M d, Y H:i:s') ?: 'Not recorded' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No evidence recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($includeFinance)
    <h2>Billing and Travel Credit</h2>
    <table class="summary">
        <tr>
            <td><strong>Billing treatment</strong></td>
            <td>{{ $ticket->financialTreatment?->billing_treatment ?: 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Travel credit units</strong></td>
            <td>{{ $ticket->financialTreatment?->travel_credit_units ?? 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Travel credit value</strong></td>
            <td>{{ $ticket->financialTreatment?->travel_credit_value ?? 'Not recorded' }}</td>
        </tr>
        <tr>
            <td><strong>Additional costs status</strong></td>
            <td>{{ $ticket->financialTreatment?->additional_costs_status ?: 'Not recorded' }}</td>
        </tr>
    </table>

    <h2>Travel Credit Ledger Events</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Date and time</th>
                <th>Entry Type</th>
                <th>Units</th>
                <th>Value</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
        @forelse($ticket->travelCreditEntries as $entry)
            <tr>
                <td>{{ $entry->created_at?->format('M d, Y H:i:s') }}</td>
                <td>{{ $entry->entry_type }}</td>
                <td>{{ $entry->units_delta }}</td>
                <td>{{ $entry->credit_value }}</td>
                <td>{{ $entry->recordedBy?->name ?: 'System' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No Travel Credit ledger events recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <h2>Additional Costs</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Category</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Billing Treatment</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
        @forelse($ticket->costs as $cost)
            <tr>
                <td>{{ $cost->category }}</td>
                <td>{{ $cost->description }}</td>
                <td>{{ number_format((float) $cost->amount, 2) }}</td>
                <td>{{ $cost->billing_treatment }}</td>
                <td>{{ $cost->recordedBy?->name ?: 'System' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No additional costs recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @endif
    <h2>Chronological History</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Date and time</th>
                <th>Event</th>
                <th>Actor</th>
                @if($includeFinance)<th>Details</th>@endif
            </tr>
        </thead>
        <tbody>
        @forelse($history as $event)
            <tr>
                <td>{{ $event->created_at?->format('M d, Y H:i:s') }}</td>
                <td>{{ str_replace('_', ' ', ucfirst($event->action)) }}</td>
                <td>{{ $event->user?->name ?: 'System' }}</td>
                @if($includeFinance)
                    <td>{{ json_encode($event->metadata['details'] ?? $event->changes ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $includeFinance ? 4 : 3 }}">No history events recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <p class="muted">
        Verification: {{ $verificationUrl }}
    </p>
</body>
</html>

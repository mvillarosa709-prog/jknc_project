<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $ticket->reference_no }}</title>
    <style>
        @page {
            margin: 0.45in;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 10px;
            line-height: 1.35;
        }

        .document {
            border: 2px solid #1d4ed8;
            padding: 18px 20px 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        h1 {
            margin: 0 0 3px;
            font-size: 20px;
            color: #172033;
        }

        h2 {
            margin: 14px 0 6px;
            padding: 5px 7px;
            font-size: 11px;
            color: #172033;
            background: #eff6ff;
            border-left: 4px solid #1d4ed8;
            border-bottom: 1px solid #bfdbfe;
        }

        .muted {
            color: #64748b;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
        }

        .summary td {
            padding: 3px 7px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary td:first-child {
            width: 29%;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        .grid tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .grid th,
        .grid td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            vertical-align: top;
        }

        .grid th {
            background: #eaf2ff;
            text-align: left;
            font-weight: bold;
        }

        .code {
            font-family: DejaVu Sans Mono, monospace;
        }

        .verification {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 9px;
        }

        .verification-url {
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
<div class="document">
    <div class="header">
        <h1>Official Business Trip Ticket</h1>
        <div class="muted">Client-specific travel record</div>
    </div>

    <h2>Ticket</h2>
    <table class="summary">
        <tr><td><strong>Trip Ticket</strong></td><td class="code">{{ $ticket->reference_no }}</td></tr>
        <tr><td><strong>Client</strong></td><td>{{ $ticket->client_name_snapshot }}</td></tr>
        <tr><td><strong>Status</strong></td><td>{{ $ticket->status }}</td></tr>
        <tr><td><strong>Master O.B.</strong></td><td class="code">{{ $ticket->officialBusinessTrip?->ob_reference_no ?: 'Not assigned' }}</td></tr>
        <tr><td><strong>Purpose</strong></td><td>{{ $ticket->stops->pluck('activity')->filter()->unique()->implode(' / ') ?: 'Not recorded' }}</td></tr>
        <tr><td><strong>Destination</strong></td><td>{{ $ticket->stops->pluck('destination')->filter()->unique()->implode(' / ') ?: 'Not recorded' }}</td></tr>
        <tr><td><strong>Travel dates</strong></td><td>{{ $ticket->officialBusinessTrip?->date_from?->format('M d, Y') ?: 'Not recorded' }}@if($ticket->officialBusinessTrip?->date_to) – {{ $ticket->officialBusinessTrip->date_to->format('M d, Y') }}@endif</td></tr>
    </table>

    <h2>Client Stops</h2>
    <table class="grid">
        <thead><tr><th>#</th><th>Destination</th><th>Activity</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($ticket->stops as $stop)
            <tr><td>{{ $stop->sequence }}</td><td>{{ $stop->destination }}</td><td>{{ $stop->activity }}</td><td>{{ $stop->resolution_status }}</td></tr>
        @empty
            <tr><td colspan="4">No client stops recorded.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Personnel</h2>
    <table class="grid">
        <thead><tr><th>Name</th><th>Role</th><th>Position</th><th>Participation</th></tr></thead>
        <tbody>
        @forelse($ticket->personnel as $member)
            <tr><td>{{ $member->employee_name_snapshot }}</td><td>{{ $member->role }}</td><td>{{ $member->position_snapshot }}</td><td>{{ $member->participation_status }}</td></tr>
        @empty
            <tr><td colspan="4">No personnel recorded.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Evidence</h2>
    <table class="grid">
        <thead><tr><th>Type</th><th>File / Reference</th><th>Recorded</th></tr></thead>
        <tbody>
        @forelse($ticket->evidence as $evidence)
            <tr><td>{{ $evidence->category }}</td><td>{{ $evidence->original_filename ?: $evidence->filename }}</td><td>{{ $evidence->uploaded_at?->format('M d, Y H:i') }}</td></tr>
        @empty
            <tr><td colspan="3">No evidence recorded.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if($includeFinance)
        <h2>Finance</h2>
        <table class="summary">
            <tr><td><strong>Billing treatment</strong></td><td>{{ $ticket->financialTreatment?->billing_treatment ?: 'Not recorded' }}</td></tr>
            <tr><td><strong>Travel credits</strong></td><td>{{ $ticket->financialTreatment?->travel_credit_units ?? '—' }} / {{ $ticket->financialTreatment?->travel_credit_value ?? '—' }}</td></tr>
            <tr><td><strong>Additional costs status</strong></td><td>{{ $ticket->financialTreatment?->additional_costs_status ?: 'Not recorded' }}</td></tr>
        </table>
        <table class="grid">
            <thead><tr><th>Category</th><th>Description</th><th>Amount</th><th>Treatment</th></tr></thead>
            <tbody>
            @forelse($ticket->costs as $cost)
                <tr><td>{{ $cost->category }}</td><td>{{ $cost->description }}</td><td>{{ number_format((float) $cost->amount, 2) }}</td><td>{{ $cost->billing_treatment }}</td></tr>
            @empty
                <tr><td colspan="4">No additional costs recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif

    <div class="verification">
    <h2>Verification</h2>
    <div>Code: <span class="code">{{ $ticket->reference_no }}</span></div>
    <div class="muted verification-url">{{ $verificationUrl }}</div>
    </div>
</div>
</body>
</html>

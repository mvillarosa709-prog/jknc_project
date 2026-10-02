<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Trip Ticket Verification | JK&C</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
        }

        .page {
            max-width: 980px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .brand {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #1d4ed8;
        }

        .title {
            margin: 8px 0 0;
            font-size: 30px;
            line-height: 1.15;
            color: #0f172a;
        }

        .subtitle {
            margin-top: 8px;
            color: #64748b;
            font-size: 14px;
        }

        .verified {
            min-width: 180px;
            border: 1px solid #86efac;
            background: #f0fdf4;
            border-radius: 14px;
            padding: 15px 18px;
            text-align: center;
        }

        .verified-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #16a34a;
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .verified-text {
            font-weight: 800;
            color: #15803d;
            letter-spacing: .08em;
            font-size: 13px;
        }

        .verified-caption {
            margin-top: 5px;
            font-size: 11px;
            line-height: 1.5;
            color: #4d7c0f;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .field {
            padding: 16px 20px;
            border-bottom: 1px solid #edf2f7;
        }

        .field:nth-child(odd) {
            border-right: 1px solid #edf2f7;
        }

        .label {
            display: block;
            margin-bottom: 6px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
            color: #94a3b8;
        }

        .value {
            font-size: 14px;
            color: #1e293b;
            overflow-wrap: anywhere;
        }

        .reference {
            font-family: Consolas, "Courier New", monospace;
            color: #1d4ed8;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 20px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: 10px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .status {
            display: inline-block;
            border-radius: 999px;
            padding: 5px 10px;
            background: #dcfce7;
            color: #15803d;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .footer {
            margin-top: 26px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            text-align: center;
            color: #64748b;
            font-size: 11px;
            line-height: 1.7;
        }

        .security-note {
            margin-top: 12px;
            color: #94a3b8;
            font-size: 10px;
        }

        @media (max-width: 700px) {
            .header {
                display: block;
            }

            .verified {
                margin-top: 20px;
                width: 100%;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .field:nth-child(odd) {
                border-right: 0;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .title {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
<div class="page">

    <div class="header">
        <div>
            <div class="brand">John Kelly & Company</div>

            <h1 class="title">
                Official Business Trip Ticket Verification
            </h1>

            <p class="subtitle">
                Public verification of a completed client Trip Ticket recorded in ORDO.
            </p>
        </div>

        <div class="verified">
            <div class="verified-mark">✓</div>

            <div class="verified-text">
                Verified
            </div>

            <div class="verified-caption">
                This reference matches a valid completed Trip Ticket.
            </div>
        </div>
    </div>

    <section class="card">
        <div class="card-header">
            Trip Ticket Information
        </div>

        <div class="grid">

            <div class="field">
                <span class="label">Trip Ticket Reference</span>
                <div class="value reference">
                    {{ $ticket->reference_no }}
                </div>
            </div>

            <div class="field">
                <span class="label">Status</span>
                <div class="value">
                    <span class="status">{{ $ticket->status }}</span>
                </div>
            </div>

            @if($showDetails)
            <div class="field">
                <span class="label">Client</span>
                <div class="value">
                    {{ $ticket->client_name_snapshot }}
                </div>
            </div>

            <div class="field">
                <span class="label">Master O.B. Reference</span>
                <div class="value reference">
                    {{ $ticket->officialBusinessTrip?->ob_reference_no ?: '—' }}
                </div>
            </div>

            <div class="field">
                <span class="label">Purpose / Activity</span>
                <div class="value">
                    {{ $ticket->stops->pluck('activity')->filter()->unique()->implode(' / ') ?: '—' }}
                </div>
            </div>

            <div class="field">
                <span class="label">Destination</span>
                <div class="value">
                    {{ $ticket->stops->pluck('destination')->filter()->unique()->implode(' / ') ?: '—' }}
                </div>
            </div>

            <div class="field">
                <span class="label">Travel Date From</span>
                <div class="value">
                    {{ $ticket->officialBusinessTrip?->date_from?->format('F d, Y') ?: '—' }}
                </div>
            </div>

            <div class="field">
                <span class="label">Travel Date To</span>
                <div class="value">
                    {{ $ticket->officialBusinessTrip?->date_to?->format('F d, Y') ?: '—' }}
                </div>
            </div>
            @endif

        </div>
    </section>

    @if($showDetails)
    <section class="card">
        <div class="card-header">
            Client Stops
        </div>

        <table>
            <thead>
                <tr>
                    <th>Sequence</th>
                    <th>Destination</th>
                    <th>Activity</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            @forelse($ticket->stops as $stop)
                <tr>
                    <td>{{ $stop->sequence }}</td>
                    <td>{{ $stop->destination }}</td>
                    <td>{{ $stop->activity ?: '—' }}</td>
                    <td>{{ $stop->resolution_status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No client stops recorded.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card">
        <div class="card-header">
            Personnel
        </div>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Participation</th>
                </tr>
            </thead>

            <tbody>
            @forelse($ticket->personnel as $member)
                <tr>
                    <td>{{ $member->employee_name_snapshot }}</td>
                    <td>{{ $member->role ?: '—' }}</td>
                    <td>{{ $member->participation_status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No personnel recorded.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
    @endif

    <div class="footer">
        <strong>Verification Notice</strong><br>

        This page confirms that the referenced Official Business Trip Ticket
        exists in ORDO as a completed JK&C business record.

        <div class="security-note">
            For privacy and security, this public verification page does not
            display client details, financial treatment, travel credits, costs,
            supporting evidence files, internal audit logs, or confidential notes.
        </div>
    </div>

</div>
</body>
</html>

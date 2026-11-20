<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ORDO Audit Log</title>
    <style>
        @page { margin: 24px; }
        body { color: #1f2937; font: 9px DejaVu Sans, sans-serif; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        .meta { color: #667085; margin-bottom: 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #dfe5ed; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f6fa; }
    </style>
</head>
<body>
    <h1>ORDO Records Audit Log</h1>
    <div class="meta">
        Generated {{ $generatedAt->format('Y-m-d H:i') }}
        @if ($filters['from'] || $filters['to'])
            &middot; Date range {{ $filters['from'] ?: 'any' }} to {{ $filters['to'] ?: 'any' }}
        @endif
        @if ($filters['action'])
            &middot; Action {{ $filters['action'] }}
        @endif
        @if ($filters['q'])
            &middot; Search {{ $filters['q'] }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Action</th>
                <th>Description</th>
                <th>Subject</th>
                <th>Document Reference</th>
                <th>User</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $entry->action }}</td>
                    <td>{{ $entry->description }}</td>
                    <td>{{ $entry->subjectType() }}: {{ $entry->subjectLabel() }}</td>
                    <td>{{ $entry->record?->document_reference ?? '' }}</td>
                    <td>{{ $entry->user?->name ?? 'System' }}</td>
                    <td>{{ $entry->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No audit entries match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $type->label() }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #1f2937; font: 9px DejaVu Sans, sans-serif; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 18px 0 8px; }
        .meta { color: #667085; margin-bottom: 14px; }
        .kpis { border-collapse: collapse; margin-bottom: 14px; width: 100%; }
        .kpis td { background: #f3f6fa; border: 1px solid #dfe5ed; padding: 8px; width: 25%; }
        .kpi-label { color: #667085; font-size: 8px; }
        .kpi-value { font-size: 14px; font-weight: bold; margin-top: 3px; }
        table.records { border-collapse: collapse; width: 100%; }
        .records th, .records td { border: 1px solid #dfe5ed; padding: 5px; text-align: left; vertical-align: top; }
        .records th { background: #f3f6fa; }
        .comments { border: 1px solid #dfe5ed; min-height: 32px; padding: 8px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{{ $type->label() }}</h1>
    <div class="meta">
        ORDO Records Management &middot; Generated {{ $generatedAt->format('Y-m-d H:i') }}
        @if ($filters['classification'])
            &middot; Classification {{ $filters['classification'] }}
        @endif
        @if ($filters['index'])
            &middot; Index {{ $filters['index'] }}
        @endif
        @if ($filters['source'])
            &middot; Source {{ $filters['source'] }}
        @endif
        @if ($filters['year'])
            &middot; Document year {{ $filters['year'] }}
        @endif
    </div>

    <table class="kpis">
        <tr>
            <td><div class="kpi-label">Records Included</div><div class="kpi-value">{{ $metrics['records'] }}</div></td>
            <td><div class="kpi-label">Classifications</div><div class="kpi-value">{{ $metrics['classifications'] }}</div></td>
            <td><div class="kpi-label">Sources</div><div class="kpi-value">{{ $metrics['sources'] }}</div></td>
            <td><div class="kpi-label">Reporting Year</div><div class="kpi-value">{{ $metrics['year'] }}</div></td>
        </tr>
    </table>

    <h2>Management Summary</h2>
    <p>This report includes {{ $metrics['records'] }} record(s) matching the selected filters.</p>
    @if ($comments !== '')
        <div class="comments">{{ $comments }}</div>
    @endif

    <h2>Records</h2>
    <table class="records">
        <thead>
            <tr>
                <th>Document Reference</th>
                <th>Record ID</th>
                <th>Document Title</th>
                <th>Classification</th>
                <th>Index</th>
                <th>Source</th>
                <th>Document Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Filed On</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record->document_reference }}</td>
                    <td>{{ $record->record_number ?: $record->id }}</td>
                    <td>{{ $record->title }}</td>
                    <td>{{ $record->classification?->code }} — {{ $record->classification?->name }}</td>
                    <td>{{ $record->index?->code }} — {{ $record->index?->name }}</td>
                    <td>{{ $record->source }}</td>
                    <td>{{ $record->document_date?->format('Y-m-d') }}</td>
                    <td>{{ $record->amount }} {{ $record->currency }}</td>
                    <td>{{ $record->status->label() }}</td>
                    <td>{{ $record->filed_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No records match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

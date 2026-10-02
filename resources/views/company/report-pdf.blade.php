<!doctype html>
<html>
<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .company-brand {
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .report-title {
            margin-top: 8px;
            font-size: 16px;
            font-weight: bold;
        }

        .client-name {
            margin-top: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .period {
            margin-top: 4px;
            color: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 8px 5px;
            border-top: 1px solid #6b7280;
            border-bottom: 1px solid #6b7280;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        td {
            padding: 7px 5px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .hours {
            text-align: right;
            white-space: nowrap;
        }

        .summary {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #6b7280;
        }

        .summary-row {
            margin-bottom: 5px;
        }

        .label {
            font-weight: bold;
        }
    </style>
</head>

<body>
@php
    $coverage = $dataset['duration_coverage'] ?? [];
    $period = $dataset['report_period'] ?? [];

    $from = !empty($period['from'])
        ? \Carbon\Carbon::parse($period['from'])
        : null;

    $to = !empty($period['to'])
        ? \Carbon\Carbon::parse($period['to'])
        : null;

    if ($from && $to) {
        $sameMonth = $from->format('Y-m') === $to->format('Y-m');
        $fullMonth = $from->day === 1
            && $to->day === $to->copy()->endOfMonth()->day;

        $periodLabel = $sameMonth && $fullMonth
            ? $from->format('F Y')
            : $from->format('M d, Y').' - '.$to->format('M d, Y');
    } else {
        $periodLabel = '-';
    }

    $filters = $dataset['filters'] ?? [];

    $activityTypes = collect($filters['activity_types'] ?? [])
        ->map(fn ($type) => \Illuminate\Support\Str::headline(
            strtolower((string) $type)
        ))
        ->implode(', ');

    $statusFilter = $filters['status'] ?? null;

    $filterLabel = ($dataset['selection_mode'] ?? 'filtered') === 'selected'
        ? 'Selected Activities'
        : 'Activity Type: '.($activityTypes ?: 'All')
            .' | Status: '.($statusFilter ?: 'All');

    $formatMinutes = function ($minutes) {
        if ($minutes === null) {
            return '-';
        }

        $minutes = (int) $minutes;
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0 && $remaining > 0) {
            return $hours.'h '.$remaining.'m';
        }

        if ($hours > 0) {
            return $hours.'h';
        }

        return $remaining.'m';
    };
@endphp

<div class="header">
    <div class="company-brand">JOHN KELLY & COMPANY</div>

    <div class="report-title">
        CLIENT ACTIVITY REPORT
    </div>

    <div class="client-name">
        {{ $company->company_name }}
    </div>

    <div class="period">
        {{ $periodLabel }}
    </div>

    <div class="period">
        {{ $filterLabel }}
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 16%;">Date</th>
            <th style="width: 18%;">Type</th>
            <th>Activity</th>
            <th style="width: 15%; text-align:right;">Hours</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($dataset['rows'] as $row)
            <tr>
                <td>
                    {{ !empty($row['occurred_at'])
                        ? \Carbon\Carbon::parse($row['occurred_at'])->format('M d')
                        : '-'
                    }}
                </td>

                <td>
                    {{ \Illuminate\Support\Str::headline(
                        strtolower((string) $row['source_activity_type'])
                    ) }}
                </td>

                <td>
                    {{ $row['report_description'] ?: $row['title'] }}
                </td>

                <td class="hours">
                    {{ $formatMinutes($row['duration_minutes']) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align:center; padding:20px;">
                    No activities found for this report.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="summary">
    <div class="summary-row">
        <span class="label">Recorded Hours:</span>
        {{ $formatMinutes($coverage['total_duration_minutes'] ?? 0) }}
    </div>

    <div class="summary-row">
        <span class="label">Activities with Duration:</span>
        {{ $coverage['activities_with_duration'] ?? 0 }}
        of
        {{ $coverage['total_activities'] ?? 0 }}
    </div>
</div>
</body>
</html>
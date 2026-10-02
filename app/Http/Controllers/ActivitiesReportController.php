<?php

namespace App\Http\Controllers;

use App\Services\ActivitiesReportService;
use App\Services\ActivitiesXlsxExportService;
use App\Services\ActivityAuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivitiesReportController extends Controller
{
    public function __construct(
        private readonly ActivitiesReportService $reports,
        private readonly ActivityAuditService $audit,
        private readonly ActivitiesXlsxExportService $xlsx
    ) {}

    public function dataset(Request $request)
    {
        $parameters = $this->validatedParameters($request);
        $dataset = $this->reports->build($request->user(), $parameters);

        $this->recordGeneration('report_generated', $dataset, $parameters);

        return response()->json($dataset);
    }

    public function export(Request $request)
    {
        $parameters = $this->validatedParameters($request, true);
        $dataset = $this->reports->build($request->user(), $parameters);
        $format = $parameters['format'] ?? 'csv';

        $this->recordGeneration('export_generated', $dataset, $parameters);

        if ($format === 'json') {
            return response()->json($dataset);
        }

        if ($format === 'print') {
            return response($this->pdfHtml($dataset), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="activities-report-print.html"',
            ]);
        }

        if ($format === 'pdf' || $format === 'transmittal') {
            $filename = $format === 'transmittal' ? 'activities-transmittal-' : 'activities-report-';
            return Pdf::loadHTML($this->pdfHtml($dataset))
                ->setPaper('a4', 'landscape')
                ->download($filename.now()->format('Ymd-His').'.pdf');
        }

        if ($format === 'xlsx') {
            return response($this->xlsx->build($dataset), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="activities-report-'.now()->format('Ymd-His').'.xlsx"',
            ]);
        }

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Selection Mode', $dataset['selection_mode']]);
        fputcsv($handle, ['Total Activities', $dataset['duration_coverage']['total_activities']]);
        fputcsv($handle, ['Activities with Duration', $dataset['duration_coverage']['activities_with_duration']]);
        fputcsv($handle, ['Total Duration Minutes', $dataset['duration_coverage']['total_duration_minutes']]);
        fputcsv($handle, ['Total Duration Hours', $dataset['duration_coverage']['total_duration_hours']]);
        fputcsv($handle, []);
        $headers = [
            'Activity ID', 'Type', 'Title', 'Date/Time', 'Duration Minutes', 'Duration Hours', 'Status',
            'Prepared By', 'Assigned To', 'Company', 'Contact', 'Project', 'Deal',
            'Client Report Description', 'Has Documentation',
        ];
        if ($dataset['finance_authorized']) {
            array_push($headers, 'O.B. Number', 'Trip Ticket Number', 'Travel Credit Units', 'Travel Credit Value', 'Additional Cost Total', 'Billability');
        }
        fputcsv($handle, $headers);

        foreach ($dataset['rows'] as $row) {
            $cells = [
                $row['id'],
                $row['source_activity_type'],
                $row['title'],
                $row['occurred_at'],
                $row['duration_minutes'],
                $row['duration_hours'],
                $row['status'],
                $row['prepared_by'],
                $row['assigned_to'],
                $row['company'],
                $row['contact'],
                $row['project'],
                $row['deal'],
                $row['report_description'],
                $row['has_documentation'] ? 'Yes' : 'No',
            ];
            if ($dataset['finance_authorized']) {
                array_push($cells,
                    $row['ob_reference'] ?? null, $row['ob_ticket_reference'] ?? null,
                    $row['ob_travel_credit_units'] ?? null, $row['ob_travel_credit_value'] ?? null,
                    $row['ob_additional_cost_total'] ?? null, $row['ob_billability'] ?? null
                );
            }
            fputcsv($handle, array_map($this->csvCell(...), $cells));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="activities-report-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    private function validatedParameters(Request $request, bool $withFormat = false): array
    {
        $financeFilters = ['ob_credit_usage', 'ob_additional_cost', 'ob_billability'];
        if (collect($financeFilters)->contains(fn (string $key): bool => $request->filled($key))) {
            abort_unless(app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($request->user()), 403);
        }

        $rules = [
            'selected_ids' => ['nullable', 'array'],
            'selected_ids.*' => ['string', 'regex:/^(task|event|call|meeting|email|message|official-business-travel|official-business-trip-ticket)-\d+$/i'],
            'ob_master_ids' => ['sometimes', 'array'],
            'ob_master_ids.*' => ['integer', 'min:1'],
            'ob_client' => ['nullable', 'string', 'max:255'],
            'ob_reference' => ['nullable', 'string', 'max:255'],
            'ob_ticket_reference' => ['nullable', 'string', 'max:255'],
            'ob_destination' => ['nullable', 'string', 'max:255'],
            'ob_activity' => ['nullable', 'string', 'max:255'],
            'ob_transportation' => ['nullable', 'string', 'max:255'],
            'ob_employee' => ['nullable', 'string', 'max:255'],
            'ob_department' => ['nullable', 'string', 'max:255'],
            'ob_credit_usage' => ['nullable', Rule::in(['with', 'without'])],
            'ob_additional_cost' => ['nullable', Rule::in(['with', 'without'])],
            'ob_billability' => ['nullable', 'string', 'max:255'],
            'date_preset' => ['nullable', Rule::in([
                'today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month',
                'this_quarter', 'last_quarter', 'year_to_date', 'this_year', 'last_7_days', 'last_30_days',
            ])],
            'month' => ['nullable', 'date_format:Y-m'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'year' => ['nullable', 'integer', 'between:1900,2200'],
            'date_from' => ['nullable', 'date'],
            'date_to' => [
                'nullable',
                'date',
                Rule::when($request->filled('date_from'), 'after_or_equal:date_from'),
            ],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['string', Rule::in([
                'TASK', 'EVENT', 'CALL', 'MEETING', 'EMAIL', 'MESSAGE', 'OFFICIAL_BUSINESS_TRAVEL',
                'task', 'event', 'call', 'meeting', 'email', 'message', 'official_business_travel',
                'official-business-travel',
            ])],
            'company_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'deal_id' => ['nullable', 'integer'],
            'prepared_by_user_id' => ['nullable', 'integer'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:80'],
            'duration_availability' => ['nullable', Rule::in(['with', 'without'])],
            'documentation_availability' => ['nullable', Rule::in(['with', 'without'])],
            'include_in_client_report' => ['nullable', 'boolean'],
        ];

        if ($withFormat) {
            $rules['format'] = ['nullable', Rule::in(['csv', 'json', 'pdf', 'xlsx', 'transmittal', 'print'])];
        }

        return $request->validate($rules);
    }

    private function recordGeneration(string $action, array $dataset, array $parameters): void
    {
        $this->audit->record('REPORT', null, $action, [], [
            'selection_mode' => $dataset['selection_mode'],
            'row_count' => count($dataset['rows']),
            'parameters' => $parameters,
        ]);
    }

    private function csvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function pdfHtml(array $dataset): string
    {
        $escape = fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $coverage = $dataset['duration_coverage'];
        $period = $dataset['report_period'];
        $filters = collect($dataset['filters'])
            ->map(fn ($value, string $key): string => $escape($key).': '.$escape(is_array($value) ? implode(', ', $value) : $value))
            ->implode(' | ');
        $includeFinance = $dataset['finance_authorized'];
        $rows = collect($dataset['rows'])->map(function (array $row) use ($escape, $includeFinance): string {
            return '<tr>'
                .'<td>'.$escape($row['id']).'</td>'
                .'<td>'.$escape($row['source_activity_type']).'</td>'
                .'<td>'.$escape($row['title']).'</td>'
                .'<td>'.$escape($row['occurred_at']).'</td>'
                .'<td>'.$escape($row['duration_minutes']).'</td>'
                .'<td>'.$escape($row['status']).'</td>'
                .'<td>'.$escape($row['prepared_by']).'</td>'
                .'<td>'.$escape($row['assigned_to']).'</td>'
                .'<td>'.$escape($row['company']).'</td>'
                .'<td>'.$escape($row['report_description']).'</td>'
                .($includeFinance
                    ? '<td>'.$escape($row['ob_travel_credit_units'] ?? null).'</td>'
                        .'<td>'.$escape($row['ob_travel_credit_value'] ?? null).'</td>'
                        .'<td>'.$escape($row['ob_additional_cost_total'] ?? null).'</td>'
                        .'<td>'.$escape($row['ob_billability'] ?? null).'</td>'
                    : '')
                .'</tr>';
        })->implode('');

        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111827}'
            .'h1{font-size:18px;margin:0 0 8px}p{margin:0 0 10px}'
            .'table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:4px;vertical-align:top}'
            .'th{background:#f3f4f6;text-align:left}'
            .'</style></head><body><h1>Activities Report</h1><p>'
            .'Selection: '.$escape($dataset['selection_mode'])
            .' | Activities with Duration: '.$escape($coverage['activities_with_duration']).'/'.$escape($coverage['total_activities'])
            .' | Coverage: '.$escape($coverage['coverage_percentage']).'%'
            .' | Total minutes: '.$escape($coverage['total_duration_minutes'])
            .'</p><p>Period: '.$escape($period['from']).' to '.$escape($period['to'])
            .($filters !== '' ? ' | Filters: '.$filters : '')
            .'</p><table><thead><tr>'
            .'<th>ID</th><th>Type</th><th>Title</th><th>Date/Time</th><th>Minutes</th>'
            .'<th>Status</th><th>Prepared By</th><th>Assigned To</th><th>Company</th><th>Client Report Description</th>'
            .($includeFinance ? '<th>Credit Units</th><th>Credit Value</th><th>Cost Total</th><th>Billability</th>' : '')
            .'</tr></thead><tbody>'.$rows.'</tbody></table></body></html>';
    }
}

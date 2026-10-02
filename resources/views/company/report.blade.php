@extends('layouts.app')
@section('title', 'Client Activity Report')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 mt-4 pb-8">
    <div class="overflow-hidden rounded-md border border-gray-100 bg-white">
        @include('company.partials.company-header', ['company' => $company])

        <div class="border-t border-gray-100 px-5 py-6 sm:px-6 lg:px-8">

            {{-- Report Title --}}
            <div class="text-center">
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-gray-500">
                    John Kelly & Company
                </p>

                <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">
                    Client Activity Report
                </h1>

                <p class="mt-1 text-base font-semibold text-gray-800">
                    {{ $company->company_name }}
                </p>

                <p id="reportPeriodLabel" class="mt-1 text-sm text-gray-500">
                    -
                </p>

                <p id="reportFilterSummary" class="mt-1 text-xs text-gray-400">
                    Activity Type: All | Status: All
                </p>
            </div>

            {{-- Filters --}}
            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50/60 p-5 shadow-sm">
                <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <i class="fas fa-filter text-sm"></i>
                        </div>

                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">
                                Report Filters
                            </h2>

                            <p class="mt-0.5 text-xs text-gray-500">
                                Filter the activities to view the client's activity report.
                            </p>
                        </div>
                    </div>

                    <button
                        id="exportClientReportPdf"
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <i class="fas fa-file-pdf mr-2 text-xs"></i>
                        Export to PDF
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="reportDateFrom" class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Date From
                        </label>

                        <input
                            id="reportDateFrom"
                            type="date"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div>
                        <label for="reportDateTo" class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Date To
                        </label>

                        <input
                            id="reportDateTo"
                            type="date"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div>
                        <label for="reportActivityType" class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Activity Type
                        </label>

                        <select
                            id="reportActivityType"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">All</option>
                            <option value="TASK">Task</option>
                            <option value="EVENT">Event</option>
                            <option value="CALL">Call</option>
                            <option value="MEETING">Meeting</option>
                            <option value="EMAIL">Email</option>
                            <option value="MESSAGE">Message</option>
                            <option value="OFFICIAL_BUSINESS_TRAVEL">Official Business</option>
                        </select>
                    </div>

                    <div>
                        <label for="reportStatus" class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Status
                        </label>

                        <select
                            id="reportStatus"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">All</option>
                            <option value="Completed">Completed</option>
                            <option value="Open">Open</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Waiting/On Hold">Waiting/On Hold</option>
                            <option value="For Review">For Review</option>
                            <option value="Linked">Linked</option>
                            <option value="Unlinked">Unlinked</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        id="applyReportFilters"
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        <i class="fas fa-filter mr-2 text-xs"></i>
                        Apply Filters
                    </button>

                    <span
                        id="reportSelectionSummary"
                        class="text-xs font-medium text-gray-500"
                    >
                        No activities selected
                    </span>
                </div>
            </div>

            {{-- KPI Summary --}}
            <div class="mt-5 grid gap-4 md:grid-cols-2">

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                            <i class="far fa-clock text-xl"></i>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-500">
                                Recorded Hours
                            </p>

                            <p
                                id="reportRecordedHours"
                                class="mt-1 text-2xl font-bold text-gray-900"
                            >
                                0m
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <i class="fas fa-list-ul text-xl"></i>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-500">
                                Activities with Duration
                            </p>

                            <p
                                id="reportDurationCoverage"
                                class="mt-1 text-2xl font-bold text-gray-900"
                            >
                                0 of 0
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Activity Table --}}
            <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="w-12 px-5 py-4">
                                    <input
                                        id="selectAllReportRows"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                </th>

                                <th class="px-4 py-4">
                                    Date
                                </th>

                                <th class="px-4 py-4">
                                    Type
                                </th>

                                <th class="px-4 py-4">
                                    Activity
                                </th>

                                <th class="px-5 py-4 text-right">
                                    Hours
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            id="reportRows"
                            class="divide-y divide-gray-100 bg-white"
                        >
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">
                                    Loading report...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const companyId = @json($company->id);
    const datasetUrl = @json($reportDatasetUrl);
    const exportUrl = @json(route('company.report.pdf', ['company' => $company->id]));

    const dateFrom = document.getElementById('reportDateFrom');
    const dateTo = document.getElementById('reportDateTo');
    const activityType = document.getElementById('reportActivityType');
    const status = document.getElementById('reportStatus');
    const applyFilters = document.getElementById('applyReportFilters');
    const exportPdf = document.getElementById('exportClientReportPdf');

    const rowsContainer = document.getElementById('reportRows');
    const recordedHours = document.getElementById('reportRecordedHours');
    const durationCoverage = document.getElementById('reportDurationCoverage');
    const periodLabel = document.getElementById('reportPeriodLabel');
    const filterSummary = document.getElementById('reportFilterSummary');
    const selectionSummary = document.getElementById('reportSelectionSummary');
    const selectAll = document.getElementById('selectAllReportRows');

    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    let appliedFilters = null;
    let currentRows = [];
    let currentCoverage = null;
    let activeController = null;
    let requestSequence = 0;

    const toInputDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    dateFrom.value = toInputDate(firstDay);
    dateTo.value = toInputDate(lastDay);

    const controlFilters = () => ({
        company_id: companyId,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        activity_type: activityType.value,
        status: status.value
    });

    const selectedIds = () => Array.from(
        document.querySelectorAll('.report-row-checkbox:checked')
    ).map((checkbox) => checkbox.value);

    const formatDuration = (minutes) => {
        if (minutes === null || minutes === undefined || minutes === '') {
            return '-';
        }

        const value = Number(minutes);

        if (!Number.isFinite(value)) {
            return '-';
        }

        const hours = Math.floor(value / 60);
        const remainingMinutes = value % 60;

        if (hours > 0 && remainingMinutes > 0) {
            return `${hours}h ${remainingMinutes}m`;
        }

        if (hours > 0) {
            return `${hours}h`;
        }

        return `${remainingMinutes}m`;
    };

    const formatDate = (value) => {
        if (!value) {
            return '-';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '-';
        }

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit'
        });
    };

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    };

    const buildDatasetQuery = (filters) => {
        const params = new URLSearchParams();

        params.set('company_id', filters.company_id);

        if (filters.date_from) {
            params.set('date_from', filters.date_from);
        }

        if (filters.date_to) {
            params.set('date_to', filters.date_to);
        }

        if (filters.activity_type) {
            params.append('activity_types[]', filters.activity_type);
        }

        if (filters.status) {
            params.set('status', filters.status);
        }

        return params;
    };

    const formatPeriodLabel = (filters) => {
        if (!filters?.date_from || !filters?.date_to) {
            return '-';
        }

        const from = new Date(`${filters.date_from}T00:00:00`);
        const to = new Date(`${filters.date_to}T00:00:00`);

        const sameMonth =
            from.getFullYear() === to.getFullYear() &&
            from.getMonth() === to.getMonth();

        const firstOfMonth = from.getDate() === 1;
        const expectedLastDay = new Date(
            to.getFullYear(),
            to.getMonth() + 1,
            0
        ).getDate();
        const lastOfMonth = to.getDate() === expectedLastDay;

        if (sameMonth && firstOfMonth && lastOfMonth) {
            return from.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
        }

        return (
            from.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            }) +
            ' - ' +
            to.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            })
        );
    };

    const updateAppliedHeader = () => {
        periodLabel.textContent = formatPeriodLabel(appliedFilters);

        filterSummary.textContent =
            `Activity Type: ${appliedFilters?.activity_type || 'All'} | ` +
            `Status: ${appliedFilters?.status || 'All'}`;
    };

    const coverageForRows = (rows) => {
        const withDuration = rows.filter(
            (row) => row.duration_minutes !== null &&
                row.duration_minutes !== undefined
        );

        return {
            activities_with_duration: withDuration.length,
            total_activities: rows.length,
            total_duration_minutes: withDuration.reduce(
                (sum, row) => sum + Number(row.duration_minutes || 0),
                0
            )
        };
    };

    const renderCoverage = (coverage) => {
        recordedHours.textContent =
            formatDuration(coverage?.total_duration_minutes ?? 0);

        durationCoverage.textContent =
            `${coverage?.activities_with_duration ?? 0} of ` +
            `${coverage?.total_activities ?? 0}`;
    };

    const updateSelectionState = () => {
        const selected = selectedIds();

        selectionSummary.textContent = selected.length === 0
            ? 'No activities selected'
            : `${selected.length} activit${selected.length === 1 ? 'y' : 'ies'} selected`;

        if (selected.length === 0) {
            renderCoverage(currentCoverage || {
                activities_with_duration: 0,
                total_activities: 0,
                total_duration_minutes: 0
            });

            return;
        }

        const selectedSet = new Set(selected);
        const selectedRows = currentRows.filter(
            (row) => selectedSet.has(String(row.id))
        );

        renderCoverage(coverageForRows(selectedRows));
    };

    const bindRowCheckboxes = () => {
        document.querySelectorAll('.report-row-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', function () {
                const allRows = Array.from(
                    document.querySelectorAll('.report-row-checkbox')
                );

                selectAll.checked =
                    allRows.length > 0 &&
                    allRows.every((row) => row.checked);

                updateSelectionState();
            });
        });
    };

    const renderRows = (rows) => {
        if (rows.length === 0) {
            rowsContainer.innerHTML = `
                <tr>
                    <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                        No activities found for the selected filters.
                    </td>
                </tr>
            `;

            return;
        }

        rowsContainer.innerHTML = rows.map((row) => `
            <tr>
                <td class="px-2 py-3">
                    <input
                        type="checkbox"
                        class="report-row-checkbox"
                        value="${escapeHtml(row.id)}"
                    >
                </td>

                <td class="whitespace-nowrap px-2 py-3 text-gray-700">
                    ${escapeHtml(formatDate(row.occurred_at))}
                </td>

                <td class="whitespace-nowrap px-2 py-3 text-gray-700">
                    ${escapeHtml(
                        String(row.source_activity_type || '')
                            .replaceAll('_', ' ')
                            .toLowerCase()
                            .replace(/\b\w/g, (letter) => letter.toUpperCase())
                    )}
                </td>

                <td class="px-2 py-3 text-gray-900">
                    ${escapeHtml(row.report_description || row.title || '-')}
                </td>

                <td class="whitespace-nowrap px-2 py-3 text-right text-gray-700">
                    ${escapeHtml(formatDuration(row.duration_minutes))}
                </td>
            </tr>
        `).join('');

        bindRowCheckboxes();
    };

    async function loadReport() {
        const requestedFilters = controlFilters();
        const sequence = ++requestSequence;

        activeController?.abort();
        activeController = new AbortController();

        applyFilters.disabled = true;
        exportPdf.disabled = true;

        rowsContainer.innerHTML = `
            <tr>
                <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                    Loading report...
                </td>
            </tr>
        `;

        selectAll.checked = false;

        try {
            const response = await fetch(
                `${datasetUrl}?${buildDatasetQuery(requestedFilters).toString()}`,
                {
                    headers: {
                        'Accept': 'application/json'
                    },
                    signal: activeController.signal
                }
            );

            if (!response.ok) {
                throw new Error(`Report request failed (${response.status})`);
            }

            const dataset = await response.json();

            if (sequence !== requestSequence) {
                return;
            }

            appliedFilters = { ...requestedFilters };
            currentRows = Array.isArray(dataset.rows) ? dataset.rows : [];
            currentCoverage = dataset.duration_coverage || {
                activities_with_duration: 0,
                total_activities: 0,
                total_duration_minutes: 0
            };

            renderRows(currentRows);
            renderCoverage(currentCoverage);
            updateAppliedHeader();
            updateSelectionState();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            if (sequence !== requestSequence) {
                return;
            }

            currentRows = [];
            currentCoverage = {
                activities_with_duration: 0,
                total_activities: 0,
                total_duration_minutes: 0
            };

            rowsContainer.innerHTML = `
                <tr>
                    <td colspan="5" class="px-3 py-8 text-center text-red-600">
                        Unable to load the report.
                    </td>
                </tr>
            `;

            selectAll.checked = false;
            renderCoverage(currentCoverage);
            selectionSummary.textContent = 'No activities selected';

            console.error(error);
        } finally {
            if (sequence === requestSequence) {
                applyFilters.disabled = false;
                exportPdf.disabled = false;
            }
        }
    }

    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.report-row-checkbox').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });

        updateSelectionState();
    });

    applyFilters.addEventListener('click', loadReport);

    exportPdf.addEventListener('click', function () {
        const params = new URLSearchParams();
        const selected = selectedIds();

        if (selected.length > 0) {
            selected.forEach((id) => {
                params.append('selected_ids[]', id);
            });
        } else {
            const current = appliedFilters;

            if (!current) {
                return;
            }

            if (current.date_from) {
                params.set('date_from', current.date_from);
            }

            if (current.date_to) {
                params.set('date_to', current.date_to);
            }

            if (current.activity_type) {
                params.append('activity_types[]', current.activity_type);
            }

            if (current.status) {
                params.set('status', current.status);
            }
        }

        window.location.href = `${exportUrl}?${params.toString()}`;
    });

    loadReport();
});
</script>
@endsection
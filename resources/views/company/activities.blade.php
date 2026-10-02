@extends('layouts.app')
@section('title', 'Company Activities')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 mt-4 pb-8">
    <div class="bg-white border border-gray-100 rounded-md overflow-hidden">
        @include('company.partials.company-header', ['company' => $company])

        <div class="px-4 pt-4 sm:px-6">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    onclick="openCompanyActivityShortcut('task')"
                    class="inline-flex h-9 items-center justify-center rounded-md bg-[#1d54e2] px-4 text-sm font-semibold text-white transition hover:bg-[#1541b0]"
                >
                    <i class="fas fa-plus mr-2 text-xs"></i>
                    Add Task
                </button>

                <button
                    type="button"
                    onclick="openCompanyActivityShortcut('meetings')"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    <i class="fas fa-calendar-plus mr-2 text-xs"></i>
                    Add Meeting
                </button>

                <button
                    type="button"
                    onclick="openCompanyActivityShortcut('messages')"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    <i class="fas fa-comment-dots mr-2 text-xs"></i>
                    Add Message
                </button>

                <a
                    id="companyActivitiesReportButton"
                    href="{{ route('company.report', ['company' => $company->id]) }}"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    <i class="fas fa-file-lines mr-2 text-xs" aria-hidden="true"></i>
                    Report
                </a>
            </div>
        </div>
        @include('partials.activities-app', [
            'appId' => 'companyActivitiesApp',
            'listId' => 'companyActivitiesList',
            'modalId' => 'companyActivityModal',
            'formId' => 'companyActivityForm',
            'title' => 'Activities',
            'description' => 'View activities linked to this company from the centralized Activities workspace.',
            'openUrl' => $activitiesIndexUrl ?? route('activities'),
            'openLabel' => 'Open Main Activities',
            'editable' => false,
            'activities' => $activities,
        ])
    </div>
</div>
<script>
function openCompanyActivityShortcut(type) {
    localStorage.setItem('activity_company_prefill', JSON.stringify({
        type: type,
        company_id: @json($company->id),
        company_name: @json($company->company_name)
    }));

    window.location.href =
        @json($activitiesIndexUrl ?? route('activities')) +
        '?open=' +
        encodeURIComponent(type);
}
</script>
@endsection
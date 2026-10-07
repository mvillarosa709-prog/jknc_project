<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequestsHumanCapitalApproval;
use App\Http\Controllers\Concerns\ScopesHumanCapitalRecords;
use App\Http\Controllers\Concerns\UsesLatestGisCompanyHeader;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Project;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Services\OfficialBusinessActivityService;
use App\Services\OfficialBusinessCompletionService;
use App\Services\OfficialBusinessTripTicketService;
use App\Services\OfficialBusinessAuthorizationService;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OfficialBusinessTripController extends Controller
{
    use RequestsHumanCapitalApproval;
    use ScopesHumanCapitalRecords;
    use UsesLatestGisCompanyHeader;

    public function activitiesIndex(
        OfficialBusinessActivityService $officialBusinessActivities,
        OfficialBusinessCompletionService $completionService
    ): JsonResponse
    {
        abort_unless(
            app(OfficialBusinessAuthorizationService::class)->canView(Auth::user()),
            403
        );

        $context = $officialBusinessActivities->context(Auth::user());
        $authorization = app(OfficialBusinessAuthorizationService::class);
        $user = Auth::user();
        $canManageObf = $context['can_manage'];
        $currentEmployee = $context['current_employee'];

        $trips = $context['records']
            ->map(function (OfficialBusinessTrip $trip) use ($context, $officialBusinessActivities, $completionService, $authorization, $canManageObf, $user) {
                $completionChecklist = (int) $trip->workflow_version >= 3
                    ? $completionService->checklist($trip)
                    : [];
                $record = array_merge(
                    $this->formatTrip($trip),
                    $this->formatActivityTrip(
                        $trip,
                        $context['identity'],
                        $officialBusinessActivities
                    ),
                    [
                        'destination_primary' => $trip->destination,
                        'completion_checklist' => $completionChecklist,
                        'completion_ready' => $completionService->isReady($completionChecklist),
                    ]
                );

                if (! $canManageObf) {
                    foreach ([
                        'immediate_superior',
                        'superior_email',
                        'remarks',
                    ] as $privateField) {
                        unset($record[$privateField]);
                    }
                }

                if (! $authorization->canReadFinance($user)) {
                    foreach ([
                        'client_payment_status',
                        'client_payment_items',
                        'client_payment_other',
                        'amount_client_will_pay',
                        'travel_credit_details',
                        'estimated_expenses',
                    ] as $financeField) {
                        unset($record[$financeField]);
                    }
                }

                $record['trip_tickets'] = (int) $trip->workflow_version >= 3 && $trip->is_client_travel
                    ? $trip->tripTickets()
                        ->with(['stops', 'personnel', 'evidence'])
                        ->orderBy('id')
                        ->get()
                        ->filter(fn (OfficialBusinessTripTicket $ticket): bool => Gate::forUser($user)->allows('view', $ticket))
                        ->map(fn (OfficialBusinessTripTicket $ticket): array => [
                            'id' => $ticket->id,
                            'reference_no' => $ticket->reference_no,
                            'client_name_snapshot' => $ticket->client_name_snapshot,
                            'status' => $ticket->status,
                            'can_update' => Gate::forUser($user)->allows('update', $ticket),
                            'can_finance' => Gate::forUser($user)->allows('finance', $ticket),
                            'stops_count' => $ticket->stops->count(),
                            'personnel_count' => $ticket->personnel->count(),
                            'evidence_count' => $ticket->evidence->count(),
                            'pdf_url' => route('activities.official-business.trip-tickets.pdf', $ticket),
                            'history_pdf_url' => route('activities.official-business.trip-tickets.history-pdf', $ticket),
                            'verification_url' => $ticket->status === 'Completed'
                                ? route('official-business-trip-tickets.verify', $ticket->reference_no)
                                : null,
                        ])
                        ->values()
                    : [];

                return $record;
            })
            ->values();

        return response()->json([
            'records' => $trips,
            'current_employee_id' => $currentEmployee?->id,
            'can_manage' => $canManageObf,
            'can_view_all' => $authorization->canView($user),
            'supported_statuses' => [
                'Draft',
                'Submitted',
                'Pending',
                'Approved',
                'Ready for Travel',
                'In Progress',
                'Ongoing',
                'Returned',
                'For Completion',
                'Completed',
                'Reverted',
                'Cancelled',
                'Rejected',
            ],
            'capabilities' => [
                'create' => $authorization->canCreateOwn($user),
                'approve' => $authorization->canApprove($user),
                'travel_movement' => $authorization->canControlTravel($user),
                'manage' => $authorization->canManage($user),
                'finance' => $authorization->canReadFinance($user),
                'recovery' => $authorization->canAdminister($user),
                'soft_delete' => $authorization->canManage($user),
                'reports' => false,
                'multiple_clients' => true,
            ],
            'workspace_url' => route('human-capital.obf'),
        ]);
    }

    public function index()
    {
        $authorization = app(OfficialBusinessAuthorizationService::class);
        $user = Auth::user();
        abort_unless($authorization->canView($user), 403);
        $canManageObf = $this->canManageObf();
        $currentEmployee = $this->currentEmployee();

        $employeeQuery = $canManageObf
            ? Employee::with('department')->orderBy('last_name')
            : Employee::with('department')->where('id', $currentEmployee?->id ?: 0);

        $employees = $employeeQuery->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'full_name' => $employee->full_name,
                    'email' => $employee->work_email ?: $employee->email,
                    'personal_email' => $employee->personal_email ?? $employee->email,
                    'work_email' => $employee->work_email ?? null,
                    'phone_number' => $employee->phone_number,
                    'position' => $this->effectiveEmployeePosition($employee),
                    'department' => $employee->department?->department_name,
                    'immediate_superior' => $this->employeeSuperiorName($employee),
                    'superior_email' => $this->employeeSuperiorEmail($employee),
                ];
            })
            ->values();

        $personnelEmployees = Employee::query()->with('department')->orderBy('last_name')->get()
            ->filter(fn (Employee $employee) => ! filled($employee->employment_status) || ! in_array(strtolower((string) $employee->employment_status), ['inactive', 'terminated', 'resigned'], true))
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'position' => $this->effectiveEmployeePosition($employee),
                'department' => $employee->department?->department_name,
                'immediate_superior' => $this->employeeSuperiorName($employee),
            ])->values();

        $contacts = Contact::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function ($contact) {
                $fullName = trim(implode(' ', array_filter([
                    $contact->first_name,
                    $contact->middle_initial,
                    $contact->last_name,
                    $contact->name_extension,
                ])));

                $clientType = 'Local';
                $internationalHints = strtolower(implode(' ', array_filter([
                    $contact->ownership_flag,
                    $contact->foreign_business_nature,
                    $contact->organization_type,
                ])));

                if (str_contains($internationalHints, 'foreign') || str_contains($internationalHints, 'international')) {
                    $clientType = 'International';
                }

                return [
                    'id' => $contact->id,
                    'cif_no' => $contact->cif_no,
                    'full_name' => $fullName !== '' ? $fullName : 'Contact #'.$contact->id,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'company_name' => $contact->company_name,
                    'customer_type' => $contact->customer_type,
                    'client_status' => $contact->client_status,
                    'client_type' => $clientType,
                    'tin' => $contact->tin,
                    'contract_type' => $contact->client_status ? ucfirst((string) $contact->client_status) : null,
                    'label' => trim(($fullName !== '' ? $fullName : 'Contact #'.$contact->id).($contact->company_name ? ' - '.$contact->company_name : '').($contact->email ? ' ('.$contact->email.')' : '')),
                ];
            })
            ->values();

        $trips = OfficialBusinessTrip::latest()
            ->get()
            ->map(fn ($trip) => $this->formatTrip($trip))
            ->values();

        return view('human-capital.obf', [
            'employees' => $employees,
            'personnelEmployees' => $personnelEmployees,
            'contacts' => $contacts,
            'trips' => $trips,
            'canManageObf' => $canManageObf,
            'canViewAllObf' => $authorization->canView($user),
            'canApproveObf' => $authorization->canApprove($user),
            'canControlObfTravel' => $authorization->canControlTravel($user),
            'canAdministerObf' => $authorization->canAdminister($user),
            'currentEmployee' => $currentEmployee,
            'companyHeader' => $this->latestGisCompanyHeader(),
            'obClassifications' => config('official_business.classifications'),
            'internalActivityTypes' => config('official_business.internal_activity_types'),
            'socialActivityTypes' => config('official_business.social_activity_types'),
            'obCompanies' => Company::query()->orderBy('company_name')->get(['id', 'company_name']),
            'obDepartments' => Department::query()->orderBy('department_name')->get(['id', 'department_name']),
        ]);
    }

    public function suggestions(Request $request, \App\Services\OfficialBusinessSuggestionService $service): JsonResponse
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canView(Auth::user()), 403);
        $input = $request->validate([
            'field_type' => ['required', Rule::in(config('official_business.suggestion_field_types'))],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['values' => $service->suggest($input['field_type'], $input['q'] ?? null)
            ->pluck('display_value')->unique(fn ($value) => mb_strtolower($value))->values()]);
    }

    public function engagements(Request $request): JsonResponse
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canView(Auth::user()), 403);
        $input = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'type' => ['required', Rule::in(['Regular', 'Project'])],
        ]);
        $items = app(\App\Services\OfficialBusinessEngagementService::class)
            ->eligible((int) $input['company_id'], $input['type'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'deal_id' => $input['type'] === 'Regular' ? $project->deal_id : null,
            ]);
        $company = Company::findOrFail($input['company_id']);
        $credit = app(OfficialBusinessAuthorizationService::class)->canReadFinance(Auth::user())
            ? app(\App\Services\CompanyTravelCreditService::class)->summary($company)['available_units']
            : null;

        return response()->json(['items' => $items, 'travel_credit_balance' => $credit]);
    }

    public function store(
        Request $request,
        OfficialBusinessTripTicketService $tripTicketService
    )
    {
        abort_unless(
            app(OfficialBusinessAuthorizationService::class)->canCreateOwn(Auth::user()),
            403
        );

        $intent = $request->input('intent', 'submit');

        abort_unless(
            in_array($intent, ['submit', 'save_draft'], true),
            422,
            'Invalid Official Business form action.'
        );

        $isDraft = $intent === 'save_draft';

        $validated = $this->validateObf($request, false, $isDraft);
        $validated = $this->validateMultipleClientRoutes($validated, $isDraft);
        $detailRows = $this->validatedCreateDetails($validated, $isDraft);
        $attachmentService = app(\App\Services\OfficialBusinessCreateAttachmentService::class);
        $attachmentRows = filled($validated['classification'] ?? null)
            ? $attachmentService->validate($request, null, $detailRows, $isDraft)
            : [];
        $validated = $this->projectCreateDetailsToLegacyHeader($validated, $detailRows);

        // Employee users always submit the O.B. as their own Human Capital
        // employee identity. Managers may continue selecting another employee.
        $employee = $this->canManageObf()
            ? (
                $isDraft
                    ? (
                        filled($validated['employee_id'] ?? null)
                            ? Employee::with('department')->find($validated['employee_id'])
                            : null
                    )
                    : $this->resolveEmployee($validated['employee_id'] ?? null)
            )
            : $this->currentEmployee();

        if (! $isDraft) {
            abort_unless(
                $employee,
                422,
                'Your user account is not linked to an active employee record. Please contact Human Capital.'
            );
        }

        $isClientTravel = (bool) ($validated['is_client_travel'] ?? false);

        $clientContact = $isClientTravel && filled($validated['contact_id'] ?? null)
            ? Contact::find($validated['contact_id'])
            : null;

        $clientCompanyId = array_key_exists('company_id', $validated)
            ? $validated['company_id']
            : $this->resolveClientCompanyId($clientContact);

        $clientMode = $isClientTravel
            ? ($validated['client_mode'] ?? 'single')
            : 'single';

        $clientCompanyIds = collect($validated['company_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->when(
                $clientCompanyId,
                fn ($ids) => $ids->prepend((int) $clientCompanyId)
            )
            ->unique()
            ->values();

        if (
            ! $isDraft
            && $isClientTravel
            && $clientMode === 'multiple'
            && $clientCompanyIds->count() < 2
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_ids' => 'Select at least two different clients for a Multiple Client O.B.',
            ]);
        }

        $tripAttributes = [
            'ob_reference_no' => $this->resolveSubmittedReferenceNo($request),

            'employee_id' => $employee?->id,
            'employee_code' => $employee?->employee_code,
            'employee_name' => $employee?->full_name,
            'position' => $this->effectiveEmployeePosition($employee),
            'department' => $employee?->department?->department_name,
            'immediate_superior' => $this->superiorSnapshot($employee, $validated)['name'],
            'superior_email' => $this->superiorSnapshot($employee, $validated)['email'],

            'destination' => $validated['destination'] ?? null,
            'additional_stops' => $validated['additional_stops'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'purpose_options' => $validated['purpose_options'] ?? [],
            'purpose_other' => $validated['purpose_other'] ?? null,
            'trip_type' => $validated['trip_type'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'departure_time' => $validated['departure_time'] ?? null,
            'return_time' => $validated['return_time'] ?? null,
            'nature_of_travel' => $validated['nature_of_travel'] ?? null,
            'team_members' => array_key_exists('team_member_employee_ids', $validated)
                ? $this->masterTeamNames($validated['team_member_employee_ids'], $employee)
                : $this->filterArray($validated['team_members'] ?? []),

            'is_client_travel' => $isClientTravel,
            'company_id' => $clientCompanyId,
            'contact_id' => $clientContact?->id,
            'deal_id' => $validated['deal_id'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'duration_minutes' => null,
            'client_type' => $validated['client_type'] ?? null,
            'client_id_no' => $validated['client_id_no'] ?? null,
            'client_name' => $validated['client_name'] ?? null,
            'client_email' => $validated['client_email'] ?? null,
            'client_contract_number' => $validated['client_contract_number'] ?? null,
            'contract_type' => $validated['contract_type'] ?? null,
            'travel_billability' => $validated['travel_billability'] ?? null,

            'client_payment_status' => $validated['client_payment_status'] ?? null,
            'client_payment_items' => $validated['client_payment_items'] ?? [],
            'client_payment_other' => $validated['client_payment_other'] ?? null,
            'amount_client_will_pay' => $validated['amount_client_will_pay'] ?? 0,

            'travel_credit_details' => [
                'monthly_limit' => $validated['travel_credit_monthly_limit'] ?? null,
                'used_this_month' => $validated['travel_credit_used'] ?? null,
                'needed_for_trip' => $validated['travel_credit_needed'] ?? null,
                'remaining_after_trip' => $validated['travel_credit_remaining'] ?? null,
            ],

            'transportation_mode' => $validated['transportation_mode'] ?? null,
            'transportation_details' => $validated['transportation_details'] ?? $this->transportationDetails($validated),
            'estimated_expenses' => $validated['estimated_expenses'] ?? 0,

            'attachment_paths' => filled($validated['classification'] ?? null)
                ? []
                : $this->storeAttachments($request),
            'attachment_types' => $validated['attachment_types'] ?? [],
            'attachment_other' => $validated['attachment_other'] ?? null,

            'remarks' => $validated['remarks'] ?? null,
            'workflow_version' => 3,
            'classification' => $validated['classification'] ?? null,
            'status' => $isDraft ? 'Draft' : 'Submitted',
            'created_by' => Auth::id(),
            'client_report_description' => $validated['client_report_description'] ?? null,
            'include_in_client_report' => (bool) ($validated['include_in_client_report'] ?? false),
        ];
        if (! Schema::hasColumn('official_business_trips', 'classification')) {
            unset($tripAttributes['classification']);
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $trip = OfficialBusinessTrip::create($tripAttributes);
                break;
            } catch (\Illuminate\Database\QueryException $exception) {
                $referenceCollision = str_contains(strtolower($exception->getMessage()), 'ob_master_reference_unique')
                    || str_contains(strtolower($exception->getMessage()), 'official_business_trips.ob_reference_no');
                if (! $referenceCollision || $attempt === 4) {
                    throw $exception;
                }
                $tripAttributes['ob_reference_no'] = $this->resolveSubmittedReferenceNo($request);
            }
        }
        $trip->prepared_by_user_id = Auth::id();
        $trip->save();
        app(\App\Services\OfficialBusinessMasterPersonnelService::class)
            ->sync($trip, $validated['team_member_employee_ids'] ?? [], $this->teamNatures($validated));
        if (filled($validated['classification'] ?? null)) {
            app(\App\Services\OfficialBusinessCreateDetailService::class)
                ->sync($trip, $validated['classification'], $detailRows);
            app(\App\Services\OfficialBusinessCreateDetailService::class)->rememberSuggestions($detailRows);
            $attachmentService->persist($trip, $attachmentRows, $detailRows, $request);
        }

        app(ActivityAuditService::class)->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
            $trip->id,
            $isDraft ? 'draft_saved' : 'submitted',
            $trip->ob_reference_no,
            ['status' => $trip->status]
        );

        if ($isClientTravel && $clientMode === 'multiple') {
            if ($clientCompanyIds->isNotEmpty()) {
                $tickets = $tripTicketService->syncClients(
                    Auth::user(),
                    $trip,
                    $clientCompanyIds->all()
                );

                if (($validated['client_mode'] ?? 'single') === 'multiple') {
                    $this->syncMultipleClientPrimaryStops(
                        $trip,
                        $tickets,
                        $validated['client_routes'] ?? []
                    );
                }
            }
        } else {
            $tripTicketService->syncCurrentMasterClient(Auth::user(), $trip);
        }

        if (! $isDraft) {
            $this->notifyHumanCapitalAdmins(
                title: 'Official Business Trip Form submitted',
                message: ($trip->employee_name ?: 'An employee') . ' submitted an Official Business Trip Form for approval.',
                module: 'Official Business Trip Form',
                recordTitle: $trip->ob_reference_no ?: ($trip->destination ?: 'Official Business Trip'),
                actorName: Auth::user()?->name ?? Auth::user()?->email ?? 'System User'
            );
        }

        return redirect()
            ->route('human-capital.obf')
            ->with(
                'success',
                $isDraft
                    ? 'Official Business draft saved successfully.'
                    : 'OBF request submitted successfully.'
            );
    }

    public function update(
        Request $request,
        OfficialBusinessTrip $officialBusinessTrip,
        OfficialBusinessTripTicketService $tripTicketService
    )
    {
        $this->authorizeTripAccess($officialBusinessTrip, true);

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            if (! in_array($officialBusinessTrip->status, ['Draft', 'Reverted'], true)) {
                $message = 'This Official Business record can no longer be edited because its current status is '.$officialBusinessTrip->status.'.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'status' => $officialBusinessTrip->status,
                    ], 422);
                }

                return redirect()
                    ->route('human-capital.obf')
                    ->with('error', $message);
            }

            $intent = $request->input('intent', 'submit');

            abort_unless(
                in_array($intent, ['submit', 'save_draft'], true),
                422,
                'Invalid Official Business form action.'
            );

            $isDraft = $intent === 'save_draft';

            $validated = $this->validateObf($request, true, $isDraft);

            $validated = $this->validateMultipleClientRoutes($validated, $isDraft);
            $detailRows = $this->validatedCreateDetails($validated, $isDraft);
            $attachmentService = app(\App\Services\OfficialBusinessCreateAttachmentService::class);
            $attachmentRows = filled($validated['classification'] ?? null)
                ? $attachmentService->validate($request, $officialBusinessTrip, $detailRows, $isDraft)
                : [];
            $validated = $this->projectCreateDetailsToLegacyHeader($validated, $detailRows);

            if ($this->canManageObf()) {
                $employee = filled($validated['employee_id'] ?? null)
                    ? Employee::with('department')->find($validated['employee_id'])
                    : (
                        $officialBusinessTrip->employee_id
                            ? Employee::with('department')->find($officialBusinessTrip->employee_id)
                            : null
                    );
            } else {
                $employee = $this->currentEmployee()
                    ?: (
                        $officialBusinessTrip->employee_id
                            ? Employee::with('department')->find($officialBusinessTrip->employee_id)
                            : null
                    );
            }

            if (! $isDraft) {
                abort_unless(
                    $employee,
                    422,
                    'Employee is required before submitting an Official Business request.'
                );
            }

            $isClientTravel = (bool) ($validated['is_client_travel'] ?? false);

            $clientContact = $isClientTravel && filled($validated['contact_id'] ?? null)
                ? Contact::find($validated['contact_id'])
                : null;

            $clientCompanyId = $isClientTravel
                ? (
                    array_key_exists('company_id', $validated)
                        ? $validated['company_id']
                        : $this->resolveClientCompanyId($clientContact)
                )
                : null;

            $clientMode = $isClientTravel
                ? ($validated['client_mode'] ?? 'single')
                : 'single';

            $clientCompanyIds = collect($validated['company_ids'] ?? [])
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->when(
                    $clientCompanyId,
                    fn ($ids) => $ids->prepend((int) $clientCompanyId)
                )
                ->unique()
                ->values();

            if (
                ! $isDraft
                && $isClientTravel
                && $clientMode === 'multiple'
                && $clientCompanyIds->count() < 2
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'company_ids' => 'Select at least two different clients for a Multiple Client O.B.',
                ]);
            }

            $existingAttachments = $officialBusinessTrip->attachment_paths ?? [];
            $newAttachments = filled($validated['classification'] ?? null)
                ? []
                : $this->storeAttachments($request);

            DB::beginTransaction();

            try {
                if (filled($validated['classification'] ?? null)) {
                    $attachmentService->prepareExisting($officialBusinessTrip, $attachmentRows, $detailRows);
                }

                if (! $isClientTravel && $officialBusinessTrip->tripTickets()->exists()) {
                    $tripTicketService->syncClients(
                        Auth::user(),
                        $officialBusinessTrip,
                        []
                    );
                }

                $officialBusinessTrip->update([
                ... (Schema::hasColumn('official_business_trips', 'classification')
                    ? ['classification' => $validated['classification'] ?? $officialBusinessTrip->classification]
                    : []),
                'employee_id' => $employee?->id,
                'employee_code' => $employee?->employee_code,
                'employee_name' => $employee?->full_name,
                'position' => $this->effectiveEmployeePosition($employee),
                'department' => $employee?->department?->department_name,

                'immediate_superior' => $this->superiorSnapshot($employee, $validated, $officialBusinessTrip)['name'],
                'superior_email' => $this->superiorSnapshot($employee, $validated, $officialBusinessTrip)['email'],

                'destination' => $validated['destination'] ?? null,
                'additional_stops' => $validated['additional_stops'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'purpose_options' => $validated['purpose_options'] ?? [],
                'purpose_other' => $validated['purpose_other'] ?? null,
                'trip_type' => $validated['trip_type'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'departure_time' => $validated['departure_time'] ?? null,
                'return_time' => $validated['return_time'] ?? null,
                'nature_of_travel' => $validated['nature_of_travel'] ?? null,
                'team_members' => array_key_exists('team_member_employee_ids', $validated)
                    ? $this->masterTeamNames($validated['team_member_employee_ids'], $employee)
                    : $this->filterArray($validated['team_members'] ?? []),

                'is_client_travel' => $isClientTravel,
                'company_id' => $clientCompanyId,
                'contact_id' => $clientContact?->id,
                'deal_id' => $isClientTravel
                    ? (array_key_exists('deal_id', $validated) ? $validated['deal_id'] : $officialBusinessTrip->deal_id)
                    : null,
                'project_id' => $isClientTravel
                    ? (array_key_exists('project_id', $validated) ? $validated['project_id'] : $officialBusinessTrip->project_id)
                    : null,

                'client_type' => $validated['client_type'] ?? null,
                'client_id_no' => $validated['client_id_no'] ?? null,
                'client_name' => $validated['client_name'] ?? null,
                'client_email' => $validated['client_email'] ?? null,
                'client_contract_number' => $validated['client_contract_number'] ?? null,
                'contract_type' => $validated['contract_type'] ?? null,
                'travel_billability' => $validated['travel_billability'] ?? null,

                'client_payment_status' => $validated['client_payment_status'] ?? null,
                'client_payment_items' => $validated['client_payment_items'] ?? [],
                'client_payment_other' => $validated['client_payment_other'] ?? null,
                'amount_client_will_pay' => $validated['amount_client_will_pay'] ?? 0,

                'travel_credit_details' => [
                    'monthly_limit' => $validated['travel_credit_monthly_limit'] ?? null,
                    'used_this_month' => $validated['travel_credit_used'] ?? null,
                    'needed_for_trip' => $validated['travel_credit_needed'] ?? null,
                    'remaining_after_trip' => $validated['travel_credit_remaining'] ?? null,
                ],

                'transportation_mode' => $validated['transportation_mode'] ?? null,
                'transportation_details' => $validated['transportation_details'] ?? $this->transportationDetails($validated),

                'estimated_expenses' => $validated['estimated_expenses'] ?? 0,

                'attachment_paths' => array_values(
                    array_merge($existingAttachments, $newAttachments)
                ),
                'attachment_types' => filled($validated['classification'] ?? null)
                    ? ($officialBusinessTrip->attachment_types ?? [])
                    : ($validated['attachment_types'] ?? []),
                'attachment_other' => filled($validated['classification'] ?? null)
                    ? $officialBusinessTrip->attachment_other
                    : ($validated['attachment_other'] ?? null),

                'remarks' => $validated['remarks'] ?? null,
                'client_report_description' => $validated['client_report_description']
                    ?? $officialBusinessTrip->client_report_description,
                'include_in_client_report' => array_key_exists(
                    'include_in_client_report',
                    $validated
                )
                    ? (bool) $validated['include_in_client_report']
                    : (bool) $officialBusinessTrip->include_in_client_report,

                'status' => $isDraft ? 'Draft' : 'Submitted',
            ]);

            $statusChanged = $officialBusinessTrip->wasChanged('status');

            if (array_key_exists('team_member_employee_ids', $validated)) {
                app(\App\Services\OfficialBusinessMasterPersonnelService::class)
                    ->sync($officialBusinessTrip, $validated['team_member_employee_ids'], $this->teamNatures($validated));
            }
            if (filled($validated['classification'] ?? null)) {
                app(\App\Services\OfficialBusinessCreateDetailService::class)
                    ->sync($officialBusinessTrip, $validated['classification'], $detailRows, (bool) ($validated['classification_change_confirmed'] ?? false));
                app(\App\Services\OfficialBusinessCreateDetailService::class)->rememberSuggestions($detailRows);
                $attachmentService->persist($officialBusinessTrip, $attachmentRows, $detailRows, $request);
            }

            $freshTrip = $officialBusinessTrip->fresh();

            if ($isClientTravel && $clientCompanyIds->isNotEmpty()) {
                $tickets = $tripTicketService->syncClients(
                    Auth::user(),
                    $freshTrip,
                    $clientCompanyIds->all()
                );

                if (($validated['client_mode'] ?? 'single') === 'multiple') {
                    $this->syncMultipleClientPrimaryStops(
                        $freshTrip,
                        $tickets,
                        $validated['client_routes'] ?? []
                    );
                }
            }

            if ($isDraft && ! $statusChanged) {
                app(ActivityAuditService::class)->recordSemantic(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $officialBusinessTrip->id,
                    'draft_saved',
                    $officialBusinessTrip->ob_reference_no,
                    ['status' => $officialBusinessTrip->status]
                );
            }

                DB::commit();
            } catch (\Throwable $exception) {
                DB::rollBack();
                $attachmentService->cleanupUncommittedUploads();

                throw $exception;
            }

            if (! $isDraft) {
                $this->notifyHumanCapitalAdmins(
                    title: 'Official Business Trip Form submitted',
                    message: ($officialBusinessTrip->employee_name ?: 'An employee')
                        . ' submitted an Official Business Trip Form for approval.',
                    module: 'Official Business Trip Form',
                    recordTitle: $officialBusinessTrip->ob_reference_no
                        ?: ($officialBusinessTrip->destination ?: 'Official Business Trip'),
                    actorName: Auth::user()?->name
                        ?? Auth::user()?->email
                        ?? 'System User'
                );
            }

            return redirect()
                ->route('human-capital.obf')
                ->with(
                    'success',
                    $isDraft
                        ? 'Official Business draft saved successfully.'
                        : 'Official Business request submitted successfully.'
                );
        }

        $validated = $this->validateObf($request, true);

        \Log::info('OBF UPDATE DEBUG', [
            'trip_id' => $officialBusinessTrip->id,
            'request_is_client_travel' => $request->input('is_client_travel'),
            'validated_is_client_travel' => $validated['is_client_travel'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
        ]);

        $isClientTravel = (bool) ($validated['is_client_travel'] ?? false);

        $clientContact = $isClientTravel && filled($validated['contact_id'] ?? null)
            ? Contact::find($validated['contact_id'])
            : null;

        $clientCompanyId = array_key_exists('company_id', $validated)
            ? $validated['company_id']
            : $this->resolveClientCompanyId($clientContact);

        $existingAttachments = $officialBusinessTrip->attachment_paths ?? [];
        $newAttachments = $this->storeAttachments($request);

        $payload = [
            'immediate_superior' => $validated['immediate_superior'] ?? null,
            'superior_email' => $validated['superior_email'] ?? null,

            'destination' => $validated['destination'],
            'additional_stops' => $validated['additional_stops'] ?? null,
            'purpose' => $validated['purpose'],
            'purpose_options' => $validated['purpose_options'] ?? [],
            'purpose_other' => $validated['purpose_other'] ?? null,
            'trip_type' => $validated['trip_type'] ?? null,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'] ?? null,
            'departure_time' => $validated['departure_time'] ?? null,
            'return_time' => $validated['return_time'] ?? null,
            'nature_of_travel' => $validated['nature_of_travel'] ?? null,
            'team_members' => $this->filterArray($validated['team_members'] ?? []),

            'is_client_travel' => $isClientTravel,
            'company_id' => $clientCompanyId,
            'contact_id' => $clientContact?->id,
            'deal_id' => $isClientTravel
                ? (array_key_exists('deal_id', $validated) ? $validated['deal_id'] : $officialBusinessTrip->deal_id)
                : null,
            'project_id' => $isClientTravel
                ? (array_key_exists('project_id', $validated) ? $validated['project_id'] : $officialBusinessTrip->project_id)
                : null,
            'duration_minutes' => $officialBusinessTrip->duration_minutes,
            'client_type' => $validated['client_type'] ?? null,
            'client_id_no' => $validated['client_id_no'] ?? null,
            'client_name' => $validated['client_name'] ?? null,
            'client_email' => $validated['client_email'] ?? null,
            'client_contract_number' => $validated['client_contract_number'] ?? null,
            'contract_type' => $validated['contract_type'] ?? null,
            'travel_billability' => $validated['travel_billability'] ?? null,

            'client_payment_status' => $validated['client_payment_status'] ?? null,
            'client_payment_items' => $validated['client_payment_items'] ?? [],
            'client_payment_other' => $validated['client_payment_other'] ?? null,
            'amount_client_will_pay' => $validated['amount_client_will_pay'] ?? 0,

            'travel_credit_details' => [
                'monthly_limit' => $validated['travel_credit_monthly_limit'] ?? null,
                'used_this_month' => $validated['travel_credit_used'] ?? null,
                'needed_for_trip' => $validated['travel_credit_needed'] ?? null,
                'remaining_after_trip' => $validated['travel_credit_remaining'] ?? null,
            ],

            'transportation_mode' => $validated['transportation_mode'] ?? null,
            'transportation_details' => $this->transportationDetails($validated),
            'estimated_expenses' => $validated['estimated_expenses'] ?? 0,

            'attachment_paths' => array_values(array_merge($existingAttachments, $newAttachments)),
            'attachment_types' => $validated['attachment_types'] ?? [],
            'attachment_other' => $validated['attachment_other'] ?? null,

            'remarks' => $validated['remarks'] ?? null,
            'client_report_description' => $validated['client_report_description']
                ?? $officialBusinessTrip->client_report_description,
            'include_in_client_report' => array_key_exists('include_in_client_report', $validated)
                ? (bool) $validated['include_in_client_report']
                : (bool) $officialBusinessTrip->include_in_client_report,
        ];

        $this->requestHumanCapitalChange($request, 'Official Business Trip Form', 'update', $officialBusinessTrip, $payload, $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->employee_name);

        return redirect()->route('human-capital.obf')->with('success', 'OBF update submitted for admin approval.');
    }

    public function approve(Request $request, OfficialBusinessTrip $officialBusinessTrip)
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canApprove(Auth::user()), 403);

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            abort_unless(
                $officialBusinessTrip->status === 'Submitted',
                422,
                'Only submitted V3 Official Business requests can be approved.'
            );
        }

        $officialBusinessTrip->update(['status' => 'Approved']);

        if ($officialBusinessTrip->created_by) {
            \App\Models\User::find($officialBusinessTrip->created_by)?->notify(new \App\Notifications\HumanCapitalWorkflowNotification(
                'Official Business Trip Form approved',
                'Your Official Business Trip Form has been approved.',
                route('human-capital.obf'),
                'Official Business Trip Form',
                $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->destination,
                Auth::user()?->name ?? Auth::user()?->email ?? ''
            ));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'OBF request approved.',
                'record' => $this->formatTrip($officialBusinessTrip->fresh()),
            ]);
        }

        return redirect()->route('human-capital.obf')->with('success', 'OBF request approved.');
    }

    public function reject(Request $request, OfficialBusinessTrip $officialBusinessTrip)
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canApprove(Auth::user()), 403);

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            abort_unless(
                $officialBusinessTrip->status === 'Submitted',
                422,
                'Only submitted V3 Official Business requests can be rejected.'
            );
        }

        $request->validate(['remarks' => ['nullable', 'string']]);

        $officialBusinessTrip->update([
            'status' => 'Rejected',
            'remarks' => $request->remarks ?: $officialBusinessTrip->remarks,
        ]);

        if ($officialBusinessTrip->created_by) {
            $message = 'Your Official Business Trip Form has been rejected.';
            if ($request->filled('remarks')) {
                $message .= ' Note: ' . $request->remarks;
            }

            \App\Models\User::find($officialBusinessTrip->created_by)?->notify(new \App\Notifications\HumanCapitalWorkflowNotification(
                'Official Business Trip Form rejected',
                $message,
                route('human-capital.obf'),
                'Official Business Trip Form',
                $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->destination,
                Auth::user()?->name ?? Auth::user()?->email ?? ''
            ));
        }

        return redirect()->route('human-capital.obf')->with('success', 'OBF request rejected.');
    }

    public function revert(Request $request, OfficialBusinessTrip $officialBusinessTrip)
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canApprove(Auth::user()), 403);
        abort_unless((int) $officialBusinessTrip->workflow_version >= 3, 422,
            'Only V3 Official Business requests use the revert workflow.');
        abort_unless($officialBusinessTrip->status === 'Submitted', 422,
            'Only submitted Official Business requests can be reverted.');

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $officialBusinessTrip->update([
            'status' => 'Reverted',
            'remarks' => $validated['remarks'],
        ]);

        if ($officialBusinessTrip->created_by) {
            \App\Models\User::find($officialBusinessTrip->created_by)?->notify(new \App\Notifications\HumanCapitalWorkflowNotification(
                'Official Business Trip Form reverted',
                'Your Official Business Trip Form was returned for correction. Note: '.$validated['remarks'],
                route('human-capital.obf'),
                'Official Business Trip Form',
                $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->destination,
                Auth::user()?->name ?? Auth::user()?->email ?? ''
            ));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'OBF request reverted for correction.',
                'record' => $this->formatTrip($officialBusinessTrip->fresh()),
            ]);
        }

        return redirect()->route('human-capital.obf')->with('success', 'OBF request reverted for correction.');
    }

    public function readyForTravel(OfficialBusinessTrip $officialBusinessTrip): JsonResponse
    {
        $this->normalizeLegacyMovementState($officialBusinessTrip);

        $this->authorizeTripMovement($officialBusinessTrip);

        abort_unless(
            (int) $officialBusinessTrip->workflow_version >= 3,
            422,
            'Only V3 Official Business records use Ready for Travel.'
        );

        abort_unless(
            in_array($officialBusinessTrip->status, [
                'Draft',
                'Reverted',
                'Submitted',
                'Approved',
            ], true),
            422,
            'This Official Business record cannot be marked Ready for Travel.'
        );

        abort_unless(
            ! $officialBusinessTrip->actual_departure,
            422,
            'Departure has already been recorded.'
        );

        $officialBusinessTrip->update([
            'movement_status' => 'Ready',
        ]);

        return response()->json([
            'message' => 'Official Business trip is ready for travel.',
            'record' => $this->formatTrip($officialBusinessTrip->fresh()),
        ]);
    }

    public function depart(OfficialBusinessTrip $officialBusinessTrip): JsonResponse
    {
        $this->normalizeLegacyMovementState($officialBusinessTrip);

        $this->authorizeTripMovement($officialBusinessTrip);

        if ($officialBusinessTrip->actual_departure) {
            return response()->json([
                'message' => 'Departure has already been recorded.',
                'record' => $this->formatTrip($officialBusinessTrip->fresh()),
            ]);
        }

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            abort_unless(
                in_array($officialBusinessTrip->status, [
                    'Draft',
                    'Reverted',
                    'Submitted',
                    'Approved',
                ], true),
                422,
                'This Official Business record cannot depart in its current approval state.'
            );

            abort_unless(
                in_array($officialBusinessTrip->movement_status, [
                    null,
                    'Not Started',
                    'Ready',
                ], true),
                422,
                'This Official Business trip cannot depart from its current movement state.'
            );
        } else {
            abort_unless(
                $officialBusinessTrip->status === 'Approved',
                422,
                'Only Approved Official Business trips can depart.'
            );
        }

        $updates = [
            'actual_departure' => now(),
            'actual_return' => null,
            'duration_minutes' => null,
        ];

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            $updates['movement_status'] = 'Departed';
        } else {
            $updates['status'] = 'In Progress';
        }

        $officialBusinessTrip->update($updates);

        return response()->json([
            'message' => 'Departure recorded successfully.',
            'record' => $this->formatTrip($officialBusinessTrip->fresh()),
        ]);
    }

    public function returnTrip(OfficialBusinessTrip $officialBusinessTrip): JsonResponse
    {
        $this->normalizeLegacyMovementState($officialBusinessTrip);

        $this->authorizeTripMovement($officialBusinessTrip);

        if ($officialBusinessTrip->actual_return) {
            return response()->json([
                'message' => 'Return has already been recorded.',
                'record' => $this->formatTrip($officialBusinessTrip->fresh()),
            ]);
        }

        abort_unless(
            $officialBusinessTrip->actual_departure,
            422,
            'Departure must be recorded before return.'
        );

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            abort_unless(
                $officialBusinessTrip->movement_status === 'Departed',
                422,
                'Only departed Official Business trips can be returned.'
            );
        } else {
            abort_unless(
                $officialBusinessTrip->status === 'In Progress',
                422,
                'Only trips currently in progress can be returned.'
            );
        }

        $actualReturn = now();

        $durationMinutes = (int) $officialBusinessTrip->actual_departure
            ->diffInMinutes($actualReturn);

        $updates = [
            'actual_return' => $actualReturn,
            'duration_minutes' => $durationMinutes,
        ];

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            $updates['movement_status'] = 'Returned';
        } else {
            $updates['status'] = 'Completed';
        }

        $officialBusinessTrip->update($updates);

        return response()->json([
            'message' => 'Return recorded successfully.',
            'record' => $this->formatTrip($officialBusinessTrip->fresh()),
        ]);
    }

    public function startCompletion(OfficialBusinessTrip $officialBusinessTrip): JsonResponse
    {
        $this->normalizeLegacyMovementState($officialBusinessTrip);

        abort_unless(
            app(OfficialBusinessAuthorizationService::class)->canManage(Auth::user()),
            403
        );

        abort_unless(
            (int) $officialBusinessTrip->workflow_version >= 3,
            422,
            'Only V3 Official Business records use the completion workflow.'
        );

        abort_unless(
            $officialBusinessTrip->movement_status === 'Returned',
            422,
            'Only returned Official Business records can be moved For Completion.'
        );

        abort_unless(
            $officialBusinessTrip->actual_return !== null,
            422,
            'Actual return must be recorded before completion processing.'
        );

        abort_unless(
            ! in_array($officialBusinessTrip->status, ['For Completion', 'Completed', 'Cancelled'], true),
            422,
            'This Official Business record is not eligible to start completion.'
        );

        $officialBusinessTrip->update([
            'status' => 'For Completion',
        ]);

        return response()->json([
            'message' => 'Official Business record is now For Completion.',
            'record' => $this->formatTrip($officialBusinessTrip->fresh()),
        ]);
    }

    public function cancel(OfficialBusinessTrip $officialBusinessTrip): JsonResponse
    {
        $this->authorizeTripCancellation($officialBusinessTrip);
        abort_unless((int) $officialBusinessTrip->workflow_version >= 3, 422,
            'Only V3 Official Business records use this cancellation workflow.');
        abort_unless(in_array($officialBusinessTrip->status, [
            'Draft', 'Reverted', 'Submitted', 'Approved', 'Ready for Travel',
        ], true), 422, 'This Official Business record can no longer be cancelled.');

        $officialBusinessTrip->update(['status' => 'Cancelled']);

        return response()->json([
            'message' => 'Official Business record cancelled.',
            'record' => $this->formatTrip($officialBusinessTrip->fresh()),
        ]);
    }

    public function complete(
        OfficialBusinessTrip $officialBusinessTrip,
        OfficialBusinessCompletionService $completionService
    ): JsonResponse
    {
        abort_unless($this->canManageObf(), 403);

        abort_unless(
            (int) $officialBusinessTrip->workflow_version >= 3,
            422,
            'Only V3 Official Business records use the completion workflow.'
        );

        abort_unless(
            $officialBusinessTrip->status === 'For Completion',
            422,
            'Only Official Business records that are For Completion can be completed.'
        );

        $checklist = $completionService->checklist($officialBusinessTrip);
        $missingRequirements = $completionService->missingRequirements($checklist);

        if ($missingRequirements !== []) {
            return response()->json([
                'message' => 'Official Business cannot be completed until the missing requirements are resolved.',
                'completion_ready' => false,
                'missing_requirements' => $missingRequirements,
                'completion_checklist' => $checklist,
            ], 422);
        }

        app(ActivityAuditService::class)->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
            $officialBusinessTrip->id,
            'completion_ready',
            $officialBusinessTrip->ob_reference_no,
            ['checklist_keys' => collect($checklist)->pluck('key')->all()]
        );

        $officialBusinessTrip->update([
            'status' => 'Completed',
        ]);

        return response()->json([
            'message' => 'Official Business trip completed successfully.',
            'record' => $this->formatTrip($officialBusinessTrip->fresh()),
            'completion_ready' => true,
            'completion_checklist' => $checklist,
        ]);
    }

    public function deletedActivitiesIndex(): JsonResponse
    {
        $authorization = app(OfficialBusinessAuthorizationService::class);
        abort_unless(
            $authorization->canManage(Auth::user()) || $authorization->canAdminister(Auth::user()),
            403
        );

        $records = OfficialBusinessTrip::onlyTrashed()
            ->latest('deleted_at')
            ->get()
            ->map(function (OfficialBusinessTrip $trip): array {
                return [
                    'id' => $trip->id,
                    'ref' => $trip->ob_reference_no ?: ('OBF-' . $trip->id),
                    'employee' => $trip->employee_name ?: 'Not recorded',
                    'client' => $trip->client_name ?: 'Internal / Non-client',
                    'destination' => $trip->destination,
                    'previousStatus' => $trip->status,
                    'deletedOn' => $trip->deleted_at?->format('Y-m-d H:i'),
                    'deleted_at' => $trip->deleted_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'records' => $records,
        ]);
    }

    public function restoreDeleted(int $id): JsonResponse
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canAdminister(Auth::user()), 403);

        $trip = OfficialBusinessTrip::onlyTrashed()->findOrFail($id);

        $trip->restore();

        return response()->json([
            'message' => 'Official Business record restored successfully.',
            'record' => $this->formatTrip($trip->fresh()),
        ]);
    }

    public function forceDeleteDeleted(int $id): JsonResponse
    {
        abort_unless(app(OfficialBusinessAuthorizationService::class)->canAdminister(Auth::user()), 403);

        $trip = OfficialBusinessTrip::onlyTrashed()->findOrFail($id);

        if ($trip->tripTickets()->withTrashed()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'record' => 'This Official Business record cannot be permanently deleted because Trip Ticket history exists. Restore the record instead to preserve its audit trail.',
            ]);
        }

        $trip->forceDelete();

        return response()->json([
            'message' => 'Official Business record permanently deleted.',
        ]);
    }
    public function destroy(Request $request, OfficialBusinessTrip $officialBusinessTrip)
    {
        $this->authorizeTripAccess($officialBusinessTrip, true);

        $authorization = app(OfficialBusinessAuthorizationService::class);
        $user = $request->user();

        if ($authorization->canAdminister($user)) {
            $reference = $officialBusinessTrip->ob_reference_no
                ?: $officialBusinessTrip->employee_name;

            $officialBusinessTrip->delete();

            app(ActivityAuditService::class)->recordSemantic(
                ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                $officialBusinessTrip->id,
                'deleted',
                $reference,
                [
                    'deleted_by_user_id' => $user?->id,
                    'deletion_mode' => 'administrator',
                ],
                actorId: $user?->id
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Official Business deleted successfully.',
                    'record_id' => $officialBusinessTrip->id,
                ]);
            }

            return redirect()
                ->route('human-capital.obf')
                ->with('success', 'OBF deleted successfully.');
        }

        $changeRequest = $this->requestHumanCapitalChange(
            $request,
            'Official Business Trip Form',
            'delete',
            $officialBusinessTrip,
            null,
            $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->employee_name
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Official Business deletion submitted for admin approval.',
                'change_request_id' => $changeRequest->id,
            ], 202);
        }

        return redirect()
            ->route('human-capital.obf')
            ->with('success', 'OBF deletion submitted for admin approval.');
    }

    private function validateMultipleClientRoutes(array $validated, bool $isDraft): array
    {
        $isClientTravel = filter_var(
            $validated['is_client_travel'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $isMultiple = ($validated['client_mode'] ?? 'single') === 'multiple';

        if (! $isClientTravel || ! $isMultiple) {
            return $validated;
        }

        $clientIds = collect([
            $validated['company_id'] ?? null,
            ...($validated['company_ids'] ?? []),
        ])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $routes = collect($validated['client_routes'] ?? [])
            ->filter(fn ($route): bool => is_array($route))
            ->filter(fn ($route): bool => filled($route['company_id'] ?? null))
            ->mapWithKeys(
                fn (array $route): array => [
                    (int) $route['company_id'] => $route,
                ]
            );

        if (! $isDraft) {
            $errors = [];

            foreach ($clientIds as $index => $companyId) {
                $route = $routes->get($companyId);

                if (! $route || blank($route['destination'] ?? null)) {
                    $errors["client_routes.{$index}.destination"] =
                        'Destination is required for Client '.($index + 1).'.';
                }
            }

            if ($errors !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
        }

        /*
         * Keep the master destination for compatibility with existing
         * listings/reports, but the Trip Ticket stop is authoritative
         * for each individual client.
         */
        $primaryCompanyId = (int) ($validated['company_id'] ?? 0);
        $primaryRoute = $routes->get($primaryCompanyId);

        if ($primaryRoute && filled($primaryRoute['destination'] ?? null)) {
            $validated['destination'] = trim(
                (string) $primaryRoute['destination']
            );
        }

        return $validated;
    }

    private function syncMultipleClientPrimaryStops(
        OfficialBusinessTrip $trip,
        iterable $tickets,
        array $clientRoutes
    ): void {
        $routes = collect($clientRoutes)
            ->filter(fn ($route): bool => is_array($route))
            ->filter(fn ($route): bool => filled($route['company_id'] ?? null))
            ->mapWithKeys(
                fn (array $route): array => [
                    (int) $route['company_id'] => $route,
                ]
            );

        foreach ($tickets as $ticket) {
            $route = $routes->get((int) $ticket->company_id);

            if (! $route || blank($route['destination'] ?? null)) {
                continue;
            }

            $stop = $ticket->stops()
                ->withTrashed()
                ->where('sequence', 1)
                ->first();

            if ($stop?->trashed()) {
                $stop->restore();
            }

            if (! $stop) {
                $stop = $ticket->stops()->make([
                    'sequence' => 1,
                    'resolution_status' => 'Planned',
                ]);
            }

            $stop->destination = trim(
                (string) $route['destination']
            );

            if (filled($route['activity'] ?? null)) {
                $stop->activity = trim(
                    (string) $route['activity']
                );
            } elseif (blank($stop->activity)) {
                $stop->activity = $trip->purpose;
            }

            if (! $stop->planned_at && $trip->date_from) {
                $stop->planned_at = Carbon::parse(
                    $trip->date_from->format('Y-m-d')
                    .' '.
                    ($trip->departure_time ?: '00:00:00')
                );
            }

            $stop->save();
        }
    }
    private function validatedCreateDetails(array $validated, bool $isDraft): array
    {
        if (! filled($validated['classification'] ?? null)) {
            return [];
        }
        $service = app(\App\Services\OfficialBusinessCreateDetailService::class);

        return $service->validateRows($service->decode($validated['details_json'] ?? null), $validated['classification'], $isDraft);
    }

    private function projectCreateDetailsToLegacyHeader(array $validated, array $rows): array
    {
        if (! filled($validated['classification'] ?? null)) {
            return $validated;
        }
        $first = $rows[0] ?? [];
        $travel = $first['travel'] ?? [];
        $transportation = $first['transportation'] ?? [];
        $validated['is_client_travel'] = $validated['classification'] === OfficialBusinessTrip::CLASSIFICATION_CLIENT;
        $validated['client_mode'] = 'single';
        $validated['destination'] = $first['destination'] ?? null;
        $validated['purpose'] = $first['purpose'] ?? null;
        $validated['company_id'] = $validated['is_client_travel'] ? ($first['company_id'] ?? null) : null;
        $validated['deal_id'] = $validated['is_client_travel'] ? ($first['deal_id'] ?? null) : null;
        $validated['project_id'] = $validated['is_client_travel'] ? ($first['project_id'] ?? null) : null;
        $validated['date_from'] = $travel['departure_date'] ?? null;
        $validated['departure_time'] = $travel['departure_time'] ?? null;
        $validated['date_to'] = $travel['expected_arrival_date'] ?? null;
        $validated['return_time'] = $travel['expected_arrival_time'] ?? null;
        $expenses = collect($rows)->map(fn ($row) => data_get($row, 'travel.estimated_expense'))
            ->filter(fn ($amount) => filled($amount));
        $validated['estimated_expenses'] = $expenses->isEmpty() ? 0 : $expenses->sum();
        $validated['transportation_mode'] = $transportation['mode'] ?? null;
        $validated['transportation_details'] = $this->compatibleLegacyTransportationDetails($transportation);

        return $validated;
    }

    private function compatibleLegacyTransportationDetails(array $transportation): array
    {
        return match ($transportation['mode'] ?? null) {
            'Company Vehicle' => [
                'company_vehicle' => $transportation['vehicle'] ?? null,
                'vehicle_details' => $transportation['vehicle'] ?? null,
                'plate_number' => $transportation['plate_number'] ?? null,
                'assigned_driver' => $transportation['driver'] ?? null,
            ],
            'Private Vehicle' => [
                'private_vehicle_details' => $transportation['vehicle'] ?? null,
                'plate_number' => $transportation['plate_number'] ?? null,
                'assigned_driver' => $transportation['owner_driver'] ?? null,
            ],
            'Ride-Hailing' => ['ride_hailing_provider' => $transportation['provider'] ?? null],
            'Taxi' => ['other_description' => $transportation['other_details'] ?? null],
            'Public Transportation' => ['public_transport_mode' => $transportation['transport_type'] ?? null],
            'Rental Vehicle' => [
                'vehicle_details' => $transportation['vehicle'] ?? null,
                'plate_number' => $transportation['plate_number'] ?? null,
                'other_description' => $transportation['rental_provider'] ?? null,
            ],
            'Air' => [
                'carrier' => $transportation['airline'] ?? null,
                'booking_reference' => $transportation['flight_number'] ?? null,
                'air_travel_details' => $transportation['flight_number'] ?? null,
            ],
            'Sea/Ferry' => [
                'carrier' => $transportation['shipping_line'] ?? null,
                'booking_reference' => $transportation['vessel_trip'] ?? null,
                'sea_travel_details' => $transportation['vessel_trip'] ?? null,
            ],
            'Other' => ['other_description' => $transportation['other_details'] ?? null],
            default => [],
        };
    }

    private function teamNatures(array $validated): array
    {
        $natures = json_decode($validated['team_natures_json'] ?? '{}', true);
        if (! is_array($natures)) {
            return [];
        }
        return collect($natures)->filter(fn ($nature, $id) => is_numeric($id)
            && in_array($nature, ['Individual', 'Team'], true))->all();
    }

    private function validateObf(
        Request $request,
        bool $isUpdate = false,
        bool $isDraft = false
    ): array {
        $enhanced = filled($request->input('classification'));
        return $request->validate([
            'classification' => ['nullable', Rule::in(config('official_business.classifications'))],
            'details_json' => ['nullable', 'string'],
            'team_natures_json' => ['nullable', 'string'],
            'classification_change_confirmed' => ['nullable', 'boolean'],
            'employee_id' => [$isDraft || $isUpdate || ! $this->canManageObf() ? 'nullable' : 'required', 'nullable', 'exists:employees,id'],

            'immediate_superior' => ['nullable', 'string', 'max:255'],
            'superior_email' => ['nullable', 'email', 'max:255'],

            'destination' => [
                $isDraft
                    || $enhanced
                    || ! filter_var(
                        $request->input('is_client_travel'),
                        FILTER_VALIDATE_BOOLEAN
                    )
                    || ($request->input('client_mode') ?? 'single') === 'multiple'
                        ? 'nullable'
                        : 'required',
                'nullable',
                'string',
                'max:255',
            ],
            'additional_stops' => ['nullable', 'string'],
            'purpose' => [$isDraft || $enhanced ? 'nullable' : 'required', 'nullable', 'string'],
            'purpose_options' => ['nullable', 'array'],
            'purpose_options.*' => ['nullable', 'string', 'max:255'],
            'purpose_other' => ['nullable', 'string', 'max:255'],
            'trip_type' => ['nullable', 'string', 'max:255'],
            'date_from' => [$isDraft || $enhanced ? 'nullable' : 'required', 'nullable', 'date'],
            'date_to' => $isDraft
                ? ['nullable', 'date']
                : ['nullable', 'date', 'after_or_equal:date_from'],
            'departure_time' => ['nullable'],
            'return_time' => ['nullable'],
            'nature_of_travel' => ['nullable', 'string', 'max:255'],
            'team_members' => ['nullable', 'array'],
            'team_members.*' => ['nullable', 'string', 'max:255'],
            'team_member_employee_ids' => ['nullable', 'array', 'max:100'],
            'team_member_employee_ids.*' => ['nullable', 'integer', 'distinct', 'exists:employees,id'],

            'is_client_travel' => ['nullable'],
            'client_mode' => ['nullable', 'in:single,multiple'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['nullable', 'integer', 'distinct', 'exists:companies,id'],
            'client_routes' => ['nullable', 'array', 'max:50'],
            'client_routes.*.company_id' => ['required_with:client_routes', 'integer', 'distinct', 'exists:companies,id'],
            'client_routes.*.destination' => ['nullable', 'string', 'max:255'],
            'client_routes.*.activity' => ['nullable', 'string', 'max:2000'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_type' => ['nullable', 'string', 'max:255'],
            'client_id_no' => ['nullable', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_contract_number' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['nullable', 'string', 'max:255'],
            'travel_billability' => ['nullable', 'string', 'max:255'],

            'client_payment_status' => ['nullable', 'string', 'max:255'],
            'client_payment_items' => ['nullable', 'array'],
            'client_payment_items.*' => ['nullable', 'string', 'max:255'],
            'client_payment_other' => ['nullable', 'string', 'max:255'],
            'amount_client_will_pay' => ['nullable', 'numeric', 'min:0'],

            'travel_credit_monthly_limit' => ['nullable', 'numeric', 'min:0'],
            'travel_credit_used' => ['nullable', 'numeric', 'min:0'],
            'travel_credit_needed' => ['nullable', 'numeric', 'min:0'],
            'travel_credit_remaining' => ['nullable', 'numeric', 'min:0'],

            'transportation_mode' => ['nullable', 'string', 'max:255'],
            'company_vehicle' => ['nullable', 'string', 'max:255'],
            'assigned_driver' => ['nullable', 'string', 'max:255'],
            'vehicle_details' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:255'],
            'odometer' => ['nullable', 'string', 'max:255'],
            'fuel_level' => ['nullable', 'string', 'max:255'],
            'private_vehicle_details' => ['nullable', 'string', 'max:255'],
            'public_transport_details' => ['nullable', 'string', 'max:255'],
            'air_travel_details' => ['nullable', 'string', 'max:255'],
            'sea_travel_details' => ['nullable', 'string', 'max:255'],
            'ride_hailing_provider' => ['nullable', 'string', 'max:255'],
            'transportation_cost' => ['nullable', 'numeric', 'min:0'],
            'public_transport_mode' => ['nullable', 'string', 'max:255'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'transport_route' => ['nullable', 'string', 'max:255'],
            'booking_reference' => ['nullable', 'string', 'max:255'],
            'other_transportation' => ['nullable', 'string', 'max:500'],
            'estimated_expenses' => ['nullable', 'numeric', 'min:0'],

            'attachment_types' => ['nullable', 'array'],
            'attachment_types.*' => ['nullable', 'string', 'max:255'],
            'attachment_other' => ['nullable', 'string', 'max:255'],
            'attachment_rows_json' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],

            'remarks' => ['nullable', 'string'],
            'client_report_description' => ['nullable', 'string'],
            'include_in_client_report' => ['nullable', 'boolean'],
        ]);
    }

    private function storeAttachments(Request $request): array
    {
        $attachments = [];

        foreach ($request->file('attachments', []) as $file) {
            if (!$file) {
                continue;
            }

            $path = $file->store('obf/attachments', 'public');

            $attachments[] = [
                'path' => $path,
                'url' => Storage::url($path),
                'original_name' => $file->getClientOriginalName(),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        return $attachments;
    }

    private function resolveClientCompanyId(?Contact $contact): ?int
    {
        return \App\Support\ActivityRelationships::companyId($contact);
    }

    private function filterArray(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => filled($item))
            ->values()
            ->all();
    }

    private function transportationDetails(array $validated): array
    {
        $mode = $validated['transportation_mode'] ?? null;
        $allowed = match ($mode) {
            'Company Vehicle' => ['company_vehicle', 'assigned_driver', 'vehicle_details', 'plate_number', 'odometer', 'fuel_level'],
            'Personal Vehicle', 'Private Vehicle' => ['private_vehicle_details', 'plate_number'],
            'Taxi / Ride-Hailing' => ['ride_hailing_provider', 'transportation_cost'],
            'Public Transportation', 'Public Transport' => ['public_transport_details', 'public_transport_mode', 'transportation_cost'],
            'Air / Sea' => ['carrier', 'transport_route', 'booking_reference', 'transportation_cost'],
            'Air Travel' => ['carrier', 'transport_route', 'booking_reference', 'transportation_cost', 'air_travel_details'],
            'Sea Travel' => ['carrier', 'transport_route', 'booking_reference', 'transportation_cost', 'sea_travel_details'],
            'Other' => ['other_transportation'],
            default => [],
        };
        $active = array_intersect_key($validated, array_flip($allowed));

        return [
            'company_vehicle' => $active['company_vehicle'] ?? null,
            'assigned_driver' => $active['assigned_driver'] ?? null,
            'vehicle_details' => $active['vehicle_details'] ?? null,
            'plate_number' => $active['plate_number'] ?? null,
            'odometer' => $active['odometer'] ?? null,
            'fuel_level' => $active['fuel_level'] ?? null,
            'private_vehicle_details' => $active['private_vehicle_details'] ?? null,
            'public_transport_details' => $active['public_transport_details'] ?? null,
            'air_travel_details' => $active['air_travel_details'] ?? null,
            'sea_travel_details' => $active['sea_travel_details'] ?? null,
            'ride_hailing_provider' => $active['ride_hailing_provider'] ?? null,
            'transportation_cost' => $active['transportation_cost'] ?? null,
            'public_transport_mode' => $active['public_transport_mode'] ?? null,
            'carrier' => $active['carrier'] ?? null,
            'route' => $active['transport_route'] ?? null,
            'booking_reference' => $active['booking_reference'] ?? null,
            'other_description' => $active['other_transportation'] ?? null,
        ];
    }

    private function resolveSubmittedReferenceNo(Request $request): string
    {
        $submitted = strtoupper(
            trim((string) $request->input('ob_reference_no', ''))
        );

        if (
            preg_match('/^OBF-\d{8}-[A-Z0-9]{5}$/', $submitted) === 1
            && ! OfficialBusinessTrip::withTrashed()
                ->where('ob_reference_no', $submitted)
                ->exists()
        ) {
            return $submitted;
        }

        do {
            $generated = $this->generateReferenceNo();
        } while (
            OfficialBusinessTrip::withTrashed()
                ->where('ob_reference_no', $generated)
                ->exists()
        );

        return $generated;
    }
    private function generateReferenceNo(): string
    {
        return 'OBF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }

    private function normalizeLegacyMovementState(OfficialBusinessTrip $trip): void
    {
        if ((int) $trip->workflow_version < 3) {
            return;
        }

        $legacyMovement = match ($trip->status) {
            'Ready for Travel' => 'Ready',
            'Ongoing' => 'Departed',
            'Returned' => 'Returned',
            default => null,
        };

        if ($legacyMovement === null) {
            return;
        }

        /*
         * Actual timestamps are more authoritative than the
         * legacy movement-like status when determining movement.
         */
        if ($trip->actual_return !== null) {
            $legacyMovement = 'Returned';
        } elseif ($trip->actual_departure !== null) {
            $legacyMovement = 'Departed';
        }

        $trip->forceFill([
            'status' => 'Approved',
            'movement_status' => $legacyMovement,
        ])->saveQuietly();

        $trip->refresh();
    }

    private function formatTrip(OfficialBusinessTrip $trip): array
    {
        $formatted = [
            'id' => $trip->id,
            'can_view' => app(OfficialBusinessAuthorizationService::class)->canView(Auth::user(), $trip),
            'can_edit' => app(OfficialBusinessAuthorizationService::class)->canEdit(Auth::user(), $trip),
            'can_delete' => $this->canDeleteTrip($trip),
            'ob_reference_no' => $trip->ob_reference_no,
            'classification' => $trip->classification,
            'detail_rows' => Schema::hasTable('official_business_trip_details')
                ? $trip->details()->with(['deal', 'project', 'travelDetail', 'transportationDetail'])->orderBy('sort_order')->orderBy('id')->get()
                    ->map(function ($detail): array {
                        $row = $detail->toArray();
                        unset($row['deal'], $row['project'], $row['travel_detail'], $row['transportation_detail']);
                        $travel = $detail->travelDetail;
                        $row['travel'] = $travel ? [
                            'departure_date' => $travel->getRawOriginal('departure_date'),
                            'departure_time' => $travel->getRawOriginal('departure_time'),
                            'expected_arrival_date' => $travel->getRawOriginal('expected_arrival_date'),
                            'expected_arrival_time' => $travel->getRawOriginal('expected_arrival_time'),
                            'estimated_expense' => $travel->getRawOriginal('estimated_expense'),
                        ] : [];
                        $row['transportation'] = $detail->transportationDetail?->only([
                            'mode', 'vehicle', 'plate_number', 'driver', 'owner_driver', 'provider',
                            'transport_type', 'rental_provider', 'airline', 'flight_number',
                            'shipping_line', 'vessel_trip', 'other_details',
                        ]) ?? [];
                        if ($detail->engagement_type === 'Regular' && ! $detail->project_id && $detail->deal_id) {
                            $workspace = app(\App\Services\OfficialBusinessEngagementService::class)
                                ->legacyRegular((int) $detail->company_id, (int) $detail->deal_id);
                            $row['project_id'] = $workspace?->id;
                            $row['engagement_name'] = $workspace?->name ?: $detail->deal?->deal_name;
                        } else {
                            $row['engagement_name'] = $detail->project?->name;
                        }
                        $company = $detail->company_id && app(OfficialBusinessAuthorizationService::class)->canReadFinance(Auth::user())
                            ? Company::find($detail->company_id) : null;
                        $row['travel_credit_balance'] = $company && Schema::hasTable('company_travel_credit_accounts')
                            ? app(\App\Services\CompanyTravelCreditService::class)->summary($company)['available_units']
                            : null;
                        return $row;
                    })->all()
                : [],
            'personnel_rows' => Schema::hasTable('official_business_trip_personnel')
                ? $trip->personnel()->orderBy('id')->get()->map(fn ($member) => [
                    'employee_id' => $member->employee_id,
                    'role' => $member->role ?? null,
                    'nature_of_travel' => $member->nature_of_travel ?? null,
                    'employee_code' => $member->employee_code_snapshot,
                    'employee_name' => $member->employee_name_snapshot,
                    'position' => $member->position_snapshot,
                    'department' => $member->department_snapshot,
                    'immediate_superior' => $member->immediate_superior_snapshot ?? null,
                ])->all()
                : [],
            'employee_id' => $trip->employee_id,
            'employee_code' => $trip->employee_code,
            'employee_name' => $trip->employee_name,
            'position' => $this->effectiveTripPosition($trip),
            'department' => $trip->department,
            'immediate_superior' => $trip->immediate_superior,
            'superior_email' => $trip->superior_email,

            'destination' => $trip->destination,
            'additional_stops' => $trip->additional_stops,
            'purpose' => $trip->purpose,
            'purpose_options' => $trip->purpose_options ?? [],
            'purpose_other' => $trip->purpose_other,
            'trip_type' => $trip->trip_type,
            'date_from' => optional($trip->date_from)->format('Y-m-d'),
            'date_to' => optional($trip->date_to)->format('Y-m-d'),
            'departure_time' => $trip->departure_time,
            'return_time' => $trip->return_time,
            'actual_departure' => $trip->actual_departure?->toIso8601String(),
            'actual_return' => $trip->actual_return?->toIso8601String(),
            'nature_of_travel' => $trip->nature_of_travel,
            'team_members' => $trip->team_members ?? [],
            'team_member_employee_ids' => \Illuminate\Support\Facades\Schema::hasTable('official_business_trip_personnel')
                ? $trip->personnel()->where('employee_id', '<>', $trip->employee_id)->pluck('employee_id')->all()
                : [],

            'is_client_travel' => $trip->is_client_travel,
            'company_id' => $trip->company_id,
            'contact_id' => $trip->contact_id,
            'deal_id' => $trip->deal_id,
            'project_id' => $trip->project_id,
            'duration_minutes' => $trip->duration_minutes,
            'client_type' => $trip->client_type,
            'client_id_no' => $trip->client_id_no,
            'client_name' => $trip->client_name,
            'client_email' => $trip->client_email,
            'client_contract_number' => $trip->client_contract_number,
            'contract_type' => $trip->contract_type,
            'travel_billability' => $trip->travel_billability,

            'client_payment_status' => $trip->client_payment_status,
            'client_payment_items' => $trip->client_payment_items ?? [],
            'client_payment_other' => $trip->client_payment_other,
            'amount_client_will_pay' => $trip->amount_client_will_pay,

            'travel_credit_details' => $trip->travel_credit_details ?? [],
            'transportation_mode' => $trip->transportation_mode,
            'transportation_details' => $trip->transportation_details ?? [],
            'estimated_expenses' => $trip->estimated_expenses,

            'attachment_paths' => $trip->attachment_paths ?? [],
            'attachment_types' => $trip->attachment_types ?? [],
            'attachment_other' => $trip->attachment_other,
            'create_attachments' => Schema::hasTable('activity_attachments')
                ? \App\Models\ActivityAttachment::query()
                    ->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL)
                    ->where('activity_id', $trip->id)
                    ->orderBy('uploaded_at')->orderBy('id')->get()
                    ->map(fn (\App\Models\ActivityAttachment $attachment): array => [
                        'id' => $attachment->id,
                        'type' => $attachment->category,
                        'description' => $attachment->metadata['description'] ?? '',
                        'filename' => $attachment->original_filename ?: $attachment->filename,
                        'url' => $attachment->external_url ?: ($attachment->storage_disk && $attachment->storage_path
                            ? Storage::disk($attachment->storage_disk)->url($attachment->storage_path)
                            : null),
                        'detail_id' => $attachment->official_business_trip_detail_id,
                    ])->all()
                : [],

            'remarks' => $trip->remarks,
            'workflow_version' => (int) $trip->workflow_version,
            'status' => $trip->status,
            'movement_status' => (int) $trip->workflow_version >= 3
                ? ($trip->movement_status ?: (
                    $trip->actual_return
                        ? 'Returned'
                        : ($trip->actual_departure ? 'Departed' : 'Not Started')
                ))
                : null,            'can_control_travel' => app(\App\Services\OfficialBusinessAuthorizationService::class)
                ->canControlTravel(Auth::user(), $trip),
            'prepared_by_user_id' => $trip->prepared_by_user_id,
            'prepared_by' => $trip->preparedBy,
            'client_report_description' => $trip->client_report_description,
            'include_in_client_report' => (bool) $trip->include_in_client_report,
            'updated_at' => $trip->updated_at?->toIso8601String(),

            'trip_tickets' => (
                (int) $trip->workflow_version >= 3
                && $trip->is_client_travel
            )
                ? $trip->tripTickets()
                    ->with('stops')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (OfficialBusinessTripTicket $ticket): array => [
                        'id' => $ticket->id,
                        'company_id' => $ticket->company_id,
                        'stops' => $ticket->stops
                            ->sortBy('sequence')
                            ->map(fn ($stop): array => [
                                'id' => $stop->id,
                                'sequence' => (int) $stop->sequence,
                                'destination' => $stop->destination,
                                'activity' => $stop->activity,
                                'resolution_status' => $stop->resolution_status,
                            ])
                            ->values()
                            ->all(),
                        'reference_no' => $ticket->reference_no,
                        'client_name_snapshot' => $ticket->client_name_snapshot,
                        'status' => $ticket->status,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
        $viewer = Auth::user();

        // OBV3 finance-sensitive master fields must only be exposed
        // to viewers with explicit O.B. finance-read authorization.
        if (
            ! $viewer
            || ! app(OfficialBusinessAuthorizationService::class)
                ->canReadFinance($viewer)
        ) {
            unset(
                $formatted['client_payment_status'],
                $formatted['client_payment_items'],
                $formatted['client_payment_other'],
                $formatted['amount_client_will_pay'],
                $formatted['travel_credit_details'],
                $formatted['estimated_expenses']
            );
        }

        return $formatted;
    }

    private function formatActivityTrip(
        OfficialBusinessTrip $trip,
        array $identity,
        OfficialBusinessActivityService $officialBusinessActivities
    ): array
    {
        $isClientTravel = (bool) $trip->is_client_travel;

        $ticketClients = (
            (int) $trip->workflow_version >= 3
            && $isClientTravel
        )
            ? $trip->tripTickets()
                ->orderBy('id')
                ->get(['company_id', 'client_name_snapshot'])
            : collect();

        $clientNames = $ticketClients
            ->pluck('client_name_snapshot')
            ->filter(fn ($name) => filled($name))
            ->unique()
            ->values();

        $activityType = ! $isClientTravel
            ? 'Internal / JK&C'
            : ($ticketClients->count() > 1 ? 'Multiple Clients' : 'One Client');

        $client = ! $isClientTravel
            ? 'Internal / JK&C'
            : (
                $clientNames->isNotEmpty()
                    ? $clientNames->implode(', ')
                    : ($trip->client_name ?: 'Client')
            );

        return [
            'id' => $trip->id,
            'ref' => $trip->ob_reference_no ?: 'OBF-'.$trip->id,
            'employee_id' => $trip->employee_id,
            'employee' => $trip->employee_name,
            'type' => $activityType,
            'clients' => $client,
            'destination' => collect([$trip->destination, $trip->additional_stops])
                ->filter(fn ($value) => filled($value))
                ->implode(' / '),
            'departure' => $this->formatActivityDateTime($trip->date_from, $trip->departure_time),
            'expectedReturn' => $this->formatActivityDateTime($trip->date_to ?: $trip->date_from, $trip->return_time),
            'actualDeparture' => $trip->actual_departure?->toIso8601String(),
            'actualReturn' => $trip->actual_return?->toIso8601String(),
            'dateFrom' => optional($trip->date_from)->format('Y-m-d'),
            'dateTo' => optional($trip->date_to ?: $trip->date_from)->format('Y-m-d'),
            'workflowVersion' => (int) $trip->workflow_version,
            'status' => $trip->status,
            'movement_status' => (int) $trip->workflow_version >= 3
                ? ($trip->movement_status ?: (
                    $trip->actual_return
                        ? 'Returned'
                        : ($trip->actual_departure ? 'Departed' : 'Not Started')
                ))
                : null,            'can_control_travel' => app(\App\Services\OfficialBusinessAuthorizationService::class)
                ->canControlTravel(Auth::user(), $trip),
            'isMine' => $officialBusinessActivities->isMine($trip, $identity),
            'company_id' => $trip->company_id,
            'contact_id' => $trip->contact_id,
            'deal_id' => $trip->deal_id,
            'project_id' => $trip->project_id,
            'duration_minutes' => $trip->actual_departure && $trip->actual_return
                ? (int) $trip->actual_departure->diffInMinutes($trip->actual_return)
                : null,
            'prepared_by_user_id' => $trip->prepared_by_user_id,
            'prepared_by' => $trip->preparedBy,
            'client_report_description' => $trip->client_report_description,
            'include_in_client_report' => (bool) $trip->include_in_client_report,
        ];
    }

    public function downloadHistoryPdf(OfficialBusinessTrip $officialBusinessTrip)
    {
        $this->authorizeTripAccess($officialBusinessTrip);

        $officialBusinessTrip->load([
            'employee',
            'preparedBy',
            'company',
            'tripTickets.company',
            'tripTickets.personnel',
        ]);

        $history = \App\Models\ActivityAuditLog::query()
            ->where(
                'activity_type',
                \App\Services\ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL
            )
            ->where('activity_id', $officialBusinessTrip->id)
            ->with('user:id,name,email')
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'human-capital.official-business-history-pdf',
            [
                'trip' => $officialBusinessTrip,
                'history' => $history,
                'includeFinance' => app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance(request()->user()),
                'generatedAt' => now(),
            ]
        )
            ->setPaper('a4', 'portrait')
            ->download(
                ($officialBusinessTrip->ob_reference_no ?: 'official-business')
                .'-history.pdf'
            );
    }
    private function formatActivityDateTime($date, ?string $time): ?string
    {
        if (! $date) {
            return null;
        }

        $formatted = $date->format('M j, Y');

        if (! filled($time)) {
            return $formatted;
        }

        try {
            return $formatted.', '.Carbon::parse($time)->format('g:i A');
        } catch (\Throwable) {
            return $formatted;
        }
    }

    private function officialBusinessDurationMinutes(array $attributes): ?int
    {
        if (blank($attributes['date_from'] ?? null)
            || blank($attributes['departure_time'] ?? null)
            || blank($attributes['return_time'] ?? null)) {
            return null;
        }

        try {
            $start = Carbon::parse($attributes['date_from'].' '.$attributes['departure_time']);
            $endDate = $attributes['date_to'] ?? $attributes['date_from'];
            $end = Carbon::parse($endDate.' '.$attributes['return_time']);

            return $end->greaterThanOrEqualTo($start) ? (int) $start->diffInMinutes($end) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function effectiveEmployeePosition(?Employee $employee): ?string
    {
        if (! $employee) {
            return null;
        }

        $position = trim((string) ($employee->position ?? ''));

        if ($position !== '') {
            return $position;
        }

        if (! $employee->user_id) {
            return null;
        }

        $role = \App\Models\User::query()
            ->whereKey($employee->user_id)
            ->value('role');

        return filled($role)
            ? trim((string) $role)
            : null;
    }

    /** @param array<int, int|string> $employeeIds */
    private function masterTeamNames(array $employeeIds, ?Employee $filer): array
    {
        $ids = collect($employeeIds)
            ->filter(fn ($id): bool => filled($id) && (int) $id !== (int) ($filer?->id ?? 0))
            ->map(fn ($id): int => (int) $id)
            ->unique()->values();
        $employees = Employee::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn (int $id): ?string => $employees->get($id)?->full_name)
            ->filter()->values()->all();
    }

    private function employeeSuperiorName(Employee $employee): ?string
    {
        $name = trim((string) ($employee->immediate_supervisor ?: $employee->reporting_to));

        return $name !== '' ? $name : null;
    }

    private function employeeSuperiorEmail(Employee $employee): ?string
    {
        $name = $this->employeeSuperiorName($employee);
        if (! $name) {
            return null;
        }

        $supervisor = Employee::query()->get(['first_name', 'middle_name', 'last_name', 'work_email', 'email'])
            ->first(fn (Employee $candidate) => strcasecmp($candidate->full_name, $name) === 0);

        return $supervisor?->work_email ?: $supervisor?->email;
    }

    private function superiorSnapshot(?Employee $employee, array $validated, ?OfficialBusinessTrip $existing = null): array
    {
        if ($existing && (int) $existing->employee_id === (int) ($employee?->id ?? 0)) {
            return [
                'name' => $existing->immediate_superior,
                'email' => $existing->superior_email,
            ];
        }

        $name = $employee ? $this->employeeSuperiorName($employee) : null;

        return [
            'name' => $name ?: ($validated['immediate_superior'] ?? null),
            'email' => $name
                ? $this->employeeSuperiorEmail($employee)
                : ($validated['superior_email'] ?? null),
        ];
    }

    private function effectiveTripPosition(OfficialBusinessTrip $trip): ?string
    {
        $position = trim((string) ($trip->position ?? ''));

        if ($position !== '') {
            return $position;
        }

        if (! $trip->employee_id) {
            return null;
        }

        $employee = Employee::query()
            ->select(['id', 'user_id', 'position'])
            ->find($trip->employee_id);

        return $this->effectiveEmployeePosition($employee);
    }
    private function canManageObf(): bool
    {
        $user = Auth::user();

        return app(OfficialBusinessAuthorizationService::class)->canManage($user);
    }

    private function canDeleteTrip(OfficialBusinessTrip $trip): bool
    {
        $user = Auth::user();

        if (! $user || ! app(OfficialBusinessAuthorizationService::class)->canView($user, $trip)) {
            return false;
        }

        if (app(OfficialBusinessAuthorizationService::class)->canManage($user)) {
            return true;
        }

        $employee = $this->currentEmployee();

        return (int) $trip->created_by === (int) $user->id
            || ($employee && (int) $trip->employee_id === (int) $employee->id)
            || ($employee && (int) $trip->workflow_version >= 3 && $trip->tripTickets()
                ->whereHas('personnel', fn ($query) => $query->where('employee_id', $employee->id))
                ->exists());
    }

    private function currentEmployee(): ?Employee
    {
        return $this->currentHumanCapitalEmployee(Auth::user());
    }

    private function resolveEmployee(?int $employeeId): Employee
    {
        if ($this->canManageObf() && $employeeId) {
            return Employee::with('department')->findOrFail($employeeId);
        }

        $employee = $this->currentEmployee();

        if (! $employee) {
            abort(403, 'No employee profile is linked to your account email.');
        }

        return $employee;
    }

    private function authorizeTripMovement(OfficialBusinessTrip $trip): void
    {
        abort_unless(
            app(OfficialBusinessAuthorizationService::class)->canControlTravel(Auth::user(), $trip),
            403
        );
    }

    private function authorizeTripCancellation(OfficialBusinessTrip $trip): void
    {
        $user = Auth::user();
        $authorization = app(OfficialBusinessAuthorizationService::class);

        if ($authorization->canManage($user)) {
            return;
        }

        abort_unless($user && (
            (int) $trip->created_by === (int) $user->id
            || $trip->employee()->where('user_id', $user->id)->exists()
        ), 403);
    }

    private function authorizeTripAccess(OfficialBusinessTrip $trip, bool $editing = false): void
    {
        if ($editing && (int) $trip->workflow_version >= 3
            && (! $this->canManageObf() || in_array($trip->status, ['Draft', 'Reverted'], true))) {
            abort_unless(app(OfficialBusinessAuthorizationService::class)->canEdit(Auth::user(), $trip), 403);

            return;
        }

        if ($this->canManageObf()) {
            return;
        }

        $employee = $this->currentEmployee();

        $identity = $this->humanCapitalEmployeeIdentity($employee, Auth::user());
        $isParticipant = (int) $trip->workflow_version >= 3
            ? ($employee && $trip->tripTickets()->whereHas(
                'personnel',
                fn ($query) => $query->where('employee_id', $employee->id)
            )->exists())
            : (! empty($identity['name']) && collect($trip->team_members ?? [])
                ->map(fn ($item) => strtolower(trim((string) $item)))
                ->contains(strtolower(trim((string) $identity['name']))));

        $editableStatus = (int) $trip->workflow_version >= 3
            ? in_array($trip->status, ['Draft', 'Reverted'], true)
            : $trip->status === 'Pending';

        if (
            ! $employee
            || ($trip->employee_id !== $employee->id && $trip->created_by !== Auth::id() && ! $isParticipant)
            || ($editing && ! $editableStatus)
        ) {
            abort(403);
        }
    }
}

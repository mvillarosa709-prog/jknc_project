<?php

namespace App\Http\Controllers;

use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\OfficialBusinessTripTicketCost;
use App\Models\ActivityAttachment;
use App\Models\ActivityAuditLog;
use App\Services\ActivityAttachmentService;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;
use App\Services\OfficialBusinessTripTicketCompletionService;
use App\Services\OfficialBusinessTripTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class OfficialBusinessTripTicketController extends Controller
{
    public function financeReady(Request $request): JsonResponse
    {
        abort_unless(
            app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($request->user()),
            403
        );

        $filters = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = OfficialBusinessTripTicket::query()
            ->where('status', 'Completed')
            ->whereHas('officialBusinessTrip', fn ($query) => $query->whereNull('deleted_at'))
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->with(['officialBusinessTrip', 'company', 'stops', 'personnel', 'financialTreatment.travelCreditEntry', 'costs', 'evidence'])
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);

        return response()->json([
            'tickets' => $page->getCollection()->map(fn (OfficialBusinessTripTicket $ticket): array => [
                'id' => $ticket->id,
                'trip_ticket_no' => $ticket->reference_no,
                'ob_reference_no' => $ticket->officialBusinessTrip?->ob_reference_no,
                'company_id' => $ticket->company_id,
                'client' => $ticket->client_name_snapshot,
                'deal_id' => $ticket->officialBusinessTrip?->deal_id,
                'project_id' => $ticket->officialBusinessTrip?->project_id,
                'date' => $ticket->officialBusinessTrip?->date_from?->format('Y-m-d'),
                'activities' => $ticket->stops->pluck('activity')->filter()->values(),
                'personnel' => $ticket->personnel->pluck('employee_name_snapshot')->values(),
                'billing_treatment' => $ticket->financialTreatment?->billing_treatment,
                'travel_credit_units' => $ticket->financialTreatment?->travel_credit_units,
                'travel_credit_value' => $ticket->financialTreatment?->travel_credit_value,
                'travel_credit_entry_id' => $ticket->financialTreatment?->travel_credit_entry_id,
                'additional_costs' => $ticket->costs->map(fn (OfficialBusinessTripTicketCost $cost): array => [
                    'id' => $cost->id,
                    'amount' => $cost->amount,
                    'billing_treatment' => $cost->billing_treatment,
                ])->values(),
                'evidence_count' => $ticket->evidence->count(),
                'pdf_url' => route('activities.official-business.trip-tickets.pdf', $ticket),
            ])->values(),
            'pagination' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function clientIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isClient(), 403);

        return response()->json([
            'tickets' => $this->clientTickets($request)
                ->map(fn (OfficialBusinessTripTicket $ticket): array => $this->formatClientTicket($ticket))->values(),
        ]);
    }

    public function clientPage(Request $request): View
    {
        abort_unless($request->user()?->isClient(), 403);

        return view('client.official-business-trip-tickets', [
            'tickets' => $this->clientTickets($request)
                ->map(fn (OfficialBusinessTripTicket $ticket): array => $this->formatClientTicket($ticket)),
        ]);
    }

    private function clientTickets(Request $request): \Illuminate\Support\Collection
    {
        return OfficialBusinessTripTicket::query()
            ->where('status', 'Completed')
            ->whereHas('officialBusinessTrip', fn ($trip) => $trip->whereNull('deleted_at'))
            ->whereHas('company', function ($company) use ($request): void {
                $company->where(function ($scope) use ($request): void {
                    $scope->whereHas('contacts', fn ($contacts) => $contacts->where('user_id', $request->user()->id))
                        ->orWhereHas('primaryContact', fn ($contact) => $contact->where('user_id', $request->user()->id));
                });
            })
            ->with(['officialBusinessTrip', 'company', 'stops', 'personnel', 'evidence.uploadedBy'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function clientShow(Request $request, OfficialBusinessTripTicket $officialBusinessTripTicket): JsonResponse
    {
        abort_unless($request->user()?->isClient(), 403);
        Gate::forUser($request->user())->authorize('view', $officialBusinessTripTicket);

        return response()->json([
            'ticket' => $this->formatClientTicket($officialBusinessTripTicket),
        ]);
    }

    public function verify(string $reference)
    {
        $ticket = OfficialBusinessTripTicket::query()
            ->where('reference_no', $reference)
            ->where('status', 'Completed')
            ->whereHas('officialBusinessTrip', fn ($trip) => $trip->whereNull('deleted_at'))
            ->with([
                'officialBusinessTrip',
                'stops',
                'personnel',
            ])
            ->firstOrFail();

        $viewer = request()->user();
        if ($viewer?->isClient()) {
            Gate::forUser($viewer)->authorize('view', $ticket);
        }

        return view('public.official-business-trip-ticket-verification', [
            'ticket' => $ticket,
            'showDetails' => $viewer && Gate::forUser($viewer)->allows('view', $ticket),
        ]);
    }

    public function index(Request $request, OfficialBusinessTrip $officialBusinessTrip): JsonResponse
    {
        Gate::forUser($request->user())->authorize(
            'viewAny',
            [OfficialBusinessTripTicket::class, $officialBusinessTrip]
        );

        $tickets = $officialBusinessTrip->tripTickets()
            ->with(['company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence.uploadedBy'])
            ->orderBy('id')
            ->get()
            ->filter(fn (OfficialBusinessTripTicket $ticket): bool => Gate::forUser($request->user())
                ->allows('view', $ticket));

        return response()->json([
            'tickets' => $tickets
                ->map(fn (OfficialBusinessTripTicket $ticket): array => $this->formatTicket($ticket))
                ->values(),
        ]);
    }

    public function show(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket
    ): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $officialBusinessTripTicket);

        return response()->json([
            'ticket' => $this->formatTicket(
                $officialBusinessTripTicket->load([
                    'company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence.uploadedBy',
                ])
            ),
        ]);
    }

    public function store(
        Request $request,
        OfficialBusinessTrip $officialBusinessTrip,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $ticket = $service->syncClients(
            $request->user(),
            $officialBusinessTrip,
            [(int) $validated['company_id']]
        )->sole();

        return response()->json([
            'message' => 'Trip Ticket synchronized successfully.',
            'ticket' => $this->formatTicket($ticket->load([
                'company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence.uploadedBy',
            ])),
        ], $ticket->wasRecentlyCreated ? 201 : 200);
    }

    public function sync(
        Request $request,
        OfficialBusinessTrip $officialBusinessTrip,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        $validated = $request->validate([
            'company_ids' => ['required', 'array', 'min:1', 'max:50'],
            'company_ids.*' => ['required', 'integer', 'distinct', 'exists:companies,id'],
        ]);

        $tickets = $service->syncClients(
            $request->user(),
            $officialBusinessTrip,
            $validated['company_ids']
        );

        return response()->json([
            'message' => 'Client Trip Tickets synchronized successfully.',
            'tickets' => $tickets
                ->map(fn (OfficialBusinessTripTicket $ticket): array => $this->formatTicket(
                    $ticket->load([
                        'company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence.uploadedBy',
                    ])
                ))
                ->values(),
        ]);
    }

    public function update(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        $validated = $request->validate([
            'stops' => ['sometimes', 'array', 'max:50'],
            'stops.*.sequence' => ['required', 'integer', 'min:1', 'max:65535', 'distinct'],
            'stops.*.destination' => ['required', 'string', 'max:255'],
            'stops.*.activity' => ['nullable', 'string'],
            'stops.*.planned_at' => ['nullable', 'date'],
            'stops.*.actual_arrival_at' => ['nullable', 'date'],
            'stops.*.resolved_at' => ['nullable', 'date'],
            'stops.*.resolution_status' => [
                'nullable',
                Rule::in(['Planned', 'Visited', 'Resolved', 'Cancelled']),
            ],
            'personnel' => ['sometimes', 'array', 'max:100'],
            'personnel.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'personnel.*.role' => ['nullable', 'string', 'max:40'],
            'personnel.*.participation_status' => [
                'nullable',
                Rule::in(['Assigned', 'Confirmed', 'Did Not Go']),
            ],
        ]);

        $ticket = $service->updateDetails(
            $request->user(),
            $officialBusinessTripTicket,
            array_key_exists('stops', $validated) ? $validated['stops'] : null,
            array_key_exists('personnel', $validated) ? $validated['personnel'] : null
        );

        return response()->json([
            'message' => 'Trip Ticket details updated successfully.',
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    public function destroy(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket
    ): JsonResponse
    {
        Gate::forUser($request->user())->authorize('delete', $officialBusinessTripTicket);

        abort_unless(
            in_array($officialBusinessTripTicket->status, ['Draft', 'Reverted', 'Cancelled'], true),
            422,
            'Only Draft, Reverted, or Cancelled Trip Tickets can be archived.'
        );

        $officialBusinessTripTicket->forceFill([
            'status' => 'Cancelled',
            'archived_reason' => 'manual_cancellation',
            'archived_by_user_id' => $request->user()->id,
            'archived_at' => now(),
        ])->save();
        $officialBusinessTripTicket->delete();

        $audit = app(ActivityAuditService::class);
        $audit->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
            $officialBusinessTripTicket->id,
            'cancelled',
            $officialBusinessTripTicket->reference_no,
            ['status' => 'Cancelled'],
            actorId: $request->user()->id
        );
        $audit->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
            $officialBusinessTripTicket->id,
            'deleted',
            $officialBusinessTripTicket->reference_no,
            ['soft_deleted' => true],
            actorId: $request->user()->id
        );

        return response()->json([
            'message' => 'Trip Ticket cancelled and archived successfully.',
        ]);
    }

    public function storeEvidence(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        ActivityAttachmentService $attachments,
        ActivityAuditService $audit
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('update', $officialBusinessTripTicket);
        $this->ensureTicketMutable($officialBusinessTripTicket);

        $validated = $request->validate([
            'attachment' => [
                'nullable',
                'required_without:external_url',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,mp3,wav,m4a,mp4,mov',
                'max:20480',
            ],
            'external_url' => ['nullable', 'required_without:attachment', 'url', 'max:2000'],
            'evidence_type' => [
                'required',
                'string',
                Rule::in(OfficialBusinessTripTicketCompletionService::EVIDENCE_TYPES),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $metadata = filled($validated['description'] ?? null)
            ? ['description' => $validated['description']]
            : [];

        if ($request->hasFile('attachment')) {
            $file = $validated['attachment'];
            $path = $file->store(
                'activities/official-business-trip-tickets/'.$officialBusinessTripTicket->id,
                'public'
            );
            $attachment = $attachments->createFromStoredFile(
                ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
                $officialBusinessTripTicket->id,
                $path,
                $file->getClientOriginalName(),
                $file->getMimeType(),
                $file->getSize(),
                $validated['evidence_type'],
                $request->user()->id,
                $metadata
            );
        } else {
            $path = parse_url($validated['external_url'], PHP_URL_PATH);
            $name = basename((string) $path) ?: 'Evidence reference';
            $attachment = $attachments->createExternalReference(
                ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
                $officialBusinessTripTicket->id,
                $validated['external_url'],
                $name,
                $validated['evidence_type'],
                $request->user()->id,
                $metadata
            );
        }

        $audit->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
            $officialBusinessTripTicket->id,
            'evidence_uploaded',
            $officialBusinessTripTicket->reference_no,
            [
                'attachment_id' => $attachment->id,
                'evidence_type' => $attachment->category,
                'filename' => $attachment->original_filename,
            ],
            actorId: $request->user()->id
        );

        return response()->json([
            'evidence' => $attachments->serialize($attachment->load('uploadedBy:id,name,email')),
        ], 201);
    }

    public function destroyEvidence(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        ActivityAttachment $attachment,
        ActivityAuditService $audit
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('update', $officialBusinessTripTicket);
        $this->ensureTicketMutable($officialBusinessTripTicket);
        abort_unless(
            $attachment->activity_type === ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET
                && (int) $attachment->activity_id === (int) $officialBusinessTripTicket->id,
            404
        );

        if ($attachment->storage_disk && $attachment->storage_path) {
            Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
        }

        $attachmentId = $attachment->id;
        $attachmentName = $attachment->original_filename ?: $attachment->filename;
        $attachmentCategory = $attachment->category;
        $attachment->delete();
        $audit->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
            $officialBusinessTripTicket->id,
            'evidence_removed',
            $officialBusinessTripTicket->reference_no,
            ['attachment_id' => $attachmentId, 'filename' => $attachmentName, 'evidence_type' => $attachmentCategory],
            actorId: $request->user()->id
        );

        return response()->json(null, 204);
    }

    public function updateFinancialTreatment(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('finance', $officialBusinessTripTicket);

        $validated = $request->validate([
            'billing_treatment' => [
                'required',
                Rule::in(OfficialBusinessTripTicketCompletionService::BILLING_TREATMENTS),
            ],
            'travel_credit_units' => ['nullable', 'decimal:0,2', 'min:0'],
            'travel_credit_value' => ['nullable', 'decimal:0,2', 'min:0'],
            'evidence_required' => ['required', 'boolean'],
            'evidence_requirement_notes' => ['nullable', 'string', 'max:2000'],
            'additional_costs_status' => ['required', Rule::in(['Pending', 'Not Applicable', 'Resolved'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $ticket = $service->updateFinancialTreatment(
            $request->user(),
            $officialBusinessTripTicket,
            $validated
        );

        return response()->json([
            'message' => 'Trip Ticket financial treatment updated successfully.',
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    public function storeCost(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('finance', $officialBusinessTripTicket);

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'decimal:0,2', 'min:0'],
            'incurred_on' => ['nullable', 'date'],
            'billing_treatment' => [
                'required',
                Rule::in(OfficialBusinessTripTicketCompletionService::BILLING_TREATMENTS),
            ],
            'evidence_reference' => ['nullable', 'string', 'max:2000'],
        ]);

        $cost = $service->addCost($request->user(), $officialBusinessTripTicket, $validated);

        return response()->json(['cost' => $this->formatCost($cost)], 201);
    }

    public function destroyCost(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketCost $cost,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('finance', $officialBusinessTripTicket);

        $service->deleteCost($request->user(), $officialBusinessTripTicket, $cost);

        return response()->json(null, 204);
    }

    public function updateCost(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketCost $cost,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('finance', $officialBusinessTripTicket);

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'decimal:0,2', 'min:0'],
            'incurred_on' => ['nullable', 'date'],
            'billing_treatment' => [
                'required',
                Rule::in(OfficialBusinessTripTicketCompletionService::BILLING_TREATMENTS),
            ],
            'evidence_reference' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'cost' => $this->formatCost($service->updateCost(
                $request->user(),
                $officialBusinessTripTicket,
                $cost,
                $validated
            )),
        ]);
    }

    public function restore(
        Request $request,
        int $id,
        OfficialBusinessTripTicketService $service
    ): JsonResponse {
        $ticket = $service->restoreArchived($request->user(), $id);

        return response()->json([
            'message' => 'Trip Ticket restored successfully.',
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    public function completionChecklist(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketCompletionService $completion
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('view', $officialBusinessTripTicket);
        $checklist = $completion->checklist($officialBusinessTripTicket);

        return response()->json([
            'completion_checklist' => $checklist,
            'missing_requirements' => $completion->missingRequirements($checklist),
            'completion_ready' => $completion->isReady($checklist),
        ]);
    }

    public function complete(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket,
        OfficialBusinessTripTicketCompletionService $completion
    ): JsonResponse {
        $result = $completion->complete($request->user(), $officialBusinessTripTicket);
        if (! $result['completion_ready']) {
            return response()->json([
                'message' => 'Trip Ticket completion requirements are incomplete.',
                'completion_checklist' => $result['completion_checklist'],
                'missing_requirements' => $completion->missingRequirements($result['completion_checklist']),
                'completion_ready' => false,
            ], 422);
        }

        return response()->json([
            'message' => 'Trip Ticket completed successfully.',
            'ticket' => $this->formatTicket($result['ticket']->load([
                'company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence.uploadedBy',
            ])),
            'completion_checklist' => $result['completion_checklist'],
            'missing_requirements' => [],
            'completion_ready' => true,
        ]);
    }

    public function downloadPdf(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket
    ) {
        Gate::forUser($request->user())->authorize('view', $officialBusinessTripTicket);

        $ticket = $officialBusinessTripTicket->load([
            'officialBusinessTrip', 'company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence',
        ]);
        $includeFinance = ! $request->user()->isClient()
            && app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($request->user());

        return Pdf::loadView('human-capital.official-business-trip-ticket-pdf', [
            'ticket' => $ticket,
            'includeFinance' => $includeFinance,
            'verificationUrl' => route('official-business-trip-tickets.verify', $ticket->reference_no),
        ])->setPaper([0, 0, 612, 936], 'portrait')->download($ticket->reference_no.'.pdf');
    }

    public function downloadHistoryPdf(
        Request $request,
        OfficialBusinessTripTicket $officialBusinessTripTicket
    ) {
        Gate::forUser($request->user())->authorize('view', $officialBusinessTripTicket);

        $history = ActivityAuditLog::query()
            ->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET)
            ->where('activity_id', $officialBusinessTripTicket->id)
            ->with('user:id,name,email')
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        $includeFinance = ! $request->user()->isClient()
            && app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($request->user());

        if (! $includeFinance) {
            $history = $history->reject(fn (ActivityAuditLog $event): bool => in_array($event->action, [
                'billing_treatment_updated', 'travel_credit_used', 'travel_credit_reversed',
                'additional_cost_added', 'additional_cost_removed', 'additional_cost_updated',
            ], true))->values();
        }

        return Pdf::loadView('human-capital.official-business-trip-ticket-history-pdf', [
            'ticket' => $officialBusinessTripTicket->load([
                'officialBusinessTrip',
                'company',
                'stops',
                'personnel',
                'evidence.uploadedBy',
                'financialTreatment.travelCreditEntry',
                'costs.recordedBy',
                'travelCreditEntries.recordedBy',
            ]),
            'history' => $history,
            'includeFinance' => $includeFinance,
            'verificationUrl' => route('official-business-trip-tickets.verify', $officialBusinessTripTicket->reference_no),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait')->download($officialBusinessTripTicket->reference_no.'-history.pdf');
    }

    private function formatTicket(OfficialBusinessTripTicket $ticket): array
    {
        $ticket->loadMissing([
            'officialBusinessTrip', 'company', 'stops', 'personnel', 'financialTreatment.travelCreditEntry', 'costs', 'evidence.uploadedBy',
        ]);
        $viewer = request()->user();

        $canUpdate = $viewer
            ? Gate::forUser($viewer)->allows('update', $ticket)
            : false;

        $employeeOptions = $canUpdate
            ? \App\Models\Employee::query()
                ->with('department:id,department_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (\App\Models\Employee $employee): array => [
                    'id' => (int) $employee->id,
                    'name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'position' => $employee->position,
                    'department' => $employee->department?->department_name,
                ])
                ->values()
            : collect();

        $formatted = [
            'id' => $ticket->id,
            'official_business_trip_id' => $ticket->official_business_trip_id,
            'company_id' => $ticket->company_id,
            'reference_no' => $ticket->reference_no,
            'verification_code' => $ticket->reference_no,
            'verification_url' => $ticket->status === 'Completed'
                ? route('official-business-trip-tickets.verify', $ticket->reference_no)
                : null,
            'client_name_snapshot' => $ticket->client_name_snapshot,
            'status' => $ticket->status,
            'trip' => [
                'id' => $ticket->officialBusinessTrip?->id,
                'reference_no' => $ticket->officialBusinessTrip?->ob_reference_no,
                'purpose' => $ticket->officialBusinessTrip?->purpose,
                'destination' => $ticket->officialBusinessTrip?->destination,
                'date_from' => $ticket->officialBusinessTrip?->date_from?->format('Y-m-d'),
                'date_to' => $ticket->officialBusinessTrip?->date_to?->format('Y-m-d'),
                'actual_departure' => $ticket->officialBusinessTrip?->actual_departure?->toIso8601String(),
                'actual_return' => $ticket->officialBusinessTrip?->actual_return?->toIso8601String(),
                'status' => $ticket->officialBusinessTrip?->status,
                'movement_status' => $ticket->officialBusinessTrip?->movement_status ?: (
                    $ticket->officialBusinessTrip?->actual_return
                        ? 'Returned'
                        : ($ticket->officialBusinessTrip?->actual_departure ? 'Departed' : 'Not Started')
                ),
            ],
            'stops' => $ticket->stops->map(fn ($stop): array => [
                'id' => $stop->id,
                'sequence' => $stop->sequence,
                'destination' => $stop->destination,
                'activity' => $stop->activity,
                'planned_at' => $stop->planned_at?->toIso8601String(),
                'actual_arrival_at' => $stop->actual_arrival_at?->toIso8601String(),
                'resolved_at' => $stop->resolved_at?->toIso8601String(),
                'resolution_status' => $stop->resolution_status,
            ])->values(),
            'personnel' => $ticket->personnel->map(fn ($member): array => [
                'id' => $member->id,
                'employee_id' => $member->employee_id,
                'employee_name_snapshot' => $member->employee_name_snapshot,
                'position_snapshot' => $member->position_snapshot,
                'department_snapshot' => $member->department_snapshot,
                'role' => $member->role,
                'participation_status' => $member->participation_status,
            ])->values(),
            'employee_options' => $employeeOptions,
            'financial_treatment' => $ticket->financialTreatment ? [
                'billing_treatment' => $ticket->financialTreatment->billing_treatment,
                'travel_credit_units' => $ticket->financialTreatment->travel_credit_units,
                'travel_credit_value' => $ticket->financialTreatment->travel_credit_value,
                'travel_credit_entry_id' => $ticket->financialTreatment->travel_credit_entry_id,
                'evidence_required' => $ticket->financialTreatment->evidence_required,
                'evidence_requirement_notes' => $ticket->financialTreatment->evidence_requirement_notes,
                'additional_costs_status' => $ticket->financialTreatment->additional_costs_status,
                'notes' => $ticket->financialTreatment->notes,
                'resolved_by_user_id' => $ticket->financialTreatment->resolved_by_user_id,
                'resolved_at' => $ticket->financialTreatment->resolved_at?->toIso8601String(),
            ] : null,
            'costs' => $ticket->costs->map(fn (OfficialBusinessTripTicketCost $cost): array => $this->formatCost($cost))->values(),
            'evidence' => $ticket->evidence
                ->map(fn (ActivityAttachment $attachment): array => app(ActivityAttachmentService::class)->serialize($attachment))
                ->values(),
            'permissions' => [
                'update' => $canUpdate,
                'finance' => $viewer
                    ? Gate::forUser($viewer)->allows('finance', $ticket)
                    : false,
                'delete' => $viewer
                    ? Gate::forUser($viewer)->allows('delete', $ticket)
                    : false,
            ],
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($viewer && ! app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($viewer)) {
            unset($formatted['financial_treatment'], $formatted['costs']);
        } elseif ($viewer && $ticket->company) {
            $formatted['travel_credit_account'] = app(\App\Services\CompanyTravelCreditService::class)
                ->summary($ticket->company);
        }

        if ($viewer?->isClient()) {
            unset($formatted['trip']['purpose'], $formatted['trip']['destination']);
        }

        return $formatted;
    }

    private function formatClientTicket(OfficialBusinessTripTicket $ticket): array
    {
        $formatted = $this->formatTicket($ticket);
        unset($formatted['financial_treatment'], $formatted['costs']);
        unset($formatted['trip']['purpose'], $formatted['trip']['destination']);
        $formatted['evidence'] = collect($formatted['evidence'])->map(fn (array $evidence): array => [
            'id' => $evidence['id'],
            'filename' => $evidence['original_filename'] ?: $evidence['filename'],
            'safe_url' => $evidence['safe_url'],
            'category' => $evidence['category'],
            'uploaded_at' => $evidence['uploaded_at'],
        ])->values();

        return $formatted;
    }

    private function formatCost(OfficialBusinessTripTicketCost $cost): array
    {
        return [
            'id' => $cost->id,
            'official_business_trip_ticket_id' => $cost->official_business_trip_ticket_id,
            'category' => $cost->category,
            'description' => $cost->description,
            'amount' => $cost->amount,
            'incurred_on' => $cost->incurred_on?->format('Y-m-d'),
            'billing_treatment' => $cost->billing_treatment,
            'evidence_reference' => $cost->evidence_reference,
            'recorded_by_user_id' => $cost->recorded_by_user_id,
        ];
    }

    private function ensureTicketMutable(OfficialBusinessTripTicket $ticket): void
    {
        if (in_array(strtolower((string) $ticket->status), ['completed', 'resolved', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Completed, resolved, or cancelled Trip Tickets cannot be changed.',
            ]);
        }
    }
}

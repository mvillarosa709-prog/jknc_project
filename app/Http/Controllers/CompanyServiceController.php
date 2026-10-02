<?php

namespace App\Http\Controllers;

use App\Models\CatalogChangeRequest;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCustomField;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class CompanyServiceController extends Controller
{
    private const SERVICE_AREA_OPTIONS = [
        'Corporate & Regulatory Advisory',
        'Governance & Policy Advisory',
        'People & Talent Solutions',
        'Strategic Situations Advisory',
        'Accounting & Compliance Advisory',
        'Business Strategy & Process Advisory',
        'Learning & Capability Development',
        'Others',
    ];

    private const CATEGORY_OPTIONS = [
        'Professional Fees',
        'Consulting Revenue',
        'Accountancy Revenue',
        'Compliance Revenue',
        'Advisory Revenue',
        'Others',
    ];

    private const FREQUENCY_OPTIONS = [
        'No Frequency / Not Applicable',
        'One-time',
        'Daily',
        'Weekly',
        'Monthly',
        'Quarterly',
        'Annually',
        'Per Transaction',
        'Custom',
    ];

    private const REMINDER_OPTIONS = [
        'Same day',
        '1 day before',
        '3 days before',
        '7 days before',
    ];

    private const REQUIREMENT_CATEGORIES = [
        'SOLE / NATURAL PERSON / INDIVIDUAL',
        'JURIDICAL ENTITY (Corporation / OPC / Partnership / Cooperative)',
        'Other',
    ];

    private const DEFAULT_REQUIREMENT_GROUPS = [
        'individual' => [
            'Valid ID',
            'DTI Registration',
        ],
        'juridical' => [
            'SEC Registration',
            'GIS',
            'Articles of Incorporation',
        ],
        'other' => [
            'Special Permit',
        ],
    ];

    private const ENGAGEMENT_OPTIONS = [
        'Project Engagement',
        'Regular (Retainer)',
        'Hybrid',
    ];

    private const UNIT_OPTIONS = [
        'Hour',
        'Day',
        'Week',
        'Month',
        'Project',
        'Fixed',
    ];

    private const TAX_TYPE_OPTIONS = [
        'Tax Inclusive',
        'Tax Exclusive',
    ];

    private const ASSIGNED_UNIT_OPTIONS = [
        'Operations',
        'Accounting',
        'Tax',
        'Corporate',
        'Legal',
        'HR',
        'Admin',
        'Sales',
    ];

    private const STATUS_OPTIONS = [
        'Pending Approval',
        'Draft',
        'Active',
        'Inactive',
        'Rejected',
        'Archived',
    ];

    public function globalIndex(Request $request): View
    {
        $filters = $this->serviceFilters($request);
        if ($filters['tab'] === 'pending_review') {
            $filters['tab'] = 'all';
        }
        $query = $this->serviceQuery($filters);
        $summary = $this->serviceSummary(
            $this->serviceWithRelations()
                ->whereNull('company_id')
                ->get()
        );
        $services = $query->paginate($filters['per_page'])->withQueryString();

        return view('services.index', $this->viewData($request, $services, null, $filters, $summary));
    }

    public function companyIndex(Request $request, int $company): View
    {
        $companyModel = Company::query()->findOrFail($company);
        $filters = $this->serviceFilters($request);
        $query = $this->serviceQuery($filters, $company);
        $summary = $this->serviceSummary((clone $query)->get());
        $services = $query->paginate($filters['per_page'])->withQueryString();

        return view('company.services', $this->viewData($request, $services, $companyModel, $filters, $summary));
    }

    public function storeGlobal(Request $request): RedirectResponse
    {
        $service = $this->persistService($request);

        return redirect()
            ->route('services.index', ['tab' => 'pending_review'])
            ->with('services_success', "Service {$service->service_name} created successfully.");
    }

    public function storeForCompany(Request $request, int $company): RedirectResponse
    {
        $companyModel = Company::query()->findOrFail($company);
        $service = $this->persistService($request, null, $companyModel->id);

        return redirect()
            ->route('company.services.index', $companyModel->id)
            ->with('services_success', "Service {$service->service_name} created for {$companyModel->company_name}.");
    }

    public function showGlobal(int $service): View
    {
        $serviceModel = $this->serviceWithRelations()->findOrFail($service);

        return view('services.show', [
            'service' => $serviceModel,
            'company' => $serviceModel->company,
            'customFields' => ServiceCustomField::query()->orderBy('sort_order')->orderBy('id')->get(),
            'historyItems' => $this->serviceHistoryItems($serviceModel),
            'backRoute' => route('services.index'),
            'backLabel' => 'Back to Services',
        ]);
    }

    public function showForCompany(int $company, int $service): View
    {
        $companyModel = Company::query()->findOrFail($company);
        $serviceModel = $this->serviceWithRelations()
            ->where('company_id', $companyModel->id)
            ->findOrFail($service);

        return view('company.service-show', [
            'company' => $companyModel,
            'service' => $serviceModel,
            'customFields' => ServiceCustomField::query()->orderBy('sort_order')->orderBy('id')->get(),
            'historyItems' => $this->serviceHistoryItems($serviceModel),
        ]);
    }

    public function updateGlobal(Request $request, int $service): RedirectResponse
    {
        $serviceModel = Service::query()->findOrFail($service);

        if ((string) $serviceModel->status === 'Pending Approval' && blank($serviceModel->approved_at)) {
            $serviceModel = $this->persistService($request, $serviceModel);

            return redirect()
                ->route('services.index', ['tab' => 'pending_review'])
                ->with('services_success', "Pending service {$serviceModel->service_name} updated successfully.");
        }

        $attributes = $this->buildServiceAttributes($request, $serviceModel);
        $this->upsertCatalogChangeRequest(
            record: $serviceModel,
            action: 'update',
            payload: $attributes,
            submittedBy: $request->user()?->id,
        );

        return redirect()
            ->route('services.index', ['tab' => 'pending_review'])
            ->with('services_success', "Service {$serviceModel->service_name} update submitted for admin approval.");
    }

    public function updateForCompany(Request $request, int $company, int $service): RedirectResponse
    {
        $companyModel = Company::query()->findOrFail($company);
        $serviceModel = Service::query()
            ->where('company_id', $companyModel->id)
            ->findOrFail($service);

        $serviceModel = $this->persistService($request, $serviceModel, $companyModel->id);

        return redirect()
            ->route('company.services.index', $companyModel->id)
            ->with('services_success', "Service {$serviceModel->service_name} updated successfully.");
    }

    public function approveGlobal(Request $request, int $service): RedirectResponse
    {
        abort_unless(in_array((string) ($request->user()?->role ?? ''), ['Admin', 'SuperAdmin'], true), 403);

        $serviceModel = Service::query()->findOrFail($service);
        $now = now();
        $userId = $request->user()?->id;

        $serviceModel->fill([
            'status' => 'Active',
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'approved_by' => $userId,
            'approved_at' => $now,
        ]);
        $serviceModel->save();

        return back()
            ->with('services_success', "Service {$serviceModel->service_name} approved and activated.");
    }

    public function rejectGlobal(Request $request, int $service): RedirectResponse
    {
        abort_unless(in_array((string) ($request->user()?->role ?? ''), ['Admin', 'SuperAdmin'], true), 403);

        $serviceModel = Service::query()->findOrFail($service);
        $now = now();
        $userId = $request->user()?->id;

        $serviceModel->fill([
            'status' => 'Rejected',
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $serviceModel->save();

        return back()
            ->with('services_success', "Service {$serviceModel->service_name} rejected.");
    }

    public function destroyGlobal(int $service): RedirectResponse
    {
        $serviceModel = Service::query()->findOrFail($service);

        if ((string) $serviceModel->status === 'Pending Approval' && blank($serviceModel->approved_at)) {
            $serviceModel->delete();

            return redirect()
                ->route('services.index')
                ->with('services_success', 'Pending service removed successfully.');
        }

        $this->upsertCatalogChangeRequest(
            record: $serviceModel,
            action: 'delete',
            payload: null,
            submittedBy: request()->user()?->id,
        );

        return redirect()
            ->route('services.index')
            ->with('services_success', 'Service delete request submitted for admin approval.');
    }

    public function destroyForCompany(int $company, int $service): RedirectResponse
    {
        $serviceModel = Service::query()
            ->where('company_id', $company)
            ->findOrFail($service);

        $serviceModel->delete();

        return redirect()
            ->route('company.services.index', $company)
            ->with('services_success', 'Service removed from this company successfully.');
    }

    public function storeCustomField(Request $request): RedirectResponse
    {
        $allowedTypes = collect($this->fieldTypes())->pluck('value')->all();

        $validated = $request->validate([
            'field_type' => ['required', 'string', Rule::in($allowedTypes)],
            'field_name' => ['required', 'string', 'max:80'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'required' => ['nullable', 'boolean'],
            'lookup_module' => ['nullable', 'string', Rule::in(['deals', 'company', 'contacts', 'products'])],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:100'],
        ]);

        $fieldName = trim((string) $validated['field_name']);
        $fieldType = (string) $validated['field_type'];
        $nameExists = ServiceCustomField::query()
            ->whereRaw('LOWER(field_name) = ?', [Str::lower($fieldName)])
            ->exists();

        if ($nameExists) {
            return back()->withErrors(['field_name' => 'Field name already exists for Services.'])->withInput();
        }

        $options = collect($validated['options'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($fieldType === 'picklist' && count($options) === 0) {
            return back()->withErrors(['options' => 'Picklist fields need at least one option.'])->withInput();
        }

        $keyBase = Str::slug($fieldName, '_');
        if ($keyBase === '') {
            $keyBase = 'custom_field';
        }

        $fieldKey = 'custom_'.$keyBase;
        $suffix = 1;
        while (ServiceCustomField::query()->where('field_key', $fieldKey)->exists()) {
            $suffix++;
            $fieldKey = 'custom_'.$keyBase.'_'.$suffix;
        }

        ServiceCustomField::query()->create([
            'field_name' => $fieldName,
            'field_key' => $fieldKey,
            'field_type' => $fieldType,
            'is_required' => (bool) ($validated['required'] ?? false),
            'options' => $fieldType === 'picklist' ? $options : [],
            'default_value' => $this->normalizedDefaultValue($fieldType, trim((string) ($validated['default_value'] ?? ''))),
            'lookup_module' => $fieldType === 'lookup' ? ($validated['lookup_module'] ?? null) : null,
            'sort_order' => ((int) ServiceCustomField::query()->max('sort_order')) + 1,
        ]);

        return redirect()->back()->with('services_success', 'Service custom field created successfully.');
    }

    private function viewData(Request $request, $services, ?Company $company, array $filters, array $summary): array
    {
        $customFields = ServiceCustomField::query()->orderBy('sort_order')->orderBy('id')->get();
        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $companies = Company::query()->orderBy('company_name')->get(['id', 'company_name', 'owner_name']);
        $serviceAreaOptions = $this->serviceAreaOptions();

        return [
            'services' => $services,
            'company' => $company,
            'companies' => $companies,
            'customFields' => $customFields,
            'fieldTypes' => collect($this->fieldTypes()),
            'lookupModules' => $this->lookupModules(),
            'staffOptions' => $users->pluck('name')->values(),
            'users' => $users,
            'categories' => collect($this->categoryOptions()),
            'statusOptions' => collect(self::STATUS_OPTIONS),
            'frequencyOptions' => collect(self::FREQUENCY_OPTIONS),
            'serviceAreaOptions' => collect($serviceAreaOptions),
            'reminderLeadTimes' => collect(self::REMINDER_OPTIONS),
            'requirementCategories' => collect(self::REQUIREMENT_CATEGORIES),
            'requirementTemplateDefaults' => [
                'individual' => implode("\n", self::DEFAULT_REQUIREMENT_GROUPS['individual']),
                'juridical' => implode("\n", self::DEFAULT_REQUIREMENT_GROUPS['juridical']),
                'other' => implode("\n", self::DEFAULT_REQUIREMENT_GROUPS['other']),
            ],
            'engagementOptions' => collect(self::ENGAGEMENT_OPTIONS),
            'unitOptions' => collect(self::UNIT_OPTIONS),
            'taxTypeOptions' => collect(self::TAX_TYPE_OPTIONS),
            'assignedUnitOptions' => collect(self::ASSIGNED_UNIT_OPTIONS),
            'filters' => $filters,
            'summary' => $summary,
            'pendingChangeRequests' => CatalogChangeRequest::query()
                ->with(['submitter', 'reviewer'])
                ->where('module', 'service')
                ->where('status', 'Pending Approval')
                ->latest('updated_at')
                ->get(),
            'pendingRequestMap' => CatalogChangeRequest::query()
                ->where('module', 'service')
                ->where('status', 'Pending Approval')
                ->get()
                ->keyBy('record_id'),
        ];
    }

    private function serviceQuery(array $filters, ?int $companyId = null)
    {
        $query = $this->serviceWithRelations();

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('service_name', 'like', $term)
                    ->orWhere('service_id', 'like', $term)
                    ->orWhere('category', 'like', $term)
                    ->orWhere('frequency', 'like', $term)
                    ->orWhere('assigned_unit', 'like', $term)
                    ->orWhere('status', 'like', $term)
                    ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('company_name', 'like', $term))
                    ->orWhereHas('creator', fn ($userQuery) => $userQuery->where('name', 'like', $term));
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if ($filters['frequency'] !== 'all') {
            $query->where('frequency', $filters['frequency']);
        }

        if ($filters['assigned_unit'] !== 'all') {
            $query->where('assigned_unit', $filters['assigned_unit']);
        }

        if ($filters['engagement_type'] !== 'all') {
            $query->whereJsonContains('engagement_structure', $filters['engagement_type']);
        }

        if ($filters['service_area'] !== 'all') {
            $query->whereJsonContains('service_area', $filters['service_area']);
        }

        if ($filters['tab'] === 'active') {
            $query->where('status', 'Active');
        } elseif ($filters['tab'] === 'pending_review') {
            $query->where('status', 'Pending Approval');
        } elseif ($filters['tab'] === 'recurring') {
            $query->where('is_recurring', true);
        } elseif ($filters['tab'] === 'rejected') {
            $query->where('status', 'Rejected');
        } elseif ($filters['tab'] === 'due_soon') {
            $query->whereNotNull('deadline')
                ->whereBetween('deadline', [now(), now()->addDays(7)]);
        }

        return $query->latest('updated_at');
    }

    private function serviceWithRelations()
    {
        return Service::query()->with(['company', 'creator', 'reviewer', 'approver']);
    }

    private function serviceHistoryItems(Service $service): array
    {
        $history = [[
            'icon' => 'fa-briefcase',
            'title' => 'Service created',
            'description' => $service->service_name,
            'user_name' => $service->creator?->name ?: $this->resolveServiceActorName($service->created_by),
            'created_at' => optional($service->created_at)->format('M d, Y h:i A'),
        ]];

        if ($service->updated_at && optional($service->updated_at)->ne($service->created_at)) {
            $history[] = [
                'icon' => 'fa-pen',
                'title' => 'Service updated',
                'description' => 'Latest service configuration saved.',
                'user_name' => $service->creator?->name ?: $this->resolveServiceActorName($service->created_by),
                'created_at' => optional($service->updated_at)->format('M d, Y h:i A'),
            ];
        }

        if ($service->reviewed_at) {
            $history[] = [
                'icon' => 'fa-clipboard-check',
                'title' => $service->status === 'Rejected' ? 'Service review rejected' : 'Service reviewed',
                'description' => $service->status === 'Rejected'
                    ? 'The service submission was reviewed and rejected.'
                    : 'The service submission was reviewed.',
                'user_name' => $service->reviewer?->name ?: $this->resolveServiceActorName($service->reviewed_by),
                'created_at' => optional($service->reviewed_at)->format('M d, Y h:i A'),
            ];
        }

        if ($service->approved_at) {
            $history[] = [
                'icon' => 'fa-circle-check',
                'title' => 'Service approved',
                'description' => 'The service was approved and activated.',
                'user_name' => $service->approver?->name ?: $this->resolveServiceActorName($service->approved_by),
                'created_at' => optional($service->approved_at)->format('M d, Y h:i A'),
            ];
        }

        usort($history, function (array $left, array $right): int {
            return strtotime((string) ($right['created_at'] ?? '')) <=> strtotime((string) ($left['created_at'] ?? ''));
        });

        return $history;
    }

    private function resolveServiceActorName(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : 'System';
    }

    private function serviceFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', 'all')),
            'category' => trim((string) $request->query('category', 'all')),
            'frequency' => trim((string) $request->query('frequency', 'all')),
            'engagement_type' => trim((string) $request->query('engagement_type', 'all')),
            'assigned_unit' => trim((string) $request->query('assigned_unit', 'all')),
            'service_area' => trim((string) $request->query('service_area', 'all')),
            'tab' => trim((string) $request->query('tab', 'all')),
            'per_page' => in_array((int) $request->query('per_page', 10), [5, 10, 25, 50], true)
                ? (int) $request->query('per_page', 10)
                : 10,
        ];
    }

    private function serviceSummary(Collection $services): array
    {
        return [
            'total_count' => $services->count(),
            'total_value' => (float) $services->sum(function (Service $service): float {
                if ($service->rate_per_unit) {
                    return (float) $service->rate_per_unit;
                }

                return (float) ($service->price_fee ?? 0);
            }),
            'pending' => $services->where('status', 'Pending Approval')->count()
                + CatalogChangeRequest::query()->where('module', 'service')->where('status', 'Pending Approval')->count(),
            'active' => $services->where('status', 'Active')->count(),
            'rejected' => $services->where('status', 'Rejected')->count()
                + CatalogChangeRequest::query()->where('module', 'service')->where('status', 'Rejected')->count(),
            'recurring' => $services->where('is_recurring', true)->count(),
            'due_soon' => $services->filter(function (Service $service): bool {
                return $service->deadline !== null
                    && $service->deadline->isFuture()
                    && $service->deadline->lte(now()->addDays(7));
            })->count(),
        ];
    }

    private function ensureDefaultGlobalServices(): void
    {
        return;

        try {
            if (! Schema::hasTable('services')) {
                return;
            }

            if (Service::query()->whereNull('company_id')->exists()) {
                return;
            }

            $now = Carbon::now();
            $defaultRequirements = $this->defaultRequirementPayload();

            foreach ($this->defaultServiceCatalog() as $area => $services) {
                foreach ($services as $serviceName) {
                    Service::query()->create([
                        'company_id' => null,
                        'service_id' => $this->generateServiceId(),
                        'service_name' => $serviceName,
                        'service_description' => $serviceName,
                        'service_activity_output' => $serviceName,
                        'service_area' => [$area],
                        'service_area_other' => null,
                        'category' => 'Advisory Revenue',
                        'frequency' => 'One-time',
                        'schedule_rule' => null,
                        'deadline' => null,
                        'reminder_lead_time' => null,
                        'requirements' => $defaultRequirements,
                        'requirement_category' => $this->primaryRequirementCategory($defaultRequirements),
                        'engagement_structure' => ['Project Engagement'],
                        'is_recurring' => false,
                        'unit' => 'Project',
                        'rate_per_unit' => null,
                        'min_units' => null,
                        'max_cap' => null,
                        'price_fee' => 2500,
                        'cost_of_service' => null,
                        'tax_type' => 'Tax Exclusive',
                        'assigned_unit' => 'Operations',
                        'status' => 'Active',
                        'created_by' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'approved_by' => null,
                        'approved_at' => null,
                        'custom_field_values' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        } catch (\Throwable) {
            // Keep the Services page usable even if auto-seeding fails.
        }
    }

    private function ensureGlobalCompanyLinkNullable(): void
    {
        try {
            if (DB::getDriverName() === 'sqlite' || ! Schema::hasTable('services') || ! Schema::hasColumn('services', 'company_id')) {
                return;
            }

            $column = DB::selectOne("SHOW COLUMNS FROM `services` LIKE 'company_id'");
            $isNullable = isset($column->Null) && strtoupper((string) $column->Null) === 'YES';

            if ($isNullable) {
                return;
            }

            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });

            DB::statement('ALTER TABLE `services` MODIFY `company_id` BIGINT UNSIGNED NULL');

            Schema::table('services', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        } catch (\Throwable) {
            // Leave schema untouched if runtime alteration is not available.
        }
    }

    private function persistService(Request $request, ?Service $service = null, ?int $lockedCompanyId = null): Service
    {
        $userId = $request->user()?->id;
        $attributes = $this->buildServiceAttributes($request, $service, $lockedCompanyId);

        if ($service === null) {
            $service = new Service();
            $service->service_id = $this->generateServiceId();
            $service->created_by = $userId;
        }

        $service->fill($attributes);

        $service->save();

        return $service->fresh(['company', 'creator', 'reviewer', 'approver']);
    }

    private function buildServiceAttributes(Request $request, ?Service $service = null, ?int $lockedCompanyId = null): array
    {
        $customFields = ServiceCustomField::query()->orderBy('sort_order')->orderBy('id')->get();
        $validated = $this->validateService($request, $customFields, $lockedCompanyId !== null);
        $companyId = $lockedCompanyId ?? (filled($validated['company_id'] ?? null) ? (int) $validated['company_id'] : null);
        $engagementStructure = collect($validated['engagement_structure'] ?? [])->values()->all();
        $serviceArea = collect($validated['service_area'] ?? [])->values()->all();
        $requirements = $this->normalizeRequirementsByGroups(
            (string) ($validated['requirements_individual'] ?? ''),
            (string) ($validated['requirements_juridical'] ?? ''),
            (string) ($validated['requirements_other'] ?? ''),
            (string) ($validated['requirements'] ?? ''),
            $validated['requirement_category'] ?? null,
        );
        $customFieldValues = $this->normalizeCustomFieldValues($request, $customFields);
        $isRecurring = $this->isRecurring($engagementStructure);

        return [
            'company_id' => $companyId,
            'service_name' => $validated['service_name'],
            'service_description' => $validated['service_description'],
            'service_activity_output' => $validated['service_activity_output'],
            'service_area' => $serviceArea,
            'service_area_other' => $validated['service_area_other'] ?? null,
            'category' => ($validated['category'] ?? null) === 'Others'
                ? ($validated['category_other'] ?? null)
                : ($validated['category'] ?? null),
            'frequency' => $validated['frequency'] ?? null,
            'schedule_rule' => $validated['schedule_rule'] ?? null,
            'deadline' => $validated['deadline'] ?? null,
            'reminder_lead_time' => $validated['reminder_lead_time'] ?? null,
            'requirements' => $requirements,
            'requirement_category' => $this->primaryRequirementCategory($requirements),
            'engagement_structure' => $engagementStructure,
            'is_recurring' => $isRecurring,
            'unit' => $validated['unit'],
            'rate_per_unit' => $this->nullableDecimal($validated['rate_per_unit'] ?? null),
            'min_units' => $validated['min_units'] ?? null,
            'max_cap' => $this->nullableDecimal($validated['max_cap'] ?? null),
            'price_fee' => $this->nullableDecimal($validated['price_fee'] ?? null),
            'cost_of_service' => $this->nullableDecimal($validated['cost_of_service'] ?? null),
            'tax_type' => $validated['tax_type'] ?? 'Tax Exclusive',
            'assigned_unit' => $validated['assigned_unit'] ?? null,
            'status' => 'Pending Approval',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'custom_field_values' => $customFieldValues,
        ];
    }

    private function upsertCatalogChangeRequest(Service $record, string $action, ?array $payload, ?int $submittedBy): void
    {
        CatalogChangeRequest::query()->updateOrCreate(
            [
                'module' => 'service',
                'record_id' => (int) $record->id,
                'status' => 'Pending Approval',
            ],
            [
                'record_public_id' => $record->service_id,
                'record_name' => $record->service_name,
                'action' => $action,
                'payload' => $payload,
                'submitted_by' => $submittedBy,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_notes' => null,
            ]
        );
    }

    private function validateService(Request $request, Collection $customFields, bool $companyLocked): array
    {
        $serviceAreaOptions = $this->serviceAreaOptions();

        $rules = [
            'service_name' => ['required', 'string', 'max:255'],
            'service_description' => ['required', 'string'],
            'service_activity_output' => ['required', 'string'],
            'service_area' => ['required', 'array', 'min:1'],
            'service_area.*' => ['string', Rule::in($serviceAreaOptions)],
            'service_area_other' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'category_other' => ['nullable', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', Rule::in(self::FREQUENCY_OPTIONS)],
            'schedule_rule' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'reminder_lead_time' => ['nullable', 'string', Rule::in(self::REMINDER_OPTIONS)],
            'requirement_category' => ['nullable', 'string', Rule::in(self::REQUIREMENT_CATEGORIES)],
            'requirements' => ['nullable', 'string'],
            'requirements_individual' => ['nullable', 'string'],
            'requirements_juridical' => ['nullable', 'string'],
            'requirements_other' => ['nullable', 'string'],
            'engagement_structure' => ['required', 'array', 'min:1'],
            'engagement_structure.*' => ['string', Rule::in(self::ENGAGEMENT_OPTIONS)],
            'unit' => ['required', 'string', Rule::in(self::UNIT_OPTIONS)],
            'rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'min_units' => ['nullable', 'integer', 'min:1'],
            'max_cap' => ['nullable', 'numeric', 'min:0'],
            'price_fee' => ['nullable', 'numeric', 'min:0'],
            'cost_of_service' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['required', 'string', Rule::in(self::TAX_TYPE_OPTIONS)],
            'assigned_unit' => ['nullable', 'string', Rule::in(self::ASSIGNED_UNIT_OPTIONS)],
            'status' => ['nullable', 'string', Rule::in(self::STATUS_OPTIONS)],
        ];

        if (! $companyLocked) {
            $companyRule = Schema::hasTable('companies')
                ? ['nullable', 'integer', 'exists:companies,id']
                : ['nullable', 'integer'];
            $rules['company_id'] = $companyRule;
        }

        foreach ($customFields as $field) {
            $key = 'custom_fields.'.$field->field_key;
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];

            switch ($field->field_type) {
                case 'number':
                case 'currency':
                    $fieldRules[] = 'numeric';
                    break;
                case 'checkbox':
                    $fieldRules[] = 'nullable';
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
            }

            if ($field->field_type === 'picklist' && filled($field->options)) {
                $fieldRules[] = Rule::in($field->options);
            }

            $rules[$key] = $fieldRules;
        }

        $validated = $request->validate($rules);

        validator($validated, [])->after(function (Validator $validator) use ($validated) {
            $serviceArea = collect($validated['service_area'] ?? [])->filter()->values();
            $engagementStructure = collect($validated['engagement_structure'] ?? [])->filter()->values();
            $isRecurring = $this->isRecurring($engagementStructure->all());
            $frequency = $validated['frequency'] ?? null;
            $requirements = collect([
                trim((string) ($validated['requirements'] ?? '')),
                trim((string) ($validated['requirements_individual'] ?? '')),
                trim((string) ($validated['requirements_juridical'] ?? '')),
                trim((string) ($validated['requirements_other'] ?? '')),
            ])->filter(fn ($value) => $value !== '');

            if ($serviceArea->contains('Others') && blank($validated['service_area_other'] ?? null)) {
                $validator->errors()->add('service_area_other', 'Other Service Area is required when Others is selected.');
            }

            if (($validated['category'] ?? null) === 'Others' && blank($validated['category_other'] ?? null)) {
                $validator->errors()->add('category_other', 'Other Category is required when Others is selected.');
            }

            if (
                $isRecurring
                && filled($frequency)
                && ! in_array($frequency, ['No Frequency / Not Applicable', 'One-time', 'Per Transaction'], true)
                && blank($validated['schedule_rule'] ?? null)
            ) {
                $validator->errors()->add('schedule_rule', 'Schedule Rule is required for recurring scheduled services.');
            }

            if (blank($validated['rate_per_unit'] ?? null) && blank($validated['price_fee'] ?? null)) {
                $validator->errors()->add('rate_per_unit', 'Either Rate per Unit or Price / Fee is required.');
            }

            if ($requirements->isNotEmpty() && blank($validated['requirement_category'] ?? null) && blank($validated['requirements_individual'] ?? null) && blank($validated['requirements_juridical'] ?? null) && blank($validated['requirements_other'] ?? null)) {
                $validator->errors()->add('requirement_category', 'Requirement Category is required when requirements are provided.');
            }
        })->validate();

        return $validated;
    }

    private function serviceAreaOptions(): array
    {
        $options = collect(self::SERVICE_AREA_OPTIONS);

        try {
            if (Schema::hasTable('services') && Schema::hasColumn('services', 'service_area')) {
                $storedAreas = Service::query()
                    ->whereNotNull('service_area')
                    ->pluck('service_area')
                    ->flatMap(function ($areas): array {
                        if (is_string($areas)) {
                            $decoded = json_decode($areas, true);
                            $areas = is_array($decoded) ? $decoded : [$areas];
                        }

                        return is_array($areas) ? $areas : [];
                    })
                    ->filter(fn ($value): bool => is_string($value) && trim($value) !== '' && trim($value) !== 'Others')
                    ->map(fn ($value): string => trim((string) $value));

                $storedOtherAreas = [];
                if (Schema::hasColumn('services', 'service_area_other')) {
                    $storedOtherAreas = Service::query()
                        ->whereNotNull('service_area_other')
                        ->pluck('service_area_other')
                        ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                        ->map(fn ($value): string => trim((string) $value))
                        ->all();
                }

                $options = $options
                    ->merge($storedAreas)
                    ->merge($storedOtherAreas);
            }
        } catch (\Throwable) {
            // Fall back to default options when service-area discovery fails.
        }

        return $options
            ->unique()
            ->values()
            ->all();
    }

    private function categoryOptions(): array
    {
        $options = collect(self::CATEGORY_OPTIONS);

        try {
            if (Schema::hasTable('services') && Schema::hasColumn('services', 'category')) {
                $storedCategories = Service::query()
                    ->whereNotNull('category')
                    ->pluck('category')
                    ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                    ->map(fn ($value): string => trim((string) $value));

                $options = $options->merge($storedCategories);
            }
        } catch (\Throwable) {
            // Fall back to default options when category discovery fails.
        }

        return $options
            ->unique()
            ->values()
            ->all();
    }

    private function defaultServiceCatalog(): array
    {
        return [
            'Corporate & Regulatory Advisory' => [
                'Business Registration (SEC / DTI / BIR)',
                'Business Permit Processing / Renewal',
                'Regulatory Compliance',
                'Loan Application Assistance',
                'Foreign Business Entry Support',
            ],
            'Accounting & Compliance Advisory' => [
                'Bookkeeping Services',
                'Tax Filing & Compliance (BIR)',
                'AFS Preparation',
                'Audit Support / Coordination',
                'Accounting Services',
            ],
            'Governance & Policy Advisory' => [
                'Corporate Secretary Services',
                'Corporate Officers Services',
                'Policy Development (HR, Finance, Ops)',
                'Board Resolutions & Minutes',
                'Risk & Internal Control Setup',
            ],
            'Business Strategy & Process Advisory' => [
                'Business Consulting / Strategy',
                'Process Improvement / SOP Development',
                'Organizational Structuring',
                'Digital Transformation',
                'Financial Planning & Analysis',
            ],
            'Strategic Situations Advisory' => [
                'Corporate Deadlock Resolution',
                'Crisis Assessment & Stabilization',
                'Business Restructuring Strategy',
                'Stakeholder Negotiation Support',
                'High-Risk / Complex Case Advisory',
            ],
            'People & Talent Solutions' => [
                'Recruitment & Hiring Support',
                'HR Structuring & Organization Design',
                'KPI & Performance Management Systems',
                'HR Documentation & Contracts',
                'Executive / Virtual Assistant Support',
            ],
            'Learning & Capability Development' => [
                'Accounting & Compliance Training',
                'Corporate Governance Workshops',
                'Business & Strategy Training',
                'Client Capability Development Programs',
                'JKNC Academy Courses',
            ],
        ];
    }

    private function normalizeRequirements(?string $category, string $requirementsText): ?array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $requirementsText) ?: [])
            ->map(fn ($line) => trim(Str::replaceFirst('•', '', $line)))
            ->filter()
            ->values()
            ->all();

        if ($category === null && count($lines) === 0) {
            return null;
        }

        return [
            'category' => $category,
            'items' => $lines,
        ];
    }

    private function normalizeRequirementsByGroups(string $individualText, string $juridicalText, string $otherText, string $legacyRequirementsText = '', ?string $legacyCategory = null): ?array
    {
        $groups = [
            'individual' => $this->normalizeRequirementLines($individualText),
            'juridical' => $this->normalizeRequirementLines($juridicalText),
            'other' => $this->normalizeRequirementLines($otherText),
        ];

        $groups = collect($groups)
            ->filter(fn (array $lines): bool => count($lines) > 0)
            ->all();

        if ($groups === [] && filled($legacyRequirementsText) && filled($legacyCategory)) {
            $legacyLines = $this->normalizeRequirementLines($legacyRequirementsText);
            $legacyKey = match ($legacyCategory) {
                'SOLE / NATURAL PERSON / INDIVIDUAL' => 'individual',
                'JURIDICAL ENTITY (Corporation / OPC / Partnership / Cooperative)' => 'juridical',
                default => 'other',
            };

            if ($legacyLines !== []) {
                $groups[$legacyKey] = $legacyLines;
            }
        }

        if ($groups === []) {
            return null;
        }

        return [
            'groups' => $groups,
        ];
    }

    private function defaultRequirementPayload(): array
    {
        return [
            'groups' => self::DEFAULT_REQUIREMENT_GROUPS,
        ];
    }

    private function normalizeRequirementLines(string $requirementsText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $requirementsText) ?: [])
            ->map(fn ($line) => trim(preg_replace('/^[^A-Za-z0-9]+/u', '', (string) $line) ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    private function primaryRequirementCategory(?array $requirements): ?string
    {
        $groups = collect($requirements['groups'] ?? []);

        if (($groups['individual'] ?? []) !== []) {
            return 'SOLE / NATURAL PERSON / INDIVIDUAL';
        }

        if (($groups['juridical'] ?? []) !== []) {
            return 'JURIDICAL ENTITY (Corporation / OPC / Partnership / Cooperative)';
        }

        if (($groups['other'] ?? []) !== []) {
            return 'Other';
        }

        return null;
    }

    private function normalizeCustomFieldValues(Request $request, Collection $customFields): array
    {
        $values = [];

        foreach ($customFields as $field) {
            $value = data_get($request->input('custom_fields', []), $field->field_key);

            if ($field->field_type === 'checkbox') {
                $values[$field->field_key] = $request->boolean('custom_fields.'.$field->field_key) ? '1' : '0';
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                $default = $field->default_value;
                $values[$field->field_key] = $default === null ? '' : $default;
                continue;
            }

            $values[$field->field_key] = $value;
        }

        return $values;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function isRecurring(array $engagementStructure): bool
    {
        return collect($engagementStructure)->contains(fn ($value) => in_array($value, ['Regular (Retainer)', 'Hybrid'], true));
    }

    private function generateServiceId(): string
    {
        do {
            $serviceId = (string) random_int(10000, 99999);
        } while (Service::query()->where('service_id', $serviceId)->exists());

        return $serviceId;
    }

    private function fieldTypes(): array
    {
        return [
            ['value' => 'text', 'label' => 'Single Line Text', 'icon' => 'fa-input-text'],
            ['value' => 'textarea', 'label' => 'Multi Line Text', 'icon' => 'fa-align-left'],
            ['value' => 'number', 'label' => 'Number', 'icon' => 'fa-hashtag'],
            ['value' => 'currency', 'label' => 'Currency', 'icon' => 'fa-money-bill-wave'],
            ['value' => 'picklist', 'label' => 'Picklist', 'icon' => 'fa-caret-square-down'],
            ['value' => 'checkbox', 'label' => 'Checkbox', 'icon' => 'fa-square-check'],
            ['value' => 'date', 'label' => 'Date', 'icon' => 'fa-calendar'],
            ['value' => 'lookup', 'label' => 'Lookup', 'icon' => 'fa-link'],
        ];
    }

    private function lookupModules(): array
    {
        return [
            ['value' => 'deals', 'label' => 'Deals'],
            ['value' => 'company', 'label' => 'Company'],
            ['value' => 'contacts', 'label' => 'Contacts'],
            ['value' => 'products', 'label' => 'Products'],
        ];
    }

    private function normalizedDefaultValue(string $fieldType, string $defaultValue): string
    {
        if ($fieldType === 'checkbox') {
            return in_array(Str::lower($defaultValue), ['1', 'yes', 'true', 'checked'], true) ? '1' : '0';
        }

        return $defaultValue;
    }
}

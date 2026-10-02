<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyBif;
use App\Models\CompanyConsultationNote;
use App\Models\CompanyHistoryEntry;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivitiesReportService;
use App\Services\ActivityAuditService;
use App\Support\ActivityTimelineBuilder;
use App\Support\CompanyHistoryLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $typeFilter = trim((string) $request->query('type', 'All'));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 25, 50], true) ? $perPage : 10;
        $customFields = $this->companyCustomFields($request);

        $allCompanies = $this->companyRecords($request)
            ->map(fn (array $company): array => $this->applyCompanyCustomFieldDefaults($company, $customFields))
            ->sortByDesc('created_at')
            ->values();

        $companyTypes = $allCompanies
            ->pluck('company_type')
            ->filter(fn (?string $type) => filled($type))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($typeFilter !== 'All' && ! in_array($typeFilter, $companyTypes, true)) {
            $typeFilter = 'All';
        }

        $filteredCompanies = $allCompanies
            ->when($search !== '', function ($collection) use ($search) {
                $term = Str::lower($search);

                return $collection->filter(function (array $company) use ($term) {
                    return collect([
                        $company['company_name'] ?? '',
                        $company['phone'] ?? '',
                        $company['mobile_no'] ?? '',
                        $company['tin_no'] ?? '',
                        $company['owner_name'] ?? '',
                        $company['company_type'] ?? '',
                        ...collect($company['custom_fields'] ?? [])->values()->all(),
                    ])->contains(fn (?string $value) => Str::contains(Str::lower((string) $value), $term));
                });
            })
            ->when($typeFilter !== 'All', fn ($collection) => $collection->where('company_type', $typeFilter))
            ->values();

        $currentPage = max((int) $request->query('page', 1), 1);
        $paginatedCompanies = new LengthAwarePaginator(
            $filteredCompanies
                ->slice(($currentPage - 1) * $perPage, $perPage)
                ->map(fn (array $company) => (object) $company)
                ->values(),
            $filteredCompanies->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $typeCounts = $allCompanies
            ->groupBy(fn (array $company) => $company['company_type'] ?: 'Unspecified')
            ->map(fn ($items) => $items->count())
            ->all();

        $companyCreateContacts = collect();

        if (Schema::hasTable('contacts')) {
            $companyCreateContacts = Contact::query()
                ->where('cif_status', 'approved')
                ->with([
                    'primaryCompanies.latestBif',
                    'companies.latestBif',
                ])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get([
                    'id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'name_extension',
                    'position',
                    'email',
                    'phone',
                    'contact_address',
                    'company_name',
                    'company_address',
                    'business_type_organization',
                    'organization_type',
                    'organization_type_other',
                    'cif_no',
                    'tin',
                    'sales_marketing',
                    'consultant_lead',
                    'lead_associate',
                    'referred_by',
                    'cif_status',
                ])
                ->map(function (Contact $contact): Contact {
                    $autofill = $this->buildCompanyAutofillPayload(
                        $contact,
                        $this->loadContactCifData($contact),
                        $this->loadLinkedBifData($contact)
                    );

                    $contact->setAttribute('company_autofill', $autofill);
                    $contact->setAttribute('contact_full_name', trim(collect([
                        $contact->first_name,
                        $contact->last_name,
                    ])->filter()->implode(' ')));

                    return $contact;
                })
                ->values();
        }

        return view('company.company', [
            'companies' => $paginatedCompanies,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'companyTypes' => $companyTypes,
            'perPage' => $perPage,
            'typeCounts' => $typeCounts,
            'customFields' => $customFields,
            'fieldTypes' => collect($this->fieldTypes()),
            'lookupModules' => $this->lookupModules(),
            'companyCreateContacts' => $companyCreateContacts,
            'employeeOptions' => $this->employeeOptions(),
            'roleContactOptions' => $this->roleContactOptions(),
            'hasApprovedCompanyCreateContacts' => Schema::hasTable('contacts')
                ? Contact::query()->where('cif_status', 'approved')->exists()
                : false,
        ]);
    }

    public function searchRoleContacts(Request $request): JsonResponse
    {
        $search = Str::lower(trim((string) $request->query('search', '')));
        $businessName = Str::lower(trim((string) $request->query('business_name', '')));

        $contacts = collect($this->roleContactOptions())
            ->when($businessName !== '', function ($collection) use ($businessName) {
                $matching = $collection->filter(fn (array $contact) => Str::lower(trim((string) ($contact['company_name'] ?? ''))) === $businessName);

                return $matching->isNotEmpty() ? $matching : $collection;
            })
            ->when($search !== '', function ($collection) use ($search) {
                return $collection->filter(function (array $contact) use ($search): bool {
                    return Str::contains(Str::lower(collect([
                        $contact['label'] ?? '',
                        $contact['company_name'] ?? '',
                        $contact['position'] ?? '',
                        $contact['email'] ?? '',
                        $contact['phone'] ?? '',
                    ])->filter()->implode(' ')), $search);
                });
            })
            ->take(10)
            ->values();

        return response()->json(['contacts' => $contacts]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCompany($request);
        $isAdminAutoApprover = $this->isAdminAutoApprover($request->user());
        $reviewerName = $request->user()?->name ?? 'System User';
        $contact = Contact::query()->findOrFail((int) $validated['contact_id']);

        if (strtolower((string) $contact->cif_status) !== 'approved') {
            return redirect()
                ->back()
                ->withErrors(['contact_id' => 'CIF must be approved before creating a company.'])
                ->withInput();
        }

        $cifData = $this->loadContactCifData($contact);
        $autofill = $this->buildCompanyAutofillPayload($contact, $cifData, $this->loadLinkedBifData($contact));
        $normalizedValidated = array_merge($validated, array_filter([
            'business_organization' => ($validated['business_organization'] ?? null) ?: ($autofill['business_organization'] ?? null),
            'business_organization_other' => ($validated['business_organization_other'] ?? null) ?: ($autofill['business_organization_other'] ?? null),
            'office_type' => ($validated['office_type'] ?? null) ?: ($autofill['office_type'] ?? null),
            'office_type_other' => ($validated['office_type_other'] ?? null) ?: ($autofill['office_type_other'] ?? null),
            'business_phone' => ($validated['business_phone'] ?? null) ?: ($autofill['business_phone'] ?? null),
            'mobile_no' => ($validated['mobile_no'] ?? null) ?: ($autofill['mobile_no'] ?? null),
            'business_address' => ($validated['business_address'] ?? null) ?: ($autofill['business_address'] ?? null),
            'authorized_contact_person_name' => ($validated['authorized_contact_person_name'] ?? null) ?: ($autofill['authorized_contact_person_name'] ?? null),
            'authorized_contact_person_email' => ($validated['authorized_contact_person_email'] ?? null) ?: ($autofill['authorized_contact_person_email'] ?? null),
            'authorized_contact_person_phone' => ($validated['authorized_contact_person_phone'] ?? null) ?: ($autofill['authorized_contact_person_phone'] ?? null),
            'authorized_contact_person_position' => ($validated['authorized_contact_person_position'] ?? null) ?: ($autofill['authorized_contact_person_position'] ?? null),
            'tin_no' => ($validated['tin_no'] ?? null) ?: ($autofill['tin_no'] ?? null),
            'zip_code' => ($validated['zip_code'] ?? null) ?: ($autofill['zip_code'] ?? null),
            'nationality_status' => ($validated['nationality_status'] ?? null) ?: ($autofill['nationality_status'] ?? null),
            'business_name' => ($validated['business_name'] ?? null) ?: ($autofill['business_name'] ?? null),
            'alternative_business_name' => ($validated['alternative_business_name'] ?? null) ?: ($autofill['alternative_business_name'] ?? null),
        ], static fn ($value) => filled($value)));
        if (empty($normalizedValidated['industry_types']) && ! empty($autofill['industry_types'])) {
            $normalizedValidated['industry_types'] = $autofill['industry_types'];
            $normalizedValidated['industry_other_text'] = $autofill['industry_other_text'] ?? null;
        }

        $company = Company::query()->create([
            'company_name' => $normalizedValidated['business_name'],
            'phone' => $normalizedValidated['business_phone'] ?: ($normalizedValidated['mobile_no'] ?: null),
            'email' => $normalizedValidated['authorized_contact_person_email'] ?? ($contact->email ?: null),
            'address' => $normalizedValidated['business_address'] ?: null,
            'primary_contact_id' => $contact->id,
            'owner_name' => $request->user()?->name ?? 'Owner 1',
        ]);

        $bif = CompanyBif::query()->create([
            ...$this->makeCompanyPayload($normalizedValidated),
            'company_id' => $company->id,
            'title' => 'Business Information Form - '.$normalizedValidated['business_name'],
            'status' => $isAdminAutoApprover ? 'pending_approval' : 'draft',
            'submitted_at' => $isAdminAutoApprover ? now() : null,
            'approved_at' => null,
            'approved_by_name' => null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
            'last_submission_source' => $isAdminAutoApprover ? 'manual' : null,
            'last_manual_updated_at' => $isAdminAutoApprover ? now() : null,
            'last_manual_updated_by_name' => $isAdminAutoApprover ? $reviewerName : null,
        ]);

        if (blank($bif->bif_no)) {
            $bif->updateQuietly([
                'bif_no' => 'BIF-' . now()->format('Ymd') . '-' . str_pad((string) $bif->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        // Link the primary contact (and any signatory/UBO contacts with a contact_id)
        // to the company via the pivot table, matching the behaviour in update().
        if (Schema::hasTable('company_contact')) {
            $contactIdsToLink = collect([$contact->id]);

            foreach (array_merge($validated['authorized_signatories'] ?? [], $validated['ubos'] ?? []) as $row) {
                $rowContactId = (int) ($row['contact_id'] ?? 0);
                if ($rowContactId > 0) {
                    $contactIdsToLink->push($rowContactId);
                }
            }

            $company->contacts()->syncWithoutDetaching($contactIdsToLink->unique()->all());
        }

        return redirect()
            ->route('company.kyc', ['company' => $company->id, 'tab' => 'business-client-information'])
            ->with('bif_success', $isAdminAutoApprover
                ? 'Business Information Form created and sent for approval. You can approve or reject it now.'
                : 'Business Information Form saved as draft. Complete the requirements and submit for verification.');
    }

    public function update(Request $request, int $company): RedirectResponse
    {
        $validated = $this->validateCompany($request);
        $customFields = $this->companyCustomFields($request);
        $isAdminAutoApprover = $this->isAdminAutoApprover($request->user());

        if (Schema::hasTable('companies')) {
            $companyRecord = Company::query()->with('latestBif')->findOrFail($company);
            $payload = $this->makeCompanyPayload($validated);
            $reviewerName = $request->user()?->name ?? 'System User';

            $companyRecord->update(array_filter([
                'company_name' => $validated['business_name'] ?? $companyRecord->company_name,
                'email' => $validated['authorized_contact_person_email'] ?? $companyRecord->email,
                'phone' => $validated['business_phone'] ?: ($validated['mobile_no'] ?: $companyRecord->phone),
                'address' => $validated['business_address'] ?? $companyRecord->address,
                'primary_contact_id' => (int) ($validated['contact_id'] ?? $companyRecord->primary_contact_id),
                'owner_name' => $request->user()?->name ?? $companyRecord->owner_name,
            ], static fn ($value) => $value !== null && $value !== ''));

            $bif = $companyRecord->latestBif;
            if ($bif) {
                $bifUpdatePayload = [
                    ...$payload,
                    'title' => 'Business Information Form - '.($validated['business_name'] ?? $companyRecord->company_name),
                    'updated_by' => $request->user()?->id,
                    'last_manual_updated_at' => now(),
                    'last_manual_updated_by_name' => $reviewerName,
                ];

                if ($isAdminAutoApprover) {
                    $bifUpdatePayload = array_merge($bifUpdatePayload, [
                        'status' => 'approved',
                        'submitted_at' => $bif->submitted_at ?? now(),
                        'approved_at' => now(),
                        'approved_by_name' => $reviewerName,
                        'rejected_at' => null,
                        'rejected_by_name' => null,
                        'rejection_reason' => null,
                        'change_request_payload' => null,
                        'change_request_status' => null,
                        'change_request_note' => null,
                        'change_requested_at' => null,
                        'change_requested_by_name' => null,
                        'change_reviewed_at' => null,
                        'change_reviewed_by_name' => null,
                        'change_rejection_reason' => null,
                        'last_submission_source' => 'manual',
                    ]);
                }

                $bif->update($bifUpdatePayload);
            }

            if (Schema::hasTable('company_contact')) {
                $companyRecord->contacts()->syncWithoutDetaching([(int) $validated['contact_id']]);
            }

            CompanyHistoryLogger::log($companyRecord->id, [
                'type' => 'profile',
                'title' => 'Company profile updated',
                'description' => $companyRecord->company_name,
                'extra_label' => 'Status',
                'extra_value' => $bif?->fresh()?->status ?: 'approved',
                'user_name' => $reviewerName,
                'user_initials' => $this->initials($reviewerName),
            ]);

            return redirect()
                ->back()
                ->with('success', $isAdminAutoApprover ? 'Company updated and auto-approved successfully.' : 'Company updated successfully.');
        }

        abort(404);
    }

    public function destroy(Request $request, int $company): RedirectResponse
    {
        abort_unless(
            $this->canDeleteCorporateRecord($request->user()),
            403,
            'You do not have permission to delete company records.'
        );

        if (Schema::hasTable('companies')) {
            $record = Company::query()->find($company);

            if ($record) {
                $this->deleteCompanyRecord($record);

                return redirect()
                    ->route('company.index')
                    ->with('success', 'Company deleted successfully.');
            }
        }

        abort(404);
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        abort_unless(
            $this->canDeleteCorporateRecord($request->user()),
            403,
            'You do not have permission to delete company records.'
        );

        $validated = $request->validate([
            'selected_companies' => ['required', 'array', 'min:1'],
            'selected_companies.*' => ['required', 'integer'],
        ]);

        if (Schema::hasTable('companies')) {
            $companies = Company::query()
                ->whereIn('id', $validated['selected_companies'])
                ->get();

            $deletedCount = 0;

            foreach ($companies as $company) {
                $this->deleteCompanyRecord($company);
                $deletedCount++;
            }

            return redirect()
                ->route('company.index')
                ->with('success', $deletedCount === 1 ? '1 company deleted successfully.' : "{$deletedCount} companies deleted successfully.");
        }

        abort(404);
    }

    public function storeCustomField(Request $request): RedirectResponse
    {
        $allowedTypes = collect($this->fieldTypes())->pluck('value')->all();

        $validated = $request->validate([
            'field_type' => ['required', 'string', 'in:'.implode(',', $allowedTypes)],
            'field_name' => ['required', 'string', 'max:80'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'required' => ['nullable', 'boolean'],
            'lookup_module' => ['nullable', 'string', 'in:'.implode(',', array_column($this->lookupModules(), 'value'))],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:100'],
        ]);

        $fieldName = trim((string) $validated['field_name']);
        $fieldType = (string) $validated['field_type'];
        $customFields = collect($request->session()->get('company.custom_fields', []))->values();

        $nameExists = $customFields->contains(function (array $field) use ($fieldName): bool {
            return Str::lower((string) ($field['name'] ?? '')) === Str::lower($fieldName);
        });

        if ($nameExists) {
            return back()->withErrors(['field_name' => 'Field name already exists for Company.'])->withInput();
        }

        $keyBase = Str::slug($fieldName, '_');
        if ($keyBase === '') {
            $keyBase = 'custom_field';
        }

        $key = 'custom_'.$keyBase;
        $suffix = 1;
        $usedKeys = $customFields->pluck('key')->all();
        while (in_array($key, $usedKeys, true)) {
            $suffix++;
            $key = 'custom_'.$keyBase.'_'.$suffix;
        }

        $options = collect($validated['options'] ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        if ($fieldType === 'picklist' && count($options) === 0) {
            return back()->withErrors(['options' => 'Picklist fields need at least one option.'])->withInput();
        }

        if ($fieldType !== 'lookup') {
            $validated['lookup_module'] = null;
        }

        $defaultValue = trim((string) ($validated['default_value'] ?? ''));
        if ($fieldType === 'checkbox') {
            $defaultValue = in_array(Str::lower($defaultValue), ['1', 'yes', 'true', 'checked'], true) ? '1' : '0';
        }
        if ($fieldType === 'picklist' && $defaultValue !== '' && ! in_array($defaultValue, $options, true)) {
            return back()->withErrors(['default_value' => 'Default value must match one of the options.'])->withInput();
        }

        $customFields->push([
            'id' => 'fld-'.now()->format('YmdHisv').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'type' => $fieldType,
            'name' => $fieldName,
            'key' => $key,
            'required' => (bool) ($validated['required'] ?? false),
            'options' => $fieldType === 'picklist' ? $options : [],
            'lookup_module' => $fieldType === 'lookup' ? (string) ($validated['lookup_module'] ?? '') : null,
            'default_value' => $this->normalizedDefaultValue($fieldType, $defaultValue),
        ]);

        $request->session()->put('company.custom_fields', $customFields->values()->all());
        return redirect()
            ->route('company.index')
            ->with('success', 'Company custom field created successfully.');
    }

    public function show(Request $request, int $company): RedirectResponse
    {
        $this->findCompanyOrAbort($request, $company);

        return redirect()->route('company.kyc', [
            'company' => $company,
            'tab' => 'business-client-information',
        ]);
    }

    public function history(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);

        return view('company.history', [
            'company' => (object) $companyData,
            'historyItems' => $this->companyHistoryItems($companyData),
        ]);
    }

    public function consultationNotes(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);

        return view('company.consultation-notes', [
            'company' => (object) $companyData,
            'consultationNotes' => $this->companyConsultationNotes($companyData),
        ]);
    }

    public function contacts(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $search = trim((string) $request->query('search', ''));
        $companyName = trim((string) ($companyData['company_name'] ?? ''));
        $primaryContactId = (int) ($companyData['primary_contact_id'] ?? 0);

        $contacts = Contact::query()
            ->where(function ($query) use ($company, $companyName, $primaryContactId) {
                $query->whereHas('companies', fn ($relation) => $relation->where('companies.id', $company));

                if ($companyName !== '') {
                    $query->orWhere('company_name', $companyName);
                }

                if ($primaryContactId > 0) {
                    $query->orWhere('id', $primaryContactId);
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'owner_name', 'kyc_status']);

        $roleContacts = collect();

        if (Schema::hasTable('company_bifs')) {
            $latestBif = CompanyBif::query()
                ->where('company_id', $company)
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($latestBif) {
                $roleContacts = $this->companyRoleContacts($latestBif, $companyName, $company, $primaryContactId)
                    ->when($search !== '', function ($collection) use ($search) {
                        $term = Str::lower($search);

                        return $collection->filter(function (array $item) use ($term) {
                            return collect([
                                $item['role_label'] ?? '',
                                $item['full_name'] ?? '',
                                $item['position'] ?? '',
                                $item['email'] ?? '',
                                $item['phone'] ?? '',
                                $item['tin'] ?? '',
                                $item['nationality'] ?? '',
                            ])->contains(fn (?string $value) => Str::contains(Str::lower((string) $value), $term));
                        });
                    })
                    ->values();
            }
        }

        return view('company.contacts', [
            'company' => (object) $companyData,
            'contacts' => $contacts,
            'roleContacts' => $roleContacts,
            'search' => $search,
            'contactsModuleCreateUrl' => route('contacts.index', [
                'open_create' => 1,
                'company_name' => $companyName,
            ]),
        ]);
    }

    public function storeContact(Request $request, int $company): RedirectResponse
    {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $validator = $this->contactValidator($request);

        if ($validator->fails()) {
            return redirect()
                ->to(route('company.contacts', $company) . '#contact-form')
                ->withErrors($validator)
                ->withInput($request->except(['_token']) + [
                    '_contact_form' => 'create',
                ]);
        }

        $validated = $validator->validated();
        $linkedContactId = (int) ($validated['linked_contact_id'] ?? 0);
        $companyRecord = Company::query()->findOrFail($company);
        $linkedContact = $linkedContactId > 0 ? Contact::query()->findOrFail($linkedContactId) : null;

        if ($linkedContact) {
            $companyRecord->contacts()->syncWithoutDetaching([$linkedContact->id]);
        } else {
            $contactRecord = Contact::query()->create([
                'first_name' => trim((string) ($validated['first_name'] ?? '')),
                'last_name' => trim((string) ($validated['last_name'] ?? '')),
                'email' => trim((string) ($validated['email'] ?? '')) ?: null,
                'phone' => trim((string) ($validated['mobile'] ?? $validated['phone'] ?? '')) ?: null,
                'company_name' => $companyRecord->company_name,
                'owner_name' => trim((string) ($validated['owner_name'] ?? '')) ?: ($companyData['owner_name'] ?? null),
            ]);
            $companyRecord->contacts()->syncWithoutDetaching([$contactRecord->id]);
        }

        return redirect()
            ->route('company.contacts', $company)
            ->with('success', 'Contact added successfully.');
    }

    public function updateContact(Request $request, int $company, int $contact): RedirectResponse
    {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $companyRecord = Company::query()->findOrFail($company);
        $existingContact = $companyRecord->contacts()->where('contacts.id', $contact)->firstOrFail();

        $validator = $this->contactValidator($request);

        if ($validator->fails()) {
            return redirect()
                ->to(route('company.contacts', $company) . '#contact-form')
                ->withErrors($validator)
                ->withInput($request->except(['_token', '_method']) + [
                    '_contact_form' => 'edit',
                    'contact_id' => $contact,
                ]);
        }

        $validated = $validator->validated();
        $existingContact->update([
            'first_name' => trim((string) ($validated['first_name'] ?? '')) ?: $existingContact->first_name,
            'last_name' => trim((string) ($validated['last_name'] ?? '')) ?: $existingContact->last_name,
            'email' => trim((string) ($validated['email'] ?? '')) ?: $existingContact->email,
            'phone' => trim((string) ($validated['mobile'] ?? $validated['phone'] ?? '')) ?: $existingContact->phone,
            'owner_name' => trim((string) ($validated['owner_name'] ?? '')) ?: ($existingContact->owner_name ?: ($companyData['owner_name'] ?? null)),
        ]);

        return redirect()
            ->route('company.contacts', $company)
            ->with('success', 'Contact updated successfully.');
    }

    public function storeContactCustomField(Request $request, int $company): RedirectResponse
    {
        return redirect()
            ->route('company.contacts', $company)
            ->withErrors(['label' => 'Company contact custom fields require persistent storage before they can be added.']);
    }

    public function destroyContact(Request $request, int $company, int $contact): RedirectResponse
    {
        $this->findCompanyOrAbort($request, $company);

        $companyRecord = Company::query()->findOrFail($company);
        abort_unless($companyRecord->contacts()->where('contacts.id', $contact)->exists(), 404);
        $companyRecord->contacts()->detach($contact);

        return redirect()
            ->route('company.contacts', $company)
            ->with('success', 'Contact deleted successfully.');
    }

    public function deals(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $deals = collect();

        if (Schema::hasTable('deals')) {
            $deals = Deal::query()
                ->where('company_name', (string) ($companyData['company_name'] ?? ''))
                ->latest('updated_at')
                ->get()
                ->map(function (Deal $deal): array {
                    $owner = $deal->assigned_consultant ?: $deal->lead_consultant ?: $deal->created_by ?: 'Unassigned';

                    return [
                        'name' => $deal->deal_name ?: ($deal->deal_code ?: 'Untitled deal'),
                        'stage' => $deal->stage ?: 'Qualification',
                        'amount' => 'P'.number_format((float) ($deal->total_estimated_engagement_value ?? 0), 2),
                        'expected_close_date' => optional($deal->confirmed_delivery_date ?: $deal->estimated_completion_date ?: $deal->client_preferred_completion_date)->format('M d, Y') ?: '-',
                        'owner' => $owner,
                        'owner_initials' => collect(explode(' ', $owner))
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                            ->implode('') ?: 'NA',
                        'last_updated' => optional($deal->updated_at)->format('M d, Y h:i A') ?: '-',
                    ];
                });
        }

        return view('company.deals', [
            'company' => (object) $companyData,
            'deals' => $deals,
        ]);
    }

    public function activities(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return view('company.activities', [
            'company' => (object) $companyData,
            'activities' => $this->companyActivities($companyData, $user),
            'activitiesIndexUrl' => route('activities'),
        ]);
    }

    public function report(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);

        return view('company.report', [
            'company' => (object) $companyData,
            'reportDatasetUrl' => url('/api/activities/report'),
        ]);
    }

    public function reportPdf(
        Request $request,
        int $company,
        ActivitiesReportService $reports,
        ActivityAuditService $audit
    ) {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'selected_ids' => ['nullable', 'array'],
            'selected_ids.*' => ['string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['string'],
            'status' => ['nullable', 'string', 'max:80'],
        ]);

        $companyParameters = [
            'company_id' => $company,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'activity_types' => $validated['activity_types'] ?? [],
            'status' => $validated['status'] ?? null,
        ];

        $selectionWasSubmitted = array_key_exists('selected_ids', $validated);

        $selectedIds = collect($validated['selected_ids'] ?? [])
            ->map(fn ($id) => strtolower(trim((string) $id)))
            ->unique()
            ->values();

        if ($selectionWasSubmitted) {
            // Build the authorized company boundary once, then derive the
            // selected export from that immutable snapshot. This prevents
            // selected-row export from ever escaping the current company.
            $companyScopeDataset = $reports->build($user, [
                'company_id' => $company,
            ]);

            $selectedRows = collect($companyScopeDataset['rows'])
                ->filter(fn (array $row): bool => $selectedIds->contains(
                    strtolower((string) $row['id'])
                ))
                ->values();

            $withDuration = $selectedRows
                ->filter(fn (array $row): bool => $row['duration_minutes'] !== null);

            $totalActivities = $selectedRows->count();
            $totalDurationMinutes = (int) $withDuration->sum('duration_minutes');

            $selectedDates = $selectedRows
                ->pluck('occurred_at')
                ->filter()
                ->map(fn ($value) => \Carbon\Carbon::parse($value));

            $dataset = $companyScopeDataset;
            $dataset['selection_mode'] = 'selected';
            $dataset['filters'] = [];
            $dataset['rows'] = $selectedRows->all();
            $dataset['report_period'] = [
                'from' => $selectedDates->isEmpty()
                    ? null
                    : $selectedDates->min()->copy()->startOfDay()->toIso8601String(),
                'to' => $selectedDates->isEmpty()
                    ? null
                    : $selectedDates->max()->copy()->endOfDay()->toIso8601String(),
            ];
            $dataset['duration_coverage'] = [
                'activities_with_duration' => $withDuration->count(),
                'total_activities' => $totalActivities,
                'coverage_percentage' => $totalActivities === 0
                    ? 0.0
                    : round(($withDuration->count() / $totalActivities) * 100, 2),
                'total_duration_minutes' => $totalDurationMinutes,
                'total_duration_hours' => round($totalDurationMinutes / 60, 2),
                'by_activity_type' => $withDuration
                    ->groupBy('source_activity_type')
                    ->map(fn ($rows, $type): array => [
                        'activity_type' => $type,
                        'activities_with_duration' => $rows->count(),
                        'duration_minutes' => (int) $rows->sum('duration_minutes'),
                        'duration_hours' => round(((int) $rows->sum('duration_minutes')) / 60, 2),
                    ])
                    ->values()
                    ->all(),
            ];
        } else {
            $dataset = $reports->build($user, $companyParameters);
        }

        $audit->record('REPORT', null, 'export_generated', [], [
            'selection_mode' => $dataset['selection_mode'],
            'row_count' => count($dataset['rows']),
            'parameters' => [
                'company_id' => $company,
                'selected_ids' => $validated['selected_ids'] ?? [],
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'activity_types' => $validated['activity_types'] ?? [],
                'status' => $validated['status'] ?? null,
                'format' => 'pdf',
                'company_report' => true,
            ],
        ]);

        $filename = 'client-activity-report-'
            .Str::slug((string) ($companyData['company_name'] ?? 'company'))
            .'-'
            .now()->format('Ymd-His')
            .'.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('company.report-pdf', [
            'company' => (object) $companyData,
            'dataset' => $dataset,
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function projects(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);

        $projects = collect();

        if (Schema::hasTable('projects')) {
            $projects = Project::query()
                ->where('company_id', $company)
                ->latest()
                ->get()
                ->map(function (Project $project): array {
                    $owner = $project->assigned_project_manager ?: $project->assigned_consultant ?: 'Unassigned';

                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                        'start_date' => optional($project->planned_start_date)->format('M d, Y') ?: '-',
                        'end_date' => optional($project->target_completion_date)->format('M d, Y') ?: '-',
                        'owner' => $owner,
                        'owner_initials' => collect(explode(' ', $owner))
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                            ->implode('') ?: 'NA',
                    ];
                });
        }

        return view('company.projects', [
            'company' => (object) $companyData,
            'projects' => $projects,
        ]);
    }

    public function regular(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);

        $regularEngagements = collect();

        if (Schema::hasTable('projects')) {
            $regularEngagements = Project::query()
                ->where('company_id', $company)
                ->whereRaw('LOWER(COALESCE(engagement_type, "")) LIKE ?', ['%regular%'])
                ->latest('updated_at')
                ->get()
                ->map(function (Project $project): array {
                    $owner = $project->assigned_project_manager ?: $project->assigned_consultant ?: $project->assigned_associate ?: 'Unassigned';
                    $frequency = data_get($project->metadata, 'frequency');

                    return [
                        'id' => $project->id,
                        'name' => $project->name ?: ($project->project_code ?: 'Regular engagement'),
                        'frequency' => $frequency ?: 'Regular',
                        'status' => $project->status ?: 'Open',
                        'start_date' => optional($project->planned_start_date)->format('M d, Y') ?: '-',
                        'next_billing_date' => optional($project->target_completion_date)->format('M d, Y') ?: '-',
                        'owner' => $owner,
                        'owner_initials' => collect(explode(' ', $owner))
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                            ->implode('') ?: 'NA',
                        'show_url' => route('regular.show', $project->id),
                    ];
                });
        }

        return view('company.regular', [
            'company' => (object) $companyData,
            'regularEngagements' => $regularEngagements,
        ]);
    }

    public function products(Request $request, int $company): View
    {
        $companyData = $this->findCompanyOrAbort($request, $company);
        $products = collect();

        if (Schema::hasTable('products') && Schema::hasTable('deals')) {
            $dealIds = Deal::query()
                ->where('company_name', (string) ($companyData['company_name'] ?? ''))
                ->pluck('id');

            if ($dealIds->isNotEmpty()) {
                $products = Product::query()
                    ->whereIn('deal_id', $dealIds)
                    ->latest('updated_at')
                    ->get()
                    ->map(fn (Product $product): array => [
                        'name' => $product->product_name ?: ($product->product_id ?: 'Unnamed product'),
                        'sku' => $product->sku ?: '-',
                        'category' => $product->category ?: 'General',
                        'price' => 'P'.number_format((float) ($product->price ?? 0), 2),
                        'status' => $product->status ?: 'Open',
                    ]);
            }
        }

        return view('company.products', [
            'company' => (object) $companyData,
            'products' => $products,
        ]);
    }

    private function defaultCompanies(): array
    {
        return [
            [
                'id' => 1,
                'company_name' => 'Company 1',
                'company_type' => 'Corporation',
                'email' => 'company1@example.com',
                'phone' => '09012345678',
                'website' => 'https://bigin.example',
                'description' => 'Sample company record',
                'address' => 'Makati City',
                'owner_name' => 'Owner 1',
                'created_at' => '2026-03-01 10:00:00',
            ],
            [
                'id' => 2,
                'company_name' => 'Company 2',
                'company_type' => 'Corporation',
                'email' => 'company2@example.com',
                'phone' => '09000345678',
                'website' => 'https://bigin.example',
                'description' => 'Sample company record',
                'address' => 'Taguig City',
                'owner_name' => 'Owner 2',
                'created_at' => '2026-03-02 10:00:00',
            ],
            [
                'id' => 3,
                'company_name' => 'Company 3',
                'company_type' => 'Corporation',
                'email' => 'company3@example.com',
                'phone' => '09777345678',
                'website' => 'https://bigin.example',
                'description' => 'Sample company record',
                'address' => 'Pasig City',
                'owner_name' => 'Owner 3',
                'created_at' => '2026-03-03 10:00:00',
            ],
        ];
    }

    private function companyHistoryItems(array $companyData): array
    {
        $companyId = (int) $companyData['id'];

        if (Schema::hasTable('company_history_entries')) {
            return CompanyHistoryEntry::query()
                ->where('company_id', $companyId)
                ->latest('occurred_at')
                ->get()
                ->map(fn (CompanyHistoryEntry $entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'title' => $entry->title,
                    'description' => $entry->description,
                    'extraLabel' => $entry->extra_label,
                    'extraValue' => $entry->extra_value,
                    'user' => $entry->user_name,
                    'initials' => $entry->user_initials,
                    'datetime' => optional($entry->occurred_at)->format('M j, Y, h:i A'),
                ])
                ->all();
        }

        return [
            [
                'id' => 1,
                'type' => 'deals',
                'title' => 'Deal linked to company',
                'description' => "Deal 'Cloud Migration Services' linked to company",
                'extraLabel' => 'Deal',
                'extraValue' => 'Cloud Migration Services',
                'user' => 'John Admin',
                'initials' => 'JA',
                'datetime' => 'Mar 10, 2026, 02:30 PM',
            ],
            [
                'id' => 2,
                'type' => 'notes',
                'title' => 'Note added to company',
                'description' => 'Added consultation note regarding implementation scope',
                'extraLabel' => 'Note',
                'extraValue' => 'Implementation Scope Review',
                'user' => 'Maria Santos',
                'initials' => 'MS',
                'datetime' => 'Mar 08, 2026, 11:15 AM',
            ],
            [
                'id' => 3,
                'type' => 'profile',
                'title' => 'Company profile updated',
                'description' => 'Registered address updated on company profile',
                'extraLabel' => 'Address',
                'extraValue' => $companyData['address'] ?? 'Makati City',
                'user' => 'John Admin',
                'initials' => 'JA',
                'datetime' => 'Mar 05, 2026, 04:20 PM',
            ],
            [
                'id' => 4,
                'type' => 'files',
                'title' => 'File uploaded',
                'description' => 'Uploaded signed corporate requirements checklist',
                'extraLabel' => 'File',
                'extraValue' => 'Corporate_Requirements.pdf',
                'user' => 'Maria Santos',
                'initials' => 'MS',
                'datetime' => 'Mar 03, 2026, 09:00 AM',
            ],
        ];
    }

    private function companyConsultationNotes(array $companyData): array
    {
        $companyId = (int) $companyData['id'];

        if (Schema::hasTable('company_consultation_notes')) {
            return CompanyConsultationNote::query()
                ->where('company_id', $companyId)
                ->latest('consultation_date')
                ->get()
                ->map(fn (CompanyConsultationNote $note) => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'consultationDate' => optional($note->consultation_date)->format('Y-m-d'),
                    'author' => $note->author,
                    'summary' => $note->summary,
                    'details' => $note->details,
                    'category' => $note->category,
                    'linkedDeal' => $note->linked_deal,
                    'linkedActivity' => $note->linked_activity,
                    'attachments' => $note->attachments ?? [],
                    'createdAt' => optional($note->created_at)->toIso8601String(),
                    'updatedAt' => optional($note->updated_at)->toIso8601String(),
                ])
                ->all();
        }

        return [
            [
                'id' => 1,
                'title' => 'Initial Consultation - Software Requirements',
                'consultationDate' => '2026-03-02',
                'author' => 'Maria Santos',
                'summary' => 'Discussed enterprise software licensing options, support packages, and implementation timeline.',
                'details' => 'Client requested a phased rollout, training bundle, and SLA-based support package.',
                'category' => 'Software Consultation',
                'linkedDeal' => 'Cloud Migration Services',
                'linkedActivity' => 'Follow-up call',
                'attachments' => [
                    ['id' => 101, 'name' => 'Requirements_Checklist.pdf', 'type' => 'PDF', 'size' => 182344, 'url' => '#'],
                ],
                'createdAt' => '2026-03-02T10:20:00',
                'updatedAt' => '2026-03-02T10:20:00',
            ],
            [
                'id' => 2,
                'title' => 'Follow-up Meeting - Budget Planning',
                'consultationDate' => '2026-02-26',
                'author' => 'John Admin',
                'summary' => 'Reviewed budget allocation for Q2 software implementation and training requirements.',
                'details' => 'Budget approved for initial deployment and milestone-based invoicing.',
                'category' => 'Budget Review',
                'linkedDeal' => 'Security Audit Package',
                'linkedActivity' => 'Quarterly review meeting',
                'attachments' => [
                    ['id' => 201, 'name' => 'Budget_Allocation_Q2.xlsx', 'type' => 'XLSX', 'size' => 120304, 'url' => '#'],
                ],
                'createdAt' => '2026-02-26T14:30:00',
                'updatedAt' => '2026-02-26T14:30:00',
            ],
        ];
    }

    private function companyActivities(array $companyData, User $user): array
    {
        return app(ActivityTimelineBuilder::class)->forCompany($companyData, $user);
    }

    private function findCompanyOrAbort(Request $request, int $company): array
    {
        $companyData = $this->companyRecords($request)->firstWhere('id', $company);

        abort_unless($companyData, 404);

        return $companyData;
    }

    private function loadContactCifData(Contact $contact): array
    {
        $embedded = $contact->getAttribute('cif_data');
        if (is_array($embedded) && $embedded !== []) {
            return $embedded;
        }

        $legacyPaths = [
            'contact-cif-data/'.$contact->id.'.json',
            'contact-cif-data/'.$contact->id.'-data.json',
        ];

        foreach ($legacyPaths as $path) {
            if (Storage::disk('local')->exists($path)) {
                $stored = json_decode((string) Storage::disk('local')->get($path), true);
                if (is_array($stored) && $stored !== []) {
                    return $stored;
                }
            }
        }

        return [];
    }

    private function loadLinkedBifData(Contact $contact): array
    {
        $linkedCompany = null;

        if ($contact->relationLoaded('primaryCompanies')) {
            $linkedCompany = $contact->primaryCompanies->first();
        }

        if (! $linkedCompany && $contact->relationLoaded('companies')) {
            $linkedCompany = $contact->companies->first();
        }

        if (! $linkedCompany) {
            $linkedCompany = $contact->primaryCompanies()->with('latestBif')->first()
                ?: $contact->companies()->with('latestBif')->first();
        }

        return (array) ($linkedCompany?->latestBif?->toArray() ?? []);
    }

    private function firstFilledValue(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function buildCompanyAutofillPayload(Contact $contact, array $cifData, array $bifData = []): array
    {
        $fullName = trim(collect([
            $cifData['first_name'] ?? $contact->first_name,
            $cifData['middle_name'] ?? $contact->middle_name,
            $cifData['last_name'] ?? $contact->last_name,
            $cifData['name_extension'] ?? $contact->name_extension,
        ])->filter()->implode(' '));

        // For Authorized Contact Person and Acknowledgment: First + Last only
        $contactPersonName = trim(collect([
            $cifData['first_name'] ?? $contact->first_name,
            $cifData['last_name'] ?? $contact->last_name,
        ])->filter()->implode(' '));
        $companyName = $this->firstFilledValue(
            $cifData['company_name'] ?? null,
            $cifData['business_name'] ?? null,
            $contact->company_name,
            $bifData['business_name'] ?? null
        );

        $citizenshipType = strtolower((string) ($cifData['citizenship_type'] ?? ''));
        $organizationType = $this->firstFilledValue(
            $this->mapContactOrganizationToBusinessOrganization((string) ($contact->organization_type ?? '')),
            $this->mapContactOrganizationToBusinessOrganization((string) ($contact->business_type_organization ?? '')),
            $bifData['business_organization'] ?? null
        );
        $nationalityStatus = $this->firstFilledValue(
            $bifData['nationality_status'] ?? null,
            $citizenshipType === 'foreigner' ? 'foreign' : null,
            'filipino'
        );
        $businessAddress = $this->firstFilledValue(
            $contact->company_address ?? null,
            $bifData['business_address'] ?? null
        );
        $industryDefaults = $this->industryDefaultsFromNature($this->firstFilledValue(
            $contact->nature_of_business,
            $cifData['nature_of_business'] ?? null,
            $cifData['nature_of_work_business'] ?? null,
            $bifData['industry_other_text'] ?? null
        ));

        return [
            'business_name' => $companyName,
            'business_organization' => $organizationType,
            'business_organization_other' => $organizationType === 'other'
                ? $this->firstFilledValue(
                    $contact->organization_type_other,
                    $contact->business_type_organization,
                    $bifData['business_organization_other'] ?? null
                )
                : null,
            'office_type' => $bifData['office_type'] ?? null,
            'office_type_other' => $bifData['office_type_other'] ?? null,
            'business_phone' => $this->firstFilledValue($cifData['mobile'] ?? null, $bifData['business_phone'] ?? null, $contact->phone),
            'mobile_no' => $this->firstFilledValue($cifData['mobile'] ?? null, $bifData['mobile_no'] ?? null, $contact->phone),
            'business_address' => $businessAddress,
            'industry_types' => $industryDefaults['types'],
            'industry_other_text' => $industryDefaults['other_text'],
            'zip_code' => $this->firstFilledValue($cifData['zip_code'] ?? null, $bifData['zip_code'] ?? null),
            'tin_no' => $this->firstFilledValue($contact->tin, $cifData['tin'] ?? null, $bifData['tin_no'] ?? null),
            'authorized_contact_person_name' => $contactPersonName !== '' ? $contactPersonName : trim($contact->first_name.' '.$contact->last_name),
            'authorized_contact_person_email' => $this->firstFilledValue($cifData['email'] ?? null, $contact->email),
            'authorized_contact_person_phone' => $this->firstFilledValue($cifData['mobile'] ?? null, $contact->phone),
            'authorized_contact_person_position' => $this->firstFilledValue(
                $cifData['sig_position_left'] ?? null,
                $cifData['sig_position_right'] ?? null,
                $contact->position,
                $cifData['nature_of_work_business'] ?? null
            ),
            'consultant_lead' => $this->firstFilledValue($contact->consultant_lead, $bifData['consultant_lead'] ?? null),
            'lead_associate' => $this->firstFilledValue($contact->lead_associate, $bifData['lead_associate'] ?? null),
            'sales_marketing_name' => $this->firstFilledValue($contact->sales_marketing, $cifData['sales_marketing_footer'] ?? null, $bifData['sales_marketing_name'] ?? null),
            'sales_marketing_date_signature' => $bifData['sales_marketing_date_signature'] ?? null,
            'finance_name' => $this->firstFilledValue($cifData['finance_footer'] ?? null, $bifData['finance_name'] ?? null),
            'finance_date_signature' => $bifData['finance_date_signature'] ?? null,
            'referred_by' => $this->firstFilledValue($contact->referred_by, $bifData['referred_by'] ?? null),
            'president_use_only_name' => $this->firstFilledValue($bifData['president_use_only_name'] ?? null),
            'nationality_status' => $nationalityStatus,
            'alternative_business_name' => $bifData['alternative_business_name'] ?? null,
        ];
    }

    private function mapContactOrganizationToBusinessOrganization(string $value): ?string
    {
        $normalized = Str::lower(trim($value));

        return match ($normalized) {
            'sole proprietorship' => 'sole_proprietorship',
            'partnership' => 'partnership',
            'corporation', 'stock' => 'corporation',
            'cooperative' => 'cooperative',
            'ngo', 'non-stock' => 'ngo',
            'others', 'other' => 'other',
            default => null,
        };
    }

    private function industryDefaultsFromNature(?string $value): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return ['types' => [], 'other_text' => null];
        }

        $normalized = Str::of($raw)->lower()->replace(['&', '/', '-'], ' ')->squish()->toString();
        $options = [
            'services' => ['services', 'service'],
            'export_import' => ['export import', 'export', 'import'],
            'education' => ['education', 'school', 'academy'],
            'financial_services' => ['financial services', 'finance', 'financial', 'banking'],
            'transportation' => ['transportation', 'transport', 'logistics'],
            'distribution' => ['distribution', 'distributor'],
            'manufacturing' => ['manufacturing', 'manufacturer'],
            'government' => ['government'],
            'wholesale_retail_trade' => ['wholesale retail trade', 'wholesale', 'retail', 'trade'],
        ];

        foreach ($options as $key => $needles) {
            foreach ($needles as $needle) {
                if ($normalized === $needle || Str::contains($normalized, $needle)) {
                    return ['types' => [$key], 'other_text' => null];
                }
            }
        }

        return ['types' => ['other'], 'other_text' => $raw];
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
            ->implode('');
    }

    private function validateCompany(Request $request): array
    {
        return $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'bif_no' => ['nullable', 'string', 'max:255'],
            'bif_date' => ['required', 'date'],
            'client_type' => ['required', 'string', 'in:new_client,existing_client,change_information'],
            'business_organization' => ['nullable', 'string', 'in:sole_proprietorship,partnership,corporation,cooperative,ngo,other'],
            'business_organization_other' => ['nullable', 'string', 'max:255'],
            'nationality_status' => ['nullable', 'string', 'in:filipino,foreign'],
            'office_type' => ['nullable', 'string', 'in:head_office,branch,regional_headquarter,other'],
            'office_type_other' => ['nullable', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'alternative_business_name' => ['nullable', 'string', 'max:255'],
            'business_address' => ['nullable', 'string'],
            'zip_code' => ['nullable', 'string', 'max:50'],
            'business_phone' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:255'],
            'tin_no' => ['nullable', 'string', 'max:255'],
            'place_of_incorporation' => ['nullable', 'string', 'max:255'],
            'date_of_incorporation' => ['nullable', 'date'],
            'industry_types' => ['nullable', 'array'],
            'industry_types.*' => ['string'],
            'industry_other_text' => ['nullable', 'string', 'max:255'],
            'capital_category' => ['nullable', 'string', 'in:micro,small,medium,large'],
            'employee_male' => ['nullable', 'integer', 'min:0'],
            'employee_female' => ['nullable', 'integer', 'min:0'],
            'employee_pwd' => ['nullable', 'integer', 'min:0'],
            'employee_total' => ['nullable', 'integer', 'min:0'],
            'source_of_funds' => ['nullable', 'array'],
            'source_of_funds.*' => ['string'],
            'source_other_text' => ['nullable', 'string', 'max:255'],
            'president_name' => ['nullable', 'string', 'max:255'],
            'treasurer_name' => ['nullable', 'string', 'max:255'],
            'authorized_signatories' => ['nullable', 'array'],
            'authorized_signatories.*.contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'authorized_signatories.*.full_name' => ['nullable', 'string', 'max:255'],
            'authorized_signatories.*.address' => ['nullable', 'string', 'max:255'],
            'authorized_signatories.*.nationality' => ['nullable', 'string', 'max:255'],
            'authorized_signatories.*.date_of_birth' => ['nullable', 'date'],
            'authorized_signatories.*.tin' => ['nullable', 'string', 'max:255'],
            'authorized_signatories.*.position' => ['nullable', 'string', 'max:255'],
            'ubos' => ['nullable', 'array'],
            'ubos.*.contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'ubos.*.full_name' => ['nullable', 'string', 'max:255'],
            'ubos.*.address' => ['nullable', 'string', 'max:255'],
            'ubos.*.nationality' => ['nullable', 'string', 'max:255'],
            'ubos.*.date_of_birth' => ['nullable', 'date'],
            'ubos.*.tin' => ['nullable', 'string', 'max:255'],
            'ubos.*.position' => ['nullable', 'string', 'max:255'],
            'authorized_contact_person_name' => ['nullable', 'string', 'max:255'],
            'authorized_contact_person_position' => ['nullable', 'string', 'max:255'],
            'authorized_contact_person_email' => ['nullable', 'email', 'max:255'],
            'authorized_contact_person_phone' => ['nullable', 'string', 'max:255'],
            'signature_printed_name' => ['nullable', 'string', 'max:255'],
            'signature_position' => ['nullable', 'string', 'max:255'],
            'review_signature_printed_name' => ['nullable', 'string', 'max:255'],
            'review_signature_position' => ['nullable', 'string', 'max:255'],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'consultant_lead' => ['nullable', 'string', 'max:255'],
            'lead_associate' => ['nullable', 'string', 'max:255'],
            'president_use_only_name' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function makeCompanyPayload(array $validated): array
    {
        $industries = collect($validated['industry_types'] ?? []);
        $sources = collect($validated['source_of_funds'] ?? []);
        $signatories = collect($validated['authorized_signatories'] ?? [])
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => filled($value)))
            ->values()
            ->all();
        $ubos = collect($validated['ubos'] ?? [])
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => filled($value)))
            ->values()
            ->all();

        return [
            'bif_no' => $validated['bif_no'] ?? null,
            'bif_date' => $validated['bif_date'],
            'client_type' => $validated['client_type'],
            'business_organization' => $validated['business_organization'] ?? null,
            'business_organization_other' => ($validated['business_organization'] ?? null) === 'other' ? ($validated['business_organization_other'] ?? null) : null,
            'nationality_status' => $validated['nationality_status'] ?? null,
            'office_type' => $validated['office_type'] ?? null,
            'office_type_other' => ($validated['office_type'] ?? null) === 'other' ? ($validated['office_type_other'] ?? null) : null,
            'business_name' => $validated['business_name'],
            'alternative_business_name' => $validated['alternative_business_name'] ?? null,
            'business_address' => $validated['business_address'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'business_phone' => $validated['business_phone'] ?? null,
            'mobile_no' => $validated['mobile_no'] ?? null,
            'tin_no' => $validated['tin_no'] ?? null,
            'place_of_incorporation' => $validated['place_of_incorporation'] ?? null,
            'date_of_incorporation' => $validated['date_of_incorporation'] ?? null,
            'industry_services' => $industries->contains('services'),
            'industry_export_import' => $industries->contains('export_import'),
            'industry_education' => $industries->contains('education'),
            'industry_financial_services' => $industries->contains('financial_services'),
            'industry_transportation' => $industries->contains('transportation'),
            'industry_distribution' => $industries->contains('distribution'),
            'industry_manufacturing' => $industries->contains('manufacturing'),
            'industry_government' => $industries->contains('government'),
            'industry_wholesale_retail_trade' => $industries->contains('wholesale_retail_trade'),
            'industry_other' => $industries->contains('other'),
            'industry_other_text' => $industries->contains('other') ? ($validated['industry_other_text'] ?? null) : null,
            'capital_micro' => ($validated['capital_category'] ?? null) === 'micro',
            'capital_small' => ($validated['capital_category'] ?? null) === 'small',
            'capital_medium' => ($validated['capital_category'] ?? null) === 'medium',
            'capital_large' => ($validated['capital_category'] ?? null) === 'large',
            'employee_male' => $validated['employee_male'] ?? null,
            'employee_female' => $validated['employee_female'] ?? null,
            'employee_pwd' => $validated['employee_pwd'] ?? null,
            'employee_total' => $validated['employee_total'] ?? (($validated['employee_male'] ?? 0) + ($validated['employee_female'] ?? 0) + ($validated['employee_pwd'] ?? 0)),
            'source_revenue_income' => $sources->contains('revenue_income'),
            'source_investments' => $sources->contains('investments'),
            'source_remittance' => $sources->contains('remittance'),
            'source_other' => $sources->contains('other'),
            'source_other_text' => $sources->contains('other') ? ($validated['source_other_text'] ?? null) : null,
            'source_fees' => $sources->contains('fees'),
            'president_name' => $validated['president_name'] ?? null,
            'treasurer_name' => $validated['treasurer_name'] ?? null,
            'authorized_signatory_name' => $signatories[0]['full_name'] ?? null,
            'authorized_signatory_address' => $signatories[0]['address'] ?? null,
            'authorized_signatory_nationality' => $signatories[0]['nationality'] ?? null,
            'authorized_signatory_date_of_birth' => $signatories[0]['date_of_birth'] ?? null,
            'authorized_signatory_tin' => $signatories[0]['tin'] ?? null,
            'authorized_signatory_position' => $signatories[0]['position'] ?? null,
            'authorized_signatories' => $signatories,
            'ubo_name' => $ubos[0]['full_name'] ?? null,
            'ubo_address' => $ubos[0]['address'] ?? null,
            'ubo_nationality' => $ubos[0]['nationality'] ?? null,
            'ubo_date_of_birth' => $ubos[0]['date_of_birth'] ?? null,
            'ubo_tin' => $ubos[0]['tin'] ?? null,
            'ubo_position' => $ubos[0]['position'] ?? null,
            'ubos' => $ubos,
            'authorized_contact_person_name' => $validated['authorized_contact_person_name'] ?? null,
            'authorized_contact_person_position' => $validated['authorized_contact_person_position'] ?? null,
            'authorized_contact_person_email' => $validated['authorized_contact_person_email'] ?? null,
            'authorized_contact_person_phone' => $validated['authorized_contact_person_phone'] ?? null,
            'signature_printed_name' => $validated['signature_printed_name'] ?? null,
            'signature_position' => $validated['signature_position'] ?? null,
            'review_signature_printed_name' => $validated['review_signature_printed_name'] ?? null,
            'review_signature_position' => $validated['review_signature_position'] ?? null,
            'referred_by' => $validated['referred_by'] ?? null,
            'consultant_lead' => $validated['consultant_lead'] ?? null,
            'lead_associate' => $validated['lead_associate'] ?? null,
            'president_use_only_name' => $validated['president_use_only_name'] ?? null,
        ];
    }

    private function employeeOptions(): array
    {
        if (! Schema::hasTable('employees')) {
            return User::query()
                ->select(['id', 'name', 'email', 'role'])
                ->whereIn('role', ['Admin', 'Employee', 'SuperAdmin'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'employee_code' => null,
                    'email' => $user->email,
                    'position' => null,
                    'department' => null,
                ])
                ->filter(fn (array $employee): bool => filled($employee['name']))
                ->values()
                ->all();
        }

        return Employee::query()
            ->with('department:id,department_name')
            ->select(['id', 'employee_code', 'first_name', 'last_name', 'email', 'position', 'department_id'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'email' => $employee->email,
                'position' => $employee->position,
                'department' => $employee->department?->department_name,
            ])
            ->filter(fn (array $employee): bool => filled($employee['name']))
            ->values()
            ->all();
    }

    private function companyRecords(Request $request)
    {
        if (Schema::hasTable('companies') && Company::query()->count() > 0) {
            return Company::query()
                ->with('latestBif')
                ->get()
                ->map(function (Company $company) {
                    $bif = $company->latestBif;

                    return [
                        'id' => $company->id,
                        'company_name' => $company->company_name,
                        'company_type' => $bif?->business_organization ? str_replace('_', ' ', $bif->business_organization) : null,
                        'bif_no' => $bif?->bif_no,
                        'email' => $company->email,
                        'phone' => $bif?->business_phone ?: $company->phone,
                        'website' => $company->website,
                        'description' => $company->description,
                        'address' => $company->address,
                        'mobile_no' => $bif?->mobile_no,
                        'tin_no' => $bif?->tin_no,
                        'status' => $bif?->status,
                        'owner_name' => $company->owner_name,
                        'created_at' => optional($company->created_at)->toDateTimeString(),
                        'custom_fields' => [],
                    ];
                });
        }

        return collect();
    }

    private function companyCustomFields(Request $request): array
    {
        return collect($request->session()->get('company.custom_fields', []))
            ->values()
            ->all();
    }

    private function companyRoleContacts(CompanyBif $bif, string $companyName, int $companyId = 0, int $primaryContactId = 0): \Illuminate\Support\Collection
    {
        $companyName = trim($companyName);

        $signatories = collect($bif->authorized_signatories ?? [])
            ->filter(fn (array $item) => filled($item['full_name'] ?? null))
            ->map(fn (array $item) => $this->mapRoleContactRecord($item, 'Authorized Signatory', $companyName));

        if ($signatories->isEmpty() && filled($bif->authorized_signatory_name)) {
            $signatories = collect([
                $this->mapRoleContactRecord([
                    'full_name' => $bif->authorized_signatory_name,
                    'address' => $bif->authorized_signatory_address,
                    'nationality' => $bif->authorized_signatory_nationality,
                    'date_of_birth' => optional($bif->authorized_signatory_date_of_birth)?->format('Y-m-d'),
                    'tin' => $bif->authorized_signatory_tin,
                    'position' => $bif->authorized_signatory_position,
                ], 'Authorized Signatory', $companyName),
            ]);
        }

        $ubos = collect($bif->ubos ?? [])
            ->filter(fn (array $item) => filled($item['full_name'] ?? null))
            ->map(fn (array $item) => $this->mapRoleContactRecord($item, 'UBO (20%+ Stockholder)', $companyName));

        if ($ubos->isEmpty() && filled($bif->ubo_name)) {
            $ubos = collect([
                $this->mapRoleContactRecord([
                    'full_name' => $bif->ubo_name,
                    'address' => $bif->ubo_address,
                    'nationality' => $bif->ubo_nationality,
                    'date_of_birth' => optional($bif->ubo_date_of_birth)?->format('Y-m-d'),
                    'tin' => $bif->ubo_tin,
                    'position' => $bif->ubo_position,
                ], 'UBO (20%+ Stockholder)', $companyName),
            ]);
        }

        $authorizedContact = collect();

        if (filled($bif->authorized_contact_person_name)) {
            $authorizedContact = collect([
                $this->mapRoleContactRecord([
                    'full_name' => $bif->authorized_contact_person_name,
                    'position' => $bif->authorized_contact_person_position,
                    'email' => $bif->authorized_contact_person_email,
                    'phone' => $bif->authorized_contact_person_phone,
                ], 'Authorized Contact Person', $companyName),
            ]);
        }

        return $signatories
            ->concat($ubos)
            ->concat($authorizedContact)
            ->map(function (array $item) use ($companyName, $companyId, $primaryContactId) {
                $matchedContact = $this->matchRoleContactToContact($item, $companyName, $companyId, $primaryContactId);

                $item['linked_contact'] = $matchedContact;
                $item['exists_in_contacts'] = $matchedContact !== null;
                $item['add_to_contacts_url'] = $matchedContact
                    ? null
                    : route('contacts.index', array_filter([
                        'open_create' => 1,
                        'company_name' => $companyName !== '' ? $companyName : ($item['company_name'] ?? null),
                        'first_name' => $item['first_name'] ?? null,
                        'last_name' => $item['last_name'] ?? null,
                        'email' => $item['email'] ?? null,
                        'mobile_number' => $item['phone'] ?? null,
                        'position' => $item['position'] ?? null,
                        'contact_address' => $item['address'] ?? null,
                    ], fn ($value) => filled($value)));

                return $item;
            })
            ->unique(fn (array $item) => Str::lower(($item['role_label'] ?? '').'|'.($item['full_name'] ?? '').'|'.($item['email'] ?? '').'|'.($item['phone'] ?? '')))
            ->values();
    }

    private function mapRoleContactRecord(array $item, string $roleLabel, string $companyName): array
    {
        $fullName = trim((string) ($item['full_name'] ?? ''));
        [$firstName, $lastName] = $this->splitFullName($fullName);

        return [
            'role_label' => $roleLabel,
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'position' => $item['position'] ?? null,
            'email' => $item['email'] ?? null,
            'phone' => $item['phone'] ?? null,
            'address' => $item['address'] ?? null,
            'nationality' => $item['nationality'] ?? null,
            'date_of_birth' => $item['date_of_birth'] ?? null,
            'tin' => $item['tin'] ?? null,
        ];
    }

    private function matchRoleContactToContact(array $item, string $companyName, int $companyId = 0, int $primaryContactId = 0): ?Contact
    {
        $fullName = trim((string) ($item['full_name'] ?? ''));
        $email = trim((string) ($item['email'] ?? ''));
        $phone = trim((string) ($item['phone'] ?? ''));

        if ($fullName === '' && $email === '' && $phone === '') {
            return null;
        }

        return Contact::query()
            ->where(function ($query) use ($companyId, $companyName, $primaryContactId) {
                if ($companyId > 0) {
                    $query->whereHas('companies', fn ($relation) => $relation->where('companies.id', $companyId));
                }

                if ($companyName !== '') {
                    $query->orWhere('company_name', $companyName);
                }

                if ($primaryContactId > 0) {
                    $query->orWhere('id', $primaryContactId);
                }
            })
            ->where(function ($query) use ($fullName, $email, $phone) {
                if ($fullName !== '') {
                    [$firstName, $lastName] = $this->splitFullName($fullName);

                    $query->where(function ($nameQuery) use ($fullName, $firstName, $lastName) {
                        $nameQuery->whereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) = ?", [$fullName]);

                        if ($firstName !== '' || $lastName !== '') {
                            $nameQuery->orWhere(function ($exactQuery) use ($firstName, $lastName) {
                                if ($firstName !== '') {
                                    $exactQuery->where('first_name', $firstName);
                                }

                                if ($lastName !== '') {
                                    $exactQuery->where('last_name', $lastName);
                                }
                            });
                        }
                    });
                }

                if ($email !== '') {
                    $method = $fullName !== '' ? 'orWhere' : 'where';
                    $query->{$method}('email', $email);
                }

                if ($phone !== '') {
                    $method = ($fullName !== '' || $email !== '') ? 'orWhere' : 'where';
                    $query->{$method}('phone', $phone);
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function splitFullName(string $fullName): array
    {
        $segments = collect(preg_split('/\s+/', trim($fullName)) ?: [])->filter()->values();
        $firstName = (string) ($segments->first() ?? '');
        $lastName = $segments->count() > 1 ? (string) $segments->slice(1)->implode(' ') : '';

        return [$firstName, $lastName];
    }

    private function roleContactOptions(): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        return Contact::query()
            ->with([
                'primaryCompanies.latestBif',
                'companies.latestBif',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'name_extension',
                'position',
                'email',
                'phone',
                'contact_address',
                'company_name',
                'company_address',
                'tin',
                'date_of_birth',
                'cif_status',
            ])
            ->map(function (Contact $contact): array {
                $cifData = $this->loadContactCifData($contact);
                $bifData = $this->loadLinkedBifData($contact);

                // Build the display label using only first + last name (no name extension)
                // so auto-populated inputs show "Juan Dela Cruz" not "Juan Dela Cruz Jr."
                $label = trim(collect([
                    $cifData['first_name'] ?? $contact->first_name,
                    $cifData['last_name'] ?? $contact->last_name,
                ])->filter()->implode(' '));

                // The full name (including middle name / extension) is only used for the
                // search blob so the contact is still findable by their full legal name.
                $fullName = trim(collect([
                    $cifData['first_name'] ?? $contact->first_name,
                    $cifData['middle_name'] ?? $contact->middle_name,
                    $cifData['last_name'] ?? $contact->last_name,
                    $cifData['name_extension'] ?? $contact->name_extension,
                ])->filter()->implode(' '));

                return [
                    'id' => (int) $contact->id,
                    'label' => $label !== '' ? $label : ($fullName !== '' ? $fullName : 'Contact #'.$contact->id),
                    'search_blob' => Str::lower(collect([
                        $fullName,
                        $this->firstFilledValue(
                            $cifData['company_name'] ?? null,
                            $bifData['business_name'] ?? null,
                            $contact->company_name
                        ),
                        $this->firstFilledValue(
                            $cifData['sig_position_left'] ?? null,
                            $cifData['sig_position_right'] ?? null,
                            $contact->position,
                            $cifData['nature_of_work_business'] ?? null
                        ),
                        $this->firstFilledValue($cifData['email'] ?? null, $contact->email),
                        $this->firstFilledValue($cifData['mobile'] ?? null, $contact->phone),
                        $this->firstFilledValue(
                            $contact->contact_address,
                            $contact->company_address,
                            collect([
                                $cifData['present_address_line1'] ?? null,
                                $cifData['present_address_line2'] ?? null,
                            ])->filter()->implode(', ')
                        ),
                        $this->firstFilledValue($contact->tin, $cifData['tin'] ?? null, $bifData['tin_no'] ?? null),
                    ])->filter()->implode(' ')),
                    'company_name' => $this->firstFilledValue(
                        $cifData['company_name'] ?? null,
                        $bifData['business_name'] ?? null,
                        $contact->company_name
                    ),
                    'position' => $this->firstFilledValue(
                        $cifData['sig_position_left'] ?? null,
                        $cifData['sig_position_right'] ?? null,
                        $contact->position,
                        $cifData['nature_of_work_business'] ?? null
                    ),
                    'email' => $this->firstFilledValue($cifData['email'] ?? null, $contact->email),
                    'phone' => $this->firstFilledValue($cifData['mobile'] ?? null, $contact->phone),
                    'address' => $this->firstFilledValue(
                        $contact->contact_address,
                        $contact->company_address,
                        collect([
                            $cifData['present_address_line1'] ?? null,
                            $cifData['present_address_line2'] ?? null,
                        ])->filter()->implode(', ')
                    ),
                    'nationality' => $this->firstFilledValue(
                        $cifData['citizenship_nationality'] ?? null,
                        $bifData['nationality_status'] ?? null
                    ),
                    'date_of_birth' => $this->firstFilledValue(
                        $cifData['date_of_birth'] ?? null,
                        optional($contact->date_of_birth)->format('Y-m-d')
                    ),
                    'tin' => $this->firstFilledValue($contact->tin, $cifData['tin'] ?? null, $bifData['tin_no'] ?? null),
                    'cif_status' => $contact->cif_status,
                ];
            })
            ->values()
            ->all();
    }

    private function applyCompanyCustomFieldDefaults(array $company, array $customFields): array
    {
        $company['custom_fields'] = $company['custom_fields'] ?? [];

        foreach ($customFields as $field) {
            $key = $field['key'] ?? null;

            if (! $key) {
                continue;
            }

            $company['custom_fields'][$key] = $company['custom_fields'][$key] ?? ($field['default_value'] ?? '');
        }

        return $company;
    }

    private function fieldTypes(): array
    {
        return [
            ['value' => 'picklist', 'label' => 'Picklist', 'icon' => 'fa-list'],
            ['value' => 'text', 'label' => 'Text', 'icon' => 'fa-font'],
            ['value' => 'numerical', 'label' => 'Numerical', 'icon' => 'fa-hashtag'],
            ['value' => 'lookup', 'label' => 'Lookup', 'icon' => 'fa-link'],
            ['value' => 'date', 'label' => 'Date', 'icon' => 'fa-calendar-days'],
            ['value' => 'checkbox', 'label' => 'Checkbox', 'icon' => 'fa-square-check'],
            ['value' => 'email', 'label' => 'Email', 'icon' => 'fa-envelope'],
            ['value' => 'phone', 'label' => 'Phone', 'icon' => 'fa-phone'],
            ['value' => 'url', 'label' => 'URL', 'icon' => 'fa-globe'],
            ['value' => 'user', 'label' => 'User', 'icon' => 'fa-user'],
            ['value' => 'currency', 'label' => 'Currency', 'icon' => 'fa-peso-sign'],
        ];
    }

    private function lookupModules(): array
    {
        return [
            ['value' => 'contacts', 'label' => 'Contacts'],
            ['value' => 'deals', 'label' => 'Deals'],
            ['value' => 'services', 'label' => 'Services'],
        ];
    }

    private function normalizedDefaultValue(string $fieldType, ?string $defaultValue): string
    {
        $value = trim((string) ($defaultValue ?? ''));

        if ($fieldType === 'checkbox') {
            return in_array(Str::lower($value), ['1', 'yes', 'true', 'checked'], true) ? '1' : '0';
        }

        return $value;
    }

    private function contactValidator(Request $request)
    {
        $rules = [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'linked_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ];

        foreach ($this->companyContactCustomFields($request, (int) $request->route('company')) as $field) {
            $rules['custom_fields.' . $field['key']] = ['nullable', 'string', 'max:255'];
        }

        return Validator::make($request->all(), $rules)->after(function ($validator) use ($request) {
            $fullName = trim((string) $request->input('full_name'));
            $firstName = trim((string) $request->input('first_name'));
            $lastName = trim((string) $request->input('last_name'));

            if ($fullName === '' && trim($firstName . ' ' . $lastName) === '') {
                $validator->errors()->add('full_name', 'Please provide a contact name.');
            }
        });
    }

    private function allMockContacts(Request $request): array
    {
        return [];
    }

    private function companyContacts(Request $request, int $companyId): array
    {
        return $this->allMockContacts($request)[$companyId] ?? [];
    }

    private function makeContactPayload(
        array $validated,
        int $id,
        int $companyId,
        string $defaultOwner,
        array $customFields,
        array $existingContact = []
    ): array {
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));
        $providedFullName = trim((string) ($validated['full_name'] ?? ''));
        $fullName = $providedFullName !== '' ? $providedFullName : trim($firstName . ' ' . $lastName);

        return [
            'id' => $id,
            'company_id' => $companyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName !== '' ? $fullName : 'Unnamed Contact',
            'email' => trim((string) ($validated['email'] ?? '')),
            'phone' => trim((string) ($validated['phone'] ?? '')),
            'mobile' => trim((string) ($validated['mobile'] ?? '')),
            'owner_name' => trim((string) ($validated['owner_name'] ?? '')) ?: ($existingContact['owner_name'] ?? $defaultOwner),
            'custom_fields' => $this->normalizeCustomFieldValues($validated['custom_fields'] ?? [], $existingContact['custom_fields'] ?? [], $customFields),
            'created_at' => $existingContact['created_at'] ?? now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    private function allMockContactCustomFields(Request $request): array
    {
        return [];
    }

    private function companyContactCustomFields(Request $request, int $companyId): array
    {
        return $this->allMockContactCustomFields($request)[$companyId] ?? [];
    }

    private function normalizeCustomFieldValues(array $submitted, array $existing, array $customFields): array
    {
        $fields = collect($customFields)
            ->mapWithKeys(function (array $field) use ($submitted, $existing) {
                $value = $submitted[$field['key']] ?? $existing[$field['key']] ?? '';

                return [$field['key'] => trim((string) $value)];
            })
            ->all();

        return $fields;
    }

    private function defaultCompanyContacts(): array
    {
        return [
            1 => [
                [
                    'id' => 1,
                    'company_id' => 1,
                    'first_name' => 'Ted',
                    'last_name' => 'Watson',
                    'full_name' => 'Ted Watson',
                    'email' => 'support@bigin.com',
                    'phone' => '',
                    'mobile' => '609-884-0686',
                    'owner_name' => 'Owner 1',
                    'custom_fields' => [
                        'linkedin' => 'linkedin.com/in/tedwatson',
                    ],
                    'created_at' => '2026-03-01 10:00:00',
                    'updated_at' => '2026-03-01 10:00:00',
                ],
            ],
            2 => [
                [
                    'id' => 1,
                    'company_id' => 2,
                    'first_name' => 'Maria',
                    'last_name' => 'Santos',
                    'full_name' => 'Maria Santos',
                    'email' => 'maria.santos@company2.example.com',
                    'phone' => '02-8123-4500',
                    'mobile' => '0917-123-4567',
                    'owner_name' => 'Owner 2',
                    'custom_fields' => [
                        'branch' => 'BGC',
                    ],
                    'created_at' => '2026-03-02 10:00:00',
                    'updated_at' => '2026-03-02 10:00:00',
                ],
            ],
            3 => [],
        ];
    }

    private function defaultCompanyContactCustomFields(): array
    {
        return [
            1 => [
                ['key' => 'linkedin', 'label' => 'LinkedIn'],
            ],
            2 => [
                ['key' => 'branch', 'label' => 'Branch'],
            ],
            3 => [],
        ];
    }

    private function isAdminAutoApprover(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isSuperAdmin());
    }

    private function canDeleteCorporateRecord(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->hasPermission('approve_corporate')
            || $user->hasPermission('access_admin_dashboard');
    }

    private function deleteCompanyRecord(Company $record): void
    {
        DB::transaction(function () use ($record) {
            $tablesWithCompanyId = [
                'company_bifs',
                'company_cifs',
                'company_history_entries',
                'company_consultation_notes',
                'deals',
                'products',
                'services',
                'sec_coi',
                'sec_aois',
                'bylaws',
                'gis_records',
                'authorized_capital_stocks',
                'subscribed_capitals',
                'paid_up_capitals',
                'directors_officers',
                'stockholders',
            ];

            foreach ($tablesWithCompanyId as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                    DB::table($table)->where('company_id', $record->id)->delete();
                }
            }

            $record->delete();
        });
    }
}

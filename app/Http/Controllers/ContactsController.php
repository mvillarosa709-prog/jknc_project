<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactConsultationNote;
use App\Models\ContactHistoryEntry;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\SpecimenSignature;
use App\Models\User;
use App\Support\ActivityTimelineBuilder;
use App\Support\ContactHistoryLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class ContactsController extends Controller
{
    private const CLIENT_LINK_TTL_DAYS = 14;
    private const DEFAULT_CIF_PRESIDENT_NAME = 'John Kelly Abalde';
    private const BASE_KYC_REQUIREMENT_KEYS = [
        'cif_signed_document',
        'two_valid_ids',
        'specimen_signature_form',
        'tin_proof',
    ];
    private const FOREIGNER_KYC_REQUIREMENT_KEYS = [
        'passport_proof',
        'visa_proof',
        'acr_card_proof',
        'aaep_proof',
    ];
    private const KYC_REQUIREMENT_LABELS = [
        'cif_signed_document' => 'CIF Document (Signed)',
        'two_valid_ids' => 'Two Valid IDs',
        'specimen_signature_form' => 'Specimen Signature Form',
        'tin_proof' => 'TIN Proof',
        'passport_proof' => 'Passport',
        'visa_proof' => 'Visa',
        'acr_card_proof' => 'ACR Card',
        'aaep_proof' => 'AEP',
    ];
    private const KYC_STATUSES = [
        'Verified',
        'Pending Verification',
        'Not Submitted',
        'Rejected',
    ];

    private const SERVICE_INQUIRY_OPTIONS = [
        'Business Registration / Entity Formation',
        'Business Permit (New / Renewal)',
        'Tax Compliance / BIR Filing',
        'Accounting / Bookkeeping',
        'Financial Statements Preparation',
        'Corporate Officers Services',
        'Business Advisory / Consultation',
        'Regulatory Compliance',
        'Other',
    ];

    private const RECOMMENDATION_OPTIONS = [
        'Proceed to Proposal Preparation',
        'Refer to Senior Consultant',
        'Refer to Subject Matter Expert',
        'For Further Study / Assessment',
        'For Due Diligence / Background Check',
        'Schedule Consultation Meeting',
        'Request Additional Information from Client',
        'Not Suitable for Engagement',
        'Others',
    ];

    private const LEAD_SOURCE_OPTIONS = [
        'Facebook',
        'Instagram',
        'LinkedIn',
        'Tiktok',
        'Website',
        'Google Search',
        'Google Ads',
        'Walk-In',
        'Referral-Client',
        'Referral-Partner',
        'Referral-Employee',
        'Email Inquiry',
        'Phone Call',
        'SMS/Viber',
        'WhatsApp',
        'Online Market Place',
        'Event Seminar',
        'Webinar',
        'Trade Show Expo',
        'Flyer / Brochure',
        'Radio Advertisement',
        'Returning Client',
        'Influencer / Content Creator',
        'Television Advertisement',
        'Other',
    ];

    private const LEAD_STAGE_OPTIONS = [
        'Inquiry',
        'Qualification',
        'Consultation',
        'Proposal',
        'Negotiation',
        'Payment',
        'Activation',
        'Closed Lost',
    ];

    private const TABS = [
        'kyc' => 'KYC',
        'history' => 'History',
        'consultation-notes' => 'Consultation Notes',
        'activities' => 'Activities',
        'deals' => 'Deals',
        'company' => 'Company',
        'projects' => 'Projects',
        'regular' => 'Regular',
        'products' => 'Products',
        'services' => 'Services',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $kycFilter = (string) $request->query('kyc', 'All');
        $perPage = 5;

        $query = Contact::query()
            ->with([
                'companies' => fn ($companyQuery) => $companyQuery
                    ->select('companies.id', 'companies.company_name')
                    ->orderBy('companies.company_name'),
            ]);

        if ($search !== '') {
            $this->applyContactSearchFilter($query, $search);
        }

        if (in_array($kycFilter, self::KYC_STATUSES, true)) {
            $query->where('kyc_status', $kycFilter);
        } else {
            $kycFilter = 'All';
        }

        if ($search !== '') {
            $query
                ->orderByRaw(
                    "CASE
                        WHEN LOWER(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) LIKE ? THEN 0
                        WHEN LOWER(first_name) LIKE ? THEN 1
                        WHEN LOWER(last_name) LIKE ? THEN 2
                        WHEN LOWER(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) LIKE ? THEN 3
                        ELSE 4
                    END",
                    [
                        Str::lower($search).'%',
                        Str::lower($search).'%',
                        Str::lower($search).'%',
                        '%'.Str::lower($search).'%',
                    ]
                )
                ->orderBy('first_name')
                ->orderBy('last_name');
        } else {
            $query
                ->orderBy('first_name')
                ->orderBy('last_name');
        }

        $contacts = $query->paginate($perPage)->withQueryString();

        $customFields = collect($request->session()->get('contacts.custom_fields', []))
            ->values()
            ->all();

        $owners = $this->ownerOptions();
        $defaultOwner = $request->user();
        $defaultOwnerId = old('owner_id', $defaultOwner?->id ?? Arr::first($owners)['id']);
        $createdByDisplay = old('created_by', $request->user()?->name ?? 'Admin User');
        $createdAtDisplay = old('created_at_display', now()->format('F j, Y • g:i A'));
        $defaultBusinessDate = old('business_date', old('intake_date', now()->toDateString()));

        return view('contacts.index', [
            'contacts' => $contacts,
            'search' => $search,
            'kycFilter' => $kycFilter,
            'perPage' => $perPage,
            'statusCounts' => [
                'Verified' => Contact::query()->where('kyc_status', 'Verified')->count(),
                'Pending Verification' => Contact::query()->where('kyc_status', 'Pending Verification')->count(),
                'Not Submitted' => Contact::query()->where('kyc_status', 'Not Submitted')->count(),
                'Rejected' => Contact::query()->where('kyc_status', 'Rejected')->count(),
            ],
            'kycStatuses' => self::KYC_STATUSES,
            'owners' => $owners,
            'defaultOwnerId' => (int) $defaultOwnerId,
            'createdByDisplay' => $createdByDisplay,
            'createdAtDisplay' => $createdAtDisplay,
            'defaultBusinessDate' => $defaultBusinessDate,
            'customFields' => $customFields,
            'fieldTypes' => collect($this->fieldTypes()),
            'lookupModules' => $this->lookupModules(),
            'employeeOptions' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $owners = collect($this->ownerOptions())->keyBy('id');
        $isAdminAutoApprover = $this->isAdminAutoApprover($request->user());

        $validated = $request->validate([
            'business_date' => ['nullable', 'date'],
            'customer_type' => ['nullable', 'string', 'in:business,individual'],
            'client_status' => ['nullable', 'string', 'in:new,existing'],
            'salutation' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:20'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_extension' => ['nullable', 'string', 'max:50'],
            'sex' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:150'],
            'business_type_organization' => ['nullable', 'string', 'max:150'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'organization_type_other' => ['nullable', 'string', 'max:150'],
            'nature_of_business' => ['nullable', 'string', 'max:255'],
            'capitalization_amount' => ['nullable'],
            'ownership_structure' => ['nullable', 'string', 'max:255'],
            'previous_year_revenue' => ['nullable'],
            'years_operating' => ['nullable', 'string', 'max:100'],
            'projected_current_year_revenue' => ['nullable'],
            'ownership_flag' => ['nullable', 'string', 'max:150'],
            'foreign_business_nature' => ['nullable', 'string', 'max:2000'],
            'service_inquiry_types' => ['nullable', 'array'],
            'service_inquiry_types.*' => ['string', 'in:'.implode(',', self::SERVICE_INQUIRY_OPTIONS)],
            'service_inquiry_other' => ['nullable', 'string', 'max:255'],
            'inquiry' => ['nullable', 'string', 'max:4000'],
            'jknc_notes' => ['nullable', 'string', 'max:4000'],
            'sales_marketing' => ['nullable', 'string', 'max:4000'],
            'consultant_lead' => ['nullable', 'string', 'max:150'],
            'lead_associate' => ['nullable', 'string', 'max:150'],
            'recommendation_options' => ['nullable', 'array'],
            'recommendation_options.*' => ['string', 'in:'.implode(',', self::RECOMMENDATION_OPTIONS)],
            'recommendation_other' => ['nullable', 'string', 'max:255'],
            'lead_source_channels' => ['nullable', 'array'],
            'lead_source_channels.*' => ['string', 'in:'.implode(',', self::LEAD_SOURCE_OPTIONS)],
            'lead_source_other' => ['nullable', 'string', 'max:255'],
            'referred_by' => ['nullable', 'string', 'max:150'],
            'lead_stage' => ['nullable', 'string', 'in:'.implode(',', self::LEAD_STAGE_OPTIONS)],
            'email' => ['required', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $duplicateContact = $this->findDuplicateContact(
            $validated['first_name'] ?? '',
            $validated['last_name'] ?? '',
            $validated['mobile_number'] ?? null,
        );

        if ($duplicateContact) {
            return redirect()
                ->back()
                ->withErrors([
                    'duplicate_contact' => "Possible duplicate found. Contact {$duplicateContact->first_name} {$duplicateContact->last_name} with contact number {$duplicateContact->phone} already exists. Update the existing record instead of creating a new one.",
                ])
                ->withInput();
        }

        $selectedOwnerId = (int) ($validated['owner_id'] ?? 0);
        $owner = $owners->get($selectedOwnerId) ?? $owners->first();

        $serviceInquiryTypes = collect($validated['service_inquiry_types'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $recommendationOptions = collect($validated['recommendation_options'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $leadSourceChannels = collect($validated['lead_source_channels'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $serviceInquirySummary = implode(', ', array_filter([
            implode(', ', $serviceInquiryTypes),
            $validated['service_inquiry_other'] ?? null,
        ]));
        $recommendationSummary = implode(', ', array_filter([
            implode(', ', $recommendationOptions),
            $validated['recommendation_other'] ?? null,
        ]));
        $leadSourceSummary = implode(', ', array_filter([
            implode(', ', $leadSourceChannels),
            $validated['lead_source_other'] ?? null,
        ]));

        $attributes = [
            'business_date' => $validated['business_date'] ?? now()->toDateString(),
            'intake_date' => $validated['business_date'] ?? now()->toDateString(),
            'customer_type' => $validated['customer_type'] ?? null,
            'client_status' => $validated['client_status'] ?? null,
            'salutation' => $validated['salutation'] ?? null,
            'first_name' => $validated['first_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? '',
            'name_extension' => $validated['name_extension'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'company_address' => $validated['company_address'] ?? null,
            'contact_address' => $validated['contact_address'] ?? null,
            'position' => $validated['position'] ?? null,
            'business_type_organization' => $validated['business_type_organization'] ?? null,
            'organization_type' => $validated['organization_type'] ?? null,
            'organization_type_other' => $validated['organization_type_other'] ?? null,
            'nature_of_business' => $validated['nature_of_business'] ?? null,
            'capitalization_amount' => $this->normalizeMoneyValue($validated['capitalization_amount'] ?? null),
            'ownership_structure' => $validated['ownership_structure'] ?? null,
            'previous_year_revenue' => $this->normalizeMoneyValue($validated['previous_year_revenue'] ?? null),
            'years_operating' => $validated['years_operating'] ?? null,
            'projected_current_year_revenue' => $this->normalizeMoneyValue($validated['projected_current_year_revenue'] ?? null),
            'ownership_flag' => $validated['ownership_flag'] ?? null,
            'foreign_business_nature' => $validated['foreign_business_nature'] ?? null,
            'service_inquiry_types' => $serviceInquiryTypes,
            'service_inquiry_other' => $validated['service_inquiry_other'] ?? null,
            'service_inquiry_type' => $serviceInquirySummary !== '' ? $serviceInquirySummary : null,
            'inquiry' => $validated['inquiry'] ?? null,
            'jknc_notes' => $validated['jknc_notes'] ?? null,
            'sales_marketing' => $validated['sales_marketing'] ?? null,
            'consultant_lead' => $validated['consultant_lead'] ?? null,
            'lead_associate' => $validated['lead_associate'] ?? null,
            'recommendation_options' => $recommendationOptions,
            'recommendation_other' => $validated['recommendation_other'] ?? null,
            'recommendation' => $recommendationSummary !== '' ? $recommendationSummary : ($validated['recommendation'] ?? null),
            'lead_source_channels' => $leadSourceChannels,
            'lead_source_other' => $validated['lead_source_other'] ?? null,
            'lead_source' => $leadSourceSummary !== '' ? $leadSourceSummary : null,
            'referred_by' => $validated['referred_by'] ?? null,
            'lead_stage' => $validated['lead_stage'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['mobile_number'] ?? null,
            'description' => $validated['inquiry'] ?? ($validated['description'] ?? null),
            'kyc_status' => $isAdminAutoApprover ? 'Pending Verification' : 'Not Submitted',
            'cif_status' => $isAdminAutoApprover ? 'pending' : 'draft',
            'cif_submitted_at' => $isAdminAutoApprover ? now() : null,
            'cif_reviewed_at' => null,
            'cif_reviewed_by' => null,
            'cif_rejection_reason' => null,
            'created_by' => $request->user()?->name ?? ($owner['name'] ?? 'Admin User'),
            'owner_name' => $owner['name'],
        ];

        $contact = Contact::query()->create($this->filterPersistableContactAttributes($attributes));

        if (Schema::hasColumn('contacts', 'cif_no') && blank($contact->cif_no)) {
            $contact->updateQuietly([
                'cif_no' => $this->generateCifNumber($contact),
            ]);
        }

        ContactHistoryLogger::log($contact->id, [
            'type' => 'profile',
            'title' => 'Contact created',
            'description' => 'Contact record created in the system',
            'extra_label' => 'Profile',
            'extra_value' => trim($contact->first_name.' '.$contact->last_name) ?: 'New contact record',
            'user_name' => $request->user()?->name ?? ($owner['name'] ?? 'System'),
            'user_initials' => $this->initialsForHistory($request->user()?->name ?? ($owner['name'] ?? 'System')),
        ]);

        if ($isAdminAutoApprover) {
            ContactHistoryLogger::log($contact->id, [
                'type' => 'kyc',
                'title' => 'KYC queued for approval',
                'description' => 'Client Information Form created by admin and routed directly for approval review',
                'extra_label' => 'Status',
                'extra_value' => 'Pending Verification',
                'user_name' => $request->user()?->name ?? ($owner['name'] ?? 'System'),
                'user_initials' => $this->initialsForHistory($request->user()?->name ?? ($owner['name'] ?? 'System')),
            ]);
        }

        if ($request->boolean('return_to_company')) {
            return redirect()
                ->route('company.index', ['open_create_company' => 1, 'company_contact_created' => $contact->id])
                ->with('success', $isAdminAutoApprover ? 'Contact created and sent for approval. You can approve or reject it now.' : 'Contact created successfully.');
        }

        return redirect()
            ->route('contacts.index')
            ->with('success', $isAdminAutoApprover ? 'Contact created and sent for approval. You can approve or reject it now.' : 'Contact created successfully.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        abort_unless(
            $this->canDeleteCorporateRecord($request->user()),
            403,
            'You do not have permission to delete contact records.'
        );

        $validated = $request->validate([
            'selected_contacts' => ['required', 'array', 'min:1'],
            'selected_contacts.*' => ['required', 'integer'],
        ]);

        $deletedCount = Contact::query()
            ->whereIn('id', $validated['selected_contacts'])
            ->delete();

        return redirect()
            ->route('contacts.index')
            ->with('success', $deletedCount === 1 ? '1 contact deleted successfully.' : "{$deletedCount} contacts deleted successfully.");
    }

    public function update(Request $request, string $contact): RedirectResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);
        $owners = collect($this->ownerOptions())->keyBy('id');
        $isAdminAutoApprover = $this->isAdminAutoApprover($request->user());

        $validated = $request->validate([
            'business_date' => ['nullable', 'date'],
            'customer_type' => ['nullable', 'string', 'in:business,individual'],
            'client_status' => ['nullable', 'string', 'in:new,existing'],
            'salutation' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:20'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_extension' => ['nullable', 'string', 'max:50'],
            'sex' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:150'],
            'business_type_organization' => ['nullable', 'string', 'max:150'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'organization_type_other' => ['nullable', 'string', 'max:150'],
            'nature_of_business' => ['nullable', 'string', 'max:255'],
            'capitalization_amount' => ['nullable'],
            'ownership_structure' => ['nullable', 'string', 'max:255'],
            'previous_year_revenue' => ['nullable'],
            'years_operating' => ['nullable', 'string', 'max:100'],
            'projected_current_year_revenue' => ['nullable'],
            'ownership_flag' => ['nullable', 'string', 'max:150'],
            'foreign_business_nature' => ['nullable', 'string', 'max:2000'],
            'service_inquiry_types' => ['nullable', 'array'],
            'service_inquiry_types.*' => ['string', 'in:'.implode(',', self::SERVICE_INQUIRY_OPTIONS)],
            'service_inquiry_other' => ['nullable', 'string', 'max:255'],
            'inquiry' => ['nullable', 'string', 'max:4000'],
            'jknc_notes' => ['nullable', 'string', 'max:4000'],
            'sales_marketing' => ['nullable', 'string', 'max:4000'],
            'consultant_lead' => ['nullable', 'string', 'max:150'],
            'lead_associate' => ['nullable', 'string', 'max:150'],
            'recommendation_options' => ['nullable', 'array'],
            'recommendation_options.*' => ['string', 'in:'.implode(',', self::RECOMMENDATION_OPTIONS)],
            'recommendation_other' => ['nullable', 'string', 'max:255'],
            'lead_source_channels' => ['nullable', 'array'],
            'lead_source_channels.*' => ['string', 'in:'.implode(',', self::LEAD_SOURCE_OPTIONS)],
            'lead_source_other' => ['nullable', 'string', 'max:255'],
            'referred_by' => ['nullable', 'string', 'max:150'],
            'lead_stage' => ['nullable', 'string', 'in:'.implode(',', self::LEAD_STAGE_OPTIONS)],
            'email' => ['required', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $duplicateContact = $this->findDuplicateContact(
            $validated['first_name'] ?? '',
            $validated['last_name'] ?? '',
            $validated['mobile_number'] ?? null,
            $contactModel->id,
        );

        if ($duplicateContact) {
            return redirect()
                ->back()
                ->withErrors([
                    'duplicate_contact' => "This update would duplicate an existing contact: {$duplicateContact->first_name} {$duplicateContact->last_name} ({$duplicateContact->phone}). Please update/link the existing record instead.",
                ])
                ->withInput();
        }

        $selectedOwnerId = (int) ($validated['owner_id'] ?? 0);
        $owner = $owners->get($selectedOwnerId) ?? ['name' => $contactModel->owner_name ?: ($request->user()?->name ?? 'Admin User')];

        $serviceInquiryTypes = collect($validated['service_inquiry_types'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $recommendationOptions = collect($validated['recommendation_options'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $leadSourceChannels = collect($validated['lead_source_channels'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $serviceInquirySummary = implode(', ', array_filter([
            implode(', ', $serviceInquiryTypes),
            $validated['service_inquiry_other'] ?? null,
        ]));
        $recommendationSummary = implode(', ', array_filter([
            implode(', ', $recommendationOptions),
            $validated['recommendation_other'] ?? null,
        ]));
        $leadSourceSummary = implode(', ', array_filter([
            implode(', ', $leadSourceChannels),
            $validated['lead_source_other'] ?? null,
        ]));

        $attributes = [
            'business_date' => $validated['business_date'] ?? $contactModel->business_date,
            'intake_date' => $validated['business_date'] ?? $contactModel->intake_date,
            'customer_type' => $validated['customer_type'] ?? null,
            'client_status' => $validated['client_status'] ?? null,
            'salutation' => $validated['salutation'] ?? null,
            'first_name' => $validated['first_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? '',
            'name_extension' => $validated['name_extension'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'company_address' => $validated['company_address'] ?? null,
            'contact_address' => $validated['contact_address'] ?? null,
            'position' => $validated['position'] ?? null,
            'business_type_organization' => $validated['business_type_organization'] ?? null,
            'organization_type' => $validated['organization_type'] ?? null,
            'organization_type_other' => $validated['organization_type_other'] ?? null,
            'nature_of_business' => $validated['nature_of_business'] ?? null,
            'capitalization_amount' => $this->normalizeMoneyValue($validated['capitalization_amount'] ?? null),
            'ownership_structure' => $validated['ownership_structure'] ?? null,
            'previous_year_revenue' => $this->normalizeMoneyValue($validated['previous_year_revenue'] ?? null),
            'years_operating' => $validated['years_operating'] ?? null,
            'projected_current_year_revenue' => $this->normalizeMoneyValue($validated['projected_current_year_revenue'] ?? null),
            'ownership_flag' => $validated['ownership_flag'] ?? null,
            'foreign_business_nature' => $validated['foreign_business_nature'] ?? null,
            'service_inquiry_types' => $serviceInquiryTypes,
            'service_inquiry_other' => $validated['service_inquiry_other'] ?? null,
            'service_inquiry_type' => $serviceInquirySummary !== '' ? $serviceInquirySummary : null,
            'inquiry' => $validated['inquiry'] ?? null,
            'jknc_notes' => $validated['jknc_notes'] ?? null,
            'sales_marketing' => $validated['sales_marketing'] ?? null,
            'consultant_lead' => $validated['consultant_lead'] ?? null,
            'lead_associate' => $validated['lead_associate'] ?? null,
            'recommendation_options' => $recommendationOptions,
            'recommendation_other' => $validated['recommendation_other'] ?? null,
            'recommendation' => $recommendationSummary !== '' ? $recommendationSummary : ($validated['recommendation'] ?? null),
            'lead_source_channels' => $leadSourceChannels,
            'lead_source_other' => $validated['lead_source_other'] ?? null,
            'lead_source' => $leadSourceSummary !== '' ? $leadSourceSummary : null,
            'referred_by' => $validated['referred_by'] ?? null,
            'lead_stage' => $validated['lead_stage'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['mobile_number'] ?? null,
            'description' => $validated['inquiry'] ?? ($validated['description'] ?? null),
            'owner_name' => $owner['name'],
        ];

        if ($isAdminAutoApprover) {
            $attributes = array_merge($attributes, [
                'kyc_status' => 'Pending Verification',
                'cif_status' => 'pending',
                'cif_submitted_at' => now(),
                'cif_reviewed_at' => null,
                'cif_reviewed_by' => null,
                'cif_rejection_reason' => null,
            ]);
        }

        $contactModel->update($this->filterPersistableContactAttributes($attributes));

        ContactHistoryLogger::log($contactModel->id, [
            'type' => 'profile',
            'title' => 'Contact profile updated',
            'description' => 'Contact details were updated',
            'extra_label' => 'Profile',
            'extra_value' => trim($contactModel->first_name.' '.$contactModel->last_name) ?: 'Contact updated',
            'user_name' => $request->user()?->name ?? ($owner['name'] ?? 'System'),
            'user_initials' => $this->initialsForHistory($request->user()?->name ?? ($owner['name'] ?? 'System')),
        ]);

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'Contact KYC form updated successfully.');
    }

    public function assignOwner(Request $request): RedirectResponse
    {
        $owners = collect($this->ownerOptions())->keyBy('id');

        $validated = $request->validate([
            'selected_contacts' => ['required', 'array', 'min:1'],
            'selected_contacts.*' => ['required', 'integer'],
            'assign_owner_id' => ['required', 'integer'],
        ]);

        $owner = $owners->get((int) $validated['assign_owner_id']);
        if (! $owner) {
            return back()->withErrors(['assign_owner_id' => 'Please select a valid owner.'])->withInput();
        }

        Contact::query()
            ->whereIn('id', $validated['selected_contacts'])
            ->update(['owner_name' => $owner['name']]);

        return redirect()->route('contacts.index')->with('success', 'Owner assigned to selected contacts.');
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
        $customFields = collect($request->session()->get('contacts.custom_fields', []))->values();

        $nameExists = $customFields->contains(function (array $field) use ($fieldName): bool {
            return Str::lower((string) ($field['name'] ?? '')) === Str::lower($fieldName);
        });
        if ($nameExists) {
            return back()->withErrors(['field_name' => 'Field name already exists for Contacts.'])->withInput();
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

        $request->session()->put('contacts.custom_fields', $customFields->values()->all());

        return redirect()->route('contacts.index')->with('success', 'Contact custom field created successfully.');
    }

    public function show(Request $request, string $contact): View
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $tab = strtolower((string) $request->query('tab', 'kyc'));
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'kyc';
        }

        $relatedCompanies = $this->relatedCompaniesForContact($contactModel, trim((string) $request->query('company_search', '')));

        return view('contacts.show', [
            'contact' => $contactModel,
            'tab' => $tab,
            'tabs' => self::TABS,
            'tabData' => $this->tabData($contactModel, $user),
            'cifData' => $this->loadCifData($contactModel),
            'cifDocuments' => $this->loadCifDocuments($contactModel),
            'cifEditMode' => $request->boolean('edit_cif') || $this->hasCifFormErrors($request),
            'specimenSignature' => $specimenSignature = SpecimenSignature::query()->where('contact_id', $contactModel->id)->first(),
            'kycRequirementState' => $this->kycRequirementState($contactModel, $specimenSignature),
            'requiredKycRequirementKeys' => $this->requiredKycRequirementKeysForContact($contactModel),
            'kycActivityLogs' => $this->buildKycActivityLogsWithAudit($contactModel),
            'companySearch' => trim((string) $request->query('company_search', '')),
            'companyTabCompanies' => $relatedCompanies,
            'headerLinkedCompanyName' => $relatedCompanies[0]['company_name'] ?? null,
            'companyCustomFields' => collect($request->session()->get('company.custom_fields', []))->values()->all(),
            'fieldTypes' => collect($this->fieldTypes()),
            'lookupModules' => $this->lookupModules(),
        ]);
    }

    public function unlinkCompany(Request $request, string $contact, string $company): RedirectResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);
        $contactModel->companies()->detach((int) $company);

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'company'])
            ->with('success', 'Company unlinked from contact successfully.');
    }

    public function sendCifClientForm(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
        ]);

        $token = Str::random(64);
        $expiresAt = now()->addDays(self::CLIENT_LINK_TTL_DAYS);
        $recipientEmail = trim((string) $validated['recipient_email']);

        $contactModel->update([
            'cif_access_token' => $token,
            'cif_access_expires_at' => $expiresAt,
            'cif_form_sent_to_email' => $recipientEmail,
            'cif_form_sent_at' => now(),
        ]);

        $cifData = $this->loadCifData($contactModel);
        $cifData['cif_document_issued_on'] = now()->toDateString();
        $cifData['cif_document_issued_by'] = $request->user()?->name ?? 'Admin User';
        $this->saveCifDataToStorage($contactModel, $cifData);

        $clientUrl = route('contacts.cif.client.show', ['token' => $token]);
        $emailHtml = view('emails.contacts.cif-client-link', [
            'contact' => $contactModel,
            'clientName' => trim(($contactModel->first_name ?? '').' '.($contactModel->last_name ?? '')) ?: 'Client',
            'clientUrl' => $clientUrl,
            'expiresAt' => $expiresAt,
        ])->render();

        Mail::html($emailHtml, function ($message) use ($recipientEmail, $contactModel) {
            $message
                ->from(config('mail.from.address'), 'John Kelly & Company')
                ->to($recipientEmail)
                ->subject("Client Information Form for {$contactModel->first_name} {$contactModel->last_name}");
        });

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => "CIF link sent to {$recipientEmail}",
                'recipient_email' => $recipientEmail,
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', "CIF link sent to {$recipientEmail}")
            ->with('contact_client_email', $recipientEmail);
    }

    public function sendSpecimenClientForm(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
        ]);

        $token = Str::random(64);
        $expiresAt = now()->addDays(self::CLIENT_LINK_TTL_DAYS);
        $recipientEmail = trim((string) $validated['recipient_email']);

        $contactModel->update([
            'specimen_access_token' => $token,
            'specimen_access_expires_at' => $expiresAt,
            'specimen_form_sent_to_email' => $recipientEmail,
            'specimen_form_sent_at' => now(),
        ]);

        $clientUrl = route('contacts.specimen.client.show', ['token' => $token]);
        $emailHtml = view('emails.contacts.specimen-client-link', [
            'contact' => $contactModel,
            'clientName' => trim(($contactModel->first_name ?? '').' '.($contactModel->last_name ?? '')) ?: 'Client',
            'clientUrl' => $clientUrl,
            'expiresAt' => $expiresAt,
        ])->render();

        Mail::html($emailHtml, function ($message) use ($recipientEmail, $contactModel) {
            $message
                ->from(config('mail.from.address'), 'John Kelly & Company')
                ->to($recipientEmail)
                ->subject("Specimen Signature Form for {$contactModel->first_name} {$contactModel->last_name}");
        });

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => "Specimen link sent to {$recipientEmail}",
                'recipient_email' => $recipientEmail,
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', "Specimen link sent to {$recipientEmail}")
            ->with('contact_client_email', $recipientEmail);
    }

    public function clientCifForm(Request $request, string $token): View
    {
        $contact = $this->findContactByClientToken('cif', $token);
        $specimenSignature = SpecimenSignature::query()->where('contact_id', $contact->id)->first();

        abort_if($contact->cif_access_expires_at && $contact->cif_access_expires_at->isPast(), 403, 'This CIF link has expired.');

        return view('contacts.cif-client-form', [
            'contact' => $contact,
            'cifData' => $this->loadClientCifData($contact),
            'clientFormAction' => route('contacts.cif.client.submit', ['token' => $token]),
            'clientPreviewUrl' => route('contacts.cif.client.preview', ['token' => $token]),
            'clientDownloadUrl' => route('contacts.cif.client.download', ['token' => $token, 'autoprint' => 1]),
            'kycRequirementState' => $this->kycRequirementState($contact, $specimenSignature),
            'requiredKycRequirementKeys' => $this->requiredKycRequirementKeysForContact($contact),
        ]);
    }

    public function submitClientCifForm(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $contact = $this->findContactByClientToken('cif', $token);

        abort_if($contact->cif_access_expires_at && $contact->cif_access_expires_at->isPast(), 403, 'This CIF link has expired.');

        $request->validate([
            'client_acknowledgment' => ['accepted'],
            'sig_name_left' => ['required', 'string', 'max:150'],
            'sig_position_left' => ['required', 'string', 'max:150'],
        ], [
            'client_acknowledgment.accepted' => 'Please acknowledge the CIF terms before submitting.',
            'sig_name_left.required' => 'Please enter your printed name in the acknowledgment section.',
            'sig_position_left.required' => 'Please enter your position in the acknowledgment section.',
        ]);

        $validated = $this->validatedCifPayload($request);

        $this->saveCifDataToStorage($contact, [
            ...$this->loadClientCifData($contact),
            ...$validated,
            'cif_no' => $contact->cif_no ?: ($validated['cif_no'] ?? ''),
            'client_acknowledgment' => true,
            'client_acknowledged_at' => now()->toDateTimeString(),
        ]);

        // Keep the main contact record in sync with client-submitted CIF edits.
        $this->syncContactFromCifPayload($contact, $validated);
        $this->storeClientSubmittedKycDocuments($request, $contact);

        $this->syncContactKycSnapshot($contact, [
            'cif_no' => $contact->cif_no ?: ($validated['cif_no'] ?? null),
            'tin' => $validated['tin'] ?? null,
        ]);
        $contact->refresh();

        $statusPayload = $this->filterPersistableContactAttributes([
            'kyc_status' => 'Pending Verification',
            'cif_status' => 'pending',
            'cif_submitted_at' => now(),
            'cif_reviewed_at' => null,
            'cif_reviewed_by' => null,
            'cif_rejection_reason' => null,
        ]);

        if (! empty($statusPayload)) {
            $contact->forceFill($statusPayload)->save();
        }

        $cifData = $this->loadCifData($contact);
        $cifData['date_verified'] = '';
        $cifData['verified_by'] = '';
        $cifData['change_request_status'] = '';
        $cifData['change_request_note'] = '';
        $cifData['change_requested_at'] = '';
        $cifData['change_requested_by'] = '';
        $cifData['change_reviewed_at'] = '';
        $cifData['change_reviewed_by'] = '';
        $cifData['change_rejection_reason'] = '';
        $this->saveCifDataToStorage($contact, $cifData);

        ContactHistoryLogger::log($contact->id, [
            'type' => 'kyc',
            'title' => 'KYC submitted for verification',
            'description' => 'Client Information Form submitted from secure client link',
            'extra_label' => 'Status',
            'extra_value' => 'Pending Verification',
            'user_name' => trim((string) $request->input('sig_name_left', '')) ?: 'Client',
            'user_initials' => $this->initialsForHistory(trim((string) $request->input('sig_name_left', '')) ?: 'Client'),
        ]);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'Your Client Information Form has been submitted successfully.',
            ]);
        }

        return redirect()
            ->route('contacts.cif.client.show', ['token' => $token])
            ->with('success', 'Your Client Information Form has been submitted successfully.');
    }

    public function previewClientCif(Request $request, string $token): View
    {
        $contact = $this->findContactByClientToken('cif', $token);

        abort_if($contact->cif_access_expires_at && $contact->cif_access_expires_at->isPast(), 403, 'This CIF link has expired.');

        return view('contacts.cif-preview', [
            'contact' => $contact,
            'cifData' => $this->loadClientCifData($contact),
            'cifDocuments' => $this->loadCifDocuments($contact),
            'downloadMode' => false,
            'autoPrint' => false,
            'backUrl' => route('contacts.cif.client.show', ['token' => $token]),
        ]);
    }

    public function downloadClientCif(Request $request, string $token): View
    {
        $contact = $this->findContactByClientToken('cif', $token);

        abort_if($contact->cif_access_expires_at && $contact->cif_access_expires_at->isPast(), 403, 'This CIF link has expired.');

        return view('contacts.cif-preview', [
            'contact' => $contact,
            'cifData' => $this->loadClientCifData($contact),
            'cifDocuments' => $this->loadCifDocuments($contact),
            'downloadMode' => true,
            'autoPrint' => $request->boolean('autoprint'),
            'backUrl' => route('contacts.cif.client.show', ['token' => $token]),
        ]);
    }

    public function clientSpecimenForm(Request $request, string $token): View
    {
        $contact = $this->findContactByClientToken('specimen', $token);
        $specimenSignature = SpecimenSignature::query()->where('contact_id', $contact->id)->first();

        abort_if($contact->specimen_access_expires_at && $contact->specimen_access_expires_at->isPast(), 403, 'This specimen signature link has expired.');

        return view('contacts.specimen-client-form', [
            'contact' => $contact,
            'specimenSignature' => $specimenSignature,
            'specimenForm' => $this->specimenFormData($contact, $specimenSignature),
            'clientFormAction' => route('contacts.specimen.client.submit', ['token' => $token]),
            'clientPreviewUrl' => route('contacts.specimen.client.preview', ['token' => $token]),
            'clientDownloadUrl' => route('contacts.specimen.client.download', ['token' => $token, 'autoprint' => 1]),
            'kycRequirementState' => $this->kycRequirementState($contact, $specimenSignature),
        ]);
    }

    public function submitClientSpecimenForm(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $contact = $this->findContactByClientToken('specimen', $token);

        abort_if($contact->specimen_access_expires_at && $contact->specimen_access_expires_at->isPast(), 403, 'This specimen signature link has expired.');

        $validated = $this->validatedSpecimenPayload($request);
        $this->validateClientSpecimenSignedUpload($request);
        $existing = SpecimenSignature::query()->where('contact_id', $contact->id)->first();
        $existingAuthenticationData = (array) ($existing?->authentication_data ?? []);
        $resolvedBusinessBifNo = $this->resolvedBusinessBifNumber($contact);
        $resolvedClientCifNo = (string) ($contact->cif_no ?? '');

        SpecimenSignature::query()->updateOrCreate(
            ['contact_id' => $contact->id],
            [
                'date' => $validated['date'] ?? null,
                'bif_no' => $this->shouldUseBusinessBifNumber($contact)
                    ? $resolvedBusinessBifNo
                    : ($validated['bif_no'] ?? null),
                'client_type' => $validated['client_type'] ?? 'new',
                'business_name_left' => $validated['business_name_left'] ?? null,
                'business_name_right' => $validated['business_name_right'] ?? null,
                'account_number_left' => $validated['account_number_left'] ?? null,
                'account_number_right' => $validated['account_number_right'] ?? null,
                'signatories' => collect($validated['signatory_names'] ?? [])->map(fn ($name) => ['name' => $name, 'signature' => null])->all(),
                'authentication_data' => [
                    'signature_combination' => $validated['signature_combination'] ?? null,
                    'signature_class' => $validated['signature_class'] ?? null,
                    'left_client_name' => $validated['left_client_name'] ?? null,
                    'left_cif_no' => $resolvedClientCifNo,
                    'left_cif_dated' => $validated['left_cif_dated'] ?? null,
                    'right_client_name' => $validated['right_client_name'] ?? null,
                    'right_cif_no' => $resolvedClientCifNo,
                    'right_cif_dated' => $validated['right_cif_dated'] ?? null,
                    'authenticated_by' => $validated['authenticated_by'] ?? null,
                    'board_resolution_spa_no' => $validated['board_resolution_spa_no'] ?? null,
                    'board_resolution_spa_date' => $validated['board_resolution_spa_date'] ?? null,
                    'signature_over_printed_name' => $validated['signature_over_printed_name'] ?? null,
                    'authorized_signatory_signature' => $validated['authorized_signatory_signature'] ?? null,
                    'authorized_signatory_date' => $validated['authorized_signatory_date'] ?? null,
                    'processing_instruction' => $existingAuthenticationData['processing_instruction'] ?? null,
                    'sales_marketing' => $existingAuthenticationData['sales_marketing'] ?? null,
                    'processed_by' => $existingAuthenticationData['processed_by'] ?? null,
                    'processed_date' => $existingAuthenticationData['processed_date'] ?? null,
                    'finance' => $existingAuthenticationData['finance'] ?? null,
                    'scanned_by' => $existingAuthenticationData['scanned_by'] ?? null,
                    'scanned_date' => $existingAuthenticationData['scanned_date'] ?? null,
                ],
                'remarks' => $existing?->remarks,
                'created_by' => $existing?->created_by ?: ($contact->owner_name ?: 'Client'),
            ]
        );

        $this->storeClientSubmittedSpecimenDocuments($request, $contact);

        $this->syncContactKycSnapshot($contact, [
            'cif_no' => $resolvedClientCifNo,
        ]);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'Your Specimen Signature Form has been submitted successfully.',
            ]);
        }

        return redirect()
            ->route('contacts.specimen.client.show', ['token' => $token])
            ->with('success', 'Your Specimen Signature Form has been submitted successfully.');
    }

    public function previewClientSpecimenForm(Request $request, string $token): View
    {
        $contact = $this->findContactByClientToken('specimen', $token);
        $specimenSignature = SpecimenSignature::query()->where('contact_id', $contact->id)->first();

        abort_if($contact->specimen_access_expires_at && $contact->specimen_access_expires_at->isPast(), 403, 'This specimen signature link has expired.');

        return view('contacts.specimen-signature-print', [
            'contact' => $contact,
            'data' => $this->buildSpecimenPrintData($contact, $specimenSignature),
            'autoPrint' => false,
            'embedMode' => false,
            'backUrl' => route('contacts.specimen.client.show', ['token' => $token]),
        ]);
    }

    public function downloadClientSpecimenForm(Request $request, string $token): View
    {
        $contact = $this->findContactByClientToken('specimen', $token);
        $specimenSignature = SpecimenSignature::query()->where('contact_id', $contact->id)->first();

        abort_if($contact->specimen_access_expires_at && $contact->specimen_access_expires_at->isPast(), 403, 'This specimen signature link has expired.');

        return view('contacts.specimen-signature-print', [
            'contact' => $contact,
            'data' => $this->buildSpecimenPrintData($contact, $specimenSignature),
            'autoPrint' => $request->boolean('autoprint'),
            'embedMode' => false,
            'backUrl' => route('contacts.specimen.client.show', ['token' => $token]),
        ]);
    }

    public function saveCif(Request $request, string $contact): RedirectResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);
        $this->abortIfApprovedKycLockedForUser($contactModel, $request->user());
        if ($contactModel->exists && blank($contactModel->cif_status) && Schema::hasColumn('contacts', 'cif_status')) {
            $contactModel->updateQuietly(['cif_status' => 'draft']);
        }

        $validated = $request->validate([
            'cif_date' => ['nullable', 'date'],
            'cif_no' => ['nullable', 'string', 'max:100'],
            'is_new_client' => ['nullable', 'boolean'],
            'is_existing_client' => ['nullable', 'boolean'],
            'is_change_information' => ['nullable', 'boolean'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'no_middle_name' => ['nullable', 'boolean'],
            'only_first_name' => ['nullable', 'boolean'],
            'present_address_line1' => ['nullable', 'string', 'max:255'],
            'present_address_line2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'citizenship_nationality' => ['nullable', 'string', 'max:150'],
            'citizenship_type' => ['nullable', 'in:filipino,foreigner,dual_citizen'],
            'gender' => ['nullable', 'in:male,female'],
            'civil_status' => ['nullable', 'in:single,separated,widowed,married'],
            'spouse_name' => ['nullable', 'string', 'max:150'],
            'nature_of_work_business' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:100'],
            'other_government_id' => ['nullable', 'string', 'max:150'],
            'id_number' => ['nullable', 'string', 'max:150'],
            'mothers_maiden_name' => ['nullable', 'string', 'max:150'],
            'source_of_funds' => ['nullable', 'array'],
            'source_of_funds.*' => ['string', 'in:salary,remittance,business,others,commission_fees,retirement_pension'],
            'source_of_funds_other_text' => ['nullable', 'string', 'max:150'],
            'foreigner_passport_no' => ['nullable', 'string', 'max:150'],
            'foreigner_passport_expiry_date' => ['nullable', 'date'],
            'foreigner_passport_place_of_issue' => ['nullable', 'string', 'max:150'],
            'foreigner_acr_id_no' => ['nullable', 'string', 'max:150'],
            'foreigner_acr_expiry_date' => ['nullable', 'date'],
            'foreigner_acr_place_of_issue' => ['nullable', 'string', 'max:150'],
            'visa_status' => ['nullable', 'string', 'max:150'],
            'onboarding_two_valid_ids' => ['nullable', 'boolean'],
            'onboarding_tin_id' => ['nullable', 'boolean'],
            'onboarding_authorized_signatory_card' => ['nullable', 'boolean'],
            'referred_by_footer' => ['nullable', 'string', 'max:150'],
            'referred_date' => ['nullable', 'date'],
            'sales_marketing_footer' => ['nullable', 'string', 'max:150'],
            'finance_footer' => ['nullable', 'string', 'max:150'],
            'president_footer' => ['nullable', 'string', 'max:150'],
            'sig_name_left' => ['nullable', 'string', 'max:150'],
            'sig_position_left' => ['nullable', 'string', 'max:150'],
            'sig_name_right' => ['nullable', 'string', 'max:150'],
            'sig_position_right' => ['nullable', 'string', 'max:150'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'kyc_status' => ['nullable', 'string', 'max:100'],
            'date_verified' => ['nullable', 'date'],
            'verified_by' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['source_of_funds'] = array_values(array_filter($validated['source_of_funds'] ?? []));
        $validated['is_new_client'] = $request->boolean('is_new_client');
        $validated['is_existing_client'] = $request->boolean('is_existing_client');
        $validated['is_change_information'] = $request->boolean('is_change_information');
        $validated['no_middle_name'] = $request->boolean('no_middle_name');
        $validated['only_first_name'] = $request->boolean('only_first_name');
        $validated['onboarding_two_valid_ids'] = $request->boolean('onboarding_two_valid_ids');
        $validated['onboarding_tin_id'] = $request->boolean('onboarding_tin_id');
        $validated['onboarding_authorized_signatory_card'] = $request->boolean('onboarding_authorized_signatory_card');

        validator($validated, [])->after(function (Validator $validator) use ($validated) {
            $onlyFirstName = (bool) ($validated['only_first_name'] ?? false);
            if (! $onlyFirstName && blank($validated['last_name'] ?? null)) {
                $validator->errors()->add('last_name', 'Last name is required unless "I only have a First Name" is checked.');
            }
        })->validate();

        $existingCifData = $this->loadCifData($contactModel);
        $nextCifData = [
            ...$existingCifData,
            ...$validated,
        ];

        $this->syncContactFromCifPayload($contactModel, $validated);
        $contactModel->refresh();
        $nextCifData = [
            ...$this->loadCifData($contactModel),
            ...$validated,
        ];

        $this->saveCifDataToStorage($contactModel, $nextCifData);

        ContactHistoryLogger::log($contactModel->id, [
            'type' => 'kyc',
            'title' => 'Client intake form updated',
            'description' => 'Client Information Form details were updated',
            'extra_label' => 'CIF',
            'extra_value' => $nextCifData['cif_no'] ?? $contactModel->cif_no,
            'user_name' => $request->user()?->name ?? ($contactModel->owner_name ?: 'System'),
            'user_initials' => $this->initialsForHistory($request->user()?->name ?? ($contactModel->owner_name ?: 'System')),
        ]);

        if (
            Schema::hasColumn('contacts', 'cif_status')
            && $this->hasCifDataChanges($existingCifData, $nextCifData)
            && in_array(Str::lower((string) ($contactModel->cif_status ?? 'draft')), ['approved', 'pending', 'rejected'], true)
        ) {
            $this->resetContactKycForResubmission($contactModel);
            $nextCifData['date_verified'] = '';
            $nextCifData['verified_by'] = '';
            $nextCifData['change_request_status'] = '';
            $nextCifData['change_request_note'] = '';
            $nextCifData['change_requested_at'] = '';
            $nextCifData['change_requested_by'] = '';
            $nextCifData['change_reviewed_at'] = '';
            $nextCifData['change_reviewed_by'] = '';
            $this->saveCifDataToStorage($contactModel, $nextCifData);

            ContactHistoryLogger::log($contactModel->id, [
                'type' => 'kyc',
                'title' => 'KYC returned to approval',
                'description' => 'Client Information Form edits were submitted for admin review',
                'extra_label' => 'Status',
                'extra_value' => 'Pending Verification',
                'user_name' => $request->user()?->name ?? ($contactModel->owner_name ?: 'System'),
                'user_initials' => $this->initialsForHistory($request->user()?->name ?? ($contactModel->owner_name ?: 'System')),
            ]);
        }

        $this->syncContactKycSnapshot($contactModel, [
            'cif_no' => $validated['cif_no'] ?? null,
            'tin' => $validated['tin'] ?? null,
        ]);

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'CIF information saved successfully.');
    }

    public function uploadCifDocument(Request $request, string $contact): RedirectResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);
        $this->abortIfApprovedKycLockedForUser($contactModel, $request->user());

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:cif_document,valid_id,tin_document,registration_document,other'],
            'document_file' => ['required', 'file', 'max:5120'],
            'document_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $storedPath = $request->file('document_file')->store('contact-cif-documents', 'public');
        $documents = $this->loadCifDocuments($contactModel);
        $documents[$validated['document_type']] = [
            'label' => $this->cifDocumentTypes()[$validated['document_type']],
            'path' => $storedPath,
            'file_name' => $request->file('document_file')->getClientOriginalName(),
            'mime_type' => $request->file('document_file')->getMimeType(),
            'uploaded_at' => now()->toDateTimeString(),
            'notes' => $validated['document_notes'] ?? '',
        ];

        $this->saveCifDocumentsToStorage($contactModel, $documents);
        $this->resetApprovedKycAfterModificationIfNeeded($contactModel, $request->user());

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'Supporting document uploaded successfully.');
    }

    public function uploadKycRequirementDocument(Request $request, string $contact): RedirectResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);
        $this->abortIfApprovedKycLockedForUser($contactModel, $request->user());

        $rawUpload = $request->file('document_file') ?? $request->file('document');
        if ($rawUpload && ! $rawUpload->isValid()) {
            return redirect()
                ->to(route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']).'#kyc')
                ->withErrors([
                    'document_file' => $rawUpload->getErrorMessage() ?: 'The selected file could not be uploaded.',
                ], 'kycRequirementUpload');
        }

        $validator = validator(array_merge($request->all(), $request->allFiles()), [
            'requirement' => ['required', 'string', 'in:cif_signed_document,two_valid_ids,tin_proof,specimen_signature_upload,specimen_signature_form,passport_proof,visa_proof,acr_card_proof,aaep_proof'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'cif_no' => ['nullable', 'string', 'max:100'],
            'company_reg_no' => ['nullable', 'string', 'max:100'],
            'date_upload' => ['nullable', 'date'],
            'date_created' => ['nullable', 'date'],
            'issued_on' => ['nullable', 'date'],
            'issued_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']).'#kyc')
                ->withErrors($validator, 'kycRequirementUpload')
                ->withInput();
        }

        $validated = $validator->validated();

        $documents = $this->loadKycRequirementDocuments($contactModel);
        $requirement = $this->normalizeKycRequirementKey($validated['requirement']);
        $upload = $requirement === 'cif_signed_document'
            ? $request->file('document')
            : ($request->file('document_file') ?? $request->file('document'));

        if ($requirement !== 'cif_signed_document' && ! $upload) {
            return redirect()
                ->to(route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']).'#kyc')
                ->withErrors(['document_file' => 'Please upload a document file.'], 'kycRequirementUpload');
        }

        if ($requirement === 'two_valid_ids') {
            $storedPath = $upload->store('contact-kyc-documents', 'public');
            $document = [
                'path' => $storedPath,
                'file_path' => $storedPath,
                'file_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getMimeType(),
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $request->user()?->name ?? 'Admin User',
            ];
            $existing = array_values(array_filter((array) ($documents['two_valid_ids'] ?? []), fn ($item) => is_array($item)));
            $existing[] = $document;
            $documents['two_valid_ids'] = $existing;
        } elseif ($requirement === 'cif_signed_document') {
            $existing = is_array($documents[$requirement] ?? null) ? $documents[$requirement] : null;
            $cifDocumentDefaults = $this->cifSignedDocumentDefaults($contactModel);

            if (! $upload && $existing === null) {
                return redirect()
                    ->to(route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']).'#kyc')
                    ->withErrors(['document' => 'Please upload the signed CIF document first.'], 'kycRequirementUpload');
            }

            $document = $existing ?? [];

            if ($upload) {
                if (! empty($document['path']) && Storage::disk('public')->exists($document['path'])) {
                    Storage::disk('public')->delete($document['path']);
                }

                $storedPath = $upload->store('contact-kyc-documents', 'public');
                $document['path'] = $storedPath;
                $document['file_path'] = $storedPath;
                $document['file_name'] = $upload->getClientOriginalName();
                $document['mime_type'] = $upload->getMimeType();
                $document['uploaded_at'] = $validated['date_upload'] ?? now()->toDateTimeString();
                $document['uploaded_by'] = $request->user()?->name ?? 'Admin User';
            } else {
                $document['uploaded_at'] = $validated['date_upload'] ?? ($document['uploaded_at'] ?? now()->toDateTimeString());
                $document['uploaded_by'] = $document['uploaded_by'] ?? ($request->user()?->name ?? 'Admin User');
            }

            $document['document_title'] = $validated['document_title']
                ?? ($document['document_title']
                ?? ($cifDocumentDefaults['document_title']
                ?? pathinfo((string) ($document['file_name'] ?? ''), PATHINFO_FILENAME)));
            $document['cif_no'] = $validated['cif_no']
                ?? ($document['cif_no'] ?? $cifDocumentDefaults['cif_no']);
            $document['company_reg_no'] = '';
            $document['date_created'] = $validated['date_created'] ?? ($document['date_created'] ?? $cifDocumentDefaults['date_created']);
            $document['issued_on'] = $validated['issued_on'] ?? ($document['issued_on'] ?? $cifDocumentDefaults['issued_on']);
            $uploaderName = $request->user()?->name ?? 'Admin User';
            $document['issued_by'] = $validated['issued_by']
                ?? ($upload ? $uploaderName : null)
                ?? ($document['uploaded_by'] ?? null)
                ?? ($document['issued_by'] ?? $cifDocumentDefaults['issued_by']);
            $document['remarks'] = $validated['remarks'] ?? ($document['remarks'] ?? '');

            $documents[$requirement] = $document;
        } else {
            $storedPath = $upload->store('contact-kyc-documents', 'public');
            $document = [
                'path' => $storedPath,
                'file_path' => $storedPath,
                'file_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getMimeType(),
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $request->user()?->name ?? 'Admin User',
            ];
            $existing = array_values(array_filter((array) ($documents[$requirement] ?? []), fn ($item) => is_array($item)));
            $existing[] = $document;
            $documents[$requirement] = $existing;
        }

        $this->saveKycRequirementDocuments($contactModel, $documents);
        $this->syncContactKycSnapshot($contactModel, [
            'tin' => $requirement === 'tin_proof'
                ? ($this->loadCifData($contactModel)['tin'] ?? $contactModel->tin)
                : $contactModel->tin,
        ]);
        $this->resetApprovedKycAfterModificationIfNeeded($contactModel, $request->user());

        return redirect()->to(route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']).'#kyc')
            ->with('success', 'KYC requirement document uploaded successfully.');
    }

    public function removeKycRequirementDocument(Request $request, string $contact, string $requirement): RedirectResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);
        $this->abortIfApprovedKycLockedForUser($contactModel, $request->user());
        $requirement = $this->normalizeKycRequirementKey($requirement);
        abort_unless(in_array($requirement, ['cif_signed_document', 'two_valid_ids', 'tin_proof', 'specimen_signature_upload', 'passport_proof', 'visa_proof', 'acr_card_proof', 'aaep_proof'], true), 404);

        $documents = $this->loadKycRequirementDocuments($contactModel);
        $index = $request->filled('index') ? max(0, (int) $request->input('index')) : null;

        if ($requirement === 'two_valid_ids') {
            $files = array_values(array_filter((array) ($documents['two_valid_ids'] ?? []), fn ($item) => is_array($item)));
            if ($index !== null && isset($files[$index])) {
                $storedPath = $files[$index]['file_path'] ?? $files[$index]['path'] ?? null;
                if (! empty($storedPath) && Storage::disk('public')->exists($storedPath)) {
                    Storage::disk('public')->delete($storedPath);
                }
                unset($files[$index]);
                $documents['two_valid_ids'] = array_values($files);
            } else {
                foreach ($files as $document) {
                    $storedPath = $document['file_path'] ?? $document['path'] ?? null;
                    if (! empty($storedPath) && Storage::disk('public')->exists($storedPath)) {
                        Storage::disk('public')->delete($storedPath);
                    }
                }
                $documents['two_valid_ids'] = [];
            }
        } elseif (in_array($requirement, ['tin_proof', 'specimen_signature_upload', 'passport_proof', 'visa_proof', 'acr_card_proof', 'aaep_proof'], true)) {
            $files = array_values(array_filter((array) ($documents[$requirement] ?? []), fn ($item) => is_array($item)));
            if ($index !== null && isset($files[$index])) {
                $storedPath = $files[$index]['file_path'] ?? $files[$index]['path'] ?? null;
                if (! empty($storedPath) && Storage::disk('public')->exists($storedPath)) {
                    Storage::disk('public')->delete($storedPath);
                }
                unset($files[$index]);
                $documents[$requirement] = array_values($files);
            } else {
                foreach ($files as $document) {
                    $storedPath = $document['file_path'] ?? $document['path'] ?? null;
                    if (! empty($storedPath) && Storage::disk('public')->exists($storedPath)) {
                        Storage::disk('public')->delete($storedPath);
                    }
                }
                $documents[$requirement] = [];
            }
        } else {
            $document = $documents[$requirement] ?? null;
            $storedPath = is_array($document) ? ($document['file_path'] ?? $document['path'] ?? null) : null;
            if (is_array($document) && ! empty($storedPath) && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }
            $documents[$requirement] = null;
        }

        $this->saveKycRequirementDocuments($contactModel, $documents);
        $this->resetApprovedKycAfterModificationIfNeeded($contactModel, $request->user());

        return redirect()->to(route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']).'#kyc')
            ->with('success', 'KYC requirement document removed successfully.');
    }

    public function previewCif(Request $request, string $contact): View
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);

        return view('contacts.cif-preview', [
            'contact' => $contactModel,
            'cifData' => $this->loadCifData($contactModel),
            'cifDocuments' => $this->loadCifDocuments($contactModel),
            'downloadMode' => false,
            'autoPrint' => false,
            'backUrl' => route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']),
        ]);
    }

    public function downloadCif(Request $request, string $contact): View
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);

        return view('contacts.cif-preview', [
            'contact' => $contactModel,
            'cifData' => $this->loadCifData($contactModel),
            'cifDocuments' => $this->loadCifDocuments($contactModel),
            'downloadMode' => true,
            'autoPrint' => $request->boolean('autoprint'),
            'backUrl' => route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']),
        ]);
    }

    public function specimenSignature(Request $request, string $id): View
    {
        $contactModel = Contact::query()->find($id);
        abort_unless($contactModel, 404);

        $specimenSignature = SpecimenSignature::query()->where('contact_id', $contactModel->id)->first();
        $isEditMode = ! $this->isApprovedKycLockedForUser($contactModel, $request->user())
            && ($request->boolean('edit') || $request->session()->has('errors'));

        return view('contacts.specimen-signature', [
            'contact' => $contactModel,
            'specimenSignature' => $specimenSignature,
            'specimenForm' => $this->specimenFormData($contactModel, $specimenSignature),
            'isEditMode' => $isEditMode,
        ]);
    }

    public function saveSpecimenSignature(Request $request, string $id): RedirectResponse
    {
        $contactModel = Contact::query()->find($id);
        abort_unless($contactModel, 404);
        $this->abortIfApprovedKycLockedForUser($contactModel, $request->user());

        $existing = SpecimenSignature::query()->where('contact_id', $contactModel->id)->first();
        $resolvedBusinessBifNo = $this->resolvedBusinessBifNumber($contactModel);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'bif_no' => ['nullable', 'string', 'max:100'],
            'client_type' => ['nullable', 'string', 'in:new,existing,change'],
            'business_name_left' => ['nullable', 'string', 'max:255'],
            'business_name_right' => ['nullable', 'string', 'max:255'],
            'account_number_left' => ['nullable', 'string', 'max:255'],
            'account_number_right' => ['nullable', 'string', 'max:255'],
            'signature_combination' => ['nullable', 'string', 'max:255'],
            'signature_class' => ['nullable', 'string', 'max:255'],
            'left_client_name' => ['nullable', 'string', 'max:255'],
            'left_cif_no' => ['nullable', 'string', 'max:100'],
            'left_cif_dated' => ['nullable', 'date'],
            'right_client_name' => ['nullable', 'string', 'max:255'],
            'right_cif_no' => ['nullable', 'string', 'max:100'],
            'right_cif_dated' => ['nullable', 'date'],
            'signatory_names' => ['nullable', 'array'],
            'signatory_names.*' => ['nullable', 'string', 'max:255'],
            'authenticated_by' => ['nullable', 'string', 'max:255'],
            'board_resolution_spa_no' => ['nullable', 'string', 'max:255'],
            'board_resolution_spa_date' => ['nullable', 'date'],
            'signature_over_printed_name' => ['nullable', 'string', 'max:255'],
            'authorized_signatory_signature' => ['nullable', 'string', 'max:255'],
            'authorized_signatory_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'processing_instruction' => ['nullable', 'string', 'max:5000'],
            'sales_marketing' => ['nullable', 'string', 'max:255'],
            'processed_by' => ['nullable', 'string', 'max:255'],
            'processed_date' => ['nullable', 'date'],
            'finance' => ['nullable', 'string', 'max:255'],
            'scanned_by' => ['nullable', 'string', 'max:255'],
            'scanned_date' => ['nullable', 'date'],
        ]);

        validator($validated, [])->after(function (Validator $validator) use ($validated) {
            if (blank($validated['business_name_left'] ?? null) && blank($validated['business_name_right'] ?? null)) {
                $validator->errors()->add('business_name_left', 'Business Name is required.');
            }

            $hasSignatory = collect($validated['signatory_names'] ?? [])
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->isNotEmpty();

            if (! $hasSignatory) {
                $validator->errors()->add('signatory_names', 'At least 1 signatory is required.');
            }
        })->validate();

        $signatories = collect($validated['signatory_names'] ?? [])
            ->pad(6, null)
            ->take(6)
            ->map(function ($name): array {
                $cleanName = trim((string) $name);

                return [
                    'name' => $cleanName !== '' ? $cleanName : null,
                    'signature' => null,
                ];
            })
            ->values()
            ->all();

        $authenticationData = [
            'left_client_name' => $validated['left_client_name'] ?? null,
            'left_cif_no' => $validated['left_cif_no'] ?? null,
            'left_cif_dated' => $validated['left_cif_dated'] ?? null,
            'right_client_name' => $validated['right_client_name'] ?? null,
            'right_cif_no' => $validated['right_cif_no'] ?? null,
            'right_cif_dated' => $validated['right_cif_dated'] ?? null,
            'signature_combination' => $validated['signature_combination'] ?? null,
            'signature_class' => $validated['signature_class'] ?? null,
            'authenticated_by' => $validated['authenticated_by'] ?? null,
            'board_resolution_spa_no' => $validated['board_resolution_spa_no'] ?? null,
            'board_resolution_spa_date' => $validated['board_resolution_spa_date'] ?? null,
            'signature_over_printed_name' => $validated['signature_over_printed_name'] ?? null,
            'authorized_signatory_signature' => $validated['authorized_signatory_signature'] ?? null,
            'authorized_signatory_date' => $validated['authorized_signatory_date'] ?? null,
            'processing_instruction' => $validated['processing_instruction'] ?? null,
            'sales_marketing' => $validated['sales_marketing'] ?? null,
            'processed_by' => $validated['processed_by'] ?? null,
            'processed_date' => $validated['processed_date'] ?? null,
            'finance' => $validated['finance'] ?? null,
            'scanned_by' => $validated['scanned_by'] ?? null,
            'scanned_date' => $validated['scanned_date'] ?? null,
        ];

        SpecimenSignature::query()->updateOrCreate(
            ['contact_id' => $contactModel->id],
            [
                'bif_no' => $this->shouldUseBusinessBifNumber($contactModel)
                    ? $resolvedBusinessBifNo
                    : ($validated['bif_no'] ?? null),
                'date' => $validated['date'] ?? now()->toDateString(),
                'client_type' => $validated['client_type'] ?? null,
                'business_name_left' => $validated['business_name_left'] ?? null,
                'business_name_right' => $validated['business_name_right'] ?? null,
                'account_number_left' => $validated['account_number_left'] ?? null,
                'account_number_right' => $validated['account_number_right'] ?? null,
                'signatories' => $signatories,
                'authentication_data' => $authenticationData,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $existing?->created_by ?: ($request->user()?->name ?? 'Admin User'),
            ]
        );

        $this->syncContactKycSnapshot($contactModel, [
            'cif_no' => $validated['left_cif_no'] ?? $contactModel->cif_no,
        ]);
        $this->resetApprovedKycAfterModificationIfNeeded($contactModel, $request->user());

        return redirect()
            ->route('contacts.specimen-signature', ['id' => $contactModel->id])
            ->with('success', 'Specimen Signature Form saved successfully.');
    }

    public function submitKycForVerification(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        $contactModel = Contact::query()->find($contact);
        abort_unless($contactModel, 404);

        $currentCifStatus = Str::lower((string) ($contactModel->cif_status ?? 'draft'));
        if ($currentCifStatus === 'pending') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'CIF is already pending approval.']);
        }

        if ($currentCifStatus === 'approved') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'CIF is already approved. Edit the CIF details first before submitting again.']);
        }

        $statusPayload = $this->filterPersistableContactAttributes([
            'kyc_status' => 'Pending Verification',
            'cif_status' => 'pending',
            'cif_submitted_at' => now(),
            'cif_reviewed_at' => null,
            'cif_reviewed_by' => null,
            'cif_rejection_reason' => null,
        ]);

        if (! empty($statusPayload)) {
            $contactModel->forceFill($statusPayload)->save();
        }

        ContactHistoryLogger::log($contactModel->id, [
            'type' => 'kyc',
            'title' => 'KYC submitted for verification',
            'description' => 'Client Information Form submitted for admin review',
            'extra_label' => 'Status',
            'extra_value' => 'Pending Verification',
            'user_name' => $request->user()?->name ?? ($contactModel->owner_name ?: 'System'),
            'user_initials' => $this->initialsForHistory($request->user()?->name ?? ($contactModel->owner_name ?: 'System')),
        ]);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'KYC submitted for verification successfully.',
                'kyc' => [
                    'status' => 'Pending Verification',
                    'submitted' => true,
                    'dateVerified' => '',
                    'verifiedBy' => '',
                    'rejectionReason' => '',
                ],
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'KYC submitted for verification successfully.');
    }

    public function approveKyc(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        abort_unless($this->isKycReviewer($request->user()), 403);
        $contactModel = Contact::query()->findOrFail($contact);

        if (strtolower((string) ($contactModel->cif_status ?? '')) !== 'pending') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'Only pending CIF submissions can be approved.']);
        }

        $payload = $this->filterPersistableContactAttributes([
            'kyc_status' => 'Verified',
            'cif_status' => 'approved',
            'cif_reviewed_at' => now(),
            'cif_reviewed_by' => $request->user()?->name,
            'cif_rejection_reason' => null,
        ]);

        if (! empty($payload)) {
            $contactModel->forceFill($payload)->save();
        }

        $cifData = $this->loadCifData($contactModel);
        $cifData['date_verified'] = now()->toDateString();
        $cifData['verified_by'] = $request->user()?->name ?? '';
        $cifData['change_request_status'] = '';
        $cifData['change_request_note'] = '';
        $cifData['change_requested_at'] = '';
        $cifData['change_requested_by'] = '';
        $cifData['change_reviewed_at'] = '';
        $cifData['change_reviewed_by'] = '';
        $cifData['change_rejection_reason'] = '';
        $this->saveCifDataToStorage($contactModel, $cifData);

        ContactHistoryLogger::log($contactModel->id, [
            'type' => 'kyc',
            'title' => 'KYC approved',
            'description' => 'Client Information Form approved',
            'extra_label' => 'Status',
            'extra_value' => 'Verified',
            'user_name' => $request->user()?->name ?? 'System',
            'user_initials' => $this->initialsForHistory($request->user()?->name ?? 'System'),
        ]);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'CIF approved successfully.',
                'kyc' => [
                    'status' => 'Approved',
                    'submitted' => true,
                    'dateVerified' => $cifData['date_verified'] ?? '',
                    'verifiedBy' => $cifData['verified_by'] ?? '',
                    'rejectionReason' => '',
                ],
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'CIF approved successfully.');
    }

    public function rejectKyc(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        abort_unless($this->isKycReviewer($request->user()), 403);
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $contactModel = Contact::query()->findOrFail($contact);

        if (strtolower((string) ($contactModel->cif_status ?? '')) !== 'pending') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'Only pending CIF submissions can be rejected.']);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));

        $payload = $this->filterPersistableContactAttributes([
            'kyc_status' => 'Rejected',
            'cif_status' => 'rejected',
            'cif_reviewed_at' => now(),
            'cif_reviewed_by' => $request->user()?->name,
            'cif_rejection_reason' => $reason !== '' ? $reason : null,
        ]);

        if (! empty($payload)) {
            $contactModel->forceFill($payload)->save();
        }

        if ($reason !== '') {
            $cifData = $this->loadCifData($contactModel);
            $cifData['remarks'] = $reason;
            $cifData['date_verified'] = '';
            $cifData['verified_by'] = '';
            $this->saveCifDataToStorage($contactModel, $cifData);
        }

        ContactHistoryLogger::log($contactModel->id, [
            'type' => 'kyc',
            'title' => 'KYC rejected',
            'description' => $reason !== '' ? $reason : 'Client Information Form rejected',
            'extra_label' => 'Status',
            'extra_value' => 'Rejected',
            'user_name' => $request->user()?->name ?? 'System',
            'user_initials' => $this->initialsForHistory($request->user()?->name ?? 'System'),
        ]);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'CIF rejected.',
                'kyc' => [
                    'status' => 'Rejected',
                    'submitted' => true,
                    'dateVerified' => '',
                    'verifiedBy' => '',
                    'rejectionReason' => $reason,
                ],
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'CIF rejected.');
    }

    public function requestKycChange(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);
        abort_if($this->isKycReviewer($request->user()), 403);

        if (strtolower((string) ($contactModel->cif_status ?? '')) !== 'approved') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'Only approved CIF records can request change information.']);
        }

        $cifData = $this->loadCifData($contactModel);
        if (strtolower((string) ($cifData['change_request_status'] ?? '')) === 'pending') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'A change request is already pending approval.']);
        }

        $validated = $request->validate([
            'change_request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cifData['change_request_status'] = 'pending';
        $cifData['change_request_note'] = trim((string) ($validated['change_request_note'] ?? ''));
        $cifData['change_requested_at'] = now()->toDateTimeString();
        $cifData['change_requested_by'] = $request->user()?->name ?? '';
        $cifData['change_reviewed_at'] = '';
        $cifData['change_reviewed_by'] = '';
        $cifData['change_rejection_reason'] = '';
        $this->saveCifDataToStorage($contactModel, $cifData);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'Change request submitted. Please wait for admin approval before editing.',
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'Change request submitted. Please wait for admin approval before editing.');
    }

    public function approveKycChange(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        abort_unless($this->isKycReviewer($request->user()), 403);
        $contactModel = Contact::query()->findOrFail($contact);

        if (strtolower((string) ($contactModel->cif_status ?? '')) !== 'approved') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'Only approved CIF records can unlock change editing.']);
        }

        $cifData = $this->loadCifData($contactModel);
        if (strtolower((string) ($cifData['change_request_status'] ?? '')) !== 'pending') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'There is no pending change request to approve.']);
        }

        $cifData['change_request_status'] = 'approved';
        $cifData['change_reviewed_at'] = now()->toDateTimeString();
        $cifData['change_reviewed_by'] = $request->user()?->name ?? '';
        $cifData['change_rejection_reason'] = '';
        $this->saveCifDataToStorage($contactModel, $cifData);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'Change request approved. Editing is now unlocked until the next resubmission.',
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'Change request approved. Editing is now unlocked until the next resubmission.');
    }

    public function rejectKycChange(Request $request, string $contact): RedirectResponse|JsonResponse
    {
        abort_unless($this->isKycReviewer($request->user()), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $contactModel = Contact::query()->findOrFail($contact);

        if (strtolower((string) ($contactModel->cif_status ?? '')) !== 'approved') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'Only approved CIF records can reject change requests.']);
        }

        $cifData = $this->loadCifData($contactModel);
        if (strtolower((string) ($cifData['change_request_status'] ?? '')) !== 'pending') {
            return redirect()
                ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
                ->withErrors(['kyc' => 'There is no pending change request to reject.']);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        $cifData['change_request_status'] = 'rejected';
        $cifData['change_reviewed_at'] = now()->toDateTimeString();
        $cifData['change_reviewed_by'] = $request->user()?->name ?? '';
        $cifData['change_rejection_reason'] = $reason;
        $this->saveCifDataToStorage($contactModel, $cifData);

        if ($this->prefersJsonResponse($request)) {
            return response()->json([
                'message' => 'Change request rejected.',
            ]);
        }

        return redirect()
            ->route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc'])
            ->with('success', 'Change request rejected.');
    }

    public function downloadSpecimenSignature(Request $request, string $id): View
    {
        $contactModel = Contact::query()->find($id);
        abort_unless($contactModel, 404);

        $data = SpecimenSignature::query()->where('contact_id', $contactModel->id)->first();

        return view('contacts.specimen-signature-print', [
            'contact' => $contactModel,
            'data' => $this->buildSpecimenPrintData($contactModel, $data),
            'autoPrint' => $request->boolean('autoprint'),
            'embedMode' => $request->boolean('embed'),
            'backUrl' => route('contacts.show', ['contact' => $contactModel->id, 'tab' => 'kyc']),
        ]);
    }

    private function buildSpecimenPrintData(Contact $contact, ?SpecimenSignature $data): object
    {
        if ($data) {
            return $data;
        }

        $form = $this->specimenFormData($contact, null);

        return (object) [
            'bif_no' => $form['bif_no'] ?? '',
            'date' => filled($form['date'] ?? null) ? \Carbon\Carbon::parse($form['date']) : null,
            'client_type' => $form['client_type'] ?? 'new',
            'business_name_left' => $form['business_name_left'] ?? '',
            'business_name_right' => $form['business_name_right'] ?? '',
            'account_number_left' => $form['account_number_left'] ?? '',
            'account_number_right' => $form['account_number_right'] ?? '',
            'signatories' => collect($form['signatories'] ?? [])->map(fn ($name) => ['name' => $name, 'signature' => null])->all(),
            'authentication_data' => [
                'left_client_name' => $form['left_client_name'] ?? '',
                'left_cif_no' => $form['left_cif_no'] ?? '',
                'left_cif_dated' => $form['left_cif_dated'] ?? '',
                'right_client_name' => $form['right_client_name'] ?? '',
                'right_cif_no' => $form['right_cif_no'] ?? '',
                'right_cif_dated' => $form['right_cif_dated'] ?? '',
                'signature_combination' => $form['signature_combination'] ?? '',
                'signature_class' => $form['signature_class'] ?? '',
                'authenticated_by' => $form['authenticated_by'] ?? '',
                'board_resolution_spa_no' => $form['board_resolution_spa_no'] ?? '',
                'board_resolution_spa_date' => $form['board_resolution_spa_date'] ?? '',
                'signature_over_printed_name' => $form['signature_over_printed_name'] ?? '',
                'authorized_signatory_signature' => $form['authorized_signatory_signature'] ?? '',
                'authorized_signatory_date' => $form['authorized_signatory_date'] ?? '',
                'processing_instruction' => $form['processing_instruction'] ?? '',
                'sales_marketing' => $form['sales_marketing'] ?? '',
                'processed_by' => $form['processed_by'] ?? '',
                'processed_date' => $form['processed_date'] ?? '',
                'finance' => $form['finance'] ?? '',
                'scanned_by' => $form['scanned_by'] ?? '',
                'scanned_date' => $form['scanned_date'] ?? '',
            ],
            'remarks' => $form['remarks'] ?? '',
        ];
    }

    private function ownerOptions(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    private function prefersJsonResponse(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || str_contains(Str::lower((string) $request->header('Accept')), 'application/json');
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
            ['value' => 'deals', 'label' => 'Deals'],
            ['value' => 'company', 'label' => 'Company'],
            ['value' => 'products', 'label' => 'Products'],
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

    private function loadCifData(Contact $contact): array
    {
        $stored = $this->loadContactJsonPayload($contact, 'cif_data', [
            $this->cifDataPath($contact),
            $this->legacyCifDataPath($contact),
        ]);

        return $this->applyContactBackedCifFallbacks($contact, $stored);
    }

    private function loadClientCifData(Contact $contact): array
    {
        $stored = $this->loadContactJsonPayload($contact, 'cif_data', [
            $this->cifDataPath($contact),
            $this->legacyCifDataPath($contact),
        ]);

        return $this->applyContactBackedCifFallbacks($contact, $stored);
    }

    private function relatedCompaniesForContact(Contact $contact, string $search = ''): array
    {
        $relatedCompanies = Company::query()
            ->with([
                'latestBif' => fn ($query) => $query->select([
                    'company_bifs.id',
                    'company_bifs.company_id',
                    'company_bifs.status',
                    'company_bifs.industry_services',
                    'company_bifs.industry_export_import',
                    'company_bifs.industry_education',
                    'company_bifs.industry_financial_services',
                    'company_bifs.industry_transportation',
                    'company_bifs.industry_distribution',
                    'company_bifs.industry_manufacturing',
                    'company_bifs.industry_government',
                    'company_bifs.industry_wholesale_retail_trade',
                    'company_bifs.industry_other',
                    'company_bifs.industry_other_text',
                ]),
            ])
            ->select(['companies.id', 'companies.company_name', 'companies.email', 'companies.phone', 'companies.owner_name'])
            ->when(
                method_exists($contact, 'companies'),
                fn ($query) => $query->whereHas('contacts', fn ($relation) => $relation->where('contacts.id', $contact->id))
            )
            ->get();

        $items = $relatedCompanies
            ->unique('id')
            ->map(function (Company $company) {
                $bif = $company->latestBif;

                return [
                    'id' => $company->id,
                    'company_name' => $company->company_name,
                    'email' => $company->email ?: '-',
                    'phone' => $company->phone ?: '-',
                    'owner' => $company->owner_name ?: '-',
                    'status' => $bif?->status ?: 'Active',
                    'show_url' => route('company.show', $company->id),
                    'custom_fields' => is_array($company->custom_fields ?? null) ? $company->custom_fields : [],
                ];
            });

        if ($search !== '') {
            $items = $items->filter(fn (array $item) => Str::contains(Str::lower($item['company_name']), Str::lower($search)));
        }

        return $items->values()->all();
    }

    private function shouldUseBusinessBifNumber(Contact $contact): bool
    {
        return ($contact->customer_type ?? null) === 'business';
    }

    private function resolvedBusinessBifNumber(Contact $contact): ?string
    {
        if (! $this->shouldUseBusinessBifNumber($contact)) {
            return null;
        }

        $company = Company::query()
            ->with([
                'latestBif' => fn ($query) => $query->select([
                    'company_bifs.id',
                    'company_bifs.company_id',
                    'company_bifs.bif_no',
                ]),
            ])
            ->where(function ($query) use ($contact) {
                if (Schema::hasColumn('companies', 'primary_contact_id')) {
                    $query->where('primary_contact_id', $contact->id);
                }

                if (method_exists($contact, 'companies')) {
                    $query->orWhereHas('contacts', fn ($relation) => $relation->where('contacts.id', $contact->id));
                }
            })
            ->get()
            ->sortByDesc(function (Company $company) use ($contact) {
                $exactNameMatch = filled($contact->company_name)
                    && strcasecmp((string) $company->company_name, (string) $contact->company_name) === 0;

                return sprintf(
                    '%01d-%010d-%010d',
                    $exactNameMatch ? 1 : 0,
                    (int) (optional($company->latestBif)->id ?? 0),
                    (int) $company->id
                );
            })
            ->first();

        return $company?->latestBif?->bif_no;
    }

    private function applyContactBackedCifFallbacks(Contact $contact, array $cifData): array
    {
        $contactCreatedDate = optional($contact->created_at ?: $contact->business_date ?: $contact->intake_date)->toDateString() ?? now()->toDateString();
        $clientName = $this->contactPrintedName($contact);
        $creatorName = trim((string) ($contact->created_by ?: $contact->owner_name ?: $contact->referred_by ?: ''));
        $salesMarketingName = trim((string) ($contact->sales_marketing ?: $contact->owner_name ?: $creatorName));
        $contactBackedFields = [
            'cif_date' => $contactCreatedDate,
            'cif_no' => $contact->cif_no,
            'first_name' => $contact->first_name,
            'middle_name' => $contact->middle_name,
            'last_name' => $contact->last_name,
            'name_extension' => $contact->name_extension,
            'present_address_line1' => $contact->contact_address,
            'date_of_birth' => optional($contact->date_of_birth)->toDateString() ?? '',
            'gender' => $this->normalizeContactGender($contact->sex),
            'nature_of_work_business' => $contact->nature_of_business ?: ($cifData['nature_of_work_business'] ?? null),
            'tin' => $contact->tin ?? '',
            'referred_by_footer' => $creatorName,
            'referred_date' => $contactCreatedDate,
            'sales_marketing_footer' => $salesMarketingName,
            'president_footer' => self::DEFAULT_CIF_PRESIDENT_NAME,
            'email' => $contact->email,
            'mobile' => $contact->phone,
            'owner_name' => $contact->owner_name,
            'sig_name_left' => $clientName,
            'sig_position_left' => $contact->position ?: 'Client',
        ];

        foreach ($contactBackedFields as $key => $value) {
            if (filled($value)) {
                $cifData[$key] = $value;
            } elseif (array_key_exists($key, $cifData)) {
                $cifData[$key] = is_string($cifData[$key]) ? '' : null;
            }
        }

        return $cifData;
    }

    private function contactPrintedName(Contact $contact): string
    {
        return trim(implode(' ', array_filter([
            $contact->first_name,
            $contact->middle_name,
            $contact->last_name,
        ])));
    }

    private function syncContactFromCifPayload(Contact $contact, array $validated): void
    {
        $contactUpdates = $this->filterPersistableContactAttributes([
            'first_name' => $validated['first_name'] ?? $contact->first_name,
            'middle_name' => $validated['middle_name'] ?? $contact->middle_name,
            'last_name' => $validated['last_name'] ?? $contact->last_name,
            'name_extension' => $validated['name_extension'] ?? $contact->name_extension,
            'contact_address' => $validated['present_address_line1'] ?? $contact->contact_address,
            'date_of_birth' => $validated['date_of_birth'] ?? $contact->date_of_birth,
            'sex' => $validated['gender'] ?? $contact->sex,
            'nature_of_business' => $validated['nature_of_work_business'] ?? $contact->nature_of_business,
            'tin' => $validated['tin'] ?? $contact->tin,
            'email' => $validated['email'] ?? $contact->email,
            'phone' => $validated['mobile'] ?? $contact->phone,
            'owner_name' => $validated['owner_name'] ?? $contact->owner_name,
        ]);

        if ($contactUpdates !== []) {
            $contact->forceFill($contactUpdates)->save();
        }
    }

    private function cifSignedDocumentDefaults(Contact $contact): array
    {
        $cifData = $this->loadCifData($contact);

        return [
            'document_title' => 'CIF Document (Signed)',
            'cif_no' => $contact->cif_no ?: ($cifData['cif_no'] ?? ''),
            'date_created' => $cifData['cif_date'] ?? '',
            'issued_on' => $cifData['cif_document_issued_on'] ?? optional($contact->cif_form_sent_at)->toDateString() ?? '',
            'issued_by' => $cifData['cif_document_issued_by'] ?? '',
        ];
    }

    private function isKycReviewer(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->hasPermission('approve_corporate')
            || $user->hasPermission('access_admin_dashboard');
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

    private function hasApprovedChangeRequest(Contact $contact): bool
    {
        $cifData = $this->loadCifData($contact);

        return strtolower((string) ($cifData['change_request_status'] ?? '')) === 'approved';
    }

    private function isApprovedKycLockedForUser(Contact $contact, ?User $user): bool
    {
        return ! $this->isKycReviewer($user)
            && strtolower((string) ($contact->cif_status ?? '')) === 'approved'
            && ! $this->hasApprovedChangeRequest($contact);
    }

    private function abortIfApprovedKycLockedForUser(Contact $contact, ?User $user): void
    {
        abort_if(
            $this->isApprovedKycLockedForUser($contact, $user),
            403,
            'Request change information first and wait for admin approval before editing this approved KYC.'
        );
    }

    private function resetContactKycForResubmission(Contact $contact): void
    {
        $draftResetPayload = $this->filterPersistableContactAttributes([
            'cif_status' => 'pending',
            'kyc_status' => 'Pending Verification',
            'cif_submitted_at' => now(),
            'cif_reviewed_at' => null,
            'cif_reviewed_by' => null,
            'cif_rejection_reason' => null,
        ]);

        if (! empty($draftResetPayload)) {
            $contact->forceFill($draftResetPayload)->save();
        }
    }

    private function resetApprovedKycAfterModificationIfNeeded(Contact $contact, ?User $user): void
    {
        if ($this->isKycReviewer($user) || strtolower((string) ($contact->cif_status ?? '')) !== 'approved' || ! $this->hasApprovedChangeRequest($contact)) {
            return;
        }

        $this->resetContactKycForResubmission($contact);

        $cifData = $this->loadCifData($contact);
        $cifData['date_verified'] = '';
        $cifData['verified_by'] = '';
        $cifData['change_request_status'] = '';
        $cifData['change_request_note'] = '';
        $cifData['change_requested_at'] = '';
        $cifData['change_requested_by'] = '';
        $cifData['change_reviewed_at'] = '';
        $cifData['change_reviewed_by'] = '';
        $cifData['change_rejection_reason'] = '';
        $this->saveCifDataToStorage($contact, $cifData);
    }

    private function saveCifDataToStorage(Contact $contact, array $payload): void
    {
        $this->persistContactJsonPayload($contact, 'cif_data', $payload);
        Storage::disk('local')->put($this->cifDataPath($contact), json_encode($payload, JSON_PRETTY_PRINT));
        Storage::disk('local')->put($this->legacyCifDataPath($contact), json_encode($payload, JSON_PRETTY_PRINT));
    }

    private function loadCifDocuments(Contact $contact): array
    {
        $documents = $this->loadContactJsonPayload($contact, 'cif_documents', [
            $this->cifDocumentsPath($contact),
        ]);

        return collect($documents)
            ->map(fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-cif-documents') : $document)
            ->all();
    }

    private function saveCifDocumentsToStorage(Contact $contact, array $documents): void
    {
        $this->persistContactJsonPayload($contact, 'cif_documents', $documents);
        Storage::disk('local')->put($this->cifDocumentsPath($contact), json_encode($documents, JSON_PRETTY_PRINT));
    }

    private function loadKycRequirementDocuments(Contact $contact): array
    {
        $stored = $this->loadContactJsonPayload($contact, 'kyc_requirement_documents', [
            $this->kycRequirementDocumentsPath($contact),
            $this->legacyKycRequirementDocumentsPath($contact),
        ]);

        $cifDocuments = $this->loadCifDocuments($contact);
        $stored['two_valid_ids'] = array_values(array_filter(
            ! empty($stored['two_valid_ids'])
                ? (array) $stored['two_valid_ids']
                : (isset($cifDocuments['valid_id']) ? [$cifDocuments['valid_id']] : []),
            fn ($item) => is_array($item) && ! empty($item['file_name'] ?? $item['path'] ?? null)
        ));
        $stored['two_valid_ids'] = array_values(array_map(
            fn ($document) => $this->normalizeStoredDocument((array) $document, 'contact-kyc-documents'),
            $stored['two_valid_ids']
        ));
        $stored['cif_signed_document'] = array_key_exists('cif_signed_document', $stored) ? $this->normalizeNullableDocument($stored['cif_signed_document'], 'contact-kyc-documents') : ($cifDocuments['cif_document'] ?? null);
        $stored['tin_proof'] = array_values(array_filter(array_map(
            fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-kyc-documents') : null,
            $this->normalizeDocumentsArray($stored['tin_proof'] ?? ($cifDocuments['tin_document'] ?? null))
        )));
        $stored['specimen_signature_upload'] = array_values(array_filter(array_map(
            fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-kyc-documents') : null,
            $this->normalizeDocumentsArray($stored['specimen_signature_upload'] ?? ($stored['specimen_signature_form'] ?? []))
        )));
        unset($stored['specimen_signature_form']);
        $stored['passport_proof'] = array_values(array_filter(array_map(
            fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-kyc-documents') : null,
            $this->normalizeDocumentsArray($stored['passport_proof'] ?? [])
        )));
        $stored['visa_proof'] = array_values(array_filter(array_map(
            fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-kyc-documents') : null,
            $this->normalizeDocumentsArray($stored['visa_proof'] ?? [])
        )));
        $stored['acr_card_proof'] = array_values(array_filter(array_map(
            fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-kyc-documents') : null,
            $this->normalizeDocumentsArray($stored['acr_card_proof'] ?? [])
        )));
        $stored['aaep_proof'] = array_values(array_filter(array_map(
            fn ($document) => is_array($document) ? $this->normalizeStoredDocument($document, 'contact-kyc-documents') : null,
            $this->normalizeDocumentsArray($stored['aaep_proof'] ?? [])
        )));

        return $stored;
    }

    private function normalizeDocumentsArray(mixed $documents): array
    {
        if (is_array($documents) && array_is_list($documents)) {
            return array_values(array_filter($documents, fn ($item) => is_array($item)));
        }

        if (is_array($documents)) {
            return [$documents];
        }

        return [];
    }

    private function saveKycRequirementDocuments(Contact $contact, array $documents): void
    {
        $this->persistContactJsonPayload($contact, 'kyc_requirement_documents', $documents);
        Storage::disk('local')->put($this->kycRequirementDocumentsPath($contact), json_encode($documents, JSON_PRETTY_PRINT));
        Storage::disk('local')->put($this->legacyKycRequirementDocumentsPath($contact), json_encode($documents, JSON_PRETTY_PRINT));
    }

    private function loadContactJsonPayload(Contact $contact, string $attribute, array $legacyPaths = []): array
    {
        $contact->refresh();

        $embedded = $contact->getAttribute($attribute);
        if (is_array($embedded) && $embedded !== []) {
            return $embedded;
        }

        foreach ($legacyPaths as $path) {
            if (! $path || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $stored = json_decode((string) Storage::disk('local')->get($path), true);
            if (is_array($stored)) {
                return $stored;
            }
        }

        return [];
    }

    private function persistContactJsonPayload(Contact $contact, string $attribute, array $payload): void
    {
        if (! $this->contactSupportsEmbeddedKycStorage()) {
            return;
        }

        $contact->forceFill([$attribute => $payload])->save();
        $contact->refresh();
    }

    private function contactSupportsEmbeddedKycStorage(): bool
    {
        return Schema::hasColumn('contacts', 'cif_data')
            && Schema::hasColumn('contacts', 'cif_documents')
            && Schema::hasColumn('contacts', 'kyc_requirement_documents');
    }

    private function normalizeNullableDocument(mixed $document, string $defaultDirectory): ?array
    {
        if (! is_array($document)) {
            return null;
        }

        return $this->normalizeStoredDocument($document, $defaultDirectory);
    }

    private function normalizeStoredDocument(array $document, string $defaultDirectory): array
    {
        $path = $this->normalizeStoredPath($document['file_path'] ?? $document['path'] ?? null, $defaultDirectory);
        if ($path !== null) {
            $path = $this->migrateLegacyDocumentPath($path);
        }

        if ($path !== null) {
            $document['path'] = $path;
            $document['file_path'] = $path;
            $document['url'] = asset('storage/'.$path);
        } else {
            $document['path'] = null;
            $document['file_path'] = null;
            $document['url'] = '#';
        }

        return $document;
    }

    private function normalizeStoredPath(mixed $path, string $defaultDirectory): ?string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);

        if (str_contains($value, '/storage/')) {
            $value = Str::after($value, '/storage/');
        } elseif (str_starts_with($value, 'storage/')) {
            $value = Str::after($value, 'storage/');
        } elseif (str_contains($value, 'contact-kyc-documents/')) {
            $value = Str::after($value, 'contact-kyc-documents/');
            $value = 'contact-kyc-documents/'.$value;
        } elseif (str_contains($value, 'contact-cif-documents/')) {
            $value = Str::after($value, 'contact-cif-documents/');
            $value = 'contact-cif-documents/'.$value;
        } elseif (! str_contains($value, '/')) {
            $value = trim($defaultDirectory, '/').'/'.$value;
        }

        return ltrim($value, '/');
    }

    private function migrateLegacyDocumentPath(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('public')->put($path, Storage::disk('local')->get($path));
            Storage::disk('local')->delete($path);

            return $path;
        }

        return $path;
    }

    private function syncContactKycSnapshot(Contact $contact, array $overrides = []): void
    {
        $payload = array_filter([
            'cif_no' => $overrides['cif_no'] ?? ($this->loadCifData($contact)['cif_no'] ?? $contact->cif_no),
            'tin' => $overrides['tin'] ?? ($this->loadCifData($contact)['tin'] ?? $contact->tin),
        ], fn ($value) => $value !== null && $value !== '');

        if (! empty($payload)) {
            $contact->forceFill($payload)->save();
        }
    }

    private function hasCifDataChanges(array $before, array $after): bool
    {
        return $this->normalizeCifDataForComparison($before) !== $this->normalizeCifDataForComparison($after);
    }

    private function normalizeCifDataForComparison(array $payload): array
    {
        $excludedKeys = ['updated_at', 'created_at'];

        $normalize = function ($value) use (&$normalize) {
            if (is_string($value)) {
                return trim($value);
            }

            if (! is_array($value)) {
                return $value;
            }

            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $normalize($item);
            }

            ksort($normalized);

            return $normalized;
        };

        $filtered = Arr::except($payload, $excludedKeys);
        ksort($filtered);

        return $normalize($filtered);
    }

    private function canSubmitKycForVerification(Contact $contact): bool
    {
        return $this->missingKycRequirementLabelsForSubmission($contact) === [];
    }

    private function missingKycRequirementLabelsForSubmission(Contact $contact): array
    {
        $specimenSignature = SpecimenSignature::query()->where('contact_id', $contact->id)->first();
        $state = $this->kycRequirementState($contact, $specimenSignature);
        $requiredKeys = $this->requiredKycRequirementKeysForContact($contact);

        $missing = [];
        foreach ($requiredKeys as $key) {
            if (! (($state[$key]['complete'] ?? false) === true)) {
                $missing[] = self::KYC_REQUIREMENT_LABELS[$key] ?? Str::headline((string) $key);
            }
        }

        return $missing;
    }

    private function requiredKycRequirementKeysForContact(Contact $contact): array
    {
        $required = self::BASE_KYC_REQUIREMENT_KEYS;
        if ($this->requiresForeignerKycDocuments($contact)) {
            $required = [...$required, ...self::FOREIGNER_KYC_REQUIREMENT_KEYS];
        }

        return $required;
    }

    private function requiresForeignerKycDocuments(Contact $contact): bool
    {
        $cifData = $this->loadCifData($contact);
        $citizenshipType = strtolower((string) ($cifData['citizenship_type'] ?? ''));

        return in_array($citizenshipType, ['foreigner', 'dual_citizen'], true);
    }

    private function kycRequirementDocumentsPath(Contact $contact): string
    {
        return 'contact-kyc-data/'.$contact->id.'-requirements.json';
    }

    private function legacyKycRequirementDocumentsPath(Contact $contact): string
    {
        return 'contact-cif-data/'.$contact->id.'-kyc-requirements.json';
    }

    private function kycRequirementState(Contact $contact, ?SpecimenSignature $specimenSignature): array
    {
        $documents = $this->loadKycRequirementDocuments($contact);
        $cifSignedDocument = is_array($documents['cif_signed_document'] ?? null) ? $documents['cif_signed_document'] : null;
        $twoValidIds = array_values(array_filter((array) ($documents['two_valid_ids'] ?? []), fn ($item) => is_array($item)));
        $tinProofFiles = array_values(array_filter((array) ($documents['tin_proof'] ?? []), fn ($item) => is_array($item)));
        $specimenUploadFiles = array_values(array_filter((array) ($documents['specimen_signature_upload'] ?? []), fn ($item) => is_array($item)));
        $passportProofFiles = array_values(array_filter((array) ($documents['passport_proof'] ?? []), fn ($item) => is_array($item)));
        $visaProofFiles = array_values(array_filter((array) ($documents['visa_proof'] ?? []), fn ($item) => is_array($item)));
        $acrCardProofFiles = array_values(array_filter((array) ($documents['acr_card_proof'] ?? []), fn ($item) => is_array($item)));
        $aaepProofFiles = array_values(array_filter((array) ($documents['aaep_proof'] ?? []), fn ($item) => is_array($item)));
        $specimenFormExists = $specimenSignature !== null;

        return [
            'cif_signed_document' => [
                'file' => $cifSignedDocument,
                'complete' => $cifSignedDocument !== null,
            ],
            'two_valid_ids' => [
                'count' => count($twoValidIds),
                'files' => $twoValidIds,
                'complete' => count($twoValidIds) >= 2,
            ],
            'specimen_signature_form' => [
                'form_exists' => $specimenFormExists,
                'file' => $specimenUploadFiles[0] ?? null,
                'files' => $specimenUploadFiles,
                'complete' => $specimenFormExists || count($specimenUploadFiles) > 0,
            ],
            'tin_proof' => [
                'file' => $tinProofFiles[0] ?? null,
                'files' => $tinProofFiles,
                'complete' => count($tinProofFiles) > 0,
            ],
            'passport_proof' => [
                'file' => $passportProofFiles[0] ?? null,
                'files' => $passportProofFiles,
                'complete' => count($passportProofFiles) > 0,
            ],
            'visa_proof' => [
                'file' => $visaProofFiles[0] ?? null,
                'files' => $visaProofFiles,
                'complete' => count($visaProofFiles) > 0,
            ],
            'acr_card_proof' => [
                'file' => $acrCardProofFiles[0] ?? null,
                'files' => $acrCardProofFiles,
                'complete' => count($acrCardProofFiles) > 0,
            ],
            'aaep_proof' => [
                'file' => $aaepProofFiles[0] ?? null,
                'files' => $aaepProofFiles,
                'complete' => count($aaepProofFiles) > 0,
            ],
        ];
    }

    private function normalizeKycRequirementKey(string $requirement): string
    {
        $normalized = trim($requirement);

        return match ($normalized) {
            'specimen_signature_form' => 'specimen_signature_upload',
            default => $normalized,
        };
    }

    private function hasCifFormErrors(Request $request): bool
    {
        $errors = $request->session()->get('errors');
        if (! $errors instanceof \Illuminate\Support\ViewErrorBag) {
            return false;
        }

        $defaultBag = $errors->getBag('default');
        if ($defaultBag->isEmpty()) {
            return false;
        }

        $cifFieldNames = [
            'cif_date',
            'cif_no',
            'is_new_client',
            'is_existing_client',
            'is_change_information',
            'first_name',
            'last_name',
            'name_extension',
            'middle_name',
            'no_middle_name',
            'only_first_name',
            'present_address_line1',
            'zip_code',
            'present_address_line2',
            'email',
            'mobile',
            'date_of_birth',
            'place_of_birth',
            'citizenship_nationality',
            'citizenship_type',
            'gender',
            'civil_status',
            'spouse_name',
            'nature_of_work_business',
            'tin',
            'other_government_id',
            'id_number',
            'mothers_maiden_name',
            'source_of_funds',
            'source_of_funds_other_text',
            'foreigner_passport_no',
            'foreigner_passport_expiry_date',
            'foreigner_passport_place_of_issue',
            'foreigner_acr_id_no',
            'foreigner_acr_expiry_date',
            'foreigner_acr_place_of_issue',
            'visa_status',
            'onboarding_two_valid_ids',
            'onboarding_tin_id',
            'onboarding_authorized_signatory_card',
            'referred_by_footer',
            'referred_date',
            'sales_marketing_footer',
            'finance_footer',
            'president_footer',
            'sig_name_left',
            'sig_position_left',
            'sig_name_right',
            'sig_position_right',
        ];

        return count(array_intersect(array_keys($defaultBag->messages()), $cifFieldNames)) > 0;
    }

    private function cifDataPath(Contact $contact): string
    {
        return 'contact-cif-data/'.$contact->id.'.json';
    }

    private function legacyCifDataPath(Contact $contact): string
    {
        return 'contact-cif-data/'.$contact->id.'-data.json';
    }

    private function cifDocumentsPath(Contact $contact): string
    {
        return 'contact-cif-data/'.$contact->id.'-documents.json';
    }

    private function cifDocumentTypes(): array
    {
        return [
            'cif_document' => 'CIF Document',
            'valid_id' => 'Valid ID',
            'tin_document' => 'TIN Document',
            'registration_document' => 'Supporting Registration Document',
            'other' => 'Other Related File',
        ];
    }

    private function specimenFormData(Contact $contact, ?SpecimenSignature $specimenSignature): array
    {
        $cifData = $this->loadCifData($contact);
        $authenticationData = (array) ($specimenSignature?->authentication_data ?? []);
        $signatories = collect((array) ($specimenSignature?->signatories ?? []))
            ->map(fn ($entry) => is_array($entry) ? $entry : ['name' => null, 'signature' => null])
            ->pad(6, ['name' => null, 'signature' => null])
            ->take(6)
            ->values()
            ->all();
        $contactDisplayName = trim(implode(' ', array_filter([
            $contact->first_name,
            $contact->middle_name,
            $contact->last_name,
        ])));
        $defaultCifNo = (string) ($cifData['cif_no'] ?? ($contact->cif_no ?: ($contact->id ? $this->generateCifNumber($contact) : '')));
        $defaultCifDate = (string) ($cifData['cif_date'] ?? '');
        $resolvedBusinessBifNo = $this->resolvedBusinessBifNumber($contact);

        return [
            'date' => old('date', optional($specimenSignature?->date)->toDateString() ?? now()->toDateString()),
            'bif_no' => old(
                'bif_no',
                $this->shouldUseBusinessBifNumber($contact)
                    ? ($resolvedBusinessBifNo ?? $specimenSignature?->bif_no ?? '')
                    : ($specimenSignature?->bif_no ?? '')
            ),
            'client_type' => old('client_type', $specimenSignature?->client_type ?? 'new'),
            'business_name_left' => old('business_name_left', $specimenSignature?->business_name_left ?? $contact->company_name ?? ''),
            'business_name_right' => old('business_name_right', $specimenSignature?->business_name_right ?? $contact->company_name ?? ''),
            'account_number_left' => old('account_number_left', $specimenSignature?->account_number_left ?? ''),
            'account_number_right' => old('account_number_right', $specimenSignature?->account_number_right ?? ''),
            'signature_combination' => old('signature_combination', (string) ($authenticationData['signature_combination'] ?? '')),
            'signature_class' => old('signature_class', (string) ($authenticationData['signature_class'] ?? '')),
            'left_client_name' => old('left_client_name', (string) ($authenticationData['left_client_name'] ?? $contactDisplayName)),
            'left_cif_no' => old('left_cif_no', (string) ($authenticationData['left_cif_no'] ?? $defaultCifNo)),
            'left_cif_dated' => old('left_cif_dated', (string) ($authenticationData['left_cif_dated'] ?? $defaultCifDate)),
            'right_client_name' => old('right_client_name', (string) ($authenticationData['right_client_name'] ?? $contactDisplayName)),
            'right_cif_no' => old('right_cif_no', (string) ($authenticationData['right_cif_no'] ?? $defaultCifNo)),
            'right_cif_dated' => old('right_cif_dated', (string) ($authenticationData['right_cif_dated'] ?? $defaultCifDate)),
            'signatories' => old('signatory_names', collect($signatories)->pluck('name')->all()),
            'authenticated_by' => old('authenticated_by', (string) ($authenticationData['authenticated_by'] ?? '')),
            'board_resolution_spa_no' => old('board_resolution_spa_no', (string) ($authenticationData['board_resolution_spa_no'] ?? '')),
            'board_resolution_spa_date' => old('board_resolution_spa_date', (string) ($authenticationData['board_resolution_spa_date'] ?? '')),
            'signature_over_printed_name' => old('signature_over_printed_name', (string) ($authenticationData['signature_over_printed_name'] ?? '')),
            'authorized_signatory_signature' => old('authorized_signatory_signature', (string) ($authenticationData['authorized_signatory_signature'] ?? '')),
            'authorized_signatory_date' => old('authorized_signatory_date', (string) ($authenticationData['authorized_signatory_date'] ?? '')),
            'remarks' => old('remarks', $specimenSignature?->remarks ?? ''),
            'processing_instruction' => old('processing_instruction', (string) ($authenticationData['processing_instruction'] ?? 'The officer processing this document must verify the authorized signatory/ies, obtain a copy of the Board Resolution, Secretary\'s Certificate, or Special Power of Attorney (SPA) if applicable, and ensure the document number.')),
            'sales_marketing' => old('sales_marketing', (string) ($authenticationData['sales_marketing'] ?? '')),
            'processed_by' => old('processed_by', (string) ($authenticationData['processed_by'] ?? '')),
            'processed_date' => old('processed_date', (string) ($authenticationData['processed_date'] ?? '')),
            'finance' => old('finance', (string) ($authenticationData['finance'] ?? '')),
            'scanned_by' => old('scanned_by', (string) ($authenticationData['scanned_by'] ?? '')),
            'scanned_date' => old('scanned_date', (string) ($authenticationData['scanned_date'] ?? '')),
            'created_by' => $specimenSignature?->created_by ?? ($contact->owner_name ?: 'Admin User'),
        ];
    }

    private function normalizeContactGender(?string $value): string
    {
        $normalized = Str::lower(trim((string) $value));

        return match ($normalized) {
            'male' => 'male',
            'female' => 'female',
            default => '',
        };
    }

    private function normalizeMoneyValue(mixed $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        $normalized = str_replace(',', '', (string) $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function generateCifNumber(Contact $contact): string
    {
        return 'CIF-'.now()->format('Ymd').'-'.str_pad((string) $contact->id, 4, '0', STR_PAD_LEFT);
    }

    private function filterPersistableContactAttributes(array $attributes): array
    {
        static $contactColumns = null;

        if (! Schema::hasTable('contacts')) {
            return [];
        }

        if ($contactColumns === null) {
            $contactColumns = array_flip(Schema::getColumnListing('contacts'));
        }

        return collect($attributes)
            ->filter(fn ($_value, $key) => isset($contactColumns[$key]))
            ->all();
    }

    private function tabData(Contact $contact, User $user): array
    {
        $deals = $this->linkedDealsForContact($contact);
        $projects = $this->linkedProjectsForContact($contact);
        $regular = $this->linkedRegularsForContact($contact);
        $products = $this->linkedProductsForContact($contact);
        $services = $this->linkedServicesForContact($contact);

        return [
            'history' => [
                'filters' => ['All Activities', 'Profile Changes', 'KYC Updates', 'Deals', 'Files', 'Notes'],
                'items' => $this->contactHistoryItems($contact),
            ],
            'consultation-notes' => $this->contactConsultationNotes($contact),
            'activities' => $this->contactActivities($contact, $user),
            'deals' => $deals,
            'projects' => $projects,
            'regular' => $regular,
            'products' => $products,
            'services' => $services,
        ];

        $deals = [
            [
                'name' => 'Corporate Software License',
                'stage' => 'Negotiation',
                'amount' => '₱250,000',
                'closing_date' => 'Mar 15, 2026',
                'owner' => 'John Admin',
                'status' => 'Open',
            ],
            [
                'name' => 'Security Audit Package',
                'stage' => 'Proposal',
                'amount' => '₱120,000',
                'closing_date' => 'Apr 02, 2026',
                'owner' => 'Maria Santos',
                'status' => 'Pending',
            ],
            [
                'name' => 'Cloud Migration Retainer',
                'stage' => 'Qualification',
                'amount' => '₱340,000',
                'closing_date' => 'Apr 20, 2026',
                'owner' => 'AdminUser',
                'status' => 'Open',
            ],
            [
                'name' => 'Website Redesign Project',
                'stage' => 'Closed Lost',
                'amount' => '₱180,000',
                'closing_date' => 'Feb 20, 2026',
                'owner' => 'Admin User',
                'status' => 'Lost',
            ],
        ];

        if ((int) $contact->id === 101) {
            array_unshift($deals, [
                'name' => 'Tax Advisory Compliance Audit Regular Retainer',
                'stage' => 'Inquiry',
                'amount' => '₱920,000',
                'closing_date' => 'Jun 10, 2026',
                'owner' => 'Admin User',
                'status' => 'Open',
            ]);
        }

        $deals = $this->linkedDealsForContact($contact);
        $projects = $this->linkedProjectsForContact($contact);
        $regular = $this->linkedRegularsForContact($contact);
        $products = $this->linkedProductsForContact($contact);
        $services = $this->linkedServicesForContact($contact);

        return [
            'history' => [
                'filters' => ['All Activities', 'Profile Changes', 'KYC Updates', 'Deals', 'Files', 'Notes'],
                'items' => [
                    [
                        'id' => 1,
                        'type' => 'deals',
                        'icon' => 'fa-arrow-trend-up',
                        'title' => 'Deal linked to contact',
                        'description' => "Deal 'Corporate Software License' linked to contact",
                        'extraLabel' => 'Deal',
                        'extraValue' => 'Corporate Software License',
                        'user' => 'John Admin',
                        'initials' => 'JA',
                        'datetime' => 'Mar 1, 2026, 02:30 PM',
                    ],
                    [
                        'id' => 2,
                        'type' => 'deals',
                        'icon' => 'fa-arrow-trend-up',
                        'title' => 'Deal stage changed',
                        'description' => "Deal 'Corporate Software License' stage changed from Proposal to Negotiation",
                        'extraLabel' => 'Deal',
                        'extraValue' => 'Corporate Software License',
                        'user' => 'John Admin',
                        'initials' => 'JA',
                        'datetime' => 'Feb 28, 2026, 11:15 AM',
                    ],
                    [
                        'id' => 3,
                        'type' => 'notes',
                        'icon' => 'fa-note-sticky',
                        'title' => 'Note added to contact',
                        'description' => 'Added consultation note regarding software requirements',
                        'extraLabel' => 'Note',
                        'extraValue' => '"Discussed enterprise software licensing options and support packages"',
                        'user' => 'Maria Santos',
                        'initials' => 'MS',
                        'datetime' => 'Feb 26, 2026, 04:45 PM',
                    ],
                    [
                        'id' => 4,
                        'type' => 'profile',
                        'icon' => 'fa-pen',
                        'title' => 'Phone number updated',
                        'description' => 'Phone number changed',
                        'extraLabel' => 'Phone',
                        'extraValue' => '+63 917 123 4567',
                        'user' => 'Juan Dela Cruz',
                        'initials' => 'JD',
                        'datetime' => 'Feb 15, 2026, 04:20 PM',
                    ],
                    [
                        'id' => 5,
                        'type' => 'kyc',
                        'icon' => 'fa-shield-check',
                        'title' => 'KYC status updated',
                        'description' => 'KYC moved from Not Submitted to Pending Verification',
                        'extraLabel' => 'KYC',
                        'extraValue' => 'Pending Verification',
                        'user' => $owner,
                        'initials' => 'JA',
                        'datetime' => 'Feb 13, 2026, 10:20 AM',
                    ],
                    [
                        'id' => 6,
                        'type' => 'files',
                        'icon' => 'fa-file-arrow-up',
                        'title' => 'File attached',
                        'description' => 'Uploaded signed requirements document',
                        'extraLabel' => 'File',
                        'extraValue' => 'Requirements_Signed.pdf',
                        'user' => 'Maria Santos',
                        'initials' => 'MS',
                        'datetime' => 'Feb 12, 2026, 03:12 PM',
                    ],
                    [
                        'id' => 7,
                        'type' => 'profile',
                        'icon' => 'fa-user-plus',
                        'title' => 'Contact created',
                        'description' => 'Contact record created in the system',
                        'extraLabel' => 'Profile',
                        'extraValue' => 'New contact record added',
                        'user' => $owner,
                        'initials' => 'JA',
                        'datetime' => 'Feb 10, 2026, 09:00 AM',
                    ],
                ],
            ],
            'consultation-notes' => [
                [
                    'id' => 1,
                    'title' => 'Initial Consultation - Software Requirements',
                    'consultationDate' => '2026-03-02',
                    'author' => 'Maria Santos',
                    'summary' => 'Discussed enterprise software licensing options, support packages, and implementation timeline.',
                    'details' => 'Client requested a phased rollout, training bundle, and SLA-based support package. They prefer annual billing with quarterly review sessions and requested revised pricing by next week.',
                    'category' => 'Software Consultation',
                    'attachments' => [
                        [
                            'id' => 101,
                            'name' => 'Requirements_Checklist.pdf',
                            'type' => 'PDF',
                            'size' => 182344,
                            'url' => '#',
                        ],
                        [
                            'id' => 102,
                            'name' => 'Discovery_Notes.docx',
                            'type' => 'DOCX',
                            'size' => 93211,
                            'url' => '#',
                        ],
                    ],
                    'createdAt' => '2026-03-02T10:20:00',
                    'updatedAt' => '2026-03-02T10:20:00',
                ],
                [
                    'id' => 2,
                    'title' => 'Follow-up Meeting - Budget Planning',
                    'consultationDate' => '2026-02-26',
                    'author' => $owner,
                    'summary' => 'Reviewed budget allocation for Q2 software implementation and training requirements.',
                    'details' => 'Budget approved for initial deployment and user onboarding. Security audit to be scoped in a separate proposal. Finance team requested milestone-based invoicing.',
                    'category' => 'Budget Review',
                    'attachments' => [
                        [
                            'id' => 201,
                            'name' => 'Budget_Allocation_Q2.xlsx',
                            'type' => 'XLSX',
                            'size' => 120304,
                            'url' => '#',
                        ],
                    ],
                    'createdAt' => '2026-02-26T14:30:00',
                    'updatedAt' => '2026-02-26T14:30:00',
                ],
            ],
            'activities' => $linkedActivities ?: [
                [
                    'type' => 'Call',
                    'icon' => 'fa-phone',
                    'description' => 'Follow-up call regarding software implementation timeline',
                    'when' => 'Mar 03, 2026 02:30 PM',
                    'owner' => $owner,
                    'status' => 'Completed',
                ],
                [
                    'type' => 'Meeting',
                    'icon' => 'fa-video',
                    'description' => 'Quarterly review meeting with stakeholders',
                    'when' => 'Mar 01, 2026 10:00 AM',
                    'owner' => 'Maria Santos',
                    'status' => 'Completed',
                ],
                [
                    'type' => 'Email',
                    'icon' => 'fa-envelope',
                    'description' => 'Sent proposal document and pricing breakdown',
                    'when' => 'Feb 28, 2026 04:15 PM',
                    'owner' => $owner,
                    'status' => 'Sent',
                ],
                [
                    'type' => 'Task',
                    'icon' => 'fa-square-check',
                    'description' => 'Prepare contract documents for review',
                    'when' => 'Feb 27, 2026 11:00 AM',
                    'owner' => $owner,
                    'status' => 'Pending',
                ],
            ],
            'deals' => $deals,
            'projects' => $projects,
            'regular' => $regular,
            'products' => $products,
            'services' => $services,
        ];
    }

    private function contactHistoryItems(Contact $contact): array
    {
        if (Schema::hasTable('contact_history_entries')) {
            $history = ContactHistoryEntry::query()
                ->where('contact_id', $contact->id)
                ->latest('occurred_at')
                ->get()
                ->map(function (ContactHistoryEntry $entry): array {
                    return [
                        'id' => $entry->id,
                        'type' => $entry->type,
                        'icon' => $this->historyIconForType($entry->type),
                        'title' => $entry->title,
                        'description' => $entry->description,
                        'extraLabel' => $entry->extra_label,
                        'extraValue' => $entry->extra_value,
                        'user' => $entry->user_name,
                        'initials' => $entry->user_initials,
                        'datetime' => optional($entry->occurred_at)->format('M j, Y, h:i A'),
                    ];
                })
                ->all();

            if (! empty($history)) {
                return $history;
            }
        }

        $owner = $contact->created_by ?: ($contact->owner_name ?: 'System');

        return [[
            'id' => 'contact-created-'.$contact->id,
            'type' => 'profile',
            'icon' => 'fa-user-plus',
            'title' => 'Contact created',
            'description' => 'Contact record created in the system',
            'extraLabel' => 'Profile',
            'extraValue' => trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: 'New contact record added',
            'user' => $owner,
            'initials' => $this->initialsForHistory($owner),
            'datetime' => optional($contact->created_at)->format('M j, Y, h:i A'),
        ]];
    }

    private function contactConsultationNotes(Contact $contact): array
    {
        if (! Schema::hasTable('contact_consultation_notes')) {
            return [];
        }

        return ContactConsultationNote::query()
            ->where('contact_id', $contact->id)
            ->latest('consultation_date')
            ->latest('updated_at')
            ->get()
            ->map(function (ContactConsultationNote $note): array {
                return [
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
                ];
            })
            ->all();
    }

    private function contactActivities(Contact $contact, User $user): array
    {
        return collect(app(ActivityTimelineBuilder::class)->forContact($contact, $user))
            ->map(function (array $activity): array {
                $showUrl = route('activities');

                if (! empty($activity['id'])) {
                    $showUrl .= '#'.$activity['type'].'-'.$activity['id'];
                }

                return [
                    ...$activity,
                    'show_url' => $showUrl,
                ];
            })
            ->values()
            ->all();
    }

    private function historyIconForType(?string $type): string
    {
        return match (Str::lower((string) $type)) {
            'deals' => 'fa-arrow-trend-up',
            'notes' => 'fa-note-sticky',
            'kyc' => 'fa-shield-check',
            'files' => 'fa-file-arrow-up',
            default => 'fa-pen',
        };
    }

    private function initialsForHistory(?string $name): string
    {
        $initials = collect(explode(' ', trim((string) $name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'NA';
    }

    private function linkedDealsForContact(Contact $contact): array
    {
        if (! Schema::hasTable('deals')) {
            return [];
        }

        return Deal::query()
            ->where('contact_id', $contact->id)
            ->latest('updated_at')
            ->get()
            ->map(function (Deal $deal): array {
                $stage = trim((string) ($deal->stage ?: 'Qualification'));

                return [
                    'id' => $deal->id,
                    'name' => $deal->deal_name ?: ($deal->deal_code ?: 'Untitled deal'),
                    'stage' => $stage,
                    'amount' => 'P'.number_format((float) ($deal->total_estimated_engagement_value ?? 0), 2),
                    'closing_date' => optional($deal->confirmed_delivery_date ?: $deal->estimated_completion_date ?: $deal->client_preferred_completion_date)->format('M d, Y') ?: '-',
                    'owner' => $deal->assigned_consultant ?: $deal->lead_consultant ?: $deal->created_by ?: 'Unassigned',
                    'status' => in_array($stage, ['Won', 'Lost', 'Closed Lost'], true) ? ($stage === 'Won' ? 'Won' : 'Lost') : 'Open',
                    'show_url' => route('deals.show', $deal->id),
                ];
            })
            ->values()
            ->all();
    }

    private function linkedProjectsForContact(Contact $contact): array
    {
        if (! Schema::hasTable('projects')) {
            return [];
        }

        return Project::query()
            ->where('contact_id', $contact->id)
            ->where(function ($query) {
                $query
                    ->whereNull('engagement_type')
                    ->orWhereRaw('LOWER(COALESCE(engagement_type, "")) NOT LIKE ?', ['%regular%']);
            })
            ->latest('updated_at')
            ->get()
            ->map(function (Project $project): array {
                $owner = $project->assigned_project_manager ?: $project->assigned_consultant ?: 'Unassigned';

                return [
                    'id' => $project->id,
                    'name' => $project->name ?: ($project->project_code ?: 'Untitled project'),
                    'status' => $project->status ?: 'Open',
                    'start_date' => optional($project->planned_start_date)->format('M d, Y') ?: '-',
                    'end_date' => optional($project->target_completion_date)->format('M d, Y') ?: '-',
                    'owner' => $owner,
                    'owner_initials' => $this->initialsFromName($owner),
                    'show_url' => route('project.show', $project->id),
                ];
            })
            ->values()
            ->all();
    }

    private function linkedRegularsForContact(Contact $contact): array
    {
        if (! Schema::hasTable('projects')) {
            return [];
        }

        return Project::query()
            ->where('contact_id', $contact->id)
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
                    'owner_initials' => $this->initialsFromName($owner),
                    'show_url' => route('regular.show', $project->id),
                ];
            })
            ->values()
            ->all();
    }

    private function initialsFromName(?string $name): string
    {
        $initials = collect(explode(' ', trim((string) $name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'NA';
    }

    private function linkedProductsForContact(Contact $contact): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('deals')) {
            return [
                'items' => [],
                'grand_total' => 'P0.00',
                'total_products' => 0,
                'total_quantity' => 0,
                'total_revenue' => 'P0.00',
            ];
        }

        $dealIds = Deal::query()
            ->where('contact_id', $contact->id)
            ->pluck('id');

        $items = Product::query()
            ->whereIn('deal_id', $dealIds)
            ->latest('updated_at')
            ->get()
            ->map(function (Product $product): array {
                $price = (float) ($product->price ?? 0);
                $quantity = 1;

                return [
                    'id' => $product->id,
                    'name' => $product->product_name ?: ($product->product_id ?: 'Unnamed product'),
                    'sku' => $product->sku ?: '-',
                    'category' => $product->category ?: 'General',
                    'price' => 'P'.number_format($price, 2),
                    'pricing_type' => $product->pricing_type ?: 'One-Time',
                    'status' => $product->status ?: 'Open',
                    'linked_at' => optional($product->updated_at)->format('M d, Y h:i A') ?: '-',
                    'owner' => $product->owner_name ?: '-',
                    'quantity' => (string) $quantity,
                    'total' => 'P'.number_format($price * $quantity, 2),
                    'show_url' => route('products.show', $product->product_id),
                ];
            })
            ->values();

        $totalRevenue = $items->sum(function (array $item) {
            return (float) str_replace([',', 'P'], '', $item['total']);
        });

        return [
            'items' => $items->all(),
            'grand_total' => 'P'.number_format($totalRevenue, 2),
            'total_products' => $items->count(),
            'total_quantity' => $items->count(),
            'total_revenue' => 'P'.number_format($totalRevenue, 2),
        ];
    }

    private function linkedServicesForContact(Contact $contact): array
    {
        if (! Schema::hasTable('services')) {
            return ['items' => [], 'total_services' => 0, 'completed' => 0, 'total_value' => 'P0.00'];
        }

        $relatedCompanies = collect($this->relatedCompaniesForContact($contact))
            ->pluck('id')
            ->filter()
            ->values();

        $items = Service::query()
            ->when($relatedCompanies->isNotEmpty(), fn ($query) => $query->whereIn('company_id', $relatedCompanies->all()))
            ->when($relatedCompanies->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('updated_at')
            ->get()
            ->map(function (Service $service): array {
                $assignedStaff = $service->assigned_unit ?: '-';
                $priceRate = '-';

                if ($service->rate_per_unit) {
                    $priceRate = number_format((float) $service->rate_per_unit, 2).' / '.($service->unit ?: 'Unit');
                } elseif ($service->price_fee) {
                    $priceRate = number_format((float) $service->price_fee, 2);
                }

                return [
                    'id' => $service->id,
                    'name' => $service->service_name ?: ($service->service_id ?: 'Unnamed service'),
                    'category' => $service->category ?: '-',
                    'frequency' => $service->frequency ?: '-',
                    'engagement_type' => implode(', ', $service->engagement_structure ?? []) ?: '-',
                    'price_rate' => $priceRate,
                    'assigned_unit' => $assignedStaff,
                    'status' => $service->status ?: 'Open',
                    'owner' => $service->reviewed_by ?: $service->approved_by ?: $service->created_by ?: 'Unassigned',
                    'show_url' => route('services.show', $service->id),
                ];
            })
            ->values();

        $totalValue = $items->sum(function (array $item) {
            return (float) str_replace([',', 'P'], '', $item['price_rate']);
        });

        return [
            'items' => $items->all(),
            'total_services' => $items->count(),
            'completed' => $items->where('status', 'Completed')->count(),
            'total_value' => 'P'.number_format($totalValue, 2),
        ];
    }

    private function buildKycActivityLogs(Contact $contact): array
    {
        $actor = trim((string) ($contact->created_by ?: $contact->owner_name ?: 'Admin User'));

        return [[
            'message' => "KYC profile loaded by {$actor}",
            'timestamp' => optional($contact->created_at)->format('F d, Y • h:i A') ?: null,
        ]];
    }

    private function buildKycActivityLogsWithAudit(Contact $contact): array
    {
        $logs = $this->buildKycActivityLogs($contact);

        if (filled($contact->cif_submitted_at ?? null)) {
            $logs[] = [
                'message' => 'CIF submitted for verification',
                'timestamp' => optional($contact->cif_submitted_at)->format('F d, Y â€¢ h:i A'),
            ];
        }

        if (filled($contact->cif_reviewed_at ?? null) && filled($contact->cif_reviewed_by ?? null)) {
            $decision = strtolower((string) ($contact->cif_status ?? '')) === 'rejected' ? 'rejected' : 'approved';
            $logs[] = [
                'message' => "CIF {$decision} by {$contact->cif_reviewed_by}",
                'timestamp' => optional($contact->cif_reviewed_at)->format('F d, Y â€¢ h:i A'),
            ];
        }

        return $logs;
    }

    private function findDuplicateContact(string $firstName, ?string $lastName, ?string $mobileNumber, ?int $ignoreId = null): ?Contact
    {
        $normalizedFirstName = Str::lower(trim($firstName));
        $normalizedLastName = Str::lower(trim((string) $lastName));
        $normalizedPhone = $this->normalizeContactNumber($mobileNumber);

        if ($normalizedFirstName === '' || $normalizedPhone === '') {
            return null;
        }

        return Contact::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get()
            ->first(function (Contact $contact) use ($normalizedFirstName, $normalizedLastName, $normalizedPhone): bool {
                return Str::lower(trim((string) $contact->first_name)) === $normalizedFirstName
                    && Str::lower(trim((string) $contact->last_name)) === $normalizedLastName
                    && $this->normalizeContactNumber($contact->phone) === $normalizedPhone;
            });
    }

    private function normalizeContactNumber(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function applyContactSearchFilter($query, string $search): void
    {
        $query->where(function ($builder) use ($search) {
            $builder
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$search}%"])
                ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, '')) LIKE ?", ["%{$search}%"])
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('position', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('contact_address', 'like', "%{$search}%")
                ->orWhere('company_address', 'like', "%{$search}%")
                ->orWhere('owner_name', 'like', "%{$search}%");
        });
    }

    private function findContactByClientToken(string $type, string $token): Contact
    {
        $column = $type === 'specimen' ? 'specimen_access_token' : 'cif_access_token';
        $normalizedToken = trim(rawurldecode($token));

        abort_if($normalizedToken === '', 404);

        return Contact::query()->where($column, $normalizedToken)->firstOrFail();
    }

    private function validatedCifPayload(Request $request): array
    {
        $validated = $request->validate([
            'cif_date' => ['nullable', 'date'],
            'cif_no' => ['nullable', 'string', 'max:100'],
            'is_new_client' => ['nullable', 'boolean'],
            'is_existing_client' => ['nullable', 'boolean'],
            'is_change_information' => ['nullable', 'boolean'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'no_middle_name' => ['nullable', 'boolean'],
            'only_first_name' => ['nullable', 'boolean'],
            'present_address_line1' => ['nullable', 'string', 'max:255'],
            'present_address_line2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'citizenship_nationality' => ['nullable', 'string', 'max:150'],
            'citizenship_type' => ['nullable', 'in:filipino,foreigner,dual_citizen'],
            'gender' => ['nullable', 'in:male,female'],
            'civil_status' => ['nullable', 'in:single,separated,widowed,married'],
            'spouse_name' => ['nullable', 'string', 'max:150'],
            'nature_of_work_business' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:100'],
            'other_government_id' => ['nullable', 'string', 'max:150'],
            'id_number' => ['nullable', 'string', 'max:150'],
            'mothers_maiden_name' => ['nullable', 'string', 'max:150'],
            'source_of_funds' => ['nullable', 'array'],
            'source_of_funds.*' => ['string', 'in:salary,remittance,business,others,commission_fees,retirement_pension'],
            'source_of_funds_other_text' => ['nullable', 'string', 'max:150'],
            'foreigner_passport_no' => ['nullable', 'string', 'max:150'],
            'foreigner_passport_expiry_date' => ['nullable', 'date'],
            'foreigner_passport_place_of_issue' => ['nullable', 'string', 'max:150'],
            'foreigner_acr_id_no' => ['nullable', 'string', 'max:150'],
            'foreigner_acr_expiry_date' => ['nullable', 'date'],
            'foreigner_acr_place_of_issue' => ['nullable', 'string', 'max:150'],
            'visa_status' => ['nullable', 'string', 'max:150'],
            'onboarding_two_valid_ids' => ['nullable', 'boolean'],
            'onboarding_tin_id' => ['nullable', 'boolean'],
            'onboarding_authorized_signatory_card' => ['nullable', 'boolean'],
            'referred_by_footer' => ['nullable', 'string', 'max:150'],
            'referred_date' => ['nullable', 'date'],
            'sales_marketing_footer' => ['nullable', 'string', 'max:150'],
            'finance_footer' => ['nullable', 'string', 'max:150'],
            'president_footer' => ['nullable', 'string', 'max:150'],
            'sig_name_left' => ['nullable', 'string', 'max:150'],
            'sig_position_left' => ['nullable', 'string', 'max:150'],
            'sig_name_right' => ['nullable', 'string', 'max:150'],
            'sig_position_right' => ['nullable', 'string', 'max:150'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'kyc_status' => ['nullable', 'string', 'max:100'],
            'date_verified' => ['nullable', 'date'],
            'verified_by' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
        ]);

        $citizenshipType = strtolower((string) ($validated['citizenship_type'] ?? ''));

        if ($citizenshipType === 'filipino') {
            $validated['citizenship_nationality'] = 'FILIPINO';
        } else {
            $validated['citizenship_nationality'] = strtoupper(trim((string) ($validated['citizenship_nationality'] ?? '')));
        }

        if (($validated['civil_status'] ?? '') !== 'married') {
            $validated['spouse_name'] = '';
        }

        return $validated;
    }

    private function storeClientSubmittedKycDocuments(Request $request, Contact $contact): void
    {
        $request->validate([
            'cif_signed_document_upload' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'two_valid_ids_uploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'tin_proof_uploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'passport_proof_uploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'visa_proof_uploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'acr_card_proof_uploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aaep_proof_uploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $documents = $this->loadKycRequirementDocuments($contact);
        $uploadedBy = trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: 'Client';

        $signedCifUpload = $request->file('cif_signed_document_upload');
        if ($signedCifUpload) {
            $existingSignedCif = is_array($documents['cif_signed_document'] ?? null) ? $documents['cif_signed_document'] : null;
            $existingPath = $existingSignedCif['file_path'] ?? $existingSignedCif['path'] ?? null;
            $cifDocumentDefaults = $this->cifSignedDocumentDefaults($contact);

            if ($existingPath && Storage::disk('public')->exists($existingPath)) {
                Storage::disk('public')->delete($existingPath);
            }

            $storedPath = $signedCifUpload->store('contact-kyc-documents', 'public');
            $documents['cif_signed_document'] = [
                'path' => $storedPath,
                'file_path' => $storedPath,
                'file_name' => $signedCifUpload->getClientOriginalName(),
                'mime_type' => $signedCifUpload->getMimeType(),
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $uploadedBy,
                'document_title' => $existingSignedCif['document_title'] ?? $cifDocumentDefaults['document_title'],
                'cif_no' => $existingSignedCif['cif_no'] ?? $cifDocumentDefaults['cif_no'],
                'date_created' => $existingSignedCif['date_created'] ?? $cifDocumentDefaults['date_created'],
                'issued_on' => $existingSignedCif['issued_on'] ?? $cifDocumentDefaults['issued_on'],
                'issued_by' => $uploadedBy,
                'remarks' => 'Uploaded by client through secure CIF form.',
            ];
        }

        $appendDocuments = function (string $inputName, string $requirement) use ($request, &$documents, $uploadedBy): void {
            $uploads = $request->file($inputName, []);

            if (! is_array($uploads) || $uploads === []) {
                return;
            }

            $existing = array_values(array_filter((array) ($documents[$requirement] ?? []), fn ($item) => is_array($item)));

            foreach ($uploads as $upload) {
                if (! $upload) {
                    continue;
                }

                $storedPath = $upload->store('contact-kyc-documents', 'public');
                $existing[] = [
                    'path' => $storedPath,
                    'file_path' => $storedPath,
                    'file_name' => $upload->getClientOriginalName(),
                    'mime_type' => $upload->getMimeType(),
                    'uploaded_at' => now()->toDateTimeString(),
                    'uploaded_by' => $uploadedBy,
                ];
            }

            $documents[$requirement] = $existing;
        };

        $appendDocuments('two_valid_ids_uploads', 'two_valid_ids');
        $appendDocuments('tin_proof_uploads', 'tin_proof');
        $appendDocuments('passport_proof_uploads', 'passport_proof');
        $appendDocuments('visa_proof_uploads', 'visa_proof');
        $appendDocuments('acr_card_proof_uploads', 'acr_card_proof');
        $appendDocuments('aaep_proof_uploads', 'aaep_proof');

        $this->saveKycRequirementDocuments($contact, $documents);
    }

    private function validateClientSpecimenSignedUpload(Request $request): void
    {
        $request->validate([
            'specimen_signature_signed_upload' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function storeClientSubmittedSpecimenDocuments(Request $request, Contact $contact): void
    {
        $upload = $request->file('specimen_signature_signed_upload');
        if (! $upload) {
            return;
        }

        $documents = $this->loadKycRequirementDocuments($contact);
        $uploadedBy = trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: 'Client';
        $existing = array_values(array_filter((array) ($documents['specimen_signature_upload'] ?? []), fn ($item) => is_array($item)));
        $storedPath = $upload->store('contact-kyc-documents', 'public');

        $existing[] = [
            'path' => $storedPath,
            'file_path' => $storedPath,
            'file_name' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'uploaded_at' => now()->toDateTimeString(),
            'uploaded_by' => $uploadedBy,
        ];

        $documents['specimen_signature_upload'] = $existing;
        $this->saveKycRequirementDocuments($contact, $documents);
    }

    private function validatedSpecimenPayload(Request $request): array
    {
        return $request->validate([
            'date' => ['nullable', 'date'],
            'bif_no' => ['nullable', 'string', 'max:100'],
            'client_type' => ['nullable', 'string', 'in:new,existing,change'],
            'business_name_left' => ['nullable', 'string', 'max:255'],
            'business_name_right' => ['nullable', 'string', 'max:255'],
            'account_number_left' => ['nullable', 'string', 'max:100'],
            'account_number_right' => ['nullable', 'string', 'max:100'],
            'signature_combination' => ['nullable', 'string', 'max:150'],
            'signature_class' => ['nullable', 'string', 'max:150'],
            'left_client_name' => ['nullable', 'string', 'max:150'],
            'left_cif_no' => ['nullable', 'string', 'max:100'],
            'left_cif_dated' => ['nullable', 'date'],
            'right_client_name' => ['nullable', 'string', 'max:150'],
            'right_cif_no' => ['nullable', 'string', 'max:100'],
            'right_cif_dated' => ['nullable', 'date'],
            'signatory_names' => ['nullable', 'array'],
            'signatory_names.*' => ['nullable', 'string', 'max:150'],
            'authenticated_by' => ['nullable', 'string', 'max:150'],
            'board_resolution_spa_no' => ['nullable', 'string', 'max:150'],
            'board_resolution_spa_date' => ['nullable', 'date'],
            'signature_over_printed_name' => ['nullable', 'string', 'max:150'],
            'authorized_signatory_signature' => ['nullable', 'string', 'max:150'],
            'authorized_signatory_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'processing_instruction' => ['nullable', 'string'],
            'sales_marketing' => ['nullable', 'string', 'max:150'],
            'processed_by' => ['nullable', 'string', 'max:150'],
            'finance' => ['nullable', 'string', 'max:150'],
            'scanned_by' => ['nullable', 'string', 'max:150'],
        ]);
    }
}

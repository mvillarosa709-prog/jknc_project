@extends('layouts.app')
@section('title', 'Contact Details')

@section('content')
@php
    $statusPillClasses = [
        'Verified' => 'bg-green-100 text-green-700 border border-green-200',
        'Approved' => 'bg-green-100 text-green-700 border border-green-200',
        'Pending Verification' => 'bg-amber-100 text-amber-700 border border-amber-200',
        'For Review' => 'bg-amber-100 text-amber-700 border border-amber-200',
        'Not Submitted' => 'bg-gray-100 text-gray-600 border border-gray-200',
        'Rejected' => 'bg-red-100 text-red-700 border border-red-200',
    ];

    $status = $contact->kyc_status ?: 'Not Submitted';
    $headerFirstName = $cifData['first_name'] ?? $contact->first_name ?? '';
    $headerLastName = $cifData['last_name'] ?? $contact->last_name ?? '';
    $name = trim(collect([$headerFirstName, $headerLastName])
        ->filter(fn ($value) => filled(trim((string) $value)))
        ->implode(' '));
    $initials = strtoupper(mb_substr($headerFirstName, 0, 1).mb_substr($headerLastName, 0, 1));
    $contactCifNo = $contact->cif_no ?: ($cifData['cif_no'] ?? '-');
    $headerCompanyName = $headerLinkedCompanyName ?? 'No company linked';
    $headerEmail = $contact->email ?: '-';
    $headerPhone = $contact->phone ?: '-';
    $headerCustomerType = filled($contact->customer_type) ? ucfirst((string) $contact->customer_type) : '-';
    $headerOwner = $contact->owner_name ?: ($contact->created_by ?: '-');
    $headerAddress = $contact->contact_address ?: '-';
    $kycRequirements = $kycRequirementState ?? [
        'cif_signed_document' => ['file' => null, 'complete' => false],
        'two_valid_ids' => ['count' => 0, 'files' => [], 'complete' => false],
        'specimen_signature_form' => ['form_exists' => false, 'file' => null, 'files' => [], 'complete' => false],
        'tin_proof' => ['file' => null, 'files' => [], 'complete' => false],
    ];
    $requiredKycRequirementKeys = $requiredKycRequirementKeys ?? ['cif_signed_document', 'two_valid_ids', 'specimen_signature_form', 'tin_proof'];
    $kycRequirementLabels = [
        'cif_signed_document' => 'CIF Document (Signed)',
        'two_valid_ids' => 'Two Valid IDs',
        'specimen_signature_form' => 'Specimen Signature Form',
        'tin_proof' => 'TIN Proof',
        'passport_proof' => 'Passport',
        'visa_proof' => 'Visa',
        'acr_card_proof' => 'ACR Card',
        'aaep_proof' => 'AEP',
    ];
    $foreignerDocumentRequirements = [
        ['key' => 'passport_proof', 'label' => 'Passport'],
        ['key' => 'visa_proof', 'label' => 'Visa'],
        ['key' => 'acr_card_proof', 'label' => 'ACR Card'],
        ['key' => 'aaep_proof', 'label' => 'AEP'],
    ];
    $requiresForeignerDocuments = collect($requiredKycRequirementKeys)->intersect(array_column($foreignerDocumentRequirements, 'key'))->isNotEmpty();
    $canReviewKyc = in_array((string) (auth()->user()->role ?? ''), ['Admin', 'SuperAdmin'], true);
    $cifStatus = strtolower((string) ($contact->cif_status ?? 'draft'));
    $changeRequestStatus = strtolower((string) ($cifData['change_request_status'] ?? ''));
    $changeRequestPending = $changeRequestStatus === 'pending';
    $changeRequestApproved = $changeRequestStatus === 'approved';
    $kycLockedForRequester = ! $canReviewKyc && $cifStatus === 'approved' && ! $changeRequestApproved;
    $canModifyKyc = ! $kycLockedForRequester;
@endphp

<div class="bg-white">
    <div class="border-b border-gray-200 px-6 py-3 text-sm text-gray-600">
        <a href="{{ route('contacts.index') }}" class="hover:text-blue-700"><i class="fas fa-arrow-left mr-1"></i>Contacts</a>
        <span class="mx-1">/</span>
        <span id="contactHeaderBreadcrumb" class="font-medium text-gray-900">{{ $name }}</span>
    </div>

    <div id="contactHeaderSummary" class="border-b border-gray-200 px-6 py-4">
        <div class="flex flex-wrap items-center gap-5">
            <div class="flex h-28 w-28 items-center justify-center rounded-full bg-blue-100 text-3xl font-semibold text-blue-700">
                {{ $initials ?: 'C' }}
            </div>
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold text-gray-900">{{ $name }}</h1>
                <p class="text-xl text-gray-700">{{ $headerCompanyName }}</p>
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-700">
                    <span>Email: {{ $headerEmail }}</span>
                    <span>Phone number: {{ $headerPhone }}</span>
                    <span>Customer Type: {{ $headerCustomerType }}</span>
                    <span>CIF No: {{ $contactCifNo ?: '-' }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span id="contactKycHeaderBadge" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusPillClasses[$status] ?? $statusPillClasses['Not Submitted'] }}">{{ $status }}</span>
                    <span class="text-sm text-gray-700">Contact Owner: {{ $headerOwner }}</span>
                </div>
                <p class="text-sm text-gray-600">Address: {{ $headerAddress }}</p>
            </div>
        </div>
    </div>

    <div class="flex">
        <aside class="w-48 border-r border-gray-200 p-3">
            <nav class="space-y-1">
                @foreach ($tabs as $tabKey => $tabLabel)
                    <a
                        href="{{ route('contacts.show', $contact).'?tab='.$tabKey }}"
                        class="block rounded-lg px-3 py-1.5 text-sm {{ $tab === $tabKey ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}"
                    >
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <section class="flex-1 bg-white p-6">
            @if (session('success') && !session('contact_client_email'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif
            <div id="contactKycLiveFeedback" class="mb-4 hidden rounded-lg px-4 py-3 text-sm"></div>

            @if ($tab === 'kyc')
                <div id="kyc">
                <div id="kycTabApp">
                    <div class="mb-4 grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                <div>
                                    <h2 class="text-base font-semibold text-gray-900">Client Information Form (CIF)</h2>
                                    <p class="mt-1 text-xs text-gray-500">Manual CIF data is the source of truth for this contact record.</p>
                                </div>
                                @if ($cifEditMode)
                                    <a href="{{ route('contacts.show', ['contact' => $contact->id, 'tab' => 'kyc']) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                                @elseif ($canModifyKyc)
                                    <a href="{{ route('contacts.show', ['contact' => $contact->id, 'tab' => 'kyc', 'edit_cif' => 1]) }}" class="text-sm text-blue-600 hover:text-blue-700">Edit</a>
                                @else
                                    <span class="text-sm text-amber-700">Request change to edit</span>
                                @endif
                            </div>
                            <div id="contactCifDocumentContent" class="p-4">
                                @if ($cifEditMode)
                                    @include('contacts.partials.cif-document-edit', [
                                        'contact' => $contact,
                                        'cifData' => $cifData,
                                    ])
                                @else
                                    @include('contacts.partials.cif-document', ['cifData' => $cifData])
                                @endif
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <h3 class="text-base font-semibold text-gray-900">Client Outreach</h3>
                                </div>
                                <div class="space-y-4 px-4 py-4">
                                    <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-cyan-50 p-4">
                                        <p class="text-sm font-semibold text-gray-900">Send secure CIF link</p>
                                        <p class="mt-1 text-xs leading-5 text-gray-600">Send a secure CIF link to the client so they can complete missing details and upload onboarding documents.</p>
                                        <button type="button" data-open-send-cif-modal class="mt-4 flex h-12 w-full items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                            Send CIF
                                        </button>
                                    </div>

                                    <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-cyan-50 p-4">
                                        <p class="text-sm font-semibold text-gray-900">Send secure Specimen Signature link</p>
                                        <p class="mt-1 text-xs leading-5 text-gray-600">Send a secure link so the client can complete the specimen signature form.</p>
                                        <button type="button" data-open-send-specimen-modal class="mt-4 flex h-12 w-full items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                            Send Specimen Link
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <h3 class="text-base font-semibold text-gray-900">Preview / PDF</h3>
                                </div>
                                <div class="px-4 py-4">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <h3 class="mb-2 text-sm font-semibold text-gray-900">PDF Tools</h3>
                                        <p class="mb-3 text-xs text-gray-500">
                                            Preview the current document or export a print-friendly PDF.
                                        </p>

                                        <button type="button"
                                            data-cif-pdf-preview
                                            data-preview-url="{{ route('contacts.cif.preview', $contact->id) }}"
                                            class="mb-2 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            Preview PDF
                                        </button>

                                        <button type="button"
                                            data-cif-pdf-download
                                            data-download-url="{{ route('contacts.cif.download', $contact->id) }}?autoprint=1"
                                            class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                                            Download PDF
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[320px_1fr]">
                        <div class="space-y-4">
                            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                    <h2 class="text-base font-semibold text-gray-900">KYC Information</h2>
                                    <button id="openContactIntakeModal" type="button" class="text-sm text-blue-600 hover:text-blue-700">View KYC Form</button>
                                </div>
                                <div id="contactKycInfoCard" class="space-y-4 px-4 py-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">CIF</p>
                                        <p id="kycCifValue" class="font-medium text-gray-900"></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">TIN</p>
                                        <p id="kycTinValue" class="font-medium text-gray-900"></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">KYC Status</p>
                                        <span id="kycCardStatusBadge" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"></span>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Date Verified</p>
                                        <p id="kycDateVerifiedValue" class="font-medium text-gray-900"></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Verified By</p>
                                        <p id="kycVerifiedByValue" class="font-medium text-gray-900"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <h3 class="text-base font-semibold text-gray-900">Actions</h3>
                                </div>
                                <div id="contactKycActionPanel" class="space-y-2 px-4 py-4">
                                    @if (! $canReviewKyc)
                                        @if ($cifStatus === 'approved')
                                            <button type="button" disabled class="h-10 w-full cursor-not-allowed rounded-lg bg-green-100 text-sm font-medium text-green-700 border border-green-200">Approved</button>
                                            @if ($changeRequestPending)
                                                <button type="button" disabled class="h-10 w-full cursor-not-allowed rounded-lg bg-amber-100 text-sm font-medium text-amber-700">Change Request Pending</button>
                                                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                                    Your request is waiting for admin approval before editing is unlocked.
                                                </p>
                                            @elseif ($changeRequestApproved)
                                                <button type="button" disabled class="h-10 w-full cursor-not-allowed rounded-lg bg-blue-100 text-sm font-medium text-blue-700">Editing Unlocked</button>
                                                <p class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                                                    Update the forms and requirements, then submit for verification again.
                                                </p>
                                            @else
                                                <form id="requestKycChangeForm" method="POST" action="{{ route('contacts.kyc.change-request', $contact->id) }}" class="space-y-2">
                                                    @csrf
                                                    <textarea name="change_request_note" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Optional note for the admin reviewer"></textarea>
                                                    <button type="submit" class="h-10 w-full rounded-lg bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">Request Change Information</button>
                                                </form>
                                            @endif
                                        @elseif ($cifStatus === 'pending')
                                            <button type="button" disabled class="h-10 w-full cursor-not-allowed rounded-lg bg-amber-100 text-sm font-medium text-amber-700">Pending Approval</button>
                                        @else
                                            <form id="submitKycForVerificationForm" method="POST" action="{{ route('contacts.kyc.submit', $contact->id) }}">
                                                @csrf
                                                <button id="submitForVerificationBtn" type="submit" class="h-10 w-full rounded-lg bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">Submit For Verification</button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($canReviewKyc)
                                        @if ($cifStatus === 'approved')
                                            <button type="button" disabled class="h-10 w-full cursor-not-allowed rounded-lg border border-green-200 bg-green-100 text-sm font-medium text-green-700">Approved</button>
                                        @else
                                            <form id="approveKycForm" method="POST" action="{{ route('contacts.kyc.approve', $contact->id) }}">
                                                @csrf
                                                <button id="approveKycBtn" type="submit" class="h-10 w-full rounded-lg bg-green-600 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-green-300">Approve</button>
                                            </form>
                                            <form id="rejectKycForm" method="POST" action="{{ route('contacts.kyc.reject', $contact->id) }}">
                                                @csrf
                                                <input id="rejectReasonField" type="hidden" name="reason" value="">
                                                <button id="rejectKycBtn" type="button" class="h-10 w-full rounded-lg bg-red-600 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300">Reject</button>
                                            </form>
                                        @endif
                                        @if ($changeRequestPending && $cifStatus === 'approved')
                                            <form id="approveKycChangeForm" method="POST" action="{{ route('contacts.kyc.change-request.approve', $contact->id) }}">
                                                @csrf
                                                <button type="submit" class="h-10 w-full rounded-lg bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">Approve Change Request</button>
                                            </form>
                                            <form id="rejectKycChangeForm" method="POST" action="{{ route('contacts.kyc.change-request.reject', $contact->id) }}">
                                                @csrf
                                                <button type="submit" class="h-10 w-full rounded-lg bg-red-600 text-sm font-medium text-white hover:bg-red-700">Reject Change Request</button>
                                            </form>
                                        @endif
                                    @endif
                                    <p id="kycActionWarning" class="hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"></p>
                                    <div class="pt-2">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">KYC Activity</p>
                                        <div id="kycActionLogs" class="space-y-1 text-xs text-gray-500"></div>
                                    </div>
                                    <p id="kycRejectionNote" class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"></p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 px-4 py-3">
                                <h2 class="text-base font-semibold text-gray-900">KYC Requirements</h2>
                                <p class="mt-1 text-xs text-gray-500">Upload and manage required compliance items. Foreigner and dual-citizen contacts also require Passport, Visa, ACR Card, and AEP uploads.</p>
                            </div>
                            @if ($errors->kycRequirementUpload->any())
                                <div class="border-b border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {{ $errors->kycRequirementUpload->first() }}
                                </div>
                            @endif
                            <div id="contactKycRequirementsList" class="max-h-[520px] space-y-3 overflow-y-auto p-4">
                                @php
                                    $cifSignedRequirement = $kycRequirements['cif_signed_document'];
                                    $twoValidIds = $kycRequirements['two_valid_ids'];
                                    $specimenRequirement = $kycRequirements['specimen_signature_form'];
                                    $tinRequirement = $kycRequirements['tin_proof'];
                                    $actionBtn = 'rounded-md border border-gray-200 px-2 py-1 text-gray-600 hover:bg-gray-50';
                                    $primaryBtn = 'rounded-md border border-blue-200 px-2 py-1 text-blue-700 hover:bg-blue-50';
                                    $dangerBtn = 'rounded-md border border-red-200 px-2 py-1 text-red-600 hover:bg-red-50';
                                    $disabledBtn = 'opacity-40 pointer-events-none';
                                @endphp

                                <article class="rounded-xl border border-gray-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">CIF Document (Signed)</p>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $cifSignedRequirement['complete'] ? 'border border-green-200 bg-green-100 text-green-700' : 'border border-gray-200 bg-gray-100 text-gray-600' }}">
                                                {{ $cifSignedRequirement['complete'] ? 'Complete' : 'Missing' }}
                                            </span>
                                            <p class="mt-2 text-xs text-gray-500">{{ $cifSignedRequirement['file']['file_name'] ?? 'No file uploaded' }}</p>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.upload', $contact->id) }}" enctype="multipart/form-data" class="inline-flex">
                                                    @csrf
                                                    <input type="hidden" name="requirement" value="cif_signed_document">
                                                    <label class="{{ $actionBtn }} cursor-pointer">
                                                        Upload
                                                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            @endif
                                            <button
                                                id="viewCifSignedDocumentBtn"
                                                type="button"
                                                @if ($cifSignedRequirement['file'])
                                                    data-document-trigger
                                                    data-document-path="{{ $cifSignedRequirement['file']['file_path'] ?? $cifSignedRequirement['file']['path'] ?? '' }}"
                                                    data-document-key="cif_signed_document"
                                                @endif
                                                class="{{ $actionBtn }} {{ $cifSignedRequirement['file'] ? '' : $disabledBtn }}"
                                            >
                                                View
                                            </button>
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => 'cif_signed_document']) }}" class="inline-flex" onsubmit="return confirm('Remove the uploaded signed CIF document?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="{{ $dangerBtn }} {{ $cifSignedRequirement['file'] ? '' : $disabledBtn }}">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>

                                <article class="rounded-xl border border-gray-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">Two Valid IDs</p>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $twoValidIds['complete'] ? 'border border-green-200 bg-green-100 text-green-700' : 'border border-gray-200 bg-gray-100 text-gray-600' }}">
                                                {{ $twoValidIds['complete'] ? 'Complete' : 'Missing' }}
                                            </span>
                                            <p class="mt-2 text-xs text-gray-500">
                                                {{ $twoValidIds['count'] > 0 ? $twoValidIds['count'].' file'.($twoValidIds['count'] === 1 ? '' : 's').' uploaded' : 'No file uploaded' }}
                                            </p>
                                            @if ($twoValidIds['count'] > 0)
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach ($twoValidIds['files'] as $fileIndex => $file)
                                                        <button
                                                            type="button"
                                                            data-document-trigger
                                                            data-document-path="{{ $file['file_path'] ?? $file['path'] ?? '' }}"
                                                            data-document-key="two_valid_ids"
                                                            data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $twoValidIds['files']))) }}"
                                                            data-document-index="{{ $fileIndex }}"
                                                            class="rounded-md border border-gray-200 px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50"
                                                        >
                                                            File {{ $loop->iteration }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.upload', $contact->id) }}" enctype="multipart/form-data" class="inline-flex">
                                                    @csrf
                                                    <input type="hidden" name="requirement" value="two_valid_ids">
                                                    <label class="{{ $actionBtn }} cursor-pointer">
                                                        Upload
                                                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            @endif
                                            @php $twoValidFirstFile = $twoValidIds['files'][0] ?? null; @endphp
                                            <button
                                                type="button"
                                                @if ($twoValidFirstFile)
                                                    data-document-trigger
                                                    data-document-path="{{ $twoValidFirstFile['file_path'] ?? $twoValidFirstFile['path'] ?? '' }}"
                                                    data-document-key="two_valid_ids"
                                                    data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $twoValidIds['files']))) }}"
                                                    data-document-index="0"
                                                @endif
                                                class="{{ $actionBtn }} {{ $twoValidFirstFile ? '' : $disabledBtn }}"
                                            >
                                                View
                                            </button>
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => 'two_valid_ids']) }}" class="inline-flex" onsubmit="return confirm('Remove all uploaded valid IDs for this requirement?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="{{ $dangerBtn }} {{ $twoValidIds['count'] > 0 ? '' : $disabledBtn }}">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>

                                <article class="rounded-xl border border-gray-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">Specimen Signature Form</p>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $specimenRequirement['complete'] ? 'border border-green-200 bg-green-100 text-green-700' : 'border border-gray-200 bg-gray-100 text-gray-600' }}">
                                                {{ $specimenRequirement['complete'] ? 'Complete' : 'Missing' }}
                                            </span>
                                            <p class="mt-2 text-xs text-gray-500">
                                                @if ($specimenRequirement['file'])
                                                    {{ $specimenRequirement['file']['file_name'] ?? 'Specimen signature file uploaded' }}
                                                @elseif ($specimenRequirement['form_exists'])
                                                    System form created
                                                @else
                                                    No file uploaded
                                                @endif
                                            </p>
                                            @if (!empty($specimenRequirement['files']))
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach ($specimenRequirement['files'] as $fileIndex => $file)
                                                        <button
                                                            type="button"
                                                            data-document-trigger
                                                            data-document-path="{{ $file['file_path'] ?? $file['path'] ?? '' }}"
                                                            data-document-key="specimen_signature_upload"
                                                            data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $specimenRequirement['files']))) }}"
                                                            data-document-index="{{ $fileIndex }}"
                                                            class="rounded-md border border-gray-200 px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50"
                                                        >
                                                            File {{ $loop->iteration }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @if ($specimenRequirement['form_exists'])
                                                <a href="{{ route('contacts.specimen-signature', ['id' => $contact->id]) }}" class="{{ $actionBtn }}">View Form</a>
                                                @if ($canModifyKyc)
                                                    <a href="{{ route('contacts.specimen-signature', ['id' => $contact->id, 'edit' => 1]) }}" class="{{ $actionBtn }}">Edit Form</a>
                                                @endif
                                            @elseif ($canModifyKyc)
                                                <a href="{{ route('contacts.specimen-signature', ['id' => $contact->id]) }}" class="{{ $primaryBtn }}">Create Form</a>
                                            @endif
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.upload', $contact->id) }}" enctype="multipart/form-data" class="inline-flex">
                                                    @csrf
                                                    <input type="hidden" name="requirement" value="specimen_signature_upload">
                                                    <label class="{{ $actionBtn }} cursor-pointer">
                                                        Upload
                                                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            @endif
                                            <button
                                                type="button"
                                                @if ($specimenRequirement['file'])
                                                    data-document-trigger
                                                    data-document-path="{{ $specimenRequirement['file']['file_path'] ?? $specimenRequirement['file']['path'] ?? '' }}"
                                                    data-document-key="specimen_signature_upload"
                                                    data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $specimenRequirement['files'] ?? []))) }}"
                                                    data-document-index="0"
                                                @endif
                                                class="{{ $actionBtn }} {{ $specimenRequirement['file'] ? '' : $disabledBtn }}"
                                            >
                                                View
                                            </button>
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => 'specimen_signature_upload']) }}" class="inline-flex" onsubmit="return confirm('Remove the uploaded specimen signature file?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="{{ $dangerBtn }} {{ $specimenRequirement['file'] ? '' : $disabledBtn }}">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>

                                <article class="rounded-xl border border-gray-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">TIN</p>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $tinRequirement['complete'] ? 'border border-green-200 bg-green-100 text-green-700' : 'border border-gray-200 bg-gray-100 text-gray-600' }}">
                                                {{ $tinRequirement['complete'] ? 'Complete' : 'Missing' }}
                                            </span>
                                            <p class="mt-2 text-xs text-gray-500">
                                                @if (!empty($tinRequirement['files']))
                                                    {{ count($tinRequirement['files']) > 1 ? count($tinRequirement['files']).' files uploaded' : ($tinRequirement['file']['file_name'] ?? 'No file uploaded') }}
                                                @else
                                                    No file uploaded
                                                @endif
                                            </p>
                                            @if (!empty($tinRequirement['files']))
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach ($tinRequirement['files'] as $fileIndex => $file)
                                                        <button
                                                            type="button"
                                                            data-document-trigger
                                                            data-document-path="{{ $file['file_path'] ?? $file['path'] ?? '' }}"
                                                            data-document-key="tin_proof"
                                                            data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $tinRequirement['files']))) }}"
                                                            data-document-index="{{ $fileIndex }}"
                                                            class="rounded-md border border-gray-200 px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50"
                                                        >
                                                            File {{ $loop->iteration }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.upload', $contact->id) }}" enctype="multipart/form-data" class="inline-flex">
                                                    @csrf
                                                    <input type="hidden" name="requirement" value="tin_proof">
                                                    <label class="{{ $actionBtn }} cursor-pointer">
                                                        Upload
                                                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            @endif
                                            <button
                                                type="button"
                                                @if ($tinRequirement['file'])
                                                    data-document-trigger
                                                    data-document-path="{{ $tinRequirement['file']['file_path'] ?? $tinRequirement['file']['path'] ?? '' }}"
                                                    data-document-key="tin_proof"
                                                    data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $tinRequirement['files'] ?? []))) }}"
                                                    data-document-index="0"
                                                @endif
                                                class="{{ $actionBtn }} {{ $tinRequirement['file'] ? '' : $disabledBtn }}"
                                            >
                                                View
                                            </button>
                                            @if ($canModifyKyc)
                                                <form method="POST" action="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => 'tin_proof']) }}" class="inline-flex" onsubmit="return confirm('Remove the uploaded TIN document?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="{{ $dangerBtn }} {{ $tinRequirement['file'] ? '' : $disabledBtn }}">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>

                                @if ($requiresForeignerDocuments)
                                    @foreach ($foreignerDocumentRequirements as $foreignerRequirement)
                                        @php
                                            $foreignerRequirementKey = $foreignerRequirement['key'];
                                            $foreignerRequirementLabel = $foreignerRequirement['label'];
                                            $foreignerState = $kycRequirements[$foreignerRequirementKey] ?? ['file' => null, 'files' => [], 'complete' => false];
                                            $foreignerFiles = array_values(array_filter((array) ($foreignerState['files'] ?? []), fn ($item) => is_array($item)));
                                            $foreignerPrimaryFile = $foreignerState['file'] ?? ($foreignerFiles[0] ?? null);
                                        @endphp
                                        <article class="rounded-xl border border-gray-200 bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900">{{ $foreignerRequirementLabel }}</p>
                                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ ($foreignerState['complete'] ?? false) ? 'border border-green-200 bg-green-100 text-green-700' : 'border border-gray-200 bg-gray-100 text-gray-600' }}">
                                                        {{ ($foreignerState['complete'] ?? false) ? 'Complete' : 'Missing' }}
                                                    </span>
                                                    <p class="mt-2 text-xs text-gray-500">
                                                        @if (!empty($foreignerFiles))
                                                            {{ count($foreignerFiles) > 1 ? count($foreignerFiles).' files uploaded' : ($foreignerPrimaryFile['file_name'] ?? 'No file uploaded') }}
                                                        @else
                                                            No file uploaded
                                                        @endif
                                                    </p>
                                                    @if (!empty($foreignerFiles))
                                                        <div class="mt-2 flex flex-wrap gap-2">
                                                            @foreach ($foreignerFiles as $fileIndex => $file)
                                                                <button
                                                                    type="button"
                                                                    data-document-trigger
                                                                    data-document-path="{{ $file['file_path'] ?? $file['path'] ?? '' }}"
                                                                    data-document-key="{{ $foreignerRequirementKey }}"
                                                                    data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $foreignerFiles))) }}"
                                                                    data-document-index="{{ $fileIndex }}"
                                                                    class="rounded-md border border-gray-200 px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50"
                                                                >
                                                                    File {{ $loop->iteration }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                                                    <form method="POST" action="{{ route('contacts.kyc.requirements.upload', $contact->id) }}" enctype="multipart/form-data" class="inline-flex">
                                                        @csrf
                                                        <input type="hidden" name="requirement" value="{{ $foreignerRequirementKey }}">
                                                        <label class="{{ $actionBtn }} cursor-pointer">
                                                            Upload
                                                            <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
                                                        </label>
                                                    </form>
                                                    <button
                                                        type="button"
                                                        @if ($foreignerPrimaryFile)
                                                            data-document-trigger
                                                            data-document-path="{{ $foreignerPrimaryFile['file_path'] ?? $foreignerPrimaryFile['path'] ?? '' }}"
                                                            data-document-key="{{ $foreignerRequirementKey }}"
                                                            data-document-files="{{ json_encode(array_values(array_map(fn ($entry) => $entry['file_path'] ?? $entry['path'] ?? '', $foreignerFiles))) }}"
                                                            data-document-index="0"
                                                        @endif
                                                        class="{{ $actionBtn }} {{ $foreignerPrimaryFile ? '' : $disabledBtn }}"
                                                    >
                                                        View
                                                    </button>
                                                    <form method="POST" action="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => $foreignerRequirementKey]) }}" class="inline-flex" onsubmit="return confirm('Remove uploaded {{ $foreignerRequirementLabel }} document(s)?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="{{ $dangerBtn }} {{ !empty($foreignerFiles) ? '' : $disabledBtn }}">Remove</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>

                    <div id="kycEditModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
                        <button type="button" data-slideover-overlay class="absolute inset-0 bg-slate-900/45 opacity-0 transition-opacity duration-300"></button>
                        <div class="absolute inset-y-0 right-0 flex w-full justify-end overflow-hidden pointer-events-none">
                        <div data-slideover-panel class="pointer-events-auto flex h-full w-full max-w-[560px] translate-x-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 sm:px-8">
                                <h3 class="text-xl font-semibold text-gray-900">Edit KYC Information</h3>
                                <button id="closeKycEditModal" type="button" class="text-2xl leading-none text-gray-500 hover:text-gray-900">&times;</button>
                            </div>
                            <form id="kycEditForm" class="flex min-h-0 flex-1 flex-col">
                                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-6 sm:px-8">
                                <div>
                                    <label for="kycEditCif" class="mb-1 block text-sm font-medium text-gray-700">CIF</label>
                                    <input id="kycEditCif" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <p id="kycErrorCif" class="mt-1 hidden text-xs text-red-600">CIF is required.</p>
                                </div>
                                <div>
                                    <label for="kycEditTin" class="mb-1 block text-sm font-medium text-gray-700">TIN</label>
                                    <input id="kycEditTin" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <p id="kycErrorTin" class="mt-1 hidden text-xs text-red-600">TIN is required.</p>
                                </div>
                                <div>
                                    <label for="kycEditStatus" class="mb-1 block text-sm font-medium text-gray-700">KYC Status</label>
                                    <select id="kycEditStatus" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                        <option>Not Submitted</option>
                                        <option>Pending Verification</option>
                                        <option>For Review</option>
                                        <option>Approved</option>
                                        <option>Rejected</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="kycEditDateVerified" class="mb-1 block text-sm font-medium text-gray-700">Date Verified</label>
                                    <input id="kycEditDateVerified" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <p id="kycErrorDateVerified" class="mt-1 hidden text-xs text-red-600">Date Verified is required for Approved status.</p>
                                </div>
                                <div>
                                    <label for="kycEditVerifiedBy" class="mb-1 block text-sm font-medium text-gray-700">Verified By</label>
                                    <input id="kycEditVerifiedBy" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <p id="kycErrorVerifiedBy" class="mt-1 hidden text-xs text-red-600">Verified By is required for Approved status.</p>
                                </div>
                                </div>
                                <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4 sm:px-8">
                                    <button id="cancelKycEdit" type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                                    <button type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Save Changes</button>
                                </div>
                            </form>
                        </div>
                        </div>
                    </div>

                    <div id="documentModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
                        <button type="button" data-slideover-overlay class="absolute inset-0 bg-slate-900/45 opacity-0 transition-opacity duration-300"></button>
                        <div class="absolute inset-y-0 right-0 flex w-full justify-end overflow-hidden pointer-events-none">
                        <div data-slideover-panel class="pointer-events-auto flex h-full w-full max-w-[960px] translate-x-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 sm:px-8">
                                <h3 id="documentModalTitle" class="text-xl font-semibold text-gray-900">Edit CIF Document</h3>
                                <button id="closeDocumentModal" type="button" class="text-2xl leading-none text-gray-500 hover:text-gray-900">&times;</button>
                            </div>
                            <form id="documentForm" method="POST" action="{{ route('contacts.kyc.requirements.upload', $contact->id) }}" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
                                @csrf
                                <input type="hidden" name="requirement" value="cif_signed_document">
                                <div class="grid min-h-0 flex-1 grid-cols-1 gap-0 overflow-y-auto lg:grid-cols-[1.1fr_0.9fr]">
                                <div class="border-b border-gray-100 p-5 lg:border-b-0 lg:border-r">
                                    <div id="documentPreviewPanel" class="flex min-h-[620px] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-center text-sm text-gray-500">
                                        <i class="far fa-file-pdf text-6xl text-gray-400"></i>
                                        <p class="mt-2">No CIF document selected</p>
                                        <p class="text-xs">Upload a PDF or image file to preview</p>
                                    </div>
                                </div>
                                <div class="space-y-3 p-5">
                                    <div><label for="docCertificateNo" class="mb-1 block text-sm font-medium text-gray-700">CIF No.</label><input id="docCertificateNo" name="cif_no" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></div>
                                    <div class="grid grid-cols-2 gap-3"><div><label for="docUploadDate" class="mb-1 block text-sm font-medium text-gray-700">Date Upload</label><input id="docUploadDate" name="date_upload" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></div><div><label for="docCreatedDate" class="mb-1 block text-sm font-medium text-gray-700">Date Created</label><input id="docCreatedDate" name="date_created" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></div></div>
                                    <div class="grid grid-cols-2 gap-3"><div><label for="docIssuedOn" class="mb-1 block text-sm font-medium text-gray-700">Issued On</label><input id="docIssuedOn" name="issued_on" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></div><div><label for="docIssuedBy" class="mb-1 block text-sm font-medium text-gray-700">Issued By</label><input id="docIssuedBy" name="issued_by" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></div></div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Document</label>
                                        <label for="docFileInput" class="flex h-11 cursor-pointer items-center rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-600 hover:bg-gray-50"><i class="fas fa-folder-open mr-2 text-blue-600"></i><span id="docFileNameLabel">Replace file</span></label>
                                        <input id="docFileInput" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                        <div class="mt-2 flex items-center justify-between gap-3 text-xs">
                                            <span id="docCurrentFileMeta" class="text-gray-500"></span>
                                            <button id="clearDocFileBtn" type="button" class="hidden text-gray-500 hover:text-red-600">Clear replacement file</button>
                                        </div>
                                        <p id="docErrorFile" class="mt-1 hidden text-xs text-red-600">Please upload a CIF document file.</p>
                                    </div>
                                    <div><label for="docRemarks" class="mb-1 block text-sm font-medium text-gray-700">Remarks</label><textarea id="docRemarks" name="remarks" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea></div>
                                </div>
                                </div>
                                <div class="flex justify-between gap-3 border-t border-gray-100 bg-white px-6 py-4 sm:px-8">
                                    <button id="removeCifSignedDocumentBtn" type="button" class="h-10 rounded-lg border border-red-200 bg-white px-4 text-sm text-red-600 hover:bg-red-50">Remove file</button>
                                    <div class="flex justify-end gap-3">
                                        <button id="cancelDocumentModal" type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                                        <button type="submit" id="saveDocumentBtn" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        </div>
                    </div>

                    <form id="removeCifSignedDocumentForm" method="POST" action="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => 'cif_signed_document']) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                    <form id="removeKycDocumentForm" method="POST" action="" data-action-template="{{ route('contacts.kyc.requirements.remove', ['contact' => $contact->id, 'requirement' => '__REQUIREMENT__']) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="index" id="removeKycDocumentIndex" value="">
                    </form>

                    <div id="documentViewModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
                        <button type="button" data-slideover-overlay class="absolute inset-0 bg-slate-900/45 opacity-0 transition-opacity duration-300"></button>
                        <div class="absolute inset-y-0 right-0 flex w-full justify-end overflow-hidden pointer-events-none">
                        <div data-slideover-panel class="pointer-events-auto flex h-full w-full max-w-[900px] translate-x-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 sm:px-8"><h3 id="documentViewTitle" class="text-xl font-semibold text-gray-900">Document Details</h3><button id="closeDocumentViewModal" type="button" class="text-2xl leading-none text-gray-500 hover:text-gray-900">&times;</button></div>
                            <div class="grid min-h-0 flex-1 grid-cols-1 gap-0 overflow-y-auto lg:grid-cols-[1.25fr_0.75fr]"><div id="documentViewPreview" class="m-4 flex min-h-[620px] items-center justify-center rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500"></div><div class="space-y-3 border-l border-gray-100 p-5 text-sm"><div><p class="text-gray-500">CIF No.</p><p id="viewDocCertificateNo" class="font-medium text-gray-900"></p></div><div><p class="text-gray-500">Date Upload</p><p id="viewDocUploadDate" class="font-medium text-gray-900"></p></div><div><p class="text-gray-500">Date Created</p><p id="viewDocCreatedDate" class="font-medium text-gray-900"></p></div><div><p class="text-gray-500">Issued On</p><p id="viewDocIssuedOn" class="font-medium text-gray-900"></p></div><div><p class="text-gray-500">Issued By</p><p id="viewDocIssuedBy" class="font-medium text-gray-900"></p></div><div><p class="text-gray-500">Remarks</p><p id="viewDocRemarks" class="font-medium text-gray-900"></p></div><div><p class="text-gray-500">File Name</p><p id="viewDocFileName" class="font-medium text-gray-900"></p></div></div></div>
                            <div class="flex justify-end border-t border-gray-100 px-6 py-4"><button id="closeDocumentViewFooter" type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:bg-gray-50">Close</button></div>
                        </div>
                        </div>
                    </div>

                    <div id="rejectKycModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
                        <button type="button" data-slideover-overlay class="absolute inset-0 bg-slate-900/45 opacity-0 transition-opacity duration-300"></button>
                        <div class="absolute inset-y-0 right-0 flex w-full justify-end overflow-hidden pointer-events-none">
                        <div data-slideover-panel class="pointer-events-auto flex h-full w-full max-w-[520px] translate-x-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
                            <div class="border-b border-gray-100 px-6 py-5 sm:px-8"><h3 class="text-xl font-semibold text-gray-900">Reject KYC</h3></div>
                            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 py-6 sm:px-8">
                                <p class="text-sm text-gray-600">Are you sure you want to reject this KYC submission?</p>
                                <div><label for="rejectReasonInput" class="mb-1 block text-sm font-medium text-gray-700">Rejection Reason (optional)</label><textarea id="rejectReasonInput" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea></div>
                            </div>
                            <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4 sm:px-8"><button id="cancelRejectKyc" type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:bg-gray-50">Cancel</button><button id="confirmRejectKyc" type="button" class="h-10 rounded-lg bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700">Reject</button></div>
                            </div>
                        </div>
                    </div>

                    @php
                        $cifSignedDocumentMeta = is_array($cifSignedRequirement['file'] ?? null) ? $cifSignedRequirement['file'] : null;
                        $cifDocumentDefaultsJs = [
                            'document_title' => 'CIF Document (Signed)',
                            'cif_no' => $contact->cif_no ?: ($cifData['cif_no'] ?? ''),
                            'date_created' => $cifData['cif_date'] ?? '',
                            'issued_on' => ($cifData['cif_document_issued_on'] ?? null) ?: optional($contact->cif_form_sent_at)->toDateString(),
                            'issued_by' => $cifSignedDocumentMeta['issued_by'] ?? $cifSignedDocumentMeta['uploaded_by'] ?? ($cifData['cif_document_issued_by'] ?? ''),
                        ];
                        $kycTabPayload = [
                            'mockUser' => $contact->owner_name ?: 'John Admin',
                            'statusRaw' => $status,
                            'specimenSignatureExists' => (bool) $specimenSignature,
                            'kycRequirementState' => $kycRequirements,
                            'requiredKycRequirementKeys' => $requiredKycRequirementKeys,
                            'kycRequirementLabels' => $kycRequirementLabels,
                            'cifSignedDocument' => $cifSignedRequirement['file'] ?? null,
                            'cifDocumentDefaults' => $cifDocumentDefaultsJs,
                            'specimenSignatureRoutes' => [
                                'create' => route('contacts.specimen-signature', ['id' => $contact->id]),
                                'view' => route('contacts.specimen-signature', ['id' => $contact->id]),
                                'edit' => route('contacts.specimen-signature', ['id' => $contact->id, 'edit' => 1]),
                                'download' => route('contacts.specimen-signature.download', ['id' => $contact->id]),
                            ],
                            'kyc' => [
                                'cif' => $contact->cif_no ?: ($cifData['cif_no'] ?? ''),
                                'tin' => $contact->tin ?: ($cifData['tin'] ?? ''),
                                'dateVerified' => $cifData['date_verified'] ?? '',
                                'verifiedBy' => $cifData['verified_by'] ?? '',
                            ],
                            'logs' => $kycActivityLogs ?? [],
                        ];
                    @endphp
                    <textarea id="kycTabPayload" class="hidden" hidden>{!! json_encode($kycTabPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</textarea>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const app = document.getElementById('kycTabApp');
                            if (!app) return;
                            const q = (id) => document.getElementById(id);
                            const readJsonPayload = (id, fallback = {}) => {
                                try {
                                    return JSON.parse(document.getElementById(id)?.textContent || '');
                                } catch (error) {
                                    return fallback;
                                }
                            };
                            const payload = readJsonPayload('kycTabPayload', {});
                            const mockUser = payload.mockUser || 'John Admin';
                            const todayIso = new Date().toISOString().slice(0, 10);
                            const statusStyles = {'Not Submitted':'bg-gray-100 text-gray-600 border border-gray-200','Pending Verification':'bg-amber-100 text-amber-700 border border-amber-200','For Review':'bg-amber-100 text-amber-700 border border-amber-200','Approved':'bg-green-100 text-green-700 border border-green-200','Rejected':'bg-red-100 text-red-700 border border-red-200'};
                            const statusRaw = payload.statusRaw || 'Not Submitted';
                            const statusInit = statusRaw === 'Verified' ? 'Approved' : statusRaw;
                            const specimenSignatureExists = Boolean(payload.specimenSignatureExists);
                            let kycRequirementState = payload.kycRequirementState || {};
                            let requiredKycRequirementKeys = payload.requiredKycRequirementKeys || [];
                            const kycRequirementLabels = payload.kycRequirementLabels || {};
                            let cifSignedDocument = payload.cifSignedDocument || null;
                            let cifDocumentDefaults = payload.cifDocumentDefaults || {};
                            const specimenSignatureRoutes = payload.specimenSignatureRoutes || {};
                            let kyc = {
                                cif: payload.kyc?.cif || '',
                                tin: payload.kyc?.tin || '',
                                status: statusInit || 'Not Submitted',
                                dateVerified: payload.kyc?.dateVerified || '',
                                verifiedBy: payload.kyc?.verifiedBy || '',
                                rejectionReason: '',
                                submitted: ['Pending Verification','For Review','Approved','Rejected', 'Verified'].includes(statusInit)
                            };
                            let logs = Array.isArray(payload.logs) ? payload.logs : [];
                            let activeDoc = null; let file = null; let fileUrl = '';
                            let currentFiles = [];
                            let currentIndex = 0;
                            let currentDocs = [];
                            let refreshSequence = 0;
                            const syncEventName = 'jknc:kyc-sync';
                            const liveFeedback = document.getElementById('contactKycLiveFeedback');
                            const sendSuccessOverlay = document.getElementById('contactSendSuccessOverlay');
                            const sendSuccessCard = document.getElementById('contactSendSuccessCard');
                            const sendSuccessText = document.getElementById('contactSendSuccessText');

                            const node = (tag, className = '', text = null) => {
                                const element = document.createElement(tag);
                                if (className) element.className = className;
                                if (text !== null && text !== undefined) element.textContent = text;
                                return element;
                            };

                            const fmtDate = (s) => { if (!s) return '-'; const d = new Date(s + 'T00:00:00'); return Number.isNaN(d.getTime()) ? s : new Intl.DateTimeFormat('en-US',{month:'short',day:'2-digit',year:'numeric'}).format(d); };
                            const fmtBytes = (n) => !n ? '-' : (n < 1024 ? `${n} B` : (n < 1048576 ? `${(n/1024).toFixed(1)} KB` : `${(n/1048576).toFixed(1)} MB`));
                            const open = (m) => {
                                const panel = m.querySelector('[data-slideover-panel]');
                                const overlay = m.querySelector('[data-slideover-overlay]');
                                m.classList.remove('hidden');
                                m.setAttribute('aria-hidden', 'false');
                                document.body.classList.add('overflow-hidden');
                                requestAnimationFrame(() => {
                                    overlay?.classList.remove('opacity-0');
                                    panel?.classList.remove('translate-x-full');
                                });
                            };
                            const close = (m) => {
                                const panel = m.querySelector('[data-slideover-panel]');
                                const overlay = m.querySelector('[data-slideover-overlay]');
                                overlay?.classList.add('opacity-0');
                                panel?.classList.add('translate-x-full');
                                window.setTimeout(() => {
                                    m.classList.add('hidden');
                                    m.setAttribute('aria-hidden', 'true');
                                    if ([q('kycEditModal'),q('documentModal'),q('documentViewModal'),q('rejectKycModal')].every((x) => x.classList.contains('hidden'))) document.body.classList.remove('overflow-hidden');
                                }, 300);
                            };
                            const badge = (el, status) => { el.className = `inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusStyles[status] || statusStyles['Not Submitted']}`; el.textContent = status; };
                            const addLog = (msg) => logs.unshift({
                                message: msg,
                                timestamp: new Date().toLocaleString('en-US', {
                                    month: 'long',
                                    day: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                }).replace(',', '').replace(' at', ' •'),
                            });
                            const allRequiredUploaded = () => requiredKycRequirementKeys.every((key) => kycRequirementState[key]?.complete === true);
                            const showFeedback = (message, tone = 'success') => {
                                if (!liveFeedback) return;
                                const tones = {
                                    success: 'border border-green-200 bg-green-50 text-green-700',
                                    error: 'border border-red-200 bg-red-50 text-red-700',
                                    warning: 'border border-amber-200 bg-amber-50 text-amber-800',
                                };
                                liveFeedback.className = `mb-4 rounded-lg px-4 py-3 text-sm ${tones[tone] || tones.success}`;
                                liveFeedback.textContent = message || '';
                                liveFeedback.classList.toggle('hidden', !message);
                            };
                            let sendSuccessTimeout = null;
                            const showSendSuccess = (email, documentName = 'secure form') => {
                                if (!sendSuccessOverlay || !sendSuccessCard || !sendSuccessText || !email) return;
                                sendSuccessText.textContent = `The ${documentName} has been sent to ${email}.`;
                                sendSuccessOverlay.classList.remove('hidden');
                                sendSuccessOverlay.classList.add('flex');
                                sendSuccessOverlay.setAttribute('aria-hidden', 'false');
                                window.clearTimeout(sendSuccessTimeout);
                                requestAnimationFrame(() => {
                                    sendSuccessCard.classList.remove('translate-y-2', 'opacity-0');
                                });
                                sendSuccessTimeout = window.setTimeout(() => {
                                    sendSuccessCard.classList.add('translate-y-2', 'opacity-0');
                                    window.setTimeout(() => {
                                        sendSuccessOverlay.classList.add('hidden');
                                        sendSuccessOverlay.classList.remove('flex');
                                        sendSuccessOverlay.setAttribute('aria-hidden', 'true');
                                    }, 300);
                                }, 3200);
                            };
                            const replaceHtmlIfPresent = (id, sourceDoc) => {
                                const current = document.getElementById(id);
                                const incoming = sourceDoc.getElementById(id);
                                if (!current || !incoming) return;
                                current.innerHTML = incoming.innerHTML;
                            };
                            const syncHeaderBadgeFromDom = (sourceDoc) => {
                                const current = q('contactKycHeaderBadge');
                                const incoming = sourceDoc.getElementById('contactKycHeaderBadge');
                                if (!current || !incoming) return;
                                current.className = incoming.className;
                                current.textContent = incoming.textContent;
                            };
                            const activeCifEditFormSelector = '[data-cif-document-form], [data-cif-card-form]';
                            const isCifEditActive = () => !!document.querySelector(activeCifEditFormSelector);
                            const isCifEditDirty = () => !!document.querySelector(`${activeCifEditFormSelector}[data-cif-dirty="1"]`);
                            const shouldSkipCifRefresh = () => isCifEditActive() || isCifEditDirty();
                            const normalizeKycStatus = (status) => {
                                const normalized = String(status || '').trim();
                                return normalized === 'Verified' ? 'Approved' : (normalized || 'Not Submitted');
                            };
                            const applyKycPayload = (nextPayload) => {
                                if (!nextPayload || typeof nextPayload !== 'object') return;

                                const nextStatus = normalizeKycStatus(nextPayload.statusRaw || kyc.status);
                                const nextKyc = nextPayload.kyc || {};
                                const has = (key) => Object.prototype.hasOwnProperty.call(nextKyc, key);

                                kyc = {
                                    ...kyc,
                                    cif: has('cif') ? (nextKyc.cif || kyc.cif || '') : kyc.cif,
                                    tin: has('tin') ? (nextKyc.tin || kyc.tin || '') : kyc.tin,
                                    status: nextStatus,
                                    dateVerified: has('dateVerified') ? (nextKyc.dateVerified || '') : kyc.dateVerified,
                                    verifiedBy: has('verifiedBy') ? (nextKyc.verifiedBy || '') : kyc.verifiedBy,
                                    submitted: ['Pending Verification','For Review','Approved','Rejected', 'Verified'].includes(nextStatus),
                                };

                                if (nextPayload.kycRequirementState) {
                                    kycRequirementState = nextPayload.kycRequirementState;
                                }
                                if (Array.isArray(nextPayload.requiredKycRequirementKeys)) {
                                    requiredKycRequirementKeys = nextPayload.requiredKycRequirementKeys;
                                }
                                if (Object.prototype.hasOwnProperty.call(nextPayload, 'cifSignedDocument')) {
                                    cifSignedDocument = nextPayload.cifSignedDocument || null;
                                }
                                if (nextPayload.cifDocumentDefaults) {
                                    cifDocumentDefaults = nextPayload.cifDocumentDefaults;
                                }
                                if (Array.isArray(nextPayload.logs)) {
                                    logs = nextPayload.logs;
                                }
                            };
                            const refreshKycFragments = async (options = {}) => {
                                const sequence = ++refreshSequence;
                                try {
                                    const refreshUrl = new URL(window.location.href);
                                    refreshUrl.searchParams.set('tab', 'kyc');
                                    refreshUrl.searchParams.set('_ts', String(Date.now()));
                                    const response = await fetch(refreshUrl.toString(), {
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                        cache: 'no-store',
                                    });
                                    if (!response.ok) return;
                                    const html = await response.text();
                                    if (sequence !== refreshSequence) return;
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');
                                    const nextPayload = (() => {
                                        try {
                                            return JSON.parse(doc.getElementById('kycTabPayload')?.textContent || '{}');
                                        } catch (error) {
                                            return {};
                                        }
                                    })();
                                    applyKycPayload(nextPayload);
                                    replaceHtmlIfPresent('contactHeaderSummary', doc);
                                    replaceHtmlIfPresent('contactHeaderBreadcrumb', doc);
                                    if (!shouldSkipCifRefresh()) {
                                        replaceHtmlIfPresent('contactCifDocumentContent', doc);
                                    }
                                    replaceHtmlIfPresent('contactKycInfoCard', doc);
                                    if (options.refreshActions) {
                                        replaceHtmlIfPresent('contactKycActionPanel', doc);
                                    }
                                    replaceHtmlIfPresent('contactKycRequirementsList', doc);
                                    syncHeaderBadgeFromDom(doc);
                                    render();
                                } catch (error) {
                                }
                            };
                            const submitJsonForm = async (form, options = {}) => {
                                if (!form) return null;
                                const response = await fetch(form.action, {
                                    method: form.method || 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: new FormData(form),
                                    cache: 'no-store',
                                });

                                if (response.status === 422) {
                                    const payload = await response.json();
                                    const firstError = Object.values(payload.errors || {}).flat()[0] || 'Please review the form.';
                                    throw new Error(firstError);
                                }

                                if (!response.ok) {
                                    throw new Error(options.fallbackError || 'Unable to complete the request right now.');
                                }

                                return response.json();
                            };

                            const render = () => {
                                q('kycCifValue').textContent = kyc.cif || '-';
                                q('kycTinValue').textContent = kyc.tin || '-';
                                q('kycDateVerifiedValue').textContent = fmtDate(kyc.dateVerified);
                                q('kycVerifiedByValue').textContent = kyc.verifiedBy || '-';
                                badge(q('kycCardStatusBadge'), kyc.status);
                                if (q('contactKycHeaderBadge')) badge(q('contactKycHeaderBadge'), kyc.status);
                                q('kycActionLogs').replaceChildren(...logs.slice(0, 7).map((entry) => {
                                    const wrapper = node('div', 'space-y-0.5');
                                    wrapper.append(
                                        node('p', 'text-xs text-gray-600', entry.message ?? ''),
                                        node('p', 'text-[11px] text-gray-400', entry.timestamp ?? '')
                                    );
                                    return wrapper;
                                }));
                                const canReview = kyc.submitted && ['Pending Verification','For Review'].includes(kyc.status);
                                const approveBtn = q('approveKycBtn');
                                const rejectBtn = q('rejectKycBtn');
                                if (approveBtn) approveBtn.disabled = !canReview;
                                if (rejectBtn) rejectBtn.disabled = !canReview;
                                q('kycRejectionNote').classList.toggle('hidden', !kyc.rejectionReason);
                                q('kycRejectionNote').textContent = kyc.rejectionReason ? `Rejection reason: ${kyc.rejectionReason}` : '';
                            };

                            const renderPreview = (name, url, mime) => {
                                const label = 'document';
                                const panel = q('documentPreviewPanel');
                                if (!panel) return;

                                panel.replaceChildren();

                                if (!name) {
                                    const icon = document.createElement('i');
                                    icon.className = 'far fa-file-pdf text-6xl text-gray-400';
                                    const title = document.createElement('p');
                                    title.className = 'mt-2';
                                    title.textContent = `No ${label.toLowerCase()} selected`;
                                    const subtitle = document.createElement('p');
                                    subtitle.className = 'text-xs';
                                    subtitle.textContent = 'Upload a PDF or image file to preview';
                                    panel.append(icon, title, subtitle);
                                    return;
                                }

                                const normalizedMime = String(mime || '').toLowerCase();
                                const normalizedUrl = String(url || '');
                                const normalizedName = String(name || '').toLowerCase();
                                const isPdf = normalizedMime.includes('pdf') || normalizedUrl.toLowerCase().endsWith('.pdf') || normalizedName.endsWith('.pdf');
                                const isImage = normalizedMime.startsWith('image/')
                                    || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(normalizedUrl)
                                    || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(normalizedName);

                                if (isPdf && url && url !== '#') {
                                    const frame = document.createElement('iframe');
                                    frame.src = url;
                                    frame.className = 'h-[620px] w-full rounded-lg border border-gray-200 bg-white';
                                    panel.appendChild(frame);
                                    return;
                                }

                                if (isImage && url && url !== '#') {
                                    const image = document.createElement('img');
                                    image.src = url;
                                    image.alt = 'Document preview';
                                    image.className = 'h-[620px] w-full rounded-lg border border-gray-200 bg-white object-contain';
                                    panel.appendChild(image);
                                    return;
                                }

                                const wrapper = document.createElement('div');
                                wrapper.className = 'text-center';
                                const fileIcon = document.createElement('i');
                                fileIcon.className = 'far fa-file text-5xl text-blue-600';
                                const fileName = document.createElement('p');
                                fileName.className = 'mt-2 font-medium text-gray-800';
                                fileName.textContent = name;
                                const fileMeta = document.createElement('p');
                                fileMeta.className = 'text-xs text-gray-500';
                                fileMeta.textContent = mime || 'Document file';
                                wrapper.append(fileIcon, fileName, fileMeta);
                                panel.appendChild(wrapper);
                            };

                            const normalizeDateInput = (value) => {
                                if (!value) return '';
                                return String(value).slice(0, 10);
                            };

                            const renderPreviewSwitcher = () => {
                                let switcher = document.getElementById('documentPreviewSwitcher');
                                if (!switcher) {
                                    switcher = document.createElement('div');
                                    switcher.id = 'documentPreviewSwitcher';
                                    switcher.className = 'mt-3 flex flex-wrap gap-2';
                                    q('documentPreviewPanel').insertAdjacentElement('afterend', switcher);
                                }
                                if (currentFiles.length <= 1) {
                                    switcher.replaceChildren();
                                    return;
                                }
                                switcher.replaceChildren(...currentFiles.map((_, index) => {
                                    const button = document.createElement('button');
                                    button.type = 'button';
                                    button.dataset.previewIndex = String(index);
                                    button.className = `rounded-md border px-2 py-1 text-xs ${index === currentIndex ? 'border-blue-200 bg-blue-100 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'}`;
                                    button.textContent = String(index + 1);
                                    return button;
                                }));
                                switcher.querySelectorAll('[data-preview-index]').forEach((button) => {
                                    button.addEventListener('click', () => switchFile(Number(button.dataset.previewIndex)));
                                });
                            };

                            const renderActiveDocument = () => {
                                const doc = currentDocs[currentIndex] || {};
                                const filePath = currentFiles[currentIndex] || '';
                                const previewUrl = (() => {
                                    if (doc.url && doc.url !== '#') return doc.url;
                                    if (!filePath) return '';
                                    if (/^https?:\/\//i.test(filePath)) return filePath;
                                    const normalizedPath = String(filePath)
                                        .replace(/^\/+/, '')
                                        .replace(/^storage\//i, '');
                                    return normalizedPath ? `/storage/${normalizedPath}` : '';
                                })();
                                q('documentModalTitle').textContent = kycRequirementLabels[activeDoc] || 'Document';
                                q('docCertificateNo').value = doc.cif_no || cifDocumentDefaults.cif_no || kyc.cif || '';
                                q('docUploadDate').value = normalizeDateInput(doc.uploaded_at) || todayIso;
                                q('docCreatedDate').value = normalizeDateInput(doc.date_created) || normalizeDateInput(cifDocumentDefaults.date_created);
                                q('docIssuedOn').value = normalizeDateInput(doc.issued_on) || normalizeDateInput(cifDocumentDefaults.issued_on);
                                q('docIssuedBy').value = doc.issued_by || doc.uploaded_by || cifDocumentDefaults.issued_by || '';
                                q('docRemarks').value = doc.remarks || '';
                                q('docFileNameLabel').textContent = doc.file_name ? 'Replace file' : 'Upload File';
                                q('docCurrentFileMeta').textContent = doc.file_name ? `Current file: ${doc.file_name}` : 'No file uploaded yet';
                                q('removeCifSignedDocumentBtn').classList.toggle('hidden', !doc.file_name);
                                const showCifFields = activeDoc === 'cif_signed_document';
                                q('docCertificateNo').closest('div').classList.toggle('hidden', !showCifFields);
                                q('docCreatedDate').closest('div').classList.toggle('hidden', !showCifFields);
                                renderPreview(doc.file_name || '', previewUrl, doc.mime_type || '');
                                renderPreviewSwitcher();
                            };

                            const switchFile = (index) => {
                                currentIndex = index;
                                renderActiveDocument();
                            };
                            window.switchFile = switchFile;

                            const openDocumentModal = (filePath, docType, files = [], startIndex = 0) => {
                                activeDoc = docType;
                                const resolvedDocType = docType === 'specimen_signature_upload' ? 'specimen_signature_form' : docType;
                                const requirementState = docType === 'cif_signed_document'
                                    ? { files: cifSignedDocument ? [cifSignedDocument] : [] }
                                    : kycRequirementState[resolvedDocType];
                                currentDocs = Array.isArray(requirementState?.files) && requirementState.files.length
                                    ? requirementState.files
                                    : (requirementState?.file ? [requirementState.file] : []);
                                currentFiles = files.length ? files : currentDocs.map((doc) => doc.url || doc.file_path || doc.path || '');
                                currentIndex = Math.min(Math.max(startIndex, 0), Math.max(currentFiles.length - 1, 0));
                                if (!currentFiles[currentIndex] && !(currentDocs[currentIndex]?.file_name)) return;
                                file = null;
                                fileUrl = '';
                                q('documentForm').reset();
                                q('docErrorFile').classList.add('hidden');
                                q('clearDocFileBtn').classList.add('hidden');
                                renderActiveDocument();
                                open(q('documentModal'));
                            };
                            window.openDocumentModal = openDocumentModal;

                            document.addEventListener('click', (event) => {
                                const button = event.target.closest('[data-document-trigger]');
                                if (!button) return;

                                let files = [];
                                try {
                                    files = JSON.parse(button.dataset.documentFiles || '[]');
                                } catch (error) {
                                    files = [];
                                }

                                openDocumentModal(
                                    button.dataset.documentPath || '',
                                    button.dataset.documentKey || '',
                                    Array.isArray(files) ? files : [],
                                    Number(button.dataset.documentIndex || 0)
                                );
                            });
                            document.addEventListener('input', (event) => {
                                const form = event.target.closest('[data-cif-document-form], [data-cif-card-form]');
                                if (!form) return;
                                form.dataset.cifDirty = '1';
                            });

                            const openKycEditButton = q('openKycEditModal');
                            if (openKycEditButton) {
                                openKycEditButton.addEventListener('click', () => { q('kycEditCif').value = kyc.cif; q('kycEditTin').value = kyc.tin; q('kycEditStatus').value = kyc.status; q('kycEditDateVerified').value = kyc.dateVerified; q('kycEditVerifiedBy').value = kyc.verifiedBy; ['kycErrorCif','kycErrorTin','kycErrorDateVerified','kycErrorVerifiedBy'].forEach((id) => q(id).classList.add('hidden')); open(q('kycEditModal')); });
                            }
                            [q('closeKycEditModal'), q('cancelKycEdit')].forEach((b) => b.addEventListener('click', () => close(q('kycEditModal'))));
                            [q('closeDocumentModal'), q('cancelDocumentModal')].forEach((b) => b.addEventListener('click', () => close(q('documentModal'))));
                            [q('closeDocumentViewModal'), q('closeDocumentViewFooter')].forEach((b) => b.addEventListener('click', () => close(q('documentViewModal'))));
                            q('cancelRejectKyc').addEventListener('click', () => close(q('rejectKycModal')));

                            q('kycEditForm').addEventListener('submit', (e) => {
                                e.preventDefault();
                                const s = q('kycEditStatus').value; const req = s === 'Approved';
                                const okCif = !!q('kycEditCif').value.trim(), okTin = !!q('kycEditTin').value.trim(), okDate = !req || !!q('kycEditDateVerified').value, okBy = !req || !!q('kycEditVerifiedBy').value.trim();
                                q('kycErrorCif').classList.toggle('hidden', okCif); q('kycErrorTin').classList.toggle('hidden', okTin); q('kycErrorDateVerified').classList.toggle('hidden', okDate); q('kycErrorVerifiedBy').classList.toggle('hidden', okBy);
                                if (!(okCif && okTin && okDate && okBy)) return;
                                kyc = { ...kyc, cif: q('kycEditCif').value.trim(), tin: q('kycEditTin').value.trim(), status: s, dateVerified: q('kycEditDateVerified').value, verifiedBy: q('kycEditVerifiedBy').value.trim() };
                                addLog(`KYC information updated by ${mockUser}`); render(); close(q('kycEditModal'));
                            });

                            q('docFileInput').addEventListener('change', () => {
                                const f = q('docFileInput').files?.[0];
                                if (!f) return;
                                file = f;
                                fileUrl = URL.createObjectURL(f);
                                q('docFileNameLabel').textContent = 'Replace file';
                                q('docCurrentFileMeta').textContent = `Replacement file: ${f.name}`;
                                q('clearDocFileBtn').classList.remove('hidden');
                                renderPreview(f.name, fileUrl, f.type || '');
                            });
                            q('clearDocFileBtn').addEventListener('click', () => {
                                file = null;
                                fileUrl = '';
                                q('docFileInput').value = '';
                                q('clearDocFileBtn').classList.add('hidden');
                                renderActiveDocument();
                            });
                            q('removeCifSignedDocumentBtn')?.addEventListener('click', () => {
                                const activeFile = currentDocs[currentIndex] || {};
                                if (!activeFile?.file_name || !window.confirm('Delete this file?')) return;
                                if (activeDoc === 'cif_signed_document') {
                                    q('removeCifSignedDocumentForm').submit();
                                    return;
                                }
                                q('removeKycDocumentForm').setAttribute('action', (q('removeKycDocumentForm').dataset.actionTemplate || '').replace('__REQUIREMENT__', activeDoc));
                                q('removeKycDocumentIndex').value = String(currentIndex);
                                q('removeKycDocumentForm').submit();
                            });
                            q('documentForm').addEventListener('submit', (event) => {
                                const activeFile = currentDocs[currentIndex] || {};
                                const hasExistingFile = !!activeFile?.file_name;
                                const hasReplacementFile = !!q('docFileInput').files?.[0];
                                q('docErrorFile').classList.toggle('hidden', hasExistingFile || hasReplacementFile);
                                if (!hasExistingFile && !hasReplacementFile) {
                                    event.preventDefault();
                                }
                            });
                            q('rejectKycBtn')?.addEventListener('click', () => {
                                if (!kyc.submitted) {
                                    q('kycActionWarning').textContent = 'Submit for verification first before rejecting.';
                                    q('kycActionWarning').classList.remove('hidden');
                                    setTimeout(() => q('kycActionWarning').classList.add('hidden'), 3200);
                                    return;
                                }
                                open(q('rejectKycModal'));
                            });
                            q('confirmRejectKyc').addEventListener('click', () => {
                                const reason = q('rejectReasonInput').value.trim();
                                const reasonField = q('rejectReasonField');
                                if (reasonField) {
                                    reasonField.value = reason;
                                }
                                q('rejectKycForm')?.requestSubmit();
                            });
                            [q('kycEditModal'), q('documentModal'), q('documentViewModal'), q('rejectKycModal')].forEach((m) => {
                                m.querySelector('[data-slideover-overlay]')?.addEventListener('click', () => close(m));
                            });
                            document.addEventListener('keydown', (event) => {
                                if (event.key !== 'Escape') return;
                                [q('kycEditModal'), q('documentModal'), q('documentViewModal'), q('rejectKycModal')].forEach((m) => {
                                    if (!m.classList.contains('hidden')) close(m);
                                });
                            });

                            const sessionClientEmail = @json(session('contact_client_email'));
                            if (sessionClientEmail) {
                                showSendSuccess(sessionClientEmail, 'secure client form');
                            }

                            const handleKycSyncEvent = (detail) => {
                                if (!detail || detail.module !== 'contacts' || String(detail.id || '') !== @json((string) $contact->id)) return;
                                refreshKycFragments({ refreshActions: true });
                            };
                            let syncChannel = null;
                            if ('BroadcastChannel' in window) {
                                syncChannel = new BroadcastChannel(syncEventName);
                                syncChannel.addEventListener('message', (event) => handleKycSyncEvent(event.data));
                            }
                            window.addEventListener('storage', (event) => {
                                if (event.key !== syncEventName || !event.newValue) return;
                                try {
                                    handleKycSyncEvent(JSON.parse(event.newValue));
                                } catch (error) {
                                }
                            });

                            q('sendCifForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to send the CIF link.' });
                                    showFeedback('');
                                    showSendSuccess(payload.recipient_email || '', 'secure Client Information Form');
                                    addLog(`Secure CIF link sent by ${mockUser}`);
                                    render();
                                    refreshKycFragments();
                                    window.jkncSlideOver.close(document.getElementById('sendCifModal'));
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('sendSpecimenForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to send the specimen link.' });
                                    showFeedback('');
                                    showSendSuccess(payload.recipient_email || '', 'secure Specimen Signature Form');
                                    addLog(`Secure specimen link sent by ${mockUser}`);
                                    render();
                                    refreshKycFragments();
                                    window.jkncSlideOver.close(document.getElementById('sendSpecimenModal'));
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('submitKycForVerificationForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to submit KYC for verification.' });
                                    kyc = { ...kyc, ...(payload.kyc || {}) };
                                    addLog(`KYC submitted for verification by ${mockUser}`);
                                    showFeedback(payload.message, payload.warning ? 'warning' : 'success');
                                    render();
                                    refreshKycFragments({ refreshActions: true });
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('approveKycForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to approve this CIF.' });
                                    kyc = { ...kyc, ...(payload.kyc || {}) };
                                    addLog(`KYC approved by ${mockUser}`);
                                    showFeedback(payload.message, 'success');
                                    render();
                                    refreshKycFragments({ refreshActions: true });
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('rejectKycForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to reject this CIF.' });
                                    kyc = { ...kyc, ...(payload.kyc || {}) };
                                    addLog(`KYC rejected by ${mockUser}`);
                                    showFeedback(payload.message, 'success');
                                    close(q('rejectKycModal'));
                                    render();
                                    refreshKycFragments({ refreshActions: true });
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('requestKycChangeForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to submit the change request.' });
                                    showFeedback(payload.message, 'success');
                                    refreshKycFragments({ refreshActions: true });
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('approveKycChangeForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to approve the change request.' });
                                    showFeedback(payload.message, 'success');
                                    refreshKycFragments({ refreshActions: true });
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            q('rejectKycChangeForm')?.addEventListener('submit', async (event) => {
                                event.preventDefault();
                                try {
                                    const payload = await submitJsonForm(event.currentTarget, { fallbackError: 'Unable to reject the change request.' });
                                    showFeedback(payload.message, 'success');
                                    refreshKycFragments({ refreshActions: true });
                                } catch (error) {
                                    showFeedback(error.message, 'error');
                                }
                            });

                            render();
                            window.setInterval(refreshKycFragments, 10000);
                        });

                        window.addEventListener('load', function () {
                            if (window.location.hash === '#kyc') {
                                document.getElementById('kyc')?.scrollIntoView({ behavior: 'smooth' });
                            }
                        });
                    </script>
                </div>
                </div>
            @endif

            @if ($tab === 'history')
                @php
                    $historyChips = [
                        ['key' => 'all', 'label' => 'All Activities'],
                        ['key' => 'profile', 'label' => 'Profile Changes'],
                        ['key' => 'kyc', 'label' => 'KYC Updates'],
                        ['key' => 'deals', 'label' => 'Deals'],
                        ['key' => 'files', 'label' => 'Files'],
                        ['key' => 'notes', 'label' => 'Notes'],
                    ];

                    $typeStyles = [
                        'deals' => [
                            'badge' => 'bg-amber-100 text-amber-600',
                            'icon' => 'fa-arrow-trend-up',
                        ],
                        'notes' => [
                            'badge' => 'bg-yellow-100 text-yellow-700',
                            'icon' => 'fa-note-sticky',
                        ],
                        'profile' => [
                            'badge' => 'bg-blue-100 text-blue-600',
                            'icon' => 'fa-pen',
                        ],
                        'kyc' => [
                            'badge' => 'bg-green-100 text-green-600',
                            'icon' => 'fa-shield-halved',
                        ],
                        'files' => [
                            'badge' => 'bg-indigo-100 text-indigo-600',
                            'icon' => 'fa-file-arrow-up',
                        ],
                    ];
                @endphp

                <div id="historyFeed" class="rounded-xl bg-white">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" aria-label="Filter">
                            <i class="fas fa-filter text-sm"></i>
                        </button>
                        @foreach ($historyChips as $chip)
                            <button
                                type="button"
                                data-history-chip="{{ $chip['key'] }}"
                                class="history-chip rounded-lg border px-3 py-1.5 text-sm {{ $chip['key'] === 'all' ? 'border-blue-200 bg-blue-700 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}"
                            >
                                {{ $chip['label'] }}
                            </button>
                        @endforeach
                        <span id="historyRecordCount" class="ml-auto text-sm text-gray-500">{{ count($tabData['history']['items']) }} records</span>
                    </div>

                    <div class="relative space-y-4 pl-12 before:absolute before:bottom-2 before:left-4 before:top-2 before:w-px before:bg-gray-200">
                        @foreach ($tabData['history']['items'] as $item)
                            @php
                                $type = $item['type'] ?? 'profile';
                                $style = $typeStyles[$type] ?? $typeStyles['profile'];
                            @endphp
                            <article data-history-item data-history-type="{{ $type }}" class="relative rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <span class="absolute -left-12 top-6 z-10 flex h-9 w-9 items-center justify-center rounded-full {{ $style['badge'] }}">
                                    <i class="fas {{ $style['icon'] }} text-xs"></i>
                                </span>

                                <h3 class="text-lg font-semibold leading-tight text-gray-900">{{ $item['title'] }}</h3>
                                <p class="mt-1 text-sm text-gray-600">{{ $item['description'] }}</p>

                                @if (!empty($item['extraLabel']) && !empty($item['extraValue']))
                                    <div class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        <span class="font-medium text-gray-700">{{ $item['extraLabel'] }}:</span> {{ $item['extraValue'] }}
                                    </div>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 font-semibold text-blue-700">{{ $item['initials'] }}</span>
                                    <span>{{ $item['user'] }}</span>
                                    <span><i class="far fa-clock mr-1"></i>{{ $item['datetime'] }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const feed = document.getElementById('historyFeed');
                        if (!feed) {
                            return;
                        }

                        const chips = Array.from(feed.querySelectorAll('[data-history-chip]'));
                        const items = Array.from(feed.querySelectorAll('[data-history-item]'));
                        const countLabel = document.getElementById('historyRecordCount');

                        function setActiveChip(activeKey) {
                            chips.forEach((chip) => {
                                const isActive = chip.dataset.historyChip === activeKey;
                                chip.classList.toggle('bg-blue-700', isActive);
                                chip.classList.toggle('text-white', isActive);
                                chip.classList.toggle('border-blue-200', isActive);
                                chip.classList.toggle('bg-white', !isActive);
                                chip.classList.toggle('text-gray-700', !isActive);
                                chip.classList.toggle('border-gray-200', !isActive);
                            });
                        }

                        function applyFilter(filterKey) {
                            let visibleCount = 0;

                            items.forEach((item) => {
                                const itemType = item.dataset.historyType;
                                const visible = filterKey === 'all' || itemType === filterKey;
                                item.classList.toggle('hidden', !visible);
                                if (visible) {
                                    visibleCount += 1;
                                }
                            });

                            countLabel.textContent = `${visibleCount} records`;
                            setActiveChip(filterKey);
                        }

                        chips.forEach((chip) => {
                            chip.addEventListener('click', function () {
                                applyFilter(chip.dataset.historyChip);
                            });
                        });

                        applyFilter('all');
                    });
                </script>
            @endif

            @if ($tab === 'consultation-notes')
                <div id="consultationNotesApp">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">Consultation Notes</h2>
                            <p class="text-sm text-gray-500">Record and track all consultation sessions</p>
                        </div>
                        <button id="openConsultationNoteModal" type="button" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                            + Add Consultation Note
                        </button>
                    </div>

                    <div id="consultationNotesList" class="space-y-3"></div>

                    <div id="consultationFormModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
                        <button type="button" data-slideover-overlay class="absolute inset-0 bg-slate-900/45 opacity-0 transition-opacity duration-300"></button>
                        <div class="absolute inset-y-0 right-0 flex w-full justify-end overflow-hidden pointer-events-none">
                        <div data-slideover-panel class="pointer-events-auto flex h-full w-full max-w-[720px] translate-x-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 sm:px-8">
                                <h3 id="consultationFormTitle" class="text-xl font-semibold text-gray-900">Add Consultation Note</h3>
                                <button id="closeConsultationFormModal" type="button" class="text-2xl leading-none text-gray-500 hover:text-gray-900">&times;</button>
                            </div>
                            <form id="consultationForm" class="flex min-h-0 flex-1 flex-col">
                                <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:px-8">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="noteTitle" class="mb-1 block text-sm font-medium text-gray-700">Note Title</label>
                                        <input id="noteTitle" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                        <p id="errorTitle" class="mt-1 hidden text-xs text-red-600">Note title is required.</p>
                                    </div>
                                    <div>
                                        <label for="consultationDate" class="mb-1 block text-sm font-medium text-gray-700">Consultation Date</label>
                                        <input id="consultationDate" type="date" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                        <p id="errorDate" class="mt-1 hidden text-xs text-red-600">Consultation date is required.</p>
                                    </div>
                                    <div>
                                        <label for="consultationAuthor" class="mb-1 block text-sm font-medium text-gray-700">Author / Created By</label>
                                        <input id="consultationAuthor" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="consultationCategory" class="mb-1 block text-sm font-medium text-gray-700">Tags or Category</label>
                                        <input id="consultationCategory" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="e.g. Budget Review">
                                    </div>
                                    <div>
                                        <label for="consultationLinkedDeal" class="mb-1 block text-sm font-medium text-gray-700">Linked Deal</label>
                                        <input id="consultationLinkedDeal" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Deal name or code">
                                    </div>
                                    <div>
                                        <label for="consultationLinkedActivity" class="mb-1 block text-sm font-medium text-gray-700">Linked Activity</label>
                                        <input id="consultationLinkedActivity" type="text" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Activity title">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="consultationSummary" class="mb-1 block text-sm font-medium text-gray-700">Consultation Summary</label>
                                        <textarea id="consultationSummary" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="consultationDetails" class="mb-1 block text-sm font-medium text-gray-700">Detailed Notes</label>
                                        <textarea id="consultationDetails" rows="5" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea>
                                        <p id="errorBody" class="mt-1 hidden text-xs text-red-600">Provide a summary or detailed notes.</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Attachments</label>
                                        <label for="consultationAttachments" class="flex cursor-pointer items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600 hover:bg-gray-100">
                                            <span><i class="fas fa-paperclip mr-2"></i>Upload files</span>
                                        </label>
                                        <input id="consultationAttachments" type="file" multiple class="hidden">
                                        <div id="selectedAttachments" class="mt-2 space-y-2"></div>
                                    </div>
                                </div>
                                </div>
                                <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4 sm:px-8">
                                    <button id="cancelConsultationForm" type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                                    <button id="saveConsultationNote" type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Save Consultation Note</button>
                                </div>
                            </form>
                        </div>
                        </div>
                    </div>

                    <div id="consultationViewModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
                        <button type="button" data-slideover-overlay class="absolute inset-0 bg-slate-900/45 opacity-0 transition-opacity duration-300"></button>
                        <div class="absolute inset-y-0 right-0 flex w-full justify-end overflow-hidden pointer-events-none">
                        <div data-slideover-panel class="pointer-events-auto flex h-full w-full max-w-[620px] translate-x-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 sm:px-8">
                                <h3 class="text-xl font-semibold text-gray-900">Consultation Note Details</h3>
                                <button id="closeConsultationViewModal" type="button" class="text-2xl leading-none text-gray-500 hover:text-gray-900">&times;</button>
                            </div>
                            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5 text-sm sm:px-8">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Note Title</p>
                                    <p id="viewNoteTitle" class="mt-1 text-base font-semibold text-gray-900"></p>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Consultation Date</p>
                                        <p id="viewConsultationDate" class="mt-1 text-gray-800"></p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Author</p>
                                        <p id="viewConsultationAuthor" class="mt-1 text-gray-800"></p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Linked Deal</p>
                                        <p id="viewConsultationLinkedDeal" class="mt-1 text-gray-800"></p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Linked Activity</p>
                                        <p id="viewConsultationLinkedActivity" class="mt-1 text-gray-800"></p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Summary</p>
                                    <p id="viewConsultationSummary" class="mt-1 text-gray-700"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Detailed Notes</p>
                                    <p id="viewConsultationDetails" class="mt-1 whitespace-pre-wrap text-gray-700"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attached Files</p>
                                    <div id="viewConsultationAttachments" class="mt-2 space-y-2"></div>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4 sm:px-8">
                                <button id="editFromView" type="button" class="h-10 rounded-lg border border-blue-200 bg-blue-50 px-4 text-sm text-blue-700 hover:bg-blue-100">Edit</button>
                                <button id="closeConsultationViewFooter" type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:bg-gray-50">Close</button>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                @php
                    $consultationNotesPayload = [
                        'defaultAuthor' => auth()->user()->name ?? ($contact->owner_name ?: 'System'),
                        'notes' => $tabData['consultation-notes'],
                        'storeUrl' => route('contacts.consultation-notes.store', $contact),
                        'updateUrlTemplate' => route('contacts.consultation-notes.update', [$contact, '__NOTE__']),
                        'deleteUrlTemplate' => route('contacts.consultation-notes.destroy', [$contact, '__NOTE__']),
                    ];
                @endphp
                <textarea id="consultationNotesPayload" class="hidden" hidden>{!! json_encode($consultationNotesPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</textarea>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const app = document.getElementById('consultationNotesApp');
                        if (!app) {
                            return;
                        }

                        const notesList = document.getElementById('consultationNotesList');
                        const openButton = document.getElementById('openConsultationNoteModal');
                        const formModal = document.getElementById('consultationFormModal');
                        const viewModal = document.getElementById('consultationViewModal');
                        const formTitle = document.getElementById('consultationFormTitle');
                        const form = document.getElementById('consultationForm');
                        const saveButton = document.getElementById('saveConsultationNote');
                        const attachmentInput = document.getElementById('consultationAttachments');
                        const selectedAttachments = document.getElementById('selectedAttachments');

                        const fields = {
                            title: document.getElementById('noteTitle'),
                            consultationDate: document.getElementById('consultationDate'),
                            author: document.getElementById('consultationAuthor'),
                            linkedDeal: document.getElementById('consultationLinkedDeal'),
                            linkedActivity: document.getElementById('consultationLinkedActivity'),
                            summary: document.getElementById('consultationSummary'),
                            details: document.getElementById('consultationDetails'),
                            category: document.getElementById('consultationCategory'),
                        };

                        const errors = {
                            title: document.getElementById('errorTitle'),
                            consultationDate: document.getElementById('errorDate'),
                            body: document.getElementById('errorBody'),
                        };

                        const readJsonPayload = (id, fallback = {}) => {
                            try {
                                return JSON.parse(document.getElementById(id)?.textContent || '');
                            } catch (error) {
                                return fallback;
                            }
                        };
                        const consultationPayload = readJsonPayload('consultationNotesPayload', {});
                        const defaultAuthor = consultationPayload.defaultAuthor || 'System';
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const storeUrl = consultationPayload.storeUrl || '';
                        const updateUrlTemplate = consultationPayload.updateUrlTemplate || '';
                        const deleteUrlTemplate = consultationPayload.deleteUrlTemplate || '';
                        let notes = Array.isArray(consultationPayload.notes) ? consultationPayload.notes : [];
                        let editNoteId = null;
                        let viewNoteId = null;
                        let formAttachments = [];

                        const formatDate = (value) => {
                            if (!value) return '-';
                            const date = new Date(value + 'T00:00:00');
                            return Number.isNaN(date.getTime())
                                ? value
                                : new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric' }).format(date);
                        };

                        const formatBytes = (bytes) => {
                            if (!bytes || Number.isNaN(Number(bytes))) return '-';
                            if (bytes < 1024) return `${bytes} B`;
                            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
                            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
                        };

                        const normalizeType = (name, rawType) => {
                            if (rawType) {
                                const parts = rawType.split('/');
                                if (parts.length > 1 && parts[1]) return parts[1].toUpperCase();
                            }
                            const ext = name.includes('.') ? name.split('.').pop() : 'FILE';
                            return String(ext).toUpperCase();
                        };

                        const noteUrl = (template, noteId) => template.replace('__NOTE__', String(noteId));

                        const requestJson = async (url, method, payload) => {
                            const response = await fetch(url, {
                                method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify(payload),
                            });

                            if (!response.ok) {
                                throw new Error('Unable to save consultation note.');
                            }

                            return response.status === 204 ? null : response.json();
                        };

                        const node = (tag, className = '', text = null) => {
                            const element = document.createElement(tag);
                            if (className) element.className = className;
                            if (text !== null && text !== undefined) element.textContent = text;
                            return element;
                        };

                        const metaSpan = (iconClass, text) => {
                            const span = node('span');
                            const icon = node('i', `${iconClass} mr-1`);
                            span.append(icon, document.createTextNode(text));
                            return span;
                        };

                        const attachmentRow = (file, options = {}) => {
                            const row = node('div', options.className || 'flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm');
                            const detail = node('div', 'min-w-0');
                            detail.append(
                                node('p', 'truncate font-medium text-gray-800', file.name),
                                node('p', 'text-xs text-gray-500', `${file.type || 'FILE'} | ${formatBytes(file.size)}`)
                            );

                            if (options.viewLink) {
                                const link = node('a', 'text-sm text-blue-600 hover:text-blue-700', 'View');
                                link.href = file.url || '#';
                                link.target = '_blank';
                                link.rel = 'noopener noreferrer';
                                row.append(detail, link);
                                return row;
                            }

                            const button = node('button', 'remove-attachment text-gray-500 hover:text-red-600');
                            button.type = 'button';
                            button.dataset.fileId = String(file.id);
                            button.appendChild(node('i', 'fas fa-xmark'));
                            row.append(detail, button);
                            return row;
                        };

                        const sortNotes = () => {
                            notes.sort((a, b) => {
                                const left = new Date(b.consultationDate || 0).getTime();
                                const right = new Date(a.consultationDate || 0).getTime();
                                if (left !== right) return left - right;
                                return new Date(b.updatedAt || 0).getTime() - new Date(a.updatedAt || 0).getTime();
                            });
                        };

                        const renderAttachmentsForForm = () => {
                            if (!formAttachments.length) {
                                selectedAttachments.replaceChildren(node('p', 'text-xs text-gray-500', 'No files selected.'));
                                return;
                            }
                            selectedAttachments.replaceChildren(...formAttachments.map((file) => attachmentRow(file)));
                        };

                        const renderNotes = () => {
                            sortNotes();
                            if (!notes.length) {
                                notesList.replaceChildren(node('div', 'rounded-xl border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500', 'No consultation notes yet.'));
                                return;
                            }
                            notesList.replaceChildren(...notes.map((note) => {
                                const attachmentCount = (note.attachments || []).length;
                                const attachmentLabel = `${attachmentCount} attachment${attachmentCount === 1 ? '' : 's'}`;
                                const article = node('article', 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm');
                                const layout = node('div', 'flex items-start justify-between gap-3');
                                const body = node('div', 'min-w-0');
                                const meta = node('div', 'mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500');
                                meta.append(
                                    metaSpan('far fa-calendar', formatDate(note.consultationDate)),
                                    metaSpan('far fa-user', note.author || defaultAuthor),
                                    metaSpan('fas fa-paperclip', attachmentLabel)
                                );
                                body.append(
                                    node('h3', 'text-xl font-semibold text-gray-900', note.title),
                                    node('p', 'mt-1 text-sm text-gray-600', note.summary || note.details || ''),
                                    node('p', 'mt-2 text-xs text-gray-500', [note.linkedDeal, note.linkedActivity].filter(Boolean).join(' • ') || 'No linked deal or activity'),
                                    meta
                                );

                                const actions = node('div', 'flex items-center gap-3 text-gray-500');
                                const viewButton = node('button', 'note-view hover:text-blue-600');
                                viewButton.type = 'button';
                                viewButton.dataset.noteId = String(note.id);
                                viewButton.setAttribute('aria-label', 'View note');
                                viewButton.appendChild(node('i', 'far fa-eye'));

                                const editButton = node('button', 'note-edit hover:text-blue-600');
                                editButton.type = 'button';
                                editButton.dataset.noteId = String(note.id);
                                editButton.setAttribute('aria-label', 'Edit note');
                                editButton.appendChild(node('i', 'far fa-pen-to-square'));

                                const deleteButton = node('button', 'note-delete hover:text-red-600');
                                deleteButton.type = 'button';
                                deleteButton.dataset.noteId = String(note.id);
                                deleteButton.setAttribute('aria-label', 'Delete note');
                                deleteButton.appendChild(node('i', 'far fa-trash-can'));

                                actions.append(viewButton, editButton, deleteButton);
                                layout.append(body, actions);
                                article.appendChild(layout);
                                return article;
                            }));
                        };

                        const showModal = (modal) => {
                            const panel = modal.querySelector('[data-slideover-panel]');
                            const overlay = modal.querySelector('[data-slideover-overlay]');
                            modal.classList.remove('hidden');
                            modal.setAttribute('aria-hidden', 'false');
                            document.body.classList.add('overflow-hidden');
                            requestAnimationFrame(() => {
                                overlay?.classList.remove('opacity-0');
                                panel?.classList.remove('translate-x-full');
                            });
                        };

                        const hideModal = (modal) => {
                            const panel = modal.querySelector('[data-slideover-panel]');
                            const overlay = modal.querySelector('[data-slideover-overlay]');
                            overlay?.classList.add('opacity-0');
                            panel?.classList.add('translate-x-full');
                            window.setTimeout(() => {
                                modal.classList.add('hidden');
                                modal.setAttribute('aria-hidden', 'true');
                                if (formModal.classList.contains('hidden') && viewModal.classList.contains('hidden')) {
                                    document.body.classList.remove('overflow-hidden');
                                }
                            }, 300);
                        };

                        const resetValidation = () => {
                            Object.values(errors).forEach((el) => el.classList.add('hidden'));
                        };

                        const resetForm = () => {
                            editNoteId = null;
                            fields.title.value = '';
                            fields.consultationDate.value = '';
                            fields.author.value = defaultAuthor;
                            fields.linkedDeal.value = '';
                            fields.linkedActivity.value = '';
                            fields.summary.value = '';
                            fields.details.value = '';
                            fields.category.value = '';
                            formAttachments = [];
                            attachmentInput.value = '';
                            resetValidation();
                            renderAttachmentsForForm();
                        };

                        const openAddModal = () => {
                            resetForm();
                            fields.consultationDate.value = new Date().toISOString().slice(0, 10);
                            formTitle.textContent = 'Add Consultation Note';
                            saveButton.textContent = 'Save Consultation Note';
                            showModal(formModal);
                        };

                        const openEditModal = (noteId) => {
                            const note = notes.find((item) => Number(item.id) === Number(noteId));
                            if (!note) return;

                            editNoteId = Number(note.id);
                            fields.title.value = note.title || '';
                            fields.consultationDate.value = note.consultationDate || '';
                            fields.author.value = note.author || defaultAuthor;
                            fields.linkedDeal.value = note.linkedDeal || '';
                            fields.linkedActivity.value = note.linkedActivity || '';
                            fields.summary.value = note.summary || '';
                            fields.details.value = note.details || '';
                            fields.category.value = note.category || '';
                            formAttachments = (note.attachments || []).map((file) => ({ ...file }));
                            attachmentInput.value = '';
                            resetValidation();
                            renderAttachmentsForForm();

                            formTitle.textContent = 'Edit Consultation Note';
                            saveButton.textContent = 'Update Consultation Note';
                            showModal(formModal);
                        };

                        const openViewModal = (noteId) => {
                            const note = notes.find((item) => Number(item.id) === Number(noteId));
                            if (!note) return;

                            viewNoteId = Number(note.id);
                            document.getElementById('viewNoteTitle').textContent = note.title || '-';
                            document.getElementById('viewConsultationDate').textContent = formatDate(note.consultationDate);
                            document.getElementById('viewConsultationAuthor').textContent = note.author || '-';
                            document.getElementById('viewConsultationLinkedDeal').textContent = note.linkedDeal || '-';
                            document.getElementById('viewConsultationLinkedActivity').textContent = note.linkedActivity || '-';
                            document.getElementById('viewConsultationSummary').textContent = note.summary || '-';
                            document.getElementById('viewConsultationDetails').textContent = note.details || '-';

                            const viewAttachmentList = document.getElementById('viewConsultationAttachments');
                            const attachments = note.attachments || [];
                            if (!attachments.length) {
                                viewAttachmentList.replaceChildren(node('p', 'text-xs text-gray-500', 'No attachments'));
                            } else {
                                viewAttachmentList.replaceChildren(...attachments.map((file) => attachmentRow(file, {
                                    className: 'flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2',
                                    viewLink: true,
                                })));
                            }

                            showModal(viewModal);
                        };

                        const validateForm = () => {
                            resetValidation();
                            let valid = true;
                            if (!fields.title.value.trim()) {
                                errors.title.classList.remove('hidden');
                                valid = false;
                            }
                            if (!fields.consultationDate.value) {
                                errors.consultationDate.classList.remove('hidden');
                                valid = false;
                            }
                            if (!fields.summary.value.trim() && !fields.details.value.trim()) {
                                errors.body.classList.remove('hidden');
                                valid = false;
                            }
                            return valid;
                        };

                        openButton.addEventListener('click', openAddModal);

                        document.getElementById('closeConsultationFormModal').addEventListener('click', () => hideModal(formModal));
                        document.getElementById('cancelConsultationForm').addEventListener('click', () => hideModal(formModal));
                        document.getElementById('closeConsultationViewModal').addEventListener('click', () => hideModal(viewModal));
                        document.getElementById('closeConsultationViewFooter').addEventListener('click', () => hideModal(viewModal));
                        document.getElementById('editFromView').addEventListener('click', () => {
                            hideModal(viewModal);
                            if (viewNoteId !== null) openEditModal(viewNoteId);
                        });

                        [formModal, viewModal].forEach((modal) => {
                            modal.querySelector('[data-slideover-overlay]')?.addEventListener('click', () => hideModal(modal));
                        });
                        document.addEventListener('keydown', function (event) {
                            if (event.key !== 'Escape') return;
                            [formModal, viewModal].forEach((modal) => {
                                if (!modal.classList.contains('hidden')) hideModal(modal);
                            });
                        });

                        attachmentInput.addEventListener('change', function () {
                            const files = Array.from(attachmentInput.files || []);
                            if (!files.length) return;

                            files.forEach((file, index) => {
                                formAttachments.push({
                                    id: Date.now() + index + Math.floor(Math.random() * 1000),
                                    name: file.name,
                                    type: normalizeType(file.name, file.type),
                                    size: file.size || 0,
                                    url: URL.createObjectURL(file),
                                });
                            });

                            attachmentInput.value = '';
                            renderAttachmentsForForm();
                        });

                        selectedAttachments.addEventListener('click', function (event) {
                            const button = event.target.closest('.remove-attachment');
                            if (!button) return;
                            const targetId = Number(button.dataset.fileId);
                            formAttachments = formAttachments.filter((file) => Number(file.id) !== targetId);
                            renderAttachmentsForForm();
                        });

                        notesList.addEventListener('click', function (event) {
                            const viewBtn = event.target.closest('.note-view');
                            const editBtn = event.target.closest('.note-edit');
                            const deleteBtn = event.target.closest('.note-delete');

                            if (viewBtn) {
                                openViewModal(viewBtn.dataset.noteId);
                            }
                            if (editBtn) {
                                openEditModal(editBtn.dataset.noteId);
                            }
                            if (deleteBtn) {
                                const noteId = Number(deleteBtn.dataset.noteId);
                                if (!noteId || !window.confirm('Delete this consultation note?')) {
                                    return;
                                }

                                requestJson(noteUrl(deleteUrlTemplate, noteId), 'DELETE', {})
                                    .then(() => {
                                        notes = notes.filter((item) => Number(item.id) !== noteId);
                                        renderNotes();
                                    })
                                    .catch((error) => window.alert(error.message));
                            }
                        });

                        form.addEventListener('submit', async function (event) {
                            event.preventDefault();
                            if (!validateForm()) return;

                            const payload = {
                                title: fields.title.value.trim(),
                                consultation_date: fields.consultationDate.value,
                                author: fields.author.value.trim() || defaultAuthor,
                                linked_deal: fields.linkedDeal.value.trim(),
                                linked_activity: fields.linkedActivity.value.trim(),
                                summary: fields.summary.value.trim(),
                                details: fields.details.value.trim(),
                                category: fields.category.value.trim(),
                                attachments: formAttachments.map((item) => ({ ...item })),
                            };

                            try {
                                const savedNote = editNoteId !== null
                                    ? await requestJson(noteUrl(updateUrlTemplate, editNoteId), 'PUT', payload)
                                    : await requestJson(storeUrl, 'POST', payload);

                                if (editNoteId !== null) {
                                    notes = notes.map((item) => Number(item.id) === editNoteId ? savedNote : item);
                                } else {
                                    notes.unshift(savedNote);
                                }

                                renderNotes();
                                hideModal(formModal);
                            } catch (error) {
                                window.alert(error.message);
                            }
                        });

                        renderNotes();
                    });
                </script>
            @endif

            @if ($tab === 'activities')
                @php
                    $activityContactName = trim($contact->first_name.' '.$contact->last_name) ?: ($contact->company_name ?: 'Contact');
                    $activityContactLabel = trim(collect([$activityContactName, $contact->company_name])->filter()->implode(' - '));
                    $activityContactEmail = $contact->email ?: '';
                    $activityContactPayload = [
                        'contact_id' => $contact->id,
                        'company_id' => \App\Support\ActivityRelationships::companyId($contact),
                        'name' => $activityContactName,
                        'label' => $activityContactLabel,
                        'email' => $activityContactEmail,
                        'company' => $contact->company_name ?: '',
                        'phone' => $contact->phone ?: '',
                    ];
                @endphp

                <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                    <button type="button" onclick="openContactActivityShortcut('events')" class="h-10 rounded border">Add Event</button>
                    <button type="button" onclick="openContactActivityShortcut('messages')" class="h-10 rounded border">Add Message</button>
                    <button type="button" onclick="openContactActivityShortcut('call')" class="h-10 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-phone mr-1"></i>Log Call
                    </button>

                    <button type="button" onclick="openContactActivityShortcut('meetings')" class="h-10 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-video mr-1"></i>Schedule Meeting
                    </button>

                    <button type="button" onclick="openContactEmailShortcut()" class="h-10 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-envelope mr-1"></i>Send Email
                    </button>

                    <button type="button" onclick="openContactActivityShortcut('task')" class="h-10 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-square-check mr-1"></i>Add Task
                    </button>
                </div>

                <script>
                    window.contactActivityPayload = @json($activityContactPayload);

                    function openContactActivityShortcut(type) {
                        const payload = window.contactActivityPayload || {};

                        localStorage.setItem('activity_contact_prefill', JSON.stringify({
                            type: type, contact_id: payload.contact_id, company_id: payload.company_id,
                            contact_name: payload.name || '',
                            related_contact: payload.label || payload.name || '',
                            email: payload.email || '',
                            company: payload.company || '',
                            phone: payload.phone || ''
                        }));

                        window.location.href = "{{ url('/activities') }}?open=" + encodeURIComponent(type);
                    }

                    function openContactEmailShortcut() {
                        const payload = window.contactActivityPayload || {};
                        const email = payload.email || '';

                        if (!email) {
                            alert('This contact has no email address.');
                            return;
                        }

                        openContactActivityShortcut('email');
                    }
                </script>

                @include('partials.activities-app', [
                    'appId' => 'contactActivitiesApp',
                    'listId' => 'contactActivitiesList',
                    'title' => 'Activities',
                    'description' => 'Manage contact activities with the same activity workspace used in the company module.',
                    'openUrl' => route('activities'),
                    'openLabel' => 'Open Main Activities',
                    'editable' => false,
                    'activities' => $tabData['activities'],
                ])
            @endif

            @if ($tab === 'deals')
                @php
                    $dealStageClasses = [
                        'Inquiry' => 'bg-slate-100 text-slate-700 border border-slate-200',
                        'Qualification' => 'bg-blue-100 text-blue-700 border border-blue-200',
                        'Consultation' => 'bg-indigo-100 text-indigo-700 border border-indigo-200',
                        'Proposal' => 'bg-cyan-100 text-cyan-700 border border-cyan-200',
                        'Negotiation' => 'bg-amber-100 text-amber-700 border border-amber-200',
                        'Payment' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                        'Activation' => 'bg-violet-100 text-violet-700 border border-violet-200',
                        'Closed Lost' => 'bg-red-100 text-red-700 border border-red-200',
                    ];
                    $dealStatusClasses = [
                        'Open' => 'bg-blue-100 text-blue-700 border border-blue-200',
                        'Won' => 'bg-green-100 text-green-700 border border-green-200',
                        'Lost' => 'bg-red-100 text-red-700 border border-red-200',
                        'Pending' => 'bg-amber-100 text-amber-700 border border-amber-200',
                    ];
                @endphp
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900">Related Deals</h2>
                        <p class="text-sm text-gray-500">Track all deals associated with this contact</p>
                    </div>
                    <a href="{{ route('deals.index') }}" class="inline-flex h-10 items-center rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">+ Open Deals</a>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-3 py-3 text-left">Deal Name</th>
                                <th class="px-3 py-3 text-left">Stage</th>
                                <th class="px-3 py-3 text-left">Closing Date</th>
                                <th class="px-3 py-3 text-left">Owner</th>
                                <th class="px-3 py-3 text-left">Status</th>
                                <th class="px-3 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tabData['deals'] as $deal)
                                <tr>
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ $deal['name'] }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $dealStageClasses[$deal['stage']] ?? 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                                            {{ $deal['stage'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-700">{{ $deal['closing_date'] }}</td>
                                    <td class="px-3 py-3 text-gray-700">{{ $deal['owner'] }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $dealStatusClasses[$deal['status']] ?? 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                                            {{ $deal['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3"><a href="{{ $deal['show_url'] ?? route('deals.index') }}" class="text-blue-600 hover:text-blue-700"><i class="far fa-eye mr-1"></i>View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($tab === 'company')
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900">Related Companies</h2>
                        <p class="text-sm text-gray-500">Track companies linked to this contact.</p>
                    </div>
                    <a
                        href="{{ route('company.index', ['prefill_contact' => $contact->id, 'open_add_company' => 1]) }}"
                        class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        + Add Company
                    </a>
                </div>
                @php
                    $companyCustomFields = $companyCustomFields ?? [];
                @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <form method="GET" action="{{ route('contacts.show', $contact->id) }}" class="flex flex-wrap items-center gap-3">
                            <input type="hidden" name="tab" value="company">
                            <div class="relative w-full max-w-md">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                                <input
                                    type="text"
                                    name="company_search"
                                    value="{{ $companySearch }}"
                                    placeholder="Search company name..."
                                    class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-8 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                                <tr>
                                    <th class="w-10 px-3 py-3 text-left"><input type="checkbox" class="h-4 w-4 rounded border-gray-300"></th>
                                    <th class="px-3 py-3 text-left">Company Name</th>
                                    <th class="px-3 py-3 text-left">Email</th>
                                    <th class="px-3 py-3 text-left">Phone</th>
                                    <th class="px-3 py-3 text-left">Owner</th>
                                    <th class="px-3 py-3 text-left">Status</th>
                                    <th class="px-3 py-3 text-left">Action</th>
                                    @foreach ($companyCustomFields as $field)
                                        <th class="px-3 py-3 text-left">{{ $field['name'] }}</th>
                                    @endforeach
                                    <th class="px-3 py-3 text-right normal-case">
                                        <button id="openContactCompanyCreateFieldDropdown" type="button" class="text-sm font-medium text-blue-600 hover:text-blue-700">+ Create Field</button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($companyTabCompanies as $companyItem)
                                    <tr class="text-gray-700">
                                        <td class="px-3 py-3"><input type="checkbox" class="h-4 w-4 rounded border-gray-300"></td>
                                        <td class="px-3 py-3 font-medium text-gray-900">{{ $companyItem['company_name'] }}</td>
                                        <td class="px-3 py-3">{{ $companyItem['email'] }}</td>
                                        <td class="px-3 py-3">{{ $companyItem['phone'] }}</td>
                                        <td class="px-3 py-3">{{ $companyItem['owner'] }}</td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ $companyItem['status'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ $companyItem['show_url'] }}" class="inline-flex h-8 items-center rounded-full border border-gray-200 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                    <i class="far fa-eye mr-1"></i>View
                                                </a>
                                                <form method="POST" action="{{ route('contacts.companies.unlink', ['contact' => $contact->id, 'company' => $companyItem['id']]) }}" onsubmit="return confirm('Unlink this company from the current contact?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex h-8 items-center rounded-full border border-red-200 px-3 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @foreach ($companyCustomFields as $field)
                                            @php
                                                $customValue = $companyItem['custom_fields'][$field['key']] ?? ($field['default_value'] ?? '');
                                            @endphp
                                            <td class="px-3 py-3 text-gray-600">
                                                @if (($field['type'] ?? '') === 'checkbox')
                                                    {{ $customValue === '1' ? 'Yes' : 'No' }}
                                                @elseif (($field['type'] ?? '') === 'currency' && $customValue !== '')
                                                    P{{ number_format((float) $customValue, 2) }}
                                                @elseif (is_array($customValue))
                                                    {{ implode(', ', array_filter($customValue, fn ($value) => filled($value))) ?: '-' }}
                                                @else
                                                    {{ $customValue !== '' ? $customValue : '-' }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-3"></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 8 + count($companyCustomFields) }}" class="px-3 py-10 text-center text-sm text-gray-500">No related companies found for this contact.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @include('products.partials.create-field-dropdown', [
                    'fieldTypes' => $fieldTypes,
                    'dropdownId' => 'contactCompanyCreateFieldDropdownMenu',
                ])
                @include('products.partials.create-field-modal', [
                    'createFieldActionRoute' => route('company.custom-fields.store'),
                    'lookupModules' => $lookupModules,
                ])

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const openCreateFieldDropdown = document.getElementById('openContactCompanyCreateFieldDropdown');
                        const createFieldDropdownMenu = document.getElementById('contactCompanyCreateFieldDropdownMenu');
                        const fieldTypeButtons = Array.from(document.querySelectorAll('#contactCompanyCreateFieldDropdownMenu .create-field-type-option'));
                        const createFieldModal = document.getElementById('createFieldModal');
                        const createFieldPanel = document.getElementById('createFieldPanel');
                        const createFieldModalOverlay = document.getElementById('createFieldModalOverlay');
                        const closeCreateFieldModal = document.getElementById('closeCreateFieldModal');
                        const cancelCreateFieldModal = document.getElementById('cancelCreateFieldModal');
                        const createFieldTypeInput = document.getElementById('createFieldTypeInput');
                        const createFieldTypeLabel = document.getElementById('createFieldTypeLabel');
                        const picklistOptionsSection = document.getElementById('picklistOptionsSection');
                        const picklistOptionsContainer = document.getElementById('picklistOptionsContainer');
                        const addPicklistOption = document.getElementById('addPicklistOption');
                        const defaultValueSection = document.getElementById('defaultValueSection');
                        const lookupSection = document.getElementById('lookupSection');
                        const defaultValueInput = document.getElementById('default_value');
                        let createFieldDropdownOpen = false;

                        if (!openCreateFieldDropdown || !createFieldDropdownMenu) {
                            return;
                        }

                        const buildPicklistOptionRow = (value = '') => {
                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-2';

                            const input = document.createElement('input');
                            input.name = 'options[]';
                            input.value = value;
                            input.placeholder = 'Option value';
                            input.className = 'h-10 flex-1 rounded-md border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'remove-picklist-option h-8 w-8 rounded-full border border-gray-300 text-gray-500 hover:bg-gray-50';

                            const icon = document.createElement('i');
                            icon.className = 'fas fa-minus text-xs';
                            button.appendChild(icon);

                            row.append(input, button);

                            return row;
                        };

                        const ensurePicklistOptionRows = () => {
                            if (!picklistOptionsContainer) {
                                return;
                            }

                            if (picklistOptionsContainer.querySelectorAll('input[name="options[]"]').length === 0) {
                                picklistOptionsContainer.appendChild(buildPicklistOptionRow(''));
                            }
                        };

                        const applyCreateFieldTypeUI = (type, label) => {
                            if (!createFieldTypeInput || !createFieldTypeLabel) {
                                return;
                            }

                            createFieldTypeInput.value = type;
                            createFieldTypeLabel.textContent = label;
                            picklistOptionsSection?.classList.toggle('hidden', type !== 'picklist');
                            lookupSection?.classList.toggle('hidden', type !== 'lookup');
                            lookupSection?.classList.toggle('grid', type === 'lookup');
                            defaultValueSection?.classList.toggle('hidden', type === 'lookup');
                            defaultValueSection?.classList.toggle('grid', type !== 'lookup');

                            if (defaultValueInput) {
                                defaultValueInput.placeholder = type === 'date' ? 'YYYY-MM-DD' : 'Optional default value';
                            }

                            if (type === 'picklist') {
                                ensurePicklistOptionRows();
                            }
                        };

                        const positionCreateFieldDropdown = () => {
                            const rect = openCreateFieldDropdown.getBoundingClientRect();
                            const dropdownWidth = createFieldDropdownMenu.offsetWidth || 256;
                            const viewportPadding = 12;

                            let left = rect.left;
                            if (left + dropdownWidth > window.innerWidth - viewportPadding) {
                                left = rect.right - dropdownWidth;
                            }
                            if (left < viewportPadding) {
                                left = viewportPadding;
                            }

                            let top = rect.bottom + 8;
                            const dropdownHeight = createFieldDropdownMenu.offsetHeight || 320;
                            if (top + dropdownHeight > window.innerHeight - viewportPadding) {
                                top = Math.max(viewportPadding, rect.top - dropdownHeight - 8);
                            }

                            createFieldDropdownMenu.style.left = `${left}px`;
                            createFieldDropdownMenu.style.top = `${top}px`;
                        };

                        const closeCreateFieldDropdownFn = () => {
                            createFieldDropdownMenu.classList.add('hidden');
                            createFieldDropdownOpen = false;
                        };

                        const openCreateFieldModalFn = (type, label) => {
                            applyCreateFieldTypeUI(type, label);
                            closeCreateFieldDropdownFn();
                            createFieldModal?.classList.remove('hidden');
                            createFieldModal?.setAttribute('aria-hidden', 'false');
                            document.body.classList.add('overflow-hidden');
                            requestAnimationFrame(() => {
                                createFieldModalOverlay?.classList.remove('opacity-0');
                                createFieldPanel?.classList.remove('translate-x-full');
                            });
                        };

                        const closeCreateFieldModalFn = () => {
                            createFieldModalOverlay?.classList.add('opacity-0');
                            createFieldPanel?.classList.add('translate-x-full');
                            document.body.classList.remove('overflow-hidden');
                            window.setTimeout(() => {
                                createFieldModal?.classList.add('hidden');
                                createFieldModal?.setAttribute('aria-hidden', 'true');
                            }, 300);
                        };

                        openCreateFieldDropdown.addEventListener('click', function () {
                            if (createFieldDropdownOpen) {
                                closeCreateFieldDropdownFn();
                                return;
                            }

                            createFieldDropdownMenu.classList.remove('hidden');
                            createFieldDropdownOpen = true;
                            positionCreateFieldDropdown();
                        });

                        fieldTypeButtons.forEach((button) => {
                            button.addEventListener('click', function () {
                                const type = button.dataset.fieldType || 'picklist';
                                const label = button.dataset.fieldLabel || 'Picklist';
                                openCreateFieldModalFn(type, label);
                            });
                        });

                        closeCreateFieldModal?.addEventListener('click', closeCreateFieldModalFn);
                        cancelCreateFieldModal?.addEventListener('click', closeCreateFieldModalFn);
                        createFieldModalOverlay?.addEventListener('click', closeCreateFieldModalFn);

                        document.addEventListener('click', function (event) {
                            const clickedFieldTrigger = openCreateFieldDropdown.contains(event.target);
                            if (createFieldDropdownOpen && !createFieldDropdownMenu.contains(event.target) && !clickedFieldTrigger) {
                                closeCreateFieldDropdownFn();
                            }
                        });

                        document.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape') {
                                closeCreateFieldDropdownFn();
                                closeCreateFieldModalFn();
                            }
                        });

                        window.addEventListener('resize', function () {
                            if (createFieldDropdownOpen) {
                                positionCreateFieldDropdown();
                            }
                        });

                        document.addEventListener('scroll', function () {
                            if (createFieldDropdownOpen) {
                                positionCreateFieldDropdown();
                            }
                        }, true);

                        addPicklistOption?.addEventListener('click', function () {
                            picklistOptionsContainer?.appendChild(buildPicklistOptionRow(''));
                        });

                        picklistOptionsContainer?.addEventListener('click', function (event) {
                            const button = event.target.closest('.remove-picklist-option');
                            if (!button) {
                                return;
                            }

                            button.closest('.flex')?.remove();
                            ensurePicklistOptionRows();
                        });

                        const initialFieldType = createFieldTypeInput ? createFieldTypeInput.value : 'picklist';
                        const initialTypeButton = fieldTypeButtons.find((button) => (button.dataset.fieldType || '') === initialFieldType);
                        applyCreateFieldTypeUI(initialFieldType, initialTypeButton?.dataset.fieldLabel || 'Picklist');
                    });
                </script>
            @endif

            @if ($tab === 'projects')
                <div class="rounded-md border border-gray-200 bg-white overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-4">
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900">PROJECTS</h2>

                        <div class="mt-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div class="relative w-full sm:w-[320px]">
                                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <input
                                        type="text"
                                        placeholder="Search projects..."
                                        class="w-full h-9 rounded border border-gray-200 bg-white pl-11 pr-4 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                    >
                                </div>

                                <select class="h-9 rounded border border-gray-200 bg-white px-3 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                    <option>All</option>
                                    <option>In Progress</option>
                                    <option>Completed</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-600">All: {{ count($tabData['projects']) }}</span>
                                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">Active: {{ collect($tabData['projects'])->where('status', '!=', 'Completed')->count() }}</span>
                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Completed: {{ collect($tabData['projects'])->where('status', 'Completed')->count() }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="border border-gray-200 rounded-md bg-white overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                                        <tr>
                                            <th class="w-10 px-3 py-3 text-left">
                                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                            </th>
                                            <th class="px-3 py-3 text-left font-medium">Project Name</th>
                                            <th class="px-3 py-3 text-left font-medium">Status</th>
                                            <th class="px-3 py-3 text-left font-medium">Start Date</th>
                                            <th class="px-3 py-3 text-left font-medium">End Date</th>
                                            <th class="px-3 py-3 text-left font-medium">Owner</th>
                                            <th class="px-3 py-3 text-right font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white text-gray-700">
                                        @forelse ($tabData['projects'] as $project)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-3">
                                                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                                </td>
                                                <td class="px-3 py-3 font-medium text-gray-800">{{ $project['name'] }}</td>
                                                <td class="px-3 py-3">
                                                    @if ($project['status'] === 'Completed')
                                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Completed</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">In Progress</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3">{{ $project['start_date'] }}</td>
                                                <td class="px-3 py-3">{{ $project['end_date'] }}</td>
                                                <td class="px-3 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-7 w-7 rounded-full bg-gray-100 border border-gray-200 text-[11px] font-semibold text-gray-600 inline-flex items-center justify-center">{{ $project['owner_initials'] }}</span>
                                                        <span>{{ $project['owner'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <a href="{{ $project['show_url'] ?? route('project.index') }}" class="inline-flex h-8 items-center rounded-full bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">View</a>
                                                        <button class="h-8 w-8 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                                                            <i class="fas fa-ellipsis-v text-xs"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-3 py-10 text-center text-sm text-gray-500">No projects linked to this contact yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 px-4 py-3 flex flex-wrap items-center justify-end gap-3 text-sm text-gray-500">
                        <span>{{ count($tabData['projects']) ? '1-'.count($tabData['projects']).' of '.count($tabData['projects']).' results' : '0 results' }}</span>
                        <button class="h-8 rounded border border-gray-200 px-3 hover:bg-gray-50">Previous</button>
                        <button class="h-8 rounded border border-gray-200 px-3 hover:bg-gray-50">Next</button>
                        <select class="h-8 rounded border border-gray-200 bg-white px-2 text-sm text-gray-700">
                            <option>10 per page</option>
                        </select>
                    </div>
                </div>
            @endif

            @if ($tab === 'regular')
                <div class="rounded-md border border-gray-200 bg-white overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-4">
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900">REGULAR</h2>
                        <p class="mt-1 text-sm text-gray-500">Manage regular engagements of the contact.</p>

                        <div class="mt-4 grid grid-cols-1 gap-2 lg:grid-cols-12">
                            <div class="relative lg:col-span-4">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input
                                    type="text"
                                    placeholder="Search engagements..."
                                    class="w-full h-10 rounded border border-gray-200 bg-white pl-11 pr-4 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                >
                            </div>

                            <div class="lg:col-span-2">
                                <select class="w-full h-10 rounded border border-gray-200 bg-white px-3 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                    <option>Frequency: All</option>
                                    <option>Frequency: Monthly</option>
                                    <option>Frequency: Quarterly</option>
                                    <option>Frequency: Annual</option>
                                </select>
                            </div>

                            <div class="lg:col-span-2">
                                <select class="w-full h-10 rounded border border-gray-200 bg-white px-3 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                    <option>Status: All</option>
                                    <option>Status: Active</option>
                                </select>
                            </div>

                            <div class="lg:col-span-1">
                                <span class="inline-flex h-10 w-full items-center justify-center rounded border border-gray-200 bg-gray-50 px-2 text-sm font-medium text-gray-600">{{ count($tabData['regular']) }}</span>
                            </div>

                            <div class="lg:col-span-1">
                                <button class="w-full h-10 rounded border border-gray-200 text-gray-700 text-sm hover:bg-gray-50 inline-flex items-center justify-center">
                                    <i class="fas fa-sort text-xs"></i>
                                </button>
                            </div>

                            <div class="lg:col-span-2">
                                <a href="{{ route('regular.index') }}" class="w-full h-10 rounded-full bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700 inline-flex items-center justify-center gap-2">
                                    <span class="text-base leading-none">+</span>
                                    <span>Open Regular</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="border border-gray-200 rounded-md bg-white overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                                        <tr>
                                            <th class="w-10 px-3 py-3 text-left">
                                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                            </th>
                                            <th class="px-3 py-3 text-left font-medium">Engagement Name</th>
                                            <th class="px-3 py-3 text-left font-medium">Frequency</th>
                                            <th class="px-3 py-3 text-left font-medium">Status</th>
                                            <th class="px-3 py-3 text-left font-medium">Start Date</th>
                                            <th class="px-3 py-3 text-left font-medium">Next Billing Date</th>
                                            <th class="px-3 py-3 text-left font-medium">Owner</th>
                                            <th class="px-3 py-3 text-right font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white text-gray-700">
                                        @forelse ($tabData['regular'] as $engagement)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-3">
                                                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                                </td>
                                                <td class="px-3 py-3 font-medium text-gray-800">{{ $engagement['name'] }}</td>
                                                <td class="px-3 py-3">{{ $engagement['frequency'] }}</td>
                                                <td class="px-3 py-3">
                                                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ $engagement['status'] }}</span>
                                                </td>
                                                <td class="px-3 py-3">{{ $engagement['start_date'] }}</td>
                                                <td class="px-3 py-3">{{ $engagement['next_billing_date'] }}</td>
                                                <td class="px-3 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="h-7 w-7 rounded-full bg-gray-100 border border-gray-200 text-[11px] font-semibold text-gray-600 inline-flex items-center justify-center">{{ $engagement['owner_initials'] }}</span>
                                                        <span>{{ $engagement['owner'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <a href="{{ $engagement['show_url'] ?? route('regular.index') }}" class="inline-flex h-8 items-center rounded-full bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">View</a>
                                                        <button class="h-8 w-8 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                                                            <i class="fas fa-ellipsis-v text-xs"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-3 py-10 text-center text-sm text-gray-500">No regular engagements linked to this contact yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 px-4 py-3 flex flex-wrap items-center justify-end gap-3 text-sm text-gray-500">
                        <span>{{ count($tabData['regular']) ? '1-'.count($tabData['regular']).' of '.count($tabData['regular']).' results' : '0 results' }}</span>
                        <button class="h-8 rounded border border-gray-200 px-3 hover:bg-gray-50">Previous</button>
                        <button class="h-8 rounded border border-gray-200 px-3 hover:bg-gray-50">Next</button>
                        <select class="h-8 rounded border border-gray-200 bg-white px-2 text-sm text-gray-700">
                            <option>10 per page</option>
                        </select>
                    </div>
                </div>
            @endif

            @if ($tab === 'products')
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900">Products Availed</h2>
                        <p class="text-sm text-gray-500">Products tied to this contact through linked deals.</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $tabData['products']['total_products'] }} {{ \Illuminate\Support\Str::plural('product', $tabData['products']['total_products']) }}
                        <span class="mx-2 text-gray-300">|</span>
                        Total value {{ $tabData['products']['total_revenue'] }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-700">
                                <tr>
                                    <th class="px-3 py-3 text-left font-medium">Product Name</th>
                                    <th class="px-3 py-3 text-left font-medium">SKU</th>
                                    <th class="px-3 py-3 text-left font-medium">Category</th>
                                    <th class="px-3 py-3 text-left font-medium">Price</th>
                                    <th class="px-3 py-3 text-left font-medium">Pricing Type</th>
                                    <th class="px-3 py-3 text-left font-medium">Status</th>
                                    <th class="px-3 py-3 text-left font-medium">Linked Date</th>
                                    <th class="px-3 py-3 text-left font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($tabData['products']['items'] as $item)
                                    <tr class="text-gray-700 hover:bg-gray-50">
                                        <td class="px-3 py-3">
                                            <a href="{{ $item['show_url'] ?? route('products.index') }}" class="font-medium text-gray-900 hover:text-blue-700">
                                                {{ $item['name'] }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['sku'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['category'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['price'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['pricing_type'] }}</td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $item['status'] === 'Active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($item['status'] === 'Inactive' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-gray-200 bg-gray-100 text-gray-600') }}">
                                                {{ $item['status'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['linked_at'] }}</td>
                                        <td class="px-3 py-3">
                                            <a href="{{ $item['show_url'] ?? route('products.index') }}" class="inline-flex rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-12 text-center text-sm text-gray-500">No availed products found for this contact yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                        Total revenue: <span class="font-semibold text-gray-900">{{ $tabData['products']['grand_total'] }}</span>
                    </div>
                </div>
            @endif

            @if ($tab === 'services')
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900">Services Availed</h2>
                        <p class="text-sm text-gray-500">Services associated with this contact through related company work.</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $tabData['services']['total_services'] }} {{ \Illuminate\Support\Str::plural('service', $tabData['services']['total_services']) }}
                        <span class="mx-2 text-gray-300">|</span>
                        Total value {{ $tabData['services']['total_value'] }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-700">
                                <tr>
                                    <th class="px-3 py-3 text-left font-medium">Service Name</th>
                                    <th class="px-3 py-3 text-left font-medium">Category</th>
                                    <th class="px-3 py-3 text-left font-medium">Frequency</th>
                                    <th class="px-3 py-3 text-left font-medium">Engagement Type</th>
                                    <th class="px-3 py-3 text-left font-medium">Price / Rate</th>
                                    <th class="px-3 py-3 text-left font-medium">Assigned Unit</th>
                                    <th class="px-3 py-3 text-left font-medium">Status</th>
                                    <th class="px-3 py-3 text-left font-medium">Service Owner</th>
                                    <th class="px-3 py-3 text-left font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($tabData['services']['items'] as $item)
                                    <tr class="text-gray-700 hover:bg-gray-50">
                                        <td class="px-3 py-3">
                                            <a href="{{ $item['show_url'] ?? route('services.index') }}" class="font-medium text-gray-900 hover:text-blue-700">
                                                {{ $item['name'] }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['category'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['frequency'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['engagement_type'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['price_rate'] }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['assigned_unit'] }}</td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $item['status'] === 'Completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                                                {{ $item['status'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600">{{ $item['owner'] }}</td>
                                        <td class="px-3 py-3">
                                            <a href="{{ $item['show_url'] ?? route('services.index') }}" class="inline-flex rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-12 text-center text-sm text-gray-500">No availed services found for this contact yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                        Completed: <span class="font-semibold text-gray-900">{{ $tabData['services']['completed'] }}</span>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>

@include('contacts.partials.kyc-intake-modal', ['contact' => $contact])

<x-slide-over id="sendCifModal" width="sm:max-w-[560px]">
    <div class="border-b border-gray-100 px-4 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Send CIF</h2>
                <p class="mt-1 text-sm text-gray-500">Email a secure CIF link to the client so they can complete missing details and upload onboarding documents.</p>
            </div>
            <button type="button" data-close-send-cif-modal class="h-9 w-9 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
    </div>

    <form id="sendCifForm" method="POST" action="{{ route('contacts.cif.send', $contact->id) }}" class="flex min-h-0 flex-1 flex-col">
        @csrf
        <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-6">
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                The secure link opens a client CIF form. Submitted details update this contact's KYC profile and keep the Contact record as the main source of truth.
            </div>
            <div>
                <label for="cif_recipient_email" class="mb-1 block text-sm font-medium text-gray-700">Recipient Email</label>
                <input id="cif_recipient_email" name="recipient_email" type="email" value="{{ $contact->email }}" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                <p class="mt-1 text-xs text-gray-500">Defaults to the contact email. You can change it before sending.</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                <p class="font-semibold uppercase tracking-wide text-gray-500">What the client can do</p>
                <p class="mt-2">Complete personal details, address, citizenship, KYC details, and onboarding information from the secure CIF page.</p>
            </div>
        </div>
        <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-close-send-cif-modal class="h-9 min-w-[100px] rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="h-9 min-w-[140px] rounded-full bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Send CIF Link</button>
            </div>
        </div>
    </form>
</x-slide-over>

<x-slide-over id="sendSpecimenModal" width="sm:max-w-[560px]">
    <div class="border-b border-gray-100 px-4 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Send Specimen Form</h2>
                <p class="mt-1 text-sm text-gray-500">Email a secure specimen signature form link so the client can complete the signature card remotely.</p>
            </div>
            <button type="button" data-close-send-specimen-modal class="h-9 w-9 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
    </div>

    <form id="sendSpecimenForm" method="POST" action="{{ route('contacts.specimen.send', $contact->id) }}" class="flex min-h-0 flex-1 flex-col">
        @csrf
        <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-6">
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                The secure link opens a client specimen signature form. Submitted details populate the saved specimen signature record for this contact.
            </div>
            <div>
                <label for="specimen_recipient_email" class="mb-1 block text-sm font-medium text-gray-700">Recipient Email</label>
                <input id="specimen_recipient_email" name="recipient_email" type="email" value="{{ $contact->email }}" class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                <p class="mt-1 text-xs text-gray-500">Defaults to the contact email. You can change it before sending.</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                <p class="font-semibold uppercase tracking-wide text-gray-500">What the client can do</p>
                <p class="mt-2">Complete the specimen signature card details, client references, signatory names, and related authentication fields.</p>
            </div>
        </div>
        <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-close-send-specimen-modal class="h-9 min-w-[100px] rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="h-9 min-w-[160px] rounded-full bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Send Specimen Link</button>
            </div>
        </div>
    </form>
</x-slide-over>

<div id="contactSendSuccessOverlay" class="pointer-events-none fixed inset-0 z-[80] hidden items-start justify-center px-4 pt-8" aria-hidden="true">
    <div id="contactSendSuccessCard" class="w-full max-w-sm translate-y-2 opacity-0 rounded-xl border border-gray-200 bg-white p-4 shadow-lg shadow-slate-200/70 transition duration-300">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
                <i class="fas fa-paper-plane text-xs"></i>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">Client Outreach</p>
                <h3 class="mt-1 text-base font-semibold text-slate-900">Sent to client's email</h3>
                <p id="contactSendSuccessText" class="mt-1 text-sm leading-5 text-slate-600"></p>
            </div>
        </div>
    </div>
</div>

<span id="contactIntakeOldEditFlag" data-active="{{ old('_from_contact_intake_edit') ? '1' : '0' }}" hidden></span>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const contactIntakeModal = document.getElementById('contactIntakeModal');
        const contactIntakeForm = document.getElementById('contactIntakeForm');
        const contactIntakeEditBtn = document.getElementById('contactIntakeEditBtn');
        const contactIntakeSaveBtn = document.getElementById('contactIntakeSaveBtn');
        const contactIntakeCancelBtn = document.getElementById('contactIntakeCancelBtn');
        const contactIntakeCloseBtn = document.getElementById('cancelContactIntakeModal');
        const contactIntakeOpenBtn = document.getElementById('openContactIntakeModal');
        const contactIntakeCloseButtons = document.querySelectorAll('[data-close-contact-intake-modal]');
        const contactIntakeFields = contactIntakeForm
            ? Array.from(contactIntakeForm.querySelectorAll('input, select, textarea')).filter((field) => !['hidden', 'submit', 'button'].includes(field.type))
            : [];

        const syncContactIntakeConditionalFields = () => {
            document.getElementById('intakeServiceInquiryOtherWrap')?.classList.toggle('hidden', !contactIntakeForm?.querySelector('input[name="service_inquiry_types[]"][value="Other"]')?.checked);
            document.getElementById('intakeRecommendationOtherWrap')?.classList.toggle('hidden', !contactIntakeForm?.querySelector('input[name="recommendation_options[]"][value="Others"]')?.checked);
            document.getElementById('intakeLeadSourceOtherWrap')?.classList.toggle('hidden', !contactIntakeForm?.querySelector('input[name="lead_source_channels[]"][value="Other"]')?.checked);
            document.getElementById('intakeOrganizationTypeOtherWrap')?.classList.toggle('hidden', contactIntakeForm?.querySelector('input[name="organization_type"]:checked')?.value !== 'Others');
            document.getElementById('intakeForeignBusinessNatureWrap')?.classList.toggle('hidden', contactIntakeForm?.querySelector('input[name="ownership_flag"]:checked')?.value !== 'Foreign-Owned Business');
        };

        const setContactIntakeEditMode = (isEditMode) => {
            contactIntakeFields.forEach((field) => {
                if (!field.readOnly) {
                    field.disabled = !isEditMode;
                }
            });

            contactIntakeEditBtn?.classList.toggle('hidden', isEditMode);
            contactIntakeSaveBtn?.classList.toggle('hidden', !isEditMode);
            contactIntakeCancelBtn?.classList.toggle('hidden', !isEditMode);
            contactIntakeCloseBtn?.classList.toggle('hidden', isEditMode);
        };

        const resetContactIntakeForm = () => {
            if (!contactIntakeForm) {
                return;
            }

            contactIntakeForm.reset();
            syncContactIntakeConditionalFields();
            setContactIntakeEditMode(false);
        };

        if (contactIntakeForm) {
            setContactIntakeEditMode(false);
            syncContactIntakeConditionalFields();
            contactIntakeForm.querySelectorAll('input, select, textarea').forEach((field) => {
                field.addEventListener('change', syncContactIntakeConditionalFields);
            });
        }

        contactIntakeOpenBtn?.addEventListener('click', () => {
            resetContactIntakeForm();
            window.jkncSlideOver.open(contactIntakeModal);
        });

        contactIntakeCloseButtons.forEach((button) => {
            button.addEventListener('click', () => {
                resetContactIntakeForm();
                window.jkncSlideOver.close(contactIntakeModal);
            });
        });

        contactIntakeEditBtn?.addEventListener('click', () => setContactIntakeEditMode(true));
        contactIntakeCancelBtn?.addEventListener('click', () => resetContactIntakeForm());

        if (document.getElementById('contactIntakeOldEditFlag')?.dataset.active === '1') {
            setContactIntakeEditMode(true);
            syncContactIntakeConditionalFields();
            window.jkncSlideOver.open(contactIntakeModal);
        }

        const bindSlideOver = (modalId, openSelector, closeSelector) => {
            const modal = document.getElementById(modalId);
            const openButtons = document.querySelectorAll(openSelector);
            const closeButtons = document.querySelectorAll(closeSelector);
            const openModal = () => window.jkncSlideOver.open(modal);
            const closeModal = () => window.jkncSlideOver.close(modal);

            openButtons.forEach((button) => button.addEventListener('click', openModal));
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));

            modal?.addEventListener('click', function (event) {
                if (event.target === modal || event.target.hasAttribute('data-drawer-overlay')) {
                    closeModal();
                }
            });
        };

        bindSlideOver('sendCifModal', '[data-open-send-cif-modal]', '[data-close-send-cif-modal]');
        bindSlideOver('sendSpecimenModal', '[data-open-send-specimen-modal]', '[data-close-send-specimen-modal]');

        const cifPreviewButton = document.querySelector('[data-cif-pdf-preview]');
        const cifDownloadButton = document.querySelector('[data-cif-pdf-download]');

        const openCifPrintFrame = (url) => {
            if (!url) {
                return;
            }

            const frame = document.createElement('iframe');
            frame.style.position = 'fixed';
            frame.style.width = '0';
            frame.style.height = '0';
            frame.style.border = '0';
            frame.style.opacity = '0';
            frame.setAttribute('aria-hidden', 'true');
            frame.src = url;
            document.body.appendChild(frame);

            const cleanup = () => {
                window.setTimeout(() => frame.remove(), 1500);
            };

            frame.addEventListener('load', cleanup, { once: true });
        };

        cifPreviewButton?.addEventListener('click', () => {
            window.open(cifPreviewButton.dataset.previewUrl, '_blank');
        });

        cifDownloadButton?.addEventListener('click', () => {
            openCifPrintFrame(cifDownloadButton.dataset.downloadUrl);
        });
    });
</script>
@endsection
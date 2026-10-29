@extends('layouts.app')
@section('title', 'Transmittal')

@section('content')
@php
    $transmittalPrefill = $prefill ?? null;
    $transmittalContacts = $contactOptions ?? [];
    $transmittalEmployees = $employeeOptions ?? [];
    $transmittalCompanies = $companyOptions ?? [];
    $transmittalCorporateContext = $corporateContext ?? [];
    $tmCompanyName = $transmittalCorporateContext['companyName'] ?? 'John Kelly & Company';
    $tmSecRegNo = $transmittalCorporateContext['secRegNo'] ?? '';
    $tmPrincipalAddress = $transmittalCorporateContext['principalAddress'] ?? '';
    $tmLogoUrl = $transmittalCorporateContext['logoUrl'] ?? asset('images/FINAL_LOGO.jpg');
@endphp
<div
    id="transmittal-page"
    class="w-full h-full px-6 py-5"
    x-data="{
        showSlideOver: false,
        drawerTitle: 'Add Transmittal',

        previewRef: 'AUTO-INCREMENT',
        previewDate: '{{ now()->format('Y-m-d') }}',
        previewFormCode: '',

        transmittalMode: 'SEND',

        partyName: '',
        officeName: '',
        previewAddress: '',

        deliveryType: '',
        byPersonWho: '',
        registeredMailProvider: '',
        registeredMailProviderOther: '',
        electronicMethod: '',

        recipientEmail: '',

        onDeliveryTypeChange() {
            if (this.deliveryType === 'Electronic') {
                this.electronicMethod = 'Email';
            }
        },

        actionDelivery: false,
        actionPickUp: false,
        actionDropOff: false,
        actionEmail: false,

        previewPreparedBy: '',
        previewApprovedBy: '',
        previewPreparedAt: '',
        previewApprovedAt: '',
        previewApprovedPosition: '',
        previewCustodian: '',
        previewDeliveredBy: '',
        previewReceivedBy: '',
        previewReceiverAffiliation: '',
        previewReceivedAt: '',
        selectedReceiverKey: '',
        selectedCompanyId: '',
        receiverOptions: @js(array_merge($transmittalContacts, $transmittalEmployees)),
        companyOptions: @js($transmittalCompanies),
        employeeOptions: @js($transmittalEmployees),
        selectedApprovedByKey: '',
        selectedOperationsManagerKey: '',
        selectedDeliveredByKey: '',
        extraAttachments: [],

        previewItems: [
            { no: 1, particular: '', unique_id: '', qty: '', description: '', remarks: '', file: null },
        ],

        applyEmployeeSelection(target, selectedKey) {
            if (!selectedKey) return;
            const selected = this.employeeOptions.find(option => `${option.type}-${option.id}` === selectedKey || String(option.id) === String(selectedKey));
            if (!selected) return;

            if (target === 'approved_by') this.previewApprovedBy = selected.label || '';
            if (target === 'operations_manager') this.previewApprovedPosition = selected.label || '';
            if (target === 'delivered_by') this.previewDeliveredBy = selected.label || '';
        },

        applyReceiverSelection() {
            if (!this.selectedReceiverKey) return;
            const selected = this.receiverOptions.find(option => `${option.type}-${option.id}` === this.selectedReceiverKey);
            if (!selected) return;

            this.previewReceivedBy = selected.label || '';

            if (selected.affiliation && !this.previewReceiverAffiliation) {
                this.previewReceiverAffiliation = selected.affiliation;
            }

            if (selected.email && !this.recipientEmail) {
                this.recipientEmail = selected.email;
            }
        },

        applyCompanySelection() {
            if (!this.selectedCompanyId) return;
            const selected = this.companyOptions.find(option => String(option.id) === String(this.selectedCompanyId));
            if (!selected) return;

            this.previewReceiverAffiliation = selected.label || '';
        },

        closeAddSectionAlpine() {
            this.showSlideOver = false;
            closeAddSection();
        },

        modeDescription() {
            return this.transmittalMode === 'SEND'
                ? 'Outgoing transmittal'
                : 'Incoming transmittal';
        },

        computedFrom() {
            return this.transmittalMode === 'SEND'
                ? (this.officeName || '')
                : (this.partyName || '');
        },

        computedTo() {
            return this.transmittalMode === 'SEND'
                ? (this.partyName || '')
                : (this.officeName || '');
        },

        partyLabel() {
            return this.transmittalMode === 'SEND' ? 'To' : 'From';
        },

        partyPlaceholder() {
            return this.transmittalMode === 'SEND'
                ? 'Enter external receiver / destination'
                : 'Enter external sender / source';
        },

        officeLabel() {
            return this.transmittalMode === 'SEND' ? 'From (Office)' : 'To (Office)';
        },

        officePlaceholder() {
            return this.transmittalMode === 'SEND'
                ? 'Enter office sending the transmittal'
                : 'Enter office receiving the transmittal';
        },

        selectedActions() {
            const actions = [];
            if (this.actionDelivery) actions.push('Delivery');
            if (this.actionPickUp) actions.push('Pick Up');
            if (this.actionDropOff) actions.push('Drop Off');
            if (this.actionEmail) actions.push('Email');
            return actions.length ? actions.join(', ') : '—';
        },

        deliverySummary() {
            if (this.deliveryType === 'By Person') {
                return this.byPersonWho ? `By Person - ${this.byPersonWho}` : 'By Person';
            }

            if (this.deliveryType === 'Registered Mail') {
                const prov = this.registeredMailProvider === 'Others' 
                    ? (this.registeredMailProviderOther ? `Others (${this.registeredMailProviderOther})` : 'Others')
                    : this.registeredMailProvider;
                return prov ? `Registered Mail - ${prov}` : 'Registered Mail';
            }

            if (this.deliveryType === 'Electronic') {
                return this.electronicMethod ? `Electronic - ${this.electronicMethod}` : 'Electronic';
            }

            return '—';
        }
    }"
>
    <div class="bg-white border border-gray-200 rounded-xl min-h-[calc(100vh-7rem)] flex flex-col">

        <div class="px-5 py-4 flex items-center justify-between border-b border-gray-200">
            <h1 class="text-[30px] font-semibold text-gray-800 leading-none">Transmittal</h1>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('transmittals.smart-upload') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-full transition flex items-center gap-1.5"
                >
                    <i class="fas fa-file-import text-xs" style="color: #fff;"></i> Smart Upload
                </a>

                <button
                    type="button"
                    @click="showSlideOver = true"
                    onclick="openAddSection()"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-full transition"
                >
                    <i class="fas fa-plus mr-1" style="color: #000;"></i> Add
                </button>
            </div>
        </div>

        {{-- Metric Summary Cards --}}
        <div class="px-5 pt-4 pb-1 grid grid-cols-2 md:grid-cols-4 gap-4 bg-white border-b border-gray-100">
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total transmittals</span>
                <span class="text-2xl font-bold text-gray-900 mt-1">{{ $metrics['total'] ?? 0 }}</span>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Awaiting approval</span>
                <span class="text-2xl font-bold text-gray-900 mt-1">{{ $metrics['awaiting_approval'] ?? 0 }}</span>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Awaiting acknowledgment</span>
                <span class="text-2xl font-bold text-gray-900 mt-1">{{ $metrics['awaiting_acknowledgment'] ?? 0 }}</span>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Completed this month</span>
                <span class="text-2xl font-bold text-gray-900 mt-1">{{ $metrics['completed_this_month'] ?? 0 }}</span>
            </div>
        </div>

        <div class="px-5 pt-4 bg-white border-b border-gray-100">
            <div class="flex gap-8 text-[15px] text-gray-700 overflow-x-auto">
                <button onclick="applyWorkflowFilter('all')" id="tab-all" class="pb-3 whitespace-nowrap border-b-2 border-blue-600 font-medium text-gray-900">
                    All Status
                </button>
                <button onclick="applyWorkflowFilter('uploaded')" id="tab-uploaded" class="pb-3 whitespace-nowrap">
                    Uploaded
                </button>
                <button onclick="applyWorkflowFilter('submitted')" id="tab-submitted" class="pb-3 whitespace-nowrap">
                    Submitted
                </button>
                <button onclick="applyWorkflowFilter('accepted')" id="tab-accepted" class="pb-3 whitespace-nowrap">
                    Accepted
                </button>
                <button onclick="applyWorkflowFilter('reverted')" id="tab-reverted" class="pb-3 whitespace-nowrap">
                    Reverted
                </button>
                <button onclick="applyWorkflowFilter('archived')" id="tab-archived" class="pb-3 whitespace-nowrap">
                    Archived
                </button>
            </div>

            <div id="statusMessage" class="mt-3 mb-4 border border-blue-200 bg-blue-50 text-blue-700 text-[14px] px-4 py-3 rounded-md">
                Showing all transmittal records across all status.
            </div>

            {{-- SEARCH BAR (MATCHING SS2 PILL SHAPE) --}}
            <div class="mb-4">
                <div class="relative w-full">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input
                        id="transmittalTableSearch"
                        type="text"
                        placeholder="Search"
                        oninput="onSearchInput(this.value)"
                        class="w-full bg-gray-100 focus:bg-white border border-transparent focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none transition"
                    >
                </div>
            </div>
        </div>

        <div id="tableSection" class="px-5 pt-4 pb-5">
            <div class="border border-gray-200 rounded-md overflow-hidden overflow-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold">Ref#</th>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold">Date</th>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold text-center">Mode</th>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold">From</th>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold">To</th>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold">Delivery Type</th>
                            <th class="px-3 py-3 border-r border-gray-200 font-semibold text-center">Workflow Status</th>
                            <th class="px-3 py-3 font-semibold text-center">Approval Status</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="bg-white">
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">No transmittal records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="paginationSection" class="mt-4 flex items-center justify-between text-sm text-gray-600">
                <div id="paginationInfo">Showing 0 to 0 of 0 records</div>

                <div class="flex items-center gap-2">
                    <button
                        id="prevPageBtn"
                        type="button"
                        class="px-3 py-1.5 border border-gray-300 rounded-md bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="changePage(currentPage - 1)"
                        disabled
                    >
                        Previous
                    </button>

                    <span id="paginationLabel" class="min-w-[110px] text-center">Page 1 of 1</span>

                    <button
                        id="nextPageBtn"
                        type="button"
                        class="px-3 py-1.5 border border-gray-300 rounded-md bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="changePage(currentPage + 1)"
                        disabled
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div x-show="showSlideOver" x-cloak class="fixed inset-0 z-50 overflow-hidden bg-white">
            <div class="absolute inset-0 bg-white" @click="closeAddSectionAlpine()"></div>

            <div class="absolute inset-0 flex w-full h-full bg-white">
                <div
                    x-show="showSlideOver"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-300"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                    class="flex-1 h-full bg-[#ffffff] overflow-y-auto p-6 border-r border-gray-200"
                >
                    <div class="max-w-[1150px] mx-auto flex flex-col items-center gap-6">

                        {{-- PAGE 1: Confidential Notes & Classification Cover Page --}}
                        <div id="transmittal-cover-page" class="transmittal-doc-page bg-white border border-gray-300 shadow" style="width: 210mm; min-height: 297mm; padding: 22mm 22mm 20mm 22mm; box-sizing: border-box; background: #fff; color: #000; font-family: 'Times New Roman', Georgia, serif; display: flex; flex-direction: column; margin: 0 auto; page-break-after: always; line-height: 1.45;">
                            {{-- Center-aligned JK&C logo --}}
                            <div style="text-align: center; margin-bottom: 24px;">
                                <img src="{{ $tmLogoUrl }}" alt="JK&amp;C Logo" class="tm-corp-logo" style="width: 145px; max-width: 145px; max-height: 75px; object-fit: contain; margin: 0 auto; display: block;">
                            </div>

                            {{-- Bold, prominent centered header --}}
                            <div style="text-align: center; margin-bottom: 24px;">
                                <h1 style="font-family: 'Times New Roman', Georgia, serif; font-size: 28px; font-weight: 800; color: #cc0000; letter-spacing: 1.5px; margin: 0 0 8px 0; text-transform: uppercase;">
                                    STRICTLY CONFIDENTIAL
                                </h1>
                                <div style="display: inline-block; background: #cc0000; color: #ffffff; font-family: Arial, sans-serif; font-size: 13px; font-weight: 800; padding: 4px 16px; letter-spacing: 1px; text-transform: uppercase;">
                                    RESTRICTED ACCESS – AUTHORIZED RECIPIENT ONLY
                                </div>
                            </div>

                            {{-- Document title & Control Number --}}
                            <div style="text-align: center; margin-bottom: 28px;">
                                <div style="font-family: 'Times New Roman', Georgia, serif; font-size: 16px; font-weight: 700; color: #000; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.35;">
                                    OFFICIAL DOCUMENT TRANSMITTAL
                                </div>
                                <div style="font-family: 'Times New Roman', Georgia, serif; font-size: 15px; font-weight: 700; color: #000; text-transform: uppercase; margin-top: 4px;">
                                    CONTROL NO.: <span x-text="previewRef"></span>
                                </div>
                            </div>

                            {{-- Main Notice Content --}}
                            <div style="font-family: 'Times New Roman', Georgia, serif; font-size: 14.5px; color: #000; text-align: justify; line-height: 1.55;">
                                <div style="font-size: 15px; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">
                                    CONFIDENTIALITY AND LEGAL NOTICE
                                </div>

                                <p style="margin: 0 0 16px 0;">
                                    <span style="background: #cc0000; color: #ffffff; font-weight: 800; padding: 1px 5px;">WARNING: DO NOT OPEN IF YOU ARE NOT THE INTENDED RECIPIENT.</span> The enclosed documents contain confidential, proprietary, and potentially legally protected information. Unauthorized access, reproduction, disclosure, distribution, alteration, or use is strictly prohibited.
                                </p>

                                <p style="margin: 0 0 18px 0;">
                                    <span style="background: #cc0000; color: #ffffff; font-weight: 800; padding: 1px 5px;">IF YOU ARE NOT THE INTENDED RECIPIENT,</span> do not open or examine the enclosed documents. <strong>IMMEDIATELY CONTACT JOHN KELLY &amp; COMPANY</strong> through the official contact details below and arrange for the secure return of the documents to its designated address or such other address as may be advised by the Company.
                                </p>

                                {{-- Official contact details block --}}
                                <div style="margin: 16px 0 20px 0; font-size: 14.5px; line-height: 1.5;">
                                    <div><strong>EMAIL:</strong> <a href="mailto:start@jknc.io" style="color: #0044cc; text-decoration: underline;">start@jknc.io</a></div>
                                    <div><strong>PHONE:</strong> 0995 353 3789</div>
                                    <div><strong>WEBSITE:</strong> <a href="https://jknc.io/" target="_blank" rel="noopener noreferrer" style="color: #0044cc; text-decoration: underline;">https://jknc.io/</a></div>
                                </div>

                                {{-- Control numbers text --}}
                                <p style="margin: 0 0 16px 0;">
                                    This transmittal bears a unique Control Number corresponding to JK&amp;C's official, securely maintained records identifying the enclosed documents, their contents, and the relevant dispatch and receipt information.
                                </p>

                                {{-- Republic Act No. 10173 compliance --}}
                                <p style="margin: 0 0 18px 0;">
                                    Unlawful access, processing, disclosure, or misuse of protected information may give rise to civil, criminal, or administrative liability under Republic Act No. 10173 (Data Privacy Act of 2012), where applicable, and other relevant Philippine laws.
                                </p>

                                {{-- Legal disclaimer highlighting John Kelly & Company's rights and remedies --}}
                                <p style="margin: 0 0 22px 0; font-size: 14.5px; font-weight: 800; color: #0033cc; line-height: 1.45;">
                                    JOHN KELLY &amp; COMPANY EXPRESSLY RESERVES THE RIGHT TO PURSUE ALL APPROPRIATE LEGAL REMEDIES AGAINST PERSONS RESPONSIBLE FOR UNLAWFUL ACTS.
                                </p>

                                {{-- Footer closing statement --}}
                                <p style="margin: 18px 0 0 0; font-style: italic; color: #000;">
                                    All rights and remedies under applicable law expressly reserved.
                                </p>
                            </div>

                            <div class="tm-footer-code" style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; margin-top: auto; padding-top: 14px; border-top: 1px solid #d1d5db; color: #4b5563; font-family: Arial, sans-serif;">
                                <div>JKNC-TF-GS-V.1-2025</div>
                                <div>Page 1 of 2</div>
                            </div>
                        </div>

                        {{-- PAGE 2: Transmittal Form --}}
                        <div id="transmittal-preview-pdf" class="transmittal-doc-page bg-white border border-gray-300 shadow">
                            <div class="tm-corp-header" style="text-align: center; margin-bottom: 14mm; color: #000; width: 100%;">
                                <img src="{{ $tmLogoUrl }}" alt="Company Logo" class="tm-corp-logo" style="width: 155px; max-width: 155px; max-height: 100px; object-fit: contain; margin: 0 auto 6px auto; display: block;">
                                <div class="tm-corp-name" style="font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 700; text-transform: uppercase; line-height: 1.15; text-align: center;">JK&amp;C INC.</div>
                                <div class="tm-corp-line" style="font-family: Georgia, 'Times New Roman', serif; font-size: 12px; font-weight: 700; line-height: 1.25; text-transform: uppercase; text-align: center;">CONTROL NO.: <span x-text="previewRef"></span></div>
                                <div class="tm-corp-line" style="font-family: Georgia, 'Times New Roman', serif; font-size: 12px; font-weight: 700; line-height: 1.25; text-transform: uppercase; text-align: center;">3RD FLOOR UNIT 305 CEBU HOLDINGS CENTER, CEBU BUSINESS PARK, HIPPODROMO, CEBU CITY</div>
                            </div>

                            <div class="tm-title">
                                <div class="tm-title-main">Transmittal Form</div>
                                <div class="text-[11px] uppercase tracking-[0.12em] text-gray-500" x-text="previewFormCode"></div>
                            </div>

                            <div class="tm-top-block">
                                <div class="tm-top-row tm-top-row-first">
                                    <div class="tm-label">Ref No</div>
                                    <div class="tm-line" x-text="previewRef"></div>

                                    <div class="tm-label tm-date-label">Date</div>
                                    <div class="tm-line tm-date" x-text="previewDate"></div>
                                </div>

                                <div class="tm-top-row">
                                    <div class="tm-label">Mode</div>
                                    <div class="tm-line" x-text="transmittalMode"></div>
                                </div>

                                <div class="tm-top-row">
                                    <div class="tm-label">From</div>
                                    <div class="tm-line tm-address" x-text="computedFrom() || ' '"></div>
                                </div>

                                <div class="tm-top-row">
                                    <div class="tm-label">To</div>
                                    <div class="tm-line tm-address" x-text="computedTo() || ' '"></div>
                                </div>

                                <div class="tm-top-row">
                                    <div class="tm-label">Address</div>
                                    <div class="tm-line tm-address" x-text="previewAddress"></div>
                                </div>
                            </div>

                            <div class="tm-meta-grid">
                                <div>
                                    <span class="tm-meta-label">Delivery Type:</span>
                                    <span x-text="deliverySummary()"></span>
                                </div>
                                <div>
                                    <span class="tm-meta-label">Actions:</span>
                                    <span x-text="selectedActions()"></span>
                                </div>
                                <div>
                                    <span class="tm-meta-label">Recipient Email:</span>
                                    <span x-text="recipientEmail || '—'"></span>
                                </div>
                                <div>
                                    <span class="tm-meta-label">Electronic Method:</span>
                                    <span x-text="electronicMethod || '—'"></span>
                                </div>
                            </div>

                            <div class="tm-section-title">List of Items</div>

                            <table class="tm-table">
                                <thead>
                                    <tr>
                                        <th class="tm-col-no">No</th>
                                        <th class="tm-col-particular">Particular</th>
                                        <th class="tm-col-uid">Unique ID</th>
                                        <th class="tm-col-qty">Qty.</th>
                                        <th class="tm-col-description">Description</th>
                                        <th class="tm-col-remarks">Remarks</th>
                                        <th class="tm-col-file">Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in previewItems" :key="index">
                                        <tr>
                                            <td x-text="item.no"></td>
                                            <td x-text="item.particular || ''"></td>
                                            <td x-text="item.unique_id || ''"></td>
                                            <td x-text="item.qty || ''"></td>
                                            <td x-text="item.description || ''"></td>
                                            <td x-text="item.remarks || ''"></td>
                                            <td x-text="item.file ? item.file.name : '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>

                            <div class="tm-signatures">
                                <div class="tm-sign-col">
                                    <div class="tm-sign-label">Prepared by:</div>
                                    <div class="tm-sign-line" x-text="previewPreparedBy || ' '"></div>
                                    <div class="tm-sign-sub" x-text="previewPreparedAt || ''"></div>

                                    <div class="tm-sign-label tm-sign-gap">Approved by:</div>
                                    <div class="tm-sign-line" x-text="previewApprovedBy || ' '"></div>

                                    <div class="tm-sign-label tm-sign-gap-sm">Operations Manager:</div>
                                    <div class="tm-sign-line" x-text="previewApprovedPosition || ' '"></div>
                                </div>

                                <div class="tm-sign-col">
                                    <div class="tm-sign-label">Delivered by:</div>
                                    <div class="tm-sign-line" x-text="previewDeliveredBy || ' '"></div>

                                    <div class="tm-sign-label tm-sign-gap">Received by:</div>
                                    <div class="tm-sign-line" x-text="previewReceivedBy || ' '"></div>

                                    <div class="tm-sign-label tm-sign-gap-sm">Processing for Company / Organization:</div>
                                    <div class="tm-sign-line" x-text="previewReceiverAffiliation || ' '"></div>
                                </div>
                            </div>

                            <div class="tm-footer-code">
                                <div>JKNC-TF-GS-V.1-2025</div>
                                <div>Page 2 of 2</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    x-show="showSlideOver"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-300"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="w-[520px] 2xl:w-[560px] max-w-[95vw] shrink-0 h-full bg-white shadow-2xl flex flex-col border-l border-gray-200"
                >
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800" x-text="drawerTitle">Add Transmittal</h2>
                        <button type="button" @click="closeAddSectionAlpine()" class="text-black hover:text-gray-800 text-lg" style="color: #000;">
                            <i class="fas fa-times" style="color: #000;"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Ref #</label>
                            <input type="text" value="AUTO-INCREMENT" readonly class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-600">
                            <p class="mt-1 text-xs text-gray-400">Date is automatically set to today when saved.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Date</label>
                            <input type="date" x-model="previewDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Mode</label>
                            <select x-model="transmittalMode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                <option value="SEND">SEND</option>
                                <option value="RECEIVE">RECEIVE</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400" x-text="modeDescription()"></p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1" x-text="partyLabel()"></label>
                            <input type="text" x-model="partyName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500" :placeholder="partyPlaceholder()">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1" x-text="officeLabel()"></label>
                            <input type="text" x-model="officeName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500" :placeholder="officePlaceholder()">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Address</label>
                            <textarea x-model="previewAddress" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500" placeholder="Enter address"></textarea>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3">Delivery Type</h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Type</label>
                                    <select x-model="deliveryType" @change="onDeliveryTypeChange()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                        <option value="">Select delivery type</option>
                                        <option value="By Person">By Person</option>
                                        <option value="Registered Mail">Registered Mail</option>
                                        <option value="Electronic">Electronic</option>
                                    </select>
                                </div>

                                <div x-show="deliveryType === 'By Person'">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Who?</label>
                                    <input type="text" x-model="byPersonWho" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Enter courier / person name">
                                </div>

                                <div x-show="deliveryType === 'Registered Mail'">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Courier / Provider</label>
                                    <select x-model="registeredMailProvider" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                        <option value="">Select provider</option>
                                        <option value="LBC">LBC</option>
                                        <option value="J&T">J&T</option>
                                        <option value="Postal">Postal</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>

                                <div x-show="deliveryType === 'Registered Mail' && registeredMailProvider === 'Others'">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Specify Provider / Details</label>
                                    <input type="text" x-model="registeredMailProviderOther" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500" placeholder="Type custom provider name">
                                </div>

                                <div x-show="deliveryType === 'Electronic'">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Electronic Method</label>
                                    <select x-model="electronicMethod" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                        <option value="Email">Email</option>
                                    </select>
                                </div>

                                <div x-show="deliveryType === 'Electronic'">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Recipient Email</label>
                                    <input type="email" x-model="recipientEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500" placeholder="Enter email address">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3">Actions</h3>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" x-model="actionDelivery" class="rounded border-gray-300">
                                    <span>Delivery</span>
                                </label>

                                <label class="flex items-center gap-2">
                                    <input type="checkbox" x-model="actionPickUp" class="rounded border-gray-300">
                                    <span>Pick Up</span>
                                </label>

                                <label class="flex items-center gap-2">
                                    <input type="checkbox" x-model="actionDropOff" class="rounded border-gray-300">
                                    <span>Drop Off</span>
                                </label>

                                <label class="flex items-center gap-2">
                                    <input type="checkbox" x-model="actionEmail" class="rounded border-gray-300">
                                    <span>Email</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-2">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold text-gray-700">List of Items</label>
                                <button type="button" onclick="addTransmittalRow()" class="text-xs bg-blue-600 text-white px-2.5 py-1.5 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-plus mr-1" style="color: #000;"></i> Add Row
                                </button>
                            </div>

                            <div class="space-y-4">
                                <template x-for="(item, index) in previewItems" :key="index">
                                    <div class="rounded-lg border border-gray-200 p-3 bg-gray-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-xs font-semibold text-gray-700">Item <span x-text="index + 1"></span></p>
                                            <button type="button" @click="removeTransmittalRow(index)" class="text-black hover:text-gray-700 font-medium text-xs underline" style="color: #000;">
                                                Remove
                                            </button>
                                        </div>

                                        <div class="space-y-2.5">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Particular</label>
                                                <input type="text" x-model="item.particular" placeholder="Item name" class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            </div>

                                            <div class="flex gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Unique ID</label>
                                                    <input type="text" x-model="item.unique_id" placeholder="ID / serial" class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                                </div>

                                                <div class="w-[110px] flex-shrink-0">
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Qty</label>
                                                    <input type="number" min="0" step="any" x-model="item.qty" placeholder="Quantity" class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                                <input type="text" x-model="item.description" placeholder="Description" class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Remarks</label>
                                                <input type="text" x-model="item.remarks" placeholder="Remarks" class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Upload File</label>
                                                <div class="w-full box-border border border-slate-300 rounded-lg p-2 bg-white">
                                                    <template x-if="item.file">
                                                        <div class="text-xs text-slate-800 font-semibold mb-1.5 flex items-center gap-1.5">
                                                            <span>📎</span>
                                                            <strong class="truncate" x-text="item.file.name"></strong>
                                                        </div>
                                                    </template>
                                                    <div class="flex items-center gap-2.5 flex-wrap">
                                                        <label class="inline-flex items-center justify-center px-3 py-1.5 bg-[#e5e7eb] hover:bg-[#d1d5db] border border-[#dfe3e8] rounded text-xs font-medium text-[#4b5563] cursor-pointer transition select-none">
                                                            Choose Files
                                                            <input
                                                                type="file"
                                                                accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
                                                                @change="item.file = $event.target.files[0]"
                                                                class="sr-only"
                                                            >
                                                        </label>
                                                        <span class="text-xs text-[#475569] truncate max-w-[260px]" x-text="item.file ? item.file.name : 'No file chosen'"></span>
                                                    </div>
                                                </div>
                                                <small class="text-[#6b7280] text-[11px] block mt-1" x-text="item.file ? 'Choose a new file to replace the current attachment.' : 'Select a file for this item.'"></small>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Supporting Attachments --}}
                        <div class="border-t border-gray-200 pt-3 mt-3">
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Supporting Attachments</label>
                            <div class="w-full box-border border border-slate-300 rounded-lg p-2 bg-white mb-1">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <label class="inline-flex items-center justify-center px-3 py-1.5 bg-[#e5e7eb] hover:bg-[#d1d5db] border border-[#dfe3e8] rounded text-xs font-medium text-[#4b5563] cursor-pointer transition select-none">
                                        Choose Files
                                        <input
                                            type="file"
                                            multiple
                                            accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
                                            @change="extraAttachments = Array.from($event.target.files)"
                                            class="sr-only"
                                        >
                                    </label>
                                    <span class="text-xs text-[#475569] truncate max-w-[260px]" x-text="extraAttachments.length ? `${extraAttachments.length} file(s) chosen` : 'No file chosen'"></span>
                                </div>
                            </div>
                            <template x-if="extraAttachments.length">
                                <ul class="mt-1 text-xs text-gray-600 list-disc pl-5">
                                    <template x-for="file in extraAttachments" :key="file.name">
                                        <li x-text="file.name"></li>
                                    </template>
                                </ul>
                            </template>
                            <small class="text-[#6b7280] text-[11px] block mt-1">You may upload multiple supporting files. These will be attached when using Send Email.</small>
                        </div>

                        {{-- Signatories --}}
                        <div class="border-t border-gray-200 pt-3 mt-3">
                            <label class="block text-xs font-semibold text-gray-700 mb-3">Signatories</label>

                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Prepared by</label>
                                    <input type="text" x-model="previewPreparedBy" readonly class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-gray-100 text-gray-600">
                                </div>

                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Prepared at</label>
                                    <input type="text" x-model="previewPreparedAt" readonly class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-gray-100 text-gray-600">
                                </div>

                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Approved by</label>
                                    <select x-model="selectedApprovedByKey" @change="applyEmployeeSelection('approved_by', selectedApprovedByKey)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white mb-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">Select employee approver</option>
                                        <template x-for="employee in employeeOptions" :key="`approved-${employee.id}`">
                                            <option :value="`${employee.type}-${employee.id}`" x-text="`${employee.label}${employee.position ? ' - ' + employee.position : ''}`"></option>
                                        </template>
                                    </select>
                                    <input type="text" x-model="previewApprovedBy" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Or type approver manually">
                                </div>

                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Operations Manager</label>
                                    <select x-model="selectedOperationsManagerKey" @change="applyEmployeeSelection('operations_manager', selectedOperationsManagerKey)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white mb-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">Select Operations Manager</option>
                                        <template x-for="employee in employeeOptions" :key="`ops-${employee.id}`">
                                            <option :value="`${employee.type}-${employee.id}`" x-text="`${employee.label}${employee.position ? ' - ' + employee.position : ''}`"></option>
                                        </template>
                                    </select>
                                    <input type="text" x-model="previewApprovedPosition" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Or type Operations Manager manually">
                                </div>

                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Delivered by</label>
                                    <select x-model="selectedDeliveredByKey" @change="applyEmployeeSelection('delivered_by', selectedDeliveredByKey)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white mb-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">Select employee</option>
                                        <template x-for="employee in employeeOptions" :key="`delivered-${employee.id}`">
                                            <option :value="`${employee.type}-${employee.id}`" x-text="`${employee.label}${employee.position ? ' - ' + employee.position : ''}`"></option>
                                        </template>
                                    </select>
                                    <input type="text" x-model="previewDeliveredBy" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Or type delivered by manually">
                                </div>

                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Received by (Contact / Employee)</label>
                                    <select x-model="selectedReceiverKey" @change="applyReceiverSelection()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white mb-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">Select contact or employee</option>
                                        <template x-for="option in receiverOptions" :key="`${option.type}-${option.id}`">
                                            <option :value="`${option.type}-${option.id}`" x-text="`${option.label} (${option.type})`"></option>
                                        </template>
                                    </select>
                                    <input type="text" x-model="previewReceivedBy" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Or type receiver manually">
                                </div>

                                <div>
                                    <label class="block text-[11px] text-gray-700 mb-1">Processing for Company / Organization</label>
                                    <select x-model="selectedCompanyId" @change="applyCompanySelection()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white mb-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">Select company</option>
                                        <template x-for="company in companyOptions" :key="company.id">
                                            <option :value="company.id" x-text="company.label"></option>
                                        </template>
                                    </select>
                                    <input type="text" x-model="previewReceiverAffiliation" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Or type company manually">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex items-center gap-3">
                        <button type="button" onclick="saveTransmittal('draft')" class="flex-1 border border-gray-300 text-gray-700 rounded-lg py-2.5 text-sm font-semibold hover:bg-gray-50 transition text-center">
                            Save Draft
                        </button>

                        <button type="button" onclick="saveTransmittal('submit')" class="flex-1 bg-blue-600 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-blue-700 transition text-center shadow-sm">
                            Submit for review
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .transmittal-doc-page {
        width: 210mm;
        min-height: 297mm;
        padding: 25.4mm;
        box-sizing: border-box;
        background: #fff;
        color: #111827;
        font-family: Arial, sans-serif;
        display: flex;
        flex-direction: column;
    }


    .tm-corp-header { text-align:center; margin-bottom:18px; color:#000; }
    .tm-corp-logo { width:155px; max-width:155px; max-height:100px; object-fit:contain; margin:0 auto 6px auto; display:block; }
    .tm-corp-name { font-family: Georgia, 'Times New Roman', serif; font-size:22px; font-weight:700; text-transform:uppercase; line-height:1.15; }
    .tm-corp-line { font-family: Georgia, 'Times New Roman', serif; font-size:12px; font-weight:700; line-height:1.25; text-transform:uppercase; }

    .tm-letterhead {
        display: grid;
        grid-template-columns: 118px 1fr;
        align-items: stretch;
        margin: -3mm -3mm 18mm -3mm;
        border-bottom: 3px solid #1d4ed8;
    }

    .tm-letterhead-logo-box {
        min-height: 68px;
        border: 3px solid #8aa3df;
        border-left: 0;
        border-radius: 0 18px 18px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        padding: 8px 14px;
    }

    .tm-letterhead-logo {
        width: 78px;
        height: auto;
        object-fit: contain;
    }

    .tm-letterhead-info {
        min-height: 68px;
        margin-left: 16px;
        background: #7f9bdb;
        color: #fff;
        border-radius: 18px 0 0 18px;
        padding: 12px 18px 10px 22px;
        font-size: 14px;
        line-height: 1.22;
    }

    .tm-company-name {
        font-size: 18px;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 2px;
    }

    .tm-title {
        text-align: center;
        margin-bottom: 18px;
    }

    .tm-title-main {
        font-size: 26px;
        font-weight: 800;
        line-height: 1.2;
        color: #0037a6;
        font-family: Georgia, 'Times New Roman', serif;
    }

    .tm-top-block {
        font-size: 12px;
        margin-bottom: 16px;
    }

    .tm-top-row {
        display: grid;
        grid-template-columns: 70px 1fr;
        gap: 10px;
        align-items: end;
        margin-bottom: 6px;
    }

    .tm-top-row-first {
        grid-template-columns: 70px 1fr 42px 130px;
    }

    .tm-label {
        font-weight: 700;
        line-height: 1.2;
        color: #0037a6;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 14px;
    }

    .tm-date-label {
        text-align: right;
    }

    .tm-line {
        border-bottom: 1px solid #9ca3af;
        min-height: 18px;
        line-height: 1.2;
        padding-bottom: 2px;
        word-break: break-word;
    }

    .tm-address {
        white-space: normal;
    }

    .tm-meta-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 20px;
        font-size: 11px;
        margin-bottom: 16px;
    }

    .tm-meta-label {
        font-weight: 700;
    }

    .tm-section-title {
        font-size: 18px;
        font-weight: 800;
        margin: 18px 0 10px;
        text-align: center;
        color: #0037a6;
        font-family: Georgia, 'Times New Roman', serif;
    }

    .tm-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 10.5px;
        margin-bottom: 20px;
    }

    .tm-table th,
    .tm-table td {
        border: 1px solid #9ca3af;
        padding: 5px 6px;
        vertical-align: top;
        text-align: left;
        min-height: 26px;
        word-break: break-word;
    }

    .tm-table th {
        font-weight: 700;
        background: #0b3db8;
        color: #fff;
        text-align: center;
    }

    .tm-col-no { width: 36px; }
    .tm-col-particular { width: 100px; }
    .tm-col-uid { width: 90px; }
    .tm-col-qty { width: 40px; }
    .tm-col-description { width: auto; }
    .tm-col-remarks { width: 85px; }
    .tm-col-file { width: 100px; }

    .tm-footer-code {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        margin-top: auto;
        padding-top: 18px;
    }

    .tm-signatures {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 36px;
        font-size: 12px;
    }

    .tm-sign-col {
        display: flex;
        flex-direction: column;
    }

    .tm-sign-label {
        margin-bottom: 24px;
    }

    .tm-sign-gap {
        margin-top: 28px;
    }

    .tm-sign-gap-sm {
        margin-top: 22px;
    }

    .tm-sign-line {
        border-bottom: 1px solid #9ca3af;
        min-height: 18px;
        padding-bottom: 2px;
        line-height: 1.2;
        font-weight: 600;
        word-break: break-word;
    }

    .tm-sign-sub {
        margin-top: 4px;
        line-height: 1.2;
    }

    .record-row-clickable {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .record-row-clickable:hover {
        background: #f9fafb;
    }

    .transmittal-doc-page {
        transform: scale(1.08);
        transform-origin: top center;
        margin-bottom: 32px !important;
    }

    @media (max-width: 1400px) {
        .transmittal-doc-page {
            transform: scale(1.02);
            transform-origin: top center;
        }
    }

    @media (max-width: 1200px) {
        .transmittal-doc-page {
            transform: scale(0.96);
            transform-origin: top center;
        }
    }
</style>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
const urlParams = new URLSearchParams(window.location.search);
let currentWorkflowFilter = urlParams.get('filter') || urlParams.get('workflow_status') || 'all';
let currentSearchQuery = urlParams.get('q') || '';
let searchDebounceTimeout = null;
let currentPage = 1;
let paginationMeta = { current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 };
let transmittalRows = [];
const transmittalPrefill = @json($transmittalPrefill);

function defaultTransmittalItem(index = 0) {
    return { no: index + 1, particular: '', unique_id: '', qty: '', description: '', remarks: '', file: null };
}

function resetTransmittalForm() {
    const alpineData = getAlpineData();
    if (!alpineData) return;

    alpineData.previewRef = 'AUTO-INCREMENT';
    alpineData.previewDate = '{{ now()->format('Y-m-d') }}';
    alpineData.previewFormCode = '';
    alpineData.transmittalMode = 'SEND';
    alpineData.partyName = '';
    alpineData.officeName = '';
    alpineData.previewAddress = '';
    alpineData.deliveryType = '';
    alpineData.byPersonWho = '';
    alpineData.registeredMailProvider = '';
    alpineData.electronicMethod = '';
    alpineData.recipientEmail = '';
    alpineData.actionDelivery = false;
    alpineData.actionPickUp = false;
    alpineData.actionDropOff = false;
    alpineData.actionEmail = false;
    alpineData.previewPreparedBy = '{{ auth()->user()->name ?? "System User" }}';
    alpineData.previewApprovedBy = '';
    alpineData.previewApprovedPosition = '';
    alpineData.previewCustodian = '';
    alpineData.previewDeliveredBy = '';
    alpineData.previewReceivedBy = '';
    alpineData.previewReceiverAffiliation = '';
    alpineData.previewReceivedAt = '';
    alpineData.selectedReceiverKey = '';
    alpineData.selectedCompanyId = '';
    alpineData.previewItems = [defaultTransmittalItem(0)];
}

function applyTransmittalPrefill(prefill) {
    const alpineData = getAlpineData();
    if (!alpineData || !prefill) return;

    alpineData.previewDate = prefill.transmittal_date || alpineData.previewDate;
    alpineData.previewFormCode = prefill.form_code || '';
    alpineData.transmittalMode = prefill.mode || 'SEND';
    alpineData.partyName = prefill.party_name || '';
    alpineData.officeName = prefill.office_name || '';
    alpineData.previewAddress = prefill.address || '';
    alpineData.deliveryType = prefill.delivery_type || '';
    alpineData.byPersonWho = prefill.by_person_who || '';
    alpineData.registeredMailProvider = prefill.registered_mail_provider || '';
    alpineData.electronicMethod = prefill.electronic_method || '';
    alpineData.recipientEmail = prefill.recipient_email || '';
    alpineData.actionDelivery = !!prefill.action_delivery;
    alpineData.actionPickUp = !!prefill.action_pick_up;
    alpineData.actionDropOff = !!prefill.action_drop_off;
    alpineData.actionEmail = !!prefill.action_email;
    alpineData.previewPreparedBy = prefill.prepared_by_name || '';
    alpineData.previewApprovedBy = prefill.approved_by_name || '';
    alpineData.previewApprovedPosition = prefill.approved_position || '';
    alpineData.previewCustodian = prefill.document_custodian || '';
    alpineData.previewDeliveredBy = prefill.delivered_by || '';
    alpineData.previewReceivedBy = prefill.received_by || '';
    alpineData.previewReceiverAffiliation = prefill.receiver_affiliation || '';
    alpineData.previewReceivedAt = '';
    alpineData.previewItems = Array.isArray(prefill.items) && prefill.items.length
        ? prefill.items.map((item, index) => ({
            no: index + 1,
            particular: item.particular || '',
            unique_id: item.unique_id || '',
            qty: item.qty || '',
            description: item.description || '',
            remarks: item.remarks || '',
            file: null
        }))
        : [defaultTransmittalItem(0)];
}

function getAlpineData() {
    const root = document.getElementById('transmittal-page');
    return root ? Alpine.$data(root) : null;
}

function updateStatusMessage() {
    const messageBox = document.getElementById('statusMessage');

    if (currentWorkflowFilter === 'all') {
        messageBox.className = 'mt-3 mb-4 border border-blue-200 bg-blue-50 text-blue-700 text-[14px] px-4 py-3 rounded-md';
        messageBox.textContent = 'Showing all transmittal records across all status.';
    } else if (currentWorkflowFilter === 'uploaded') {
        messageBox.className = 'mt-3 mb-4 border border-blue-200 bg-blue-50 text-blue-700 text-[14px] px-4 py-3 rounded-md';
        messageBox.textContent = 'These records are uploaded and ready for submission.';
    } else if (currentWorkflowFilter === 'submitted') {
        messageBox.className = 'mt-3 mb-4 border border-yellow-200 bg-yellow-50 text-yellow-700 text-[14px] px-4 py-3 rounded-md';
        messageBox.textContent = 'These records are already submitted and waiting for admin approval.';
    } else if (currentWorkflowFilter === 'accepted') {
        messageBox.className = 'mt-3 mb-4 border border-green-200 bg-green-50 text-green-700 text-[14px] px-4 py-3 rounded-md';
        messageBox.textContent = 'Click the accepted record row to open full preview page.';
    } else if (currentWorkflowFilter === 'reverted') {
        messageBox.className = 'mt-3 mb-4 border border-red-200 bg-red-50 text-red-700 text-[14px] px-4 py-3 rounded-md';
        messageBox.textContent = 'These records were reverted and can be corrected then resubmitted.';
    } else {
        messageBox.className = 'mt-3 mb-4 border border-gray-200 bg-gray-50 text-gray-700 text-[14px] px-4 py-3 rounded-md';
        messageBox.textContent = 'These records are archived.';
    }
}

function setActiveTab() {
    ['all', 'uploaded', 'submitted', 'accepted', 'reverted', 'archived'].forEach(tab => {
        const el = document.getElementById(`tab-${tab}`);
        if (!el) return;

        if (tab === currentWorkflowFilter) {
            el.className = 'pb-3 whitespace-nowrap border-b-2 border-blue-600 font-medium text-gray-900';
        } else {
            el.className = 'pb-3 whitespace-nowrap text-gray-700';
        }
    });
}

function applyWorkflowFilter(filterValue) {
    currentWorkflowFilter = filterValue;
    currentPage = 1;
    renderTable();
}

function openAddSection(shouldReset = true) {
    if (shouldReset) {
        resetTransmittalForm();
    }
    const alpineData = getAlpineData();
    if (alpineData) {
        alpineData.showSlideOver = true;
        alpineData.previewPreparedBy = '{{ auth()->user()->name ?? "System User" }}';
        alpineData.previewPreparedAt = new Date().toLocaleString();
        alpineData.previewApprovedBy = '';
        alpineData.previewApprovedAt = '';
    }
}

function closeAddSection() {
    const alpineData = getAlpineData();
    if (alpineData) {
        alpineData.showSlideOver = false;
    }
}

function addTransmittalRow() {
    const alpineData = getAlpineData();
    if (!alpineData) return;

    alpineData.previewItems.push({
        no: alpineData.previewItems.length + 1,
        particular: '',
        unique_id: '',
        qty: '',
        description: '',
        remarks: '',
        file: null
    });
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function fetchTransmittals() {
    const params = new URLSearchParams();
    params.append('workflow_status', currentWorkflowFilter);
    params.append('page', currentPage);
    params.append('per_page', 10);
    if (currentSearchQuery && currentSearchQuery.trim()) {
        params.append('q', currentSearchQuery.trim());
    }

    const res = await fetch(`/transmittal/data?${params.toString()}`, {
        headers: {
            'Accept': 'application/json'
        }
    });

    return await res.json();
}

function onSearchInput(val) {
    currentSearchQuery = val;
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        currentPage = 1;
        renderTable();
    }, 300);
}

function renderPagination(meta) {
    const info = document.getElementById('paginationInfo');
    const label = document.getElementById('paginationLabel');
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');

    if (!info || !label || !prevBtn || !nextBtn) return;

    info.textContent = `Showing ${meta.from ?? 0} to ${meta.to ?? 0} of ${meta.total ?? 0} records`;
    label.textContent = `Page ${meta.current_page ?? 1} of ${meta.last_page ?? 1}`;

    prevBtn.disabled = (meta.current_page ?? 1) <= 1;
    nextBtn.disabled = (meta.current_page ?? 1) >= (meta.last_page ?? 1);
}

function changePage(page) {
    if (page < 1) return;
    if (page > (paginationMeta.last_page ?? 1)) return;

    currentPage = page;
    renderTable();
}

async function renderTable() {
    const tableBody = document.getElementById('tableBody');
    tableBody.innerHTML = '';

    const response = await fetchTransmittals();
    transmittalRows = response.data || [];
    paginationMeta = response.pagination || {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
        has_more_pages: false
    };

    updateStatusMessage();
    setActiveTab();
    renderPagination(paginationMeta);

    if (!transmittalRows.length) {
        tableBody.innerHTML = `<tr><td colspan="10" class="px-3 py-8 text-center text-gray-500">No transmittal records found.</td></tr>`;
        return;
    }

    transmittalRows.forEach((item) => {
        const previewUrl = item.preview_url ?? '';
        const workspaceUrl = item.workspace_url ?? `/transmittals/${item.id}/workspace`;
        const primaryUrl = workspaceUrl || previewUrl;
        const isClickable = !item.can_submit && primaryUrl ? ' record-row-clickable' : '';
        const rowClick = !item.can_submit && primaryUrl ? `onclick="window.location.href='${primaryUrl}'"` : '';

        tableBody.innerHTML += `
            <tr class="border-t border-gray-200${isClickable}" ${rowClick}>
                <td class="px-3 py-3 border-r border-gray-200 font-medium">
                    <a href="${workspaceUrl}" class="text-blue-600 hover:underline" onclick="event.stopPropagation()">${escapeHtml(item.transmittal_no ?? '')}</a>
                </td>
                <td class="px-3 py-3 border-r border-gray-200">${escapeHtml(item.date ?? '')}</td>
                <td class="px-3 py-3 border-r border-gray-200 text-center font-bold text-gray-900" style="color: #111827 !important;">${escapeHtml(item.mode ?? '')}</td>
                <td class="px-3 py-3 border-r border-gray-200">${escapeHtml(item.from_value ?? '')}</td>
                <td class="px-3 py-3 border-r border-gray-200">${escapeHtml(item.to_value ?? '')}</td>
                <td class="px-3 py-3 border-r border-gray-200">${escapeHtml(item.delivery_type ?? '')}</td>
                <td class="px-3 py-3 border-r border-gray-200 text-center">${escapeHtml(item.workflow_status ?? '')}</td>
                <td class="px-3 py-3 text-center">${escapeHtml(item.approval_status ?? '')}</td>
            </tr>
        `;
    });
}

async function saveTransmittal(intent = 'draft') {
    const alpineData = getAlpineData();
    if (!alpineData) return;

    const hasParty = !!(alpineData.partyName && alpineData.partyName.trim());
    const hasItems = Array.isArray(alpineData.previewItems) && alpineData.previewItems.some(i => (i.particular && i.particular.trim()) || i.file);

    if (intent === 'submit') {
        const emailRequired = (alpineData.deliveryType === 'Electronic') || !!alpineData.actionEmail;
        const hasEmail = !!(alpineData.recipientEmail && alpineData.recipientEmail.trim());

        if (!hasParty || (emailRequired && !hasEmail) || !hasItems) {
            let missing = [];
            if (!hasParty) missing.push(alpineData.transmittalMode === 'SEND' ? 'Recipient / To' : 'Sender / From');
            if (emailRequired && !hasEmail) missing.push('Recipient Email (under Delivery Type)');
            if (!hasItems) missing.push('at least one item in List of Items (Particular or File)');
            alert('Please fill up the form before submitting:\n• ' + missing.join('\n• '));
            return;
        }
    } else {
        if (!hasParty && !hasItems) {
            alert('Please fill up the form (provide ' + (alpineData.transmittalMode === 'SEND' ? 'Recipient / To' : 'Sender / From') + ' or at least one item).');
            return;
        }
    }

    const formData = new FormData();

    // Intent and required core fields
    formData.append('intent', intent);
    formData.append('subject', (alpineData.partyName ? ('Transmittal - ' + alpineData.partyName) : 'Official Document Transmittal'));
    formData.append('purpose', 'Official Transmittal and Document Turnover');
    formData.append('confidentiality', 'standard');
    formData.append('direction', (alpineData.transmittalMode || 'SEND').toLowerCase());
    formData.append('transmittal_date', alpineData.previewDate || '{{ now()->format('Y-m-d') }}');
    formData.append('origin_office', alpineData.officeName || 'John Kelly & Company');
    formData.append('recipient_address', alpineData.previewAddress ?? '');
    formData.append('related_company', alpineData.previewReceiverAffiliation || (alpineData.transmittalMode === 'SEND' ? (alpineData.partyName ?? '') : ''));

    // Recipients array (required on submit)
    const partyName = (alpineData.partyName || '').trim() || 'Intended Recipient';
    const recipientEmail = (alpineData.recipientEmail || '').trim();
    formData.append('recipients[0][name]', partyName);
    if (recipientEmail) {
        formData.append('recipients[0][email]', recipientEmail);
    } else if (intent === 'draft') {
        formData.append('recipients[0][email]', '');
    }
    formData.append('recipients[0][organization]', alpineData.previewReceiverAffiliation || '');

    // Form fields
    formData.append('mode', alpineData.transmittalMode);
    formData.append('party_name', alpineData.partyName ?? '');
    formData.append('office_name', alpineData.officeName ?? '');
    formData.append('address', alpineData.previewAddress ?? '');

    // Delivery Type mapping
    let dType = 'by_person';
    if (alpineData.deliveryType === 'Registered Mail') dType = 'registered_mail';
    else if (alpineData.deliveryType === 'Electronic') dType = 'electronic';
    else if (alpineData.deliveryType === 'By Person') dType = 'by_person';
    formData.append('delivery_type', dType);
    formData.append('delivery_method', dType === 'electronic' ? 'electronic' : 'by_person');

    const providerValue = alpineData.registeredMailProvider === 'Others' && alpineData.registeredMailProviderOther
        ? alpineData.registeredMailProviderOther
        : (alpineData.registeredMailProvider ?? '');

    formData.append('by_person_who', alpineData.byPersonWho ?? '');
    formData.append('registered_mail_provider', providerValue);
    formData.append('electronic_method', dType === 'electronic' ? (alpineData.electronicMethod || 'Email') : (alpineData.electronicMethod ?? ''));
    formData.append('recipient_email', alpineData.recipientEmail ?? '');

    formData.append('action_delivery', alpineData.actionDelivery ? 1 : 0);
    formData.append('action_pick_up', alpineData.actionPickUp ? 1 : 0);
    formData.append('action_drop_off', alpineData.actionDropOff ? 1 : 0);
    formData.append('action_email', alpineData.actionEmail ? 1 : 0);

    // Delivery actions array
    if (alpineData.actionDelivery) formData.append('delivery_actions[]', 'delivery');
    if (alpineData.actionPickUp) formData.append('delivery_actions[]', 'pick_up');
    if (alpineData.actionDropOff) formData.append('delivery_actions[]', 'drop_off');
    if (alpineData.actionEmail || dType === 'electronic') formData.append('delivery_actions[]', 'email');

    // Signatories
    formData.append('approved_by_name', alpineData.previewApprovedBy ?? '');
    formData.append('operations_manager', alpineData.previewApprovedPosition ?? '');
    formData.append('approved_position', alpineData.previewApprovedPosition ?? '');
    formData.append('document_custodian', alpineData.previewCustodian ?? '');
    formData.append('delivered_by', alpineData.previewDeliveredBy ?? '');
    formData.append('received_by', alpineData.previewReceivedBy ?? '');
    formData.append('processing_company', alpineData.previewReceiverAffiliation ?? '');
    formData.append('receiver_affiliation', alpineData.previewReceiverAffiliation ?? '');
    formData.append('received_at', '');

    alpineData.previewItems.forEach((item, index) => {
        formData.append(`items[${index}][particular]`, item.particular ?? '');
        formData.append(`items[${index}][unique_id]`, item.unique_id ?? '');
        formData.append(`items[${index}][quantity]`, item.qty ?? '');
        formData.append(`items[${index}][description]`, item.description ?? '');
        formData.append(`items[${index}][remarks]`, item.remarks ?? '');
        if (item.file) {
            formData.append(`items[${index}][attachment]`, item.file);
            formData.append('documents[]', item.file);
        }
    });

    (alpineData.extraAttachments || []).forEach((file) => {
        formData.append('attachments[]', file);
    });

    try {
        const res = await fetch('/transmittal', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });

        const data = await res.json();

        if (!res.ok) {
            console.error('Transmittal save error:', data);
            let errMsg = 'Kindly fill up the required form fields.';
            if (data.errors) {
                const errorLines = Object.values(data.errors).flat();
                errMsg = 'Kindly fill up the required form fields:\n• ' + errorLines.join('\n• ');
            } else if (data.message) {
                errMsg = data.message;
            }
            alert(errMsg);
            return;
        }

        alert(data.message || (intent === 'submit' ? 'Transmittal submitted for review successfully.' : 'Transmittal saved successfully.'));
        
        if (intent === 'submit') {
            window.location.href = '/transmittal?filter=submitted';
            return;
        }

        currentWorkflowFilter = 'uploaded';
        currentPage = 1;
        closeAddSection();
        await renderTable();
    } catch (error) {
        console.error('Error in saveTransmittal:', error);
        alert('Something went wrong while saving the transmittal.');
    }
}

async function submitTransmittal(id) {
    try {
        const res = await fetch(`/transmittal/${id}/submit`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        const data = await res.json();

        if (!res.ok) {
            alert(data.message || 'Unable to submit transmittal.');
            return;
        }

        alert(data.message || 'Submitted successfully.');
        currentWorkflowFilter = 'submitted';
        currentPage = 1;
        await renderTable();
    } catch (error) {
        alert('Something went wrong while submitting.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const alpineData = getAlpineData();

    if (alpineData) {
        alpineData.removeTransmittalRow = function(index) {
            if (this.previewItems.length > 1) {
                this.previewItems.splice(index, 1);
                this.previewItems.forEach((item, i) => item.no = i + 1);
            }
        };
    }

    const downloadBtn = document.getElementById('download-preview-pdf');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function () {
            const element = document.getElementById('transmittal-preview-pdf');
            if (!element) return;

            html2pdf().set({
                margin: [0, 0, 0, 0],
                filename: 'transmittal-form.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] }
            }).from(element).save();
        });
    }

    document.addEventListener('keydown', (e) => {
        const alpineData = getAlpineData();
        if (!alpineData) return;

        if (e.key === 'Escape') {
            if (alpineData.showSlideOver) {
                closeAddSection();
            }
        }
    });

    if (transmittalPrefill) {
        resetTransmittalForm();
        applyTransmittalPrefill(transmittalPrefill);
        openAddSection(false);
    }

    const searchInput = document.getElementById('transmittalTableSearch');
    if (searchInput && currentSearchQuery) {
        searchInput.value = currentSearchQuery;
    }

    renderTable();
});
</script>
@endpush

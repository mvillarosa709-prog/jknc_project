@extends('layouts.app')

@section('content')
@php
    $companyHeader = $companyHeader ?? [
        'logo_url' => asset('images/jk-logo.png'),
        'company_name' => 'JOHN KELLY & COMPANY (JK&C INC)',
        'company_address' => '3F Cebu Holdings Center Cebu Business Park, Cebu City, Philippines, 6000',
    ];
@endphp
<div
    class="w-full px-6 mt-4 h-[calc(100vh-100px)] flex flex-col"
    x-data="obfPage()"
>
    <div class="bg-white rounded-xl border border-gray-200 flex flex-col flex-grow min-h-0">
        <div class="flex items-center justify-between px-5 py-4 border-b shrink-0 gap-4">
            <div>
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider">Human Capital</p>
                <h1 class="text-lg font-semibold text-gray-900">Official Business Trip Form</h1>
                <p class="text-xs text-gray-500">OBF records, employee fill-up form, and HR approval.</p>
            </div>

            <button
                type="button"
                @click="openAdd()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-semibold"
            >
                + Add New
            </button>
        </div>

        @if (session('success'))
            <div class="mx-5 mt-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mx-5 mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-semibold">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-5 mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                <p class="font-bold mb-1">Please fix the following:</p>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="p-5 flex-grow overflow-hidden">
            <div class="border rounded-xl h-full overflow-auto bg-white">
                <table class="w-full text-sm min-w-[1200px] border-collapse">
                    <thead class="bg-gray-50 text-gray-600 sticky top-0 z-20">
                        <tr>
                            <th class="p-3 text-left">Reference No.</th>
                            <th class="p-3 text-left">Employee</th>
                            <th class="p-3 text-left">Position</th>
                            <th class="p-3 text-left">Destination</th>
                            <th class="p-3 text-left">Date From</th>
                            <th class="p-3 text-left">Date To</th>
                            <th class="p-3 text-left">Trip Type</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template x-if="trips.length === 0">
                            <tr>
                                <td colspan="9" class="p-10 text-center text-gray-400">
                                    No OBF records yet. Click + Add New to create one.
                                </td>
                            </tr>
                        </template>

                        <template x-for="trip in trips" :key="trip.id">
                            <tr class="border-t hover:bg-gray-50">
                                <td class="p-3 font-semibold text-blue-700" x-text="trip.ob_reference_no || '-'"></td>
                                <td class="p-3">
                                    <div class="font-medium text-gray-900" x-text="trip.employee_name || '-'"></div>
                                    <div class="text-xs text-gray-500" x-text="trip.employee_code || '-'"></div>
                                </td>
                                <td class="p-3 text-gray-700" x-text="trip.position || '-'"></td>
                                <td class="p-3 text-gray-700" x-text="trip.destination || '-'"></td>
                                <td class="p-3 text-gray-700" x-text="trip.date_from || '-'"></td>
                                <td class="p-3 text-gray-700" x-text="trip.date_to || '-'"></td>
                                <td class="p-3 text-gray-700" x-text="trip.trip_type || '-'"></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="statusClass(trip.status)" x-text="trip.status || 'Pending'"></span>
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <button type="button" @click="openView(trip)" class="text-indigo-600 hover:underline text-xs font-semibold mr-3">View</button>
                                    <a
                                        :href="`{{ url('/api/official-business') }}/${trip.id}/history.pdf`"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-slate-600 hover:underline text-xs font-semibold mr-3"
                                    >
                                        Master History PDF
                                    </a>
                                    <a
                                        x-show="Number(trip.workflow_version || 1) >= 3 && trip.is_client_travel && (trip.trip_tickets || []).length"
                                        :href="`{{ route('activities') }}?activity_type=official_business_travel&source_id=${trip.id}&section=completion`"
                                        class="text-blue-600 hover:underline text-xs font-semibold mr-3"
                                        x-text="`Trip Tickets (${trip.trip_tickets?.length || 0})`"
                                    ></a>
                                    <button
                                        type="button"
                                        @click="openEdit(trip)"
                                        x-show="trip.can_edit"
                                        class="text-blue-600 hover:underline text-xs font-semibold mr-3"
                                    >
                                        Edit
                                    </button>

                                    <form
                                        :action="`{{ url('/human-capital/obf') }}/${trip.id}/approve`"
                                        method="POST"
                                        class="inline"
                                        x-show="
                                            canApproveObf
                                            && (
                                                Number(trip.workflow_version || 1) >= 3
                                                    ? trip.status === 'Submitted'
                                                    : trip.status === 'Pending'
                                            )
                                        "
                                    >
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:underline text-xs font-semibold mr-3">Approve</button>
                                    </form>

                                    <form
                                        :action="`{{ url('/human-capital/obf') }}/${trip.id}/reject`"
                                        method="POST"
                                        class="inline"
                                        x-show="
                                            canApproveObf
                                            && (
                                                Number(trip.workflow_version || 1) >= 3
                                                    ? trip.status === 'Submitted'
                                                    : trip.status === 'Pending'
                                            )
                                        "
                                    >
                                        @csrf
                                        <button type="submit" class="text-orange-600 hover:underline text-xs font-semibold mr-3">Reject</button>
                                    </form>

                                    <form
                                        :action="`{{ url('/human-capital/obf') }}/${trip.id}`"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Delete this OBF record?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            x-show="trip.can_delete"
                                            class="text-red-500 hover:underline text-xs font-semibold"
                                        >Delete</button>
                                    </form>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PDS / MRF STYLE SLIDING PANEL: LEFT PREVIEW + RIGHT FILL-UP FORM --}}
    <div
        x-show="showPanel"
        x-transition.opacity
        class="fixed inset-0 z-50 bg-black/40 flex justify-end"
        style="display: none;"
        @click.self="closePanel()"
    >
        <div
            x-transition:enter="transform transition ease-in-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="w-screen max-w-none h-full bg-white shadow-xl flex flex-col"
        >
            <div class="px-6 py-4 border-b bg-white flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold uppercase tracking-widest text-gray-900" x-text="panelTitle"></h2>
                    <p class="text-xs text-gray-500" x-text="form.ob_reference_no || 'Official Business Travel Form'"></p>
                </div>

                <button type="button" @click="closePanel()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>

            <div class="flex-1 min-h-0 grid grid-cols-1 grid-rows-[minmax(0,1fr)_minmax(0,1fr)] lg:grid-rows-1 lg:grid-cols-[58%_42%] gap-0 bg-gray-50">
                {{-- LEFT LIVE PREVIEW --}}
                <div class="min-h-0 flex flex-col border-r bg-gray-100">
                    <div class="shrink-0 flex items-center justify-between border-b border-gray-200 bg-gray-100 px-5 py-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Live Preview</p>
                        <button type="button" onclick="window.print()" class="px-3 py-2 border rounded-lg text-xs font-semibold text-gray-700 bg-white">
                            Download PDF
                        </button>
                    </div>

                    {{-- A4 PAPER PREVIEW --}}
                    <div class="flex-1 min-h-0 overflow-auto p-5">
                    <div class="obf-paper print-area">
                        <div class="obf-letterhead">
                            <img src="{{ $companyHeader['logo_url'] }}" onerror="this.style.display='none';" class="obf-logo" alt="Company Logo">
                            <p class="obf-company-name">{{ $companyHeader['company_name'] }}</p>
                            <p>{{ $companyHeader['company_address'] }}</p>
                        </div>

                        <div class="obf-title">
                            Official Business Travel Form
                        </div>

                        <div class="obf-reference">
                            <div><b>OB Reference No.:</b><strong x-text="form.ob_reference_no || '-'"></strong></div>
                            <div><b>Date & Time:</b><strong>{{ now()->format('n/j/Y h:i:s A') }}</strong></div>
                        </div>

                        <div x-show="form.classification" x-cloak>
                            @include('human-capital.partials.obf-create-preview')
                        </div>
                        <div x-show="!form.classification" x-cloak>
                                                                        <div class="section-title">
                            Client & Billability Section
                        </div>

                        <table class="preview-table text-xs">

                            {{-- NO: INTERNAL / NON-CLIENT --}}
                            <template x-if="form.is_client_travel == '0'">
                                <tbody>

                                    <tr>
                                        <td style="width:28%;">
                                            <b>Is this travel for a client?</b>
                                            <span class="ml-1">No</span>
                                        </td>

                                        <td style="width:72%;">
                                            <b>Travel Purpose Options:</b>
                                            <span
                                                class="ml-1"
                                                x-text="
                                                    Array.isArray(form.purpose_options)
                                                        && form.purpose_options.length
                                                        ? form.purpose_options
                                                            .filter(
                                                                option =>
                                                                    option !== 'Client Meeting'
                                                            )
                                                            .join(', ')
                                                        : '-'
                                                "
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <b>Purpose / Details:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.purpose || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr x-show="form.purpose_other">
                                        <td colspan="2">
                                            <b>If Others, specify:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.purpose_other || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Departure Date:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.date_from || '-'"
                                            ></span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Return Date:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.date_to || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Departure Time:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.departure_time || '-'"
                                            ></span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Return Time:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.return_time || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                </tbody>
                            </template>

                            {{-- YES: CLIENT O.B. --}}
                            <template x-if="form.is_client_travel == '1'">
                                <tbody>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Is this travel for a client?</b>
                                            <span class="ml-1">Yes</span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Client Coverage:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.client_mode === 'multiple' ? 'Multiple Clients' : 'Single Client'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <b x-text="form.client_mode === 'multiple' ? 'Primary Client:' : 'Selected Client Contact:'"></b>
                                            <span
                                                class="ml-1"
                                                x-text="
                                                    contacts.find(
                                                        contact => String(contact.id) === String(form.client_contact_id)
                                                    )?.label || '-'
                                                "
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Client Type:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.client_type || '-'"
                                            ></span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Client ID / CIF No.:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.client_id_no || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Client Name:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.client_name || '-'"
                                            ></span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Client Email:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.client_email || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Client Contact Number:</b>
                                            <span
                                                class="ml-1"
                                                x-text="selectedClientPhone || '-'"
                                            ></span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Company / Business Name:</b>
                                            <span
                                                class="ml-1"
                                                x-text="selectedClientCompany || '-'"
                                            ></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="width:50%;">
                                            <b>Contract Number:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.client_contract_number || '-'"
                                            ></span>
                                        </td>

                                        <td style="width:50%;">
                                            <b>Contract Type:</b>
                                            <span
                                                class="ml-1"
                                                x-text="form.contract_type || '-'"
                                            ></span>
                                        </td>
                                    </tr>


                                    <!-- SINGLE CLIENT: NO ADDITIONAL CLIENTS -->
                                    <template x-if="form.client_mode !== 'multiple'">
                                        <tr>
                                            <td colspan="2">
                                                <b>Travel Billability:</b>
                                                <span
                                                    class="ml-1"
                                                    x-text="form.travel_billability || '-'"
                                                ></span>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- MULTIPLE CLIENTS: SHOW ADDITIONAL CLIENTS -->
                                    <template x-if="form.client_mode === 'multiple'">
                                        <tr>
                                            <td style="width:50%;">
                                                <b>Travel Billability:</b>
                                                <span
                                                    class="ml-1"
                                                    x-text="form.travel_billability || '-'"
                                                ></span>
                                            </td>


                                        </tr>
                                    </template>

                                    <template
                                        x-if="
                                            form.client_mode === 'multiple'
                                            && Array.isArray(form.company_ids)
                                            && form.company_ids.filter(Boolean).length
                                        "
                                    >
                                                                                                        <tr
                                    x-show="
                                        form.client_mode === 'multiple'
                                        && Array.isArray(form.company_ids)
                                        && form.company_ids.filter(Boolean).length
                                    "
                                >
                                    <td colspan="2">
                                        <b>Additional Clients:</b>

                                        <div class="mt-1 min-h-[34px] rounded border border-blue-600 bg-white px-3 py-2">

                                            @foreach (\App\Models\Company::orderBy('company_name')->get(['id', 'company_name']) as $previewAdditionalCompany)
                                                <div
                                                    x-show="
                                                        Array.isArray(form.company_ids)
                                                        && form.company_ids
                                                            .filter(Boolean)
                                                            .map(String)
                                                            .includes('{{ $previewAdditionalCompany->id }}')
                                                    "
                                                >
                                                    {{ $previewAdditionalCompany->company_name }}
                                                    (#{{ $previewAdditionalCompany->id }})

</div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                                    </template>

                                </tbody>
                            </template>

                        </table>
<div class="section-title">Personnel Details</div>
                                                                        <table class="preview-table">
                            <tr>
                                <td>
                                    <b>Employee Name:</b>
                                    <span
                                        x-text="preview.full_name || form.employee_name || '-'"
                                    ></span>
                                </td>

                                <td>
                                    <b>Employee ID:</b>
                                    <span
                                        x-text="preview.employee_code || form.employee_code || '-'"
                                    ></span>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <b>Position:</b>
                                    <span
                                        x-text="preview.position || form.position || '-'"
                                    ></span>
                                </td>

                                <td>
                                    <b>Department / Unit:</b>
                                    <span
                                        x-text="preview.department || form.department || '-'"
                                    ></span>
                                </td>
                            </tr>
                                <tr>
                                    <td colspan="2">
                                        <b>Nature of Travel:</b>
                                        <span
                                            class="ml-1"
                                            x-text="
                                                (
                                                    preview.nature_of_travel
                                                    || form.nature_of_travel
                                                    || '-'
                                                )
                                            "
                                        ></span>
                                    </td>
                                </tr>

                            <tr
                                x-show="
                                    Array.isArray(form.team_members)
                                        ? form.team_members.some(
                                            member =>
                                                String(member || '').trim() !== ''
                                        )
                                        : String(form.team_members || '').trim() !== ''
                                "
                            >
                                <td colspan="2">
                                    <b>Team Members:</b>
                                    <span x-text="teamText()"></span>
                                </td>
                            </tr>
                        </table>




                        {{-- LIVE PREVIEW: TRAVEL INFORMATION --}}
                                                {{-- YES CLIENT ONLY: TRAVEL INFORMATION --}}
                        <template x-if="form.is_client_travel == '1'">
                            <div>
<div class="section-title">TRAVEL INFORMATION</div>

                        <table class="preview-table text-xs">
                            <tbody>

                                <tr>
                                    <td style="width:50%;">
                                        <b>Primary Destination:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.destination || '-'"
                                        ></span>
                                    </td>

                                    <td style="width:50%;">
                                        <b>Trip Type:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.trip_type || '-'"
                                        ></span>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">
                                        <b>Additional Stops:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.additional_stops || '-'"
                                        ></span>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">
                                        <b>Travel Purpose Options:</b>
                                        <span
                                            class="ml-1"
                                            x-text="
                                                Array.isArray(form.purpose_options)
                                                && form.purpose_options.length
                                                    ? form.purpose_options.join(', ')
                                                    : '-'
                                            "
                                        ></span>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">
                                        <b>Purpose / Details:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.purpose || '-'"
                                        ></span>
                                    </td>
                                </tr>

                                <tr x-show="form.purpose_other">
                                    <td colspan="2">
                                        <b>If Others, specify:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.purpose_other || '-'"
                                        ></span>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="width:50%;">
                                        <b>Departure Date:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.date_from || '-'"
                                        ></span>
                                    </td>

                                    <td style="width:50%;">
                                        <b>Return Date:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.date_to || '-'"
                                        ></span>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="width:50%;">
                                        <b>Departure Time:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.departure_time || '-'"
                                        ></span>
                                    </td>

                                    <td style="width:50%;">
                                        <b>Return Time:</b>
                                        <span
                                            class="ml-1"
                                            x-text="form.return_time || '-'"
                                        ></span>
                                    </td>
                                </tr>

                            </tbody>
                        </table>


                            </div>
                        </template>
                        {{-- YES CLIENT ONLY: CLIENT PAYMENT DETAILS --}}
                        <template x-if="form.is_client_travel == '1'">
                            <div>

                                <div class="section-title">CLIENT PAYMENT DETAILS</div>

                                <table class="preview-table text-xs">
                                    <tbody>

                                        <tr>
                                            <td style="width:50%;">
                                                <b>Will client pay for this travel?</b>

                                                <div class="mt-1 min-h-[34px] rounded border border-blue-600 bg-white px-3 py-2">
                                                    <span
                                                        x-text="form.client_payment_status || '-'"
                                                    ></span>
                                                </div>
                                            </td>

                                            <td style="width:50%;">
                                                <b>What will client pay for?</b>

                                                <div class="mt-1 min-h-[34px] rounded border border-blue-600 bg-white px-3 py-2">
                                                    <span
                                                        x-text="
                                                            Array.isArray(form.client_payment_items)
                                                            && form.client_payment_items.length
                                                                ? form.client_payment_items.join(', ')
                                                                : '-'
                                                        "
                                                    ></span>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="width:50%;">
                                                <b>If Others, specify:</b>

                                                <div class="mt-1 min-h-[34px] rounded border border-blue-600 bg-white px-3 py-2">
                                                    <span
                                                        x-text="form.client_payment_other || '-'"
                                                    ></span>
                                                </div>
                                            </td>

                                            <td style="width:50%;">
                                                <b>Amount Client Will Pay:</b>

                                                <div class="mt-1 min-h-[34px] rounded border border-blue-600 bg-white px-3 py-2">
                                                    <span
                                                        x-text="
                                                            form.amount_client_will_pay !== null
                                                            && form.amount_client_will_pay !== undefined
                                                            && form.amount_client_will_pay !== ''
                                                                ? Number(form.amount_client_will_pay || 0).toFixed(2)
                                                                : '0.00'
                                                        "
                                                    ></span>
                                                </div>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                            </div>
                        </template>

<div class="section-title">Mode of Transportation</div>

<div class="obf-transport-mirror">

    {{-- =====================================================
         BASE ROW
         Public Transport uses Mode in the second column.
         All other modes use Estimated Expenses.
         ===================================================== --}}

    <div class="obf-transport-field">
        <b>Transportation Mode:</b>
        <div
            class="obf-transport-value"
            x-text="form.transportation_mode || '-'"
        ></div>
    </div>

    <div class="obf-transport-field">

        <template
            x-if="
                ['Public Transportation', 'Public Transport']
                    .includes(form.transportation_mode)
            "
        >
            <div>
                <b>Mode:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.public_transport_mode || '-'"
                ></div>
            </div>
        </template>

        <template
            x-if="
                !['Public Transportation', 'Public Transport']
                    .includes(form.transportation_mode)
            "
        >
            <div>
                <b>Estimated Expenses:</b>
                <div
                    class="obf-transport-value"
                    x-text="
                        form.estimated_expenses !== ''
                        && form.estimated_expenses !== null
                        && form.estimated_expenses !== undefined
                            ? form.estimated_expenses
                            : '0.00'
                    "
                ></div>
            </div>
        </template>

    </div>


    {{-- =====================================================
         COMPANY VEHICLE
         ===================================================== --}}

    <template x-if="form.transportation_mode === 'Company Vehicle'">
        <div class="obf-transport-contents">

            <div class="obf-transport-field">
                <b>Vehicle:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.company_vehicle || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Plate Number:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.plate_number || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Driver:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.assigned_driver || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Vehicle Details:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.vehicle_details || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Odometer:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.odometer || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Fuel Level:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.fuel_level || '-'"
                ></div>
            </div>

        </div>
    </template>


    {{-- =====================================================
         PERSONAL / PRIVATE VEHICLE
         ===================================================== --}}

    <template
        x-if="
            ['Personal Vehicle', 'Private Vehicle']
                .includes(form.transportation_mode)
        "
    >
        <div class="obf-transport-contents">

            <div class="obf-transport-field">
                <b>Vehicle Details:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.private_vehicle_details || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Plate Number:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.plate_number || '-'"
                ></div>
            </div>

        </div>
    </template>


    {{-- =====================================================
         TAXI / RIDE-HAILING
         ===================================================== --}}

    <template x-if="form.transportation_mode === 'Taxi / Ride-Hailing'">
        <div class="obf-transport-contents">

            <div class="obf-transport-field">
                <b>Provider:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.ride_hailing_provider || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Estimated / Actual Cost:</b>
                <div
                    class="obf-transport-value"
                    x-text="
                        form.transportation_cost !== ''
                        && form.transportation_cost !== null
                        && form.transportation_cost !== undefined
                            ? form.transportation_cost
                            : '-'
                    "
                ></div>
            </div>

        </div>
    </template>


    {{-- =====================================================
         PUBLIC TRANSPORT
         Top-right Mode is already rendered in BASE ROW.
         ===================================================== --}}

    <template
        x-if="
            ['Public Transportation', 'Public Transport']
                .includes(form.transportation_mode)
        "
    >
        <div class="obf-transport-contents">

            <div class="obf-transport-field">
                <b>Details:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.public_transport_details || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Cost:</b>
                <div
                    class="obf-transport-value"
                    x-text="
                        form.transportation_cost !== ''
                        && form.transportation_cost !== null
                        && form.transportation_cost !== undefined
                            ? form.transportation_cost
                            : '-'
                    "
                ></div>
            </div>

        </div>
    </template>


    {{-- =====================================================
         AIR / SEA
         ===================================================== --}}

    <template
        x-if="
            ['Air / Sea', 'Air Travel', 'Sea Travel']
                .includes(form.transportation_mode)
        "
    >
        <div class="obf-transport-contents">

            <div class="obf-transport-field">
                <b>Carrier:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.carrier || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Route:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.transport_route || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Booking / Reference:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.booking_reference || '-'"
                ></div>
            </div>

            <div class="obf-transport-field">
                <b>Cost:</b>
                <div
                    class="obf-transport-value"
                    x-text="
                        form.transportation_cost !== ''
                        && form.transportation_cost !== null
                        && form.transportation_cost !== undefined
                            ? form.transportation_cost
                            : '-'
                    "
                ></div>
            </div>

            <template x-if="form.transportation_mode === 'Air Travel'">
                <div class="obf-transport-field">
                    <b>Legacy Air Details:</b>
                    <div
                        class="obf-transport-value"
                        x-text="form.air_travel_details || '-'"
                    ></div>
                </div>
            </template>

            <template x-if="form.transportation_mode === 'Sea Travel'">
                <div class="obf-transport-field">
                    <b>Legacy Sea Details:</b>
                    <div
                        class="obf-transport-value"
                        x-text="form.sea_travel_details || '-'"
                    ></div>
                </div>
            </template>

        </div>
    </template>


    {{-- =====================================================
         OTHER
         ===================================================== --}}

    <template x-if="form.transportation_mode === 'Other'">
        <div class="obf-transport-full">

            <div class="obf-transport-field">
                <b>Transportation Description:</b>
                <div
                    class="obf-transport-value"
                    x-text="form.other_transportation || '-'"
                ></div>
            </div>

        </div>
    </template>

</div>
                        </div>
<div class="section-title">Attachments</div>
                        <table class="preview-table">
                            <tr>
                                <td><b>Attachment Types:</b> <span x-text="attachmentText() || '-'"></span></td>
                                <td><b>If Others, specify:</b> <span x-text="form.attachment_other || '-'"></span></td>
                            </tr>

                        </table>

                        <div class="grid grid-cols-3 gap-8 mt-10 text-center">
                            <div>

                        {{-- LIVE PREVIEW: REMARKS --}}
                        <div class="section-title">REMARKS</div>

                        <table class="preview-table text-xs">
                            <tbody>
                                <tr>
                                    <td>
                                        <span x-text="form.remarks || '-'"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

<div class="border-t border-gray-700 pt-1">Requester</div>
                            </div>
                            <div>
                                <div class="border-t border-gray-700 pt-1">Immediate Superior</div>
                            </div>
                            <div>
                                <div class="border-t border-gray-700 pt-1">Authorized Approver</div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                {{-- RIGHT FILL-UP FORM --}}
                <div class="min-h-0 overflow-y-auto bg-white">
                    <div class="sticky top-0 z-20 bg-blue-700 text-white px-5 py-4">
                        <p class="text-xs font-bold uppercase tracking-widest">Fill Up Form</p>
                    </div>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        :action="isEdit ? `{{ url('/human-capital/obf') }}/${form.id}` : `{{ url('/human-capital/obf') }}`"
                        class="p-5 space-y-6"
                        @input="dirty = true"
                        @change="dirty = true"
                        @submit="dirty = false"
                     novalidate>
                        @csrf

                        <input type="hidden" name="ob_reference_no" :value="form.ob_reference_no || ''">
                        <input type="hidden" name="classification" :value="form.classification || ''">
                        <input type="hidden" name="employee_id" :value="form.classification ? (form.employee_id || '') : ''" :disabled="!form.classification">
                        <input type="hidden" name="nature_of_travel" :value="form.classification ? (form.nature_of_travel || '') : ''" :disabled="!form.classification">
                        <input type="hidden" name="details_json" :value="JSON.stringify(form.detail_rows || [])" :disabled="!form.classification">
                        <input type="hidden" name="attachment_rows_json" :value="JSON.stringify(form.classification ? form.create_attachments || [] : [])" :disabled="!form.classification">
                        <input type="hidden" name="team_natures_json" :value="JSON.stringify(teamNatures())" :disabled="!form.classification">
                        <input type="hidden" name="classification_change_confirmed" :value="form.classification_change_confirmed ? '1' : '0'" :disabled="!form.classification">
                        <template x-for="member in (form.classification ? form.team_rows || [] : [])" :key="member.employee_id">
                            <input type="hidden" name="team_member_employee_ids[]" :value="member.employee_id">
                        </template>

                        <template x-if="isEdit">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <template x-if="isView">
                            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider">Viewing Mode</p>
                                <p class="text-sm text-blue-900">Status: <span class="font-bold" x-text="form.status"></span></p>
                            </div>
                        </template>

                        @include('human-capital.partials.obf-create-form')
                        <fieldset x-show="!form.classification && isEdit" :disabled="Boolean(form.classification) || !isEdit" x-cloak>
                        {{-- A --}}
                        <section class="form-section" x-show="!form.classification">
                            <h3 class="form-title">Client & Billability Section</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Is this travel for a client?</label>

                                    <input
                                        type="hidden"
                                        name="is_client_travel"
                                        :value="form.is_client_travel"
                                    >

                                    <div
                                        class="grid grid-cols-2 gap-2"
                                        role="group"
                                        aria-label="Is this travel for a client?"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                if (!isView) {
                                                    form.is_client_travel = '1';
                                                    handleClientTravelChange();
                                                }
                                            "
                                            :disabled="isView"
                                            :aria-pressed="form.is_client_travel == '1'"
                                            :class="
                                                form.is_client_travel == '1'
                                                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                                                    : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400 hover:bg-blue-50'
                                            "
                                            class="h-10 rounded-lg border px-4 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            Yes
                                        </button>

                                        <button
                                            type="button"
                                            @click="
                                                if (!isView) {
                                                    form.is_client_travel = '0';
                                                    handleClientTravelChange();
                                                }
                                            "
                                            :disabled="isView"
                                            :aria-pressed="form.is_client_travel == '0'"
                                            :class="
                                                form.is_client_travel == '0'
                                                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                                                    : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400 hover:bg-blue-50'
                                            "
                                            class="h-10 rounded-lg border px-4 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            No
                                        </button>
                                    </div>

                                    <div
                                        x-show="form.is_client_travel == '1' && form.client_mode === 'multiple'"
                                        x-cloak
                                        class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3"
                                    >
                                        <div class="flex items-center justify-between gap-3 mb-2">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800">Additional Clients</p>
                                                <p class="text-[11px] text-gray-500">
                                                    Each selected company will receive its own O.B. Trip Ticket.
                                                </p>
                                            </div>

                                            <button
                                                type="button"
                                                @click="addClientCompany()"
                                                x-show="!isView"
                                                class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700"
                                            >
                                                + Add Client
                                            </button>
                                        </div>

                                        <template x-for="(companyId, index) in form.company_ids" :key="index">
                                            <div class="flex items-center gap-2 mt-2">
                                                <select
                                                    name="company_ids[]"
                                                    x-model="form.company_ids[index]"
                                                    :disabled="isView"
                                                    class="input flex-1"
                                                >
                                                    <option value="">Select additional company</option>
                                                    @foreach (\App\Models\Company::orderBy('company_name')->get(['id', 'company_name']) as $additionalCompany)
                                                        <option value="{{ $additionalCompany->id }}">{{ $additionalCompany->company_name }} (#{{ $additionalCompany->id }})</option>
                                                    @endforeach
                                                </select>

                                                <button
                                                    type="button"
                                                    @click="removeClientCompany(index)"
                                                    x-show="!isView"
                                                    class="px-3 py-2 rounded-md border border-red-200 text-red-600 text-xs font-semibold hover:bg-red-50"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </template>

                                        <p
                                            x-show="form.company_ids.length === 0"
                                            class="mt-2 text-xs text-gray-500"
                                        >
                                            Click Add Client to select another client.
                                        </p>

                                        <p class="mt-2 text-[11px] text-blue-700">
                                            A Multiple Client O.B. requires at least two different companies, including the Primary Client.
                                        </p>
                                    </div>

                                </div>

                                <div x-show="form.is_client_travel == '1'" x-cloak>
                                    <label class="label">Client Coverage</label>
                                    <select
                                        name="client_mode"
                                        x-model="form.client_mode"
                                        @change="handleClientModeChange()"
                                        :disabled="isView"
                                        class="input mb-3"
                                    >
                                        <option value="single">Single Client</option>
                                        <option value="multiple">Multiple Clients</option>
                                    </select>

                                    <label class="label" x-text="form.client_mode === 'multiple' ? 'Primary Client' : 'Select Client Contact'"></label>
                                    <select name="contact_id" x-model="form.client_contact_id" @change="selectClientContact()" :disabled="isView" class="input">
                                        <option value="">Select contact from Contacts</option>
                                        <template x-for="contact in contacts" :key="contact.id">
                                            <option :value="contact.id" x-text="contact.label"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-[11px] text-gray-400">This auto-fills the client details below from the Contacts module.</p>

                                </div>

                                                                <div
                                    x-show="
                                        form.is_client_travel == '1'
                                        && form.client_mode === 'multiple'
                                    "
                                    x-cloak
                                    class="col-span-2 rounded-lg border border-blue-200 bg-blue-50/40 p-4"
                                >
                                    <div class="mb-3">
                                        <p class="text-sm font-semibold text-gray-800">
                                            Per-Client Destinations
                                        </p>

                                        <p class="text-[11px] text-gray-500">
                                            Each selected client must have its own destination.
                                        </p>
                                    </div>

                                    <div class="space-y-3">
                                        <template
                                            x-for="(clientId, routeIndex) in multipleClientIds()"
                                            :key="clientId"
                                        >
                                            <div
                                                x-init="ensureClientRoute(clientId)"
                                                class="rounded-lg border border-gray-200 bg-white p-3"
                                            >
                                                <p class="mb-3 text-xs font-bold text-blue-700 break-words overflow-hidden">
                                                    Client
                                                    <span x-text="routeIndex + 1"></span>
                                                    <span x-text="companyName(clientId)"></span>
                                                </p>

                                                <input
                                                    type="hidden"
                                                    :name="`client_routes[${routeIndex}][company_id]`"
                                                    :value="clientId"
                                                >

                                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                    <div>
                                                        <label class="label">
                                                            Destination
                                                            <span class="text-red-500">*</span>
                                                        </label>

                                                        <input
                                                            type="text"
                                                            :name="`client_routes[${routeIndex}][destination]`"
                                                            x-model="ensureClientRoute(clientId).destination"
                                                            list="obfDestinationSuggestions"
                                                            :readonly="isView"
                                                            :required="
                                                                !isView
                                                                && form.client_mode === 'multiple'
                                                            "
                                                            placeholder="e.g. ABC Company Office / BIR Cebu"
                                                            class="input"
                                                        >
                                                    </div>

                                                    <div>
                                                        <label class="label">
                                                            Activity / Purpose
                                                        </label>

                                                        <input
                                                            type="text"
                                                            :name="`client_routes[${routeIndex}][activity]`"
                                                            x-model="ensureClientRoute(clientId).activity"
                                                            :readonly="isView"
                                                            placeholder="e.g. Client meeting / Submit BIR requirement"
                                                            class="input"
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <p
                                            x-show="multipleClientIds().length < 2"
                                            class="text-xs font-semibold text-amber-600"
                                        >
                                            Multiple Client O.B. requires at least two clients.
                                        </p>
                                    </div>
                                </div>
                                {{-- OBF-NO-CLIENT-DETAILS --}}
                                <div
                                    x-show="form.is_client_travel == '0'"
                                    x-cloak
                                    class="col-span-2"
                                >
                                    <div class="grid grid-cols-2 gap-4">

                                        <div class="col-span-2">
                                            <label class="label">
                                                Travel Purpose Options
                                            </label>

                                            <div class="grid grid-cols-3 gap-2 text-sm">
                                                <template
                                                    x-for="option in purposeOptionList.filter(option => option !== 'Client Meeting')"
                                                    :key="`internal-${option}`"
                                                >
                                                    <label class="check-card">
                                                        <input
                                                            type="checkbox"
                                                            :value="option"
                                                            x-model="form.purpose_options"
                                                            :disabled="isView"
                                                        >
                                                        <span x-text="option"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="col-span-2">
                                            <label class="label">
                                                Purpose / Details
                                                <span class="text-red-500">*</span>
                                            </label>

                                            <textarea
                                                x-model="form.purpose"
                                                :readonly="isView"
                                                rows="4"
                                                class="input"
                                            ></textarea>
                                        </div>

                                        <div class="col-span-2">
                                            <label class="label">
                                                If Others, specify
                                            </label>

                                            <input
                                                type="text"
                                                x-model="form.purpose_other"
                                                :readonly="isView"
                                                class="input"
                                            >
                                        </div>

                                        <div>
                                            <label class="label">
                                                Departure Date
                                                <span class="text-red-500">*</span>
                                            </label>

                                            <input
                                                type="date"
                                                x-model="form.date_from"
                                                :readonly="isView"
                                                class="input"
                                            >
                                        </div>

                                        <div>
                                            <label class="label">
                                                Return Date
                                            </label>

                                            <input
                                                type="date"
                                                x-model="form.date_to"
                                                :readonly="isView"
                                                class="input"
                                            >
                                        </div>

                                        <div>
                                            <label class="label">
                                                Departure Time
                                            </label>

                                            <input
                                                type="time"
                                                x-model="form.departure_time"
                                                :readonly="isView"
                                                class="input"
                                            >
                                        </div>

                                        <div>
                                            <label class="label">
                                                Return Time
                                            </label>

                                            <input
                                                type="time"
                                                x-model="form.return_time"
                                                :readonly="isView"
                                                class="input"
                                            >
                                        </div>

                                    </div>
                                </div>
<div x-show="form.is_client_travel == '1'" x-cloak>
                                    <label class="label">Client Type</label>
                                    <select name="client_type" x-model="form.client_type" :disabled="isView" class="input">
                                        <option value="">Select client type</option>
                                        <option>Local</option>
                                        <option>International</option>
                                    </select>
                                </div>

                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Client ID / CIF No.</label><input name="client_id_no" x-model="form.client_id_no" :readonly="isView" class="input"></div>
                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Client Name</label><input name="client_name" x-model="form.client_name" :readonly="isView" class="input"></div>
                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Client Email</label><input type="email" name="client_email" x-model="form.client_email" :readonly="isView" class="input"></div>
                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Client Contact Number</label><input x-model="selectedClientPhone" readonly class="input bg-gray-100"></div>
                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Company / Business Name</label><input x-model="selectedClientCompany" readonly class="input bg-gray-100"></div>
                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Contract Number</label><input name="client_contract_number" x-model="form.client_contract_number" :readonly="isView" class="input"></div>
                                <div x-show="form.is_client_travel == '1'" x-cloak><label class="label">Contract Type</label><input name="contract_type" x-model="form.contract_type" :readonly="isView" class="input"></div>

                                <div x-show="form.is_client_travel == '1'" x-cloak>
                                    <label class="label">Travel Billability</label>
                                    <select name="travel_billability" x-model="form.travel_billability" :disabled="isView" class="input">
                                        <option value="">Select billability</option>
                                        <option>Billable</option>
                                        <option>Non-billable</option>
                                        <option>Partially Billable</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        {{-- B --}}
                        <section class="form-section">
                            <h3 class="form-title">Personnel Details</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="label">Employee <span class="text-red-500">*</span></label>
                                    <select
                                        name="employee_id"
                                        x-model="form.employee_id"
                                        @change="selectEmployee()"
                                        :disabled="
                                            isView
                                            || (
                                                isEdit
                                                && !(
                                                    Number(form.workflow_version || 1) >= 3
                                                    && ['Draft', 'Reverted'].includes(form.status)
                                                )
                                            )
                                        "
                                        required
                                        class="input bg-white disabled:bg-gray-100"
                                    >
                                        <option value="">Select employee</option>
                                        <template x-for="employee in employees" :key="employee.id">
                                            <option :value="employee.id" x-text="`${employee.employee_code} - ${employee.full_name}`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div><label class="label">Employee ID</label><input x-model="preview.employee_code" readonly class="input bg-gray-100"></div>
                                <div class="hidden"><label class="label">Email</label><input x-model="preview.email" readonly class="input bg-gray-100"></div>
                                <div class="hidden"><label class="label">Contact Number</label><input x-model="preview.phone_number" readonly class="input bg-gray-100"></div>
                                <div><label class="label">Position</label><input x-model="preview.position" readonly class="input bg-gray-100"></div>
                                <div><label class="label">Department / Unit</label><input x-model="preview.department" readonly class="input bg-gray-100"></div>

                                <div>
                                    <label class="label">Nature of Travel</label>
                                    <select name="nature_of_travel" x-model="form.nature_of_travel" :disabled="isView" class="input">
                                        <option value="">Select nature</option>
                                        <option>Individual</option>
                                        <option>Team</option>
                                    </select>
                                </div>

                                <div><label class="label">Immediate Superior</label><input name="immediate_superior" x-model="form.immediate_superior" :readonly="isView" class="input"></div>
                                <div><label class="label">Superior Email</label><input type="email" name="superior_email" x-model="form.superior_email" :readonly="isView" class="input"></div>

                                <div class="col-span-2">
                                    <label class="label">Team Members</label>
                                    <div class="grid grid-cols-3 gap-3" x-show="!form.legacy_team_members">
                                        <template x-for="(memberId, index) in form.team_member_employee_ids" :key="index">
                                            <select name="team_member_employee_ids[]" x-model="form.team_member_employee_ids[index]" @change="syncTeamNames()" :disabled="isView || form.legacy_team_members" class="input bg-white">
                                                <option value="">Select employee</option>
                                                <template x-for="employee in personnelEmployees" :key="employee.id">
                                                    <option :value="employee.id" x-text="`${employee.employee_code} - ${employee.full_name}`"></option>
                                                </template>
                                            </select>
                                        </template>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3" x-show="form.legacy_team_members">
                                        <template x-for="(member, index) in form.team_members" :key="index">
                                            <input name="team_members[]" x-model="form.team_members[index]" :disabled="!form.legacy_team_members" :readonly="isView" :placeholder="`Team member ${index + 1}`" class="input">
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- C --}}
                        <section class="form-section" x-show="form.is_client_travel == '1'" x-cloak>
                            <h3 class="form-title">Travel Information</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div x-show="form.client_mode !== 'multiple'" x-cloak>
                                    <label class="label">
                                        Primary Destination
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        name="destination"
                                        x-model="form.destination"
                                        list="obfDestinationSuggestions"
                                        :readonly="isView"
                                        :required="form.client_mode !== 'multiple'"
                                        class="input"
                                    >
                                </div>

                                <datalist id="obfDestinationSuggestions">
                                    <template x-for="destination in destinationSuggestions" :key="destination">
                                        <option :value="destination"></option>
                                    </template>
                                </datalist>

                                <div>
                                    <label class="label">Trip Type</label>
                                    <select name="trip_type" x-model="form.trip_type" :disabled="isView" class="input">
                                        <option value="">Select trip type</option>
                                        <option>One-way</option>
                                        <option>Roundtrip</option>
                                        <option>Multi-stop</option>
                                    </select>
                                </div>

                                <div class="col-span-2">
                                    <label class="label">Additional Stops</label>
                                    <textarea name="additional_stops" x-model="form.additional_stops" :readonly="isView" rows="2" class="input"></textarea>
                                </div>

                                <div class="col-span-2">
                                    <label class="label">Travel Purpose Options</label>
                                    <div class="grid grid-cols-3 gap-2 text-sm">
                                        <template x-for="option in purposeOptionList" :key="option">
                                            <label class="check-card">
                                                <input type="checkbox" name="purpose_options[]" :value="option" x-model="form.purpose_options" :disabled="isView">
                                                <span x-text="option"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div class="col-span-2">
                                    <label class="label">Purpose / Details <span class="text-red-500">*</span></label>
                                    <textarea name="purpose" x-model="form.purpose" :readonly="isView" required rows="4" class="input"></textarea>
                                </div>

                                <div class="col-span-2">
                                    <label class="label">If Others, specify</label>
                                    <input name="purpose_other" x-model="form.purpose_other" :readonly="isView" class="input">
                                </div>

                                <div>
                                    <label class="label">Departure Date <span class="text-red-500">*</span></label>
                                    <input type="date" name="date_from" x-model="form.date_from" :readonly="isView" required class="input">
                                </div>

                                <div>
                                    <label class="label">Return Date</label>
                                    <input type="date" name="date_to" x-model="form.date_to" :readonly="isView" class="input">
                                </div>

                                <div>
                                    <label class="label">Departure Time</label>
                                    <input type="time" name="departure_time" x-model="form.departure_time" :readonly="isView" class="input">
                                </div>

                                <div>
                                    <label class="label">Return Time</label>
                                    <input type="time" name="return_time" x-model="form.return_time" :readonly="isView" class="input">
                                </div>
                            </div>
                        </section>

                        {{-- D --}}
                        <section
                            class="form-section"
                            x-show="form.is_client_travel == '1'"
                            x-cloak
                        >
                            <h3 class="form-title">Client Payment Details</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Will client pay for this travel?</label>
                                    <select name="client_payment_status" x-model="form.client_payment_status" :disabled="isView" class="input">
                                        <option value="">Select</option>
                                        <option>Yes</option>
                                        <option>No</option>
                                        <option>Partial</option>
                                    </select>
                                </div>

                                <div><label class="label">Amount Client Will Pay</label><input type="number" step="0.01" name="amount_client_will_pay" x-model="form.amount_client_will_pay" :readonly="isView" class="input"></div>

                                <div class="col-span-2">
                                    <label class="label">What will client pay for?</label>
                                    <div class="grid grid-cols-4 gap-2 text-sm">
                                        <template x-for="option in clientPaymentOptionList" :key="option">
                                            <label class="check-card">
                                                <input type="checkbox" name="client_payment_items[]" :value="option" x-model="form.client_payment_items" :disabled="isView">
                                                <span x-text="option"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div class="col-span-2"><label class="label">If Others, specify</label><input name="client_payment_other" x-model="form.client_payment_other" :readonly="isView" class="input"></div>
                            </div>
                        </section>

                        {{-- E --}}


                        {{-- F --}}
                        <section class="form-section">
                            <h3 class="form-title">Mode of Transportation</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Transportation Mode</label>
                                    <select name="transportation_mode" x-model="form.transportation_mode" :disabled="isView" class="input">
                                        <option value="">Select mode</option>
                                        <option>Company Vehicle</option>
                                        <option>Personal Vehicle</option>
                                        <option>Taxi / Ride-Hailing</option>
                                        <option>Public Transportation</option>
                                        <option>Air / Sea</option>
                                        <option>Other</option>
                                        <option value="Private Vehicle">Private Vehicle (Legacy)</option>
                                        <option value="Public Transport">Public Transport (Legacy)</option>
                                        <option value="Air Travel">Air Travel (Legacy)</option>
                                        <option value="Sea Travel">Sea Travel (Legacy)</option>
                                    </select>
                                </div>

                                <div>
    <template
        x-if="
            ['Public Transportation', 'Public Transport']
                .includes(form.transportation_mode)
        "
    >
        <div>
            <label class="label">Mode</label>
            <input
                name="public_transport_mode"
                x-model="form.public_transport_mode"
                :readonly="isView"
                class="input"
                placeholder="Bus, jeepney, train, etc."
            >
        </div>
    </template>

    <template
        x-if="
            !['Public Transportation', 'Public Transport']
                .includes(form.transportation_mode)
        "
    >
        <div>
            <label class="label">Estimated Expenses</label>
            <input
                type="number"
                step="0.01"
                name="estimated_expenses"
                x-model="form.estimated_expenses"
                :readonly="isView"
                class="input"
            >
        </div>
    </template>
</div>

                                <template x-if="form.transportation_mode === 'Company Vehicle'">
                                    <div class="col-span-2 grid grid-cols-2 gap-4">
                                        <div><label class="label">Vehicle</label><input name="company_vehicle" x-model="form.company_vehicle" :readonly="isView" class="input"></div>
                                        <div><label class="label">Plate Number</label><input name="plate_number" x-model="form.plate_number" :readonly="isView" class="input"></div>
                                        <div><label class="label">Driver</label><input name="assigned_driver" x-model="form.assigned_driver" :readonly="isView" class="input"></div>
                                        <div><label class="label">Vehicle Details</label><input name="vehicle_details" x-model="form.vehicle_details" :readonly="isView" class="input"></div>
                                        <div><label class="label">Odometer</label><input name="odometer" x-model="form.odometer" :readonly="isView" class="input"></div>
                                        <div><label class="label">Fuel Level</label><input name="fuel_level" x-model="form.fuel_level" :readonly="isView" class="input"></div>
                                    </div>
                                </template>

                                <template x-if="['Personal Vehicle', 'Private Vehicle'].includes(form.transportation_mode)">
                                    <div class="col-span-2 grid grid-cols-2 gap-4">
                                        <div><label class="label">Vehicle Details</label><input name="private_vehicle_details" x-model="form.private_vehicle_details" :readonly="isView" class="input"></div>
                                        <div><label class="label">Plate Number</label><input name="plate_number" x-model="form.plate_number" :readonly="isView" class="input"></div>
                                    </div>
                                </template>

                                <template x-if="form.transportation_mode === 'Taxi / Ride-Hailing'">
                                    <div class="col-span-2 grid grid-cols-2 gap-4">
                                        <div><label class="label">Provider</label><input name="ride_hailing_provider" x-model="form.ride_hailing_provider" :readonly="isView" class="input" placeholder="Taxi, Grab, JoyRide, etc."></div>
                                        <div><label class="label">Estimated / Actual Cost</label><input type="number" min="0" step="0.01" name="transportation_cost" x-model="form.transportation_cost" :readonly="isView" class="input"></div>
                                    </div>
                                </template>

                                <template x-if="['Public Transportation', 'Public Transport'].includes(form.transportation_mode)">
    <div class="col-span-2 grid grid-cols-2 gap-4">

        <div>
            <label class="label">Details</label>
            <input
                name="public_transport_details"
                x-model="form.public_transport_details"
                :readonly="isView"
                class="input"
            >
        </div>

        <div>
            <label class="label">Cost</label>
            <input
                type="number"
                min="0"
                step="0.01"
                name="transportation_cost"
                x-model="form.transportation_cost"
                :readonly="isView"
                class="input"
            >
        </div>

    </div>
</template>

                                <template x-if="['Air / Sea', 'Air Travel', 'Sea Travel'].includes(form.transportation_mode)">
                                    <div class="col-span-2 grid grid-cols-2 gap-4">
                                        <div><label class="label">Carrier</label><input name="carrier" x-model="form.carrier" :readonly="isView" class="input"></div>
                                        <div><label class="label">Route</label><input name="transport_route" x-model="form.transport_route" :readonly="isView" class="input"></div>
                                        <div><label class="label">Booking / Reference</label><input name="booking_reference" x-model="form.booking_reference" :readonly="isView" class="input"></div>
                                        <div><label class="label">Cost</label><input type="number" min="0" step="0.01" name="transportation_cost" x-model="form.transportation_cost" :readonly="isView" class="input"></div>
                                        <div x-show="form.transportation_mode === 'Air Travel'"><label class="label">Legacy Air Details</label><input name="air_travel_details" x-model="form.air_travel_details" :readonly="isView" class="input"></div>
                                        <div x-show="form.transportation_mode === 'Sea Travel'"><label class="label">Legacy Sea Details</label><input name="sea_travel_details" x-model="form.sea_travel_details" :readonly="isView" class="input"></div>
                                    </div>
                                </template>

                                <template x-if="form.transportation_mode === 'Other'">
                                    <div class="col-span-2"><label class="label">Transportation Description</label><input name="other_transportation" x-model="form.other_transportation" :readonly="isView" class="input"></div>
                                </template>
                            </div>
                        </section>

                        </fieldset>
                        {{-- G --}}
                        <section class="form-section" x-show="!form.classification">
                            <h3 class="form-title">Attachments</h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="label">Attachment Type</label>
                                    <div class="grid grid-cols-4 gap-2 text-sm">
                                        <template x-for="option in attachmentTypeList" :key="option">
                                            <label class="check-card">
                                                <input type="checkbox" name="attachment_types[]" :value="option" x-model="form.attachment_types" :disabled="isView">
                                                <span x-text="option"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div><label class="label">If Others, specify</label><input name="attachment_other" x-model="form.attachment_other" :readonly="isView" class="input"></div>

                                <template x-if="!isView">
                                    <div>
                                        <label class="label">Upload Attachments</label>
                                        <input type="file" name="attachments[]" multiple class="input">
                                        <p class="text-[11px] text-gray-500 mt-1">Accepted: PDF, JPG, PNG, DOC, DOCX. Max 10MB each.</p>
                                    </div>
                                </template>

                                <template x-if="form.attachment_paths && form.attachment_paths.length">
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Uploaded Files</p>
                                        <template x-for="attachment in form.attachment_paths" :key="attachment.path">
                                            <a :href="attachment.url" target="_blank" class="block px-3 py-2 rounded-lg border border-gray-200 text-sm text-blue-700 hover:bg-blue-50 mb-2" x-text="attachment.original_name || 'Attachment'"></a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </section>

                        <section class="form-section">
                            <h3 class="form-title">Remarks</h3>
                            <textarea name="remarks" x-model="form.remarks" :readonly="isView" rows="4" class="input"></textarea>
                        </section>

                        <div class="sticky bottom-0 bg-white border-t py-4 flex justify-end gap-3">
                            <button type="button" @click="closePanel()" class="px-5 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>

                            <template x-if="isView && form.can_edit">
                                <button type="button" @click="openEdit(form)" class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                    Edit OBF
                                </button>
                            </template>

                            <template x-if="!isView">
                                <div class="flex items-center gap-2">
                                    <template
                                        x-if="
                                            !isEdit
                                            || (
                                                Number(form.workflow_version || 1) >= 3
                                                && ['Draft', 'Reverted'].includes(form.status)
                                            )
                                        "
                                    >
                                        <button type="submit" name="intent" value="save_draft"
                                            formnovalidate
                                            class="px-5 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50"
                                        >
                                            Save Draft
                                        </button>
                                    </template>

                                    <button type="submit" name="intent" value="submit"
                                        class="px-6 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700"
                                    >
                                        <span x-text="isEdit && Number(form.workflow_version || 1) < 3 ? 'Update OBF' : 'Submit O.B.'"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.input {
    width: 100%;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid rgb(209 213 219);
    border-radius: 0.5rem;
    outline: none;
}
.input:focus {
    border-color: rgb(59 130 246);
    box-shadow: 0 0 0 2px rgb(191 219 254);
}
.label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(75 85 99);
    margin-bottom: 0.25rem;
}
.form-section {
    border: 1px solid rgb(229 231 235);
    border-radius: 0.75rem;
    padding: 1rem;
}
.form-title {
    font-size: 0.75rem;
    font-weight: 800;
    color: rgb(37 99 235);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgb(229 231 235);
    padding-bottom: 0.6rem;
    margin-bottom: 1rem;
}
.check-card {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid rgb(229 231 235);
    border-radius: 0.5rem;
    padding: 0.55rem 0.7rem;
}
.obf-paper {
    background: white;
    border-left: 3px solid #1d4ed8;
    box-shadow: 0 10px 22px rgb(15 23 42 / 0.16);
    color: #001b5f;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    line-height: 1.35;
    margin: 0 auto;
    min-height: 1123px;
    padding: 28px 28px 40px;
    width: 794px;
}
.obf-letterhead {
    border-bottom: 4px solid #2654e8;
    color: #0b1b3f;
    margin-bottom: 30px;
    padding-bottom: 18px;
    text-align: center;
}
.obf-logo {
    height: 76px;
    margin: 0 auto 8px;
    object-fit: contain;
}
.obf-company-name {
    color: #000;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
}
.obf-partners {
    color: #000;
    font-size: 12px;
    font-weight: 800;
}
.obf-letterhead p {
    margin: 4px 0;
}
.obf-title {
    background: #2654e8;
    border-radius: 7px;
    color: white;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.08em;
    margin-bottom: 18px;
    padding: 10px 18px;
    text-transform: uppercase;
}
.obf-reference {
    display: grid;
    gap: 16px 26px;
    grid-template-columns: max-content 1fr;
    margin-bottom: 28px;
}
.obf-reference b {
    color: #06236d;
    display: inline-block;
    min-width: 132px;
}
.obf-reference strong {
    color: #000;
    font-size: 13px;
}
.obf-config {
    align-self: center;
    border: 1px solid #22c55e;
    border-radius: 999px;
    color: #047857;
    font-size: 11px;
    font-weight: 700;
    justify-self: end;
    padding: 4px 12px;
}
.section-title {
    background: #173684;
    color: white;
    border: 0;
    border-radius: 5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0.5rem 0.9rem;
    margin: 1.25rem 0 0.75rem;
}
.preview-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.6rem;
    margin-bottom: 0;
}
.preview-table td {
    border: 0;
    padding: 0 0.75rem 0 0;
    vertical-align: top;
}
.preview-table b {
    color: #001b5f;
    display: block;
    font-size: 11px;
    margin-bottom: 0.3rem;
}
.preview-table td > span {
    background: #f8fafc;
    border: 1px solid #1d4ed8;
    border-radius: 5px;
    color: #111827;
    display: block;
    min-height: 32px;
    padding: 8px 10px;
    white-space: pre-line;
}
.preview-table td[colspan="2"] > span {
    min-height: 44px;
}
@media print {
    body * {
        visibility: hidden;
    }
    .print-area, .print-area * {
        visibility: visible;
    }
    @page {
        size: A4;
        margin: 0;
    }
    .print-area {
        position: fixed;
        inset: 0;
        width: 794px;
        min-height: 1123px;
        box-shadow: none;
        border: none;
        padding: 28px;
    }
}

/* Remove only the far-left blue border from the OBF preview sheet */
.print-area {
    border-left: none !important;
}

.print-area::before,
.print-area::after {
    border-left: none !important;
}

/* Smart OBF print: Alpine-hidden information occupies no print space */
.print-area [style*="display: none"] {
    display: none !important;
}

@media print {
    .print-area tr[style*="display: none"],
    .print-area td[style*="display: none"],
    .print-area .section-title[style*="display: none"],
    .print-area .preview-table[style*="display: none"] {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>

<script>
function obfPage() {
    return {
        employees: @json($employees),
        personnelEmployees: @json($personnelEmployees),
        classifications: @json($obClassifications),
        internalActivityTypes: @json($internalActivityTypes),
        socialActivityTypes: @json($socialActivityTypes),
        companies: @json($obCompanies),
        departments: @json($obDepartments),
        suggestions: {from: [], destination: [], purpose: []},
        engagements: [],
        teamModalOpen: false,
        teamDraft: {employee_id: '', nature_of_travel: ''},
        detailModalOpen: false,
        detailModalIndex: null,
        detailDraft: {},
        currentEmployeeId: @json($currentEmployee?->id),
        contacts: @json($contacts ?? []),
        trips: @json($trips),
        get destinationSuggestions() {
            return [...new Set(this.trips.flatMap(trip => [
                trip.destination,
                ...(trip.trip_tickets || []).flatMap(ticket => (ticket.stops || []).map(stop => stop.destination)),
            ]).map(value => String(value || '').trim()).filter(Boolean))].sort();
        },
        canManageObf: @json($canManageObf ?? false),
        canApproveObf: @json($canApproveObf ?? false),

        showPanel: false,
        isEdit: false,
        isView: false,
        dirty: false,

        purposeOptionList: ['Official Business Travel', 'Site Inspection', 'Training / Seminar', 'Operations Support', 'Field Work', 'Audit', 'Client Meeting', 'Delivery / Pickup', 'Others'],
        clientPaymentOptionList: ['Transportation', 'Meals Per Diem', 'Miscellaneous', 'Vehicle Rental', 'Fuel', 'Airline Boat Tickets', 'Driver Fee', 'Toll Parking', 'Accommodation', 'Others'],
        attachmentTypeList: ['Rental Contract', 'Flight Ticket', 'Transmital form', 'Memo', 'Client Contract', 'Clients Approval', 'Picture of Meter', 'Others'],
        createAttachmentTypes: ['Rental Contract', 'Flight Ticket', 'Transmittal Form', 'Memo', 'Client Contract', 'Client Approval', 'Picture of Meter', 'Others'],

        form: {},
        preview: {},

        classificationLabel(value = this.form.classification) {
            return {client: 'Client O.B.', internal: 'Internal / Company O.B.', social: 'Social / Marketing / Non-Creditable'}[value] || '-';
        },
        detailSectionTitle() {
            return {client: 'Client Official Business', internal: 'Internal O.B. Details', social: 'Social / Marketing Activity'}[this.form.classification] || 'O.B. Details';
        },
        detailAddLabel() {
            return {client: '+ Add Client', internal: '+ Add Internal Activity', social: '+ Add Activity'}[this.form.classification] || '+ Add Detail';
        },
        changeClassification(value) {
            if (this.isView || this.form.classification === value) return;
            const linked = (this.form.create_attachments || []).some(item => !item.remove && item.detail_key);
            if (linked) {
                window.alert('Relink or remove each detail-linked attachment before changing classification.');
                return;
            }
            if ((this.form.detail_rows || []).length) {
                if (!window.confirm('Changing the O.B. Classification will remove the current O.B. Detail entries because their fields are specific to the selected classification. Continue?')) return;
                this.form.detail_rows = [];
                this.form.classification_change_confirmed = true;
            }
            this.form.classification = value;
            this.form.is_client_travel = value === 'client' ? '1' : '0';
            this.detailModalOpen = false;
            this.dirty = true;
        },
        selectedTeamEmployee() {
            return this.personnelEmployees.find(employee => String(employee.id) === String(this.teamDraft.employee_id));
        },
        addTeamMember() {
            const employee = this.selectedTeamEmployee();
            if (!employee || !this.teamDraft.nature_of_travel || String(employee.id) === String(this.form.employee_id)
                || (this.form.team_rows || []).some(row => String(row.employee_id) === String(employee.id))) return;
            this.form.team_rows.push({...employee, employee_id: employee.id, nature_of_travel: this.teamDraft.nature_of_travel});
            this.teamModalOpen = false;
            this.dirty = true;
        },
        removePrimaryFromTeam() {
            this.form.team_rows = (this.form.team_rows || []).filter(row => String(row.employee_id) !== String(this.form.employee_id));
        },
        removeTeamMember(index) {
            this.form.team_rows.splice(index, 1);
            this.dirty = true;
        },
        teamNatures() {
            const result = {};
            if (this.form.employee_id) result[this.form.employee_id] = this.form.nature_of_travel || '';
            for (const row of this.form.team_rows || []) result[row.employee_id] = row.nature_of_travel;
            return result;
        },
        clientName(id) { return this.companies.find(item => String(item.id) === String(id))?.company_name || '-'; },
        departmentName(id) { return this.departments.find(item => String(item.id) === String(id))?.department_name || '-'; },
        contactName(id) { return this.contacts.find(item => String(item.id) === String(id))?.full_name || ''; },
        detailHeading(row) {
            if (this.form.classification === 'client') return this.clientName(row.company_id);
            if (this.form.classification === 'social') return row.activity_event_name || '-';
            return row.from_location || '-';
        },
        detailRelated(row) {
            if (this.form.classification === 'client') return this.engagements.find(item => String(item.id) === String(row.project_id))?.name || row.engagement_name || (row.project_id ? `#${row.project_id}` : '-');
            if (this.form.classification === 'internal') return this.departmentName(row.department_id);
            return row.organization_host || '-';
        },
        detailDescription(row) {
            if (this.form.classification === 'client') return `${row.engagement_type || '-'} / ${this.detailRelated(row)} / Credit: ${row.travel_credit_balance ?? '—'}`;
            if (this.form.classification === 'internal') return `${row.internal_activity_type || '-'} / ${this.departmentName(row.department_id)}`;
            return `${row.social_activity_type || '-'} / ${row.organization_host || '-'}`;
        },
        detailTripLabel(row) {
            if (this.form.classification === 'client') {
                return this.clientName(row.company_id) + (row.engagement_name ? ` / ${row.engagement_name}` : '');
            }
            if (this.form.classification === 'internal') {
                return `${row.internal_activity_type || 'Internal O.B.'}${row.department_id ? ` / ${this.departmentName(row.department_id)}` : ''}`;
            }
            return row.activity_event_name || 'Social / Marketing Activity';
        },
        transportationDescription(transportation) {
            const fields = {
                'Company Vehicle': ['vehicle', 'plate_number', 'driver'],
                'Private Vehicle': ['vehicle', 'plate_number', 'owner_driver'],
                'Ride-Hailing': ['provider'],
                Taxi: ['other_details'],
                'Public Transportation': ['transport_type'],
                'Rental Vehicle': ['rental_provider', 'vehicle', 'plate_number'],
                Air: ['airline', 'flight_number'],
                'Sea/Ferry': ['shipping_line', 'vessel_trip'],
                Other: ['other_details'],
            }[transportation.mode] || [];
            const labels = {vehicle:'Vehicle',plate_number:'Plate',driver:'Driver',owner_driver:'Owner / Driver',provider:'Provider',transport_type:'Type',rental_provider:'Rental Provider',airline:'Airline',flight_number:'Flight',shipping_line:'Shipping Line',vessel_trip:'Vessel / Trip',other_details:'Details'};
            return fields.map(field => transportation[field] ? `${labels[field]}: ${transportation[field]}` : '').filter(Boolean).join(' · ') || (transportation.mode === 'Walking' ? '—' : '-');
        },
        ensureDetailChildren(row) {
            if (!row.client_key && !row.id) row.client_key = this.makeClientKey();
            row.travel = {...(row.travel || {})};
            row.transportation = {...(row.transportation || {})};
            return row;
        },
        clearTransportationForMode(index, mode) {
            const row = this.ensureDetailChildren(this.form.detail_rows[index]);
            const fields = ['vehicle','plate_number','driver','owner_driver','provider','transport_type','rental_provider','airline','flight_number','shipping_line','vessel_trip','other_details'];
            fields.forEach(field => row.transportation[field] = '');
            row.transportation.mode = mode;
        },
        moveDetail(index, offset) {
            const target = index + offset;
            if (target < 0 || target >= this.form.detail_rows.length) return;
            const [row] = this.form.detail_rows.splice(index, 1);
            this.form.detail_rows.splice(target, 0, row);
            this.dirty = true;
        },
        openDetailModal(index = null) {
            this.detailModalIndex = index;
            this.detailDraft = index === null ? {} : {...this.form.detail_rows[index]};
            this.engagements = [];
            this.detailModalOpen = true;
            if (this.form.classification === 'client' && this.detailDraft.company_id && this.detailDraft.engagement_type) this.refreshEngagements(true);
        },
        async refreshEngagements(preserveSelection = false) {
            const selected = preserveSelection ? this.detailDraft.project_id : '';
            this.detailDraft.project_id = selected;
            this.engagements = [];
            this.detailDraft.travel_credit_balance = null;
            if (!this.detailDraft.company_id || !this.detailDraft.engagement_type) return;
            const params = new URLSearchParams({company_id: this.detailDraft.company_id, type: this.detailDraft.engagement_type});
            const response = await fetch(`{{ route('human-capital.obf.engagements') }}?${params}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            this.engagements = data.items || [];
            this.detailDraft.travel_credit_balance = data.travel_credit_balance;
            if (!this.engagements.some(item => String(item.id) === String(selected))) this.detailDraft.project_id = '';
        },
        async loadSuggestions(field) {
            const response = await fetch(`{{ route('human-capital.obf.suggestions') }}?field_type=${encodeURIComponent(field)}`, {headers: {'Accept': 'application/json'}});
            if (response.ok) this.suggestions[field] = (await response.json()).values || [];
        },
        saveDetail() {
            const row = this.ensureDetailChildren({...this.detailDraft});
            if (this.form.classification === 'client') row.engagement_name = this.engagements.find(item => String(item.id) === String(row.project_id))?.name || row.engagement_name || '';
            if (this.detailModalIndex === null) this.form.detail_rows.push(row);
            else this.form.detail_rows[this.detailModalIndex] = row;
            this.detailModalOpen = false;
            this.dirty = true;
        },
        removeDetail(index) {
            const row = this.form.detail_rows[index];
            const key = this.detailReference(row);
            if ((this.form.create_attachments || []).some(item => !item.remove && item.detail_key === key)) {
                window.alert('This O.B. Detail has linked attachments. Relink or remove those attachments before removing the detail.');
                return;
            }
            if (!window.confirm('Remove this O.B. Detail and its linked Travel and Transportation details?')) return;
            this.form.detail_rows.splice(index, 1);
            this.dirty = true;
        },
        makeClientKey() {
            return window.crypto?.randomUUID ? window.crypto.randomUUID() : `draft-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        },
        detailReference(row) {
            return row?.id ? `detail:${row.id}` : (row?.client_key || '');
        },
        attachmentTripLabel(row) {
            const route = `${row.from_location || '-'} → ${row.destination || '-'}`;
            if (this.form.classification === 'client') return `${this.clientName(row.company_id)}  ${route}`;
            if (this.form.classification === 'internal') return `Internal  ${route}`;
            return `${row.activity_event_name || 'Activity / Event'}  ${route}`;
        },
        addCreateAttachment() {
            this.form.create_attachments ||= [];
            this.form.create_attachments.push({id: null, key: this.makeClientKey(), type: '', detail_key: '', description: '', filename: '', url: '', remove: false});
            this.dirty = true;
        },
        setCreateAttachmentFile(index, event) {
            const file = event.target.files?.[0];
            if (file && this.form.create_attachments[index]) this.form.create_attachments[index].filename = file.name;
            this.dirty = true;
        },
        removeCreateAttachment(index) {
            const row = this.form.create_attachments[index];
            if (row?.id) row.remove = true;
            else this.form.create_attachments.splice(index, 1);
            this.dirty = true;
        },

        init() {
            const parameters = new URLSearchParams(window.location.search);

            if (parameters.get('create') === '1') {
                this.openAdd();
                return;
            }

            const tripId = Number(parameters.get('open'));
            const trip = this.trips.find(item => Number(item.id) === tripId);

            if (trip) {
                this.openView(trip);
                return;
            }

            const editTripId = Number(parameters.get('edit'));
            const editableTrip = this.trips.find(item => Number(item.id) === editTripId);

            if (editableTrip) {
                this.openEdit(editableTrip);
            }
        },

        get panelTitle() {
            if (this.isView) return 'View Official Business Travel Form';
            if (this.isEdit) return 'Edit Official Business Travel Form';
            return 'New Official Business Travel Form';
        },

        generatePreviewReference() {
            const now = new Date();

            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');

            const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let suffix = '';

            if (window.crypto && window.crypto.getRandomValues) {
                const values = new Uint32Array(5);
                window.crypto.getRandomValues(values);

                values.forEach(value => {
                    suffix += alphabet[value % alphabet.length];
                });
            } else {
                for (let index = 0; index < 5; index++) {
                    suffix += alphabet[
                        Math.floor(Math.random() * alphabet.length)
                    ];
                }
            }

            return `OBF-${year}${month}${day}-${suffix}`;
        },
        blankForm() {
            return {
                id: null,
                classification: '',
                classification_change_confirmed: false,
                detail_rows: [],
                team_rows: [],
                ob_reference_no: '',
                employee_id: '',
                employee_name: '',
                employee_code: '',
                position: '',
                department: '',
                destination: '',
                additional_stops: '',
                purpose: '',
                purpose_options: [],
                purpose_other: '',
                trip_type: '',
                date_from: '',
                date_to: '',
                departure_time: '',
                return_time: '',
                nature_of_travel: '',
            team_members: ['', '', ''],
            team_member_employee_ids: ['', '', ''],
            legacy_team_members: false,

                immediate_superior: '',
                superior_email: '',

                is_client_travel: '0',
                client_mode: 'single',
                company_id: '', client_contact_id: '',
                company_ids: [],
                client_route_map: {},
                client_type: '',
                client_id_no: '',
                client_name: '',
                client_email: '',
                client_contract_number: '',
                contract_type: '',
                travel_billability: '',

                client_payment_status: '',
                client_payment_items: [],
                client_payment_other: '',
                amount_client_will_pay: 0,

                travel_credit_monthly_limit: '',
                travel_credit_used: '',
                travel_credit_needed: '',
                travel_credit_remaining: '',

                transportation_mode: '',
                company_vehicle: '',
                assigned_driver: '',
                vehicle_details: '',
                plate_number: '',
                odometer: '',
                fuel_level: '',
                private_vehicle_details: '',
                public_transport_details: '',
                air_travel_details: '',
                sea_travel_details: '',
                ride_hailing_provider: '',
                transportation_cost: '',
                public_transport_mode: '',
                carrier: '',
                transport_route: '',
                booking_reference: '',
                other_transportation: '',
                estimated_expenses: 0,

                attachment_paths: [],
                attachment_types: [],
                attachment_other: '',
                create_attachments: [],

                remarks: '',
                status: 'Pending',
            };
        },

        blankPreview() {
            return {
                full_name: '',
                employee_code: '',
                email: '',
                phone_number: '',
                position: '',
                department: '',
            };
        },

        openAdd() {
            this.isView = false;
            this.isEdit = false;
            this.form = this.blankForm();
            this.form.ob_reference_no = this.generatePreviewReference();
            this.preview = this.blankPreview();
            if (this.currentEmployeeId) {
                const currentEmployee = this.employees.find(
                    employee => String(employee.id) === String(this.currentEmployeeId)
                );

                if (currentEmployee) {
                    this.form.employee_id = currentEmployee.id;
                    this.selectEmployee();
                }
            }
            this.showPanel = true;
            this.dirty = false;
        },

        openView(trip) {
            this.isView = true;
            this.isEdit = false;
            this.form = this.hydrateTrip(trip);
            this.hydrateClientRouteMap(trip);
            this.setPreviewFromTrip(trip);
            this.showPanel = true;
            this.dirty = false;
        },

        openEdit(trip) {
            if (!trip.id || !trip.can_edit) return;

            this.isView = false;
            this.isEdit = true;
            this.form = this.hydrateTrip(trip);
            this.hydrateClientRouteMap(trip);

            this.setPreviewFromTrip(trip);
            this.showPanel = true;
            this.dirty = false;
        },

        closePanel() {
            if (!this.isView && this.dirty && !window.confirm('Discard unsaved changes? Nothing will be saved.')) return;
            this.showPanel = false;
            this.isView = false;
            this.isEdit = false;
            this.form = {};
            this.preview = {};
            this.dirty = false;
        },

        hydrateTrip(trip) {
            const credits = trip.travel_credit_details || {};
            const transpo = trip.transportation_details || {};

            return {
                ...this.blankForm(),
                ...trip,
                detail_rows: Array.isArray(trip.detail_rows) ? trip.detail_rows.map(row => this.ensureDetailChildren({...row})) : [],
                team_rows: Array.isArray(trip.personnel_rows) ? trip.personnel_rows
                    .filter(row => String(row.employee_id) !== String(trip.employee_id))
                    .map(row => ({...this.personnelEmployees.find(employee => String(employee.id) === String(row.employee_id)), ...row, full_name: row.employee_name || ''})) : [],
                purpose_options: trip.purpose_options || [],
                team_members: trip.team_members && trip.team_members.length ? [...trip.team_members, '', '', ''].slice(0, 3) : ['', '', ''],
                team_member_employee_ids: Array.isArray(trip.team_member_employee_ids)
                    ? [...trip.team_member_employee_ids, '', '', ''].slice(0, 3)
                    : ['', '', ''],
                legacy_team_members: Boolean(
                    trip.team_members?.length && !trip.team_member_employee_ids?.length
                ),
                is_client_travel: trip.is_client_travel ? '1' : '0',
                client_mode: (
                    Array.isArray(trip.trip_tickets)
                    && trip.trip_tickets.length > 1
                ) ? 'multiple' : 'single',
                company_id: trip.company_id || '', client_contact_id: trip.contact_id || '',
                company_ids: Array.isArray(trip.trip_tickets)
                    ? trip.trip_tickets
                        .map(ticket => String(ticket.company_id || ''))
                        .filter(companyId =>
                            companyId
                            && String(companyId) !== String(trip.company_id || '')
                        )
                    : [],
                client_payment_items: trip.client_payment_items || [],
                attachment_paths: trip.attachment_paths || [],
                attachment_types: trip.attachment_types || [],
                create_attachments: Array.isArray(trip.create_attachments) ? trip.create_attachments.map(item => ({...item, detail_key: item.detail_id ? `detail:${item.detail_id}` : '', key: `attachment-${item.id}`, remove: false})) : [],
                travel_credit_monthly_limit: credits.monthly_limit || '',
                travel_credit_used: credits.used_this_month || '',
                travel_credit_needed: credits.needed_for_trip || '',
                travel_credit_remaining: credits.remaining_after_trip || '',
                company_vehicle: transpo.company_vehicle || '',
                assigned_driver: transpo.assigned_driver || '',
                vehicle_details: transpo.vehicle_details || '',
                plate_number: transpo.plate_number || '',
                odometer: transpo.odometer || '',
                fuel_level: transpo.fuel_level || '',
                private_vehicle_details: transpo.private_vehicle_details || '',
                public_transport_details: transpo.public_transport_details || '',
                air_travel_details: transpo.air_travel_details || '',
                sea_travel_details: transpo.sea_travel_details || '',
                ride_hailing_provider: transpo.ride_hailing_provider || '',
                transportation_cost: transpo.transportation_cost || '',
                public_transport_mode: transpo.public_transport_mode || '',
                carrier: transpo.carrier || '',
                transport_route: transpo.route || '',
                booking_reference: transpo.booking_reference || '',
                other_transportation: transpo.other_description || '',
            };
        },

        selectEmployee() {
            const employee = this.employees.find(item => String(item.id) === String(this.form.employee_id));

            if (!employee) {
                this.preview = this.blankPreview();
                this.form.immediate_superior = '';
                this.form.superior_email = '';
                return;
            }

            this.preview = {
                full_name: employee.full_name || '',
                employee_code: employee.employee_code || '',
                email: employee.email || '',
                phone_number: employee.phone_number || '',
                position: employee.position || '',
                department: employee.department || '',
            };
            this.form.immediate_superior = employee.immediate_superior || '';
            this.form.superior_email = employee.superior_email || '';
            this.syncTeamNames();
        },

        syncTeamNames() {
            if (this.form.legacy_team_members) return;
            this.form.team_members = (this.form.team_member_employee_ids || [])
                .filter(id => id && String(id) !== String(this.form.employee_id))
                .map(id => this.personnelEmployees.find(employee => String(employee.id) === String(id))?.full_name || '')
                .filter(Boolean);
        },

        setPreviewFromTrip(trip) {
            const employee = this.employees.find(item => String(item.id) === String(trip.employee_id));

            this.preview = {
                full_name: trip.employee_name || employee?.full_name || '',
                employee_code: trip.employee_code || employee?.employee_code || '',
                email: employee?.email || '',
                phone_number: employee?.phone_number || '',
                position: trip.position || employee?.position || '',
                department: trip.department || employee?.department || '',
            };
        },


        selectedClientContact() {
            return this.contacts.find(item => String(item.id) === String(this.form.client_contact_id));
        },

        get selectedClientPhone() {
            return this.selectedClientContact()?.phone || '';
        },

        get selectedClientCompany() {
            return this.selectedClientContact()?.company_name || '';
        },

        handleClientTravelChange() {
            if (this.form.is_client_travel == '1') {
                return;
            }

            this.form.client_mode = 'single';
            this.form.company_id = ''; this.form.client_contact_id = '';
            this.form.company_ids = [];
            this.form.client_route_map = {};
            this.form.client_type = '';
            this.form.client_id_no = '';
            this.form.client_name = '';
            this.form.client_email = '';
            this.form.client_contract_number = '';
            this.form.contract_type = '';
            this.form.travel_billability = '';
        },

        handleClientModeChange() {
            if (this.form.client_mode === 'multiple') {
                if (!Array.isArray(this.form.company_ids)) {
                    this.form.company_ids = [];
                }

                if (
                    !this.form.client_route_map
                    || typeof this.form.client_route_map !== 'object'
                ) {
                    this.form.client_route_map = {};
                }

                return;
            }

            this.form.company_ids = [];
            this.form.client_route_map = {};
        },

        addClientCompany() {
            if (!Array.isArray(this.form.company_ids)) {
                this.form.company_ids = [];
            }

            this.form.company_ids.push('');
        },

        removeClientCompany(index) {
            this.form.company_ids.splice(index, 1);
        },

        multipleClientIds() {
            const ids = [
                this.form.company_id,
                ...(Array.isArray(this.form.company_ids)
                    ? this.form.company_ids
                    : []),
            ]
                .map(id => String(id || ''))
                .filter(Boolean);

            return ids.filter(
                (id, index, values) => values.indexOf(id) === index
            );
        },

        companyName(clientId) {
            const option = document.querySelector(
                `select[name="company_id"] option[value="${clientId}"]`
            );

            if (option) {
                return option.textContent
                    .replace(/\s+\(#\d+\)\s*$/, '')
                    .trim();
            }

            return `Client ${clientId}`;
        },

        ensureClientRoute(clientId) {
            if (
                !this.form.client_route_map
                || typeof this.form.client_route_map !== 'object'
            ) {
                this.form.client_route_map = {};
            }

            const key = String(clientId || '');

            if (!key) {
                return {
                    destination: '',
                    activity: '',
                };
            }

            if (!this.form.client_route_map[key]) {
                this.form.client_route_map[key] = {
                    destination: '',
                    activity: '',
                };
            }

            return this.form.client_route_map[key];
        },

        hydrateClientRouteMap(trip) {
            this.form.client_route_map = {};

            if (!Array.isArray(trip.trip_tickets)) {
                return;
            }

            trip.trip_tickets.forEach(ticket => {
                const companyId = String(ticket.company_id || '');

                if (!companyId) {
                    return;
                }

                const stops = Array.isArray(ticket.stops)
                    ? ticket.stops
                    : [];

                const primaryStop =
                    stops.find(stop => Number(stop.sequence) === 1)
                    || stops[0]
                    || null;

                this.form.client_route_map[companyId] = {
                    destination:
                        primaryStop?.destination
                        || (
                            String(trip.company_id || '') === companyId
                                ? (trip.destination || '')
                                : ''
                        ),
                    activity:
                        primaryStop?.activity
                        || (
                            String(trip.company_id || '') === companyId
                                ? (trip.purpose || '')
                                : ''
                        ),
                };
            });
        },
        selectClientContact() {
            if (!this.form.client_contact_id) {
                this.form.company_id = '';
                this.form.client_type = '';
                this.form.client_id_no = '';
                this.form.client_name = '';
                this.form.client_email = '';
                this.form.client_contact_number = '';
                this.form.company_business_name = '';
                this.form.contract_type = '';
                this.form.travel_billability = '';
                this.selectedClientPhone = '';
    this.form.client_contract_number = '';
                return;
            }

            const contact = this.selectedClientContact();

            if (!contact) {
                return;
            }

            this.form.is_client_travel = '1';
            this.form.client_type = contact.client_type || this.form.client_type || 'Local';
            this.form.client_id_no = contact.cif_no || contact.tin || contact.id || '';
            this.form.client_name = contact.full_name || '';
            this.form.client_email = contact.email || '';
            this.form.client_contract_number = contact.cif_no || this.form.client_contract_number || '';
            this.form.contract_type = contact.contract_type || this.form.contract_type || '';
        },

        printHasValue(value) {
            if (value === null || value === undefined) {
                return false;
            }

            if (Array.isArray(value)) {
                return value.some(item => this.printHasValue(item));
            }

            if (typeof value === 'number') {
                return Number.isFinite(value) && value !== 0;
            }

            const normalized = String(value).trim();

            if (!normalized) {
                return false;
            }

            return ![
                '-',
                'none',
                'null',
                'undefined',
                '0',
                '0.00',
                'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±0',
                'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±0.00'
            ].includes(normalized.toLowerCase());
        },

        printHasAny(...values) {
            return values.some(value => this.printHasValue(value));
        },

        printHasClient() {
            return String(this.form.is_client_travel || '') === '1';
        },

        printHasClientPayment() {
            if (!this.printHasClient()) {
                return false;
            }

            return this.printHasAny(
                this.form.client_payment_status,
                this.form.client_payment_items,
                this.form.client_payment_other,
                this.form.amount_client_will_pay
            );
        },

        printHasTravelCredits() {
            return this.printHasAny(
                this.form.travel_credit_monthly_limit,
                this.form.travel_credit_used,
                this.form.travel_credit_needed,
                this.form.travel_credit_remaining
            );
        },

        printHasTransportation() {
            return this.printHasAny(
                this.form.transportation_mode,
                this.form.estimated_expenses,
                this.form.company_vehicle,
                this.form.assigned_driver,
                this.form.vehicle_details,
                this.form.plate_number,
                this.form.odometer,
                this.form.fuel_level,
                this.form.private_vehicle_details,
                this.form.public_transport_details,
                this.form.air_travel_details,
                this.form.sea_travel_details,
                this.form.ride_hailing_provider,
                this.form.transportation_cost,
                this.form.public_transport_mode,
                this.form.carrier,
                this.form.transport_route,
                this.form.booking_reference,
                this.form.other_transportation
            );
        },

        printHasAttachmentsOrRemarks() {
            return this.printHasAny(
                this.form.attachment_types,
                this.form.attachment_other,
                this.form.attachment_paths,
                this.form.remarks
            );
        },
        purposeText() {
            const values = this.form.purpose_options || [];
            return values.length ? values.join(', ') : '-';
        },

        teamText() {
            const values = (this.form.team_members || []).filter(Boolean);
            return values.length ? values.join(', ') : '-';
        },

        clientPaymentText() {
            const values = this.form.client_payment_items || [];
            return values.length ? values.join(', ') : '-';
        },

        attachmentText() {
            const values = this.form.attachment_types || [];
            return values.length ? values.join(', ') : '-';
        },

        dateTimeText(date, time) {
            if (!date && !time) return '-';
            return `${date || ''} ${time || ''}`.trim();
        },

        statusClass(status) {
            if (['Approved', 'Ready for Travel'].includes(status)) return 'bg-blue-100 text-blue-700';
            if (['In Progress', 'Ongoing'].includes(status)) return 'bg-violet-100 text-violet-700';
            if (['Returned', 'For Completion'].includes(status)) return 'bg-amber-100 text-amber-700';
            if (status === 'Completed') return 'bg-green-100 text-green-700';
            if (status === 'Submitted') return 'bg-cyan-100 text-cyan-700';
            if (['Draft', 'Reverted'].includes(status)) return 'bg-orange-100 text-orange-700';
            if (status === 'Rejected') return 'bg-red-100 text-red-700';
            if (status === 'Cancelled') return 'bg-gray-100 text-gray-700';
            return 'bg-yellow-100 text-yellow-700';
        },
    }
}
</script>
@endsection

{{-- OBF-PRINT-COMPACT-FIX --}}
<style>
@media print {

    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    /*
     * Keep the printable OBF document compact.
     * Do not preserve screen modal/container heights during printing.
     */
    .preview-document,
    .preview-paper,
    .obf-preview,
    .print-area,
    [data-print-area] {
        width: 100% !important;
        max-width: none !important;
        min-height: 0 !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        overflow: visible !important;
    }

    /*
     * Main document content.
     */
    .preview-document *,
    .preview-paper *,
    .obf-preview *,
    .print-area * {
        box-sizing: border-box !important;
    }

    /*
     * Section headings.
     */
    .section-title {
        margin-top: 10px !important;
        margin-bottom: 5px !important;
        padding: 6px 10px !important;
        min-height: 0 !important;
        line-height: 1.15 !important;
        font-size: 10px !important;

        break-after: avoid !important;
        page-break-after: avoid !important;
    }

    /*
     * First section should not have unnecessary top spacing.
     */
    .section-title:first-of-type {
        margin-top: 5px !important;
    }

    /*
     * Preview tables.
     */
    .preview-table {
        width: 100% !important;
        margin: 0 0 5px 0 !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        table-layout: fixed !important;

        break-inside: auto !important;
        page-break-inside: auto !important;
    }

    .preview-table tr {
        min-height: 0 !important;
        height: auto !important;

        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .preview-table td,
    .preview-table th {
        padding: 4px 7px !important;
        min-height: 0 !important;
        height: auto !important;
        line-height: 1.2 !important;
        vertical-align: top !important;
        font-size: 9px !important;
    }

    .preview-table td b,
    .preview-table th b {
        display: block !important;
        margin-bottom: 2px !important;
        line-height: 1.1 !important;
        font-size: 8.5px !important;
    }

    .preview-table td span {
        line-height: 1.15 !important;
    }

    /*
     * Remove oversized spacing inherited from screen Tailwind classes.
     */
    .mt-1 {
        margin-top: 2px !important;
    }

    .mt-2 {
        margin-top: 3px !important;
    }

    .mt-3,
    .mt-4 {
        margin-top: 5px !important;
    }

    .mb-3,
    .mb-4 {
        margin-bottom: 5px !important;
    }

    .gap-4 {
        gap: 6px !important;
    }

    .gap-3 {
        gap: 5px !important;
    }

    .gap-2 {
        gap: 4px !important;
    }

    /*
     * Preview value boxes.
     */
    .preview-table .rounded-md,
    .preview-table .rounded-lg,
    .preview-table .rounded-xl {
        min-height: 0 !important;
        height: auto !important;
        padding: 4px 7px !important;
        margin: 0 !important;
        border-radius: 4px !important;
    }

    /*
     * Prevent form-input styling from becoming tall in printed preview.
     */
    .preview-table input,
    .preview-table select,
    .preview-table textarea {
        min-height: 0 !important;
        height: auto !important;
        padding: 3px 6px !important;
        margin: 0 !important;
        line-height: 1.15 !important;
        font-size: 9px !important;
    }

    /*
     * Long text should expand naturally instead of reserving empty height.
     */
    .preview-table textarea,
    .preview-table .whitespace-pre-wrap {
        min-height: 0 !important;
        height: auto !important;
    }

    /*
     * Don't force whole sections onto a new page.
     * Only individual rows are protected from splitting.
     */
    .section-title + .preview-table {
        break-before: avoid !important;
        page-break-before: avoid !important;
    }

    /*
     * Remove screen-only modal spacing.
     */
    .overflow-y-auto,
    .overflow-auto {
        overflow: visible !important;
        max-height: none !important;
        height: auto !important;
    }

    /*
     * Hide controls in print.
     */
    button,
    .no-print {
        display: none !important;
    }
}
</style>

{{-- OBF-PUBLIC-TRANSPORT-PRINT-GRID --}}
<style>
@media print {
    [x-cloak] {
        display: none;
    }

    .obf-public-print-grid {
        display: grid !important;
        grid-template-columns: minmax(0,1fr) minmax(0,1fr) !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

{{-- OBF-COMPANY-VEHICLE-5050 --}}
<style>
    /*
     * Transportation preview always spans the full document width.
     */
    .obf-transport-preview-table {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
    }

    /*
     * Company Vehicle uses the exact same two-column structure
     * as the fill-up form.
     */
    .obf-transport-preview-table .obf-company-vehicle-preview tr {
        width: 100% !important;
    }

    .obf-transport-preview-table .obf-company-vehicle-preview td {
        width: 50% !important;
        min-width: 0 !important;
        max-width: 50% !important;

        box-sizing: border-box !important;
        vertical-align: top !important;

        overflow: hidden !important;
    }

    /*
     * Long content cannot resize either 50% column.
     */
    .obf-transport-preview-table .obf-company-vehicle-preview td > span {
        display: block !important;

        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;

        box-sizing: border-box !important;

        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    @media print {
        .obf-transport-preview-table {
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
            table-layout: fixed !important;
        }

        .obf-transport-preview-table .obf-company-vehicle-preview td {
            width: 50% !important;
            min-width: 0 !important;
            max-width: 50% !important;
        }
    }
</style>

{{-- OBF-COMPANY-PREVIEW-FIXED-BOX --}}
<style>
    .obf-company-preview-field {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .obf-company-preview-field > b {
        display: block !important;
        color: #001b5f !important;
        font-size: 11px !important;
        margin-bottom: 0.3rem !important;
    }

    .obf-company-preview-value {
        display: block !important;

        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;

        height: 36px !important;

        padding: 8px 10px !important;

        background: #f8fafc !important;
        border: 1px solid #1d4ed8 !important;
        border-radius: 5px !important;

        color: #111827 !important;

        box-sizing: border-box !important;

        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    @media print {
        .obf-company-preview-field {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
        }

        .obf-company-preview-value {
            width: 100% !important;
            max-width: 100% !important;
        }
    }
</style>

{{-- OBF-TRANSPORT-MIRROR-V1 --}}
<style>

    /*
     * Main C section:
     * Transportation Mode | Second field
     */
    .obf-transport-mirror {
        display: grid !important;
        grid-template-columns: minmax(0,1fr) minmax(0,1fr) !important;

        width: 100% !important;
        max-width: 100% !important;

        gap: 10px 12px !important;

        box-sizing: border-box !important;
    }

    /*
     * Mode-specific contents span the complete grid,
     * but contain another equal 50/50 grid.
     */
    .obf-transport-contents {
        grid-column: 1 / -1 !important;

        display: grid !important;
        grid-template-columns: minmax(0,1fr) minmax(0,1fr) !important;

        width: 100% !important;
        max-width: 100% !important;

        gap: 10px 12px !important;

        box-sizing: border-box !important;
    }

    .obf-transport-full {
        grid-column: 1 / -1 !important;

        width: 100% !important;
        max-width: 100% !important;
    }

    .obf-transport-field {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;

        box-sizing: border-box !important;
    }

    .obf-transport-field b {
        display: block !important;

        color: #001b5f !important;
        font-size: 11px !important;

        margin-bottom: 0.3rem !important;
    }

    /*
     * Match the standard preview boxes.
     */
    .obf-transport-value {
        display: block !important;

        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;

        height: 36px !important;

        padding: 8px 10px !important;

        background: #f8fafc !important;

        border: 1px solid #1d4ed8 !important;
        border-radius: 5px !important;

        color: #111827 !important;

        box-sizing: border-box !important;

        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    @media print {

        .obf-transport-mirror,
        .obf-transport-contents {
            display: grid !important;

            grid-template-columns:
                minmax(0,1fr)
                minmax(0,1fr) !important;

            width: 100% !important;
            max-width: 100% !important;

            gap: 6px 8px !important;
        }

        .obf-transport-value {
            width: 100% !important;
            max-width: 100% !important;
        }
    }

</style>

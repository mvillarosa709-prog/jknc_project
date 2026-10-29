@extends('layouts.app')

@section('title', $transmittal->tracking_number . ' Workspace')

@push('styles')
    @vite(['resources/css/transmittal.css'])
@endpush

@push('scripts')
    @vite(['resources/js/transmittal.js'])
@endpush
@section('content')

<div class="transmittal-workspace-page" style="width: 100%; max-width: 100%; padding: 18px 24px 40px; box-sizing: border-box; font-family: Arial, sans-serif;">

    <div class="workspace-page-heading">

        <div>


            <h1 style="font-size: 30px; font-weight: 600; color: #1f2937; margin: 0 0 6px 0; letter-spacing: -0.025em; line-height: 1; font-family: inherit;">Transmittal Preview</h1>

            <p style="font-size: 13px; font-weight: 500; color: #64748b; margin: 0;">{{ $transmittal->tracking_number }}</p>

        </div>



        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <a
                href="{{ route('transmittal.index') }}"
                style="display: inline-flex; align-items: center; justify-content: center; height: 32px; padding: 0 16px; border: 1.5px solid #cbd5e1; border-radius: 8px; background: #ffffff; color: #1e3a8a; font-size: 13px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); transition: all 0.15s ease;"
                onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#94a3b8';"
                onmouseout="this.style.background='#ffffff'; this.style.borderColor='#cbd5e1';"
            >
                Back
            </a>
        </div>

    </div> 
    <section class="workspace-panel">
        <nav class="tabs" aria-label="Transmittal workspace sections">
            <button class="tab active" type="button" data-tab="draft">
                Draft of Transmittal
            </button>

            <button class="tab" type="button" data-tab="transmittal">
                Transmittal
            </button>

            <button class="tab" type="button" data-tab="receipt">
                Receipt
                <span class="tab-count">{{ $transmittal->receipts->count() }}</span>
            </button>

            <button class="tab" type="button" data-tab="proof">
                Proof of Transmission
                <span class="tab-count">{{ $transmittal->proofs->count() }}</span>
            </button>

            <button class="tab" type="button" data-tab="attachments">
                Attachments
                <span class="tab-count">{{ $transmittal->documents->count() }}</span>
            </button>

            <button class="tab" type="button" data-tab="history">
                History
            </button>
        </nav>

        <div class="workspace-tab-layout">
            <div class="workspace-tab-panels">

 {{-- DRAFT OF TRANSMITTAL --}}
<div class="tab-content active" id="tab-draft">
    <div class="draft-workspace-layout">

        {{-- Kaliwa: Document Preview --}}
        <section
            class="document-preview-pane draft-preview-pane"
            aria-label="Draft document preview"
        >
            <div class="preview-pane-header">
                <span>DOCUMENT PREVIEW</span>
            </div>

            <div class="add-preview-pages workspace-document-pages">
                @include('transmittals._document-form', [
                    'transmittal' => $transmittal,
                    'preview' => true
                ])
            </div>
        </section>

        {{-- Kanan: Editable Draft Form --}}
        <div class="draft-information-cards">
            @php
                $latestReceipt = $transmittal->receipts
                    ->sortByDesc('received_at')
                    ->first();

                $draftRecipient = $transmittal->recipients->first();
                $employees = $employees ?? collect();
                $contacts = $contacts ?? collect();
                $companies = $companies ?? collect();

                $formItems = old('items', $transmittal->items->map(fn ($item) => [
                    'id' => $item->id,
                    'particular' => $item->particular,
                    'unique_id' => $item->unique_id,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                    'remarks' => $item->remarks,
                ])->all() ?: [['particular' => '', 'unique_id' => '', 'quantity' => '', 'description' => '', 'remarks' => '']]);
            @endphp

            {{-- Distinct Block: Transmittal Form Details --}}
            <article class="detail-card meta-card" style="padding: 20px;">

                <h2 style="margin-bottom: 16px; font-size: 18px; font-weight: 600;">
                    Edit Transmittal Details
                </h2>

                @if ($errors->any())
                    <div style="background-color: #fef2f2; border: 1px solid #f87171; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
                        <strong style="display: block; margin-bottom: 4px; font-weight: 700;">Please check the following before submitting:</strong>
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

<form
    action="{{ route('transmittals.update', $transmittal) }}"
    method="POST"
    enctype="multipart/form-data"
    id="transmittal-form"
>
                
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="confidentiality" id="draft_confidentiality" value="{{ old('confidentiality', $transmittal->confidentiality ?? 'standard') }}">
                    <input type="hidden" name="purpose" id="draft_purpose" value="{{ old('purpose', $transmittal->purpose ?? 'Official Transmittal') }}">
                    <input type="hidden" name="subject" value="{{ old('subject', $transmittal->subject ?: 'Transmittal Form') }}" id="draft_subject">
                    <input type="hidden" name="related_company" value="{{ old('related_company', $transmittal->related_company ?? '') }}">
                    <input type="hidden" name="external_reference" value="{{ old('external_reference', $transmittal->external_reference ?? '') }}">

                    <div style="display: flex; flex-direction: column; gap: 14px;">

                        {{-- Reference Number --}}
                        <div>
                            <label
                                for="draft_tracking_number"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Ref #
                            </label>

                            <input
                                id="draft_tracking_number"
                                type="text"
                                value="{{ $transmittal->tracking_number }}"
                                disabled
                                style="width: 100%; padding: 8px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; color: #6b7280;"
                            >
                        </div>

                        {{-- Transmittal Date --}}
                        <div>
                            <label
                                for="draft_transmittal_date"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Date *
                            </label>

                            <input
                                id="draft_transmittal_date"
                                type="date"
                                name="transmittal_date"
                                value="{{ old('transmittal_date', optional($transmittal->transmittal_date)->format('Y-m-d')) }}"
                                required
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >

                            @error('transmittal_date')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Direction / Mode --}}
                        <div>
                            <label
                                for="draft_direction"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Mode *
                            </label>

                            <select
                                id="draft_direction"
                                name="direction"
                                required
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >
                                <option
                                    value="send"
                                    {{ old('direction', $transmittal->direction ?? 'send') === 'send' ? 'selected' : '' }}
                                >
                                    SEND
                                </option>

                                <option
                                    value="receive"
                                    {{ old('direction', $transmittal->direction ?? 'send') === 'receive' ? 'selected' : '' }}
                                >
                                    RECEIVE
                                </option>
                            </select>

                            @error('direction')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Recipient / Counterparty Name --}}
                        <div>
                            <label
                                for="draft_recipient_name"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                {{ ($transmittal->direction ?? 'send') === 'receive' ? 'From' : 'To' }}
                            </label>

                            <input
                                id="draft_recipient_name"
                                type="text"
                                name="recipients[0][name]"
                                value="{{ old('recipients.0.name', $draftRecipient?->name) }}"
                                maxlength="120"
                                placeholder="{{ ($transmittal->direction ?? 'send') === 'receive' ? 'Enter external sender / source' : 'Enter external receiver / destination' }}"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >

                            @error('recipients.0.name')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Origin Office --}}
                        <div>
                            <label
                                for="draft_origin_office"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                {{ ($transmittal->direction ?? 'send') === 'receive' ? 'To (Office)' : 'From (Office)' }} *
                            </label>

                            <input
                                id="draft_origin_office"
                                type="text"
                                name="origin_office"
                                value="{{ old('origin_office', $transmittal->origin_office ?: config('ordo.office_name')) }}"
                                maxlength="160"
                                required
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >

                            @error('origin_office')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Recipient Address --}}
                        <div>
                            <label
                                for="draft_recipient_address"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Address
                            </label>

                            <textarea
                                id="draft_recipient_address"
                                name="recipient_address"
                                rows="2"
                                maxlength="2000"
                                placeholder="Recipient mailing address"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >{{ old('recipient_address', $transmittal->recipient_address) }}</textarea>

                            @error('recipient_address')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Delivery Type --}}
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                                Delivery Type *
                            </label>
                            @php
                                $selectedDeliveryType = old('delivery_type', $transmittal->delivery_type ?? 'by_person');
                                $selectedProvider = old('registered_mail_provider', $transmittal->registered_mail_provider ?? '');
                                $isOtherProvider = !in_array($selectedProvider, ['', 'LBC', 'J&T', 'Postal'], true);
                            @endphp
                            <select id="workspace_delivery_type" name="delivery_type" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background-color: #fff;">
                                <option value="by_person" {{ $selectedDeliveryType === 'by_person' ? 'selected' : '' }}>By Person</option>
                                <option value="registered_mail" {{ $selectedDeliveryType === 'registered_mail' ? 'selected' : '' }}>Registered Mail</option>
                                <option value="electronic" {{ $selectedDeliveryType === 'electronic' ? 'selected' : '' }}>Electronic</option>
                            </select>
                        </div>

                        {{-- Who? (for By Person) --}}
                        <div id="workspace_by_person_container" style="{{ $selectedDeliveryType === 'by_person' ? 'display: block;' : 'display: none;' }}">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                                Who?
                            </label>
                            <input
                                id="workspace_by_person_who"
                                type="text"
                                name="by_person_who"
                                value="{{ old('by_person_who', $transmittal->by_person_who ?? '') }}"
                                placeholder="Enter courier / person name"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                            >
                        </div>

                        {{-- Courier / Provider (for Registered Mail) --}}
                        <div id="workspace_provider_container" style="{{ $selectedDeliveryType === 'registered_mail' ? 'display: block;' : 'display: none;' }}">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                                Courier / Provider
                            </label>
                            <select id="workspace_provider_select" name="registered_mail_provider_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background-color: #fff;">
                                <option value="">Select provider</option>
                                <option value="LBC" {{ $selectedProvider === 'LBC' ? 'selected' : '' }}>LBC</option>
                                <option value="J&T" {{ $selectedProvider === 'J&T' ? 'selected' : '' }}>J&T</option>
                                <option value="Postal" {{ $selectedProvider === 'Postal' ? 'selected' : '' }}>Postal</option>
                                <option value="Others" {{ ($selectedProvider === 'Others' || $isOtherProvider) ? 'selected' : '' }}>Others</option>
                            </select>
                        </div>

                        {{-- Others provider specify field --}}
                        <div id="workspace_provider_other_container" style="{{ ($selectedDeliveryType === 'registered_mail' && ($selectedProvider === 'Others' || $isOtherProvider)) ? 'display: block;' : 'display: none;' }}">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                                Specify Provider / Details
                            </label>
                            <input
                                id="workspace_provider_other_input"
                                type="text"
                                name="registered_mail_provider_other"
                                value="{{ old('registered_mail_provider_other', $isOtherProvider ? $selectedProvider : '') }}"
                                placeholder="Type custom provider name"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                            >
                        </div>
                        <input type="hidden" id="workspace_registered_mail_provider_final" name="registered_mail_provider" value="{{ $selectedProvider }}">

                        {{-- Electronic Method --}}
                        <div id="workspace_electronic_method_container" style="{{ $selectedDeliveryType === 'electronic' ? 'display: block;' : 'display: none;' }}">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
                                Electronic Method
                            </label>
                            <select id="workspace_electronic_method" name="electronic_method" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background-color: #fff;">
                                <option value="Email" selected>Email</option>
                            </select>
                        </div>

                        {{-- Recipient Email (visible when Electronic) --}}
                        <div id="workspace_recipient_email_container" style="{{ $selectedDeliveryType === 'electronic' ? 'display: block;' : 'display: none;' }}">
                            <label
                                for="draft_recipient_email"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Recipient Email
                            </label>

                            <input
                                id="draft_recipient_email"
                                type="email"
                                name="recipients[0][email]"
                                value="{{ old('recipients.0.email', $draftRecipient?->email) }}"
                                maxlength="255"
                                placeholder="name@company.com"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                            >

                            @error('recipients.0.email')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        <input
                            type="hidden"
                            name="recipients[0][organization]"
                            value="{{ old('recipients.0.organization', $draftRecipient?->organization) }}"
                        >

                        {{-- Delivery Actions --}}
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">Actions</label>
                            @php
                                $selectedActions = old('delivery_actions', $transmittal->delivery_actions ?? ['email']);
                            @endphp
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; font-size: 12px;">
                                @foreach (['delivery' => 'Delivery', 'pick_up' => 'Pick Up', 'drop_off' => 'Drop Off', 'email' => 'Email'] as $value => $label)
                                    <label><input type="checkbox" name="delivery_actions[]" value="{{ $value }}" {{ in_array($value, $selectedActions, true) ? 'checked' : '' }}> {{ $label }}</label>
                                @endforeach
                            </div>
                        </div>


{{-- List of Items --}}
<div style="border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <label style="font-size: 14px; font-weight: 600; color: #1f2937;">
            List of Items
        </label>

        <button
            type="button"
            id="add-item-btn"
            style="background-color: #2563eb; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;"
        >
            + Add Row
        </button>
    </div>

    <div id="item-list" style="display: flex; flex-direction: column; gap: 16px;">
        @foreach ($formItems as $index => $item)
            <fieldset
                class="item-row"
                style="border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; background: #ffffff; position: relative; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);"
            >
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="item-number" style="font-size: 13px; font-weight: 600; color: #1f2937;">
                        Item {{ $loop->iteration }}
                    </span>

                    <button
                        type="button"
                        class="remove-item-btn"
                        style="color: #000; background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: underline;"
                    >
                        Remove
                    </button>
                </div>

                <input
                    type="hidden"
                    name="items[{{ $index }}][id]"
                    value="{{ is_array($item) ? ($item['id'] ?? '') : ($item->id ?? '') }}"
                >

                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">
                        Particular
                    </label>

                    <input
                        name="items[{{ $index }}][particular]"
                        value="{{ is_array($item) ? ($item['particular'] ?? '') : ($item->particular ?? '') }}"
                        placeholder="Item name"
                        style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                    >
                </div>

                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <div style="flex: 1; min-width: 0;">
                        <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">
                            Unique ID
                        </label>

                        <input
                            name="items[{{ $index }}][unique_id]"
                            value="{{ is_array($item) ? ($item['unique_id'] ?? '') : ($item->unique_id ?? '') }}"
                            placeholder="ID / serial"
                            style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                        >
                    </div>

                    <div style="width: 110px; flex-shrink: 0;">
                        <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">
                            Qty
                        </label>

                        <input
                            type="number"
                            min="0"
                            step="any"
                            name="items[{{ $index }}][quantity]"
                            value="{{ is_array($item) ? ($item['quantity'] ?? '') : ($item->quantity ?? '') }}"
                            placeholder="Quantity"
                            style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                        >
                    </div>
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">
                        Description
                    </label>

                    <input
                        name="items[{{ $index }}][description]"
                        value="{{ is_array($item) ? ($item['description'] ?? '') : ($item->description ?? '') }}"
                        placeholder="Description"
                        style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                    >
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">
                        Remarks
                    </label>

                    <input
                        name="items[{{ $index }}][remarks]"
                        value="{{ is_array($item) ? ($item['remarks'] ?? '') : ($item->remarks ?? '') }}"
                        placeholder="Remarks"
                        style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                    >
                </div>

                {{-- BOXED ITEM FILE UPLOAD --}}
    <div style="margin-bottom: 4px;">
        <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px;">
            Upload File
        </label>
        <div style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; background: #ffffff;">
           @php
                $itemObj = is_array($item) ? (object)$item : $item;
                $matchedDoc = null;
                
                // 1. Hanapin gamit ang document_id kung meron man
                if (isset($itemObj->document_id) && $itemObj->document_id) {
                    $matchedDoc = $transmittal->documents->firstWhere('id', $itemObj->document_id);
                }
                
                // 2. Fallback: Kunin ang dokumento base sa index o kunin ang pinakauna kung galing Smart Upload
                if (!$matchedDoc && $transmittal->documents->count() > 0) {
                    $matchedDoc = $transmittal->documents->get($index) ?? $transmittal->documents->first();
                }
                
                $attachedFileName = $matchedDoc?->original_name ?? $itemObj?->attachment_name ?? null;
            @endphp

            @if ($attachedFileName)
                <div style="font-size: 12px; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <span>📎</span>
                    <strong>{{ $attachedFileName }}</strong>
                </div>
            @endif

            <div class="custom-file-upload-wrapper" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <label class="custom-file-btn" style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; background: #e5e7eb; border: 1px solid #dfe3e8; border-radius: 5px; color: #4b5563; font-size: 12px; font-weight: 500; cursor: pointer; user-select: none; transition: background 0.15s ease;">
                    Choose Files
                    <input
                        type="file"
                        name="items[{{ $index }}][attachments][]"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
                        data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}"
                        class="item-file-input"
                        style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;"
                    >
                </label>
                <span class="chosen-file-name" style="font-size: 12px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px;">
                    {{ $attachedFileName ?: 'No file chosen' }}
                </span>
            </div>
        </div>
        <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 5px;">
            {{ $attachedFileName ? 'Choose a new file to replace the current attachment.' : 'Select a file for this item.' }}
        </small>
    </div>
            </fieldset>
        @endforeach
    </div>
</div>

{{-- Supporting Attachments --}}
<div style="border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
    <label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px;">
        Supporting Attachments
    </label>

    <div style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; background: #ffffff; margin-bottom: 6px;">
        <div class="custom-file-upload-wrapper" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <label class="custom-file-btn" style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; background: #e5e7eb; border: 1px solid #dfe3e8; border-radius: 5px; color: #4b5563; font-size: 12px; font-weight: 500; cursor: pointer; user-select: none; transition: background 0.15s ease;">
                Choose Files
                <input
                    type="file"
                    name="supporting_attachments[]"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
                    data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}"
                    class="supporting-file-input"
                    style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;"
                >
            </label>
            <span class="chosen-file-name" style="font-size: 12px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px;">
                No file chosen
            </span>
        </div>
    </div>

    <small style="color: #6b7280; font-size: 11px; display: block;">
        You may upload multiple supporting files. These will be attached when using Send Email.
    </small>
</div>

{{-- GRAY CHOOSE FILE BUTTONS & ROBUST LAYOUT --}}
<style>
    .draft-workspace-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(460px, 520px);
        align-items: start;
        gap: 28px;
    }
    .draft-information-cards {
        min-width: 0;
        width: 100%;
        max-width: 520px;
    }
    .custom-file-upload-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
        width: 100%;
        min-width: 0;
    }
    .custom-file-btn {
        position: relative !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        cursor: pointer;
    }
    .custom-file-btn input[type="file"] {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: 100% !important;
        opacity: 0 !important;
        cursor: pointer !important;
        margin: 0 !important;
        padding: 0 !important;
        z-index: 2;
    }
    .chosen-file-name {
        flex: 1 1 0%;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .custom-file-btn:hover {
        background-color: #d1d5db !important;
        border-color: #cbd5e1 !important;
    }
    .custom-file-btn:focus-within {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }
    #item-list .item-row input[type="file"]::file-selector-button,
    input[name="supporting_attachments[]"]::file-selector-button {
        margin-right: 12px;
        padding: 7px 12px;
        border: 1px solid #dfe3e8;
        border-radius: 5px;
        background-color: #e5e7eb;
        color: #4b5563;
        font-family: inherit;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    #item-list .item-row input[type="file"]::file-selector-button:hover,
    input[name="supporting_attachments[]"]::file-selector-button:hover {
        background-color: #d1d5db;
        border-color: #cbd5e1;
    }

    #item-list .item-row input[type="file"]:focus-visible,
    input[name="supporting_attachments[]"]:focus-visible {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }

    @media (max-width: 480px) {
        #item-list .item-row input[type="file"],
        input[name="supporting_attachments[]"] {
            font-size: 11px;
        }

        #item-list .item-row input[type="file"]::file-selector-button,
        input[name="supporting_attachments[]"]::file-selector-button {
            margin-right: 6px;
            padding: 7px 9px;
        }
    }
</style>


                        {{-- Signatories --}}
                        <div style="border-top: 1px solid #e5e7eb; padding-top: 12px; display: flex; flex-direction: column; gap: 12px;">
                            <label style="font-size: 13px; font-weight: 600; color: #374151;">Signatories</label>

                            {{-- Prepared by --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Prepared by</label>
                                <input type="text" readonly value="{{ $transmittal->prepared_by_name ?: ($transmittal->preparer?->name ?? 'System Super Admin') }}" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; background: #f3f4f6; color: #4b5563;">
                            </div>

                            {{-- Prepared at --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Prepared at</label>
                                <input type="text" readonly value="{{ optional($transmittal->prepared_at ?? $transmittal->created_at)->format('m/d/Y, g:i:s A') }}" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; background: #f3f4f6; color: #4b5563;">
                            </div>

                            {{-- Approved by --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Approved by</label>
                                <select id="workspace_select_approved_by" class="form-select" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; margin-bottom: 6px; background-color: #fff;">
                                    <option value="">Select employee approver</option>
                                    @foreach ($employeeOptions as $emp)
                                        <option value="{{ $emp['label'] }}">{{ $emp['label'] }}{{ !empty($emp['position']) ? ' - ' . $emp['position'] : '' }}</option>
                                    @endforeach
                                </select>
                                <input id="workspace_approved_by_name" name="approved_by_name" value="{{ old('approved_by_name', $transmittal->approved_by_name ?? '') }}" placeholder="Or type approver manually" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px;">
                            </div>

                            {{-- Operations Manager --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Operations Manager</label>
                                <select id="workspace_select_operations_manager" class="form-select" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; margin-bottom: 6px; background-color: #fff;">
                                    <option value="">Select Operations Manager</option>
                                    @foreach ($employeeOptions as $emp)
                                        <option value="{{ $emp['label'] }}">{{ $emp['label'] }}{{ !empty($emp['position']) ? ' - ' . $emp['position'] : '' }}</option>
                                    @endforeach
                                </select>
                                <input id="workspace_operations_manager" name="operations_manager" value="{{ old('operations_manager', $transmittal->operations_manager ?? '') }}" placeholder="Or type Operations Manager manually" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px;">
                            </div>

                            {{-- Delivered by --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Delivered by</label>
                                <select id="workspace_select_delivered_by" class="form-select" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; margin-bottom: 6px; background-color: #fff;">
                                    <option value="">Select employee</option>
                                    @foreach ($employeeOptions as $emp)
                                        <option value="{{ $emp['label'] }}">{{ $emp['label'] }}{{ !empty($emp['position']) ? ' - ' . $emp['position'] : '' }}</option>
                                    @endforeach
                                </select>
                                <input id="workspace_delivered_by" name="delivered_by" value="{{ old('delivered_by', $transmittal->delivered_by ?? '') }}" placeholder="Or type delivered by manually" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px;">
                            </div>

                            {{-- Received by (Contact / Employee) --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Received by (Contact / Employee)</label>
                                @php
                                    $allReceivers = array_merge($contactOptions ?? [], $employeeOptions ?? []);
                                @endphp
                                <select id="workspace_select_received_by" class="form-select" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; margin-bottom: 6px; background-color: #fff;">
                                    <option value="">Select contact or employee</option>
                                    @foreach ($allReceivers as $recv)
                                        <option value="{{ $recv['label'] }}" data-affiliation="{{ $recv['affiliation'] ?? '' }}" data-email="{{ $recv['email'] ?? '' }}">{{ $recv['label'] }} ({{ $recv['type'] ?? 'contact' }})</option>
                                    @endforeach
                                </select>
                                <input id="workspace_received_by" name="received_by" value="{{ old('received_by', $transmittal->received_by ?? '') }}" placeholder="Or type receiver manually" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px;">
                            </div>

                            {{-- Processing for Company / Organization --}}
                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Processing for Company / Organization</label>
                                <select id="workspace_select_company" class="form-select" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; margin-bottom: 6px; background-color: #fff;">
                                    <option value="">Select company</option>
                                    @foreach ($companyOptions as $comp)
                                        <option value="{{ $comp['label'] }}">{{ $comp['label'] }}</option>
                                    @endforeach
                                </select>
                                <input id="workspace_processing_company" name="processing_company" value="{{ old('processing_company', $transmittal->processing_company ?? '') }}" placeholder="Or type company manually" style="width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px;">
                            </div>
                        </div>

                    </div>

                    {{-- Form Actions --}}
                    <div style="margin-top: 20px; display: flex; gap: 8px; flex-direction: column;">

                        <button
                            type="submit"
                            name="intent"
                            value="draft"
                            class="button secondary"
                            style="width: 100%; padding: 10px;"
                        >
                            Save Draft
                        </button>

                        <button
                            type="submit"
                            name="intent"
                            value="submit"
                            class="button primary"
                            style="width: 100%; padding: 10px;"
                        >
                            Submit for review
                        </button>

                        @error('intent')
                            <p class="field-error" style="color: #dc2626; font-size: 12px; margin-top: 6px; font-weight: 600;">{{ $message }}</p>
                        @enderror

                    </div>

                </form>
            </article>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemList = document.getElementById('item-list');
    const addItemBtn = document.getElementById('add-item-btn');

    // 1. Live Preview Sync for all form fields
    function updateFieldPreviews() {
        // Classification / Confidentiality
        const confSelect = document.getElementById('draft_confidentiality') || document.querySelector('[name="confidentiality"]');
        if (confSelect) {
            const val = confSelect.value || 'standard';
            const label = val.charAt(0).toUpperCase() + val.slice(1);
            document.querySelectorAll('[data-preview-output="confidentiality"]').forEach(el => {
                el.textContent = (el.style.textTransform === 'uppercase' || el.classList.contains('uppercase')) ? label.toUpperCase() : label;
            });
        }

        // Notes / Purpose
        const purposeInput = document.getElementById('draft_purpose') || document.querySelector('[name="purpose"]');
        if (purposeInput) {
            const val = purposeInput.value.trim() || 'For review, approval, and official record.';
            document.querySelectorAll('[data-preview-output="purpose"]').forEach(el => {
                el.textContent = val;
            });
        }

        // Date
        const dateInput = document.getElementById('draft_transmittal_date') || document.querySelector('[name="transmittal_date"]');
        if (dateInput && dateInput.value) {
            document.querySelectorAll('[data-preview-output="date"]').forEach(el => {
                el.textContent = dateInput.value;
            });
        }

        // Direction / Mode
        const dirSelect = document.getElementById('draft_direction') || document.querySelector('[name="direction"]');
        const dirVal = (dirSelect?.value || 'send').toLowerCase();
        document.querySelectorAll('[data-preview-output="direction"]').forEach(el => {
            el.textContent = dirVal.toUpperCase();
        });

        // From and To based on Direction
        const recipientInput = document.getElementById('draft_recipient_name') || document.querySelector('[name="recipients[0][name]"]');
        const officeInput = document.getElementById('draft_origin_office') || document.querySelector('[name="origin_office"]');
        const recipientVal = recipientInput?.value.trim() || '';
        const officeVal = officeInput?.value.trim() || '';

        document.querySelectorAll('[data-preview-output="from"]').forEach(el => {
            el.textContent = dirVal === 'receive' ? recipientVal : officeVal;
        });
        document.querySelectorAll('[data-preview-output="to"]').forEach(el => {
            el.textContent = dirVal === 'receive' ? officeVal : recipientVal;
        });

        // Address
        const addrInput = document.getElementById('draft_recipient_address') || document.querySelector('[name="recipient_address"]');
        if (addrInput) {
            document.querySelectorAll('[data-preview-output="address"]').forEach(el => {
                el.textContent = addrInput.value.trim();
            });
        }

        // Delivery Type & Details
        const delTypeSelect = document.getElementById('workspace_delivery_type') || document.querySelector('select[name="delivery_type"]');
        if (delTypeSelect) {
            const dtVal = delTypeSelect.value;
            let displayType = 'By Person';
            if (dtVal === 'by_person') {
                const whoInput = document.getElementById('workspace_by_person_who');
                const whoVal = whoInput ? whoInput.value.trim() : '';
                displayType = whoVal ? `By Person - ${whoVal}` : 'By Person';
            } else if (dtVal === 'registered_mail') {
                const provSel = document.getElementById('workspace_provider_select');
                const provOther = document.getElementById('workspace_provider_other_input');
                let provVal = provSel ? provSel.value : '';
                if (provVal === 'Others') {
                    provVal = provOther && provOther.value.trim() ? `Others (${provOther.value.trim()})` : 'Others';
                }
                displayType = provVal ? `Registered Mail - ${provVal}` : 'Registered Mail';
            } else if (dtVal === 'electronic') {
                displayType = 'Electronic - Email';
            } else {
                displayType = delTypeSelect.options[delTypeSelect.selectedIndex]?.text || dtVal;
            }

            document.querySelectorAll('[data-preview-output="delivery-type"]').forEach(el => {
                el.textContent = displayType;
            });
        }

        // Actions
        const actionBoxes = document.querySelectorAll('input[name="delivery_actions[]"]:checked');
        const actionLabels = Array.from(actionBoxes).map(cb => cb.parentElement.textContent.trim()).join(', ');
        document.querySelectorAll('[data-preview-output="delivery-actions"]').forEach(el => {
            el.textContent = actionLabels || '—';
        });

        // Recipient Email
        const emailInput = document.getElementById('draft_recipient_email') || document.querySelector('[name="recipients[0][email]"]');
        if (emailInput) {
            document.querySelectorAll('[data-preview-output="recipient-email"]').forEach(el => {
                el.textContent = emailInput.value.trim() || '—';
            });
        }

        // Electronic Method
        const elecMethodSelect = document.getElementById('workspace_electronic_method');
        const elecMethodVal = delTypeSelect && delTypeSelect.value === 'electronic' ? (elecMethodSelect ? elecMethodSelect.value : 'Email') : '—';
        document.querySelectorAll('[data-preview-output="electronic-method"]').forEach(el => {
            el.textContent = elecMethodVal;
        });

        // Signatories
        const approverInput = document.querySelector('input[name="approved_by_name"]');
        if (approverInput) {
            document.querySelectorAll('[data-preview-output="approved-by"]').forEach(el => {
                el.textContent = approverInput.value.trim();
            });
        }

        const opMgrInput = document.querySelector('input[name="operations_manager"]');
        if (opMgrInput) {
            document.querySelectorAll('[data-preview-output="operations-manager"]').forEach(el => {
                el.textContent = opMgrInput.value.trim();
            });
        }

        const delByInput = document.querySelector('input[name="delivered_by"]');
        if (delByInput) {
            document.querySelectorAll('[data-preview-output="delivered-by"]').forEach(el => {
                el.textContent = delByInput.value.trim();
            });
        }

        const recvByInput = document.querySelector('input[name="received_by"]');
        if (recvByInput) {
            document.querySelectorAll('[data-preview-output="received-by"]').forEach(el => {
                el.textContent = recvByInput.value.trim();
            });
        }

        const procCompInput = document.querySelector('input[name="processing_company"]');
        if (procCompInput) {
            document.querySelectorAll('[data-preview-output="processing-company"]').forEach(el => {
                el.textContent = procCompInput.value.trim();
            });
        }
    }

    // 2. Real-time items table preview synchronization
    function updatePreviewItems() {
        const formRows = document.querySelectorAll('#item-list .item-row');
        const previewTableBody = document.querySelector('[data-preview-form-items]') || document.querySelector('.tm-table tbody') || document.querySelector('.ordo-form-items tbody');
        
        if (!previewTableBody) return;

        let html = '';
        let hasContent = false;

        formRows.forEach((row, index) => {
            const particular = row.querySelector('[name*="[particular]"]')?.value || '';
            const uniqueId = row.querySelector('[name*="[unique_id]"]')?.value || '';
            const quantity = row.querySelector('[name*="[quantity]"]')?.value || '';
            const description = row.querySelector('[name*="[description]"]')?.value || '';
            const remarks = row.querySelector('[name*="[remarks]"]')?.value || '';

            // Check file upload or existing attachment name
            const chosenSpan = row.querySelector('.chosen-file-name');
            const fileDisplay = row.querySelector('strong');
            const fileInput = row.querySelector('input[type="file"]');
            
            let fileName = '—';
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                fileName = Array.from(fileInput.files).map(f => f.name).join(', ');
            } else if (fileDisplay && fileDisplay.textContent) {
                fileName = fileDisplay.textContent.trim();
            } else if (chosenSpan && chosenSpan.textContent && chosenSpan.textContent.trim() !== 'No file chosen') {
                fileName = chosenSpan.textContent.trim();
            }

            if (particular || uniqueId || quantity || description || remarks || (fileName && fileName !== '—')) {
                hasContent = true;
            }

            html += `
                <tr>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${index + 1}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${particular}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${uniqueId}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${quantity}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${description}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${remarks}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">${fileName}</td>
                </tr>
            `;
        });

        previewTableBody.innerHTML = (hasContent && html) 
            ? html 
            : '<tr><td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">1</td><td style="border: 1px solid #9ca3af; padding: 5px 6px;"></td><td style="border: 1px solid #9ca3af; padding: 5px 6px;"></td><td style="border: 1px solid #9ca3af; padding: 5px 6px;"></td><td style="border: 1px solid #9ca3af; padding: 5px 6px;"></td><td style="border: 1px solid #9ca3af; padding: 5px 6px;"></td><td style="border: 1px solid #9ca3af; padding: 5px 6px;">—</td></tr>';
    }

    // 3. File Input UI State synchronization
    function initFileLabels() {
        document.querySelectorAll('.custom-file-upload-wrapper').forEach(wrapper => {
            const fileInput = wrapper.querySelector('input[type="file"]');
            const chosenSpan = wrapper.querySelector('.chosen-file-name');
            if (!fileInput || !chosenSpan) return;

            fileInput.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    const names = Array.from(this.files).map(f => f.name).join(', ');
                    chosenSpan.textContent = names;
                    chosenSpan.style.color = '#0f172a';
                    chosenSpan.style.fontWeight = '600';
                }
                updatePreviewItems();
            });
        });
    }

    // Initialize all bindings on load
    updateFieldPreviews();
    updatePreviewItems();
    initFileLabels();

    // 4. Mag-add ng bagong row kapag pinindot ang "+ Add Row"
    if (addItemBtn && itemList) {
        addItemBtn.addEventListener('click', function () {
            const index = itemList.querySelectorAll('.item-row').length;
            const newRow = document.createElement('fieldset');
            newRow.className = 'item-row';
            newRow.style.cssText = 'border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; background: #ffffff; position: relative; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);';
            
            newRow.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="item-number" style="font-size: 13px; font-weight: 600; color: #1f2937;">Item ${index + 1}</span>
                    <button type="button" class="remove-item-btn" style="color: #000; background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: underline;">Remove</button>
                </div>
                <input type="hidden" name="items[${index}][id]" value="">
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">Particular</label>
                    <input name="items[${index}][particular]" placeholder="Item name" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <div style="flex: 1; min-width: 0;">
                        <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">Unique ID</label>
                        <input name="items[${index}][unique_id]" placeholder="ID / serial" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div style="width: 110px; flex-shrink: 0;">
                        <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">Qty</label>
                        <input type="number" min="0" step="any" name="items[${index}][quantity]" placeholder="Quantity" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                    </div>
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">Description</label>
                    <input name="items[${index}][description]" placeholder="Description" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px;">Remarks</label>
                    <input name="items[${index}][remarks]" placeholder="Remarks" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                </div>
                <div style="margin-bottom: 4px;">
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px;">Upload File</label>
                    <div style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; background: #ffffff;">
                        <div class="custom-file-upload-wrapper" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <label class="custom-file-btn" style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; background: #e5e7eb; border: 1px solid #dfe3e8; border-radius: 5px; color: #4b5563; font-size: 12px; font-weight: 500; cursor: pointer; user-select: none; transition: background 0.15s ease;">
                                Choose Files
                                <input
                                    type="file"
                                    name="items[${index}][attachments][]"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
                                    data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}"
                                    class="item-file-input"
                                    style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;"
                                >
                            </label>
                            <span class="chosen-file-name" style="font-size: 12px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px;">
                                No file chosen
                            </span>
                        </div>
                    </div>
                    <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 5px;">
                        Select a file for this item.
                    </small>
                </div>
            `;
            itemList.appendChild(newRow);
            initFileLabels();
            updatePreviewItems();
        });

        // 5. Mag-remove ng row at mag-update ng preview
        itemList.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-item-btn')) {
                e.target.closest('.item-row').remove();
                const rows = itemList.querySelectorAll('.item-row');
                rows.forEach((row, idx) => {
                    row.querySelector('.item-number').textContent = `Item ${idx + 1}`;
                });
                updatePreviewItems();
            }
        });
    }

    // 6. Signatories dropdown helpers
    const selApp = document.getElementById('workspace_select_approved_by');
    const inpApp = document.getElementById('workspace_approved_by_name');
    if (selApp && inpApp) {
        selApp.addEventListener('change', function () {
            if (this.value) {
                inpApp.value = this.value;
                updateFieldPreviews();
            }
        });
    }

    const selOps = document.getElementById('workspace_select_operations_manager');
    const inpOps = document.getElementById('workspace_operations_manager');
    if (selOps && inpOps) {
        selOps.addEventListener('change', function () {
            if (this.value) {
                inpOps.value = this.value;
                updateFieldPreviews();
            }
        });
    }

    const selDel = document.getElementById('workspace_select_delivered_by');
    const inpDel = document.getElementById('workspace_delivered_by');
    if (selDel && inpDel) {
        selDel.addEventListener('change', function () {
            if (this.value) {
                inpDel.value = this.value;
                updateFieldPreviews();
            }
        });
    }

    const selRec = document.getElementById('workspace_select_received_by');
    const inpRec = document.getElementById('workspace_received_by');
    const inpComp = document.getElementById('workspace_processing_company');
    const inpEmail = document.getElementById('draft_recipient_email');
    if (selRec && inpRec) {
        selRec.addEventListener('change', function () {
            if (this.value) {
                inpRec.value = this.value;
                const opt = this.options[this.selectedIndex];
                const affil = opt?.getAttribute('data-affiliation');
                const em = opt?.getAttribute('data-email');
                if (affil && inpComp && !inpComp.value) {
                    inpComp.value = affil;
                }
                if (em && inpEmail && !inpEmail.value) {
                    inpEmail.value = em;
                }
                updateFieldPreviews();
            }
        });
    }

    const selComp = document.getElementById('workspace_select_company');
    if (selComp && inpComp) {
        selComp.addEventListener('change', function () {
            if (this.value) {
                inpComp.value = this.value;
                updateFieldPreviews();
            }
        });
    }

    // 7. Delivery Type & Dynamic Provider Handling
    const wsDelType = document.getElementById('workspace_delivery_type');
    const wsByPersonCont = document.getElementById('workspace_by_person_container');
    const wsProvCont = document.getElementById('workspace_provider_container');
    const wsProvOtherCont = document.getElementById('workspace_provider_other_container');
    const wsElecCont = document.getElementById('workspace_electronic_method_container');
    const wsRecipEmailCont = document.getElementById('workspace_recipient_email_container');
    const wsProvSelect = document.getElementById('workspace_provider_select');
    const wsProvOtherInput = document.getElementById('workspace_provider_other_input');
    const wsProvFinal = document.getElementById('workspace_registered_mail_provider_final');
    const wsElecMethod = document.getElementById('workspace_electronic_method');

    function syncDeliveryProviderFinal() {
        if (!wsProvFinal) return;
        if (wsProvSelect && wsProvSelect.value === 'Others') {
            wsProvFinal.value = wsProvOtherInput ? wsProvOtherInput.value.trim() : 'Others';
        } else if (wsProvSelect) {
            wsProvFinal.value = wsProvSelect.value;
        }
    }

    if (wsDelType) {
        wsDelType.addEventListener('change', function () {
            const val = this.value;
            if (wsByPersonCont) wsByPersonCont.style.display = (val === 'by_person') ? 'block' : 'none';
            if (wsProvCont) wsProvCont.style.display = (val === 'registered_mail') ? 'block' : 'none';
            if (wsProvOtherCont) wsProvOtherCont.style.display = (val === 'registered_mail' && wsProvSelect && wsProvSelect.value === 'Others') ? 'block' : 'none';
            if (wsElecCont) wsElecCont.style.display = (val === 'electronic') ? 'block' : 'none';
            if (wsRecipEmailCont) wsRecipEmailCont.style.display = (val === 'electronic') ? 'block' : 'none';

            if (val === 'electronic') {
                if (wsElecMethod) wsElecMethod.value = 'Email';
            }
            syncDeliveryProviderFinal();
            updateFieldPreviews();
        });
    }

    if (wsProvSelect) {
        wsProvSelect.addEventListener('change', function () {
            if (wsProvOtherCont) {
                wsProvOtherCont.style.display = (this.value === 'Others') ? 'block' : 'none';
            }
            syncDeliveryProviderFinal();
            updateFieldPreviews();
        });
    }

    if (wsProvOtherInput) {
        wsProvOtherInput.addEventListener('input', function () {
            syncDeliveryProviderFinal();
            updateFieldPreviews();
        });
    }

    // 7. Mag-update habang nagta-type sa alinmang form fields
    document.addEventListener('input', function (e) {
        if (e.target.closest('#transmittal-form') || e.target.id === 'draft_confidentiality' || e.target.id === 'draft_purpose') {
            updateFieldPreviews();
            if (e.target.name && e.target.name.includes('items')) {
                updatePreviewItems();
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.closest('#transmittal-form') || e.target.id === 'draft_confidentiality' || e.target.id === 'draft_purpose') {
            updateFieldPreviews();
            if (e.target.name && e.target.name.includes('items')) {
                updatePreviewItems();
            }
        }
    });
});
</script>
@endpush



        </div>
    </div>
</div>
                {{-- TRANSMITTAL --}}
                <div class="tab-content" id="tab-transmittal">
                    <div class="transmittal-preview-heading">
                        <div class="section-title tab-title">
                            <span class="step-number">02</span>

                            <div>
                                <h2>Transmittal</h2>
                                <p class="muted">
                                    Preview the official two-page transmittal form.
                                </p>
                            </div>
                        </div>
                    </div>

                    <section
                        class="document-preview-pane"
                        aria-label="Live transmittal document preview"
                    >
                        <div class="preview-pane-header">
                            <span>DOCUMENT PREVIEW</span>
                        </div>

                        <div class="add-preview-pages workspace-document-pages">
                            @include('transmittals._document-form', [
                                'transmittal' => $transmittal,
                                'preview' => true
                            ])
                        </div>
                    </section>
                </div>

                {{-- RECEIPT --}}
                <div class="tab-content" id="tab-receipt">
                    <div class="section-title tab-title">
                        <span class="step-number">03</span>

                        <div>
                            <h2>Transmittal Receipt (TRR)</h2>
                            <p class="muted">
                                Official acknowledgment receipt linked to this transmittal.
                            </p>
                        </div>
                    </div>

                    @forelse ($transmittal->receipts as $receipt)
                        <div style="margin-bottom: 24px;">
                            @include('transmittals._receipt-document', ['transmittal' => $transmittal, 'receipt' => $receipt, 'canViewPrivateFiles' => $canViewPrivateFiles])
                        </div>
                    @empty
                        <article class="workspace-receipt-document receipt-empty">
                            <p class="eyebrow">OFFICIAL RECEIPT</p>

                            <h2>TRANSMITTAL RECEIPT (TRR)</h2>

                            <p class="receipt-reference">
                                TRN Reference:
                                <strong>{{ $transmittal->tracking_number }}</strong>
                            </p>

                            <p>
                                A receipt will appear here after the recipient acknowledges this transmittal.
                            </p>

                            <p class="receipt-legal">
                                This tab is for the Transmittal Receipt (TRR), not the Transmittal Form.
                            </p>
                        </article>
                    @endforelse
                </div>

                {{-- PROOF OF TRANSMISSION --}}
                <div class="tab-content" id="tab-proof">
                    <div class="section-title tab-title">
                        <span class="step-number">04</span>

                        <div>
                            <h2>Proof of Transmission</h2>
                            <p class="muted">
                                Each proof, sent, viewed, acknowledged, or manual event
                                is recorded under this TRN.
                            </p>
                        </div>
                    </div>

                    <div class="proof-record-notice">
                        Each upload, send, view, acknowledgment, or manually recorded
                        event becomes its own transmission record.
                    </div>

                    <div class="proof-record-heading">
                        <div>
                            <h3>Transmission records</h3>
                            <p>Multiple records can belong to one TRN.</p>
                        </div>

                        <button
                            class="button primary"
                            type="button"
                            data-open-proof-action
                        >
                            + Add proof record
                        </button>
                    </div>

                    <div class="table-scroll proof-record-table-scroll">
                        <table class="data-table proof-record-table">
                            <thead>
                                <tr>
                                    <th style="width: 140px; text-align: left;">Type</th>
                                    <th style="width: 130px; text-align: left;">Reference No.</th>
                                    <th style="width: 160px; text-align: left;">Recipient / Receiver</th>
                                    <th style="min-width: 200px; text-align: left;">Transmission Method</th>
                                    <th style="width: 110px; text-align: center;">Status</th>
                                    <th style="width: 150px; text-align: left;">Date Sent / Uploaded</th>
                                    <th style="width: 150px; text-align: left;">Date Acknowledged</th>
                                    <th style="width: 160px; text-align: left;">Source / Proof</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($transmittal->proofs as $proof)
                                    @include('transmittals._proof-record-row', [
                                        'proof' => $proof
                                    ])
                                @empty
                                    <tr>
                                        <td class="proof-empty-cell" colspan="9">
                                            <strong>No proof records yet</strong>
                                            <span>
                                                Use Upload proof in the Action Center to add evidence.
                                            </span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ATTACHMENTS --}}
                <div class="tab-content" id="tab-attachments">
                    <div class="section-title tab-title">
                        <span class="step-number">04</span>

                        <div>
                            <h2>Attachments</h2>
                            <p class="muted">
                                Uploaded source documents and item files.
                            </p>
                        </div>
                    </div>

                    @if ($transmittal->documents->isNotEmpty())
                        @php
                            $initialAttachment = $transmittal->documents->first();
                            $initialExtension = strtolower(pathinfo($initialAttachment->original_name, PATHINFO_EXTENSION));
                            $initialMime = strtolower($initialAttachment->mime_type ?: '');
                            $initialPreviewable = in_array($initialMime, ['application/pdf', 'text/plain'], true)
                                || (str_starts_with($initialMime, 'image/') && ! in_array($initialExtension, ['tif', 'tiff'], true))
                                || in_array($initialExtension, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true);
                            $initialUrl = route('transmittals.documents.download', [$transmittal, $initialAttachment]);
                            $initialPreviewUrl = $initialUrl.($initialExtension === 'pdf' ? '#view=FitH&navpanes=0' : '');
                        @endphp
                        @php
                            $canShowAttachments = ($canViewAttachments ?? true) || ($canViewPrivateFiles ?? false);
                        @endphp
                        @if ($canShowAttachments)
                        <div class="attachment-browser-layout" data-attachment-browser>
                            <aside class="attachment-browser" aria-label="Uploaded attachments">
                                <h3>Attachments <span>{{ $transmittal->documents->count() }}</span></h3>
                                <div class="attachment-browser-list">
                                    @foreach ($transmittal->documents as $document)
                                        @php
                                            $extension = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
                                            $mime = strtolower($document->mime_type ?: '');
                                            $previewable = in_array($mime, ['application/pdf', 'text/plain'], true)
                                                || (str_starts_with($mime, 'image/') && ! in_array($extension, ['tif', 'tiff'], true))
                                                || in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'], true);
                                            $url = route('transmittals.documents.download', [$transmittal, $document]);
                                            $previewUrl = $url.($extension === 'pdf' ? '#view=FitH&navpanes=0' : '');
                                        @endphp
                                        <button class="attachment-browser-item" type="button" data-attachment-select
                                            data-attachment-url="{{ $url }}" data-attachment-fit-url="{{ $previewUrl }}"
                                            data-attachment-name="{{ $document->original_name }}"
                                            data-attachment-category="{{ str($document->category)->replace('_', ' ')->title() }}"
                                            data-attachment-previewable="{{ $previewable ? 'true' : 'false' }}"
                                            aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                                            <span class="file-type">{{ strtoupper($extension ?: 'FILE') }}</span>
                                            <span class="attachment-copy"><strong>{{ $document->original_name }}</strong><small>{{ str($document->category)->replace('_', ' ')->title() }}</small></span>
                                        </button>
                                    @endforeach
                                </div>
                            </aside>
                            <section class="attachment-preview" aria-label="Attachment preview">
                                <header class="attachment-preview-heading">
                                    <div><h3 data-attachment-preview-title>{{ $initialAttachment->original_name }}</h3><p data-attachment-preview-category>{{ str($initialAttachment->category)->replace('_', ' ')->title() }}</p></div>
                                    <div class="attachment-preview-actions">
                                        <a href="{{ $initialUrl }}" target="_blank" download class="button subtle" style="padding: 7px 14px; font-size: 13px; font-weight: 600; text-decoration: none;">Download File</a>
                                    </div>
                                </header>
                                <div class="attachment-preview-stage">
                                    <iframe data-attachment-frame title="{{ $initialAttachment->original_name }}" src="{{ $initialPreviewUrl }}" @if (! $initialPreviewable) hidden @endif></iframe>
                                    <p data-attachment-unavailable @if ($initialPreviewable) hidden @endif>This file format cannot be previewed directly in the browser. Download it to view the document.</p>
                                </div>
                            </section>
                        </div>
                        @else
                            <div class="empty-inline">Attachments are private. Sign in with an authorized account or open via recipient link.</div>
                        @endif
                    @else
                        <div class="empty-inline">No uploaded attachments.</div>
                    @endif
                </div>

                {{-- HISTORY --}}
                <div class="tab-content" id="tab-history">
                    <div class="section-title tab-title">
                        <span class="step-number">05</span>

                        <div>
                            <h2>History</h2>
                            <p class="muted">
                                Recorded activity for this transmittal.
                            </p>
                        </div>
                    </div>

                    <div class="timeline">
                        @forelse ($transmittal->histories as $entry)
                            <div class="timeline-entry">
                                <span class="timeline-mark"></span>

                                <div>
                                    <strong>
                                        {{ ucfirst(str_replace('_', ' ', $entry->action)) }}
                                    </strong>

                                    <p>{{ $entry->description }}</p>

                                    <small>
                                        {{ $entry->created_at->format('M j, Y · H:i:s') }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="empty-inline">
                                No history available.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

            @include('transmittals._action-center', [
                'transmittal' => $transmittal,
                'draftWorkspace' => true
            ])
        </div>
    </section>
</div>
@endsection

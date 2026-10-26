@extends('layouts.app')

@section('title', $transmittal->tracking_number . ' Workspace')

@section('content')
    <div class="workspace-page-heading">
        <div>
            <span class="eyebrow">ORDO / TRANSMITTAL WORKSPACE</span>
            <h1>Transmittal Preview</h1>
            <p>{{ $transmittal->tracking_number }}</p>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <a
                class="button secondary"
                href="{{ route('transmittals.index') }}"
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

            <article class="detail-card meta-card" style="padding: 20px;">

                <h2 style="margin-bottom: 16px; font-size: 18px; font-weight: 600;">
                    Edit Transmittal Details
                </h2>

<form
    action="{{ route('transmittals.update', $transmittal) }}"
    method="POST"
    enctype="multipart/form-data"
    id="transmittal-form"
>
                
                    @csrf
                    @method('PUT')

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

                        {{-- Direction --}}
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

                        {{-- Recipient Name --}}
                        <div>
                            <label
                                for="draft_recipient_name"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                To (Recipient Name)
                            </label>

                            <input
                                id="draft_recipient_name"
                                type="text"
                                name="recipients[0][name]"
                                value="{{ old('recipients.0.name', $draftRecipient?->name) }}"
                                maxlength="120"
                                placeholder="Recipient name"
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
                                From (Office) *
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

                        {{-- Recipient Email --}}
                        <div>
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
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
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

                        {{-- Related Company --}}
                        <div>
                            <label
                                for="draft_related_company"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Related Client / Company
                            </label>

                            <input
                                id="draft_related_company"
                                type="text"
                                name="related_company"
                                value="{{ old('related_company', $transmittal->related_company) }}"
                                maxlength="160"
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >

                            @error('related_company')
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
                            @endphp
                            <select name="delivery_type" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                                <option value="by_person" {{ $selectedDeliveryType === 'by_person' ? 'selected' : '' }}>By Person</option>
                                <option value="registered_mail" {{ $selectedDeliveryType === 'registered_mail' ? 'selected' : '' }}>Registered Mail</option>
                                <option value="electronic" {{ $selectedDeliveryType === 'electronic' ? 'selected' : '' }}>Electronic</option>
                            </select>
                        </div>

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

                        {{-- Subject --}}
                        <div>
                            <label
                                for="draft_subject"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Subject *
                            </label>

                            <input
                                id="draft_subject"
                                type="text"
                                name="subject"
                                value="{{ old('subject', $transmittal->subject) }}"
                                maxlength="200"
                                placeholder="Document package or transmittal subject"
                                required
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >

                            @error('subject')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Purpose --}}
                        <div>
                            <label
                                for="draft_purpose"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Purpose *
                            </label>

                            <textarea
                                id="draft_purpose"
                                name="purpose"
                                rows="3"
                                maxlength="5000"
                                placeholder="Describe what is being transmitted and any action required"
                                required
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >{{ old('purpose', $transmittal->purpose) }}</textarea>

                            @error('purpose')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- Confidentiality --}}
                        <div>
                            <label
                                for="draft_confidentiality"
                                style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;"
                            >
                                Confidentiality *
                            </label>

                            <select
                                id="draft_confidentiality"
                                name="confidentiality"
                                required
                                style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;"
                            >
                                <option
                                    value="standard"
                                    {{ old('confidentiality', $transmittal->confidentiality ?? 'standard') === 'standard' ? 'selected' : '' }}
                                >
                                    Standard
                                </option>

                                <option
                                    value="confidential"
                                    {{ old('confidentiality', $transmittal->confidentiality ?? 'standard') === 'confidential' ? 'selected' : '' }}
                                >
                                    Confidential
                                </option>

                                <option
                                    value="restricted"
                                    {{ old('confidentiality', $transmittal->confidentiality ?? 'standard') === 'restricted' ? 'selected' : '' }}
                                >
                                    Restricted
                                </option>
                            </select>

                            @error('confidentiality')
                                <small style="display: block; color: #dc2626;">{{ $message }}</small>
                            @enderror
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
                        style="color: #dc2626; background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600;"
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

<input
    type="file"
    name="items[{{ $index }}][attachments][]"
    multiple
    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
    style="display: block; width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box; border: 0; padding: 0; margin: 0; background: transparent; color: #8495ad; font-size: 12px;"
>
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
        <input
            type="file"
            name="supporting_attachments[]"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx"
            data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}"
            style="display: block; width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box; border: 0; padding: 0; margin: 0; background: transparent; color: #8495ad; font-size: 12px;"
        >
    </div>

    <small style="color: #6b7280; font-size: 11px; display: block;">
        You may upload multiple supporting files. These will be attached when using Send Email.
    </small>
</div>

{{-- GRAY CHOOSE FILE BUTTONS --}}
<style>
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
                        <div style="border-top: 1px solid #e5e7eb; padding-top: 12px; display: flex; flex-direction: column; gap: 10px;">
                            <label style="font-size: 13px; font-weight: 600; color: #374151;">Signatories</label>

                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Approved by</label>
                                <input name="approved_by_name" value="{{ old('approved_by_name', $transmittal->approved_by_name ?? '') }}" placeholder="Type approver name" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Operations Manager</label>
                                <input name="operations_manager" value="{{ old('operations_manager', $transmittal->operations_manager ?? '') }}" placeholder="Type Operations Manager" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Delivered by</label>
                                <input name="delivered_by" value="{{ old('delivered_by', $transmittal->delivered_by ?? '') }}" placeholder="Delivered by" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 2px;">Received by (Contact / Employee)</label>
                                <input name="received_by" value="{{ old('received_by', $transmittal->received_by ?? '') }}" placeholder="Receiver name" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                            </div>

                            <div>
                                <label style="display: block; font-size: 11px; color: #374151; margin-bottom: 4px;">Processing for Company / Organization</label>
                                <input name="processing_company" value="{{ old('processing_company', $transmittal->processing_company ?? '') }}" placeholder="Company name" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
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

                    </div>

                </form>
            </article>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemList = document.getElementById('item-list');
    const addItemBtn = document.getElementById('add-item-btn');

    function updatePreviewItems() {
        const formRows = document.querySelectorAll('#item-list .item-row');
        const previewTableBody = document.querySelector('.ordo-form-items tbody');
        
        if (!previewTableBody) return;

        let html = '';
        let hasContent = false;

        formRows.forEach((row, index) => {
            const particular = row.querySelector('[name*="[particular]"]')?.value || '';
            const uniqueId = row.querySelector('[name*="[unique_id]"]')?.value || '';
            const quantity = row.querySelector('[name*="[quantity]"]')?.value || '';
            const description = row.querySelector('[name*="[description]"]')?.value || '';
            const remarks = row.querySelector('[name*="[remarks]"]')?.value || '';

            if (particular || uniqueId || quantity || description || remarks) {
                hasContent = true;
            }

const fileDisplay = row.querySelector('strong');
            let fileName = '—';
            if (fileDisplay && fileDisplay.textContent) {
                fileName = fileDisplay.textContent.trim();
            }

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${particular}</td>
                    <td>${uniqueId}</td>
                    <td>${quantity}</td>
                    <td>${description}</td>
                    <td>${remarks}</td>
                    <td>${fileName}</td>
                </tr>
            `;
        });

        previewTableBody.innerHTML = (hasContent && html) ? html : '<tr><td>1</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
    }

    // 1. I-load agad ang laman pagbukas ng page
    updatePreviewItems();

    // 2. Mag-add ng bagong row kapag pinindot ang "+ Add Row"
    if (addItemBtn && itemList) {
        addItemBtn.addEventListener('click', function () {
            const index = itemList.querySelectorAll('.item-row').length;
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.style.cssText = 'border: 1px solid #d1d5db; padding: 10px; border-radius: 6px; background: #f9fafb; position: relative;';
            
            newRow.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 11px; font-weight: 600; color: #4b5563;" class="item-number">Item ${index + 1}</span>
                    <button type="button" class="remove-item-btn" style="color: #dc2626; background: none; border: none; cursor: pointer; font-size: 11px; font-weight: 600;">Remove</button>
                </div>
                <input type="hidden" name="items[${index}][id]" value="">
                <div style="margin-bottom: 6px;">
                    <label style="font-size: 11px; color: #374151;">Particular</label>
                    <input name="items[${index}][particular]" placeholder="Item name" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                </div>
                <div style="display: flex; gap: 6px; margin-bottom: 6px;">
                    <div style="flex: 1;">
                        <label style="font-size: 11px; color: #374151;">Unique ID</label>
                        <input name="items[${index}][unique_id]" placeholder="ID / serial" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                    </div>
                    <div style="width: 80px;">
                        <label style="font-size: 11px; color: #374151;">Qty</label>
                        <input type="number" min="0" step="any" name="items[${index}][quantity]" placeholder="Qty" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                    </div>
                </div>
                <div style="margin-bottom: 6px;">
                    <label style="font-size: 11px; color: #374151;">Description</label>
                    <input name="items[${index}][description]" placeholder="Description" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label style="font-size: 11px; color: #374151;">Remarks</label>
                    <input name="items[${index}][remarks]" placeholder="Remarks" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
                </div>
                <div>
                    <label style="font-size: 11px; color: #374151;">Upload File</label>
                    <input type="file" name="items[${index}][attachment]" style="width: 100%; font-size: 11px;">
                </div>
            `;
            itemList.appendChild(newRow);
            updatePreviewItems();
        });

        // 3. Mag-remove ng row at mag-update ng preview
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

    // 4. Mag-update habang nagta-type sa fields
    document.addEventListener('input', function (e) {
        if (e.target.closest('#transmittal-form') && e.target.name && e.target.name.includes('items')) {
            updatePreviewItems();
        }
    });
});
</script>
@endpush



            {{-- Receipt Information --}}
            <article class="detail-card meta-card">
                <h2>Receipt Information</h2>

                <dl>
                    <div>
                        <dt>Receipt No</dt>
                        <dd>{{ $latestReceipt?->receipt_code ?? 'Not yet generated' }}</dd>
                    </div>

                    <div>
                        <dt>Receipt File</dt>
                        <dd>
                            @if ($latestReceipt && $canViewPrivateFiles)
                                <a href="{{ route('transmittals.receipts.pdf', [$transmittal, $latestReceipt->id]) }}">
                                    Download TRR
                                </a>
                            @else
                                {{ $latestReceipt ? 'Available to authorized users' : 'Not available' }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </article>

            {{-- Attachment Summary --}}
            <article class="detail-card meta-card">
                <h2>Attachment Summary</h2>

                <dl>
                    <div>
                        <dt>With Files</dt>
                        <dd>{{ $transmittal->documents->count() }}</dd>
                    </div>

                    <div>
                        <dt>Total Items</dt>
                        <dd>{{ $transmittal->items->count() }}</dd>
                    </div>
                </dl>
            </article>

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
                        <article class="workspace-receipt-document">
                            <p class="eyebrow">OFFICIAL RECORD</p>

                            <h2>OFFICIAL TRANSMITTAL RECEIPT (TRR)</h2>

                            <p class="receipt-reference">
                                TRN Reference:
                                <strong>{{ $transmittal->tracking_number }}</strong>
                            </p>

                            <dl>
                                <div>
                                    <dt>TRR Reference</dt>
                                    <dd>{{ $receipt->receipt_code }}</dd>
                                </div>

                                <div>
                                    <dt>Recipient</dt>
                                    <dd>{{ $receipt->recipient->name ?: $receipt->recipient->email }}</dd>
                                </div>

                                <div>
                                    <dt>Acknowledged by</dt>
                                    <dd>{{ $receipt->signed_name }}</dd>
                                </div>

                                <div>
                                    <dt>Date / time received</dt>
                                    <dd>{{ $receipt->received_at->format('M j, Y · H:i:s') }}</dd>
                                </div>

                                <div>
                                    <dt>Comments</dt>
                                    <dd>{{ $receipt->comments ?: 'No comments provided.' }}</dd>
                                </div>
                            </dl>

                            <p class="receipt-legal">
                                This is a Transmittal Receipt (TRR) linked to the TRN.
                                It is a receipt record and not the Transmittal Form.
                            </p>

                            @if ($canViewPrivateFiles)
                                <a href="{{ route('transmittals.receipts.pdf', [$transmittal, $receipt->id]) }}">
                                    View / Print official TRR
                                </a>
                            @endif
                        </article>
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
                                    <th>Type</th>
                                    <th>Reference No.</th>
                                    <th>Recipient / Receiver</th>
                                    <th>Transmission Method</th>
                                    <th>Status</th>
                                    <th>Date Sent / Uploaded</th>
                                    <th>Date Acknowledged</th>
                                    <th>Source / Proof</th>
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
                        @if ($canViewPrivateFiles ?? false)
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
                                    <button class="button subtle attachment-expand-button" type="button" data-expand-attachment-preview aria-pressed="false">Expand Preview</button>
                                </header>
                                <div class="attachment-preview-stage">
                                    <iframe data-attachment-frame title="{{ $initialAttachment->original_name }}" src="{{ $initialPreviewUrl }}" @if (! $initialPreviewable) hidden @endif></iframe>
                                    <p data-attachment-unavailable @if ($initialPreviewable) hidden @endif>This file format cannot be previewed in the browser. Download it to open the document.</p>
                                </div>
                            </section>
                        </div>
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
@endsection

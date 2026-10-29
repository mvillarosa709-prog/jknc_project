@php
    $preview = $preview ?? false;
    $recipients = $transmittal?->recipients ?? collect();
    $recipient = $recipients->first();
    $direction = old('direction', $transmittal?->direction ?? 'send');
    $date = old('transmittal_date', $transmittal?->transmittal_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $office = old('origin_office', $transmittal?->origin_office ?? config('ordo.office_name'));
    $trackingNumber = $transmittal?->tracking_number ?? 'AUTO-INCREMENT';
    
    $formItemsInput = old('items', null);
    if ($formItemsInput !== null) {
        $items = collect($formItemsInput)->map(function($item) {
            return (object) [
                'particular' => $item['particular'] ?? '',
                'unique_id' => $item['unique_id'] ?? '',
                'quantity' => $item['quantity'] ?? '',
                'description' => $item['description'] ?? '',
                'remarks' => $item['remarks'] ?? '',
                'document' => null
            ];
        });
    } else {
        $items = $transmittal?->items ?? collect();
    }

    $deliveryActions = old('delivery_actions', $transmittal?->delivery_actions ?? ['email']);
    $preparedBy = $transmittal?->prepared_by_name ?: ($transmittal?->preparer?->name ?? auth()->user()?->name ?? 'Public submitter');
    $preparedAt = $transmittal?->prepared_at?->format('m/d/Y, g:i:s A') ?? now()->format('m/d/Y, g:i:s A');
    $from = $direction === 'receive' ? ($recipient?->name ?? '') : $office;
    $to = $direction === 'receive' ? $office : ($recipient?->name ?: ($transmittal?->related_company ?? ''));
    $fromLabel = $direction === 'receive' ? 'From' : 'From';
    $toLabel = $direction === 'receive' ? 'To' : 'To';
    $deliveryType = old('delivery_type', $transmittal?->delivery_type ?? 'by_person');
    $deliveryTypeLabel = str($deliveryType)->replace('_', ' ')->title();
    $actionLabels = collect($deliveryActions)->map(fn ($action) => str($action)->replace('_', ' ')->title())->implode(', ');

    $confidentiality = old('confidentiality', $transmittal?->confidentiality ?? 'standard');
    $confidentialityLabel = [
        'standard' => 'Standard',
        'confidential' => 'Confidential',
        'restricted' => 'Restricted',
    ][$confidentiality] ?? ucfirst((string) $confidentiality);
    $purpose = old('purpose', $transmittal?->purpose ?? 'For review, approval, and official record.');

    $logoLocalPath = public_path('images/FINAL_LOGO.jpg');
    $logoSrc = asset('images/FINAL_LOGO.jpg');
    if (! $preview && is_file($logoLocalPath)) {
        $mime = mime_content_type($logoLocalPath) ?: 'image/jpeg';
        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoLocalPath));
    }
@endphp

{{-- PAGE 1: Confidential Notes & Classification Cover Page --}}
<div id="transmittal-cover-page" class="transmittal-doc-page bg-white border border-gray-300 shadow" style="width: 210mm; min-height: 297mm; padding: 22mm 22mm 20mm 22mm; box-sizing: border-box; background: #fff; color: #000; font-family: 'Times New Roman', Georgia, serif; display: flex; flex-direction: column; margin: 0 auto 28px auto; page-break-after: always; line-height: 1.45;">
    
    {{-- Center-aligned JK&C logo --}}
    <div style="text-align: center; margin-bottom: 24px;">
        @if (!empty($logoSrc))
            <img src="{{ $logoSrc }}" alt="JK&amp;C Logo" class="tm-corp-logo" style="width: 145px; max-width: 145px; max-height: 75px; object-fit: contain; margin: 0 auto; display: block;">
        @endif
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
            CONTROL NO.: <span data-preview-output="tracking-number">{{ $trackingNumber }}</span>
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

{{-- PAGE 2: Official Transmittal Form --}}
<div id="transmittal-preview-pdf" class="transmittal-doc-page bg-white border border-gray-300 shadow" style="width: 210mm; min-height: 297mm; padding: 25.4mm; box-sizing: border-box; background: #fff; color: #111827; font-family: Arial, sans-serif; display: flex; flex-direction: column; margin: 0 auto;">
    <div class="tm-corp-header" style="text-align: center; margin-bottom: 20px; color: #000; width: 100%;">
        <table style="width: 100%; border-collapse: collapse; margin: 0 auto; text-align: center;">
            <tr>
                <td style="text-align: center; vertical-align: middle; padding: 0;">
                    <img src="{{ $logoSrc }}" alt="Company Logo" class="tm-corp-logo" style="width: 155px; max-width: 155px; max-height: 100px; object-fit: contain; margin: 0 auto 6px auto; display: inline-block;">
                </td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle; padding: 0;">
                    <div class="tm-corp-name" style="font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 700; text-transform: uppercase; line-height: 1.15; text-align: center;">JK&amp;C INC.</div>
                    <div class="tm-corp-line" style="font-family: Georgia, 'Times New Roman', serif; font-size: 12px; font-weight: 700; line-height: 1.25; text-transform: uppercase; text-align: center;">CONTROL NO.: <span data-preview-output="tracking-number">{{ $trackingNumber }}</span></div>
                    <div class="tm-corp-line" style="font-family: Georgia, 'Times New Roman', serif; font-size: 12px; font-weight: 700; line-height: 1.25; text-transform: uppercase; text-align: center;">3RD FLOOR UNIT 305 CEBU HOLDINGS CENTER, CEBU BUSINESS PARK, HIPPODROMO, CEBU CITY</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="tm-title" style="text-align: center; margin-bottom: 18px;">
        <div class="tm-title-main" style="font-size: 26px; font-weight: 800; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif;">Transmittal Form</div>
    </div>

    <div class="tm-top-block" style="font-size: 12px; margin-bottom: 16px;">
        <div class="tm-top-row tm-top-row-first" style="display: grid; grid-template-columns: 70px 1fr 42px 130px; gap: 10px; align-items: end; margin-bottom: 6px;">
            <div class="tm-label" style="font-weight: 700; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 14px;">Ref No</div>
            <div class="tm-line" data-preview-output="tracking-number" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $trackingNumber }}</div>

            <div class="tm-label tm-date-label" style="font-weight: 700; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 14px; text-align: right;">Date</div>
            <div class="tm-line tm-date" data-preview-output="date" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ \Illuminate\Support\Carbon::parse($date)->format('Y-m-d') }}</div>
        </div>

        <div class="tm-top-row" style="display: grid; grid-template-columns: 70px 1fr; gap: 10px; align-items: end; margin-bottom: 6px;">
            <div class="tm-label" style="font-weight: 700; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 14px;">Mode</div>
            <div class="tm-line" data-preview-output="direction" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ strtoupper($direction) }}</div>
        </div>

        <div class="tm-top-row" style="display: grid; grid-template-columns: 70px 1fr; gap: 10px; align-items: end; margin-bottom: 6px;">
            <div class="tm-label" data-preview-party="{{ $direction === 'receive' ? 'counterparty' : 'office' }}" style="font-weight: 700; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 14px;">From</div>
            <div class="tm-line tm-address" data-preview-output="from" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word; white-space: normal;">{{ $from }}</div>
        </div>

        <div class="tm-top-row" style="display: grid; grid-template-columns: 70px 1fr; gap: 10px; align-items: end; margin-bottom: 6px;">
            <div class="tm-label" data-preview-party="{{ $direction === 'receive' ? 'office' : 'counterparty' }}" style="font-weight: 700; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 14px;">To</div>
            <div class="tm-line tm-address" data-preview-output="to" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word; white-space: normal;">{{ $to }}</div>
        </div>

        <div class="tm-top-row" style="display: grid; grid-template-columns: 70px 1fr; gap: 10px; align-items: end; margin-bottom: 6px;">
            <div class="tm-label" style="font-weight: 700; line-height: 1.2; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 14px;">Address</div>
            <div class="tm-line tm-address" data-preview-output="address" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word; white-space: normal;">{{ $transmittal?->recipient_address ?? '' }}</div>
        </div>
    </div>

    <div class="tm-meta-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; font-size: 11px; margin-bottom: 16px;">
        <div>
            <span class="tm-meta-label" style="font-weight: 700;">Delivery Type:</span>
            <span data-preview-output="delivery-type">{{ $deliveryTypeLabel }}</span>
        </div>
        <div>
            <span class="tm-meta-label" style="font-weight: 700;">Actions:</span>
            <span data-preview-output="delivery-actions">{{ $actionLabels ?: '—' }}</span>
        </div>
        <div>
            <span class="tm-meta-label" style="font-weight: 700;">Recipient Email:</span>
            <span data-preview-output="recipient-email">{{ $recipient?->email ?: '—' }}</span>
        </div>
        <div>
            <span class="tm-meta-label" style="font-weight: 700;">Electronic Method:</span>
            <span data-preview-output="electronic-method">{{ $transmittal?->delivery_method ? ucfirst($transmittal->delivery_method) : '—' }}</span>
        </div>
    </div>

    <div class="tm-section-title" style="font-size: 18px; font-weight: 800; margin: 18px 0 10px; text-align: center; color: #0037a6; font-family: Georgia, 'Times New Roman', serif;">List of Items</div>

    <table class="tm-table" style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10.5px; margin-bottom: 20px;">
        <thead>
            <tr>
                <th class="tm-col-no" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: 36px;">No</th>
                <th class="tm-col-particular" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: 100px;">Particular</th>
                <th class="tm-col-uid" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: 90px;">Unique ID</th>
                <th class="tm-col-qty" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: 40px;">Qty.</th>
                <th class="tm-col-description" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: auto;">Description</th>
                <th class="tm-col-remarks" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: 85px;">Remarks</th>
                <th class="tm-col-file" style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: center; font-weight: 700; background: #0b3db8; color: #fff; width: 100px;">Attachment</th>
            </tr>
        </thead>
        <tbody data-preview-form-items>
            @forelse ($items as $item)
                <tr>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ $loop->iteration }}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ data_get($item, 'particular') ?: '' }}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ data_get($item, 'unique_id') ?: '' }}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ data_get($item, 'quantity') ?: '' }}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ data_get($item, 'description') ?: '' }}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ data_get($item, 'remarks') ?: '' }}</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">{{ data_get($item, 'document.original_name') ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">1</td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;"></td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;"></td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;"></td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;"></td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;"></td>
                    <td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; text-align: left; min-height: 26px; word-break: break-word;">—</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="tm-signatures" style="display: grid; grid-template-columns: 1fr 1fr; gap: 36px; font-size: 12px;">
        <div class="tm-sign-col" style="display: flex; flex-direction: column;">
            <div class="tm-sign-label" style="margin-bottom: 24px;">Prepared by:</div>
            <div class="tm-sign-line" data-preview-output="prepared-by" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $preparedBy }}</div>
            <div class="tm-sign-sub" data-preview-output="prepared-at" style="font-size: 10px; color: #6b7280; margin-top: 4px;">{{ $preparedAt }}</div>

            <div class="tm-sign-label tm-sign-gap" style="margin-top: 28px; margin-bottom: 24px;">Approved by:</div>
            <div class="tm-sign-line" data-preview-output="approved-by" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $transmittal?->approver?->name ?? $transmittal?->approved_by_name }}</div>

            <div class="tm-sign-label tm-sign-gap-sm" style="margin-top: 22px; margin-bottom: 24px;">Operations Manager:</div>
            <div class="tm-sign-line" data-preview-output="operations-manager" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $transmittal?->operations_manager }}</div>
        </div>

        <div class="tm-sign-col" style="display: flex; flex-direction: column;">
            <div class="tm-sign-label" style="margin-bottom: 24px;">Delivered by:</div>
            <div class="tm-sign-line" data-preview-output="delivered-by" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $transmittal?->delivered_by }}</div>

            <div class="tm-sign-label tm-sign-gap" style="margin-top: 28px; margin-bottom: 24px;">Received by:</div>
            <div class="tm-sign-line" data-preview-output="received-by" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $transmittal?->received_by }}</div>

            <div class="tm-sign-label tm-sign-gap-sm" style="margin-top: 22px; margin-bottom: 24px;">Processing for Company / Organization:</div>
            <div class="tm-sign-line" data-preview-output="processing-company" style="border-bottom: 1px solid #9ca3af; min-height: 18px; line-height: 1.2; padding-bottom: 2px; word-break: break-word;">{{ $transmittal?->processing_company }}</div>
        </div>
    </div>

    <div class="tm-footer-code" style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; margin-top: auto; padding-top: 18px;">
        <div>JKNC-TF-GS-V.1-2025</div>
        <div>Page 2 of 2</div>
    </div>
</div>

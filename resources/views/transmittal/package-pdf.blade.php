<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Official Transmittal &amp; Receipt Package - {{ $transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'TRN') }}</title>
    <style>
        @page {
            margin: 0.8in;
            size: a4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #111827;
            line-height: 1.25;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .page-break {
            page-break-after: always;
            clear: both;
        }

        .transmittal-doc-page {
            width: 100% !important;
            max-width: 100% !important;
            min-height: auto !important;
            height: auto !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            box-shadow: none !important;
            display: block !important;
        }

        #transmittal-cover-page {
            page-break-after: always !important;
            page-break-inside: avoid !important;
            min-height: 880px;
            position: relative;
            font-family: 'Times New Roman', Georgia, serif !important;
        }
        #transmittal-cover-page h1 {
            font-size: 24px !important;
            line-height: 1.2 !important;
        }
        #transmittal-cover-page div,
        #transmittal-cover-page p {
            font-size: 11.5px !important;
            line-height: 1.45 !important;
        }
        #transmittal-cover-page .tm-footer-code {
            font-size: 9.5px !important;
        }

        #transmittal-preview-pdf {
            page-break-after: avoid !important;
            page-break-inside: avoid !important;
            min-height: 880px;
            position: relative;
        }
        .tm-corp-header {
            width: 100% !important;
            text-align: center !important;
            margin: 0 auto 14px auto !important;
        }
        .tm-corp-header table {
            width: 100% !important;
            margin: 0 auto !important;
            text-align: center !important;
        }
        .tm-corp-header td {
            text-align: center !important;
        }
        .tm-corp-logo {
            width: 125px !important;
            max-width: 125px !important;
            max-height: 65px !important;
            margin: 0 auto 4px auto !important;
            display: inline-block !important;
        }
        .tm-corp-name {
            font-family: Georgia, 'Times New Roman', serif !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            line-height: 1.15 !important;
            text-align: center !important;
        }
        .tm-corp-line {
            font-family: Georgia, 'Times New Roman', serif !important;
            font-size: 9.5px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            text-align: center !important;
        }
        .tm-title {
            text-align: center !important;
            margin-top: 12px !important;
            margin-bottom: 12px !important;
        }
        .tm-title-main {
            font-size: 20px !important;
            font-weight: 800 !important;
            color: #0037a6 !important;
            font-family: Georgia, 'Times New Roman', serif !important;
        }
        .tm-section-title {
            font-size: 13px !important;
            font-weight: 800 !important;
            margin: 10px 0 6px !important;
            text-align: center !important;
            color: #0037a6 !important;
            font-family: Georgia, 'Times New Roman', serif !important;
        }
        .tm-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-bottom: 12px !important;
            font-size: 8px !important;
        }
        .tm-table th {
            background: #0b3db8 !important;
            color: #ffffff !important;
            border: 1px solid #9ca3af !important;
            padding: 4px 5px !important;
            font-size: 8px !important;
        }
        .tm-table td {
            border: 1px solid #9ca3af !important;
            padding: 4px 5px !important;
            font-size: 8px !important;
            vertical-align: top !important;
        }
        .tm-top-block {
            margin-bottom: 8px !important;
            font-size: 8.5px !important;
        }
        .tm-top-row {
            display: table !important;
            width: 100% !important;
            margin-bottom: 4px !important;
        }
        .tm-top-row > div {
            display: table-cell !important;
            vertical-align: bottom !important;
        }
        .tm-top-row .tm-label {
            width: 55px !important;
            font-size: 9px !important;
            font-weight: 700 !important;
            color: #0037a6 !important;
            font-family: Georgia, 'Times New Roman', serif !important;
        }
        .tm-top-row-first .tm-label:first-child {
            width: 55px !important;
        }
        .tm-top-row-first .tm-date-label {
            width: 40px !important;
            text-align: right !important;
            padding-right: 6px !important;
        }
        .tm-top-row-first .tm-date {
            width: 110px !important;
        }
        .tm-top-row .tm-line {
            border-bottom: 1px solid #9ca3af !important;
            padding-bottom: 1px !important;
        }
        .tm-meta-grid {
            display: table !important;
            width: 100% !important;
            margin-bottom: 8px !important;
            font-size: 8px !important;
        }
        .tm-meta-grid > div {
            display: inline-block !important;
            width: 48% !important;
            margin-bottom: 3px !important;
            vertical-align: top !important;
        }
        .tm-signatures {
            display: table !important;
            width: 100% !important;
            margin-top: 8px !important;
            font-size: 8.5px !important;
            page-break-inside: avoid !important;
        }
        .tm-sign-col {
            display: table-cell !important;
            width: 50% !important;
            padding-right: 15px !important;
            vertical-align: top !important;
        }
        .tm-sign-col:last-child {
            padding-right: 0 !important;
            padding-left: 15px !important;
        }
        .tm-sign-label {
            margin-bottom: 14px !important;
            font-size: 8.5px !important;
        }
        .tm-sign-line {
            border-bottom: 1px solid #9ca3af !important;
            min-height: 14px !important;
            font-size: 8.5px !important;
        }
        .tm-sign-gap {
            margin-top: 14px !important;
            margin-bottom: 14px !important;
        }
        .tm-sign-gap-sm {
            margin-top: 12px !important;
            margin-bottom: 14px !important;
        }
        .tm-footer-code {
            position: absolute !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            font-size: 8.5px !important;
            border-top: 1px solid #cbd5e1 !important;
            padding-top: 4px !important;
        }

        /* PAGE 3: RECEIPT PAGE STYLING */
        .receipt-page-container {
            min-height: 880px;
            position: relative;
            padding-top: 8px;
        }
        .rcpt-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .rcpt-header-left {
            vertical-align: top;
        }
        .rcpt-header-right {
            text-align: right;
            vertical-align: top;
            font-size: 8px;
            line-height: 1.35;
        }
        .rcpt-company-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .rcpt-company-sub {
            font-size: 9px;
            color: #475569;
            margin-top: 2px;
        }
        .rcpt-title-banner {
            text-align: center;
            margin: 8px 0 12px 0;
            padding: 8px 10px;
            border-radius: 4px;
        }
        .rcpt-title {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .rcpt-subtitle {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        .rcpt-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .rcpt-info-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 4px;
        }
        .rcpt-info-table td:first-child {
            padding-left: 0;
        }
        .rcpt-info-table td:last-child {
            padding-right: 0;
        }
        .rcpt-info-box {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            background: #ffffff;
            font-size: 8px;
            line-height: 1.5;
        }
        .rcpt-section-title {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            margin: 12px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #0f172a;
            letter-spacing: 0.3px;
        }
        .rcpt-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
        }
        .rcpt-details-table td {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            line-height: 1.35;
        }
        .rcpt-details-table .rcpt-label-col {
            width: 160px;
            font-weight: bold;
            background: #f8fafc;
            color: #0f172a;
        }
        .rcpt-signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 8px;
        }
        .rcpt-signatures td {
            width: 25%;
            vertical-align: top;
            padding-right: 8px;
        }
        .rcpt-signatures td:last-child {
            padding-right: 0;
        }
        .rcpt-sign-label {
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .rcpt-sign-line {
            border-bottom: 1.5px solid #475569;
            padding-bottom: 2px;
            font-weight: bold;
            color: #0f172a;
            min-height: 14px;
        }
        .rcpt-sign-datetime {
            font-size: 7px;
            color: #64748b;
            margin-top: 3px;
        }
        .rcpt-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
            font-size: 7.5px;
            color: #64748b;
            display: table;
            width: 100%;
        }

        /* PAGE 4: ATTACHMENTS & EVIDENTIARY MANIFEST */
        .manifest-page {
            min-height: 880px;
            position: relative;
            padding-top: 8px;
        }
        .manifest-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .manifest-sub {
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .manifest-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 8px;
        }
        .manifest-table th {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 5px 7px;
            border: 1px solid #1e3a8a;
        }
        .manifest-table td {
            padding: 5px 7px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
    </style>
</head>
<body>

@php
    $preview = false;
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
    if (is_file($logoLocalPath)) {
        $mime = mime_content_type($logoLocalPath) ?: 'image/jpeg';
        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoLocalPath));
    }

    // Receipt-specific variables
    $receiptDate = $receipt->received_at ? $receipt->received_at->format('Y-m-d') : ($transmittal->transmittal_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $receivedAt = $receipt->received_at ? $receipt->received_at->format('Y-m-d H:i:s') : 'N/A';
    $receiptPreparedAt = $transmittal->prepared_at ? $transmittal->prepared_at->format('Y-m-d H:i:s') : ($transmittal->created_at ? $transmittal->created_at->format('Y-m-d H:i:s') : 'N/A');
    $receiptApprovedAt = $transmittal->approved_at ? $transmittal->approved_at->format('Y-m-d H:i:s') : 'N/A';
    
    $rawDeliveryType = strtolower((string) ($transmittal->delivery_type ?? 'by_person'));
    $isElectronic = in_array($rawDeliveryType, ['electronic', 'email'], true) || filled($receipt->ip_address) || filled($receipt->user_agent);
    $receiptTypeName = $isElectronic ? 'ELECTRONIC TRANSMISSION RECEIPT (ETR)' : 'PHYSICAL TRANSMITTAL RECEIPT (PTR)';
    $receiptTypeSub = $isElectronic 
        ? 'Digital acknowledgment recorded via Secure Recipient Portal' 
        : 'Official physical handover receipt generated upon transmittal';

    $fromValue = ($transmittal->direction ?? $transmittal->mode ?? 'send') === 'send'
        ? ($transmittal->origin_office ?: $transmittal->office_name ?: config('ordo.office_name', 'JK&C INC.'))
        : ($transmittal->party_name ?: $receipt->recipient?->name ?: 'N/A');

    $toValue = ($transmittal->direction ?? $transmittal->mode ?? 'send') === 'send'
        ? ($receipt->recipient?->name ?: $transmittal->party_name ?: $transmittal->recipients->first()?->name ?: 'N/A')
        : ($transmittal->origin_office ?: $transmittal->office_name ?: config('ordo.office_name', 'JK&C INC.'));

    $approvedByText = ($transmittal->approver?->name ?? $transmittal->approved_by_name ?? 'N/A')
        . ($transmittal->approved_position ? ' (' . $transmittal->approved_position . ')' : '');

    $actions = collect([
        $transmittal->action_delivery ? 'Delivery' : null,
        $transmittal->action_pick_up ? 'Pick Up' : null,
        $transmittal->action_drop_off ? 'Drop Off' : null,
        $transmittal->action_email ? 'Email' : null,
    ])->filter()->implode(', ');
    if ($actions === '') {
        $actions = collect($transmittal->delivery_actions ?? ['email'])->map(fn ($action) => str($action)->replace('_', ' ')->title())->implode(', ') ?: '—';
    }
@endphp

{{-- =========================================================================
     PAGE 1: STRICTLY CONFIDENTIAL COVER PAGE & LEGAL NOTICE
     ========================================================================= --}}
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
        <div>Page 1 of 3</div>
    </div>
</div>

{{-- =========================================================================
     PAGE 2: OFFICIAL ACKNOWLEDGMENT RECEIPT (ETR / PTR)
     ========================================================================= --}}
<div class="receipt-page-container">
    {{-- Header --}}
    <table class="rcpt-header-table">
        <tr>
            <td class="rcpt-header-left">
                <div class="rcpt-company-name">JOHN KELLY &amp; COMPANY</div>
                <div class="rcpt-company-sub">{{ $isElectronic ? 'Electronic Transmission Receipt' : 'Physical Transmittal Receipt' }}</div>
            </td>
            <td class="rcpt-header-right">
                <div><strong>Receipt No:</strong> {{ $receipt->receipt_no ?? ($receipt->receipt_code ?? 'N/A') }}</div>
                <div><strong>Receipt Date:</strong> {{ $receiptDate }}</div>
                <div><strong>Linked Ref No:</strong> {{ $transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'N/A') }}</div>
            </td>
        </tr>
    </table>

    {{-- Title Banner --}}
    <div class="rcpt-title-banner" style="background: {{ $isElectronic ? '#f0fdf4' : '#f8fafc' }}; border: 1px solid {{ $isElectronic ? '#bbf7d0' : '#e2e8f0' }};">
        <div class="rcpt-title" style="color: {{ $isElectronic ? '#15803d' : '#0f172a' }};">
            {{ $receiptTypeName }}
        </div>
        <div class="rcpt-subtitle">{{ $receiptTypeSub }}</div>
    </div>

    {{-- Info Boxes --}}
    <table class="rcpt-info-table">
        <tr>
            <td>
                <div class="rcpt-info-box">
                    <div><strong>Mode:</strong> {{ strtoupper($transmittal->direction ?? ($transmittal->mode ?? 'SEND')) }}</div>
                    <div><strong>Office:</strong> {{ $transmittal->origin_office ?? ($transmittal->office_name ?? config('ordo.office_name', 'JK&C INC.')) }}</div>
                    <div><strong>From:</strong> {{ $fromValue }}</div>
                    <div><strong>To:</strong> {{ $toValue }}</div>
                </div>
            </td>
            <td>
                <div class="rcpt-info-box">
                    <div><strong>Delivery Type:</strong> <strong style="color: {{ $isElectronic ? '#166534' : '#1e3a8a' }};">{{ $isElectronic ? 'Electronic (Digital Portal)' : 'Physical Handover / Courier' }}</strong></div>
                    <div><strong>Recipient Email:</strong> {{ $receipt->recipient?->email ?: ($transmittal->recipient_email ?: ($transmittal->recipients->first()?->email ?: '—')) }}</div>
                    <div><strong>Workflow:</strong> {{ str($transmittal->status ?? ($transmittal->workflow_status ?? 'Accepted'))->replace('_', ' ')->title() }}</div>
                    <div><strong>Approval:</strong> {{ $transmittal->approved_at ? 'Approved' : ($transmittal->approval_status ?? 'Pending') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Section Title --}}
    <div class="rcpt-section-title">RECEIPT DETAILS</div>

    {{-- Details Table --}}
    <table class="rcpt-details-table">
        <tbody>
            <tr>
                <td class="rcpt-label-col">Address</td>
                <td>{{ $transmittal->recipient_address ?? ($transmittal->address ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Actions</td>
                <td>{{ $actions }}</td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Prepared By</td>
                <td>{{ $transmittal->prepared_by_name ?? ($transmittal->preparer?->name ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Approved By</td>
                <td>{{ filled($approvedByText) && $approvedByText !== 'N/A' ? $approvedByText : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Delivered By</td>
                <td>{{ filled($transmittal->delivered_by) && $transmittal->delivered_by !== 'N/A' ? $transmittal->delivered_by : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Received By</td>
                <td><strong>{{ $receipt->signed_name ?? ($receipt->received_by ?? ($transmittal->received_by ?? 'N/A')) }}</strong></td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Affiliated to / Company</td>
                <td>{{ filled($transmittal->processing_company) ? $transmittal->processing_company : ($transmittal->receiver_affiliation ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="rcpt-label-col">Date and Time Received</td>
                <td>{{ $receivedAt }}</td>
            </tr>
            @if ($isElectronic)
                <tr>
                    <td class="rcpt-label-col">Digital Audit Trail</td>
                    <td>
                        <div><strong>Verification Status:</strong> Digitally signed and confirmed via Secure Recipient Portal</div>
                        @if (!empty($receipt->snapshot['location']) && !str_contains(strtolower($receipt->snapshot['location']), '127.0.0.1'))
                            <div style="margin-top: 2px;"><strong>Location:</strong> {{ $receipt->snapshot['location'] }}</div>
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Signatures Grid --}}
    <table class="rcpt-signatures">
        <tr>
            <td>
                <div class="rcpt-sign-label">Prepared by:</div>
                <div class="rcpt-sign-line">{{ $transmittal->prepared_by_name ?? ($transmittal->preparer?->name ?? ' ') }}</div>
                <div class="rcpt-sign-datetime">{{ $receiptPreparedAt }}</div>
            </td>
            <td>
                <div class="rcpt-sign-label">Approved by:</div>
                <div class="rcpt-sign-line">{{ $transmittal->approver?->name ?? ($transmittal->approved_by_name ?? ' ') }}</div>
                <div class="rcpt-sign-datetime">{{ $receiptApprovedAt }}</div>
            </td>
            <td>
                <div class="rcpt-sign-label">Delivered by:</div>
                <div class="rcpt-sign-line">{{ $transmittal->delivered_by ?? ' ' }}</div>
            </td>
            <td>
                <div class="rcpt-sign-label">Received by:</div>
                <div class="rcpt-sign-line">{{ $receipt->signed_name ?? ($receipt->received_by ?? ($transmittal->received_by ?? ' ')) }}</div>
                <div class="rcpt-sign-datetime">{{ $transmittal->processing_company ?? ($transmittal->receiver_affiliation ?? ' ') }}</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="rcpt-footer">
        <div style="display: table-cell; text-align: left;">
            This document is system-generated by John Kelly &amp; Company CRM. Linked Transmittal: {{ $transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'N/A') }}.
        </div>
        <div style="display: table-cell; text-align: right; width: 140px;">
            Page 2 of 3 · Official Receipt
        </div>
    </div>
</div>

{{-- =========================================================================
     PAGE 3: OFFICIAL TRANSMITTAL FORM
     ========================================================================= --}}
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
        <div>Page 3 of 3</div>
    </div>
</div>

{{-- =========================================================================
     PAGE 4: SUPPORTING DOCUMENTS & EVIDENTIARY ATTACHMENTS MANIFEST (IF ANY)
     ========================================================================= --}}
@php
    $documents = $transmittal->documents ?? collect();
    $attachments = $transmittal->attachments ?? collect();
@endphp

@php
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $imageDocs = ($transmittal->documents ?? collect())->filter(function($doc) use ($imageExtensions) {
        $ext = strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION));
        return in_array($ext, $imageExtensions, true) && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->storage_path);
    });
@endphp

@foreach ($imageDocs as $imgDoc)
    @php
        $imgData = \Illuminate\Support\Facades\Storage::disk('local')->get($imgDoc->storage_path);
        $mime = $imgDoc->mime_type ?: 'image/jpeg';
        $base64 = 'data:' . $mime . ';base64,' . base64_encode($imgData);
    @endphp
    <div class="page-break"></div>
    <div style="min-height: 880px; position: relative; padding-top: 10px; text-align: center;">
        <div style="text-align: left; margin-bottom: 12px; border-bottom: 1.5px solid #1e3a8a; padding-bottom: 6px;">
            <div style="font-size: 14px; font-weight: bold; color: #1e3a8a; text-transform: uppercase;">
                Attached Evidentiary Document: {{ $imgDoc->original_name }}
            </div>
            <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                Category: {{ ucfirst($imgDoc->category ?? 'Attachment') }} · SHA-256: {{ $imgDoc->sha256 }} · Ref: {{ $transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'TRN') }}
            </div>
        </div>
        <div style="margin: 16px auto; max-width: 100%; text-align: center;">
            <img src="{{ $base64 }}" style="max-width: 100%; max-height: 750px; object-fit: contain; border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);" alt="{{ $imgDoc->original_name }}">
        </div>
        <div class="rcpt-footer">
            <div style="display: table-cell; text-align: left;">
                JK&amp;C Inc. Evidentiary Document Record · {{ $imgDoc->original_name }}
            </div>
            <div style="display: table-cell; text-align: right; width: 140px;">
                Attached Image File
            </div>
        </div>
    </div>
@endforeach

</body>
</html>

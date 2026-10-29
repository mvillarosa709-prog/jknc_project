@php
    $receiptDate = $receipt->received_at ? $receipt->received_at->format('Y-m-d') : ($transmittal->transmittal_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $receivedAt = $receipt->received_at ? $receipt->received_at->format('Y-m-d H:i:s') : 'N/A';
    $preparedAt = $transmittal->created_at ? $transmittal->created_at->format('Y-m-d H:i:s') : 'N/A';
    $approvedAt = $transmittal->approved_at ? $transmittal->approved_at->format('Y-m-d H:i:s') : 'N/A';
    $deliveryType = str($transmittal->delivery_type ?? 'by_person')->replace('_', ' ')->title();
    $actions = collect($transmittal->delivery_actions ?? ['email'])->map(fn ($action) => str($action)->replace('_', ' ')->title())->implode(', ') ?: 'Pick Up';
    
    $fromValue = ($transmittal->direction ?? $transmittal->mode ?? 'send') === 'send'
        ? ($transmittal->origin_office ?: $transmittal->office_name ?: config('ordo.office_name', 'JK&C INC.'))
        : ($transmittal->party_name ?: $receipt->recipient?->name ?: 'N/A');

    $toValue = ($transmittal->direction ?? $transmittal->mode ?? 'send') === 'send'
        ? ($receipt->recipient?->name ?: $transmittal->party_name ?: $transmittal->recipients->first()?->name ?: 'N/A')
        : ($transmittal->origin_office ?: $transmittal->office_name ?: config('ordo.office_name', 'JK&C INC.'));

    $approvedByText = ($transmittal->approver?->name ?? $transmittal->approved_by_name ?? 'N/A')
        . ($transmittal->approved_position ? ' (' . $transmittal->approved_position . ')' : '');

    $rawDeliveryType = strtolower((string) ($transmittal->delivery_type ?? 'by_person'));
    $isElectronic = in_array($rawDeliveryType, ['electronic', 'email'], true) || filled($receipt->ip_address) || filled($receipt->user_agent);
    $receiptTypeName = $isElectronic ? 'ELECTRONIC TRANSMISSION RECEIPT (ETR)' : 'PHYSICAL TRANSMITTAL RECEIPT (PTR)';
    $receiptTypeSub = $isElectronic 
        ? 'Digital acknowledgment recorded via Secure Recipient Portal' 
        : 'Official physical handover receipt generated upon transmittal';
@endphp

<div class="trr-sheet-card" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08); padding: 24px 28px; max-width: 960px; margin: 0 auto; color: #111827; font-family: Arial, sans-serif; font-size: 11px;">
    {{-- Header --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
        <tr>
            <td style="vertical-align: top;">
                <div style="font-size: 15px; font-weight: bold; line-height: 1.2; color: #0f172a; text-transform: uppercase;">JOHN KELLY &amp; COMPANY</div>
                <div style="font-size: 11px; color: #475569; margin-top: 2px;">{{ $isElectronic ? 'Electronic Transmission Receipt' : 'Physical Transmittal Receipt' }}</div>
            </td>
            <td style="text-align: right; vertical-align: top; font-size: 11px; line-height: 1.45; color: #1e293b;">
                <div><span style="font-weight: bold; color: #0f172a;">Receipt No:</span> {{ $receipt->receipt_no ?? ($receipt->receipt_code ?? 'N/A') }}</div>
                <div><span style="font-weight: bold; color: #0f172a;">Receipt Date:</span> {{ $receiptDate }}</div>
                <div><span style="font-weight: bold; color: #0f172a;">Linked Ref No:</span> {{ $transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'N/A') }}</div>
            </td>
        </tr>
    </table>

    {{-- Title Banner --}}
    <div style="text-align: center; margin: 6px 0 14px 0; padding: 8px 12px; background: {{ $isElectronic ? '#f0fdf4' : '#f8fafc' }}; border: 1px solid {{ $isElectronic ? '#bbf7d0' : '#e2e8f0' }}; border-radius: 6px;">
        <div style="font-size: 17px; font-weight: 800; letter-spacing: 0.5px; color: {{ $isElectronic ? '#15803d' : '#0f172a' }}; line-height: 1.2;">
            {{ $receiptTypeName }}
        </div>
        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ $receiptTypeSub }}</div>
    </div>

    {{-- Top Info Grid --}}
    <table style="width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-bottom: 14px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px 14px; background: #ffffff;">
                <div style="line-height: 1.7; font-size: 11.5px;">
                    <div><span style="font-weight: bold; color: #0f172a;">Mode:</span> {{ strtoupper($transmittal->direction ?? ($transmittal->mode ?? 'SEND')) }}</div>
                    <div><span style="font-weight: bold; color: #0f172a;">Office:</span> {{ $transmittal->origin_office ?? ($transmittal->office_name ?? config('ordo.office_name', 'JK&C INC.')) }}</div>
                    <div><span style="font-weight: bold; color: #0f172a;">From:</span> {{ $fromValue }}</div>
                    <div><span style="font-weight: bold; color: #0f172a;">To:</span> {{ $toValue }}</div>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px 14px; background: #ffffff;">
                <div style="line-height: 1.7; font-size: 11.5px;">
                    <div><span style="font-weight: bold; color: #0f172a;">Delivery Type:</span> <span style="font-weight: 700; color: {{ $isElectronic ? '#166534' : '#1e3a8a' }};">{{ $isElectronic ? 'Electronic (Digital Portal)' : 'Physical Handover / Courier' }}</span></div>
                    <div><span style="font-weight: bold; color: #0f172a;">Recipient Email:</span> {{ $receipt->recipient?->email ?: ($transmittal->recipient_email ?: ($transmittal->recipients->first()?->email ?: '—')) }}</div>
                    <div><span style="font-weight: bold; color: #0f172a;">Workflow:</span> {{ str($transmittal->status ?? ($transmittal->workflow_status ?? 'Accepted'))->replace('_', ' ')->title() }}</div>
                    <div><span style="font-weight: bold; color: #0f172a;">Approval:</span> {{ $transmittal->approved_at ? 'Approved' : ($transmittal->approval_status ?? 'Pending') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Section Title --}}
    <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin: 12px 0 6px 0; padding-bottom: 4px; border-bottom: 1.5px solid #0f172a; letter-spacing: 0.3px;">
        RECEIPT DETAILS
    </div>

    {{-- Details Table --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; border: 1px solid #cbd5e1;">
        <tbody>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Address</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ $transmittal->recipient_address ?? ($transmittal->address ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Actions</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ $actions }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Prepared By</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ $transmittal->prepared_by_name ?? ($transmittal->preparer?->name ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Approved By</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ filled($approvedByText) && $approvedByText !== 'N/A' ? $approvedByText : 'N/A' }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Delivered By</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ filled($transmittal->delivered_by) && $transmittal->delivered_by !== 'N/A' ? $transmittal->delivered_by : 'N/A' }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Received By</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px; font-weight: bold;">{{ $receipt->signed_name ?? ($receipt->received_by ?? ($transmittal->received_by ?? 'N/A')) }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Affiliated to / Company</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ filled($transmittal->processing_company) ? $transmittal->processing_company : ($transmittal->receiver_affiliation ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Date and Time Received</td>
                <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">{{ $receivedAt }}</td>
            </tr>
            @if ($isElectronic)
                <tr>
                    <td style="width: 180px; font-weight: bold; background: #f8fafc; padding: 7px 10px; border: 1px solid #cbd5e1; color: #0f172a; font-size: 11px;">Digital Audit Trail</td>
                    <td style="padding: 7px 10px; border: 1px solid #cbd5e1; color: #1e293b; font-size: 11px;">
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
    <table style="width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-top: 14px;">
        <tr>
            <td style="width: 25%; vertical-align: top;">
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 6px;">Prepared by:</div>
                <div style="border-bottom: 1.5px solid #475569; padding-bottom: 3px; font-weight: bold; font-size: 11px; color: #0f172a; min-height: 18px;">{{ $transmittal->prepared_by_name ?? ($transmittal->preparer?->name ?? ' ') }}</div>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 3px;">{{ $preparedAt }}</div>
            </td>
            <td style="width: 25%; vertical-align: top;">
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 6px;">Approved by:</div>
                <div style="border-bottom: 1.5px solid #475569; padding-bottom: 3px; font-weight: bold; font-size: 11px; color: #0f172a; min-height: 18px;">{{ $transmittal->approver?->name ?? ($transmittal->approved_by_name ?? ' ') }}</div>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 3px;">{{ $approvedAt }}</div>
            </td>
            <td style="width: 25%; vertical-align: top;">
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 6px;">Delivered by:</div>
                <div style="border-bottom: 1.5px solid #475569; padding-bottom: 3px; font-weight: bold; font-size: 11px; color: #0f172a; min-height: 18px;">{{ $transmittal->delivered_by ?? ' ' }}</div>
            </td>
            <td style="width: 25%; vertical-align: top;">
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 6px;">Received by:</div>
                <div style="border-bottom: 1.5px solid #475569; padding-bottom: 3px; font-weight: bold; font-size: 11px; color: #0f172a; min-height: 18px;">{{ $receipt->signed_name ?? ($receipt->received_by ?? ($transmittal->received_by ?? ' ')) }}</div>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 3px;">{{ $transmittal->processing_company ?? ($transmittal->receiver_affiliation ?? ' ') }}</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div style="margin-top: 18px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 9.5px; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
        <span>This document is system-generated by John Kelly &amp; Company CRM. Linked Transmittal: {{ $transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'N/A') }}.</span>
        @if ($canViewPrivateFiles ?? true)
            <a href="{{ route('transmittals.receipts.pdf', [$transmittal, $receipt->id]) }}" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 11px;">
                Download / Print PDF &rarr;
            </a>
        @endif
    </div>
</div>

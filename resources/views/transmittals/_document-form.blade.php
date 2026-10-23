@php
    $preview = $preview ?? false;
    $recipients = $transmittal?->recipients ?? collect();
    $recipient = $recipients->first();
    $direction = old('direction', $transmittal?->direction ?? 'send');
    $date = old('transmittal_date', $transmittal?->transmittal_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $office = old('origin_office', $transmittal?->origin_office ?? config('ordo.office_name'));
    $trackingNumber = $transmittal?->tracking_number ?? 'AUTO-INCREMENT';
    
    // Pinalitan natin ito para makuha ang live data mula sa form inputs kung meron man
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
    $fromLabel = $direction === 'receive' ? 'From' : 'From (Office)';
    $toLabel = $direction === 'receive' ? 'To (Office)' : 'To';
    $deliveryType = old('delivery_type', $transmittal?->delivery_type ?? 'by_person');
    $deliveryTypeLabel = str($deliveryType)->replace('_', ' ')->title();
    $actionLabels = collect($deliveryActions)->map(fn ($action) => str($action)->replace('_', ' ')->title())->implode(', ');
@endphp


<section class="ordo-document-page ordo-cover-page" aria-label="Confidential notes">
    <header class="ordo-company-heading cover-company-heading">
        <div class="ordo-company-name">John Kelly</div>
        <div class="ordo-company-brand-line"><span>&amp;</span> Company</div>
        <div class="ordo-company-legal">J K&amp;C INC.</div>
        <div class="ordo-company-registration">COMPANY REG. NO.: 20251230239000-02</div>
        <div class="ordo-company-address">3RD FLOOR UNIT 305 CEBU HOLDINGS CENTER, CEBU BUSINESS PARK, HIPODROMO, CEBU CITY</div>
    </header>
    <p class="ordo-cover-kicker">Confidential Notes · Controlled Document</p>
    <div class="ordo-cover-rule"></div>
    <p class="ordo-cover-kicker">Classification · <span data-preview-output="confidentiality">{{ strtoupper($transmittal?->confidentiality ?? 'standard') }}</span></p>
    <h1 class="ordo-cover-title">Confidential Notes</h1>
    <p data-preview-output="subject">{{ $transmittal?->subject ?: 'Transmittal subject' }}</p>
    <p class="ordo-cover-meta">
        Reference: <strong data-preview-output="tracking-number">{{ $trackingNumber }}</strong><br>
        Classification: <strong data-preview-output="confidentiality">{{ strtoupper($transmittal?->confidentiality ?? 'standard') }}</strong><br>
        Prepared by: <span data-preview-output="prepared-by">{{ $preparedBy }}</span>
    </p>
    <div class="ordo-cover-note">
        <strong>CONFIDENTIALITY NOTICE</strong>
        <p>This document package and any accompanying materials are intended solely for the named recipients. It may contain confidential or restricted information. Unauthorized review, copying, distribution, or disclosure is prohibited. If you received this package in error, notify the sender and securely delete all copies.</p>
        <p>Keep this notice with the transmittal and its attached documents. Handle the package according to its stated classification and applicable records procedures.</p>
    </div>
    @if ($transmittal?->approval_authority_code)
        <p class="ordo-cover-meta">APPROVAL AUTHORITY CODE<br><strong>{{ $transmittal->approval_authority_code }}</strong></p>
    @endif
    <footer class="ordo-form-footer"><span>JKNC-TF-GS-V.1-2025</span><span>Page 1 of 2</span></footer>
</section>

<section class="ordo-document-page ordo-form-page" aria-label="Official transmittal form">
    <header class="ordo-company-heading">
        <div class="ordo-company-name">{{ str(config('ordo.office_name'))->before('&')->trim() }}</div>
        <div class="ordo-company-brand-line"><span>&amp;</span> {{ str(config('ordo.office_name'))->after('&')->trim() }}</div>
        <div class="ordo-company-legal">J K&amp;C INC.</div>
        <div class="ordo-company-registration">COMPANY REG. NO.: 20251230239000-02</div>
        <div class="ordo-company-address">3RD FLOOR UNIT 305 CEBU HOLDINGS CENTER, CEBU BUSINESS PARK, HIPODROMO, CEBU CITY</div>
    </header>

    <h1 class="ordo-form-title">Transmittal Form</h1>

    <table class="ordo-form-identifiers">
        <tr>
            <th>Ref No</th><td data-preview-output="tracking-number">{{ $trackingNumber }}</td>
            <th>Date</th><td data-preview-output="date">{{ \Illuminate\Support\Carbon::parse($date)->format('Y-m-d') }}</td>
        </tr>
        <tr><th>Mode</th><td colspan="3" data-preview-output="direction">{{ strtoupper($direction) }}</td></tr>
        <tr><th data-preview-party="{{ $direction === 'receive' ? 'counterparty' : 'office' }}">{{ $fromLabel }}</th><td colspan="3" data-preview-output="from">{{ $from }}</td></tr>
        <tr><th data-preview-party="{{ $direction === 'receive' ? 'office' : 'counterparty' }}">{{ $toLabel }}</th><td colspan="3" data-preview-output="to">{{ $to }}</td></tr>
        <tr><th>Address</th><td colspan="3" data-preview-output="address">{{ $transmittal?->recipient_address }}</td></tr>
    </table>

    <table class="ordo-delivery-details">
        <tr>
            <td><strong>Delivery Type:</strong> <span data-preview-output="delivery-type">{{ $deliveryTypeLabel }}</span></td>
            <td><strong>Actions:</strong> <span data-preview-output="delivery-actions">{{ $actionLabels ?: '—' }}</span></td>
            <td><strong>Electronic Method:</strong></td>
        </tr>
        <tr><td colspan="3"><strong>Recipient Email:</strong> <span data-preview-output="recipient-email">{{ $recipient?->email ?: '—' }}</span></td></tr>
    </table>

    <h2 class="ordo-items-title">List of Items</h2>
    <table class="ordo-form-items">
        <thead><tr><th>No</th><th>Particular</th><th>Unique ID</th><th>Qty.</th><th>Description</th><th>Remarks</th><th>Attachment</th></tr></thead>
        <tbody data-preview-form-items>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ data_get($item, 'particular') ?: '' }}</td>
                    <td>{{ data_get($item, 'unique_id') ?: '' }}</td>
                    <td>{{ data_get($item, 'quantity') ?: '' }}</td>
                    <td>{{ data_get($item, 'description') ?: '' }}</td>
                    <td>{{ data_get($item, 'remarks') ?: '' }}</td>
                    <td>{{ data_get($item, 'document.original_name') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td>1</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ordo-form-signatures">
        <tr>
            <td><span>Prepared by:</span><strong data-preview-output="prepared-by">{{ $preparedBy }}</strong><small>{{ $preparedAt }}</small></td>
            <td><span>Delivered by:</span><strong data-preview-output="delivered-by">{{ $transmittal?->delivered_by }}</strong><small class="signature-date-placeholder" aria-hidden="true">&nbsp;</small></td>
        </tr>
        <tr>
            <td><span>Approved by:</span><strong data-preview-output="approved-by">{{ $transmittal?->approver?->name ?? $transmittal?->approved_by_name }}</strong><small class="signature-date-placeholder" aria-hidden="true">&nbsp;</small></td>
            <td><span>Received by:</span><strong data-preview-output="received-by">{{ $transmittal?->received_by }}</strong><small class="signature-date-placeholder" aria-hidden="true">&nbsp;</small></td>
        </tr>
        <tr>
            <td><span>Operations manager:</span><strong data-preview-output="operations-manager">{{ $transmittal?->operations_manager }}</strong><small class="signature-date-placeholder" aria-hidden="true">&nbsp;</small></td>
            <td><span>Processing for Company / Organization:</span><strong data-preview-output="processing-company">{{ $transmittal?->processing_company }}</strong><small class="signature-date-placeholder" aria-hidden="true">&nbsp;</small></td>
        </tr>
    </table>
    <footer class="ordo-form-footer"><span>JKNC-TF-GS-V.1-2025</span><span>Page 2 of 2</span></footer>
</section>

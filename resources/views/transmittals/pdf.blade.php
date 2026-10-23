<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 38px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e2f49; line-height: 1.35; }
        .ordo-document-page { position: relative; width: 100%; }
        .ordo-cover-page { page-break-after: always; height: 738px; padding: 82px 32px 30px; }
        .ordo-cover-brand { color: #2457a6; font-size: 15px; font-weight: bold; letter-spacing: 3px; }
        .ordo-cover-kicker, .ordo-form-label { color: #65748a; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .ordo-cover-rule { border-top: 2px solid #2457a6; margin: 20px 0 24px; }
        .ordo-cover-title { margin: 0 0 10px; color: #1d3151; font-size: 24px; }
        .ordo-cover-note { margin-top: 85px; padding: 22px 24px; border: 1px solid #c7d4e6; background: #f4f7fb; }
        .ordo-cover-note strong { color: #2457a6; font-size: 10px; letter-spacing: 1px; }
        .ordo-cover-meta { margin-top: 26px; color: #65748a; font-size: 8px; }
        .ordo-form-page { padding: 0 2px 22px; }
        .ordo-form-header { width: 100%; margin-bottom: 13px; border-collapse: collapse; }
        .ordo-form-header td { padding: 0; border: 0; vertical-align: middle; }
        .ordo-form-mark { width: 40px; height: 40px; border: 2px solid #2457a6; border-radius: 20px; color: #2457a6; text-align: center; font-family: DejaVu Sans, sans-serif; font-size: 18px; font-weight: bold; line-height: 36px; }
        .ordo-office-name { padding-left: 9px !important; color: #1d3151; font-size: 12px; font-weight: bold; }
        .ordo-office-name small { display: block; margin-top: 3px; color: #687991; font-size: 7px; font-weight: normal; letter-spacing: 1px; }
        .ordo-form-code { color: #2457a6; font-size: 8px; font-weight: bold; text-align: right; }
        .ordo-form-title { margin: 7px 0 12px; padding: 10px 12px; background: #2457a6; color: #fff; }
        .ordo-form-title strong { font-size: 14px; letter-spacing: 1px; }
        .ordo-form-title span { float: right; padding-top: 3px; font-size: 8px; }
        .ordo-form-meta, .ordo-form-parties, .ordo-form-items, .ordo-form-signatures { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .ordo-form-meta { margin-bottom: 9px; }
        .ordo-form-meta td { width: 50%; padding: 6px 8px; border: 1px solid #d9e1ed; vertical-align: top; }
        .ordo-form-meta strong, .ordo-form-parties strong, .ordo-signature-cell strong { display: block; margin-top: 3px; color: #213754; font-size: 9px; overflow-wrap: anywhere; }
        .ordo-form-parties { margin-bottom: 10px; }
        .ordo-form-parties td { width: 50%; padding: 7px 8px; border: 1px solid #d9e1ed; vertical-align: top; }
        .ordo-form-parties p { margin: 3px 0 0; color: #52647e; font-size: 8px; overflow-wrap: anywhere; }
        .ordo-form-items { margin: 4px 0 10px; font-size: 7px; }
        .ordo-form-items th { padding: 5px 3px; background: #eaf0f8; color: #304968; text-align: left; font-size: 6px; }
        .ordo-form-items td { padding: 5px 3px; border-bottom: 1px solid #d9e1ed; vertical-align: top; overflow-wrap: anywhere; }
        .ordo-form-items th:nth-child(1) { width: 4%; }
        .ordo-form-items th:nth-child(2) { width: 16%; }
        .ordo-form-items th:nth-child(3) { width: 12%; }
        .ordo-form-items th:nth-child(4) { width: 6%; }
        .ordo-form-items th:nth-child(5) { width: 24%; }
        .ordo-form-items th:nth-child(6) { width: 16%; }
        .ordo-form-items th:nth-child(7) { width: 22%; }
        .ordo-signature-heading { margin: 9px 0 5px; color: #304968; font-size: 8px; font-weight: bold; letter-spacing: .7px; }
        .ordo-form-signatures td { width: 33.33%; height: 44px; padding: 6px; border: 1px solid #d9e1ed; vertical-align: bottom; }
        .ordo-signature-cell span { display: block; color: #65748a; font-size: 6px; letter-spacing: .5px; }
        .ordo-signature-cell strong { min-height: 11px; border-bottom: 1px solid #aab8ca; font-size: 8px; font-weight: normal; }
        .ordo-form-footer { position: absolute; right: 0; bottom: 0; left: 0; padding-top: 5px; border-top: 1px solid #d9e1ed; color: #75849a; font-size: 7px; }
        .ordo-form-footer span:last-child { float: right; }
        .ordo-form-page { padding: 8px 8px 22px; color: #111; }
        .ordo-company-heading { margin: 0 auto 38px; text-align: center; color: #080808; font-family: DejaVu Serif, serif; font-weight: bold; line-height: 1.12; }
        .ordo-company-name { font-size: 16px; }
        .ordo-company-legal { margin-top: 3px; font-size: 13px; }
        .ordo-company-registration, .ordo-company-address { font-size: 8px; }
        .ordo-form-title { margin: 0 0 14px; padding: 0; background: transparent; color: #0645ad; text-align: center; font-size: 20px; font-weight: bold; }
        .ordo-company-brand-line { margin-top: -1px; font-size: 15px; }
        .ordo-company-brand-line span { color: #0645ad; font-family: DejaVu Sans, sans-serif; }
        .ordo-form-identifiers { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .ordo-form-identifiers th { width: 12%; padding: 3px 5px 3px 0; color: #0645ad; text-align: left; vertical-align: top; font-size: 9px; }
        .ordo-form-identifiers td { min-height: 17px; padding: 3px 4px; border-bottom: 1px solid #9aa3ad; overflow-wrap: anywhere; vertical-align: top; }
        .ordo-form-identifiers th:nth-child(3) { width: 8%; padding-left: 10px; }
        .ordo-delivery-details { width: 100%; margin: 13px 0 26px; border-collapse: collapse; table-layout: fixed; font-size: 8px; }
        .ordo-delivery-details td { width: 50%; padding: 3px 5px 3px 0; vertical-align: top; }
        .ordo-items-title { margin: 0 0 10px; color: #0645ad; text-align: center; font-size: 14px; }
        .ordo-form-items { width: 100%; margin: 0 0 16px; border-collapse: collapse; table-layout: fixed; font-size: 7px; }
        .ordo-form-items th { padding: 5px 3px; border: 1px solid #8f9baa; background: #0645ad; color: #fff; text-align: center; font-size: 7px; }
        .ordo-form-items td { min-height: 22px; padding: 5px 3px; border: 1px solid #aab4c1; vertical-align: top; overflow-wrap: anywhere; }
        .ordo-form-items th:nth-child(1) { width: 5%; }
        .ordo-form-items th:nth-child(2) { width: 16%; }
        .ordo-form-items th:nth-child(3) { width: 14%; }
        .ordo-form-items th:nth-child(4) { width: 6%; }
        .ordo-form-items th:nth-child(5) { width: 34%; }
        .ordo-form-items th:nth-child(6) { width: 13%; }
        .ordo-form-items th:nth-child(7) { width: 12%; }
        .ordo-form-signatures { width: 100%; margin-top: 4px; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
        .ordo-form-signatures td { width: 50%; height: 64px; padding: 4px 8px; border: 0; vertical-align: bottom; }
        .ordo-form-signatures span { display: block; margin-bottom: 13px; }
        .ordo-form-signatures strong { display: block; min-height: 14px; border-bottom: 1px solid #9aa3ad; font-size: 8px; font-weight: normal; }
        .ordo-form-signatures small { display: block; margin-top: 3px; font-size: 7px; }
        .ordo-form-footer { position: static; margin-top: 10px; padding-top: 4px; border-top: 1px solid #c4cbd4; color: #687586; font-size: 7px; }
        .ordo-form-footer { display: flex; justify-content: space-between; }
        .ordo-cover-page > .ordo-form-footer { position: absolute; right: 0; bottom: 0; left: 0; margin: 0; border: 0; }
        .signature-date-placeholder { visibility: hidden; }
        .ordo-evidence-page { page-break-before: always; }
        .ordo-evidence-page h1 { margin: 0 0 8px; color: #1d3151; font-size: 19px; }
        .ordo-evidence-page h2 { margin: 15px 0 5px; color: #304968; font-size: 10px; }
        .ordo-evidence-page p { margin: 4px 0; }
        .ordo-evidence-page table { width: 100%; margin: 5px 0 10px; border-collapse: collapse; table-layout: fixed; }
        .ordo-evidence-page th { padding: 5px; background: #eaf0f8; color: #304968; text-align: left; font-size: 7px; }
        .ordo-evidence-page td { padding: 5px; border-bottom: 1px solid #d9e1ed; vertical-align: top; font-size: 7px; overflow-wrap: anywhere; }
        .ordo-evidence-code { font-family: DejaVu Sans Mono, monospace; font-size: 6px; }
    </style>
</head>
<body>
    @include('transmittals._document-form', ['transmittal' => $transmittal, 'preview' => false])

    {{-- 
    @if ($transmittal->documents->isNotEmpty() || $transmittal->proofs->isNotEmpty() || $transmittal->receipts->isNotEmpty() || $transmittal->approved_snapshot || $transmittal->status === 'rejected')
        <section class="ordo-evidence-page">
            <div class="ordo-cover-brand">ORDO</div>
            <p class="ordo-cover-kicker">Supporting record · {{ $transmittal->tracking_number }}</p>
            <h1>Delivery and approval evidence</h1>
            @if ($transmittal->recipients->isNotEmpty())
                <h2>Recipients</h2>
                <table><thead><tr><th>Name</th><th>Organization</th><th>Email</th></tr></thead><tbody>
                    @foreach ($transmittal->recipients as $recipient)
                        <tr><td>{{ $recipient->name ?: '—' }}</td><td>{{ $recipient->organization ?: '—' }}</td><td>{{ $recipient->email }}</td></tr>
                    @endforeach
                </tbody></table>
            @endif
            @if ($transmittal->documents->isNotEmpty())
                <h2>Document register</h2>
                <table><thead><tr><th>File</th><th>Category</th><th>Type</th><th>SHA-256</th></tr></thead><tbody>
                    @foreach ($transmittal->documents as $document)
                        <tr><td>{{ $document->original_name }}</td><td>{{ ucfirst($document->category) }}</td><td>{{ $document->mime_type }}</td><td class="ordo-evidence-code">{{ $document->sha256 }}</td></tr>
                    @endforeach
                </tbody></table>
            @endif
            @if ($transmittal->proofs->isNotEmpty())
                <h2>Proof of transmission</h2>
                <table><thead><tr><th>Recipient</th><th>Channel / Status</th><th>Transmitted</th><th>Provider ID</th><th>Transaction reference</th></tr></thead><tbody>
                    @foreach ($transmittal->proofs as $proof)
                        <tr><td>{{ $proof->recipient?->email ?: '—' }}</td><td>{{ ucfirst($proof->channel) }} / {{ ucfirst($proof->status) }}</td><td>{{ $proof->transmitted_at?->format('Y-m-d H:i T') ?: '—' }}</td><td class="ordo-evidence-code">{{ $proof->provider_message_id ?: '—' }}</td><td class="ordo-evidence-code">{{ $proof->transaction_reference ?: '—' }}</td></tr>
                    @endforeach
                </tbody></table>
            @endif
            @if ($transmittal->receipts->isNotEmpty())
                <h2>Receipt acknowledgments</h2>
                <table><thead><tr><th>Recipient</th><th>Acknowledged by</th><th>TRR reference</th><th>Received</th></tr></thead><tbody>
                    @foreach ($transmittal->receipts as $receipt)
                        <tr><td>{{ $receipt->recipient?->email ?: '—' }}</td><td>{{ $receipt->signed_name }}</td><td class="ordo-evidence-code">{{ $receipt->receipt_code }}</td><td>{{ $receipt->received_at?->format('Y-m-d H:i T') ?: '—' }}</td></tr>
                    @endforeach
                </tbody></table>
            @endif
            @if ($transmittal->approved_snapshot)
                <h2>Approval record</h2>
                <p>Approved by {{ $transmittal->approver?->name ?? $transmittal->approved_by_name ?? 'administrator' }} on {{ $transmittal->approved_at?->format('F j, Y H:i T') ?: '—' }}.</p>
                <p class="ordo-evidence-code">Authority code: {{ $transmittal->approval_authority_code ?: '—' }}</p>
            @endif
            @if ($transmittal->status === 'rejected')
                <h2>Administrator decision</h2>
                <p>Rejected by {{ $transmittal->rejector?->name ?? 'administrator' }} on {{ $transmittal->rejected_at?->format('F j, Y H:i T') ?: '—' }}.</p>
                <p>{{ $transmittal->rejection_reason }}</p>
            @endif
            <p class="ordo-cover-meta">Generated {{ now()->format('F j, Y H:i T') }} · Refer to the live audit history for subsequent activity.</p>
        </section>
    @endif
    --}}
</body>
</html>
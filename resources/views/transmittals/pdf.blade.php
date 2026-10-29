<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 1in;
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
            min-height: 900px;
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
        .tm-cover-ref-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 6px !important;
        }
        .tm-cover-ref-table td {
            padding: 3px 4px !important;
            vertical-align: top !important;
            font-size: 9px !important;
        }
        .tm-cover-ref-table td.tm-ref-lbl {
            width: 120px !important;
            font-weight: 700 !important;
            color: #475569 !important;
        }
        #transmittal-preview-pdf {
            page-break-after: avoid !important;
            page-break-inside: avoid !important;
            min-height: 900px;
            position: relative;
        }
        .tm-corp-header {
            width: 100% !important;
            text-align: center !important;
            margin: 0 auto 16px auto !important;
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
            width: 130px !important;
            max-width: 130px !important;
            max-height: 70px !important;
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
            margin-top: 14px !important;
            margin-bottom: 14px !important;
        }
        .tm-title-main {
            font-size: 20px !important;
            font-weight: 800 !important;
            color: #0037a6 !important;
            font-family: Georgia, 'Times New Roman', serif !important;
        }
        .tm-section-title {
            font-size: 14px !important;
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
            margin-top: 6px !important;
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
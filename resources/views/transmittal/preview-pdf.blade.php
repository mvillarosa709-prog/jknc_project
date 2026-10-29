<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transmittal Form</title>
    <style>
        @page { size: A4 portrait; margin: 1in; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111827; margin: 0; padding: 0; }
        .page { width: 100%; min-height: 240mm; }
        .corp-header { text-align: center; margin-top: 0; margin-bottom: 0.75in; color: #000; }
        .corp-logo { width: 155px; max-width: 155px; max-height: 95px; object-fit: contain; margin: 0 auto 8px auto; display: block; }
        .corp-name { font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 700; text-transform: uppercase; line-height: 1.15; }
        .corp-reg, .corp-address { font-family: Georgia, 'Times New Roman', serif; font-size: 12px; font-weight: 700; line-height: 1.25; text-transform: uppercase; }
        .title { text-align: center; font-size: 25px; font-weight: 800; margin-top: 0.5in; margin-bottom: 0.5in; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; }
        .top-row { display: table; width: 100%; margin-bottom: 6px; }
        .cell { display: table-cell; vertical-align: bottom; }
        .label { font-weight: 700; width: 70px; color: #0037a6; font-family: Georgia, 'Times New Roman', serif; font-size: 13px; }
        .line { border-bottom: 1px solid #9ca3af; min-height: 16px; padding-bottom: 2px; word-break: break-word; }
        .date-label { width: 42px; text-align: right; padding-right: 8px; }
        .date-line { width: 130px; }
        .meta { display: table; width: 100%; margin: 14px 0; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; width: 50%; padding: 3px 14px 3px 0; }
        .section-title { font-weight: 800; margin: 18px 0 10px; text-align: center; color: #0037a6; font-size: 18px; font-family: Georgia, 'Times New Roman', serif; }
        table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.items th, table.items td { border: 1px solid #9ca3af; padding: 5px; vertical-align: top; word-break: break-word; }
        table.items th { font-weight: 700; background: #0b3db8; color: #fff; text-align: center; }
        .signatures { display: table; width: 100%; margin-top: 24px; }
        .sign-col { display: table-cell; width: 50%; padding-right: 28px; vertical-align: top; }
        .sign-label { margin-bottom: 20px; }
        .sign-line { border-bottom: 1px solid #9ca3af; min-height: 18px; padding-bottom: 2px; font-weight: 700; }
        .sign-sub { margin-top: 4px; }
        .gap { margin-top: 28px; }
        .small-gap { margin-top: 20px; }
    </style>
</head>
<body>
@php
    $ctx = $corporateContext ?? [];
    $companyName = $ctx['companyName'] ?? 'John Kelly & Company';
    $secRegNo = $ctx['secRegNo'] ?? '';
    $principalAddress = $ctx['principalAddress'] ?? '';
    $logoSrc = $ctx['logoBase64'] ?? $ctx['logoUrl'] ?? asset('images/FINAL_LOGO.jpg');
    $approvedByName = $approvedByDisplay ?? $transmittal->approved_by_name;
    $footerLeft = ($transmittal->transmittal_no ?? 'TRANSMITTAL') . ' - ' . $companyName;
@endphp

<div class="page">
    <div class="corp-header">
        @if(!empty($logoSrc))
            <img src="{{ $logoSrc }}" class="corp-logo" alt="Company Logo">
        @endif
        <div class="corp-name">{{ $companyName }}</div>
        <div class="corp-reg">CONTROL NO.: {{ $transmittal->transmittal_no ?? ($transmittal->tracking_number ?? 'AUTO-INCREMENT') }}</div>
        @if($principalAddress !== '')
            <div class="corp-address">{{ $principalAddress }}</div>
        @endif
    </div>

    <div class="title">Transmittal Form</div>

    <div class="top-row">
        <div class="cell label">Ref No</div>
        <div class="cell line">{{ $transmittal->transmittal_no }}</div>
        <div class="cell label date-label">Date</div>
        <div class="cell line date-line">{{ optional($transmittal->transmittal_date)->format('Y-m-d') }}</div>
    </div>
    <div class="top-row"><div class="cell label">Mode</div><div class="cell line">{{ $transmittal->mode }}</div></div>
    <div class="top-row"><div class="cell label">From</div><div class="cell line">{{ $transmittal->from_value }}</div></div>
    <div class="top-row"><div class="cell label">To</div><div class="cell line">{{ $transmittal->to_value }}</div></div>
    <div class="top-row"><div class="cell label">Address</div><div class="cell line">{{ $transmittal->address }}</div></div>

    <div class="meta">
        <div class="meta-row">
            <div class="meta-cell"><strong>Delivery Type:</strong> {{ $transmittal->delivery_summary ?: '—' }}</div>
            <div class="meta-cell"><strong>Actions:</strong> {{ $transmittal->actions_summary ?: '—' }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-cell"><strong>Recipient Email:</strong> {{ $transmittal->recipient_email ?: '—' }}</div>
            <div class="meta-cell"><strong>Electronic Method:</strong> {{ $transmittal->electronic_method ?: '—' }}</div>
        </div>
    </div>

    <div class="section-title">List of Items</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 34px;">No</th>
                <th style="width: 100px;">Particular</th>
                <th style="width: 85px;">Unique ID</th>
                <th style="width: 40px;">Qty.</th>
                <th>Description</th>
                <th style="width: 85px;">Remarks</th>
                <th style="width: 90px;">Attachment</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transmittal->items as $item)
                <tr>
                    <td>{{ $item->item_no }}</td>
                    <td>{{ $item->particular }}</td>
                    <td>{{ $item->unique_id }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->remarks }}</td>
                    <td>{{ $item->attachment_path ? basename($item->attachment_path) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color:#6b7280;">No items listed.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($transmittal->attachments->count())
        <div class="section-title" style="font-size:15px; margin-top:14px;">Supporting Attachments</div>
        <ul style="margin:0 0 0 16px; padding:0;">
            @foreach($transmittal->attachments as $attachment)
                <li>{{ $attachment->original_name ?: basename($attachment->file_path) }}</li>
            @endforeach
        </ul>
    @endif

    <div class="signatures">
        <div class="sign-col">
            <div class="sign-label">Prepared by:</div>
            <div class="sign-line">{{ $transmittal->prepared_by_name ?: ' ' }}</div>
            <div class="sign-sub">{{ optional($transmittal->prepared_at)->format('Y-m-d h:i A') }}</div>
            <div class="sign-label gap">Approved by:</div>
            <div class="sign-line">{{ $approvedByName ?: ' ' }}</div>
            <div class="sign-label small-gap">Operations Manager:</div>
            <div class="sign-line">{{ $transmittal->approved_position ?: ' ' }}</div>
        </div>
        <div class="sign-col">
            <div class="sign-label">Delivered by:</div>
            <div class="sign-line">{{ $transmittal->delivered_by ?: ' ' }}</div>
            <div class="sign-label gap">Received by:</div>
            <div class="sign-line">{{ $transmittal->received_by ?: ' ' }}</div>
            <div class="sign-label small-gap">Processing for Company / Organization:</div>
            <div class="sign-line">{{ $transmittal->receiver_affiliation ?: ' ' }}</div>
        </div>
    </div>
</div>

<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font("Times-Roman", "normal");
        $bold = $fontMetrics->get_font("Times-Bold", "bold");
        $pdf->line(36, 805, 559, 805, [0, 0, 0], 0.5);
        $pdf->page_text(36, 812, "{{ addslashes($footerLeft) }}", $font, 8, [0, 0, 0]);
        $pdf->page_text(502, 812, "Page {PAGE_NUM} of {PAGE_COUNT}", $bold, 8, [0, 0, 0]);
    }
</script>
</body>
</html>

@php
    $proofMeta = $proof->provider_metadata ?? [];
    $senderLocation = $proofMeta['sender_location'] ?? (($proofMeta['location_source'] ?? null) === 'user_entered' ? ($proofMeta['location'] ?? null) : null);
    $senderDevice = ($proofMeta['device_source'] ?? null) === 'user_entered' ? ($proofMeta['device_category'] ?? null) : null;
    $proofType = $proofMeta['proof_type'] ?? ($proof->document_id ? 'delivery_receipt' : 'other');
    $proofTypeLabel = [
        'delivery_receipt' => 'Delivery Receipt',
        'email_confirmation' => 'Email Confirmation',
        'courier_receipt' => 'Courier Receipt',
        'other' => 'Other',
    ][$proofType] ?? 'Other';
    $proofEditData = [
        'recipient_id' => $proof->recipient_id,
        'proof_type' => $proofMeta['proof_type'] ?? ($proof->document_id ? 'delivery_receipt' : 'other'),
        'reference_number' => $proofMeta['reference_number'] ?? $proof->transaction_reference,
        'receiver_name' => $proofMeta['receiver_name'] ?? $proof->recipient?->name,
        'receiver_email' => $proofMeta['receiver_email'] ?? $proof->recipient?->email,
        'channel' => $proof->channel,
        'sender_name' => $proofMeta['sender_name'] ?? '',
        'sender_position' => $proofMeta['sender_position'] ?? '',
        'acknowledged_by' => $proofMeta['acknowledged_by'] ?? '',
        'location' => $senderLocation ?? '',
        'device_category' => $proofMeta['device_category'] ?? '',
        'device' => ($proofMeta['device_source'] ?? null) === 'user_entered' ? ($proofMeta['device'] ?? '') : '',
        'notes' => $proofMeta['notes'] ?? '',
        'transmission_at' => $proof->transmitted_at?->format('Y-m-d\\TH:i'),
        'acknowledged_at' => $proof->acknowledged_at?->format('Y-m-d\\TH:i'),
    ];
    $proofViewData = [
        'proof_type' => $proofTypeLabel,
        'reference_number' => $proofMeta['reference_number'] ?? $proof->transaction_reference,
        'receiver_name' => $proofMeta['receiver_name'] ?? $proof->recipient?->name,
        'receiver_email' => $proofMeta['receiver_email'] ?? $proof->recipient?->email,
        'channel' => str($proof->channel)->replace('_', ' ')->title(),
        'status' => ucfirst($proof->status),
        'transmitted_at' => $proof->transmitted_at?->format('M j, Y H:i:s'),
        'acknowledged_at' => $proof->acknowledged_at?->format('M j, Y H:i:s'),
        'sender_name' => $proofMeta['sender_name'] ?? null,
        'sender_position' => $proofMeta['sender_position'] ?? null,
        'location' => $senderLocation,
        'device' => trim(implode(' ', array_filter([
            $senderDevice ? str($senderDevice)->replace('_', ' ')->title()->toString() : null,
            ($proofMeta['device_source'] ?? null) === 'user_entered' ? ($proofMeta['device'] ?? null) : null,
        ]))),
        'acknowledged_by' => $proofMeta['acknowledged_by'] ?? $proof->recipient?->receipt?->signed_name,
        'source' => $proof->document?->original_name ?? $proof->provider_message_id ?? 'Transmission event',
        'source_url' => $proof->document ? route('transmittals.documents.download', [$transmittal, $proof->document]) : null,
        'notes' => $proofMeta['notes'] ?? null,
    ];
@endphp
<tr>
    <td style="width: 140px; text-align: left;"><button type="button" class="proof-type-view" data-view-proof data-proof-view="{{ json_encode($proofViewData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}" style="padding: 0; border: 0; background: transparent; color: #21426c; font: inherit; text-align: left; text-decoration: underline; cursor: pointer; font-weight: 600;">{{ $proofTypeLabel }}</button></td>
    
    <td style="width: 130px; text-align: left; font-family: monospace; font-size: 12px;">{{ $proofMeta['reference_number'] ?? $proof->transaction_reference ?? $transmittal->tracking_number }}</td>
    <td style="width: 160px; text-align: left;">{{ $proofMeta['receiver_name'] ?? ($proof->recipient?->name ?: $proof->recipient?->email ?: '—') }}</td>
    <td class="proof-method-cell" style="min-width: 200px; text-align: left;">
        <strong class="proof-method-label" style="display: block; margin-bottom: 2px;">{{ str($proof->channel)->replace('_', ' ')->title() ?: 'Not provided' }}</strong>
        <small style="display: block; color: #64748b; font-size: 11px;">Sender: {{ ($proofMeta['sender_name'] ?? null) ?: 'Not provided' }}@if (!empty($proofMeta['sender_position'])) · {{ $proofMeta['sender_position'] }}@endif</small>
        <small style="display: block; color: #64748b; font-size: 11px;">Location: {{ $senderLocation ?: 'Not provided' }}</small>
        <small style="display: block; color: #64748b; font-size: 11px;">Device: {{ $senderDevice ? str($senderDevice)->replace('_', ' ')->title() : 'Not provided' }}@if (($proofMeta['device_source'] ?? null) === 'user_entered' && !empty($proofMeta['device'])) · {{ $proofMeta['device'] }}@endif</small>
        @if (!empty($proofMeta['recorded_by_name']) || !empty($proofMeta['notes']) || !empty($proofMeta['source']))
            <details style="margin-top: 4px; font-size: 10.5px;"><summary style="cursor: pointer; color: #2563eb;">Source, notes &amp; audit</summary>
                @if (!empty($proofMeta['source']))<small style="display: block;">Source: {{ str($proofMeta['source'])->replace('_', ' ')->title() }}</small>@endif
                @if (!empty($proofMeta['notes']))<small style="display: block;">Notes: {{ $proofMeta['notes'] }}</small>@endif
                @if (!empty($proofMeta['recorded_by_name']))<small style="display: block;">Recorded / confirmed by {{ $proofMeta['recorded_by_name'] }} at {{ $proofMeta['recorded_at'] ?? '—' }}</small>@endif
                @foreach (($proofMeta['edit_history'] ?? []) as $edit)
                    <small style="display: block;">Edited by {{ $edit['edited_by_name'] ?? 'Authorized user' }} at {{ $edit['edited_at'] ?? '—' }}: {{ $edit['reason'] ?? '' }}</small>
                @endforeach
            </details>
        @endif
        @if (auth()->check() && auth()->user()->can('uploadProof', $transmittal))
            <button type="button" class="proof-edit-button" data-edit-proof
                data-proof="{{ json_encode($proofEditData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"
                data-update-url="{{ route('transmittals.proofs.update', [$transmittal, $proof]) }}">Edit details</button>
        @endif
    </td>
    <td style="width: 110px; text-align: center;">
        <span class="status-badge status-{{ strtolower($proof->status) }}" style="display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;">
            {{ ucfirst($proof->status) }}
        </span>
    </td>
    <td style="width: 150px; text-align: left; font-size: 12px; white-space: nowrap;">
        {{ $proof->transmitted_at?->format('M j, Y H:i:s') ?: '—' }}
        @if (($proofMeta['transmission_at_source'] ?? null) === 'user_entered')<small style="display: block; color: #64748b;">(entered)</small>@endif
        @if (!empty($proofMeta['recorded_at']))<small style="display: block; color: #64748b;">Recorded: {{ $proofMeta['recorded_at'] }}</small>@endif
    </td>
    <td style="width: 150px; text-align: left; font-size: 12px; white-space: nowrap;">
        {{ $proof->acknowledged_at?->format('M j, Y H:i:s') ?: '—' }}
        @php($ackName = $proofMeta['acknowledged_by'] ?? $proof->recipient?->receipt?->signed_name)
        @if ($ackName)<small style="display: block; color: #64748b; font-weight: 500;">{{ $ackName }}</small>@endif
    </td>
    
    <td style="width: 160px; text-align: left; word-break: break-all;">
        @if ($proof->document)
            <a href="{{ route('transmittals.documents.download', [$transmittal, $proof->document]) }}" style="color: #2563eb; text-decoration: underline; font-weight: 500;">
                {{ $proof->document->original_name }}
            </a>
        @else
            <span style="color: #64748b;">{{ $proof->provider_message_id ?: 'Transmission event' }}</span>
        @endif
    </td>
</tr>

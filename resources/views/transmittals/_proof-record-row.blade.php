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
    <td><button type="button" class="proof-type-view" data-view-proof data-proof-view="{{ json_encode($proofViewData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}" style="padding: 0; border: 0; background: transparent; color: #21426c; font: inherit; text-align: left; text-decoration: underline; cursor: pointer;">{{ $proofTypeLabel }}</button></td>
    
    <td>{{ $proofMeta['reference_number'] ?? $proof->transaction_reference ?? $transmittal->tracking_number }}</td>
    <td>{{ $proofMeta['receiver_name'] ?? ($proof->recipient?->name ?: $proof->recipient?->email ?: '—') }}</td>
    <td class="proof-method-cell">
        <strong class="proof-method-label">{{ str($proof->channel)->replace('_', ' ')->title() ?: 'Not provided' }}</strong>
        <small>Sender: {{ ($proofMeta['sender_name'] ?? null) ?: 'Not provided' }}@if (!empty($proofMeta['sender_position'])) · {{ $proofMeta['sender_position'] }}@endif</small>
        <small>Location: {{ $senderLocation ?: 'Not provided' }}</small>
        <small>Device: {{ $senderDevice ? str($senderDevice)->replace('_', ' ')->title() : 'Not provided' }}@if (($proofMeta['device_source'] ?? null) === 'user_entered' && !empty($proofMeta['device'])) · {{ $proofMeta['device'] }}@endif</small>
        @if (!empty($proofMeta['recorded_by_name']) || !empty($proofMeta['notes']) || !empty($proofMeta['source']))
            <details><summary>Source, notes &amp; audit</summary>
                @if (!empty($proofMeta['source']))<small>Source: {{ str($proofMeta['source'])->replace('_', ' ')->title() }}</small>@endif
                @if (!empty($proofMeta['notes']))<small>Notes: {{ $proofMeta['notes'] }}</small>@endif
                @if (!empty($proofMeta['recorded_by_name']))<small>Recorded / confirmed by {{ $proofMeta['recorded_by_name'] }} at {{ $proofMeta['recorded_at'] ?? '—' }}</small>@endif
                @foreach (($proofMeta['edit_history'] ?? []) as $edit)
                    <small>Edited by {{ $edit['edited_by_name'] ?? 'Authorized user' }} at {{ $edit['edited_at'] ?? '—' }}: {{ $edit['reason'] ?? '' }}</small>
                @endforeach
            </details>
        @endif
        @if (auth()->check() && auth()->user()->can('uploadProof', $transmittal))
            <button type="button" class="proof-edit-button" data-edit-proof
                data-proof="{{ json_encode($proofEditData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"
                data-update-url="{{ route('transmittals.proofs.update', [$transmittal, $proof]) }}">Edit details</button>
        @endif
    </td>
    <td>{{ ucfirst($proof->status) }}</td>
    <td>{{ $proof->transmitted_at?->format('M j, Y H:i:s') ?: '—' }}
        @if (($proofMeta['transmission_at_source'] ?? null) === 'user_entered')<small>(entered)</small>@endif
        @if (!empty($proofMeta['recorded_at']))<small>Recorded: {{ $proofMeta['recorded_at'] }}</small>@endif
    </td>
    <td>{{ $proof->acknowledged_at?->format('M j, Y H:i:s') ?: '—' }}
        @php($ackName = $proofMeta['acknowledged_by'] ?? $proof->recipient?->receipt?->signed_name)
        @if ($ackName)<small>{{ $ackName }}</small>@endif
    </td>
    
    {{-- 2. Ginawa nating direktang clickable link ang pangalan ng PDF file --}}
    <td>
        @if ($proof->document)
            <a href="{{ route('transmittals.documents.download', [$transmittal, $proof->document]) }}">
                {{ $proof->document->original_name }}
            </a>
        @else
            {{ $proof->provider_message_id ?: 'Transmission event' }}
        @endif
    </td>
    
    {{-- 3. Tinanggal na natin ang Actions column dito --}}
</tr>

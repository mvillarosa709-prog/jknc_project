@extends('layouts.transmittal')

@section('title', $transmittal->tracking_number)
@section('eyebrow', $transmittal->tracking_number)
@section('content')
    <div class="workspace-heading">
        <div><a class="back-link" href="{{ route('transmittals.index') }}">â† All transmittals</a><div class="title-line"><h1>{{ $transmittal->subject }}</h1><span class="status-badge status-{{ $transmittal->status }}">{{ str_replace('_', ' ', $transmittal->status) }}</span></div><p class="tracking-subtitle">{{ $transmittal->tracking_number }} <span>Â·</span> Submitted {{ $transmittal->created_at->format('M j, Y') }} by {{ $transmittal->preparer?->name ?? 'Public submitter' }}</p></div>
    </div>
    <section class="workspace-panel">
        <nav class="tabs" aria-label="Transmittal workspace sections">
            <button class="tab active" type="button" data-tab="draft">Draft of Transmittal</button>
            <button class="tab" type="button" data-tab="transmittal">Transmittal</button>
            <button class="tab" type="button" data-tab="receipt">Receipt <span class="tab-count">{{ $transmittal->receipts->count() }}</span></button>
            <button class="tab" type="button" data-tab="proof">Proof of Transmission <span class="tab-count">{{ $transmittal->proofs->count() }}</span></button>
            <button class="tab" type="button" data-tab="attachments">Attachments <span class="tab-count">{{ $transmittal->documents->count() }}</span></button>
            <button class="tab" type="button" data-tab="history">History</button>
        </nav>
        <div class="workspace-tab-layout">
        <div class="workspace-tab-panels">
        <div class="tab-content active" id="tab-draft">
            <div class="workspace-columns">
                <div class="detail-column">
                    <div class="detail-card"><span class="eyebrow">PURPOSE</span><p class="purpose-text">{{ $transmittal->purpose }}</p>
                        @if($transmittal->external_reference)<p class="muted detail-meta-line">Document reference Â· {{ $transmittal->external_reference }}</p>@endif
                        <p class="muted detail-meta-line">{{ strtoupper($transmittal->direction ?? 'send') }} Â· {{ $transmittal->transmittal_date?->format('M j, Y') ?? $transmittal->created_at->format('M j, Y') }} Â· {{ $transmittal->origin_office ?: config('ordo.office_name') }}</p>
                        @if($transmittal->related_company)<p class="muted detail-meta-line">Related company Â· {{ $transmittal->related_company }}</p>@endif
                        @if($transmittal->recipient_address)<p class="muted detail-meta-line">Address Â· {{ $transmittal->recipient_address }}</p>@endif
                    </div>
                    <div class="detail-card"><span class="eyebrow">LIST OF ITEMS</span>
                        <div class="table-scroll"><table class="data-table"><thead><tr><th>PARTICULAR</th><th>UNIQUE ID</th><th>QTY</th><th>DESCRIPTION</th><th>REMARKS</th></tr></thead><tbody>
                            @forelse ($transmittal->items as $item)
                                <tr><td>{{ $item->particular ?: 'â€”' }}</td><td>{{ $item->unique_id ?: 'â€”' }}</td><td>{{ $item->quantity ?: 'â€”' }}</td><td>{{ $item->description ?: 'â€”' }}</td><td>{{ $item->remarks ?: 'â€”' }}</td></tr>
                            @empty
                                <tr><td colspan="5">No items recorded.</td></tr>
                            @endforelse
                        </tbody></table></div>
                    </div>
                    @if ($transmittal->status === 'rejected')
                        <div class="detail-card rejection-card"><span class="eyebrow">ADMINISTRATOR DECISION</span><strong>Rejected by {{ $transmittal->rejector?->name ?? 'administrator' }}</strong><p>{{ $transmittal->rejection_reason }}</p><small>{{ $transmittal->rejected_at?->format('M j, Y Â· H:i') }}</small></div>
                    @endif
                    <div class="detail-card" id="recipient-routing"><div class="detail-card-heading"><div><span class="eyebrow">RECIPIENT ROUTING</span><p class="muted">External recipients and acknowledgment status</p></div></div>
                        <div class="recipient-table">
                            @foreach ($transmittal->recipients as $recipient)
                                <div class="recipient-display"><span class="avatar pale">{{ mb_substr($recipient->name ?: $recipient->email, 0, 1) }}</span><div class="recipient-ident"><strong>{{ $recipient->name ?: $recipient->email }}</strong><small>{{ $recipient->email }}@if($recipient->organization) Â· {{ $recipient->organization }}@endif</small></div><span class="receipt-state {{ $recipient->receipt ? 'received' : '' }}">{{ $recipient->acknowledgment_revoked_at ? 'Link revoked' : ($recipient->receipt ? 'Received' : ($recipient->sent_at ? 'Awaiting receipt' : 'Not sent')) }}</span>
                                    @if (auth()->user()?->canApproveTransmittals() && $recipient->sent_at && ! $recipient->acknowledgment_revoked_at)
                                        <form method="post" action="{{ route('transmittals.recipients.revoke-link', [$transmittal, $recipient]) }}" class="reject-form">
                                            @csrf
                                            <input name="reason" placeholder="Reason to revoke link" required maxlength="1000" aria-label="Reason to revoke recipient link">
                                            <button class="button subtle" type="submit">Revoke link</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($transmittal->approved_snapshot)
                        <div class="detail-card frozen-card"><span class="eyebrow">FROZEN APPROVAL SNAPSHOT</span><p>This approved record is immutable. Its authority code verifies the exact approved contents.</p><code>{{ $transmittal->authorityCode?->code }}</code><small>SHA-256 Â· {{ $transmittal->authorityCode?->snapshot_hash }}</small></div>
                    @endif
                    @if ($canEditDraft || auth()->user()?->canApproveTransmittals())
                        <details class="detail-card workflow-admin-controls"><summary>Approval and record controls</summary>
                            @if ($canEditDraft)<a class="action-panel-button" href="{{ route('transmittals.edit', $transmittal) }}">Edit draft</a>@endif
                            @can('approve', $transmittal)
                                <form method="post" action="{{ route('transmittals.approve', $transmittal) }}">@csrf<button class="button primary" type="submit">Approve &amp; freeze</button></form>
                                <form method="post" action="{{ route('transmittals.reject', $transmittal) }}" class="reject-form">@csrf<label class="sr-only" for="rejection-reason">Reason for rejection</label><input id="rejection-reason" name="rejection_reason" placeholder="Reason for rejection" required maxlength="2000"><button class="button reject-button" type="submit">Reject</button></form>
                            @endcan
                            @if ($canManageLifecycle && $transmittal->status === 'pending_approval')<form method="post" action="{{ route('transmittals.recall', $transmittal) }}" class="reject-form">@csrf<input name="reason" placeholder="Reason for recall" required maxlength="2000" aria-label="Reason for recall"><button class="button subtle" type="submit">Recall submission</button></form>@endif
                            @if (auth()->user()?->canApproveTransmittals())<form method="post" action="{{ route('transmittals.destroy', $transmittal) }}" class="reject-form">@csrf @method('DELETE')<input name="reason" placeholder="Reason to move to Deleted" required maxlength="2000" aria-label="Reason to delete"><button class="button subtle" type="submit">Move to Deleted</button></form>@endif
                        </details>
                    @endif
                </div>
                <aside class="detail-column">
                    <div class="detail-card meta-card"><span class="eyebrow">TRANSMITTAL INFORMATION</span><dl><div><dt>Ref No</dt><dd>{{ $transmittal->tracking_number }}</dd></div><div><dt>Date</dt><dd>{{ $transmittal->transmittal_date?->format('Y-m-d') ?? '—' }}</dd></div><div><dt>Mode</dt><dd>{{ strtoupper($transmittal->direction ?? 'send') }}</dd></div><div><dt>From</dt><dd>{{ $transmittal->origin_office ?: config('ordo.office_name') }}</dd></div><div><dt>To</dt><dd>{{ $transmittal->recipients->first()?->name ?: '—' }}</dd></div><div><dt>Address</dt><dd>{{ $transmittal->recipient_address ?: '—' }}</dd></div><div><dt>Recipient Email</dt><dd>{{ $transmittal->recipients->first()?->email ?: '—' }}</dd></div><div><dt>Workflow</dt><dd>{{ str($transmittal->status)->replace('_', ' ')->title() }}</dd></div><div><dt>Approval</dt><dd>{{ $transmittal->approved_at ? 'Approved' : 'Pending' }}</dd></div><div><dt>Delivery type</dt><dd>{{ str($transmittal->delivery_type ?? 'by_person')->replace('_', ' ')->title() }}</dd></div><div><dt>Delivery actions</dt><dd>{{ collect($transmittal->delivery_actions ?? [])->map(fn ($action) => str($action)->replace('_', ' ')->title())->implode(', ') ?: 'â€”' }}</dd></div><div><dt>Prepared by</dt><dd>{{ $transmittal->prepared_by_name ?: ($transmittal->preparer?->name ?? 'Public submitter') }}</dd></div><div><dt>Approved by</dt><dd>{{ $transmittal->approver?->name ?? $transmittal->approved_by_name ?? 'Not yet approved' }}</dd></div><div><dt>Operations manager</dt><dd>{{ $transmittal->operations_manager ?: 'â€”' }}</dd></div><div><dt>Delivered / received by</dt><dd>{{ $transmittal->delivered_by ?: 'â€”' }} / {{ $transmittal->received_by ?: 'â€”' }}</dd></div><div><dt>Processing for</dt><dd>{{ $transmittal->processing_company ?: 'â€”' }}</dd></div><div><dt>Approval date</dt><dd>{{ $transmittal->approved_at?->format('M j, Y Â· H:i') ?? 'â€”' }}</dd></div></dl></div>
                    @php($latestReceipt = $transmittal->receipts->sortByDesc('received_at')->first())
                    <div class="detail-card meta-card"><span class="eyebrow">RECEIPT INFORMATION</span><dl><div><dt>Receipt No</dt><dd>{{ $latestReceipt?->receipt_code ?? 'Not yet generated' }}</dd></div><div><dt>Receipt File</dt><dd>@if ($latestReceipt && $canViewPrivateFiles)<a href="{{ route('transmittals.receipts.pdf', [$transmittal, $latestReceipt->id]) }}">Download TRR</a>@else{{ $latestReceipt ? 'Available to authorized users' : 'Not available' }}@endif</dd></div></dl></div>
                    <div class="detail-card meta-card"><span class="eyebrow">ATTACHMENT SUMMARY</span><dl><div><dt>With Files</dt><dd>{{ $transmittal->documents->count() }}</dd></div><div><dt>Total Items</dt><dd>{{ $transmittal->items->count() }}</dd></div></dl></div>                    <div class="approval-progress"><span class="eyebrow">WORKFLOW</span><div class="workflow-step done"><span>âœ“</span><div><strong>Submitted</strong><small>{{ $transmittal->created_at->format('M j, Y') }}</small></div></div><div class="workflow-step {{ $transmittal->approved_at || $transmittal->status === 'rejected' ? 'done' : '' }}"><span>{{ $transmittal->approved_at ? 'âœ“' : ($transmittal->status === 'rejected' ? 'Ã—' : '2') }}</span><div><strong>Administrator review</strong><small>{{ $transmittal->approved_at ? $transmittal->approved_at->format('M j, Y') : ($transmittal->status === 'rejected' ? 'Rejected' : 'Awaiting administrator decision') }}</small></div></div><div class="workflow-step {{ $transmittal->proofs->isNotEmpty() ? 'done' : '' }}"><span>{{ $transmittal->proofs->isNotEmpty() ? 'âœ“' : '3' }}</span><div><strong>Transmission</strong><small>{{ $transmittal->proofs->count() }} proof record(s)</small></div></div><div class="workflow-step {{ $transmittal->receipts->isNotEmpty() ? 'done' : '' }}"><span>{{ $transmittal->receipts->isNotEmpty() ? 'âœ“' : '4' }}</span><div><strong>Receipt</strong><small>{{ $transmittal->receipts->count() }} received</small></div></div></div>
                </aside>
            </div>
        </div>
        <div class="tab-content" id="tab-transmittal">
            <div class="section-title tab-title"><span class="step-number">02</span><div><h2>Transmittal</h2><p class="muted">Official two-page transmittal form.</p></div></div>
            <div class="add-preview-pages workspace-document-pages">
                @include('transmittals._document-form', ['transmittal' => $transmittal, 'preview' => true])
            </div>
        </div>
        <div class="tab-content" id="tab-receipt">
    <div class="section-title tab-title"><span class="step-number">03</span><div><h2>Transmittal Receipt (TRR)</h2><p class="muted">Official acknowledgment receipt linked to this transmittal.</p></div></div>
    @forelse ($transmittal->receipts as $receipt)
        <article class="workspace-receipt-document">
            <p class="eyebrow">OFFICIAL RECORD</p>
            <h2>OFFICIAL TRANSMITTAL RECEIPT (TRR)</h2>
            <p class="receipt-reference">TRN Reference: <strong>{{ $transmittal->tracking_number }}</strong></p>
            <dl>
                <div><dt>TRR Reference</dt><dd>{{ $receipt->receipt_code }}</dd></div>
                <div><dt>Recipient</dt><dd>{{ $receipt->recipient?->name ?: $receipt->recipient?->email }}</dd></div>
                <div><dt>Acknowledged by</dt><dd>{{ $receipt->signed_name }}</dd></div>
                <div><dt>Date / time received</dt><dd>{{ $receipt->received_at->format('M j, Y · H:i:s') }}</dd></div>
                <div><dt>Comments</dt><dd>{{ $receipt->comments ?: 'No comments provided.' }}</dd></div>
            </dl>
            <p class="receipt-legal">This is a Transmittal Receipt (TRR) linked to the TRN. It is a receipt record and not the Transmittal Form.</p>
            @if ($canViewPrivateFiles)<a href="{{ route('transmittals.receipts.pdf', [$transmittal, $receipt->id]) }}">View / Print official TRR</a>@endif
        </article>
    @empty
        <article class="workspace-receipt-document receipt-empty"><p class="eyebrow">OFFICIAL RECEIPT</p><h2>TRANSMITTAL RECEIPT (TRR)</h2><p class="receipt-reference">TRN Reference: <strong>{{ $transmittal->tracking_number }}</strong></p><p>A receipt will appear here after the recipient acknowledges this transmittal.</p><p class="receipt-legal">This tab is for the Transmittal Receipt (TRR), not the Transmittal Form.</p></article>
    @endforelse
</div>
        <div class="tab-content" id="tab-proof">
    <div class="section-title tab-title"><span class="step-number">04</span><div><h2>Proof of Transmission</h2><p class="muted">Each proof, sent, viewed, acknowledged, or manual event is recorded under this TRN.</p></div></div>
    <div class="proof-record-notice">Each upload, send, view, acknowledgment, or manually recorded event becomes its own transmission record.</div>
    <div class="proof-record-heading"><div><h3>Transmission records</h3><p>Multiple records can belong to one TRN.</p></div><button class="button primary" type="button" data-open-proof-action>+ Add proof record</button></div>
    <div class="table-scroll proof-record-table-scroll"><table class="data-table proof-record-table"><thead><tr><th>Type</th><th>Reference No.</th><th>Recipient / Receiver</th><th>Transmission Method</th><th>Status</th><th>Date Sent / Uploaded</th><th>Date Acknowledged</th><th>Source / Proof</th><th>Actions</th></tr></thead><tbody>
        @forelse ($transmittal->proofs as $proof)
            @include('transmittals._proof-record-row', ['proof' => $proof])
        @empty
            <tr><td class="proof-empty-cell" colspan="9"><strong>No proof records yet</strong><span>Use Upload proof in the Action Center to add evidence.</span></td></tr>
        @endforelse
    </tbody></table></div>
</div>
        <div class="tab-content" id="tab-attachments">
            <div class="section-title tab-title"><span class="step-number">04</span><div><h2>Attachments</h2><p class="muted">Private source documents are available to authorized workspace users and recipients with a valid secure link.</p></div></div>
            @if ($canViewPrivateFiles)
                <div class="attachment-list">@forelse ($transmittal->documents as $document)<a class="attachment-row" href="{{ route('transmittals.documents.download', [$transmittal, $document]) }}"><span class="file-type">{{ strtoupper(pathinfo($document->original_name, PATHINFO_EXTENSION)) }}</span><span class="attachment-copy"><strong>{{ $document->original_name }}</strong><small>{{ ucfirst($document->category) }} Â· {{ number_format($document->size_bytes / 1024, 1) }} KB Â· SHA-256 {{ substr($document->sha256, 0, 16) }}â€¦</small></span><span class="download-mark">â†“</span></a>@empty<div class="empty-inline">There are no attachments on this transmittal.</div>@endforelse</div>
            @else
                <div class="empty-inline">Attachments are private. Use the recipient link from the notification or sign in to an authorized workspace.</div>
            @endif
        </div>
        <div class="tab-content" id="tab-history">
            <div class="section-title tab-title"><span class="step-number">05</span><div><h2>Activity history</h2><p class="muted">A chronological audit trail of record actions.</p></div></div>
            <div class="timeline">@forelse ($transmittal->histories as $entry)<div class="timeline-entry"><span class="timeline-mark"></span><div><strong>{{ ucfirst(str_replace('_', ' ', $entry->action)) }}</strong><p>{{ $entry->description }}</p><small>{{ $entry->actor?->name ?? 'External recipient' }} Â· {{ $entry->created_at->format('M j, Y Â· H:i:s') }}</small></div></div>@empty<div class="empty-inline">No history available.</div>@endforelse</div>
        </div>
        </div>
        @include('transmittals._action-center', ['transmittal' => $transmittal])
        </div>
    </section>
@endsection

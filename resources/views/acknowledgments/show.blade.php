@extends('layouts.guest')

@section('title', 'Receipt acknowledgment')
@section('content')
    <span class="eyebrow">SECURE RECIPIENT PORTAL</span>
    @if ($recipient->receipt)
        <div class="ack-success-icon">✓</div><h1>Receipt confirmed</h1><p class="muted">Your acknowledgment is securely recorded against <strong>{{ $recipient->transmittal->tracking_number }}</strong>.</p>
        <div class="ack-receipt"><span>RECEIPT CODE</span><code>{{ $recipient->receipt->receipt_code }}</code><small>Recorded {{ $recipient->receipt->received_at->format('M j, Y · H:i T') }}</small></div>
        <a class="button subtle wide" href="{{ route('acknowledgments.receipts.pdf', ['token' => request()->route('token'), 'receipt' => $recipient->receipt->id]) }}">Download official TRR</a>
    @else
        <h1>Confirm receipt</h1><p class="muted">Confirm that you have received this document package. This link is private to you and expires {{ $recipient->acknowledgment_expires_at->format('M j, Y') }}.</p>
        <div class="ack-summary"><span class="eyebrow">TRANSMITTAL</span><strong>{{ $recipient->transmittal->tracking_number }}</strong><p>{{ $recipient->transmittal->subject }}</p><small>Sent to {{ $recipient->email }}</small></div>
        <form method="post" action="{{ route('acknowledgments.store', ['token' => request()->route('token')]) }}" class="form-stack">
            @csrf
            <label>Your full name <span class="required">*</span><input name="signed_name" value="{{ old('signed_name', $recipient->name) }}" autocomplete="name" required maxlength="160"></label>
            <label>Where are you acknowledging from? <span class="field-hint">Optional</span><input name="location" value="{{ old('location') }}" autocomplete="address-level2" maxlength="200" placeholder="City / location"></label>
            <label>Comment <span class="field-hint">Optional</span><textarea name="comments" rows="3" maxlength="2000" placeholder="Add a note for the sender"></textarea></label>
            <label class="check-label"><input type="checkbox" required> I confirm receipt of this transmittal and its listed materials.</label>
            <button class="button primary wide" type="submit">Confirm receipt <span>→</span></button>
        </form>
        <p class="external-privacy">This acknowledgment records your name, submission time, location if provided, network address, and device/browser for audit purposes.</p>
    @endif
    <section class="ack-summary">
        <span class="eyebrow">PRIVATE ATTACHMENTS</span>
        @forelse ($recipient->transmittal->documents as $document)
            <p><a href="{{ route('acknowledgments.documents.download', ['token' => request()->route('token'), 'document' => $document->id]) }}">{{ $document->original_name }}</a></p>
        @empty
            <p>No files were attached to this transmittal.</p>
        @endforelse
    </section>
@endsection

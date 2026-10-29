@extends('layouts.guest')

@section('title', 'Acknowledge Transmittal ' . $recipient->transmittal->tracking_number)

@section('content')
@php
    $transmittal = $recipient->transmittal;
    $hasAttachments = $transmittal->documents->isNotEmpty();
@endphp

<div class="portal-grid">
    <!-- Left Column: Acknowledgment & Signature Form -->
    <div class="portal-card portal-action-card">
        @if ($recipient->receipt)
            <div class="success-box">
                <div class="success-icon-badge">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Receipt Confirmed</h1>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 16px;">
                    Your official acknowledgment has been securely recorded against transmittal <strong>{{ $transmittal->tracking_number }}</strong>.
                </p>

                <div class="receipt-code-badge">
                    <span>OFFICIAL RECEIPT NUMBER</span>
                    <code>{{ $recipient->receipt->receipt_no ?? ('TRR-' . str_pad((string) $recipient->receipt->id, 5, '0', STR_PAD_LEFT)) }}</code>
                </div>

                <div style="font-size: 12px; color: #64748b; margin-bottom: 16px;">
                    <i class="fa-regular fa-clock" style="margin-right: 4px;"></i>
                    Recorded {{ $recipient->receipt->received_at->format('M j, Y · h:i A') }}
                </div>

                {{-- Expiry Notice in Success Box --}}
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; font-size: 11.5px; color: #1e40af; line-height: 1.4; text-align: left;">
                    <i class="fa-solid fa-circle-info" style="color: #2563eb; margin-right: 4px;"></i>
                    <strong>Notice:</strong> This link and attached files will expire in <strong>72 hours</strong>. Please download and store your copy of the receipt and all attachments now.
                </div>

                <a class="btn-download-trr" href="{{ route('acknowledgments.receipts.pdf', ['token' => request()->route('token'), 'receipt' => $recipient->receipt->id]) }}">
                    <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i>
                    Download Complete Transmittal &amp; Receipt Package (PDF)
                </a>
            </div>
        @else
            <div class="portal-card-header">
                <div style="font-size: 11px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Official Transmission</div>
                <h1>Confirm Receipt</h1>
                <p>Please review the transmittal document on the right and complete this acknowledgment form. This secure link expires on <strong>{{ $recipient->acknowledgment_expires_at->format('M j, Y · h:i A') }}</strong>.</p>
            </div>

            {{-- 72-Hour Expiry Warning & Document Download Notice --}}
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; display: flex; gap: 10px; align-items: flex-start;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #d97706; font-size: 16px; margin-top: 2px;"></i>
                <div style="font-size: 12px; color: #92400e; line-height: 1.45;">
                    <strong style="color: #78350f;">Important Notice:</strong> This secure portal, link, and attached evidentiary documents will expire in <strong>72 hours (3 days)</strong>. Please make sure to download and save your personal copy of the documents and receipt immediately upon confirmation.
                </div>
            </div>

            <!-- Transmittal Meta Info -->
            <div class="transmittal-meta-pill">
                <span class="eyebrow">Transmittal Reference</span>
                <span class="tracking-no">{{ $transmittal->tracking_number }}</span>
                <p class="subject">{{ $transmittal->subject }}</p>
                <div class="recipient-email">
                    <i class="fa-regular fa-envelope"></i>
                    <span>{{ $recipient->email }}</span>
                </div>
            </div>

            <form method="post" action="{{ route('acknowledgments.store', ['token' => request()->route('token')]) }}">
                @csrf
                <div class="form-group">
                    <label for="signed_name">
                        Your Full Name <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="signed_name" 
                        id="signed_name" 
                        class="form-control" 
                        value="{{ old('signed_name', $recipient->name) }}" 
                        autocomplete="name" 
                        required 
                        maxlength="160"
                        placeholder="Enter your full name"
                    >
                </div>

                <div class="form-group">
                    <label for="comments">
                        Comments / Remarks <span class="hint">(Optional)</span>
                    </label>
                    <textarea 
                        name="comments" 
                        id="comments" 
                        class="form-control" 
                        rows="3" 
                        maxlength="2000" 
                        placeholder="Add any comments or receiving remarks"
                    >{{ old('comments') }}</textarea>
                </div>

                <label class="checkbox-container">
                    <input type="checkbox" name="confirmation_check" required>
                    <span>I hereby confirm receipt of this official transmittal and all accompanying documents listed.</span>
                </label>

                <button class="btn-acknowledge" type="submit">
                    <span>Acknowledge Transmission</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <p class="audit-footer-note">
                <i class="fa-solid fa-shield-halved" style="margin-right: 4px;"></i>
                Audit Trail Recorded: IP address, device metadata, timestamp, and signature are permanently attached to this acknowledgment record.
            </p>
        @endif
    </div>

    <!-- Right Column: Official Transmittal Preview (Identical to Draft of Transmittal) -->
    <section class="document-preview-pane draft-preview-pane portal-preview-pane" aria-label="Official Document Preview">
        <div class="preview-pane-header">
            <span>OFFICIAL TRANSMITTAL DOCUMENT PREVIEW</span>
            <span>2 PAGES</span>
        </div>

        <div class="add-preview-pages workspace-document-pages">
            @include('transmittals._document-form', [
                'transmittal' => $transmittal,
                'preview' => true
            ])
        </div>

        @if ($hasAttachments)
            <div class="portal-attachments-card">
                <div class="portal-attachments-header">
                    <div>
                        <span class="portal-attachments-eyebrow">Attached Evidentiary Files</span>
                        <h3>Supporting Documents ({{ $transmittal->documents->count() }})</h3>
                    </div>
                </div>
                <div class="portal-attachments-list">
                    @foreach ($transmittal->documents as $doc)
                        @php
                            $docExt = strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION));
                            $docUrl = route('acknowledgments.documents.download', ['token' => request()->route('token'), 'document' => $doc->id]);
                            $rawBytes = $doc->file_size ?: ($doc->size_bytes ?? 0);
                            if ($rawBytes == 0 && !empty($doc->storage_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->storage_path)) {
                                $rawBytes = \Illuminate\Support\Facades\Storage::disk('local')->size($doc->storage_path);
                            }
                            $displayKb = $rawBytes > 0 ? number_format($rawBytes / 1024, 1) . ' KB' : '—';
                        @endphp
                        <div class="portal-attachment-item">
                            <div class="portal-attachment-info">
                                <i class="fa-regular fa-file-pdf portal-file-icon"></i>
                                <div>
                                    <strong>{{ $doc->original_name }}</strong>
                                    <small>{{ strtoupper($docExt) }} · {{ $displayKb }}</small>
                                </div>
                            </div>
                            <a href="{{ $docUrl }}" class="btn-portal-download" target="_blank" download>
                                <i class="fa-solid fa-download"></i>
                                Download
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>
@endsection

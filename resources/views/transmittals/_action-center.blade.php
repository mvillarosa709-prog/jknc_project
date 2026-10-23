@php
    $draftWorkspace = $draftWorkspace ?? false;
    $user = auth()->user();
    $canUploadProof = ($canUploadProof ?? false) || ($user?->can('uploadProof', $transmittal) ?? false);
    $canRequestAcknowledgment = $user?->can('transmit', $transmittal) ?? false;
    $canPrintPdf = ($user?->can('view', $transmittal) ?? false) || $draftWorkspace;
    $canCancelTransmittal = ($draftWorkspace && in_array($transmittal->status, ['draft', 'rejected'], true))
        || ($user?->canApproveTransmittals() && in_array($transmittal->status, ['draft', 'pending_approval', 'rejected'], true));
@endphp
<aside class="transmittal-action-panel" data-action-panel aria-label="Transmittal actions" hidden>
    <h2>Action Center</h2>
    <p class="muted">Operational actions for this TRN</p>
    <button class="action-panel-button" type="button" data-toggle-proof-form @unless ($canUploadProof) data-action-feedback="The preparer can add draft proof; otherwise an authorized approver can add proof after submission." @endunless>Upload proof</button>
    <button class="action-panel-button" type="button" data-open-proof-file @unless ($canUploadProof) data-action-feedback="Proof upload is unavailable for your account or this transmittal status." @endunless>Take photo / video</button>
    @if ($canUploadProof)
        <div class="proof-modal" data-proof-modal hidden>
            <button class="proof-modal-backdrop" type="button" data-close-proof-modal aria-label="Close proof record form"></button>
            <section class="proof-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="proof-modal-title">
                <header><h2 id="proof-modal-title">Upload proof of transmission</h2><button class="proof-modal-close" type="button" data-close-proof-modal aria-label="Close">×</button></header>
                <form method="post" action="{{ route('transmittals.proofs.store', $transmittal) }}" enctype="multipart/form-data" class="action-proof-form form-stack" id="proof-upload-form" data-proof-return="{{ $draftWorkspace ? 'workspace' : 'show' }}">
                    @csrf
                    <input type="hidden" name="_method" value="">
                    <input type="hidden" name="return_to" value="{{ $draftWorkspace ? 'workspace' : 'show' }}">
                    <div class="proof-entry-modes" role="tablist" aria-label="Choose how to add proof">
                        <button type="button" class="active" data-proof-mode="manual" role="tab" aria-selected="true">Fill in manually</button>
                        <button type="button" data-proof-mode="ocr" role="tab" aria-selected="false">Upload file with OCR</button>
                    </div>
                    <p class="proof-mode-hint" data-proof-mode-hint>Enter the proof details below, then attach the supporting file.</p>
                    <div class="proof-file-upload">
                        <label>Evidence file <span class="required">*</span><input name="proof" id="proof-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.mp4,.mov,.webm" capture="environment" required></label>
                        <small data-proof-file-hint>Choose a file to attach. Switch to OCR mode to read its details automatically.</small>
                        <button class="button subtle" type="button" id="analyze-proof" data-analyze-url="{{ route('transmittals.analyze') }}" hidden>Analyze and fill form</button>
                        <p class="ocr-message" id="proof-ocr-message" role="status" aria-live="polite"></p>
                    </div>
                    <div class="proof-modal-fields" data-proof-fields>
                        <label>Proof type <select name="proof_type" required><option value="delivery_receipt">Delivery receipt</option><option value="email_confirmation">Email confirmation</option><option value="courier_receipt">Courier receipt</option><option value="other">Other</option></select></label>
                        <label>Reference number<input name="reference_number" id="proof-reference-number" maxlength="120" placeholder="Optional reference"></label>
                        <label>Saved recipient<select name="recipient_id" id="proof-recipient"><option value="">Use receiver name below</option>@foreach ($transmittal->recipients as $recipient)<option value="{{ $recipient->id }}" data-recipient-name="{{ $recipient->name ?: $recipient->email }}">{{ $recipient->name ?: $recipient->email }}</option>@endforeach</select></label>
                        <label>Recipient / receiver name<input name="receiver_name" id="proof-receiver-name" required maxlength="160" value="{{ $transmittal->recipients->first()?->name ?: '' }}" placeholder="Enter receiver name"></label>
                        <label>Recipient email<input name="receiver_email" id="proof-receiver-email" type="email" maxlength="254" placeholder="Optional email"></label>
                        <label>Transmission Method <select name="channel" required>@foreach (['email', 'in_person', 'courier', 'registered_mail', 'portal', 'manual', 'other'] as $channel)<option value="{{ $channel }}">{{ ['portal' => 'Online Portal', 'registered_mail' => 'Registered Mail', 'manual' => 'Manual entry'][$channel] ?? str($channel)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                        <label>Status <select id="proof-status" disabled><option value="uploaded">Uploaded</option><option value="acknowledged">Acknowledged</option></select><small>Adding an acknowledgment date records this proof as Acknowledged.</small></label>
                        <label>Who are you? / Sender <input name="sender_name" id="proof-sender-name" required maxlength="160" value="{{ auth()->user()?->name }}" placeholder="Person responsible for this transmission"></label>
                        <label>Sender Position / Office <input name="sender_position" id="proof-sender-position" maxlength="160" placeholder="Optional; enter if applicable"></label>
                        <label>Acknowledged by<input name="acknowledged_by" id="proof-acknowledged-by" maxlength="160" placeholder="Name on acknowledgment"></label>
                        <label>Where are you at? / Sender Location<input name="location" id="proof-location" maxlength="200" placeholder="Enter location manually; no GPS is collected"><small>Manually entered; not location-verified.</small></label>
                        <label>What device are you using? <select name="device_category" id="proof-device-category" required><option value="">Select device type</option><option value="desktop">Desktop Computer</option><option value="laptop">Laptop</option><option value="mobile">Mobile Phone</option><option value="tablet">Tablet</option><option value="other">Other</option></select></label>
                        <label>Device details<input name="device" id="proof-device" maxlength="300" placeholder="Optional model or device details"><small>Entered by the sender; browser metadata is recorded separately.</small></label>
                        <label class="proof-notes-field">Additional proof details<textarea name="notes" id="proof-notes" rows="2" maxlength="2000" placeholder="Additional notes from the evidence"></textarea></label>
                        <label>Transmission Date and Time<input name="transmission_at" id="proof-transmission-at" type="datetime-local" value="{{ now()->format('Y-m-d\\TH:i') }}" required></label>
                        <label>Date acknowledged<input name="acknowledged_at" id="proof-acknowledged-at" type="datetime-local"></label>
                    </div>
                    <label class="proof-edit-reason" data-proof-edit-reason hidden>Edit reason <textarea name="edit_reason" rows="2" maxlength="500" placeholder="Why is this record being corrected?"></textarea></label>
                    <input type="hidden" name="ocr_token" id="proof-ocr-token" value="">
                    <div class="proof-ocr-review" data-proof-review hidden><strong>Review the extracted details</strong><p>OCR suggestions are editable. Correct any wrong or incomplete values before confirming and saving the record.</p></div>
                    <label class="check-label" data-proof-confirm hidden><input type="checkbox" name="ocr_reviewed" value="1"> I reviewed and confirm these proof details.</label>
                    <footer><button class="button subtle" type="button" data-close-proof-modal>Cancel</button><button class="button primary" type="submit">Save proof record</button></footer>
                </form>
            </section>
        </div>
    @endif
    <div class="proof-camera-modal" data-camera-modal hidden>
        <button class="proof-camera-backdrop" type="button" data-close-camera aria-label="Close camera"></button>
        <section class="proof-camera-dialog" role="dialog" aria-modal="true" aria-labelledby="proof-camera-title">
            <header><h2 id="proof-camera-title">Take photo / video</h2><button class="proof-modal-close" type="button" data-close-camera aria-label="Close">×</button></header>
            <video id="proof-camera-preview" autoplay playsinline muted></video>
            <canvas id="proof-camera-canvas" hidden></canvas>
            <p class="camera-message" id="proof-camera-message" role="status" aria-live="polite">Allow camera access, then capture a photo or record a short video.</p>
            <div class="camera-controls">
                <button class="button subtle" type="button" data-camera-photo disabled>Capture photo</button>
                <button class="button subtle" type="button" data-camera-record disabled>Start video</button>
                <button class="button danger" type="button" data-camera-stop hidden>Stop recording</button>
                <button class="button primary" type="button" data-camera-use disabled>Use captured file</button>
                <button class="camera-file-fallback" type="button" data-camera-browse>Choose file instead</button>
            </div>
        </section>
    </div>
    @if ($canRequestAcknowledgment)
        <form method="post" action="{{ route('transmittals.transmit', $transmittal) }}">@csrf<button class="action-panel-button" type="submit">Request acknowledgment</button></form>
    @else
        <button class="action-panel-button" type="button" data-action-feedback="Request acknowledgment is available after approval and for an authorized sender.">Request acknowledgment</button>
    @endif
    <p class="action-panel-feedback" data-action-feedback-message role="status" aria-live="polite" hidden></p>
    @if ($draftWorkspace)
        <button class="action-panel-button" type="button" data-open-recipient-fields>Open recipient view</button>
    @else
        <button class="action-panel-button" type="button" data-open-recipient-view>Open recipient view</button>
    @endif
    <button class="action-panel-button" type="button" data-action-tab="receipt">Generate / view receipt</button>
    @if ($canPrintPdf)<a class="action-panel-button" href="{{ route('transmittals.pdf', $transmittal) }}">Print / save PDF</a>@else<button class="action-panel-button" type="button" disabled>Print / save PDF</button>@endif
    @if ($canCancelTransmittal)
        <form method="post" action="{{ route('transmittals.cancel', $transmittal) }}" data-cancel-confirm>@csrf<input type="hidden" name="reason" value=""><button class="action-panel-button danger" type="submit">Cancel transmittal</button></form>
    @else
        <button class="action-panel-button danger" type="button" disabled>Cancel transmittal</button>
    @endif
</aside>

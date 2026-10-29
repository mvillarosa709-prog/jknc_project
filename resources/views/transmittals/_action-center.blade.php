@php
    $draftWorkspace = $draftWorkspace ?? false;
    $user = auth()->user();
    $canUploadProof = ($canUploadProof ?? false) || ($user?->can('uploadProof', $transmittal) ?? false);
    $canRequestAcknowledgment = $user?->can('transmit', $transmittal) ?? false;
    $canPrintPdf = ($canPrintPdf ?? false) || ($user !== null) || ($canViewPrivateFiles ?? false) || ($canManageLifecycle ?? false);
    $canCancelTransmittal = ($canManageLifecycle ?? false)
        && in_array($transmittal->status, ['draft', 'pending_approval', 'rejected'], true);
@endphp
<aside class="transmittal-action-panel" data-action-panel aria-label="Transmittal actions" hidden>
    <header class="action-panel-heading"><h2>Action Center</h2><button type="button" class="action-panel-close" data-close-action-panel aria-label="Close Action Center">&times;</button></header>
    <p class="muted">Operational actions for this TRN</p>
    <button class="action-panel-button" type="button" data-action-tab="proof" data-toggle-proof-form @unless ($canUploadProof) data-action-feedback="The preparer can add draft proof; otherwise an authorized approver can add proof after submission." @endunless>Upload proof</button>
    <button class="action-panel-button" type="button" data-action-tab="proof" data-open-proof-file @unless ($canUploadProof) data-action-feedback="Proof upload is unavailable for your account or this transmittal status." @endunless>Take photo / video</button>
    @if ($canRequestAcknowledgment)
        <form method="post" action="{{ route('transmittals.transmit', $transmittal) }}" data-action-tab="transmittal" data-confirm="Send the acknowledgment request email to the transmittal recipient(s)?">@csrf<button class="action-panel-button" type="submit">Request acknowledgment</button></form>
    @else
        <button class="action-panel-button" type="button" data-action-tab="transmittal" data-action-feedback="Request acknowledgment is available after approval and for an authorized sender.">Request acknowledgment</button>
    @endif
    <p class="action-panel-feedback" data-action-feedback-message role="status" aria-live="polite" hidden></p>
    <button class="action-panel-button" type="button" data-action-tab="receipt" data-open-receipt-tab>Open receipt view</button>
    @if ($canPrintPdf)<a class="action-panel-button" href="{{ route('transmittals.pdf', $transmittal) }}" data-action-tab="transmittal,receipt,proof">Print / save PDF</a>@else<button class="action-panel-button" type="button" data-action-tab="transmittal,receipt,proof" disabled>Print / save PDF</button>@endif
    @if ($canCancelTransmittal)
        <form method="post" action="{{ route('transmittals.cancel', $transmittal) }}" data-action-tab="transmittal,receipt,proof" data-cancel-confirm>@csrf<input type="hidden" name="reason" value=""><button class="action-panel-button danger" type="submit">Cancel</button></form>
    @endif
    <div class="action-panel-meta-cards" style="margin-top: 14px; display: flex; flex-direction: column; gap: 12px; border-top: 1px solid #eef2f7; padding-top: 14px;">
        @include('transmittals._meta-summary-cards')
    </div>
</aside>

<button class="action-panel-reopen" type="button" data-reopen-action-panel hidden>Open Action Center</button>

@if ($canUploadProof)
    <div class="proof-modal" data-proof-modal hidden>
        <button class="proof-modal-backdrop" type="button" data-close-proof-modal aria-label="Close proof record form"></button>
        <section class="proof-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="proof-modal-title">
            <header>
                <h2 id="proof-modal-title">Upload proof of transmission</h2>
                <button class="proof-modal-close" type="button" data-close-proof-modal aria-label="Close dialog">&times;</button>
            </header>
            <form method="post" action="{{ route('transmittals.proofs.store', $transmittal) }}" enctype="multipart/form-data" class="action-proof-form form-stack" id="proof-upload-form" data-proof-return="{{ $draftWorkspace ? 'workspace' : 'show' }}">
                @csrf
                <input type="hidden" name="_method" value="">
                <input type="hidden" name="return_to" value="{{ $draftWorkspace ? 'workspace' : 'show' }}">
                <div class="proof-entry-modes" role="tablist" aria-label="Choose how to add proof">
                    <button type="button" class="active" data-proof-mode="ocr" role="tab" aria-selected="true">Upload file with OCR</button>
                    <button type="button" data-proof-mode="manual" role="tab" aria-selected="false">Fill in manually</button>
                </div>
                <p class="proof-mode-hint" data-proof-mode-hint>Upload the proof file. OCR will fill the record fields for you to review and correct.</p>
                <div class="proof-file-upload">
                    <label>
                        Evidence file <span class="required">*</span>
                        <div class="proof-file-box" style="border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 14px; background: #ffffff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); margin-top: 4px;">
                            <input name="proof" id="proof-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.mp4,.mov,.webm" capture="environment" data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}" required style="width: 100%; border: none; padding: 0; background: transparent; font-size: 13px; color: #374151;">
                        </div>
                    </label>
                    <small data-proof-file-hint>Choose a file to attach. Switch to OCR mode to read its details automatically.</small>
                    <button class="button subtle" type="button" id="analyze-proof" data-analyze-url="{{ route('transmittals.analyze') }}" hidden>Analyze and fill form</button>
                    <p class="ocr-message" id="proof-ocr-message" role="status" aria-live="polite"></p>
                </div>
                <div class="proof-modal-fields" data-proof-fields>
                    <div>
                        <label for="proof-type-select">Proof type <span class="required">*</span></label>
                        <select name="proof_type" id="proof-type-select" required>
                            <option value="delivery_receipt">Delivery receipt</option>
                            <option value="email_confirmation">Email confirmation</option>
                            <option value="courier_receipt">Courier receipt</option>
                            <option value="other">Others</option>
                        </select>
                        <div id="proof-type-other-wrapper" style="margin-top: 8px;" hidden>
                            <input name="proof_type_other" id="proof-type-other" maxlength="120" placeholder="Enter custom proof type details" style="width: 100%;">
                        </div>
                    </div>
                    <div>
                        <label for="proof-reference-number">Reference number</label>
                        <input name="reference_number" id="proof-reference-number" maxlength="120" placeholder="Optional reference">
                    </div>
                    <div>
                        <label for="proof-recipient">Saved recipient</label>
                        <select name="recipient_id" id="proof-recipient">
                            <option value="">Use receiver name below</option>
                            @foreach ($transmittal->recipients as $recipient)
                                <option value="{{ $recipient->id }}" data-recipient-name="{{ $recipient->name ?: $recipient->email }}">{{ $recipient->name ?: $recipient->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="proof-receiver-name">Recipient / receiver name <span class="required">*</span></label>
                        <input name="receiver_name" id="proof-receiver-name" required maxlength="160" value="{{ $transmittal->recipients->first()?->name ?: '' }}" placeholder="Enter receiver name">
                    </div>
                    <div>
                        <label for="proof-receiver-email">Recipient email</label>
                        <input name="receiver_email" id="proof-receiver-email" type="email" maxlength="254" placeholder="Optional email">
                    </div>
                    <div>
                        <label for="proof-channel-select">Transmission Method <span class="required">*</span></label>
                        <select name="channel" id="proof-channel-select" required>
                            @foreach (['email', 'in_person', 'courier', 'registered_mail', 'portal', 'manual', 'other'] as $channel)
                                <option value="{{ $channel }}">{{ ['portal' => 'Online Portal', 'registered_mail' => 'Registered Mail', 'manual' => 'Manual entry', 'other' => 'Others'][$channel] ?? str($channel)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        <div id="proof-channel-other-wrapper" style="margin-top: 8px;" hidden>
                            <input name="channel_other" id="proof-channel-other" maxlength="120" placeholder="Enter custom transmission method" style="width: 100%;">
                        </div>
                    </div>
                    <div>
                        <label for="proof-status">Status</label>
                        <select id="proof-status" disabled>
                            <option value="uploaded">Uploaded</option>
                            <option value="acknowledged">Acknowledged</option>
                        </select>
                        <small style="display: block; margin-top: 4px; color: #72849c; font-size: 11.5px;">Adding an acknowledgment date records this proof as Acknowledged.</small>
                    </div>
                    <div>
                        <label for="proof-sender-name">Who are you? / Sender <span class="required">*</span></label>
                        <input name="sender_name" id="proof-sender-name" required maxlength="160" value="{{ auth()->user()?->name }}" placeholder="Person responsible for this transmission">
                    </div>
                    <div>
                        <label for="proof-sender-position">Sender Position / Office</label>
                        <input name="sender_position" id="proof-sender-position" maxlength="160" placeholder="Optional; enter if applicable">
                    </div>
                    <div>
                        <label for="proof-acknowledged-by">Acknowledged by</label>
                        <input name="acknowledged_by" id="proof-acknowledged-by" maxlength="160" placeholder="Name on acknowledgment">
                    </div>
                    <div>
                        <label for="proof-location">Where are you at? / Sender Location</label>
                        <input name="location" id="proof-location" maxlength="200" placeholder="Enter location manually; no GPS is collected">
                        <small style="display: block; margin-top: 4px; color: #72849c; font-size: 11.5px;">Manually entered; not location-verified.</small>
                    </div>
                    <div>
                        <label for="proof-device-category">What device are you using? <span class="required">*</span></label>
                        <select name="device_category" id="proof-device-category" required>
                            <option value="">Select device type</option>
                            <option value="desktop">Desktop Computer</option>
                            <option value="laptop">Laptop</option>
                            <option value="mobile">Mobile Phone</option>
                            <option value="tablet">Tablet</option>
                            <option value="other">Others</option>
                        </select>
                        <div id="proof-device-category-other-wrapper" style="margin-top: 8px;" hidden>
                            <input name="device_category_other" id="proof-device-category-other" maxlength="120" placeholder="Enter custom device details" style="width: 100%;">
                        </div>
                    </div>
                    <div>
                        <label for="proof-device">Device details</label>
                        <input name="device" id="proof-device" maxlength="300" placeholder="Optional model or device details">
                        <small style="display: block; margin-top: 4px; color: #72849c; font-size: 11.5px;">Entered by the sender; browser metadata is recorded separately.</small>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="proof-notes">Additional proof details</label>
                        <textarea name="notes" id="proof-notes" rows="2" maxlength="2000" placeholder="Additional notes from the evidence"></textarea>
                    </div>
                    <div>
                        <label for="proof-transmission-at">Transmission Date and Time <span class="required">*</span></label>
                        <input name="transmission_at" id="proof-transmission-at" type="datetime-local" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                    </div>
                    <div>
                        <label for="proof-acknowledged-at">Date acknowledged</label>
                        <input name="acknowledged_at" id="proof-acknowledged-at" type="datetime-local">
                    </div>
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
        <header>
            <h2 id="proof-camera-title">Take photo / video</h2>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button class="button subtle" type="button" data-camera-switch style="font-size: 13px; font-weight: 600; padding: 6px 12px; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px;">🔄 Switch camera</button>
                <button class="proof-modal-close" type="button" data-close-camera aria-label="Close camera">&times;</button>
            </div>
        </header>
        <video id="proof-camera-preview" autoplay playsinline muted></video>
        <canvas id="proof-camera-canvas" hidden></canvas>
        <p class="camera-message" id="proof-camera-message" role="status" aria-live="polite">Allow camera access, then capture a photo or record a short video.</p>
        <div class="camera-controls">
            <button class="button primary" type="button" data-camera-photo disabled>📸 Capture photo</button>
            <button class="button subtle" type="button" data-camera-record disabled>🎥 Record video</button>
            <button class="button danger" type="button" data-camera-stop hidden>⏹ Stop recording</button>
            <button class="button primary" type="button" data-camera-use disabled>✓ Use captured file</button>
            <button class="camera-file-fallback" type="button" data-camera-browse>Choose file instead</button>
            <input type="file" accept="image/*,video/*" capture="environment" id="camera-native-fallback" hidden>
        </div>
    </section>
</div>

<div class="proof-modal" data-proof-view-modal hidden>
    <button class="proof-modal-backdrop" type="button" data-close-proof-view aria-label="Close proof details"></button>
    <section class="proof-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="proof-view-title">
        <header>
            <h2 id="proof-view-title">Proof of Transmission Details</h2>
            <button class="proof-modal-close" type="button" data-close-proof-view aria-label="Close">&times;</button>
        </header>
        <div class="proof-modal-fields" style="padding: 20px;">
            <label>Type <span data-proof-view-value="proof_type"></span></label>
            <label>Reference number <span data-proof-view-value="reference_number"></span></label>
            <label>Recipient / receiver <span data-proof-view-value="receiver_name"></span></label>
            <label>Recipient email <span data-proof-view-value="receiver_email"></span></label>
            <label>Transmission method <span data-proof-view-value="channel"></span></label>
            <label>Status <span data-proof-view-value="status"></span></label>
            <label>Date sent / uploaded <span data-proof-view-value="transmitted_at"></span></label>
            <label>Date acknowledged <span data-proof-view-value="acknowledged_at"></span></label>
            <label>Sender <span data-proof-view-value="sender_name"></span></label>
            <label>Sender position <span data-proof-view-value="sender_position"></span></label>
            <label>Sender location <span data-proof-view-value="location"></span></label>
            <label>Device <span data-proof-view-value="device"></span></label>
            <label>Acknowledged by <span data-proof-view-value="acknowledged_by"></span></label>
            <label>Evidence / source <span data-proof-view-value="source"></span><a data-proof-view-evidence hidden target="_blank" rel="noopener">Open evidence</a></label>
            <label style="grid-column: 1 / -1;">Additional details <span data-proof-view-value="notes" style="white-space: pre-wrap; overflow-wrap: anywhere;"></span></label>
        </div>
    </section>
</div>

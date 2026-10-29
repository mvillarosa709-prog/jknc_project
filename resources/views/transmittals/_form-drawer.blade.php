@php
    $transmittal = $transmittal ?? new \App\Models\Transmittal();
    $isEditing = $transmittal->exists;
    $workspace = $workspace ?? false;
    $direction = old('direction', $transmittal->direction ?? 'send');
    $formRecipients = old('recipients', $isEditing
        ? $transmittal->recipients->map(fn ($recipient) => [
            'name' => $recipient->name,
            'email' => $recipient->email,
            'organization' => $recipient->organization,
        ])->all()
        : [['name' => '', 'email' => '', 'organization' => '']]);
    $formItems = old('items', $isEditing
        ? $transmittal->items->map(fn ($item) => [
            'id' => $item->id,
            'particular' => $item->particular,
            'unique_id' => $item->unique_id,
            'quantity' => $item->quantity,
            'description' => $item->description,
            'remarks' => $item->remarks,
        ])->all()
        : [['particular' => '', 'unique_id' => '', 'quantity' => '', 'description' => '', 'remarks' => '']]);
    $employees = $employees ?? collect();
    $contacts = $contacts ?? collect();
    $companies = $companies ?? collect();
    $approvedByName = old('approved_by_name', $transmittal->approved_by_name ?? '');
    $operationsManagerName = old('operations_manager', $transmittal->operations_manager ?? '');
    $selectedApprover = old('approved_by_employee', $employees->firstWhere('name', $approvedByName)?->id);
    $selectedOperationsManager = old('operations_manager_employee', $employees->firstWhere('name', $operationsManagerName)?->id);
    $deliveredByName = old('delivered_by', $transmittal->delivered_by ?? '');
    $receivedByName = old('received_by', $transmittal->received_by ?? '');
    $processingCompanyName = old('processing_company', $transmittal->processing_company ?? '');
    if ($formRecipients === []) {
        $formRecipients = [['name' => '', 'email' => '', 'organization' => '']];
    }
@endphp


<div class="drawer-backdrop" data-close-drawer></div>
<aside class="transmittal-drawer {{ $workspace ? 'workspace-drawer' : '' }}" data-transmittal-drawer aria-labelledby="drawer-title" aria-hidden="{{ $workspace ? 'false' : 'true' }}">
    <header class="drawer-header">
        <div>
            <h2 id="drawer-title">{{ $workspace ? 'Draft of Transmittal' : ($isEditing ? 'Edit Draft' : 'Add Transmittal') }}</h2>
            <p>{{ $workspace ? 'Complete the transmittal information, save the draft, or submit it for review.' : 'Manual creation. Save a draft and continue later.' }}</p>
        </div>
        @if ($workspace)
            <a class="drawer-close" href="{{ route('transmittals.index', ['filter' => 'uploaded']) }}" aria-label="Back to uploaded transmittals">×</a>
        @else
            <div class="drawer-header-actions">
                <button class="button subtle mobile-preview-toggle" type="button" data-show-document-preview>Preview</button>
                <button class="drawer-close" type="button" data-close-drawer aria-label="Close">×</button>
            </div>
        @endif
    </header>
    <form method="post" action="{{ $isEditing ? route('transmittals.update', $transmittal) : route('transmittals.store') }}" enctype="multipart/form-data" id="transmittal-form" class="drawer-form" data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}" data-upload-start-url="{{ route('transmittals.uploads.start') }}" data-chunk-bytes="{{ config('ordo.upload.chunk_bytes') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif
        <div class="drawer-body">
            @if ($errors->any())
                <div class="drawer-errors" role="alert">
                    <strong>Please check these details:</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($isEditing && $transmittal->status === 'rejected')
                <div class="drawer-errors" role="status">
                    <strong>Reverted for revision</strong>
                    <p>{{ $transmittal->rejection_reason }}</p>
                    <small>Update this record and resubmit under {{ $transmittal->tracking_number }}.</small>
                </div>
            @endif

            <label>Ref #
                <input value="{{ $isEditing ? $transmittal->tracking_number : 'AUTO-INCREMENT' }}" readonly aria-label="Reference number">
                <p class="field-hint" style="margin-top: 4px; font-size: 11px; color: #8794a8;">Date is automatically set to today when saved.</p>
            </label>
            <div>
                <label>Date <span class="required">*</span>
                    <input type="date" name="transmittal_date" value="{{ old('transmittal_date', $isEditing ? $transmittal->transmittal_date?->format('Y-m-d') : now()->format('Y-m-d')) }}" required data-preview="date">
                    @error('transmittal_date')<span class="field-error">{{ $message }}</span>@enderror
                </label>
            </div>
            <div>
                <label>Mode <span class="required">*</span>
                    <select name="direction" required data-preview="direction">
                        <option value="send" @selected($direction === 'send')>SEND</option>
                        <option value="receive" @selected($direction === 'receive')>RECEIVE</option>
                    </select>
                    @error('direction')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <p class="field-hint" data-mode-description style="margin-top: 4px; font-size: 11px; color: #8794a8;">{{ $direction === 'receive' ? 'Incoming transmittal' : 'Outgoing transmittal' }}</p>
            </div>

            <input type="hidden" name="subject" value="{{ old('subject', $transmittal->subject ?: 'Transmittal Form') }}" id="subject-field" data-preview="subject">
            <input type="hidden" name="related_company" value="{{ old('related_company', $transmittal->related_company ?? '') }}" data-preview="related-company">
            <input type="hidden" name="confidentiality" value="{{ old('confidentiality', $transmittal->confidentiality ?? 'standard') }}" data-preview="confidentiality">
            <input type="hidden" name="purpose" value="{{ old('purpose', $transmittal->purpose ?? 'Official Transmittal') }}" data-preview="purpose">
            <label><span data-counterparty-label>{{ $direction === 'receive' ? 'From' : 'To' }}</span>
                <input name="recipients[0][name]" value="{{ old('recipients.0.name', $formRecipients[0]['name'] ?? '') }}" placeholder="{{ $direction === 'receive' ? 'Enter external sender / source' : 'Enter external receiver / destination' }}" data-preview="recipient">
                @error('recipients.0.name')<span class="field-error">{{ $message }}</span>@enderror
            </label>
            <label><span data-office-label>{{ $direction === 'receive' ? 'To (Office)' : 'From (Office)' }}</span> <span class="required">*</span>
                <input name="origin_office" value="{{ old('origin_office', $transmittal->origin_office ?? config('ordo.office_name')) }}" required maxlength="160" placeholder="{{ $direction === 'receive' ? 'Enter office receiving the transmittal' : 'Enter office sending the transmittal' }}" data-preview="office">
                @error('origin_office')<span class="field-error">{{ $message }}</span>@enderror
            </label>
            <label>Address
                <textarea name="recipient_address" rows="3" placeholder="Enter address" data-preview="address">{{ old('recipient_address', $transmittal->recipient_address ?? '') }}</textarea>
                @error('recipient_address')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <div class="drawer-divider"></div>

            <section class="drawer-section">
                <h3>Delivery Type</h3>
                @php
                    $deliveryType = old('delivery_type', $transmittal->delivery_type ?? 'by_person');
                    $deliveryMethod = old('delivery_method', $transmittal->delivery_method ?? 'electronic');
                @endphp
                <label>Type
                    <select name="delivery_type" id="drawer-delivery-type" data-preview="delivery-type">
                        <option value="">Select delivery type</option>
                        <option value="by_person" @selected($deliveryType === 'by_person')>By Person</option>
                        <option value="registered_mail" @selected($deliveryType === 'registered_mail')>Registered Mail</option>
                        <option value="electronic" @selected($deliveryType === 'electronic')>Electronic</option>
                    </select>
                    @error('delivery_type')<span class="field-error">{{ $message }}</span>@enderror
                </label>

                <div id="drawer-by-person-block" style="{{ $deliveryType === 'by_person' ? '' : 'display: none;' }}">
                    <label>Who?
                        <input type="text" name="delivered_by" value="{{ old('delivered_by', $transmittal->delivered_by ?? '') }}" placeholder="Enter person responsible" data-preview="delivered-by">
                        @error('delivered_by')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                </div>

                <div id="drawer-registered-mail-block" style="{{ $deliveryType === 'registered_mail' ? '' : 'display: none;' }}">
                    <label>Courier / Provider
                        <select name="registered_mail_provider" data-preview="courier-provider">
                            <option value="">Select provider</option>
                            @foreach (['LBC', 'J&T', 'Postal', 'Others'] as $provider)
                                <option value="{{ $provider }}" @selected(old('registered_mail_provider', $transmittal->external_reference ?? '') === $provider)>{{ $provider }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div id="drawer-electronic-block" style="{{ $deliveryType === 'electronic' ? '' : 'display: none;' }}">
                    <label>Electronic Method
                        <select name="delivery_method" data-preview="delivery-method">
                            <option value="">Select method</option>
                            <option value="electronic" @selected($deliveryMethod === 'electronic')>Email</option>
                        </select>
                    </label>
                </div>

                <div id="drawer-recipient-email-block" style="{{ $deliveryType === 'electronic' || $workspace || $isEditing ? '' : 'display: none;' }}">
                    <label>Recipient Email
                        <input type="email" name="recipients[0][email]" value="{{ old('recipients.0.email', $formRecipients[0]['email'] ?? '') }}" placeholder="Enter email address" data-preview="recipient-email">
                        @error('recipients.0.email')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                </div>
            </section>

            <div class="drawer-divider"></div>

            <section class="drawer-section">
                <h3>Actions</h3>
                @php($selectedActions = old('delivery_actions', $transmittal->delivery_actions ?? ['email']))
                <fieldset class="delivery-actions">
                    <legend>Actions</legend>
                    @foreach (['delivery' => 'Delivery', 'pick_up' => 'Pick Up', 'drop_off' => 'Drop Off', 'email' => 'Email'] as $value => $label)
                        <label><input type="checkbox" name="delivery_actions[]" value="{{ $value }}" @checked(in_array($value, $selectedActions, true))> {{ $label }}</label>
                    @endforeach
                </fieldset>
            </section>



            <section class="drawer-section item-section">
                <div class="item-section-heading">
                    <label class="block text-xs font-semibold text-gray-500" style="font-size: 13px; font-weight: 600; color: #1f2937;">List of Items</label>
                    <button class="button primary" type="button" id="add-item">＋ Add Row</button>
                </div>
                <div id="item-list" style="display: flex; flex-direction: column; gap: 12px;">
                    @foreach ($formItems as $index => $item)
                        <fieldset class="item-editor-row">
                            <legend>Item {{ $loop->iteration }}<button class="remove-item text-button" type="button">Remove</button></legend>
                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
                            <label>Particular<input name="items[{{ $index }}][particular]" value="{{ $item['particular'] ?? '' }}" maxlength="200" placeholder="Item name"></label>
                            <div class="drawer-field-row">
                                <label>Unique ID<input name="items[{{ $index }}][unique_id]" value="{{ $item['unique_id'] ?? '' }}" maxlength="120" placeholder="ID / serial"></label>
                                <label>Qty.<input type="number" min="0" step="any" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '' }}" maxlength="40" placeholder="Quantity"></label>
                            </div>
                            @if ($isEditing && !empty($item['id']) && ($linkedItem = $transmittal->items->firstWhere('id', $item['id']))?->document)
                                <small class="item-file-existing">Attached: <a href="{{ route('transmittals.documents.download', [$transmittal, $linkedItem->document]) }}">{{ $linkedItem->document->original_name }}</a></small>
                            @endif
                            <label>Description<input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" maxlength="2000" placeholder="Description"></label>
                            <label>Remarks<input name="items[{{ $index }}][remarks]" value="{{ $item['remarks'] ?? '' }}" maxlength="2000" placeholder="Remarks"></label>
                            <label>Upload File
                                <input type="file" name="items[{{ $index }}][attachment]" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx" data-file-name-target data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}">
                                <small class="file-selection-name">No file selected</small>
                            </label>
                        </fieldset>
                    @endforeach
                </div>
                <template id="item-row-template">
                    <fieldset class="item-editor-row">
                        <legend>Item<button class="remove-item text-button" type="button">Remove</button></legend>
                        <input type="hidden" name="items[0][id]" value="">
                        <label>Particular<input name="items[0][particular]" maxlength="200" placeholder="Item name"></label>
                        <div class="drawer-field-row">
                            <label>Unique ID<input name="items[0][unique_id]" maxlength="120" placeholder="ID / serial"></label>
                            <label>Qty.<input type="number" min="0" step="any" name="items[0][quantity]" maxlength="40" placeholder="Quantity"></label>
                        </div>
                        <label>Description<input name="items[0][description]" maxlength="2000" placeholder="Description"></label>
                        <label>Remarks<input name="items[0][remarks]" maxlength="2000" placeholder="Remarks"></label>
                        <label>Upload File
                            <input type="file" name="items[0][attachment]" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx" data-file-name-target data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}">
                            <small class="file-selection-name">No file selected</small>
                        </label>
                    </fieldset>
                </template>
            </section>

            <div class="drawer-divider"></div>

            <section class="drawer-section supporting-attachments-section">
                <h3>Supporting Attachments</h3>
                @if ($isEditing && $transmittal->documents->isNotEmpty())
                    <div class="existing-files">
                        @foreach ($transmittal->documents as $document)
                            <a href="{{ route('transmittals.documents.download', [$transmittal, $document]) }}">{{ $document->original_name }} · {{ ucfirst($document->category) }}</a>
                        @endforeach
                    </div>
                @endif
                <input type="file" name="supporting_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx" data-supporting-attachments data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}">
                <small class="file-selection-name" data-supporting-file-names>No file selected</small>
                <p class="field-hint">You may upload multiple supporting files. These will be attached when using Send Email.</p>
            </section>

            <div class="drawer-divider"></div>

            <section class="drawer-section">
                <h3>Signatories</h3>
                <label>Prepared by<input value="{{ $transmittal?->prepared_by_name ?: (auth()->user()?->name ?? 'Public submitter') }}" readonly data-preview="prepared-by"></label>
                <label>Prepared at<input value="{{ $transmittal?->prepared_at?->format('m/d/Y, g:i:s A') ?? now()->format('m/d/Y, g:i:s A') }}" readonly data-preview="prepared-at"></label>
                <label>Approved by
                    <select name="approved_by_employee" data-signatory-select="approved_by_name">
                        <option value="">Select employee approver</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" data-employee-name="{{ $employee->name }}" @selected((string) $selectedApprover === (string) $employee->id)>{{ $employee->name }}{{ $employee->transmittal_role ? ' - '.str($employee->transmittal_role)->replace('_', ' ')->title() : '' }}</option>
                        @endforeach
                    </select>
                    <input name="approved_by_name" value="{{ $approvedByName }}" maxlength="160" placeholder="Or type approver manually" data-preview="approved-by">
                </label>
                <label>Operations Manager
                    <select name="operations_manager_employee" data-signatory-select="operations_manager">
                        <option value="">Select Operations Manager</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" data-employee-name="{{ $employee->name }}" @selected((string) $selectedOperationsManager === (string) $employee->id)>{{ $employee->name }}{{ $employee->transmittal_role ? ' - '.str($employee->transmittal_role)->replace('_', ' ')->title() : '' }}</option>
                        @endforeach
                    </select>
                    <input name="operations_manager" value="{{ $operationsManagerName }}" maxlength="160" placeholder="Or type Operations Manager manually" data-preview="operations-manager">
                </label>
                <label>Delivered by
                    <select name="delivered_by_employee" data-signatory-select="delivered_by">
                        <option value="">Select employee</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->name }}" data-employee-name="{{ $employee->name }}" @selected($deliveredByName === $employee->name)>{{ $employee->name }}{{ $employee->transmittal_role ? ' - '.str($employee->transmittal_role)->replace('_', ' ')->title() : '' }}</option>
                        @endforeach
                    </select>
                    <input name="delivered_by" value="{{ $deliveredByName }}" maxlength="160" placeholder="Or type delivered by manually" data-preview="delivered-by">
                </label>
                <label>Received by (Contact / Employee)
                    <select name="received_by_contact" data-signatory-select="received_by">
                        <option value="">Select contact or employee</option>
                        <optgroup label="Employees">
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->name }}" data-employee-name="{{ $employee->name }}" @selected($receivedByName === $employee->name)>{{ $employee->name }}{{ $employee->transmittal_role ? ' - '.$employee->transmittal_role : '' }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Contacts">
                            @foreach ($contacts as $contact)
                                <option value="{{ $contact->name }}" data-employee-name="{{ $contact->name }}" @selected($receivedByName === $contact->name)>{{ $contact->name }}{{ $contact->organization ? ' ('.$contact->organization.')' : '' }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <input name="received_by" value="{{ $receivedByName }}" maxlength="160" placeholder="Or type receiver manually" data-preview="received-by">
                </label>
                <label>Processing for Company / Organization
                    <select name="processing_company_selection" data-signatory-select="processing_company">
                        <option value="">Select company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company }}" data-employee-name="{{ $company }}" @selected($processingCompanyName === $company)>{{ $company }}</option>
                        @endforeach
                    </select>
                    <input name="processing_company" value="{{ $processingCompanyName }}" maxlength="160" placeholder="Or type company manually" data-preview="processing-company">
                </label>
            </section>
            @if ($isEditing && ! $workspace)
                <section class="drawer-section">
                    <h3>Attachments &amp; Smart Upload</h3>
                    @if ($isEditing && $transmittal->documents->isNotEmpty())
                        <div class="existing-files">
                            @foreach ($transmittal->documents as $document)
                                <a href="{{ route('transmittals.documents.download', [$transmittal, $document]) }}">{{ $document->original_name }} · {{ ucfirst($document->category) }}</a>
                            @endforeach
                        </div>
                    @endif
                    <label class="drop-zone" id="drop-zone" for="document-input">
                        <span class="upload-icon">↑</span><strong>Drop your files here</strong><span>or <u>browse files</u></span>
                        <small>PDF, images, DOCX · up to 20 MB each</small>
                        <input type="file" name="documents[]" id="document-input" multiple accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,.doc,.docx" data-upload-max-bytes="{{ config('ordo.upload.max_bytes') }}">
                    </label>
                    <input type="file" id="transmittal-zip-input" accept=".zip,application/zip,application/x-zip-compressed" hidden>
                    <div class="upload-controls">
                        <button class="button subtle" type="button" id="analyze-files" data-max-files="{{ config('ordo.ocr.max_files') }}" data-analyze-url="{{ route('transmittals.analyze') }}" disabled>✧ Classify &amp; extract</button>
                        <span class="field-hint">OCR uses the configured server tools.</span>
                    </div>
                    <p class="ocr-message" id="ocr-message" role="status"></p>
                    <div id="file-list" class="file-list"></div>
                    <p class="privacy-callout"><span>◇</span> Attachments and register details are publicly accessible. Do not upload restricted or confidential material.</p>
                </section>
            @endif
        </div>
        
       <footer class="drawer-footer">
    @if ($workspace)
        <a
            class="button subtle"
            href="{{ route('transmittals.index', ['filter' => 'uploaded']) }}"
        >
            Cancel
        </a>
    @else
        <button
            class="button subtle"
            type="button"
            data-close-drawer
        >
            Cancel
        </button>
    @endif

    @error('intent')
        <span class="field-error" id="intent-error">
            {{ $message }} Choose Save Draft or Submit for review.
        </span>
    @enderror

    <button
        class="button primary"
        type="submit"
        name="intent"
        value="draft"
    >
        Save Draft
    </button>

    <button
        class="button primary submit-review-button"
        type="submit"
        name="intent"
        value="submit"
    >
        Submit for review
    </button>
</footer>
    </form>
</aside>

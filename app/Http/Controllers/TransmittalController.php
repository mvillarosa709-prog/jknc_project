<?php

namespace App\Http\Controllers;

use App\Exceptions\OcrUnavailableException;
use App\Models\Transmittal;
use App\Models\TransmittalDocument;
use App\Models\TransmittalReceipt;
use App\Models\TransmittalRecipient;
use App\Models\TransmittalUpload;
use App\Models\User;
use App\Services\BrevoTransmittalService;
use App\Services\ResumableTransmittalUploadService;
use App\Services\TransmittalApprovalService;
use App\Services\TransmittalOcrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TransmittalController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transmittal::query()
            ->with(['preparer', 'recipients'])
            ->withCount(['recipients', 'documents', 'proofs', 'receipts'])
            ->orderByDesc('transmittal_date')
            ->orderByDesc('id');

        $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'uploaded', 'submitted', 'accepted', 'reverted', 'archived', 'deleted'])],
            'q' => ['nullable', 'string', 'max:200'],
        ]);
        $filter = $request->string('filter', 'all')->toString();
        abort_unless($filter !== 'deleted' || $request->user()?->canApproveTransmittals(), 404);
        $filterStatuses = [
            'uploaded' => ['draft'],
            'submitted' => ['pending_approval'],
            'accepted' => ['approved', 'sent', 'acknowledged'],
            'reverted' => ['rejected'],
            'archived' => ['cancelled'],
        ];
        if (isset($filterStatuses[$filter])) {
            $query->whereIn('status', $filterStatuses[$filter]);
        } elseif ($filter === 'deleted') {
            $query->onlyTrashed();
        }

        $query->when($request->filled('q'), function ($builder) use ($request): void {
            $term = '%'.addcslashes((string) $request->string('q'), '%_\\').'%';
            $builder->where(fn ($search) => $search
                ->where('tracking_number', 'like', $term)
                ->orWhere('subject', 'like', $term)
                ->orWhere('external_reference', 'like', $term)
                ->orWhere('origin_office', 'like', $term)
                ->orWhere('related_company', 'like', $term)
                ->orWhereHas('documents', fn ($documents) => $documents->where('original_name', 'like', $term))
                ->orWhereHas('recipients', fn ($recipients) => $recipients
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('organization', 'like', $term)));
        });

        $summary = Transmittal::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $draftTokens = $request->session()->get('transmittal_draft_tokens', []);
        $editableDraftIds = [];

        if ($draftTokens !== []) {
            $editableDraftIds = Transmittal::query()
                ->whereIn('status', ['draft', 'rejected'])
                ->whereIn('id', array_keys($draftTokens))
                ->get(['id', 'edit_token_hash'])
                ->filter(fn (Transmittal $draft): bool => is_string($draftTokens[$draft->id] ?? null)
                    && is_string($draft->edit_token_hash)
                    && hash_equals($draft->edit_token_hash, hash('sha256', $draftTokens[$draft->id])))
                ->pluck('id')
                ->all();
        }

        $editTransmittal = null;
        if ($request->filled('edit')) {
            $editTransmittal = Transmittal::query()->findOrFail($request->integer('edit'));
            abort_unless($this->canEditDraft($request, $editTransmittal), 404);
            $editTransmittal->load('recipients', 'documents', 'items.document', 'preparer', 'approver');
        }

        return view('transmittals.index', [
            'transmittals' => $query->paginate(12)->withQueryString(),
            'filter' => $filter,
            'search' => $request->string('q')->toString(),
            'summary' => $summary,
            'deletedCount' => $request->user()?->canApproveTransmittals()
                ? Transmittal::onlyTrashed()->count()
                : 0,
            'editableDraftIds' => $editableDraftIds,
            'editTransmittal' => $editTransmittal,
        ] + $this->signatoryDirectory());
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('transmittals.index', ['add' => 1]);
    }

    public function smartUpload(): View
    {
        $recentDocuments = TransmittalDocument::query()
            ->with('transmittal:id,tracking_number,subject')
            ->latest('id')
            ->limit(25)
            ->get(['id', 'transmittal_id', 'original_name', 'category', 'size_bytes', 'created_at']);

        return view('transmittals.smart-upload', [
            'recentDocuments' => $recentDocuments,
        ]);
    }

    public function createFromSmartUpload(Request $request, ResumableTransmittalUploadService $uploads): RedirectResponse
    {
        $validated = $request->validate([
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx,txt'],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['required', Rule::in(['document', 'receipt', 'proof', 'attachment'])],
            'ocr_tokens' => ['nullable', 'array', 'max:20'],
            'ocr_tokens.*' => ['nullable', 'string', 'max:100000'],
            'upload_ids' => ['nullable', 'array', 'max:20'],
            'upload_ids.*' => ['required', 'uuid', 'distinct'],
            'upload_categories' => ['nullable', 'array', 'max:20'],
            'upload_categories.*' => ['required', Rule::in(['document', 'receipt', 'proof', 'attachment'])],
            'upload_ocr_tokens' => ['nullable', 'array', 'max:20'],
            'upload_ocr_tokens.*' => ['nullable', 'string', 'max:100000'],
            'subject' => ['required', 'string', 'max:200'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'related_company' => ['nullable', 'string', 'max:200'],
            'recipient_address' => ['nullable', 'string', 'max:500'],
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'recipient_email' => ['nullable', 'email', 'max:254'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'transmittal_date' => ['nullable', 'date'],
            'direction' => ['nullable', 'string', Rule::in(['send', 'receive'])],
            'items' => ['nullable', 'array'],
            'items.*.particular' => ['nullable', 'string', 'max:255'],
            'items.*.unique_id' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:40'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
            'items.*.document_index' => ['nullable', 'integer'],
        ]);

        $directFiles = $request->file('documents', []);
        $uploadIds = $validated['upload_ids'] ?? [];
        if ($directFiles === [] && $uploadIds === []) {
            throw ValidationException::withMessages(['documents' => 'Choose at least one file to upload.']);
        }
        if (count($directFiles) + count($uploadIds) > 20) {
            throw ValidationException::withMessages(['documents' => 'A maximum of 20 files can be attached.']);
        }

        $fileEntries = [];
        foreach ($directFiles as $index => $file) {
            $fileEntries[] = [
                'file' => $file,
                'category' => $validated['categories'][$index] ?? 'document',
                'ocr_token' => $validated['ocr_tokens'][$index] ?? null,
                'upload' => null,
            ];
        }
        $uploadOwnerHash = hash('sha256', $request->session()->getId());
        foreach ($uploadIds as $index => $uploadId) {
            $uploadToken = $request->session()->get("transmittal_upload_tokens.{$uploadId}");
            abort_unless(is_string($uploadToken), 404);
            $upload = $uploads->ownedUpload(
                $uploadOwnerHash,
                $uploadId,
                $uploadToken
            );
            abort_unless($upload->status === 'completed', 409, 'A resumable upload is not complete.');
            $file = $uploads->temporaryFile($upload);
            validator(['document' => $file], [
                'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx,txt'],
            ])->validate();
            $fileEntries[] = [
                'file' => $file,
                'category' => $validated['upload_categories'][$index] ?? 'document',
                'ocr_token' => $validated['upload_ocr_tokens'][$index] ?? null,
                'upload' => $upload,
            ];
        }

        $user = $request->user();
        $token = Str::random(64);
        $transmittal = DB::transaction(function () use ($validated, $user, $token, $fileEntries, $uploadIds, $uploadOwnerHash, $request): Transmittal {
            if ($uploadIds !== []) {
                $claimed = TransmittalUpload::query()
                    ->whereIn('upload_id', $uploadIds)
                    ->where('owner_session_hash', $uploadOwnerHash)
                    ->where('status', 'completed')
                    ->where('expires_at', '>', now())
                    ->update([
                        'status' => 'consumed',
                        'upload_token_hash' => hash('sha256', Str::random(64)),
                        'expires_at' => now()->addHours((int) config('ordo.upload.expiry_hours')),
                    ]);
                abort_unless($claimed === count($uploadIds), 409, 'One or more uploads have already been imported or have expired.');
            }

            $transmittal = Transmittal::query()->create([
                'tracking_number' => 'PENDING-'.Str::uuid(),
                'subject' => $validated['subject'],
                'external_reference' => $validated['external_reference'] ?? null,
                'related_company' => $validated['related_company'] ?? null,
                'recipient_address' => $validated['recipient_address'] ?? null,
                'purpose' => $validated['purpose'] ?? 'Review the uploaded documents and complete the transmittal details.',
                'direction' => $validated['direction'] ?? 'send',
                'transmittal_date' => $validated['transmittal_date'] ?? now()->toDateString(),
                'origin_office' => config('ordo.office_name'),
                'delivery_method' => 'electronic',
                'delivery_type' => 'by_person',
                'delivery_actions' => ['email'],
                'prepared_by' => $user?->id,
                'prepared_by_name' => $user?->name ?? 'Public submitter',
                'prepared_at' => now(),
                'status' => 'draft',
            ]);
            $transmittal->forceFill([
                'tracking_number' => 'TRN-'.str_pad((string) $transmittal->id, 5, '0', STR_PAD_LEFT),
                'edit_token_hash' => hash('sha256', $token),
            ])->save();

            if (! empty($validated['recipient_email'])) {
                $transmittal->recipients()->create([
                    'name' => $validated['recipient_name'] ?? null,
                    'email' => $validated['recipient_email'],
                    'organization' => $validated['related_company'] ?? null,
                ]);
            }

            $storedDocuments = [];
            foreach ($fileEntries as $index => $entry) {
                $file = $entry['file'];
                $document = $this->storeDocument(
                    $transmittal,
                    $user,
                    $file,
                    $entry['category'],
                    $this->verifiedOcrData($entry['ocr_token'], $file)
                );
                $storedDocuments[$index] = $document;
            }

            $submittedItems = $request->input('items', []);
            if (! empty($submittedItems) && is_array($submittedItems)) {
                $pos = 0;
                foreach ($submittedItems as $itemIndex => $itemData) {
                    $docIdx = isset($itemData['document_index']) && is_numeric($itemData['document_index'])
                        ? (int) $itemData['document_index']
                        : $pos;
                    $doc = $storedDocuments[$docIdx] ?? ($storedDocuments[$pos] ?? null);

                    $transmittal->items()->create([
                        'document_id' => $doc?->id,
                        'position' => $pos,
                        'particular' => ! empty($itemData['particular']) ? $itemData['particular'] : ($doc ? pathinfo($doc->original_name, PATHINFO_FILENAME) : 'Item '.($pos + 1)),
                        'unique_id' => $itemData['unique_id'] ?? null,
                        'quantity' => ! empty($itemData['quantity']) ? (string) $itemData['quantity'] : '1',
                        'description' => $itemData['description'] ?? null,
                        'remarks' => $itemData['remarks'] ?? 'Auto-filled from Smart Upload; reviewed by user.',
                    ]);
                    $pos++;
                }
            } else {
                foreach ($storedDocuments as $index => $document) {
                    $fields = $document->extracted_data ?? [];
                    $transmittal->items()->create([
                        'document_id' => $document->id,
                        'position' => $index,
                        'particular' => pathinfo($document->original_name, PATHINFO_FILENAME),
                        'unique_id' => $fields['unique_id'] ?? $fields['reference'] ?? null,
                        'quantity' => '1',
                        'description' => $fields['description'] ?? $fields['subject'] ?? null,
                        'remarks' => 'Auto-filled from Smart Upload; review and correct before submission.',
                    ]);
                }
            }

            $transmittal->histories()->create([
                'actor_id' => $user?->id,
                'action' => 'smart_upload_draft_created',
                'description' => 'Draft created from Smart Upload with permanent tracking number '.$transmittal->tracking_number.'.',
                'created_at' => now(),
            ]);

            return $transmittal;
        });

        if ($uploadIds !== []) {
            $tokens = $request->session()->get('transmittal_upload_tokens', []);
            foreach ($uploadIds as $uploadId) {
                unset($tokens[$uploadId]);
            }
            $request->session()->put('transmittal_upload_tokens', $tokens);
        }

        $tokens = $request->session()->get('transmittal_draft_tokens', []);
        $tokens[$transmittal->id] = $token;
        $request->session()->put('transmittal_draft_tokens', $tokens);

        return redirect()->route('transmittals.workspace', $transmittal)
            ->with('status', 'Files uploaded. Complete the transmittal workspace and save or submit it.');
    }

public function workspace(Request $request, Transmittal $transmittal): View
    {
        // Payagan ang pag-access sa workspace kahit guest basta umiiral ang transmittal
        // (O maaari mong ilagay ang iyong custom check kung kinakailangan)
        
        $transmittal->load([
            'recipients',
            'documents',
            'items.document',
            'receipts.recipient',
            'proofs.recipient',
            'proofs.sender',
            'proofs.document',
            'histories.actor',
        ]);

        return view('transmittals.workspace', [
            'transmittal' => $transmittal,
            'canViewPrivateFiles' => $this->canViewPrivateFiles($request, $transmittal),
            'canUploadProof' => true, // Ginawa nating true para makapag-upload kaagad ang kahit sino sa workspace
        ] + $this->signatoryDirectory());
    }

    public function edit(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($this->canEditDraft($request, $transmittal), 404);

        return redirect()->route('transmittals.index', ['edit' => $transmittal->id]);
    }

    public function analyze(Request $request, TransmittalOcrService $ocr): JsonResponse
    {
        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:'.config('ordo.ocr.max_files')],
            'documents.*' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,tif,tiff'],
        ]);

        try {
            return response()->json([
                'documents' => collect($data['documents'])->map(function (UploadedFile $file) use ($ocr): array {
                    $analysis = $ocr->analyze($file);
                    $path = $file->getRealPath();
                    abort_unless(is_string($path), 422, 'The uploaded file could not be verified.');
                    $sha256 = hash_file('sha256', $path);

                    $existingDoc = TransmittalDocument::query()
                        ->with('transmittal:id,tracking_number,subject')
                        ->where('sha256', $sha256)
                        ->orWhere('original_name', $file->getClientOriginalName())
                        ->first();

                    $refMatch = ! empty($analysis['fields']['reference'])
                        ? Transmittal::query()->where('external_reference', $analysis['fields']['reference'])->first(['id', 'tracking_number', 'subject'])
                        : null;

                    $matchedRecord = null;
                    if ($existingDoc) {
                        $matchedRecord = [
                            'type' => $existingDoc->sha256 === $sha256 ? 'duplicate_file' : 'similar_name',
                            'label' => $existingDoc->sha256 === $sha256 ? 'Duplicate File' : 'Matched Document',
                            'tracking_number' => $existingDoc->transmittal?->tracking_number,
                            'subject' => $existingDoc->transmittal?->subject,
                            'document_name' => $existingDoc->original_name,
                        ];
                    } elseif ($refMatch) {
                        $matchedRecord = [
                            'type' => 'reference_match',
                            'label' => 'Matched Reference',
                            'tracking_number' => $refMatch->tracking_number,
                            'subject' => $refMatch->subject,
                        ];
                    }

                    return [
                        'name' => $file->getClientOriginalName(),
                        'category' => $analysis['category'],
                        'fields' => $analysis['fields'],
                        'field_metadata' => $analysis['field_metadata'],
                        'matched_record' => $matchedRecord,
                        'sha256' => $sha256,
                        'text_preview' => Str::limit($analysis['text'], 500),
                        'analysis_token' => Crypt::encryptString(json_encode([
                            'name' => basename($file->getClientOriginalName()),
                            'sha256' => $sha256,
                            'category' => $analysis['category'],
                            'text' => $analysis['text'],
                            'fields' => $analysis['fields'],
                            'field_metadata' => $analysis['field_metadata'],
                        ], JSON_THROW_ON_ERROR)),
                    ];
                }),
            ]);
        } catch (OcrUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTransmittal($request);
        $isDraft = $validated['intent'] === 'draft';
        $user = $request->user();
        $editToken = Str::random(64);

        $transmittal = DB::transaction(function () use ($validated, $request, $user, $editToken, $isDraft): Transmittal {
            $transmittal = Transmittal::query()->create([
                'tracking_number' => 'PENDING-'.Str::uuid(),
                'external_reference' => $validated['external_reference'] ?? null,
                'subject' => $validated['subject'],
                'purpose' => $validated['purpose'],
                'direction' => $validated['direction'],
                'transmittal_date' => $validated['transmittal_date'],
                'origin_office' => $validated['origin_office'],
                'recipient_address' => $validated['recipient_address'] ?? null,
                'related_company' => $validated['related_company'] ?? null,
                'delivery_method' => $validated['delivery_method'] ?? 'electronic',
                'delivery_type' => $validated['delivery_type'] ?? 'by_person',
                'delivery_actions' => $validated['delivery_actions'] ?? [],
                'prepared_by_name' => $user?->name ?? 'Public submitter',
                'prepared_at' => now(),
                'approved_by_name' => $this->resolveSignatoryName($validated, 'approved_by_name', 'approved_by_employee'),
                'operations_manager' => $this->resolveSignatoryName($validated, 'operations_manager', 'operations_manager_employee'),
                'delivered_by' => $this->resolveSelectedName($validated, 'delivered_by', 'delivered_by_employee'),
                'received_by' => $this->resolveSelectedName($validated, 'received_by', 'received_by_contact'),
                'processing_company' => $this->resolveSelectedName($validated, 'processing_company', 'processing_company_selection'),
                'confidentiality' => $validated['confidentiality'],
                'status' => $isDraft ? 'draft' : 'pending_approval',
                'prepared_by' => $user?->id,
            ]);
            $transmittal->forceFill([
                'tracking_number' => 'TRN-'.str_pad((string) $transmittal->id, 5, '0', STR_PAD_LEFT),
                'edit_token_hash' => hash('sha256', $editToken),
            ])->save();

            $this->syncRecipients($transmittal, $validated['recipients'] ?? []);
            $this->storeDocuments($transmittal, $request, $user, $validated);
            $this->syncItems($transmittal, $request, $user, $validated);

            $transmittal->histories()->create([
                'actor_id' => $user?->id,
                'action' => $isDraft ? 'draft_saved' : 'submitted',
                'description' => $isDraft
                    ? 'Public draft saved and linked to the submitter browser session.'
                    : 'Public submission received and added to the administrator review queue.',
                'created_at' => now(),
            ]);

            return $transmittal;
        });

        $tokens = $request->session()->get('transmittal_draft_tokens', []);
        $tokens[$transmittal->id] = $editToken;
        $request->session()->put('transmittal_draft_tokens', $tokens);

        return redirect()->route('transmittals.index', [
            'filter' => $isDraft ? 'uploaded' : 'submitted',
        ])->with('status', $isDraft
            ? "Draft {$transmittal->tracking_number} saved. You can continue editing it from this browser."
            : 'Transmittal submitted for administrator review.');
    }

    public function update(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($this->canEditDraft($request, $transmittal), 404);
        $validated = $this->validateTransmittal($request);
        $isDraft = $validated['intent'] === 'draft';
        $user = $request->user();

        $isReverting = DB::transaction(function () use ($transmittal, $validated, $request, $user, $isDraft): bool {
            $record = Transmittal::query()->whereKey($transmittal->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canEditDraft($request, $record), 404);
            $isReverting = $record->status === 'rejected' && ! $isDraft;

            $record->forceFill([
                'external_reference' => filled($validated['external_reference'] ?? null)
                    ? $validated['external_reference']
                    : $record->external_reference,
                'subject' => $validated['subject'],
                'purpose' => $validated['purpose'],
                'direction' => $validated['direction'],
                'transmittal_date' => $validated['transmittal_date'],
                'origin_office' => $validated['origin_office'],
                'recipient_address' => filled($validated['recipient_address'] ?? null)
                    ? $validated['recipient_address']
                    : $record->recipient_address,
                'related_company' => filled($validated['related_company'] ?? null)
                    ? $validated['related_company']
                    : $record->related_company,
                'delivery_method' => $validated['delivery_method'] ?? 'electronic',
                'delivery_type' => $validated['delivery_type'] ?? $record->delivery_type ?? 'by_person',
                'delivery_actions' => $validated['delivery_actions'] ?? $record->delivery_actions ?? [],
                'prepared_by_name' => $user?->name ?? $record->prepared_by_name ?? 'Public submitter',
                'prepared_at' => $record->prepared_at ?? now(),
                'approved_by_name' => $this->resolveSignatoryName($validated, 'approved_by_name', 'approved_by_employee')
                    ?? $record->approved_by_name,
                'operations_manager' => $this->resolveSignatoryName($validated, 'operations_manager', 'operations_manager_employee')
                    ?? $record->operations_manager,
                'delivered_by' => $this->resolveSelectedName($validated, 'delivered_by', 'delivered_by_employee')
                    ?? $record->delivered_by,
                'received_by' => $this->resolveSelectedName($validated, 'received_by', 'received_by_contact')
                    ?? $record->received_by,
                'processing_company' => $this->resolveSelectedName($validated, 'processing_company', 'processing_company_selection')
                    ?? $record->processing_company,
                'confidentiality' => $validated['confidentiality'],
                'status' => $isDraft ? ($record->status === 'rejected' ? 'rejected' : 'draft') : 'pending_approval',
                'edit_token_hash' => $record->edit_token_hash,
            ])->save();

            $recipients = $validated['recipients'] ?? [];
            $hasRecipientEmail = collect($recipients)->contains(
                fn ($recipient): bool => is_array($recipient) && filled($recipient['email'] ?? null)
            );
            if ($hasRecipientEmail || ! $isDraft || $record->recipients()->doesntExist()) {
                $this->syncRecipients($record, $recipients);
            }
            $this->storeDocuments($record, $request, $user, $validated);
            if (array_key_exists('items', $validated)) {
                $this->syncItems($record, $request, $user, $validated);
            }
            $record->histories()->create([
                'actor_id' => $user?->id,
                'action' => $isReverting && ! $isDraft ? 'reverted_for_revision' : ($isDraft ? 'draft_updated' : 'submitted'),
                'description' => $isReverting && ! $isDraft
                    ? 'Reverted transmittal revised and resubmitted under the same tracking number.'
                    : ($isDraft
                        ? 'Public draft details updated.'
                        : 'Saved draft submitted to the administrator review queue.'),
                'created_at' => now(),
            ]);

            return $isReverting;
        });

        return redirect()->route('transmittals.index', [
            'filter' => $isDraft ? ($isReverting ? 'reverted' : 'uploaded') : 'submitted',
        ])->with('status', $isDraft
            ? "Draft {$transmittal->tracking_number} updated."
            : 'Transmittal submitted for administrator review.');
    }

    private function validateTransmittal(Request $request): array
    {
        return $request->validate([
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'subject' => ['required', 'string', 'max:200'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:5000'],
            'direction' => ['required', Rule::in(['send', 'receive'])],
            'transmittal_date' => ['required', 'date'],
            'origin_office' => ['required', 'string', 'max:160'],
            'recipient_address' => ['nullable', 'string', 'max:2000'],
            'related_company' => ['nullable', 'string', 'max:160'],
            'delivery_method' => ['nullable', Rule::in(['electronic'])],
            'delivery_type' => ['nullable', Rule::in(['by_person', 'registered_mail', 'electronic', 'physical', 'courier', 'other'])],
            'delivery_actions' => ['nullable', 'array', 'max:4'],
            'delivery_actions.*' => ['required', Rule::in(['delivery', 'pick_up', 'drop_off', 'email'])],
            'operations_manager' => ['nullable', 'string', 'max:160'],
            'operations_manager_employee' => ['nullable', 'integer', 'exists:users,id'],
            'approved_by_name' => ['nullable', 'string', 'max:160'],
            'approved_by_employee' => ['nullable', 'integer', 'exists:users,id'],
            'delivered_by_employee' => ['nullable', 'string', 'max:160'],
            'received_by_contact' => ['nullable', 'string', 'max:160'],
            'processing_company_selection' => ['nullable', 'string', 'max:160'],
            'delivered_by' => ['nullable', 'string', 'max:160'],
            'received_by' => ['nullable', 'string', 'max:160'],
            'processing_company' => ['nullable', 'string', 'max:160'],
            'confidentiality' => ['required', Rule::in(['standard', 'confidential', 'restricted'])],
            'recipients' => ['required_if:intent,submit', 'array', 'min:1', 'max:20'],
            'recipients.*.name' => ['nullable', 'string', 'max:120'],
            'recipients.*.email' => ['required_if:intent,submit', 'nullable', 'email', 'max:255', 'distinct:ignore_case'],
            'recipients.*.organization' => ['nullable', 'string', 'max:160'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx'],
            'supporting_attachments' => ['nullable', 'array', 'max:20'],
            'supporting_attachments.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['required', Rule::in(['document', 'receipt', 'proof', 'attachment'])],
            'ocr_tokens' => ['nullable', 'array', 'max:20'],
            'ocr_tokens.*' => ['nullable', 'string', 'max:100000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.particular' => ['nullable', 'string', 'max:200'],
            'items.*.unique_id' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:40'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.remarks' => ['nullable', 'string', 'max:2000'],
            'items.*.attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx'],
        ]);
    }

    private function resolveSignatoryName(array $validated, string $manualField, string $employeeField): ?string
    {
        if (filled($validated[$manualField] ?? null)) {
            return trim($validated[$manualField]);
        }

        return isset($validated[$employeeField])
            ? User::query()->find($validated[$employeeField])?->name
            : null;
    }

    private function resolveSelectedName(array $validated, string $manualField, string $selectionField): ?string
    {
        $name = filled($validated[$manualField] ?? null)
            ? $validated[$manualField]
            : ($validated[$selectionField] ?? null);

        return filled($name) ? trim($name) : null;
    }

    private function signatoryDirectory(): array
    {
        $employees = User::query()->orderBy('id')->get(['id', 'name', 'transmittal_role']);
        $contacts = TransmittalRecipient::query()
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderBy('id')
            ->get(['id', 'name', 'organization'])
            ->unique(fn (TransmittalRecipient $recipient): string => mb_strtolower(trim($recipient->name)))
            ->values();
        $companies = Transmittal::query()->whereNotNull('related_company')->orderBy('id')->pluck('related_company')
            ->merge(TransmittalRecipient::query()->whereNotNull('organization')->orderBy('id')->pluck('organization'))
            ->map(fn ($company): string => trim((string) $company))
            ->filter()
            ->unique(fn (string $company): string => mb_strtolower($company))
            ->values();

        return compact('employees', 'contacts', 'companies');
    }

    private function syncRecipients(Transmittal $transmittal, array $recipients): void
    {
        $transmittal->recipients()->delete();

        foreach ($recipients as $recipient) {
            if (filled($recipient['email'] ?? null)) {
                $transmittal->recipients()->create($recipient);
            }
        }
    }

    private function storeDocuments(Transmittal $transmittal, Request $request, ?User $user, array $validated): void
    {
        foreach ($request->file('documents', []) as $index => $file) {
            $this->storeDocument(
                $transmittal,
                $user,
                $file,
                $validated['categories'][$index] ?? 'document',
                $this->verifiedOcrData($validated['ocr_tokens'][$index] ?? null, $file)
            );
        }

        foreach ($request->file('supporting_attachments', []) as $file) {
            $this->storeDocument($transmittal, $user, $file, 'attachment', null);
        }
    }

    private function syncItems(Transmittal $transmittal, Request $request, ?User $user, array $validated): void
    {
        $existingItems = $transmittal->items()->get()->keyBy('id');
        $retainedIds = [];

        foreach ($validated['items'] ?? [] as $position => $data) {
            $item = isset($data['id']) ? $existingItems->get($data['id']) : null;
            $documentId = $item?->document_id;
            $file = $request->file("items.{$position}.attachment");

            if ($file instanceof UploadedFile) {
                $document = $this->storeDocument($transmittal, $user, $file, 'attachment', null);
                $documentId = $document->id;
            }

            $item = $item ?? $transmittal->items()->make();
            $item->fill([
                'document_id' => $documentId,
                'position' => $position,
                'particular' => $data['particular'] ?? null,
                'unique_id' => $data['unique_id'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'description' => $data['description'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);
            $item->save();
            $retainedIds[] = $item->id;
        }

        $transmittal->items()->whereNotIn('id', $retainedIds ?: [0])->delete();
    }

    private function canEditDraft(Request $request, Transmittal $transmittal): bool
{
    if (! in_array($transmittal->status, ['draft', 'pending_approval', 'rejected'], true)) {
        return false;
    }

    if ($request->user()?->canApproveTransmittals()) {
        return true;
    }

    $token = $request->session()->get("transmittal_draft_tokens.{$transmittal->id}");

    return is_string($token)
        && is_string($transmittal->edit_token_hash)
        && hash_equals($transmittal->edit_token_hash, hash('sha256', $token));
}

    public function show(Request $request, Transmittal $transmittal): View|RedirectResponse
    {
        $transmittal->load([
            'preparer',
            'approver',
            'rejector',
            'recipients.receipt',
            'documents',
            'items.document',
            'proofs.recipient',
            'proofs.sender',
            'proofs.document',
            'receipts.recipient',
            'histories.actor',
            'authorityCode',
        ]);
        $canEditDraft = $this->canEditDraft($request, $transmittal);

        if ($canEditDraft && $transmittal->status === 'rejected') {
            return redirect()->route('transmittals.workspace', $transmittal);
        }

        $canViewPrivateFiles = $this->canViewPrivateFiles($request, $transmittal);
        $canManageLifecycle = $this->canManageLifecycle($request, $transmittal);

        return view('transmittals.show', compact('transmittal', 'canEditDraft', 'canViewPrivateFiles', 'canManageLifecycle'));
    }

    public function approve(Request $request, Transmittal $transmittal, TransmittalApprovalService $approval): RedirectResponse
    {
        $this->authorize('approve', $transmittal);
        $authority = $approval->approve($transmittal, $request->user());

        return redirect()->route('transmittals.show', $transmittal)
            ->with('status', "Approved. Authority code: {$authority->code}");
    }

    public function reject(
        Request $request,
        Transmittal $transmittal,
        TransmittalApprovalService $approval
    ): RedirectResponse {
        $this->authorize('reject', $transmittal);
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $approval->reject($transmittal, $request->user(), $validated['rejection_reason']);

        return redirect()->route('transmittals.show', $transmittal)->with('status', 'Transmittal rejected.');
    }

    public function recall(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($this->canManageLifecycle($request, $transmittal), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $transmittal, $validated): void {
            $record = Transmittal::query()->whereKey($transmittal->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending_approval', 409, 'Only a submission awaiting review can be recalled.');
            $record->forceFill(['status' => 'draft'])->save();
            $record->histories()->create([
                'actor_id' => $request->user()?->id,
                'action' => 'recalled',
                'description' => 'Submission recalled from administrator review.',
                'metadata' => ['reason' => $validated['reason']],
            ]);
        });

        return redirect()->route('transmittals.workspace', $transmittal)
            ->with('status', 'Submission recalled to its draft workspace.');
    }

    public function cancel(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($this->canManageLifecycle($request, $transmittal), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $transmittal, $validated): void {
            $record = Transmittal::query()->whereKey($transmittal->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->status, ['draft', 'pending_approval', 'rejected'], true), 409, 'This transmittal can no longer be cancelled.');
            $previousStatus = $record->status;
            $record->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $request->user()?->id,
                'cancelled_from_status' => $previousStatus,
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ])->save();
            $record->histories()->create([
                'actor_id' => $request->user()?->id,
                'action' => 'cancelled',
                'description' => 'Transmittal cancelled before approval.',
                'metadata' => ['reason' => $validated['reason'], 'previous_status' => $previousStatus],
            ]);
        });

        return redirect()->route('transmittals.show', $transmittal)
            ->with('status', 'Transmittal cancelled and recorded in its history.');
    }

    public function destroy(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($request->user()?->canApproveTransmittals(), 403);
        abort_unless(! $transmittal->trashed(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $transmittal, $validated): void {
            $record = Transmittal::query()->whereKey($transmittal->id)->lockForUpdate()->firstOrFail();
            abort_unless(! $record->trashed(), 404);
            $record->histories()->create([
                'actor_id' => $request->user()->id,
                'action' => 'soft_deleted',
                'description' => 'Transmittal moved to the deleted register.',
                'metadata' => ['reason' => $validated['reason']],
            ]);
            $record->delete();
        });

        return redirect()->route('transmittals.index')
            ->with('status', 'Transmittal moved to Deleted. Its proof and audit history remain intact.');
    }

    public function restore(Request $request, int $transmittal): RedirectResponse
    {
        abort_unless($request->user()?->canApproveTransmittals(), 403);
        $record = DB::transaction(function () use ($request, $transmittal): Transmittal {
            $current = Transmittal::withTrashed()->whereKey($transmittal)->lockForUpdate()->firstOrFail();
            abort_unless($current->trashed(), 409, 'This transmittal is not deleted.');
            $current->restore();
            $current->histories()->create([
                'actor_id' => $request->user()->id,
                'action' => 'restored',
                'description' => 'Transmittal restored from the deleted register.',
            ]);

            return $current;
        });

        return redirect()->route('transmittals.show', $record)
            ->with('status', 'Transmittal restored. Its audit and proof records are unchanged.');
    }

    public function forceDelete(Request $request, int $transmittal): RedirectResponse
    {
        abort_unless($request->user()?->canApproveTransmittals(), 403);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'confirm_tracking_number' => ['required', 'string', 'max:80'],
        ]);
        $documentPaths = [];
        $trackingNumber = '';
        DB::transaction(function () use ($request, $transmittal, $validated, &$documentPaths, &$trackingNumber): void {
            $record = Transmittal::withTrashed()->whereKey($transmittal)->lockForUpdate()->firstOrFail();
            abort_unless($record->trashed(), 409, 'Only a soft-deleted transmittal may be permanently deleted.');
            abort_unless(hash_equals($record->tracking_number, $validated['confirm_tracking_number']), 422, 'Enter the exact TRN to confirm permanent deletion.');
            abort_if(
                $record->approved_snapshot !== null
                    || $record->proofs()->exists()
                    || $record->receipts()->exists()
                    || $record->acknowledgments()->exists(),
                409,
                'Records with approval, proof, receipt, or acknowledgment evidence cannot be permanently deleted.'
            );

            $documentPaths = $record->documents()->pluck('storage_path')->all();
            $trackingNumber = $record->tracking_number;
            $history = $record->histories()->orderBy('id')->get()->map(fn ($entry): array => $entry->getAttributes())->all();
            DB::table('transmittal_lifecycle_archives')->insert([
                'transmittal_id' => $record->id,
                'tracking_number' => $record->tracking_number,
                'actor_id' => $request->user()->id,
                'action' => 'permanently_deleted',
                'reason' => $validated['reason'],
                'archived_record' => json_encode([
                    'transmittal' => $record->getAttributes(),
                    'history' => $history,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            $record->forceDelete();
        });

        $disk = Storage::disk('local');
        $failedPaths = [];
        foreach ($documentPaths as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                $failedPaths[] = $path;
            }
        }
        if ($failedPaths !== []) {
            Log::error('Permanent transmittal deletion left private files behind.', [
                'transmittal_id' => $transmittal,
                'tracking_number' => $trackingNumber,
                'failed_paths' => $failedPaths,
            ]);
            abort(500, 'The transmittal was deleted and its audit archive retained, but private file cleanup failed. Contact an administrator.');
        }

        return redirect()->route('transmittals.index')
            ->with('status', 'Transmittal permanently deleted. Its lifecycle audit archive has been retained.');
    }

    public function revokeRecipientLink(Request $request, Transmittal $transmittal, TransmittalRecipient $recipient): RedirectResponse
    {
        abort_unless($request->user()?->canApproveTransmittals(), 403);
        abort_unless((int) $recipient->transmittal_id === (int) $transmittal->id, 404);
        abort_unless($recipient->sent_at && ! $recipient->acknowledgment_revoked_at, 409, 'This acknowledgment link is not active.');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $transmittal, $recipient, $validated): void {
            $revokedAt = now();
            $recipient->forceFill(['acknowledgment_revoked_at' => $revokedAt])->save();
            $transmittal->acknowledgments()->create([
                'recipient_id' => $recipient->id,
                'event' => 'link_revoked',
                'details' => ['reason' => $validated['reason'], 'revoked_by' => $request->user()->id],
                'occurred_at' => $revokedAt,
            ]);
            $transmittal->histories()->create([
                'actor_id' => $request->user()->id,
                'action' => 'recipient_link_revoked',
                'description' => "Acknowledgment link revoked for {$recipient->email}.",
                'metadata' => ['reason' => $validated['reason'], 'recipient_id' => $recipient->id],
                'created_at' => $revokedAt,
            ]);
        });

        return redirect()->route('transmittals.show', $transmittal)
            ->with('status', 'Recipient acknowledgment link revoked.');
    }


public function transmit(
    Request $request,
    Transmittal $transmittal,
    BrevoTransmittalService $brevo
): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse {
    $this->authorize('transmit', $transmittal);

    try {
        $sent = \Illuminate\Support\Facades\Cache::lock(
            "ordo:transmittal:transmit:{$transmittal->id}",
            360
        )->block(10, function () use ($request, $transmittal, $brevo): int {
            $sent = 0;

            foreach ($transmittal->recipients as $recipient) {
                $recipient->refresh();

                if ($recipient->sent_at) {
                    continue;
                }

                $token = \Illuminate\Support\Str::random(64);
                $messageId = $brevo->sendAcknowledgmentRequest($recipient, $token);
                $sentAt = now();
                $transactionReference = 'TXN-'.strtoupper(
                    \Illuminate\Support\Str::random(18)
                );

                \Illuminate\Support\Facades\DB::transaction(
                    function () use (
                        $request,
                        $transmittal,
                        $recipient,
                        $token,
                        $messageId,
                        $sentAt,
                        $transactionReference
                    ): void {
                        $recipient->forceFill([
                            'acknowledgment_token_hash' => hash('sha256', $token),
                            'acknowledgment_expires_at' => $sentAt->copy()->addDays(
                                config('ordo.brevo.acknowledgment_days')
                            ),
                            'sent_at' => $sentAt,
                        ])->save();

                        $transmittal->proofs()->create([
                            'recipient_id' => $recipient->id,
                           'sent_by' => $request->user()?->id,
                            'channel' => 'email',
                            'status' => 'sent',
                            'provider_message_id' => $messageId !== ''
                                ? $messageId
                                : null,
                            'transaction_reference' => $transactionReference,
                            'transmitted_at' => $sentAt,
                            'provider_metadata' => [
                                'provider' => 'brevo',
                                'transaction_reference' => $transactionReference,
                            ],
                        ]);

                        $transmittal->histories()->create([
                            'actor_id' => $request->user()->id,
                            'action' => 'transmitted',
                            'description' => "Secure acknowledgment request emailed to {$recipient->email}.",
                            'created_at' => $sentAt,
                        ]);
                    }
                );

                $sent++;
            }

            if ($transmittal->status === 'approved' && $sent > 0) {
                $transmittal->forceFill(['status' => 'sent'])->save();
            }

            return $sent;
        });

        $message = $sent > 0
            ? "{$sent} recipient notification(s) sent through Brevo."
            : 'All recipients have already been notified.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'sent' => $sent,
            ]);
        }

        return redirect()
            ->route('transmittals.show', $transmittal)
            ->with('status', $message);
    } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
        throw $exception;
    } catch (\Throwable $exception) {
        report($exception);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send the email. Please check the server logs or Brevo configuration.',
            ], 500);
        }

        return redirect()
            ->route('transmittals.show', $transmittal)
            ->withErrors([
                'email' => 'Unable to send the email. Please check the server logs or Brevo configuration.',
            ]);
    }
}


    public function uploadProof(Request $request, Transmittal $transmittal): RedirectResponse
    {
        // Tinanggal o inalis natin ang abort_unless(..., 403) para hindi na mag-forbidden

        $rules = [
            'recipient_id' => [
                'nullable',
                Rule::exists('transmittal_recipients', 'id')
                    ->where('transmittal_id', $transmittal->id),
            ],
            'proof' => ['required', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png,tif,tiff,mp4,mov,webm'],
            'channel' => ['required', Rule::in(['email', 'courier', 'in_person', 'registered_mail', 'portal', 'manual', 'other'])],
            'proof_type' => ['required', Rule::in(['delivery_receipt', 'email_confirmation', 'courier_receipt', 'other'])],
            'sender_name' => ['required', 'string', 'max:160'],
            'sender_position' => ['nullable', 'string', 'max:160'],
            'receiver_name' => ['required', 'string', 'max:160'],
            'receiver_email' => ['nullable', 'email', 'max:254'],
            'acknowledged_by' => ['nullable', 'required_with:acknowledged_at', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:200'],
            'device' => ['nullable', 'string', 'max:300'],
            'device_category' => ['required', Rule::in(['desktop', 'laptop', 'mobile', 'tablet', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'proof_date' => ['nullable', 'date'],
            'transmission_at' => ['required', 'date'],
            'acknowledged_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ocr_token' => ['nullable', 'string', 'max:100000'],
            'return_to' => ['nullable', Rule::in(['workspace', 'show'])],
        ];
        if (filled($request->input('ocr_token'))) {
            $rules['ocr_reviewed'] = ['required', 'accepted'];
        }
        $validated = $request->validate($rules);

        /** @var UploadedFile $file */
        $file = $validated['proof'];
        $ocrData = $this->verifiedOcrData($validated['ocr_token'] ?? null, $file);

        $document = $this->storeDocument($transmittal, $request->user(), $file, 'proof', $ocrData);
        $uploadedAt = now();
        $transmissionAt = Carbon::parse($validated['transmission_at']);
        $acknowledgedAt = filled($validated['acknowledged_at'] ?? null)
            ? Carbon::parse($validated['acknowledged_at'])
            : null;
        $transactionReference = 'TXN-'.strtoupper(Str::random(18));

        try {
            DB::transaction(function () use ($request, $transmittal, $validated, $document, $uploadedAt, $transmissionAt, $acknowledgedAt, $transactionReference, $ocrData): void {
                $transmittal->proofs()->create([
                    'recipient_id' => $validated['recipient_id'],
                    'document_id' => $document->id,
                    'sent_by' => $request->user()?->id,
                    'channel' => $validated['channel'],
                    'status' => $acknowledgedAt ? 'acknowledged' : 'uploaded',
                    'transaction_reference' => $transactionReference,
                    'acknowledged_at' => $acknowledgedAt,
                    'transmitted_at' => $transmissionAt,
                    'provider_metadata' => [
                        'source' => $ocrData === null ? 'manual_upload' : 'ocr_assisted_upload',
                        'proof_type' => $validated['proof_type'],
                        'sender_name' => $validated['sender_name'],
                        'sender_position' => $validated['sender_position'] ?? null,
                        'sender_location' => $validated['location'] ?? null,
                        'location' => $validated['location'] ?? null,
                        'location_source' => filled($validated['location'] ?? null) ? 'user_entered' : null,
                        'device_category' => $validated['device_category'] ?? null,
                        'device' => $validated['device'] ?? null,
                        'device_source' => filled($validated['device'] ?? null) || filled($validated['device_category'] ?? null) ? 'user_entered' : null,
                        'transmission_at_source' => 'user_entered',
                        'recorded_by_name' => $request->user()?->name ?? 'Public Submitter',
                        'recorded_at' => $uploadedAt->toIso8601String(),
                        'receiver_name' => $validated['receiver_name'],
                        'receiver_email' => $validated['receiver_email'] ?? null,
                        'acknowledged_by' => $validated['acknowledged_by'] ?? null,
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                        'reference_number' => $validated['reference_number'] ?? null,
                        'proof_date' => $transmissionAt->toDateString(),
                        'transmission_at' => $transmissionAt->toIso8601String(),
                        'acknowledged_at' => $acknowledgedAt?->toIso8601String(),
                        'notes' => $validated['notes'] ?? null,
                        'ocr_suggestions' => $ocrData['fields'] ?? [],
                        'ocr_field_metadata' => $ocrData['field_metadata'] ?? [],
                        'reviewed_values' => [
                            'receiver_name' => $validated['receiver_name'],
                            'sender_name' => $validated['sender_name'],
                            'sender_position' => $validated['sender_position'] ?? null,
                            'sender_location' => $validated['location'] ?? null,
                            'receiver_email' => $validated['receiver_email'] ?? null,
                            'acknowledged_by' => $validated['acknowledged_by'] ?? null,
                            'location' => $validated['location'] ?? null,
                            'device' => $validated['device'] ?? null,
                            'device_category' => $validated['device_category'] ?? null,
                            'reference_number' => $validated['reference_number'] ?? null,
                            'proof_date' => $transmissionAt->toDateString(),
                            'transmission_at' => $transmissionAt->toIso8601String(),
                            'acknowledged_at' => $acknowledgedAt?->toIso8601String(),
                            'notes' => $validated['notes'] ?? null,
                        ],
                        'reviewed_by' => $request->user()?->id,
                        'reviewed_at' => $uploadedAt->toIso8601String(),
                        'confirmed_by' => $request->user()?->id,
                        'confirmed_at' => $uploadedAt->toIso8601String(),
                        'initial_confirmation' => [
                            'user_id' => $request->user()?->id,
                            'user_name' => $request->user()?->name ?? 'Public Submitter',
                            'at' => $uploadedAt->toIso8601String(),
                        ],
                        'entry_mode' => $ocrData === null ? 'manual' : 'ocr',
                        'ocr_confirmed' => $ocrData !== null,
                    ],
                ]);

                $transmittal->histories()->create([
                    'actor_id' => $request->user()?->id,
                    'action' => 'proof_uploaded',
                    'description' => $acknowledgedAt
                        ? "Acknowledgment proof recorded for {$validated['acknowledged_by']}."
                        : "Manual proof uploaded for {$validated['receiver_name']}.",
                    'metadata' => [
                        'document_id' => $document->id,
                        'transaction_reference' => $transactionReference,
                    ],
                    'created_at' => $uploadedAt,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($document->storage_path);
            throw $exception;
        }

        $destination = ($validated['return_to'] ?? 'show') === 'workspace'
            ? 'transmittals.workspace'
            : 'transmittals.show';

        return redirect()->route($destination, $transmittal)
            ->with('status', 'Proof of Transmission uploaded and recorded.');
    }

    public function updateProof(Request $request, Transmittal $transmittal, int $proof): RedirectResponse
    {
        // Tinanggal din natin ang abort_unless(..., 403) dito

        $record = $transmittal->proofs()->findOrFail($proof);
        $validated = $request->validate([
            'recipient_id' => ['nullable', Rule::exists('transmittal_recipients', 'id')->where('transmittal_id', $transmittal->id)],
            'proof' => ['nullable', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png,tif,tiff,mp4,mov,webm'],
            'channel' => ['required', Rule::in(['email', 'courier', 'in_person', 'registered_mail', 'portal', 'manual', 'other'])],
            'proof_type' => ['required', Rule::in(['delivery_receipt', 'email_confirmation', 'courier_receipt', 'other'])],
            'sender_name' => ['required', 'string', 'max:160'],
            'sender_position' => ['nullable', 'string', 'max:160'],
            'receiver_name' => ['required', 'string', 'max:160'],
            'receiver_email' => ['nullable', 'email', 'max:254'],
            'acknowledged_by' => ['nullable', 'required_with:acknowledged_at', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:200'],
            'device' => ['nullable', 'string', 'max:300'],
            'device_category' => ['required', Rule::in(['desktop', 'laptop', 'mobile', 'tablet', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'transmission_at' => ['nullable', 'date'],
            'acknowledged_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'edit_reason' => ['required', 'string', 'max:500'],
            'return_to' => ['nullable', Rule::in(['workspace', 'show'])],
        ]);
        
        // (Ipagpatuloy ang natitirang code ng updateProof mo rito...)

        $document = null;
        $metadata = $record->provider_metadata ?? [];
        $fields = [
            'sender_name' => $validated['sender_name'],
            'sender_position' => $validated['sender_position'] ?? null,
            'receiver_name' => $validated['receiver_name'],
            'receiver_email' => $validated['receiver_email'] ?? null,
            'location' => $validated['location'] ?? null,
            'device_category' => $validated['device_category'] ?? null,
            'device' => $validated['device'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'transmission_at' => filled($validated['transmission_at'] ?? null)
                ? Carbon::parse($validated['transmission_at'])->toIso8601String()
                : $record->transmitted_at?->toIso8601String(),
            'acknowledged_by' => $validated['acknowledged_by'] ?? null,
            'acknowledged_at' => filled($validated['acknowledged_at'] ?? null) ? Carbon::parse($validated['acknowledged_at'])->toIso8601String() : null,
            'notes' => $validated['notes'] ?? null,
        ];
        $previous = collect($fields)->map(fn ($value, $key) => match ($key) {
            'sender_name' => $metadata['sender_name'] ?? null,
            'sender_position' => $metadata['sender_position'] ?? null,
            'receiver_name' => $metadata['receiver_name'] ?? null,
            'receiver_email' => $metadata['receiver_email'] ?? null,
            'location' => $metadata['location'] ?? null,
            'device_category' => $metadata['device_category'] ?? null,
            'device' => $metadata['device'] ?? null,
            'reference_number' => $metadata['reference_number'] ?? null,
            'transmission_at' => $record->transmitted_at?->toIso8601String(),
            'acknowledged_by' => $metadata['acknowledged_by'] ?? null,
            'acknowledged_at' => $record->acknowledged_at?->toIso8601String(),
            'notes' => $metadata['notes'] ?? null,
        })->all();
        $editedAt = now();
        $transmissionAt = filled($validated['transmission_at'] ?? null)
            ? Carbon::parse($validated['transmission_at'])
            : $record->transmitted_at;
        $metadata['edit_history'] ??= [];
        $metadata['edit_history'][] = [
            'edited_by' => $request->user()->id,
            'edited_by_name' => $request->user()->name,
            'edited_at' => $editedAt->toIso8601String(),
            'reason' => $validated['edit_reason'],
            'previous' => $previous,
            'updated' => $fields,
        ];
        $metadata = array_merge($metadata, [
            'sender_name' => $validated['sender_name'],
            'sender_position' => $validated['sender_position'] ?? null,
            'sender_location' => $validated['location'] ?? null,
            'location' => $validated['location'] ?? null,
            'location_source' => filled($validated['location'] ?? null) ? 'user_entered' : null,
            'device_category' => $validated['device_category'] ?? null,
            'device' => $validated['device'] ?? null,
            'device_source' => filled($validated['device'] ?? null) || filled($validated['device_category'] ?? null) ? 'user_entered' : null,
            'reference_number' => $validated['reference_number'] ?? null,
            'receiver_name' => $validated['receiver_name'],
            'receiver_email' => $validated['receiver_email'] ?? null,
            'acknowledged_by' => $validated['acknowledged_by'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'reviewed_values' => $fields,
            'last_edited_by' => $request->user()->id,
            'last_edited_at' => $editedAt->toIso8601String(),
            'transmission_at_source' => filled($validated['transmission_at'] ?? null)
                ? 'user_entered'
                : ($metadata['transmission_at_source'] ?? null),
        ]);

        try {
            DB::transaction(function () use ($record, $transmittal, $request, $validated, $metadata, &$document, $editedAt, $transmissionAt): void {
                if ($request->file('proof')) {
                    $document = $this->storeDocument($transmittal, $request->user(), $validated['proof'], 'proof', null);
                }
                $editIndex = array_key_last($metadata['edit_history']);
                $metadata['edit_history'][$editIndex]['previous']['document_id'] = $record->document_id;
                $metadata['edit_history'][$editIndex]['previous']['document_name'] = $record->document?->original_name;
                $metadata['edit_history'][$editIndex]['updated']['document_id'] = $document?->id ?? $record->document_id;
                $metadata['edit_history'][$editIndex]['updated']['document_name'] = $document?->original_name ?? $record->document?->original_name;
                $record->forceFill([
                    'recipient_id' => $validated['recipient_id'] ?? null,
                    'document_id' => $document?->id ?? $record->document_id,
                    'channel' => $validated['channel'],
                    'status' => filled($validated['acknowledged_at'] ?? null) ? 'acknowledged' : 'uploaded',
                    'transmitted_at' => $transmissionAt,
                    'acknowledged_at' => filled($validated['acknowledged_at'] ?? null) ? Carbon::parse($validated['acknowledged_at']) : null,
                    'provider_metadata' => $metadata,
                ])->save();

                $transmittal->histories()->create([
                    'actor_id' => $request->user()->id,
                    'action' => 'proof_updated',
                    'description' => "Proof record {$record->transaction_reference} updated: {$validated['edit_reason']}",
                    'metadata' => ['proof_id' => $record->id, 'edited_at' => $editedAt->toIso8601String()],
                    'created_at' => $editedAt,
                ]);
            });
        } catch (Throwable $exception) {
            if ($document) {
                Storage::disk('local')->delete($document->storage_path);
            }
            throw $exception;
        }

        $destination = ($validated['return_to'] ?? 'show') === 'workspace' ? 'transmittals.workspace' : 'transmittals.show';

        return redirect()->route($destination, $transmittal)->with('status', 'Proof record updated with an audit history entry.');
    }

    public function download(Request $request, Transmittal $transmittal, int $document): StreamedResponse
    {

        $record = $transmittal->documents()->findOrFail($document);

        return $this->streamDocument($transmittal, $record);
    }

    public function downloadForRecipient(string $token, TransmittalDocument $document): StreamedResponse
    {
        $recipient = $this->recipientForToken($token);
        abort_unless($recipient->transmittal_id === $document->transmittal_id, 404);

        return $this->streamDocument($document->transmittal, $document);
    }

    public function pdf(Request $request, Transmittal $transmittal): Response
    {
        abort_unless($this->canViewPrivateFiles($request, $transmittal), 404);

        $transmittal->load(['preparer', 'approver', 'rejector', 'recipients', 'documents', 'items.document', 'proofs.recipient', 'receipts', 'histories']);
        foreach ($transmittal->documents as $document) {
            $this->verifyDocumentIntegrity($transmittal, $document);
        }

        $pdf = Pdf::loadView('transmittals.pdf', compact('transmittal'))
            ->setPaper('a4');

        return $this->markPrivateFileResponse($pdf->download($transmittal->tracking_number.'.pdf'));
    }

    public function receiptPdf(Request $request, Transmittal $transmittal, int $receipt): Response
    {
        abort_unless($this->canViewPrivateFiles($request, $transmittal), 404);

        $transmittal->load(['recipients', 'receipts.recipient']);
        $record = $transmittal->receipts->firstWhere('id', $receipt);
        abort_unless($record, 404);

        return $this->renderReceiptPdf($transmittal, $record);
    }

    public function receiptPdfForRecipient(string $token, int $receipt): Response
    {
        $recipient = $this->recipientForToken($token);
        $record = $recipient->receipt;
        abort_unless($record && $record->id === $receipt, 404);

        return $this->renderReceiptPdf($recipient->transmittal, $record);
    }

    private function streamDocument(Transmittal $transmittal, TransmittalDocument $record): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($record->storage_path), 404);
        $this->verifyDocumentIntegrity($transmittal, $record);

        // Ginamit natin ang inline para mag-preview ito sa browser sa halip na mag-download
        $response = Storage::disk('local')->download($record->storage_path, $record->original_name);
        $response->headers->set('Content-Disposition', 'inline; filename="' . $record->original_name . '"');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function renderReceiptPdf(Transmittal $transmittal, TransmittalReceipt $receipt): Response
    {
        $receipt->loadMissing('recipient');
        $receipt->recipient?->load([
            'proofs' => fn ($query) => $query
                ->where('status', 'acknowledged')
                ->latest('acknowledged_at')
                ->limit(1),
        ]);
        $acknowledgmentProof = $receipt->recipient?->proofs->first();

        $response = Pdf::loadView('transmittals.receipt', [
            'transmittal' => $transmittal,
            'receipt' => $receipt,
            'snapshot' => $receipt->snapshot ?? [],
            'acknowledgmentProof' => $acknowledgmentProof,
        ])->setPaper('a4')->download($receipt->receipt_code.'.pdf');

        return $this->markPrivateFileResponse($response);
    }

    private function markPrivateFileResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }


private function storeDocument(
    Transmittal $transmittal,
    ?User $user,
    UploadedFile $file,
    string $category,
    ?array $ocrData
): TransmittalDocument {
    $directory = "transmittals/{$transmittal->id}";
    $extension = strtolower($file->guessExtension() ?: 'bin');

    // Generate a unique filename for every stored document.
    $filename = (string) \Illuminate\Support\Str::uuid().'.'.$extension;

    $path = $file->storeAs($directory, $filename, 'local');

    if (! is_string($path) || $path === '') {
        throw new \RuntimeException('Unable to store the uploaded document.');
    }

    $disk = Storage::disk('local');
    $realPath = $disk->path($path);

    if (! is_file($realPath)) {
        $disk->delete($path);

        throw new \RuntimeException('Unable to verify the uploaded file content.');
    }

    try {
        return $transmittal->documents()->create([
            'uploaded_by' => $user?->id,
            'category' => $category,
            'original_name' => basename($file->getClientOriginalName()),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $realPath),
            'ocr_text' => $ocrData['text'] ?? null,
            'extracted_data' => $ocrData === null
                ? null
                : array_merge(
                    $ocrData['fields'],
                    ['_field_metadata' => $ocrData['field_metadata'] ?? []]
                ),
        ]);
    } catch (\Throwable $exception) {
        $disk->delete($path);

        throw $exception;
    }
}


    private function verifiedOcrData(?string $token, UploadedFile $file): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $analysis = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            throw ValidationException::withMessages([
                'documents' => 'OCR analysis has expired or is invalid. Please classify the files again.',
            ]);
        }

        $path = $file->getRealPath();
        if (! is_string($path)
            || ! is_array($analysis)
            || ($analysis['name'] ?? null) !== basename($file->getClientOriginalName())
            || ! hash_equals((string) ($analysis['sha256'] ?? ''), hash_file('sha256', $path) ?: '')
            || ! in_array($analysis['category'] ?? null, ['document', 'receipt', 'proof', 'attachment'], true)
            || ! is_string($analysis['text'] ?? null)
            || ! is_array($analysis['fields'] ?? null)
            || ! is_array($analysis['field_metadata'] ?? [])) {
            throw ValidationException::withMessages([
                'documents' => 'The uploaded files do not match the completed OCR analysis.',
            ]);
        }

        return $analysis;
    }

    private function verifyDocumentIntegrity(Transmittal $transmittal, TransmittalDocument $document): void
    {
        $path = Storage::disk('local')->path($document->storage_path);
        $digest = is_file($path) ? hash_file('sha256', $path) : false;

        if (! is_string($digest) || ! hash_equals($document->sha256, $digest)) {
            Log::error('Transmittal attachment integrity check failed.', [
                'transmittal_id' => $transmittal->id,
                'document_id' => $document->id,
            ]);
            abort(409, 'Attachment integrity verification failed. Contact a workspace administrator.');
        }
    }

    private function canViewPrivateFiles(Request $request, Transmittal $transmittal): bool
    {
        return ($request->user()?->can('view', $transmittal) ?? false)
            || $this->canEditDraft($request, $transmittal);
    }

    private function canManageLifecycle(Request $request, Transmittal $transmittal): bool
    {
        if ($request->user()?->canApproveTransmittals()) {
            return true;
        }

        $token = $request->session()->get("transmittal_draft_tokens.{$transmittal->id}");

        return is_string($token)
            && is_string($transmittal->edit_token_hash)
            && hash_equals($transmittal->edit_token_hash, hash('sha256', $token));
    }

    private function recipientForToken(string $token): TransmittalRecipient
    {
        abort_if(strlen($token) < 32 || strlen($token) > 100, 404);

        $recipient = TransmittalRecipient::query()
            ->with(['transmittal', 'receipt'])
            ->where('acknowledgment_token_hash', hash('sha256', $token))
            ->where('acknowledgment_expires_at', '>', now())
            ->whereNull('acknowledgment_revoked_at')
            ->first();

        abort_unless($recipient && $recipient->sent_at, 404);

        return $recipient;
    }
}

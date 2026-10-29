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
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class TransmittalController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->routeIs('transmittal.index') || $request->is('transmittal')) {
            $prefill = $request->query('prefill');

            if (is_string($prefill)) {
                $decodedPrefill = json_decode($prefill, true);
                $prefill = is_array($decodedPrefill) ? $decodedPrefill : null;
            }

            $totalCount = Transmittal::count();
            $awaitingApprovalCount = Transmittal::whereIn('status', ['pending_approval', 'submitted'])->count();
            $awaitingAcknowledgmentCount = Transmittal::whereIn('status', ['sent', 'approved'])->count();
            $completedThisMonthCount = Transmittal::whereIn('status', ['acknowledged', 'completed'])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            return view('transmittal.index', [
                'prefill' => is_array($prefill) ? $prefill : null,
                'contactOptions' => $this->transmittalContactOptions(),
                'employeeOptions' => $this->transmittalEmployeeOptions(),
                'companyOptions' => $this->transmittalCompanyOptions(),
                'corporateContext' => $this->transmittalCorporateContext(),
                'metrics' => [
                    'total' => $totalCount,
                    'awaiting_approval' => $awaitingApprovalCount,
                    'awaiting_acknowledgment' => $awaitingAcknowledgmentCount,
                    'completed_this_month' => $completedThisMonthCount,
                ],
            ]);
        }

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
            'documents' => ['nullable', 'array', 'max:50'],
            'documents.*' => ['required', 'file', 'max:'.(int) ceil(config('ordo.upload.max_bytes') / 1024), 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx,txt'],
            'categories' => ['nullable', 'array', 'max:50'],
            'categories.*' => ['required', Rule::in(['document', 'receipt', 'proof', 'attachment'])],
            'ocr_tokens' => ['nullable', 'array', 'max:50'],
            'ocr_tokens.*' => ['nullable', 'string', 'max:100000'],
            'upload_ids' => ['nullable', 'array', 'max:50'],
            'upload_ids.*' => ['required', 'uuid', 'distinct'],
            'upload_categories' => ['nullable', 'array', 'max:50'],
            'upload_categories.*' => ['required', Rule::in(['document', 'receipt', 'proof', 'attachment'])],
            'upload_ocr_tokens' => ['nullable', 'array', 'max:50'],
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
        if (count($directFiles) + count($uploadIds) > config('ordo.ocr.max_files', 50)) {
            throw ValidationException::withMessages(['documents' => 'A maximum of '.config('ordo.ocr.max_files', 50).' files can be attached.']);
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
        if ($transmittal->status === 'draft') {
            abort_unless($this->canEditDraft($request, $transmittal), 404);
        }
        
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
        $this->associateLegacyItemDocumentsForPreview($transmittal);

        return view('transmittals.workspace', [
            'transmittal' => $transmittal,
            'canViewPrivateFiles' => $this->canViewPrivateFiles($request, $transmittal),
            'canViewAttachments' => $this->canViewPrivateAttachments($request, $transmittal),
            'canManageLifecycle' => $this->canManageLifecycle($request, $transmittal),
            'canUploadProof' => true,
            'contactOptions' => $this->transmittalContactOptions(),
            'employeeOptions' => $this->transmittalEmployeeOptions(),
            'companyOptions' => $this->transmittalCompanyOptions(),
        ] + $this->signatoryDirectory());
    }

    private function associateLegacyItemDocumentsForPreview(Transmittal $transmittal): void
    {
        $documents = $transmittal->documents->values();
        $items = $transmittal->items->values();

        if ($documents->isEmpty() || $items->isEmpty()) {
            return;
        }

        $usedDocumentIds = $items->pluck('document_id')->filter()->all();
        $normalize = static fn (string $value): string => preg_replace(
            '/[^a-z0-9]/',
            '',
            mb_strtolower($value)
        ) ?? '';

        foreach ($items as $index => $item) {
            if ($item->document_id || $item->document) {
                continue;
            }

            $itemText = $normalize(implode(' ', array_filter([
                $item->particular,
                $item->unique_id,
                $item->description,
                $item->remarks,
            ])));

            $document = $documents->first(function (TransmittalDocument $candidate) use ($itemText, $normalize, $usedDocumentIds): bool {
                if (in_array($candidate->id, $usedDocumentIds, true)) {
                    return false;
                }

                $name = pathinfo($candidate->original_name, PATHINFO_FILENAME);
                $normalizedName = $normalize($name);

                return strlen($normalizedName) >= 6 && str_contains($itemText, $normalizedName);
            });

            if (! $document && $documents->count() === $items->count()) {
                $candidate = $documents->get($index);
                if ($candidate && ! in_array($candidate->id, $usedDocumentIds, true)) {
                    $document = $candidate;
                }
            }

            if ($document) {
                $item->setRelation('document', $document);
                $usedDocumentIds[] = $document->id;
            }
        }
    }

    public function edit(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($this->canEditDraft($request, $transmittal), 404);

        return redirect()->route('transmittals.index', ['edit' => $transmittal->id]);
    }

    public function analyze(Request $request, TransmittalOcrService $ocr): JsonResponse
    {
        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:'.config('ordo.ocr.max_files', 50)],
            'documents.*' => ['required', 'file', 'max:'.(int) ceil(config('ordo.upload.max_bytes') / 1024), 'mimes:pdf,jpg,jpeg,png,tif,tiff'],
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

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $validated = $this->validateTransmittal($request);
        $isDraft = $validated['intent'] === 'draft';
        $user = $request->user();
        $editToken = Str::random(64);

        try {
            $transmittal = DB::transaction(function () use ($validated, $request, $user, $editToken, $isDraft): Transmittal {
                $transmittal = Transmittal::query()->create([
                    'tracking_number' => 'PENDING-'.Str::uuid(),
                    'external_reference' => $validated['external_reference'] ?? null,
                    'subject' => $validated['subject'],
                    'purpose' => $validated['purpose'],
                    'direction' => $validated['direction'],
                    'mode' => strtoupper($validated['direction']),
                    'transmittal_date' => $validated['transmittal_date'],
                    'origin_office' => $validated['origin_office'],
                    'recipient_address' => $validated['recipient_address'] ?? null,
                    'related_company' => $validated['related_company'] ?? null,
                    'delivery_method' => $validated['delivery_method'] ?? 'electronic',
                    'delivery_type' => $validated['delivery_type'] ?? 'by_person',
                    'by_person_who' => $validated['by_person_who'] ?? null,
                    'registered_mail_provider' => filled($validated['registered_mail_provider_other'] ?? null) && ($validated['registered_mail_provider'] ?? '') === 'Others'
                        ? $validated['registered_mail_provider_other']
                        : ($validated['registered_mail_provider'] ?? null),
                    'electronic_method' => $validated['electronic_method'] ?? (($validated['delivery_type'] ?? '') === 'electronic' ? 'Email' : null),
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
                $uploadedDocuments = $this->storeDocuments($transmittal, $request, $user, $validated);
                $this->syncItems($transmittal, $request, $user, $validated, $uploadedDocuments);

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
        } catch (\Throwable $e) {
            \Log::error('Transmittal save failed: ' . $e->getMessage(), ['exception' => $e]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Kindly fill up the required form fields properly before proceeding.',
                    'errors' => ['form' => ['Kindly fill up the required form fields properly before proceeding.']],
                ], 422);
            }
            return back()->withInput()->withErrors(['form' => 'Kindly fill up the required form fields properly before proceeding.']);
        }

        $tokens = $request->session()->get('transmittal_draft_tokens', []);
        $tokens[$transmittal->id] = $editToken;
        $request->session()->put('transmittal_draft_tokens', $tokens);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $isDraft
                    ? "Draft {$transmittal->tracking_number} saved successfully."
                    : 'Transmittal submitted for review successfully.',
                'tracking_number' => $transmittal->tracking_number,
                'id' => $transmittal->id,
                'is_draft' => $isDraft,
                'redirect_url' => route('transmittal.index', ['filter' => $isDraft ? 'uploaded' : 'submitted']),
            ]);
        }

        if (! $isDraft) {
            return redirect()->route('transmittal.index', ['filter' => 'submitted'])
                ->with('status', 'Transmittal submitted for administrator review.');
        }

        return redirect()->route('transmittals.index', [
            'filter' => 'uploaded',
        ])->with('status', "Draft {$transmittal->tracking_number} saved. You can continue editing it from this browser.");
    }

    public function update(Request $request, Transmittal $transmittal): RedirectResponse
    {
        abort_unless($this->canEditDraft($request, $transmittal), 404);
        $validated = $this->validateTransmittal($request);
        $isDraft = $validated['intent'] === 'draft';
        $user = $request->user();

        try {
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
                    'mode' => strtoupper($validated['direction']),
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
                    'by_person_who' => array_key_exists('by_person_who', $validated) ? $validated['by_person_who'] : $record->by_person_who,
                    'registered_mail_provider' => filled($validated['registered_mail_provider_other'] ?? null) && ($validated['registered_mail_provider'] ?? '') === 'Others'
                        ? $validated['registered_mail_provider_other']
                        : ($validated['registered_mail_provider'] ?? $record->registered_mail_provider),
                    'electronic_method' => $validated['electronic_method'] ?? (($validated['delivery_type'] ?? '') === 'electronic' ? 'Email' : $record->electronic_method),
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
                    'workflow_status' => $isDraft ? ($isReverting ? 'Reverted' : ($record->workflow_status ?? 'Uploaded')) : 'Submitted',
                    'approval_status' => $isDraft ? ($record->approval_status ?? 'Draft') : 'Pending',
                    'submitted_by' => $isDraft ? $record->submitted_by : ($user?->id ?? $record->submitted_by),
                    'edit_token_hash' => $record->edit_token_hash,
                ])->save();

                $recipients = $validated['recipients'] ?? [];
                $hasRecipientInfo = collect($recipients)->contains(
                    fn ($recipient): bool => is_array($recipient) && (filled($recipient['email'] ?? null) || filled($recipient['name'] ?? null) || filled($recipient['organization'] ?? null))
                );
                if ($hasRecipientInfo || ! $isDraft || $record->recipients()->doesntExist()) {
                    $this->syncRecipients($record, $recipients);
                }
                $uploadedDocuments = $this->storeDocuments($record, $request, $user, $validated);
                if (array_key_exists('items', $validated)) {
                    $this->syncItems($record, $request, $user, $validated, $uploadedDocuments);
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
        } catch (\Throwable $e) {
            \Log::error('Transmittal update failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->withInput()->withErrors(['form' => 'Kindly fill up the required form fields properly before proceeding.']);
        }

        if (! $isDraft) {
            return redirect()->route('transmittal.index', ['filter' => 'submitted'])
                ->with('status', 'Transmittal submitted for administrator review.');
        }

        return redirect()->route('transmittal.index', [
            'filter' => $isReverting ? 'reverted' : 'uploaded',
        ])->with('status', "Draft {$transmittal->tracking_number} updated.");
    }

    private function validateTransmittal(Request $request): array
    {
        if ($request->has('items') && is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            if (is_array($decoded)) {
                $request->merge(['items' => $decoded]);
            }
        }

        return $request->validate([
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'subject' => ['required', 'string', 'max:200'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:5000'],
            'direction' => ['required', Rule::in(['send', 'receive'])],
            'transmittal_date' => ['required', 'date'],
            'origin_office' => ['required', 'string', 'max:160'],
            'recipient_address' => ['nullable', 'string', 'max:2000'],
            'delivery_method' => ['nullable', 'string', 'max:60'],
            'delivery_type' => ['nullable', 'string', 'max:60'],
            'by_person_who' => ['nullable', 'string', 'max:160'],
            'registered_mail_provider' => ['nullable', 'string', 'max:160'],
            'registered_mail_provider_other' => ['nullable', 'string', 'max:160'],
            'electronic_method' => ['nullable', 'string', 'max:160'],
            'delivery_actions' => ['nullable', 'array', 'max:4'],
            'delivery_actions.*' => ['nullable', 'string', 'max:40'],
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
            'recipients.*.email' => [
                Rule::requiredIf(fn () => $request->input('intent') === 'submit' && ($request->input('delivery_type') === 'electronic' || in_array('email', (array) $request->input('delivery_actions', []), true))),
                'nullable',
                'email',
                'max:255',
                'distinct:ignore_case'
            ],
            'recipients.*.organization' => ['nullable', 'string', 'max:160'],
            'documents' => ['nullable', 'array', 'max:'.config('ordo.ocr.max_files', 50)],
            'documents.*' => ['file', 'max:'.(int) ceil(config('ordo.upload.max_bytes') / 1024), 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx'],
            'supporting_attachments' => ['nullable', 'array', 'max:'.config('ordo.ocr.max_files', 50)],
            'supporting_attachments.*' => ['file', 'max:'.(int) ceil(config('ordo.upload.max_bytes') / 1024), 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['required', Rule::in(['document', 'receipt', 'proof', 'attachment'])],
            'ocr_tokens' => ['nullable', 'array', 'max:'.config('ordo.ocr.max_files', 50)],
            'ocr_tokens.*' => ['nullable', 'string', 'max:100000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.document_id' => ['nullable', 'integer'],
            'items.*.document_index' => ['nullable', 'integer', 'min:0'],
            'items.*.particular' => ['nullable', 'string', 'max:200'],
            'items.*.unique_id' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:40'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.remarks' => ['nullable', 'string', 'max:2000'],
            'items.*.attachment' => ['nullable', 'file', 'max:'.(int) ceil(config('ordo.upload.max_bytes') / 1024), 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx'],
        ], [
            'intent.required' => 'Kindly select an action (Save Draft or Submit for review).',
            'subject.required' => 'Kindly fill up the required subject field.',
            'purpose.required' => 'Kindly fill up the transmittal purpose.',
            'direction.required' => 'Kindly select the transmittal mode (Send or Receive).',
            'transmittal_date.required' => 'Kindly select the transmittal date.',
            'origin_office.required' => 'Kindly specify the office name.',
            'recipients.required_if' => 'Kindly specify at least one recipient before submitting for review.',
            'recipients.*.email.required' => 'Kindly fill up the required recipient email address.',
            'recipients.*.email.email' => 'Kindly provide a valid recipient email address.',
            'recipients.*.name.max' => 'Recipient name cannot exceed 120 characters.',
            'items.array' => 'Kindly fill up the item details properly.',
            'items.max' => 'The items list cannot exceed 50 items.',
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
            ->whereRaw('LENGTH(name) <= 60')
            ->where('name', 'not like', '%confidential%')
            ->where('name', 'not like', '%LOM%')
            ->orderBy('id')
            ->get(['id', 'name', 'organization'])
            ->unique(fn (TransmittalRecipient $recipient): string => mb_strtolower(trim($recipient->name)))
            ->values();

        $companies = Transmittal::query()->whereNotNull('related_company')->orderBy('id')->pluck('related_company')
            ->merge(TransmittalRecipient::query()->whereNotNull('organization')->orderBy('id')->pluck('organization'))
            ->map(fn ($company): string => trim((string) $company))
            ->filter(fn (string $company): bool => strlen($company) <= 60 && ! str_contains(strtolower($company), 'confidential') && ! str_contains(strtolower($company), 'address:'))
            ->unique(fn (string $company): string => mb_strtolower($company))
            ->values();

        return compact('employees', 'contacts', 'companies');
    }

    private function syncRecipients(Transmittal $transmittal, array $recipients): void
    {
        $transmittal->recipients()->delete();

        foreach ($recipients as $recipient) {
            $name = filled($recipient['name'] ?? null) ? trim((string) $recipient['name']) : null;
            $email = filled($recipient['email'] ?? null) ? trim((string) $recipient['email']) : null;
            $org = filled($recipient['organization'] ?? null) ? trim((string) $recipient['organization']) : null;

            if ($email !== null || $name !== null || $org !== null) {
                $transmittal->recipients()->create([
                    'name' => $name,
                    'email' => $email,
                    'organization' => $org,
                ]);
            }
        }
    }

    private function storeDocuments(Transmittal $transmittal, Request $request, ?User $user, array $validated): array
    {
        $storedDocuments = [];
        foreach ($request->file('documents', []) as $index => $file) {
            $storedDocuments[$index] = $this->storeDocument(
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

        return $storedDocuments;
    }

    private function syncItems(
        Transmittal $transmittal,
        Request $request,
        ?User $user,
        array $validated,
        array $uploadedDocuments = []
    ): void
    {
        $existingItems = $transmittal->items()->get()->keyBy('id');
        $retainedIds = [];

        foreach ($validated['items'] ?? [] as $position => $data) {
            $item = isset($data['id']) ? $existingItems->get($data['id']) : null;
            $documentId = $item?->document_id;

            if (filled($data['document_id'] ?? null)) {
                $linkedDocument = $transmittal->documents()->whereKey($data['document_id'])->first();
                abort_unless($linkedDocument, 404);
                $documentId = $linkedDocument->id;
            } elseif (! $documentId) {
                $documentIndex = isset($data['document_index']) && is_numeric($data['document_index'])
                    ? (int) $data['document_index']
                    : $position;
                $documentId = $uploadedDocuments[$documentIndex]->id ?? null;
            }

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
        if (! in_array($transmittal->status, ['draft', 'rejected', 'pending_approval'], true)) {
            return false;
        }

        $user = $request->user();

        if ($user) {
            return true;
        }

        $token = $request->session()->get("transmittal_draft_tokens.{$transmittal->id}");

        if (is_string($token)
            && is_string($transmittal->edit_token_hash)
            && hash_equals($transmittal->edit_token_hash, hash('sha256', $token))) {
            return true;
        }

        // Allow public editing for draft or rejected transmittals created in the system
        if (in_array($transmittal->status, ['draft', 'rejected'], true)) {
            return true;
        }

        return false;
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

        if ($transmittal->status === 'draft' || $transmittal->status === 'rejected') {
            return redirect()->route('transmittals.workspace', $transmittal);
        }

        $canViewPrivateFiles = $this->canViewPrivateFiles($request, $transmittal);
        $canViewAttachments = $this->canViewPrivateAttachments($request, $transmittal);
        $canManageLifecycle = $this->canManageLifecycle($request, $transmittal);

        // Recipient accounts may preview files addressed to them in the workspace,
        // but must not enter the internal transmittal detail page.
        if (! $canViewPrivateFiles && $canViewAttachments) {
            return redirect()->route('transmittals.workspace', $transmittal);
        }

        return view('transmittals.show', compact('transmittal', 'canEditDraft', 'canViewPrivateFiles', 'canViewAttachments', 'canManageLifecycle'));
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

        return redirect()->route('transmittals.index', ['filter' => 'archived'])
            ->with('status', 'Transmittal archived and recorded in its history.');
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

            if ($transmittal->recipients->isEmpty() && filled($transmittal->recipient_email)) {
                $transmittal->recipients()->create([
                    'name' => $transmittal->party_name ?: $transmittal->recipient_email,
                    'email' => $transmittal->recipient_email,
                    'organization' => $transmittal->office_name ?: $transmittal->related_company,
                ]);
                $transmittal->load('recipients');
            }

            foreach ($transmittal->recipients as $recipient) {
                $recipient->refresh();

                // Determine valid recipient email
                $targetEmail = trim((string) ($recipient->email ?: $transmittal->recipient_email));
                if ($targetEmail === '' || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                // If recipient model was missing email, populate it
                if (empty($recipient->email)) {
                    $recipient->forceFill(['email' => $targetEmail])->save();
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
                            'acknowledgment_expires_at' => $sentAt->copy()->addHours(
                                (int) config('ordo.brevo.acknowledgment_hours', 72)
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
            'channel_other' => ['nullable', 'string', 'max:160'],
            'proof_type' => ['required', Rule::in(['delivery_receipt', 'email_confirmation', 'courier_receipt', 'other'])],
            'proof_type_other' => ['nullable', 'string', 'max:160'],
            'sender_name' => ['required', 'string', 'max:160'],
            'sender_position' => ['nullable', 'string', 'max:160'],
            'receiver_name' => ['required', 'string', 'max:160'],
            'receiver_email' => ['nullable', 'email', 'max:254'],
            'acknowledged_by' => ['nullable', 'required_with:acknowledged_at', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:200'],
            'device' => ['nullable', 'string', 'max:300'],
            'device_category' => ['required', Rule::in(['desktop', 'laptop', 'mobile', 'tablet', 'other'])],
            'device_category_other' => ['nullable', 'string', 'max:160'],
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
                        'proof_type_other' => $validated['proof_type_other'] ?? null,
                        'channel_other' => $validated['channel_other'] ?? null,
                        'sender_name' => $validated['sender_name'],
                        'sender_position' => $validated['sender_position'] ?? null,
                        'sender_location' => $validated['location'] ?? null,
                        'location' => $validated['location'] ?? null,
                        'location_source' => filled($validated['location'] ?? null) ? 'user_entered' : null,
                        'device_category' => $validated['device_category'] ?? null,
                        'device_category_other' => $validated['device_category_other'] ?? null,
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
            'channel_other' => ['nullable', 'string', 'max:160'],
            'proof_type' => ['required', Rule::in(['delivery_receipt', 'email_confirmation', 'courier_receipt', 'other'])],
            'proof_type_other' => ['nullable', 'string', 'max:160'],
            'sender_name' => ['required', 'string', 'max:160'],
            'sender_position' => ['nullable', 'string', 'max:160'],
            'receiver_name' => ['required', 'string', 'max:160'],
            'receiver_email' => ['nullable', 'email', 'max:254'],
            'acknowledged_by' => ['nullable', 'required_with:acknowledged_at', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:200'],
            'device' => ['nullable', 'string', 'max:300'],
            'device_category' => ['required', Rule::in(['desktop', 'laptop', 'mobile', 'tablet', 'other'])],
            'device_category_other' => ['nullable', 'string', 'max:160'],
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
        abort_unless($this->canViewPrivateAttachments($request, $transmittal), 404);
        $record = $transmittal->documents()->findOrFail($document);

        return $this->streamDocument($transmittal, $record, $request->boolean('download'));
    }

    public function downloadForRecipient(string $token, TransmittalDocument $document): StreamedResponse
    {
        $recipient = $this->recipientForToken($token);
        abort_unless($recipient->transmittal_id === $document->transmittal_id, 404);

        return $this->streamDocument($document->transmittal, $document);
    }

    public function pdf(Request $request, Transmittal $transmittal): Response
    {
        $canAccess = $this->canViewPrivateFiles($request, $transmittal)
            || ($request->user() && $transmittal->exists)
            || $this->canManageLifecycle($request, $transmittal);

        abort_unless($canAccess, 404);
        $transmittal->load(['preparer', 'approver', 'rejector', 'recipients', 'documents', 'items.document', 'proofs.recipient', 'receipts', 'histories']);

        $pdf = Pdf::loadView('transmittals.pdf', compact('transmittal'))
            ->setPaper('a4');

        return $this->markPrivateFileResponse($pdf->download($transmittal->tracking_number.'.pdf'));
    }

    public function receiptPdf(Request $request, Transmittal $transmittal, ?int $receipt = null): Response
    {
        if ($receipt === null) {
            $transmittal->load(['receipt']);

            if (! $transmittal->receipt) {
                abort(404, 'Receipt not found.');
            }

            return $this->renderReceiptPdf($transmittal, $transmittal->receipt);
        }

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

    private function streamDocument(Transmittal $transmittal, TransmittalDocument $record, bool $asDownload = false): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($record->storage_path), 404);
        $this->verifyDocumentIntegrity($transmittal, $record);

        $response = Storage::disk('local')->response(
            $record->storage_path,
            $record->original_name,
            [
                'Content-Type' => $record->mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $asDownload ? 'attachment' : 'inline',
        );

        return $response;
    }

    private function renderReceiptPdf(Transmittal $transmittal, TransmittalReceipt $receipt): Response
    {
        $transmittal->loadMissing(['items', 'documents', 'attachments', 'recipients', 'receipts']);
        $receipt->loadMissing('recipient');

        $receiptDate = $receipt->created_at
            ? $receipt->created_at->format('Y-m-d')
            : now()->format('Y-m-d');

        $receivedAt = $receipt->received_at
            ? $receipt->received_at->format('Y-m-d H:i:s')
            : ($transmittal->received_at ? $transmittal->received_at->format('Y-m-d H:i:s') : 'N/A');

        $preparedAt = $transmittal->prepared_at
            ? $transmittal->prepared_at->format('Y-m-d H:i:s')
            : ($transmittal->created_at ? $transmittal->created_at->format('Y-m-d H:i:s') : 'N/A');

        $approvedAt = $transmittal->approved_at
            ? $transmittal->approved_at->format('Y-m-d H:i:s')
            : 'N/A';

        $deliveryType = 'N/A';
        if (($transmittal->delivery_type ?? '') === 'By Person' || ($transmittal->delivery_type ?? '') === 'by_person') {
            $deliveryType = $transmittal->by_person_who
                ? 'By Person - ' . $transmittal->by_person_who
                : 'By Person';
        } elseif (($transmittal->delivery_type ?? '') === 'Registered Mail' || ($transmittal->delivery_type ?? '') === 'registered_mail') {
            $deliveryType = $transmittal->registered_mail_provider
                ? 'Registered Mail - ' . $transmittal->registered_mail_provider
                : 'Registered Mail';
        } elseif (($transmittal->delivery_type ?? '') === 'Electronic' || ($transmittal->delivery_type ?? '') === 'electronic') {
            $deliveryType = 'Electronic' . ($transmittal->electronic_method ? ' - ' . $transmittal->electronic_method : '');
        }

        $actions = collect([
            $transmittal->action_delivery ? 'Delivery' : null,
            $transmittal->action_pick_up ? 'Pick Up' : null,
            $transmittal->action_drop_off ? 'Drop Off' : null,
            $transmittal->action_email ? 'Email' : null,
        ])->filter()->implode(', ');

        if ($actions === '') {
            $actions = collect($transmittal->delivery_actions ?? [])
                ->map(fn ($action) => str($action)->replace('_', ' ')->title())
                ->implode(', ') ?: '—';
        }

        $fromValue = ($transmittal->direction ?? $transmittal->mode ?? 'send') === 'send'
            ? ($transmittal->origin_office ?: $transmittal->office_name ?: config('ordo.office_name'))
            : ($transmittal->party_name ?: $receipt->recipient?->name ?: 'N/A');

        $toValue = ($transmittal->direction ?? $transmittal->mode ?? 'send') === 'send'
            ? ($receipt->recipient?->name ?: $transmittal->party_name ?: $transmittal->recipients->first()?->name ?: 'N/A')
            : ($transmittal->origin_office ?: $transmittal->office_name ?: config('ordo.office_name'));

        $approvedByText = ($transmittal->approver?->name ?? $transmittal->approved_by_name ?? 'N/A')
            . ($transmittal->approved_position ? ' (' . $transmittal->approved_position . ')' : '');

        $corporateContext = $this->transmittalCorporateContext();

        $fileName = ($transmittal->tracking_number ?? ($transmittal->transmittal_no ?? 'TRN')) . '-PACKAGE-RECEIPT.pdf';

        $response = Pdf::loadView('transmittal.package-pdf', [
            'transmittal' => $transmittal,
            'receipt' => $receipt,
            'receiptDate' => $receiptDate,
            'receivedAt' => $receivedAt,
            'preparedAt' => $preparedAt,
            'approvedAt' => $approvedAt,
            'deliveryType' => $deliveryType,
            'actions' => $actions,
            'fromValue' => $fromValue,
            'toValue' => $toValue,
            'approvedByText' => $approvedByText,
            'corporateContext' => $corporateContext,
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true]);

        $basePdfContent = $response->output();

        // Check for attached PDF documents to append their full pages
        $pdfDocs = ($transmittal->documents ?? collect())->filter(function ($doc) {
            $ext = strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION));
            return ($ext === 'pdf' || $doc->mime_type === 'application/pdf')
                && Storage::disk('local')->exists($doc->storage_path);
        });

        // Also check transmittal items with public/local attachment paths
        $itemPdfPaths = ($transmittal->items ?? collect())->filter(function ($item) {
            if (empty($item->attachment_path)) {
                return false;
            }
            $ext = strtolower(pathinfo($item->attachment_path, PATHINFO_EXTENSION));
            return $ext === 'pdf' && (
                Storage::disk('public')->exists($item->attachment_path) ||
                Storage::disk('local')->exists($item->attachment_path)
            );
        })->map(function ($item) {
            return Storage::disk('public')->exists($item->attachment_path)
                ? Storage::disk('public')->path($item->attachment_path)
                : Storage::disk('local')->path($item->attachment_path);
        });

        if ($pdfDocs->isNotEmpty() || $itemPdfPaths->isNotEmpty()) {
            try {
                $fpdi = new \setasign\Fpdi\Fpdi();

                // 1. Import base package pages (Cover, Form, Receipt, Manifest)
                $tempBaseFile = tempnam(sys_get_temp_dir(), 'base_pkg_');
                file_put_contents($tempBaseFile, $basePdfContent);
                $basePageCount = $fpdi->setSourceFile($tempBaseFile);
                for ($pageNo = 1; $pageNo <= $basePageCount; $pageNo++) {
                    $templateId = $fpdi->importPage($pageNo);
                    $size = $fpdi->getTemplateSize($templateId);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($templateId);
                }
                @unlink($tempBaseFile);

                // 2. Append all attached PDF documents with clear "ATTACHMENT" header banner
                foreach ($pdfDocs as $pdfDoc) {
                    $docFullPath = Storage::disk('local')->path($pdfDoc->storage_path);
                    try {
                        $docPageCount = $fpdi->setSourceFile($docFullPath);
                        for ($pageNo = 1; $pageNo <= $docPageCount; $pageNo++) {
                            $templateId = $fpdi->importPage($pageNo);
                            $size = $fpdi->getTemplateSize($templateId);
                            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                            $fpdi->useTemplate($templateId);

                            // Add prominent ATTACHMENT top header banner
                            $fpdi->SetFont('Arial', 'B', 8);
                            $fpdi->SetTextColor(15, 23, 42);
                            $fpdi->SetFillColor(241, 245, 249);
                            $fpdi->SetDrawColor(203, 213, 225);
                            $fpdi->Rect(5, 4, $size['width'] - 10, 6, 'DF');
                            $fpdi->SetXY(8, 5.2);
                            $headerText = 'ATTACHMENT: ' . strtoupper($pdfDoc->original_name) . ($docPageCount > 1 ? " (Page {$pageNo} of {$docPageCount})" : '');
                            $fpdi->Cell($size['width'] - 16, 4, $headerText, 0, 0, 'L');
                        }
                    } catch (\Throwable $docErr) {
                        \Log::warning("Could not merge attached PDF document ID {$pdfDoc->id}: " . $docErr->getMessage());
                    }
                }

                // 3. Append all item attachment PDFs with clear "ATTACHMENT" header banner
                foreach ($itemPdfPaths as $itemPath) {
                    try {
                        $docPageCount = $fpdi->setSourceFile($itemPath);
                        $baseFileName = basename($itemPath);
                        for ($pageNo = 1; $pageNo <= $docPageCount; $pageNo++) {
                            $templateId = $fpdi->importPage($pageNo);
                            $size = $fpdi->getTemplateSize($templateId);
                            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                            $fpdi->useTemplate($templateId);

                            // Add prominent ATTACHMENT top header banner
                            $fpdi->SetFont('Arial', 'B', 8);
                            $fpdi->SetTextColor(15, 23, 42);
                            $fpdi->SetFillColor(241, 245, 249);
                            $fpdi->SetDrawColor(203, 213, 225);
                            $fpdi->Rect(5, 4, $size['width'] - 10, 6, 'DF');
                            $fpdi->SetXY(8, 5.2);
                            $headerText = 'ATTACHMENT: ' . strtoupper($baseFileName) . ($docPageCount > 1 ? " (Page {$pageNo} of {$docPageCount})" : '');
                            $fpdi->Cell($size['width'] - 16, 4, $headerText, 0, 0, 'L');
                        }
                    } catch (\Throwable $itemErr) {
                        \Log::warning("Could not merge item PDF attachment: " . $itemErr->getMessage());
                    }
                }

                $mergedPdf = $fpdi->Output('S');

                return $this->markPrivateFileResponse(
                    response($mergedPdf, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    ])
                );
            } catch (\Throwable $mergeErr) {
                \Log::error("FPDI PDF merge failed, falling back to base package: " . $mergeErr->getMessage());
            }
        }

        return $this->markPrivateFileResponse(
            response($basePdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ])
        );
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

    private function canViewPrivateAttachments(Request $request, Transmittal $transmittal): bool
    {
        return ($request->user()?->can('viewAttachments', $transmittal) ?? false)
            || $this->canEditDraft($request, $transmittal);
    }

    private function canManageLifecycle(Request $request, Transmittal $transmittal): bool
    {
        $user = $request->user();

        if ($user?->canApproveTransmittals() || $transmittal->prepared_by === $user?->id) {
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

    public function createFromProject(Project $project): RedirectResponse
    {
        $project->loadMissing([
            'contact:id,first_name,last_name,email,company_name',
            'company:id,company_name',
        ]);

        return redirect()->route('transmittal.index', [
            'prefill' => json_encode($this->buildProjectPrefill($project, 'SOW')),
        ]);
    }

    public function createFromRegular(Project $regular): RedirectResponse
    {
        $regular->loadMissing([
            'contact:id,first_name,last_name,email,company_name',
            'company:id,company_name',
        ]);

        return redirect()->route('transmittal.index', [
            'prefill' => json_encode($this->buildProjectPrefill($regular, 'RSAT')),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $workflow = $request->get('workflow_status', 'uploaded');
        $perPage = (int) $request->get('per_page', 10);

        if ($perPage < 1) {
            $perPage = 10;
        }

        $workflowMap = [
            'uploaded' => 'Uploaded',
            'submitted' => 'Submitted',
            'accepted' => 'Accepted',
            'reverted' => 'Reverted',
            'archived' => 'Archived',
        ];

        $query = Transmittal::with(['items', 'receipt', 'attachments', 'recipients']);

        // `status` is the source of truth; `workflow_status` can be stale, so it is only a fallback when status is NULL.
        $statusMap = [
            'uploaded' => ['draft'],
            'submitted' => ['pending_approval'],
            'accepted' => ['approved', 'sent', 'acknowledged'],
            'reverted' => ['rejected'],
            'archived' => ['cancelled'],
        ];

        if (isset($statusMap[$workflow])) {
            $statuses = $statusMap[$workflow];
            $label = $workflowMap[$workflow];
            $query->where(function ($q) use ($statuses, $label) {
                $q->whereIn('status', $statuses)
                    ->orWhere(function ($sub) use ($label) {
                        $sub->whereNull('status')->where('workflow_status', $label);
                    });
            });
        }

        $searchTerm = $request->string('q')->trim()->toString() ?: $request->string('search')->trim()->toString();
        if ($searchTerm !== '') {
            $term = '%'.addcslashes($searchTerm, '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('transmittal_no', 'like', $term)
                    ->orWhere('tracking_number', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('party_name', 'like', $term)
                    ->orWhere('office_name', 'like', $term)
                    ->orWhere('external_reference', 'like', $term)
                    ->orWhere('origin_office', 'like', $term)
                    ->orWhere('related_company', 'like', $term)
                    ->orWhereHas('documents', fn ($documents) => $documents->where('original_name', 'like', $term))
                    ->orWhereHas('recipients', fn ($recipients) => $recipients
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('organization', 'like', $term));
            });
        }

        $paginator = $query->latest()->paginate($perPage);

        $rows = collect($paginator->items())
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'transmittal_no' => $item->transmittal_no ?? $item->tracking_number,
                    'date' => optional($item->transmittal_date)->format('Y-m-d'),
                    'mode' => strtoupper($item->direction ?? ($item->mode ?? 'SEND')),
                    'from_value' => $item->from_value,
                    'to_value' => $item->to_value,
                    'delivery_type' => $item->delivery_summary,
                    'actions' => $item->actions_summary,
                    'workflow_status' => match ($item->status) {
                        'draft' => 'Uploaded',
                        'pending_approval' => 'Submitted',
                        'approved', 'sent', 'acknowledged' => 'Accepted',
                        'rejected' => 'Reverted',
                        'cancelled' => 'Archived',
                        default => $item->workflow_status ?? 'Uploaded',
                    },
                    'approval_status' => match ($item->status) {
                        'approved', 'sent', 'acknowledged' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $item->approval_status ?? 'Pending',
                    },
                    'party_name' => $item->party_name,
                    'office_name' => $item->office_name,
                    'address' => $item->address,
                    'by_person_who' => $item->by_person_who,
                    'registered_mail_provider' => $item->registered_mail_provider,
                    'electronic_method' => $item->electronic_method,
                    'recipient_email' => $item->recipient_email,
                    'action_delivery' => $item->action_delivery,
                    'action_pick_up' => $item->action_pick_up,
                    'action_drop_off' => $item->action_drop_off,
                    'action_email' => $item->action_email,
                    'prepared_by_name' => $item->prepared_by_name,
                    'prepared_at' => optional($item->prepared_at)->format('Y-m-d H:i'),
                    'approved_by_name' => $item->approved_by_name,
                    'approved_position' => $item->approved_position,
                    'approved_at' => optional($item->approved_at)->format('Y-m-d H:i'),
                    'document_custodian' => $item->document_custodian,
                    'delivered_by' => $item->delivered_by,
                    'received_by' => $item->received_by,
                    'receiver_affiliation' => $item->receiver_affiliation,
                    'received_at' => optional($item->received_at)->format('Y-m-d\TH:i'),
                    'attachments_count' => $item->attachments ? $item->attachments->count() : 0,
                    'items' => $item->items ? $item->items->map(function ($row) {
                        return [
                            'no' => $row->item_no ?? $row->no,
                            'particular' => $row->particular,
                            'unique_id' => $row->unique_id,
                            'qty' => $row->qty ?? $row->quantity,
                            'description' => $row->description,
                            'remarks' => $row->remarks,
                            'attachment_path' => $row->attachment_path,
                            'attachment_url' => $row->attachment_path ? asset('storage/' . $row->attachment_path) : null,
                        ];
                    })->values() : [],
                    'can_submit' => in_array($item->workflow_status ?? $item->status, ['Uploaded', 'Reverted', 'draft', 'rejected'], true),
                    'preview_url' => route('transmittal.preview', $item->id),
                    'workspace_url' => route('transmittals.workspace', $item->id),
                    'receipt_id' => optional($item->receipt)->id,
                    'receipt_no' => optional($item->receipt)->receipt_no,
                    'receipt_url' => $item->receipt ? route('transmittal.receipts.show', $item->receipt->id) : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function submit($id): JsonResponse
    {
        $transmittal = Transmittal::findOrFail($id);

        if (! in_array($transmittal->workflow_status ?? $transmittal->status, ['Uploaded', 'Reverted', 'draft', 'rejected'], true)) {
            return response()->json(['message' => 'Only uploaded or reverted transmittals can be submitted.'], 422);
        }

        $transmittal->update([
            'workflow_status' => 'Submitted',
            'status' => 'pending_approval',
            'approval_status' => 'Pending',
            'submitted_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Transmittal submitted for approval successfully.']);
    }

    public function preview($id): View
    {
        $transmittal = Transmittal::with(['items', 'receipt', 'attachments'])->findOrFail($id);

        $transmittalPdfUrl = route('transmittal.preview.pdf', $transmittal->id);
        $receiptPdfUrl = $transmittal->receipt
            ? route('transmittal.receipt.pdf', $transmittal->id)
            : null;

        $corporateContext = $this->transmittalCorporateContext();

        return view('transmittal.preview', compact('transmittal', 'transmittalPdfUrl', 'receiptPdfUrl', 'corporateContext'));
    }

    public function previewPdf($id): Response
    {
        $transmittal = Transmittal::with(['items', 'receipt', 'attachments'])->findOrFail($id);

        $approvedByDisplay = $this->resolveApprovedByName($transmittal);
        $corporateContext = $this->transmittalCorporateContext();

        $pdf = Pdf::loadView('transmittal.preview-pdf', [
                'transmittal' => $transmittal,
                'approvedByDisplay' => $approvedByDisplay,
                'corporateContext' => $corporateContext,
            ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true]);

        return $pdf->stream('transmittal-' . ($transmittal->transmittal_no ?? $transmittal->tracking_number) . '.pdf');
    }

    public function sendEmail($id): RedirectResponse
    {
        $transmittal = Transmittal::with(['items', 'receipt', 'attachments'])->findOrFail($id);

        $recipient = trim((string) $transmittal->recipient_email);
        if ($recipient === '') {
            return back()->with('error', 'Recipient email is required before sending the transmittal.');
        }

        $approvedByDisplay = $this->resolveApprovedByName($transmittal);
        $corporateContext = $this->transmittalCorporateContext();

        $pdf = Pdf::loadView('transmittal.preview-pdf', [
                'transmittal' => $transmittal,
                'approvedByDisplay' => $approvedByDisplay,
                'corporateContext' => $corporateContext,
            ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true]);

        $pdfFileName = 'transmittal-' . Str::slug((string) ($transmittal->transmittal_no ?? $transmittal->tracking_number), '-') . '.pdf';
        $pdfOutput = $pdf->output();

        $fileAttachments = [];

        foreach ($transmittal->items as $item) {
            if (! empty($item->attachment_path) && Storage::disk('public')->exists($item->attachment_path)) {
                $fileAttachments[] = [
                    'path' => Storage::disk('public')->path($item->attachment_path),
                    'name' => basename($item->attachment_path),
                ];
            }
        }

        if ($transmittal->attachments) {
            foreach ($transmittal->attachments as $attachment) {
                if (! empty($attachment->file_path) && Storage::disk('public')->exists($attachment->file_path)) {
                    $fileAttachments[] = [
                        'path' => Storage::disk('public')->path($attachment->file_path),
                        'name' => $attachment->original_name ?: basename($attachment->file_path),
                    ];
                }
            }
        }

        $emailSubject = 'Transmittal Form ' . ($transmittal->transmittal_no ?? $transmittal->tracking_number);
        $htmlContent = view('emails.transmittal-sent', [
            'transmittal' => $transmittal,
            'corporateContext' => $corporateContext,
        ])->render();

        $brevoApiKey = config('ordo.brevo.api_key');
        $brevoSender = config('ordo.brevo.sender_email');

        if (filled($brevoApiKey) && filled($brevoSender)) {
            try {
                app(BrevoTransmittalService::class)->sendTransmittalDocument(
                    $recipient,
                    $emailSubject,
                    $htmlContent,
                    $pdfOutput,
                    $pdfFileName,
                    $fileAttachments
                );

                return back()->with('success', 'Transmittal email sent successfully to ' . $recipient . ' via verified delivery.');
            } catch (\Throwable $e) {
                \Log::warning('Brevo direct transmission failed, falling back to mailer: ' . $e->getMessage());
            }
        }

        Mail::send([], [], function ($message) use ($recipient, $transmittal, $emailSubject, $htmlContent, $pdfOutput, $pdfFileName, $fileAttachments) {
            $senderEmail = config('ordo.brevo.sender_email') ?: config('mail.from.address');
            $senderName = config('ordo.brevo.sender_name') ?: config('mail.from.name');

            $message->to($recipient)
                ->from($senderEmail, $senderName)
                ->replyTo($senderEmail, $senderName)
                ->subject($emailSubject)
                ->html($htmlContent)
                ->attachData($pdfOutput, $pdfFileName, ['mime' => 'application/pdf']);

            foreach ($fileAttachments as $attachment) {
                $message->attach($attachment['path'], ['as' => $attachment['name']]);
            }
        });

        return back()->with('success', 'Transmittal email sent successfully to ' . $recipient . '.');
    }

    private function buildProjectPrefill(Project $project, string $sourceLabel): array
    {
        $formCode = $sourceLabel === 'RSAT' ? 'REG-F-004' : 'PROJ-F-006';
        $contactName = trim(collect([
            $project->contact?->first_name,
            $project->contact?->last_name,
        ])->filter()->implode(' '));

        $externalParty = $contactName
            ?: ($project->client_name ?: ($project->company?->company_name ?: ''));

        $officeName = $project->business_name
            ?: ($project->company?->company_name ?: 'John Kelly & Company');

        $projectCode = $project->project_code ?: ('PROJECT-' . $project->id);
        $clientEmail = $project->contact?->email;
        $services = trim((string) $project->services);
        $products = trim((string) $project->products);
        $scopeSummary = trim((string) $project->scope_summary);

        $descriptionParts = array_values(array_filter([
            $project->name ? "Engagement: {$project->name}" : null,
            $services !== '' ? "Services: {$services}" : null,
            $products !== '' ? "Products: {$products}" : null,
            $scopeSummary !== '' ? "Scope: {$scopeSummary}" : null,
        ]));

        return [
            'source_type' => strtolower($sourceLabel),
            'source_id' => $project->id,
            'source_label' => $sourceLabel,
            'form_code' => $formCode,
            'transmittal_date' => now()->toDateString(),
            'mode' => 'SEND',
            'party_name' => $externalParty,
            'office_name' => $officeName,
            'address' => '',
            'delivery_type' => $clientEmail ? 'Electronic' : '',
            'electronic_method' => $clientEmail ? 'Email' : '',
            'recipient_email' => $clientEmail,
            'action_delivery' => false,
            'action_pick_up' => false,
            'action_drop_off' => false,
            'action_email' => (bool) $clientEmail,
            'prepared_by_name' => (string) ($project->assigned_associate ?: ''),
            'approved_by_name' => (string) ($project->assigned_consultant ?: ''),
            'approved_position' => 'Operations Manager',
            'document_custodian' => '',
            'delivered_by' => '',
            'received_by' => $externalParty,
            'received_at' => '',
            'items' => [[
                'no' => 1,
                'particular' => "{$sourceLabel} Documents",
                'unique_id' => $projectCode,
                'qty' => 1,
                'description' => implode(' | ', $descriptionParts),
                'remarks' => "{$sourceLabel} generated from {$projectCode}",
            ]],
        ];
    }

    private function transmittalContactOptions(): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $columns = Schema::getColumnListing('contacts');
        $select = array_values(array_intersect([
            'id', 'first_name', 'middle_name', 'middle_initial', 'last_name', 'name_extension',
            'company_name', 'email', 'phone'
        ], $columns));

        if (! in_array('id', $select, true)) {
            $select[] = 'id';
        }

        return DB::table('contacts')
            ->select($select)
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(function ($contact) {
                $fullName = trim(collect([
                    $contact->first_name ?? null,
                    $contact->middle_initial ?? null,
                    $contact->middle_name ?? null,
                    $contact->last_name ?? null,
                    $contact->name_extension ?? null,
                ])->filter()->implode(' '));

                $company = trim((string) ($contact->company_name ?? ''));
                $label = $fullName !== '' ? $fullName : ($company !== '' ? $company : 'Contact #' . $contact->id);

                return [
                    'id' => $contact->id,
                    'type' => 'contact',
                    'label' => $label,
                    'affiliation' => $company,
                    'email' => $contact->email ?? '',
                    'phone' => $contact->phone ?? '',
                ];
            })
            ->values()
            ->all();
    }

    private function transmittalEmployeeOptions(): array
    {
        if (Schema::hasTable('employees')) {
            $columns = Schema::getColumnListing('employees');
            $select = array_values(array_intersect([
                'id', 'employee_id', 'full_name', 'first_name', 'middle_name', 'last_name',
                'work_email', 'company_email', 'personal_email', 'email', 'department', 'position'
            ], $columns));

            if (! in_array('id', $select, true)) {
                $select[] = 'id';
            }

            return DB::table('employees')
                ->select($select)
                ->orderByDesc('id')
                ->limit(500)
                ->get()
                ->map(function ($employee) {
                    $fullName = trim((string) ($employee->full_name ?? ''));

                    if ($fullName === '') {
                        $fullName = trim(collect([
                            $employee->first_name ?? null,
                            $employee->middle_name ?? null,
                            $employee->last_name ?? null,
                        ])->filter()->implode(' '));
                    }

                    return [
                        'id' => $employee->id,
                        'type' => 'employee',
                        'label' => $fullName !== '' ? $fullName : 'Employee #' . $employee->id,
                        'affiliation' => trim((string) ($employee->department ?? '')),
                        'email' => $employee->work_email ?? ($employee->company_email ?? ($employee->email ?? ($employee->personal_email ?? ''))),
                        'position' => $employee->position ?? '',
                    ];
                })
                ->values()
                ->all();
        }

        if (! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'type' => 'employee',
                'label' => $user->name ?: $user->email,
                'affiliation' => 'Employee',
                'email' => $user->email ?? '',
                'position' => '',
            ])
            ->values()
            ->all();
    }

    private function transmittalCompanyOptions(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        $columns = Schema::getColumnListing('companies');
        $select = array_values(array_intersect(['id', 'company_name', 'name', 'email', 'address'], $columns));

        if (! in_array('id', $select, true)) {
            $select[] = 'id';
        }

        return DB::table('companies')
            ->select($select)
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(function ($company) {
                $label = trim((string) ($company->company_name ?? ($company->name ?? '')));

                return [
                    'id' => $company->id,
                    'label' => $label !== '' ? $label : 'Company #' . $company->id,
                    'email' => $company->email ?? '',
                    'address' => $company->address ?? '',
                ];
            })
            ->values()
            ->all();
    }

    private function resolveApprovedByName(Transmittal $transmittal): string
    {
        if (! empty($transmittal->approved_by) && Schema::hasTable('users')) {
            $userColumns = Schema::getColumnListing('users');
            $nameColumn = in_array('name', $userColumns, true) ? 'name' : (in_array('email', $userColumns, true) ? 'email' : null);

            if ($nameColumn) {
                $name = DB::table('users')
                    ->where('id', $transmittal->approved_by)
                    ->value($nameColumn);

                if (trim((string) $name) !== '') {
                    return trim((string) $name);
                }
            }
        }

        return trim((string) ($transmittal->approved_by_name ?? ''));
    }

    private function transmittalCorporateContext(): array
    {
        $fallback = [
            'companyName' => 'John Kelly & Company',
            'secRegNo' => '',
            'principalAddress' => '',
            'logoUrl' => asset('images/FINAL_LOGO.jpg'),
            'logoBase64' => null,
        ];

        if (! Schema::hasTable('gis_records')) {
            return $fallback;
        }

        $buildCorporateGisQuery = function () {
            $query = DB::table('gis_records');

            if (Schema::hasColumn('gis_records', 'company_id')) {
                $query->whereNull('company_id');
            }

            return $query;
        };

        $query = $buildCorporateGisQuery();

        if (Schema::hasColumn('gis_records', 'workflow_status')) {
            $query->where(function ($q) {
                $q->where('workflow_status', 'Accepted');

                if (Schema::hasColumn('gis_records', 'approval_status')) {
                    $q->orWhere('approval_status', 'Approved')
                        ->orWhere('approval_status', 'Accepted');
                }

                if (Schema::hasColumn('gis_records', 'submission_status')) {
                    $q->orWhere('submission_status', 'Accepted')
                        ->orWhere('submission_status', 'Approved');
                }
            });
        } elseif (Schema::hasColumn('gis_records', 'approval_status')) {
            $query->where(function ($q) {
                $q->where('approval_status', 'Approved')
                    ->orWhere('approval_status', 'Accepted');
            });
        }

        $gis = $query
            ->latest('updated_at')
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $gis) {
            $gis = $buildCorporateGisQuery()
                ->latest('updated_at')
                ->latest('created_at')
                ->latest('id')
                ->first();
        }

        if (! $gis) {
            return $fallback;
        }

        $companyName = trim((string) ($gis->corporation_name ?? '')) ?: $fallback['companyName'];
        $secRegNo = trim((string) ($gis->company_reg_no ?? ''));
        $principalAddress = trim((string) ($gis->principal_address ?? ($gis->business_address ?? '')));
        $logoPath = trim((string) ($gis->logo_path ?? ''));

        $logoUrl = $fallback['logoUrl'];
        $logoBase64 = null;

        if ($logoPath !== '') {
            $normalizedLogoPath = preg_replace('#^/?storage/#', '', $logoPath);

            if (Storage::disk('public')->exists($normalizedLogoPath)) {
                $absoluteLogoPath = Storage::disk('public')->path($normalizedLogoPath);
                $mime = function_exists('mime_content_type')
                    ? (mime_content_type($absoluteLogoPath) ?: 'image/png')
                    : 'image/png';

                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absoluteLogoPath));
                $logoUrl = asset('storage/' . $normalizedLogoPath);
            } elseif (file_exists(public_path($normalizedLogoPath))) {
                $absoluteLogoPath = public_path($normalizedLogoPath);
                $mime = function_exists('mime_content_type')
                    ? (mime_content_type($absoluteLogoPath) ?: 'image/png')
                    : 'image/png';

                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absoluteLogoPath));
                $logoUrl = asset($normalizedLogoPath);
            }
        }

        return [
            'companyName' => $companyName,
            'secRegNo' => $secRegNo,
            'principalAddress' => $principalAddress,
            'logoUrl' => $logoUrl,
            'logoBase64' => $logoBase64,
        ];
    }
}

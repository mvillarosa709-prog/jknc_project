<?php

namespace App\Jobs;

use App\Enums\OcrStatus;
use App\Models\RecordFile;
use App\Services\Records\DocumentTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessIntakeOcr implements ShouldQueue
{
    use Queueable;

    /**
     * Attempts before the job is treated as having failed outright.
     *
     * Reading is best-effort by design: AGENTS.md requires that a failed read
     * never costs us the original file, so retrying is about settling the file
     * into a correct terminal state rather than about rescuing the work.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry, giving a transient database or storage
     * outage room to clear before the next attempt.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Seconds a single attempt may run before the worker kills it.
     */
    public int $timeout = 900;

    /**
     * The tenant and company travel with the job because it runs on a queue
     * worker, where there is no session to resolve a company context from. Every
     * query the job makes is scoped by them, the same as any request-side query.
     *
     * recordFileId narrows the job to one file and exists because a file that was
     * added after intake — a supporting file, or a new version — carries no
     * intake item at all. Resolving files by intake item alone would return an
     * empty set for those and the read would quietly do nothing. It is also what
     * makes "re-read this document" in the preview mean that one file rather than
     * every file the original upload happened to carry.
     *
     * Both are nullable, but not interchangeably: a job that names neither has
     * nothing to read, so it settles as a no-op rather than reaching for a file
     * it was never given.
     */
    public function __construct(
        public readonly ?string $recordIntakeItemId,
        public readonly string $tenantId,
        public readonly string $companyId,
        public readonly ?string $recordFileId = null,
    ) {}

    /**
     * Document reading is queued asynchronously and must never block the original file
     * from being preserved or filed manually.
     *
     * Extraction lives in DocumentTextExtractor so this job only decides what a read
     * means for a file's state. A file that yields text becomes Ready with the text
     * stored; a file that yields none becomes Needs Review carrying the reason.
     *
     * Nothing here throws for an unreadable document: the stored bytes stay put
     * either way, and an exception would only cost us the reason the file could not
     * be read, which is the one thing the drawer needs to show.
     */
    public function handle(): void
    {
        $extractor = $this->extractor();

        foreach ($this->files() as $file) {
            try {
                $file->forceFill(['ocr_status' => OcrStatus::Reading])->save();

                $result = $extractor->extract(
                    $this->absolutePath($file),
                    (string) $file->mime_type,
                    (string) $file->extension,
                );

                if ($result['reason'] !== null) {
                    // No text is not a success. Settling these to Ready — which is
                    // what the earlier stub did — made an unreadable document look
                    // like it had been read.
                    $this->settleForReview($file, $result['reason']);

                    continue;
                }

                $file->forceFill([
                    'ocr_text' => $result['text'],
                    'ocr_status' => OcrStatus::Ready,
                    'ocr_error' => null,
                    'ocr_completed_at' => now(),
                ])->save();
            } catch (Throwable $e) {
                $this->settleForReview($file, $e->getMessage());
            }
        }

        try {
            $item = RecordIntakeItem::query()
                ->where('tenant_id', $this->tenantId)
                ->where('company_id', $this->companyId)
                ->find($this->recordIntakeItemId);

            if ($item) {
                $detected = app(\App\Services\Records\DocumentIntelligenceService::class)->detect($item);
                $updates = [];
                if (blank($item->suggested_classification_id) && ! empty($detected['classification_id'])) {
                    $updates['suggested_classification_id'] = $detected['classification_id'];
                    $updates['suggested_index_id'] = $detected['index_id'];
                }
                if (blank($item->source) && ! empty($detected['source'])) {
                    $updates['source'] = $detected['source'];
                }
                if (! empty($updates)) {
                    $item->forceFill($updates)->save();
                }
            }
        } catch (Throwable) {
            // Intelligent suggestion is best-effort and must never block filing
        }
    }

    /**
     * Settle the item's files once every attempt has been used.
     *
     * Without this a file interrupted mid-read would sit on Reading forever with
     * no indication of what became of it. Files are moved to Needs Review, which
     * AGENTS.md defines as reviewable by hand, and the original file on disk and
     * in the database is left exactly as it was.
     */
    public function failed(?Throwable $e): void
    {
        $reason = $e?->getMessage() ?? 'The document reading job did not complete.';

        Log::error('Intake document reading job failed.', [
            'record_intake_item_id' => $this->recordIntakeItemId,
            'tenant_id' => $this->tenantId,
            'company_id' => $this->companyId,
            'error' => $reason,
        ]);

        foreach ($this->files() as $file) {
            $this->settleForReview($file, $reason);
        }
    }

    /**
     * Resolved inside the job rather than injected.
     *
     * A job is rebuilt from its serialised payload on the worker, so a constructor
     * dependency would have to be serialisable. Resolving here keeps the payload to
     * the three identifiers it genuinely needs, which is also all a worker can
     * safely be handed.
     */
    private function extractor(): DocumentTextExtractor
    {
        return app(DocumentTextExtractor::class);
    }

    /**
     * Where a file's bytes actually are on disk.
     *
     * Read through the file's own disk rather than assumed to be the default,
     * because a supporting file or a new version can be stored on another one. A
     * disk that cannot be resolved yields an empty path, which the extractor
     * reports as Needs Review rather than as a job failure.
     */
    private function absolutePath(RecordFile $file): string
    {
        try {
            return Storage::disk((string) $file->disk)->path((string) $file->path);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * The files this job is responsible for, inside its tenant and company.
     *
     * A single file when one was named, otherwise every file the intake item
     * captured. Both paths stay scoped by tenant and company: a worker has no
     * session, so this is the only thing standing between a queued job and
     * another company's bytes.
     *
     * @return Collection<int, RecordFile>
     */
    private function files(): Collection
    {
        return RecordFile::query()
            ->where('tenant_id', $this->tenantId)
            ->where('company_id', $this->companyId)
            ->when(
                $this->recordFileId !== null,
                fn ($query) => $query->where('id', $this->recordFileId),
                fn ($query) => $query->where('record_intake_item_id', $this->recordIntakeItemId),
            )
            ->get();
    }

    /**
     * Move one file to Needs Review, recording why, without touching the stored
     * bytes so the document can still be filed manually.
     */
    private function settleForReview(RecordFile $file, string $reason): void
    {
        Log::warning('Intake document reading failed.', [
            'record_file_id' => $file->id,
            'record_intake_item_id' => $this->recordIntakeItemId,
            'error' => $reason,
        ]);

        $file->forceFill([
            'ocr_status' => OcrStatus::NeedsReview,
            'ocr_error' => $reason,
            'ocr_completed_at' => now(),
        ])->save();
    }
}
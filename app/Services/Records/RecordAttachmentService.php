<?php

namespace App\Services\Records;

use App\Enums\FileRole;
use App\Enums\OcrStatus;
use App\Enums\RecordStatus;
use App\Jobs\ProcessIntakeOcr;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Files added to a Record that already exists.
 *
 * Three actions, and the difference between them is what they cost. A supporting
 * file and a new version both attach bytes to a Record that is already on the
 * books: AGENTS.md is explicit that they add storage and no further Record Unit,
 * so they are guarded on the storage ceiling and never on the unit allocation.
 * Uploading as a Record does file something new, so it goes through intake and
 * filing and consumes exactly one Record Unit.
 *
 * Every file written here carries a null record_intake_item_id, because none of
 * them arrived through intake. That is the case the reading job's per-file scope
 * exists for: resolved by intake item alone, these files return an empty set and
 * are silently never read.
 */
class RecordAttachmentService
{
    public function __construct(
        private readonly RecordCapacityService $capacity,
        private readonly RecordIntakeService $intake,
        private readonly RecordFilingService $filing,
        private readonly FileMetadata $metadata,
    ) {}

    /**
     * Attach a supporting file. Storage only; no Record Unit.
     */
    public function addSupportingFile(Record $record, UploadedFile $upload, mixed $user = null): RecordFile
    {
        return $this->attach($record, $upload, FileRole::Supporting, $this->nextVersion($record) + 1, $user);
    }

    /**
     * Attach a new version of the master document. Storage only; no Record Unit.
     *
     * The prior master is left in place rather than removed: a version history that
     * discards what it superseded is not a history, and AGENTS.md treats a stored
     * document as something that must survive.
     */
    public function addVersion(Record $record, UploadedFile $upload, mixed $user = null): RecordFile
    {
        return $this->attach($record, $upload, FileRole::Master, $this->nextVersion($record) + 1, $user);
    }

    /**
     * Upload a file and file it as a Record in its own right.
     *
     * Goes through intake and filing rather than writing a Record directly, so it
     * gets the same atomic Document Reference, the same unit accounting and the
     * same audit entry as any other filing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function uploadAsRecord(UploadedFile $upload, array $attributes, mixed $user = null): Record
    {
        // Refused here as well as inside the filing service so the rejection
        // names this upload rather than arriving from three calls deeper.
        $this->capacity->verifyFilingCapacity(1);

        $item = $this->intake->createIntakeItem([$upload], [
            'title' => $attributes['title'] ?? null,
            'source' => $attributes['source'] ?? null,
            'document_date' => $attributes['document_date'] ?? null,
            'external_reference_number' => $attributes['external_reference_number'] ?? null,
            'uploaded_by' => $user,
        ]);

        return $this->filing->file($item, [
            'record_classification_id' => $attributes['record_classification_id'],
            'record_index_id' => $attributes['record_index_id'],
            'is_confidential' => (bool) ($attributes['is_confidential'] ?? false),
        ], $user);
    }

    /**
     * The shared write path behind a supporting file and a new version.
     *
     * The bytes land before the row, so a failure after the row is written would
     * leave a file record pointing at nothing. The stored path is tracked and
     * removed if anything throws, which is the same bargain RecordIntakeService
     * makes.
     */
    private function attach(
        Record $record,
        UploadedFile $upload,
        FileRole $role,
        int $version,
        mixed $user,
    ): RecordFile {
        $this->guardArchived($record);

        $disk = (string) config('filesystems.default');

        return DB::transaction(function () use ($record, $upload, $role, $version, $user, $disk): RecordFile {
            $sizeBytes = (int) $upload->getSize();

            $this->guardStorage($sizeBytes);

            $path = $this->storeFile($upload, $record, $disk);

            try {
                $details = $this->metadata->extract($upload, $disk, $path);

                $file = RecordFile::query()->create([
                    'tenant_id' => $record->tenant_id,
                    'company_id' => $record->company_id,
                    'record_id' => $record->id,
                    // Null: this file did not arrive through intake.
                    'record_intake_item_id' => null,
                    'file_role' => $role,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime_type' => $details['mime_type'],
                    'extension' => $details['extension'],
                    'size_bytes' => $details['size_bytes'],
                    'page_count' => $details['page_count'],
                    'checksum_sha256' => $details['sha256'],
                    'ocr_status' => OcrStatus::Queued,
                    'uploaded_by' => $user,
                    'version' => $version,
                ]);
            } catch (Throwable $e) {
                Storage::disk($disk)->delete($path);

                throw $e;
            }

            $this->rescopeStorage($record);

            $this->log($record, $file, $role, $user);

            DB::afterCommit(function () use ($file, $record): void {
                // Scoped to this one file, and with no intake item — which is
                // exactly the shape the job's recordFileId argument was added for.
                ProcessIntakeOcr::dispatch(
                    null,
                    (string) $record->tenant_id,
                    (string) $record->company_id,
                    (string) $file->id,
                );
            });

            return $file;
        });
    }

    /**
     * Refuse when the company's storage ceiling cannot absorb these bytes.
     *
     * Separate from the Record Unit check on purpose: supporting files and
     * versions cost bytes only, so charging them against the unit allocation
     * would refuse uploads the plan explicitly allows.
     */
    private function guardStorage(int $incomingBytes): void
    {
        $limit = $this->capacity->storageLimit();

        if ($limit === null) {
            return;
        }

        $used = (int) $this->capacity->storage()['total'];

        if ($used + $incomingBytes > $limit) {
            throw new LogicException(sprintf(
                'This upload needs %s but only %s of storage remains.',
                number_format($incomingBytes).' bytes',
                number_format(max(0, $limit - $used)).' bytes',
            ));
        }
    }

    /**
     * An archived Record is read-only.
     *
     * Archiving keeps the record in the register with its Document Reference and
     * Record Unit intact, so accepting new files onto it would change a record the
     * reader has been told is closed.
     *
     * @param  Record|null  $record  null while a new record is still being uploaded
     */
    private function guardArchived(?Record $record): void
    {
        if ($record?->status === RecordStatus::Archived) {
            throw new LogicException('This record is archived and cannot take new files.');
        }
    }

    /**
     * Keep the record's cached storage total in step with its files.
     *
     * Denormalised onto the record so the register can sort and filter without a
     * join; it is only truthful if something recomputes it on every write.
     */
    private function rescopeStorage(Record $record): void
    {
        $record->forceFill([
            'storage_bytes' => (int) $record->files()->sum('size_bytes'),
        ])->save();
    }

    /**
     * The next version number for this record's master document.
     *
     * Derived from the maximum rather than tracked on the record, so the value
     * cannot drift out of step with the files themselves.
     */
    private function nextVersion(Record $record): int
    {
        return (int) ($record->files()->max('version') ?? 0);
    }

    private function storeFile(UploadedFile $upload, Record $record, string $disk): string
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        $name = Str::ulid()->toBase32().($extension !== '' ? '.'.$extension : '');

        // records/ rather than intake/: these bytes belong to a filed Record and
        // would otherwise sit in the unclassified tree forever.
        $path = sprintf('records/%s/%s/%s/%s', $record->tenant_id, $record->company_id, $record->id, $name);

        Storage::disk($disk)->putFileAs(dirname($path), $upload, basename($path));

        return $path;
    }

    private function log(Record $record, RecordFile $file, FileRole $role, mixed $user): void
    {
        $versioned = $role === FileRole::Master && (int) $file->version > 1;

        RecordActivityLog::query()->create([
            'tenant_id' => $record->tenant_id,
            'company_id' => $record->company_id,
            'record_id' => $record->id,
            'record_intake_item_id' => null,
            'record_file_id' => $file->id,
            'user_id' => $user?->id,
            'action' => $versioned ? 'record.version_created' : 'record.file_added',
            'description' => $versioned
                ? sprintf('Version %d stored as %s.', $file->version, $file->original_name)
                : sprintf('Supporting file %s stored.', $file->original_name),
            'properties' => [
                'file_role' => $role->value,
                'version' => (int) $file->version,
                'size_bytes' => (int) $file->size_bytes,
                'record_units' => 0,
            ],
            'ip_address' => request()?->ip(),
        ]);
    }
}

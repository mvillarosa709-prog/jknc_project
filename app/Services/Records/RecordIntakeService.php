<?php

namespace App\Services\Records;

use App\Enums\CaptureSource;
use App\Enums\FileRole;
use App\Enums\IntakeStatus;
use App\Enums\OcrStatus;
use App\Jobs\ProcessIntakeOcr;
use App\Models\RecordActivityLog;
use App\Models\RecordFile;
use App\Models\RecordIntakeItem;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class RecordIntakeService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly FileMetadata $metadata,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $attributes
     */
    public function createIntakeItem(array $files, array $attributes = [], ?CaptureSource $source = null): RecordIntakeItem
    {
        $this->guardAgainstSuppliedScope($attributes);

        if ($files === []) {
            throw new LogicException('At least one file is required to create an intake item.');
        }

        $company = $this->context->current();
        $tenantId = (string) $company->tenant_id;
        $companyId = (string) $company->id;
        $source ??= CaptureSource::forCount(count($files));

        $storedPaths = [];

        try {
            $item = DB::transaction(function () use ($files, $attributes, $source, $tenantId, $companyId, &$storedPaths): RecordIntakeItem {
                $item = RecordIntakeItem::query()->create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'title' => $attributes['title'] ?? $this->fallbackTitle($files),
                    'source' => $attributes['source'] ?? null,
                    'document_date' => $attributes['document_date'] ?? null,
                    'external_reference_number' => $attributes['external_reference_number'] ?? null,
                    'status' => IntakeStatus::Pending,
                    'capture_source' => $source,
                    'storage_bytes' => 0,
                    'uploaded_by' => $attributes['uploaded_by'] ?? null,
                ]);

                $storageBytes = 0;

                foreach ($files as $file) {
                    $path = $this->storeFile($file, $tenantId, $companyId, (string) $item->id);
                    $storedPaths[] = $path;

                    $details = $this->metadata->extract($file, (string) config('filesystems.default'), $path);
                    $storageBytes += $details['size_bytes'];

                    $this->createFile($item, $file, $path, $details, $attributes['uploaded_by'] ?? null);
                }

                $item->forceFill(['storage_bytes' => $storageBytes])->save();

                $this->log($item, 'intake.created', sprintf(
                    '%d file(s) received via %s.',
                    count($files),
                    $source->label(),
                ), ['files' => count($files), 'storage_bytes' => $storageBytes, 'capture_source' => $source->value]);

                return $item;
            });
        } catch (Throwable $e) {
            $this->discardStoredFiles($storedPaths);

            throw $e;
        }

        $item->load('files');

        DB::afterCommit(function () use ($item, $tenantId, $companyId): void {
            ProcessIntakeOcr::dispatch($item->id, $tenantId, $companyId);
        });

        return $item;
    }

    /**
     * Move an intake item out of the staging queue without destroying it.
     *
     * A discard is a governance decision, not a deletion: the stored bytes stay on disk and
     * the activity trail stays intact, so an abandoned upload can still be audited.
     */
    public function discard(RecordIntakeItem $item, ?User $user = null): RecordIntakeItem
    {
        $company = $this->context->current();
        $tenantId = (string) $company->tenant_id;
        $companyId = (string) $company->id;

        $this->guardItemScope($item, $tenantId, $companyId);

        return DB::transaction(function () use ($item, $tenantId, $companyId, $user): RecordIntakeItem {
            $locked = RecordIntakeItem::query()
                ->whereKey($item->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardItemScope($locked, $tenantId, $companyId);

            if ($locked->status === IntakeStatus::Filed) {
                throw new LogicException('A filed intake item cannot be discarded. Archive the Record instead.');
            }

            if ($locked->status === IntakeStatus::Discarded) {
                throw new LogicException('This intake item has already been discarded.');
            }

            $previous = $locked->status;

            $locked->forceFill(['status' => IntakeStatus::Discarded])->save();

            $this->log(
                $locked,
                'intake.discarded',
                'Removed from Unclassified Records.',
                [
                    'previous_status' => $previous->value,
                    'storage_bytes' => (int) $locked->storage_bytes,
                    'files' => $locked->files()->count(),
                ],
                null,
                $user?->id,
            );

            return $locked->refresh();
        });
    }

    private function guardItemScope(RecordIntakeItem $item, string $tenantId, string $companyId): void
    {
        if ((string) $item->tenant_id !== $tenantId || (string) $item->company_id !== $companyId) {
            throw new LogicException('That intake item does not belong to the active company.');
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function createFile(
        RecordIntakeItem $item,
        UploadedFile $file,
        string $path,
        array $details,
        mixed $uploadedBy,
    ): RecordFile {
        $recordFile = RecordFile::query()->create([
            'tenant_id' => $item->tenant_id,
            'company_id' => $item->company_id,
            'record_intake_item_id' => $item->id,
            'file_role' => FileRole::Master,
            'disk' => (string) config('filesystems.default'),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $details['mime_type'],
            'extension' => $details['extension'],
            'size_bytes' => $details['size_bytes'],
            'page_count' => $details['page_count'],
            'checksum_sha256' => $details['sha256'],
            'ocr_status' => OcrStatus::Queued,
            'uploaded_by' => $uploadedBy,
        ]);

        $this->log(
            $item,
            'intake.file_uploaded',
            sprintf('%s (%s) stored.', $recordFile->original_name, $details['document_type']),
            [
                'record_file_id' => $recordFile->id,
                'size_bytes' => $details['size_bytes'],
                'page_count' => $details['page_count'],
                'checksum_sha256' => $details['sha256'],
            ],
            $recordFile,
        );

        return $recordFile;
    }

    private function storeFile(UploadedFile $file, string $tenantId, string $companyId, string $itemId): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::ulid()->toBase32().($extension !== '' ? '.'.$extension : '');

        $path = sprintf('intake/%s/%s/%s/%s', $tenantId, $companyId, $itemId, $name);

        Storage::disk((string) config('filesystems.default'))->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return $path;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guardAgainstSuppliedScope(array $attributes): void
    {
        foreach (['tenant_id', 'company_id'] as $key) {
            if (array_key_exists($key, $attributes)) {
                throw new LogicException("Tenant and company scope is resolved server-side; [{$key}] cannot be supplied.");
            }
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function discardStoredFiles(array $paths): void
    {
        $disk = Storage::disk((string) config('filesystems.default'));

        foreach ($paths as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable) {
                // The database rollback is authoritative; a stray file must not mask the original failure.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(
        RecordIntakeItem $item,
        string $action,
        string $description,
        array $properties = [],
        ?RecordFile $file = null,
        ?string $userId = null,
    ): void {
        RecordActivityLog::query()->create([
            'tenant_id' => $item->tenant_id,
            'company_id' => $item->company_id,
            'record_intake_item_id' => $item->id,
            'record_file_id' => $file?->id,
            'user_id' => $userId ?? $item->uploaded_by,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function fallbackTitle(array $files): string
    {
        $name = $files[0]->getClientOriginalName();
        $base = pathinfo($name, PATHINFO_FILENAME);

        return $base !== '' ? Str::limit($base, 180, '') : 'Untitled intake item';
    }
}

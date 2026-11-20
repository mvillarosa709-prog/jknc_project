<?php

namespace App\Services\Records;

use App\Enums\IntakeStatus;
use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordClassification;
use App\Models\RecordFile;
use App\Models\RecordIndex;
use App\Models\RecordIntakeItem;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\DocumentReferenceGenerator;
use App\Support\RecordNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class RecordFilingService
{
    /**
     * How many times a filing is retried when the Document Reference collides.
     *
     * The reference is five glyphs from a 32-character alphabet, so the keyspace is
     * about 33.5 million and a collision is rare — but the records table carries a
     * unique index on (tenant_id, company_id, document_reference), and that index
     * is what actually decides. DocumentReferenceGenerator's own existence loop
     * cannot see uncommitted rows, so two filings landing together can still reach
     * it. One retry turns an HTTP 500 into a filed record.
     */
    private const REFERENCE_COLLISION_ATTEMPTS = 3;

    /**
     * The index whose violation is safe to treat as a lost race.
     *
     * Matching on this rather than on "any QueryException" matters: retrying a
     * foreign key failure or a check violation three times would delay a real bug
     * and then report it as something it is not.
     */
    private const REFERENCE_INDEX = 'records_tenant_company_doc_ref_unique';

    public function __construct(
        private readonly CompanyContext $context,
        private readonly DocumentReferenceGenerator $references,
        private readonly RecordNumberGenerator $numbers,
        private readonly RecordCapacityService $capacity,
    ) {}

    /**
     * Transition an intake item into an official Record.
     *
     * The Document Reference is assigned here, once, at official filing. The stored bytes are
     * never moved: record_files are re-pointed at the new record so the original master file
     * and its checksum stay exactly as they were captured at intake.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function file(RecordIntakeItem $item, array $attributes = [], ?User $filedBy = null): Record
    {
        $this->guardAgainstSuppliedScope($attributes);

        $company = $this->context->current();
        $tenantId = (string) $company->tenant_id;
        $companyId = (string) $company->id;

        $this->guardItemIsOpen($item, $tenantId, $companyId);
        $this->capacity->verifyFilingCapacity();

        return $this->fileWithRetry($item, $attributes, $filedBy, $tenantId, $companyId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fileInTransaction(RecordIntakeItem $item, array $attributes, ?User $filedBy, string $tenantId, string $companyId): Record
    {
        return DB::transaction(function () use ($item, $attributes, $filedBy, $tenantId, $companyId): Record {
            $locked = RecordIntakeItem::query()
                ->whereKey($item->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardItemIsOpen($locked, $tenantId, $companyId);

            $classification = $this->resolveClassification($attributes['record_classification_id'] ?? null, $tenantId, $companyId);
            $index = $this->resolveIndex(
                $attributes['record_index_id'] ?? null,
                $classification,
                $tenantId,
                $companyId,
            );

            // The Record ID counter lives on the company row, so it is locked
            // here rather than the intake item above. Locking the item does not
            // help: two *different* items filed at the same moment would each
            // read the same counter and take the same number. The unique index
            // is still the real guarantee; this keeps it from being reached.
            $lockedCompany = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $record = Record::query()->create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'record_classification_id' => $classification->id,
                'record_index_id' => $index->id,
                'record_intake_item_id' => $locked->id,
                'document_reference' => $this->references->generate($tenantId, $companyId),
                'record_number' => $this->numbers->allocate($lockedCompany),
                'title' => $this->title($attributes['title'] ?? null, $locked),
                'document_date' => $attributes['document_date'] ?? $locked->document_date,
                'source' => $attributes['source'] ?? $locked->source,
                'external_reference_number' => $attributes['external_reference_number'] ?? $locked->external_reference_number,
                'identifiers' => $this->json($attributes['identifiers'] ?? null),
                'additional_dates' => $this->json($attributes['additional_dates'] ?? null),
                'signatories' => $this->json($attributes['signatories'] ?? null),
                'financials' => $this->json($attributes['financials'] ?? null),
                'status' => RecordStatus::Filed,
                'record_units' => 1,
                'storage_bytes' => (int) $locked->storage_bytes,
                'is_confidential' => (bool) ($attributes['is_confidential'] ?? false),
                'filed_at' => now(),
                'filed_by' => $filedBy?->id,
                'created_by' => $filedBy?->id ?? $locked->uploaded_by,
            ]);

            $repointed = RecordFile::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('record_intake_item_id', $locked->id)
                ->update(['record_id' => $record->id]);

            $locked->forceFill([
                'status' => IntakeStatus::Filed,
                'filed_at' => now(),
            ])->save();

            $this->log($record, 'record.filed', sprintf(
                'Filed as %s under %s / %s.',
                $record->document_reference,
                $classification->code,
                $index->code,
            ), [
                'intake_item_id' => $locked->id,
                'classification_code' => $classification->code,
                'index_code' => $index->code,
                'document_reference' => $record->document_reference,
                'record_units' => $record->record_units,
                'storage_bytes' => $record->storage_bytes,
                'files_repointed' => $repointed,
            ], $filedBy?->id);

            return $record->load(['classification', 'index', 'files']);
        });
    }

    /**
     * Run the filing transaction, retrying only a lost race for the Document
     * Reference.
     *
     * The retry sits *outside* the transaction rather than inside it, because that
     * is the only portable place to put it: on PostgreSQL a unique violation
     * poisons the whole transaction, so every statement after it fails with 25P02
     * and retrying within the same transaction cannot work. MySQL only aborts the
     * offending statement, which is why an in-transaction retry would appear to
     * work there and fail in production on the other engine.
     *
     * Each attempt re-runs the entire transaction, so the intake item is re-locked
     * and re-checked rather than resumed. That matters because a failed attempt
     * rolled back: the item is open again and the capacity check has to hold.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fileWithRetry(RecordIntakeItem $item, array $attributes, ?User $filedBy, string $tenantId, string $companyId): Record
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->fileInTransaction($item, $attributes, $filedBy, $tenantId, $companyId);
            } catch (QueryException $e) {
                $attempt++;

                if ($attempt >= self::REFERENCE_COLLISION_ATTEMPTS || ! $this->isReferenceCollision($e)) {
                    throw $e;
                }

                // Collision, not failure. Logged at warning rather than reported: the filing
                // succeeds on the retry, so this is an anomaly worth being able to
                // find — a rising rate would mean the keyspace is filling up —
                // but reporting it would page someone for something that worked.
                Log::warning('Document Reference collision on filing; retried with a new reference.', [
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'attempt' => $attempt,
                ]);
            }
        }
    }

    /**
     * Whether this failure is the Document Reference index and nothing else.
     *
     * Matching the index name keeps a foreign key violation or a check constraint
     * from being retried three times and then reported as a collision.
     */
    private function isReferenceCollision(QueryException $e): bool
    {
        // SQLSTATE class 23 is an integrity-constraint violation: MySQL 23000,
        // PostgreSQL 23505, SQLite 23000. Requiring it stops a foreign key or
        // not-null failure elsewhere in the insert from being retried and then
        // reported as a collision.
        if (! str_starts_with((string) $e->getCode(), '23')) {
            return false;
        }

        // The index name alone would not be portable: MySQL and PostgreSQL name
        // the index, but SQLite reports the columns instead, so a match on the
        // name alone would mean the retry never fires under the test suite.
        $message = $e->getMessage();

        return str_contains($message, self::REFERENCE_INDEX)
            || str_contains($message, 'document_reference');
    }

    /**
     * File several intake items together so a mid-batch failure files none of them.
     *
     * @param  array<int, string>  $itemIds
     * @param  array<string, mixed>  $attributes
     * @return array<int, Record>
     */
    public function fileMany(array $itemIds, array $attributes = [], ?User $filedBy = null): array
    {
        $this->guardAgainstSuppliedScope($attributes);

        if ($itemIds === []) {
            throw new LogicException('At least one intake item is required to file records.');
        }

        $company = $this->context->current();
        $tenantId = (string) $company->tenant_id;
        $companyId = (string) $company->id;

        $items = RecordIntakeItem::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereIn('id', $itemIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($items->count() !== count(array_unique($itemIds))) {
            throw new LogicException('One or more of the selected intake items could not be found.');
        }

        return DB::transaction(function () use ($items, $attributes, $filedBy): array {
            $records = [];

            foreach ($items as $item) {
                $records[] = $this->file($item, $attributes, $filedBy);
            }

            return $records;
        });
    }

    private function guardItemIsOpen(RecordIntakeItem $item, string $tenantId, string $companyId): void
    {
        if ((string) $item->tenant_id !== $tenantId || (string) $item->company_id !== $companyId) {
            throw new LogicException('That intake item does not belong to the active company.');
        }

        if ($item->status === IntakeStatus::Filed) {
            throw new LogicException('This intake item has already been filed.');
        }

        if (! $item->status->isOpen()) {
            throw new LogicException(sprintf(
                'An intake item with status [%s] cannot be filed.',
                $item->status->value,
            ));
        }

        if ($item->files()->whereNull('record_id')->doesntExist()) {
            throw new LogicException('This intake item has no files to file.');
        }
    }

    private function resolveClassification(mixed $id, string $tenantId, string $companyId): RecordClassification
    {
        $classification = RecordClassification::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereKey(is_string($id) || is_int($id) ? $id : '')
            ->first();

        if (! $classification instanceof RecordClassification) {
            throw new LogicException('Select a valid Classification for the active company.');
        }

        if (! $classification->is_active) {
            throw new LogicException(sprintf(
                'Classification [%s] is not active and cannot be used for filing.',
                $classification->code,
            ));
        }

        return $classification;
    }

    private function resolveIndex(mixed $id, RecordClassification $classification, string $tenantId, string $companyId): RecordIndex
    {
        $index = RecordIndex::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereKey(is_string($id) || is_int($id) ? $id : '')
            ->first();

        if (! $index instanceof RecordIndex) {
            throw new LogicException('Select a valid Index for the active company.');
        }

        if ((string) $index->record_classification_id !== (string) $classification->id) {
            throw new LogicException(sprintf(
                'Index [%s] does not belong to Classification [%s].',
                $index->code,
                $classification->code,
            ));
        }

        if (! $index->is_active) {
            throw new LogicException(sprintf(
                'Index [%s] is not active and cannot be used for filing.',
                $index->code,
            ));
        }

        return $index;
    }

    private function title(mixed $title, RecordIntakeItem $item): string
    {
        $title = is_string($title) ? trim($title) : '';

        return $title !== '' ? $title : $item->title;
    }

    private function json(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $filtered = array_filter($value, static fn (mixed $item): bool => $item !== null && $item !== '');

        return $filtered === [] ? null : array_values($filtered);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(
        Record $record,
        string $action,
        string $description,
        array $properties = [],
        ?string $userId = null,
    ): void {
        RecordActivityLog::query()->create([
            'tenant_id' => $record->tenant_id,
            'company_id' => $record->company_id,
            'record_id' => $record->id,
            'record_intake_item_id' => $record->record_intake_item_id,
            'user_id' => $userId ?? $record->filed_by,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()?->ip(),
        ]);
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
}

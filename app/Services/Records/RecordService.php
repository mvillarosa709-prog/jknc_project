<?php

namespace App\Services\Records;

use App\Enums\RecordStatus;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecordService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * Update the confirmed metadata on an already-filed Record.
     *
     * Classification, Index and the Document Reference are deliberately not editable here:
     * they define the record's place in the scheme and its official identity, both of
     * which are fixed at filing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateMetadata(Record $record, array $attributes, ?User $actor = null): Record
    {
        $this->guardAgainstSuppliedScope($attributes);
        $this->guardRecordScope($record);
        $this->guardNoLegalHold($record);

        return DB::transaction(function () use ($record, $attributes, $actor): Record {
            $locked = Record::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-checked against the locked row: the caller's model may predate a
            // hold placed between their read and this write.
            $this->guardRecordScope($locked);
            $this->guardNoLegalHold($locked);

            if ($locked->status === RecordStatus::Archived) {
                throw new LogicException('An archived record cannot be edited.');
            }

            $changes = array_filter([
                'title' => $this->text($attributes['title'] ?? null, $locked->title),
                'document_date' => $attributes['document_date'] ?? $locked->document_date,
                'source' => $attributes['source'] ?? $locked->source,
                'external_reference_number' => $attributes['external_reference_number'] ?? $locked->external_reference_number,
                'amount' => $attributes['amount'] ?? $locked->amount,
                'currency' => $attributes['currency'] ?? $locked->currency,
                'notes' => $attributes['notes'] ?? $locked->notes,
                'identifiers' => $this->json($attributes['identifiers'] ?? null),
                'additional_dates' => $this->json($attributes['additional_dates'] ?? null),
                'signatories' => $this->json($attributes['signatories'] ?? null),
                'financials' => $this->json($attributes['financials'] ?? null),
                'retention_period' => $attributes['retention_period'] ?? $locked->retention_period,
                'is_confidential' => (bool) ($attributes['is_confidential'] ?? false),
            ], static fn (mixed $value, string $key): bool => $locked->{$key} != $value, ARRAY_FILTER_USE_BOTH);

            if ($changes === []) {
                return $locked;
            }

            $locked->forceFill($changes)->save();

            $this->log($locked, 'record.metadata_updated', sprintf(
                'Metadata updated: %s.',
                implode(', ', array_keys($changes)),
            ), ['fields' => array_keys($changes)], $actor?->id);

            return $locked->refresh();
        });
    }

    /**
     * Archive a filed Record.
     *
     * Archiving is a lifecycle transition, not a deletion: the record stays in the
     * register, keeps its Document Reference and Record Unit, and remains searchable.
     */
    public function archive(Record $record, ?User $actor = null, ?string $reason = null): Record
    {
        $this->guardRecordScope($record);
        $this->guardNoLegalHold($record);

        return DB::transaction(function () use ($record, $actor, $reason): Record {
            $locked = Record::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-checked against the locked row, for the same reason as above.
            $this->guardRecordScope($locked);
            $this->guardNoLegalHold($locked);

            if ($locked->status === RecordStatus::Archived) {
                throw new LogicException('This record is already archived.');
            }

            $locked->forceFill([
                'status' => RecordStatus::Archived,
                // Stamped in the same transaction as the status it describes, so
                // the Archive listing can order by it without reading the trail.
                'archived_at' => now(),
            ])->save();

            $this->log($locked, 'record.archived', $reason !== null && trim($reason) !== ''
                ? sprintf('Archived: %s', trim($reason))
                : 'Record archived.', [
                    'previous_status' => RecordStatus::Filed->value,
                    'document_reference' => $locked->document_reference,
                ], $actor?->id);

            return $locked->refresh();
        });
    }

    /**
     * Restore an archived Record.
     *
     * The inverse of archive(), and deliberately as narrow as it: the status
     * returns to Filed and the archived date is cleared, but the Document
     * Reference and the Record Unit are left exactly as they were. Restoring is a
     * lifecycle change, not a re-filing, so nothing is re-allocated and nothing
     * is re-numbered.
     */
    public function restore(Record $record, ?User $actor = null): Record
    {
        $this->guardRecordScope($record);
        $this->guardNoLegalHold($record);

        return DB::transaction(function () use ($record, $actor): Record {
            $locked = Record::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardRecordScope($locked);
            $this->guardNoLegalHold($locked);

            if ($locked->status !== RecordStatus::Archived) {
                throw new LogicException('Only an archived record can be restored.');
            }

            $locked->forceFill([
                'status' => RecordStatus::Filed,
                'archived_at' => null,
            ])->save();

            $this->log($locked, 'record.restored', 'Record restored from the archive.', [
                'previous_status' => RecordStatus::Archived->value,
                'document_reference' => $locked->document_reference,
            ], $actor?->id);

            return $locked->refresh();
        });
    }

    private function guardRecordScope(Record $record): void
    {
        if ((string) $record->tenant_id !== $this->context->tenantId() || (string) $record->company_id !== $this->context->id()) {
            throw new LogicException('That record does not belong to the active company.');
        }
    }

    /**
     * A record under legal hold is frozen. Editing it would alter the very
     * document the hold exists to preserve, and archiving it would move it out of
     * the active register while the hold is in force.
     */
    private function guardNoLegalHold(Record $record): void
    {
        if ($record->isOnLegalHold()) {
            throw new LogicException(sprintf(
                'Record %s is under a legal hold and cannot be changed until the hold is released.',
                (string) $record->document_reference,
            ));
        }
    }

    private function text(mixed $value, string $fallback): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text !== '' ? $text : $fallback;
    }

    /**
     * @return array<string, mixed>
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
     * @param  array<int, string>|null  $value
     * @return array<int, string>|null
     */
    private function json(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $filtered = array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value),
            static fn (string $item): bool => $item !== '',
        ));

        return $filtered === [] ? null : $filtered;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Record $record, string $action, string $description, array $properties = [], ?string $userId = null): void
    {
        RecordActivityLog::query()->create([
            'tenant_id' => $record->tenant_id,
            'company_id' => $record->company_id,
            'record_id' => $record->id,
            'record_intake_item_id' => $record->record_intake_item_id,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()?->ip(),
        ]);
    }
}

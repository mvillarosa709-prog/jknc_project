<?php

namespace App\Services\Records;

use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Legal holds and retention expiry.
 *
 * A legal hold is a freeze, not a lock-out: it stops the record being edited or
 * archived until someone with the authority to do so releases it, and it records
 * who held it, when, and why. Retention expiry is computed separately from the
 * hold, because a held record's retention clock may legitimately have run out
 * without the document becoming disposable.
 */
class RecordRetentionService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * Place a legal hold on a record.
     *
     * The reason is required and kept on the record itself, not only in the
     * activity log: a hold needs to be explicable from the record years later,
     * without reconstructing it from an audit trail.
     */
    public function applyLegalHold(Record $record, string $reason, User $user): Record
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new LogicException('A legal hold needs a reason before it can be applied.');
        }

        return DB::transaction(function () use ($record, $reason, $user): Record {
            $locked = $this->lockedRecord($record);

            if ($locked->isOnLegalHold()) {
                throw new LogicException('This record is already under a legal hold.');
            }

            $locked->forceFill([
                'is_legal_hold' => true,
                'legal_hold_reason' => $reason,
                'legal_hold_set_at' => now(),
                'legal_hold_set_by' => $user->id,
            ])->save();

            $this->log($locked, 'record.legal_hold_applied', sprintf(
                'Legal hold applied: %s.',
                $reason,
            ), [
                'legal_hold_reason' => $reason,
                'legal_hold_set_at' => (string) $locked->legal_hold_set_at,
                'set_by' => $user->name,
            ], $user->id);

            return $locked->refresh();
        });
    }

    /**
     * Lift a legal hold, returning the record to normal handling.
     */
    public function releaseLegalHold(Record $record, User $user): Record
    {
        return DB::transaction(function () use ($record, $user): Record {
            $locked = $this->lockedRecord($record);

            if (! $locked->isOnLegalHold()) {
                throw new LogicException('This record is not under a legal hold.');
            }

            $previousReason = (string) $locked->legal_hold_reason;

            $locked->forceFill([
                'is_legal_hold' => false,
                'legal_hold_reason' => null,
                'legal_hold_set_at' => null,
                'legal_hold_set_by' => null,
            ])->save();

            $this->log($locked, 'record.legal_hold_released', sprintf(
                'Legal hold released. It was applied for: %s.',
                $previousReason !== '' ? $previousReason : 'no reason recorded',
            ), [
                'previous_legal_hold_reason' => $previousReason,
                'released_by' => $user->name,
            ], $user->id);

            return $locked->refresh();
        });
    }

    /**
     * Work out when this record's retention period ends.
     *
     * The rule lives on the classification as an integer number of months.
     * records.retention_period is a free-text label a person typed and is never
     * read here, because prose such as "retain permanently" cannot be computed
     * from reliably.
     *
     * The clock runs from the document's own date. A record with no usable date
     * gets no expiry rather than one dated from today, which would invent a
     * retention window that never existed.
     */
    public function calculateRetentionExpiry(Record $record): ?Carbon
    {
        // Setting an expiry writes to the record, and a held record is frozen.
        // The clock can still be reported; only the write is refused.
        if ($record->isOnLegalHold()) {
            throw new LogicException(sprintf(
                'Record %s is under a legal hold, so its retention expiry cannot be changed.',
                (string) $record->document_reference,
            ));
        }

        $months = $record->classification?->retention_months;

        if ($months === null) {
            return null;
        }

        $start = $record->document_date ?? $record->filed_at?->toDateString() ?? $record->created_at;

        if ($start === null) {
            return null;
        }

        $expiry = Carbon::parse($start)->addMonthsNoOverflow($months)->startOfDay();

        $record->forceFill(['retention_until' => $expiry->toDateString()])->save();

        return $expiry;
    }

    /**
     * The state a retention review needs for one record.
     *
     * @return array<string, mixed>
     */
    public function status(Record $record): array
    {
        $months = $record->classification?->retention_months;

        return [
            'is_legal_hold' => $record->isOnLegalHold(),
            'legal_hold_reason' => $record->legal_hold_reason,
            'legal_hold_set_at' => $record->legal_hold_set_at?->format('Y-m-d H:i'),
            'legal_hold_set_by' => $record->legalHoldSetBy?->name,
            'retention_label' => $record->retention_period,
            'retention_months' => $months,
            'retention_until' => $record->retention_until?->format('Y-m-d'),
        ];
    }

    /**
     * Records whose retention has run out, and whether they are held.
     *
     * @return Collection<int, Record>
     */
    public function expired()
    {
        return Record::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->whereNotNull('retention_until')
            ->whereDate('retention_until', '<', now())
            ->with(['classification', 'legalHoldSetBy'])
            ->orderBy('retention_until')
            ->get();
    }

    private function lockedRecord(Record $record): Record
    {
        $locked = Record::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->whereKey($record->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked instanceof Record) {
            throw new LogicException('That record does not belong to the active company.');
        }

        return $locked;
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

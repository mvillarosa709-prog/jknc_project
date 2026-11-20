<?php

namespace App\Services\Records;

use App\Enums\LinkRelationship;
use App\Enums\RecordStatus;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordLink;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Links between records and between a record and an ORDO module object.
 *
 * Two rules hold for every link written here. Linking copies nothing: the bytes
 * already stored against each record stay where they are, so a link adds 0 storage
 * bytes and 0 Record Units to either side. And a record-to-record link is always
 * reciprocal, so the same fact is readable from either record's Linked To tab.
 */
class RecordLinkService
{
    /**
     * record_links.linked_id is a ULID column, so a module reference has to fit the
     * same 26 characters a record id would.
     */
    private const REFERENCE_LENGTH = 26;

    public function __construct(private readonly CompanyContext $context) {}

    /**
     * Link two records, writing the relationship from both sides.
     *
     * The caller states the relationship from $source's point of view; the mirror
     * row carries the inverse, so a "Supports" link shows as "Supported By" when the
     * target record is opened.
     */
    public function linkRecordToRecord(Record $source, Record $target, LinkRelationship $relationship, ?User $actor = null): RecordLink
    {
        if ((string) $source->id === (string) $target->id) {
            throw new LogicException('A record cannot be linked to itself.');
        }

        return DB::transaction(function () use ($source, $target, $relationship, $actor): RecordLink {
            $lockedSource = $this->lockedRecord($source);
            $lockedTarget = $this->lockedRecord($target);

            $this->guardLinkable($lockedSource);
            $this->guardLinkable($lockedTarget);

            $forward = $this->createLink($lockedSource, Record::class, (string) $lockedTarget->id, $relationship, $this->recordLabel($lockedTarget), $actor);

            $mirror = $this->createLink(
                $lockedTarget,
                Record::class,
                (string) $lockedSource->id,
                $relationship->inverse(),
                $this->recordLabel($lockedSource),
                $actor,
            );

            $this->log($lockedSource, 'record.linked', sprintf(
                'Linked as %s to %s (%s).',
                $relationship->label(),
                $this->recordLabel($lockedTarget),
                (string) $lockedTarget->document_reference,
            ), [
                'linked_type' => Record::class,
                'linked_id' => (string) $lockedTarget->id,
                'link_type' => $relationship->value,
                'reciprocal_link_id' => (string) $mirror->id,
                'record_units_added' => 0,
                'storage_bytes_added' => 0,
            ], $actor?->id);

            $this->log($lockedTarget, 'record.linked', sprintf(
                'Linked as %s to %s (%s).',
                $relationship->inverse()->label(),
                $this->recordLabel($lockedSource),
                (string) $lockedSource->document_reference,
            ), [
                'linked_type' => Record::class,
                'linked_id' => (string) $lockedSource->id,
                'link_type' => $relationship->inverse()->value,
                'reciprocal_link_id' => (string) $forward->id,
                'record_units_added' => 0,
                'storage_bytes_added' => 0,
            ], $actor?->id);

            return $forward;
        });
    }

    /**
     * Link a record to an object in another ORDO module.
     *
     * Module links are one-directional by nature: the far side is not a record in
     * this module, so there is nothing to write a reciprocal row against. The
     * reference is stored as given and is not verified to exist, because the
     * modules it points at are not built yet.
     */
    public function linkRecordToModule(Record $record, string $module, string $referenceId, string $title, LinkRelationship $relationship, ?User $actor = null): RecordLink
    {
        $module = $this->moduleSlug($module);
        $referenceId = trim($referenceId);

        if ($referenceId === '') {
            throw new LogicException('A module reference is required to link an ORDO object.');
        }

        if (strlen($referenceId) > self::REFERENCE_LENGTH) {
            throw new LogicException(sprintf('A module reference cannot be longer than %d characters.', self::REFERENCE_LENGTH));
        }

        return DB::transaction(function () use ($record, $module, $referenceId, $title, $relationship, $actor): RecordLink {
            $locked = $this->lockedRecord($record);

            $this->guardLinkable($locked);

            $link = $this->createLink($locked, $module, $referenceId, $relationship, trim($title), $actor);

            $this->log($locked, 'record.linked', sprintf(
                'Linked as %s to the %s object %s.',
                $relationship->label(),
                $link->moduleLabel(),
                $link->linked_label !== null && $link->linked_label !== '' ? $link->linked_label : $referenceId,
            ), [
                'linked_type' => $module,
                'linked_id' => $referenceId,
                'link_type' => $relationship->value,
                'record_units_added' => 0,
                'storage_bytes_added' => 0,
            ], $actor?->id);

            return $link;
        });
    }

    /**
     * Remove a link, and its reciprocal when the link pointed at another record.
     *
     * Only the row is deleted. Neither record's files, storage or Record Unit move.
     */
    public function unlink(RecordLink $link, ?User $actor = null): void
    {
        DB::transaction(function () use ($link, $actor): void {
            $locked = RecordLink::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->whereKey($link->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof RecordLink) {
                throw new LogicException('That link does not belong to the active company.');
            }

            $targetsRecord = $locked->targetsRecord();

            $reciprocal = $targetsRecord ? $locked->reciprocal() : null;

            $linkedLabel = $locked->linked_label;
            $relationship = $locked->link_type;
            $moduleLabel = $locked->moduleLabel();

            $locked->delete();

            $reciprocal?->delete();

            $this->log($locked->record, 'record.unlinked', sprintf(
                'Unlinked from %s (%s).',
                $linkedLabel !== null && $linkedLabel !== '' ? $linkedLabel : $moduleLabel,
                $targetsRecord ? (string) $locked->linked_id : 'ORDO module object',
            ), [
                'linked_type' => $locked->linked_type,
                'linked_id' => (string) $locked->linked_id,
                'link_type' => $relationship->value,
                'reciprocal_removed' => $reciprocal !== null,
                'storage_bytes_added' => 0,
            ], $actor?->id);
        });
    }

    private function createLink(
        Record $record,
        string $linkedType,
        string $linkedId,
        LinkRelationship $relationship,
        ?string $label,
        ?User $actor,
        ?string $notes = null,
    ): RecordLink {
        $existing = RecordLink::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('company_id', $record->company_id)
            ->where('record_id', $record->id)
            ->where('linked_type', $linkedType)
            ->where('linked_id', $linkedId)
            ->exists();

        if ($existing) {
            throw new LogicException(sprintf(
                '%s is already linked to this record.',
                $linkedType === Record::class ? 'That record' : 'That ORDO object',
            ));
        }

        return RecordLink::query()->create([
            'tenant_id' => $record->tenant_id,
            'company_id' => $record->company_id,
            'record_id' => $record->id,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'linked_label' => $label !== null && $label !== '' ? $label : null,
            'link_type' => $relationship->value,
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Re-read the record under the current company scope and lock it, so a model
     * handed in from elsewhere cannot smuggle a foreign id into a link.
     */
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

    private function guardLinkable(Record $record): void
    {
        if ($record->status === RecordStatus::Archived) {
            throw new LogicException('An archived record cannot be linked.');
        }

        if (! $record->isFiled()) {
            throw new LogicException('Only an officially filed record can be linked.');
        }
    }

    private function recordLabel(Record $record): string
    {
        return (string) ($record->document_reference ?? $record->title);
    }

    private function moduleSlug(string $module): string
    {
        $slug = strtolower(trim(str_replace([' ', '-'], '_', $module)));

        if ($slug === '') {
            throw new LogicException('Name the ORDO module this object belongs to.');
        }

        return $slug;
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

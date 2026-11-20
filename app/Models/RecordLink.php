<?php

namespace App\Models;

use App\Enums\LinkRelationship;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecordLink extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'record_id',
        'linked_type',
        'linked_id',
        'linked_label',
        'link_type',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'link_type' => LinkRelationship::class,
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * Polymorphic destination. linked_type stores the module or model type, so a
     * link may target another Record today and any ORDO module later.
     */
    public function linked(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Record-to-record links store the Record class as the polymorphic type so the
     * morph resolves. Anything else is an ORDO module slug.
     */
    public function targetsRecord(): bool
    {
        return $this->linked_type === Record::class;
    }

    /**
     * A short module name for the drawer. The raw linked_type of a record link is
     * the fully qualified class name, which is not something to show a user.
     */
    public function moduleLabel(): string
    {
        if ($this->targetsRecord()) {
            return 'Records';
        }

        return ucwords(str_replace(['_', '-'], ' ', (string) $this->linked_type));
    }

    /**
     * The record this link points at, when it points at another record rather than
     * an ORDO module. Scoped to the link's own company so a link can never resolve
     * across the tenant boundary.
     */
    public function linkedRecord(): ?Record
    {
        if (! $this->targetsRecord()) {
            return null;
        }

        return Record::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('company_id', $this->company_id)
            ->find($this->linked_id);
    }

    /**
     * The mirror link, when this link targets another record. The reciprocal is
     * derived rather than flagged, so it stays correct no matter which side of the
     * relationship is being read.
     */
    public function reciprocal(): ?self
    {
        if (! $this->targetsRecord()) {
            return null;
        }

        return self::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('company_id', $this->company_id)
            ->where('record_id', $this->linked_id)
            ->where('linked_type', $this->linked_type)
            ->where('linked_id', $this->record_id)
            ->first();
    }

    /**
     * Linking never copies the master file; both sides reference existing bytes.
     */
    public function duplicatesStorage(): bool
    {
        return false;
    }

    /**
     * Links are structural. They never add a Record Unit to either record.
     */
    public function recordUnits(): int
    {
        return 0;
    }
}

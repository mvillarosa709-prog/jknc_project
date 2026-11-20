<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Record extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'record_classification_id',
        'record_index_id',
        'record_intake_item_id',
        'document_reference',
        'record_number',
        'title',
        'document_date',
        'source',
        'external_reference_number',
        'amount',
        'currency',
        'notes',
        'identifiers',
        'additional_dates',
        'signatories',
        'financials',
        'status',
        'record_units',
        'storage_bytes',
        'retention_period',
        'retention_until',
        'is_legal_hold',
        'legal_hold_reason',
        'legal_hold_set_at',
        'legal_hold_set_by',
        'is_confidential',
        'filed_at',
        'archived_at',
        'filed_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'identifiers' => 'array',
            'additional_dates' => 'array',
            'signatories' => 'array',
            'financials' => 'array',
            'amount' => 'decimal:2',
            'status' => RecordStatus::class,
            'document_date' => 'date',
            'retention_until' => 'date',
            'filed_at' => 'datetime',
            'archived_at' => 'datetime',
            'legal_hold_set_at' => 'datetime',
            'record_units' => 'integer',
            'storage_bytes' => 'integer',
            'is_confidential' => 'boolean',
            'is_legal_hold' => 'boolean',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(RecordClassification::class, 'record_classification_id');
    }

    public function index(): BelongsTo
    {
        return $this->belongsTo(RecordIndex::class, 'record_index_id');
    }

    public function intakeItem(): BelongsTo
    {
        return $this->belongsTo(RecordIntakeItem::class, 'record_intake_item_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class, 'record_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(RecordLink::class, 'record_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RecordActivityLog::class, 'record_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function legalHoldSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legal_hold_set_by');
    }

    /**
     * A record under legal hold is frozen: it cannot be edited or archived until
     * the hold is explicitly released.
     */
    public function isOnLegalHold(): bool
    {
        return (bool) $this->is_legal_hold;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The Document Reference is assigned once, at official filing.
     */
    public function isFiled(): bool
    {
        return $this->status === RecordStatus::Filed && filled($this->document_reference);
    }

    /**
     * An officially filed Record consumes exactly one Record Unit. Supporting files,
     * versions and record links add storage but never another Record Unit.
     */
    public function recordUnits(): int
    {
        return (int) $this->record_units;
    }
}

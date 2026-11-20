<?php

namespace App\Models;

use App\Enums\CaptureSource;
use App\Enums\IntakeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordIntakeItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'title',
        'source',
        'document_date',
        'external_reference_number',
        'suggested_classification_id',
        'suggested_index_id',
        'status',
        'capture_source',
        'ocr_result',
        'ocr_completed_at',
        'storage_bytes',
        'uploaded_by',
        'filed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IntakeStatus::class,
            'capture_source' => CaptureSource::class,
            'ocr_result' => 'array',
            'ocr_completed_at' => 'datetime',
            'document_date' => 'date',
            'storage_bytes' => 'integer',
            'filed_at' => 'datetime',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class, 'record_intake_item_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RecordActivityLog::class, 'record_intake_item_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(RecordClassification::class, 'suggested_classification_id');
    }

    public function index(): BelongsTo
    {
        return $this->belongsTo(RecordIndex::class, 'suggested_index_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The record this item became, once it was officially filed.
     *
     * A hasOne, not a belongsTo: the foreign key is `records.record_intake_item_id`,
     * so it lives on the other table. Declared the other way round it produced a
     * query against a `record_intake_items.record_intake_item_id` column that has
     * never existed, and because nothing read the relation the mistake stayed
     * invisible until the audit trail had to ask whether an item had been filed
     * into a confidential record.
     *
     * One item yields at most one record — filing closes the item.
     */
    public function record(): HasOne
    {
        return $this->hasOne(Record::class, 'record_intake_item_id');
    }

    /**
     * Intake consumes storage only. Record Units are assigned when an item is officially filed.
     */
    public function recordUnits(): int
    {
        return 0;
    }
}

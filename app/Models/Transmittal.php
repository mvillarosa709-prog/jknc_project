<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Transmittal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tracking_number',
        'external_reference',
        'subject',
        'purpose',
        'direction',
        'transmittal_date',
        'origin_office',
        'recipient_address',
        'related_company',
        'delivery_method',
        'delivery_type',
        'delivery_actions',
        'prepared_by_name',
        'prepared_at',
        'approved_by_name',
        'operations_manager',
        'delivered_by',
        'received_by',
        'processing_company',
        'confidentiality',
        'status',
        'prepared_by',
        'approved_by',
        'approved_at',
        'approved_snapshot',
        'approval_authority_code',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_from_status',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'approved_snapshot' => 'array',
            'transmittal_date' => 'date',
            'delivery_actions' => 'array',
            'prepared_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    protected static function booted(): void
    {
        static::updating(function (Transmittal $transmittal): void {
            if ($transmittal->getOriginal('approved_snapshot') !== null
                && $transmittal->isDirty([
                    'tracking_number',
                    'external_reference',
                    'subject',
                    'purpose',
                    'direction',
                    'transmittal_date',
                    'origin_office',
                    'recipient_address',
                    'related_company',
                    'delivery_method',
                    'delivery_type',
                    'delivery_actions',
                    'prepared_by_name',
                    'prepared_at',
                    'approved_by_name',
                    'operations_manager',
                    'delivered_by',
                    'received_by',
                    'processing_company',
                    'edit_token_hash',
                    'confidentiality',
                    'prepared_by',
                    'approved_by',
                    'approved_at',
                    'approved_snapshot',
                    'approval_authority_code',
                    'rejected_by',
                    'rejected_at',
                    'rejection_reason',
                ])) {
                throw new LogicException('Approved transmittal details and snapshots are immutable.');
            }

            if ($transmittal->getOriginal('status') === 'rejected' && $transmittal->isDirty()) {
                $editableFields = [
                    'external_reference',
                    'subject',
                    'purpose',
                    'direction',
                    'transmittal_date',
                    'origin_office',
                    'recipient_address',
                    'related_company',
                    'delivery_method',
                    'delivery_type',
                    'delivery_actions',
                    'operations_manager',
                    'delivered_by',
                    'received_by',
                    'processing_company',
                    'confidentiality',
                    'prepared_by_name',
                    'prepared_at',
                    'edit_token_hash',
                    'status',
                    'cancelled_by',
                    'cancelled_from_status',
                    'cancelled_at',
                    'cancellation_reason',
                ];

                if (! in_array($transmittal->status, ['rejected', 'pending_approval', 'cancelled'], true)
                    || array_diff(array_keys($transmittal->getDirty()), $editableFields) !== []) {
                    throw new LogicException('Rejected transmittals may only be edited when resubmitted for review.');
                }
            }

            if ($transmittal->getOriginal('approved_snapshot') !== null && $transmittal->isDirty('status')) {
                $allowedStatuses = match ($transmittal->getOriginal('status')) {
                    'approved' => ['sent'],
                    'sent' => ['acknowledged'],
                    default => [],
                };

                if (! in_array($transmittal->status, $allowedStatuses, true)) {
                    throw new LogicException('An approved transmittal cannot leave its delivery workflow.');
                }
            }

            if ($transmittal->getOriginal('status') === 'cancelled' && $transmittal->isDirty('status')) {
                throw new LogicException('Cancelled transmittals must be restored through the authorized restore action.');
            }
        });

        static::deleting(function (Transmittal $transmittal): void {
            if ($transmittal->isForceDeleting()
                && ($transmittal->getOriginal('approved_snapshot') !== null
                    || $transmittal->proofs()->exists()
                    || $transmittal->receipts()->exists()
                    || $transmittal->acknowledgments()->exists())) {
                throw new LogicException('A transmittal with an approval, proof, receipt, or acknowledgment cannot be permanently deleted.');
            }
        });
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(TransmittalRecipient::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TransmittalDocument::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransmittalItem::class)->orderBy('position');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(ProofOfTransmission::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(TransmittalReceipt::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TransmittalHistory::class);
    }

    public function acknowledgments(): HasMany
    {
        return $this->hasMany(ExternalAcknowledgment::class);
    }

    public function authorityCode(): HasOne
    {
        return $this->hasOne(ApprovalAuthorityCode::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class TransmittalRecipient extends Model
{
    protected $fillable = [
        'transmittal_id',
        'name',
        'email',
        'organization',
        'acknowledgment_token_hash',
        'acknowledgment_expires_at',
        'acknowledgment_revoked_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledgment_expires_at' => 'datetime',
            'acknowledgment_revoked_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (TransmittalRecipient $recipient): void {
            if ($recipient->transmittal()->whereNotNull('approved_snapshot')->exists()
                && $recipient->isDirty(['transmittal_id', 'name', 'email', 'organization'])) {
                throw new LogicException('Approved recipient details are immutable.');
            }
        });

        static::deleting(function (TransmittalRecipient $recipient): void {
            if ($recipient->transmittal()->whereNotNull('approved_snapshot')->exists()) {
                throw new LogicException('An approved recipient cannot be removed.');
            }
        });
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(ProofOfTransmission::class, 'recipient_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(TransmittalReceipt::class, 'recipient_id');
    }
}

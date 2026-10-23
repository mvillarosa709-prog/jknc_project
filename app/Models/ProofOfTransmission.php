<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProofOfTransmission extends Model
{
    protected $fillable = [
        'transmittal_id',
        'recipient_id',
        'document_id',
        'sent_by',
        'channel',
        'status',
        'provider_message_id',
        'transaction_reference',
        'acknowledged_at',
        'transmitted_at',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'transmitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (ProofOfTransmission $proof): void {
            if ($proof->isDirty(['transmittal_id', 'sent_by', 'transaction_reference'])) {
                throw new LogicException('Proof audit identity and ownership cannot be reassigned.');
            }
        });
        static::deleting(fn (ProofOfTransmission $proof) => throw new LogicException('Proof of transmission records are immutable.'));
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TransmittalRecipient::class, 'recipient_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(TransmittalDocument::class);
    }
}

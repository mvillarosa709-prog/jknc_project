<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ExternalAcknowledgment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transmittal_id',
        'recipient_id',
        'receipt_id',
        'event',
        'details',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('External acknowledgment events are immutable.'));
        static::deleting(fn () => throw new LogicException('External acknowledgment events are immutable.'));
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TransmittalRecipient::class, 'recipient_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(TransmittalReceipt::class);
    }
}

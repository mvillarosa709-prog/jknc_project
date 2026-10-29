<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TransmittalReceipt extends Model
{
    protected $fillable = [
        'transmittal_id',
        'recipient_id',
        'receipt_no',
        'receipt_date',
        'signed_name',
        'received_by',
        'comments',
        'receipt_code',
        'snapshot',
        'received_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (TransmittalReceipt $receipt) => throw new LogicException('Submitted receipts are immutable.'));
        static::deleting(fn (TransmittalReceipt $receipt) => throw new LogicException('Submitted receipts are immutable.'));
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TransmittalRecipient::class, 'recipient_id');
    }
}

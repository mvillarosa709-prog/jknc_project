<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TransmittalItem extends Model
{
    protected $fillable = [
        'transmittal_id',
        'document_id',
        'position',
        'particular',
        'unique_id',
        'quantity',
        'description',
        'remarks',
    ];

    protected static function booted(): void
    {
        static::updating(function (TransmittalItem $item): void {
            if ($item->transmittal()->whereNotNull('approved_snapshot')->exists()) {
                throw new LogicException('Approved transmittal items are immutable.');
            }
        });

        static::deleting(function (TransmittalItem $item): void {
            if ($item->transmittal()->whereNotNull('approved_snapshot')->exists()) {
                throw new LogicException('Approved transmittal items are immutable.');
            }
        });
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(TransmittalDocument::class);
    }
}

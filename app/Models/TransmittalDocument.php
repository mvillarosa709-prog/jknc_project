<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TransmittalDocument extends Model
{
    protected $fillable = [
        'transmittal_id',
        'uploaded_by',
        'category',
        'original_name',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'ocr_text',
        'extracted_data',
    ];

    protected function casts(): array
    {
        return ['extracted_data' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (TransmittalDocument $document): void {
            if ($document->transmittal()->whereNotNull('approved_snapshot')->exists()) {
                throw new LogicException('Approved transmittal documents are immutable.');
            }
        });

        static::deleting(function (TransmittalDocument $document): void {
            if ($document->transmittal()->whereNotNull('approved_snapshot')->exists()) {
                throw new LogicException('An approved transmittal document cannot be removed.');
            }
        });
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }
}

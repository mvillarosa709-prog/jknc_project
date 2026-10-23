<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApprovalAuthorityCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transmittal_id',
        'approved_by',
        'code',
        'frozen_snapshot',
        'snapshot_hash',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'frozen_snapshot' => 'array',
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Approval authority records are immutable.'));
        static::deleting(fn () => throw new LogicException('Approval authority records are immutable.'));
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
